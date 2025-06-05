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

// Get tracking details
$stmt = $db->prepare("
    SELECT 
        et.*,
        el.status as email_status,
        el.created_at as sent_at
    FROM email_tracking et
    LEFT JOIN email_logs el ON et.CampaignID = el.campaign_id AND et.RecipientEmail = el.to_emails
    WHERE et.CampaignID = ?
    ORDER BY et.LastOpenedAt DESC
");
$stmt->execute([$_GET['id']]);
$tracking = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$totalSent = $campaign['total_sent'];
$totalOpens = $campaign['total_opens'];
$openRate = $totalSent > 0 ? round(($totalOpens / $totalSent) * 100, 1) : 0;

// Prepare the content for the layout
ob_start();
?>

<div class="container-fluid py-4">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">Campaign Tracking</h4>
                    <p class="text-muted mb-0">Detailed tracking for: <?php echo htmlspecialchars($campaign['subject']); ?></p>
                </div>
                <a href="history.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to History
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Total Sent</h6>
                    <h3 class="card-title mb-0"><?php echo $totalSent; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Total Opens</h6>
                    <h3 class="card-title mb-0"><?php echo $totalOpens; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Open Rate</h6>
                    <h3 class="card-title mb-0"><?php echo $openRate; ?>%</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Campaign Date</h6>
                    <h3 class="card-title mb-0"><?php echo date('M j, Y', strtotime($campaign['created_at'])); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Tracking Details Table -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">Recipient Tracking Details</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Recipient</th>
                                    <th>First Open</th>
                                    <th>Last Open</th>
                                    <th>Open Count</th>
                                    <th>Device</th>
                                    <th>IP Address</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tracking as $track): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($track['RecipientEmail']); ?></td>
                                        <td>
                                            <?php 
                                            echo $track['FirstOpenedAt'] 
                                                ? date('M j, Y g:i A', strtotime($track['FirstOpenedAt']))
                                                : 'Not opened';
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            echo $track['LastOpenedAt'] 
                                                ? date('M j, Y g:i A', strtotime($track['LastOpenedAt']))
                                                : 'Not opened';
                                            ?>
                                        </td>
                                        <td><?php echo $track['OpenCount']; ?></td>
                                        <td>
                                            <?php 
                                            $userAgent = $track['UserAgent'];
                                            if (strpos($userAgent, 'iPhone') !== false) {
                                                echo '<i class="fab fa-apple me-1"></i>iPhone';
                                            } elseif (strpos($userAgent, 'Android') !== false) {
                                                echo '<i class="fab fa-android me-1"></i>Android';
                                            } elseif (strpos($userAgent, 'Windows') !== false) {
                                                echo '<i class="fab fa-windows me-1"></i>Windows';
                                            } elseif (strpos($userAgent, 'Macintosh') !== false) {
                                                echo '<i class="fab fa-apple me-1"></i>Mac';
                                            } else {
                                                echo '<i class="fas fa-desktop me-1"></i>Desktop';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo $track['IPAddress']; ?></td>
                                        <td>
                                            <?php if ($track['email_status'] === 'sent'): ?>
                                                <span class="badge bg-success">Sent</span>
                                            <?php elseif ($track['email_status'] === 'failed'): ?>
                                                <span class="badge bg-danger">Failed</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Pending</span>
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