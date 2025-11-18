<?php
// materials/edit.php (update: robust delete_file + delete_material moved to header)
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireRole(['guru']);

$db = getDB();
$userId = (int)($_SESSION['user_id'] ?? 0);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . BASE_URL . '/materials/list.php');
    exit;
}

// load material and verify ownership
$stmt = $db->prepare("SELECT m.*, s.nama_mapel, s.class_id FROM materials m LEFT JOIN subjects s ON m.subject_id = s.id WHERE m.id = ? AND m.created_by = ? LIMIT 1");
if (!$stmt) { echo "DB error: " . sanitize($db->error); exit; }
$stmt->bind_param("ii", $id, $userId);
$stmt->execute();
$res = $stmt->get_result();
$m = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$m) { http_response_code(403); echo "Forbidden"; exit; }

// detect files table & cols
function detectFilesTableAndCols($db) {
    $candidates = ['files','file_uploads','fileuploads','uploads'];
    foreach ($candidates as $t) {
        $r = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($t) . "'");
        if ($r && $r->num_rows > 0) {
            $cols = [];
            $res = $db->query("SHOW COLUMNS FROM `{$t}`");
            if ($res) {
                while ($row = $res->fetch_assoc()) $cols[] = $row['Field'];
            }
            return ['table' => $t, 'cols' => $cols];
        }
    }
    return null;
}
$filesMeta = detectFilesTableAndCols($db);

// load current file info (similar logic as before)
$currentFile = null;
if (!empty($m['file_id']) && is_numeric($m['file_id']) && $filesMeta) {
    $fid = (int)$m['file_id'];
    $t = $filesMeta['table'];
    $cols = $filesMeta['cols'];
    $selectCols = [];
    foreach (['original_name','filename','file_name','name'] as $c) if (in_array($c,$cols,true)) $selectCols[] = $c;
    foreach (['path','filepath','file_path','url'] as $c) if (in_array($c,$cols,true)) $selectCols[] = $c;
    if (empty($selectCols)) $selectCols = ['*'];
    $sel = implode(',', $selectCols);
    $q = $db->prepare("SELECT {$sel} FROM `{$t}` WHERE id = ? LIMIT 1");
    if ($q) {
        $q->bind_param("i", $fid);
        $q->execute();
        $rf = $q->get_result();
        $rrow = $rf ? $rf->fetch_assoc() : null;
        $q->close();
        if ($rrow) {
            $original = null; $physical = null; $path = null;
            foreach (['original_name','filename','file_name','name'] as $c) {
                if (isset($rrow[$c]) && trim($rrow[$c]) !== '') {
                    $original = $rrow[$c];
                    if ($physical === null && preg_match('/^[0-9a-f]{6,}_/i', $rrow[$c])) $physical = $rrow[$c];
                }
            }
            foreach (['path','filepath','file_path','url'] as $c) {
                if (isset($rrow[$c]) && trim($rrow[$c]) !== '') {
                    $path = $rrow[$c];
                    $bn = basename($rrow[$c]);
                    if ($physical === null && $bn !== '') $physical = $bn;
                }
            }
            if ($physical === null) {
                foreach (['filename','file_name','name'] as $c) {
                    if (isset($rrow[$c]) && trim($rrow[$c]) !== '') { $physical = $rrow[$c]; break; }
                }
            }
            $currentFile = [
                'original_name' => $original ?? ('file_'.$fid),
                'physical' => $physical ?? null,
                'path' => $path ?? null,
                'raw' => $rrow,
                'id' => $fid
            ];
        }
    }
}

