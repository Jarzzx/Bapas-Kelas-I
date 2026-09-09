<?php
// Debug Approval Flow & Email Data
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'shared/config/database.php';
require_once 'shared/config/email.php';

$conn = getDBConnection();

echo "<h1>Debug Approval & Email</h1>";

// 1. List Last 10 Users
echo "<h3>Recent Clients (Last 10)</h3>";
$query = "SELECT id, nama, username, email, no_registrasi, status_approval FROM klien_users ORDER BY id DESC LIMIT 10";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse; width:100%;'>";
    echo "<tr style='background:#eee;'>
            <th>ID</th>
            <th>Nama</th>
            <th>Username</th>
            <th>Email</th>
            <th>Status</th>
            <th>Action</th>
          </tr>";
    
    while ($row = $result->fetch_assoc()) {
        $email_display = empty($row['email']) ? '<span style="color:red; font-weight:bold;">MISSING</span>' : $row['email'];
        echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['nama']}</td>
                <td>{$row['username']}</td>
                <td>{$email_display}</td>
                <td>{$row['status_approval']}</td>
                <td>
                    <form method='POST' style='margin:0;'>
                        <input type='hidden' name='test_user_id' value='{$row['id']}'>
                        <input type='hidden' name='test_email' value='{$row['email']}'>
                        <input type='hidden' name='test_nama' value='{$row['nama']}'>
                        <button type='submit' name='test_send'>Test HTML Email</button>
                        <button type='submit' name='test_send_simple' style='background:#ccc; color:#000; margin-left:5px;'>Test Plain Text</button>
                    </form>
                </td>
              </tr>";
    }
    echo "</table>";
} else {
    echo "<p>No users found.</p>";
}

// 2. Handle Test Send
if (isset($_POST['test_send']) || isset($_POST['test_send_simple'])) {
    echo "<hr><h3>Sending Test...</h3>";
    $user_id = $_POST['test_user_id'];
    $email = $_POST['test_email'];
    $nama = $_POST['test_nama'];
    $is_simple = isset($_POST['test_send_simple']);
    
    if (empty($email)) {
        echo "<div style='color:red; font-weight:bold;'>ERROR: Cannot send email because the Email Address is EMPTY!</div>";
    } else {
        echo "<p>Target: $email ($nama) | Mode: " . ($is_simple ? "Plain Text" : "HTML") . "</p>";
        
        // Construct Login URL (Mock)
        $host = $_SERVER['HTTP_HOST'];
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $login_url = $protocol . '://' . $host . '/pengawasan/klien/login.php';
        
        if ($is_simple) {
            $subject = 'Test Simple Email BAPAS';
            $message = "Halo $nama,\n\nIni adalah email tes sederhana untuk memastikan notifikasi bisa masuk ke Inbox Anda.\n\nJika Anda menerima email ini, berarti sistem email berfungsi, namun mungkin email HTML sebelumnya masuk ke Spam.\n\nSalam,\nAdmin BAPAS";
        } else {
            $subject = '✅ [TEST] Akun Anda Telah Diverifikasi';
            $message = getApprovalEmailTemplate($nama, $login_url);
        }
        
        // Debug PHPMailer
        if (!loadPHPMailer()) {
            echo "PHPMailer library not found!<br>";
        } else {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mail->SMTPDebug = 2; // Verbose debug
                $mail->Debugoutput = 'html';
                
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USER;
                $mail->Password = str_replace(' ', '', trim(SMTP_PASS));
                $mail->SMTPSecure = SMTP_SECURE;
                $mail->Port = SMTP_PORT;
                $mail->CharSet = 'UTF-8';
                $mail->Timeout = 60;
                
                 $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );
                
                $mail->setFrom(SMTP_FROM_EMAIL, 'BAPAS Admin'); // Use shorter name
                $mail->addAddress($email);
                
                $mail->isHTML(!$is_simple);
                $mail->Subject = $subject;
                $mail->Body = $message;
                
                echo "<div style='background:#f9f9f9; padding:10px; border:1px solid #ccc; max-height:300px; overflow:auto;'>";
                $mail->send();
                echo "</div>";
                echo "<h4 style='color:green;'>Email sent successfully!</h4>";
                echo "<p style='background:yellow; padding:10px;'><strong>PENTING:</strong> Jika status sukses tapi tidak ada di Inbox, tolong cek folder <strong>SPAM</strong> atau <strong>Junk</strong>.</p>";
                
            } catch (Exception $e) {
                echo "<h4 style='color:red;'>Email sending failed!</h4>";
                echo "Error: " . $mail->ErrorInfo;
            }
        }
    }
}
?>