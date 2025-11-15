<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireRole(['admin']);

$db = getDB();
$error = '';
$report = [
    'created' => [],
    'skipped' => [],
    'errors' => []
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Jika submit manual (tambah 1 siswa)
    if (isset($_POST['action']) && $_POST['action'] === 'manual') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $nama_kelas = trim($_POST['nama_kelas'] ?? '');
        $jenjang = trim($_POST['jenjang'] ?? '');
        $jurusan = trim($_POST['jurusan'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');

        if ($name === '') {
            $error = 'Nama siswa wajib diisi.';
        } else {
            // prepare email jika kosong
            if ($email === '') {
                $base = slugify($name);
                $candidate = $base . '@school.local';
                $attempt = 1;
                while (true) {
                    $stmtChk = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                    $stmtChk->bind_param("s", $candidate);
                    $stmtChk->execute();
                    $rChk = $stmtChk->get_result();
                    if ($rChk && $rChk->num_rows === 0) { $stmtChk->close(); break; }
                    $stmtChk->close();
                    $attempt++;
                    $candidate = $base . $attempt . '@school.local';
                }
                $email = $candidate;
            } else {
                // cek email unik
                $stmtEx = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $stmtEx->bind_param("s", $email);
                $stmtEx->execute();
                $rEx = $stmtEx->get_result();
                if ($rEx && $rEx->num_rows > 0) {
                    $report['skipped'][] = "Email {$email} sudah terdaftar.";
                    $stmtEx->close();
                    // tampilkan halaman dengan report
                    goto RENDER;
                }
                $stmtEx->close();
            }

            // cari atau buat kelas
            $classId = null;
            if ($nama_kelas !== '') {
                $stmtC = $db->prepare("SELECT id FROM classes WHERE nama_kelas = ? LIMIT 1");
                $stmtC->bind_param("s", $nama_kelas);
                $stmtC->execute();
                $rC = $stmtC->get_result();
                if ($rC && $rC->num_rows > 0) $classId = (int)$rC->fetch_assoc()['id'];
                $stmtC->close();
            } elseif ($jenjang !== '' && $jurusan !== '') {
                $stmtC = $db->prepare("SELECT id FROM classes WHERE jenjang = ? AND jurusan = ? LIMIT 1");
                if ($stmtC) {
                    $stmtC->bind_param("ss", $jenjang, $jurusan);
                    $stmtC->execute();
                    $rC = $stmtC->get_result();
                    if ($rC && $rC->num_rows > 0) $classId = (int)$rC->fetch_assoc()['id'];
                    $stmtC->close();
                }
            }

            if ($classId === null) {
                $computedNama = $nama_kelas !== '' ? $nama_kelas : trim($jenjang . ' ' . $jurusan);
                if ($computedNama === '') $computedNama = 'Kelas Baru';
                $stmtInsC = $db->prepare("INSERT INTO classes (nama_kelas, jenjang, jurusan, deskripsi, created_at) VALUES (?, ?, ?, ?, NOW())");
                if ($stmtInsC) {
                    $stmtInsC->bind_param("ssss", $computedNama, $jenjang, $jurusan, $deskripsi);
                    if ($stmtInsC->execute()) $classId = $db->insert_id;
                    $stmtInsC->close();
                }
            }

            // buat akun siswa
            $plainPass = generateReadablePassword();
            $hash = password_hash($plainPass, PASSWORD_DEFAULT);

            $stmtU = $db->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, 'siswa', NOW())");
            if ($stmtU) {
                $stmtU->bind_param("sss", $name, $email, $hash);
                if ($stmtU->execute()) {
                    $newUserId = $db->insert_id;
                    $stmtU->close();
                    if ($classId) {
                        $stmtLink = $db->prepare("INSERT INTO class_user (class_id, user_id, created_at) VALUES (?, ?, NOW())");
                        if ($stmtLink) {
                            $stmtLink->bind_param("ii", $classId, $newUserId);
                            $stmtLink->execute();
                            $stmtLink->close();
                        }
                    }
                    $report['created'][] = ['id' => $newUserId, 'name' => $name, 'email' => $email, 'password' => $plainPass, 'class_id' => $classId];
                } else {
                    $report['errors'][] = 'Gagal menyimpan user: ' . $stmtU->error;
                    $stmtU->close();
                }
            } else {
                $report['errors'][] = 'Gagal menyiapkan query user: ' . $db->error;
            }
        }

    } elseif (isset($_FILES['csv']) && $_FILES['csv']['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES['csv']['tmp_name'];
        if (($handle = fopen($tmp, 'r')) === false) {
            $error = 'Gagal membuka file.';
        } else {
            // Baca header
            $header = fgetcsv($handle);
            if ($header === false) {
                $error = 'File kosong atau format CSV tidak valid.';
                fclose($handle);
            } else {
                // Normalisasi header: lowercase and trim
                $cols = array_map(function($c){ return strtolower(trim($c)); }, $header);
                $rowNo = 1;
                $db->begin_transaction();
                try {
                    while (($row = fgetcsv($handle)) !== false) {
                        $rowNo++;
                        $data = array_combine($cols, $row);
                        // Ambil fields
                        $name = trim($data['name'] ?? '');
                        $email = trim($data['email'] ?? '');
                        $nama_kelas = trim($data['nama_kelas'] ?? '');
                        $jenjang = trim($data['jenjang'] ?? '');
                        $jurusan = trim($data['jurusan'] ?? '');

                        if ($name === '') {
                            $report['errors'][] = "Baris {$rowNo}: Nama kosong, dilewati.";
                            continue;
                        }

                        // Pastikan email, jika kosong buat dari nama
                        if ($email === '') {
                            $base = slugify($name);
                            // Pastikan unik: append counter if needed
                            $attempt = 1;
                            $candidate = $base . '@school.local';
                            while (true) {
                                $stmtChk = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                                $stmtChk->bind_param("s", $candidate);
                                $stmtChk->execute();
                                $rChk = $stmtChk->get_result();
                                if ($rChk && $rChk->num_rows === 0) {
                                    $stmtChk->close();
                                    break;
                                }
                                $stmtChk->close();
                                $attempt++;
                                $candidate = $base . $attempt . '@school.local';
                            }
                            $email = $candidate;
                        } else {
                            // jika email sudah dipakai, skip
                            $stmtEx = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                            $stmtEx->bind_param("s", $email);
                            $stmtEx->execute();
                            $rEx = $stmtEx->get_result();
                            if ($rEx && $rEx->num_rows > 0) {
                                $report['skipped'][] = "Baris {$rowNo}: email {$email} sudah terdaftar, dilewati.";
                                $stmtEx->close();
                                continue;
                            }
                            $stmtEx->close();
                        }

                        // Cari/buat class
                        $classId = null;
                        if ($nama_kelas !== '') {
                            $stmtC = $db->prepare("SELECT id FROM classes WHERE nama_kelas = ? LIMIT 1");
                            $stmtC->bind_param("s", $nama_kelas);
                            $stmtC->execute();
                            $rC = $stmtC->get_result();
                            if ($rC && $rC->num_rows > 0) {
                                $classId = (int)$rC->fetch_assoc()['id'];
                            }
                            $stmtC->close();
                        } elseif ($jenjang !== '' && $jurusan !== '') {
                            // coba berdasarkan kolom jenjang & jurusan jika tersedia
                            $stmtC = $db->prepare("SELECT id FROM classes WHERE jenjang = ? AND jurusan = ? LIMIT 1");
                            if ($stmtC) {
                                $stmtC->bind_param("ss", $jenjang, $jurusan);
                                $stmtC->execute();
                                $rC = $stmtC->get_result();
                                if ($rC && $rC->num_rows > 0) {
                                    $classId = (int)$rC->fetch_assoc()['id'];
                                }
                                $stmtC->close();
                            }
                        }

                        // jika class tidak ditemukan, buat baru (guru_id NULL)
                        if ($classId === null) {
                            $computedNama = $nama_kelas !== '' ? $nama_kelas : trim($jenjang . ' ' . $jurusan);
                            if ($computedNama === '') $computedNama = 'Kelas Baru';
                            $stmtInsC = $db->prepare("INSERT INTO classes (nama_kelas, jenjang, jurusan, created_at) VALUES (?, ?, ?, NOW())");
                            if ($stmtInsC) {
                                $stmtInsC->bind_param("sss", $computedNama, $jenjang, $jurusan);
                                if ($stmtInsC->execute()) {
                                    $classId = $db->insert_id;
                                }
                                $stmtInsC->close();
                            }
                        }

                        // Generate password readable dan hash
                        $plainPass = generateReadablePassword();
                        $hash = password_hash($plainPass, PASSWORD_DEFAULT);

                        // Insert user
                        $stmtU = $db->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, 'siswa', NOW())");
                        if (!$stmtU) {
                            $report['errors'][] = "Baris {$rowNo}: Gagal menyiapkan insert user ({$db->error})";
                            continue;
                        }
                        $stmtU->bind_param("sss", $name, $email, $hash);
                        if ($stmtU->execute()) {
                            $newUserId = $db->insert_id;
                            $stmtU->close();
                            // link to class if available
                            if ($classId) {
                                $stmtLink = $db->prepare("INSERT INTO class_user (class_id, user_id, created_at) VALUES (?, ?, NOW())");
                                if ($stmtLink) {
                                    $stmtLink->bind_param("ii", $classId, $newUserId);
                                    $stmtLink->execute();
                                    $stmtLink->close();
                                }
                            }
                            $report['created'][] = ['id' => $newUserId, 'name' => $name, 'email' => $email, 'password' => $plainPass, 'class_id' => $classId];
                        } else {
                            $report['errors'][] = "Baris {$rowNo}: Gagal menyimpan user ({$stmtU->error})";
                            $stmtU->close();
                        }
                    } // end while
                    $db->commit();
                } catch (Exception $ex) {
                    $db->rollback();
                    $report['errors'][] = 'Exception: ' . $ex->getMessage();
                }
                fclose($handle);
            }
        }
    }
}

