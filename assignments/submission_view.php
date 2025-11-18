<?php
// assignments/submission_view.php
// Lihat detail 1 pengumpulan tugas (submission) + lampiran + form penilaian (guru)

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireLogin();

$db      = getDB();
$userId  = (int)($_SESSION['user_id'] ?? 0);
$role    = $_SESSION['role'] ?? 'murid';

$subId = (int)($_GET['id'] ?? 0);
if ($subId <= 0) {
    echo "ID pengumpulan tidak valid.";
    exit;
}

// --- Ambil data submission + tugas + mapel + kelas + siswa + guru ---
$sql = "
    SELECT
        s.*,
        a.judul              AS assignment_title,
        a.deadline,
        a.created_at         AS assignment_created_at,
        a.created_by         AS assignment_created_by,
        sub.nama_mapel,
        sub.guru_id          AS subject_guru_id,
        c.nama_kelas,
        c.level,
        c.jurusan,
        stu.nama             AS student_nama,
        stu.email            AS student_email,
        tea.nama             AS guru_nama,
        tea.email            AS guru_email
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN subjects   sub ON a.subject_id   = sub.id
    JOIN classes    c   ON a.target_class_id = c.id
    LEFT JOIN users stu ON s.student_id   = stu.id
    LEFT JOIN users tea ON a.created_by   = tea.id
    WHERE s.id = ?
    LIMIT 1
";

$stmt = $db->prepare($sql);
if (!$stmt) {
    echo "DB Error: " . sanitize($db->error);
    exit;
}
$stmt->bind_param("i", $subId);
$stmt->execute();
$res = $stmt->get_result();
$sub = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$sub) {
    echo "Pengumpulan tidak ditemukan.";
    exit;
}

// --- Access control ---
// 1) Admin selalu boleh
$allowed = ($role === 'admin');

// 2) Murid pemilik submission
if (!$allowed && $role === 'murid' && (int)$sub['student_id'] === $userId) {
    $allowed = true;
}

// 3) Guru pembuat tugas / guru mapel
if (!$allowed && $role === 'guru') {
    if ((int)$sub['assignment_created_by'] === $userId ||
        (int)$sub['subject_guru_id'] === $userId) {
        $allowed = true;
    }
}

if (!$allowed) {
    http_response_code(403);
    echo "Anda tidak memiliki hak untuk melihat pengumpulan ini.";
    exit;
}

// Guru yang boleh menilai: guru pembuat tugas / guru mapel
$canGrade = ($role === 'guru' &&
    ((int)$sub['assignment_created_by'] === $userId ||
     (int)$sub['subject_guru_id'] === $userId)
);

// --- PROSES POST: simpan nilai & feedback ---
$gradeError  = '';
$gradeSuccess = '';

if ($canGrade && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token, 'grade_submission_' . $subId)) {
        $gradeError = 'Token tidak valid. Silakan muat ulang halaman.';
    } else {
        $nilaiRaw   = trim($_POST['nilai'] ?? '');
        $feedback   = trim($_POST['feedback'] ?? '');

        if ($nilaiRaw === '') {
            $gradeError = 'Nilai harus diisi.';
        } elseif (!is_numeric($nilaiRaw)) {
            $gradeError = 'Nilai harus berupa angka.';
        } else {
            $nilai = (float)$nilaiRaw;
            // Silakan sesuaikan range, misal 0–100
            if ($nilai < 0 || $nilai > 100) {
                $gradeError = 'Nilai harus di antara 0 sampai 100.';
            } else {
                // Simpan ke submissions
                $upd = $db->prepare("
                    UPDATE submissions
                    SET nilai = ?, feedback = ?, graded_at = NOW(), graded_by = ?
                    WHERE id = ?
                ");
                if (!$upd) {
                    $gradeError = 'Gagal menyiapkan query penilaian: ' . $db->error;
                } else {
                    $sid = (int)$sub['id'];
                    $upd->bind_param("dsii", $nilai, $feedback, $userId, $sid);
                    if ($upd->execute()) {
                        $gradeSuccess = 'Nilai berhasil disimpan.';
                        // update data di memori biar tampilan ikut berubah
                        $sub['nilai']    = $nilai;
                        $sub['feedback'] = $feedback;
                        $sub['graded_at'] = date('Y-m-d H:i:s');
                        $sub['graded_by'] = $userId;

                        // Kirim notifikasi ke siswa
                        if (!empty($sub['student_id'])) {
                            createNotification(
                                (int)$sub['student_id'],
                                'Nilai Tugas: ' . ($sub['assignment_title'] ?? 'Tugas'),
                                'Tugas Anda sudah dinilai. Nilai: ' . $nilai,
                                '/web_MG/assignments/submission_view.php?id=' . $sid
                            );
                        }
                    } else {
                        $gradeError = 'Gagal menyimpan nilai: ' . $upd->error;
                    }
                    $upd->close();
                }
            }
        }
    }
}

