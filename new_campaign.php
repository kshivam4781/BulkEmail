<?php
session_start();
// Set unlimited execution time
set_time_limit(0);
ini_set('max_execution_time', 0);

require_once 'config/database.php';

// Clear any existing session messages
unset($_SESSION['message']);
unset($_SESSION['message_type']);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Get user's email
$db = (new Database())->connect();
$stmt = $db->prepare("SELECT email FROM users WHERE userId = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if user exists and has an email
if (!$user || !isset($user['email'])) {
    // Clear session and redirect to login
    session_destroy();
    header('Location: index.php');
    exit();
}

// Get list emails if list ID is provided
$listEmails = '';
if (isset($_GET['list'])) {
    $listId = (int)$_GET['list'];
    $stmt = $db->prepare("
        SELECT Email 
        FROM contacts 
        WHERE ListID = ? 
        ORDER BY Email
    ");
    $stmt->execute([$listId]);
    $emails = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $listEmails = implode(', ', $emails);
}

// Get draft details if draft ID is provided
$draft = null;
$draftOwner = null;
if (isset($_GET['draft'])) {
    $draftId = (int)$_GET['draft'];
    $stmt = $db->prepare("
        SELECT d.*, u.email as owner_email 
        FROM emaildrafts d
        JOIN users u ON d.UserID = u.userId
        WHERE d.DraftID = ?
    ");
    $stmt->execute([$draftId]);
    $draft = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($draft) {
        if ($draft['UserID'] != $_SESSION['user_id']) {
            // Draft belongs to another user
            $_SESSION['message'] = "This draft belongs to " . htmlspecialchars($draft['owner_email']) . ". Please contact them to make changes.";
            $_SESSION['message_type'] = 'warning';
            header('Location: email_drafts.php');
            exit();
        }
    }
}

// Prepare the content for the layout
ob_start();
?>

<div class="container-fluid py-4">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1 text-primary">New Campaign</h4>
                    <p class="text-muted mb-0">Create and send a new email campaign</p>
                </div>
                <a href="history.php" class="btn btn-primary">
                    <i class="fas fa-history me-2"></i>View History
                </a>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
                    <?php 
                    echo $_SESSION['message'];
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Form Section -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <form action="process_campaign.php" method="POST" enctype="multipart/form-data" id="campaignForm">
                        <!-- From and To Section -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="from" class="form-label fw-bold">From</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="fas fa-envelope text-primary"></i>
                                        </span>
                                        <input type="email" class="form-control border-start-0" id="from" name="from" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="to" class="form-label fw-bold">To</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="fas fa-users text-primary"></i>
                                        </span>
                                        <!-- <textarea class="form-control border-start-0" id="to" name="to" rows="2" placeholder="Enter email addresses (one per line)" required><?php echo htmlspecialchars($listEmails); ?></textarea> -->
                                        <div class="form-control border-start-0 bg-light" style="height: auto; min-height: 42px;">
                                            <small class="text-muted">Please upload an Excel file with an "Email" column using the button below</small>
                                        </div>
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#recipientsModal">
                                            <i class="fas fa-plus me-1"></i>Add Recipients
                                        </button>
                                    </div>
                                    <small class="text-muted mt-1 d-block">
                                        <i class="fas fa-info-circle me-1"></i>Upload an Excel file containing email addresses
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- CC Section -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="cc" class="form-label fw-bold">CC</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="fas fa-copy text-primary"></i>
                                        </span>
                                        <textarea class="form-control border-start-0" id="cc" name="cc" rows="2" placeholder="Enter CC email addresses (one per line or comma-separated)"></textarea>
                                    </div>
                                    <small class="text-muted mt-1 d-block">
                                        <i class="fas fa-info-circle me-1"></i>Optional: Add CC recipients (multiple emails can be separated by commas)
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="bcc" class="form-label fw-bold">BCC</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="fas fa-user-secret text-primary"></i>
                                        </span>
                                        <textarea class="form-control border-start-0" id="bcc" name="bcc" rows="2" placeholder="Enter BCC email addresses (one per line or comma-separated)"></textarea>
                                    </div>
                                    <small class="text-muted mt-1 d-block">
                                        <i class="fas fa-info-circle me-1"></i>Optional: Add BCC recipients (multiple emails can be separated by commas)
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- Subject Section -->
                        <div class="form-group mb-4">
                            <label for="subject" class="form-label fw-bold">Subject</label>
                            <div class="input-group">   
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-heading text-primary"></i>
                                </span>
                                <input type="text" class="form-control border-start-0" id="subject" name="subject" 
                                    placeholder="Enter email subject" required
                                    value="<?php echo $draft ? htmlspecialchars($draft['Subject']) : ''; ?>">
                                <button type="button" class="btn btn-outline-primary" onclick="previewSubject()">
                                    <i class="fas fa-eye me-2"></i>Preview
                                </button>
                            </div>
                            <small class="text-muted mt-1 d-block">
                                <i class="fas fa-info-circle me-1"></i>You can use personalization fields from your Excel file in the subject line
                            </small>
                        </div>

                        <!-- Message Body Section with Personalization -->
                        <div class="row mb-4">
                            <div class="col-md-12" id="normalMessageSection">
                                <div class="form-group">
                                    <label for="message" class="form-label fw-bold">Message Body</label>
                                    <div class="d-flex justify-content-end gap-2 mb-2">
                                        <button type="button" class="btn btn-primary" onclick="previewMessage()">
                                            <i class="fas fa-eye me-2"></i>Preview Mail
                                        </button>
                                        <button type="button" class="btn btn-secondary" onclick="saveDraft()">
                                            <i class="fas fa-save me-2"></i>Save as Draft
                                        </button>
                                    </div>
                                    <textarea id="message" name="message" class="form-control"><?php echo $draft ? htmlspecialchars($draft['Body']) : ''; ?></textarea>
                                    <input type="hidden" id="message_content" name="message_content">
                                </div>
                            </div>
                            <div class="col-md-9 d-none" id="personalizedMessageSection">
                                <div class="form-group">
                                    <label for="message" class="form-label fw-bold">Message Body</label>
                                    <div class="alert alert-info mb-2">
                                        <i class="fas fa-info-circle me-2"></i>Drag and drop personalization fields from the right panel into your message
                                    </div>
                                    <div class="d-flex justify-content-end gap-2 mb-2">
                                        <button type="button" class="btn btn-primary" onclick="previewMessage()">
                                            <i class="fas fa-eye me-2"></i>Preview Mail
                                        </button>
                                        <button type="button" class="btn btn-secondary" onclick="saveDraft()">
                                            <i class="fas fa-save me-2"></i>Save as Draft
                                        </button>
                                    </div>
                                    <textarea id="personalized_message" name="message" class="form-control"></textarea>
                                    <input type="hidden" id="message_content" name="message_content">
                                </div>
                            </div>
                            <div class="col-md-3 d-none" id="personalizationPanel">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0">
                                            <i class="fas fa-table me-2"></i>Personalization Fields
                                        </h6>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="list-group list-group-flush" id="personalizationFields">
                                            <!-- Fields will be populated here -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Attachment Section -->
                        <div class="form-group mb-4">
                            <label for="attachment" class="form-label fw-bold">Attachment</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-paperclip text-primary"></i>
                                </span>
                                <input type="file" class="form-control border-start-0" id="attachment" name="attachment">
                            </div>
                            <small class="text-muted mt-1 d-block">
                                <i class="fas fa-info-circle me-1"></i>Optional: Add an attachment to your email
                            </small>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary" id="sendNowButton">
                                <i class="fas fa-paper-plane me-2"></i>Send Now
                            </button>
                            <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                                <i class="fas fa-clock me-2"></i>Schedule for Later
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom bg-light">
                <h5 class="modal-title text-primary">
                    <i class="fas fa-clock me-2"></i>Schedule Campaign
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="scheduleForm">
                    <div class="mb-3">
                        <label for="scheduleDate" class="form-label fw-bold">Date</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-calendar text-primary"></i>
                            </span>
                            <input type="date" class="form-control border-start-0" id="scheduleDate" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="scheduleTime" class="form-label fw-bold">Time</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-clock text-primary"></i>
                            </span>
                            <input type="time" class="form-control border-start-0" id="scheduleTime" required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top bg-light">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="scheduleButton">
                    <i class="fas fa-check me-2"></i>Schedule
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Recipients Modal -->
<div class="modal fade" id="recipientsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom bg-light">
                <h5 class="modal-title text-primary">
                    <i class="fas fa-users me-2"></i>Add Recipients
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <ul class="nav nav-tabs mb-3" id="recipientsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active bg-primary text-white" id="excel-tab" data-bs-toggle="tab" data-bs-target="#excel" type="button" role="tab">
                            <i class="fas fa-file-excel me-1"></i>Excel Upload
                        </button>
                    </li>
                </ul>
                <div class="tab-content" id="recipientsTabContent">
                    <div class="tab-pane fade show active" id="excel" role="tabpanel">
                        <div class="mb-3">
                            <label for="excelFile" class="form-label fw-bold">Upload Excel File</label>
                            <input type="file" class="form-control" id="excelFile" accept=".xlsx, .xls">
                            <small class="text-muted">Upload an Excel file with email addresses in the first column</small>
                        </div>
                        <button type="button" class="btn btn-primary" onclick="processExcelFile()">
                            <i class="fas fa-upload me-1"></i>Upload and Process
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Excel Preview Modal -->
<div class="modal fade" id="excelPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom bg-light">
                <h5 class="modal-title text-primary">
                    <i class="fas fa-table me-2"></i>Select Header Row
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info mb-3">
                    <i class="fas fa-info-circle me-2"></i>Select the row that contains your column headers
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="excelPreviewTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">Select</th>
                                <th>Row Preview</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Excel data will be populated here -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-top bg-light">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmHeaderBtn" disabled>
                    <i class="fas fa-check me-2"></i>Confirm Header
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom bg-light">
                <h5 class="modal-title text-primary">
                    <i class="fas fa-eye me-2"></i>Email Preview
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <strong>Subject:</strong>
                    <div id="previewSubject" class="border-bottom pb-2 mb-3"></div>
                    <strong>Message:</strong>
                    <div id="previewMessage" class="border p-3 bg-light"></div>
                </div>
            </div>
            <div class="modal-footer border-top bg-light">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom bg-light">
                <h5 class="modal-title text-primary">
                    <i class="fas fa-check-circle me-2"></i>Success
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <i class="fas fa-check-circle text-success" style="font-size: 48px;"></i>
                <h5 class="mt-3" id="successMessage"></h5>
                <p class="text-muted">The draft has been saved to your account.</p>
            </div>
            <div class="modal-footer border-top bg-light">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- Subject Preview Modal -->
<div class="modal fade" id="subjectPreviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom bg-light">
                <h5 class="modal-title text-primary">
                    <i class="fas fa-eye me-2"></i>Subject Preview
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <strong>Preview:</strong>
                    <div id="previewSubjectContent" class="border p-3 bg-light mt-2"></div>
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>This is how the subject will appear for the first recipient in your list
                </div>
            </div>
            <div class="modal-footer border-top bg-light">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Error Modal -->
<div class="modal fade" id="errorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom bg-light">
                <h5 class="modal-title text-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>Error
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <i class="fas fa-exclamation-circle text-danger" style="font-size: 48px;"></i>
                <h5 class="mt-3" id="errorMessage"></h5>
            </div>
            <div class="modal-footer border-top bg-light">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- TinyMCE -->
<script src="https://cdn.tiny.cloud/1/ty9olnipt398xv19du58z8ofchfkuctsjo8mwcwqry4jmwfy/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Store the current content globally
    let currentContent = '';

    // Clear both message fields
    if (tinymce.get('message')) {
        tinymce.get('message').setContent('');
    }
    if (tinymce.get('personalized_message')) {
        tinymce.get('personalized_message').setContent('');
    }

    // Initialize TinyMCE
    tinymce.init({
        selector: '#message',
        height: 400,
        menubar: true,
        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
            'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
            'insertdatetime', 'media', 'table', 'help', 'wordcount', 'emoticons',
            'codesample', 'visualchars', 'quickbars', 'emoticons'
        ],
        toolbar: 'undo redo | blocks | ' +
            'bold italic backcolor | alignleft aligncenter ' +
            'alignright alignjustify | bullist numlist outdent indent | ' +
            'removeformat | help | image media table emoticons | ' +
            'forecolor backcolor | fontfamily fontsize | ' +
            'link anchor codesample | charmap | preview fullscreen',
        content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-size: 14px; }',
        setup: function(editor) {
            editor.on('init', function() {
                <?php if ($draft): ?>
                currentContent = <?php echo json_encode($draft['Body']); ?>;
                editor.setContent(currentContent);
                <?php endif; ?>
            });
        }
    });

    // Initialize TinyMCE for personalized message
    tinymce.init({
        selector: '#personalized_message',
        height: 400,
        menubar: true,
        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
            'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
            'insertdatetime', 'media', 'table', 'help', 'wordcount', 'emoticons',
            'codesample', 'visualchars', 'quickbars', 'emoticons'
        ],
        toolbar: 'undo redo | blocks | ' +
            'bold italic backcolor | alignleft aligncenter ' +
            'alignright alignjustify | bullist numlist outdent indent | ' +
            'removeformat | help | image media table emoticons | ' +
            'forecolor backcolor | fontfamily fontsize | ' +
            'link anchor codesample | charmap | preview fullscreen',
        content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-size: 14px; }',
        setup: function(editor) {
            editor.on('init', function() {
                // Set the content from the draft or previous editor
                if (currentContent) {
                    editor.setContent(currentContent);
                }
            });
            
            editor.on('drop', function(e) {
                e.preventDefault();
                const data = e.dataTransfer.getData('text/plain');
                if (data.startsWith('{{') && data.endsWith('}}')) {
                    editor.insertContent(data);
                }
            });
            
            editor.on('dragover', function(e) {
                e.preventDefault();
            });
        }
    });

    // Set default date and time for scheduling
    setDefaultScheduleDateTime();

    // Handle Send Now button click
    document.getElementById('sendNowButton').addEventListener('click', function() {
        // Prevent multiple submissions
        const sendButton = this;
        if (sendButton.disabled) return;
        
        // Disable button and show loading state
        sendButton.disabled = true;
        const originalText = sendButton.innerHTML;
        sendButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
        
        const form = document.getElementById('campaignForm');
        const formData = new FormData(form);
        
        // Get message content from TinyMCE
        let messageContent;
        if (excelData && selectedHeaderRow !== null) {
            messageContent = tinymce.get('personalized_message') ? 
                tinymce.get('personalized_message').getContent() : '';
        } else {
            messageContent = tinymce.get('message') ? 
                tinymce.get('message').getContent() : '';
        }
        
        // Validate message content
        if (!messageContent.trim()) {
            showError('Please enter a message body');
            sendButton.disabled = false;
            sendButton.innerHTML = originalText;
            return;
        }
        
        // Add message content to form data
        formData.append('message_content', messageContent);
        formData.append('message', messageContent);
        
        // Add Excel data if available
        if (excelData && selectedHeaderRow !== null) {
            const excelDataJson = JSON.stringify({
                headers: excelData[selectedHeaderRow],
                data: excelData.slice(selectedHeaderRow + 1)
            });
            formData.append('excel_data', excelDataJson);
        } else {
            showError('Please upload an Excel file with email addresses first');
            sendButton.disabled = false;
            sendButton.innerHTML = originalText;
            return;
        }
        
        // Add CC emails if available
        const ccEmails = document.getElementById('cc').value.trim();
        if (ccEmails) {
            const ccList = ccEmails.split(',')
                .map(email => email.trim())
                .filter(email => email.length > 0 && email.includes('@'));
            formData.append('cc_emails', JSON.stringify(ccList));
        }
        
        // Get personalized subject
        let subject = document.getElementById('subject').value;
        if (!subject.trim()) {
            showError('Please enter an email subject');
            sendButton.disabled = false;
            sendButton.innerHTML = originalText;
            return;
        }
        formData.append('subject', subject);
        
        // Submit the form
        fetch('process_campaign.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Show success message
                document.getElementById('successMessage').textContent = data.message;
                const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                successModal.show();
                
                // Redirect after a short delay
                setTimeout(() => {
                    if (data.data && data.data.redirect) {
                        window.location.href = data.data.redirect;
            } else {
                        window.location.href = 'email_results.php?campaign_id=' + data.data.campaign_id;
                    }
                }, 2000);
            } else {
                // Show error message with details
                let errorMessage = data.message;
                if (data.data && data.data.errors && data.data.errors.length > 0) {
                    errorMessage += '\n\nDetails:\n' + data.data.errors.join('\n');
                }
                showError(errorMessage);
                sendButton.disabled = false;
                sendButton.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Error sending campaign: ' + error.message);
            sendButton.disabled = false;
            sendButton.innerHTML = originalText;
        });
    });

    // Handle schedule button click
    document.getElementById('scheduleButton').addEventListener('click', function() {
        // Prevent multiple submissions
        const scheduleButton = this;
        if (scheduleButton.disabled) return;
        
        // Disable button and show loading state
        scheduleButton.disabled = true;
        const originalText = scheduleButton.innerHTML;
        scheduleButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Scheduling...';
        
        const date = document.getElementById('scheduleDate').value;
        const time = document.getElementById('scheduleTime').value;
        
        if (!date || !time) {
            showError('Please select both date and time');
            scheduleButton.disabled = false;
            scheduleButton.innerHTML = originalText;
            return;
        }

        // Create scheduled datetime object
        const scheduledDateTime = new Date(date + 'T' + time);
        const now = new Date();
        
        // Validate scheduled time is in the future
        if (scheduledDateTime <= now) {
            showError('Please select a future date and time');
            scheduleButton.disabled = false;
            scheduleButton.innerHTML = originalText;
            return;
        }

        // Get form data
        const form = document.getElementById('campaignForm');
        const formData = new FormData(form);
        
        // Add scheduled date/time to form data
        formData.append('scheduled_for', scheduledDateTime.toISOString());
        
        // Get message content from TinyMCE
        let messageContent;
        if (excelData && selectedHeaderRow !== null) {
            messageContent = tinymce.get('personalized_message') ? 
                tinymce.get('personalized_message').getContent() : '';
        } else {
            messageContent = tinymce.get('message') ? 
                tinymce.get('message').getContent() : '';
        }
        
        // Validate message content
        if (!messageContent.trim()) {
            showError('Please enter a message body');
            scheduleButton.disabled = false;
            scheduleButton.innerHTML = originalText;
            return;
        }
        
        // Add message content to form data
        formData.append('message_content', messageContent);
        formData.append('message', messageContent);
        
        // Add Excel data if available
        if (excelData && selectedHeaderRow !== null) {
            const excelDataJson = JSON.stringify({
                headers: excelData[selectedHeaderRow],
                data: excelData.slice(selectedHeaderRow + 1)
            });
            formData.append('excel_data', excelDataJson);
        } else {
            showError('Please upload an Excel file with email addresses first');
            scheduleButton.disabled = false;
            scheduleButton.innerHTML = originalText;
            return;
        }
        
        // Add CC emails if available
        const ccEmails = document.getElementById('cc').value.trim();
        if (ccEmails) {
            const ccList = ccEmails.split(',')
                .map(email => email.trim())
                .filter(email => email.length > 0 && email.includes('@'));
            formData.append('cc_emails', JSON.stringify(ccList));
        }
        
        // Get personalized subject
        let subject = document.getElementById('subject').value;
        if (!subject.trim()) {
            showError('Please enter an email subject');
            scheduleButton.disabled = false;
            scheduleButton.innerHTML = originalText;
            return;
        }
        formData.append('subject', subject);
        
        // Submit the form
        fetch('process_campaign.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Show success message
                document.getElementById('successMessage').textContent = data.message;
                const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                successModal.show();
                
                // Redirect after a short delay
                setTimeout(() => {
                    if (data.data && data.data.redirect) {
                        window.location.href = data.data.redirect;
                    } else {
                        window.location.href = 'scheduled_campaign.php?campaign_id=' + data.data.campaign_id;
                    }
                }, 2000);
            } else {
                showError(data.message || 'Error scheduling campaign');
                scheduleButton.disabled = false;
                scheduleButton.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Error scheduling campaign: ' + error.message);
            scheduleButton.disabled = false;
            scheduleButton.innerHTML = originalText;
        });
    });

    // Check if we should continue the tutorial
    if (sessionStorage.getItem('continueTutorial') === 'true') {
        // Clear the flag
        sessionStorage.removeItem('continueTutorial');
        
        // Start the tutorial
        introJs().setOptions({
            showStepNumbers: true,
            exitOnOverlayClick: false,
            showBullets: true,
            skipLabel: 'Skip',
            doneLabel: 'Got it!',
            nextLabel: 'Next →',
            prevLabel: '← Back',
            tooltipClass: 'customTooltip',
            steps: [
                {
                    title: 'Welcome to Campaign Creation!',
                    intro: "Let's create your first email campaign. I'll guide you through each step of the process.",
                    position: 'center'
                },
                {
                    title: 'From Address',
                    element: document.querySelector('#from'),
                    intro: "This is your sender email address. It's automatically set to your registered email.",
                    position: 'bottom'
                },
                {
                    title: 'Add Recipients',
                    element: document.querySelector('[data-bs-target="#recipientsModal"]'),
                    intro: "Click here to upload your Excel file containing email addresses. Make sure your file has an 'Email' column.",
                    position: 'bottom'
                },
                {
                    title: 'CC Recipients',
                    element: document.querySelector('#cc'),
                    intro: "Optionally add CC recipients who should receive a copy of this email.",
                    position: 'bottom'
                },
                {
                    title: 'Email Subject',
                    element: document.querySelector('#subject'),
                    intro: "Create an engaging subject line. You can use personalization fields from your Excel file like {{Name}}.",
                    position: 'bottom'
                },
                {
                    title: 'Message Editor',
                    element: document.querySelector('#message'),
                    intro: "Write your email content here. Use the rich text editor to format your message and make it visually appealing.",
                    position: 'bottom'
                },
                {
                    title: 'Personalization Fields',
                    element: document.querySelector('#personalizationPanel'),
                    intro: "After uploading your Excel file, you can drag and drop these fields into your message for personalization.",
                    position: 'left'
                },
                {
                    title: 'Attachments',
                    element: document.querySelector('#attachment'),
                    intro: "Optionally attach files to your email. Supported formats include PDF, images, and documents.",
                    position: 'bottom'
                },
                {
                    title: 'Send Options',
                    element: document.querySelector('#sendNowButton'),
                    intro: "Choose to send your email immediately or schedule it for later. You can also save it as a draft to work on it later.",
                    position: 'top'
                },
                {
                    title: 'Preview',
                    element: document.querySelector('[onclick="previewMessage()"]'),
                    intro: "Before sending, preview how your email will look to recipients. This helps ensure everything is formatted correctly.",
                    position: 'bottom'
                },
                {
                    title: 'Ready to Send?',
                    element: document.querySelector('#sendNowButton'),
                    intro: "Click the 'Send Now' button to deliver your email campaign to all recipients.",
                    position: 'top',
                    buttons: [] // Remove all buttons for this step
                }
            ]
        }).start();
    }
});

