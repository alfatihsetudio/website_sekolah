<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

$pageTitle = 'Daftar Pengguna';
include __DIR__ . '/../inc/header.php';

$db = getDB();
$userId = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? 'siswa';
$users = [];

// helper kecil: cari kolom nama yang tersedia
function displayNameFromRow(array $row) {
    $candidates = ['name','nama','fullname','full_name','display_name','username','user_name'];
    foreach ($candidates as $k) {
        if (isset($row[$k]) && $row[$k] !== '') return $row[$k];
    }
    // fallback ke email atau id
    if (!empty($row['email'])) return $row['email'];
    if (!empty($row['id'])) return 'User #' . (int)$row['id'];
    return '-';
}

// Guru/admin/siswa: ambil data tanpa mengasumsikan ada kolom 'name'
if ($role === 'admin') {
    // ambil kolom yang pasti ada
    $stmt = $db->prepare("SELECT id, email, role, created_at FROM users ORDER BY role, id");
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} elseif ($role === 'guru') {
    // guru melihat siswa di kelasnya
    $stmt = $db->prepare("
        SELECT DISTINCT u.id, u.email, u.role, u.created_at
        FROM users u
        JOIN class_user cu ON cu.user_id = u.id
        JOIN classes c ON cu.class_id = c.id
        WHERE c.guru_id = ?
        ORDER BY u.name
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    // siswa: tampilkan profil sendiri
    $stmt = $db->prepare("SELECT id, email, role, created_at FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<div class="container">
    <div class="page-header">
        <h1>Daftar Pengguna</h1>
        <?php if ($role === 'admin'): ?>
            <a href="/web_MG/users/create.php" class="btn btn-primary">+ Tambah Pengguna</a>
        <?php endif; ?>
    </div>

    <?php if (empty($users)): ?>
        <div class="card"><p>Tidak ada pengguna.</p></div>
    <?php else: ?>
        <div class="card">
            <table class="table">
                <thead><tr><th>#</th><th>Nama / Identitas</th><th>Email</th><th>Role</th><th>Aksi</th></tr></thead>
                <tbody>
                    <?php foreach ($users as $i => $u): ?>
                        <tr>
                            <td><?php echo $i+1; ?></td>
                            <td><?php echo sanitize(displayNameFromRow($u)); ?></td>
                            <td><?php echo sanitize($u['email'] ?? '-'); ?></td>
                            <td><?php echo sanitize($u['role'] ?? '-'); ?></td>
                            <td>
                                <a href="/web_MG/users/view.php?id=<?php echo (int)$u['id']; ?>">Lihat</a>
                                <?php if ($role === 'admin'): ?>
                                    &middot; <a href="/web_MG/users/edit.php?id=<?php echo (int)$u['id']; ?>">Edit</a>
                                    &middot; <a href="/web_MG/users/delete.php?id=<?php echo (int)$u['id']; ?>" onclick="return confirm('Hapus pengguna?')">Hapus</a>
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
