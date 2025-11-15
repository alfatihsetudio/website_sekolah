<?php
/**
 * Debug File - Hapus setelah selesai troubleshooting
 * Akses: http://localhost/web_MG/debug.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session early to avoid "headers already sent" warnings
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h1>🔍 Debug Information</h1>";

// Test 1: PHP Version
echo "<h2>1. PHP Version</h2>";
echo "Version: " . phpversion() . "<br>";
echo "Status: " . (version_compare(phpversion(), '7.4.0', '>=') ? "✅ OK" : "❌ PHP 7.4+ required") . "<br><br>";

// Test 2: File Exists
echo "<h2>2. File Check</h2>";
$incDir = __DIR__ . '/inc';
$files = [
    $incDir . '/config.php',
    $incDir . '/db.php',
    $incDir . '/auth.php',
    $incDir . '/helpers.php'
];
foreach ($files as $file) {
    $exists = file_exists($file);
    // show relative path for readability
    $show = str_replace(__DIR__ . DIRECTORY_SEPARATOR, '', $file);
    echo "$show: " . ($exists ? "✅" : "❌") . "<br>";
}
echo "<br>";

// Test 3: Config Constants
echo "<h2>3. Config Constants</h2>";
if (file_exists($incDir . '/config.php')) {
    require_once $incDir . '/config.php';
    echo "DB_HOST: " . (defined('DB_HOST') ? DB_HOST : "❌ Not defined") . "<br>";
    echo "DB_USER: " . (defined('DB_USER') ? DB_USER : "❌ Not defined") . "<br>";
    echo "DB_PASS: " . (defined('DB_PASS') ? (DB_PASS === '' ? '(empty)' : '***') : "❌ Not defined") . "<br>";
    echo "DB_NAME: " . (defined('DB_NAME') ? DB_NAME : "❌ Not defined") . "<br>";
    echo "APP_NAME: " . (defined('APP_NAME') ? APP_NAME : "❌ Not defined") . "<br>";
} else {
    echo "❌ config.php not found<br>";
}
echo "<br>";

// Test 4: Database Connection
echo "<h2>4. Database Connection</h2>";
if (file_exists($incDir . '/config.php') && defined('DB_HOST') && defined('DB_USER') && defined('DB_NAME')) {
    try {
        // Remove error suppression so connection errors surface
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            echo "❌ Connection Failed<br>";
            echo "Error: " . $conn->connect_error . "<br>";
            echo "<br>";
            echo "<strong>Kemungkinan masalah:</strong><br>";
            echo "1. Database 'web_markazlugoh' belum dibuat<br>";
            echo "2. Username/password database salah<br>";
            echo "3. MySQL service tidak berjalan<br>";
            echo "<br>";
            echo "<strong>Solusi:</strong><br>";
            echo "1. Buka phpMyAdmin: <a href='http://localhost/phpmyadmin' target='_blank'>http://localhost/phpmyadmin</a><br>";
            echo "2. Buat database dengan nama: <strong>web_markazlugoh</strong><br>";
            echo "3. Import file database.sql ke database tersebut<br>";
        } else {
            echo "✅ Database Connection: OK<br>";
            echo "Database: " . DB_NAME . "<br>";
            
            // Test query
            $result = $conn->query("SHOW TABLES");
            if ($result) {
                $tables = [];
                while ($row = $result->fetch_array()) {
                    $tables[] = $row[0];
                }
                echo "Tables found: " . count($tables) . "<br>";
                if (count($tables) > 0) {
                    echo "✅ Database sudah diimport<br>";
                } else {
                    echo "⚠️ Database kosong - perlu import database.sql<br>";
                }
            }
            $conn->close();
        }
    } catch (Exception $e) {
        echo "❌ Exception: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Config tidak lengkap<br>";
}
echo "<br>";

// Test 5: Include Test
echo "<h2>5. Include Test</h2>";
try {
    require_once $incDir . '/config.php';
    echo "✅ config.php: OK<br>";
    
    require_once $incDir . '/db.php';
    echo "✅ db.php: OK<br>";
    
    require_once $incDir . '/auth.php';
    echo "✅ auth.php: OK<br>";
    
    // Test functions
    if (function_exists('isLoggedIn')) {
        echo "✅ Function isLoggedIn(): OK<br>";
    } else {
        echo "❌ Function isLoggedIn(): Not found<br>";
    }
    
    if (function_exists('redirectByRole')) {
        echo "✅ Function redirectByRole(): OK<br>";
    } else {
        echo "❌ Function redirectByRole(): Not found<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
} catch (Error $e) {
    echo "❌ Fatal Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}
echo "<br>";

// Test 6: Session
echo "<h2>6. Session Test</h2>";
// session already started at the top; just report status
echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? "✅ Active" : "❌ Not Active") . "<br>";
echo "Session ID: " . session_id() . "<br>";
echo "<br>";

// Summary
echo "<h2>📋 Summary</h2>";
echo "<p><strong>Jika semua test ✅, coba akses:</strong></p>";
echo "<ul>";
echo "<li><a href='index.php'>index.php</a></li>";
echo "<li><a href='auth/login.php'>auth/login.php</a></li>";
echo "</ul>";
echo "<p><strong style='color: red;'>⚠️ HAPUS FILE INI SETELAH SELESAI!</strong></p>";

