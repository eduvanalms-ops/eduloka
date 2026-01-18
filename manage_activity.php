<?php
require_once __DIR__ . '/config/config.php';
require_login();

$user = get_logged_user();
$kursus_id = (int)($_GET['kursus_id'] ?? 0);
$edit_id = (int)($_GET['edit'] ?? 0);
$activity = null;
$activity_types = [];

if (!$kursus_id) {
    header('Location: /index.php');
    exit;
}

// Check permission
$is_allowed = false;
if ($user['role'] === 'admin') {
    $is_allowed = true;
} elseif ($user['role'] === 'pengajar') {
    $stmt = $pdo->prepare("SELECT 1 FROM kursus WHERE id = ? AND pengajar_id = ?");
    $stmt->execute([$kursus_id, $user['id']]);
    $is_allowed = $stmt->fetchColumn() > 0;
}

if (!$is_allowed) {
    set_flash('error', 'Akses ditolak');
    header('Location: /index.php');
    exit;
}

// Load activity if editing
if ($edit_id) {
    $stmt = $pdo->prepare("SELECT * FROM aktivitas WHERE id = ? AND kursus_id = ?");
    $stmt->execute([$edit_id, $kursus_id]);
    $activity = $stmt->fetch();
    
    if (!$activity) {
        set_flash('error', 'Aktivitas tidak ditemukan');
        header('Location: /course_view.php?id=' . $kursus_id);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT tipe FROM aktivitas_tipe WHERE aktivitas_id = ?");
    $stmt->execute([$edit_id]);
    $activity_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Handle CREATE/UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $judul_id = $_POST['judul_id'] ?? '';
    $judul_en = $_POST['judul_en'] ?? '';
    $deskripsi_id = $_POST['deskripsi_id'] ?? '';
    $deskripsi_en = $_POST['deskripsi_en'] ?? '';
    $tipe_array = $_POST['tipe'] ?? [];
    
    if (empty($tipe_array)) {
        set_flash('error', 'Pilih minimal satu tipe aktivitas');
        header('Location: /manage_activity.php?kursus_id=' . $kursus_id);
        exit;
    }
    
    try {
        $primary_type = $tipe_array[0] ?? 'materi';
        
        if ($edit_id) {
            // Update existing activity
            $stmt = $pdo->prepare("UPDATE aktivitas SET judul_id = ?, judul_en = ?, deskripsi_id = ?, deskripsi_en = ?, tipe = ? WHERE id = ?");
            $stmt->execute([$judul_id, $judul_en, $deskripsi_id, $deskripsi_en, $primary_type, $edit_id]);
            $aktivitas_id = $edit_id;
            
            // Delete and re-insert types
            $stmt = $pdo->prepare("DELETE FROM aktivitas_tipe WHERE aktivitas_id = ?");
            $stmt->execute([$aktivitas_id]);
        } else {
            // Insert new activity
            $stmt = $pdo->prepare("INSERT INTO aktivitas (kursus_id, judul_id, judul_en, deskripsi_id, deskripsi_en, tipe) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$kursus_id, $judul_id, $judul_en, $deskripsi_id, $deskripsi_en, $primary_type]);
            $aktivitas_id = $pdo->lastInsertId();
        }
        
        // Insert all selected types into aktivitas_tipe
        $stmt = $pdo->prepare("INSERT INTO aktivitas_tipe (aktivitas_id, tipe) VALUES (?, ?)");
        foreach ($tipe_array as $type) {
            $stmt->execute([$aktivitas_id, $type]);
        }
        
        // Handle file uploads
        if (!empty($_FILES['files']['name'][0])) {
            $upload_dir = __DIR__ . '/uploads/files/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            
            foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
                if (!empty($tmp_name) && is_uploaded_file($tmp_name)) {
                    $filename = time() . '_' . basename($_FILES['files']['name'][$key]);
                    $filepath = $upload_dir . $filename;
                    
                    if (move_uploaded_file($tmp_name, $filepath)) {
                        $file_size = filesize($filepath);
                        $file_type = pathinfo($filename, PATHINFO_EXTENSION);
                        $stmt = $pdo->prepare("INSERT INTO files (aktivitas_id, file_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$aktivitas_id, $_FILES['files']['name'][$key], '/uploads/files/' . $filename, $file_type, $file_size]);
                    }
                }
            }
        }
        
        set_flash('success', $edit_id ? 'Aktivitas berhasil diperbarui' : 'Aktivitas berhasil ditambahkan');
    } catch (PDOException $e) {
        set_flash('error', 'Error: ' . $e->getMessage());
    }
    
    header('Location: /course_view.php?id=' . $kursus_id);
    exit;
}

$page_title = $edit_id ? 'Edit Aktivitas' : t('add_activity');
require __DIR__ . '/components/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1>
                <i class="fas <?php echo $edit_id ? 'fa-edit' : 'fa-plus'; ?>"></i> 
                <?php echo $edit_id ? 'Edit Aktivitas' : t('add_activity'); ?>
            </h1>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <div class="mb-3">
                    <label class="form-label"><?php echo t('activity_type'); ?> * (Pilih minimal satu)</label>
                    <div class="border p-3 rounded" style="background-color: var(--bg-secondary);">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="tipe[]" value="materi" id="tipe_materi" <?php echo (!$edit_id || in_array('materi', $activity_types)) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="tipe_materi">
                                <i class="fas fa-file-alt"></i> <?php echo t('material'); ?>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="tipe[]" value="video" id="tipe_video" <?php echo ($edit_id && in_array('video', $activity_types)) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="tipe_video">
                                <i class="fas fa-video"></i> <?php echo t('video'); ?>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="tipe[]" value="quiz" id="tipe_quiz" <?php echo ($edit_id && in_array('quiz', $activity_types)) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="tipe_quiz">
                                <i class="fas fa-question-circle"></i> <?php echo t('quiz'); ?>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="tipe[]" value="tugas" id="tipe_tugas" <?php echo ($edit_id && in_array('tugas', $activity_types)) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="tipe_tugas">
                                <i class="fas fa-tasks"></i> <?php echo t('assignment'); ?>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="tipe[]" value="forum" id="tipe_forum" <?php echo ($edit_id && in_array('forum', $activity_types)) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="tipe_forum">
                                <i class="fas fa-comments"></i> <?php echo t('forum'); ?>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label"><i class="fas fa-paperclip"></i> Lampirkan Dokumen (Opsional)</label>
                    <input type="file" name="files[]" class="form-control" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.png,.txt">
                    <small class="text-muted">Format yang didukung: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG, PNG, TXT (Max 10MB per file)</small>
                </div>
                
                <?php if (get_language() === 'id'): ?>
                <div class="mb-3">
                    <label class="form-label">Judul *</label>
                    <input type="text" name="judul_id" class="form-control" value="<?php echo $activity ? htmlspecialchars($activity['judul_id']) : ''; ?>" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="deskripsi_id" class="form-control" rows="4"><?php echo $activity ? htmlspecialchars($activity['deskripsi_id']) : ''; ?></textarea>
                </div>
                <input type="hidden" name="judul_en" value="<?php echo $activity ? htmlspecialchars($activity['judul_en']) : ''; ?>">
                <input type="hidden" name="deskripsi_en" value="<?php echo $activity ? htmlspecialchars($activity['deskripsi_en']) : ''; ?>">
                <?php else: ?>
                <div class="mb-3">
                    <label class="form-label">Title *</label>
                    <input type="text" name="judul_en" class="form-control" value="<?php echo $activity ? htmlspecialchars($activity['judul_en']) : ''; ?>" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="deskripsi_en" class="form-control" rows="4"><?php echo $activity ? htmlspecialchars($activity['deskripsi_en']) : ''; ?></textarea>
                </div>
                <input type="hidden" name="judul_id" value="<?php echo $activity ? htmlspecialchars($activity['judul_id']) : ''; ?>">
                <input type="hidden" name="deskripsi_id" value="<?php echo $activity ? htmlspecialchars($activity['deskripsi_id']) : ''; ?>">
                <?php endif; ?>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo t('save'); ?>
                    </button>
                    <a href="/course_view.php?id=<?php echo $kursus_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> <?php echo t('cancel'); ?>
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/components/footer.php'; ?>
