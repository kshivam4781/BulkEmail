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
    
    // Get draft details to delete attachment if exists
    $stmt = $db->prepare("SELECT Attachment FROM emaildrafts WHERE DraftID = ? AND UserID = ?");
    $stmt->execute([$data['draftId'], $_SESSION['user_id']]);
    $draft = $stmt->fetch(PDO::FETCH_ASSOC);

    // Delete the draft
    $stmt = $db->prepare("DELETE FROM emaildrafts WHERE DraftID = ? AND UserID = ?");
    $stmt->execute([$data['draftId'], $_SESSION['user_id']]);

    // Delete attachment if exists
    if ($draft && $draft['Attachment']) {
        $filePath = 'uploads/drafts/' . $draft['Attachment'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 