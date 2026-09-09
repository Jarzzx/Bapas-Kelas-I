<?php
require_once 'shared/config/database.php';
$conn = getDBConnection();

echo 'Menjalankan migration tambah kolom isi_laporan...' . PHP_EOL;

$queries = [
    "ALTER TABLE laporan_pengawasan ADD COLUMN IF NOT EXISTS isi_laporan TEXT NULL DEFAULT NULL AFTER catatan"
];

foreach ($queries as $q) {
    echo 'Menjalankan: ' . substr($q, 0, 80) . '...' . PHP_EOL;
    $ok = $conn->query($q);
    if ($ok) {
        echo '  ✅ Berhasil' . PHP_EOL;
    } else {
        echo '  ❌ Gagal: ' . $conn->error . PHP_EOL;
    }
}

$conn->close();
echo PHP_EOL . 'Migration selesai!';
