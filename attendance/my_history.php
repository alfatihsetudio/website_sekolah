<?php
// attendance/my_history.php
// Murid melihat riwayat absensi sendiri dalam bentuk kalender tahunan berwarna
// Diperbaiki: semua data dibatasi oleh school_id (1 admin = 1 dunia)

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireRole(['murid']); // hanya murid

$db       = getDB();
$baseUrl  = rtrim(BASE_URL, '/\\');
$student  = (int)($_SESSION['user_id'] ?? 0);

// -------------------------------------------------
// Ambil school_id dari sesi / helper
// Konsep: 1 admin = 1 dunia = 1 school_id
// Semua query harus dibatasi ke school_id ini
// -------------------------------------------------
if (!function_exists('getCurrentSchoolId')) {
    // fallback kalau belum ada helper, tapi di project kamu sudah ada
    function getCurrentSchoolId() {
        return (int)($_SESSION['school_id'] ?? 0);
    }
}
$schoolId = getCurrentSchoolId();

// Kalau school_id tidak valid, jangan tampilkan apa pun
if ($schoolId <= 0) {
    $rows = [];
    $dayStatus = [];
    $totalDays = ['H' => 0,'S' => 0,'I' => 0,'A' => 0];
    $dateFrom = date('Y-01-01');
    $dateTo   = date('Y-12-31');
    $year     = (int)date('Y');
} else {

    // =======================
    // Filter tanggal
    // =======================
    $todayYear = (int)date('Y');

    $dateFrom = trim($_GET['from'] ?? '');
    $dateTo   = trim($_GET['to'] ?? '');

    // default: 1 Januari s/d 31 Desember tahun ini
    if ($dateFrom === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $dateFrom = sprintf('%04d-01-01', $todayYear);
    }
    if ($dateTo === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $dateTo = sprintf('%04d-12-31', $todayYear);
    }

    // pastikan from <= to
    if ($dateFrom > $dateTo) {
        $tmp      = $dateFrom;
        $dateFrom = $dateTo;
        $dateTo   = $tmp;
    }

    // tahun acuan untuk kalender (diambil dari from)
    $year = (int)substr($dateFrom, 0, 4);
    if ($year < 2000 || $year > 2100) {
        $year = $todayYear;
    }

    // =======================
    // Ambil data absensi & olah jadi per-tanggal
    // DIBATASI school_id:
    //   - classes.school_id = :schoolId
    //   - user.school_id    = :schoolId
    // =======================
    $sql = "
        SELECT 
            a.date,
            a.status
        FROM attendance a
        INNER JOIN users u   ON a.student_id = u.id
        INNER JOIN classes c ON a.class_id   = c.id
        WHERE a.student_id   = ?
          AND u.school_id    = ?
          AND c.school_id    = ?
          AND a.date BETWEEN ? AND ?
        ORDER BY a.date ASC
    ";
    $rows = [];
    $st   = $db->prepare($sql);
    if ($st) {
        $st->bind_param("iiiss", $student, $schoolId, $schoolId, $dateFrom, $dateTo);
        $st->execute();
        $rs   = $st->get_result();
        $rows = $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];
        $st->close();
    }

    // Map tanggal => status agregat
    // dan hitung total hari per status
    $dayStatus = []; // 'Y-m-d' => 'H'|'S'|'I'|'A'
    $totalDays = [
        'H' => 0, // Hadir
        'S' => 0, // Sakit
        'I' => 0, // Izin
        'A' => 0, // Alpa
    ];

    // Prioritas status per hari: A > S > I > H
    function pickStatus($current, $new) {
        $prio = ['H' => 1, 'I' => 2, 'S' => 3, 'A' => 4];
        if (!isset($prio[$current])) return $new;
        if (!isset($prio[$new]))     return $current;
        return ($prio[$new] > $prio[$current]) ? $new : $current;
    }

    foreach ($rows as $r) {
        $d  = $r['date'];
        $st = strtoupper(trim($r['status'] ?? 'H'));

        if (!in_array($st, ['H','S','I','A'], true)) {
            $st = 'H';
        }

        if (!isset($dayStatus[$d])) {
            $dayStatus[$d] = $st;
        } else {
            $dayStatus[$d] = pickStatus($dayStatus[$d], $st);
        }
    }

    // Hitung total hari per status
    foreach ($dayStatus as $d => $st) {
        if (isset($totalDays[$st])) {
            $totalDays[$st]++;
        }
    }
}

// =======================
// Data tampilan
// =======================
$pageTitle = 'Riwayat Absensi Saya';
include __DIR__ . '/../inc/header.php';

// Nama bulan & hari
$bulanNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];
$dayHeaders = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

$todayYmd = date('Y-m-d');
?>

