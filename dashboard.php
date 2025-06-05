<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$userName = $_SESSION['user_name'];
$userEmail = $_SESSION['user_email'];

// Initialize database connection
$db = (new Database())->connect();

// Get campaign statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT c.campaign_id) as total_campaigns,
        COUNT(el.id) as total_emails,
        SUM(CASE WHEN el.status = 'sent' THEN 1 ELSE 0 END) as sent_emails,
        SUM(CASE WHEN el.status = 'failed' THEN 1 ELSE 0 END) as failed_emails,
        COUNT(DISTINCT et.TrackingID) as total_opens
    FROM campaigns c
    LEFT JOIN email_logs el ON c.campaign_id = el.campaign_id
    LEFT JOIN email_tracking et ON c.campaign_id = et.CampaignID
    WHERE c.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get scheduled emails
$stmt = $db->prepare("
    SELECT * FROM scheduled_emails 
    WHERE user_id = ? AND status = 'pending' 
    ORDER BY scheduled_time ASC
");
$stmt->execute([$_SESSION['user_id']]);
$scheduledEmails = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate seen rate
$seenRate = $stats['total_emails'] > 0 
    ? round(($stats['total_opens'] / $stats['total_emails']) * 100, 1) 
    : 0;

// Recent campaigns with details (limited to 5)
$stmt = $db->prepare("
    SELECT 
        c.campaign_id,
        c.subject,
        c.created_at,
        COUNT(el.id) as total_emails,
        SUM(CASE WHEN el.status = 'sent' THEN 1 ELSE 0 END) as sent_count,
        SUM(CASE WHEN el.status = 'failed' THEN 1 ELSE 0 END) as failed_count,
        COUNT(DISTINCT et.TrackingID) as open_count
    FROM campaigns c
    LEFT JOIN email_logs el ON c.campaign_id = el.campaign_id
    LEFT JOIN email_tracking et ON c.campaign_id = et.CampaignID
    WHERE c.user_id = ?
    GROUP BY c.campaign_id, c.subject, c.created_at
    ORDER BY c.created_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recentCampaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sample notifications (in a real app, these would come from the database)
$notifications = [
    [
        'type' => 'success',
        'icon' => 'check-circle',
        'message' => 'Campaign "Summer Sale" completed successfully',
        'time' => '2 hours ago'
    ],
    [
        'type' => 'warning',
        'icon' => 'exclamation-triangle',
        'message' => 'SMTP server response time is slower than usual',
        'time' => '3 hours ago'
    ],
    [
        'type' => 'info',
        'icon' => 'info-circle',
        'message' => 'New email template available: "Product Launch"',
        'time' => '5 hours ago'
    ],
    [
        'type' => 'danger',
        'icon' => 'times-circle',
        'message' => 'Failed to send 5 emails in "Newsletter" campaign',
        'time' => '1 day ago'
    ]
];

// Check if user needs to see tutorial
$stmt = $db->prepare("SELECT has_seen_tutorial FROM users WHERE userId = ?");
$stmt->execute([$_SESSION['user_id']]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);
$showTutorial = !$userData['has_seen_tutorial'];

// Prepare the content for the layout
ob_start();
?>

<!-- Add Intro.js CSS and JS -->
<link rel="stylesheet" href="https://unpkg.com/intro.js/minified/introjs.min.css">
<script src="https://unpkg.com/intro.js/minified/intro.min.js"></script>

<style>
/* Custom Intro.js Styling */
.introjs-tooltip {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    padding: 20px;
    max-width: 400px;
}

.introjs-tooltip-title {
    color: #2c3e50;
    font-size: 1.2rem;
    font-weight: 600;
    margin-bottom: 10px;
}

.introjs-tooltiptext {
    color: #34495e;
    font-size: 0.95rem;
    line-height: 1.5;
    margin-bottom: 15px;
}

.introjs-button {
    background: #3498db;
    border: none;
    border-radius: 4px;
    color: white;
    font-size: 0.9rem;
    font-weight: 500;
    padding: 8px 16px;
    margin: 0 5px;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.introjs-button:hover {
    background: #2980b9;
    transform: translateY(-1px);
}

.introjs-button.introjs-skipbutton {
    background: #95a5a6;
    font-size: 0.85rem;
    padding: 6px 12px;
}

.introjs-button.introjs-skipbutton:hover {
    background: #7f8c8d;
}

.introjs-button.introjs-skipbutton::before {
    content: "×";
    font-size: 1.2rem;
    font-weight: bold;
    line-height: 1;
}

.introjs-bullets ul li a {
    background: #bdc3c7;
}

.introjs-bullets ul li a.active {
    background: #3498db;
}

.introjs-helperLayer {
    background: rgba(52, 152, 219, 0.1);
    border: 2px solid #3498db;
    border-radius: 4px;
}

.introjs-tooltipReferenceLayer {
    background: rgba(0, 0, 0, 0.5);
}

.introjs-tooltipbuttons {
    border-top: 1px solid #ecf0f1;
    padding-top: 15px;
    margin-top: 15px;
    text-align: right;
}

.introjs-progress {
    background-color: #ecf0f1;
    height: 3px;
}

.introjs-progressbar {
    background-color: #3498db;
}

.introjs-tooltip .introjs-tooltipbuttons {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.introjs-tooltip .introjs-tooltipbuttons button {
    min-width: 80px;
}
</style>

<?php if ($showTutorial): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const intro = introJs();
    
    intro.setOptions({
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
                title: 'Welcome to Bulk Email System!',
                intro: "Let's take a quick tour of the main features to help you get started with your email marketing journey.",
                position: 'center'
            },
            {
                title: 'Navigation Sidebar',
                element: document.querySelector('.sidebar'),
                intro: "This is your main navigation panel. You can collapse it to get more workspace by clicking the toggle button.",
                position: 'right'
            },
            {
                title: 'Dashboard',
                element: document.querySelector('.menu-item[href="dashboard.php"]'),
                intro: "Your command center. View campaign statistics, recent activities, and quick insights about your email marketing performance.",
                position: 'right'
            },
            {
                title: 'Email Lists',
                element: document.querySelector('.menu-item[href="email_lists.php"]'),
                intro: "Manage your contact lists here. Create, import, and organize your email contacts for targeted campaigns.",
                position: 'right'
            },
            {
                title: 'History',
                element: document.querySelector('.menu-item[href="history.php"]'),
                intro: "Track all your past campaigns. View detailed reports, open rates, and delivery statistics for each campaign.",
                position: 'right'
            },
            {
                title: 'Drafts',
                element: document.querySelector('.menu-item[href="email_drafts.php"]'),
                intro: "Access your saved email templates and draft campaigns. Perfect for reusing successful content or finishing incomplete campaigns.",
                position: 'right'
            },
            {
                title: 'New Campaign',
                element: document.querySelector('.sidebar-footer .menu-item[href="new_campaign.php"]'),
                intro: "Start creating a new email campaign. Choose your recipients, design your email, and schedule when to send it.",
                position: 'right'
            },
            {
                title: 'Scheduled Campaigns',
                element: document.querySelector('.menu-item[href="scheduled_campaigns.php"]'),
                intro: "View and manage your upcoming email campaigns. Edit schedules, pause, or cancel scheduled sends.",
                position: 'right'
            },
            {
                title: 'New Draft',
                element: document.querySelector('.menu-item[href="new_draft.php"]'),
                intro: "Create a new email template or draft. Save your work in progress and come back to it later.",
                position: 'right'
            },
            {
                title: 'Profile Settings',
                element: document.querySelector('#profileButton'),
                intro: "Access your account settings, update your profile information, and manage your email sender configurations.",
                position: 'right'
            },
            {
                title: 'Campaign Overview',
                element: document.querySelector('.card.bg-primary'),
                intro: "Track your total number of campaigns here. This gives you a quick overview of your email marketing activity.",
                position: 'bottom'
            },
            {
                title: 'Email Performance',
                element: document.querySelector('.card.bg-success'),
                intro: "Monitor your email delivery success rate. Keep track of how many emails have been successfully sent across all campaigns.",
                position: 'bottom'
            },
            {
                title: 'Engagement Metrics',
                element: document.querySelector('.card.bg-info'),
                intro: "This shows your email open rate - a crucial metric for measuring the effectiveness of your campaigns.",
                position: 'bottom'
            },
            {
                title: 'Delivery Status',
                element: document.querySelector('.card.bg-warning'),
                intro: "Stay informed about any failed email deliveries. This helps you maintain a healthy sender reputation.",
                position: 'bottom'
            },
            {
                title: 'Best Practices',
                element: document.querySelector('.tips-list'),
                intro: "Discover proven strategies and best practices to enhance your email marketing results and engagement.",
                position: 'left'
            },
            {
                title: 'Ready to Send Your First Email?',
                intro: "Now that you're familiar with the dashboard, let's learn how to create and send your first email campaign!",
                position: 'center'
            },
            {
                title: 'Create New Campaign',
                element: document.querySelector('.sidebar-footer .menu-item[href="new_campaign.php"]'),
                intro: "Click this button to start creating your first email campaign. I'll guide you through the process step by step.",
                position: 'right',
                buttons: [] // Remove all buttons for this step
            }
        ]
    });

    // Add event listeners
    intro.oncomplete(function() {
        // Mark tutorial as seen when completed
        fetch('mark_tutorial_seen.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        });
    });

    intro.onexit(function() {
        // Mark tutorial as seen when skipped
        fetch('mark_tutorial_seen.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        });
    });

    // Start the tutorial
    intro.start();

    // Add click event listener to the New Campaign button
    const newCampaignBtn = document.querySelector('.sidebar-footer .menu-item[href="new_campaign.php"]');
    if (newCampaignBtn) {
        newCampaignBtn.addEventListener('click', function(e) {
            // Store a flag in sessionStorage to indicate tutorial continuation
            sessionStorage.setItem('continueTutorial', 'true');
        });
    }
});
</script>
<?php endif; ?>

<div class="container-fluid">
    <!-- Main Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Campaigns</h6>
                    <h2 class="mb-0"><?php echo $stats['total_campaigns']; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Emails Sent</h6>
                    <h2 class="mb-0"><?php echo $stats['sent_emails']; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">Seen Rate</h6>
                    <h2 class="mb-0"><?php echo $seenRate; ?>%</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="card-title">Failed Emails</h6>
                    <h2 class="mb-0"><?php echo $stats['failed_emails']; ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row">
        <!-- Recent Campaigns -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Campaigns</h5>
                        <a href="new_campaign.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus-circle me-1"></i>New Campaign
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Date</th>
                                    <th>Sent</th>
                                    <th>Opens</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentCampaigns as $campaign): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($campaign['subject']); ?></td>
                                        <td><?php echo date('M d, H:i', strtotime($campaign['created_at'])); ?></td>
                                        <td><?php echo $campaign['sent_count']; ?></td>
                                        <td><?php echo $campaign['open_count']; ?></td>
                                        <td>
                                            <?php if ($campaign['failed_count'] > 0): ?>
                                                <span class="badge bg-danger"><?php echo $campaign['failed_count']; ?> failed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Scheduled Emails -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Scheduled Emails</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($scheduledEmails)): ?>
                        <p class="text-muted mb-0">No emails scheduled at the moment.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Scheduled For</th>
                                        <th>Recipients</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($scheduledEmails as $email): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($email['subject']); ?></td>
                                            <td><?php echo date('M d, H:i', strtotime($email['scheduled_time'])); ?></td>
                                            <td><?php echo count(explode(',', $email['to_emails'])); ?></td>
                                            <td><span class="badge bg-warning">Pending</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Best Practices and Tips -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Tips for Better Engagement</h5>
                </div>
                <div class="card-body">
                    <div class="tips-list">
                        <div class="tip-item mb-3">
                            <h6><i class="fas fa-clock text-primary me-2"></i>Timing Matters</h6>
                            <p class="small text-muted mb-0">Send emails during business hours (9 AM - 5 PM) for better open rates.</p>
                        </div>
                        <div class="tip-item mb-3">
                            <h6><i class="fas fa-heading text-primary me-2"></i>Compelling Subject Lines</h6>
                            <p class="small text-muted mb-0">Keep subject lines clear, concise, and action-oriented.</p>
                        </div>
                        <div class="tip-item mb-3">
                            <h6><i class="fas fa-mobile-alt text-primary me-2"></i>Mobile Optimization</h6>
                            <p class="small text-muted mb-0">Ensure your emails are mobile-friendly for better engagement.</p>
                        </div>
                        <div class="tip-item mb-3">
                            <h6><i class="fas fa-users text-primary me-2"></i>Personalization</h6>
                            <p class="small text-muted mb-0">Use recipient's name and relevant content for better response rates.</p>
                        </div>
                        <div class="tip-item">
                            <h6><i class="fas fa-chart-line text-primary me-2"></i>Regular Testing</h6>
                            <p class="small text-muted mb-0">Test different subject lines and content to optimize performance.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Quick Stats</h5>
                </div>
                <div class="card-body">
                    <div class="stat-item mb-3">
                        <h6 class="text-muted mb-1">Average Open Rate</h6>
                        <h4 class="mb-0"><?php echo $seenRate; ?>%</h4>
                    </div>
                    <div class="stat-item mb-3">
                        <h6 class="text-muted mb-1">Scheduled Emails</h6>
                        <h4 class="mb-0"><?php echo count($scheduledEmails); ?></h4>
                    </div>
                    <div class="stat-item">
                        <h6 class="text-muted mb-1">Success Rate</h6>
                        <h4 class="mb-0">
                            <?php 
                            $successRate = $stats['total_emails'] > 0 
                                ? round(($stats['sent_emails'] / $stats['total_emails']) * 100, 1) 
                                : 0;
                            echo $successRate . '%';
                            ?>
                        </h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Campaign Details Modal -->
