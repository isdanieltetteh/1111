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

// Handle ticket actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'reply_ticket':
            $ticket_id = intval($_POST['ticket_id']);
            $reply_message = trim($_POST['reply_message']);
            
            if (empty($reply_message)) {
                $error_message = 'Reply message is required';
                break;
            }
            
            // Insert reply
            $reply_query = "INSERT INTO support_replies (ticket_id, admin_id, message) 
                           VALUES (:ticket_id, :admin_id, :message)";
            $reply_stmt = $db->prepare($reply_query);
            $reply_stmt->bindParam(':ticket_id', $ticket_id);
            $reply_stmt->bindParam(':admin_id', $_SESSION['user_id']);
            $reply_stmt->bindParam(':message', $reply_message);
            
            if ($reply_stmt->execute()) {
                // Update ticket status
                $update_query = "UPDATE support_tickets SET status = 'replied', updated_at = NOW() WHERE id = :ticket_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':ticket_id', $ticket_id);
                $update_stmt->execute();
                
                $success_message = 'Reply sent successfully!';
            } else {
                $error_message = 'Error sending reply';
            }
            break;
            
        case 'close_ticket':
            $ticket_id = intval($_POST['ticket_id']);
            $update_query = "UPDATE support_tickets SET status = 'closed', updated_at = NOW() WHERE id = :ticket_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':ticket_id', $ticket_id);
            
            if ($update_stmt->execute()) {
                $success_message = 'Ticket closed successfully!';
            } else {
                $error_message = 'Error closing ticket';
            }
            break;
            
        case 'reopen_ticket':
            $ticket_id = intval($_POST['ticket_id']);
            $update_query = "UPDATE support_tickets SET status = 'open', updated_at = NOW() WHERE id = :ticket_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':ticket_id', $ticket_id);
            
            if ($update_stmt->execute()) {
                $success_message = 'Ticket reopened successfully!';
            } else {
                $error_message = 'Error reopening ticket';
            }
            break;
    }
}

// Get filters
$status_filter = $_GET['status'] ?? 'all';
$priority_filter = $_GET['priority'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build WHERE clause
$where_conditions = ['1=1'];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "st.status = :status";
    $params[':status'] = $status_filter;
}

if ($priority_filter !== 'all') {
    $where_conditions[] = "st.priority = :priority";
    $params[':priority'] = $priority_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get tickets
$tickets_query = "SELECT st.*, u.username, u.email,
                  (SELECT COUNT(*) FROM support_replies WHERE ticket_id = st.id) as reply_count
                  FROM support_tickets st
                  LEFT JOIN users u ON st.user_id = u.id
                  WHERE {$where_clause}
                  ORDER BY 
                    CASE st.status 
                        WHEN 'open' THEN 1 
                        WHEN 'replied' THEN 2 
                        WHEN 'closed' THEN 3 
                    END,
                    CASE st.priority 
                        WHEN 'high' THEN 1 
                        WHEN 'medium' THEN 2 
                        WHEN 'low' THEN 3 
                    END,
                    st.created_at DESC
                  LIMIT {$per_page} OFFSET {$offset}";

$tickets_stmt = $db->prepare($tickets_query);
$tickets_stmt->execute($params);
$tickets = $tickets_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get ticket statistics
$ticket_stats_query = "SELECT 
    COUNT(*) as total_tickets,
    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_tickets,
    SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) as replied_tickets,
    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_tickets,
    SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_priority
    FROM support_tickets";
$ticket_stats_stmt = $db->prepare($ticket_stats_query);
$ticket_stats_stmt->execute();
$ticket_stats = $ticket_stats_stmt->fetch(PDO::FETCH_ASSOC);

