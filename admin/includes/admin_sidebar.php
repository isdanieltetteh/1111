<?php
$currentAdminPage = basename($_SERVER['PHP_SELF']);
$adNavigationPages = ['ad-revenue.php', 'ad-analytics.php', 'ad-control.php', 'ad-spaces-manager.php', 'ad-analytics.php', 'ads.php'];
?>

<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $currentAdminPage === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-gauge-high"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentAdminPage === 'sites.php' ? 'active' : ''; ?>" href="sites.php">
                    <i class="fas fa-globe"></i> Sites Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentAdminPage === 'backlink-tracking.php' ? 'active' : ''; ?>" href="backlink-tracking.php">
                    <i class="fas fa-link"></i> Backlink Tracking
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentAdminPage === 'users.php' ? 'active' : ''; ?>" href="users.php">
                    <i class="fas fa-users"></i> Users Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentAdminPage === 'create-user.php' ? 'active' : ''; ?>" href="create-user.php">
                    <i class="fas fa-user-plus"></i> Create User
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentAdminPage === 'reviews.php' ? 'active' : ''; ?>" href="reviews.php">
                    <i class="fas fa-comments"></i> Reviews Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentAdminPage === 'categories.php' ? 'active' : ''; ?>" href="categories.php">
                    <i class="fas fa-tags"></i> Categories
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentAdminPage === 'notifications.php' ? 'active' : ''; ?>" href="notifications.php">
                    <i class="fas fa-bell"></i> Notifications
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentAdminPage === 'email.php' ? 'active' : ''; ?>" href="email.php">
                    <i class="fas fa-envelope"></i> Email Campaigns
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentAdminPage === 'newsletter.php' ? 'active' : ''; ?>" href="newsletter.php">
                    <i class="fas fa-newspaper"></i> Newsletter
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentAdminPage === 'support.php' ? 'active' : ''; ?>" href="support.php">
                    <i class="fas fa-headset"></i> Support Tickets
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentAdminPage === 'badges.php' ? 'active' : ''; ?>" href="badges.php">
                    <i class="fas fa-medal"></i> Badge System
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentAdminPage === 'wallet.php' ? 'active' : ''; ?>" href="wallet.php">
                    <i class="fas fa-wallet"></i> Wallet System
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Advertising</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo $currentAdminPage === 'ad-revenue.php' ? 'active' : ''; ?>" href="ad-revenue.php">
                    <i class="fas fa-clipboard-check"></i> Campaign Approvals
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentAdminPage === 'ad-control.php' ? 'active' : ''; ?>" href="ad-control.php">
                    <i class="fas fa-sliders-h"></i> Placement Control
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentAdminPage === 'ad-spaces-manager.php' ? 'active' : ''; ?>" href="ad-spaces-manager.php">
                    <i class="fas fa-border-all"></i> Ad Spaces
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentAdminPage === 'ad-analytics.php' ? 'active' : ''; ?>" href="ad-analytics.php">
                    <i class="fas fa-chart-pie"></i> Performance Analytics
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Growth</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo $currentAdminPage === 'promotions.php' ? 'active' : ''; ?>" href="promotions.php">
                    <i class="fas fa-rocket"></i> Promotions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentAdminPage === 'coupons.php' ? 'active' : ''; ?>" href="coupons.php">
                    <i class="fas fa-ticket-simple"></i> Coupon System
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentAdminPage === 'analytics.php' ? 'active' : ''; ?>" href="analytics.php">
                    <i class="fas fa-chart-line"></i> Analytics
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentAdminPage === 'site-statistics.php' ? 'active' : ''; ?>" href="site-statistics.php">
                    <i class="fas fa-chart-bar"></i> Site Statistics
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Maintenance</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo $currentAdminPage === 'dead-links.php' ? 'active' : ''; ?>" href="dead-links.php">
                    <i class="fas fa-unlink"></i> Dead Links
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentAdminPage === 'security.php' ? 'active' : ''; ?>" href="security.php">
                    <i class="fas fa-shield-halved"></i> Security
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentAdminPage === 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Tools</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link" href="backup.php">
                    <i class="fas fa-database"></i> Database Backup
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logs.php">
                    <i class="fas fa-file-lines"></i> System Logs
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../index.php" target="_blank">
                    <i class="fas fa-arrow-up-right-from-square"></i> View Site
                </a>
            </li>
        </ul>
    </div>
</nav>
