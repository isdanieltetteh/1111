<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();

// Redirect if not logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user = $auth->getCurrentUser();
$user_id = $_SESSION['user_id'];

$feedback_message = $_SESSION['ad_feedback'] ?? null;
$feedback_type = $_SESSION['ad_feedback_type'] ?? 'success';
unset($_SESSION['ad_feedback'], $_SESSION['ad_feedback_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ad_id'], $_POST['action'])) {
    $ad_id = intval($_POST['ad_id']);
    $action = $_POST['action'];

    $ad_stmt = $db->prepare("SELECT * FROM user_advertisements WHERE id = :ad_id AND user_id = :user_id");
    $ad_stmt->bindParam(':ad_id', $ad_id, PDO::PARAM_INT);
    $ad_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $ad_stmt->execute();
    $ad = $ad_stmt->fetch(PDO::FETCH_ASSOC);

    $valid_actions = ['pause', 'resume', 'delete'];
    if (!$ad || !in_array($action, $valid_actions, true)) {
        $_SESSION['ad_feedback'] = 'Unable to process the requested action.';
        $_SESSION['ad_feedback_type'] = 'danger';
        header('Location: my-ads.php');
        exit();
    }

    $is_performance_campaign = in_array($ad['campaign_type'], ['cpc', 'cpm'], true);

    try {
        switch ($action) {
            case 'pause':
                if (!$is_performance_campaign || $ad['status'] !== 'active') {
                    throw new RuntimeException('Only active CPC/CPM campaigns can be paused.');
                }
                $pause_stmt = $db->prepare("UPDATE user_advertisements SET status = 'paused' WHERE id = :ad_id");
                $pause_stmt->bindParam(':ad_id', $ad_id, PDO::PARAM_INT);
                $pause_stmt->execute();
                $_SESSION['ad_feedback'] = 'Campaign paused successfully.';
                $_SESSION['ad_feedback_type'] = 'success';
                break;

            case 'resume':
                if (!$is_performance_campaign || $ad['status'] !== 'paused') {
                    throw new RuntimeException('Only paused CPC/CPM campaigns can be resumed.');
                }
                if ($ad['budget_remaining'] <= 0) {
                    throw new RuntimeException('Add more budget before resuming this campaign.');
                }
                $resume_stmt = $db->prepare("UPDATE user_advertisements SET status = 'active', start_date = COALESCE(start_date, NOW()) WHERE id = :ad_id");
                $resume_stmt->bindParam(':ad_id', $ad_id, PDO::PARAM_INT);
                $resume_stmt->execute();
                $_SESSION['ad_feedback'] = 'Campaign resumed and set to active.';
                $_SESSION['ad_feedback_type'] = 'success';
                break;

            case 'delete':
                if (!$is_performance_campaign) {
                    throw new RuntimeException('Only CPC/CPM campaigns can be removed from this view.');
                }
                if ($ad['status'] === 'active') {
                    throw new RuntimeException('Pause the campaign before deleting it.');
                }

                $refund_amount = (float) $ad['budget_remaining'];
                $db->beginTransaction();

                if ($refund_amount > 0) {
                    $refund_stmt = $db->prepare("UPDATE users SET credits = credits + :refund WHERE id = :user_id");
                    $refund_stmt->bindParam(':refund', $refund_amount);
                    $refund_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                    $refund_stmt->execute();

                    $ad_tx = $db->prepare("INSERT INTO ad_transactions (ad_id, user_id, amount, transaction_type, description) VALUES (:ad_id, :user_id, :amount, 'refund', :description)");
                    $ad_tx->bindParam(':ad_id', $ad_id, PDO::PARAM_INT);
                    $ad_tx->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                    $refund_amount_negative = -$refund_amount;
                    $ad_tx->bindParam(':amount', $refund_amount_negative);
                    $description = 'Unused budget refund';
                    $ad_tx->bindParam(':description', $description);
                    $ad_tx->execute();

                    $credit_tx = $db->prepare("INSERT INTO credit_transactions (user_id, amount, type, description, status) VALUES (:user_id, :amount, 'refund', :description, 'completed')");
                    $credit_tx->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                    $credit_tx->bindParam(':amount', $refund_amount);
                    $credit_tx->bindParam(':description', $description);
                    $credit_tx->execute();
                }

                $delete_stmt = $db->prepare("DELETE FROM user_advertisements WHERE id = :ad_id");
                $delete_stmt->bindParam(':ad_id', $ad_id, PDO::PARAM_INT);
                $delete_stmt->execute();

                $db->commit();

                $_SESSION['ad_feedback'] = 'Campaign removed and any unused budget refunded.';
                $_SESSION['ad_feedback_type'] = 'success';
                break;
        }
    } catch (Exception $exception) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        $_SESSION['ad_feedback'] = $exception->getMessage();
        $_SESSION['ad_feedback_type'] = 'danger';
    }

    header('Location: my-ads.php');
    exit();
}

