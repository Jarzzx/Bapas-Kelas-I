<?php
require_once '../shared/config/database.php';
require_once '../shared/config/auth.php';
requirePKLogin();

$conn = getDBConnection();
$pk_id = $_SESSION['user_id'];
$laporan_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get laporan data
$stmt = $conn->prepare("
    SELECT lp.*, dk.nama as klien_nama, dk.no_registrasi, dk.alamat, dk.pasal_pidana,
           dk.tanggal_mulai_bimbingan, dk.tanggal_akhir_bimbingan, dk.jenis_integrasi, dk.status as status_klien,
           pk.nama as pk_nama, pk.nip as pk_nip
    FROM laporan_pengawasan lp
    JOIN data_klien dk ON lp.klien_id = dk.id
    JOIN pk_users pk ON lp.pk_id = pk.id
    WHERE lp.id = ? AND lp.pk_id = ?
");
$stmt->bind_param("ii", $laporan_id, $pk_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Laporan tidak ditemukan atau Anda tidak memiliki akses!');
}

$laporan = $result->fetch_assoc();
$stmt->close();
// $conn->close(); // Keep connection open for sidebar

// Format tanggal
$tanggal_laporan = date('d F Y', strtotime($laporan['tanggal_laporan']));
$waktu_laporan = date('H:i:s', strtotime($laporan['tanggal_laporan']));

// Format jenis integrasi
$jenis_integrasi_text = '';
if ($laporan['jenis_integrasi'] === 'PB') $jenis_integrasi_text = 'Pembebasan Bersyarat (PB)';
elseif ($laporan['jenis_integrasi'] === 'CMB') $jenis_integrasi_text = 'Cuti Menjelang Bebas (CMB)';
elseif ($laporan['jenis_integrasi'] === 'CMJB') $jenis_integrasi_text = 'Cuti Menjelang Bebas (CMJB)';
else $jenis_integrasi_text = '-';

// Status color
$status_color = $laporan['status_klien'] === 'Baik' ? 'text-green-600' : 
               ($laporan['status_klien'] === 'Perlu Perhatian' ? 'text-yellow-600' : 'text-red-600');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pengawasan - <?php echo htmlspecialchars($laporan['klien_nama']); ?></title>
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
                            950: '#082f49',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        
        .fade-in { animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .fade-in-up { animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        @media print {
            .no-print { display: none !important; }
            body { background: white; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .print-container { box-shadow: none; border: none; padding: 0; margin: 0; width: 100%; max-width: none; }
            .print-header-hidden { display: none; }
            .print-break-inside-avoid { break-inside: avoid; }
            /* Ensure text is black for printing */
            * { color: black !important; text-shadow: none !important; }
            /* Show borders clearly */
            .border-print { border: 1px solid #ddd !important; }
        }
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-800 min-h-screen selection:bg-sky-100 selection:text-sky-700">
    <div class="container mx-auto px-4 py-8 max-w-5xl fade-in">
        <!-- Action Bar -->
    <div class="mb-8 flex items-center justify-between no-print">
        <a href="laporan-pengawasan.php" class="group flex items-center text-slate-500 hover:text-sky-600 transition-all duration-300">
            <div class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center mr-3 group-hover:border-sky-200 group-hover:bg-sky-50 group-hover:shadow-md transition-all duration-300 shadow-sm">
                <i class="fas fa-arrow-left text-sm group-hover:-translate-x-1 transition-transform duration-300"></i>
            </div>
            <div>
                <span class="block text-xs text-slate-400 font-medium mb-0.5">Kembali ke</span>
                <span class="font-bold text-sm">Daftar Laporan</span>
            </div>
        </a>

        <a href="cetak-laporan-pengawasan-pdf.php?id=<?php echo $laporan_id; ?>" target="_blank" class="group flex items-center text-slate-500 hover:text-sky-600 transition-all duration-300">
            <div class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center mr-3 group-hover:border-sky-200 group-hover:bg-sky-50 group-hover:shadow-md transition-all duration-300 shadow-sm">
                <i class="fas fa-print text-sm group-hover:scale-110 transition-transform duration-300"></i>
            </div>
            <div>
                <span class="block text-xs text-slate-400 font-medium mb-0.5">Export</span>
                <span class="font-bold text-sm">Cetak PDF</span>
            </div>
        </a>
    </div>

    <!-- Laporan Container -->
    <div class="glass-card rounded-3xl shadow-xl overflow-hidden print-container fade-in-up" style="animation-delay: 0.1s;">
        <!-- Screen Header (Modern) -->
        <div class="bg-gradient-to-r from-sky-600 to-blue-700 px-8 py-10 text-white relative overflow-hidden no-print">
            <div class="absolute top-0 right-0 w-80 h-80 bg-white/10 rounded-full -mr-20 -mt-20 blur-3xl"></div>
            <div class="absolute bottom-0 left-0 w-40 h-40 bg-sky-400/20 rounded-full -ml-10 -mb-10 blur-2xl"></div>
            
            <div class="relative z-10 flex items-start justify-between">
                <div>
                    <div class="inline-flex items-center space-x-2 bg-white/20 backdrop-blur-md px-3 py-1 rounded-full text-xs font-medium text-white border border-white/20 mb-4 shadow-sm">
                        <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                        <span>Laporan Resmi</span>
                    </div>
                    <h1 class="text-3xl font-bold mb-2 tracking-tight">Laporan Pengawasan</h1>
                    <p class="text-sky-100 text-sm max-w-lg leading-relaxed font-medium">Detail laporan pengawasan klien pemasyarakatan.</p>
                </div>
                <div class="hidden md:block p-4 bg-white/10 rounded-2xl border border-white/20 backdrop-blur-sm shadow-inner">
                    <i class="fas fa-file-contract text-4xl text-white"></i>
                </div>
            </div>
        </div>

        <!-- Print Header (Official) -->
        <div class="hidden print:block text-center mb-8 border-b-2 border-gray-900 pb-6 pt-4">
            <h1 class="text-2xl font-bold text-black mb-1 uppercase tracking-wider">Kementerian Imigrasi dan Pemasyarakatan RI</h1>
            <h2 class="text-xl font-bold text-black mb-2 uppercase">Balai Pemasyarakatan Kelas I Pekanbaru</h2>
            <p class="text-lg font-semibold text-black">LAPORAN PENGAWASAN KEMASYARAKATAN</p>
            <p class="text-sm text-gray-600 mt-2">Nomor: LP-<?php echo str_pad($laporan['id'], 6, '0', STR_PAD_LEFT); ?></p>
        </div>

        <div class="p-8 lg:p-10">
            <!-- Metadata Grid -->
            <div class="grid md:grid-cols-2 gap-6 mb-10 print-break-inside-avoid">
                <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100 print:bg-white print:border-gray-200 hover:shadow-md transition-shadow duration-300">
                    <div class="text-xs text-slate-500 font-bold uppercase tracking-wider mb-2">Nomor Laporan</div>
                    <div class="text-xl font-bold text-slate-800">#LP-<?php echo str_pad($laporan['id'], 6, '0', STR_PAD_LEFT); ?></div>
                </div>
                <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100 print:bg-white print:border-gray-200 hover:shadow-md transition-shadow duration-300">
                    <div class="text-xs text-slate-500 font-bold uppercase tracking-wider mb-2">Waktu Laporan</div>
                    <div class="flex items-center text-slate-800 font-bold">
                        <i class="fas fa-calendar-alt mr-2 text-slate-400"></i>
                        <?php echo $tanggal_laporan; ?>
                        <span class="mx-2 text-slate-300">|</span>
                        <i class="fas fa-clock mr-2 text-slate-400"></i>
                        <?php echo $waktu_laporan; ?> WIB
                    </div>
                </div>
            </div>

            <!-- Data Klien Card -->
            <div class="mb-10 print-break-inside-avoid group">
                <div class="flex items-center space-x-2 mb-6">
                    <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-600 shadow-sm group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-user"></i>
                    </div>
                    <h3 class="font-bold text-slate-800 text-lg">Data Klien</h3>
                </div>
                
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-lg hover:border-blue-100 transition-all duration-300 print:shadow-none print:border-gray-300">
                    <div class="grid md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-slate-100 print:divide-gray-200">
                        <div class="p-6 space-y-4">
                                <div>
                                    <p class="text-xs text-slate-500 font-medium uppercase tracking-wider mb-1">Nama Lengkap</p>
                                    <p class="font-bold text-slate-800 text-lg"><?php echo htmlspecialchars($laporan['klien_nama']); ?></p>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-500 font-medium uppercase tracking-wider mb-1">Nomor Registrasi</p>
                                    <div class="inline-flex items-center px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 font-mono text-sm font-medium">
                                        <?php echo htmlspecialchars($laporan['no_registrasi']); ?>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-500 font-medium uppercase tracking-wider mb-1">Alamat</p>
                                    <p class="text-slate-700 leading-relaxed"><?php echo htmlspecialchars($laporan['alamat'] ?: '-'); ?></p>
                                </div>
                            </div>
                            <div class="p-6 space-y-4">
                                <div>
                                    <p class="text-xs text-slate-500 font-medium uppercase tracking-wider mb-1">Pasal Pidana</p>
                                    <p class="text-slate-700 font-medium"><?php echo htmlspecialchars($laporan['pasal_pidana'] ?: '-'); ?></p>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-500 font-medium uppercase tracking-wider mb-1">Jenis Integrasi</p>
                                    <p class="text-slate-700 font-medium"><?php echo htmlspecialchars($jenis_integrasi_text); ?></p>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-500 font-medium uppercase tracking-wider mb-1">Status Pengawasan</p>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold <?php 
                                        echo $laporan['status_klien'] === 'Baik' ? 'bg-green-100 text-green-700' : 
                                            ($laporan['status_klien'] === 'Perlu Perhatian' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'); 
                                    ?>">
                                        <span class="w-2 h-2 rounded-full mr-2 <?php 
                                            echo $laporan['status_klien'] === 'Baik' ? 'bg-green-500' : 
                                                ($laporan['status_klien'] === 'Perlu Perhatian' ? 'bg-yellow-500' : 'bg-red-500'); 
                                        ?>"></span>
                                        <?php echo htmlspecialchars($laporan['status_klien'] ?: '-'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php if ($laporan['tanggal_mulai_bimbingan']): ?>
                        <div class="bg-slate-50 px-6 py-4 border-t border-slate-100 print:bg-white print:border-gray-200">
                            <p class="text-xs text-slate-500 font-medium uppercase tracking-wider mb-1">Periode Bimbingan</p>
                            <p class="text-slate-700 font-medium">
                                <i class="fas fa-calendar-week mr-2 text-slate-400"></i>
                                <?php echo date('d F Y', strtotime($laporan['tanggal_mulai_bimbingan'])); ?> 
                                <?php if ($laporan['tanggal_akhir_bimbingan']): ?>
                                    <span class="mx-2 text-slate-300">s/d</span> 
                                    <?php echo date('d F Y', strtotime($laporan['tanggal_akhir_bimbingan'])); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Isi Laporan -->
                <div class="mb-10 print-break-inside-avoid">
                    <div class="flex items-center space-x-2 mb-6">
                        <div class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center text-green-600">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <h3 class="font-bold text-slate-800 text-lg">Hasil Pengawasan</h3>
                    </div>
                    
                    <div class="bg-white rounded-2xl p-8 border border-slate-200 shadow-sm leading-loose text-slate-700 text-justify print:shadow-none print:border-gray-300 print:p-0">
                        <?php echo nl2br(htmlspecialchars($laporan['isi_laporan'])); ?>
                    </div>
                </div>

                <!-- New Fields Display -->
                <?php if (!empty($laporan['nomor_sk']) || !empty($laporan['simpulan'])): ?>
                <div class="mb-10 print-break-inside-avoid">
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <h5 class="font-bold text-slate-700 border-b border-slate-200 pb-2">Data Legalitas</h5>
                            <div>
                                <span class="text-xs font-semibold text-slate-500 block">Nomor SK</span>
                                <span class="text-sm text-slate-800"><?php echo htmlspecialchars($laporan['nomor_sk'] ?? '-'); ?></span>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-slate-500 block">Tanggal SK</span>
                                <span class="text-sm text-slate-800"><?php echo !empty($laporan['tanggal_sk']) ? date('d F Y', strtotime($laporan['tanggal_sk'])) : '-'; ?></span>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-slate-500 block">Nomor Litmas</span>
                                <span class="text-sm text-slate-800"><?php echo htmlspecialchars($laporan['nomor_litmas'] ?? '-'); ?></span>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-slate-500 block">Tanggal Litmas</span>
                                <span class="text-sm text-slate-800"><?php echo !empty($laporan['tanggal_litmas']) ? date('d F Y', strtotime($laporan['tanggal_litmas'])) : '-'; ?></span>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <h5 class="font-bold text-slate-700 border-b border-slate-200 pb-2">Analisis PK</h5>
                            <div>
                                <span class="text-xs font-semibold text-slate-500 block">Simpulan</span>
                                <p class="text-sm text-slate-800 whitespace-pre-line"><?php echo htmlspecialchars($laporan['simpulan'] ?? '-'); ?></p>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-slate-500 block">Saran/Rekomendasi</span>
                                <p class="text-sm text-slate-800 whitespace-pre-line"><?php echo htmlspecialchars($laporan['saran'] ?? '-'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Catatan -->
                <?php if ($laporan['catatan']): ?>
                <div class="mb-10 print-break-inside-avoid">
                    <div class="flex items-center space-x-2 mb-4">
                        <div class="w-8 h-8 rounded-lg bg-yellow-50 flex items-center justify-center text-yellow-600">
                            <i class="fas fa-lightbulb"></i>
                        </div>
                        <h3 class="font-bold text-slate-800 text-lg">Catatan Tambahan</h3>
                    </div>
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-6 rounded-r-xl text-slate-800">
                        <?php echo nl2br(htmlspecialchars($laporan['catatan'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Tanda Tangan (Print Friendly) -->
                <div class="mt-20 grid grid-cols-2 gap-12 print-break-inside-avoid">
                    <div class="text-center group cursor-default">
                        <div class="inline-block p-4 rounded-xl transition-all duration-300 group-hover:bg-slate-50">
                            <p class="text-slate-600 mb-20 font-medium group-hover:text-blue-600 transition-colors">Pembimbing Kemasyarakatan</p>
                            <div class="inline-block px-8 pt-2 border-t-2 border-slate-300 group-hover:border-blue-500 transition-all duration-300 group-hover:w-full">
                                <p class="text-slate-800 font-bold text-lg group-hover:text-blue-700 transition-colors"><?php echo htmlspecialchars($laporan['pk_nama']); ?></p>
                                <p class="text-sm text-slate-500 mt-1 group-hover:text-blue-500 transition-colors">NIP. <?php echo htmlspecialchars($laporan['pk_nip']); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="text-center group cursor-default">
                        <div class="inline-block p-4 rounded-xl transition-all duration-300 group-hover:bg-slate-50">
                            <p class="text-slate-600 mb-20 font-medium group-hover:text-blue-600 transition-colors">Mengetahui,<br>Kepala BAPAS Kelas I Pekanbaru</p>
                            <div class="inline-block px-8 pt-2 border-t-2 border-slate-300 group-hover:border-blue-500 transition-all duration-300 group-hover:w-full">
                                <p class="text-slate-800 font-bold text-lg group-hover:text-blue-700 transition-colors">(Nama Kepala BAPAS)</p>
                                <p class="text-sm text-slate-500 mt-1 group-hover:text-blue-500 transition-colors">NIP. ..........................</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="mt-12 pt-8 border-t border-slate-100 text-center print:hidden">
                    <p class="text-xs text-slate-400 font-medium bg-slate-50 inline-block px-4 py-2 rounded-full border border-slate-100">
                        <i class="fas fa-history mr-1"></i>
                        Dokumen ini digenerate otomatis oleh Sistem Informasi Pengawasan BAPAS pada <?php echo date('d F Y H:i'); ?> WIB
                    </p>
                </div>
            </div>
        </div>
        </div>
    </div> <!-- Close content wrapper -->
</body>
</html>
