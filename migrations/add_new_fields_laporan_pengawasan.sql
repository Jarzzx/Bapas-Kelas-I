-- Tambahkan kolom baru ke tabel laporan_pengawasan
-- Untuk mendukung form input baru: Observasi, Wawancara, Koordinasi, Judul, Dasar Hukum, dst

ALTER TABLE laporan_pengawasan
ADD COLUMN IF NOT EXISTS judul_laporan VARCHAR(255) NULL DEFAULT NULL AFTER saran,
ADD COLUMN IF NOT EXISTS dasar_hukum TEXT NULL DEFAULT NULL AFTER judul_laporan,
ADD COLUMN IF NOT EXISTS tujuan_laporan TEXT NULL DEFAULT NULL AFTER dasar_hukum,
ADD COLUMN IF NOT EXISTS ruang_lingkup TEXT NULL DEFAULT NULL AFTER tujuan_laporan,
ADD COLUMN IF NOT EXISTS tempat_ttd VARCHAR(100) NULL DEFAULT 'Pekanbaru' AFTER ruang_lingkup,
ADD COLUMN IF NOT EXISTS observasi TEXT NULL DEFAULT NULL AFTER tempat_ttd,
ADD COLUMN IF NOT EXISTS wawancara TEXT NULL DEFAULT NULL AFTER observasi,
ADD COLUMN IF NOT EXISTS koordinasi TEXT NULL DEFAULT NULL AFTER wawancara;
