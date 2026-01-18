<?php
require_once __DIR__ . '/config/config.php';
require_role(['admin', 'pengajar']);

$user = get_logged_user();
$kursus_id = (int)($_GET['kursus_id'] ?? 0);
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

// Get course info
$stmt = $pdo->prepare("SELECT * FROM kursus WHERE id = ?");
$stmt->execute([$kursus_id]);
$course = $stmt->fetch();

// Handle create attendance session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_attendance'])) {
    require_csrf();
    $pertemuan = (int)$_POST['pertemuan'];
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $qr_code = 'ATT-' . uniqid();
    $qr_expired_at = date('Y-m-d H:i:s', strtotime('+2 hours'));
    
    try {
        $stmt = $pdo->prepare("INSERT INTO presensi (kursus_id, pertemuan, tanggal, qr_code, qr_expired_at, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$kursus_id, $pertemuan, $tanggal, $qr_code, $qr_expired_at, $user['id']]);
        set_flash('success', 'Sesi presensi berhasil dibuat');
    } catch (PDOException $e) {
        set_flash('error', 'Error: ' . $e->getMessage());
    }
    
    header('Location: /manage_attendance.php?kursus_id=' . $kursus_id);
    exit;
}

// Handle update attendance session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_attendance'])) {
    require_csrf();
    $presensi_id = (int)$_POST['presensi_id'];
    $pertemuan = (int)$_POST['pertemuan'];
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $qr_code = 'ATT-' . uniqid();
    $qr_expired_at = date('Y-m-d H:i:s', strtotime('+2 hours'));
    
    try {
        $stmt = $pdo->prepare("UPDATE presensi SET pertemuan = ?, tanggal = ?, qr_code = ?, qr_expired_at = ? WHERE id = ? AND kursus_id = ?");
        $stmt->execute([$pertemuan, $tanggal, $qr_code, $qr_expired_at, $presensi_id, $kursus_id]);
        set_flash('success', 'Sesi presensi berhasil diperbarui');
    } catch (PDOException $e) {
        set_flash('error', 'Error: ' . $e->getMessage());
    }
    
    header('Location: /manage_attendance.php?kursus_id=' . $kursus_id);
    exit;
}

// Handle delete attendance session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_attendance'])) {
    require_csrf();
    $presensi_id = (int)$_POST['delete_attendance'];
    
    try {
        // Delete attendance records first
        $stmt = $pdo->prepare("DELETE FROM presensi_records WHERE presensi_id = ?");
        $stmt->execute([$presensi_id]);
        
        // Delete attendance session
        $stmt = $pdo->prepare("DELETE FROM presensi WHERE id = ? AND kursus_id = ?");
        $stmt->execute([$presensi_id, $kursus_id]);
        set_flash('success', 'Sesi presensi berhasil dihapus');
    } catch (PDOException $e) {
        set_flash('error', 'Error: ' . $e->getMessage());
    }
    
    header('Location: /manage_attendance.php?kursus_id=' . $kursus_id);
    exit;
}

// Handle manual attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_attendance'])) {
    require_csrf();
    $presensi_id = (int)$_POST['presensi_id'];
    $mahasiswa_id = (int)$_POST['mahasiswa_id'];
    $status = $_POST['status'] ?? 'hadir';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO presensi_records (presensi_id, mahasiswa_id, status) VALUES (?, ?, ?) ON CONFLICT (presensi_id, mahasiswa_id) DO UPDATE SET status = ?");
        $stmt->execute([$presensi_id, $mahasiswa_id, $status, $status]);
        set_flash('success', t('attendance_recorded'));
    } catch (PDOException $e) {
        set_flash('error', 'Error: ' . $e->getMessage());
    }
    
    header('Location: /manage_attendance.php?kursus_id=' . $kursus_id);
    exit;
}

// Get attendance sessions
$stmt = $pdo->prepare("SELECT * FROM presensi WHERE kursus_id = ? ORDER BY tanggal DESC");
$stmt->execute([$kursus_id]);
$attendance_sessions = $stmt->fetchAll();

// Get enrolled students
$stmt = $pdo->prepare("SELECT u.id, u.username, u.full_name FROM kursus_enrollments ke JOIN users u ON ke.mahasiswa_id = u.id WHERE ke.kursus_id = ? ORDER BY u.full_name");
$stmt->execute([$kursus_id]);
$students = $stmt->fetchAll();

