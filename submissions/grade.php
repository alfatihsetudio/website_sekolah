<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
requireRole(['guru']);
$id = (int)($_GET['id'] ?? 0); if ($id<=0) header('Location: '.BASE_URL.'/submissions/list.php');
$db = getDB();
$stmt = $db->prepare("SELECT sub.*, u.name AS student, a.created_by FROM submissions sub JOIN users u ON sub.user_id = u.id JOIN assignments a ON sub.assignment_id = a.id WHERE sub.id = ? LIMIT 1");
$stmt->bind_param("i", $id); $stmt->execute(); $sub = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$sub) { http_response_code(404); exit; }
if ($sub['created_by'] != $_SESSION['user_id']) {
    // ensure teacher owns assignment
    http_response_code(403); echo "Forbidden"; exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $score = intval($_POST['score'] ?? 0);
    $feedback = trim($_POST['feedback'] ?? '');
    $stmt = $db->prepare("INSERT INTO grades (submission_id, score, feedback, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iis", $id, $score, $feedback);
    if ($stmt->execute()) {
        // update submission status
        $stmt->close();
        $stmt2 = $db->prepare("UPDATE submissions SET status='graded' WHERE id = ?");
        $stmt2->bind_param("i", $id); $stmt2->execute(); $stmt2->close();
        header('Location: ' . BASE_URL . '/submissions/view.php?id=' . $id);
        exit;
    } else { $error = 'Gagal memberi nilai.'; }
}
$pageTitle='Beri Nilai'; include __DIR__ . '/../inc/header.php';
?>
<div class="container">
    <h1>Beri Nilai untuk <?php echo sanitize($sub['student']); ?></h1>
    <?php if($error):?><div class="alert alert-error"><?php echo sanitize($error);?></div><?php endif;?>
    <form method="POST">
        <div class="form-group"><label>Nilai (angka)</label><input type="number" name="score" required></div>
        <div class="form-group"><label>Feedback</label><textarea name="feedback"></textarea></div>
        <button class="btn btn-primary">Simpan Nilai</button>
    </form>
</div>
<?php include __DIR__ . '/../inc/footer.php'; ?>
