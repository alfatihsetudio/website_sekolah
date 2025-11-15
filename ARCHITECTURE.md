# Dokumentasi Arsitektur & Backend

Dokumentasi lengkap tentang arsitektur aplikasi dan endpoint backend.

## 🏗️ Arsitektur Aplikasi

### Struktur MVC (Simplified)
Aplikasi menggunakan struktur MVC sederhana tanpa framework:

```
Model      → Database (MySQL) + Database Class
View       → PHP Templates (header.php, footer.php) + HTML
Controller → PHP Files (list.php, create.php, view.php, dll)
```

### Flow Request

```
User Request
    ↓
index.php / route file
    ↓
inc/auth.php (check login & role)
    ↓
inc/helpers.php (helper functions)
    ↓
inc/db.php (database connection)
    ↓
Process Logic
    ↓
Render View (header.php + content + footer.php)
    ↓
Response to User
```

## 📡 Endpoint Documentation

### 1. Autentikasi

#### `GET /auth/login.php`
- **Method**: GET
- **Auth**: Tidak perlu (redirect jika sudah login)
- **Description**: Menampilkan form login
- **Response**: HTML form login

#### `POST /auth/login.php`
- **Method**: POST
- **Auth**: Tidak perlu
- **Parameters**:
  - `email` (string, required): Email user
  - `password` (string, required): Password user
- **Validation**:
  - Email format valid
  - Password tidak kosong
- **Process**:
  1. Sanitize input
  2. Query user dari database
  3. Verify password dengan `password_verify()`
  4. Create session
  5. Regenerate session ID
  6. Redirect ke dashboard sesuai role
- **Response**: Redirect ke dashboard atau error message

#### `GET /auth/logout.php`
- **Method**: GET
- **Auth**: Tidak perlu (bisa dipanggil tanpa login)
- **Description**: Logout user
- **Process**:
  1. Destroy session
  2. Clear session cookie
  3. Redirect ke login
- **Response**: Redirect ke `/auth/login.php`

#### `GET /auth/register.php`
- **Method**: GET
- **Auth**: Admin only
- **Description**: Menampilkan form register user baru
- **Response**: HTML form register

#### `POST /auth/register.php`
- **Method**: POST
- **Auth**: Admin only
- **Parameters**:
  - `nama` (string, required): Nama lengkap
  - `email` (string, required): Email (unique)
  - `password` (string, required, min 6 chars): Password
  - `role` (enum: admin/guru/murid, required): Role user
- **Validation**:
  - Semua field required
  - Email format valid
  - Email belum terdaftar
  - Password minimal 6 karakter
  - Role valid
- **Process**:
  1. Hash password dengan `password_hash()`
  2. Insert ke database
  3. Redirect atau success message
- **Response**: Success message atau error

---

### 2. Dashboard

#### `GET /dashboard/admin.php`
- **Method**: GET
- **Auth**: Admin only
- **Description**: Dashboard admin
- **Data**:
  - Total guru
  - Total murid
  - Total kelas
  - Total materi
- **Response**: HTML dashboard

#### `GET /dashboard/guru.php`
- **Method**: GET
- **Auth**: Guru only
- **Description**: Dashboard guru
- **Data**:
  - Total murid
  - Total materi
  - Total tugas aktif
  - Tugas menunggu penilaian
  - Materi terbaru (5)
  - Tugas terbaru (5)
- **Response**: HTML dashboard

#### `GET /dashboard/murid.php`
- **Method**: GET
- **Auth**: Murid only
- **Description**: Dashboard murid
- **Data**:
  - Kehadiran minggu ini
  - Tugas belum dikumpul (5)
  - Materi terbaru (5)
- **Response**: HTML dashboard

---

### 3. Materi Pembelajaran

#### `GET /materials/list.php`
- **Method**: GET
- **Auth**: Login required
- **Query Parameters**:
  - `search` (string, optional): Keyword pencarian
  - `page` (int, optional, default 1): Halaman
- **Description**: Daftar materi
- **Access Control**:
  - Guru: Hanya materi yang dibuatnya
  - Murid: Hanya materi dari kelasnya
- **Response**: HTML daftar materi dengan pagination

#### `GET /materials/view.php`
- **Method**: GET
- **Auth**: Login required
- **Query Parameters**:
  - `id` (int, required): ID materi
- **Description**: Detail materi
- **Access Control**: Sama seperti list
- **Response**: HTML detail materi

#### `GET /materials/create.php`
- **Method**: GET
- **Auth**: Guru only
- **Description**: Form buat materi baru
- **Response**: HTML form

