<?php
// list_materials_debug.php — tampilkan isi folder uploads/materials dan cari kemiripan
header('Content-Type: text/plain; charset=utf-8');

$dir = __DIR__ . '/uploads/materials';
echo "Debug list uploads/materials\n";
echo "Folder: $dir\n\n";

if (!is_dir($dir)) {
    echo "Folder tidak ditemukan.\n";
    exit;
}

$files = scandir($dir);
if ($files === false) {
    echo "Gagal membaca folder.\n";
    exit;
}

foreach ($files as $f) {
    if ($f === '.' || $f === '..') continue;
    $path = $dir . DIRECTORY_SEPARATOR . $f;
    if (!is_file($path)) continue;
    $size = filesize($path);
    $mtime = date('Y-m-d H:i:s', filemtime($path));
    echo "FILE: $f\n";
    echo "  size: $size bytes\n";
    echo "  mtime: $mtime\n";
    echo "  path: uploads/materials/$f\n\n";
}

// Also print a small search helper: if you recently requested "WhatsApp Image 2025-11-13 at 14.19.09.jpeg"
$needle = 'WhatsApp Image 2025-11-13 at 14.19.09';
echo "-----\nSearch for pattern: \"$needle\"\n";
foreach ($files as $f) {
    if ($f === '.' || $f === '..') continue;
    if (stripos($f, $needle) !== false) {
        echo "MATCH: $f\n";
    }
}
echo "-----\n";
