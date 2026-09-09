<?php
require_once '../shared/config/database.php';
require_once '../shared/config/auth.php';
requireKlienLogin();

// Check approval status
$conn = getDBConnection();
$klien_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT status_approval FROM klien_users WHERE id = ?");
$stmt->bind_param("i", $klien_id);
$stmt->execute();
$result = $stmt->get_result();
$user_status = $result->fetch_assoc();
$stmt->close();

if ($user_status['status_approval'] !== 'approved') {
    header('Location: pending.php');
    exit;
}

$nama = $_SESSION['nama'] ?? 'User';

// Get client info
$klien_info = null;
$stmt = $conn->prepare("SELECT * FROM klien_users WHERE id = ?");
$stmt->bind_param("i", $klien_id);
$stmt->execute();
$result = $stmt->get_result();
$klien_info = $result->fetch_assoc();
$stmt->close();

// Check if biodata is complete
$biodata_lengkap = $klien_info['biodata_lengkap'] ?? 'belum';

// Robust validation: Check mandatory fields
$required_fields = ['nik', 'tempat_lahir', 'tanggal_lahir', 'agama', 'jenis_kelamin', 'status_pernikahan', 'pekerjaan', 'riwayat_pendidikan'];
foreach ($required_fields as $field) {
    if (empty($klien_info[$field])) {
        $biodata_lengkap = 'belum';
        break;
    }
}

