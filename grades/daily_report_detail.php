<?php
// grades/daily_report_detail.php
// Detail nilai harian untuk satu siswa pada rentang tanggal

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireRole(['guru']);

$db       = getDB();
$baseUrl  = rtrim(BASE_URL, '/\\');
$userId   = (int)($_SESSION['user_id'] ?? 0);
$schoolId = (int)getCurrentSchoolId();

$studentId = (int)($_GET['student_id'] ?? 0);
$from      = trim($_GET['from'] ?? date('Y-m-01'));
$to        = trim($_GET['to']   ?? date('Y-m-d'));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');
if (strtotime($from) > strtotime($to)) { $t=$from; $from=$to; $to=$t; }

$fromDt = $from . ' 00:00:00';
$toDt   = $to   . ' 23:59:59';

$student   = null;
$rows      = [];
$errorText = '';

if ($studentId <= 0 || $schoolId <= 0) {
    $errorText = 'Data siswa atau sekolah tidak valid.';
} else {
    // cek siswa dan pastikan dia ada di sekolah ini
    $stmtS = $db->prepare("SELECT id, nama, email FROM users WHERE id = ? AND (role='murid' OR role='siswa') LIMIT 1");
    if ($stmtS) {
        $stmtS->bind_param("i", $studentId);
        $stmtS->execute();
        $resS = $stmtS->get_result();
        if ($resS && $resS->num_rows) {
            $student = $resS->fetch_assoc();
        } else {
            $errorText = 'Siswa tidak ditemukan.';
        }
        $stmtS->close();
    }

    if (!$errorText) {
        $sql = "
            SELECT
                s.id,
                s.nilai,
                s.submitted_at,
                a.judul         AS assignment_title,
                sub.nama_mapel  AS subject_name,
                c.nama_kelas
            FROM submissions s
            JOIN assignments a ON s.assignment_id = a.id
            JOIN subjects   sub ON a.subject_id    = sub.id
            JOIN classes    c   ON a.target_class_id = c.id
            WHERE s.student_id = ?
              AND sub.guru_id  = ?
              AND c.school_id  = ?
              AND s.submitted_at BETWEEN ? AND ?
            ORDER BY s.submitted_at DESC
        ";

        $stmt = $db->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("iiiss", $studentId, $userId, $schoolId, $fromDt, $toDt);
            if ($stmt->execute()) {
                $res  = $stmt->get_result();
                $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            } else {
                $errorText = 'Gagal mengambil detail nilai.';
            }
            $stmt->close();
        } else {
            $errorText = 'Gagal menyiapkan query detail nilai.';
        }
    }
}

$pageTitle = 'Detail Nilai Siswa';
include __DIR__ . '/../inc/header.php';
?>

<div class="container">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
        <div>
            <h1 style="margin:0 0 4px 0;">Detail Nilai Siswa</h1>
            <?php if ($student): ?>
                <div style="font-size:0.9rem;color:#4b5563;">
                    Siswa: <strong><?php echo sanitize($student['nama']); ?></strong><br>
                    Periode: <?php echo sanitize($from); ?> s/d <?php echo sanitize($to); ?>
                </div>
            <?php endif; ?>
        </div>
        <a href="<?php echo $baseUrl; ?>/grades/daily_recap.php?from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>"
           class="btn btn-secondary">
            ← Kembali ke Rekap
        </a>
    </div>

    <?php if ($errorText): ?>
        <div class="card"><p><?php echo sanitize($errorText); ?></p></div>
    <?php else: ?>
        <div class="card">
            <?php if (empty($rows)): ?>
                <p>Belum ada nilai untuk siswa ini pada periode tersebut.</p>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="table" style="width:100%;">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Tanggal Submit</th>
                                <th>Mata Pelajaran</th>
                                <th>Kelas</th>
                                <th>Judul Tugas</th>
                                <th>Nilai</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $i => $r): ?>
                                <tr>
                                    <td><?php echo (int)($i + 1); ?></td>
                                    <td><?php echo sanitize($r['submitted_at']); ?></td>
                                    <td><?php echo sanitize($r['subject_name']); ?></td>
                                    <td><?php echo sanitize($r['nama_kelas'] ?? '-'); ?></td>
                                    <td><?php echo sanitize($r['assignment_title']); ?></td>
                                    <td><strong><?php echo number_format((float)$r['nilai'], 2); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
