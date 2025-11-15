<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
requireRole(['guru']);
$id = (int)($_GET['id'] ?? 0);
$db = getDB();
$guruId = (int)($_SESSION['user_id']);
if ($id <= 0) header('Location: ' . BASE_URL . '/subjects/list.php');
$stmt = $db->prepare("DELETE FROM subjects WHERE id = ? AND guru_id = ?");
$stmt->bind_param("ii", $id, $guruId);
$stmt->execute();
$stmt->close();
header('Location: ' . BASE_URL . '/subjects/list.php');
exit;
