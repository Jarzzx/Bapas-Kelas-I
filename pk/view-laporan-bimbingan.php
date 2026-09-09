<?php
require_once '../shared/config/database.php';
require_once '../shared/config/auth.php';
requirePKLogin();

$conn = getDBConnection();
$pk_id = $_SESSION['user_id'];
$laporan_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get laporan data
$stmt = $conn->prepare("
    SELECT lb.*, 
           dk.nama as klien_nama, dk.no_registrasi, dk.alamat, dk.pasal_pidana,
           dk.tanggal_mulai_bimbingan, dk.tanggal_akhir_bimbingan, dk.jenis_integrasi, 
           dk.status as status_klien, dk.agama, dk.jenis_kelamin,
           ku.tempat_lahir, ku.tanggal_lahir, ku.riwayat_pendidikan, ku.pekerjaan, ku.status_pernikahan,
           pk.nama as nama_pk,
           jb.materi_bimbingan as materi_jadwal
    FROM laporan_bimbingan lb
    JOIN data_klien dk ON lb.klien_id = dk.id
    LEFT JOIN klien_users ku ON dk.no_registrasi = ku.no_registrasi
    JOIN pk_users pk ON lb.pk_id = pk.id
    LEFT JOIN jadwal_bimbingan jb ON lb.jadwal_bimbingan_id = jb.id
    WHERE lb.id = ? AND lb.pk_id = ?
");
$stmt->bind_param("ii", $laporan_id, $pk_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Laporan tidak ditemukan atau Anda tidak memiliki akses!');
}

$laporan = $result->fetch_assoc();
$stmt->close();
// $conn->close();

// Format tanggal
$tanggal_bimbingan = $laporan['tanggal_bimbingan'] ? date('d F Y', strtotime($laporan['tanggal_bimbingan'])) : '-';
$tanggal_laporan = date('d F Y', strtotime($laporan['tanggal_laporan']));
$waktu_laporan = date('H:i:s', strtotime($laporan['tanggal_laporan']));

// Format bentuk pembimbingan
$bentuk_text = '';
if ($laporan['bentuk_pembimbingan'] === 'tatap_muka') {
    $bentuk_text = 'Tatap Muka';
} elseif ($laporan['bentuk_pembimbingan'] === 'daring') {
    $bentuk_text = 'Daring (Online)';
    $bentuk_class = 'bg-sky-50 text-sky-700 border border-sky-100';
} elseif ($laporan['bentuk_pembimbingan'] === 'kunjungan_rumah') {
    $bentuk_text = 'Kunjungan Rumah';
} else {
    $bentuk_text = '-';
}

// Format jenis integrasi
$jenis_integrasi_text = '';
if ($laporan['jenis_integrasi'] === 'PB') $jenis_integrasi_text = 'Pembebasan Bersyarat (PB)';
elseif ($laporan['jenis_integrasi'] === 'CMB') $jenis_integrasi_text = 'Cuti Menjelang Bebas (CMB)';
elseif ($laporan['jenis_integrasi'] === 'CMJB') $jenis_integrasi_text = 'Cuti Menjelang Bebas (CMJB)';
else $jenis_integrasi_text = '-';

// Determine Materi Bimbingan
$materi_final = !empty($laporan['materi_jadwal']) ? $laporan['materi_jadwal'] : $laporan['materi_bimbingan'];

// Format TTL
$ttl = '-';
if (!empty($laporan['tempat_lahir']) || !empty($laporan['tanggal_lahir'])) {
    $tempat = $laporan['tempat_lahir'] ?? '';
    $tanggal = !empty($laporan['tanggal_lahir']) ? date('d F Y', strtotime($laporan['tanggal_lahir'])) : '';
    $ttl = trim("$tempat, $tanggal", ", ");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Bimbingan - <?php echo htmlspecialchars($laporan['klien_nama']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            darkMode: false,
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        sky: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .fade-in {
            animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        
        .fade-in-up {
            animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1; 
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1; 
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8; 
        }

        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: white;
                color: black;
            }
            .print-container {
                box-shadow: none;
                border: none;
                padding: 0;
                margin: 0;
                width: 100%;
            }
            .print-header {
                display: block !important;
            }
            .screen-header {
                display: none !important;
            }
            /* Reset layout for print */
            .p-4.sm\:ml-64.mt-14 {
                padding: 0 !important;
                margin: 0 !important;
            }
            .container {
                max-width: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }
        }
    </style>
</head>
<body class="bg-gray-50 font-sans text-gray-800 min-h-screen selection:bg-sky-100 selection:text-sky-700 transition-colors duration-300">
    
    <div class="no-print">
        <?php include 'includes/sidebar.php'; ?>
        <?php include 'includes/topbar.php'; ?>
    </div>

    <div class="p-4 sm:ml-64 mt-14">
        <div class="container mx-auto px-4 py-8 max-w-5xl fade-in">
            <!-- Print Button -->
            <div class="mb-8 flex items-center justify-between no-print">
                <a href="laporan-bimbingan.php" class="group flex items-center text-gray-500 hover:text-sky-600 transition-all duration-300">
                    <div class="w-10 h-10 rounded-full bg-white border border-gray-200 flex items-center justify-center mr-3 group-hover:border-sky-200 group-hover:bg-sky-50 group-hover:shadow-md transition-all duration-300 shadow-sm">
                        <i class="fas fa-arrow-left text-sm group-hover:-translate-x-1 transition-transform duration-300"></i>
                    </div>
                    <div>
                        <span class="block text-xs text-gray-400 font-medium mb-0.5">Kembali ke</span>
                        <span class="font-bold text-sm">Daftar Laporan</span>
                    </div>
                </a>
                <div class="flex gap-3">
                    <a href="cetak-laporan-pdf.php?id=<?php echo $laporan['id']; ?>" target="_blank" class="group px-5 py-2.5 bg-sky-600 text-white rounded-xl hover:bg-sky-700 hover:shadow-lg hover:shadow-sky-500/30 transition-all duration-300 shadow-md flex items-center text-sm font-bold hover:-translate-y-0.5 active:translate-y-0">
                        <i class="fas fa-file-pdf mr-2 group-hover:scale-110 transition-transform"></i>
                        Unduh PDF
                    </a>
                </div>
            </div>

            <!-- Laporan Container -->
            <div class="bg-white rounded-3xl shadow-xl shadow-gray-200/50 border border-white overflow-hidden print-container fade-in-up" style="animation-delay: 0.1s;">
                
                <!-- Screen Header (Modern) -->
                <div class="screen-header bg-gradient-to-r from-sky-600 to-blue-700 px-8 py-10 text-white relative overflow-hidden">
                <div class="absolute top-0 right-0 w-80 h-80 bg-white/10 rounded-full -mr-20 -mt-20 blur-3xl"></div>
                <div class="absolute bottom-0 left-0 w-40 h-40 bg-blue-500/10 rounded-full -ml-10 -mb-10 blur-2xl"></div>
                
                <div class="relative z-10 flex items-start justify-between">
                    <div>
                        <div class="inline-flex items-center space-x-2 bg-white/10 backdrop-blur-md px-3 py-1 rounded-full text-xs font-medium text-sky-100 border border-white/10 mb-4">
                            <span class="w-1.5 h-1.5 rounded-full bg-sky-400 animate-pulse"></span>
                            <span>Detail Laporan</span>
                        </div>
                        <h1 class="text-3xl font-bold mb-2 tracking-tight">Laporan Bimbingan Kemasyarakatan</h1>
                        <p class="text-slate-300 text-sm max-w-lg leading-relaxed">Dokumen resmi hasil pelaksanaan bimbingan klien pemasyarakatan.</p>
                    </div>
                    <div class="hidden md:block p-4 bg-white/5 rounded-2xl border border-white/10 backdrop-blur-sm">
                        <i class="fas fa-file-signature text-4xl text-white/80"></i>
                    </div>
                </div>
            </div>

            <!-- Print Header (Formal) -->
            <div class="hidden print-header text-center mb-8 border-b-2 border-gray-800 pb-6 pt-8 px-8">
                <h1 class="text-2xl font-bold text-gray-900 mb-2 uppercase tracking-wide">Balai Pemasyarakatan Kelas I Pekanbaru</h1>
                <p class="text-lg text-gray-700 font-serif italic">Laporan Bimbingan Kemasyarakatan</p>
            </div>

            <div class="p-8 lg:p-12">
                <!-- Informasi Laporan -->
                <div class="mb-10 bg-slate-50/50 rounded-2xl p-6 border border-slate-100">
                    <div class="grid md:grid-cols-3 gap-6">
                        <div class="space-y-1">
                            <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider">Nomor Laporan</p>
                            <p class="font-mono text-lg font-bold text-gray-800">LB-<?php echo str_pad($laporan['id'], 6, '0', STR_PAD_LEFT); ?></p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider">Tanggal Laporan</p>
                            <p class="font-bold text-gray-800"><?php echo $tanggal_laporan; ?> <span class="text-gray-400 font-normal text-sm">| <?php echo $waktu_laporan; ?> WIB</span></p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider">Tanggal Bimbingan</p>
                            <p class="font-bold text-gray-800"><?php echo $tanggal_bimbingan; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Data Klien -->
                <div class="mb-10">
                    <div class="flex items-center space-x-3 mb-6 border-b border-slate-100 pb-4">
                        <div class="w-10 h-10 rounded-xl bg-sky-50 flex items-center justify-center text-sky-600">
                            <i class="fas fa-user"></i>
                        </div>
                        <h2 class="text-xl font-bold text-slate-800">Data Klien</h2>
                    </div>
                    
                    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                        <div class="grid md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-slate-100">
                            <div class="p-6 space-y-4">
                                <div class="flex justify-between">
                                    <span class="text-sm text-slate-500">Nama Lengkap</span>
                                    <span class="text-sm font-bold text-slate-800 text-right"><?php echo htmlspecialchars($laporan['klien_nama']); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-slate-500">No. Registrasi</span>
                                    <span class="text-sm font-mono font-bold text-slate-800 bg-slate-50 px-2 py-0.5 rounded text-right"><?php echo htmlspecialchars($laporan['no_registrasi']); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-slate-500">Tempat, Tanggal Lahir</span>
                                    <span class="text-sm font-medium text-slate-800 text-right"><?php echo htmlspecialchars($ttl); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-slate-500">Jenis Kelamin</span>
                                    <span class="text-sm font-medium text-slate-800 text-right"><?php echo htmlspecialchars($laporan['jenis_kelamin'] ?: '-'); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-slate-500">Agama</span>
                                    <span class="text-sm font-medium text-slate-800 text-right"><?php echo htmlspecialchars($laporan['agama'] ?: '-'); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-slate-500">Alamat</span>
                                    <span class="text-sm font-medium text-slate-800 text-right max-w-[60%]"><?php echo htmlspecialchars($laporan['alamat'] ?: '-'); ?></span>
                                </div>
                            </div>
                            <div class="p-6 space-y-4">
                                <div class="flex justify-between">
                                    <span class="text-sm text-slate-500">Pendidikan Terakhir</span>
                                    <span class="text-sm font-medium text-slate-800 text-right"><?php echo htmlspecialchars($laporan['riwayat_pendidikan'] ?: '-'); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-slate-500">Pekerjaan</span>
                                    <span class="text-sm font-medium text-slate-800 text-right"><?php echo htmlspecialchars($laporan['pekerjaan'] ?: '-'); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-slate-500">Status Perkawinan</span>
                                    <span class="text-sm font-medium text-slate-800 text-right"><?php echo htmlspecialchars($laporan['status_pernikahan'] ?: '-'); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-slate-500">Pasal Pidana</span>
                                    <span class="text-sm font-medium text-slate-800 text-right"><?php echo htmlspecialchars($laporan['pasal_pidana'] ?: '-'); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-slate-500">Jenis Integrasi</span>
                                    <span class="text-sm font-bold text-sky-600 text-right"><?php echo htmlspecialchars($jenis_integrasi_text); ?></span>
                                </div>
                                <?php if ($laporan['tanggal_mulai_bimbingan']): ?>
                                <div class="flex justify-between">
                                    <span class="text-sm text-slate-500">Periode Bimbingan</span>
                                    <span class="text-sm font-medium text-slate-800 text-right">
                                        <?php echo date('d M Y', strtotime($laporan['tanggal_mulai_bimbingan'])); ?> 
                                        <?php if ($laporan['tanggal_akhir_bimbingan']): ?>
                                            - <?php echo date('d M Y', strtotime($laporan['tanggal_akhir_bimbingan'])); ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Pembimbingan -->
                <div class="mb-10 group">
                    <div class="flex items-center space-x-3 mb-6 border-b border-slate-100 pb-4">
                        <div class="w-10 h-10 rounded-xl bg-sky-50 flex items-center justify-center text-sky-600 shadow-sm group-hover:scale-110 transition-transform duration-300">
                            <i class="fas fa-book-reader"></i>
                        </div>
                        <h2 class="text-xl font-bold text-slate-800">Detail Pembimbingan</h2>
                    </div>
                    
                    <div class="space-y-6">
                        <div class="bg-slate-50 rounded-2xl p-6 border border-slate-100 hover:shadow-md transition-all duration-300">
                            <p class="text-xs text-slate-500 font-semibold uppercase tracking-wider mb-3">Bentuk Pembimbingan</p>
                            <div class="inline-flex items-center px-4 py-2 bg-white rounded-lg border border-slate-200 shadow-sm text-sm font-medium text-slate-700">
                                <i class="fas fa-handshake mr-2 text-sky-500"></i>
                                <?php echo htmlspecialchars($bentuk_text); ?>
                            </div>
                        </div>

                        <?php if (!empty($laporan['lokasi'])): ?>
                        <div class="bg-orange-50 rounded-2xl p-6 border border-orange-100 hover:shadow-md transition-all duration-300">
                            <p class="text-xs text-orange-600 font-semibold uppercase tracking-wider mb-3">Lokasi Bimbingan</p>
                            <div class="space-y-4">
                                <div class="inline-flex items-center px-4 py-2 bg-white rounded-lg border border-orange-200 shadow-sm text-sm font-medium text-slate-700">
                                    <i class="fas fa-map-marker-alt mr-2 text-orange-500"></i>
                                    <?php echo htmlspecialchars($laporan['lokasi']); ?>
                                </div>
                                
                                <?php if (!empty($laporan['latitude']) && !empty($laporan['longitude'])): ?>
                                <div id="map-view" class="h-64 w-full rounded-xl border border-orange-200 shadow-sm z-0"></div>
                                <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
                                <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
                                <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        var map = L.map('map-view').setView([<?php echo $laporan['latitude']; ?>, <?php echo $laporan['longitude']; ?>], 15);
                                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                            attribution: '© OpenStreetMap contributors'
                                        }).addTo(map);
                                        L.marker([<?php echo $laporan['latitude']; ?>, <?php echo $laporan['longitude']; ?>]).addTo(map)
                                            .bindPopup("<?php echo htmlspecialchars($laporan['lokasi']); ?>")
                                            .openPopup();
                                    });
                                </script>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="grid md:grid-cols-2 gap-6">
                            <div class="bg-white rounded-2xl p-6 border border-gray-200 shadow-sm hover:shadow-lg hover:border-blue-100 transition-all duration-300">
                                <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider mb-3 flex items-center">
                                    <i class="fas fa-book mr-1.5 text-blue-500"></i> Materi Bimbingan
                                </p>
                                <p class="text-gray-700 leading-relaxed whitespace-pre-wrap"><?php echo htmlspecialchars($laporan['materi_bimbingan'] ?: '-'); ?></p>
                            </div>
                            
                            <div class="bg-white rounded-2xl p-6 border border-gray-200 shadow-sm hover:shadow-lg hover:border-green-100 transition-all duration-300">
                                <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider mb-3 flex items-center">
                                    <i class="fas fa-comment-medical mr-1.5 text-green-500"></i> Saran Pembimbing
                                </p>
                                <p class="text-gray-700 leading-relaxed whitespace-pre-wrap"><?php echo htmlspecialchars($laporan['hasil_bimbingan'] ?: '-'); ?></p>
                            </div>
                        </div>
                        
                        <?php if ($laporan['tindak_lanjut']): ?>
                        <div class="bg-blue-50/50 rounded-2xl p-6 border border-blue-100 hover:shadow-md transition-all duration-300">
                            <p class="text-xs text-blue-500 font-semibold uppercase tracking-wider mb-3 flex items-center">
                                <i class="fas fa-tasks mr-1.5"></i> Tindak Lanjut
                            </p>
                            <p class="text-gray-800 leading-relaxed whitespace-pre-wrap font-medium"><?php echo htmlspecialchars($laporan['tindak_lanjut']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Isi Laporan Lengkap -->
                <div class="mb-10 group">
                    <div class="flex items-center space-x-3 mb-6 border-b border-gray-100 pb-4">
                        <div class="w-10 h-10 rounded-xl bg-slate-50 flex items-center justify-center text-slate-600 shadow-sm group-hover:scale-110 transition-transform duration-300">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800">Isi Laporan Lengkap</h2>
                    </div>
                    <div class="bg-white p-8 rounded-2xl border border-gray-200 shadow-sm hover:shadow-lg hover:border-slate-300 transition-all duration-300">
                        <div class="prose max-w-none text-gray-700 leading-relaxed whitespace-pre-wrap font-serif text-lg"><?php echo htmlspecialchars($laporan['isi_laporan']); ?></div>
                    </div>
                </div>

                <!-- Bukti Foto Bimbingan -->
                <?php if (!empty($laporan['foto_bimbingan'])): ?>
                <div class="mb-10 print:break-inside-avoid group">
                    <div class="flex items-center space-x-3 mb-6 border-b border-gray-100 pb-4">
                        <div class="w-10 h-10 rounded-xl bg-pink-50 flex items-center justify-center text-pink-600 shadow-sm group-hover:scale-110 transition-transform duration-300">
                            <i class="fas fa-camera"></i>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800">Dokumentasi Bimbingan</h2>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-2xl flex justify-center border border-gray-200 border-dashed">
                        <img src="../<?php echo htmlspecialchars($laporan['foto_bimbingan']); ?>" 
                             alt="Bukti Foto Bimbingan" 
                             class="max-w-full max-h-[500px] rounded-xl shadow-lg transform hover:scale-[1.01] transition-transform duration-300">
                    </div>
                </div>
                <?php endif; ?>

                <!-- Tanda Tangan -->
                <div class="mt-16 grid md:grid-cols-2 gap-12 print:break-inside-avoid">
                    <div class="text-center group">
                        <p class="text-gray-600 mb-20 font-medium group-hover:text-sky-600 transition-colors">Pembimbing Kemasyarakatan</p>
                        <div class="inline-block px-8 py-2 border-t border-gray-300 group-hover:border-sky-400 transition-colors">
                            <p class="text-gray-900 font-bold text-lg group-hover:text-sky-700 transition-colors"><?php echo htmlspecialchars($laporan['nama_pk'] ?: '-'); ?></p>
                        </div>
                    </div>
                    
                    <!-- KABAPAS -->
                    <div class="text-center group">
                        <p class="text-gray-600 mb-8 font-medium group-hover:text-sky-600 transition-colors">Mengetahui,</p>
                        <p class="text-gray-800 font-bold mb-12 group-hover:text-sky-700 transition-colors">Kepala Balai Pemasyarakatan<br>Kelas I Pekanbaru</p>
                        <div class="inline-block px-8 py-2 border-t border-gray-300 group-hover:border-sky-400 transition-colors">
                            <p class="text-gray-900 font-bold text-lg group-hover:text-sky-700 transition-colors">...........................</p>
                            <p class="text-gray-500 text-xs mt-1">NIP. ...........................</p>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="mt-12 text-center text-xs text-gray-400 border-t border-gray-100 pt-8 no-print">
                    <p class="mb-1">Laporan ini dibuat secara otomatis oleh Sistem Informasi Pengawasan BAPAS Pekanbaru</p>
                    <p class="font-mono">ID Laporan: <?php echo $laporan['id']; ?> • Dicetak: <?php echo date('d/m/Y H:i'); ?></p>
                </div>
            </div>
        </div>
        </div>
    </div>
</body>
</html>
