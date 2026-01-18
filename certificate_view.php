<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/certificate_service.php';

$certificate_id = $_GET['id'] ?? null;
$verification_code = $_GET['verify'] ?? null;

$certificateService = new CertificateService($pdo);

if ($verification_code) {
    $certificate = $certificateService->verifyCertificate($verification_code);
    if (!$certificate) {
        die('<div style="font-family: Arial; padding: 40px; text-align: center;">
            <h1 style="color: #dc3545;">Invalid Certificate</h1>
            <p>The verification code is invalid or the certificate has been revoked.</p>
        </div>');
    }
} elseif ($certificate_id) {
    if (!is_logged_in()) {
        header('Location: /login.php');
        exit;
    }
    $certificate = $certificateService->getCertificate($certificate_id);
    if (!$certificate) {
        die('<div style="font-family: Arial; padding: 40px; text-align: center;">
            <h1 style="color: #dc3545;">Certificate Not Found</h1>
            <p>The requested certificate does not exist.</p>
        </div>');
    }
    
    $user = get_logged_user();
    if ($certificate['user_id'] != $user['id'] && $user['role'] !== 'admin') {
        die('<div style="font-family: Arial; padding: 40px; text-align: center;">
            <h1 style="color: #dc3545;">Unauthorized</h1>
            <p>You do not have permission to view this certificate.</p>
        </div>');
    }
} else {
    die('<div style="font-family: Arial; padding: 40px; text-align: center;">
        <h1 style="color: #dc3545;">Error</h1>
        <p>Please provide a certificate ID or verification code.</p>
    </div>');
}

$lang = get_language();
$kursus_nama = $lang == 'id' ? $certificate['kursus_nama_id'] : $certificate['kursus_nama_en'];
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate - <?php echo htmlspecialchars($certificate['full_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            font-family: "Open Sans", sans-serif;
        }
        
        .toolbar {
            background: white;
            padding: 15px 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .certificate-wrapper {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 30px rgba(0,0,0,0.15);
        }
        
        .certificate {
            width: 1000px;
            height: 700px;
            background: linear-gradient(135deg, #fffef7 0%, #fefcf0 100%);
            border: 15px solid #c9a227;
            position: relative;
            padding: 40px;
        }
        
        .certificate::before {
            content: "";
            position: absolute;
            top: 10px;
            left: 10px;
            right: 10px;
            bottom: 10px;
            border: 2px solid #c9a227;
            pointer-events: none;
        }
        
        .certificate-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #1a365d;
            margin-bottom: 10px;
        }
        
        .certificate-title {
            font-family: "Playfair Display", serif;
            font-size: 48px;
            color: #c9a227;
            margin: 15px 0;
            text-transform: uppercase;
            letter-spacing: 5px;
        }
        
        .subtitle {
            font-size: 18px;
            color: #666;
            margin-bottom: 15px;
        }
        
        .content-area {
            text-align: center;
            flex-grow: 1;
        }
        
        .certify-text {
            font-size: 16px;
            color: #666;
        }
        
        .recipient-name {
            font-family: "Playfair Display", serif;
            font-size: 42px;
            color: #1a365d;
            margin: 25px 0;
            border-bottom: 2px solid #c9a227;
            display: inline-block;
            padding-bottom: 10px;
        }
        
        .course-info {
            font-size: 20px;
            color: #333;
            margin: 20px 0;
            line-height: 1.6;
        }
        
        .course-name {
            font-weight: 600;
            color: #1a365d;
        }
        
        .course-code {
            font-size: 14px;
            color: #888;
        }
        
        .score-info {
            font-size: 16px;
            color: #666;
            margin: 15px 0;
        }
        
        .certificate-footer {
            position: absolute;
            bottom: 60px;
            left: 60px;
            right: 60px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        
        .footer-item {
            text-align: center;
        }
        
        .signature-line {
            width: 200px;
            border-top: 1px solid #333;
            padding-top: 5px;
            font-size: 14px;
        }
        
        .certificate-number {
            font-size: 12px;
            color: #999;
            position: absolute;
            bottom: 25px;
            left: 50%;
            transform: translateX(-50%);
        }
        
        .verification-code {
            font-size: 12px;
            color: #999;
            position: absolute;
            bottom: 25px;
            right: 60px;
        }
        
        .date-info {
            font-size: 14px;
            color: #666;
        }
        
        .seal {
            width: 100px;
            height: 100px;
            border: 3px solid #c9a227;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #c9a227;
            text-align: center;
            font-weight: bold;
        }
        
        .verification-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        @media print {
            body { background: white; padding: 0; }
            .toolbar { display: none; }
            .certificate-wrapper { box-shadow: none; padding: 0; }
        }
        
        @media (max-width: 1100px) {
            .certificate-wrapper { transform: scale(0.9); transform-origin: top center; }
        }
        
        @media (max-width: 900px) {
            .certificate-wrapper { transform: scale(0.7); transform-origin: top center; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="javascript:history.back()" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fas fa-print"></i> Print / Save as PDF
        </button>
        <?php if ($verification_code): ?>
            <span class="badge bg-success"><i class="fas fa-check-circle"></i> Verified Certificate</span>
        <?php endif; ?>
    </div>
    
    <div class="certificate-wrapper">
        <div class="certificate">
            <?php if ($verification_code): ?>
                <div class="verification-badge">
                    <i class="fas fa-check-circle"></i> VERIFIED
                </div>
            <?php endif; ?>
            
            <div class="certificate-header">
                <div class="logo"><i class="fas fa-graduation-cap"></i> EduLoka</div>
                <div class="certificate-title">Certificate</div>
                <div class="subtitle">of Completion</div>
            </div>
            
            <div class="content-area">
                <p class="certify-text">This is to certify that</p>
                <div class="recipient-name"><?php echo htmlspecialchars($certificate['full_name']); ?></div>
                <div class="course-info">
                    has successfully completed the course<br>
                    <span class="course-name"><?php echo htmlspecialchars($kursus_nama); ?></span><br>
                    <span class="course-code">(<?php echo htmlspecialchars($certificate['kursus_kode']); ?>)</span>
                </div>
                <div class="score-info">
                    Final Score: <strong><?php echo number_format($certificate['final_score'], 1); ?>%</strong>
                </div>
            </div>
            
            <div class="certificate-footer">
                <div class="footer-item">
                    <div class="date-info">Issued on: <?php echo date('F d, Y', strtotime($certificate['issued_date'])); ?></div>
                </div>
                <div class="footer-item seal">
                    OFFICIAL<br>CERTIFICATE
                </div>
                <div class="footer-item">
                    <div class="signature-line"><?php echo htmlspecialchars($certificate['pengajar_nama'] ?? 'Course Instructor'); ?></div>
                </div>
            </div>
            
            <div class="certificate-number">Certificate No: <?php echo htmlspecialchars($certificate['certificate_number']); ?></div>
            <div class="verification-code">Verify: <?php echo htmlspecialchars($certificate['verification_code']); ?></div>
        </div>
    </div>
    
    <div class="mt-4 text-center">
        <p class="text-muted">
            <i class="fas fa-link"></i> Verification URL: 
            <a href="<?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/certificate_view.php?verify=' . urlencode($certificate['verification_code']); ?>">
                <?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/certificate_view.php?verify=' . urlencode($certificate['verification_code']); ?>
            </a>
        </p>
    </div>
</body>
</html>
