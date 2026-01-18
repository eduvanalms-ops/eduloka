<?php
require_once __DIR__ . '/config/config.php';
require_login();

$user = get_logged_user();
$page_title = t('dashboard');

// Get statistics based on role
if ($user['role'] === 'admin') {
    $stats = [
        'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'total_programs' => $pdo->query("SELECT COUNT(*) FROM program_studi")->fetchColumn(),
        'total_courses' => $pdo->query("SELECT COUNT(*) FROM kursus")->fetchColumn(),
        'total_students' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'mahasiswa'")->fetchColumn(),
    ];
} elseif ($user['role'] === 'pengajar') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM kursus WHERE pengajar_id = ?");
    $stmt->execute([$user['id']]);
    $my_courses = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT mahasiswa_id) FROM kursus_enrollments ke JOIN kursus k ON ke.kursus_id = k.id WHERE k.pengajar_id = ?");
    $stmt->execute([$user['id']]);
    $total_students = $stmt->fetchColumn();
    
    $stats = [
        'my_courses' => $my_courses,
        'total_students' => $total_students,
        'pending_assignments' => 0,
    ];
} else {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM kursus_enrollments WHERE mahasiswa_id = ?");
    $stmt->execute([$user['id']]);
    $stats = [
        'enrolled_courses' => $stmt->fetchColumn(),
        'pending_assignments' => 0,
        'completed_courses' => 0,
    ];
}

// Get recent courses
if ($user['role'] === 'admin') {
    $courses = $pdo->query("SELECT k.*, p.nama_id as prodi_nama, u.full_name as pengajar_nama FROM kursus k LEFT JOIN program_studi p ON k.program_studi_id = p.id LEFT JOIN users u ON k.pengajar_id = u.id ORDER BY k.created_at DESC LIMIT 5")->fetchAll();
} elseif ($user['role'] === 'pengajar') {
    $stmt = $pdo->prepare("SELECT k.*, p.nama_id as prodi_nama FROM kursus k LEFT JOIN program_studi p ON k.program_studi_id = p.id WHERE k.pengajar_id = ? ORDER BY k.created_at DESC LIMIT 5");
    $stmt->execute([$user['id']]);
    $courses = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT k.*, p.nama_id as prodi_nama, u.full_name as pengajar_nama FROM kursus k JOIN kursus_enrollments ke ON k.id = ke.kursus_id LEFT JOIN program_studi p ON k.program_studi_id = p.id LEFT JOIN users u ON k.pengajar_id = u.id WHERE ke.mahasiswa_id = ? ORDER BY ke.enrolled_at DESC LIMIT 5");
    $stmt->execute([$user['id']]);
    $courses = $stmt->fetchAll();
}

require __DIR__ . '/components/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-tachometer-alt"></i> <?php echo t('dashboard'); ?></h1>
            <p class="text-muted"><?php echo t('welcome'); ?>, <?php echo htmlspecialchars($user['full_name']); ?>!</p>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <?php if ($user['role'] === 'admin'): ?>
            <div class="col-md-3 mb-3">
                <div class="card stat-card primary">
                    <div class="card-body">
                        <h6 class="text-muted"><?php echo t('users'); ?></h6>
                        <h2><?php echo $stats['total_users']; ?></h2>
                        <i class="fas fa-users fa-2x text-primary opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card success">
                    <div class="card-body">
                        <h6 class="text-muted"><?php echo t('program_studi'); ?></h6>
                        <h2><?php echo $stats['total_programs']; ?></h2>
                        <i class="fas fa-building fa-2x text-success opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card info">
                    <div class="card-body">
                        <h6 class="text-muted"><?php echo t('courses'); ?></h6>
                        <h2><?php echo $stats['total_courses']; ?></h2>
                        <i class="fas fa-book fa-2x text-info opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card warning">
                    <div class="card-body">
                        <h6 class="text-muted"><?php echo t('total_students'); ?></h6>
                        <h2><?php echo $stats['total_students']; ?></h2>
                        <i class="fas fa-user-graduate fa-2x text-warning opacity-25"></i>
                    </div>
                </div>
            </div>
        <?php elseif ($user['role'] === 'pengajar'): ?>
            <div class="col-md-4 mb-3">
                <div class="card stat-card primary">
                    <div class="card-body">
                        <h6 class="text-muted"><?php echo t('my_courses'); ?></h6>
                        <h2><?php echo $stats['my_courses']; ?></h2>
                        <i class="fas fa-chalkboard-teacher fa-2x text-primary opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card stat-card success">
                    <div class="card-body">
                        <h6 class="text-muted"><?php echo t('total_students'); ?></h6>
                        <h2><?php echo $stats['total_students']; ?></h2>
                        <i class="fas fa-users fa-2x text-success opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card stat-card warning">
                    <div class="card-body">
                        <h6 class="text-muted">Pending Submissions</h6>
                        <h2><?php echo $stats['pending_assignments']; ?></h2>
                        <i class="fas fa-tasks fa-2x text-warning opacity-25"></i>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="col-md-4 mb-3">
                <div class="card stat-card primary">
                    <div class="card-body">
                        <h6 class="text-muted"><?php echo t('enrolled'); ?> <?php echo t('courses'); ?></h6>
                        <h2><?php echo $stats['enrolled_courses']; ?></h2>
                        <i class="fas fa-book-reader fa-2x text-primary opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card stat-card warning">
                    <div class="card-body">
                        <h6 class="text-muted">Pending <?php echo t('assignment'); ?></h6>
                        <h2><?php echo $stats['pending_assignments']; ?></h2>
                        <i class="fas fa-clipboard-list fa-2x text-warning opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card stat-card success">
                    <div class="card-body">
                        <h6 class="text-muted">Completed</h6>
                        <h2><?php echo $stats['completed_courses']; ?></h2>
                        <i class="fas fa-check-circle fa-2x text-success opacity-25"></i>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Recent/My Courses -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-book"></i> 
                        <?php echo $user['role'] === 'admin' ? t('recent_activities') : t('my_courses'); ?>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($courses)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <p><?php echo t('no_data'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th><?php echo t('course_code'); ?></th>
                                        <th><?php echo t('course_name'); ?></th>
                                        <th><?php echo t('program_studi'); ?></th>
                                        <?php if ($user['role'] !== 'pengajar'): ?>
                                        <th><?php echo t('lecturer'); ?></th>
                                        <?php endif; ?>
                                        <th><?php echo t('credits'); ?></th>
                                        <th><?php echo t('view'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($courses as $course): 
                                        $nama_kursus = get_language() === 'id' ? $course['nama_id'] : $course['nama_en'];
                                    ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($course['kode']); ?></code></td>
                                        <td><?php echo htmlspecialchars($nama_kursus); ?></td>
                                        <td><?php echo htmlspecialchars($course['prodi_nama'] ?? '-'); ?></td>
                                        <?php if ($user['role'] !== 'pengajar'): ?>
                                        <td><?php echo htmlspecialchars($course['pengajar_nama'] ?? '-'); ?></td>
                                        <?php endif; ?>
                                        <td><span class="badge bg-primary"><?php echo $course['sks']; ?> SKS</span></td>
                                        <td>
                                            <a href="/course_view.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> <?php echo t('view'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/components/footer.php'; ?>
