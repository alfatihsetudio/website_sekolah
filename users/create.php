<?php
// users/create.php
// Versi lengkap + safe: tambah murid/guru/admin + kirim email password (murid).
// Tidak merubah mail_helper.php/smtp_config.php — hanya gunakan jika tersedia.

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

// include mail_helper jika memang ada (opsional)
$mail_helper_path = __DIR__ . '/../inc/mail_helper.php';
if (file_exists($mail_helper_path)) {
    @include_once $mail_helper_path;
}

requireRole(['admin']);

$db = getDB();
$error = '';
$success = '';

// helper: map param to DB role
function mapRoleParamToDb($r) {
    if ($r === 'guru') return 'guru';
    if ($r === 'admin') return 'admin';
    return 'murid';
}

// helper: generate readable random password
function generate_plain_password($len = 8) {
    try {
        $bytes = random_bytes($len);
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $out = '';
        $cl = strlen($chars);
        for ($i = 0; $i < $len; $i++) {
            $out .= $chars[ord($bytes[$i]) % $cl];
        }
        return $out;
    } catch (Exception $e) {
        return substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'), 0, $len);
    }
}

// helper: log email send attempts
function _log_email_attempt($txt) {
    $dir = __DIR__ . '/../storage';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $file = $dir . '/email_log.txt';
    $line = "[" . date('Y-m-d H:i:s') . "] " . $txt . PHP_EOL;
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

// fallback send using PHPMailer (composer autoload)
function _send_via_phpmailer($toEmail, $toName, $plainPassword) {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        return ['ok' => false, 'msg' => 'composer autoload tidak ditemukan'];
    }
    $smtpCfg = [];
    $smtpFile = __DIR__ . '/../inc/smtp_config.php';
    if (file_exists($smtpFile)) {
        $cfg = include $smtpFile;
        if (is_array($cfg)) $smtpCfg = $cfg;
    }

    require_once $autoload;
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtpCfg['host'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $smtpCfg['username'] ?? ($smtpCfg['from_email'] ?? '');
        $mail->Password = $smtpCfg['password'] ?? '';
        $mail->SMTPSecure = $smtpCfg['secure'] ?? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtpCfg['port'] ?? 587;

        $fromEmail = $smtpCfg['from_email'] ?? ($smtpCfg['username'] ?? 'no-reply@example.com');
        $fromName = $smtpCfg['from_name'] ?? 'Web MG';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = "Akun baru - Informasi login";
        $body = "<p>Halo " . htmlspecialchars($toName) . ",</p>";
        $body .= "<p>Akun Anda telah dibuat di <strong>Web MG</strong>.</p>";
        $body .= "<p>Email: <strong>" . htmlspecialchars($toEmail) . "</strong><br>";
        $body .= "Password sementara: <strong>" . htmlspecialchars($plainPassword) . "</strong></p>";
        $body .= "<p>Silakan login lalu ganti password Anda.</p>";
        $body .= "<hr><p>Jika Anda tidak menerima email, hubungi administrator.</p>";
        $mail->Body = $body;
        $mail->AltBody = "Halo $toName\nAkun Anda telah dibuat.\nEmail: $toEmail\nPassword sementara: $plainPassword\nSilakan login lalu ganti password Anda.";

        $mail->send();
        return ['ok' => true, 'msg' => 'sent via phpmailer'];
    } catch (Exception $ex) {
        return ['ok' => false, 'msg' => 'PHPMailer error: ' . ($mail->ErrorInfo ?? '') . ' / ' . $ex->getMessage()];
    }
}

// helper: cek apakah kolom ada di tabel users
function users_has_column($col) {
    global $db;
    $dbName = '';
    // coba konstanta DB_NAME dulu, kalau tidak ada fallback ke DATABASE()
    if (defined('DB_NAME') && DB_NAME) {
        $dbName = DB_NAME;
    } else {
        $q = $db->query("SELECT DATABASE() AS dbname");
        if ($q) {
            $r = $q->fetch_assoc();
            $dbName = $r['dbname'] ?? '';
        }
    }
    if (!$dbName) return false;
    $colEsc = $db->real_escape_string($col);
    $sql = "SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . $db->real_escape_string($dbName) . "' AND TABLE_NAME = 'users' AND COLUMN_NAME = '{$colEsc}'";
    $res = $db->query($sql);
    if (!$res) return false;
    $row = $res->fetch_assoc();
    return ((int)$row['c']) > 0;
}

