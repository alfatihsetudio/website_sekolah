<?php
// attendance/take.php
// Guru mengisi absensi satu kelas (berdasarkan mapel + tanggal)

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireRole(['guru']);

$db     = getDB();
$guruId = (int)($_SESSION['user_id'] ?? 0);

$error   = '';
$success = '';

// --- Ambil daftar mapel yang diajar guru (subjek + kelas) ---
$subjects = [];
$stmt = $db->prepare("
    SELECT s.id AS subject_id,
           s.nama_mapel,
           c.id AS class_id,
           c.nama_kelas
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

// ambil subject & tanggal yang dipilih
$selectedSubjectId = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : (int)($_POST['subject_id'] ?? 0);
$selectedDate      = isset($_GET['date']) ? trim($_GET['date']) : trim($_POST['date'] ?? date('Y-m-d'));

// validasi format tanggal sederhana
if ($selectedDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}

// cari data subject terpilih (untuk ambil class_id)
$selectedSubject = null;
$selectedClassId = 0;
foreach ($subjects as $s) {
    if ((int)$s['subject_id'] === $selectedSubjectId) {
        $selectedSubject = $s;
        $selectedClassId = (int)$s['class_id'];
        break;
    }
}

// --- PROSES POST (simpan absensi) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selectedSubjectId > 0 && $selectedClassId > 0) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token, 'attendance_take')) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        // data status: status[student_id] = H/S/I/A
        $statusArr = $_POST['status'] ?? [];
        $noteArr   = $_POST['note'] ?? [];

        if (empty($statusArr)) {
            $error = 'Tidak ada data siswa untuk disimpan.';
        } else {
            try {
                $db->begin_transaction();

                $sql = "
                    INSERT INTO attendance
                        (student_id, class_id, subject_id, date, status, note, created_by, created_at, updated_at)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        status    = VALUES(status),
                        note      = VALUES(note),
                        updated_at = NOW()
                ";
                $ins = $db->prepare($sql);
                if (!$ins) {
                    throw new Exception('Gagal menyiapkan query absensi: ' . $db->error);
                }

                foreach ($statusArr as $sid => $st) {
                    $sid = (int)$sid;
                    $st  = strtoupper(trim($st));
                    if (!in_array($st, ['H','S','I','A'], true)) {
                        $st = 'H';
                    }
                    $note = trim($noteArr[$sid] ?? '');

                    $ins->bind_param(
                        "iiisssi",
                        $sid,
                        $selectedClassId,
                        $selectedSubjectId,
                        $selectedDate,
                        $st,
                        $note,
                        $guruId
                    );
                    if (!$ins->execute()) {
                        throw new Exception('Gagal menyimpan baris absensi: ' . $ins->error);
                    }
                }

                $ins->close();
                $db->commit();
                $success = 'Absensi berhasil disimpan untuk tanggal ' . sanitize($selectedDate);
            } catch (Exception $e) {
                $db->rollback();
                $error = 'Terjadi kesalahan saat menyimpan absensi: ' . $e->getMessage();
            }
        }
    }
}

// --- Jika subject & kelas sudah dipilih: ambil daftar siswa + absensi sebelumnya ---
$students  = [];
$attMap    = [];   // [student_id] => ['status'=>..,'note'=>..]

