<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
requireLogin();
$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$assignmentId = (int)($_GET['assignment_id'] ?? 0);
if ($id <= 0 && $assignmentId <= 0) header('Location:' . BASE_URL . '/assignments/list.php');

if ($id > 0) {
    // lihat submission by id
    $stmt = $db->prepare("SELECT sub.*, u.name AS student FROM submissions sub JOIN users u ON sub.user_id = u.id WHERE sub.id = ? LIMIT 1");
    $stmt->bind_param("i", $id); $stmt->execute(); $sub = $stmt->get_result()->fetch_assoc(); $stmt->close();
} else {
    // buat submission form for assignment_id by current user
    $stmt = $db->prepare("SELECT * FROM assignments WHERE id = ? LIMIT 1"); $stmt->bind_param("i", $assignmentId); $stmt->execute(); $as = $stmt->get_result()->fetch_assoc(); $stmt->close();
    $sub = null;
}

// handle POST (submit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $assignmentId > 0) {
    $assignmentId = (int)($_POST['assignment_id']);
    $userId = (int)$_SESSION['user_id'];
    $status = 'submitted';
    $fileId = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $up = uploadFile($_FILES['file'], 'submissions/');
        if ($up['success']) $fileId = $up['file_id'];
    }
    $stmt = $db->prepare("INSERT INTO submissions (assignment_id, user_id, file_id, status, submitted_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("iiis", $assignmentId, $userId, $fileId, $status);
    if ($stmt->execute()) {
        header('Location: ' . BASE_URL . '/assignments/view.php?id=' . $assignmentId);
        exit;
    } else {
        $error = 'Gagal mengirim.';
    }
    $stmt->close();
}

$pageTitle = 'Pengumpulan Tugas';
include __DIR__ . '/../inc/header.php';
?>
<div class="container">
    <?php if ($sub): ?>
        <h1>Pengumpulan <?php echo sanitize($sub['student']); ?></h1>
        <div>Status: <?php echo sanitize($sub['status']); ?></div>
        <div>Dikirim: <?php echo sanitize($sub['submitted_at']); ?></div>
        <?php if (!empty($sub['file_id'])): $stmt = $db->prepare("SELECT filename,path FROM files WHERE id=?"); $stmt->bind_param("i",$sub['file_id']); $stmt->execute(); $f=$stmt->get_result()->fetch_assoc(); $stmt->close(); if($f): ?>
            <div><a href="<?php echo BASE_URL . '/' . ltrim(sanitize($f['path']), '/'); ?>">Unduh lampiran</a></div>
        <?php endif; endif; ?>
        <?php if ($_SESSION['role'] === 'guru'): ?>
            <div><a href="<?php echo BASE_URL; ?>/submissions/grade.php?id=<?php echo (int)$sub['id']; ?>">Beri Nilai</a></div>
        <?php endif; ?>
    <?php else: ?>
        <h1>Kirim Pengumpulan</h1>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="assignment_id" value="<?php echo (int)$assignmentId; ?>">
            <div class="form-group"><label>File (opsional)</label><input type="file" name="file"></div>
            <button class="btn btn-primary">Kirim</button>
        </form>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../inc/footer.php'; ?>
