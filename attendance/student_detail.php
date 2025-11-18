<?php
// attendance/student_detail.php
// Detail riwayat absensi satu siswa untuk satu mapel

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireRole(['guru']);

$db     = getDB();
$guruId = (int)($_SESSION['user_id'] ?? 0);

$studentId = (int)($_GET['student_id'] ?? 0);
$subjectId = (int)($_GET['subject_id'] ?? 0);
$dateFrom  = trim($_GET['from'] ?? '');
$dateTo    = trim($_GET['to'] ?? '');

if ($dateFrom === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = date('Y-m-d', strtotime('-30 days'));
}
if ($dateTo === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = date('Y-m-d');
}

if ($studentId <= 0 || $subjectId <= 0) {
    echo "Parameter tidak lengkap.";
    exit;
}

// cek bahwa guru memang mengajar subject ini
$sub = null;
$st = $db->prepare("
    SELECT s.id, s.nama_mapel, s.class_id, c.nama_kelas
    FROM subjects s
    JOIN classes c ON s.class_id = c.id
    WHERE s.id = ? AND s.guru_id = ?
    LIMIT 1
");
if ($st) {
    $st->bind_param("ii", $subjectId, $guruId);
    $st->execute();
    $rs = $st->get_result();
    $sub = $rs ? $rs->fetch_assoc() : null;
    $st->close();
}

if (!$sub) {
    echo "Anda tidak berhak melihat data ini.";
    exit;
}

// info siswa
$stu = null;
$qs = $db->prepare("SELECT id, nama, email FROM users WHERE id = ? LIMIT 1");
if ($qs) {
    $qs->bind_param("i", $studentId);
    $qs->execute();
    $rstu = $qs->get_result();
    $stu = $rstu ? $rstu->fetch_assoc() : null;
    $qs->close();
}

if (!$stu) {
    echo "Siswa tidak ditemukan.";
    exit;
}

// ambil riwayat
$rows = [];
$q = $db->prepare("
    SELECT date, status, note
    FROM attendance
    WHERE student_id = ? AND subject_id = ? AND date BETWEEN ? AND ?
    ORDER BY date DESC
");
if ($q) {
    $q->bind_param("iiss", $studentId, $subjectId, $dateFrom, $dateTo);
    $q->execute();
    $rr = $q->get_result();
    $rows = $rr ? $rr->fetch_all(MYSQLI_ASSOC) : [];
    $q->close();
}

$pageTitle = 'Riwayat Absensi Siswa';
$baseUrl   = rtrim(BASE_URL, '/\\');
include __DIR__ . '/../inc/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Riwayat Absensi Siswa</h1>
        <a href="<?php echo $baseUrl; ?>/attendance/report_class.php?subject_id=<?php echo (int)$subjectId; ?>&from=<?php echo urlencode($dateFrom); ?>&to=<?php echo urlencode($dateTo); ?>" class="btn btn-secondary">← Kembali ke Rekap</a>
    </div>

    <div class="card" style="margin-bottom:16px;">
        <p>
            <strong>Siswa:</strong> <?php echo sanitize($stu['nama']); ?> (ID: <?php echo (int)$stu['id']; ?>)<br>
            <strong>Kelas:</strong> <?php echo sanitize($sub['nama_kelas']); ?><br>
            <strong>Mata Pelajaran:</strong> <?php echo sanitize($sub['nama_mapel']); ?><br>
            <strong>Periode:</strong> <?php echo sanitize($dateFrom); ?> s/d <?php echo sanitize($dateTo); ?>
        </p>
    </div>

    <div class="card">
        <?php if (empty($rows)): ?>
            <p>Belum ada data absensi pada periode ini.</p>
        <?php else: ?>
            <table class="table" style="width:100%;">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Status</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $label = [
                        'H' => 'Hadir',
                        'S' => 'Sakit',
                        'I' => 'Izin',
                        'A' => 'Alpa'
                    ];
                    foreach ($rows as $r):
                        $st = $r['status'] ?? 'H';
                    ?>
                        <tr>
                            <td><?php echo sanitize($r['date']); ?></td>
                            <td><?php echo sanitize($label[$st] ?? $st); ?></td>
                            <td><?php echo sanitize($r['note'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
