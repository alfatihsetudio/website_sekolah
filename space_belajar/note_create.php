<?php
// space_belajar/note_create.php
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
if (!is_dir($uploadBaseDir)) {
    @mkdir($uploadBaseDir, 0777, true);
}

$parentId    = (int)($_POST['parent_id'] ?? 0);
$noteTitle   = trim($_POST['note_title'] ?? '');
$noteContent = (string)($_POST['note_content'] ?? '');

if ($noteTitle === '' || $noteContent === '') {
    header('Location: ' . $baseUrl . '/space_belajar/file_exploler_dashboard.php?folder=' . $parentId);
    exit;
}

// validasi folder
if ($parentId > 0) {
    $stmt = $db->prepare("SELECT id FROM study_folders WHERE id = ? AND user_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("ii", $parentId, $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res || !$res->num_rows) {
            $parentId = 0;
        }
        $stmt->close();
    } else {
        $parentId = 0;
    }
}

$randomKey = bin2hex(random_bytes(4));
$stored    = 'note_' . $userId . '_' . time() . '_' . $randomKey . '.txt';
$destPath  = $uploadBaseDir . '/' . $stored;

if (file_put_contents($destPath, $noteContent) === false) {
    header('Location: ' . $baseUrl . '/space_belajar/file_exploler_dashboard.php?folder=' . $parentId);
    exit;
}

$origName  = $stored;
$mime      = 'text/plain';
$sizeBytes = filesize($destPath);

$stmt = $db->prepare("
    INSERT INTO study_files (user_id, folder_id, title, original_name, stored_name, mime_type, size_bytes, description, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, '', NOW())
");
if ($stmt) {
    $stmt->bind_param("iissssi", $userId, $parentId, $noteTitle, $origName, $stored, $mime, $sizeBytes);
    $stmt->execute();
    $stmt->close();
}

header('Location: ' . $baseUrl . '/space_belajar/file_exploler_dashboard.php?folder=' . $parentId);
exit;
