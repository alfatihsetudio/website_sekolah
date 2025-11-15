-- ============================================
-- Script SQL untuk membuat user fatih
-- ============================================
-- Nama: fatih
-- Email: fatih@example.com
-- Password: 123
-- Role: admin
-- ============================================

-- Hapus user jika sudah ada (opsional)
-- DELETE FROM users WHERE email = 'fatih@example.com';

-- Insert user baru
INSERT INTO `users` (`email`, `password`, `nama`, `role`) 
VALUES (
    'fatih@example.com', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
    'fatih', 
    'admin'
);

-- Catatan: 
-- Password hash di atas adalah untuk password "123"
-- Jika ingin password berbeda, generate hash baru dengan:
-- SELECT password_hash('password_anda', PASSWORD_DEFAULT);

-- Untuk melihat user yang sudah dibuat:
-- SELECT id, email, nama, role FROM users;

