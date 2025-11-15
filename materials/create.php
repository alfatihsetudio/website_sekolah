<?php
/**
 * Tambah Materi Pembelajaran (Hardened)
 * Upload foto/teks/video untuk materi pembelajaran
 */
require_once __DIR__ . '/../inc/auth.php';
requireRole(['guru']);

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

$db = getDB();
$guruId = (int)($_SESSION['user_id'] ?? 0);

$error = '';
$success = '';

// Ambil subjects yang diajar guru (prepared)
$stmt = $db->prepare("
    SELECT s.id, s.nama_mapel, c.nama_kelas
    FROM subjects s
    JOIN classes c ON s.class_id = c.id
    WHERE s.guru_id = ?
    ORDER BY c.nama_kelas, s.nama_mapel
");
$stmt->bind_param("i", $guruId);
$stmt->execute();
$res = $stmt->get_result();
$subjects = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

// Process form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($subjects)) {
    // CSRF check
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token, 'create_material')) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman dan coba lagi.';
    } else {
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $judul = trim($_POST['judul'] ?? '');
        $konten = trim($_POST['konten'] ?? '');
        $videoLink = trim($_POST['video_link'] ?? '');

        // Validasi minimal
        if ($subjectId <= 0) {
            $error = 'Mata pelajaran harus dipilih';
        } elseif ($judul === '') {
            $error = 'Judul materi harus diisi';
        } else {
            // Verify subject belongs to guru
            $stmt = $db->prepare("SELECT id FROM subjects WHERE id = ? AND guru_id = ? LIMIT 1");
            $stmt->bind_param("ii", $subjectId, $guruId);
            $stmt->execute();
            $r = $stmt->get_result();
            if (!$r || $r->num_rows === 0) {
                $stmt->close();
                $error = 'Mata pelajaran tidak valid atau tidak berada di bawah pengajaran Anda.';
            } else {
                $stmt->close();

                $fileId = null;

                // Handle file upload (foto/dokumen)
                if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                    $uploadResult = uploadFile($_FILES['file'], 'materials/');
                    if ($uploadResult['success']) {
                        $fileId = (int)($uploadResult['file_id'] ?? 0);
                    } else {
                        $error = $uploadResult['message'] ?? 'Gagal upload file';
                    }
                }

                // Validasi: minimal harus ada konten, file, atau video
                if (empty($konten) && empty($fileId) && empty($videoLink)) {
                    $error = 'Minimal harus ada teks, file, atau video';
                }

                if (empty($error)) {
                    // Insert material — dua jalur jelas: dengan file_id atau tanpa
                    if ($fileId !== null && $fileId > 0) {
                        $stmtIns = $db->prepare("INSERT INTO materials (subject_id, judul, konten, file_id, video_link, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                        if (!$stmtIns) {
                            $error = 'Gagal menyiapkan penyimpanan materi (1).';
                        } else {
                            // types: i (subject), s (judul), s (konten), i (file_id), s (video_link), i (created_by)
                            $stmtIns->bind_param("issisi", $subjectId, $judul, $konten, $fileId, $videoLink, $guruId);
                            if ($stmtIns->execute()) {
                                $materialId = $db->insert_id;
                            } else {
                                $error = 'Gagal menyimpan materi: ' . $stmtIns->error;
                            }
                            $stmtIns->close();
                        }
                    } else {
                        $stmtIns = $db->prepare("INSERT INTO materials (subject_id, judul, konten, file_id, video_link, created_by, created_at) VALUES (?, ?, ?, NULL, ?, ?, NOW())");
                        if (!$stmtIns) {
                            $error = 'Gagal menyiapkan penyimpanan materi (2).';
                        } else {
                            // types: i (subject), s (judul), s (konten), s (video_link), i (created_by)
                            $stmtIns->bind_param("isssi", $subjectId, $judul, $konten, $videoLink, $guruId);
                            if ($stmtIns->execute()) {
                                $materialId = $db->insert_id;
                            } else {
                                $error = 'Gagal menyimpan materi: ' . $stmtIns->error;
                            }
                            $stmtIns->close();
                        }
                    }

                    // Jika insert sukses, buat notifikasi untuk murid di kelas
                    if (empty($error) && !empty($materialId)) {
                        $stmtN = $db->prepare("
                            SELECT cu.user_id
                            FROM class_user cu
                            JOIN subjects s ON cu.class_id = s.class_id
                            WHERE s.id = ?
                        ");
                        if ($stmtN) {
                            $stmtN->bind_param("i", $subjectId);
                            $stmtN->execute();
                            $rN = $stmtN->get_result();
                            while ($row = $rN->fetch_assoc()) {
                                $studentId = (int)$row['user_id'];
                                // createNotification akan insert ke notifications dengan prepared stmt
                                createNotification(
                                    $studentId,
                                    'Materi Baru',
                                    'Materi baru: ' . $judul,
                                    '/web_MG/materials/view.php?id=' . $materialId
                                );
                            }
                            $stmtN->close();
                        }
                        // Redirect to list with success
                        header('Location: /web_MG/materials/list.php?success=1');
                        exit;
                    }
                }
            }
        }
    }
}

// Page render
$pageTitle = 'Tambah Materi Pembelajaran';
include __DIR__ . '/../inc/header.php';
?>

