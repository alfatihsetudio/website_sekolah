<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireLogin();

$db      = getDB();
$baseUrl = rtrim(BASE_URL, '/\\');

$userId   = (int)($_SESSION['user_id'] ?? 0);
$role     = getUserRole() ?? ($_SESSION['role'] ?? 'siswa');
$schoolId = (int) getCurrentSchoolId();

$subjects   = [];
$errorFetch = '';

/**
 * Helper: tampilan nama kelas (prefer nama_kelas)
 */
function kelas_display($row) {
    if (!empty($row['nama_kelas'])) return $row['nama_kelas'];

    $lv = trim($row['class_level'] ?? '');
    $jr = trim($row['jurusan'] ?? '');
    $mix = trim(($lv ? $lv . ' ' : '') . $jr);
    return $mix ?: '-';
}

/**
 * Helper: cari kolom nama user yang tersedia
 */
function detectUserNameColumn(mysqli $db) {
    $possible = ['nama', 'name', 'full_name', 'username'];
    $res = $db->query("SHOW COLUMNS FROM users");
    if ($res) {
        while ($col = $res->fetch_assoc()) {
            $f = strtolower($col['Field']);
            if (in_array($f, $possible, true)) {
                return $col['Field'];
            }
        }
    }
    return null;
}

$userNameCol = detectUserNameColumn($db);
$guruSelect  = $userNameCol
    ? ", u.`{$userNameCol}` AS guru_name"
    : ", '' AS guru_name";

// Cek school_id valid
if ($schoolId <= 0) {
    $subjects   = [];
    $errorFetch = 'School ID tidak ditemukan. Silakan login ulang.';
} else {

    /* ===========================
       ROLE: GURU
       =========================== */
    if ($role === 'guru') {
        $sql = "
            SELECT
                s.id,
                s.nama_mapel,
                c.nama_kelas,
                s.class_level,
                s.jurusan
            FROM subjects s
            LEFT JOIN classes c ON s.class_id = c.id
            WHERE s.guru_id   = ?
              AND s.school_id = ?
            ORDER BY COALESCE(c.nama_kelas, CONCAT(s.class_level, ' ', s.jurusan)), s.nama_mapel
        ";
        $stmt = $db->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ii", $userId, $schoolId);
            $stmt->execute();
            $res = $stmt->get_result();
            $subjects = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            $stmt->close();
        } else {
            $errorFetch = 'Query guru gagal disiapkan.';
        }

    /* ===========================
       ROLE: ADMIN
       Melihat seluruh mapel sekolah
       =========================== */
    } elseif ($role === 'admin') {

        $sql = "
            SELECT
                s.id,
                s.nama_mapel,
                c.nama_kelas,
                s.class_level,
                s.jurusan
                {$guruSelect}
            FROM subjects s
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN users   u ON s.guru_id = u.id
            WHERE s.school_id = ?
            ORDER BY COALESCE(c.nama_kelas, CONCAT(s.class_level, ' ', s.jurusan)), s.nama_mapel
        ";

        $stmt = $db->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $schoolId);
            $stmt->execute();
            $res = $stmt->get_result();
            $subjects = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            $stmt->close();
        } else {
            $errorFetch = 'Query admin gagal disiapkan.';
        }

    /* ===========================
       ROLE: SISWA / MURID
       =========================== */
    } else {

        $sql = "
            SELECT DISTINCT
                s.id,
                s.nama_mapel,
                c.nama_kelas,
                s.class_level,
                s.jurusan
                {$guruSelect}
            FROM subjects s
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN users   u ON s.guru_id = u.id
            WHERE s.school_id = ?
              AND c.school_id = ?
              AND (
                    c.id IN (
                        SELECT cu.class_id
                        FROM class_user cu
                        WHERE cu.user_id = ?
                    )
                    OR c.id = (
                        SELECT class_id FROM users WHERE id = ? LIMIT 1
                    )
                 )
            ORDER BY COALESCE(c.nama_kelas, CONCAT(s.class_level, ' ', s.jurusan)), s.nama_mapel
        ";

        $stmt = $db->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("iiii", $schoolId, $schoolId, $userId, $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            $subjects = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            $stmt->close();
        } else {
            $errorFetch = 'Query siswa gagal disiapkan.';
        }
    }
}

$pageTitle = 'Daftar Mata Pelajaran';
include __DIR__ . '/../inc/header.php';
?>

<div class="container">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
        <h1>Daftar Mata Pelajaran</h1>

        <div style="display:flex;gap:10px;">
            <a href="<?php echo $baseUrl; ?>/dashboard/<?php echo ($role==='admin' ? 'admin' : ($role==='guru'?'guru':'murid')); ?>.php"
               class="btn btn-secondary">← Kembali ke Dashboard</a>

            <?php if ($role === 'guru' || $role === 'admin'): ?>
                <a href="/web_MG/subjects/create.php" class="btn btn-primary">+ Tambah Mata Pelajaran</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($errorFetch): ?>
        <div class="alert alert-error"><?php echo sanitize($errorFetch); ?></div>
    <?php endif; ?>

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
                        <?php if ($role !== 'guru'): ?>
                            <th>Guru</th>
                        <?php endif; ?>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subjects as $idx => $s): ?>
                        <tr>
                            <td><?php echo (int)($idx + 1); ?></td>
                            <td><?php echo sanitize($s['nama_mapel']); ?></td>
                            <td><?php echo sanitize(kelas_display($s)); ?></td>

                            <?php if ($role !== 'guru'): ?>
                                <td><?php echo sanitize($s['guru_name'] ?? '-'); ?></td>
                            <?php endif; ?>

                            <td>
                                <a href="/web_MG/subjects/view.php?id=<?php echo (int)$s['id']; ?>">Lihat</a>

                                <?php if ($role === 'guru' || $role === 'admin'): ?>
                                    &middot; <a href="/web_MG/subjects/edit.php?id=<?php echo (int)$s['id']; ?>">Edit</a>
                                    &middot; <a href="/web_MG/subjects/delete.php?id=<?php echo (int)$s['id']; ?>"
                                               onclick="return confirm('Hapus mapel ini?')">Hapus</a>
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
