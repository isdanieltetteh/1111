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

$success_message = '';
$error_message = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'send_newsletter':
            $subject = trim($_POST['subject']);
            $content = trim($_POST['content']);
            $target_preferences = $_POST['target_preferences'] ?? [];
            
            if (empty($subject) || empty($content)) {
                $error_message = 'Subject and content are required';
                break;
            }
            
            // Build query based on preferences
            $where_conditions = ['ns.is_active = 1', 'ns.verified_at IS NOT NULL'];
            $params = [];
            
            if (!empty($target_preferences)) {
                $preference_conditions = [];
                foreach ($target_preferences as $pref) {
                    $preference_conditions[] = "JSON_CONTAINS(ns.preferences, ?)";
                }
                $where_clause_prefs = '(' . implode(' OR ', $preference_conditions) . ')';
                $where_conditions[] = $where_clause_prefs;
                
                // Add parameters for each preference
                foreach ($target_preferences as $pref) {
                    $params[] = '"' . $pref . '"';
                }
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            // Get subscribers
            $subscribers_query = "SELECT ns.email, u.username 
                                FROM newsletter_subscriptions ns
                                LEFT JOIN users u ON ns.user_id = u.id
                                WHERE {$where_clause}";
            $subscribers_stmt = $db->prepare($subscribers_query);
            if (!empty($params)) {
                $subscribers_stmt->execute($params);
            } else {
                $subscribers_stmt->execute();
            }
            $subscribers = $subscribers_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($subscribers)) {
                // Log newsletter campaign
                $campaign_query = "INSERT INTO email_campaigns (subject, message, target_audience, recipient_count, sent_by) 
                                  VALUES (:subject, :content, :target, :count, :admin_id)";
                $campaign_stmt = $db->prepare($campaign_query);
                $campaign_stmt->bindParam(':subject', $subject);
                $campaign_stmt->bindParam(':content', $content);
                $campaign_stmt->bindParam(':target', implode(',', $target_preferences));
                $campaign_stmt->bindParam(':count', count($subscribers));
                $campaign_stmt->bindParam(':admin_id', $_SESSION['user_id']);
                $campaign_stmt->execute();
                
                $success_message = 'Newsletter sent to ' . count($subscribers) . ' subscribers!';
            } else {
                $error_message = 'No subscribers found for selected preferences';
            }
            break;
            
        case 'export_subscribers':
            $preference_filter = $_POST['preference_filter'] ?? 'all';
            
            $where_conditions = ['ns.is_active = 1'];
            if ($preference_filter !== 'all') {
                $where_conditions[] = "JSON_CONTAINS(ns.preferences, '\"" . $preference_filter . "\"')";
            }
            
            $export_query = "SELECT ns.email, ns.preferences, ns.created_at, u.username
                           FROM newsletter_subscriptions ns
                           LEFT JOIN users u ON ns.user_id = u.id
                           WHERE " . implode(' AND ', $where_conditions) . "
                           ORDER BY ns.created_at DESC";
            $export_stmt = $db->prepare($export_query);
            $export_stmt->execute();
            $export_data = $export_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Generate CSV
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="newsletter_subscribers_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Email', 'Username', 'Preferences', 'Subscribed Date']);
            
            foreach ($export_data as $row) {
                $preferences = json_decode($row['preferences'], true);
                fputcsv($output, [
                    $row['email'],
                    $row['username'] ?: 'Guest',
                    implode(', ', $preferences),
                    $row['created_at']
                ]);
            }
            
            fclose($output);
            exit();
    }
}

