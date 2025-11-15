<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
requireRole(['guru']);
$id = (int)($_GET['id'] ?? 0); if ($id<=0) header('Location:' . BASE_URL . '/assignments/list.php');
$db = getDB(); $stmt = $db->prepare("DELETE FROM assignments WHERE id = ? AND created_by = ?"); $stmt->bind_param("ii",$id,$_SESSION['user_id']); $stmt->execute(); $stmt->close();
header('Location:' . BASE_URL . '/assignments/list.php'); exit;