// Function to add CC emails to the main recipients list
function addCCEmails() {
    const ccEmails = document.getElementById('ccEmails').value;
    const toTextarea = document.getElementById('to');
    
    if (ccEmails.trim()) {
        // Split by commas and clean up each email
        const emails = ccEmails.split(',')
            .map(email => email.trim())
            .filter(email => email.length > 0);
        
        // Add to existing recipients
        const currentEmails = toTextarea.value.split('\n').filter(email => email.trim().length > 0);
        const allEmails = [...new Set([...currentEmails, ...emails])]; // Remove duplicates
        
        toTextarea.value = allEmails.join('\n');
        document.getElementById('ccEmails').value = ''; // Clear CC input
        
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('recipientsModal'));
        modal.hide();
    }
}

// Store Excel data globally
let excelData = [];

// Handle Excel file upload
function processExcelFile() {
    const fileInput = document.getElementById('excelFile');
    const file = fileInput.files[0];
    
    if (!file) {
        showError('Please select an Excel file');
        return;
    }
    
    const reader = new FileReader();
    reader.onload = function(e) {
        const data = new Uint8Array(e.target.result);
        const workbook = XLSX.read(data, { 
            type: 'array',
            cellDates: true,  // Preserve date formats
            cellNF: true,     // Preserve number formats
            cellText: true    // Preserve text formats
        });
        const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
        
        // Convert to array of arrays with raw values and preserve formats
        excelData = XLSX.utils.sheet_to_json(firstSheet, { 
            header: 1, 
            raw: false,  // Don't convert to raw values
            defval: ''   // Use empty string as default value
        });
        
        // Update the To field with a message
        const toField = document.getElementById('to');
        if (toField) {
            toField.value = '';
            toField.placeholder = 'Email addresses will be taken from Excel file';
        }
        
        // Show preview modal
        showExcelPreview(excelData);
    };
    reader.readAsArrayBuffer(file);
}

