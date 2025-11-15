<?php
// Hapus file ini setelah digunakan. Gunakan hanya di lingkungan development lokal.
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

$db = getDB();
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || $pass === '') {
        $msg = 'Semua field wajib diisi.';
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, 'admin', NOW())");
        if ($stmt) {
            $stmt->bind_param("sss", $name, $email, $hash);
            if ($stmt->execute()) {
                $msg = 'Admin berhasil dibuat. Hapus file tools/create_admin.php sekarang.';
            } else {
                $msg = 'Gagal membuat admin: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $msg = 'Gagal menyiapkan statement: ' . $db->error;
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Buat Admin</title><link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/style.css"></head>
<body>
<div class="container" style="max-width:540px;margin:40px auto;">
  <div class="card">
    <h2>Buat Akun Admin (Sementara)</h2>
    <?php if ($msg): ?><div class="alert alert-error"><?php echo sanitize($msg); ?></div><?php endif; ?>
    <form method="POST">
      <div class="form-group"><label>Nama</label><input name="name" required></div>
      <div class="form-group"><label>Email</label><input name="email" type="email" required></div>
      <div class="form-group"><label>Password</label><input name="password" type="password" required></div>
      <button class="btn btn-primary">Buat Admin</button>
    </form>
    <p class="small" style="margin-top:12px;color:#666;">Hapus file ini setelah admin dibuat.</p>
  </div>
</div>
</body>
</html>
