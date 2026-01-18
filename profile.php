<?php
require_once __DIR__ . '/config/config.php';
require_login();

$user = get_logged_user();
$page_title = t('profile');

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    require_csrf();
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
        $stmt->execute([$full_name, $email, $user['id']]);
        set_flash('success', 'Profil berhasil diperbarui');
        
        // Refresh user data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $_SESSION['user'] = $stmt->fetch();
    } catch (PDOException $e) {
        set_flash('error', 'Error: ' . $e->getMessage());
    }
    
    header('Location: /profile.php');
    exit;
}

require __DIR__ . '/components/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-user-circle"></i> <?php echo t('profile'); ?></h1>
            <p class="text-muted">Kelola informasi profil Anda</p>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-4">
            <div class="card text-center mb-4">
                <div class="card-body py-5">
                    <div class="position-relative d-inline-block" style="margin-bottom: 20px;">
                        <?php if (!empty($user['profile_photo'])): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>?t=<?php echo time(); ?>" alt="Profile" style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 3px solid #007E6E;">
                        <?php else: ?>
                            <div style="font-size: 80px; color: #007E6E;">
                                <i class="fas fa-user-circle"></i>
                            </div>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-primary" style="position: absolute; bottom: 0; right: 0; border-radius: 50%; padding: 8px 10px;" onclick="document.getElementById('photoInput').click()" title="Change photo">
                            <i class="fas fa-camera"></i>
                        </button>
                    </div>
                    <input type="file" id="photoInput" accept="image/*" style="display: none;" onchange="uploadProfilePhoto(this)">
                    <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                    <p class="text-muted mb-3"><?php echo htmlspecialchars($user['email']); ?></p>
                    
                    <?php if ($user['role'] === 'admin'): ?>
                        <span class="badge bg-danger">Admin</span>
                    <?php elseif ($user['role'] === 'pengajar'): ?>
                        <span class="badge bg-primary">Pengajar</span>
                    <?php else: ?>
                        <span class="badge bg-success">Mahasiswa</span>
                    <?php endif; ?>
                    
                    <hr>
                    <div class="text-start small">
                        <p class="mb-2">
                            <strong>Username:</strong><br>
                            <code><?php echo htmlspecialchars($user['username']); ?></code>
                        </p>
                        <p class="mb-2">
                            <strong>Tergabung:</strong><br>
                            <?php echo date('d M Y', strtotime($user['created_at'])); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-edit"></i> Edit Profil</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                            <small class="text-muted">Username tidak dapat diubah</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><?php echo t('full_name'); ?> *</label>
                            <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><?php echo t('email'); ?> *</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><?php echo t('role'); ?></label>
                            <input type="text" class="form-control" value="<?php 
                                if ($user['role'] === 'admin') echo 'Administrator';
                                elseif ($user['role'] === 'pengajar') echo 'Pengajar';
                                else echo 'Mahasiswa';
                            ?>" disabled>
                            <small class="text-muted">Role tidak dapat diubah</small>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?php echo t('save'); ?>
                            </button>
                            <a href="/index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Kembali
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-image"></i> Foto Profil</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Upload foto profil Anda (PNG, JPG, GIF, WEBP | Max: 2MB)</p>
                    <form id="photoForm" enctype="multipart/form-data">
                        <?php echo csrf_field(); ?>
                        <div class="mb-3">
                            <input type="file" name="profile_photo" id="photoInput2" class="form-control" accept="image/*" required>
                        </div>
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="fas fa-upload"></i> Unggah Foto
                        </button>
                    </form>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/profile-photo.js"></script>
<?php require __DIR__ . '/components/footer.php'; ?>
