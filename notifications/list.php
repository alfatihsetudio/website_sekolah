<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireLogin();

$db       = getDB();
$userId   = (int)($_SESSION['user_id'] ?? 0);
$role     = getUserRole() ?: 'murid';
$schoolId = getCurrentSchoolId();
$baseUrl  = rtrim(BASE_URL, '/\\');

if ($schoolId <= 0) {
    $notifications = [];
} else {

    // =============================
    // NOTIFIKASI FILTER SCHOOL_ID
    // =============================
    $stmt = $db->prepare("
        SELECT
            n.id,
            n.title,
            n.message,
            n.link,
            n.is_read,
            n.created_at,
            n.sender_id,
            n.assignment_id,
            u.nama AS sender_name,
            u.role AS sender_role,
            a.judul AS assignment_title
        FROM notifications n
        LEFT JOIN users u       ON n.sender_id     = u.id        AND u.school_id = ?
        LEFT JOIN assignments a ON n.assignment_id = a.id
        LEFT JOIN classes c     ON a.target_class_id = c.id
        WHERE n.user_id = ?
          AND n.school_id = ?
        ORDER BY n.created_at DESC
    ");

    $stmt->bind_param("iii", $schoolId, $userId, $schoolId);
    $stmt->execute();
    $res = $stmt->get_result();
    $notifications = [];

    while ($row = $res->fetch_assoc()) {

        /* =============================
           DETAIL SUBMISSION (dengan filter sekolah)
        ============================= */
        $row['submission'] = null;

        // Deteksi submission id
        $submissionId = null;
        if (!empty($row['link']) && strpos($row['link'], 'submission_view.php') !== false) {
            if (preg_match('/[?&]id=(\d+)/', $row['link'], $m)) {
                $submissionId = (int)$m[1];
            }
        }

        if ($submissionId) {
            $stmtSub = $db->prepare("
                SELECT
                    s.id,
                    s.assignment_id,
                    s.student_id,
                    s.submitted_at,
                    s.nilai,
                    u.nama AS student_name,
                    u.email AS student_email,
                    a.judul AS assignment_title,
                    c.nama_kelas,
                    c.level,
                    c.jurusan
                FROM submissions s
                JOIN users u       ON s.student_id       = u.id        AND u.school_id = ?
                JOIN assignments a ON s.assignment_id    = a.id
                JOIN classes c     ON a.target_class_id  = c.id        AND c.school_id = ?
                WHERE s.id = ?
                LIMIT 1
            ");
            $stmtSub->bind_param("iii", $schoolId, $schoolId, $submissionId);
            $stmtSub->execute();
            $rSub = $stmtSub->get_result();
            if ($rSub && $rSub->num_rows) {
                $row['submission'] = $rSub->fetch_assoc();
            }
            $stmtSub->close();
        }

        $notifications[] = $row;
    }

    $stmt->close();
}

$pageTitle = 'Notifikasi';
include __DIR__ . '/../inc/header.php';
?>

<style>
/* (CSS TIDAK DIUBAH) */
.notif-list { display:flex; flex-direction:column; gap:12px; }
.notif-card {
    background:#ffffff;
    border-radius:14px;
    padding:14px 16px;
    border:1px solid #e5e7eb;
    box-shadow:0 1px 3px rgba(150,56,41,0.04);
    display:flex; justify-content:space-between; gap:12px;
}
.notif-card.unread { border-left:4px solid #2563eb; background:#f8fbff; }
.notif-main { flex:1; }
.notif-title { margin:0 0 4px; font-size:0.98rem; }
.notif-title a { color:#111827; text-decoration:none; }
.notif-title a:hover { text-decoration:underline; }
.notif-meta { font-size:0.85rem; color:#6b7280; margin:2px 0 0; }
.notif-extra { font-size:0.85rem; color:#4b5563; margin-top:4px; }
.notif-actions {
    min-width:140px; display:flex; flex-direction:column;
    gap:6px; font-size:0.82rem; align-items:flex-end;
}
.badge-status { padding:3px 8px; border-radius:999px; font-size:0.78rem; }
.badge-status.unread { background:#fee2e2; color:#b91c1c; }
.badge-status.read { background:#dcfce7; color:#16a34a; }
.btn-disabled { opacity:0.5; pointer-events:none; }
@media (max-width: 640px) {
    .notif-card { flex-direction:column; align-items:flex-start; }
    .notif-actions { flex-direction:row; flex-wrap:wrap; }
}
</style>

<div class="container">

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
        <h2 style="margin:0;">Notifikasi Saya</h2>

        <a href="javascript:history.back()" 
           style="padding:6px 12px;font-size:0.85rem;background:#f3f4f6;
                  color:#374151;border:1px solid #d1d5db;border-radius:6px;">
           ← Kembali
        </a>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="card"><p>Tidak ada notifikasi.</p></div>

    <?php else: ?>
        <div class="notif-list">

        <?php foreach ($notifications as $n): ?>
            <?php
                $isRead = (int)$n['is_read'] === 1;
                $statusClass = $isRead ? 'read' : 'unread';
                $statusText  = $isRead ? 'Sudah dibaca' : 'Baru';
                $targetUrl   = !empty($n['link']) ? $n['link'] : '#';
            ?>

            <div class="notif-card <?php echo $isRead ? '' : 'unread'; ?>">
                <div class="notif-main">

                    <h2 class="notif-title">
                        <a href="<?php echo sanitize($targetUrl); ?>">
                            <?php echo sanitize($n['title']); ?>
                        </a>
                    </h2>

                    <p style="margin:0 0 4px;font-size:0.9rem;">
                        <?php echo nl2br(sanitize($n['message'])); ?>
                    </p>

                    <p class="notif-meta">
                        <?php if (!empty($n['sender_name'])): ?>
                            Dari: <strong><?php echo sanitize($n['sender_name']); ?></strong>
                            (<?php echo strtoupper(sanitize($n['sender_role'])); ?>)
                            &middot;
                        <?php endif; ?>
                        Waktu: <?php echo sanitize($n['created_at']); ?>
                    </p>

                    <?php if (!empty($n['submission'])): ?>
                        <div class="notif-extra">
                            <div>Siswa: <strong><?php echo sanitize($n['submission']['student_name']); ?></strong></div>
                            <div>Tugas: <strong><?php echo sanitize($n['submission']['assignment_title']); ?></strong></div>
                            <div>
                                Kelas:
                                <?php
                                    echo sanitize(
                                        trim(($n['submission']['level'] ?? '') . ' ' . ($n['submission']['jurusan'] ?? ''))
                                    );
                                ?>
                            </div>
                            <div>Dikumpulkan: <?php echo sanitize($n['submission']['submitted_at']); ?></div>
                        </div>
                    <?php elseif (!empty($n['assignment_title'])): ?>
                        <div class="notif-extra">
                            Tugas: <strong><?php echo sanitize($n['assignment_title']); ?></strong>
                        </div>
                    <?php endif; ?>

                </div>

                <div class="notif-actions">
                    <div class="badge-status <?php echo $statusClass; ?>">
                        <?php echo $statusText; ?>
                    </div>

                    <a href="<?php echo $isRead ? 'javascript:void(0);' : $baseUrl . '/notifications/mark_read.php?id=' . (int)$n['id']; ?>"
                       class="btn btn-secondary btn-link-small <?php echo $isRead ? 'btn-disabled' : ''; ?>">
                       Tandai sudah dibaca
                    </a>

                    <?php if ($targetUrl !== '#'): ?>
                        <a href="<?php echo sanitize($targetUrl); ?>" class="btn btn-primary btn-link-small">
                            Lihat detail
                        </a>
                    <?php else: ?>
                        <a href="javascript:void(0);" class="btn btn-primary btn-link-small btn-disabled">
                            Lihat detail
                        </a>
                    <?php endif; ?>

                    <a href="<?php echo $baseUrl; ?>/notifications/delete.php?id=<?php echo (int)$n['id']; ?>"
                       class="btn btn-secondary btn-link-small"
                       onclick="return confirm('Hapus notifikasi ini?');">
                       Hapus notif
                    </a>
                </div>

            </div>

        <?php endforeach; ?>

        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
