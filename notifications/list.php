<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

$pageTitle = 'Notifikasi';
include __DIR__ . '/../inc/header.php';

$db = getDB();
$userId = (int)($_SESSION['user_id'] ?? 0);

$stmt = $db->prepare("SELECT id, title, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$notifs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="container">
    <div class="page-header">
        <h1>Notifikasi Saya</h1>
    </div>

    <?php if (empty($notifs)): ?>
        <div class="card"><p>Tidak ada notifikasi.</p></div>
    <?php else: ?>
        <div class="card">
            <ul class="list-group">
                <?php foreach ($notifs as $n): ?>
                    <li class="list-group-item" style="<?php echo $n['is_read'] ? '' : 'background:#f8fbff;'; ?>">
                        <strong><?php echo sanitize($n['title']); ?></strong>
                        <div style="font-size:13px; color:#555;"><?php echo sanitize($n['message']); ?></div>
                        <div style="font-size:12px; color:#888;"><?php echo sanitize($n['created_at']); ?></div>
                        <div style="margin-top:6px;">
                            &middot; <a href="/web_MG/notifications/mark_read.php?id=<?php echo (int)$n['id']; ?>">Tandai sudah dibaca</a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
