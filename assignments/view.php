<?php
/**
 * assignments/view.php
 * Menampilkan detail tugas + lampiran + daftar submission (jika guru)
 */

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
requireLogin();
$id = (int)($_GET['id'] ?? 0); if ($id<=0) header('Location:' . BASE_URL . '/assignments/list.php');
$db = getDB();
$stmt = $db->prepare("SELECT a.*, s.nama_mapel, c.nama_kelas FROM assignments a JOIN subjects s ON a.subject_id = s.id JOIN classes c ON a.target_class_id = c.id WHERE a.id = ? LIMIT 1");
$stmt->bind_param("i",$id); $stmt->execute(); $a = $stmt->get_result()->fetch_assoc(); $stmt->close();
$pageTitle = $a['judul'] ?? 'Tugas'; include __DIR__ . '/../inc/header.php';
?>
<div class="container">
    <h1><?php echo sanitize($a['judul']); ?></h1>
    <div><?php echo sanitize($a['nama_mapel']) . ' — ' . sanitize($a['nama_kelas']); ?></div>
    <p><?php echo nl2br(sanitize($a['deskripsi'] ?? '')); ?></p>
    <div>Deadline: <?php echo $a['deadline'] ? sanitize($a['deadline']) : '-'; ?></div>

    <?php if ($_SESSION['role'] === 'siswa'): ?>
        <h3>Pengumpulan Anda</h3>
        <?php
        $stmt = $db->prepare("SELECT * FROM submissions WHERE assignment_id = ? AND user_id = ? LIMIT 1");
        $stmt->bind_param("ii", $id, $_SESSION['user_id']); $stmt->execute(); $sub = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($sub) {
            echo "<div>Status: ".sanitize($sub['status'])." — Dikirim: ".sanitize($sub['submitted_at'])."</div>";
            if (!empty($sub['file_id'])) {
                $stmt = $db->prepare("SELECT filename, path FROM files WHERE id = ? LIMIT 1"); $stmt->bind_param("i",$sub['file_id']); $stmt->execute(); $f=$stmt->get_result()->fetch_assoc(); $stmt->close();
                if ($f) echo "<div><a href=\"".BASE_URL.'/'.ltrim(sanitize($f['path']),'/')."\">Unduh lampiran</a></div>";
            }
        } else {
            echo "<div><a href=\"".BASE_URL."/submissions/view.php?assignment_id=".$id."\">Kirim Tugas</a></div>";
        }
        ?>
    <?php endif; ?>

    <?php if ($_SESSION['role'] === 'guru'): ?>
        <h3>Semua Pengumpulan</h3>
        <?php
        $stmt = $db->prepare("SELECT sub.*, u.name FROM submissions sub JOIN users u ON sub.user_id = u.id WHERE sub.assignment_id = ? ORDER BY sub.submitted_at DESC");
        $stmt->bind_param("i",$id); $stmt->execute(); $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        if (empty($rows)) echo "<p>Belum ada pengumpulan.</p>"; else {
            echo "<ul>";
            foreach ($rows as $r) {
                echo "<li>".sanitize($r['name'])." — ".sanitize($r['status'])." — <a href=\"".BASE_URL."/submissions/view.php?id=". (int)$r['id']."\">Lihat</a></li>";
            }
            echo "</ul>";
        }
        ?>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../inc/footer.php'; ?>
