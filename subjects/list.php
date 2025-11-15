<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

$pageTitle = 'Daftar Mata Pelajaran';
include __DIR__ . '/../inc/header.php';

$db = getDB();
$userId = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? 'siswa';

$subjects = [];

// Guru: tampilkan mapel yang dia ampuh
if ($role === 'guru') {
    $stmt = $db->prepare("SELECT s.id, s.nama_mapel, c.nama_kelas, s.deskripsi FROM subjects s JOIN classes c ON s.class_id = c.id WHERE s.guru_id = ? ORDER BY c.nama_kelas, s.nama_mapel");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $subjects = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
} else {
    // Siswa atau lainnya: mapel untuk kelas yang dia ikuti
    $stmt = $db->prepare("
        SELECT DISTINCT s.id, s.nama_mapel, c.nama_kelas, s.deskripsi, u.name AS guru_name
        FROM subjects s
        JOIN classes c ON s.class_id = c.id
        JOIN class_user cu ON cu.class_id = c.id
        LEFT JOIN users u ON u.id = s.guru_id
        WHERE cu.user_id = ?
        ORDER BY c.nama_kelas, s.nama_mapel
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $subjects = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}
?>

<div class="container">
    <div class="page-header">
        <h1>Daftar Mata Pelajaran</h1>
        <?php if ($role === 'guru'): ?>
            <a href="/web_MG/subjects/create.php" class="btn btn-primary">+ Tambah Mata Pelajaran</a>
        <?php endif; ?>
    </div>

    <?php if (empty($subjects)): ?>
        <div class="card">
            <p>Tidak ada mata pelajaran.</p>
        </div>
    <?php else: ?>
        <div class="card">
            <table class="table" style="width:100%;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Mata Pelajaran</th>
                        <th>Kelas</th>
                        <th>Deskripsi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subjects as $idx => $s): ?>
                        <tr>
                            <td><?php echo $idx + 1; ?></td>
                            <td><?php echo sanitize($s['nama_mapel']); ?></td>
                            <td><?php echo sanitize($s['nama_kelas']); ?></td>
                            <td><?php echo sanitize($s['deskripsi'] ?? ''); ?></td>
                            <td>
                                <a href="/web_MG/subjects/view.php?id=<?php echo (int)$s['id']; ?>">Lihat</a>
                                <?php if ($role === 'guru'): ?>
                                    &middot; <a href="/web_MG/subjects/edit.php?id=<?php echo (int)$s['id']; ?>">Edit</a>
                                    &middot; <a href="/web_MG/subjects/delete.php?id=<?php echo (int)$s['id']; ?>" onclick="return confirm('Hapus mapel ini?')">Hapus</a>
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
