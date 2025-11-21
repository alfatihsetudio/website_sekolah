<?php
// space_belajar/progres_belajar.php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireLogin();
$role = getUserRole();
if (!in_array($role, ['murid','siswa'], true)) {
    http_response_code(403);
    echo "Halaman ini khusus murid.";
    exit;
}

$db      = getDB();
$baseUrl = rtrim(BASE_URL, '/\\');
$userId  = (int)($_SESSION['user_id'] ?? 0);

$errors  = [];
$success = '';

// tambah goal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_goal') {
    $title       = trim($_POST['goal_title'] ?? '');
    $description = trim($_POST['goal_desc'] ?? '');
    $targetDate  = trim($_POST['target_date'] ?? '');

    if ($title === '') {
        $errors[] = 'Judul tujuan tidak boleh kosong.';
    } else {
        if ($targetDate === '') $targetDate = null;

        $stmt = $db->prepare("
            INSERT INTO study_goals (user_id, title, description, target_date, status, created_at)
            VALUES (?, ?, ?, ?, 'ongoing', NOW())
        ");
        $stmt->bind_param("isss", $userId, $title, $description, $targetDate);
        if ($stmt->execute()) {
            $success = 'Tujuan belajar baru berhasil dibuat.';
            $_POST = [];
        } else {
            $errors[] = 'Gagal menyimpan tujuan belajar.';
        }
        $stmt->close();
    }
}

