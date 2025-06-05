<?php
require_once 'config/database.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

try {
    // Get SMTP configuration from database
    $db = (new Database())->connect();
    $stmt = $db->query("SELECT * FROM emailsender WHERE id = 1");
    $smtpConfig = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$smtpConfig) {
        throw new Exception("SMTP configuration not found");
    }

    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);

    // Server settings
    $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Enable verbose debug output
    $mail->isSMTP();
    $mail->Host = $smtpConfig['smtp'];
    $mail->SMTPAuth = true;
    $mail->Username = $smtpConfig['email'];
    $mail->Password = $smtpConfig['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $smtpConfig['port'];

    // Recipients
    $mail->setFrom($smtpConfig['email'], 'SKY Bulk Email Sender');
    $mail->addAddress($smtpConfig['email']); // Send to the same email for testing

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Test Email from SKY Bulk Email Sender';
    $mail->Body = 'This is a test email to verify that the email sending functionality is working correctly.';

    // Send the email
    $mail->send();
    echo "Test email sent successfully!";
} catch (Exception $e) {
    echo "Error sending test email: {$mail->ErrorInfo}";
}
?> 