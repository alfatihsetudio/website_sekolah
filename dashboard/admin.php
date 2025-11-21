<?php
// dashboard/admin.php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireRole(['admin']);

$db       = getDB();
$schoolId = (int) getCurrentSchoolId();
$baseUrl  = rtrim(BASE_URL, '/\\');

// helper singkat ambil 1 angka
function getCount($db, $sql) {
    $res = $db->query($sql);
    if ($res) {
        $row = $res->fetch_row();
        return (int) ($row[0] ?? 0);
    }
    return 0;
}

// -------------------- STATISTIK UTAMA PER SEKOLAH -------------------- //
$counts = [
    'users'         => 0,
    'guru'          => 0,
    'siswa'         => 0,
    'classes'       => 0,
    'subjects'      => 0,
    'materials'     => 0,
    'assignments'   => 0,
    'notifications' => 0,
];

$quality = [
    'guru_no_class'  => 0,
    'murid_no_class' => 0,
];

if ($schoolId > 0) {
    // Users (selalu pakai school_id di users)
    $counts['users'] = getCount($db,
        "SELECT COUNT(*) FROM users WHERE school_id = {$schoolId}"
    );
    $counts['guru']  = getCount($db,
        "SELECT COUNT(*) FROM users WHERE role = 'guru' AND school_id = {$schoolId}"
    );
    $counts['siswa'] = getCount($db,
        "SELECT COUNT(*) FROM users 
         WHERE (role = 'siswa' OR role = 'murid') AND school_id = {$schoolId}"
    );

    // Kelas & Mapel (sudah punya school_id sendiri)
    $counts['classes']  = getCount($db,
        "SELECT COUNT(*) FROM classes WHERE school_id = {$schoolId}"
    );
    $counts['subjects'] = getCount($db,
        "SELECT COUNT(*) FROM subjects WHERE school_id = {$schoolId}"
    );

    // Aktivitas belajar: sekarang sudah di-scope per sekolah
    $counts['materials'] = getCount($db,
        "SELECT COUNT(*) FROM materials WHERE school_id = {$schoolId}"
    );
    $counts['assignments'] = getCount($db,
        "SELECT COUNT(*) FROM assignments WHERE school_id = {$schoolId}"
    );
    $counts['notifications'] = getCount($db,
        "SELECT COUNT(*) FROM notifications WHERE school_id = {$schoolId}"
    );

    // -------------------- KUALITAS DATA -------------------- //
    // Guru yang belum tercatat sebagai wali / pengampu kelas
    $quality['guru_no_class'] = getCount($db,
        "SELECT COUNT(*) FROM users u
         WHERE u.role = 'guru'
           AND u.school_id = {$schoolId}
           AND u.id NOT IN (
               SELECT DISTINCT guru_id
               FROM classes
               WHERE school_id = {$schoolId}
                 AND guru_id IS NOT NULL
           )"
    );

    // Murid yang belum punya kelas (tidak di class_user dan users.class_id kosong)
    $quality['murid_no_class'] = getCount($db,
        "SELECT COUNT(*) FROM users u
         WHERE (u.role = 'murid' OR u.role = 'siswa')
           AND u.school_id = {$schoolId}
           AND (u.class_id IS NULL OR u.class_id = 0)
           AND u.id NOT IN (
               SELECT DISTINCT cu.user_id
               FROM class_user cu
               JOIN classes c ON cu.class_id = c.id
               WHERE c.school_id = {$schoolId}
           )"
    );
}

// -------------------- INFO SEKOLAH & ADMIN -------------------- //
$currentAdmin = getCurrentUser(); // dari auth.php

$school = [
    'nama_sekolah' => 'Belum diatur',
    'alamat'       => '',
    'created_at'   => null,
];

if ($schoolId > 0) {
    $stmt = $db->prepare("
        SELECT nama_sekolah, alamat, created_at
        FROM schools
        WHERE id = ?
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("i", $schoolId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows) {
            $row = $res->fetch_assoc();
            $school['nama_sekolah'] = $row['nama_sekolah'] ?: 'Sekolah';
            $school['alamat']       = $row['alamat'] ?? '';
            $school['created_at']   = $row['created_at'] ?? null;
        }
        $stmt->close();
    }
}

// -------------------- DATA TERBARU (per sekolah) -------------------- //
$latestUsers   = [];
$latestClasses = [];

