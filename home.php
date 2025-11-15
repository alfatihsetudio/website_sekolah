<?php
/**
 * Halaman Home - Profil Sekolah
 * Bisa diakses oleh semua orang (belum login)
 */
require_once __DIR__ . '/inc/config.php';

$pageTitle = 'Home';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo defined('APP_NAME') ? APP_NAME : 'Web Pembelajaran'; ?> - Home</title>

    <!-- tetap memuat stylesheet project jika ada -->
    <link rel="stylesheet" href="/web_MG/assets/css/style.css">

    <style>
        /* ---------- Simple & Mobile-first ---------- */
        :root{
            --bg: #071029;
            --card: #071820;
            --text: #e6eef8;
            --muted: #9aa8bf;
            --accent-guru: #10b981;
            --accent-murid: #2563eb;
            --max-width: 1000px;
            --gap: 16px;
        }

        *{box-sizing:border-box}
        body{
            margin:0;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
            background: linear-gradient(180deg,#061125 0%, #081428 100%);
            color:var(--text);
            -webkit-font-smoothing:antialiased;
            -moz-osx-font-smoothing:grayscale;
            line-height:1.5;
        }

        .container{
            max-width:var(--max-width);
            margin:0 auto;
            padding:18px;
        }

        /* header sederhana */
        .header{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
            margin-bottom:12px;
        }
        .brand{display:flex;align-items:center;gap:12px;text-decoration:none;color:inherit}
        .logo{
            width:44px;height:44px;border-radius:8px;
            display:grid;place-items:center;font-weight:700;
            background: linear-gradient(135deg,#4f46e5,#7c3aed);
            box-shadow: 0 6px 12px rgba(0,0,0,0.4);
            font-size:16px;
        }
        .brand h1{margin:0;font-size:1rem}
        .brand p{margin:0;font-size:0.78rem;color:var(--muted)}

        /* hero */
        .hero{
            background: linear-gradient(180deg, rgba(79,70,229,0.14), rgba(16,185,129,0.04));
            border-radius:12px;
            padding:20px;
            display:flex;
            flex-direction:column;
            gap:12px;
        }
        .hero h2{margin:0;font-size:1.4rem}
        .hero p.lead{margin:0;color:var(--muted);font-size:0.98rem}

        /* tombol CTA - dibuat besar untuk sentuhan */
        .cta{
            display:flex;
            gap:12px;
            flex-direction:column; /* default mobile: kolom */
            margin-top:6px;
        }
        .cta a{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            padding:12px 14px;
            border-radius:10px;
            font-weight:600;
            text-decoration:none;
            color:white;
            border:0;
            cursor:pointer;
        }
        .cta .guru{ background: linear-gradient(180deg,var(--accent-guru), #059669); color:#042018 }
        .cta .murid{ background: linear-gradient(180deg,var(--accent-murid), #1e40af); }

        /* konten profil & fitur */
        .cards{ display:grid; gap:var(--gap); margin-top:14px; }
        .card{
            background: rgba(255,255,255,0.02);
            border-radius:10px;
            padding:14px;
            border:1px solid rgba(255,255,255,0.03);
        }
        .card h3{margin:0 0 8px 0}
        .card p{margin:0;color:var(--muted);font-size:0.95rem}

        .features{
            display:grid;
            grid-template-columns: repeat(2, 1fr);
            gap:10px;
            margin-top:10px;
        }
        .feature{
            padding:10px;border-radius:8px;background:transparent;border:1px solid rgba(255,255,255,0.02);text-align:center;
        }
        .feature h4{margin:0 0 6px 0;font-size:0.98rem}
        .feature p{margin:0;color:var(--muted);font-size:0.88rem}

        /* footer */
        .footer{ text-align:center; color:var(--muted); margin-top:18px; font-size:0.9rem }

        /* ----- Responsive untuk tablet/desktop ----- */
        @media(min-width:700px){
            .cta{ flex-direction:row }
            .hero{ flex-direction:row; align-items:center; justify-content:space-between; padding:24px }
            .hero .left{max-width:62%}
            .hero .right{width:34%}
            .cards{ grid-template-columns: 1fr 320px; align-items:start }
            .features{ grid-template-columns: repeat(2, 1fr) }
        }

        @media(min-width:1000px){
            .cards{ grid-template-columns: 1fr 360px }
        }

        /* aksesibilitas: fokus */
        a:focus{ outline:3px solid rgba(255,255,255,0.06); outline-offset:3px }
    </style>
</head>
<body>
    <div class="container">
        <!-- header -->
        <div class="header">
            <a class="brand" href="<?php echo BASE_URL; ?>/">
                <div class="logo"><?php echo strtoupper(substr(defined('APP_NAME') ? APP_NAME : 'WP', 0, 2)); ?></div>
                <div>
                    <h1><?php echo defined('APP_NAME') ? APP_NAME : 'Web Pembelajaran'; ?></h1>
                    <p>Platform Pembelajaran Online</p>
                </div>
            </a>
            <div class="tiny" style="color:var(--muted);font-size:0.85rem">UI simpel • Kompatibel HP</div>
        </div>

        <!-- hero -->
        <section class="hero" aria-labelledby="home-title">
            <div class="left">
                <h2 id="home-title">Selamat datang di <?php echo defined('APP_NAME') ? APP_NAME : 'Web Pembelajaran'; ?></h2>
                <p class="lead">Sistem manajemen pembelajaran sederhana, ringan, dan ramah HP.</p>

                <?php
                    // pastikan selalu tampilkan halaman login: lakukan logout lalu redirect ke login (force)
                    $loginGuru = rawurlencode(BASE_URL . '/auth/login.php?role=guru&force=1');
                    $loginSiswa = rawurlencode(BASE_URL . '/auth/login.php?role=siswa&force=1');
                ?>

                <div class="cta" role="navigation" aria-label="Tombol masuk">
                    <a href="<?php echo BASE_URL; ?>/auth/logout.php?next=<?php echo $loginGuru; ?>" class="guru">📚 Masuk sebagai Guru</a>
                    <a href="<?php echo BASE_URL; ?>/auth/logout.php?next=<?php echo $loginSiswa; ?>" class="murid">🎓 Masuk sebagai Murid</a>
                </div>
            </div>

            <div class="right" aria-hidden="true" style="text-align:right;">
                <div style="display:inline-block;text-align:left;">
                    <strong style="display:block;font-size:0.95rem">Fitur cepat</strong>
                    <div style="color:var(--muted);margin-top:6px;font-size:0.9rem">Materi • Tugas • Absensi • Notifikasi</div>
                </div>
            </div>
        </section>

        <!-- konten -->
        <main class="cards" role="main">
            <div class="card">
                <h3>📖 Profil Sekolah</h3>
                <p>
                    Selamat datang di <strong><?php echo defined('APP_NAME') ? APP_NAME : 'Web Pembelajaran'; ?></strong>.
                    Platform ini membantu proses belajar mengajar, mulai dari manajemen materi, penugasan, absensi, hingga evaluasi.
                </p>
                <div style="margin-top:12px;">
                    <h4 style="margin:0 0 8px 0">✨ Fitur Utama</h4>
                    <div class="features">
                        <div class="feature">
                            <h4>📚 Materi</h4>
                            <p>Akses materi kapan saja</p>
                        </div>
                        <div class="feature">
                            <h4>📝 Tugas</h4>
                            <p>Kumpul & nilai tugas</p>
                        </div>
                        <div class="feature">
                            <h4>📊 Absensi</h4>
                            <p>Pantau kehadiran</p>
                        </div>
                        <div class="feature">
                            <h4>🔔 Notif</h4>
                            <p>Pemberitahuan penting</p>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="card" aria-label="Untuk siapa">
                <h3>👥 Untuk Siapa?</h3>
                <div style="margin-top:8px">
                    <strong style="color:#10b981;display:block">👨‍🏫 Guru</strong>
                    <p style="color:var(--muted);margin:6px 0 12px 0">Kelola materi, buat tugas, nilai siswa, dan rekam absensi.</p>

                    <strong style="color:#2563eb;display:block">🎓 Murid</strong>
                    <p style="color:var(--muted);margin:6px 0 0 0">Akses materi, kumpulkan tugas, lihat nilai, dan pantau absensi.</p>
                </div>
            </aside>
        </main>

        <footer class="footer" style="
    margin-top:28px;
    padding:16px 20px;
    text-align:center;
    font-size:0.9rem;
    color:#94a3b8;
    border-top:1px solid rgba(255,255,255,0.08);
    background:rgba(255,255,255,0.02);
    backdrop-filter:blur(6px);
    border-radius:8px;
">
    &copy; <?php echo date('Y'); ?> 
    <?php echo defined('APP_NAME') ? APP_NAME : 'Web Pembelajaran'; ?>  
    <span style="opacity:0.8;">• Platform Pembelajaran Online</span>
</footer>

    </div>
</body>
</html>
