<?php
// account/change_password_user.php
// Halaman ganti EMAIL & PASSWORD khusus GURU & MURID (bukan admin)

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/helpers.php';

requireLogin();

$db      = getDB();
$baseUrl = rtrim(BASE_URL, '/\\');

$currentUser = getCurrentUser();
$role        = getUserRole();

// Admin tidak menggunakan halaman ini
if ($role === 'admin') {
    header('Location: ' . $baseUrl . '/account/change_password_admin.php');
    exit;
}

// Halaman khusus guru + murid/siswa
if (!in_array($role, ['guru', 'murid', 'siswa'], true)) {
    http_response_code(403);
    echo "Halaman ini hanya untuk Guru / Murid.";
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldPass   = $_POST['old_password'] ?? '';
    $newPass   = $_POST['new_password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';
    $newEmail  = trim($_POST['new_email'] ?? '');

    // Wajib isi password lama untuk semua perubahan
    if ($oldPass === '') {
        $error = 'Password lama wajib diisi untuk mengubah email / password.';
    } else {
        // Minimal salah satu: email baru atau password baru
        if ($newEmail === '' && $newPass === '' && $confirm === '') {
            $error = 'Isi email baru dan/atau password baru yang ingin diubah.';
        }
    }

    // Validasi email baru (opsional)
    if ($error === '' && $newEmail !== '') {
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email baru tidak valid.';
        } else {
            // Cek apakah email sudah dipakai user lain
            $stmtE = $db->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
            if ($stmtE) {
                $uid = (int)$currentUser['id'];
                $stmtE->bind_param("si", $newEmail, $uid);
                $stmtE->execute();
                $resE = $stmtE->get_result();
                if ($resE && $resE->num_rows > 0) {
                    $error = 'Email baru sudah digunakan oleh akun lain.';
                }
                $stmtE->close();
            }
        }
    }

    // Validasi password baru (opsional)
    if ($error === '' && ($newPass !== '' || $confirm !== '')) {
        if (strlen($newPass) < 6) {
            $error = 'Password baru minimal 6 karakter.';
        } elseif ($newPass !== $confirm) {
            $error = 'Konfirmasi password baru tidak cocok.';
        }
    }

    if ($error === '') {
        // cek password lama di DB
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
        $uid = (int)$currentUser['id'];
        $stmt->bind_param("i", $uid);
        $stmt->execute();

        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            $error = 'Akun tidak ditemukan.';
        } elseif (!password_verify($oldPass, $row['password'])) {
            $error = 'Password lama yang Anda masukkan salah.';
        } else {
            // Susun UPDATE dinamis: bisa email saja, password saja, atau keduanya
            $fields = [];
            $types  = '';
            $values = [];

            if ($newEmail !== '' && $newEmail !== $currentUser['email']) {
                $fields[] = 'email = ?';
                $types   .= 's';
                $values[] = $newEmail;
            }

            if ($newPass !== '') {
                $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                $fields[] = 'password = ?';
                $types   .= 's';
                $values[] = $newHash;
            }

            if (empty($fields)) {
                $error = 'Tidak ada perubahan yang disimpan (email & password sama seperti sebelumnya).';
            } else {
                $fields[] = 'updated_at = NOW()';

                $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
                $stmtUp = $db->prepare($sql);
                if (!$stmtUp) {
                    $error = 'Gagal menyiapkan query update.';
                } else {
                    $types .= 'i';
                    $values[] = $uid;

                    // bind_param butuh by reference
                    $params = [];
                    $params[] = &$types;
                    foreach ($values as $k => $v) {
                        $params[] = &$values[$k];
                    }

                    call_user_func_array([$stmtUp, 'bind_param'], $params);

                    if ($stmtUp->execute()) {
                        $successParts = [];
                        if ($newEmail !== '' && $newEmail !== $currentUser['email']) {
                            $successParts[] = 'email';
                            // update email di session juga
                            $_SESSION['email'] = $newEmail;
                        }
                        if ($newPass !== '') {
                            $successParts[] = 'password';
                        }

                        if (!empty($successParts)) {
                            $success = 'Perubahan berhasil disimpan: ' . implode(' dan ', $successParts) . ' diperbarui.';
                        } else {
                            $success = 'Perubahan berhasil disimpan.';
                        }

                        $stmtUp->close();
                        // refresh data currentUser
                        $currentUser = getCurrentUser();
                    } else {
                        $error = 'Gagal menyimpan perubahan ke database.';
                        $stmtUp->close();
                    }
                }
            }
        }
    }
}

$pageTitle = 'Ganti Email & Password';
include __DIR__ . '/../inc/header.php';
?>

<div class="container" style="max-width:520px;margin:auto;">

    <div class="card" style="margin-top:20px;padding:20px;border-radius:14px;">
        <h1 style="margin-top:0;margin-bottom:10px;font-size:1.3rem;">
            🔐 Ganti Email & Password
        </h1>
        <p style="margin:0 0 14px 0;font-size:0.9rem;color:#6b7280;">
            Akun: <strong><?php echo sanitize($currentUser['nama'] ?? $currentUser['email']); ?></strong>
            (<?php echo strtoupper(sanitize($role)); ?>)<br>
            Username / nama akun <strong>tidak bisa diubah</strong>, hanya email dan password.
        </p>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo sanitize($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo sanitize($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <!-- Email sekarang (readonly) -->
            <div class="form-group">
                <label>Email saat ini</label>
                <input type="email" value="<?php echo sanitize($currentUser['email']); ?>" readonly>
            </div>

            <!-- Email baru (opsional) -->
            <div class="form-group">
                <label>Email baru (opsional)</label>
                <input
                    type="email"
                    name="new_email"
                    placeholder="Kosongkan jika tidak ingin mengganti email"
                    value="<?php echo isset($_POST['new_email']) ? sanitize($_POST['new_email']) : ''; ?>"
                >
            </div>

            <hr>

            <!-- Password lama -->
            <div class="form-group">
                <label>Password lama <span style="color:#b91c1c;">*</span></label>
                <input type="password" name="old_password" required placeholder="Masukkan password saat ini">
                <small class="text-muted">
                    Wajib diisi untuk mengkonfirmasi perubahan email / password.
                </small>
            </div>

            <!-- Password baru (opsional) -->
            <div class="form-group">
                <label>Password baru (opsional)</label>
                <input type="password" name="new_password" placeholder="Minimal 6 karakter, isi jika ingin mengganti">
            </div>

            <div class="form-group">
                <label>Konfirmasi password baru</label>
                <input type="password" name="confirm_password" placeholder="Ulangi password baru">
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                Simpan Perubahan
            </button>

            <p style="margin-top:12px;font-size:0.9rem;text-align:center;">
                <a href="<?php echo $baseUrl; ?>/dashboard/guru.php">← Kembali ke Dashboard</a>
            </p>
        </form>
    </div>

</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
