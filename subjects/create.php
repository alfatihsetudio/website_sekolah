<?php
/**
 * Buat Mata Pelajaran Baru (Guru & Admin)
 * Versi baru: sudah multi-school, admin & guru dapat CRUD
 */

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/db.php';

requireLogin();

$db       = getDB();
$userId   = (int)($_SESSION['user_id'] ?? 0);
$role     = getUserRole() ?? 'murid';
$schoolId = (int) getCurrentSchoolId();

if (!in_array($role, ['guru', 'admin'])) {
    http_response_code(403);
    exit("Akses ditolak.");
}

$error   = '';
$success = '';

/* ==========================================
   Ambil kelas berdasarkan school_id
   ========================================== */
$stmt = $db->prepare("
    SELECT id, nama_kelas, level, jurusan
    FROM classes
    WHERE school_id = ?
    ORDER BY nama_kelas
");
if ($stmt) {
    $stmt->bind_param("i", $schoolId);
    $stmt->execute();
    $res = $stmt->get_result();
    $classes = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
} else {
    $classes = [];
}

/* ==========================================
   Jika admin → admin bisa pilih guru
   Jika guru → otomatis guru_id = login
   ========================================== */
$gurus = [];

if ($role === 'admin') {
    $qg = $db->prepare("
        SELECT id, nama 
        FROM users
        WHERE role = 'guru' AND school_id = ?
        ORDER BY nama
    ");
    if ($qg) {
        $qg->bind_param("i", $schoolId);
        $qg->execute();
        $r = $qg->get_result();
        $gurus = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
        $qg->close();
    }
}

/* ==========================================
   PROSES FORM
   ========================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '', 'create_subject')) {
        $error = 'Token keamanan tidak valid. Silakan refresh halaman.';
    } else {

        $classId    = (int)($_POST['class_id'] ?? 0);
        $namaMapel  = trim($_POST['nama_mapel'] ?? '');

        // Guru pengajar
        if ($role === 'admin') {
            $guruId = (int)($_POST['guru_id'] ?? 0);
        } else {
            $guruId = $userId;
        }

        if ($classId <= 0) {
            $error = 'Kelas wajib dipilih.';
        } elseif ($namaMapel === '') {
            $error = 'Nama mata pelajaran wajib diisi.';
        } elseif ($role === 'admin' && $guruId <= 0) {
            $error = 'Silakan pilih guru pengajar.';
        } else {

            /* ==========================================
               Ambil detail kelas
               ========================================== */
            $qc = $db->prepare("SELECT level, jurusan, nama_kelas FROM classes WHERE id = ? AND school_id = ? LIMIT 1");
            $qc->bind_param("ii", $classId, $schoolId);
            $qc->execute();
            $rc = $qc->get_result();

            if (!$rc || $rc->num_rows === 0) {
                $error = 'Kelas tidak ditemukan dalam sekolah ini.';
            } else {
                $kelas = $rc->fetch_assoc();
                $class_level = $kelas['level'];
                $jurusan     = $kelas['jurusan'];

                /* ==========================================
                   Cek duplikasi (per sekolah)
                   ========================================== */
                $qdup = $db->prepare("
                    SELECT id 
                    FROM subjects 
                    WHERE class_id = ? 
                      AND nama_mapel = ?
                      AND guru_id = ?
                      AND school_id = ?
                    LIMIT 1
                ");
                $qdup->bind_param("isii", $classId, $namaMapel, $guruId, $schoolId);
                $qdup->execute();
                $rdup = $qdup->get_result();

                if ($rdup && $rdup->num_rows > 0) {
                    $error = 'Mapel ini sudah dibuat untuk guru & kelas tersebut.';
                } else {

                    /* ==========================================
                       Insert mapel
                       ========================================== */
                    $ins = $db->prepare("
                        INSERT INTO subjects (class_id, nama_mapel, guru_id, class_level, jurusan, school_id, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");

                    if ($ins) {
                        $ins->bind_param("isisss",
                            $classId,
                            $namaMapel,
                            $guruId,
                            $class_level,
                            $jurusan,
                            $schoolId
                        );

                        if ($ins->execute()) {
                            header("Location: /web_MG/subjects/list.php");
                            exit;
                        } else {
                            $error = 'Gagal menyimpan data: ' . $ins->error;
                        }
                        $ins->close();
                    } else {
                        $error = 'Gagal menyiapkan query insert.';
                    }
                }
            }
        }
    }
}

/* ==========================================
   RENDER
   ========================================== */

$pageTitle = 'Tambah Mata Pelajaran';
include __DIR__ . '/../inc/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Tambah Mata Pelajaran</h1>
        <a href="/web_MG/subjects/list.php" class="btn btn-secondary">← Kembali</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST">
            <?php $csrf = generateCsrfToken('create_subject'); ?>
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf); ?>">

            <?php if (empty($classes)): ?>
                <div class="alert alert-info">Belum ada kelas dalam sekolah ini.</div>
            <?php else: ?>

                <!-- ADMIN → pilih guru -->
                <?php if ($role === 'admin'): ?>
                    <div class="form-group">
                        <label>Pilih Guru Pengajar</label>
                        <select name="guru_id" required>
                            <option value="">-- pilih guru --</option>
                            <?php foreach ($gurus as $g): ?>
                                <option value="<?php echo (int)$g['id']; ?>">
                                    <?php echo sanitize($g['nama']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Pilih Kelas</label>
                    <select name="class_id" required>
                        <option value="">-- Pilih Kelas --</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>">
                                <?php 
                                    $nm = $c['nama_kelas'] ?: trim(($c['level'] ?? '') . ' ' . ($c['jurusan'] ?? ''));
                                    echo sanitize($nm);
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Nama Mata Pelajaran</label>
                    <input type="text" name="nama_mapel" required placeholder="Contoh: Matematika">
                </div>

            <?php endif; ?>

            <button type="submit" class="btn btn-primary btn-block">Simpan</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
