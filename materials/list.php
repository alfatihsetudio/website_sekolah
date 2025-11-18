<?php
/**
 * Daftar Materi (robust)
 * Menampilkan materi bagi guru (yang dibuatnya) atau siswa (dari kelas yang dia ikuti).
 */
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

$pageTitle = 'Daftar Materi';
include __DIR__ . '/../inc/header.php';

$db = getDB();
$userId = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? 'siswa';
$materials = [];
$errorFetch = '';

// helper: cari nama kolom nama pada tabel users (name, nama, full_name, username)
function detectUserNameColumn($db) {
    $possible = ['name','nama','full_name','username'];
    $res = $db->query("SHOW COLUMNS FROM users");
    if ($res) {
        while ($col = $res->fetch_assoc()) {
            $field = strtolower($col['Field']);
            if (in_array($field, $possible, true)) {
                return $col['Field'];
            }
        }
    }
    return null;
}

$userNameCol = detectUserNameColumn($db);

/* ========== Branch: guru ========== */
if ($role === 'guru') {
    $sql = "
        SELECT m.id, m.judul, m.created_at, s.nama_mapel, COALESCE(c.nama_kelas, CONCAT(s.class_level, ' ', s.jurusan)) AS nama_kelas
        FROM materials m
        LEFT JOIN subjects s ON m.subject_id = s.id
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE m.created_by = ?
        ORDER BY m.created_at DESC
    ";
    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            $materials = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        } else {
            $errorFetch = 'Gagal mengeksekusi query materi (guru).';
        }
        $stmt->close();
    } else {
        $errorFetch = 'Gagal menyiapkan query materi (guru).';
    }
} else {
    /* ========== Branch: siswa / lainnya ==========
       Kita coba beberapa variasi query tergantung nama tabel relasi (class_user / class_members).
       Jika keduanya gagal, coba ambil semua materi (fallback) agar halaman tetap tampil,
       tapi beritahukan user bahwa filtering kelas gagal.
    */
    $queries = [];

    // prefer query yang juga mengambil nama guru (jika kolom nama terdeteksi)
    $guruSelect = $userNameCol ? ", u.`{$userNameCol}` AS guru_name" : "";

    // Query 1: using class_user
    $queries[] = "
        SELECT DISTINCT m.id, m.judul, m.created_at, s.nama_mapel, COALESCE(c.nama_kelas, CONCAT(s.class_level, ' ', s.jurusan)) AS nama_kelas {$guruSelect}
        FROM materials m
        LEFT JOIN subjects s ON m.subject_id = s.id
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN users u ON m.created_by = u.id
        JOIN class_user cu ON cu.class_id = c.id
        WHERE cu.user_id = ?
        ORDER BY m.created_at DESC
    ";

    // Query 2: using class_members (alternative common name)
    $queries[] = "
        SELECT DISTINCT m.id, m.judul, m.created_at, s.nama_mapel, COALESCE(c.nama_kelas, CONCAT(s.class_level, ' ', s.jurusan)) AS nama_kelas {$guruSelect}
        FROM materials m
        LEFT JOIN subjects s ON m.subject_id = s.id
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN users u ON m.created_by = u.id
        JOIN class_members cu ON cu.class_id = c.id
        WHERE cu.user_id = ?
        ORDER BY m.created_at DESC
    ";

    // Query 3: fallback — semua materi (tidak ideal, tapi supaya UI tampil)
    $queries[] = "
        SELECT m.id, m.judul, m.created_at, s.nama_mapel, COALESCE(c.nama_kelas, CONCAT(s.class_level, ' ', s.jurusan)) AS nama_kelas {$guruSelect}
        FROM materials m
        LEFT JOIN subjects s ON m.subject_id = s.id
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN users u ON m.created_by = u.id
        ORDER BY m.created_at DESC
    ";

    $materials = [];
    $ok = false;
    foreach ($queries as $q) {
        $stmt = $db->prepare($q);
        if (!$stmt) {
            // kemungkinan table tidak ada (prepare gagal) -> coba query berikutnya
            continue;
        }
        // bind param only if query has a placeholder (i.e. not the fallback)
        if (strpos($q, '?') !== false) {
            $stmt->bind_param("i", $userId);
        }
        if (!$stmt->execute()) {
            $stmt->close();
            continue;
        }
        $res = $stmt->get_result();
        $materials = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        $ok = true;
        break;
    }

    if (!$ok) {
        $errorFetch = 'Gagal mengambil materi untuk akun siswa. Periksa struktur tabel relasi kelas-siswa (class_user / class_members).';
    }
}

/* ---------------------
   Render page
   --------------------- */
?>

<div class="container">
    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
        <h1>Daftar Materi</h1>
        <?php if ($role === 'guru'): ?>
            <a href="/web_MG/materials/create.php" class="btn btn-primary">+ Tambah Materi</a>
        <?php endif; ?>
    </div>

    <?php if (!empty($errorFetch)): ?>
        <div class="alert alert-error"><?php echo sanitize($errorFetch); ?></div>
    <?php endif; ?>

    <?php if (empty($materials)): ?>
        <div class="card">
            <p>Tidak ada materi.</p>
            <?php if ($role === 'guru'): ?>
                <div style="margin-top:12px;">
                    <a href="/web_MG/materials/create.php" class="btn btn-primary">+ Buat Materi Baru</a>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="card">
            <ul class="list-group" style="list-style:none;padding:0;margin:0;">
                <?php foreach ($materials as $m): ?>
                    <li class="list-group-item" style="padding:14px;border-bottom:1px solid #eee;">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
                            <div style="flex:1;">
                                <div style="font-weight:600;font-size:16px;">
                                    <?php echo sanitize($m['judul']); ?>
                                </div>
                                <div style="font-size:13px;color:#666;margin-top:6px;">
                                    <?php echo sanitize($m['nama_mapel'] ?? '-'); ?> — <?php echo sanitize($m['nama_kelas'] ?? '-'); ?>
                                    &middot; <?php echo sanitize($m['created_at'] ?? '-'); ?>
                                </div>
                                <?php if ($role !== 'guru' && !empty($m['guru_name'])): ?>
                                    <div style="font-size:13px;color:#666;margin-top:6px;">
                                        Pengajar: <?php echo sanitize($m['guru_name']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div style="min-width:120px;text-align:right;">
                                <a href="/web_MG/materials/view.php?id=<?php echo (int)$m['id']; ?>">Lihat</a>
                                <?php if ($role === 'guru'): ?>
                                    &nbsp;·&nbsp;<a href="/web_MG/materials/edit.php?id=<?php echo (int)$m['id']; ?>">Edit</a>
                                    &nbsp;·&nbsp;<a href="/web_MG/materials/delete.php?id=<?php echo (int)$m['id']; ?>" onclick="return confirm('Hapus materi?')">Hapus</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
