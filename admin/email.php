<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/MailService.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();

if (!$auth->isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$success_message = '';
$error_message = '';
$mailService = MailService::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'send_email') {
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $messageTemplate = trim((string) ($_POST['message'] ?? ''));
        $target = $_POST['target'] ?? 'newsletter';
        $sendType = $_POST['send_type'] ?? 'immediate';
        $sendType = in_array($sendType, ['immediate', 'queue'], true) ? $sendType : 'immediate';

        if ($subject === '' || $messageTemplate === '') {
            $error_message = 'Subject and message are required.';
        } else {
            $recipientQuery = '';
            switch ($target) {
                case 'all_users':
                    $recipientQuery = "SELECT email, username FROM users WHERE is_banned = 0";
                    break;
                case 'active_users':
                    $recipientQuery = "SELECT email, username FROM users WHERE is_banned = 0 AND last_active >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                    break;
                case 'new_users':
                    $recipientQuery = "SELECT email, username FROM users WHERE is_banned = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    break;
                case 'newsletter':
                default:
                    $recipientQuery = "SELECT email, username FROM users WHERE is_banned = 0 AND email_notifications = 1";
                    $target = 'newsletter';
                    break;
            }

            $recipientStmt = $db->prepare($recipientQuery);
            $recipientStmt->execute();
            $recipients = $recipientStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$recipients) {
                $error_message = 'No recipients found for the selected audience.';
            } else {
                $renderEmail = static function (string $content, string $recipientName): array {
                    $safeName = htmlspecialchars($recipientName !== '' ? $recipientName : 'there', ENT_QUOTES, 'UTF-8');
                    $personalised = str_replace(['{{name}}', '{{username}}'], $safeName, $content);
                    $isLikelyHtml = $personalised !== strip_tags($personalised);
                    if (!$isLikelyHtml) {
                        $personalised = nl2br(htmlspecialchars($personalised, ENT_QUOTES, 'UTF-8'));
                    }

                    $html = '<!DOCTYPE html>' .
                        '<html lang="en"><head><meta charset="UTF-8"><title>' . htmlspecialchars($safeName, ENT_QUOTES, 'UTF-8') . '</title></head>' .
                        '<body style="margin:0;padding:24px;background-color:#f5f7fa;font-family:Arial,sans-serif;">' .
                        '<div style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:12px;padding:32px;box-shadow:0 20px 45px rgba(15,23,42,0.08);">' .
                        '<h2 style="margin:0 0 12px;font-size:24px;color:#111827;">' . htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') . '</h2>' .
                        '<p style="margin:0 0 24px;font-size:16px;color:#374151;">Hello ' . $safeName . ',</p>' .
                        '<div style="font-size:15px;line-height:1.6;color:#111827;">' . $personalised . '</div>' .
                        '<p style="margin:32px 0 0;font-size:14px;color:#6b7280;">Best regards,<br>' . htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') . ' Team</p>' .
                        '</div>' .
                        '<p style="margin:24px auto 0;text-align:center;font-size:12px;color:#9ca3af;max-width:480px;">You are receiving this email because you have an account on ' . htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') . '.</p>' .
                        '</body></html>';

                    $plainBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], PHP_EOL, $personalised));
                    $plainBody = html_entity_decode($plainBody, ENT_QUOTES, 'UTF-8');
                    $plainBody = 'Hello ' . ($recipientName !== '' ? $recipientName : 'there') . "\n\n" . trim($plainBody) . "\n\n" . SITE_NAME . ' Team';

                    return [$html, $plainBody];
                };

                $db->beginTransaction();
                try {
                    $campaignStmt = $db->prepare("INSERT INTO email_campaigns (subject, message, target_audience, recipient_count, sent_by) VALUES (:subject, :message, :target, :count, :admin)");
                    $campaignStmt->execute([
                        ':subject' => $subject,
                        ':message' => $messageTemplate,
                        ':target' => $target,
                        ':count' => count($recipients),
                        ':admin' => $_SESSION['user_id'] ?? null,
                    ]);

                    $campaignId = (int) $db->lastInsertId();
                    $queueInsert = $db->prepare("INSERT INTO email_queue (campaign_id, recipient_email, recipient_name, subject, message, error_message, status) VALUES (:campaign, :email, :name, :subject, :message, NULL, 'pending')");
                    $queueUpdate = $db->prepare("UPDATE email_queue SET status = :status, sent_at = :sent_at, error_message = :error WHERE id = :id");

                    $sentCount = 0;
                    $failedCount = 0;
                    $queuedCount = 0;

                    foreach ($recipients as $recipient) {
                        $recipientEmail = trim((string) ($recipient['email'] ?? ''));
                        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                            continue;
                        }

                        $recipientName = trim((string) ($recipient['username'] ?? ''));
                        [$htmlBody, $plainBody] = $renderEmail($messageTemplate, $recipientName);

                        $queueInsert->execute([
                            ':campaign' => $campaignId,
                            ':email' => $recipientEmail,
                            ':name' => $recipientName,
                            ':subject' => $subject,
                            ':message' => $htmlBody,
                        ]);

                        $queueId = (int) $db->lastInsertId();

                        if ($sendType === 'immediate') {
                            $result = $mailService->send(
                                [['email' => $recipientEmail, 'name' => $recipientName]],
                                $subject,
                                $htmlBody,
                                [
                                    'text' => $plainBody,
                                    'reply_to' => ['email' => SITE_EMAIL, 'name' => SITE_NAME],
                                ]
                            );

                            $status = $result['success'] ? 'sent' : 'failed';
                            $error = $result['success'] ? null : substr((string) $result['message'], 0, 500);
                            $timestamp = date('Y-m-d H:i:s');
                            $queueUpdate->execute([
                                ':status' => $status,
                                ':sent_at' => $timestamp,
                                ':error' => $error,
                                ':id' => $queueId,
                            ]);

                            if ($result['success']) {
                                $sentCount++;
                            } else {
                                $failedCount++;
                            }
                        } else {
                            $queuedCount++;
                        }
                    }

                    $db->commit();

                    $parts = [];
                    if ($sentCount > 0) {
                        $parts[] = number_format($sentCount) . ' sent';
                    }
                    if ($queuedCount > 0) {
                        $parts[] = number_format($queuedCount) . ' queued';
                    }
                    if ($failedCount > 0) {
                        $parts[] = number_format($failedCount) . ' failed';
                    }

                    $success_message = 'Email campaign processed successfully. ' . implode(', ', $parts ?: ['No deliveries were made.']);
                } catch (Throwable $exception) {
                    $db->rollBack();
                    $error_message = 'Failed to process the campaign: ' . $exception->getMessage();
                }
            }
        }
    }
}

