<?php
require_once __DIR__ . '/../inc/auth.php';
requireRole(['admin']); // HANYA admin boleh buat/ubah data kelas

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

$db       = getDB();
$error    = '';
$success  = '';
$baseUrl  = rtrim(BASE_URL, '/\\');
$userId   = (int)($_SESSION['user_id'] ?? 0);
$schoolId = getCurrentSchoolId(); // semua kelas milik sekolah yang sedang login

$guruList = [];

if ($schoolId <= 0) {
    $error = 'Tidak dapat menentukan sekolah saat ini. Silakan logout lalu login kembali sebagai admin.';
} else {
    // ambil daftar guru di sekolah ini untuk dropdown
    $stmt = $db->prepare("
        SELECT id, nama, email
        FROM users
        WHERE role = 'guru' AND school_id = ?
        ORDER BY nama, email
    ");
    if ($stmt) {
        $stmt->bind_param("i", $schoolId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            $guruList = $res->fetch_all(MYSQLI_ASSOC);
        }
        $stmt->close();
    }
    if (empty($guruList) && $error === '') {
        $error = 'Belum ada guru yang terdaftar di sekolah ini. Tambah guru dulu sebelum membuat kelas.';
    }
}

// PROSES SUBMIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {

    // Ambil input
    $jenjang         = trim($_POST['jenjang'] ?? '');
    $jurusan         = trim($_POST['jurusan'] ?? '');
    $walimurid       = trim($_POST['walimurid'] ?? '');
    $no_telpon_wali  = trim($_POST['no_telpon_wali'] ?? '');
    $nama_km         = trim($_POST['nama_km'] ?? '');
    $no_telpon_km    = trim($_POST['no_telpon_km'] ?? '');
    $guruId          = (int)($_POST['guru_id'] ?? 0);

    // Validasi wajib
    if ($jenjang === '' || $jurusan === '') {
        $error = 'Jenjang dan Jurusan wajib diisi. Contoh: Jenjang = 10, Jurusan = RPL.';
    } elseif ($guruId <= 0) {
        $error = 'Guru pengampu wajib dipilih.';
    } else {
        // bentuk nama_kelas otomatis, misal: "10 RPL"
        $nama_kelas = trim($jenjang . ' ' . $jurusan);
        $level      = $jenjang; // level = jenjang

        // Insert ke tabel classes
        $sql = "
            INSERT INTO classes
                (nama_kelas, level, jurusan, walimurid, no_telpon_wali,
                 nama_km, no_telpon_km, guru_id, school_id, created_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            $error = 'Gagal menyiapkan query penyimpanan kelas.';
        } else {
            $stmt->bind_param(
                "sssssssii",
                $nama_kelas,
                $level,
                $jurusan,
                $walimurid,
                $no_telpon_wali,
                $nama_km,
                $no_telpon_km,
                $guruId,
                $schoolId
            );

            if ($stmt->execute()) {
                $stmt->close();
                header('Location: ' . $baseUrl . '/classes/list.php');
                exit;
            } else {
                $error = 'Gagal menyimpan kelas: ' . $stmt->error;
                $stmt->close();
            }
        }
    }
}

// Page render
$pageTitle = 'Buat Kelas';
include __DIR__ . '/../inc/header.php';
?>


<div class="container">
    <div class="page-header">
        <h1>Buat Kelas</h1>
        <a href="<?php echo $baseUrl; ?>/classes/list.php" class="btn btn-secondary">← Kembali</a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo sanitize($success); ?></div>
    <?php endif; ?>

    <div class="card">
        <?php if ($schoolId <= 0): ?>
            <p>School ID tidak ditemukan. Silakan login ulang.</p>
        <?php elseif (empty($guruList)): ?>
            <p>Belum ada guru di sekolah ini. Tambahkan guru terlebih dahulu sebelum membuat kelas.</p>
        <?php else: ?>
            <form method="POST" action="">
                <p class="small text-muted">
                    Isi Jenjang dan Jurusan untuk membentuk Nama Kelas secara otomatis.<br>
                    Contoh: Jenjang = <strong>10</strong>, Jurusan = <strong>RPL</strong> → Nama Kelas = <strong>10 RPL</strong>.
                </p>

                <div class="form-group">
                    <label for="jenjang">Jenjang (mis. 10, 11, 12)</label>
                    <input
                        type="text"
                        id="jenjang"
                        name="jenjang"
                        placeholder="Contoh: 10"
                        value="<?php echo isset($_POST['jenjang']) ? sanitize($_POST['jenjang']) : ''; ?>"
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
                        value="<?php echo isset($_POST['jurusan']) ? sanitize($_POST['jurusan']) : ''; ?>"
                        required
                    >
                </div>

                <hr>

                <div class="form-group">
                    <label for="guru_id">Guru Pengampu / Wali Kelas</label>
                    <select id="guru_id" name="guru_id" required>
                        <option value="">-- Pilih Guru --</option>
                        <?php
                        $selectedGuru = isset($_POST['guru_id']) ? (int)$_POST['guru_id'] : 0;

                        foreach ($guruList as $g):
                            $gid   = (int)$g['id'];
                            $nama  = trim($g['nama'] ?? '');
                            $email = trim($g['email'] ?? '');

                            // Buat label: jika nama ada → "Nama Guru — email"
                            // kalau nama kosong → tetap tampilkan email
                            if ($nama !== '') {
                                $label = $nama . ' — ' . $email;
                            } else {
                                $label = $email;
                            }
                        ?>
                            <option value="<?php echo $gid; ?>" 
                                <?php echo $gid === $selectedGuru ? 'selected' : ''; ?>>
                                <?php echo sanitize($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <small class="text-muted">Guru ini akan tercatat sebagai pengampu kelas.</small>
                </div>


                <hr>

                <div class="form-group">
                    <label for="no_telpon_wali">No. Telp wali kelas</label>
                    <input
                        type="text"
                        id="no_telpon_wali"
                        name="no_telpon_wali"
                        placeholder="Contoh: 0813xxxxxxx"
                        value="<?php echo isset($_POST['no_telpon_wali']) ? sanitize($_POST['no_telpon_wali']) : ''; ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="nama_km">Nama Ketua Murid / KM (opsional)</label>
                    <input
                        type="text"
                        id="nama_km"
                        name="nama_km"
                        placeholder="Contoh: Ahmad, Siti, dsb."
                        value="<?php echo isset($_POST['nama_km']) ? sanitize($_POST['nama_km']) : ''; ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="no_telpon_km">No. Telp KM (opsional)</label>
                    <input
                        type="text"
                        id="no_telpon_km"
                        name="no_telpon_km"
                        placeholder="Contoh: 0813xxxxxxx"
                        value="<?php echo isset($_POST['no_telpon_km']) ? sanitize($_POST['no_telpon_km']) : ''; ?>"
                    >
                </div>
                <div class="card" style="margin-bottom:16px;">
                    <h3 style="margin-top:0;">Import banyak kelas sekaligus</h3>
                    <p class="small text-muted">
                        Jika Anda ingin menambahkan banyak kelas sekaligus, gunakan fitur import dari Excel.
                    </p>
                    <a href="<?php echo $baseUrl; ?>/classes/import_excel.php" class="btn btn-secondary">
                        Import dari Excel
                    </a>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Buat Kelas</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
