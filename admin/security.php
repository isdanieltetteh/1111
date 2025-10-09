<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();
$security = new SecurityManager($db);
// Redirect if not admin
if (!$auth->isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$success_message = '';
$error_message = '';

// Handle security actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'block_ip':
    $ip_address = trim($_POST['ip_address']);
    $reason = trim($_POST['reason']);
    $duration_hours = intval($_POST['duration_hours'] ?? 0); // Fallback to 0 if hidden/not posted
    $is_permanent = isset($_POST['is_permanent']) ? 1 : 0; // Explicit 1/0
    
    if (empty($ip_address) || empty($reason)) {
        $error_message = 'IP address and reason are required';
        break;
    }
    
    // Only use duration if not permanent; ensure positive hours
    $expires_at = ($is_permanent === 1) ? null : date('Y-m-d H:i:s', strtotime("+{$duration_hours} hours"));
    
    $block_query = "INSERT INTO blocked_ips (ip_address, reason, blocked_by, expires_at, is_permanent, created_at) 
                   VALUES (:ip_address, :reason, :admin_id, :expires_at, :is_permanent, NOW())";
    $block_stmt = $db->prepare($block_query);
    $block_stmt->bindParam(':ip_address', $ip_address);
    $block_stmt->bindParam(':reason', $reason);
    $block_stmt->bindParam(':admin_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $block_stmt->bindParam(':expires_at', $expires_at);
    $block_stmt->bindParam(':is_permanent', $is_permanent, PDO::PARAM_INT);
    
    if ($block_stmt->execute()) {
        $success_message = 'IP address blocked successfully!';
    } else {
        $error_message = 'Error blocking IP address';
    }
    break;
            
        case 'unblock_ip':
            $block_id = intval($_POST['block_id']);
            $unblock_query = "DELETE FROM blocked_ips WHERE id = :block_id";
            $unblock_stmt = $db->prepare($unblock_query);
            $unblock_stmt->bindParam(':block_id', $block_id);
            
            if ($unblock_stmt->execute()) {
                $success_message = 'IP address unblocked successfully!';
            } else {
                $error_message = 'Error unblocking IP address';
            }
            break;
            
        case 'clear_security_logs':
            $days = intval($_POST['days']);
            $clear_query = "DELETE FROM security_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
            $clear_stmt = $db->prepare($clear_query);
            $clear_stmt->bindParam(':days', $days);
            $clear_stmt->execute();
            $cleared_count = $clear_stmt->rowCount();
            
            $success_message = "Cleared {$cleared_count} old security log entries";
            break;
    }
}

