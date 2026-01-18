<?php
require_once __DIR__ . '/config/config.php';

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: /index.php');
    exit;
}

$token = $_GET['token'] ?? '';
$error = '';
$valid_token = false;

if (!$token) {
    $error = t('invalid_token');
} else {
    try {
        $stmt = $pdo->prepare("
            SELECT prt.*, u.email, u.full_name
            FROM password_reset_tokens prt
            JOIN users u ON prt.user_id = u.id
            WHERE prt.token = ? AND prt.expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $reset_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reset_data) {
            $error = t('reset_link_expired');
        } else {
            $valid_token = true;
        }
    } catch (Exception $e) {
        $error = t('invalid_token');
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'])) {
    $post_token = $_POST['token'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($new_password !== $confirm_password) {
        set_flash('error', t('passwords_not_match'));
        header('Location: /reset_password.php?token=' . urlencode($post_token));
        exit;
    }

    if (strlen($new_password) < 6) {
        set_flash('error', 'Password minimal 6 karakter');
        header('Location: /reset_password.php?token=' . urlencode($post_token));
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT prt.*, u.id
            FROM password_reset_tokens prt
            JOIN users u ON prt.user_id = u.id
            WHERE prt.token = ? AND prt.expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([$post_token]);
        $reset_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reset_data) {
            set_flash('error', t('reset_link_expired'));
            header('Location: /forgot_password.php');
            exit;
        }

        // Update password
        $user_id = $reset_data['id'];
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        
        $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update_stmt->execute([$hashed_password, $user_id]);

        // Delete used token
        $delete_stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
        $delete_stmt->execute([$post_token]);

        // Log activity
        require_once __DIR__ . '/config/activity_logger.php';
        log_activity('password_reset', 'user', $user_id, "Password reset successful", 'success');

        set_flash('success', t('password_reset_success'));
        header('Location: /login.php');
        exit;
    } catch (Exception $e) {
        set_flash('error', 'Error: ' . $e->getMessage());
        header('Location: /reset_password.php?token=' . urlencode($post_token));
        exit;
    }
}

$page_title = t('reset_password_title');
?>
<!DOCTYPE html>
<html lang="<?php echo get_language(); ?>" data-theme="<?php echo get_theme(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('reset_password_title'); ?> - <?php echo APP_NAME; ?></title>
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

        .reset-container {
            max-width: 450px;
            width: 100%;
            margin: 0 auto;
        }

        .reset-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            padding: 2.5rem;
        }

        [data-theme="dark"] .reset-card {
            background-color: #2d2d2d;
            color: #e9ecef;
        }

        .reset-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .reset-header h2 {
            font-weight: 700;
            color: #007E6E;
            margin: 1rem 0;
        }

        [data-theme="dark"] .reset-header h2 {
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

        .btn-reset {
            background-color: #007E6E;
            border: none;
            color: white;
            font-weight: 600;
            padding: 0.8rem;
            border-radius: 8px;
            width: 100%;
            transition: all 0.3s;
            margin-top: 1rem;
        }

        .btn-reset:hover {
            background-color: #006158;
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 126, 110, 0.3);
            color: white;
        }

        .btn-reset:disabled {
            background-color: #a8d4cc;
            cursor: not-allowed;
            opacity: 0.8;
        }

        .back-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #ddd;
        }

        [data-theme="dark"] .back-link {
            border-top-color: #495057;
        }

        .back-link a {
            color: #007E6E;
            text-decoration: none;
        }

        [data-theme="dark"] .back-link a {
            color: #80d4c8;
        }

        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <div class="reset-header">
                <i class="fas fa-lock fa-2x" style="color: #007E6E;"></i>
                <h2><?php echo t('reset_password_title'); ?></h2>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <div class="back-link">
                    <a href="/forgot_password.php">
                        <i class="fas fa-arrow-left"></i> <?php echo t('back_to_login'); ?>
                    </a>
                </div>
            <?php elseif ($valid_token): ?>
                <form method="POST" action="/reset_password.php" id="resetForm">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-lock"></i> <?php echo t('new_password'); ?></label>
                        <div class="input-group">
                            <input type="password" name="new_password" class="form-control" required id="passwordInput" placeholder="<?php echo t('new_password'); ?>">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-check-circle"></i> <?php echo t('confirm_password'); ?></label>
                        <div class="input-group">
                            <input type="password" name="confirm_password" class="form-control" required id="confirmInput" placeholder="<?php echo t('confirm_password'); ?>">
                            <button class="btn btn-outline-secondary" type="button" id="toggleConfirm">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-reset" id="resetBtn">
                        <i class="fas fa-check"></i> <?php echo t('reset_password'); ?>
                    </button>
                </form>

                <div class="back-link">
                    <a href="/login.php">
                        <i class="fas fa-arrow-left"></i> <?php echo t('back_to_login'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const passwordInput = document.getElementById('passwordInput');
        const confirmInput = document.getElementById('confirmInput');
        const togglePassword = document.getElementById('togglePassword');
        const toggleConfirm = document.getElementById('toggleConfirm');

        if (togglePassword) {
            togglePassword.addEventListener('click', function(e) {
                e.preventDefault();
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.querySelector('i').className = type === 'text' ? 'fas fa-eye-slash' : 'fas fa-eye';
            });
        }

        if (toggleConfirm) {
            toggleConfirm.addEventListener('click', function(e) {
                e.preventDefault();
                const type = confirmInput.getAttribute('type') === 'password' ? 'text' : 'password';
                confirmInput.setAttribute('type', type);
                this.querySelector('i').className = type === 'text' ? 'fas fa-eye-slash' : 'fas fa-eye';
            });
        }

        const resetForm = document.getElementById('resetForm');
        if (resetForm) {
            resetForm.addEventListener('submit', function() {
                const resetBtn = document.getElementById('resetBtn');
                resetBtn.disabled = true;
                resetBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> <?php echo t("processing"); ?>';
            });
        }
    </script>
</body>
</html>
