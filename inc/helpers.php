<?php
/**
 * Helper Functions (Hardened)
 *
 * Pastikan di inc/config.php ada definisi:
 * - BASE_PATH        (root project, mis: __DIR__ . '/..')
 * - BASE_URL         (mis: '/web_MG')
 * - UPLOAD_DIR       (mis: __DIR__ . '/../uploads/')
 * - MAX_FILE_SIZE    (bytes), contoh: 10 * 1024 * 1024
 * - ALLOWED_TYPES    (array ext), contoh: ['pdf','doc','docx','jpg','jpeg','png','zip']
 *
 * Jika belum, file ini akan menyediakan default fallback seperlunya.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Default fallback constants (jika belum didefinisikan di config.php)
if (!defined('UPLOAD_DIR')) {
    define('UPLOAD_DIR', __DIR__ . '/../uploads/');
}
if (!defined('MAX_FILE_SIZE')) {
    define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
}
if (!defined('ALLOWED_TYPES')) {
    define('ALLOWED_TYPES', ['pdf','doc','docx','jpg','jpeg','png','zip','mp3','m4a','wav']);
}

/**
 * Format tanggal Indonesia
 */
function formatTanggal($date, $withTime = false) {
    if (empty($date)) return '-';

    $timestamp = strtotime($date);
    if ($timestamp === false) return '-';

    $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
              'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    $format = $hari[date('w', $timestamp)] . ', ' . date('d', $timestamp) . ' ' .
              $bulan[(int)date('m', $timestamp)] . ' ' . date('Y', $timestamp);

    if ($withTime) {
        $format .= ' ' . date('H:i', $timestamp);
    }

    return $format;
}

/**
 * Format waktu relatif (2 jam lalu, 3 hari lalu)
 */
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    if ($timestamp === false) return '-';

    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'Baru saja';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' menit lalu';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' jam lalu';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' hari lalu';
    } else {
        return formatTanggal($datetime);
    }
}

/**
 * Sanitize output untuk mencegah XSS
 */
function sanitize($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * CSRF token
 */
function generateCsrfToken($key = 'default') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $token = bin2hex(random_bytes(16));
    $_SESSION['csrf_tokens'][$key] = $token;
    return $token;
}

function verifyCsrfToken($token, $key = 'default') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($token) || empty($key)) return false;
    if (!isset($_SESSION['csrf_tokens'][$key])) return false;
    $valid = hash_equals($_SESSION['csrf_tokens'][$key], $token);
    // sekali pakai
    unset($_SESSION['csrf_tokens'][$key]);
    return $valid;
}

/**
 * Generate safe random filename (tidak wajib, tapi bisa dipakai kalau perlu)
 */
function _generateRandomFilename($ext) {
    $t = time();
    $r = bin2hex(random_bytes(6));
    return $t . '_' . $r . '.' . $ext;
}

/**
 * Upload file dengan validasi + simpan metadata ke DB
 *
 * $file   = $_FILES['file'] (atau satu item dari $_FILES['file']['name'][$i] dsb.)
 * $subdir = 'materials', 'assignments', dll (relatif terhadap UPLOAD_DIR)
 *
 * Return (standar):
 * [
 *   'success'       => bool,
 *   'file_id'       => int|null,          // id di tabel file_uploads
 *   'file_path'     => string|null,       // path relatif: "materials/xxx_y.jpg"
 *   'original_name' => string|null,
 *   'stored_name'   => string|null,
 *   'message'       => string
 * ]
 */
