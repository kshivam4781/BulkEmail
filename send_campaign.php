<?php
session_start();
require_once 'config/database.php';
require_once 'includes/EmailSender.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

try {
    $db = (new Database())->connect();
    
    // Log the start of the process
    error_log("Starting campaign send process - " . date('Y-m-d H:i:s'));
    error_log("POST data: " . print_r($_POST, true));
    
    // Validate required fields
    if (!isset($_POST['subject']) || !isset($_POST['message'])) {
        error_log("Missing required fields - subject or message not set");
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
            error_log("Invalid CC email data: " . $_POST['cc_emails']);
            throw new Exception("Invalid CC email data");
        }
    }

    if (isset($_POST['excel_data']) && !empty($_POST['excel_data'])) {
        // Process Excel data
        $excelData = json_decode($_POST['excel_data'], true);
        if (!$excelData) {
            error_log("Invalid Excel data: " . $_POST['excel_data']);
            throw new Exception("Invalid Excel data");
        }

        error_log("Excel data processed - Headers: " . print_r($excelData['headers'], true));
        error_log("Number of rows: " . count($excelData['data']));

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
            error_log("No email column found in headers: " . print_r($excelData['headers'], true));
            throw new Exception('No email column found in Excel data');
        }

        // Extract email addresses from Excel
        foreach ($excelData['data'] as $row) {
            if (isset($row[$emailIndex])) {
                // Keep the entire cell content as one recipient group
                $emailGroup = trim($row[$emailIndex]);
                if (filter_var(explode(',', $emailGroup)[0], FILTER_VALIDATE_EMAIL)) {
                    $recipients[] = $emailGroup;
                }
            }
        }

        error_log("Number of valid recipients found: " . count($recipients));
    }

    if (empty($recipients)) {
        error_log("No valid email addresses found in the data");
        throw new Exception("No valid email addresses found");
    }

    // First, create the campaign record
    $db->beginTransaction();
    try {
        // Insert into campaigns table
        $stmt = $db->prepare("
            INSERT INTO campaigns (
                user_id, subject, message, message_content, 
                cc_emails, status, created_at
            ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $_POST['subject'],
            $_POST['message'],
            $_POST['message_content'],
            isset($_POST['cc_emails']) ? $_POST['cc_emails'] : null
        ]);
        
        $campaignId = $db->lastInsertId();
        
        if (!$campaignId) {
            error_log("Failed to create campaign record");
            throw new Exception("Failed to create campaign record");
        }

        error_log("Campaign created with ID: " . $campaignId);
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

    // Get SMTP configuration
    $stmt = $db->prepare("SELECT email, smtp, port, password FROM emailsender WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $smtp = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$smtp) {
        error_log("SMTP configuration not found for user: " . $_SESSION['user_id']);
        throw new Exception("SMTP configuration not found");
    }

    error_log("SMTP configuration loaded for: " . $smtp['email']);

    // Initialize email sender
    $emailSender = new EmailSender($smtp['email'], $smtp['smtp'], $smtp['port'], $smtp['password']);
    $emailSender->setUserId($_SESSION['user_id']);

    // Track success and failure counts
    $successCount = 0;
    $failureCount = 0;
    $errors = [];

    // Set script timeout to 0 (no timeout)
    set_time_limit(0);
    
    // Increase memory limit
    ini_set('memory_limit', '256M');

    // Process emails in batches
    $batchSize = 10; // Process 10 emails at a time
    $delayBetweenEmails = 2; // 2 seconds delay between emails
    $delayBetweenBatches = 5; // 5 seconds delay between batches
    
    $totalRecipients = count($recipients);
    $batches = array_chunk($recipients, $batchSize);

    foreach ($batches as $batchIndex => $batch) {
        error_log("Processing batch " . ($batchIndex + 1) . " of " . count($batches));
        
        foreach ($batch as $emailGroup) {
            try {
                $message = $_POST['message_content'];
                $subject = $_POST['subject'];
                
                // If Excel data is available, personalize the message and subject
                if ($excelData) {
                    // Get email column index
                    $emailIndex = array_search('Email', $excelData['headers']);
                    if ($emailIndex === false) {
                        $errors[] = "Email column not found in headers for: " . $emailGroup;
                        $failureCount++;
                        error_log("Email column not found for: " . $emailGroup);
                        continue;
                    }

                    // Get the current row index from the email group
                    $currentRowIndex = false;
                    foreach ($excelData['data'] as $index => $row) {
                        if (isset($row[$emailIndex]) && trim($row[$emailIndex]) === $emailGroup) {
                            $currentRowIndex = $index;
                            break;
                        }
                    }
                    
                    if ($currentRowIndex === false) {
                        $errors[] = "Email group not found in data: " . $emailGroup;
                        $failureCount++;
                        error_log("Email group not found in data: " . $emailGroup);
                        continue;
                    }

                    // Get the row data for this index
                    $rowData = $excelData['data'][$currentRowIndex];
                    
                    if ($rowData) {
                        // Replace placeholders in message and subject
                        foreach ($excelData['headers'] as $index => $header) {
                            if (empty($header)) continue;
                            
                            $placeholder = '{{' . $header . '}}';
                            $value = $rowData[$index] ?? '';
                            
                            $message = str_replace($placeholder, $value, $message);
                            $subject = str_replace($placeholder, $value, $subject);
                        }
                    }
                }

                // Add tracking pixel to the message
                $trackingPixel = '<img src="http://' . $_SERVER['HTTP_HOST'] . '/bulk/track.php?c=' . $campaignId . '&e=' . urlencode($emailGroup) . '" width="1" height="1" style="display:none" />';
                $message .= $trackingPixel;

                error_log("Attempting to send email to: " . $emailGroup);

                // Send email with CC recipients
                $emailSender->sendEmail($emailGroup, $subject, $message, $ccRecipients);

                // Log successful send
                $db->beginTransaction();
                try {
                    $stmt = $db->prepare("
                        INSERT INTO email_logs (campaign_id, user_id, to_emails, subject, from_email, status, cc_emails) 
                        VALUES (?, ?, ?, ?, ?, 'sent', ?)
                    ");
                    $stmt->execute([
                        $campaignId,
                        $_SESSION['user_id'],
                        $emailGroup,
                        $subject,
                        $smtp['email'],
                        $ccRecipients ? json_encode($ccRecipients) : null
                    ]);

                    // Update campaign stats
                    $stmt = $db->prepare("
                        UPDATE campaigns 
                        SET success_count = success_count + 1,
                            status = CASE 
                                WHEN failure_count = 0 THEN 'sent'
                                ELSE 'partially_sent'
                            END
                        WHERE campaign_id = ?
                    ");
                    $stmt->execute([$campaignId]);

                    $db->commit();
                    $successCount++;
                    error_log("Successfully sent email to: " . $emailGroup);
                } catch (Exception $e) {
                    $db->rollBack();
                    error_log("Failed to update logs for successful email: " . $e->getMessage());
                }

                // Add delay between emails
                sleep($delayBetweenEmails);

            } catch (Exception $e) {
                // Log failed send
                $db->beginTransaction();
                try {
                    $stmt = $db->prepare("
                        INSERT INTO email_logs (campaign_id, user_id, to_emails, subject, from_email, status, error_message, cc_emails) 
                        VALUES (?, ?, ?, ?, ?, 'failed', ?, ?)
                    ");
                    $stmt->execute([
                        $campaignId,
                        $_SESSION['user_id'],
                        $emailGroup,
                        $subject,
                        $smtp['email'],
                        $e->getMessage(),
                        $ccRecipients ? json_encode($ccRecipients) : null
                    ]);

                    // Update campaign stats
                    $stmt = $db->prepare("
                        UPDATE campaigns 
                        SET failure_count = failure_count + 1,
                            status = CASE 
                                WHEN success_count = 0 THEN 'failed'
                                ELSE 'partially_sent'
                            END,
                            error_log = JSON_ARRAY_APPEND(
                                COALESCE(error_log, JSON_ARRAY()),
                                '$',
                                ?
                            )
                        WHERE campaign_id = ?
                    ");
                    $stmt->execute([
                        json_encode("Failed to send to " . $emailGroup . ": " . $e->getMessage()),
                        $campaignId
                    ]);

                    $db->commit();
                    $errors[] = "Failed to send to " . $emailGroup . ": " . $e->getMessage();
                    $failureCount++;
                    error_log("Failed to send email to " . $emailGroup . ": " . $e->getMessage());
                } catch (Exception $dbError) {
                    $db->rollBack();
                    error_log("Failed to update logs for failed email: " . $dbError->getMessage());
                }
            }
        }

        // Add delay between batches
        if ($batchIndex < count($batches) - 1) {
            error_log("Waiting " . $delayBetweenBatches . " seconds before next batch...");
            sleep($delayBetweenBatches);
        }
    }

    // Final campaign status update
    $db->beginTransaction();
    try {
        $campaignStatus = ($failureCount === 0) ? 'sent' : 
                         (($successCount === 0) ? 'failed' : 'partially_sent');
        
        $stmt = $db->prepare("
            UPDATE campaigns 
            SET status = ?,
                success_count = ?,
                failure_count = ?,
                error_log = ?
            WHERE campaign_id = ?
        ");
        $stmt->execute([
            $campaignStatus,
            $successCount,
            $failureCount,
            json_encode($errors),
            $campaignId
        ]);
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Failed to update final campaign status: " . $e->getMessage());
    }

    error_log("Campaign completed - Status: " . $campaignStatus . ", Success: " . $successCount . ", Failures: " . $failureCount);

    echo json_encode([
        'success' => true,
        'message' => sprintf(
            'Campaign processed. Success: %d, Failures: %d', 
            $successCount, 
            $failureCount
        ),
        'campaign_id' => $campaignId,
        'status' => $campaignStatus,
        'errors' => $errors
    ]);
    exit;

} catch (Exception $e) {
    error_log("Campaign sending error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'Error sending campaign: ' . $e->getMessage()
    ]);
    exit;
}
?>