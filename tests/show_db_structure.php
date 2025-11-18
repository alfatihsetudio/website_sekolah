<?php
// tools/show_db_structure.php
// Menampilkan seluruh struktur database (tabel + kolom + foreign key)

require_once __DIR__ . '/../inc/db.php';

$db = getDB();

// Ambil semua tabel
$tables = [];
$res = $db->query("SHOW TABLES");
while ($row = $res->fetch_array()) {
    $tables[] = $row[0];
}

echo "<h1>Struktur Database: " . DB_NAME . "</h1>";
echo "<hr>";

foreach ($tables as $table) {
    echo "<h2>📌 Tabel: <span style='color:blue'>$table</span></h2>";

    // Struktur kolom
    echo "<h3>Kolom:</h3>";
    $desc = $db->query("DESCRIBE `$table`");
    if ($desc) {
        echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;'>";
        echo "<tr style='background:#eee'>
                <th>Field</th><th>Type</th><th>Null</th>
                <th>Key</th><th>Default</th><th>Extra</th>
              </tr>";
        while ($col = $desc->fetch_assoc()) {
            echo "<tr>
                    <td>{$col['Field']}</td>
                    <td>{$col['Type']}</td>
                    <td>{$col['Null']}</td>
                    <td>{$col['Key']}</td>
                    <td>{$col['Default']}</td>
                    <td>{$col['Extra']}</td>
                  </tr>";
        }
        echo "</table>";
    }

    // Foreign key relations
    echo "<h3>Foreign Key:</h3>";

    $fk = $db->query("
        SELECT
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM
            INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE
            TABLE_SCHEMA = '" . DB_NAME . "'
            AND TABLE_NAME = '$table'
            AND REFERENCED_TABLE_NAME IS NOT NULL
    ");

    if ($fk && $fk->num_rows > 0) {
        echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;'>";
        echo "<tr style='background:#eef'>
                <th>Constraint</th>
                <th>Column</th>
                <th>Ref Table</th>
                <th>Ref Column</th>
              </tr>";

        while ($r = $fk->fetch_assoc()) {
            echo "<tr>
                    <td>{$r['CONSTRAINT_NAME']}</td>
                    <td>{$r['COLUMN_NAME']}</td>
                    <td>{$r['REFERENCED_TABLE_NAME']}</td>
                    <td>{$r['REFERENCED_COLUMN_NAME']}</td>
                  </tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:gray'>Tidak ada foreign key.</p>";
    }

    echo "<hr>";
}

echo "<p><b>Selesai.</b></p>";
?>
