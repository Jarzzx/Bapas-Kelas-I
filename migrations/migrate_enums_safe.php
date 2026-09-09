<?php
require_once '../shared/config/database.php';

$conn = getDBConnection();

function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

function getColumnType($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['Type'];
    }
    return null;
}

echo "Memulai migrasi tabel data_klien...\n";

// 1. Handle jenis_kelamin
echo "\n[1/2] Memeriksa kolom 'jenis_kelamin'...\n";
if (columnExists($conn, 'data_klien', 'jenis_kelamin')) {
    $currentType = getColumnType($conn, 'data_klien', 'jenis_kelamin');
    echo "Kolom 'jenis_kelamin' sudah ada (Tipe: $currentType).\n";
    
    if (strpos($currentType, 'enum') === false) {
        echo "Mengubah tipe kolom menjadi ENUM...\n";
        // Safe modify - warning: existing data must match enum or it might be truncated/empty
        $sql = "ALTER TABLE data_klien MODIFY COLUMN jenis_kelamin ENUM('Laki-laki', 'Perempuan') NULL";
        if ($conn->query($sql)) {
            echo "SUKSES: Kolom 'jenis_kelamin' berhasil diubah ke ENUM.\n";
        } else {
            echo "GAGAL: " . $conn->error . "\n";
        }
    } else {
        echo "Tipe kolom sudah ENUM, tidak perlu perubahan.\n";
    }
} else {
    echo "Kolom 'jenis_kelamin' belum ada. Menambahkan kolom...\n";
    $sql = "ALTER TABLE data_klien ADD COLUMN jenis_kelamin ENUM('Laki-laki', 'Perempuan') NULL AFTER nama";
    if ($conn->query($sql)) {
        echo "SUKSES: Kolom 'jenis_kelamin' berhasil ditambahkan.\n";
    } else {
        echo "GAGAL: " . $conn->error . "\n";
    }
}

// 2. Handle agama
echo "\n[2/2] Memeriksa kolom 'agama'...\n";
if (columnExists($conn, 'data_klien', 'agama')) {
    $currentType = getColumnType($conn, 'data_klien', 'agama');
    echo "Kolom 'agama' sudah ada (Tipe: $currentType).\n";
    
    if (strpos($currentType, 'enum') === false) {
        echo "Mengubah tipe kolom menjadi ENUM...\n";
        $sql = "ALTER TABLE data_klien MODIFY COLUMN agama ENUM('Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu') NULL";
        if ($conn->query($sql)) {
            echo "SUKSES: Kolom 'agama' berhasil diubah ke ENUM.\n";
        } else {
            echo "GAGAL: " . $conn->error . "\n";
        }
    } else {
        echo "Tipe kolom sudah ENUM, tidak perlu perubahan.\n";
    }
} else {
    echo "Kolom 'agama' belum ada. Menambahkan kolom...\n";
    $sql = "ALTER TABLE data_klien ADD COLUMN agama ENUM('Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu') NULL AFTER jenis_kelamin";
    if ($conn->query($sql)) {
        echo "SUKSES: Kolom 'agama' berhasil ditambahkan.\n";
    } else {
        echo "GAGAL: " . $conn->error . "\n";
    }
}

echo "\nMigrasi selesai brodi!\n";
$conn->close();
?>
