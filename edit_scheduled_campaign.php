<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$campaignId = $_GET['id'] ?? null;
if (!$campaignId) {
    header('Location: scheduled_campaigns.php');
    exit();
}

$db = (new Database())->connect();

// Get campaign details
$stmt = $db->prepare("
    SELECT c.*, u.email as sender_email
    FROM campaigns c
    LEFT JOIN users u ON c.user_id = u.userId
    WHERE c.campaign_id = ? AND c.user_id = ?
");
$stmt->execute([$campaignId, $_SESSION['user_id']]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$campaign) {
    header('Location: scheduled_campaigns.php');
    exit();
}

// Get scheduled emails
$stmt = $db->prepare("
    SELECT *
    FROM scheduled_emails
    WHERE campaign_id = ?
    ORDER BY id ASC
");
$stmt->execute([$campaignId]);
$scheduledEmails = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format scheduled time
$scheduledTime = new DateTime($campaign['scheduled_time']);
$scheduledTime->setTimezone(new DateTimeZone('America/Los_Angeles'));

// Process CC emails
$ccEmails = json_decode($campaign['cc_emails'] ?? '[]', true);

// Prepare the content for the layout
ob_start();
?>

<div class="container-fluid py-4">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1 text-primary">Edit Scheduled Campaign</h4>
                    <p class="text-muted mb-0">Modify campaign details and schedule</p>
                </div>
                <a href="scheduled_campaign.php?campaign_id=<?php echo $campaignId; ?>" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Campaign Details
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

    <!-- Edit Campaign Form -->
    <form id="editCampaignForm" action="update_campaign.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="campaign_id" value="<?php echo $campaignId; ?>">
        
        <div class="row">
            <!-- Left Column -->
            <div class="col-md-8">
                <!-- Subject -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="subject" name="subject" 
                                   value="<?php echo htmlspecialchars($campaign['subject']); ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Message Content -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="message" class="form-label">Message Content</label>
                            <textarea class="form-control" id="message" name="message" rows="10" required><?php echo htmlspecialchars($campaign['message_content']); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Recipients -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Recipients</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Email Address</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="recipientsList">
                                    <?php foreach ($scheduledEmails as $email): ?>
                                        <tr>
                                            <td>
                                                <input type="email" class="form-control" name="recipients[]" 
                                                       value="<?php echo htmlspecialchars($email['to_emails']); ?>" required>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-danger btn-action remove-recipient">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="addRecipient">
                            <i class="fas fa-plus me-2"></i>Add Recipient
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-md-4">
                <!-- CC Recipients -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">CC Recipients</h5>
                        <div id="ccList">
                            <?php foreach ($ccEmails as $index => $email): ?>
                                <div class="input-group mb-2">
                                    <input type="email" class="form-control" name="cc[]" 
                                           value="<?php echo htmlspecialchars($email); ?>">
                                    <button type="button" class="btn btn-outline-danger remove-cc">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="addCC">
                            <i class="fas fa-plus me-2"></i>Add CC
                        </button>
                    </div>
                </div>

                <!-- Schedule -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Schedule</h5>
                        <div class="mb-3">
                            <label for="scheduledDate" class="form-label">Date</label>
                            <input type="date" class="form-control" id="scheduledDate" name="scheduled_date" 
                                   value="<?php echo $scheduledTime->format('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="scheduledTime" class="form-label">Time</label>
                            <input type="time" class="form-control" id="scheduledTime" name="scheduled_time" 
                                   value="<?php echo $scheduledTime->format('H:i'); ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Attachment -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Attachment</h5>
                        <div class="mb-3">
                            <input type="file" class="form-control" id="attachment" name="attachment">
                            <?php if ($campaign['attachment_path']): ?>
                                <div class="mt-2">
                                    <small class="text-muted">Current attachment: 
                                        <a href="<?php echo htmlspecialchars($campaign['attachment_path']); ?>" target="_blank">
                                            <?php echo basename($campaign['attachment_path']); ?>
                                        </a>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Update Campaign
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.tiny.cloud/1/ty9olnipt398xv19du58z8ofchfkuctsjo8mwcwqry4jmwfy/tinymce/6/tinymce.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize TinyMCE
    tinymce.init({
        selector: '#message',
        height: 400,
        plugins: [
            'advlist autolink lists link image charmap print preview anchor',
            'searchreplace visualblocks code fullscreen',
            'insertdatetime media table paste code help wordcount'
        ],
        toolbar: 'undo redo | formatselect | bold italic backcolor | \
                alignleft aligncenter alignright alignjustify | \
                bullist numlist outdent indent | removeformat | help'
    });

    // Add recipient
    document.getElementById('addRecipient').addEventListener('click', function() {
        const tbody = document.getElementById('recipientsList');
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <input type="email" class="form-control" name="recipients[]" required>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-danger btn-action remove-recipient">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    // Remove recipient
    document.addEventListener('click', function(e) {
        if (e.target.closest('.remove-recipient')) {
            e.target.closest('tr').remove();
        }
    });

    // Add CC
    document.getElementById('addCC').addEventListener('click', function() {
        const ccList = document.getElementById('ccList');
        const div = document.createElement('div');
        div.className = 'input-group mb-2';
        div.innerHTML = `
            <input type="email" class="form-control" name="cc[]">
            <button type="button" class="btn btn-outline-danger remove-cc">
                <i class="fas fa-times"></i>
            </button>
        `;
        ccList.appendChild(div);
    });

    // Remove CC
    document.addEventListener('click', function(e) {
        if (e.target.closest('.remove-cc')) {
            e.target.closest('.input-group').remove();
        }
    });

    // Form validation
    document.getElementById('editCampaignForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Get scheduled date and time
        const scheduledDate = document.getElementById('scheduledDate').value;
        const scheduledTime = document.getElementById('scheduledTime').value;
        const scheduledDateTime = new Date(scheduledDate + 'T' + scheduledTime);
        const now = new Date();

        if (scheduledDateTime <= now) {
            alert('Please select a future date and time for scheduling.');
            return;
        }

        // Get message content from TinyMCE
        const messageContent = tinymce.get('message').getContent();
        if (!messageContent.trim()) {
            alert('Please enter a message.');
            return;
        }

        // Submit form
        this.submit();
    });
});
</script>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
?> 