<?php
// space_belajar/file_upload.php
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

$parentId = (int)($_POST['parent_id'] ?? 0);

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

if (empty($_FILES['upload_file']) || $_FILES['upload_file']['error'] !== UPLOAD_ERR_OK) {
    header('Location: ' . $baseUrl . '/space_belajar/file_exploler_dashboard.php?folder=' . $parentId);
    exit;
}

$tmp      = $_FILES['upload_file']['tmp_name'];
$origName = $_FILES['upload_file']['name'];
$size     = (int)$_FILES['upload_file']['size'];
$mime     = $_FILES['upload_file']['type'] ?? 'application/octet-stream';

if ($size <= 0) {
    header('Location: ' . $baseUrl . '/space_belajar/file_exploler_dashboard.php?folder=' . $parentId);
    exit;
}

$ext       = pathinfo($origName, PATHINFO_EXTENSION);
$randomKey = bin2hex(random_bytes(4));
$stored    = time() . '_' . $randomKey . ($ext ? '.' . $ext : '');
$destPath  = $uploadBaseDir . '/' . $stored;

if (!@move_uploaded_file($tmp, $destPath)) {
    header('Location: ' . $baseUrl . '/space_belajar/file_exploler_dashboard.php?folder=' . $parentId);
    exit;
}

$title = trim($_POST['file_title'] ?? '');
if ($title === '') {
    $title = $origName;
}
$desc      = trim($_POST['file_desc'] ?? '');
$sizeBytes = $size;

$stmt = $db->prepare("
    INSERT INTO study_files (user_id, folder_id, title, original_name, stored_name, mime_type, size_bytes, description, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
");
if ($stmt) {
    $stmt->bind_param("iissssis", $userId, $parentId, $title, $origName, $stored, $mime, $sizeBytes, $desc);
    $stmt->execute();
    $stmt->close();
}

header('Location: ' . $baseUrl . '/space_belajar/file_exploler_dashboard.php?folder=' . $parentId);
exit;
