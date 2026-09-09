
ALTER TABLE `data_klien`
ADD COLUMN `agama` VARCHAR(50) DEFAULT NULL AFTER `jenis_kelamin`,
ADD COLUMN `jenis_kelamin` ENUM('Laki-laki', 'Perempuan') DEFAULT NULL AFTER `no_registrasi_perkara`;
