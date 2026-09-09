<?php
require_once '../shared/config/database.php';
require_once '../shared/config/auth.php';

// Redirect if already logged in
if (isKlienLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$message_type = '';

// Function to generate nomor registrasi otomatis
function generateNoRegistrasi($conn) {
    $tahun = date('Y');
    $prefix = 'BPS';
    
    // Cari nomor registrasi terakhir untuk tahun ini dari KEDUA tabel untuk mencegah bentrok
    // Cek data_klien
    $stmt1 = $conn->prepare("SELECT no_registrasi FROM data_klien WHERE no_registrasi LIKE ? ORDER BY no_registrasi DESC LIMIT 1");
    $pattern = $prefix . '-%-' . $tahun;
    $stmt1->bind_param("s", $pattern);
    $stmt1->execute();
    $res1 = $stmt1->get_result();
    $last_reg1 = ($res1->num_rows > 0) ? $res1->fetch_assoc()['no_registrasi'] : '';
    $stmt1->close();

    // Cek klien_users
    $stmt2 = $conn->prepare("SELECT no_registrasi FROM klien_users WHERE no_registrasi LIKE ? ORDER BY no_registrasi DESC LIMIT 1");
    $stmt2->bind_param("s", $pattern);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    $last_reg2 = ($res2->num_rows > 0) ? $res2->fetch_assoc()['no_registrasi'] : '';
    $stmt2->close();

    // Bandingkan mana yang lebih besar
    $max_num = 0;
    
    foreach ([$last_reg1, $last_reg2] as $reg) {
        if (!empty($reg)) {
            $escaped_prefix = preg_quote($prefix, '/');
            if (preg_match('/' . $escaped_prefix . '-(\d+)-' . $tahun . '/', $reg, $matches)) {
                $num = intval($matches[1]);
                if ($num > $max_num) {
                    $max_num = $num;
                }
            }
        }
    }
    
    $next_number = $max_num + 1;
    
    // Format: BPS-001-2024, BPS-002-2024, etc.
    return $prefix . '-' . str_pad($next_number, 3, '0', STR_PAD_LEFT) . '-' . $tahun;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $nama = trim($_POST['nama'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;
    $no_telepon = trim($_POST['no_telepon'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pk_id = !empty($_POST['pk_id']) ? (int)$_POST['pk_id'] : null;
    
    // Detail alamat dari komponen
    $alamat_jalan = trim($_POST['alamat_jalan'] ?? '');
    $alamat_kelurahan = trim($_POST['alamat_kelurahan'] ?? '');
    $alamat_kecamatan = trim($_POST['alamat_kecamatan'] ?? '');
    $alamat_kota = trim($_POST['alamat_kota'] ?? '');
    $alamat_provinsi = trim($_POST['alamat_provinsi'] ?? '');
    $alamat_kode_pos = trim($_POST['alamat_kode_pos'] ?? '');
    $alamat_negara = trim($_POST['alamat_negara'] ?? 'Indonesia');
    $alamat_rt = trim($_POST['alamat_rt'] ?? '');
    $alamat_rw = trim($_POST['alamat_rw'] ?? '');
    $alamat_nomor = trim($_POST['alamat_nomor'] ?? '');
    $alamat_formatted = trim($_POST['alamat_formatted'] ?? '');
    $alamat_api_data = $_POST['alamat_api_data'] ?? null;
    
    // Generate nomor registrasi otomatis
    $conn = getDBConnection();
    $no_registrasi = generateNoRegistrasi($conn);
    
    // Validation
    if (empty($username) || empty($password) || empty($nama) || empty($email)) {
        $message = 'Username, Password, Nama, dan Email harus diisi!';
        $message_type = 'error';
    } elseif (empty($pk_id)) {
        $message = 'Pilih Pembimbing Kemasyarakatan (PK) yang akan membimbing Anda!';
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Format email tidak valid!';
        $message_type = 'error';
    } elseif ($password !== $password_confirm) {
        $message = 'Password dan konfirmasi password tidak cocok!';
        $message_type = 'error';
    } elseif (strlen($password) < 6) {
        $message = 'Password minimal 6 karakter!';
        $message_type = 'error';
    } else {
        // Verify PK exists
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id FROM pk_users WHERE id = ?");
        $stmt->bind_param("i", $pk_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $message = 'Pembimbing Kemasyarakatan yang dipilih tidak valid!';
            $message_type = 'error';
            $stmt->close();
        } else {
            $stmt->close();
            // Check if username exists
            $stmt = $conn->prepare("SELECT id FROM klien_users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $message = 'Username sudah digunakan!';
                $message_type = 'error';
            } else {
                // Check if email exists
                $stmt = $conn->prepare("SELECT id FROM klien_users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $message = 'Email sudah terdaftar!';
                    $message_type = 'error';
                } else {
                    // Insert new user with pending status
                    $password_md5 = md5($password);
                    $status = 'pending';
                    
                    // Build formatted address jika belum ada
                    if (empty($alamat_formatted) && !empty($alamat)) {
                        $alamat_formatted = $alamat;
                    }
                    
                    // Convert API data to JSON string if exists
                    $api_data_json = null;
                    if ($alamat_api_data) {
                        $api_data_json = is_string($alamat_api_data) ? $alamat_api_data : json_encode($alamat_api_data);
                    }
                    
                    // Insert dengan semua komponen alamat + pk_id + created_at
                    // Total 24 kolom: username, password, nama, no_registrasi, pk_id, alamat, alamat_formatted,
                    // alamat_jalan, alamat_kelurahan, alamat_kecamatan, alamat_kota, alamat_provinsi, 
                    // alamat_kode_pos, alamat_negara, alamat_rt, alamat_rw, alamat_nomor,
                    // latitude, longitude, no_telepon, email, status_approval, alamat_api_data, created_at
                    $stmt = $conn->prepare("INSERT INTO klien_users (
                        username, password, nama, no_registrasi, pk_id,
                        alamat, alamat_formatted,
                        alamat_jalan, alamat_kelurahan, alamat_kecamatan, 
                        alamat_kota, alamat_provinsi, alamat_kode_pos, alamat_negara,
                        alamat_rt, alamat_rw, alamat_nomor,
                        latitude, longitude, 
                        no_telepon, email, status_approval, alamat_api_data, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $created_at = date('Y-m-d H:i:s');

                    // Bind parameters: 24 total
                    $stmt->bind_param("ssssissssssssssssddsssss", 
                        $username, $password_md5, $nama, $no_registrasi, $pk_id,
                        $alamat, $alamat_formatted,
                        $alamat_jalan, $alamat_kelurahan, $alamat_kecamatan,
                        $alamat_kota, $alamat_provinsi, $alamat_kode_pos, $alamat_negara,
                        $alamat_rt, $alamat_rw, $alamat_nomor,
                        $latitude, $longitude,
                        $no_telepon, $email, $status, $api_data_json, $created_at
                    );
            
                    if ($stmt->execute()) {
                        // FIX: Juga insert ke tabel data_klien agar data muncul di Dashboard PK dan Klien
                        $stmt_dk = $conn->prepare("INSERT INTO data_klien (nama, no_registrasi, pk_id, alamat, status, created_at) VALUES (?, ?, ?, ?, 'Aktif', ?)");
                        $stmt_dk->bind_param("ssiss", $nama, $no_registrasi, $pk_id, $alamat, $created_at);
                        $stmt_dk->execute();
                        $stmt_dk->close();

                        $message = 'Registrasi berhasil!<br><br><div class="bg-green-50 border-2 border-green-500 rounded-lg p-4 mt-3"><strong class="text-green-800">Nomor Registrasi Anda:</strong><br><code class="bg-green-100 px-4 py-2 rounded font-mono text-lg text-green-800 font-bold block mt-2">' . htmlspecialchars($no_registrasi) . '</code><br><small class="text-green-700 mt-2 block"><i class="fas fa-exclamation-triangle mr-1"></i>Simpan nomor registrasi ini dengan baik!</small></div><br>Akun Anda sedang menunggu persetujuan dari Pembimbing Kemasyarakatan yang dipilih. Notifikasi akan dikirim ke email Anda.';
                        $message_type = 'success';
                        // Clear form
                        $_POST = [];
                    } else {
                        $message = 'Gagal melakukan registrasi: ' . $conn->error;
                        $message_type = 'error';
                    }
                }
            }
        }
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi Klien - BAPAS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        tailwind.config = {
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
                            600: '#0284c7',
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
                            500: '#14b8a6',
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
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        .float-animation {
            animation: float 6s ease-in-out infinite;
        }
        .float-animation-delayed {
            animation: float 6s ease-in-out infinite;
            animation-delay: 3s;
        }
        .fade-in-up {
            animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        #map {
            border-radius: 0.75rem;
            z-index: 1;
        }
        .goapi-autocomplete {
            position: relative;
        }
        .goapi-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            display: none;
            margin-top: 0.5rem;
        }
        .goapi-suggestion-item {
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            transition: all 0.2s;
            font-size: 0.875rem;
            color: #475569;
        }
        .goapi-suggestion-item:hover {
            background: #f0f9ff;
            color: #0284c7;
            padding-left: 1.25rem;
        }
        .goapi-suggestion-item:last-child {
            border-bottom: none;
        }
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="bg-gradient-to-br from-primary-50 via-sky-50 to-secondary-50 min-h-screen py-12 font-sans antialiased text-slate-800">
    <div class="container mx-auto px-4 max-w-2xl relative">
        <!-- Decorative Elements -->
        <div class="absolute top-0 left-0 w-72 h-72 bg-primary-200/30 rounded-full blur-3xl float-animation -z-10"></div>
        <div class="absolute bottom-0 right-0 w-72 h-72 bg-secondary-200/30 rounded-full blur-3xl float-animation-delayed -z-10"></div>

        <div class="bg-white/80 backdrop-blur-xl rounded-3xl shadow-2xl shadow-sky-900/10 p-8 relative z-10 border border-white/50 fade-in-up">
            <!-- Header -->
            <div class="text-center mb-10">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-primary-500 to-secondary-500 rounded-2xl mb-6 shadow-lg shadow-primary-500/30 transform rotate-3 hover:rotate-6 transition-transform duration-300">
                    <i class="fas fa-user-plus text-4xl text-white"></i>
                </div>
                <h1 class="text-3xl font-bold text-slate-800 mb-2 tracking-tight">Registrasi Akun</h1>
                <p class="text-slate-500 text-lg font-medium">Daftar sebagai Klien Pemasyarakatan</p>
            </div>

            <?php if ($message): ?>
                <div class="mb-8 p-4 rounded-xl <?php echo $message_type === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'; ?> flex items-start gap-3 shadow-sm">
                    <div class="mt-0.5">
                        <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <?php echo $message; // Allow HTML for success message ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($message_type !== 'success'): ?>
            <form method="POST" action="" class="space-y-6">
                <div class="group">
                    <label class="block text-slate-700 font-semibold mb-2 text-sm group-focus-within:text-primary-600 transition-colors">Username <span class="text-red-500">*</span></label>
                    <input type="text" name="username" required 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                           class="w-full px-4 py-3.5 bg-slate-50 border-2 border-slate-100 rounded-xl focus:outline-none focus:bg-white focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all duration-300 placeholder-slate-400"
                           placeholder="Pilih username">
                </div>
                
                <div class="bg-primary-50/50 border border-primary-100 rounded-xl p-4 flex items-start gap-3">
                    <i class="fas fa-info-circle text-primary-500 mt-0.5"></i>
                    <p class="text-sm text-primary-700">
                        <strong>Nomor Registrasi:</strong> Akan otomatis dibuat saat registrasi dengan format <code class="bg-white px-2 py-0.5 rounded border border-primary-200 text-primary-600 font-mono text-xs font-bold shadow-sm">BPS-XXX-<?php echo date('Y'); ?></code>
                    </p>
                </div>

                <div class="group">
                    <label class="block text-slate-700 font-semibold mb-2 text-sm group-focus-within:text-primary-600 transition-colors">Nama Lengkap <span class="text-red-500">*</span></label>
                    <input type="text" name="nama" required 
                           value="<?php echo htmlspecialchars($_POST['nama'] ?? ''); ?>"
                           class="w-full px-4 py-3.5 bg-slate-50 border-2 border-slate-100 rounded-xl focus:outline-none focus:bg-white focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all duration-300 placeholder-slate-400"
                           placeholder="Masukkan nama lengkap sesuai KTP">
                </div>

                <div class="grid md:grid-cols-2 gap-6">
                    <div class="group">
                        <label class="block text-slate-700 font-semibold mb-2 text-sm group-focus-within:text-primary-600 transition-colors">Password <span class="text-red-500">*</span></label>
                        <input type="password" name="password" required 
                               class="w-full px-4 py-3.5 bg-slate-50 border-2 border-slate-100 rounded-xl focus:outline-none focus:bg-white focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all duration-300 placeholder-slate-400"
                               placeholder="Minimal 6 karakter">
                    </div>
                    <div class="group">
                        <label class="block text-slate-700 font-semibold mb-2 text-sm group-focus-within:text-primary-600 transition-colors">Konfirmasi Password <span class="text-red-500">*</span></label>
                        <input type="password" name="password_confirm" required 
                               class="w-full px-4 py-3.5 bg-slate-50 border-2 border-slate-100 rounded-xl focus:outline-none focus:bg-white focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all duration-300 placeholder-slate-400"
                               placeholder="Ulangi password">
                    </div>
                </div>

                <div class="space-y-4">
                    <label class="block text-slate-700 font-semibold text-sm">Alamat Lengkap <span class="text-red-500">*</span></label>
                    
                    <div class="goapi-autocomplete relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-search text-slate-400 group-focus-within:text-primary-500 transition-colors"></i>
                        </div>
                        <input type="text" id="address-input" 
                               class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border-2 border-slate-100 rounded-xl focus:outline-none focus:bg-white focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all duration-300 placeholder-slate-400"
                               placeholder="Cari kelurahan/desa atau kecamatan...">
                        <div id="address-suggestions" class="goapi-suggestions"></div>
                    </div>

                    <div id="map" class="h-80 w-full shadow-inner border-2 border-slate-100"></div>
                    
                    <textarea name="alamat" id="alamat" rows="3" required
                              class="w-full px-4 py-3.5 bg-slate-50 border-2 border-slate-100 rounded-xl focus:outline-none focus:bg-white focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all duration-300 placeholder-slate-400 resize-none"
                              placeholder="Detail alamat akan terisi otomatis, tambahkan detail jalan/nomor rumah jika perlu"><?php echo htmlspecialchars($_POST['alamat'] ?? ''); ?></textarea>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-slate-50 rounded-lg p-3 border border-slate-100 flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-red-50 text-red-500 flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-map-marker-alt text-xs"></i>
                            </div>
                            <div class="overflow-hidden">
                                <p class="text-[10px] text-slate-500 uppercase font-bold tracking-wider">Latitude</p>
                                <p id="lat-display" class="text-xs font-mono font-medium text-slate-700 truncate">-</p>
                            </div>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-3 border border-slate-100 flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-map-marker-alt text-xs"></i>
                            </div>
                            <div class="overflow-hidden">
                                <p class="text-[10px] text-slate-500 uppercase font-bold tracking-wider">Longitude</p>
                                <p id="lng-display" class="text-xs font-mono font-medium text-slate-700 truncate">-</p>
                            </div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="latitude" id="latitude" value="<?php echo htmlspecialchars($_POST['latitude'] ?? ''); ?>">
                    <input type="hidden" name="longitude" id="longitude" value="<?php echo htmlspecialchars($_POST['longitude'] ?? ''); ?>">
                    
                    <!-- Hidden fields untuk komponen alamat detail -->
                    <input type="hidden" name="alamat_jalan" id="alamat_jalan" value="">
                    <input type="hidden" name="alamat_kelurahan" id="alamat_kelurahan" value="">
                    <input type="hidden" name="alamat_kecamatan" id="alamat_kecamatan" value="">
                    <input type="hidden" name="alamat_kota" id="alamat_kota" value="">
                    <input type="hidden" name="alamat_provinsi" id="alamat_provinsi" value="">
                    <input type="hidden" name="alamat_kode_pos" id="alamat_kode_pos" value="">
                    <input type="hidden" name="alamat_negara" id="alamat_negara" value="Indonesia">
                    <input type="hidden" name="alamat_rt" id="alamat_rt" value="">
                    <input type="hidden" name="alamat_rw" id="alamat_rw" value="">
                    <input type="hidden" name="alamat_nomor" id="alamat_nomor" value="">
                    <input type="hidden" name="alamat_formatted" id="alamat_formatted" value="">
                    <input type="hidden" name="alamat_api_data" id="alamat_api_data" value="">
                    
                    <p class="text-xs text-slate-500 flex items-center gap-1.5">
                        <i class="fas fa-info-circle text-primary-500"></i>
                        Ketik alamat untuk mencari, atau klik peta untuk menentukan lokasi yang tepat
                    </p>
                </div>

                <div class="grid md:grid-cols-2 gap-6">
                    <div class="group">
                        <label class="block text-slate-700 font-semibold mb-2 text-sm group-focus-within:text-primary-600 transition-colors">No. Telepon</label>
                        <input type="text" name="no_telepon" 
                               value="<?php echo htmlspecialchars($_POST['no_telepon'] ?? ''); ?>"
                               class="w-full px-4 py-3.5 bg-slate-50 border-2 border-slate-100 rounded-xl focus:outline-none focus:bg-white focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all duration-300 placeholder-slate-400"
                               placeholder="Contoh: 081234567890">
                    </div>
                    <div class="group">
                        <label class="block text-slate-700 font-semibold mb-2 text-sm group-focus-within:text-primary-600 transition-colors">Email <span class="text-red-500">*</span></label>
                        <input type="email" name="email" required
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                               class="w-full px-4 py-3.5 bg-slate-50 border-2 border-slate-100 rounded-xl focus:outline-none focus:bg-white focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all duration-300 placeholder-slate-400"
                               placeholder="email@example.com">
                        <p class="text-xs text-slate-500 mt-1.5">
                            <i class="fas fa-info-circle mr-1"></i>Notifikasi approval akan dikirim ke email ini
                        </p>
                    </div>
                </div>

                <div class="group">
                    <label class="block text-slate-700 font-semibold mb-2 text-sm group-focus-within:text-primary-600 transition-colors">
                        Pilih Pembimbing Kemasyarakatan (PK) <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-user-tie text-slate-400 group-focus-within:text-primary-500 transition-colors"></i>
                        </div>
                        <select name="pk_id" required
                                class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border-2 border-slate-100 rounded-xl focus:outline-none focus:bg-white focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all duration-300 appearance-none cursor-pointer">
                            <option value="">-- Pilih PK --</option>
                            <?php
                            $conn = getDBConnection();
                            $pk_list = $conn->query("SELECT id, nama, nip FROM pk_users ORDER BY nama ASC");
                            $selected_pk = $_POST['pk_id'] ?? '';
                            while ($pk = $pk_list->fetch_assoc()) {
                                $selected = ($selected_pk == $pk['id']) ? 'selected' : '';
                            $display_name = $pk['nama'];
                            if (!empty($pk['nip'])) {
                                $display_name .= ' (NIP: ' . htmlspecialchars($pk['nip']) . ')';
                            }
                            echo "<option value=\"{$pk['id']}\" $selected>" . htmlspecialchars($display_name) . "</option>";
                        }
                        $conn->close();
                        ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-info-circle mr-1"></i>Pilih PK yang akan menjadi pembimbing Anda setelah surat keterangan dikeluarkan oleh lembaga pusat
                    </p>
                </div>

                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                    <p class="text-sm text-blue-700">
                        <i class="fas fa-info-circle mr-2"></i>
                        <strong>Catatan:</strong> Setelah registrasi, akun Anda akan ditinjau oleh Pembimbing Kemasyarakatan. Anda akan dapat login setelah akun disetujui.
                    </p>
                </div>

                <button type="submit" 
                        class="w-full bg-gradient-to-r from-primary-600 to-secondary-600 text-white py-4 px-6 rounded-xl hover:from-primary-700 hover:to-secondary-700 transition-all font-bold text-lg shadow-lg shadow-primary-500/30 hover:shadow-xl hover:shadow-primary-500/40 hover:-translate-y-0.5 active:translate-y-0">
                    <span class="flex items-center justify-center gap-2">
                        <i class="fas fa-user-plus"></i> Daftar Sekarang
                    </span>
                </button>
            </form>
            <?php endif; ?>

            <div class="mt-8 pt-6 border-t border-slate-100 text-center">
                <p class="text-slate-500 mb-3 text-sm">Sudah punya akun?</p>
                <a href="login.php" class="inline-flex items-center justify-center gap-2 text-primary-600 hover:text-primary-700 font-bold hover:underline decoration-2 underline-offset-4 transition-all">
                    <i class="fas fa-sign-in-alt"></i> Login di sini
                </a>
            </div>
            
            <div class="mt-6 text-center">
                <a href="../index.php" class="text-slate-400 hover:text-slate-600 text-sm font-medium transition-colors flex items-center justify-center gap-2">
                    <i class="fas fa-long-arrow-alt-left"></i> Kembali ke Halaman Utama
                </a>
            </div>
        </div>
    </div>

    <script>
        const GOAPI_KEY = 'dac187b8-9469-5789-7632-419e373b';
        const GOAPI_BASE_URL = 'https://api.goapi.io';
        
        let map;
        let marker;
        let searchTimeout;
        let reverseGeocodeTimeout;
        let reverseGeocodeCache = {}; // Cache untuk hasil reverse geocoding
        let isReverseGeocoding = false; // Flag untuk mencegah multiple simultaneous requests

        // Initialize Leaflet map
        function initMap() {
            // Default location (Jakarta, Indonesia)
            const defaultLocation = [-6.2088, 106.8456];
            
            map = L.map('map').setView(defaultLocation, 13);
            
            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(map);
            
            // Add marker
            marker = L.marker(defaultLocation, { draggable: true }).addTo(map);
            
            // When marker is dragged
            marker.on('dragend', function(e) {
                const latlng = marker.getLatLng();
                // Update koordinat langsung
                document.getElementById('latitude').value = latlng.lat;
                document.getElementById('longitude').value = latlng.lng;
                document.getElementById('lat-display').textContent = latlng.lat.toFixed(6);
                document.getElementById('lng-display').textContent = latlng.lng.toFixed(6);
                
                // Debounce reverse geocoding untuk menghindari terlalu banyak request
                clearTimeout(reverseGeocodeTimeout);
                reverseGeocodeTimeout = setTimeout(() => {
                    updateLocationFromCoords(latlng.lat, latlng.lng);
                }, 500);
            });
            
            // When map is clicked
            map.on('click', function(e) {
                marker.setLatLng(e.latlng);
                // Update koordinat langsung
                document.getElementById('latitude').value = e.latlng.lat;
                document.getElementById('longitude').value = e.latlng.lng;
                document.getElementById('lat-display').textContent = e.latlng.lat.toFixed(6);
                document.getElementById('lng-display').textContent = e.latlng.lng.toFixed(6);
                
                // Reverse geocode untuk mendapatkan alamat
                updateLocationFromCoords(e.latlng.lat, e.latlng.lng);
            });
            
            // Try to get user's current location
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const userLocation = [position.coords.latitude, position.coords.longitude];
                        map.setView(userLocation, 15);
                        marker.setLatLng(userLocation);
                        updateLocationFromCoords(userLocation[0], userLocation[1]);
                    },
                    function() {
                        // Use default location if geolocation fails
                        updateLocationFromCoords(defaultLocation[0], defaultLocation[1]);
                    }
                );
            } else {
                updateLocationFromCoords(defaultLocation[0], defaultLocation[1]);
            }
        }

        // Search address using GoAPI
        async function searchAddress(query) {
            if (!query || query.length < 3) {
                document.getElementById('address-suggestions').style.display = 'none';
                return;
            }

            try {
                const response = await fetch(`${GOAPI_BASE_URL}/regional/area?search=${encodeURIComponent(query)}&api_key=${GOAPI_KEY}`);
                const data = await response.json();
                
                if (data.status === 'success' && data.data && data.data.length > 0) {
                    displaySuggestions(data.data);
                } else {
                    // Try alternative search
                    await searchAddressAlternative(query);
                }
            } catch (error) {
                console.error('Error searching address:', error);
                await searchAddressAlternative(query);
            }
        }

        // Alternative search using GoAPI geocoding with timeout
        async function searchAddressAlternative(query) {
            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 5000); // 5 detik timeout
                
                const response = await fetch(`${GOAPI_BASE_URL}/geocoding/search?q=${encodeURIComponent(query)}&api_key=${GOAPI_KEY}`, {
                    signal: controller.signal
                });
                clearTimeout(timeoutId);
                
                const data = await response.json();
                
                if (data.status === 'success' && data.data && data.data.length > 0) {
                    displaySuggestions(data.data);
                } else {
                    document.getElementById('address-suggestions').style.display = 'none';
                }
            } catch (error) {
                document.getElementById('address-suggestions').style.display = 'none';
            }
        }

        // Display address suggestions
        function displaySuggestions(suggestions) {
            const suggestionsDiv = document.getElementById('address-suggestions');
            suggestionsDiv.innerHTML = '';
            
            suggestions.slice(0, 5).forEach(item => {
                const div = document.createElement('div');
                div.className = 'goapi-suggestion-item';
                
                let displayText = '';
                if (item.name) {
                    displayText = item.name;
                    if (item.province) displayText += ', ' + item.province;
                } else if (item.formatted_address) {
                    displayText = item.formatted_address;
                } else if (item.address) {
                    displayText = item.address;
                }
                
                div.textContent = displayText;
                div.onclick = function() {
                    selectAddress(item);
                };
                suggestionsDiv.appendChild(div);
            });
            
            suggestionsDiv.style.display = suggestions.length > 0 ? 'block' : 'none';
        }

        // Select address from suggestions
        function selectAddress(item) {
            let lat, lng, address;
            
            // Extract coordinates dari berbagai format
            if (item.latitude && item.longitude) {
                lat = parseFloat(item.latitude);
                lng = parseFloat(item.longitude);
            } else if (item.lat && item.lon) {
                lat = parseFloat(item.lat);
                lng = parseFloat(item.lon);
            } else if (item.location && item.location.lat && item.location.lng) {
                lat = parseFloat(item.location.lat);
                lng = parseFloat(item.location.lng);
            } else if (item.coordinates) {
                lat = parseFloat(item.coordinates.lat || item.coordinates[1]);
                lng = parseFloat(item.coordinates.lng || item.coordinates[0]);
            }
            
            // Extract address dari berbagai format
            if (item.formatted_address) {
                address = item.formatted_address;
            } else if (item.address) {
                address = item.address;
            } else if (item.name) {
                const parts = [item.name];
                if (item.district || item.kecamatan) parts.push(item.district || item.kecamatan);
                if (item.city || item.kota) parts.push(item.city || item.kota);
                if (item.province || item.provinsi) parts.push(item.province || item.provinsi);
                address = parts.join(', ');
            } else if (item.display_name) {
                address = item.display_name;
            }
            
            // Update map dan location
            if (lat && lng) {
                map.setView([lat, lng], 15);
                marker.setLatLng([lat, lng]);
                updateLocationFromCoords(lat, lng, address);
            } else if (address) {
                // Jika tidak ada koordinat tapi ada alamat, coba geocode
                geocodeAddress(address);
            }
            
            document.getElementById('address-suggestions').style.display = 'none';
            document.getElementById('address-input').value = address || '';
        }
        
        // Geocode address to get coordinates (helper function)
        async function geocodeAddress(address) {
            try {
                const response = await fetch(`${GOAPI_BASE_URL}/geocoding/search?q=${encodeURIComponent(address)}&api_key=${GOAPI_KEY}`);
                const data = await response.json();
                
                if (data.status === 'success' && data.data && data.data.length > 0) {
                    const firstResult = data.data[0];
                    let lat, lng;
                    
                    if (firstResult.latitude && firstResult.longitude) {
                        lat = parseFloat(firstResult.latitude);
                        lng = parseFloat(firstResult.longitude);
                    } else if (firstResult.lat && firstResult.lon) {
                        lat = parseFloat(firstResult.lat);
                        lng = parseFloat(firstResult.lon);
                    } else if (firstResult.location) {
                        lat = parseFloat(firstResult.location.lat);
                        lng = parseFloat(firstResult.location.lng);
                    }
                    
                    if (lat && lng) {
                        map.setView([lat, lng], 15);
                        marker.setLatLng([lat, lng]);
                        updateLocationFromCoords(lat, lng, address);
                    }
                }
            } catch (error) {
                console.error('Error geocoding address:', error);
            }
        }

        // Function to update address components
        function updateAddressComponents(addressData, source = 'nominatim') {
            if (!addressData) return;
            
            // Jangan reset jika sudah ada data (untuk menghindari overwrite data yang sudah baik)
            // Hanya update jika field masih kosong atau jika data baru lebih lengkap
            
            if (source === 'goapi' && addressData) {
                // Parse dari GoAPI
                const jalan = addressData.street || addressData.jalan || addressData.road || '';
                const kelurahan = addressData.village || addressData.kelurahan || '';
                const kecamatan = addressData.district || addressData.kecamatan || addressData.subdistrict || '';
                const kota = addressData.city || addressData.kota || addressData.kabupaten || '';
                const provinsi = addressData.province || addressData.provinsi || addressData.state || '';
                const kodePos = addressData.postcode || addressData.postal_code || '';
                const negara = addressData.country || 'Indonesia';
                
                // Update hanya jika field kosong atau data baru lebih lengkap
                if (jalan) document.getElementById('alamat_jalan').value = jalan;
                if (kelurahan) document.getElementById('alamat_kelurahan').value = kelurahan;
                if (kecamatan) document.getElementById('alamat_kecamatan').value = kecamatan;
                if (kota) document.getElementById('alamat_kota').value = kota;
                if (provinsi) document.getElementById('alamat_provinsi').value = provinsi;
                if (kodePos) document.getElementById('alamat_kode_pos').value = kodePos;
                if (negara) document.getElementById('alamat_negara').value = negara;
                
                console.log('Komponen alamat dari GoAPI:', { jalan, kelurahan, kecamatan, kota, provinsi, kodePos });
            } else if (source === 'nominatim' && addressData) {
                // Parse dari Nominatim
                const addr = addressData.address || addressData;
                
                const jalan = addr.road || addr.street || '';
                const kelurahan = addr.village || addr.hamlet || addr.neighbourhood || '';
                const kecamatan = addr.suburb || addr.city_district || '';
                const kota = addr.city || addr.town || addr.municipality || '';
                const provinsi = addr.state || addr.province || '';
                const kodePos = addr.postcode || '';
                const negara = addr.country || 'Indonesia';
                
                // Update hanya jika field kosong atau data baru lebih lengkap
                if (jalan) document.getElementById('alamat_jalan').value = jalan;
                if (kelurahan) document.getElementById('alamat_kelurahan').value = kelurahan;
                if (kecamatan) document.getElementById('alamat_kecamatan').value = kecamatan;
                if (kota) document.getElementById('alamat_kota').value = kota;
                if (provinsi) document.getElementById('alamat_provinsi').value = provinsi;
                if (kodePos) document.getElementById('alamat_kode_pos').value = kodePos;
                if (negara) document.getElementById('alamat_negara').value = negara;
                
                console.log('Komponen alamat dari Nominatim:', { jalan, kelurahan, kecamatan, kota, provinsi, kodePos });
            }
            
            // Simpan data API untuk referensi
            if (addressData) {
                document.getElementById('alamat_api_data').value = JSON.stringify(addressData);
            }
        }

        // Helper function to build address from components
        function buildAddressFromComponents() {
            const parts = [];
            const jalan = document.getElementById('alamat_jalan')?.value || '';
            const kelurahan = document.getElementById('alamat_kelurahan')?.value || '';
            const kecamatan = document.getElementById('alamat_kecamatan')?.value || '';
            const kota = document.getElementById('alamat_kota')?.value || '';
            const provinsi = document.getElementById('alamat_provinsi')?.value || '';
            const kodePos = document.getElementById('alamat_kode_pos')?.value || '';
            
            // Build address dengan urutan: jalan, kelurahan, kecamatan, kota, provinsi
            if (jalan && jalan.trim() !== '') parts.push(jalan.trim());
            if (kelurahan && kelurahan.trim() !== '') parts.push(kelurahan.trim());
            if (kecamatan && kecamatan.trim() !== '') parts.push(kecamatan.trim());
            if (kota && kota.trim() !== '') parts.push(kota.trim());
            if (provinsi && provinsi.trim() !== '') parts.push(provinsi.trim());
            if (kodePos && kodePos.trim() !== '') parts.push(kodePos.trim());
            
            const result = parts.length > 0 ? parts.join(', ') : null;
            console.log('🔧 buildAddressFromComponents result:', result, 'from parts:', parts);
            return result;
        }
        
        // Helper function to update address field
        function updateAddressField(address) {
            const alamatField = document.getElementById('alamat');
            console.log('updateAddressField called with:', address);
            
            if (address && address.trim() !== '') {
                address = address.trim().replace(/\s+/g, ' ');
                alamatField.value = address;
                alamatField.disabled = false;
                
                const addressInput = document.getElementById('address-input');
                if (addressInput) {
                    addressInput.value = address;
                }
                
                const alamatFormatted = document.getElementById('alamat_formatted');
                if (alamatFormatted) {
                    alamatFormatted.value = address;
                }
                
                console.log('Address field updated successfully:', address);
            } else {
                const builtAddress = buildAddressFromComponents();
                console.log('Built address from components:', builtAddress);
                
                if (builtAddress && builtAddress.trim() !== '') {
                    alamatField.value = builtAddress;
                    alamatField.disabled = false;
                    
                    const addressInput = document.getElementById('address-input');
                    if (addressInput) {
                        addressInput.value = builtAddress;
                    }
                    
                    const alamatFormatted = document.getElementById('alamat_formatted');
                    if (alamatFormatted) {
                        alamatFormatted.value = builtAddress;
                    }
                    
                    console.log('Address field updated from components:', builtAddress);
                } else {
                    alamatField.value = '';
                    alamatField.placeholder = 'Alamat tidak ditemukan. Silakan ketik alamat manual atau pilih lokasi lain.';
                    alamatField.disabled = false;
                    
                    const addressInput = document.getElementById('address-input');
                    if (addressInput) {
                        addressInput.value = '';
                    }
                    
                    console.log('No address found, allowing manual input');
                }
            }
        }

        // Update location from coordinates - Optimized version with caching
        async function updateLocationFromCoords(lat, lng, addressText = null) {
            // Prevent multiple simultaneous requests
            if (isReverseGeocoding) return;
            isReverseGeocoding = true;
            
            // Deklarasi variabel
            const alamatField = document.getElementById('alamat');
            let address = null;
            let addressData = null;
            let source = null;
            
            // Pastikan marker tetap ada di posisi yang benar
            if (map && marker) {
                const newLatLng = [lat, lng];
                marker.setLatLng(newLatLng);
                // Pastikan marker terlihat di map
                if (!map.hasLayer(marker)) {
                    marker.addTo(map);
                }
            }
            
            // Update koordinat
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;
            document.getElementById('lat-display').textContent = lat.toFixed(6);
            document.getElementById('lng-display').textContent = lng.toFixed(6);
            
            // Set loading state
            alamatField.disabled = true;
            alamatField.value = 'Mengambil alamat...';
            
            // Check cache first (precision 4 decimal places ~11 meters)
            const cacheKey = `${lat.toFixed(4)}_${lng.toFixed(4)}`;
            if (reverseGeocodeCache[cacheKey]) {
                const cached = reverseGeocodeCache[cacheKey];
                updateAddressComponents(cached.data, cached.source);
                updateAddressField(cached.address || buildAddressFromComponents());
                isReverseGeocoding = false;
                return;
            }
            
            // If addressText provided, use it immediately
            if (addressText) {
                updateAddressField(addressText);
            }
            
            // Try reverse geocoding via server-side proxy (to avoid CORS issues)
            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 detik timeout
                
                const response = await fetch(`../shared/api/reverse-geocode.php?lat=${lat}&lng=${lng}`, {
                    signal: controller.signal
                });
                clearTimeout(timeoutId);
                
                if (response.ok) {
                    const result = await response.json();
                    console.log('📡 Reverse Geocode Response:', result);
                    
                    if (result.success && result.data) {
                        const data = result.data;
                        
                        // Update komponen alamat dulu (selalu update komponen)
                        if (data.address) {
                            updateAddressComponents(data, 'nominatim');
                        }
                        
                        // Setelah update komponen, coba build alamat
                        const builtAddr = buildAddressFromComponents();
                        
                        // Prioritaskan address dari proxy (sudah diformat dengan baik)
                        if (result.address && result.address.trim() !== '') {
                            address = result.address.trim();
                            addressData = data;
                            source = 'nominatim';
                            console.log('✅ Address dari proxy:', address);
                        }
                        // Jika tidak ada dari proxy, prioritaskan display_name
                        else if (data.display_name && data.display_name.trim() !== '') {
                            address = data.display_name.trim();
                            addressData = data;
                            source = 'nominatim';
                            console.log('✅ Address dari display_name:', address);
                        } 
                        // Jika tidak ada display_name, gunakan yang dibangun dari komponen
                        else if (builtAddr && builtAddr.trim() !== '') {
                            address = builtAddr.trim();
                            addressData = data;
                            source = 'nominatim';
                            console.log('✅ Address dari komponen:', address);
                        } 
                        // Fallback: coba build dari address object secara manual
                        else if (data.address) {
                            const addrParts = [];
                            
                            // Road/Street
                            if (data.address.road) {
                                addrParts.push(data.address.road);
                            } else if (data.address.pedestrian) {
                                addrParts.push(data.address.pedestrian);
                            }
                            
                            // House number
                            if (data.address.house_number) {
                                addrParts.push('No. ' + data.address.house_number);
                            }
                            
                            // Village/Hamlet/Neighbourhood
                            if (data.address.village) {
                                addrParts.push(data.address.village);
                            } else if (data.address.hamlet) {
                                addrParts.push(data.address.hamlet);
                            } else if (data.address.neighbourhood) {
                                addrParts.push(data.address.neighbourhood);
                            }
                            
                            // Suburb/City District
                            if (data.address.suburb) {
                                addrParts.push(data.address.suburb);
                            } else if (data.address.city_district) {
                                addrParts.push(data.address.city_district);
                            }
                            
                            // City/Town/Municipality
                            if (data.address.city) {
                                addrParts.push(data.address.city);
                            } else if (data.address.town) {
                                addrParts.push(data.address.town);
                            } else if (data.address.municipality) {
                                addrParts.push(data.address.municipality);
                            }
                            
                            // State/Province
                            if (data.address.state) {
                                addrParts.push(data.address.state);
                            } else if (data.address.province) {
                                addrParts.push(data.address.province);
                            }
                            
                            // Country
                            if (data.address.country) {
                                addrParts.push(data.address.country);
                            }
                            
                            if (addrParts.length > 0) {
                                address = addrParts.join(', ');
                                addressData = data;
                                source = 'nominatim';
                                console.log('✅ Address dari address object:', address);
                            } else {
                                console.log('⚠️ Nominatim address object kosong');
                            }
                        } else {
                            console.log('⚠️ Nominatim response tidak memiliki display_name atau address');
                        }
                    } else {
                        console.log('⚠️ Reverse geocode response tidak valid:', result);
                    }
                } else {
                    console.log('⚠️ Reverse geocode HTTP Error:', response.status, response.statusText);
                }
            } catch (e) {
                console.log('⚠️ Reverse geocode failed:', e);
                // Reverse geocode failed, akan menggunakan komponen alamat yang sudah ada atau manual input
            }
            
            // Fallback: Jika masih tidak ada alamat, coba build dari komponen yang sudah ter-update
            if (!address || address.trim() === '') {
                const builtFromComponents = buildAddressFromComponents();
                if (builtFromComponents && builtFromComponents.trim() !== '') {
                    address = builtFromComponents.trim();
                    console.log('✓ Address dari komponen (fallback):', address);
                }
            }
            
            // Build final address - prioritaskan dari API response, lalu dari komponen
            let finalAddress = null;
            
            console.log('🔍 Building final address. address:', address, 'addressData:', addressData ? 'exists' : 'null');
            
            // Jika ada address dari API, gunakan itu
            if (address && address.trim() !== '') {
                finalAddress = address.trim();
                console.log('✅ Using address from API:', finalAddress);
            } else {
                // Coba build dari komponen alamat yang sudah di-update
                finalAddress = buildAddressFromComponents();
                console.log('🔧 Built from components:', finalAddress);
            }
            
            // Jika masih kosong, coba sekali lagi build dari komponen
            if (!finalAddress || finalAddress.trim() === '') {
                finalAddress = buildAddressFromComponents();
                console.log('🔧 Second attempt from components:', finalAddress);
            }
            
            // Cache result (hanya jika ada data)
            if (addressData && finalAddress && finalAddress.trim() !== '') {
                reverseGeocodeCache[cacheKey] = {
                    address: finalAddress,
                    data: addressData,
                    source: source || 'nominatim'
                };
                console.log('💾 Cached result:', finalAddress);
            }
            
            // Pastikan marker tetap ada di akhir proses (sebelum update UI)
            if (map && marker) {
                const currentLatLng = marker.getLatLng();
                if (!currentLatLng || Math.abs(currentLatLng.lat - lat) > 0.0001 || Math.abs(currentLatLng.lng - lng) > 0.0001) {
                    marker.setLatLng([lat, lng]);
                }
                // Pastikan marker selalu terlihat di map
                if (!map.hasLayer(marker)) {
                    marker.addTo(map);
                }
                // Pastikan map terlihat di viewport marker (tanpa animasi)
                const currentZoom = map.getZoom();
                if (currentZoom < 15) {
                    map.setView([lat, lng], 15, { animate: false });
                } else {
                    map.setView([lat, lng], currentZoom, { animate: false });
                }
            }
            
            // Update UI - selalu update meskipun alamat kosong
            alamatField.disabled = false;
            
            console.log('📝 Final address to display:', finalAddress);
            
            if (finalAddress && finalAddress.trim() !== '') {
                // Update alamat field dengan alamat yang ditemukan
                alamatField.value = finalAddress;
                alamatField.placeholder = '';
                alamatField.classList.remove('placeholder-gray-500');
                
                const addressInput = document.getElementById('address-input');
                if (addressInput) {
                    addressInput.value = finalAddress;
                }
                
                const alamatFormatted = document.getElementById('alamat_formatted');
                if (alamatFormatted) {
                    alamatFormatted.value = finalAddress;
                }
                
                console.log('✅ Address field updated successfully:', finalAddress);
            } else {
                // Jika tidak ada alamat, biarkan user input manual
                alamatField.value = '';
                alamatField.placeholder = 'Alamat tidak ditemukan. Silakan ketik alamat manual atau pilih lokasi lain.';
                alamatField.classList.add('placeholder-gray-500');
                
                const addressInput = document.getElementById('address-input');
                if (addressInput) {
                    addressInput.value = '';
                }
                
                console.log('⚠️ No address found, allowing manual input');
            }
            
            isReverseGeocoding = false;
            console.log('✅ Reverse geocoding completed');
        }
        // Setup address input autocomplete
        document.addEventListener('DOMContentLoaded', function() {
            const addressInput = document.getElementById('address-input');
            
            addressInput.addEventListener('input', function(e) {
                clearTimeout(searchTimeout);
                const query = e.target.value;
                
                if (query.length >= 3) {
                    searchTimeout = setTimeout(() => {
                        searchAddress(query);
                    }, 500);
                } else {
                    document.getElementById('address-suggestions').style.display = 'none';
                }
            });
            
            // Hide suggestions when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.goapi-autocomplete')) {
                    document.getElementById('address-suggestions').style.display = 'none';
                }
            });
            
            // Initialize map
            initMap();
        });
    </script>
</body>
</html>

