<?php
require_once '../shared/config/database.php';
require_once '../shared/config/auth.php';
requirePKLogin();

$conn = getDBConnection();
$pk_id = $_SESSION['user_id'];
$nama = $_SESSION['nama'];

// Handle form submission
$message = '';
$message_type = '';

// Check for session message
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    $message_type = 'success';
    unset($_SESSION['success_message']);
}

// Get clients list
$klien_list = $conn->query("SELECT id, nama, no_registrasi FROM data_klien WHERE pk_id = $pk_id ORDER BY nama");

// Get all reports
$laporan_list = $conn->query("
    SELECT lb.*, dk.nama as klien_nama, dk.no_registrasi,
           jb.tanggal_bimbingan as jadwal_tanggal_bimbingan,
           jb.materi_bimbingan as materi_jadwal,
           jb.jenis_bimbingan as jadwal_jenis_bimbingan,
           (SELECT nama FROM pk_users WHERE id = lb.pk_id) as nama_pk
    FROM laporan_bimbingan lb
    JOIN data_klien dk ON lb.klien_id = dk.id
    LEFT JOIN jadwal_bimbingan jb ON lb.jadwal_bimbingan_id = jb.id
    WHERE lb.pk_id = $pk_id
    ORDER BY COALESCE(jb.tanggal_bimbingan, lb.tanggal_bimbingan, lb.tanggal_laporan) DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Bimbingan - BAPAS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- DataTables & jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.tailwindcss.css">
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.tailwindcss.js"></script>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../shared/js/sweetalert-loader.js"></script>
    
    <script>
        tailwind.config = {
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
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in { animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .fade-in-up { animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards; }

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

    <div class="p-4 sm:ml-64 mt-14">
        <div class="max-w-7xl mx-auto fade-in">
            
            <?php if ($message): ?>
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
                
                <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-6">
                    <div>
                        <div class="inline-flex items-center space-x-2 bg-white/20 backdrop-blur-md px-3 py-1 rounded-full text-xs font-medium text-white border border-white/20 mb-3 shadow-sm">
                            <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                            <span>Sistem Pengawasan Klien</span>
                        </div>
                        <h1 class="text-3xl font-bold mb-2 tracking-tight">Laporan Bimbingan</h1>
                        <p class="text-sky-100 text-sm max-w-lg leading-relaxed font-medium">Arsip lengkap dan riwayat bimbingan klien pemasyarakatan.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="buat-jadwal-bimbingan.php" class="bg-white text-sky-700 hover:bg-sky-50 px-6 py-3 rounded-xl font-bold text-sm shadow-lg shadow-black/10 transition-all duration-300 flex items-center transform hover:-translate-y-0.5">
                            <i class="fas fa-plus mr-2"></i>
                            Buat Laporan Baru
                        </a>
                    </div>
                </div>
            </div>

            <!-- Reports List -->
            <div class="bg-white/80 backdrop-blur-xl rounded-3xl shadow-xl border border-white/50 overflow-hidden fade-in-up" style="animation-delay: 0.1s;">
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table id="laporanBimbinganTable" class="w-full">
                            <thead>
                                <tr class="bg-sky-50/50 text-slate-700 text-left">
                                    <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider rounded-l-xl">No</th>
                                    <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider">Klien</th>
                                    <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider">Tanggal</th>
                                    <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider">Bentuk</th>
                                    <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider">Materi</th>
                                    <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider">Hasil</th>
                                    <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider rounded-r-xl">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php
                                $no = 1;
                                if ($laporan_list->num_rows > 0) {
                                    while ($laporan = $laporan_list->fetch_assoc()) {
                                        $tgl_source = !empty($laporan['jadwal_tanggal_bimbingan']) ? $laporan['jadwal_tanggal_bimbingan'] : $laporan['tanggal_bimbingan'];
                                        $tanggal_bimbingan = $tgl_source ? date('d/m/Y', strtotime($tgl_source)) : '-';
                                        
                                        $bentuk_source = !empty($laporan['jadwal_jenis_bimbingan']) ? $laporan['jadwal_jenis_bimbingan'] : $laporan['bentuk_pembimbingan'];
                                        $bentuk_text = '';
                                        $bentuk_class = 'bg-slate-100 text-slate-600';
                                        
                                        if ($bentuk_source === 'tatap_muka') {
                                            $bentuk_text = 'Tatap Muka';
                                            $bentuk_class = 'bg-sky-50 text-sky-700 border border-sky-100';
                                        } elseif ($bentuk_source === 'daring') {
                                            $bentuk_text = 'Daring';
                                            $bentuk_class = 'bg-cyan-50 text-cyan-700 border border-cyan-100';
                                        } elseif ($bentuk_source === 'kunjungan_rumah') {
                                            $bentuk_text = 'Kunjungan Rumah';
                                            $bentuk_class = 'bg-orange-50 text-orange-700 border border-orange-100';
                                        } else {
                                            $bentuk_text = '-';
                                        }
                                        
                                        // Determine Materi Bimbingan
                                        $materi_real = !empty($laporan['materi_jadwal']) ? $laporan['materi_jadwal'] : $laporan['materi_bimbingan'];
                                        $materi_short = $materi_real ? (strlen($materi_real) > 50 ? substr($materi_real, 0, 50) . '...' : $materi_real) : '-';
                                        
                                        $hasil_short = $laporan['hasil_bimbingan'] ? (strlen($laporan['hasil_bimbingan']) > 50 ? substr($laporan['hasil_bimbingan'], 0, 50) . '...' : $laporan['hasil_bimbingan']) : '-';
                                        
                                        echo "<tr class='hover:bg-sky-50/50 transition-colors duration-200 group'>
                                                <td class='px-6 py-4 text-sm font-medium text-slate-500'>$no</td>
                                                <td class='px-6 py-4'>
                                                    <div class='flex items-center'>
                                                        <div class='w-8 h-8 rounded-full bg-sky-50 text-sky-600 flex items-center justify-center mr-3 font-bold text-xs border border-sky-100'>
                                                            " . substr($laporan['klien_nama'], 0, 1) . "
                                                        </div>
                                                        <div>
                                                            <div class='font-bold text-slate-800 text-sm'>{$laporan['klien_nama']}</div>
                                                            <div class='text-xs text-slate-500'>{$laporan['no_registrasi']}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class='px-6 py-4'>
                                                    <div class='text-sm text-slate-600 flex items-center'>
                                                        <i class='far fa-calendar-alt mr-2 text-slate-400'></i>
                                                        $tanggal_bimbingan
                                                    </div>
                                                </td>
                                                <td class='px-6 py-4'>
                                                    <span class='px-2.5 py-1 rounded-full text-xs font-bold $bentuk_class'>$bentuk_text</span>
                                                </td>
                                                <td class='px-6 py-4'>
                                                    <div class='max-w-xs text-sm text-slate-600 truncate' title='" . htmlspecialchars($materi_real ?? '') . "'>$materi_short</div>
                                                </td>
                                                <td class='px-6 py-4'>
                                                    <div class='max-w-xs text-sm text-slate-600 truncate' title='" . htmlspecialchars($laporan['hasil_bimbingan'] ?? '') . "'>$hasil_short</div>
                                                </td>
                                                <td class='px-6 py-4'>
                                                    <div class='flex gap-2 opacity-80 group-hover:opacity-100 transition-opacity'>
                                                        <a href='edit-laporan-bimbingan.php?id={$laporan['id']}' 
                                                           class='w-8 h-8 flex items-center justify-center bg-amber-50 text-amber-600 border border-amber-200 hover:bg-amber-100 rounded-lg transition-all shadow-sm'
                                                           title='Edit Laporan'>
                                                            <i class='fas fa-edit text-xs'></i>
                                                        </a>
                                                        <a href='view-laporan-bimbingan.php?id={$laporan['id']}' 
                                                           target='_blank'
                                                           class='w-8 h-8 flex items-center justify-center bg-sky-50 text-sky-600 border border-sky-200 hover:bg-sky-100 rounded-lg transition-all shadow-sm'
                                                           title='Lihat Laporan'>
                                                            <i class='fas fa-eye text-xs'></i>
                                                        </a>
                                                    </div>
                                                </td>
                                              </tr>";
                                        $no++;
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

    <script>
        $(document).ready(function() {
            $('#laporanBimbinganTable').DataTable({
                responsive: true,
                language: {
                    search: "Cari:",
                    lengthMenu: "Tampilkan _MENU_ data",
                    info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                    infoEmpty: "Tidak ada data yang ditampilkan",
                    infoFiltered: "(difilter dari _MAX_ total data)",
                    paginate: {
                        first: "Pertama",
                        last: "Terakhir",
                        next: "Selanjutnya",
                        previous: "Sebelumnya"
                    },
                    zeroRecords: "Data tidak ditemukan"
                }
            });
        });
    </script>
</body>
</html>
