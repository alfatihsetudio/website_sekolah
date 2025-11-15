<?php
/**
 * API — Get Subjects by Class (Guru Only)
 * Return JSON list of subjects (id, nama_mapel) milik guru di kelas tertentu
 */

require_once __DIR__ . '/../inc/auth.php';
requireRole(['guru']);

header('Content-Type: application/json');

$db = getDB();
$guruId = $_SESSION['user_id'];
$classId = (int)($_GET['class_id'] ?? 0);

// Validate class_id
if ($classId === 0) {
    echo json_encode([]);
    exit;
}

// Check kelas valid (harus ada mapelnya dan guru memang memiliki mapel di kelas ini)
$sql = "SELECT DISTINCT id, nama_mapel
        FROM subjects
        WHERE class_id = $classId
        AND guru_id = $guruId
        ORDER BY nama_mapel ASC";

$result = $db->query($sql);

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'id' => (int)$row['id'],
        'nama_mapel' => $row['nama_mapel']
    ];
}

// Return JSON
echo json_encode($data);
exit;
