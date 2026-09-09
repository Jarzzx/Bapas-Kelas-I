<?php
/**
 * Email Configuration untuk BAPAS
 * Konfigurasi email untuk mengirim notifikasi via Gmail SMTP
 */

// Gmail SMTP Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587); // Port 587 untuk TLS, 465 untuk SSL
define('SMTP_USER', 'anggiilmu03@gmail.com'); // Email Gmail pengirim
define('SMTP_PASS', 'kgavpdmmqpyofllv'); // App Password Gmail: kgavpdmmqpyofllv (tanpa spasi)
define('SMTP_FROM_EMAIL', 'anggiilmu03@gmail.com'); // Email pengirim (sama dengan SMTP_USER)
define('SMTP_FROM_NAME', 'BAPAS Pekanbaru - Balai Pemasyarakatan Kelas I Pekanbaru');
define('SMTP_SECURE', 'tls'); // 'tls' untuk port 587, 'ssl' untuk port 465

/**
 * Load PHPMailer via Composer autoload
 */
function loadPHPMailer() {
    static $loaded = false;
    
    if ($loaded) {
        return true;
    }
    
    // Try composer autoload
    $autoload_path = __DIR__ . '/../../vendor/autoload.php';
    if (file_exists($autoload_path)) {
        require_once $autoload_path;
        $loaded = true;
        return true;
    }
    
    return false;
}

/**
 * Send email using PHPMailer (recommended for Gmail)
 */
function sendEmail($to, $subject, $message, $isHTML = true) {
    // Load PHPMailer
    if (!loadPHPMailer()) {
        error_log("PHPMailer not found. Please run: composer require phpmailer/phpmailer");
        return false;
    }
    
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Enable verbose debug output (set to 0 for production, 2 for debugging)
        // FORCE DEBUGGING for now since user reports issues
        $mail->SMTPDebug = 0; 
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer Debug: $str");
        };
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        // Trim password dan hapus semua whitespace
        $mail->Password = str_replace(' ', '', trim(SMTP_PASS));
        $mail->SMTPSecure = SMTP_SECURE; // 'tls' for port 587, 'ssl' for port 465
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 60; // Increased timeout to 60 seconds
        
        // Enable SMTP keep alive
        $mail->SMTPKeepAlive = false;
        
        // SSL/TLS options for Gmail (nonaktifkan SSL verification untuk development)
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        
        // Reply-To (optional)
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Content
        $mail->isHTML($isHTML);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        if (!$isHTML) {
            $mail->AltBody = strip_tags($message);
        } else {
            // Add plain text alternative for HTML emails
            $mail->AltBody = strip_tags($message);
        }
        
        // Send email
        $result = $mail->send();
        
        if ($result) {
            error_log("Email successfully sent to: $to");
            return true;
        } else {
            error_log("Email send failed. Error: " . $mail->ErrorInfo);
            return false;
        }
        
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        $error_message = "PHPMailer Exception: " . $mail->ErrorInfo . " | " . $e->getMessage();
        error_log($error_message);
        // Log to file for debugging
        $log_file = __DIR__ . '/../../logs/email_errors.log';
        $log_dir = dirname($log_file);
        if (!is_dir($log_dir)) {
            @mkdir($log_dir, 0755, true);
        }
        @file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $error_message . "\n", FILE_APPEND);
        return false;
    } catch (\Exception $e) {
        $error_message = "General Exception: " . $e->getMessage();
        error_log($error_message);
        // Log to file for debugging
        $log_file = __DIR__ . '/../../logs/email_errors.log';
        $log_dir = dirname($log_file);
        if (!is_dir($log_dir)) {
            @mkdir($log_dir, 0755, true);
        }
        @file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $error_message . "\n", FILE_APPEND);
        return false;
    } finally {
        // Close SMTP connection
        if (isset($mail)) {
            $mail->smtpClose();
        }
    }
}

/**
 * Get email template for approval notification
 */
