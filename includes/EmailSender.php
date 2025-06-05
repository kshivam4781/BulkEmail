<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailSender {
    private $db;
    private $smtpConfig;
    private $mailer;
    private $fromEmail;
    private $userId;
    private $maxRetries = 3;
    private $retryDelay = 5; // seconds

    public function __construct($fromEmail, $smtpHost, $smtpPort, $smtpPassword) {
        $this->db = (new Database())->connect();
        $this->fromEmail = $fromEmail;
        $this->mailer = new PHPMailer(true);

        // Server settings
        $this->mailer->isSMTP();
        $this->mailer->Host = $smtpHost;
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $fromEmail;
        $this->mailer->Password = $smtpPassword;
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = $smtpPort;

        // Additional settings for Gmail
        $this->mailer->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Set timeout values
        $this->mailer->Timeout = 30;
        $this->mailer->SMTPKeepAlive = true;

        // Default sender
        $this->mailer->setFrom($fromEmail);
    }

    public function setUserId($userId) {
        $this->userId = $userId;
        $this->loadSmtpConfig();
    }

    private function loadSmtpConfig() {
        if (!$this->userId) {
            throw new Exception("User ID not set");
        }

        try {
            $stmt = $this->db->prepare("SELECT * FROM emailsender WHERE id = ?");
            $stmt->execute([$this->userId]);
            $this->smtpConfig = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$this->smtpConfig) {
                throw new Exception("SMTP configuration not found for user");
            }
            
            // Log the SMTP configuration (without password)
            error_log("SMTP Configuration loaded: " . 
                "Host: " . $this->smtpConfig['smtp'] . ", " .
                "Port: " . $this->smtpConfig['port'] . ", " .
                "Email: " . $this->smtpConfig['email']);
        } catch(PDOException $e) {
            throw new Exception("Error loading SMTP configuration: " . $e->getMessage());
        }
    }

    public function sendTestEmail($to) {
        if (!$this->smtpConfig) {
            throw new Exception("SMTP configuration not found");
        }

        try {
            //Recipients
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to);

            //Content
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Test Email';
            $this->mailer->Body    = '
                <html>
                <head>
                    <title>Test Email</title>
                </head>
                <body>
                    <h1>This is a test email</h1>
                    <p>This email was sent using the SMTP configuration from the database.</p>
                </body>
                </html>
            ';

            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            throw new Exception("Message could not be sent. Mailer Error: {$this->mailer->ErrorInfo}");
        }
    }

    private function shouldRetry($error) {
        $retryableErrors = [
            'DATA command failed',
            'Temporary System Problem',
            'MAIL FROM command failed',
            'SMTP Error: data not accepted',
            'Connection timed out',
            'Connection refused',
            'SMTP connect() failed'
        ];

        foreach ($retryableErrors as $retryableError) {
            if (stripos($error, $retryableError) !== false) {
                return true;
            }
        }
        return false;
    }

    public function sendEmail($to, $subject, $body, $ccRecipients = null, $bccRecipients = null, $attachment = null) {
        $attempt = 0;
        $lastError = null;

        while ($attempt < $this->maxRetries) {
            try {
                // Reset recipients for each new email
                $this->mailer->clearAddresses();
                $this->mailer->clearAttachments();

                // Add recipients - handle both single email and array of emails
                if (is_array($to)) {
                    foreach ($to as $email) {
                        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $this->mailer->addAddress($email);
                        } else {
                            error_log("Invalid email address in array: " . $email);
                            throw new Exception("Invalid email address: " . $email);
                        }
                    }
                } else {
                    // Split multiple emails in a single string
                    $emails = array_map('trim', explode(',', $to));
                    foreach ($emails as $email) {
                        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $this->mailer->addAddress($email);
                        } else {
                            error_log("Invalid email address in string: " . $email);
                            throw new Exception("Invalid email address: " . $email);
                        }
                    }
                }

                // Add CC recipients if provided
                if ($ccRecipients && is_array($ccRecipients)) {
                    foreach ($ccRecipients as $ccEmail) {
                        if (filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
                            $this->mailer->addCC($ccEmail);
                        } else {
                            error_log("Invalid CC email address: " . $ccEmail);
                            throw new Exception("Invalid CC email address: " . $ccEmail);
                        }
                    }
                }

                // Add BCC recipients if provided
                if ($bccRecipients && is_array($bccRecipients)) {
                    foreach ($bccRecipients as $bccEmail) {
                        if (filter_var($bccEmail, FILTER_VALIDATE_EMAIL)) {
                            $this->mailer->addBCC($bccEmail);
                        } else {
                            error_log("Invalid BCC email address: " . $bccEmail);
                            throw new Exception("Invalid BCC email address: " . $bccEmail);
                        }
                    }
                }

                // Add attachment if provided
                if ($attachment && $attachment['error'] === UPLOAD_ERR_OK) {
                    try {
                        $this->mailer->addAttachment(
                            $attachment['tmp_name'],
                            $attachment['name']
                        );
                    } catch (Exception $e) {
                        error_log("Failed to add attachment: " . $e->getMessage());
                        throw new Exception("Failed to add attachment: " . $e->getMessage());
                    }
                }

                // Content
                $this->mailer->isHTML(true);
                $this->mailer->Subject = $subject;
                
                // Set both HTML and plain text versions
                $this->mailer->Body = $body;
                $this->mailer->AltBody = strip_tags($body);

                // Set character set to UTF-8
                $this->mailer->CharSet = 'UTF-8';
                
                // Enable debug output
                $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;
                $this->mailer->Debugoutput = function($str, $level) {
                    error_log("SMTP Debug: $str");
                };

                // Send email
                if (!$this->mailer->send()) {
                    throw new Exception("Failed to send email: " . $this->mailer->ErrorInfo);
                }

                // If we get here, the email was sent successfully
                return true;

            } catch (Exception $e) {
                $lastError = $e->getMessage();
                error_log("Email sending attempt " . ($attempt + 1) . " failed: " . $lastError);

                // Check if we should retry
                if ($this->shouldRetry($lastError)) {
                    $attempt++;
                    if ($attempt < $this->maxRetries) {
                        error_log("Retrying in " . $this->retryDelay . " seconds...");
                        sleep($this->retryDelay);
                        // Increase delay for next attempt
                        $this->retryDelay *= 2;
                        continue;
                    }
                }
                
                // If we shouldn't retry or we've exhausted retries, throw the error
                throw new Exception("Email could not be sent after " . $attempt . " attempts. Last error: " . $lastError);
            }
        }

        throw new Exception("Email could not be sent after " . $this->maxRetries . " attempts. Last error: " . $lastError);
    }
} 