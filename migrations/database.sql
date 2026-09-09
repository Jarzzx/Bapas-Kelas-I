-- Database schema for BAPAS (Balai Pemasyarakatan)
CREATE DATABASE IF NOT EXISTS bapas_db;
USE bapas_db;

-- Table for PK (Pembimbing Kemasyarakatan)
CREATE TABLE IF NOT EXISTS pk_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nama VARCHAR(200) NOT NULL,
    nip VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table for Klien Pemasyarakatan
CREATE TABLE IF NOT EXISTS klien_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nama VARCHAR(200) NOT NULL,
    no_registrasi VARCHAR(50),
    pk_id INT,
    status_approval ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    alamat TEXT,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    no_telepon VARCHAR(20),
    email VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pk_id) REFERENCES pk_users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES pk_users(id) ON DELETE SET NULL
);

-- Table for Data Klien (supervised by PK)
CREATE TABLE IF NOT EXISTS data_klien (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pk_id INT NOT NULL,
    nama VARCHAR(200) NOT NULL,
    no_registrasi VARCHAR(50) UNIQUE NOT NULL,
    alamat TEXT,
    jenis_kasus VARCHAR(200),
    tanggal_mulai DATE,
    status VARCHAR(50) DEFAULT 'Aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pk_id) REFERENCES pk_users(id) ON DELETE CASCADE
);

-- Table for Laporan Pengawasan
CREATE TABLE IF NOT EXISTS laporan_pengawasan (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pk_id INT NOT NULL,
    klien_id INT NOT NULL,
    tanggal_laporan TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    isi_laporan TEXT NOT NULL,
    status_klien VARCHAR(100),
    catatan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pk_id) REFERENCES pk_users(id) ON DELETE CASCADE,
    FOREIGN KEY (klien_id) REFERENCES data_klien(id) ON DELETE CASCADE
);

-- Table for Laporan Bimbingan
CREATE TABLE IF NOT EXISTS laporan_bimbingan (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pk_id INT NOT NULL,
    klien_id INT NOT NULL,
    tanggal_laporan TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    jenis_bimbingan VARCHAR(100),
    isi_laporan TEXT NOT NULL,
    hasil_evaluasi TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pk_id) REFERENCES pk_users(id) ON DELETE CASCADE,
    FOREIGN KEY (klien_id) REFERENCES data_klien(id) ON DELETE CASCADE
);

-- Insert sample PK user (password: admin123)
-- Password menggunakan MD5
INSERT INTO pk_users (username, password, nama, nip) VALUES 
('pk001', MD5('admin123'), 'Budi Santoso', '198001012001011001');

-- Insert sample Klien user (password: klien123) - sudah approved
-- Password menggunakan MD5
INSERT INTO klien_users (username, password, nama, no_registrasi, pk_id, status_approval, approved_by) VALUES 
('klien001', MD5('klien123'), 'Ahmad Fauzi', 'KL-2024-001', 1, 'approved', 1);

-- Catatan: Password disimpan dalam format MD5 untuk keamanan

