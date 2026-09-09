<?php
require_once '../shared/config/database.php';
require_once '../shared/config/auth.php';
requireKlienLogin();

$conn = getDBConnection();
$klien_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Check approval status
$stmt = $conn->prepare("SELECT status_approval FROM klien_users WHERE id = ?");
$stmt->bind_param("i", $klien_id);
$stmt->execute();
$result = $stmt->get_result();
$user_status = $result->fetch_assoc();
$stmt->close();

if ($user_status['status_approval'] !== 'approved') {
    header('Location: dashboard.php');
    exit;
}

// Get current biodata
$stmt = $conn->prepare("SELECT nik, foto_klien, riwayat_pendidikan, agama, jenis_kelamin, status_pernikahan, pekerjaan, tempat_lahir, tanggal_lahir, biodata_lengkap FROM klien_users WHERE id = ?");
$stmt->bind_param("i", $klien_id);
$stmt->execute();
$result = $stmt->get_result();
$biodata = $result->fetch_assoc();
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nik = trim($_POST['nik'] ?? '');
    $riwayat_pendidikan = trim($_POST['riwayat_pendidikan'] ?? '');
    $agama = trim($_POST['agama'] ?? '');
    $jenis_kelamin = trim($_POST['jenis_kelamin'] ?? '');
    $status_pernikahan = $_POST['status_pernikahan'] ?? '';
    $pekerjaan = trim($_POST['pekerjaan'] ?? '');
    $tempat_lahir = trim($_POST['tempat_lahir'] ?? '');
    $tanggal_lahir = $_POST['tanggal_lahir'] ?? '';
    
    // Handle file upload
    $foto_klien = $biodata['foto_klien'] ?? null;
    if (isset($_FILES['foto_klien']) && $_FILES['foto_klien']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/foto_klien/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = pathinfo($_FILES['foto_klien']['name'], PATHINFO_EXTENSION);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array(strtolower($file_extension), $allowed_extensions)) {
            $new_filename = 'klien_' . $klien_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['foto_klien']['tmp_name'], $upload_path)) {
                // Delete old photo if exists
                if ($foto_klien && file_exists('../' . $foto_klien)) {
                    unlink('../' . $foto_klien);
                }
                $foto_klien = 'uploads/foto_klien/' . $new_filename;
            }
        }
    }
    
    // Validation
    if (empty($nik) || empty($riwayat_pendidikan) || empty($agama) || empty($jenis_kelamin) || empty($status_pernikahan) || 
        empty($pekerjaan) || empty($tempat_lahir) || empty($tanggal_lahir)) {
        $message = 'Semua field harus diisi!';
        $message_type = 'error';
    } elseif (strlen($nik) < 16) {
        $message = 'NIK harus 16 digit!';
        $message_type = 'error';
    } else {
        // Update biodata
        $stmt = $conn->prepare("UPDATE klien_users SET 
            nik = ?, 
            foto_klien = ?, 
            riwayat_pendidikan = ?, 
            agama = ?, 
            jenis_kelamin = ?,
            status_pernikahan = ?, 
            pekerjaan = ?, 
            tempat_lahir = ?, 
            tanggal_lahir = ?,
            biodata_lengkap = 'sudah',
            biodata_dilengkapi_at = NOW()
            WHERE id = ?");
        
        // Bind parameters: 9 strings + 1 integer = 10 total
        $stmt->bind_param("sssssssssi", 
            $nik, 
            $foto_klien, 
            $riwayat_pendidikan, 
            $agama, 
            $jenis_kelamin,
            $status_pernikahan, 
            $pekerjaan, 
            $tempat_lahir, 
            $tanggal_lahir,
            $klien_id
        );
        
        if ($stmt->execute()) {
            $message = 'Biodata berhasil dilengkapi!';
            $message_type = 'success';
            // Close UPDATE statement first
            $stmt->close();
            // Refresh biodata
            $stmt = $conn->prepare("SELECT nik, foto_klien, riwayat_pendidikan, agama, jenis_kelamin, status_pernikahan, pekerjaan, tempat_lahir, tanggal_lahir, biodata_lengkap FROM klien_users WHERE id = ?");
            $stmt->bind_param("i", $klien_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $biodata = $result->fetch_assoc();
            $stmt->close();
        } else {
            $message = 'Gagal menyimpan biodata: ' . $conn->error;
            $message_type = 'error';
            $stmt->close();
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lengkapi Biodata - BAPAS</title>
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
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <div class="mb-8 fade-in-up">
            <a href="dashboard.php" class="inline-flex items-center text-sky-600 hover:text-sky-800 font-medium transition-colors duration-200 group">
                <div class="w-8 h-8 rounded-full bg-sky-50 flex items-center justify-center mr-2 group-hover:bg-sky-100 transition-colors">
                    <i class="fas fa-arrow-left group-hover:-translate-x-1 transition-transform"></i>
                </div>
                Kembali ke Dashboard
            </a>
        </div>

        <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-white overflow-hidden fade-in-up mb-8" style="animation-delay: 0.1s;">
            <div class="bg-gradient-to-r from-sky-600 to-teal-600 px-8 py-10 text-white relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -mr-16 -mt-16 blur-3xl"></div>
                <div class="absolute bottom-0 left-0 w-48 h-48 bg-teal-400/20 rounded-full -ml-10 -mb-10 blur-2xl"></div>
                
                <div class="relative z-10 flex items-center gap-4">
                    <div class="w-14 h-14 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center border border-white/20 shadow-inner">
                        <i class="fas fa-user-edit text-2xl text-white"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold tracking-tight">Lengkapi Biodata</h2>
                        <p class="text-sky-100 text-sm mt-1">Isi data diri Anda dengan lengkap dan benar.</p>
                    </div>
                </div>
            </div>

            <div class="p-8">
                <?php if ($message): ?>
                    <div class="mb-8 p-4 rounded-xl <?php echo $message_type === 'success' ? 'bg-teal-50 text-teal-700 border border-teal-200' : 'bg-rose-50 text-rose-700 border border-rose-200'; ?> flex items-start gap-3 shadow-sm animate-pulse">
                        <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mt-0.5 text-lg"></i>
                        <div>
                            <p class="font-medium"><?php echo htmlspecialchars($message); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($biodata['biodata_lengkap'] === 'sudah'): ?>
                    <div class="bg-emerald-50 border-l-4 border-emerald-500 p-5 mb-8 rounded-r-xl shadow-sm">
                        <div class="flex items-start gap-3">
                            <i class="fas fa-check-circle text-emerald-500 text-xl mt-0.5"></i>
                            <div>
                                <h3 class="font-bold text-emerald-800 text-sm uppercase tracking-wide mb-1">Status: Lengkap</h3>
                                <p class="text-emerald-700 text-sm">
                                    Biodata Anda sudah lengkap. Anda dapat memperbarui data di bawah ini jika terdapat perubahan.
                                </p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="bg-amber-50 border-l-4 border-amber-500 p-5 mb-8 rounded-r-xl shadow-sm">
                        <div class="flex items-start gap-3">
                            <i class="fas fa-exclamation-triangle text-amber-500 text-xl mt-0.5"></i>
                            <div>
                                <h3 class="font-bold text-amber-800 text-sm uppercase tracking-wide mb-1">Status: Belum Lengkap</h3>
                                <p class="text-amber-700 text-sm">
                                    Mohon lengkapi seluruh kolom biodata di bawah ini untuk melanjutkan proses bimbingan.
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="space-y-8">
                    <div class="grid md:grid-cols-2 gap-8">
                        <div class="space-y-6">
                            <div class="border-b border-slate-100 pb-2 mb-4">
                                <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider">Informasi Pribadi</h3>
                            </div>

                            <div>
                                <label class="block text-slate-700 text-sm font-semibold mb-2">NIK *</label>
                                <input type="text" name="nik" required maxlength="16" 
                                       value="<?php echo htmlspecialchars($biodata['nik'] ?? ''); ?>"
                                       class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-slate-700 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-500/10 transition-all duration-200"
                                       placeholder="16 digit NIK">
                            </div>

                            <div>
                                <label class="block text-slate-700 text-sm font-semibold mb-2">Tempat Lahir *</label>
                                <input type="text" name="tempat_lahir" required
                                       value="<?php echo htmlspecialchars($biodata['tempat_lahir'] ?? ''); ?>"
                                       class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-slate-700 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-500/10 transition-all duration-200"
                                       placeholder="Contoh: Jakarta">
                            </div>

                            <div>
                                <label class="block text-slate-700 text-sm font-semibold mb-2">Tanggal Lahir *</label>
                                <input type="date" name="tanggal_lahir" required
                                       value="<?php echo htmlspecialchars($biodata['tanggal_lahir'] ?? ''); ?>"
                                       class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-slate-700 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-500/10 transition-all duration-200">
                            </div>

                            <div>
                                <label class="block text-slate-700 text-sm font-semibold mb-2">Jenis Kelamin *</label>
                                <div class="relative">
                                    <select name="jenis_kelamin" required
                                            class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-slate-700 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-500/10 transition-all duration-200 appearance-none">
                                        <option value="">-- Pilih Jenis Kelamin --</option>
                                        <option value="Laki-laki" <?php echo ($biodata['jenis_kelamin'] ?? '') === 'Laki-laki' ? 'selected' : ''; ?>>Laki-laki</option>
                                        <option value="Perempuan" <?php echo ($biodata['jenis_kelamin'] ?? '') === 'Perempuan' ? 'selected' : ''; ?>>Perempuan</option>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-slate-500">
                                        <i class="fas fa-chevron-down text-xs"></i>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-slate-700 text-sm font-semibold mb-2">Agama *</label>
                                <div class="relative">
                                    <select name="agama" required
                                            class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-slate-700 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-500/10 transition-all duration-200 appearance-none">
                                        <option value="">-- Pilih Agama --</option>
                                        <option value="Islam" <?php echo ($biodata['agama'] ?? '') === 'Islam' ? 'selected' : ''; ?>>Islam</option>
                                        <option value="Kristen" <?php echo ($biodata['agama'] ?? '') === 'Kristen' ? 'selected' : ''; ?>>Kristen</option>
                                        <option value="Katolik" <?php echo ($biodata['agama'] ?? '') === 'Katolik' ? 'selected' : ''; ?>>Katolik</option>
                                        <option value="Hindu" <?php echo ($biodata['agama'] ?? '') === 'Hindu' ? 'selected' : ''; ?>>Hindu</option>
                                        <option value="Buddha" <?php echo ($biodata['agama'] ?? '') === 'Buddha' ? 'selected' : ''; ?>>Buddha</option>
                                        <option value="Konghucu" <?php echo ($biodata['agama'] ?? '') === 'Konghucu' ? 'selected' : ''; ?>>Konghucu</option>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-slate-500">
                                        <i class="fas fa-chevron-down text-xs"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div class="border-b border-slate-100 pb-2 mb-4">
                                <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider">Latar Belakang & Foto</h3>
                            </div>

                            <div>
                                <label class="block text-slate-700 text-sm font-semibold mb-2">Riwayat Pendidikan *</label>
                                <div class="relative">
                                    <select name="riwayat_pendidikan" required
                                            class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-slate-700 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-500/10 transition-all duration-200 appearance-none">
                                        <option value="">-- Pilih Pendidikan --</option>
                                        <option value="SD" <?php echo ($biodata['riwayat_pendidikan'] ?? '') === 'SD' ? 'selected' : ''; ?>>SD</option>
                                        <option value="SMP" <?php echo ($biodata['riwayat_pendidikan'] ?? '') === 'SMP' ? 'selected' : ''; ?>>SMP</option>
                                        <option value="SMA" <?php echo ($biodata['riwayat_pendidikan'] ?? '') === 'SMA' ? 'selected' : ''; ?>>SMA</option>
                                        <option value="D3" <?php echo ($biodata['riwayat_pendidikan'] ?? '') === 'D3' ? 'selected' : ''; ?>>D3</option>
                                        <option value="S1" <?php echo ($biodata['riwayat_pendidikan'] ?? '') === 'S1' ? 'selected' : ''; ?>>S1</option>
                                        <option value="S2" <?php echo ($biodata['riwayat_pendidikan'] ?? '') === 'S2' ? 'selected' : ''; ?>>S2</option>
                                        <option value="S3" <?php echo ($biodata['riwayat_pendidikan'] ?? '') === 'S3' ? 'selected' : ''; ?>>S3</option>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-slate-500">
                                        <i class="fas fa-chevron-down text-xs"></i>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-slate-700 text-sm font-semibold mb-2">Status Pernikahan *</label>
                                <div class="relative">
                                    <select name="status_pernikahan" required
                                            class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-slate-700 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-500/10 transition-all duration-200 appearance-none">
                                        <option value="">-- Pilih Status --</option>
                                        <option value="Belum Menikah" <?php echo ($biodata['status_pernikahan'] ?? '') === 'Belum Menikah' ? 'selected' : ''; ?>>Belum Menikah</option>
                                        <option value="Menikah" <?php echo ($biodata['status_pernikahan'] ?? '') === 'Menikah' ? 'selected' : ''; ?>>Menikah</option>
                                        <option value="Cerai Hidup" <?php echo ($biodata['status_pernikahan'] ?? '') === 'Cerai Hidup' ? 'selected' : ''; ?>>Cerai Hidup</option>
                                        <option value="Cerai Mati" <?php echo ($biodata['status_pernikahan'] ?? '') === 'Cerai Mati' ? 'selected' : ''; ?>>Cerai Mati</option>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-slate-500">
                                        <i class="fas fa-chevron-down text-xs"></i>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-slate-700 text-sm font-semibold mb-2">Pekerjaan *</label>
                                <input type="text" name="pekerjaan" required
                                       value="<?php echo htmlspecialchars($biodata['pekerjaan'] ?? ''); ?>"
                                       class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-slate-700 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-500/10 transition-all duration-200"
                                       placeholder="Contoh: Karyawan Swasta">
                            </div>

                            <div>
                                <label class="block text-slate-700 text-sm font-semibold mb-2">Foto Klien</label>
                                <div class="flex items-start gap-4">
                                    <?php if (!empty($biodata['foto_klien'])): ?>
                                        <div class="flex-shrink-0">
                                            <img src="../<?php echo htmlspecialchars($biodata['foto_klien']); ?>" 
                                                 alt="Foto Klien" 
                                                 class="w-24 h-24 object-cover rounded-xl border-2 border-slate-200 shadow-sm">
                                        </div>
                                    <?php endif; ?>
                                    <div class="flex-1">
                                        <div class="relative group">
                                            <input type="file" name="foto_klien" accept="image/*"
                                                   class="block w-full text-sm text-slate-500
                                                   file:mr-4 file:py-2.5 file:px-4
                                                   file:rounded-xl file:border-0
                                                   file:text-sm file:font-semibold
                                                   file:bg-sky-50 file:text-sky-700
                                                   hover:file:bg-sky-100
                                                   cursor-pointer border-2 border-slate-200 border-dashed rounded-xl p-2 hover:border-sky-400 transition-colors">
                                        </div>
                                        <p class="text-xs text-slate-400 mt-2">Format: JPG, PNG (Max 2MB). Foto formal latar belakang merah/biru disarankan.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-6 border-t border-slate-100 gap-4">
                        <a href="dashboard.php" class="px-6 py-3 bg-white border border-slate-300 text-slate-700 rounded-xl font-bold hover:bg-slate-50 hover:text-slate-900 transition-colors">
                            Batal
                        </a>
                        <button type="submit" class="px-8 py-3 bg-gradient-to-r from-sky-600 to-sky-700 text-white rounded-xl font-bold shadow-lg shadow-sky-600/20 hover:shadow-sky-600/40 hover:-translate-y-0.5 transition-all duration-300 flex items-center gap-2">
                            <i class="fas fa-save"></i> Simpan Biodata
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>

