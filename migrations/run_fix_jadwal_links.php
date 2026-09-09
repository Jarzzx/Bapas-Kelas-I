<?php
require_once __DIR__ . '/../shared/config/database.php';

$conn = getDBConnection();
echo "Migration started: Fixing missing jadwal_bimbingan_id in laporan_bimbingan...\n";

// 1. Find reports with missing jadwal_bimbingan_id
$stmt = $conn->prepare("
    SELECT lb.id, lb.klien_id, lb.tanggal_bimbingan, lb.created_at
    FROM laporan_bimbingan lb
    WHERE lb.jadwal_bimbingan_id IS NULL OR lb.jadwal_bimbingan_id = 0
");
$stmt->execute();
$reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$count = 0;
foreach ($reports as $report) {
    // Try to match with a schedule
    // Logic: Same Klien (User ID matching) AND Same Date
    // Note: lb.klien_id is `data_klien.id` (usually), but let's be careful.
    // In `laporan-bimbingan.php` (client side), it inserts `data_klien_id` into `klien_id` column.
    
    // We need to map `data_klien.id` back to `klien_users.id` to check `jadwal_bimbingan` (which uses `klien_users.id` in `klien_id` column?)
    // Let's check schemas.
    // jadwal_bimbingan: pk_id, klien_id (klien_users.id?), data_klien_id (data_klien.id)
    // laporan_bimbingan: klien_id (data_klien.id)
    
    // So we should match `laporan_bimbingan.klien_id` == `jadwal_bimbingan.data_klien_id`
    
    $check_date = date('Y-m-d', strtotime($report['tanggal_bimbingan'] ?? $report['created_at']));
    
    $find_jadwal = $conn->prepare("
        SELECT id, lokasi_bimbingan, jenis_bimbingan, link_meeting
        FROM jadwal_bimbingan
        WHERE data_klien_id = ? 
        AND DATE(tanggal_bimbingan) = ?
        LIMIT 1
    ");
    
    $find_jadwal->bind_param("is", $report['klien_id'], $check_date);
    $find_jadwal->execute();
    $jadwal_res = $find_jadwal->get_result();
    
    if ($jadwal = $jadwal_res->fetch_assoc()) {
        echo "Found match for Report #{$report['id']} -> Jadwal #{$jadwal['id']}\n";
        
        // Update the report
        $update = $conn->prepare("
            UPDATE laporan_bimbingan 
            SET jadwal_bimbingan_id = ?,
                lokasi_laporan = COALESCE(NULLIF(lokasi_laporan, ''), ?),
                bentuk_pembimbingan = COALESCE(NULLIF(bentuk_pembimbingan, ''), ?)
            WHERE id = ?
        ");
        
        if (!$update) {
            echo "Prepare failed: " . $conn->error . "\n";
            continue;
        }

        $update->bind_param("issi", 
            $jadwal['id'], 
            $jadwal['lokasi_bimbingan'],
            $jadwal['jenis_bimbingan'],
            $report['id']
        );
        
        if ($update->execute()) {
            $count++;
            echo "Updated Report #{$report['id']}\n";
        } else {
            echo "Failed to update Report #{$report['id']}: " . $conn->error . "\n";
        }
    }
}

echo "Migration completed. Fixed $count reports.\n";
