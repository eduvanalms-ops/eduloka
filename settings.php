<?php
require_once __DIR__ . '/config/config.php';
require_login();

$user = get_logged_user();
$page_title = t('settings');

// Handle language change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_language'])) {
    require_csrf();
    $language = $_POST['language'] ?? 'id';
    
    if (in_array($language, ['id', 'en'])) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET language = ? WHERE id = ?");
            $stmt->execute([$language, $user['id']]);
            $_SESSION['language'] = $language;
            set_flash('success', 'Bahasa berhasil diubah');
        } catch (PDOException $e) {
            set_flash('error', 'Error: ' . $e->getMessage());
        }
    }
    
    header('Location: /settings.php');
    exit;
}

// Handle theme change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_theme'])) {
    require_csrf();
    $theme = $_POST['theme'] ?? 'light';
    
    if (in_array($theme, ['light', 'dark'])) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET theme = ? WHERE id = ?");
            $stmt->execute([$theme, $user['id']]);
            $_SESSION['theme'] = $theme;
            set_flash('success', 'Tema berhasil diubah');
        } catch (PDOException $e) {
            set_flash('error', 'Error: ' . $e->getMessage());
        }
    }
    
    header('Location: /settings.php');
    exit;
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    require_csrf();
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if ($new_password !== $confirm_password) {
        set_flash('error', 'Password baru tidak cocok');
    } elseif (strlen($new_password) < 6) {
        set_flash('error', 'Password minimal 6 karakter');
    } elseif (!password_verify($old_password, $user['password'])) {
        set_flash('error', 'Password lama tidak sesuai');
    } else {
        try {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $user['id']]);
            set_flash('success', 'Password berhasil diubah');
        } catch (PDOException $e) {
            set_flash('error', 'Error: ' . $e->getMessage());
        }
    }
    
    header('Location: /settings.php');
    exit;
}

// Get logo path
$logo_path = '/assets/images/logo.png';
try {
    $stmt = $pdo->query("SELECT setting_value FROM app_settings WHERE setting_key = 'logo_path' LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $logo_path = $result['setting_value'];
    }
} catch (Exception $e) {
    // Use default logo
}

require __DIR__ . '/components/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-cog"></i> <?php echo t('settings'); ?></h1>
            <p class="text-muted">Kelola preferensi dan keamanan akun Anda</p>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-8">
            <!-- Language Settings -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-globe"></i> Pengaturan Bahasa</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="d-flex gap-3 align-items-end">
                        <?php echo csrf_field(); ?>
                        <div class="flex-grow-1">
                            <label class="form-label">Pilih Bahasa</label>
                            <select name="language" class="form-select">
                                <option value="id" <?php echo get_language() === 'id' ? 'selected' : ''; ?>>🇮🇩 Indonesia</option>
                                <option value="en" <?php echo get_language() === 'en' ? 'selected' : ''; ?>>🇬🇧 English</option>
                            </select>
                        </div>
                        <button type="submit" name="change_language" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan
                        </button>
                    </form>
                    <small class="text-muted d-block mt-2">
                        Perubahan bahasa akan berlaku setelah halaman dimuat ulang
                    </small>
                </div>
            </div>
            
            <!-- Theme Settings -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-palette"></i> Pengaturan Tema</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="d-flex gap-3 align-items-end">
                        <?php echo csrf_field(); ?>
                        <div class="flex-grow-1">
                            <label class="form-label">Pilih Tema</label>
                            <select name="theme" class="form-select">
                                <option value="light" <?php echo get_theme() === 'light' ? 'selected' : ''; ?>>☀️ Terang (Light)</option>
                                <option value="dark" <?php echo get_theme() === 'dark' ? 'selected' : ''; ?>>🌙 Gelap (Dark)</option>
                            </select>
                        </div>
                        <button type="submit" name="change_theme" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan
                        </button>
                    </form>
                    <small class="text-muted d-block mt-2">
                        Tema akan berubah secara instan setelah pemilihan
                    </small>
                </div>
            </div>
            
            <!-- Logo Upload Settings (Admin Only) -->
            <?php if ($user['role'] === 'admin'): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-image"></i> Pengaturan Logo Aplikasi</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3 text-center">
                        <img src="<?php echo htmlspecialchars($logo_path); ?>?t=<?php echo time(); ?>" alt="Current Logo" class="img-fluid" style="max-height: 150px; margin-bottom: 1rem;">
                    </div>
                    <form id="logoForm" enctype="multipart/form-data">
                        <?php echo csrf_field(); ?>
                        <div class="mb-3">
                            <label class="form-label">Unggah Logo Baru</label>
                            <input type="file" name="logo" id="logoInput" class="form-control" accept="image/*" required>
                            <small class="text-muted">Format: PNG, JPG, GIF, WEBP | Max: 5MB</small>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Unggah Logo
                        </button>
                        <small class="text-muted d-block mt-2">Logo akan otomatis menyesuaikan ukuran di semua halaman</small>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Password Settings -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-lock"></i> Ubah Password</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Password Lama *</label>
                            <input type="password" name="old_password" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Password Baru *</label>
                            <input type="password" name="new_password" class="form-control" required>
                            <small class="text-muted">Minimal 6 karakter</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Konfirmasi Password Baru *</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn btn-danger">
                            <i class="fas fa-key"></i> Ubah Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> Informasi Akun</h5>
                </div>
                <div class="card-body">
                    <dl class="mb-0">
                        <dt>Username</dt>
                        <dd><code><?php echo htmlspecialchars($user['username']); ?></code></dd>
                        
                        <dt class="mt-3">Email</dt>
                        <dd><?php echo htmlspecialchars($user['email']); ?></dd>
                        
                        <dt class="mt-3">Role</dt>
                        <dd>
                            <?php if ($user['role'] === 'admin'): ?>
                                <span class="badge bg-danger">Admin</span>
                            <?php elseif ($user['role'] === 'pengajar'): ?>
                                <span class="badge bg-primary">Pengajar</span>
                            <?php else: ?>
                                <span class="badge bg-success">Mahasiswa</span>
                            <?php endif; ?>
                        </dd>
                        
                        <dt class="mt-3">Bahasa</dt>
                        <dd><?php echo get_language() === 'id' ? 'Indonesia' : 'English'; ?></dd>
                        
                        <dt class="mt-3">Tema</dt>
                        <dd><?php echo get_theme() === 'light' ? 'Terang' : 'Gelap'; ?></dd>
                        
                        <dt class="mt-3">Tergabung</dt>
                        <dd><?php echo date('d M Y H:i', strtotime($user['created_at'])); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/logo-upload.js"></script>
<?php require __DIR__ . '/components/footer.php'; ?>
