<?php
/**
 * Halaman Login (Guru & Murid)
 * Method: GET (tampilkan form) / POST (proses login)
 */
// Pastikan semua file yang diperlukan sudah di-include
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/helpers.php';

// Jika sudah login, redirect ke dashboard (kecuali dipaksa via ?force=1)
$force = isset($_GET['force']) && $_GET['force'] === '1';
if (isLoggedIn() && !$force) {
    // Jika admin mencoba login di sini, redirect ke admin login
    if (isAdmin()) {
        header('Location: /web_MG/admin_login.php');
        exit;
    }
    redirectByRole();
}

// Get role dari URL parameter
$roleFilter = $_GET['role'] ?? '';
$allowedRoles = ['guru', 'siswa']; // gunakan 'siswa' bukan 'murid'

$error = '';

// helper lokal: terima alias 'siswa' <-> 'murid'
function roleMatches($userRole, $requestedRole) {
    if (empty($requestedRole)) return true;
    if ($userRole === $requestedRole) return true;
    $aliases = [
        'siswa' => 'murid',
        'murid' => 'siswa'
    ];
    return (isset($aliases[$requestedRole]) && $aliases[$requestedRole] === $userRole)
        || (isset($aliases[$userRole]) && $aliases[$userRole] === $requestedRole);
}

// Proses login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $requestedRole = $_POST['role'] ?? '';
    
    // Validasi
    if (empty($email) || empty($password)) {
        $error = 'Email dan password harus diisi';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid';
    } else {
        // Coba login
        if (loginUser($email, $password)) {
            $userRole = getUserRole();
            
            // Cek jika user adalah admin, redirect ke admin login
            if ($userRole === 'admin') {
                logoutUser();
                $error = 'Admin harus login melalui halaman admin. <a href="/web_MG/admin_login.php">Klik di sini</a>';
            } 
            // Cek jika role sesuai dengan yang diminta (terima alias 'murid'/'siswa')
            elseif (!roleMatches($userRole, $requestedRole)) {
                logoutUser();
                $error = "Akun ini bukan untuk role '{$requestedRole}'. Silakan login dengan akun yang sesuai.";
            } else {
                redirectByRole();
            }
        } else {
            $error = 'Email atau password salah';
        }
    }
}

// Set role filter dari URL atau POST (terima alias 'murid' sebagai 'siswa' untuk tampilan)
if (empty($roleFilter) && isset($_POST['role'])) {
    $roleFilter = $_POST['role'];
} elseif (empty($roleFilter) && isset($_GET['role'])) {
    $roleFilter = $_GET['role'];
}

$pageTitle = $roleFilter === 'guru' ? 'Login Guru' : ($roleFilter === 'siswa' ? 'Login Murid' : 'Login');
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
            <h1 class="auth-title">
                <?php if ($roleFilter === 'guru'): ?>
                    👨‍🏫 Login Guru
                <?php elseif ($roleFilter === 'siswa'): ?>
                    🎓 Login Murid
                <?php else: ?>
                    <?php echo APP_NAME; ?>
                <?php endif; ?>
            </h1>
            <p class="auth-subtitle">
                <?php if ($roleFilter === 'guru'): ?>
                    Masuk sebagai Guru
                <?php elseif ($roleFilter === 'siswa'): ?>
                    Masuk sebagai Murid
                <?php else: ?>
                    Masuk ke akun Anda
                <?php endif; ?>
            </p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo sanitize($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="" class="auth-form">
                <input type="hidden" name="role" value="<?php echo sanitize($roleFilter); ?>">

                <div class="form-group">
                    <label for="email">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        required
                        autocomplete="email"
                        placeholder="nama@email.com"
                        value="<?php echo isset($_POST['email']) ? sanitize($_POST['email']) : ''; ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="Masukkan password"
                    >
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <?php if ($roleFilter === 'guru'): ?>
                        Masuk sebagai Guru
                    <?php elseif ($roleFilter === 'siswa'): ?>
                        Masuk sebagai Murid
                    <?php else: ?>
                        Masuk
                    <?php endif; ?>
                </button>
            </form>

            <div class="auth-footer">
                <p><a href="<?php echo BASE_URL; ?>/home.php" style="color: var(--primary);">← Kembali ke Home</a></p>
                <?php if ($roleFilter === 'guru'): ?>
                    <p style="margin-top: 10px; font-size: 0.9rem; color: var(--muted);">
                        Bukan guru? <a href="<?php echo BASE_URL; ?>/auth/login.php?role=siswa" style="color: var(--primary);">Login sebagai Murid</a>
                    </p>
                <?php elseif ($roleFilter === 'siswa'): ?>
                    <p style="margin-top: 10px; font-size: 0.9rem; color: var(--muted);">
                        Bukan murid? <a href="<?php echo BASE_URL; ?>/auth/login.php?role=guru" style="color: var(--primary);">Login sebagai Guru</a>
                    </p>
                <?php else: ?>
                    <p style="margin-top: 10px; font-size: 0.9rem; color: var(--muted);">
                        Belum punya akun? Hubungi administrator
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

