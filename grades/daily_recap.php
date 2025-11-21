<?php
// grades/daily_report.php
// Rekap nilai harian per siswa (guru saja)

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireRole(['guru']);

$db       = getDB();
$baseUrl  = rtrim(BASE_URL, '/\\');
$userId   = (int)($_SESSION['user_id'] ?? 0);
$schoolId = (int)getCurrentSchoolId();

// ------------ Baca & normalisasi tanggal -------------
$today = date('Y-m-d');
$firstThisMonth = date('Y-m-01');

$from = trim($_GET['from'] ?? $firstThisMonth);
$to   = trim($_GET['to']   ?? $today);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = $firstThisMonth;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = $today;

// pastikan from <= to
if (strtotime($from) > strtotime($to)) {
    $tmp  = $from;
    $from = $to;
    $to   = $tmp;
}

$fromDt = $from . ' 00:00:00';
$toDt   = $to   . ' 23:59:59';

$rows        = [];
$errorFetch  = '';
$totalSiswa  = 0;
$totalTugas  = 0;
$totalNilai  = 0.0;

if ($schoolId > 0) {
    $sql = "
        SELECT 
            s.student_id,
            u.nama      AS student_name,
            u.email     AS student_email,
            COUNT(*)    AS tugas_count,
            SUM(s.nilai) AS total_nilai,
            AVG(s.nilai) AS avg_nilai,
            MIN(s.submitted_at) AS first_submit,
            MAX(s.submitted_at) AS last_submit
        FROM submissions s
        JOIN assignments a ON s.assignment_id = a.id
        JOIN subjects    sub ON a.subject_id    = sub.id
        JOIN classes     c   ON a.target_class_id = c.id
        JOIN users       u   ON s.student_id   = u.id
        WHERE sub.guru_id   = ?
          AND c.school_id   = ?
          AND s.submitted_at BETWEEN ? AND ?
        GROUP BY s.student_id, u.nama, u.email
        ORDER BY u.nama ASC
    ";

    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("iiss", $userId, $schoolId, $fromDt, $toDt);
        if ($stmt->execute()) {
            $res  = $stmt->get_result();
            $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        } else {
            $errorFetch = 'Gagal mengeksekusi query rekap nilai.';
        }
        $stmt->close();
    } else {
        $errorFetch = 'Gagal menyiapkan query rekap nilai.';
    }
} else {
    $errorFetch = 'School ID tidak ditemukan. Silakan login ulang.';
}

// hitung ringkasan
foreach ($rows as $r) {
    $totalSiswa++;
    $totalTugas += (int)$r['tugas_count'];
    $totalNilai += (float)$r['total_nilai'];
}

// ------------ Export CSV jika diminta -------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="rekap_nilai_' . $from . '_sd_' . $to . '.csv"');

    $out = fopen('php://output', 'w');
    // BOM biar Excel aman
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // header
    fputcsv($out, ['Siswa', 'Email', 'Jumlah Tugas Dinilai', 'Total Nilai', 'Rata-rata', 'Tanggal Pertama', 'Tanggal Terakhir']);

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['student_name'],
            $r['student_email'],
            (int)$r['tugas_count'],
            number_format((float)$r['total_nilai'], 2, '.', ''),
            number_format((float)$r['avg_nilai'], 2, '.', ''),
            $r['first_submit'],
            $r['last_submit'],
        ]);
    }

    fclose($out);
    exit;
}

$pageTitle = 'Rekap Nilai Harian';
include __DIR__ . '/../inc/header.php';
?>

