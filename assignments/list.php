<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireLogin();

$db       = getDB();
$baseUrl  = rtrim(BASE_URL, '/\\');
$userId   = (int)($_SESSION['user_id'] ?? 0);
$role     = getUserRole() ?? 'murid';
$schoolId = getCurrentSchoolId();

$isMurid  = ($role === 'murid' || $role === 'siswa');

$assignments   = [];
$submissionMap = [];

// ----------------------
// Jika school_id bermasalah
// ----------------------
if ($schoolId <= 0) {
    $assignments = [];
} else {

    // ======================
    // GURU : tugas yang dia buat
    // ======================
    if ($role === 'guru') {
        $stmt = $db->prepare("
            SELECT a.id, a.judul, a.created_at, a.deadline,
                   s.nama_mapel, c.nama_kelas, c.level, c.jurusan
            FROM assignments a
            JOIN classes   c ON a.target_class_id = c.id
            LEFT JOIN subjects s ON a.subject_id   = s.id
            WHERE a.created_by = ?
              AND c.school_id  = ?
            ORDER BY a.created_at DESC
        ");
        $stmt->bind_param("ii", $userId, $schoolId);
        $stmt->execute();
        $res         = $stmt->get_result();
        $assignments = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

    // ======================
    // MURID / SISWA
    // ======================
    } elseif ($isMurid) {

        // 1) Ambil semua tugas untuk kelas yang dimiliki murid
        //    (baik dari class_user maupun dari users.class_id)
        $sql = "
            SELECT DISTINCT
                a.id, a.judul, a.created_at, a.deadline,
                s.nama_mapel, c.nama_kelas, c.level, c.jurusan
            FROM assignments a
            JOIN classes   c ON a.target_class_id = c.id
            LEFT JOIN subjects s ON a.subject_id = s.id
            WHERE c.school_id = ?
              AND (
                    c.id IN (
                        SELECT cu.class_id
                        FROM class_user cu
                        WHERE cu.user_id = ?
                    )
                    OR c.id = (
                        SELECT u.class_id
                        FROM users u
                        WHERE u.id = ?
                        LIMIT 1
                    )
              )
            ORDER BY a.created_at DESC
        ";

        $stmt = $db->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("iii", $schoolId, $userId, $userId);
            $stmt->execute();
            $res         = $stmt->get_result();
            $assignments = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            $stmt->close();
        } else {
            $assignments = [];
        }

        // 2) Ambil status submissions murid ini untuk assignment yang tampil
        if (!empty($assignments)) {
            $assignmentIds = array_column($assignments, 'id');
            $assignmentIds = array_map('intval', $assignmentIds);

            // kalau tidak ada id, lewati
            if (!empty($assignmentIds)) {
                // aman karena semua sudah di-cast ke int
                $inIds = implode(',', $assignmentIds);

                $sqlSub = "
                    SELECT assignment_id, submitted_at
                    FROM submissions
                    WHERE student_id = {$userId}
                      AND assignment_id IN ({$inIds})
                ";
                $resSub = $db->query($sqlSub);
                if ($resSub) {
                    while ($row = $resSub->fetch_assoc()) {
                        $submissionMap[(int)$row['assignment_id']] = $row;
                    }
                }
            }
        }

    // ======================
    // ADMIN : semua tugas di sekolah ini
    // ======================
    } elseif ($role === 'admin') {
        $stmt = $db->prepare("
            SELECT a.id, a.judul, a.created_at, a.deadline,
                   s.nama_mapel, c.nama_kelas, c.level, c.jurusan
            FROM assignments a
            JOIN classes   c  ON a.target_class_id = c.id
            LEFT JOIN subjects s ON a.subject_id   = s.id
            WHERE c.school_id = ?
            ORDER BY a.created_at DESC
        ");
        $stmt->bind_param("i", $schoolId);
        $stmt->execute();
        $res         = $stmt->get_result();
        $assignments = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

    } else {
        $assignments = [];
    }
}

// ---------------------- RENDER ----------------------
$pageTitle = 'Daftar Tugas';
include __DIR__ . '/../inc/header.php';
?>

<div class="container">
    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
        <div>
            <h1 style="margin-bottom:4px;">Daftar Tugas</h1>
            <p style="margin:0;font-size:0.9rem;color:#6b7280;">
                Anda login sebagai <strong><?php echo strtoupper(htmlspecialchars($role)); ?></strong>.
                Menampilkan tugas di lingkungan sekolah Anda.
            </p>
        </div>

        <?php if ($role === 'guru'): ?>
            <a href="<?php echo $baseUrl; ?>/assignments/create.php" class="btn btn-primary">
                + Buat Tugas
            </a>
        <?php endif; ?>
    </div>

    <?php if ($schoolId <= 0): ?>
        <div class="card">
            <p>School ID tidak ditemukan. Silakan logout lalu login kembali.</p>
        </div>
    <?php elseif (empty($assignments)): ?>
        <div class="card">
            <?php if ($isMurid): ?>
                <p>Tidak ada tugas untuk kelas Anda (atau Anda belum tergabung di kelas mana pun).</p>
            <?php else: ?>
                <p>Tidak ada tugas.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="card">
            <div style="overflow-x:auto;">
                <table class="table" style="width:100%;">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Judul</th>
                            <th>Mata Pelajaran / Kelas</th>
                            <th>Dibuat</th>
                            <th>Deadline</th>
                            <th>Status</th>
                            <th style="width:180px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assignments as $i => $a): ?>
                            <tr>
                                <td><?php echo (int)($i + 1); ?></td>
                                <td><?php echo sanitize($a['judul']); ?></td>
                                <td>
                                    <?php
                                        $kelasStr  = trim(($a['level'] ?? '') . ' ' . ($a['jurusan'] ?? ''));
                                        $kelasNama = $kelasStr ?: ($a['nama_kelas'] ?? '');
                                        echo sanitize(($a['nama_mapel'] ?? '') . ' — ' . $kelasNama);
                                    ?>
                                </td>
                                <td><?php echo sanitize($a['created_at']); ?></td>
                                <td>
                                    <?php
                                    $deadline = $a['deadline'] ?? null;
                                    echo ($deadline && $deadline !== '0000-00-00 00:00:00')
                                        ? sanitize($deadline)
                                        : '-';
                                    ?>
                                </td>
                                <td>
                                    <?php if ($isMurid): ?>
                                        <?php if (!empty($submissionMap[(int)$a['id']])): ?>
                                            <span style="color:#16a34a;font-weight:600;">Sudah dikerjakan</span>
                                        <?php else: ?>
                                            <span style="color:#b91c1c;font-weight:600;">Belum dikerjakan</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo $baseUrl; ?>/assignments/view.php?id=<?php echo (int)$a['id']; ?>">
                                        Lihat
                                    </a>
                                    <?php if ($role === 'guru'): ?>
                                        &middot;
                                        <a href="<?php echo $baseUrl; ?>/assignments/edit.php?id=<?php echo (int)$a['id']; ?>">
                                            Edit
                                        </a>
                                        &middot;
                                        <a href="<?php echo $baseUrl; ?>/assignments/delete.php?id=<?php echo (int)$a['id']; ?>"
                                           onclick="return confirm('Hapus tugas?');">
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
