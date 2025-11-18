<?php
// account/register_admin.php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

$db        = getDB();
$pageTitle = 'Daftar Admin Sekolah';

$error   = '';
$success = '';

// kalau sudah login, nggak usah daftar admin lagi
if (!empty($_SESSION['user_id'])) {
    // kalau punya dashboard admin, bisa redirect ke situ
    header("Location: " . rtrim(BASE_URL, '/\\') . "/index.php");
    exit;
}

// sticky form
$form_nama_sekolah = '';
$form_alamat       = '';
$form_nama_admin   = '';
$form_email        = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrf, 'register_admin')) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $namaSekolah = trim($_POST['nama_sekolah'] ?? '');
        $alamat      = trim($_POST['alamat'] ?? '');
        $namaAdmin   = trim($_POST['nama_admin'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $password    = $_POST['password'] ?? '';
        $confirm     = $_POST['password_confirm'] ?? '';

        // simpan ke sticky form
        $form_nama_sekolah = $namaSekolah;
        $form_alamat       = $alamat;
        $form_nama_admin   = $namaAdmin;
        $form_email        = $email;

        // validasi
        if ($namaSekolah === '' || $namaAdmin === '' || $email === '' || $password === '' || $confirm === '') {
            $error = 'Semua field wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email admin tidak valid.';
        } elseif (strlen($password) < 6) {
            $error = 'Password minimal 6 karakter.';
        } elseif ($password !== $confirm) {
            $error = 'Konfirmasi password tidak cocok.';
        } else {
            // cek email unik
            $cek = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            if (!$cek) {
                $error = 'DB error: ' . $db->error;
            } else {
                $cek->bind_param("s", $email);
                $cek->execute();
                $rcek = $cek->get_result();
                if ($rcek && $rcek->num_rows > 0) {
                    $error = 'Email sudah digunakan oleh akun lain.';
                }
                $cek->close();
            }
        }

        if ($error === '') {
            // mulai transaksi biar aman
            $db->begin_transaction();
            try {
                // 1) buat sekolah baru
                $s = $db->prepare("INSERT INTO schools (nama_sekolah, alamat, created_at) VALUES (?, ?, NOW())");
                if (!$s) {
                    throw new Exception('Gagal menyiapkan query sekolah: ' . $db->error);
                }
                $s->bind_param("ss", $namaSekolah, $alamat);
                if (!$s->execute()) {
                    throw new Exception('Gagal menyimpan data sekolah: ' . $s->error);
                }
                $schoolId = $db->insert_id;
                $s->close();

                // 2) buat user admin
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $role = 'admin';

                $u = $db->prepare("
                    INSERT INTO users (school_id, nama, email, password, role, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                ");
                if (!$u) {
                    throw new Exception('Gagal menyiapkan query admin: ' . $db->error);
                }
                $u->bind_param("issss", $schoolId, $namaAdmin, $email, $hash, $role);
                if (!$u->execute()) {
                    throw new Exception('Gagal menyimpan akun admin: ' . $u->error);
                }
                $u->close();

                $db->commit();

                $success = 'Akun admin dan data sekolah berhasil dibuat. Silakan login menggunakan email dan password yang didaftarkan.';
                // kosongkan form setelah sukses
                $form_nama_sekolah = $form_alamat = $form_nama_admin = $form_email = '';

            } catch (Exception $ex) {
                $db->rollback();
                $error = 'Terjadi kesalahan saat menyimpan data: ' . $ex->getMessage();
            }
        }
    }
}

// token baru
$csrfToken = generateCsrfToken('register_admin');

// header
include __DIR__ . '/../inc/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Daftar Admin Sekolah</h1>
        <p style="font-size:14px;color:#555;">
            Form ini digunakan untuk mendaftarkan <strong>admin utama</strong> sebuah sekolah.
            Satu akun admin akan mewakili satu sekolah (formal maupun non-formal).
        </p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo sanitize($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo sanitize($success); ?></div>
    <?php endif; ?>

    <div class="card" style="padding:16px;max-width:600px;margin:0 auto;">
        <h3 style="margin-top:0;">Form Pendaftaran Admin</h3>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">

            <div class="form-group">
                <label>Nama Sekolah</label>
                <input type="text"
                       name="nama_sekolah"
                       required
                       value="<?php echo sanitize($form_nama_sekolah); ?>"
                       placeholder="Contoh: SD Islam Al-Amanah, Lembaga Kursus Bahasa Arab, dll">
            </div>

            <div class="form-group">
                <label>Alamat Sekolah (opsional)</label>
                <textarea name="alamat" rows="3"
                          placeholder="Alamat lengkap sekolah"><?php echo sanitize($form_alamat); ?></textarea>
            </div>

            <hr>

            <div class="form-group">
                <label>Nama Admin</label>
                <input type="text"
                       name="nama_admin"
                       required
                       value="<?php echo sanitize($form_nama_admin); ?>"
                       placeholder="Nama lengkap admin sekolah">
            </div>

            <div class="form-group">
                <label>Email Admin</label>
                <input type="email"
                       name="email"
                       required
                       value="<?php echo sanitize($form_email); ?>"
                       placeholder="Email yang digunakan untuk login">
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password"
                       name="password"
                       required
                       placeholder="Minimal 6 karakter">
            </div>

            <div class="form-group">
                <label>Ulangi Password</label>
                <input type="password"
                       name="password_confirm"
                       required
                       placeholder="Ketik ulang password">
            </div>

            <button type="submit" class="btn btn-primary">
                Daftar Sebagai Admin Sekolah
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
