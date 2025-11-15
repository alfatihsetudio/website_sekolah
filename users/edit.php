<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
requireLogin();
$id=(int)($_GET['id']??$_SESSION['user_id']);
$db=getDB();
if($_SESSION['role']!=='admin' && $id!==$_SESSION['user_id']) { http_response_code(403); exit; }
$stmt=$db->prepare("SELECT id,name,email,role FROM users WHERE id=? LIMIT 1"); $stmt->bind_param("i",$id); $stmt->execute(); $u=$stmt->get_result()->fetch_assoc(); $stmt->close();
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $name=trim($_POST['name']??''); $email=trim($_POST['email']??''); $role=$_SESSION['role']==='admin'?trim($_POST['role']??$u['role']):$u['role'];
    if($name==='') $error='Nama wajib.'; else {
        $stmt=$db->prepare("UPDATE users SET name=?, email=?, role=? WHERE id=?"); $stmt->bind_param("sssi",$name,$email,$role,$id); $stmt->execute(); $stmt->close();
        header('Location: '.BASE_URL.'/users/view.php?id='.$id); exit;
    }
}
$pageTitle='Edit Pengguna'; include __DIR__ . '/../inc/header.php';
?>
<div class="container">
    <?php if($error):?><div class="alert alert-error"><?php echo sanitize($error);?></div><?php endif;?>
    <form method="POST">
        <div class="form-group"><label>Nama</label><input name="name" value="<?php echo sanitize($u['name']); ?>" required></div>
        <div class="form-group"><label>Email</label><input name="email" value="<?php echo sanitize($u['email']); ?>" required></div>
        <?php if($_SESSION['role']==='admin'): ?>
            <div class="form-group"><label>Role</label><select name="role"><option value="siswa" <?php echo $u['role']=='siswa'?'selected':'';?>>Siswa</option><option value="guru" <?php echo $u['role']=='guru'?'selected':'';?>>Guru</option><option value="admin" <?php echo $u['role']=='admin'?'selected':'';?>>Admin</option></select></div>
        <?php endif; ?>
        <button class="btn btn-primary">Simpan</button>
    </form>
</div>
<?php include __DIR__ . '/../inc/footer.php'; ?>