// Get user's advertisements with dimensions
$ads_query = "SELECT ua.*,
              DATEDIFF(ua.end_date, NOW()) as days_remaining,
              (SELECT COUNT(*) FROM ad_impressions WHERE ad_id = ua.id) as total_impressions,
              (SELECT COUNT(*) FROM ad_clicks WHERE ad_id = ua.id) as total_clicks
              FROM user_advertisements ua
              WHERE ua.user_id = :user_id
              ORDER BY ua.created_at DESC";
$ads_stmt = $db->prepare($ads_query);
$ads_stmt->bindParam(':user_id', $user_id);
$ads_stmt->execute();
$user_ads = $ads_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT
    COUNT(*) as total_ads,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_ads,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_ads,
    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_ads,
    SUM(CASE WHEN campaign_type IN ('cpc','cpm') THEN total_spent ELSE cost_paid + premium_cost END) as total_spent,
    SUM(CASE WHEN campaign_type IN ('cpc','cpm') THEN budget_total ELSE cost_paid + premium_cost END) as total_budget,
    SUM(CASE WHEN campaign_type IN ('cpc','cpm') THEN budget_remaining ELSE 0 END) as remaining_budget,
    SUM(impression_count) as total_impressions,
    SUM(click_count) as total_clicks
    FROM user_advertisements
    WHERE user_id = :user_id";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':user_id', $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$total_budget = isset($stats['total_budget']) ? (float) $stats['total_budget'] : 0.0;
$total_spent = isset($stats['total_spent']) ? (float) $stats['total_spent'] : 0.0;
$remaining_budget = isset($stats['remaining_budget']) ? (float) $stats['remaining_budget'] : 0.0;

$avg_ctr = $stats['total_impressions'] > 0
    ? ($stats['total_clicks'] / $stats['total_impressions']) * 100
    : 0;

function timeRemaining($datetime) {
    if (!$datetime) return 'N/A';
    $time = strtotime($datetime) - time();
    if ($time <= 0) return 'Expired';
    
    $days = floor($time / 86400);
    $hours = floor(($time % 86400) / 3600);
    
    if ($days > 0) return $days . 'd ' . $hours . 'h';
    return $hours . 'h';
}

$page_title = 'My Campaigns - ' . SITE_NAME;
$page_description = 'Monitor every ad campaign you run on ' . SITE_NAME . ' with live performance insights.';
$current_page = 'dashboard';

$additional_head = ($additional_head ?? '') . "\n<script src=\"https://cdn.jsdelivr.net/npm/chart.js\"></script>\n";

include 'includes/header.php';
?>

