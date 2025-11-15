<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

$pageTitle = 'Daftar Tugas';
include __DIR__ . '/../inc/header.php';

$db = getDB();
$userId = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? 'siswa';
$assignments = [];

if ($role === 'guru') {
    $stmt = $db->prepare("
        SELECT a.id, a.judul, a.created_at, a.deadline, s.nama_mapel, c.nama_kelas
        FROM assignments a
        JOIN subjects s ON a.subject_id = s.id
        JOIN classes c ON a.target_class_id = c.id
        WHERE a.created_by = ?
        ORDER BY a.created_at DESC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $assignments = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
} else {
    $stmt = $db->prepare("
        SELECT DISTINCT a.id, a.judul, a.created_at, a.deadline, s.nama_mapel, c.nama_kelas
        FROM assignments a
        JOIN subjects s ON a.subject_id = s.id
        JOIN classes c ON a.target_class_id = c.id
        JOIN class_user cu ON cu.class_id = c.id
        WHERE cu.user_id = ?
        ORDER BY a.created_at DESC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $assignments = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}
?>

<div class="container">
    <div class="page-header">
        <h1>Daftar Tugas</h1>
        <?php if ($role === 'guru'): ?>
            <a href="/web_MG/assignments/create.php" class="btn btn-primary">+ Buat Tugas</a>
        <?php endif; ?>
    </div>

    <?php if (empty($assignments)): ?>
        <div class="card">
            <p>Tidak ada tugas.</p>
        </div>
    <?php else: ?>
        <div class="card">
            <table class="table" style="width:100%;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Judul</th>
                        <th>Mata Pelajaran / Kelas</th>
                        <th>Dibuat</th>
                        <th>Deadline</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assignments as $i => $a): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><?php echo sanitize($a['judul']); ?></td>
                            <td><?php echo sanitize($a['nama_mapel']) . ' — ' . sanitize($a['nama_kelas']); ?></td>
                            <td><?php echo sanitize($a['created_at']); ?></td>
                            <td><?php echo $a['deadline'] ? sanitize($a['deadline']) : '-'; ?></td>
                            <td>
                                <a href="/web_MG/assignments/view.php?id=<?php echo (int)$a['id']; ?>">Lihat</a>
                                <?php if ($role === 'guru'): ?>
                                    &middot; <a href="/web_MG/assignments/edit.php?id=<?php echo (int)$a['id']; ?>">Edit</a>
                                    &middot; <a href="/web_MG/assignments/delete.php?id=<?php echo (int)$a['id']; ?>" onclick="return confirm('Hapus tugas?')">Hapus</a>
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
