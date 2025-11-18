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
$users    = [];

$isMurid = ($role === 'murid' || $role === 'siswa');

// --- Ambil parameter filter & search ---
$q          = trim($_GET['q'] ?? '');
$filterRole = $_GET['filter_role'] ?? ''; // hanya dipakai admin

// Normalisasi filter role biar aman
$allowedFilterRoles = ['admin', 'guru', 'murid'];
if (!in_array($filterRole, $allowedFilterRoles, true)) {
    $filterRole = '';
}

// helper kecil: cari kolom nama yang tersedia
function displayNameFromRow(array $row) {
    $candidates = ['nama','name','fullname','full_name','display_name','username','user_name'];
    foreach ($candidates as $k) {
        if (isset($row[$k]) && trim((string)$row[$k]) !== '') {
            return $row[$k];
        }
    }
    // fallback ke email atau id
    if (!empty($row['email'])) return $row['email'];
    if (!empty($row['id']))    return 'User #' . (int)$row['id'];
    return '-';
}

if ($schoolId > 0) {

    // ====================== ADMIN ======================
    if ($role === 'admin') {
        $sql = "
            SELECT id, nama, email, role, created_at
            FROM users
        ";

        $conditions = ["school_id = ?"];
        $types      = "i";
        $params     = [$schoolId];

        // Filter role (admin/guru/murid → murid juga include siswa)
        if ($filterRole === 'admin' || $filterRole === 'guru') {
            $conditions[] = "role = ?";
            $types       .= "s";
            $params[]     = $filterRole;
        } elseif ($filterRole === 'murid') {
            // murid = murid + siswa
            $conditions[] = "(role = 'murid' OR role = 'siswa')";
        }

        // Search by nama / email
        if ($q !== '') {
            $conditions[] = "(nama LIKE ? OR email LIKE ?)";
            $types       .= "ss";
            $like         = '%' . $q . '%';
            $params[]     = $like;
            $params[]     = $like;
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= "
            ORDER BY 
                FIELD(role, 'admin','guru','murid','siswa') ASC,
                nama ASC,
                id ASC
        ";

        $stmt = $db->prepare($sql);
        if ($stmt) {
            if ($params) {
                $bindParams = [];
                $bindParams[] = &$types;
                foreach ($params as $k => $v) {
                    $bindParams[] = &$params[$k];
                }
                call_user_func_array([$stmt, 'bind_param'], $bindParams);
            }
            $stmt->execute();
            $res   = $stmt->get_result();
            $users = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            $stmt->close();
        }

    // ====================== GURU ======================
    } elseif ($role === 'guru') {
        $sql = "
            SELECT DISTINCT u.id, u.nama, u.email, u.role, u.created_at
            FROM users u
            JOIN class_user cu ON cu.user_id = u.id
            JOIN classes    c  ON cu.class_id = c.id
            WHERE c.guru_id   = ?
              AND c.school_id = ?
              AND (u.role = 'murid' OR u.role = 'siswa')
        ";

        $types  = "ii";
        $params = [$userId, $schoolId];

        if ($q !== '') {
            $sql   .= " AND (u.nama LIKE ? OR u.email LIKE ?)";
            $types .= "ss";
            $like   = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= " ORDER BY u.nama ASC, u.id ASC";

        $stmt = $db->prepare($sql);
        if ($stmt) {
            $bindParams   = [];
            $bindParams[] = &$types;
            foreach ($params as $k => $v) {
                $bindParams[] = &$params[$k];
            }
            call_user_func_array([$stmt, 'bind_param'], $bindParams);
            $stmt->execute();
            $res   = $stmt->get_result();
            $users = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            $stmt->close();
        }

    // ====================== MURID / SISWA ======================
    } elseif ($isMurid) {
        // Murid cuma lihat dirinya sendiri, search boleh dipakai
        $sql = "
            SELECT id, nama, email, role, created_at
            FROM users
            WHERE id = ? AND school_id = ?
        ";

        $types  = "ii";
        $params = [$userId, $schoolId];

        if ($q !== '') {
            $sql   .= " AND (nama LIKE ? OR email LIKE ?)";
            $types .= "ss";
            $like   = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= " LIMIT 1";

        $stmt = $db->prepare($sql);
        if ($stmt) {
            $bindParams   = [];
            $bindParams[] = &$types;
            foreach ($params as $k => $v) {
                $bindParams[] = &$params[$k];
            }
            call_user_func_array([$stmt, 'bind_param'], $bindParams);
            $stmt->execute();
            $res   = $stmt->get_result();
            $users = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            $stmt->close();
        }

    // ====================== ROLE LAIN (AMAN) ======================
    } else {
        $stmt = $db->prepare("
            SELECT id, nama, email, role, created_at
            FROM users
            WHERE id = ? AND school_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("ii", $userId, $schoolId);
        $stmt->execute();
        $res   = $stmt->get_result();
        $users = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }
} else {
    $users = [];
}

$pageTitle = 'Daftar Pengguna';
include __DIR__ . '/../inc/header.php';
?>

<div class="container">
    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
        <div>
            <h1 style="margin-bottom:4px;">Daftar Pengguna</h1>
            <p style="margin:0;font-size:0.9rem;color:#6b7280;">
                Menampilkan pengguna di lingkungan sekolah Anda saja.
            </p>
        </div>
        <?php if ($role === 'admin'): ?>
            <a href="<?php echo $baseUrl; ?>/users/create.php" class="btn btn-primary">+ Tambah Pengguna</a>
        <?php endif; ?>
    </div>

    <?php if ($schoolId <= 0): ?>
        <div class="card">
            <p>School ID tidak ditemukan. Silakan logout lalu login kembali.</p>
        </div>
    <?php else: ?>

        <!-- FILTER & SEARCH BAR -->
        <div class="card" style="margin-bottom:12px;">
            <form method="get" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                <!-- Search -->
                <div style="flex:1 1 220px;min-width:200px;">
                    <label for="q" style="display:block;font-size:0.8rem;color:#6b7280;margin-bottom:2px;">
                        Cari nama atau email
                    </label>
                    <input
                        type="text"
                        id="q"
                        name="q"
                        value="<?php echo sanitize($q); ?>"
                        placeholder="Contoh: Ahmad, guru@gmail.com"
                        style="width:100%;padding:6px 8px;border:1px solid #e5e7eb;border-radius:6px;font-size:0.9rem;"
                    >
                </div>

                <!-- Filter role khusus admin -->
                <?php if ($role === 'admin'): ?>
                    <div style="flex:0 0 180px;">
                        <label style="display:block;font-size:0.8rem;color:#6b7280;margin-bottom:2px;">
                            Filter per role
                        </label>
                        <select
                            name="filter_role"
                            style="width:100%;padding:6px 8px;border:1px solid #e5e7eb;border-radius:6px;font-size:0.9rem;"
                        >
                            <option value="">Semua role</option>
                            <option value="admin" <?php echo $filterRole === 'admin' ? 'selected' : ''; ?>>
                                Admin
                            </option>
                            <option value="guru" <?php echo $filterRole === 'guru' ? 'selected' : ''; ?>>
                                Guru
                            </option>
                            <option value="murid" <?php echo $filterRole === 'murid' ? 'selected' : ''; ?>>
                                Murid
                            </option>
                        </select>
                    </div>
                <?php endif; ?>

                <div style="flex:0 0 auto;align-self:flex-end;">
                    <button type="submit" class="btn btn-secondary">
                        Terapkan
                    </button>
                </div>
            </form>
        </div>

        <!-- TABEL HASIL -->
        <?php if (empty($users)): ?>
            <div class="card">
                <?php if ($role === 'admin'): ?>
                    <p>Tidak ada pengguna yang cocok dengan filter ini.</p>
                <?php elseif ($role === 'guru'): ?>
                    <p>Tidak ada murid yang cocok dengan filter ini.</p>
                <?php else: ?>
                    <p>Data pengguna tidak ditemukan.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <div style="overflow-x:auto;">
                    <table class="table">
                        <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Nama / Identitas</th>
                            <th>Email</th>
                            <th style="width:180px;">Aksi</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $i => $u): ?>
                            <tr>
                                <td><?php echo (int)($i + 1); ?></td>
                                <td><?php echo sanitize(displayNameFromRow($u)); ?></td>
                                <td><?php echo sanitize($u['email'] ?? '-'); ?></td>
                                <td>
                                    <a href="<?php echo $baseUrl; ?>/users/view.php?id=<?php echo (int)$u['id']; ?>">
                                        Lihat
                                    </a>
                                    <?php if ($role === 'admin'): ?>
                                        &middot;
                                        <a href="<?php echo $baseUrl; ?>/users/edit.php?id=<?php echo (int)$u['id']; ?>">
                                            Edit
                                        </a>
                                        &middot;
                                        <a href="<?php echo $baseUrl; ?>/users/delete.php?id=<?php echo (int)$u['id']; ?>"
                                           onclick="return confirm('Hapus pengguna ini?');">
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

    <?php endif; ?>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
