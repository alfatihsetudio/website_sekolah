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

/**
 * Helper: ambil display nama kelas (prefer nama_kelas, fallback ke class_level + jurusan)
 */
function kelas_display($row) {
    if (!empty($row['nama_kelas'])) {
        return $row['nama_kelas'];
    }
    $lv = trim($row['class_level'] ?? '');
    $jr = trim($row['jurusan'] ?? '');
    $comb = trim(($lv ? $lv . ' ' : '') . $jr);
    return $comb ?: '-';
}

/* ===========================
   Branch: guru
   =========================== */
if ($role === 'guru') {
    $sql = "
        SELECT s.id, s.nama_mapel, c.nama_kelas, s.class_level, s.jurusan
        FROM subjects s
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE s.guru_id = ?
        ORDER BY COALESCE(c.nama_kelas, CONCAT(s.class_level, ' ', s.jurusan)), s.nama_mapel
    ";
    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $subjects = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    } else {
        // jika prepare gagal, tetap kosong tapi beri pesan di UI nanti
        $subjects = [];
        $errorFetch = 'Gagal mengambil data mata pelajaran (guru).';
    }
} else {
    /* ===========================
       Branch: siswa / lainnya
       Coba query terhadap table class_user, kalau gagal coba class_members (fallback)
       =========================== */
    $queriesToTry = [
        // seperti file awal user: class_user
        "
        SELECT DISTINCT s.id, s.nama_mapel, c.nama_kelas, s.class_level, s.jurusan, u.name AS guru_name
        FROM subjects s
        LEFT JOIN classes c ON s.class_id = c.id
        JOIN class_user cu ON cu.class_id = c.id
        LEFT JOIN users u ON u.id = s.guru_id
        WHERE cu.user_id = ?
        ORDER BY COALESCE(c.nama_kelas, CONCAT(s.class_level, ' ', s.jurusan)), s.nama_mapel
        ",
        // fallback: class_members (nama tabel alternatif)
        "
        SELECT DISTINCT s.id, s.nama_mapel, c.nama_kelas, s.class_level, s.jurusan, u.name AS guru_name
        FROM subjects s
        LEFT JOIN classes c ON s.class_id = c.id
        JOIN class_members cu ON cu.class_id = c.id
        LEFT JOIN users u ON u.id = s.guru_id
        WHERE cu.user_id = ?
        ORDER BY COALESCE(c.nama_kelas, CONCAT(s.class_level, ' ', s.jurusan)), s.nama_mapel
        "
    ];

    $subjects = [];
    $errorFetch = '';
    foreach ($queriesToTry as $q) {
        $stmt = $db->prepare($q);
        if (!$stmt) {
            // prepare gagal (mis. tabel tidak ada), coba query berikutnya
            continue;
        }
        $stmt->bind_param("i", $userId);
        if (!$stmt->execute()) {
            $stmt->close();
            continue;
        }
        $res = $stmt->get_result();
        $subjects = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        // jika berhasil dan data (atau kosong) diperoleh, hentikan loop
        break;
    }

    if ($stmt === false) {
        $errorFetch = 'Gagal mengambil data mata pelajaran (siswa). Pastikan tabel relasi kelas-siswa ada.';
    }
}

?>

<div class="container">
    <div class="page-header">
        <h1>Daftar Mata Pelajaran</h1>
        <?php if ($role === 'guru'): ?>
            <a href="/web_MG/subjects/create.php" class="btn btn-primary">+ Tambah Mata Pelajaran</a>
        <?php endif; ?>
    </div>

    <?php if (!empty($errorFetch)): ?>
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
                            <td><?php echo $idx + 1; ?></td>
                            <td><?php echo sanitize($s['nama_mapel']); ?></td>
                            <td><?php echo sanitize(kelas_display($s)); ?></td>
                            <?php if ($role !== 'guru'): ?>
                                <td><?php echo sanitize($s['guru_name'] ?? '-'); ?></td>
                            <?php endif; ?>
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
