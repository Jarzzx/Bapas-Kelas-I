-- Script ini untuk MENGUBAH kolom yang SUDAH ADA menjadi tipe ENUM
-- Gunakan ini karena error "Duplicate column name" (artinya kolom sudah ada)

-- 1. Ubah kolom jenis_kelamin menjadi tipe ENUM
ALTER TABLE data_klien MODIFY COLUMN jenis_kelamin ENUM('Laki-laki', 'Perempuan') NULL;

-- 2. Ubah kolom agama menjadi tipe ENUM
-- Catatan: Jika error "Unknown column 'agama'", ganti kata 'MODIFY' menjadi 'ADD'
ALTER TABLE data_klien MODIFY COLUMN agama ENUM('Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu') NULL;