// ACTIONS: save, delete_file, delete_material
$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token, 'edit_material_' . $id)) {
        $error = 'Token tidak valid.';
    } else {
        $action = $_POST['action'] ?? 'save';
        if ($action === 'save') {
            $judul = trim($_POST['judul'] ?? '');
            $konten = trim($_POST['konten'] ?? '');
            $videoLink = trim($_POST['video_link'] ?? '');
            if ($judul === '') $error = 'Judul wajib.';
            // handle upload similar to before
            $newFileId = null;
            if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['file']['error'] === UPLOAD_ERR_OK) {
                    $uploadResult = uploadFile($_FILES['file'], 'materials/');
                    if (!empty($uploadResult) && !empty($uploadResult['success']) && !empty($uploadResult['file_id'])) {
                        $newFileId = (int)$uploadResult['file_id'];
                    } else {
                        $error = $uploadResult['message'] ?? 'Gagal mengunggah file.';
                    }
                } else {
                    $error = 'Error upload file (code ' . (int)$_FILES['file']['error'] . ').';
                }
            }
            if (empty($error)) {
                if ($newFileId) {
                    $stmt2 = $db->prepare("UPDATE materials SET judul = ?, konten = ?, video_link = ?, file_id = ?, updated_at = NOW() WHERE id = ?");
                    $stmt2->bind_param("sssii", $judul, $konten, $videoLink, $newFileId, $id);
                } else {
                    $stmt2 = $db->prepare("UPDATE materials SET judul = ?, konten = ?, video_link = ?, updated_at = NOW() WHERE id = ?");
                    $stmt2->bind_param("sssi", $judul, $konten, $videoLink, $id);
                }
                if ($stmt2->execute()) {
                    $success = 'Perubahan tersimpan.';
                    if ($newFileId) {
                        header('Location: ' . BASE_URL . '/materials/edit.php?id=' . $id);
                        exit;
                    }
                } else {
                    $error = 'Gagal menyimpan: ' . sanitize($stmt2->error);
                }
                $stmt2->close();
            }
        } elseif ($action === 'delete_file') {
            // delete file logic: only if currentFile exists
            if (!$currentFile || empty($currentFile['id'])) {
                $error = 'Tidak ada file untuk dihapus.';
            } else {
                $fid = (int)$currentFile['id'];
                // try determine physical filename robustly
                $physical = $currentFile['physical'] ?? null;
                if (empty($physical) && !empty($currentFile['path'])) $physical = basename($currentFile['path']);
                // If still empty, attempt to fetch filename from DB raw fields
                if (empty($physical) && !empty($currentFile['raw'])) {
                    foreach (['filename','file_name','name','original_name'] as $c) {
                        if (!empty($currentFile['raw'][$c])) { $physical = $currentFile['raw'][$c]; break; }
                    }
                }

                // Start transaction
                $db->begin_transaction();
                $unlinkError = '';
                try {
                    // unset material pointer
                    $u = $db->prepare("UPDATE materials SET file_id = NULL WHERE id = ?");
                    $u->bind_param("i", $id); $u->execute(); $u->close();

                    // delete files table row if exists
                    if ($filesMeta) {
                        $t = $filesMeta['table'];
                        $del = $db->prepare("DELETE FROM `{$t}` WHERE id = ? LIMIT 1");
                        $del->bind_param("i", $fid);
                        $del->execute();
                        $del->close();
                    }

                    // attempt to unlink physical file if it lives in uploads/materials
                    if (!empty($physical)) {
                        $uploadsRoot = realpath(__DIR__ . '/../uploads');
                        if ($uploadsRoot) {
                            $full = $uploadsRoot . DIRECTORY_SEPARATOR . 'materials' . DIRECTORY_SEPARATOR . $physical;
                            // normalize
                            $full = str_replace(['..'.DIRECTORY_SEPARATOR], '', $full);
                            if (is_file($full) && is_readable($full)) {
                                // try unlink and record if failed
                                if (!@unlink($full)) {
                                    // unlink failed (permissions?) — don't abort DB deletion but inform user
                                    $unlinkError = 'Gagal menghapus file fisik dari storage (periksa izin).';
                                }
                            } else {
                                // file tidak ada fisiknya; that's ok
                            }
                        }
                    }

                    $db->commit();
                    $success = 'File berhasil dihapus.' . ($unlinkError ? ' Catatan: ' . $unlinkError : '');
                    // refresh page
                    header('Location: ' . BASE_URL . '/materials/edit.php?id=' . $id . ($unlinkError ? '&unlink_err=1' : '&deleted=1'));
                    exit;
                } catch (Exception $e) {
                    $db->rollback();
                    $error = 'Gagal menghapus file: ' . sanitize($e->getMessage());
                }
            }
        } elseif ($action === 'delete_material') {
            // delete entire material (moved to header) - same as before
            $db->begin_transaction();
            try {
                if ($currentFile && !empty($currentFile['id'])) {
                    $fid = (int)$currentFile['id'];
                    $physical = $currentFile['physical'] ?? null;
                    if (empty($physical) && !empty($currentFile['path'])) $physical = basename($currentFile['path']);
                    if ($filesMeta) {
                        $t = $filesMeta['table'];
                        $del = $db->prepare("DELETE FROM `{$t}` WHERE id = ? LIMIT 1");
                        $del->bind_param("i", $fid);
                        $del->execute();
                        $del->close();
                    }
                    if (!empty($physical)) {
                        $uploadsRoot = realpath(__DIR__ . '/../uploads');
                        if ($uploadsRoot) {
                            $full = $uploadsRoot . DIRECTORY_SEPARATOR . 'materials' . DIRECTORY_SEPARATOR . $physical;
                            if (is_file($full) && is_readable($full)) { @unlink($full); }
                        }
                    }
                }
                $d = $db->prepare("DELETE FROM materials WHERE id = ? AND created_by = ? LIMIT 1");
                $d->bind_param("ii", $id, $userId);
                $d->execute();
                $affected = $d->affected_rows;
                $d->close();
                if ($affected > 0) {
                    $db->commit();
                    header('Location: ' . BASE_URL . '/materials/list.php?deleted=1');
                    exit;
                } else {
                    $db->rollback();
                    $error = 'Gagal menghapus materi (mungkin bukan milik Anda).';
                }
            } catch (Exception $e) {
                $db->rollback();
                $error = 'Gagal menghapus materi: ' . sanitize($e->getMessage());
            }
        }
    }
}