// --- Ambil info file (jika ada file_id) dari file_uploads ---
$fileInfo  = null;
$serveUrl  = null;

if (!empty($sub['file_id']) && (int)$sub['file_id'] > 0) {
    $fid = (int)$sub['file_id'];
    $r = $db->query("SHOW TABLES LIKE 'file_uploads'");
    if ($r && $r->num_rows > 0) {
        $q = $db->prepare("SELECT * FROM file_uploads WHERE id = ? LIMIT 1");
        if ($q) {
            $q->bind_param("i", $fid);
            $q->execute();
            $rf  = $q->get_result();
            $row = $rf ? $rf->fetch_assoc() : null;
            $q->close();

            if ($row) {
                $fileInfo = [
                    'id'            => $row['id'],
                    'original_name' => $row['original_name'],
                    'stored_name'   => $row['stored_name'],
                    'file_path'     => $row['file_path'],
                    'mime_type'     => $row['mime_type'],
                    'file_size'     => $row['file_size'],
                    'raw'           => $row,
                ];

                if (!empty($row['file_path'])) {
                    $rel      = trim($row['file_path'], "/\\");
                    $serveUrl = rtrim(BASE_URL, '/\\') .
                                '/serve_file.php?f=' . rawurlencode($rel) .
                                '&mode=inline';
                }
            }
        }
    }
}

$judulPage  = 'Pengumpulan: ' . ($sub['assignment_title'] ?? 'Tugas');
$pageTitle  = $judulPage;

