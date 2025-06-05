<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

try {
    $db = (new Database())->connect();
    
    // Log start of scheduling process
    $logMessage = "\n\n=== " . date('Y-m-d H:i:s') . " Pacific Time ===\n";
    $logMessage .= "User ID: " . $_SESSION['user_id'] . "\n";
    $logMessage .= "=== Starting Campaign Scheduling ===\n";
    file_put_contents(__DIR__ . '/includes/php_errors.log', $logMessage, FILE_APPEND);
    
    // Validate required fields
    if (!isset($_POST['subject']) || !isset($_POST['message']) || !isset($_POST['scheduled_for'])) {
        $logMessage = "ERROR: Missing required fields\n";
        $logMessage .= "POST data: " . print_r($_POST, true) . "\n";
        file_put_contents(__DIR__ . '/includes/php_errors.log', $logMessage, FILE_APPEND);
        throw new Exception("Missing required fields");
    }

    // Get recipients from Excel data
    $recipients = [];
    $ccRecipients = [];
    $excelData = null;

    // Process CC recipients if available
    if (isset($_POST['cc_emails']) && !empty($_POST['cc_emails'])) {
        $ccRecipients = json_decode($_POST['cc_emails'], true);
        if (!$ccRecipients) {
            $logMessage = "ERROR: Invalid CC email data\n";
            $logMessage .= "CC data: " . $_POST['cc_emails'] . "\n";
            file_put_contents(__DIR__ . '/includes/php_errors.log', $logMessage, FILE_APPEND);
            throw new Exception("Invalid CC email data");
        }
    }

    if (isset($_POST['excel_data']) && !empty($_POST['excel_data'])) {
        // Process Excel data
        $excelData = json_decode($_POST['excel_data'], true);
        if (!$excelData) {
            $logMessage = "ERROR: Invalid Excel data\n";
            $logMessage .= "Excel data: " . $_POST['excel_data'] . "\n";
            file_put_contents(__DIR__ . '/includes/php_errors.log', $logMessage, FILE_APPEND);
            throw new Exception("Invalid Excel data");
        }

        $logMessage = "\n=== Processing Excel Data ===\n";
        $logMessage .= "Headers: " . print_r($excelData['headers'], true) . "\n";
        $logMessage .= "Number of rows: " . count($excelData['data']) . "\n";
        file_put_contents(__DIR__ . '/includes/php_errors.log', $logMessage, FILE_APPEND);

        // Find email column index
        $emailIndex = null;
        $emailVariations = ['email', 'e-mail', 'email address', 'emailaddress'];
        
        foreach ($excelData['headers'] as $index => $header) {
            $headerLower = strtolower(trim($header));
            if (in_array($headerLower, $emailVariations)) {
                $emailIndex = $index;
                break;
            }
        }
        
        if ($emailIndex === null) {
            $logMessage = "ERROR: No email column found in Excel data\n";
            $logMessage .= "Headers: " . print_r($excelData['headers'], true) . "\n";
            file_put_contents(__DIR__ . '/includes/php_errors.log', $logMessage, FILE_APPEND);
            throw new Exception('No email column found in Excel data');
        }

        // Extract email addresses from Excel
        foreach ($excelData['data'] as $rowIndex => $row) {
            if (isset($row[$emailIndex])) {
                // Split multiple emails in the cell by comma
                $emails = array_map('trim', explode(',', $row[$emailIndex]));
                foreach ($emails as $email) {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $recipients[] = $email;
                    }
                }
            }
        }

        $logMessage = "\n=== Recipients Found ===\n";
        $logMessage .= "Total valid recipients: " . count($recipients) . "\n";
        file_put_contents(__DIR__ . '/includes/php_errors.log', $logMessage, FILE_APPEND);
    }

    if (empty($recipients)) {
        $logMessage = "ERROR: No valid email addresses found\n";
        file_put_contents(__DIR__ . '/includes/php_errors.log', $logMessage, FILE_APPEND);
        throw new Exception("No valid email addresses found");
    }

    // Validate scheduled time
    $scheduledDateTime = new DateTime($_POST['scheduled_for']);
    $now = new DateTime();
    
    // Set timezone to Pacific
    $scheduledDateTime->setTimezone(new DateTimeZone('America/Los_Angeles'));
    $now->setTimezone(new DateTimeZone('America/Los_Angeles'));
    
    $logMessage = "\n=== Schedule Validation ===\n";
    $logMessage .= "Scheduled time: " . $scheduledDateTime->format('Y-m-d H:i:s') . "\n";
    $logMessage .= "Current time: " . $now->format('Y-m-d H:i:s') . "\n";
    file_put_contents(__DIR__ . '/includes/php_errors.log', $logMessage, FILE_APPEND);
    
    if ($scheduledDateTime <= $now) {
        $logMessage = "ERROR: Scheduled time must be in the future\n";
        file_put_contents(__DIR__ . '/includes/php_errors.log', $logMessage, FILE_APPEND);
        throw new Exception('Scheduled time must be in the future');
    }
    
    // Format datetime for MySQL while preserving Pacific time
    $formattedScheduledTime = $scheduledDateTime->format('Y-m-d H:i:s');

    // Begin transaction
    $db->beginTransaction();

    try {
        // Insert into campaigns table
        $stmt = $db->prepare("
            INSERT INTO campaigns (
                user_id, subject, message, message_content, 
                scheduled_time, cc_emails, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'scheduled', NOW())
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $_POST['subject'],
            $_POST['message'],
            $_POST['message_content'],
            $formattedScheduledTime,
            isset($_POST['cc_emails']) ? $_POST['cc_emails'] : null
        ]);
        
        $campaignId = $db->lastInsertId();
        
        if (!$campaignId) {
            $logMessage = "ERROR: Failed to create campaign record\n";
            file_put_contents(__DIR__ . '/includes/php_errors.log', $logMessage, FILE_APPEND);
            throw new Exception("Failed to create campaign record");
        }

        $logMessage = "\n=== Campaign Created ===\n";
        $logMessage .= "Campaign ID: " . $campaignId . "\n";
        file_put_contents(__DIR__ . '/includes/php_errors.log', $logMessage, FILE_APPEND);

        // Process attachment if available
        $attachmentPath = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileName = uniqid() . '_' . basename($_FILES['attachment']['name']);
            $attachmentPath = $uploadDir . $fileName;
            if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $attachmentPath)) {
                $logMessage = "ERROR: Failed to upload attachment\n";
                file_put_contents(__DIR__ . '/includes/php_errors.log', $logMessage, FILE_APPEND);
                throw new Exception("Failed to upload attachment");
            }
        }

        // Insert into scheduled_emails table for each recipient
        $stmt = $db->prepare("
            INSERT INTO scheduled_emails (
                campaign_id, user_id, from_email, to_emails, subject, body,
                scheduled_time, attachment_path, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");

        $logMessage = "\n=== Processing Recipients ===\n";
        file_put_contents(__DIR__ . '/includes/php_errors.log', $logMessage, FILE_APPEND);

        $recipientCount = 0;
        foreach ($recipients as $email) {
            $personalizedBody = $_POST['message_content'];
            $personalizedSubject = $_POST['subject'];
            
            // Replace placeholders with actual values if Excel data is available
            if ($excelData) {
                // Get row data for this email
                $rowData = null;
                foreach ($excelData['data'] as $rowIndex => $row) {
                    if ($row[$emailIndex] === $email) {
                        $rowData = $row;
                        break;
                    }
                }

                if ($rowData) {
                    $logMessage = "\nProcessing email: " . $email . "\n";
                    $logMessage .= "Row data: " . print_r($rowData, true) . "\n";
                    file_put_contents(__DIR__ . '/includes/php_errors.log', $logMessage, FILE_APPEND);

                    foreach ($excelData['headers'] as $index => $header) {
                        if (empty($header)) continue;
                        
                        $placeholder = '{{' . $header . '}}';
                        $value = $rowData[$index] ?? '';
                        
                        $personalizedBody = str_replace($placeholder, $value, $personalizedBody);
                        $personalizedSubject = str_replace($placeholder, $value, $personalizedSubject);
                    }
                }
            }

            $stmt->execute([
                $campaignId,
                $_SESSION['user_id'],
                $_POST['from'],
                $email,
                $personalizedSubject,
                $personalizedBody,
                $formattedScheduledTime,
                $attachmentPath
            ]);
            
            $recipientCount++;
        }

        // Commit transaction
        $db->commit();

        $logMessage = "\n=== Campaign Scheduling Complete ===\n";
        $logMessage .= "Time: " . date('Y-m-d H:i:s') . " Pacific Time\n";
        $logMessage .= "User ID: " . $_SESSION['user_id'] . "\n";
        $logMessage .= "Campaign ID: " . $campaignId . "\n";
        $logMessage .= "Total recipients: " . count($recipients) . "\n";
        $logMessage .= "========================================\n\n";
        file_put_contents(__DIR__ . '/includes/php_errors.log', $logMessage, FILE_APPEND);

        echo json_encode([
            'success' => true,
            'message' => 'Campaign scheduled successfully',
            'campaign_id' => $campaignId,
            'redirect' => 'scheduled_campaign.php?campaign_id=' . $campaignId
        ]);
        exit;

    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollBack();
        $logMessage = "\nERROR: Transaction failed\n";
        $logMessage .= "Error message: " . $e->getMessage() . "\n";
        file_put_contents(__DIR__ . '/includes/php_errors.log', $logMessage, FILE_APPEND);
        throw $e;
    }

} catch (Exception $e) {
    $logMessage = "\nERROR: Campaign scheduling failed\n";
    $logMessage .= "Error message: " . $e->getMessage() . "\n";
    $logMessage .= "========================================\n\n";
    file_put_contents(__DIR__ . '/includes/php_errors.log', $logMessage, FILE_APPEND);
    
    error_log("Campaign scheduling error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error scheduling campaign: ' . $e->getMessage()
    ]);
    exit;
}
?>