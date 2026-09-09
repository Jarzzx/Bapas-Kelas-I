<?php
ob_start(); // Prevent whitespace output
// Prevent timeout and memory issues on shared hosting
ini_set('memory_limit', '256M');
set_time_limit(60);
require_once '../vendor/autoload.php';
require_once '../shared/config/database.php';
require_once '../shared/config/auth.php';
requireKlienLogin();

use Dompdf\Dompdf;
use Dompdf\Options;

$conn = getDBConnection();
$klien_id = $_SESSION['user_id'];
$laporan_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get laporan data
// Check access: Join with klien_users (ku) to verify the logged in user owns this report
$stmt = $conn->prepare("
    SELECT lb.*, 
           dk.nama as klien_nama, dk.no_registrasi, dk.no_registrasi_perkara, dk.alamat, dk.pasal_pidana,
           dk.tanggal_mulai_bimbingan, dk.tanggal_akhir_bimbingan, dk.jenis_integrasi, 
           dk.status as status_klien, dk.agama, dk.jenis_kelamin,
           ku.tempat_lahir, ku.tanggal_lahir, ku.riwayat_pendidikan, ku.pekerjaan, ku.status_pernikahan, ku.agama as agama_user, ku.jenis_kelamin as jenis_kelamin_user,
           pk.nama as nama_pk, pk.nip as nip_pk,
           jb.jenis_bimbingan as jadwal_jenis_bimbingan, jb.lokasi_bimbingan, jb.link_meeting, jb.materi_bimbingan as materi_jadwal, jb.tanggal_bimbingan as jadwal_tanggal_bimbingan
    FROM laporan_bimbingan lb
    JOIN data_klien dk ON lb.klien_id = dk.id
    LEFT JOIN klien_users ku ON dk.no_registrasi = ku.no_registrasi
    JOIN pk_users pk ON lb.pk_id = pk.id
    LEFT JOIN jadwal_bimbingan jb ON lb.jadwal_bimbingan_id = jb.id
    WHERE lb.id = ? AND ku.id = ?
");
$stmt->bind_param("ii", $laporan_id, $klien_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Laporan tidak ditemukan atau Anda tidak memiliki akses!');
}

$laporan = $result->fetch_assoc();
$stmt->close();
// $conn->close(); // Keep connection open for later use

// Setup DOMPDF
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
// Disable chroot to allow flexible path reading (we use base64 anyway)
// $options->set('chroot', realpath('..')); 
$dompdf = new Dompdf($options);

// DEFINE ROOT PATH (Solusi Paling Ampuh untuk Hosting)
$root_path = $_SERVER['DOCUMENT_ROOT'];
if (substr($root_path, -1) == '/') {
    $root_path = substr($root_path, 0, -1);
}

/**
 * HELPER: Robust Image Reader (No HTTP/Loopback)
 */
function find_file_case_insensitive($path) {
    // 1. Check exact match first
    if (file_exists($path)) return $path;
    
    // 2. Check case-insensitive match in the directory
    $directory = dirname($path);
    $filename = basename($path);
    
    if (!file_exists($directory) || !is_dir($directory)) {
        return false;
    }
    
    $files = scandir($directory);
    if ($files === false) return false;
    
    foreach ($files as $file) {
        if (strtolower($file) === strtolower($filename)) {
            return $directory . DIRECTORY_SEPARATOR . $file;
        }
    }
    return false;
}

function read_image_as_data_uri($candidates, &$debug_log = []) {
    foreach ($candidates as $raw_path) {
        $log_entry = "Checking: " . $raw_path;
        
        // Resolve path to realpath if possible to handle .. and symlinks
        $path = $raw_path;
        
        // 1. Try reading directly (Best for local files)
        $data = @file_get_contents($path);
        
        // 2. If failed and path is relative, try resolving with __DIR__
        if ($data === false && strpos($path, '/') !== 0 && strpos($path, ':') === false) {
             $abs_path = __DIR__ . '/' . $path;
             $log_entry .= " -> Try Abs: $abs_path";
             $data = @file_get_contents($abs_path);
             if ($data !== false) $path = $abs_path;
        }

        // 3. If still failed, try case-insensitive search
        if ($data === false) {
            $found_path = find_file_case_insensitive($path);
            if ($found_path) {
                $log_entry .= " -> Found Case-Insensitive: $found_path";
                $data = @file_get_contents($found_path);
                $path = $found_path;
            }
        }

        if ($data) {
            // VALIDATE IMAGE CONTENT
            $is_image = false;
            $mime = '';
            
            // Fallback: Check Magic Bytes FIRST (Faster/Safer)
            $header = substr($data, 0, 4);
            $hex = bin2hex($header);
            
            if (strpos($hex, '89504e47') === 0) { // PNG
                $is_image = true;
                $mime = 'image/png';
            } elseif (strpos($hex, 'ffd8ff') === 0) { // JPG
                $is_image = true;
                $mime = 'image/jpeg';
            } elseif (strpos($hex, '47494638') === 0) { // GIF
                $is_image = true;
                $mime = 'image/gif';
            }

            // Secondary check using GD if available and magic bytes failed/uncertain
            if (!$is_image && function_exists('getimagesizefromstring')) {
                $info = @getimagesizefromstring($data);
                if ($info && isset($info['mime'])) {
                    $is_image = true;
                    $mime = $info['mime'];
                }
            }
            
            if ($is_image && $mime) {
                $log_entry .= " [LOADED: " . strlen($data) . " bytes]";
                $debug_log[] = $log_entry;
                return 'data:' . $mime . ';base64,' . base64_encode($data);
            } else {
                $log_entry .= " [INVALID_CONTENT_HEX: $hex]";
            }
        } else {
            $log_entry .= " [READ_FAIL]";
            $err = error_get_last();
            if ($err) $log_entry .= " Err: " . $err['message'];
        }
        $debug_log[] = $log_entry;
    }
    return '';
}

// Initialize debug log
$debug_log = [];

// 1. SETUP KOP SURAT (Dynamic File Read)
$candidates_kop = [
    // 1. Relative from script (klien/) to asset/
    '../asset/images/kop.png',
    '../assets/images/kop.png',
    '../asset/image/kop.png',
    
    // 2. Absolute path via __DIR__
    dirname(__DIR__) . '/asset/images/kop.png',
    dirname(__DIR__) . '/assets/images/kop.png',
    
    // 3. Document Root based (for hosting)
    $_SERVER['DOCUMENT_ROOT'] . '/asset/images/kop.png',
    $_SERVER['DOCUMENT_ROOT'] . '/assets/images/kop.png',
    $_SERVER['DOCUMENT_ROOT'] . '/pengawasan/asset/images/kop.png',
    
    // 4. Fallback for public_html structure
    str_replace('/klien', '', __DIR__) . '/asset/images/kop.png',
    $_SERVER['DOCUMENT_ROOT'] . '/../public_html/asset/images/kop.png',

    // 5. HOSTING SPECIFIC ABSOLUTE PATH (InfinityFree/Vol13_8) - DIAGNOSED
    '/home/vol13_8/infinityfree.com/if0_41123842/htdocs/asset/images/kop.png',
    '/home/vol13_8/infinityfree.com/if0_41123842/htdocs/assets/images/kop.png',
];
$logo_data = read_image_as_data_uri($candidates_kop, $debug_log);

// HTTP fallback: embed as data URI to avoid remote fetch
if (empty($logo_data)) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (!empty($host)) {
        $logo_url = $scheme . '://' . $host . '/assets/images/kop.png';
        $data = @file_get_contents($logo_url);
        if ($data !== false) {
            $logo_data = 'data:image/png;base64,' . base64_encode($data);
            $debug_log[] = 'HTTP Fallback Logo (embedded)';
        } else {
            $debug_log[] = 'HTTP Fallback Logo failed: ' . $logo_url;
        }
    }
}

