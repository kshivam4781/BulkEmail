<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$db = (new Database())->connect();

// Get KPIs for scheduled campaigns
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT c.campaign_id) as total_scheduled,
        COUNT(DISTINCT se.id) as total_emails,
        MIN(c.scheduled_time) as next_scheduled,
        COUNT(DISTINCT CASE WHEN c.scheduled_time <= NOW() THEN c.campaign_id END) as overdue
    FROM campaigns c
    LEFT JOIN scheduled_emails se ON c.campaign_id = se.campaign_id
    WHERE c.status = 'scheduled' AND c.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$kpis = $stmt->fetch(PDO::FETCH_ASSOC);

// Get scheduled campaigns with details
$stmt = $db->prepare("
    SELECT 
        c.*,
        COUNT(DISTINCT se.id) as total_emails,
        u.email as sender_email
    FROM campaigns c
    LEFT JOIN scheduled_emails se ON c.campaign_id = se.campaign_id
    LEFT JOIN users u ON c.user_id = u.userId
    WHERE c.status = 'scheduled' AND c.user_id = ?
    GROUP BY c.campaign_id
    ORDER BY c.scheduled_time ASC
");
$stmt->execute([$_SESSION['user_id']]);
$campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare the content for the layout
ob_start();
?>

<div class="container-fluid py-4">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1 text-primary">Scheduled Campaigns</h4>
                    <p class="text-muted mb-0">Manage your scheduled email campaigns</p>
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
                    <h6 class="card-subtitle mb-2">Total Scheduled</h6>
                    <h3 class="card-title mb-0"><?php echo $kpis['total_scheduled']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm kpi-card">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">Total Emails</h6>
                    <h3 class="card-title mb-0"><?php echo $kpis['total_emails']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm kpi-card">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">Next Scheduled</h6>
                    <h3 class="card-title mb-0">
                        <?php 
                        if ($kpis['next_scheduled']) {
                            $nextScheduled = new DateTime($kpis['next_scheduled']);
                            $nextScheduled->setTimezone(new DateTimeZone('America/Los_Angeles'));
                            echo $nextScheduled->format('M j, Y g:i A');
                        } else {
                            echo 'None';
                        }
                        ?>
                    </h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm kpi-card">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">Overdue</h6>
                    <h3 class="card-title mb-0"><?php echo $kpis['overdue']; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Campaigns Table -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Scheduled For</th>
                                    <th>Total Emails</th>
                                    <th>Sender</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($campaigns as $campaign): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($campaign['subject']); ?></td>
                                        <td>
                                            <?php 
                                            $scheduledTime = new DateTime($campaign['scheduled_time']);
                                            $scheduledTime->setTimezone(new DateTimeZone('America/Los_Angeles'));
                                            echo $scheduledTime->format('M j, Y g:i A');
                                            ?>
                                        </td>
                                        <td><?php echo $campaign['total_emails']; ?></td>
                                        <td><?php echo htmlspecialchars($campaign['sender_email']); ?></td>
                                        <td>
                                            <a href="scheduled_campaign.php?campaign_id=<?php echo $campaign['campaign_id']; ?>" 
                                               class="btn btn-sm btn-info btn-action" title="View Campaign">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_scheduled_campaign.php?id=<?php echo $campaign['campaign_id']; ?>" 
                                               class="btn btn-sm btn-primary btn-action" title="Edit Campaign">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger btn-action delete-campaign" 
                                                    data-campaign-id="<?php echo $campaign['campaign_id']; ?>"
                                                    title="Delete Campaign">
                                                <i class="fas fa-trash"></i>
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

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this scheduled campaign? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle delete button clicks
    document.querySelectorAll('.delete-campaign').forEach(button => {
        button.addEventListener('click', function() {
            const campaignId = this.dataset.campaignId;
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            
            document.getElementById('confirmDelete').onclick = function() {
                // Send delete request
                fetch('delete_campaign.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        campaign_id: campaignId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert(data.message || 'Error deleting campaign');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting campaign');
                });
                
                modal.hide();
            };
            
            modal.show();
        });
    });

    // Handle edit button clicks
    document.querySelectorAll('.edit-campaign').forEach(button => {
        button.addEventListener('click', function() {
            const campaignId = this.dataset.campaignId;
            window.location.href = `edit_campaign.php?id=${campaignId}`;
        });
    });
});
</script>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
?> 