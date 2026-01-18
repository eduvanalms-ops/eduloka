<?php
require_once __DIR__ . '/config/config.php';
require_role(['mahasiswa']);

$user = get_logged_user();
$page_title = t('scan_attendance');

// Ensure CSRF token is generated
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$stmt = $pdo->prepare("
    SELECT k.id, k.nama_id, k.nama_en, p.id as presensi_id, p.pertemuan, p.tanggal, p.qr_code, p.qr_expired_at,
           pr.status as my_status, pr.waktu_presensi
    FROM kursus_enrollments ke
    JOIN kursus k ON ke.kursus_id = k.id
    LEFT JOIN presensi p ON k.id = p.kursus_id AND p.qr_expired_at > NOW()
    LEFT JOIN presensi_records pr ON p.id = pr.presensi_id AND pr.mahasiswa_id = ?
    WHERE ke.mahasiswa_id = ?
    ORDER BY k.nama_id, p.tanggal DESC
");
$stmt->execute([$user['id'], $user['id']]);
$active_sessions = $stmt->fetchAll();

require __DIR__ . '/components/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-qrcode"></i> <?php echo t('scan_attendance'); ?></h1>
            <p class="text-muted">Scan QR code atau masukkan kode presensi untuk mencatat kehadiran Anda</p>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header" style="background-color: #007E6E; color: white;">
                    <h5 class="mb-0"><i class="fas fa-camera"></i> Scan QR Code</h5>
                </div>
                <div class="card-body">
                    <div id="qr-reader" style="width: 100%; min-height: 300px; background: #f5f5f5; border-radius: 8px;"></div>
                    <div id="scan-result" class="mt-3" style="display: none;"></div>
                    
                    <div class="mt-3">
                        <button class="btn btn-primary" id="startScanBtn" onclick="startScanning()">
                            <i class="fas fa-play"></i> Mulai Scan
                        </button>
                        <button class="btn btn-secondary" id="stopScanBtn" onclick="stopScanning()" style="display: none;">
                            <i class="fas fa-stop"></i> Stop Scan
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header" style="background-color: #007E6E; color: white;">
                    <h5 class="mb-0"><i class="fas fa-keyboard"></i> Masukkan Kode Manual</h5>
                </div>
                <div class="card-body">
                    <form id="manualCodeForm">
                        <?php echo csrf_field(); ?>
                        <div class="input-group">
                            <input type="text" id="manualCode" class="form-control" placeholder="Masukkan kode presensi (contoh: ATT-xxxx)" required>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check"></i> Submit
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header" style="background-color: #007E6E; color: white;">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Sesi Presensi Aktif</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($active_sessions) || !$active_sessions[0]['presensi_id']): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Tidak ada sesi presensi aktif saat ini.
                    </div>
                    <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($active_sessions as $session): 
                            if (!$session['presensi_id']) continue;
                            $lang = get_language();
                            $course_name = $lang === 'id' ? $session['nama_id'] : ($session['nama_en'] ?: $session['nama_id']);
                        ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($course_name); ?></h6>
                                    <p class="mb-1 text-muted">
                                        Pertemuan <?php echo $session['pertemuan']; ?> - 
                                        <?php echo date('d M Y', strtotime($session['tanggal'])); ?>
                                    </p>
                                    <small class="text-muted">
                                        Kode: <code><?php echo substr($session['qr_code'], 0, 15); ?>...</code>
                                    </small>
                                </div>
                                <div>
                                    <?php if ($session['my_status']): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check"></i> <?php echo ucfirst($session['my_status']); ?>
                                    </span>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-outline-primary" onclick="submitAttendance('<?php echo $session['qr_code']; ?>')">
                                        <i class="fas fa-hand-paper"></i> Hadir
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header" style="background-color: #73AF6F; color: white;">
                    <h5 class="mb-0"><i class="fas fa-question-circle"></i> Cara Menggunakan</h5>
                </div>
                <div class="card-body">
                    <ol class="mb-0">
                        <li class="mb-2">Klik tombol <strong>"Mulai Scan"</strong> untuk mengaktifkan kamera</li>
                        <li class="mb-2">Arahkan kamera ke QR code yang ditampilkan pengajar</li>
                        <li class="mb-2">Atau masukkan kode presensi secara manual jika QR tidak bisa dipindai</li>
                        <li class="mb-2">Pastikan lokasi Anda berada di dalam kelas saat melakukan presensi</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.4/html5-qrcode.min.js"></script>
<script>
let html5QrCode = null;
let isScanning = false;

function startScanning() {
    const qrReader = document.getElementById('qr-reader');
    
    html5QrCode = new Html5Qrcode("qr-reader");
    
    html5QrCode.start(
        { facingMode: "environment" },
        {
            fps: 10,
            qrbox: { width: 250, height: 250 }
        },
        onScanSuccess,
        onScanFailure
    ).then(() => {
        isScanning = true;
        document.getElementById('startScanBtn').style.display = 'none';
        document.getElementById('stopScanBtn').style.display = 'inline-block';
    }).catch(err => {
        console.error('Error starting scanner:', err);
        alert('Gagal mengakses kamera. Pastikan Anda memberikan izin akses kamera.');
    });
}

function stopScanning() {
    if (html5QrCode && isScanning) {
        html5QrCode.stop().then(() => {
            isScanning = false;
            document.getElementById('startScanBtn').style.display = 'inline-block';
            document.getElementById('stopScanBtn').style.display = 'none';
        });
    }
}

function onScanSuccess(decodedText, decodedResult) {
    stopScanning();
    
    let qrCode = decodedText;
    
    try {
        const decoded = JSON.parse(atob(decodedText));
        if (decoded.type === 'eduloka_attendance' && decoded.code) {
            qrCode = decoded.code;
        }
    } catch (e) {
        if (decodedText.startsWith('ATT-')) {
            qrCode = decodedText;
        }
    }
    
    submitAttendance(qrCode);
}

function onScanFailure(error) {
}

const csrfToken = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';

async function submitAttendance(qrCode) {
    const resultDiv = document.getElementById('scan-result');
    resultDiv.style.display = 'block';
    resultDiv.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Memproses presensi...</div>';
    
    try {
        const formData = new FormData();
        formData.append('action', 'scan');
        formData.append('qr_code', qrCode);
        formData.append('csrf_token', csrfToken);
        
        const response = await fetch('/api/attendance_qr.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            resultDiv.innerHTML = `
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <strong>${data.message}</strong><br>
                    ${data.course_name ? `<small>Kursus: ${data.course_name} | Pertemuan: ${data.pertemuan}</small>` : ''}
                </div>
            `;
            
            setTimeout(() => location.reload(), 2000);
        } else {
            resultDiv.innerHTML = `<div class="alert alert-danger"><i class="fas fa-times-circle"></i> Error: ${data.error}</div>`;
        }
    } catch (err) {
        resultDiv.innerHTML = `<div class="alert alert-danger"><i class="fas fa-times-circle"></i> Error: ${err.message}</div>`;
    }
}

document.getElementById('manualCodeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const code = document.getElementById('manualCode').value.trim();
    if (code) {
        submitAttendance(code);
    }
});
</script>

<?php require __DIR__ . '/components/footer.php'; ?>
