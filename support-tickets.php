<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/support_helpers.php';
require_once __DIR__ . '/includes/MailService.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();
ensure_support_schema($db);

$mailer = MailService::getInstance();
$user = $auth->isLoggedIn() ? $auth->getCurrentUser() : null;
$user_id = $user['id'] ?? null;

$ownerCondition = '';
$ownerParams = [];
if ($user) {
    $parts = [];
    if (!empty($user_id)) {
        $parts[] = 'st.user_id = :user_id';
        $ownerParams[':user_id'] = (int) $user_id;
    }
    if (!empty($user['email'])) {
        $parts[] = 'st.email = :email';
        $ownerParams[':email'] = $user['email'];
    }
    if ($parts) {
        $ownerCondition = '(' . implode(' OR ', $parts) . ')';
    }
}

function bind_owner_params(\PDOStatement $stmt, array $params): void {
    foreach ($params as $key => $value) {
        $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $type);
    }
}

function format_minutes_label(?int $minutes): string {
    if ($minutes === null) {
        return '—';
    }

    if ($minutes < 60) {
        return $minutes . ' min';
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

function get_admin_users(PDO $db): array {
    $stmt = $db->query("SELECT id, username, email FROM users WHERE is_admin = 1 AND is_banned = 0");
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

$success_message = '';
$error_message = '';
$ticket_created = false;
$selected_ticket_id = isset($_GET['ticket']) ? (int) $_GET['ticket'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create_ticket';

    switch ($action) {
        case 'reply_ticket':
            if (!$auth->isLoggedIn() || !$ownerCondition) {
                $error_message = 'Please log in to reply to your support tickets.';
                break;
            }

            $ticket_id = isset($_POST['ticket_id']) ? (int) $_POST['ticket_id'] : 0;
            $reply_message = trim($_POST['reply_message'] ?? '');

            if ($ticket_id <= 0) {
                $error_message = 'Invalid ticket selected.';
                break;
            }

            if ($reply_message === '') {
                $error_message = 'Reply message cannot be empty.';
                break;
            }

            $ticket_query = $db->prepare("SELECT st.* FROM support_tickets st WHERE st.id = :ticket_id AND {$ownerCondition} LIMIT 1");
            $ticket_query->bindValue(':ticket_id', $ticket_id, PDO::PARAM_INT);
            bind_owner_params($ticket_query, $ownerParams);
            $ticket_query->execute();
            $ticket = $ticket_query->fetch(PDO::FETCH_ASSOC);

            if (!$ticket) {
                $error_message = 'Ticket not found or you do not have permission to reply.';
                break;
            }

            $reply_insert = $db->prepare("INSERT INTO support_replies (ticket_id, admin_id, user_id, message, sender_type) VALUES (:ticket_id, NULL, :user_id, :message, 'user')");
            $reply_insert->bindValue(':ticket_id', $ticket_id, PDO::PARAM_INT);
            $reply_insert->bindValue(':user_id', $user_id, PDO::PARAM_INT);
            $reply_insert->bindValue(':message', $reply_message);

            if ($reply_insert->execute()) {
                $update_ticket = $db->prepare("UPDATE support_tickets SET status = 'open', updated_at = NOW() WHERE id = :ticket_id");
                $update_ticket->bindValue(':ticket_id', $ticket_id, PDO::PARAM_INT);
                $update_ticket->execute();

                $selected_ticket_id = $ticket_id;
                $success_message = 'Your reply has been sent to the support team.';

                $admins = get_admin_users($db);
                $adminRecipients = [];
                foreach ($admins as $admin) {
                    if (!empty($admin['email'])) {
                        $adminRecipients[] = ['email' => $admin['email'], 'name' => $admin['username']];
                    }
                    create_notification(
                        $db,
                        (int) $admin['id'],
                        'User replied to support ticket #' . $ticket_id,
                        sprintf('%s sent a new reply: "%s"', $user['username'] ?? $ticket['name'], mb_strimwidth($reply_message, 0, 120, '…')),
                        'info'
                    );
                }

                if (defined('ADMIN_EMAIL') && ADMIN_EMAIL && empty($adminRecipients)) {
                    $adminRecipients[] = ADMIN_EMAIL;
                }

                $ticket_url = SITE_URL . '/support-tickets.php?ticket=' . $ticket_id;
                $html = '<p>Hello support team,</p>' .
                        '<p><strong>' . htmlspecialchars($user['username'] ?? $ticket['name']) . '</strong> has replied to support ticket <strong>#' . $ticket_id . '</strong>.</p>' .
                        '<blockquote style="border-left:4px solid #38bdf8;padding-left:12px;margin:12px 0;">' . nl2br(htmlspecialchars($reply_message)) . '</blockquote>' .
                        '<p><a href="' . htmlspecialchars($ticket_url) . '" style="color:#0ea5e9;">View the conversation in the dashboard</a>.</p>';

                $mailer->send(
                    $adminRecipients,
                    '[' . SITE_NAME . '] New reply for ticket #' . $ticket_id,
                    $html,
                    [
                        'text' => strip_tags($reply_message . "\n\nView conversation: " . $ticket_url),
                    ]
                );
            } else {
                $error_message = 'Failed to submit your reply. Please try again.';
            }
            break;

        case 'create_ticket':
        default:
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            if ($user) {
                $name = $user['username'];
                $email = $user['email'];
            }

            $subject = trim($_POST['subject'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $priority = $_POST['priority'] ?? 'medium';
            $priority = in_array($priority, ['low', 'medium', 'high'], true) ? $priority : 'medium';

            if ($name === '' || $email === '' || $subject === '' || $message === '') {
                $error_message = 'Please complete all fields before submitting your ticket.';
                break;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error_message = 'Please enter a valid email address.';
                break;
            }

            $insert_ticket = $db->prepare("INSERT INTO support_tickets (user_id, name, email, subject, message, priority) VALUES (:user_id, :name, :email, :subject, :message, :priority)");
            $insert_ticket->bindValue(':user_id', $user_id, $user_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $insert_ticket->bindValue(':name', $name);
            $insert_ticket->bindValue(':email', $email);
            $insert_ticket->bindValue(':subject', $subject);
            $insert_ticket->bindValue(':message', $message);
            $insert_ticket->bindValue(':priority', $priority);

            if ($insert_ticket->execute()) {
                $ticket_id = (int) $db->lastInsertId();
                $ticket_created = true;
                $selected_ticket_id = $ticket_id;
                $_POST = [];

                if ($user_id) {
                    create_notification(
                        $db,
                        $user_id,
                        'Support ticket #' . $ticket_id . ' created',
                        'We have received your request. Our team will review it and respond shortly.',
                        'success'
                    );
                }

                $admins = get_admin_users($db);
                $adminRecipients = [];
                foreach ($admins as $admin) {
                    if (!empty($admin['email'])) {
                        $adminRecipients[] = ['email' => $admin['email'], 'name' => $admin['username']];
                    }
                    create_notification(
                        $db,
                        (int) $admin['id'],
                        'New support ticket #' . $ticket_id,
                        sprintf('%s opened a %s priority ticket: "%s"', $name, ucfirst($priority), mb_strimwidth($subject, 0, 100, '…')),
                        'warning'
                    );
                }

                if (defined('ADMIN_EMAIL') && ADMIN_EMAIL && empty($adminRecipients)) {
                    $adminRecipients[] = ADMIN_EMAIL;
                }

                $ticket_url = SITE_URL . '/support-tickets.php?ticket=' . $ticket_id;
                $adminHtml = '<p>A new support ticket has been created.</p>' .
                             '<ul>' .
                             '<li><strong>ID:</strong> #' . $ticket_id . '</li>' .
                             '<li><strong>Subject:</strong> ' . htmlspecialchars($subject) . '</li>' .
                             '<li><strong>Priority:</strong> ' . ucfirst($priority) . '</li>' .
                             '<li><strong>From:</strong> ' . htmlspecialchars($name) . ' (' . htmlspecialchars($email) . ')</li>' .
                             '</ul>' .
                             '<p><strong>Message:</strong></p>' .
                             '<blockquote style="border-left:4px solid #38bdf8;padding-left:12px;margin:12px 0;">' . nl2br(htmlspecialchars($message)) . '</blockquote>' .
                             '<p><a href="' . htmlspecialchars($ticket_url) . '" style="color:#0ea5e9;">Open ticket in dashboard</a></p>';

                $mailer->send(
                    $adminRecipients,
                    '[' . SITE_NAME . '] New support ticket #' . $ticket_id,
                    $adminHtml,
                    [
                        'text' => strip_tags("Ticket #{$ticket_id}: {$subject}\n{$message}\nView: {$ticket_url}"),
                    ]
                );

                $userHtml = '<p>Hi ' . htmlspecialchars($name) . ',</p>' .
                            '<p>We received your support ticket <strong>#' . $ticket_id . '</strong> with the subject <em>' . htmlspecialchars($subject) . '</em>.</p>' .
                            '<p>Our team will get back to you shortly. You can follow the conversation on your dashboard at any time.</p>' .
                            '<p><a href="' . htmlspecialchars($ticket_url) . '" style="color:#0ea5e9;">View your ticket</a></p>' .
                            '<p>Thank you,<br>' . SITE_NAME . ' Support</p>';

                $mailer->send(
                    [$email => $name],
                    '[' . SITE_NAME . '] Ticket #' . $ticket_id . ' received',
                    $userHtml,
                    [
                        'text' => "We received your ticket #{$ticket_id}. View: {$ticket_url}",
                    ]
                );

                $success_message = 'Ticket created successfully! Our support team has been notified.';
            } else {
                $error_message = 'There was an issue submitting your ticket. Please try again later.';
            }
            break;
    }
}

$ticketInsights = [
    'total' => 0,
    'awaiting_support' => 0,
    'awaiting_user' => 0,
    'closed' => 0,
    'critical_open' => 0,
    'avg_minutes' => null,
    'last_activity' => null,
];

$userTickets = [];
$selectedTicket = null;
$ticketReplies = [];

if ($ownerCondition) {
    $insights_stmt = $db->prepare("SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN st.status = 'open' THEN 1 ELSE 0 END) AS awaiting_support,
            SUM(CASE WHEN st.status = 'replied' THEN 1 ELSE 0 END) AS awaiting_user,
            SUM(CASE WHEN st.status = 'closed' THEN 1 ELSE 0 END) AS closed,
            SUM(CASE WHEN st.priority = 'high' AND st.status != 'closed' THEN 1 ELSE 0 END) AS critical_open
        FROM support_tickets st
        WHERE {$ownerCondition}");
    bind_owner_params($insights_stmt, $ownerParams);
    $insights_stmt->execute();
    $insights_data = $insights_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    foreach (['total', 'awaiting_support', 'awaiting_user', 'closed', 'critical_open'] as $key) {
        $ticketInsights[$key] = isset($insights_data[$key]) ? (int) $insights_data[$key] : 0;
    }

    $avg_stmt = $db->prepare("SELECT AVG(response_minutes) AS avg_minutes FROM (
            SELECT TIMESTAMPDIFF(MINUTE, st.created_at,
                (SELECT MIN(sr.created_at) FROM support_replies sr WHERE sr.ticket_id = st.id AND sr.sender_type = 'admin')
            ) AS response_minutes
            FROM support_tickets st
            WHERE {$ownerCondition}
        ) AS responses
        WHERE response_minutes IS NOT NULL");
    bind_owner_params($avg_stmt, $ownerParams);
    $avg_stmt->execute();
    $avgValue = $avg_stmt->fetchColumn();
    $ticketInsights['avg_minutes'] = $avgValue !== false ? (int) round((float) $avgValue) : null;

    $activity_stmt = $db->prepare("SELECT MAX(updated_at) AS last_activity FROM support_tickets st WHERE {$ownerCondition}");
    bind_owner_params($activity_stmt, $ownerParams);
    $activity_stmt->execute();
    $activity_row = $activity_stmt->fetch(PDO::FETCH_ASSOC);
    if ($activity_row && $activity_row['last_activity']) {
        $ticketInsights['last_activity'] = $activity_row['last_activity'];
    }

    $tickets_stmt = $db->prepare("SELECT st.*, 
            (SELECT sr.created_at FROM support_replies sr WHERE sr.ticket_id = st.id ORDER BY sr.created_at DESC LIMIT 1) AS last_reply_at,
            (SELECT sr.sender_type FROM support_replies sr WHERE sr.ticket_id = st.id ORDER BY sr.created_at DESC LIMIT 1) AS last_reply_sender,
            (SELECT sr.message FROM support_replies sr WHERE sr.ticket_id = st.id ORDER BY sr.created_at DESC LIMIT 1) AS last_reply_message
        FROM support_tickets st
        WHERE {$ownerCondition}
        ORDER BY st.updated_at DESC, st.created_at DESC");
    bind_owner_params($tickets_stmt, $ownerParams);
    $tickets_stmt->execute();
    $userTickets = $tickets_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if ($selected_ticket_id) {
        $ticket_stmt = $db->prepare("SELECT * FROM support_tickets st WHERE st.id = :ticket_id AND {$ownerCondition} LIMIT 1");
        $ticket_stmt->bindValue(':ticket_id', $selected_ticket_id, PDO::PARAM_INT);
        bind_owner_params($ticket_stmt, $ownerParams);
        $ticket_stmt->execute();
        $selectedTicket = $ticket_stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if (!$selectedTicket && !empty($userTickets)) {
        $selectedTicket = $userTickets[0];
        $selected_ticket_id = (int) $selectedTicket['id'];
    }

    if ($selectedTicket) {
        $replies_stmt = $db->prepare("SELECT sr.*, admin.username AS admin_username, user.username AS user_username
            FROM support_replies sr
            LEFT JOIN users admin ON sr.admin_id = admin.id
            LEFT JOIN users user ON sr.user_id = user.id
            WHERE sr.ticket_id = :ticket_id
            ORDER BY sr.created_at ASC");
        $replies_stmt->bindValue(':ticket_id', $selectedTicket['id'], PDO::PARAM_INT);
        $replies_stmt->execute();
        $ticketReplies = $replies_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

$prefillName = htmlspecialchars($_POST['name'] ?? ($user['username'] ?? ''), ENT_QUOTES, 'UTF-8');
$prefillEmail = htmlspecialchars($_POST['email'] ?? ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8');
$prefillSubject = htmlspecialchars($_POST['subject'] ?? '', ENT_QUOTES, 'UTF-8');
$prefillMessage = htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES, 'UTF-8');
$prefillPriority = $_POST['priority'] ?? 'medium';

$page_title = 'Support Tickets - ' . SITE_NAME;
$page_description = 'Manage support requests, continue conversations with the team, and open new tickets.';
$current_page = 'dashboard';

include 'includes/header.php';
?>

<div class="page-wrapper flex-grow-1">
    <section class="page-hero pb-0">
        <div class="container">
            <div class="glass-card p-4 p-lg-5 animate-fade-in" data-aos="fade-up">
                <div class="d-flex flex-column flex-lg-row align-items-lg-start justify-content-between gap-4">
                    <div class="flex-grow-1">
                        <div class="dashboard-breadcrumb mb-3">
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">Support</li>
                                </ol>
                            </nav>
                        </div>
                        <h1 class="text-white fw-bold mb-2">Support Center</h1>
                        <p class="text-muted mb-0">Track your open tickets, review detailed insights, and continue conversations with the support team.</p>
                    </div>
                    <div class="text-lg-end">
                        <div class="option-chip justify-content-center ms-lg-auto">
                            <i class="fas fa-sparkles"></i>
                            <span>Average response within 12 hours</span>
                        </div>
                        <a href="#new-ticket" class="btn btn-theme btn-outline-glass mt-3">
                            <i class="fas fa-circle-plus me-2"></i>Open New Ticket
                        </a>
                    </div>
                </div>
            </div>
            <div class="ad-slot dev-slot mt-4">Hero Banner 970x250</div>
        </div>
    </section>

    <section class="pb-5" id="support-center">
        <div class="container">
            <div class="ad-slot dev-slot2 mb-4">Inline Ad 728x90</div>

            <?php if ($error_message): ?>
                <div class="alert alert-glass alert-danger mb-4" role="alert">
                    <span class="icon text-danger"><i class="fas fa-circle-exclamation"></i></span>
                    <div><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-glass alert-success mb-4" role="alert">
                    <span class="icon text-success"><i class="fas fa-check-circle"></i></span>
                    <div><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            <?php elseif ($ticket_created && !$auth->isLoggedIn()): ?>
                <div class="alert alert-glass alert-info mb-4" role="alert">
                    <span class="icon text-info"><i class="fas fa-envelope-open-text"></i></span>
                    <div>We sent a confirmation email with your ticket reference. Keep it safe so you can follow up easily.</div>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-xl-4">
                    <?php if ($auth->isLoggedIn()): ?>
                        <div class="glass-card p-4 mb-4 animate-fade-in" data-aos="fade-up">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h2 class="h5 text-white mb-1">Ticket Overview</h2>
                                    <p class="text-muted small mb-0">Quick insight into how your support requests are progressing.</p>
                                </div>
                                <span class="badge rounded-pill bg-primary-subtle text-primary fw-semibold">Total <?php echo $ticketInsights['total']; ?></span>
                            </div>

                            <div class="ticket-progress">
                                <?php
                                $statusBreakdown = [
                                    ['label' => 'Awaiting Support', 'value' => $ticketInsights['awaiting_support'], 'class' => 'bg-warning'],
                                    ['label' => 'Awaiting Your Reply', 'value' => $ticketInsights['awaiting_user'], 'class' => 'bg-info'],
                                    ['label' => 'Closed', 'value' => $ticketInsights['closed'], 'class' => 'bg-secondary'],
                                ];
                                $totalTickets = max(1, $ticketInsights['total']);
                                foreach ($statusBreakdown as $row):
                                    $percentage = $ticketInsights['total'] ? round(($row['value'] / $totalTickets) * 100) : 0;
                                ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between small mb-1">
                                            <span class="text-muted"><?php echo htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="text-white fw-semibold"><?php echo $row['value']; ?> (<?php echo $percentage; ?>%)</span>
                                        </div>
                                        <div class="progress progress-thin">
                                            <div class="progress-bar <?php echo $row['class']; ?>" role="progressbar" style="width: <?php echo $percentage; ?>%" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="d-flex flex-column gap-3 mt-4">
                                <div class="insight-chip">
                                    <div class="icon bg-info"><i class="fas fa-stopwatch"></i></div>
                                    <div>
                                        <span class="label text-muted">Average first response</span>
                                        <span class="value text-white fw-semibold"><?php echo htmlspecialchars(format_minutes_label($ticketInsights['avg_minutes']), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </div>
                                <div class="insight-chip">
                                    <div class="icon bg-danger"><i class="fas fa-siren-on"></i></div>
                                    <div>
                                        <span class="label text-muted">High priority awaiting</span>
                                        <span class="value text-white fw-semibold"><?php echo $ticketInsights['critical_open']; ?></span>
                                    </div>
                                </div>
                                <div class="insight-chip">
                                    <div class="icon bg-success"><i class="fas fa-clock"></i></div>
                                    <div>
                                        <span class="label text-muted">Last update</span>
                                        <span class="value text-white fw-semibold"><?php echo $ticketInsights['last_activity'] ? time_ago($ticketInsights['last_activity']) : '—'; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="glass-card p-4 animate-fade-in" data-aos="fade-up" data-aos-delay="100">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h2 class="h5 text-white mb-0">My Tickets</h2>
                                <a href="#new-ticket" class="btn btn-sm btn-outline-light rounded-pill"><i class="fas fa-plus me-1"></i>New ticket</a>
                            </div>

                            <?php if (empty($userTickets)): ?>
                                <div class="empty-state text-center py-4">
                                    <i class="fas fa-inbox fa-2x text-muted mb-3"></i>
                                    <p class="text-muted mb-1">No tickets yet.</p>
                                    <small class="text-muted">You can open a ticket using the form to the right.</small>
                                </div>
                            <?php else: ?>
                                <div class="ticket-list d-flex flex-column gap-3">
                                    <?php foreach ($userTickets as $ticket):
                                        $isActive = $selectedTicket && (int) $selectedTicket['id'] === (int) $ticket['id'];
                                        $statusBadge = format_ticket_status($ticket['status']);
                                        $priorityBadge = format_ticket_priority($ticket['priority']);
                                        $lastUpdate = $ticket['last_reply_at'] ?? $ticket['updated_at'] ?? $ticket['created_at'];
                                        ?>
                                        <a href="support-tickets.php?ticket=<?php echo (int) $ticket['id']; ?>" class="ticket-list-item <?php echo $isActive ? 'active' : ''; ?>">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h3 class="h6 text-white mb-1">#<?php echo (int) $ticket['id']; ?> · <?php echo htmlspecialchars($ticket['subject'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                                    <div class="d-flex gap-2">
                                                        <span class="<?php echo $statusBadge['class']; ?> badge-pill"><?php echo htmlspecialchars($statusBadge['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                        <span class="<?php echo $priorityBadge['class']; ?> badge-pill"><?php echo htmlspecialchars($priorityBadge['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                    </div>
                                                </div>
                                                <small class="text-muted">Updated <?php echo time_ago($lastUpdate); ?></small>
                                            </div>
                                            <?php if (!empty($ticket['last_reply_message'])): ?>
                                                <p class="text-muted small mb-0">“<?php echo htmlspecialchars(mb_strimwidth($ticket['last_reply_message'], 0, 80, '…'), ENT_QUOTES, 'UTF-8'); ?>”</p>
                                            <?php else: ?>
                                                <p class="text-muted small mb-0">No replies yet</p>
                                            <?php endif; ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="glass-card p-4 animate-fade-in" data-aos="fade-up">
                            <h2 class="h5 text-white mb-2">Stay updated on your tickets</h2>
                            <p class="text-muted">Create an account or sign in to see the status of your requests, receive real-time notifications, and reply to the support team from one place.</p>
                            <div class="d-flex flex-wrap gap-2 mt-3">
                                <a href="login.php" class="btn btn-theme btn-gradient"><i class="fas fa-right-to-bracket me-2"></i>Login</a>
                                <a href="register.php" class="btn btn-theme btn-outline-glass"><i class="fas fa-user-plus me-2"></i>Create account</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-xl-8">
                    <?php if ($auth->isLoggedIn() && $selectedTicket):
                        $statusBadge = format_ticket_status($selectedTicket['status']);
                        $priorityBadge = format_ticket_priority($selectedTicket['priority']);
                        ?>
                        <div class="glass-card p-4 p-lg-5 mb-4 animate-fade-in" data-aos="fade-up">
                            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-4">
                                <div>
                                    <span class="badge bg-dark-subtle text-dark fw-semibold mb-2">Ticket #<?php echo (int) $selectedTicket['id']; ?></span>
                                    <h2 class="h4 text-white mb-1"><?php echo htmlspecialchars($selectedTicket['subject'], ENT_QUOTES, 'UTF-8'); ?></h2>
                                    <p class="text-muted mb-2">Opened <?php echo date('M j, Y g:i A', strtotime($selectedTicket['created_at'])); ?> · Updated <?php echo time_ago($selectedTicket['updated_at']); ?></p>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="<?php echo $statusBadge['class']; ?> badge-pill"><?php echo htmlspecialchars($statusBadge['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="<?php echo $priorityBadge['class']; ?> badge-pill"><?php echo htmlspecialchars($priorityBadge['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </div>
                                <div class="text-lg-end">
                                    <small class="text-muted d-block">Assigned to support</small>
                                    <?php if ($selectedTicket['status'] === 'closed'): ?>
                                        <span class="badge bg-success-subtle text-success fw-semibold"><i class="fas fa-lock me-1"></i>Resolved</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary-subtle text-primary fw-semibold"><i class="fas fa-comments me-1"></i>In progress</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="support-thread">
                                <div class="support-message support-message-user">
                                    <div class="support-meta">
                                        <strong><?php echo htmlspecialchars($selectedTicket['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <span>· <?php echo date('M j, Y g:i A', strtotime($selectedTicket['created_at'])); ?></span>
                                    </div>
                                    <div class="support-body"><?php echo nl2br(htmlspecialchars($selectedTicket['message'], ENT_QUOTES, 'UTF-8')); ?></div>
                                </div>

                                <?php foreach ($ticketReplies as $reply):
                                    $senderType = $reply['sender_type'] ?? 'admin';
                                    $isAdmin = $senderType === 'admin';
                                    $author = $isAdmin
                                        ? ($reply['admin_username'] ?? 'Support Team')
                                        : ($reply['user_username'] ?? $selectedTicket['name']);
                                    $messageClass = $isAdmin ? 'support-message-admin' : 'support-message-user';
                                    ?>
                                    <div class="support-message <?php echo $messageClass; ?>">
                                        <div class="support-meta">
                                            <strong><?php echo htmlspecialchars($author, ENT_QUOTES, 'UTF-8'); ?><?php echo $isAdmin ? ' · Support' : ''; ?></strong>
                                            <span>· <?php echo date('M j, Y g:i A', strtotime($reply['created_at'])); ?></span>
                                        </div>
                                        <div class="support-body"><?php echo nl2br(htmlspecialchars($reply['message'], ENT_QUOTES, 'UTF-8')); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($selectedTicket['status'] !== 'closed'): ?>
                                <form method="POST" class="support-reply-form mt-4">
                                    <input type="hidden" name="action" value="reply_ticket">
                                    <input type="hidden" name="ticket_id" value="<?php echo (int) $selectedTicket['id']; ?>">
                                    <label for="reply_message" class="form-label text-muted text-uppercase small">Reply to support</label>
                                    <textarea id="reply_message" name="reply_message" class="form-control form-control-lg" rows="4" placeholder="Type your response here..." required></textarea>
                                    <div class="d-flex justify-content-between align-items-center gap-3 mt-3 flex-wrap">
                                        <small class="text-muted">Replies notify the support team instantly. They will respond as soon as possible.</small>
                                        <button type="submit" class="btn btn-theme btn-gradient"><i class="fas fa-paper-plane me-2"></i>Send reply</button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-glass alert-secondary mt-4 mb-0">
                                    <i class="fas fa-circle-check me-2"></i>This ticket is closed. Open a new ticket if you need further assistance on this topic.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($auth->isLoggedIn()): ?>
                        <div class="glass-card p-4 p-lg-5 mb-4 animate-fade-in" data-aos="fade-up">
                            <div class="text-center py-5">
                                <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                <h2 class="h4 text-white mb-2">Select a ticket to view the conversation</h2>
                                <p class="text-muted mb-0">Pick a ticket from the list on the left to review updates and reply to the support team.</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="glass-card p-4 p-lg-5 animate-fade-in" data-aos="fade-up" id="new-ticket">
                        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
                            <div>
                                <h2 class="h4 text-white mb-1">Open a new support ticket</h2>
                                <p class="text-muted mb-0">Provide details and our response team will reach out. Attach as much context as possible.</p>
                            </div>
                            <div class="option-chip">
                                <i class="fas fa-shield-halved"></i>
                                <span>Secure & confidential</span>
                            </div>
                        </div>

                        <form method="POST" class="row g-3">
                            <input type="hidden" name="action" value="create_ticket">
                            <div class="col-md-6">
                                <label for="name" class="form-label text-uppercase small text-muted">Your name</label>
                                <input type="text" id="name" name="name" class="form-control form-control-lg" placeholder="Enter your full name" value="<?php echo $prefillName; ?>" <?php echo $auth->isLoggedIn() ? 'readonly' : 'required'; ?>>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label text-uppercase small text-muted">Email address</label>
                                <input type="email" id="email" name="email" class="form-control form-control-lg" placeholder="you@example.com" value="<?php echo $prefillEmail; ?>" <?php echo $auth->isLoggedIn() ? 'readonly' : 'required'; ?>>
                            </div>
                            <div class="col-12">
                                <label for="subject" class="form-label text-uppercase small text-muted">Subject</label>
                                <input type="text" id="subject" name="subject" class="form-control form-control-lg" placeholder="Brief summary of your issue" value="<?php echo $prefillSubject; ?>" required>
                            </div>
                            <div class="col-12 col-lg-6">
                                <label for="priority" class="form-label text-uppercase small text-muted">Priority</label>
                                <select id="priority" name="priority" class="form-select form-select-lg">
                                    <option value="low" <?php echo $prefillPriority === 'low' ? 'selected' : ''; ?>>Low · General inquiry</option>
                                    <option value="medium" <?php echo $prefillPriority === 'medium' ? 'selected' : ''; ?>>Medium · Account help</option>
                                    <option value="high" <?php echo $prefillPriority === 'high' ? 'selected' : ''; ?>>High · Urgent issue</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="message" class="form-label text-uppercase small text-muted">Message</label>
                                <textarea id="message" name="message" class="form-control form-control-lg" rows="6" placeholder="Explain what happened, include links or evidence if relevant" required><?php echo $prefillMessage; ?></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-theme btn-gradient w-100">
                                    <i class="fas fa-life-ring me-2"></i>Submit ticket
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>
