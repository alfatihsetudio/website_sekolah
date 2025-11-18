<?php
/**
 * Halaman Home - Landing Page Sekolah
 * Bisa diakses oleh semua orang (belum login)
 */
require_once __DIR__ . '/inc/config.php';

$pageTitle = 'Home';

$baseUrl    = rtrim(BASE_URL, '/\\');
$loginGuru  = rawurlencode($baseUrl . '/auth/login.php?role=guru&force=1');
$loginSiswa = rawurlencode($baseUrl . '/auth/login.php?role=siswa&force=1');
$logoutUrl  = $baseUrl . '/auth/logout.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo defined('APP_NAME') ? APP_NAME : 'Web Pembelajaran'; ?> - Home</title>

    <!-- stylesheet utama project -->
    <link rel="stylesheet" href="/web_MG/assets/css/style.css">

    <style>
        :root {
            --bg-page: #f5f5f7;
            --bg-card: #ffffff;
            --border-subtle: #e5e7eb;
            --text-main: #111827;
            --text-muted: #6b7280;
            --accent: #2563eb;
            --accent-soft: #eff6ff;
            --radius-lg: 18px;
            --radius-md: 12px;
            --shadow-soft: 0 18px 40px rgba(15, 23, 42, 0.08);
            --max-width: 1120px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: var(--bg-page);
            color: var(--text-main);
            -webkit-font-smoothing: antialiased;
        }

        .page {
            max-width: var(--max-width);
            margin: 0 auto;
            padding: 16px 16px 32px;
        }

        @media (min-width: 720px) {
            .page {
                padding: 20px 20px 40px;
            }
        }

        /* NAVBAR */
        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 16px;
            background: #ffffff;
            border-radius: 999px;
            border: 1px solid var(--border-subtle);
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
            margin-bottom: 20px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: inherit;
        }

        .brand-logo {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            font-weight: 700;
            font-size: 0.95rem;
            background: #2563eb;
            color: #ffffff;
        }

        .brand-title {
            margin: 0;
            font-size: 0.98rem;
            font-weight: 600;
        }

        .brand-sub {
            margin: 0;
            font-size: 0.78rem;
            color: var(--text-muted);
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-links {
            display: none;
            gap: 10px;
            align-items: center;
        }

        .nav-link {
            font-size: 0.82rem;
            color: var(--text-muted);
            text-decoration: none;
            padding: 4px 8px;
            border-radius: 999px;
        }

        .nav-link:hover {
            background: #f3f4f6;
            color: var(--text-main);
        }

        @media (min-width: 768px) {
            .nav-links {
                display: flex;
            }
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 500;
            border: 1px solid transparent;
            text-decoration: none;
            cursor: pointer;
            white-space: nowrap;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }

        .btn-primary:hover {
            filter: brightness(1.05);
        }

        .btn-outline {
            background: white;
            color: var(--text-main);
            border-color: var(--border-subtle);
        }

        .btn-outline:hover {
            background: #f9fafb;
        }

        /* HERO */
        .hero {
            background: #ffffff;
            border-radius: var(--radius-lg);
            padding: 22px 18px 20px;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-subtle);
        }

        @media (min-width: 900px) {
            .hero {
                display: grid;
                grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr);
                gap: 24px;
                padding: 26px 26px 22px;
            }
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 9px;
            border-radius: 999px;
            font-size: 0.75rem;
            background: var(--accent-soft);
            color: #1d4ed8;
            margin-bottom: 10px;
        }

        .hero-title {
            margin: 0;
            font-size: 1.6rem;
            line-height: 1.3;
        }

        @media (min-width: 900px) {
            .hero-title {
                font-size: 2rem;
            }
        }

        .hero-title span {
            color: var(--accent);
        }

        .hero-subtitle {
            margin: 8px 0 0;
            font-size: 0.96rem;
            color: var(--text-muted);
        }

        .hero-cta {
            margin-top: 18px;
            display: grid;
            grid-template-columns: 1fr;
            gap: 8px;
        }

        @media (min-width: 520px) {
            .hero-cta {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        .hero-cta .btn {
            width: 100%;
        }

        .hero-extra {
            margin-top: 8px;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .hero-extra a {
            color: var(--accent);
            text-decoration: none;
        }
        .hero-extra a:hover {
            text-decoration: underline;
        }

        .hero-right {
            margin-top: 16px;
        }

        @media (min-width: 900px) {
            .hero-right {
                margin-top: 0;
            }
        }

        .mockup {
            background: #f9fafb;
            border-radius: 16px;
            border: 1px solid var(--border-subtle);
            padding: 12px;
        }

        .mockup-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .mockup-tabs {
            display: inline-flex;
            gap: 4px;
            background: #e5e7eb;
            border-radius: 999px;
            padding: 2px;
        }

        .mockup-tab {
            padding: 3px 7px;
            border-radius: 999px;
            font-size: 0.78rem;
            color: #6b7280;
        }

        .mockup-tab.active {
            background: white;
            color: #111827;
        }

        .mockup-body {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            margin-top: 6px;
        }

        .mockup-card {
            background: white;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            padding: 8px;
            font-size: 0.75rem;
        }

        .mockup-card-title {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .mockup-card-pill {
            margin-top: 4px;
            display: inline-block;
            padding: 2px 6px;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 0.7rem;
        }

        /* SECTIONS */
        .sections {
            margin-top: 22px;
            display: grid;
            gap: 16px;
        }

        @media (min-width: 900px) {
            .sections {
                grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr);
            }
        }

        .card {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-subtle);
            padding: 16px 16px 14px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
        }

        .card-header {
            margin-bottom: 10px;
        }

        .card-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }

        .card-subtitle {
            margin: 4px 0 0;
            font-size: 0.88rem;
            color: var(--text-muted);
        }

        .features-grid {
            display: grid;
            gap: 10px;
            margin-top: 10px;
        }

        @media (min-width: 600px) {
            .features-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        .feature-item {
            border-radius: 10px;
            border: 1px solid var(--border-subtle);
            padding: 10px 10px 9px;
            background: #ffffff;
        }

        .feature-title {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .feature-desc {
            margin: 4px 0 0;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .tag-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .tag {
            padding: 3px 8px;
            border-radius: 999px;
            border: 1px dashed var(--border-subtle);
            font-size: 0.78rem;
            color: var(--text-muted);
        }

        .role-block {
            border-radius: 10px;
            border: 1px solid var(--border-subtle);
            padding: 10px 10px 9px;
            margin-bottom: 8px;
        }

        .role-title {
            margin: 0 0 2px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .role-desc {
            margin: 0;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .role-list {
            margin: 6px 0 0;
            padding-left: 16px;
            font-size: 0.78rem;
            color: var(--text-muted);
        }

        /* FOOTER */
        .footer {
            margin-top: 24px;
            padding-top: 14px;
            border-top: 1px solid var(--border-subtle);
            font-size: 0.8rem;
            color: var(--text-muted);
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            justify-content: space-between;
        }

        .footer-links {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .footer-links a {
            color: var(--text-muted);
            text-decoration: none;
        }

        .footer-links a:hover {
            text-decoration: underline;
        }

        /* Fokus */
        a:focus-visible,
        button:focus-visible {
            outline: 2px solid var(--accent);
            outline-offset: 2px;
        }
    </style>
</head>
<body>
<div class="page">

    <!-- NAVBAR -->
    <header class="navbar">
        <a href="<?php echo $baseUrl; ?>/home.php" class="brand">
            <div class="brand-logo">
                <?php
                $app = defined('APP_NAME') ? APP_NAME : 'WP';
                echo strtoupper(substr($app, 0, 2));
                ?>
            </div>
            <div>
                <p class="brand-title">
                    <?php echo defined('APP_NAME') ? APP_NAME : 'Web Pembelajaran'; ?>
                </p>
                <p class="brand-sub">Platform pembelajaran untuk sekolah formal & non-formal</p>
            </div>
        </a>

        <div class="nav-right">
            <div class="nav-links">
                <a href="#tentang" class="nav-link">Tentang</a>
                <a href="#fitur" class="nav-link">Fitur</a>
                <a href="#peran" class="nav-link">Peran pengguna</a>
            </div>
            <a href="<?php echo $baseUrl; ?>/admin_login.php" class="btn btn-outline">Login admin</a>
            <a href="<?php echo $baseUrl; ?>/account/register_admin.php" class="btn btn-primary">Daftar admin</a>
        </div>
    </header>

    <!-- HERO -->
    <section class="hero" aria-labelledby="hero-title">
        <div>
            <div class="hero-badge">
                🌱 Cocok untuk sekolah besar maupun lembaga kecil
            </div>
            <h1 id="hero-title" class="hero-title">
                Buat <span>lingkungan belajar</span> online untuk sekolah Anda.
            </h1>
            <p class="hero-subtitle">
                Satu akun admin untuk satu sekolah. Guru dan murid yang didaftarkan hanya melihat
                kelas dan data di sekolahnya sendiri.
            </p>

            <div class="hero-cta">
                <a href="<?php echo $logoutUrl; ?>?next=<?php echo $loginGuru; ?>" class="btn btn-primary">
                    👨‍🏫 Masuk sebagai guru
                </a>
                <a href="<?php echo $logoutUrl; ?>?next=<?php echo $loginSiswa; ?>" class="btn btn-outline">
                    🎓 Masuk sebagai murid
                </a>
            </div>

            <div class="hero-extra">
                Belum punya akun sekolah?
                <a href="<?php echo $baseUrl; ?>/account/register_admin.php">
                    Daftar sebagai admin sekolah
                </a>
            </div>
        </div>

        <div class="hero-right" aria-hidden="true">
            <div class="mockup">
                <div class="mockup-header">
                    <span style="font-weight:500;">Tampilan ringkas dashboard</span>
                    <div class="mockup-tabs">
                        <span class="mockup-tab active">Kelas</span>
                        <span class="mockup-tab">Materi</span>
                        <span class="mockup-tab">Absensi</span>
                    </div>
                </div>
                <div class="mockup-body">
                    <div class="mockup-card">
                        <div class="mockup-card-title">Kelas 7A</div>
                        <div class="mockup-card-pill">28 murid</div>
                    </div>
                    <div class="mockup-card">
                        <div class="mockup-card-title">Kelas 8B</div>
                        <div class="mockup-card-pill">26 murid</div>
                    </div>
                    <div class="mockup-card">
                        <div class="mockup-card-title">Kelas 9C</div>
                        <div class="mockup-card-pill">30 murid</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- SECTIONS -->
    <main class="sections" role="main">

        <!-- TENTANG & FITUR -->
        <section class="card" id="tentang">
            <div class="card-header">
                <h2 class="card-title">Tentang platform ini</h2>
                <p class="card-subtitle">
                    <?php echo defined('APP_NAME') ? APP_NAME : 'Web Pembelajaran'; ?> dibuat untuk membantu sekolah
                    formal maupun lembaga kecil non-formal mengatur kegiatan belajar mengajar tanpa sistem yang rumit.
                </p>
            </div>

            <div class="features-grid" id="fitur">
                <article class="feature-item">
                    <h3 class="feature-title">📚 Materi terpusat</h3>
                    <p class="feature-desc">
                        Guru mengunggah materi sesuai kelas dan mapel, murid bisa mengakses kapan saja dari akun masing-masing.
                    </p>
                </article>
                <article class="feature-item">
                    <h3 class="feature-title">📝 Tugas & penilaian</h3>
                    <p class="feature-desc">
                        Tugas terkumpul di satu tempat. Guru memberi nilai, murid bisa melihat hasilnya secara langsung.
                    </p>
                </article>
                <article class="feature-item">
                    <h3 class="feature-title">📊 Absensi sederhana</h3>
                    <p class="feature-desc">
                        Catat kehadiran murid dengan tampilan sederhana namun cukup untuk kebutuhan harian.
                    </p>
                </article>
                <article class="feature-item">
                    <h3 class="feature-title">🔔 Informasi penting</h3>
                    <p class="feature-desc">
                        Admin dan guru dapat menyampaikan pengumuman untuk seluruh murid dalam satu lingkungan sekolah.
                    </p>
                </article>
            </div>

            <div class="tag-row">
                <span class="tag">Satu admin = satu sekolah</span>
                <span class="tag">Guru & murid tidak tercampur antar sekolah</span>
                <span class="tag">Tampilan putih, simpel, mudah dipahami</span>
            </div>
        </section>

        <!-- PERAN PENGGUNA -->
        <aside class="card" id="peran">
            <div class="card-header">
                <h2 class="card-title">Peran di dalam sistem</h2>
                <p class="card-subtitle">
                    Setiap orang punya akses sesuai tugasnya: admin mengatur, guru mengajar, murid belajar.
                </p>
            </div>

            <div class="role-block">
                <h3 class="role-title">👨‍💼 Admin sekolah</h3>
                <p class="role-desc">
                    Pemilik akun sekolah. Biasanya kepala sekolah, tata usaha, atau penanggung jawab lembaga.
                </p>
                <ul class="role-list">
                    <li>Membuat akun guru dan murid</li>
                    <li>Mengatur kelas dan mata pelajaran</li>
                    <li>Mengelola identitas sekolah di sistem</li>
                </ul>
                <div style="margin-top:8px;">
                    <a href="<?php echo $baseUrl; ?>/account/register_admin.php"
                       class="btn btn-primary"
                       style="width:100%;justify-content:center;">
                        Daftar sebagai admin sekolah
                    </a>
                </div>
            </div>

            <div class="role-block">
                <h3 class="role-title">👨‍🏫 Guru</h3>
                <p class="role-desc">
                    Mengajar di kelas yang sudah ditentukan admin, tanpa perlu mengurus pengaturan teknis.
                </p>
                <ul class="role-list">
                    <li>Menyusun materi dan tugas</li>
                    <li>Memberi nilai tugas dan ujian</li>
                    <li>Mencatat kehadiran murid</li>
                </ul>
            </div>

            <div class="role-block">
                <h3 class="role-title">🎓 Murid</h3>
                <p class="role-desc">
                    Masuk menggunakan akun yang dibuat admin, lalu fokus belajar di kelas masing-masing.
                </p>
                <ul class="role-list">
                    <li>Mengakses materi pelajaran</li>
                    <li>Mengumpulkan tugas</li>
                    <li>Melihat nilai dan kehadiran</li>
                </ul>
            </div>
        </aside>
    </main>

    <!-- FOOTER -->
    <footer class="footer">
        <div>
            &copy; <?php echo date('Y'); ?>
            <?php echo defined('APP_NAME') ? APP_NAME : 'Web Pembelajaran'; ?>.
            <span>Semua hak dilindungi.</span>
        </div>
        <div class="footer-links">
            <a href="#tentang">Tentang</a>
            <a href="#fitur">Fitur</a>
            <a href="<?php echo $baseUrl; ?>/login.php">Login admin</a>
        </div>
    </footer>

</div>
</body>
</html>
