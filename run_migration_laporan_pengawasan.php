<?php
require_once 'shared/config/database.php';
$conn = getDBConnection();

echo 'Menjalankan migration...' . PHP_EOL;

$queries = [
    "ALTER TABLE laporan_pengawasan ADD COLUMN IF NOT EXISTS judul_laporan VARCHAR(255) NULL DEFAULT NULL AFTER saran",
    "ALTER TABLE laporan_pengawasan ADD COLUMN IF NOT EXISTS dasar_hukum TEXT NULL DEFAULT NULL AFTER judul_laporan",
    "ALTER TABLE laporan_pengawasan ADD COLUMN IF NOT EXISTS tujuan_laporan TEXT NULL DEFAULT NULL AFTER dasar_hukum",
    "ALTER TABLE laporan_pengawasan ADD COLUMN IF NOT EXISTS ruang_lingkup TEXT NULL DEFAULT NULL AFTER tujuan_laporan",
    "ALTER TABLE laporan_pengawasan ADD COLUMN IF NOT EXISTS tempat_ttd VARCHAR(100) NULL DEFAULT 'Pekanbaru' AFTER ruang_lingkup",
    "ALTER TABLE laporan_pengawasan ADD COLUMN IF NOT EXISTS observasi TEXT NULL DEFAULT NULL AFTER tempat_ttd",
    "ALTER TABLE laporan_pengawasan ADD COLUMN IF NOT EXISTS wawancara TEXT NULL DEFAULT NULL AFTER observasi",
    "ALTER TABLE laporan_pengawasan ADD COLUMN IF NOT EXISTS koordinasi TEXT NULL DEFAULT NULL AFTER wawancara"
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
