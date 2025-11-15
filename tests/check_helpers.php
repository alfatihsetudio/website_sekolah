<?php
// tests/check_helpers.php
error_reporting(E_ALL);
ini_set('display_errors',1);

echo "<h2>Check helpers include & fungsi</h2>";
$helpers = __DIR__ . '/../inc/helpers.php';
echo "<p>Path helpers: <code>$helpers</code></p>";

if (file_exists($helpers)) {
    echo "<p style='color:green'>helpers.php ditemukan.</p>";
    include_once $helpers;
} else {
    echo "<p style='color:red'>helpers.php TIDAK DITEMUKAN. Periksa path.</p>";
    exit;
}

// Cek fungsi penting
$funcs = ['sanitize','uploadFile','getDB','getUnreadNotificationsCount','timeAgo'];
foreach ($funcs as $f) {
    echo "<p>Fungsi <strong>$f</strong> : ";
    echo function_exists($f) ? "<span style='color:green'>TERDEFINISI</span>" : "<span style='color:red'>TIDAK TERDEFINISI</span>";
    echo "</p>";
}

// Cek apakah include helpers memicu error/echo
echo "<p>Isi file helpers (baris awal):</p><pre>";
$head = file($helpers, FILE_IGNORE_NEW_LINES);
for ($i=0;$i<min(20,count($head));$i++){
    echo htmlspecialchars($head[$i]) . "\n";
}
echo "</pre>";