// Function to show Excel preview
function showExcelPreview(data) {
    const tableBody = document.querySelector('#excelPreviewTable tbody');
    tableBody.innerHTML = '';
    
    // Show first 10 rows for preview
    const previewRows = data.slice(0, 10);
    
    previewRows.forEach((row, index) => {
        const tr = document.createElement('tr');
        
        // Add checkbox cell
        const tdCheckbox = document.createElement('td');
        const checkbox = document.createElement('input');
        checkbox.type = 'radio';
        checkbox.name = 'headerRow';
        checkbox.className = 'form-check-input';
        checkbox.value = index;
        checkbox.onchange = function() {
            document.getElementById('confirmHeaderBtn').disabled = false;
            // Remove highlight from all rows
            document.querySelectorAll('#excelPreviewTable tbody tr').forEach(tr => {
                tr.classList.remove('table-primary');
            });
            // Add highlight to selected row
            tr.classList.add('table-primary');
        };
        tdCheckbox.appendChild(checkbox);
        tr.appendChild(tdCheckbox);
        
        // Add row preview cell
        const tdPreview = document.createElement('td');
        tdPreview.textContent = row.join(' | ');
        tr.appendChild(tdPreview);
        
        tableBody.appendChild(tr);
    });
    
    // Show the modal
    const previewModal = new bootstrap.Modal(document.getElementById('excelPreviewModal'));
    previewModal.show();
}

