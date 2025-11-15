<?php
// tests/show_error_log.php
error_reporting(E_ALL);
ini_set('display_errors',1);

echo "<h2>Last lines from Apache/PHP error logs</h2>";

$logPaths = [
    // Common XAMPP apache error.log path on Windows
    'C:\\xampp\\apache\\logs\\error.log',
    // fallback: php error log (if set)
    ini_get('error_log')
];

$found = false;
foreach ($logPaths as $path) {
    if ($path && file_exists($path)) {
        echo "<h3>Log: " . htmlspecialchars($path) . "</h3><pre>";
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            echo "Tidak bisa baca file log atau file kosong.\n";
        } else {
            $tail = array_slice($lines, -120); // last 120 lines
            foreach ($tail as $l) echo htmlspecialchars($l) . "\n";
        }
        echo "</pre>";
        $found = true;
    } else {
        echo "<p>Log tidak ditemukan di: " . htmlspecialchars($path) . "</p>";
    }
}
if (!$found) echo "<p style='color:orange'>Tidak ada file log yang bisa dibaca. Cek konfigurasi Apache/XAMPP.</p>";
