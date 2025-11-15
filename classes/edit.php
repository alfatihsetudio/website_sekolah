<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireRole(['guru','admin']);

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . rtrim(BASE_URL, '/\\') . '/classes/list.php');
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? 'siswa';

// Cek apakah kolom tambahan ada
$hasJenjang = false;
$hasJurusan = false;
$hasWali = false;
$hasNoTelpWali = false;
$hasNamaKm = false;
$hasNoTelpKm = false;
$hasDeskripsi = false;

$res = $db->query("SHOW COLUMNS FROM classes");
$cols = [];
if ($res) {
    while ($r = $res->fetch_assoc()) $cols[] = $r['Field'];
    $res->close();
}
$hasJenjang = in_array('jenjang', $cols, true);
$hasJurusan = in_array('jurusan', $cols, true);
$hasWali = in_array('walimurid', $cols, true);
$hasNoTelpWali = in_array('no_telpon_wali', $cols, true);
$hasNamaKm = in_array('nama_km', $cols, true);
$hasNoTelpKm = in_array('no_telpon_km', $cols, true);
$hasDeskripsi = in_array('deskripsi', $cols, true);

// Ambil data kelas (ambil hanya kolom yang ada)
$selectCols = "c.id, c.nama_kelas, c.guru_id";
if ($hasDeskripsi) $selectCols .= ", c.deskripsi";
if ($hasJenjang) $selectCols .= ", c.jenjang";
if ($hasJurusan) $selectCols .= ", c.jurusan";
if ($hasWali) $selectCols .= ", c.walimurid";
if ($hasNoTelpWali) $selectCols .= ", c.no_telpon_wali";
if ($hasNamaKm) $selectCols .= ", c.nama_km";
if ($hasNoTelpKm) $selectCols .= ", c.no_telpon_km";

$stmt = $db->prepare("SELECT {$selectCols} FROM classes c WHERE c.id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$class = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$class) {
    http_response_code(404);
    echo "Kelas tidak ditemukan.";
    exit;
}

