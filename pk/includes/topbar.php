<nav class="fixed top-0 z-50 w-full bg-white/80 backdrop-blur-xl border-b border-sky-100">
  <div class="px-3 py-3 lg:px-5 lg:pl-3">
    <div class="flex items-center justify-between">
      <div class="flex items-center justify-start rtl:justify-end">
        <button data-drawer-target="logo-sidebar" data-drawer-toggle="logo-sidebar" aria-controls="logo-sidebar" type="button" class="inline-flex items-center p-2 text-sm text-gray-500 rounded-lg sm:hidden hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-200">
            <span class="sr-only">Open sidebar</span>
            <svg class="w-6 h-6" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
               <path clip-rule="evenodd" fill-rule="evenodd" d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 10.5a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5a.75.75 0 01-.75-.75zM2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10z"></path>
            </svg>
         </button>
        <a href="dashboard.php" class="flex ms-2 md:ms-4 items-center gap-3">
          <img src="../assets/images/kop.png" class="h-8 md:h-10 w-auto" alt="Logo" onerror="this.src='https://upload.wikimedia.org/wikipedia/commons/thumb/5/54/Logo_Kemenkumham.png/600px-Logo_Kemenkumham.png'">
          <div class="flex flex-col">
              <span class="self-center text-lg md:text-xl font-bold sm:text-2xl whitespace-nowrap text-slate-800 leading-none">BAPAS KELAS I PEKANBARU</span>
              <span class="text-[10px] md:text-xs text-slate-500 font-medium tracking-wider">PORTAL PEMBIMBING KEMASYARAKATAN</span>
          </div>
        </a>
      </div>
      <div class="flex items-center">
          <div class="flex items-center ms-3">
            <div class="hidden md:flex flex-col items-end mr-4">
                <span class="text-sm font-semibold text-gray-900"><?php echo isset($_SESSION['nama']) ? htmlspecialchars($_SESSION['nama']) : 'Petugas PK'; ?></span>
                <span class="text-xs text-gray-500">Pembimbing Kemasyarakatan</span>
            </div>
            <button type="button" class="flex text-sm bg-gray-800 rounded-full focus:ring-4 focus:ring-gray-300" aria-expanded="false" data-dropdown-toggle="dropdown-user">
              <span class="sr-only">Open user menu</span>
              <div class="w-8 h-8 md:w-10 md:h-10 rounded-full bg-sky-100 flex items-center justify-center text-sky-600 font-bold border-2 border-sky-200">
                  <?php 
                    $initials = 'PK';
                    if (isset($_SESSION['nama'])) {
                        $parts = explode(' ', $_SESSION['nama']);
                        $initials = strtoupper(substr($parts[0], 0, 1));
                        if (count($parts) > 1) {
                            $initials .= strtoupper(substr($parts[1], 0, 1));
                        }
                    }
                    echo $initials;
                  ?>
              </div>
            </button>
          </div>
      </div>
    </div>
  </div>
</nav>

<!-- JavaScript for Sidebar Toggle -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const drawerToggle = document.querySelector('[data-drawer-toggle="logo-sidebar"]');
        const sidebar = document.getElementById('logo-sidebar');
        
        if (drawerToggle && sidebar) {
            drawerToggle.addEventListener('click', function() {
                sidebar.classList.toggle('-translate-x-full');
            });
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const isClickInsideSidebar = sidebar.contains(event.target);
            const isClickInsideToggle = drawerToggle.contains(event.target);
            
            if (!isClickInsideSidebar && !isClickInsideToggle && window.innerWidth < 640 && !sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.add('-translate-x-full');
            }
        });
    });
</script>