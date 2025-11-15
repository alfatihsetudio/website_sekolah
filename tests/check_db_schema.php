<?php
// tests/check_db_schema.php
error_reporting(E_ALL);
ini_set('display_errors',1);

$configPath = __DIR__ . '/../inc/config.php';
if (file_exists($configPath)) {
    require_once $configPath;
} else {
    // fallback, ubah jika DB anda beda
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'web_markazlugoh');
}

$mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) {
    echo "<h2>GAGAL KONEKSI DATABASE</h2>";
    echo "Errno: {$mysqli->connect_errno} <br> Error: {$mysqli->connect_error}";
    exit;
}
echo "<h2>Koneksi DB OK</h2>";

$expected_tables = [
    'users','classes','class_user','subjects','materials',
    'assignments','submissions','attendance','notifications','file_uploads','teaching_schedule','teaching_logs'
];

echo "<h3>Daftar tabel (yang ditemukan)</h3><ul>";
$res = $mysqli->query("SHOW TABLES");
$present = [];
while ($r = $res->fetch_array()) {
    $present[] = $r[0];
    echo "<li>{$r[0]}</li>";
}
echo "</ul>";

echo "<h3>Periksa tabel penting & kolom yang sering dibutuhkan</h3>";
$check_columns = [
    'users' => ['id','email','password','nama','role','created_at','updated_at'],
    'materials' => ['id','subject_id','judul','konten','file_id','video_link','created_by','created_at','updated_at'],
    'assignments' => ['id','subject_id','judul','deskripsi','file_id','deadline','created_by','created_at','updated_at'],
    'submissions' => ['id','assignment_id','student_id','file_id','link_drive','submitted_at','nilai','graded_at','graded_by'],
    'file_uploads' => ['id','original_name','stored_name','file_path','mime_type','file_size','uploader_id','uploaded_at'],
    'classes' => ['id','nama_kelas','deskripsi','guru_id','created_at']
];

foreach ($check_columns as $table => $cols) {
    echo "<h4>Tabel: $table</h4>";
    if (!in_array($table, $present)) {
        echo "<div style='color:orange;'>TIDAK DITEMUKAN</div>";
        continue;
    }
    $resCols = $mysqli->query("SHOW COLUMNS FROM `$table`");
    $have = [];
    while ($c = $resCols->fetch_assoc()) {
        $have[] = $c['Field'];
    }
    echo "<ul>";
    foreach ($cols as $col) {
        if (in_array($col, $have)) {
            echo "<li style='color:green;'>$col (OK)</li>";
        } else {
            echo "<li style='color:red;'>$col (MISSING)</li>";
        }
    }
    echo "</ul>";
}

echo "<h3>Jika banyak kolom missing → import file database.sql atau jalankan ALTER TABLE</h3>";
echo "<p>File database.sql ada di root proyek? (cek web_MG/database.sql)</p>";

$mysqli->close();
