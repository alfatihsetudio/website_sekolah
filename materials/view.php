<?php
// materials/view.php
// Tampilkan materi + file (preview gambar / tombol download) via serve_file.php

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireLogin();

$db    = getDB();
$userId = (int)($_SESSION['user_id'] ?? 0);
$role   = $_SESSION['role'] ?? 'murid';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . rtrim(BASE_URL, '/\\') . '/materials/list.php');
    exit;
}

/**
 * Cek kolom nama di tabel users (name / nama / full_name / username)
 */
function detectUserNameColumn(mysqli $db)
{
    $possible = ['name','nama','full_name','username'];
    $res = $db->query("SHOW COLUMNS FROM users");
    if ($res) {
        while ($col = $res->fetch_assoc()) {
            $f = strtolower($col['Field']);
            if (in_array($f, $possible, true)) {
                return $col['Field'];
            }
        }
    }
    return null;
}

// ----- Ambil data materi (+ mapel + kelas + nama guru jika ada) -----

$userNameCol = detectUserNameColumn($db);

if ($userNameCol) {
    $sql = "
        SELECT m.*,
               s.nama_mapel,
               c.nama_kelas,
               s.class_id,
               s.class_level,
               s.jurusan,
               u.`{$userNameCol}` AS guru_name
        FROM materials m
        LEFT JOIN subjects s ON m.subject_id = s.id
        LEFT JOIN classes  c ON s.class_id = c.id
        LEFT JOIN users    u ON m.created_by = u.id
        WHERE m.id = ?
        LIMIT 1
    ";
} else {
    $sql = "
        SELECT m.*,
               s.nama_mapel,
               c.nama_kelas,
               s.class_id,
               s.class_level,
               s.jurusan,
               m.created_by AS guru_id
        FROM materials m
        LEFT JOIN subjects s ON m.subject_id = s.id
        LEFT JOIN classes  c ON s.class_id = c.id
        WHERE m.id = ?
        LIMIT 1
    ";
}

$stmt = $db->prepare($sql);
if (!$stmt) {
    echo "Gagal menyiapkan query: " . sanitize($db->error);
    exit;
}
$stmt->bind_param('i', $id);
$stmt->execute();
$res      = $stmt->get_result();
$material = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$material) {
    http_response_code(404);
    echo "Materi tidak ditemukan.";
    exit;
}

// ----- Access control: guru pembuat / murid di kelas terkait -----

$allowed = false;

if ($role === 'guru' && !empty($material['created_by']) && (int)$material['created_by'] === $userId) {
    $allowed = true;
} else {
    // murid (atau role lain) -> harus anggota class_id
    $classId = (int)($material['class_id'] ?? 0);
    if ($classId > 0) {
        // class_user
        $stmt2 = $db->prepare("SELECT 1 FROM class_user WHERE class_id = ? AND user_id = ? LIMIT 1");
        if ($stmt2) {
            $stmt2->bind_param('ii', $classId, $userId);
            $stmt2->execute();
            $r2 = $stmt2->get_result();
            if ($r2 && $r2->num_rows > 0) $allowed = true;
            $stmt2->close();
        }

        // fallback: class_members
        if (!$allowed) {
            $stmt3 = $db->prepare("SELECT 1 FROM class_members WHERE class_id = ? AND user_id = ? LIMIT 1");
            if ($stmt3) {
                $stmt3->bind_param('ii', $classId, $userId);
                $stmt3->execute();
                $r3 = $stmt3->get_result();
                if ($r3 && $r3->num_rows > 0) $allowed = true;
                $stmt3->close();
            }
        }
    }
}

if (!$allowed) {
    http_response_code(403);
    echo "Anda tidak memiliki hak untuk melihat materi ini.";
    exit;
}

// ----- Nama guru untuk ditampilkan -----

$guruDisplay = '-';

if (!empty($material['guru_name'])) {
    $guruDisplay = sanitize($material['guru_name']);
} elseif (!empty($material['guru_id'])) {
    $gId = (int)$material['guru_id'];
    $q   = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    if ($q) {
        $q->bind_param('i', $gId);
        $q->execute();
        $ru      = $q->get_result();
        $userRow = $ru ? $ru->fetch_assoc() : null;
        $q->close();

        if ($userRow) {
            foreach (['name','nama','full_name','username'] as $fn) {
                if (!empty($userRow[$fn])) {
                    $guruDisplay = sanitize($userRow[$fn]);
                    break;
                }
            }
        } else {
            $guruDisplay = 'ID: ' . $gId;
        }
    }
}

