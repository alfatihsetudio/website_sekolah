<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
requireLogin();
$id=(int)($_GET['id']??0); if($id<=0) header('Location:'.BASE_URL.'/files/list.php');
$db=getDB(); $stmt=$db->prepare("SELECT * FROM files WHERE id=? LIMIT 1"); $stmt->bind_param("i",$id); $stmt->execute(); $f=$stmt->get_result()->fetch_assoc(); $stmt->close();
$pageTitle='File'; include __DIR__ . '/../inc/header.php';
?>
<div class="container">
    <h1><?php echo sanitize($f['filename'] ?? '-'); ?></h1>
    <div>Diupload: <?php echo sanitize($f['created_at'] ?? '-'); ?></div>
    <div><a href="<?php echo BASE_URL . '/' . ltrim(sanitize($f['path'] ?? ''), '/'); ?>" target="_blank">Unduh</a></div>
</div>
<?php include __DIR__ . '/../inc/footer.php'; ?>
