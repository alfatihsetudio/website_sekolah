<?php
// tests/check_header_include.php
error_reporting(E_ALL);
ini_set('display_errors',1);

echo "<h2>Check inc/header.php include</h2>";
$header = __DIR__ . '/../inc/header.php';
echo "<p>Path header: <code>$header</code></p>";

if (!file_exists($header)) {
    echo "<p style='color:red'>header.php TIDAK DITEMUKAN</p>";
    exit;
}

try {
    include_once $header;
    echo "<p style='color:green'>header.php berhasil di-include tanpa fatal error.</p>";
    // tutup html (header biasanya membuka tag), supaya output terlihat rapi
    echo "<!-- header included -->";
} catch (Throwable $e) {
    echo "<p style='color:red'>Exception saat include header: " . htmlspecialchars($e->getMessage()) . "</p>";
}