// 2. SETUP FOTO BIMBINGAN (Dynamic File Read)
$foto_db = $laporan['foto_bimbingan'] ?? '';
$foto_data = '';

if (!empty($foto_db)) {
    $raw_path = $foto_db;
    // Normalize path (remove leading slash)
    if (strpos($raw_path, '/') === 0) $raw_path = substr($raw_path, 1);
    
    $candidates_doc = [];
    
    // 1. Direct path from DB (often relative to root)
    $candidates_doc[] = $raw_path;
    
    // 2. Relative to script
    $candidates_doc[] = '../' . $raw_path;
    
    // 3. Absolute via __DIR__
    $candidates_doc[] = dirname(__DIR__) . '/' . $raw_path;
    
    // 4. Document Root based
    if (isset($_SERVER['DOCUMENT_ROOT'])) {
        $candidates_doc[] = $_SERVER['DOCUMENT_ROOT'] . '/' . $raw_path;
        $candidates_doc[] = $_SERVER['DOCUMENT_ROOT'] . '/pengawasan/' . $raw_path;
        $candidates_doc[] = $_SERVER['DOCUMENT_ROOT'] . '/../public_html/' . $raw_path;
    }
    
    // 5. Try to find in the uploads directory relative to the script
    $candidates_doc[] = '../uploads/' . basename($raw_path);
    $candidates_doc[] = '../uploads/foto_bimbingan/' . basename($raw_path);
    
    // 6. Hosting common paths
    if (isset($_SERVER['DOCUMENT_ROOT'])) {
         $candidates_doc[] = $_SERVER['DOCUMENT_ROOT'] . '/pengawasan/uploads/' . basename($raw_path);
         $candidates_doc[] = $_SERVER['DOCUMENT_ROOT'] . '/uploads/foto_bimbingan/' . basename($raw_path);
    }

    // 7. HOSTING SPECIFIC ABSOLUTE PATH (InfinityFree/Vol13_8) - DIAGNOSED
    $candidates_doc[] = '/home/vol13_8/infinityfree.com/if0_41123842/htdocs/' . $raw_path;
    $candidates_doc[] = '/home/vol13_8/infinityfree.com/if0_41123842/htdocs/uploads/' . basename($raw_path);
    $candidates_doc[] = '/home/vol13_8/infinityfree.com/if0_41123842/htdocs/uploads/foto_bimbingan/' . basename($raw_path);
    
    $foto_data = read_image_as_data_uri($candidates_doc, $debug_log);
    
    // HTTP fallback: embed as data URI
    if (empty($foto_data)) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (!empty($host)) {
            $http_url = $scheme . '://' . $host . '/' . ltrim($raw_path, '/');
            $data_http = @file_get_contents($http_url);
            if ($data_http !== false) {
                $mime = 'image/jpeg';
                $check = substr(bin2hex(substr($data_http,0,4)),0,8);
                if ($check === '89504e47') $mime = 'image/png';
                $foto_data = 'data:' . $mime . ';base64,' . base64_encode($data_http);
                $debug_log[] = 'HTTP Fallback Foto (embedded)';
            } else {
                $debug_log[] = 'HTTP Fallback Foto failed: ' . $http_url;
            }
        }
    }
}

