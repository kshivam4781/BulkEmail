<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Check if list ID is provided
if (!isset($_GET['id'])) {
    header('Location: email_lists.php');
    exit();
}

try {
    $db = (new Database())->connect();
    
    // Get list details
    $stmt = $db->prepare("
        SELECT 
            cl.*,
            u.email as creator_email
        FROM contactlists cl
        LEFT JOIN users u ON cl.UserID = u.userId
        WHERE cl.ListID = ?
    ");
    $stmt->execute([$_GET['id']]);
    $list = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$list) {
        header('Location: email_lists.php');
        exit();
    }
    
    // Get contacts
    $stmt = $db->prepare("
        SELECT * FROM contacts 
        WHERE ListID = ? 
        ORDER BY FullName
    ");
    $stmt->execute([$_GET['id']]);
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    header('Location: email_lists.php');
    exit();
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
                    <h4 class="mb-1"><?php echo htmlspecialchars($list['Name']); ?></h4>
                    <p class="text-muted mb-0">
                        Created by <?php echo htmlspecialchars($list['creator_email']); ?> on 
                        <?php echo date('M d, Y H:i', strtotime($list['CreatedAt'])); ?>
                    </p>
                </div>
                <div>
                    <a href="email_lists.php" class="btn btn-light me-2">
                        <i class="fas fa-arrow-left me-2"></i>Back to Lists
                    </a>
                    <a href="new_campaign.php?list=<?php echo $list['ListID']; ?>" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>Use List
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Contacts Section -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <?php if (empty($contacts)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5>No Contacts Found</h5>
                            <p class="text-muted">This list is empty</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Company</th>
                                        <th>Created Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($contacts as $contact): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($contact['FullName']); ?></td>
                                            <td><?php echo htmlspecialchars($contact['Email']); ?></td>
                                            <td><?php echo htmlspecialchars($contact['Phone'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($contact['Company'] ?? '-'); ?></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($contact['CreatedAt'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
?> 