if ($selectedSubjectId > 0 && $selectedClassId > 0) {
    // daftar siswa di kelas
    $st = $db->prepare("
        SELECT u.id, u.nama, u.email
        FROM class_user cu
        JOIN users u ON cu.user_id = u.id
        WHERE cu.class_id = ?
        ORDER BY u.nama
    ");
    if ($st) {
        $st->bind_param("i", $selectedClassId);
        $st->execute();
        $rs = $st->get_result();
        $students = $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];
        $st->close();
    }

    // ambil absensi existing utk tanggal ini
    $at = $db->prepare("
        SELECT student_id, status, note
        FROM attendance
        WHERE class_id = ? AND subject_id = ? AND date = ?
    ");
    if ($at) {
        $at->bind_param("iis", $selectedClassId, $selectedSubjectId, $selectedDate);
        $at->execute();
        $ra = $at->get_result();
        while ($row = $ra->fetch_assoc()) {
            $attMap[(int)$row['student_id']] = $row;
        }
        $at->close();
    }
}

$pageTitle = 'Absensi Kelas';
include __DIR__ . '/../inc/header.php';

$baseUrl = rtrim(BASE_URL, '/\\');
?>

<div class="container">
    <div class="page-header">
        <h1>Absensi Kelas</h1>
        <a href="<?php echo $baseUrl; ?>/dashboard.php" class="btn btn-secondary">← Kembali</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo sanitize($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo sanitize($success); ?></div>
    <?php endif; ?>

    <?php if (empty($subjects)): ?>
        <div class="card">
            <p>Anda belum memiliki mata pelajaran / kelas yang terdaftar.</p>
        </div>
    <?php else: ?>

        <!-- Form pilih mapel & tanggal -->
        <div class="card" style="margin-bottom:16px;">
            <form method="GET">
                <div class="form-group">
                    <label for="subject_id">Mata Pelajaran / Kelas</label>
                    <select name="subject_id" id="subject_id" required>
                        <option value="">Pilih Mata Pelajaran</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?php echo (int)$s['subject_id']; ?>"
                                <?php echo $selectedSubjectId === (int)$s['subject_id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($s['nama_kelas'] . ' — ' . $s['nama_mapel']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="date">Tanggal</label>
                    <input type="date" id="date" name="date"
                           value="<?php echo sanitize($selectedDate); ?>" required>
                </div>

                <button class="btn btn-primary" type="submit">Tampilkan Siswa</button>
            </form>
        </div>

        <?php if ($selectedSubjectId > 0 && $selectedClassId > 0): ?>
            <?php if (empty($students)): ?>
                <div class="card">
                    <p>Belum ada siswa di kelas ini.</p>
                </div>
            <?php else: ?>
                <?php $csrf = generateCsrfToken('attendance_take'); ?>
                <div class="card">
                    <h3>
                        Absensi <?php echo sanitize($selectedSubject['nama_kelas']); ?>
                        — <?php echo sanitize($selectedSubject['nama_mapel']); ?>
                        (<?php echo sanitize($selectedDate); ?>)
                    </h3>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf); ?>">
                        <input type="hidden" name="subject_id" value="<?php echo (int)$selectedSubjectId; ?>">
                        <input type="hidden" name="date" value="<?php echo sanitize($selectedDate); ?>">

                        <table class="table" style="width:100%; margin-top:12px;">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nama Siswa</th>
                                    <th>Status</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $statusLabels = [
                                    'H' => 'Hadir',
                                    'S' => 'Sakit',
                                    'I' => 'Izin',
                                    'A' => 'Alpa'
                                ];
                                foreach ($students as $i => $st):
                                    $sid = (int)$st['id'];
                                    $att = $attMap[$sid] ?? null;
                                    $curStatus = $att['status'] ?? 'H';
                                    $curNote   = $att['note'] ?? '';
                                ?>
                                    <tr>
                                        <td><?php echo $i + 1; ?></td>
                                        <td><?php echo sanitize($st['nama']); ?></td>
                                        <td>
                                            <select name="status[<?php echo $sid; ?>]">
                                                <?php foreach ($statusLabels as $code => $label): ?>
                                                    <option value="<?php echo $code; ?>"
                                                        <?php echo ($curStatus === $code) ? 'selected' : ''; ?>>
                                                        <?php echo $label; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text"
                                                   name="note[<?php echo $sid; ?>]"
                                                   value="<?php echo sanitize($curNote); ?>"
                                                   placeholder="Opsional (misal: datang terlambat)">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <button type="submit" class="btn btn-primary">Simpan Absensi</button>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
