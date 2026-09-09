<?php
require_once __DIR__ . '/../shared/config/database.php';

$conn = getDBConnection();

echo "Modifying ENUM to remove CMJB...\n";

// Remove CMJB from ENUM
$sql = "ALTER TABLE data_klien MODIFY COLUMN jenis_integrasi ENUM('PB', 'CB', 'CMB') DEFAULT NULL";

if ($conn->query($sql) === TRUE) {
    echo "Successfully updated ENUM for jenis_integrasi (Removed CMJB).\n";
} else {
    echo "Error updating record: " . $conn->error . "\n";
}

$conn->close();
?>
