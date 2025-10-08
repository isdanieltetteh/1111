<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/support_helpers.php';
require_once __DIR__ . '/../includes/MailService.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();
ensure_support_schema($db);
$mailer = MailService::getInstance();

function admin_format_minutes(?int $minutes): string {
    if ($minutes === null) {
        return '—';
    }

    if ($minutes < 60) {
        return $minutes . 'm';
    }

    $hours = intdiv($minutes, 60);
    $mins = $minutes % 60;

    if ($hours < 24) {
        return $hours . 'h' . ($mins ? ' ' . $mins . 'm' : '');
    }

    $days = intdiv($hours, 24);
    $hours = $hours % 24;

    $label = $days . 'd';
    if ($hours) {
        $label .= ' ' . $hours . 'h';
    }

    return $label;
}

function fetch_ticket_with_user(PDO $db, int $ticket_id): ?array {
    $stmt = $db->prepare("SELECT st.*, u.username AS account_username, u.email AS account_email FROM support_tickets st LEFT JOIN users u ON st.user_id = u.id WHERE st.id = :ticket_id LIMIT 1");
    $stmt->bindValue(':ticket_id', $ticket_id, PDO::PARAM_INT);
    $stmt->execute();

    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    return $ticket ?: null;
}

function get_ticket_recipients(array $ticket): array {
    $recipients = [];

    if (!empty($ticket['account_email']) && filter_var($ticket['account_email'], FILTER_VALIDATE_EMAIL)) {
        $recipients[$ticket['account_email']] = $ticket['account_username'] ?? $ticket['name'];
    }

    if (!empty($ticket['email']) && filter_var($ticket['email'], FILTER_VALIDATE_EMAIL)) {
        if (!isset($recipients[$ticket['email']])) {
            $recipients[$ticket['email']] = $ticket['name'];
        }
    }

    $formatted = [];
    foreach ($recipients as $email => $name) {
        $formatted[] = ['email' => $email, 'name' => $name];
    }

    return $formatted;
}

