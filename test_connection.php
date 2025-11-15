<?php
/**
 * Test Connection - Hapus file ini setelah testing
 * Akses: http://localhost/web_MG/test_connection.php
 */

// Test 1: PHP Version
echo "<h2>Test 1: PHP Version</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Status: " . (version_compare(phpversion(), '7.4.0', '>=') ? "✅ OK" : "❌ PHP 7.4+ required") . "<br><br>";

// Test 2: Required Extensions
echo "<h2>Test 2: Required Extensions</h2>";
$extensions = ['mysqli', 'mbstring', 'fileinfo', 'session'];
foreach ($extensions as $ext) {
    $loaded = extension_loaded($ext);
    echo "Extension $ext: " . ($loaded ? "✅ Loaded" : "❌ Not Loaded") . "<br>";
}
echo "<br>";

// Test 3: File Structure
echo "<h2>Test 3: File Structure</h2>";
$required_files = [
    'inc/config.php',
    'inc/db.php',
    'inc/auth.php',
    'inc/helpers.php',
    'inc/header.php',
    'inc/footer.php',
    'database.sql',
    'auth/login.php',
    'dashboard/guru.php',
    'dashboard/murid.php',
    'assets/css/style.css',
    'assets/js/main.js'
];

foreach ($required_files as $file) {
    $exists = file_exists($file);
    echo "File $file: " . ($exists ? "✅ Exists" : "❌ Missing") . "<br>";
}
echo "<br>";

// Test 4: Folder Permissions
echo "<h2>Test 4: Folder Permissions</h2>";
$folders = ['uploads', 'uploads/materials', 'uploads/assignments', 'uploads/submissions'];
foreach ($folders as $folder) {
    if (!file_exists($folder)) {
        if (mkdir($folder, 0755, true)) {
            echo "Folder $folder: ✅ Created<br>";
        } else {
            echo "Folder $folder: ❌ Cannot create<br>";
        }
    } else {
        $writable = is_writable($folder);
        echo "Folder $folder: " . ($writable ? "✅ Writable" : "❌ Not Writable") . "<br>";
    }
}
echo "<br>";

// Test 5: Database Connection (jika config sudah diisi)
echo "<h2>Test 5: Database Connection</h2>";
if (file_exists('inc/config.php')) {
    require_once 'inc/config.php';
    
    if (defined('DB_HOST') && defined('DB_USER') && defined('DB_NAME')) {
        try {
            $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($conn->connect_error) {
                echo "❌ Connection Failed: " . $conn->connect_error . "<br>";
                echo "💡 <strong>Action Required:</strong> Edit <code>inc/config.php</code> dengan setting database Anda<br>";
                echo "💡 <strong>Nama Database:</strong> web_markazlugoh<br>";
            } else {
                echo "✅ Database Connection: OK<br>";
                echo "Database: " . DB_NAME . "<br>";
                $conn->close();
            }
        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "⚠️ Config constants not defined<br>";
    }
} else {
    echo "❌ config.php not found<br>";
}
echo "<br>";

// Test 6: Include Test
echo "<h2>Test 6: Include Test</h2>";
try {
    require_once 'inc/config.php';
    require_once 'inc/db.php';
    echo "✅ Config & DB includes: OK<br>";
} catch (Exception $e) {
    echo "❌ Include Error: " . $e->getMessage() . "<br>";
}
echo "<br>";

// Summary
echo "<h2>📋 Summary</h2>";
echo "<p><strong>Jika semua test ✅ OK, aplikasi siap dijalankan!</strong></p>";
echo "<p><strong>Langkah selanjutnya:</strong></p>";
echo "<ol>";
echo "<li>Import <code>database.sql</code> ke database Anda</li>";
echo "<li>Edit <code>inc/config.php</code> dengan setting database yang benar</li>";
echo "<li>Set permission folder <code>uploads/</code> menjadi 755 atau 777</li>";
echo "<li>Akses <a href='auth/login.php'>auth/login.php</a> untuk login</li>";
echo "<li>Default admin: <code>admin@example.com</code> / <code>admin123</code></li>";
echo "</ol>";
echo "<p><strong style='color: red;'>⚠️ HAPUS FILE INI SETELAH TESTING!</strong></p>";

