<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

$pageTitle = 'Daftar File';
include __DIR__ . '/../inc/header.php';

$db = getDB();
$userId = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? 'siswa';
$files = [];

if ($role === 'admin') {
    $stmt = $db->prepare("SELECT f.id, f.filename, f.path, f.uploaded_by, u.name AS uploader, f.created_at FROM files f LEFT JOIN users u ON f.uploaded_by = u.id ORDER BY f.created_at DESC");
    $stmt->execute();
    $files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} elseif ($role === 'guru') {
    $stmt = $db->prepare("SELECT f.id, f.filename, f.path, f.created_at FROM files f WHERE f.uploaded_by = ? ORDER BY f.created_at DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    // siswa: files linked to classes they are in (simple join via assignment_files/materials/files mapping if exists)
    $stmt = $db->prepare("SELECT DISTINCT f.id, f.filename, f.path, f.created_at FROM files f JOIN assignment_files af ON af.file_id = f.id JOIN assignments a ON af.assignment_id = a.id JOIN class_user cu ON cu.class_id = a.target_class_id WHERE cu.user_id = ? ORDER BY f.created_at DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<div class="container">
    <div class="page-header">
        <h1>Daftar File</h1>
        <?php if ($role === 'admin' || $role === 'guru'): ?>
            <a href="/web_MG/files/upload.php" class="btn btn-primary">+ Upload File</a>
        <?php endif; ?>
    </div>

    <?php if (empty($files)): ?>
        <div class="card"><p>Tidak ada file.</p></div>
    <?php else: ?>
        <div class="card">
            <ul class="list-group">
                <?php foreach ($files as $f): ?>
                    <li class="list-group-item">
                        <strong><?php echo sanitize($f['filename']); ?></strong>
                        <div style="font-size:12px; color:#666;">
                            <?php echo sanitize($f['created_at'] ?? ''); ?>
                        </div>
                        <div style="margin-top:6px;">
                            <a href="/web_MG/<?php echo ltrim(sanitize($f['path']), '/'); ?>" target="_blank">Unduh</a>
                            <?php if ($role === 'admin' || ($role === 'guru' && isset($f['uploaded_by']) && $f['uploaded_by'] == $userId)): ?>
                                &middot; <a href="/web_MG/files/delete.php?id=<?php echo (int)$f['id']; ?>" onclick="return confirm('Hapus file?')">Hapus</a>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
