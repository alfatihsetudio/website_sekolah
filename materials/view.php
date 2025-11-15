<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
requireLogin();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: ' . BASE_URL . '/materials/list.php'); exit; }
$db = getDB();
$stmt = $db->prepare("SELECT m.*, s.nama_mapel, c.nama_kelas, u.name AS guru FROM materials m JOIN subjects s ON m.subject_id = s.id JOIN classes c ON s.class_id = c.id LEFT JOIN users u ON m.created_by = u.id WHERE m.id = ? LIMIT 1");
$stmt->bind_param("i", $id); $stmt->execute(); $m = $stmt->get_result()->fetch_assoc(); $stmt->close();
$pageTitle = $m['judul'] ?? 'Materi';
include __DIR__ . '/../inc/header.php';
?>
<div class="container">
    <h1><?php echo sanitize($m['judul'] ?? '-'); ?></h1>
    <div><?php echo sanitize($m['nama_mapel'] ?? '-'); ?> — <?php echo sanitize($m['nama_kelas'] ?? '-'); ?></div>
    <div style="margin-top:12px;"><?php echo nl2br(sanitize($m['konten'] ?? '')); ?></div>
    <?php if (!empty($m['file_id'])): 
        $stmt = $db->prepare("SELECT filename, path FROM files WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $m['file_id']); $stmt->execute(); $f = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($f): ?>
            <div style="margin-top:12px;"><a href="<?php echo BASE_URL . '/' . ltrim(sanitize($f['path']), '/'); ?>" target="_blank">Unduh: <?php echo sanitize($f['filename']); ?></a></div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../inc/footer.php'; ?>
