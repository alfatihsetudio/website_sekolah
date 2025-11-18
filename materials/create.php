<?php
// materials/create.php
// Tambah / Kirim Materi (guru) - menyimpan file_id atau file_path, kirim notifikasi ke murid kelas.

/**
 * Requirements:
 * - inc/auth.php (requireRole)
 * - inc/db.php (getDB)
 * - inc/helpers.php (uploadFile, createNotification, generateCsrfToken, verifyCsrfToken, sanitize)
 * - serve_file.php exists for preview/download
 */

require_once __DIR__ . '/../inc/auth.php';
requireRole(['guru']);

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

$db = getDB();
$guruId = (int)($_SESSION['user_id'] ?? 0);

$error = '';
$success = '';

// load subjects taught by guru (with class info)
$stmt = $db->prepare("
    SELECT s.id, s.nama_mapel, s.class_id, c.nama_kelas
    FROM subjects s
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE s.guru_id = ?
    ORDER BY COALESCE(c.nama_kelas, ''), s.nama_mapel
");
$subjects = [];
if ($stmt) {
    $stmt->bind_param("i", $guruId);
    $stmt->execute();
    $res = $stmt->get_result();
    $subjects = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}

// helper log
function _log_material($txt) {
    $dir = __DIR__ . '/../storage';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    @file_put_contents($dir . '/material_upload_log.txt', "[".date('Y-m-d H:i:s')."] ".$txt."\n", FILE_APPEND | LOCK_EX);
}

// helper verify file_uploads record (if using file_id)
function _file_exists_in_uploads($db, $fileId) {
    $chk = $db->prepare("SELECT id FROM file_uploads WHERE id = ? LIMIT 1");
    if (!$chk) return false;
    $chk->bind_param("i", $fileId);
    $chk->execute();
    $r = $chk->get_result();
    $exists = ($r && $r->num_rows > 0);
    $chk->close();
    return $exists;
}

// Process POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($subjects)) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token, 'create_material')) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $judul = trim($_POST['judul'] ?? '');
        $konten = trim($_POST['konten'] ?? '');
        $videoLink = trim($_POST['video_link'] ?? '');
        $action = ($_POST['action'] ?? 'save'); // save | send

        if ($subjectId <= 0) {
            $error = 'Pilih mata pelajaran.';
        } elseif ($judul === '') {
            $error = 'Judul materi harus diisi.';
        } else {
            // verify subject belongs to guru
            $v = $db->prepare("SELECT id, class_id FROM subjects WHERE id = ? AND guru_id = ? LIMIT 1");
            if (!$v) {
                $error = 'DB error: '. $db->error;
            } else {
                $v->bind_param("ii", $subjectId, $guruId);
                $v->execute();
                $rv = $v->get_result();
                if (!$rv || $rv->num_rows === 0) {
                    $error = 'Mata pelajaran tidak valid atau bukan milik Anda.';
                } else {
                    $sub = $rv->fetch_assoc();
                    $subject_class_id = (int)($sub['class_id'] ?? 0);
                }
                $v->close();
            }
        }

        // handle upload: allow uploadFile() to return either file_id or filepath details
        $fileId = null;
        $filePath = null;
        $uploadError = null;
        if (empty($error) && isset($_FILES['file']) && isset($_FILES['file']['error']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            if (function_exists('uploadFile')) {
                // uploadFile should return associative array
                $up = uploadFile($_FILES['file'], 'materials/');
                if (!empty($up) && !empty($up['success'])) {
                    // prefer file_id if present
                    if (!empty($up['file_id'])) {
                        $fileId = (int)$up['file_id'];
                        // verify existence
                        if (! _file_exists_in_uploads($db, $fileId)) {
                            _log_material("upload returned file_id={$fileId} but file_uploads missing");
                            $fileId = null;
                            // fallback try filepath keys
                        }
                    }
                    if ($fileId === null) {
                        if (!empty($up['filepath'])) {
                            $filePath = $up['filepath'];
                        } elseif (!empty($up['filename'])) {
                            // try guess relative path
                            $filePath = 'materials/' . $up['filename'];
                        } elseif (!empty($up['stored_name'])) {
                            $filePath = 'materials/' . $up['stored_name'];
                        }
                    }
                } else {
                    $uploadError = $up['message'] ?? ($up['msg'] ?? json_encode($up));
                }
            } else {
                $uploadError = 'Fungsi uploadFile() tidak ditemukan.';
            }

            if (!empty($uploadError)) {
                _log_material("Upload failed for guru_id={$guruId} subject={$subjectId} : {$uploadError}");
            }
        }

        if (empty($error) && $konten === '' && $fileId === null && empty($filePath) && $videoLink === '') {
            $error = 'Minimal harus ada teks, file, atau link video.';
        }

        if (empty($error)) {
            // Save material (transaction)
            $db->begin_transaction();
            try {
                if ($fileId !== null) {
                    $stmtIns = $db->prepare("
                        INSERT INTO materials (subject_id, judul, konten, file_id, file_path, video_link, created_by, created_at, updated_at)
                        VALUES (?, ?, ?, ?, NULL, ?, ?, NOW(), NOW())
                    ");
                    if (!$stmtIns) throw new Exception('Prepare failed (with file_id): ' . $db->error);
                    $stmtIns->bind_param("issiis", $subjectId, $judul, $konten, $fileId, $videoLink, $guruId);
                } elseif (!empty($filePath)) {
                    // store file_path (relative or absolute as returned by uploadFile)
                    $stmtIns = $db->prepare("
                        INSERT INTO materials (subject_id, judul, konten, file_id, file_path, video_link, created_by, created_at, updated_at)
                        VALUES (?, ?, ?, NULL, ?, ?, ?, NOW(), NOW())
                    ");
                    if (!$stmtIns) throw new Exception('Prepare failed (with file_path): ' . $db->error);
                    $stmtIns->bind_param("isssis", $subjectId, $judul, $konten, $filePath, $videoLink, $guruId);
                } else {
                    $stmtIns = $db->prepare("
                        INSERT INTO materials (subject_id, judul, konten, file_id, file_path, video_link, created_by, created_at, updated_at)
                        VALUES (?, ?, ?, NULL, NULL, ?, ?, NOW(), NOW())
                    ");
                    if (!$stmtIns) throw new Exception('Prepare failed (no file): ' . $db->error);
                    $stmtIns->bind_param("issis", $subjectId, $judul, $konten, $videoLink, $guruId);
                }

                if (!$stmtIns->execute()) {
                    $err = $stmtIns->error;
                    $stmtIns->close();
                    throw new Exception('Gagal menyimpan materi: ' . $err);
                }
                $materialId = $db->insert_id;
                $stmtIns->close();

                // notify students if send
                if ($action === 'send' && !empty($subject_class_id)) {
                    $stmtStu = $db->prepare("SELECT cu.user_id FROM class_user cu WHERE cu.class_id = ?");
                    if ($stmtStu) {
                        $stmtStu->bind_param("i", $subject_class_id);
                        $stmtStu->execute();
                        $resStu = $stmtStu->get_result();
                        while ($row = $resStu->fetch_assoc()) {
                            $studentId = (int)$row['user_id'];
                            createNotification(
                                $studentId,
                                'Materi Baru: ' . $judul,
                                'Guru mengirim materi baru untuk kelas Anda: ' . $judul,
                                '/web_MG/materials/view.php?id=' . $materialId
                            );
                        }
                        $stmtStu->close();
                    } else {
                        _log_material("Notify prepare failed: " . $db->error);
                    }
                }

                $db->commit();

                if ($action === 'send') {
                    header('Location: ' . rtrim(BASE_URL, '/\\') . '/materials/list.php?sent=1');
                    exit;
                } else {
                    header('Location: ' . rtrim(BASE_URL, '/\\') . '/materials/list.php?saved=1');
                    exit;
                }

            } catch (Exception $e) {
                $db->rollback();
                $error = 'Terjadi kesalahan saat menyimpan materi: ' . $e->getMessage();
                _log_material("Exception saving material: ".$e->getMessage());
            }
        }
    }
}

// render page
$pageTitle = 'Tambah Materi Pembelajaran';
include __DIR__ . '/../inc/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Tambah Materi Pembelajaran</h1>
        <a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/materials/list.php" class="btn btn-secondary">← Kembali</a>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?php echo sanitize($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo sanitize($success); ?></div><?php endif; ?>

    <?php if (empty($subjects)): ?>
        <div class="card"><p>Anda belum memiliki mata pelajaran.</p></div>
    <?php else: ?>
        <form method="POST" enctype="multipart/form-data" id="materialForm">
            <?php $csrf = generateCsrfToken('create_material'); ?>
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf); ?>">
            <input type="hidden" name="action" id="form_action" value="save">

            <div class="card">
                <div class="form-group">
                    <label for="subject_id">Mata Pelajaran *</label>
                    <select id="subject_id" name="subject_id" required>
                        <option value="">Pilih Mata Pelajaran</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?php echo (int)$s['id']; ?>" <?php echo (isset($_POST['subject_id']) && $_POST['subject_id']==$s['id'])? 'selected':''; ?>>
                                <?php echo sanitize($s['nama_mapel'] . ' — ' . ($s['nama_kelas'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Judul Materi *</label>
                    <input type="text" name="judul" required value="<?php echo isset($_POST['judul'])? sanitize($_POST['judul']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>Upload File (opsional)</label>
                    <input type="file" name="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip">
                    <small>File akan disimpan dan (jika bisa) didaftarkan di file_uploads.</small>
                </div>

                <div class="form-group">
                    <label>Konten (teks)</label>
                    <textarea name="konten" rows="6" style="width:100%;"><?php echo isset($_POST['konten'])? sanitize($_POST['konten']):''; ?></textarea>
                </div>

                <div class="form-group">
                    <label>Link Video (opsional)</label>
                    <input type="url" name="video_link" value="<?php echo isset($_POST['video_link'])? sanitize($_POST['video_link']):''; ?>">
                </div>

                <div style="display:flex;gap:12px;">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('form_action').value='save'; document.getElementById('materialForm').submit();">💾 Simpan (Draft)</button>
                    <button type="button" class="btn btn-primary" onclick="document.getElementById('form_action').value='send'; document.getElementById('materialForm').submit();">🚀 Kirim ke Siswa</button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
