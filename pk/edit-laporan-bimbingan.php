<?php
require_once '../shared/config/database.php';
require_once '../shared/config/auth.php';
requirePKLogin();

$conn = getDBConnection();
$pk_id = $_SESSION['user_id'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$message_type = '';

// Get existing report
$stmt = $conn->prepare("
    SELECT lb.*, dk.nama as klien_nama, dk.no_registrasi,
           COALESCE(jb.materi_bimbingan, lb.materi_bimbingan) as materi_display
    FROM laporan_bimbingan lb
    JOIN data_klien dk ON lb.klien_id = dk.id
    LEFT JOIN jadwal_bimbingan jb ON lb.jadwal_bimbingan_id = jb.id
    WHERE lb.id = ? AND lb.pk_id = ?
");
$stmt->bind_param("ii", $id, $pk_id);
$stmt->execute();
$result = $stmt->get_result();
$laporan = $result->fetch_assoc();
$stmt->close();

if (!$laporan) {
    die("Laporan tidak ditemukan atau Anda tidak memiliki akses.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hasil_bimbingan = $_POST['hasil_bimbingan'] ?? '';
    $tindak_lanjut = $_POST['tindak_lanjut'] ?? '';
    $isi_laporan = $_POST['isi_laporan'] ?? '';
    $lokasi = $_POST['lokasi'] ?? '';
    $latitude = !empty($_POST['latitude']) ? $_POST['latitude'] : null;
    $longitude = !empty($_POST['longitude']) ? $_POST['longitude'] : null;
    $status_verifikasi = $_POST['status_verifikasi'] ?? $laporan['status_verifikasi'];
    
    // Update laporan
    $stmt = $conn->prepare("UPDATE laporan_bimbingan SET 
        hasil_bimbingan = ?, 
        tindak_lanjut = ?, 
        isi_laporan = ?,
        status_verifikasi = ?,
        lokasi = ?,
        latitude = ?,
        longitude = ?
        WHERE id = ? AND pk_id = ?");
        
    $stmt->bind_param("sssssddii", $hasil_bimbingan, $tindak_lanjut, $isi_laporan, $status_verifikasi, $lokasi, $latitude, $longitude, $id, $pk_id);
    
    if ($stmt->execute()) {
        $message = 'Laporan berhasil diperbarui!';
        $message_type = 'success';
        // Refresh data
        $laporan['hasil_bimbingan'] = $hasil_bimbingan;
        $laporan['tindak_lanjut'] = $tindak_lanjut;
        $laporan['isi_laporan'] = $isi_laporan;
        $laporan['status_verifikasi'] = $status_verifikasi;
        $laporan['lokasi'] = $lokasi;
        $laporan['latitude'] = $latitude;
        $laporan['longitude'] = $longitude;
    } else {
        $message = 'Gagal memperbarui laporan: ' . $conn->error;
        $message_type = 'error';
    }
    $stmt->close();
}

// $conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Laporan Bimbingan - BAPAS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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
        /* Glassmorphism */
        .glass-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
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
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-800 min-h-screen selection:bg-sky-100 selection:text-sky-700">
    
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/topbar.php'; ?>

    <div class="p-4 sm:ml-64 mt-20">
        <div class="max-w-4xl mx-auto fade-in">
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
                            <span>Edit Laporan</span>
                        </div>
                        <h1 class="text-3xl font-bold mb-2 tracking-tight">Edit Laporan Bimbingan</h1>
                        <p class="text-sky-100 text-sm max-w-xl leading-relaxed">Perbarui data laporan bimbingan klien pemasyarakatan.</p>
                    </div>
                    <div class="hidden md:block">
                        <div class="w-16 h-16 bg-white/20 backdrop-blur-md rounded-2xl flex items-center justify-center border border-white/20 shadow-lg transform rotate-3 group-hover:rotate-6 transition-transform duration-500">
                            <i class="fas fa-edit text-3xl text-white"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-8 flex items-center justify-between">
                <a href="laporan-bimbingan.php" class="group flex items-center text-slate-500 hover:text-sky-600 transition-all duration-300">
                    <div class="w-10 h-10 rounded-full bg-white border border-slate-200 flex items-center justify-center mr-3 group-hover:border-sky-200 group-hover:bg-sky-50 group-hover:shadow-md transition-all duration-300 shadow-sm">
                        <i class="fas fa-arrow-left text-sm group-hover:-translate-x-1 transition-transform duration-300"></i>
                    </div>
                    <div>
                        <span class="block text-xs text-slate-400 font-medium mb-0.5">Kembali ke</span>
                        <span class="font-bold text-sm">Daftar Laporan</span>
                    </div>
                </a>
                <div class="flex items-center gap-4">
                    <div class="hidden md:block text-right">
                        <div id="current-time" class="text-lg font-bold text-slate-700 leading-none"></div>
                        <div id="current-date" class="text-xs text-slate-500 font-medium uppercase tracking-wider"></div>
                    </div>
                    <div class="text-xs font-bold text-slate-400 uppercase tracking-wider bg-white px-3 py-1.5 rounded-lg border border-slate-200 shadow-sm">
                        ID: #<?php echo $id; ?>
                    </div>
                </div>
            </div>

        <?php if ($message): ?>
            <div class="mb-8 p-4 rounded-2xl shadow-lg shadow-gray-100 border-l-4 flex items-center <?php echo $message_type === 'success' ? 'bg-white border-green-500 text-green-700' : 'bg-white border-red-500 text-red-700'; ?> fade-in-up">
                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-4 shadow-sm <?php echo $message_type === 'success' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?>">
                    <i class="fas <?php echo $message_type === 'success' ? 'fa-check' : 'fa-exclamation-triangle'; ?>"></i>
                </div>
                <div>
                    <h4 class="font-bold text-sm <?php echo $message_type === 'success' ? 'text-green-800' : 'text-red-800'; ?>">
                        <?php echo $message_type === 'success' ? 'Berhasil!' : 'Terjadi Kesalahan'; ?>
                    </h4>
                    <p class="text-sm opacity-90"><?php echo htmlspecialchars($message); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <div class="glass-card rounded-3xl shadow-xl overflow-hidden fade-in-up" style="animation-delay: 0.1s;">
            <!-- Header -->
            <div class="bg-gradient-to-r from-sky-600 to-blue-700 px-8 py-10 text-white relative overflow-hidden">
                <div class="absolute top-0 right-0 w-96 h-96 bg-white/10 rounded-full -mr-20 -mt-20 blur-3xl"></div>
                <div class="absolute bottom-0 left-0 w-60 h-60 bg-sky-400/20 rounded-full -ml-10 -mb-10 blur-2xl"></div>
                
                <div class="relative z-10 flex items-start justify-between">
                    <div>
                        <div class="inline-flex items-center space-x-2 bg-white/20 backdrop-blur-md px-3 py-1 rounded-full text-xs font-medium text-white border border-white/20 mb-4 shadow-sm">
                            <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                            <span>Mode Edit</span>
                        </div>
                        <h2 class="text-3xl font-bold mb-2 tracking-tight">Edit Laporan Bimbingan</h2>
                        <p class="text-sky-100 text-sm max-w-lg leading-relaxed font-medium">Perbarui data hasil bimbingan, tindak lanjut, dan status verifikasi laporan klien secara detail.</p>
                    </div>
                    <div class="hidden md:block p-4 bg-white/10 rounded-2xl border border-white/20 backdrop-blur-sm shadow-inner">
                        <i class="fas fa-edit text-4xl text-white"></i>
                    </div>
                </div>
            </div>
            
            <div class="p-8 lg:p-10">
                <!-- Info Card -->
                <div class="bg-slate-50/50 rounded-2xl p-6 mb-10 border border-slate-100">
                    <div class="flex items-center space-x-2 mb-6">
                        <i class="fas fa-info-circle text-sky-600"></i>
                        <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wide">Informasi Dasar</h3>
                    </div>
                    <div class="grid md:grid-cols-2 gap-8">
                        <!-- Client Info -->
                        <div class="flex items-start space-x-4">
                            <div class="w-12 h-12 rounded-2xl bg-white border border-slate-200 flex items-center justify-center flex-shrink-0 text-sky-600 shadow-sm">
                                <span class="text-lg font-bold"><?php echo substr($laporan['klien_nama'], 0, 1); ?></span>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500 font-semibold uppercase tracking-wider mb-1">Klien Pemasyarakatan</p>
                                <p class="font-bold text-slate-800 text-lg mb-1"><?php echo htmlspecialchars($laporan['klien_nama']); ?></p>
                                <div class="inline-flex items-center px-2 py-0.5 rounded bg-white border border-slate-200 text-xs text-slate-500 font-medium">
                                    <i class="fas fa-id-card mr-1.5 text-slate-400"></i>
                                    <?php echo htmlspecialchars($laporan['no_registrasi']); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Date Info -->
                        <div class="flex items-start space-x-4">
                            <div class="w-12 h-12 rounded-2xl bg-white border border-slate-200 flex items-center justify-center flex-shrink-0 text-sky-600 shadow-sm">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500 font-semibold uppercase tracking-wider mb-1">Tanggal Bimbingan</p>
                                <p class="font-bold text-slate-800 text-lg mb-1"><?php echo date('d F Y', strtotime($laporan['tanggal_bimbingan'] ?? $laporan['tanggal_laporan'])); ?></p>
                                <div class="inline-flex items-center px-2 py-0.5 rounded bg-white border border-slate-200 text-xs text-slate-500 font-medium">
                                    <i class="fas fa-clock mr-1.5 text-slate-400"></i>
                                    <?php echo date('H:i', strtotime($laporan['created_at'] ?? 'now')); ?> WIB
                                </div>
                            </div>
                        </div>

                        <!-- Material Info -->
                        <div class="md:col-span-2 bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
                            <div class="flex items-start space-x-4">
                                <div class="mt-1 w-8 h-8 rounded-lg bg-sky-50 flex items-center justify-center flex-shrink-0 text-sky-600">
                                    <i class="fas fa-book-open text-xs"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-xs text-slate-500 font-semibold uppercase tracking-wider mb-1">Materi Bimbingan</p>
                                    <p class="font-medium text-slate-700 leading-relaxed"><?php echo htmlspecialchars($laporan['materi_display']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <form method="POST" action="" class="space-y-8">
                    <div class="grid md:grid-cols-2 gap-8">
                        <div class="group md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-3 group-focus-within:text-sky-600 transition-colors">
                                <div class="flex items-center">
                                    <span class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center mr-2 border border-sky-100 group-focus-within:bg-sky-600 group-focus-within:text-white transition-all duration-300">
                                        <i class="fas fa-comment-medical text-xs"></i>
                                    </span>
                                    Saran Pembimbing
                                </div>
                            </label>
                            <div class="relative">
                                <textarea name="hasil_bimbingan" rows="4"
                                          class="w-full pl-5 pr-5 py-4 border border-slate-200 rounded-xl focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 transition-all duration-300 resize-none bg-slate-50/50 focus:bg-white text-slate-700 leading-relaxed placeholder-slate-400 shadow-sm group-hover:border-slate-300"
                                          placeholder="Tuliskan saran konstruktif untuk klien..."><?php echo htmlspecialchars($laporan['hasil_bimbingan'] ?? ''); ?></textarea>
                                <div class="absolute bottom-3 right-3 text-xs text-slate-400 pointer-events-none bg-white/80 px-2 py-1 rounded backdrop-blur-sm">
                                    Hasil Bimbingan
                                </div>
                            </div>
                        </div>
                        
                        <div class="group md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-3 group-focus-within:text-blue-600 transition-colors">
                                <div class="flex items-center">
                                    <span class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center mr-2 border border-blue-100 group-focus-within:bg-blue-600 group-focus-within:text-white transition-all duration-300">
                                        <i class="fas fa-tasks text-xs"></i>
                                    </span>
                                    Tindak Lanjut
                                </div>
                            </label>
                            <div class="relative">
                                <textarea name="tindak_lanjut" rows="3"
                                          class="w-full pl-5 pr-5 py-4 border border-slate-200 rounded-xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all duration-300 resize-none bg-slate-50/50 focus:bg-white text-slate-700 leading-relaxed placeholder-slate-400 shadow-sm group-hover:border-slate-300"
                                          placeholder="Rencana tindak lanjut kedepan..."><?php echo htmlspecialchars($laporan['tindak_lanjut'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="group md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-3 group-focus-within:text-orange-600 transition-colors">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <span class="w-8 h-8 rounded-lg bg-orange-50 text-orange-600 flex items-center justify-center mr-2 border border-orange-100 group-focus-within:bg-orange-600 group-focus-within:text-white transition-all duration-300">
                                            <i class="fas fa-map-marker-alt text-xs"></i>
                                        </span>
                                        Lokasi Bimbingan (Opsional)
                                    </div>
                                    <div class="flex items-center bg-white px-3 py-1.5 rounded-lg border border-slate-200 shadow-sm">
                                        <input type="checkbox" id="use_map" class="w-4 h-4 text-orange-600 bg-gray-100 border-gray-300 rounded focus:ring-orange-500 focus:ring-2 cursor-pointer" onchange="toggleMap()" <?php echo (!empty($laporan['latitude']) ? 'checked' : ''); ?>>
                                        <label for="use_map" class="ml-2 text-xs font-bold text-gray-700 cursor-pointer select-none">Gunakan Pinpoint Map</label>
                                    </div>
                                </div>
                            </label>
                            <div class="relative space-y-4">
                                <input type="text" name="lokasi" id="lokasi_input"
                                       class="w-full pl-5 pr-5 py-4 border border-slate-200 rounded-xl focus:ring-4 focus:ring-orange-500/10 focus:border-orange-500 transition-all duration-300 bg-slate-50/50 focus:bg-white text-slate-700 leading-relaxed placeholder-slate-400 shadow-sm group-hover:border-slate-300"
                                       placeholder="Lokasi pertemuan (misal: Ruang Layanan BAPAS)..."
                                       value="<?php echo htmlspecialchars($laporan['lokasi'] ?? ''); ?>">
                                
                                <div id="map-container" class="<?php echo (!empty($laporan['latitude']) ? '' : 'hidden'); ?> transition-all duration-300 ease-in-out">
                                    <div class="relative w-full h-80 rounded-xl overflow-hidden shadow-sm border border-slate-200">
                                        <div id="map" class="absolute inset-0 z-0"></div>
                                        <button type="button" onclick="getCurrentLocation()" class="absolute top-3 right-3 z-[1000] bg-white px-3 py-2 rounded-lg shadow-md border border-slate-200 text-sky-600 font-bold text-xs hover:bg-sky-50 transition-all flex items-center group/btn">
                                            <i class="fas fa-location-arrow mr-2 group-hover/btn:animate-bounce"></i> Lokasi Saya
                                        </button>
                                    </div>
                                    <p class="mt-2 text-xs text-slate-500 italic flex items-center">
                                        <i class="fas fa-info-circle mr-1 text-orange-500"></i>
                                        Klik pada peta untuk menandai lokasi yang tepat.
                                    </p>
                                </div>

                                <input type="hidden" name="latitude" id="latitude" value="<?php echo htmlspecialchars($laporan['latitude'] ?? ''); ?>">
                                <input type="hidden" name="longitude" id="longitude" value="<?php echo htmlspecialchars($laporan['longitude'] ?? ''); ?>">

                                <p class="mt-2 text-xs text-slate-500 italic">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Isi jika pelaksanaan bimbingan dilakukan di lokasi tertentu (meskipun status daring).
                                </p>
                            </div>
                        </div>
                        
                        <div class="group md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-3 group-focus-within:text-sky-600 transition-colors">
                                <div class="flex items-center">
                                    <span class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center mr-2 border border-sky-100 group-focus-within:bg-sky-600 group-focus-within:text-white transition-all duration-300">
                                        <i class="fas fa-file-alt text-xs"></i>
                                    </span>
                                    Isi Laporan Lengkap
                                </div>
                            </label>
                            <div class="relative">
                                <textarea name="isi_laporan" rows="8"
                                          class="w-full pl-5 pr-5 py-4 border border-slate-200 rounded-xl focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 transition-all duration-300 resize-none bg-slate-50/50 focus:bg-white text-slate-700 leading-relaxed placeholder-slate-400 shadow-sm group-hover:border-slate-300"
                                          placeholder="Deskripsikan jalannya bimbingan secara detail..."><?php echo htmlspecialchars($laporan['isi_laporan'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="group md:col-span-1">
                            <label class="block text-sm font-bold text-slate-700 mb-3 group-focus-within:text-sky-600 transition-colors">
                                <div class="flex items-center">
                                    <span class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center mr-2 border border-sky-100 group-focus-within:bg-sky-600 group-focus-within:text-white transition-all duration-300">
                                        <i class="fas fa-clipboard-check text-xs"></i>
                                    </span>
                                    Status Verifikasi
                                </div>
                            </label>
                            <div class="relative">
                                <select name="status_verifikasi" class="w-full pl-5 pr-10 py-4 border border-slate-200 rounded-xl focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 transition-all duration-300 appearance-none bg-slate-50/50 focus:bg-white cursor-pointer font-medium text-slate-700 shadow-sm group-hover:border-slate-300">
                                    <option value="pending" <?php echo $laporan['status_verifikasi'] === 'pending' ? 'selected' : ''; ?>>⏳ Menunggu Verifikasi</option>
                                    <option value="approved" <?php echo $laporan['status_verifikasi'] === 'approved' ? 'selected' : ''; ?>>✅ Disetujui (Approved)</option>
                                    <option value="rejected" <?php echo $laporan['status_verifikasi'] === 'rejected' ? 'selected' : ''; ?>>❌ Ditolak (Rejected)</option>
                                </select>
                                <div class="absolute right-4 top-4 text-sky-500 pointer-events-none">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-6">
                        <button type="submit" class="group relative w-full flex justify-center py-4 px-4 border border-transparent text-sm font-bold rounded-xl text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 shadow-lg hover:shadow-sky-500/30 transition-all duration-300">
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <i class="fas fa-save group-hover:animate-bounce"></i>
                            </span>
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let map, marker;
        const initialLat = <?php echo !empty($laporan['latitude']) ? $laporan['latitude'] : '-6.2088'; ?>;
        const initialLng = <?php echo !empty($laporan['longitude']) ? $laporan['longitude'] : '106.8456'; ?>;
        const hasLocation = <?php echo !empty($laporan['latitude']) ? 'true' : 'false'; ?>;

        function initMap() {
            if (map) return; // Already initialized

            map = L.map('map').setView([initialLat, initialLng], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            if (hasLocation) {
                marker = L.marker([initialLat, initialLng]).addTo(map);
                // If address is empty but we have coordinates, fetch the address
                if (!document.getElementById('lokasi_input').value) {
                    updateLocation(initialLat, initialLng);
                }
            }

            map.on('click', function(e) {
                updateLocation(e.latlng.lat, e.latlng.lng);
            });
        }

        function updateLocation(lat, lng) {
            if (marker) {
                marker.setLatLng([lat, lng]);
            } else {
                marker = L.marker([lat, lng]).addTo(map);
            }

            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;
            
            // Show loading state
            const lokasiInput = document.getElementById('lokasi_input');
            const originalValue = lokasiInput.value;
            lokasiInput.value = "Sedang mengambil data alamat...";
            lokasiInput.classList.add('animate-pulse');

            // Reverse Geocoding using local API
            fetch(`../shared/api/reverse-geocode.php?lat=${lat}&lng=${lng}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    lokasiInput.classList.remove('animate-pulse');
                    if (data.success && data.address) {
                        lokasiInput.value = data.address;
                    } else if (data.display_name) {
                        lokasiInput.value = data.display_name;
                    } else {
                        lokasiInput.value = "Alamat tidak ditemukan (Koordinat: " + lat.toFixed(5) + ", " + lng.toFixed(5) + ")";
                    }
                })
                .catch(err => {
                    console.error(err);
                    lokasiInput.classList.remove('animate-pulse');
                    lokasiInput.value = "Gagal memuat alamat. Silakan ketik manual.";
                });
        }

        function getCurrentLocation(silent = false) {
            if (navigator.geolocation) {
                const btn = document.querySelector('button[onclick="getCurrentLocation()"]');
                const originalText = btn ? btn.innerHTML : '';
                if(btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Mencari...';
                
                const options = {
                    enableHighAccuracy: true,
                    timeout: 5000,
                    maximumAge: 0
                };
                
                navigator.geolocation.getCurrentPosition(function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    
                    map.setView([lat, lng], 17);
                    updateLocation(lat, lng);
                    
                    if(btn) btn.innerHTML = originalText;
                }, function(error) {
                    let msg = "Gagal mendapatkan lokasi.";
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            msg = "Izin lokasi ditolak. Mohon izinkan akses lokasi di browser.";
                            break;
                        case error.POSITION_UNAVAILABLE:
                            msg = "Informasi lokasi tidak tersedia.";
                            break;
                        case error.TIMEOUT:
                            msg = "Waktu permintaan lokasi habis.";
                            break;
                    }
                    
                    if (!silent) {
                        alert(msg);
                    } else {
                        console.warn("Silent location error: " + msg);
                    }
                    
                    if(btn) btn.innerHTML = originalText;
                }, options);
            } else {
                if (!silent) alert("Browser tidak mendukung geolocation.");
            }
        }

        function toggleMap() {
            const checkbox = document.getElementById('use_map');
            const mapContainer = document.getElementById('map-container');
            const latInput = document.getElementById('latitude');
            const lngInput = document.getElementById('longitude');

            if (checkbox.checked) {
                mapContainer.classList.remove('hidden');
                
                // Init map immediately
                initMap();
                
                if (map) {
                    map.invalidateSize();
                    
                    // Auto-detect location if no location is currently set
                    if (!latInput.value) {
                        getCurrentLocation(true); // silent mode
                    }
                }
                
                // Backup resize
                setTimeout(() => {
                    if (map) map.invalidateSize();
                }, 500);
            } else {
                mapContainer.classList.add('hidden');
                latInput.value = '';
                lngInput.value = '';
                document.getElementById('lokasi_input').value = ''; // Clear address text too
                if (marker && map) {
                    map.removeLayer(marker);
                    marker = null;
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('use_map').checked) {
                initMap();
            }
        });
    </script>
</body>
</html>