// Get filters
$preference_filter = $_GET['preference'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Build WHERE clause
$where_conditions = ['1=1'];
$params = [];

if ($preference_filter !== 'all') {
    $where_conditions[] = "JSON_CONTAINS(ns.preferences, :preference)";
    $params[':preference'] = '"' . $preference_filter . '"';
}

if ($status_filter !== 'all') {
    if ($status_filter === 'verified') {
        $where_conditions[] = "ns.verified_at IS NOT NULL";
    } elseif ($status_filter === 'unverified') {
        $where_conditions[] = "ns.verified_at IS NULL";
    } elseif ($status_filter === 'active') {
        $where_conditions[] = "ns.is_active = 1";
    }
}

$where_clause = implode(' AND ', $where_conditions);

// Get subscribers
$subscribers_query = "SELECT ns.*, u.username
                     FROM newsletter_subscriptions ns
                     LEFT JOIN users u ON ns.user_id = u.id
                     WHERE {$where_clause}
                     ORDER BY ns.created_at DESC
                     LIMIT {$per_page} OFFSET {$offset}";
$subscribers_stmt = $db->prepare($subscribers_query);
$subscribers_stmt->execute($params);
$subscribers = $subscribers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_subscribers,
    SUM(CASE WHEN verified_at IS NOT NULL THEN 1 ELSE 0 END) as verified_subscribers,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_subscribers,
    SUM(CASE WHEN JSON_CONTAINS(preferences, '\"scam_alerts\"') THEN 1 ELSE 0 END) as scam_alert_subs,
    SUM(CASE WHEN JSON_CONTAINS(preferences, '\"new_sites\"') THEN 1 ELSE 0 END) as new_site_subs,
    SUM(CASE WHEN JSON_CONTAINS(preferences, '\"weekly_digest\"') THEN 1 ELSE 0 END) as weekly_digest_subs
    FROM newsletter_subscriptions";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$page_title = 'Newsletter Management - Admin Panel';
include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Newsletter Management</h1>
                <div class="btn-group">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sendNewsletterModal">
                        <i class="fas fa-paper-plane"></i> Send Newsletter
                    </button>
                    <button class="btn btn-success" onclick="exportSubscribers()">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Subscribers</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_subscribers']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Verified</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['verified_subscribers']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Scam Alerts</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['scam_alert_subs']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Weekly Digest</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['weekly_digest_subs']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-week fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Preference Filter</label>
                            <select name="preference" class="form-select">
                                <option value="all" <?php echo $preference_filter === 'all' ? 'selected' : ''; ?>>All Preferences</option>
                                <option value="scam_alerts" <?php echo $preference_filter === 'scam_alerts' ? 'selected' : ''; ?>>Scam Alerts</option>
                                <option value="new_sites" <?php echo $preference_filter === 'new_sites' ? 'selected' : ''; ?>>New Sites</option>
                                <option value="weekly_digest" <?php echo $preference_filter === 'weekly_digest' ? 'selected' : ''; ?>>Weekly Digest</option>
                                <option value="high_paying" <?php echo $preference_filter === 'high_paying' ? 'selected' : ''; ?>>High Paying</option>
                                <option value="platform_updates" <?php echo $preference_filter === 'platform_updates' ? 'selected' : ''; ?>>Platform Updates</option>
                                <option value="earning_tips" <?php echo $preference_filter === 'earning_tips' ? 'selected' : ''; ?>>Earning Tips</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status Filter</label>
                            <select name="status" class="form-select">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                                <option value="unverified" <?php echo $status_filter === 'unverified' ? 'selected' : ''; ?>>Unverified</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary d-block">Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Subscribers List -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Newsletter Subscribers</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($subscribers)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Email</th>
                                        <th>Username</th>
                                        <th>Preferences</th>
                                        <th>Status</th>
                                        <th>Subscribed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subscribers as $subscriber): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($subscriber['email']); ?></td>
                                        <td><?php echo htmlspecialchars($subscriber['username'] ?: 'Guest'); ?></td>
                                        <td>
                                            <?php 
                                            $preferences = json_decode($subscriber['preferences'], true);
                                            if (!empty($preferences)) {
                                                foreach ($preferences as $pref) {
                                                    $pref_labels = [
                                                        'scam_alerts' => '🚨 Scam Alerts',
                                                        'new_sites' => '🆕 New Sites',
                                                        'weekly_digest' => '📊 Weekly Digest',
                                                        'high_paying' => '💰 High Paying',
                                                        'platform_updates' => '🔔 Updates',
                                                        'earning_tips' => '💡 Tips'
                                                    ];
                                                    echo '<span class="badge bg-secondary me-1">' . ($pref_labels[$pref] ?? $pref) . '</span>';
                                                }
                                            } else {
                                                echo '<span class="text-muted">No preferences</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($subscriber['verified_at']): ?>
                                                <span class="badge bg-success">Verified</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Unverified</span>
                                            <?php endif; ?>
                                            
                                            <?php if ($subscriber['is_active']): ?>
                                                <span class="badge bg-info">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($subscriber['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-envelope fa-3x text-muted mb-3"></i>
                            <h5>No subscribers found</h5>
                            <p class="text-muted">No subscribers match your current filters.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Send Newsletter Modal -->
<div class="modal fade" id="sendNewsletterModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Send Newsletter</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="send_newsletter">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" name="subject" class="form-control" placeholder="Newsletter subject" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Content</label>
                        <textarea name="content" class="form-control" rows="8" placeholder="Newsletter content..." required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Target Preferences</label>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="target_preferences[]" value="scam_alerts" class="form-check-input">
                                    <label class="form-check-label">🚨 Scam Alerts (<?php echo $stats['scam_alert_subs']; ?>)</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="target_preferences[]" value="new_sites" class="form-check-input">
                                    <label class="form-check-label">🆕 New Sites (<?php echo $stats['new_site_subs']; ?>)</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="target_preferences[]" value="weekly_digest" class="form-check-input">
                                    <label class="form-check-label">📊 Weekly Digest (<?php echo $stats['weekly_digest_subs']; ?>)</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="target_preferences[]" value="high_paying" class="form-check-input">
                                    <label class="form-check-label">💰 High Paying Sites</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="target_preferences[]" value="platform_updates" class="form-check-input">
                                    <label class="form-check-label">🔔 Platform Updates</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="target_preferences[]" value="earning_tips" class="form-check-input">
                                    <label class="form-check-label">💡 Earning Tips</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Newsletter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function exportSubscribers() {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = '<input type="hidden" name="action" value="export_subscribers">';
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
</script>

<?php include 'includes/admin_footer.php'; ?>
