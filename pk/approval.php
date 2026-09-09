<?php
require_once '../shared/config/database.php';
require_once '../shared/config/auth.php';
require_once '../shared/config/email.php';
requirePKLogin();

$conn = getDBConnection();
$pk_id = $_SESSION['user_id'];
$pk_nama = $_SESSION['nama'];
$message = '';
$message_type = '';

// Handle approval action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $klien_id = $_POST['klien_id'] ?? 0;
    $action = $_POST['action'] ?? '';
    
    // Get klien info for email
    $stmt = $conn->prepare("SELECT email, nama FROM klien_users WHERE id = ?");
    $stmt->bind_param("i", $klien_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $klien_info = $result->fetch_assoc();
    $stmt->close();
    
    // Debug Email
    if (empty($klien_info['email'])) {
        error_log("WARNING: Client ID $klien_id has NO EMAIL in database!");
    } else {
        error_log("INFO: Attempting to send email to Client ID $klien_id: " . $klien_info['email']);
    }
    
    if ($action === 'approve') {
        // Verify that this klien is assigned to this PK
        $verify_stmt = $conn->prepare("SELECT pk_id FROM klien_users WHERE id = ?");
        $verify_stmt->bind_param("i", $klien_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        $klien_data = $verify_result->fetch_assoc();
        $verify_stmt->close();
        
        if (!$klien_data || $klien_data['pk_id'] != $pk_id) {
            $message = 'Anda tidak memiliki izin untuk menyetujui akun ini!';
            $message_type = 'error';
        } else {
            $approved_at = date('Y-m-d H:i:s');
            $stmt = $conn->prepare("UPDATE klien_users SET status_approval = 'approved', approved_by = ?, approved_at = ? WHERE id = ? AND pk_id = ?");
            $stmt->bind_param("isii", $pk_id, $approved_at, $klien_id, $pk_id);
            if ($stmt->execute()) {
                // Insert ke data_klien untuk dashboard PK
                $klien_stmt = $conn->prepare("SELECT nama, no_registrasi, alamat FROM klien_users WHERE id = ?");
                $klien_stmt->bind_param("i", $klien_id);
                $klien_stmt->execute();
                $klien_result = $klien_stmt->get_result();
                $klien_detail = $klien_result->fetch_assoc();
                $klien_stmt->close();
                
                if ($klien_detail) {
                    // Check if already exists in data_klien
                    $check_stmt = $conn->prepare("SELECT id FROM data_klien WHERE no_registrasi = ? AND pk_id = ?");
                    $check_stmt->bind_param("si", $klien_detail['no_registrasi'], $pk_id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    $check_stmt->close();
                    
                    if ($check_result->num_rows === 0) {
                        // Insert new record
                        $insert_stmt = $conn->prepare("INSERT INTO data_klien (pk_id, nama, no_registrasi, alamat, status, tanggal_mulai) VALUES (?, ?, ?, ?, 'Aktif', CURDATE())");
                        $insert_stmt->bind_param("isss", $pk_id, $klien_detail['nama'], $klien_detail['no_registrasi'], $klien_detail['alamat']);
                        $insert_stmt->execute();
                        $insert_stmt->close();
                    }
                }
                
                // Send approval email
                if (!empty($klien_info['email'])) {
                    $email_sent = sendApprovalEmail($klien_info['email'], $klien_info['nama'], 'approved', $pk_nama);
                    if ($email_sent) {
                        $message = 'Akun klien berhasil disetujui! Email notifikasi telah dikirim ke ' . $klien_info['email'];
                    } else {
                        $message = 'Akun klien berhasil disetujui! (Email gagal dikirim)';
                    }
                } else {
                    $message = 'Akun klien berhasil disetujui!';
                }
                $message_type = 'success';
            } else {
                $message = 'Gagal menyetujui akun: ' . $conn->error;
                $message_type = 'error';
            }
            $stmt->close();
        }
    } elseif ($action === 'reject') {
        // Verify that this klien is assigned to this PK
        $verify_stmt = $conn->prepare("SELECT pk_id FROM klien_users WHERE id = ?");
        $verify_stmt->bind_param("i", $klien_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        $klien_data = $verify_result->fetch_assoc();
        $verify_stmt->close();
        
        if (!$klien_data || $klien_data['pk_id'] != $pk_id) {
            $message = 'Anda tidak memiliki izin untuk menolak akun ini!';
            $message_type = 'error';
        } else {
            $approved_at = date('Y-m-d H:i:s');
            $stmt = $conn->prepare("UPDATE klien_users SET status_approval = 'rejected', approved_by = ?, approved_at = ? WHERE id = ? AND pk_id = ?");
            $stmt->bind_param("isii", $pk_id, $approved_at, $klien_id, $pk_id);
            if ($stmt->execute()) {
                // Send rejection email
                if (!empty($klien_info['email'])) {
                    $email_sent = sendApprovalEmail($klien_info['email'], $klien_info['nama'], 'rejected', $pk_nama);
                    if ($email_sent) {
                        $message = 'Akun klien ditolak! Email notifikasi telah dikirim ke ' . $klien_info['email'];
                    } else {
                        $message = 'Akun klien ditolak! (Email gagal dikirim)';
                    }
                } else {
                    $message = 'Akun klien ditolak!';
                }
                $message_type = 'success';
            } else {
                $message = 'Gagal menolak akun: ' . $conn->error;
                $message_type = 'error';
            }
            $stmt->close();
        }
    }
}

// Get pending users yang terikat dengan PK ini
$pending_users = $conn->query("
    SELECT id, username, nama, no_registrasi, alamat, no_telepon, email, created_at
    FROM klien_users 
    WHERE status_approval = 'pending' AND pk_id = $pk_id
    ORDER BY created_at DESC
");

// Get all users with status yang terikat dengan PK ini
$all_users = $conn->query("
    SELECT ku.*, pk.nama as approved_by_name
    FROM klien_users ku
    LEFT JOIN pk_users pk ON ku.approved_by = pk.id
    WHERE ku.pk_id = $pk_id
    ORDER BY ku.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approval Klien - BAPAS</title>
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
    <script src="../shared/js/auto-notification-refresh.js"></script>
    
    <script>
        tailwind.config = {
            darkMode: false,
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
        /* Glassmorphism */
        .glass-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
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

    <div class="p-4 sm:ml-64 mt-20">
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
                        <div class="inline-flex items-center space-x-2 bg-white/20 backdrop-blur-md px-4 py-1.5 rounded-full text-xs font-semibold border border-white/20 mb-3 shadow-sm">
                            <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                            <span>Verifikasi Akun</span>
                        </div>
                        <h1 class="text-3xl font-bold mb-2 tracking-tight">Approval Klien</h1>
                        <p class="text-sky-100 text-sm max-w-xl leading-relaxed">Verifikasi pendaftaran akun klien baru untuk memberikan akses sistem.</p>
                    </div>
                    <div class="hidden md:block">
                        <div class="w-16 h-16 bg-white/20 backdrop-blur-md rounded-2xl flex items-center justify-center border border-white/20 shadow-lg transform rotate-3 group-hover:rotate-6 transition-transform duration-500">
                            <i class="fas fa-user-check text-3xl text-white"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Approvals -->
            <div class="mb-8">
                <div class="flex items-center space-x-3 mb-6">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center border border-amber-100 shadow-sm">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-lg text-slate-800">Menunggu Persetujuan</h3>
                        <p class="text-sm text-slate-500">Daftar akun yang belum diverifikasi</p>
                    </div>
                    <?php if ($pending_users->num_rows > 0): ?>
                        <span class="px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-bold border border-amber-200 shadow-sm animate-pulse">
                            <?php echo $pending_users->num_rows; ?> Pending
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="glass-card rounded-3xl shadow-xl overflow-hidden">
                    <div class="p-6">
                        <table id="pendingApprovalTable" class="w-full">
                            <thead>
                                <tr class="bg-sky-50/50 text-slate-700 text-left">
                                    <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider rounded-l-xl">No</th>
                                    <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider">Username</th>
                                    <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider">Nama Lengkap</th>
                                    <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider">No. Registrasi</th>
                                    <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider">Kontak</th>
                                    <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider">Waktu Daftar</th>
                                    <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider rounded-r-xl">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php
                                $no = 1;
                                $pending_users->data_seek(0);
                                if ($pending_users->num_rows > 0) {
                                    while ($user = $pending_users->fetch_assoc()) {
                                        echo "<tr class='hover:bg-sky-50/30 transition-colors duration-200 group'>
                                                <td class='px-6 py-4 text-sm font-medium text-gray-500'>$no</td>
                                                <td class='px-6 py-4'>
                                                    <div class='font-mono text-sm text-sky-600 bg-sky-50 px-2 py-1 rounded inline-block'>{$user['username']}</div>
                                                </td>
                                                <td class='px-6 py-4'>
                                                    <div class='font-bold text-gray-800 group-hover:text-sky-600 transition-colors'>{$user['nama']}</div>
                                                </td>
                                                <td class='px-6 py-4 text-sm text-gray-600'>{$user['no_registrasi']}</td>
                                                <td class='px-6 py-4'>
                                                    <div class='flex flex-col space-y-1'>
                                                        " . ($user['no_telepon'] ? "<div class='text-xs text-gray-500'><i class='fas fa-phone mr-2 w-4 text-gray-400'></i>{$user['no_telepon']}</div>" : "") . "
                                                        " . ($user['email'] ? "<div class='text-xs text-gray-500'><i class='fas fa-envelope mr-2 w-4 text-gray-400'></i>{$user['email']}</div>" : "") . "
                                                    </div>
                                                </td>
                                                <td class='px-6 py-4 text-sm text-gray-500'>" . date('d/m/Y H:i', strtotime($user['created_at'])) . "</td>
                                                <td class='px-6 py-4'>
                                                    <div class='flex items-center space-x-2'>
                                                        <button onclick=\"confirmAction('{$user['id']}', 'approve', '{$user['nama']}')\" 
                                                                class='w-9 h-9 rounded-xl bg-green-50 text-green-600 hover:bg-green-600 hover:text-white transition-all duration-300 flex items-center justify-center border border-green-200 hover:border-green-600 shadow-sm' title='Setujui'>
                                                            <i class='fas fa-check'></i>
                                                        </button>
                                                        <button onclick=\"confirmAction('{$user['id']}', 'reject', '{$user['nama']}')\" 
                                                                class='w-9 h-9 rounded-xl bg-red-50 text-red-600 hover:bg-red-600 hover:text-white transition-all duration-300 flex items-center justify-center border border-red-200 hover:border-red-600 shadow-sm' title='Tolak'>
                                                            <i class='fas fa-times'></i>
                                                        </button>
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

            <!-- History Table -->
            <div class="mb-12 fade-in-up" style="animation-delay: 0.1s;">
                <div class="flex items-center space-x-3 mb-6">
                    <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center border border-sky-100 shadow-sm">
                        <i class="fas fa-history"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-lg text-slate-800">Riwayat Persetujuan</h3>
                        <p class="text-sm text-slate-500">Semua riwayat pendaftaran akun</p>
                    </div>
                </div>

                <div class="glass-card rounded-3xl shadow-xl overflow-hidden">
                    <div class="p-6">
                        <table id="historyTable" class="w-full">
                            <thead>
                                <tr class="bg-sky-50/50 text-slate-700 text-left">
                                    <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider rounded-l-xl">No</th>
                                    <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider">Nama Lengkap</th>
                                    <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider">Diverifikasi Oleh</th>
                                    <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider">Waktu</th>
                                    <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider rounded-r-xl">Waktu Daftar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php
                                $no = 1;
                                $all_users->data_seek(0);
                                while ($user = $all_users->fetch_assoc()) {
                                    $status_class = '';
                                    $status_icon = '';
                                    
                                    if ($user['status_approval'] === 'approved') {
                                        $status_class = 'bg-green-50 text-green-700 border border-green-200';
                                        $status_icon = '<i class="fas fa-check-circle mr-1"></i>';
                                    } elseif ($user['status_approval'] === 'rejected') {
                                        $status_class = 'bg-red-50 text-red-700 border border-red-200';
                                        $status_icon = '<i class="fas fa-times-circle mr-1"></i>';
                                    } else {
                                        $status_class = 'bg-amber-50 text-amber-700 border border-amber-200';
                                        $status_icon = '<i class="fas fa-clock mr-1"></i>';
                                    }
                                    
                                    echo "<tr class='hover:bg-sky-50/30 transition-colors duration-200 group'>
                                            <td class='px-6 py-4 text-sm font-medium text-gray-500'>$no</td>
                                            <td class='px-6 py-4'>
                                                <div class='font-bold text-gray-800 group-hover:text-sky-600 transition-colors'>{$user['nama']}</div>
                                                <div class='text-xs text-gray-500'>{$user['username']}</div>
                                            </td>
                                            <td class='px-6 py-4'>
                                                <span class='px-3 py-1 rounded-full text-xs font-bold $status_class flex items-center w-fit shadow-sm'>
                                                    $status_icon " . ucfirst($user['status_approval']) . "
                                                </span>
                                            </td>
                                            <td class='px-6 py-4 text-sm text-gray-600'>" . ($user['approved_by_name'] ?? '-') . "</td>
                                            <td class='px-6 py-4 text-sm text-gray-500'>" . ($user['approved_at'] ? date('d/m/Y H:i', strtotime($user['approved_at'])) : '-') . "</td>
                                            <td class='px-6 py-4 text-sm text-gray-500'>" . date('d/m/Y H:i', strtotime($user['created_at'])) . "</td>
                                        </tr>";
                                    $no++;
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
    
    <!-- Hidden form for actions -->
    <form id="actionForm" method="POST" class="hidden">
        <input type="hidden" name="klien_id" id="form_klien_id">
        <input type="hidden" name="action" id="form_action">
    </form>

    <script>
        $(document).ready(function() {
            // Configuration for DataTables
            const dataTableConfig = {
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
                },
                dom: '<"flex flex-col md:flex-row justify-between items-center mb-4 space-y-2 md:space-y-0"lf>rt<"flex flex-col md:flex-row justify-between items-center mt-4 space-y-2 md:space-y-0"ip>',
                initComplete: function() {
                    // Custom styling for search input
                    $('.dt-search input').addClass('focus:ring-2 focus:ring-sky-500 focus:border-sky-500 block w-full sm:text-sm border-gray-300 rounded-xl px-4 py-2 shadow-sm');
                    $('.dt-length select').addClass('focus:ring-2 focus:ring-sky-500 focus:border-sky-500 block w-full sm:text-sm border-gray-300 rounded-xl px-4 py-2 shadow-sm');
                }
            };

            $('#pendingApprovalTable').DataTable(dataTableConfig);
            $('#historyTable').DataTable(dataTableConfig);
        });

        function confirmAction(id, action, name) {
            const title = action === 'approve' ? 'Setujui Akun?' : 'Tolak Akun?';
            const text = action === 'approve' 
                ? `Apakah Anda yakin ingin menyetujui akun <b>${name}</b>?` 
                : `Apakah Anda yakin ingin menolak akun <b>${name}</b>?`;
            const icon = action === 'approve' ? 'question' : 'warning';
            const confirmBtnColor = action === 'approve' ? '#10b981' : '#ef4444';
            const confirmBtnText = action === 'approve' ? 'Ya, Setujui!' : 'Ya, Tolak!';

            Swal.fire({
                title: title,
                html: text,
                icon: icon,
                showCancelButton: true,
                confirmButtonColor: confirmBtnColor,
                cancelButtonColor: '#6b7280',
                confirmButtonText: confirmBtnText,
                cancelButtonText: 'Batal',
                customClass: {
                    popup: 'rounded-2xl',
                    confirmButton: 'rounded-xl',
                    cancelButton: 'rounded-xl'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('form_klien_id').value = id;
                    document.getElementById('form_action').value = action;
                    document.getElementById('actionForm').submit();
                }
            });
        }
    </script>
</body>
</html>
