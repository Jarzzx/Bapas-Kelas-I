<?php
ob_start(); // Start output buffering to prevent whitespace leakage
// Disable error reporting to prevent PDF corruption
error_reporting(0);
ini_set('display_errors', 0);
// Prevent timeout and memory issues on shared hosting
ini_set('memory_limit', '256M');
set_time_limit(60);

require_once '../vendor/autoload.php';
require_once '../shared/config/database.php';
require_once '../shared/config/auth.php';
requirePKLogin();

use Dompdf\Dompdf;
use Dompdf\Options;

$conn = getDBConnection();
$pk_id = $_SESSION['user_id'];
$root_dir = dirname(__DIR__); // Define root dir for path resolution
$laporan_id = 0;

if (isset($_POST['id'])) {
    $laporan_id = (int)$_POST['id'];
} elseif (isset($_GET['id'])) {
    $laporan_id = (int)$_GET['id'];
}

if ($laporan_id === 0) {
    die('ID Laporan tidak valid!');
}

// Get dynamic inputs from POST (or default to empty if not provided)
$nomor_sk = $_POST['nomor_sk'] ?? '-';
$tanggal_sk = $_POST['tanggal_sk'] ?? '';
$nomor_litmas = $_POST['nomor_litmas'] ?? '-';
$tanggal_litmas = $_POST['tanggal_litmas'] ?? '';
$simpulan = $_POST['simpulan'] ?? '-';
$saran = $_POST['saran'] ?? '-';
$judul_laporan = $_POST['judul_laporan'] ?? '';
$dasar_hukum = $_POST['dasar_hukum'] ?? '';
$tujuan_laporan = $_POST['tujuan_laporan'] ?? '';
$ruang_lingkup = $_POST['ruang_lingkup'] ?? '';
$tempat_ttd = $_POST['tempat_ttd'] ?? 'Pekanbaru';
$observasi = $_POST['observasi'] ?? '';
$wawancara = $_POST['wawancara'] ?? '';
$koordinasi = $_POST['koordinasi'] ?? '';

// Format dates if provided (initial check from POST)
$tgl_sk_formatted = !empty($tanggal_sk) ? date('d/m/Y', strtotime($tanggal_sk)) : '-';
$tgl_litmas_formatted = !empty($tanggal_litmas) ? date('d/m/Y', strtotime($tanggal_litmas)) : '-';

// Get Laporan Data (Single Row)
$stmt = $conn->prepare("
    SELECT lp.*, 
           dk.nama as nama_klien, dk.no_registrasi, dk.agama, dk.pasal_pidana, dk.jenis_integrasi,
           ku.tempat_lahir, ku.tanggal_lahir, ku.pekerjaan, ku.agama as agama_user, ku.alamat as alamat_user, dk.alamat as alamat_klien,
           pk.nama as nama_pk, pk.nip as nip_pk
    FROM laporan_pengawasan lp
    JOIN data_klien dk ON lp.klien_id = dk.id
    LEFT JOIN klien_users ku ON dk.no_registrasi = ku.no_registrasi
    JOIN pk_users pk ON lp.pk_id = pk.id
    WHERE lp.id = ? AND lp.pk_id = ?
");
$stmt->bind_param("ii", $laporan_id, $pk_id);
$stmt->execute();
$laporan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$laporan) {
    die('Laporan tidak ditemukan atau Anda tidak memiliki akses!');
}