// Get security statistics
$security_stats_query = "SELECT 
    (SELECT COUNT(*) FROM security_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as events_24h,
    (SELECT COUNT(*) FROM security_logs WHERE risk_level = 'high' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as high_risk_7d,
    (SELECT COUNT(*) FROM blocked_ips WHERE is_permanent = 1 OR expires_at > NOW()) as active_blocks,
    (SELECT COUNT(DISTINCT ip_address) FROM security_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as unique_ips_24h,
    (SELECT COUNT(*) FROM users WHERE last_active >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as active_users_24h,
    (SELECT COUNT(*) FROM security_logs WHERE action = 'login_failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)) as failed_logins_1h";

$security_stats_stmt = $db->prepare($security_stats_query);
$security_stats_stmt->execute();
$security_stats = $security_stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent security events
$recent_events_query = "SELECT sl.*, u.username 
                       FROM security_logs sl
                       LEFT JOIN users u ON sl.user_id = u.id
                       ORDER BY sl.created_at DESC
                       LIMIT 50";
$recent_events_stmt = $db->prepare($recent_events_query);
$recent_events_stmt->execute();
$recent_events = $recent_events_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get blocked IPs
$blocked_ips_query = "SELECT bi.*, u.username as blocked_by_username
                     FROM blocked_ips bi
                     JOIN users u ON bi.blocked_by = u.id
                     WHERE bi.is_permanent = 1 OR bi.expires_at > NOW()
                     ORDER BY bi.id DESC";
$blocked_ips_stmt = $db->prepare($blocked_ips_query);
$blocked_ips_stmt->execute();
$blocked_ips = $blocked_ips_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get suspicious activity patterns
$suspicious_patterns_query = "SELECT 
    ip_address,
    COUNT(*) as event_count,
    COUNT(DISTINCT action) as unique_actions,
    MAX(created_at) as last_activity,
    SUM(CASE WHEN risk_level = 'high' THEN 1 ELSE 0 END) as high_risk_count
    FROM security_logs 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY ip_address
    HAVING event_count > 10 OR high_risk_count > 0
    ORDER BY high_risk_count DESC, event_count DESC
    LIMIT 20";
$suspicious_patterns_stmt = $db->prepare($suspicious_patterns_query);
$suspicious_patterns_stmt->execute();
$suspicious_patterns = $suspicious_patterns_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Security Dashboard - Admin Panel';
include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Security Dashboard</h1>
                <div class="btn-group">
                    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#blockIpModal">
                        <i class="fas fa-ban"></i> Block IP
                    </button>
                    <button class="btn btn-warning" onclick="runSecurityScan()">
                        <i class="fas fa-shield-halved"></i> Security Scan
                    </button>
                </div>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- Security Statistics -->
            <div class="row mb-4">
                <div class="col-xl-2 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Events (24h)</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($security_stats['events_24h']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-6 mb-4">
                    <div class="card border-left-danger shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">High Risk (7d)</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($security_stats['high_risk_7d']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Blocked IPs</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($security_stats['active_blocks']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-ban fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Unique IPs (24h)</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($security_stats['unique_ips_24h']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-globe fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Users</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($security_stats['active_users_24h']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-6 mb-4">
                    <div class="card border-left-danger shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Failed Logins (1h)</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($security_stats['failed_logins_1h']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-lock fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Suspicious Activity Patterns -->
            <?php if (!empty($suspicious_patterns)): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-danger">Suspicious Activity Patterns (24h)</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>IP Address</th>
                                    <th>Total Events</th>
                                    <th>Unique Actions</th>
                                    <th>High Risk Events</th>
                                    <th>Last Activity</th>
                                    <th>Risk Level</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($suspicious_patterns as $pattern): ?>
                                <tr>
                                    <td>
                                        <code><?php echo htmlspecialchars($pattern['ip_address']); ?></code>
                                        <button class="btn btn-sm btn-outline-info ms-2" 
                                                onclick="viewIPDetails('<?php echo $pattern['ip_address']; ?>')">
                                            <i class="fas fa-info"></i>
                                        </button>
                                    </td>
                                    <td><?php echo $pattern['event_count']; ?></td>
                                    <td><?php echo $pattern['unique_actions']; ?></td>
                                    <td class="text-danger"><strong><?php echo $pattern['high_risk_count']; ?></strong></td>
                                    <td><?php echo date('M j, g:i A', strtotime($pattern['last_activity'])); ?></td>
                                    <td>
                                        <?php 
                                        $risk_score = ($pattern['high_risk_count'] * 10) + ($pattern['event_count'] * 0.5);
                                        if ($risk_score >= 50): ?>
                                            <span class="badge bg-danger">Critical</span>
                                        <?php elseif ($risk_score >= 20): ?>
                                            <span class="badge bg-warning">High</span>
                                        <?php else: ?>
                                            <span class="badge bg-info">Medium</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-danger btn-sm" 
                                                onclick="quickBlockIP('<?php echo $pattern['ip_address']; ?>')">
                                            <i class="fas fa-ban"></i> Block
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

            <!-- Blocked IPs -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Blocked IP Addresses</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($blocked_ips)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>IP Address</th>
                                        <th>Reason</th>
                                        <th>Blocked By</th>
                                        <th>Blocked Date</th>
                                        <th>Expires</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($blocked_ips as $blocked_ip): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($blocked_ip['ip_address']); ?></code></td>
                                        <td><?php echo htmlspecialchars($blocked_ip['reason']); ?></td>
                                        <td><?php echo htmlspecialchars($blocked_ip['blocked_by_username']); ?></td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($blocked_ip['created_at'])); ?></td>
                                        <td>
                                            <?php if ($blocked_ip['is_permanent']): ?>
                                                <span class="badge bg-danger">Permanent</span>
                                            <?php elseif ($blocked_ip['expires_at']): ?>
                                                <?php 
                                                $expires = strtotime($blocked_ip['expires_at']);
                                                if ($expires > time()): ?>
                                                    <span class="badge bg-warning">
                                                        <?php echo date('M j, Y g:i A', $expires); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Expired</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="unblock_ip">
                                                <input type="hidden" name="block_id" value="<?php echo $blocked_ip['id']; ?>">
                                                <button type="submit" class="btn btn-success btn-sm" 
                                                        onclick="return confirm('Unblock this IP address?')">
                                                    <i class="fas fa-unlock"></i> Unblock
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-shield-halved fa-3x text-success mb-3"></i>
                            <h5>No Blocked IPs</h5>
                            <p class="text-muted">No IP addresses are currently blocked.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Security Events -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Security Events</h6>
                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#clearLogsModal">
                        <i class="fas fa-trash"></i> Clear Old Logs
                    </button>
                </div>
                <div class="card-body">
                    <div style="max-height: 500px; overflow-y: auto;">
                        <?php foreach ($recent_events as $event): ?>
                        <div class="border-bottom pb-2 mb-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-<?php echo $event['risk_level'] === 'high' ? 'danger' : ($event['risk_level'] === 'medium' ? 'warning' : 'info'); ?>">
                                        <?php echo ucfirst($event['risk_level']); ?>
                                    </span>
                                    <strong><?php echo ucfirst(str_replace('_', ' ', $event['action'])); ?></strong>
                                </div>
                                <small class="text-muted"><?php echo date('M j, g:i A', strtotime($event['created_at'])); ?></small>
                            </div>
                            <div class="text-muted">
                                <small>
                                    <strong>IP:</strong> <?php echo htmlspecialchars($event['ip_address']); ?> |
                                    <strong>User:</strong> <?php echo htmlspecialchars($event['username'] ?: 'Unknown'); ?>
                                </small>
                            </div>
                            <?php if ($event['details']): ?>
                                <div class="mt-1">
                                    <small class="text-muted">
                                        <?php 
                                        $details = json_decode($event['details'], true);
                                        if (is_array($details)) {
                                            foreach ($details as $key => $value) {
                                                if (!is_array($value)) {
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

            <!-- System Security Status -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">System Security Status</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Security Features</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success"></i>
                                    <strong>HTTPS Enabled:</strong> 
                                    <span class="badge bg-<?php echo isset($_SERVER['HTTPS']) ? 'success' : 'danger'; ?>">
                                        <?php echo isset($_SERVER['HTTPS']) ? 'Yes' : 'No'; ?>
                                    </span>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success"></i>
                                    <strong>Session Security:</strong> 
                                    <span class="badge bg-success">Active</span>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success"></i>
                                    <strong>Input Validation:</strong> 
                                    <span class="badge bg-success">Enabled</span>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success"></i>
                                    <strong>SQL Injection Protection:</strong> 
                                    <span class="badge bg-success">PDO Prepared Statements</span>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Security Metrics</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <strong>Failed Login Rate:</strong> 
                                    <?php 
                                    $login_rate = $security_stats['active_users_24h'] > 0 ? 
                                        ($security_stats['failed_logins_1h'] / $security_stats['active_users_24h']) * 100 : 0;
                                    ?>
                                    <span class="badge bg-<?php echo $login_rate > 10 ? 'danger' : ($login_rate > 5 ? 'warning' : 'success'); ?>">
                                        <?php echo number_format($login_rate, 1); ?>%
                                    </span>
                                </li>
                                <li class="mb-2">
                                    <strong>Security Score:</strong>
                                    <?php 
                                    $security_score = max(0, 100 - ($security_stats['high_risk_7d'] * 2) - ($security_stats['failed_logins_1h'] * 0.5));
                                    ?>
                                    <span class="badge bg-<?php echo $security_score >= 90 ? 'success' : ($security_score >= 70 ? 'warning' : 'danger'); ?>">
                                        <?php echo number_format($security_score); ?>/100
                                    </span>
                                </li>
                                <li class="mb-2">
                                    <strong>Last Security Scan:</strong> 
                                    <span class="text-muted">Manual scan required</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Block IP Modal -->
<div class="modal fade" id="blockIpModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Block IP Address</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="block_ip">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">IP Address</label>
                        <input type="text" name="ip_address" id="blockIpAddress" class="form-control" 
                               placeholder="192.168.1.1" pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <select name="reason" class="form-select" required>
                            <option value="">Select reason...</option>
                            <option value="Suspicious activity detected">Suspicious activity detected</option>
                            <option value="Multiple failed login attempts">Multiple failed login attempts</option>
                            <option value="Spam or abuse">Spam or abuse</option>
                            <option value="Multiple account creation">Multiple account creation</option>
                            <option value="Fraudulent behavior">Fraudulent behavior</option>
                            <option value="Security threat">Security threat</option>
                            <option value="Manual admin decision">Manual admin decision</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_permanent" id="isPermanent" class="form-check-input" onchange="toggleDuration()">
                            <label class="form-check-label">Permanent Block</label>
                        </div>
                    </div>
                    
                    <div class="mb-3" id="durationGroup">
                        <label class="form-label">Block Duration (hours)</label>
                        <select name="duration_hours" class="form-select">
                            <option value="1">1 Hour</option>
                            <option value="6">6 Hours</option>
                            <option value="24" selected>24 Hours</option>
                            <option value="168">1 Week</option>
                            <option value="720">1 Month</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Block IP Address</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Clear Logs Modal -->
<div class="modal fade" id="clearLogsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Clear Security Logs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="clear_security_logs">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Clear logs older than:</label>
                        <select name="days" class="form-select" required>
                            <option value="7">7 days</option>
                            <option value="30" selected>30 days</option>
                            <option value="90">90 days</option>
                            <option value="365">1 year</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-warning">
                        <strong>Warning:</strong> This action cannot be undone. Security logs will be permanently deleted.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Clear Logs</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- IP Details Modal -->
<div class="modal fade" id="ipDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">IP Address Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="ipDetailsContent">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" onclick="blockCurrentIP()">Block This IP</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentViewingIP = '';

function toggleDuration() {
    const isPermanent = document.getElementById('isPermanent').checked;
    const durationGroup = document.getElementById('durationGroup');
    durationGroup.style.display = isPermanent ? 'none' : 'block';
}

function quickBlockIP(ipAddress) {
    document.getElementById('blockIpAddress').value = ipAddress;
    const modal = new bootstrap.Modal(document.getElementById('blockIpModal'));
    modal.show();
}

function viewIPDetails(ipAddress) {
    currentViewingIP = ipAddress;
    
    fetch(`ajax/get-ip-details.php?ip=${encodeURIComponent(ipAddress)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('ipDetailsContent').innerHTML = data.html;
                const modal = new bootstrap.Modal(document.getElementById('ipDetailsModal'));
                modal.show();
            } else {
                alert('Error loading IP details: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error loading IP details:', error);
            alert('Error loading IP details');
        });
}

function blockCurrentIP() {
    if (currentViewingIP) {
        document.getElementById('blockIpAddress').value = currentViewingIP;
        bootstrap.Modal.getInstance(document.getElementById('ipDetailsModal')).hide();
        const blockModal = new bootstrap.Modal(document.getElementById('blockIpModal'));
        blockModal.show();
    }
}

function runSecurityScan() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Scanning...';
    
    fetch('ajax/run-security-scan.php', {method: 'POST'})
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`Security scan completed!\n${data.summary}`);
                if (data.threats > 0) {
                    location.reload();
                }
            } else {
                alert('Security scan failed: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Security scan error:', error);
            alert('Security scan failed');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
}

// Auto-refresh security stats every 30 seconds
setInterval(function() {
    fetch('ajax/get-security-stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update stats without full page reload
                Object.keys(data.stats).forEach(key => {
                    const element = document.querySelector(`[data-stat="${key}"]`);
                    if (element) {
                        element.textContent = data.stats[key];
                    }
                });
            }
        })
        .catch(error => console.log('Stats refresh failed:', error));
}, 30000);
</script>

<?php include 'includes/admin_footer.php'; ?>