<style>
.attendance-container {
    max-width: 1100px;
    margin: 0 auto;
}

/* Header + filter */
.att-header {
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:10px;
    margin-bottom:12px;
    flex-wrap:wrap;
}
.att-header-title {
    font-size:1.35rem;
    font-weight:600;
}
.att-header-sub {
    font-size:0.9rem;
    color:#6b7280;
}

/* Filter form */
.att-filter-card {
    margin-bottom:12px;
    padding:10px 12px;
    border-radius:12px;
    border:1px solid #e5e7eb;
    background:#ffffff;
}
.att-filter-form {
    display:flex;
    flex-wrap:wrap;
    gap:8px 16px;
    align-items:flex-end;
}
.att-filter-group {
    display:flex;
    flex-direction:column;
    gap:4px;
    font-size:0.85rem;
    color:#4b5563;
}
.att-filter-group input[type="date"] {
    padding:4px 8px;
    font-size:0.85rem;
    border-radius:8px;
    border:1px solid #d1d5db;
}
.att-filter-form .btn {
    font-size:0.85rem;
    padding:6px 12px;
}

/* Legend + summary */
.att-legend {
    margin-bottom:10px;
    padding:8px 10px;
    border-radius:12px;
    border:1px solid #e5e7eb;
    background:#f9fafb;
    font-size:0.85rem;
    color:#4b5563;
}
.att-legend-row {
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin-top:6px;
}
.att-legend-item {
    display:inline-flex;
    align-items:center;
    gap:6px;
}
.att-legend-box {
    width:16px;
    height:16px;
    border-radius:4px;
    border:1px solid transparent;
}

