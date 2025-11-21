<?php
// space_belajar/riwayat_tugas_detail.php
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

$db         = getDB();
$baseUrl    = rtrim(BASE_URL, '/\\');
$userId     = (int)($_SESSION['user_id'] ?? 0);
$schoolId   = getCurrentSchoolId();
$assignmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($userId <= 0 || $schoolId <= 0 || $assignmentId <= 0) {
    http_response_code(404);
    echo "Data tidak ditemukan.";
    exit;
}

// Ambil detail tugas + info kelas/mapel/guru + submission milik murid ini
$sql = "
    SELECT
        a.id,
        a.judul,
        a.deskripsi,
        a.session_info,
        a.deadline,
        a.video_link,
        a.created_at,
        a.updated_at,
        s.nama_mapel,
        c.nama_kelas,
        c.level,
        c.jurusan,
        g.nama AS guru_nama,

        sub.id           AS submission_id,
        sub.submitted_at,
        sub.link_drive,
        sub.catatan,
        sub.nilai,
        sub.feedback,
        sub.graded_at,

        f.id             AS submission_file_id,
        f.original_name  AS submission_original_name,
        f.path           AS submission_path
    FROM assignments a
    JOIN classes c
        ON a.target_class_id = c.id
    LEFT JOIN subjects s
        ON a.subject_id = s.id
    LEFT JOIN users g
        ON a.created_by = g.id
    LEFT JOIN submissions sub
        ON sub.assignment_id = a.id
       AND sub.student_id    = ?
    LEFT JOIN files f
        ON sub.file_id = f.id
    WHERE a.id = ?
      AND c.school_id = ?
      AND EXISTS (
            SELECT 1
            FROM class_user cu
            WHERE cu.class_id = c.id
              AND cu.user_id  = ?
      )
    LIMIT 1
";

$stmt = $db->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo "Terjadi kesalahan saat mengambil data.";
    exit;
}

$stmt->bind_param("iiii", $userId, $assignmentId, $schoolId, $userId);
$stmt->execute();
$res   = $stmt->get_result();
$detail = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$detail) {
    http_response_code(404);
    echo "Tugas tidak ditemukan atau bukan untuk akun ini.";
    exit;
}

$pageTitle = 'Detail Riwayat Tugas';

$backUrl = !empty($_SERVER['HTTP_REFERER'])
    ? $_SERVER['HTTP_REFERER']
    : $baseUrl . '/space_belajar/riwayat_tugas.php';

include __DIR__ . '/../inc/header.php';
?>

