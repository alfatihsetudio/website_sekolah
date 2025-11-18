<?php
// attendance/my_history.php
// Murid melihat riwayat absensi sendiri

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireRole(['murid']); // hanya murid

$db      = getDB();
$student = (int)($_SESSION['user_id'] ?? 0);

$dateFrom = trim($_GET['from'] ?? '');
$dateTo   = trim($_GET['to'] ?? '');

if ($dateFrom === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = date('Y-m-d', strtotime('-30 days'));
}
if ($dateTo === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = date('Y-m-d');
}

// ambil riwayat semua mapel
$sql = "
    SELECT 
        a.date,
        a.status,
        a.note,
        s.nama_mapel,
        c.nama_kelas
    FROM attendance a
    LEFT JOIN subjects s ON a.subject_id = s.id
    LEFT JOIN classes  c ON a.class_id   = c.id
    WHERE a.student_id = ? AND a.date BETWEEN ? AND ?
    ORDER BY a.date DESC, s.nama_mapel
";
$rows = [];
$st = $db->prepare($sql);
if ($st) {
    $st->bind_param("iss", $student, $dateFrom, $dateTo);
    $st->execute();
    $rs = $st->get_result();
    $rows = $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];
    $st->close();
}

$pageTitle = 'Riwayat Absensi Saya';
$baseUrl   = rtrim(BASE_URL, '/\\');
include __DIR__ . '/../inc/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Riwayat Absensi Saya</h1>
        <a href="<?php echo $baseUrl; ?>/dashboard.php" class="btn btn-secondary">← Kembali ke Dashboard</a>
    </div>

    <div class="card" style="margin-bottom:16px;">
        <form method="GET" class="form-inline">
            <div class="form-group">
                <label>Dari</label>
                <input type="date" name="from" value="<?php echo sanitize($dateFrom); ?>">
            </div>
            <div class="form-group">
                <label>Sampai</label>
                <input type="date" name="to" value="<?php echo sanitize($dateTo); ?>">
            </div>
            <button class="btn btn-primary" type="submit">Filter</button>
        </form>
    </div>

    <div class="card">
        <?php if (empty($rows)): ?>
            <p>Belum ada data absensi pada periode ini.</p>
        <?php else: ?>
            <table class="table" style="width:100%;">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Kelas / Mapel</th>
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
                        $kelas = trim(($r['nama_kelas'] ?? '') . ' — ' . ($r['nama_mapel'] ?? ''));
                    ?>
                        <tr>
                            <td><?php echo sanitize($r['date']); ?></td>
                            <td><?php echo sanitize($kelas); ?></td>
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
