<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
requireRole(['guru']);

$id = (int)($_GET['id'] ?? 0);
$db = getDB();
$guruId = (int)($_SESSION['user_id']);
$error = '';
if ($id <= 0) { header('Location: ' . BASE_URL . '/subjects/list.php'); exit; }

$stmt = $db->prepare("SELECT * FROM subjects WHERE id = ? AND guru_id = ? LIMIT 1");
$stmt->bind_param("ii", $id, $guruId); $stmt->execute(); $s = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$s) { http_response_code(403); echo "Forbidden"; exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_mapel'] ?? '');
    $desc = trim($_POST['deskripsi'] ?? '');
    if ($nama === '') $error = 'Nama mapel wajib diisi.';
    else {
        $stmt = $db->prepare("UPDATE subjects SET nama_mapel = ?, deskripsi = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ssi", $nama, $desc, $id);
        if ($stmt->execute()) {
            header('Location: ' . BASE_URL . '/subjects/view.php?id=' . $id);
            exit;
        } else $error = 'Gagal menyimpan.';
        $stmt->close();
    }
}

$pageTitle = 'Edit Mata Pelajaran';
include __DIR__ . '/../inc/header.php';
?>
<div class="container">
    <h1>Edit Mapel</h1>
    <?php if ($error): ?><div class="alert alert-error"><?php echo sanitize($error); ?></div><?php endif; ?>
    <form method="POST">
        <div class="form-group"><label>Nama</label><input name="nama_mapel" value="<?php echo sanitize($s['nama_mapel']); ?>" required></div>
        <div class="form-group"><label>Deskripsi</label><textarea name="deskripsi"><?php echo sanitize($s['deskripsi']); ?></textarea></div>
        <button class="btn btn-primary">Simpan</button>
    </form>
</div>
<?php include __DIR__ . '/../inc/footer.php'; ?>
