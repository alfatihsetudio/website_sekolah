<?php
// study/calendar.php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/db.php';

requireLogin();

$baseUrl = rtrim(BASE_URL, '/\\');
$userId  = (int)($_SESSION['user_id'] ?? 0);
$db      = getDB();

// PARAM: tahun (default = tahun sekarang)
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
if ($year < 2000 || $year > 2100) {
    $year = (int)date('Y');
}

// Nama bulan & hari
$bulanNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];
$dayHeaders = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

$todayYmd = date('Y-m-d');

// Ambil semua event tahun ini untuk user ini
// eventsByDate['Y-m-d'] = ['holiday' => bool, 'special' => bool, 'warning' => bool]
$eventsByDate = [];
$yearStart = sprintf('%04d-01-01', $year);
$yearEnd   = sprintf('%04d-12-31', $year);

$stmt = $db->prepare("
    SELECT event_date, type
    FROM calendar_events
    WHERE user_id = ? AND event_date BETWEEN ? AND ?
");
if ($stmt) {
    $stmt->bind_param("iss", $userId, $yearStart, $yearEnd);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $d = $row['event_date'];
        $t = $row['type'];

        if (!in_array($t, ['holiday', 'special', 'warning'], true)) {
            continue;
        }

        if (!isset($eventsByDate[$d])) {
            $eventsByDate[$d] = [
                'holiday' => false,
                'special' => false,
                'warning' => false,
            ];
        }

        $eventsByDate[$d][$t] = true;
    }
    $stmt->close();
}

$pageTitle = 'Kalender Tahun ' . $year;
$todayY   = (int)date('Y');
$todayM   = (int)date('n');
$todayD   = (int)date('j');

include __DIR__ . '/../inc/header.php';
?>

<style>
/* ===================== WRAPPER & HEADER ===================== */
.calendar-container {
    max-width: 1100px;
    margin: 0 auto;
}

.calendar-header {
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:10px;
    margin-bottom:12px;
    flex-wrap:wrap;
}
.calendar-title {
    font-size:1.35rem;
    font-weight:600;
}
.calendar-sub {
    font-size:0.9rem;
    color:#6b7280;
    max-width:540px;
}
.calendar-actions {
    display:flex;
    gap:6px;
    flex-wrap:wrap;
}
.calendar-actions .btn {
    padding:5px 10px;
    font-size:0.85rem;
}

/* ===================== LEGEND (dipindah ke atas) ===================== */
.calendar-legend {
    margin-top:4px;
    margin-bottom:12px;
    padding:10px 12px;
    border-radius:12px;
    border:1px solid #e5e7eb;
    background:#f9fafb;
    font-size:0.85rem;
    color:#4b5563;
}
.legend-row {
    display:flex;
    flex-wrap:wrap;
    gap:12px;
    margin-top:6px;
}
.legend-item {
    display:inline-flex;
    align-items:center;
    gap:6px;
}
.legend-dot {
    width:14px;
    height:14px;
    border-radius:999px;
}

/* legend warna, konsisten dengan dot hari */
.legend-holiday {
    background:#81C784;
    border:1px solid #4CAF50;
}
.legend-special {
    background:#FBC02D;
    border:1px solid #F9A825;
}
.legend-warning {
    background:#E53935;
    border:1px solid #C62828;
}

/* Hari ini */
.legend-today {
    border-radius:999px;
    width:14px;
    height:14px;
    border:2px solid #4f46e5;
    box-shadow:0 0 0 1px rgba(79,70,229,0.15);
}

/* ===================== GRID 12 BULAN ===================== */
.year-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
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

/* ===================== MINI CALENDAR ===================== */
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

/* Minggu (kolom terakhir) background merah muda */
.month-table th.sunday-header {
    background:#ffe4ef;
}
.month-table td.sunday-cell {
    background:#ffe4ef;
}

/* ===================== HARI / TANGGAL ===================== */
.day-cell {
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap:2px;
}

.day-link {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:22px;
    height:22px;
    border-radius:999px;
    text-decoration:none;
    font-size:0.72rem;
    color:#111827;
    transition:0.12s ease;
}
.day-link:hover {
    background:#e5e7eb;
}

.day-today {
    border:1px solid #4f46e5;
    box-shadow:0 0 0 1px rgba(79,70,229,0.15);
    font-weight:600;
}

/* ===================== CARD & DOT STATUS JADWAL ===================== */

.day-dots-wrapper {
    height:12px; /* tinggi stabil meski tidak ada jadwal */
    display:flex;
    align-items:center;
    justify-content:center;
}

.day-dots-card {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:3px;
    padding:2px 5px;
    border-radius:999px;
    background:#e5e7eb;
}

/* titik */
.day-dot {
    width:6px;
    height:6px;
    border-radius:999px;
}

/* HOLIDAY - hijau lembut */
.day-dot-holiday {
    background:#81C784;      /* Green 300 */
}

/* SPECIAL - kuning emas */
.day-dot-special {
    background:#FBC02D;      /* Yellow 600 */
}

/* WARNING - merah */
.day-dot-warning {
    background:#E53935;      /* Red 600 */
}

/* ===================== RESPONSIVE ===================== */
@media (max-width:768px) {
    .year-grid {
        grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
    }
}
</style>


