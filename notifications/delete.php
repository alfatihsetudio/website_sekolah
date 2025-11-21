<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

requireLogin();

$db      = getDB();
$userId  = (int)($_SESSION['user_id'] ?? 0);
$notifId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($notifId <= 0) {
    // ID tidak valid → langsung balik
    header("Location: list.php");
    exit;
}

// Hapus hanya jika notif milik user yang login
$stmt = $db->prepare("
    DELETE FROM notifications
    WHERE id = ? AND user_id = ?
    LIMIT 1
");

if ($stmt) {
    $stmt->bind_param("ii", $notifId, $userId);
    $stmt->execute();
    $stmt->close();
}

// Setelah hapus, kembali ke daftar notif
header("Location: list.php");
exit;
