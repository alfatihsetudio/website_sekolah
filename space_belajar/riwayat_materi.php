<?php
// space_belajar/riwayat_materi.php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireLogin();

$role = getUserRole();
if (!in_array($role, ['murid', 'siswa'], true)) {
    http_response_code(403);
    echo "Halaman ini khusus murid.";
    exit;
}

$db       = getDB();
$baseUrl  = rtrim(BASE_URL, '/\\');
$userId   = (int)($_SESSION['user_id'] ?? 0);
$schoolId = getCurrentSchoolId();

$materi = [];

if ($schoolId > 0 && $userId > 0) {
    $sql = "
        SELECT 
            m.id,
            m.judul,
            m.konten,
            m.file_path,
            m.video_link,
            m.created_at,
            s.nama_mapel,
            c.nama_kelas,
            c.level,
            c.jurusan,
            g.nama AS guru_nama
        FROM materials m
        JOIN subjects s 
            ON m.subject_id = s.id
        JOIN classes c
            ON s.class_id = c.id
        LEFT JOIN users g
            ON m.created_by = g.id
        WHERE c.school_id = ?
          AND s.school_id = ?
          AND EXISTS (
                SELECT 1 
                FROM class_user cu
                WHERE cu.class_id = c.id
                  AND cu.user_id  = ?
          )
        ORDER BY m.created_at DESC
    ";

    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("iii", $schoolId, $schoolId, $userId);
        $stmt->execute();
        $res    = $stmt->get_result();
        $materi = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    } else {
        $materi = [];
    }
}

$pageTitle = 'Riwayat Materi';
include __DIR__ . '/../inc/header.php';
?>

<div class="container" style="max-width:1100px;margin:0 auto;">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
        <div>
            <h1 style="margin-bottom:4px;">Riwayat Materi</h1>
            <p style="margin:0;font-size:.9rem;color:#6b7280;">
                Semua materi yang pernah dibagikan ke kamu, urut dari yang terbaru.
            </p>
        </div>
        <a href="<?php echo $baseUrl; ?>/space_belajar/dashboard.php" class="btn btn-secondary" style="font-size:.85rem;">
            ← Kembali ke Space Belajar
        </a>
    </div>

    <div class="card">
        <?php if (empty($materi)): ?>
            <p style="margin:0;">Belum ada riwayat materi untuk akun ini.</p>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="table">
                    <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Judul</th>
                        <th>Mapel / Kelas</th>
                        <th>Guru</th>
                        <th>Dibuat</th>
                        <th style="width:120px;">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($materi as $i => $m): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td>
                                <div style="font-weight:600;">
                                    <?php echo sanitize($m['judul']); ?>
                                </div>
                                <?php if (!empty($m['konten'])): ?>
                                    <div style="font-size:.8rem;color:#6b7280;max-width:360px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                        <?php echo sanitize(mb_substr($m['konten'], 0, 100)); ?>
                                        <?php if (mb_strlen($m['konten']) > 100): ?>...<?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $kelasStr  = trim(($m['level'] ?? '') . ' ' . ($m['jurusan'] ?? ''));
                                $kelasNama = $kelasStr !== '' ? $kelasStr : ($m['nama_kelas'] ?? '');
                                $mapel     = $m['nama_mapel'] ?? '';
                                echo sanitize($mapel . ($mapel && $kelasNama ? ' • ' : '') . $kelasNama);
                                ?>
                            </td>
                            <td>
                                <?php echo sanitize($m['guru_nama'] ?? '-'); ?>
                            </td>
                            <td>
                                <?php echo sanitize($m['created_at']); ?>
                            </td>
                            <td>
                                <a href="<?php echo $baseUrl; ?>/materials/view.php?id=<?php echo (int)$m['id']; ?>"
                                   style="font-size:.85rem;">
                                    Lihat
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
