<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Get campaign ID from URL
$campaignId = isset($_GET['campaign_id']) ? (int)$_GET['campaign_id'] : 0;

if (!$campaignId) {
    header('Location: new_campaign.php');
    exit();
}

// Get campaign details
$db = (new Database())->connect();
$stmt = $db->prepare("
    SELECT c.*, u.email as from_email 
    FROM campaigns c 
    JOIN users u ON c.user_id = u.userId 
    WHERE c.campaign_id = ? AND c.user_id = ?
");
$stmt->execute([$campaignId, $_SESSION['user_id']]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$campaign) {
    header('Location: new_campaign.php');
    exit();
}

// Get email logs for this campaign
$stmt = $db->prepare("
    SELECT * FROM email_logs 
    WHERE campaign_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$campaignId]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count statistics
$total = count($logs);
$sent = 0;
$failed = 0;
foreach ($logs as $log) {
    if ($log['status'] === 'sent') {
        $sent++;
    } else if ($log['status'] === 'failed') {
        $failed++;
    }
}

// Prepare the content for the layout
ob_start();
?>

<div class="container-fluid">
    <!-- Back Button and Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <a href="javascript:history.back()" class="btn btn-outline-secondary me-3">
                        <i class="fas fa-arrow-left me-2"></i>Back
                    </a>
                    <h4 class="mb-0">Campaign Results</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Emails</h6>
                    <h2 class="mb-0"><?php echo $total; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Successfully Sent</h6>
                    <h2 class="mb-0"><?php echo $sent; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6 class="card-title">Failed</h6>
                    <h2 class="mb-0"><?php echo $failed; ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Campaign Details -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-3">Campaign Details</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Subject:</strong> <?php echo htmlspecialchars($campaign['subject']); ?></p>
                            <p><strong>From:</strong> <?php echo htmlspecialchars($campaign['from_email']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Created:</strong> <?php echo date('M d, Y H:i', strtotime($campaign['created_at'])); ?></p>
                            <p><strong>Status:</strong> 
                                <?php if ($failed > 0): ?>
                                    <span class="badge bg-danger">Some Failed</span>
                                <?php elseif ($sent > 0): ?>
                                    <span class="badge bg-success">Completed</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Pending</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Email Logs -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-3">Email Logs</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Recipient</th>
                                    <th>Status</th>
                                    <th>Time</th>
                                    <th>Error Message</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['to_emails']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $log['status'] === 'sent' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($log['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></td>
                                        <td>
                                            <?php if ($log['status'] === 'failed' && !empty($log['error_message'])): ?>
                                                <span class="text-danger"><?php echo htmlspecialchars($log['error_message']); ?></span>
                                            <?php endif; ?>
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

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
?> 