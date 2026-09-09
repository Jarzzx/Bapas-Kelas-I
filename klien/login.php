<?php
require_once '../shared/config/database.php';
require_once '../shared/config/auth.php';

// Redirect if already logged in
if (isKlienLoggedIn()) {
    // Check approval status
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT status_approval FROM klien_users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    if ($user['status_approval'] === 'approved') {
        header('Location: dashboard.php');
    } else {
        header('Location: pending.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id, username, password, nama, status_approval FROM klien_users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            // Password verification menggunakan MD5
            if (md5($password) === $user['password']) {
                // Regenerate session ID for security
                session_regenerate_id(true);
                // Check approval status
                if ($user['status_approval'] === 'approved') {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_type'] = 'klien';
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['nama'] = $user['nama'];
                    header('Location: dashboard.php');
                    exit;
                } else {
                    // Set session but redirect to pending
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_type'] = 'klien';
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['nama'] = $user['nama'];
                    header('Location: pending.php');
                    exit;
                }
            } else {
                $error = 'Username atau password salah!';
            }
        } else {
            $error = 'Username atau password salah!';
        }
        $stmt->close();
        $conn->close();
    } else {
        $error = 'Silakan isi semua field!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Klien - BAPAS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
    </style>
</head>
<body class="bg-gradient-to-br from-primary-50 via-sky-50 to-secondary-50 min-h-screen flex items-center justify-center py-8 font-sans antialiased text-slate-800">
    <div class="w-full max-w-md px-4 relative">
        <!-- Decorative Elements -->
        <div class="absolute -top-20 -left-20 w-64 h-64 bg-primary-200/30 rounded-full blur-3xl float-animation"></div>
        <div class="absolute -bottom-20 -right-20 w-64 h-64 bg-secondary-200/30 rounded-full blur-3xl float-animation-delayed"></div>
        
        <div class="bg-white/80 backdrop-blur-xl rounded-3xl shadow-2xl shadow-sky-900/10 p-8 relative z-10 border border-white/50 fade-in-up">
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-primary-500 to-secondary-500 rounded-2xl mb-6 shadow-lg shadow-primary-500/30 transform rotate-3 hover:rotate-6 transition-transform duration-300">
                    <i class="fas fa-user-friends text-4xl text-white"></i>
                </div>
                <h1 class="text-3xl font-bold text-slate-800 mb-2 tracking-tight">Selamat Datang</h1>
                <p class="text-slate-500 text-lg font-medium">Portal Klien Pemasyarakatan</p>
                <div class="mt-4 inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 text-emerald-600 border border-emerald-100">
                    <i class="fas fa-shield-alt text-xs"></i>
                    <span class="text-xs font-semibold uppercase tracking-wider">Aman & Terpercaya</span>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl mb-6 flex items-start gap-3 animate-pulse">
                    <i class="fas fa-exclamation-circle mt-0.5"></i>
                    <span class="text-sm font-medium"><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-5">
                <div class="group">
                    <label class="block text-slate-700 font-semibold mb-2 text-sm group-focus-within:text-primary-600 transition-colors">
                        Username
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-user text-slate-400 group-focus-within:text-primary-500 transition-colors"></i>
                        </div>
                        <input type="text" name="username" required 
                               class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border-2 border-slate-100 text-slate-900 rounded-xl focus:outline-none focus:bg-white focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all duration-300 placeholder-slate-400"
                               placeholder="Masukkan username Anda">
                    </div>
                </div>

                <div class="group">
                    <label class="block text-slate-700 font-semibold mb-2 text-sm group-focus-within:text-primary-600 transition-colors">
                        Password
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-slate-400 group-focus-within:text-primary-500 transition-colors"></i>
                        </div>
                        <input type="password" name="password" required 
                               class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border-2 border-slate-100 text-slate-900 rounded-xl focus:outline-none focus:bg-white focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all duration-300 placeholder-slate-400"
                               placeholder="Masukkan password Anda">
                    </div>
                </div>

                <button type="submit" 
                        class="w-full bg-gradient-to-r from-primary-600 to-secondary-600 text-white py-4 px-6 rounded-xl hover:from-primary-700 hover:to-secondary-700 focus:ring-4 focus:ring-primary-500/30 transition-all duration-300 font-bold text-lg shadow-lg shadow-primary-500/30 hover:shadow-xl hover:shadow-primary-500/40 hover:-translate-y-0.5 active:translate-y-0">
                    <span class="flex items-center justify-center gap-2">
                        Masuk ke Portal <i class="fas fa-arrow-right text-sm"></i>
                    </span>
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-slate-100 text-center">
                <p class="text-slate-500 mb-3 text-sm">Belum punya akun?</p>
                <a href="register.php" class="inline-flex items-center justify-center gap-2 text-primary-600 hover:text-primary-700 font-bold hover:underline decoration-2 underline-offset-4 transition-all">
                    <i class="fas fa-user-plus"></i> Daftar Sekarang
                </a>
            </div>

            <div class="mt-6 text-center">
                <a href="../index.php" class="text-slate-400 hover:text-slate-600 text-sm font-medium transition-colors flex items-center justify-center gap-2">
                    <i class="fas fa-long-arrow-alt-left"></i> Kembali ke Halaman Utama
                </a>
            </div>

            <div class="mt-8">
                <div class="bg-gradient-to-r from-primary-50/50 to-secondary-50/50 rounded-xl p-4 text-center border border-primary-100/50">
                    <p class="text-[10px] uppercase tracking-widest text-slate-500 mb-1 font-semibold">
                        Waktu Server
                    </p>
                    <div id="current-time" class="text-2xl font-mono font-bold text-primary-600 tracking-tight"></div>
                    <div id="current-date" class="text-xs text-slate-500 font-medium mt-1"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('id-ID', { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit',
                hour12: false 
            });
            const dateString = now.toLocaleDateString('id-ID', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            document.getElementById('current-time').textContent = timeString;
            document.getElementById('current-date').textContent = dateString;
        }
        
        updateTime();
        setInterval(updateTime, 1000);
    </script>
</body>
</html>

