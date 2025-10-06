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

// Handle email sending
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'send_email':
            $subject = trim($_POST['subject']);
            $message = trim($_POST['message']);
            $target = $_POST['target'];
            $send_type = $_POST['send_type'];
            
            if (empty($subject) || empty($message)) {
                $error_message = 'Subject and message are required';
                break;
            }
            
            $recipients = [];
            
            if ($target === 'all_users') {
                $users_query = "SELECT email, username FROM users WHERE is_banned = 0";
                $users_stmt = $db->prepare($users_query);
                $users_stmt->execute();
                $recipients = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($target === 'newsletter') {
                $users_query = "SELECT email, username FROM users WHERE is_banned = 0 AND email_notifications = 1";
                $users_stmt = $db->prepare($users_query);
                $users_stmt->execute();
                $recipients = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($target === 'active_users') {
                $users_query = "SELECT email, username FROM users WHERE is_banned = 0 AND last_active >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                $users_stmt = $db->prepare($users_query);
                $users_stmt->execute();
                $recipients = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            if (!empty($recipients)) {
                // Log email campaign
                $campaign_query = "INSERT INTO email_campaigns (subject, message, target_audience, recipient_count, sent_by) 
                                  VALUES (:subject, :message, :target, :count, :admin_id)";
                $campaign_stmt = $db->prepare($campaign_query);
                $campaign_stmt->bindParam(':subject', $subject);
                $campaign_stmt->bindParam(':message', $message);
                $campaign_stmt->bindParam(':target', $target);
                $campaign_stmt->bindParam(':count', count($recipients));
                $campaign_stmt->bindParam(':admin_id', $_SESSION['user_id']);
                $campaign_stmt->execute();
                
                $campaign_id = $db->lastInsertId();
                
                // Queue emails for sending
                foreach ($recipients as $recipient) {
                    $queue_query = "INSERT INTO email_queue (campaign_id, recipient_email, recipient_name, subject, message) 
                                   VALUES (:campaign_id, :email, :name, :subject, :message)";
                    $queue_stmt = $db->prepare($queue_query);
                    $queue_stmt->bindParam(':campaign_id', $campaign_id);
                    $queue_stmt->bindParam(':email', $recipient['email']);
                    $queue_stmt->bindParam(':name', $recipient['username']);
                    $queue_stmt->bindParam(':subject', $subject);
                    $queue_stmt->bindParam(':message', $message);
                    $queue_stmt->execute();
                }
                
                $success_message = 'Email campaign created! ' . count($recipients) . ' emails queued for sending.';
            } else {
                $error_message = 'No recipients found for the selected target';
            }
            break;
    }
}

// Get email statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM email_campaigns) as total_campaigns,
    (SELECT COUNT(*) FROM email_queue WHERE status = 'pending') as pending_emails,
    (SELECT COUNT(*) FROM email_queue WHERE status = 'sent') as sent_emails,
    (SELECT COUNT(*) FROM email_queue WHERE status = 'failed') as failed_emails";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$email_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent campaigns
$campaigns_query = "SELECT ec.*, u.username as sent_by_username 
                   FROM email_campaigns ec
                   JOIN users u ON ec.sent_by = u.id
                   ORDER BY ec.created_at DESC
                   LIMIT 10";
$campaigns_stmt = $db->prepare($campaigns_query);
$campaigns_stmt->execute();
$recent_campaigns = $campaigns_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Email Management - Admin Panel';
include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Email Management</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sendEmailModal">
                    <i class="fas fa-envelope"></i> Send Email Campaign
                </button>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- Email Statistics -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Campaigns</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($email_stats['total_campaigns']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-paper-plane fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Emails</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($email_stats['pending_emails']); ?></div>
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
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Sent Emails</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($email_stats['sent_emails']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-danger shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Failed Emails</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($email_stats['failed_emails']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-times fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Campaigns -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Email Campaigns</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_campaigns)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Target Audience</th>
                                        <th>Recipients</th>
                                        <th>Sent By</th>
                                        <th>Created</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_campaigns as $campaign): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($campaign['subject']); ?></strong></td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo ucfirst(str_replace('_', ' ', $campaign['target_audience'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($campaign['recipient_count']); ?></td>
                                        <td><?php echo htmlspecialchars($campaign['sent_by_username']); ?></td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($campaign['created_at'])); ?></td>
                                        <td>
                                            <span class="badge bg-success">Queued</span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-envelope fa-3x text-muted mb-3"></i>
                            <h5>No email campaigns yet</h5>
                            <p class="text-muted">Create your first email campaign to engage with users.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Send Email Modal -->
<div class="modal fade" id="sendEmailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Send Email Campaign</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="send_email">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Email Subject</label>
                        <input type="text" name="subject" class="form-control" placeholder="Enter email subject" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email Message</label>
                        <textarea name="message" class="form-control" rows="6" placeholder="Enter your email message..." required></textarea>
                        <small class="form-text text-muted">HTML is supported</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Target Audience</label>
                                <select name="target" class="form-select" required>
                                    <option value="newsletter">Newsletter Subscribers</option>
                                    <option value="all_users">All Users</option>
                                    <option value="active_users">Active Users (30 days)</option>
                                    <option value="new_users">New Users (7 days)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Send Type</label>
                                <select name="send_type" class="form-select" required>
                                    <option value="immediate">Send Immediately</option>
                                    <option value="queue">Queue for Later</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Email Campaign</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/admin_footer.php'; ?>