#### `POST /materials/create.php`
- **Method**: POST
- **Auth**: Guru only
- **Parameters**:
  - `subject_id` (int, required): ID mata pelajaran
  - `judul` (string, required): Judul materi
  - `konten` (text, required): Konten materi
  - `file` (file, optional): File materi
  - `video_link` (url, optional): Link video
- **Validation**:
  - Subject belongs to guru
  - Judul & konten tidak kosong
  - File max 10MB, type valid
- **Process**:
  1. Upload file (jika ada)
  2. Insert ke database
  3. Create notification untuk murid
  4. Redirect ke list
- **Response**: Redirect atau error

#### `GET /materials/edit.php`
- **Method**: GET
- **Auth**: Guru only (owner)
- **Query Parameters**:
  - `id` (int, required): ID materi
- **Description**: Form edit materi
- **Response**: HTML form dengan data existing

#### `POST /materials/edit.php`
- **Method**: POST
- **Auth**: Guru only (owner)
- **Parameters**: Sama seperti create
- **Process**: Update database
- **Response**: Redirect ke detail materi

#### `GET /materials/delete.php`
- **Method**: GET
- **Auth**: Guru only (owner)
- **Query Parameters**:
  - `id` (int, required): ID materi
- **Description**: Hapus materi
- **Process**: Delete dari database
- **Response**: Redirect ke list

---

### 4. Tugas / Penugasan

#### `GET /assignments/list.php`
- **Method**: GET
- **Auth**: Login required
- **Query Parameters**:
  - `page` (int, optional): Halaman
- **Description**: Daftar tugas
- **Access Control**: Sama seperti materi
- **Response**: HTML daftar tugas

#### `GET /assignments/view.php`
- **Method**: GET
- **Auth**: Login required
- **Query Parameters**:
  - `id` (int, required): ID tugas
- **Description**: Detail tugas
- **Response**: HTML detail tugas + submission status (untuk murid)

#### `GET /assignments/create.php`
- **Method**: GET
- **Auth**: Guru only
- **Description**: Form buat tugas baru
- **Response**: HTML form

#### `POST /assignments/create.php`
- **Method**: POST
- **Auth**: Guru only
- **Parameters**:
  - `subject_id` (int, required): ID mata pelajaran
  - `judul` (string, required): Judul tugas
  - `deskripsi` (text, required): Deskripsi tugas
  - `deadline` (datetime, required): Deadline
  - `file` (file, optional): File contoh
- **Validation**:
  - Deadline tidak di masa lalu
  - File max 10MB
- **Process**:
  1. Upload file (jika ada)
  2. Insert ke database
  3. Create notification untuk murid
  4. Redirect ke list
- **Response**: Redirect atau error

#### `GET /assignments/submit.php`
- **Method**: GET
- **Auth**: Murid only
- **Query Parameters**:
  - `id` (int, required): ID tugas
- **Description**: Form submit tugas
- **Response**: HTML form

#### `POST /assignments/submit.php`
- **Method**: POST
- **Auth**: Murid only
- **Parameters**:
  - `file` (file, optional): File tugas
  - `link_drive` (url, optional): Link Google Drive
  - `catatan` (text, optional): Catatan
- **Validation**:
  - Harus ada file ATAU link_drive
  - Student in class
  - Belum submit sebelumnya
- **Process**:
  1. Upload file (jika ada)
  2. Insert submission
  3. Create notification untuk guru
  4. Redirect ke detail tugas
- **Response**: Redirect atau error

#### `GET /assignments/grade.php`
- **Method**: GET
- **Auth**: Guru only (owner)
- **Query Parameters**:
  - `id` (int, required): ID tugas
- **Description**: Daftar submission untuk dinilai
- **Response**: HTML daftar submission

#### `POST /assignments/grade.php`
- **Method**: POST
- **Auth**: Guru only (owner)
- **Parameters**:
  - `submission_id` (int, required): ID submission
  - `nilai` (decimal, required, 0-100): Nilai
  - `feedback` (text, optional): Feedback
- **Validation**:
  - Nilai antara 0-100
- **Process**:
  1. Update submission dengan nilai
  2. Create notification untuk murid
  3. Redirect atau reload
- **Response**: Success message atau error

---

### 5. Absensi

#### `GET /attendance/record.php`
- **Method**: GET
- **Auth**: Guru only
- **Query Parameters**:
  - `class_id` (int, optional): ID kelas
  - `date` (date, optional): Tanggal absensi
- **Description**: Form rekam absensi
- **Response**: HTML form absensi

#### `POST /attendance/record.php`
- **Method**: POST
- **Auth**: Guru only
- **Parameters**:
  - `class_id` (int, required): ID kelas
  - `date` (date, required): Tanggal
  - `attendance[student_id][status]` (enum, required): Status
  - `attendance[student_id][keterangan]` (string, optional): Keterangan
