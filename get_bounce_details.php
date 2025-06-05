<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

try {
    $db = (new Database())->connect();
    
    // Validate bounce ID
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception("Invalid bounce ID");
    }
    
    // Get bounce details
    $stmt = $db->prepare("
        SELECT bl.*, c.subject as campaign_subject
        FROM bounce_logs bl
        JOIN campaigns c ON bl.campaign_id = c.campaign_id
        WHERE bl.bounce_id = ? AND bl.user_id = ?
    ");
    $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
    $bounce = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$bounce) {
        throw new Exception("Bounce not found");
    }
    
    echo json_encode([
        'success' => true,
        'bounce' => $bounce
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching bounce details: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 