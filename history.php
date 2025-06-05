<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'config/database.php';

$db = (new Database())->connect();

// Get KPI statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT c.campaign_id) as total_campaigns,
        COUNT(DISTINCT CASE WHEN el.status = 'sent' THEN c.campaign_id END) as successful_campaigns,
        COUNT(CASE WHEN el.status = 'sent' THEN el.id END) as total_emails_sent,
        COUNT(et.TrackingID) as total_emails_viewed
    FROM campaigns c
    LEFT JOIN email_logs el ON c.campaign_id = el.campaign_id
    LEFT JOIN email_tracking et ON c.campaign_id = et.CampaignID
    WHERE c.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$kpis = $stmt->fetch(PDO::FETCH_ASSOC);

// Get filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date_desc';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build the query with filters
$query = "
    SELECT 
        c.*,
        COUNT(DISTINCT et.RecipientEmail) as total_opens,
        COUNT(DISTINCT el.to_emails) as total_sent,
        MAX(et.LastOpenedAt) as last_open
    FROM campaigns c
    LEFT JOIN email_tracking et ON c.campaign_id = et.CampaignID
    LEFT JOIN email_logs el ON c.campaign_id = el.campaign_id
    WHERE c.user_id = ?
";

$params = [$_SESSION['user_id']];

if ($search) {
    $query .= " AND (c.subject LIKE ? OR c.message LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($dateFrom) {
    $query .= " AND c.created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}

if ($dateTo) {
    $query .= " AND c.created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

$query .= " GROUP BY c.campaign_id";

// Add sorting
switch ($sort) {
    case 'date_asc':
        $query .= " ORDER BY c.created_at ASC";
        break;
    case 'opens_desc':
        $query .= " ORDER BY total_opens DESC";
        break;
    case 'opens_asc':
        $query .= " ORDER BY total_opens ASC";
        break;
    case 'sent_desc':
        $query .= " ORDER BY total_sent DESC";
        break;
    case 'sent_asc':
        $query .= " ORDER BY total_sent ASC";
        break;
    default: // date_desc
        $query .= " ORDER BY c.created_at DESC";
}

$stmt = $db->prepare($query);
$stmt->execute($params);
$campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare the content for the layout
ob_start();
?>

<div class="container-fluid py-4">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center page-header">
                <div>
                    <h4 class="mb-1">Campaign History</h4>
                    <p class="text-muted mb-0">View your email campaign results and tracking</p>
                </div>
                <a href="new_campaign.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>New Campaign
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

    <!-- KPI Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm kpi-card">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">Total Campaigns</h6>
                    <h3 class="card-title mb-0"><?php echo $kpis['total_campaigns']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm kpi-card">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">Successful Campaigns</h6>
                    <h3 class="card-title mb-0"><?php echo $kpis['successful_campaigns']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm kpi-card">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">Total Emails Sent</h6>
                    <h3 class="card-title mb-0"><?php echo $kpis['total_emails_sent']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm kpi-card">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">Total Emails Viewed</h6>
                    <h3 class="card-title mb-0"><?php echo $kpis['total_emails_viewed']; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm filter-card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <input type="text" class="form-control" name="search" placeholder="Search campaigns..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <input type="date" class="form-control" name="date_from" value="<?php echo $dateFrom; ?>" placeholder="From Date">
                        </div>
                        <div class="col-md-2">
                            <input type="date" class="form-control" name="date_to" value="<?php echo $dateTo; ?>" placeholder="To Date">
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="sort">
                                <option value="date_desc" <?php echo $sort === 'date_desc' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="date_asc" <?php echo $sort === 'date_asc' ? 'selected' : ''; ?>>Oldest First</option>
                                <option value="opens_desc" <?php echo $sort === 'opens_desc' ? 'selected' : ''; ?>>Most Opens</option>
                                <option value="opens_asc" <?php echo $sort === 'opens_asc' ? 'selected' : ''; ?>>Least Opens</option>
                                <option value="sent_desc" <?php echo $sort === 'sent_desc' ? 'selected' : ''; ?>>Most Sent</option>
                                <option value="sent_asc" <?php echo $sort === 'sent_asc' ? 'selected' : ''; ?>>Least Sent</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Campaigns Table -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm table-card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Sent Date</th>
                                    <th>Total Sent</th>
                                    <th>Opens</th>
                                    <th>Open Rate</th>
                                    <th>Last Open</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($campaigns as $campaign): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($campaign['subject']); ?></td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($campaign['created_at'])); ?></td>
                                        <td><?php echo $campaign['total_sent']; ?></td>
                                        <td><?php echo $campaign['total_opens']; ?></td>
                                        <td>
                                            <?php 
                                            $openRate = $campaign['total_sent'] > 0 
                                                ? round(($campaign['total_opens'] / $campaign['total_sent']) * 100, 1) 
                                                : 0;
                                            echo $openRate . '%';
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            echo $campaign['last_open'] 
                                                ? date('M j, Y g:i A', strtotime($campaign['last_open']))
                                                : 'Not opened yet';
                                            ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="view_campaign.php?id=<?php echo $campaign['campaign_id']; ?>" 
                                                   class="btn btn-sm btn-info btn-action" 
                                                   data-bs-toggle="tooltip" 
                                                   title="View Campaign Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="campaign_tracking.php?id=<?php echo $campaign['campaign_id']; ?>" 
                                                   class="btn btn-sm btn-primary btn-action" 
                                                   data-bs-toggle="tooltip" 
                                                   title="View Tracking Details">
                                                    <i class="fas fa-chart-line"></i>
                                                </a>
                                            </div>
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

<script>
// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
?> 