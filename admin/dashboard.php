<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();

if (!$auth->isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$stats_query = "SELECT
    (SELECT COUNT(*) FROM sites WHERE is_approved = 1) as total_sites,
    (SELECT COUNT(*) FROM sites WHERE is_approved = 0) as pending_sites,
    (SELECT COUNT(*) FROM sites WHERE status = 'scam_reported') as reported_sites,
    (SELECT COUNT(*) FROM sites WHERE status = 'scam') as scam_sites,
    (SELECT COUNT(*) FROM sites WHERE is_dead = TRUE AND admin_approved_dead = FALSE) as dead_sites,
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as new_users,
    (SELECT COUNT(*) FROM users WHERE is_banned = 1) as banned_users,
    (SELECT COUNT(*) FROM reviews) as total_reviews,
    (SELECT COUNT(*) FROM reviews WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as recent_reviews,
    (SELECT COUNT(*) FROM reviews WHERE is_scam_report = 1) as flagged_reviews,
    (SELECT SUM(credits) FROM users) as total_credits,
    (SELECT SUM(points_balance) FROM user_wallets) as total_points,
    (SELECT COUNT(*) FROM withdrawal_requests WHERE status = 'pending') as pending_withdrawals,
    (SELECT COUNT(*) FROM redirect_ads WHERE is_active = 1) as active_ads,
    (SELECT COUNT(*) FROM security_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as security_events_24h,
    (SELECT COUNT(*) FROM support_tickets WHERE status = 'open') as open_tickets";

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$recent_sites_query = "SELECT s.*, u.username FROM sites s
                       LEFT JOIN users u ON s.submitted_by = u.id
                       WHERE s.is_approved = 0
                       ORDER BY s.created_at DESC LIMIT 5";
$recent_sites_stmt = $db->prepare($recent_sites_query);
$recent_sites_stmt->execute();
$recent_sites = $recent_sites_stmt->fetchAll(PDO::FETCH_ASSOC);

$recent_users_query = "SELECT * FROM users ORDER BY created_at DESC LIMIT 5";
$recent_users_stmt = $db->prepare($recent_users_query);
$recent_users_stmt->execute();
$recent_users = $recent_users_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Admin Dashboard - ' . SITE_NAME;
include 'includes/admin_header.php';
include 'includes/admin_sidebar.php';
?>

<main class="admin-main">
    <div class="admin-page-header">
        <div>
            <div class="admin-breadcrumb">
                <i class="fas fa-gauge-high text-primary"></i>
                <span>Overview</span>
                <span class="text-muted">Command Center</span>
            </div>
            <h1>Mission Control</h1>
            <p class="text-muted mb-0">Monitor submissions, users, and revenue engines powering <?php echo SITE_NAME; ?>.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="../index.php" target="_blank" class="btn btn-light btn-sm shadow-hover">
                <i class="fas fa-arrow-up-right-from-square me-2"></i>Live Preview
            </a>
            <a href="backup.php" class="btn btn-primary btn-sm shadow-hover">
                <i class="fas fa-database me-2"></i>Quick Backup
            </a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="admin-metric-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="metric-label">Total Sites</p>
                        <p class="metric-value mb-1" data-stat="total_sites"><?php echo number_format($stats['total_sites']); ?></p>
                        <span class="metric-trend <?php echo ($stats['dead_sites'] > 0 || $stats['scam_sites'] > 0) ? 'down' : 'up'; ?>">
                            <i class="fas fa-<?php echo ($stats['dead_sites'] > 0 || $stats['scam_sites'] > 0) ? 'triangle-exclamation' : 'arrow-trend-up'; ?>"></i>
                            <?php if ($stats['dead_sites'] > 0): ?>
                                <?php echo $stats['dead_sites']; ?> dead links to review
                            <?php elseif ($stats['scam_sites'] > 0): ?>
                                <?php echo $stats['scam_sites']; ?> flagged scams
                            <?php else: ?>
                                Healthy ecosystem
                            <?php endif; ?>
                        </span>
                    </div>
                    <span class="icon-wrapper"><i class="fas fa-globe"></i></span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="admin-metric-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="metric-label">Pending Approvals</p>
                        <p class="metric-value mb-1" data-stat="pending_sites"><?php echo number_format($stats['pending_sites']); ?></p>
                        <span class="metric-trend <?php echo $stats['reported_sites'] > 0 ? 'down' : 'up'; ?>">
                            <i class="fas fa-<?php echo $stats['reported_sites'] > 0 ? 'shield-halved' : 'hourglass-half'; ?>"></i>
                            <?php echo $stats['reported_sites'] > 0 ? $stats['reported_sites'] . ' reports awaiting action' : 'Keep reviews flowing'; ?>
                        </span>
                    </div>
                    <span class="icon-wrapper"><i class="fas fa-inbox"></i></span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="admin-metric-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="metric-label">Total Users</p>
                        <p class="metric-value mb-1" data-stat="total_users"><?php echo number_format($stats['total_users']); ?></p>
                        <span class="metric-trend up">
                            <i class="fas fa-user-plus"></i>
                            <?php echo number_format($stats['new_users']); ?> joined last 30 days
                        </span>
                    </div>
                    <span class="icon-wrapper"><i class="fas fa-users"></i></span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="admin-metric-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="metric-label">Reviews Logged</p>
                        <p class="metric-value mb-1" data-stat="total_reviews"><?php echo number_format($stats['total_reviews']); ?></p>
                        <span class="metric-trend <?php echo $stats['flagged_reviews'] > 0 ? 'down' : 'up'; ?>">
                            <i class="fas fa-comments"></i>
                            <?php if ($stats['flagged_reviews'] > 0): ?>
                                <?php echo $stats['flagged_reviews']; ?> flagged for follow-up
                            <?php else: ?>
                                <?php echo number_format($stats['recent_reviews']); ?> this week
                            <?php endif; ?>
                        </span>
                    </div>
                    <span class="icon-wrapper"><i class="fas fa-comment-dots"></i></span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="admin-metric-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="metric-label">Security Events (24h)</p>
                        <p class="metric-value mb-1" data-stat="security_events_24h"><?php echo number_format($stats['security_events_24h']); ?></p>
                        <span class="metric-trend <?php echo $stats['security_events_24h'] > 0 ? 'down' : 'up'; ?>">
                            <i class="fas fa-shield"></i>
                            <?php echo $stats['security_events_24h'] > 0 ? 'Review latest alerts' : 'No alerts triggered'; ?>
                        </span>
                    </div>
                    <span class="icon-wrapper"><i class="fas fa-lock"></i></span>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="admin-metric-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="metric-label">Credits in Circulation</p>
                        <p class="metric-value mb-1" data-stat="total_credits">$<?php echo number_format($stats['total_credits'], 4); ?></p>
                        <span class="metric-trend up">
                            <i class="fas fa-coins"></i>
                            Monetization engine stable
                        </span>
                    </div>
                    <span class="icon-wrapper"><i class="fas fa-dollar-sign"></i></span>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="admin-metric-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="metric-label">Points Treasury</p>
                        <p class="metric-value mb-1" data-stat="total_points"><?php echo number_format($stats['total_points']); ?></p>
                        <span class="metric-trend up">
                            <i class="fas fa-trophy"></i>
                            Powering loyalty rewards
                        </span>
                    </div>
                    <span class="icon-wrapper"><i class="fas fa-gem"></i></span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="admin-content-wrapper h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="mb-1">Pending Site Submissions</h5>
                        <p class="text-muted mb-0">Approve or reject the freshest community drops.</p>
                    </div>
                    <a href="sites.php" class="btn btn-sm btn-primary shadow-hover">View All</a>
                </div>
                <?php if (!empty($recent_sites)): ?>
                    <div class="scroll-shadow" style="max-height: 320px;">
                        <?php foreach ($recent_sites as $site): ?>
                            <div class="d-flex align-items-center justify-content-between py-3 border-bottom border-light-subtle">
                                <div class="d-flex align-items-center gap-3">
                                    <img src="../<?php echo htmlspecialchars($site['logo'] ?: 'assets/images/default-logo.png'); ?>" class="rounded shadow" width="48" height="48" alt="<?php echo htmlspecialchars($site['name']); ?>">
                                    <div>
                                        <h6 class="mb-0 fw-semibold"><?php echo htmlspecialchars($site['name']); ?></h6>
                                        <small class="text-muted">Submitted by <?php echo htmlspecialchars($site['username'] ?: 'Unknown'); ?></small>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="sites.php?action=approve&id=<?php echo $site['id']; ?>" class="btn btn-success btn-sm shadow-hover">Approve</a>
                                    <a href="sites.php?action=reject&id=<?php echo $site['id']; ?>" class="btn btn-outline-danger btn-sm">Reject</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">No pending submissions</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="admin-content-wrapper h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="mb-1">Newest Agents</h5>
                        <p class="text-muted mb-0">Celebrate our latest community members.</p>
                    </div>
                    <a href="users.php" class="btn btn-sm btn-primary shadow-hover">View All</a>
                </div>
                <?php if (!empty($recent_users)): ?>
                    <div class="scroll-shadow" style="max-height: 320px;">
                        <?php foreach ($recent_users as $user): ?>
                            <div class="d-flex align-items-center justify-content-between py-3 border-bottom border-light-subtle">
                                <div class="d-flex align-items-center gap-3">
                                    <img src="../<?php echo htmlspecialchars($user['avatar']); ?>" class="rounded-circle border" width="48" height="48" alt="<?php echo htmlspecialchars($user['username']); ?>">
                                    <div>
                                        <h6 class="mb-0 fw-semibold"><?php echo htmlspecialchars($user['username']); ?></h6>
                                        <small class="text-muted">Joined <?php echo date('M j, Y', strtotime($user['created_at'])); ?></small>
                                    </div>
                                </div>
                                <span class="badge-soft"><i class="fas fa-star me-1 text-warning"></i><?php echo $user['reputation_points']; ?> pts</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">No recent users</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="admin-content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="mb-1">Quick Actions</h5>
                <p class="text-muted mb-0">Warp to high-impact areas of the control room.</p>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-md-3 col-sm-6">
                <a href="sites.php" class="btn btn-outline-primary w-100 shadow-hover"><i class="fas fa-globe me-2"></i>Manage Sites</a>
            </div>
            <div class="col-md-3 col-sm-6">
                <a href="users.php" class="btn btn-outline-success w-100 shadow-hover"><i class="fas fa-users me-2"></i>Manage Users</a>
            </div>
            <div class="col-md-3 col-sm-6">
                <a href="reviews.php" class="btn btn-outline-info w-100 shadow-hover"><i class="fas fa-comments me-2"></i>Manage Reviews</a>
            </div>
            <div class="col-md-3 col-sm-6">
                <a href="settings.php" class="btn btn-outline-warning w-100 shadow-hover"><i class="fas fa-sliders-h me-2"></i>Settings</a>
            </div>
            <div class="col-md-3 col-sm-6">
                <a href="dead-links.php" class="btn btn-outline-danger w-100 shadow-hover"><i class="fas fa-unlink me-2"></i>Dead Links</a>
            </div>
            <div class="col-md-3 col-sm-6">
                <a href="security.php" class="btn btn-outline-dark w-100 shadow-hover"><i class="fas fa-shield-halved me-2"></i>Security</a>
            </div>
            <div class="col-md-3 col-sm-6">
                <a href="support.php" class="btn btn-outline-primary w-100 shadow-hover"><i class="fas fa-headset me-2"></i>Support</a>
            </div>
            <div class="col-md-3 col-sm-6">
                <a href="site-statistics.php" class="btn btn-outline-success w-100 shadow-hover"><i class="fas fa-chart-bar me-2"></i>Statistics</a>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/admin_footer.php'; ?>
