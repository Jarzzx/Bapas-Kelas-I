<?php
require_once '../shared/config/database.php';

// Allow direct access for fix
$conn = getDBConnection();

$target_reg = 'BPS-005-2026';
$wrong_date_start = '2026-10-02 00:00:00';
$wrong_date_end = '2026-10-02 23:59:59';
$correct_date = '2026-02-10 10:00:00'; // Assume 10:00 if time lost, or keep original time?
// Better to just swap month/day if possible, but safer to hardcode for this specific request.

echo "<h1>Fix Date Issue</h1>";
echo "<p>Target Klien: $target_reg</p>";

// 1. Find Client ID
$stmt = $conn->prepare("SELECT id, nama FROM data_klien WHERE no_registrasi = ?");
$stmt->bind_param("s", $target_reg);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    die("Klien tidak ditemukan.");
}
$klien = $res->fetch_assoc();
$klien_id = $klien['id'];
echo "<p>Klien ID: $klien_id ({$klien['nama']})</p>";

// 2. Update Laporan Bimbingan
echo "<h3>Updating Laporan Bimbingan...</h3>";
$sql_lb = "UPDATE laporan_bimbingan 
           SET tanggal_bimbingan = '2026-02-10' 
           WHERE klien_id = $klien_id AND tanggal_bimbingan = '2026-10-02'";
$conn->query($sql_lb);
if ($conn->affected_rows > 0) {
    echo "<p style='color:green'>Success: Updated {$conn->affected_rows} rows in laporan_bimbingan.</p>";
} else {
    echo "<p style='color:orange'>Notice: No rows updated in laporan_bimbingan (maybe already fixed or date not matching).</p>";
    // Check if it exists with time
    $sql_check = "SELECT * FROM laporan_bimbingan WHERE klien_id = $klien_id";
    $res_check = $conn->query($sql_check);
    echo "Current records:<br>";
    while($row = $res_check->fetch_assoc()) {
        echo "ID: {$row['id']} - Tanggal: {$row['tanggal_bimbingan']}<br>";
    }
}

// 3. Update Jadwal Bimbingan
echo "<h3>Updating Jadwal Bimbingan...</h3>";
// Jadwal uses DATETIME usually
$sql_jb = "UPDATE jadwal_bimbingan 
           SET tanggal_bimbingan = '2026-02-10 10:00:00' 
           WHERE klien_id = $klien_id AND DATE(tanggal_bimbingan) = '2026-10-02'";
$conn->query($sql_jb);
if ($conn->affected_rows > 0) {
    echo "<p style='color:green'>Success: Updated {$conn->affected_rows} rows in jadwal_bimbingan.</p>";
} else {
    echo "<p style='color:orange'>Notice: No rows updated in jadwal_bimbingan.</p>";
}

echo "<hr><p>Done. Please check the Laporan Bimbingan page again.</p>";
echo "<a href='laporan-bimbingan.php'>Go back to Laporan Bimbingan</a>";
?>