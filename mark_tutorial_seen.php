<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

try {
    $db = (new Database())->connect();
    
    // Update the user's has_seen_tutorial status
    $stmt = $db->prepare("UPDATE users SET has_seen_tutorial = TRUE WHERE userId = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Error marking tutorial as seen: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?> 