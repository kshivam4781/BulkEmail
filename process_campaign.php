<?php
session_start();

// Set unlimited execution time and increase memory limit
set_time_limit(0);
ini_set('memory_limit', '512M');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once 'config/database.php';
require_once 'includes/EmailSender.php';

// Set proper content type for JSON responses
header('Content-Type: application/json');

// Function to send JSON response
function sendJsonResponse($success, $message, $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Error handler to catch any PHP errors
function handleError($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    sendJsonResponse(false, "An error occurred while processing your request");
}

// Set error handler
set_error_handler('handleError');

if (!isset($_SESSION['user_id'])) {
    sendJsonResponse(false, "User not logged in");
}

try {
    $db = (new Database())->connect();
    
    // Validate required fields
    if (!isset($_POST['subject']) || !isset($_POST['message'])) {
        sendJsonResponse(false, "Missing required fields");
    }

    // Get recipients either from Excel or manual input
    $recipients = [];
    $ccRecipients = [];
    $bccRecipients = [];
    $excelData = null;

    // Process CC recipients if available
    if (isset($_POST['cc']) && !empty($_POST['cc'])) {
        $ccEmails = array_filter(explode("\n", str_replace("\r", "", $_POST['cc'])));
        $ccRecipients = [];
        foreach ($ccEmails as $email) {
            // Split by comma and clean each email
            $emails = array_map('trim', explode(',', $email));
            foreach ($emails as $singleEmail) {
                if (filter_var($singleEmail, FILTER_VALIDATE_EMAIL)) {
                    $ccRecipients[] = $singleEmail;
                }
            }
        }
    }

    // Process BCC recipients if available
    if (isset($_POST['bcc']) && !empty($_POST['bcc'])) {
        $bccEmails = array_filter(explode("\n", str_replace("\r", "", $_POST['bcc'])));
        $bccRecipients = [];
        foreach ($bccEmails as $email) {
            // Split by comma and clean each email
            $emails = array_map('trim', explode(',', $email));
            foreach ($emails as $singleEmail) {
                if (filter_var($singleEmail, FILTER_VALIDATE_EMAIL)) {
                    $bccRecipients[] = $singleEmail;
                }
            }
        }
    }

    if (isset($_POST['excel_data']) && !empty($_POST['excel_data'])) {
        // Process Excel data
        $excelData = json_decode($_POST['excel_data'], true);
        if (!$excelData) {
            sendJsonResponse(false, "Invalid Excel data");
        }

        // Find email column index - more flexible search
        $emailIndex = null;
        $emailVariations = [
            'email', 'e-mail', 'email address', 'emailaddress',
            'Email', 'E-mail', 'Email Address', 'EmailAddress',
            'EMAIL', 'E-MAIL', 'EMAIL ADDRESS', 'EMAILADDRESS',
            'eMail', 'e-mail', 'eMail Address', 'eMailAddress'
        ];
        
        foreach ($excelData['headers'] as $index => $header) {
            $headerLower = strtolower(trim($header));
            $headerUpper = strtoupper(trim($header));
            $headerOriginal = trim($header);
            
            if (in_array($headerLower, array_map('strtolower', $emailVariations)) ||
                in_array($headerUpper, array_map('strtoupper', $emailVariations)) ||
                in_array($headerOriginal, $emailVariations)) {
                $emailIndex = $index;
                break;
            }
        }
        
        if ($emailIndex === null) {
            sendJsonResponse(false, 'No email column found in Excel data. Please ensure your Excel file has a column named "Email" (case insensitive)');
        }

        // Extract email addresses from Excel while preserving original format
        foreach ($excelData['data'] as $row) {
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
    } else if (isset($_POST['to']) && !empty($_POST['to'])) {
        // Process manual email input
        $manualEmails = array_filter(explode("\n", str_replace("\r", "", $_POST['to'])));
        foreach ($manualEmails as $email) {
            $email = trim($email);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $recipients[] = $email;
            }
        }
    }

    if (empty($recipients)) {
        sendJsonResponse(false, "No valid email addresses found");
    }

    // Check if this is a scheduled campaign
    $isScheduled = isset($_POST['scheduled_for']) && !empty($_POST['scheduled_for']);
    $scheduledTime = $isScheduled ? $_POST['scheduled_for'] : null;

    if ($isScheduled) {
        try {
            // Debug logging
            error_log("POST data received: " . print_r($_POST, true));
            error_log("FILES data received: " . print_r($_FILES, true));
            
            // Validate required fields
            if (empty($_POST['subject'])) {
                sendJsonResponse(false, "Subject is required");
            }
            if (empty($_POST['message'])) {
                sendJsonResponse(false, "Message is required");
            }
            if (empty($_POST['message_content'])) {
                sendJsonResponse(false, "Message content is required");
            }
            
            // Validate scheduled time
            $scheduledDateTime = new DateTime($scheduledTime);
            $now = new DateTime();
            
            // Set timezone to Pacific
            $scheduledDateTime->setTimezone(new DateTimeZone('America/Los_Angeles'));
            $now->setTimezone(new DateTimeZone('America/Los_Angeles'));
            
            if ($scheduledDateTime <= $now) {
                sendJsonResponse(false, 'Scheduled time must be in the future');
            }
            
            // Format datetime for MySQL while preserving Pacific time
            $formattedScheduledTime = $scheduledDateTime->format('Y-m-d H:i:s');
            
            // Begin transaction
            $db->beginTransaction();
            
            try {
                // Insert into campaigns table
                $stmt = $db->prepare("
                    INSERT INTO campaigns (
                        user_id, subject, message, scheduled_time, 
                        message_content, cc_emails, bcc_emails, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', NOW())
                ");
                
                $stmt->execute([
                    $_SESSION['user_id'],
                    $_POST['subject'],
                    $_POST['message'],
                    $formattedScheduledTime,
                    $_POST['message_content'],
                    !empty($ccRecipients) ? json_encode($ccRecipients) : null,
                    !empty($bccRecipients) ? json_encode($bccRecipients) : null
                ]);
                
                $campaignId = $db->lastInsertId();
                
                if (!$campaignId) {
                    throw new Exception("Failed to create campaign record");
                }
                
                // Process Excel data if available
                if (isset($_POST['excel_data'])) {
                    $excelData = json_decode($_POST['excel_data'], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new Exception("Invalid Excel data format: " . json_last_error_msg());
                    }

                    if (!isset($excelData['headers']) || !isset($excelData['data'])) {
                        throw new Exception("Excel data missing headers or data");
                    }

                    $headers = $excelData['headers'];
                    $data = $excelData['data'];
                    
                    // Find email column index - more flexible search
                    $emailIndex = null;
                    $emailVariations = [
                        'email', 'e-mail', 'email address', 'emailaddress',
                        'Email', 'E-mail', 'Email Address', 'EmailAddress',
                        'EMAIL', 'E-MAIL', 'EMAIL ADDRESS', 'EMAILADDRESS',
                        'eMail', 'e-mail', 'eMail Address', 'eMailAddress'
                    ];
                    
                    foreach ($headers as $index => $header) {
                        $headerLower = strtolower(trim($header));
                        $headerUpper = strtoupper(trim($header));
                        $headerOriginal = trim($header);
                        
                        if (in_array($headerLower, array_map('strtolower', $emailVariations)) ||
                            in_array($headerUpper, array_map('strtoupper', $emailVariations)) ||
                            in_array($headerOriginal, $emailVariations)) {
                            $emailIndex = $index;
                            break;
                        }
                    }
                    
                    if ($emailIndex === null) {
                        throw new Exception('No email column found in Excel data. Please ensure your Excel file has a column named "Email" (case insensitive)');
                    }
                    
                    // Insert into scheduled_emails table for each recipient
                    $stmt = $db->prepare("
                        INSERT INTO scheduled_emails (
                            campaign_id, user_id, from_email, to_emails, subject, body,
                            scheduled_time, attachment_path, status, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                    ");
                    
                    $attachmentPath = null;
                    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                        $uploadDir = 'uploads/';
                        if (!file_exists($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        $fileName = uniqid() . '_' . basename($_FILES['attachment']['name']);
                        $attachmentPath = $uploadDir . $fileName;
                        if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $attachmentPath)) {
                            throw new Exception("Failed to upload attachment");
                        }
                    }
                    
                    $recipientCount = 0;
                    foreach ($data as $row) {
                        if (empty($row[$emailIndex])) continue;
                        
                        // Split multiple emails in the cell by comma and clean them
                        $emails = array_map('trim', explode(',', $row[$emailIndex]));
                        $validEmails = [];
                        
                        // Validate each email
                        foreach ($emails as $email) {
                            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                $validEmails[] = $email;
                            }
                        }
                        
                        if (empty($validEmails)) {
                            continue; // Skip if no valid emails found
                        }
                        
                        // Get the original message content
                        $personalizedBody = $_POST['message_content'];
                        $personalizedSubject = $_POST['subject'];
                        
                        // Log personalization attempt
                        error_log("Personalizing scheduled message for email group: " . implode(', ', $validEmails));
                        
                        // Replace placeholders with actual values
                        foreach ($headers as $index => $header) {
                            if (empty($header)) continue;
                            
                            $placeholder = '{{' . $header . '}}';
                            $value = $row[$index] ?? '';
                            
                            // Log each replacement
                            if (strpos($personalizedBody, $placeholder) !== false || strpos($personalizedSubject, $placeholder) !== false) {
                                error_log("Replacing {$placeholder} with {$value}");
                            }
                            
                            $personalizedBody = str_replace($placeholder, $value, $personalizedBody);
                            $personalizedSubject = str_replace($placeholder, $value, $personalizedSubject);
                        }
                        
                        // Verify no placeholders remain
                        $remainingPlaceholders = [];
                        preg_match_all('/{{([^}]+)}}/', $personalizedBody . $personalizedSubject, $remainingPlaceholders);
                        if (!empty($remainingPlaceholders[1])) {
                            error_log("Warning: Unreplaced placeholders found in scheduled email: " . implode(', ', $remainingPlaceholders[1]));
                            }
                        
                        // Join valid emails with comma for database storage
                        $toEmails = implode(',', $validEmails);
                        
                        $stmt->execute([
                            $campaignId,
                            $_SESSION['user_id'],
                            $_POST['from'],
                            $toEmails,
                            $personalizedSubject, // Use personalized subject
                            $personalizedBody,    // Use personalized body
                            $formattedScheduledTime,
                            $attachmentPath
                        ]);
                        
                        $recipientCount++;
                    }
                    
                    if ($recipientCount === 0) {
                        throw new Exception("No valid email addresses found in Excel data");
                    }
                } else {
                    throw new Exception("Excel data is required for scheduling");
                }
                
                // Commit transaction
                $db->commit();
                
                sendJsonResponse(true, 'Campaign scheduled successfully', [
                    'campaign_id' => $campaignId,
                    'redirect' => 'scheduled_campaign.php?campaign_id=' . $campaignId
                ]);
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $db->rollBack();
                error_log("Campaign scheduling error: " . $e->getMessage());
                sendJsonResponse(false, 'Error scheduling campaign: ' . $e->getMessage());
            }
        } catch (Exception $e) {
            error_log("Campaign scheduling error: " . $e->getMessage());
            sendJsonResponse(false, 'Error scheduling campaign: ' . $e->getMessage());
        }
    }

    // If not scheduled, send emails immediately
    if (!$scheduledTime) {
        try {
            // Get SMTP configuration
            $stmt = $db->prepare("SELECT email, smtp, port, password FROM emailsender WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $smtp = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$smtp) {
                sendJsonResponse(false, "SMTP configuration not found. Please configure your email settings first.");
            }

            // Initialize email sender
            $emailSender = new EmailSender($smtp['email'], $smtp['smtp'], $smtp['port'], $smtp['password']);
            $emailSender->setUserId($_SESSION['user_id']);

            // Create campaign record first
            $stmt = $db->prepare("
                INSERT INTO campaigns (
                    user_id, subject, message, message_content, 
                    cc_emails, bcc_emails, status, created_at, success_count, failure_count
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), 0, 0)
            ");
            
            $stmt->execute([
                $_SESSION['user_id'],
                $_POST['subject'],
                $_POST['message'],
                $_POST['message_content'],
                !empty($ccRecipients) ? json_encode($ccRecipients) : null,
                !empty($bccRecipients) ? json_encode($bccRecipients) : null
            ]);
            
            $campaignId = $db->lastInsertId();
            
            if (!$campaignId) {
                throw new Exception("Failed to create campaign record");
            }

            // Process emails in batches
            $batchSize = 10; // Process 10 emails at a time
            $delayBetweenEmails = 2; // 2 seconds delay between emails
            $delayBetweenBatches = 5; // 5 seconds delay between batches
            
            $successCount = 0;
            $errorCount = 0;
            $errors = [];
            
            // Split recipients into batches
            $batches = array_chunk($recipients, $batchSize);
            
            foreach ($batches as $batchIndex => $batch) {
                foreach ($batch as $emailGroup) {
                    try {
                        $message = $_POST['message'];
                        $subject = $_POST['subject'];
                        
                        // Split multiple emails in the group by comma and clean them
                        $emails = array_map('trim', explode(',', $emailGroup));
                        $validEmails = [];
                        
                        // Validate each email
                        foreach ($emails as $email) {
                            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                $validEmails[] = $email;
                            } else {
                                error_log("Invalid email found in group: " . $email);
                            }
                        }
                        
                        if (empty($validEmails)) {
                            error_log("No valid emails found in group: " . $emailGroup);
                            continue;
                        }
                        
                        // If Excel data is available, personalize the message
                        if ($excelData) {
                            // Get row data for this email group
                            $rowData = null;
                            foreach ($excelData['data'] as $row) {
                                // Check if any email in the group matches
                                $rowEmails = array_map('trim', explode(',', $row[$emailIndex]));
                                if (array_intersect($validEmails, $rowEmails)) {
                                    $rowData = $row;
                                    break;
                                }
                            }

                            if ($rowData) {
                                // Log personalization attempt
                                error_log("Personalizing message for email group: " . implode(', ', $validEmails));
                                
                                // Replace placeholders in message and subject
                                foreach ($excelData['headers'] as $index => $header) {
                                    if (empty($header)) continue;
                                    
                                    $placeholder = '{{' . $header . '}}';
                                    $value = $rowData[$index] ?? '';
                                    
                                    // Log each replacement
                                    if (strpos($message, $placeholder) !== false || strpos($subject, $placeholder) !== false) {
                                        error_log("Replacing {$placeholder} with {$value}");
                                    }
                                    
                                    $message = str_replace($placeholder, $value, $message);
                                    $subject = str_replace($placeholder, $value, $subject);
                                }
                                
                                // Verify no placeholders remain
                                $remainingPlaceholders = [];
                                preg_match_all('/{{([^}]+)}}/', $message . $subject, $remainingPlaceholders);
                                if (!empty($remainingPlaceholders[1])) {
                                    error_log("Warning: Unreplaced placeholders found: " . implode(', ', $remainingPlaceholders[1]));
                                }
                            } else {
                                error_log("Warning: No matching row data found for email group: " . implode(', ', $validEmails));
                            }
                        }

                        // Add tracking pixel to the message
                        $trackingPixel = '<img src="http://' . $_SERVER['HTTP_HOST'] . '/bulk/track.php?c=' . $campaignId . '&e=' . urlencode(implode(',', $validEmails)) . '" width="1" height="1" style="display:none" />';
                        $message .= $trackingPixel;

                        // Send single email to all recipients in the group
                        $emailSender->sendEmail($validEmails, $subject, $message, $ccRecipients, $bccRecipients);

                        // Log successful send for the group
                        $stmt = $db->prepare("
                            INSERT INTO email_logs (
                                campaign_id, user_id, to_emails, subject, from_email, 
                                status, cc_emails, bcc_emails, created_at
                            ) VALUES (?, ?, ?, ?, ?, 'sent', ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $campaignId,
                            $_SESSION['user_id'],
                            implode(',', $validEmails),
                            $subject,
                            $smtp['email'],
                            $ccRecipients ? json_encode($ccRecipients) : null,
                            $bccRecipients ? json_encode($bccRecipients) : null
                        ]);

                        $successCount++;
                        
                        // Add delay between emails
                        sleep($delayBetweenEmails);
                        
                    } catch (Exception $e) {
                        $errorCount++;
                        $errors[] = "Error sending to group " . implode(', ', $validEmails) . ": " . $e->getMessage();
                        
                        // Log failed send for the group
                        $stmt = $db->prepare("
                            INSERT INTO email_logs (
                                campaign_id, user_id, to_emails, subject, from_email, 
                                status, error_message, cc_emails, bcc_emails, created_at
                            ) VALUES (?, ?, ?, ?, ?, 'failed', ?, ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $campaignId,
                            $_SESSION['user_id'],
                            implode(',', $validEmails),
                            $subject,
                            $smtp['email'],
                            $e->getMessage(),
                            $ccRecipients ? json_encode($ccRecipients) : null,
                            $bccRecipients ? json_encode($bccRecipients) : null
                        ]);
                    }
                }
                
                // Add delay between batches
                if ($batchIndex < count($batches) - 1) {
                    sleep($delayBetweenBatches);
                }
            }

            // Update campaign status and counts
            $status = ($errorCount === 0) ? 'sent' : 'partially_sent';
            $stmt = $db->prepare("
                UPDATE campaigns 
                SET status = ?, 
                    success_count = ?, 
                    failure_count = ?, 
                    error_log = ? 
                WHERE campaign_id = ?
            ");
            $stmt->execute([
                $status,
                $successCount,
                $errorCount,
                json_encode($errors),
                $campaignId
            ]);

            if ($successCount > 0) {
                sendJsonResponse(true, "Campaign sent successfully", [
                    'campaign_id' => $campaignId,
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                    'errors' => $errors,
                    'redirect' => 'email_results.php?campaign_id=' . $campaignId
                ]);
            } else {
                sendJsonResponse(false, "Failed to send campaign: " . implode(", ", $errors));
            }
        } catch (Exception $e) {
            error_log("Campaign sending error: " . $e->getMessage());
            sendJsonResponse(false, "Error sending campaign: " . $e->getMessage());
        }
    }

    sendJsonResponse(true, "Campaign created successfully", [
        'campaign_id' => $campaignId,
        'redirect' => 'email_results.php?campaign_id=' . $campaignId
    ]);

} catch (Exception $e) {
    error_log("Campaign processing error: " . $e->getMessage());
    sendJsonResponse(false, "Error: " . $e->getMessage());
}
?>