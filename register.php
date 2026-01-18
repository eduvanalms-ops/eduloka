<?php
require_once __DIR__ . '/config/config.php';

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: /index.php');
    exit;
}

$errors = [];
$success = false;

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/config/activity_logger.php';
    
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'mahasiswa';
    $terms = $_POST['terms'] ?? '';
    
    // Validation
    if (empty($full_name)) {
        $errors[] = t('full_name') . ' harus diisi';
    }
    
    if (empty($username)) {
        $errors[] = t('username_required');
    } elseif (strlen($username) < 3) {
        $errors[] = t('username_min_chars');
    }
    
    if (empty($email)) {
        $errors[] = t('email_required');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = t('email_invalid');
    }
    
    if (empty($password)) {
        $errors[] = t('password_required');
    } elseif (strlen($password) < 6) {
        $errors[] = t('password_min_chars');
    }
    
    if ($password !== $confirm_password) {
        $errors[] = t('password_mismatch');
    }
    
    if (!in_array($role, ['mahasiswa', 'pengajar'])) {
        $errors[] = 'Role tidak valid';
    }
    
    if (!$terms) {
        $errors[] = t('agree_terms_required');
    }
    
    if (empty($errors)) {
        try {
            // Check if username or email exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetch()) {
                $errors[] = t('username_email_exists');
            } else {
                // Create new user
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, nama_lengkap, role, language, theme)
                    VALUES (?, ?, ?, ?, ?, 'id', 'light')
                ");
                
                if ($stmt->execute([$username, $email, $hashed_password, $full_name, $role])) {
                    $new_user_id = $pdo->lastInsertId();
                    log_activity('register', 'user', $new_user_id, "Pendaftaran baru: $full_name ($role)", 'success');
                    set_flash('success', t('register_success'));
                    header('Location: /login.php');
                    exit;
                }
            }
        } catch (Exception $e) {
            $errors[] = 'Error: ' . $e->getMessage();
        }
    }
}

