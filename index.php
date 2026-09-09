<?php
session_start();
// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'pk') {
        header("Location: pk/dashboard.php");
        exit;
    } elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'klien') {
        header("Location: klien/dashboard.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Pengawasan Digital - BAPAS Pekanbaru</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            900: '#0c4a6e',
                        },
                        secondary: {
                            500: '#6366f1',
                            600: '#4f46e5',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .hero-pattern {
            background-color: #0f172a;
            background-image: radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%), radial-gradient(at 50% 0%, hsla(225,39%,30%,1) 0, transparent 50%), radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%);
        }
        .blob {
            position: absolute;
            filter: blur(40px);
            z-index: 0;
            opacity: 0.4;
            animation: move 10s infinite alternate;
        }
        @keyframes move {
            from { transform: translate(0, 0) scale(1); }
            to { transform: translate(20px, -20px) scale(1.1); }
        }
    </style>
</head>
<body class="font-sans antialiased text-slate-800 bg-slate-50 selection:bg-primary-500 selection:text-white">

    <!-- Navbar -->
    <nav class="fixed w-full z-50 transition-all duration-300" id="navbar">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <div class="flex-shrink-0 flex items-center gap-3">
                    <img src="assets/images/kop.png" alt="Logo BAPAS" class="h-10 w-auto mr-3">
                    <div class="hidden md:block">
                        <span class="block text-lg font-bold text-white tracking-tight leading-none">BAPAS PEKANBARU</span>
                        <span class="block text-xs text-slate-300 font-medium tracking-wider">KEMENTERIAN IMIGRASI DAN PEMASYARAKATAN RI</span>
                    </div>
                </div>
                <div class="hidden md:flex space-x-8">
                    <a href="#beranda" class="text-slate-300 hover:text-white transition-colors text-sm font-semibold">Beranda</a>
                    <a href="#layanan" class="text-slate-300 hover:text-white transition-colors text-sm font-semibold">Layanan</a>
                    <a href="#akses" class="text-slate-300 hover:text-white transition-colors text-sm font-semibold">Akses Portal</a>
                </div>
                <div class="md:hidden">
                    <button class="text-white focus:outline-none">
                        <i class="fas fa-bars text-2xl"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="beranda" class="relative min-h-screen flex items-center justify-center pt-20 overflow-hidden hero-pattern">
        <div class="absolute inset-0 bg-[url('assets/images/bapas.jpeg')] bg-cover bg-center opacity-10"></div>
        <!-- Animated Background Elements -->
        <div class="absolute top-0 left-1/4 w-96 h-96 bg-primary-600 rounded-full blob mix-blend-multiply filter blur-3xl opacity-20 animate-blob"></div>
        <div class="absolute top-0 right-1/4 w-96 h-96 bg-secondary-600 rounded-full blob mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-2000"></div>
        <div class="absolute -bottom-32 left-1/3 w-96 h-96 bg-pink-600 rounded-full blob mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-4000"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 z-10 text-center">
            <div data-aos="fade-up" data-aos-duration="1000">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/10 backdrop-blur-sm border border-white/20 text-indigo-200 text-sm font-medium mb-6">
                    <span class="flex h-2 w-2 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
                    </span>
                    Sistem Informasi Pengawasan Digital
                </div>
                
                <h1 class="text-4xl md:text-6xl lg:text-7xl font-extrabold text-white tracking-tight mb-6 leading-tight">
                    Transformasi Digital <br>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary-400 to-secondary-400">Pengawasan Klien</span>
                </h1>
                
                <p class="mt-4 max-w-2xl mx-auto text-xl text-slate-300 leading-relaxed mb-10">
                    Memudahkan proses pembimbingan, pelaporan, dan pengawasan klien pemasyarakatan secara real-time, transparan, dan terintegrasi.
                </p>

                <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                    <a href="#akses" class="px-8 py-4 bg-white text-slate-900 rounded-full font-bold text-lg hover:bg-slate-100 transition-all shadow-lg hover:shadow-xl hover:-translate-y-1 flex items-center gap-2">
                        Mulai Sekarang <i class="fas fa-arrow-right"></i>
                    </a>
                    <a href="#layanan" class="px-8 py-4 bg-white/5 text-white border border-white/10 rounded-full font-bold text-lg hover:bg-white/10 transition-all backdrop-blur-sm">
                        Pelajari Lebih Lanjut
                    </a>
                </div>
            </div>

            <!-- Dashboard Preview / Illustration -->
            <div class="mt-16 relative mx-auto max-w-5xl" data-aos="fade-up" data-aos-delay="200">
                <div class="bg-slate-900/50 backdrop-blur-xl rounded-2xl p-2 border border-white/10 shadow-2xl">
                    <img src="assets/images/bapas.jpeg" alt="Dashboard Preview" class="rounded-xl w-full h-[300px] md:h-[500px] object-cover opacity-80 hover:opacity-100 transition-opacity duration-700">
                    
                    <!-- Floating Stats Cards -->
                    <div class="absolute -right-4 top-10 hidden md:block animate-bounce" style="animation-duration: 3s;">
                        <div class="bg-white p-4 rounded-xl shadow-xl flex items-center gap-3 border border-slate-100">
                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center text-green-600">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500 font-bold uppercase">Status</p>
                                <p class="text-sm font-bold text-slate-800">Terverifikasi</p>
                            </div>
                        </div>
                    </div>

                    <div class="absolute -left-4 bottom-20 hidden md:block animate-bounce" style="animation-duration: 4s;">
                        <div class="bg-white p-4 rounded-xl shadow-xl flex items-center gap-3 border border-slate-100">
                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center text-blue-600">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500 font-bold uppercase">Lokasi</p>
                                <p class="text-sm font-bold text-slate-800">Real-time GPS</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="layanan" class="py-24 bg-white relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16" data-aos="fade-up">
                <h2 class="text-primary-600 font-bold tracking-wide uppercase text-sm mb-3">Keunggulan Sistem</h2>
                <h3 class="text-3xl md:text-4xl font-bold text-slate-900 mb-4">Solusi Cerdas Pengawasan</h3>
                <p class="text-slate-600 max-w-2xl mx-auto">Kami menghadirkan fitur-fitur modern untuk menunjang efektivitas pembimbingan kemasyarakatan.</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="group p-8 rounded-3xl bg-slate-50 hover:bg-white hover:shadow-xl transition-all duration-300 border border-slate-100" data-aos="fade-up" data-aos-delay="100">
                    <div class="w-14 h-14 bg-blue-100 rounded-2xl flex items-center justify-center text-blue-600 mb-6 group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-laptop-medical text-2xl"></i>
                    </div>
                    <h4 class="text-xl font-bold text-slate-900 mb-3">Pelaporan Digital</h4>
                    <p class="text-slate-600 leading-relaxed">
                        Klien dapat melakukan wajib lapor secara daring tanpa harus datang ke kantor, menghemat waktu dan biaya.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="group p-8 rounded-3xl bg-slate-50 hover:bg-white hover:shadow-xl transition-all duration-300 border border-slate-100" data-aos="fade-up" data-aos-delay="200">
                    <div class="w-14 h-14 bg-sky-100 rounded-2xl flex items-center justify-center text-sky-600 mb-6 group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-map-marked-alt text-2xl"></i>
                    </div>
                    <h4 class="text-xl font-bold text-slate-900 mb-3">Geo-Tagging Lokasi</h4>
                    <p class="text-slate-600 leading-relaxed">
                        Memastikan keberadaan klien dengan verifikasi lokasi berbasis GPS yang akurat dan real-time saat pelaporan.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="group p-8 rounded-3xl bg-slate-50 hover:bg-white hover:shadow-xl transition-all duration-300 border border-slate-100" data-aos="fade-up" data-aos-delay="300">
                    <div class="w-14 h-14 bg-emerald-100 rounded-2xl flex items-center justify-center text-emerald-600 mb-6 group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-chart-line text-2xl"></i>
                    </div>
                    <h4 class="text-xl font-bold text-slate-900 mb-3">Monitoring Terpadu</h4>
                    <p class="text-slate-600 leading-relaxed">
                        Dashboard komprehensif bagi PK untuk memantau perkembangan dan kepatuhan klien secara mudah.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Role Selection / Access Portal -->
    <section id="akses" class="py-24 bg-slate-50 relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-slate-200/50 [mask-image:linear-gradient(0deg,white,rgba(255,255,255,0.6))] bg-[length:20px_20px]"></div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center mb-16" data-aos="fade-up">
                <h3 class="text-3xl md:text-4xl font-bold text-slate-900 mb-4">Pilih Akses Masuk</h3>
                <p class="text-slate-600 max-w-2xl mx-auto">Silakan pilih portal sesuai dengan peran Anda dalam sistem.</p>
            </div>

            <div class="grid md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                <!-- PK Card -->
                <div class="group relative bg-white rounded-3xl p-8 shadow-lg hover:shadow-2xl transition-all duration-300 border border-slate-200 overflow-hidden" data-aos="fade-right">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-indigo-50 rounded-bl-full -mr-8 -mt-8 group-hover:scale-150 transition-transform duration-500"></div>
                    
                    <div class="relative z-10 text-center">
                        <div class="w-20 h-20 bg-indigo-600 text-white rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-lg shadow-indigo-200 group-hover:rotate-6 transition-transform duration-300">
                            <i class="fas fa-user-tie text-3xl"></i>
                        </div>
                        <h4 class="text-2xl font-bold text-slate-900 mb-2">Pembimbing Kemasyarakatan</h4>
                        <p class="text-slate-500 mb-8">Login untuk petugas PK BAPAS</p>
                        
                        <div class="space-y-3">
                            <a href="pk/login.php" class="block w-full py-3.5 px-6 bg-slate-900 text-white rounded-xl font-bold hover:bg-slate-800 transition-colors shadow-lg hover:shadow-xl">
                                Login Petugas
                            </a>
                            <a href="pk/register.php" class="block w-full py-3.5 px-6 bg-white text-slate-900 border-2 border-slate-200 rounded-xl font-bold hover:border-slate-900 hover:bg-slate-50 transition-colors">
                                Registrasi Akun
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Klien Card -->
                <div class="group relative bg-white rounded-3xl p-8 shadow-lg hover:shadow-2xl transition-all duration-300 border border-slate-200 overflow-hidden" data-aos="fade-left">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-sky-50 rounded-bl-full -mr-8 -mt-8 group-hover:scale-150 transition-transform duration-500"></div>
                    
                    <div class="relative z-10 text-center">
                        <div class="w-20 h-20 bg-sky-500 text-white rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-lg shadow-sky-200 group-hover:-rotate-6 transition-transform duration-300">
                            <i class="fas fa-users text-3xl"></i>
                        </div>
                        <h4 class="text-2xl font-bold text-slate-900 mb-2">Klien Pemasyarakatan</h4>
                        <p class="text-slate-500 mb-8">Login untuk Wajib Lapor</p>
                        
                        <div class="space-y-3">
                            <a href="klien/login.php" class="block w-full py-3.5 px-6 bg-sky-600 text-white rounded-xl font-bold hover:bg-sky-700 transition-colors shadow-lg hover:shadow-xl">
                                Login Klien
                            </a>
                            <a href="klien/register.php" class="block w-full py-3.5 px-6 bg-white text-sky-600 border-2 border-sky-100 rounded-xl font-bold hover:border-sky-600 hover:bg-sky-50 transition-colors">
                                Daftar Baru
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-slate-900 text-slate-300 py-12 border-t border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-4 gap-8 mb-8">
                <div class="col-span-1 md:col-span-2">
                    <div class="flex items-center gap-3 mb-4">
                        <img src="assets/images/kop.png" alt="Logo" class="h-8 w-auto brightness-0 invert">
                        <span class="font-bold text-white text-lg">BAPAS PEKANBARU</span>
                    </div>
                    <p class="text-slate-400 leading-relaxed max-w-sm">
                        Sistem informasi digital untuk meningkatkan kualitas layanan dan pengawasan pemasyarakatan yang transparan dan akuntabel.
                    </p>
                </div>
                <div>
                    <h5 class="text-white font-bold mb-4">Tautan Cepat</h5>
                    <ul class="space-y-2">
                        <li><a href="#beranda" class="hover:text-white transition-colors">Beranda</a></li>
                        <li><a href="#layanan" class="hover:text-white transition-colors">Layanan</a></li>
                        <li><a href="#akses" class="hover:text-white transition-colors">Portal Masuk</a></li>
                    </ul>
                </div>
                <div>
                    <h5 class="text-white font-bold mb-4">Kontak</h5>
                    <ul class="space-y-2">
                        <li class="flex items-center gap-2"><i class="fas fa-map-marker-alt w-5"></i> Pekanbaru, Riau</li>
                        <li class="flex items-center gap-2"><i class="fas fa-envelope w-5"></i> info@bapas-pekanbaru.go.id</li>
                    </ul>
                </div>
            </div>
            <div class="pt-8 border-t border-slate-800 text-center text-sm">
                <p>&copy; <?php echo date('Y'); ?> Balai Pemasyarakatan Kelas I Pekanbaru. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- AOS Animation Script -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            once: true,
            offset: 50,
            duration: 800,
        });

        // Navbar Scroll Effect
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('bg-slate-900/90', 'backdrop-blur-md', 'shadow-lg');
            } else {
                navbar.classList.remove('bg-slate-900/90', 'backdrop-blur-md', 'shadow-lg');
            }
        });
    </script>
</body>
</html>