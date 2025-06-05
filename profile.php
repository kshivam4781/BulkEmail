<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Initialize database connection
$db = (new Database())->connect();

// Get current user data first
try {
    $stmt = $db->prepare("SELECT name, email FROM users WHERE userId = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception("User not found.");
    }
} catch (PDOException $e) {
    $error_message = "Error fetching user data.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate input
    if (empty($name) || empty($email)) {
        $error_message = "Name and email are required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        try {
            // Start transaction
            $db->beginTransaction();

            // Check if email is already taken by another user
            $stmt = $db->prepare("SELECT userId FROM users WHERE email = ? AND userId != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->rowCount() > 0) {
                throw new Exception("Email address is already in use.");
            }

            // Update basic info
            $stmt = $db->prepare("UPDATE users SET name = ?, email = ? WHERE userId = ?");
            $stmt->execute([$name, $email, $user_id]);

            // Update password if provided
            if (!empty($current_password)) {
                // Verify current password
                $stmt = $db->prepare("SELECT passwordHash FROM users WHERE userId = ?");
                $stmt->execute([$user_id]);
                $userData = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!password_verify($current_password, $userData['passwordHash'])) {
                    throw new Exception("Current password is incorrect.");
                }

                // Validate new password
                if (empty($new_password)) {
                    throw new Exception("New password is required when changing password.");
                }
                if (strlen($new_password) < 8) {
                    throw new Exception("New password must be at least 8 characters long.");
                }
                if ($new_password !== $confirm_password) {
                    throw new Exception("New passwords do not match.");
                }

                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET passwordHash = ? WHERE userId = ?");
                $stmt->execute([$hashed_password, $user_id]);
            }

            $db->commit();
            $success_message = "Profile updated successfully!";
            
            // Update session name
            $_SESSION['user_name'] = $name;
            
            // Refresh user data
            $stmt = $db->prepare("SELECT name, email FROM users WHERE userId = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = $e->getMessage();
        }
    }
}

// Start output buffering
ob_start();
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">My Profile</h4>
                </div>
                <div class="card-body">
                    <?php if ($success_message): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>

                        <hr class="my-4">
                        <h5>Change Password</h5>
                        <p class="text-muted small">To change your password, please enter your current password and your new password</p>

                        <div class="mb-3 position-relative">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                            <span class="password-toggle position-absolute" style="right: 10px; top: 38px; cursor: pointer;" onclick="togglePassword('current_password')">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>

                        <div class="mb-3 position-relative">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" 
                                   minlength="8" required>
                            <span class="password-toggle position-absolute" style="right: 10px; top: 38px; cursor: pointer;" onclick="togglePassword('new_password')">
                                <i class="fas fa-eye"></i>
                            </span>
                            <div class="form-text">Password must be at least 8 characters long</div>
                        </div>

                        <div class="mb-3 position-relative">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            <span class="password-toggle position-absolute" style="right: 10px; top: 38px; cursor: pointer;" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.password-toggle {
    color: #666;
    transition: color 0.3s ease;
}

.password-toggle:hover {
    color: #0d6efd;
}
</style>

<script>
// Form validation
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
})()

// Password toggle function
function togglePassword(inputId) {
    const passwordInput = document.getElementById(inputId);
    const toggleIcon = passwordInput.nextElementSibling.querySelector('i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}

// Password validation
document.getElementById('new_password').addEventListener('input', function() {
    const confirmPassword = document.getElementById('confirm_password');
    if (this.value !== confirmPassword.value) {
        confirmPassword.setCustomValidity('Passwords do not match');
    } else {
        confirmPassword.setCustomValidity('');
    }
});

document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password');
    if (this.value !== newPassword.value) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
?> 