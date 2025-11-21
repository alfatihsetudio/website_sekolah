<?php
// space_belajar/view_note.php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/db.php';

requireLogin();

$db      = getDB();
$userId  = (int)($_SESSION['user_id'] ?? 0);
$fileId  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($fileId <= 0) {
    echo "ID file tidak valid.";
    exit;
}

// Ambil metadata dari database
$stmt = $db->prepare("
    SELECT id, user_id, title, stored_name, original_name, mime_type, created_at
    FROM study_files
    WHERE id = ? AND user_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $fileId, $userId);
$stmt->execute();
$res  = $stmt->get_result();
$file = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$file) {
    echo "File tidak ditemukan atau Anda tidak memiliki akses.";
    exit;
}

$fullPath = __DIR__ . '/../uploads/study_files/' . $file['stored_name'];

if (!file_exists($fullPath)) {
    echo "File tidak ditemukan di server.";
    exit;
}

// Baca isi file
$content = file_get_contents($fullPath);

// Escape agar aman (biar tidak menjalankan script)
$safeContent = nl2br(htmlspecialchars($content));
$baseUrl     = rtrim(BASE_URL, '/\\');

$pageTitle = 'Catatan: ' . sanitize($file['title']);
include __DIR__ . '/../inc/header.php';
?>

<div class="container" style="max-width:850px;">

    <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
        <h1 style="margin:0;"><?php echo sanitize($file['title']); ?></h1>

        <a href="javascript:history.back()" class="btn btn-secondary" style="white-space:nowrap;">
            ← Kembali
        </a>
    </div>

    <div class="card" style="margin-top:15px;padding:18px;">
        <div style="
            white-space:pre-wrap;
            font-family: 'Courier New', monospace;
            font-size:1rem;
            line-height:1.55;
        ">
            <?php echo $safeContent; ?>
        </div>
    </div>

   <div style="margin-top:18px; font-size:0.85rem; color:#6b7280;">
    Dibuat: <?php echo sanitize($file['created_at']); ?><br>
    Nama file: <?php echo sanitize($file['original_name']); ?>
    </div>

    <div style="margin-top:15px;">
        <a href="<?php echo $baseUrl . '/uploads/study_files/' . sanitize($file['stored_name']); ?>"
        download="<?php echo sanitize($file['original_name']); ?>"
        class="btn btn-primary">
            ⬇️ Download File
        </a>
    </div>


</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
