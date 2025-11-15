<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
requireLogin();
$id = (int)($_GET['id'] ?? 0); if ($id<=0) header('Location:' . BASE_URL . '/grades/list.php');
$db = getDB();
$stmt = $db->prepare("SELECT g.*, u.name AS student, a.judul FROM grades g JOIN submissions s ON g.submission_id = s.id JOIN users u ON s.user_id = u.id JOIN assignments a ON s.assignment_id = a.id WHERE g.id = ? LIMIT 1");
$stmt->bind_param("i",$id); $stmt->execute(); $g = $stmt->get_result()->fetch_assoc(); $stmt->close();
$pageTitle='Nilai'; include __DIR__ . '/../inc/header.php';
?>
<div class="container">
    <h1>Nilai: <?php echo sanitize($g['score']); ?></h1>
    <div>Tugas: <?php echo sanitize($g['judul']); ?></div>
    <div>Siswa: <?php echo sanitize($g['student']); ?></div>
    <p>Feedback: <?php echo nl2br(sanitize($g['feedback'] ?? '')); ?></p>
</div>
<?php include __DIR__ . '/../inc/footer.php'; ?>
