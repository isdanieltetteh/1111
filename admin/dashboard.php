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
    (SELECT COUNT(*) FROM support_tickets WHERE status = 'open') as open_tickets,
    (SELECT COUNT(*) FROM secure_visit_tokens WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as visits_last_7_days";

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

$active_sites = max((int)$stats['total_sites'] - ((int)$stats['scam_sites'] + (int)$stats['dead_sites']), 0);
$inactive_sites = (int)$stats['pending_sites'] + (int)$stats['reported_sites'] + (int)$stats['scam_sites'] + (int)$stats['dead_sites'];
$site_status_total = $active_sites + $inactive_sites;
$active_site_percentage = $site_status_total > 0 ? round(($active_sites / $site_status_total) * 100, 1) : 0;
$inactive_site_percentage = $site_status_total > 0 ? round(($inactive_sites / $site_status_total) * 100, 1) : 0;

$system_health = [
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown Stack',
    'db_status' => 'Online',
    'memory_usage' => memory_get_usage(true),
    'memory_peak' => memory_get_peak_usage(true),
    'load_average' => function_exists('sys_getloadavg') ? sys_getloadavg() : null,
];

try {
    $db->query('SELECT 1');
} catch (Exception $exception) {
    $system_health['db_status'] = 'Degraded';
    $system_health['db_error'] = $exception->getMessage();
}

$uptime_file = @file_get_contents('/proc/uptime');
$uptime_seconds = null;
if ($uptime_file !== false) {
    $uptime_parts = explode(' ', trim($uptime_file));
    if (!empty($uptime_parts[0])) {
        $uptime_seconds = (int) floor((float) $uptime_parts[0]);
    }
}

if (!function_exists('adminFormatInterval')) {
    function adminFormatInterval(int $seconds): string
    {
        if ($seconds <= 0) {
            return 'N/A';
        }

        $days = intdiv($seconds, 86400);
        $seconds %= 86400;
        $hours = intdiv($seconds, 3600);
        $seconds %= 3600;
        $minutes = intdiv($seconds, 60);
        $seconds %= 60;

        $parts = [];
        if ($days > 0) {
            $parts[] = $days . 'd';
        }
        if ($hours > 0) {
            $parts[] = $hours . 'h';
        }
        if ($minutes > 0) {
            $parts[] = $minutes . 'm';
        }
        if ($seconds > 0 && empty($parts)) {
            $parts[] = $seconds . 's';
        }

        return implode(' ', $parts);
    }
}

$system_health['uptime'] = $uptime_seconds ? adminFormatInterval($uptime_seconds) : 'Unavailable';
$system_health['memory_usage_human'] = number_format($system_health['memory_usage'] / (1024 * 1024), 1) . ' MB';
$system_health['memory_peak_human'] = number_format($system_health['memory_peak'] / (1024 * 1024), 1) . ' MB';
$system_health['load_average_human'] = $system_health['load_average'] ? number_format((float) $system_health['load_average'][0], 2) : 'N/A';

$security_logs_stmt = $db->prepare("SELECT action, risk_level, message, created_at FROM security_logs ORDER BY created_at DESC LIMIT 6");
$security_logs_stmt->execute();
$security_logs = $security_logs_stmt->fetchAll(PDO::FETCH_ASSOC);

$traffic_labels = [];
$traffic_values = [];
for ($i = 29; $i >= 0; $i--) {
    $traffic_labels[] = date('M j', strtotime("-$i days"));
    $traffic_values[] = 0;
}

$site_status_breakdown = [
    'Active' => $active_sites,
    'Pending' => (int)$stats['pending_sites'],
    'Reported' => (int)$stats['reported_sites'],
    'Scam' => (int)$stats['scam_sites'],
    'Dead' => (int)$stats['dead_sites'],
];

$visitor_country_labels = [];
$visitor_country_values = [];
$visitor_country_labels_json = !empty($visitor_country_labels) ? $visitor_country_labels : ['Awaiting Data'];
$visitor_country_values_json = !empty($visitor_country_values) ? $visitor_country_values : [1];
$has_visitor_data = !empty($visitor_country_values);

$page_title = 'Admin Dashboard - ' . SITE_NAME;
include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row g-0">
        <?php include 'includes/admin_sidebar.php'; ?>

        <main class="main-content-shell col-12 col-xl-10 ms-auto">
            <?php if (!empty($_GET['q'])): ?>
                <div class="glass-card page-alert p-4 mb-4 fade-in">
                    <div class="d-flex align-items-start gap-3">
                        <span class="alert-icon text-primary"><i class="fas fa-magnifying-glass"></i></span>
                        <div>
                            <h6 class="text-uppercase text-muted fw-bold small mb-1">Command Search</h6>
                            <p class="mb-0 text-muted">No direct matches for "<strong><?php echo htmlspecialchars($_GET['q']); ?></strong>" yet. Use the navigation to access the correct module.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="page-hero glass-card p-4 p-xl-5 mb-4 fade-in">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                    <div>
                        <h1 class="page-title mb-2">Mission Control</h1>
                        <p class="page-subtitle mb-0">
                            Monitoring <?php echo number_format($stats['total_sites']); ?> verified sites and <?php echo number_format($stats['total_users']); ?> explorers across the network.
                        </p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="analytics.php" class="btn btn-primary px-4">
                            <i class="fas fa-chart-line me-2"></i>Open Analytics
                        </a>
                        <a href="logs.php" class="btn btn-outline-light px-4">
                            <i class="fas fa-list-check me-2"></i>System Logs
                        </a>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-3 mt-4">
                    <span class="badge badge-soft text-primary">
                        <i class="fas fa-shield-halved me-2"></i>
                        <span data-stat="security_events_24h"><?php echo number_format($stats['security_events_24h']); ?></span> security events (24h)
                    </span>
                    <span class="badge badge-soft text-success">
                        <i class="fas fa-bullhorn me-2"></i>
                        <span data-stat="active_ads"><?php echo number_format($stats['active_ads']); ?></span> active ad campaigns
                    </span>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-12 col-sm-6 col-xxl-3">
                    <div class="glass-card p-4 h-100 fade-in">
                        <div class="d-flex align-items-start justify-content-between">
                            <div>
                                <span class="metric-label">Total Sites</span>
                                <div class="metric-value" data-stat="total_sites"><?php echo number_format($stats['total_sites']); ?></div>
                            </div>
                            <div class="metric-icon primary">
                                <i class="fas fa-globe"></i>
                            </div>
                        </div>
                        <div class="metric-trend mt-3 <?php echo $stats['dead_sites'] > 0 ? 'text-warning' : 'text-success'; ?>">
                            <?php if ($stats['dead_sites'] > 0): ?>
                                <?php echo number_format($stats['dead_sites']); ?> offline alerts to review.
                            <?php else: ?>
                                Infrastructure healthy.
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xxl-3">
                    <div class="glass-card p-4 h-100 fade-in">
                        <div class="d-flex align-items-start justify-content-between">
                            <div>
                                <span class="metric-label">Total Users</span>
                                <div class="metric-value" data-stat="total_users"><?php echo number_format($stats['total_users']); ?></div>
                            </div>
                            <div class="metric-icon success">
                                <i class="fas fa-user-astronaut"></i>
                            </div>
                        </div>
                        <div class="metric-trend mt-3 text-success">
                            +<?php echo number_format($stats['new_users']); ?> new this month
                        </div>
                        <?php if ($stats['banned_users'] > 0): ?>
                            <p class="metric-footnote text-danger mb-0"><?php echo number_format($stats['banned_users']); ?> accounts locked.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xxl-3">
                    <div class="glass-card p-4 h-100 fade-in">
                        <div class="d-flex align-items-start justify-content-between">
                            <div>
                                <span class="metric-label">Total Reviews</span>
                                <div class="metric-value" data-stat="total_reviews"><?php echo number_format($stats['total_reviews']); ?></div>
                            </div>
                            <div class="metric-icon info">
                                <i class="fas fa-comments"></i>
                            </div>
                        </div>
                        <div class="metric-trend mt-3 text-info">
                            <?php echo number_format($stats['recent_reviews']); ?> new this week
                        </div>
                        <?php if ($stats['flagged_reviews'] > 0): ?>
                            <p class="metric-footnote text-warning mb-0"><?php echo number_format($stats['flagged_reviews']); ?> flagged for review.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xxl-3">
                    <div class="glass-card p-4 h-100 fade-in">
                        <div class="d-flex align-items-start justify-content-between">
                            <div>
                                <span class="metric-label">Visits (7 Days)</span>
                                <div class="metric-value" data-stat="visits_last_7_days"><?php echo number_format($stats['visits_last_7_days']); ?></div>
                            </div>
                            <div class="metric-icon warning">
                                <i class="fas fa-signal"></i>
                            </div>
                        </div>
                        <div class="metric-trend mt-3 text-muted">
                            Awaiting live telemetry rollout.
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mt-1">
                <div class="col-12 col-xl-4">
                    <div class="glass-card p-4 h-100 fade-in">
                        <div class="section-title mb-3">Active vs Inactive Sites</div>
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <span class="metric-label text-uppercase">Active</span>
                                <div class="metric-value fs-3 mb-0"><?php echo number_format($active_sites); ?></div>
                            </div>
                            <div class="text-end">
                                <span class="badge badge-soft text-warning">Inactive <?php echo number_format($inactive_sites); ?></span>
                            </div>
                        </div>
                        <div class="progress progress-soft mb-3">
                            <div class="progress-bar" role="progressbar" style="width: <?php echo $active_site_percentage; ?>%;" aria-valuenow="<?php echo $active_site_percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                <?php echo $active_site_percentage; ?>%
                            </div>
                        </div>
                        <ul class="mini-stats list-unstyled mb-0">
                            <?php foreach ($site_status_breakdown as $label => $value): ?>
                                <li>
                                    <span><?php echo htmlspecialchars($label); ?></span>
                                    <span class="fw-semibold"><?php echo number_format($value); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <div class="col-12 col-xl-4">
                    <div class="glass-card p-4 h-100 fade-in">
                        <div class="section-title mb-3">Treasury Snapshot</div>
                        <ul class="stat-list list-unstyled mb-0">
                            <li>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Credits in circulation</span>
                                    <span class="stat-value" data-stat="total_credits">$<?php echo number_format((float)$stats['total_credits'], 2); ?></span>
                                </div>
                            </li>
                            <li>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Loyalty points bank</span>
                                    <span class="stat-value" data-stat="total_points"><?php echo number_format((float)$stats['total_points']); ?></span>
                                </div>
                            </li>
                            <li>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Pending withdrawals</span>
                                    <span class="stat-value" data-stat="pending_withdrawals"><?php echo number_format($stats['pending_withdrawals']); ?></span>
                                </div>
                            </li>
                        </ul>
                        <div class="mt-3 small text-muted">Monitor liquidity flow to keep campaigns funded.</div>
                    </div>
                </div>
                <div class="col-12 col-xl-4">
                    <div class="glass-card p-4 h-100 fade-in">
                        <div class="section-title mb-3">Operations Backlog</div>
                        <ul class="stat-list list-unstyled mb-3">
                            <li>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Pending site approvals</span>
                                    <span class="stat-value" data-stat="pending_sites"><?php echo number_format($stats['pending_sites']); ?></span>
                                </div>
                            </li>
                            <li>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Open support tickets</span>
                                    <span class="stat-value" data-stat="open_tickets"><?php echo number_format($stats['open_tickets']); ?></span>
                                </div>
                            </li>
                            <li>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Flagged reviews</span>
                                    <span class="stat-value"><?php echo number_format($stats['flagged_reviews']); ?></span>
                                </div>
                            </li>
                            <li>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Active campaigns</span>
                                    <span class="stat-value" data-stat="active_ads"><?php echo number_format($stats['active_ads']); ?></span>
                                </div>
                            </li>
                        </ul>
                        <a href="support.php" class="btn btn-outline-primary w-100"><i class="fas fa-arrow-up-right-from-square me-2"></i>Review queues</a>
                    </div>
                </div>
            </div>

            <div class="row g-4 mt-1 align-items-stretch">
                <div class="col-12 col-xl-8">
                    <div class="glass-card h-100 p-4 fade-in">
                        <div class="section-title d-flex align-items-center justify-content-between mb-3">
                            <span>Traffic Pulse</span>
                            <span class="badge badge-soft text-info">Last 30 days</span>
                        </div>
                        <div class="chart-wrapper">
                            <canvas id="trafficTrendChart"></canvas>
                        </div>
                        <p class="text-muted small mt-3 mb-0">Visitor telemetry will populate once the analytics module is connected.</p>
                    </div>
                </div>
                <div class="col-12 col-xl-4">
                    <div class="glass-card h-100 p-4 fade-in">
                        <div class="section-title d-flex align-items-center justify-content-between mb-3">
                            <span>Visitors by Country</span>
                            <span class="badge badge-soft text-primary">Preview</span>
                        </div>
                        <div class="chart-wrapper">
                            <canvas id="trafficCountryChart"></canvas>
                        </div>
                        <p class="text-muted small mt-3 mb-0">Geo insights activate after the visitor tracker is deployed.</p>
                    </div>
                </div>
            </div>

            <div class="row g-4 mt-1 align-items-stretch">
                <div class="col-12 col-xxl-5">
                    <div class="glass-card h-100 p-4 fade-in">
                        <div class="section-title d-flex align-items-center justify-content-between mb-3">
                            <span>System Health</span>
                            <span class="badge badge-soft <?php echo $system_health['db_status'] === 'Online' ? 'text-success' : 'text-danger'; ?>"><?php echo htmlspecialchars($system_health['db_status']); ?></span>
                        </div>
                        <ul class="system-health-list list-unstyled mb-0">
                            <li><span><i class="fas fa-code me-2 text-primary"></i>PHP Version</span><span><?php echo htmlspecialchars($system_health['php_version']); ?></span></li>
                            <li><span><i class="fas fa-server me-2 text-info"></i>Server Stack</span><span><?php echo htmlspecialchars($system_health['server_software']); ?></span></li>
                            <li><span><i class="fas fa-tachometer-alt me-2 text-success"></i>Load Average</span><span><?php echo htmlspecialchars($system_health['load_average_human']); ?></span></li>
                            <li><span><i class="fas fa-clock me-2 text-warning"></i>Uptime</span><span><?php echo htmlspecialchars($system_health['uptime']); ?></span></li>
                            <li><span><i class="fas fa-memory me-2 text-danger"></i>Memory Usage</span><span><?php echo htmlspecialchars($system_health['memory_usage_human']); ?></span></li>
                            <li><span><i class="fas fa-chart-area me-2 text-secondary"></i>Peak Memory</span><span><?php echo htmlspecialchars($system_health['memory_peak_human']); ?></span></li>
                        </ul>
                        <?php if (!empty($system_health['db_error'])): ?>
                            <p class="text-danger small mt-3 mb-0">Database warning: <?php echo htmlspecialchars($system_health['db_error']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-12 col-xxl-7">
                    <div class="glass-card h-100 p-4 fade-in">
                        <div class="section-title d-flex align-items-center justify-content-between mb-3">
                            <span>Recent Security Alerts</span>
                            <a href="security.php" class="btn btn-sm btn-outline-light">View console</a>
                        </div>
                        <?php if (!empty($security_logs)): ?>
                            <div class="activity-feed">
                                <?php foreach ($security_logs as $log): ?>
                                    <?php $risk_level = strtolower($log['risk_level'] ?? 'info'); ?>
                                    <div class="activity-item">
                                        <div class="d-flex align-items-start gap-3">
                                            <span class="activity-icon risk-<?php echo htmlspecialchars($risk_level); ?>">
                                                <i class="fas fa-radar"></i>
                                            </span>
                                            <div>
                                                <div class="fw-semibold text-capitalize"><?php echo htmlspecialchars(str_replace('_', ' ', $log['action'])); ?></div>
                                                <p class="activity-message mb-1"><?php echo htmlspecialchars($log['message'] ?: 'No additional context provided.'); ?></p>
                                                <span class="text-muted small"><?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?> • <span class="text-uppercase"><?php echo htmlspecialchars($risk_level); ?></span></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">All clear. No recent security events logged.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="row g-4 mt-1">
                <div class="col-12 col-xl-6">
                    <div class="glass-card h-100 p-4 fade-in">
                        <div class="section-title d-flex align-items-center justify-content-between mb-3">
                            <span>Pending Site Submissions</span>
                            <a href="sites.php" class="btn btn-sm btn-primary">View all</a>
                        </div>
                        <?php if (!empty($recent_sites)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_sites as $site): ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex align-items-center gap-3 flex-wrap">
                                            <img src="../<?php echo htmlspecialchars($site['logo'] ?: 'assets/images/default-logo.png'); ?>" class="avatar-square" alt="<?php echo htmlspecialchars($site['name']); ?>">
                                            <div class="flex-grow-1" style="min-width: 0;">
                                                <div class="fw-semibold text-truncate"><?php echo htmlspecialchars($site['name']); ?></div>
                                                <div class="text-muted small">Submitted by <?php echo htmlspecialchars($site['username'] ?: 'Unknown'); ?> • <?php echo date('M j, Y', strtotime($site['created_at'])); ?></div>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <a href="sites.php?action=approve&id=<?php echo $site['id']; ?>" class="btn btn-sm btn-success">Approve</a>
                                                <a href="sites.php?action=reject&id=<?php echo $site['id']; ?>" class="btn btn-sm btn-danger">Reject</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">All caught up. No pending submissions.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-12 col-xl-6">
                    <div class="glass-card h-100 p-4 fade-in">
                        <div class="section-title d-flex align-items-center justify-content-between mb-3">
                            <span>Newest Explorers</span>
                            <a href="users.php" class="btn btn-sm btn-outline-light">Manage users</a>
                        </div>
                        <?php if (!empty($recent_users)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_users as $user): ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex align-items-center gap-3 flex-wrap">
                                            <img src="../<?php echo htmlspecialchars($user['avatar']); ?>" class="avatar-circle" alt="<?php echo htmlspecialchars($user['username']); ?>">
                                            <div class="flex-grow-1" style="min-width: 0;">
                                                <div class="fw-semibold text-truncate"><?php echo htmlspecialchars($user['username']); ?></div>
                                                <div class="text-muted small">Joined <?php echo date('M j, Y', strtotime($user['created_at'])); ?></div>
                                            </div>
                                            <span class="badge badge-soft text-primary"><?php echo number_format($user['reputation_points']); ?> pts</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">No new registrations in the last 24 hours.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="glass-card p-4 mt-4 fade-in">
                <div class="section-title mb-3">Quick Actions</div>
                <div class="row g-3">
                    <div class="col-12 col-sm-6 col-lg-3">
                        <a href="sites.php" class="action-tile glass-card d-flex align-items-center gap-3 p-3 h-100 text-decoration-none">
                            <span class="action-icon text-primary"><i class="fas fa-globe"></i></span>
                            <span class="action-label">Manage Sites</span>
                        </a>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <a href="users.php" class="action-tile glass-card d-flex align-items-center gap-3 p-3 h-100 text-decoration-none">
                            <span class="action-icon text-success"><i class="fas fa-users"></i></span>
                            <span class="action-label">Manage Users</span>
                        </a>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <a href="reviews.php" class="action-tile glass-card d-flex align-items-center gap-3 p-3 h-100 text-decoration-none">
                            <span class="action-icon text-info"><i class="fas fa-comments"></i></span>
                            <span class="action-label">Manage Reviews</span>
                        </a>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <a href="settings.php" class="action-tile glass-card d-flex align-items-center gap-3 p-3 h-100 text-decoration-none">
                            <span class="action-icon text-warning"><i class="fas fa-cog"></i></span>
                            <span class="action-label">Settings</span>
                        </a>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <a href="dead-links.php" class="action-tile glass-card d-flex align-items-center gap-3 p-3 h-100 text-decoration-none">
                            <span class="action-icon text-danger"><i class="fas fa-unlink"></i></span>
                            <span class="action-label">Dead Links</span>
                        </a>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <a href="security.php" class="action-tile glass-card d-flex align-items-center gap-3 p-3 h-100 text-decoration-none">
                            <span class="action-icon text-danger"><i class="fas fa-shield-halved"></i></span>
                            <span class="action-label">Security Center</span>
                        </a>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <a href="support.php" class="action-tile glass-card d-flex align-items-center gap-3 p-3 h-100 text-decoration-none">
                            <span class="action-icon text-primary"><i class="fas fa-headset"></i></span>
                            <span class="action-label">Support Desk</span>
                        </a>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <a href="site-statistics.php" class="action-tile glass-card d-flex align-items-center gap-3 p-3 h-100 text-decoration-none">
                            <span class="action-icon text-success"><i class="fas fa-chart-bar"></i></span>
                            <span class="action-label">Site Statistics</span>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const trafficCtx = document.getElementById('trafficTrendChart');
        if (trafficCtx) {
            new Chart(trafficCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($traffic_labels); ?>,
                    datasets: [{
                        label: 'Daily Visits',
                        data: <?php echo json_encode($traffic_values); ?>,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99, 102, 241, 0.18)',
                        tension: 0.4,
                        fill: true,
                        borderWidth: 3,
                        pointRadius: 3,
                        pointBackgroundColor: '#6366f1',
                        pointBorderColor: '#ffffff',
                        pointHoverRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.85)',
                            borderColor: 'rgba(255, 255, 255, 0.08)',
                            borderWidth: 1,
                            padding: 12,
                            titleFont: { family: 'Inter', weight: '600' },
                            bodyFont: { family: 'Inter' }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: '#94a3b8', maxRotation: 0 }
                        },
                        y: {
                            grid: { color: 'rgba(148, 163, 184, 0.18)', borderDash: [6, 6] },
                            ticks: { color: '#94a3b8', precision: 0 }
                        }
                    }
                }
            });
        }

        const countryCtx = document.getElementById('trafficCountryChart');
        if (countryCtx) {
            const visitorHasData = <?php echo $has_visitor_data ? 'true' : 'false'; ?>;
            new Chart(countryCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($visitor_country_labels_json); ?>,
                    datasets: [{
                        data: <?php echo json_encode($visitor_country_values_json); ?>,
                        backgroundColor: ['#6366f1', '#8b5cf6', '#22d3ee', '#38bdf8', '#f97316', '#f59e0b', '#22c55e'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { color: '#94a3b8', boxWidth: 12, usePointStyle: true }
                        },
                        tooltip: {
                            enabled: visitorHasData,
                            backgroundColor: 'rgba(15, 23, 42, 0.85)',
                            borderColor: 'rgba(255, 255, 255, 0.08)',
                            borderWidth: 1,
                            titleFont: { family: 'Inter', weight: '600' },
                            bodyFont: { family: 'Inter' }
                        }
                    }
                }
            });
        }
    });
</script>

<?php include 'includes/admin_footer.php'; ?>
