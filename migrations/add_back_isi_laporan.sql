-- Tambahkan kembali kolom isi_laporan untuk backward compatibility
-- Kolom ini bisa NULL dan diisi dengan gabungan dari observasi, wawancara, dan koordinasi

ALTER TABLE laporan_pengawasan ADD COLUMN IF NOT EXISTS isi_laporan TEXT NULL DEFAULT NULL AFTER catatan;
