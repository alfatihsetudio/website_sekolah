-- Tambah kolom baru untuk wali murid dan kontak KM
ALTER TABLE classes
  ADD COLUMN walimurid VARCHAR(255) DEFAULT NULL,
  ADD COLUMN no_telpon_wali VARCHAR(50) DEFAULT NULL,
  ADD COLUMN nama_km VARCHAR(255) DEFAULT NULL,
  ADD COLUMN no_telpon_km VARCHAR(50) DEFAULT NULL;

-- Opsional: jika Anda ingin merename kolom 'deskripsi' menjadi 'old_deskripsi' sebagai backup:
-- ALTER TABLE classes CHANGE deskripsi old_deskripsi TEXT;

-- Opsional: jika Anda yakin ingin mengganti/deskripsi menjadi kolom nomor telepon langsung (HATI-HATI: data akan kehilangan format teks semula)
-- ALTER TABLE classes CHANGE deskripsi no_telpon_wali VARCHAR(50) DEFAULT NULL;

-- Contoh copy data dari old_deskripsi ke no_telpon_wali (jika sebelumnya anda menyimpan no telp di deskripsi):
-- UPDATE classes SET no_telpon_wali = old_deskripsi WHERE old_deskripsi IS NOT NULL AND old_deskripsi <> '';

-- Jangan lupa: setelah migrasi, periksa index/privileges dan sesuaikan form create/edit agar menulis ke kolom baru.
