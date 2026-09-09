<?php
// Script Diagnosa Path Hosting untuk Trae AI
// Silakan akses file ini di browser: domain.com/pk/cek_path_hosting.php

header('Content-Type: text/plain');

echo "=== DIAGNOSA PATH HOSTING ===\n";
echo "Waktu: " . date('Y-m-d H:i:s') . "\n";
echo "Script Path: " . __FILE__ . "\n";
echo "Dir Name (__DIR__): " . __DIR__ . "\n";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Realpath '..': " . realpath('..') . "\n";
echo "\n";

echo "=== PENCARIAN KOP.PNG ===\n";
$candidates = [
    '../asset/images/kop.png',
    '../assets/images/kop.png',
    '../asset/image/kop.png',
    dirname(__DIR__) . '/asset/images/kop.png',
    dirname(__DIR__) . '/assets/images/kop.png',
    $_SERVER['DOCUMENT_ROOT'] . '/asset/images/kop.png',
    $_SERVER['DOCUMENT_ROOT'] . '/assets/images/kop.png',
    $_SERVER['DOCUMENT_ROOT'] . '/pengawasan/asset/images/kop.png',
    $_SERVER['DOCUMENT_ROOT'] . '/htdocs/asset/images/kop.png', // InfinityFree specific?
    '/home/vol15_2/epizy.com/htdocs/asset/images/kop.png' // Guessing
];

$found = false;
foreach ($candidates as $path) {
    echo "Check: $path ... ";
    if (file_exists($path)) {
        echo "[ADA] (Size: " . filesize($path) . " bytes)\n";
        $found = true;
        // Coba baca
        $content = @file_get_contents($path);
        if ($content) {
            echo "   -> Read Success (" . strlen($content) . " bytes)\n";
            $mime = mime_content_type($path);
            echo "   -> MIME: $mime\n";
        } else {
            echo "   -> READ FAILED!\n";
        }
    } else {
        echo "[TIDAK ADA]\n";
    }
}

if (!$found) {
    echo "\n[WARNING] File KOP tidak ditemukan di kandidat manapun!\n";
    echo "Listing directory ../asset/images/ (jika ada):\n";
    $dir = dirname(__DIR__) . '/asset/images';
    if (is_dir($dir)) {
        print_r(scandir($dir));
    } else {
        echo "Dir $dir tidak ditemukan.\n";
        echo "Coba list ../assets/images/ :\n";
        $dir2 = dirname(__DIR__) . '/assets/images';
        if (is_dir($dir2)) {
            print_r(scandir($dir2));
        } else {
            echo "Dir $dir2 juga tidak ditemukan.\n";
        }
    }
}

echo "\n=== END DIAGNOSTIC ===\n";
?>