/* warna legend (harus sama dengan kalender di bawah) */
.att-present { background:#bfdbfe; border-color:#60a5fa; } /* Hadir - biru muda */
.att-sick    { background:#bbf7d0; border-color:#22c55e; } /* Sakit - hijau */
.att-leave   { background:#fef9c3; border-color:#eab308; } /* Izin - kuning */
.att-absent  { background:#fecaca; border-color:#ef4444; } /* Alpa - merah */
.att-none    { background:#e5e7eb; border-color:#d1d5db; } /* Tidak ada data */

/* summary chip */
.att-summary-row {
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    margin-top:6px;
}
.att-summary-chip {
    border-radius:999px;
    padding:4px 10px;
    font-size:0.8rem;
    display:inline-flex;
    align-items:center;
    gap:6px;
    background:#ffffff;
    border:1px solid #e5e7eb;
}

/* Grid 12 bulan */
.year-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));
    gap:12px;
}

.month-card {
    border-radius:14px;
    border:1px solid #e5e7eb;
    background:#ffffff;
    box-shadow:0 1px 3px rgba(15,23,42,0.06);
    padding:8px 10px;
}
.month-header {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:4px;
}
.month-name {
    font-size:0.95rem;
    font-weight:600;
}
.month-year {
    font-size:0.75rem;
    color:#9ca3af;
}

/* Tabel mini per bulan */
.month-table {
    width:100%;
    border-collapse:collapse;
    table-layout:fixed;
    font-size:0.75rem;
}
.month-table th,
.month-table td {
    padding:3px 2px;
    text-align:center;
    vertical-align:middle;
}
.month-table th {
    font-weight:600;
    color:#6b7280;
}
.month-table td.empty {
    color:transparent;
}

/* Sel hari */
.day-cell {
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap:2px;
}

/* Kotak tanggal */
.day-box {
    width:22px;
    height:22px;
    border-radius:6px;
    font-size:0.74rem;
    display:flex;
    align-items:center;
    justify-content:center;
    border:1px solid transparent;
    color:#111827;
    background:#ffffff;
}
.day-today {
    border-color:#4f46e5;
    box-shadow:0 0 0 1px rgba(79,70,229,0.15);
    font-weight:600;
}

/* Warna status hari (harus match legend) */
.day-box-present { background:#bfdbfe; border-color:#60a5fa; } /* H */
.day-box-sick    { background:#bbf7d0; border-color:#22c55e; } /* S */
.day-box-leave   { background:#fef9c3; border-color:#eab308; } /* I */
.day-box-absent  { background:#fecaca; border-color:#ef4444; } /* A */

/* Hover */
.day-box:hover {
    box-shadow:0 0 0 1px rgba(148,163,184,0.5);
}

/* Responsif */
@media (max-width:768px) {
    .year-grid {
        grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
    }
}
</style>

<div class="attendance-container">

    <div class="att-header">
        <div>
            <div class="att-header-title">Riwayat Absensi Saya</div>
            <div class="att-header-sub">
                Tampilan kalender tahun <?php echo (int)$year; ?> dengan warna sesuai status kehadiran.
            </div>
        </div>
        <a href="<?php echo $baseUrl; ?>/dashboard/murid.php" class="btn btn-secondary" style="font-size:0.85rem;">
            ← Kembali ke Dashboard
        </a>
    </div>

    <?php if ($schoolId <= 0): ?>
        <div class="card">
            <p>School ID tidak valid. Silakan logout lalu login kembali.</p>
        </div>
    <?php else: ?>

        <div class="att-filter-card">
            <form method="GET" class="att-filter-form">
                <div class="att-filter-group">
                    <label for="from">Dari tanggal</label>
                    <input type="date" id="from" name="from" value="<?php echo sanitize($dateFrom); ?>">
                </div>
                <div class="att-filter-group">
                    <label for="to">Sampai tanggal</label>
                    <input type="date" id="to" name="to" value="<?php echo sanitize($dateTo); ?>">
                </div>
                <button class="btn btn-primary" type="submit">Terapkan</button>
            </form>
            <div style="margin-top:6px;font-size:0.8rem;color:#6b7280;">
                Default: satu tahun penuh. Kamu bisa atur rentang tanggal sesuai kebutuhan.
            </div>
        </div>

        <div class="att-legend">
            <div>Arti warna:</div>
            <div class="att-legend-row">
                <div class="att-legend-item">
                    <span class="att-legend-box att-present"></span>
                    <span>Hadir (biru muda)</span>
                </div>
                <div class="att-legend-item">
                    <span class="att-legend-box att-sick"></span>
                    <span>Sakit (hijau)</span>
                </div>
                <div class="att-legend-item">
                    <span class="att-legend-box att-leave"></span>
                    <span>Izin (kuning)</span>
                </div>
                <div class="att-legend-item">
                    <span class="att-legend-box att-absent"></span>
                    <span>Alpa (merah)</span>
                </div>
                <div class="att-legend-item">
                    <span class="att-legend-box att-none"></span>
                    <span>Tidak ada data absensi</span>
                </div>
            </div>

            <div class="att-summary-row">
                <div class="att-summary-chip">
                    <span class="att-legend-box att-present"></span>
                    <span>Hadir: <?php echo (int)$totalDays['H']; ?> hari</span>
                </div>
                <div class="att-summary-chip">
                    <span class="att-legend-box att-sick"></span>
                    <span>Sakit: <?php echo (int)$totalDays['S']; ?> hari</span>
                </div>
                <div class="att-summary-chip">
                    <span class="att-legend-box att-leave"></span>
                    <span>Izin: <?php echo (int)$totalDays['I']; ?> hari</span>
                </div>
                <div class="att-summary-chip">
                    <span class="att-legend-box att-absent"></span>
                    <span>Alpa: <?php echo (int)$totalDays['A']; ?> hari</span>
                </div>
            </div>
        </div>

        <div class="year-grid">
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <?php
                $firstDayTs  = mktime(0, 0, 0, $m, 1, $year);
                $daysInMonth = (int)date('t', $firstDayTs);
                $startDow    = (int)date('N', $firstDayTs); // 1=Senin ... 7=Minggu
                ?>
                <div class="month-card">
                    <div class="month-header">
                        <div class="month-name"><?php echo $bulanNames[$m]; ?></div>
                        <div class="month-year"><?php echo (int)$year; ?></div>
                    </div>

                    <table class="month-table">
                        <thead>
                            <tr>
                                <?php foreach ($dayHeaders as $dh): ?>
                                    <th><?php echo $dh; ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $currentDay = 1;
                        $cell       = 1;
                        while ($currentDay <= $daysInMonth) {
                            echo '<tr>';
                            for ($col = 1; $col <= 7; $col++, $cell++) {

                                if ($cell < $startDow || $currentDay > $daysInMonth) {
                                    echo '<td class="empty">&nbsp;</td>';
                                    continue;
                                }

                                $dateStr = sprintf('%04d-%02d-%02d', $year, $m, $currentDay);
                                $isToday = ($dateStr === $todayYmd);

                                $status  = $dayStatus[$dateStr] ?? null;
                                $classes = 'day-box';

                                if ($isToday) {
                                    $classes .= ' day-today';
                                }

                                if ($status === 'H') {
                                    $classes .= ' day-box-present';
                                } elseif ($status === 'S') {
                                    $classes .= ' day-box-sick';
                                } elseif ($status === 'I') {
                                    $classes .= ' day-box-leave';
                                } elseif ($status === 'A') {
                                    $classes .= ' day-box-absent';
                                }

                                echo '<td>';
                                echo '<div class="day-cell">';
                                echo '<div class="' . $classes . '">';
                                echo (int)$currentDay;
                                echo '</div>';
                                echo '</div>';
                                echo '</td>';

                                $currentDay++;
                            }
                            echo '</tr>';
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
