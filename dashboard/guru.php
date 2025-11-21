<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireRole(['guru']);

$db       = getDB();
$baseUrl  = rtrim(BASE_URL, '/\\');
$userId   = (int)($_SESSION['user_id'] ?? 0);
$schoolId = getCurrentSchoolId();
$guru     = getCurrentUser();

// Default nilai
$classCount            = 0;
$studentCount          = 0;
$assignmentCount       = 0;
$upcomingAssignmentCnt = 0;
$materialCount         = 0;
$classes               = [];
$upcomingAssignments   = [];

if ($schoolId > 0) {
    // --- KELAS YANG DIAMPU GURU INI ---
    $classIds = [];

    $stmt = $db->prepare("
        SELECT id, nama_kelas, level, jurusan
        FROM classes
        WHERE guru_id = ? AND school_id = ?
        ORDER BY nama_kelas
    ");
    $stmt->bind_param("ii", $userId, $schoolId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        $classes = $res->fetch_all(MYSQLI_ASSOC);
        foreach ($classes as $c) {
            $classIds[] = (int)$c['id'];
        }
    }
    $stmt->close();

    $classCount = count($classes);

    // --- HITUNG MURID DI SEMUA KELAS YANG DIAMPU ---
    if (!empty($classIds)) {
        $idList = implode(',', array_map('intval', $classIds));
        $sqlStu = "
            SELECT COUNT(DISTINCT u.id) AS total_murid
            FROM users u
            JOIN class_user cu ON cu.user_id = u.id
            JOIN classes c     ON cu.class_id = c.id
            WHERE c.id IN ($idList)
              AND c.school_id = {$schoolId}
              AND (u.role = 'murid' OR u.role = 'siswa')
        ";
        $resStu = $db->query($sqlStu);
        if ($resStu) {
            $rowStu       = $resStu->fetch_assoc();
            $studentCount = (int)($rowStu['total_murid'] ?? 0);
        }
    }

    // --- HITUNG TUGAS YANG DIBUAT GURU INI (PER SEKOLAH) ---
    $stmtA = $db->prepare("
        SELECT COUNT(*) AS total_tugas
        FROM assignments a
        JOIN classes c ON a.target_class_id = c.id
        WHERE a.created_by = ?
          AND c.school_id  = ?
    ");
    $stmtA->bind_param("ii", $userId, $schoolId);
    $stmtA->execute();
    $resA = $stmtA->get_result();
    if ($resA) {
        $rowA            = $resA->fetch_assoc();
        $assignmentCount = (int)($rowA['total_tugas'] ?? 0);
    }
    $stmtA->close();

    // Tugas yang belum lewat deadline (maks 5 untuk list)
    $stmtB = $db->prepare("
        SELECT a.id, a.judul, a.deadline, c.nama_kelas
        FROM assignments a
        JOIN classes c ON a.target_class_id = c.id
        WHERE a.created_by = ?
          AND c.school_id  = ?
          AND a.deadline IS NOT NULL
          AND a.deadline <> '0000-00-00 00:00:00'
          AND a.deadline >= NOW()
        ORDER BY a.deadline ASC
        LIMIT 5
    ");
    $stmtB->bind_param("ii", $userId, $schoolId);
    $stmtB->execute();
    $resB = $stmtB->get_result();
    if ($resB) {
        while ($row = $resB->fetch_assoc()) {
            $upcomingAssignments[] = $row;
        }
        $upcomingAssignmentCnt = count($upcomingAssignments);
    }
    $stmtB->close();

    // --- HITUNG MATERI (sementara global) ---
    $resM = $db->query("SELECT COUNT(*) AS total_materi FROM materials");
    if ($resM) {
        $rowM          = $resM->fetch_assoc();
        $materialCount = (int)($rowM['total_materi'] ?? 0);
    }
}

$pageTitle = 'Dashboard Guru';
include __DIR__ . '/../inc/header.php';
?>

<!-- CSS khusus dashboard guru -->
<style>
.dashboard-header {
    display:flex;
    flex-wrap:wrap;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    margin-bottom:16px;
}
.dashboard-header-main h1 {
    font-size:1.4rem;
    margin:0 0 4px 0;
}
.dashboard-header-main p {
    margin:0;
    font-size:0.9rem;
    color:#6b7280;
}
.dashboard-header-side {
    display:flex;
    flex-direction:column;
    gap:6px;
    font-size:0.85rem;
    align-items:flex-end;
}
.dashboard-header-side .subtext {
    color:#6b7280;
}

.stat-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:14px;
    margin-bottom:18px;
}
.stat-card-title {
    font-size:0.95rem;
    margin-bottom:4px;
}
.stat-card-value {
    font-size:1.7rem;
    font-weight:600;
    margin:4px 0;
}
.stat-card-desc {
    font-size:0.85rem;
    color:#6b7280;
}

.main-grid {
    display:grid;
    grid-template-columns:minmax(0,1.6fr) minmax(0,1.2fr);
    gap:16px;
    margin-bottom:18px;
}
@media(max-width:840px){
    .main-grid {
        grid-template-columns:1fr;
    }
}

.subtext {
    font-size:0.9rem;
    color:#6b7280;
}

/* quick actions: tile besar, full-card clickable */
.quick-actions {
    margin-top:4px;
}
.quick-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
    gap:12px;
}
.quick-tile {
    display:block;
    background:#ffffff;
    border-radius:12px;
    padding:12px 14px;
    box-shadow:0 1px 3px rgba(15,23,42,0.06);
    border:1px solid #e5e7eb;
    text-decoration:none;
    color:#111827;
    transition:transform 0.08s ease, box-shadow 0.08s ease, border-color 0.08s ease, background 0.08s ease;
}
.quick-tile:hover {
    transform:translateY(-1px);
    box-shadow:0 4px 10px rgba(15,23,42,0.12);
    border-color:#c7d2fe;
    background:#f3f4ff;
    text-decoration:none;
}
.quick-icon {
    font-size:1.4rem;
    margin-bottom:4px;
}
.quick-title {
    font-size:0.95rem;
    font-weight:600;
    margin-bottom:2px;
}
.quick-desc {
    font-size:0.82rem;
    color:#6b7280;
}
</style>

<div class="card dashboard-header">
    <div class="dashboard-header-main">
        <h1>Dashboard Guru</h1>
        <p>
            Selamat datang, <strong><?php echo sanitize($guru['nama'] ?? 'Guru'); ?></strong>.<br>
            Ringkasan kelas, murid, dan aktivitas mengajar Anda.
        </p>
    </div>
    <div class="dashboard-header-side">
        <div class="subtext">
            Email: <strong><?php echo sanitize($guru['email'] ?? ''); ?></strong>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
            <a href="<?php echo $baseUrl; ?>/account/change_password_user.php" class="btn btn-secondary">
                Ganti email & password
            </a>
            <a href="<?php echo $baseUrl; ?>/auth/logout.php" class="btn btn-secondary">
                Keluar
            </a>
        </div>
    </div>
</div>

<!-- STATISTIK RINGKAS -->
<div class="stat-grid">
    <div class="card">
        <div class="stat-card-title">Kelas diampu</div>
        <div class="stat-card-value"><?php echo (int)$classCount; ?></div>
        <div class="stat-card-desc">Jumlah kelas yang Anda bimbing.</div>
    </div>
    <div class="card">
        <div class="stat-card-title">Total murid</div>
        <div class="stat-card-value"><?php echo (int)$studentCount; ?></div>
        <div class="stat-card-desc">Murid yang terdaftar di kelas-kelas Anda.</div>
    </div>
    <div class="card">
        <div class="stat-card-title">Tugas aktif</div>
        <div class="stat-card-value"><?php echo (int)$upcomingAssignmentCnt; ?></div>
        <div class="stat-card-desc">Tugas yang belum melewati deadline.</div>
    </div>
    <div class="card">
        <div class="stat-card-title">Materi</div>
        <div class="stat-card-value"><?php echo (int)$materialCount; ?></div>
        <div class="stat-card-desc">Jumlah materi di sistem.</div>
    </div>
</div>

<!-- GRID KONTEN UTAMA -->
<div class="main-grid">

    <!-- KELAS DIAMPU -->
    <div class="card">
        <h3 style="margin-top:0;margin-bottom:6px;">Kelas yang Anda ampu</h3>
        <?php if (empty($classes)): ?>
            <p class="subtext" style="margin:4px 0;">
                Anda belum terdaftar sebagai wali/pengampu kelas apa pun.
            </p>
        <?php else: ?>
            <div style="overflow-x:auto;margin-top:4px;">
                <table>
                    <thead>
                    <tr>
                        <th style="width:36px;">#</th>
                        <th>Nama Kelas</th>
                        <th>Level / Jurusan</th>
                        <th style="width:200px;">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($classes as $i => $c): ?>
                        <tr>
                            <td><?php echo (int)($i + 1); ?></td>
                            <td><?php echo sanitize($c['nama_kelas']); ?></td>
                            <td>
                                <?php
                                $lv   = $c['level']   ?? '';
                                $jur  = $c['jurusan'] ?? '';
                                $info = trim($lv . ' ' . $jur);
                                echo sanitize($info !== '' ? $info : '-');
                                ?>
                            </td>
                            <td>
                                <a href="<?php echo $baseUrl; ?>/classes/view.php?id=<?php echo (int)$c['id']; ?>">Detail</a>
                                &middot;
                                <a href="<?php echo $baseUrl; ?>/attendance/take.php?class_id=<?php echo (int)$c['id']; ?>">Absensi</a>
                                &middot;
                                <a href="<?php echo $baseUrl; ?>/attendance/report_class.php?class_id=<?php echo (int)$c['id']; ?>">Rekap</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- TUGAS MENDATANG + NOTIF -->
    <div style="display:flex;flex-direction:column;gap:12px;">
        <div class="card">
            <h3 style="margin-top:0;margin-bottom:6px;">Tugas mendatang</h3>
            <?php if (empty($upcomingAssignments)): ?>
                <p class="subtext" style="margin:4px 0;">
                    Belum ada tugas yang akan datang atau semua tugas sudah melewati deadline.
                </p>
            <?php else: ?>
                <ul style="list-style:none;margin:4px 0 0 0;padding:0;font-size:0.9rem;">
                    <?php foreach ($upcomingAssignments as $a): ?>
                        <li style="padding:6px 0;border-bottom:1px solid #e5e7eb;">
                            <div style="font-weight:500;"><?php echo sanitize($a['judul']); ?></div>
                            <div class="subtext">
                                Kelas: <?php echo sanitize($a['nama_kelas'] ?? '-'); ?><br>
                                Deadline: <span><?php echo sanitize($a['deadline']); ?></span>
                            </div>
                            <div style="margin-top:2px;">
                                <a href="<?php echo $baseUrl; ?>/assignments/view.php?id=<?php echo (int)$a['id']; ?>">
                                    Lihat detail & pengumpulan
                                </a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <div style="margin-top:8px;font-size:0.85rem;">
                <a href="<?php echo $baseUrl; ?>/assignments/list.php">Lihat semua tugas →</a>
            </div>
        </div>

        <div class="card">
            <h3 style="margin-top:0;margin-bottom:6px;">Notifikasi & informasi</h3>
            <p class="subtext" style="margin:0 0 6px 0;">
                Lihat pengumuman dari admin sekolah atau sistem.
            </p>
            <a href="<?php echo $baseUrl; ?>/notifications/list.php" class="btn btn-secondary">
                Buka Notifikasi
            </a>
        </div>
    </div>
</div>

<!-- AKSI CEPAT: TILE BESAR -->
<div class="card" style="margin-bottom:20px;">
    <h3 style="margin-top:0;">Aksi cepat</h3>
    <p class="subtext" style="margin:2px 0 10px 0;">
        Pilih salah satu kotak di bawah ini. Semua kotak adalah tombol yang bisa diklik.
    </p>

    <div class="quick-actions">
        <div class="quick-grid">
            <!-- TUGAS & MATERI -->
            <a href="<?php echo $baseUrl; ?>/assignments/create.php" class="quick-tile">
                <div class="quick-icon">📝</div>
                <div class="quick-title">Buat tugas baru</div>
                <div class="quick-desc">Tambahkan tugas untuk salah satu kelas yang Anda ampu.</div>
            </a>

            <a href="<?php echo $baseUrl; ?>/assignments/list.php" class="quick-tile">
                <div class="quick-icon">📂</div>
                <div class="quick-title">Kelola & nilai tugas</div>
                <div class="quick-desc">
                    Lihat semua tugas, buka detailnya untuk menilai pengumpulan siswa.
                </div>
            </a>

            <!-- SHORTCUT BARU: REKAP NILAI HARIAN -->
            <a href="<?php echo $baseUrl; ?>/grades/daily_recap.php" class="quick-tile">
                <div class="quick-icon">📈</div>
                <div class="quick-title">Rekap nilai harian</div>
                <div class="quick-desc">
                    Lihat rekap nilai berdasarkan tanggal pengumpulan tugas.
                </div>
            </a>

            <a href="<?php echo $baseUrl; ?>/materials/list.php" class="quick-tile">
                <div class="quick-icon">📚</div>
                <div class="quick-title">Kelola materi</div>
                <div class="quick-desc">Upload dan atur materi pembelajaran untuk siswa.</div>
            </a>

            <!-- KELAS & MAPEL -->
            <a href="<?php echo $baseUrl; ?>/classes/list.php" class="quick-tile">
                <div class="quick-icon">🏫</div>
                <div class="quick-title">Lihat daftar kelas</div>
                <div class="quick-desc">Lihat semua kelas yang ada di sekolah Anda.</div>
            </a>

            <a href="<?php echo $baseUrl; ?>/subjects/list.php" class="quick-tile">
                <div class="quick-icon">📘</div>
                <div class="quick-title">Daftar mata pelajaran</div>
                <div class="quick-desc">Cek dan kelola mata pelajaran yang tersedia.</div>
            </a>

            <!-- ABSENSI -->
            <a href="<?php echo $baseUrl; ?>/attendance/take.php" class="quick-tile">
                <div class="quick-icon">✅</div>
                <div class="quick-title">Input absensi</div>
                <div class="quick-desc">Pilih kelas lalu catat kehadiran siswa.</div>
            </a>

            <a href="<?php echo $baseUrl; ?>/attendance/report_class.php" class="quick-tile">
                <div class="quick-icon">📊</div>
                <div class="quick-title">Rekap absensi kelas</div>
                <div class="quick-desc">Lihat ringkasan kehadiran per kelas.</div>
            </a>

            <!-- AKUN -->
            <a href="<?php echo $baseUrl; ?>/account/change_password_user.php" class="quick-tile">
                <div class="quick-icon">⚙️</div>
                <div class="quick-title">Ganti email & password</div>
                <div class="quick-desc">
                    Ubah email login dan password Anda (username tidak dapat diganti).
                </div>
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
