<?php
/**
 * Buat Mata Pelajaran Baru (Guru) - Ditingkatkan
 */
require_once __DIR__ . '/../inc/auth.php';
requireRole(['guru']);

require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/db.php';

$db = getDB();
$guruId = (int)($_SESSION['user_id'] ?? 0);

$error = '';
$success = '';

// Ambil semua kelas milik guru (atau semua kelas jika ingin)
$stmt = $db->prepare("SELECT c.id, c.nama_kelas FROM classes c WHERE c.guru_id = ? ORDER BY c.nama_kelas");
$stmt->bind_param("i", $guruId);
$stmt->execute();
$res = $stmt->get_result();
$classes = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

// Process form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token, 'create_subject')) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman dan coba lagi.';
    } else {
        $class_choice = $_POST['class_choice'] ?? ''; // 'existing' or 'new'
        $classId = 0;

        if ($class_choice === 'new') {
            $nama_kelas = trim($_POST['nama_kelas'] ?? '');
            $deskripsi_kelas = trim($_POST['deskripsi_kelas'] ?? '');

            if (empty($nama_kelas)) {
                $error = 'Nama kelas harus diisi ketika membuat kelas baru.';
            } else {
                // Insert kelas baru (guru sebagai wali)
                $stmt = $db->prepare("INSERT INTO classes (nama_kelas, deskripsi, guru_id, created_at) VALUES (?, ?, ?, NOW())");
                if ($stmt) {
                    $stmt->bind_param("ssi", $nama_kelas, $deskripsi_kelas, $guruId);
                    if ($stmt->execute()) {
                        $classId = $db->insert_id;
                    } else {
                        $error = 'Gagal membuat kelas baru.';
                    }
                    $stmt->close();
                } else {
                    $error = 'Gagal menyiapkan query pembuatan kelas.';
                }
            }
        } else {
            // existing class
            $classId = (int)($_POST['class_id'] ?? 0);
            if ($classId <= 0) {
                $error = 'Pilih kelas terlebih dahulu atau buat kelas baru.';
            } else {
                // Optional: verify that selected class belongs to this guru
                $stmt = $db->prepare("SELECT id FROM classes WHERE id = ? AND guru_id = ?");
                if ($stmt) {
                    $stmt->bind_param("ii", $classId, $guruId);
                    $stmt->execute();
                    $r = $stmt->get_result();
                    if (!$r || $r->num_rows === 0) {
                        // Jika guru bukan pemilik, boleh tetap izinkan jika kebijakan mengizinkan
                        // Untuk sekarang kita hanya izinkan jika guru adalah wali kelas
                        $stmt->close();
                        $error = 'Anda tidak memiliki hak untuk menambahkan mapel pada kelas yang dipilih.';
                    } else {
                        $stmt->close();
                    }
                }
            }
        }

        // Jika belum error, proses pembuatan subject
        if (empty($error)) {
            $namaMapel = trim($_POST['nama_mapel'] ?? '');
            $deskripsi = trim($_POST['deskripsi'] ?? '');

            if (empty($namaMapel)) {
                $error = 'Nama mata pelajaran wajib diisi.';
            } else {
                // cek duplicate untuk kelas ini dan guru ini
                $stmt = $db->prepare("SELECT id FROM subjects WHERE class_id = ? AND nama_mapel = ? AND guru_id = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("isi", $classId, $namaMapel, $guruId);
                    $stmt->execute();
                    $r = $stmt->get_result();
                    if ($r && $r->num_rows > 0) {
                        $stmt->close();
                        $error = 'Mata pelajaran dengan nama yang sama sudah ada di kelas ini.';
                    } else {
                        $stmt->close();

                        // Insert subject
                        $stmt2 = $db->prepare("INSERT INTO subjects (class_id, nama_mapel, guru_id, deskripsi, created_at) VALUES (?, ?, ?, ?, NOW())");
                        if ($stmt2) {
                            // types: class_id (i), nama_mapel (s), guru_id (i), deskripsi (s)
                            $stmt2->bind_param("isis", $classId, $namaMapel, $guruId, $deskripsi);
                            if ($stmt2->execute()) {
                                $success = 'Mata pelajaran berhasil dibuat.';
                                // Redirect ke list agar double submit tidak terjadi
                                header('Location: /web_MG/subjects/list.php');
                                exit;
                            } else {
                                $error = 'Gagal menyimpan mata pelajaran.';
                            }
                            $stmt2->close();
                        } else {
                            $error = 'Gagal menyiapkan query pembuatan mata pelajaran.';
                        }
                    }
                } else {
                    $error = 'Gagal menyiapkan pengecekan duplikasi.';
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

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo sanitize($success); ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="" class="form">
            <?php $csrf = generateCsrfToken('create_subject'); ?>
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf); ?>">

            <?php if (empty($classes)): ?>
                <!-- Jika tidak ada kelas, langsung tampilkan form buat kelas baru -->
                <div class="form-group">
                    <label for="nama_kelas">Nama Kelas (Buat baru)</label>
                    <input type="text" id="nama_kelas" name="nama_kelas" required placeholder="Contoh: Kelas 10 IPA 1" value="<?php echo isset($_POST['nama_kelas']) ? sanitize($_POST['nama_kelas']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="deskripsi_kelas">Deskripsi Kelas (Opsional)</label>
                    <textarea id="deskripsi_kelas" name="deskripsi_kelas" rows="3"><?php echo isset($_POST['deskripsi_kelas']) ? sanitize($_POST['deskripsi_kelas']) : ''; ?></textarea>
                </div>

                <input type="hidden" name="class_choice" value="new">
            <?php else: ?>
                <div class="form-group">
                    <label for="class_choice">Kelas</label>
                    <select id="class_choice" name="class_choice" required>
                        <option value="existing" <?php echo (isset($_POST['class_choice']) && $_POST['class_choice'] === 'existing') ? 'selected' : ''; ?>>Pilih Kelas yang sudah ada</option>
                        <option value="new" <?php echo (isset($_POST['class_choice']) && $_POST['class_choice'] === 'new') ? 'selected' : ''; ?>>Buat kelas baru</option>
                    </select>
                </div>

                <div id="existing-class-block" style="<?php echo (isset($_POST['class_choice']) && $_POST['class_choice'] === 'new') ? 'display:none;' : ''; ?>">
                    <div class="form-group">
                        <label for="class_id">Pilih Kelas</label>
                        <select id="class_id" name="class_id">
                            <option value="">Pilih Kelas</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo (int)$class['id']; ?>" <?php echo (isset($_POST['class_id']) && $_POST['class_id'] == $class['id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitize($class['nama_kelas']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div id="new-class-block" style="<?php echo (isset($_POST['class_choice']) && $_POST['class_choice'] === 'new') ? '' : 'display:none;'; ?>">
                    <div class="form-group">
                        <label for="nama_kelas">Nama Kelas Baru</label>
                        <input type="text" id="nama_kelas" name="nama_kelas" placeholder="Contoh: Kelas 10 IPA 1" value="<?php echo isset($_POST['nama_kelas']) ? sanitize($_POST['nama_kelas']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="deskripsi_kelas">Deskripsi Kelas (Opsional)</label>
                        <textarea id="deskripsi_kelas" name="deskripsi_kelas" rows="3"><?php echo isset($_POST['deskripsi_kelas']) ? sanitize($_POST['deskripsi_kelas']) : ''; ?></textarea>
                    </div>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="nama_mapel">Nama Mata Pelajaran</label>
                <input 
                    type="text" 
                    id="nama_mapel" 
                    name="nama_mapel" 
                    required 
                    placeholder="Contoh: Matematika, Bahasa Indonesia, dll"
                    value="<?php echo isset($_POST['nama_mapel']) ? sanitize($_POST['nama_mapel']) : ''; ?>"
                >
            </div>

            <div class="form-group">
                <label for="deskripsi">Deskripsi (Opsional)</label>
                <textarea 
                    id="deskripsi" 
                    name="deskripsi" 
                    rows="4"
                    placeholder="Deskripsi mata pelajaran..."><?php echo isset($_POST['deskripsi']) ? sanitize($_POST['deskripsi']) : ''; ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Simpan Mata Pelajaran</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const choice = document.getElementById('class_choice');
    if (!choice) return;

    const existingBlock = document.getElementById('existing-class-block');
    const newBlock = document.getElementById('new-class-block');

    function toggleBlocks() {
        if (choice.value === 'new') {
            existingBlock.style.display = 'none';
            newBlock.style.display = '';
            // set hidden input for POST
            let hidden = document.querySelector('input[name="class_choice"][type="hidden"]');
            if (!hidden) {
                hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'class_choice';
                document.querySelector('form').appendChild(hidden);
            }
            hidden.value = 'new';
        } else {
            existingBlock.style.display = '';
            newBlock.style.display = 'none';
            let hidden = document.querySelector('input[name="class_choice"][type="hidden"]');
            if (hidden) hidden.value = 'existing';
        }
    }

    choice.addEventListener('change', toggleBlocks);
    toggleBlocks();
});
</script>

<?php include __DIR__ . '/../inc/footer.php'; ?>
