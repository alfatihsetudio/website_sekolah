<?php
/**
 * Submit Tugas (Hardened)
 */
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/helpers.php'; // pastikan helper ada (uploadFile, sanitize, formatTanggal, createNotification)
requireRole(['murid']);

$db = getDB();
$muridId = (int)($_SESSION['user_id'] ?? 0);
$assignmentId = (int)($_GET['id'] ?? 0);

if ($assignmentId <= 0) {
    header('Location: /web_MG/assignments/list.php');
    exit;
}

// Get assignment (prepared statement)
$sql = "SELECT a.*, s.class_id, a.judul, a.due_date as deadline, a.created_by
        FROM assignments a
        JOIN subjects s ON a.subject_id = s.id
        WHERE a.id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $assignmentId);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    header('Location: /web_MG/assignments/list.php');
    exit;
}

$assignment = $result->fetch_assoc();

// Check if student is in the class (prepared)
$sql = "SELECT id FROM class_user WHERE class_id = ? AND user_id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("ii", $assignment['class_id'], $muridId);
$stmt->execute();
$check = $stmt->get_result();
if (!$check || $check->num_rows === 0) {
    header('Location: /web_MG/assignments/list.php');
    exit;
}

// Check if already submitted
$sql = "SELECT id FROM submissions WHERE assignment_id = ? AND student_id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("ii", $assignmentId, $muridId);
$stmt->execute();
$existing = $stmt->get_result();
if ($existing && $existing->num_rows > 0) {
    header('Location: /web_MG/assignments/view.php?id=' . $assignmentId);
    exit;
}

$error = '';

// Process form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check (requires inc/auth.php generate/verify functions)
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken, 'submit_assignment_' . $assignmentId)) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman dan coba lagi.';
    } else {
        $linkDrive = trim($_POST['link_drive'] ?? '');
        $catatan = trim($_POST['catatan'] ?? '');

        $fileId = null;

        // Validate link jika diisi
        if (!empty($linkDrive) && !filter_var($linkDrive, FILTER_VALIDATE_URL)) {
            $error = 'Link Google Drive tidak valid.';
        }

        // OPTIONAL: check deadline policy (uncomment / adjust if you want to block submissions after deadline)
        // if (!empty($assignment['deadline'])) {
        //     $deadline_ts = strtotime($assignment['deadline']);
        //     if ($deadline_ts !== false && time() > $deadline_ts) {
        //         $error = 'Waktu pengumpulan sudah lewat. Anda tidak dapat mengumpulkan tugas ini.';
        //     }
        // }

        // Handle file upload
        if (empty($error) && isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            // uploadFile() diasumsikan tersedia di helpers.php
            $uploadResult = uploadFile($_FILES['file'], 'submissions/');
            if ($uploadResult['success']) {
                // uploadFile sebaiknya mengembalikan index/file_id dari tabel file_uploads
                $fileId = $uploadResult['file_id'] ?? null;
            } else {
                $error = $uploadResult['message'] ?? 'Gagal meng-upload file.';
            }
        }

        if (empty($error)) {
            if (empty($fileId) && empty($linkDrive)) {
                $error = 'Harus upload file atau isi link Google Drive.';
            } else {
                // Insert submission menggunakan prepared statement
                $sql = "INSERT INTO submissions (assignment_id, student_id, file_id, link_drive, catatan, submitted_at)
                        VALUES (?, ?, ?, ?, ?, NOW())";
                $stmt = $db->prepare($sql);

                // handle null file_id
                if ($fileId === null) {
                    $fileId_param = null;
                    // bind_param cannot bind PHP null directly for integer; use string and convert in SQL if needed
                    // We'll bind as string and let DB accept NULL if passed as null via mysqli_stmt::bind_param not possible.
                    // Safer: use conditional query to set file_id to NULL literal.
                    $sql = "INSERT INTO submissions (assignment_id, student_id, file_id, link_drive, catatan, submitted_at)
                            VALUES (?, ?, NULLIF(?, ''), ?, ?, NOW())";
                    // We'll prepare again with different binding: file_id as string (or empty) then NULLIF will convert '' to NULL
                    $stmt = $db->prepare($sql);
                    $fileIdStr = $fileId ? (string)$fileId : '';
                    $stmt->bind_param("iiss", $assignmentId, $muridId, $fileIdStr, $linkDrive, $catatan);
                } else {
                    // fileId exists, bind as integer and link_drive can be empty
                    $stmt = $db->prepare("INSERT INTO submissions (assignment_id, student_id, file_id, link_drive, catatan, submitted_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt->bind_param("iiiss", $assignmentId, $muridId, $fileId, $linkDrive, $catatan);
                }

                if (!$stmt) {
                    $error = 'Gagal menyiapkan query database.';
                } else {
                    if ($stmt->execute()) {
                        // Notify teacher (assume createNotification exists in helpers)
                        $teacherId = $assignment['created_by'] ?? null;
                        if ($teacherId) {
                            createNotification(
                                $teacherId,
                                'Submission Baru',
                                "Murid mengumpulkan tugas: " . sanitize($assignment['judul']),
                                '/web_MG/assignments/grade.php?id=' . $assignmentId
                            );
                        }

                        header('Location: /web_MG/assignments/view.php?id=' . $assignmentId);
                        exit;
                    } else {
                        $error = 'Gagal mengumpulkan tugas (DB).';
                    }
                }
            }
        }
    }
}

// Page render
$pageTitle = 'Kumpulkan Tugas';
include __DIR__ . '/../inc/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Kumpulkan Tugas</h1>
        <a href="/web_MG/assignments/view.php?id=<?php echo $assignmentId; ?>" class="btn btn-secondary">← Kembali</a>
    </div>

    <div class="card">
        <h3><?php echo sanitize($assignment['judul']); ?></h3>
        <p><strong>Deadline:</strong> <?php echo isset($assignment['deadline']) ? formatTanggal($assignment['deadline'], true) : '-'; ?></p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="" enctype="multipart/form-data" class="form">
            <?php
            // CSRF token (generate per-form)
            $csrfToken = generateCsrfToken('submit_assignment_' . $assignmentId);
            ?>
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">

            <div class="form-group">
                <label for="file">Upload File Tugas</label>
                <input
                    type="file"
                    id="file"
                    name="file"
                    accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip"
                >
                <small>Maksimal 10MB. Format: PDF, DOC, DOCX, JPG, PNG, ZIP</small>
            </div>

            <div style="text-align: center; margin: 16px 0; color: var(--gray-500);">
                <strong>ATAU</strong>
            </div>

            <div class="form-group">
                <label for="link_drive">Link Google Drive</label>
                <input
                    type="url"
                    id="link_drive"
                    name="link_drive"
                    placeholder="https://drive.google.com/..."
                    value="<?php echo isset($_POST['link_drive']) ? sanitize($_POST['link_drive']) : ''; ?>"
                >
                <small>Jika file terlalu besar, gunakan link Google Drive</small>
            </div>

            <div class="form-group">
                <label for="catatan">Catatan (Opsional)</label>
                <textarea
                    id="catatan"
                    name="catatan"
                    rows="4"
                    placeholder="Tambahkan catatan jika diperlukan..."
                ><?php echo isset($_POST['catatan']) ? sanitize($_POST['catatan']) : ''; ?></textarea>
            </div>

            <button type="submit" class="btn btn-success btn-block">Kumpulkan Tugas</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
