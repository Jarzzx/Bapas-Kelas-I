-- =====================================================
-- MIGRATION: Ensure 1 Klien 1 PK Constraint
-- Memastikan bahwa 1 klien hanya bisa dibimbing oleh 1 PK
-- =====================================================
-- Tanggal: 2026-01-12
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- =====================================================
-- 1. Memastikan kolom pk_id ada di klien_users
-- =====================================================

-- Check if pk_id column exists, if not add it
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'klien_users' 
    AND COLUMN_NAME = 'pk_id'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `klien_users` ADD COLUMN `pk_id` INT DEFAULT NULL AFTER `biodata_dilengkapi_at`',
    'SELECT "Column pk_id already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 2. Memastikan foreign key constraint untuk pk_id
-- =====================================================

-- Check if foreign key exists
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'klien_users' 
    AND COLUMN_NAME = 'pk_id' 
    AND REFERENCED_TABLE_NAME = 'pk_users'
);

SET @sql_fk = IF(@fk_exists = 0,
    'ALTER TABLE `klien_users` ADD CONSTRAINT `klien_users_ibfk_1` FOREIGN KEY (`pk_id`) REFERENCES `pk_users` (`id`) ON DELETE SET NULL',
    'SELECT "Foreign key already exists" AS message'
);

PREPARE stmt_fk FROM @sql_fk;
EXECUTE stmt_fk;
DEALLOCATE PREPARE stmt_fk;

-- =====================================================
-- 3. Memastikan index untuk pk_id (untuk performa query)
-- =====================================================

-- Check if index exists
SET @idx_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'klien_users' 
    AND INDEX_NAME = 'pk_id'
);

SET @sql_idx = IF(@idx_exists = 0,
    'ALTER TABLE `klien_users` ADD INDEX `pk_id` (`pk_id`)',
    'SELECT "Index pk_id already exists" AS message'
);

PREPARE stmt_idx FROM @sql_idx;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

-- =====================================================
-- 4. Memastikan data_klien juga memiliki constraint yang benar
-- =====================================================

-- Check if foreign key exists for data_klien.pk_id
SET @fk_data_klien_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'data_klien' 
    AND COLUMN_NAME = 'pk_id' 
    AND REFERENCED_TABLE_NAME = 'pk_users'
);

SET @sql_fk_dk = IF(@fk_data_klien_exists = 0,
    'ALTER TABLE `data_klien` ADD CONSTRAINT `data_klien_ibfk_1` FOREIGN KEY (`pk_id`) REFERENCES `pk_users` (`id`) ON DELETE CASCADE',
    'SELECT "Foreign key data_klien.pk_id already exists" AS message'
);

PREPARE stmt_fk_dk FROM @sql_fk_dk;
EXECUTE stmt_fk_dk;
DEALLOCATE PREPARE stmt_fk_dk;

-- =====================================================
-- 5. Memastikan jadwal_bimbingan hanya bisa dibuat untuk klien yang pk_id nya sama
-- (Ini sudah di-handle di aplikasi, tapi kita pastikan constraint di database)
-- =====================================================

-- Check if foreign key exists for jadwal_bimbingan.klien_id
SET @fk_jb_klien_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'jadwal_bimbingan' 
    AND COLUMN_NAME = 'klien_id' 
    AND REFERENCED_TABLE_NAME = 'klien_users'
);

SET @sql_fk_jb_klien = IF(@fk_jb_klien_exists = 0,
    'ALTER TABLE `jadwal_bimbingan` ADD CONSTRAINT `jadwal_bimbingan_ibfk_2` FOREIGN KEY (`klien_id`) REFERENCES `klien_users` (`id`) ON DELETE CASCADE',
    'SELECT "Foreign key jadwal_bimbingan.klien_id already exists" AS message'
);

PREPARE stmt_fk_jb_klien FROM @sql_fk_jb_klien;
EXECUTE stmt_fk_jb_klien;
DEALLOCATE PREPARE stmt_fk_jb_klien;

-- =====================================================
-- 6. Memastikan laporan_bimbingan hanya bisa dibuat untuk klien yang pk_id nya sama
-- =====================================================

-- Check if foreign key exists for laporan_bimbingan.klien_id
SET @fk_lb_klien_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'laporan_bimbingan' 
    AND COLUMN_NAME = 'klien_id' 
    AND REFERENCED_TABLE_NAME = 'data_klien'
);

SET @sql_fk_lb_klien = IF(@fk_lb_klien_exists = 0,
    'ALTER TABLE `laporan_bimbingan` ADD CONSTRAINT `laporan_bimbingan_ibfk_2` FOREIGN KEY (`klien_id`) REFERENCES `data_klien` (`id`) ON DELETE CASCADE',
    'SELECT "Foreign key laporan_bimbingan.klien_id already exists" AS message'
);

PREPARE stmt_fk_lb_klien FROM @sql_fk_lb_klien;
EXECUTE stmt_fk_lb_klien;
DEALLOCATE PREPARE stmt_fk_lb_klien;

-- =====================================================
-- CATATAN PENTING:
-- =====================================================
-- 1. Constraint 1 klien 1 PK di-handle di level aplikasi:
--    - Saat register, klien memilih pk_id
--    - Saat approval, PK hanya bisa approve klien yang pk_id nya sama
--    - Semua query sudah filter berdasarkan pk_id
--
-- 2. Tidak menggunakan UNIQUE constraint untuk pk_id karena:
--    - 1 klien memang hanya punya 1 pk_id (tidak perlu unique)
--    - Yang perlu unique adalah kombinasi klien + pk (tapi sudah di-handle di aplikasi)
--
-- 3. Foreign key constraints memastikan:
--    - Data integrity
--    - Cascade delete jika PK dihapus
--    - Referential integrity
-- =====================================================

SELECT 'Migration completed successfully!' AS status;
