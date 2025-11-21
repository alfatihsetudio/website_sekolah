<?php
// space_belajar/dashboard.php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

requireLogin();

$role = getUserRole();
if (!in_array($role, ['murid','siswa'], true)) {
    http_response_code(403);
    echo "Halaman ini khusus untuk akun murid.";
    exit;
}

$baseUrl = rtrim(BASE_URL, '/\\');
$pageTitle = 'Space Belajar';
include __DIR__ . '/../inc/header.php';
?>

<style>
.sb-container {
    max-width: 1100px;
    margin: 0 auto;
}
.sb-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:16px;
    margin-top:18px;
}
.sb-card-link {
    display:block;
    background:#ffffff;
    border-radius:14px;
    padding:16px 18px;
    border:1px solid #e5e7eb;
    box-shadow:0 1px 4px rgba(15,23,42,0.06);
    text-decoration:none;
    color:#111827;
    transition:.15s;
}
.sb-card-link:hover {
    transform:translateY(-2px);
    box-shadow:0 4px 12px rgba(15,23,42,0.12);
    background:#f9fafb;
}
.sb-card-icon {
    font-size:1.8rem;
    margin-bottom:6px;
}
.sb-card-title {
    font-weight:600;
    margin-bottom:4px;
}
.sb-card-desc {
    font-size:.88rem;
    color:#6b7280;
}
</style>

<div class="container sb-container">
    <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:8px;flex-wrap:wrap;">
        <div>
            <h1 style="margin:0 0 4px 0;">Space Belajar</h1>
            <p style="margin:0;font-size:.94rem;color:#6b7280;">
                Ruang khusus untuk menyimpan dan memantau perjalanan belajar kamu.
            </p>
        </div>
        
    </div>

    <div class="sb-grid">
        <a class="sb-card-link" href="<?php echo $baseUrl; ?>/space_belajar/riwayat_tugas.php">
            <div class="sb-card-icon">📝</div>
            <div class="sb-card-title">Riwayat Tugas</div>
            <div class="sb-card-desc">Lihat semua tugas yang pernah diberikan guru, lengkap dengan status dan nilai.</div>
        </a>

        <a class="sb-card-link" href="<?php echo $baseUrl; ?>/space_belajar/riwayat_materi.php">
            <div class="sb-card-icon">📚</div>
            <div class="sb-card-title">Riwayat Materi</div>
            <div class="sb-card-desc">Kumpulan semua materi pelajaran yang pernah dibagikan guru.</div>
        </a>

        <a class="sb-card-link" href="<?php echo $baseUrl; ?>/space_belajar/file_exploler_dashboard.php">
            <div class="sb-card-icon">🗂️</div>
            <div class="sb-card-title">File Explorer</div>
            <div class="sb-card-desc">Buat folder sendiri, simpan file belajar, dan cari materi dengan cepat.</div>
        </a>

        <a class="sb-card-link" href="<?php echo $baseUrl; ?>/space_belajar/progres_belajar.php">
            <div class="sb-card-icon">📈</div>
            <div class="sb-card-title">Progres Belajar</div>
            <div class="sb-card-desc">Tentukan tujuan belajarmu dan catat progres yang sudah kamu capai.</div>
        </a>

        <a class="sb-card-link" href="<?php echo $baseUrl; ?>/study/calendar.php">
            <div class="sb-card-icon">📅</div>
            <div class="sb-card-title">Kalender Belajar</div>
            <div class="sb-card-desc">Lihat jadwal dan rencana belajar kamu dalam satu kalender yang mudah dipahami.</div>
        </a>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
