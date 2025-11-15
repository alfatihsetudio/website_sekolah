<?php
/**
 * Dashboard Guru (FULL REWRITE) - Diperbarui: tambah tombol "Tambah Materi" & "Tambah Tugas" dari Quick Selector
 */
require_once __DIR__ . '/../inc/auth.php';
requireRole(['guru']);

$db = getDB();
$guruId = $_SESSION['user_id'];

$pageTitle = "Dashboard Guru";

// ======================
// 1. NOTIFIKASI TERBARU
// ======================
$sql = "SELECT * FROM notifications 
        WHERE user_id = $guruId 
        ORDER BY created_at DESC 
        LIMIT 10";
$notifs = $db->query($sql)->fetch_all(MYSQLI_ASSOC);

// ======================
// 2. TUGAS AKTIF
// ======================
$sql = "SELECT a.*, s.nama_mapel, c.nama_kelas
        FROM assignments a
        JOIN subjects s ON a.subject_id = s.id
        JOIN classes c ON s.class_id = c.id
        WHERE a.created_by = $guruId
        AND (a.deadline IS NULL OR a.deadline >= NOW())
        ORDER BY a.deadline ASC
        LIMIT 6";
$tugasAktif = $db->query($sql)->fetch_all(MYSQLI_ASSOC);

// ============================
// 3. MENUNGGU PENILAIAN
// ============================
$sql = "SELECT s.*, u.nama as nama_murid, a.judul as judul_tugas, s.assignment_id
        FROM submissions s
        JOIN users u ON s.student_id = u.id
        JOIN assignments a ON s.assignment_id = a.id
        WHERE a.created_by = $guruId
        AND s.nilai IS NULL
        ORDER BY s.submitted_at ASC
        LIMIT 6";
$menungguNilai = $db->query($sql)->fetch_all(MYSQLI_ASSOC);

// ======================
// 4. JADWAL BERLANGSUNG
// ======================
$nowDay = strtolower(date('D')); // e.g. Mon, Tue -> make consistent with stored day values
$nowTime = date('H:i:s');

$sql = "SELECT ts.*, c.nama_kelas, s.nama_mapel
        FROM teaching_schedule ts
        JOIN classes c ON ts.class_id = c.id
        JOIN subjects s ON ts.subject_id = s.id
        WHERE ts.guru_id = $guruId
        AND ts.day_of_week = '$nowDay'
        AND ts.start_time <= '$nowTime'
        AND ts.end_time >= '$nowTime'
        LIMIT 1";
$sedangNgajar = $db->query($sql)->fetch_assoc();

// ======================
// 5. JADWAL SELANJUTNYA
// ======================
// Note: simple ordering by day_of_week string — assumes values 'mon','tue',...
$sql = "SELECT ts.*, c.nama_kelas, s.nama_mapel
        FROM teaching_schedule ts
        JOIN classes c ON ts.class_id = c.id
        JOIN subjects s ON ts.subject_id = s.id
        WHERE ts.guru_id = $guruId
        AND (
            ts.day_of_week > '$nowDay'
            OR (ts.day_of_week = '$nowDay' AND ts.start_time > '$nowTime')
        )
        ORDER BY FIELD(day_of_week, 'mon','tue','wed','thu','fri','sat','sun'), start_time
        LIMIT 1";
$nextNgajar = $db->query($sql)->fetch_assoc();

// ======================
// 6. HISTORI MENGAJAR (5 terbaru)
// ======================
$sql = "SELECT tl.*, c.nama_kelas, s.nama_mapel
        FROM teaching_logs tl
        JOIN classes c ON tl.class_id = c.id
        JOIN subjects s ON tl.subject_id = s.id
        WHERE tl.guru_id = $guruId
        ORDER BY tl.tanggal DESC, tl.created_at DESC
        LIMIT 5";
$histori = $db->query($sql)->fetch_all(MYSQLI_ASSOC);

// ======================
// 7. QUICK SELECTOR
// ======================
$sql = "SELECT DISTINCT c.id, c.nama_kelas
        FROM classes c
        JOIN subjects s ON s.class_id = c.id
        WHERE s.guru_id = $guruId
        ORDER BY c.nama_kelas";
$kelasGuru = $db->query($sql)->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../inc/header.php';
?>

