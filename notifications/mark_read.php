<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

requireLogin();

$db      = getDB();
$baseUrl = rtrim(BASE_URL, '/\\');
$userId  = (int)($_SESSION['user_id'] ?? 0);

$notifId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($notifId > 0) {
    // Pastikan notifikasi memang milik user yang sedang login
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $notifId, $userId);
        $stmt->execute();
        $stmt->close();
    }
}

// Balik lagi ke list notifikasi
header('Location: ' . $baseUrl . '/notifications/list.php');
exit;
