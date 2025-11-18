<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireLogin(); // wajib login

$db        = getDB();
$baseUrl   = rtrim(BASE_URL, '/\\');
$userId    = (int)($_SESSION['user_id'] ?? 0);
$role      = getUserRole() ?: 'siswa';
$schoolId  = getCurrentSchoolId(); // multi-sekolah
$classes   = [];

// Jika school_id valid -> ambil SEMUA kelas di sekolah ini (untuk semua role)
if ($schoolId > 0) {
    $stmt = $db->prepare("
        SELECT 
            c.id,
            c.nama_kelas,
            c.no_telpon_wali,
            c.nama_km,
            c.no_telpon_km,
            c.guru_id,
            u.nama  AS guru_nama,
            u.email AS guru_email
        FROM classes c
        LEFT JOIN users u ON c.guru_id = u.id
        WHERE c.school_id = ?
        ORDER BY c.nama_kelas
    ");
    $stmt->bind_param("i", $schoolId);
    $stmt->execute();
    $res     = $stmt->get_result();
    $classes = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}

$pageTitle = 'Daftar Kelas';
include __DIR__ . '/../inc/header.php';
?>

<div class="container">
    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
        <div>
            <h1 style="margin-bottom:4px;">Daftar Kelas</h1>
            <p style="margin:0;font-size:0.9rem;color:#6b7280;">
                Anda login sebagai <strong><?php echo strtoupper(htmlspecialchars($role)); ?></strong>.
                Menampilkan seluruh kelas di sekolah Anda.
            </p>
        </div>

        <?php if ($role === 'admin'): ?>
            <a href="<?php echo $baseUrl; ?>/classes/create.php" class="btn btn-primary">
                + Tambah Kelas
            </a>
        <?php endif; ?>
    </div>

    <?php if ($schoolId <= 0): ?>
        <div class="card">
            <p>School ID tidak ditemukan. Silakan logout lalu login kembali.</p>
        </div>
    <?php elseif (empty($classes)): ?>
        <div class="card">
            <p>Tidak ada kelas yang terdaftar untuk sekolah Anda.</p>
        </div>
    <?php else: ?>
        <div class="card">
            <div style="overflow-x:auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Nama Kelas</th>
                            <th>Wali Kelas / Guru</th>
                            <th>No. Telp Wali Kelas</th>
                            <th>Nama KM</th>
                            <th>No. Telp KM</th>
                            <?php if ($role === 'admin'): ?>
                                <th style="width:180px;">Aksi</th>
                            <?php else: ?>
                                <th style="width:100px;">Detail</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classes as $i => $c): ?>
                            <?php
                                // tentukan label guru: pakai nama, kalau kosong pakai email, kalau dua-duanya kosong → "-"
                                $guruLabel = '-';
                                if (!empty($c['guru_nama'])) {
                                    $guruLabel = $c['guru_nama'];
                                } elseif (!empty($c['guru_email'])) {
                                    $guruLabel = $c['guru_email'];
                                }
                            ?>
                            <tr>
                                <td><?php echo (int)($i + 1); ?></td>
                                <td><?php echo sanitize($c['nama_kelas']); ?></td>
                                <td><?php echo sanitize($guruLabel); ?></td>
                                <td><?php echo sanitize($c['no_telpon_wali'] ?? '-'); ?></td>
                                <td><?php echo sanitize($c['nama_km'] ?? '-'); ?></td>
                                <td><?php echo sanitize($c['no_telpon_km'] ?? '-'); ?></td>
                                <td>
                                    <a href="<?php echo $baseUrl; ?>/classes/view.php?id=<?php echo (int)$c['id']; ?>">
                                        Lihat
                                    </a>
                                    <?php if ($role === 'admin'): ?>
                                        &middot;
                                        <a href="<?php echo $baseUrl; ?>/classes/edit.php?id=<?php echo (int)$c['id']; ?>">
                                            Edit
                                        </a>
                                        &middot;
                                        <a href="<?php echo $baseUrl; ?>/classes/delete.php?id=<?php echo (int)$c['id']; ?>"
                                           onclick="return confirm('Hapus kelas ini? Data terkait bisa ikut terpengaruh. Lanjutkan?');">
                                            Hapus
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
