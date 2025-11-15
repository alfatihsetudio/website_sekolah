# Web Pembelajaran - Sistem Manajemen Pembelajaran Online

Sistem pembelajaran online modern yang dibangun dengan **Pure PHP**, **HTML5**, **CSS3**, dan **MySQL** tanpa framework. Desain mobile-first yang responsif dan mudah digunakan.

## 📋 Fitur Utama

### 1. Autentikasi & Keamanan
- ✅ Login/Logout dengan session management
- ✅ Register user (khusus admin)
- ✅ Password hashing (bcrypt)
- ✅ Role-based access control (Admin, Guru, Murid)
- ✅ Session security (regenerate ID)

### 2. Dashboard
- ✅ Dashboard Guru: Statistik, materi terbaru, tugas terbaru, tugas menunggu penilaian
- ✅ Dashboard Murid: Tugas belum dikumpul, materi terbaru, kehadiran minggu ini
- ✅ Dashboard Admin: Manajemen user dan kelas

### 3. Materi Pembelajaran
- ✅ CRUD lengkap (Create, Read, Update, Delete)
- ✅ Upload file materi (PDF, DOC, DOCX, JPG, PNG, ZIP)
- ✅ Link video YouTube/eksternal
- ✅ Search & filter
- ✅ Pagination

### 4. Sistem Penugasan
- ✅ Guru: Buat tugas, set deadline, nilai submission
- ✅ Murid: Lihat tugas, submit tugas (file atau link Drive), lihat nilai & feedback
- ✅ Deadline tracking dengan status (OK, Urgent, Overdue)
- ✅ File contoh tugas

### 5. Absensi
- ✅ Guru: Rekam absensi harian (Hadir, Izin, Sakit, Alfa, Terlambat)
- ✅ Murid: Lihat riwayat absensi pribadi
- ✅ Statistik kehadiran

### 6. Notifikasi
- ✅ Notifikasi tugas baru
- ✅ Notifikasi deadline
- ✅ Notifikasi nilai masuk
- ✅ Notifikasi submission baru (untuk guru)

### 7. File Management
- ✅ Upload file dengan validasi (max 10MB)
- ✅ Protected download (akses melalui script)
- ✅ Metadata file tersimpan di database

## 🛠️ Teknologi

- **Backend**: PHP 7.4+ (Pure PHP, no framework)
- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **Database**: MySQL 5.7+
- **Server**: Apache (InfinityFree compatible)

## 📁 Struktur Folder

```
/web_MG
├── assets/
│   ├── css/
│   │   └── style.css          # CSS utama (mobile-first)
│   ├── js/
│   │   └── main.js            # JavaScript vanilla
│   └── img/                   # Gambar/icon
├── auth/
│   ├── login.php              # Halaman login
│   ├── logout.php             # Logout handler
│   └── register.php           # Register user (admin only)
├── dashboard/
│   ├── admin.php              # Dashboard admin
│   ├── guru.php               # Dashboard guru
│   └── murid.php              # Dashboard murid
├── materials/
│   ├── list.php               # Daftar materi
│   ├── view.php               # Detail materi
│   ├── create.php             # Buat materi
│   ├── edit.php               # Edit materi
│   └── delete.php             # Hapus materi
├── assignments/
│   ├── list.php               # Daftar tugas
│   ├── view.php               # Detail tugas
│   ├── create.php             # Buat tugas
│   ├── submit.php             # Submit tugas (murid)
│   └── grade.php              # Nilai tugas (guru)
├── attendance/
│   ├── record.php             # Rekam absensi (guru)
│   └── view.php               # Riwayat absensi (murid)
├── notifications/
│   └── index.php              # Daftar notifikasi
├── classes/
│   ├── list.php               # Daftar kelas
│   └── view.php               # Detail kelas
├── inc/
│   ├── config.php             # Konfigurasi aplikasi
│   ├── db.php                 # Database connection
│   ├── auth.php               # Authentication helper
│   ├── helpers.php            # Helper functions
│   ├── header.php             # Header template
│   └── footer.php             # Footer template
├── uploads/                   # Folder upload (protected)
│   ├── materials/             # File materi
│   ├── assignments/           # File tugas
│   └── submissions/           # File submission
├── .htaccess                  # Apache configuration
├── index.php                  # Homepage (redirect)
├── download.php               # File download handler
├── database.sql               # SQL schema
└── README.md                  # Dokumentasi ini
```

## 🚀 Instalasi & Setup

### 1. Persyaratan
- PHP 7.4 atau lebih tinggi
- MySQL 5.7 atau lebih tinggi
- Apache web server
- phpMyAdmin (untuk import database)

### 2. Upload File
1. Upload semua file ke folder `htdocs/` di InfinityFree atau server lokal Anda
2. Pastikan struktur folder tetap sama

### 3. Setup Database
1. Buat database baru di phpMyAdmin (contoh: `web_markazlugoh`)
2. Import file `database.sql` ke database tersebut
3. Database akan otomatis terbuat dengan:
   - Tabel-tabel yang diperlukan
   - User admin default:
     - Email: `admin@example.com`
     - Password: `admin123`
     - **PENTING**: Ganti password setelah login pertama!

### 4. Konfigurasi
Edit file `inc/config.php`:

```php
// Database Configuration
define('DB_HOST', 'localhost');        // Ganti dengan hostname database
define('DB_USER', 'root');             // Ganti dengan username database
define('DB_PASS', '');                 // Ganti dengan password database
define('DB_NAME', 'web_markazlugoh'); // Ganti dengan nama database

// Application Settings
define('APP_NAME', 'Web Pembelajaran');
define('APP_URL', 'http://localhost/web_MG'); // Ganti dengan URL hosting Anda
```

