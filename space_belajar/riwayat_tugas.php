<?php
// space_belajar/riwayat_tugas.php
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

$tugas = [];

if ($schoolId > 0 && $userId > 0) {
    $sql = "
        SELECT 
            a.id,
            a.judul,
            a.created_at,
            a.deadline,
            s.nama_mapel,
            c.nama_kelas,
            c.level,
            c.jurusan,
            sub.submitted_at,
            sub.nilai AS score
        FROM assignments a
        JOIN classes c 
            ON a.target_class_id = c.id
        LEFT JOIN subjects s 
            ON a.subject_id = s.id
        LEFT JOIN submissions sub 
            ON sub.assignment_id = a.id
           AND sub.student_id    = ?
        WHERE c.school_id = ?
          AND EXISTS (
                SELECT 1 
                FROM class_user cu
                WHERE cu.class_id = c.id
                  AND cu.user_id  = ?
          )
        ORDER BY a.created_at DESC
    ";

    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("iii", $userId, $schoolId, $userId);
        $stmt->execute();
        $res   = $stmt->get_result();
        $tugas = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    } else {
        // optional: log error, tapi jangan tampilkan detail ke user
        $tugas = [];
    }
}

$pageTitle = 'Riwayat Tugas';
include __DIR__ . '/../inc/header.php';
?>

<div class="container" style="max-width:1100px;margin:0 auto;">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
        <div>
            <h1 style="margin-bottom:4px;">Riwayat Tugas</h1>
            <p style="margin:0;font-size:.9rem;color:#6b7280;">
                Semua tugas yang pernah diberikan ke kamu, urut dari yang terbaru.
            </p>
        </div>
        <a href="<?php echo $baseUrl; ?>/space_belajar/dashboard.php" class="btn btn-secondary" style="font-size:.85rem;">
            ← Kembali ke Space Belajar
        </a>
    </div>

    <div class="card">
        <?php if (empty($tugas)): ?>
            <p style="margin:0;">Belum ada riwayat tugas untuk akun ini.</p>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="table">
                    <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Judul</th>
                        <th>Mapel / Kelas</th>
                        <th>Diberikan</th>
                        <th>Deadline</th>
                        <th>Status</th>
                        <th style="width:110px;">Nilai</th>
                        <th style="width:120px;">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tugas as $i => $t): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><?php echo sanitize($t['judul']); ?></td>
                            <td>
                                <?php
                                $kelasStr  = trim(($t['level'] ?? '') . ' ' . ($t['jurusan'] ?? ''));
                                $kelasNama = $kelasStr !== '' ? $kelasStr : ($t['nama_kelas'] ?? '');
                                $mapel     = $t['nama_mapel'] ?? '';
                                echo sanitize($mapel . ($mapel && $kelasNama ? ' • ' : '') . $kelasNama);
                                ?>
                            </td>
                            <td><?php echo sanitize($t['created_at']); ?></td>
                            <td>
                                <?php
                                $dl = $t['deadline'] ?? null;
                                echo ($dl && $dl !== '0000-00-00 00:00:00') ? sanitize($dl) : '-';
                                ?>
                            </td>
                            <td>
                                <?php if (!empty($t['submitted_at'])): ?>
                                    <span style="color:#16a34a;font-weight:600;font-size:.87rem;">
                                        Sudah dikumpulkan
                                    </span><br>
                                    <span style="font-size:.8rem;color:#6b7280;">
                                        <?php echo sanitize($t['submitted_at']); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:#b91c1c;font-weight:600;font-size:.87rem;">
                                        Belum dikumpulkan
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                if ($t['score'] === null || $t['score'] === '') {
                                    echo '<span style="font-size:.85rem;color:#6b7280;">Belum dinilai</span>';
                                } else {
                                    echo '<strong>' . sanitize($t['score']) . '</strong>';
                                }
                                ?>
                            </td>
                            <td>
                                <a href="<?php echo $baseUrl; ?>/space_belajar/riwayat_tugas_detail.php?id=<?php echo (int)$t['id']; ?>"
                                   style="font-size:.85rem;">
                                    Detail
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
