<?php
require_once __DIR__ . '/../inc/auth.php';
requireRole(['guru']);

$db = getDB();
$guruId = $_SESSION['user_id'];
$id = (int)($_GET['id'] ?? 0);

if ($id === 0) {
    header("Location: schedule_list.php");
    exit;
}

// Hanya boleh menghapus jadwal miliknya
$db->query("DELETE FROM teaching_schedule WHERE id = $id AND guru_id = $guruId");

header("Location: /web_MG/teaching/schedule_list.php");
exit;
