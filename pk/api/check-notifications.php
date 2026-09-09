<?php
/**
 * API Endpoint untuk check notifications PK
 * Digunakan oleh auto-refresh script
 */

require_once '../../shared/config/database.php';
require_once '../../shared/config/auth.php';

header('Content-Type: application/json');

// Check authentication
if (!isPKLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$conn = getDBConnection();
$pk_id = $_SESSION['user_id'];

// Get pending approvals count
$pending_count = $conn->query("SELECT COUNT(*) as total FROM klien_users WHERE status_approval = 'pending' AND pk_id = $pk_id")->fetch_assoc()['total'];

// Get biodata complete count
$biodata_lengkap_count = $conn->query("
    SELECT COUNT(*) as total
    FROM klien_users ku
    LEFT JOIN data_klien dk ON ku.no_registrasi = dk.no_registrasi AND dk.pk_id = $pk_id
    WHERE ku.pk_id = $pk_id 
    AND ku.status_approval = 'approved'
    AND ku.biodata_lengkap = 'sudah'
    AND (dk.id IS NULL OR dk.dokumen_lengkap = 'belum')
")->fetch_assoc()['total'];

// Get statistics
$total_klien = $conn->query("SELECT COUNT(*) as total FROM data_klien WHERE pk_id = $pk_id")->fetch_assoc()['total'];
$total_pengawasan = $conn->query("SELECT COUNT(*) as total FROM laporan_pengawasan WHERE pk_id = $pk_id")->fetch_assoc()['total'];
$total_bimbingan = $conn->query("SELECT COUNT(*) as total FROM laporan_bimbingan WHERE pk_id = $pk_id")->fetch_assoc()['total'];

$conn->close();

echo json_encode([
    'success' => true,
    'pending_count' => (int)$pending_count,
    'biodata_lengkap_count' => (int)$biodata_lengkap_count,
    'statistics' => [
        'total_klien' => (int)$total_klien,
        'total_pengawasan' => (int)$total_pengawasan,
        'total_bimbingan' => (int)$total_bimbingan
    ],
    'timestamp' => time()
]);
