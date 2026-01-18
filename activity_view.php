<?php
require_once __DIR__ . '/config/config.php';
require_login();

$user = get_logged_user();
$activity_id = (int)($_GET['id'] ?? 0);

if (!$activity_id) {
    header('Location: /index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT a.*, k.id as kursus_id, k.nama_id as kursus_nama FROM aktivitas a JOIN kursus k ON a.kursus_id = k.id WHERE a.id = ?");
$stmt->execute([$activity_id]);
$activity = $stmt->fetch();

if (!$activity) {
    set_flash('error', 'Aktivitas tidak ditemukan');
    header('Location: /index.php');
    exit;
}

$lang = get_language();
$judul = !empty($activity['judul_' . $lang]) ? $activity['judul_' . $lang] : $activity['judul_' . ($lang === 'id' ? 'en' : 'id')];
$deskripsi = !empty($activity['deskripsi_' . $lang]) ? $activity['deskripsi_' . $lang] : $activity['deskripsi_' . ($lang === 'id' ? 'en' : 'id')];
$page_title = $judul;

$stmt = $pdo->prepare("SELECT tipe FROM aktivitas_tipe WHERE aktivitas_id = ? ORDER BY tipe");
$stmt->execute([$activity_id]);
$activity_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (empty($activity_types)) {
    $activity_types = [$activity['tipe']];
}

$stmt = $pdo->prepare("SELECT * FROM files WHERE aktivitas_id = ? ORDER BY uploaded_at DESC");
$stmt->execute([$activity_id]);
$files = $stmt->fetchAll();

require __DIR__ . '/components/header.php';
?>

<script>
// Global activity tracking and instant progress update
var activityId = <?php echo (int)$activity_id; ?>;      // ✅ HARUS var, sebelumnya pakai const - pakai var agar bisa diubah
var kursusId = <?php echo (int)$activity['kursus_id']; ?>;
var currentUserId = <?php echo (int)$user['id']; ?>;

// Function to get CSRF token from meta tag
function getCsrfToken() {
    const metaToken = document.querySelector('meta[name="csrf-token"]');
    return metaToken ? metaToken.getAttribute('content') : '';
}

// Function to get language
function get_language() {
    // Placeholder for language retrieval, replace with actual implementation if needed
    return 'id'; // Default to Indonesian
}

// Function to escape HTML entities
function escapeHtml(text) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}

// Function to mark activity completed instantly
function markActivityCompleted() {
    // Mark in sessionStorage for instant UI update
    const completed = JSON.parse(sessionStorage.getItem('completedActivities') || '[]');
    if (!completed.includes(activityId)) {
        completed.push(activityId);
        sessionStorage.setItem('completedActivities', JSON.stringify(completed));
        console.log('✓ Activity ' + activityId + ' marked as completed');
    }

    // Update indicator in parent window if accessible
    try {
        const indicator = window.parent.document.getElementById('status-' + activityId);
        if (indicator) {
            indicator.innerHTML = '✓';
            indicator.classList.add('completed');
            indicator.classList.remove('not-completed');
            indicator.title = 'Sudah dituntaskan';
            console.log('✓ Indicator updated in parent window');
        }
    } catch (e) {
        console.log('✓ Parent indicator update skipped (cross-origin or not in iframe)');
    }
}

// Track activity access on page load
if (activityId) {
    const trackUrl = '/api/track_activity_access.php?activity_id=' + activityId;

    fetch(trackUrl, {
        method: 'POST',
        credentials: 'include'
    })
    .then(r => {
        if (!r.ok) {
            console.warn('Track response not OK:', r.status, r.statusText);
            return r.text().then(text => {
                try { return JSON.parse(text); }
                catch(e) { return { error: text }; }
            });
        }
        return r.json();
    })
    .then(data => {
        console.log('✓ Activity access tracked:', data);
        if (data.success) {
            // Mark activity as completed instantly
            markActivityCompleted();
        }
    })
    .catch(err => {
        console.error('✗ Error tracking activity access:', err);
    });
}

// Hook into form submissions to mark activity as completed
document.addEventListener('DOMContentLoaded', function() {
    // Find all submission forms (tugas, forum, quiz)
    const tugiSubmitBtn = document.querySelector('button[type="submit"][value*="kirim"]');
    const forumSubmitBtn = document.querySelector('button[type="submit"][value*="post"]');

    // Helper to mark completed on button click
    function onSubmissionSuccess(event) {
        setTimeout(() => {
            if (!event.target.closest('form').classList.contains('has-error')) {
                markActivityCompleted();
            }
        }, 100);
    }

    // Find all submit buttons and forms
    document.querySelectorAll('form').forEach(form => {
        // Add listener only if it's not the forum reply form which is handled separately
        if (form.id !== 'forumReplyForm' && form.id !== 'submissionForm') {
             form.addEventListener('submit', function(e) {
                // After form submits, mark as completed if successful (check after delay)
                setTimeout(() => {
                    // If there's no error message shown, assume success
                    if (!form.querySelector('.alert-danger')) {
                        markActivityCompleted();
                        console.log('✓ Form submission - Activity marked as completed');
                    }
                }, 500);
            });
        }
    });

    // Handle forum reply submission separately
    const forumForm = document.getElementById('forumReplyForm');
    if (forumForm) {
        forumForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const submitBtn = document.getElementById('forumSubmitBtn');
            const feedback = document.getElementById('forumFeedback');
            const kontenField = document.getElementById('forumKonten');
            const formData = new FormData(this);

            // Add CSRF token
            const csrfToken = getCsrfToken();
            if (csrfToken) {
                formData.append('csrf_token', csrfToken);
            }

            // Disable button
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Mengirim...';
            feedback.style.display = 'none';

            try {
                const response = await fetch('/api/post_forum.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    // Show success message
                    feedback.style.display = 'block';
                    feedback.style.backgroundColor = '#d4edda';
                    feedback.style.color = '#155724';
                    feedback.style.border = '1px solid #c3e6cb';
                    feedback.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;

                    // Clear textarea
                    kontenField.value = '';

                    // Mark activity as completed
                    if (typeof markActivityCompleted === 'function') {
                        markActivityCompleted();
                    }

                    // Reload page after 1 second to show new post
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    // Show error message
                    feedback.style.display = 'block';
                    feedback.style.backgroundColor = '#f8d7da';
                    feedback.style.color = '#721c24';
                    feedback.style.border = '1px solid #f5c6cb';
                    feedback.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + (data.error || 'Gagal mengirim balasan');

                    // Re-enable button
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Kirim Balasan';
                }
            } catch (err) {
                // Show error message
                feedback.style.display = 'block';
                feedback.style.backgroundColor = '#f8d7da';
                feedback.style.color = '#721c24';
                feedback.style.border = '1px solid #f5c6cb';
                feedback.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error: ' + err.message;

                // Re-enable button
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Kirim Balasan';
            }
        });
    }
});
</script>

<link rel="stylesheet" href="/assets/css/forum.css">

<style>
    textarea[name="konten"] {
        padding: 20px 25px !important;
        line-height: 1.6 !important;
        font-size: 14px !important;
    }

    textarea[name="konten"]::placeholder {
        color: #ccc;
        opacity: 1;
    }
</style>

