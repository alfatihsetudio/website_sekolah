<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireLogin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . BASE_URL . '/subjects/list.php');
    exit;
}

$db = getDB();

/*
 * Ambil detail mapel + kelas + guru
 * (pakai u.nama, karena di DB kolomnya biasanya `nama`, bukan `name`)
 */
$stmt = $db->prepare("
    SELECT
        s.*,
        c.nama_kelas,
        u.nama AS guru
    FROM subjects s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN users   u ON s.guru_id  = u.id
    WHERE s.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$subject = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$subject) {
    // kalau mapel tidak ditemukan, balik ke list
    header('Location: ' . BASE_URL . '/subjects/list.php');
    exit;
}

$pageTitle = 'Detail Mata Pelajaran';
include __DIR__ . '/../inc/header.php';

/* =======================
   Ambil materi mapel ini
   ======================= */
$stmt = $db->prepare("
    SELECT id, judul, created_at
    FROM materials
    WHERE subject_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $id);
$stmt->execute();
$mat = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* =======================
   Ambil tugas mapel ini
   ======================= */
$stmt = $db->prepare("
    SELECT a.id, a.judul, a.deadline, a.created_at
    FROM assignments a
    WHERE a.subject_id = ?
    ORDER BY a.created_at DESC
");
$stmt->bind_param("i", $id);
$stmt->execute();
$ass = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* =======================
   Ringkasan materi & tugas
   ======================= */
$totalMateri = count($mat);
$totalTugas  = count($ass);

$materiTerakhir   = $totalMateri > 0 ? ($mat[0]['created_at'] ?? null) : null;
$tugasTerbaru     = $totalTugas  > 0 ? ($ass[0]['created_at'] ?? null) : null;

// cari deadline terdekat yang masih >= hari ini
$today           = date('Y-m-d H:i:s');
$deadlineTerdekat = null;
foreach ($ass as $a) {
    $dl = $a['deadline'] ?? null;
    if (!$dl || $dl === '0000-00-00 00:00:00') {
        continue;
    }
    if ($dl >= $today) {
        if ($deadlineTerdekat === null || $dl < $deadlineTerdekat) {
            $deadlineTerdekat = $dl;
        }
    }
}
?>
<div class="container">

    <!-- HEADER MAPEL -->
    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
        <div>
            <h1 style="margin:0 0 4px 0;"><?php echo sanitize($subject['nama_mapel'] ?? '-'); ?></h1>
            <div style="font-size:0.9rem;color:#6b7280;">
                Kelas:
                <strong><?php echo sanitize($subject['nama_kelas'] ?? '-'); ?></strong>
                &middot;
                Guru:
                <strong><?php echo sanitize($subject['guru'] ?? '-'); ?></strong>
            </div>
        </div>

        <a href="<?php echo BASE_URL; ?>/subjects/list.php" class="btn btn-secondary">
            ← Kembali ke daftar mapel
        </a>
    </div>

    <?php if (!empty($subject['deskripsi'])): ?>
        <div class="card" style="margin-bottom:16px;">
            <h3 style="margin-top:0;">Deskripsi</h3>
            <p style="margin:4px 0;"><?php echo nl2br(sanitize($subject['deskripsi'])); ?></p>
        </div>
    <?php endif; ?>

    <!-- RINGKASAN AKTIVITAS GURU UNTUK MAPEL INI -->
    <div class="card" style="margin-bottom:16px;">
        <h3 style="margin-top:0;margin-bottom:6px;">Ringkasan materi & tugas mapel ini</h3>
        <p style="margin:0 0 8px 0;font-size:0.88rem;color:#6b7280;">
            Rekap cepat semua materi dan tugas yang pernah diberikan pada mata pelajaran ini.
        </p>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;font-size:0.9rem;">
            <div>
                <div style="font-weight:600;">Materi</div>
                <ul style="margin:4px 0 0 18px;padding:0;">
                    <li>Total materi: <strong><?php echo (int)$totalMateri; ?></strong></li>
                    <li>
                        Materi terakhir:
                        <strong>
                            <?php echo $materiTerakhir ? sanitize($materiTerakhir) : '-'; ?>
                        </strong>
                    </li>
                </ul>
            </div>

            <div>
                <div style="font-weight:600;">Tugas</div>
                <ul style="margin:4px 0 0 18px;padding:0;">
                    <li>Total tugas: <strong><?php echo (int)$totalTugas; ?></strong></li>
                    <li>
                        Tugas terakhir dibuat:
                        <strong>
                            <?php echo $tugasTerbaru ? sanitize($tugasTerbaru) : '-'; ?>
                        </strong>
                    </li>
                    <li>
                        Deadline terdekat:
                        <strong>
                            <?php echo $deadlineTerdekat ? sanitize($deadlineTerdekat) : '-'; ?>
                        </strong>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- LIST MATERI -->
    <div class="card" style="margin-bottom:16px;">
        <h3 style="margin-top:0;">Materi</h3>
        <?php if (empty($mat)): ?>
            <p>Tidak ada materi.</p>
        <?php else: ?>
            <ul style="margin:6px 0 0 18px;padding:0;">
                <?php foreach ($mat as $m): ?>
                    <li style="margin-bottom:4px;">
                        <a href="<?php echo BASE_URL; ?>/materials/view.php?id=<?php echo (int)$m['id']; ?>">
                            <?php echo sanitize($m['judul']); ?>
                        </a>
                        <small style="color:#6b7280;">
                            &middot; <?php echo sanitize($m['created_at']); ?>
                        </small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- LIST TUGAS -->
    <div class="card">
        <h3 style="margin-top:0;">Tugas</h3>
        <?php if (empty($ass)): ?>
            <p>Tidak ada tugas.</p>
        <?php else: ?>
            <ul style="margin:6px 0 0 18px;padding:0;">
                <?php foreach ($ass as $a): ?>
                    <li style="margin-bottom:4px;">
                        <a href="<?php echo BASE_URL; ?>/assignments/view.php?id=<?php echo (int)$a['id']; ?>">
                            <?php echo sanitize($a['judul']); ?>
                        </a>
                        <small style="color:#6b7280;">
                            &middot; Deadline:
                            <?php
                            $dl = $a['deadline'] ?? '';
                            echo $dl && $dl !== '0000-00-00 00:00:00'
                                ? sanitize($dl)
                                : '-';
                            ?>
                        </small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