<div class="container" style="max-width:900px;margin:0 auto;">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
        <div>
            <h1 style="margin-bottom:4px;">Detail Riwayat Tugas</h1>
            <p style="margin:0;font-size:.9rem;color:#6b7280;">
                Tampilan riwayat: instruksi guru, jawaban kamu, dan nilai.
            </p>
        </div>
        <a href="<?php echo htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8'); ?>"
           class="btn btn-secondary" style="font-size:.85rem;">
            ← Kembali
        </a>
    </div>

    <div class="card" style="margin-bottom:16px;">
        <h2 style="margin-top:0;margin-bottom:8px;font-size:1.2rem;">
            <?php echo sanitize($detail['judul']); ?>
        </h2>
        <p style="margin:0 0 4px;font-size:.9rem;color:#6b7280;">
            <?php
            $mapel   = $detail['nama_mapel'] ?? '';
            $kelas   = $detail['nama_kelas'] ?? '';
            $lv      = $detail['level'] ?? '';
            $jurusan = $detail['jurusan'] ?? '';
            $kelasStr = trim($lv . ' ' . $jurusan);
            $kelasLabel = $kelasStr !== '' ? $kelasStr : $kelas;

            $info = [];
            if ($mapel)      $info[] = 'Mapel: ' . $mapel;
            if ($kelasLabel) $info[] = 'Kelas: ' . $kelasLabel;
            if (!empty($detail['guru_nama'])) $info[] = 'Guru: ' . $detail['guru_nama'];

            echo sanitize(implode(' • ', $info));
            ?>
        </p>
        <p style="margin:0;font-size:.85rem;color:#6b7280;">
            Dibuat: <?php echo sanitize($detail['created_at']); ?>
            <?php if (!empty($detail['deadline']) && $detail['deadline'] !== '0000-00-00 00:00:00'): ?>
                • Deadline: <?php echo sanitize($detail['deadline']); ?>
            <?php endif; ?>
        </p>
    </div>

    <div class="card" style="margin-bottom:16px;">
        <h3 style="margin-top:0;margin-bottom:8px;font-size:1.05rem;">Instruksi dari Guru</h3>

        <?php if (!empty($detail['session_info'])): ?>
            <p style="margin:0 0 8px;font-size:.9rem;color:#4b5563;">
                <strong>Info Sesi:</strong><br>
                <?php echo nl2br(sanitize($detail['session_info'])); ?>
            </p>
        <?php endif; ?>

        <div style="margin:0 0 8px;font-size:.9rem;color:#111827;">
            <?php echo nl2br(sanitize($detail['deskripsi'])); ?>
        </div>

        <?php if (!empty($detail['video_link'])): ?>
            <p style="margin:8px 0 0;font-size:.9rem;">
                <strong>Video Pendukung:</strong><br>
                <a href="<?php echo sanitize($detail['video_link']); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo sanitize($detail['video_link']); ?>
                </a>
            </p>
        <?php endif; ?>
    </div>

    <div class="card" style="margin-bottom:16px;">
        <h3 style="margin-top:0;margin-bottom:8px;font-size:1.05rem;">Jawaban Kamu</h3>

        <?php if (empty($detail['submission_id'])): ?>
            <p style="margin:0;font-size:.9rem;color:#6b7280;">
                Kamu belum pernah mengumpulkan jawaban untuk tugas ini.
            </p>
        <?php else: ?>
            <p style="margin:0 0 6px;font-size:.9rem;color:#4b5563;">
                <strong>Waktu Pengumpulan:</strong><br>
                <?php echo sanitize($detail['submitted_at']); ?>
            </p>

            <?php if (!empty($detail['link_drive'])): ?>
                <p style="margin:0 0 6px;font-size:.9rem;">
                    <strong>Link Jawaban (Drive):</strong><br>
                    <a href="<?php echo sanitize($detail['link_drive']); ?>" target="_blank" rel="noopener noreferrer">
                        <?php echo sanitize($detail['link_drive']); ?>
                    </a>
                </p>
            <?php endif; ?>

            <?php if (!empty($detail['submission_file_id']) && !empty($detail['submission_path'])): ?>
                <?php
                $fileUrl = $baseUrl . '/' . ltrim($detail['submission_path'], '/');
                $fileName = $detail['submission_original_name'] ?: basename($detail['submission_path']);
                ?>
                <p style="margin:0 0 6px;font-size:.9rem;">
                    <strong>File Jawaban:</strong><br>
                    <a href="<?php echo sanitize($fileUrl); ?>" target="_blank" rel="noopener noreferrer">
                        <?php echo sanitize($fileName); ?>
                    </a>
                </p>
            <?php endif; ?>

            <?php if (!empty($detail['catatan'])): ?>
                <p style="margin:6px 0 0;font-size:.9rem;color:#111827;">
                    <strong>Catatan / Keterangan dari Kamu:</strong><br>
                    <?php echo nl2br(sanitize($detail['catatan'])); ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3 style="margin-top:0;margin-bottom:8px;font-size:1.05rem;">Nilai & Feedback Guru</h3>

        <?php if (empty($detail['submission_id'])): ?>
            <p style="margin:0;font-size:.9rem;color:#6b7280;">
                Belum ada nilai karena kamu belum mengumpulkan tugas ini.
            </p>
        <?php else: ?>
            <?php if ($detail['nilai'] === null || $detail['nilai'] === ''): ?>
                <p style="margin:0;font-size:.9rem;color:#6b7280;">
                    Tugas sudah dikumpulkan, tetapi belum dinilai.
                </p>
            <?php else: ?>
                <p style="margin:0 0 4px;font-size:1.1rem;">
                    <strong>Nilai:</strong>
                    <span style="font-size:1.3rem;">
                        <?php echo sanitize($detail['nilai']); ?>
                    </span>
                </p>
                <?php if (!empty($detail['graded_at'])): ?>
                    <p style="margin:0 0 6px;font-size:.8rem;color:#6b7280;">
                        Dinilai pada: <?php echo sanitize($detail['graded_at']); ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($detail['feedback'])): ?>
                <p style="margin:6px 0 0;font-size:.9rem;color:#111827;">
                    <strong>Feedback Guru:</strong><br>
                    <?php echo nl2br(sanitize($detail['feedback'])); ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
