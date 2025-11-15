<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

$pageTitle = 'Pengumuman';
include __DIR__ . '/../inc/header.php';

$db = getDB();
$userId = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? 'siswa';

$stmt = $db->prepare("SELECT id, title, content, created_by, created_at FROM announcements ORDER BY created_at DESC");
$stmt->execute();
$ann = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="container">
    <div class="page-header">
        <h1>Pengumuman</h1>
        <?php if ($role === 'admin' || $role === 'guru'): ?>
            <a href="/web_MG/announcements/create.php" class="btn btn-primary">+ Buat Pengumuman</a>
        <?php endif; ?>
    </div>

    <?php if (empty($ann)): ?>
        <div class="card"><p>Tidak ada pengumuman.</p></div>
    <?php else: ?>
        <div class="card">
            <?php foreach ($ann as $a): ?>
                <div class="announcement" style="padding:12px; border-bottom:1px solid #eee;">
                    <h3><?php echo sanitize($a['title']); ?></h3>
                    <div style="color:#666; font-size:13px;"><?php echo sanitize($a['created_at']); ?></div>
                    <p><?php echo nl2br(sanitize($a['content'])); ?></p>
                    <div><a href="/web_MG/announcements/view.php?id=<?php echo (int)$a['id']; ?>">Lihat detail</a></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
