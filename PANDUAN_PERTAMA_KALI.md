# 🎯 Panduan Pertama Kali Menggunakan Aplikasi

## ✅ Step 1: Import Database (Jika Belum)

1. Buka **phpMyAdmin**: `http://localhost/phpmyadmin`
2. Pilih database **`web_markazlugoh`**
3. Klik tab **"Import"**
4. Pilih file **`database.sql`** dari folder proyek
5. Klik **"Go"** atau **"Import"**
6. Tunggu hingga selesai

**Catatan**: Database sudah berisi user admin default!

---

## 🔐 Step 2: Login Pertama Kali

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

3. **Setelah login**, Anda akan masuk ke **Dashboard Admin**

---

## 👥 Step 3: Register User Baru (Guru & Murid)

### Register Guru:
1. Di Dashboard Admin, klik **"Register User"**
2. Isi form:
   - **Nama**: Nama guru (contoh: "Budi Santoso")
   - **Email**: Email guru (contoh: "guru@example.com")
   - **Password**: Password untuk guru
   - **Role**: Pilih **"Guru"**
3. Klik **"Daftarkan User"**

### Register Murid:
1. Klik **"Register User"** lagi
2. Isi form:
   - **Nama**: Nama murid (contoh: "Andi Pratama")
   - **Email**: Email murid (contoh: "murid@example.com")
   - **Password**: Password untuk murid
   - **Role**: Pilih **"Murid"**
3. Klik **"Daftarkan User"**

---

## 🏫 Step 4: Buat Kelas (Admin)

1. Di Dashboard Admin, klik **"Manajemen Kelas"** atau akses:
   ```
   http://localhost/web_MG/classes/list.php
   ```

2. Klik **"+ Tambah Kelas"**

3. Isi form:
   - **Nama Kelas**: Contoh "X IPA 1"
   - **Guru Wali**: Pilih guru yang sudah dibuat
   - **Deskripsi**: (Opsional)
4. Klik **"Simpan Kelas"**

---

## 👨‍🎓 Step 5: Tambahkan Murid ke Kelas

**Catatan**: Fitur ini belum ada di UI, jadi perlu dilakukan via database atau bisa ditambahkan manual.

### Via phpMyAdmin:
1. Buka phpMyAdmin
2. Pilih database `web_markazlugoh`
3. Buka tabel `class_user`
4. Klik **"Insert"**
5. Isi:
   - `class_id`: ID kelas yang sudah dibuat (lihat di tabel `classes`)
   - `user_id`: ID murid yang sudah dibuat (lihat di tabel `users`, role = 'murid')
6. Klik **"Go"**

**Contoh**:
- Jika kelas ID = 1, murid ID = 2
- Insert: `class_id = 1`, `user_id = 2`

---

## 📚 Step 6: Buat Mata Pelajaran (Opsional - Via Database)

**Catatan**: Fitur ini juga belum ada di UI, perlu ditambahkan via database.

### Via phpMyAdmin:
1. Buka tabel `subjects`
2. Klik **"Insert"**
3. Isi:
   - `class_id`: ID kelas
   - `nama_mapel`: Nama mata pelajaran (contoh: "Matematika")
   - `guru_id`: ID guru yang mengajar
   - `deskripsi`: (Opsional)
4. Klik **"Go"**

**Contoh**:
- `class_id = 1`
- `nama_mapel = "Matematika"`
- `guru_id = 2` (ID guru yang sudah dibuat)

---

## 🧪 Step 7: Test Fitur sebagai Guru

1. **Logout** dari admin
2. **Login** sebagai guru (email & password yang sudah dibuat)
3. Anda akan masuk ke **Dashboard Guru**

### Test Buat Materi:
1. Klik **"+ Tambah Materi"**
2. Pilih mata pelajaran (jika sudah dibuat)
3. Isi:
   - **Judul**: "Materi Test"
   - **Konten**: "Ini adalah konten materi test"
   - **File**: (Opsional) Upload file PDF
   - **Link Video**: (Opsional) Link YouTube
4. Klik **"Simpan Materi"**

### Test Buat Tugas:
1. Klik **"+ Tambah Tugas"**
2. Pilih mata pelajaran
3. Isi:
   - **Judul**: "Tugas Test"
   - **Deskripsi**: "Ini adalah deskripsi tugas"
   - **Deadline**: Pilih tanggal & waktu
   - **File Contoh**: (Opsional)
4. Klik **"Simpan Tugas"**

---

## 🎓 Step 8: Test Fitur sebagai Murid

1. **Logout** dari guru
2. **Login** sebagai murid (email & password yang sudah dibuat)
3. Anda akan masuk ke **Dashboard Murid**

### Test Lihat Materi:
1. Klik **"Daftar Materi"**
2. Materi yang dibuat guru akan muncul
3. Klik materi untuk melihat detail
4. Download file jika ada

### Test Submit Tugas:
1. Klik **"Daftar Tugas"**
2. Pilih tugas yang belum dikumpulkan
3. Klik **"Kumpulkan"**
4. Upload file atau isi link Google Drive
5. Klik **"Kumpulkan Tugas"**

---

## 📊 Step 9: Test Fitur Absensi (Guru)

1. **Login** sebagai guru
2. Klik **"Absensi"** atau akses:
   ```
   http://localhost/web_MG/attendance/record.php
   ```
3. Pilih kelas dan tanggal
4. Set status untuk setiap murid (Hadir, Izin, Sakit, Alfa, Terlambat)
5. Klik **"Simpan Absensi"**

### Lihat Absensi sebagai Murid:
1. **Login** sebagai murid
2. Klik **"Riwayat Absensi"**
3. Lihat statistik dan riwayat kehadiran

---

## ✅ Checklist Testing

- [ ] Login sebagai admin berhasil
- [ ] Register user baru berhasil
- [ ] Buat kelas berhasil
- [ ] Tambah murid ke kelas (via database)
- [ ] Buat mata pelajaran (via database)
- [ ] Login sebagai guru berhasil
- [ ] Buat materi berhasil
- [ ] Buat tugas berhasil
- [ ] Login sebagai murid berhasil
- [ ] Lihat materi berhasil
- [ ] Submit tugas berhasil
- [ ] Rekam absensi berhasil
- [ ] Lihat absensi berhasil

---

## 🎉 Selesai!

Jika semua checklist ✅, aplikasi sudah berfungsi dengan baik!

**Tips**:
- Gunakan browser developer tools (F12) untuk melihat error jika ada
- Cek error log PHP jika ada masalah
- Pastikan folder `uploads/` writable untuk upload file

**Selamat menggunakan aplikasi!** 🚀

