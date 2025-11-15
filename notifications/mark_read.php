<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
requireRole(['guru','siswa','admin']);
$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();
}
header('Location: ' . BASE_URL . '/notifications/list.php');
exit;
