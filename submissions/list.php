<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

$pageTitle = 'Pengumpulan Tugas';
include __DIR__ . '/../inc/header.php';

$db = getDB();
$userId = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? 'siswa';
$subs = [];

if ($role === 'guru') {
    $stmt = $db->prepare("SELECT sub.id, sub.assignment_id, sub.user_id, u.name AS student, sub.submitted_at, sub.status, a.judul FROM submissions sub JOIN users u ON sub.user_id = u.id JOIN assignments a ON sub.assignment_id = a.id WHERE a.created_by = ? ORDER BY sub.submitted_at DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $subs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $stmt = $db->prepare("SELECT sub.id, sub.assignment_id, sub.submitted_at, sub.status, a.judul FROM submissions sub JOIN assignments a ON sub.assignment_id = a.id WHERE sub.user_id = ? ORDER BY sub.submitted_at DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $subs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<div class="container">
    <div class="page-header">
        <h1>Pengumpulan Tugas</h1>
    </div>

    <?php if (empty($subs)): ?>
        <div class="card"><p>Tidak ada pengumpulan.</p></div>
    <?php else: ?>
        <div class="card">
            <table class="table">
                <thead><tr><th>#</th><th>Tugas</th><th>Pengumpul</th><th>Dikirim</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                    <?php foreach ($subs as $i => $s): ?>
                        <tr>
                            <td><?php echo $i+1; ?></td>
                            <td><?php echo sanitize($s['judul'] ?? $s['assignment_id']); ?></td>
                            <td><?php echo sanitize($s['student'] ?? ($_SESSION['name'] ?? '-')); ?></td>
                            <td><?php echo sanitize($s['submitted_at'] ?? '-'); ?></td>
                            <td><?php echo sanitize($s['status'] ?? '-'); ?></td>
                            <td>
                                <a href="/web_MG/submissions/view.php?id=<?php echo (int)$s['id']; ?>">Lihat</a>
                                <?php if ($role === 'guru'): ?>
                                    &middot; <a href="/web_MG/submissions/grade.php?id=<?php echo (int)$s['id']; ?>">Beri Nilai</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
