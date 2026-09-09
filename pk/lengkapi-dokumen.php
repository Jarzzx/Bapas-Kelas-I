<?php
require_once '../shared/config/database.php';
require_once '../shared/config/auth.php';
requirePKLogin();

$conn = getDBConnection();
$pk_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Get klien yang sudah melengkapi biodata
$klien_list = $conn->query("
    SELECT ku.id, ku.nama, ku.no_registrasi, ku.biodata_dilengkapi_at,
           dk.id as data_klien_id, dk.pasal_pidana, dk.no_registrasi_perkara, dk.tanggal_mulai_bimbingan, 
           dk.tanggal_akhir_bimbingan, dk.masa_bimbingan_tahun, dk.masa_bimbingan_bulan, 
           dk.masa_bimbingan_hari, dk.jenis_integrasi, dk.status, dk.dokumen_lengkap
    FROM klien_users ku
    LEFT JOIN data_klien dk ON ku.no_registrasi = dk.no_registrasi AND dk.pk_id = $pk_id
    WHERE ku.pk_id = $pk_id 
    AND ku.status_approval = 'approved'
    AND ku.biodata_lengkap = 'sudah'
    AND (dk.dokumen_lengkap IS NULL OR dk.dokumen_lengkap != 'sudah')
    ORDER BY ku.biodata_dilengkapi_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['klien_id'])) {
    $klien_id = (int)$_POST['klien_id'];
    $pasal_pidana = trim($_POST['pasal_pidana'] ?? '');
    $no_registrasi_perkara = trim($_POST['no_registrasi_perkara'] ?? '');
    $tanggal_mulai = $_POST['tanggal_mulai_bimbingan'] ?? '';
    $tanggal_akhir = $_POST['tanggal_akhir_bimbingan'] ?? '';
    $jenis_integrasi = $_POST['jenis_integrasi'] ?? '';
    $status_klien = $_POST['status_klien'] ?? 'Aktif';
    
    // Get klien info
    $stmt = $conn->prepare("SELECT no_registrasi, nama FROM klien_users WHERE id = ? AND pk_id = ?");
    $stmt->bind_param("ii", $klien_id, $pk_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $klien_info = $result->fetch_assoc();
    $stmt->close();
    
    if (!$klien_info) {
        $message = 'Klien tidak ditemukan!';
        $message_type = 'error';
    } elseif (empty($pasal_pidana) || empty($tanggal_mulai) || empty($tanggal_akhir)) {
        $message = 'Semua field harus diisi!';
        $message_type = 'error';
    } elseif (strtotime($tanggal_akhir) < strtotime($tanggal_mulai)) {
        $message = 'Tanggal akhir bimbingan tidak boleh lebih awal dari tanggal mulai!';
        $message_type = 'error';
    } else {
        // Calculate masa bimbingan
        $start = new DateTime($tanggal_mulai);
        $end = new DateTime($tanggal_akhir);
        $diff = $start->diff($end);
        
        $tahun = $diff->y;
        $bulan = $diff->m;
        $hari = $diff->d;
        
        // Check if data_klien exists
        $stmt = $conn->prepare("SELECT id FROM data_klien WHERE no_registrasi = ? AND pk_id = ?");
        $stmt->bind_param("si", $klien_info['no_registrasi'], $pk_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data_klien = $result->fetch_assoc();
        $stmt->close();
        
        if ($data_klien) {
            // Update existing
            $stmt = $conn->prepare("UPDATE data_klien SET 
                pasal_pidana = ?,
                no_registrasi_perkara = ?,
                tanggal_mulai_bimbingan = ?,
                tanggal_akhir_bimbingan = ?,
                masa_bimbingan_tahun = ?,
                masa_bimbingan_bulan = ?,
                masa_bimbingan_hari = ?,
                jenis_integrasi = ?,
                status = ?,
                dokumen_lengkap = 'sudah',
                updated_at = NOW()
                WHERE id = ? AND pk_id = ?");
            $stmt->bind_param("ssssiiissii", 
                $pasal_pidana, 
                $no_registrasi_perkara,
                $tanggal_mulai, 
                $tanggal_akhir,
                $tahun,
                $bulan,
                $hari,
                $jenis_integrasi,
                $status_klien,
                $data_klien['id'],
                $pk_id
            );
        } else {
            // Insert new
            $stmt = $conn->prepare("INSERT INTO data_klien (
                pk_id, nama, no_registrasi, 
                pasal_pidana, no_registrasi_perkara, tanggal_mulai_bimbingan, tanggal_akhir_bimbingan,
                masa_bimbingan_tahun, masa_bimbingan_bulan, masa_bimbingan_hari,
                jenis_integrasi, status, dokumen_lengkap, tanggal_mulai
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'sudah', ?)");
            $stmt->bind_param("issssssiiisss", 
                $pk_id,
                $klien_info['nama'],
                $klien_info['no_registrasi'],
                $pasal_pidana, 
                $no_registrasi_perkara,
                $tanggal_mulai, 
                $tanggal_akhir,
                $tahun,
                $bulan,
                $hari,
                $jenis_integrasi,
                $status_klien,
                $tanggal_mulai
            );
        }
        
        if ($stmt->execute()) {
            $message = 'Dokumen berhasil dilengkapi! Masa bimbingan: ' . $tahun . ' tahun ' . $bulan . ' bulan ' . $hari . ' hari.';
            $message_type = 'success';
            // Refresh list
            $klien_list = $conn->query("
                SELECT ku.id, ku.nama, ku.no_registrasi, ku.biodata_dilengkapi_at,
                       dk.id as data_klien_id, dk.pasal_pidana, dk.tanggal_mulai_bimbingan, 
                       dk.tanggal_akhir_bimbingan, dk.masa_bimbingan_tahun, dk.masa_bimbingan_bulan, 
                       dk.masa_bimbingan_hari, dk.jenis_integrasi, dk.status, dk.dokumen_lengkap
                FROM klien_users ku
                LEFT JOIN data_klien dk ON ku.no_registrasi = dk.no_registrasi AND dk.pk_id = $pk_id
                WHERE ku.pk_id = $pk_id 
                AND ku.status_approval = 'approved'
                AND ku.biodata_lengkap = 'sudah'
                AND (dk.dokumen_lengkap IS NULL OR dk.dokumen_lengkap != 'sudah')
                ORDER BY ku.biodata_dilengkapi_at DESC
            ")->fetch_all(MYSQLI_ASSOC);
        } else {
            $message = 'Gagal menyimpan dokumen: ' . $conn->error;
            $message_type = 'error';
        }
        // $stmt->close(); // Keep statement/connection open if needed later or close specifically
        $stmt->close();
    }
    // $conn->close(); // Keep connection open for sidebar
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lengkapi Dokumen Klien - BAPAS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../shared/js/sweetalert-loader.js"></script>
    <script src="../shared/js/auto-notification-refresh.js"></script>
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
        /* Glassmorphism Utilities */
        .glass-card {
            background: rgba(255, 255, 255, 0.8);
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
        <div class="max-w-5xl mx-auto fade-in">
            
            <?php 
            // Handle URL success message
            if (isset($_GET['success']) && isset($_GET['msg'])) {
                $message = urldecode($_GET['msg']);
                $message_type = $_GET['success'] == '1' ? 'success' : 'error';
            }
            ?>
            <?php if (isset($message) && $message): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        <?php if ($message_type === 'success'): ?>
                            showSuccess('<?php echo addslashes($message); ?>');
                        <?php else: ?>
                            showError('<?php echo addslashes($message); ?>');
                        <?php endif; ?>
                    });
                </script>
            <?php endif; ?>

            <!-- Header Banner -->
            <div class="rounded-3xl p-8 mb-8 relative overflow-hidden text-white shadow-xl group">
                <!-- Background with gradient and shapes -->
                <div class="absolute inset-0 bg-gradient-to-r from-sky-600 to-blue-700 z-0"></div>
                <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -mr-16 -mt-16 blur-3xl transform group-hover:scale-110 transition-transform duration-700"></div>
                <div class="absolute bottom-0 left-0 w-40 h-40 bg-sky-400/20 rounded-full -ml-10 -mb-10 blur-2xl"></div>
                
                <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-4">
                    <div>
                        <div class="inline-flex items-center space-x-2 bg-white/20 backdrop-blur-md px-3 py-1 rounded-full text-xs font-semibold border border-white/20 mb-2 shadow-sm">
                            <span class="w-2 h-2 rounded-full bg-sky-300 animate-pulse"></span>
                            <span>Administrasi Klien</span>
                        </div>
                        <h1 class="text-2xl font-bold mb-1 tracking-tight">Lengkapi Dokumen Klien</h1>
                        <p class="text-sky-100 text-sm max-w-xl leading-relaxed">Kelola data administratif dan masa bimbingan klien pemasyarakatan.</p>
                    </div>
                    <div class="hidden md:block">
                        <div class="w-16 h-16 bg-white/20 backdrop-blur-md rounded-2xl flex items-center justify-center border border-white/20 shadow-lg transform rotate-3 group-hover:rotate-6 transition-transform duration-500">
                            <i class="fas fa-file-signature text-3xl text-white"></i>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (empty($klien_list)): ?>
                <div class="text-center py-16 glass-card rounded-3xl shadow-sm border border-dashed border-gray-300">
                    <div class="w-24 h-24 bg-sky-50 rounded-full flex items-center justify-center mx-auto mb-6 text-sky-300">
                        <i class="fas fa-check-circle text-4xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">Semua Dokumen Lengkap</h3>
                    <p class="text-gray-500 max-w-md mx-auto">Tidak ada klien yang memerlukan kelengkapan dokumen saat ini.</p>
                </div>
            <?php else: ?>
                <div class="space-y-8">
                    <?php foreach ($klien_list as $klien): ?>
                        <div class="glass-card rounded-3xl p-6 shadow-xl hover:shadow-2xl transition-all duration-300 group relative overflow-hidden">
                            <div class="absolute top-0 right-0 w-24 h-24 bg-gradient-to-br from-sky-50 to-blue-50 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110 duration-500"></div>
                            
                            <div class="flex flex-col md:flex-row justify-between items-start mb-8 relative z-10">
                                <div class="flex items-start space-x-4">
                                    <div class="w-12 h-12 rounded-2xl bg-sky-50 border border-sky-100 flex items-center justify-center flex-shrink-0 text-sky-600 shadow-sm group-hover:scale-110 transition-transform duration-300">
                                        <span class="text-lg font-bold"><?php echo substr($klien['nama'], 0, 1); ?></span>
                                    </div>
                                    <div>
                                        <h3 class="text-xl font-bold text-gray-800 mb-1 group-hover:text-sky-600 transition-colors"><?php echo htmlspecialchars($klien['nama']); ?></h3>
                                        <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                                            <i class="fas fa-id-card text-gray-400"></i>
                                            <span><?php echo htmlspecialchars($klien['no_registrasi']); ?></span>
                                        </div>
                                        <div class="flex items-center gap-2 text-xs text-gray-400">
                                            <i class="fas fa-clock"></i>
                                            <span>Biodata: <?php echo date('d/m/Y H:i', strtotime($klien['biodata_dilengkapi_at'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-4 md:mt-0">
                                    <?php if ($klien['dokumen_lengkap'] === 'sudah'): ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full bg-sky-50 text-sky-700 text-xs font-semibold border border-sky-100">
                                            <span class="w-1.5 h-1.5 rounded-full bg-sky-500 mr-2 animate-pulse"></span>
                                            Dokumen Lengkap
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full bg-amber-50 text-amber-700 text-xs font-semibold border border-amber-100">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 mr-2 animate-pulse"></span>
                                            Belum Lengkap
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <form method="POST" class="space-y-6 relative z-10" onsubmit="return handleFormSubmit(this)">
                                <input type="hidden" name="klien_id" value="<?php echo $klien['id']; ?>">
                                
                                <div class="grid md:grid-cols-2 gap-6">
                                    <div class="md:col-span-2 group/input">
                                        <label class="block text-sm font-bold text-gray-700 mb-2 group-focus-within/input:text-sky-600 transition-colors">
                                            Pasal Pidana <span class="text-red-500">*</span>
                                        </label>
                                        <textarea name="pasal_pidana" required rows="2"
                                                  class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 transition-all duration-300 resize-none bg-white/50 focus:bg-white text-gray-700 placeholder-gray-400"
                                                  placeholder="Contoh: Pasal 362 KUHP tentang Pencurian"><?php echo htmlspecialchars($klien['pasal_pidana'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="group/input">
                                        <label class="block text-sm font-bold text-gray-700 mb-2 group-focus-within/input:text-sky-600 transition-colors">
                                            No. Registrasi Perkara (Asli)
                                        </label>
                                        <input type="text" name="no_registrasi_perkara" 
                                               class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 transition-all duration-300 bg-white/50 focus:bg-white text-gray-700 placeholder-gray-400"
                                               placeholder="Contoh: 123/Pid.B/2026/PN Pbr"
                                               value="<?php echo htmlspecialchars($klien['no_registrasi_perkara'] ?? ''); ?>">
                                    </div>

                                    <div class="group/input">
                                        <label class="block text-sm font-bold text-gray-700 mb-2 group-focus-within/input:text-sky-600 transition-colors">
                                            Status Klien <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <select name="status_klien" required
                                                    class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 transition-all duration-300 appearance-none bg-white/50 focus:bg-white cursor-pointer font-medium text-gray-700">
                                                <option value="Aktif" <?php echo ($klien['status'] ?? 'Aktif') === 'Aktif' ? 'selected' : ''; ?>>Aktif</option>
                                                <option value="Selesai" <?php echo ($klien['status'] ?? '') === 'Selesai' ? 'selected' : ''; ?>>Selesai</option>
                                                <option value="Dicabut" <?php echo ($klien['status'] ?? '') === 'Dicabut' ? 'selected' : ''; ?>>Dicabut</option>
                                            </select>
                                            <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-gray-500">
                                                <i class="fas fa-chevron-down text-xs"></i>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="group/input">
                                        <label class="block text-sm font-bold text-gray-700 mb-2 group-focus-within/input:text-sky-600 transition-colors">
                                            Tanggal Mulai Bimbingan <span class="text-red-500">*</span>
                                        </label>
                                        <input type="date" name="tanggal_mulai_bimbingan" required
                                               class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 transition-all duration-300 bg-white/50 focus:bg-white text-gray-700"
                                               value="<?php echo $klien['tanggal_mulai_bimbingan'] ?? ''; ?>">
                                    </div>

                                    <div class="group/input">
                                        <label class="block text-sm font-bold text-gray-700 mb-2 group-focus-within/input:text-sky-600 transition-colors">
                                            Tanggal Akhir Bimbingan <span class="text-red-500">*</span>
                                        </label>
                                        <input type="date" name="tanggal_akhir_bimbingan" required
                                               class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 transition-all duration-300 bg-white/50 focus:bg-white text-gray-700"
                                               value="<?php echo $klien['tanggal_akhir_bimbingan'] ?? ''; ?>">
                                    </div>

                                    <div class="md:col-span-2 group/input">
                                        <label class="block text-sm font-bold text-gray-700 mb-2 group-focus-within/input:text-sky-600 transition-colors">
                                            Jenis Integrasi <span class="text-red-500">*</span>
                                        </label>
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                            <?php
                                            $integrasi_opts = ['PB', 'CB', 'CMB'];
                                            foreach ($integrasi_opts as $opt):
                                                $checked = ($klien['jenis_integrasi'] ?? 'PB') === $opt ? 'checked' : '';
                                            ?>
                                            <label class="cursor-pointer relative">
                                                <input type="radio" name="jenis_integrasi" value="<?php echo $opt; ?>" <?php echo $checked; ?> class="peer sr-only">
                                                <div class="p-3 text-center rounded-xl border border-gray-200 bg-white/50 hover:bg-sky-50 peer-checked:bg-sky-50 peer-checked:border-sky-500 peer-checked:text-sky-700 transition-all duration-200">
                                                    <span class="font-bold text-sm"><?php echo $opt; ?></span>
                                                </div>
                                            </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-end pt-4">
                                    <button type="submit" class="w-full md:w-auto px-8 py-3.5 bg-gradient-to-r from-sky-600 to-blue-600 hover:from-sky-700 hover:to-blue-700 text-white rounded-xl font-bold shadow-lg shadow-sky-500/30 transform hover:-translate-y-0.5 transition-all duration-300 flex items-center justify-center">
                                        <i class="fas fa-save mr-2"></i>
                                        Simpan Data
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script>
        function handleFormSubmit(form) {
            const btn = form.querySelector('button[type="submit"]');
            const originalContent = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Menyimpan...';
            btn.disabled = true;
            return true;
        }
    </script>
</body>
</html>