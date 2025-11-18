<?php
// serve_file.php (robust, tolerant, multiple roots, debug support)
// Usage: /serve_file.php?f=materials/abc.jpg&mode=inline&debug=1

$param = $_GET['f'] ?? '';
$param = trim((string)$param);
$mode = strtolower(trim($_GET['mode'] ?? 'attachment')); // 'inline' or 'attachment'
$debugMode = isset($_GET['debug']) && ($_GET['debug'] == '1' || $_GET['debug'] === 'true');

// Basic validation
if ($param === '' || strpos($param, "\0") !== false) {
    http_response_code(400);
    echo "Invalid file parameter.";
    exit;
}
if (strpos($param, '..') !== false) {
    http_response_code(400);
    echo "Invalid file parameter (directory traversal).";
    exit;
}

// normalize incoming path separators
$requested = str_replace(['\\','/'], DIRECTORY_SEPARATOR, $param);
$requested = ltrim($requested, DIRECTORY_SEPARATOR);

// candidate roots to search (order matters)
$candidateRoots = [
    realpath(__DIR__ . '/uploads'),
    realpath(__DIR__ . '/uploads/materials'),
    realpath(__DIR__ . '/storage/uploads'),
    realpath(__DIR__ . '/storage'),
    realpath(__DIR__ . '/public/uploads'),
    realpath(__DIR__ . '/files'),
    realpath(__DIR__ . '/materials'),
    realpath(__DIR__), // fallback to project root
];

// remove false entries and deduplicate
$roots = [];
foreach ($candidateRoots as $r) {
    if ($r && is_dir($r) && !in_array($r, $roots, true)) $roots[] = $r;
}

// helper derive original name
function deriveOriginalNameFromPhysical($physicalName) {
    if (preg_match('/^[a-f0-9]+_(.+)$/i', $physicalName, $m)) {
        $orig = $m[1];
        $orig = str_replace('_', ' ', $orig);
        return $orig;
    }
    return $physicalName;
}

function sendHeadersAndStream($filePath, $downloadName, $mode = 'attachment') {
    if (!is_file($filePath) || !is_readable($filePath)) {
        http_response_code(404);
        echo "File not found.";
        exit;
    }

    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $filePath) : 'application/octet-stream';
    if ($finfo) finfo_close($finfo);

    $downloadName = $downloadName ?: basename($filePath);
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $downloadName);
    if ($ascii === false || $ascii === null || trim($ascii) === '') {
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $downloadName);
    }
    $ascii = str_replace('"', '\'', $ascii);
    $utf8enc = rawurlencode($downloadName);

    // caching headers for public files (images/docs) - 1 day
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: public, max-age=86400, must-revalidate');

    if ($mode === 'inline') {
        header("Content-Disposition: inline; filename=\"{$ascii}\"; filename*=UTF-8''{$utf8enc}");
    } else {
        header("Content-Disposition: attachment; filename=\"{$ascii}\"; filename*=UTF-8''{$utf8enc}");
    }

    // Support for large files: use readfile
    // (If needing range support, can be added later)
    readfile($filePath);
    exit;
}

// function to test candidate full path under a root
function test_candidate($root, $rel) {
    // Compose candidate, resolve realpath, ensure within root
    $candidate = $root . DIRECTORY_SEPARATOR . $rel;
    $real = @realpath($candidate);
    if ($real === false) return false;
    // Ensure file is inside root (no traversal)
    if (strpos($real, $root) !== 0) return false;
    if (!is_file($real) || !is_readable($real)) return false;
    return $real;
}

// Record attempted paths (for debug)
$attempts = [];

// 1) Try exact path under each root (use requested as-is)
foreach ($roots as $root) {
    $full = test_candidate($root, $requested);
    $attempts[] = $root . DIRECTORY_SEPARATOR . $requested;
    if ($full !== false) {
        $physical = basename($full);
        $orig = deriveOriginalNameFromPhysical($physical);
        if ($debugMode) {
            header('Content-Type: text/plain; charset=utf-8');
            echo "DEBUG: Found exact path\n";
            echo "Root: {$root}\n";
            echo "Full: {$full}\n";
            echo "Serving as: {$orig}\n\n";
        }
        sendHeadersAndStream($full, $orig, $mode);
    }
}

