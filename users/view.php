<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
requireLogin();
$id=(int)($_GET['id']??$_SESSION['user_id']);
$db=getDB(); $stmt=$db->prepare("SELECT id,name,email,role,created_at FROM users WHERE id=? LIMIT 1"); $stmt->bind_param("i",$id); $stmt->execute(); $u=$stmt->get_result()->fetch_assoc(); $stmt->close();
$pageTitle='Profil User'; include __DIR__ . '/../inc/header.php';
?>
<div class="container">
    <h1><?php echo sanitize($u['name']); ?></h1>
    <div>Email: <?php echo sanitize($u['email']); ?></div>
    <div>Role: <?php echo sanitize($u['role']); ?></div>
    <div>Terdaftar: <?php echo sanitize($u['created_at']); ?></div>
</div>
<?php include __DIR__ . '/../inc/footer.php'; ?>
