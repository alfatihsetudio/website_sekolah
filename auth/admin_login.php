<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/helpers.php';

// jika sudah login dan bukan force, redirect
$force = isset($_GET['force']) && $_GET['force'] === '1';
if (isLoggedIn() && !$force) {
    redirectByRole();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Email dan password harus diisi';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid';
    } else {
        if (loginUser($email, $password)) {
            $role = getUserRole();
            if ($role !== 'admin') {
                // bukan admin -> logout dan tampilkan pesan
                logoutUser();
                $error = 'Akun ini bukan admin.';
            } else {
                // jika file admin.php ada di project, redirect ke sana, jika tidak ada fallback ke index.php
                $adminFilePath = rtrim(BASE_PATH, '/\\') . '/admin.php';
                if (file_exists($adminFilePath)) {
                    header('Location: ' . rtrim(BASE_URL, '/\\') . '/admin.php');
                } else {
                    header('Location: ' . rtrim(BASE_URL, '/\\') . '/index.php');
                }
                exit;
            }
        } else {
            $error = 'Email atau password salah';
        }
    }
}

// tampilkan form minimal (tanpa header)
$pageTitle = 'Login Admin';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo sanitize($pageTitle) . ' - ' . sanitize(APP_NAME); ?></title>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/style.css">
</head>
<body>
<div class="auth-container">
  <div class="auth-card">
    <h1 class="auth-title">🔑 Login Admin</h1>
    <p class="auth-subtitle">Masuk sebagai Administrator</p>

    <?php if (!empty($error)): ?><div class="alert alert-error"><?php echo sanitize($error); ?></div><?php endif; ?>

    <form method="POST" class="auth-form">
      <div class="form-group">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" required value="<?php echo isset($_POST['email']) ? sanitize($_POST['email']) : ''; ?>">
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>
      </div>
      <button class="btn btn-primary btn-block" type="submit">Masuk sebagai Admin</button>
    </form>

    <div class="auth-footer">
      <p><a href="<?php echo BASE_URL; ?>/home.php">← Kembali ke Home</a></p>
    </div>
  </div>
</div>
</body>
</html>
