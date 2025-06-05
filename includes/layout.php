<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Email System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --accent-color: #3498db;
            --text-color: #ecf0f1;
            --hover-color: rgba(255, 255, 255, 0.1);
            --active-color: rgba(255, 255, 255, 0.15);
        }

        body {
            min-height: 100vh;
            background: #f8f9fa;
            display: flex;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: var(--text-color);
            height: 100vh;
            position: fixed;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar-header {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header h3 {
            margin: 0;
            font-size: 1.5rem;
            white-space: nowrap;
            overflow: hidden;
            color: var(--text-color);
        }

        .user-greeting {
            padding: 1rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-greeting p {
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            color: var(--text-color);
        }

        .sidebar-menu {
            padding: 1rem 0;
            height: calc(100vh - 300px);
            overflow-y: auto;
        }

        .menu-section {
            padding: 0.5rem 0;
        }

        .menu-section-title {
            padding: 0.5rem 1.5rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.6);
            white-space: nowrap;
            overflow: hidden;
        }

        .menu-item {
            padding: 0.8rem 1.5rem;
            display: flex;
            align-items: center;
            color: var(--text-color);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .menu-item:hover {
            background: var(--hover-color);
            color: var(--text-color);
        }

        .menu-item.active {
            background: var(--active-color);
        }

        .menu-item i {
            width: 20px;
            margin-right: 10px;
            text-align: center;
        }

        .menu-item span {
            white-space: nowrap;
            overflow: hidden;
        }

        .sidebar-footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            transition: all 0.3s ease;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .main-content.expanded {
            margin-left: var(--sidebar-collapsed-width);
        }

        .content-wrapper {
            flex: 1;
            padding: 2rem;
        }

        /* Footer Styles */
        .footer {
            background: white;
            padding: 1rem;
            text-align: center;
            border-top: 1px solid #eee;
        }

        /* Toggle Button */
        .sidebar-toggle {
            position: fixed;
            top: 1rem;
            left: calc(var(--sidebar-width) - 30px);
            background: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 1001;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .sidebar-toggle.collapsed {
            left: calc(var(--sidebar-collapsed-width) - 30px);
        }

        .sidebar-toggle i {
            transition: transform 0.3s ease;
        }

        .sidebar-toggle.collapsed i {
            transform: rotate(180deg);
        }

        /* Dropdown Styles */
        .dropdown-menu {
            background: var(--primary-color);
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .dropdown-item {
            color: var(--text-color);
        }

        .dropdown-item:hover {
            background: var(--hover-color);
            color: var(--text-color);
        }

        .dropdown-divider {
            border-color: rgba(255, 255, 255, 0.1);
        }

        /* Profile Panel Styles */
        .profile-panel {
            position: fixed;
            top: 0;
            right: -300px;
            width: 300px;
            height: 100vh;
            background: var(--primary-color);
            color: var(--text-color);
            transition: right 0.3s ease;
            z-index: 1002;
            box-shadow: -2px 0 10px rgba(0,0,0,0.1);
        }

        .profile-panel.show {
            right: 0;
        }

        .profile-panel-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .profile-panel-header h4 {
            margin: 0;
            font-size: 1.2rem;
        }

        .profile-panel-close {
            background: none;
            border: none;
            color: var(--text-color);
            font-size: 1.2rem;
            cursor: pointer;
            padding: 0.5rem;
        }

        .profile-panel-body {
            padding: 1.5rem;
        }

        .profile-option {
            display: flex;
            align-items: center;
            padding: 1rem;
            color: var(--text-color);
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 0.375rem;
            margin-bottom: 0.5rem;
        }

        .profile-option:hover {
            background: var(--hover-color);
            color: var(--text-color);
        }

        .profile-option i {
            width: 20px;
            margin-right: 10px;
        }

        .profile-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1001;
            display: none;
        }

        .profile-overlay.show {
            display: block;
        }
    </style>
</head>
<body>
    <!-- Profile Panel -->
    <div class="profile-panel" id="profilePanel">
        <div class="profile-panel-header">
            <h4>Profile Options</h4>
            <button class="profile-panel-close" id="profilePanelClose">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="profile-panel-body">
            <a href="profile.php" class="profile-option">
                <i class="fas fa-user"></i>
                My Profile
            </a>
            <a href="logout.php" class="profile-option">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </div>

    <!-- Profile Overlay -->
    <div class="profile-overlay" id="profileOverlay"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>SKY Bulk Email Sender</h3>
        </div>
        <div class="user-greeting">
            <p>Hi, <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
        </div>
        <div class="sidebar-menu">
            <!-- Main Menu -->
            <div class="menu-section">
                <div class="menu-section-title">Main</div>
                <a href="dashboard.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="email_lists.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'email_lists.php' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i>
                    <span>Email Lists</span>
                </a>
                <a href="history.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'history.php' ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i>
                    <span>History</span>
                </a>
                <a href="email_drafts.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'email_drafts.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt"></i>
                    <span>Drafts</span>
                </a>
            </div>
        </div>
        <div class="sidebar-footer">
            <!-- Quick Actions -->
            <div class="menu-section">
                <div class="menu-section-title">Quick Actions</div>
                <a href="new_campaign.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'new_campaign.php' ? 'active' : ''; ?>">
                    <i class="fas fa-paper-plane me-2"></i>New Campaign
                </a>
                <a href="scheduled_campaigns.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'scheduled_campaigns.php' ? 'active' : ''; ?>">
                    <i class="fas fa-clock me-2"></i>Scheduled Campaigns
                </a>
                <a href="email_drafts.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'new_draft.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt"></i>
                    <span>New Draft</span>
                </a>
            </div>
            <div class="menu-item" id="profileButton">
                <i class="fas fa-user-circle"></i>
                <span>Profile</span>
            </div>
        </div>
    </div>

    <!-- Toggle Button -->
    <button class="sidebar-toggle" id="sidebarToggle">
        <i class="fas fa-chevron-left"></i>
    </button>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="content-wrapper">
            <!-- Page content will be inserted here -->
            <?php if (isset($content)) echo $content; ?>
        </div>

        <!-- Footer -->
        <footer class="footer">
            <div class="container">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> SKY Bulk Email Sender. All rights reserved.</p>
            </div>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, {
                placement: 'right',
                trigger: 'hover'
            });
        });

        // Profile Panel
        const profileButton = document.getElementById('profileButton');
        const profilePanel = document.getElementById('profilePanel');
        const profilePanelClose = document.getElementById('profilePanelClose');
        const profileOverlay = document.getElementById('profileOverlay');

        profileButton.addEventListener('click', () => {
            profilePanel.classList.add('show');
            profileOverlay.classList.add('show');
        });

        function closeProfilePanel() {
            profilePanel.classList.remove('show');
            profileOverlay.classList.remove('show');
        }

        profilePanelClose.addEventListener('click', closeProfilePanel);
        profileOverlay.addEventListener('click', closeProfilePanel);

        // Sidebar Toggle
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const menuItems = document.querySelectorAll('.menu-item');
        const sectionTitles = document.querySelectorAll('.menu-section-title');

        function updateTooltips() {
            if (sidebar.classList.contains('collapsed')) {
                // Show tooltips when sidebar is collapsed
                menuItems.forEach(item => {
                    const text = item.querySelector('span').textContent;
                    item.setAttribute('data-bs-toggle', 'tooltip');
                    item.setAttribute('data-bs-title', text);
                    new bootstrap.Tooltip(item, {
                        placement: 'right',
                        trigger: 'hover'
                    });
                });
            } else {
                // Remove tooltips when sidebar is expanded
                menuItems.forEach(item => {
                    item.removeAttribute('data-bs-toggle');
                    item.removeAttribute('data-bs-title');
                    const tooltip = bootstrap.Tooltip.getInstance(item);
                    if (tooltip) {
                        tooltip.dispose();
                    }
                });
            }
        }

        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            sidebarToggle.classList.toggle('collapsed');
            
            // Update tooltips after transition
            setTimeout(updateTooltips, 300);
        });

        // Handle dropdown in collapsed state
        const dropdowns = document.querySelectorAll('.dropdown');
        dropdowns.forEach(dropdown => {
            dropdown.addEventListener('show.bs.dropdown', (e) => {
                if (sidebar.classList.contains('collapsed')) {
                    e.preventDefault();
                    const menu = dropdown.querySelector('.dropdown-menu');
                    menu.style.position = 'fixed';
                    menu.style.left = '80px';
                    menu.style.top = 'auto';
                    menu.style.bottom = '0';
                    menu.classList.add('show');
                }
            });
        });

        // Initial tooltip setup
        updateTooltips();
    </script>
</body>
</html> 