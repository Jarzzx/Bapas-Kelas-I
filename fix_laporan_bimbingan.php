<?php
// Fix laporan_bimbingan table structure
require_once 'shared/config/database.php';

$conn = getDBConnection();

echo "<h2>Fixing 'laporan_bimbingan' table...</h2>";

// 1. Check Primary Key
$check_pk = $conn->query("SHOW KEYS FROM laporan_bimbingan WHERE Key_name = 'PRIMARY'");
$pk_exists = ($check_pk && $check_pk->num_rows > 0);

if (!$pk_exists) {
    echo "Primary Key is MISSING. Adding Primary Key...<br>";
    $sql = "ALTER TABLE laporan_bimbingan MODIFY COLUMN id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY";
} else {
    echo "Primary Key exists. Ensuring AUTO_INCREMENT...<br>";
    $sql = "ALTER TABLE laporan_bimbingan MODIFY COLUMN id INT(11) NOT NULL AUTO_INCREMENT";
}

if ($conn->query($sql)) {
    echo "<h3 style='color:green'>Success: 'id' column is now AUTO_INCREMENT.</h3>";
} else {
    echo "<h3 style='color:red'>Error: " . $conn->error . "</h3>";
    
    // Fallback: If duplicate entry '0' exists
    if (strpos($conn->error, "Duplicate entry '0'") !== false) {
        echo "Duplicate entry '0' detected. Attempting to fix IDs...<br>";
        
        // Fix duplicate IDs
        $rows = $conn->query("SELECT id, tanggal_laporan FROM laporan_bimbingan WHERE id = 0");
        if ($rows) {
            $new_id = $conn->query("SELECT MAX(id) FROM laporan_bimbingan")->fetch_row()[0] ?? 0;
            while ($row = $rows->fetch_assoc()) {
                $new_id++;
                // We can't identify unique rows easily if ID is 0 for all.
                // We'll limit to 1 per update to fix them sequentially
                $conn->query("UPDATE laporan_bimbingan SET id = $new_id WHERE id = 0 LIMIT 1");
                echo "Updated row with ID 0 -> ID $new_id<br>";
            }
            
            // Retry ALTER TABLE
            if ($conn->query($sql)) {
                echo "<h3 style='color:green'>Success: 'id' column is now AUTO_INCREMENT (after ID fix).</h3>";
            } else {
                echo "<h3 style='color:red'>Retry Failed: " . $conn->error . "</h3>";
            }
        }
    }
}

$conn->close();
?>