<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$campaignId = $data['campaign_id'] ?? null;

if (!$campaignId) {
    echo json_encode(['success' => false, 'message' => 'Campaign ID is required']);
    exit();
}

try {
    $db = (new Database())->connect();
    
    // Start transaction
    $db->beginTransaction();
    
    // First, delete scheduled emails
    $stmt = $db->prepare("DELETE FROM scheduled_emails WHERE campaign_id = ?");
    $stmt->execute([$campaignId]);
    
    // Then delete the campaign
    $stmt = $db->prepare("DELETE FROM campaigns WHERE campaign_id = ? AND user_id = ?");
    $stmt->execute([$campaignId, $_SESSION['user_id']]);
    
    // Commit transaction
    $db->commit();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("Error deleting campaign: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error deleting campaign']);
}
?> 