<?php
require_once __DIR__ . '/config/config.php';
require_role(['admin', 'pengajar']);

$kursus_id = (int)($_GET['kursus_id'] ?? 0);
if (!$kursus_id) {
    header('Location: /index.php');
    exit;
}

// Get enrolled students with grades from gradebook
$students = $pdo->prepare("
    SELECT u.*, ke.enrolled_at, g.final_score as nilai_akhir, g.grade
    FROM users u
    JOIN kursus_enrollments ke ON u.id = ke.mahasiswa_id
    LEFT JOIN gradebook g ON g.user_id = u.id AND g.kursus_id = ?
    WHERE ke.kursus_id = ?
    ORDER BY u.full_name
");
$students->execute([$kursus_id, $kursus_id]);
$students = $students->fetchAll();

$page_title = 'Kelola Mahasiswa';
require __DIR__ . '/components/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-users"></i> Kelola Mahasiswa</h1>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Terdaftar</th>
                            <th>Nilai Akhir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                            <td><?php echo date('d M Y', strtotime($student['enrolled_at'])); ?></td>
                            <td><?php echo $student['nilai_akhir'] ?? '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">Belum ada mahasiswa terdaftar</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/components/footer.php'; ?>
