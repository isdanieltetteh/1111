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

// Get system logs
$logs_query = "SELECT 'site_status' as log_type, site_id as reference_id, old_status, new_status, reason, changed_by, created_at
               FROM scam_reports_log
               ORDER BY created_at DESC
               LIMIT 50";
$logs_stmt = $db->prepare($logs_query);
$logs_stmt->execute();
$system_logs = $logs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent activity logs
$activity_query = "SELECT 
    'user_registration' as activity_type,
    u.username as user_name,
    u.created_at as activity_time,
    'User registered' as description
    FROM users u
    WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    
    UNION ALL
    
    SELECT 
    'site_submission' as activity_type,
    u.username as user_name,
    s.created_at as activity_time,
    CONCAT('Submitted site: ', s.name) as description
    FROM sites s
    JOIN users u ON s.submitted_by = u.id
    WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    
    UNION ALL
    
    SELECT 
    'review_posted' as activity_type,
    u.username as user_name,
    r.created_at as activity_time,
    CONCAT('Posted review for: ', s.name) as description
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    JOIN sites s ON r.site_id = s.id
    WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    
    ORDER BY activity_time DESC
    LIMIT 100";

$activity_stmt = $db->prepare($activity_query);
$activity_stmt->execute();
$recent_activity = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'System Logs - Admin Panel';
include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">System Logs</h1>
            </div>

            <div class="row">
                <!-- System Status Changes -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Site Status Changes</h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($system_logs)): ?>
                                <div style="max-height: 400px; overflow-y: auto;">
                                    <?php foreach ($system_logs as $log): ?>
                                    <div class="border-bottom pb-2 mb-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <strong>Site ID: <?php echo $log['reference_id']; ?></strong>
                                            <small class="text-muted"><?php echo date('M j, g:i A', strtotime($log['created_at'])); ?></small>
                                        </div>
                                        <p class="mb-1">
                                            Status changed from 
                                            <span class="badge bg-secondary"><?php echo ucfirst($log['old_status']); ?></span>
                                            to 
                                            <span class="badge bg-warning"><?php echo ucfirst($log['new_status']); ?></span>
                                        </p>
                                        <?php if ($log['reason']): ?>
                                            <small class="text-muted">Reason: <?php echo htmlspecialchars($log['reason']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No status changes recorded</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Recent Activity (7 Days)</h6>
                        </div>
                        <div class="card-body">
                            <div style="max-height: 400px; overflow-y: auto;">
                                <?php foreach ($recent_activity as $activity): ?>
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <strong><?php echo htmlspecialchars($activity['user_name']); ?></strong>
                                        <small class="text-muted"><?php echo date('M j, g:i A', strtotime($activity['activity_time'])); ?></small>
                                    </div>
                                    <p class="mb-0 text-muted"><?php echo htmlspecialchars($activity['description']); ?></p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/admin_footer.php'; ?>
