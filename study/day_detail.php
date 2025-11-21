<?php
// study/day_detail.php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireLogin();

$baseUrl = rtrim(BASE_URL, '/\\');
$userId  = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(403);
    echo "Unauthorized";
    exit;
}

// Ambil dan validasi tanggal (start default = tanggal halaman ini)
$dateParam = $_GET['date'] ?? date('Y-m-d');

// Validasi format Y-m-d
$d = DateTime::createFromFormat('Y-m-d', $dateParam);
if (!$d || $d->format('Y-m-d') !== $dateParam) {
    http_response_code(400);
    echo "Format tanggal tidak valid.";
    exit;
}

$selectedDate = $d->format('Y-m-d');
$year  = (int)$d->format('Y');
$month = (int)$d->format('n');
$day   = (int)$d->format('j');

$dayNames = [
    1 => 'Senin',
    2 => 'Selasa',
    3 => 'Rabu',
    4 => 'Kamis',
    5 => 'Jumat',
    6 => 'Sabtu',
    7 => 'Minggu',
];
$monthNames = [
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember',
];

$dayOfWeek = (int)$d->format('N'); // 1-7

$db = getDB();
$errorMsg = '';

// Handle submit event (tambah, edit, hapus satu, hapus semua)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Hapus satu jadwal
    if (isset($_POST['delete_event_id'])) {
        $deleteId = (int)($_POST['delete_event_id'] ?? 0);

        if ($deleteId > 0) {
            $stmt = $db->prepare("
                DELETE FROM calendar_events
                WHERE id = ? AND user_id = ? AND event_date = ?
            ");
            if ($stmt) {
                $stmt->bind_param("iis", $deleteId, $userId, $selectedDate);
                $stmt->execute();
                $stmt->close();
                header("Location: " . $baseUrl . "/study/day_detail.php?date=" . $selectedDate);
                exit;
            } else {
                $errorMsg = "Gagal menghapus jadwal.";
            }
        } else {
            $errorMsg = "ID jadwal tidak valid.";
        }

    // Hapus semua jadwal di hari ini
    } elseif (isset($_POST['delete_all'])) {

        $stmt = $db->prepare("
            DELETE FROM calendar_events
            WHERE user_id = ? AND event_date = ?
        ");
        if ($stmt) {
            $stmt->bind_param("is", $userId, $selectedDate);
            $stmt->execute();
            $stmt->close();
            header("Location: " . $baseUrl . "/study/day_detail.php?date=" . $selectedDate);
            exit;
        } else {
            $errorMsg = "Gagal menghapus semua jadwal.";
        }

    // Edit jadwal (1 tanggal saja)
    } elseif (!empty($_POST['edit_event_id'])) {

        $editId = (int)($_POST['edit_event_id'] ?? 0);
        $type   = $_POST['event_type'] ?? '';
        $title  = trim($_POST['title'] ?? '');
        $note   = trim($_POST['note'] ?? '');

        $allowedTypes = ['holiday', 'special', 'warning'];

        if ($editId <= 0) {
            $errorMsg = "ID jadwal untuk edit tidak valid.";
        } elseif (!in_array($type, $allowedTypes, true)) {
            $errorMsg = "Tipe jadwal tidak valid.";
        } elseif ($title === '') {
            $errorMsg = "Judul tidak boleh kosong.";
        } else {
            $stmt = $db->prepare("
                UPDATE calendar_events
                SET type = ?, title = ?, note = ?
                WHERE id = ? AND user_id = ? AND event_date = ?
            ");
            if ($stmt) {
                $stmt->bind_param("sssiis", $type, $title, $note, $editId, $userId, $selectedDate);
                $stmt->execute();
                $stmt->close();
                header("Location: " . $baseUrl . "/study/day_detail.php?date=" . $selectedDate);
                exit;
            } else {
                $errorMsg = "Gagal mengubah jadwal.";
            }
        }

    // Tambah jadwal baru (bisa beberapa tanggal sekaligus)
    } else {

        $type      = $_POST['event_type'] ?? '';
        $title     = trim($_POST['title'] ?? '');
        $note      = trim($_POST['note'] ?? '');
        $endRaw    = trim($_POST['end_date'] ?? '');

        $allowedTypes = ['holiday', 'special', 'warning'];

        if (!in_array($type, $allowedTypes, true)) {
            $errorMsg = "Tipe jadwal tidak valid. Pilih salah satu kategori warna.";
        } elseif ($title === '') {
            $errorMsg = "Judul tidak boleh kosong.";
        } else {

            // Tentukan daftar tanggal yang akan dibuat
            $datesToInsert = [];
            $startDate     = $selectedDate;

            if ($endRaw !== '') {
                $endObj = DateTime::createFromFormat('Y-m-d', $endRaw);
                if ($endObj && $endObj->format('Y-m-d') >= $startDate) {
                    $cur = new DateTime($startDate);
                    $end = $endObj;
                    while ($cur <= $end) {
                        $datesToInsert[] = $cur->format('Y-m-d');
                        $cur->modify('+1 day');
                    }
                } else {
                    // Jika end date tidak valid / lebih kecil, fallback 1 hari
                    $datesToInsert[] = $startDate;
                }
            } else {
                $datesToInsert[] = $startDate;
            }

            $stmt = $db->prepare("
                INSERT INTO calendar_events (user_id, event_date, type, title, note)
                VALUES (?, ?, ?, ?, ?)
            ");

            if ($stmt) {
                foreach ($datesToInsert as $eventDate) {
                    $stmt->bind_param("issss", $userId, $eventDate, $type, $title, $note);
                    $stmt->execute();
                }
                $stmt->close();
                header("Location: " . $baseUrl . "/study/day_detail.php?date=" . $selectedDate);
                exit;
            } else {
                $errorMsg = "Gagal menyimpan jadwal.";
            }
        }
    }
}

// Ambil semua event di tanggal ini untuk user ini
$events = [];
$stmt = $db->prepare("
    SELECT id, type, title, note, created_at
    FROM calendar_events
    WHERE user_id = ? AND event_date = ?
    ORDER BY created_at ASC, id ASC
");
if ($stmt) {
    $stmt->bind_param("is", $userId, $selectedDate);
    $stmt->execute();
    $res = $stmt->get_result();
    $events = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}

$pageTitle = 'Detail Hari ' . $selectedDate;
$backUrl   = $baseUrl . '/study/calendar.php?year=' . $year;

include __DIR__ . '/../inc/header.php';
?>

<style>
.day-page-header {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    margin-bottom:16px;
    flex-wrap:wrap;
}
.day-page-title {
    font-size:1.35rem;
    font-weight:600;
}
.day-page-sub {
    font-size:0.9rem;
    color:#6b7280;
}
.day-page-date {
    font-size:0.95rem;
    font-weight:500;
    color:#111827;
}

/* PILIH TIPE JADWAL (WARNA) */
.day-shortcuts {
    display:flex;
    flex-direction:column;
    gap:8px;
    margin-bottom:10px;
}
.shortcut-btn {
    border-radius:12px;
    border:1px solid #e5e7eb;
    padding:8px 10px;
    font-size:0.85rem;
    cursor:pointer;
    color:#111827;
    display:flex;
    align-items:center;
    gap:8px;
    background:#f9fafb;
    width:100%;
    text-align:left;
    transition:0.15s ease;
}
.shortcut-btn:hover {
    background:#f3f4f6;
}
.shortcut-btn-active {
    border-color:#2563eb;
    box-shadow:0 0 0 1px rgba(37,99,235,0.15);
    background:#eff6ff;
}
.shortcut-dot {
    width:14px;
    height:14px;
    border-radius:999px;
}
.shortcut-main-label {
    font-weight:600;
    font-size:0.9rem;
}
.shortcut-sub-label {
    font-size:0.78rem;
    color:#6b7280;
}

/* LIBUR – hijau lembut */
.shortcut-holiday .shortcut-dot {
    background:#81C784;
}
/* ACARA KHUSUS – kuning */
.shortcut-special .shortcut-dot {
    background:#FBC02D;
}
/* PERINGATAN – merah */
.shortcut-warning .shortcut-dot {
    background:#E53935;
}

/* form */
.day-form-wrap {
    border-radius:12px;
    border:1px solid #e5e7eb;
    background:#ffffff;
    padding:12px 14px;
    margin-bottom:16px;
    display:none;
}
.day-form-header {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:8px;
    margin-bottom:8px;
}
.day-form-title {
    font-size:0.95rem;
    font-weight:600;
}
.day-form-type-label {
    font-size:0.85rem;
    color:#6b7280;
}
.day-form-body .form-group {
    margin-bottom:8px;
}
.day-form-body label {
    font-size:0.83rem;
    color:#4b5563;
    display:block;
    margin-bottom:2px;
}
.day-form-body input[type="text"],
.day-form-body textarea,
.day-form-body input[type="date"] {
    width:100%;
    border-radius:8px;
    border:1px solid #d1d5db;
    padding:6px 8px;
    font-size:0.85rem;
}
.day-form-body textarea {
    min-height:70px;
    resize:vertical;
}

/* range tanggal */
.date-range-info {
    font-size:0.8rem;
    color:#4b5563;
    margin-bottom:4px;
}
.date-range-row {
    display:flex;
    flex-wrap:wrap;
    align-items:center;
    gap:8px;
    font-size:0.8rem;
    color:#4b5563;
}
.date-range-toggle-label {
    display:inline-flex;
    align-items:center;
    gap:4px;
    cursor:pointer;
}
#endDateWrap {
    margin-top:6px;
}
.date-range-hint {
    font-size:0.78rem;
    color:#6b7280;
    margin-top:2px;
}

/* event list */
.day-events-card {
    border-radius:12px;
    border:1px solid #e5e7eb;
    background:#ffffff;
    padding:10px 12px;
}
.day-events-empty {
    font-size:0.9rem;
    color:#6b7280;
}
.day-event-item {
    display:flex;
    gap:8px;
    padding:6px 0;
    border-bottom:1px solid #f3f4f6;
}
.day-event-item:last-child {
    border-bottom:none;
}
.day-event-badge {
    width:6px;
    border-radius:999px;
    margin-top:3px;
}

/* warna badge disamakan dengan kalender utama */
.badge-holiday {
    background:#81C784;
}
.badge-special {
    background:#FBC02D;
}
.badge-warning {
    background:#E53935;
}

.day-event-main {
    flex:1;
}
.day-event-header {
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:8px;
}
.day-event-title {
    font-size:0.9rem;
    font-weight:600;
    margin-bottom:2px;
}
.day-event-note {
    font-size:0.8rem;
    color:#4b5563;
}
.day-event-meta {
    font-size:0.75rem;
    color:#9ca3af;
    margin-top:2px;
}

.day-event-actions {
    display:flex;
    flex-direction:column;
    gap:4px;
    margin-left:4px;
}

.day-event-delete-form {
    margin:0;
}
.day-event-delete-btn {
    border:none;
    padding:2px 8px;
    font-size:0.75rem;
    border-radius:999px;
    background:#fee2e2;
    color:#b91c1c;
    cursor:pointer;
    white-space:nowrap;
}
.day-event-delete-btn:hover {
    background:#fecaca;
}

.day-event-edit-btn {
    border:none;
    padding:2px 8px;
    font-size:0.75rem;
    border-radius:999px;
    background:#e0f2fe;
    color:#0369a1;
    cursor:pointer;
    white-space:nowrap;
}
.day-event-edit-btn:hover {
    background:#bae6fd;
}

.day-alert {
    font-size:0.83rem;
    color:#b91c1c;
    margin-bottom:10px;
}
</style>

<div class="container" style="max-width:800px;margin:0 auto;">

    <div class="day-page-header">
        <div>
            <div class="day-page-title">
                Detail Hari
            </div>
            <div class="day-page-date">
                <?php echo $dayNames[$dayOfWeek] . ', ' . $day . ' ' . $monthNames[$month] . ' ' . $year; ?>
            </div>
            <div class="day-page-sub">
                Atur jadwal libur, acara khusus, dan peringatan untuk tanggal ini atau rentang beberapa hari.
            </div>
        </div>
        <a href="<?php echo htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8'); ?>"
           class="btn btn-secondary" style="font-size:0.85rem;">
            ← Kembali ke kalender
        </a>
    </div>

    <?php if (!empty($errorMsg)): ?>
        <div class="day-alert">
            <?php echo sanitize($errorMsg); ?>
        </div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:12px;">
        <div style="font-size:0.9rem;font-weight:500;margin-bottom:4px;">
            Tambah / ubah jadwal
        </div>
        <div style="font-size:0.82rem;color:#6b7280;margin-bottom:6px;">
            Langkah 1: pilih jenis jadwal (warna) di bawah ini untuk mulai menambahkan. Kamu juga bisa klik warna lain saat edit untuk mengganti jenisnya.
        </div>
        <div class="day-shortcuts">
            <button type="button"
                    class="shortcut-btn shortcut-holiday"
                    data-type="holiday"
                    data-label="Libur">
                <span class="shortcut-dot"></span>
                <div>
                    <div class="shortcut-main-label">Libur</div>
                    <div class="shortcut-sub-label">Hari libur sekolah, libur nasional, atau istirahat pribadi.</div>
                </div>
            </button>

            <button type="button"
                    class="shortcut-btn shortcut-special"
                    data-type="special"
                    data-label="Acara khusus">
                <span class="shortcut-dot"></span>
                <div>
                    <div class="shortcut-main-label">Acara khusus</div>
                    <div class="shortcut-sub-label">Pernikahan, ulang tahun, reuni, atau kegiatan penting lainnya.</div>
                </div>
            </button>

            <button type="button"
                    class="shortcut-btn shortcut-warning"
                    data-type="warning"
                    data-label="Peringatan">
                <span class="shortcut-dot"></span>
                <div>
                    <div class="shortcut-main-label">Peringatan</div>
                    <div class="shortcut-sub-label">Deadline tugas, ujian, dan pengingat penting lain.</div>
                </div>
            </button>
        </div>
    </div>

    <div id="dayFormWrap" class="day-form-wrap">
        <div class="day-form-header">
            <div class="day-form-title">Tambah jadwal</div>
            <div class="day-form-type-label">
                <span id="dayFormTypeLabel">Belum ada tipe dipilih</span>
            </div>
        </div>

        <form method="post">
            <input type="hidden" name="event_type" id="eventTypeInput" value="">
            <input type="hidden" name="edit_event_id" id="editEventIdInput" value="">
            <div class="day-form-body">

                <div class="form-group">
                    <div class="date-range-info">
                        Tanggal awal: <strong><?php echo $day . ' ' . $monthNames[$month] . ' ' . $year; ?></strong>
                    </div>
                    <div class="date-range-row">
                        <span>Secara default jadwal hanya untuk tanggal ini.</span>
                        <label class="date-range-toggle-label">
                            <input type="checkbox" id="multiDayToggle">
                            <span>Acara berlangsung beberapa hari</span>
                        </label>
                    </div>
                    <div id="endDateWrap" style="display:none;">
                        <label for="endDateInput">Sampai tanggal</label>
                        <input type="date"
                               name="end_date"
                               id="endDateInput"
                               min="<?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>"
                               value="<?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="date-range-hint">
                            Jadwal akan dibuat untuk setiap tanggal dari awal sampai tanggal ini.
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="titleInput">Judul</label>
                    <input type="text" name="title" id="titleInput" placeholder="Misal: Libur sekolah / Acara pernikahan" required>
                </div>
                <div class="form-group">
                    <label for="noteInput">Catatan (opsional)</label>
                    <textarea name="note" id="noteInput" placeholder="Detail tambahan, lokasi, jam, dsb."></textarea>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:6px;margin-top:4px;">
                    <button type="button" class="btn btn-secondary" onclick="hideDayForm()">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-primary" id="dayFormSubmitBtn">
                        Simpan
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="day-events-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <h2 style="margin:0;font-size:1rem;">Jadwal di tanggal ini</h2>
            <?php if (!empty($events)): ?>
                <form method="post"
                      onsubmit="return confirm('Yakin ingin menghapus SEMUA jadwal di tanggal ini?');"
                      style="margin:0;">
                    <input type="hidden" name="delete_all" value="1">
                    <button type="submit" class="btn btn-danger" style="font-size:0.75rem;">
                        Hapus semua
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (empty($events)): ?>
            <div class="day-events-empty">
                Belum ada jadwal untuk tanggal ini. Pilih salah satu kategori jadwal di atas untuk menambahkan.
            </div>
        <?php else: ?>
            <?php foreach ($events as $e): ?>
                <?php
                $badgeClass = 'badge-special';
                if ($e['type'] === 'holiday')  $badgeClass = 'badge-holiday';
                if ($e['type'] === 'warning')  $badgeClass = 'badge-warning';

                $typeLabel = [
                    'holiday' => 'Libur',
                    'special' => 'Acara khusus',
                    'warning' => 'Peringatan',
                ][$e['type']] ?? $e['type'];

                $noteRaw  = $e['note'] ?? '';
                $noteAttr = str_replace(["\r", "\n"], [' ', ' '], $noteRaw);
                ?>
                <div class="day-event-item">
                    <div class="day-event-badge <?php echo $badgeClass; ?>"></div>
                    <div class="day-event-main">
                        <div class="day-event-header">
                            <div class="day-event-title">
                                <?php echo sanitize($e['title']); ?>
                            </div>
                            <div class="day-event-actions">
                                <button type="button"
                                        class="day-event-edit-btn"
                                        data-id="<?php echo (int)$e['id']; ?>"
                                        data-type="<?php echo htmlspecialchars($e['type'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-title="<?php echo htmlspecialchars($e['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-note="<?php echo htmlspecialchars($noteAttr, ENT_QUOTES, 'UTF-8'); ?>">
                                    Edit
                                </button>
                                <form method="post"
                                      class="day-event-delete-form"
                                      onsubmit="return confirm('Yakin ingin menghapus jadwal ini?');">
                                    <input type="hidden" name="delete_event_id" value="<?php echo (int)$e['id']; ?>">
                                    <button type="submit" class="day-event-delete-btn">
                                        Hapus
                                    </button>
                                </form>
                            </div>
                        </div>

                        <?php if (!empty($e['note'])): ?>
                            <div class="day-event-note">
                                <?php echo nl2br(sanitize($e['note'])); ?>
                            </div>
                        <?php endif; ?>
                        <div class="day-event-meta">
                            <?php echo sanitize($typeLabel); ?> •
                            dibuat <?php echo sanitize($e['created_at']); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<script>
(function() {
    const typeButtons  = document.querySelectorAll('.shortcut-btn');
    const formWrap     = document.getElementById('dayFormWrap');
    const typeInput    = document.getElementById('eventTypeInput');
    const typeLabel    = document.getElementById('dayFormTypeLabel');
    const titleInput   = document.getElementById('titleInput');
    const noteInput    = document.getElementById('noteInput');
    const editIdInput  = document.getElementById('editEventIdInput');
    const formTitleEl  = document.querySelector('.day-form-title');
    const submitBtn    = document.getElementById('dayFormSubmitBtn');
    const editButtons  = document.querySelectorAll('.day-event-edit-btn');

    const multiToggle  = document.getElementById('multiDayToggle');
    const endDateWrap  = document.getElementById('endDateWrap');
    const endDateInput = document.getElementById('endDateInput');

    function setPlaceholderByType(type) {
        if (type === 'holiday') {
            titleInput.placeholder = 'Misal: Libur nasional / Libur sekolah';
        } else if (type === 'special') {
            titleInput.placeholder = 'Misal: Acara pernikahan / Ulang tahun / Reuni';
        } else if (type === 'warning') {
            titleInput.placeholder = 'Misal: Deadline tugas / Ujian / Pengingat penting';
        } else {
            titleInput.placeholder = 'Misal: Jadwal khusus';
        }
    }

    function setActiveType(type, labelText) {
        typeButtons.forEach(btn => {
            if (btn.getAttribute('data-type') === type) {
                btn.classList.add('shortcut-btn-active');
            } else {
                btn.classList.remove('shortcut-btn-active');
            }
        });
        typeInput.value = type;
        typeLabel.textContent = 'Tipe: ' + labelText;
        setPlaceholderByType(type);
    }

    // Klik tipe (warna) => buka form tambah dengan tipe tersebut
    typeButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const type  = btn.getAttribute('data-type');
            const label = btn.getAttribute('data-label');

            editIdInput.value = '';
            formTitleEl.textContent = 'Tambah jadwal';
            submitBtn.textContent   = 'Simpan';

            // reset range multi-day
            if (multiToggle) {
                multiToggle.checked = false;
            }
            if (endDateWrap) {
                endDateWrap.style.display = 'none';
            }
            if (endDateInput) {
                endDateInput.value = endDateInput.getAttribute('min') || '';
            }

            setActiveType(type, label);

            // reset isi form kalau form sedang tidak terlihat
            if (!formWrap.style.display || formWrap.style.display === 'none') {
                titleInput.value = '';
                noteInput.value  = '';
            }

            formWrap.style.display = 'block';
            titleInput.focus();
        });
    });

    // Mode edit dari tombol "Edit" di jadwal
    editButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const id    = btn.getAttribute('data-id');
            const type  = btn.getAttribute('data-type');
            const title = btn.getAttribute('data-title') || '';
            const note  = btn.getAttribute('data-note') || '';

            editIdInput.value = id;
            formTitleEl.textContent = 'Edit jadwal';
            submitBtn.textContent   = 'Simpan perubahan';

            let label = 'Jadwal';
            if (type === 'holiday')      label = 'Libur';
            else if (type === 'special') label = 'Acara khusus';
            else if (type === 'warning') label = 'Peringatan';

            // Saat edit, matikan multi-day (edit hanya untuk tanggal ini)
            if (multiToggle) {
                multiToggle.checked = false;
            }
            if (endDateWrap) {
                endDateWrap.style.display = 'none';
            }
            if (endDateInput) {
                endDateInput.value = endDateInput.getAttribute('min') || '';
            }

            setActiveType(type, label);

            titleInput.value = title;
            noteInput.value  = note;

            formWrap.style.display = 'block';
            titleInput.focus();
        });
    });

    // Toggle multi-day
    if (multiToggle && endDateWrap) {
        multiToggle.addEventListener('change', () => {
            if (multiToggle.checked) {
                endDateWrap.style.display = 'block';
                if (endDateInput && !endDateInput.value) {
                    endDateInput.value = endDateInput.getAttribute('min') || '';
                }
            } else {
                endDateWrap.style.display = 'none';
                if (endDateInput) {
                    endDateInput.value = endDateInput.getAttribute('min') || '';
                }
            }
        });
    }

    window.hideDayForm = function() {
        formWrap.style.display = 'none';
        // hilangkan highlight tipe ketika form ditutup
        typeButtons.forEach(btn => btn.classList.remove('shortcut-btn-active'));
        typeInput.value = '';
        typeLabel.textContent = 'Belum ada tipe dipilih';
    };
})();
</script>

<?php include __DIR__ . '/../inc/footer.php'; ?>