// Format dates
$source_date = !empty($laporan['jadwal_tanggal_bimbingan']) ? $laporan['jadwal_tanggal_bimbingan'] : $laporan['tanggal_bimbingan'];
$tanggal_bimbingan = $source_date ? date('d/m/Y', strtotime($source_date)) : '-';
$hari_bimbingan = $source_date ? date('l', strtotime($source_date)) : '-';
$jam_bimbingan = $source_date ? date('H.i', strtotime($source_date)) . ' WIB' : ($laporan['created_at'] ? date('H.i', strtotime($laporan['created_at'])) . ' WIB' : '09.00 WIB');

// Translate day name
$days = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];
if (isset($days[$hari_bimbingan])) {
    $hari_bimbingan = $days[$hari_bimbingan];
}

// Format TTL
$ttl = '-';
if (!empty($laporan['tempat_lahir']) || !empty($laporan['tanggal_lahir'])) {
    $tempat = $laporan['tempat_lahir'] ?? '';
    $tanggal = !empty($laporan['tanggal_lahir']) ? date('d F Y', strtotime($laporan['tanggal_lahir'])) : '';
    $ttl = trim("$tempat, $tanggal", ", ");
}

// Format Agama (Prioritize User Profile)
$agama_final = !empty($laporan['agama_user']) ? $laporan['agama_user'] : $laporan['agama'];

