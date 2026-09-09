-- MySQL dump 10.13  Distrib 8.0.30, for Win64 (x86_64)
--
-- Host: localhost    Database: bapas_db
-- ------------------------------------------------------
-- Server version	8.0.30

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `data_klien`
--

DROP TABLE IF EXISTS `data_klien`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `data_klien` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pk_id` int NOT NULL,
  `nama` varchar(200) NOT NULL,
  `no_registrasi` varchar(50) NOT NULL,
  `alamat` text,
  `jenis_kasus` varchar(200) DEFAULT NULL,
  `pasal_pidana` text,
  `tanggal_mulai_bimbingan` date DEFAULT NULL,
  `tanggal_akhir_bimbingan` date DEFAULT NULL,
  `masa_bimbingan_tahun` int DEFAULT NULL,
  `masa_bimbingan_bulan` int DEFAULT NULL,
  `masa_bimbingan_hari` int DEFAULT NULL,
  `dokumen_lengkap` enum('belum','sudah') DEFAULT 'belum',
  `tanggal_mulai` date DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Aktif',
  `jenis_integrasi` enum('PB','CMB','CMJB') DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `no_registrasi` (`no_registrasi`),
  KEY `pk_id` (`pk_id`),
  CONSTRAINT `data_klien_ibfk_1` FOREIGN KEY (`pk_id`) REFERENCES `pk_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `data_klien`
--

