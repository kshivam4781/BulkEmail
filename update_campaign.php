<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: scheduled_campaigns.php');
    exit();
}

$campaignId = $_POST['campaign_id'] ?? null;
if (!$campaignId) {
    $_SESSION['message'] = 'Invalid campaign ID.';
    $_SESSION['message_type'] = 'danger';
    header('Location: scheduled_campaigns.php');
    exit();
}

try {
    $db = (new Database())->connect();
    $db->beginTransaction();

    // Validate campaign ownership
    $stmt = $db->prepare("SELECT * FROM campaigns WHERE campaign_id = ? AND user_id = ?");
    $stmt->execute([$campaignId, $_SESSION['user_id']]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$campaign) {
        throw new Exception('Campaign not found or unauthorized.');
    }

    // Process scheduled time
    $scheduledDate = $_POST['scheduled_date'];
    $scheduledTime = $_POST['scheduled_time'];
    $scheduledDateTime = new DateTime($scheduledDate . ' ' . $scheduledTime, new DateTimeZone('America/Los_Angeles'));
    $scheduledDateTime->setTimezone(new DateTimeZone('UTC'));

    // Validate scheduled time
    $now = new DateTime('now', new DateTimeZone('UTC'));
    if ($scheduledDateTime <= $now) {
        throw new Exception('Scheduled time must be in the future.');
    }

    // Process CC emails
    $ccEmails = array_filter($_POST['cc'] ?? [], function($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    });

    // Process attachment
    $attachmentPath = $campaign['attachment_path'];
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/attachments/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = uniqid() . '_' . basename($_FILES['attachment']['name']);
        $uploadFile = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadFile)) {
            // Delete old attachment if exists
            if ($attachmentPath && file_exists($attachmentPath)) {
                unlink($attachmentPath);
            }
            $attachmentPath = $uploadFile;
        }
    }

    // Update campaign
    $stmt = $db->prepare("
        UPDATE campaigns 
        SET subject = ?,
            message_content = ?,
            scheduled_time = ?,
            cc_emails = ?,
            attachment_path = ?,
            updated_at = NOW()
        WHERE campaign_id = ? AND user_id = ?
    ");

    $stmt->execute([
        $_POST['subject'],
        $_POST['message'],
        $scheduledDateTime->format('Y-m-d H:i:s'),
        json_encode($ccEmails),
        $attachmentPath,
        $campaignId,
        $_SESSION['user_id']
    ]);

    // Delete existing scheduled emails
    $stmt = $db->prepare("DELETE FROM scheduled_emails WHERE campaign_id = ?");
    $stmt->execute([$campaignId]);

    // Insert new scheduled emails
    $stmt = $db->prepare("
        INSERT INTO scheduled_emails (campaign_id, to_emails, scheduled_time, status)
        VALUES (?, ?, ?, 'scheduled')
    ");

    foreach ($_POST['recipients'] as $recipient) {
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $stmt->execute([
                $campaignId,
                $recipient,
                $scheduledDateTime->format('Y-m-d H:i:s')
            ]);
        }
    }

    $db->commit();

    $_SESSION['message'] = 'Campaign updated successfully.';
    $_SESSION['message_type'] = 'success';
    header('Location: scheduled_campaign.php?campaign_id=' . $campaignId);
    exit();

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $_SESSION['message'] = 'Error updating campaign: ' . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
    header('Location: edit_scheduled_campaign.php?id=' . $campaignId);
    exit();
} 