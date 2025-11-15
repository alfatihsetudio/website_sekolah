<?php
/**
 * Script untuk membuat contoh akun untuk semua role
 * Hapus file ini setelah selesai!
 * Akses: http://localhost/web_MG/create_example_users.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/db.php';

// Data contoh akun
$exampleUsers = [
    [
        'nama' => 'Administrator',
        'email' => 'admin@example.com',
        'password' => 'admin123',
        'role' => 'admin'
    ],
    [
        'nama' => 'Budi Santoso',
        'email' => 'guru@example.com',
        'password' => 'guru123',
        'role' => 'guru'
    ],
    [
        'nama' => 'Andi Pratama',
        'email' => 'murid@example.com',
        'password' => 'murid123',
        'role' => 'murid'
    ]
];

echo "<h1>📋 Contoh Akun untuk Semua Role</h1>";
echo "<p>Script ini akan membuat/mengupdate contoh akun untuk testing.</p>";
echo "<hr>";

try {
    $db = getDB();
    $created = 0;
    $updated = 0;
    
    foreach ($exampleUsers as $user) {
        $nama = $user['nama'];
        $email = $user['email'];
        $password = $user['password'];
        $role = $user['role'];
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Escape input
        $namaEscaped = $db->real_escape_string($nama);
        $emailEscaped = $db->real_escape_string($email);
        $roleEscaped = $db->real_escape_string($role);
        
        // Cek apakah email sudah ada
        $check = $db->query("SELECT id, nama, role FROM users WHERE email = '$emailEscaped'");
        
        if ($check->num_rows > 0) {
            // Update user yang sudah ada
            $existing = $check->fetch_assoc();
            $sql = "UPDATE users SET password = '$hashedPassword', nama = '$namaEscaped', role = '$roleEscaped' WHERE email = '$emailEscaped'";
            
            if ($db->query($sql)) {
                echo "<div style='background: #fef3c7; padding: 15px; border-radius: 8px; margin-bottom: 15px;'>";
                echo "<h3 style='margin-top: 0;'>🔄 Updated: $nama</h3>";
                echo "<p><strong>Email:</strong> $email</p>";
                echo "<p><strong>Password:</strong> $password</p>";
                echo "<p><strong>Role:</strong> $role</p>";
                echo "<p style='color: #92400e;'>⚠️ User sudah ada, password dan data diupdate</p>";
                echo "</div>";
                $updated++;
            } else {
                echo "<p style='color: red;'>❌ Gagal update user $email: " . $db->error . "</p>";
            }
        } else {
            // Insert user baru
            $sql = "INSERT INTO users (email, password, nama, role) VALUES ('$emailEscaped', '$hashedPassword', '$namaEscaped', '$roleEscaped')";
            
            if ($db->query($sql)) {
                echo "<div style='background: #d1fae5; padding: 15px; border-radius: 8px; margin-bottom: 15px;'>";
                echo "<h3 style='margin-top: 0;'>✅ Created: $nama</h3>";
                echo "<p><strong>Email:</strong> $email</p>";
                echo "<p><strong>Password:</strong> $password</p>";
                echo "<p><strong>Role:</strong> $role</p>";
                echo "</div>";
                $created++;
            } else {
                echo "<p style='color: red;'>❌ Gagal membuat user $email: " . $db->error . "</p>";
            }
        }
    }
    
    echo "<hr>";
    echo "<h2>📊 Summary</h2>";
    echo "<p>✅ Created: $created user(s)</p>";
    echo "<p>🔄 Updated: $updated user(s)</p>";
    
    echo "<hr>";
    echo "<h2>📝 Daftar Contoh Akun</h2>";
    echo "<div style='background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>";
    
    echo "<h3>👨‍💼 Admin</h3>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>";
    echo "<tr style='background: #f3f4f6;'><th>Email</th><th>Password</th><th>Role</th></tr>";
    echo "<tr><td>admin@example.com</td><td>admin123</td><td>admin</td></tr>";
    echo "</table>";
    echo "<p><strong>Login URL:</strong> <a href='/web_MG/admin_login.php' target='_blank'>/web_MG/admin_login.php</a></p>";
    
    echo "<h3>👨‍🏫 Guru</h3>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>";
    echo "<tr style='background: #f3f4f6;'><th>Email</th><th>Password</th><th>Role</th></tr>";
    echo "<tr><td>guru@example.com</td><td>guru123</td><td>guru</td></tr>";
    echo "</table>";
    echo "<p><strong>Login URL:</strong> <a href='/web_MG/auth/login.php?role=guru' target='_blank'>/web_MG/auth/login.php?role=guru</a></p>";
    
    echo "<h3>🎓 Murid</h3>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>";
    echo "<tr style='background: #f3f4f6;'><th>Email</th><th>Password</th><th>Role</th></tr>";
    echo "<tr><td>murid@example.com</td><td>murid123</td><td>murid</td></tr>";
    echo "</table>";
    echo "<p><strong>Login URL:</strong> <a href='/web_MG/auth/login.php?role=murid' target='_blank'>/web_MG/auth/login.php?role=murid</a></p>";
    
    echo "</div>";
    
    echo "<hr>";
    echo "<h2>🔗 Quick Links</h2>";
    echo "<div style='display: flex; gap: 10px; flex-wrap: wrap;'>";
    echo "<a href='/web_MG/home.php' style='background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🏠 Home</a>";
    echo "<a href='/web_MG/admin_login.php' style='background: #10b981; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>👨‍💼 Login Admin</a>";
    echo "<a href='/web_MG/auth/login.php?role=guru' style='background: #f59e0b; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>👨‍🏫 Login Guru</a>";
    echo "<a href='/web_MG/auth/login.php?role=murid' style='background: #06b6d4; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🎓 Login Murid</a>";
    echo "</div>";
    
    echo "<hr>";
    echo "<p style='color: red;'><strong>⚠️ PENTING: Hapus file ini setelah selesai untuk keamanan!</strong></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
}

