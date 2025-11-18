<?php
// attendance/report_class.php
// Rekap absensi per kelas & per siswa (guru)

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireRole(['guru']); // hanya guru

$db     = getDB();
$guruId = (int)($_SESSION['user_id'] ?? 0);

$error = '';
$summaryRows = [];

// --- Ambil daftar mapel+kelas yang diajar guru ---
$subjects = [];
$stmt = $db->prepare("
    SELECT s.id AS subject_id,
           s.nama_mapel,
           c.id AS class_id,
           c.nama_kelas,
           c.level,
           c.jurusan
    FROM subjects s
    JOIN classes c ON s.class_id = c.id
    WHERE s.guru_id = ?
    ORDER BY c.nama_kelas, s.nama_mapel
");
if ($stmt) {
    $stmt->bind_param("i", $guruId);
    $stmt->execute();
    $res = $stmt->get_result();
    $subjects = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}

// input filter
$selectedSubjectId = (int)($_GET['subject_id'] ?? 0);
$dateFrom = trim($_GET['from'] ?? '');
$dateTo   = trim($_GET['to'] ?? '');

if ($dateFrom === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    // default: 30 hari terakhir
    $dateFrom = date('Y-m-d', strtotime('-30 days'));
}
if ($dateTo === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = date('Y-m-d');
}

// cari subject terpilih
$selectedSubject = null;
$selectedClassId = 0;
foreach ($subjects as $s) {
    if ((int)$s['subject_id'] === $selectedSubjectId) {
        $selectedSubject = $s;
        $selectedClassId = (int)$s['class_id'];
        break;
    }
}

// jika sudah pilih mapel -> ambil rekap
if ($selectedSubject && $selectedClassId > 0) {
    $sql = "
        SELECT 
            u.id AS student_id,
            u.nama,
            u.email,
            SUM(CASE WHEN a.status = 'H' THEN 1 ELSE 0 END) AS hadir,
            SUM(CASE WHEN a.status = 'S' THEN 1 ELSE 0 END) AS sakit,
            SUM(CASE WHEN a.status = 'I' THEN 1 ELSE 0 END) AS izin,
            SUM(CASE WHEN a.status = 'A' THEN 1 ELSE 0 END) AS alpa,
            COUNT(a.id) AS total_pertemuan
        FROM class_user cu
        JOIN users u ON cu.user_id = u.id
        LEFT JOIN attendance a
            ON a.student_id = u.id
           AND a.class_id   = ?
           AND a.subject_id = ?
           AND a.date BETWEEN ? AND ?
        WHERE cu.class_id = ?
        GROUP BY u.id, u.nama, u.email
        ORDER BY u.nama
    ";
    $st = $db->prepare($sql);
    if ($st) {
        $st->bind_param(
            "iissi",
            $selectedClassId,
            $selectedSubjectId,
            $dateFrom,
            $dateTo,
            $selectedClassId
        );
        $st->execute();
        $rs = $st->get_result();
        $summaryRows = $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];
        $st->close();
    } else {
        $error = 'Gagal menyiapkan query rekap: ' . $db->error;
    }
}

$pageTitle = 'Rekap Absensi Kelas';
$baseUrl   = rtrim(BASE_URL, '/\\');
include __DIR__ . '/../inc/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Rekap Absensi Kelas</h1>
        <a href="<?php echo $baseUrl; ?>/attendance/take.php" class="btn btn-secondary">← Kembali ke Absensi</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <?php if (empty($subjects)): ?>
        <div class="card"><p>Anda belum memiliki mata pelajaran.</p></div>
    <?php else: ?>
        <div class="card" style="margin-bottom:16px;">
            <form method="GET" class="form-inline">
                <div class="form-group">
                    <label for="subject_id">Mata Pelajaran / Kelas</label>
                    <select name="subject_id" id="subject_id" required>
                        <option value="">Pilih Mapel...</option>
                        <?php foreach ($subjects as $s): ?>
                            <?php
                                $kls = trim(($s['level'] ?? '') . ' ' . ($s['jurusan'] ?? ''));
                                $label = ($kls ? $kls . ' - ' : '') . $s['nama_kelas'] . ' — ' . $s['nama_mapel'];
                            ?>
                            <option value="<?php echo (int)$s['subject_id']; ?>"
                                <?php echo $selectedSubjectId === (int)$s['subject_id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Dari</label>
                    <input type="date" name="from" value="<?php echo sanitize($dateFrom); ?>">
                </div>
                <div class="form-group">
                    <label>Sampai</label>
                    <input type="date" name="to" value="<?php echo sanitize($dateTo); ?>">
                </div>

                <button class="btn btn-primary" type="submit">Tampilkan Rekap</button>
            </form>
        </div>

        <?php if ($selectedSubject && !empty($summaryRows)): ?>
            <div class="card">
                <h3>
                    Kelas: <?php echo sanitize($selectedSubject['nama_kelas']); ?> —
                    <?php echo sanitize($selectedSubject['nama_mapel']); ?>
                    <br>
                    <small>Periode: <?php echo sanitize($dateFrom); ?> s/d <?php echo sanitize($dateTo); ?></small>
                </h3>

                <table class="table" style="width:100%; margin-top:10px;">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nama Siswa</th>
                            <th>Hadir</th>
                            <th>Sakit</th>
                            <th>Izin</th>
                            <th>Alpa</th>
                            <th>% Hadir</th>
                            <th>Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($summaryRows as $i => $row): ?>
                            <?php
                                $total = (int)$row['total_pertemuan'];
                                $hadir = (int)$row['hadir'];
                                $percent = $total > 0 ? round($hadir / $total * 100, 1) : 0;
                            ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td><?php echo sanitize($row['nama']); ?></td>
                                <td><?php echo (int)$row['hadir']; ?></td>
                                <td><?php echo (int)$row['sakit']; ?></td>
                                <td><?php echo (int)$row['izin']; ?></td>
                                <td><?php echo (int)$row['alpa']; ?></td>
                                <td><?php echo $percent; ?>%</td>
                                <td>
                                    <a href="<?php echo $baseUrl; ?>/attendance/student_detail.php?student_id=<?php echo (int)$row['student_id']; ?>&subject_id=<?php echo (int)$selectedSubjectId; ?>&from=<?php echo urlencode($dateFrom); ?>&to=<?php echo urlencode($dateTo); ?>">
                                        Lihat riwayat
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($selectedSubject): ?>
            <div class="card">
                <p>Belum ada data absensi pada rentang tanggal ini.</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