// render page
$pageTitle = 'Edit Materi';
include __DIR__ . '/../inc/header.php';
?>

<div class="container">
    <div class="page-header" style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h1>Edit Materi</h1>
            <div style="font-size:14px;color:#666;"><?php echo sanitize($m['nama_mapel'] ?? ''); ?></div>
        </div>

        <!-- Delete material button moved here (header) -->
        <div style="text-align:right;">
            <a href="<?php echo BASE_URL; ?>/materials/view.php?id=<?php echo (int)$id; ?>" class="btn btn-secondary" style="margin-right:8px;">← Kembali</a>

            <form method="POST" style="display:inline;" onsubmit="return confirm('Yakin menghapus materi ini secara permanen?');">
                <?php $csrf = generateCsrfToken('edit_material_' . $id); ?>
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf); ?>">
                <input type="hidden" name="action" value="delete_material">
                <button type="submit" class="btn btn-outline-danger">Hapus Materi</button>
            </form>
        </div>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?php echo sanitize($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo sanitize($success); ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data" onsubmit="return confirmSave();">
        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf); ?>">
        <input type="hidden" name="action" value="save">

        <div class="card" style="padding:18px;">
            <div class="form-group">
                <label for="judul">Judul *</label>
                <input id="judul" name="judul" type="text" required value="<?php echo sanitize($m['judul'] ?? ''); ?>" style="width:100%; padding:8px;">
            </div>

            <div class="form-group">
                <label for="konten">Konten</label>
                <textarea id="konten" name="konten" rows="8" style="width:100%;"><?php echo sanitize($m['konten'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="video_link">Link Video (opsional)</label>
                <input id="video_link" name="video_link" type="url" placeholder="https://..." value="<?php echo sanitize($m['video_link'] ?? ''); ?>" style="width:100%; padding:8px;">
            </div>

            <div class="form-group">
                <label>File Terlampir Saat Ini</label>
                <div style="margin-bottom:8px;">
                    <?php if ($currentFile && (!empty($currentFile['physical']) || !empty($currentFile['path']))): ?>
                        <?php
                            $physical = $currentFile['physical'] ?? basename($currentFile['path'] ?? '');
                            $serveInline = BASE_URL . '/serve_file.php?f=' . rawurlencode('materials/' . $physical) . '&mode=inline';
                            $serveAttach = BASE_URL . '/serve_file.php?f=' . rawurlencode('materials/' . $physical) . '&mode=attachment';
                        ?>
                        <div style="display:flex; gap:8px; align-items:center;">
                            <button type="button" onclick="window.open('<?php echo sanitize($serveInline); ?>','_blank','noopener')" class="btn btn-primary">Lihat File</button>

                            <a href="<?php echo sanitize($serveAttach); ?>" class="btn" style="background:#f3f4f6;color:#111;margin-left:4px;" download>Unduh</a>

                            <!-- Hapus File (POST) -->
                            <form method="POST" style="display:inline;margin-left:8px;" onsubmit="return confirm('Yakin menghapus file terlampir? Ini akan mencoba menghapus file fisik.');">
                                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf); ?>">
                                <input type="hidden" name="action" value="delete_file">
                                <button type="submit" class="btn btn-danger">Hapus File</button>
                            </form>
                        </div>

                        <div style="margin-top:8px;"><?php echo sanitize($currentFile['original_name'] ?? $physical); ?></div>

                        <?php
                            $ext = strtolower(pathinfo($physical, PATHINFO_EXTENSION));
                            $imgExts = ['jpg','jpeg','png','gif','webp','svg'];
                            if (in_array($ext, $imgExts, true)):
                        ?>
                            <div style="margin-top:8px;">
                                <img src="<?php echo sanitize(BASE_URL . '/serve_file.php?f=' . rawurlencode('materials/' . $physical) . '&mode=inline'); ?>" alt="" style="max-width:320px;height:auto;border:1px solid #eee;padding:6px;border-radius:8px;cursor:pointer;" onclick="window.open('<?php echo sanitize(BASE_URL . '/serve_file.php?f=' . rawurlencode('materials/' . $physical) . '&mode=inline'); ?>','_blank','noopener')">
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div>-</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="file">Unggah File Baru (opsional)</label>
                <input id="file" name="file" type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip">
                <p style="font-size:13px;color:#666;margin-top:6px;">Jika Anda unggah file baru, file lama akan tetap ada di storage tetapi materi akan menunjuk ke file baru.</p>
            </div>

            <div style="display:flex;gap:10px;margin-top:12px;">
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                <a href="<?php echo BASE_URL; ?>/materials/list.php?id=<?php echo (int)$id; ?>" class="btn btn-secondary">Batal</a>
            </div>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>

<script>
function confirmSave() {
    return true;
}
</script>
