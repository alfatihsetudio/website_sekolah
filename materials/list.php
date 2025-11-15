<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

$pageTitle = 'Daftar Materi';
include __DIR__ . '/../inc/header.php';

$db = getDB();
$userId = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? 'siswa';
$materials = [];

if ($role === 'guru') {
    $stmt = $db->prepare("
        SELECT m.id, m.judul, m.created_at, s.nama_mapel, c.nama_kelas
        FROM materials m
        JOIN subjects s ON m.subject_id = s.id
        JOIN classes c ON s.class_id = c.id
        WHERE m.created_by = ?
        ORDER BY m.created_at DESC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $materials = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
} else {
    $stmt = $db->prepare("
        SELECT DISTINCT m.id, m.judul, m.created_at, s.nama_mapel, c.nama_kelas
        FROM materials m
        JOIN subjects s ON m.subject_id = s.id
        JOIN classes c ON s.class_id = c.id
        JOIN class_user cu ON cu.class_id = c.id
        WHERE cu.user_id = ?
        ORDER BY m.created_at DESC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $materials = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}
?>

<div class="container">
    <div class="page-header">
        <h1>Daftar Materi</h1>
        <?php if ($role === 'guru'): ?>
            <a href="/web_MG/materials/create.php" class="btn btn-primary">+ Tambah Materi</a>
        <?php endif; ?>
    </div>

    <?php if (empty($materials)): ?>
        <div class="card">
            <p>Tidak ada materi.</p>
        </div>
    <?php else: ?>
        <div class="card">
            <ul class="list-group">
                <?php foreach ($materials as $m): ?>
                    <li class="list-group-item">
                        <strong><?php echo sanitize($m['judul']); ?></strong>
                        <div style="font-size:13px; color:#666;">
                            <?php echo sanitize($m['nama_mapel']); ?> — <?php echo sanitize($m['nama_kelas']); ?>
                            &middot; <?php echo sanitize($m['created_at']); ?>
                        </div>
                        <div style="margin-top:6px;">
                            <a href="/web_MG/materials/view.php?id=<?php echo (int)$m['id']; ?>">Lihat</a>
                            <?php if ($role === 'guru'): ?>
                                &middot; <a href="/web_MG/materials/edit.php?id=<?php echo (int)$m['id']; ?>">Edit</a>
                                &middot; <a href="/web_MG/materials/delete.php?id=<?php echo (int)$m['id']; ?>" onclick="return confirm('Hapus materi?')">Hapus</a>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
