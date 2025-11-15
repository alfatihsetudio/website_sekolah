<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
requireRole(['guru']);
$id = (int)($_GET['id'] ?? 0); if ($id<=0) header('Location:' . BASE_URL . '/assignments/list.php');
$db = getDB();
$stmt = $db->prepare("SELECT * FROM assignments WHERE id = ? AND created_by = ? LIMIT 1");
$stmt->bind_param("ii",$id,$_SESSION['user_id']); $stmt->execute(); $a = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$a) { http_response_code(403); echo "Forbidden"; exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $judul = trim($_POST['judul'] ?? ''); $des = trim($_POST['deskripsi'] ?? '');
    if ($judul==='') $error='Judul wajib.'; else {
        $stmt = $db->prepare("UPDATE assignments SET judul=?, deskripsi=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param("ssi",$judul,$des,$id); if ($stmt->execute()) header('Location: '.BASE_URL.'/assignments/view.php?id='.$id);
        $stmt->close();
    }
}
$pageTitle='Edit Tugas'; include __DIR__ . '/../inc/header.php';
?>
<div class="container">
    <h1>Edit Tugas</h1>
    <?php if ($error): ?><div class="alert alert-error"><?php echo sanitize($error); ?></div><?php endif; ?>
    <form method="POST">
        <div class="form-group"><label>Judul</label><input name="judul" value="<?php echo sanitize($a['judul']); ?>" required></div>
        <div class="form-group"><label>Deskripsi</label><textarea name="deskripsi"><?php echo sanitize($a['deskripsi']); ?></textarea></div>
        <button class="btn btn-primary">Simpan</button>
    </form>
</div>
<?php include __DIR__ . '/../inc/footer.php'; ?>