### 5. Set Permission
Pastikan folder `uploads/` memiliki permission write (755 atau 777):

```bash
chmod 755 uploads/
chmod 755 uploads/materials/
chmod 755 uploads/assignments/
chmod 755 uploads/submissions/
```

### 6. Testing
1. Buka browser dan akses URL aplikasi
2. Login dengan:
   - Email: `admin@example.com`
   - Password: `admin123`
3. Setelah login, segera ganti password admin!

## 📖 Panduan Penggunaan

### Untuk Admin
1. **Register User Baru**
   - Masuk ke Dashboard Admin
   - Klik "Register User"
   - Isi data user (nama, email, password, role)
   - User baru akan terdaftar

2. **Manajemen Kelas**
   - Buat kelas baru
   - Tambahkan murid ke kelas
   - Assign guru ke kelas

### Untuk Guru
1. **Membuat Materi**
   - Dashboard → "Tambah Materi"
   - Pilih mata pelajaran
   - Isi judul, konten
   - Upload file (opsional)
   - Tambah link video (opsional)
   - Simpan

2. **Membuat Tugas**
   - Dashboard → "Tambah Tugas"
   - Pilih mata pelajaran
   - Isi judul, deskripsi, deadline
   - Upload file contoh (opsional)
   - Simpan

3. **Menilai Tugas**
   - Dashboard → "Daftar Tugas" → Pilih tugas
   - Klik "Nilai"
   - Input nilai (0-100) dan feedback
   - Simpan

4. **Rekam Absensi**
   - Dashboard → "Absensi"
   - Pilih kelas dan tanggal
   - Pilih status untuk setiap murid
   - Simpan

### Untuk Murid
1. **Melihat Materi**
   - Dashboard → "Daftar Materi"
   - Klik materi untuk membaca
   - Download file jika ada

2. **Mengerjakan Tugas**
   - Dashboard → "Daftar Tugas"
   - Klik tugas untuk melihat detail
   - Klik "Kumpulkan"
   - Upload file atau isi link Google Drive
   - Submit

3. **Melihat Nilai**
   - Dashboard → "Daftar Tugas"
   - Tugas yang sudah dinilai akan menampilkan nilai dan feedback

4. **Melihat Absensi**
   - Dashboard → "Riwayat Absensi"
   - Lihat statistik dan riwayat kehadiran

## 🔒 Keamanan

- ✅ Password di-hash dengan `password_hash()` (bcrypt)
- ✅ Session management dengan `session_regenerate_id()`
- ✅ SQL injection protection dengan prepared statements
- ✅ XSS protection dengan `htmlspecialchars()`
- ✅ File upload validation (type & size)
- ✅ Protected file download (akses melalui script)
- ✅ Role-based access control

## 📱 Mobile-First Design

- ✅ Responsive design untuk semua ukuran layar
- ✅ Target layar minimal: 360px width
- ✅ Touch-friendly buttons (min 44px height)
- ✅ Optimized untuk mobile browsing
- ✅ Clean & modern UI

## 🐛 Troubleshooting

### Database Error
- **Error**: "Koneksi database gagal"
- **Solusi**: 
  - Cek `inc/config.php` (host, user, pass, dbname)
  - Pastikan database sudah dibuat
  - Pastikan MySQL service berjalan

### Upload Error
- **Error**: "File terlalu besar"
- **Solusi**: 
  - Cek ukuran file (max 10MB)
  - Cek `php.ini`: `upload_max_filesize` dan `post_max_size`
  - Cek permission folder `uploads/`

### 404 Error
- **Error**: "Halaman tidak ditemukan"
- **Solusi**: 
  - Cek struktur folder
  - Cek `.htaccess` (jika pakai mod_rewrite)
  - Cek URL path di `config.php`

### 500 Error
- **Error**: "Internal Server Error"
- **Solusi**: 
  - Cek error log PHP
  - Cek syntax error di file PHP
  - Cek permission file

### Session Error
- **Error**: "Session tidak berfungsi"
- **Solusi**: 
  - Cek permission folder session
  - Cek `php.ini`: `session.save_path`
  - Pastikan `session_start()` dipanggil

## 📝 Test Cases

### 1. Login
- ✅ Login dengan email & password benar → Berhasil
- ✅ Login dengan password salah → Error
- ✅ Login dengan email tidak terdaftar → Error

### 2. Upload File
- ✅ Upload file < 10MB → Berhasil
- ✅ Upload file > 10MB → Error
- ✅ Upload file dengan tipe tidak diizinkan → Error

### 3. Submit Tugas
- ✅ Submit sebelum deadline → Berhasil
- ✅ Submit setelah deadline → Terlambat (jika diizinkan)

### 4. Nilai Tugas
- ✅ Input nilai 0-100 → Berhasil
- ✅ Input nilai > 100 → Error
- ✅ Input nilai negatif → Error

### 5. Absensi
- ✅ Rekam absensi untuk semua murid → Berhasil
- ✅ Edit absensi yang sudah ada → Update berhasil

## 🔄 Update & Maintenance

### Backup Database
```sql
-- Export database
mysqldump -u username -p database_name > backup.sql
```

### Backup File
- Backup folder `uploads/` secara berkala
- Backup file konfigurasi `inc/config.php`

### Update
1. Backup database dan file
2. Upload file baru (jangan overwrite `config.php`)
3. Test semua fitur
4. Restore jika ada masalah

## 📞 Support

Jika ada pertanyaan atau masalah:
1. Cek dokumentasi ini
2. Cek error log
3. Cek troubleshooting section
4. Hubungi administrator

## 📄 License

Proyek ini dibuat untuk keperluan pembelajaran. Bebas digunakan dan dimodifikasi sesuai kebutuhan.

---

**Dibuat dengan ❤️ menggunakan Pure PHP**

