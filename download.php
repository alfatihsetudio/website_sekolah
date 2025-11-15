<?php
/**
 * Download File Handler (Protected) - Hardened
 *
 * Keamanan:
 * - Semua query menggunakan prepared statements
 * - Cegah directory traversal dengan realpath() dan pengecekan terhadap UPLOAD_DIR
 * - Periksa hak akses: admin, uploader, guru/owner materi/assignment, student terkait, atau submitter
 * - Header download aman (filename* UTF-8 encoded)
 */

require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/helpers.php'; // asumsi ada sanitize() dll
requireLogin();

$db = getDB();

// helper: uniform deny
function denyAccess($message = 'Anda tidak memiliki akses ke file ini', $code = 403) {
    http_response_code($code);
    // tampilkan pesan singkat (jangan leak path)
    echo sanitize($message);
    exit;
}

// validasi file id
$fileId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($fileId <= 0) {
    denyAccess('File tidak ditemukan', 404);
}

// ambil info file menggunakan prepared statement
$stmt = $db->prepare("SELECT id, original_name, file_path, mime_type, size, uploader_id FROM file_uploads WHERE id = ?");
if (!$stmt) {
    denyAccess('Internal server error', 500);
}
$stmt->bind_param("i", $fileId);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    $stmt->close();
    denyAccess('File tidak ditemukan', 404);
}
$file = $res->fetch_assoc();
$stmt->close();

// bangun path fisik file dan cek eksistensi
$uploadDir = rtrim(UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$realUploadDir = realpath($uploadDir);
if ($realUploadDir === false) {
    denyAccess('Server configuration error', 500);
}

$filePath = $uploadDir . $file['file_path'];
$realFile = realpath($filePath);
if ($realFile === false || !file_exists($realFile)) {
    denyAccess('File tidak ditemukan di server', 404);
}

// pastikan file berada di dalam folder uploads (cegah traversal)
if (strpos($realFile, $realUploadDir) !== 0) {
    denyAccess('Akses file diblokir', 403);
}

// akses permission checks
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$role = getUserRole();

// 1) admin dapat mengakses semua
if ($role === 'admin') {
    $allowed = true;
} elseif ($file['uploader_id'] == $currentUserId) {
    // uploader asli dapat mengakses
    $allowed = true;
} else {
    $allowed = false;

    // 2) material related?
    $stmt = $db->prepare("
        SELECT m.id, m.subject_id, s.class_id, m.created_by
        FROM materials m
        JOIN subjects s ON m.subject_id = s.id
        WHERE m.file_id = ?
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("i", $fileId);
        $stmt->execute();
        $r = $stmt->get_result();
        if ($r && $r->num_rows > 0) {
            $m = $r->fetch_assoc();
            // guru owner of material can access
            if ($role === 'guru' && (int)$m['created_by'] === $currentUserId) {
                $allowed = true;
            } elseif ($role === 'murid') {
                // check student in class
                $stmt2 = $db->prepare("
                    SELECT 1 FROM class_user cu
                    WHERE cu.class_id = ? AND cu.user_id = ? LIMIT 1
                ");
                if ($stmt2) {
                    $classId = (int)$m['class_id'];
                    $stmt2->bind_param("ii", $classId, $currentUserId);
                    $stmt2->execute();
                    $r2 = $stmt2->get_result();
                    if ($r2 && $r2->num_rows > 0) {
                        $allowed = true;
                    }
                    $stmt2->close();
                }
            }
        }
        $stmt->close();
    }
    if (!$allowed) {
        // 3) assignment related?
        $stmt = $db->prepare("
            SELECT a.id, a.subject_id, s.class_id, a.created_by
            FROM assignments a
            JOIN subjects s ON a.subject_id = s.id
            WHERE a.file_id = ? LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param("i", $fileId);
            $stmt->execute();
            $r = $stmt->get_result();
            if ($r && $r->num_rows > 0) {
                $a = $r->fetch_assoc();
                if ($role === 'guru' && (int)$a['created_by'] === $currentUserId) {
                    $allowed = true;
                } elseif ($role === 'murid') {
                    $stmt2 = $db->prepare("
                        SELECT 1 FROM class_user cu
                        WHERE cu.class_id = ? AND cu.user_id = ? LIMIT 1
                    ");
                    if ($stmt2) {
                        $classId = (int)$a['class_id'];
                        $stmt2->bind_param("ii", $classId, $currentUserId);
                        $stmt2->execute();
                        $r2 = $stmt2->get_result();
                        if ($r2 && $r2->num_rows > 0) {
                            $allowed = true;
                        }
                        $stmt2->close();
                    }
                }
            }
            $stmt->close();
        }
    }

    if (!$allowed) {
        // 4) submission related? (student who submitted or assignment owner)
        $stmt = $db->prepare("
            SELECT s.student_id, a.created_by AS assignment_owner
            FROM submissions s
            LEFT JOIN assignments a ON s.assignment_id = a.id
            WHERE s.file_id = ? LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param("i", $fileId);
            $stmt->execute();
            $r = $stmt->get_result();
            if ($r && $r->num_rows > 0) {
                $sub = $r->fetch_assoc();
                if ((int)$sub['student_id'] === $currentUserId) {
                    $allowed = true;
                } elseif ((int)$sub['assignment_owner'] === $currentUserId && $role === 'guru') {
                    $allowed = true;
                }
            }
            $stmt->close();
        }
    }
}

if (empty($allowed)) {
    denyAccess();
}

// Serve file safely
// Prepare filename for header (use RFC5987 for UTF-8)
$originalName = $file['original_name'];
$basename = basename($originalName);
$utf8Name = rawurlencode($basename);

// Determine content type (fallback)
$mime = !empty($file['mime_type']) ? $file['mime_type'] : (finfo_file(finfo_open(FILEINFO_MIME_TYPE), $realFile) ?: 'application/octet-stream');

// Force download headers
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Transfer-Encoding: binary');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9\-\._]/', '_', $basename) . '"; filename*=UTF-8\'\'' . $utf8Name);
header('Content-Length: ' . filesize($realFile));

// Flush output buffers then read file
flush();
$fp = fopen($realFile, 'rb');
if ($fp) {
    while (!feof($fp)) {
        echo fread($fp, 8192);
        flush();
    }
    fclose($fp);
    exit;
} else {
    denyAccess('Gagal membuka file', 500);
}