// Format Jenis Kelamin (Prioritize User Profile)
$jenis_kelamin_final = !empty($laporan['jenis_kelamin_user']) ? $laporan['jenis_kelamin_user'] : $laporan['jenis_kelamin'];

// Determine Materi Bimbingan
$materi_final = !empty($laporan['materi_jadwal']) ? $laporan['materi_jadwal'] : $laporan['materi_bimbingan'];

// Format jenis integrasi
$jenis_integrasi_text = '';
if ($laporan['jenis_integrasi'] === 'PB') $jenis_integrasi_text = 'Pembebasan Bersyarat';
elseif ($laporan['jenis_integrasi'] === 'CMB') $jenis_integrasi_text = 'Cuti Menjelang Bebas';
elseif ($laporan['jenis_integrasi'] === 'CMJB') $jenis_integrasi_text = 'Cuti Menjelang Bebas';

// Determine Jenis Bimbingan Display
$jenis_bimbingan_display = '-';
if (!empty($laporan['jadwal_jenis_bimbingan'])) {
    $jenis_bimbingan_display = $laporan['jadwal_jenis_bimbingan'] === 'daring' ? 'Daring (Online)' : 'Tatap Muka (Offline)';
} elseif (!empty($laporan['jenis_bimbingan'])) {
    $jenis_bimbingan_display = $laporan['jenis_bimbingan'];
}