<div class="calendar-container">

    <div class="calendar-header">
        <div>
            <div class="calendar-title">
                Kalender Tahun <?php echo (int)$year; ?>
            </div>
            <div class="calendar-sub">
                Tampilan 1 tahun penuh. Setiap tanggal bisa diklik untuk membuka halaman detail dan mengatur jadwal di hari tersebut.
            </div>
        </div>
        <div class="calendar-actions">
            <a href="<?php echo $baseUrl; ?>/dashboard/murid.php" class="btn btn-light">
                ← Kembali ke dashboard
            </a>
            <a href="<?php echo $baseUrl; ?>/study/calendar.php?year=<?php echo $year - 1; ?>" class="btn btn-secondary">
                ← <?php echo $year - 1; ?>
            </a>
            <a href="<?php echo $baseUrl; ?>/study/calendar.php?year=<?php echo $todayY; ?>" class="btn btn-secondary">
                Tahun ini
            </a>
            <a href="<?php echo $baseUrl; ?>/study/calendar.php?year=<?php echo $year + 1; ?>" class="btn btn-secondary">
                <?php echo $year + 1; ?> →
            </a>
        </div>
    </div>

    <div class="calendar-legend">
        <div>Penjelasan tanda:</div>
        <div class="legend-row">
            <div class="legend-item">
                <span class="legend-dot legend-holiday"></span>
                <span>Dot hijau &nbsp;= Ada jadwal tipe "Libur"</span>
            </div>
            <div class="legend-item">
                <span class="legend-dot legend-special"></span>
                <span>Dot kuning &nbsp;= Ada jadwal tipe "Acara khusus"</span>
            </div>
            <div class="legend-item">
                <span class="legend-dot legend-warning"></span>
                <span>Dot merah &nbsp;= Ada jadwal tipe "Peringatan"</span>
            </div>
            <div class="legend-item">
                <span class="legend-today"></span>
                <span>Lingkaran berbingkai ungu &nbsp;= Hari ini</span>
            </div>
        </div>
    </div>

    <div class="year-grid">
        <?php for ($m = 1; $m <= 12; $m++): ?>
            <?php
            $firstDayTs  = mktime(0, 0, 0, $m, 1, $year);
            $daysInMonth = (int)date('t', $firstDayTs);
            $startDow    = (int)date('N', $firstDayTs); // 1 = Senin ... 7 = Minggu
            ?>
            <div class="month-card">
                <div class="month-header">
                    <div class="month-name"><?php echo $bulanNames[$m]; ?></div>
                    <div class="month-year"><?php echo (int)$year; ?></div>
                </div>

                <table class="month-table">
                    <thead>
                    <tr>
                        <?php foreach ($dayHeaders as $idx => $dh): ?>
                            <?php
                            $isSunday = ($idx === 6); // index ke-6 = "Min"
                            $thClass  = $isSunday ? ' class="sunday-header"' : '';
                            ?>
                            <th<?php echo $thClass; ?>><?php echo $dh; ?></th>
                        <?php endforeach; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $currentDay = 1;
                    $cell = 1;
                    while ($currentDay <= $daysInMonth) {
                        echo '<tr>';
                        for ($col = 1; $col <= 7; $col++, $cell++) {

                            $isSundayCol = ($col === 7);

                            if ($cell < $startDow || $currentDay > $daysInMonth) {
                                $emptyClass = 'empty';
                                if ($isSundayCol) {
                                    $emptyClass .= ' sunday-cell';
                                }
                                echo '<td class="' . $emptyClass . '">&nbsp;</td>';
                                continue;
                            }

                            $dateStr      = sprintf('%04d-%02d-%02d', $year, $m, $currentDay);
                            $isToday      = ($dateStr === $todayYmd);
                            $typesForDate = $eventsByDate[$dateStr] ?? [
                                'holiday' => false,
                                'special' => false,
                                'warning' => false,
                            ];

                            $hasHoliday = !empty($typesForDate['holiday']);
                            $hasSpecial = !empty($typesForDate['special']);
                            $hasWarning = !empty($typesForDate['warning']);

                            // susun array dot aktif, urutan: holiday, special, warning
                            $activeDots = [];
                            if ($hasHoliday) $activeDots[] = 'day-dot-holiday';
                            if ($hasSpecial) $activeDots[] = 'day-dot-special';
                            if ($hasWarning) $activeDots[] = 'day-dot-warning';
                            $dotCount = count($activeDots);

                            $classes = 'day-link';
                            if ($isToday) {
                                $classes .= ' day-today';
                            }

                            $tdClasses = [];
                            if ($isSundayCol) {
                                $tdClasses[] = 'sunday-cell';
                            }
                            $tdClassAttr = $tdClasses ? ' class="' . implode(' ', $tdClasses) . '"' : '';

                            $dayTargetUrl = $baseUrl . '/study/day_detail.php?date=' . $dateStr;

                            echo '<td' . $tdClassAttr . '>';
                            echo '<div class="day-cell">';
                            echo '<a href="' . htmlspecialchars($dayTargetUrl, ENT_QUOTES, 'UTF-8') . '" class="' . $classes . '">';
                            echo (int)$currentDay;
                            echo '</a>';

                            echo '<div class="day-dots-wrapper">';
                            if ($dotCount > 0) {
                                echo '<div class="day-dots-card">';
                                foreach ($activeDots as $cls) {
                                    echo '<span class="day-dot ' . $cls . '"></span>';
                                }
                                echo '</div>';
                            }
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

</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
