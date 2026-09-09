<?php
require_once '../shared/config/database.php';
require_once '../shared/config/auth.php';
requirePKLogin();

$conn = getDBConnection();
$pk_id = $_SESSION['user_id'];
$nama = $_SESSION['nama'];

// Handle CRUD operations
$message = '';
$message_type = '';

// Handle URL success message
if (isset($_GET['success']) && isset($_GET['msg'])) {
    $message = urldecode($_GET['msg']);
    $message_type = $_GET['success'] == '1' ? 'success' : 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update') {
            $id = $_POST['id'] ?? 0;
            $status = $_POST['status'] ?? 'Aktif';
            $jenis_integrasi = $_POST['jenis_integrasi'] ?? '';
            $no_registrasi_perkara = $_POST['no_registrasi_perkara'] ?? '';
            
            $stmt = $conn->prepare("UPDATE data_klien SET status = ?, jenis_integrasi = ?, no_registrasi_perkara = ? WHERE id = ? AND pk_id = ?");
            $stmt->bind_param("sssii", $status, $jenis_integrasi, $no_registrasi_perkara, $id, $pk_id);
            
            if ($stmt->execute()) {
                $message = 'Data klien berhasil diupdate!';
                $message_type = 'success';
                header('Location: data-klien.php?success=1&msg=' . urlencode($message));
                exit;
            } else {
                $message = 'Gagal mengupdate data klien: ' . $conn->error;
                $message_type = 'error';
            }
            $stmt->close();
        }
        
        if ($_POST['action'] === 'delete') {
            $id = $_POST['id'] ?? 0;
            
            $stmt = $conn->prepare("DELETE FROM data_klien WHERE id = ? AND pk_id = ?");
            $stmt->bind_param("ii", $id, $pk_id);
            
            if ($stmt->execute()) {
                $message = 'Data klien berhasil dihapus!';
                $message_type = 'success';
                header('Location: data-klien.php?success=1&msg=' . urlencode($message));
                exit;
            } else {
                $message = 'Gagal menghapus data klien!';
                $message_type = 'error';
            }
            $stmt->close();
        }
    }
}

