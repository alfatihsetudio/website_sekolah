<?php
// account/change_password_admin.php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/mail_helper.php';

requireRole(['admin']); // hanya admin

$db        = getDB();
$userId    = (int)($_SESSION['user_id'] ?? 0);
$pageTitle = 'Ganti Email / Password Admin';

$error   = '';
$success = '';
$info    = '';

// --------- variabel untuk sticky form ----------
$formNewEmail     = '';
$formOldPass      = '';
$formNewPass      = '';
$formConfirmPass  = '';
$formVerifyCode   = '';
// ------------------------------------------------

// fungsi kirim email kode verifikasi
function sendAdminVerificationCode($email, $nama, $code) {
    // gunakan mail_helper.php kalau ada
    if (function_exists('send_password_email_smtp')) {
        $res = send_password_email_smtp($email, $nama, $code);
        if (is_array($res)) {
            return !empty($res['ok']);   // true kalau ok==true
        }
        return (bool)$res;
    }

    // kalau punya helper lain yang return boolean
    if (function_exists('send_password_email')) {
        $msg = "Kode verifikasi untuk ganti password akun admin Anda adalah: {$code}\n\nKode berlaku 10 menit.";
        return send_password_email($email, $nama, $msg);
    }

    // fallback: mail() standar
    $subject = 'Kode Verifikasi Ganti Password Admin';
    $body    = "Halo {$nama},\n\nKode verifikasi Anda: {$code}\n\nKode berlaku 10 menit.";
    $headers = "From: no-reply@example.com\r\n";
    return @mail($email, $subject, $body, $headers);
}

