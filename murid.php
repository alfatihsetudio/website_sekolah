<?php
require_once __DIR__ . '/inc/auth.php';
requireRole(['siswa']);

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/helpers.php';

$db = getDB();
$userId = (int)($_SESSION['user_id'] ?? 0);
$pageTitle = 'Dashboard Murid';
include __DIR__ . '/inc/header.php';

// 1) Kelas yang diikuti
$stmt = $db->prepare("SELECT c.id, c.nama_kelas, u.name AS wali FROM class_user cu JOIN classes c ON cu.class_id = c.id LEFT JOIN users u ON c.guru_id = u.id WHERE cu.user_id = ? ORDER BY c.nama_kelas");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$kelasList = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

// 2) Mata pelajaran untuk kelas murid
$stmt = $db->prepare("
    SELECT s.id, s.nama_mapel, c.id AS class_id, c.nama_kelas, u.name AS guru
    FROM subjects s
    JOIN classes c ON s.class_id = c.id
    LEFT JOIN users u ON s.guru_id = u.id
    JOIN class_user cu ON cu.class_id = c.id
    WHERE cu.user_id = ?
    ORDER BY c.nama_kelas, s.nama_mapel
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$subjects = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

// 3) Materi terbaru untuk murid (limit 8)
$stmt = $db->prepare("
    SELECT m.id, m.judul, m.created_at, s.nama_mapel, c.nama_kelas
    FROM materials m
    JOIN subjects s ON m.subject_id = s.id
    JOIN classes c ON s.class_id = c.id
    JOIN class_user cu ON cu.class_id = c.id
    WHERE cu.user_id = ?
    ORDER BY m.created_at DESC
    LIMIT 8
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$materials = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

// 4) Tugas aktif (upcoming/not submitted) untuk murid (limit 8)
$stmt = $db->prepare("
    SELECT a.id, a.judul, a.deadline, s.nama_mapel, c.nama_kelas
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.id
    JOIN classes c ON a.target_class_id = c.id
    JOIN class_user cu ON cu.class_id = c.id
    WHERE cu.user_id = ?
    ORDER BY a.deadline IS NULL, a.deadline ASC
    LIMIT 8
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$assignments = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

// 5) Notifikasi terbaru
$stmt = $db->prepare("SELECT id, title, message, url, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 6");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$notifs = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

// 6) Nilai terbaru
$stmt = $db->prepare("
    SELECT g.id, g.score, g.feedback, a.judul, g.created_at
    FROM grades g
    JOIN submissions s ON g.submission_id = s.id
    JOIN assignments a ON s.assignment_id = a.id
    WHERE s.user_id = ?
    ORDER BY g.created_at DESC
    LIMIT 6
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$grades = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();
?>

<div class="container">
    <div class="page-header">
        <h1>Halo, <?php echo sanitize($_SESSION['name'] ?? ''); ?> — Dashboard Murid</h1>
    </div>

    <div class="grid" style="display:grid; grid-template-columns: 1fr 320px; gap:20px;">
        <div>
            <section class="card" style="margin-bottom:20px;">
                <h2>Materi Terbaru</h2>
                <?php if (empty($materials)): ?>
                    <p>Tidak ada materi terbaru.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($materials as $m): ?>
                            <li>
                                <a href="/web_MG/materials/view.php?id=<?php echo (int)$m['id']; ?>">
                                    <?php echo sanitize($m['judul']); ?>
                                </a>
                                <div style="font-size:12px;color:#666;">
                                    <?php echo sanitize($m['nama_mapel']); ?> — <?php echo sanitize($m['nama_kelas']); ?> · <?php echo sanitize($m['created_at']); ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div><a href="/web_MG/materials/list.php">Lihat semua materi</a></div>
                <?php endif; ?>
            </section>

            <section class="card" style="margin-bottom:20px;">
                <h2>Tugas / Tugas Aktif</h2>
                <?php if (empty($assignments)): ?>
                    <p>Tidak ada tugas aktif.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($assignments as $a): ?>
                            <li>
                                <a href="/web_MG/assignments/view.php?id=<?php echo (int)$a['id']; ?>">
                                    <?php echo sanitize($a['judul']); ?>
                                </a>
                                <div style="font-size:12px;color:#666;">
                                    <?php echo sanitize($a['nama_mapel']); ?> — <?php echo sanitize($a['nama_kelas']); ?>
                                    &middot; Deadline: <?php echo $a['deadline'] ? sanitize($a['deadline']) : '-'; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div><a href="/web_MG/assignments/list.php">Lihat semua tugas</a></div>
                <?php endif; ?>
            </section>

            <section class="card">
                <h2>Mata Pelajaran Anda</h2>
                <?php if (empty($subjects)): ?>
                    <p>Tidak ada mata pelajaran.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($subjects as $s): ?>
                            <li>
                                <?php echo sanitize($s['nama_mapel']); ?> — <?php echo sanitize($s['nama_kelas']); ?>
                                <div style="font-size:12px;color:#666;">Guru: <?php echo sanitize($s['guru'] ?? '-'); ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div><a href="/web_MG/subjects/list.php">Lihat semua mata pelajaran</a></div>
                <?php endif; ?>
            </section>
        </div>

        <aside>
            <section class="card" style="margin-bottom:20px;">
                <h3>Notifikasi</h3>
                <?php if (empty($notifs)): ?>
                    <p>Tidak ada notifikasi.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($notifs as $n): ?>
                            <li style="<?php echo $n['is_read'] ? '' : 'font-weight:600;'; ?>">
                                <?php if (!empty($n['url'])): ?>
                                    <a href="<?php echo sanitize($n['url']); ?>"><?php echo sanitize($n['title']); ?></a>
                                <?php else: ?>
                                    <?php echo sanitize($n['title']); ?>
                                <?php endif; ?>
                                <div style="font-size:11px;color:#666;"><?php echo sanitize($n['created_at']); ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div><a href="/web_MG/notifications/list.php">Lihat semua notifikasi</a></div>
                <?php endif; ?>
            </section>

            <section class="card">
                <h3>Nilai Terbaru</h3>
                <?php if (empty($grades)): ?>
                    <p>Belum ada nilai.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($grades as $g): ?>
                            <li>
                                <a href="/web_MG/grades/view.php?id=<?php echo (int)$g['id']; ?>"><?php echo sanitize($g['judul'] ?? 'Nilai'); ?></a>
                                <div style="font-size:12px;color:#666;">Nilai: <?php echo sanitize($g['score']); ?> · <?php echo sanitize($g['created_at']); ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div><a href="/web_MG/grades/list.php">Lihat semua nilai</a></div>
                <?php endif; ?>
            </section>
        </aside>
    </div>
</div>

<?php include __DIR__ . '/inc/footer.php'; ?>