function getApprovalEmailTemplate($klien_nama, $login_url) {
    return '
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                line-height: 1.6;
                color: #333333;
                background-color: #f5f5f5;
            }
            .email-wrapper {
                max-width: 600px;
                margin: 0 auto;
                background-color: #ffffff;
            }
            .email-header {
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                padding: 40px 30px;
                text-align: center;
                border-radius: 10px 10px 0 0;
            }
            .email-header h1 {
                color: #ffffff;
                font-size: 28px;
                font-weight: 700;
                margin-bottom: 10px;
            }
            .email-header .icon {
                font-size: 64px;
                margin-bottom: 20px;
            }
            .email-content {
                padding: 40px 30px;
                background-color: #ffffff;
            }
            .email-content h2 {
                color: #1f2937;
                font-size: 24px;
                margin-bottom: 20px;
                font-weight: 600;
            }
            .email-content p {
                color: #4b5563;
                font-size: 16px;
                margin-bottom: 16px;
                line-height: 1.8;
            }
            .email-content .highlight {
                background: linear-gradient(120deg, #dbeafe 0%, #bfdbfe 100%);
                padding: 20px;
                border-left: 4px solid #3b82f6;
                border-radius: 5px;
                margin: 25px 0;
            }
            .email-content .highlight p {
                margin-bottom: 8px;
            }
            .email-content .highlight strong {
                color: #1e40af;
            }
            .button-container {
                text-align: center;
                margin: 35px 0;
            }
            .button {
                display: inline-block;
                padding: 16px 40px;
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: #ffffff !important;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 600;
                font-size: 16px;
                box-shadow: 0 4px 6px rgba(16, 185, 129, 0.3);
                transition: transform 0.2s, box-shadow 0.2s;
            }
            .button:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 12px rgba(16, 185, 129, 0.4);
            }
            .info-box {
                background-color: #f0fdf4;
                border: 1px solid #86efac;
                border-radius: 8px;
                padding: 20px;
                margin: 25px 0;
            }
            .info-box p {
                color: #166534;
                margin-bottom: 8px;
            }
            .info-box ul {
                margin-left: 20px;
                color: #166534;
            }
            .info-box li {
                margin-bottom: 5px;
            }
            .email-footer {
                background-color: #f9fafb;
                padding: 30px;
                text-align: center;
                border-radius: 0 0 10px 10px;
                border-top: 1px solid #e5e7eb;
            }
            .email-footer p {
                color: #6b7280;
                font-size: 14px;
                margin-bottom: 8px;
            }
            .email-footer .divider {
                height: 1px;
                background-color: #e5e7eb;
                margin: 20px 0;
            }
            @media only screen and (max-width: 600px) {
                .email-content {
                    padding: 30px 20px;
                }
                .email-header {
                    padding: 30px 20px;
                }
                .email-header h1 {
                    font-size: 24px;
                }
                .button {
                    padding: 14px 30px;
                    font-size: 14px;
                }
            }
        </style>
    </head>
    <body>
        <div class="email-wrapper">
            <div class="email-header">
                <div class="icon">✅</div>
                <h1>Akun Anda Telah Diverifikasi!</h1>
            </div>
            
            <div class="email-content">
                <h2>Selamat, ' . htmlspecialchars($klien_nama) . '! 🎉</h2>
                
                <p>Kami dengan senang hati memberitahu bahwa <strong>akun Anda telah diverifikasi dan disetujui</strong> oleh Pembimbing Kemasyarakatan (PK).</p>
                
                <div class="highlight">
                    <p><strong>Akun Anda sekarang aktif!</strong></p>
                    <p>Anda dapat langsung login dan mengakses semua layanan yang tersedia di portal BAPAS.</p>
                </div>
                
                <p>Untuk memulai, silakan klik tombol di bawah ini untuk masuk ke portal:</p>
                
                <div class="button-container">
                    <a href="' . htmlspecialchars($login_url) . '" class="button">Login ke Portal BAPAS</a>
                </div>
                
                <div class="info-box">
                    <p><strong>Informasi Penting:</strong></p>
                    <ul>
                        <li>Pastikan Anda menggunakan username dan password yang telah didaftarkan</li>
                        <li>Jika lupa password, silakan hubungi administrator BAPAS</li>
                        <li>Portal dapat diakses 24/7 untuk memudahkan Anda</li>
                    </ul>
                </div>
                
                <p>Terima kasih atas kesabaran Anda selama proses verifikasi. Kami berharap dapat membantu Anda dengan sebaik-baiknya.</p>
                
                <p style="margin-top: 30px;">
                    Salam hormat,<br>
                    <strong>Tim BAPAS</strong><br>
                    <em>Balai Pemasyarakatan</em>
                </p>
            </div>
            
            <div class="email-footer">
                <div class="divider"></div>
                <p><strong>BAPAS - Balai Pemasyarakatan</strong></p>
                <p>Email ini dikirim secara otomatis. Mohon jangan membalas email ini.</p>
                <p style="margin-top: 15px; font-size: 12px; color: #9ca3af;">
                    Jika Anda tidak melakukan pendaftaran, silakan abaikan email ini.
                </p>
            </div>
        </div>
    </body>
    </html>';
}

