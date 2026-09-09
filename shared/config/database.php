<?php
// Database configuration
// Saat upload ke hosting (InfinityFree), GANTI value dibawah ini dengan data dari "MySQL Databases" di Panel Hosting.

define('DB_HOST', 'localhost'); // <-- Ganti dengan MySQL Hostname (misal: sql304.infinityfree.com)
define('DB_USER', 'root');      // <-- Ganti dengan MySQL Username (misal: if0_3829xxxx)
define('DB_PASS', '');          // <-- Ganti dengan Password vPanel/FTP
define('DB_NAME', 'bapas_db');  // <-- Ganti dengan Database Name (misal: if0_3829xxxx_bapas_db)

// Set PHP Timezone to WIB (Asia/Jakarta)
date_default_timezone_set('Asia/Jakarta');

// Create database connection
function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        $conn->set_charset("utf8mb4");
        
        // Set MySQL Timezone to +07:00 (WIB)
        $conn->query("SET time_zone = '+07:00'");
        
        return $conn;
    } catch (Exception $e) {
        die("Database connection error: " . $e->getMessage());
    }
}
?>