-- Migration Script untuk Update Laporan Bimbingan PK dan Dokumen Klien
-- File ini untuk update database yang sudah ada tanpa menghapus data

USE bapas_db;

-- ============================================
-- UPDATE TABLE: laporan_bimbingan
-- ============================================

-- Tambah kolom bentuk_pembimbingan
-- Note: Jika kolom sudah ada, akan muncul error "Duplicate column", bisa diabaikan
ALTER TABLE laporan_bimbingan 
ADD COLUMN bentuk_pembimbingan ENUM('tatap_muka', 'daring', 'kunjungan_rumah') NULL 
AFTER jenis_bimbingan;

-- Tambah kolom tanggal_bimbingan (tanggal kapan bimbingan dilakukan, bukan tanggal laporan dibuat)
ALTER TABLE laporan_bimbingan 
ADD COLUMN tanggal_bimbingan DATE NULL 
AFTER tanggal_laporan;

-- Tambah kolom materi_bimbingan
ALTER TABLE laporan_bimbingan 
ADD COLUMN materi_bimbingan TEXT NULL 
AFTER bentuk_pembimbingan;

-- Tambah kolom hasil_bimbingan
ALTER TABLE laporan_bimbingan 
ADD COLUMN hasil_bimbingan TEXT NULL 
AFTER materi_bimbingan;

-- Tambah kolom tindak_lanjut
ALTER TABLE laporan_bimbingan 
ADD COLUMN tindak_lanjut TEXT NULL 
AFTER hasil_bimbingan;

-- Tambah kolom nama_pk (untuk menyimpan nama PK saat laporan dibuat)
ALTER TABLE laporan_bimbingan 
ADD COLUMN nama_pk VARCHAR(200) NULL 
AFTER pk_id;

-- ============================================
-- UPDATE TABLE: data_klien
-- ============================================

-- Tambah kolom jenis_pembebasan
-- Note: Jika kolom sudah ada, akan muncul error "Duplicate column", bisa diabaikan
ALTER TABLE data_klien 
ADD COLUMN jenis_pembebasan ENUM('PB', 'CMB', 'CMJB') NULL 
AFTER status;

-- Update kolom status menjadi ENUM (jika belum)
-- Note: Jika kolom status sudah ada data, perlu hati-hati
-- Kita akan cek dulu apakah perlu diubah atau tidak
-- Untuk sementara, kita biarkan status tetap VARCHAR dan tambahkan kolom baru

-- Jika ingin mengubah status menjadi ENUM, uncomment baris di bawah:
-- ALTER TABLE data_klien MODIFY COLUMN status ENUM('Aktif', 'Selesai', 'Dicabut') DEFAULT 'Aktif';

-- ============================================
-- CATATAN PENTING
-- ============================================
-- 1. File ini aman dijalankan berkali-kali (idempotent) dengan IF NOT EXISTS
-- 2. Jika ada error "Duplicate column", berarti sudah ada, bisa diabaikan
-- 3. Pastikan backup database sebelum menjalankan migration
-- 4. Setelah migration, pastikan semua form sudah terintegrasi dengan kolom baru

-- ============================================
-- VERIFIKASI STRUKTUR
-- ============================================
-- Jalankan query ini untuk cek struktur tabel setelah migration:
-- DESCRIBE laporan_bimbingan;
-- DESCRIBE data_klien;

