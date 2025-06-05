<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['listId'])) {
    echo json_encode(['success' => false, 'message' => 'List ID is required']);
    exit();
}

try {
    $db = (new Database())->connect();
    
    // Start transaction
    $db->beginTransaction();
    
    // Check if user owns the list
    $stmt = $db->prepare("SELECT UserID FROM contactlists WHERE ListID = ?");
    $stmt->execute([$data['listId']]);
    $list = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$list || $list['UserID'] != $_SESSION['user_id']) {
        throw new Exception('You do not have permission to delete this list');
    }
    
    // Delete contacts first (due to foreign key constraint)
    $stmt = $db->prepare("DELETE FROM contacts WHERE ListID = ?");
    $stmt->execute([$data['listId']]);
    
    // Delete the list
    $stmt = $db->prepare("DELETE FROM contactlists WHERE ListID = ?");
    $stmt->execute([$data['listId']]);
    
    // Commit transaction
    $db->commit();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 