<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/rate_limiter.php';

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: /index.php');
    exit;
}

// Initialize rate limiter (5 attempts per 15 minutes)
$rateLimiter = new RateLimiter($pdo, 5, 15);
$rateLimitKey = getRateLimiterKey('login');

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/config/activity_logger.php';
    
    // Check rate limit
    if ($rateLimiter->tooManyAttempts($rateLimitKey)) {
        $waitTime = $rateLimiter->availableIn($rateLimitKey);
        $waitMinutes = ceil($waitTime / 60);
        set_flash('error', "Terlalu banyak percobaan login. Silakan tunggu $waitMinutes menit.");
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if ($username && $password) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && password_verify($password, $user['password'])) {
                    // Clear rate limit on successful login
                    $rateLimiter->clear($rateLimitKey);
                    
                    $_SESSION['user'] = $user;
                    log_activity('login', 'user', $user['id'], "Login berhasil: {$user['full_name']}", 'success');
                    set_flash('success', 'Login berhasil!');
                    header('Location: /index.php');
                    exit;
                } else {
                    // Record failed attempt
                    $remaining = $rateLimiter->hit($rateLimitKey);
                    log_activity('login_failed', 'user', null, "Percobaan login gagal untuk: $username", 'failed');
                    
                    if ($remaining > 0) {
                        set_flash('error', "Username atau password salah! Sisa percobaan: $remaining");
                    } else {
                        $waitTime = $rateLimiter->availableIn($rateLimitKey);
                        $waitMinutes = ceil($waitTime / 60);
                        set_flash('error', "Terlalu banyak percobaan. Silakan tunggu $waitMinutes menit.");
                    }
                }
            } catch (Exception $e) {
                log_activity('login_error', 'user', null, "Error login: " . $e->getMessage(), 'failed');
                set_flash('error', 'Error: ' . $e->getMessage());
            }
        } else {
            set_flash('error', 'Silakan isi username dan password!');
        }
    }
}

// Get available courses from database (max 4)
$courses = [];
try {
    // Cast is_active to boolean to handle both PostgreSQL boolean and integer formats
    $stmt = $pdo->query("SELECT id, nama_id, nama_en, deskripsi_id, deskripsi_en, is_active::text as active_status FROM kursus ORDER BY created_at DESC LIMIT 10");
    $all_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filter only active courses
    foreach ($all_courses as $course) {
        if ($course['active_status'] === 't' || $course['active_status'] === 'true' || $course['active_status'] == '1') {
            if (!empty($course['nama_id']) || !empty($course['nama_en'])) {
                $courses[] = $course;
                if (count($courses) >= 4) break;
            }
        }
    }
} catch (Exception $e) {
    // If no courses, fallback to empty array
    $courses = [];
}

