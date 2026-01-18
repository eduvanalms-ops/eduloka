<?php
require_once __DIR__ . '/config/config.php';
require_login();

$user = get_logged_user();
$kursus_id = (int)($_GET['id'] ?? 0);

if (!$kursus_id) {
    header('Location: /index.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT k.*, p.nama_id as prodi_nama, u.full_name as pengajar_nama, u.id as pengajar_id
    FROM kursus k
    LEFT JOIN program_studi p ON k.program_studi_id = p.id
    LEFT JOIN users u ON k.pengajar_id = u.id
    WHERE k.id = ?
");
$stmt->execute([$kursus_id]);
$course = $stmt->fetch();

if (!$course) {
    set_flash('error', 'Mata kuliah tidak ditemukan');
    header('Location: /index.php');
    exit;
}

$is_pengajar = ($user['role'] === 'pengajar' && $user['id'] == $course['pengajar_id']);
$is_admin = ($user['role'] === 'admin');
$is_enrolled = false;
$can_edit = $is_pengajar || $is_admin;

if ($user['role'] === 'mahasiswa') {
    $stmt = $pdo->prepare("SELECT 1 FROM kursus_enrollments WHERE kursus_id = ? AND mahasiswa_id = ?");
    $stmt->execute([$kursus_id, $user['id']]);
    $is_enrolled = $stmt->fetchColumn() > 0;
    
    if (!$is_enrolled && !$is_admin) {
        set_flash('error', 'Anda belum terdaftar di mata kuliah ini');
        header('Location: /modules/mahasiswa/browse_courses.php');
        exit;
    }
}

require_once __DIR__ . '/config/activity_logger.php';

// Convert video URL to embed format
function convertVideoUrlToEmbed($url) {
    if (empty($url)) return '';
    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]+)/', $url, $matches)) {
        return 'https://www.youtube.com/embed/' . $matches[1];
    }
    if (preg_match('/vimeo\.com\/(\d+)/', $url, $matches)) {
        return 'https://player.vimeo.com/video/' . $matches[1];
    }
    return $url;
}

// Handle SESSION DELETE (only for teachers/admin)
if ($can_edit && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_session'])) {
    require_csrf();
    $session_id = (int)$_POST['delete_session'];
    $stmt = $pdo->prepare("DELETE FROM course_sessions WHERE id = ? AND kursus_id = ?");
    if ($stmt->execute([$session_id, $kursus_id])) {
        log_activity('delete_session', 'course_session', $session_id, "Hapus sesi dari kursus $kursus_id", 'success');
        set_flash('success', 'Sesi pertemuan berhasil dihapus');
    }
    header("Location: ?id=$kursus_id");
    exit;
}