// Handle header confirmation
document.getElementById('confirmHeaderBtn').addEventListener('click', function() {
    const selectedRow = document.querySelector('input[name="headerRow"]:checked');
    if (selectedRow) {
        selectedHeaderRow = parseInt(selectedRow.value);
        const headers = excelData[selectedHeaderRow];
        
        // Verify email column exists
        const hasEmailColumn = headers.some(header => 
            header && header.toLowerCase() === 'email'
        );
        
        if (!hasEmailColumn) {
            showError('Excel file must contain an "Email" column');
            return;
        }
        
        // Show personalization sections
        document.getElementById('normalMessageSection').classList.add('d-none');
        document.getElementById('personalizedMessageSection').classList.remove('d-none');
        document.getElementById('personalizationPanel').classList.remove('d-none');
        
        // Store the current message content before switching editors
        currentContent = tinymce.get('message') ? tinymce.get('message').getContent() : '';
        
        // Populate personalization fields
        const fieldsContainer = document.getElementById('personalizationFields');
        fieldsContainer.innerHTML = '';
        
        headers.forEach((header, index) => {
            if (header) { // Only add non-empty headers
                const field = document.createElement('div');
                field.className = 'list-group-item list-group-item-action d-flex align-items-center';
                field.draggable = true;
                field.innerHTML = `
                    <i class="fas fa-grip-vertical me-2 text-muted"></i>
                    <span>${header}</span>
                `;
                
                // Add drag event listeners
                field.addEventListener('dragstart', function(e) {
                    e.dataTransfer.setData('text/plain', `{{${header}}}`);
                });
                
                fieldsContainer.appendChild(field);
            }
        });
        
        // Initialize TinyMCE for personalized message
        tinymce.remove('#personalized_message'); // Remove existing instance if any
        tinymce.init({
            selector: '#personalized_message',
            height: 400,
            menubar: true,
            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                'insertdatetime', 'media', 'table', 'help', 'wordcount', 'emoticons',
                'codesample', 'visualchars', 'quickbars', 'emoticons'
            ],
            toolbar: 'undo redo | blocks | ' +
                'bold italic backcolor | alignleft aligncenter ' +
                'alignright alignjustify | bullist numlist outdent indent | ' +
                'removeformat | help | image media table emoticons | ' +
                'forecolor backcolor | fontfamily fontsize | ' +
                'link anchor codesample | charmap | preview fullscreen',
            content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-size: 14px; }',
            setup: function(editor) {
                editor.on('init', function() {
                    // Set the content from the draft or previous editor
                    if (currentContent) {
                        editor.setContent(currentContent);
                    }
                });
                
                editor.on('drop', function(e) {
                    e.preventDefault();
                    const data = e.dataTransfer.getData('text/plain');
                    if (data.startsWith('{{') && data.endsWith('}}')) {
                        editor.insertContent(data);
                    }
                });
                
                editor.on('dragover', function(e) {
                    e.preventDefault();
                });
            }
        });
        
        // Close the preview modal
        const previewModal = bootstrap.Modal.getInstance(document.getElementById('excelPreviewModal'));
        if (previewModal) {
        previewModal.hide();
        }
        
        // Close the recipients modal
        const recipientsModal = bootstrap.Modal.getInstance(document.getElementById('recipientsModal'));
        if (recipientsModal) {
        recipientsModal.hide();
        }
        
        // Show success message
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-success alert-dismissible fade show mt-3';
        alertDiv.innerHTML = `
            <i class="fas fa-check-circle me-2"></i>
            Header row selected successfully! You can now drag and drop personalization fields into your message.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Find the correct parent element to insert the alert
        const formGroup = document.querySelector('.form-group');
        if (formGroup && formGroup.parentNode) {
            formGroup.parentNode.insertBefore(alertDiv, formGroup);
        }
    }
});

// Store selected header row
let selectedHeaderRow = null;

// Initialize TinyMCE for normal message
tinymce.init({
    selector: '#message',
    height: 400,
    menubar: true,
    plugins: [
        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
        'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
        'insertdatetime', 'media', 'table', 'help', 'wordcount', 'emoticons',
        'codesample', 'visualchars', 'quickbars', 'emoticons'
    ],
    toolbar: 'undo redo | blocks | ' +
        'bold italic backcolor | alignleft aligncenter ' +
        'alignright alignjustify | bullist numlist outdent indent | ' +
        'removeformat | help | image media table emoticons | ' +
        'forecolor backcolor | fontfamily fontsize | ' +
        'link anchor codesample | charmap | preview fullscreen',
    content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-size: 14px; }'
});

// Add XLSX library
const script = document.createElement('script');
script.src = 'https://cdn.sheetjs.com/xlsx-0.19.3/package/dist/xlsx.full.min.js';
document.head.appendChild(script);

function previewMessage() {
    const subject = document.getElementById('subject').value;
    let messageContent;
    
    if (excelData && selectedHeaderRow !== null) {
        messageContent = tinymce.get('personalized_message') ? 
            tinymce.get('personalized_message').getContent() : '';
            
        // Create a temporary div to parse the HTML content
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = messageContent;
        
        // Find all text nodes that might contain personalization fields
        const walker = document.createTreeWalker(
            tempDiv,
            NodeFilter.SHOW_TEXT,
            null,
            false
        );
        
        const textNodes = [];
        let node;
        while (node = walker.nextNode()) {
            textNodes.push(node);
        }
        
        // Process each text node to highlight personalization fields
        textNodes.forEach(textNode => {
            const text = textNode.nodeValue;
            const regex = /{{([^}]+)}}/g;
            let match;
            let lastIndex = 0;
            let newHTML = '';
            
            while ((match = regex.exec(text)) !== null) {
                // Add text before the match
                newHTML += text.substring(lastIndex, match.index);
                
                // Create highlighted span for the personalization field
                const fieldName = match[1].trim();
                const span = document.createElement('span');
                span.className = 'personalization-field';
                span.style.backgroundColor = '#fff3cd';
                span.style.border = '1px solid #ffeeba';
                span.style.borderRadius = '3px';
                span.style.padding = '0 3px';
                span.style.cursor = 'help';
                span.title = `This will be replaced with the "${fieldName}" field from your Excel file`;
                span.textContent = match[0];
                
                // Add the span to the new HTML
                newHTML += span.outerHTML;
                
                lastIndex = regex.lastIndex;
            }
            
            // Add remaining text
            newHTML += text.substring(lastIndex);
            
            // Replace the text node with the new HTML
            const tempDiv2 = document.createElement('div');
            tempDiv2.innerHTML = newHTML;
            while (tempDiv2.firstChild) {
                textNode.parentNode.insertBefore(tempDiv2.firstChild, textNode);
            }
            textNode.parentNode.removeChild(textNode);
        });
        
        messageContent = tempDiv.innerHTML;
    } else {
        messageContent = tinymce.get('message') ? 
            tinymce.get('message').getContent() : '';
    }
    
    document.getElementById('previewSubject').textContent = subject;
    document.getElementById('previewMessage').innerHTML = messageContent;
    
    // Add CSS for tooltips
    const style = document.createElement('style');
    style.textContent = `
        .personalization-field {
            position: relative;
            display: inline-block;
            background-color: #fff3cd !important;
            border: 1px solid #ffeeba !important;
            border-radius: 3px !important;
            padding: 0 3px !important;
            cursor: help !important;
        }
        .personalization-field:hover::after {
            content: attr(title);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            padding: 5px 10px;
            background-color: #333;
            color: white;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 1000;
            margin-bottom: 5px;
        }
        .personalization-field:hover::before {
            content: '';
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            border-width: 5px;
            border-style: solid;
            border-color: #333 transparent transparent transparent;
            z-index: 1000;
        }
    `;
    document.head.appendChild(style);
    
    const previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
    previewModal.show();
}

function saveDraft() {
    const subject = document.getElementById('subject').value.trim();
    let messageContent;
    
    if (excelData && selectedHeaderRow !== null) {
        messageContent = tinymce.get('personalized_message') ? 
            tinymce.get('personalized_message').getContent() : '';
    } else {
        messageContent = tinymce.get('message') ? 
            tinymce.get('message').getContent() : '';
    }
    
    if (!subject || !messageContent.trim()) {
        showError('Please enter both subject and message content');
        return;
    }
    
    // Create form data
    const formData = new FormData();
    formData.append('subject', subject);
    formData.append('body', messageContent);
    <?php if (isset($_GET['draft'])): ?>
    formData.append('draftId', <?php echo $_GET['draft']; ?>);
    <?php endif; ?>
    
    // Send request
    fetch('save_draft.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message in modal
            document.getElementById('successMessage').textContent = data.message;
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();
            
            if (data.draftId) {
                // Update URL with draft ID if it's a new draft
                const url = new URL(window.location.href);
                url.searchParams.set('draft', data.draftId);
                window.history.pushState({}, '', url);
            }
        } else {
            // Show error message in modal
            document.getElementById('successMessage').textContent = data.message;
            document.getElementById('successModal').querySelector('.modal-title').textContent = 'Error';
            document.getElementById('successModal').querySelector('.fas').className = 'fas fa-exclamation-circle text-danger';
            document.getElementById('successModal').querySelector('.text-muted').textContent = 'Please try again.';
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Show error message in modal
        document.getElementById('successMessage').textContent = 'Error saving draft';
        document.getElementById('successModal').querySelector('.modal-title').textContent = 'Error';
        document.getElementById('successModal').querySelector('.fas').className = 'fas fa-exclamation-circle text-danger';
        document.getElementById('successModal').querySelector('.text-muted').textContent = 'Please try again.';
        const successModal = new bootstrap.Modal(document.getElementById('successModal'));
        successModal.show();
    });
}

// Set default date and time for scheduling
function setDefaultScheduleDateTime() {
    const now = new Date();
    const tomorrow = new Date(now);
    tomorrow.setDate(tomorrow.getDate() + 1);
    
    // Format date as YYYY-MM-DD
    const dateStr = tomorrow.toISOString().split('T')[0];
    // Set default time to 9:00 AM
    const timeStr = '09:00';
    
    document.getElementById('scheduleDate').value = dateStr;
    document.getElementById('scheduleTime').value = timeStr;
    
    // Set min date to today instead of tomorrow
    const today = new Date();
    const todayStr = today.toISOString().split('T')[0];
    document.getElementById('scheduleDate').min = todayStr;
    
    // Function to update time restrictions
    function updateTimeRestrictions() {
        const selectedDate = new Date(document.getElementById('scheduleDate').value);
        const today = new Date();
        
        // If selected date is today, set minimum time to current time
        if (selectedDate.toDateString() === today.toDateString()) {
            const hours = today.getHours().toString().padStart(2, '0');
            const minutes = today.getMinutes().toString().padStart(2, '0');
            document.getElementById('scheduleTime').min = `${hours}:${minutes}`;
            
            // If current time is selected, ensure it's not in the past
            const currentTime = `${hours}:${minutes}`;
            if (document.getElementById('scheduleTime').value < currentTime) {
                document.getElementById('scheduleTime').value = currentTime;
            }
        } else {
            // For future dates, allow any time
            document.getElementById('scheduleTime').min = '00:00';
        }
    }
    
    // Add event listener to update time restrictions when date changes
    document.getElementById('scheduleDate').addEventListener('change', updateTimeRestrictions);
    
    // Add event listener to update time restrictions when modal opens
    document.getElementById('scheduleModal').addEventListener('show.bs.modal', function() {
        // Reset to default values
        document.getElementById('scheduleDate').value = dateStr;
        document.getElementById('scheduleTime').value = timeStr;
        // Update time restrictions
        updateTimeRestrictions();
    });
}

// Function to preview subject with personalization
function previewSubject() {
    const subject = document.getElementById('subject').value;
    let previewContent = subject;
    
    if (excelData && selectedHeaderRow !== null && excelData['data'].length > 0) {
        // Get the first row of data for preview
        const firstRow = excelData['data'][0];
        
        // Replace placeholders with actual values
        excelData['headers'].forEach((header, index) => {
            const placeholder = '{{' + header + '}}';
            const value = firstRow[index] || '';
            previewContent = previewContent.replace(new RegExp(placeholder, 'g'), value);
        });
    }
    
    // Highlight any remaining placeholders
    const regex = /{{([^}]+)}}/g;
    previewContent = previewContent.replace(regex, '<span class="personalization-field" title="This will be replaced with the actual value from your Excel file">$&</span>');
    
    document.getElementById('previewSubjectContent').innerHTML = previewContent;
    
    // Show the modal
    const previewModal = new bootstrap.Modal(document.getElementById('subjectPreviewModal'));
    previewModal.show();
}

// Function to show error message in modal
function showError(message) {
    const errorMessage = document.getElementById('errorMessage');
    if (errorMessage) {
        // Replace newlines with <br> tags for HTML display
        errorMessage.innerHTML = message.replace(/\n/g, '<br>');
    }
    
    const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
    errorModal.show();
    
    // Fix ARIA accessibility issue
    const errorModalElement = document.getElementById('errorModal');
    if (errorModalElement) {
        errorModalElement.removeAttribute('aria-hidden');
        errorModalElement.setAttribute('aria-modal', 'true');
    }
}
</script>

<style>
.nav-tabs .nav-link {
    border: none;
    border-radius: 0.375rem 0.375rem 0 0;
    margin-right: 0.25rem;
    transition: all 0.2s ease-in-out;
}

.nav-tabs .nav-link:hover {
    opacity: 0.9;
}

.nav-tabs .nav-link.active {
    background-color: var(--bs-primary) !important;
    color: white !important;
}

.nav-tabs .nav-link:not(.active) {
    background-color: var(--bs-light) !important;
    color: var(--bs-primary) !important;
}

.nav-tabs .nav-link:not(.active):hover {
    background-color: var(--bs-primary) !important;
    color: white !important;
}

/* Add styles for personalization fields in subject */
.personalization-field {
    background-color: #fff3cd;
    border: 1px solid #ffeeba;
    border-radius: 3px;
    padding: 0 3px;
    cursor: help;
}

/* Fix modal accessibility */
.modal {
    display: none;
}

.modal.show {
    display: block;
}

.modal[aria-modal="true"] {
    display: block;
}

.modal[aria-modal="true"] .modal-dialog {
    margin: 1.75rem auto;
}

/* Ensure focus is visible */
.modal:focus-within {
    outline: none;
}

.modal .btn:focus {
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

/* Fix modal backdrop */
.modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    z-index: 1040;
    width: 100vw;
    height: 100vh;
    background-color: #000;
}

.modal-backdrop.show {
    opacity: 0.5;
}
</style>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
?> 