$page_title = t('login');
?>
<!DOCTYPE html>
<html lang="<?php echo get_language(); ?>" data-theme="<?php echo get_theme(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('login'); ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #007E6E 0%, #006158 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 2rem 1rem;
        }

        .login-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
        }

        .courses-side {
            color: white;
        }

        .courses-side h2 {
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 2rem;
        }

        .courses-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .course-item {
            background: rgba(255,255,255,0.1);
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid #E7DEAF;
            backdrop-filter: blur(10px);
        }

        .course-item h4 {
            margin: 0;
            font-weight: 600;
        }

        .course-item p {
            margin: 0.5rem 0 0;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .login-side {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            padding: 2.5rem;
            width: 100%;
            max-width: 450px;
        }

        [data-theme="dark"] .login-card {
            background-color: #2d2d2d;
            color: #e9ecef;
        }

        .app-branding {
            text-align: center;
            margin-bottom: 2.5rem;
            padding: 1.5rem;
            border-bottom: 2px solid #E7DEAF;
            background: linear-gradient(135deg, #4ab3a3 0%, #2d9b8a 100%);
            border-radius: 8px;
            border: 2px solid #1a8578;
        }

        .app-logo {
            max-width: 100%;
            height: auto;
            max-height: 120px;
            display: block;
            margin: 0 auto;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        }

        [data-theme="dark"] .app-branding {
            background: linear-gradient(135deg, #2a5a55 0%, #1a4a45 100%);
            border-bottom-color: #73AF6F;
            border-color: #3d7b73;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header h2 {
            font-weight: 700;
            color: #007E6E;
            margin: 0.5rem 0;
        }

        [data-theme="dark"] .login-header h2 {
            color: #80d4c8;
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 0.7rem;
        }

        [data-theme="dark"] .form-control {
            background-color: #1a1a1a;
            border-color: #495057;
            color: #e9ecef;
        }

        .btn-login {
            background-color: #007E6E;
            border: none;
            color: white;
            font-weight: 600;
            padding: 0.8rem;
            border-radius: 8px;
            transition: all 0.3s;
            width: 100%;
            margin-top: 1rem;
        }

        .btn-login:hover {
            background-color: #006158;
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 126, 110, 0.3);
        }

        .btn-login:active,
        .btn-login:focus {
            background-color: #005047;
            transform: translateY(0px);
            box-shadow: 0 4px 8px rgba(0, 126, 110, 0.2);
            outline: none;
        }

        .btn-login:disabled {
            background-color: #a8d4cc;
            cursor: not-allowed;
            opacity: 0.8;
        }

        .btn-login.loading {
            background-color: #a8d4cc;
            cursor: not-allowed;
        }

        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
            border-width: 0.2em;
        }

        .text-center a {
            color: #007E6E;
            text-decoration: none;
        }

        [data-theme="dark"] .text-center a {
            color: #80d4c8;
        }

        .demo-box {
            background-color: #e8f4f1;
            border-left: 4px solid #007E6E;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1.5rem;
            font-size: 0.85rem;
        }

        [data-theme="dark"] .demo-box {
            background-color: #1a3a37;
            color: #e9ecef;
        }

        .lang-switch {
            text-align: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #ddd;
        }

        [data-theme="dark"] .lang-switch {
            border-top-color: #495057;
        }

        .lang-switch a {
            margin: 0 0.5rem;
            text-decoration: none;
            transition: color 0.3s;
        }

        .lang-switch a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .login-wrapper {
                grid-template-columns: 1fr;
            }

            .login-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <!-- Courses Side -->
        <div class="courses-side">
            <h2><i class="fas fa-book"></i> <?php echo t('available_courses'); ?></h2>
            <div class="courses-grid">
                <?php if (!empty($courses)): ?>
                    <?php foreach ($courses as $course): 
                        $lang = get_language();
                        // Prioritize current language, fallback to other language
                        $nama = $lang === 'id' ? 
                            (!empty($course['nama_id']) ? $course['nama_id'] : $course['nama_en']) : 
                            (!empty($course['nama_en']) ? $course['nama_en'] : $course['nama_id']);
                        $deskripsi = $lang === 'id' ? 
                            (!empty($course['deskripsi_id']) ? $course['deskripsi_id'] : $course['deskripsi_en']) : 
                            (!empty($course['deskripsi_en']) ? $course['deskripsi_en'] : $course['deskripsi_id']);
                        if (!empty($nama)): // Only show if there's a course name
                    ?>
                    <div class="course-item">
                        <h4><?php echo htmlspecialchars($nama); ?></h4>
                        <p><?php echo htmlspecialchars($deskripsi); ?></p>
                    </div>
                    <?php endif; endforeach; ?>
                <?php else: ?>
                    <div class="course-item" style="grid-column: 1 / -1;">
                        <p style="text-align: center; opacity: 0.8;"><?php echo t('no_available_courses'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Login Side -->
        <div class="login-side">
            <div class="login-card">
                <?php 
                $logo_path = '/assets/images/logo.png';
                try {
                    $stmt = $pdo->query("SELECT setting_value FROM app_settings WHERE setting_key = 'logo_path' LIMIT 1");
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($result) {
                        $logo_path = $result['setting_value'];
                    }
                } catch (Exception $e) {}
                ?>
                <div class="app-branding">
                    <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="EduLoka" class="app-logo">
                </div>

                <div class="login-header">
                    <i class="fas fa-sign-in-alt fa-2x" style="color: #007E6E;"></i>
                    <h2><?php echo t('login'); ?></h2>
                    <p style="color: #666; margin: 0;"><?php echo t('login_subtitle'); ?></p>
                </div>

                <?php if ($error = get_flash('error')): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success = get_flash('success')): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/login.php" id="loginForm">
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-user"></i> <?php echo t('username_email'); ?></label>
                        <input type="text" name="username" class="form-control" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-lock"></i> <?php echo t('password'); ?></label>
                        <div class="input-group">
                            <input type="password" name="password" class="form-control" required id="passwordInput">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-sign-in-alt"></i> <?php echo t('login'); ?>
                    </button>
                </form>

                <div style="text-align: center; margin-top: 1rem;">
                    <a href="/forgot_password.php" style="color: #007E6E; text-decoration: none; font-size: 0.9rem;">
                        <i class="fas fa-key"></i> <?php echo t('forgot_password'); ?>
                    </a>
                </div>

                <div class="demo-box">
                    <strong><i class="fas fa-info-circle"></i> <?php echo t('demo_accounts'); ?></strong><br><br>
                    <strong style="color: #0d6efd;"><?php echo t('lecturer'); ?>:</strong> pengajar1 / password123<br>
                    <strong style="color: #198754;"><?php echo t('student'); ?>:</strong> mahasiswa1 / password123
                </div>

                <div class="lang-switch">
                    <small><?php echo t('language'); ?>:</small><br>
                    <a href="#" class="language-switch" data-lang="id">🇮🇩 ID</a> | 
                    <a href="#" class="language-switch" data-lang="en">🇬🇧 EN</a>
                </div>

                <div class="text-center mt-3">
                    <small><?php echo t('already_have_account'); ?> <a href="/register.php"><?php echo t('register_here'); ?></a></small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/app.js"></script>
    <script>
        const passwordInput = document.getElementById('passwordInput');
        const togglePassword = document.getElementById('togglePassword');

        togglePassword.addEventListener('click', function(e) {
            e.preventDefault();
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            const icon = togglePassword.querySelector('i');
            icon.className = type === 'text' ? 'fas fa-eye-slash' : 'fas fa-eye';
        });

        // Login button loading state
        const loginForm = document.getElementById('loginForm');
        const loginButton = loginForm.querySelector('.btn-login');

        loginForm.addEventListener('submit', function(e) {
            // Don't prevent default - let form submit normally
            loginButton.disabled = true;
            loginButton.classList.add('loading');
            loginButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> <?php echo t("processing"); ?>';
        });
    </script>
</body>
</html>