$page_title = t('register');
?>
<!DOCTYPE html>
<html lang="<?php echo get_language(); ?>" data-theme="<?php echo get_theme(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('register'); ?> - <?php echo APP_NAME; ?></title>
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

        .register-container {
            max-width: 600px;
            width: 100%;
            margin: 0 auto;
        }

        .register-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            padding: 2.5rem;
        }

        [data-theme="dark"] .register-card {
            background-color: #2d2d2d;
            color: #e9ecef;
        }

        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .register-header h2 {
            font-weight: 700;
            color: #007E6E;
            margin: 0.5rem 0;
            font-size: 1.8rem;
        }

        [data-theme="dark"] .register-header h2 {
            color: #80d4c8;
        }

        .register-header p {
            color: #666;
            margin: 0;
            font-size: 0.95rem;
        }

        [data-theme="dark"] .register-header p {
            color: #adb5bd;
        }

        /* Two column form layout */
        .form-row-two {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 640px) {
            .form-row-two {
                grid-template-columns: 1fr;
            }
        }

        /* Input group with icon on left */
        .input-group-left-icon .input-group-text {
            background-color: #f0f0f0;
            border: 1px solid #ddd;
            border-right: none;
            color: #666;
            font-size: 1.1rem;
        }

        [data-theme="dark"] .input-group-left-icon .input-group-text {
            background-color: #3a3a3a;
            border-color: #495057;
            color: #adb5bd;
        }

        .input-group-left-icon .form-control {
            border: 1px solid #ddd;
            border-left: none;
            border-radius: 0 8px 8px 0;
        }

        [data-theme="dark"] .input-group-left-icon .form-control {
            border-color: #495057;
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

        /* Role selection */
        .role-selection {
            margin: 2rem 0;
            padding: 1.5rem;
            background-color: #f8f9fa;
            border-radius: 8px;
        }

        [data-theme="dark"] .role-selection {
            background-color: #3a3a3a;
        }

        .role-selection h5 {
            margin-bottom: 1rem;
            font-weight: 600;
            color: #007E6E;
        }

        [data-theme="dark"] .role-selection h5 {
            color: #80d4c8;
        }

        .role-options {
            display: flex;
            gap: 2rem;
        }

        .role-option {
            display: flex;
            align-items: center;
            cursor: pointer;
        }

        .role-option input[type="radio"] {
            cursor: pointer;
            margin-right: 0.5rem;
            width: 18px;
            height: 18px;
            accent-color: #007E6E;
        }

        .role-option label {
            cursor: pointer;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
        }

        .role-option label i {
            font-size: 1.2rem;
        }

        /* Terms checkbox */
        .terms-checkbox {
            margin: 1.5rem 0;
            font-size: 0.95rem;
        }

        .terms-checkbox input[type="checkbox"] {
            accent-color: #007E6E;
            margin-right: 0.5rem;
            cursor: pointer;
        }

        .terms-checkbox a {
            color: #007E6E;
            text-decoration: none;
        }

        [data-theme="dark"] .terms-checkbox a {
            color: #80d4c8;
        }

        .terms-checkbox a:hover {
            text-decoration: underline;
        }

        .btn-register {
            background-color: #007E6E;
            border: none;
            color: white;
            font-weight: 600;
            padding: 1rem;
            border-radius: 8px;
            transition: all 0.3s;
            width: 100%;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-register:hover {
            background-color: #006158;
            transform: translateY(-2px);
        }

        .text-center a {
            color: #007E6E;
            text-decoration: none;
        }

        [data-theme="dark"] .text-center a {
            color: #80d4c8;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 1rem;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }

        .back-link:hover {
            color: #E7DEAF;
            transform: translateX(-5px);
        }
    </style>
</head>
<body>
    <div class="register-container">
        <a href="/login.php" class="back-link"><i class="fas fa-arrow-left"></i> <?php echo t('back_to_login'); ?></a>

        <div class="register-card">
            <div class="register-header">
                <i class="fas fa-user-plus fa-2x" style="color: #007E6E;"></i>
                <h2><?php echo t('register'); ?></h2>
                <p><?php echo t('create_new_account'); ?></p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <ul style="margin: 0; padding-left: 1.5rem;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="/register.php" id="registerForm">
                <!-- Username and Nama Lengkap Row -->
                <div class="form-row-two">
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('username'); ?></label>
                        <div class="input-group input-group-left-icon">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" name="username" class="form-control" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('full_name'); ?></label>
                        <div class="input-group input-group-left-icon">
                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                            <input type="text" name="full_name" class="form-control" required value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- Email -->
                <div class="mb-3">
                    <label class="form-label"><?php echo t('email'); ?></label>
                    <div class="input-group input-group-left-icon">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Password -->
                <div class="mb-3">
                    <label class="form-label"><?php echo t('password'); ?></label>
                    <div class="input-group input-group-left-icon">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" class="form-control" required id="passwordInput">
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword" style="border: 1px solid #ddd;">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Konfirmasi Password -->
                <div class="mb-3">
                    <label class="form-label"><?php echo t('confirm_password'); ?></label>
                    <div class="input-group input-group-left-icon">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" name="confirm_password" class="form-control" required id="confirmPasswordInput">
                        <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword" style="border: 1px solid #ddd;">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Role Selection -->
                <div class="role-selection">
                    <h5><i class="fas fa-user-tag"></i> <?php echo t('register_as'); ?></h5>
                    <div class="role-options">
                        <div class="role-option">
                            <input type="radio" id="role_mahasiswa" name="role" value="mahasiswa" checked>
                            <label for="role_mahasiswa">
                                <i class="fas fa-graduation-cap"></i> <?php echo t('student'); ?>
                            </label>
                        </div>
                        <div class="role-option">
                            <input type="radio" id="role_pengajar" name="role" value="pengajar">
                            <label for="role_pengajar">
                                <i class="fas fa-chalkboard-user"></i> <?php echo t('lecturer'); ?>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Terms Checkbox -->
                <div class="terms-checkbox">
                    <input type="checkbox" id="terms" name="terms" value="1" required>
                    <label for="terms" style="display: inline;">
                        <?php echo t('i_agree_terms'); ?> <a href="#" onclick="return false;"><?php echo t('terms_of_service'); ?></a>
                    </label>
                </div>

                <!-- Register Button -->
                <button type="submit" class="btn btn-register">
                    <i class="fas fa-user-plus"></i> <?php echo t('register'); ?>
                </button>
            </form>

            <div class="text-center mt-3">
                <small><?php echo t('already_have_account_q'); ?> <a href="/login.php"><?php echo t('login_now'); ?></a></small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/app.js"></script>
    <script>
        const passwordInput = document.getElementById('passwordInput');
        const confirmPasswordInput = document.getElementById('confirmPasswordInput');
        const togglePassword = document.getElementById('togglePassword');
        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');

        function toggleVisibility(input, btn) {
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            const icon = btn.querySelector('i');
            icon.className = type === 'text' ? 'fas fa-eye-slash' : 'fas fa-eye';
        }

        togglePassword.addEventListener('click', (e) => {
            e.preventDefault();
            toggleVisibility(passwordInput, togglePassword);
        });

        toggleConfirmPassword.addEventListener('click', (e) => {
            e.preventDefault();
            toggleVisibility(confirmPasswordInput, toggleConfirmPassword);
        });
    </script>
</body>
</html>
