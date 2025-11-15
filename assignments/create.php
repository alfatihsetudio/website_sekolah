<?php
// assignments/create.php
// Guru membuat tugas untuk 1 kelas (bisa teks, lampiran, catatan suara, atau tautan video)

require_once __DIR__ . '/../inc/helpers.php'; // PASTIKAN path benar
require_once __DIR__ . '/../inc/auth.php';
requireRole(['guru']);


require_once __DIR__ . '/../inc/db.php';


$db = getDB();
$guruId = (int)($_SESSION['user_id'] ?? 0);

$pageTitle = "Buat Tugas Baru";
$error = '';
$success = '';

// Ambil daftar subjects yang diampu guru (beserta kelas terkait)
$stmt = $db->prepare("SELECT s.id AS subject_id, s.nama_mapel, s.class_id, c.nama_kelas
                      FROM subjects s
                      JOIN classes c ON s.class_id = c.id
                      WHERE s.guru_id = ?
                      ORDER BY c.nama_kelas, s.nama_mapel");
$stmt->bind_param("i", $guruId);
$stmt->execute();
$res = $stmt->get_result();
$subjects = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

// Ambil daftar kelas unik yang guru punya mapelnya (untuk dropdown target_class)
$kelasList = [];
foreach ($subjects as $s) {
    $kelasList[$s['class_id']] = $s['nama_kelas'];
}

// Process form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil input
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $targetClassId = (int)($_POST['target_class_id'] ?? 0);
    $judul = trim($_POST['judul'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $sessionInfo = trim($_POST['session_info'] ?? '');
    $videoLink = trim($_POST['video_link'] ?? '');
    $deadlineRaw = trim($_POST['deadline'] ?? '');

    // Basic validation
    if ($subjectId <= 0 || $targetClassId <= 0 || $judul === '') {
        $error = 'Pilih mata pelajaran, pilih kelas tujuan, dan isi judul tugas.';
    } else {
        // Verify that the subject belongs to this guru and that subject.class_id == targetClassId
        $stmt = $db->prepare("SELECT id, class_id FROM subjects WHERE id = ? AND guru_id = ? LIMIT 1");
        $stmt->bind_param("ii", $subjectId, $guruId);
        $stmt->execute();
        $r = $stmt->get_result();
        if (!$r || $r->num_rows === 0) {
            $error = 'Mata pelajaran tidak valid atau bukan milik Anda.';
        } else {
            $subRow = $r->fetch_assoc();
            if ((int)$subRow['class_id'] !== $targetClassId) {
                // Untuk keamanan, tolak jika subject tidak terkait ke kelas tujuan
                $error = 'Mata pelajaran ini tidak terkait dengan kelas yang dipilih.';
            }
        }
        $stmt->close();
    }

    // Parse deadline if provided (optional)
    $deadlineSql = 'NULL';
    if ($deadlineRaw !== '') {
        $dt = date('Y-m-d H:i:s', strtotime($deadlineRaw));
        if (!$dt) {
            $error = 'Format deadline tidak valid.';
        } else {
            $deadlineSql = "'" . $db->real_escape_string($dt) . "'";
        }
    }

    if (empty($error)) {
        // Prepare insertion into assignments
        $judulEsc = $db->real_escape_string($judul);
        $desEsc = $db->real_escape_string($deskripsi);
        $sessionEsc = $db->real_escape_string($sessionInfo);
        $videoEsc = $db->real_escape_string($videoLink);

        // Use prepared statement for insert (file_id null initially)
        $sql = "INSERT INTO assignments (subject_id, target_class_id, judul, deskripsi, file_id, deadline, created_by, created_at, session_info, updated_at)
                VALUES (?, ?, ?, ?, NULL, " . ($deadlineSql === 'NULL' ? "NULL" : "?") . ", ?, NOW(), ?, NOW())";

        // We will bind variables depending on whether deadline provided
        if ($deadlineSql === 'NULL') {
            $stmt = $db->prepare("INSERT INTO assignments (subject_id, target_class_id, judul, deskripsi, file_id, deadline, created_by, created_at, session_info, updated_at)
                                  VALUES (?, ?, ?, ?, NULL, NULL, ?, NOW(), ?, NOW())");
            if (!$stmt) {
                $error = 'Gagal menyiapkan statement (1).';
            } else {
                $stmt->bind_param("iissis", $subjectId, $targetClassId, $judulEsc, $desEsc, $guruId, $sessionEsc);
            }
        } else {
            $stmt = $db->prepare("INSERT INTO assignments (subject_id, target_class_id, judul, deskripsi, file_id, deadline, created_by, created_at, session_info, updated_at)
                                  VALUES (?, ?, ?, ?, NULL, ?, ?, NOW(), ?, NOW())");
            if (!$stmt) {
                $error = 'Gagal menyiapkan statement (2).';
            } else {
                $stmt->bind_param("iisssis", $subjectId, $targetClassId, $judulEsc, $desEsc, $dt, $guruId, $sessionEsc);
            }
        }

        if (empty($error)) {
            $execOk = $stmt->execute();
            if (!$execOk) {
                $error = 'Gagal menyimpan tugas: ' . $stmt->error;
                $stmt->close();
            } else {
                $assignmentId = $db->insert_id;
                $stmt->close();

                // Handle file uploads (multiple attachments)
                $uploadedFiles = []; // array of ['file_id'=>int, 'type'=>string]

                // FILE[] multiple attachments
                if (isset($_FILES['file']) && is_array($_FILES['file']['name'])) {
                    $fileCount = count($_FILES['file']['name']);
                    for ($i = 0; $i < $fileCount; $i++) {
                        if (isset($_FILES['file']['error'][$i]) && $_FILES['file']['error'][$i] === UPLOAD_ERR_OK) {
                            $single = [
                                'name' => $_FILES['file']['name'][$i],
                                'type' => $_FILES['file']['type'][$i],
                                'tmp_name' => $_FILES['file']['tmp_name'][$i],
                                'error' => $_FILES['file']['error'][$i],
                                'size' => $_FILES['file']['size'][$i],
                            ];
                            $up = uploadFile($single, 'assignments/');
                            if ($up['success']) {
                                $uploadedFiles[] = ['file_id' => $up['file_id'], 'type' => 'attachment'];
                            }
                        }
                    }
                }

                // Voice note (single file input name="voice")
                if (isset($_FILES['voice']) && isset($_FILES['voice']['error']) && $_FILES['voice']['error'] === UPLOAD_ERR_OK) {
                    $up = uploadFile($_FILES['voice'], 'assignments/voice/');
                    if ($up['success']) {
                        $uploadedFiles[] = ['file_id' => $up['file_id'], 'type' => 'voice'];
                    }
                }

                // If video link provided, already saved in assignments.video_link below
                if (!empty($videoEsc)) {
                    // update video_link column
                    $stmt2 = $db->prepare("UPDATE assignments SET video_link = ? WHERE id = ?");
                    if ($stmt2) {
                        $stmt2->bind_param("si", $videoEsc, $assignmentId);
                        $stmt2->execute();
                        $stmt2->close();
                    }
                }

                // Insert uploadedFiles into assignment_files table
                if (!empty($uploadedFiles)) {
                    $insStmt = $db->prepare("INSERT INTO assignment_files (assignment_id, file_id, file_type) VALUES (?, ?, ?)");
                    if ($insStmt) {
                        foreach ($uploadedFiles as $uf) {
                            $fid = (int)$uf['file_id'];
                            $ftype = $uf['type'];
                            $insStmt->bind_param("iis", $assignmentId, $fid, $ftype);
                            $insStmt->execute();
                        }
                        $insStmt->close();
                    }
                }

                // Create notifications for all students in target class
                $stmtStu = $db->prepare("SELECT cu.user_id FROM class_user cu WHERE cu.class_id = ?");
                $stmtStu->bind_param("i", $targetClassId);
                $stmtStu->execute();
                $resStu = $stmtStu->get_result();
                $titleNotif = "Tugas Baru: " . $judul;
                $msgNotif = "Guru mengirim tugas (" . ($sessionInfo ?: 'tanpa sesi') . ") untuk kelas " . ($kelasList[$targetClassId] ?? 'kelas') . ".";
                while ($row = $resStu->fetch_assoc()) {
                    createNotification($row['user_id'], $titleNotif, $msgNotif, '/web_MG/assignments/view.php?id=' . $assignmentId);
                }
                $stmtStu->close();

                // Redirect to view page of assignment
                header("Location: /web_MG/assignments/view.php?id=" . $assignmentId);
                exit;
            }
        }
    }
}

// Render form
include __DIR__ . '/../inc/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Buat Tugas Baru</h1>
        <a href="/web_MG/assignments/list.php" class="btn btn-secondary">← Kembali</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="subject_id">Mata Pelajaran *</label>
                <select id="subject_id" name="subject_id" required>
                    <option value="">Pilih Mata Pelajaran</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?php echo (int)$s['subject_id']; ?>"
                            data-class-id="<?php echo (int)$s['class_id']; ?>">
                            <?php echo sanitize($s['nama_kelas']) . ' — ' . sanitize($s['nama_mapel']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>Pilih mata pelajaran yang sesuai.</small>
            </div>

            <div class="form-group">
                <label for="target_class_id">Kelas Tujuan *</label>
                <select id="target_class_id" name="target_class_id" required>
                    <option value="">Pilih Kelas</option>
                    <?php foreach ($kelasList as $cid => $cname): ?>
                        <option value="<?php echo (int)$cid; ?>"><?php echo sanitize($cname); ?></option>
                    <?php endforeach; ?>
                </select>
                <small>Pilih kelas yang akan menerima tugas.</small>
            </div>

            <div class="form-group">
                <label for="judul">Judul Tugas *</label>
                <input type="text" id="judul" name="judul" required placeholder="Contoh: PR Halaman 10 soal 1-5">
            </div>

            <div class="form-group">
                <label for="deskripsi">Deskripsi / Instruksi</label>
                <textarea id="deskripsi" name="deskripsi" rows="5" placeholder="Petunjuk tugas..."></textarea>
            </div>

            <div class="form-group">
                <label for="session_info">Sesi / Pertemuan (contoh: Pertemuan Senin 1)</label>
                <input type="text" id="session_info" name="session_info" placeholder="Pertemuan Senin / Sesi 1">
            </div>

            <div class="form-group">
                <label for="deadline">Batas Waktu (opsional)</label>
                <input type="datetime-local" id="deadline" name="deadline">
            </div>

            <div class="form-group">
                <label>Upload Lampiran (foto / dokumen) — boleh banyak</label>
                <input type="file" name="file[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip">
                <small>Maks <?php echo (MAX_FILE_SIZE/1024/1024); ?> MB per berkas.</small>
            </div>

            <div class="form-group">
                <label>Catatan Suara (opsional)</label>
                <input type="file" name="voice" accept="audio/*">
                <small>Gunakan jika ingin memberikan instruksi suara.</small>
            </div>

            <div class="form-group">
                <label for="video_link">Video (link) — opsional</label>
                <input type="url" id="video_link" name="video_link" placeholder="https://youtube.com/...">
            </div>

            <button type="submit" class="btn btn-primary btn-block">Kirim Tugas ke Kelas</button>
        </form>
    </div>
</div>

<script>
// UX helper: ketika guru memilih mata pelajaran, auto pilih kelas tujuan sesuai subject.class_id
document.getElementById('subject_id').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const classId = opt.getAttribute('data-class-id');
    if (classId) {
        const target = document.getElementById('target_class_id');
        // set selected option if exists
        for (let i = 0; i < target.options.length; i++) {
            if (target.options[i].value === classId) {
                target.selectedIndex = i;
                break;
            }
        }
    }
});
</script>

<?php include __DIR__ . '/../inc/footer.php'; ?>
