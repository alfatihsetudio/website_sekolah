<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
requireRole(['admin']);
$id=(int)($_GET['id']??0); if($id<=0) header('Location:'.BASE_URL.'/users/list.php');
$db=getDB(); $stmt=$db->prepare("DELETE FROM users WHERE id=?"); $stmt->bind_param("i",$id); $stmt->execute(); $stmt->close();
header('Location:'.BASE_URL.'/users/list.php'); exit;
