<?php
// web_MG/users/import_students.php
// Import siswa dari CSV (kolom: nama, level/jenjang, jurusan, email)
// Hanya admin boleh akses

require_once __DIR__ . '/../inc/auth.php';
requireRole(['admin']);
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

$pageTitle = 'Import Siswa (CSV)';
include __DIR__ . '/../inc/header.php';

$db = getDB();

$errors = [];
$warnings = [];
$successCount = 0;
$skipCount = 0;
$total = 0;

function resolveOrCreateClass($db, $level, $jurusan, $nama_kelas = null) {
    // Trim & normalisasi
    $level = trim((string)$level);
    $jurusan = trim((string)$jurusan);
    $nama_kelas = trim((string)$nama_kelas);

    // Cek existing by level + jurusan (case-insensitive)
    $q = $db->prepare("SELECT id FROM classes WHERE (level = ? OR nama_kelas = ?) AND (jurusan = ? OR jurusan = '') LIMIT 1");
    if (!$q) return null;
    $q->bind_param("sss", $level, $nama_kelas, $jurusan);
    $q->execute();
    $r = $q->get_result();
    if ($r && $r->num_rows > 0) {
        $row = $r->fetch_assoc();
        $q->close();
        return (int)$row['id'];
    }
    $q->close();

    // Tidak ada, buat baru; isi nama_kelas jika kosong sebagai "LEVEL JURUSAN" atau nama_kelas param
    $nama = $nama_kelas ?: trim(($level ? $level : '') . ' ' . ($jurusan ? $jurusan : ''));
    if ($nama === '') $nama = 'Kelas';

    $ins = $db->prepare("INSERT INTO classes (nama_kelas, level, jurusan, guru_id, created_at) VALUES (?, ?, ?, NULL, NOW())");
    if (!$ins) return null;
    $ins->bind_param("sss", $nama, $level, $jurusan);
    if ($ins->execute()) {
        $newId = $db->insert_id;
        $ins->close();
        return (int)$newId;
    } else {
        $ins->close();
        return null;
    }
}

function createUserIfNotExists($db, $nama, $email, $role = 'murid') {
    // Return array: ['status'=>'ok'|'exists'|'error', 'id'=>int|null, 'msg'=>string, 'password'=>plaintext|null]
    $out = ['status' => 'error', 'id' => null, 'msg' => '', 'password' => null];

    // cek duplicate
    $chk = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    if (!$chk) { $out['msg'] = $db->error; return $out; }
    $chk->bind_param("s", $email);
    $chk->execute();
    $r = $chk->get_result();
    if ($r && $r->num_rows > 0) {
        $row = $r->fetch_assoc();
        $id = (int)$row['id'];
        $chk->close();
        $out['status'] = 'exists';
        $out['id'] = $id;
        return $out;
    }
    $chk->close();

    // generate password
    try {
        $plain = bin2hex(random_bytes(6)); // 12 chars
    } catch (Exception $e) {
        // fallback
        $plain = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 12);
    }
    $hash = password_hash($plain, PASSWORD_DEFAULT);

    // insert
    $ins = $db->prepare("INSERT INTO users (email, password, nama, role, created_at) VALUES (?, ?, ?, ?, NOW())");
    if (!$ins) { $out['msg'] = $db->error; return $out; }
    $ins->bind_param("ssss", $email, $hash, $nama, $role);
    if ($ins->execute()) {
        $uid = (int)$db->insert_id;
        $ins->close();
        $out['status'] = 'ok';
        $out['id'] = $uid;
        $out['password'] = $plain;
        return $out;
    } else {
        $out['msg'] = $ins->error;
        $ins->close();
        return $out;
    }
}

function attachUserToClass($db, $userId, $classId) {
    // Insert to class_user if not exists
    $q = $db->prepare("SELECT id FROM class_user WHERE user_id = ? AND class_id = ? LIMIT 1");
    if (!$q) return false;
    $q->bind_param("ii", $userId, $classId);
    $q->execute();
    $r = $q->get_result();
    if ($r && $r->num_rows > 0) { $q->close(); return true; }
    $q->close();

    $ins = $db->prepare("INSERT INTO class_user (class_id, user_id, created_at) VALUES (?, ?, NOW())");
    if (!$ins) return false;
    $ins->bind_param("ii", $classId, $userId);
    $ok = $ins->execute();
    $ins->close();
    return $ok;
}

