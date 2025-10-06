<aside id="sidebarMenu" class="admin-sidebar">
    <div class="d-flex flex-column h-100">
        <div>
            <p class="sidebar-title text-uppercase">Main Navigation</p>
            <nav class="nav flex-column nav-section">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <span class="icon-wrapper"><i class="fas fa-gauge-high"></i></span>
                    <span>Dashboard</span>
                </a>
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'sites.php' ? 'active' : ''; ?>" href="sites.php">
                    <span class="icon-wrapper"><i class="fas fa-globe"></i></span>
                    <span>Sites Management</span>
                    <?php if (!empty($stats['pending_sites'] ?? null)): ?>
                        <span class="badge ms-auto">Pending</span>
                    <?php endif; ?>
                </a>
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'backlink-tracking.php' ? 'active' : ''; ?>" href="backlink-tracking.php">
                    <span class="icon-wrapper"><i class="fas fa-link"></i></span>
                    <span>Backlink Tracking</span>
                </a>
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" href="users.php">
                    <span class="icon-wrapper"><i class="fas fa-users"></i></span>
                    <span>Users Management</span>
                </a>
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'create-user.php' ? 'active' : ''; ?>" href="create-user.php">
                    <span class="icon-wrapper"><i class="fas fa-user-plus"></i></span>
                    <span>Create User</span>
                </a>
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reviews.php' ? 'active' : ''; ?>" href="reviews.php">
                    <span class="icon-wrapper"><i class="fas fa-comments"></i></span>
                    <span>Reviews</span>
                </a>
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>" href="categories.php">
                    <span class="icon-wrapper"><i class="fas fa-tags"></i></span>
                    <span>Categories</span>
                </a>
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>" href="notifications.php">
                    <span class="icon-wrapper"><i class="fas fa-bell"></i></span>
                    <span>Notifications</span>
                </a>
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'email.php' ? 'active' : ''; ?>" href="email.php">
                    <span class="icon-wrapper"><i class="fas fa-envelope"></i></span>
                    <span>Email Campaigns</span>
                </a>
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'newsletter.php' ? 'active' : ''; ?>" href="newsletter.php">
                    <span class="icon-wrapper"><i class="fas fa-newspaper"></i></span>
                    <span>Newsletter</span>
                </a>
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'support.php' ? 'active' : ''; ?>" href="support.php">
                    <span class="icon-wrapper"><i class="fas fa-headset"></i></span>
                    <span>Support Tickets</span>
                </a>
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'badges.php' ? 'active' : ''; ?>" href="badges.php">
                    <span class="icon-wrapper"><i class="fas fa-medal"></i></span>
                    <span>Badges</span>
                </a>
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'wallet.php' ? 'active' : ''; ?>" href="wallet.php">
                    <span class="icon-wrapper"><i class="fas fa-wallet"></i></span>
                    <span>Wallet System</span>
                </a>
                <a class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['ads.php', 'ad-revenue.php', 'ad-analytics.php', 'manage-ad-spaces.php', 'ad-control.php']) ? 'active' : ''; ?>" href="ad-revenue.php">
                    <span class="icon-wrapper"><i class="fas fa-ad"></i></span>
                    <span>Ad Revenue Suite</span>
                </a>
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'promotions.php' ? 'active' : ''; ?>" href="promotions.php">
                    <span class="icon-wrapper"><i class="fas fa-rocket"></i></span>
                    <span>Promotions</span>
                </a>
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'coupons.php' ? 'active' : ''; ?>" href="coupons.php">
                    <span class="icon-wrapper"><i class="fas fa-ticket"></i></span>
                    <span>Coupons</span>
                </a>
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : ''; ?>" href="analytics.php">
                    <span class="icon-wrapper"><i class="fas fa-chart-line"></i></span>
                    <span>Analytics</span>
                </a>
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'site-statistics.php' ? 'active' : ''; ?>" href="site-statistics.php">
                    <span class="icon-wrapper"><i class="fas fa-chart-bar"></i></span>
                    <span>Site Statistics</span>
                </a>
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dead-links.php' ? 'active' : ''; ?>" href="dead-links.php">
                    <span class="icon-wrapper"><i class="fas fa-unlink"></i></span>
                    <span>Dead Links</span>
                </a>
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'security.php' ? 'active' : ''; ?>" href="security.php">
                    <span class="icon-wrapper"><i class="fas fa-shield-halved"></i></span>
                    <span>Security</span>
                </a>
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                    <span class="icon-wrapper"><i class="fas fa-sliders-h"></i></span>
                    <span>Settings</span>
                </a>
            </nav>
        </div>

        <div>
            <p class="sidebar-title text-uppercase">Tools &amp; Logs</p>
            <nav class="nav flex-column nav-section">
                <a class="nav-link" href="backup.php">
                    <span class="icon-wrapper"><i class="fas fa-database"></i></span>
                    <span>Database Backup</span>
                </a>
                <a class="nav-link" href="logs.php">
                    <span class="icon-wrapper"><i class="fas fa-file-lines"></i></span>
                    <span>System Logs</span>
                </a>
                <a class="nav-link" href="../index.php" target="_blank">
                    <span class="icon-wrapper"><i class="fas fa-arrow-up-right-from-square"></i></span>
                    <span>View Site</span>
                </a>
            </nav>
        </div>

        <div class="sidebar-footer mt-4">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="d-block fw-semibold">Status</span>
                    <small class="text-muted">System operational</small>
                </div>
                <span class="badge">v<?php echo defined('APP_VERSION') ? APP_VERSION : '1.0'; ?></span>
            </div>
        </div>
    </div>
</aside>