// Ambil daftar kelas untuk dropdown (dipakai form)
$classes = [];
$r = $db->query("SELECT id, nama_kelas, level, jurusan FROM classes ORDER BY nama_kelas, level, jurusan");
if ($r) {
    while ($row = $r->fetch_assoc()) $classes[] = $row;
}

// Process POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roleParam = $_POST['role'] ?? 'siswa';
    $roleDb = mapRoleParamToDb($roleParam);

    if ($roleDb === 'murid') {
        $nama = trim($_POST['nama'] ?? '');
        $jenjang = trim($_POST['jenjang'] ?? '');
        $jurusan = trim($_POST['jurusan'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $class_id = (int)($_POST['class_id'] ?? 0); // <- pastikan kita menerima class_id dari form

        if ($nama === '' || $jenjang === '' || $email === '') {
            $error = 'Nama lengkap, jenjang, dan email wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email tidak valid.';
        } else {
            // cek unik
            $chk = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            if (!$chk) {
                $error = 'DB error: ' . $db->error;
            } else {
                $chk->bind_param("s", $email);
                $chk->execute();
                $rchk = $chk->get_result();
                if ($rchk && $rchk->num_rows > 0) {
                    $error = 'Email sudah terdaftar.';
                    $chk->close();
                } else {
                    $chk->close();

                    // validasi kelas (opsional)
                    $validClass = false;
                    if ($class_id > 0) {
                        $qc = $db->prepare("SELECT id FROM classes WHERE id = ? LIMIT 1");
                        if ($qc) {
                            $qc->bind_param("i", $class_id);
                            $qc->execute();
                            $rc = $qc->get_result();
                            if ($rc && $rc->num_rows > 0) $validClass = true;
                            $qc->close();
                        }
                    }

                    // Jika admin tidak memilih class tetapi mengisi jenjang+jurusan,
                    // coba cari class_id otomatis berdasarkan nama_kelas = "jenjang jurusan"
                    if (!$validClass && $jenjang !== '' && $jurusan !== '') {
                        $lookup = trim($jenjang . ' ' . $jurusan);
                        $q = $db->prepare("SELECT id FROM classes WHERE TRIM(nama_kelas) = ? LIMIT 1");
                        if ($q) {
                            $q->bind_param("s", $lookup);
                            $q->execute();
                            $rq = $q->get_result();
                            if ($rq && $rq->num_rows > 0) {
                                $rowc = $rq->fetch_assoc();
                                $class_id = (int)$rowc['id'];
                                $validClass = true;
                            }
                            $q->close();
                        }
                    }

                    // generate password
                    $plain = generate_plain_password(8);
                    $hash = password_hash($plain, PASSWORD_DEFAULT);

                    // If users table has extra columns, include them in insert safely
                    $cols = ['email','password','nama','role','created_at'];
                    $placeholders = ['?','?','?','\'murid\'','NOW()'];
                    $bind_types = 'sss';
                    $bind_values = [$email, $hash, $nama];

                    $has_class_id_col = users_has_column('class_id');
                    $has_jenjang_col = users_has_column('jenjang');
                    $has_jurusan_col = users_has_column('jurusan');

                    if ($has_jenjang_col) {
                        $cols[] = 'jenjang';
                        $placeholders[] = '?';
                        $bind_types .= 's';
                        $bind_values[] = $jenjang;
                    }
                    if ($has_jurusan_col) {
                        $cols[] = 'jurusan';
                        $placeholders[] = '?';
                        $bind_types .= 's';
                        $bind_values[] = $jurusan;
                    }
                    if ($has_class_id_col) {
                        $cols[] = 'class_id';
                        if ($validClass) {
                            $placeholders[] = '?';
                            $bind_types .= 'i';
                            $bind_values[] = $class_id;
                        } else {
                            $placeholders[] = 'NULL';
                        }
                    }

                    // build query string
                    $cols_sql = implode(',', $cols);
                    $vals_sql = implode(',', $placeholders);
                    $sql = "INSERT INTO users ({$cols_sql}) VALUES ({$vals_sql})";

                    $ins = $db->prepare($sql);
                    if (!$ins) {
                        $error = 'Gagal menyiapkan query: ' . $db->error . ' SQL:' . $sql;
                    } else {
                        // bind dynamic params if ada (? placeholders)
                        if (!empty($bind_types)) {
                            $refs = [];
                            $refs[] = & $bind_types;
                            foreach ($bind_values as $k => $v) {
                                $refs[] = & $bind_values[$k];
                            }
                            // safe call
                            call_user_func_array([$ins, 'bind_param'], $refs);
                        }

                        if ($ins->execute()) {
                            $newId = $db->insert_id;

                            // enroll ke class_user jika class valid
                            if ($validClass) {
                                $en = $db->prepare("INSERT IGNORE INTO class_user (class_id, user_id, enrolled_at) VALUES (?, ?, NOW())");
                                if ($en) {
                                    $en->bind_param("ii", $class_id, $newId);
                                    $en->execute();
                                    $en->close();
                                }
                            }

                            // Send email: prefer mail_helper functions
                            $mail_sent = false;
                            $mail_msg = '';

                            if (function_exists('send_password_email_smtp')) {
                                try {
                                    $res = send_password_email_smtp($email, $nama, $plain);
                                    if ($res === true || (is_array($res) && !empty($res['ok']))) {
                                        $mail_sent = true;
                                    } else {
                                        $mail_msg = is_array($res) ? ($res['msg'] ?? json_encode($res)) : (string)$res;
                                    }
                                } catch (Throwable $t) {
                                    $mail_msg = 'Exception from mail helper: ' . $t->getMessage();
                                }
                            } elseif (function_exists('send_password_email')) {
                                try {
                                    $res = send_password_email($email, $nama, $plain);
                                    if ($res === true || (is_array($res) && !empty($res['ok']))) {
                                        $mail_sent = true;
                                    } else {
                                        $mail_msg = is_array($res) ? ($res['msg'] ?? json_encode($res)) : (string)$res;
                                    }
                                } catch (Throwable $t) {
                                    $mail_msg = 'Exception from mail helper: ' . $t->getMessage();
                                }
                            } else {
                                $fallback = _send_via_phpmailer($email, $nama, $plain);
                                if (!empty($fallback) && !empty($fallback['ok'])) {
                                    $mail_sent = true;
                                } else {
                                    $mail_msg = $fallback['msg'] ?? 'unknown fallback error';
                                }
                            }

                            // Log & set message
                            if ($mail_sent) {
                                _log_email_attempt("SENT: user_id={$newId} email={$email}");
                                $success = 'Akun siswa berhasil dibuat. Password sementara telah dikirim ke email siswa.';
                            } else {
                                _log_email_attempt("FAILED: user_id={$newId} email={$email} reason={$mail_msg}");
                                $success = 'Akun siswa berhasil dibuat. Namun pengiriman email gagal (cek storage/email_log.txt).';
                            }

                            $_POST = [];
                        } else {
                            $error = 'Gagal menyimpan pengguna: ' . $ins->error;
                        }
                        $ins->close();
                    }
                }
            }
        }
    } else {
        // guru/admin - admin masukkan password
        $nama = trim($_POST['nama'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $roleDb = in_array($roleDb = mapRoleParamToDb($_POST['role'] ?? ''), ['guru','admin']) ? $roleDb : 'guru';

        if ($nama === '' || $email === '' || $password === '') {
            $error = 'Nama, email, dan password wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email tidak valid.';
        } else {
            $chk = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            if (!$chk) {
                $error = 'DB error: ' . $db->error;
            } else {
                $chk->bind_param("s", $email);
                $chk->execute();
                $rchk = $chk->get_result();
                if ($rchk && $rchk->num_rows > 0) {
                    $error = 'Email sudah terdaftar.';
                    $chk->close();
                } else {
                    $chk->close();
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $ins = $db->prepare("INSERT INTO users (email, password, nama, role, created_at) VALUES (?, ?, ?, ?, NOW())");
                    if (!$ins) {
                        $error = 'Gagal menyiapkan query: ' . $db->error;
                    } else {
                        $ins->bind_param("ssss", $email, $hash, $nama, $roleDb);
                        if ($ins->execute()) {
                            $success = 'Akun ' . htmlspecialchars(strtoupper($roleDb)) . ' berhasil dibuat.';
                            $_POST = [];
                        } else {
                            $error = 'Gagal menyimpan pengguna: ' . $ins->error;
                        }
                        $ins->close();
                    }
                }
            }
        }
    }
}

// Render page
$pageTitle = 'Tambah Pengguna';
include __DIR__ . '/../inc/header.php';
?>

<div class="container">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
        <h1>Tambah Pengguna</h1>
        <a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/users/list.php" class="btn btn-secondary">← Kembali</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo sanitize($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo sanitize($success); ?></div>
    <?php endif; ?>

    <div class="card" style="padding:18px;">
        <h3>Pilih Jenis Pengguna</h3>
        <p class="small text-muted">Gunakan "Tambah Siswa (Manual)" untuk menambahkan satu siswa. Untuk import banyak siswa gunakan halaman Impor CSV.</p>

        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;">
            <a href="?role=siswa" class="btn <?php echo (!isset($_GET['role']) || $_GET['role']==='siswa')? 'btn-primary':'btn-outline'; ?>">Tambah Siswa (Manual)</a>
            <a href="?role=guru" class="btn <?php echo (isset($_GET['role']) && $_GET['role']==='guru')? 'btn-primary':'btn-outline'; ?>">Tambah Guru</a>
            <a href="?role=admin" class="btn <?php echo (isset($_GET['role']) && $_GET['role']==='admin')? 'btn-primary':'btn-outline'; ?>">Tambah Admin</a>
            <a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/users/import_students.php" class="btn btn-secondary">Impor Siswa (CSV)</a>
        </div>

        <?php $roleToShow = $_GET['role'] ?? 'siswa'; ?>

        <?php if ($roleToShow === 'siswa' || $roleToShow === ''): ?>
            <form method="POST" class="form-vertical">
                <input type="hidden" name="role" value="siswa">
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama" required value="<?php echo isset($_POST['nama']) ? sanitize($_POST['nama']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>Jenjang / Level</label>
                    <input type="text" name="jenjang" required placeholder="Contoh: 12" value="<?php echo isset($_POST['jenjang']) ? sanitize($_POST['jenjang']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>Jurusan</label>
                    <input type="text" name="jurusan" placeholder="Contoh: RPL" value="<?php echo isset($_POST['jurusan']) ? sanitize($_POST['jurusan']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required value="<?php echo isset($_POST['email']) ? sanitize($_POST['email']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>Kelas (opsional) — pilih jika ingin langsung assign ke kelas</label>
                    <select name="class_id">
                        <option value="0">-- Tidak pilih --</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>" <?php echo (isset($_POST['class_id']) && (int)$_POST['class_id'] === (int)$c['id']) ? 'selected' : ''; ?>>
                                <?php echo sanitize($c['nama_kelas'] . ' ' . ($c['level'] ?? '') . ' ' . ($c['jurusan'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="small text-muted">Jika dikosongkan, sistem akan mencoba mencocokkan berdasarkan Jenjang+Jurusan (mis: "12 RPL").</small>
                </div>

                <button class="btn btn-primary" type="submit">Buat Akun Siswa</button>
            </form>

            <hr>
            <p class="small text-muted">Password akan digenerate otomatis dan dikirim ke email siswa (jika pengiriman dikonfigurasi). Administrator tidak dapat melihat password.</p>

        <?php else: ?>
            <form method="POST" class="form-vertical">
                <input type="hidden" name="role" value="<?php echo sanitize($roleToShow); ?>">
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama" required value="<?php echo isset($_POST['nama']) ? sanitize($_POST['nama']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required value="<?php echo isset($_POST['email']) ? sanitize($_POST['email']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="text" name="password" required placeholder="Buat password sementara">
                </div>

                <button class="btn btn-primary" type="submit">Buat Akun <?php echo strtoupper(sanitize($roleToShow)); ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