// Re-evaluate logic after fetching $laporan
if ($nomor_sk === '-' && !empty($laporan['nomor_sk'])) $nomor_sk = $laporan['nomor_sk'];
if ($nomor_litmas === '-' && !empty($laporan['nomor_litmas'])) $nomor_litmas = $laporan['nomor_litmas'];
if ($simpulan === '-' && !empty($laporan['simpulan'])) $simpulan = $laporan['simpulan'];
if ($saran === '-' && !empty($laporan['saran'])) $saran = $laporan['saran'];
if (empty($judul_laporan) && !empty($laporan['judul_laporan'])) $judul_laporan = $laporan['judul_laporan'];
if (empty($dasar_hukum) && !empty($laporan['dasar_hukum'])) $dasar_hukum = $laporan['dasar_hukum'];
if (empty($tujuan_laporan) && !empty($laporan['tujuan_laporan'])) $tujuan_laporan = $laporan['tujuan_laporan'];
if (empty($ruang_lingkup) && !empty($laporan['ruang_lingkup'])) $ruang_lingkup = $laporan['ruang_lingkup'];
if ($tempat_ttd === 'Pekanbaru' && !empty($laporan['tempat_ttd'])) $tempat_ttd = $laporan['tempat_ttd'];
if (empty($observasi) && !empty($laporan['observasi'])) $observasi = $laporan['observasi'];
if (empty($wawancara) && !empty($laporan['wawancara'])) $wawancara = $laporan['wawancara'];
if (empty($koordinasi) && !empty($laporan['koordinasi'])) $koordinasi = $laporan['koordinasi'];
if ($tgl_sk_formatted === '-' && !empty($laporan['tanggal_sk'])) $tgl_sk_formatted = date('d/m/Y', strtotime($laporan['tanggal_sk']));
if ($tgl_litmas_formatted === '-' && !empty($laporan['tanggal_litmas'])) $tgl_litmas_formatted = date('d/m/Y', strtotime($laporan['tanggal_litmas']));

// Setup DOMPDF
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('chroot', realpath('..'));
$dompdf = new Dompdf($options);

// Helper for images
// Initialize debug log
$debug_log = [];

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
        
        // 1. Try reading directly
        $data = @file_get_contents($path);
        
        // 2. If failed, try resolving relative to __DIR__
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

// 1. Logo KOP (sederhana agar DOMPDF bisa membacanya)
$logo_src = '';
$logo_path = null;
$possible_logo_paths = [
    dirname(__DIR__) . '/asset/images/kop.png',
    dirname(__DIR__) . '/assets/images/kop.png',
    $_SERVER['DOCUMENT_ROOT'] . '/assets/images/kop.png',
    $_SERVER['DOCUMENT_ROOT'] . '/asset/images/kop.png'
];

foreach ($possible_logo_paths as $p) {
    if (file_exists($p)) {
        $logo_path = $p;
        break;
    }
}

if ($logo_path) {
    $img_data = file_get_contents($logo_path);
    if ($img_data) {
        $logo_src = 'data:image/png;base64,' . base64_encode($img_data);
    }
}

// Jika tidak bisa baca sebagai data URI, pakai URL HTTP
if (empty($logo_src)) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $logo_src = $scheme . '://' . $host . '/assets/images/kop.png';
}

// Format Dates
$current_date = date('d F Y');

// Format data
$nama_klien = $laporan['nama_klien'];
$no_reg = $laporan['no_registrasi'];
$ttl = ($laporan['tempat_lahir'] ?? '-') . ', ' . ($laporan['tanggal_lahir'] ? date('d/m/Y', strtotime($laporan['tanggal_lahir'])) : '-');
$agama = !empty($laporan['agama_user']) ? $laporan['agama_user'] : ($laporan['agama'] ?? '-');
$pekerjaan = $laporan['pekerjaan'] ?? '-';
$alamat = $laporan['alamat_user'] ?? ($laporan['alamat_klien'] ?? '-');
$tindak_pidana = $laporan['pasal_pidana'] ?? '-';
$jenis_integrasi = $laporan['jenis_integrasi'] ?? null;
$jenis_integrasi_text = '';
if ($jenis_integrasi === 'PB') $jenis_integrasi_text = 'Pembebasan Bersyarat (PB)';
elseif ($jenis_integrasi === 'CMB') $jenis_integrasi_text = 'Cuti Menjelang Bebas (CMB)';
elseif ($jenis_integrasi === 'CMJB') $jenis_integrasi_text = 'Cuti Menjelang Bebas (CMJB)';
$pk_nama = $laporan['nama_pk'];
$pk_nip = $laporan['nip_pk'];

