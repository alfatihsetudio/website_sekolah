<?php
// space_belajar/file_delete.php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireLogin();

$db      = getDB();
$baseUrl = rtrim(BASE_URL, '/\\');
$userId  = (int)($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $userId <= 0) {
    header('Location: ' . $baseUrl . '/space_belajar/file_exploler_dashboard.php');
    exit;
}

$uploadBaseDir = __DIR__ . '/../uploads/study_files';

$fileId   = (int)($_POST['file_id'] ?? 0);
$folderId = (int)($_POST['folder_id'] ?? 0);

if ($fileId <= 0) {
    header('Location: ' . $baseUrl . '/space_belajar/file_exploler_dashboard.php?folder=' . $folderId);
    exit;
}

$stmt = $db->prepare("
    SELECT id, folder_id, stored_name
    FROM study_files
    WHERE id = ? AND user_id = ?
    LIMIT 1
");
if (!$stmt) {
    header('Location: ' . $baseUrl . '/space_belajar/file_exploler_dashboard.php?folder=' . $folderId);
    exit;
}
$stmt->bind_param("ii", $fileId, $userId);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || !$res->num_rows) {
    $stmt->close();
    header('Location: ' . $baseUrl . '/space_belajar/file_exploler_dashboard.php?folder=' . $folderId);
    exit;
}
$row      = $res->fetch_assoc();
$folderId = (int)$row['folder_id'];
$stored   = $row['stored_name'];
$stmt->close();

$stmtD = $db->prepare("DELETE FROM study_files WHERE id = ? AND user_id = ? LIMIT 1");
if ($stmtD) {
    $stmtD->bind_param("ii", $fileId, $userId);
    $stmtD->execute();
    $stmtD->close();
}

$fs = $uploadBaseDir . '/' . $stored;
if (is_file($fs)) {
    @unlink($fs);
}

header('Location: ' . $baseUrl . '/space_belajar/file_exploler_dashboard.php?folder=' . $folderId);
exit;
