<?php
// materials/edit.php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireRole(['guru']);

$db     = getDB();
$userId = (int)($_SESSION['user_id'] ?? 0);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . BASE_URL . '/materials/list.php');
    exit;
}

// Ambil materi + mapel, pastikan milik guru ini
$stmt = $db->prepare("
    SELECT m.*, s.nama_mapel, s.class_id
    FROM materials m
    LEFT JOIN subjects s ON m.subject_id = s.id
    WHERE m.id = ? AND m.created_by = ?
    LIMIT 1
");
if (!$stmt) {
    echo "DB error: " . sanitize($db->error);
    exit;
}
$stmt->bind_param("ii", $id, $userId);
$stmt->execute();
$res = $stmt->get_result();
$m   = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$m) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

// Ambil info file dari tabel file_uploads (bukan 'files')
$currentFile = null;
if (!empty($m['file_id']) && is_numeric($m['file_id'])) {
    $fid = (int)$m['file_id'];
    $q   = $db->prepare("
        SELECT id, original_name, stored_name, file_path, mime_type, file_size
        FROM file_uploads
        WHERE id = ?
        LIMIT 1
    ");
    if ($q) {
        $q->bind_param("i", $fid);
        $q->execute();
        $rf   = $q->get_result();
        $rowF = $rf ? $rf->fetch_assoc() : null;
        $q->close();

        if ($rowF) {
            $currentFile = $rowF; // id, original_name, stored_name, file_path, mime_type, file_size
        }
    }
}

$error   = '';
$success = '';

/**
 * Simpan file ke /uploads/materials dan catat ke file_uploads.
 * Return: ['success' => bool, 'file_id' => int|null, 'message' => string]
 */
function handleMaterialUpload(array $file, int $uploaderId, mysqli $db): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [
            'success' => false,
            'file_id' => null,
            'message' => 'Error upload file (code ' . (int)$file['error'] . ').'
        ];
    }

    $uploadsRoot = realpath(__DIR__ . '/../uploads');
    if ($uploadsRoot === false) {
        // coba buat folder uploads kalau belum ada
        $base = __DIR__ . '/../uploads';
        if (!is_dir($base) && !mkdir($base, 0775, true)) {
            return [
                'success' => false,
                'file_id' => null,
                'message' => 'Folder uploads tidak tersedia.'
            ];
        }
        $uploadsRoot = realpath($base);
        if ($uploadsRoot === false) {
            return [
                'success' => false,
                'file_id' => null,
                'message' => 'Gagal inisialisasi folder uploads.'
            ];
        }
    }

    $targetDir = $uploadsRoot . DIRECTORY_SEPARATOR . 'materials';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true)) {
        return [
            'success' => false,
            'file_id' => null,
            'message' => 'Gagal membuat folder materials.'
        ];
    }

    $originalName = $file['name'] ?? 'file';
    $originalName = trim($originalName);

    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExt = ['pdf','doc','docx','jpg','jpeg','png','zip','webp'];
    if ($ext && !in_array($ext, $allowedExt, true)) {
        return [
            'success' => false,
            'file_id' => null,
            'message' => 'Tipe file tidak diizinkan.'
        ];
    }

    // nama file random yang rapi, mirip pola di DB
    $rand       = bin2hex(random_bytes(8));
    $safeBase   = preg_replace('/[^A-Za-z0-9_\-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
    $storedName = $rand . '_' . $safeBase . ($ext ? '.' . $ext : '');

    $relativePath = 'materials/' . $storedName; // disimpan ke kolom file_path
    $fullPath     = $targetDir . DIRECTORY_SEPARATOR . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        return [
            'success' => false,
            'file_id' => null,
            'message' => 'Gagal memindahkan file upload.'
        ];
    }

    $mime     = $file['type'] ?? '';
    $fileSize = (int)($file['size'] ?? 0);

    $stmt = $db->prepare("
        INSERT INTO file_uploads (original_name, stored_name, file_path, mime_type, file_size, uploader_id)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        return [
            'success' => false,
            'file_id' => null,
            'message' => 'DB error saat menyimpan file.'
        ];
    }
    $stmt->bind_param("sssisi", $originalName, $storedName, $relativePath, $mime, $fileSize, $uploaderId);
    if (!$stmt->execute()) {
        $msg = 'DB error: ' . $stmt->error;
        $stmt->close();
        return [
            'success' => false,
            'file_id' => null,
            'message' => $msg
        ];
    }
    $newId = (int)$stmt->insert_id;
    $stmt->close();

    return [
        'success' => true,
        'file_id' => $newId,
        'message' => 'Upload berhasil.'
    ];
}

// POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token, 'edit_material_' . $id)) {
        $error = 'Token tidak valid.';
    } else {
        $action = $_POST['action'] ?? 'save';

        // ---- SIMPAN PERUBAHAN ----
        if ($action === 'save') {
            $judul  = trim($_POST['judul'] ?? '');
            $konten = trim($_POST['konten'] ?? '');

            $videoLink = trim($_POST['video_link'] ?? '');
            if ($videoLink === '' || $videoLink === '0') {
                $videoLink = null;   // benar-benar opsional
            }

            if ($judul === '') {
                $error = 'Judul wajib diisi.';
            }

            $newFileId   = null;
            $newFilePath = null;

            // Upload file baru jika dipilih (opsional)
            if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $up = handleMaterialUpload($_FILES['file'], $userId, $db);
                if (!$up['success']) {
                    $error = $up['message'];
                } else {
                    $newFileId   = (int)$up['file_id'];
                    // Sesuai handleMaterialUpload, relative path selalu "materials/xxx"
                    $newFilePath = 'materials/' . basename($_FILES['file']['name']); // placeholder, tapi kita sebenarnya tidak butuh kolom ini
                }
            }

            if ($error === '') {
                if ($newFileId) {
                    // update judul/konten/video + file_id
                    $stmt2 = $db->prepare("
                        UPDATE materials
                        SET judul = ?, konten = ?, video_link = ?, file_id = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt2->bind_param("sssii", $judul, $konten, $videoLink, $newFileId, $id);
                } else {
                    $stmt2 = $db->prepare("
                        UPDATE materials
                        SET judul = ?, konten = ?, video_link = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt2->bind_param("sssi", $judul, $konten, $videoLink, $id);
                }

                if ($stmt2->execute()) {
                    $stmt2->close();
                    header('Location: ' . BASE_URL . '/materials/edit.php?id=' . $id);
                    exit;
                } else {
                    $error = 'Gagal menyimpan: ' . sanitize($stmt2->error);
                    $stmt2->close();
                }
            }

        // ---- HAPUS FILE LAMPIRAN SAJA ----
        } elseif ($action === 'delete_file') {
            if (!$currentFile || empty($currentFile['id'])) {
                $error = 'Tidak ada file untuk dihapus.';
            } else {
                $fid = (int)$currentFile['id'];

                $db->begin_transaction();
                try {
                    // kosongkan pointer di materials
                    $u = $db->prepare("UPDATE materials SET file_id = NULL, file_path = NULL WHERE id = ?");
                    $u->bind_param("i", $id);
                    $u->execute();
                    $u->close();

                    // ambil path dulu
                    $fp = $currentFile['file_path'] ?? null;

                    // hapus row file_uploads
                    $del = $db->prepare("DELETE FROM file_uploads WHERE id = ? LIMIT 1");
                    $del->bind_param("i", $fid);
                    $del->execute();
                    $del->close();

                    // hapus file fisik
                    if (!empty($fp)) {
                        $uploadsRoot = realpath(__DIR__ . '/../uploads');
                        if ($uploadsRoot) {
                            $full = $uploadsRoot . DIRECTORY_SEPARATOR . str_replace(['../', '..\\'], '', $fp);
                            if (is_file($full) && is_readable($full)) {
                                @unlink($full);
                            }
                        }
                    }

                    $db->commit();
                    header('Location: ' . BASE_URL . '/materials/edit.php?id=' . $id);
                    exit;

                } catch (Exception $e) {
                    $db->rollback();
                    $error = 'Gagal menghapus file: ' . sanitize($e->getMessage());
                }
            }

        // ---- HAPUS MATERI + FILE-NYA ----
        } elseif ($action === 'delete_material') {
            $db->begin_transaction();
            try {
                // kalau ada file, hapus juga
                if ($currentFile && !empty($currentFile['id'])) {
                    $fid = (int)$currentFile['id'];
                    $fp  = $currentFile['file_path'] ?? null;

                    $delF = $db->prepare("DELETE FROM file_uploads WHERE id = ? LIMIT 1");
                    $delF->bind_param("i", $fid);
                    $delF->execute();
                    $delF->close();

                    if (!empty($fp)) {
                        $uploadsRoot = realpath(__DIR__ . '/../uploads');
                        if ($uploadsRoot) {
                            $full = $uploadsRoot . DIRECTORY_SEPARATOR . str_replace(['../', '..\\'], '', $fp);
                            if (is_file($full) && is_readable($full)) {
                                @unlink($full);
                            }
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

$pageTitle = 'Edit Materi';
$csrf      = generateCsrfToken('edit_material_' . $id);

include __DIR__ . '/../inc/header.php';
?>

<div class="container">
    <div class="page-header" style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h1>Edit Materi</h1>
            <div style="font-size:14px;color:#666;"><?php echo sanitize($m['nama_mapel'] ?? ''); ?></div>
        </div>

        <div style="text-align:right;">
            <a href="<?php echo BASE_URL; ?>/materials/view.php?id=<?php echo (int)$id; ?>"
               class="btn btn-secondary" style="margin-right:8px;">← Kembali</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo sanitize($success); ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf); ?>">

        <div class="card" style="padding:18px;">
            <div class="form-group">
                <label for="judul">Judul *</label>
                <input id="judul" name="judul" type="text" required
                       value="<?php echo sanitize($m['judul'] ?? ''); ?>"
                       style="width:100%; padding:8px;">
            </div>

            <div class="form-group">
                <label for="konten">Konten</label>
                <textarea id="konten" name="konten" rows="8" style="width:100%;"><?php echo sanitize($m['konten'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="video_link">Link Video (opsional)</label>
                <?php
                $videoValue = $m['video_link'] ?? '';
                if ($videoValue === '0') {
                    $videoValue = '';
                }
                ?>
                <input id="video_link" name="video_link" type="url"
                       placeholder="https://... (boleh dikosongkan jika tidak ada)"
                       value="<?php echo sanitize($videoValue); ?>"
                       style="width:100%; padding:8px;">
            </div>

            <div class="form-group">
                <label>File Terlampir Saat Ini</label>
                <div style="margin-bottom:8px;">
                    <?php if ($currentFile && !empty($currentFile['file_path'])): ?>
                        <?php
                        $relative = $currentFile['file_path']; // contoh: materials/xxx.jpeg
                        $serveInline = BASE_URL . '/serve_file.php?f=' . rawurlencode($relative) . '&mode=inline';
                        $serveAttach = BASE_URL . '/serve_file.php?f=' . rawurlencode($relative) . '&mode=attachment';
                        ?>
                        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                            <button type="button"
                                    onclick="window.open('<?php echo sanitize($serveInline); ?>','_blank','noopener')"
                                    class="btn btn-primary">
                                Lihat File
                            </button>

                            <a href="<?php echo sanitize($serveAttach); ?>"
                               class="btn" style="background:#f3f4f6;color:#111;margin-left:4px;" download>
                                Unduh
                            </a>

                            <button type="submit"
                                    name="action"
                                    value="delete_file"
                                    class="btn btn-danger"
                                    style="margin-left:8px;"
                                    onclick="return confirm('Yakin menghapus file terlampir?');">
                                Hapus File
                            </button>
                        </div>

                        <div style="margin-top:8px;">
                            <?php echo sanitize($currentFile['original_name'] ?? $currentFile['stored_name']); ?>
                        </div>

                        <?php
                        $ext     = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
                        $imgExts = ['jpg','jpeg','png','gif','webp','svg'];
                        if (in_array($ext, $imgExts, true)):
                        ?>
                            <div style="margin-top:8px;">
                                <img src="<?php echo sanitize($serveInline); ?>"
                                     alt=""
                                     style="max-width:320px;height:auto;border:1px solid #eee;padding:6px;border-radius:8px;cursor:pointer;"
                                     onclick="window.open('<?php echo sanitize($serveInline); ?>','_blank','noopener')">
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div>-</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="file">Unggah File Baru (opsional)</label>
                <input id="file" name="file" type="file"
                       accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip,.webp">
                <p style="font-size:13px;color:#666;margin-top:6px;">
                    Bagian ini opsional. Jika tidak memilih file, materi tetap tersimpan tanpa perubahan file.
                    Jika Anda unggah file baru, materi akan menunjuk ke file baru, sementara file lama tetap ada di storage.
                </p>
            </div>

            <div style="display:flex;gap:10px;margin-top:12px;align-items:center;flex-wrap:wrap;">
                <button type="submit"
                        class="btn btn-primary"
                        name="action"
                        value="save">
                    Simpan Perubahan
                </button>

                <!-- Tombol merah jelas -->
                <button type="submit"
                        class="btn btn-danger"
                        name="action"
                        value="delete_material"
                        style="background:#ef4444;border-color:#b91c1c;color:#fff;"
                        onclick="return confirm('Yakin menghapus materi ini secara permanen?');">
                    Hapus Materi
                </button>

                <a href="<?php echo BASE_URL; ?>/materials/list.php"
                   class="btn btn-secondary">
                    Batal
                </a>
            </div>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
