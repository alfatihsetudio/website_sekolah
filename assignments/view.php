<?php
// assignments/view.php
// Detail tugas + lampiran + daftar submissions
// Menggunakan serve_file.php untuk file, dan pengecekan akses kelas

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireLogin();

$db     = getDB();
$userId = (int)($_SESSION['user_id'] ?? 0);
$role   = $_SESSION['role'] ?? 'murid';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . rtrim(BASE_URL, '/\\') . '/assignments/list.php');
    exit;
}

// ------------------------------------------------------------------
// Ambil detail tugas (subject + class + guru pembuat)
// ------------------------------------------------------------------
$stmt = $db->prepare("
    SELECT 
        a.*,
        s.nama_mapel,
        c.nama_kelas,
        c.level,
        c.jurusan,
        u.nama  AS guru_nama,
        u.email AS guru_email,
        s.class_id
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.id
    JOIN classes  c ON a.target_class_id = c.id
    LEFT JOIN users u ON a.created_by = u.id
    WHERE a.id = ?
    LIMIT 1
");
if (!$stmt) {
    echo 'Gagal menyiapkan query: ' . sanitize($db->error);
    exit;
}
$stmt->bind_param("i", $id);
$stmt->execute();
$res        = $stmt->get_result();
$assignment = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$assignment) {
    echo "Tugas tidak ditemukan.";
    exit;
}

// ------------------------------------------------------------------
// Akses kontrol:
// - guru pembuat (atau admin) boleh lihat
// - murid hanya boleh lihat jika tergabung di kelas target
// ------------------------------------------------------------------
$allowed = false;
$classId = (int)($assignment['target_class_id'] ?? 0);

if ($role === 'admin') {
    $allowed = true;
} elseif ($role === 'guru' && !empty($assignment['created_by']) && (int)$assignment['created_by'] === $userId) {
    $allowed = true;
} elseif ($role === 'murid' && $classId > 0) {
    // cek class_user
    $cs = $db->prepare("SELECT 1 FROM class_user WHERE class_id = ? AND user_id = ? LIMIT 1");
    if ($cs) {
        $cs->bind_param("ii", $classId, $userId);
        $cs->execute();
        $cr = $cs->get_result();
        if ($cr && $cr->num_rows > 0) {
            $allowed = true;
        }
        $cs->close();
    }
}

if (!$allowed) {
    http_response_code(403);
    echo "Anda tidak memiliki hak untuk melihat tugas ini.";
    exit;
}

