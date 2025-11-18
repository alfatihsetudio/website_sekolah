<?php
// classes/import_excel.php  (sebenarnya import dari CSV)
require_once __DIR__ . '/../inc/auth.php';
requireRole(['admin']); // hanya admin boleh import

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

$db       = getDB();
$baseUrl  = rtrim(BASE_URL, '/\\');
$userId   = (int)($_SESSION['user_id'] ?? 0);
$schoolId = getCurrentSchoolId();

$pageTitle = 'Import Kelas dari CSV';
$errors    = [];
$report    = null;

if ($schoolId <= 0) {
    $errors[] = 'School ID tidak ditemukan. Silakan logout lalu login kembali sebagai admin.';
}

// helper: validasi no telp (boleh kosong, hanya angka, spasi, +, -)
function is_valid_phone($value) {
    $value = trim($value);
    if ($value === '') return true; // opsional
    return (bool)preg_match('/^[0-9+\s\-]+$/', $value);
}

// PROSES IMPORT CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {

    if (!isset($_FILES['kelas_file']) || $_FILES['kelas_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File CSV belum dipilih atau gagal di-upload.';
    } else {
        $tmpName  = $_FILES['kelas_file']['tmp_name'];
        $fileName = $_FILES['kelas_file']['name'] ?? '';
        $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($ext !== 'csv') {
            $errors[] = 'Format file harus .csv. Simpan file dari Excel sebagai "CSV (Comma delimited)".';
        } else {
            $fh = fopen($tmpName, 'r');
            if (!$fh) {
                $errors[] = 'Gagal membuka file CSV.';
            } else {

                // --- BACA SEMUA BARIS SEKALIGUS ---
                $rows = [];
                while (($row = fgetcsv($fh, 0, ',')) !== false) {
                    $rows[] = $row;
                }
                fclose($fh);

                if (empty($rows)) {
                    $errors[] = 'File CSV kosong atau tidak bisa dibaca.';
                } else {
                    $firstRow = $rows[0];

                    // Normalisasi baris pertama
                    $lowerFirst = [];
                    foreach ($firstRow as $v) {
                        $lowerFirst[] = strtolower(trim($v));
                    }

                    $expectedNames = ['jenjang','jurusan','guru_email','no_telpon_wali','nama_km','no_telpon_km'];

                    // Jika di baris pertama ada salah satu nama header → anggap sebagai HEADER
                    $hasHeaderRow = count(array_intersect($expectedNames, $lowerFirst)) > 0;

                    $headerMap = [];
                    $dataRows  = [];

                    if ($hasHeaderRow) {
                        // MODE 1: Ada header di baris pertama
                        foreach ($lowerFirst as $idx => $name) {
                            if ($name !== '') {
                                $headerMap[$name] = $idx;
                            }
                        }

                        // Header minimal: jenjang & jurusan
                        $requiredHeaders = ['jenjang','jurusan'];
                        foreach ($requiredHeaders as $h) {
                            if (!isset($headerMap[$h])) {
                                $errors[] = 'Header CSV tidak lengkap. Jika memakai header, minimal harus ada kolom "jenjang" dan "jurusan" di baris pertama.';
                                break;
                            }
                        }

                        // Data mulai baris kedua
                        $dataRows = array_slice($rows, 1);

                    } else {
                        // MODE 2: TANPA header → baris pertama langsung data
                        // Pemetaan berdasarkan POSISI kolom A–F
                        $headerMap = [
                            'jenjang'        => 0,
                            'jurusan'        => 1,
                            'guru_email'     => 2,
                            'no_telpon_wali' => 3,
                            'nama_km'        => 4,
                            'no_telpon_km'   => 5,
                        ];
                        $dataRows = $rows; // semua baris adalah data
                    }

                    if (empty($errors)) {
                        $processed = 0;
                        $inserted  = 0;
                        $skipped   = 0;
                        $rowErrors = [];

                        // Siapkan prepared statement untuk INSERT
                        $sqlInsert = "
                            INSERT INTO classes
                                (nama_kelas, level, jurusan, walimurid, no_telpon_wali,
                                 nama_km, no_telpon_km, guru_id, school_id, created_at)
                            VALUES
                                (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ";
                        $stmtIns = $db->prepare($sqlInsert);
                        if (!$stmtIns) {
                            $errors[] = 'Gagal menyiapkan query insert kelas: ' . $db->error;
                        } else {

                            foreach ($dataRows as $idx => $row) {
                                // Nomor baris sebenarnya di file
                                $lineNo = $hasHeaderRow ? ($idx + 2) : ($idx + 1);

                                // Pastikan cukup panjang
                                $maxIndex = max(array_values($headerMap));
                                for ($i = 0; $i <= $maxIndex; $i++) {
                                    if (!isset($row[$i])) {
                                        $row[$i] = '';
                                    }
                                }

                                // Ambil nilai per kolom
                                $jenjang    = isset($headerMap['jenjang'])        ? trim((string)$row[$headerMap['jenjang']])        : '';
                                $jurusan    = isset($headerMap['jurusan'])        ? trim((string)$row[$headerMap['jurusan']])        : '';
                                $guruEmail  = isset($headerMap['guru_email'])     ? trim((string)$row[$headerMap['guru_email']])     : '';
                                $noTelpWali = isset($headerMap['no_telpon_wali']) ? trim((string)$row[$headerMap['no_telpon_wali']]) : '';
                                $namaKM     = isset($headerMap['nama_km'])        ? trim((string)$row[$headerMap['nama_km']])        : '';
                                $noTelpKM   = isset($headerMap['no_telpon_km'])   ? trim((string)$row[$headerMap['no_telpon_km']])   : '';

                                // Cek baris benar-benar kosong → lewati
                                if (
                                    $jenjang === '' && $jurusan === '' && $guruEmail === '' &&
                                    $noTelpWali === '' && $namaKM === '' && $noTelpKM === ''
                                ) {
                                    continue;
                                }

                                $processed++;
                                $msg = [];

                                // VALIDASI WAJIB: jenjang & jurusan
                                if ($jenjang === '' || $jurusan === '') {
                                    $msg[] = 'Jenjang dan Jurusan wajib diisi.';
                                } else {
                                    // Jenjang harus angka murni (10, 11, 12, dst)
                                    if (!ctype_digit($jenjang)) {
                                        $msg[] = 'Jenjang harus berupa angka (mis. 10, 11, 12).';
                                    }
                                }

                                // VALIDASI: no telp (kalau diisi) hanya boleh angka, spasi, +, -
                                if (!is_valid_phone($noTelpWali)) {
                                    $msg[] = 'No. telp wali hanya boleh berisi angka, spasi, + atau - (tanpa huruf).';
                                }
                                if (!is_valid_phone($noTelpKM)) {
                                    $msg[] = 'No. telp KM hanya boleh berisi angka, spasi, + atau - (tanpa huruf).';
                                }

                                // guru_email sekarang OPSIONAL
                                $guruId = null;
                                if ($guruEmail !== '' && filter_var($guruEmail, FILTER_VALIDATE_EMAIL)) {
                                    $stmtG = $db->prepare("
                                        SELECT id 
                                        FROM users 
                                        WHERE email = ? AND role = 'guru' AND school_id = ?
                                        LIMIT 1
                                    ");
                                    if ($stmtG) {
                                        $stmtG->bind_param("si", $guruEmail, $schoolId);
                                        $stmtG->execute();
                                        $rG = $stmtG->get_result();
                                        if ($rG && $rG->num_rows === 1) {
                                            $gRow   = $rG->fetch_assoc();
                                            $guruId = (int)$gRow['id'];
                                        } else {
                                            // guru tidak ditemukan → biarkan NULL, TIDAK error
                                            $guruId = null;
                                        }
                                        $stmtG->close();
                                    } else {
                                        $msg[] = 'Gagal menyiapkan query pencarian guru.';
                                    }
                                }

                                // Bentuk nama_kelas & level
                                $namaKelas = trim($jenjang . ' ' . $jurusan);
                                $level     = $jenjang;

                                // Cek duplikat nama_kelas (boleh kamu matikan kalau mau)
                                if (empty($msg)) {
                                    $stmtC = $db->prepare("
                                        SELECT id 
                                        FROM classes 
                                        WHERE nama_kelas = ? AND school_id = ?
                                        LIMIT 1
                                    ");
                                    if ($stmtC) {
                                        $stmtC->bind_param("si", $namaKelas, $schoolId);
                                        $stmtC->execute();
                                        $rC = $stmtC->get_result();
                                        if ($rC && $rC->num_rows > 0) {
                                            $msg[] = 'Kelas dengan nama tersebut sudah ada.';
                                        }
                                        $stmtC->close();
                                    }
                                }

                                if (!empty($msg)) {
                                    $rowErrors[] = 'Baris ' . $lineNo . ': ' . implode(' ', $msg);
                                    $skipped++;
                                    continue;
                                }

                                $walimurid = ''; // masih kosong untuk sekarang

                                $stmtIns->bind_param(
                                    "sssssssii",
                                    $namaKelas,
                                    $level,
                                    $jurusan,
                                    $walimurid,
                                    $noTelpWali,
                                    $namaKM,
                                    $noTelpKM,
                                    $guruId,
                                    $schoolId
                                );

                                if ($stmtIns->execute()) {
                                    $inserted++;
                                } else {
                                    $rowErrors[] = 'Baris ' . $lineNo . ': gagal insert (' . $stmtIns->error . ')';
                                    $skipped++;
                                }
                            }

                            $stmtIns->close();

                            $report = [
                                'processed' => $processed,
                                'inserted'  => $inserted,
                                'skipped'   => $skipped,
                                'rowErrors' => $rowErrors,
                                'fileName'  => $fileName,
                                'hasHeader' => $hasHeaderRow,
                            ];
                        }
                    }
                }
            }
        }
    }
}

include __DIR__ . '/../inc/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Import Kelas dari CSV</h1>
        <a href="<?php echo $baseUrl; ?>/classes/list.php" class="btn btn-secondary">← Kembali ke Daftar Kelas</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $e): ?>
                <div><?php echo sanitize($e); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php if ($report): ?>
    <?php
        // Jika ada baris yang berhasil ditambah → hijau, kalau tidak ada sama sekali → merah
        $successImport = ($report['inserted'] > 0);

        $bgColor      = $successImport ? '#f0fdf4' : '#fef2f2';   // hijau muda / merah muda
        $borderColor  = $successImport ? '#bbf7d0' : '#fecaca';   // border lembut
        $leftBarColor = $successImport ? '#16a34a' : '#dc2626';   // garis kiri sedikit lebih kuat
    ?>
    <div class="card"
         style="
            margin-bottom:16px;
            padding:16px;
            border-radius:10px;
            background: <?php echo $bgColor; ?>;
            border:1px solid <?php echo $borderColor; ?>;
            border-left:4px solid <?php echo $leftBarColor; ?>;
         ">

        <h3 style="margin-top:0;">Hasil Import</h3>

        <p style="margin:0 0 10px 0;font-size:0.95rem;">
            File: <strong><?php echo sanitize($report['fileName']); ?></strong><br>
            Mode:
            <strong>
                <?php echo $report['hasHeader']
                    ? 'Dengan header di baris 1'
                    : 'Tanpa header (baris 1 langsung data)'; ?>
            </strong>
        </p>

        <!-- Ringkasan angka -->
        <div style="display:flex;flex-wrap:wrap;gap:16px;font-size:0.9rem;margin-bottom:8px;">
            <div style="min-width:140px;padding:8px 10px;border-radius:8px;background:#f9fafb;border:1px solid #e5e7eb;">
                <div style="font-size:0.8rem;color:#6b7280;">Baris diproses</div>
                <div style="font-size:1.1rem;font-weight:600;">
                    <?php echo (int)$report['processed']; ?>
                </div>
            </div>
            <div style="min-width:140px;padding:8px 10px;border-radius:8px;background:#ecfdf3;border:1px solid #bbf7d0;">
                <div style="font-size:0.8rem;color:#166534;">Berhasil ditambah</div>
                <div style="font-size:1.1rem;font-weight:600;color:#166534;">
                    <?php echo (int)$report['inserted']; ?>
                </div>
            </div>
            <div style="min-width:140px;padding:8px 10px;border-radius:8px;background:#fef2f2;border:1px solid #fecaca;">
                <div style="font-size:0.8rem;color:#b91c1c;">Dilewati / error</div>
                <div style="font-size:1.1rem;font-weight:600;color:#b91c1c;">
                    <?php echo (int)$report['skipped']; ?>
                </div>
            </div>
        </div>

        <?php if (!empty($report['rowErrors'])): ?>
            <!-- Pesan penting, selalu terlihat -->
            <div style="margin-top:6px;padding:12px 14px;border-radius:10px;background:#fef2f2;border:1px solid #fecaca;">
                <strong style="display:block;margin-bottom:4px;color:#b91c1c;">
                    Perhatian: ada baris yang TIDAK di-import.
                </strong>
                <p style="margin:4px 0 8px 0;font-size:0.88rem;color:#4b5563;">
                    Baris di bawah ini <u>di-skip oleh sistem</u>. Perbaiki datanya di file Excel/CSV
                    (misalnya jenjang harus angka, nomor telp tidak boleh huruf), lalu import ulang jika perlu.
                </p>
                <ol style="margin:0;padding-left:20px;font-size:0.88rem;color:#111827;">
                    <?php foreach ($report['rowErrors'] as $re): ?>
                        <li style="margin-bottom:6px;">
                            <strong>Problem:</strong>
                            <?php echo sanitize($re); ?><br>
                            <strong>Solusi:</strong>
                            Periksa dan perbaiki data pada baris tersebut di file CSV/Excel, lalu coba import ulang.
                        </li>
                    <?php endforeach; ?>
                </ol>

            </div>
        <?php else: ?>
            <div style="margin-top:6px;padding:10px 12px;border-radius:10px;background:#ecfdf3;border:1px solid #bbf7d0;font-size:0.9rem;color:#166534;">
                <strong>Semua baris berhasil di-import.</strong>
                Tidak ada baris yang di-skip.
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>



    <div class="card">
        <h3 style="margin-top:0;">Upload file CSV</h3>
        <p class="small text-muted">
            Kamu boleh:
            <br>• Memakai header di baris pertama (<code>jenjang,jurusan,guru_email,...</code>), <em>atau</em>
            <br>• Langsung isi data di baris pertama (tanpa header), seperti contoh di bawah.
            <br>File disimpan sebagai <strong>CSV (Comma delimited)</strong>.
        </p>
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label for="kelas_file">File CSV</label>
                <input type="file" name="kelas_file" id="kelas_file" accept=".csv" required>
            </div>

            <button type="submit" class="btn btn-primary">Import Kelas</button>
        </form>
    </div>

    <div class="card" style="margin-top:16px;">
        <h3 style="margin-top:0;">Contoh isi Excel (tanpa header)</h3>
        <p class="small text-muted">
            Contoh file seperti gambar yang kamu kirim:
        </p>

        <div style="overflow-x:auto; border:1px solid #ddd; background:white;">
            <table style="border-collapse:collapse; min-width:700px; font-family:Consolas, monospace;">
                <tr>
                    <th style="border:1px solid #ccc; padding:6px; background:#f8fafc;">A</th>
                    <th style="border:1px solid #ccc; padding:6px; background:#f8fafc;">B</th>
                    <th style="border:1px solid #ccc; padding:6px; background:#f8fafc;">C</th>
                    <th style="border:1px solid #ccc; padding:6px; background:#f8fafc;">D</th>
                    <th style="border:1px solid #ccc; padding:6px; background:#f8fafc;">E</th>
                    <th style="border:1px solid #ccc; padding:6px; background:#f8fafc;">F</th>
                </tr>
                <tr>
                    <td style="border:1px solid #ccc; padding:6px;">12</td>
                    <td style="border:1px solid #ccc; padding:6px;">RPL</td>
                    <td style="border:1px solid #ccc; padding:6px;">GURU@GMAIL.COM</td>
                    <td style="border:1px solid #ccc; padding:6px;">087867687</td>
                    <td style="border:1px solid #ccc; padding:6px;">FATIH</td>
                    <td style="border:1px solid #ccc; padding:6px;">0867867678</td>
                </tr>
            </table>
        </div>

        <p class="small text-muted" style="margin-top:6px;">
            Kolom:
            <br>A = jenjang, B = jurusan, C = guru_email (opsional), D = no_telpon_wali (opsional),
            E = nama_km (opsional), F = no_telpon_km (opsional).
        </p>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
