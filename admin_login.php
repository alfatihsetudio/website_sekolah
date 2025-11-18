<?php
/**
 * Halaman Login Admin (Khusus Admin)
 * Hanya bisa diakses via URL langsung
 */
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/helpers.php';

$baseUrl = rtrim(BASE_URL, '/\\');

// Jika sudah login sebagai admin, langsung ke dashboard admin
if (isLoggedIn() && isAdmin()) {
    header('Location: ' . $baseUrl . '/dashboard/admin.php');
    exit;
}

// Jika sudah login tapi bukan admin, redirect ke dashboard sesuai role
if (isLoggedIn() && !isAdmin()) {
    redirectByRole();
    exit;
}

$error = '';

// Proses login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validasi input
    if ($email === '' || $password === '') {
        $error = 'Email dan password harus diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } else {
        // Coba login memakai helper lama
        if (loginUser($email, $password)) {
            // Pastikan benar-benar admin
            if (isAdmin()) {
                // PASTIKAN school_id MASUK SESSION
                $db = getDB();
                $stmt = $db->prepare("SELECT id, nama, email, role, school_id FROM users WHERE email = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $user = $res ? $res->fetch_assoc() : null;
                    $stmt->close();

                    if ($user) {
                        // overwrite / lengkapi session
                        $_SESSION['user_id']   = (int)$user['id'];
                        $_SESSION['role']      = $user['role'];
                        $_SESSION['school_id'] = (int)$user['school_id'];
                        $_SESSION['nama']      = $user['nama'];
                        $_SESSION['email']     = $user['email'];
                    }
                }

                header('Location: ' . $baseUrl . '/dashboard/admin.php');
                exit;
            } else {
                // sudah login tapi bukan admin → paksa logout
                $error = 'Anda bukan admin. Gunakan halaman login untuk Guru/Murid.';
                logoutUser();
            }
        } else {
            $error = 'Email atau password salah.';
        }
    }
}

$pageTitle = 'Login Admin';
include __DIR__ . '/inc/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <h1 class="auth-title">🔐 Login Admin</h1>
        <p class="auth-subtitle">Halaman khusus untuk Administrator Sekolah</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo sanitize($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="" class="auth-form">
            <div class="form-group">
                <label for="email">Email Admin</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    required
                    autocomplete="email"
                    placeholder="admin@example.com"
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

            <button type="submit" class="btn btn-primary btn-block">Masuk sebagai Admin</button>
        </form>

        <div class="auth-footer">
            <p>
                <a href="<?php echo $baseUrl; ?>/home.php" style="color: var(--primary);">
                    ← Kembali ke Home
                </a>
            </p>
            <p style="margin-top: 10px; font-size: 0.9rem; color: var(--gray-500);">
                Bukan admin?
                <a href="<?php echo $baseUrl; ?>/auth/login.php" style="color: var(--primary);">
                    Login sebagai Guru/Murid
                </a>
            </p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/inc/footer.php'; ?>
