/**
 * Auto Notification Refresh untuk BAPAS
 * Script untuk auto-refresh notifikasi tanpa reload halaman
 */

class NotificationAutoRefresh {
    constructor(config = {}) {
        this.interval = config.interval || 10000; // 10 detik default
        this.endpoint = config.endpoint || null; // Endpoint untuk fetch notifikasi
        this.onUpdate = config.onUpdate || null; // Callback saat ada update
        this.isRunning = false;
        this.intervalId = null;
        this.lastCheck = null;
    }

    /**
     * Start auto refresh
     */
    start() {
        if (this.isRunning) return;
        
        this.isRunning = true;
        this.checkNotifications();
        
        this.intervalId = setInterval(() => {
            this.checkNotifications();
        }, this.interval);
        
        console.log('Auto notification refresh started');
    }

    /**
     * Stop auto refresh
     */
    stop() {
        if (!this.isRunning) return;
        
        this.isRunning = false;
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
        
        console.log('Auto notification refresh stopped');
    }

    /**
     * Check for new notifications
     */
    async checkNotifications() {
        if (!this.endpoint) return;
        
        try {
            const response = await fetch(this.endpoint, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Cache-Control': 'no-cache'
                },
                cache: 'no-cache'
            });
            
            if (response.ok) {
                const data = await response.json();
                this.lastCheck = new Date();
                
                if (this.onUpdate && typeof this.onUpdate === 'function') {
                    this.onUpdate(data);
                }
            } else if (response.status === 401 || response.status === 403) {
                // Session expired
                this.stop();
                console.log('Session expired, stopping auto refresh');
                // Optional: Redirect to login or show alert
                // window.location.reload(); // This will trigger server-side redirect
            }
        } catch (error) {
            console.error('Error checking notifications:', error);
        }
    }

    /**
     * Force check now
     */
    async forceCheck() {
        await this.checkNotifications();
    }
}

// Global instance
let notificationRefresh = null;

/**
 * Get dynamic API endpoint based on current URL
 * Handles subdirectories and different hosting environments
 */
function getAPIEndpoint(type) {
    // Get current path
    const path = window.location.pathname;
    
    // Determine base path (e.g. /pengawasan/ or /)
    // Look for '/pk/' or '/klien/'
    let basePath = '';
    
    if (path.includes('/pk/')) {
        basePath = path.split('/pk/')[0];
    } else if (path.includes('/klien/')) {
        basePath = path.split('/klien/')[0];
    }
    
    // Construct absolute endpoint
    return `${window.location.origin}${basePath}/${type}/api/check-notifications.php`;
}

/**
 * Initialize auto refresh for PK dashboard
 */
function initPKNotificationRefresh() {
    notificationRefresh = new NotificationAutoRefresh({
        interval: 10000, // 10 detik
        endpoint: getAPIEndpoint('pk'),
        onUpdate: (data) => {
            updatePKNotifications(data);
        }
    });
    notificationRefresh.start();
}

/**
 * Initialize auto refresh for Klien dashboard
 */
function initKlienNotificationRefresh() {
    notificationRefresh = new NotificationAutoRefresh({
        interval: 10000, // 10 detik
        endpoint: getAPIEndpoint('klien'),
        onUpdate: (data) => {
            updateKlienNotifications(data);
        }
    });
    notificationRefresh.start();
}

/**
 * Update PK notifications in DOM
 */
function updatePKNotifications(data) {
    // Update pending approvals count
    if (data.pending_count !== undefined) {
        const alertElement = document.getElementById('pending-approvals-alert');
        const countElement = document.getElementById('pending-count');
        
        if (countElement) {
            countElement.textContent = data.pending_count;
        }
        
        if (alertElement) {
            if (data.pending_count > 0) {
                alertElement.classList.remove('hidden');
                // Show notification if new
                if (data.pending_count > parseInt(countElement.textContent || 0)) {
                    showInfo('Notifikasi Baru', `Ada ${data.pending_count} pendaftaran klien yang menunggu persetujuan`);
                }
            } else {
                alertElement.classList.add('hidden');
            }
        }
    }
    
    // Update biodata complete count
    if (data.biodata_lengkap_count !== undefined) {
        const alertElement = document.getElementById('biodata-complete-alert');
        const countElement = document.getElementById('biodata-count');
        
        if (countElement) {
            countElement.textContent = data.biodata_lengkap_count;
        }
        
        if (alertElement) {
            if (data.biodata_lengkap_count > 0) {
                alertElement.classList.remove('hidden');
            } else {
                alertElement.classList.add('hidden');
            }
        }
    }
    
    // Update statistics
    if (data.statistics) {
        updateStatistics(data.statistics);
    }
}

/**
 * Update Klien notifications in DOM
 */
function updateKlienNotifications(data) {
    // Update jadwal bimbingan
    if (data.jadwal_bimbingan) {
        updateJadwalBimbingan(data.jadwal_bimbingan);
    }
    
    // Update laporan status
    if (data.laporan_status) {
        updateLaporanStatus(data.laporan_status);
    }
}

/**
 * Update statistics
 */
function updateStatistics(stats) {
    if (stats.total_klien !== undefined) {
        const elem = document.getElementById('stat-total-klien');
        if (elem) elem.textContent = stats.total_klien;
    }
    
    if (stats.total_pengawasan !== undefined) {
        const elem = document.getElementById('stat-total-pengawasan');
        if (elem) elem.textContent = stats.total_pengawasan;
    }
    
    if (stats.total_bimbingan !== undefined) {
        const elem = document.getElementById('stat-total-bimbingan');
        if (elem) elem.textContent = stats.total_bimbingan;
    }
}

/**
 * Update jadwal bimbingan
 */
function updateJadwalBimbingan(jadwal) {
    // Implementation untuk update jadwal di dashboard klien
    console.log('Updating jadwal bimbingan:', jadwal);
}

/**
 * Update laporan status
 */
function updateLaporanStatus(status) {
    // Implementation untuk update status laporan
    console.log('Updating laporan status:', status);
}

// Pause ketika tab tidak aktif
document.addEventListener('visibilitychange', () => {
    if (notificationRefresh) {
        if (document.hidden) {
            notificationRefresh.stop();
        } else {
            notificationRefresh.start();
            notificationRefresh.forceCheck(); // Check immediately when tab becomes visible
        }
    }
});
