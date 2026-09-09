<?php
require_once '../shared/config/database.php';
require_once '../shared/config/auth.php';
requirePKLogin();

$conn = getDBConnection();
$pk_id = $_SESSION['user_id'];
$nama = $_SESSION['nama'];

// Get pending approvals count untuk PK ini saja
$pending_count = $conn->query("SELECT COUNT(*) as total FROM klien_users WHERE status_approval = 'pending' AND pk_id = $pk_id")->fetch_assoc()['total'];

// Get klien yang sudah melengkapi biodata tapi belum ada dokumen lengkap
$klien_biodata_lengkap = $conn->query("
    SELECT ku.id, ku.nama, ku.no_registrasi, ku.biodata_dilengkapi_at,
           dk.id as data_klien_id, dk.dokumen_lengkap
    FROM klien_users ku
    LEFT JOIN data_klien dk ON ku.no_registrasi = dk.no_registrasi AND dk.pk_id = $pk_id
    WHERE ku.pk_id = $pk_id 
    AND ku.status_approval = 'approved'
    AND ku.biodata_lengkap = 'sudah'
    AND (dk.id IS NULL OR dk.dokumen_lengkap = 'belum')
    ORDER BY ku.biodata_dilengkapi_at DESC
")->fetch_all(MYSQLI_ASSOC);
$biodata_lengkap_count = count($klien_biodata_lengkap);

// Chart Data: Monthly unique clients
$monthly_stats = $conn->query("
    SELECT 
        DATE_FORMAT(COALESCE(jb.tanggal_bimbingan, lb.tanggal_bimbingan, lb.tanggal_laporan), '%Y-%m') as bulan,
        COUNT(DISTINCT lb.klien_id) as total_klien
    FROM laporan_bimbingan lb
    LEFT JOIN jadwal_bimbingan jb ON lb.jadwal_bimbingan_id = jb.id
    WHERE lb.pk_id = $pk_id 
    AND (jb.tanggal_bimbingan >= DATE_SUB(NOW(), INTERVAL 1 YEAR) OR lb.tanggal_bimbingan >= DATE_SUB(NOW(), INTERVAL 1 YEAR))
    GROUP BY bulan
    ORDER BY bulan ASC
")->fetch_all(MYSQLI_ASSOC);

$chart_labels = [];
$chart_data = [];
foreach ($monthly_stats as $stat) {
    $chart_labels[] = date('M Y', strtotime($stat['bulan']));
    $chart_data[] = $stat['total_klien'];
}

// Client Detail Data
$client_details = $conn->query("
    SELECT 
        dk.nama, dk.no_registrasi,
        COUNT(lb.id) as total_bimbingan,
        MAX(COALESCE(jb.tanggal_bimbingan, lb.tanggal_bimbingan, lb.tanggal_laporan)) as last_bimbingan,
        (SELECT GROUP_CONCAT(DISTINCT COALESCE(jb2.materi_bimbingan, lb2.materi_bimbingan) SEPARATOR '||') 
         FROM laporan_bimbingan lb2 
         LEFT JOIN jadwal_bimbingan jb2 ON lb2.jadwal_bimbingan_id = jb2.id
         WHERE lb2.klien_id = dk.id AND lb2.pk_id = $pk_id
         ORDER BY COALESCE(jb2.tanggal_bimbingan, lb2.tanggal_bimbingan) DESC LIMIT 5) as recent_topics
    FROM data_klien dk
    LEFT JOIN laporan_bimbingan lb ON dk.id = lb.klien_id AND lb.pk_id = $pk_id
    LEFT JOIN jadwal_bimbingan jb ON lb.jadwal_bimbingan_id = jb.id
    WHERE dk.pk_id = $pk_id
    GROUP BY dk.id
    ORDER BY last_bimbingan DESC
")->fetch_all(MYSQLI_ASSOC);

// Total Stats
$total_klien = $conn->query("SELECT COUNT(*) as total FROM data_klien WHERE pk_id = $pk_id")->fetch_assoc()['total'];
$total_pengawasan = $conn->query("SELECT COUNT(*) as total FROM laporan_pengawasan WHERE pk_id = $pk_id")->fetch_assoc()['total'];
$total_bimbingan = $conn->query("SELECT COUNT(*) as total FROM laporan_bimbingan WHERE pk_id = $pk_id")->fetch_assoc()['total'];

// Klien Terikat
$klien_terikat = $conn->query("
    SELECT id, nama, no_registrasi, status_approval, created_at
    FROM klien_users
    WHERE pk_id = $pk_id
    ORDER BY created_at DESC
    LIMIT 10
");

// Recent Activity
$recent = $conn->query("
    SELECT 'pengawasan' as type, tanggal_laporan as tanggal, 
           (SELECT nama FROM data_klien WHERE id = laporan_pengawasan.klien_id) as klien_nama
    FROM laporan_pengawasan 
    WHERE pk_id = $pk_id 
    UNION ALL
    SELECT 'bimbingan' as type, tanggal_laporan as tanggal,
           (SELECT nama FROM data_klien WHERE id = laporan_bimbingan.klien_id) as klien_nama
    FROM laporan_bimbingan 
    WHERE pk_id = $pk_id 
    ORDER BY tanggal DESC 
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard PK - BAPAS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../shared/js/sweetalert-loader.js"></script>
    <script src="../shared/js/auto-notification-refresh.js"></script>
    <script>
        tailwind.config = {
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
                    },
                    animation: {
                        'blob': 'blob 7s infinite',
                        'fade-in-up': 'fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards',
                    },
                    keyframes: {
                        blob: {
                            '0%': { transform: 'translate(0px, 0px) scale(1)' },
                            '33%': { transform: 'translate(30px, -50px) scale(1.1)' },
                            '66%': { transform: 'translate(-20px, 20px) scale(0.9)' },
                            '100%': { transform: 'translate(0px, 0px) scale(1)' },
                        },
                        fadeInUp: {
                            '0%': { opacity: 0, transform: 'translateY(20px)' },
                            '100%': { opacity: 1, transform: 'translateY(0)' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        /* Custom Scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Glassmorphism Utilities */
        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-800 min-h-screen selection:bg-sky-500 selection:text-white">
    
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/topbar.php'; ?>

    <div class="p-4 sm:ml-64 mt-14">
        <div class="max-w-7xl mx-auto animate-fade-in-up">
            
            <!-- Welcome Banner (Sky Blue Gradient) -->
            <div class="glass-card rounded-3xl p-8 mb-8 relative overflow-hidden text-white shadow-xl group border-0">
                <!-- Background with gradient and shapes -->
                <div class="absolute inset-0 bg-gradient-to-r from-sky-600 to-blue-700 z-0"></div>
                <div class="absolute top-0 right-0 w-64 h-64 bg-sky-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob"></div>
                <div class="absolute -bottom-8 -left-8 w-64 h-64 bg-blue-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-2000"></div>
                <div class="absolute top-1/2 left-1/2 w-64 h-64 bg-cyan-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-4000"></div>
                
                <div class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-md border border-white/20 text-xs font-medium mb-3 shadow-lg">
                            <span class="w-2 h-2 rounded-full bg-sky-400 animate-pulse"></span>
                            Sistem Pengawasan Klien
                        </div>
                        <h1 class="text-3xl md:text-4xl font-bold mb-2 tracking-tight">Selamat Datang, <?php echo htmlspecialchars($nama); ?></h1>
                        <p class="text-slate-300 max-w-xl text-lg">Pantau perkembangan dan aktivitas klien Anda dalam satu dashboard terintegrasi.</p>
                    </div>
                    
                    <!-- Date/Time Box -->
                    <div class="bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl p-4 text-center min-w-[180px] shadow-lg transform transition-transform group-hover:scale-105 duration-300">
                        <div id="clock-time" class="text-3xl font-bold font-mono tracking-wider">00:00:00</div>
                        <div id="clock-date" class="text-xs text-slate-300 uppercase tracking-wider mt-1 font-semibold">...</div>
                    </div>
                </div>
            </div>

            <!-- Alerts Section -->
            <div class="space-y-4 mb-8">
                <!-- Pending Approvals Alert -->
                <div id="pending-approvals-alert" class="<?php echo $pending_count > 0 ? '' : 'hidden'; ?>">
                    <div class="glass-card border-l-4 border-l-yellow-500 p-5 rounded-2xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 hover:shadow-md transition-all duration-300">
                        <div class="flex items-center">
                            <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center text-yellow-600 mr-4 flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-lg text-slate-800">Menunggu Persetujuan</h3>
                                <p class="text-slate-500"><span id="pending-count-display" class="font-bold text-yellow-600"><?php echo $pending_count; ?></span> klien baru menunggu persetujuan Anda.</p>
                            </div>
                        </div>
                        <a href="approval.php" class="px-5 py-2.5 bg-yellow-500 hover:bg-yellow-600 text-white rounded-xl text-sm font-bold shadow-lg shadow-yellow-500/30 hover:shadow-yellow-500/40 hover:-translate-y-0.5 transition-all duration-300">
                            Lihat Detail
                        </a>
                    </div>
                </div>

                <!-- Biodata Lengkap Alert -->
                <?php if ($biodata_lengkap_count > 0): ?>
                <div class="glass-card border-l-4 border-l-blue-500 p-5 rounded-2xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 hover:shadow-md transition-all duration-300">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 mr-4 flex-shrink-0">
                            <i class="fas fa-file-signature text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg text-slate-800">Biodata Dilengkapi</h3>
                            <p class="text-slate-500"><span class="font-bold text-blue-600"><?php echo $biodata_lengkap_count; ?></span> klien telah melengkapi biodata dan menunggu verifikasi dokumen.</p>
                        </div>
                    </div>
                    <a href="lengkapi-dokumen.php" class="px-5 py-2.5 bg-blue-500 hover:bg-blue-600 text-white rounded-xl text-sm font-bold shadow-lg shadow-blue-500/30 hover:shadow-blue-500/40 hover:-translate-y-0.5 transition-all duration-300">
                        Verifikasi Dokumen
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <!-- Total Klien -->
                <div class="glass-card rounded-2xl p-6 hover:-translate-y-1 hover:shadow-xl transition-all duration-300 group">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-sky-100 rounded-xl text-sky-600 group-hover:scale-110 transition-transform duration-300">
                            <i class="fas fa-users text-2xl"></i>
                        </div>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-400 bg-slate-100 px-2 py-1 rounded-lg">Total</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-4xl font-bold text-slate-800 mb-1"><?php echo $total_klien; ?></span>
                        <span class="text-sm text-slate-500 font-medium">Klien Pemasyarakatan</span>
                    </div>
                </div>

                <!-- Total Laporan Pengawasan -->
                <div class="glass-card rounded-2xl p-6 hover:-translate-y-1 hover:shadow-xl transition-all duration-300 group">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-sky-100 rounded-xl text-sky-600 group-hover:scale-110 transition-transform duration-300">
                            <i class="fas fa-binoculars text-2xl"></i>
                        </div>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-400 bg-slate-100 px-2 py-1 rounded-lg">Laporan</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-4xl font-bold text-slate-800 mb-1"><?php echo $total_pengawasan; ?></span>
                        <span class="text-sm text-slate-500 font-medium">Laporan Pengawasan</span>
                    </div>
                </div>

                <!-- Total Bimbingan -->
                <div class="glass-card rounded-2xl p-6 hover:-translate-y-1 hover:shadow-xl transition-all duration-300 group">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-blue-100 rounded-xl text-blue-600 group-hover:scale-110 transition-transform duration-300">
                            <i class="fas fa-clipboard-list text-2xl"></i>
                        </div>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-400 bg-slate-100 px-2 py-1 rounded-lg">Laporan</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-4xl font-bold text-slate-800 mb-1"><?php echo $total_bimbingan; ?></span>
                        <span class="text-sm text-slate-500 font-medium">Laporan Bimbingan</span>
                    </div>
                </div>
            </div>

            <!-- Chart & Recent Activity -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <!-- Chart -->
                <div class="lg:col-span-2 glass-card rounded-2xl p-6 shadow-lg">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                            <i class="fas fa-chart-line text-sky-500"></i>
                            Aktivitas Bimbingan
                        </h2>
                        <select class="text-sm border-none bg-slate-100 rounded-lg px-3 py-1 focus:ring-2 focus:ring-sky-500">
                            <option>Tahun Ini</option>
                        </select>
                    </div>
                    <div class="relative h-72">
                        <canvas id="bimbinganChart"></canvas>
                    </div>
                </div>

                <!-- Recent Activity Feed -->
                <div class="glass-card rounded-2xl p-6 shadow-lg flex flex-col">
                    <h2 class="text-xl font-bold text-slate-800 mb-6 flex items-center gap-2">
                        <i class="fas fa-history text-sky-500"></i>
                        Aktivitas Terbaru
                    </h2>
                    
                    <div class="space-y-6 overflow-y-auto custom-scrollbar pr-2 flex-grow max-h-[300px]">
                        <?php if ($recent->num_rows > 0): ?>
                            <?php while($row = $recent->fetch_assoc()): ?>
                                <div class="flex gap-4 group">
                                    <div class="flex flex-col items-center">
                                        <div class="w-2 h-2 rounded-full bg-slate-300 group-hover:bg-sky-500 transition-colors duration-300"></div>
                                        <div class="w-0.5 h-full bg-slate-100 -mb-6"></div>
                                    </div>
                                    <div class="pb-2">
                                        <p class="text-sm font-medium text-slate-800">
                                            Laporan <?php echo ucfirst($row['type']); ?>
                                        </p>
                                        <p class="text-xs text-slate-500 mt-0.5">
                                            Klien: <span class="font-semibold text-sky-600"><?php echo htmlspecialchars($row['klien_nama']); ?></span>
                                        </p>
                                        <span class="text-[10px] text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full mt-2 inline-block">
                                            <?php echo date('d M Y, H:i', strtotime($row['tanggal'])); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-8 text-slate-400">
                                <i class="fas fa-inbox text-3xl mb-2"></i>
                                <p class="text-sm">Belum ada aktivitas</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Client List Table -->
            <div class="glass-card rounded-2xl p-6 shadow-lg mb-8">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-6">
                    <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                        <i class="fas fa-user-check text-sky-500"></i>
                        Daftar Klien Aktif
                    </h2>
                    <a href="data-klien.php" class="text-sm font-semibold text-sky-600 hover:text-sky-700 hover:underline">
                        Lihat Semua Klien <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                
                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full text-left text-sm text-slate-600">
                        <thead class="bg-slate-50 text-xs uppercase font-bold text-slate-500">
                            <tr>
                                <th class="px-6 py-4">Nama Klien</th>
                                <th class="px-6 py-4">No. Berkas</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Terdaftar</th>
                                <th class="px-6 py-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white/50">
                            <?php if ($klien_terikat->num_rows > 0): ?>
                                <?php while($klien = $klien_terikat->fetch_assoc()): ?>
                                <tr class="hover:bg-sky-50 transition-colors duration-200">
                                    <td class="px-6 py-4 font-semibold text-slate-800">
                                        <?php echo htmlspecialchars($klien['nama']); ?>
                                    </td>
                                    <td class="px-6 py-4 font-mono text-slate-500">
                                        <?php echo htmlspecialchars($klien['no_registrasi']); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium 
                                            <?php echo $klien['status_approval'] === 'approved' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?>">
                                            <span class="w-1.5 h-1.5 rounded-full <?php echo $klien['status_approval'] === 'approved' ? 'bg-green-500' : 'bg-yellow-500'; ?>"></span>
                                            <?php echo ucfirst($klien['status_approval']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-slate-500">
                                        <?php echo date('d M Y', strtotime($klien['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <a href="data-klien.php?search=<?php echo urlencode($klien['no_registrasi']); ?>" 
                                           class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-white border border-slate-200 hover:bg-sky-50 hover:border-sky-200 text-slate-500 hover:text-sky-600 transition-all duration-200 shadow-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-slate-400 italic">
                                        Belum ada data klien
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Clock Script
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }).replace(/\./g, ':');
            const dateString = now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
            
            document.getElementById('clock-time').textContent = timeString;
            document.getElementById('clock-date').textContent = dateString;
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Chart.js Configuration
        const ctx = document.getElementById('bimbinganChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Jumlah Klien Bimbingan',
                    data: <?php echo json_encode($chart_data); ?>,
                    borderColor: '#0ea5e9', // Sky 500
                    backgroundColor: 'rgba(14, 165, 233, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#0284c7', // Sky 600
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleColor: '#f8fafc',
                        bodyColor: '#e2e8f0',
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: false,
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(148, 163, 184, 0.1)',
                            borderDash: [5, 5]
                        },
                        ticks: {
                            color: '#64748b',
                            font: { family: "'Plus Jakarta Sans', sans-serif" }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#64748b',
                            font: { family: "'Plus Jakarta Sans', sans-serif" }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