$statsQuery = $db->prepare("SELECT
    (SELECT COUNT(*) FROM email_campaigns) AS total_campaigns,
    (SELECT COALESCE(SUM(recipient_count), 0) FROM email_campaigns) AS total_recipients,
    (SELECT COUNT(*) FROM email_queue WHERE status = 'sent') AS sent_emails,
    (SELECT COUNT(*) FROM email_queue WHERE status = 'failed') AS failed_emails,
    (SELECT COUNT(*) FROM email_queue WHERE status = 'pending') AS pending_emails,
    (SELECT MAX(sent_at) FROM email_queue WHERE status = 'sent') AS last_sent_at");
$statsQuery->execute();
$email_stats = $statsQuery->fetch(PDO::FETCH_ASSOC) ?: [];

$totalProcessed = (int) ($email_stats['sent_emails'] ?? 0) + (int) ($email_stats['failed_emails'] ?? 0);
$email_stats['delivery_rate'] = $totalProcessed > 0 ? round(((int) $email_stats['sent_emails'] / $totalProcessed) * 100, 1) : null;

$insightsQuery = $db->prepare("SELECT
    COALESCE(AVG(recipient_count), 0) AS avg_recipients,
    COALESCE(MAX(recipient_count), 0) AS max_recipients,
    COALESCE(MIN(recipient_count), 0) AS min_recipients
    FROM email_campaigns");
$insightsQuery->execute();
$insights = $insightsQuery->fetch(PDO::FETCH_ASSOC) ?: [];

$recentCampaignsStmt = $db->prepare("SELECT ec.*, u.username AS sent_by_username,
    SUM(CASE WHEN eq.status = 'sent' THEN 1 ELSE 0 END) AS sent_count,
    SUM(CASE WHEN eq.status = 'failed' THEN 1 ELSE 0 END) AS failed_count,
    SUM(CASE WHEN eq.status = 'pending' THEN 1 ELSE 0 END) AS pending_count
    FROM email_campaigns ec
    LEFT JOIN email_queue eq ON eq.campaign_id = ec.id
    LEFT JOIN users u ON ec.sent_by = u.id
    GROUP BY ec.id
    ORDER BY ec.created_at DESC
    LIMIT 10");
$recentCampaignsStmt->execute();
$recent_campaigns = $recentCampaignsStmt->fetchAll(PDO::FETCH_ASSOC);

$audienceBreakdownStmt = $db->prepare("SELECT target_audience, COUNT(*) AS campaigns, SUM(recipient_count) AS recipients
    FROM email_campaigns
    GROUP BY target_audience
    ORDER BY recipients DESC");
$audienceBreakdownStmt->execute();
$audience_breakdown = $audienceBreakdownStmt->fetchAll(PDO::FETCH_ASSOC);

$deliveryTrendStmt = $db->prepare("SELECT DATE(sent_at) AS day,
    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent_count,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_count
    FROM email_queue
    WHERE sent_at IS NOT NULL AND sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(sent_at)
    ORDER BY DATE(sent_at)");
$deliveryTrendStmt->execute();
$delivery_trend = $deliveryTrendStmt->fetchAll(PDO::FETCH_ASSOC);

$pendingQueueStmt = $db->prepare("SELECT recipient_email, subject, created_at FROM email_queue WHERE status = 'pending' ORDER BY created_at ASC LIMIT 10");
$pendingQueueStmt->execute();
$pending_queue = $pendingQueueStmt->fetchAll(PDO::FETCH_ASSOC);

$failedQueueStmt = $db->prepare("SELECT recipient_email, subject, error_message, sent_at FROM email_queue WHERE status = 'failed' ORDER BY sent_at DESC LIMIT 10");
$failedQueueStmt->execute();
$failed_queue = $failedQueueStmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Email Management - Admin Panel';
include 'includes/admin_header.php';

$trendLabels = array_map(static fn ($row) => $row['day'], $delivery_trend);
$trendSent = array_map(static fn ($row) => (int) $row['sent_count'], $delivery_trend);
$trendFailed = array_map(static fn ($row) => (int) $row['failed_count'], $delivery_trend);

$audienceLabels = array_map(static fn ($row) => ucfirst(str_replace('_', ' ', (string) $row['target_audience'])), $audience_breakdown);
$audienceCounts = array_map(static fn ($row) => (int) $row['recipients'], $audience_breakdown);
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-0">Email Operations</h1>
                    <p class="text-muted mb-0">Monitor campaign performance, deliverability, and queue health.</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sendEmailModal">
                    <i class="fas fa-paper-plane me-2"></i>New Campaign
                </button>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <span class="text-uppercase text-muted small">Delivery Rate</span>
                            <h3 class="mt-2 mb-0">
                                <?php echo $email_stats['delivery_rate'] !== null ? $email_stats['delivery_rate'] . '%': '—'; ?>
                            </h3>
                            <p class="text-muted mb-0">Successful deliveries vs failures</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <span class="text-uppercase text-muted small">Emails Sent</span>
                            <h3 class="mt-2 mb-0"><?php echo number_format((int) ($email_stats['sent_emails'] ?? 0)); ?></h3>
                            <p class="text-muted mb-0">Lifetime processed emails</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <span class="text-uppercase text-muted small">Pending Queue</span>
                            <h3 class="mt-2 mb-0"><?php echo number_format((int) ($email_stats['pending_emails'] ?? 0)); ?></h3>
                            <p class="text-muted mb-0">Awaiting dispatch</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <span class="text-uppercase text-muted small">Failed Attempts</span>
                            <h3 class="mt-2 mb-0"><?php echo number_format((int) ($email_stats['failed_emails'] ?? 0)); ?></h3>
                            <p class="text-muted mb-0">Investigate bounce reasons</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">30-Day Delivery Trend</h6>
                            <span class="text-muted small">Sent vs failed</span>
                        </div>
                        <div class="card-body">
                            <canvas id="deliveryTrendChart" height="220"></canvas>
                            <?php if (empty($trendLabels)): ?>
                                <p class="text-muted text-center mt-3 mb-0">No delivery activity recorded in the past 30 days.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-0">
                            <h6 class="mb-0">Campaign Insights</h6>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                                <li class="mb-3">
                                    <strong><?php echo number_format((int) ($email_stats['total_campaigns'] ?? 0)); ?></strong>
                                    <span class="text-muted">total campaigns launched</span>
                                </li>
                                <li class="mb-3">
                                    <strong><?php echo number_format((float) ($insights['avg_recipients'] ?? 0), 1); ?></strong>
                                    <span class="text-muted">average recipients per campaign</span>
                                </li>
                                <li class="mb-3">
                                    <strong><?php echo number_format((int) ($email_stats['total_recipients'] ?? 0)); ?></strong>
                                    <span class="text-muted">emails targeted overall</span>
                                </li>
                                <li class="mb-0 text-muted small">
                                    Last delivery:
                                    <?php echo !empty($email_stats['last_sent_at']) ? date('M j, Y g:i A', strtotime($email_stats['last_sent_at'])) : 'No deliveries yet'; ?>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-0">
                            <h6 class="mb-0">Audience Breakdown</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="audienceBreakdownChart" height="220"></canvas>
                            <?php if (empty($audienceLabels)): ?>
                                <p class="text-muted text-center mt-3 mb-0">Audience data will appear once campaigns are sent.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-0">
                            <h6 class="mb-0">Recent Campaigns</h6>
                        </div>
                        <div class="card-body p-0">
                            <?php if ($recent_campaigns): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Subject</th>
                                                <th>Audience</th>
                                                <th>Recipients</th>
                                                <th>Progress</th>
                                                <th>Owner</th>
                                                <th>Created</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_campaigns as $campaign): ?>
                                                <?php
                                                    $totalRecipients = (int) ($campaign['recipient_count'] ?? 0);
                                                    $sent = (int) ($campaign['sent_count'] ?? 0);
                                                    $failed = (int) ($campaign['failed_count'] ?? 0);
                                                    $pending = (int) ($campaign['pending_count'] ?? 0);
                                                    $complete = $totalRecipients > 0 ? min(100, round(($sent / $totalRecipients) * 100)) : 0;
                                                ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($campaign['subject']); ?></strong>
                                                        <?php if (!empty($campaign['failed_count'])): ?>
                                                            <span class="badge bg-danger-subtle text-danger ms-2">Attention</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><span class="badge bg-primary-subtle text-primary"><?php echo ucfirst(str_replace('_', ' ', (string) $campaign['target_audience'])); ?></span></td>
                                                    <td><?php echo number_format($totalRecipients); ?></td>
                                                    <td style="min-width:180px;">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="progress flex-grow-1" style="height: 6px;">
                                                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $complete; ?>%"></div>
                                                            </div>
                                                            <span class="text-muted small"><?php echo $sent; ?>/<?php echo $totalRecipients ?: '—'; ?></span>
                                                        </div>
                                                        <div class="mt-1 small text-muted">
                                                            <span class="text-success">Sent: <?php echo $sent; ?></span>
                                                            <span class="ms-2 text-warning">Pending: <?php echo $pending; ?></span>
                                                            <span class="ms-2 text-danger">Failed: <?php echo $failed; ?></span>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($campaign['sent_by_username'] ?? 'System'); ?></td>
                                                    <td><?php echo date('M j, Y g:i A', strtotime($campaign['created_at'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="p-4 text-center text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3"></i>
                                    <p class="mb-0">Launch a campaign to start gathering performance data.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-5">
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-0">
                            <h6 class="mb-0">Pending Queue</h6>
                        </div>
                        <div class="card-body p-0">
                            <?php if ($pending_queue): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Email</th>
                                                <th>Subject</th>
                                                <th>Queued</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pending_queue as $pending): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($pending['recipient_email']); ?></td>
                                                    <td><?php echo htmlspecialchars($pending['subject']); ?></td>
                                                    <td><?php echo date('M j, Y g:i A', strtotime($pending['created_at'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="p-4 text-center text-muted">
                                    <p class="mb-0">No emails waiting in the queue.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-0">
                            <h6 class="mb-0">Recent Failures</h6>
                        </div>
                        <div class="card-body p-0">
                            <?php if ($failed_queue): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Email</th>
                                                <th>Subject</th>
                                                <th>Error</th>
                                                <th>When</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($failed_queue as $failed): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($failed['recipient_email']); ?></td>
                                                    <td><?php echo htmlspecialchars($failed['subject']); ?></td>
                                                    <td class="text-danger small"><?php echo htmlspecialchars($failed['error_message'] ?? 'Unknown'); ?></td>
                                                    <td><?php echo $failed['sent_at'] ? date('M j, Y g:i A', strtotime($failed['sent_at'])) : '—'; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="p-4 text-center text-muted">
                                    <p class="mb-0">No failures recorded recently.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<div class="modal fade" id="sendEmailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Email Campaign</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="send_email">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" name="subject" class="form-control" placeholder="Campaign subject" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea name="message" class="form-control" rows="8" placeholder="Compose your message. Use {{name}} to personalise." required></textarea>
                        <small class="text-muted">HTML supported. Use {{name}} or {{username}} to insert the recipient name.</small>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Audience</label>
                            <select name="target" class="form-select" required>
                                <option value="newsletter">Newsletter Subscribers</option>
                                <option value="all_users">All Users</option>
                                <option value="active_users">Active Users (30 days)</option>
                                <option value="new_users">New Users (7 days)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Dispatch Mode</label>
                            <select name="send_type" class="form-select" required>
                                <option value="immediate">Send immediately</option>
                                <option value="queue">Queue for later</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Launch Campaign</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const deliveryCtx = document.getElementById('deliveryTrendChart');
        const trendData = {
            labels: <?php echo json_encode($trendLabels, JSON_UNESCAPED_SLASHES); ?>,
            datasets: [
                {
                    label: 'Sent',
                    data: <?php echo json_encode($trendSent, JSON_UNESCAPED_SLASHES); ?>,
                    borderColor: '#1cc88a',
                    backgroundColor: 'rgba(28, 200, 138, 0.15)',
                    tension: 0.35,
                    fill: true,
                },
                {
                    label: 'Failed',
                    data: <?php echo json_encode($trendFailed, JSON_UNESCAPED_SLASHES); ?>,
                    borderColor: '#e74a3b',
                    backgroundColor: 'rgba(231, 74, 59, 0.15)',
                    tension: 0.35,
                    fill: true,
                }
            ]
        };

        if (deliveryCtx && trendData.labels.length) {
            new Chart(deliveryCtx, {
                type: 'line',
                data: trendData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true
                        }
                    }
                }
            });
        }

        const audienceCtx = document.getElementById('audienceBreakdownChart');
        const audienceData = {
            labels: <?php echo json_encode($audienceLabels, JSON_UNESCAPED_SLASHES); ?>,
            datasets: [
                {
                    data: <?php echo json_encode($audienceCounts, JSON_UNESCAPED_SLASHES); ?>,
                    backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e'],
                }
            ]
        };

        if (audienceCtx && audienceData.labels.length) {
            new Chart(audienceCtx, {
                type: 'doughnut',
                data: audienceData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    });
</script>

<?php include 'includes/admin_footer.php'; ?>