if ($schoolId > 0) {
    // 5 pengguna terakhir di sekolah ini
    $stmt = $db->prepare("
        SELECT id, nama, email, role, created_at
        FROM users
        WHERE school_id = ?
        ORDER BY id DESC
        LIMIT 5
    ");
    if ($stmt) {
        $stmt->bind_param("i", $schoolId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            $latestUsers = $res->fetch_all(MYSQLI_ASSOC);
        }
        $stmt->close();
    }

    // 5 kelas terakhir di sekolah ini
    $stmt = $db->prepare("
        SELECT id, nama_kelas, level, jurusan, created_at
        FROM classes
        WHERE school_id = ?
        ORDER BY id DESC
        LIMIT 5
    ");
    if ($stmt) {
        $stmt->bind_param("i", $schoolId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            $latestClasses = $res->fetch_all(MYSQLI_ASSOC);
        }
        $stmt->close();
    }
}

$pageTitle = 'Dashboard Admin';
include __DIR__ . '/../inc/header.php';
?>

<div class="container">

    <!-- HEADER DASHBOARD -->
    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <div>
            <h1 style="margin:0 0 4px 0;">Dashboard Admin</h1>
            <p style="margin:0;font-size:0.9rem;color:#6b7280;">
                Pusat kontrol untuk mengelola <strong>akun sekolah</strong>,
                <strong>guru &amp; murid</strong>, serta <strong>struktur kelas</strong>.
            </p>
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?php echo $baseUrl; ?>/users/create.php" class="btn btn-primary">+ Pengguna baru</a>
            <a href="<?php echo $baseUrl; ?>/classes/create.php" class="btn btn-secondary">+ Kelas baru</a>
            <a href="<?php echo $baseUrl; ?>/classes/import_excel.php" class="btn btn-secondary">Import kelas CSV</a>
        </div>
    </div>

    <!-- BARIS ATAS: PROFIL SEKOLAH + AKUN ADMIN -->
    <div style="display:grid;grid-template-columns: minmax(0,2fr) minmax(0,1.2fr);gap:16px;margin-bottom:18px;">

        <!-- Profil Sekolah -->
        <div class="card" style="border-radius:14px;">
            <h2 style="margin-top:0;margin-bottom:6px;font-size:1.05rem;">Profil Sekolah</h2>
            <p style="margin:0;font-weight:600;font-size:1rem;">
                <?php echo sanitize($school['nama_sekolah']); ?>
            </p>

            <?php if (!empty($school['alamat'])): ?>
                <p style="margin:2px 0 8px 0;font-size:0.88rem;color:#6b7280;">
                    <?php echo nl2br(sanitize($school['alamat'])); ?>
                </p>
            <?php endif; ?>

            <dl style="margin:4px 0 0 0;font-size:0.86rem;color:#4b5563;">
                <div style="margin-bottom:4px;">
                    <dt style="font-weight:600;display:inline;">Total pengguna:</dt>
                    <dd style="display:inline;margin:0;">
                        <?php echo (int) $counts['users']; ?>
                        (Guru: <?php echo (int) $counts['guru']; ?>,
                        Murid: <?php echo (int) $counts['siswa']; ?>)
                    </dd>
                </div>
                <div style="margin-bottom:4px;">
                    <dt style="font-weight:600;display:inline;">Kelas terdaftar:</dt>
                    <dd style="display:inline;margin:0;"><?php echo (int) $counts['classes']; ?></dd>
                </div>
                <div style="margin-bottom:4px;">
                    <dt style="font-weight:600;display:inline;">Mata pelajaran:</dt>
                    <dd style="display:inline;margin:0;"><?php echo (int) $counts['subjects']; ?></dd>
                </div>
                <?php if (!empty($school['created_at'])): ?>
                    <div style="margin-top:4px;">
                        <dt style="font-weight:600;display:inline;">Bergabung sejak:</dt>
                        <dd style="display:inline;margin:0;"><?php echo sanitize($school['created_at']); ?></dd>
                    </div>
                <?php endif; ?>
            </dl>
        </div>

        <!-- Info Admin -->
        <div class="card" style="border-radius:14px;">
            <h2 style="margin-top:0;margin-bottom:6px;font-size:1.05rem;">Akun Admin</h2>
            <p style="margin:0;font-size:0.95rem;font-weight:500;">
                <?php echo sanitize($currentAdmin['nama'] ?? 'Admin'); ?>
            </p>
            <p style="margin:2px 0 8px 0;font-size:0.86rem;color:#6b7280;">
                <?php echo sanitize($currentAdmin['email'] ?? ''); ?>
            </p>

            <ul style="margin:0 0 10px 18px;padding:0;font-size:0.84rem;color:#6b7280;">
                <li>Mengelola guru dan murid di sekolah ini</li>
                <li>Mengatur struktur kelas &amp; mata pelajaran</li>
                <li>Menjaga keamanan akun dan data sekolah</li>
            </ul>

            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="<?php echo $baseUrl; ?>/account/settings.php" class="btn btn-secondary">
                    Pengaturan profil
                </a>
                <a href="<?php echo $baseUrl; ?>/account/change_password_admin.php" class="btn btn-secondary">
                    Ganti email / password
                </a>
            </div>
        </div>
    </div>

    <!-- STATISTIK GRID -->
    <div style="display:grid;grid-template-columns: repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:18px;">

        <div class="card" style="border-radius:14px;">
            <h3 style="margin-top:0;">Pengguna</h3>
            <p style="font-size:28px;margin:8px 0;"><?php echo (int) $counts['users']; ?></p>
            <div class="small text-muted">
                Guru: <?php echo (int) $counts['guru']; ?> ·
                Murid: <?php echo (int) $counts['siswa']; ?>
            </div>
            <div style="margin-top:10px;">
                <a href="<?php echo $baseUrl; ?>/users/list.php">Kelola pengguna →</a>
            </div>
        </div>

        <div class="card" style="border-radius:14px;">
            <h3 style="margin-top:0;">Kelas</h3>
            <p style="font-size:28px;margin:8px 0;"><?php echo (int) $counts['classes']; ?></p>
            <div class="small text-muted">Struktur rombel &amp; wali kelas.</div>
            <div style="margin-top:10px;">
                <a href="<?php echo $baseUrl; ?>/classes/list.php">Kelola kelas →</a>
            </div>
        </div>

        <div class="card" style="border-radius:14px;">
            <h3 style="margin-top:0;">Mata pelajaran</h3>
            <p style="font-size:28px;margin:8px 0;"><?php echo (int) $counts['subjects']; ?></p>
            <div class="small text-muted">Mapel yang digunakan di sekolah.</div>
            <div style="margin-top:10px;">
                <a href="<?php echo $baseUrl; ?>/subjects/list.php">Kelola mapel →</a>
            </div>
        </div>

        <div class="card" style="border-radius:14px;">
            <h3 style="margin-top:0;">Ringkasan aktivitas</h3>
            <p style="margin:6px 0 0 0;font-size:0.86rem;color:#6b7280;">
                Untuk memantau seberapa aktif guru &amp; murid menggunakan sistem.
            </p>
            <ul style="margin:8px 0 0 18px;padding:0;font-size:0.84rem;color:#4b5563;">
                <li>Materi terunggah: <strong><?php echo (int) $counts['materials']; ?></strong></li>
                <li>Tugas tercatat: <strong><?php echo (int) $counts['assignments']; ?></strong></li>
                <li>Notifikasi terkirim: <strong><?php echo (int) $counts['notifications']; ?></strong></li>
            </ul>
        </div>
    </div>

    <!-- KUALITAS DATA + DATA TERBARU -->
    <div style="display:grid;grid-template-columns:minmax(0,2fr) minmax(0,3fr);gap:16px;margin-bottom:18px;">

        <!-- Kualitas data -->
        <div class="card" style="border-radius:14px;">
            <h3 style="margin-top:0;">Kualitas data</h3>
            <p style="margin:0 0 8px 0;font-size:0.86rem;color:#6b7280;">
                Bantu admin mengecek data yang perlu dibereskan.
            </p>

            <ul style="margin:0 0 4px 18px;padding:0;font-size:0.88rem;color:#111827;">
                <li>
                    <strong><?php echo (int) $quality['guru_no_class']; ?></strong>
                    guru belum terhubung ke kelas mana pun.
                    <br><span style="font-size:0.8rem;color:#6b7280;">
                        Solusi: atur mereka sebagai wali/pengampu di menu Kelas.
                    </span>
                </li>
                <li style="margin-top:6px;">
                    <strong><?php echo (int) $quality['murid_no_class']; ?></strong>
                    murid belum punya kelas.
                    <br><span style="font-size:0.8rem;color:#6b7280;">
                        Solusi: masukkan murid tersebut ke rombel melalui menu Kelas / Pengguna.
                    </span>
                </li>
            </ul>
        </div>

        <!-- Data terbaru: pengguna & kelas -->
        <div class="card" style="border-radius:14px;">
            <h3 style="margin-top:0;">Aktivitas terbaru</h3>
            <div style="display:grid;grid-template-columns: minmax(0,1fr) minmax(0,1fr);gap:12px;font-size:0.88rem;">

                <div>
                    <strong>Pengguna baru</strong>
                    <?php if (empty($latestUsers)): ?>
                        <p style="margin:4px 0 0 0;color:#6b7280;">Belum ada data.</p>
                    <?php else: ?>
                        <ul style="margin:4px 0 0 0;padding-left:18px;">
                            <?php foreach ($latestUsers as $u): ?>
                                <li>
                                    <?php echo sanitize($u['nama'] ?: $u['email']); ?>
                                    <br><span style="font-size:0.78rem;color:#6b7280;">
                                        <?php echo strtoupper(sanitize($u['role'])); ?>
                                        • <?php echo sanitize($u['created_at']); ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div>
                    <strong>Kelas baru</strong>
                    <?php if (empty($latestClasses)): ?>
                        <p style="margin:4px 0 0 0;color:#6b7280;">Belum ada data.</p>
                    <?php else: ?>
                        <ul style="margin:4px 0 0 0;padding-left:18px;">
                            <?php foreach ($latestClasses as $c): ?>
                                <li>
                                    <?php
                                        $label = trim(($c['level'] ?? '') . ' ' . ($c['jurusan'] ?? ''));
                                        if (!empty($c['nama_kelas']) && $label !== trim($c['nama_kelas'])) {
                                            $label .= ' (' . $c['nama_kelas'] . ')';
                                        }
                                        echo sanitize($label);
                                    ?>
                                    <br><span style="font-size:0.78rem;color:#6b7280;">
                                        <?php echo sanitize($c['created_at']); ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

            </div>
        </div>

    </div>

    <!-- AKSI CEPAT ADMIN -->
    <div class="card" style="border-radius:14px;">
        <h3 style="margin-top:0;">Aksi cepat admin</h3>
        <p style="margin:2px 0 10px 0;font-size:0.86rem;color:#6b7280;">
            Gunakan menu di bawah ini untuk pekerjaan admin sehari-hari.
        </p>
        <div style="display:grid;grid-template-columns: repeat(auto-fit,minmax(220px,1fr));gap:8px;font-size:0.9rem;">

            <div>
                <strong>Pengguna</strong>
                <ul style="margin:4px 0 0 18px;padding:0;font-size:0.86rem;">
                    <li><a href="<?php echo $baseUrl; ?>/users/create.php">Buat pengguna baru (guru/murid)</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/users/list.php">Kelola semua pengguna</a></li>
                </ul>
            </div>

            <div>
                <strong>Struktur kelas &amp; mapel</strong>
                <ul style="margin:4px 0 0 18px;padding:0;font-size:0.86rem;">
                    <li><a href="<?php echo $baseUrl; ?>/classes/create.php">Buat kelas baru</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/classes/import_excel.php">Import kelas dari CSV</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/subjects/create.php">Tambah mata pelajaran</a></li>
                </ul>
            </div>

            <div>
                <strong>Monitoring belajar</strong>
                <ul style="margin:4px 0 0 18px;padding:0;font-size:0.86rem;">
                    <li><a href="<?php echo $baseUrl; ?>/materials/list.php">Lihat semua materi</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/assignments/list.php">Lihat semua tugas</a></li>
                </ul>
            </div>

            <div>
                <strong>Keamanan &amp; akun</strong>
                <ul style="margin:4px 0 0 18px;padding:0;font-size:0.86rem;">
                    <li><a href="<?php echo $baseUrl; ?>/account/change_password_admin.php">Ganti email / password admin</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/auth/logout.php">Keluar dari sistem</a></li>
                </ul>
            </div>

        </div>
    </div>

</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
