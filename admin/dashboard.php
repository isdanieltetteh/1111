<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();

// Redirect if not admin
if (!$auth->isAdmin()) {
    header('Location: ../login.php');
    exit();
}

// Get dashboard statistics
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

// Get recent activity
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
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dashboard</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary">Export</button>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Sites</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_sites']); ?></div>
                                    <div class="text-xs text-gray-600">
                                        <?php if ($stats['dead_sites'] > 0): ?>
                                            <span class="text-danger"><?php echo $stats['dead_sites']; ?> dead</span>
                                        <?php else: ?>
                                            <span class="text-success">All healthy</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-globe fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Sites</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['pending_sites']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Users</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_users']); ?></div>
                                    <div class="text-xs text-gray-600">
                                        <span class="text-success"><?php echo $stats['new_users']; ?> new this month</span>
                                        <?php if ($stats['banned_users'] > 0): ?>
                                            | <span class="text-danger"><?php echo $stats['banned_users']; ?> banned</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Reviews</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_reviews']); ?></div>
                                    <div class="text-xs text-gray-600">
                                        <span class="text-info"><?php echo $stats['recent_reviews']; ?> this week</span>
                                        <?php if ($stats['flagged_reviews'] > 0): ?>
                                            | <span class="text-warning"><?php echo $stats['flagged_reviews']; ?> flagged</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-comments fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Financial Stats -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Security Events</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['security_events_24h']); ?></div>
                                    <div class="text-xs text-gray-600">Last 24 hours</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-shield-halved fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Credits</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($stats['total_credits'], 4); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Points</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_points']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-coins fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Withdrawals</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['pending_withdrawals']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-arrow-up fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Open Tickets</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['open_tickets']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-headset fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Pending Site Submissions</h6>
                            <a href="sites.php" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recent_sites)): ?>
                                <?php foreach ($recent_sites as $site): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <img src="../<?php echo htmlspecialchars($site['logo'] ?: 'assets/images/default-logo.png'); ?>" 
                                         class="rounded me-3" width="40" height="40">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($site['name']); ?></h6>
                                        <small class="text-muted">by <?php echo htmlspecialchars($site['username'] ?: 'Unknown'); ?></small>
                                    </div>
                                    <div>
                                        <a href="sites.php?action=approve&id=<?php echo $site['id']; ?>" 
                                           class="btn btn-sm btn-success me-1">Approve</a>
                                        <a href="sites.php?action=reject&id=<?php echo $site['id']; ?>" 
                                           class="btn btn-sm btn-danger">Reject</a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">No pending submissions</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 mb-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Recent Users</h6>
                            <a href="users.php" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recent_users)): ?>
                                <?php foreach ($recent_users as $user): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <img src="../<?php echo htmlspecialchars($user['avatar']); ?>" 
                                         class="rounded-circle me-3" width="40" height="40">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($user['username']); ?></h6>
                                        <small class="text-muted"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></small>
                                    </div>
                                    <div>
                                        <span class="badge bg-primary"><?php echo $user['reputation_points']; ?> pts</span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">No recent users</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <a href="sites.php" class="btn btn-outline-primary btn-block">
                                        <i class="fas fa-globe"></i> Manage Sites
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="users.php" class="btn btn-outline-success btn-block">
                                        <i class="fas fa-users"></i> Manage Users
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="reviews.php" class="btn btn-outline-info btn-block">
                                        <i class="fas fa-comments"></i> Manage Reviews
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="settings.php" class="btn btn-outline-warning btn-block">
                                        <i class="fas fa-cog"></i> Settings
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="dead-links.php" class="btn btn-outline-danger btn-block">
                                        <i class="fas fa-unlink"></i> Dead Links
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="security.php" class="btn btn-outline-dark btn-block">
                                        <i class="fas fa-shield-halved"></i> Security
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="support.php" class="btn btn-outline-primary btn-block">
                                        <i class="fas fa-headset"></i> Support
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="site-statistics.php" class="btn btn-outline-success btn-block">
                                        <i class="fas fa-chart-bar"></i> Statistics
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/admin_footer.php'; ?>
