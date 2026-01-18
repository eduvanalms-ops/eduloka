<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/certificate_service.php';

$certificateService = new CertificateService($pdo);
$verification_code = $_GET['code'] ?? $_POST['code'] ?? '';
$certificate = null;
$error = null;

if ($verification_code) {
    $certificate = $certificateService->verifyCertificate($verification_code);
    if (!$certificate) {
        $error = get_language() == 'id' ? 'Sertifikat tidak valid atau telah dicabut' : 'Invalid or revoked certificate';
    }
}

$page_title = t('verify_certificate') ?? 'Verify Certificate';
?>
<!DOCTYPE html>
<html lang="<?php echo get_language(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - EduLoka</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .verify-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
            overflow: hidden;
        }
        .verify-header {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .verify-body {
            padding: 30px;
        }
        .verified-badge {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 10px 30px;
            border-radius: 50px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .invalid-badge {
            display: inline-block;
            background: #dc3545;
            color: white;
            padding: 10px 30px;
            border-radius: 50px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            color: #666;
            font-weight: 500;
        }
        .info-value {
            font-weight: 600;
            color: #333;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="verify-card">
        <div class="verify-header">
            <i class="fas fa-certificate fa-4x mb-3"></i>
            <h3>EduLoka Certificate Verification</h3>
        </div>
        <div class="verify-body">
            <?php if (!$verification_code): ?>
                <form method="GET" action="">
                    <div class="mb-4">
                        <label class="form-label">Enter Verification Code</label>
                        <input type="text" name="code" class="form-control form-control-lg" 
                               placeholder="e.g., ABC123XYZ" required autofocus>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="fas fa-search"></i> Verify Certificate
                    </button>
                </form>
            <?php elseif ($certificate): ?>
                <div class="text-center">
                    <div class="verified-badge">
                        <i class="fas fa-check-circle"></i> VERIFIED
                    </div>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Recipient Name</span>
                    <span class="info-value"><?php echo htmlspecialchars($certificate['full_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Course</span>
                    <span class="info-value"><?php echo htmlspecialchars(get_language() == 'id' ? $certificate['kursus_nama_id'] : $certificate['kursus_nama_en']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Certificate Number</span>
                    <span class="info-value"><code><?php echo htmlspecialchars($certificate['certificate_number']); ?></code></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Issue Date</span>
                    <span class="info-value"><?php echo date('F d, Y', strtotime($certificate['issued_date'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Final Score</span>
                    <span class="info-value">
                        <span class="badge bg-success"><?php echo number_format($certificate['final_score'], 1); ?>%</span>
                    </span>
                </div>
                
                <div class="mt-4 text-center">
                    <a href="/certificate_view.php?verify=<?php echo urlencode($verification_code); ?>" class="btn btn-success" target="_blank">
                        <i class="fas fa-eye"></i> View Full Certificate
                    </a>
                    <a href="/verify_certificate.php" class="btn btn-outline-secondary ms-2">
                        <i class="fas fa-redo"></i> Verify Another
                    </a>
                </div>
            <?php else: ?>
                <div class="text-center">
                    <div class="invalid-badge">
                        <i class="fas fa-times-circle"></i> INVALID
                    </div>
                    <p class="text-muted"><?php echo htmlspecialchars($error); ?></p>
                    <a href="/verify_certificate.php" class="btn btn-primary mt-3">
                        <i class="fas fa-redo"></i> Try Again
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <div class="text-center p-3 bg-light">
            <small class="text-muted">
                <i class="fas fa-graduation-cap"></i> EduLoka Learning Management System
            </small>
        </div>
    </div>
</body>
</html>