function uploadFile(array $file, $subdir = '') {
    $result = [
        'success'       => false,
        'file_id'       => null,
        'file_path'     => null,
        'original_name' => null,
        'stored_name'   => null,
        'message'       => ''
    ];

    // Validasi dasar
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $result['message'] = 'Upload gagal (kode error: ' . ($file['error'] ?? 'unknown') . ').';
        return $result;
    }

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $result['message'] = 'File upload tidak valid.';
        return $result;
    }

    if (!isset($file['size'])) {
        $file['size'] = filesize($file['tmp_name']);
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        $result['message'] = 'File terlalu besar. Maksimum ' . (MAX_FILE_SIZE / 1024 / 1024) . ' MB.';
        return $result;
    }

    $originalName = trim($file['name'] ?? 'file');
    if ($originalName === '') $originalName = 'file';

    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_TYPES, true)) {
        $result['message'] = 'Tipe file tidak diizinkan.';
        return $result;
    }

    // Root uploads
    $uploadsRoot = realpath(UPLOAD_DIR);
    if ($uploadsRoot === false) {
        // coba buat
        $uploadsRoot = rtrim(UPLOAD_DIR, '/\\');
        if (!is_dir($uploadsRoot)) {
            if (!@mkdir($uploadsRoot, 0755, true)) {
                $result['message'] = 'Gagal membuat folder uploads.';
                return $result;
            }
        }
        $uploadsRoot = realpath($uploadsRoot);
    }

    if ($uploadsRoot === false) {
        $result['message'] = 'Folder uploads tidak tersedia.';
        return $result;
    }

    // Subfolder (materials, assignments, dll)
    $subdir = trim((string)$subdir, "/\\");
    $targetDir = $uploadsRoot;
    if ($subdir !== '') {
        $targetDir .= DIRECTORY_SEPARATOR . $subdir;
        if (!is_dir($targetDir)) {
            if (!@mkdir($targetDir, 0755, true)) {
                $result['message'] = 'Gagal membuat folder upload: ' . $subdir;
                return $result;
            }
        }
    }

    // Nama file aman & unik
    try {
        $rand = bin2hex(random_bytes(8));
    } catch (Exception $e) {
        $rand = uniqid('', true);
    }

    $baseName  = pathinfo($originalName, PATHINFO_FILENAME);
    $safeBase  = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $baseName);
    if ($safeBase === '') $safeBase = 'file';

    // pola: random_namaAsli.ext (supaya bisa ditebak ulang di serve_file.php)
    $storedName = $rand . '_' . $safeBase . ($ext ? '.' . $ext : '');
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $storedName;

    if (!@move_uploaded_file($file['tmp_name'], $targetPath)) {
        $result['message'] = 'Gagal memindahkan file upload.';
        return $result;
    }

    // Info file
    $mime = $file['type'] ?? null;
    if (!$mime) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo ? finfo_file($finfo, $targetPath) : 'application/octet-stream';
        if ($finfo) finfo_close($finfo);
    }

    $size       = (int)$file['size'];
    $uploaderId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    // Path relatif dari folder uploads
    $relativePath = ($subdir !== '' ? $subdir . '/' : '') . $storedName;

    // Simpan ke tabel file_uploads (ini yang dipakai FK oleh materials & assignment_files)
    try {
        $db = getDB();

        $stmt = $db->prepare("
            INSERT INTO file_uploads
                (original_name, stored_name, file_path, mime_type, file_size, uploader_id, uploaded_at)
            VALUES
                (?, ?, ?, ?, ?, ?, NOW())
        ");
        if (!$stmt) {
            throw new Exception('prepare error: ' . $db->error);
        }

        $stmt->bind_param(
            "sssiii",
            $originalName,
            $storedName,
            $relativePath,
            $mime,
            $size,
            $uploaderId
        );

        if (!$stmt->execute()) {
            $msg = $stmt->error;
            $stmt->close();
            // hapus file fisik jika gagal simpan DB
            @unlink($targetPath);
            throw new Exception('execute error: ' . $msg);
        }

        $fileId = (int)$db->insert_id;
        $stmt->close();

        // OPSIONAL: mirror ke tabel `files` jika tabel tersebut ada
        $checkFiles = $db->query("SHOW TABLES LIKE 'files'");
        if ($checkFiles && $checkFiles->num_rows > 0) {
            $stmt2 = $db->prepare("
                INSERT INTO files
                    (original_name, filename, path, filesize, mime, uploaded_by, created_at, filepath)
                VALUES
                    (?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            if ($stmt2) {
                $pathForFiles = $relativePath;
                $stmt2->bind_param(
                    "sssisis",
                    $originalName,
                    $storedName,
                    $relativePath,
                    $size,
                    $mime,
                    $uploaderId,
                    $pathForFiles
                );
                $stmt2->execute();
                $stmt2->close();
            }
        }

        $result['success']       = true;
        $result['file_id']       = $fileId;
        $result['file_path']     = $relativePath;
        $result['original_name'] = $originalName;
        $result['stored_name']   = $storedName;
        $result['message']       = 'OK';
        return $result;

    } catch (Exception $e) {
        $result['message'] = 'DB error: ' . $e->getMessage();
        return $result;
    }
}

/**
 * Delete file by file_uploads.id
 * - Hapus file fisik + baris DB
 */
function deleteFileById($fileId) {
    $db = getDB();
    $fileId = (int)$fileId;

    $stmt = $db->prepare("SELECT file_path FROM file_uploads WHERE id = ?");
    if (!$stmt) return false;
    $stmt->bind_param("i", $fileId);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) {
        $stmt->close();
        return false;
    }
    $row = $res->fetch_assoc();
    $stmt->close();

    $uploadRoot     = rtrim(UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $realUploadRoot = realpath($uploadRoot);
    $target         = $uploadRoot . $row['file_path'];
    $realTarget     = realpath($target);

    if ($realTarget && $realUploadRoot && strpos($realTarget, $realUploadRoot) === 0) {
        @unlink($realTarget);
    }

    $stmt2 = $db->prepare("DELETE FROM file_uploads WHERE id = ?");
    if (!$stmt2) return false;
    $stmt2->bind_param("i", $fileId);
    $ok = $stmt2->execute();
    $stmt2->close();

    return (bool)$ok;
}

/**
 * Buat notifikasi (prepared statement)
 * Returns insert_id (int) or false
 */
function createNotification($userId, $title, $message, $link = '') {
    $db = getDB();

    $userId  = (int)$userId;
    $title   = trim($title);
    $message = trim($message);
    $link    = trim($link);

    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, title, message, link, is_read, created_at)
        VALUES (?, ?, ?, ?, 0, NOW())
    ");
    if (!$stmt) return false;
    $stmt->bind_param("isss", $userId, $title, $message, $link);
    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }
    $insertId = $db->insert_id;
    $stmt->close();
    return (int)$insertId;
}

