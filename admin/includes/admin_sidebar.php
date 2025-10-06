<nav id="sidebarMenu" class="app-sidebar sidebar collapse d-lg-block">
    <div class="sidebar-inner">
        <div class="d-flex align-items-center justify-content-between mb-4 d-lg-none">
            <h6 class="mb-0 text-white text-uppercase">Menu</h6>
            <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="true" aria-label="Close navigation">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <ul class="nav flex-column gap-1">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-gauge-high"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'sites.php' ? 'active' : ''; ?>" href="sites.php">
                    <i class="fas fa-globe"></i>
                    <span>Sites Management</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'backlink-tracking.php' ? 'active' : ''; ?>" href="backlink-tracking.php">
                    <i class="fas fa-link"></i>
                    <span>Backlink Tracking</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" href="users.php">
                    <i class="fas fa-users"></i>
                    <span>Users Management</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'create-user.php' ? 'active' : ''; ?>" href="create-user.php">
                    <i class="fas fa-user-plus"></i>
                    <span>Create User</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reviews.php' ? 'active' : ''; ?>" href="reviews.php">
                    <i class="fas fa-comments"></i>
                    <span>Reviews Management</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>" href="categories.php">
                    <i class="fas fa-tags"></i>
                    <span>Categories</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>" href="notifications.php">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'email.php' ? 'active' : ''; ?>" href="email.php">
                    <i class="fas fa-envelope"></i>
                    <span>Email Campaigns</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'newsletter.php' ? 'active' : ''; ?>" href="newsletter.php">
                    <i class="fas fa-newspaper"></i>
                    <span>Newsletter</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'support.php' ? 'active' : ''; ?>" href="support.php">
                    <i class="fas fa-headset"></i>
                    <span>Support Tickets</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'badges.php' ? 'active' : ''; ?>" href="badges.php">
                    <i class="fas fa-medal"></i>
                    <span>Badge System</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'wallet.php' ? 'active' : ''; ?>" href="wallet.php">
                    <i class="fas fa-wallet"></i>
                    <span>Wallet System</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['ads.php', 'ad-revenue.php', 'ad-analytics.php', 'ad-spaces-manager.php', 'manage-ad-spaces.php']) ? 'active' : ''; ?>" href="ad-revenue.php">
                    <i class="fas fa-ad"></i>
                    <span>Ad Revenue System</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'promotions.php' ? 'active' : ''; ?>" href="promotions.php">
                    <i class="fas fa-rocket"></i>
                    <span>Promotions</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'promotions11111.php' ? 'active' : ''; ?>" href="promotions11111.php">
                    <i class="fas fa-bullhorn"></i>
                    <span>Promo Experiments</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'coupons.php' ? 'active' : ''; ?>" href="coupons.php">
                    <i class="fas fa-ticket-simple"></i>
                    <span>Coupon System</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'coupon-security.php' ? 'active' : ''; ?>" href="coupon-security.php">
                    <i class="fas fa-lock"></i>
                    <span>Coupon Security</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : ''; ?>" href="analytics.php">
                    <i class="fas fa-chart-line"></i>
                    <span>Analytics</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'site-statistics.php' ? 'active' : ''; ?>" href="site-statistics.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Site Statistics</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dead-links.php' ? 'active' : ''; ?>" href="dead-links.php">
                    <i class="fas fa-unlink"></i>
                    <span>Dead Links</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'security.php' ? 'active' : ''; ?>" href="security.php">
                    <i class="fas fa-shield-halved"></i>
                    <span>Security</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
        </ul>

        <div class="nav-section-title">Tools</div>
        <ul class="nav flex-column mb-2 gap-1">
            <li class="nav-item">
                <a class="nav-link" href="backup.php">
                    <i class="fas fa-database"></i>
                    <span>Database Backup</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logs.php">
                    <i class="fas fa-file-lines"></i>
                    <span>System Logs</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../index.php" target="_blank">
                    <i class="fas fa-arrow-up-right-from-square"></i>
                    <span>View Site</span>
                </a>
            </li>
        </ul>
    </div>
</nav>