// ------------------------------------------------------------------
// Ambil lampiran tugas (assignment_files → file_uploads)
// ------------------------------------------------------------------
$files = [];
$fs = $db->prepare("
    SELECT 
        af.id          AS af_id,
        af.file_type,
        fu.id          AS file_id,
        fu.original_name,
        fu.stored_name,
        fu.file_path,
        fu.mime_type
    FROM assignment_files af
    JOIN file_uploads fu ON af.file_id = fu.id
    WHERE af.assignment_id = ?
");
if ($fs) {
    $fs->bind_param("i", $id);
    $fs->execute();
    $rfs = $fs->get_result();
    while ($row = $rfs->fetch_assoc()) {
        $files[] = $row;
    }
    $fs->close();
}

// ------------------------------------------------------------------
// Ambil semua submissions untuk tugas ini
// ------------------------------------------------------------------
$submissions = [];
$ss = $db->prepare("
    SELECT 
        sub.*,
        u.nama  AS student_nama,
        u.email AS student_email
    FROM submissions sub
    LEFT JOIN users u ON sub.student_id = u.id
    WHERE sub.assignment_id = ?
    ORDER BY sub.submitted_at DESC
");
if ($ss) {
    $ss->bind_param("i", $id);
    $ss->execute();
    $rs = $ss->get_result();
    while ($r = $rs->fetch_assoc()) {
        $submissions[] = $r;
    }
    $ss->close();
}

// ------------------------------------------------------------------
// Helper untuk buat URL serve_file.php dari data file_uploads
// ------------------------------------------------------------------
function buildServeUrlFromFileRow(array $row, $inline = false) {
    // Prioritas: file_path berisi path relatif dari folder uploads (mis: "assignments/xxx.png")
    $rel = trim($row['file_path'] ?? '', " \t\n\r\0\x0B/\\");
    if ($rel === '' && !empty($row['stored_name'])) {
        // fallback: tebak folder dari tipe berkas (sangat tergantung implementasi uploadFile)
        $rel = 'assignments/' . $row['stored_name'];
    }
    if ($rel === '') return null;

    $mode = $inline ? 'inline' : 'attachment';
    return rtrim(BASE_URL, '/\\') . '/serve_file.php?f=' . rawurlencode($rel) . '&mode=' . $mode;
}

// ------------------------------------------------------------------
// Siapkan data tampilan
// ------------------------------------------------------------------
$pageTitle  = $assignment['judul'] ?? 'Detail Tugas';
$judul      = sanitize($assignment['judul'] ?? '-');
$mapel      = sanitize($assignment['nama_mapel'] ?? '-');
$kelasLabel = trim(($assignment['level'] ?? '') . ' ' . ($assignment['jurusan'] ?? ''));
$kelasLabel = $kelasLabel !== '' ? $kelasLabel : ($assignment['nama_kelas'] ?? '-');
$kelasLabel = sanitize($kelasLabel);
$deskripsi  = $assignment['deskripsi'] ?? '';
$deadline   = $assignment['deadline'] ?? '';
$createdAt  = $assignment['created_at'] ?? '';
$videoLink  = $assignment['video_link'] ?? '';
$guruNama   = $assignment['guru_nama'] ?: ($assignment['guru_email'] ?? '-');

include __DIR__ . '/../inc/header.php';
?>

<div class="container">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;">
        <div>
            <h1 style="margin-bottom:4px;"><?php echo $judul; ?></h1>
            <div style="font-size:14px;color:#666;">
                <?php echo $mapel; ?> — <?php echo $kelasLabel; ?><br>
                Dibuat oleh: <?php echo sanitize($guruNama); ?><br>
                <span style="font-size:12px;">
                    Dibuat: <?php echo $createdAt ? sanitize(formatTanggal($createdAt, true)) : '-'; ?>
                    <?php if ($deadline): ?>
                        &middot; Deadline:
                        <?php
                            $status = getDeadlineStatus($deadline);
                            echo '<span class="badge badge-' . sanitize($status['class']) . '">'
                                 . sanitize($status['label']) . '</span>';
                        ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;">
            <a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/assignments/list.php" class="btn btn-secondary">← Kembali</a>

            <?php if ($role === 'murid'): ?>
                <!-- Tombol Kumpulkan Tugas (hanya murid) -->
                <a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/assignments/submit.php?assignment_id=<?php echo (int)$id; ?>" 
                   class="btn btn-primary">
                    📤 Kumpulkan Tugas
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card" style="padding:18px; margin-bottom:18px;">
        <h3 style="margin-top:0;">Instruksi / Deskripsi</h3>
        <?php if (trim($deskripsi) === ''): ?>
            <p><em>Tidak ada deskripsi.</em></p>
        <?php else: ?>
            <p style="white-space:pre-wrap;"><?php echo nl2br(sanitize($deskripsi)); ?></p>
        <?php endif; ?>

        <?php if ($deadline): ?>
            <p style="margin-top:12px;">
                <strong>Deadline:</strong>
                <?php echo sanitize(formatTanggal($deadline, true)); ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($videoLink)): ?>
            <div style="margin-top:16px;">
                <strong>Video:</strong>
                <div style="margin-top:8px;">
                    <?php
                    $videoLinkSafe = sanitize($videoLink);
                    if (strpos($videoLink, 'youtube.com') !== false || strpos($videoLink, 'youtu.be') !== false) {
                        $vId = null;
                        if (preg_match('/v=([a-zA-Z0-9_\-]+)/', $videoLink, $m)) {
                            $vId = $m[1];
                        } elseif (preg_match('/youtu\.be\/([a-zA-Z0-9_\-]+)/', $videoLink, $m)) {
                            $vId = $m[1];
                        }
                        if ($vId) {
                            $embed = 'https://www.youtube.com/embed/' . htmlspecialchars($vId);
                            echo '<div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;">'
                               . '<iframe src="'.$embed.'" '
                               . 'style="position:absolute;top:0;left:0;width:100%;height:100%;" '
                               . 'frameborder="0" allowfullscreen></iframe></div>';
                        } else {
                            echo '<a href="'.$videoLinkSafe.'" target="_blank">'.$videoLinkSafe.'</a>';
                        }
                    } else {
                        echo '<a href="'.$videoLinkSafe.'" target="_blank">'.$videoLinkSafe.'</a>';
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($files)): ?>
        <div class="card" style="padding:18px; margin-bottom:18px;">
            <h3 style="margin-top:0;">Lampiran</h3>
            <ul style="list-style:none;padding-left:0;margin:0;">
                <?php foreach ($files as $f): ?>
                    <?php
                        $fileName  = $f['original_name'] ?: $f['stored_name'] ?: ('file_'.$f['file_id']);
                        $mime      = strtolower($f['mime_type'] ?? '');
                        $ext       = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        $isImage   = (strpos($mime, 'image/') === 0) || in_array($ext, ['jpg','jpeg','png','gif','webp','svg'], true);
                        $serveUrl  = buildServeUrlFromFileRow($f, $isImage);
                        $downloadUrl = $serveUrl ? preg_replace('/(&|\?)mode=inline$/', '$1mode=attachment', $serveUrl) : null;
                    ?>
                    <li style="margin-bottom:10px;">
                        <?php if ($serveUrl): ?>
                            <a href="<?php echo sanitize($serveUrl); ?>" target="_blank" style="font-weight:600;">
                                <?php echo sanitize($fileName); ?>
                            </a>
                        <?php else: ?>
                            <?php echo sanitize($fileName); ?> (lokasi file tidak diketahui)
                        <?php endif; ?>
                        <span style="font-size:12px;color:#666;">
                            (<?php echo sanitize($f['file_type'] ?? 'attachment'); ?>)
                        </span>

                        <?php if ($downloadUrl): ?>
                            &middot;
                            <a href="<?php echo sanitize($downloadUrl); ?>" style="font-size:12px;">Unduh</a>
                        <?php endif; ?>

                        <?php if ($isImage && $serveUrl): ?>
                            <div style="margin-top:6px;">
                                <img
                                    src="<?php echo sanitize($serveUrl); ?>"
                                    alt="<?php echo sanitize($fileName); ?>"
                                    style="max-width:260px;height:auto;border:1px solid #eee;padding:4px;border-radius:6px;cursor:pointer;"
                                    onclick="window.open('<?php echo sanitize($serveUrl); ?>','_blank','noopener')"
                                >
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card" style="padding:18px;">
        <h3 style="margin-top:0;">Pengumpulan Tugas</h3>
        <?php if (empty($submissions)): ?>
            <p><em>Belum ada pengumpulan.</em></p>
        <?php else: ?>
            <table class="table" style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="text-align:left;padding:8px;border-bottom:1px solid #ddd;">#</th>
                        <th style="text-align:left;padding:8px;border-bottom:1px solid #ddd;">Nama Siswa</th>
                        <th style="text-align:left;padding:8px;border-bottom:1px solid #ddd;">Waktu</th>
                        <th style="text-align:left;padding:8px;border-bottom:1px solid #ddd;">Nilai</th>
                        <th style="text-align:left;padding:8px;border-bottom:1px solid #ddd;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($submissions as $sub): ?>
                        <tr>
                            <td style="padding:8px;border-bottom:1px solid #f1f1f1;"><?php echo $i++; ?></td>
                            <td style="padding:8px;border-bottom:1px solid #f1f1f1;">
                                <?php echo sanitize($sub['student_nama'] ?? ('ID ' . ($sub['student_id'] ?? ''))); ?>
                            </td>
                            <td style="padding:8px;border-bottom:1px solid #f1f1f1;">
                                <?php echo sanitize(formatTanggal($sub['submitted_at'], true)); ?>
                            </td>
                            <td style="padding:8px;border-bottom:1px solid #f1f1f1;">
                                <?php echo is_null($sub['nilai']) ? '-' : sanitize($sub['nilai']); ?>
                            </td>
                            <td style="padding:8px;border-bottom:1px solid #f1f1f1;">
                                <a href="<?php echo rtrim(BASE_URL, '/\\') . '/assignments/submission_view.php?id=' . (int)$sub['id']; ?>">
                                    Lihat
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div style="margin-top:14px;">
        <a href="<?php echo rtrim(BASE_URL, '/\\'); ?>/assignments/list.php" class="btn btn-secondary">← Kembali ke daftar tugas</a>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