/**
 * Get email template for rejection notification
 */
function getRejectionEmailTemplate($klien_nama) {
    return '
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                line-height: 1.6;
                color: #333333;
                background-color: #f5f5f5;
            }
            .email-wrapper {
                max-width: 600px;
                margin: 0 auto;
                background-color: #ffffff;
            }
            .email-header {
                background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
                padding: 40px 30px;
                text-align: center;
                border-radius: 10px 10px 0 0;
            }
            .email-header h1 {
                color: #ffffff;
                font-size: 28px;
                font-weight: 700;
                margin-bottom: 10px;
            }
            .email-header .icon {
                font-size: 64px;
                margin-bottom: 20px;
            }
            .email-content {
                padding: 40px 30px;
                background-color: #ffffff;
            }
            .email-content h2 {
                color: #1f2937;
                font-size: 24px;
                margin-bottom: 20px;
                font-weight: 600;
            }
            .email-content p {
                color: #4b5563;
                font-size: 16px;
                margin-bottom: 16px;
                line-height: 1.8;
            }
            .email-footer {
                background-color: #f9fafb;
                padding: 30px;
                text-align: center;
                border-radius: 0 0 10px 10px;
                border-top: 1px solid #e5e7eb;
            }
            .email-footer p {
                color: #6b7280;
                font-size: 14px;
                margin-bottom: 8px;
            }
        </style>
    </head>
    <body>
        <div class="email-wrapper">
            <div class="email-header">
                <div class="icon">❌</div>
                <h1>Status Pendaftaran</h1>
            </div>
            
            <div class="email-content">
                <h2>Kepada ' . htmlspecialchars($klien_nama) . ',</h2>
                
                <p>Kami memberitahu bahwa pendaftaran akun Anda telah <strong>ditolak</strong>.</p>
                
                <p>Jika Anda memiliki pertanyaan atau memerlukan informasi lebih lanjut mengenai keputusan ini, silakan hubungi administrator BAPAS.</p>
                
                <p style="margin-top: 30px;">
                    Salam hormat,<br>
                    <strong>Tim BAPAS</strong><br>
                    <em>Balai Pemasyarakatan</em>
                </p>
            </div>
            
            <div class="email-footer">
                <p><strong>BAPAS - Balai Pemasyarakatan</strong></p>
                <p>Email ini dikirim secara otomatis. Mohon jangan membalas email ini.</p>
            </div>
        </div>
    </body>
    </html>';
}

/**
 * Send approval notification email
 * Email tujuan diambil dari email yang digunakan klien saat mendaftar
 */
function sendApprovalEmail($klien_email, $klien_nama, $status, $pk_nama = '') {
    // Build login URL
    // Detect Protocol (HTTP/HTTPS) including proxy support
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $protocol = 'https';
    }
    
    $host = $_SERVER['HTTP_HOST'];
    
    // Determine script directory relative to document root
    // Normalize slashes for Windows compatibility
    $script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
    
    // Assuming the structure is always 2 levels deep for the calling script (e.g., /pk/approval.php)
    // If called from /pk/approval.php, dirname(dirname) -> /
    // If called from /index.php (unlikely), dirname(dirname) -> . or /
    
    // Better approach: Find the 'pk' or 'shared' segment and strip everything after
    // This assumes the app structure is fixed.
    $app_root = dirname(dirname($script_name));
    
    // Normalize again just in case
    $app_root = str_replace('\\', '/', $app_root);
    
    // Remove trailing slash if it's just "/"
    if ($app_root === '/' || $app_root === '\\') {
        $app_root = '';
    }
    $app_root = rtrim($app_root, '/');
    
    // Construct Base URL
    $base_url = $protocol . '://' . $host . $app_root;
    
    // Construct Login URL
    $login_url = $base_url . '/klien/login.php';
    
    if ($status === 'approved') {
        $subject = '✅ Akun Anda Telah Diverifikasi - BAPAS';
        $message = getApprovalEmailTemplate($klien_nama, $login_url);
    } else {
        $subject = 'Status Pendaftaran Akun - BAPAS';
        $message = getRejectionEmailTemplate($klien_nama);
    }
    
    // Send email to klien (email yang digunakan saat mendaftar)
    return sendEmail($klien_email, $subject, $message, true);
}
?>
