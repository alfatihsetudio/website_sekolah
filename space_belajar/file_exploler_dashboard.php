<?php
// space_belajar/file_exploler_dashboard.php

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireLogin();

$db      = getDB();
$baseUrl = rtrim(BASE_URL, '/\\');
$userId  = (int)($_SESSION['user_id'] ?? 0);

// -------------------- KONFIG --------------------
$uploadBaseDir  = __DIR__ . '/../uploads/study_files';
$maxUploadBytes = 10 * 1024 * 1024; // 10MB

if (!is_dir($uploadBaseDir)) {
    @mkdir($uploadBaseDir, 0755, true);
}

// folder aktif (0 = root)
$currentFolderId = isset($_GET['folder']) ? max(0, (int)$_GET['folder']) : 0;

// -------------------- HELPER: breadcrumb --------------------
function getFolderBreadcrumb(mysqli $db, int $userId, int $folderId): array
{
    if ($folderId <= 0) return [];

    $crumbs  = [];
    $current = $folderId;

    while ($current > 0) {
        $stmt = $db->prepare("
            SELECT id, parent_id, name
            FROM study_folders
            WHERE id = ? AND user_id = ?
            LIMIT 1
        ");
        if (!$stmt) break;
        $stmt->bind_param("ii", $current, $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$row) break;

        $crumbs[] = $row;
        $current  = (int)($row['parent_id'] ?? 0);
    }

    return array_reverse($crumbs);
}

// helper untuk label folder "A / B / C"
function getFolderFullPath(mysqli $db, int $userId, int $folderId): string
{
    $crumbs = getFolderBreadcrumb($db, $userId, $folderId);
    if (empty($crumbs)) return '';
    $names = array_map(function ($c) { return $c['name']; }, $crumbs);
    return implode(' / ', $names);
}

// -------------------- HELPER: hapus folder rekursif --------------------
function deleteFolderRecursive(mysqli $db, int $userId, int $folderId, string $uploadBaseDir)
{
    // hapus file di folder ini
    $stmtF = $db->prepare("
        SELECT id, stored_name
        FROM study_files
        WHERE user_id = ? AND folder_id = ?
    ");
    if ($stmtF) {
        $stmtF->bind_param("ii", $userId, $folderId);
        $stmtF->execute();
        $resF = $stmtF->get_result();
        while ($file = $resF->fetch_assoc()) {
            $path = $uploadBaseDir . '/' . $file['stored_name'];
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $stmtF->close();

        $stmtDelFiles = $db->prepare("
            DELETE FROM study_files
            WHERE user_id = ? AND folder_id = ?
        ");
        if ($stmtDelFiles) {
            $stmtDelFiles->bind_param("ii", $userId, $folderId);
            $stmtDelFiles->execute();
            $stmtDelFiles->close();
        }
    }

    // subfolder
    $stmtS = $db->prepare("
        SELECT id
        FROM study_folders
        WHERE user_id = ? AND parent_id = ?
    ");
    if ($stmtS) {
        $stmtS->bind_param("ii", $userId, $folderId);
        $stmtS->execute();
        $resS = $stmtS->get_result();
        while ($sub = $resS->fetch_assoc()) {
            deleteFolderRecursive($db, $userId, (int)$sub['id'], $uploadBaseDir);
        }
        $stmtS->close();
    }

    // hapus folder utama
    $stmtDel = $db->prepare("
        DELETE FROM study_folders
        WHERE user_id = ? AND id = ?
        LIMIT 1
    ");
    if ($stmtDel) {
        $stmtDel->bind_param("ii", $userId, $folderId);
        $stmtDel->execute();
        $stmtDel->close();
    }
}

// -------------------- HELPER: tambah folder ke ZIP --------------------
function addFolderToZip(
    mysqli $db,
    int $userId,
    int $folderId,
    ZipArchive $zip,
    string $uploadBaseDir,
    string $pathPrefix = ''
) {
    // file di folder ini
    $stmtF = $db->prepare("
        SELECT original_name, stored_name
        FROM study_files
        WHERE user_id = ? AND folder_id = ?
    ");
    if ($stmtF) {
        $stmtF->bind_param("ii", $userId, $folderId);
        $stmtF->execute();
        $resF = $stmtF->get_result();
        while ($file = $resF->fetch_assoc()) {
            $filePath = $uploadBaseDir . '/' . $file['stored_name'];
            if (is_file($filePath)) {
                $internal = $pathPrefix . ($file['original_name'] ?: $file['stored_name']);
                $zip->addFile($filePath, $internal);
            }
        }
        $stmtF->close();
    }

    // subfolder
    $stmtS = $db->prepare("
        SELECT id, name
        FROM study_folders
        WHERE user_id = ? AND parent_id = ?
    ");
    if ($stmtS) {
        $stmtS->bind_param("ii", $userId, $folderId);
        $stmtS->execute();
        $resS = $stmtS->get_result();
        while ($sub = $resS->fetch_assoc()) {
            $subPrefix = $pathPrefix . $sub['name'] . '/';
            addFolderToZip($db, $userId, (int)$sub['id'], $zip, $uploadBaseDir, $subPrefix);
        }
        $stmtS->close();
    }
}

// -------------------- AJAX PREVIEW FILE --------------------
if (isset($_GET['preview']) && ctype_digit($_GET['preview'])) {
    $fileId = (int)$_GET['preview'];

    $stmt = $db->prepare("
        SELECT id, original_name, stored_name, mime_type, description, created_at
        FROM study_files
        WHERE id = ? AND user_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $fileId, $userId);
    $stmt->execute();
    $res  = $stmt->get_result();
    $file = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$file) {
        http_response_code(404);
        echo '<div style="padding:12px;">File tidak ditemukan.</div>';
        exit;
    }

    // file di-serve lewat serve_file.php supaya aman dari 403
    $fileUrl = $baseUrl . '/serve_file.php?f=' . rawurlencode('study_files/' . $file['stored_name']);
    $fileUrlHtml = htmlspecialchars($fileUrl, ENT_QUOTES, 'UTF-8');

    $orig = htmlspecialchars($file['original_name'], ENT_QUOTES, 'UTF-8');
    $desc = nl2br(htmlspecialchars($file['description'] ?? '', ENT_QUOTES, 'UTF-8'));
    $mime = strtolower($file['mime_type'] ?? '');

    echo '<div style="padding:16px 18px;">';
    echo '<div style="font-weight:600;margin-bottom:4px;">' . $orig . '</div>';
    echo '<div style="font-size:0.85rem;color:#6b7280;margin-bottom:10px;">Dibuat: ' .
         htmlspecialchars($file['created_at'], ENT_QUOTES, 'UTF-8') . '</div>';

    if (strpos($mime, 'image/') === 0) {
        echo '<div style="max-height:70vh;overflow:auto;margin-bottom:10px;">';
        echo '<img src="' . $fileUrlHtml . '" style="max-width:100%;border-radius:6px;">';
        echo '</div>';
    } elseif (strpos($mime, 'text/') === 0) {
        $path = $uploadBaseDir . '/' . $file['stored_name'];
        if (is_file($path)) {
            $content = file_get_contents($path);
            $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
            echo '<pre style="max-height:60vh;overflow:auto;padding:10px;background:#f9fafb;border-radius:6px;border:1px solid #e5e7eb;font-size:0.85rem;">' .
                 $content . '</pre>';
        }
    }

    if ($desc !== '') {
        echo '<div style="font-size:0.85rem;color:#4b5563;margin-top:6px;">Catatan: ' . $desc . '</div>';
    }

    $downloadUrl = $fileUrlHtml . '&download=1';
    echo '<div style="margin-top:14px;">';
    echo '<a href="' . $downloadUrl . '" class="btn btn-primary" style="font-size:0.85rem;padding:6px 12px;">⬇️ Download</a>';
    echo '</div>';

    echo '</div>';
    exit;
}

// -------------------- HANDLE POST (aksi utama) --------------------
$flashError   = '';
$flashSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- KHUSUS download folder: langsung stream ZIP, tidak pakai redirect ---
    if ($action === 'download_zip') {
        if (!class_exists('ZipArchive')) {
            http_response_code(500);
            echo 'ZipArchive belum tersedia di server PHP.';
            exit;
        }

        $targetFolderId = isset($_POST['target_folder_id']) ? (int)$_POST['target_folder_id'] : 0;
        if ($targetFolderId <= 0) {
            http_response_code(400);
            echo 'Folder tidak valid.';
            exit;
        }

        // pastikan folder milik user
        $stmt = $db->prepare("
            SELECT id, name
            FROM study_folders
            WHERE id = ? AND user_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("ii", $targetFolderId, $userId);
        $stmt->execute();
        $res   = $stmt->get_result();
        $folder = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$folder) {
            http_response_code(404);
            echo 'Folder tidak ditemukan.';
            exit;
        }

        $zip = new ZipArchive();
        $tmpZip = tempnam(sys_get_temp_dir(), 'studzip_');
        if ($zip->open($tmpZip, ZipArchive::OVERWRITE) !== true) {
            http_response_code(500);
            echo 'Gagal membuat ZIP.';
            exit;
        }

        addFolderToZip($db, $userId, $targetFolderId, $zip, $uploadBaseDir, $folder['name'] . '/');
        $zip->close();

        $zipName = preg_replace('/[^a-zA-Z0-9_\-]+/', '_', $folder['name']);
        if ($zipName === '') $zipName = 'folder';
        $zipName .= '.zip';

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipName . '"');
        header('Content-Length: ' . filesize($tmpZip));
        readfile($tmpZip);
        @unlink($tmpZip);
        exit;
    }

    // --- aksi lain pakai flash + redirect (PRG) ---
    $redirectUrl = $baseUrl . '/space_belajar/file_exploler_dashboard.php';
    if ($currentFolderId > 0) {
        $redirectUrl .= '?folder=' . $currentFolderId;
    }

    try {
        if ($action === 'new_folder') {
            $name = trim($_POST['folder_name'] ?? '');
            if ($name === '') {
                $flashError = 'Nama folder tidak boleh kosong.';
            } else {
                $parentId = $currentFolderId ?: null;
                $stmt = $db->prepare("
                    INSERT INTO study_folders (user_id, parent_id, name, created_at, updated_at)
                    VALUES (?, ?, ?, NOW(), NOW())
                ");
                $stmt->bind_param("iis", $userId, $parentId, $name);
                $stmt->execute();
                $stmt->close();
                $flashSuccess = 'Folder "' . $name . '" berhasil dibuat.';
            }

        } elseif ($action === 'bulk_delete') {
            $foldersCsv = trim($_POST['selected_folders'] ?? '');
            $filesCsv   = trim($_POST['selected_files'] ?? '');

            if ($foldersCsv === '' && $filesCsv === '') {
                $flashError = 'Tidak ada item yang dipilih.';
            } else {
                if ($foldersCsv !== '') {
                    $ids = array_filter(array_map('intval', explode(',', $foldersCsv)));
                    foreach ($ids as $fid) {
                        if ($fid > 0) {
                            deleteFolderRecursive($db, $userId, $fid, $uploadBaseDir);
                        }
                    }
                }
                if ($filesCsv !== '') {
                    $ids = array_filter(array_map('intval', explode(',', $filesCsv)));
                    foreach ($ids as $id) {
                        if ($id > 0) {
                            $stmt = $db->prepare("
                                SELECT stored_name
                                FROM study_files
                                WHERE id = ? AND user_id = ?
                                LIMIT 1
                            ");
                            $stmt->bind_param("ii", $id, $userId);
                            $stmt->execute();
                            $res = $stmt->get_result();
                            $row = $res ? $res->fetch_assoc() : null;
                            $stmt->close();
                            if ($row) {
                                $path = $uploadBaseDir . '/' . $row['stored_name'];
                                if (is_file($path)) @unlink($path);
                                $stmtDel = $db->prepare("
                                    DELETE FROM study_files
                                    WHERE id = ? AND user_id = ?
                                    LIMIT 1
                                ");
                                $stmtDel->bind_param("ii", $id, $userId);
                                $stmtDel->execute();
                                $stmtDel->close();
                            }
                        }
                    }
                }
                $flashSuccess = 'Item yang dipilih sudah dihapus.';
            }

        } elseif ($action === 'move_files') {
            $filesCsv = trim($_POST['selected_files'] ?? '');
            $targetRaw = $_POST['target_folder_id'] ?? '';
            $targetFolderId = null;

            if ($filesCsv === '') {
                $flashError = 'Tidak ada file yang dipilih untuk dipindahkan.';
            } else {
                if ($targetRaw !== '' && $targetRaw !== '0') {
                    $tmpId = (int)$targetRaw;
                    if ($tmpId > 0) {
                        $stmt = $db->prepare("
                            SELECT id FROM study_folders
                            WHERE id = ? AND user_id = ?
                            LIMIT 1
                        ");
                        $stmt->bind_param("ii", $tmpId, $userId);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        $row = $res ? $res->fetch_assoc() : null;
                        $stmt->close();
                        if ($row) {
                            $targetFolderId = $tmpId;
                        } else {
                            $flashError = 'Folder tujuan tidak ditemukan.';
                        }
                    }
                }

                if ($flashError === '') {
                    $ids = array_filter(array_map('intval', explode(',', $filesCsv)));
                    foreach ($ids as $id) {
                        if ($id <= 0) continue;

                        if ($targetFolderId === null) {
                            // pindah ke root
                            $stmt = $db->prepare("
                                UPDATE study_files
                                SET folder_id = NULL
                                WHERE id = ? AND user_id = ?
                            ");
                            $stmt->bind_param("ii", $id, $userId);
                        } else {
                            $stmt = $db->prepare("
                                UPDATE study_files
                                SET folder_id = ?
                                WHERE id = ? AND user_id = ?
                            ");
                            $stmt->bind_param("iii", $targetFolderId, $id, $userId);
                        }
                        $stmt->execute();
                        $stmt->close();
                    }
                    $flashSuccess = 'File berhasil dipindahkan.';
                }
            }

        } elseif ($action === 'upload_photo') {
            if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
                $flashError = 'Gagal mengunggah foto.';
            } else {
                $file = $_FILES['photo'];

                if ($file['size'] > $maxUploadBytes) {
                    $flashError = 'Ukuran file melebihi batas 10MB.';
                } else {
                    $mime = mime_content_type($file['tmp_name']);
                    if (strpos($mime, 'image/') !== 0) {
                        $flashError = 'File harus berupa gambar (jpg, png, dll).';
                    } else {
                        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        if ($ext === '') $ext = 'jpg';
                        $stored = 'img_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                        $dest   = $uploadBaseDir . '/' . $stored;

                        if (!move_uploaded_file($file['tmp_name'], $dest)) {
                            $flashError = 'Gagal menyimpan file di server.';
                        } else {
                            $title    = trim($_POST['photo_title'] ?? '');
                            if ($title === '') {
                                $title = pathinfo($file['name'], PATHINFO_FILENAME);
                            }
                            $folderId = $currentFolderId ?: null;
                            $size     = (int)$file['size'];

                            $stmt = $db->prepare("
                                INSERT INTO study_files
                                    (user_id, folder_id, title, original_name, stored_name,
                                     mime_type, size_bytes, description, created_at)
                                VALUES
                                    (?, ?, ?, ?, ?, ?, ?, '', NOW())
                            ");
                            $stmt->bind_param(
                                "iissssi",
                                $userId,
                                $folderId,
                                $title,
                                $file['name'],
                                $stored,
                                $mime,
                                $size
                            );
                            $stmt->execute();
                            $stmt->close();

                            $flashSuccess = 'Foto berhasil disimpan.';
                        }
                    }
                }
            }

        } elseif ($action === 'create_note') {
            $title = trim($_POST['note_title'] ?? '');
            $body  = trim($_POST['note_body'] ?? '');

            if ($title === '' && $body === '') {
                $flashError = 'Catatan kosong tidak disimpan.';
            } else {
                $stored   = 'note_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.txt';
                $destPath = $uploadBaseDir . '/' . $stored;
                file_put_contents($destPath, $body);

                $folderId = $currentFolderId ?: null;
                $mime     = 'text/plain';
                $origName = $title !== '' ? $title . '.txt' : $stored;
                $size     = filesize($destPath) ?: 0;

                $stmt = $db->prepare("
                    INSERT INTO study_files
                        (user_id, folder_id, title, original_name, stored_name,
                         mime_type, size_bytes, description, created_at)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, '', NOW())
                ");
                $stmt->bind_param(
                    "iissssi",
                    $userId,
                    $folderId,
                    $title,
                    $origName,
                    $stored,
                    $mime,
                    $size
                );
                $stmt->execute();
                $stmt->close();

                $flashSuccess = 'Catatan baru tersimpan.';
            }
        }

    } catch (Throwable $e) {
        $flashError = 'Terjadi kesalahan: ' . $e->getMessage();
    }

    $_SESSION['fe_flash_error']   = $flashError;
    $_SESSION['fe_flash_success'] = $flashSuccess;

    header('Location: ' . $redirectUrl);
    exit;
}

// ambil flash setelah redirect
if (isset($_SESSION['fe_flash_error'])) {
    $flashError = $_SESSION['fe_flash_error'];
    unset($_SESSION['fe_flash_error']);
}
if (isset($_SESSION['fe_flash_success'])) {
    $flashSuccess = $_SESSION['fe_flash_success'];
    unset($_SESSION['fe_flash_success']);
}

// -------------------- DATA UNTUK TAMPILAN --------------------
$breadcrumb = getFolderBreadcrumb($db, $userId, $currentFolderId);

// folders level ini
$folders = [];
$stmtF = $db->prepare("
    SELECT id, name, created_at
    FROM study_folders
    WHERE user_id = ? AND " . ($currentFolderId > 0 ? "parent_id = ?" : "parent_id IS NULL") . "
    ORDER BY name ASC
");
if ($currentFolderId > 0) {
    $stmtF->bind_param("ii", $userId, $currentFolderId);
} else {
    $stmtF->bind_param("i", $userId);
}
$stmtF->execute();
$resF    = $stmtF->get_result();
$folders = $resF ? $resF->fetch_all(MYSQLI_ASSOC) : [];
$stmtF->close();

// files level ini
$files = [];
$stmtFiles = $db->prepare("
    SELECT id, title, original_name, stored_name, mime_type, size_bytes, created_at
    FROM study_files
    WHERE user_id = ? AND " . ($currentFolderId > 0 ? "folder_id = ?" : "folder_id IS NULL") . "
    ORDER BY created_at DESC
");
if ($currentFolderId > 0) {
    $stmtFiles->bind_param("ii", $userId, $currentFolderId);
} else {
    $stmtFiles->bind_param("i", $userId);
}
$stmtFiles->execute();
$resFiles = $stmtFiles->get_result();
$files    = $resFiles ? $resFiles->fetch_all(MYSQLI_ASSOC) : [];
$stmtFiles->close();

// semua folder user (untuk dropdown pindah)
$allFolders = [];
$stmtAll = $db->prepare("
    SELECT id
    FROM study_folders
    WHERE user_id = ?
");
$stmtAll->bind_param("i", $userId);
$stmtAll->execute();
$resAll = $stmtAll->get_result();
while ($row = $resAll->fetch_assoc()) {
    $label = getFolderFullPath($db, $userId, (int)$row['id']);
    if ($label === '') $label = 'Folder #' . (int)$row['id'];
    $allFolders[] = ['id' => (int)$row['id'], 'label' => $label];
}
$stmtAll->close();

// sort label
usort($allFolders, function ($a, $b) {
    return strcasecmp($a['label'], $b['label']);
});

// nama user
$currentUser = getCurrentUser();
$userName    = $currentUser['nama'] ?? ($currentUser['email'] ?? 'User');

$pageTitle = 'File Explorer';
include __DIR__ . '/../inc/header.php';
?>

<style>
.fe-wrapper{max-width:1100px;margin:0 auto;}
/* title bar putih */
.fe-titlebar{
    display:flex;align-items:center;justify-content:space-between;gap:8px;
    padding:8px 12px;margin-bottom:8px;
    border-radius:10px;
    background:#ffffff;
    border:1px solid #e5e7eb;
    color:#111827;
}
.fe-titlebar-left{display:flex;align-items:center;gap:8px;}
.fe-device-icon{font-size:1.1rem;}
/* breadcrumb */
.fe-breadcrumb-bar{
    margin:10px 0;display:flex;align-items:center;flex-wrap:wrap;gap:6px;font-size:0.9rem;
}
.fe-breadcrumb-label{color:#6b7280;margin-right:4px;}
.fe-crumb{
    padding:4px 9px;border-radius:999px;background:#f3f4f6;border:1px solid #e5e7eb;
    text-decoration:none;color:#111827;font-size:0.85rem;
}
.fe-crumb.active{background:#dbeafe;border-color:#bfdbfe;color:#1d4ed8;font-weight:600;}
.fe-crumb-divider{font-size:0.8rem;color:#9ca3af;}
/* toolbar */
.fe-toolbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;font-size:0.85rem;}
.fe-toolbar-left{display:flex;gap:6px;flex-wrap:wrap;}
.fe-btn-ghost{
    padding:4px 9px;border-radius:6px;border:1px solid #e5e7eb;background:#ffffff;
    cursor:pointer;font-size:0.85rem;
}
.fe-btn-ghost:hover{background:#f3f4f6;}
.fe-toolbar-right{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.fe-btn-main-danger{
    padding:5px 12px;border-radius:999px;
    border:1px solid #fecaca;background:#fee2e2;
    color:#b91c1c;font-size:0.85rem;font-weight:500;
    cursor:pointer;display:inline-flex;align-items:center;gap:6px;
}
.fe-btn-main-danger:hover{background:#fecaca;}
.fe-btn-pill{
    padding:5px 10px;border-radius:999px;
    border:1px solid #e5e7eb;background:#ffffff;
    cursor:pointer;font-size:0.85rem;
}
.fe-btn-pill:hover{background:#f3f4f6;}
.fe-btn-active{
    box-shadow:0 0 0 1px #2563eb inset;
}
/* main grid */
.fe-main{border-radius:12px;border:1px solid #e5e7eb;background:#ffffff;padding:10px;min-height:380px;}
.fe-main-hint{font-size:0.8rem;color:#6b7280;margin-bottom:6px;}
.fe-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;}
.fe-item{
    position:relative;
    border-radius:10px;padding:8px;border:1px solid transparent;background:#f9fafb;
    cursor:pointer;display:flex;flex-direction:column;align-items:flex-start;transition:.12s;
}
.fe-item:hover{border-color:#bfdbfe;background:#eff6ff;}
.fe-thumb{
    width:100%;border-radius:8px;background:#e5e7eb;margin-bottom:6px;
    overflow:hidden;display:flex;align-items:center;justify-content:center;
}
.fe-thumb img{display:block;width:100%;height:80px;object-fit:cover;}
.fe-item-icon{font-size:1.6rem;margin-bottom:6px;}
.fe-item-name{
    font-size:0.85rem;font-weight:500;color:#111827;margin-bottom:2px;
    max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.fe-item-meta{font-size:0.78rem;color:#6b7280;}
/* select mode */
.fe-select-checkbox{
    position:absolute;top:6px;left:6px;display:none;
}
.fe-item.select-mode .fe-select-checkbox{display:block;}
.fe-item.selected{border-color:#60a5fa;background:#dbeafe;}
/* modal preview */
.fe-modal-backdrop{
    position:fixed;inset:0;background:rgba(15,23,42,0.45);
    display:none;align-items:center;justify-content:center;z-index:50;
}
.fe-modal{
    background:#ffffff;border-radius:14px;max-width:720px;width:94%;
    max-height:85vh;overflow:auto;
    box-shadow:0 10px 40px rgba(15,23,42,0.45);
}
/* dynamic island */
.fe-dynamic{
    position:fixed;left:50%;bottom:18px;transform:translateX(-50%);
    background:#111827;color:#e5e7eb;border-radius:999px;
    padding:6px 10px;display:flex;align-items:center;gap:10px;
    box-shadow:0 8px 24px rgba(15,23,42,0.4);z-index:40;
}
.fe-dynamic-btn{
    width:34px;height:34px;border-radius:999px;border:none;background:#1f2937;
    color:#e5e7eb;display:flex;align-items:center;justify-content:center;
    font-size:1.1rem;cursor:pointer;transition:.12s;
}
.fe-dynamic-btn:hover{background:#4b5563;}
.fe-dynamic-label{font-size:0.8rem;color:#9ca3af;}
/* inline forms */
.fe-inline-form{
    margin-bottom:10px;padding:8px 10px;border-radius:8px;
    background:#f9fafb;border:1px solid #e5e7eb;font-size:0.85rem;
}
.fe-inline-form label{font-size:0.8rem;color:#6b7280;}
.fe-inline-form input[type="text"],
.fe-inline-form textarea{
    width:100%;box-sizing:border-box;margin-top:4px;padding:6px 8px;
    border-radius:6px;border:1px solid #d1d5db;font-size:0.85rem;
}
.fe-inline-form textarea{resize:vertical;min-height:70px;}
/* modal kecil (rename & move) */
.fe-rename-backdrop{
    position:fixed;inset:0;background:rgba(15,23,42,0.45);
    display:none;align-items:center;justify-content:center;z-index:60;
}
.fe-rename-modal{
    background:#ffffff;border-radius:14px;padding:14px 16px;width:320px;
    box-shadow:0 10px 30px rgba(15,23,42,0.45);font-size:0.9rem;
}
</style>

<div class="container">
    <div class="fe-wrapper">

        <!-- title bar -->
        <div class="fe-titlebar">
            <div class="fe-titlebar-left">
                <div class="fe-device-icon">💻</div>
                <div>
                    <div style="font-size:0.8rem;color:#6b7280;">File Explorer</div>
                    <div style="font-size:0.95rem;font-weight:500;"><?php echo sanitize($userName); ?></div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <a href="<?php echo $baseUrl; ?>/space_belajar/dashboard.php"
                   class="btn btn-secondary"
                   style="font-size:0.8rem;padding:4px 10px;">
                    ← Kembali ke ruang belajar
                </a>
                <div style="font-size:0.8rem;color:#6b7280;">Penyimpanan belajar pribadi</div>
            </div>
        </div>

        <!-- breadcrumb -->
        <div class="fe-breadcrumb-bar">
            <span class="fe-breadcrumb-label">Lokasi:</span>
            <a href="<?php echo $baseUrl; ?>/space_belajar/file_exploler_dashboard.php" class="fe-crumb">This PC</a>
            <span class="fe-crumb-divider">›</span>
            <a href="<?php echo $baseUrl; ?>/space_belajar/file_exploler_dashboard.php"
               class="fe-crumb <?php echo $currentFolderId === 0 ? 'active' : ''; ?>">Root</a>
            <?php foreach ($breadcrumb as $b): ?>
                <span class="fe-crumb-divider">›</span>
                <a href="<?php echo $baseUrl; ?>/space_belajar/file_exploler_dashboard.php?folder=<?php echo (int)$b['id']; ?>"
                   class="fe-crumb <?php echo $currentFolderId === (int)$b['id'] ? 'active' : ''; ?>">
                    <?php echo sanitize($b['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- toolbar -->
        <div class="fe-toolbar">
            <div class="fe-toolbar-left">
                <button type="button" class="fe-btn-ghost" id="btnShowNewFolderForm">+ Folder baru</button>
                <button type="button" class="fe-btn-ghost" id="btnShowNoteForm">+ Catatan</button>
            </div>
            <div class="fe-toolbar-right">
                <span style="color:#6b7280;font-size:0.8rem;">
                    <?php echo count($folders); ?> folder · <?php echo count($files); ?> file
                </span>
                <button type="button" class="fe-btn-main-danger" id="btnDeleteMode">
                    🗑 Hapus
                </button>
                <button type="button" class="fe-btn-pill" id="btnMoveMode">
                    ⇄ Pindahkan
                </button>
                <button type="button" class="fe-btn-pill" id="btnDownloadMode">
                    ⬇️ Download folder
                </button>
            </div>
        </div>

        <!-- form aksi bulk (hapus / pindah / download) -->
        <form id="bulkActionForm" method="post" style="display:none;">
            <input type="hidden" name="action" id="bulkActionField" value="">
            <input type="hidden" name="selected_folders" id="selectedFoldersInput">
            <input type="hidden" name="selected_files" id="selectedFilesInput">
            <input type="hidden" name="target_folder_id" id="targetFolderInput">
        </form>

        <!-- flash -->
        <?php if ($flashError): ?>
            <div class="alert alert-error" style="margin-bottom:8px;"><?php echo sanitize($flashError); ?></div>
        <?php endif; ?>
        <?php if ($flashSuccess): ?>
            <div class="alert alert-success" style="margin-bottom:8px;"><?php echo sanitize($flashSuccess); ?></div>
        <?php endif; ?>

        <!-- form folder baru -->
        <div id="newFolderForm" class="fe-inline-form" style="display:none;">
            <form method="post">
                <input type="hidden" name="action" value="new_folder">
                <label for="folder_name">Nama folder baru</label>
                <input type="text" id="folder_name" name="folder_name" placeholder="Misal: Matematika, Fisika, Catatan UTS">
                <div style="margin-top:6px;display:flex;gap:6px;">
                    <button type="submit" class="btn btn-primary" style="font-size:0.8rem;padding:4px 10px;">Simpan</button>
                    <button type="button" class="btn btn-secondary" style="font-size:0.8rem;padding:4px 10px;"
                            onclick="document.getElementById('newFolderForm').style.display='none';">
                        Batal
                    </button>
                </div>
            </form>
        </div>

        <!-- form catatan -->
        <div id="noteForm" class="fe-inline-form" style="display:none;">
            <form method="post">
                <input type="hidden" name="action" value="create_note">
                <label for="note_title">Judul catatan</label>
                <input type="text" id="note_title" name="note_title" placeholder="Contoh: Ringkasan Biologi Bab 1">
                <label for="note_body" style="margin-top:6px;display:block;">Isi catatan</label>
                <textarea id="note_body" name="note_body" placeholder="Tulis poin penting, rumus, dll."></textarea>
                <div style="margin-top:6px;display:flex;gap:6px;">
                    <button type="submit" class="btn btn-primary" style="font-size:0.8rem;padding:4px 10px;">Simpan catatan</button>
                    <button type="button" class="btn btn-secondary" style="font-size:0.8rem;padding:4px 10px;"
                            onclick="document.getElementById('noteForm').style.display='none';">
                        Batal
                    </button>
                </div>
            </form>
        </div>

        <!-- area utama -->
        <div class="fe-main">
            <div id="modeHint" class="fe-main-hint" style="display:none;"></div>

            <?php if (empty($folders) && empty($files)): ?>
                <p style="font-size:0.9rem;color:#6b7280;margin:6px 0;">
                    Belum ada folder atau file di lokasi ini. Gunakan tombol di bawah (dynamic island)
                    untuk menambah folder, mengunggah foto, atau membuat catatan.
                </p>
            <?php else: ?>
                <div class="fe-grid">
                    <!-- folder -->
                    <?php foreach ($folders as $f): ?>
                        <div class="fe-item" data-kind="folder"
                             data-id="<?php echo (int)$f['id']; ?>"
                             title="<?php echo htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="fe-select-checkbox">
                                <input type="checkbox" class="fe-item-select">
                            </div>
                            <div class="fe-item-icon">📁</div>
                            <div class="fe-item-name">
                                <?php echo sanitize($f['name']); ?>
                            </div>
                            <div class="fe-item-meta">
                                Folder · <?php echo sanitize(substr($f['created_at'],0,10)); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- file -->
                    <?php foreach ($files as $fi):
                        $mime  = strtolower($fi['mime_type'] ?? '');
                        $isImg = strpos($mime, 'image/') === 0;
                        $title = $fi['title'] ?: $fi['original_name'];
                        $sizeKb = $fi['size_bytes'] ? round($fi['size_bytes']/1024) . ' KB' : '';
                        $thumbUrl = $isImg
                            ? $baseUrl . '/serve_file.php?f=' . rawurlencode('study_files/' . $fi['stored_name'])
                            : '';
                        $thumbHtml = htmlspecialchars($thumbUrl, ENT_QUOTES, 'UTF-8');
                        ?>
                        <div class="fe-item" data-kind="file"
                             data-id="<?php echo (int)$fi['id']; ?>"
                             title="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="fe-select-checkbox">
                                <input type="checkbox" class="fe-item-select">
                            </div>

                            <?php if ($isImg): ?>
                                <div class="fe-thumb">
                                    <img src="<?php echo $thumbHtml; ?>" alt="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                            <?php else: ?>
                                <div class="fe-item-icon">📄</div>
                            <?php endif; ?>

                            <div class="fe-item-name">
                                <?php echo sanitize($title); ?>
                            </div>
                            <div class="fe-item-meta">
                                <?php echo $sizeKb; ?> <?php echo $sizeKb ? '· ' : ''; ?>
                                <?php echo sanitize(substr($fi['created_at'],0,10)); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- modal preview -->
<div id="previewBackdrop" class="fe-modal-backdrop">
    <div class="fe-modal" id="previewContent"></div>
</div>

<!-- modal rename foto -->
<div id="renameBackdrop" class="fe-rename-backdrop">
    <div class="fe-rename-modal">
        <div style="font-weight:600;margin-bottom:4px;">Nama file</div>
        <div style="font-size:0.8rem;color:#6b7280;margin-bottom:6px;">
            Opsional. Kosongkan jika ingin memakai nama asli.
        </div>
        <input type="text" id="renameInput" style="width:100%;padding:6px 8px;border-radius:6px;border:1px solid #d1d5db;font-size:0.85rem;">
        <div style="margin-top:10px;display:flex;justify-content:flex-end;gap:6px;">
            <button type="button" class="btn btn-secondary" id="btnRenameCancel" style="font-size:0.8rem;padding:4px 10px;">Batal</button>
            <button type="button" class="btn btn-primary" id="btnRenameOk" style="font-size:0.8rem;padding:4px 10px;">Simpan</button>
        </div>
    </div>
</div>

<!-- modal pindah file -->
<div id="moveBackdrop" class="fe-rename-backdrop">
    <div class="fe-rename-modal">
        <div style="font-weight:600;margin-bottom:4px;">Pindahkan file ke folder</div>
        <div style="font-size:0.8rem;color:#6b7280;margin-bottom:6px;">
            Pilih folder tujuan. Pilih "Ruang utama (Root)" untuk memindahkan ke halaman utama.
        </div>
        <select id="moveTargetSelect"
                style="width:100%;padding:6px 8px;border-radius:6px;border:1px solid #d1d5db;font-size:0.85rem;">
            <option value="">Ruang utama (Root)</option>
            <?php foreach ($allFolders as $af): ?>
                <option value="<?php echo (int)$af['id']; ?>">
                    <?php echo sanitize($af['label']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div style="margin-top:10px;display:flex;justify-content:flex-end;gap:6px;">
            <button type="button" class="btn btn-secondary" id="btnMoveCancel" style="font-size:0.8rem;padding:4px 10px;">Batal</button>
            <button type="button" class="btn btn-primary" id="btnMoveOk" style="font-size:0.8rem;padding:4px 10px;">Pindahkan</button>
        </div>
    </div>
</div>

<!-- dynamic island -->
<div class="fe-dynamic" id="dynamicIsland">
    <span class="fe-dynamic-label">Aksi cepat:</span>
    <button class="fe-dynamic-btn" id="diNewFolder" title="Folder baru">📁</button>
    <button class="fe-dynamic-btn" id="diCamera" title="Foto dari kamera">📷</button>
    <button class="fe-dynamic-btn" id="diUpload" title="Transfer file dari perangkat">⬆️</button>
    <button class="fe-dynamic-btn" id="diNote" title="Catatan teks">📝</button>
</div>

<!-- form upload foto/file (hidden) -->
<form id="photoForm" method="post" enctype="multipart/form-data" style="display:none;">
    <input type="hidden" name="action" value="upload_photo">
    <input type="hidden" name="photo_title" id="photo_title_hidden">
    <input type="file" name="photo" id="input_photo_camera" accept="image/*" capture="environment">
    <input type="file" name="photo_file" id="input_photo_file" accept="image/*">
</form>

<script>
// toggle inline forms
document.getElementById('btnShowNewFolderForm').onclick = function () {
    const el = document.getElementById('newFolderForm');
    el.style.display = (el.style.display === 'none' || !el.style.display) ? 'block' : 'none';
};
document.getElementById('btnShowNoteForm').onclick = function () {
    const el = document.getElementById('noteForm');
    el.style.display = (el.style.display === 'none' || !el.style.display) ? 'block' : 'none';
};

// dynamic island shortcuts
document.getElementById('diNewFolder').onclick = function () {
    document.getElementById('newFolderForm').style.display = 'block';
    document.getElementById('folder_name').focus();
};
document.getElementById('diNote').onclick = function () {
    document.getElementById('noteForm').style.display = 'block';
    document.getElementById('note_title').focus();
};

const photoForm   = document.getElementById('photoForm');
const camInput    = document.getElementById('input_photo_camera');
const fileInput   = document.getElementById('input_photo_file');
const renameBackdrop = document.getElementById('renameBackdrop');
const renameInput    = document.getElementById('renameInput');
let pendingSource = null; // 'camera' atau 'file'

document.getElementById('diCamera').onclick = function () {
    pendingSource = 'camera';
    camInput.value = '';
    camInput.click(); // di mobile (HTTPS) akan buka kamera
};

document.getElementById('diUpload').onclick = function () {
    pendingSource = 'file';
    fileInput.value = '';
    fileInput.click();
};

camInput.addEventListener('change', function () {
    if (!camInput.files || !camInput.files.length) return;
    showRenameModal(camInput.files[0].name);
});
fileInput.addEventListener('change', function () {
    if (!fileInput.files || !fileInput.files.length) return;
    showRenameModal(fileInput.files[0].name);
});

function showRenameModal(defaultName) {
    renameInput.value = defaultName.replace(/\.[^.]+$/, '');
    renameBackdrop.style.display = 'flex';
    renameInput.focus();
}
document.getElementById('btnRenameCancel').onclick = function () {
    renameBackdrop.style.display = 'none';
    camInput.value = '';
    fileInput.value = '';
    pendingSource = null;
};
document.getElementById('btnRenameOk').onclick = function () {
    const title = renameInput.value.trim();
    document.getElementById('photo_title_hidden').value = title;
    renameBackdrop.style.display = 'none';
    photoForm.submit();
};

// preview modal
function openPreview(fileId) {
    const backdrop = document.getElementById('previewBackdrop');
    const content  = document.getElementById('previewContent');
    backdrop.style.display = 'flex';
    content.innerHTML = '<div style="padding:20px;">Memuat...</div>';

    fetch('<?php echo $baseUrl; ?>/space_belajar/file_exploler_dashboard.php?preview=' + fileId)
        .then(r => r.text())
        .then(html => { content.innerHTML = html; })
        .catch(() => { content.innerHTML = '<div style="padding:20px;">Gagal memuat file.</div>'; });
}
document.getElementById('previewBackdrop').onclick = function (e) {
    if (e.target === this) this.style.display = 'none';
};

// mode pilih: none / delete / move / download
let feMode = 'none';
const modeHint = document.getElementById('modeHint');
const btnDeleteMode = document.getElementById('btnDeleteMode');
const btnMoveMode = document.getElementById('btnMoveMode');
const btnDownloadMode = document.getElementById('btnDownloadMode');
const bulkForm = document.getElementById('bulkActionForm');
const bulkActionField = document.getElementById('bulkActionField');
const selectedFoldersInput = document.getElementById('selectedFoldersInput');
const selectedFilesInput   = document.getElementById('selectedFilesInput');
const targetFolderInput    = document.getElementById('targetFolderInput');

let selectedFolders = [];
let selectedFiles   = [];

function updateSelectedHidden() {
    selectedFoldersInput.value = selectedFolders.join(',');
    selectedFilesInput.value   = selectedFiles.join(',');
}

function setMode(newMode) {
    feMode = newMode;
    document.querySelectorAll('.fe-item').forEach(function (item) {
        const cb = item.querySelector('.fe-item-select');
        item.classList.remove('select-mode', 'selected');
        if (cb) cb.checked = false;
    });
    selectedFolders = [];
    selectedFiles   = [];
    updateSelectedHidden();

    btnDeleteMode.classList.remove('fe-btn-active');
    btnMoveMode.classList.remove('fe-btn-active');
    btnDownloadMode.classList.remove('fe-btn-active');
    modeHint.style.display = 'none';

    if (newMode === 'none') return;

    let text = '';
    if (newMode === 'delete') {
        btnDeleteMode.classList.add('fe-btn-active');
        text = 'Mode hapus: pilih folder / file yang ingin dihapus, lalu klik tombol Hapus lagi.';
    } else if (newMode === 'move') {
        btnMoveMode.classList.add('fe-btn-active');
        text = 'Mode pindah: pilih file yang ingin dipindahkan, lalu klik tombol Pindahkan lagi.';
    } else if (newMode === 'download') {
        btnDownloadMode.classList.add('fe-btn-active');
        text = 'Mode download: pilih satu folder, lalu klik tombol Download folder lagi.';
    }

    modeHint.textContent = text;
    modeHint.style.display = 'block';

    document.querySelectorAll('.fe-item').forEach(function (item) {
        item.classList.add('select-mode');
    });
}

function exitMode() {
    setMode('none');
}

// tombol HAPUS (satu tombol saja)
btnDeleteMode.onclick = function () {
    if (feMode !== 'delete') {
        setMode('delete');
        return;
    }
    const total = selectedFolders.length + selectedFiles.length;
    if (!total) {
        exitMode();
        return;
    }
    if (!confirm('Hapus semua folder/file yang dipilih?')) return;

    bulkActionField.value = 'bulk_delete';
    bulkForm.submit();
};

// tombol PINDAHKAN
const moveBackdrop = document.getElementById('moveBackdrop');
const moveSelect   = document.getElementById('moveTargetSelect');
document.getElementById('btnMoveCancel').onclick = function () {
    moveBackdrop.style.display = 'none';
};
document.getElementById('btnMoveOk').onclick = function () {
    const target = moveSelect.value;
    targetFolderInput.value = target;
    bulkActionField.value = 'move_files';
    bulkForm.submit();
};

btnMoveMode.onclick = function () {
    if (feMode !== 'move') {
        setMode('move');
        return;
    }
    if (!selectedFiles.length) {
        exitMode();
        return;
    }
    moveBackdrop.style.display = 'flex';
};

// tombol DOWNLOAD FOLDER (ZIP)
btnDownloadMode.onclick = function () {
    if (feMode !== 'download') {
        setMode('download');
        return;
    }
    if (!selectedFolders.length) {
        alert('Pilih folder yang ingin diunduh.');
        return;
    }
    if (selectedFolders.length > 1) {
        alert('Untuk saat ini hanya bisa mengunduh satu folder sekaligus.');
        return;
    }
    const folderId = selectedFolders[0];
    targetFolderInput.value = folderId;
    bulkActionField.value = 'download_zip';
    bulkForm.submit();
};

// klik card
document.querySelectorAll('.fe-item').forEach(function (item) {
    const kind = item.getAttribute('data-kind');
    const id   = parseInt(item.getAttribute('data-id'), 10);
    const cb   = item.querySelector('.fe-item-select');

    item.addEventListener('click', function (e) {
        if (e.target.classList.contains('fe-item-select')) return;

        if (feMode === 'none') {
            if (kind === 'folder') {
                window.location.href = '<?php echo $baseUrl; ?>/space_belajar/file_exploler_dashboard.php?folder=' + id;
            } else if (kind === 'file') {
                openPreview(id);
            }
            return;
        }

        // mode pilih
        if (feMode === 'move' && kind === 'folder') {
            // di mode pindah kita hanya boleh pilih file
            return;
        }
        if (feMode === 'download' && kind === 'file') {
            // di mode download hanya pilih folder
            return;
        }

        if (cb) cb.checked = !cb.checked;
        item.classList.toggle('selected', cb && cb.checked);

        if (kind === 'folder') {
            if (cb && cb.checked) {
                if (!selectedFolders.includes(id)) selectedFolders.push(id);
            } else {
                selectedFolders = selectedFolders.filter(function (v) { return v !== id; });
            }
        } else if (kind === 'file') {
            if (cb && cb.checked) {
                if (!selectedFiles.includes(id)) selectedFiles.push(id);
            } else {
                selectedFiles = selectedFiles.filter(function (v) { return v !== id; });
            }
        }
        updateSelectedHidden();
    });
});
</script>

<?php include __DIR__ . '/../inc/footer.php'; ?>
