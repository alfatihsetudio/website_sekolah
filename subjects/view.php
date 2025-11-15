<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: ' . BASE_URL . '/subjects/list.php'); exit; }

$db = getDB();
$stmt = $db->prepare("SELECT s.*, c.nama_kelas, u.name AS guru FROM subjects s LEFT JOIN classes c ON s.class_id = c.id LEFT JOIN users u ON s.guru_id = u.id WHERE s.id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$subject = $stmt->get_result()->fetch_assoc();
$stmt->close();

$pageTitle = 'Detail Mata Pelajaran';
include __DIR__ . '/../inc/header.php';
?>
<div class="container">
    <h1><?php echo sanitize($subject['nama_mapel'] ?? '-'); ?></h1>
    <div>Kelas: <?php echo sanitize($subject['nama_kelas'] ?? '-'); ?></div>
    <div>Guru: <?php echo sanitize($subject['guru'] ?? '-'); ?></div>
    <p><?php echo sanitize($subject['deskripsi'] ?? ''); ?></p>

    <h3>Materi</h3>
    <?php
    $stmt = $db->prepare("SELECT id, judul, created_at FROM materials WHERE subject_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $id); $stmt->execute(); $mat = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    if (empty($mat)) echo "<p>Tidak ada materi.</p>"; else {
        echo "<ul>";
        foreach ($mat as $m) {
            echo "<li><a href=\"".BASE_URL."/materials/view.php?id=".(int)$m['id']."\">".sanitize($m['judul'])."</a> <small>".sanitize($m['created_at'])."</small></li>";
        }
        echo "</ul>";
    }
    ?>

    <h3>Tugas</h3>
    <?php
    $stmt = $db->prepare("SELECT a.id, a.judul, a.deadline FROM assignments a WHERE a.subject_id = ? ORDER BY a.created_at DESC");
    $stmt->bind_param("i", $id); $stmt->execute(); $ass = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    if (empty($ass)) echo "<p>Tidak ada tugas.</p>"; else {
        echo "<ul>";
        foreach ($ass as $a) {
            echo "<li><a href=\"".BASE_URL."/assignments/view.php?id=".(int)$a['id']."\">".sanitize($a['judul'])."</a> <small>".($a['deadline'] ? sanitize($a['deadline']) : '-')."</small></li>";
        }
        echo "</ul>";
    }
    ?>
</div>
<?php include __DIR__ . '/../inc/footer.php'; ?>