<style>
    body {
        overflow-y: scroll;
    }

    .video-container {
        max-width: 800px;
        margin: 0 auto 1.5rem;
        width: 100%;
    }

    .video-wrapper {
        position: relative;
        width: 100%;
        padding-bottom: 56.25%; /* 16:9 aspect ratio */
        height: 0;
        overflow: hidden;
        border-radius: 8px;
        background: #000;
    }

    .video-wrapper iframe {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        border: none;
    }

    /* Mobile optimization */
    @media (max-width: 768px) {
        .video-container {
            max-width: 100%;
            padding: 0 0.5rem;
        }
    }

    @media (max-width: 480px) {
        .video-container {
            max-width: 100%;
            padding: 0;
        }
    }

    #fileViewerModal {
        display: none !important;
        position: fixed;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        height: 100% !important;
        background: rgba(0,0,0,0.9) !important;
        z-index: 99999 !important;
        padding: 0 !important;
        margin: 0 !important;
        overflow: hidden !important;
        inset: 0 !important;
    }

    #fileViewerModal.active {
        display: flex !important;
        flex-direction: column !important;
    }

    .file-viewer-container {
        width: 100% !important;
        height: 100% !important;
        display: flex !important;
        flex-direction: column !important;
        background-color: white !important;
        max-width: none !important;
    }

    .file-viewer-header {
        background-color: #007E6E;
        color: white;
        padding: 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0;
        gap: 1rem;
    }

    .file-viewer-header strong {
        flex: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        min-width: 0;
    }

    .file-controls {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }

    .page-info {
        color: white;
        font-size: 0.9rem;
        min-width: 100px;
    }

    .file-viewer-content {
        flex: 1;
        overflow: auto;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        background-color: #f5f5f5;
    }

    .image-viewer {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    .image-viewer img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    .text-viewer {
        width: 100%;
        height: 100%;
        padding: 1rem;
        white-space: pre-wrap;
        word-break: break-word;
        font-family: 'Courier New', monospace;
        font-size: 0.9rem;
        overflow: auto;
        background-color: white;
        color: #333;
    }

    .office-viewer {
        width: 100%;
        height: 100%;
        border: none;
    }
</style>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/index.php"><?php echo t('dashboard'); ?></a></li>
                    <li class="breadcrumb-item"><a href="/course_view.php?id=<?php echo $activity['kursus_id']; ?>"><?php echo htmlspecialchars($activity['kursus_nama']); ?></a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($judul); ?></li>
                </ol>
            </nav>

            <h1>
                <?php
                $icon = '';
                switch($activity['tipe']) {
                    case 'materi': $icon = 'fa-file-alt'; break;
                    case 'video': $icon = 'fa-video'; break;
                    case 'quiz': $icon = 'fa-question-circle'; break;
                    case 'tugas': $icon = 'fa-tasks'; break;
                    case 'forum': $icon = 'fa-comments'; break;
                }
                ?>
                <i class="fas <?php echo $icon; ?>"></i> <?php echo htmlspecialchars($judul); ?>
            </h1>
            <p class="text-muted">
                <?php foreach ($activity_types as $type): ?>
                <span class="badge bg-primary me-1"><?php echo t($type); ?></span>
                <?php endforeach; ?>
            </p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <?php if ($activity['tipe'] === 'video' && !empty($activity['video_url'])): ?>
            <div class="video-container">
                <div class="video-wrapper">
                    <iframe
                        src="<?php echo htmlspecialchars($activity['video_url']); ?>"
                        title="<?php echo htmlspecialchars($judul); ?>"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen>
                    </iframe>
                </div>
            </div>
            <?php endif; ?>

            <div class="mb-4">
                <h5><i class="fas fa-align-left"></i> Deskripsi</h5>
                <p><?php echo nl2br(htmlspecialchars($deskripsi)); ?></p>
            </div>

            <?php if (!empty($files)): ?>
            <div class="mb-4">
                <h5><i class="fas fa-paperclip"></i> Dokumen Lampiran</h5>

                <div class="list-group mb-3">
                    <?php foreach ($files as $index => $file):
                        $ext = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
                        $is_viewable = in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'txt', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx']);
                    ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div class="flex-grow-1">
                            <div class="d-flex gap-2 align-items-center">
                                <?php if ($ext === 'pdf'): ?>
                                    <i class="fas fa-file-pdf" style="color: #dc3545;"></i>
                                <?php elseif (in_array($ext, ['jpg', 'jpeg', 'png'])): ?>
                                    <i class="fas fa-image" style="color: #007E6E;"></i>
                                <?php elseif (in_array($ext, ['doc', 'docx'])): ?>
                                    <i class="fas fa-file-word" style="color: #2b579a;"></i>
                                <?php elseif (in_array($ext, ['xls', 'xlsx'])): ?>
                                    <i class="fas fa-file-excel" style="color: #217346;"></i>
                                <?php elseif (in_array($ext, ['ppt', 'pptx'])): ?>
                                    <i class="fas fa-file-powerpoint" style="color: #c4351d;"></i>
                                <?php elseif ($ext === 'txt'): ?>
                                    <i class="fas fa-file-text" style="color: #6c757d;"></i>
                                <?php else: ?>
                                    <i class="fas fa-file" style="color: #6c757d;"></i>
                                <?php endif; ?>
                                <div>
                                    <strong><?php echo htmlspecialchars($file['file_name']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo round($file['file_size'] / 1024 / 1024, 2); ?> MB • <?php echo date('d M Y H:i', strtotime($file['uploaded_at'])); ?></small>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <?php if ($is_viewable): ?>
                            <button class="btn btn-sm btn-outline-primary" onclick="viewFile(<?php echo $index; ?>, '<?php echo htmlspecialchars($file['file_path']); ?>', '<?php echo htmlspecialchars($file['file_name']); ?>', '<?php echo $ext; ?>')">
                                <i class="fas fa-eye"></i> Lihat
                            </button>
                            <?php endif; ?>
                            <a href="<?php echo htmlspecialchars($file['file_path']); ?>" class="btn btn-sm btn-outline-secondary" download>
                                <i class="fas fa-download"></i> Download
                            </a>
                            <?php if (in_array($user['role'], ['admin', 'pengajar'])): ?>
                            <button class="btn btn-sm btn-outline-warning" onclick="editFile(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars($file['file_name']); ?>')" title="Edit File">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteFile(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars($file['file_name']); ?>')" title="Hapus File">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div id="fileViewerModal">
                    <div class="file-viewer-container">
                        <div class="file-viewer-header">
                            <strong id="viewerFileName">Dokumen</strong>
                            <div class="file-controls">
                                <span class="page-info" id="pageInfo" style="display: none;"></span>
                                <button class="btn btn-sm btn-light" id="prevPageBtn" style="display: none;" onclick="previousPage()" title="Halaman Sebelumnya">
                                    <i class="fas fa-chevron-up"></i>
                                </button>
                                <button class="btn btn-sm btn-light" id="nextPageBtn" style="display: none;" onclick="nextPage()" title="Halaman Berikutnya">
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                                <a id="downloadFileBtn" href="#" class="btn btn-sm btn-light" download title="Download File">
                                    <i class="fas fa-download"></i>
                                </a>
                                <button class="btn btn-sm btn-light" onclick="toggleFullscreen()" title="Fullscreen">
                                    <i class="fas fa-expand"></i>
                                </button>
                                <button class="btn btn-sm btn-light" onclick="closeFileViewer()" title="Tutup">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <div class="file-viewer-content" id="fileViewerContent">
                            <p class="text-muted">Loading...</p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($activity['tipe'] === 'quiz'): ?>
    <?php
    // Get quiz data - create if not exists
    $stmt = $pdo->prepare("SELECT * FROM kuis WHERE aktivitas_id = ?");
    $stmt->execute([$activity_id]);
    $kuis = $stmt->fetch();

    if (!$kuis) {
        $stmt = $pdo->prepare("INSERT INTO kuis (aktivitas_id, durasi, max_attempts, passing_score, show_correct_answers) VALUES (?, 30, 3, 60, true)");
        $stmt->execute([$activity_id]);
        $kuis = [
            'id' => $pdo->lastInsertId(),
            'aktivitas_id' => $activity_id,
            'durasi' => 30,
            'max_attempts' => 3,
            'passing_score' => 60,
            'show_correct_answers' => true
        ];
    }

    // Get quiz questions
    $stmt = $pdo->prepare("SELECT * FROM kuis_soal WHERE kuis_id = ? ORDER BY urutan");
    $stmt->execute([$kuis['id']]);
    $quiz_questions = $stmt->fetchAll();

    $is_teacher = in_array($user['role'], ['admin', 'pengajar']);

    // Get student's previous attempts
    $student_attempts = [];
    $can_attempt = true;
    if ($user['role'] === 'mahasiswa') {
        $stmt = $pdo->prepare("
            SELECT attempt, SUM(score) as total_score, MAX(submitted_at) as submitted_at,
                   SUM(CASE WHEN is_correct THEN 1 ELSE 0 END) as correct_count
            FROM kuis_jawaban
            WHERE kuis_id = ? AND mahasiswa_id = ?
            GROUP BY attempt
            ORDER BY attempt DESC
        ");
        $stmt->execute([$kuis['id'], $user['id']]);
        $student_attempts = $stmt->fetchAll();

        if ($kuis['max_attempts'] && count($student_attempts) >= $kuis['max_attempts']) {
            $can_attempt = false;
        }
    }

    // Calculate max score
    $max_score = 0;
    foreach ($quiz_questions as $q) {
        $max_score += $q['poin'];
    }
    ?>

    <?php if ($is_teacher): ?>
    <!-- Teacher View - Quiz Management -->
    <div class="card mb-4">
        <div class="card-header" style="background-color: #007E6E; color: white;">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-cog"></i> Pengaturan Quiz</h5>
                <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#quizSettingsModal">
                    <i class="fas fa-edit"></i> Edit
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <strong>Durasi:</strong> <?php echo $kuis['durasi']; ?> menit
                </div>
                <div class="col-md-3">
                    <strong>Max Percobaan:</strong> <?php echo $kuis['max_attempts'] ?: 'Tidak terbatas'; ?>
                </div>
                <div class="col-md-3">
                    <strong>Passing Score:</strong> <?php echo $kuis['passing_score']; ?>%
                </div>
                <div class="col-md-3">
                    <strong>Tampilkan Jawaban:</strong> <?php echo $kuis['show_correct_answers'] ? 'Ya' : 'Tidak'; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header" style="background-color: #007E6E; color: white;">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list"></i> Soal Quiz (<?php echo count($quiz_questions); ?>)</h5>
                <div>
                    <button class="btn btn-sm btn-light me-2" data-bs-toggle="modal" data-bs-target="#importQuestionModal">
                        <i class="fas fa-download"></i> Import dari Bank Soal
                    </button>
                    <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
                        <i class="fas fa-plus"></i> Tambah Soal
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($quiz_questions)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Belum ada soal. Tambahkan soal baru atau import dari bank soal.
            </div>
            <?php else: ?>
            <div class="accordion" id="questionsAccordion">
                <?php foreach ($quiz_questions as $idx => $q):
                    $pilihan = json_decode($q['pilihan_json'], true) ?: [];
                    $lang = get_language();
                    $soal_text = $lang === 'id' ? $q['soal_id'] : ($q['soal_en'] ?: $q['soal_id']);
                ?>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q<?php echo $q['id']; ?>">
                            <span class="badge bg-secondary me-2"><?php echo $idx + 1; ?></span>
                            <span class="badge bg-info me-2"><?php echo $q['poin']; ?> poin</span>
                            <?php echo htmlspecialchars(substr($soal_text, 0, 80)); ?>...
                        </button>
                    </h2>
                    <div id="q<?php echo $q['id']; ?>" class="accordion-collapse collapse" data-bs-parent="#questionsAccordion">
                        <div class="accordion-body">
                            <p><strong>Soal:</strong> <?php echo htmlspecialchars($soal_text); ?></p>
                            <?php if ($q['tipe'] === 'multiple_choice' && !empty($pilihan)): ?>
                            <div class="list-group mb-3">
                                <?php foreach ($pilihan as $key => $val): ?>
                                <div class="list-group-item <?php echo $key === $q['jawaban_benar'] ? 'list-group-item-success' : ''; ?>">
                                    <?php if ($key === $q['jawaban_benar']): ?>
                                    <i class="fas fa-check text-success me-2"></i>
                                    <?php endif; ?>
                                    <strong><?php echo $key; ?>.</strong> <?php echo htmlspecialchars($val); ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteQuizQuestion(<?php echo $q['id']; ?>)">
                                <i class="fas fa-trash"></i> Hapus
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quiz Results for Teacher -->
    <div class="card">
        <div class="card-header" style="background-color: #007E6E; color: white;">
            <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Hasil Quiz Mahasiswa</h5>
        </div>
        <div class="card-body">
            <?php
            $stmt = $pdo->prepare("
                SELECT u.id, u.full_name, u.email,
                       COUNT(DISTINCT kj.attempt) as attempts,
                       MAX(kj.submitted_at) as last_attempt
                FROM users u
                JOIN kuis_jawaban kj ON u.id = kj.mahasiswa_id
                WHERE kj.kuis_id = ?
                GROUP BY u.id
                ORDER BY last_attempt DESC
            ");
            $stmt->execute([$kuis['id']]);
            $quiz_results = $stmt->fetchAll();
            ?>
            <?php if (empty($quiz_results)): ?>
            <p class="text-muted">Belum ada mahasiswa yang mengerjakan quiz ini.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Mahasiswa</th>
                            <th>Percobaan</th>
                            <th>Nilai Terakhir</th>
                            <th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quiz_results as $result):
                            // Get last attempt score
                            $stmt = $pdo->prepare("
                                SELECT SUM(score) as score FROM kuis_jawaban
                                WHERE kuis_id = ? AND mahasiswa_id = ?
                                AND attempt = (SELECT MAX(attempt) FROM kuis_jawaban WHERE kuis_id = ? AND mahasiswa_id = ?)
                            ");
                            $stmt->execute([$kuis['id'], $result['id'], $kuis['id'], $result['id']]);
                            $last_score = $stmt->fetch()['score'] ?? 0;
                            $percentage = $max_score > 0 ? round(($last_score / $max_score) * 100, 1) : 0;
                            $passed = $percentage >= $kuis['passing_score'];
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($result['full_name']); ?></strong>
                                <br><small class="text-muted"><?php echo $result['email']; ?></small>
                            </td>
                            <td><?php echo $result['attempts']; ?> kali</td>
                            <td>
                                <span class="badge <?php echo $passed ? 'bg-success' : 'bg-warning'; ?>">
                                    <?php echo $percentage; ?>%
                                </span>
                            </td>
                            <td><?php echo date('d M Y H:i', strtotime($result['last_attempt'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quiz Settings Modal -->
    <div class="modal fade" id="quizSettingsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Pengaturan Quiz</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="quizSettingsForm">
                        <input type="hidden" name="kuis_id" value="<?php echo $kuis['id']; ?>">
                        <div class="mb-3">
                            <label class="form-label">Durasi (menit)</label>
                            <input type="number" name="durasi" class="form-control" value="<?php echo $kuis['durasi']; ?>" min="1">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Maksimal Percobaan</label>
                            <input type="number" name="max_attempts" class="form-control" value="<?php echo $kuis['max_attempts']; ?>" min="1">
                            <small class="text-muted">Kosongkan untuk tidak terbatas</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Passing Score (%)</label>
                            <input type="number" name="passing_score" class="form-control" value="<?php echo $kuis['passing_score']; ?>" min="0" max="100">
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="show_correct_answers" class="form-check-input" id="showAnswers" <?php echo $kuis['show_correct_answers'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="showAnswers">Tampilkan jawaban benar setelah submit</label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" onclick="saveQuizSettings()">Simpan</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Question Modal -->
    <div class="modal fade" id="addQuestionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Soal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addQuestionForm">
                        <input type="hidden" name="kuis_id" value="<?php echo $kuis['id']; ?>">
                        <div class="mb-3">
                            <label class="form-label">Pertanyaan *</label>
                            <textarea name="soal" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipe Soal</label>
                            <select name="tipe" class="form-select" id="questionType">
                                <option value="multiple_choice">Pilihan Ganda</option>
                                <option value="essay">Essay</option>
                            </select>
                        </div>
                        <div id="essayOptions" style="display: none;">
                            <label class="form-label">Kata Kunci Jawaban (untuk auto-grading)</label>
                            <div class="mb-2">
                                <input type="text" id="keywordInput" class="form-control mb-2" placeholder="Masukkan kata kunci dan tekan Enter">
                                <div id="keywordsList" class="d-flex flex-wrap gap-2 mb-2"></div>
                                <input type="hidden" name="keywords" id="keywordsHidden">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Bobot Keyword (0.0 - 1.0)</label>
                                <input type="number" name="keyword_weight" class="form-control" value="1.0" min="0" max="1" step="0.1">
                                <small class="text-muted">1.0 = 100% berdasarkan keyword, 0.5 = 50% keyword + 50% penilaian subjektif</small>
                            </div>
                        </div>
                        <div id="multipleChoiceOptions">
                            <label class="form-label">Pilihan Jawaban</label>
                            <div class="mb-2">
                                <div class="input-group">
                                    <span class="input-group-text">A</span>
                                    <input type="text" name="pilihan[A]" class="form-control" placeholder="Pilihan A">
                                    <div class="input-group-text">
                                        <input type="radio" name="jawaban_benar" value="A" required>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-2">
                                <div class="input-group">
                                    <span class="input-group-text">B</span>
                                    <input type="text" name="pilihan[B]" class="form-control" placeholder="Pilihan B">
                                    <div class="input-group-text">
                                        <input type="radio" name="jawaban_benar" value="B">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-2">
                                <div class="input-group">
                                    <span class="input-group-text">C</span>
                                    <input type="text" name="pilihan[C]" class="form-control" placeholder="Pilihan C">
                                    <div class="input-group-text">
                                        <input type="radio" name="jawaban_benar" value="C">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-2">
                                <div class="input-group">
                                    <span class="input-group-text">D</span>
                                    <input type="text" name="pilihan[D]" class="form-control" placeholder="Pilihan D">
                                    <div class="input-group-text">
                                        <input type="radio" name="jawaban_benar" value="D">
                                    </div>
                                </div>
                            </div>
                            <small class="text-muted">Pilih jawaban yang benar dengan radio button</small>
                        </div>
                        <div class="mb-3 mt-3">
                            <label class="form-label">Poin</label>
                            <input type="number" name="poin" class="form-control" value="10" min="1">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" onclick="addQuizQuestion()">Tambah Soal</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Question Modal -->
    <div class="modal fade" id="importQuestionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import dari Bank Soal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="questionBankList">
                        <p class="text-center"><span class="spinner-border spinner-border-sm"></span> Loading...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" onclick="importQuestions()">Import Terpilih</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    const kuisId = <?php echo $kuis['id']; ?>;
    const aktivitasId = <?php echo $activity_id; ?>;

    let keywords = [];

    document.getElementById('questionType').addEventListener('change', function() {
        const isMultipleChoice = this.value === 'multiple_choice';
        const isEssay = this.value === 'essay';
        document.getElementById('multipleChoiceOptions').style.display = isMultipleChoice ? 'block' : 'none';
        document.getElementById('essayOptions').style.display = isEssay ? 'block' : 'none';
    });

    // Handle keywords input
    document.getElementById('keywordInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const keyword = this.value.trim();
            if (keyword && !keywords.includes(keyword)) {
                keywords.push(keyword);
                updateKeywordsList();
                this.value = '';
            }
        }
    });

    function updateKeywordsList() {
        const container = document.getElementById('keywordsList');
        container.innerHTML = '';
        keywords.forEach((kw, idx) => {
            const badge = document.createElement('span');
            badge.className = 'badge bg-primary';
            badge.innerHTML = `${kw} <i class="fas fa-times ms-1" style="cursor: pointer;" onclick="removeKeyword(${idx})"></i>`;
            container.appendChild(badge);
        });
        document.getElementById('keywordsHidden').value = JSON.stringify(keywords);
    }

    function removeKeyword(index) {
        keywords.splice(index, 1);
        updateKeywordsList();
    }

    async function saveQuizSettings() {
        const form = document.getElementById('quizSettingsForm');
        const formData = new FormData(form);
        formData.append('action', 'update_settings');
        formData.append('aktivitas_id', aktivitasId);
        formData.append('show_correct_answers', document.getElementById('showAnswers').checked);

        try {
            const response = await fetch('/api/quiz_manage.php', { method: 'POST', body: formData });
            const data = await response.json();
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        } catch (e) {
            alert('Error: ' + e.message);
        }
    }

    async function addQuizQuestion() {
        const form = document.getElementById('addQuestionForm');
        const formData = new FormData(form);
        formData.append('action', 'add_question');
        formData.append('aktivitas_id', aktivitasId);

        try {
            const response = await fetch('/api/quiz_manage.php', { method: 'POST', body: formData });
            const data = await response.json();
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        } catch (e) {
            alert('Error: ' + e.message);
        }
    }

    async function deleteQuizQuestion(questionId) {
        if (!confirm('Hapus soal ini?')) return;

        const formData = new FormData();
        formData.append('action', 'delete_question');
        formData.append('aktivitas_id', aktivitasId);
        formData.append('question_id', questionId);

        try {
            const response = await fetch('/api/quiz_manage.php', { method: 'POST', body: formData });
            const data = await response.json();
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        } catch (e) {
            alert('Error: ' + e.message);
        }
    }

    document.getElementById('importQuestionModal').addEventListener('show.bs.modal', async function() {
        const container = document.getElementById('questionBankList');
        try {
            const response = await fetch('/api/quiz_manage.php?action=get_question_bank&aktivitas_id=' + aktivitasId);
            const data = await response.json();
            if (data.success && data.questions.length > 0) {
                let html = '<div class="list-group">';
                data.questions.forEach(q => {
                    html += `<label class="list-group-item d-flex align-items-center">
                        <input type="checkbox" class="form-check-input me-3" name="import_q" value="${q.id}">
                        <div>
                            <span class="badge bg-secondary me-2">${q.difficulty_level || 'medium'}</span>
                            ${q.question_text.substring(0, 100)}...
                        </div>
                    </label>`;
                });
                html += '</div>';
                container.innerHTML = html;
            } else {
                container.innerHTML = '<p class="text-muted text-center">Tidak ada soal di bank soal. <a href="/modules/shared/question_bank.php">Tambah soal</a></p>';
            }
        } catch (e) {
            container.innerHTML = '<p class="text-danger">Error loading questions</p>';
        }
    });

    async function importQuestions() {
        const checkboxes = document.querySelectorAll('input[name="import_q"]:checked');
        const ids = Array.from(checkboxes).map(cb => cb.value);

        if (ids.length === 0) {
            alert('Pilih minimal satu soal');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'import_from_bank');
        formData.append('aktivitas_id', aktivitasId);
        formData.append('kuis_id', kuisId);
        ids.forEach(id => formData.append('question_ids[]', id));

        try {
            const response = await fetch('/api/quiz_manage.php', { method: 'POST', body: formData });
            const data = await response.json();
            if (data.success) {
                alert(`${data.imported} soal berhasil diimport`);
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        } catch (e) {
            alert('Error: ' + e.message);
        }
    }
    </script>

    <?php else: ?>
    <!-- Student View - Take Quiz -->
    <div class="card">
        <div class="card-header" style="background-color: #007E6E; color: white;">
            <h5 class="mb-0"><i class="fas fa-question-circle"></i> Quiz</h5>
        </div>
        <div class="card-body">
            <?php if (empty($quiz_questions)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Quiz ini belum memiliki soal.
            </div>
            <?php else: ?>

            <!-- Previous Attempts -->
            <?php if (!empty($student_attempts)): ?>
            <div class="mb-4">
                <h6><i class="fas fa-history"></i> Riwayat Percobaan</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Percobaan</th>
                                <th>Skor</th>
                                <th>Benar</th>
                                <th>Waktu</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($student_attempts as $att):
                                $percentage = $max_score > 0 ? round(($att['total_score'] / $max_score) * 100, 1) : 0;
                                $passed = $percentage >= $kuis['passing_score'];
                            ?>
                            <tr>
                                <td>#<?php echo $att['attempt']; ?></td>
                                <td><?php echo $att['total_score']; ?>/<?php echo $max_score; ?> (<?php echo $percentage; ?>%)</td>
                                <td><?php echo $att['correct_count']; ?>/<?php echo count($quiz_questions); ?></td>
                                <td><?php echo date('d M Y H:i', strtotime($att['submitted_at'])); ?></td>
                                <td>
                                    <span class="badge <?php echo $passed ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $passed ? 'Lulus' : 'Tidak Lulus'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($can_attempt): ?>
            <!-- Quiz Timer Display - Hidden by default, shown when quiz starts -->
            <div id="quizTimerContainer" class="alert alert-info sticky-top" style="position: sticky; top: 0; z-index: 1000; display: none;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-stopwatch me-2"></i>
                        <strong>Sisa Waktu:</strong>
                        <span id="quizTimer" class="fs-5 fw-bold ms-2">--:--</span>
                    </div>
                    <div>
                        <span class="badge bg-secondary" id="answeredCount">0/<?php echo count($quiz_questions); ?> terjawab</span>
                    </div>
                </div>
                <div class="progress mt-2" style="height: 5px;">
                    <div id="timerProgress" class="progress-bar bg-success" role="progressbar" style="width: 100%"></div>
                </div>
            </div>

            <!-- Start Quiz Button and Information -->
            <div id="quizIntro" class="text-center py-4">
                <!-- Quiz Information Card -->
                <div class="card border-info mb-4">
                    <div class="card-header" style="background-color: #007E6E; color: white;">
                        <h5 class="mb-0"><i class="fas fa-info-circle"></i> Informasi Kuis</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-start">
                            <div class="col-md-4 mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-clock fa-2x text-primary me-3"></i>
                                    <div>
                                        <small class="text-muted">Durasi</small>
                                        <h5 class="mb-0"><?php echo $kuis['durasi']; ?> menit</h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-trophy fa-2x text-success me-3"></i>
                                    <div>
                                        <small class="text-muted">Passing Score</small>
                                        <h5 class="mb-0"><?php echo $kuis['passing_score']; ?>%</h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-redo fa-2x text-warning me-3"></i>
                                    <div>
                                        <small class="text-muted">Percobaan</small>
                                        <h5 class="mb-0"><?php echo count($student_attempts); ?>/<?php echo $kuis['max_attempts'] ?: '∞'; ?></h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row text-start mt-2">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-list-ol fa-2x text-info me-3"></i>
                                    <div>
                                        <small class="text-muted">Jumlah Soal</small>
                                        <h5 class="mb-0"><?php echo count($quiz_questions); ?> soal</h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-star fa-2x text-danger me-3"></i>
                                    <div>
                                        <small class="text-muted">Total Poin</small>
                                        <h5 class="mb-0"><?php echo $max_score; ?> poin</h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Warning and Start Button -->
                <div class="card border-warning">
                    <div class="card-body">
                        <h5><i class="fas fa-exclamation-triangle text-warning"></i> Perhatian!</h5>
                        <p class="mb-3">
                            • Setelah memulai quiz, timer akan berjalan dan tidak dapat dihentikan<br>
                            • Quiz akan otomatis ter-submit ketika waktu habis<br>
                            • Pastikan koneksi internet Anda stabil<br>
                            • Jawaban tidak dapat diubah setelah submit
                        </p>
                        <button type="button" id="startQuizBtn" class="btn btn-lg btn-success" onclick="startQuiz()">
                            <i class="fas fa-play-circle"></i> Mulai Quiz
                        </button>
                    </div>
                </div>
            </div>

            <form id="quizForm" style="display: none;">
                <input type="hidden" name="kuis_id" value="<?php echo $kuis['id']; ?>">

                <?php foreach ($quiz_questions as $idx => $q):
                    $pilihan = json_decode($q['pilihan_json'], true) ?: [];
                    $lang = get_language();
                    $soal_text = $lang === 'id' ? $q['soal_id'] : ($q['soal_en'] ?: $q['soal_id']);
                ?>
                <div class="card mb-3">
                    <div class="card-header">
                        <strong>Soal <?php echo $idx + 1; ?></strong>
                        <span class="badge bg-info float-end"><?php echo $q['poin']; ?> poin</span>
                    </div>
                    <div class="card-body">
                        <p><?php echo htmlspecialchars($soal_text); ?></p>

                        <?php if ($q['tipe'] === 'multiple_choice' && !empty($pilihan)): ?>
                        <div class="list-group">
                            <?php foreach ($pilihan as $key => $val): ?>
                            <label class="list-group-item">
                                <input type="radio" name="answers[<?php echo $q['id']; ?>]" value="<?php echo $key; ?>" class="form-check-input me-2" required>
                                <strong><?php echo $key; ?>.</strong> <?php echo htmlspecialchars($val); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <textarea name="answers[<?php echo $q['id']; ?>]" class="form-control" rows="4" placeholder="Tulis jawaban Anda..."></textarea>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <button type="submit" class="btn btn-primary" style="background-color: #007E6E; border-color: #007E6E;">
                    <i class="fas fa-paper-plane"></i> Submit Quiz
                </button>
            </form>

            <script>
            const quizDuration = <?php echo $kuis['durasi']; ?> * 60; // in seconds
            const kuisIdForTimer = <?php echo $kuis['id']; ?>;
            const totalQuestions = <?php echo count($quiz_questions); ?>;
            let timerInterval = null;
            let remainingSeconds = quizDuration;
            let isSubmitting = false;

            // Check if quiz was already started (resume timer)
            function checkExistingQuiz() {
                const savedData = localStorage.getItem(`quiz_${kuisIdForTimer}_start`);
                if (savedData) {
                    const startTime = parseInt(savedData);
                    const elapsed = Math.floor((Date.now() - startTime) / 1000);
                    remainingSeconds = quizDuration - elapsed;

                    if (remainingSeconds > 0) {
                        // Resume the quiz
                        document.getElementById('quizIntro').style.display = 'none';
                        document.getElementById('quizForm').style.display = 'block';
                        document.getElementById('quizTimerContainer').style.display = 'block';
                        startTimer();
                    } else {
                        // Time already expired, clear and allow fresh start
                        localStorage.removeItem(`quiz_${kuisIdForTimer}_start`);
                    }
                }
            }

            // Start quiz function
            function startQuiz() {
                // Save start time
                localStorage.setItem(`quiz_${kuisIdForTimer}_start`, Date.now().toString());

                // Show quiz form and timer
                document.getElementById('quizIntro').style.display = 'none';
                document.getElementById('quizForm').style.display = 'block';
                document.getElementById('quizTimerContainer').style.display = 'block';

                // Start timer
                remainingSeconds = quizDuration;
                startTimer();
            }

            // Timer function
            function startTimer() {
                updateTimerDisplay();

                timerInterval = setInterval(() => {
                    remainingSeconds--;
                    updateTimerDisplay();

                    if (remainingSeconds <= 0) {
                        clearInterval(timerInterval);
                        autoSubmitQuiz();
                    }
                }, 1000);
            }

            // Update timer display
            function updateTimerDisplay() {
                const minutes = Math.floor(remainingSeconds / 60);
                const seconds = remainingSeconds % 60;
                const timerEl = document.getElementById('quizTimer');
                const progressEl = document.getElementById('timerProgress');
                const containerEl = document.getElementById('quizTimerContainer');

                timerEl.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;

                // Update progress bar
                const percentage = (remainingSeconds / quizDuration) * 100;
                progressEl.style.width = percentage + '%';

                // Change color based on remaining time
                if (remainingSeconds <= 60) {
                    // Last minute - red and pulsing
                    containerEl.className = 'alert alert-danger sticky-top';
                    progressEl.className = 'progress-bar bg-danger';
                    timerEl.classList.add('text-danger');
                    if (remainingSeconds <= 30) {
                        timerEl.style.animation = 'pulse 0.5s infinite';
                    }
                } else if (remainingSeconds <= 300) {
                    // Last 5 minutes - warning
                    containerEl.className = 'alert alert-warning sticky-top';
                    progressEl.className = 'progress-bar bg-warning';
                } else {
                    containerEl.className = 'alert alert-info sticky-top';
                    progressEl.className = 'progress-bar bg-success';
                }

                // Update answered count
                updateAnsweredCount();
            }

            // Count answered questions
            function updateAnsweredCount() {
                const form = document.getElementById('quizForm');
                const answered = new Set();

                form.querySelectorAll('input[type="radio"]:checked').forEach(input => {
                    const name = input.name;
                    if (name.startsWith('answers[')) {
                        answered.add(name);
                    }
                });

                form.querySelectorAll('textarea').forEach(textarea => {
                    if (textarea.value.trim() !== '') {
                        answered.add(textarea.name);
                    }
                });

                const countEl = document.getElementById('answeredCount');
                countEl.textContent = `${answered.size}/${totalQuestions} terjawab`;
                countEl.className = answered.size === totalQuestions ? 'badge bg-success' : 'badge bg-secondary';
            }

            // Auto submit when time is up
            async function autoSubmitQuiz() {
                if (isSubmitting) return;
                isSubmitting = true;

                // Clear saved start time
                localStorage.removeItem(`quiz_${kuisIdForTimer}_start`);

                alert('Waktu habis! Quiz akan di-submit secara otomatis.');
                await submitQuiz(true);
            }

            // Submit quiz function
            async function submitQuiz(isAutoSubmit = false) {
                const form = document.getElementById('quizForm');
                const formData = new FormData(form);
                const btn = form.querySelector('button[type="submit"]');

                // Ensure CSRF token is included
                if (!formData.has('csrf_token')) {
                    const csrfToken = getCsrfToken();
                    if (csrfToken) {
                        formData.append('csrf_token', csrfToken);
                    }
                }

                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
                }

                try {
                    const response = await fetch('/api/quiz_submit.php', { method: 'POST', body: formData });
                    const data = await response.json();

                    // Clear timer and saved data
                    if (timerInterval) clearInterval(timerInterval);
                    localStorage.removeItem(`quiz_${kuisIdForTimer}_start`);

                    if (data.success) {
                        let message = isAutoSubmit ? 'Waktu habis!\n\n' : 'Quiz berhasil disubmit!\n\n';
                        message += `Skor: ${data.score}/${data.max_score} (${data.percentage}%)\nBenar: ${data.correct_count}/${data.total_questions}\n\nStatus: ${data.passed ? 'LULUS' : 'TIDAK LULUS'}`;
                        alert(message);
                        location.reload();
                    } else {
                        alert('Error: ' + data.error);
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Quiz';
                        }
                        isSubmitting = false;
                    }
                } catch (err) {
                    alert('Error: ' + err.message);
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Quiz';
                    }
                    isSubmitting = false;
                }
            }

            // Form submit handler
            document.getElementById('quizForm').addEventListener('submit', async function(e) {
                e.preventDefault();

                if (isSubmitting) return;

                if (!confirm('Yakin ingin submit quiz? Jawaban tidak dapat diubah setelah submit.')) return;

                isSubmitting = true;
                localStorage.removeItem(`quiz_${kuisIdForTimer}_start`);
                if (timerInterval) clearInterval(timerInterval);

                await submitQuiz(false);
            });

            // Track answer changes for count update
            document.getElementById('quizForm').addEventListener('change', updateAnsweredCount);
            document.getElementById('quizForm').addEventListener('input', updateAnsweredCount);

            // Warn before leaving page
            window.addEventListener('beforeunload', function(e) {
                if (document.getElementById('quizForm').style.display !== 'none' && !isSubmitting) {
                    e.preventDefault();
                    e.returnValue = 'Quiz sedang berlangsung. Yakin ingin meninggalkan halaman?';
                    return e.returnValue;
                }
            });

            // Check for existing quiz on page load
            checkExistingQuiz();
            </script>

            <style>
            @keyframes pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.5; }
            }
            #quizTimerContainer {
                background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
                border: none;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            #quizTimerContainer.alert-warning {
                background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
            }
            #quizTimerContainer.alert-danger {
                background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            }
            </style>

            <?php else: ?>
            <div class="alert alert-danger">
                <i class="fas fa-ban"></i> Anda sudah mencapai batas maksimal percobaan (<?php echo $kuis['max_attempts']; ?> kali).
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($activity['tipe'] === 'tugas'): ?>
    <?php
    // Get assignment data - create if not exists
    $stmt = $pdo->prepare("SELECT * FROM tugas WHERE aktivitas_id = ?");
    $stmt->execute([$activity_id]);
    $tugas = $stmt->fetch();

    if (!$tugas) {
        // Create default tugas record if not exists
        $stmt = $pdo->prepare("INSERT INTO tugas (aktivitas_id, instruksi_id, instruksi_en, max_score, allow_late_submission) VALUES (?, ?, ?, 100, true)");
        $stmt->execute([$activity_id, $deskripsi, $deskripsi]);
        $tugas = ['id' => $pdo->lastInsertId(), 'max_score' => 100, 'deadline' => null, 'allow_late_submission' => true];
    }

    $is_teacher = in_array($user['role'], ['admin', 'pengajar']);

    if ($tugas):
        if ($is_teacher):
            // Teacher view - show submissions
            $stmt = $pdo->prepare("
                SELECT ts.id, ts.mahasiswa_id, ts.file_path, ts.jawaban_text,
                       ts.submitted_at, ts.score, ts.feedback, ts.graded_at,
                       u.full_name, u.email
                FROM tugas_submission ts
                JOIN users u ON ts.mahasiswa_id = u.id
                WHERE ts.tugas_id = ?
                ORDER BY ts.submitted_at DESC
            ");
            $stmt->execute([$tugas['id']]);
            $submissions = $stmt->fetchAll();
    ?>
    <div class="card">
        <div class="card-header" style="background-color: #007E6E; color: white;">
            <h5 class="mb-0"><i class="fas fa-tasks"></i> Daftar Submission Tugas</h5>
        </div>
        <div class="card-body">
            <?php if (empty($submissions)): ?>
            <p class="text-muted">Belum ada submission dari mahasiswa</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead style="background-color: #f8f9fa;">
                        <tr>
                            <th>Mahasiswa</th>
                            <th>Waktu Submission</th>
                            <th>Nilai</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $sub): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($sub['full_name']); ?></strong><br><small class="text-muted"><?php echo $sub['email']; ?></small></td>
                            <td><?php echo $sub['submitted_at'] ? date('d M Y H:i', strtotime($sub['submitted_at'])) : '-'; ?></td>
                            <td>
                                <strong><?php echo $sub['score'] !== null ? (int)$sub['score'] : '-'; ?></strong>/<?php echo $tugas['max_score']; ?>
                            </td>
                            <td>
                                <?php if ($sub['score'] !== null): ?>
                                    <span class="badge bg-success">Dinilai</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Menunggu</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary grade-btn"
                                    data-submission="<?php echo $sub['id']; ?>"
                                    data-student="<?php echo $sub['mahasiswa_id']; ?>"
                                    data-name="<?php echo htmlspecialchars($sub['full_name']); ?>"
                                    data-file="<?php echo htmlspecialchars($sub['file_path'] ?? ''); ?>">
                                    <i class="fas fa-check-circle"></i> Grade
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Grading Modal -->
    <div class="modal fade" id="gradingModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Penilaian Tugas - <span id="gradingStudentName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="submissionId">
                    <input type="hidden" id="studentId">
                    <input type="hidden" id="courseId" value="<?php echo $activity['kursus_id']; ?>">

                    <div class="mb-3" id="fileSection" style="display: block !important; visibility: visible !important;">
                        <label class="form-label"><i class="fas fa-file"></i> File Submission</label>
                        <div id="submissionFile" style="min-height: 50px; padding: 15px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; display: block !important; visibility: visible !important;"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nilai <span style="color: red;">*</span></label>
                        <div class="input-group">
                            <input type="number" id="scoreInput" class="form-control" min="0" max="<?php echo $tugas['max_score']; ?>" required>
                            <span class="input-group-text">/ <?php echo $tugas['max_score']; ?></span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Komentar/Feedback</label>
                        <textarea id="feedbackInput" class="form-control" rows="4" placeholder="Berikan feedback kepada mahasiswa..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" onclick="submitGrade(this)">
                        <i class="fas fa-save"></i> Kirim Nilai
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php else: // Student view - show submission form
        $stmt = $pdo->prepare("SELECT * FROM tugas_submission WHERE tugas_id = ? AND mahasiswa_id = ?");
        $stmt->execute([$tugas['id'], $user['id']]);
        $my_submission = $stmt->fetch();
    ?>
    <div class="card">
        <div class="card-header" style="background-color: #007E6E; color: white;">
            <h5 class="mb-0"><i class="fas fa-upload"></i> Submission Tugas</h5>
        </div>
        <div class="card-body">
            <?php
            $is_past_deadline = $tugas['deadline'] && strtotime($tugas['deadline']) < time();
            // Allow submit if: no submission yet, OR submission exists but not graded yet
            $is_graded = $my_submission && $my_submission['score'] !== null;
            $can_submit = !$my_submission || (!$is_graded && (!$is_past_deadline || $tugas['allow_late_submission']));
            ?>

            <?php if ($my_submission): ?>
            <div class="mb-3" style="background-color: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 0.75rem 1.25rem; border-radius: 0.25rem; display: block !important; visibility: visible !important; opacity: 1 !important;">
                <i class="fas fa-info-circle"></i> <strong>Status Submission:</strong>
                <div style="margin-top: 0.5rem; display: block !important; visibility: visible !important;">
                    Waktu Upload: <strong><?php echo date('d M Y H:i', strtotime($my_submission['submitted_at'])); ?></strong>
                    <?php if ($is_past_deadline): ?>
                    <span class="badge bg-warning">Terlambat</span>
                    <?php endif; ?>
                </div>
                <?php if ($my_submission['score'] !== null): ?>
                <div style="margin-top: 0.5rem; display: block !important; visibility: visible !important;">
                    Nilai: <strong style="color: #007E6E; font-size: 1.2rem;"><?php echo (int)$my_submission['score']; ?>/<?php echo $tugas['max_score']; ?></strong>
                </div>
                <?php if ($my_submission['feedback']): ?>
                <div style="margin-top: 0.5rem; display: block !important; visibility: visible !important;">
                    Feedback:<br>
                    <div style="background: #f8f9fa; padding: 0.75rem; border-left: 4px solid #007E6E; margin-top: 0.5rem; border-radius: 4px; display: block !important; visibility: visible !important;">
                        <?php echo nl2br(htmlspecialchars($my_submission['feedback'])); ?>
                    </div>
                </div>
                <?php else: ?>
                <div style="margin-top: 0.5rem; display: block !important; visibility: visible !important;">
                    Status: <span class="badge bg-warning">Menunggu Penilaian</span>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($can_submit): ?>
            <div class="mb-3">
                <?php if ($my_submission && !$is_graded): ?>
                <div style="background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 0.75rem 1.25rem; border-radius: 0.25rem; display: block !important; visibility: visible !important; opacity: 1 !important;">
                    <i class="fas fa-info-circle"></i> <strong>Pengiriman Ulang:</strong> Anda dapat mengirim ulang tugas untuk perbaikan. Pengiriman ulang akan mereset nilai dan feedback sebelumnya.
                </div>
                <?php endif; ?>
            </div>
            <form id="submissionForm" enctype="multipart/form-data">
                <input type="hidden" name="tugas_id" value="<?php echo $tugas['id']; ?>">

                <div class="mb-3">
                    <label class="form-label"><i class="fas fa-file-upload"></i> Upload File Tugas (PDF, DOC, DOCX, ZIP)</label>
                    <input type="file" name="submission_file" class="form-control" id="submissionFile" accept=".pdf,.doc,.docx,.zip,.jpg,.jpeg,.png,.xls,.xlsx,.ppt,.pptx,.txt">
                    <small class="text-muted d-block mt-2">Format: PDF, DOC, DOCX, ZIP, JPG, PNG, XLS, XLSX, PPT, PPTX, TXT (Max 50MB)</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Catatan/Keterangan (Opsional)</label>
                    <textarea name="submission_text" class="form-control" rows="3" placeholder="Tuliskan catatan atau penjelasan tentang tugas Anda..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="background-color: #007E6E; border-color: #007E6E;">
                    <i class="fas fa-check"></i> <?php echo $my_submission ? 'Kirim Ulang Tugas' : 'Kirim Tugas'; ?>
                </button>
            </form>
            <?php elseif ($is_graded): ?>
            <div style="background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 0.75rem 1.25rem; border-radius: 0.25rem; display: block !important; visibility: visible !important; opacity: 1 !important;">
                <i class="fas fa-lock"></i> Tugas sudah dinilai dan tidak dapat diubah lagi. Silakan hubungi pengajar jika ingin melakukan perbaikan.
            </div>
            <?php else: ?>
            <div style="background-color: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 0.75rem 1.25rem; border-radius: 0.25rem; display: block !important; visibility: visible !important; opacity: 1 !important;">
                <i class="fas fa-exclamation-triangle"></i> Deadline telah berlalu dan pengumpulan terlambat tidak diizinkan.
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?> <!-- closes if ($is_teacher) -->
    <?php endif; ?> <!-- closes if ($tugas) -->
    <?php endif; ?> <!-- closes if ($activity['tipe'] === 'tugas') from line 422 -->

    <?php if ($activity['tipe'] === 'forum'): ?>
    <div class="forum-discussion-container mt-4">
        <!-- Forum Topic + Reply Form Combined -->
        <div style="background: white; border: 2px solid #E7DEAF; border-radius: 8px; padding: 25px; margin-bottom: 30px;">
            <!-- Topic Section -->
            <div style="display: flex; align-items: flex-start; gap: 15px; margin-bottom: 30px; padding-bottom: 30px; border-bottom: 1px solid #E7DEAF;">
                <div style="flex-shrink: 0;">
                    <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #007E6E, #73AF6F); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px;">
                        <i class="fas fa-comments"></i>
                    </div>
                </div>
                <div style="flex: 1;">
                    <h5 style="color: #007E6E; font-weight: 600; margin-bottom: 10px;">
                        <i class="fas fa-lightbulb"></i> Topik Diskusi
                    </h5>
                    <p style="font-size: 16px; line-height: 1.6; color: #333; margin: 0;">
                        <?php echo nl2br(htmlspecialchars($deskripsi)); ?>
                    </p>
                </div>
            </div>

            <!-- Reply Form Section -->
            <form id="forumReplyForm" method="POST" action="/api/post_forum.php">
                <input type="hidden" name="activity_id" value="<?php echo $activity_id; ?>">
                <?php echo csrf_field_html(); ?>

                <h6 style="color: #007E6E; font-weight: 600; margin-bottom: 15px; margin-top: 10px;">
                    <i class="fas fa-pen-fancy"></i> Tulis Balasan
                </h6>

                <div id="forumFeedback" style="display: none; padding: 12px; border-radius: 4px; margin-bottom: 15px;"></div>

                <textarea
                    id="forumKonten"
                    name="konten"
                    placeholder="Tuliskan tanggapan, pertanyaan, atau komentar Anda di sini..."
                    style="display: block; width: 100%; border: 1px solid #D7C097; border-radius: 4px; font-family: Arial, sans-serif; margin-bottom: 10px; resize: vertical; min-height: 150px; box-sizing: border-box;"
                    required></textarea>

                <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                    <small style="color: #999; display: flex; align-items: center; gap: 5px;">
                        <i class="fas fa-info-circle"></i> Tulis dengan jelas dan sopan
                    </small>
                    <button id="forumSubmitBtn" type="submit" style="background-color: #007E6E; color: white; border: none; padding: 12px 25px; border-radius: 4px; font-weight: 600; cursor: pointer; font-size: 14px; white-space: nowrap;">
                        <i class="fas fa-paper-plane"></i> Kirim Balasan
                    </button>
                </div>
            </form>
        </div>

        <!-- Discussion Thread -->
        <div>
            <h6 style="color: #007E6E; font-weight: 600; margin-bottom: 20px;">
                <i class="fas fa-comments"></i> Balasan
            </h6>
            <div id="forumPostsContainer">
                <?php
                $stmt = $pdo->prepare("
                    SELECT fd.*, u.full_name, u.email
                    FROM forum_diskusi fd
                    JOIN users u ON fd.user_id = u.id
                    WHERE fd.aktivitas_id = ? AND fd.parent_id IS NULL
                    ORDER BY fd.created_at DESC
                ");
                $stmt->execute([$activity_id]);
                $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($posts)):
                ?>
                <div style="text-align: center; color: #999; padding: 40px 20px;">
                    <i class="fas fa-inbox" style="font-size: 48px; opacity: 0.3; display: block; margin-bottom: 15px;"></i>
                    <p>Belum ada balasan. Jadilah yang pertama!</p>
                </div>
                <?php else: ?>
                <?php foreach ($posts as $post):
                    $date = date('d M Y H:i', strtotime($post['created_at']));
                    $isOwner = $post['user_id'] == $user['id'];
                    $nameLetter = strtoupper(substr($post['full_name'], 0, 1));

                    // Determine if current user can delete this post
                    $canDelete = false;
                    if ($isOwner) {
                        $canDelete = true;
                    } elseif ($user['role'] === 'admin') {
                        $canDelete = true;
                    } elseif ($user['role'] === 'pengajar') {
                        // Pengajar can delete student posts
                        $canDelete = true;
                    }
                ?>
                <div style="background: white; border-left: 4px solid #73AF6F; border-radius: 4px; padding: 15px; margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                        <div style="display: flex; gap: 12px; flex: 1;">
                            <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #007E6E, #73AF6F); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; flex-shrink: 0;">
                                <?php echo $nameLetter; ?>
                            </div>
                            <div style="flex: 1;">
                                <div style="color: #212529; font-weight: 600; margin-bottom: 3px;"><?php echo htmlspecialchars($post['full_name']); ?></div>
                                <div style="color: #999; font-size: 12px;"><i class="fas fa-clock"></i> <?php echo $date; ?></div>
                            </div>
                        </div>
                        <?php if ($canDelete): ?>
                        <button type="button" onclick="deleteForumPost(<?php echo $post['id']; ?>)" style="background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; font-size: 12px;"><i class="fas fa-trash"></i></button>
                        <?php endif; ?>
                    </div>
                    <div style="line-height: 1.6; color: #333; word-break: break-word;">
                        <?php echo nl2br(htmlspecialchars($post['konten'])); ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card mt-3">
        <div class="card-body">
            <a href="/course_view.php?id=<?php echo $activity['kursus_id']; ?>" class="btn" style="background-color: #007E6E; color: white; border-color: #007E6E;">
                <i class="fas fa-arrow-left"></i> <?php echo t('back'); ?>
            </a>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
    let currentPdfDoc = null;
    let currentPage = 1;
    let totalPages = 0;

    function viewFile(index, filePath, fileName, ext) {
        const modal = document.getElementById('fileViewerModal');
        const content = document.getElementById('fileViewerContent');
        const fileNameEl = document.getElementById('viewerFileName');
        const downloadBtn = document.getElementById('downloadFileBtn');

        fileNameEl.textContent = fileName;
        downloadBtn.href = filePath;
        downloadBtn.download = fileName;

        document.getElementById('pageInfo').style.display = 'none';
        document.getElementById('prevPageBtn').style.display = 'none';
        document.getElementById('nextPageBtn').style.display = 'none';

        document.documentElement.style.overflow = 'hidden';
        document.body.style.overflow = 'hidden';
        modal.classList.add('active');

        if (ext.toLowerCase() === 'pdf') {
            content.innerHTML = '<canvas id="pdf-canvas" style="max-width: 100%; max-height: 100%; object-fit: contain;"></canvas>';
            loadPDF(filePath);
        } else if (['jpg', 'jpeg', 'png'].includes(ext.toLowerCase())) {
            content.innerHTML = '<div class="image-viewer"><img src="' + filePath + '" alt="' + fileName + '"></div>';
        } else if (ext.toLowerCase() === 'txt') {
            content.innerHTML = '<div class="text-viewer" id="textContent">Loading...</div>';
            fetch(filePath)
                .then(r => r.text())
                .then(text => {
                    document.getElementById('textContent').textContent = text;
                });
        } else if (['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'].includes(ext.toLowerCase())) {
            const onlyOfficeUrl = 'https://view.officeapps.live.com/op/embed.aspx?src=' + encodeURIComponent(window.location.origin + filePath);
            content.innerHTML = '<iframe class="office-viewer" src="' + onlyOfficeUrl + '" frameborder="0"></iframe>';
        }
    }

    function closeFileViewer() {
        const modal = document.getElementById('fileViewerModal');
        modal.classList.remove('active');
        document.documentElement.style.overflow = '';
        document.body.style.overflow = '';
        currentPdfDoc = null;
    }

    function toggleFullscreen() {
        const elem = document.getElementById('fileViewerModal');
        if (document.fullscreenElement) {
            // Exit fullscreen
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            }
        } else {
            // Enter fullscreen
            if (elem.requestFullscreen) {
                elem.requestFullscreen();
            } else if (elem.webkitRequestFullscreen) {
                elem.webkitRequestFullscreen();
            }
        }
    }

    // Handle fullscreen changes
    document.addEventListener('fullscreenchange', () => {
        // Modal stays active, browser handles fullscreen state
    });

    document.addEventListener('webkitfullscreenchange', () => {
        // Webkit fallback
    });

    function loadPDF(filePath) {
        pdfjsLib.getDocument(filePath).promise.then(pdf => {
            currentPdfDoc = pdf;
            totalPages = pdf.numPages;
            renderPDFPage(1);
            document.getElementById('pageInfo').style.display = 'inline';
            document.getElementById('prevPageBtn').style.display = 'inline-block';
            document.getElementById('nextPageBtn').style.display = 'inline-block';
        });
    }

    function renderPDFPage(pageNum) {
        if (!currentPdfDoc) return;

        currentPage = Math.max(1, Math.min(pageNum, totalPages));
        document.getElementById('pageInfo').textContent = 'Halaman ' + currentPage + ' dari ' + totalPages;

        currentPdfDoc.getPage(currentPage).then(page => {
            const canvas = document.getElementById('pdf-canvas');
            const ctx = canvas.getContext('2d');
            const viewport = page.getViewport({scale: 1.5});
            canvas.width = viewport.width;
            canvas.height = viewport.height;

            const renderContext = {
                canvasContext: ctx,
                viewport: viewport
            };
            page.render(renderContext);
        });
    }

    function previousPage() {
        renderPDFPage(currentPage - 1);
    }

    function nextPage() {
        renderPDFPage(currentPage + 1);
    }

    function deleteFile(fileId, fileName) {
        const modal = document.getElementById('confirmationModal');
        const title = modal.querySelector('.modal-title');
        const body = modal.querySelector('.modal-body');
        const deleteBtn = modal.querySelector('.delete-btn');

        title.innerHTML = '<i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i> Hapus File';
        body.innerHTML = `
            <div style="border-left: 4px solid #dc3545; padding-left: 1rem; margin-bottom: 1rem;">
                <p><strong>Anda yakin ingin menghapus file ini?</strong></p>
                <p style="margin: 0.5rem 0;"><strong>File:</strong> ${fileName}</p>
            </div>
            <p style="color: #666; margin-bottom: 0;">Tindakan ini tidak dapat dibatalkan. File akan dihapus permanen dari sistem.</p>
        `;

        deleteBtn.onclick = () => {
            deleteBtn.disabled = true;
            deleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menghapus...';

            fetch('/api/delete_file.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'file_id=' + fileId
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    closeConfirmation();
                    setTimeout(() => {
                        location.reload();
                    }, 500);
                } else {
                    alert('Error: ' + (data.error || 'Gagal menghapus file'));
                    deleteBtn.disabled = false;
                    deleteBtn.innerHTML = '<i class="fas fa-trash"></i> Hapus';
                }
            })
            .catch(err => {
                alert('Error: ' + err.message);
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = '<i class="fas fa-trash"></i> Hapus';
            });
        };

        modal.classList.add('active');
    }

    function editFile(fileId, fileName) {
        alert('Edit file feature will be available soon. Current file: ' + fileName);
    }

    document.addEventListener('keydown', function(e) {
        const fileModal = document.getElementById('fileViewerModal');
        if (fileModal && fileModal.classList.contains('active')) {
            if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') previousPage();
            if (e.key === 'ArrowDown' || e.key === 'ArrowRight') nextPage();
            if (e.key === 'Escape') {
                // If in fullscreen, exit fullscreen first
                if (document.fullscreenElement) {
                    if (document.exitFullscreen) {
                        document.exitFullscreen();
                    }
                } else {
                    closeFileViewer();
                }
            }
        }
    });

    // Assignment submission handling
    <?php if ($activity['tipe'] === 'tugas' && $user['role'] === 'mahasiswa'): ?>

    document.getElementById('submissionForm').addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(e.target);

        // Add CSRF token
        const csrfToken = getCsrfToken();
        if (csrfToken) {
            formData.append('csrf_token', csrfToken);
        }

        const btn = e.target.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Mengunggah...';

        try {
            const response = await fetch('/api/submit_assignment.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                const text = await response.text();
                let errorMsg = 'Gagal upload tugas';
                try {
                    const data = JSON.parse(text);
                    errorMsg = data.error || errorMsg;
                } catch (e) {
                    // Response bukan JSON, kemungkinan HTML error page
                    if (text.includes('403')) {
                        errorMsg = 'Session expired. Silakan refresh halaman dan coba lagi.';
                    } else {
                        errorMsg = 'Server error: ' + response.status;
                    }
                }
                alert('Error: ' + errorMsg);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check"></i> Kirim Tugas';
                return;
            }

            const data = await response.json();
            if (data.success) {
                alert('Tugas berhasil diupload!');
                setTimeout(() => location.reload(), 1000);
            } else {
                alert('Error: ' + (data.error || 'Gagal upload'));
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check"></i> Kirim Tugas';
            }
        } catch (err) {
            alert('Error: ' + err.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> Kirim Tugas';
        }
    });
    <?php endif; ?>

    // Grading functions
    <?php if ($activity['tipe'] === 'tugas' && in_array($user['role'], ['admin', 'pengajar'])): ?>
    let gradingModalInstance = null;

    document.addEventListener('DOMContentLoaded', function() {
        const gradeButtons = document.querySelectorAll('.grade-btn');
        gradeButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const submissionId = this.getAttribute('data-submission');
                const studentId = this.getAttribute('data-student');
                const studentName = this.getAttribute('data-name');
                const filePath = this.getAttribute('data-file');

                document.getElementById('submissionId').value = submissionId;
                document.getElementById('studentId').value = studentId;
                document.getElementById('gradingStudentName').textContent = studentName;
                document.getElementById('scoreInput').value = '';
                document.getElementById('feedbackInput').value = '';

                const fileSection = document.getElementById('fileSection');
                const submissionFile = document.getElementById('submissionFile');

                // Ensure section is visible
                fileSection.style.display = 'block';
                fileSection.style.visibility = 'visible';
                fileSection.style.opacity = '1';

                if (filePath && filePath.trim()) {
                    const fileName = filePath.split('/').pop();
                    const ext = fileName.split('.').pop().toLowerCase();
                    const previewUrl = `/api/preview_file.php?file=${encodeURIComponent(filePath)}`;
                    const htmlContent = `<div style="display: flex; justify-content: space-between; align-items: center; gap: 10px;"><div style="display: flex; align-items: center; gap: 5px;"><i class="fas fa-file"></i> <strong>${fileName}</strong></div><div style="display: flex; gap: 5px; flex-shrink: 0;"><a href="${previewUrl}" target="_blank" class="btn btn-sm btn-outline-info"><i class="fas fa-eye"></i> Preview</a><a href="${filePath}" target="_blank" download class="btn btn-sm btn-outline-primary"><i class="fas fa-download"></i> Download</a></div></div>`;
                    submissionFile.innerHTML = htmlContent;
                } else {
                    submissionFile.innerHTML = '<p class="text-muted" style="margin: 0;">Tidak ada file</p>';
                }

                submissionFile.style.display = 'block';
                submissionFile.style.visibility = 'visible';
                submissionFile.style.opacity = '1';

                if (!gradingModalInstance) {
                    gradingModalInstance = new bootstrap.Modal(document.getElementById('gradingModal'));
                }
                gradingModalInstance.show();
            });
        });
    });

    async function submitGrade(btn) {
        const submissionId = document.getElementById('submissionId').value;
        const studentId = document.getElementById('studentId').value;
        const courseId = document.getElementById('courseId').value;
        const score = document.getElementById('scoreInput').value;
        const feedback = document.getElementById('feedbackInput').value;

        if (!score) {
            alert('Nilai harus diisi!');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';

        // Get CSRF token
        const csrfToken = getCsrfToken();

        try {
            const response = await fetch('/api/grade_assignment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    submission_id: submissionId,
                    student_id: studentId,
                    course_id: courseId,
                    score: score,
                    feedback: feedback
                })
            });

            const data = await response.json();
            if (data.success) {
                alert('Nilai berhasil disimpan!');
                if (gradingModalInstance) {
                    gradingModalInstance.hide();
                }
                setTimeout(() => location.reload(), 1000);
            } else {
                alert('Error: ' + (data.error || 'Gagal menyimpan'));
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> Kirim Nilai';
            }
        } catch (err) {
            alert('Error: ' + err.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Kirim Nilai';
        }
    }
    <?php endif; ?>
