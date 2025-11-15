<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
requireRole(['guru','admin']);
$id=(int)($_GET['id']??0); if($id<=0) header('Location:'.BASE_URL.'/files/list.php');
$db=getDB();
$stmt=$db->prepare("SELECT uploaded_by, path FROM files WHERE id=? LIMIT 1"); $stmt->bind_param("i",$id); $stmt->execute(); $f=$stmt->get_result()->fetch_assoc(); $stmt->close();
if(!$f) header('Location:'.BASE_URL.'/files/list.php');
if($_SESSION['role']!=='admin' && $f['uploaded_by'] != $_SESSION['user_id']) { http_response_code(403); exit; }
$path = BASE_PATH . '/' . $f['path'];
@unlink($path);
$stmt = $db->prepare("DELETE FROM files WHERE id=?"); $stmt->bind_param("i",$id); $stmt->execute(); $stmt->close();
header('Location:'.BASE_URL.'/files/list.php'); exit;
