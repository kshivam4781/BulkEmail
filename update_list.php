<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

if (!isset($_POST['listId']) || !isset($_POST['name'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

try {
    $db = (new Database())->connect();
    
    // Verify list belongs to user
    $stmt = $db->prepare("SELECT ListID FROM contactlists WHERE ListID = ? AND UserID = ?");
    $stmt->execute([$_POST['listId'], $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'List not found or unauthorized']);
        exit();
    }
    
    // Start transaction
    $db->beginTransaction();
    
    // Update list name
    $stmt = $db->prepare("UPDATE contactlists SET Name = ? WHERE ListID = ?");
    $stmt->execute([$_POST['name'], $_POST['listId']]);
    
    // Add new contacts if any
    if (isset($_POST['contacts'])) {
        $contacts = json_decode($_POST['contacts'], true);
        if (is_array($contacts)) {
            $stmt = $db->prepare("
                INSERT INTO contacts (UserID, ListID, FullName, Email, CreatedAt) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            foreach ($contacts as $contact) {
                $stmt->execute([
                    $_SESSION['user_id'],
                    $_POST['listId'],
                    $contact['name'],
                    $contact['email']
                ]);
            }
        }
    }
    
    // Commit transaction
    $db->commit();
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?> 