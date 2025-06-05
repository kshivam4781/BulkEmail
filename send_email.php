<?php
session_start();
require_once 'config/database.php';
require_once 'includes/EmailSender.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['email']) || !isset($_POST['campaign_id'])) {
    die(json_encode(['success' => false, 'error' => 'Invalid request']));
}

try {
    $db = (new Database())->connect();
    
    // Get campaign details
    $stmt = $db->prepare("SELECT subject, message FROM campaigns WHERE campaign_id = ? AND user_id = ?");
    $stmt->execute([$_POST['campaign_id'], $_SESSION['user_id']]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$campaign) {
        die(json_encode(['success' => false, 'error' => 'Campaign not found']));
    }

    // Get SMTP configuration
    $stmt = $db->prepare("SELECT email, smtp, port, password FROM emailsender WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $smtp = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$smtp) {
        die(json_encode(['success' => false, 'error' => 'SMTP configuration not found']));
    }

    // Validate message content
    if (empty($campaign['message'])) {
        die(json_encode(['success' => false, 'error' => 'Message content is empty']));
    }

    // Send email
    $emailSender = new EmailSender($smtp['email'], $smtp['smtp'], $smtp['port'], $smtp['password']);
    $emailSender->setUserId($_SESSION['user_id']);
    $result = $emailSender->sendEmail(
        $_POST['email'],
        $campaign['subject'],
        $campaign['message']
    );

    if (!$result) {
        throw new Exception('Failed to send email');
    }

    // Log successful email
    $stmt = $db->prepare("
        INSERT INTO email_logs (campaign_id, user_id, to_emails, subject, from_email, status) 
        VALUES (?, ?, ?, ?, ?, 'sent')
    ");
    $stmt->execute([
        $_POST['campaign_id'],
        $_SESSION['user_id'],
        $_POST['email'],
        $campaign['subject'],
        $smtp['email']
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // Log failed email
    if (isset($db) && isset($campaign) && isset($smtp)) {
        $stmt = $db->prepare("
            INSERT INTO email_logs (campaign_id, user_id, to_emails, subject, from_email, status) 
            VALUES (?, ?, ?, ?, ?, 'failed')
        ");
        $stmt->execute([
            $_POST['campaign_id'],
            $_SESSION['user_id'],
            $_POST['email'],
            $campaign['subject'],
            $smtp['email']
        ]);
    }

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 