// SMART RECOVERY: If location is empty and no schedule linked, try to find a matching schedule by date/client
$loc_check = isset($laporan['lokasi_laporan']) ? trim($laporan['lokasi_laporan']) : '';
if (empty($loc_check) && empty($laporan['lokasi_bimbingan']) && empty($laporan['jadwal_bimbingan_id'])) {
    $check_date = date('Y-m-d', strtotime($laporan['tanggal_bimbingan'] ?? $laporan['created_at']));
    $check_klien = $laporan['klien_id'];
    
    $recovery_stmt = $conn->prepare("
        SELECT lokasi_bimbingan, jenis_bimbingan, link_meeting 
        FROM jadwal_bimbingan 
        WHERE klien_id = ? 
        AND DATE(tanggal_bimbingan) = ? 
        ORDER BY id DESC LIMIT 1
    ");
    if ($recovery_stmt) {
        $recovery_stmt->bind_param("is", $check_klien, $check_date);
        $recovery_stmt->execute();
        $rec_res = $recovery_stmt->get_result();
        if ($rec_row = $rec_res->fetch_assoc()) {
            $laporan['lokasi_bimbingan'] = $rec_row['lokasi_bimbingan'];
            // Update jenis bimbingan info if available
            if (!empty($rec_row['jenis_bimbingan'])) {
                $laporan['jadwal_jenis_bimbingan'] = $rec_row['jenis_bimbingan'];
                // Update display text if needed
                if ($jenis_bimbingan_display === '-') {
                     $jenis_bimbingan_display = $rec_row['jenis_bimbingan'] === 'daring' ? 'Daring (Online)' : 'Tatap Muka (Offline)';
                }
            }
            if (!empty($rec_row['link_meeting'])) {
                 $laporan['link_meeting'] = $rec_row['link_meeting'];
            }
        }
        $recovery_stmt->close();
    }
}

// Determine Lokasi and Alamat
$is_online = false;
if (!empty($laporan['jadwal_jenis_bimbingan'])) {
    $is_online = ($laporan['jadwal_jenis_bimbingan'] === 'daring');
} elseif (!empty($laporan['jenis_bimbingan'])) {
    $is_online = ($laporan['jenis_bimbingan'] === 'daring');
} elseif (!empty($laporan['link_meeting'])) {
    $is_online = true;
}

$bapas_name = 'Balai Pemasyarakatan Kelas I Pekanbaru';
$bapas_address = 'Jl. Chandra Dimuka No. 01 Kel. Delima Kec. Bina Widya Kota Pekanbaru Prov. Riau';

if ($is_online) {
    $tempat_display = 'Daring (Online)';
    $alamat_display = '-';
} else {
    // Offline / Tatap Muka
    // PRIORITY 1: Use Schedule Location (defined by PK)
    $loc = '';
    if (!empty($laporan['lokasi_bimbingan'])) {
        $loc = trim($laporan['lokasi_bimbingan']);
    }
    
    // PRIORITY 2: Use Report Location (GPS/Input from Client) as fallback
    if (empty($loc) && !empty($laporan['lokasi_laporan'])) {
        $loc = trim($laporan['lokasi_laporan']);
    }
    
    if (empty($loc)) {
        // Default to Bapas if no location specified
        $tempat_display = $bapas_name;
        $alamat_display = $bapas_address;
    } else {
        // Custom location exists
        // Check if it looks like Bapas
        if (stripos((string)$loc, 'Chandra Dimuka') !== false || stripos((string)$loc, 'Bapas') !== false) {
            $tempat_display = $bapas_name;
            $alamat_display = $loc;
        } else {
            // Likely a home visit or other location
            $tempat_display = (strlen((string)$loc) > 50) ? 'Lokasi Bimbingan' : $loc;
            $alamat_display = $loc;
        }
    }
}

// HTML Content
$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        @page { margin: 2cm 2cm 2cm 2.5cm; }
        body { 
            font-family: "Times New Roman", Times, serif; 
            font-size: 12pt; 
            line-height: 1.5; 
            color: #000;
            background-color: #fff;
        }
        
        /* Header Section */
        .header { 
            position: relative;
            margin-bottom: 20px; 
            border-bottom: 3px double #000; 
            padding-bottom: 15px; 
        }
        .header table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: middle; }
        .header img { width: 90px; height: auto; }
        .header-text { text-align: center; }
        .header-text h1 { margin: 0; font-size: 14pt; font-weight: bold; text-transform: uppercase; }
        .header-text h2 { margin: 0; font-size: 12pt; font-weight: bold; text-transform: uppercase; }
        .header-text p { margin: 0; font-size: 10pt; }
        
        /* Document Title */
        .title-section { 
            text-align: center; 
            margin-bottom: 25px; 
        }
        .doc-title { 
            font-size: 14pt; 
            font-weight: bold; 
            text-transform: uppercase;
            text-decoration: underline;
            margin-bottom: 5px;
        }
        .doc-subtitle {
            font-size: 12pt;
            font-weight: bold;
        }

        /* Content Tables */
        .content-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .content-table td { vertical-align: top; padding: 2px 0; }
        
        .label-col { width: 230px; }
        .sep-col { width: 20px; text-align: center; }
        .value-col { font-weight: normal; }
        .num-col { width: 25px; }
        
        /* Section Headers */
        .section-header { 
            font-weight: bold; 
            font-size: 12pt;
            margin-top: 15px; 
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        
        /* Text Content */
        .text-content { 
            text-align: justify; 
            margin-bottom: 15px; 
            line-height: 1.5;
        }
        
        /* Photo Section */
        .photo-container {
            text-align: center;
            margin: 15px 0;
            padding: 10px;
            border: 1px solid #000;
        }
        .photo-container img {
            max-width: 90%;
            max-height: 300px;
        }
        .no-photo {
            font-style: italic;
            padding: 20px;
        }
        
        /* Signatures */
        .signature-section {
            margin-top: 40px;
            width: 100%;
            page-break-inside: avoid;
        }
        .signature-table { width: 100%; border-collapse: collapse; }
        .signature-table td { width: 50%; text-align: center; vertical-align: top; }
        .signature-space { height: 80px; }
        .signer-name { font-weight: bold; text-decoration: underline; }
        .signer-nip { display: block; margin-top: 5px; }
        
        /* Footer/Page Number */
        .page-footer {
            position: fixed;
            bottom: -1cm;
            left: 0;
            right: 0;
            text-align: right;
            font-size: 9pt;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td width="15%" style="text-align: center;">
                    ' . ($logo_data ? '<img src="' . $logo_data . '" style="width: 100%;">' : '<div style="border: 2px solid red; background: #fffec8; font-size: 10px; color: black; width: 100%; overflow: hidden; padding: 5px;"><strong>LOGO NOT FOUND</strong><br>Root: ' . $_SERVER['DOCUMENT_ROOT'] . '<br>Dir: ' . __DIR__ . '<br>' . implode('<br>', array_map(function($s){return htmlspecialchars(substr($s,0,80));}, $debug_log)) . '</div>') . '
                </td>
                <td width="85%" class="header-text">
                    <h1>Kementerian Imigrasi dan Pemasyarakatan RI</h1>
                    <h2>Direktorat Jenderal Pemasyarakatan</h2>
                    <h2>Kantor Wilayah Riau</h2>
                    <h2>Balai Pemasyarakatan Kelas I Pekanbaru</h2>
                    <p>Jl. Chandra Dimuka No. 1, Kota Pekanbaru, Riau | 28294</p>
                    <p>Telepon (0761) 65322 | Faksimili (0761) 65322 | Pos-el: bapaspku@gmail.com</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="title-section">
        <div class="doc-title">CATATAN HASIL BIMBINGAN</div>
        <div class="doc-subtitle">Status Klien: ' . htmlspecialchars($jenis_integrasi_text) . '</div>
    </div>

    <table class="content-table">
        <tr>
            <td class="num-col">1.</td>
            <td class="label-col">Nama</td>
            <td class="sep-col">:</td>
            <td class="value-col">' . htmlspecialchars($laporan['klien_nama']) . '</td>
        </tr>
        <tr>
            <td class="num-col">2.</td>
            <td class="label-col">Nomor Berkas</td>
            <td class="sep-col">:</td>
            <td class="value-col">' . htmlspecialchars($laporan['no_registrasi']) . '</td>
        </tr>
        <tr>
            <td class="num-col">3.</td>
            <td class="label-col">Nomor Register</td>
            <td class="sep-col">:</td>
            <td class="value-col">' . htmlspecialchars($laporan['no_registrasi_perkara'] ?: '-') . '</td>
        </tr>
        <tr>
            <td class="num-col">4.</td>
            <td class="label-col">Tempat, Tanggal Lahir</td>
            <td class="sep-col">:</td>
            <td class="value-col">' . htmlspecialchars($ttl) . '</td>
        </tr>
        <tr>
            <td class="num-col">5.</td>
            <td class="label-col">Jenis Kelamin</td>
            <td class="sep-col">:</td>
            <td class="value-col">' . htmlspecialchars($jenis_kelamin_final ?: '-') . '</td>
        </tr>
        <tr>
            <td class="num-col">6.</td>
            <td class="label-col">Agama</td>
            <td class="sep-col">:</td>
            <td class="value-col">' . htmlspecialchars($agama_final ?: '-') . '</td>
        </tr>
        <tr>
            <td class="num-col">7.</td>
            <td class="label-col">Pendidikan Terakhir</td>
            <td class="sep-col">:</td>
            <td class="value-col">' . htmlspecialchars($laporan['riwayat_pendidikan'] ?: '-') . '</td>
        </tr>
        <tr>
            <td class="num-col">8.</td>
            <td class="label-col">Pekerjaan</td>
            <td class="sep-col">:</td>
            <td class="value-col">' . htmlspecialchars($laporan['pekerjaan'] ?: '-') . '</td>
        </tr>
        <tr>
            <td class="num-col">9.</td>
            <td class="label-col">Status Perkawinan</td>
            <td class="sep-col">:</td>
            <td class="value-col">' . htmlspecialchars($laporan['status_pernikahan'] ?: '-') . '</td>
        </tr>
        <tr>
            <td class="num-col">10.</td>
            <td class="label-col">Alamat Tempat Tinggal</td>
            <td class="sep-col">:</td>
            <td class="value-col">' . htmlspecialchars($laporan['alamat'] ?: '-') . '</td>
        </tr>
        <tr>
            <td class="num-col">11.</td>
            <td class="label-col">Jenis Bimbingan</td>
            <td class="sep-col">:</td>
            <td class="value-col">' . htmlspecialchars($jenis_bimbingan_display) . '</td>
        </tr>
        <tr>
            <td class="num-col">12.</td>
            <td class="label-col">Bimbingan Ke</td>
            <td class="sep-col">:</td>
            <td class="value-col">1 (satu)</td>
        </tr>
        <tr>
            <td class="num-col">13.</td>
            <td class="label-col">Pembimbing Kemasyarakatan</td>
            <td class="sep-col">:</td>
            <td class="value-col">' . htmlspecialchars($laporan['nama_pk']) . '</td>
        </tr>
        <tr>
            <td class="num-col">14.</td>
            <td class="label-col">Tanggal/Jam Pelaksanaan</td>
            <td class="sep-col">:</td>
            <td class="value-col">' . $hari_bimbingan . ', ' . $tanggal_bimbingan . ' / Pukul ' . $jam_bimbingan . '</td>
        </tr>
        <tr>
            <td class="num-col">15.</td>
            <td class="label-col">Tempat Dilaksanakan Bimbingan</td>
            <td class="sep-col">:</td>
            <td class="value-col">' . htmlspecialchars($tempat_display) . '</td>
        </tr>
        <tr>
            <td class="num-col">16.</td>
            <td class="label-col">Alamat Kegiatan Bimbingan</td>
            <td class="sep-col">:</td>
            <td class="value-col">' . htmlspecialchars($alamat_display) . '</td>
        </tr>
    </table>

    <div class="section-header">A. Materi Bimbingan</div>
    <div class="text-content">
        ' . nl2br(htmlspecialchars($materi_final)) . '
    </div>

    <div class="section-header">B. Saran / Hasil Bimbingan</div>
    <div class="text-content">
        ' . nl2br(htmlspecialchars($laporan['hasil_bimbingan'])) . '
    </div>

    ' . ($laporan['tindak_lanjut'] ? '
    <div class="section-header">C. Tindak Lanjut</div>
    <div class="text-content">
        ' . nl2br(htmlspecialchars($laporan['tindak_lanjut'])) . '
    </div>
    ' : '') . '

    <div class="section-header">' . ($laporan['tindak_lanjut'] ? 'D' : 'C') . '. Dokumentasi Kegiatan</div>
    <div class="photo-container">
        ' . ($foto_data ? '<img src="' . $foto_data . '">' : '<div style="border: 2px solid red; background: #fffec8; font-size: 10px; color: black; width: 100%; overflow: hidden; padding: 5px;"><strong>FOTO NOT FOUND</strong><br>Root: ' . $_SERVER['DOCUMENT_ROOT'] . '<br>Dir: ' . __DIR__ . '<br>' . implode('<br>', array_map(function($s){return htmlspecialchars(substr($s,0,80));}, $debug_log)) . '</div>') . '
    </div>

    <div class="signature-section">
        <table class="signature-table">
            <tr>
                <td>
                    Mengetahui,<br>
                    Kepala Bapas Kelas I Pekanbaru
                    <div class="signature-space"></div>
                    <span class="signer-name">ERI ERAWAN</span><br>
                    <span class="signer-nip">NIP. 197303141993031001</span>
                </td>
                <td>
                    Pekanbaru, ' . $tanggal_bimbingan . '<br>
                    Pembimbing Kemasyarakatan,
                    <div class="signature-space"></div>
                    <span class="signer-name">' . htmlspecialchars($laporan['nama_pk']) . '</span><br>
                    <span class="signer-nip">NIP. ' . htmlspecialchars($laporan['nip_pk'] ?? '.........................') . '</span>
                </td>
            </tr>
        </table>
    </div>
    
    <div class="page-footer">
        Dicetak melalui Sistem Pengawasan Klien BAPAS
    </div>
</body>
</html>';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
if (ob_get_length()) ob_clean();

$date_suffix = !empty($laporan['tanggal_bimbingan']) ? date('Ymd', strtotime($laporan['tanggal_bimbingan'])) : date('Ymd');
$filename = "Laporan_Bimbingan_" . $laporan['no_registrasi'] . "_" . $date_suffix . ".pdf";
$dompdf->stream($filename, array("Attachment" => 0));
