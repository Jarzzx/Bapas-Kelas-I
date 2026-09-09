<?php
// Enable Error Reporting for Debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../shared/config/database.php';
require_once '../shared/config/auth.php';
requirePKLogin();

$conn = getDBConnection();
$pk_id = $_SESSION['user_id'];
$nama = $_SESSION['nama'];

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    try {
        $klien_id = $_POST['klien_id'] ?? 0;
        $status_klien = $_POST['status_klien'] ?? '';
        $catatan = $_POST['catatan'] ?? '';
        
        // Handle File Upload
        $foto_dokumentasi = null;
        if (isset($_FILES['foto_dokumentasi'])) {
            if ($_FILES['foto_dokumentasi']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/dokumentasi_pengawasan/';
                
                // Ensure directory exists with correct permissions
                if (!file_exists($upload_dir)) {
                    if (!mkdir($upload_dir, 0755, true)) {
                        throw new Exception("Gagal membuat folder upload. Periksa permission server.");
                    }
                }
                
                $file_ext = strtolower(pathinfo($_FILES['foto_dokumentasi']['name'], PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($file_ext, $allowed_ext)) {
                    $new_filename = uniqid('doc_pengawasan_') . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['foto_dokumentasi']['tmp_name'], $upload_path)) {
                        $foto_dokumentasi = 'uploads/dokumentasi_pengawasan/' . $new_filename;
                    } else {
                        throw new Exception("Gagal memindahkan file upload. Periksa permission folder uploads.");
                    }
                } else {
                    throw new Exception("Format file tidak didukung! Gunakan JPG, PNG, atau GIF.");
                }
            } elseif ($_FILES['foto_dokumentasi']['error'] !== UPLOAD_ERR_NO_FILE) {
                // Handle specific upload errors
                switch ($_FILES['foto_dokumentasi']['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                        throw new Exception("Ukuran file terlalu besar (melebihi upload_max_filesize).");
                    case UPLOAD_ERR_FORM_SIZE:
                        throw new Exception("Ukuran file terlalu besar (melebihi MAX_FILE_SIZE form).");
                    default:
                        throw new Exception("Gagal upload file. Kode Error: " . $_FILES['foto_dokumentasi']['error']);
                }
            }
        }
    
        $nomor_sk = $_POST['nomor_sk'] ?? '';
        $tanggal_sk = !empty($_POST['tanggal_sk']) ? $_POST['tanggal_sk'] : null;
        $nomor_litmas = $_POST['nomor_litmas'] ?? '';
        $tanggal_litmas = !empty($_POST['tanggal_litmas']) ? $_POST['tanggal_litmas'] : null;
        $simpulan = $_POST['simpulan'] ?? '';
        $saran = $_POST['saran'] ?? '';
        $judul_laporan = $_POST['judul_laporan'] ?? '';
        $dasar_hukum = $_POST['dasar_hukum'] ?? '';
        $tujuan_laporan = $_POST['tujuan_laporan'] ?? '';
        $ruang_lingkup = $_POST['ruang_lingkup'] ?? '';
        $tempat_ttd = $_POST['tempat_ttd'] ?? 'Pekanbaru';
        $observasi = $_POST['observasi'] ?? '';
        $wawancara = $_POST['wawancara'] ?? '';
        $koordinasi = $_POST['koordinasi'] ?? '';
        
        $ringkasan_parts = [];
        if (!empty(trim($observasi))) $ringkasan_parts[] = "Observasi:\n" . trim($observasi);
        if (!empty(trim($wawancara))) $ringkasan_parts[] = "Wawancara:\n" . trim($wawancara);
        if (!empty(trim($koordinasi))) $ringkasan_parts[] = "Koordinasi:\n" . trim($koordinasi);
        $isi_laporan = implode("\n\n", $ringkasan_parts);
        
        $query = "INSERT INTO laporan_pengawasan (pk_id, klien_id, isi_laporan, status_klien, catatan, foto_dokumentasi, nomor_sk, tanggal_sk, nomor_litmas, tanggal_litmas, simpulan, saran, judul_laporan, dasar_hukum, tujuan_laporan, ruang_lingkup, tempat_ttd, observasi, wawancara, koordinasi) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Database Error (Prepare): " . $conn->error);
        }
        
        $stmt->bind_param("iissssssssssssssssss", $pk_id, $klien_id, $isi_laporan, $status_klien, $catatan, $foto_dokumentasi, $nomor_sk, $tanggal_sk, $nomor_litmas, $tanggal_litmas, $simpulan, $saran, $judul_laporan, $dasar_hukum, $tujuan_laporan, $ruang_lingkup, $tempat_ttd, $observasi, $wawancara, $koordinasi);
        
        if ($stmt->execute()) {
            $message = 'Laporan pengawasan berhasil dibuat!';
            $message_type = 'success';
            // Reset form data
            $klien_id = 0;
            $isi_laporan = '';
            $status_klien = '';
            $catatan = '';
        } else {
            throw new Exception("Gagal menyimpan ke database: " . $stmt->error);
        }
        $stmt->close();
    
    } catch (Exception $e) {
        $message = 'Terjadi Kesalahan: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get clients list
$klien_list = $conn->query("SELECT id, nama, no_registrasi FROM data_klien WHERE pk_id = $pk_id AND status = 'Aktif' ORDER BY nama");

// Get all reports
$laporan_list = $conn->query("
    SELECT lp.*, dk.nama as klien_nama, dk.no_registrasi 
    FROM laporan_pengawasan lp
    JOIN data_klien dk ON lp.klien_id = dk.id
    WHERE lp.pk_id = $pk_id
    ORDER BY lp.tanggal_laporan DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pengawasan - BAPAS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- DataTables & jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.tailwindcss.css">
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.tailwindcss.js"></script>
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
        /* Glassmorphism & Custom Scrollbar */
        .glass-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        .glass-header {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        /* DataTables Customization - FORCE OVERRIDE */
        
        /* Table Header */
        table.dataTable thead th {
            background-color: #f0f9ff !important; /* sky-50 */
            color: #0369a1 !important; /* sky-700 */
            font-weight: 700 !important;
            border-bottom: 1px solid #bae6fd !important; /* sky-200 */
            text-transform: uppercase;
            font-size: 0.75rem; /* text-xs */
        }

        /* Table Body */
        table.dataTable tbody tr {
            background-color: white !important;
            border-bottom: 1px solid #f1f5f9 !important; /* slate-100 */
        }
        
        table.dataTable tbody tr.even {
            background-color: #f8fafc !important; /* slate-50 */
        }

        table.dataTable tbody tr:hover {
            background-color: #e0f2fe !important; /* sky-100 */
        }
        
        /* Pagination Buttons */
        div.dt-container .dt-paging .dt-paging-button,
        div.dt-container .dt-paging button.dt-paging-button,
        div.dt-container .dt-paging a.dt-paging-button,
        div.dt-container .dt-paging li.dt-paging-button,
        div.dt-container .dt-paging button,
        div.dt-container .dt-paging a {
            background: white !important;
            color: #0ea5e9 !important; /* sky-500 */
            border: 1px solid #e2e8f0 !important; /* slate-200 */
            border-radius: 0.5rem !important;
            font-weight: 600 !important;
            margin: 0 2px !important;
        }
        
        div.dt-container .dt-paging .dt-paging-button:hover,
        div.dt-container .dt-paging button.dt-paging-button:hover,
        div.dt-container .dt-paging a.dt-paging-button:hover,
        div.dt-container .dt-paging li.dt-paging-button:hover,
        div.dt-container .dt-paging button:hover,
        div.dt-container .dt-paging a:hover {
            background: #e0f2fe !important; /* sky-100 */
            color: #0284c7 !important; /* sky-600 */
            border-color: #bae6fd !important; /* sky-200 */
        }
        
        div.dt-container .dt-paging .dt-paging-button.current,
        div.dt-container .dt-paging button.dt-paging-button.current,
        div.dt-container .dt-paging a.dt-paging-button.current,
        div.dt-container .dt-paging li.dt-paging-button.current,
        div.dt-container .dt-paging button[aria-current="page"] {
            background: linear-gradient(to right, #0ea5e9, #0284c7) !important;
            color: white !important;
            border: none !important;
            box-shadow: 0 4px 6px -1px rgba(14, 165, 233, 0.3);
        }
        
        div.dt-container .dt-paging .dt-paging-button.disabled,
        div.dt-container .dt-paging button.dt-paging-button.disabled,
        div.dt-container .dt-paging a.dt-paging-button.disabled,
        div.dt-container .dt-paging li.dt-paging-button.disabled,
        div.dt-container .dt-paging button[disabled],
        div.dt-container .dt-paging a[aria-disabled="true"] {
            color: #cbd5e1 !important; /* slate-300 */
            cursor: not-allowed !important;
            background: #f8fafc !important; /* light disabled background */
            border-color: #e2e8f0 !important;
        }

        /* Search & Length Inputs */
        div.dt-container .dt-search input,
        div.dt-container .dt-length select {
            border: 1px solid #e2e8f0 !important;
            border-radius: 0.5rem !important;
            padding: 0.5rem 1rem !important;
            color: #475569 !important; /* slate-600 */
            background-color: white !important;
        }
        
        div.dt-container .dt-search input:focus,
        div.dt-container .dt-length select:focus {
            border-color: #0ea5e9 !important; /* sky-500 */
            box-shadow: 0 0 0 3px #e0f2fe !important; /* simulated ring */
            outline: none !important;
        }

        /* Info Text */
        div.dt-container .dt-info {
            color: #64748b !important; /* slate-500 */
            font-size: 0.875rem !important;
        }
</style>
</head>
<body class="bg-slate-50 font-sans text-slate-800 min-h-screen selection:bg-sky-100 selection:text-sky-700">
    
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/topbar.php'; ?>

    <div class="p-4 sm:ml-64 mt-20">
        <div class="max-w-7xl mx-auto animate-fade-in-up">
            
            <!-- Header Banner -->
            <div class="rounded-3xl p-8 mb-8 relative overflow-hidden text-white shadow-xl group">
                <!-- Background with gradient and shapes -->
                <div class="absolute inset-0 bg-gradient-to-r from-sky-600 to-blue-700 z-0"></div>
                <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -mr-16 -mt-16 blur-3xl transform group-hover:scale-110 transition-transform duration-700"></div>
                <div class="absolute bottom-0 left-0 w-40 h-40 bg-sky-400/20 rounded-full -ml-10 -mb-10 blur-2xl"></div>
                
                <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-6">
                    <div>
                        <div class="inline-flex items-center space-x-2 bg-white/20 backdrop-blur-md px-4 py-1.5 rounded-full text-xs font-semibold border border-white/20 mb-3 shadow-sm">
                            <span class="w-2 h-2 rounded-full bg-sky-300 animate-pulse"></span>
                            <span>Monitoring & Evaluasi</span>
                        </div>
                        <h1 class="text-3xl font-bold mb-2 tracking-tight">Laporan Pengawasan</h1>
                        <p class="text-sky-100 text-sm max-w-xl leading-relaxed">Buat dan kelola laporan pengawasan klien untuk memantau perkembangan dan kepatuhan secara efektif.</p>
                    </div>
                    <div class="hidden md:block">
                        <div class="w-16 h-16 bg-white/20 backdrop-blur-md rounded-2xl flex items-center justify-center border border-white/20 shadow-lg transform rotate-3 group-hover:rotate-6 transition-transform duration-500">
                            <i class="fas fa-file-contract text-3xl text-white"></i>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="mb-8 p-5 rounded-2xl shadow-sm border-l-4 flex items-center <?php echo $message_type === 'success' ? 'bg-green-50 text-green-700 border-green-500' : 'bg-red-50 text-red-700 border-red-500'; ?> animate-fade-in-up">
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

            <div class="grid lg:grid-cols-3 gap-8 animate-fade-in-up" style="animation-delay: 0.1s;">
                <!-- Create Report Form -->
                <div class="lg:col-span-1">
                    <div class="glass-card rounded-3xl p-6 shadow-xl h-fit sticky top-24">
                        <div class="mb-6 flex items-center space-x-4 border-b border-gray-100 pb-4">
                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-sky-50 to-blue-50 text-sky-600 flex items-center justify-center shadow-sm">
                                <i class="fas fa-pen-fancy text-lg"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">Buat Laporan</h3>
                                <p class="text-xs text-gray-500">Isi form di bawah ini</p>
                            </div>
                        </div>

                        <form method="POST" action="" class="space-y-5" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="create">
                            
                            <div class="group/input">
                                <label class="block text-sm font-bold text-gray-700 mb-2 group-focus-within/input:text-sky-600 transition-colors">
                                    Pilih Klien <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <select name="klien_id" required
                                            class="w-full pl-4 pr-10 py-3.5 border border-gray-200 rounded-xl focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 bg-white/50 transition-all duration-300 appearance-none font-medium cursor-pointer shadow-sm group-hover/input:border-gray-300 text-sm">
                                        <option value="">-- Pilih Klien --</option>
                                        <?php if ($klien_list && $klien_list->num_rows > 0): ?>
                                            <?php while ($klien = $klien_list->fetch_assoc()): ?>
                                                <option value="<?php echo $klien['id']; ?>">
                                                    <?php echo htmlspecialchars($klien['nama']); ?> (<?php echo htmlspecialchars($klien['no_registrasi']); ?>)
                                                </option>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <option value="" disabled>Tidak ada klien aktif</option>
                                        <?php endif; ?>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-gray-400 group-focus-within/input:text-sky-500 transition-colors">
                                        <i class="fas fa-chevron-down text-xs"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="group/input">
                                <label class="block text-sm font-bold text-gray-700 mb-2 group-focus-within/input:text-sky-600 transition-colors">
                                    Status Klien
                                </label>
                                <div class="relative">
                                    <select name="status_klien"
                                            class="w-full pl-4 pr-10 py-3.5 border border-gray-200 rounded-xl focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 bg-white/50 transition-all duration-300 appearance-none font-medium cursor-pointer shadow-sm group-hover/input:border-gray-300 text-sm">
                                        <option value="Baik">Baik</option>
                                        <option value="Perlu Perhatian">Perlu Perhatian</option>
                                        <option value="Masalah">Masalah</option>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-gray-400 group-focus-within/input:text-sky-500 transition-colors">
                                        <i class="fas fa-chevron-down text-xs"></i>
                                    </div>
                                </div>
                            </div>
                            
                            

                            <div class="bg-slate-50 p-5 rounded-2xl border border-slate-100 space-y-4">
                                <h4 class="text-sm font-bold text-gray-800 flex items-center gap-2 border-b border-slate-200 pb-2">
                                    <i class="fas fa-tasks text-sky-500"></i> Pelaksanaan Kegiatan
                                </h4>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Observasi</label>
                                    <textarea name="observasi" rows="3" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-sky-500 focus:ring-sky-500" placeholder="Hasil observasi..."></textarea>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Wawancara</label>
                                    <textarea name="wawancara" rows="3" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-sky-500 focus:ring-sky-500" placeholder="Ringkasan wawancara..."></textarea>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Koordinasi</label>
                                    <textarea name="koordinasi" rows="3" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-sky-500 focus:ring-sky-500" placeholder="Langkah koordinasi..."></textarea>
                                </div>
                            </div>

                            <!-- Data Legalitas & Analisis -->
                            <div class="bg-slate-50 p-5 rounded-2xl border border-slate-100 space-y-5">
                                <h4 class="text-sm font-bold text-gray-800 flex items-center gap-2 border-b border-slate-200 pb-2">
                                    <i class="fas fa-file-contract text-sky-500"></i> Data Laporan Akhir
                                </h4>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Judul Laporan</label>
                                        <input type="text" name="judul_laporan" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-sky-500 focus:ring-sky-500" placeholder="LAPORAN HASIL PENGAWASAN...">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Dasar Hukum / Pendahuluan Umum</label>
                                        <textarea name="dasar_hukum" rows="3" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-sky-500 focus:ring-sky-500" placeholder="Berdasarkan Peraturan Menteri..."></textarea>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Nomor SK</label>
                                        <input type="text" name="nomor_sk" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-sky-500 focus:ring-sky-500" placeholder="Contoh: PAS-123.PK...">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Tanggal SK</label>
                                        <input type="date" name="tanggal_sk" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-sky-500 focus:ring-sky-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Nomor Litmas</label>
                                        <input type="text" name="nomor_litmas" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-sky-500 focus:ring-sky-500" placeholder="Nomor Litmas">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Tanggal Litmas</label>
                                        <input type="date" name="tanggal_litmas" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-sky-500 focus:ring-sky-500">
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Tujuan Laporan</label>
                                        <textarea name="tujuan_laporan" rows="2" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-sky-500 focus:ring-sky-500" placeholder="Tujuan penyusunan laporan..."></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Ruang Lingkup</label>
                                        <textarea name="ruang_lingkup" rows="2" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-sky-500 focus:ring-sky-500" placeholder="Ruang lingkup pengawasan..."></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Simpulan</label>
                                        <textarea name="simpulan" rows="2" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-sky-500 focus:ring-sky-500" placeholder="Simpulan hasil pengawasan..."></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Saran/Rekomendasi</label>
                                        <textarea name="saran" rows="2" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-sky-500 focus:ring-sky-500" placeholder="Saran rekomendasi..."></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Tempat Tanda Tangan</label>
                                        <input type="text" name="tempat_ttd" value="Pekanbaru" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-sky-500 focus:ring-sky-500">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="group/input">
                                <label class="block text-sm font-bold text-gray-700 mb-2 group-focus-within/input:text-sky-600 transition-colors">
                                    Catatan Tambahan
                                </label>
                                <textarea name="catatan" rows="2"
                                          class="w-full px-4 py-3.5 border border-gray-200 rounded-xl focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 bg-white/50 transition-all duration-300 resize-none font-medium shadow-sm group-hover/input:border-gray-300 text-sm"
                                          placeholder="Catatan tambahan (opsional)..."></textarea>
                            </div>
                            
                            <div class="group/input">
                                <label class="block text-sm font-bold text-gray-700 mb-2 group-focus-within/input:text-sky-600 transition-colors">
                                    Foto Dokumentasi <span class="text-xs font-normal text-gray-400">(Opsional)</span>
                                </label>
                                <div class="relative">
                                    <input type="file" name="foto_dokumentasi" accept="image/jpeg,image/png,image/gif"
                                           class="w-full px-4 py-3.5 border border-gray-200 rounded-xl focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 bg-white/50 transition-all duration-300 font-medium shadow-sm group-hover/input:border-gray-300 text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-bold file:bg-sky-50 file:text-sky-600 hover:file:bg-sky-100 cursor-pointer">
                                </div>
                                <p class="mt-1 text-xs text-gray-400">Format: JPG, PNG, GIF. Maks: 2MB</p>
                            </div>
                            
                            <div class="bg-blue-50 border border-blue-100 p-4 rounded-xl flex items-start gap-3">
                                <div class="text-blue-600 mt-0.5">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                <p class="text-xs text-blue-600/90 leading-relaxed font-medium">
                                    Waktu laporan otomatis disimpan sesuai waktu server saat ini.
                                </p>
                            </div>
                            
                            <button type="submit" class="w-full bg-gradient-to-r from-sky-600 to-blue-600 hover:from-sky-700 hover:to-blue-700 text-white py-3.5 px-6 rounded-xl transition-all duration-300 font-bold shadow-lg shadow-sky-500/30 transform hover:-translate-y-1 hover:shadow-sky-500/40 flex items-center justify-center group">
                                <span>Simpan Laporan</span>
                                <i class="fas fa-save ml-2 group-hover:scale-110 transition-transform"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Reports List -->
                <div class="lg:col-span-2">
                    <div class="glass-card rounded-3xl shadow-xl overflow-hidden h-full flex flex-col">
                        <div class="p-6 border-b border-gray-100 bg-white/40 backdrop-blur-sm">
                            <h2 class="text-lg font-bold text-gray-800 flex items-center">
                                <div class="w-8 h-8 rounded-lg bg-sky-100 text-sky-600 flex items-center justify-center mr-3">
                                <i class="fas fa-history text-sm"></i>
                            </div>
                            Riwayat Laporan Pengawasan
                        </h2>
                    </div>
                        
                        <div class="p-6 flex-grow overflow-x-auto">
                            <table id="laporanPengawasanTable" class="w-full text-sm text-left whitespace-nowrap">
                                <thead class="text-xs text-slate-700 uppercase bg-sky-50/50">
                                    <tr>
                                        <th class="px-4 py-3 rounded-l-lg">No</th>
                                        <th class="px-4 py-3">Klien</th>
                                        <th class="px-4 py-3">Waktu</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3">Isi</th>
                                        <th class="px-4 py-3">Dokumentasi</th>
                                        <th class="px-4 py-3 rounded-r-lg">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php
                                    $no = 1;
                                    if ($laporan_list && $laporan_list->num_rows > 0) {
                                        while ($laporan = $laporan_list->fetch_assoc()) {
                                            $statusClasses = [
                                                'Baik' => 'bg-green-100 text-green-700',
                                                'Perlu Perhatian' => 'bg-yellow-100 text-yellow-700',
                                                'Masalah' => 'bg-red-100 text-red-700'
                                            ];
                                            $statusClass = isset($statusClasses[$laporan['status_klien']]) 
                                                ? $statusClasses[$laporan['status_klien']] 
                                                : 'bg-gray-100 text-gray-700';
                                    ?>
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="px-4 py-3 font-medium text-gray-500"><?php echo $no++; ?></td>
                                        <td class="px-4 py-3">
                                            <div class="font-bold text-gray-800"><?php echo htmlspecialchars($laporan['klien_nama']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($laporan['no_registrasi']); ?></div>
                                        </td>
                                        <td class="px-4 py-3 text-gray-600">
                                            <?php echo date('d M Y H:i', strtotime($laporan['tanggal_laporan'])); ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $statusClass; ?>">
                                                <?php echo htmlspecialchars($laporan['status_klien']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <p class="truncate w-48 text-gray-600" title="<?php echo htmlspecialchars($laporan['observasi'] ?? ($laporan['isi_laporan'] ?? '')); ?>">
                                                <?php echo htmlspecialchars($laporan['observasi'] ?? ($laporan['isi_laporan'] ?? '')); ?>
                                            </p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <?php if (!empty($laporan['foto_dokumentasi'])): ?>
                                                <a href="../<?php echo htmlspecialchars($laporan['foto_dokumentasi']); ?>" target="_blank" class="inline-flex items-center space-x-1 text-sky-600 hover:text-sky-800 bg-sky-50 px-2 py-1 rounded text-xs">
                                                    <i class="fas fa-image"></i>
                                                    <span>Lihat</span>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs italic">Tidak ada</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex gap-2">
                                                <a href="javascript:void(0)" onclick="checkAndPrint(this)"
                                           data-id="<?php echo $laporan['id']; ?>"
                                           data-nomor-sk="<?php echo htmlspecialchars($laporan['nomor_sk'] ?? ''); ?>"
                                           data-tanggal-sk="<?php echo htmlspecialchars($laporan['tanggal_sk'] ?? ''); ?>"
                                           data-nomor-litmas="<?php echo htmlspecialchars($laporan['nomor_litmas'] ?? ''); ?>"
                                           data-tanggal-litmas="<?php echo htmlspecialchars($laporan['tanggal_litmas'] ?? ''); ?>"
                                           data-simpulan="<?php echo htmlspecialchars($laporan['simpulan'] ?? ''); ?>"
                                           data-saran="<?php echo htmlspecialchars($laporan['saran'] ?? ''); ?>"
                                           data-judul-laporan="<?php echo htmlspecialchars($laporan['judul_laporan'] ?? ''); ?>"
                                           data-dasar-hukum="<?php echo htmlspecialchars($laporan['dasar_hukum'] ?? ''); ?>"
                                           data-tujuan-laporan="<?php echo htmlspecialchars($laporan['tujuan_laporan'] ?? ''); ?>"
                                           data-ruang-lingkup="<?php echo htmlspecialchars($laporan['ruang_lingkup'] ?? ''); ?>"
                                           data-tempat-ttd="<?php echo htmlspecialchars($laporan['tempat_ttd'] ?? 'Pekanbaru'); ?>"
                                           class="text-amber-600 hover:text-amber-800 hover:bg-amber-50 p-2 rounded-lg transition-colors" title="Cetak PDF">
                                            <i class="fas fa-print"></i>
                                        </a>
                                                <a href="view-laporan-pengawasan.php?id=<?php echo $laporan['id']; ?>" 
                                                   class="text-sky-600 hover:text-sky-800 hover:bg-sky-50 p-2 rounded-lg transition-colors" title="Lihat Detail">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Print Modal -->
    <div id="printModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closePrintModal()"></div>

            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form action="cetak-laporan-pengawasan-pdf.php" method="POST" target="_blank" onsubmit="closePrintModal()">
                    <input type="hidden" name="id" id="print_laporan_id">
                    
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-sky-100 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="fas fa-print text-sky-600"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                    Lengkapi Data Cetak
                                </h3>
                                <div class="mt-2 text-sm text-gray-500">
                                    <p class="mb-4">Masukkan data legalitas untuk dicetak pada laporan ini.</p>
                                    
                                    <div class="space-y-3 text-left">
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-700 mb-1">Judul Laporan</label>
                                            <input type="text" name="judul_laporan" class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-sky-500 focus:ring-sky-500" placeholder="LAPORAN HASIL PENGAWASAN...">
                                        </div>

                                        <div>
                                            <label class="block text-xs font-semibold text-gray-700 mb-1">Dasar Hukum / Pendahuluan</label>
                                            <textarea name="dasar_hukum" rows="2" class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-sky-500 focus:ring-sky-500" placeholder="Dasar hukum..."></textarea>
                                        </div>

                                        <div class="grid grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-xs font-semibold text-gray-700 mb-1">Nomor SK</label>
                                                <input type="text" name="nomor_sk" class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-sky-500 focus:ring-sky-500" placeholder="Nomor SK">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold text-gray-700 mb-1">Tanggal SK</label>
                                                <input type="date" name="tanggal_sk" class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-sky-500 focus:ring-sky-500">
                                            </div>
                                        </div>
                                        
                                        <div class="grid grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-xs font-semibold text-gray-700 mb-1">Nomor Litmas</label>
                                                <input type="text" name="nomor_litmas" class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-sky-500 focus:ring-sky-500" placeholder="Nomor Litmas">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold text-gray-700 mb-1">Tanggal Litmas</label>
                                                <input type="date" name="tanggal_litmas" class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-sky-500 focus:ring-sky-500">
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-xs font-semibold text-gray-700 mb-1">Tujuan Laporan</label>
                                                <textarea name="tujuan_laporan" rows="2" class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-sky-500 focus:ring-sky-500" placeholder="Tujuan..."></textarea>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold text-gray-700 mb-1">Ruang Lingkup</label>
                                                <textarea name="ruang_lingkup" rows="2" class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-sky-500 focus:ring-sky-500" placeholder="Ruang lingkup..."></textarea>
                                            </div>
                                        </div>

                                        <div>
                                            <label class="block text-xs font-semibold text-gray-700 mb-1">Simpulan</label>
                                            <textarea name="simpulan" rows="2" class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-sky-500 focus:ring-sky-500" placeholder="Simpulan hasil pengawasan..."></textarea>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-700 mb-1">Saran/Rekomendasi</label>
                                            <textarea name="saran" rows="2" class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-sky-500 focus:ring-sky-500" placeholder="Saran rekomendasi..."></textarea>
                                        </div>

                                        <div>
                                            <label class="block text-xs font-semibold text-gray-700 mb-1">Tempat Tanda Tangan</label>
                                            <input type="text" name="tempat_ttd" class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-sky-500 focus:ring-sky-500">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-sky-600 text-base font-medium text-white hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Cetak PDF
                        </button>
                        <button type="button" onclick="closePrintModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#laporanPengawasanTable').DataTable({
                responsive: true,
                language: {
                    search: "Cari:",
                    lengthMenu: "Tampilkan _MENU_ data",
                    info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                    paginate: {
                        first: "Awal",
                        last: "Akhir",
                        next: "Lanjut",
                        previous: "Kembali"
                    }
                }
            });
        });

        function checkAndPrint(btn) {
            const id = btn.getAttribute('data-id');
            const nomorSk = btn.getAttribute('data-nomor-sk');
            const nomorLitmas = btn.getAttribute('data-nomor-litmas');
            const simpulan = btn.getAttribute('data-simpulan');
            const saran = btn.getAttribute('data-saran');
            const judulLaporan = btn.getAttribute('data-judul-laporan');
            
            // Check if essential fields are filled
            // Note: Tanggal fields might be empty if user didn't input them, but usually numbers are critical
            if (nomorSk && nomorLitmas && simpulan && saran && judulLaporan) {
                // All data present, print directly
                window.open(`cetak-laporan-pengawasan-pdf.php?id=${id}`, '_blank');
            } else {
                // Data missing, open modal
                openPrintModal(id, btn);
            }
        }

        function openPrintModal(id, btn) {
            document.getElementById('print_laporan_id').value = id;
            
            // Pre-fill existing data if any (so user only fills what's missing)
            if(btn) {
                document.querySelector('#printModal input[name="nomor_sk"]').value = btn.getAttribute('data-nomor-sk') || '';
                document.querySelector('#printModal input[name="tanggal_sk"]').value = btn.getAttribute('data-tanggal-sk') || '';
                document.querySelector('#printModal input[name="nomor_litmas"]').value = btn.getAttribute('data-nomor-litmas') || '';
                document.querySelector('#printModal input[name="tanggal_litmas"]').value = btn.getAttribute('data-tanggal-litmas') || '';
                document.querySelector('#printModal textarea[name="simpulan"]').value = btn.getAttribute('data-simpulan') || '';
                document.querySelector('#printModal textarea[name="saran"]').value = btn.getAttribute('data-saran') || '';
                document.querySelector('#printModal input[name="judul_laporan"]').value = btn.getAttribute('data-judul-laporan') || '';
                document.querySelector('#printModal textarea[name="dasar_hukum"]').value = btn.getAttribute('data-dasar-hukum') || '';
                document.querySelector('#printModal textarea[name="tujuan_laporan"]').value = btn.getAttribute('data-tujuan-laporan') || '';
                document.querySelector('#printModal textarea[name="ruang_lingkup"]').value = btn.getAttribute('data-ruang-lingkup') || '';
                document.querySelector('#printModal input[name="tempat_ttd"]').value = btn.getAttribute('data-tempat-ttd') || 'Pekanbaru';
            }
            
            document.getElementById('printModal').classList.remove('hidden');
        }

        function closePrintModal() {
            document.getElementById('printModal').classList.add('hidden');
        }
    </script>
</body>
</html>
