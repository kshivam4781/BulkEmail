<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$campaignId = $_GET['campaign_id'] ?? null;
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

// Format dates
$createdAt = new DateTime($campaign['created_at']);
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
                    <h4 class="mb-1 text-primary">Campaign Details</h4>
                    <p class="text-muted mb-0">View scheduled campaign information</p>
                </div>
                <div>
                    <a href="edit_scheduled_campaign.php?id=<?php echo $campaignId; ?>" class="btn btn-primary me-2">
                        <i class="fas fa-edit me-2"></i>Edit Campaign
                    </a>
                    <a href="scheduled_campaigns.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Scheduled Campaigns
                    </a>
                </div>
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

    <!-- Campaign Details -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="card-title mb-4">Campaign Information</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <th style="width: 150px;">Campaign ID:</th>
                                    <td><?php echo htmlspecialchars($campaign['campaign_id']); ?></td>
                                </tr>
                                <tr>
                                    <th>Subject:</th>
                                    <td><?php echo htmlspecialchars($campaign['subject']); ?></td>
                                </tr>
                                <tr>
                                    <th>Created At:</th>
                                    <td><?php echo $createdAt->format('M j, Y g:i A'); ?></td>
                                </tr>
                                <tr>
                                    <th>Scheduled For:</th>
                                    <td><?php echo $scheduledTime->format('M j, Y g:i A'); ?></td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <span class="badge bg-warning">Scheduled</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Sender:</th>
                                    <td><?php echo htmlspecialchars($campaign['sender_email']); ?></td>
                                </tr>
                                <tr>
                                    <th>CC Recipients:</th>
                                    <td>
                                        <?php if (!empty($ccEmails)): ?>
                                            <?php echo htmlspecialchars(implode(', ', $ccEmails)); ?>
                                        <?php else: ?>
                                            <span class="text-muted">None</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Attachment:</th>
                                    <td>
                                        <?php if ($campaign['attachment_path']): ?>
                                            <a href="<?php echo htmlspecialchars($campaign['attachment_path']); ?>" target="_blank">
                                                <i class="fas fa-paperclip me-1"></i>View Attachment
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">None</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h5 class="card-title mb-4">Message Content</h5>
                            <div class="border rounded p-3 bg-light">
                                <?php echo $campaign['message_content']; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scheduled Emails Table -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-4">Scheduled Emails</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>To</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($scheduledEmails as $email): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($email['to_emails']); ?></td>
                                        <td>
                                            <span class="badge bg-warning">Scheduled</span>
                                        </td>
                                        <td>
                                            <button type="button" 
                                                    class="btn btn-sm btn-info btn-action view-email" 
                                                    data-email-id="<?php echo $email['id']; ?>"
                                                    title="View Email">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Email View Modal -->
<div class="modal fade" id="emailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Email Content</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="emailContent"></div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle view email button clicks
    document.querySelectorAll('.view-email').forEach(button => {
        button.addEventListener('click', function() {
            const emailId = this.dataset.emailId;
            const emailContent = this.closest('tr').querySelector('td:first-child').textContent;
            
            document.getElementById('emailContent').innerHTML = emailContent;
            const modal = new bootstrap.Modal(document.getElementById('emailModal'));
            modal.show();
        });
    });
});
</script>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
?> 