<?php
// dashboard/murid.php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireLogin(); // wajib login

// Hanya murid/siswa yang boleh masuk dashboard ini
if (!in_array(getUserRole(), ['murid', 'siswa'], true)) {
    http_response_code(403);
    echo "Halaman ini khusus untuk akun murid.";
    exit;
}

$db       = getDB();
$baseUrl  = rtrim(BASE_URL, '/\\');
$userId   = (int)($_SESSION['user_id'] ?? 0);
$role     = getUserRole();      // 'murid' atau 'siswa'
$schoolId = getCurrentSchoolId();

//
// 1. DATA USER
//
$user = null;
$stmt = $db->prepare("
    SELECT id, nama, email, role, class_id, school_id
    FROM users
    WHERE id = ?
    LIMIT 1
");
if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res  = $stmt->get_result();
    $user = $res ? $res->fetch_assoc() : null;
    $stmt->close();
}
if (!$user) {
    http_response_code(500);
    echo "Data pengguna tidak ditemukan.";
    exit;
}

//
// 2. KELAS YANG DIA IKUTI (class_user + fallback users.class_id)
//
$classIds = [];

// a) dari tabel relasi class_user
if ($schoolId > 0) {
    $stmt = $db->prepare("
        SELECT DISTINCT cu.class_id
        FROM class_user cu
        JOIN classes c ON cu.class_id = c.id
        WHERE cu.user_id = ? AND c.school_id = ?
    ");
    if ($stmt) {
        $stmt->bind_param("ii", $userId, $schoolId);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) {
            $classIds[] = (int)$row['class_id'];
        }
        $stmt->close();
    }
}

// b) fallback: users.class_id (kalau ada dan belum masuk list)
if (!empty($user['class_id'])) {
    $cid = (int)$user['class_id'];
    if (!in_array($cid, $classIds, true)) {
        $classIds[] = $cid;
    }
}

// hapus duplikat
$classIds = array_values(array_unique($classIds));

