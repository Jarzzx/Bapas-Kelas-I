<?php
// Script Diagnosa Path & Gambar untuk Hosting
// Simpan di folder pk/cek_gambar.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Diagnosa Gambar Hosting</h1>";
echo "<p>Script ini akan memeriksa apakah file gambar KOP dan Dokumentasi bisa dibaca oleh server.</p>";

$root_dir = dirname(__DIR__);
echo "<h3>Informasi Path Server</h3>";
echo "<ul>";
echo "<li><strong>__DIR__ (Lokasi Script):</strong> " . __DIR__ . "</li>";
echo "<li><strong>dirname(__DIR__) (Root Project):</strong> " . $root_dir . "</li>";
echo "<li><strong>\$_SERVER['DOCUMENT_ROOT']:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</li>";
echo "</ul>";

echo "<h3>Pemeriksaan Folder Assets (KOP)</h3>";
$possible_asset_dirs = [
    $root_dir . '/asset',
    $root_dir . '/assets',
    '../asset',
    '../assets',
    $_SERVER['DOCUMENT_ROOT'] . '/asset',
    $_SERVER['DOCUMENT_ROOT'] . '/assets'
];

foreach ($possible_asset_dirs as $dir) {
    echo "Checking dir: <code>$dir</code> ... ";
    if (is_dir($dir)) {
        echo "<span style='color:green'>ADA (Is Dir)</span><br>";
        echo "Isi folder:";
        $files = scandir($dir);
        if ($files) {
            echo "<ul>";
            foreach ($files as $f) {
                if ($f == '.' || $f == '..') continue;
                echo "<li>$f</li>";
            }
            echo "</ul>";
            
            // Cek images/kop.png didalamnya
            $img_dir = $dir . '/images';
            if (is_dir($img_dir)) {
                 echo "-> Ada folder images. Isi images/: " . implode(', ', scandir($img_dir)) . "<br>";
                 $kop_path = $img_dir . '/kop.png';
                 if (file_exists($kop_path)) {
                     echo "-> <strong>kop.png DITEMUKAN!</strong> Path: $kop_path<br>";
                     echo "-> Coba baca file... ";
                     $content = @file_get_contents($kop_path);
                     if ($content) {
                         echo "<span style='color:green'>SUKSES DIBACA (" . strlen($content) . " bytes)</span>";
                         echo "<br><img src='data:image/png;base64," . base64_encode($content) . "' style='width:100px; border:1px solid black'>";
                     } else {
                         echo "<span style='color:red'>GAGAL DIBACA (Permission/Blocked?)</span>";
                         print_r(error_get_last());
                     }
                 } else {
                     echo "-> kop.png TIDAK ADA di $kop_path<br>";
                 }
            } else {
                echo "-> Tidak ada folder images<br>";
            }
            
        } else {
            echo " (Gagal scandir)";
        }
    } else {
        echo "<span style='color:red'>TIDAK ADA</span><br>";
    }
    echo "<hr>";
}

echo "<h3>Cek Fungsi PHP</h3>";
echo "file_get_contents enabled: " . (function_exists('file_get_contents') ? 'YA' : 'TIDAK') . "<br>";
echo "allow_url_fopen: " . ini_get('allow_url_fopen') . "<br>";
echo "GD Library: " . (extension_loaded('gd') ? 'YA' : 'TIDAK') . "<br>";

?>