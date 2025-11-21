<?php
// space_belajar/note_update.php
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

$fileId      = (int)($_POST['file_id'] ?? 0);
$folderId    = (int)($_POST['folder_id'] ?? 0);
$noteTitle   = trim($_POST['note_title'] ?? '');
$noteContent = (string)($_POST['note_content'] ?? '');

if ($fileId <= 0 || $noteTitle === '') {
    header('Location: ' . $baseUrl . '/space_belajar/file_exploler_dashboard.php?folder=' . $folderId);
    exit;
}

$stmt = $db->prepare("
    SELECT id, folder_id, stored_name, mime_type
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
$row     = $res->fetch_assoc();
$stored  = $row['stored_name'];
$mime    = $row['mime_type'];
$folderId = (int)$row['folder_id'];
$stmt->close();

$path = $uploadBaseDir . '/' . $stored;
if ($mime !== 'text/plain' || !is_file($path) || !is_writable($path)) {
    header('Location: ' . $baseUrl . '/space_belajar/file_exploler_dashboard.php?folder=' . $folderId);
    exit;
}

if (file_put_contents($path, $noteContent) === false) {
    header('Location: ' . $baseUrl . '/space_belajar/file_exploler_dashboard.php?folder=' . $folderId);
    exit;
}

$newSize = filesize($path);

$stmtU = $db->prepare("
    UPDATE study_files
    SET title = ?, size_bytes = ?
    WHERE id = ? AND user_id = ?
");
if ($stmtU) {
    $stmtU->bind_param("siii", $noteTitle, $newSize, $fileId, $userId);
    $stmtU->execute();
    $stmtU->close();
}

header('Location: ' . $baseUrl . '/space_belajar/file_exploler_dashboard.php?folder=' . $folderId . '&view=' . $fileId);
exit;
