<?php
/**
 * Logout Handler
 */
require_once __DIR__ . '/../inc/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Hapus session
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Jika ada parameter 'next', redirect ke situ (validasi sederhana), else ke halaman home
$next = $_GET['next'] ?? '';
if (!empty($next)) {
    // sederhana: hanya izinkan redirect ke path di dalam aplikasi (menghindari open redirect)
    $decoded = rawurldecode($next);
    if (strpos($decoded, BASE_URL) === 0 || strpos($decoded, '/') === 0) {
        header('Location: ' . $decoded);
        exit;
    }
}

// fallback: arahkan ke home, bukan ke login
header('Location: ' . BASE_URL . '/home.php');
exit;