// Get all clients for this PK with complete biodata and document info
$klien_list = $conn->query("
    SELECT 
        dk.*,
        ku.nik, ku.foto_klien, ku.riwayat_pendidikan, ku.agama, ku.jenis_kelamin,
        ku.status_pernikahan, ku.pekerjaan, ku.tempat_lahir, ku.tanggal_lahir,
        ku.biodata_lengkap, ku.email, ku.no_telepon, ku.alamat as alamat_klien,
        ku.latitude as lat_klien, ku.longitude as lng_klien
    FROM data_klien dk
    LEFT JOIN klien_users ku ON dk.no_registrasi = ku.no_registrasi AND ku.pk_id = $pk_id
    WHERE dk.pk_id = $pk_id
    ORDER BY dk.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Klien - BAPAS</title>
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
    <script src="../shared/js/sweetalert-loader.js?v=<?php echo time(); ?>"></script>
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
<body class="bg-gray-50 font-sans text-gray-800 min-h-screen selection:bg-sky-100 selection:text-sky-700 transition-colors duration-300">
    
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
                            <span>Manajemen Data</span>
                        </div>
                        <h1 class="text-3xl font-bold mb-2 tracking-tight">Data Klien</h1>
                        <p class="text-sky-100 text-sm max-w-lg leading-relaxed font-medium">Kelola data klien yang dibimbing, pantau masa bimbingan, dan status integrasi.</p>
                    </div>
                    <div class="hidden md:block p-4 bg-white/10 rounded-2xl border border-white/20 backdrop-blur-sm shadow-inner group-hover:bg-white/20 transition-all duration-300">
                        <i class="fas fa-users text-4xl text-white"></i>
                    </div>
                </div>
            </div>

            <!-- Clients Table -->
            <div class="bg-white/80 backdrop-blur-xl rounded-3xl shadow-xl border border-white/50 overflow-hidden fade-in-up" style="animation-delay: 0.1s;">
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table id="dataKlienTable" class="w-full">
                            <thead>
                                <tr class="bg-sky-50/50 text-slate-700 text-left">
                                    <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider rounded-l-xl">No</th>
                                    <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider">Nama</th>
                                    <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider">No. Berkas</th>
                                    <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider">NIK</th>
                                    <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider">Pasal Pidana</th>
                                    <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider">Masa Bimbingan</th>
                                    <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider">Jenis Integrasi</th>
                                    <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider rounded-r-xl">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php
                                $no = 1;
                                if ($klien_list->num_rows > 0) {
                                    while ($klien = $klien_list->fetch_assoc()) {
                                        $status_color = $klien['status'] === 'Aktif' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600';
                                        $masa_bimbingan = '';
                                        if ($klien['masa_bimbingan_tahun'] !== null) {
                                            $masa_bimbingan = $klien['masa_bimbingan_tahun'] . ' tahun ' . 
                                                             $klien['masa_bimbingan_bulan'] . ' bulan ' . 
                                                             $klien['masa_bimbingan_hari'] . ' hari';
                                        } else {
                                            $masa_bimbingan = '<span class="text-slate-400 italic">Belum diset</span>';
                                        }
                                        $pasal_pidana = !empty($klien['pasal_pidana']) ? 
                                            (strlen($klien['pasal_pidana']) > 50 ? substr($klien['pasal_pidana'], 0, 50) . '...' : $klien['pasal_pidana']) : 
                                            '<span class="text-slate-400">-</span>';
                                        $jenis_integrasi_text = '';
                                        if ($klien['jenis_integrasi'] === 'PB') $jenis_integrasi_text = 'Pembebasan Bersyarat (PB)';
                                        elseif ($klien['jenis_integrasi'] === 'CMB') $jenis_integrasi_text = 'Cuti Bersyarat (CMB)';
                                        elseif ($klien['jenis_integrasi'] === 'CMJB') $jenis_integrasi_text = 'Cuti Menjelang Bebas (CMJB)';
                                        else $jenis_integrasi_text = '<span class="text-slate-400">-</span>';
                                        
                                        echo "<tr class='hover:bg-sky-50/50 transition-colors duration-200 group'>
                                                <td class='px-6 py-4 text-sm font-medium text-slate-500'>$no</td>
                                                <td class='px-6 py-4'>
                                                    <div class='font-bold text-slate-800 group-hover:text-sky-600 transition-colors'>{$klien['nama']}</div>
                                                </td>
                                                <td class='px-6 py-4'>
                                                    <span class='font-mono text-xs text-sky-600 bg-sky-50 rounded-lg px-2 py-1'>{$klien['no_registrasi']}</span>
                                                </td>
                                                <td class='px-6 py-4 font-mono text-xs text-slate-500'>" . ($klien['nik'] ?? '-') . "</td>
                                                <td class='px-6 py-4 text-sm text-slate-600'>" . $pasal_pidana . "</td>
                                                <td class='px-6 py-4 text-sm text-slate-600'>$masa_bimbingan</td>
                                                <td class='px-6 py-4 text-sm text-slate-600'>$jenis_integrasi_text</td>
                                                <td class='px-6 py-4'>
                                                    <span class='px-3 py-1 rounded-full text-xs font-bold $status_color'>{$klien['status']}</span>
                                                </td>
                                                <td class='px-6 py-4'>
                                                    <div class='flex gap-2'>
                                                        <button onclick='viewDetail(" . htmlspecialchars(json_encode($klien), ENT_QUOTES, 'UTF-8') . ")' 
                                                                class='w-8 h-8 rounded-lg bg-sky-50 text-sky-600 hover:bg-sky-600 hover:text-white transition-all duration-300 flex items-center justify-center' 
                                                                title='Lihat Detail'>
                                                            <i class='fas fa-eye'></i>
                                                        </button>
                                                        <button onclick='openModal(\"edit\", " . htmlspecialchars(json_encode($klien), ENT_QUOTES, 'UTF-8') . ")' 
                                                            class='w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white transition-all duration-300 flex items-center justify-center' 
                                                            title='Edit'>
                                                            <i class='fas fa-edit'></i>
                                                        </button>
                                                        <button onclick='confirmDelete(" . $klien['id'] . ", \"" . htmlspecialchars($klien['nama'], ENT_QUOTES, 'UTF-8') . "\")' 
                                                                class='w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-600 hover:text-white transition-all duration-300 flex items-center justify-center' 
                                                                title='Hapus'>
                                                            <i class='fas fa-trash-alt'></i>
                                                        </button>
                                                    </div>
                                                </td>
                                              </tr>";
                                        $no++;
                                    }
                                } else {
                                    echo "<tr><td colspan='9' class='px-6 py-12 text-center text-gray-500'>
                                        <div class='flex flex-col items-center justify-center'>
                                            <div class='w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4'>
                                                <i class='fas fa-users-slash text-3xl opacity-50'></i>
                                            </div>
                                            <p class='text-lg font-medium'>Belum ada data klien</p>
                                        </div>
                                    </td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Create/Edit -->
    <div id="modal" class="hidden fixed inset-0 bg-gray-900/60 backdrop-blur-md z-50 flex items-center justify-center p-4 transition-all duration-300">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto transform transition-all scale-100 border border-white/50 relative">
            <div class="absolute top-0 right-0 w-32 h-32 bg-sky-50 rounded-bl-full -mr-8 -mt-8 opacity-50"></div>
            
            <div class="p-8 border-b border-gray-100 bg-gray-50/50 relative z-10">
                <h2 id="modal-title" class="text-2xl font-bold text-gray-800 tracking-tight flex items-center">
                    <span class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center mr-3 border border-sky-100 shadow-sm">
                        <i class="fas fa-edit"></i>
                    </span>
                    <span id="modal-title-text">Edit Data Klien</span>
                </h2>
            </div>
            <form id="modal-form" method="POST" class="p-8 relative z-10">
                <input type="hidden" name="action" id="form-action">
                <input type="hidden" name="id" id="form-id">
                
                <div class="bg-amber-50 border-l-4 border-amber-500 p-4 mb-8 rounded-r-xl shadow-sm flex items-start gap-3">
                    <i class="fas fa-info-circle text-amber-500 text-xl mt-0.5"></i>
                    <p class="text-sm text-amber-800 leading-relaxed">
                        <strong>Catatan:</strong> Hanya Status Klien dan Jenis Integrasi yang dapat diubah. Data biodata terintegrasi dengan data pengguna.
                    </p>
                </div>

                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">No. Registrasi Perkara</label>
                        <input type="text" name="no_registrasi_perkara" id="no_registrasi_perkara" 
                               class="w-full px-4 py-3 rounded-xl border-gray-200 focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all placeholder:text-gray-400"
                               placeholder="Masukkan nomor registrasi perkara">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Status Klien</label>
                            <select name="status" id="status" class="w-full px-4 py-3 rounded-xl border-gray-200 focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all cursor-pointer">
                                <option value="Aktif">Aktif</option>
                                <option value="Tidak Aktif">Tidak Aktif</option>
                                <option value="Selesai">Selesai</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Jenis Integrasi</label>
                            <select name="jenis_integrasi" id="jenis_integrasi" class="w-full px-4 py-3 rounded-xl border-gray-200 focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all cursor-pointer">
                                <option value="">Pilih Jenis Integrasi</option>
                                <option value="PB">Pembebasan Bersyarat (PB)</option>
                                <option value="CB">Cuti Bersyarat (CB)</option>
                                <option value="CMB">Cuti Menjelang Bebas (CMB)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-3">
                    <button type="button" onclick="closeModal()" 
                            class="px-5 py-2.5 rounded-xl text-sm font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 transition-all">
                        Batal
                    </button>
                    <button type="submit" 
                            class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-sky-600 hover:bg-sky-700 shadow-lg shadow-sky-500/30 transition-all">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Detail Klien -->
    <div id="detailModal" class="hidden fixed inset-0 bg-gray-900/60 backdrop-blur-md z-50 flex items-center justify-center p-4 transition-all duration-300">
        <div class="bg-white rounded-3xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto transform transition-all scale-100 border border-white/50 relative">
            <div class="absolute top-0 right-0 w-48 h-48 bg-sky-50 rounded-bl-full -mr-8 -mt-8 opacity-50"></div>
            
            <div class="p-8 border-b border-gray-100 bg-gray-50/50 relative z-10 flex justify-between items-start">
                <div>
                    <div class="inline-flex items-center space-x-2 bg-sky-50 px-3 py-1 rounded-full text-xs font-medium text-sky-600 border border-sky-100 mb-3">
                        <i class="fas fa-id-card"></i>
                        <span>Detail Profil Klien</span>
                    </div>
                    <h2 class="text-3xl font-bold text-gray-800 tracking-tight" id="detail-nama">Nama Klien</h2>
                    <p class="text-gray-500 mt-1" id="detail-registrasi">No. Registrasi</p>
                </div>
                <button onclick="closeDetailModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="p-8 relative z-10">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <!-- Kolom Kiri: Foto & Status -->
                    <div class="md:col-span-1 space-y-6">
                        <div class="aspect-[3/4] rounded-2xl overflow-hidden bg-gray-100 shadow-inner relative group">
                            <img id="detail-foto" src="" alt="Foto Klien" class="w-full h-full object-cover">
                            <div class="absolute inset-0 bg-black/20 group-hover:bg-black/0 transition-all"></div>
                        </div>
                        
                        <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
                            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Status Klien</h3>
                            <span id="detail-status" class="inline-flex px-3 py-1 rounded-full text-sm font-bold bg-green-100 text-green-700 border border-green-200">Aktif</span>
                        </div>
                    </div>

                    <!-- Kolom Kanan: Detail Info -->
                    <div class="md:col-span-2 space-y-8">
                        <!-- Informasi Pribadi -->
                        <div>
                            <h3 class="flex items-center text-lg font-bold text-gray-800 mb-4">
                                <span class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center mr-3">
                                    <i class="fas fa-user"></i>
                                </span>
                                Informasi Pribadi
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-4 gap-x-6">
                                <div>
                                    <p class="text-xs text-gray-400 mb-1">NIK</p>
                                    <p id="detail-nik" class="font-medium text-gray-700">-</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400 mb-1">Tempat, Tanggal Lahir</p>
                                    <p id="detail-ttl" class="font-medium text-gray-700">-</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400 mb-1">Jenis Kelamin</p>
                                    <p id="detail-jk" class="font-medium text-gray-700">-</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400 mb-1">Agama</p>
                                    <p id="detail-agama" class="font-medium text-gray-700">-</p>
                                </div>
                                <div class="sm:col-span-2">
                                    <p class="text-xs text-gray-400 mb-1">Alamat</p>
                                    <p id="detail-alamat" class="font-medium text-gray-700">-</p>
                                </div>
                            </div>
                        </div>

                        <!-- Informasi Hukum -->
                        <div>
                            <h3 class="flex items-center text-lg font-bold text-gray-800 mb-4">
                                <span class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center mr-3">
                                    <i class="fas fa-gavel"></i>
                                </span>
                                Informasi Hukum
                            </h3>
                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <p class="text-xs text-gray-400 mb-1">No. Registrasi Perkara</p>
                                    <p id="detail-no-perkara" class="font-medium text-gray-700">-</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400 mb-1">Pasal Pidana</p>
                                    <p id="detail-pasal" class="font-medium text-gray-700">-</p>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-xs text-gray-400 mb-1">Masa Bimbingan</p>
                                        <p id="detail-masa" class="font-medium text-gray-700">-</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-400 mb-1">Jenis Integrasi</p>
                                        <p id="detail-integrasi" class="font-medium text-gray-700">-</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Kontak -->
                        <div>
                             <h3 class="flex items-center text-lg font-bold text-gray-800 mb-4">
                                <span class="w-8 h-8 rounded-lg bg-green-50 text-green-600 flex items-center justify-center mr-3">
                                    <i class="fas fa-address-book"></i>
                                </span>
                                Kontak
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <p class="text-xs text-gray-400 mb-1">Email</p>
                                    <p id="detail-email" class="font-medium text-gray-700">-</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400 mb-1">No. Telepon</p>
                                    <p id="detail-telp" class="font-medium text-gray-700">-</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="p-6 border-t border-gray-100 bg-gray-50 flex justify-end">
                <button onclick="closeDetailModal()" class="px-6 py-2.5 rounded-xl bg-white border border-gray-200 text-gray-700 font-semibold hover:bg-gray-50 transition-all shadow-sm">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <script>
        // Initialize DataTables
        $(document).ready(function() {
            $('#dataKlienTable').DataTable({
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

        // Modal Functions
        function openModal(mode, data = null) {
            const modal = document.getElementById('modal');
            const title = document.getElementById('modal-title-text');
            const formAction = document.getElementById('form-action');
            const formId = document.getElementById('form-id');
            const status = document.getElementById('status');
            const jenisIntegrasi = document.getElementById('jenis_integrasi');
            const noRegistrasiPerkara = document.getElementById('no_registrasi_perkara');

            modal.classList.remove('hidden');
            
            if (mode === 'edit' && data) {
                title.textContent = 'Edit Data Klien';
                formAction.value = 'update';
                formId.value = data.id;
                status.value = data.status || 'Aktif';
                jenisIntegrasi.value = data.jenis_integrasi || '';
                noRegistrasiPerkara.value = data.no_registrasi_perkara || '';
            }
        }

        function closeModal() {
            document.getElementById('modal').classList.add('hidden');
        }

        function confirmDelete(id, nama) {
            Swal.fire({
                title: 'Hapus Data Klien?',
                text: `Anda akan menghapus data klien "${nama}". Tindakan ini tidak dapat dibatalkan!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                background: '#fff',
                color: '#000'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="${id}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
        
        // Detail Modal Functions
        function viewDetail(data) {
            const modal = document.getElementById('detailModal');
            
            // Populate data
            document.getElementById('detail-nama').textContent = data.nama || '-';
            document.getElementById('detail-registrasi').textContent = data.no_registrasi || '-';
            
            // Foto logic
            let fotoPath;
            if (data.foto_klien) {
                // Handle various path formats stored in DB
                if (data.foto_klien.includes('uploads/')) {
                    fotoPath = '../' + data.foto_klien;
                } else {
                    fotoPath = '../uploads/foto_klien/' + data.foto_klien;
                }
            } else {
                fotoPath = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(data.nama) + '&background=random';
            }
            document.getElementById('detail-foto').src = fotoPath;
            
            document.getElementById('detail-status').textContent = data.status || 'Aktif';
            
            // Pribadi
            document.getElementById('detail-nik').textContent = data.nik || '-';
            document.getElementById('detail-ttl').textContent = (data.tempat_lahir || '') + ', ' + (data.tanggal_lahir || '');
            document.getElementById('detail-jk').textContent = data.jenis_kelamin || '-';
            document.getElementById('detail-agama').textContent = data.agama || '-';
            document.getElementById('detail-alamat').textContent = data.alamat_klien || '-';
            
            // Hukum
            document.getElementById('detail-no-perkara').textContent = data.no_registrasi_perkara || '-';
            document.getElementById('detail-pasal').textContent = data.pasal_pidana || '-';
            
            let masa = '-';
            if(data.masa_bimbingan_tahun) {
                masa = `${data.masa_bimbingan_tahun} thn ${data.masa_bimbingan_bulan} bln ${data.masa_bimbingan_hari} hr`;
            }
            document.getElementById('detail-masa').textContent = masa;
            document.getElementById('detail-integrasi').textContent = data.jenis_integrasi || '-';
            
            // Kontak
            document.getElementById('detail-email').textContent = data.email || '-';
            document.getElementById('detail-telp').textContent = data.no_telepon || '-';
            
            modal.classList.remove('hidden');
        }
        
        function closeDetailModal() {
            document.getElementById('detailModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('modal');
            const detailModal = document.getElementById('detailModal');
            if (event.target == modal) {
                closeModal();
            }
            if (event.target == detailModal) {
                closeDetailModal();
            }
        }
    </script>
</body>
</html>