- **Validation**:
  - Class belongs to guru
  - Status valid
- **Process**:
  1. Insert atau update attendance
  2. Redirect atau success message
- **Response**: Success message atau error

#### `GET /attendance/view.php`
- **Method**: GET
- **Auth**: Murid only
- **Query Parameters**:
  - `page` (int, optional): Halaman
- **Description**: Riwayat absensi murid
- **Response**: HTML riwayat absensi + statistik

---

### 6. Notifikasi

#### `GET /notifications/index.php`
- **Method**: GET
- **Auth**: Login required
- **Query Parameters**:
  - `page` (int, optional): Halaman
  - `mark_read` (int, optional): ID notifikasi untuk ditandai dibaca
  - `mark_all_read` (bool, optional): Tandai semua dibaca
- **Description**: Daftar notifikasi
- **Response**: HTML daftar notifikasi

---

### 7. File Download

#### `GET /download.php`
- **Method**: GET
- **Auth**: Login required
- **Query Parameters**:
  - `id` (int, required): ID file
- **Description**: Download file dengan proteksi
- **Access Control**:
  - Admin: Semua file
  - Uploader: File yang diupload
  - Guru: File dari materi/tugas yang dibuatnya
  - Murid: File dari materi/tugas kelasnya atau submission sendiri
- **Process**:
  1. Check access permission
  2. Get file path
  3. Output file dengan proper headers
- **Response**: File download atau error

---

## 🔐 Security Implementation

### 1. Authentication
- Session-based authentication
- Password hashing dengan `password_hash()` (bcrypt)
- Session regeneration dengan `session_regenerate_id()`
- Session timeout: 8 jam

### 2. Authorization
- Role-based access control (RBAC)
- Function `requireRole()` untuk check role
- Function `requireLogin()` untuk check login
- Redirect otomatis jika tidak authorized

### 3. Input Validation
- SQL injection: Prepared statements
- XSS: `htmlspecialchars()` untuk output
- File upload: Type & size validation
- Email: `filter_var()` dengan `FILTER_VALIDATE_EMAIL`

### 4. File Security
- File tidak bisa diakses langsung (via .htaccess)
- Download hanya melalui script dengan permission check
- File rename dengan random name
- Metadata tersimpan di database

---

## 📊 Database Schema

Lihat file `database.sql` untuk schema lengkap.

### Tabel Utama:
- `users`: Data pengguna
- `classes`: Data kelas
- `class_user`: Relasi kelas-murid
- `subjects`: Mata pelajaran
- `materials`: Materi pembelajaran
- `assignments`: Tugas
- `submissions`: Submission tugas
- `attendance`: Absensi
- `notifications`: Notifikasi
- `file_uploads`: Metadata file

---

## 🔄 Helper Functions

### `inc/helpers.php`

#### `formatTanggal($date, $withTime = false)`
Format tanggal ke format Indonesia.

#### `timeAgo($datetime)`
Format waktu relatif (2 jam lalu, 3 hari lalu).

#### `uploadFile($file, $subfolder = '')`
Upload file dengan validasi dan simpan metadata.

#### `createNotification($userId, $title, $message, $link = '')`
Buat notifikasi untuk user.

#### `getUnreadNotificationsCount($userId)`
Get jumlah notifikasi belum dibaca.

#### `sanitize($string)`
Sanitize output untuk mencegah XSS.

#### `getPagination($currentPage, $totalPages, $baseUrl)`
Generate HTML pagination.

#### `getDeadlineStatus($deadline)`
Get status deadline (OK, Urgent, Overdue).

---

## 🎨 Frontend Components

### CSS Classes
- `.card`: Card container
- `.btn`: Button
- `.form-group`: Form group
- `.alert`: Alert message
- `.navbar`: Navigation bar
- `.material-card`: Card untuk materi
- `.assignment-card`: Card untuk tugas
- `.stats-grid`: Grid untuk statistik

### JavaScript Functions
- `openModal(modalId)`: Buka modal
- `closeModal(modalId)`: Tutup modal
- `showToast(message, type)`: Tampilkan toast notification
- `confirmDelete(message)`: Konfirmasi delete

---

## 📝 Notes

- Semua file PHP menggunakan `require_once` untuk include
- Error handling dengan try-catch dan error messages
- Redirect setelah POST untuk mencegah resubmit
- Pagination default: 10 items per page
- File upload max: 10MB
- Session lifetime: 8 jam

---

**Dokumentasi ini akan terus diupdate sesuai perkembangan aplikasi.**

