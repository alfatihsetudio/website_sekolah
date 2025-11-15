<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

$pageTitle = 'Daftar Nilai';
include __DIR__ . '/../inc/header.php';

$db = getDB();
$userId = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? 'siswa';
$rows = [];

if ($role === 'guru') {
    $stmt = $db->prepare("SELECT g.id, g.submission_id, g.score, g.feedback, u.name AS student, a.judul FROM grades g JOIN submissions s ON g.submission_id = s.id JOIN users u ON s.user_id = u.id JOIN assignments a ON s.assignment_id = a.id WHERE a.created_by = ? ORDER BY g.id DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $stmt = $db->prepare("SELECT g.id, g.submission_id, g.score, g.feedback, a.judul FROM grades g JOIN submissions s ON g.submission_id = s.id JOIN assignments a ON s.assignment_id = a.id WHERE s.user_id = ? ORDER BY g.id DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<div class="container">
    <div class="page-header">
        <h1>Daftar Nilai</h1>
    </div>

    <?php if (empty($rows)): ?>
        <div class="card"><p>Tidak ada nilai.</p></div>
    <?php else: ?>
        <div class="card">
            <table class="table">
                <thead><tr><th>#</th><th>Tugas</th><th>Siswa</th><th>Nilai</th><th>Feedback</th><th>Aksi</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $i => $r): ?>
                        <tr>
                            <td><?php echo $i+1; ?></td>
                            <td><?php echo sanitize($r['judul'] ?? '-'); ?></td>
                            <td><?php echo sanitize($r['student'] ?? '-'); ?></td>
                            <td><?php echo sanitize($r['score']); ?></td>
                            <td><?php echo sanitize($r['feedback'] ?? '-'); ?></td>
                            <td><a href="/web_MG/grades/view.php?id=<?php echo (int)$r['id']; ?>">Lihat</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
