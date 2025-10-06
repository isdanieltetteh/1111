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

if (!function_exists('admin_format_bytes')) {
    function admin_format_bytes($bytes)
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int)floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), 1) . ' ' . $units[$power];
    }
}

if (!function_exists('admin_format_duration')) {
    function admin_format_duration($seconds)
    {
        if ($seconds <= 0) {
            return '0s';
        }

        $periods = [
            'd' => 86400,
            'h' => 3600,
            'm' => 60,
            's' => 1,
        ];

        $segments = [];
        foreach ($periods as $suffix => $length) {
            if ($seconds >= $length) {
                $value = floor($seconds / $length);
                $seconds -= $value * $length;
                $segments[] = $value . $suffix;
            }

            if (count($segments) === 2) {
                break;
            }
        }

        return implode(' ', $segments);
    }
}

$systemHealth = [
    'php_version' => phpversion(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown Server',
    'mysql_status' => 'Online',
    'mysql_version' => 'N/A',
    'mysql_uptime_seconds' => 0,
    'memory_usage' => admin_format_bytes(memory_get_usage(true)),
    'memory_peak' => admin_format_bytes(memory_get_peak_usage(true)),
    'load_average' => null,
    'disk_free' => null,
    'disk_total' => null,
];

try {
    $systemHealth['mysql_version'] = $db->getAttribute(PDO::ATTR_SERVER_VERSION);

    $uptime_stmt = $db->query("SHOW STATUS LIKE 'Uptime'");
    $uptime_row = $uptime_stmt ? $uptime_stmt->fetch(PDO::FETCH_ASSOC) : null;
    if ($uptime_row && isset($uptime_row['Value'])) {
        $systemHealth['mysql_uptime_seconds'] = (int)$uptime_row['Value'];
    }
} catch (PDOException $exception) {
    $systemHealth['mysql_status'] = 'Offline';
}

if (function_exists('sys_getloadavg')) {
    $load = sys_getloadavg();
    if (is_array($load)) {
        $systemHealth['load_average'] = array_map(function ($value) {
            return number_format((float)$value, 2);
        }, $load);
    }
}

$diskRoot = dirname(__DIR__);
$disk_total = @disk_total_space($diskRoot);
$disk_free = @disk_free_space($diskRoot);
if ($disk_total !== false && $disk_free !== false) {
    $systemHealth['disk_total'] = admin_format_bytes($disk_total);
    $systemHealth['disk_free'] = admin_format_bytes($disk_free);
}

$healthAlerts = [];
if ((int)$stats['security_events_24h'] > 0) {
    $healthAlerts[] = [
        'label' => 'Security Watch',
        'level' => 'alert',
        'message' => number_format($stats['security_events_24h']) . ' security events logged in the last 24h.',
    ];
} else {
    $healthAlerts[] = [
        'label' => 'Security Watch',
        'level' => 'ok',
        'message' => 'No security anomalies detected in the last 24h.',
    ];
}

if ((int)$stats['pending_sites'] > 10) {
    $healthAlerts[] = [
        'label' => 'Site Queue',
        'level' => 'warning',
        'message' => 'High submission queue with ' . number_format($stats['pending_sites']) . ' sites awaiting review.',
    ];
} else {
    $healthAlerts[] = [
        'label' => 'Site Queue',
        'level' => 'ok',
        'message' => 'Submission review queue is under control.',
    ];
}

if ((int)$stats['pending_withdrawals'] > 0) {
    $healthAlerts[] = [
        'label' => 'Finance Desk',
        'level' => 'warning',
        'message' => number_format($stats['pending_withdrawals']) . ' withdrawal request(s) require attention.',
    ];
} else {
    $healthAlerts[] = [
        'label' => 'Finance Desk',
        'level' => 'ok',
        'message' => 'No pending withdrawal queues.',
    ];
}

if ((int)$stats['open_tickets'] > 0) {
    $healthAlerts[] = [
        'label' => 'Support Center',
        'level' => 'warning',
        'message' => number_format($stats['open_tickets']) . ' open ticket(s) waiting for response.',
    ];
} else {
    $healthAlerts[] = [
        'label' => 'Support Center',
        'level' => 'ok',
        'message' => 'Support inbox is clear.',
    ];
}

$security_logs_stmt = $db->prepare("SELECT action, risk_level, created_at, ip_address FROM security_logs ORDER BY created_at DESC LIMIT 6");
$security_logs_stmt->execute();
$recent_security_logs = $security_logs_stmt->fetchAll(PDO::FETCH_ASSOC);

$days = [];
for ($i = 13; $i >= 0; $i--) {
    $days[] = date('Y-m-d', strtotime("-$i day"));
}

$user_trend_data = array_fill_keys($days, 0);
$user_trend_stmt = $db->prepare("SELECT DATE(created_at) as day_label, COUNT(*) as total FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY DATE(created_at) ORDER BY DATE(created_at)");
$user_trend_stmt->execute();
foreach ($user_trend_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $date_key = $row['day_label'];
    if (isset($user_trend_data[$date_key])) {
        $user_trend_data[$date_key] = (int)$row['total'];
    }
}

$site_trend_data = array_fill_keys($days, 0);
$site_trend_stmt = $db->prepare("SELECT DATE(created_at) as day_label, COUNT(*) as total FROM sites WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY DATE(created_at) ORDER BY DATE(created_at)");
$site_trend_stmt->execute();
foreach ($site_trend_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $date_key = $row['day_label'];
    if (isset($site_trend_data[$date_key])) {
        $site_trend_data[$date_key] = (int)$row['total'];
    }
}

$site_status_breakdown = [
    ['label' => 'Approved', 'value' => (int)$stats['total_sites']],
    ['label' => 'Pending', 'value' => (int)$stats['pending_sites']],
    ['label' => 'Reported', 'value' => (int)$stats['reported_sites']],
    ['label' => 'Flagged Scam', 'value' => (int)$stats['scam_sites']],
];

$security_risk_levels = [
    'low' => 0,
    'medium' => 0,
    'high' => 0,
    'critical' => 0,
];

$risk_stmt = $db->prepare("SELECT risk_level, COUNT(*) as total FROM security_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY risk_level");
$risk_stmt->execute();
foreach ($risk_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $risk = $row['risk_level'];
    if (isset($security_risk_levels[$risk])) {
        $security_risk_levels[$risk] = (int)$row['total'];
    }
}

$operations_snapshot = [
    [
        'label' => 'Verified Ecosystem',
        'value' => number_format($stats['total_sites']),
        'description' => 'Approved sites currently visible in listings.',
        'icon' => 'fa-earth-americas',
    ],
    [
        'label' => 'Pending Reviews',
        'value' => number_format($stats['pending_sites']),
        'description' => 'Submissions waiting for final verdict.',
        'icon' => 'fa-hourglass-half',
    ],
    [
        'label' => 'Open Support Tickets',
        'value' => number_format($stats['open_tickets']),
        'description' => 'Customers needing assistance across channels.',
        'icon' => 'fa-headset',
    ],
    [
        'label' => 'Pending Withdrawals',
        'value' => number_format($stats['pending_withdrawals']),
        'description' => 'Cash-out requests to settle from treasury.',
        'icon' => 'fa-money-check-dollar',
    ],
];

$chartPayload = [
    'dailyUserTrend' => [
        'labels' => array_map(function ($day) {
            return date('M j', strtotime($day));
        }, $days),
        'users' => array_values($user_trend_data),
        'sites' => array_values($site_trend_data),
    ],
    'siteStatus' => [
        'labels' => array_column($site_status_breakdown, 'label'),
        'values' => array_column($site_status_breakdown, 'value'),
    ],
    'securityRisk' => [
        'labels' => array_map('ucfirst', array_keys($security_risk_levels)),
        'values' => array_values($security_risk_levels),
    ],
];

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
        <div class="col-xl-8">
            <div class="admin-content-wrapper h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="mb-1">Infrastructure Health</h5>
                        <p class="text-muted mb-0">Live vitals from the <?php echo SITE_NAME; ?> control stack.</p>
                    </div>
                    <span class="badge-soft <?php echo $systemHealth['mysql_status'] === 'Online' ? 'badge-soft-success' : 'badge-soft-danger'; ?>">
                        <i class="fas fa-database me-2"></i><?php echo htmlspecialchars($systemHealth['mysql_status']); ?>
                    </span>
                </div>
                <div class="system-health-grid">
                    <div class="system-health-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="system-health-label">PHP Runtime</span>
                                <h4 class="system-health-value mb-1"><?php echo htmlspecialchars($systemHealth['php_version']); ?></h4>
                                <span class="system-health-meta"><?php echo htmlspecialchars($systemHealth['server_software']); ?></span>
                            </div>
                            <span class="pulse-indicator success"><i class="fas fa-code"></i></span>
                        </div>
                    </div>
                    <div class="system-health-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="system-health-label">Database Engine</span>
                                <h4 class="system-health-value mb-1"><?php echo htmlspecialchars($systemHealth['mysql_version']); ?></h4>
                                <span class="system-health-meta">
                                    <?php echo $systemHealth['mysql_uptime_seconds'] ? 'Uptime ' . admin_format_duration($systemHealth['mysql_uptime_seconds']) : 'Monitoring uptime'; ?>
                                </span>
                            </div>
                            <span class="pulse-indicator <?php echo $systemHealth['mysql_status'] === 'Online' ? 'success' : 'danger'; ?>"><i class="fas fa-server"></i></span>
                        </div>
                    </div>
                    <div class="system-health-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="system-health-label">Memory Footprint</span>
                                <h4 class="system-health-value mb-1"><?php echo htmlspecialchars($systemHealth['memory_usage']); ?></h4>
                                <span class="system-health-meta">Peak <?php echo htmlspecialchars($systemHealth['memory_peak']); ?></span>
                            </div>
                            <span class="pulse-indicator info"><i class="fas fa-microchip"></i></span>
                        </div>
                    </div>
                    <div class="system-health-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="system-health-label">System Load</span>
                                <h4 class="system-health-value mb-1">
                                    <?php echo $systemHealth['load_average'] ? implode(' · ', $systemHealth['load_average']) : 'Observing'; ?>
                                </h4>
                                <span class="system-health-meta">
                                    <?php if ($systemHealth['disk_free'] && $systemHealth['disk_total']): ?>
                                        <?php echo $systemHealth['disk_free']; ?> free of <?php echo $systemHealth['disk_total']; ?>
                                    <?php else: ?>
                                        Disk telemetry ready
                                    <?php endif; ?>
                                </span>
                            </div>
                            <span class="pulse-indicator warning"><i class="fas fa-chart-line"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="admin-content-wrapper h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="mb-1">Operational Alerts</h5>
                        <p class="text-muted mb-0">Key actions that need commander attention.</p>
                    </div>
                </div>
                <div class="stacked-alerts">
                    <?php foreach ($healthAlerts as $alert): ?>
                        <div class="system-alert system-alert-<?php echo htmlspecialchars($alert['level']); ?>">
                            <span class="system-alert-label"><?php echo htmlspecialchars($alert['label']); ?></span>
                            <p class="mb-0"><?php echo htmlspecialchars($alert['message']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-4">
                    <h6 class="text-uppercase text-muted fw-semibold small mb-3">Recent Security Signals</h6>
                    <?php if (!empty($recent_security_logs)): ?>
                        <ul class="system-timeline list-unstyled mb-0">
                            <?php foreach ($recent_security_logs as $log): ?>
                                <?php
                                    $riskTone = [
                                        'low' => 'success',
                                        'medium' => 'warning',
                                        'high' => 'danger',
                                        'critical' => 'danger',
                                    ];
                                    $riskLevel = $log['risk_level'];
                                    $riskClass = $riskTone[$riskLevel] ?? 'info';
                                ?>
                                <li class="system-timeline-item">
                                    <div class="system-timeline-marker system-timeline-marker-<?php echo htmlspecialchars($riskClass); ?>"></div>
                                    <div class="system-timeline-content">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-semibold text-capitalize"><?php echo htmlspecialchars(str_replace('_', ' ', $log['action'])); ?></span>
                                            <span class="badge-soft badge-soft-<?php echo htmlspecialchars($riskClass); ?> text-uppercase small"><?php echo htmlspecialchars($riskLevel); ?></span>
                                        </div>
                                        <small class="text-muted d-block"><?php echo date('M j, H:i', strtotime($log['created_at'])); ?> · <?php echo htmlspecialchars($log['ip_address']); ?></small>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="text-muted text-center py-3">No recent security logs</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xxl-8">
            <div class="admin-content-wrapper h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="mb-1">Growth Trajectory</h5>
                        <p class="text-muted mb-0">Daily intake of new users and submissions (last 14 days).</p>
                    </div>
                </div>
                <div class="chart-wrapper">
                    <canvas id="dailyUserChart" height="280"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xxl-4">
            <div class="admin-content-wrapper h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="mb-1">Site Status Mix</h5>
                        <p class="text-muted mb-0">Distribution of ecosystem states.</p>
                    </div>
                </div>
                <div class="chart-wrapper">
                    <canvas id="siteStatusChart" height="280"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <div class="admin-content-wrapper h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="mb-1">Security Pulse</h5>
                        <p class="text-muted mb-0">Risk level breakdown (7-day window).</p>
                    </div>
                </div>
                <div class="chart-wrapper">
                    <canvas id="securityPulseChart" height="260"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="admin-content-wrapper h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="mb-1">Operations Snapshot</h5>
                        <p class="text-muted mb-0">Instant read on the busiest workstreams.</p>
                    </div>
                </div>
                <div class="operations-list">
                    <?php foreach ($operations_snapshot as $item): ?>
                        <div class="operations-item">
                            <div class="operations-icon">
                                <i class="fas <?php echo htmlspecialchars($item['icon']); ?>"></i>
                            </div>
                            <div>
                                <h6 class="mb-1 fw-semibold"><?php echo htmlspecialchars($item['label']); ?></h6>
                                <div class="operations-value"><?php echo $item['value']; ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($item['description']); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
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

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined') {
            return;
        }

        const chartPayload = <?php echo json_encode($chartPayload); ?>;
        const styles = getComputedStyle(document.documentElement);
        const palette = {
            primary: styles.getPropertyValue('--admin-primary').trim() || '#4f46e5',
            success: styles.getPropertyValue('--admin-success').trim() || '#22c55e',
            warning: styles.getPropertyValue('--admin-warning').trim() || '#f59e0b',
            danger: styles.getPropertyValue('--admin-danger').trim() || '#ef4444',
            info: styles.getPropertyValue('--admin-info').trim() || '#0ea5e9',
            border: styles.getPropertyValue('--admin-border').trim() || 'rgba(15, 23, 42, 0.1)',
            grid: styles.getPropertyValue('--admin-border').trim() || 'rgba(15, 23, 42, 0.1)',
            textMuted: styles.getPropertyValue('--admin-text-muted').trim() || '#64748b',
        };

        const applyResponsiveFont = (chart) => {
            if (!chart || !chart.options || !chart.options.scales || !chart.options.scales.x) {
                return;
            }

            const width = chart.canvas.clientWidth;
            if (width < 420 && chart.options.scales.x.ticks) {
                chart.options.scales.x.ticks.maxRotation = 0;
            }
        };

        const userCtx = document.getElementById('dailyUserChart');
        if (userCtx && chartPayload.dailyUserTrend) {
            const gradient = userCtx.getContext('2d').createLinearGradient(0, 0, 0, userCtx.height);
            gradient.addColorStop(0, palette.primary + 'CC');
            gradient.addColorStop(1, palette.primary + '10');

            new Chart(userCtx, {
                type: 'line',
                data: {
                    labels: chartPayload.dailyUserTrend.labels,
                    datasets: [
                        {
                            label: 'New Users',
                            data: chartPayload.dailyUserTrend.users,
                            borderColor: palette.primary,
                            backgroundColor: gradient,
                            tension: 0.35,
                            fill: true,
                            borderWidth: 3,
                            pointRadius: 0,
                        },
                        {
                            label: 'Site Submissions',
                            data: chartPayload.dailyUserTrend.sites,
                            borderColor: palette.success,
                            backgroundColor: palette.success,
                            borderDash: [6, 4],
                            tension: 0.35,
                            fill: false,
                            borderWidth: 2,
                            pointRadius: 0,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index',
                    },
                    plugins: {
                        legend: {
                            display: true,
                            labels: {
                                color: palette.textMuted,
                                boxWidth: 14,
                                boxHeight: 14,
                            },
                        },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            cornerRadius: 8,
                            padding: 12,
                        },
                    },
                    scales: {
                        x: {
                            grid: {
                                color: palette.grid,
                                drawBorder: false,
                            },
                            ticks: {
                                color: palette.textMuted,
                                maxTicksLimit: 7,
                            },
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: palette.grid,
                                drawBorder: false,
                            },
                            ticks: {
                                color: palette.textMuted,
                                precision: 0,
                                stepSize: 1,
                            },
                        },
                    },
                    onResize: applyResponsiveFont,
                },
            });
        }

        const statusCtx = document.getElementById('siteStatusChart');
        if (statusCtx && chartPayload.siteStatus) {
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: chartPayload.siteStatus.labels,
                    datasets: [
                        {
                            data: chartPayload.siteStatus.values,
                            backgroundColor: [
                                palette.primary,
                                palette.warning,
                                palette.info,
                                palette.danger,
                            ],
                            borderColor: palette.border,
                            borderWidth: 1,
                            hoverOffset: 6,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: palette.textMuted,
                                usePointStyle: true,
                                padding: 18,
                            },
                        },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            cornerRadius: 8,
                            padding: 12,
                            callbacks: {
                                label: function (context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const dataset = context.chart.data.datasets[0].data || [];
                                    const total = dataset.reduce((sum, item) => sum + item, 0);
                                    const percent = total ? Math.round((value / total) * 100) : 0;
                                    return `${label}: ${value} (${percent}%)`;
                                },
                            },
                        },
                    },
                    cutout: '72%',
                },
            });
        }

        const securityCtx = document.getElementById('securityPulseChart');
        if (securityCtx && chartPayload.securityRisk) {
            new Chart(securityCtx, {
                type: 'bar',
                data: {
                    labels: chartPayload.securityRisk.labels,
                    datasets: [
                        {
                            label: 'Events',
                            data: chartPayload.securityRisk.values,
                            backgroundColor: [palette.success, palette.warning, palette.danger, '#7f1d1d'],
                            borderRadius: 12,
                            maxBarThickness: 38,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false,
                        },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            cornerRadius: 8,
                            padding: 12,
                        },
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false,
                            },
                            ticks: {
                                color: palette.textMuted,
                            },
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: palette.grid,
                                drawBorder: false,
                            },
                            ticks: {
                                color: palette.textMuted,
                                precision: 0,
                                stepSize: 1,
                            },
                        },
                    },
                },
            });
        }
    });
</script>

<?php include 'includes/admin_footer.php'; ?>