<style>
.upload-section {
    background: white;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.upload-option {
    border: 2px dashed var(--gray-300);
    border-radius: 8px;
    padding: 24px;
    text-align: center;
    margin-bottom: 16px;
    transition: all 0.3s;
    cursor: pointer;
}
.upload-option:hover {
    border-color: var(--primary);
    background: var(--gray-50);
}
.upload-option.active {
    border-color: var(--primary);
    background: #eff6ff;
}
.upload-icon {
    font-size: 48px;
    margin-bottom: 12px;
}
.upload-label {
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 8px;
}
.upload-desc {
    font-size: 14px;
    color: var(--gray-500);
}
</style>

<div class="container">
    <div class="page-header">
        <h1>Tambah Materi Pembelajaran</h1>
        <a href="/web_MG/materials/list.php" class="btn btn-secondary">← Kembali</a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo sanitize($success); ?></div>
    <?php endif; ?>

    <?php if (empty($subjects)): ?>
        <div class="card">
            <p>Anda belum memiliki mata pelajaran.</p>
            <div style="margin-top: 20px;">
                <a href="/web_MG/subjects/create.php" class="btn btn-primary">+ Buat Mata Pelajaran Baru</a>
                <a href="/web_MG/subjects/list.php" class="btn btn-secondary">Lihat Mata Pelajaran Saya</a>
            </div>
        </div>
    <?php else: ?>
        <form method="POST" action="" enctype="multipart/form-data" id="materialForm">
            <?php $csrf = generateCsrfToken('create_material'); ?>
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf); ?>">

            <!-- Pilih Mata Pelajaran -->
            <div class="card">
                <div class="form-group">
                    <label for="subject_id">Mata Pelajaran *</label>
                    <select id="subject_id" name="subject_id" required style="width: 100%; padding: 12px;">
                        <option value="">Pilih Mata Pelajaran</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo (int)$subject['id']; ?>"
                                <?php echo (isset($_POST['subject_id']) && $_POST['subject_id'] == $subject['id']) ? 'selected' : ''; ?>>
                                <?php echo sanitize($subject['nama_mapel']); ?> - <?php echo sanitize($subject['nama_kelas']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="judul">Judul Materi *</label>
                    <input
                        type="text"
                        id="judul"
                        name="judul"
                        required
                        placeholder="Contoh: Materi Aljabar, Sejarah Indonesia, dll"
                        value="<?php echo isset($_POST['judul']) ? sanitize($_POST['judul']) : ''; ?>"
                    >
                </div>
            </div>

            <!-- Upload Options -->
            <div class="upload-section">
                <h3 style="margin-bottom: 20px;">Pilih Jenis Konten</h3>

                <!-- Upload Foto/Dokumen -->
                <div class="upload-option" onclick="document.getElementById('file').click()">
                    <div class="upload-icon">📷</div>
                    <div class="upload-label">Upload Foto / Dokumen</div>
                    <div class="upload-desc">Upload file PDF, DOC, DOCX, JPG, PNG, atau ZIP</div>
                    <input
                        type="file"
                        id="file"
                        name="file"
                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip"
                        style="display: none;"
                        onchange="handleFileSelect(this)"
                    >
                    <div id="fileInfo" style="margin-top: 12px; color: var(--primary); font-weight: 500; display: none;"></div>
                </div>

                <!-- Upload Teks -->
                <div class="upload-option" onclick="document.getElementById('konten').focus()">
                    <div class="upload-icon">📝</div>
                    <div class="upload-label">Tulis Teks</div>
                    <div class="upload-desc">Tuliskan konten materi dalam bentuk teks</div>
                </div>
                <div class="form-group" style="margin-top: 16px;">
                    <textarea
                        id="konten"
                        name="konten"
                        placeholder="Tuliskan konten materi di sini..."
                        rows="8"
                        style="width: 100%;"
                    ><?php echo isset($_POST['konten']) ? sanitize($_POST['konten']) : ''; ?></textarea>
                </div>

                <!-- Upload Video -->
                <div class="upload-option" onclick="document.getElementById('video_link').focus()">
                    <div class="upload-icon">🎥</div>
                    <div class="upload-label">Link Video</div>
                    <div class="upload-desc">Masukkan link video YouTube atau platform lainnya</div>
                </div>
                <div class="form-group" style="margin-top: 16px;">
                    <input
                        type="url"
                        id="video_link"
                        name="video_link"
                        placeholder="https://youtube.com/watch?v=... atau link video lainnya"
                        value="<?php echo isset($_POST['video_link']) ? sanitize($_POST['video_link']) : ''; ?>"
                        style="width: 100%;"
                    >
                </div>
            </div>

            <div class="card">
                <p style="color: var(--gray-600); font-size: 14px; margin-bottom: 16px;">
                    <strong>Catatan:</strong> Minimal harus ada salah satu: File, Teks, atau Video
                </p>
                <button type="submit" class="btn btn-primary btn-block" style="font-size: 16px; padding: 16px;">
                    💾 Simpan Materi
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
function handleFileSelect(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const fileInfo = document.getElementById('fileInfo');
        const fileSize = (file.size / 1024 / 1024).toFixed(2);
        fileInfo.textContent = `File dipilih: ${file.name} (${fileSize} MB)`;
        fileInfo.style.display = 'block';
    }
}

// Highlight upload option when focused
document.getElementById('konten').addEventListener('focus', function() {
    this.parentElement.previousElementSibling.classList.add('active');
});
document.getElementById('konten').addEventListener('blur', function() {
    this.parentElement.previousElementSibling.classList.remove('active');
});
document.getElementById('video_link').addEventListener('focus', function() {
    this.parentElement.previousElementSibling.classList.add('active');
});
document.getElementById('video_link').addEventListener('blur', function() {
    this.parentElement.previousElementSibling.classList.remove('active');
});
</script>

<?php include __DIR__ . '/../inc/footer.php'; ?>
