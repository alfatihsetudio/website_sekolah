<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

$pageTitle = 'Daftar Kelas';
include __DIR__ . '/../inc/header.php';

$db = getDB();
$userId = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? 'siswa';
$classes = [];

// Ambil data kelas — gunakan kolom baru jika ada (walimurid, no_telpon_wali, nama_km, no_telpon_km)
if ($role === 'admin') {
    $stmt = $db->prepare("SELECT c.id, c.nama_kelas, c.walimurid, c.no_telpon_wali, c.nama_km, c.no_telpon_km, c.guru_id FROM classes c ORDER BY c.nama_kelas");
    $stmt->execute();
    $res = $stmt->get_result();
    $classes = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
} elseif ($role === 'guru') {
    $stmt = $db->prepare("SELECT id, nama_kelas, walimurid, no_telpon_wali, nama_km, no_telpon_km, guru_id FROM classes WHERE guru_id = ? ORDER BY nama_kelas");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $classes = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
} else {
    // siswa: kelas yang diikuti
    $stmt = $db->prepare("SELECT c.id, c.nama_kelas, c.walimurid, c.no_telpon_wali, c.nama_km, c.no_telpon_km, c.guru_id FROM classes c JOIN class_user cu ON cu.class_id = c.id WHERE cu.user_id = ? ORDER BY c.nama_kelas");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $classes = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}
?>

<div class="container">
    <div class="page-header">
        <h1>Daftar Kelas</h1>
        <?php if ($role === 'admin' || $role === 'guru'): ?>
            <a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/classes/create.php" class="btn btn-primary">+ Tambah Kelas</a>
        <?php endif; ?>
    </div>

    <?php if (empty($classes)): ?>
        <div class="card"><p>Tidak ada kelas.</p></div>
    <?php else: ?>
        <div class="card">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nama Kelas</th>
                        <th>Wali Murid</th>
                        <th>No. Telp Wali Murid</th>
                        <th>Nama KM</th>
                        <th>No. Telp KM</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classes as $i => $c): ?>
                        <tr>
                            <td><?php echo $i+1; ?></td>
                            <td><?php echo sanitize($c['nama_kelas']); ?></td>
                            <td><?php echo sanitize($c['walimurid'] ?? '-'); ?></td>
                            <td><?php echo sanitize($c['no_telpon_wali'] ?? '-'); ?></td>
                            <td><?php echo sanitize($c['nama_km'] ?? '-'); ?></td>
                            <td><?php echo sanitize($c['no_telpon_km'] ?? '-'); ?></td>
                            <td>
                                <a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/classes/view.php?id=<?php echo (int)$c['id']; ?>">Lihat</a>
                                <?php
                                    $canModify = ($role === 'admin') || ($role === 'guru' && isset($c['guru_id']) && (int)$c['guru_id'] === $userId);
                                ?>
                                <?php if ($canModify): ?>
                                    &middot; <a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/classes/edit.php?id=<?php echo (int)$c['id']; ?>">Edit</a>
                                    &middot; <a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/classes/delete.php?id=<?php echo (int)$c['id']; ?>" onclick="return confirm('Hapus kelas?')">Hapus</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
