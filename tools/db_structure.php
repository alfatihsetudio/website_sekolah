<?php
// web_MG/tools/db_structure.php
// Menampilkan daftar tabel + SHOW CREATE TABLE masing-masing
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

$db = getDB();

echo "<h2>Database: " . htmlspecialchars($db->query("SELECT DATABASE()")->fetch_row()[0]) . "</h2>";

$tables = [];
$res = $db->query("SHOW TABLES");
if ($res) {
    while ($r = $res->fetch_row()) {
        $tables[] = $r[0];
    }
}

if (empty($tables)) {
    echo "<p>No tables found.</p>";
    exit;
}

echo "<ul>";
foreach ($tables as $t) {
    echo "<li><a href=\"#tbl_".htmlspecialchars($t)."\">".htmlspecialchars($t)."</a></li>";
}
echo "</ul>";

foreach ($tables as $t) {
    echo "<h3 id=\"tbl_".htmlspecialchars($t)."\">Table: " . htmlspecialchars($t) . "</h3>";
    // columns
    echo "<h4>Columns</h4><table border='1' cellpadding='6' style='border-collapse:collapse;'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    $colres = $db->query("SHOW COLUMNS FROM `{$t}`");
    if ($colres) {
        while ($c = $colres->fetch_assoc()) {
            echo "<tr>";
            echo "<td>".htmlspecialchars($c['Field'])."</td>";
            echo "<td>".htmlspecialchars($c['Type'])."</td>";
            echo "<td>".htmlspecialchars($c['Null'])."</td>";
            echo "<td>".htmlspecialchars($c['Key'])."</td>";
            echo "<td>".htmlspecialchars($c['Default'])."</td>";
            echo "<td>".htmlspecialchars($c['Extra'])."</td>";
            echo "</tr>";
        }
    }
    echo "</table>";

    // indexes / constraints (key_column_usage)
    echo "<h4>Foreign Keys / constraints (information_schema)</h4>";
    $stmt = $db->prepare("SELECT constraint_name, column_name, referenced_table_name, referenced_column_name FROM information_schema.key_column_usage WHERE table_schema = DATABASE() AND table_name = ? AND referenced_table_name IS NOT NULL");
    $stmt->bind_param("s", $t);
    $stmt->execute();
    $kr = $stmt->get_result();
    if ($kr && $kr->num_rows) {
        echo "<ul>";
        while ($k = $kr->fetch_assoc()) {
            echo "<li>".htmlspecialchars($k['constraint_name']).": ".htmlspecialchars($k['column_name'])." -> ".htmlspecialchars($k['referenced_table_name'])."(".htmlspecialchars($k['referenced_column_name']).")</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No foreign keys.</p>";
    }
    $stmt->close();

    echo "<h4>SHOW CREATE TABLE</h4><pre style='background:#f5f5f5;padding:10px;border:1px solid #ddd;'>";
    $r = $db->query("SHOW CREATE TABLE `{$t}`");
    if ($r && $rr = $r->fetch_assoc()) {
        echo htmlspecialchars($rr['Create Table']);
    } else {
        echo "Unable to fetch SHOW CREATE TABLE.";
    }
    echo "</pre>";
}

echo "<p>Done.</p>";