function notify_ticket_user(array $ticket, string $subject, string $htmlBody, string $textBody, MailService $mailer): void {
    $recipients = get_ticket_recipients($ticket);
    if (!$recipients) {
        return;
    }

    $mailer->send($recipients, $subject, $htmlBody, ['text' => $textBody]);
}

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
            $ticket_id = isset($_POST['ticket_id']) ? (int) $_POST['ticket_id'] : 0;
            $reply_message = trim($_POST['reply_message'] ?? '');

            if ($ticket_id <= 0) {
                $error_message = 'Invalid ticket selected';
                break;
            }

            if ($reply_message === '') {
                $error_message = 'Reply message is required';
                break;
            }

            $ticket = fetch_ticket_with_user($db, $ticket_id);
            if (!$ticket) {
                $error_message = 'Ticket not found';
                break;
            }

            $reply_query = "INSERT INTO support_replies (ticket_id, admin_id, user_id, message, sender_type)
                           VALUES (:ticket_id, :admin_id, NULL, :message, 'admin')";
            $reply_stmt = $db->prepare($reply_query);
            $reply_stmt->bindValue(':ticket_id', $ticket_id, PDO::PARAM_INT);
            $reply_stmt->bindValue(':admin_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $reply_stmt->bindValue(':message', $reply_message);

            if ($reply_stmt->execute()) {
                $update_query = "UPDATE support_tickets SET status = 'replied', updated_at = NOW() WHERE id = :ticket_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindValue(':ticket_id', $ticket_id, PDO::PARAM_INT);
                $update_stmt->execute();

                if (!empty($ticket['user_id'])) {
                    create_notification(
                        $db,
                        (int) $ticket['user_id'],
                        'Support replied to ticket #' . $ticket_id,
                        sprintf('Our team replied: "%s"', mb_strimwidth($reply_message, 0, 140, '…')),
                        'info'
                    );
                }

                $ticket_url = SITE_URL . '/support-tickets.php?ticket=' . $ticket_id;
                $html = '<p>Hi ' . htmlspecialchars($ticket['name'], ENT_QUOTES, 'UTF-8') . ',</p>' .
                        '<p>We just responded to your support ticket <strong>#' . $ticket_id . '</strong>.</p>' .
                        '<blockquote style="border-left:4px solid #38bdf8;padding-left:12px;margin:12px 0;">' . nl2br(htmlspecialchars($reply_message, ENT_QUOTES, 'UTF-8')) . '</blockquote>' .
                        '<p><a href="' . htmlspecialchars($ticket_url, ENT_QUOTES, 'UTF-8') . '" style="color:#0ea5e9;">Review the conversation in your dashboard</a>.</p>' .
                        '<p>Best regards,<br>' . SITE_NAME . ' Support Team</p>';

                notify_ticket_user(
                    $ticket,
                    '[' . SITE_NAME . '] Update on support ticket #' . $ticket_id,
                    $html,
                    "We responded to your ticket #{$ticket_id}. View it here: {$ticket_url}\n\n" . $reply_message,
                    $mailer
                );

                $success_message = 'Reply sent and user notified successfully!';
            } else {
                $error_message = 'Error sending reply';
            }
            break;
            
        case 'close_ticket':
            $ticket_id = isset($_POST['ticket_id']) ? (int) $_POST['ticket_id'] : 0;
            $ticket = fetch_ticket_with_user($db, $ticket_id);

            if (!$ticket) {
                $error_message = 'Ticket not found';
                break;
            }

            $update_query = "UPDATE support_tickets SET status = 'closed', updated_at = NOW() WHERE id = :ticket_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindValue(':ticket_id', $ticket_id, PDO::PARAM_INT);

            if ($update_stmt->execute()) {
                if (!empty($ticket['user_id'])) {
                    create_notification(
                        $db,
                        (int) $ticket['user_id'],
                        'Support ticket #' . $ticket_id . ' closed',
                        'We marked your ticket as resolved. Reply if you need further assistance.',
                        'success'
                    );
                }

                $ticket_url = SITE_URL . '/support-tickets.php?ticket=' . $ticket_id;
                $html = '<p>Hi ' . htmlspecialchars($ticket['name'], ENT_QUOTES, 'UTF-8') . ',</p>' .
                        '<p>Your support ticket <strong>#' . $ticket_id . '</strong> has been closed by our team.</p>' .
                        '<p>If the issue persists or you have additional questions, you can reply to the ticket to reopen it at any time.</p>' .
                        '<p><a href="' . htmlspecialchars($ticket_url, ENT_QUOTES, 'UTF-8') . '">Review the ticket</a></p>' .
                        '<p>Thank you,<br>' . SITE_NAME . ' Support</p>';

                notify_ticket_user(
                    $ticket,
                    '[' . SITE_NAME . '] Ticket #' . $ticket_id . ' closed',
                    $html,
                    "Your ticket #{$ticket_id} was closed. Reply via {$ticket_url} if you need more help.",
                    $mailer
                );

                $success_message = 'Ticket closed successfully!';
            } else {
                $error_message = 'Error closing ticket';
            }
            break;

        case 'reopen_ticket':
            $ticket_id = isset($_POST['ticket_id']) ? (int) $_POST['ticket_id'] : 0;
            $ticket = fetch_ticket_with_user($db, $ticket_id);

            if (!$ticket) {
                $error_message = 'Ticket not found';
                break;
            }

            $update_query = "UPDATE support_tickets SET status = 'open', updated_at = NOW() WHERE id = :ticket_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindValue(':ticket_id', $ticket_id, PDO::PARAM_INT);

            if ($update_stmt->execute()) {
                if (!empty($ticket['user_id'])) {
                    create_notification(
                        $db,
                        (int) $ticket['user_id'],
                        'Ticket #' . $ticket_id . ' reopened by support',
                        'We reopened your ticket and will continue working on it.',
                        'warning'
                    );
                }

                $ticket_url = SITE_URL . '/support-tickets.php?ticket=' . $ticket_id;
                $html = '<p>Hi ' . htmlspecialchars($ticket['name'], ENT_QUOTES, 'UTF-8') . ',</p>' .
                        '<p>Your support ticket <strong>#' . $ticket_id . '</strong> has been reopened for further investigation.</p>' .
                        '<p>Feel free to add additional context or files that might help us resolve the issue faster.</p>' .
                        '<p><a href="' . htmlspecialchars($ticket_url, ENT_QUOTES, 'UTF-8') . '">View the ticket</a></p>';

                notify_ticket_user(
                    $ticket,
                    '[' . SITE_NAME . '] Ticket #' . $ticket_id . ' reopened',
                    $html,
                    "Your ticket #{$ticket_id} was reopened. View it: {$ticket_url}",
                    $mailer
                );

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
$tickets_query = "SELECT st.*, u.username AS account_username, u.email AS account_email,
                  (SELECT COUNT(*) FROM support_replies WHERE ticket_id = st.id) as reply_count,
                  (SELECT sr.message FROM support_replies sr WHERE sr.ticket_id = st.id ORDER BY sr.created_at DESC LIMIT 1) AS last_reply_message,
                  (SELECT sr.created_at FROM support_replies sr WHERE sr.ticket_id = st.id ORDER BY sr.created_at DESC LIMIT 1) AS last_reply_at
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
                    st.updated_at DESC
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
    SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as `high_priority`
    FROM support_tickets";
$ticket_stats_stmt = $db->prepare($ticket_stats_query);
$ticket_stats_stmt->execute();
$ticket_stats = $ticket_stats_stmt->fetch(PDO::FETCH_ASSOC);

$response_metrics_query = "SELECT
        AVG(TIMESTAMPDIFF(MINUTE, st.created_at,
            (SELECT MIN(sr.created_at) FROM support_replies sr WHERE sr.ticket_id = st.id AND sr.sender_type = 'admin')
        )) AS avg_first_reply,
        AVG(TIMESTAMPDIFF(MINUTE, st.created_at,
            COALESCE((SELECT MAX(sr.created_at) FROM support_replies sr WHERE sr.ticket_id = st.id), st.updated_at)
        )) AS avg_resolution
    FROM support_tickets st";
$response_metrics_stmt = $db->prepare($response_metrics_query);
$response_metrics_stmt->execute();
$response_metrics = $response_metrics_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$avg_first_reply_minutes = isset($response_metrics['avg_first_reply']) && $response_metrics['avg_first_reply'] !== null
    ? (int) round((float) $response_metrics['avg_first_reply'])
    : null;
$avg_resolution_minutes = isset($response_metrics['avg_resolution']) && $response_metrics['avg_resolution'] !== null
    ? (int) round((float) $response_metrics['avg_resolution'])
    : null;

$oldest_open_stmt = $db->prepare("SELECT TIMESTAMPDIFF(MINUTE, st.created_at, NOW()) AS age_minutes
    FROM support_tickets st
    WHERE st.status = 'open'
    ORDER BY st.created_at ASC
    LIMIT 1");
$oldest_open_stmt->execute();
$oldest_open_minutes = $oldest_open_stmt->fetchColumn();
$oldest_open_minutes = $oldest_open_minutes !== false ? (int) $oldest_open_minutes : null;

$high_priority_stmt = $db->prepare("SELECT st.*, u.username AS account_username
    FROM support_tickets st
    LEFT JOIN users u ON st.user_id = u.id
    WHERE st.priority = 'high' AND st.status != 'closed'
    ORDER BY st.updated_at ASC
    LIMIT 5");
$high_priority_stmt->execute();
$high_priority_queue = $high_priority_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$awaiting_user_stmt = $db->prepare("SELECT st.*, u.username AS account_username
    FROM support_tickets st
    LEFT JOIN users u ON st.user_id = u.id
    WHERE st.status = 'replied'
    ORDER BY st.updated_at DESC
    LIMIT 5");
$awaiting_user_stmt->execute();
$awaiting_user_tickets = $awaiting_user_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

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
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Awaiting Support</div>
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
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Awaiting User Reply</div>
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
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">High Priority Open</div>
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

            <div class="row mb-4">
                <div class="col-md-4 mb-3 mb-md-0">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Average First Reply</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo htmlspecialchars(admin_format_minutes($avg_first_reply_minutes)); ?></div>
                            <small class="text-muted">Time from ticket creation until first admin response.</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3 mb-md-0">
                    <div class="card border-left-secondary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Average Resolution</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo htmlspecialchars(admin_format_minutes($avg_resolution_minutes)); ?></div>
                            <small class="text-muted">Based on last reply or ticket closure.</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-left-dark shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">Oldest Open Ticket</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo htmlspecialchars(admin_format_minutes($oldest_open_minutes)); ?></div>
                            <small class="text-muted">Elapsed time since the oldest open ticket was created.</small>
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

            <div class="row mb-4">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <div class="card shadow-sm h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">High Priority Queue</h5>
                            <span class="badge bg-danger"><?php echo count($high_priority_queue); ?></span>
                        </div>
                        <div class="list-group list-group-flush">
                            <?php if (empty($high_priority_queue)): ?>
                                <div class="list-group-item text-muted">No high priority tickets waiting.</div>
                            <?php else: ?>
                                <?php foreach ($high_priority_queue as $queue_ticket): ?>
                                    <?php
                                        $wait_minutes = (int) round((time() - strtotime($queue_ticket['updated_at'])) / 60);
                                    ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong>#<?php echo (int) $queue_ticket['id']; ?> · <?php echo htmlspecialchars($queue_ticket['subject']); ?></strong>
                                                <div class="text-muted small">By <?php echo htmlspecialchars($queue_ticket['account_username'] ?: $queue_ticket['email']); ?> · <?php echo time_ago($queue_ticket['created_at']); ?></div>
                                            </div>
                                            <span class="badge bg-danger-subtle text-danger">Waiting <?php echo admin_format_minutes($wait_minutes); ?></span>
                                        </div>
                                        <div class="mt-2">
                                            <button class="btn btn-sm btn-outline-primary" onclick="replyToTicket(<?php echo (int) $queue_ticket['id']; ?>, '<?php echo htmlspecialchars($queue_ticket['subject'], ENT_QUOTES, 'UTF-8'); ?>')">
                                                <i class="fas fa-bolt"></i> Quick reply
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Awaiting User Follow-up</h5>
                            <span class="badge bg-info"><?php echo count($awaiting_user_tickets); ?></span>
                        </div>
                        <div class="list-group list-group-flush">
                            <?php if (empty($awaiting_user_tickets)): ?>
                                <div class="list-group-item text-muted">No tickets waiting on users.</div>
                            <?php else: ?>
                                <?php foreach ($awaiting_user_tickets as $user_ticket): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong>#<?php echo (int) $user_ticket['id']; ?> · <?php echo htmlspecialchars($user_ticket['subject']); ?></strong>
                                                <div class="text-muted small">Last admin reply <?php echo time_ago($user_ticket['updated_at']); ?> · User <?php echo htmlspecialchars($user_ticket['account_username'] ?: $user_ticket['email']); ?></div>
                                            </div>
                                            <button class="btn btn-sm btn-outline-secondary" onclick="viewTicketHistory(<?php echo (int) $user_ticket['id']; ?>)"><i class="fas fa-eye"></i></button>
                                        </div>
                                        <div class="text-muted small mt-2">
                                            Encourage the user to respond to keep momentum.
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
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
                                            <strong>From:</strong> <?php echo htmlspecialchars($ticket['account_username'] ?: $ticket['email']); ?> •
                                            <strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?> •
                                            <strong>Replies:</strong> <?php echo $ticket['reply_count']; ?>
                                            <?php if (!empty($ticket['last_reply_at'])): ?>
                                                • <strong>Last reply:</strong> <?php echo time_ago($ticket['last_reply_at']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <?php if (!empty($ticket['last_reply_message'])): ?>
                                        <div class="alert alert-light mt-3 mb-0">
                                            <strong>Latest reply:</strong> <?php echo htmlspecialchars(mb_strimwidth($ticket['last_reply_message'], 0, 160, '…')); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-4">
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-primary btn-sm"
                                                onclick="replyToTicket(<?php echo (int) $ticket['id']; ?>, '<?php echo htmlspecialchars($ticket['subject'], ENT_QUOTES, 'UTF-8'); ?>')">
                                            <i class="fas fa-reply"></i> Reply
                                        </button>

                                        <?php if ($ticket['status'] !== 'closed'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="close_ticket">
                                                <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket['id']; ?>">
                                                <button type="submit" class="btn btn-success btn-sm w-100">
                                                    <i class="fas fa-check"></i> Close Ticket
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="reopen_ticket">
                                                <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket['id']; ?>">
                                                <button type="submit" class="btn btn-warning btn-sm w-100">
                                                    <i class="fas fa-undo"></i> Reopen
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <button class="btn btn-info btn-sm"
                                                onclick="viewTicketHistory(<?php echo (int) $ticket['id']; ?>)">
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
