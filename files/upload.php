<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/helpers.php';
requireRole(['guru','admin']);
$error=''; if($_SERVER['REQUEST_METHOD']==='POST'){
    if (!isset($_FILES['file'])) $error='Pilih file.';
    else {
        $res = uploadFile($_FILES['file'], 'files/');
        if ($res['success']) header('Location: ' . BASE_URL . '/files/list.php');
        else $error = $res['message'] ?? 'Gagal upload.';
    }
}
$pageTitle='Upload File'; include __DIR__ . '/../inc/header.php';
?>
<div class="container">
    <h1>Upload File</h1>
    <?php if($error):?><div class="alert alert-error"><?php echo sanitize($error);?></div><?php endif;?>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="file" required>
        <button class="btn btn-primary">Upload</button>
    </form>
</div>
<?php include __DIR__ . '/../inc/footer.php'; ?>