</script>

<!-- Forum Modal -->
<?php if ($activity['tipe'] === 'forum'): ?>
<div class="modal fade" id="forumModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #007E6E; color: white;">
                <h5 class="modal-title"><i class="fas fa-comment"></i> Tambah Balasan Forum</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="forumParentId">

                <div class="mb-3">
                    <label class="form-label">Judul (Opsional)</label>
                    <input type="text" id="forumSubject" class="form-control" placeholder="Masukkan judul balasan...">
                </div>

                <div class="mb-3">
                    <label class="form-label">Pesan <span style="color: red;">*</span></label>
                    <div class="toolbar mb-2" style="background: #f8f9fa; padding: 0.5rem; border-radius: 4px; border: 1px solid #dee2e6;">
                        <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="insertFormat('bold')">
                            <i class="fas fa-bold"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="insertFormat('italic')">
                            <i class="fas fa-italic"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="insertFormat('underline')">
                            <i class="fas fa-underline"></i>
                        </button>
                        <div class="btn-group me-1" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertFormat('insertUnorderedList')">
                                <i class="fas fa-list-ul"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertFormat('insertOrderedList')">
                                <i class="fas fa-list-ol"></i>
                            </button>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="insertLink()">
                            <i class="fas fa-link"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="insertImage()">
                            <i class="fas fa-image"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertFormat('removeFormat')">
                            <i class="fas fa-eraser"></i>
                        </button>
                    </div>

                    <div id="forumEditor" contenteditable="true" style="min-height: 250px; padding: 10px; border: 1px solid #dee2e6; border-radius: 4px; background: white; font-size: 14px;"></div>
                    <small class="text-muted d-block mt-2">Gunakan toolbar di atas untuk memformat teks. Anda dapat menambahkan link, gambar, dan daftar.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">BATAL</button>
                <button type="button" class="btn btn-primary" onclick="submitForumPost(this)">
                    <i class="fas fa-paper-plane"></i> POST KE FORUM
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Forum Discussion Functions - Global Scope -->
<script>
    let forumModalInstance = null;
    // activityId and currentUserId are set globally above

    function loadReplies(postId) {
        if (!activityId) return;
        const repliesDiv = document.getElementById(`replies-${postId}`);
        if (!repliesDiv) return;
        if (repliesDiv.style.display === 'none') {
            fetch(`/api/get_forum_posts.php?activity_id=${activityId}&parent_id=${postId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.posts.length > 0) {
                        let html = '<div class="forum-replies" style="border-left: 3px solid #007E6E; padding-left: 1rem;">';
                        data.posts.forEach(reply => {
                            const date = new Date(reply.created_at).toLocaleString('id-ID');
                            const isOwner = reply.user_id === currentUserId;
                            html += `<div class="forum-reply card mb-2"><div class="card-body p-2"><div class="d-flex justify-content-between"><div><small><strong>${escapeHtml(reply.full_name)}</strong></small><br><small class="text-muted">${date}</small></div>${isOwner ? `<button class="btn btn-sm btn-outline-danger" onclick="deleteForumPost(${reply.id})"><i class="fas fa-trash"></i></button>` : ''}</div><div class="post-content mt-2">${reply.konten}</div></div></div>`;
                        });
                        html += '</div>';
                        repliesDiv.innerHTML = html;
                        repliesDiv.style.display = 'block';
                    }
                })
                .catch(err => console.error('Error loading replies:', err));
        } else {
            repliesDiv.style.display = 'none';
        }
    }

    function openForumModal(parentId = null) {
        const modalEl = document.getElementById('forumModal');
        const parentInput = document.getElementById('forumParentId');
        const editor = document.getElementById('forumEditor');

        if (!modalEl || !parentInput || !editor) {
            alert('Error: Forum modal not properly loaded');
            return;
        }

        parentInput.value = parentId || '';
        editor.innerHTML = '';

        // Use Bootstrap 5 modal
        if (typeof bootstrap !== 'undefined') {
            if (!forumModalInstance) {
                forumModalInstance = new bootstrap.Modal(modalEl, { backdrop: true });
            }
            forumModalInstance.show();
        } else {
            // Fallback: show with CSS
            modalEl.style.display = 'block';
            modalEl.style.position = 'fixed';
            modalEl.style.zIndex = '9999';
            modalEl.style.top = '0';
            modalEl.style.left = '0';
            modalEl.style.width = '100%';
            modalEl.style.height = '100%';
            modalEl.style.backgroundColor = 'rgba(0,0,0,0.5)';
            modalEl.classList.add('show');
        }
    }

    async function submitForumPost(btn) {
        const subject = document.getElementById('forumSubject').value.trim();
        const content = document.getElementById('forumEditor').innerHTML.trim();
        const parentId = document.getElementById('forumParentId').value;

        if (!content) {
            alert('Konten tidak boleh kosong!');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Mengirim...';

        try {
            const formData = new FormData();
            formData.append('activity_id', activityId);
            formData.append('konten', (subject ? `<strong>${escapeHtml(subject)}</strong><br>` : '') + content);
            if (parentId) formData.append('parent_id', parentId);

            // Add CSRF token
            const csrfToken = getCsrfToken();
            if (csrfToken) {
                formData.append('csrf_token', csrfToken);
            }

            const response = await fetch('/api/post_forum.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.success) {
                forumModalInstance.hide();
                loadForumPosts();
                if (typeof markActivityCompleted !== 'undefined') markActivityCompleted();
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> POST KE FORUM';
            } else {
                alert('Error: ' + (data.error || 'Gagal mengirim'));
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> POST KE FORUM';
            }
        } catch (err) {
            console.error(err);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> POST KE FORUM';
        }
    }

    async function deleteForumPost(postId) {
        if (!confirm('Yakin ingin menghapus balasan ini?')) return;

        try {
            const csrfToken = getCsrfToken();
            const formData = new FormData();
            formData.append('post_id', postId);
            formData.append('activity_id', activityId);
            formData.append('csrf_token', csrfToken);

            const response = await fetch('/api/delete_forum_post.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });
            
            if (!response.ok) {
                const text = await response.text();
                throw new Error('Server error: ' + response.status);
            }
            
            const data = await response.json();
            if (data.success) {
                // Reload the page to show updated forum posts
                window.location.reload();
            } else {
                alert('Error: ' + (data.error || 'Gagal menghapus'));
            }
        } catch (err) {
            console.error(err);
            alert('Error: ' + err.message);
        }
    }

    function insertFormat(command, value = null) {
        const editor = document.getElementById('forumEditor');
        if (editor) {
            document.execCommand(command, false, value);
            editor.focus();
        }
    }

    function insertLink() {
        const url = prompt('Masukkan URL:');
        if (url) insertFormat('createLink', url);
    }

    function insertImage() {
        const url = prompt('Masukkan URL gambar:');
        if (url) insertFormat('insertImage', url);
    }

<script>
function loadForumPosts() {
    const container = document.getElementById('forumPostsContainer');
    if (!container) return;

    fetch(`/api/get_forum_posts.php?activity_id=${activityId}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                container.innerHTML = '<p>Gagal memuat diskusi.</p>';
                return;
            }

            container.innerHTML = '';
            data.posts.forEach(post => {
                container.innerHTML += `
                    <div class="forum-post">
                        <strong>${post.user_name}</strong>
                        <p>${post.konten}</p>
                        <small>${post.created_at}</small>
                    </div>
                `;
            });
        })
        .catch(err => {
            console.error(err);
            container.innerHTML = '<p>Error memuat forum.</p>';
        });
}
</script>

    document.addEventListener('DOMContentLoaded', function() {
    if (typeof activityId !== 'undefined' &&
        activityId &&
        document.getElementById('forumPostsContainer')) {
        loadForumPosts();
    }
});
</script>

<?php require __DIR__ . '/components/footer.php'; ?>