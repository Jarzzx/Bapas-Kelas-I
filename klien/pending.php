<?php
require_once '../shared/config/database.php';
require_once '../shared/config/auth.php';

// Only show to logged in klien users
if (!isKlienLoggedIn()) {
    header('Location: login.php');
    exit;
}

$conn = getDBConnection();
$klien_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT status_approval, nama FROM klien_users WHERE id = ?");
$stmt->bind_param("i", $klien_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
$conn->close();

// If approved, redirect to dashboard
if ($user['status_approval'] === 'approved') {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menunggu Persetujuan - BAPAS</title>
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
        body {
            font-family: 'Inter', sans-serif;
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        @keyframes pulse-soft {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.05); }
        }
        .pulse-soft {
            animation: pulse-soft 3s ease-in-out infinite;
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
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-sky-50 to-slate-100 min-h-screen flex items-center justify-center py-8 px-4">
    <!-- Decorative Elements -->
    <div class="fixed top-0 left-0 w-full h-full overflow-hidden pointer-events-none z-0">
        <div class="absolute -top-[10%] -left-[5%] w-[40%] h-[40%] rounded-full bg-sky-200/20 blur-3xl animate-pulse"></div>
        <div class="absolute top-[20%] -right-[5%] w-[30%] h-[30%] rounded-full bg-teal-200/20 blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
        <div class="absolute -bottom-[10%] left-[20%] w-[35%] h-[35%] rounded-full bg-indigo-200/20 blur-3xl animate-pulse" style="animation-delay: 2s;"></div>
    </div>

    <div class="container mx-auto max-w-lg relative z-10 animate-fade-in-up">
        <div class="bg-white/80 backdrop-blur-xl rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-white/50 p-8 text-center overflow-hidden">
            <!-- Decorative Header Gradient -->
            <div class="absolute top-0 left-0 right-0 h-2 bg-gradient-to-r from-sky-400 to-teal-400"></div>

            <!-- Icon -->
            <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-gradient-to-br from-sky-100 to-teal-100 mb-8 pulse-soft shadow-lg shadow-sky-100/50">
                <i class="fas fa-clock text-4xl bg-gradient-to-br from-sky-600 to-teal-600 bg-clip-text text-transparent"></i>
            </div>

            <h1 class="text-2xl font-bold text-slate-800 mb-3 tracking-tight">
                Dokumen Sedang Ditinjau
            </h1>

            <p class="text-slate-500 mb-8 leading-relaxed">
                Hai, <span class="font-semibold text-slate-700"><?php echo htmlspecialchars($user['nama']); ?></span>
            </p>

            <div class="bg-sky-50/80 border border-sky-100 p-6 rounded-xl mb-6 text-left transform transition-all hover:shadow-md duration-300">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 rounded-full bg-sky-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                        <i class="fas fa-info text-sky-600"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-sky-900 mb-1">
                            Status Akun
                        </p>
                        <?php if ($user['status_approval'] === 'pending'): ?>
                            <div class="flex items-center gap-2 mb-2">
                                <span class="relative flex h-3 w-3">
                                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-sky-400 opacity-75"></span>
                                  <span class="relative inline-flex rounded-full h-3 w-3 bg-sky-500"></span>
                                </span>
                                <span class="text-sky-700 font-medium text-sm">Menunggu Persetujuan</span>
                            </div>
                            <p class="text-slate-600 text-sm leading-relaxed">
                                Akun Anda sedang dalam proses peninjauan oleh Pembimbing Kemasyarakatan. 
                                Anda akan dapat mengakses sistem setelah akun disetujui.
                            </p>
                        <?php elseif ($user['status_approval'] === 'rejected'): ?>
                            <div class="flex items-center gap-2 mb-2">
                                <i class="fas fa-times-circle text-red-500"></i>
                                <span class="text-red-700 font-medium text-sm">Ditolak</span>
                            </div>
                            <p class="text-slate-600 text-sm leading-relaxed">
                                Maaf, akun Anda telah ditolak. Silakan hubungi administrator untuk informasi lebih lanjut.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="bg-slate-50 border border-slate-100 p-4 rounded-xl mb-8 text-left">
                <div class="flex gap-3">
                    <i class="fas fa-lightbulb text-amber-400 mt-1"></i>
                    <p class="text-slate-600 text-xs leading-relaxed">
                        <span class="font-semibold text-slate-700">Info:</span> Halaman ini akan otomatis memperbarui status Anda setiap 30 detik.
                    </p>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <button onclick="location.reload()" class="group bg-gradient-to-r from-sky-500 to-teal-500 text-white px-6 py-3 rounded-xl hover:shadow-lg hover:shadow-sky-500/30 transition-all duration-300 font-medium w-full sm:w-auto flex items-center justify-center gap-2">
                    <i class="fas fa-sync-alt group-hover:rotate-180 transition-transform duration-500"></i>
                    Refresh Status
                </button>
                <a href="logout.php" class="group bg-white border-2 border-slate-100 text-slate-600 px-6 py-3 rounded-xl hover:bg-slate-50 hover:border-slate-200 hover:text-slate-800 transition-all duration-300 font-medium w-full sm:w-auto flex items-center justify-center gap-2">
                    <i class="fas fa-sign-out-alt group-hover:-translate-x-1 transition-transform"></i>
                    Logout
                </a>
            </div>

            <!-- Auto refresh every 30 seconds -->
            <script>
                setTimeout(function() {
                    location.reload();
                }, 30000); // 30 seconds
            </script>
        </div>
        
        <div class="text-center mt-8">
             <p class="text-slate-400 text-sm">© <?php echo date('Y'); ?> Bapas Kelas I Tangerang</p>
        </div>
    </div>
</body>
</html>