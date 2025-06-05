<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

try {
    // Get JSON data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['draftId'])) {
        throw new Exception("Draft ID is required");
    }

    $db = (new Database())->connect();
    
    // Get draft details to delete attachment
    $stmt = $db->prepare("SELECT Attachment FROM emaildrafts WHERE DraftID = ? AND UserID = ?");
    $stmt->execute([$data['draftId'], $_SESSION['user_id']]);
    $draft = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($draft && $draft['Attachment']) {
        // Delete the file
        $filePath = 'uploads/drafts/' . $draft['Attachment'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Update the draft to remove attachment reference
        $stmt = $db->prepare("UPDATE emaildrafts SET Attachment = NULL WHERE DraftID = ? AND UserID = ?");
        $stmt->execute([$data['draftId'], $_SESSION['user_id']]);
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 