<style>
.filter-bar {
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    align-items:flex-end;
    margin-bottom:12px;
}
.filter-group {
    display:flex;
    flex-direction:column;
    font-size:0.86rem;
}
.filter-group label {
    margin-bottom:2px;
    color:#4b5563;
}
.summary-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
    gap:12px;
    margin-bottom:16px;
}
.summary-card {
    border-radius:14px;
    padding:10px 12px;
    border:1px solid #e5e7eb;
    background:linear-gradient(135deg,#f9fafb,#eef2ff);
}
.summary-title {
    font-size:0.82rem;
    color:#6b7280;
}
.summary-value {
    font-size:1.5rem;
    font-weight:600;
}
.summary-sub {
    font-size:0.8rem;
    color:#6b7280;
}
.badge-range {
    display:inline-block;
    padding:3px 8px;
    background:#e0f2fe;
    color:#0369a1;
    border-radius:999px;
    font-size:0.8rem;
}
.table-small th, .table-small td {
    font-size:0.88rem;
}
.btn-pill {
    border-radius:999px;
}
</style>

<div class="container">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
        <div>
            <h1 style="margin:0 0 4px 0;">Rekap Nilai Harian</h1>
            <div class="badge-range">
                Periode: <?php echo sanitize($from); ?> s/d <?php echo sanitize($to); ?>
            </div>
        </div>
        <a href="<?php echo $baseUrl; ?>/dashboard/guru.php" class="btn btn-secondary">
            ← Kembali ke Dashboard Guru
        </a>
    </div>

    <div class="card" style="margin-bottom:14px;">
        <form class="filter-bar" method="get">
            <div class="filter-group">
                <label for="from">Dari tanggal</label>
                <input type="date" id="from" name="from" value="<?php echo sanitize($from); ?>">
            </div>
            <div class="filter-group">
                <label for="to">Sampai tanggal</label>
                <input type="date" id="to" name="to" value="<?php echo sanitize($to); ?>">
            </div>
            <div class="filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary btn-pill">Terapkan</button>
            </div>
            <?php if (!empty($rows)): ?>
                <div class="filter-group" style="margin-left:auto;">
                    <label>&nbsp;</label>
                    <a href="?from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>&export=csv"
                       class="btn btn-secondary btn-pill">
                        ⬇️ Download CSV
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <?php if (!empty($errorFetch)): ?>
        <div class="alert alert-error"><?php echo sanitize($errorFetch); ?></div>
    <?php endif; ?>

    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-title">Jumlah siswa dinilai</div>
            <div class="summary-value"><?php echo (int)$totalSiswa; ?></div>
            <div class="summary-sub">Dalam periode yang dipilih.</div>
        </div>
        <div class="summary-card">
            <div class="summary-title">Total tugas dinilai</div>
            <div class="summary-value"><?php echo (int)$totalTugas; ?></div>
            <div class="summary-sub">Semua tugas yang punya nilai.</div>
        </div>
        <div class="summary-card">
            <div class="summary-title">Total akumulasi nilai</div>
            <div class="summary-value"><?php echo number_format($totalNilai, 2); ?></div>
            <div class="summary-sub">Penjumlahan nilai semua siswa.</div>
        </div>
    </div>

    <div class="card">
        <?php if (empty($rows)): ?>
            <p style="margin:0;">Belum ada nilai pada periode ini.</p>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="table table-small" style="width:100%;">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Siswa</th>
                            <th>Jumlah tugas</th>
                            <th>Total nilai</th>
                            <th>Rata-rata</th>
                            <th>Terakhir mengumpulkan</th>
                            <th style="width:110px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $i => $r): ?>
                            <tr>
                                <td><?php echo (int)($i + 1); ?></td>
                                <td><?php echo sanitize($r['student_name']); ?></td>
                                <td><?php echo (int)$r['tugas_count']; ?></td>
                                <td><?php echo number_format((float)$r['total_nilai'], 2); ?></td>
                                <td><?php echo number_format((float)$r['avg_nilai'], 2); ?></td>
                                <td><?php echo sanitize($r['last_submit']); ?></td>
                                <td>
                                    <a class="btn btn-secondary btn-sm btn-pill"
                                       href="<?php echo $baseUrl; ?>/grades/daily_report_detail.php?student_id=<?php echo (int)$r['student_id']; ?>&from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>">
                                        Detail
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
