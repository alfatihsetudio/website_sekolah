<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireLogin();

$db       = getDB();
$baseUrl  = rtrim(BASE_URL, '/\\');
$userId   = (int)($_SESSION['user_id'] ?? 0);

$pageTitle = 'Notifikasi';
include __DIR__ . '/../inc/header.php';

/*
   Ambil notifikasi + info tugas (kalau ada):

   - notifications.assignment_id -> id tugas (boleh null)
   - assignments.judul           -> judul tugas
   - classes.nama_kelas / level / jurusan
*/

$sql = "
    SELECT
        n.id,
        n.title,
        n.message,
        n.is_read,
        n.created_at,
        n.assignment_id,

        a.judul        AS assignment_title,
        c.nama_kelas,
        c.level,
        c.jurusan
    FROM notifications n
    LEFT JOIN assignments a ON a.id = n.assignment_id
    LEFT JOIN classes     c ON a.target_class_id = c.id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
";

$stmt = $db->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$notifs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="container">
    <div class="page-header">
        <h1>Notifikasi Saya</h1>
        <p style="margin:0;font-size:0.9rem;color:#6b7280;">
            Semua pemberitahuan terkait tugas dan aktivitas akun Anda.
        </p>
    </div>

    <?php if (empty($notifs)): ?>
        <div class="card">
            <p>Tidak ada notifikasi.</p>
        </div>
    <?php else: ?>
        <div class="card" style="padding:0;border:none;background:transparent;">
            <?php foreach ($notifs as $n): ?>

                <?php
                $isRead      = (int)$n['is_read'] === 1;
                $bgCard      = $isRead ? '#ffffff' : '#f5f9ff';
                $borderColor = $isRead ? '#e5e7eb' : '#bfdbfe';
                $badgeText   = $isRead ? 'Sudah dibaca' : 'Belum dibaca';
                $badgeColor  = $isRead ? '#16a34a' : '#b91c1c';

                // label kelas
                $classLabel = '';
                if (!empty($n['level']) || !empty($n['jurusan']) || !empty($n['nama_kelas'])) {
                    $lj = trim(($n['level'] ?? '') . ' ' . ($n['jurusan'] ?? ''));
                    $classLabel = $lj !== '' ? $lj : ($n['nama_kelas'] ?? '');
                }

                $canOpenAssignment = !empty($n['assignment_id']);
                $assignmentUrl = $canOpenAssignment
                    ? $baseUrl . '/assignments/submission_view.php?assignment_id=' . (int)$n['assignment_id']
                    : '#';
                ?>

                <div
                    style="
                        padding:14px 16px;
                        border-radius:14px;
                        border:1px solid <?php echo $borderColor; ?>;
                        background:<?php echo $bgCard; ?>;
                        margin-bottom:10px;
                        display:flex;
                        align-items:flex-start;
                        justify-content:space-between;
                        gap:12px;
                    "
                >
                    <!-- KIRI: isi notif (judul + pesan + info tugas) -->
                    <div style="flex:1 1 auto; min-width:0;">
                        <?php if ($canOpenAssignment): ?>
                            <a href="<?php echo $assignmentUrl; ?>"
                               style="text-decoration:none;color:inherit;display:block;">
                        <?php endif; ?>

                            <div style="font-weight:600;margin-bottom:4px;">
                                <?php echo sanitize($n['title']); ?>
                            </div>

                            <div style="font-size:0.9rem;color:#4b5563;margin-bottom:4px;">
                                <?php echo nl2br(sanitize($n['message'])); ?>
                            </div>

                            <?php if ($n['assignment_title']): ?>
                                <div style="font-size:0.85rem;color:#4b5563;margin-top:2px;">
                                    Tugas: <strong><?php echo sanitize($n['assignment_title']); ?></strong>
                                    <?php if ($classLabel): ?>
                                        &middot; Kelas: <strong><?php echo sanitize($classLabel); ?></strong>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div style="font-size:0.8rem;color:#9ca3af;margin-top:4px;">
                                Waktu notifikasi: <?php echo sanitize($n['created_at']); ?>
                            </div>

                        <?php if ($canOpenAssignment): ?>
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- KANAN: status & tombol -->
                    <div style="flex:0 0 auto;text-align:right;min-width:140px;">
                        <div style="font-size:0.8rem;font-weight:600;color:<?php echo $badgeColor; ?>;margin-bottom:6px;">
                            <?php echo $badgeText; ?>
                        </div>

                        <?php if ($canOpenAssignment): ?>
                            <a href="<?php echo $assignmentUrl; ?>"
                               class="btn btn-primary"
                               style="display:inline-block;margin-bottom:6px;padding:6px 10px;font-size:0.85rem;">
                                Lihat / nilai tugas
                            </a>
                        <?php endif; ?>

                        <?php if (!$isRead): ?>
                            <a href="<?php echo $baseUrl; ?>/notifications/mark_read.php?id=<?php echo (int)$n['id']; ?>"
                               style="display:inline-block;font-size:0.8rem;margin-top:4px;">
                                Tandai sudah dibaca
                            </a>
                        <?php else: ?>
                            <span style="display:inline-block;font-size:0.8rem;color:#6b7280;margin-top:4px;">
                                ✔ Sudah dibaca
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