// Get all attendance records for this course
$stmt = $pdo->prepare("
    SELECT pr.presensi_id, pr.mahasiswa_id, pr.status 
    FROM presensi_records pr 
    JOIN presensi p ON pr.presensi_id = p.id 
    WHERE p.kursus_id = ?
");
$stmt->execute([$kursus_id]);
$attendance_records = [];
foreach ($stmt->fetchAll() as $record) {
    $attendance_records[$record['presensi_id']][$record['mahasiswa_id']] = $record['status'];
}

$page_title = 'Presensi';
require __DIR__ . '/components/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-calendar-check"></i> <?php echo t('attendance'); ?></h1>
            <p class="text-muted"><?php echo htmlspecialchars($course['nama_id']); ?></p>
        </div>
        <div class="col-auto d-flex gap-2 align-items-start">
            <a href="/course_view.php?id=<?php echo $kursus_id; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali ke Mata Kuliah
            </a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAttendanceModal">
                <i class="fas fa-plus"></i> <?php echo t('create_attendance_session'); ?>
            </button>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Pencatatan Presensi</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($attendance_sessions)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> <?php echo t('no_attendance_sessions'); ?>
                    </div>
                    <?php elseif (empty($students)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i> Belum ada mahasiswa yang terdaftar di kursus ini
                    </div>
                    <?php else: ?>
                    <!-- Session Selector Tabs -->
                    <ul class="nav nav-tabs mb-4" id="sessionTabs" role="tablist">
                        <?php foreach ($attendance_sessions as $idx => $session): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $idx === 0 ? 'active' : ''; ?>" 
                                    id="session-<?php echo $session['id']; ?>-tab" 
                                    data-bs-toggle="tab" 
                                    data-bs-target="#session-<?php echo $session['id']; ?>" 
                                    type="button" role="tab">
                                <strong><?php echo t('meeting'); ?> <?php echo $session['pertemuan']; ?></strong><br>
                                <small><?php echo date('d M Y', strtotime($session['tanggal'])); ?></small>
                            </button>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <!-- Session Content -->
                    <div class="tab-content" id="sessionTabContent">
                        <?php foreach ($attendance_sessions as $idx => $session): ?>
                        <div class="tab-pane fade <?php echo $idx === 0 ? 'show active' : ''; ?>" 
                             id="session-<?php echo $session['id']; ?>" role="tabpanel">
                            
                            <!-- Session Header -->
                            <div class="alert alert-light mb-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo t('meeting'); ?> <?php echo $session['pertemuan']; ?></strong> - <?php echo date('d M Y', strtotime($session['tanggal'])); ?><br>
                                    <small class="text-muted">QR: <?php echo substr($session['qr_code'], 0, 15); ?>...</small>
                                    <?php if (strtotime($session['qr_expired_at']) > time()): ?>
                                        <span class="badge bg-success ms-2"><?php echo t('active'); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary ms-2"><?php echo t('expired'); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="showQRCode(<?php echo $session['id']; ?>)">
                                        <i class="fas fa-qrcode"></i> Show QR
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="markAllPresent(<?php echo $session['id']; ?>)">
                                        <i class="fas fa-check"></i> Semua Hadir
                                    </button>
                                </div>
                            </div>

                            <!-- Students Attendance List -->
                            <div class="table-responsive">
                                <table class="table table-hover" style="font-size: 0.95rem;">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 200px;">Mahasiswa</th>
                                            <th style="width: 200px;">Status Kehadiran</th>
                                            <th style="width: 150px; text-align: center;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student): 
                                            $current_status = $attendance_records[$session['id']][$student['id']] ?? null;
                                        ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($student['full_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($student['username']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <select class="form-select attendance-status" 
                                                        data-presensi-id="<?php echo $session['id']; ?>"
                                                        data-mahasiswa-id="<?php echo $student['id']; ?>"
                                                        onchange="updateAttendanceStatus(this)">
                                                    <option value="">-- Pilih Status --</option>
                                                    <option value="hadir" <?php echo $current_status === 'hadir' ? 'selected' : ''; ?>>
                                                        ✓ Hadir
                                                    </option>
                                                    <option value="izin" <?php echo $current_status === 'izin' ? 'selected' : ''; ?>>
                                                        — Izin
                                                    </option>
                                                    <option value="sakit" <?php echo $current_status === 'sakit' ? 'selected' : ''; ?>>
                                                        ⊡ Sakit
                                                    </option>
                                                    <option value="alpha" <?php echo $current_status === 'alpha' ? 'selected' : ''; ?>>
                                                        ✗ Alpha
                                                    </option>
                                                </select>
                                            </td>
                                            <td style="text-align: center;">
                                                <?php if ($current_status): ?>
                                                <span class="badge" style="background-color: <?php 
                                                    echo $current_status === 'hadir' ? '#73AF6F' : 
                                                         ($current_status === 'izin' ? '#007E6E' : 
                                                          ($current_status === 'sakit' ? '#ff9800' : '#d32f2f'));
                                                ?>">
                                                    <?php echo ucfirst($current_status); ?>
                                                </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Session Management Card -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-cog"></i> Kelola Sesi Presensi</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($attendance_sessions)): ?>
                    <p class="text-muted">Tidak ada sesi presensi</p>
                    <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($attendance_sessions as $session): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?php echo t('meeting'); ?> <?php echo $session['pertemuan']; ?></strong> - <?php echo date('d M Y', strtotime($session['tanggal'])); ?><br>
                                <small class="text-muted">QR: <?php echo substr($session['qr_code'], 0, 15); ?>...</small>
                                <?php if (strtotime($session['qr_expired_at']) > time()): ?>
                                    <span class="badge bg-success ms-2"><?php echo t('active'); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary ms-2"><?php echo t('expired'); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-primary" onclick="showQRCode(<?php echo $session['id']; ?>)" title="Show QR Code">
                                    <i class="fas fa-qrcode"></i>
                                </button>
                                <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#editAttendanceModal" onclick="editAttendance(<?php echo $session['id']; ?>, <?php echo $session['pertemuan']; ?>, '<?php echo $session['tanggal']; ?>')" title="Edit Sesi">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger" onclick="deleteAttendance(<?php echo $session['id']; ?>)" title="Hapus Sesi">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Create Attendance Session -->
<div class="modal fade" id="createAttendanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php echo csrf_field(); ?>
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo t('create_new_attendance_session'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('meeting_number'); ?> *</label>
                        <input type="number" name="pertemuan" class="form-control" min="1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('date'); ?> *</label>
                        <input type="date" name="tanggal" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="alert alert-info mb-0">
                        <strong><?php echo t('info'); ?>:</strong> QR code akan aktif selama 2 jam untuk mahasiswa melakukan presensi
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo t('cancel'); ?></button>
                    <button type="submit" name="create_attendance" class="btn btn-primary">
                        <i class="fas fa-plus"></i> <?php echo t('create'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Attendance Session -->
<div class="modal fade" id="editAttendanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php echo csrf_field(); ?>
                <div class="modal-header">
                    <h5 class="modal-title">Edit Sesi Presensi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="presensi_id" id="editPresensiId">
                    
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('meeting_number'); ?> *</label>
                        <input type="number" name="pertemuan" id="editPertemuan" class="form-control" min="1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('date'); ?> *</label>
                        <input type="date" name="tanggal" id="editTanggal" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo t('cancel'); ?></button>
                    <button type="submit" name="update_attendance" class="btn btn-warning">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
function updateAttendanceStatus(selectElement) {
    const presensiId = selectElement.getAttribute('data-presensi-id');
    const mahasiswaId = selectElement.getAttribute('data-mahasiswa-id');
    const status = selectElement.value;
    const tr = selectElement.closest('tr');
    const badgeCell = tr.querySelector('td:last-child');
    
    if (!status) {
        // Clear badge if status is empty
        badgeCell.innerHTML = '';
        return;
    }
    
    const formData = new FormData();
    formData.append('presensi_id', presensiId);
    formData.append('user_id', mahasiswaId);
    formData.append('status', status);
    
    // Show loading state
    selectElement.disabled = true;
    selectElement.classList.add('is-loading');
    
    fetch('/api/update_attendance_status.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Update badge
            const badgeColor = status === 'hadir' ? '#73AF6F' : 
                              status === 'ijin' ? '#007E6E' : 
                              status === 'sakit' ? '#ff9800' : '#d32f2f';
            badgeCell.innerHTML = `<span class="badge" style="background-color: ${badgeColor}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
            
            selectElement.classList.remove('is-loading');
            selectElement.classList.add('is-success');
            setTimeout(() => selectElement.classList.remove('is-success'), 1000);
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
            selectElement.value = '';
        }
    })
    .catch(err => {
        console.error(err);
        alert('Terjadi kesalahan: ' + err.message);
        selectElement.value = '';
    })
    .finally(() => {
        selectElement.disabled = false;
        selectElement.classList.remove('is-loading');
    });
}

function markAllPresent(presensiId) {
    const tab = document.getElementById(`session-${presensiId}`);
    const selects = tab.querySelectorAll('.attendance-status');
    
    if (confirm(`Tandai semua ${selects.length} mahasiswa sebagai HADIR?`)) {
        let count = 0;
        selects.forEach(select => {
            select.value = 'hadir';
            updateAttendanceStatus(select);
            count++;
        });
        alert(`${count} status kehadiran telah diperbarui`);
    }
}

function editAttendance(presensiId, pertemuan, tanggal) {
    document.getElementById('editPresensiId').value = presensiId;
    document.getElementById('editPertemuan').value = pertemuan;
    document.getElementById('editTanggal').value = tanggal;
}

// Add loading state styles
document.addEventListener('DOMContentLoaded', function() {
    const style = document.createElement('style');
    style.textContent = `
        .attendance-status.is-loading {
            background-color: #e7f5ff !important;
            opacity: 0.7;
        }
        .attendance-status.is-success {
            background-color: #c3fae8 !important;
        }
    `;
    document.head.appendChild(style);
});

function deleteAttendance(presensiId) {
    if (confirm('Apakah Anda yakin ingin menghapus sesi presensi ini? Data kehadiran juga akan dihapus.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <?php echo csrf_field(); ?>
            <input type="hidden" name="delete_attendance" value="${presensiId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

async function showQRCode(presensiId) {
    const modal = document.getElementById('qrModal');
    const qrContainer = document.getElementById('qrCodeContainer');
    const qrInfo = document.getElementById('qrInfo');
    
    qrContainer.innerHTML = '<div class="text-center"><span class="spinner-border"></span><br>Loading QR Code...</div>';
    qrInfo.innerHTML = '';
    
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    
    try {
        const response = await fetch(`/api/attendance_qr.php?action=get_qr&presensi_id=${presensiId}`);
        const data = await response.json();
        
        if (data.success) {
            const qrText = data.qr_code;
            const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(qrText)}`;
            
            qrContainer.innerHTML = `
                <img src="${qrUrl}" alt="QR Code" style="max-width: 100%; border: 4px solid #007E6E; border-radius: 8px;">
                <div class="mt-3">
                    <code style="background: #f8f9fa; padding: 10px 20px; border-radius: 4px; display: inline-block; font-size: 1.1rem;">
                        ${data.qr_code}
                    </code>
                </div>
            `;
            
            const isActive = data.is_active;
            const statusBadge = isActive ? 
                '<span class="badge bg-success"><i class="fas fa-check"></i> Aktif</span>' : 
                '<span class="badge bg-danger"><i class="fas fa-times"></i> Expired</span>';
            
            qrInfo.innerHTML = `
                <table class="table table-sm mt-3">
                    <tr><td><strong>Mata Kuliah:</strong></td><td>${data.course_name}</td></tr>
                    <tr><td><strong>Pertemuan:</strong></td><td>${data.pertemuan}</td></tr>
                    <tr><td><strong>Tanggal:</strong></td><td>${data.tanggal}</td></tr>
                    <tr><td><strong>Berlaku sampai:</strong></td><td>${new Date(data.expired_at).toLocaleString('id-ID')} ${statusBadge}</td></tr>
                </table>
                ${!isActive ? '<button class="btn btn-primary btn-sm" onclick="refreshQRCode(' + presensiId + ')"><i class="fas fa-refresh"></i> Perpanjang QR (2 jam)</button>' : ''}
            `;
            
            document.getElementById('currentPresensiId').value = presensiId;
        } else {
            qrContainer.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
        }
    } catch (err) {
        qrContainer.innerHTML = `<div class="alert alert-danger">Error: ${err.message}</div>`;
    }
}

async function refreshQRCode(presensiId) {
    if (!confirm('Perpanjang QR code untuk 2 jam ke depan?')) return;
    
    const formData = new FormData();
    formData.append('action', 'refresh');
    formData.append('presensi_id', presensiId);
    formData.append('duration', 120);
    
    try {
        const response = await fetch('/api/attendance_qr.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            alert('QR code berhasil diperpanjang!');
            showQRCode(presensiId);
        } else {
            alert('Error: ' + data.error);
        }
    } catch (err) {
        alert('Error: ' + err.message);
    }
}
</script>

<!-- QR Code Modal -->
<div class="modal fade" id="qrModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #007E6E; color: white;">
                <h5 class="modal-title"><i class="fas fa-qrcode"></i> QR Code Presensi</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <input type="hidden" id="currentPresensiId">
                <div id="qrCodeContainer"></div>
                <div id="qrInfo"></div>
                <div class="mt-3 text-muted">
                    <small><i class="fas fa-info-circle"></i> Mahasiswa dapat scan QR code ini atau memasukkan kode secara manual di halaman presensi</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- QR Code Modal -->
<div class="modal fade" id="qrModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #007E6E; color: white;">
                <h5 class="modal-title"><i class="fas fa-qrcode"></i> QR Code Presensi</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <input type="hidden" id="currentPresensiId">
                <div id="qrCodeContainer"></div>
                <div id="qrInfo"></div>
                <div class="mt-3 text-muted">
                    <small><i class="fas fa-info-circle"></i> Mahasiswa dapat scan QR code ini atau memasukkan kode secara manual di halaman presensi</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/components/footer.php'; ?>
