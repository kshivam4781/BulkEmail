<?php
session_start();
require_once 'config/database.php';

// This endpoint will be called by the email server when a bounce occurs
// It should be configured in your email server's bounce handling settings

try {
    $db = (new Database())->connect();
    
    // Get the raw POST data
    $rawData = file_get_contents('php://input');
    $bounceData = json_decode($rawData, true);
    
    if (!$bounceData) {
        throw new Exception("Invalid bounce data format");
    }
    
    // Extract bounce information
    $toEmail = $bounceData['to_email'] ?? null;
    $bounceType = $bounceData['bounce_type'] ?? null;
    $bounceReason = $bounceData['bounce_reason'] ?? null;
    $bounceCode = $bounceData['bounce_code'] ?? null;
    $bounceMessage = $bounceData['bounce_message'] ?? null;
    
    if (!$toEmail || !$bounceType) {
        throw new Exception("Missing required bounce information");
    }
    
    // Find the email log entry
    $stmt = $db->prepare("
        SELECT el.id, el.campaign_id, el.user_id 
        FROM email_logs el 
        WHERE el.to_emails = ? 
        ORDER BY el.created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$toEmail]);
    $emailLog = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$emailLog) {
        throw new Exception("No matching email log found");
    }
    
    // Insert bounce record
    $stmt = $db->prepare("
        INSERT INTO bounce_logs (
            campaign_id, email_log_id, user_id, to_email, 
            bounce_type, bounce_reason, bounce_code, bounce_message
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $emailLog['campaign_id'],
        $emailLog['id'],
        $emailLog['user_id'],
        $toEmail,
        $bounceType,
        $bounceReason,
        $bounceCode,
        $bounceMessage
    ]);
    
    // Update email log status
    $stmt = $db->prepare("
        UPDATE email_logs 
        SET status = 'bounced', 
            error_message = ? 
        WHERE id = ?
    ");
    $stmt->execute([$bounceMessage, $emailLog['id']]);
    
    // If it's a hard bounce, update the campaign status
    if ($bounceType === 'hard') {
        $stmt = $db->prepare("
            UPDATE campaigns 
            SET status = 'bounced' 
            WHERE campaign_id = ?
        ");
        $stmt->execute([$emailLog['campaign_id']]);
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log("Bounce handling error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 