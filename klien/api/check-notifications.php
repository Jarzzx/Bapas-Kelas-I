<?php
/**
 * API Endpoint untuk check notifications Klien
 * Digunakan oleh auto-refresh script
 */

require_once '../../shared/config/database.php';
require_once '../../shared/config/auth.php';

header('Content-Type: application/json');

// Check authentication
if (!isKlienLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$conn = getDBConnection();
$klien_id = $_SESSION['user_id'];

// Get jadwal bimbingan terbaru
$jadwal_bimbingan = $conn->query("
    SELECT jb.*, ku.nama as klien_nama
    FROM jadwal_bimbingan jb
    JOIN klien_users ku ON jb.klien_id = ku.id
    WHERE jb.klien_id = $klien_id
    AND jb.status = 'terjadwal'
    ORDER BY jb.tanggal_bimbingan ASC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Get laporan status (pending verification)
$laporan_pending = $conn->query("
    SELECT COUNT(*) as total
    FROM laporan_bimbingan
    WHERE klien_id IN (SELECT id FROM data_klien WHERE no_registrasi IN (SELECT no_registrasi FROM klien_users WHERE id = $klien_id))
    AND status_verifikasi = 'pending'
    AND dibuat_oleh = 'klien'
")->fetch_assoc()['total'];

$conn->close();

echo json_encode([
    'success' => true,
    'jadwal_bimbingan' => $jadwal_bimbingan,
    'laporan_status' => [
        'pending' => (int)$laporan_pending
    ],
    'timestamp' => time()
]);
