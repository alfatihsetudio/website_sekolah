<?php 
// attendance/take.php
// Guru mengisi absensi + nilai harian satu kelas (berdasarkan mapel + tanggal)

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

// materi yang tersimpan (kalau sudah pernah diisi)
$existingMateri = '';

// --- PROSES POST (simpan absensi + nilai harian) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selectedSubjectId > 0 && $selectedClassId > 0) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token, 'attendance_take')) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        // data status & nilai: status[student_id], note[student_id], nilai[student_id]
        $statusArr = $_POST['status'] ?? [];
        $noteArr   = $_POST['note']   ?? [];
        $nilaiArr  = $_POST['nilai']  ?? [];
        $materi    = trim($_POST['materi'] ?? '');

        if (empty($statusArr)) {
            $error = 'Tidak ada data siswa untuk disimpan.';
        } else {
            try {
                $db->begin_transaction();

                $sql = "
                    INSERT INTO attendance
                        (student_id, class_id, subject_id, date, materi, status, note, daily_score, created_by, created_at, updated_at)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        materi      = VALUES(materi),
                        status      = VALUES(status),
                        note        = VALUES(note),
                        daily_score = VALUES(daily_score),
                        updated_at  = NOW()
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
                    $note  = trim($noteArr[$sid] ?? '');
                    $nilai = null;
                    if (isset($nilaiArr[$sid]) && $nilaiArr[$sid] !== '') {
                        $nilai = (float)$nilaiArr[$sid];
                    }

                    $ins->bind_param(
                        "iiissssdi",
                        $sid,
                        $selectedClassId,
                        $selectedSubjectId,
                        $selectedDate,
                        $materi,
                        $st,
                        $note,
                        $nilai,
                        $guruId
                    );
                    if (!$ins->execute()) {
                        throw new Exception('Gagal menyimpan baris absensi: ' . $ins->error);
                    }
                }

                $ins->close();
                $db->commit();
                $success = 'Absensi & nilai harian berhasil disimpan untuk tanggal ' . sanitize($selectedDate);
                $existingMateri = $materi;
            } catch (Exception $e) {
                $db->rollback();
                $error = 'Terjadi kesalahan saat menyimpan data: ' . $e->getMessage();
            }
        }
    }
}

// --- Jika subject & kelas sudah dipilih: ambil daftar siswa + absensi sebelumnya ---
$students  = [];
$attMap    = [];   // [student_id] => ['status'=>..,'note'=>..,'daily_score'=>..,'materi'=>..]

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
        SELECT student_id, status, note, materi, daily_score
        FROM attendance
        WHERE class_id = ? AND subject_id = ? AND date = ?
    ");
    if ($at) {
        $at->bind_param("iis", $selectedClassId, $selectedSubjectId, $selectedDate);
        $at->execute();
        $ra = $at->get_result();
        while ($row = $ra->fetch_assoc()) {
            $sid = (int)$row['student_id'];
            $attMap[$sid] = $row;
            if ($existingMateri === '' && !empty($row['materi'])) {
                $existingMateri = $row['materi'];
            }
        }
        $at->close();
    }
}

$pageTitle = 'Absensi & Nilai Harian';
include __DIR__ . '/../inc/header.php';

$baseUrl = rtrim(BASE_URL, '/\\');
?>

<div class="container">
    <div class="page-header">
        <h1>Absensi & Nilai Harian</h1>
        <a href="<?php echo $baseUrl; ?>/dashboard/guru.php" class="btn btn-secondary">← Kembali</a>
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
                        <?php echo sanitize($selectedSubject['nama_kelas']); ?>
                        — <?php echo sanitize($selectedSubject['nama_mapel']); ?>
                        (<?php echo sanitize($selectedDate); ?>)
                    </h3>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf); ?>">
                        <input type="hidden" name="subject_id" value="<?php echo (int)$selectedSubjectId; ?>">
                        <input type="hidden" name="date" value="<?php echo sanitize($selectedDate); ?>">

                        <div class="form-group" style="margin-top:10px;">
                            <label for="materi">Materi / Topik Hari Ini</label>
                            <input type="text"
                                   id="materi"
                                   name="materi"
                                   placeholder="Contoh: Persamaan Linear, Bab 3, dst."
                                   value="<?php
                                       echo sanitize(
                                           $_POST['materi'] ?? $existingMateri
                                       );
                                   ?>">
                        </div>

                        <table class="table" style="width:100%; margin-top:12px;">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nama Siswa</th>
                                    <th>Status</th>
                                    <th>Keterangan</th>
                                    <th>Nilai harian<br><span style="font-size:0.78rem;color:#6b7280;">(opsional, 0–100)</span></th>
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
                                    $curStatus = $att['status']      ?? 'H';
                                    $curNote   = $att['note']        ?? '';
                                    $curScore  = $att['daily_score'] ?? '';
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
                                        <td>
                                            <input type="number"
                                                   name="nilai[<?php echo $sid; ?>]"
                                                   value="<?php echo sanitize($curScore); ?>"
                                                   min="0" max="100" step="0.01"
                                                   style="width:90px;">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <button type="submit" class="btn btn-primary">Simpan Absensi & Nilai</button>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
