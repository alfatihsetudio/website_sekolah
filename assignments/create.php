<?php
// assignments/create.php
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
requireRole(['guru']);
require_once __DIR__ . '/../inc/db.php';

$db = getDB();
$guruId = (int)($_SESSION['user_id'] ?? 0);

$pageTitle = "Buat Tugas Baru";
$error = '';
$success = '';

// Ambil daftar subjects yang diampu guru (beserta kelas terkait)
$stmt = $db->prepare("
    SELECT s.id AS subject_id, s.nama_mapel, s.class_id, c.nama_kelas
    FROM subjects s
    JOIN classes c ON s.class_id = c.id
    WHERE s.guru_id = ?
    ORDER BY c.nama_kelas, s.nama_mapel
");
$subjects = [];
if ($stmt) {
    $stmt->bind_param("i", $guruId);
    $stmt->execute();
    $res = $stmt->get_result();
    $subjects = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}

// Buat daftar kelas unik dari subjects (untuk dropdown)
$kelasList = [];
foreach ($subjects as $s) {
    $cid = (int)$s['class_id'];
    if ($cid && !isset($kelasList[$cid])) $kelasList[$cid] = $s['nama_kelas'];
}

// Proses submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $targetClassId = (int)($_POST['target_class_id'] ?? 0);
    $judul = trim($_POST['judul'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $sessionInfo = trim($_POST['session_info'] ?? '');
    $videoLink = trim($_POST['video_link'] ?? '');
    $deadlineRaw = trim($_POST['deadline'] ?? '');

    // validasi dasar
    if ($subjectId <= 0 || $targetClassId <= 0 || $judul === '') {
        $error = 'Pilih mata pelajaran, pilih kelas tujuan, dan isi judul tugas.';
    } else {
        // verifikasi subject milik guru
        $stmt = $db->prepare("SELECT id, class_id FROM subjects WHERE id = ? AND guru_id = ? LIMIT 1");
        if (!$stmt) {
            $error = 'DB error: ' . $db->error;
        } else {
            $stmt->bind_param("ii", $subjectId, $guruId);
            $stmt->execute();
            $r = $stmt->get_result();
            if (!$r || $r->num_rows === 0) {
                $error = 'Mata pelajaran tidak valid atau bukan milik Anda.';
            } else {
                $sub = $r->fetch_assoc();
                if ((int)$sub['class_id'] !== $targetClassId) {
                    $error = 'Mata pelajaran ini tidak terkait dengan kelas yang dipilih.';
                }
            }
            $stmt->close();
        }
    }

    // parse deadline jika ada
    $deadline = null;
    if ($deadlineRaw !== '') {
        $t = strtotime($deadlineRaw);
        if ($t === false) {
            $error = 'Format deadline tidak valid.';
        } else {
            $deadline = date('Y-m-d H:i:s', $t);
        }
    }

    if (empty($error)) {
        // prepare insert dengan/ tanpa deadline
        if ($deadline === null) {
            $stmtIns = $db->prepare("
                INSERT INTO assignments
                (subject_id, target_class_id, judul, deskripsi, file_id, deadline, created_by, created_at, session_info, updated_at)
                VALUES (?, ?, ?, ?, NULL, NULL, ?, NOW(), ?, NOW())
            ");
            if ($stmtIns) $stmtIns->bind_param("iissis", $subjectId, $targetClassId, $judul, $deskripsi, $guruId, $sessionInfo);
        } else {
            $stmtIns = $db->prepare("
                INSERT INTO assignments
                (subject_id, target_class_id, judul, deskripsi, file_id, deadline, created_by, created_at, session_info, updated_at)
                VALUES (?, ?, ?, ?, NULL, ?, ?, NOW(), ?, NOW())
            ");
            if ($stmtIns) $stmtIns->bind_param("iisssis", $subjectId, $targetClassId, $judul, $deskripsi, $deadline, $guruId, $sessionInfo);
        }

        if (!$stmtIns) {
            $error = 'Gagal menyiapkan statement: ' . $db->error;
        } else {
            if (!$stmtIns->execute()) {
                $error = 'Gagal menyimpan tugas: ' . $stmtIns->error;
                $stmtIns->close();
            } else {
                $assignmentId = $db->insert_id;
                $stmtIns->close();

                // Handle uploads (menggunakan uploadFile() di helpers Anda)
                $uploadedFiles = [];
                if (isset($_FILES['file']) && is_array($_FILES['file']['name'])) {
                    $count = count($_FILES['file']['name']);
                    for ($i=0;$i<$count;$i++) {
                        if ($_FILES['file']['error'][$i] === UPLOAD_ERR_OK) {
                            $single = [
                                'name' => $_FILES['file']['name'][$i],
                                'type' => $_FILES['file']['type'][$i],
                                'tmp_name' => $_FILES['file']['tmp_name'][$i],
                                'error' => $_FILES['file']['error'][$i],
                                'size' => $_FILES['file']['size'][$i],
                            ];
                            $up = function_exists('uploadFile') ? uploadFile($single, 'assignments/') : ['success'=>false];
                            if (!empty($up['success']) && !empty($up['file_id'])) {
                                $uploadedFiles[] = ['file_id' => (int)$up['file_id'], 'type' => 'attachment'];
                            }
                        }
                    }
                }
                if (isset($_FILES['voice']) && isset($_FILES['voice']['error']) && $_FILES['voice']['error'] === UPLOAD_ERR_OK) {
                    $up = function_exists('uploadFile') ? uploadFile($_FILES['voice'], 'assignments/voice/') : ['success'=>false];
                    if (!empty($up['success']) && !empty($up['file_id'])) {
                        $uploadedFiles[] = ['file_id' => (int)$up['file_id'], 'type' => 'voice'];
                    }
                }

                // simpan video_link apabila ada
                if (!empty($videoLink)) {
                    $stmt2 = $db->prepare("UPDATE assignments SET video_link = ? WHERE id = ?");
                    if ($stmt2) {
                        $stmt2->bind_param("si", $videoLink, $assignmentId);
                        $stmt2->execute();
                        $stmt2->close();
                    }
                }

                // insert assignment_files
                if (!empty($uploadedFiles)) {
                    $insAf = $db->prepare("INSERT INTO assignment_files (assignment_id, file_id, file_type) VALUES (?, ?, ?)");
                    if ($insAf) {
                        foreach ($uploadedFiles as $uf) {
                            $fid = (int)$uf['file_id'];
                            $ft = $uf['type'];
                            $insAf->bind_param("iis", $assignmentId, $fid, $ft);
                            $insAf->execute();
                        }
                        $insAf->close();
                    }
                }

                // buat notifikasi untuk setiap murid di class_user
                $stmtStu = $db->prepare("SELECT cu.user_id FROM class_user cu WHERE cu.class_id = ?");
                if ($stmtStu) {
                    $stmtStu->bind_param("i", $targetClassId);
                    $stmtStu->execute();
                    $resStu = $stmtStu->get_result();
                    $title = "Tugas Baru: " . $judul;
                    $msg = "Guru mengirim tugas (" . ($sessionInfo ?: 'tanpa sesi') . ") untuk kelas " . ($kelasList[$targetClassId] ?? $targetClassId) . ".";
                    while ($rw = $resStu->fetch_assoc()) {
                        if (function_exists('createNotification')) {
                            createNotification($rw['user_id'], $title, $msg, '/web_MG/assignments/view.php?id=' . $assignmentId);
                        } else {
                            // fallback: insert ke notifications jika createNotification tidak ada
                            $insN = $db->prepare("INSERT INTO notifications (user_id, title, message, link, created_at) VALUES (?, ?, ?, ?, NOW())");
                            if ($insN) {
                                $link = '/web_MG/assignments/view.php?id=' . $assignmentId;
                                $insN->bind_param("isss", $rw['user_id'], $title, $msg, $link);
                                $insN->execute();
                                $insN->close();
                            }
                        }
                    }
                    $stmtStu->close();
                }

                // redirect ke halaman view
                header("Location: " . rtrim(BASE_URL, '/\\') . "/assignments/view.php?id=" . $assignmentId);
                exit;
            }
        }
    }
}

// render page
include __DIR__ . '/../inc/header.php';
?>
<div class="container">
    <div class="page-header">
        <h1>Buat Tugas Baru</h1>
        <a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/assignments/list.php" class="btn btn-secondary">← Kembali</a>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?php echo sanitize($error); ?></div><?php endif; ?>

    <div class="card">
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="subject_id">Mata Pelajaran *</label>
                <select id="subject_id" name="subject_id" required>
                    <option value="">Pilih Mata Pelajaran</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?php echo (int)$s['subject_id']; ?>" data-class-id="<?php echo (int)$s['class_id']; ?>">
                            <?php echo sanitize($s['nama_kelas']) . ' — ' . sanitize($s['nama_mapel']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="target_class_id">Kelas Tujuan *</label>
                <select id="target_class_id" name="target_class_id" required>
                    <option value="">Pilih Kelas</option>
                    <?php foreach ($kelasList as $cid => $cname): ?>
                        <option value="<?php echo (int)$cid; ?>"><?php echo sanitize($cname); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="judul">Judul Tugas *</label>
                <input type="text" id="judul" name="judul" required value="<?php echo isset($_POST['judul']) ? sanitize($_POST['judul']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="deskripsi">Deskripsi</label>
                <textarea id="deskripsi" name="deskripsi" rows="5"><?php echo isset($_POST['deskripsi']) ? sanitize($_POST['deskripsi']) : ''; ?></textarea>
            </div>

            <div class="form-group">
                <label for="session_info">Sesi / Pertemuan</label>
                <input type="text" id="session_info" name="session_info" value="<?php echo isset($_POST['session_info']) ? sanitize($_POST['session_info']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="deadline">Batas Waktu (opsional)</label>
                <input type="datetime-local" id="deadline" name="deadline" value="<?php echo isset($_POST['deadline']) ? sanitize($_POST['deadline']) : ''; ?>">
            </div>

            <div class="form-group">
                <label>Lampiran (boleh banyak)</label>
                <input type="file" name="file[]" multiple>
            </div>

            <div class="form-group">
                <label>Catatan suara (opsional)</label>
                <input type="file" name="voice" accept="audio/*">
            </div>

            <div class="form-group">
                <label for="video_link">Video (link) — opsional</label>
                <input type="url" id="video_link" name="video_link" value="<?php echo isset($_POST['video_link']) ? sanitize($_POST['video_link']) : ''; ?>">
            </div>

            <button class="btn btn-primary" type="submit">Kirim Tugas ke Kelas</button>
        </form>
    </div>
</div>

<script>
// saat pilih subject -> set target_class_id otomatis
const subj = document.getElementById('subject_id');
if (subj) {
    subj.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        const classId = opt.getAttribute('data-class-id');
        if (classId) {
            const tgt = document.getElementById('target_class_id');
            for (let i=0;i<tgt.options.length;i++){
                if (tgt.options[i].value === classId) { tgt.selectedIndex = i; break; }
            }
        }
    });
}
</script>

<?php include __DIR__ . '/../inc/footer.php'; ?>
