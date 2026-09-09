<?php
require_once '../shared/config/database.php';
require_once '../shared/config/auth.php';
requirePKLogin();

$conn = getDBConnection();
$pk_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Get klien yang sudah approved dan biodata lengkap
$klien_list = $conn->query("
    SELECT ku.id, ku.nama, ku.no_registrasi, dk.id as data_klien_id, ku.no_telepon
    FROM klien_users ku
    LEFT JOIN data_klien dk ON ku.no_registrasi = dk.no_registrasi AND dk.pk_id = $pk_id
    WHERE ku.pk_id = $pk_id 
    AND ku.status_approval = 'approved'
    AND ku.biodata_lengkap = 'sudah'
    ORDER BY ku.nama ASC
")->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $klien_id = (int)$_POST['klien_id'] ?? 0;
    $data_klien_id = !empty($_POST['data_klien_id']) ? (int)$_POST['data_klien_id'] : null;
    $judul_bimbingan = trim($_POST['judul_bimbingan'] ?? '');
    $materi_bimbingan = trim($_POST['materi_bimbingan'] ?? '');
    $jenis_bimbingan = $_POST['jenis_bimbingan'] ?? 'tatap_muka';
    
    // Handle link meeting based on platform
    $link_meeting = '';
    if ($jenis_bimbingan === 'daring') {
        $sub_jenis_daring = $_POST['sub_jenis_daring'] ?? 'meeting_link';
        if ($sub_jenis_daring === 'whatsapp') {
            $wa_number = preg_replace('/[^0-9]/', '', $_POST['wa_number'] ?? '');
            // Format number to 62xxx
            if (substr($wa_number, 0, 1) === '0') {
                $wa_number = '62' . substr($wa_number, 1);
            } elseif (substr($wa_number, 0, 2) !== '62') {
                $wa_number = '62' . $wa_number;
            }
            $link_meeting = 'https://wa.me/' . $wa_number;
        } else {
            $link_meeting = trim($_POST['meeting_url'] ?? '');
        }
    }
    
    $tanggal_bimbingan = $_POST['tanggal_bimbingan'] ?? '';
    $jam_bimbingan = $_POST['jam_bimbingan'] ?? '';
    $lokasi_bimbingan = trim($_POST['lokasi_bimbingan'] ?? '');
    $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
    $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
    
    // Combine tanggal and jam
    $tanggal_jam_bimbingan = $tanggal_bimbingan . ' ' . $jam_bimbingan;
    
    // DEBUG: Cek data yang diterima
    // var_dump($_POST); die();

    if (empty($klien_id) || empty($judul_bimbingan) || empty($materi_bimbingan) || empty($tanggal_bimbingan) || empty($jam_bimbingan)) {
        $debug_info = [];
        if (empty($klien_id)) $debug_info[] = "Klien ID kosong ($klien_id)";
        if (empty($judul_bimbingan)) $debug_info[] = "Judul kosong";
        if (empty($materi_bimbingan)) $debug_info[] = "Materi kosong";
        if (empty($tanggal_bimbingan)) $debug_info[] = "Tanggal kosong";
        if (empty($jam_bimbingan)) $debug_info[] = "Jam kosong";
        
        $message = 'Semua field wajib harus diisi! Detail: ' . implode(', ', $debug_info);
        $message_type = 'error';
    } elseif ($jenis_bimbingan === 'daring' && empty($link_meeting)) {
        $message = 'Link meeting/Nomor WA wajib diisi untuk bimbingan daring!';
        $message_type = 'error';
    } elseif ($jenis_bimbingan === 'tatap_muka' && empty($lokasi_bimbingan)) {
        $message = 'Lokasi bimbingan wajib diisi untuk bimbingan tatap muka!';
        $message_type = 'error';
    } elseif (strtotime($tanggal_jam_bimbingan) < time()) {
        $message = 'Tanggal dan jam bimbingan tidak boleh di masa lalu!';
        $message_type = 'error';
    } else {
        // Verify klien belongs to this PK
        $stmt = $conn->prepare("SELECT id FROM klien_users WHERE id = ? AND pk_id = ?");
        $stmt->bind_param("ii", $klien_id, $pk_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $message = 'Klien tidak ditemukan!';
            $message_type = 'error';
        } else {
            $stmt->close();
            
            // Insert jadwal
            $stmt = $conn->prepare("INSERT INTO jadwal_bimbingan (
                pk_id, klien_id, data_klien_id, judul_bimbingan, materi_bimbingan,
                jenis_bimbingan, link_meeting,
                tanggal_bimbingan, lokasi_bimbingan, latitude, longitude, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'terjadwal')");
            
            $stmt->bind_param("iiissssssdd", 
                $pk_id, 
                $klien_id, 
                $data_klien_id,
                $judul_bimbingan, 
                $materi_bimbingan,
                $jenis_bimbingan,
                $link_meeting,
                $tanggal_jam_bimbingan,
                $lokasi_bimbingan,
                $latitude,
                $longitude
            );
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = 'Jadwal bimbingan berhasil dibuat!';
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $message = 'Gagal membuat jadwal: ' . $conn->error;
                $message_type = 'error';
            }
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

// Get existing jadwal
$jadwal_list = $conn->query("
    SELECT jb.*, ku.nama as klien_nama, ku.no_registrasi
    FROM jadwal_bimbingan jb
    JOIN klien_users ku ON jb.klien_id = ku.id
    WHERE jb.pk_id = $pk_id
    ORDER BY jb.tanggal_bimbingan DESC
    LIMIT 20
")->fetch_all(MYSQLI_ASSOC);

// Connection will be closed automatically at script end or we keep it open for sidebar
// $conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Jadwal Bimbingan - BAPAS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        #map {
            height: 350px;
            border-radius: 0.75rem; /* rounded-xl */
            z-index: 0;
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
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-800 min-h-screen selection:bg-sky-100 selection:text-sky-700">
    <div class="fixed inset-0 -z-10 pointer-events-none overflow-hidden">
        <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] rounded-full bg-sky-400/10 blur-[100px] animate-blob"></div>
        <div class="absolute top-[20%] right-[-10%] w-[30%] h-[30%] rounded-full bg-blue-400/10 blur-[100px] animate-blob animation-delay-2000"></div>
        <div class="absolute bottom-[-10%] left-[20%] w-[35%] h-[35%] rounded-full bg-cyan-400/10 blur-[100px] animate-blob animation-delay-4000"></div>
    </div>
    
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/topbar.php'; ?>

    <div class="p-4 sm:ml-64 mt-14">
        <div class="max-w-7xl mx-auto fade-in">
            
            <!-- Header Banner -->
            <div class="glass-card rounded-3xl shadow-lg p-8 mb-8 relative overflow-hidden border-0">
                <div class="absolute top-0 right-0 w-64 h-64 bg-sky-50 rounded-full -mr-16 -mt-16 blur-3xl opacity-50"></div>
                
                <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-4">
                    <div>
                        <div class="inline-flex items-center space-x-2 bg-sky-50 px-3 py-1 rounded-full text-xs font-medium text-sky-600 border border-sky-100 mb-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-sky-500 animate-pulse"></span>
                            <span>Manajemen Jadwal</span>
                        </div>
                        <h1 class="text-2xl font-bold mb-1 text-gray-900">Buat Jadwal Bimbingan</h1>
                        <p class="text-gray-500 text-sm">Atur jadwal pertemuan dengan klien, baik tatap muka maupun daring, dengan mudah dan terintegrasi.</p>
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

            <div class="grid lg:grid-cols-3 gap-8 fade-in-up" style="animation-delay: 0.1s;">
                <!-- Create Form -->
                <div class="lg:col-span-2">
                    <div class="glass-card rounded-3xl p-8 shadow-xl relative overflow-hidden group/form">
                        <div class="absolute top-0 left-0 w-1.5 h-full bg-sky-500"></div>
                        <h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                            <span class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center mr-3 shadow-sm">
                                <i class="fas fa-plus"></i>
                            </span>
                            Form Jadwal Baru
                        </h3>

                        <form method="POST" class="space-y-6">
                            <div class="grid md:grid-cols-2 gap-6">
                                <div class="group/input">
                                    <label class="block text-sm font-bold text-gray-700 mb-2 group-focus-within/input:text-sky-600 transition-colors">Pilih Klien <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <select name="klien_id" id="klien_id" required onchange="updateDataKlienId()"
                                                class="w-full pl-4 pr-10 py-3.5 border border-gray-200 rounded-xl focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 transition-all duration-300 appearance-none font-medium cursor-pointer shadow-sm group-hover/input:border-gray-300 bg-white/50 backdrop-blur-sm">
                                            <option value="">-- Pilih Klien --</option>
                                            <?php foreach ($klien_list as $klien): ?>
                                                <option value="<?php echo $klien['id']; ?>" 
                                                        data-klien-id="<?php echo $klien['data_klien_id']; ?>"
                                                        data-no-telp="<?php echo htmlspecialchars($klien['no_telepon'] ?? ''); ?>">
                                                    <?php echo htmlspecialchars($klien['nama'] . ' (' . $klien['no_registrasi'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-gray-400 group-focus-within/input:text-sky-500 transition-colors">
                                            <i class="fas fa-chevron-down text-xs"></i>
                                        </div>
                                        <input type="hidden" name="data_klien_id" id="data_klien_id" value="">
                                    </div>
                                </div>
                                <div class="group/input">
                                    <label class="block text-sm font-bold text-gray-700 mb-2 group-focus-within/input:text-sky-600 transition-colors">Jenis Bimbingan <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <select name="jenis_bimbingan" id="jenis_bimbingan" required onchange="toggleJenisBimbingan()"
                                                class="w-full pl-4 pr-10 py-3.5 border border-gray-200 rounded-xl focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 transition-all duration-300 appearance-none font-medium cursor-pointer shadow-sm group-hover/input:border-gray-300 bg-white/50 backdrop-blur-sm">
                                            <option value="tatap_muka">Tatap Muka (Offline)</option>
                                            <option value="daring">Daring (Online)</option>
                                        </select>
                                        <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-gray-400 group-focus-within/input:text-sky-500 transition-colors">
                                            <i class="fas fa-chevron-down text-xs"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Platform Selection for Daring -->
                            <div id="daring-container" class="hidden space-y-6 border-l-2 border-sky-200 pl-4 ml-1">
                                <div class="group/input">
                                    <label class="block text-sm font-bold text-gray-700 mb-2 group-focus-within/input:text-sky-600 transition-colors">Platform Daring <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <select name="sub_jenis_daring" id="sub_jenis_daring" onchange="toggleSubJenisDaring()"
                                                class="w-full pl-4 pr-10 py-3.5 border border-gray-200 rounded-xl focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 transition-all duration-300 appearance-none font-medium cursor-pointer shadow-sm group-hover/input:border-gray-300 bg-white/50 backdrop-blur-sm">
                                            <option value="meeting_link">Zoom / Google Meet / Link Lainnya</option>
                                            <option value="whatsapp">WhatsApp Video Call</option>
                                        </select>
                                        <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-gray-400 group-focus-within/input:text-sky-500 transition-colors">
                                            <i class="fas fa-chevron-down text-xs"></i>
                                        </div>
                                    </div>
                                </div>

                                <div id="meeting-url-container" class="group/input">
                                    <label class="block text-sm font-bold text-gray-700 mb-2 group-focus-within/input:text-sky-600 transition-colors">Link Meeting (Zoom/Google Meet) <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400 group-focus-within/input:text-sky-500 transition-colors">
                                            <i class="fas fa-link"></i>
                                        </span>
                                        <input type="url" name="meeting_url" id="meeting_url"
                                               class="w-full pl-11 pr-4 py-3.5 border border-gray-200 rounded-xl focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 transition-all duration-300 font-medium shadow-sm group-hover/input:border-gray-300 bg-white/50 backdrop-blur-sm"
                                               placeholder="https://meet.google.com/...">
                                    </div>
                                </div>

                                <div id="whatsapp-container" class="hidden group/input">
                                    <label class="block text-sm font-bold text-gray-700 mb-2 group-focus-within/input:text-sky-600 transition-colors">Nomor WhatsApp (Video Call) <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400 group-focus-within/input:text-sky-500 transition-colors">
                                            <i class="fab fa-whatsapp text-lg"></i>
                                        </span>
                                        <input type="tel" name="wa_number" id="wa_number"
                                               class="w-full pl-11 pr-4 py-3.5 border border-gray-200 rounded-xl focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 transition-all duration-300 font-medium shadow-sm group-hover/input:border-gray-300 bg-white/50 backdrop-blur-sm"
                                               placeholder="Contoh: 081234567890">
                                    </div>
                                    <p class="mt-2 text-xs text-gray-500">Nomor ini akan digunakan untuk membuat link WhatsApp (wa.me).</p>
                                </div>
                            </div>

                            <div class="group/input">
                                <label class="block text-sm font-bold text-gray-700 mb-2 group-focus-within/input:text-sky-600 transition-colors">Judul Bimbingan <span class="text-red-500">*</span></label>
                                <input type="text" name="judul_bimbingan" required
                                       class="w-full px-4 py-3.5 border border-gray-200 rounded-xl focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 transition-all duration-300 font-medium shadow-sm group-hover/input:border-gray-300 bg-white/50 backdrop-blur-sm"
                                       placeholder="Contoh: Bimbingan Keterampilan Kerja">
                            </div>

                            <div class="group/input">
                                <label class="block text-sm font-bold text-gray-700 mb-2 group-focus-within/input:text-sky-600 transition-colors">Materi Bimbingan <span class="text-red-500">*</span></label>
                                <textarea name="materi_bimbingan" required rows="3"
                                          class="w-full px-4 py-3.5 border border-gray-200 rounded-xl focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 transition-all duration-300 font-medium shadow-sm resize-none group-hover/input:border-gray-300 bg-white/50 backdrop-blur-sm"
                                          placeholder="Jelaskan materi yang akan dibimbing, topik pembahasan, dll."></textarea>
                            </div>

                            <div class="grid md:grid-cols-2 gap-6">
                                <div class="group/input">
                                    <label class="block text-sm font-bold text-gray-700 mb-2 group-focus-within/input:text-sky-600 transition-colors">Tanggal Bimbingan <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <input type="date" name="tanggal_bimbingan" required
                                               min="<?php echo date('Y-m-d'); ?>"
                                               class="w-full px-4 py-3.5 border border-gray-200 rounded-xl focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 transition-all duration-300 font-medium shadow-sm group-hover/input:border-gray-300 bg-white/50 backdrop-blur-sm">
                                    </div>
                                </div>
                                <div class="group/input">
                                    <label class="block text-sm font-bold text-gray-700 mb-2 group-focus-within/input:text-sky-600 transition-colors">Jam Bimbingan <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <input type="time" name="jam_bimbingan" required
                                               class="w-full px-4 py-3.5 border border-gray-200 rounded-xl focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 transition-all duration-300 font-medium shadow-sm group-hover/input:border-gray-300 bg-white/50 backdrop-blur-sm">
                                    </div>
                                </div>
                            </div>

                            <div id="lokasi-container" class="group/input">
                                <label class="block text-sm font-bold text-gray-700 mb-2 group-focus-within/input:text-sky-600 transition-colors">Lokasi Bimbingan (Pinpoint di Map) <span class="text-red-500">*</span></label>
                                <div class="relative bg-white/50 backdrop-blur-sm p-2 rounded-xl border border-gray-200 shadow-inner group-hover/input:border-gray-300 transition-colors">
                                    <div class="relative w-full h-64 mb-3 rounded-xl overflow-hidden shadow-sm border border-gray-200">
                                        <div id="map" class="absolute inset-0 z-0"></div>
                                        <button type="button" onclick="getCurrentLocation()" class="absolute top-3 right-3 z-[1000] bg-white px-3 py-2 rounded-lg shadow-md border border-slate-200 text-sky-600 font-bold text-xs hover:bg-sky-50 transition-all flex items-center group/btn">
                                            <i class="fas fa-location-arrow mr-2 group-hover/btn:animate-bounce"></i> Lokasi Saya
                                        </button>
                                    </div>
                                    <div class="space-y-3">
                                        <textarea name="lokasi_bimbingan" id="lokasi_bimbingan" rows="2"
                                                  class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all resize-none text-sm bg-white/50"
                                                  placeholder="Nama lokasi/alamat akan muncul otomatis saat memilih titik di peta..."></textarea>
                                        <div class="grid grid-cols-2 gap-4">
                                            <input type="text" name="latitude" id="latitude" readonly
                                                   class="w-full px-3 py-2 bg-gray-100 border border-gray-200 rounded-lg text-xs text-gray-600 font-mono" placeholder="Latitude">
                                            <input type="text" name="longitude" id="longitude" readonly
                                                   class="w-full px-3 py-2 bg-gray-100 border border-gray-200 rounded-lg text-xs text-gray-600 font-mono" placeholder="Longitude">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-4">
                                <button type="submit" 
                                        class="w-full bg-gradient-to-r from-sky-600 to-blue-600 hover:from-sky-700 hover:to-blue-700 text-white py-4 px-6 rounded-xl transition-all duration-300 font-bold text-lg shadow-lg shadow-sky-500/30 transform hover:-translate-y-1 hover:shadow-sky-500/40 flex items-center justify-center group">
                                    <span>Buat Jadwal Bimbingan</span>
                                    <i class="fas fa-calendar-plus ml-3 group-hover:scale-110 transition-transform"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Side Panel: Information / Tips -->
                <div class="lg:col-span-1 space-y-8">
                    <!-- Upcoming Schedule -->
                    <div class="glass-card rounded-3xl p-6 shadow-xl h-fit">
                        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-list-alt text-sky-500 mr-2"></i>
                            Jadwal Terakhir
                        </h3>
                        
                        <div class="space-y-4">
                            <?php if (empty($jadwal_list)): ?>
                                <div class="text-center py-8 bg-gray-50 rounded-2xl border border-dashed border-gray-200">
                                    <i class="fas fa-calendar-times text-gray-300 text-3xl mb-2"></i>
                                    <p class="text-gray-400 text-sm">Belum ada jadwal dibuat</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($jadwal_list as $jadwal): ?>
                                    <div class="p-4 rounded-2xl bg-white/50 border border-gray-100 hover:border-sky-200 hover:bg-sky-50/30 transition-all duration-300 group">
                                        <div class="flex justify-between items-start mb-2">
                                            <span class="text-xs font-bold px-2 py-1 rounded-md <?php echo $jadwal['jenis_bimbingan'] === 'daring' ? 'bg-cyan-100 text-cyan-600' : 'bg-blue-100 text-blue-600'; ?>">
                                                <?php echo $jadwal['jenis_bimbingan'] === 'daring' ? 'Daring' : 'Tatap Muka'; ?>
                                            </span>
                                            <span class="text-xs text-gray-500 font-medium">
                                                <?php echo date('d M Y, H:i', strtotime($jadwal['tanggal_bimbingan'])); ?>
                                            </span>
                                        </div>
                                        <h4 class="font-bold text-gray-800 text-sm mb-1 group-hover:text-sky-700 transition-colors"><?php echo htmlspecialchars($jadwal['klien_nama']); ?></h4>
                                        <p class="text-xs text-gray-500 line-clamp-2"><?php echo htmlspecialchars($jadwal['judul_bimbingan']); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Tips Card -->
                    <div class="bg-gradient-to-br from-sky-800 to-blue-900 rounded-3xl p-6 text-white shadow-lg relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-10 -mt-10 blur-2xl"></div>
                        <h3 class="font-bold text-lg mb-4 flex items-center relative z-10">
                            <i class="fas fa-lightbulb text-yellow-300 mr-2"></i>
                            Tips Bimbingan
                        </h3>
                        <ul class="space-y-3 text-sm text-slate-200 relative z-10">
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-400 mt-1 mr-2 text-xs"></i>
                                <span>Pastikan klien telah menyetujui waktu bimbingan.</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-400 mt-1 mr-2 text-xs"></i>
                                <span>Untuk daring, gunakan link meeting yang valid.</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-400 mt-1 mr-2 text-xs"></i>
                                <span>Lokasi tatap muka sebaiknya di tempat yang kondusif.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Data Klien Helper
        function updateDataKlienId() {
            const select = document.getElementById('klien_id');
            const selectedOption = select.options[select.selectedIndex];
            const dataKlienId = selectedOption.getAttribute('data-klien-id');
            const noTelp = selectedOption.getAttribute('data-no-telp');
            
            document.getElementById('data_klien_id').value = dataKlienId || '';
            
            // Auto-fill WA number if available
            const waInput = document.getElementById('wa_number');
            if (waInput && noTelp) {
                waInput.value = noTelp;
            } else if (waInput) {
                waInput.value = '';
            }
        }

        // Form Toggles
        function toggleJenisBimbingan() {
            const jenis = document.getElementById('jenis_bimbingan').value;
            const daringContainer = document.getElementById('daring-container');
            const lokasiContainer = document.getElementById('lokasi-container');
            
            if (jenis === 'daring') {
                daringContainer.classList.remove('hidden');
                lokasiContainer.classList.add('hidden'); 
                
                // Trigger sub jenis check
                toggleSubJenisDaring();

                // Remove required from location
                document.getElementById('lokasi_bimbingan').removeAttribute('required');
            } else {
                daringContainer.classList.add('hidden');
                lokasiContainer.classList.remove('hidden');
                
                // Add required to location
                document.getElementById('lokasi_bimbingan').setAttribute('required', 'required');
                document.querySelector('label[for="lokasi_bimbingan"]').innerHTML = 'Lokasi Bimbingan (Pinpoint di Map) <span class="text-red-500">*</span>';
                
                // Remove required from daring fields
                document.getElementById('meeting_url').removeAttribute('required');
                document.getElementById('wa_number').removeAttribute('required');
                
                // Resize map when shown and auto detect location
                if (typeof map !== 'undefined' && map) {
                    map.invalidateSize();
                    
                    // Auto detect location if not set
                    const lat = document.getElementById('latitude').value;
                    if (!lat) {
                        getCurrentLocation(true); // silent mode
                    }
                }
                
                // Backup resize after animation
                setTimeout(() => {
                    if (typeof map !== 'undefined' && map) {
                        map.invalidateSize();
                    }
                }, 500);
            }
        }

        function toggleSubJenisDaring() {
            const subJenis = document.getElementById('sub_jenis_daring').value;
            const meetingContainer = document.getElementById('meeting-url-container');
            const whatsappContainer = document.getElementById('whatsapp-container');
            
            if (subJenis === 'whatsapp') {
                meetingContainer.classList.add('hidden');
                whatsappContainer.classList.remove('hidden');
                
                document.getElementById('meeting_url').removeAttribute('required');
                document.getElementById('wa_number').setAttribute('required', 'required');
                
                // Update WA number if client selected
                updateDataKlienId();
            } else {
                meetingContainer.classList.remove('hidden');
                whatsappContainer.classList.add('hidden');
                
                document.getElementById('meeting_url').setAttribute('required', 'required');
                document.getElementById('wa_number').removeAttribute('required');
            }
        }

        // Initialize Map
        let map;
        let marker;

        function initMap() {
            // Default: Jakarta/Indonesia coords
            const defaultLat = -6.2088;
            const defaultLng = 106.8456;
            
            map = L.map('map').setView([defaultLat, defaultLng], 13);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            // Handle map click
            map.on('click', function(e) {
                updateMarker(e.latlng.lat, e.latlng.lng);
            });
            
            // Try to get user location on load
            getCurrentLocation(true);
        }

        function updateMarker(lat, lng) {
            if (marker) {
                marker.setLatLng([lat, lng]);
            } else {
                marker = L.marker([lat, lng], {draggable: true}).addTo(map);
                
                marker.on('dragend', function(event) {
                    const position = marker.getLatLng();
                    updateMarker(position.lat, position.lng);
                });
            }
            
            updateInputs(lat, lng);
            
            // Show loading state
            const lokasiInput = document.getElementById('lokasi_bimbingan');
            lokasiInput.value = 'Sedang mengambil data alamat...';
            lokasiInput.classList.add('animate-pulse');
            
            // Use local API proxy for reliable geocoding
            fetch(`../shared/api/reverse-geocode.php?lat=${lat}&lng=${lng}`)
                .then(response => response.json())
                .then(data => {
                    lokasiInput.classList.remove('animate-pulse');
                    // Check for success property (boolean) and address field
                    if(data.success && data.address) {
                        lokasiInput.value = data.address;
                    } else if (data.display_name) { // Fallback if API changes
                        lokasiInput.value = data.display_name;
                    } else {
                        console.warn('Geocoding result empty:', data);
                        lokasiInput.value = '';
                        lokasiInput.placeholder = 'Alamat tidak ditemukan, silakan ketik manual...';
                    }
                })
                .catch(err => {
                    lokasiInput.classList.remove('animate-pulse');
                    console.error('Geocoding error:', err);
                    lokasiInput.value = '';
                    lokasiInput.placeholder = 'Gagal memuat alamat, silakan ketik manual...';
                });
        }

        function updateInputs(lat, lng) {
            document.getElementById('latitude').value = lat.toFixed(6);
            document.getElementById('longitude').value = lng.toFixed(6);
        }

        function getCurrentLocation(silent = false) {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        
                        if (map) {
                            map.setView([lat, lng], 15);
                            updateMarker(lat, lng);
                        }
                    },
                    (error) => {
                        if (!silent) {
                            alert("Gagal mendapatkan lokasi: " + error.message);
                        }
                    }
                );
            } else {
                if (!silent) {
                    alert("Geolocation tidak didukung browser ini.");
                }
            }
        }

        // Start initialization
        document.addEventListener('DOMContentLoaded', function() {
            initMap();
        });
    </script>
</body>
</html>
