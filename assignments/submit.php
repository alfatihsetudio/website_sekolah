<?php
// assignments/submit.php
// Murid mengumpulkan / mengedit jawaban tugas

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireLogin();
requireRole(['murid']); // hanya murid yg boleh

$db        = getDB();
$studentId = (int) ($_SESSION['user_id'] ?? 0);

// --- Ambil assignment_id dari GET/POST ---
$assignmentId = (int) ($_GET['assignment_id'] ?? ($_POST['assignment_id'] ?? 0));
if ($assignmentId <= 0) {
    echo "ID tugas tidak valid.";
    exit;
}

// --- Ambil detail tugas + cek apakah murid memang di kelas itu ---
$stmt = $db->prepare("
    SELECT a.*, s.nama_mapel, c.id AS class_id, c.nama_kelas, c.level, c.jurusan,
           u.id AS guru_id, u.nama AS guru_nama
    FROM assignments a
    JOIN subjects  s ON a.subject_id     = s.id
    JOIN classes   c ON a.target_class_id = c.id
    LEFT JOIN users u ON a.created_by    = u.id
    WHERE a.id = ? LIMIT 1
");
if (!$stmt) {
    echo "DB error: " . sanitize($db->error);
    exit;
}
$stmt->bind_param("i", $assignmentId);
$stmt->execute();
$res        = $stmt->get_result();
$assignment = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$assignment) {
    echo "Tugas tidak ditemukan.";
    exit;
}

$classId = (int) $assignment['target_class_id'];

// cek membership di class_user
$allowed = false;
$cek = $db->prepare("SELECT 1 FROM class_user WHERE class_id = ? AND user_id = ? LIMIT 1");
if ($cek) {
    $cek->bind_param("ii", $classId, $studentId);
    $cek->execute();
    $rcek = $cek->get_result();
    if ($rcek && $rcek->num_rows > 0) $allowed = true;
    $cek->close();
}

// fallback class_members kalau dipakai
if (!$allowed) {
    $cek2 = $db->prepare("SELECT 1 FROM class_members WHERE class_id = ? AND user_id = ? LIMIT 1");
    if ($cek2) {
        $cek2->bind_param("ii", $classId, $studentId);
        $cek2->execute();
        $rcek2 = $cek2->get_result();
        if ($rcek2 && $rcek2->num_rows > 0) $allowed = true;
        $cek2->close();
    }
}

if (!$allowed) {
    echo "Anda tidak terdaftar di kelas untuk tugas ini.";
    exit;
}

// --- Cek apakah sudah pernah submit (untuk UPDATE nanti) ---
$existingSub = null;
$ss = $db->prepare("SELECT * FROM submissions WHERE assignment_id = ? AND student_id = ? LIMIT 1");
if ($ss) {
    $ss->bind_param("ii", $assignmentId, $studentId);
    $ss->execute();
    $rsub = $ss->get_result();
    $existingSub = $rsub ? $rsub->fetch_assoc() : null;
    $ss->close();
}

