<?php
require_once '../shared/config/database.php';
require_once '../shared/config/auth.php';
requirePKLogin();

header('Content-Type: application/json');

$conn = getDBConnection();
$pk_id = $_SESSION['user_id'];
$klien_id = isset($_GET['klien_id']) ? (int)$_GET['klien_id'] : 0;

if (!$klien_id) {
    echo json_encode(['success' => false, 'message' => 'Klien ID tidak valid']);
    exit;
}

// Get data_klien_id from klien_id
$stmt = $conn->prepare("SELECT no_registrasi FROM klien_users WHERE id = ? AND pk_id = ?");
$stmt->bind_param("ii", $klien_id, $pk_id);
$stmt->execute();
$result = $stmt->get_result();
$klien_data = $result->fetch_assoc();
$stmt->close();

if (!$klien_data) {
    echo json_encode(['success' => false, 'message' => 'Klien tidak ditemukan']);
    exit;
}

// Get tanggal from jadwal_bimbingan
$jadwal_dates = $conn->query("
    SELECT DISTINCT DATE(tanggal_bimbingan) as tanggal
    FROM jadwal_bimbingan
    WHERE klien_id = $klien_id
    ORDER BY tanggal DESC
")->fetch_all(MYSQLI_ASSOC);

// Get data_klien_id
$stmt = $conn->prepare("SELECT id FROM data_klien WHERE no_registrasi = ? AND pk_id = ? LIMIT 1");
$stmt->bind_param("si", $klien_data['no_registrasi'], $pk_id);
$stmt->execute();
$result = $stmt->get_result();
$data_klien_row = $result->fetch_assoc();
$stmt->close();

$laporan_dates = [];
if ($data_klien_row) {
    $data_klien_id = $data_klien_row['id'];
    // Get tanggal from laporan_bimbingan (yang sudah dibuat sebelumnya)
    $laporan_dates = $conn->query("
        SELECT DISTINCT DATE(tanggal_bimbingan) as tanggal
        FROM laporan_bimbingan
        WHERE klien_id = $data_klien_id
        AND tanggal_bimbingan IS NOT NULL
        ORDER BY tanggal DESC
    ")->fetch_all(MYSQLI_ASSOC);
}

// Combine and deduplicate dates
$all_dates = [];
foreach ($jadwal_dates as $row) {
    $date = $row['tanggal'];
    if (!isset($all_dates[$date])) {
        $all_dates[$date] = [
            'value' => $date,
            'label' => date('d/m/Y', strtotime($date))
        ];
    }
}
foreach ($laporan_dates as $row) {
    $date = $row['tanggal'];
    if (!isset($all_dates[$date])) {
        $all_dates[$date] = [
            'value' => $date,
            'label' => date('d/m/Y', strtotime($date))
        ];
    }
}

// Sort by date descending
usort($all_dates, function($a, $b) {
    return strtotime($b['value']) - strtotime($a['value']);
});

echo json_encode([
    'success' => true,
    'tanggal' => array_values($all_dates)
]);

$conn->close();
?>

