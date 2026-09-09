<?php
require_once '../shared/config/database.php';
require_once '../shared/config/auth.php';

// Redirect if already logged in
if (isPKLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $nama = trim($_POST['nama'] ?? '');
    $nip = trim($_POST['nip'] ?? '');
    
    // Validation
    if (empty($username) || empty($password) || empty($nama)) {
        $message = 'Username, Password, dan Nama harus diisi!';
        $message_type = 'error';
    } elseif ($password !== $password_confirm) {
        $message = 'Password dan konfirmasi password tidak cocok!';
        $message_type = 'error';
    } elseif (strlen($password) < 6) {
        $message = 'Password minimal 6 karakter!';
        $message_type = 'error';
    } elseif (strlen($username) < 3) {
        $message = 'Username minimal 3 karakter!';
        $message_type = 'error';
    } else {
        $conn = getDBConnection();
        
        // Check if username exists
        $stmt = $conn->prepare("SELECT id FROM pk_users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = 'Username sudah digunakan!';
            $message_type = 'error';
            $stmt->close();
        } else {
            $stmt->close();
            
            // Check if NIP already exists (if provided)
            if (!empty($nip)) {
                $stmt = $conn->prepare("SELECT id FROM pk_users WHERE nip = ?");
                $stmt->bind_param("s", $nip);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $message = 'NIP sudah terdaftar!';
                    $message_type = 'error';
                    $stmt->close();
                } else {
                    $stmt->close();
                    
                    // Insert new PK user
                    $password_md5 = md5($password);
                    $nip_value = !empty($nip) ? $nip : null;
                    
                    $stmt = $conn->prepare("INSERT INTO pk_users (username, password, nama, nip) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $username, $password_md5, $nama, $nip_value);
                    
                    if ($stmt->execute()) {
                        $message = 'Akun PK berhasil dibuat! Silakan login untuk melanjutkan.';
                        $message_type = 'success';
                        // Clear form
                        $_POST = array();
                    } else {
                        $message = 'Gagal membuat akun: ' . $conn->error;
                        $message_type = 'error';
                    }
                    $stmt->close();
                }
            } else {
                // Insert new PK user without NIP
                $password_md5 = md5($password);
                
                $stmt = $conn->prepare("INSERT INTO pk_users (username, password, nama, nip) VALUES (?, ?, ?, NULL)");
                $stmt->bind_param("sss", $username, $password_md5, $nama);
                
                if ($stmt->execute()) {
                    $message = 'Akun PK berhasil dibuat! Silakan login untuk melanjutkan.';
                    $message_type = 'success';
                    // Clear form
                    $_POST = array();
                } else {
                    $message = 'Gagal membuat akun: ' . $conn->error;
                    $message_type = 'error';
                }
                $stmt->close();
            }
        }
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun PK - BAPAS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    animation: {
                        'fade-in-up': 'fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards',
                        'pulse-soft': 'pulseSoft 3s infinite',
                    },
                    keyframes: {
                        fadeInUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                        pulseSoft: {
                            '0%, 100%': { opacity: '1', transform: 'scale(1)' },
                            '50%': { opacity: '0.8', transform: 'scale(1.05)' },
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gradient-to-br from-slate-900 via-sky-900 to-slate-900 min-h-screen flex items-center justify-center p-4">
    <!-- Decorative Elements -->
    <div class="fixed top-0 left-0 w-full h-full overflow-hidden pointer-events-none z-0">
        <div class="absolute -top-[10%] -left-[5%] w-[40%] h-[40%] rounded-full bg-sky-500/20 blur-3xl animate-pulse"></div>
        <div class="absolute top-[20%] -right-[5%] w-[30%] h-[30%] rounded-full bg-blue-500/20 blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
        <div class="absolute -bottom-[10%] left-[20%] w-[35%] h-[35%] rounded-full bg-sky-500/20 blur-3xl animate-pulse" style="animation-delay: 2s;"></div>
    </div>

    <div class="w-full max-w-2xl relative z-10 animate-fade-in-up">
        <div class="bg-white/90 backdrop-blur-xl rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.12)] border border-white/50 p-8 overflow-hidden">
            <!-- Decorative Header Gradient -->
            <div class="absolute top-0 left-0 right-0 h-2 bg-gradient-to-r from-sky-500 via-blue-500 to-sky-500"></div>

            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-gradient-to-br from-sky-100 to-blue-100 mb-6 shadow-lg shadow-sky-100/50 transform rotate-3 hover:rotate-6 transition-transform duration-300">
                    <i class="fas fa-user-plus text-4xl bg-gradient-to-br from-sky-600 to-blue-600 bg-clip-text text-transparent"></i>
                </div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2 tracking-tight">Daftar Akun PK</h1>
                <p class="text-gray-500 font-medium">Pembimbing Kemasyarakatan</p>
            </div>

            <?php if ($message): ?>
                <div class="mb-6 px-4 py-3 rounded-xl flex items-start shadow-sm animate-pulse <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-100 text-green-600' : 'bg-red-50 border border-red-100 text-red-600'; ?>">
                    <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mt-1 mr-3 text-lg"></i>
                    <span class="text-sm font-medium"><?php echo htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($message_type !== 'success'): ?>
            <form method="POST" action="" class="space-y-5">
                <div class="grid md:grid-cols-2 gap-6">
                    <div class="group">
                        <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">Username *</label>
                        <div class="relative transition-all duration-300 transform group-focus-within:scale-[1.01]">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-user text-gray-400 group-focus-within:text-sky-500 transition-colors"></i>
                            </div>
                            <input type="text" name="username" required minlength="3"
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                   class="w-full pl-11 pr-4 py-3.5 border border-gray-200 rounded-xl focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 transition-all duration-300 bg-gray-50/50 focus:bg-white text-gray-800 font-medium placeholder-gray-400 shadow-sm"
                                   placeholder="Min. 3 karakter">
                        </div>
                    </div>

                    <div class="group">
                        <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">Nama Lengkap *</label>
                        <div class="relative transition-all duration-300 transform group-focus-within:scale-[1.01]">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-id-card text-gray-400 group-focus-within:text-sky-500 transition-colors"></i>
                            </div>
                            <input type="text" name="nama" required
                                   value="<?php echo htmlspecialchars($_POST['nama'] ?? ''); ?>"
                                   class="w-full pl-11 pr-4 py-3.5 border border-gray-200 rounded-xl focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 transition-all duration-300 bg-gray-50/50 focus:bg-white text-gray-800 font-medium placeholder-gray-400 shadow-sm"
                                   placeholder="Nama lengkap">
                        </div>
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-6">
                    <div class="group">
                        <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">Password *</label>
                        <div class="relative transition-all duration-300 transform group-focus-within:scale-[1.01]">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400 group-focus-within:text-sky-500 transition-colors"></i>
                            </div>
                            <input type="password" name="password" required minlength="6"
                                   class="w-full pl-11 pr-4 py-3.5 border border-gray-200 rounded-xl focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 transition-all duration-300 bg-gray-50/50 focus:bg-white text-gray-800 font-medium placeholder-gray-400 shadow-sm"
                                   placeholder="Min. 6 karakter">
                        </div>
                    </div>

                    <div class="group">
                        <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">Konfirmasi Password *</label>
                        <div class="relative transition-all duration-300 transform group-focus-within:scale-[1.01]">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400 group-focus-within:text-sky-500 transition-colors"></i>
                            </div>
                            <input type="password" name="password_confirm" required
                                   class="w-full pl-11 pr-4 py-3.5 border border-gray-200 rounded-xl focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 transition-all duration-300 bg-gray-50/50 focus:bg-white text-gray-800 font-medium placeholder-gray-400 shadow-sm"
                                   placeholder="Ulangi password">
                        </div>
                    </div>
                </div>

                <div class="group">
                    <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">NIP (Opsional)</label>
                    <div class="relative transition-all duration-300 transform group-focus-within:scale-[1.01]">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-id-badge text-gray-400 group-focus-within:text-sky-500 transition-colors"></i>
                        </div>
                        <input type="text" name="nip"
                               value="<?php echo htmlspecialchars($_POST['nip'] ?? ''); ?>"
                               class="w-full pl-11 pr-4 py-3.5 border border-gray-200 rounded-xl focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 transition-all duration-300 bg-gray-50/50 focus:bg-white text-gray-800 font-medium placeholder-gray-400 shadow-sm"
                               placeholder="Nomor Induk Pegawai">
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-sky-600 to-blue-600 hover:from-sky-700 hover:to-blue-700 text-white py-3.5 px-6 rounded-xl transition-all duration-300 font-bold text-lg shadow-lg shadow-sky-500/30 transform hover:-translate-y-1 hover:shadow-sky-500/40 flex items-center justify-center group">
                        <span>Daftar Sekarang</span>
                        <i class="fas fa-arrow-right ml-3 group-hover:translate-x-1 transition-transform"></i>
                    </button>
                </div>
            </form>
            <?php else: ?>
                <div class="text-center space-y-4">
                    <p class="text-gray-600">Akun anda telah berhasil dibuat.</p>
                    <a href="login.php" class="inline-block bg-sky-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-sky-700 transition-colors shadow-lg shadow-sky-500/30">
                        Login Sekarang
                    </a>
                </div>
            <?php endif; ?>

            <div class="mt-8 text-center space-y-4">
                <?php if ($message_type !== 'success'): ?>
                <p class="text-gray-500 text-sm">
                    Sudah punya akun? 
                    <a href="login.php" class="text-sky-600 hover:text-sky-800 font-bold hover:underline transition-all ml-1">
                        Login disini
                    </a>
                </p>
                <?php endif; ?>
                <div class="pt-4 border-t border-gray-100">
                    <a href="../index.php" class="inline-flex items-center text-gray-400 hover:text-sky-600 text-sm font-medium transition-colors duration-300 group">
                        <i class="fas fa-long-arrow-alt-left mr-2 group-hover:-translate-x-1 transition-transform"></i>
                        Kembali ke Halaman Utama
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Footer Info -->
        <p class="text-center text-slate-400 text-xs mt-8 font-medium">
            &copy; <?php echo date('Y'); ?> BAPAS Pekanbaru. All rights reserved.
        </p>
    </div>
</body>
</html>