<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

if (!isset($_POST['subject']) || !isset($_POST['body'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

try {
    $db = (new Database())->connect();
    
    // Start transaction
    $db->beginTransaction();
    
    // Check for similar drafts
    $stmt = $db->prepare("
        SELECT d.*, u.email as owner_email 
        FROM emaildrafts d
        JOIN users u ON d.UserID = u.userId
        WHERE d.UserID = ? 
        AND d.Subject = ? 
        AND d.Body = ?
        AND d.DraftID != ?
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $_POST['subject'],
        $_POST['body'],
        isset($_POST['draftId']) ? $_POST['draftId'] : 0
    ]);
    $similarDraft = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($similarDraft) {
        // Format the date to be more readable
        $createdAt = new DateTime($similarDraft['CreatedAt']);
        $formattedDate = $createdAt->format('F j, Y \a\t g:i A');
        
        throw new Exception("A similar draft already exists in your account, created on " . $formattedDate);
    }
    
    if (isset($_POST['draftId'])) {
        // Verify draft belongs to user
        $stmt = $db->prepare("
            SELECT d.*, u.email as owner_email 
            FROM emaildrafts d
            JOIN users u ON d.UserID = u.userId
            WHERE d.DraftID = ?
        ");
        $stmt->execute([$_POST['draftId']]);
        $draft = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$draft) {
            throw new Exception('Draft not found');
        }
        
        if ($draft['UserID'] != $_SESSION['user_id']) {
            throw new Exception("This draft belongs to " . htmlspecialchars($draft['owner_email']) . ". Please contact them to make changes.");
        }
        
        // Update existing draft
        $stmt = $db->prepare("
            UPDATE emaildrafts 
            SET Subject = ?, Body = ?, LastModified = CURRENT_TIMESTAMP
            WHERE DraftID = ? AND UserID = ?
        ");
        $stmt->execute([
            $_POST['subject'],
            $_POST['body'],
            $_POST['draftId'],
            $_SESSION['user_id']
        ]);
        
        $draftId = $_POST['draftId'];
        $message = 'Draft updated successfully';
    } else {
        // Create new draft
        $stmt = $db->prepare("
            INSERT INTO emaildrafts (UserID, Subject, Body, CreatedAt, LastModified)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $_POST['subject'],
            $_POST['body']
        ]);
        
        $draftId = $db->lastInsertId();
        $message = 'Draft saved successfully';
    }
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'draftId' => $draftId,
        'message' => $message
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?> 