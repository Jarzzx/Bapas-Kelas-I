-- =====================================================
-- MIGRATION: Rename jenis_pembebasan to jenis_integrasi
-- Mengganti nama kolom dari jenis_pembebasan menjadi jenis_integrasi
-- =====================================================
-- Tanggal: 2026-01-12
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- =====================================================
-- 1. Rename kolom jenis_pembebasan menjadi jenis_integrasi di tabel data_klien
-- =====================================================

-- Check if column jenis_pembebasan exists
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'data_klien' 
    AND COLUMN_NAME = 'jenis_pembebasan'
);

-- Check if column jenis_integrasi already exists
SET @col_integrasi_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'data_klien' 
    AND COLUMN_NAME = 'jenis_integrasi'
);

-- If jenis_pembebasan exists and jenis_integrasi doesn't, rename it
SET @sql = IF(@col_exists > 0 AND @col_integrasi_exists = 0,
    'ALTER TABLE `data_klien` CHANGE COLUMN `jenis_pembebasan` `jenis_integrasi` ENUM(\'PB\',\'CMB\',\'CMJB\') DEFAULT NULL',
    IF(@col_integrasi_exists > 0,
        'SELECT "Column jenis_integrasi already exists" AS message',
        'SELECT "Column jenis_pembebasan not found" AS message'
    )
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- CATATAN:
-- =====================================================
-- Kolom jenis_pembebasan di tabel data_klien akan diubah menjadi jenis_integrasi
-- Nilai ENUM tetap sama: PB, CMB, CMJB
-- Migration ini aman dan akan skip jika kolom sudah diubah atau tidak ditemukan
-- =====================================================

SELECT 'Migration completed successfully!' AS status;
