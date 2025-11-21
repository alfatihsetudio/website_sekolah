<?php
// space_belajar/folder_create.php
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

$folderName = trim($_POST['folder_name'] ?? '');
$parentId   = (int)($_POST['parent_id'] ?? 0);

if ($folderName === '') {
    header('Location: ' . $baseUrl . '/space_belajar/file_exploler_dashboard.php?folder=' . $parentId);
    exit;
}

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

$stmt = $db->prepare("
    INSERT INTO study_folders (user_id, parent_id, name, created_at, updated_at)
    VALUES (?, ?, ?, NOW(), NOW())
");
if ($stmt) {
    $stmt->bind_param("iis", $userId, $parentId, $folderName);
    $stmt->execute();
    $stmt->close();
}

header('Location: ' . $baseUrl . '/space_belajar/file_exploler_dashboard.php?folder=' . $parentId);
exit;
