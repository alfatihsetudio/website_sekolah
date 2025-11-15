<?php
require_once __DIR__ . '/inc/auth.php';
requireRole(['guru']);
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/helpers.php';
$db = getDB(); $guruId = (int)$_SESSION['user_id'];
$pageTitle = 'Dashboard Guru';
include __DIR__ . '/inc/header.php';
// ambil ringkasan: jumlah mapel, materi terbaru, tugas terbaru
$stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM subjects WHERE guru_id = ?"); $stmt->bind_param("i",$guruId); $stmt->execute(); $c = $stmt->get_result()->fetch_assoc(); $stmt->close();
$subjCount = $c['cnt'] ?? 0;
$stmt = $db->prepare("SELECT id, judul, created_at FROM materials WHERE created_by = ? ORDER BY created_at DESC LIMIT 6"); $stmt->bind_param("i",$guruId); $stmt->execute(); $mats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
$stmt = $db->prepare("SELECT id, judul, created_at FROM assignments WHERE created_by = ? ORDER BY created_at DESC LIMIT 6"); $stmt->bind_param("i",$guruId); $stmt->execute(); $ass = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
?>
<div class="container">
    <h1>Halo, <?php echo sanitize($_SESSION['name'] ?? ''); ?> — Dashboard Guru</h1>
    <div class="card"><strong>Jumlah Mapel:</strong> <?php echo (int)$subjCount; ?></div>
    <section class="card"><h3>Materi Terbaru</h3><?php if(empty($mats)) echo '<p>Tidak ada.</p>'; else { echo '<ul>'; foreach($mats as $m) echo '<li><a href="'.BASE_URL.'/materials/view.php?id='.(int)$m['id'].'">'.sanitize($m['judul']).'</a></li>'; echo '</ul>'; } ?></section>
    <section class="card"><h3>Tugas Terbaru</h3><?php if(empty($ass)) echo '<p>Tidak ada.</p>'; else { echo '<ul>'; foreach($ass as $a) echo '<li><a href="'.BASE_URL.'/assignments/view.php?id='.(int)$a['id'].'">'.sanitize($a['judul']).'</a></li>'; echo '</ul>'; } ?></section>
</div>
<?php include __DIR__ . '/inc/footer.php'; ?>