// Handle SESSION CREATE/UPDATE (only for teachers/admin)
if ($can_edit && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['session_action']) && $_POST['session_action'] === 'save') {
    require_csrf();
    $id = $_POST['session_id'] ?? null;
    $nomor_pertemuan = (int)($_POST['nomor_pertemuan'] ?? 0);
    $judul = $_POST['session_judul'] ?? '';
    $deskripsi = $_POST['session_deskripsi'] ?? '';
    
    $lang = get_language();
    if ($nomor_pertemuan && $judul) {
        try {
            if ($id) {
                $stmt_get = $pdo->prepare("SELECT judul_id, judul_en, deskripsi_id, deskripsi_en FROM course_sessions WHERE id = ?");
                $stmt_get->execute([$id]);
                $existing = $stmt_get->fetch();
                $final_judul_id = $lang === 'id' ? $judul : $existing['judul_id'];
                $final_judul_en = $lang === 'en' ? $judul : $existing['judul_en'];
                $final_deskripsi_id = $lang === 'id' ? $deskripsi : $existing['deskripsi_id'];
                $final_deskripsi_en = $lang === 'en' ? $deskripsi : $existing['deskripsi_en'];
                
                $stmt = $pdo->prepare("UPDATE course_sessions SET nomor_pertemuan = ?, judul_id = ?, judul_en = ?, deskripsi_id = ?, deskripsi_en = ? WHERE id = ? AND kursus_id = ?");
                $stmt->execute([$nomor_pertemuan, $final_judul_id, $final_judul_en, $final_deskripsi_id, $final_deskripsi_en, $id, $kursus_id]);
                set_flash('success', 'Sesi pertemuan berhasil diperbarui');
            } else {
                $final_judul_id = ($lang === 'id') ? $judul : '';
                $final_judul_en = ($lang === 'en') ? $judul : '';
                $final_deskripsi_id = ($lang === 'id') ? $deskripsi : '';
                $final_deskripsi_en = ($lang === 'en') ? $deskripsi : '';
                
                $stmt = $pdo->prepare("INSERT INTO course_sessions (kursus_id, nomor_pertemuan, judul_id, judul_en, deskripsi_id, deskripsi_en) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$kursus_id, $nomor_pertemuan, $final_judul_id, $final_judul_en, $final_deskripsi_id, $final_deskripsi_en]);
                set_flash('success', 'Sesi pertemuan berhasil ditambahkan');
            }
        } catch (Exception $e) {
            set_flash('error', 'Error: ' . $e->getMessage());
        }
    }
    header("Location: ?id=$kursus_id");
    exit;
}

// Handle ACTIVITY DELETE (only for teachers/admin)
if ($can_edit && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_activity'])) {
    require_csrf();
    $activity_id = (int)$_POST['delete_activity'];
    $stmt = $pdo->prepare("DELETE FROM aktivitas WHERE id = ? AND kursus_id = ?");
    if ($stmt->execute([$activity_id, $kursus_id])) {
        log_activity('delete_activity', 'aktivitas', $activity_id, "Hapus aktivitas dari kursus $kursus_id", 'success');
        set_flash('success', 'Aktivitas berhasil dihapus');
    }
    header("Location: ?id=$kursus_id");
    exit;
}

// Handle ACTIVITY CREATE/UPDATE (only for teachers/admin)
if ($can_edit && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activity_action']) && $_POST['activity_action'] === 'save') {
    require_csrf();
    $id = $_POST['activity_id'] ?? null;
    $session_id = (int)($_POST['session_id'] ?? 0);
    $judul = $_POST['activity_judul'] ?? '';
    $deskripsi = $_POST['activity_deskripsi'] ?? '';
    $tipe = $_POST['activity_tipe'] ?? 'materi';
    $video_url = $_POST['activity_video_url'] ?? '';
    
    if ($tipe === 'video' && $video_url) {
        $video_url = convertVideoUrlToEmbed($video_url);
    }
    
    $lang = get_language();
    if ($judul && $tipe && $session_id) {
        try {
            if ($id) {
                $stmt_get = $pdo->prepare("SELECT judul_id, judul_en, deskripsi_id, deskripsi_en, video_url FROM aktivitas WHERE id = ?");
                $stmt_get->execute([$id]);
                $existing = $stmt_get->fetch();
                $final_judul_id = $lang === 'id' ? $judul : $existing['judul_id'];
                $final_judul_en = $lang === 'en' ? $judul : $existing['judul_en'];
                $final_deskripsi_id = $lang === 'id' ? $deskripsi : $existing['deskripsi_id'];
                $final_deskripsi_en = $lang === 'en' ? $deskripsi : $existing['deskripsi_en'];
                $final_video_url = $video_url ?: $existing['video_url'];
                
                $stmt = $pdo->prepare("UPDATE aktivitas SET judul_id = ?, judul_en = ?, deskripsi_id = ?, deskripsi_en = ?, tipe = ?, video_url = ? WHERE id = ? AND session_id = ?");
                $stmt->execute([$final_judul_id, $final_judul_en, $final_deskripsi_id, $final_deskripsi_en, $tipe, $final_video_url, $id, $session_id]);
                set_flash('success', 'Aktivitas berhasil diperbarui');
            } else {
                $final_judul_id = ($lang === 'id') ? $judul : '';
                $final_judul_en = ($lang === 'en') ? $judul : '';
                $final_deskripsi_id = ($lang === 'id') ? $deskripsi : '';
                $final_deskripsi_en = ($lang === 'en') ? $deskripsi : '';
                
                $stmt = $pdo->prepare("INSERT INTO aktivitas (kursus_id, session_id, judul_id, judul_en, deskripsi_id, deskripsi_en, tipe, video_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$kursus_id, $session_id, $final_judul_id, $final_judul_en, $final_deskripsi_id, $final_deskripsi_en, $tipe, $video_url]);
                set_flash('success', 'Aktivitas berhasil ditambahkan');
            }
        } catch (Exception $e) {
            set_flash('error', 'Error: ' . $e->getMessage());
        }
    }
    header("Location: ?id=$kursus_id");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM course_sessions WHERE kursus_id = ? ORDER BY nomor_pertemuan ASC");
$stmt->execute([$kursus_id]);
$sessions = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM kursus_enrollments WHERE kursus_id = ?");
$stmt->execute([$kursus_id]);
$student_count = $stmt->fetchColumn();

$nama_kursus = get_language() === 'id' ? $course['nama_id'] : $course['nama_en'];
$deskripsi = get_language() === 'id' ? $course['deskripsi_id'] : $course['deskripsi_en'];
$page_title = $nama_kursus;

$activity_types = ['materi' => 'Materi', 'video' => 'Video', 'quiz' => 'Kuis', 'tugas' => 'Tugas', 'forum' => 'Forum'];

require __DIR__ . '/components/header.php';
?>

<style>
.course-header-section { display: flex; flex-wrap: wrap; gap: 2rem; align-items: flex-start; }
.course-info { flex: 1; min-width: 300px; }
.course-info h1 { margin-top: 0; }
.course-controls { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.session-accordion { border-radius: 8px; border: 1px solid #dee2e6; margin-bottom: 1rem; }
.session-header { padding: 1rem; cursor: pointer; background: linear-gradient(135deg, #007E6E 0%, #005f56 100%); color: white; border-radius: 8px 8px 0 0; display: flex; justify-content: space-between; align-items: center; }
.session-header:hover { background: linear-gradient(135deg, #005f56 0%, #004844 100%); }
.session-header.collapsed { border-radius: 8px; }
.session-content { padding: 1.5rem; display: none; }
.session-content.show { display: block; }
.activity-item { padding: 1rem; border: 1px solid #e9ecef; border-radius: 6px; margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center; background: #f8f9fa; transition: all 0.3s ease; }
.activity-item:hover { background: #e8f5f4; box-shadow: 0 2px 8px rgba(0,126,110,0.15); }
.activity-item a.activity-info:hover, .activity-item:has(a.activity-info:hover) { transform: translateX(4px); }
.activity-item strong { color: #007E6E; }
.activity-info { flex: 1; display: flex; align-items: center; flex-wrap: wrap; gap: 0.5rem; cursor: pointer; text-decoration: none; color: inherit; }
.activity-info:hover { color: #005f56; }
.activity-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.85rem; }
.edit-controls { display: flex; gap: 0.5rem; align-items: center; }
.edit-controls.hidden { display: none; }
.completion-indicator { 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    width: 24px; 
    height: 24px; 
    border: 2px solid #dee2e6; 
    border-radius: 4px; 
    font-weight: bold; 
    margin-left: 0.5rem;
    font-size: 16px;
    flex-shrink: 0;
    background: white;
}
.completion-indicator.completed { 
    border-color: #28a745; 
    background-color: #28a745; 
    color: white;
}
.completion-indicator.not-completed { 
    border-color: #dc3545; 
    background-color: #fff3f3;
    color: #dc3545;
}
@media (max-width: 768px) {
    .course-header-section { flex-direction: column; gap: 1rem; }
    .course-controls { width: 100%; }
}
</style>

<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/index.php"><?php echo t('dashboard'); ?></a></li>
            <li class="breadcrumb-item active"><?php echo htmlspecialchars($nama_kursus); ?></li>
        </ol>
    </nav>
    
    <div class="course-header-section mb-4">
        <div class="course-info">
            <h1><i class="fas fa-book"></i> <?php echo htmlspecialchars($nama_kursus); ?></h1>
            <p class="text-muted"><?php echo htmlspecialchars($deskripsi); ?></p>
            
            <div class="mt-3">
                <span class="badge bg-primary me-2"><?php echo $course['sks']; ?> SKS</span>
                <span class="badge bg-info me-2"><i class="fas fa-users"></i> <?php echo $student_count; ?> <?php echo t('students'); ?></span>
                <span class="badge bg-secondary"><i class="fas fa-chalkboard-teacher"></i> <?php echo htmlspecialchars($course['pengajar_nama'] ?? '-'); ?></span>
            </div>
        </div>
        
        <?php if ($can_edit): ?>
        <div class="course-controls">
            <button id="editToggleBtn" class="btn btn-sm btn-warning" onclick="toggleEditMode()" style="white-space: nowrap;">
                <i class="fas fa-toggle-off"></i> Edit OFF
            </button>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#sessionModal" onclick="newSession()" style="white-space: nowrap;">
                <i class="fas fa-plus"></i> Tambah Sesi
            </button>
            <a href="/manage_attendance.php?kursus_id=<?php echo $kursus_id; ?>" class="btn btn-sm btn-info" style="white-space: nowrap;">
                <i class="fas fa-clipboard-check"></i> Kelola Presensi
            </a>
        </div>
        <?php endif; ?>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div id="sessionsContainer">
                <?php if (empty($sessions)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-inbox"></i> Belum ada sesi & aktivitas
                    <?php if ($can_edit): ?>
                    <button class="btn btn-sm btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#sessionModal" onclick="newSession()">Buat sekarang</button>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                    <?php foreach ($sessions as $session): 
                        $s_judul = get_language() === 'id' ? $session['judul_id'] : $session['judul_en'];
                        
                        $stmt_act = $pdo->prepare("SELECT * FROM aktivitas WHERE session_id = ? ORDER BY urutan ASC");
                        $stmt_act->execute([$session['id']]);
                        $activities = $stmt_act->fetchAll();
                    ?>
                    <div class="session-accordion">
                        <div class="session-header" style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="flex: 1; cursor: pointer;" onclick="toggleSession(this.parentElement)">
                                <h5 class="mb-1">
                                    <i class="fas fa-chevron-right me-2" style="transition: transform 0.3s;"></i>
                                    Pertemuan Ke-<?php echo $session['nomor_pertemuan']; ?>: <?php echo htmlspecialchars($s_judul); ?>
                                </h5>
                                <small class="d-block mt-1" style="opacity: 0.9;">
                                    <i class="fas fa-tasks"></i> <?php echo count($activities); ?> Aktivitas
                                </small>
                            </div>
                            <?php if ($can_edit): ?>
                            <div class="edit-controls dropdown">
                                <button class="btn btn-sm btn-light" data-bs-toggle="dropdown" style="white-space: nowrap;">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="#" onclick="editSession(<?php echo htmlspecialchars(json_encode($session)); ?>); return false;">
                                        <i class="fas fa-edit"></i> Edit Sesi
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteSession(<?php echo $session['id']; ?>, '<?php echo htmlspecialchars(addslashes($s_judul)); ?>'); return false;">
                                        <i class="fas fa-trash"></i> Hapus Sesi
                                    </a></li>
                                </ul>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="session-content">
                            <div class="mb-4">
                                <h6 class="mb-3"><i class="fas fa-list"></i> Daftar Aktivitas</h6>
                                <?php if (empty($activities)): ?>
                                <p class="text-muted small">Belum ada aktivitas</p>
                                <?php else: ?>
                                    <?php $counter = 0; foreach ($activities as $activity): $counter++; 
                                        $a_judul = get_language() === 'id' ? $activity['judul_id'] : $activity['judul_en'];
                                        $a_type = $activity_types[$activity['tipe']] ?? $activity['tipe'];
                                    ?>
                                    <div class="activity-item">
                                        <a href="/activity_view.php?id=<?php echo $activity['id']; ?>" class="activity-info">
                                            <span class="badge bg-secondary me-2"><?php echo $session['nomor_pertemuan']; ?>.<?php echo chr(96 + $counter); ?></span>
                                            <strong class="text-dark"><?php echo htmlspecialchars($a_judul); ?></strong>
                                            <span class="activity-badge bg-light text-dark ms-2"><?php echo $a_type; ?></span>
                                            <?php if ($activity['tipe'] === 'materi'): ?>
                                                <?php 
                                                $stmt_f = $pdo->prepare("SELECT COUNT(*) as cnt FROM files WHERE aktivitas_id = ?");
                                                $stmt_f->execute([$activity['id']]);
                                                $fcnt = $stmt_f->fetch()['cnt'];
                                                if ($fcnt > 0):
                                                ?>
                                                <span class="activity-badge bg-info text-white ms-2"><i class="fas fa-file"></i> <?php echo $fcnt; ?></span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </a>
                                        <?php if ($is_enrolled): ?>
                                            <div class="completion-indicator" id="status-<?php echo $activity['id']; ?>" title="Klik untuk refresh">
                                                ◻
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($can_edit): ?>
                                        <div class="edit-controls">
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-light" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item" href="#" onclick="editActivity(<?php echo htmlspecialchars(json_encode($activity)); ?>); return false;">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteActivity(<?php echo $activity['id']; ?>, '<?php echo htmlspecialchars(addslashes($a_judul)); ?>'); return false;">
                                                        <i class="fas fa-trash"></i> Hapus
                                                    </a></li>
                                                </ul>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <?php if ($can_edit): ?>
                            <div class="edit-controls">
                                <button class="btn btn-sm btn-outline-primary" onclick="newActivity(<?php echo $session['id']; ?>, <?php echo $session['nomor_pertemuan']; ?>)">
                                    <i class="fas fa-plus"></i> Tambah Aktivitas
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Edit Mode Toggle
let editMode = localStorage.getItem('editMode') === 'true';

function initEditMode() {
    updateEditModeUI();
    applyEditModeStyles();
}

function toggleEditMode() {
    editMode = !editMode;
    localStorage.setItem('editMode', editMode);
    updateEditModeUI();
    applyEditModeStyles();
}

function updateEditModeUI() {
    const btn = document.getElementById('editToggleBtn');
    if (btn) {
        if (editMode) {
            btn.innerHTML = '<i class="fas fa-toggle-on"></i> Edit ON';
            btn.classList.remove('btn-warning');
            btn.classList.add('btn-success');
        } else {
            btn.innerHTML = '<i class="fas fa-toggle-off"></i> Edit OFF';
            btn.classList.remove('btn-success');
            btn.classList.add('btn-warning');
        }
    }
}

function applyEditModeStyles() {
    const controls = document.querySelectorAll('.edit-controls');
    controls.forEach(ctrl => {
        if (editMode) {
            ctrl.classList.remove('hidden');
        } else {
            ctrl.classList.add('hidden');
        }
    });
}

function toggleSession(header) {
    const icon = header.querySelector('.fa-chevron-right');
    const content = header.nextElementSibling;
    const sessionTitle = header.querySelector('h5').textContent.trim();
    
    // Toggle display
    icon.style.transform = content.classList.contains('show') ? 'rotate(0deg)' : 'rotate(90deg)';
    content.classList.toggle('show');
    
    // Save state to localStorage
    const courseId = <?php echo $kursus_id; ?>;
    const storageKey = `course_${courseId}_openSession`;
    if (content.classList.contains('show')) {
        localStorage.setItem(storageKey, sessionTitle);
        console.log('💾 Saved open session:', sessionTitle);
    } else {
        localStorage.removeItem(storageKey);
        console.log('🗑️ Cleared session memory');
    }
}

function restoreSessionState() {
    const courseId = <?php echo $kursus_id; ?>;
    const storageKey = `course_${courseId}_openSession`;
    const savedSessionTitle = localStorage.getItem(storageKey);
    
    if (!savedSessionTitle) {
        console.log('📭 No saved session found');
        return;
    }
    
    // Find and open the session that matches the saved title
    const headers = document.querySelectorAll('.session-header');
    headers.forEach(header => {
        const title = header.querySelector('h5').textContent.trim();
        if (title === savedSessionTitle) {
            const content = header.nextElementSibling;
            const icon = header.querySelector('.fa-chevron-right');
            
            // Open it
            if (!content.classList.contains('show')) {
                icon.style.transform = 'rotate(90deg)';
                content.classList.add('show');
                console.log('📖 Restored session:', savedSessionTitle);
                
                // Smooth scroll to it
                setTimeout(() => {
                    header.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 100);
            }
        }
    });
}

// Initialize page on load
document.addEventListener('DOMContentLoaded', function() {
    initEditMode();
    restoreSessionState();
    loadActivityStatus();
});

// Load activity completion status for students
function loadActivityStatus() {
    const courseId = <?php echo $kursus_id; ?>;
    const isStudent = <?php echo $is_enrolled ? 'true' : 'false'; ?>;
    
    if (!isStudent) return;
    
    // First check sessionStorage for instant completed activities (no API wait)
    const completedActivities = JSON.parse(sessionStorage.getItem('completedActivities') || '[]');
    completedActivities.forEach(activityId => {
        const indicator = document.getElementById(`status-${activityId}`);
        if (indicator && !indicator.classList.contains('completed')) {
            indicator.innerHTML = '✓';
            indicator.classList.add('completed');
            indicator.classList.remove('not-completed');
            indicator.title = 'Sudah dituntaskan';
            console.log('✓ Updated from sessionStorage:', activityId);
        }
    });
    
    // Then fetch from API for authoritative data
    fetch(`/api/get_activity_status.php?kursus_id=${courseId}`)
        .then(r => r.json())
        .then(data => {
            console.log('Activity status response:', data);
            if (data.success && data.statuses) {
                Object.entries(data.statuses).forEach(([activityId, completed]) => {
                    const indicator = document.getElementById(`status-${activityId}`);
                    if (indicator) {
                        if (completed) {
                            indicator.innerHTML = '✓';
                            indicator.classList.add('completed');
                            indicator.classList.remove('not-completed');
                            indicator.title = 'Sudah dituntaskan';
                            console.log('✓ Activity ' + activityId + ' marked as completed');
                        } else {
                            indicator.innerHTML = '◻';
                            indicator.classList.add('not-completed');
                            indicator.classList.remove('completed');
                            indicator.title = 'Belum dituntaskan';
                            console.log('◻ Activity ' + activityId + ' marked as not completed');
                        }
                    }
                });
            }
        })
        .catch(err => console.error('Error loading activity status:', err));
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initEditMode();
    loadActivityStatus();
});

// Refresh on visibility change (when user returns to page)
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        loadActivityStatus();
    }
});
</script>

<!-- Session Modal -->
<?php if ($can_edit): ?>
<div class="modal fade" id="sessionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="session_action" value="save">
                <input type="hidden" name="session_id" id="session_id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="sessionTitle">Tambah Sesi Pertemuan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nomor Pertemuan *</label>
                        <input type="number" name="nomor_pertemuan" id="nomor_pertemuan" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Judul <?php echo get_language() === 'id' ? '(Indonesia)' : '(English)'; ?> *</label>
                        <input type="text" name="session_judul" id="session_judul" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="session_deskripsi" id="session_deskripsi" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Activity Modal -->
<div class="modal fade" id="activityModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="activity_action" value="save">
                <input type="hidden" name="activity_id" id="activity_id">
                <input type="hidden" name="session_id" id="activity_session_id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="activityTitle">Tambah Aktivitas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tipe Aktivitas *</label>
                        <select name="activity_tipe" id="activity_tipe" class="form-select" required>
                            <?php foreach ($activity_types as $key => $label): ?>
                            <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Judul <?php echo get_language() === 'id' ? '(Indonesia)' : '(English)'; ?> *</label>
                        <input type="text" name="activity_judul" id="activity_judul" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="activity_deskripsi" id="activity_deskripsi" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3" id="videoUrlField" style="display: none;">
                        <label class="form-label">URL Video</label>
                        <input type="url" name="activity_video_url" id="activity_video_url" class="form-control" placeholder="https://www.youtube.com/watch?v=...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function newSession() {
    document.getElementById('session_id').value = '';
    document.getElementById('sessionTitle').textContent = 'Tambah Sesi Pertemuan';
    document.getElementById('nomor_pertemuan').value = '';
    document.getElementById('session_judul').value = '';
    document.getElementById('session_deskripsi').value = '';
}

function editSession(session) {
    const lang = '<?php echo get_language(); ?>';
    document.getElementById('sessionTitle').textContent = 'Edit Sesi Pertemuan';
    document.getElementById('session_id').value = session.id;
    document.getElementById('nomor_pertemuan').value = session.nomor_pertemuan;
    const judul = lang === 'id' ? (session.judul_id || session.judul_en) : (session.judul_en || session.judul_id);
    const deskripsi = lang === 'id' ? (session.deskripsi_id || session.deskripsi_en) : (session.deskripsi_en || session.deskripsi_id);
    document.getElementById('session_judul').value = judul || '';
    document.getElementById('session_deskripsi').value = deskripsi || '';
    new bootstrap.Modal(document.getElementById('sessionModal')).show();
}

function newActivity(sessionId, sessionNo) {
    document.getElementById('activity_id').value = '';
    document.getElementById('activityTitle').textContent = 'Tambah Aktivitas - Pertemuan ' + sessionNo;
    document.getElementById('activity_session_id').value = sessionId;
    document.getElementById('activity_tipe').value = 'materi';
    document.getElementById('activity_judul').value = '';
    document.getElementById('activity_deskripsi').value = '';
    document.getElementById('activity_video_url').value = '';
    document.getElementById('videoUrlField').style.display = 'none';
    new bootstrap.Modal(document.getElementById('activityModal')).show();
}

function editActivity(activity) {
    const lang = '<?php echo get_language(); ?>';
    document.getElementById('activityTitle').textContent = 'Edit Aktivitas';
    document.getElementById('activity_id').value = activity.id;
    document.getElementById('activity_session_id').value = activity.session_id;
    document.getElementById('activity_tipe').value = activity.tipe;
    const judul = lang === 'id' ? (activity.judul_id || activity.judul_en) : (activity.judul_en || activity.judul_id);
    const deskripsi = lang === 'id' ? (activity.deskripsi_id || activity.deskripsi_en) : (activity.deskripsi_en || activity.deskripsi_id);
    document.getElementById('activity_judul').value = judul || '';
    document.getElementById('activity_deskripsi').value = deskripsi || '';
    document.getElementById('activity_video_url').value = activity.video_url || '';
    document.getElementById('videoUrlField').style.display = activity.tipe === 'video' ? 'block' : 'none';
    
    document.getElementById('activity_tipe').onchange = function() {
        document.getElementById('videoUrlField').style.display = this.value === 'video' ? 'block' : 'none';
    };
    
    new bootstrap.Modal(document.getElementById('activityModal')).show();
}

function deleteActivity(activityId, title) {
    if (confirm('Hapus aktivitas "' + title + '"?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<?php echo csrf_field(); ?><input type="hidden" name="delete_activity" value="${activityId}">`;
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteSession(sessionId, title) {
    if (confirm('Hapus sesi dan semua aktivitasnya?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<?php echo csrf_field(); ?><input type="hidden" name="delete_session" value="${sessionId}">`;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
<?php endif; ?>

<?php require __DIR__ . '/components/footer.php'; ?>
