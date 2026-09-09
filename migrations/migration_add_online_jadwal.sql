-- Migration to add online meeting support to jadwal_bimbingan
USE bapas_db;

ALTER TABLE `jadwal_bimbingan`
ADD COLUMN `jenis_bimbingan` ENUM('tatap_muka', 'daring') DEFAULT 'tatap_muka' AFTER `materi_bimbingan`,
ADD COLUMN `link_meeting` TEXT DEFAULT NULL AFTER `jenis_bimbingan`;
