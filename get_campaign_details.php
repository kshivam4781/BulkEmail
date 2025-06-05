<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'error' => 'Not authenticated']));
}

// Check if campaign ID is provided
if (!isset($_GET['id'])) {
    die(json_encode(['success' => false, 'error' => 'No campaign ID provided']));
}

try {
    $db = (new Database())->connect();
    
    // First get the campaign details
    $stmt = $db->prepare("
        SELECT c.*, u.name as sender_name
        FROM campaigns c
        JOIN users u ON c.user_id = u.userId
        WHERE c.campaign_id = ? AND c.user_id = ?
    ");
    $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$campaign) {
        die(json_encode(['success' => false, 'error' => 'Campaign not found']));
    }

    // Then get all emails from this campaign
    $stmt = $db->prepare("
        SELECT 
            to_emails,
            status,
            created_at
        FROM email_logs 
        WHERE campaign_id = ? AND user_id = ?
        ORDER BY created_at DESC
    ");
    
    $stmt->execute([
        $_GET['id'],
        $_SESSION['user_id']
    ]);
    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($details) {
        echo json_encode([
            'success' => true,
            'campaign' => $campaign,
            'details' => $details
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No details found for this campaign'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 