<?php
/**
 * Daftar Materi (UI seragam seperti halaman Daftar Tugas)
 */
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireLogin();

$db       = getDB();
$baseUrl  = rtrim(BASE_URL, '/\\');
$userId   = (int)($_SESSION['user_id'] ?? 0);
$role     = $_SESSION['role'] ?? 'siswa';
$schoolId = getCurrentSchoolId();

// Default dashboard
$dashboardUrl = $baseUrl . '/dashboard/murid.php';
if ($role === 'guru')  $dashboardUrl = $baseUrl . '/dashboard/guru.php';
if ($role === 'admin') $dashboardUrl = $baseUrl . '/dashboard/admin.php';

// Ambil data sesuai role
$materials  = [];
$errorFetch = '';

$userNameCol = null;
$resCols = $db->query("SHOW COLUMNS FROM users");
if ($resCols) {
    while ($r = $resCols->fetch_assoc()) {
        if (in_array(strtolower($r['Field']), ['name','nama','full_name','username'])) {
            $userNameCol = $r['Field'];
            break;
        }
    }
}

if ($schoolId > 0) {

    if ($role === 'guru') {
        $sql = "
            SELECT m.id, m.judul, m.created_at, s.nama_mapel,
                   COALESCE(c.nama_kelas, CONCAT(s.class_level,' ',s.jurusan)) AS nama_kelas
            FROM materials m
            LEFT JOIN subjects s ON m.subject_id = s.id
            LEFT JOIN classes  c ON s.class_id   = c.id
            WHERE m.created_by = ?
              AND s.school_id  = ?
            ORDER BY m.created_at DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("ii", $userId, $schoolId);
        $stmt->execute();
        $materials = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

    } elseif ($role === 'murid' || $role === 'siswa') {

        $sql = "
            SELECT DISTINCT 
                m.id, m.judul, m.created_at, s.nama_mapel,
                COALESCE(c.nama_kelas, CONCAT(s.class_level,' ',s.jurusan)) AS nama_kelas
                " . ($userNameCol ? ", u.`{$userNameCol}` AS guru_name" : "") . "
            FROM materials m
            LEFT JOIN subjects s ON m.subject_id = s.id
            LEFT JOIN classes  c ON s.class_id = c.id
            LEFT JOIN users u   ON m.created_by = u.id
            WHERE s.school_id = ?
              AND (
                    c.id IN (SELECT class_id FROM class_user WHERE user_id = ?)
                OR  c.id = (SELECT class_id FROM users WHERE id = ? LIMIT 1)
              )
            ORDER BY m.created_at DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("iii", $schoolId, $userId, $userId);
        $stmt->execute();
        $materials = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

    } elseif ($role === 'admin') {

        $sql = "
            SELECT 
                m.id, m.judul, m.created_at, s.nama_mapel,
                COALESCE(c.nama_kelas, CONCAT(s.class_level,' ',s.jurusan)) AS nama_kelas
                " . ($userNameCol ? ", u.`{$userNameCol}` AS guru_name" : "") . "
            FROM materials m
            LEFT JOIN subjects s ON m.subject_id = s.id
            LEFT JOIN classes  c ON s.class_id = c.id
            LEFT JOIN users u   ON m.created_by = u.id
            WHERE s.school_id = ?
            ORDER BY m.created_at DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("i", $schoolId);
        $stmt->execute();
        $materials = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

$pageTitle = 'Daftar Materi';
include __DIR__ . '/../inc/header.php';
?>

<div class="container">

    <div class="page-header"
         style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
        
        <div>
            <h1 style="margin-bottom:4px;">Daftar Materi</h1>
            <p style="margin:0;font-size:0.9rem;color:#6b7280;">
                Anda login sebagai <strong><?php echo strtoupper(htmlspecialchars($role)); ?></strong>.
                Menampilkan materi sesuai kelas & sekolah Anda.
            </p>
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a class="btn btn-secondary" href="<?php echo $dashboardUrl; ?>">← Kembali ke Dashboard</a>

            <?php if ($role === 'guru'): ?>
                <a class="btn btn-primary" href="<?php echo $baseUrl; ?>/materials/create.php">
                    + Tambah Materi
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Jika sekolah tidak valid -->
    <?php if ($schoolId <= 0): ?>
        <div class="card">
            <p>School ID tidak ditemukan. Silakan logout dan login kembali.</p>
        </div>

    <!-- Jika kosong -->
    <?php elseif (empty($materials)): ?>
        <div class="card">
            <p>Tidak ada materi.</p>
            <?php if ($role === 'guru'): ?>
                <a href="<?php echo $baseUrl; ?>/materials/create.php" class="btn btn-primary" style="margin-top:8px;">
                    + Buat Materi Baru
                </a>
            <?php endif; ?>
        </div>

    <!-- Jika ada data -->
    <?php else: ?>

        <div class="card">
            <div style="overflow-x:auto;">
                <table class="table" style="width:100%;">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Judul</th>
                            <th>Mata Pelajaran / Kelas</th>
                            <th>Dibuat</th>
                            <th style="width:160px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($materials as $i => $m): ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td><?php echo sanitize($m['judul']); ?></td>

                                <td>
                                    <?php
                                        echo sanitize(($m['nama_mapel'] ?? '-') . ' — ' . ($m['nama_kelas'] ?? '-'));
                                    ?>
                                </td>

                                <td><?php echo sanitize($m['created_at']); ?></td>

                                <td>
                                    <a href="<?php echo $baseUrl; ?>/materials/view.php?id=<?php echo $m['id']; ?>">
                                        Lihat
                                    </a>

                                    <?php if ($role === 'guru'): ?>
                                        &middot;
                                        <a href="<?php echo $baseUrl; ?>/materials/edit.php?id=<?php echo $m['id']; ?>">
                                            Edit
                                        </a>
                                        &middot;
                                        <a href="<?php echo $baseUrl; ?>/materials/delete.php?id=<?php echo $m['id']; ?>"
                                           onclick="return confirm('Hapus materi ini?');">
                                            Hapus
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>

</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
