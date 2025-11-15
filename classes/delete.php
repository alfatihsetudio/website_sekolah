<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
requireRole(['admin','guru']);
$id=(int)($_GET['id']??0); if($id<=0) header('Location:'.BASE_URL.'/classes/list.php');
$db=getDB();
if($_SESSION['role']==='guru'){
    $stmt=$db->prepare("DELETE FROM classes WHERE id=? AND guru_id=?"); $stmt->bind_param("ii",$id,$_SESSION['user_id']);
} else {
    $stmt=$db->prepare("DELETE FROM classes WHERE id=?"); $stmt->bind_param("i",$id);
}
$stmt->execute(); $stmt->close();
header('Location:'.BASE_URL.'/classes/list.php'); exit;