$isi_laporan = $laporan['isi_laporan'] ?? '';
$timestamp_laporan = strtotime($laporan['tanggal_laporan']);
$waktu_laporan = date('d/m/Y H:i', $timestamp_laporan);
$tanggal_laporan_full = date('d F Y', $timestamp_laporan);
$hari_eng = date('l', $timestamp_laporan);
$hari_map = [
    'Sunday' => 'Minggu',
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu'
];
$hari_laporan = $hari_map[$hari_eng] ?? $hari_eng;
$status_klien = $laporan['status_klien'] ?? '-';
$catatan_tambahan = $laporan['catatan'] ?? '';

// Ambil data baru dari database (atau dari POST jika ada)
$observasi_text = $observasi ?? ($laporan['observasi'] ?? '');
$wawancara_text = $wawancara ?? ($laporan['wawancara'] ?? '');
$koordinasi_text = $koordinasi ?? ($laporan['koordinasi'] ?? '');

// Pastikan judul laporan selalu ada
if (empty($judul_laporan)) {
    $judul_laporan = "LAPORAN HASIL PENGAWASAN<br>PELAKSANAAN PROGRAM PEMBIMBINGAN KLIEN PEMASYARAKATAN";
}

// Build ringkasan
$ringkasan_parts = [];
if (!empty(trim($observasi_text))) $ringkasan_parts[] = "Observasi:\n" . trim($observasi_text);
if (!empty(trim($wawancara_text))) $ringkasan_parts[] = "Wawancara:\n" . trim($wawancara_text);
if (!empty(trim($koordinasi_text))) $ringkasan_parts[] = "Koordinasi:\n" . trim($koordinasi_text);
$ringkasan_text = !empty($ringkasan_parts) ? implode("\n\n", $ringkasan_parts) : ($isi_laporan ?: '-');

// Default values untuk bagian kosong
if (empty($dasar_hukum)) {
    $dasar_hukum = "Berdasarkan Peraturan Menteri Hukum dan HAM RI No. 3 Tahun 2018 tentang Syarat dan Tata Cara Pemberian Remisi, Asimilasi, Cuti Mengunjungi Keluarga (CMK), Pembebasan Bersyarat (PB), Cuti Menjelang Bebas (CMB), dan Cuti Bersyarat (CB), serta berdasarkan Surat Keputusan Menteri Hukum dan HAM";

    if (!empty($nomor_sk) && $nomor_sk !== '-') {
        $dasar_hukum .= " Nomor " . $nomor_sk;
        if ($tgl_sk_formatted !== '-') {
            $dasar_hukum .= " tanggal " . $tgl_sk_formatted;
        }
    }

    if (!empty($jenis_integrasi_text)) {
        $dasar_hukum .= " tentang pelaksanaan " . $jenis_integrasi_text . " bagi Klien atas nama " . $nama_klien . ".";
    } else {
        $dasar_hukum .= " bagi Klien atas nama " . $nama_klien . ".";
    }

    $dasar_hukum .= " Pembimbing Kemasyarakatan pada Balai Pemasyarakatan Kelas I Pekanbaru melaksanakan pengawasan terhadap Klien dimaksud dan menyusun laporan hasil pengawasan pelaksanaan program pembimbingan ini.";
}

if (empty($tujuan_laporan)) {
    $tujuan_laporan = "Tujuan penyusunan laporan hasil pengawasan ini adalah untuk memberikan gambaran yang jelas mengenai pelaksanaan program pembimbingan, tingkat kepatuhan Klien terhadap ketentuan yang berlaku, serta sebagai bahan pertimbangan dalam pengambilan keputusan lebih lanjut.";
}

if (empty($ruang_lingkup)) {
    $ruang_lingkup = "Ruang lingkup pengawasan meliputi pemantauan terhadap pelaksanaan program pembimbingan, perilaku dan sikap Klien dalam kehidupan bermasyarakat, serta kepatuhan Klien terhadap syarat-syarat administratif maupun substantif yang ditetapkan dalam keputusan pelaksanaan " . ($jenis_integrasi_text ?: 'program integrasi') . ".";
}