<div class="modal fade" id="campaignModal" tabindex="-1" aria-labelledby="campaignModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="campaignModalLabel">Campaign Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="campaignDetails">
                    <!-- Details will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('campaignModal');
    const detailsContainer = document.getElementById('campaignDetails');
    
    modal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const campaignId = button.getAttribute('data-campaign-id');
        
        // Show loading state
        detailsContainer.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div></div>';
        
        // Fetch campaign details
        fetch(`get_campaign_details.php?id=${campaignId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const campaign = data.campaign;
                    const details = data.details;
                    
                    let html = `
                        <div class="mb-4">
                            <h6 class="mb-2">${campaign.subject}</h6>
                            <div class="text-muted mb-3">Created: ${new Date(campaign.created_at).toLocaleString()}</div>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6 class="card-title">Message Content:</h6>
                                    <div class="card-text">${campaign.message}</div>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Recipient</th>
                                        <th>Status</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    
                    details.forEach(detail => {
                        html += `
                            <tr>
                                <td>${detail.to_emails}</td>
                                <td>
                                    <span class="badge bg-${detail.status === 'sent' ? 'success' : 'danger'}">
                                        ${detail.status}
                                    </span>
                                </td>
                                <td>${new Date(detail.created_at).toLocaleString()}</td>
                            </tr>
                        `;
                    });
                    
                    html += `
                                </tbody>
                            </table>
                        </div>
                    `;
                    
                    detailsContainer.innerHTML = html;
                } else {
                    detailsContainer.innerHTML = `
                        <div class="alert alert-danger">
                            ${data.error || 'Error loading campaign details'}
                        </div>
                    `;
                }
            })
            .catch(error => {
                detailsContainer.innerHTML = `
                    <div class="alert alert-danger">
                        Error loading campaign details: ${error.message}
                    </div>
                `;
            });
    });
});
</script>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
?> 