//
// 3. INFO KELAS UTAMA (untuk ditampilkan di kartu profil)
//
$primaryClass = null;
if (!empty($classIds)) {
    $firstClassId = $classIds[0];
    $stmt = $db->prepare("
        SELECT id, nama_kelas, level, jurusan
        FROM classes
        WHERE id = ? " . ($schoolId > 0 ? "AND school_id = ?" : "") . "
        LIMIT 1
    ");
    if ($stmt) {
        if ($schoolId > 0) {
            $stmt->bind_param("ii", $firstClassId, $schoolId);
        } else {
            $stmt->bind_param("i", $firstClassId);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $primaryClass = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    }
}

//
// 4. STATISTIK RINGKAS (jumlah tugas aktif, total tugas, jumlah kelas)
//
$stats = [
    'class_count'   => count($classIds),
    'assign_active' => 0,
    'assign_total'  => 0,
];

if (!empty($classIds) && $schoolId > 0) {
    $inIds = implode(',', array_map('intval', $classIds));

    // total tugas
    $sqlTotal = "
        SELECT COUNT(DISTINCT a.id) AS total
        FROM assignments a
        JOIN classes c ON a.target_class_id = c.id
        WHERE c.school_id = {$schoolId}
          AND c.id IN ({$inIds})
    ";
    $resT = $db->query($sqlTotal);
    if ($resT && $rowT = $resT->fetch_assoc()) {
        $stats['assign_total'] = (int)$rowT['total'];
    }

    // tugas aktif (deadline belum lewat atau tidak di-set)
    $sqlActive = "
        SELECT COUNT(DISTINCT a.id) AS total
        FROM assignments a
        JOIN classes c ON a.target_class_id = c.id
        WHERE c.school_id = {$schoolId}
          AND c.id IN ({$inIds})
          AND (
                a.deadline IS NULL
                OR a.deadline = '0000-00-00 00:00:00'
                OR a.deadline >= NOW()
          )
    ";
    $resA = $db->query($sqlActive);
    if ($resA && $rowA = $resA->fetch_assoc()) {
        $stats['assign_active'] = (int)$rowA['total'];
    }
}

//
// 5. DAFTAR TUGAS MENDATANG (maks 5)
//
$upcomingAssignments = [];
if (!empty($classIds) && $schoolId > 0) {
    $inIds = implode(',', array_map('intval', $classIds));

    $sqlUpcoming = "
        SELECT a.id, a.judul, a.deadline,
               s.nama_mapel, c.nama_kelas, c.level, c.jurusan
        FROM assignments a
        JOIN classes c ON a.target_class_id = c.id
        LEFT JOIN subjects s ON a.subject_id = s.id
        WHERE c.school_id = {$schoolId}
          AND c.id IN ({$inIds})
          AND (
                a.deadline IS NULL
                OR a.deadline = '0000-00-00 00:00:00'
                OR a.deadline >= NOW()
          )
        ORDER BY a.deadline IS NULL, a.deadline ASC
        LIMIT 5
    ";
    $resU = $db->query($sqlUpcoming);
    if ($resU) {
        $upcomingAssignments = $resU->fetch_all(MYSQLI_ASSOC);
    }
}

$pageTitle = 'Dashboard Murid';
include __DIR__ . '/../inc/header.php';
?>

<style>
/* Sedikit styling khusus dashboard murid biar rapi */
.student-header {
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
    margin-bottom:18px;
}
.student-header h1 {
    margin:0 0 4px 0;
    font-size:1.4rem;
}
.student-header p {
    margin:0;
    font-size:0.95rem;
    color:#6b7280;
}
.student-header-actions {
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    justify-content:flex-end;
}
.student-grid-top {
    display:grid;
    grid-template-columns: minmax(0,2fr) minmax(0,3fr);
    gap:16px;
}
.student-grid-bottom {
    display:grid;
    grid-template-columns: minmax(0,3fr) minmax(0,2fr);
    gap:16px;
    margin-top:18px;
}
@media(max-width:900px){
    .student-grid-top,
    .student-grid-bottom {
        grid-template-columns:1fr;
    }
}
.info-list dt {
    font-weight:600;
    color:#4b5563;
    font-size:0.86rem;
}
.info-list dd {
    margin:0 0 6px 0;
    color:#111827;
    font-size:0.95rem;
}
.badge-small {
    display:inline-block;
    padding:2px 8px;
    border-radius:999px;
    border:1px solid #e5e7eb;
    background:#f9fafb;
    font-size:0.8rem;
    color:#4b5563;
    margin-right:4px;
    margin-top:4px;
}
.task-item {
    padding:8px 0;
    border-bottom:1px solid #e5e7eb;
}
.task-item:last-child {
    border-bottom:none;
}
.task-title {
    font-weight:600;
    color:#111827;
    font-size:0.95rem;
}
.task-meta {
    font-size:0.85rem;
    color:#6b7280;
}
.quick-section-title {
    font-weight:600;
    font-size:0.95rem;
}
.quick-button-row {
    margin-top:6px;
    display:flex;
    flex-wrap:wrap;
    gap:6px;
}
.btn-small {
    padding:6px 12px;
    font-size:0.85rem;
}
</style>

<div class="container">

    <!-- HEADER: sapaan + aksi utama -->
    <div class="student-header card">
        <div>
            <h1>Dashboard Murid</h1>
            <p>
                Selamat datang, <strong><?php echo sanitize($user['nama'] ?: $user['email']); ?></strong>.<br>
                Di sini kamu bisa melihat ringkasan kelas, tugas, dan masuk ke Space Belajar.
            </p>
        </div>
        <div class="student-header-actions">
            <a href="<?php echo $baseUrl; ?>/space_belajar/dashboard.php"
               class="btn btn-primary btn-small">
                Space Belajar
            </a>
            <a href="<?php echo $baseUrl; ?>/calendar/index.php"
               class="btn btn-secondary btn-small">
                Kalender Belajar
            </a>
            <a href="<?php echo $baseUrl; ?>/account/change_password_user.php"
               class="btn btn-secondary btn-small">
                Ganti password
            </a>
            <a href="<?php echo $baseUrl; ?>/auth/logout.php"
               class="btn btn-outline btn-small"
               onclick="return confirm('Yakin ingin keluar dari akun ini?');">
                Keluar
            </a>
        </div>
    </div>

    <!-- GRID ATAS: Info akun + Ringkasan tugas -->
    <div class="student-grid-top">

        <!-- Info akun -->
        <div class="card" style="border-radius:14px;">
            <h2 style="margin-top:0;margin-bottom:8px;font-size:1.1rem;">Info Akun</h2>
            <dl class="info-list">
                <dt>Tipe Akun</dt>
                <dd>Murid / Siswa</dd>

                <dt>Nama</dt>
                <dd><?php echo sanitize($user['nama'] ?: '-'); ?></dd>

                <dt>Email</dt>
                <dd><?php echo sanitize($user['email']); ?></dd>

                <dt>Kelas Utama</dt>
                <dd>
                    <?php
                    if ($primaryClass) {
                        $label = trim(($primaryClass['level'] ?? '') . ' ' . ($primaryClass['jurusan'] ?? ''));
                        if (!empty($primaryClass['nama_kelas']) &&
                            trim($primaryClass['nama_kelas']) !== trim($label)) {
                            $label .= ' (' . $primaryClass['nama_kelas'] . ')';
                        }
                        echo sanitize($label);
                    } else {
                        echo 'Belum terdaftar di kelas mana pun';
                    }
                    ?>
                </dd>

                <dt>Jumlah Kelas Diikuti</dt>
                <dd><?php echo (int)$stats['class_count']; ?> kelas</dd>
            </dl>
        </div>

        <!-- Ringkasan tugas -->
        <div class="card" style="border-radius:14px;">
            <h2 style="margin-top:0;margin-bottom:10px;font-size:1.1rem;">Ringkasan Tugas</h2>
            <div style="display:flex;flex-wrap:wrap;gap:12px;">
                <div style="flex:1 1 150px;padding:10px 12px;border-radius:12px;border:1px solid #e5e7eb;background:#f9fafb;">
                    <div style="font-size:0.8rem;color:#6b7280;">Tugas aktif</div>
                    <div style="font-size:1.4rem;font-weight:700;margin-top:2px;">
                        <?php echo (int)$stats['assign_active']; ?>
                    </div>
                    <div style="font-size:0.8rem;color:#6b7280;margin-top:2px;">
                        Deadline belum lewat / tidak diatur.
                    </div>
                </div>
                <div style="flex:1 1 150px;padding:10px 12px;border-radius:12px;border:1px solid #e5e7eb;background:#f9fafb;">
                    <div style="font-size:0.8rem;color:#6b7280;">Total tugas</div>
                    <div style="font-size:1.4rem;font-weight:700;margin-top:2px;">
                        <?php echo (int)$stats['assign_total']; ?>
                    </div>
                    <div style="font-size:0.8rem;color:#6b7280;margin-top:2px;">
                        Semua tugas yang ditugaskan ke kelas kamu.
                    </div>
                </div>
            </div>
            <div style="margin-top:10px;">
                <a href="<?php echo $baseUrl; ?>/assignments/list.php"
                   class="btn btn-primary btn-small">
                    Buka daftar tugas
                </a>
            </div>
        </div>

    </div>

    <!-- GRID BAWAH: Tugas mendatang + Aksi cepat -->
    <div class="student-grid-bottom">

        <!-- Tugas mendatang -->
        <div class="card" style="border-radius:14px;">
            <h2 style="margin-top:0;margin-bottom:8px;font-size:1.05rem;">Tugas Mendatang</h2>
            <?php if (empty($upcomingAssignments)): ?>
                <p style="margin:4px 0 0 0;font-size:0.9rem;color:#6b7280;">
                    Belum ada tugas yang tercatat untuk kelas Anda.
                </p>
            <?php else: ?>
                <ul style="list-style:none;margin:0;padding:0;">
                    <?php foreach ($upcomingAssignments as $a): ?>
                        <li class="task-item">
                            <div class="task-title">
                                <?php echo sanitize($a['judul']); ?>
                            </div>
                            <div class="task-meta">
                                <?php
                                    $kelasStr  = trim(($a['level'] ?? '') . ' ' . ($a['jurusan'] ?? ''));
                                    $kelasNama = $kelasStr ?: ($a['nama_kelas'] ?? '');
                                    $mapel     = $a['nama_mapel'] ?? '';
                                    echo sanitize($mapel . ($mapel && $kelasNama ? ' • ' : '') . $kelasNama);
                                ?>
                            </div>
                            <div class="task-meta" style="margin-top:2px;">
                                Deadline:
                                <?php
                                    $deadline = $a['deadline'] ?? null;
                                    echo ($deadline && $deadline !== '0000-00-00 00:00:00')
                                        ? sanitize($deadline)
                                        : 'Tidak ada / belum diatur';
                                ?>
                            </div>
                            <div style="margin-top:6px;">
                                <a href="<?php echo $baseUrl; ?>/assignments/view.php?id=<?php echo (int)$a['id']; ?>"
                                   class="btn btn-secondary btn-small">
                                    Lihat detail tugas
                                </a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div style="margin-top:10px;">
                    <a href="<?php echo $baseUrl; ?>/assignments/list.php"
                       class="btn btn-outline btn-small">
                        Lihat semua tugas
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Aksi cepat (semua jadi tombol jelas) -->
        <div class="card" style="border-radius:14px;">
            <h2 style="margin-top:0;margin-bottom:6px;font-size:1.05rem;">Aksi Cepat</h2>
            <p style="margin:0 0 10px 0;font-size:0.88rem;color:#6b7280;">
                Pilih menu di bawah ini. Setiap tombol membawa kamu langsung ke halaman terkait.
            </p>

            <div style="display:grid;grid-template-columns:1fr;gap:10px;font-size:0.9rem;">

                <div>
                    <div class="quick-section-title">Belajar & Catatan Pribadi</div>
                    <div class="quick-button-row">
                        <a href="<?php echo $baseUrl; ?>/space_belajar/dashboard.php"
                           class="btn btn-primary btn-small">
                            Buka Space Belajar
                        </a>
                        <a href="<?php echo $baseUrl; ?>/study/calendar.php"
                           class="btn btn-secondary btn-small">
                            Kalender Belajar
                        </a>
                    </div>
                </div>

                <div>
                    <div class="quick-section-title">Tugas & Materi</div>
                    <div class="quick-button-row">
                        <a href="<?php echo $baseUrl; ?>/assignments/list.php"
                           class="btn btn-secondary btn-small">
                            Daftar tugas
                        </a>
                        <a href="<?php echo $baseUrl; ?>/materials/list.php"
                           class="btn btn-secondary btn-small">
                            Materi pelajaran
                        </a>
                    </div>
                </div>

                <div>
                    <div class="quick-section-title">Kelas & Mapel</div>
                    <div class="quick-button-row">
                        <a href="<?php echo $baseUrl; ?>/classes/list.php"
                           class="btn btn-secondary btn-small">
                            Daftar kelas
                        </a>
                        <a href="<?php echo $baseUrl; ?>/subjects/list.php"
                           class="btn btn-secondary btn-small">
                            Daftar mata pelajaran
                        </a>
                    </div>
                </div>

                <div>
                    <div class="quick-section-title">Absensi</div>
                    <div class="quick-button-row">
                        <a href="<?php echo $baseUrl; ?>/attendance/my_history.php"
                           class="btn btn-secondary btn-small">
                            Riwayat absensi saya
                        </a>
                    </div>
                </div>

                <div>
                    <div class="quick-section-title">Akun</div>
                    <div class="quick-button-row">
                        <a href="<?php echo $baseUrl; ?>/account/change_password_user.php"
                           class="btn btn-secondary btn-small">
                            Ganti password / pengaturan
                        </a>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