$page_title = 'Support Tickets - Admin Panel';
include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Support Tickets</h1>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- Ticket Statistics -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Tickets</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($ticket_stats['total_tickets']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-ticket-simple fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Open Tickets</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($ticket_stats['open_tickets']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-exclamation-circle fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Replied Tickets</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($ticket_stats['replied_tickets']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-reply fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">High Priority</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($ticket_stats['high_priority']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-fire fa-2x text-gray-300"></i>
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
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>>Open</option>
                                <option value="replied" <?php echo $status_filter === 'replied' ? 'selected' : ''; ?>>Replied</option>
                                <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="all" <?php echo $priority_filter === 'all' ? 'selected' : ''; ?>>All Priorities</option>
                                <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                                <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary d-block">Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tickets List -->
            <div class="card shadow mb-4">
                <div class="card-body">
                    <?php if (!empty($tickets)): ?>
                        <?php foreach ($tickets as $ticket): ?>
                        <div class="border-bottom pb-3 mb-3">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="d-flex align-items-center mb-2">
                                        <h5 class="mb-0 me-3"><?php echo htmlspecialchars($ticket['subject']); ?></h5>
                                        <span class="badge bg-<?php echo $ticket['status'] === 'open' ? 'warning' : ($ticket['status'] === 'replied' ? 'info' : 'success'); ?>">
                                            <?php echo ucfirst($ticket['status']); ?>
                                        </span>
                                        <span class="badge bg-<?php echo $ticket['priority'] === 'high' ? 'danger' : ($ticket['priority'] === 'medium' ? 'warning' : 'secondary'); ?> ms-2">
                                            <?php echo ucfirst($ticket['priority']); ?> Priority
                                        </span>
                                    </div>
                                    
                                    <p class="mb-2"><?php echo nl2br(htmlspecialchars($ticket['message'])); ?></p>
                                    
                                    <div class="d-flex align-items-center text-muted">
                                        <small>
                                            <strong>From:</strong> <?php echo htmlspecialchars($ticket['username'] ?: $ticket['email']); ?> •
                                            <strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?> •
                                            <strong>Replies:</strong> <?php echo $ticket['reply_count']; ?>
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-primary btn-sm" 
                                                onclick="replyToTicket(<?php echo $ticket['id']; ?>, '<?php echo htmlspecialchars($ticket['subject']); ?>')">
                                            <i class="fas fa-reply"></i> Reply
                                        </button>
                                        
                                        <?php if ($ticket['status'] !== 'closed'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="close_ticket">
                                                <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                                <button type="submit" class="btn btn-success btn-sm w-100">
                                                    <i class="fas fa-check"></i> Close Ticket
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="reopen_ticket">
                                                <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                                <button type="submit" class="btn btn-warning btn-sm w-100">
                                                    <i class="fas fa-undo"></i> Reopen
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-info btn-sm" 
                                                onclick="viewTicketHistory(<?php echo $ticket['id']; ?>)">
                                            <i class="fas fa-history"></i> View History
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-ticket-simple fa-3x text-muted mb-3"></i>
                            <h5>No support tickets</h5>
                            <p class="text-muted">No tickets match your current filters.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Reply Modal -->
<div class="modal fade" id="replyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reply to Ticket</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="replyForm">
                <input type="hidden" name="action" value="reply_ticket">
                <input type="hidden" name="ticket_id" id="replyTicketId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" id="replySubject" class="form-control" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Your Reply</label>
                        <textarea name="reply_message" class="form-control" rows="6" 
                                  placeholder="Type your reply..." required></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>Note:</strong> The user will receive an email notification about your reply.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Reply</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Ticket History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ticket History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="ticketHistoryContent">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function replyToTicket(ticketId, subject) {
    document.getElementById('replyTicketId').value = ticketId;
    document.getElementById('replySubject').value = 'Re: ' + subject;
    
    const modal = new bootstrap.Modal(document.getElementById('replyModal'));
    modal.show();
}

function viewTicketHistory(ticketId) {
    fetch(`ajax/get-ticket-history.php?id=${ticketId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('ticketHistoryContent').innerHTML = data.html;
                const modal = new bootstrap.Modal(document.getElementById('historyModal'));
                modal.show();
            }
        })
        .catch(error => console.error('Error loading ticket history:', error));
}
</script>

<?php include 'includes/admin_footer.php'; ?>
