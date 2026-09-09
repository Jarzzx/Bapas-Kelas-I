<?php
require_once '../shared/config/database.php';
require_once '../shared/config/auth.php';
requirePKLogin();

$conn = getDBConnection();
$pk_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Handle verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['laporan_id'])) {
    $laporan_id = (int)$_POST['laporan_id'];
    $action = $_POST['action'] ?? ''; // 'approve' or 'reject'
    $catatan = trim($_POST['catatan'] ?? '');
    
    if ($action === 'approve' || $action === 'reject') {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        
        $verified_at = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE laporan_bimbingan SET 
            status_verifikasi = ?,
            verified_by = ?,
            verified_at = ?
            WHERE id = ? AND pk_id = ?");
        
        $stmt->bind_param("sisii", $status, $pk_id, $verified_at, $laporan_id, $pk_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = $action === 'approve' ? 'Laporan berhasil disetujui!' : 'Laporan ditolak.';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $message = 'Gagal memverifikasi laporan: ' . $conn->error;
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// Check for session message
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    $message_type = 'success';
    unset($_SESSION['success_message']);
}

// Get pending reports (lb.klien_id refers to data_klien.id, not klien_users.id)
$laporan_pending = $conn->query("
    SELECT lb.*, jb.judul_bimbingan, jb.materi_bimbingan, jb.tanggal_bimbingan as jadwal_tanggal,
           jb.lokasi_bimbingan as jadwal_lokasi, jb.latitude as jadwal_lat, jb.longitude as jadwal_lng,
           dk.nama as klien_nama, dk.no_registrasi
    FROM laporan_bimbingan lb
    JOIN jadwal_bimbingan jb ON lb.jadwal_bimbingan_id = jb.id
    JOIN data_klien dk ON lb.klien_id = dk.id
    WHERE lb.pk_id = $pk_id
    AND lb.status_verifikasi = 'pending'
    AND lb.dibuat_oleh = 'klien'
    ORDER BY lb.tanggal_laporan DESC
")->fetch_all(MYSQLI_ASSOC);

// Get all reports (approved/rejected) - lb.klien_id refers to data_klien.id
$laporan_all = $conn->query("
    SELECT lb.*, jb.judul_bimbingan, jb.materi_bimbingan, jb.tanggal_bimbingan as jadwal_tanggal,
           dk.nama as klien_nama, dk.no_registrasi
    FROM laporan_bimbingan lb
    JOIN jadwal_bimbingan jb ON lb.jadwal_bimbingan_id = jb.id
    JOIN data_klien dk ON lb.klien_id = dk.id
    WHERE lb.pk_id = $pk_id
    AND lb.dibuat_oleh = 'klien'
    AND lb.status_verifikasi != 'pending'
    ORDER BY lb.verified_at DESC
    LIMIT 20
")->fetch_all(MYSQLI_ASSOC);

// $conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Laporan Bimbingan - BAPAS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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
        .map-container {
            height: 250px;
            width: 100%;
            border-radius: 1rem;
            z-index: 0;
            border: 2px solid #e2e8f0;
        }
        
        /* Glassmorphism & Custom Scrollbar */
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

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
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-800 min-h-screen selection:bg-sky-100 selection:text-sky-700">
    
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/topbar.php'; ?>

    <div class="p-4 sm:ml-64 mt-20">
        <div class="max-w-7xl mx-auto fade-in">
            
            <!-- Header Banner -->
            <div class="rounded-3xl p-8 mb-8 relative overflow-hidden text-white shadow-xl group">
                <!-- Background with gradient and shapes -->
                <div class="absolute inset-0 bg-gradient-to-r from-sky-600 to-blue-700 z-0"></div>
                <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -mr-16 -mt-16 blur-3xl transform group-hover:scale-110 transition-transform duration-700"></div>
                <div class="absolute bottom-0 left-0 w-40 h-40 bg-sky-400/20 rounded-full -ml-10 -mb-10 blur-2xl"></div>
                
                <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-6">
                    <div>
                        <div class="inline-flex items-center space-x-2 bg-white/20 backdrop-blur-md px-4 py-1.5 rounded-full text-xs font-semibold border border-white/20 mb-3 shadow-sm">
                            <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                            <span>Verifikasi Data</span>
                        </div>
                        <h1 class="text-3xl font-bold mb-2 tracking-tight">Verifikasi Laporan</h1>
                        <p class="text-sky-100 text-sm max-w-xl leading-relaxed">Tinjau dan verifikasi laporan bimbingan dari klien. Pastikan data lokasi dan foto sesuai.</p>
                    </div>
                    <div class="hidden md:block">
                        <div class="w-16 h-16 bg-white/20 backdrop-blur-md rounded-2xl flex items-center justify-center border border-white/20 shadow-lg transform rotate-3 group-hover:rotate-6 transition-transform duration-500">
                            <i class="fas fa-clipboard-check text-3xl text-white"></i>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="mb-8 p-5 rounded-2xl shadow-sm border-l-4 flex items-center <?php echo $message_type === 'success' ? 'bg-green-50 text-green-700 border-green-500' : 'bg-red-50 text-red-700 border-red-500'; ?> fade-in-up">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center mr-5 shadow-sm <?php echo $message_type === 'success' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?>">
                        <i class="fas <?php echo $message_type === 'success' ? 'fa-check text-xl' : 'fa-exclamation-triangle text-xl'; ?>"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-base <?php echo $message_type === 'success' ? 'text-green-800' : 'text-red-800'; ?>">
                            <?php echo $message_type === 'success' ? 'Berhasil!' : 'Terjadi Kesalahan'; ?>
                        </h4>
                        <p class="text-sm opacity-90 mt-1"><?php echo htmlspecialchars($message); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($laporan_pending)): ?>
                <div class="text-center py-20 glass-card rounded-3xl border border-dashed border-gray-300 fade-in-up">
                    <div class="w-24 h-24 bg-sky-50 rounded-full flex items-center justify-center mx-auto mb-6 text-sky-500 shadow-inner">
                        <i class="fas fa-check-double text-4xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">Semua Bersih!</h3>
                    <p class="text-gray-500 text-sm max-w-md mx-auto">Tidak ada laporan yang menunggu verifikasi saat ini. Anda telah menyelesaikan semua tugas pending.</p>
                </div>
            <?php else: ?>
                <div class="space-y-8 fade-in-up" style="animation-delay: 0.1s;">
                    <?php foreach ($laporan_pending as $laporan): ?>
                        <div class="glass-card rounded-3xl shadow-xl overflow-hidden group hover:shadow-2xl transition-all duration-300 relative">
                            <!-- Status Indicator Strip -->
                            <div class="absolute top-0 left-0 w-2 h-full bg-amber-400"></div>
                            
                            <div class="p-8">
                                <div class="flex flex-col lg:flex-row gap-10">
                                    <!-- Left Column: Info -->
                                    <div class="flex-1 space-y-8">
                                        <div class="flex items-start justify-between">
                                            <div>
                                                <div class="flex items-center space-x-2 text-xs font-bold text-amber-600 mb-3 uppercase tracking-wider">
                                                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                                                    <span>Menunggu Verifikasi</span>
                                                </div>
                                                <h3 class="text-2xl font-bold text-gray-800 mb-2 leading-tight"><?php echo htmlspecialchars($laporan['judul_bimbingan']); ?></h3>
                                                <p class="text-gray-500 text-sm flex items-center">
                                                    <i class="far fa-user mr-2 text-sky-500"></i>
                                                    <span class="font-semibold"><?php echo htmlspecialchars($laporan['klien_nama']); ?></span>
                                                    <span class="mx-3 text-gray-300">|</span>
                                                    <span class="font-mono bg-gray-100 px-2 py-1 rounded-lg text-xs font-bold text-gray-600"><?php echo htmlspecialchars($laporan['no_registrasi']); ?></span>
                                                </p>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-5">
                                            <div class="bg-sky-50/50 rounded-2xl p-5 border border-sky-100 hover:bg-sky-50 transition-colors">
                                                <p class="text-xs text-sky-600 font-bold uppercase tracking-wider mb-2 flex items-center">
                                                    <i class="far fa-calendar-alt mr-1.5"></i> Jadwal
                                                </p>
                                                <p class="font-bold text-gray-800 text-lg"><?php echo date('d M Y', strtotime($laporan['jadwal_tanggal'])); ?></p>
                                                <p class="text-xs text-gray-500 mt-1 font-medium"><?php echo date('H:i', strtotime($laporan['jadwal_tanggal'])); ?> WIB</p>
                                            </div>
                                            <div class="bg-sky-50/50 rounded-2xl p-5 border border-sky-100 hover:bg-sky-50 transition-colors">
                                                <p class="text-xs text-sky-600 font-bold uppercase tracking-wider mb-2 flex items-center">
                                                    <i class="far fa-clock mr-1.5"></i> Dilaporkan
                                                </p>
                                                <p class="font-bold text-gray-800 text-lg"><?php echo date('d M Y', strtotime($laporan['tanggal_laporan'])); ?></p>
                                                <p class="text-xs text-gray-500 mt-1 font-medium"><?php echo date('H:i', strtotime($laporan['tanggal_laporan'])); ?> WIB</p>
                                            </div>
                                        </div>

                                        <?php if ($laporan['keluhan']): ?>
                                            <div class="bg-gray-50 rounded-2xl p-6 border border-gray-100 relative">
                                                <i class="fas fa-quote-left absolute top-4 left-4 text-gray-200 text-3xl -z-10"></i>
                                                <p class="text-xs text-gray-500 font-bold uppercase tracking-wider mb-2 flex items-center">
                                                    <i class="far fa-comment-alt mr-1.5"></i> Keluhan / Catatan Klien
                                                </p>
                                                <p class="text-gray-700 text-sm leading-relaxed italic relative z-10">"<?php echo nl2br(htmlspecialchars($laporan['keluhan'])); ?>"</p>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($laporan['foto_bimbingan']): ?>
                                            <div>
                                                <p class="text-xs text-gray-500 font-bold uppercase tracking-wider mb-3">Bukti Foto</p>
                                                <div class="relative group/img overflow-hidden rounded-2xl border-4 border-white shadow-lg w-full max-w-sm cursor-pointer">
                                                    <img src="../<?php echo htmlspecialchars($laporan['foto_bimbingan']); ?>" 
                                                         alt="Foto Bimbingan" 
                                                         class="w-full h-56 object-cover transform group-hover/img:scale-110 transition-transform duration-700">
                                                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover/img:opacity-100 transition-opacity duration-300 flex items-end p-6">
                                                        <span class="text-white text-sm font-bold flex items-center"><i class="fas fa-camera mr-2"></i> Lihat Ukuran Penuh</span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Right Column: Maps & Actions -->
                                    <div class="flex-1 flex flex-col">
                                        <div class="bg-white rounded-2xl p-1.5 border border-gray-100 shadow-sm mb-6 flex-grow flex flex-col">
                                            <div class="grid grid-cols-2 gap-1.5 flex-grow">
                                                <div class="flex flex-col h-full relative group/map1">
                                                    <div class="absolute top-3 left-1/2 transform -translate-x-1/2 z-10 px-3 py-1 text-[10px] font-bold text-gray-600 uppercase tracking-wider bg-white/90 backdrop-blur-sm rounded-full shadow-sm border border-gray-100">Lokasi Jadwal (PK)</div>
                                                    <div id="map-jadwal-<?php echo $laporan['id']; ?>" class="map-container flex-grow !h-full min-h-[250px] rounded-xl"></div>
                                                </div>
                                                <div class="flex flex-col h-full relative group/map2">
                                                    <div class="absolute top-3 left-1/2 transform -translate-x-1/2 z-10 px-3 py-1 text-[10px] font-bold text-gray-600 uppercase tracking-wider bg-white/90 backdrop-blur-sm rounded-full shadow-sm border border-gray-100">Lokasi Laporan (Klien)</div>
                                                    <div id="map-laporan-<?php echo $laporan['id']; ?>" class="map-container flex-grow !h-full min-h-[250px] rounded-xl"></div>
                                                </div>
                                            </div>
                                            <div class="px-4 py-3 text-center bg-gray-50/50 rounded-b-xl mt-1.5">
                                                <p class="text-xs text-gray-500 font-medium flex items-center justify-center">
                                                    <i class="fas fa-map-marked-alt mr-2 text-sky-500"></i> Bandingkan kedua lokasi untuk memverifikasi kehadiran.
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <form method="POST" class="mt-auto bg-gray-50/50 rounded-2xl p-6 border border-gray-100">
                                            <input type="hidden" name="laporan_id" value="<?php echo $laporan['id']; ?>">
                                            <div class="mb-5 group/input">
                                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2 group-focus-within:text-sky-600 transition-colors">Catatan Verifikator</label>
                                                <textarea name="catatan" rows="2"
                                                          class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 transition-all duration-300 text-sm placeholder-gray-400 bg-white shadow-sm resize-none"
                                                          placeholder="Tambahkan catatan untuk klien..."></textarea>
                                            </div>
                                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                                <a href="edit-laporan-bimbingan.php?id=<?php echo $laporan['id']; ?>" 
                                                   class="inline-flex justify-center items-center px-4 py-3.5 rounded-xl border border-gray-200 bg-white text-gray-700 font-bold text-sm hover:bg-gray-50 hover:border-gray-300 hover:text-gray-900 transition-all duration-300 shadow-sm">
                                                    <i class="fas fa-edit mr-2"></i> Edit
                                                </a>
                                                <button type="submit" name="action" value="reject" 
                                                        class="inline-flex justify-center items-center px-4 py-3.5 rounded-xl bg-red-50 text-red-600 font-bold text-sm hover:bg-red-100 border border-transparent hover:border-red-200 transition-all duration-300">
                                                    <i class="fas fa-times mr-2"></i> Tolak
                                                </button>
                                                <button type="submit" name="action" value="approve" 
                                                        class="inline-flex justify-center items-center px-4 py-3.5 rounded-xl bg-gradient-to-r from-sky-600 to-blue-600 text-white font-bold text-sm hover:from-sky-700 hover:to-blue-700 shadow-lg shadow-sky-500/30 transition-all duration-300 transform hover:-translate-y-0.5">
                                                    <i class="fas fa-check mr-2"></i> Setujui
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    // Init Jadwal Map
                                    var mapJadwal = L.map('map-jadwal-<?php echo $laporan['id']; ?>', {
                                        center: [<?php echo $laporan['jadwal_lat'] ?? -6.2088; ?>, <?php echo $laporan['jadwal_lng'] ?? 106.8456; ?>],
                                        zoom: 15,
                                        zoomControl: false,
                                        attributionControl: false
                                    });
                                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(mapJadwal);
                                    L.marker([<?php echo $laporan['jadwal_lat'] ?? -6.2088; ?>, <?php echo $laporan['jadwal_lng'] ?? 106.8456; ?>]).addTo(mapJadwal)
                                     .bindPopup("Lokasi Jadwal");
                                    
                                    // Init Laporan Map
                                    var mapLaporan = L.map('map-laporan-<?php echo $laporan['id']; ?>', {
                                        center: [<?php echo $laporan['latitude'] ?? -6.2088; ?>, <?php echo $laporan['longitude'] ?? 106.8456; ?>],
                                        zoom: 15,
                                        zoomControl: false,
                                        attributionControl: false
                                    });
                                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(mapLaporan);
                                    L.marker([<?php echo $laporan['latitude'] ?? -6.2088; ?>, <?php echo $laporan['longitude'] ?? 106.8456; ?>]).addTo(mapLaporan)
                                     .bindPopup("Lokasi Laporan");

                                    // Fix map rendering issue in hidden tabs/modals
                                    setTimeout(function(){ 
                                        mapJadwal.invalidateSize(); 
                                        mapLaporan.invalidateSize();
                                    }, 500);
                                });
                            </script>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>

</body>
</html>
