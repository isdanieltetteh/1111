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

// Handle notification sending
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'send_notification':
            $title = trim($_POST['title']);
            $message = trim($_POST['message']);
            $type = $_POST['type'];
            $target = $_POST['target'];
            
            if (empty($title) || empty($message)) {
                $error_message = 'Title and message are required';
                break;
            }
            
            $user_ids = [];
            
            if ($target === 'all') {
                $users_query = "SELECT id FROM users WHERE is_banned = 0";
                $users_stmt = $db->prepare($users_query);
                $users_stmt->execute();
                $user_ids = array_column($users_stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
            } elseif ($target === 'active') {
                $users_query = "SELECT id FROM users WHERE is_banned = 0 AND last_active >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                $users_stmt = $db->prepare($users_query);
                $users_stmt->execute();
                $user_ids = array_column($users_stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
            } elseif ($target === 'new') {
                $users_query = "SELECT id FROM users WHERE is_banned = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                $users_stmt = $db->prepare($users_query);
                $users_stmt->execute();
                $user_ids = array_column($users_stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
            }
            
            if (!empty($user_ids)) {
                $insert_query = "INSERT INTO notifications (user_id, title, message, type) VALUES ";
                $values = [];
                $params = [];
                
                foreach ($user_ids as $index => $user_id) {
                    $values[] = "(:user_id_{$index}, :title_{$index}, :message_{$index}, :type_{$index})";
                    $params[":user_id_{$index}"] = $user_id;
                    $params[":title_{$index}"] = $title;
                    $params[":message_{$index}"] = $message;
                    $params[":type_{$index}"] = $type;
                }
                
                $insert_query .= implode(', ', $values);
                $insert_stmt = $db->prepare($insert_query);
                
                if ($insert_stmt->execute($params)) {
                    $success_message = 'Notification sent to ' . count($user_ids) . ' users successfully!';
                } else {
                    $error_message = 'Error sending notifications';
                }
            } else {
                $error_message = 'No users found for the selected target';
            }
            break;
            
        case 'delete_notification':
            $notification_id = intval($_POST['notification_id']);
            $delete_query = "DELETE FROM notifications WHERE id = :notification_id";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->bindParam(':notification_id', $notification_id);
            
            if ($delete_stmt->execute()) {
                $success_message = 'Notification deleted successfully!';
            } else {
                $error_message = 'Error deleting notification';
            }
            break;
    }
}

// Get recent notifications
$notifications_query = "SELECT n.*, u.username, 
                        (SELECT COUNT(*) FROM notifications n2 WHERE n2.title = n.title AND n2.message = n.message AND n2.created_at = n.created_at) as recipient_count
                        FROM notifications n
                        JOIN users u ON n.user_id = u.id
                        GROUP BY n.title, n.message, n.created_at
                        ORDER BY n.created_at DESC
                        LIMIT 20";
$notifications_stmt = $db->prepare($notifications_query);
$notifications_stmt->execute();
$recent_notifications = $notifications_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Notifications - Admin Panel';
include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Notification Center</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sendNotificationModal">
                    <i class="fas fa-bell"></i> Send Notification
                </button>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- Recent Notifications -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Notifications</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_notifications)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Message</th>
                                        <th>Type</th>
                                        <th>Recipients</th>
                                        <th>Sent</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_notifications as $notification): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($notification['title']); ?></strong></td>
                                        <td><?php echo htmlspecialchars(substr($notification['message'], 0, 100)) . '...'; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $notification['type'] === 'success' ? 'success' : ($notification['type'] === 'warning' ? 'warning' : 'info'); ?>">
                                                <?php echo ucfirst($notification['type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($notification['recipient_count']); ?> users</td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewNotification('<?php echo htmlspecialchars($notification['title']); ?>', '<?php echo htmlspecialchars($notification['message']); ?>')">
                                                View
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-bell fa-3x text-muted mb-3"></i>
                            <h5>No notifications sent yet</h5>
                            <p class="text-muted">Send your first notification to engage with users.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Send Notification Modal -->
<div class="modal fade" id="sendNotificationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Send Notification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="send_notification">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Notification Title</label>
                        <input type="text" name="title" class="form-control" placeholder="Enter notification title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea name="message" class="form-control" rows="4" placeholder="Enter your message..." required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Notification Type</label>
                                <select name="type" class="form-select" required>
                                    <option value="info">Info (Blue)</option>
                                    <option value="success">Success (Green)</option>
                                    <option value="warning">Warning (Orange)</option>
                                    <option value="error">Error (Red)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Target Audience</label>
                                <select name="target" class="form-select" required>
                                    <option value="all">All Users</option>
                                    <option value="active">Active Users (30 days)</option>
                                    <option value="new">New Users (7 days)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Notification</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Notification Modal -->
<div class="modal fade" id="viewNotificationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewNotificationTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="viewNotificationMessage"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewNotification(title, message) {
    document.getElementById('viewNotificationTitle').textContent = title;
    document.getElementById('viewNotificationMessage').textContent = message;
    
    const modal = new bootstrap.Modal(document.getElementById('viewNotificationModal'));
    modal.show();
}
</script>

<?php include 'includes/admin_footer.php'; ?>
