-- =====================================================
-- MIGRATION: Ensure PK Users Table Structure
-- Memastikan struktur tabel pk_users sudah benar untuk register PK
-- =====================================================
-- Tanggal: 2026-01-12
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- =====================================================
-- 1. Memastikan tabel pk_users ada
-- =====================================================

CREATE TABLE IF NOT EXISTS `pk_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama` varchar(200) NOT NULL,
  `nip` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- 2. Memastikan kolom-kolom yang diperlukan ada
-- =====================================================

-- Check and add username column if not exists
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'pk_users' 
    AND COLUMN_NAME = 'username'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `pk_users` ADD COLUMN `username` varchar(100) NOT NULL AFTER `id`',
    'SELECT "Column username already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add password column if not exists
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'pk_users' 
    AND COLUMN_NAME = 'password'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `pk_users` ADD COLUMN `password` varchar(255) NOT NULL AFTER `username`',
    'SELECT "Column password already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add nama column if not exists
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'pk_users' 
    AND COLUMN_NAME = 'nama'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `pk_users` ADD COLUMN `nama` varchar(200) NOT NULL AFTER `password`',
    'SELECT "Column nama already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add nip column if not exists
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'pk_users' 
    AND COLUMN_NAME = 'nip'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `pk_users` ADD COLUMN `nip` varchar(50) DEFAULT NULL AFTER `nama`',
    'SELECT "Column nip already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add created_at column if not exists
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'pk_users' 
    AND COLUMN_NAME = 'created_at'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `pk_users` ADD COLUMN `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP AFTER `nip`',
    'SELECT "Column created_at already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 3. Memastikan unique constraint untuk username
-- =====================================================

-- Check if unique index exists
SET @idx_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'pk_users' 
    AND INDEX_NAME = 'username'
    AND NON_UNIQUE = 0
);

SET @sql_idx = IF(@idx_exists = 0,
    'ALTER TABLE `pk_users` ADD UNIQUE KEY `username` (`username`)',
    'SELECT "Unique index username already exists" AS message'
);

PREPARE stmt_idx FROM @sql_idx;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

-- =====================================================
-- CATATAN:
-- =====================================================
-- Struktur tabel pk_users yang diperlukan:
-- - id: INT AUTO_INCREMENT PRIMARY KEY
-- - username: VARCHAR(100) NOT NULL UNIQUE
-- - password: VARCHAR(255) NOT NULL (disimpan dalam format MD5)
-- - nama: VARCHAR(200) NOT NULL
-- - nip: VARCHAR(50) DEFAULT NULL (opsional)
-- - created_at: TIMESTAMP DEFAULT CURRENT_TIMESTAMP
--
-- Migration ini akan otomatis skip jika kolom/index sudah ada
-- =====================================================

SELECT 'Migration completed successfully!' AS status;
