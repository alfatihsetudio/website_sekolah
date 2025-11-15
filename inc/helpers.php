<?php
/**
 * Helper Functions (Hardened)
 *
 * Pastikan di inc/config.php ada definisi:
 * - UPLOAD_DIR (contoh: __DIR__ . '/../uploads/')
 * - MAX_FILE_SIZE (bytes), e.g. 10 * 1024 * 1024
 * - ALLOWED_TYPES (array of extensions), e.g. ['pdf','doc','docx','jpg','jpeg','png','zip']
 *
 * Jika belum, file ini akan menyediakan default fallback.
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
    define('ALLOWED_TYPES', ['pdf','doc','docx','jpg','jpeg','png','zip']);
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
 * Generate safe random filename
 */
function _generateRandomFilename($ext) {
    $t = time();
    $r = bin2hex(random_bytes(6));
    return $t . '_' . $r . '.' . $ext;
}

/**
 * Upload file dengan validasi + simpan metadata ke DB
 * $file = $_FILES['file']
 * $subfolder = 'submissions/' atau 'materials/' (relatif terhadap UPLOAD_DIR)
 * Returns: ['success'=>bool, 'file_id'=>int|null, 'file_path'=>string|null, 'original_name'=>string|null, 'message'=>string]
 */
function uploadFile(array $file, $subdir = '') {
    $res = ['success' => false];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $res['message'] = 'Upload error code: ' . $file['error'];
        return $res;
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        $res['message'] = 'File terlalu besar. Maksimum ' . (MAX_FILE_SIZE/1024/1024) . ' MB';
        return $res;
    }

    $allowed = ['pdf','doc','docx','jpg','jpeg','png','zip','mp3','m4a','wav'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        $res['message'] = 'Tipe file tidak diizinkan.';
        return $res;
    }

    $targetDir = rtrim(UPLOAD_DIR, '/\\') . '/' . trim($subdir, '/\\');
    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            $res['message'] = 'Gagal membuat direktori upload.';
            return $res;
        }
    }

    $safeName = bin2hex(random_bytes(8)) . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file['name']);
    $targetPath = $targetDir . '/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        $res['message'] = 'Gagal memindahkan file upload.';
        return $res;
    }

    // Simpan ke tabel files (sesuaikan kolom dengan DB Anda)
    try {
        $db = getDB();
        $uploader = (int)($_SESSION['user_id'] ?? 0);
        $relPath = str_replace(BASE_PATH . '/', '', $targetPath);
        $stmt = $db->prepare("INSERT INTO files (filename, path, uploaded_by, created_at) VALUES (?, ?, ?, NOW())");
        if (!$stmt) throw new Exception($db->error);
        $stmt->bind_param("ssi", $file['name'], $relPath, $uploader);
        $ok = $stmt->execute();
        if (!$ok) {
            // hapus file jika gagal simpan DB
            @unlink($targetPath);
            throw new Exception($stmt->error);
        }
        $fileId = $db->insert_id;
        $stmt->close();
        $res['success'] = true;
        $res['file_id'] = $fileId;
    } catch (Exception $e) {
        $res['message'] = 'DB error: ' . $e->getMessage();
    }

    return $res;
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

    $uploadRoot = rtrim(UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $realUploadRoot = realpath($uploadRoot);
    $target = $uploadRoot . $row['file_path'];
    $realTarget = realpath($target);

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

    $userId = (int)$userId;
    $title = trim($title);
    $message = trim($message);
    $link = trim($link);

    $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, link, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
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

    $now = time();
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
 */
function getDownloadUrl($fileId) {
    return '/web_MG/download.php?id=' . (int)$fileId;
}

function debug_log($msg) {
    // ringkas: simpan ke file logs/debug.log
    $logDir = BASE_PATH . '/logs';
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
	$text = preg_replace('~[^\\pL\d]+~u', '.', $text);
	$text = preg_replace('~[^.\w]+~', '', $text);
	$text = trim($text, '.');
	$text = preg_replace('~\.+~', '.', $text);
	$text = strtolower($text);
	if (empty($text)) return 'user';
	return $text;
}