// 2) If requested contains directories (e.g. materials/abc.jpg), try basename search in typical dirs
$basename = basename($requested);
if ($basename) {
    foreach ($roots as $root) {
        // try direct in root
        $candidate = $root . DIRECTORY_SEPARATOR . $basename;
        $attempts[] = $candidate;
        if (is_file($candidate) && is_readable($candidate)) {
            $orig = deriveOriginalNameFromPhysical(basename($candidate));
            sendHeadersAndStream($candidate, $orig, $mode);
        }

        // try inside 'materials' subdir under root
        $candidate2 = $root . DIRECTORY_SEPARATOR . 'materials' . DIRECTORY_SEPARATOR . $basename;
        $attempts[] = $candidate2;
        if (is_file($candidate2) && is_readable($candidate2)) {
            $orig = deriveOriginalNameFromPhysical(basename($candidate2));
            sendHeadersAndStream($candidate2, $orig, $mode);
        }

        // try inside 'uploads' subdir (if root is project root)
        $candidate3 = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $basename;
        $attempts[] = $candidate3;
        if (is_file($candidate3) && is_readable($candidate3)) {
            $orig = deriveOriginalNameFromPhysical(basename($candidate3));
            sendHeadersAndStream($candidate3, $orig, $mode);
        }
    }
}

// 3) Tolerant scan inside each root (fast exit on match). Use normalized matching to allow prefixes/suffices
function normalize_name($s) {
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[^a-z0-9]+/u', '', $s);
    return $s;
}
$targetNorm = normalize_name($basename);
if ($targetNorm !== '') {
    foreach ($roots as $root) {
        // look through directory (non-recursive) to avoid heavy IO
        $dh = @opendir($root);
        if (!$dh) continue;
        while (($f = readdir($dh)) !== false) {
            if ($f === '.' || $f === '..') continue;
            $fp = $root . DIRECTORY_SEPARATOR . $f;
            if (!is_file($fp) || !is_readable($fp)) continue;
            $fnameNorm = normalize_name($f);
            $attempts[] = $fp;
            if (strpos($fnameNorm, $targetNorm) !== false || strpos($targetNorm, $fnameNorm) !== false) {
                $orig = deriveOriginalNameFromPhysical(basename($fp));
                closedir($dh);
                sendHeadersAndStream($fp, $orig, $mode);
            }
        }
        closedir($dh);

        // also try 'materials' subdir
        $mDir = $root . DIRECTORY_SEPARATOR . 'materials';
        if (is_dir($mDir) && is_readable($mDir)) {
            $dh2 = @opendir($mDir);
            if ($dh2) {
                while (($f = readdir($dh2)) !== false) {
                    if ($f === '.' || $f === '..') continue;
                    $fp = $mDir . DIRECTORY_SEPARATOR . $f;
                    if (!is_file($fp) || !is_readable($fp)) continue;
                    $fnameNorm = normalize_name($f);
                    $attempts[] = $fp;
                    if (strpos($fnameNorm, $targetNorm) !== false || strpos($targetNorm, $fnameNorm) !== false) {
                        $orig = deriveOriginalNameFromPhysical(basename($fp));
                        closedir($dh2);
                        sendHeadersAndStream($fp, $orig, $mode);
                    }
                }
                closedir($dh2);
            }
        }
    }
}

// Not found
if ($debugMode) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "DEBUG: file not found\n";
    echo "Requested param: {$param}\n\n";
    echo "Tried roots (in order):\n";
    foreach ($roots as $r) echo "- {$r}\n";
    echo "\nTried candidates (sample):\n";
    foreach ($attempts as $a) echo $a . "\n";
    echo "\nHint: cek folder uploads/materials atau storage/uploads, dan pastikan nama file (basename) sama dengan yang ada di DB.\n";
    exit;
}

http_response_code(404);
echo "File not found.";
exit;