LOCK TABLES `data_klien` WRITE;
/*!40000 ALTER TABLE `data_klien` DISABLE KEYS */;
INSERT INTO `data_klien` VALUES (7,3,'Miftahul Jannah','BPS-001-2026','Jalan Garuda, Kelurahan Labuh Baru Timur, Payung Sekaki, Pekanbaru, Pekanbaru Kota, Riau, Sumatra, 28123, Indonesia',NULL,'Pencurian Hewan Ternak','2026-03-12','2028-12-20',2,9,8,'sudah','2026-01-12','Aktif','PB','2026-01-11 21:18:32','2026-01-11 21:24:48');
/*!40000 ALTER TABLE `data_klien` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jadwal_bimbingan`
--

DROP TABLE IF EXISTS `jadwal_bimbingan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jadwal_bimbingan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pk_id` int NOT NULL,
  `klien_id` int NOT NULL,
  `data_klien_id` int DEFAULT NULL,
  `judul_bimbingan` varchar(200) NOT NULL,
  `materi_bimbingan` text NOT NULL,
  `tanggal_bimbingan` datetime NOT NULL,
  `lokasi_bimbingan` text,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `status` enum('terjadwal','selesai','dibatalkan') DEFAULT 'terjadwal',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `pk_id` (`pk_id`),
  KEY `klien_id` (`klien_id`),
  KEY `data_klien_id` (`data_klien_id`),
  CONSTRAINT `jadwal_bimbingan_ibfk_1` FOREIGN KEY (`pk_id`) REFERENCES `pk_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jadwal_bimbingan_ibfk_2` FOREIGN KEY (`klien_id`) REFERENCES `klien_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jadwal_bimbingan_ibfk_3` FOREIGN KEY (`data_klien_id`) REFERENCES `data_klien` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jadwal_bimbingan`
--

LOCK TABLES `jadwal_bimbingan` WRITE;
/*!40000 ALTER TABLE `jadwal_bimbingan` DISABLE KEYS */;
INSERT INTO `jadwal_bimbingan` VALUES (5,3,13,7,'Bimbingan Konseling','-','2026-01-14 10:00:00','Jalan Garuda, Kelurahan Labuh Baru Timur, Payung Sekaki, Pekanbaru, Pekanbaru Kota, Riau, Sumatra, 28123, Indonesia',0.51445760,101.42679040,'selesai','2026-01-11 22:23:47','2026-01-11 22:24:16');
/*!40000 ALTER TABLE `jadwal_bimbingan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `klien_users`
--

DROP TABLE IF EXISTS `klien_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `klien_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama` varchar(200) NOT NULL,
  `no_registrasi` varchar(50) DEFAULT NULL,
  `alamat` text,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `no_telepon` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `nik` varchar(16) DEFAULT NULL,
  `foto_klien` varchar(255) DEFAULT NULL,
  `riwayat_pendidikan` varchar(100) DEFAULT NULL,
  `agama` varchar(50) DEFAULT NULL,
  `status_pernikahan` enum('Belum Menikah','Menikah','Cerai Hidup','Cerai Mati') DEFAULT NULL,
  `pekerjaan` varchar(100) DEFAULT NULL,
  `tempat_lahir` varchar(100) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `biodata_lengkap` enum('belum','sudah') DEFAULT 'belum',
  `biodata_dilengkapi_at` timestamp NULL DEFAULT NULL,
  `pk_id` int DEFAULT NULL,
  `status_approval` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` int DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `alamat_jalan` varchar(255) DEFAULT NULL COMMENT 'Nama jalan',
  `alamat_kelurahan` varchar(100) DEFAULT NULL COMMENT 'Kelurahan/Desa',
  `alamat_kecamatan` varchar(100) DEFAULT NULL COMMENT 'Kecamatan',
  `alamat_kota` varchar(100) DEFAULT NULL COMMENT 'Kota/Kabupaten',
  `alamat_provinsi` varchar(100) DEFAULT NULL COMMENT 'Provinsi',
  `alamat_kode_pos` varchar(10) DEFAULT NULL COMMENT 'Kode Pos',
  `alamat_negara` varchar(100) DEFAULT 'Indonesia' COMMENT 'Negara',
  `alamat_rt` varchar(10) DEFAULT NULL COMMENT 'RT',
  `alamat_rw` varchar(10) DEFAULT NULL COMMENT 'RW',
  `alamat_nomor` varchar(50) DEFAULT NULL COMMENT 'Nomor rumah/bangunan',
  `alamat_formatted` text COMMENT 'Alamat lengkap yang sudah diformat',
  `alamat_api_data` json DEFAULT NULL COMMENT 'Data mentah dari API geocoding',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `pk_id` (`pk_id`),
  KEY `klien_users_ibfk_2` (`approved_by`),
  CONSTRAINT `klien_users_ibfk_1` FOREIGN KEY (`pk_id`) REFERENCES `pk_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `klien_users_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `pk_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `klien_users`
--

LOCK TABLES `klien_users` WRITE;
/*!40000 ALTER TABLE `klien_users` DISABLE KEYS */;
INSERT INTO `klien_users` VALUES (13,'nuril','6ccec9e158c33da99d60705400602d1d','Miftahul Jannah','BPS-001-2026','Jalan Garuda, Kelurahan Labuh Baru Timur, Payung Sekaki, Pekanbaru, Pekanbaru Kota, Riau, Sumatra, 28123, Indonesia',0.51445760,101.42679040,'085775566578','ijarzx@gmail.com','3201010101010002','uploads/foto_klien/klien_13_1768166378.jpeg','S1','Islam','Belum Menikah','Belum Bekerja','Pekanbaru','2006-12-29','sudah','2026-01-11 21:19:38',3,'approved',3,'2026-01-11 21:18:32','2026-01-11 21:18:25','Jalan Garuda','Kelurahan Labuh Baru Timur','Payung Sekaki','Pekanbaru','Riau','28123','Indonesia','','','','Jalan Garuda, Kelurahan Labuh Baru Timur, Payung Sekaki, Pekanbaru, Pekanbaru Kota, Riau, Sumatra, 28123, Indonesia','{\"lat\": \"0.5143961\", \"lon\": \"101.4270221\", \"name\": \"Jalan Garuda\", \"type\": \"residential\", \"class\": \"highway\", \"osm_id\": 306168164, \"address\": {\"city\": \"Pekanbaru\", \"road\": \"Jalan Garuda\", \"state\": \"Riau\", \"region\": \"Sumatra\", \"country\": \"Indonesia\", \"village\": \"Kelurahan Labuh Baru Timur\", \"district\": \"Pekanbaru Kota\", \"postcode\": \"28123\", \"country_code\": \"id\", \"city_district\": \"Payung Sekaki\", \"ISO3166-2-lvl3\": \"ID-SM\", \"ISO3166-2-lvl4\": \"ID-RI\"}, \"licence\": \"Data © OpenStreetMap contributors, ODbL 1.0. http://osm.org/copyright\", \"osm_type\": \"way\", \"place_id\": 236802785, \"importance\": 0.05338916380560003, \"place_rank\": 26, \"addresstype\": \"road\", \"boundingbox\": [\"0.5088902\", \"0.5170941\", \"101.4253602\", \"101.4275511\"], \"display_name\": \"Jalan Garuda, Kelurahan Labuh Baru Timur, Payung Sekaki, Pekanbaru, Pekanbaru Kota, Riau, Sumatra, 28123, Indonesia\"}');
/*!40000 ALTER TABLE `klien_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `laporan_bimbingan`
--

DROP TABLE IF EXISTS `laporan_bimbingan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `laporan_bimbingan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pk_id` int NOT NULL,
  `nama_pk` varchar(200) DEFAULT NULL,
  `klien_id` int NOT NULL,
  `jadwal_bimbingan_id` int DEFAULT NULL,
  `dibuat_oleh` enum('pk','klien') DEFAULT 'pk',
  `foto_bimbingan` varchar(255) DEFAULT NULL,
  `lokasi_laporan` text,
  `latitude_laporan` decimal(10,8) DEFAULT NULL,
  `longitude_laporan` decimal(11,8) DEFAULT NULL,
  `keluhan` text,
  `status_verifikasi` enum('pending','approved','rejected') DEFAULT 'pending',
  `verified_by` int DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `tanggal_laporan` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `tanggal_bimbingan` date DEFAULT NULL,
  `jenis_bimbingan` varchar(100) DEFAULT NULL,
  `bentuk_pembimbingan` enum('tatap_muka','daring','kunjungan_rumah') DEFAULT NULL,
  `materi_bimbingan` text,
  `hasil_bimbingan` text,
  `tindak_lanjut` text,
  `isi_laporan` text NOT NULL,
  `hasil_evaluasi` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `pk_id` (`pk_id`),
  KEY `klien_id` (`klien_id`),
  KEY `fk_jadwal_bimbingan` (`jadwal_bimbingan_id`),
  KEY `fk_verified_by` (`verified_by`),
  CONSTRAINT `fk_jadwal_bimbingan` FOREIGN KEY (`jadwal_bimbingan_id`) REFERENCES `jadwal_bimbingan` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `pk_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `laporan_bimbingan_ibfk_1` FOREIGN KEY (`pk_id`) REFERENCES `pk_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `laporan_bimbingan_ibfk_2` FOREIGN KEY (`klien_id`) REFERENCES `data_klien` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `laporan_bimbingan`
--

LOCK TABLES `laporan_bimbingan` WRITE;
/*!40000 ALTER TABLE `laporan_bimbingan` DISABLE KEYS */;
INSERT INTO `laporan_bimbingan` VALUES (7,3,NULL,7,5,'klien','uploads/foto_bimbingan/bimbingan_13_1768170256.png','Jalan Garuda, Kelurahan Labuh Baru Timur, Payung Sekaki, Pekanbaru, Pekanbaru Kota, Riau, Sumatra, 28123, Indonesia',0.51445760,101.42679040,'Sejauh Ini belum ada','approved',3,'2026-01-11 22:24:41','2026-01-11 22:24:16',NULL,NULL,NULL,NULL,NULL,NULL,'Keluhan: Sejauh Ini belum ada\n\nLokasi: Jalan Garuda, Kelurahan Labuh Baru Timur, Payung Sekaki, Pekanbaru, Pekanbaru Kota, Riau, Sumatra, 28123, Indonesia',NULL,'2026-01-11 22:24:16'),(8,3,'Nur Ilmil Fadilah',7,NULL,'pk',NULL,NULL,NULL,NULL,NULL,'pending',NULL,NULL,'2026-01-11 22:25:51','2026-01-14',NULL,'tatap_muka','-','Cukup Baik','Lanjutkan dengan pertemuan bulan depan','Sejauh ini klien memberikan respon yang positif saat di rehabilitasi',NULL,'2026-01-11 22:25:51');
/*!40000 ALTER TABLE `laporan_bimbingan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `laporan_pengawasan`
--

DROP TABLE IF EXISTS `laporan_pengawasan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `laporan_pengawasan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pk_id` int NOT NULL,
  `klien_id` int NOT NULL,
  `tanggal_laporan` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `isi_laporan` text NOT NULL,
  `status_klien` varchar(100) DEFAULT NULL,
  `catatan` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `pk_id` (`pk_id`),
  KEY `klien_id` (`klien_id`),
  CONSTRAINT `laporan_pengawasan_ibfk_1` FOREIGN KEY (`pk_id`) REFERENCES `pk_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `laporan_pengawasan_ibfk_2` FOREIGN KEY (`klien_id`) REFERENCES `data_klien` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `laporan_pengawasan`
--

LOCK TABLES `laporan_pengawasan` WRITE;
/*!40000 ALTER TABLE `laporan_pengawasan` DISABLE KEYS */;
INSERT INTO `laporan_pengawasan` VALUES (2,3,7,'2026-01-11 22:26:41','Klien menujukkan respon yang sangat positif','Baik','-','2026-01-11 22:26:41');
/*!40000 ALTER TABLE `laporan_pengawasan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pk_users`
--

DROP TABLE IF EXISTS `pk_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pk_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama` varchar(200) NOT NULL,
  `nip` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pk_users`
--

LOCK TABLES `pk_users` WRITE;
/*!40000 ALTER TABLE `pk_users` DISABLE KEYS */;
INSERT INTO `pk_users` VALUES (3,'deram123','21232f297a57a5a743894a0e4a801fc3','Nur Ilmil Fadilah','2110031806027','2026-01-06 14:13:24');
/*!40000 ALTER TABLE `pk_users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-01-12  5:32:10
