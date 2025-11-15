<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
requireLogin();
$id = (int)($_GET['id'] ?? 0); if ($id<=0) header('Location:' . BASE_URL . '/classes/list.php');
$db = getDB();
$stmt = $db->prepare("SELECT c.*, u.name AS wali FROM classes c LEFT JOIN users u ON c.guru_id = u.id WHERE c.id = ? LIMIT 1");
$stmt->bind_param("i",$id); $stmt->execute(); $c = $stmt->get_result()->fetch_assoc(); $stmt->close();
$pageTitle = $c['nama_kelas'] ?? 'Kelas'; include __DIR__ . '/../inc/header.php';
?>
<div class="container">
    <h1><?php echo sanitize($c['nama_kelas']); ?></h1>
    <div>Wali: <?php echo sanitize($c['wali'] ?? '-'); ?></div>
    <p><?php echo sanitize($c['deskripsi'] ?? ''); ?></p>
    <h3>Anggota</h3>
    <?php
    $stmt = $db->prepare("SELECT u.id, u.name, u.email FROM class_user cu JOIN users u ON cu.user_id = u.id WHERE cu.class_id = ? ORDER BY u.name");
    $stmt->bind_param("i",$id); $stmt->execute(); $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    if (empty($rows)) echo "<p>Tidak ada anggota.</p>"; else {
        echo "<ul>"; foreach ($rows as $r) echo "<li>".sanitize($r['name'])." (".sanitize($r['email']).")</li>"; echo "</ul>";
    }
    ?>
</div>
<?php include __DIR__ . '/../inc/footer.php'; ?>
