# 🚀 QUICK START GUIDE

Panduan cepat untuk menjalankan aplikasi Web Pembelajaran.

## ✅ Checklist Sebelum Menjalankan

### 1. Persyaratan Sistem
- [x] PHP 7.4 atau lebih tinggi
- [x] MySQL 5.7 atau lebih tinggi
- [x] Apache web server (XAMPP/WAMP/LAMP)
- [x] phpMyAdmin (untuk import database)

### 2. File yang Sudah Ada
- [x] Semua file PHP sudah dibuat
- [x] Database SQL sudah ada (`database.sql`)
- [x] CSS dan JS sudah ada
- [x] Struktur folder sudah lengkap

---

## 📝 Langkah-Langkah Menjalankan

### Step 1: Test Koneksi & Environment

1. **Buka browser** dan akses:
   ```
   http://localhost/web_MG/test_connection.php
   ```

2. **Cek hasil test**:
   - ✅ PHP Version: Harus 7.4+
   - ✅ Extensions: mysqli, mbstring, fileinfo, session harus loaded
   - ✅ File Structure: Semua file harus exists
   - ✅ Folder Permissions: Folder uploads harus writable
   - ⚠️ Database Connection: Akan error jika database belum dibuat (ini normal)

3. **Jika ada error**, perbaiki sesuai yang ditampilkan

### Step 2: Buat Database

1. **Buka phpMyAdmin**:
   ```
   http://localhost/phpmyadmin
   ```

2. **Buat database baru**:
   - Klik "New" atau "Baru"
   - Nama database: `web_markazlugoh`
   - Collation: `utf8mb4_unicode_ci`
   - Klik "Create"

3. **Import SQL**:
   - Pilih database `web_markazlugoh`
   - Klik tab "Import"
   - Pilih file `database.sql` dari folder proyek
   - Klik "Go" atau "Import"
   - Tunggu hingga selesai

### Step 3: Konfigurasi Database

1. **Edit file `inc/config.php`**:
   ```php
   // Database Configuration
   define('DB_HOST', 'localhost');        // Untuk XAMPP biasanya 'localhost'
   define('DB_USER', 'root');            // Username MySQL (default XAMPP: 'root')
   define('DB_PASS', '');                // Password MySQL (default XAMPP: kosong)
   define('DB_NAME', 'web_markazlugoh'); // Nama database yang sudah dibuat
   ```

2. **Untuk XAMPP default**:
   - DB_HOST: `localhost`
   - DB_USER: `root`
   - DB_PASS: `` (kosong)
   - DB_NAME: `web_markazlugoh`

### Step 4: Set Permission Folder

**Windows (XAMPP)**:
- Folder `uploads/` biasanya sudah writable
- Jika error upload, klik kanan folder → Properties → Security → Edit → Allow "Write"

**Linux/Mac**:
```bash
chmod 755 uploads/
chmod 755 uploads/materials/
chmod 755 uploads/assignments/
chmod 755 uploads/submissions/
```

### Step 5: Test Aplikasi

1. **Akses aplikasi**:
   ```
   http://localhost/web_MG/
   ```
   Atau langsung:
   ```
   http://localhost/web_MG/auth/login.php
   ```

2. **Login dengan admin default**:
   - **Email**: `admin@example.com`
   - **Password**: `admin123`

3. **Setelah login**:
   - Anda akan di-redirect ke Dashboard Admin
   - **PENTING**: Segera ganti password admin!

---

## 🎯 Langkah Selanjutnya

### 1. Ganti Password Admin
- Dashboard Admin → (belum ada fitur ganti password di UI, bisa langsung edit di database atau tambahkan fitur)

### 2. Register User Baru
- Dashboard Admin → "Register User"
- Buat user Guru dan Murid untuk testing

### 3. Buat Kelas
- Dashboard Admin → "Daftar Kelas" → "Tambah Kelas"
- Assign guru ke kelas
- Tambahkan murid ke kelas (via database atau buat fitur)

### 4. Test Fitur
- Guru: Buat materi dan tugas
- Murid: Lihat materi, submit tugas
- Guru: Nilai tugas
- Test absensi

---

## ⚠️ Troubleshooting

### Error: "Koneksi database gagal"
**Solusi**:
1. Pastikan MySQL service berjalan (XAMPP Control Panel → Start MySQL)
2. Cek `inc/config.php` (host, user, pass, dbname)
3. Pastikan database sudah dibuat

### Error: "File tidak ditemukan" atau 404
**Solusi**:
1. Pastikan semua file sudah di-upload ke folder `htdocs/web_MG/`
2. Cek struktur folder sesuai dokumentasi
3. Pastikan Apache service berjalan

### Error: "Permission denied" saat upload
**Solusi**:
1. Set permission folder `uploads/` menjadi 755 atau 777
2. Pastikan folder `uploads/` dan subfoldernya writable

### Error: "Call to undefined function"
**Solusi**:
1. Pastikan semua file di folder `inc/` ada
2. Cek apakah ada typo di nama function
3. Pastikan `require_once` sudah benar

### Error: "Session tidak berfungsi"
**Solusi**:
1. Pastikan folder session writable
2. Cek `php.ini`: `session.save_path`
3. Restart Apache

---

## ✅ Checklist Final

Sebelum menggunakan aplikasi, pastikan:

- [ ] Database sudah dibuat dan di-import
- [ ] File `inc/config.php` sudah diedit dengan setting database yang benar
- [ ] Folder `uploads/` sudah writable
- [ ] Bisa login dengan admin default
- [ ] Tidak ada error di halaman login
- [ ] Dashboard bisa diakses setelah login

---

## 🎉 Siap Digunakan!

Jika semua checklist ✅, aplikasi sudah siap digunakan!

**Default Login**:
- Email: `admin@example.com`
- Password: `admin123`

**Hapus file `test_connection.php` setelah testing selesai!**

---

**Selamat menggunakan aplikasi Web Pembelajaran!** 🚀

