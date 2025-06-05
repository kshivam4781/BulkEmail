<?php
require_once 'config/error_log.php';
require_once 'config/database.php';

// Enable error logging
error_log("=== Tracking pixel accessed at " . date('Y-m-d H:i:s') . " ===");
error_log("Request URI: " . $_SERVER['REQUEST_URI']);
error_log("Remote Address: " . $_SERVER['REMOTE_ADDR']);
error_log("User Agent: " . $_SERVER['HTTP_USER_AGENT']);
error_log("Referer: " . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'No referer'));

// Get campaign ID and recipient email from URL parameters
$campaignId = isset($_GET['c']) ? $_GET['c'] : null;
$recipientEmail = isset($_GET['e']) ? $_GET['e'] : null;

error_log("Campaign ID: " . $campaignId);
error_log("Recipient Email: " . $recipientEmail);

if ($campaignId && $recipientEmail) {
    try {
        $db = (new Database())->connect();
        error_log("Database connection successful");
        
        // Check if tracking record exists
        $stmt = $db->prepare("
            SELECT * FROM email_tracking 
            WHERE CampaignID = ? AND RecipientEmail = ?
        ");
        $stmt->execute([$campaignId, $recipientEmail]);
        $tracking = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get email client information
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        $emailClient = 'Unknown';
        
        // Detect email client
        if (strpos($userAgent, 'Gmail') !== false || strpos($_SERVER['HTTP_REFERER'] ?? '', 'gmail.com') !== false) {
            $emailClient = 'Gmail';
        } elseif (strpos($userAgent, 'Outlook') !== false) {
            $emailClient = 'Outlook';
        } elseif (strpos($userAgent, 'Yahoo') !== false) {
            $emailClient = 'Yahoo';
        }

        if ($tracking) {
            error_log("Updating existing tracking record for Campaign ID: " . $campaignId);
            // Update existing tracking record
            $stmt = $db->prepare("
                UPDATE email_tracking 
                SET OpenCount = OpenCount + 1,
                    LastOpenedAt = CURRENT_TIMESTAMP,
                    UserAgent = ?,
                    IPAddress = ?,
                    EmailClient = ?
                WHERE CampaignID = ? AND RecipientEmail = ?
            ");
            $stmt->execute([
                $userAgent,
                $_SERVER['REMOTE_ADDR'],
                $emailClient,
                $campaignId,
                $recipientEmail
            ]);
            error_log("Update successful");
        } else {
            error_log("Creating new tracking record for Campaign ID: " . $campaignId);
            // Create new tracking record
            $stmt = $db->prepare("
                INSERT INTO email_tracking 
                (CampaignID, RecipientEmail, FirstOpenedAt, LastOpenedAt, OpenCount, UserAgent, IPAddress, EmailClient)
                VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1, ?, ?, ?)
            ");
            $stmt->execute([
                $campaignId,
                $recipientEmail,
                $userAgent,
                $_SERVER['REMOTE_ADDR'],
                $emailClient
            ]);
            error_log("Insert successful");
        }
        error_log("Tracking record saved successfully");
    } catch (PDOException $e) {
        // Log error but don't expose it to the user
        error_log("Database Error: " . $e->getMessage());
        error_log("SQL State: " . $e->getCode());
    }
} else {
    error_log("Missing required parameters - Campaign ID or Recipient Email is null");
}

// Output a 1x1 transparent GIF
header('Content-Type: image/gif');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// 1x1 transparent GIF
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
?> 