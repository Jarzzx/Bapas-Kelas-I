<?php
// Test Email Script
require_once 'shared/config/email.php';

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Test Email SMTP</h1>";

// Hardcoded test recipient (use the sender email for self-test if no other)
$to = 'anggiilmu03@gmail.com'; 
$subject = 'Test SMTP Connection from BAPAS Debugger';
$message = '<p>This is a test email to verify SMTP configuration.</p><p>Time: ' . date('Y-m-d H:i:s') . '</p>';

// Environment Check
echo "<h3>Environment Check</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";

$host = SMTP_HOST;
$port = SMTP_PORT;
echo "Testing connection to $host:$port... ";
$fp = @fsockopen($host, $port, $errno, $errstr, 10);
if (!$fp) {
    echo "<span style='color:red'>FAILED!</span><br>";
    echo "Error: $errstr ($errno)<br>";
    echo "<strong>Possible Cause:</strong> Hosting provider is blocking outgoing SMTP connections (common on free hosting like InfinityFree).<br>";
} else {
    echo "<span style='color:green'>SUCCESS!</span> - Port is open.<br>";
    fclose($fp);
}
echo "<hr>";

echo "<p>Attempting to send email to: <strong>$to</strong></p>";

// Override SMTP Debug for this test
// We need to access PHPMailer directly or modify the function.
// Since sendEmail() encapsulates everything, let's copy the logic here for debugging.

if (!loadPHPMailer()) {
    die("CRITICAL: PHPMailer library not found!");
}

$mail = new \PHPMailer\PHPMailer\PHPMailer(true);

try {
    // Enable verbose debug output
    $mail->SMTPDebug = 2; // Client and server messages
    $mail->Debugoutput = 'html'; // Output in HTML format
    
    // Server settings
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
    
    // Recipients
    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    $mail->addAddress($to);
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $message;
    
    echo "<div style='background: #f0f0f0; padding: 10px; border: 1px solid #ccc; font-family: monospace;'>";
    $mail->send();
    echo "</div>";
    
    echo "<h3 style='color: green;'>Message has been sent successfully!</h3>";
    
} catch (Exception $e) {
    echo "</div>"; // Close debug div if open
    echo "<h3 style='color: red;'>Message could not be sent. Mailer Error:</h3>";
    echo "<pre>" . $mail->ErrorInfo . "</pre>";
    echo "<h3>Exception Message:</h3>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
?>