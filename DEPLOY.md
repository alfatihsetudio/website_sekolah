# Panduan Deploy ke InfinityFree

Panduan lengkap untuk deploy aplikasi Web Pembelajaran ke hosting InfinityFree.

## 📋 Persiapan

### 1. Akun InfinityFree
- Daftar di [InfinityFree.net](https://www.infinityfree.net)
- Verifikasi email
- Login ke control panel

### 2. Informasi Penting InfinityFree
- **Upload Limit**: ±10MB per file
- **Database**: MySQL (phpMyAdmin tersedia)
- **Folder**: `htdocs/` (public folder)
- **PHP Version**: 7.4 atau 8.0
- **URL Format**: `http://yourdomain.epizy.com` atau custom domain

## 🚀 Langkah-Langkah Deploy

### Step 1: Buat Hosting
1. Login ke InfinityFree Control Panel
2. Klik "Create Account"
3. Pilih "Free Hosting"
4. Isi:
   - **Domain**: Pilih subdomain atau gunakan domain sendiri
   - **Username**: Username untuk FTP
   - **Password**: Password untuk FTP
5. Klik "Create Account"
6. Tunggu hingga hosting aktif (biasanya beberapa menit)

### Step 2: Buat Database
1. Di Control Panel, klik "MySQL Databases"
2. Klik "Create New Database"
3. Isi:
   - **Database Name**: `web_markazlugoh` (atau nama lain)
   - **Database User**: Username database
   - **Database Password**: Password database
4. Klik "Create Database"
5. **CATAT**: Hostname, Username, Password, Database Name
   - Hostname biasanya: `sqlXXX.epizy.com` atau `localhost`
   - Username: Username yang Anda buat
   - Password: Password yang Anda buat
   - Database Name: Nama database yang Anda buat

### Step 3: Upload File
#### Opsi A: Via File Manager (Web)
1. Di Control Panel, klik "File Manager"
2. Masuk ke folder `htdocs/`
3. Upload semua file aplikasi ke folder ini
4. Pastikan struktur folder tetap sama

#### Opsi B: Via FTP
1. Download FTP client (FileZilla, WinSCP, dll)
2. Koneksi dengan:
   - **Host**: `ftpupload.net` atau hostname yang diberikan
   - **Username**: Username FTP Anda
   - **Password**: Password FTP Anda
   - **Port**: 21
3. Masuk ke folder `htdocs/`
4. Upload semua file aplikasi

### Step 4: Import Database
1. Di Control Panel, klik "phpMyAdmin"
2. Login dengan username & password database
3. Pilih database yang sudah dibuat
4. Klik tab "Import"
5. Pilih file `database.sql`
6. Klik "Go" / "Import"
7. Tunggu hingga selesai

### Step 5: Edit Konfigurasi
1. Buka file `inc/config.php` via File Manager atau FTP
2. Edit bagian database:

```php
// Database Configuration
define('DB_HOST', 'sqlXXX.epizy.com'); // Ganti dengan hostname dari InfinityFree
define('DB_USER', 'epiz_XXXXXX');      // Ganti dengan username database
define('DB_PASS', 'your_password');    // Ganti dengan password database
define('DB_NAME', 'epiz_XXXXXX_web');  // Ganti dengan nama database

// Application Settings
define('APP_NAME', 'Web Pembelajaran');
define('APP_URL', 'http://yourdomain.epizy.com'); // Ganti dengan URL hosting Anda
```

3. Simpan file

### Step 6: Set Permission Folder
1. Via File Manager, masuk ke folder `uploads/`
2. Set permission menjadi `755` atau `777`
3. Lakukan hal yang sama untuk subfolder:
   - `uploads/materials/`
   - `uploads/assignments/`
   - `uploads/submissions/`

### Step 7: Testing
1. Buka browser
2. Akses URL aplikasi: `http://yourdomain.epizy.com`
3. Login dengan:
   - Email: `admin@example.com`
   - Password: `admin123`
4. Test fitur-fitur utama:
   - Login/Logout
   - Buat materi
   - Upload file
   - Buat tugas
   - Submit tugas
   - Absensi

## 🔧 Troubleshooting Deploy

### Error: Database Connection Failed
**Penyebab**: Hostname, username, password, atau database name salah

**Solusi**:
1. Cek kembali informasi database di Control Panel
2. Pastikan hostname benar (bukan `localhost`, biasanya `sqlXXX.epizy.com`)
3. Pastikan username dan password benar
4. Pastikan database sudah dibuat dan di-import

### Error: File Upload Gagal
**Penyebab**: Permission folder atau ukuran file

**Solusi**:
1. Set permission folder `uploads/` menjadi `755` atau `777`
2. Pastikan ukuran file < 10MB
3. Cek `php.ini` di InfinityFree (via Control Panel → PHP Config)

### Error: 404 Not Found
**Penyebab**: File tidak ada atau path salah

**Solusi**:
1. Pastikan semua file sudah di-upload ke `htdocs/`
2. Pastikan struktur folder benar
3. Cek URL di `config.php` sesuai dengan domain Anda

### Error: 500 Internal Server Error
**Penyebab**: Syntax error atau konfigurasi salah

**Solusi**:
1. Cek error log di Control Panel
2. Cek syntax PHP (pastikan tidak ada error)
3. Cek `.htaccess` (jika ada rule yang tidak kompatibel, hapus)

### Error: Session Tidak Berfungsi
**Penyebab**: Permission session folder

**Solusi**:
1. Cek `php.ini` untuk `session.save_path`
2. Pastikan session folder writable
3. Cek error log untuk detail error

### Error: File Download Tidak Bisa
**Penyebab**: Permission file atau path salah

**Solusi**:
1. Pastikan file ada di folder `uploads/`
2. Cek permission file (harus readable)
3. Cek path di database (`file_uploads` table)

## 📝 Checklist Deploy

- [ ] Hosting InfinityFree sudah dibuat
- [ ] Database sudah dibuat
- [ ] File sudah di-upload ke `htdocs/`
- [ ] Database sudah di-import (`database.sql`)
- [ ] File `inc/config.php` sudah diedit
- [ ] Permission folder `uploads/` sudah diset
- [ ] Login admin berhasil
- [ ] Upload file berhasil
- [ ] Semua fitur utama sudah ditest

## 🔐 Keamanan Setelah Deploy

1. **Ganti Password Admin**
   - Setelah login pertama, segera ganti password admin
   - Gunakan password yang kuat

2. **Hapus File Tidak Perlu**
   - Hapus file `database.sql` dari server (jika sudah di-import)
   - Jangan simpan password di file config yang bisa diakses public

3. **Backup Berkala**
   - Backup database secara berkala
   - Backup folder `uploads/`

4. **Update Regular**
   - Update aplikasi jika ada bug fix
   - Monitor error log

## 📞 Support InfinityFree

Jika ada masalah dengan hosting:
- Dokumentasi: [InfinityFree Docs](https://forum.infinityfree.com/)
- Forum: [InfinityFree Forum](https://forum.infinityfree.com/)
- Support: Via Control Panel → Support

---

**Selamat! Aplikasi Anda sudah online! 🎉**

