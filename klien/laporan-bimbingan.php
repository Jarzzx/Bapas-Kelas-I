<?php
ob_start(); // Buffer output to prevent header errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../shared/config/database.php';
require_once '../shared/config/auth.php';
requireKlienLogin();

$conn = getDBConnection();
$klien_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Get jadwal bimbingan yang sudah selesai atau terjadwal untuk klien ini
$jadwal_list = $conn->query("
    SELECT jb.id, jb.pk_id, jb.klien_id, jb.data_klien_id, jb.judul_bimbingan, jb.materi_bimbingan, 
           jb.tanggal_bimbingan, jb.lokasi_bimbingan, jb.latitude, jb.longitude, jb.status,
           jb.jenis_bimbingan, jb.link_meeting,
           pk.nama as pk_nama
    FROM jadwal_bimbingan jb
    JOIN pk_users pk ON jb.pk_id = pk.id
    WHERE jb.klien_id = $klien_id
    AND jb.status = 'terjadwal'
    ORDER BY jb.tanggal_bimbingan DESC
")->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['jadwal_id'])) {
    $jadwal_id = (int)$_POST['jadwal_id'];
    $keluhan = trim($_POST['keluhan'] ?? '');
    $lokasi_laporan = trim($_POST['lokasi_laporan'] ?? '');
    $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
    $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
    
    // Get jadwal info and data_klien_id
    $stmt = $conn->prepare("SELECT jb.pk_id, jb.data_klien_id, ku.no_registrasi 
                            FROM jadwal_bimbingan jb
                            JOIN klien_users ku ON jb.klien_id = ku.id
                            WHERE jb.id = ? AND jb.klien_id = ?");
    $stmt->bind_param("ii", $jadwal_id, $klien_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $jadwal = $result->fetch_assoc();
    $stmt->close();
    
    if (!$jadwal) {
        $message = 'Jadwal bimbingan tidak ditemukan!';
        $message_type = 'error';
    } else {
        // Get data_klien_id if not in jadwal
        $data_klien_id = $jadwal['data_klien_id'];
        if (!$data_klien_id && !empty($jadwal['no_registrasi'])) {
            $stmt = $conn->prepare("SELECT id FROM data_klien WHERE no_registrasi = ? LIMIT 1");
            $stmt->bind_param("s", $jadwal['no_registrasi']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $data_klien_id = $row['id'];
            }
            $stmt->close();
        }
        
        if (!$data_klien_id) {
            $message = 'Data klien tidak ditemukan di data_klien! Pastikan klien sudah ada di data_klien.';
            $message_type = 'error';
        } else {
        // Handle file upload
        $foto_bimbingan = null;
        if (isset($_FILES['foto_bimbingan']) && $_FILES['foto_bimbingan']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/foto_bimbingan/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_ext = strtolower(pathinfo($_FILES['foto_bimbingan']['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($file_ext, $allowed_ext)) {
                $filename = 'bimbingan_' . $klien_id . '_' . time() . '.' . $file_ext;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['foto_bimbingan']['tmp_name'], $filepath)) {
                    $foto_bimbingan = 'uploads/foto_bimbingan/' . $filename;
                }
            }
        }
        
        // Build isi_laporan from keluhan and other info
        $isi_laporan = '';
        if (!empty($keluhan)) {
            $isi_laporan = "Keluhan: " . $keluhan;
        }
        if (!empty($lokasi_laporan)) {
            $isi_laporan .= ($isi_laporan ? "\n\n" : '') . "Lokasi: " . $lokasi_laporan;
        }
        if (empty($isi_laporan)) {
            $isi_laporan = "Laporan bimbingan dari klien pada " . date('d/m/Y H:i');
        }
        
            // Insert laporan (using data_klien_id, not klien_users id)
            $stmt = $conn->prepare("INSERT INTO laporan_bimbingan (
                pk_id, klien_id, jadwal_bimbingan_id, dibuat_oleh,
                foto_bimbingan, lokasi_laporan, latitude_laporan, longitude_laporan,
                keluhan, isi_laporan, status_verifikasi, tanggal_laporan,
                bentuk_pembimbingan, tanggal_bimbingan, materi_bimbingan
            ) VALUES (?, ?, ?, 'klien', ?, ?, ?, ?, ?, ?, 'pending', NOW(), ?, ?, ?)");
            
            $stmt->bind_param("iiisssdsssss", 
                $jadwal['pk_id'],
                $data_klien_id,  // Use data_klien_id, not klien_users id
                $jadwal_id,
                $foto_bimbingan,
                $lokasi_laporan,
                $latitude,
                $longitude,
                $keluhan,
                $isi_laporan,
                $jadwal['jenis_bimbingan'],
                $jadwal['tanggal_bimbingan'],
                $jadwal['materi_bimbingan']
            );
            
            if ($stmt->execute()) {
                // Update jadwal status to selesai
                $stmt2 = $conn->prepare("UPDATE jadwal_bimbingan SET status = 'selesai' WHERE id = ?");
                $stmt2->bind_param("i", $jadwal_id);
                $stmt2->execute();
                $stmt2->close();
                
                $_SESSION['success_message'] = 'Laporan bimbingan berhasil dibuat! Menunggu verifikasi dari PK.';
                
                // Redirect to avoid form resubmission (PRG Pattern)
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $message = 'Gagal membuat laporan: ' . $conn->error;
                $message_type = 'error';
            }
            $stmt->close();
        }
    }
}

// Check for session message
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    $message_type = 'success';
    unset($_SESSION['success_message']);
}

// Get jadwal bimbingan yang sudah selesai atau terjadwal untuk klien ini (Refresh list)
$jadwal_list = $conn->query("
    SELECT jb.id, jb.pk_id, jb.klien_id, jb.data_klien_id, jb.judul_bimbingan, jb.materi_bimbingan, 
           jb.tanggal_bimbingan, jb.lokasi_bimbingan, jb.latitude, jb.longitude, jb.status,
           jb.jenis_bimbingan, jb.link_meeting,
           pk.nama as pk_nama
    FROM jadwal_bimbingan jb
    JOIN pk_users pk ON jb.pk_id = pk.id
    WHERE jb.klien_id = $klien_id
    AND jb.status = 'terjadwal'
    ORDER BY jb.tanggal_bimbingan DESC
")->fetch_all(MYSQLI_ASSOC);

// Get laporan yang sudah dibuat (join dengan data_klien untuk mendapatkan klien yang benar)
// Changed to LEFT JOIN to include reports created directly by PK (without schedule)
$laporan_list = $conn->query("
    SELECT lb.*, 
           COALESCE(jb.judul_bimbingan, lb.jenis_bimbingan, 'Bimbingan Rutin') as judul_bimbingan, 
           COALESCE(jb.materi_bimbingan, lb.materi_bimbingan) as materi_bimbingan, 
           COALESCE(jb.tanggal_bimbingan, lb.tanggal_bimbingan, lb.tanggal_laporan) as jadwal_tanggal,
           pk.nama as pk_nama
    FROM laporan_bimbingan lb
    LEFT JOIN jadwal_bimbingan jb ON lb.jadwal_bimbingan_id = jb.id
    LEFT JOIN pk_users pk ON lb.pk_id = pk.id
    JOIN data_klien dk ON lb.klien_id = dk.id
    JOIN klien_users ku ON dk.no_registrasi = ku.no_registrasi
    WHERE ku.id = $klien_id
    ORDER BY lb.tanggal_laporan DESC
")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Bimbingan - BAPAS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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
        .map-container {
            height: 300px !important;
            min-height: 300px;
            border-radius: 0.75rem;
            width: 100%;
            position: relative;
            z-index: 1;
            box-shadow: inset 0 2px 4px 0 rgb(0 0 0 / 0.05);
        }
        [id^="map-"] {
            height: 300px !important;
            min-height: 300px;
            border-radius: 0.75rem;
            width: 100%;
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Animations */
        @keyframes fade-in-up {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .fade-in-up {
            animation: fade-in-up 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
    </style>
</head>
<body class="bg-slate-50 font-sans antialiased text-slate-800">
    <div class="container mx-auto px-4 py-8 max-w-5xl">
        <div class="mb-8 fade-in-up">
            <a href="dashboard.php" class="inline-flex items-center text-sky-600 hover:text-sky-800 font-medium transition-colors duration-200 group">
                <div class="w-10 h-10 rounded-full bg-white border border-slate-100 shadow-sm flex items-center justify-center mr-3 group-hover:bg-sky-50 transition-colors">
                    <i class="fas fa-arrow-left group-hover:-translate-x-1 transition-transform text-sky-500"></i>
                </div>
                <span class="text-slate-600 group-hover:text-sky-700 transition-colors">Kembali ke Dashboard</span>
            </a>
        </div>

        <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-white overflow-hidden fade-in-up mb-10" style="animation-delay: 0.1s;">
            <div class="bg-gradient-to-r from-sky-500 to-teal-500 px-8 py-10 text-white relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -mr-16 -mt-16 blur-3xl"></div>
                <div class="absolute bottom-0 left-0 w-48 h-48 bg-teal-400/20 rounded-full -ml-10 -mb-10 blur-2xl"></div>
                
                <div class="relative z-10 flex items-center gap-6">
                    <div class="w-16 h-16 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center border border-white/20 shadow-inner">
                        <i class="fas fa-file-signature text-3xl text-white"></i>
                    </div>
                    <div>
                        <h2 class="text-3xl font-bold tracking-tight text-white mb-2">Buat Laporan Bimbingan</h2>
                        <p class="text-sky-50 text-sm font-medium opacity-90 max-w-xl leading-relaxed">Laporkan hasil bimbingan Anda secara berkala untuk pemantauan perkembangan yang lebih baik.</p>
                    </div>
                </div>
            </div>

            <div class="p-8 bg-white">
                <?php if ($message): ?>
                    <div class="mb-8 p-4 rounded-xl <?php echo $message_type === 'success' ? 'bg-teal-50 text-teal-700 border border-teal-200' : 'bg-rose-50 text-rose-700 border border-rose-200'; ?> flex items-start gap-3 shadow-sm animate-pulse">
                        <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mt-0.5 text-lg"></i>
                        <div>
                            <p class="font-medium"><?php echo htmlspecialchars($message); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (empty($jadwal_list)): ?>
                    <div class="text-center py-16 px-4 rounded-3xl bg-slate-50 border border-slate-200 border-dashed group hover:bg-slate-50/80 transition-colors">
                        <div class="w-24 h-24 bg-white rounded-full flex items-center justify-center mx-auto mb-6 shadow-sm group-hover:scale-110 transition-transform duration-300">
                            <i class="fas fa-calendar-check text-4xl text-slate-300 group-hover:text-sky-400 transition-colors"></i>
                        </div>
                        <h3 class="text-xl font-bold text-slate-700 mb-2">Tidak Ada Jadwal Bimbingan</h3>
                        <p class="text-slate-500 text-sm max-w-md mx-auto leading-relaxed">Saat ini tidak ada jadwal bimbingan yang perlu dilaporkan. Silakan tunggu jadwal dari PK Anda atau hubungi PK jika ada pertanyaan.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-10">
                        <?php foreach ($jadwal_list as $jadwal): ?>
                            <div class="bg-white rounded-3xl border border-slate-200 shadow-lg shadow-slate-100 hover:shadow-xl hover:shadow-sky-100/50 hover:-translate-y-1 transition-all duration-300 overflow-hidden group">
                                <div class="bg-gradient-to-r from-sky-50 to-white border-b border-sky-100 p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
                                    <div class="flex items-center gap-4">
                                        <div class="w-14 h-14 rounded-2xl bg-white shadow-sm border border-sky-100 flex flex-col items-center justify-center text-sky-600 group-hover:scale-105 transition-transform">
                                            <span class="text-xs font-bold uppercase tracking-wider text-sky-400">Tgl</span>
                                            <span class="text-xl font-bold"><?php echo date('d', strtotime($jadwal['tanggal_bimbingan'])); ?></span>
                                        </div>
                                        <div>
                                            <p class="text-xs text-sky-600 font-bold uppercase tracking-wider mb-1">Jadwal Bimbingan</p>
                                            <h3 class="text-lg font-bold text-slate-800"><?php echo date('F Y', strtotime($jadwal['tanggal_bimbingan'])); ?></h3>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="px-4 py-2 rounded-full bg-white border border-sky-200 text-sm font-semibold text-sky-700 shadow-sm flex items-center gap-2">
                                            <i class="far fa-clock text-sky-400"></i>
                                            <?php echo date('H:i', strtotime($jadwal['tanggal_bimbingan'])); ?> WIB
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="p-6 md:p-8">
                                    <form method="POST" enctype="multipart/form-data" class="space-y-8">
                                        <input type="hidden" name="jadwal_id" value="<?php echo $jadwal['id']; ?>">
                                        
                                        <div class="grid md:grid-cols-2 gap-8">
                                            <div class="space-y-6">
                                                <div class="bg-slate-50 rounded-2xl p-6 border border-slate-200/60 relative overflow-hidden">
                                                    <div class="absolute top-0 right-0 w-24 h-24 bg-sky-100/50 rounded-full -mr-10 -mt-10 blur-2xl"></div>
                                                    
                                                    <h3 class="text-lg font-bold text-slate-800 mb-5 flex items-center gap-2 relative z-10">
                                                        <i class="fas fa-info-circle text-sky-500"></i> Detail Bimbingan
                                                    </h3>
                                                    
                                                    <div class="space-y-4 relative z-10">
                                                        <div class="group/item">
                                                            <span class="text-xs text-slate-400 uppercase tracking-wider font-bold mb-1 block">Judul</span>
                                                            <p class="text-slate-700 font-semibold group-hover/item:text-sky-600 transition-colors"><?php echo htmlspecialchars($jadwal['judul_bimbingan']); ?></p>
                                                        </div>
                                                        <div class="group/item">
                                                            <span class="text-xs text-slate-400 uppercase tracking-wider font-bold mb-1 block">Pembimbing (PK)</span>
                                                            <p class="text-slate-700 font-semibold flex items-center gap-2">
                                                                <div class="w-6 h-6 rounded-full bg-sky-100 flex items-center justify-center text-xs text-sky-600 font-bold">PK</div>
                                                                <?php echo htmlspecialchars($jadwal['pk_nama']); ?>
                                                            </p>
                                                        </div>
                                                        <div class="group/item">
                                                            <span class="text-xs text-slate-400 uppercase tracking-wider font-bold mb-1 block">Materi</span>
                                                            <p class="text-slate-700 font-medium text-sm leading-relaxed bg-white p-3 rounded-lg border border-slate-100 shadow-sm"><?php echo htmlspecialchars($jadwal['materi_bimbingan']); ?></p>
                                                        </div>
                                                        <div>
                                                            <span class="text-xs text-slate-400 uppercase tracking-wider font-bold mb-1 block">Jenis & Lokasi</span>
                                                            <?php if ($jadwal['jenis_bimbingan'] === 'daring'): ?>
                                                                <div class="flex items-center gap-3 mt-1">
                                                                    <span class="px-3 py-1 rounded-lg text-xs font-bold bg-indigo-50 text-indigo-600 border border-indigo-100">Daring</span>
                                                                    <a href="<?php echo htmlspecialchars($jadwal['link_meeting']); ?>" target="_blank" class="text-sm text-sky-600 hover:text-sky-800 hover:underline truncate max-w-[200px] flex items-center gap-1 font-medium">
                                                                        Link Meeting <i class="fas fa-external-link-alt text-xs"></i>
                                                                    </a>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="mt-1">
                                                                    <span class="px-3 py-1 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-600 border border-emerald-100 mb-2 inline-block">Tatap Muka</span>
                                                                    <p class="text-sm text-slate-700 flex items-center gap-2">
                                                                        <i class="fas fa-map-marker-alt text-rose-400"></i>
                                                                        <?php echo htmlspecialchars($jadwal['lokasi_bimbingan'] ?: '-'); ?>
                                                                    </p>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div>
                                                    <label class="block text-slate-700 text-sm font-bold mb-2 flex items-center gap-2">
                                                        <i class="fas fa-camera text-sky-500"></i> Foto Dokumentasi
                                                    </label>
                                                    <div class="relative group/upload">
                                                        <input type="file" name="foto_bimbingan" accept="image/*"
                                                               class="block w-full text-sm text-slate-500
                                                               file:mr-4 file:py-3 file:px-6
                                                               file:rounded-xl file:border-0
                                                               file:text-sm file:font-bold
                                                               file:bg-sky-50 file:text-sky-600
                                                               hover:file:bg-sky-100
                                                               file:transition-colors file:duration-200
                                                               cursor-pointer border-2 border-slate-200 border-dashed rounded-xl p-2 hover:border-sky-400 transition-colors bg-slate-50/50">
                                                    </div>
                                                    <p class="text-xs text-slate-400 mt-2 ml-1 flex items-center gap-1">
                                                        <i class="fas fa-info-circle"></i> Format: JPG, PNG (Max 5MB)
                                                    </p>
                                                </div>
                                                
                                                <div>
                                                    <label class="block text-slate-700 text-sm font-bold mb-2 flex items-center gap-2">
                                                        <i class="fas fa-pen text-sky-500"></i> Keluhan / Catatan *
                                                    </label>
                                                    <textarea name="keluhan" required rows="4"
                                                              class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-slate-700 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-500/10 transition-all duration-200 resize-none"
                                                              placeholder="Ceritakan jalannya bimbingan atau keluhan yang Anda rasakan..."></textarea>
                                                </div>
                                            </div>

                                            <div class="space-y-6">
                                                <div>
                                                    <label class="block text-slate-700 text-sm font-bold mb-3 flex items-center gap-2">
                                                        <i class="fas fa-map-marked-alt text-sky-500"></i> Lokasi Anda Saat Ini (Pinpoint) *
                                                    </label>
                                                    <div class="bg-slate-50 p-3 rounded-2xl border border-slate-200 shadow-inner">
                                                        <div id="map-<?php echo $jadwal['id']; ?>" class="map-container rounded-xl overflow-hidden shadow-sm border border-slate-200/50"></div>
                                                        <div class="mt-3 flex justify-end">
                                                            <button type="button" onclick="getCurrentLocation(<?php echo $jadwal['id']; ?>)" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 text-sm font-bold rounded-lg shadow-sm hover:bg-slate-50 hover:text-sky-600 hover:border-sky-200 transition-all duration-200 flex items-center gap-2 group/btn">
                                                                <i class="fas fa-crosshairs text-sky-500 group-hover/btn:animate-spin"></i> Ambil Lokasi Saat Ini
                                                            </button>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mt-4 space-y-3">
                                                        <div class="relative">
                                                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                                                <i class="fas fa-search text-slate-400"></i>
                                                            </div>
                                                            <input type="text" id="lokasi-input-<?php echo $jadwal['id']; ?>" 
                                                                   class="w-full pl-11 pr-4 py-3 bg-white border border-slate-200 rounded-xl text-slate-700 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-500/10 transition-all duration-200"
                                                                   placeholder="Cari alamat lokasi..."
                                                                   onkeyup="searchAddressGoAPI(<?php echo $jadwal['id']; ?>, this.value)">
                                                        </div>
                                                        
                                                        <textarea name="lokasi_laporan" required rows="2"
                                                                  class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-slate-700 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-500/10 transition-all duration-200 resize-none"
                                                                  placeholder="Detail alamat lengkap..."></textarea>
                                                    </div>
                                                          
                                                    <input type="hidden" name="latitude" id="latitude-<?php echo $jadwal['id']; ?>" value="<?php echo $jadwal['latitude'] ?? ''; ?>">
                                                    <input type="hidden" name="longitude" id="longitude-<?php echo $jadwal['id']; ?>" value="<?php echo $jadwal['longitude'] ?? ''; ?>">
                                                    
                                                    <div class="flex flex-wrap gap-3 mt-3">
                                                        <div class="flex items-center text-xs text-slate-500 font-mono bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-200">
                                                            <i class="fas fa-map-marker-alt mr-2 text-rose-500"></i>Lat: <span id="lat-display-<?php echo $jadwal['id']; ?>" class="ml-1 font-bold"><?php echo $jadwal['latitude'] ?? '-'; ?></span>
                                                        </div>
                                                        <div class="flex items-center text-xs text-slate-500 font-mono bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-200">
                                                            <i class="fas fa-map-marker-alt mr-2 text-rose-500"></i>Lng: <span id="lng-display-<?php echo $jadwal['id']; ?>" class="ml-1 font-bold"><?php echo $jadwal['longitude'] ?? '-'; ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex justify-end pt-6 border-t border-slate-100">
                                            <button type="submit" class="px-8 py-3.5 bg-gradient-to-r from-sky-500 to-teal-500 text-white rounded-xl font-bold shadow-lg shadow-sky-500/30 hover:shadow-xl hover:shadow-sky-500/40 hover:-translate-y-1 transition-all duration-300 flex items-center gap-3">
                                                <i class="fas fa-paper-plane"></i> Kirim Laporan
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Laporan yang Sudah Dibuat -->
        <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-white overflow-hidden fade-in-up" style="animation-delay: 0.2s;">
            <div class="px-8 py-6 border-b border-slate-100 bg-slate-50/50 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <h2 class="text-xl font-bold text-slate-800 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-teal-100 flex items-center justify-center text-teal-600">
                        <i class="fas fa-history"></i>
                    </div>
                    Riwayat Laporan
                </h2>
                <div class="text-sm text-slate-500 font-medium">
                    Menampilkan riwayat laporan bimbingan Anda
                </div>
            </div>
            
            <div class="p-8 bg-white">
                <div class="space-y-6">
                    <?php if (empty($laporan_list)): ?>
                        <div class="text-center py-12 bg-slate-50 rounded-2xl border border-slate-100 border-dashed">
                            <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mx-auto mb-4 shadow-sm">
                                <i class="fas fa-clipboard-list text-2xl text-slate-300"></i>
                            </div>
                            <p class="text-slate-500 font-medium">Belum ada riwayat laporan bimbingan</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($laporan_list as $laporan): ?>
                            <div class="bg-white rounded-2xl border border-slate-200 p-6 hover:shadow-lg hover:shadow-slate-200/50 transition-all duration-300 group <?php 
                                echo $laporan['status_verifikasi'] === 'approved' ? 'border-l-[6px] border-l-emerald-500' : 
                                    ($laporan['status_verifikasi'] === 'rejected' ? 'border-l-[6px] border-l-rose-500' : 'border-l-[6px] border-l-amber-500'); 
                            ?>">
                                <div class="flex flex-col lg:flex-row gap-8">
                                    <div class="flex-1">
                                        <div class="flex flex-col sm:flex-row sm:items-start justify-between mb-4 gap-2">
                                            <h3 class="text-lg font-bold text-slate-800 group-hover:text-sky-600 transition-colors">
                                                <?php echo htmlspecialchars($laporan['judul_bimbingan']); ?>
                                            </h3>
                                            <div class="flex-shrink-0">
                                                <span class="px-3 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider inline-flex items-center gap-1.5 <?php 
                                                    echo $laporan['status_verifikasi'] === 'approved' ? 'bg-emerald-100 text-emerald-700' : 
                                                        ($laporan['status_verifikasi'] === 'rejected' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700'); 
                                                ?>">
                                                    <?php 
                                                        if ($laporan['status_verifikasi'] === 'approved') echo '<i class="fas fa-check-circle"></i> Disetujui';
                                                        elseif ($laporan['status_verifikasi'] === 'rejected') echo '<i class="fas fa-times-circle"></i> Ditolak';
                                                        else echo '<i class="fas fa-clock"></i> Menunggu';
                                                    ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="grid sm:grid-cols-2 gap-y-3 gap-x-6 text-sm text-slate-600 mb-5 bg-slate-50 p-4 rounded-xl border border-slate-100">
                                            <div class="flex items-center gap-3">
                                                <div class="w-6 h-6 rounded-full bg-white flex items-center justify-center text-slate-400 shadow-sm border border-slate-100">
                                                    <i class="fas fa-user-tie text-xs"></i>
                                                </div>
                                                <span class="font-medium"><?php echo htmlspecialchars($laporan['pk_nama']); ?></span>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <div class="w-6 h-6 rounded-full bg-white flex items-center justify-center text-slate-400 shadow-sm border border-slate-100">
                                                    <i class="far fa-calendar-alt text-xs"></i>
                                                </div>
                                                <span class="font-medium"><?php echo date('d M Y H:i', strtotime($laporan['jadwal_tanggal'])); ?></span>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <div class="w-6 h-6 rounded-full bg-white flex items-center justify-center text-slate-400 shadow-sm border border-slate-100">
                                                    <i class="fas fa-map-marker-alt text-xs"></i>
                                                </div>
                                                <span class="truncate font-medium"><?php echo htmlspecialchars($laporan['lokasi_laporan'] ?: '-'); ?></span>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <div class="w-6 h-6 rounded-full bg-white flex items-center justify-center text-slate-400 shadow-sm border border-slate-100">
                                                    <i class="fas fa-paper-plane text-xs"></i>
                                                </div>
                                                <span class="font-medium">Dilaporkan: <?php echo date('d M Y', strtotime($laporan['tanggal_laporan'])); ?></span>
                                            </div>
                                        </div>
                                        
                                        <?php if ($laporan['keluhan']): ?>
                                            <div class="bg-slate-50/50 p-4 rounded-xl border border-slate-100 mb-4">
                                                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2 flex items-center gap-2">
                                                    <i class="fas fa-pen text-slate-300"></i> Keluhan / Catatan
                                                </p>
                                                <p class="text-slate-700 text-sm leading-relaxed"><?php echo htmlspecialchars($laporan['keluhan']); ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($laporan['hasil_bimbingan']): ?>
                                            <div class="bg-indigo-50 p-5 rounded-xl border border-indigo-100 relative overflow-hidden group/feedback">
                                                <div class="absolute top-0 right-0 w-20 h-20 bg-indigo-200/20 rounded-full -mr-6 -mt-6 transition-transform group-hover/feedback:scale-110 duration-500"></div>
                                                <p class="text-indigo-700 font-bold text-sm mb-2 flex items-center gap-2">
                                                    <i class="fas fa-lightbulb text-indigo-500"></i> Saran / Tanggapan PK
                                                </p>
                                                <p class="text-slate-700 text-sm leading-relaxed relative z-10"><?php echo nl2br(htmlspecialchars($laporan['hasil_bimbingan'])); ?></p>
                                            </div>
                                        <?php endif; ?>

                                        <div class="mt-5 pt-4 border-t border-slate-100 flex items-center justify-end">
                                            <a href="cetak-laporan-pdf.php?id=<?php echo $laporan['id']; ?>" target="_blank" 
                                               class="inline-flex items-center gap-2 px-4 py-2 bg-slate-800 text-white text-sm font-bold rounded-lg hover:bg-slate-700 hover:shadow-lg transition-all duration-300 group/btn">
                                                <i class="fas fa-file-pdf text-red-400 group-hover/btn:scale-110 transition-transform"></i>
                                                <span>Download PDF</span>
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <?php if ($laporan['foto_bimbingan']): ?>
                                        <div class="lg:w-48 flex-shrink-0 mt-4 lg:mt-0">
                                            <div class="relative group cursor-pointer overflow-hidden rounded-xl shadow-md border border-slate-200">
                                                <img src="../<?php echo htmlspecialchars($laporan['foto_bimbingan']); ?>" 
                                                     alt="Foto Bimbingan" 
                                                     class="w-full h-32 object-cover transition-transform duration-500 group-hover:scale-110">
                                                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors duration-300 flex items-center justify-center">
                                                    <i class="fas fa-search-plus text-white opacity-0 group-hover:opacity-100 transform translate-y-2 group-hover:translate-y-0 transition-all duration-300 drop-shadow-md"></i>
                                                </div>
                                            </div>
                                            <p class="text-center text-xs text-slate-400 mt-2 font-medium flex items-center justify-center gap-1">
                                                <i class="fas fa-image"></i> Dokumentasi
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global functions for location handling
        let searchTimeout = null;
        
        function searchAddressGoAPI(id, query) {
            if (searchTimeout) clearTimeout(searchTimeout);
            
            if (!query || query.length < 3) return;
            
            searchTimeout = setTimeout(() => {
                const inputContainer = document.getElementById('lokasi-input-' + id).parentElement;
                const icon = inputContainer.querySelector('i');
                const originalIconClass = icon ? icon.className : 'fas fa-search text-slate-400';
                
                if(icon) icon.className = 'fas fa-spinner fa-spin text-sky-500';
                
                fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1&accept-language=id`)
                    .then(res => res.json())
                    .then(data => {
                        if(icon) icon.className = originalIconClass;
                        
                        if (data && data.length > 0) {
                            const lat = parseFloat(data[0].lat);
                            const lon = parseFloat(data[0].lon);
                            
                            console.log('Search result:', lat, lon);
                            
                            // Update map
                            const map = window['map_' + id];
                            const marker = window['marker_' + id];
                            
                            if (map && marker) {
                                const newLatLng = new L.LatLng(lat, lon);
                                map.setView(newLatLng, 17);
                                marker.setLatLng(newLatLng);
                                
                                // Update location details (this will trigger reverse geocode to normalize address format)
                                updateLocation(id, lat, lon);
                            }
                        }
                    })
                    .catch(err => {
                        console.error('Search error:', err);
                        if(icon) icon.className = originalIconClass;
                    });
            }, 1000);
        }

        function buildAddressFromComponents(address) {
            if (!address) return '';
            
            const parts = [];
            
            // Road/Street
            if (address.road) {
                if (address.house_number) {
                    parts.push(address.house_number + ' ' + address.road);
                } else {
                    parts.push(address.road);
                }
            } else if (address.house_number) {
                parts.push(address.house_number);
            }
            
            // Village/Hamlet/Neighbourhood
            if (address.village) {
                parts.push(address.village);
            } else if (address.hamlet) {
                parts.push(address.hamlet);
            } else if (address.neighbourhood) {
                parts.push(address.neighbourhood);
            }
            
            // Suburb/City District
            if (address.suburb) {
                parts.push(address.suburb);
            } else if (address.city_district) {
                parts.push(address.city_district);
            }
            
            // District
            if (address.district) {
                parts.push(address.district);
            }
            
            // City/Town/Municipality
            if (address.city) {
                parts.push(address.city);
            } else if (address.town) {
                parts.push(address.town);
            } else if (address.municipality) {
                parts.push(address.municipality);
            }
            
            // State/Province
            if (address.state) {
                parts.push(address.state);
            } else if (address.province) {
                parts.push(address.province);
            }
            
            // Postal code
            if (address.postcode) {
                parts.push(address.postcode);
            }
            
            // Country
            if (address.country) {
                parts.push(address.country);
            }
            
            return parts.join(', ');
        }
        
        function updateLocation(id, lat, lng) {
            // Update coordinates first
            const latInput = document.getElementById('latitude-' + id);
            const lngInput = document.getElementById('longitude-' + id);
            const latDisplay = document.getElementById('lat-display-' + id);
            const lngDisplay = document.getElementById('lng-display-' + id);
            
            if (latInput) latInput.value = lat;
            if (lngInput) lngInput.value = lng;
            if (latDisplay) latDisplay.textContent = parseFloat(lat).toFixed(6);
            if (lngDisplay) lngDisplay.textContent = parseFloat(lng).toFixed(6);
            
            // Get form element
            const form = document.querySelector(`form input[name="jadwal_id"][value="${id}"]`)?.closest('form');
            if (!form) {
                return;
            }
            
            const lokasiInput = document.getElementById('lokasi-input-' + id);
            const lokasiTextarea = form.querySelector('textarea[name="lokasi_laporan"]');
            
            // Show loading
            if (lokasiInput) lokasiInput.value = 'Mengambil alamat...';
            if (lokasiTextarea) lokasiTextarea.value = 'Mengambil alamat...';
            
            console.log('Fetching address for:', lat, lng);
            
            // Reverse geocode using our PHP proxy (no CORS issues)
            const controller = new AbortController();
            const timeoutId = setTimeout(() => {
                controller.abort();
                console.log('Request timeout after 10 seconds');
                
                // On timeout, allow manual input
                if (lokasiInput) {
                    lokasiInput.value = '';
                    lokasiInput.placeholder = 'Alamat tidak ditemukan. Silakan ketik manual.';
                }
                if (lokasiTextarea) {
                    lokasiTextarea.value = '';
                    lokasiTextarea.placeholder = 'Alamat tidak ditemukan. Silakan ketik manual.';
                }
            }, 10000); // 10 seconds timeout
            
            fetch(`../shared/api/reverse-geocode.php?lat=${lat}&lng=${lng}`, {
                method: 'GET',
                signal: controller.signal
            })
            .then(response => {
                clearTimeout(timeoutId);
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.json();
            })
            .then(result => {
                console.log('Reverse geocode result:', result);
                
                if (result.success && result.address && result.address.trim() !== '') {
                    const address = result.address.trim();
                    
                    // Update fields
                    if (lokasiInput) {
                        lokasiInput.value = address;
                        console.log('✅ Updated lokasiInput:', address);
                    }
                    if (lokasiTextarea) {
                        lokasiTextarea.value = address;
                        console.log('✅ Updated lokasiTextarea:', address);
                    }
                } else {
                    console.warn('⚠️ No address found in response');
                    // Clear fields if no address found
                    if (lokasiInput) {
                        lokasiInput.value = '';
                        lokasiInput.placeholder = 'Alamat tidak ditemukan. Silakan ketik manual.';
                    }
                    if (lokasiTextarea) {
                        lokasiTextarea.value = '';
                        lokasiTextarea.placeholder = 'Alamat tidak ditemukan. Silakan ketik manual.';
                    }
                }
            })
            .catch(e => {
                clearTimeout(timeoutId);
                if (e.name === 'AbortError') {
                    console.error('❌ Request timeout');
                } else {
                    console.error('❌ Reverse geocode error:', e);
                }
                // Clear fields on error and allow manual input
                if (lokasiInput) {
                    lokasiInput.value = '';
                    lokasiInput.placeholder = 'Alamat tidak ditemukan. Silakan ketik manual.';
                }
                if (lokasiTextarea) {
                    lokasiTextarea.value = '';
                    lokasiTextarea.placeholder = 'Alamat tidak ditemukan. Silakan ketik manual.';
                }
            });
        }

        function getCurrentLocation(id) {
            // Check for secure context
            const isSecure = window.isSecureContext;
            if (!isSecure && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
                alert('Peringatan: Browser memblokir akses lokasi pada koneksi tidak aman (HTTP). Mohon gunakan HTTPS atau localhost.');
            }

            if (!navigator.geolocation) {
                alert('Geolocation tidak didukung oleh browser ini.');
                return;
            }
            
            // Show loading state on button
            const btn = document.querySelector(`button[onclick="getCurrentLocation(${id})"]`);
            let originalText = '';
            if (btn) {
                originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin text-sky-500"></i> Mencari Lokasi...';
                btn.disabled = true;
                btn.classList.add('cursor-not-allowed', 'opacity-75');
            }

            const options = {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            };

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    
                    console.log('Got current location:', lat, lng);
                    
                    // Update map view
                    const map = window['map_' + id];
                    const marker = window['marker_' + id];
                    
                    if (map && marker) {
                        const newLatLng = new L.LatLng(lat, lng);
                        map.setView(newLatLng, 18); // Zoom in closer
                        marker.setLatLng(newLatLng);
                        
                        // Update location details
                        updateLocation(id, lat, lng);
                        
                        // Show success feedback
                        if (btn) {
                            btn.innerHTML = '<i class="fas fa-check text-emerald-500"></i> Lokasi Ditemukan';
                            setTimeout(() => {
                                btn.innerHTML = originalText;
                                btn.disabled = false;
                                btn.classList.remove('cursor-not-allowed', 'opacity-75');
                            }, 2000);
                        }
                    } else {
                        alert('Peta belum siap. Silakan coba lagi.');
                        if (btn) {
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                            btn.classList.remove('cursor-not-allowed', 'opacity-75');
                        }
                    }
                },
                function(error) {
                    console.error('Geolocation error:', error);
                    let msg = 'Gagal mengambil lokasi.';
                    let detail = '';
                    
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            msg = 'Izin lokasi ditolak.';
                            detail = 'Mohon izinkan akses lokasi di pengaturan browser (icon gembok/lokasi di address bar), lalu coba lagi.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            msg = 'Informasi lokasi tidak tersedia.';
                            detail = 'Pastikan GPS/Lokasi perangkat Anda aktif.';
                            break;
                        case error.TIMEOUT:
                            msg = 'Waktu permintaan lokasi habis.';
                            detail = 'Koneksi lambat atau GPS sulit dijangkau. Silakan coba lagi.';
                            break;
                    }
                    
                    alert(`${msg}\n\n${detail}`);
                    
                    // Reset button
                    if (btn) {
                        btn.innerHTML = '<i class="fas fa-exclamation-triangle text-rose-500"></i> Gagal';
                        setTimeout(() => {
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                            btn.classList.remove('cursor-not-allowed', 'opacity-75');
                        }, 2000);
                    }
                },
                options
            );
        }

        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($jadwal_list as $jadwal): ?>
            (function() {
                const jadwalId = <?php echo $jadwal['id']; ?>;
                const defaultLat = <?php echo !empty($jadwal['latitude']) ? $jadwal['latitude'] : '-6.2088'; ?>;
                const defaultLng = <?php echo !empty($jadwal['longitude']) ? $jadwal['longitude'] : '106.8456'; ?>;
                
                // Wait a bit to ensure map container is rendered
                setTimeout(function() {
                    const mapElement = document.getElementById('map-' + jadwalId);
                    if (!mapElement) {
                        console.error('Map element not found: map-' + jadwalId);
                        return;
                    }
                    
                    // Check if map is already initialized
                    if (mapElement._leaflet_id) {
                        console.log('Map already initialized for map-' + jadwalId);
                        return;
                    }
                    
                    // Ensure element has height
                    mapElement.style.height = '300px';
                    mapElement.style.minHeight = '300px';
                    
                    console.log('Initializing map for map-' + jadwalId, 'at', defaultLat, defaultLng);
                    
                    const map = L.map('map-' + jadwalId, {
                        zoomControl: true
                    }).setView([defaultLat, defaultLng], 15);
                    
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors',
                        maxZoom: 19
                    }).addTo(map);
                    
                    const marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);
                    
                    // Initialize coordinates
                    document.getElementById('latitude-' + jadwalId).value = defaultLat;
                    document.getElementById('longitude-' + jadwalId).value = defaultLng;
                    document.getElementById('lat-display-' + jadwalId).textContent = parseFloat(defaultLat).toFixed(6);
                    document.getElementById('lng-display-' + jadwalId).textContent = parseFloat(defaultLng).toFixed(6);
                    
                    // Store marker reference
                    window['marker_' + jadwalId] = marker;
                    window['map_' + jadwalId] = map;
                    
                    // Event handlers
                    marker.on('dragend', function(e) {
                        const latlng = marker.getLatLng();
                        console.log('Marker dragged to:', latlng.lat, latlng.lng);
                        updateLocation(jadwalId, latlng.lat, latlng.lng);
                    });
                    
                    map.on('click', function(e) {
                        console.log('Map clicked at:', e.latlng.lat, e.latlng.lng);
                        marker.setLatLng(e.latlng);
                        updateLocation(jadwalId, e.latlng.lat, e.latlng.lng);
                    });
                    
                    // Initial address load - always try to get address
                    console.log('Initial load for jadwal', jadwalId, 'at', defaultLat, defaultLng);
                    updateLocation(jadwalId, defaultLat, defaultLng);
                }, 100);
            })();
            <?php endforeach; ?>
        });
    </script>
</body>
</html>
