<?php
/**
 * Hapus Materi
 */
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
requireRole(['guru']);

$id = (int)($_GET['id'] ?? 0); 
if ($id <= 0) {
    header('Location: ' . BASE_URL . '/materials/list.php');
    exit;
}

$db = getDB(); 
$stmt = $db->prepare("DELETE FROM materials WHERE id = ? AND created_by = ?"); 
$stmt->bind_param("ii", $id, $_SESSION['user_id']); 
$stmt->execute(); 
$stmt->close();

header('Location: ' . BASE_URL . '/materials/list.php'); 
exit;

