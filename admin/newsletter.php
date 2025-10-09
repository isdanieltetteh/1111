<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/MailService.php';
require_once __DIR__ . '/../includes/email_template.php';
require_once __DIR__ . '/../includes/newsletter_helpers.php';

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
$mailService = MailService::getInstance();

$defaultPreheader = 'Newsletter updates from ' . SITE_NAME;
$messageHtml = isset($_POST['message_html']) ? (string) $_POST['message_html'] : email_default_content_html();
$messageText = isset($_POST['message_text']) ? (string) $_POST['message_text'] : email_default_content_text();
$preheader = isset($_POST['preheader']) ? trim((string) $_POST['preheader']) : $defaultPreheader;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'send_newsletter':
            $subject = trim((string) ($_POST['subject'] ?? ''));
            $messageHtml = (string) ($_POST['message_html'] ?? $messageHtml);
            $messageText = (string) ($_POST['message_text'] ?? $messageText);
            $preheader = trim((string) ($_POST['preheader'] ?? $preheader));
            $target_preferences = $_POST['target_preferences'] ?? [];

            if ($subject === '' || trim(strip_tags($messageHtml)) === '') {
                $error_message = 'Subject and newsletter content are required';
                break;
            }

            $where_conditions = ['ns.is_active = 1', 'ns.verified_at IS NOT NULL'];
            $params = [];

            if (!empty($target_preferences)) {
                $preference_conditions = [];
                foreach ($target_preferences as $index => $pref) {
                    $preference_conditions[] = "JSON_CONTAINS(ns.preferences, ?)";
                    $params[":pref{$index}"] = '"' . $pref . '"';
                }
                $where_conditions[] = '(' . implode(' OR ', $preference_conditions) . ')';
            }

            $where_clause = implode(' AND ', $where_conditions);

            $subscribers_query = "SELECT ns.email, COALESCE(u.username, '') AS username
                                FROM newsletter_subscriptions ns
                                LEFT JOIN users u ON ns.user_id = u.id
                                WHERE {$where_clause}";
            $subscribers_stmt = $db->prepare($subscribers_query);
            if (!empty($params)) {
                $subscribers_stmt->execute(array_values($params));
            } else {
                $subscribers_stmt->execute();
            }
            $subscribers = $subscribers_stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($subscribers)) {
                $error_message = 'No subscribers found for selected preferences';
                break;
            }

            $db->beginTransaction();
            try {
                $target_audience = $target_preferences ? implode(',', $target_preferences) : 'newsletter';
                $campaign_stmt = $db->prepare("INSERT INTO email_campaigns (subject, message, target_audience, recipient_count, sent_by) VALUES (:subject, :message, :target, :count, :admin)");
                $campaign_stmt->execute([
                    ':subject' => $subject,
                    ':message' => $messageHtml,
                    ':target' => $target_audience,
                    ':count' => count($subscribers),
                    ':admin' => $_SESSION['user_id'] ?? null,
                ]);

                $campaignId = (int) $db->lastInsertId();
                $queueInsert = $db->prepare("INSERT INTO email_queue (campaign_id, recipient_email, recipient_name, subject, message, error_message, status) VALUES (:campaign, :email, :name, :subject, :message, NULL, 'pending')");
                $queueUpdate = $db->prepare("UPDATE email_queue SET status = :status, sent_at = :sent_at, error_message = :error WHERE id = :id");

                $renderEmail = function (string $htmlTemplate, string $textTemplate, string $subjectLine, string $preheaderText, string $recipientName, string $recipientEmail): array {
                    $displayName = $recipientName !== '' ? $recipientName : 'there';
                    $context = email_build_context([
                        'subject' => $subjectLine,
                        'preheader' => $preheaderText,
                        'name' => $displayName,
                        'username' => $displayName,
                        'unsubscribe_url' => newsletter_unsubscribe_url($recipientEmail),
                    ]);

                    [$htmlBody, $plainBody] = email_render_bodies($htmlTemplate, $textTemplate, $context, $preheaderText);

                    return [$htmlBody, $plainBody, $context];
                };

                $sentCount = 0;
                $failedCount = 0;

                foreach ($subscribers as $subscriber) {
                    $recipientEmail = trim((string) ($subscriber['email'] ?? ''));
                    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                        continue;
                    }

                    $recipientName = trim((string) ($subscriber['username'] ?? ''));
                    [$htmlBody, $plainBody, $context] = $renderEmail($messageHtml, $messageText, $subject, $preheader, $recipientName, $recipientEmail);
                    $unsubscribeUrl = $context['unsubscribe_url'] ?? newsletter_unsubscribe_url($recipientEmail);

                    $queueInsert->execute([
                        ':campaign' => $campaignId,
                        ':email' => $recipientEmail,
                        ':name' => $recipientName,
                        ':subject' => $subject,
                        ':message' => $htmlBody,
                    ]);

                    $queueId = (int) $db->lastInsertId();

                    $result = $mailService->send(
                        [['email' => $recipientEmail, 'name' => $recipientName]],
                        $subject,
                        $htmlBody,
                        [
                            'text' => $plainBody,
                            'reply_to' => ['email' => SITE_EMAIL, 'name' => SITE_NAME],
                            'list_unsubscribe' => [$unsubscribeUrl, 'mailto:' . SITE_EMAIL],
                            'list_unsubscribe_post' => true,
                            'custom_headers' => [
                                'X-Campaign-ID' => $campaignId,
                                'X-Entity-Ref-ID' => 'newsletter:' . $campaignId . ':' . $queueId,
                            ],
                        ]
                    );

                    $status = $result['success'] ? 'sent' : 'failed';
                    $error = $result['success'] ? null : substr((string) $result['message'], 0, 500);
                    $queueUpdate->execute([
                        ':status' => $status,
                        ':sent_at' => date('Y-m-d H:i:s'),
                        ':error' => $error,
                        ':id' => $queueId,
                    ]);

                    if ($result['success']) {
                        $sentCount++;
                    } else {
                        $failedCount++;
                    }
                }

                $db->commit();

                $parts = [];
                if ($sentCount > 0) {
                    $parts[] = number_format($sentCount) . ' delivered';
                }
                if ($failedCount > 0) {
                    $parts[] = number_format($failedCount) . ' failed';
                }

                $success_message = 'Newsletter processed successfully: ' . implode(', ', $parts ?: ['no emails were sent.']);
            } catch (Throwable $throwable) {
                $db->rollBack();
                $error_message = 'Unable to send newsletter: ' . $throwable->getMessage();
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
                                                        'scam_alerts' => 'ðŸš¨ Scam Alerts',
                                                        'new_sites' => 'ðŸ†• New Sites',
                                                        'weekly_digest' => 'ðŸ“Š Weekly Digest',
                                                        'high_paying' => 'ðŸ’° High Paying',
                                                        'platform_updates' => 'ðŸ”” Updates',
                                                        'earning_tips' => 'ðŸ’¡ Tips'
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
                        <label class="form-label">Preheader</label>
                        <input type="text" name="preheader" class="form-control" value="<?php echo htmlspecialchars($preheader, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Inbox preview copy">
                        <small class="text-muted">Displayed next to the subject line in most email clients.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">HTML content</label>
                        <textarea name="message_html" class="form-control rich-text-editor" rows="12" data-editor="wysiwyg"><?php echo htmlspecialchars($messageHtml, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <small class="text-muted">You can reference {{name}}, {{site_name}} and {{unsubscribe_url}} to personalise the campaign.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Plain text fallback</label>
                        <textarea name="message_text" class="form-control" rows="6" placeholder="Optional text-only version."><?php echo htmlspecialchars($messageText, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <small class="text-muted">Leave blank to auto-generate a readable plain text email.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Target Preferences</label>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="target_preferences[]" value="scam_alerts" class="form-check-input">
                                    <label class="form-check-label">ðŸš¨ Scam Alerts (<?php echo $stats['scam_alert_subs']; ?>)</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="target_preferences[]" value="new_sites" class="form-check-input">
                                    <label class="form-check-label">ðŸ†• New Sites (<?php echo $stats['new_site_subs']; ?>)</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="target_preferences[]" value="weekly_digest" class="form-check-input">
                                    <label class="form-check-label">ðŸ“Š Weekly Digest (<?php echo $stats['weekly_digest_subs']; ?>)</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="target_preferences[]" value="high_paying" class="form-check-input">
                                    <label class="form-check-label">ðŸ’° High Paying Sites</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="target_preferences[]" value="platform_updates" class="form-check-input">
                                    <label class="form-check-label">ðŸ”” Platform Updates</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="target_preferences[]" value="earning_tips" class="form-check-input">
                                    <label class="form-check-label">ðŸ’¡ Earning Tips</label>
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

<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.2/tinymce.min.js" referrerpolicy="origin"></script>
<script>
function exportSubscribers() {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = '<input type="hidden" name="action" value="export_subscribers">';
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

document.addEventListener('DOMContentLoaded', function () {
    if (typeof tinymce !== 'undefined') {
        tinymce.init({
            selector: 'textarea.rich-text-editor',
            height: 420,
            menubar: false,
            plugins: 'autoresize code link lists table',
            toolbar: 'undo redo | styles | bold italic underline | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist | link table | removeformat | code',
            branding: false,
            convert_urls: false,
            relative_urls: false,
            skin: document.documentElement.classList.contains('dark-mode') ? 'oxide-dark' : 'oxide',
            content_css: document.documentElement.classList.contains('dark-mode') ? 'dark' : 'default',
            autoresize_bottom_margin: 16
        });
    }
});
</script>

<?php include 'includes/admin_footer.php'; ?>
