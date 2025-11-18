<?php
// classes/edit.php
require_once __DIR__ . '/../inc/auth.php';
requireRole(['admin']); // HANYA admin boleh mengubah data kelas

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

$db       = getDB();
$baseUrl  = rtrim(BASE_URL, '/\\');
$schoolId = getCurrentSchoolId();
$userId   = (int)($_SESSION['user_id'] ?? 0);

if ($schoolId <= 0) {
    http_response_code(400);
    echo "School ID tidak ditemukan. Silakan logout lalu login kembali sebagai admin.";
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . $baseUrl . '/classes/list.php');
    exit;
}

// --- Ambil data kelas yang mau diedit (harus milik sekolah ini) ---
$stmt = $db->prepare("
    SELECT c.id,
           c.nama_kelas,
           c.level,
           c.jurusan,
           c.walimurid,
           c.no_telpon_wali,
           c.nama_km,
           c.no_telpon_km,
           c.guru_id
    FROM classes c
    WHERE c.id = ? AND c.school_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $id, $schoolId);
$stmt->execute();
$res   = $stmt->get_result();
$class = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$class) {
    http_response_code(404);
    echo "Kelas tidak ditemukan atau bukan milik sekolah ini.";
    exit;
}

// --- Ambil daftar guru di sekolah ini untuk dropdown ---
$guruList = [];
$stmtG = $db->prepare("
    SELECT id, nama, email
    FROM users
    WHERE role = 'guru' AND school_id = ?
    ORDER BY nama, email
");
if ($stmtG) {
    $stmtG->bind_param("i", $schoolId);
    $stmtG->execute();
    $rG = $stmtG->get_result();
    if ($rG) {
        $guruList = $rG->fetch_all(MYSQLI_ASSOC);
    }
    $stmtG->close();
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Ambil input form
    $jenjang        = trim($_POST['jenjang'] ?? '');
    $jurusan        = trim($_POST['jurusan'] ?? '');
    $walimurid      = trim($_POST['walimurid'] ?? '');
    $no_telpon_wali = trim($_POST['no_telpon_wali'] ?? '');
    $nama_km        = trim($_POST['nama_km'] ?? '');
    $no_telpon_km   = trim($_POST['no_telpon_km'] ?? '');
    $guruId         = (int)($_POST['guru_id'] ?? 0);

    // Validasi
    if ($jenjang === '' || $jurusan === '') {
        $error = 'Jenjang dan Jurusan wajib diisi. Contoh: Jenjang = 10, Jurusan = RPL.';
    } elseif ($guruId <= 0) {
        $error = 'Guru pengampu wajib dipilih.';
    } else {
        // Pastikan guru-nya memang guru di sekolah ini
        $stmtCheck = $db->prepare("
            SELECT id FROM users 
            WHERE id = ? AND role = 'guru' AND school_id = ?
            LIMIT 1
        ");
        if ($stmtCheck) {
            $stmtCheck->bind_param("ii", $guruId, $schoolId);
            $stmtCheck->execute();
            $rCheck = $stmtCheck->get_result();
            if (!$rCheck || $rCheck->num_rows === 0) {
                $error = 'Guru pengampu tidak valid untuk sekolah ini.';
            }
            $stmtCheck->close();
        } else {
            $error = 'Gagal memeriksa guru pengampu.';
        }
    }

    if ($error === '') {
        // Bentuk nama_kelas & level
        $nama_kelas = trim($jenjang . ' ' . $jurusan);
        $level      = $jenjang;

        // (Opsional) bisa cek duplikat nama_kelas lain di sekolah yang sama
        $stmtDup = $db->prepare("
            SELECT id 
            FROM classes
            WHERE nama_kelas = ? AND school_id = ? AND id <> ?
            LIMIT 1
        ");
        if ($stmtDup) {
            $stmtDup->bind_param("sii", $nama_kelas, $schoolId, $id);
            $stmtDup->execute();
            $rDup = $stmtDup->get_result();
            if ($rDup && $rDup->num_rows > 0) {
                $error = 'Sudah ada kelas lain dengan nama tersebut di sekolah ini.';
            }
            $stmtDup->close();
        }

        if ($error === '') {
            // Update data kelas
            $sqlUp = "
                UPDATE classes
                SET nama_kelas   = ?,
                    level        = ?,
                    jurusan      = ?,
                    walimurid    = ?,
                    no_telpon_wali = ?,
                    nama_km      = ?,
                    no_telpon_km = ?,
                    guru_id      = ?
                WHERE id = ? AND school_id = ?
                LIMIT 1
            ";
            $stmtUp = $db->prepare($sqlUp);
            if (!$stmtUp) {
                $error = 'Gagal menyiapkan query update: ' . $db->error;
            } else {
                $stmtUp->bind_param(
                    "sssssssiii",
                    $nama_kelas,
                    $level,
                    $jurusan,
                    $walimurid,
                    $no_telpon_wali,
                    $nama_km,
                    $no_telpon_km,
                    $guruId,
                    $id,
                    $schoolId
                );

                if ($stmtUp->execute()) {
                    $stmtUp->close();
                    header('Location: ' . $baseUrl . '/classes/list.php');
                    exit;
                } else {
                    $error = 'Gagal menyimpan perubahan: ' . $stmtUp->error;
                    $stmtUp->close();
                }
            }
        }
    }

    // kalau error, isi $class dengan nilai POST supaya form tetap terisi
    $class['level']          = $jenjang;
    $class['jurusan']        = $jurusan;
    $class['walimurid']      = $walimurid;
    $class['no_telpon_wali'] = $no_telpon_wali;
    $class['nama_km']        = $nama_km;
    $class['no_telpon_km']   = $no_telpon_km;
    $class['guru_id']        = $guruId;
}

// nilai default untuk form
$jenjangVal  = $class['level']   ?? '';
$jurusanVal  = $class['jurusan'] ?? '';
$waliVal     = $class['walimurid'] ?? '';
$noWaliVal   = $class['no_telpon_wali'] ?? '';
$namaKmVal   = $class['nama_km'] ?? '';
$noKmVal     = $class['no_telpon_km'] ?? '';
$guruIdVal   = (int)($class['guru_id'] ?? 0);

$pageTitle = 'Edit Kelas';
include __DIR__ . '/../inc/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Edit Kelas</h1>
        <a href="<?php echo $baseUrl; ?>/classes/list.php" class="btn btn-secondary">← Kembali</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <div class="card">
        <?php if (empty($guruList)): ?>
            <p>Belum ada guru yang terdaftar di sekolah ini. Tambahkan guru terlebih dahulu sebelum mengedit kelas.</p>
        <?php else: ?>
            <form method="POST" action="">
                <p class="small text-muted">
                    Ubah Jenjang dan Jurusan untuk memperbarui nama kelas secara otomatis
                    (mis. Jenjang = <strong>12</strong>, Jurusan = <strong>RPL</strong> → Nama Kelas = <strong>12 RPL</strong>).
                </p>

                <div class="form-group">
                    <label for="jenjang">Jenjang (mis. 10, 11, 12)</label>
                    <input
                        type="text"
                        id="jenjang"
                        name="jenjang"
                        placeholder="Contoh: 12"
                        value="<?php echo sanitize($jenjangVal); ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="jurusan">Jurusan (mis. RPL, IPA, IPS)</label>
                    <input
                        type="text"
                        id="jurusan"
                        name="jurusan"
                        placeholder="Contoh: RPL"
                        value="<?php echo sanitize($jurusanVal); ?>"
                        required
                    >
                </div>

                <hr>

                <div class="form-group">
                    <label for="guru_id">Guru Pengampu / Wali Kelas</label>
                    <select id="guru_id" name="guru_id" required>
                        <option value="">-- Pilih Guru --</option>
                        <?php foreach ($guruList as $g): ?>
                            <?php
                                $gid   = (int)$g['id'];
                                $nama  = $g['nama']  ?? '';
                                $email = $g['email'] ?? '';
                                $labelNama = $nama !== '' ? $nama : $email;
                            ?>
                            <option value="<?php echo $gid; ?>" <?php echo $gid === $guruIdVal ? 'selected' : ''; ?>>
                                <?php echo sanitize($labelNama . ' — ' . $email); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">
                        Guru ini akan tercatat sebagai pengampu / wali kelas.
                    </small>
                </div>

                <hr>

                <div class="form-group">
                    <label for="walimurid">Nama Wali Murid (opsional)</label>
                    <input
                        type="text"
                        id="walimurid"
                        name="walimurid"
                        placeholder="Nama wali kelas / kontak utama"
                        value="<?php echo sanitize($waliVal); ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="no_telpon_wali">No. Telp Wali Murid / kontak utama</label>
                    <input
                        type="text"
                        id="no_telpon_wali"
                        name="no_telpon_wali"
                        placeholder="Contoh: 0813xxxxxxx"
                        value="<?php echo sanitize($noWaliVal); ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="nama_km">Nama Ketua Murid / KM (opsional)</label>
                    <input
                        type="text"
                        id="nama_km"
                        name="nama_km"
                        placeholder="Contoh: Ahmad"
                        value="<?php echo sanitize($namaKmVal); ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="no_telpon_km">No. Telp KM (opsional)</label>
                    <input
                        type="text"
                        id="no_telpon_km"
                        name="no_telpon_km"
                        placeholder="Contoh: 0812xxxxxxx"
                        value="<?php echo sanitize($noKmVal); ?>"
                    >
                </div>

                <button class="btn btn-primary btn-block" type="submit">
                    Simpan Perubahan
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
