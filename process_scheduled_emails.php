<?php
// Set unlimited execution time
set_time_limit(0);
ini_set('max_execution_time', 0);

require_once 'config/database.php';
require_once 'includes/EmailSender.php';

try {
    $db = (new Database())->connect();
    
    // Set timezone to Pacific
    date_default_timezone_set('America/Los_Angeles');
    
    // Log start of processing
    $logMessage = "\n\n=== " . date('Y-m-d H:i:s') . " Pacific Time ===\n";
    $logMessage .= "=== Starting Scheduled Email Processing ===\n";
    file_put_contents(__DIR__ . '/includes/php_errors.log', $logMessage, FILE_APPEND);

    // // First, let's check the raw data
    // $checkStmt = $db->prepare("
    //     SELECT id, scheduled_time, status 
    //     FROM scheduled_emails 
    //     WHERE status = 'pending'
    // ");
    // $checkStmt->execute();
    // $pendingEmails = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // $logMessage = "\nPending Emails in Database:\n";
    // foreach ($pendingEmails as $email) {
    //     $logMessage .= "ID: " . $email['id'] . "\n";
    //     $logMessage .= "Raw scheduled_time: " . $email['scheduled_time'] . "\n";
    //     $logMessage .= "Converted to Pacific: " . date('Y-m-d H:i:s', strtotime($email['scheduled_time'])) . "\n";
    //     $logMessage .= "Converted to UTC: " . gmdate('Y-m-d H:i:s', strtotime($email['scheduled_time'])) . "\n";
    //     $logMessage .= "Converted to Berlin: " . date('Y-m-d H:i:s', strtotime($email['scheduled_time'] . ' Europe/Berlin')) . "\n\n";
    // }
    // file_put_contents(__DIR__ . '/includes/php_errors.log', $logMessage, FILE_APPEND);

    // Get all pending scheduled emails that are due
    $stmt = $db->prepare("
        SELECT 
            se.*,
            u.assId,
            es.email as sender_email,
            es.smtp,
            es.port,
            es.password,
            c.cc_emails as campaign_cc_emails,
            c.bcc_emails as campaign_bcc_emails
        FROM scheduled_emails se
        JOIN users u ON se.user_id = u.userId
        JOIN emailsender es ON u.assId = es.id
        JOIN campaigns c ON se.campaign_id = c.campaign_id
        WHERE se.status = 'pending'
        AND se.scheduled_time <= DATE_ADD(NOW(), INTERVAL 15 MINUTE)
        AND se.scheduled_time >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        AND NOT EXISTS (
            SELECT 1 
            FROM email_logs el 
            WHERE el.campaign_id = se.campaign_id 
            AND el.to_emails = se.to_emails 
            AND el.status = 'sent'
        )
        ORDER BY se.scheduled_time ASC
    ");
    
    $stmt->execute();
    $scheduledEmails = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($scheduledEmails)) {
        $logMessage = "No scheduled emails to process\n";
        $logMessage .= "Current time (Pacific): " . date('Y-m-d H:i:s') . "\n";
        $logMessage .= "Time window: " . date('Y-m-d H:i:s', strtotime('-15 minutes')) . " to " . date('Y-m-d H:i:s', strtotime('+15 minutes')) . "\n";
        
        // Also log the UTC and Berlin time for reference
        $logMessage .= "\nTime Reference:\n";
        $logMessage .= "Current UTC: " . gmdate('Y-m-d H:i:s') . "\n";
        $logMessage .= "Current Berlin: " . date('Y-m-d H:i:s', strtotime('now Europe/Berlin')) . "\n";
        $logMessage .= "Time window UTC: " . gmdate('Y-m-d H:i:s', strtotime('-15 minutes')) . " to " . gmdate('Y-m-d H:i:s', strtotime('+15 minutes')) . "\n";
        
        file_put_contents(__DIR__ . '/includes/php_errors.log', $logMessage, FILE_APPEND);
        exit;
    }

    $logMessage = "Found " . count($scheduledEmails) . " emails to process\n";
    file_put_contents(__DIR__ . '/includes/php_errors.log', $logMessage, FILE_APPEND);

    // Process each scheduled email
    foreach ($scheduledEmails as $email) {
        try {
            $logMessage = "\nProcessing email ID: " . $email['id'] . "\n";
            $logMessage .= "To: " . $email['to_emails'] . "\n";
            $logMessage .= "From: " . $email['sender_email'] . "\n";
            $logMessage .= "Subject: " . $email['subject'] . "\n";
            $logMessage .= "Scheduled for (Pacific): " . date('Y-m-d H:i:s', strtotime($email['scheduled_time'])) . "\n";
            $logMessage .= "Scheduled for (UTC): " . gmdate('Y-m-d H:i:s', strtotime($email['scheduled_time'])) . "\n";
            $logMessage .= "Scheduled for (Berlin): " . date('Y-m-d H:i:s', strtotime($email['scheduled_time'] . ' Europe/Berlin')) . "\n";
            file_put_contents(__DIR__ . '/includes/php_errors.log', $logMessage, FILE_APPEND);

            // Initialize email sender with user's SMTP settings
            $emailSender = new EmailSender(
                $email['sender_email'],
                $email['smtp'],
                $email['port'],
                $email['password']
            );
            $emailSender->setUserId($email['user_id']);

            // Add tracking pixel to the message
            $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
            $trackingPixel = '<img src="http://' . $host . '/bulk/track.php?c=' . $email['campaign_id'] . '&e=' . urlencode($email['to_emails']) . '" width="1" height="1" style="display:none" />';
            $message = $email['body'] . $trackingPixel;

            // Send the email
            $emailSender->sendEmail(
                $email['to_emails'],
                $email['subject'],
                $message,
                json_decode($email['campaign_cc_emails'] ?? '[]', true),
                json_decode($email['campaign_bcc_emails'] ?? '[]', true)
            );

            // Log successful send
            $stmt = $db->prepare("
                INSERT INTO email_logs (
                    campaign_id, user_id, to_emails, subject, 
                    from_email, status, cc_emails, bcc_emails, created_at
                ) VALUES (?, ?, ?, ?, ?, 'sent', ?, ?, NOW())
            ");
            
            $stmt->execute([
                $email['campaign_id'],
                $email['user_id'],
                $email['to_emails'],
                $email['subject'],
                $email['sender_email'],
                $email['campaign_cc_emails'] ?? '[]',
                $email['campaign_bcc_emails'] ?? '[]'
            ]);

            // Update scheduled email status
            $stmt = $db->prepare("
                UPDATE scheduled_emails 
                SET status = 'sent'
                WHERE id = ?
            ");
            $stmt->execute([$email['id']]);

            $logMessage = "Email sent successfully\n";
            file_put_contents(__DIR__ . '/includes/php_errors.log', $logMessage, FILE_APPEND);

        } catch (Exception $e) {
            // Log failed send
            $stmt = $db->prepare("
                INSERT INTO email_logs (
                    campaign_id, user_id, to_emails, subject, 
                    from_email, status, cc_emails, bcc_emails, error_message, created_at
                ) VALUES (?, ?, ?, ?, ?, 'failed', ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $email['campaign_id'],
                $email['user_id'],
                $email['to_emails'],
                $email['subject'],
                $email['sender_email'],
                $email['campaign_cc_emails'] ?? '[]',
                $email['campaign_bcc_emails'] ?? '[]',
                $e->getMessage()
            ]);

            // Update scheduled email status
            $stmt = $db->prepare("
                UPDATE scheduled_emails 
                SET status = 'failed', 
                    error_message = ?
                WHERE id = ?
            ");
            $stmt->execute([$e->getMessage(), $email['id']]);

            $logMessage = "ERROR: Failed to send email\n";
            $logMessage .= "Error: " . $e->getMessage() . "\n";
            file_put_contents(__DIR__ . '/includes/php_errors.log', $logMessage, FILE_APPEND);
        }
    }

    // Check if all emails in the campaign are sent
    $stmt = $db->prepare("
        SELECT 
            c.campaign_id,
            COUNT(se.id) as total_emails,
            SUM(CASE WHEN se.status = 'sent' THEN 1 ELSE 0 END) as sent_emails
        FROM campaigns c
        JOIN scheduled_emails se ON c.campaign_id = se.campaign_id
        WHERE c.status = 'scheduled'
        GROUP BY c.campaign_id
        HAVING total_emails = sent_emails
    ");
    
    $stmt->execute();
    $completedCampaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Update campaign status for completed campaigns
    foreach ($completedCampaigns as $campaign) {
        $stmt = $db->prepare("
            UPDATE campaigns 
            SET status = 'sent',
                updated_at = NOW()
            WHERE campaign_id = ?
        ");
        $stmt->execute([$campaign['campaign_id']]);

        $logMessage = "\nCampaign " . $campaign['campaign_id'] . " completed\n";
        $logMessage .= "Total emails: " . $campaign['total_emails'] . "\n";
        $logMessage .= "Sent emails: " . $campaign['sent_emails'] . "\n";
        file_put_contents(__DIR__ . '/includes/php_errors.log', $logMessage, FILE_APPEND);
    }

    $logMessage = "\n=== Scheduled Email Processing Complete ===\n";
    $logMessage .= "Time: " . date('Y-m-d H:i:s') . " Pacific Time\n";
    $logMessage .= "========================================\n\n";
    file_put_contents(__DIR__ . '/includes/php_errors.log', $logMessage, FILE_APPEND);

} catch (Exception $e) {
    $logMessage = "\nERROR: Scheduled email processing failed\n";
    $logMessage .= "Error message: " . $e->getMessage() . "\n";
    $logMessage .= "========================================\n\n";
    file_put_contents(__DIR__ . '/includes/php_errors.log', $logMessage, FILE_APPEND);
}
?> 