// tambah log progres
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_log') {
    $goalId  = (int)($_POST['goal_id'] ?? 0);
    $logText = trim($_POST['log_text'] ?? '');
    $logDate = trim($_POST['log_date'] ?? '');

    if ($goalId <= 0 || $logText === '') {
        $errors[] = 'Pilih tujuan dan isi progres yang kamu lakukan.';
    } else {
        if ($logDate === '') $logDate = date('Y-m-d');

        // cek goal milik user
        $stmt = $db->prepare("SELECT id FROM study_goals WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->bind_param("ii", $goalId, $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res || !$res->num_rows) {
            $errors[] = 'Tujuan belajar tidak ditemukan.';
        } else {
            $stmt2 = $db->prepare("
                INSERT INTO study_goal_logs (goal_id, log_date, notes, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt2->bind_param("iss", $goalId, $logDate, $logText);
            if ($stmt2->execute()) {
                $success = 'Progres berhasil ditambahkan.';
                $_POST['log_text'] = '';
            } else {
                $errors[] = 'Gagal menyimpan progres.';
            }
            $stmt2->close();
        }
        $stmt->close();
    }
}

// ambil semua goal + 3 log terbaru tiap goal
$goals = [];
$stmt = $db->prepare("
    SELECT id, title, description, target_date, status, created_at
    FROM study_goals
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
if ($res) $goals = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$logsByGoal = [];
if (!empty($goals)) {
    $ids = array_column($goals, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    $sql = "
        SELECT goal_id, log_date, notes, created_at
        FROM study_goal_logs
        WHERE goal_id IN ($placeholders)
        ORDER BY log_date DESC, created_at DESC
    ";
    $stmt = $db->prepare($sql);

    $params = [];
    $params[] = & $types;
    foreach ($ids as $k => $gid) {
        $ids[$k] = (int)$gid;
        $params[] = & $ids[$k];
    }
    call_user_func_array([$stmt, 'bind_param'], $params);

    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $gId = (int)$row['goal_id'];
        if (!isset($logsByGoal[$gId])) $logsByGoal[$gId] = [];
        if (count($logsByGoal[$gId]) < 3) {
            $logsByGoal[$gId][] = $row;
        }
    }
    $stmt->close();
}

$pageTitle = 'Progres Belajar';
include __DIR__ . '/../inc/header.php';
?>

<div class="container" style="max-width:1100px;margin:0 auto;">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
        <div>
            <h1 style="margin-bottom:4px;">Progres Belajar</h1>
            <p style="margin:0;font-size:.9rem;color:#6b7280;">
                Tulis tujuan belajar dan catat langkah-langkah yang sudah kamu kerjakan.
            </p>
        </div>
        <a href="<?php echo $baseUrl; ?>/space_belajar/dashboard.php"
           class="btn btn-secondary" style="font-size:.85rem;">
            ← Kembali ke Space Belajar
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $e): ?>
                <div><?php echo sanitize($e); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo sanitize($success); ?></div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:minmax(0,1.3fr) minmax(0,1.7fr);gap:16px;flex-wrap:wrap;">
        <!-- form tambah goal -->
        <div class="card">
            <h3 style="margin-top:0;margin-bottom:8px;font-size:.98rem;">Tambah tujuan belajar</h3>
            <form method="post">
                <input type="hidden" name="action" value="add_goal">
                <div class="form-group">
                    <label for="goal_title">Judul tujuan</label>
                    <input type="text" id="goal_title" name="goal_title"
                           value="<?php echo sanitize($_POST['goal_title'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="goal_desc">Detail tujuan (opsional)</label>
                    <textarea id="goal_desc" name="goal_desc" rows="3"><?php
                        echo sanitize($_POST['goal_desc'] ?? '');
                    ?></textarea>
                </div>
                <div class="form-group">
                    <label for="target_date">Target selesai (opsional)</label>
                    <input type="date" id="target_date" name="target_date"
                           value="<?php echo sanitize($_POST['target_date'] ?? ''); ?>">
                </div>
                <button type="submit" class="btn btn-primary">
                    Simpan tujuan
                </button>
            </form>
        </div>

        <!-- form tambah log -->
        <div class="card">
            <h3 style="margin-top:0;margin-bottom:8px;font-size:.98rem;">Catat progres hari ini</h3>
            <form method="post">
                <input type="hidden" name="action" value="add_log">
                <div class="form-group">
                    <label for="goal_id">Pilih tujuan</label>
                    <select id="goal_id" name="goal_id" required>
                        <option value="">-- Pilih tujuan --</option>
                        <?php foreach ($goals as $g): ?>
                            <option value="<?php echo (int)$g['id']; ?>"
                                <?php echo (isset($_POST['goal_id']) && (int)$_POST['goal_id'] === (int)$g['id']) ? 'selected' : ''; ?>>
                                <?php echo sanitize($g['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="log_date">Tanggal progres</label>
                    <input type="date" id="log_date" name="log_date"
                           value="<?php echo sanitize($_POST['log_date'] ?? date('Y-m-d')); ?>">
                </div>

                <div class="form-group">
                    <label for="log_text">Apa yang kamu kerjakan?</label>
                    <textarea id="log_text" name="log_text" rows="4" required><?php
                        echo sanitize($_POST['log_text'] ?? '');
                    ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary">
                    Simpan progres
                </button>
            </form>
        </div>
    </div>

    <!-- daftar goal -->
    <div class="card" style="margin-top:16px;">
        <h3 style="margin-top:0;margin-bottom:8px;font-size:.98rem;">Daftar tujuan belajar kamu</h3>
        <?php if (empty($goals)): ?>
            <p style="margin:0;">Belum ada tujuan yang kamu buat. Mulai dari formulir di atas.</p>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <?php foreach ($goals as $g): ?>
                    <div style="padding:10px 12px;border-radius:10px;border:1px solid #e5e7eb;background:#f9fafb;">
                        <div style="font-weight:600;"><?php echo sanitize($g['title']); ?></div>
                        <div style="font-size:.82rem;color:#6b7280;margin-top:2px;">
                            Dibuat: <?php echo sanitize($g['created_at']); ?>
                            <?php if (!empty($g['target_date'])): ?>
                                · Target: <?php echo sanitize($g['target_date']); ?>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($g['description'])): ?>
                            <div style="font-size:.88rem;color:#374151;margin-top:4px;">
                                <?php echo nl2br(sanitize($g['description'])); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($logsByGoal[(int)$g['id']] ?? [])): ?>
                            <div style="margin-top:6px;font-size:.84rem;color:#4b5563;">
                                <strong>Progres terbaru:</strong>
                                <ul style="margin:4px 0 0 18px;padding:0;">
                                    <?php foreach ($logsByGoal[(int)$g['id']] as $log): ?>
                                        <li>
                                            <span style="color:#6b7280;"><?php echo sanitize($log['log_date']); ?>:</span>
                                            <?php echo sanitize($log['notes']); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php else: ?>
                            <div style="margin-top:6px;font-size:.84rem;color:#6b7280;">
                                Belum ada progres yang dicatat untuk tujuan ini.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
