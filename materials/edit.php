<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
requireRole(['guru']);
$id = (int)($_GET['id'] ?? 0); if ($id<=0) header('Location: ' . BASE_URL . '/materials/list.php');
$db = getDB(); $guruId = (int)$_SESSION['user_id'];
$stmt = $db->prepare("SELECT m.* FROM materials m JOIN subjects s ON m.subject_id = s.id WHERE m.id = ? AND m.created_by = ? LIMIT 1");
$stmt->bind_param("ii", $id, $guruId); $stmt->execute(); $m = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$m) { http_response_code(403); echo "Forbidden"; exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul = trim($_POST['judul'] ?? ''); $konten = trim($_POST['konten'] ?? '');
    if ($judul === '') $error = 'Judul wajib.';
    else {
        $stmt = $db->prepare("UPDATE materials SET judul = ?, konten = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ssi", $judul, $konten, $id);
        if ($stmt->execute()) { header('Location: ' . BASE_URL . '/materials/view.php?id=' . $id); exit; }
        $stmt->close();
    }
}
$pageTitle = 'Edit Materi'; include __DIR__ . '/../inc/header.php';
?>
<div class="container">
    <h1>Edit Materi</h1>
    <?php if ($error): ?><div class="alert alert-error"><?php echo sanitize($error); ?></div><?php endif; ?>
    <form method="POST">
        <div class="form-group"><label>Judul</label><input name="judul" value="<?php echo sanitize($m['judul']); ?>" required></div>
        <div class="form-group"><label>Konten</label><textarea name="konten"><?php echo sanitize($m['konten']); ?></textarea></div>
        <button class="btn btn-primary">Simpan</button>
    </form>
</div>
<?php include __DIR__ . '/../inc/footer.php'; ?>
