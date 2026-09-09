-- Migration: Add no_registrasi_perkara to data_klien table
-- Description: Menambahkan kolom untuk menyimpan nomor registrasi perkara asli (manual input dari surat)
-- Author: Assistant
-- Date: 2026-02-02

-- Menambahkan kolom no_registrasi_perkara setelah no_registrasi
-- Menggunakan VARCHAR(100) untuk fleksibilitas format nomor surat
-- Default NULL karena data lama mungkin belum punya
ALTER TABLE `data_klien` 
ADD COLUMN `no_registrasi_perkara` VARCHAR(100) DEFAULT NULL COMMENT 'Nomor Registrasi Perkara Asli dari Surat' AFTER `no_registrasi`;

-- (Optional) Menambahkan Index jika sering dicari berdasarkan nomor perkara asli
-- ALTER TABLE `data_klien` ADD INDEX `idx_no_registrasi_perkara` (`no_registrasi_perkara`);