// ----- Ambil info file dari file_uploads (utama) -----

$fileInfo = null;   // ['original_name','stored_name','file_path','ext']
$serveUrl = null;

if (!empty($material['file_id']) && is_numeric($material['file_id'])) {
    $fid = (int)$material['file_id'];

    // cek apakah tabel file_uploads ada
    $checkFU = $db->query("SHOW TABLES LIKE 'file_uploads'");
    if ($checkFU && $checkFU->num_rows > 0) {
        $q = $db->prepare("
            SELECT original_name, stored_name, file_path, mime_type
            FROM file_uploads
            WHERE id = ?
            LIMIT 1
        ");
        if ($q) {
            $q->bind_param('i', $fid);
            $q->execute();
            $rf   = $q->get_result();
            $rowf = $rf ? $rf->fetch_assoc() : null;
            $q->close();

            if ($rowf) {
                $orig   = trim($rowf['original_name'] ?? '') ?: trim($rowf['stored_name'] ?? '');
                $stored = trim($rowf['stored_name'] ?? '');
                $path   = trim($rowf['file_path'] ?? '');

                // kalau path kosong, asumsikan di uploads/materials
                if ($path === '' && $stored !== '') {
                    $path = 'materials/' . $stored;
                }

                $fileInfo = [
                    'original_name' => $orig ?: ('file_' . $fid),
                    'stored_name'   => $stored,
                    'file_path'     => $path,
                    'mime_type'     => $rowf['mime_type'] ?? '',
                ];
            }
        }
    }

    // Fallback lama: kalau file_uploads tidak ada, coba tabel files (tidak pakai FK)
    if (!$fileInfo) {
        $checkFiles = $db->query("SHOW TABLES LIKE 'files'");
        if ($checkFiles && $checkFiles->num_rows > 0) {
            $q = $db->prepare("
                SELECT original_name, filename, path, mime
                FROM files
                WHERE id = ?
                LIMIT 1
            ");
            if ($q) {
                $q->bind_param('i', $fid);
                $q->execute();
                $rf   = $q->get_result();
                $rowf = $rf ? $rf->fetch_assoc() : null;
                $q->close();

                if ($rowf) {
                    $orig   = trim($rowf['original_name'] ?? '') ?: trim($rowf['filename'] ?? '');
                    $stored = trim($rowf['filename'] ?? '');
                    $path   = trim($rowf['path'] ?? '');

                    if ($path === '' && $stored !== '') {
                        $path = 'materials/' . $stored;
                    }

                    $fileInfo = [
                        'original_name' => $orig ?: ('file_' . $fid),
                        'stored_name'   => $stored,
                        'file_path'     => $path,
                        'mime_type'     => $rowf['mime'] ?? '',
                    ];
                }
            }
        }
    }
}

// Jika punya file_path -> buat URL ke serve_file.php
if ($fileInfo && !empty($fileInfo['file_path'])) {
    // pastikan tidak ada ../
    $rel = str_replace(['..\\','../'], '', $fileInfo['file_path']);
    $serveUrl = rtrim(BASE_URL, '/\\') . '/serve_file.php?f=' . rawurlencode($rel) . '&mode=inline';
}

// ----- Data tampilan -----

$judul      = sanitize($material['judul'] ?? '-');
$nama_mapel = sanitize($material['nama_mapel'] ?? '-');
$nama_kelas = sanitize(
    $material['nama_kelas'] ??
    trim(($material['class_level'] ?? '') . ' ' . ($material['jurusan'] ?? ''))
);
$konten    = $material['konten'] ?? '';
$videoLink = trim($material['video_link'] ?? '');
$createdAt = $material['created_at'] ?? '';

$pageTitle = $judul;
include __DIR__ . '/../inc/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><?php echo $judul; ?></h1>
        <div style="font-size:14px;color:#666;">
            <?php echo $nama_mapel; ?> — <?php echo $nama_kelas; ?>
            &middot; <?php echo sanitize($createdAt); ?><br>
            Pengajar: <?php echo $guruDisplay; ?>
        </div>
    </div>

    <div class="card" style="padding:18px;">
        <?php if (trim($konten) !== ''): ?>
            <div style="margin-bottom:18px; white-space:pre-wrap;">
                <?php echo nl2br(sanitize($konten)); ?>
            </div>
        <?php endif; ?>

        <?php if ($fileInfo): ?>
            <div style="margin-bottom:12px;">
                <strong>File terlampir:</strong>
                <div style="margin-top:8px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">

                    <?php if (!empty($serveUrl)): ?>
                        <!-- tombol buka di tab baru -->
                        <button
                            type="button"
                            onclick="window.open('<?php echo sanitize($serveUrl); ?>','_blank','noopener')"
                            class="btn btn-primary"
                            style="padding:10px 14px;border-radius:8px;cursor:pointer;"
                        >
                            Lihat File
                        </button>

                        <!-- tombol unduh -->
                        <a
                            href="<?php echo sanitize($serveUrl); ?>"
                            download
                            class="btn"
                            style="padding:8px 12px;border-radius:8px;background:#f3f4f6;color:#111;text-decoration:none;display:inline-block;"
                        >
                            Unduh
                        </a>
                    <?php else: ?>
                        <button type="button" class="btn" disabled style="padding:10px 14px;border-radius:8px;">
                            Lihat File (tidak tersedia)
                        </button>
                    <?php endif; ?>

                    <div style="font-size:14px;color:#333; margin-left:6px;">
                        <?php echo sanitize($fileInfo['original_name'] ?? 'Unnamed file'); ?>
                    </div>

                    <?php
                        $storedName = $fileInfo['stored_name'] ?? '';
                        $ext = strtolower(pathinfo($storedName, PATHINFO_EXTENSION));
                        $imgExts = ['jpg','jpeg','png','gif','webp','svg'];
                        if (!empty($serveUrl) && in_array($ext, $imgExts, true)):
                    ?>
                        <!-- preview gambar kecil -->
                        <div style="margin-top:8px; width:100%;">
                            <img
                                src="<?php echo sanitize($serveUrl); ?>"
                                alt="<?php echo sanitize($fileInfo['original_name'] ?? 'Preview'); ?>"
                                style="max-width:520px;height:auto;border:1px solid #eee;padding:6px;border-radius:8px;cursor:pointer;"
                                onclick="window.open('<?php echo sanitize($serveUrl); ?>','_blank','noopener')"
                            >
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        <?php endif; ?>

        <?php if ($videoLink !== ''): ?>
            <div style="margin-top:16px;">
                <strong>Video:</strong>
                <div style="margin-top:8px;">
                    <?php
                    if (strpos($videoLink, 'youtube.com') !== false || strpos($videoLink, 'youtu.be') !== false) {
                        $vId = null;
                        if (preg_match('/v=([a-zA-Z0-9_\-]+)/', $videoLink, $m)) {
                            $vId = $m[1];
                        } elseif (preg_match('/youtu\.be\/([a-zA-Z0-9_\-]+)/', $videoLink, $m)) {
                            $vId = $m[1];
                        }

                        if ($vId) {
                            $embed = "https://www.youtube.com/embed/" . htmlspecialchars($vId);
                            echo '<div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;">'
                               . '<iframe src="'. $embed .'" style="position:absolute;top:0;left:0;width:100%;height:100%;" frameborder="0" allowfullscreen></iframe>'
                               . '</div>';
                        } else {
                            echo '<a href="'. sanitize($videoLink) .'" target="_blank">'. sanitize($videoLink) .'</a>';
                        }
                    } else {
                        echo '<a href="'. sanitize($videoLink) .'" target="_blank">'. sanitize($videoLink) .'</a>';
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div style="margin-top:14px;">
        <a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/materials/list.php" class="btn btn-secondary">← Kembali</a>
        <?php if ($role === 'guru' && !empty($material['created_by']) && (int)$material['created_by'] === $userId): ?>
            <a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/materials/edit.php?id=<?php echo (int)$id; ?>" class="btn btn-primary">Edit</a>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