<style>
.section-title {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 12px;
}
.card {
    background:white;
    padding:16px;
    border-radius:12px;
    margin-bottom:16px;
    box-shadow:0 2px 4px rgba(0,0,0,0.05);
}
.grid-2 {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
    gap:12px;
}
.list-item {
    padding:12px 0;
    border-bottom:1px solid #eee;
}
.badge-deadline {
    padding:2px 8px;
    font-size:12px;
    border-radius:6px;
}
.badge-ok { background:#e0f7ec; color:#0a8f4d; }
.badge-warning { background:#fff4e0; color:#b26a00; }
.badge-danger { background:#ffe0e0; color:#c00; }

/* Quick selector layout */
.quick-row { display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-top:8px; }
.quick-row select { padding:8px; border-radius:8px; border:1px solid #ddd; min-width:160px; }
.quick-row .qs-actions { display:flex; gap:8px; flex-wrap:wrap; }
.btn { padding:8px 12px; border-radius:8px; text-decoration:none; display:inline-block; border: none; cursor:pointer; }
.btn-primary { background:#2563eb; color:#fff; }
.btn-secondary { background:#f3f4f6; color:#111; border:1px solid #e5e7eb; }
.btn-success { background:#16a34a; color:#fff; }
.btn-disabled { background:#e5e7eb; color:#9ca3af; cursor:not-allowed; }
.small-muted { color:#888; font-size:13px; }
</style>

<div class="container">

    <!-- ============ QUICK SELECTOR ============ -->
    <div class="card">
        <h2 class="section-title">Akses Cepat</h2>

        <div>
            <label for="qs_class">Kelas</label><br>
            <select name="class_id" id="qs_class" aria-label="Pilih kelas">
                <option value="">Pilih kelas</option>
                <?php foreach ($kelasGuru as $k): ?>
                    <option value="<?= (int)$k['id']; ?>"><?= sanitize($k['nama_kelas']); ?></option>
                <?php endforeach; ?>
            </select>

            <label for="qs_mapel" style="margin-left:12px;">Mata Pelajaran</label><br>
            <select name="subject_id" id="qs_mapel" aria-label="Pilih mata pelajaran">
                <option value="">Pilih kelas terlebih dahulu</option>
            </select>
        </div>

        <div class="quick-row" style="margin-top:12px;">
            <div class="qs-actions" id="qs_actions">
                <!-- Tombol akan aktif setelah class+subject dipilih -->
                <a id="btn_view" class="btn btn-secondary btn-disabled" href="#" onclick="return false;">Lihat Materi</a>
                <a id="btn_add_material" class="btn btn-primary btn-disabled" href="#" onclick="return false;">+ Tambah Materi</a>
                <a id="btn_add_assignment" class="btn btn-success btn-disabled" href="#" onclick="return false;">+ Tambah Tugas</a>
            </div>

            <div style="margin-left:auto; align-self:center;">
                <small class="small-muted">Pilih kelas & mapel untuk mengaktifkan aksi cepat</small>
            </div>
        </div>
    </div>

    <!-- ============ NOTIFIKASI ============ -->
    <div class="card">
        <h2 class="section-title">Notifikasi Terbaru</h2>
        <?php if (empty($notifs)): ?>
            <p>Belum ada notifikasi.</p>
        <?php else: foreach ($notifs as $n): ?>
            <div class="list-item">
                <strong><?= sanitize($n['title']); ?></strong><br>
                <?= sanitize($n['message']); ?><br>
                <small><?= timeAgo($n['created_at']); ?></small>
            </div>
        <?php endforeach; endif; ?>
        <a href="/web_MG/notifications/index.php" class="btn btn-secondary" style="margin-top:8px;">Lihat Semua</a>
    </div>

    <!-- ============ JADWAL NGANJAR ============ -->
    <div class="card">
        <h2 class="section-title">Jadwal Mengajar</h2>

        <h4>Sedang Berlangsung:</h4>
        <?php if ($sedangNgajar): ?>
            <p><strong><?= sanitize($sedangNgajar['nama_mapel']); ?></strong> - <?= sanitize($sedangNgajar['nama_kelas']); ?><br>
            <?= $sedangNgajar['start_time']; ?> - <?= $sedangNgajar['end_time']; ?></p>
        <?php else: ?>
            <p>Tidak ada pelajaran yang sedang berlangsung.</p>
        <?php endif; ?>

        <h4 style="margin-top:12px;">Selanjutnya:</h4>
        <?php if ($nextNgajar): ?>
            <p><strong><?= sanitize($nextNgajar['nama_mapel']); ?></strong> - <?= sanitize($nextNgajar['nama_kelas']); ?><br>
            <?= ucfirst($nextNgajar['day_of_week']); ?>, <?= $nextNgajar['start_time']; ?> - <?= $nextNgajar['end_time']; ?></p>
        <?php else: ?>
            <p>Tidak ada jadwal berikutnya.</p>
        <?php endif; ?>
    </div>

    <!-- ============ TUGAS AKTIF ============ -->
    <div class="card">
        <h2 class="section-title">Tugas Aktif</h2>
        <?php if (empty($tugasAktif)): ?>
            <p>Belum ada tugas aktif.</p>
        <?php else: foreach ($tugasAktif as $t):
            $deadline = getDeadlineStatus($t['deadline']);
        ?>
            <div class="list-item">
                <strong><?= sanitize($t['judul']); ?></strong><br>
                <?= sanitize($t['nama_mapel']); ?> - <?= sanitize($t['nama_kelas']); ?><br>
                <span class="badge-deadline badge-<?= $deadline['class']; ?>">
                    <?= $deadline['label']; ?>
                </span><br>
                <a href="/web_MG/assignments/view.php?id=<?= $t['id']; ?>" class="btn btn-secondary">Lihat</a>
                <a href="/web_MG/assignments/grade.php?id=<?= $t['id']; ?>" class="btn btn-success">Nilai</a>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- ============ MENUNGGU PENILAIAN ============ -->
    <div class="card">
        <h2 class="section-title">Menunggu Penilaian</h2>
        <?php if (empty($menungguNilai)): ?>
            <p>Tidak ada tugas yang menunggu penilaian.</p>
        <?php else: foreach ($menungguNilai as $m): ?>
            <div class="list-item">
                <strong><?= sanitize($m['judul_tugas']); ?></strong><br>
                <?= sanitize($m['nama_murid']); ?><br>
                <small>Diserahkan: <?= timeAgo($m['submitted_at']); ?></small><br>
                <a href="/web_MG/assignments/grade.php?id=<?= $m['assignment_id']; ?>" class="btn btn-primary">Nilai</a>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- ============ HISTORI MENGAJAR ============ -->
    <div class="card">
        <h2 class="section-title">Histori Mengajar</h2>
        <a href="/web_MG/teaching/logs_create.php" class="btn btn-primary" style="margin-bottom:10px;">+ Isi Log Hari Ini</a>

        <?php if (empty($histori)): ?>
            <p>Belum ada histori mengajar.</p>
        <?php else: foreach ($histori as $h): ?>
            <div class="list-item">
                <strong><?= sanitize($h['nama_mapel']); ?> - <?= sanitize($h['nama_kelas']); ?></strong><br>
                <?= formatTanggal($h['tanggal']); ?><br>
                <em><?= substr(sanitize($h['materi']), 0, 70); ?>...</em><br>
                <a href="/web_MG/teaching/logs_view.php?id=<?= $h['id']; ?>" class="btn btn-secondary">Lihat</a>
            </div>
        <?php endforeach; endif; ?>
    </div>

</div>

<script>
// Dynamic mapel loader for quick selector
const qsClass = document.getElementById("qs_class");
const qsMapel = document.getElementById("qs_mapel");
const btnView = document.getElementById("btn_view");
const btnAddMat = document.getElementById("btn_add_material");
const btnAddAssign = document.getElementById("btn_add_assignment");

function setDisabled(btn, disabled) {
    if (disabled) {
        btn.classList.add('btn-disabled');
        btn.classList.remove('btn-primary','btn-success','btn-secondary');
        btn.setAttribute('onclick','return false;');
        btn.removeAttribute('href');
    } else {
        btn.classList.remove('btn-disabled');
        // restore styling based on id
        if (btn.id === 'btn_view') btn.classList.add('btn-secondary');
        if (btn.id === 'btn_add_material') btn.classList.add('btn-primary');
        if (btn.id === 'btn_add_assignment') btn.classList.add('btn-success');
        btn.removeAttribute('onclick');
    }
}

// disable initially
setDisabled(btnView, true);
setDisabled(btnAddMat, true);
setDisabled(btnAddAssign, true);

qsClass.addEventListener("change", function() {
    const classId = this.value;
    qsMapel.innerHTML = "<option value=''>Loading...</option>";
    setDisabled(btnView, true);
    setDisabled(btnAddMat, true);
    setDisabled(btnAddAssign, true);

    if (!classId) {
        qsMapel.innerHTML = "<option value=''>Pilih kelas terlebih dahulu</option>";
        return;
    }

    fetch("/web_MG/api/get_subjects_by_class.php?class_id=" + encodeURIComponent(classId))
        .then(res => res.json())
        .then(data => {
            qsMapel.innerHTML = "<option value=''>Pilih mata pelajaran</option>";
            data.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.id;
                opt.textContent = m.nama_mapel;
                qsMapel.appendChild(opt);
            });
        })
        .catch(() => {
            qsMapel.innerHTML = "<option value=''>Gagal memuat mapel</option>";
        });
});

qsMapel.addEventListener("change", function() {
    const classId = qsClass.value;
    const subjectId = qsMapel.value;

    if (classId && subjectId) {
        // enable buttons and set hrefs
        setDisabled(btnView, false);
        setDisabled(btnAddMat, false);
        setDisabled(btnAddAssign, false);

        btnView.setAttribute('href', `/web_MG/materials/list.php?class_id=${encodeURIComponent(classId)}&subject_id=${encodeURIComponent(subjectId)}`);
        btnAddMat.setAttribute('href', `/web_MG/materials/create.php?class_id=${encodeURIComponent(classId)}&subject_id=${encodeURIComponent(subjectId)}`);
        btnAddAssign.setAttribute('href', `/web_MG/assignments/create.php?target_class_id=${encodeURIComponent(classId)}&subject_id=${encodeURIComponent(subjectId)}`);
    } else {
        setDisabled(btnView, true);
        setDisabled(btnAddMat, true);
        setDisabled(btnAddAssign, true);
    }
});
</script>

<?php include __DIR__ . '/../inc/footer.php'; ?>
