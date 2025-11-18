<?php
/**
 * Buat Mata Pelajaran Baru (Guru)
 * Versi: hanya mengambil kelas yang sudah diisi oleh admin (tidak ada input jenjang/jurusan)
 */
require_once __DIR__ . '/../inc/auth.php';
requireRole(['guru']);

require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/db.php';

$db = getDB();
$guruId = (int)($_SESSION['user_id'] ?? 0);

$error = '';
$success = '';

// Ambil semua kelas (diisi oleh admin). Tampilkan untuk semua guru.
$stmt = $db->prepare("SELECT id, nama_kelas, level, jurusan FROM classes ORDER BY nama_kelas");
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    $classes = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
} else {
    $classes = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token, 'create_subject')) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman dan coba lagi.';
    } else {
        // Ambil nilai dari form
        $selectedClassId = (int)($_POST['class_id'] ?? 0);
        $namaMapel = trim($_POST['nama_mapel'] ?? '');

        if ($selectedClassId <= 0) {
            $error = 'Silakan pilih kelas yang tersedia (diisi oleh admin).';
        } elseif ($namaMapel === '') {
            $error = 'Nama mata pelajaran wajib diisi.';
        } else {
            // Verifikasi kelas ada dan ambil level & jurusan dari tabel classes
            $stmt = $db->prepare("SELECT level, jurusan, nama_kelas FROM classes WHERE id = ? LIMIT 1");
            if (!$stmt) {
                $error = 'Gagal menyiapkan query verifikasi kelas.';
            } else {
                $stmt->bind_param("i", $selectedClassId);
                $stmt->execute();
                $r = $stmt->get_result();
                if (!$r || $r->num_rows === 0) {
                    $stmt->close();
                    $error = 'Kelas yang dipilih tidak ditemukan. Pastikan admin sudah menambahkannya.';
                } else {
                    $row = $r->fetch_assoc();
                    $class_level = $row['level'] ?? '';
                    $jurusan = $row['jurusan'] ?? '';
                    $nama_kelas_display = $row['nama_kelas'] ?? '';
                    $stmt->close();

                    // Cek duplikasi: mapel sama di kelas yang sama oleh guru yang sama
                    $stmtDup = $db->prepare("SELECT id FROM subjects WHERE class_id = ? AND nama_mapel = ? AND guru_id = ? LIMIT 1");
                    if (!$stmtDup) {
                        $error = 'Gagal menyiapkan pengecekan duplikasi.';
                    } else {
                        $stmtDup->bind_param("isi", $selectedClassId, $namaMapel, $guruId);
                        $stmtDup->execute();
                        $dupRes = $stmtDup->get_result();
                        if ($dupRes && $dupRes->num_rows > 0) {
                            $stmtDup->close();
                            $error = 'Mata pelajaran dengan nama yang sama sudah ada di kelas ini.';
                        } else {
                            $stmtDup->close();

                            // Insert ke subjects (gunakan class_id terpilih dan isi class_level & jurusan dari table classes)
                            $stmtIns = $db->prepare("
                                INSERT INTO subjects (class_id, nama_mapel, guru_id, class_level, jurusan, created_at)
                                VALUES (?, ?, ?, ?, ?, NOW())
                            ");
                            if (!$stmtIns) {
                                $error = 'Gagal menyiapkan query penyimpanan mata pelajaran.';
                            } else {
                                // bind types: i (class_id), s (nama_mapel), i (guru_id), s (class_level), s (jurusan)
                                $stmtIns->bind_param("isiss", $selectedClassId, $namaMapel, $guruId, $class_level, $jurusan);
                                if ($stmtIns->execute()) {
                                    $stmtIns->close();
                                    // sukses, redirect untuk menghindari double submit
                                    header('Location: /web_MG/subjects/list.php');
                                    exit;
                                } else {
                                    $error = 'Gagal menyimpan mata pelajaran: ' . sanitize($stmtIns->error);
                                    $stmtIns->close();
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

// Page render
$pageTitle = 'Tambah Mata Pelajaran';
include __DIR__ . '/../inc/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Tambah Mata Pelajaran</h1>
        <a href="/web_MG/subjects/list.php" class="btn btn-secondary">← Kembali</a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="" class="form">
            <?php $csrf = generateCsrfToken('create_subject'); ?>
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf); ?>">

            <?php if (empty($classes)): ?>
                <div class="alert alert-info">
                    Belum ada kelas yang tersedia. Silakan hubungi administrator untuk menambah daftar kelas (jenjang & jurusan).
                </div>
            <?php else: ?>
                <div class="form-group">
                    <label for="class_id">Pilih Kelas</label>
                    <select name="class_id" id="class_id" required>
                        <option value="">-- Pilih Kelas --</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>"
                                <?php echo (isset($_POST['class_id']) && $_POST['class_id'] == $c['id']) ? 'selected' : ''; ?>>
                                <?php
                                    // Tampilkan nama_kelas jika tersedia, fallback ke kombinasi level+jurusan
                                    $display = $c['nama_kelas'] ?? trim(($c['level'] ?? '') . ' ' . ($c['jurusan'] ?? ''));
                                    echo sanitize($display);
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="nama_mapel">Nama Mata Pelajaran</label>
                    <input
                        type="text"
                        id="nama_mapel"
                        name="nama_mapel"
                        required
                        placeholder="Contoh: Matematika, Bahasa Indonesia"
                        value="<?php echo isset($_POST['nama_mapel']) ? sanitize($_POST['nama_mapel']) : ''; ?>"
                    >
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary btn-block" <?php echo empty($classes) ? 'disabled' : ''; ?>>
                Simpan Mata Pelajaran
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
