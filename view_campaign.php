<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: history.php');
    exit();
}

$db = (new Database())->connect();

// Get campaign details
$stmt = $db->prepare("
    SELECT c.*, 
           COUNT(DISTINCT el.to_emails) as total_sent,
           COUNT(DISTINCT et.RecipientEmail) as total_opens
    FROM campaigns c
    LEFT JOIN email_logs el ON c.campaign_id = el.campaign_id
    LEFT JOIN email_tracking et ON c.campaign_id = et.CampaignID
    WHERE c.campaign_id = ? AND c.user_id = ?
    GROUP BY c.campaign_id
");
$stmt->execute([$_GET['id'], $_SESSION['user_id']]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$campaign) {
    $_SESSION['message'] = "Campaign not found";
    $_SESSION['message_type'] = "danger";
    header('Location: history.php');
    exit();
}

// Get campaign data if available
$stmt = $db->prepare("SELECT excel_data FROM campaign_data WHERE campaign_id = ?");
$stmt->execute([$_GET['id']]);
$campaignData = $stmt->fetch(PDO::FETCH_ASSOC);

// Prepare the content for the layout
ob_start();
?>

<div class="container-fluid py-4">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">Campaign Details</h4>
                    <p class="text-muted mb-0">View campaign information and content</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="campaign_tracking.php?id=<?php echo $campaign['campaign_id']; ?>" class="btn btn-primary">
                        <i class="fas fa-chart-line me-2"></i>View Tracking
                    </a>
                    <a href="history.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to History
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Campaign Information -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">Campaign Information</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Subject</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($campaign['subject']); ?></dd>

                        <dt class="col-sm-4">Sent Date</dt>
                        <dd class="col-sm-8"><?php echo date('M j, Y g:i A', strtotime($campaign['created_at'])); ?></dd>

                        <dt class="col-sm-4">Total Sent</dt>
                        <dd class="col-sm-8"><?php echo $campaign['total_sent']; ?></dd>

                        <dt class="col-sm-4">Total Opens</dt>
                        <dd class="col-sm-8"><?php echo $campaign['total_opens']; ?></dd>

                        <dt class="col-sm-4">Open Rate</dt>
                        <dd class="col-sm-8">
                            <?php 
                            $openRate = $campaign['total_sent'] > 0 
                                ? round(($campaign['total_opens'] / $campaign['total_sent']) * 100, 1) 
                                : 0;
                            echo $openRate . '%';
                            ?>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">Campaign Content</h5>
                </div>
                <div class="card-body">
                    <div class="border rounded p-3 bg-light">
                        <?php echo nl2br(htmlspecialchars($campaign['message'])); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($campaignData && $campaignData['excel_data']): ?>
    <!-- Excel Data Preview -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">Excel Data Preview</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <?php 
                                    $excelData = json_decode($campaignData['excel_data'], true);
                                    foreach ($excelData['headers'] as $header): 
                                    ?>
                                        <th><?php echo htmlspecialchars($header); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($excelData['data'], 0, 5) as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $cell): ?>
                                            <td><?php echo htmlspecialchars($cell); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
?> 