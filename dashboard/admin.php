<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireRole(['admin']);

$db = getDB();

// helper to get single count
function getCount($db, $sql) {
    $res = $db->query($sql);
    if ($res) {
        $row = $res->fetch_row();
        return (int)($row[0] ?? 0);
    }
    return 0;
}

$counts = [];
$counts['users'] = getCount($db, "SELECT COUNT(*) FROM users");
$counts['guru'] = getCount($db, "SELECT COUNT(*) FROM users WHERE role = 'guru'");
$counts['siswa'] = getCount($db, "SELECT COUNT(*) FROM users WHERE role = 'siswa' OR role = 'murid'");
$counts['classes'] = getCount($db, "SELECT COUNT(*) FROM classes");
$counts['subjects'] = getCount($db, "SELECT COUNT(*) FROM subjects");
$counts['materials'] = getCount($db, "SELECT COUNT(*) FROM materials");
$counts['assignments'] = getCount($db, "SELECT COUNT(*) FROM assignments");
$counts['notifications'] = getCount($db, "SELECT COUNT(*) FROM notifications");

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../inc/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Dashboard Admin</h1>
        <div>
            <a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/users/list.php" class="btn btn-secondary">Kelola Pengguna</a>
            <a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/classes/list.php" class="btn btn-secondary">Kelola Kelas</a>
            <a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/subjects/list.php" class="btn btn-secondary">Kelola Mata Pelajaran</a>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit,minmax(220px,1fr)); gap:16px; margin-bottom:18px;">
        <div class="card">
            <h3>Pengguna</h3>
            <p style="font-size:28px; margin:8px 0;"><?php echo sanitize($counts['users']); ?></p>
            <div class="small text-muted">Guru: <?php echo sanitize($counts['guru']); ?> · Siswa: <?php echo sanitize($counts['siswa']); ?></div>
            <div style="margin-top:10px;"><a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/users/list.php">Lihat semua pengguna →</a></div>
        </div>

        <div class="card">
            <h3>Kelas</h3>
            <p style="font-size:28px; margin:8px 0;"><?php echo sanitize($counts['classes']); ?></p>
            <div class="small text-muted">Kelola kelas dan wali murid</div>
            <div style="margin-top:10px;"><a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/classes/list.php">Lihat kelas →</a></div>
        </div>

        <div class="card">
            <h3>Mata Pelajaran</h3>
            <p style="font-size:28px; margin:8px 0;"><?php echo sanitize($counts['subjects']); ?></p>
            <div class="small text-muted">Mata pelajaran terdaftar</div>
            <div style="margin-top:10px;"><a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/subjects/list.php">Lihat mapel →</a></div>
        </div>

        <div class="card">
            <h3>Materi</h3>
            <p style="font-size:28px; margin:8px 0;"><?php echo sanitize($counts['materials']); ?></p>
            <div class="small text-muted">Materi yang diunggah</div>
            <div style="margin-top:10px;"><a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/materials/list.php">Lihat materi →</a></div>
        </div>

        <div class="card">
            <h3>Tugas</h3>
            <p style="font-size:28px; margin:8px 0;"><?php echo sanitize($counts['assignments']); ?></p>
            <div class="small text-muted">Tugas aktif</div>
            <div style="margin-top:10px;"><a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/assignments/list.php">Lihat tugas →</a></div>
        </div>

        <div class="card">
            <h3>Notifikasi</h3>
            <p style="font-size:28px; margin:8px 0;"><?php echo sanitize($counts['notifications']); ?></p>
            <div class="small text-muted">Semua notifikasi</div>
            <div style="margin-top:10px;"><a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/notifications/list.php">Lihat notifikasi →</a></div>
        </div>
    </div>

    <div class="card">
        <h3>Aksi Cepat</h3>
        <ul>
            <li><a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/users/create.php">Buat pengguna baru</a></li>
            <li><a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/classes/create.php">Buat kelas baru</a></li>
            <li><a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/subjects/create.php">Buat mata pelajaran</a></li>
            <li><a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/materials/create.php">Unggah materi</a></li>
            <li><a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/assignments/create.php">Buat tugas</a></li>
        </ul>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
