<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireRole(['admin']);

$db = getDB();
$error = '';
$success = '';

// Jika admin memilih tombol "Siswa", arahkan ke halaman impor siswa
if (isset($_GET['role']) && $_GET['role'] === 'siswa') {
    header('Location: ' . rtrim(BASE_URL, '/\\') . '/users/import_students.php');
    exit;
}

// Jika form disubmit untuk membuat Guru/Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? 'siswa'); // default (tidak digunakan)
    $password = trim($_POST['password'] ?? '');

    if ($name === '' || $email === '' || $password === '' || ($role !== 'guru' && $role !== 'admin')) {
        $error = 'Nama, email, password, dan role (guru/admin) wajib diisi dengan benar.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email tidak valid.';
    } else {
        // cek email unik
        $stmtChk = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmtChk->bind_param("s", $email);
        $stmtChk->execute();
        $rChk = $stmtChk->get_result();
        if ($rChk && $rChk->num_rows > 0) {
            $error = 'Email sudah terdaftar.';
            $stmtChk->close();
        } else {
            $stmtChk->close();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
            if ($stmt) {
                $stmt->bind_param("ssss", $name, $email, $hash, $role);
                if ($stmt->execute()) {
                    $success = 'Pengguna berhasil dibuat. ID: ' . $db->insert_id;
                    // kosongkan field POST agar form bersih
                    $_POST = [];
                } else {
                    $error = 'Gagal menyimpan pengguna: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = 'Gagal menyiapkan query: ' . $db->error;
            }
        }
    }
}

// Render page
$pageTitle = 'Tambah Pengguna';
include __DIR__ . '/../inc/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Tambah Pengguna</h1>
        <a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/users/list.php" class="btn btn-secondary">← Kembali</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo sanitize($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo sanitize($success); ?></div>
    <?php endif; ?>

    <div class="card">
        <h3>Pilih Tipe Pengguna</h3>
        <p class="small text-muted">Untuk efisiensi pembuatan akun siswa gunakan fitur impor CSV.</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;">
            <a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/users/import_students.php" class="btn btn-primary">Tambah Siswa (Impor CSV)</a>
            <a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/users/create.php?role=guru" class="btn btn-secondary">Tambah Guru</a>
            <a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/users/create.php?role=admin" class="btn btn-secondary">Tambah Admin</a>
        </div>

        <?php
        // Jika admin memilih membuat guru/admin, tampilkan form pembuatan single account
        $showRole = $_GET['role'] ?? '';
        if ($showRole === 'guru' || $showRole === 'admin'):
        ?>
            <hr>
            <h3>Buat Akun: <?php echo strtoupper(sanitize($showRole)); ?></h3>
            <form method="POST" action="<?php echo rtrim(BASE_URL, '/\\') . '/users/create.php?role=' . urlencode($showRole); ?>">
                <input type="hidden" name="role" value="<?php echo sanitize($showRole); ?>">
                <div class="form-group">
                    <label>Nama</label>
                    <input type="text" name="name" required value="<?php echo isset($_POST['name']) ? sanitize($_POST['name']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required value="<?php echo isset($_POST['email']) ? sanitize($_POST['email']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="text" name="password" required placeholder="Buat password atau gunakan generator" value="<?php echo isset($_POST['password']) ? sanitize($_POST['password']) : ''; ?>">
                    <small class="small text-muted">Gunakan password yang mudah diingat atau gunakan generator kata sederhana.</small>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Buat Akun <?php echo strtoupper(sanitize($showRole)); ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
