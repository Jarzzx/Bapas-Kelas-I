<?php
// Configure session settings before starting session

// === CUSTOM SESSION SAVE PATH ===
// Use local 'sessions' directory to prevent shared hosting GC from deleting sessions prematurely
$session_save_path = dirname(dirname(dirname(__FILE__))) . '/sessions';
if (is_dir($session_save_path) && is_writable($session_save_path)) {
    session_save_path($session_save_path);
}
// ================================

// Set session timeout to 8 hours (28800 seconds)
ini_set('session.gc_maxlifetime', 28800);
// Ensure GC actually runs occasionally to clean up old files in our custom directory
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);

ini_set('session.cookie_lifetime', 0);  // Session cookie (expires when browser closes)

// Use root path for session cookie to ensure it's accessible from all pages
// This prevents session loss when navigating between different directories
$base_path = '/';

// Set session cookie parameters
// lifetime = 0 means session cookie (expires when browser closes)
// httponly = true prevents JavaScript access to cookie (security)
// secure = false for localhost, set to true in production with HTTPS
session_set_cookie_params([
    'lifetime' => 0,  // Session cookie (expires when browser closes)
    'path' => $base_path,  // Use base path to ensure cookie is accessible across all pages
    'domain' => '',  // Empty for current domain
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',  // Auto-detect HTTPS
    'httponly' => true,  // Prevent JavaScript access
    'samesite' => 'Lax'  // CSRF protection
]);

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// === FIX DOUBLE URL / MALFORMED URL ===
// Detect if URI contains "http://" or "https://" inside the path (e.g. /https://domain.com/page)
$request_uri = $_SERVER['REQUEST_URI'];
if (strpos($request_uri, 'http://') !== false || strpos($request_uri, 'https://') !== false) {
    // Find the last occurrence of the actual path part (after the last http/https)
    // Example: /https://site.com/https://site.com/pk/dashboard.php
    
    // Pattern to match /http(s)://domain.com/
    // We want to extract everything after the domain part.
    // Simplest approach: Look for "pk/" or "klien/" or "index.php"
    
    $clean_path = $request_uri;
    
    // Try to find known starting segments
    $segments = ['/pk/', '/klien/', '/shared/', '/uploads/'];
    foreach ($segments as $segment) {
        $pos = strpos($request_uri, $segment);
        if ($pos !== false) {
            // Check if there is another occurrence later (take the last one)
            $last_pos = strrpos($request_uri, $segment);
            if ($last_pos !== false) {
                $clean_path = substr($request_uri, $last_pos);
                break;
            }
        }
    }
    
    // If we found a cleaner path and it is different from current
    if ($clean_path !== $request_uri) {
        // Redirect to clean path
        header("Location: " . $clean_path);
        exit;
    }
}
// ======================================

// Regenerate session ID periodically to prevent session fixation
// Only regenerate if session is older than 30 minutes (1800 seconds)
// Skip regeneration on AJAX requests to avoid race conditions
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (!$is_ajax) {
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 1800) {
        // Regenerate every 30 minutes
        session_regenerate_id(false);  // false = don't delete old session
        $_SESSION['last_regeneration'] = time();
    }
}

// Check if user is logged in as PK
function isPKLoggedIn() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'pk' && isset($_SESSION['user_id']);
}

// Check if user is logged in as Klien
function isKlienLoggedIn() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'klien' && isset($_SESSION['user_id']);
}

// Helper to get robust base URL
function getBaseUrl() {
    // Protocol
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $protocol = 'https';
    }
    
    // Host
    $host = $_SERVER['HTTP_HOST'];
    
    // Script Path (App Root)
    // Assuming file structure: /.../shared/config/auth.php
    // We want the root path of the application.
    // If SCRIPT_NAME is /pengawasan/pk/dashboard.php, we want /pengawasan
    
    $script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
    $segments = explode('/', trim($script_name, '/'));
    
    // Look for 'pk' or 'klien' or 'shared' in segments to find where the app root ends
    $app_root_segments = [];
    foreach ($segments as $segment) {
        if ($segment === 'pk' || $segment === 'klien' || $segment === 'shared') {
            break;
        }
        $app_root_segments[] = $segment;
    }
    
    $app_root = '/' . implode('/', $app_root_segments);
    if ($app_root === '/') $app_root = ''; // Root domain
    
    return $protocol . '://' . $host . $app_root;
}

// Require PK login
function requirePKLogin() {
    if (!isPKLoggedIn()) {
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        header('Location: ' . getBaseUrl() . '/pk/login.php');
        exit;
    }
}

// Require Klien login
function requireKlienLogin() {
    if (!isKlienLoggedIn()) {
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        header('Location: ' . getBaseUrl() . '/klien/login.php');
        exit;
    }
}

// Logout function
function logout() {
    session_unset();
    session_destroy();
    header('Location: ' . getBaseUrl() . '/index.php');
    exit;
}
?>

