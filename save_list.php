<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Validate input
if (!isset($_POST['name']) || !isset($_POST['contacts'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$name = trim($_POST['name']);
$contacts = json_decode($_POST['contacts'], true);

if (empty($name) || empty($contacts)) {
    echo json_encode(['success' => false, 'message' => 'List name and contacts are required']);
    exit();
}

try {
    $db = (new Database())->connect();
    
    // Start transaction
    $db->beginTransaction();
    
    // Insert list
    $stmt = $db->prepare("
        INSERT INTO contactlists (UserID, Name, CreatedAt) 
        VALUES (?, ?, NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], $name]);
    $listId = $db->lastInsertId();
    
    // Insert contacts
    $stmt = $db->prepare("
        INSERT INTO contacts (UserID, ListID, FullName, Email, CreatedAt) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    foreach ($contacts as $contact) {
        $stmt->execute([
            $_SESSION['user_id'],
            $listId,
            $contact['name'],
            $contact['email']
        ]);
    }
    
    // Commit transaction
    $db->commit();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 