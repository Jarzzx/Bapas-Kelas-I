<?php
$current_page = basename($_SERVER['PHP_SELF']);

function isActive($page_name) {
    global $current_page;
    // Sky theme for active state
    return $current_page === $page_name ? 'bg-sky-50 text-sky-600 border-r-4 border-sky-600' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900';
}

// Get pending approvals count if not already set
if (!isset($pending_count) && isset($conn) && $conn instanceof mysqli) {
    // Check if connection is still open
    if (@$conn->ping()) {
        $pending_count = $conn->query("SELECT COUNT(*) as total FROM klien_users WHERE status_approval = 'pending' AND pk_id = $pk_id")->fetch_assoc()['total'];
    }
}
?>

<aside id="logo-sidebar" class="fixed top-0 left-0 z-40 w-64 h-screen pt-20 transition-transform -translate-x-full bg-white/80 backdrop-blur-xl border-r border-sky-100 sm:translate-x-0" aria-label="Sidebar">
   <div class="h-full px-3 pb-4 overflow-y-auto bg-transparent custom-scrollbar">
      <ul class="space-y-2 font-medium">
         <li>
            <a href="dashboard.php" class="flex items-center p-3 rounded-lg group <?php echo isActive('dashboard.php'); ?>">
               <i class="fas fa-home w-5 h-5 transition duration-75 group-hover:text-sky-600"></i>
               <span class="ms-3">Dashboard</span>
            </a>
         </li>
         
         <div class="pt-4 pb-2">
            <span class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Manajemen Klien</span>
         </div>
         
         <li>
            <a href="data-klien.php" class="flex items-center p-3 rounded-lg group <?php echo isActive('data-klien.php'); ?>">
               <i class="fas fa-users w-5 h-5 transition duration-75 group-hover:text-sky-600"></i>
               <span class="ms-3">Data Klien</span>
            </a>
         </li>
         <li>
            <a href="approval.php" class="flex items-center p-3 rounded-lg group <?php echo isActive('approval.php'); ?>">
               <div class="relative">
                   <i class="fas fa-user-check w-5 h-5 transition duration-75 group-hover:text-sky-600"></i>
                   <?php if (isset($pending_count) && $pending_count > 0): ?>
                   <div class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white"></div>
                   <?php endif; ?>
               </div>
               <span class="ms-3 flex-1 whitespace-nowrap">Persetujuan Klien</span>
               <?php if (isset($pending_count) && $pending_count > 0): ?>
               <span class="inline-flex items-center justify-center w-3 h-3 p-3 ms-3 text-sm font-medium text-red-800 bg-red-100 rounded-full"><?php echo $pending_count; ?></span>
               <?php endif; ?>
            </a>
         </li>
         <li>
            <a href="lengkapi-dokumen.php" class="flex items-center p-3 rounded-lg group <?php echo isActive('lengkapi-dokumen.php'); ?>">
               <i class="fas fa-file-contract w-5 h-5 transition duration-75 group-hover:text-sky-600"></i>
               <span class="ms-3">Kelengkapan Dokumen</span>
            </a>
         </li>

         <div class="pt-4 pb-2">
            <span class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Aktivitas Bimbingan</span>
         </div>

         <li>
            <a href="buat-jadwal-bimbingan.php" class="flex items-center p-3 rounded-lg group <?php echo isActive('buat-jadwal-bimbingan.php'); ?>">
               <i class="fas fa-calendar-plus w-5 h-5 transition duration-75 group-hover:text-sky-600"></i>
               <span class="ms-3">Buat Jadwal</span>
            </a>
         </li>
         <li>
            <a href="laporan-bimbingan.php" class="flex items-center p-3 rounded-lg group <?php echo isActive('laporan-bimbingan.php'); ?> <?php echo isActive('view-laporan-bimbingan.php'); ?>">
               <i class="fas fa-clipboard-list w-5 h-5 transition duration-75 group-hover:text-sky-600"></i>
               <span class="ms-3">Laporan Bimbingan</span>
            </a>
         </li>
         <li>
            <a href="laporan-pengawasan.php" class="flex items-center p-3 rounded-lg group <?php echo isActive('laporan-pengawasan.php'); ?> <?php echo isActive('view-laporan-pengawasan.php'); ?>">
               <i class="fas fa-binoculars w-5 h-5 transition duration-75 group-hover:text-sky-600"></i>
               <span class="ms-3">Laporan Pengawasan</span>
            </a>
         </li>
         <li>
            <a href="verifikasi-laporan.php" class="flex items-center p-3 rounded-lg group <?php echo isActive('verifikasi-laporan.php'); ?>">
               <i class="fas fa-check-double w-5 h-5 transition duration-75 group-hover:text-sky-600"></i>
               <span class="ms-3">Verifikasi Laporan</span>
            </a>
         </li>

         <div class="pt-4 pb-2">
            <span class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Akun</span>
         </div>

         <li>
            <a href="logout.php" class="flex items-center p-3 rounded-lg text-red-600 hover:bg-red-50 group">
               <i class="fas fa-sign-out-alt w-5 h-5 transition duration-75 group-hover:text-red-700"></i>
               <span class="ms-3">Keluar</span>
            </a>
         </li>
      </ul>
   </div>
</aside>