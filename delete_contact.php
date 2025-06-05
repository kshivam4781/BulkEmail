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

if (!isset($data['contactId'])) {
    echo json_encode(['success' => false, 'message' => 'Missing contact ID']);
    exit();
}

try {
    $db = (new Database())->connect();
    
    // Verify contact belongs to user's list
    $stmt = $db->prepare("
        SELECT c.ContactID 
        FROM contacts c
        JOIN contactlists cl ON c.ListID = cl.ListID
        WHERE c.ContactID = ? AND cl.UserID = ?
    ");
    $stmt->execute([$data['contactId'], $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Contact not found or unauthorized']);
        exit();
    }
    
    // Delete contact
    $stmt = $db->prepare("DELETE FROM contacts WHERE ContactID = ?");
    $stmt->execute([$data['contactId']]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?> 