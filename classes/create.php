<?php
require_once __DIR__ . '/../inc/auth.php';
requireRole(['guru','admin']);

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

$db = getDB();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil input: hanya jenjang dan jurusan
    $jenjang = trim($_POST['jenjang'] ?? '');
    $jurusan = trim($_POST['jurusan'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');

    // Validasi: kedua field wajib
    if ($jenjang === '' || $jurusan === '') {
        $error = 'Jenjang dan Jurusan wajib diisi. Contoh: Jenjang = 10, Jurusan = RPL.';
    } else {
        // Bentuk nama_kelas otomatis
        $nama_kelas = trim($jenjang . ' ' . $jurusan);

        // Cek keberadaan kolom jenjang/jurusan di tabel classes
        $hasJenjang = false;
        $hasJurusan = false;
        $res = $db->query("SHOW COLUMNS FROM classes LIKE 'jenjang'");
        if ($res && $res->num_rows > 0) $hasJenjang = true;
        $res = $db->query("SHOW COLUMNS FROM classes LIKE 'jurusan'");
        if ($res && $res->num_rows > 0) $hasJurusan = true;

        // Siapkan dan jalankan INSERT sesuai kolom yang tersedia
        if ($hasJenjang && $hasJurusan) {
            $stmt = $db->prepare("INSERT INTO classes (nama_kelas, deskripsi, guru_id, jenjang, jurusan, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            if ($stmt) {
                $stmt->bind_param("ssiss", $nama_kelas, $deskripsi, $_SESSION['user_id'], $jenjang, $jurusan);
                if ($stmt->execute()) {
                    $success = 'Kelas berhasil dibuat.';
                    $stmt->close();
                    header('Location: ' . rtrim(BASE_URL, '/\\') . '/classes/list.php');
                    exit;
                } else {
                    $error = 'Gagal menyimpan kelas: ' . $stmt->error;
                    $stmt->close();
                }
            } else {
                $error = 'Gagal menyiapkan query penyimpanan kelas.';
            }
        } elseif ($hasJenjang && !$hasJurusan) {
            $stmt = $db->prepare("INSERT INTO classes (nama_kelas, deskripsi, guru_id, jenjang, created_at) VALUES (?, ?, ?, ?, NOW())");
            if ($stmt) {
                $stmt->bind_param("sssi", $nama_kelas, $deskripsi, $_SESSION['user_id'], $jenjang);
                if ($stmt->execute()) {
                    $success = 'Kelas berhasil dibuat.';
                    $stmt->close();
                    header('Location: ' . rtrim(BASE_URL, '/\\') . '/classes/list.php');
                    exit;
                } else {
                    $error = 'Gagal menyimpan kelas: ' . $stmt->error;
                    $stmt->close();
                }
            } else {
                $error = 'Gagal menyiapkan query penyimpanan kelas.';
            }
        } elseif (!$hasJenjang && $hasJurusan) {
            $stmt = $db->prepare("INSERT INTO classes (nama_kelas, deskripsi, guru_id, jurusan, created_at) VALUES (?, ?, ?, ?, NOW())");
            if ($stmt) {
                $stmt->bind_param("sssi", $nama_kelas, $deskripsi, $_SESSION['user_id'], $jurusan);
                if ($stmt->execute()) {
                    $success = 'Kelas berhasil dibuat.';
                    $stmt->close();
                    header('Location: ' . rtrim(BASE_URL, '/\\') . '/classes/list.php');
                    exit;
                } else {
                    $error = 'Gagal menyimpan kelas: ' . $stmt->error;
                    $stmt->close();
                }
            } else {
                $error = 'Gagal menyiapkan query penyimpanan kelas.';
            }
        } else {
            // Simpan default: nama_kelas saja
            $stmt = $db->prepare("INSERT INTO classes (nama_kelas, deskripsi, guru_id, created_at) VALUES (?, ?, ?, NOW())");
            if ($stmt) {
                $stmt->bind_param("ssi", $nama_kelas, $deskripsi, $_SESSION['user_id']);
                if ($stmt->execute()) {
                    $success = 'Kelas berhasil dibuat.';
                    $stmt->close();
                    header('Location: ' . rtrim(BASE_URL, '/\\') . '/classes/list.php');
                    exit;
                } else {
                    $error = 'Gagal menyimpan kelas: ' . $stmt->error;
                    $stmt->close();
                }
            } else {
                $error = 'Gagal menyiapkan query penyimpanan kelas.';
            }
        }
    }
}

// Page render
$pageTitle = 'Buat Kelas';
include __DIR__ . '/../inc/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Buat Kelas</h1>
        <a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/classes/list.php" class="btn btn-secondary">← Kembali</a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo sanitize($success); ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="">
            <p class="small text-muted">Isi Jenjang dan Jurusan untuk membentuk Nama Kelas (contoh: Jenjang = 10, Jurusan = RPL → Nama Kelas = "10 RPL").</p>

            <div class="form-group">
                <label for="jenjang">Jenjang (mis. 10, 11, 12)</label>
                <input type="text" id="jenjang" name="jenjang" placeholder="Contoh: 10" value="<?php echo isset($_POST['jenjang']) ? sanitize($_POST['jenjang']) : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="jurusan">Jurusan (mis. RPL, IPA)</label>
                <input type="text" id="jurusan" name="jurusan" placeholder="Contoh: RPL" value="<?php echo isset($_POST['jurusan']) ? sanitize($_POST['jurusan']) : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="deskripsi">Deskripsi (opsional)</label>
                <textarea id="deskripsi" name="deskripsi" rows="3"><?php echo isset($_POST['deskripsi']) ? sanitize($_POST['deskripsi']) : ''; ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Buat Kelas</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
