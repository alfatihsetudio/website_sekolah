<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/db.php';

requireRole(['admin']);

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/inc/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Admin Dashboard</h1>
    </div>

    <div class="card">
        <p>Selamat datang, <?php echo sanitize($_SESSION['name'] ?? 'Admin'); ?>.</p>
        <ul>
            <li><a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/users/list.php">Kelola Pengguna</a></li>
            <li><a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/classes/list.php">Kelola Kelas</a></li>
            <li><a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/subjects/list.php">Kelola Mata Pelajaran</a></li>
            <li><a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/materials/list.php">Kelola Materi</a></li>
            <li><a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/assignments/list.php">Kelola Tugas</a></li>
        </ul>
    </div>
</div>

<?php include __DIR__ . '/inc/footer.php'; ?>