// handle POST upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File CSV tidak ditemukan atau terjadi error upload.';
    } else {
        // cek tipe sederhana
        $tmp = $_FILES['csv_file']['tmp_name'];
        $mime = mime_content_type($tmp);
        // accept text/csv or application/vnd.ms-excel or plain text
        if (!in_array($mime, ['text/plain','text/csv','application/vnd.ms-excel','text/comma-separated-values','application/csv','application/octet-stream'])) {
            // kita tidak tolak sepenuhnya, hanya warning
            $warnings[] = "MIME file: {$mime} — akan diproses namun periksa isi file jika error.";
        }

        // buka CSV
        if (($handle = fopen($tmp, 'r')) !== false) {
            // optionally skip header if first row contains non-email in column 4 etc.
            $rowNo = 0;
            while (($row = fgetcsv($handle, 0, ",")) !== false) {
                $rowNo++;
                // skip empty lines
                $allEmpty = true;
                foreach ($row as $c) { if (trim($c) !== '') { $allEmpty = false; break; } }
                if ($allEmpty) continue;

                $total++;
                // Expected columns: nama, level/jenjang, jurusan, email
                // Toleransi kolom kurang/lebih
                $nama = isset($row[0]) ? trim($row[0]) : '';
                $level = isset($row[1]) ? trim($row[1]) : '';
                $jurusan = isset($row[2]) ? trim($row[2]) : '';
                $email = isset($row[3]) ? trim($row[3]) : '';

                // Basic validations
                if ($nama === '' || $email === '') {
                    $errors[] = "Baris {$rowNo}: Nama atau email kosong — dilewati.";
                    $skipCount++;
                    continue;
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Baris {$rowNo}: Email tidak valid ({$email}) — dilewati.";
                    $skipCount++;
                    continue;
                }

                // mulai transaksi per baris (untuk konsistensi)
                $db->begin_transaction();
                try {
                    // buat user jika belum ada
                    $rUser = createUserIfNotExists($db, $nama, $email, 'murid');
                    if ($rUser['status'] === 'exists') {
                        // user sudah ada — attach ke kelas jika class diberikan
                        $uid = $rUser['id'];
                        $skipCount++;
                        $warnings[] = "Baris {$rowNo}: Email sudah ada ({$email}). Tidak dibuat ulang.";
                        // attach to class if provided
                        if ($level !== '' || $jurusan !== '') {
                            $classId = resolveOrCreateClass($db, $level, $jurusan);
                            if ($classId) {
                                attachUserToClass($db, $uid, $classId);
                            }
                        }
                        $db->commit();
                        continue;
                    } elseif ($rUser['status'] === 'error') {
                        $errors[] = "Baris {$rowNo}: Gagal membuat user ({$email}) — {$rUser['msg']}";
                        $db->rollback();
                        $skipCount++;
                        continue;
                    }

                    // user dibuat sukses
                    $uid = $rUser['id'];
                    $plainPw = $rUser['password']; // simpan sementara untuk email

                    // attach to class if provided
                    if ($level !== '' || $jurusan !== '') {
                        $classId = resolveOrCreateClass($db, $level, $jurusan);
                        if ($classId) {
                            attachUserToClass($db, $uid, $classId);
                        } else {
                            // gagal resolve class -> rollback user? Kita isi warning dan tetap commit user.
                            $warnings[] = "Baris {$rowNo}: Gagal membuat/mendapatkan kelas untuk '{$level} / {$jurusan}' — user tetap dibuat.";
                        }
                    }

                    // coba kirim email (jika environment mendukung)
                    $sent = false;
                    $subject = "Akun Sekolah Anda";
                    $message = "Halo {$nama},\n\nAkun murid Anda telah dibuat.\nEmail: {$email}\nPassword: {$plainPw}\n\nSilakan login dan ganti kata sandi.\n";
                    $headers = "From: no-reply@localhost\r\n";
                    try {
                        $sent = @mail($email, $subject, $message, $headers);
                    } catch (Throwable $e) {
                        $sent = false;
                    }
                    if (!$sent) {
                        $warnings[] = "Baris {$rowNo}: User dibuat, namun email tidak berhasil dikirim ke {$email}.";
                    }

                    $db->commit();
                    $successCount++;
                } catch (Throwable $ex) {
                    $db->rollback();
                    $errors[] = "Baris {$rowNo}: Exception - " . $ex->getMessage();
                    $skipCount++;
                }
            } // end while
            fclose($handle);
        } else {
            $errors[] = "Tidak bisa membuka file CSV.";
        }
    }
} // end post

// Render page
?>
<div class="container">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
        <h1>Import Siswa (CSV)</h1>
        <a href="<?php echo BASE_URL; ?>/users/list.php" class="btn btn-secondary">← Kembali</a>
    </div>

    <div class="card" style="padding:16px;">
        <p>Pilih file CSV dengan kolom: <strong>nama, jenjang/level, jurusan, email</strong> (urutan). Header boleh ada atau tidak.</p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <strong>Errors:</strong>
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?php echo sanitize($e); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($warnings)): ?>
            <div class="alert alert-warning">
                <strong>Warnings:</strong>
                <ul>
                    <?php foreach ($warnings as $w): ?>
                        <li><?php echo sanitize($w); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div class="alert alert-success">
                <strong>Hasil import:</strong>
                <ul>
                    <li>Total baris diproses: <?php echo (int)$total; ?></li>
                    <li>Berhasil dibuat: <?php echo (int)$successCount; ?></li>
                    <li>Dilewati / sudah ada: <?php echo (int)$skipCount; ?></li>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <?php $csrf = generateCsrfToken('import_students'); ?>
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf); ?>">

            <div class="form-group">
                <label>Pilih file CSV</label><br>
                <input type="file" name="csv_file" accept=".csv,text/csv" required>
            </div>

            <div style="margin-top:10px;">
                <button class="btn btn-primary">Import</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