$pk_info = null;
if ($klien_info && !empty($klien_info['pk_id'])) {
    $stmt = $conn->prepare("SELECT nama, nip FROM pk_users WHERE id = ?");
    $stmt->bind_param("i", $klien_info['pk_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $pk_info = $result->fetch_assoc();
    $stmt->close();
}

// Get related data klien
$data_klien = null;
if ($klien_info && !empty($klien_info['no_registrasi'])) {
    $stmt = $conn->prepare("SELECT * FROM data_klien WHERE no_registrasi = ?");
    $stmt->bind_param("s", $klien_info['no_registrasi']);
    $stmt->execute();
    $result = $stmt->get_result();
    $data_klien = $result->fetch_assoc();
    $stmt->close();
}

// Get jadwal bimbingan yang akan datang
$jadwal_bimbingan = $conn->query("
    SELECT jb.*, pk.nama as pk_nama
    FROM jadwal_bimbingan jb
    JOIN pk_users pk ON jb.pk_id = pk.id
    WHERE jb.klien_id = $klien_id
    AND jb.status = 'terjadwal'
    AND jb.tanggal_bimbingan >= NOW()
    ORDER BY jb.tanggal_bimbingan ASC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Get recent reports
$laporan_pengawasan = null;
$laporan_bimbingan_list = null; // Renamed to avoid variable name conflict
if ($data_klien && !empty($data_klien['id'])) {
    $stmt = $conn->prepare("SELECT * FROM laporan_pengawasan WHERE klien_id = ? ORDER BY tanggal_laporan DESC LIMIT 5");
    $stmt->bind_param("i", $data_klien['id']);
    $stmt->execute();
    $laporan_pengawasan = $stmt->get_result();
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT * FROM laporan_bimbingan WHERE klien_id = ? ORDER BY tanggal_laporan DESC LIMIT 5");
    $stmt->bind_param("i", $data_klien['id']);
    $stmt->execute();
    $laporan_bimbingan_list = $stmt->get_result(); // Used renamed variable
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Klien - BAPAS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7', // Sky-600
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        },
                        secondary: {
                            50: '#f0fdfa',
                            100: '#ccfbf1',
                            200: '#99f6e4',
                            300: '#5eead4',
                            400: '#2dd4bf',
                            500: '#14b8a6', // Teal-500
                            600: '#0d9488',
                            700: '#0f766e',
                            800: '#115e59',
                            900: '#134e4a',
                        }
                    }
                }
            }
        }
    </script>
    <style>
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
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-800 antialiased selection:bg-sky-100 selection:text-sky-700">

    <!-- Navigation -->
    <nav class="bg-white/80 backdrop-blur-md border-b border-slate-200 fixed w-full z-50 transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20">
                <div class="flex items-center gap-3">
                    <div class="bg-gradient-to-br from-sky-500 to-teal-500 p-2 rounded-xl shadow-lg shadow-sky-500/20">
                        <i class="fas fa-shield-alt text-white text-xl"></i>
                    </div>
                    <div>
                        <span class="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-sky-600 to-teal-600">BAPAS KELAS I PEKANBARU</span>
                        <span class="block text-xs text-slate-500 font-medium tracking-wide">SISTEM PENGAWASAN KLIEN</span>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="hidden md:flex items-center gap-2 text-sm font-medium text-slate-600 bg-slate-50 px-4 py-2 rounded-full border border-slate-100">
                        <i class="far fa-calendar-alt text-sky-500"></i>
                        <span id="current-date"></span>
                        <span class="w-px h-4 bg-slate-300 mx-2"></span>
                        <i class="far fa-clock text-sky-500"></i>
                        <span id="current-time"></span>
                    </div>
                    <div class="relative group">
                        <button class="flex items-center gap-3 focus:outline-none">
                            <div class="text-right hidden md:block">
                                <p class="text-sm font-bold text-slate-700 group-hover:text-sky-600 transition-colors"><?php echo htmlspecialchars($nama); ?></p>
                                <p class="text-xs text-slate-500">Klien Pemasyarakatan</p>
                            </div>
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-sky-100 to-teal-100 border-2 border-white shadow-md flex items-center justify-center text-sky-600 group-hover:scale-105 transition-transform duration-300">
                                <i class="fas fa-user"></i>
                            </div>
                        </button>
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-slate-100 py-1 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 transform origin-top-right z-50">
                            <a href="logout.php" class="block px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 hover:text-red-700 transition-colors">
                                <i class="fas fa-sign-out-alt mr-2"></i> Keluar
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="pt-28 pb-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        
        <!-- Welcome Hero -->
        <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-white overflow-hidden fade-in-up mb-8 relative group" style="animation-delay: 0.1s;">
            <div class="absolute inset-0 bg-gradient-to-r from-sky-500/5 to-teal-500/5 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
            <div class="bg-gradient-to-br from-sky-600 via-sky-700 to-teal-700 px-8 py-10 text-white relative overflow-hidden">
                <div class="absolute top-0 right-0 w-96 h-96 bg-white/10 rounded-full -mr-20 -mt-20 blur-3xl"></div>
                <div class="absolute bottom-0 left-0 w-60 h-60 bg-teal-400/20 rounded-full -ml-10 -mb-10 blur-2xl"></div>
                
                <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-6">
                    <div>
                        <div class="inline-flex items-center space-x-2 bg-white/10 backdrop-blur-md px-3 py-1 rounded-full text-xs font-medium text-sky-100 border border-white/10 mb-4">
                            <span class="w-1.5 h-1.5 rounded-full bg-teal-300 animate-pulse"></span>
                            <span>Dashboard Klien</span>
                        </div>
                        <h1 class="text-3xl font-bold mb-2 tracking-tight">Selamat Datang, <?php echo htmlspecialchars($nama); ?></h1>
                        <p class="text-sky-100 text-sm max-w-lg leading-relaxed">Pantau jadwal bimbingan dan status laporan Anda dengan mudah.</p>
                    </div>
                    <div class="flex gap-3">
                        <?php if ($biodata_lengkap === 'belum'): ?>
                        <a href="lengkapi-biodata.php" class="px-5 py-2.5 bg-white text-sky-700 rounded-xl font-bold text-sm shadow-lg hover:bg-sky-50 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300 flex items-center gap-2">
                            <i class="fas fa-user-edit"></i> Lengkapi Biodata
                        </a>
                        <?php endif; ?>
                        <a href="laporan-bimbingan.php" class="px-5 py-2.5 bg-sky-500/30 backdrop-blur-md border border-white/20 text-white rounded-xl font-bold text-sm hover:bg-sky-500/40 transition-all duration-300 flex items-center gap-2">
                            <i class="fas fa-clipboard-list"></i> Buat Laporan
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
            
            <!-- Left Column: Status & Jadwal -->
            <div class="lg:col-span-2 space-y-8">
                
                <!-- Status Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 fade-in-up" style="animation-delay: 0.2s;">
                    <!-- Status Biodata -->
                    <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm hover:shadow-md transition-all duration-300 group">
                        <div class="flex items-start justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl <?php echo $biodata_lengkap === 'sudah' ? 'bg-teal-50 text-teal-600' : 'bg-amber-50 text-amber-600'; ?> flex items-center justify-center text-xl group-hover:scale-110 transition-transform duration-300">
                                <i class="fas <?php echo $biodata_lengkap === 'sudah' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-bold <?php echo $biodata_lengkap === 'sudah' ? 'bg-teal-50 text-teal-700' : 'bg-amber-50 text-amber-700'; ?>">
                                <?php echo $biodata_lengkap === 'sudah' ? 'Lengkap' : 'Belum Lengkap'; ?>
                            </span>
                        </div>
                        <h3 class="text-slate-500 text-sm font-medium mb-1">Status Biodata</h3>
                        <p class="text-2xl font-bold text-slate-800">
                            <?php echo $biodata_lengkap === 'sudah' ? 'Terverifikasi' : 'Perlu Update'; ?>
                        </p>
                    </div>

                    <!-- Pembimbing Info -->
                    <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm hover:shadow-md transition-all duration-300 group">
                        <div class="flex items-start justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center text-xl group-hover:scale-110 transition-transform duration-300">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-sky-50 text-sky-700">Pembimbing</span>
                        </div>
                        <h3 class="text-slate-500 text-sm font-medium mb-1">Pembimbing Kemasyarakatan</h3>
                        <p class="text-lg font-bold text-slate-800 truncate" title="<?php echo $pk_info['nama'] ?? '-'; ?>">
                            <?php echo $pk_info['nama'] ?? 'Belum Ditentukan'; ?>
                        </p>
                        <?php if($pk_info): ?>
                        <p class="text-xs text-slate-400 mt-1">NIP. <?php echo $pk_info['nip']; ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Jadwal Bimbingan -->
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden fade-in-up" style="animation-delay: 0.3s;">
                    <div class="p-6 border-b border-slate-50 flex justify-between items-center bg-gradient-to-r from-slate-50 to-white">
                        <div>
                            <h3 class="font-bold text-slate-800 text-lg">Jadwal Bimbingan</h3>
                            <p class="text-slate-500 text-sm">Agenda bimbingan mendatang</p>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-sky-50 flex items-center justify-center text-sky-600">
                            <i class="far fa-calendar-check"></i>
                        </div>
                    </div>
                    <div class="divide-y divide-slate-50">
                        <?php if (empty($jadwal_bimbingan)): ?>
                            <div class="p-8 text-center">
                                <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-300">
                                    <i class="far fa-calendar-times text-2xl"></i>
                                </div>
                                <p class="text-slate-500 font-medium">Belum ada jadwal bimbingan</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($jadwal_bimbingan as $jadwal): ?>
                                <div class="p-5 hover:bg-slate-50 transition-colors group">
                                    <div class="flex items-start gap-4">
                                        <div class="flex-shrink-0 w-14 text-center bg-sky-50 rounded-xl p-2 border border-sky-100 group-hover:bg-sky-100 transition-colors">
                                            <span class="block text-xs font-bold text-sky-600 uppercase mb-0.5"><?php echo date('M', strtotime($jadwal['tanggal_bimbingan'])); ?></span>
                                            <span class="block text-xl font-bold text-sky-700"><?php echo date('d', strtotime($jadwal['tanggal_bimbingan'])); ?></span>
                                        </div>
                                        <div class="flex-grow">
                                            <div class="flex justify-between items-start">
                                                <h4 class="font-bold text-slate-800 group-hover:text-sky-700 transition-colors mb-1"><?php echo htmlspecialchars($jadwal['judul_bimbingan']); ?></h4>
                                                <span class="text-xs font-medium px-2 py-1 rounded bg-slate-100 text-slate-600 border border-slate-200">
                                                    <?php echo date('H:i', strtotime($jadwal['tanggal_bimbingan'])); ?> WIB
                                                </span>
                                            </div>
                                            <p class="text-sm text-slate-600 mb-2 line-clamp-1"><?php echo htmlspecialchars($jadwal['materi_bimbingan']); ?></p>
                                            <div class="flex flex-wrap items-center gap-4 text-xs text-slate-500 mb-3">
                                                <span class="flex items-center gap-1.5">
                                                    <i class="fas fa-map-marker-alt text-teal-500"></i>
                                                    <?php echo htmlspecialchars($jadwal['lokasi_bimbingan']); ?>
                                                </span>
                                                <span class="flex items-center gap-1.5">
                                                    <i class="fas fa-video text-teal-500"></i>
                                                    <?php echo ucfirst($jadwal['jenis_bimbingan']); ?>
                                                </span>
                                            </div>
                                            
                                            <?php if ($jadwal['jenis_bimbingan'] === 'daring' && !empty($jadwal['link_meeting'])): ?>
                                                <a href="<?php echo htmlspecialchars($jadwal['link_meeting']); ?>" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-sky-500 to-teal-500 text-white rounded-lg text-xs font-bold shadow-md shadow-sky-500/20 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300">
                                                    <i class="fas fa-video"></i> 
                                                    <span>Gabung Meeting</span>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Right Column: Riwayat -->
            <div class="space-y-8 fade-in-up" style="animation-delay: 0.4s;">
                
                <!-- Recent Reports -->
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden h-full">
                    <div class="p-6 border-b border-slate-50 bg-gradient-to-r from-slate-50 to-white">
                        <h3 class="font-bold text-slate-800 text-lg">Riwayat Laporan</h3>
                        <p class="text-slate-500 text-sm">Aktivitas laporan terakhir</p>
                    </div>
                    <div class="p-4 space-y-4">
                        <?php if ((!$laporan_pengawasan || $laporan_pengawasan->num_rows === 0) && (!$laporan_bimbingan_list || $laporan_bimbingan_list->num_rows === 0)): ?>
                            <div class="text-center py-8 text-slate-500">
                                <i class="fas fa-folder-open text-4xl mb-3 text-slate-200"></i>
                                <p>Belum ada riwayat laporan</p>
                            </div>
                        <?php else: ?>
                            <!-- Combine and sort could be done here, but for simplicity showing lists -->
                            <?php if ($laporan_bimbingan_list): ?>
                                <?php while($lb = $laporan_bimbingan_list->fetch_assoc()): ?>
                                    <div class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 border border-transparent hover:border-slate-100 transition-all">
                                        <div class="w-10 h-10 rounded-full bg-teal-50 flex items-center justify-center text-teal-600 flex-shrink-0">
                                            <i class="fas fa-file-alt"></i>
                                        </div>
                                        <div class="flex-grow min-w-0">
                                            <p class="text-sm font-bold text-slate-800 truncate">Laporan Bimbingan</p>
                                            <p class="text-xs text-slate-500 truncate"><?php echo date('d M Y', strtotime($lb['tanggal_laporan'])); ?></p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-medium px-2 py-1 rounded-full bg-teal-50 text-teal-600 hidden sm:inline-block">Selesai</span>
                                            <a href="cetak-laporan-pdf.php?id=<?php echo $lb['id']; ?>" target="_blank" 
                                               class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 hover:bg-red-50 hover:text-red-500 hover:shadow-sm transition-all" 
                                               title="Download PDF">
                                                <i class="fas fa-file-pdf text-xs"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php endif; ?>
                            
                            <?php if ($laporan_pengawasan): ?>
                                <?php while($lp = $laporan_pengawasan->fetch_assoc()): ?>
                                    <div class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 border border-transparent hover:border-slate-100 transition-all">
                                        <div class="w-10 h-10 rounded-full bg-sky-50 flex items-center justify-center text-sky-600 flex-shrink-0">
                                            <i class="fas fa-eye"></i>
                                        </div>
                                        <div class="flex-grow min-w-0">
                                            <p class="text-sm font-bold text-slate-800 truncate">Laporan Pengawasan</p>
                                            <p class="text-xs text-slate-500 truncate"><?php echo date('d M Y', strtotime($lp['tanggal_laporan'])); ?></p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-medium px-2 py-1 rounded-full bg-sky-50 text-sky-600 hidden sm:inline-block">Dipantau</span>
                                            <a href="cetak-laporan-pengawasan-pdf.php?id=<?php echo $lp['id']; ?>" target="_blank"
                                               class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 hover:bg-red-50 hover:text-red-500 hover:shadow-sm transition-all"
                                               title="Download Laporan Pengawasan">
                                                <i class="fas fa-file-pdf text-xs"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <script>
        function updateTime() {
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('current-date').textContent = now.toLocaleDateString('id-ID', options);
            document.getElementById('current-time').textContent = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) + ' WIB';
        }
        setInterval(updateTime, 1000);
        updateTime();

        // Mobile Menu Toggle
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');

        if (mobileMenuBtn && mobileMenu) {
            mobileMenuBtn.addEventListener('click', () => {
                mobileMenu.classList.toggle('hidden');
                
                // Optional: Animate icon
                const icon = mobileMenuBtn.querySelector('i');
                if (mobileMenu.classList.contains('hidden')) {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                } else {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                }
            });
        }
    </script>
</body>
</html>
