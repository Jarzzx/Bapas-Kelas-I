<?php
require_once '../shared/config/database.php';
require_once '../shared/config/auth.php';

// Redirect if already logged in
if (isPKLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id, username, password, nama FROM pk_users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            $stored_password = trim($user['password']);
            $input_password_md5 = md5(trim($password));
            $is_md5_format = (strlen($stored_password) === 32 && ctype_xdigit($stored_password));
            
            // Password verification menggunakan MD5
            if ($is_md5_format && $input_password_md5 === $stored_password) {
                // Regenerate session ID for security
                session_regenerate_id(true);
                // Password match dengan MD5
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_type'] = 'pk';
                $_SESSION['username'] = $user['username'];
                $_SESSION['nama'] = $user['nama'];
                header('Location: dashboard.php');
                exit;
            } else if (!$is_md5_format) {
                // Password bukan MD5, coba update ke MD5
                $new_md5 = md5(trim($password));
                $update_stmt = $conn->prepare("UPDATE pk_users SET password = ? WHERE id = ?");
                $update_stmt->bind_param("si", $new_md5, $user['id']);
                if ($update_stmt->execute()) {
                    // Regenerate session ID for security
                    session_regenerate_id(true);
                    // Setelah update, set session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_type'] = 'pk';
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['nama'] = $user['nama'];
                    $update_stmt->close();
                    header('Location: dashboard.php');
                    exit;
                }
                $update_stmt->close();
                $error = 'Password format tidak valid. Silakan gunakan reset-pk-password.php untuk memperbaiki.';
            } else {
                // Password format MD5 tapi tidak match
                // Coba juga dengan password yang di-trim
                $password_trimmed = trim($password);
                $input_md5_trimmed = md5($password_trimmed);
                if ($input_md5_trimmed === $stored_password) {
                    // Regenerate session ID for security
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_type'] = 'pk';
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['nama'] = $user['nama'];
                    header('Location: dashboard.php');
                    exit;
                }
                $error = 'Username atau password salah! Pastikan password menggunakan format yang benar.';
            }
        } else {
            $error = 'Username tidak ditemukan!';
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
    <title>Login PK - BAPAS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

    <div class="w-full max-w-md relative z-10 animate-fade-in-up">
        <div class="bg-white/90 backdrop-blur-xl rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.12)] border border-white/50 p-8 overflow-hidden">
            <!-- Decorative Header Gradient -->
            <div class="absolute top-0 left-0 right-0 h-2 bg-gradient-to-r from-sky-500 via-blue-500 to-sky-500"></div>

            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-gradient-to-br from-sky-100 to-blue-100 mb-6 shadow-lg shadow-sky-100/50 transform rotate-3 hover:rotate-6 transition-transform duration-300">
                    <i class="fas fa-user-tie text-4xl bg-gradient-to-br from-sky-600 to-blue-600 bg-clip-text text-transparent"></i>
                </div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2 tracking-tight">Login Pembimbing</h1>
                <p class="text-gray-500 font-medium">Pembimbing Kemasyarakatan</p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-100 text-red-600 px-4 py-3 rounded-xl mb-6 flex items-start shadow-sm animate-pulse">
                    <i class="fas fa-exclamation-circle mt-1 mr-3 text-lg"></i>
                    <span class="text-sm font-medium"><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-5">
                <div class="group">
                    <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">Username</label>
                    <div class="relative transition-all duration-300 transform group-focus-within:scale-[1.01]">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-user text-gray-400 group-focus-within:text-sky-500 transition-colors"></i>
                        </div>
                        <input type="text" name="username" required 
                               class="w-full pl-11 pr-4 py-3.5 border border-gray-200 rounded-xl focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 transition-all duration-300 bg-gray-50/50 focus:bg-white text-gray-800 font-medium placeholder-gray-400 shadow-sm"
                               placeholder="Masukkan username anda">
                    </div>
                </div>

                <div class="group">
                    <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">Password</label>
                    <div class="relative transition-all duration-300 transform group-focus-within:scale-[1.01]">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400 group-focus-within:text-sky-500 transition-colors"></i>
                        </div>
                        <input type="password" name="password" required 
                               class="w-full pl-11 pr-4 py-3.5 border border-gray-200 rounded-xl focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 transition-all duration-300 bg-gray-50/50 focus:bg-white text-gray-800 font-medium placeholder-gray-400 shadow-sm"
                               placeholder="Masukkan password anda">
                    </div>
                </div>

                <button type="submit" 
                        class="w-full bg-gradient-to-r from-sky-600 to-blue-600 hover:from-sky-700 hover:to-blue-700 text-white py-3.5 px-6 rounded-xl transition-all duration-300 font-bold text-lg shadow-lg shadow-sky-500/30 transform hover:-translate-y-1 hover:shadow-sky-500/40 flex items-center justify-center group">
                    <span>Masuk Aplikasi</span>
                    <i class="fas fa-arrow-right ml-3 group-hover:translate-x-1 transition-transform"></i>
                </button>
            </form>

            <div class="mt-8 text-center space-y-4">
                <p class="text-gray-500 text-sm">
                    Belum punya akun? 
                    <a href="register.php" class="text-sky-600 hover:text-sky-800 font-bold hover:underline transition-all ml-1">
                        Daftar Pembimbing
                    </a>
                </p>
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