RENDER:
$pageTitle = 'Impor / Tambah Siswa';
include __DIR__ . '/../inc/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Impor / Tambah Siswa</h1>
        <a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/users/list.php" class="btn btn-secondary">← Kembali</a>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?php echo sanitize($error); ?></div><?php endif; ?>

    <div class="card">
        <h3>Unggah CSV (bulk)</h3>
        <p class="small text-muted">CSV minimal: <strong>name</strong>, <strong>jenjang</strong>, dan <strong>jurusan</strong>. Kolom <strong>email</strong> bersifat opsional jika tersedia.</p>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Pilih file CSV</label>
                <input type="file" name="csv" accept=".csv">
            </div>
            <button class="btn btn-primary">Unggah dan Proses CSV</button>
        </form>
    </div>

    <div class="card" style="margin-top:18px;">
        <h3>Tambah Siswa Manual</h3>
        <p class="small text-muted">Isi form untuk membuat satu akun siswa.</p>
        <form method="POST" action="">
            <input type="hidden" name="action" value="manual">
            <div class="form-group">
                <label>Nama</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>Jenjang</label>
                <input type="text" name="jenjang" placeholder="Contoh: 10" required>
            </div>
            <div class="form-group">
                <label>Jurusan</label>
                <input type="text" name="jurusan" placeholder="Contoh: RPL" required>
            </div>
            <button class="btn btn-primary btn-block">Buat Akun Siswa</button>
        </form>
    </div>

    <?php if (!empty($report['created'])): ?>
        <div class="card" style="margin-top:18px;">
            <h3>Akun Baru Dibuat (<?php echo count($report['created']); ?>)</h3>
            <table class="table">
                <thead><tr><th>#</th><th>Nama</th><th>Email</th><th>Password</th><th>Class ID</th></tr></thead>
                <tbody>
                    <?php foreach ($report['created'] as $i => $r): ?>
                        <tr>
                            <td><?php echo $i+1; ?></td>
                            <td><?php echo sanitize($r['name']); ?></td>
                            <td><?php echo sanitize($r['email']); ?></td>
                            <td><code><?php echo sanitize($r['password']); ?></code></td>
                            <td><?php echo sanitize($r['class_id'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="small text-muted">Simpan password ini dan berikan ke siswa secara aman.</p>
        </div>
    <?php endif; ?>

    <?php if (!empty($report['skipped'])): ?>
        <div class="card" style="margin-top:18px;">
            <h3>Dilewati</h3>
            <ul>
                <?php foreach ($report['skipped'] as $s): ?><li><?php echo sanitize($s); ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($report['errors'])): ?>
        <div class="card" style="margin-top:18px;">
            <h3>Error</h3>
            <ul>
                <?php foreach ($report['errors'] as $e): ?><li><?php echo sanitize($e); ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
