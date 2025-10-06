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

// Get security statistics
$security_stats_query = "SELECT 
    (SELECT COUNT(*) FROM coupon_security_logs WHERE is_suspicious = 1) as suspicious_activities,
    (SELECT COUNT(*) FROM coupon_security_logs WHERE risk_level = 'critical') as critical_events,
    (SELECT COUNT(*) FROM coupon_security_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as events_24h,
    (SELECT COUNT(DISTINCT ip_address) FROM coupon_security_logs WHERE is_suspicious = 1) as suspicious_ips,
    (SELECT COUNT(*) FROM coupon_redemptions WHERE redeemed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as redemptions_24h";
$security_stats_stmt = $db->prepare($security_stats_query);
$security_stats_stmt->execute();
$security_stats = $security_stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent security events
$security_events_query = "SELECT csl.*, c.code as coupon_code, u.username
                         FROM coupon_security_logs csl
                         LEFT JOIN coupons c ON csl.coupon_id = c.id
                         LEFT JOIN users u ON csl.user_id = u.id
                         ORDER BY csl.created_at DESC
                         LIMIT 50";
$security_events_stmt = $db->prepare($security_events_query);
$security_events_stmt->execute();
$security_events = $security_events_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get suspicious IP addresses
$suspicious_ips_query = "SELECT ip_address, 
                        COUNT(*) as event_count,
                        SUM(CASE WHEN is_suspicious = 1 THEN 1 ELSE 0 END) as suspicious_count,
                        MAX(created_at) as last_activity
                        FROM coupon_security_logs 
                        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                        GROUP BY ip_address
                        HAVING suspicious_count > 0
                        ORDER BY suspicious_count DESC, event_count DESC";
$suspicious_ips_stmt = $db->prepare($suspicious_ips_query);
$suspicious_ips_stmt->execute();
$suspicious_ips = $suspicious_ips_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Coupon Security - Admin Panel';
include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Coupon Security Monitor</h1>
            </div>

            <!-- Security Statistics -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-danger shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Suspicious Activities</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $security_stats['suspicious_activities']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Critical Events</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $security_stats['critical_events']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-shield-halved fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Events (24h)</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $security_stats['events_24h']; ?></div>
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
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Redemptions (24h)</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $security_stats['redemptions_24h']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-gift fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Suspicious IP Addresses -->
            <?php if (!empty($suspicious_ips)): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-danger">Suspicious IP Addresses</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>IP Address</th>
                                    <th>Total Events</th>
                                    <th>Suspicious Events</th>
                                    <th>Last Activity</th>
                                    <th>Risk Level</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($suspicious_ips as $ip): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($ip['ip_address']); ?></code></td>
                                    <td><?php echo $ip['event_count']; ?></td>
                                    <td class="text-danger"><strong><?php echo $ip['suspicious_count']; ?></strong></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($ip['last_activity'])); ?></td>
                                    <td>
                                        <?php 
                                        $risk_percentage = ($ip['suspicious_count'] / $ip['event_count']) * 100;
                                        if ($risk_percentage >= 80): ?>
                                            <span class="badge bg-danger">Critical</span>
                                        <?php elseif ($risk_percentage >= 50): ?>
                                            <span class="badge bg-warning">High</span>
                                        <?php else: ?>
                                            <span class="badge bg-info">Medium</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-info" 
                                                onclick="viewIPDetails('<?php echo $ip['ip_address']; ?>')">
                                            View Details
                                        </button>
                                        <button class="btn btn-sm btn-danger" 
                                                onclick="blockIP('<?php echo $ip['ip_address']; ?>')">
                                            Block IP
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Security Events Log -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Security Events</h6>
                </div>
                <div class="card-body">
                    <div style="max-height: 500px; overflow-y: auto;">
                        <?php foreach ($security_events as $event): ?>
                        <div class="border-bottom pb-2 mb-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-<?php echo $event['risk_level'] === 'critical' ? 'danger' : ($event['risk_level'] === 'high' ? 'warning' : ($event['risk_level'] === 'medium' ? 'info' : 'secondary')); ?>">
                                        <?php echo ucfirst($event['risk_level']); ?>
                                    </span>
                                    <?php if ($event['is_suspicious']): ?>
                                        <span class="badge bg-danger">Suspicious</span>
                                    <?php endif; ?>
                                    <strong><?php echo ucfirst(str_replace('_', ' ', $event['action'])); ?></strong>
                                </div>
                                <small class="text-muted"><?php echo date('M j, g:i A', strtotime($event['created_at'])); ?></small>
                            </div>
                            <div class="text-muted">
                                <small>
                                    <strong>IP:</strong> <?php echo htmlspecialchars($event['ip_address']); ?> |
                                    <strong>User:</strong> <?php echo htmlspecialchars($event['username'] ?: 'Unknown'); ?> |
                                    <strong>Coupon:</strong> <?php echo htmlspecialchars($event['coupon_code'] ?: 'N/A'); ?>
                                </small>
                            </div>
                            <?php if ($event['details']): ?>
                                <div class="mt-1">
                                    <small class="text-muted">
                                        <?php 
                                        $details = json_decode($event['details'], true);
                                        if (is_array($details)) {
                                            foreach ($details as $key => $value) {
                                                if (is_array($value)) {
                                                    echo "<strong>" . ucfirst(str_replace('_', ' ', $key)) . ":</strong> " . implode(', ', $value) . " ";
                                                } else {
                                                    echo "<strong>" . ucfirst(str_replace('_', ' ', $key)) . ":</strong> " . htmlspecialchars($value) . " ";
                                                }
                                            }
                                        }
                                        ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function viewIPDetails(ipAddress) {
    // Implementation for viewing IP details
    alert('IP Details for: ' + ipAddress);
}

function blockIP(ipAddress) {
    if (confirm('Block IP address: ' + ipAddress + '?')) {
        // Implementation for blocking IP
        alert('IP blocking functionality to be implemented');
    }
}
</script>

<?php include 'includes/admin_footer.php'; ?>
