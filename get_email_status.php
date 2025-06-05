<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get campaign ID from URL
$campaignId = isset($_GET['campaign_id']) ? (int)$_GET['campaign_id'] : 0;

if (!$campaignId) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid campaign ID']);
    exit();
}

try {
    $db = (new Database())->connect();
    
    // Get email logs for this campaign
    $stmt = $db->prepare("
        SELECT * FROM email_logs 
        WHERE campaign_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$campaignId]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count statistics
    $total = count($logs);
    $sent = 0;
    $failed = 0;
    foreach ($logs as $log) {
        if ($log['status'] === 'sent') {
            $sent++;
        } else if ($log['status'] === 'failed') {
            $failed++;
        }
    }

    // Return JSON response
    echo json_encode([
        'total' => $total,
        'sent' => $sent,
        'failed' => $failed,
        'logs' => $logs
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?> 