// ambil data admin
$stmt = $db->prepare("SELECT id, email, password, nama FROM users WHERE id = ? AND role = 'admin' LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res  = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$user) {
    $error = 'Akun admin tidak ditemukan.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrf, 'admin_change_account')) {
        $error = 'Token keamanan tidak valid.';
    } else {
        // nilai dari form
        $oldPass      = $_POST['old_password'] ?? '';
        $newPass      = $_POST['new_password'] ?? '';
        $confirmPass  = $_POST['new_password_confirm'] ?? '';
        $newEmailRaw  = trim($_POST['new_email'] ?? '');
        $verifyCode   = trim($_POST['verify_code'] ?? '');

        // simpan ke variabel sticky form
        $formNewEmail    = $newEmailRaw;
        $formOldPass     = $oldPass;
        $formNewPass     = $newPass;
        $formConfirmPass = $confirmPass;
        $formVerifyCode  = $verifyCode;

        // STEP 1: belum isi kode → kirim kode & simpan perubahan sementara
        if ($verifyCode === '') {
            $changeEmail = false;
            $changePass  = false;

            // cek email baru (optional)
            $newEmail = $user['email']; // default tetap email lama
            if ($newEmailRaw !== '' && $newEmailRaw !== $user['email']) {
                if (!filter_var($newEmailRaw, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Format email baru tidak valid.';
                } else {
                    $newEmail    = $newEmailRaw;
                    $changeEmail = true;
                }
            }

            // cek password baru (optional)
            if ($newPass !== '' || $confirmPass !== '') {
                if ($newPass === '' || $confirmPass === '') {
                    $error = 'Jika ingin mengganti password, isi password baru dan konfirmasinya.';
                } elseif ($newPass !== $confirmPass) {
                    $error = 'Password baru dan konfirmasi tidak sama.';
                } elseif (strlen($newPass) < 6) {
                    $error = 'Password baru minimal 6 karakter.';
                } else {
                    $changePass = true;
                }
            }

            // kalau tidak ada perubahan apa pun
            if (!$error && !$changeEmail && !$changePass) {
                $error = 'Tidak ada perubahan yang diajukan (email dan password tetap).';
            }

            // cek password lama (wajib kalau ada perubahan apa pun)
            if (!$error) {
                if ($oldPass === '') {
                    $error = 'Password lama wajib diisi.';
                } elseif (!password_verify($oldPass, $user['password'])) {
                    $error = 'Password lama salah.';
                }
            }

            // kalau semua valid → simpan pending + kirim kode
            if (!$error) {
                $_SESSION['admin_change_pending'] = [
                    'change_email'    => $changeEmail,
                    'new_email'       => $changeEmail ? $newEmail : $user['email'],
                    'change_password' => $changePass,
                    'new_password'    => $changePass ? password_hash($newPass, PASSWORD_DEFAULT) : null,
                    'created_at'      => time()
                ];

                $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $_SESSION['admin_change_code']     = $code;
                $_SESSION['admin_change_code_exp'] = time() + 600; // 10 menit

                $sent = sendAdminVerificationCode($user['email'], $user['nama'] ?? 'Admin', $code);
                if ($sent) {
                    $info = 'Kode verifikasi sudah dikirim ke email admin. Silakan cek inbox / spam, lalu masukkan kode di bawah.';
                    // setelah step 1, kita BIARKAN nilai form tetap,
                    // jadi user cuma perlu menambahkan kode verifikasi
                } else {
                    $error = 'Gagal mengirim kode verifikasi ke email.';
                }
            }

        // STEP 2: sudah isi kode → verifikasi & eksekusi perubahan
        } else {
            $savedCode   = $_SESSION['admin_change_code']     ?? null;
            $savedExpiry = $_SESSION['admin_change_code_exp'] ?? 0;
            $pending     = $_SESSION['admin_change_pending']  ?? null;

            if (!$savedCode || !$pending) {
                $error = 'Tidak ada perubahan yang tertunda atau kode belum diminta.';
            } elseif (time() > $savedExpiry) {
                $error = 'Kode verifikasi sudah kadaluarsa. Ajukan perubahan lagi.';
            } elseif ($verifyCode !== $savedCode) {
                $error = 'Kode verifikasi yang Anda masukkan salah.';
            } else {
                $changeEmail    = !empty($pending['change_email']);
                $changePassword = !empty($pending['change_password']);

                if (!$changeEmail && !$changePassword) {
                    $error = 'Tidak ada perubahan yang akan disimpan.';
                } else {
                    if ($changeEmail && $changePassword) {
                        $sql = "UPDATE users SET email = ?, password = ?, updated_at = NOW() WHERE id = ? LIMIT 1";
                        $stmt = $db->prepare($sql);
                        $stmt->bind_param(
                            "ssi",
                            $pending['new_email'],
                            $pending['new_password'],
                            $userId
                        );
                    } elseif ($changeEmail) {
                        $sql = "UPDATE users SET email = ?, updated_at = NOW() WHERE id = ? LIMIT 1";
                        $stmt = $db->prepare($sql);
                        $stmt->bind_param(
                            "si",
                            $pending['new_email'],
                            $userId
                        );
                    } else { // hanya password
                        $sql = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ? LIMIT 1";
                        $stmt = $db->prepare($sql);
                        $stmt->bind_param(
                            "si",
                            $pending['new_password'],
                            $userId
                        );
                    }

                    if (!$stmt) {
                        $error = 'Gagal menyiapkan query perubahan.';
                    } else {
                        if ($stmt->execute()) {
                            $success = 'Perubahan email/password admin berhasil disimpan.';

                            // bersihkan session & form (baru kosong DI SINI)
                            unset(
                                $_SESSION['admin_change_pending'],
                                $_SESSION['admin_change_code'],
                                $_SESSION['admin_change_code_exp']
                            );
                            $formNewEmail = $formOldPass = $formNewPass = $formConfirmPass = $formVerifyCode = '';

                            if ($changeEmail) {
                                $user['email'] = $pending['new_email'];
                            }
                            if ($changePassword) {
                                $user['password'] = $pending['new_password'];
                            }
                        } else {
                            $error = 'Gagal menyimpan perubahan: ' . $stmt->error;
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }
}

// render
include __DIR__ . '/../inc/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Ganti Email / Password Admin</h1>
        <p style="font-size:14px;color:#555;">
            Untuk keamanan, perubahan email/password membutuhkan
            <strong>kode verifikasi</strong> yang dikirim ke email admin.
        </p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo sanitize($error); ?></div>
    <?php endif; ?>
    <?php if ($info): ?>
        <div class="alert alert-info"><?php echo sanitize($info); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo sanitize($success); ?></div>
    <?php endif; ?>

    <div class="card" style="padding:16px;">
        <h3>Form Perubahan Email / Password</h3>
        <p style="font-size:13px;color:#555;">
            Email admin saat ini:
            <strong><?php echo sanitize($user['email'] ?? ''); ?></strong>
        </p>
        <form method="post">
            <?php $csrf = generateCsrfToken('admin_change_account'); ?>
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf); ?>">

            <div class="form-group">
                <label>Email Baru (opsional)</label>
                <input type="email"
                       name="new_email"
                       placeholder="Kosongkan jika tidak ingin ganti email"
                       value="<?php echo sanitize($formNewEmail); ?>">
            </div>

            <div class="form-group">
                <label>Password Lama (wajib jika ada perubahan)</label>
                <input type="password"
                       name="old_password"
                       required
                       value="<?php echo sanitize($formOldPass); ?>">
            </div>

            <div class="form-group">
                <label>Password Baru (opsional)</label>
                <input type="password"
                       name="new_password"
                       placeholder="Kosongkan jika tidak ingin ganti password"
                       value="<?php echo sanitize($formNewPass); ?>">
            </div>

            <div class="form-group">
                <label>Ulangi Password Baru</label>
                <input type="password"
                       name="new_password_confirm"
                       placeholder="Isi jika mengganti password"
                       value="<?php echo sanitize($formConfirmPass); ?>">
            </div>

            <hr>

            <div class="form-group">
                <label>Kode Verifikasi dari Email</label>
                <input type="text"
                       name="verify_code"
                       maxlength="6"
                       placeholder="Langkah 1: kosongkan dulu untuk meminta kode. Langkah 2: isi kode di sini."
                       value="<?php echo sanitize($formVerifyCode); ?>">
                <small>Kode akan dikirim ke email admin dan berlaku 10 menit.</small>
            </div>

            <button type="submit" class="btn btn-primary">
                Kirim / Simpan Perubahan
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
