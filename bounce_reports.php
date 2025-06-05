<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Get bounce statistics
$db = (new Database())->connect();

// Get total bounces
$stmt = $db->prepare("
    SELECT COUNT(*) as total_bounces,
           SUM(CASE WHEN bounce_type = 'hard' THEN 1 ELSE 0 END) as hard_bounces,
           SUM(CASE WHEN bounce_type = 'soft' THEN 1 ELSE 0 END) as soft_bounces
    FROM bounce_logs
    WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$bounceStats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent bounces
$stmt = $db->prepare("
    SELECT bl.*, c.subject as campaign_subject
    FROM bounce_logs bl
    JOIN campaigns c ON bl.campaign_id = c.campaign_id
    WHERE bl.user_id = ?
    ORDER BY bl.created_at DESC
    LIMIT 50
");
$stmt->execute([$_SESSION['user_id']]);
$recentBounces = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare the content for the layout
ob_start();
?>

<div class="container-fluid py-4">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1 text-primary">Bounce Reports</h4>
                    <p class="text-muted mb-0">Monitor and analyze email bounce rates</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card kpi-card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white mb-1">Total Bounces</h6>
                            <h2 class="text-white mb-0"><?php echo number_format($bounceStats['total_bounces']); ?></h2>
                        </div>
                        <div class="bg-white bg-opacity-25 rounded-circle p-3">
                            <i class="fas fa-exclamation-circle text-white fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card kpi-card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white mb-1">Hard Bounces</h6>
                            <h2 class="text-white mb-0"><?php echo number_format($bounceStats['hard_bounces']); ?></h2>
                        </div>
                        <div class="bg-white bg-opacity-25 rounded-circle p-3">
                            <i class="fas fa-times-circle text-white fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card kpi-card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white mb-1">Soft Bounces</h6>
                            <h2 class="text-white mb-0"><?php echo number_format($bounceStats['soft_bounces']); ?></h2>
                        </div>
                        <div class="bg-white bg-opacity-25 rounded-circle p-3">
                            <i class="fas fa-exclamation-triangle text-white fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Bounces Table -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-primary">
                        <i class="fas fa-history me-2"></i>Recent Bounces
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Campaign</th>
                                    <th>Email</th>
                                    <th>Type</th>
                                    <th>Reason</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentBounces as $bounce): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($bounce['campaign_subject']); ?></td>
                                    <td><?php echo htmlspecialchars($bounce['to_email']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $bounce['bounce_type'] === 'hard' ? 'danger' : 'warning'; ?>">
                                            <?php echo ucfirst($bounce['bounce_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($bounce['bounce_reason']); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($bounce['created_at'])); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                onclick="viewBounceDetails(<?php echo $bounce['bounce_id']; ?>)">
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

<!-- Bounce Details Modal -->
<div class="modal fade" id="bounceDetailsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom bg-light">
                <h5 class="modal-title text-primary">
                    <i class="fas fa-exclamation-circle me-2"></i>Bounce Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="bounceDetailsContent">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function viewBounceDetails(bounceId) {
    // Show loading state
    document.getElementById('bounceDetailsContent').innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('bounceDetailsModal'));
    modal.show();
    
    // Fetch bounce details
    fetch(`get_bounce_details.php?id=${bounceId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const bounce = data.bounce;
                document.getElementById('bounceDetailsContent').innerHTML = `
                    <div class="mb-3">
                        <label class="form-label fw-bold">Campaign</label>
                        <p class="mb-0">${bounce.campaign_subject}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Email Address</label>
                        <p class="mb-0">${bounce.to_email}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Bounce Type</label>
                        <p class="mb-0">
                            <span class="badge bg-${bounce.bounce_type === 'hard' ? 'danger' : 'warning'}">
                                ${bounce.bounce_type.charAt(0).toUpperCase() + bounce.bounce_type.slice(1)}
                            </span>
                        </p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Bounce Reason</label>
                        <p class="mb-0">${bounce.bounce_reason || 'N/A'}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Bounce Code</label>
                        <p class="mb-0">${bounce.bounce_code || 'N/A'}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Bounce Message</label>
                        <p class="mb-0">${bounce.bounce_message || 'N/A'}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Date</label>
                        <p class="mb-0">${new Date(bounce.created_at).toLocaleString()}</p>
                    </div>
                `;
            } else {
                document.getElementById('bounceDetailsContent').innerHTML = `
                    <div class="alert alert-danger">
                        ${data.message || 'Error loading bounce details'}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('bounceDetailsContent').innerHTML = `
                <div class="alert alert-danger">
                    Error loading bounce details
                </div>
            `;
        });
}
</script>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
?> 