<div class="page-wrapper flex-grow-1">
    <section class="page-hero pb-0">
        <div class="container">
            <div class="glass-card p-4 p-lg-5 animate-fade-in" data-aos="fade-up">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4">
                    <div class="flex-grow-1">
                        <div class="dashboard-breadcrumb mb-3">
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">My Campaigns</li>
                                </ol>
                            </nav>
                        </div>
                        <h1 class="text-white fw-bold mb-2">Advertise with Confidence</h1>
                        <p class="text-muted mb-0">Review pacing, placements, and returns for every sponsored slot you are running across the directory.</p>
                    </div>
                    <div class="text-lg-end">
                        <div class="option-chip justify-content-center ms-lg-auto">
                            <i class="fas fa-bullhorn"></i>
                            <span><?php echo number_format($stats['total_ads']); ?> total campaigns</span>
                        </div>
                        <a href="buy-ads.php" class="btn btn-theme btn-gradient mt-3">
                            <i class="fas fa-plus me-2"></i>Create Campaign
                        </a>
                    </div>
                </div>
            </div>
            <div class="ad-slot dev-slot mt-4">Campaign Banner 970x250</div>
        </div>
    </section>

    <section class="pb-5">
        <div class="container">
            <?php if (!empty($feedback_message)): ?>
                <div class="alert alert-glass alert-<?php echo $feedback_type === 'danger' ? 'danger' : 'success'; ?> mb-4" role="alert">
                    <span class="icon text-<?php echo $feedback_type === 'danger' ? 'danger' : 'success'; ?>">
                        <i class="fas fa-info-circle"></i>
                    </span>
                    <div><?php echo htmlspecialchars($feedback_message); ?></div>
                </div>
            <?php endif; ?>

            <div class="row g-4 align-items-stretch mb-4">
                <div class="col-12 col-sm-6 col-xl-2">
                    <div class="glass-stat-tile h-100 text-center">
                        <span class="glass-stat-label">Active Campaigns</span>
                        <span class="glass-stat-value"><?php echo number_format($stats['active_ads']); ?></span>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-2">
                    <div class="glass-stat-tile h-100 text-center">
                        <span class="glass-stat-label">Pending Review</span>
                        <span class="glass-stat-value text-warning"><?php echo number_format($stats['pending_ads']); ?></span>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-2">
                    <div class="glass-stat-tile h-100 text-center">
                        <span class="glass-stat-label">Expired</span>
                        <span class="glass-stat-value text-muted"><?php echo number_format($stats['expired_ads']); ?></span>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-2">
                    <div class="glass-stat-tile h-100 text-center">
                        <span class="glass-stat-label">Total Budget</span>
                        <span class="glass-stat-value text-info">$<?php echo number_format($total_budget, 2); ?></span>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-2">
                    <div class="glass-stat-tile h-100 text-center">
                        <span class="glass-stat-label">Spend To Date</span>
                        <span class="glass-stat-value text-success">$<?php echo number_format($total_spent, 2); ?></span>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-2">
                    <div class="glass-stat-tile h-100 text-center">
                        <span class="glass-stat-label">Remaining Budget</span>
                        <span class="glass-stat-value text-warning">$<?php echo number_format($remaining_budget, 2); ?></span>
                    </div>
                </div>
            </div>

            <div class="ad-slot dev-slot2 mb-4">Inline Ad 728x90</div>

            <?php if (!empty($user_ads)): ?>
                <div class="d-grid gap-4">
                    <?php foreach ($user_ads as $ad): ?>
                        <?php
                        $status_badges = [
                            'active' => ['label' => 'Active', 'class' => 'badge rounded-pill bg-success-subtle text-success fw-semibold', 'icon' => 'fa-check-circle'],
                            'pending' => ['label' => 'Pending', 'class' => 'badge rounded-pill bg-warning-subtle text-warning-emphasis fw-semibold', 'icon' => 'fa-hourglass-half'],
                            'expired' => ['label' => 'Expired', 'class' => 'badge rounded-pill bg-secondary-subtle text-muted fw-semibold', 'icon' => 'fa-calendar-times'],
                            'rejected' => ['label' => 'Rejected', 'class' => 'badge rounded-pill bg-danger-subtle text-danger-emphasis fw-semibold', 'icon' => 'fa-times-circle'],
                            'completed' => ['label' => 'Completed', 'class' => 'badge rounded-pill bg-primary-subtle text-primary-emphasis fw-semibold', 'icon' => 'fa-flag-checkered']
                        ];
                        $status_info = $status_badges[$ad['status']] ?? ['label' => ucfirst($ad['status']), 'class' => 'badge rounded-pill bg-secondary-subtle text-muted fw-semibold', 'icon' => 'fa-circle-info'];

                        $campaign_badges = [
                            'standard' => '<span class="badge rounded-pill bg-light text-dark fw-semibold"><i class="fas fa-calendar me-1"></i>Standard</span>',
                            'cpc' => '<span class="badge rounded-pill bg-success-subtle text-success fw-semibold"><i class="fas fa-mouse-pointer me-1"></i>CPC</span>',
                            'cpm' => '<span class="badge rounded-pill bg-info-subtle text-info-emphasis fw-semibold"><i class="fas fa-chart-area me-1"></i>CPM</span>'
                        ];
                        $placement_badges = [
                            'targeted' => '<span class="badge rounded-pill bg-secondary-subtle text-muted fw-semibold"><i class="fas fa-location-dot me-1"></i>Targeted</span>',
                            'general' => '<span class="badge rounded-pill bg-primary-subtle text-primary-emphasis fw-semibold"><i class="fas fa-layer-group me-1"></i>General Rotation</span>'
                        ];

                        $ad_type_badge = $ad['ad_type'] === 'banner'
                            ? '<span class="badge rounded-pill bg-info-subtle text-info-emphasis fw-semibold"><i class="fas fa-image me-1"></i>Banner</span>'
                            : '<span class="badge rounded-pill bg-secondary-subtle text-muted fw-semibold"><i class="fas fa-align-left me-1"></i>Text Ad</span>';
                        $visibility_badge = $ad['visibility_level'] === 'premium'
                            ? '<span class="badge rounded-pill bg-warning-subtle text-warning-emphasis fw-semibold"><i class="fas fa-star me-1"></i>Premium</span>'
                            : '';

                        $is_performance = in_array($ad['campaign_type'], ['cpc', 'cpm'], true);
                        $budget_total_campaign = $is_performance ? (float) $ad['budget_total'] : (float) ($ad['cost_paid'] + $ad['premium_cost']);
                        $spend_to_date = $is_performance ? (float) $ad['total_spent'] : (float) ($ad['cost_paid'] + $ad['premium_cost']);
                        $budget_remaining_campaign = $is_performance ? (float) $ad['budget_remaining'] : max(0, $budget_total_campaign - $spend_to_date);
                        $ctr = $ad['total_impressions'] > 0
                            ? ($ad['total_clicks'] / $ad['total_impressions']) * 100
                            : 0;

                        $dimensionText = 'Flexible';
                        if (!empty($ad['target_width']) && !empty($ad['target_height'])) {
                            $dimensionText = intval($ad['target_width']) . 'x' . intval($ad['target_height']);
                        }
                        ?>
                        <div class="glass-card p-4 p-lg-5 position-relative animate-fade-in" data-aos="fade-up">
                            <div class="d-flex flex-column flex-lg-row justify-content-between gap-4">
                                <div class="flex-grow-1">
                                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                                        <div>
                                            <h2 class="h4 text-white mb-1"><?php echo htmlspecialchars($ad['title']); ?></h2>
                                            <div class="d-flex flex-wrap align-items-center gap-2">
                                                <?php echo $ad_type_badge; ?>
                                                <?php echo $campaign_badges[$ad['campaign_type']] ?? ''; ?>
                                                <?php echo $placement_badges[$ad['placement_type']] ?? ''; ?>
                                                <?php echo $visibility_badge; ?>
                                                <span class="<?php echo $status_info['class']; ?>">
                                                    <i class="fas <?php echo $status_info['icon']; ?> me-1"></i><?php echo htmlspecialchars($status_info['label']); ?>
                                                </span>
                                                <?php if ($is_performance): ?>
                                                    <span class="badge rounded-pill bg-secondary-subtle text-muted fw-semibold">
                                                        <i class="fas fa-coins me-1"></i>$<?php echo number_format($budget_remaining_campaign, 2); ?> left
                                                    </span>
                                                <?php elseif ($ad['status'] === 'active'): ?>
                                                    <span class="badge rounded-pill bg-success-subtle text-success fw-semibold">
                                                        <i class="fas fa-clock me-1"></i><?php echo timeRemaining($ad['end_date']); ?> left
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="text-lg-end d-flex flex-column align-items-lg-end gap-2">
                                            <span class="badge rounded-pill bg-secondary-subtle text-muted fw-semibold">
                                                <i class="fas fa-ruler-combined me-1"></i><?php echo htmlspecialchars($dimensionText); ?>
                                            </span>
                                            <?php if ($is_performance): ?>
                                                <div class="d-flex gap-2">
                                                    <?php if ($ad['status'] === 'active'): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="ad_id" value="<?php echo (int) $ad['id']; ?>">
                                                            <input type="hidden" name="action" value="pause">
                                                            <button type="submit" class="btn btn-outline-warning btn-sm"><i class="fas fa-pause me-1"></i>Pause</button>
                                                        </form>
                                                    <?php elseif ($ad['status'] === 'paused' && $budget_remaining_campaign > 0): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="ad_id" value="<?php echo (int) $ad['id']; ?>">
                                                            <input type="hidden" name="action" value="resume">
                                                            <button type="submit" class="btn btn-outline-success btn-sm"><i class="fas fa-play me-1"></i>Resume</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <?php if ($ad['status'] !== 'active'): ?>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Remove this campaign and refund unused budget?');">
                                                            <input type="hidden" name="ad_id" value="<?php echo (int) $ad['id']; ?>">
                                                            <input type="hidden" name="action" value="delete">
                                                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash me-1"></i>Delete</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="row g-4">
                                        <div class="col-lg-6">
                                            <div class="p-3 rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 h-100">
                                                <h6 class="text-muted text-uppercase small fw-semibold mb-3">Campaign Details</h6>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="text-muted small">Billing Model</span>
                                                    <span class="text-white fw-semibold text-uppercase"><?php echo htmlspecialchars($ad['campaign_type']); ?></span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="text-muted small">Reserved Budget</span>
                                                    <span class="text-white fw-semibold">$<?php echo number_format($budget_total_campaign, 2); ?></span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="text-muted small">Spend to Date</span>
                                                    <span class="text-success fw-semibold">$<?php echo number_format($spend_to_date, 2); ?></span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="text-muted small">Budget Remaining</span>
                                                    <span class="text-warning fw-semibold">$<?php echo number_format($budget_remaining_campaign, 2); ?></span>
                                                </div>
                                                <?php if (!$is_performance): ?>
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <span class="text-muted small">Duration</span>
                                                        <span class="text-white fw-semibold"><?php echo (int) $ad['duration_days']; ?> days</span>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="text-muted small">Destination</span>
                                                    <a href="<?php echo htmlspecialchars($ad['target_url']); ?>" target="_blank" rel="noopener" class="text-info small text-decoration-none">
                                                        <?php echo htmlspecialchars(substr($ad['target_url'], 0, 40)) . (strlen($ad['target_url']) > 40 ? '…' : ''); ?>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="p-3 rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 h-100">
                                                <h6 class="text-muted text-uppercase small fw-semibold mb-3">Performance</h6>
                                                <div class="row row-cols-3 g-2">
                                                    <div class="col">
                                                        <div class="glass-stat-tile text-center h-100">
                                                            <span class="glass-stat-label">Views</span>
                                                            <span class="glass-stat-value"><?php echo number_format($ad['total_impressions']); ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="col">
                                                        <div class="glass-stat-tile text-center h-100">
                                                            <span class="glass-stat-label">Clicks</span>
                                                            <span class="glass-stat-value"><?php echo number_format($ad['total_clicks']); ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="col">
                                                        <div class="glass-stat-tile text-center h-100">
                                                            <span class="glass-stat-label">CTR</span>
                                                            <span class="glass-stat-value text-info"><?php echo number_format($ctr, 2); ?>%</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <canvas class="performance-chart mt-3" height="160" data-impressions="<?php echo (int) $ad['total_impressions']; ?>" data-clicks="<?php echo (int) $ad['total_clicks']; ?>"></canvas>
                                                <?php if ($is_performance): ?>
                                                    <canvas class="budget-chart mt-3" height="160" data-spent="<?php echo $spend_to_date; ?>" data-remaining="<?php echo max($budget_remaining_campaign, 0); ?>"></canvas>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex-shrink-0" style="min-width: 260px;">
                                    <div class="p-3 rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 h-100">
                                        <h6 class="text-muted text-uppercase small fw-semibold mb-3">Live Preview</h6>
                                        <?php if ($ad['ad_type'] === 'banner'): ?>
                                            <?php if ($ad['banner_image']): ?>
                                                <img src="<?php echo htmlspecialchars($ad['banner_image']); ?>"
                                                     alt="<?php echo htmlspecialchars($ad['banner_alt_text']); ?>"
                                                     class="img-fluid rounded-4"
                                                     style="max-width: <?php echo $ad['width'] ? intval($ad['width']) . 'px' : '300px'; ?>; max-height: <?php echo $ad['height'] ? intval($ad['height']) . 'px' : '250px'; ?>; object-fit: contain;">
                                            <?php else: ?>
                                                <div class="text-center text-muted small">No banner uploaded</div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div>
                                                <div class="fw-bold text-info mb-1"><?php echo htmlspecialchars($ad['text_title']); ?></div>
                                                <div class="text-muted small"><?php echo htmlspecialchars($ad['text_description']); ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="glass-card p-5 text-center animate-fade-in" data-aos="fade-up">
                    <div class="display-5 mb-3 text-warning">📦</div>
                    <h3 class="text-white mb-2">No campaigns yet</h3>
                    <p class="text-muted mb-4">Launch your first promotion to showcase your project in high-intent discovery spots.</p>
                    <a href="buy-ads.php" class="btn btn-theme btn-gradient">
                        <i class="fas fa-plus me-2"></i>Launch Campaign
                    </a>
                    <div class="dev-slot1 mt-4">Starter Ad 300x250</div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php if (!empty($user_ads)): ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const performanceCharts = document.querySelectorAll('.performance-chart');
        performanceCharts.forEach(canvas => {
            const impressions = parseInt(canvas.dataset.impressions, 10) || 0;
            const clicks = parseInt(canvas.dataset.clicks, 10) || 0;

            if (impressions === 0 && clicks === 0) {
                const emptyMessage = document.createElement('div');
                emptyMessage.className = 'text-muted small fst-italic text-center mt-3';
                emptyMessage.textContent = 'Performance data will appear once impressions are recorded.';
                canvas.style.display = 'none';
                canvas.parentNode.appendChild(emptyMessage);
                return;
            }

            const ctx = canvas.getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Impressions', 'Clicks'],
                    datasets: [{
                        data: [impressions, clicks],
                        backgroundColor: ['rgba(59, 130, 246, 0.6)', 'rgba(16, 185, 129, 0.6)'],
                        borderRadius: 6,
                        maxBarThickness: 38
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label(context) {
                                    return `${context.parsed.y.toLocaleString()} ${context.label.toLowerCase()}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback(value) {
                                    return value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        });

        const budgetCharts = document.querySelectorAll('.budget-chart');
        budgetCharts.forEach(canvas => {
            const spent = parseFloat(canvas.dataset.spent) || 0;
            const remaining = parseFloat(canvas.dataset.remaining) || 0;

            if (spent === 0 && remaining === 0) {
                const emptyMessage = document.createElement('div');
                emptyMessage.className = 'text-muted small fst-italic text-center mt-3';
                emptyMessage.textContent = 'Spend insights will populate after your campaign delivers.';
                canvas.style.display = 'none';
                canvas.parentNode.appendChild(emptyMessage);
                return;
            }

            const ctx = canvas.getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Spent', 'Remaining'],
                    datasets: [{
                        data: [spent, Math.max(remaining, 0)],
                        backgroundColor: ['rgba(239, 68, 68, 0.7)', 'rgba(234, 179, 8, 0.7)'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: '#9ca3af'
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label(context) {
                                    const value = context.parsed;
                                    return `${context.label}: $${value.toFixed(2)}`;
                                }
                            }
                        }
                    },
                    cutout: '65%'
                }
            });
        });
    });
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