// HTML Generation
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Hasil Pengawasan</title>
    <style>
        @page { margin: 2cm 2cm 2cm 2.5cm; }
        body { font-family: "Times New Roman", serif; font-size: 12pt; line-height: 1.5; }
        .header { border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 20px; text-align: center; position: relative; }
        .header img { width: 90px; height: auto; position: absolute; left: 0; top: 0; }
        .header h1 { font-size: 14pt; font-weight: bold; margin: 0; text-transform: uppercase; }
        .header h2 { font-size: 12pt; font-weight: bold; margin: 0; text-transform: uppercase; }
        .header p { font-size: 10pt; margin: 0; }
        .title { text-align: center; font-weight: bold; text-decoration: underline; margin: 20px 0; font-size: 12pt; text-transform: uppercase; }
        .section-title { font-weight: bold; margin-top: 15px; margin-bottom: 5px; }
        .content-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .content-table td { vertical-align: top; padding: 2px 0; }
        .log-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 11pt; }
        .log-table th, .log-table td { border: 1px solid #000; padding: 5px; vertical-align: top; }
        .log-table th { background-color: #f0f0f0; text-align: center; }
        .signature { margin-top: 50px; float: right; width: 250px; text-align: center; }
        .page-break { page-break-before: always; }
        .photo-container { text-align: center; margin-top: 20px; border: 1px solid #ddd; padding: 10px; }
        .photo-container img { max-width: 100%; height: auto; max-height: 400px; }
        .photo-caption { font-size: 10pt; margin-top: 5px; font-style: italic; }
    </style>
</head>
<body>
    <div class="header">
        <table style="width: 100%; border: none; margin-bottom: 10px;">
            <tr>
                <td style="width: 100px; text-align: center; vertical-align: middle;">
                    ' . ($logo_src ? '<img src="' . $logo_src . '" style="width: 90px; height: auto;">' : '') . '
                </td>
                <td style="text-align: center; vertical-align: middle;">
                    <h1 style="font-size: 14pt; font-weight: bold; margin: 0; text-transform: uppercase;">KEMENTERIAN IMIGRASI DAN PEMASYARAKATAN RI</h1>
                    <h2 style="font-size: 12pt; font-weight: bold; margin: 0; text-transform: uppercase;">KANTOR WILAYAH RIAU</h2>
                    <h2 style="font-size: 12pt; font-weight: bold; margin: 0; text-transform: uppercase;">BALAI PEMASYARAKATAN KELAS I PEKANBARU</h2>
                    <p style="font-size: 10pt; margin: 0;">Jalan Chandra Dimuka No. 1, Pekanbaru</p>
                    <p style="font-size: 10pt; margin: 0;">Telepon: (0761) 64567, Email: bapaspekanbaru@kemenkumham.go.id</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="title">' . $judul_laporan . '</div>
    
    <table class="content-table" style="margin-bottom: 15px;">
        <tr>
            <td width="150">Nama</td>
            <td width="10">:</td>
            <td>' . htmlspecialchars($nama_klien) . '</td>
        </tr>
        <tr>
            <td>Nomor Berkas</td>
            <td>:</td>
            <td>' . htmlspecialchars($no_reg) . '</td>
        </tr>
        <tr>
            <td>Tindak Pidana</td>
            <td>:</td>
            <td>' . htmlspecialchars($tindak_pidana) . '</td>
        </tr>
    </table>

    <div class="section-title">A. PENDAHULUAN</div>
    <div style="margin-left: 20px;">
        <div><strong>1. Umum</strong></div>
        <p>' . nl2br(htmlspecialchars($dasar_hukum)) . '</p>

        <div style="margin-top: 10px;"><strong>2. Tujuan</strong></div>
        <p>' . nl2br(htmlspecialchars($tujuan_laporan)) . '</p>

        <div style="margin-top: 10px;"><strong>3. Ruang Lingkup</strong></div>
        <p>' . nl2br(htmlspecialchars($ruang_lingkup)) . '</p>

        <div style="margin-top: 10px;"><strong>4. Dasar Pengawasan</strong></div>
        <table class="content-table" style="margin-left: 15px;">
            <tr><td width="150">Surat Keputusan</td><td width="10">:</td><td>Nomor: ' . htmlspecialchars($nomor_sk) . '</td></tr>
            <tr><td></td><td></td><td>Tanggal: ' . $tgl_sk_formatted . '</td></tr>
            <tr><td>Litmas</td><td>:</td><td>Nomor: ' . htmlspecialchars($nomor_litmas) . '</td></tr>
            <tr><td></td><td></td><td>Tanggal: ' . $tgl_litmas_formatted . '</td></tr>
        </table>

        <div style="margin-top: 10px;"><strong>Data Klien</strong></div>
        <table class="content-table" style="margin-left: 15px;">
            <tr><td width="150">Nama</td><td width="10">:</td><td>' . htmlspecialchars($nama_klien) . '</td></tr>
            <tr><td>No. Berkas</td><td>:</td><td>' . htmlspecialchars($no_reg) . '</td></tr>
            <tr><td>Tempat/Tgl Lahir</td><td>:</td><td>' . htmlspecialchars($ttl) . '</td></tr>
            <tr><td>Agama</td><td>:</td><td>' . htmlspecialchars($agama) . '</td></tr>
            <tr><td>Pekerjaan</td><td>:</td><td>' . htmlspecialchars($pekerjaan) . '</td></tr>
            <tr><td>Alamat</td><td>:</td><td>' . htmlspecialchars($alamat) . '</td></tr>
        </table>
    </div>

    <div class="section-title">B. PELAKSANAAN</div>
    <div style="margin-left: 20px;">
        <div><strong>1. Pelaksanaan Kegiatan</strong></div>
        <table class="content-table" style="margin-left: 15px;">
            <tr><td width="150">Nama</td><td width="10">:</td><td>' . htmlspecialchars($pk_nama) . '</td></tr>
            <tr><td>NIP</td><td>:</td><td>' . htmlspecialchars($pk_nip) . '</td></tr>
            <tr><td>Jabatan</td><td>:</td><td>Pembimbing Kemasyarakatan Balai Pemasyarakatan Kelas I Pekanbaru</td></tr>
        </table>

        <div style="margin-top: 10px;"><strong>2. Ringkasan Pelaksanaan</strong></div>
        <p>' . nl2br(htmlspecialchars($ringkasan_text)) . '</p>
        <div style="margin-top: 10px;"><strong>3. Pelaksanaan Kegiatan</strong></div>
        <p>Kegiatan pengawasan dilaksanakan pada hari/tanggal: ' . $hari_laporan . ', ' . $tanggal_laporan_full . '.</p>
        <p>Rincian pelaksanaan kegiatan adalah sebagai berikut:</p>
        <p>a. Observasi</p>
        <p>' . nl2br(htmlspecialchars($observasi_text ?: '-')) . '</p>
        <p>b. Wawancara</p>
        <p>' . nl2br(htmlspecialchars($wawancara_text ?: 'Wawancara dilakukan dengan Klien dan/atau pihak terkait untuk menggali informasi mengenai pelaksanaan program pembimbingan serta permasalahan yang dihadapi.')) . '</p>
        <p>c. Koordinasi</p>
        <p>' . nl2br(htmlspecialchars($koordinasi_text ?: 'Koordinasi dilakukan dengan pihak terkait sesuai kebutuhan untuk mendukung kelancaran dan keberhasilan pelaksanaan program pembimbingan dan pengawasan.')) . '</p>
    </div>

    <div class="section-title">C. HASIL YANG DICAPAI</div>
    <div style="margin-left: 20px;">
        <p>Hasil yang dicapai dari pelaksanaan pengawasan terhadap Klien adalah sebagai berikut:</p>
        <p>Status perkembangan Klien: <strong>' . htmlspecialchars($status_klien) . '</strong>.</p>';

if (!empty($catatan_tambahan)) {
    $html .= '
        <p>Catatan tambahan: ' . nl2br(htmlspecialchars($catatan_tambahan)) . '</p>';
}

$html .= '
    </div>

    <div class="section-title">D. SIMPULAN DAN SARAN</div>
    <div style="margin-left: 20px;">
        <p><strong>1. Simpulan</strong></p>
        <p>' . nl2br(htmlspecialchars($simpulan)) . '</p>
        
        <p><strong>2. Saran</strong></p>
        <p>' . nl2br(htmlspecialchars($saran)) . '</p>
    </div>

    <div class="section-title">E. PENUTUP</div>
    <div style="margin-left: 20px;">
        <p>Demikian laporan hasil pengawasan pelaksanaan program pembimbingan Klien ini dibuat dengan sebenarnya untuk digunakan sebagaimana mestinya.</p>
    </div>

    <div class="signature">
        <p>' . htmlspecialchars($tempat_ttd) . ', ' . $current_date . '<br>Pembimbing Kemasyarakatan,</p>
        <br><br><br>
        <p><strong><u>' . htmlspecialchars($pk_nama) . '</u></strong><br>NIP. ' . htmlspecialchars($pk_nip) . '</p>
    </div>
';

// Lampiran Dokumentasi (Single Photo)
if (!empty($laporan['foto_dokumentasi'])) {
    // Determine path based on stored format
    $candidates_doc = [];
    $raw_path = $laporan['foto_dokumentasi'];

    // 1. If path starts with ../, it is relative to pk/ directory
    if (strpos($raw_path, '../') === 0) {
        $candidates_doc[] = __DIR__ . '/' . $raw_path; // Resolves to c:/.../pk/../uploads/...
        $candidates_doc[] = $root_dir . '/' . str_replace('../', '', $raw_path); // c:/.../pengawasan/uploads/...
    } 
    
    // 2. If path starts with uploads/, it is relative to root
    $clean_path = ltrim($raw_path, '/');
    $candidates_doc[] = $root_dir . '/' . $clean_path;
    $candidates_doc[] = $_SERVER['DOCUMENT_ROOT'] . '/' . $clean_path;
    $candidates_doc[] = __DIR__ . '/../' . $clean_path;
    
    // 3. Fallback: Check if file exists in other known upload directories (shallow search)
    $filename = basename($raw_path);
    $candidates_doc[] = $root_dir . '/uploads/foto_bimbingan/' . $filename;
    $candidates_doc[] = $root_dir . '/uploads/foto_klien/' . $filename;
    
    // 4. Absolute Path Fallback (Common in Hosting)
    // If we are in public_html/pk, root might be public_html
    // Try to construct path relative to known server roots if possible
    if (isset($_SERVER['DOCUMENT_ROOT'])) {
        $candidates_doc[] = $_SERVER['DOCUMENT_ROOT'] . '/pengawasan/' . $clean_path;
        $candidates_doc[] = $_SERVER['DOCUMENT_ROOT'] . '/../public_html/' . $clean_path;
    }

    // 5. HOSTING SPECIFIC ABSOLUTE PATH (InfinityFree/Vol13_8) - DIAGNOSED
    $candidates_doc[] = '/home/vol13_8/infinityfree.com/if0_41123842/htdocs/' . $clean_path;
    $candidates_doc[] = '/home/vol13_8/infinityfree.com/if0_41123842/htdocs/uploads/' . basename($raw_path);
    $candidates_doc[] = '/home/vol13_8/infinityfree.com/if0_41123842/htdocs/uploads/dokumentasi_pengawasan/' . basename($raw_path);

    // 6. Try to find in the uploads directory relative to the script
    $candidates_doc[] = '../uploads/' . basename($raw_path);
    $candidates_doc[] = '../uploads/dokumentasi_pengawasan/' . basename($raw_path);
    
    // 6. Absolute check
    $candidates_doc[] = dirname(__DIR__) . '/uploads/' . basename($raw_path);
    $candidates_doc[] = dirname(__DIR__) . '/uploads/dokumentasi_pengawasan/' . basename($raw_path);
    
    // 7. Hosting common paths
    if (isset($_SERVER['DOCUMENT_ROOT'])) {
         $candidates_doc[] = $_SERVER['DOCUMENT_ROOT'] . '/pengawasan/uploads/' . basename($raw_path);
         $candidates_doc[] = $_SERVER['DOCUMENT_ROOT'] . '/uploads/dokumentasi_pengawasan/' . basename($raw_path);
    }

    $img_data = read_image_as_data_uri($candidates_doc, $debug_log);
    
    if ($img_data) {
        $html .= '<div class="page-break"></div>';
        $html .= '<div class="title">LAMPIRAN DOKUMENTASI</div>';
        $html .= '<div class="photo-container">';
        $html .= '<img src="' . $img_data . '">';
        $html .= '<div class="photo-caption">Dokumentasi Kegiatan (' . $waktu_laporan . ')</div>';
        $html .= '</div>';
    } else {
        // HTTP fallback using absolute URL
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (!empty($host)) {
            $http_url = $scheme . '://' . $host . '/' . ltrim($clean_path, '/');
            // Try fetch and embed as data URI
            $data_http = @file_get_contents($http_url);
            if ($data_http !== false) {
                $mime = 'image/jpeg';
                $check = substr(bin2hex(substr($data_http,0,4)),0,8);
                if ($check === '89504e47') $mime = 'image/png';
                $img_http_data = 'data:' . $mime . ';base64,' . base64_encode($data_http);
                $html .= '<div class="page-break"></div>';
                $html .= '<div class="title">LAMPIRAN DOKUMENTASI</div>';
                $html .= '<div class="photo-container">';
                $html .= '<img src="' . $img_http_data . '">';
                $html .= '<div class="photo-caption">Dokumentasi Kegiatan (' . $waktu_laporan . ')</div>';
                $html .= '</div>';
            } else {
                $html .= '<div class="page-break"></div>';
                $html .= '<div class="title">LAMPIRAN DOKUMENTASI</div>';
                $html .= '<div class="photo-container">';
                $html .= '<img src="' . htmlspecialchars($http_url) . '">';
                $html .= '<div class="photo-caption">Dokumentasi Kegiatan (' . $waktu_laporan . ')</div>';
                $html .= '</div>';
            }
        } else {
            // Show text instead of broken image if not found
            $html .= '<div class="page-break"></div>';
            $html .= '<div class="title">LAMPIRAN DOKUMENTASI</div>';
            $html .= '<div style="text-align:center; border: 1px dashed #ccc; padding: 20px;">';
            $html .= '<strong>Foto dokumentasi tidak tersedia atau file fisik tidak ditemukan.</strong><br>';
            $html .= '<span style="font-size:10px; color:#999;">(' . htmlspecialchars($raw_path) . ')</span>';
            $html .= '<div style="margin-top:5px; border-top:1px solid #eee; font-size:9px; text-align:left;">DEBUG:<br>' . implode('<br>', array_map(function($s){return htmlspecialchars(substr($s,0,80));}, $debug_log)) . '</div>';
            $html .= '</div>';
        }

    }
}

$html .= '
</body>
</html>
';

if (isset($_GET['preview']) && $_GET['preview'] === '1') {
    if (ob_get_length()) ob_end_clean();
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}


// Render PDF
try {
    $dompdf->loadHtml($html);
    $dompdf->render();
    
    // Clean any previous output buffer to ensure pure PDF output
    if (ob_get_length()) ob_end_clean();
    
    // Output
    $filename = "Laporan_Pengawasan_" . str_replace(' ', '_', $nama_klien) . "_" . date('Ymd_Hi') . ".pdf";
    $dompdf->stream($filename, array("Attachment" => 0));
} catch (Throwable $e) {
    // Fallback Preview
    if (ob_get_length()) ob_end_clean();
    header('Content-Type: text/html; charset=utf-8');
    echo "<h3>Terjadi Kesalahan saat Membuat PDF</h3>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    echo "<hr>Debug Log:<br><pre>" . print_r($debug_log, true) . "</pre>";
}
