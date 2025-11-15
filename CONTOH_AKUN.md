# 📋 Contoh Akun untuk Testing

Dokumentasi contoh akun untuk semua role di aplikasi Web Pembelajaran.

## 👨‍💼 Admin

**Email:** `admin@example.com`  
**Password:** `admin123`  
**Role:** `admin`

**Login URL:** `http://localhost/web_MG/admin_login.php`

**Catatan:** Admin harus login melalui URL khusus, tidak ada tombol di halaman home.

---

## 👨‍🏫 Guru

**Email:** `guru@example.com`  
**Password:** `guru123`  
**Role:** `guru`

**Login URL:** `http://localhost/web_MG/auth/login.php?role=guru`

**Atau:** Klik tombol "Masuk sebagai Guru" di halaman home.

---

## 🎓 Murid

**Email:** `murid@example.com`  
**Password:** `murid123`  
**Role:** `murid`

**Login URL:** `http://localhost/web_MG/auth/login.php?role=murid`

**Atau:** Klik tombol "Masuk sebagai Murid" di halaman home.

---

## 🚀 Cara Membuat Contoh Akun

### Opsi 1: Menggunakan Script PHP (Paling Mudah)

1. Akses: `http://localhost/web_MG/create_example_users.php`
2. Script akan otomatis membuat/mengupdate semua contoh akun
3. Hapus file `create_example_users.php` setelah selesai

### Opsi 2: Menggunakan SQL

Jalankan query berikut di phpMyAdmin:

```sql
-- Admin (sudah ada di database.sql)
INSERT INTO `users` (`email`, `password`, `nama`, `role`) 
VALUES ('admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin')
ON DUPLICATE KEY UPDATE password = VALUES(password);

-- Guru
INSERT INTO `users` (`email`, `password`, `nama`, `role`) 
VALUES ('guru@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Budi Santoso', 'guru')
ON DUPLICATE KEY UPDATE password = VALUES(password);

-- Murid
INSERT INTO `users` (`email`, `password`, `nama`, `role`) 
VALUES ('murid@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Andi Pratama', 'murid')
ON DUPLICATE KEY UPDATE password = VALUES(password);
```

**Catatan:** Password hash di atas adalah untuk password "123" (default). Untuk password yang berbeda, gunakan script PHP.

---

## 🔐 Password Hash

Semua password di atas menggunakan hash yang sama (password "123").  
Jika ingin password berbeda, gunakan script PHP yang akan otomatis generate hash yang benar.

---

## ⚠️ Keamanan

- **Jangan gunakan akun contoh ini di production!**
- Ganti semua password setelah testing
- Hapus file `create_example_users.php` setelah selesai
- Buat akun baru dengan password yang kuat untuk production

---

## 📝 Catatan

- Akun admin sudah ada di `database.sql` (default)
- Akun guru dan murid perlu dibuat manual atau via script
- Semua akun contoh menggunakan password sederhana untuk kemudahan testing

