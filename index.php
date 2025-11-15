<?php
/**
 * Homepage - Redirect berdasarkan login status
 */
// Enable error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once __DIR__ . '/inc/config.php';
    require_once __DIR__ . '/inc/db.php';
    require_once __DIR__ . '/inc/auth.php';
    
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!empty($_SESSION['user_id'])) {
        if ($_SESSION['role'] === 'guru') header('Location: ' . BASE_URL . '/guru.php');
        elseif ($_SESSION['role'] === 'siswa') header('Location: ' . BASE_URL . '/murid.php');
        else header('Location: ' . BASE_URL . '/subjects/list.php');
        exit;
    } else {
        // Jika belum login, redirect ke halaman home
        header('Location: /web_MG/home.php');
        exit;
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "<br>File: " . $e->getFile() . "<br>Line: " . $e->getLine());
} catch (Error $e) {
    die("Fatal Error: " . $e->getMessage() . "<br>File: " . $e->getFile() . "<br>Line: " . $e->getLine());
}
?>
<div class="container">
    <h1>Selamat datang di <?php echo APP_NAME; ?></h1>
    <p><a href="<?php echo BASE_URL; ?>/auth/login.php">Login</a> untuk mulai.</p>
</div>