// --- Proses POST ---
$error       = '';
$uploadError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $catatan   = trim($_POST['catatan'] ?? '');
    $linkDrive = trim($_POST['link_drive'] ?? '');

    // Upload file (opsional)
    $newFileId = null;
    if (isset($_FILES['answer_file']) && $_FILES['answer_file']['error'] === UPLOAD_ERR_OK) {
        if (function_exists('uploadFile')) {
            $up = uploadFile($_FILES['answer_file'], 'submissions/');
            if (!empty($up) && !empty($up['success']) && !empty($up['file_id'])) {
                $newFileId = (int) $up['file_id'];

                // kalau sebelumnya sudah punya file, hapus fisiknya
                if (!empty($existingSub['file_id'])) {
                    deleteFileById((int) $existingSub['file_id']);
                }
            } else {
                $uploadError = $up['message'] ?? ($up['msg'] ?? 'Gagal upload file.');
            }
        } else {
            $uploadError = 'Fungsi uploadFile() tidak ditemukan.';
        }
    }

    // minimal ada salah satu
    if ($catatan === '' && $linkDrive === '' && $newFileId === null && empty($existingSub['file_id'])) {
        $error = 'Minimal isi catatan, link, atau upload file.';
    }

    if (empty($error)) {
        // jika belum pernah submit -> INSERT
        if ($existingSub === null) {
            $sql = "
                INSERT INTO submissions
                    (assignment_id, student_id, file_id, link_drive, catatan, submitted_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ";
            $stmtIns = $db->prepare($sql);
            if (!$stmtIns) {
                $error = 'Gagal menyiapkan penyimpanan jawaban: ' . $db->error;
            } else {
                // file_id boleh null
                if ($newFileId !== null) {
                    $fid = $newFileId;
                } else {
                    $fid = null;
                }
                $stmtIns->bind_param("iiiss", $assignmentId, $studentId, $fid, $linkDrive, $catatan);
                if (!$stmtIns->execute()) {
                    $error = 'Gagal menyimpan jawaban: ' . $stmtIns->error;
                }
                $submissionId = $db->insert_id;
                $stmtIns->close();
            }
        } else {
            // sudah ada -> UPDATE (overwrite)
            $sql = "
                UPDATE submissions
                SET file_id      = ?,
                    link_drive   = ?,
                    catatan      = ?,
                    submitted_at = NOW()
                WHERE assignment_id = ? AND student_id = ?
                LIMIT 1
            ";
            $stmtUp = $db->prepare($sql);
            if (!$stmtUp) {
                $error = 'Gagal menyiapkan update jawaban: ' . $db->error;
            } else {
                // kalau tidak upload baru, pakai file_id lama
                $fid = $newFileId !== null ? $newFileId : (int) ($existingSub['file_id'] ?? 0);
                // jika benar-benar tidak ada file, pakai NULL
                if ($fid === 0) $fid = null;

                $stmtUp->bind_param("issii", $fid, $linkDrive, $catatan, $assignmentId, $studentId);
                if (!$stmtUp->execute()) {
                    $error = 'Gagal mengupdate jawaban: ' . $stmtUp->error;
                }
                $submissionId = (int) $existingSub['id'];
                $stmtUp->close();
            }
        }

        if (empty($error)) {
            // Notifikasi ke guru (kalau fungsi tersedia)
            if (function_exists('createNotification') && !empty($assignment['created_by'])) {
                $title = 'Pengumpulan Tugas: ' . ($assignment['judul'] ?? '');
                $msg   = 'Siswa mengumpulkan tugas untuk kelas ' .
                         trim(($assignment['level'] ?? '') . ' ' . ($assignment['jurusan'] ?? '')) . '.';
                $link  = rtrim(BASE_URL, '/\\') . '/assignments/submission_view.php?id=' . $submissionId;
                createNotification((int) $assignment['created_by'], $title, $msg, $link);
            }

            // kembali ke halaman detail tugas
            header('Location: ' . rtrim(BASE_URL, '/\\') . '/assignments/view.php?id=' . $assignmentId);
            exit;
        }
    }
}

// --- TAMPILAN FORM ---
$pageTitle = 'Kumpulkan Tugas: ' . ($assignment['judul'] ?? '');
include __DIR__ . '/../inc/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Kumpulkan Tugas</h1>
        <p>
            <strong><?php echo sanitize($assignment['judul']); ?></strong><br>
            Mapel: <?php echo sanitize($assignment['nama_mapel']); ?> —
            Kelas: <?php echo sanitize(($assignment['level'] ?? '') . ' ' . ($assignment['jurusan'] ?? '')); ?>
        </p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo sanitize($error); ?></div>
    <?php endif; ?>
    <?php if (!empty($uploadError)): ?>
        <div class="alert alert-warning"><?php echo sanitize($uploadError); ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="assignment_id" value="<?php echo (int) $assignmentId; ?>">

            <div class="form-group">
                <label>Catatan / Jawaban Teks</label>
                <textarea name="catatan" rows="6" style="width:100%;"
                          placeholder="Tulis jawaban di sini..."><?php
                    echo isset($_POST['catatan'])
                        ? sanitize($_POST['catatan'])
                        : sanitize($existingSub['catatan'] ?? '');
                ?></textarea>
            </div>

            <div class="form-group">
                <label>Link Drive / Video (opsional)</label>
                <input type="url"
                       name="link_drive"
                       style="width:100%;"
                       placeholder="https://drive.google.com/... atau https://youtube.com/..."
                       value="<?php
                           echo isset($_POST['link_drive'])
                               ? sanitize($_POST['link_drive'])
                               : sanitize($existingSub['link_drive'] ?? '');
                       ?>">
            </div>

            <div class="form-group">
                <label>Upload File (opsional)</label>
                <input type="file" name="answer_file"
                       accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip">
                <?php if (defined('MAX_FILE_SIZE')): ?>
                    <small>Maksimal <?php echo (MAX_FILE_SIZE/1024/1024); ?> MB per file.</small>
                <?php endif; ?>
                <?php if (!empty($existingSub['file_id'])): ?>
                    <div style="margin-top:6px;font-size:13px;color:#555;">
                        File sebelumnya sudah ada. Jika Anda upload file baru, file lama akan diganti.
                    </div>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary">Kirim Jawaban</button>
            <a href="<?php echo rtrim(BASE_URL, '/\\') . '/assignments/view.php?id=' . (int) $assignmentId; ?>"
               class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
