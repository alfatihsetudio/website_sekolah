<?php
/**
 * Header Template (SAFE)
 * Pastikan file ini tidak memanggil fungsi yang didefinisikan di helpers.php
 * sebelum helpers.php di-include oleh halaman yang memanggil header.
 *
 * File ini dibuat supaya aman jika helpers sudah ter-include; namun tetap
 * kita tetap merekomendasikan setiap halaman 'require_once inc/helpers.php'
 * sebelum include header.
 */

// Jika helper belum tersedia, coba include sekali secara defensif.
// Jangan memaksa include helpers.php di header kalau file header di-include
// dari lokasi yang tidak standar — namun usaha defensif ini membantu.
$helpersAttempted = false;
if (!function_exists('sanitize')) {
    $helpersPath = __DIR__ . '/helpers.php';
    if (file_exists($helpersPath)) {
        require_once $helpersPath;
        $helpersAttempted = true;
    }
}

// Pastikan APP_NAME ada
if (!defined('APP_NAME')) {
    $cfgPath = __DIR__ . '/config.php';
    if (file_exists($cfgPath)) {
        require_once $cfgPath;
    } else {
        define('APP_NAME', 'Web Pembelajaran');
    }
}

// Atur page title default jika belum ditentukan
if (!isset($pageTitle)) {
    $pageTitle = defined('APP_NAME') ? APP_NAME : 'Web Pembelajaran';
}

// Coba dapatkan current user dengan aman
$currentUser = null;
$unreadCount = 0;
if (function_exists('isLoggedIn') && isLoggedIn()) {
    // Jika helpers belum di-load tapi isLoggedIn tersedia via auth, gunakan
    try {
        if (function_exists('getCurrentUser')) {
            $currentUser = getCurrentUser();
        }
        if (function_exists('getUnreadNotificationsCount') && isset($_SESSION['user_id'])) {
            $unreadCount = getUnreadNotificationsCount($_SESSION['user_id']);
        }
    } catch (Throwable $e) {
        // jangan lempar error di header
        $currentUser = null;
        $unreadCount = 0;
    }
}
?>
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo isset($pageTitle) ? sanitize($pageTitle) . ' - ' . APP_NAME : APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/style.css">
</head>
<body>
<header style="padding:12px; border-bottom:1px solid #eee;">
    <div style="max-width:1000px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;">
        <div><a class="brand" href="<?php echo BASE_URL; ?>/index.php"><?php echo APP_NAME; ?></a></div>
        <nav>
            <?php $role = $_SESSION['role'] ?? ''; ?>
            <?php if ($role === 'admin'): ?>
                <a href="<?php echo BASE_URL; ?>/dashboard/admin.php">Dashboard Admin</a> |
                <a href="<?php echo BASE_URL; ?>/users/list.php">Pengguna</a> |
                <a href="<?php echo BASE_URL; ?>/classes/list.php">Kelas</a> |
                <a href="<?php echo BASE_URL; ?>/subjects/list.php">Mata Pelajaran</a> |
                <a href="<?php echo BASE_URL; ?>/materials/list.php">Materi</a>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>/materials/list.php">Materi</a> |
                <a href="<?php echo BASE_URL; ?>/assignments/list.php">Tugas</a> |
                <a href="<?php echo BASE_URL; ?>/subjects/list.php">Mapel</a> |
                <a href="<?php echo BASE_URL; ?>/classes/list.php">Kelas</a> |
                <a href="<?php echo BASE_URL; ?>/notifications/list.php">Notifikasi</a>
            <?php endif; ?>

            <?php if (!empty($_SESSION['user_id'])): ?>
                &nbsp;|&nbsp; <strong><?php echo sanitize($_SESSION['name'] ?? ''); ?></strong>
                &nbsp; <a href="<?php echo BASE_URL; ?>/auth/logout.php">(keluar)</a>
            <?php else: ?>
                &nbsp;|&nbsp; <a href="<?php echo BASE_URL; ?>/auth/login.php">Masuk</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main style="max-width:1000px;margin:20px auto;">
