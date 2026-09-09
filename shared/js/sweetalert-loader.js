/**
 * SweetAlert2 Loader untuk BAPAS with Lottie Support
 * Script untuk menampilkan notifikasi menggunakan SweetAlert2 dan Lottie Animations
 */

// Load SweetAlert2 dari CDN jika belum dimuat
if (typeof Swal === 'undefined') {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css';
    document.head.appendChild(link);
    
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
    document.head.appendChild(script);
}

// Lottie Player removed as per request to use standard SweetAlert

/**
 * Show success message (Standard SweetAlert)
 */
function showSuccessLottie(title, message = '', callback = null) {
    Swal.fire({
        icon: 'success',
        title: title,
        text: message,
        confirmButtonText: 'OK, Mengerti',
        confirmButtonColor: '#4f46e5', // indigo-600
        showClass: {
            popup: 'animate__animated animate__fadeInDown'
        },
        hideClass: {
            popup: 'animate__animated animate__fadeOutUp'
        }
    }).then((result) => {
        if (callback && typeof callback === 'function') {
            callback(result);
        }
    });
}

/**
 * Show delete confirmation (Standard SweetAlert)
 */
function showDeleteLottie(name, confirmCallback, cancelCallback = null) {
    Swal.fire({
        title: 'Konfirmasi Hapus',
        text: `Apakah Anda yakin ingin menghapus data "${name}"? Tindakan ini tidak dapat dibatalkan.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626', // red-600
        cancelButtonColor: '#6b7280', // gray-500
        confirmButtonText: 'Ya, Hapus Data',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            if (confirmCallback && typeof confirmCallback === 'function') {
                confirmCallback();
            }
        } else {
            if (cancelCallback && typeof cancelCallback === 'function') {
                cancelCallback();
            }
        }
    });
}

// Keep legacy functions for backward compatibility
function showSuccess(title, message = '', callback = null) {
    showSuccessLottie(title, message, callback);
}

function showError(title, message = '', callback = null) {
    Swal.fire({
        icon: 'error',
        title: title,
        text: message,
        confirmButtonColor: '#ef4444',
        showConfirmButton: true
    }).then((result) => {
        if (callback && typeof callback === 'function') callback(result);
    });
}