include __DIR__ . '/../inc/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><?php echo sanitize($judulPage); ?></h1>
        <p style="font-size:14px;color:#555;">
            Tugas: <strong><?php echo sanitize($sub['assignment_title']); ?></strong><br>
            Mapel: <?php echo sanitize($sub['nama_mapel']); ?> —
            Kelas: <?php echo sanitize(trim(($sub['level'] ?? '') . ' ' . ($sub['jurusan'] ?? '')) ?: $sub['nama_kelas']); ?><br>
            Guru: <?php echo sanitize($sub['guru_nama'] ?? '-'); ?>
        </p>
    </div>

    <div class="card" style="padding:18px;margin-bottom:18px;">
        <h3>Identitas Siswa</h3>
        <p>
            Nama: <strong><?php echo sanitize($sub['student_nama'] ?? ('ID ' . $sub['student_id'])); ?></strong><br>
            Email: <?php echo sanitize($sub['student_email'] ?? '-'); ?><br>
            Waktu Pengumpulan:
            <strong><?php echo sanitize($sub['submitted_at']); ?></strong>
            (<?php echo sanitize(timeAgo($sub['submitted_at'])); ?>)
        </p>
    </div>

    <div class="card" style="padding:18px;margin-bottom:18px;">
        <h3>Jawaban / Catatan</h3>
        <?php if (!empty($sub['catatan'])): ?>
            <div style="white-space:pre-wrap;"><?php echo nl2br(sanitize($sub['catatan'])); ?></div>
        <?php else: ?>
            <p><em>Tidak ada jawaban teks.</em></p>
        <?php endif; ?>

        <?php if (!empty($sub['link_drive'])): ?>
            <div style="margin-top:12px;">
                <strong>Link Video / Drive:</strong><br>
                <a href="<?php echo sanitize($sub['link_drive']); ?>" target="_blank" rel="noopener">
                    <?php echo sanitize($sub['link_drive']); ?>
                </a>
            </div>
        <?php endif; ?>

        <?php if ($fileInfo): ?>
            <div style="margin-top:16px;">
                <strong>Lampiran File:</strong>
                <div style="margin-top:8px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                    <?php if (!empty($serveUrl)): ?>
                        <button type="button"
                                class="btn btn-primary"
                                style="padding:8px 14px;border-radius:8px;"
                                onclick="window.open('<?php echo sanitize($serveUrl); ?>','_blank','noopener')">
                            Lihat Lampiran
                        </button>
                        <a href="<?php echo sanitize($serveUrl); ?>"
                           download
                           class="btn"
                           style="padding:8px 12px;border-radius:8px;background:#f3f4f6;color:#111;text-decoration:none;">
                            Unduh
                        </a>
                    <?php else: ?>
                        <span><em>File tidak dapat diakses.</em></span>
                    <?php endif; ?>

                    <span style="font-size:14px;color:#333;">
                        <?php echo sanitize($fileInfo['original_name']); ?>
                        <?php if (!empty($fileInfo['file_size'])): ?>
                            (<?php echo number_format($fileInfo['file_size'] / 1024, 1); ?> KB)
                        <?php endif; ?>
                    </span>
                </div>

                <?php
                    $ext = strtolower(pathinfo($fileInfo['stored_name'] ?? $fileInfo['original_name'], PATHINFO_EXTENSION));
                    $imgExts = ['jpg','jpeg','png','gif','webp','svg'];
                    if (!empty($serveUrl) && in_array($ext, $imgExts, true)):
                ?>
                    <div style="margin-top:10px;">
                        <img src="<?php echo sanitize($serveUrl); ?>"
                             alt="<?php echo sanitize($fileInfo['original_name']); ?>"
                             style="max-width:320px;height:auto;border:1px solid #eee;border-radius:8px;padding:4px;cursor:pointer;"
                             onclick="window.open('<?php echo sanitize($serveUrl); ?>','_blank','noopener')">
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card" style="padding:18px;margin-bottom:18px;">
        <h3>Penilaian</h3>

        <?php if ($gradeError): ?>
            <div class="alert alert-error"><?php echo sanitize($gradeError); ?></div>
        <?php endif; ?>
        <?php if ($gradeSuccess): ?>
            <div class="alert alert-success"><?php echo sanitize($gradeSuccess); ?></div>
        <?php endif; ?>

        <p>
            Nilai saat ini:
            <strong>
                <?php
                    echo is_null($sub['nilai'])
                        ? 'Belum dinilai'
                        : sanitize($sub['nilai']);
                ?>
            </strong><br>
            Catatan Guru:
            <?php if (!empty($sub['feedback'])): ?>
                <div style="white-space:pre-wrap;margin-top:6px;">
                    <?php echo nl2br(sanitize($sub['feedback'])); ?>
                </div>
            <?php else: ?>
                <em>Belum ada catatan.</em>
            <?php endif; ?>
        </p>

        <?php if ($canGrade): ?>
            <hr>
            <h4>Beri / Ubah Nilai</h4>
            <form method="POST">
                <?php $csrf = generateCsrfToken('grade_submission_' . $subId); ?>
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf); ?>">

                <div class="form-group">
                    <label>Nilai (0–100)</label>
                    <input type="number"
                           name="nilai"
                           step="0.01"
                           min="0"
                           max="100"
                           required
                           value="<?php echo isset($_POST['nilai']) ? sanitize($_POST['nilai']) : (is_null($sub['nilai']) ? '' : sanitize($sub['nilai'])); ?>">
                </div>

                <div class="form-group">
                    <label>Pesan / Catatan untuk siswa (opsional)</label>
                    <textarea name="feedback" rows="4" style="width:100%;"><?php
                        echo isset($_POST['feedback'])
                            ? sanitize($_POST['feedback'])
                            : sanitize($sub['feedback'] ?? '');
                    ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Simpan Nilai</button>
            </form>
        <?php endif; ?>
    </div>

    <div style="margin-top:10px;">
        <a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/assignments/view.php?id=<?php echo (int)$sub['assignment_id']; ?>" class="btn btn-secondary">
            ← Kembali ke detail tugas
        </a>
        <a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/assignments/list.php" class="btn">
            Daftar Tugas
        </a>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