// Pastikan guru hanya boleh edit kelas miliknya (kecuali admin)
if ($role === 'guru' && (int)$class['guru_id'] !== $userId) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jenjang = trim($_POST['jenjang'] ?? '');
    $jurusan = trim($_POST['jurusan'] ?? '');
    $walimurid = trim($_POST['walimurid'] ?? '');
    $no_telpon_wali = trim($_POST['no_telpon_wali'] ?? '');
    $nama_km = trim($_POST['nama_km'] ?? '');
    $no_telpon_km = trim($_POST['no_telpon_km'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');

    // Validasi minimal: jenjang & jurusan wajib
    if ($jenjang === '' || $jurusan === '') {
        $error = 'Jenjang dan Jurusan wajib diisi.';
    } else {
        $nama_kelas = trim($jenjang . ' ' . $jurusan);

        // Susun query update dinamis sesuai kolom yang ada
        $fields = [];
        $types = '';
        $values = [];

        // selalu set nama_kelas (untuk kompatibilitas)
        $fields[] = 'nama_kelas = ?';
        $types .= 's';
        $values[] = $nama_kelas;

        if ($hasDeskripsi) {
            $fields[] = 'deskripsi = ?';
            $types .= 's';
            $values[] = $deskripsi;
        }

        if ($hasJenjang) {
            $fields[] = 'jenjang = ?';
            $types .= 's';
            $values[] = $jenjang;
        }
        if ($hasJurusan) {
            $fields[] = 'jurusan = ?';
            $types .= 's';
            $values[] = $jurusan;
        }
        if ($hasWali) {
            $fields[] = 'walimurid = ?';
            $types .= 's';
            $values[] = $walimurid;
        }
        if ($hasNoTelpWali) {
            $fields[] = 'no_telpon_wali = ?';
            $types .= 's';
            $values[] = $no_telpon_wali;
        }
        if ($hasNamaKm) {
            $fields[] = 'nama_km = ?';
            $types .= 's';
            $values[] = $nama_km;
        }
        if ($hasNoTelpKm) {
            $fields[] = 'no_telpon_km = ?';
            $types .= 's';
            $values[] = $no_telpon_km;
        }

        // tambahkan updated_at jika ada kolom updated_at
        if (in_array('updated_at', $cols, true)) {
            $fields[] = 'updated_at = NOW()';
        }

        $sql = "UPDATE classes SET " . implode(', ', $fields) . " WHERE id = ?";

        $stmtUp = $db->prepare($sql);
        if (!$stmtUp) {
            $error = 'Gagal menyiapkan statement: ' . $db->error;
        } else {
            // tambahkan id ke values dan 'i' ke types
            $typesWithId = $types . 'i';
            $valuesWithId = $values;
            $valuesWithId[] = $id;

            // bind params by reference
            $bindParams = [];
            $bindParams[] = & $typesWithId;
            for ($i = 0; $i < count($valuesWithId); $i++) {
                $bindParams[] = & $valuesWithId[$i];
            }

            call_user_func_array([$stmtUp, 'bind_param'], $bindParams);

            if ($stmtUp->execute()) {
                $stmtUp->close();
                header('Location: ' . rtrim(BASE_URL, '/\\') . '/classes/list.php');
                exit;
            } else {
                $error = 'Gagal menyimpan perubahan: ' . $stmtUp->error;
                $stmtUp->close();
            }
        }
    }
}

// Page render
$pageTitle = 'Edit Kelas';
include __DIR__ . '/../inc/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Edit Kelas</h1>
        <a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/classes/list.php" class="btn btn-secondary">← Kembali</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST">
            <p class="small text-muted">Ubah Jenjang dan Jurusan — Nama kelas akan terbentuk otomatis.</p>

            <div class="form-group">
                <label for="jenjang">Jenjang</label>
                <input type="text" id="jenjang" name="jenjang" required value="<?php echo isset($_POST['jenjang']) ? sanitize($_POST['jenjang']) : (isset($class['jenjang']) ? sanitize($class['jenjang']) : (strpos($class['nama_kelas'] ?? '', ' ') !== false ? explode(' ', $class['nama_kelas'])[0] : '')); ?>">
            </div>

            <div class="form-group">
                <label for="jurusan">Jurusan</label>
                <input type="text" id="jurusan" name="jurusan" required value="<?php echo isset($_POST['jurusan']) ? sanitize($_POST['jurusan']) : (isset($class['jurusan']) ? sanitize($class['jurusan']) : (strpos($class['nama_kelas'] ?? '', ' ') !== false ? trim(substr($class['nama_kelas'], strpos($class['nama_kelas'], ' ') + 1)) : '')); ?>">
            </div>

            <?php if ($hasWali || $hasNoTelpWali): ?>
                <hr>
                <h3>Data Wali Murid</h3>
                <div class="form-group">
                    <label for="walimurid">Nama Wali Murid</label>
                    <input type="text" id="walimurid" name="walimurid" value="<?php echo isset($_POST['walimurid']) ? sanitize($_POST['walimurid']) : (isset($class['walimurid']) ? sanitize($class['walimurid']) : ''); ?>">
                </div>
                <div class="form-group">
                    <label for="no_telpon_wali">No. Telp Wali Murid</label>
                    <input type="text" id="no_telpon_wali" name="no_telpon_wali" value="<?php echo isset($_POST['no_telpon_wali']) ? sanitize($_POST['no_telpon_wali']) : (isset($class['no_telpon_wali']) ? sanitize($class['no_telpon_wali']) : ''); ?>">
                </div>
            <?php endif; ?>

            <?php if ($hasNamaKm || $hasNoTelpKm): ?>
                <hr>
                <h3>Data KM</h3>
                <div class="form-group">
                    <label for="nama_km">Nama KM</label>
                    <input type="text" id="nama_km" name="nama_km" value="<?php echo isset($_POST['nama_km']) ? sanitize($_POST['nama_km']) : (isset($class['nama_km']) ? sanitize($class['nama_km']) : ''); ?>">
                </div>
                <div class="form-group">
                    <label for="no_telpon_km">No. Telp KM</label>
                    <input type="text" id="no_telpon_km" name="no_telpon_km" value="<?php echo isset($_POST['no_telpon_km']) ? sanitize($_POST['no_telpon_km']) : (isset($class['no_telpon_km']) ? sanitize($class['no_telpon_km']) : ''); ?>">
                </div>
            <?php endif; ?>

            <?php if ($hasDeskripsi): ?>
                <div class="form-group">
                    <label for="deskripsi">Deskripsi (opsional)</label>
                    <textarea id="deskripsi" name="deskripsi" rows="3"><?php echo isset($_POST['deskripsi']) ? sanitize($_POST['deskripsi']) : (isset($class['deskripsi']) ? sanitize($class['deskripsi']) : ''); ?></textarea>
                </div>
            <?php endif; ?>

            <button class="btn btn-primary btn-block" type="submit">Simpan Perubahan</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