/**
 * Get unread notifications count (prepared)
 */
function getUnreadNotificationsCount($userId) {
    $db = getDB();
    $userId = (int)$userId;

    $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0");
    if (!$stmt) return 0;
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return (int)($row['cnt'] ?? 0);
}

/**
 * Mark notification as read (prepared)
 */
function markNotificationRead($notificationId) {
    $db = getDB();
    $notificationId = (int)$notificationId;

    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    if (!$stmt) return false;
    $stmt->bind_param("i", $notificationId);
    $res = $stmt->execute();
    $stmt->close();
    return (bool)$res;
}

/**
 * Generate pagination (simple)
 */
function getPagination($currentPage, $totalPages, $baseUrl) {
    $html = '<div class="pagination">';

    if ($currentPage > 1) {
        $html .= '<a href="' . sanitize($baseUrl) . '?page=' . ($currentPage - 1) . '" class="btn-prev">← Sebelumnya</a>';
    }

    $html .= '<span class="page-info">Halaman ' . (int)$currentPage . ' dari ' . (int)$totalPages . '</span>';

    if ($currentPage < $totalPages) {
        $html .= '<a href="' . sanitize($baseUrl) . '?page=' . ($currentPage + 1) . '" class="btn-next">Selanjutnya →</a>';
    }

    $html .= '</div>';

    return $html;
}

/**
 * Check deadline status
 */
function getDeadlineStatus($deadline) {
    if (empty($deadline)) return ['status' => 'unknown', 'label' => '-', 'class' => 'muted'];

    $now          = time();
    $deadlineTime = strtotime($deadline);
    if ($deadlineTime === false) return ['status' => 'unknown', 'label' => '-', 'class' => 'muted'];

    $diff = $deadlineTime - $now;

    if ($diff < 0) {
        return ['status' => 'overdue', 'label' => 'Terlambat', 'class' => 'danger'];
    } elseif ($diff < 86400) {
        return ['status' => 'urgent', 'label' => 'Deadline dekat', 'class' => 'warning'];
    } else {
        $days = floor($diff / 86400);
        return ['status' => 'ok', 'label' => $days . ' hari lagi', 'class' => 'info'];
    }
}

/**
 * Get file download URL
 * (kalau kamu sekarang pakai serve_file.php, bisa diarahkan ke situ)
 */
function getDownloadUrl($fileId) {
    $fileId = (int)$fileId;
    if (defined('BASE_URL')) {
        // misal file_path nanti dicari dulu di file_uploads; untuk sekarang biarkan seperti semula
        return BASE_URL . '/download.php?id=' . $fileId;
    }
    return '/web_MG/download.php?id=' . $fileId;
}

/**
 * Simple debug logger
 */
function debug_log($msg) {
    $base = defined('BASE_PATH') ? BASE_PATH : __DIR__ . '/..';
    $logDir = $base . '/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    $line = date('c') . ' ' . $msg . PHP_EOL;
    @file_put_contents($logDir . '/debug.log', $line, FILE_APPEND | LOCK_EX);
}

/**
 * Buat password yang mudah dibaca: pola suku kata + angka
 * Contoh: "bavi-4821" atau "lomo-2738"
 */
function generateReadablePassword($digits = 4) {
    $syl1 = ['ba','be','bi','bo','bu','la','le','li','lo','lu','ra','re','ri','ro','ru','ma','me','mi','mo','mu','sa','se','si','so','su'];
    $syl2 = ['na','ne','ni','no','nu','ka','ke','ki','ko','ku','da','de','di','do','du','ta','te','ti','to','tu'];
    $a = $syl1[array_rand($syl1)];
    $b = $syl2[array_rand($syl2)];
    $nums = str_pad((string)rand(0, (int)str_repeat('9', $digits)), $digits, '0', STR_PAD_LEFT);
    return $a . $b . '-' . $nums;
}

/**
 * Simple slugify untuk membuat local-part email dari nama
 */
function slugify($text) {
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $text = preg_replace('~[^\pL\d]+~u', '.', $text);
    $text = preg_replace('~[^.\w]+~', '', $text);
    $text = trim($text, '.');
    $text = preg_replace('~\.+~', '.', $text);
    $text = strtolower($text);
    if (empty($text)) return 'user';
    return $text;
}
