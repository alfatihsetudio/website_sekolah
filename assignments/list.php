<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

$pageTitle = 'Daftar Tugas';
include __DIR__ . '/../inc/header.php';

$db = getDB();
$userId = (int)($_SESSION['user_id'] ?? 0);

// IMPORTANT: default role harus 'murid' sesuai DB (bukan 'siswa')
$role = $_SESSION['role'] ?? 'murid';
$assignments    = [];
$submissionMap  = []; // [assignment_id] => row submissions (untuk murid)

/*
 Roles:
  - guru : tampilkan tugas yang dibuat guru itu
  - murid: tampilkan tugas untuk kelas yang murid terdaftar (class_user) + status sudah/belum
  - admin: tampilkan semua tugas (opsional)
*/

// GURU -> tugas yang dibuat sendiri
if ($role === 'guru') {
    $stmt = $db->prepare("
        SELECT a.id, a.judul, a.created_at, a.deadline,
               s.nama_mapel, c.nama_kelas, c.level, c.jurusan
        FROM assignments a
        JOIN subjects s ON a.subject_id = s.id
        JOIN classes  c ON a.target_class_id = c.id
        WHERE a.created_by = ?
        ORDER BY a.created_at DESC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $assignments = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

// MURID -> cari kelas yang user terdaftar, lalu ambil tugas untuk kelas-kelas itu
} elseif ($role === 'murid') {
    // ambil daftar class_id user (class_user)
    $classIds = [];
    $stmt = $db->prepare("SELECT class_id FROM class_user WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) {
            $classIds[] = (int)$row['class_id'];
        }
        $stmt->close();
    }

    // jika tidak ada enrollments, fallback ke users.class_id (jika ada)
    if (empty($classIds)) {
        $stmt2 = $db->prepare("SELECT class_id FROM users WHERE id = ? LIMIT 1");
        if ($stmt2) {
            $stmt2->bind_param("i", $userId);
            $stmt2->execute();
            $r2 = $stmt2->get_result();
            if ($r2 && $r2->num_rows) {
                $ur = $r2->fetch_assoc();
                if (!empty($ur['class_id'])) $classIds[] = (int)$ur['class_id'];
            }
            $stmt2->close();
        }
    }

    if (!empty($classIds)) {
        // build placeholders untuk IN(...)
        $placeholders = implode(',', array_fill(0, count($classIds), '?'));
        $sql = "
            SELECT DISTINCT a.id, a.judul, a.created_at, a.deadline,
                            s.nama_mapel, c.nama_kelas, c.level, c.jurusan
            FROM assignments a
            LEFT JOIN subjects s ON a.subject_id     = s.id
            LEFT JOIN classes  c ON a.target_class_id = c.id
            WHERE a.target_class_id IN ($placeholders)
            ORDER BY a.created_at DESC
        ";

        $stmt = $db->prepare($sql);
        if ($stmt) {
            // bind_param requires types + refs
            $types = str_repeat('i', count($classIds));
            $refs  = [];
            $refs[] = &$types;
            foreach ($classIds as $k => $v) {
                $classIds[$k] = (int)$v;
                $refs[] = &$classIds[$k];
            }
            call_user_func_array([$stmt, 'bind_param'], $refs);
            $stmt->execute();
            $res = $stmt->get_result();
            $assignments = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            $stmt->close();
        } else {
            $assignments = [];
        }

        // === Ambil status submissions murid ini untuk assignment yang tampil ===
        if (!empty($assignments)) {
            $assignmentIds = array_column($assignments, 'id');
            $placeholders2 = implode(',', array_fill(0, count($assignmentIds), '?'));

            $sqlSub = "
                SELECT assignment_id, submitted_at
                FROM submissions
                WHERE student_id = ?
                  AND assignment_id IN ($placeholders2)
            ";

            $stmtSub = $db->prepare($sqlSub);
            if ($stmtSub) {
                // tipe: 1 student_id + N assignment_id (semua int)
                $typesSub = 'i' . str_repeat('i', count($assignmentIds));
                $params   = [];
                $params[] = &$typesSub;

                $sid = $userId;
                $params[] = &$sid;
                foreach ($assignmentIds as $k => $aid) {
                    $assignmentIds[$k] = (int)$aid;
                    $params[] = &$assignmentIds[$k];
                }

                call_user_func_array([$stmtSub, 'bind_param'], $params);
                $stmtSub->execute();
                $rSub = $stmtSub->get_result();
                while ($row = $rSub->fetch_assoc()) {
                    $submissionMap[(int)$row['assignment_id']] = $row;
                }
                $stmtSub->close();
            }
        }

    } else {
        // user memang belum tergabung di kelas mana pun
        $assignments = [];
    }

// ADMIN -> semua tugas (opsional)
} else {
    $stmt = $db->prepare("
        SELECT a.id, a.judul, a.created_at, a.deadline,
               s.nama_mapel, c.nama_kelas, c.level, c.jurusan
        FROM assignments a
        LEFT JOIN subjects s ON a.subject_id = s.id
        LEFT JOIN classes  c ON a.target_class_id = c.id
        ORDER BY a.created_at DESC
    ");
    $stmt->execute();
    $res = $stmt->get_result();
    $assignments = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}
?>

<div class="container">
    <div class="page-header">
        <h1>Daftar Tugas</h1>
        <?php if ($role === 'guru'): ?>
            <a href="/web_MG/assignments/create.php" class="btn btn-primary">+ Buat Tugas</a>
        <?php endif; ?>
    </div>

    <?php if (empty($assignments)): ?>
        <div class="card">
            <?php if ($role === 'murid'): ?>
                <p>Tidak ada tugas untuk kelas Anda (atau Anda belum tergabung di kelas mana pun).</p>
            <?php else: ?>
                <p>Tidak ada tugas.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="card">
            <table class="table" style="width:100%;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Judul</th>
                        <th>Mata Pelajaran / Kelas</th>
                        <th>Dibuat</th>
                        <th>Deadline</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assignments as $i => $a): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><?php echo sanitize($a['judul']); ?></td>
                            <td>
                                <?php
                                    $kelasStr = trim((($a['level'] ?? '') . ' ' . ($a['jurusan'] ?? '')));
                                    echo sanitize(($a['nama_mapel'] ?? '') . ' — ' . ($kelasStr ?: ($a['nama_kelas'] ?? '')));
                                ?>
                            </td>
                            <td><?php echo sanitize($a['created_at']); ?></td>
                            <td>
                                <?php
                                    echo ($a['deadline'] && $a['deadline'] !== '0000-00-00 00:00:00')
                                        ? sanitize($a['deadline'])
                                        : '-';
                                ?>
                            </td>
                            <td>
                                <?php if ($role === 'murid'): ?>
                                    <?php if (!empty($submissionMap[(int)$a['id']])): ?>
                                        <span style="color:green;font-weight:600;">Sudah dikerjakan</span>
                                    <?php else: ?>
                                        <span style="color:#b91c1c;font-weight:600;">Belum dikerjakan</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="/web_MG/assignments/view.php?id=<?php echo (int)$a['id']; ?>">Lihat</a>
                                <?php if ($role === 'guru'): ?>
                                    &middot; <a href="/web_MG/assignments/edit.php?id=<?php echo (int)$a['id']; ?>">Edit</a>
                                    &middot; <a href="/web_MG/assignments/delete.php?id=<?php echo (int)$a['id']; ?>" onclick="return confirm('Hapus tugas?')">Hapus</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
