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
        case 'approve':
            $ad_id = intval($_POST['ad_id']);
            $approve_query = "UPDATE user_advertisements SET
                             status = 'active',
                             start_date = NOW(),
                             end_date = CASE
                                 WHEN campaign_type = 'standard' THEN DATE_ADD(NOW(), INTERVAL duration_days DAY)
                                 ELSE NULL
                             END
                             WHERE id = :ad_id";
            $approve_stmt = $db->prepare($approve_query);
            $approve_stmt->bindParam(':ad_id', $ad_id);

            if ($approve_stmt->execute()) {
                $success_message = 'Advertisement approved successfully!';
            } else {
                $error_message = 'Error approving advertisement.';
            }
            break;
            
        case 'reject':
            $ad_id = intval($_POST['ad_id']);
            $rejection_reason = trim($_POST['rejection_reason'] ?? 'Does not meet advertising guidelines');
            
            try {
                $db->beginTransaction();
                
                // Get ad details for refund
                $ad_query = "SELECT user_id, cost_paid, premium_cost FROM user_advertisements WHERE id = :ad_id";
                $ad_stmt = $db->prepare($ad_query);
                $ad_stmt->bindParam(':ad_id', $ad_id);
                $ad_stmt->execute();
                $ad = $ad_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($ad) {
                    // Refund credits to user
                    $refund_amount = $ad['cost_paid'] + $ad['premium_cost'];
                    $refund_query = "UPDATE users SET credits = credits + :refund WHERE id = :user_id";
                    $refund_stmt = $db->prepare($refund_query);
                    $refund_stmt->bindParam(':refund', $refund_amount);
                    $refund_stmt->bindParam(':user_id', $ad['user_id']);
                    $refund_stmt->execute();
                    
                    // Log refund transaction
                    $log_query = "INSERT INTO ad_transactions (ad_id, user_id, amount, transaction_type, description)
                                 VALUES (:ad_id, :user_id, :amount, 'refund', :description)";
                    $log_stmt = $db->prepare($log_query);
                    $log_stmt->bindParam(':ad_id', $ad_id);
                    $log_stmt->bindParam(':user_id', $ad['user_id']);
                    $log_stmt->bindParam(':amount', $refund_amount);
                    $log_stmt->bindParam(':description', $rejection_reason);
                    $log_stmt->execute();
                    
                    // Update ad status
                    $reject_query = "UPDATE user_advertisements SET status = 'rejected' WHERE id = :ad_id";
                    $reject_stmt = $db->prepare($reject_query);
                    $reject_stmt->bindParam(':ad_id', $ad_id);
                    $reject_stmt->execute();
                    
                    $db->commit();
                    $success_message = 'Advertisement rejected and credits refunded.';
                } else {
                    $db->rollback();
                    $error_message = 'Advertisement not found.';
                }
            } catch (Exception $e) {
                $db->rollback();
                $error_message = 'Error rejecting advertisement: ' . $e->getMessage();
            }
            break;
            
        case 'pause':
            $ad_id = intval($_POST['ad_id']);
            $pause_query = "UPDATE user_advertisements SET status = 'paused' WHERE id = :ad_id";
            $pause_stmt = $db->prepare($pause_query);
            $pause_stmt->bindParam(':ad_id', $ad_id);
            
            if ($pause_stmt->execute()) {
                $success_message = 'Advertisement paused successfully!';
            } else {
                $error_message = 'Error pausing advertisement.';
            }
            break;
            
        case 'activate':
            $ad_id = intval($_POST['ad_id']);
            $activate_query = "UPDATE user_advertisements SET
                              status = 'active',
                              start_date = COALESCE(start_date, NOW()),
                              end_date = CASE
                                  WHEN campaign_type = 'standard' THEN
                                      CASE
                                          WHEN end_date IS NULL OR end_date < NOW() THEN DATE_ADD(NOW(), INTERVAL duration_days DAY)
                                          ELSE end_date
                                      END
                                  ELSE NULL
                              END
                              WHERE id = :ad_id";
            $activate_stmt = $db->prepare($activate_query);
            $activate_stmt->bindParam(':ad_id', $ad_id);

            if ($activate_stmt->execute()) {
                $success_message = 'Advertisement activated successfully!';
            } else {
                $error_message = 'Error activating advertisement.';
            }
            break;
            
        case 'update_pricing':
            $pricing_id = intval($_POST['pricing_id']);
            $base_price = floatval($_POST['base_price']);
            $premium_multiplier = floatval($_POST['premium_multiplier']);
            
            $update_pricing = "UPDATE ad_pricing SET 
                              base_price = :base_price,
                              premium_multiplier = :premium_multiplier
                              WHERE id = :pricing_id";
            $pricing_stmt = $db->prepare($update_pricing);
            $pricing_stmt->bindParam(':base_price', $base_price);
            $pricing_stmt->bindParam(':premium_multiplier', $premium_multiplier);
            $pricing_stmt->bindParam(':pricing_id', $pricing_id);
            
            if ($pricing_stmt->execute()) {
                $success_message = 'Pricing updated successfully!';
            } else {
                $error_message = 'Error updating pricing.';
            }
            break;
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$type_filter = $_GET['type'] ?? 'all';
$campaign_filter = $_GET['campaign'] ?? 'all';
$placement_filter = $_GET['placement'] ?? 'all';

// Build query with filters
$where_conditions = [];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "ua.status = :status";
    $params[':status'] = $status_filter;
}

if ($type_filter !== 'all') {
    $where_conditions[] = "ua.ad_type = :ad_type";
    $params[':ad_type'] = $type_filter;
}

if ($campaign_filter !== 'all') {
    $where_conditions[] = "ua.campaign_type = :campaign_type";
    $params[':campaign_type'] = $campaign_filter;
}

if ($placement_filter === 'general') {
    $where_conditions[] = "ua.placement_type = 'general'";
} elseif ($placement_filter === 'targeted') {
    $where_conditions[] = "ua.placement_type = 'targeted'";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get all advertisements with user info
$ads_query = "SELECT ua.*, u.username, u.email,
              DATEDIFF(ua.end_date, NOW()) as days_remaining,
              (SELECT COUNT(*) FROM ad_impressions WHERE ad_id = ua.id) as total_impressions,
              (SELECT COUNT(*) FROM ad_clicks WHERE ad_id = ua.id) as total_clicks
              FROM user_advertisements ua
              LEFT JOIN users u ON ua.user_id = u.id
              {$where_clause}
              ORDER BY 
                CASE ua.status
                  WHEN 'pending' THEN 1
                  WHEN 'active' THEN 2
                  WHEN 'paused' THEN 3
                  WHEN 'expired' THEN 4
                  WHEN 'rejected' THEN 5
                END,
                ua.created_at DESC";
$ads_stmt = $db->prepare($ads_query);
foreach ($params as $key => $value) {
    $ads_stmt->bindValue($key, $value);
}
$ads_stmt->execute();
$ads = $ads_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT
    COUNT(*) as total_ads,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_ads,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_ads,
    SUM(CASE WHEN status = 'paused' THEN 1 ELSE 0 END) as paused_ads,
    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_ads,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_ads,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_ads,
    SUM(CASE WHEN ad_type = 'banner' THEN 1 ELSE 0 END) as banner_ads,
    SUM(CASE WHEN ad_type = 'text' THEN 1 ELSE 0 END) as text_ads,
    SUM(CASE WHEN visibility_level = 'premium' THEN 1 ELSE 0 END) as premium_ads,
    SUM(CASE WHEN campaign_type IN ('cpc','cpm') THEN 1 ELSE 0 END) as performance_ads,
    SUM(CASE WHEN placement_type = 'general' THEN 1 ELSE 0 END) as general_ads,
    COALESCE(SUM(CASE WHEN campaign_type IN ('cpc','cpm') THEN budget_total ELSE cost_paid + premium_cost END), 0) as total_budget,
    COALESCE(SUM(CASE WHEN campaign_type IN ('cpc','cpm') THEN total_spent ELSE cost_paid + premium_cost END), 0) as total_realised,
    COALESCE(SUM(CASE WHEN campaign_type IN ('cpc','cpm') THEN budget_remaining ELSE 0 END), 0) as total_remaining,
    COALESCE(SUM(impression_count), 0) as total_impressions,
    COALESCE(SUM(click_count), 0) as total_clicks
    FROM user_advertisements";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$total_budget = isset($stats['total_budget']) ? (float) $stats['total_budget'] : 0.0;
$total_realised = isset($stats['total_realised']) ? (float) $stats['total_realised'] : 0.0;
$total_remaining = isset($stats['total_remaining']) ? (float) $stats['total_remaining'] : 0.0;
$total_impressions_stats = isset($stats['total_impressions']) ? (int) $stats['total_impressions'] : 0;
$total_clicks_stats = isset($stats['total_clicks']) ? (int) $stats['total_clicks'] : 0;
$overall_ctr = $total_impressions_stats > 0 ? ($total_clicks_stats / $total_impressions_stats) * 100 : 0;

function formatAdDimensions($width, $height): string
{
    $width = (int) $width;
    $height = (int) $height;

    if ($width === 0 || $height === 0) {
        return 'Responsive Flex';
    }

    return $width . 'x' . $height . 'px';
}

// Get pricing
$pricing_query = "SELECT * FROM ad_pricing ORDER BY ad_type, duration_days";
$pricing_stmt = $db->prepare($pricing_query);
$pricing_stmt->execute();
$pricing = $pricing_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Advertisement Revenue Management - Admin Panel';
include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Advertisement Revenue Management</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="ad-analytics.php" class="btn btn-sm btn-outline-primary me-2">
                        <i class="fas fa-chart-line"></i> View Analytics
                    </a>
                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#pricingModal">
                        <i class="fas fa-dollar-sign"></i> Manage Pricing
                    </button>
                </div>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

             Statistics Cards 
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Campaigns</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_ads']); ?></div>
                                    <small class="text-muted"><?php echo number_format($stats['banner_ads']); ?> Banner · <?php echo number_format($stats['text_ads']); ?> Text</small>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-ad fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Approval Queue</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['pending_ads']); ?></div>
                                    <small class="text-muted">Rejected: <?php echo number_format($stats['rejected_ads']); ?> · Completed: <?php echo number_format($stats['completed_ads']); ?></small>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-inbox fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Campaigns</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['active_ads']); ?></div>
                                    <small class="text-muted">Performance: <?php echo number_format($stats['performance_ads']); ?> · Premium: <?php echo number_format($stats['premium_ads']); ?> · General: <?php echo number_format($stats['general_ads']); ?></small>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Spend Overview</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($total_realised, 2); ?></div>
                                    <small class="text-muted">Budget: $<?php echo number_format($total_budget, 2); ?> · Remaining: $<?php echo number_format($total_remaining, 2); ?> · CTR: <?php echo number_format($overall_ctr, 2); ?>%</small>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

             Filters 
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="paused" <?php echo $status_filter === 'paused' ? 'selected' : ''; ?>>Paused</option>
                                <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Ad Format</label>
                            <select name="type" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                                <option value="banner" <?php echo $type_filter === 'banner' ? 'selected' : ''; ?>>Banner Ads</option>
                                <option value="text" <?php echo $type_filter === 'text' ? 'selected' : ''; ?>>Text Ads</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Campaign Type</label>
                            <select name="campaign" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $campaign_filter === 'all' ? 'selected' : ''; ?>>All Campaigns</option>
                                <option value="standard" <?php echo $campaign_filter === 'standard' ? 'selected' : ''; ?>>Standard</option>
                                <option value="cpc" <?php echo $campaign_filter === 'cpc' ? 'selected' : ''; ?>>CPC</option>
                                <option value="cpm" <?php echo $campaign_filter === 'cpm' ? 'selected' : ''; ?>>CPM</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Placement</label>
                            <select name="placement" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $placement_filter === 'all' ? 'selected' : ''; ?>>All Placements</option>
                                <option value="targeted" <?php echo $placement_filter === 'targeted' ? 'selected' : ''; ?>>Targeted</option>
                                <option value="general" <?php echo $placement_filter === 'general' ? 'selected' : ''; ?>>General Rotation</option>
                            </select>
                        </div>
                        <div class="col-12 text-end">
                            <a href="ad-revenue.php" class="btn btn-secondary">Clear Filters</a>
                        </div>
                    </form>
                </div>
            </div>

             Advertisements List 
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">All Advertisements</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($ads)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Campaign</th>
                                        <th>Advertiser</th>
                                        <th>Format &amp; Placement</th>
                                        <th>Billing</th>
                                        <th>Performance</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ads as $ad): ?>
                                        <?php
                                        $is_performance = in_array($ad['campaign_type'], ['cpc', 'cpm'], true);
                                        $standard_cost = (float) $ad['cost_paid'] + (float) $ad['premium_cost'];
                                        $budget_value = $is_performance ? (float) $ad['budget_total'] : $standard_cost;
                                        $spent_value = $is_performance ? (float) $ad['total_spent'] : $standard_cost;
                                        $remaining_value = $is_performance ? max(0, (float) $ad['budget_remaining']) : 0.0;
                                        $spend_percent = $budget_value > 0 ? min(100, ($spent_value / $budget_value) * 100) : 0;
                                        $ctr_value = $ad['impression_count'] > 0 ? ($ad['click_count'] / $ad['impression_count']) * 100 : 0;
                                        $rate_label = '';
                                        if ($ad['campaign_type'] === 'cpc') {
                                            $rate_label = 'CPC $' . number_format((float) $ad['cpc_rate'], 2);
                                        } elseif ($ad['campaign_type'] === 'cpm') {
                                            $rate_label = 'CPM $' . number_format((float) $ad['cpm_rate'], 2);
                                        }
                                        $days_remaining = isset($ad['days_remaining']) ? (int) $ad['days_remaining'] : null;
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($ad['title']); ?></strong>
                                                <?php if ($ad['ad_type'] === 'banner' && $ad['banner_image']): ?>
                                                    <br><img src="../<?php echo htmlspecialchars($ad['banner_image']); ?>" alt="Banner" style="max-width: 150px; max-height: 50px;" class="mt-1">
                                                <?php elseif ($ad['ad_type'] === 'text'): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($ad['text_description']); ?></small>
                                                <?php endif; ?>
                                                <?php if ($ad['visibility_level'] === 'premium'): ?>
                                                    <br><span class="badge bg-warning text-dark">Premium</span>
                                                <?php endif; ?>
                                                <br><small class="text-muted">Created: <?php echo date('Y-m-d', strtotime($ad['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($ad['username']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($ad['email']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $ad['ad_type'] === 'banner' ? 'primary' : 'secondary'; ?>"><?php echo ucfirst($ad['ad_type']); ?></span>
                                                <?php if ($ad['placement_type'] === 'general'): ?>
                                                    <span class="badge bg-info-subtle text-info ms-1">General</span>
                                                    <div class="text-muted small">Dimensions: <?php echo formatAdDimensions($ad['target_width'], $ad['target_height']); ?></div>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary-subtle text-secondary ms-1">Targeted</span>
                                                    <div class="text-muted small">Slot: <?php echo htmlspecialchars($ad['target_space_id'] ?? 'N/A'); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-dark text-white"><?php echo strtoupper($ad['campaign_type']); ?></span>
                                                <div class="text-muted small">Budget: $<?php echo number_format($budget_value, 2); ?></div>
                                                <?php if ($is_performance): ?>
                                                    <div class="text-muted small">Spent: $<?php echo number_format($spent_value, 2); ?> · Remaining: $<?php echo number_format($remaining_value, 2); ?></div>
                                                    <div class="progress my-1" style="height: 6px;">
                                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $spend_percent; ?>%;" aria-valuenow="<?php echo $spend_percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                    <?php if ($rate_label): ?>
                                                        <div class="text-muted small"><?php echo $rate_label; ?></div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <div class="text-muted small">Paid: $<?php echo number_format($standard_cost, 2); ?></div>
                                                    <div class="text-muted small">Duration: <?php echo (int) $ad['duration_days']; ?> days</div>
                                                    <?php if ($ad['status'] === 'active' && $days_remaining !== null): ?>
                                                        <div class="text-muted small">Days remaining: <?php echo max(0, $days_remaining); ?></div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small>
                                                    <i class="fas fa-eye"></i> <?php echo number_format($ad['impression_count']); ?><br>
                                                    <i class="fas fa-mouse-pointer"></i> <?php echo number_format($ad['click_count']); ?><br>
                                                    CTR: <?php echo number_format($ctr_value, 2); ?>%
                                                </small>
                                            </td>
                                            <td>
                                                <?php
                                                $status_colors = [
                                                    'pending' => 'warning',
                                                    'active' => 'success',
                                                    'paused' => 'secondary',
                                                    'expired' => 'danger',
                                                    'rejected' => 'dark',
                                                    'completed' => 'primary'
                                                ];
                                                $color = $status_colors[$ad['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($ad['status']); ?></span>
                                            </td>
                                            <td>
                                            <div class="btn-group btn-group-sm">
                                                <?php if ($ad['status'] === 'pending'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="approve">
                                                        <input type="hidden" name="ad_id" value="<?php echo $ad['id']; ?>">
                                                        <button type="submit" class="btn btn-success btn-sm" title="Approve">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                    <button class="btn btn-danger btn-sm" 
                                                            onclick="showRejectModal(<?php echo $ad['id']; ?>)" title="Reject">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php elseif ($ad['status'] === 'active'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="pause">
                                                        <input type="hidden" name="ad_id" value="<?php echo $ad['id']; ?>">
                                                        <button type="submit" class="btn btn-warning btn-sm" title="Pause">
                                                            <i class="fas fa-pause"></i>
                                                        </button>
                                                    </form>
                                                <?php elseif ($ad['status'] === 'paused'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="activate">
                                                        <input type="hidden" name="ad_id" value="<?php echo $ad['id']; ?>">
                                                        <button type="submit" class="btn btn-success btn-sm" title="Activate">
                                                            <i class="fas fa-play"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <a href="ad-details.php?id=<?php echo $ad['id']; ?>" 
                                                   class="btn btn-info btn-sm" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-ad fa-3x text-muted mb-3"></i>
                            <h5>No advertisements found</h5>
                            <p class="text-muted">No advertisements match your current filters.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

 Pricing Management Modal 
<div class="modal fade" id="pricingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Manage Advertisement Pricing</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Ad Type</th>
                                <th>Duration</th>
                                <th>Base Price</th>
                                <th>Premium Multiplier</th>
                                <th>Premium Price</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pricing as $price): ?>
                            <tr>
                                <form method="POST" class="pricing-form">
                                    <input type="hidden" name="action" value="update_pricing">
                                    <input type="hidden" name="pricing_id" value="<?php echo $price['id']; ?>">
                                    <td>
                                        <span class="badge bg-<?php echo $price['ad_type'] === 'banner' ? 'primary' : 'secondary'; ?>">
                                            <?php echo ucfirst($price['ad_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $price['duration_days']; ?> days</td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">$</span>
                                            <input type="number" name="base_price" step="0.01" 
                                                   value="<?php echo $price['base_price']; ?>" 
                                                   class="form-control" style="width: 80px;">
                                        </div>
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="number" name="premium_multiplier" step="0.01" 
                                                   value="<?php echo $price['premium_multiplier']; ?>" 
                                                   class="form-control" style="width: 60px;">
                                            <span class="input-group-text">x</span>
                                        </div>
                                    </td>
                                    <td>
                                        <strong>$<?php echo number_format($price['base_price'] * $price['premium_multiplier'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <button type="submit" class="btn btn-sm btn-primary">Update</button>
                                    </td>
                                </form>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Note:</strong> Premium multiplier determines how much more premium visibility costs. 
                    For example, 2.00x means premium ads cost twice the base price.
                </div>
            </div>
        </div>
    </div>
</div>

 Reject Ad Modal 
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Advertisement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="rejectForm">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="ad_id" id="rejectAdId">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        The advertiser will be refunded their full payment.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rejection Reason</label>
                        <textarea name="rejection_reason" class="form-control" rows="3" required
                                  placeholder="Explain why this ad is being rejected..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject & Refund</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showRejectModal(adId) {
    document.getElementById('rejectAdId').value = adId;
    const rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));
    rejectModal.show();
}
</script>

<style>
.border-left-primary {
    border-left: 4px solid #4e73df;
}
.border-left-success {
    border-left: 4px solid #1cc88a;
}
.border-left-info {
    border-left: 4px solid #36b9cc;
}
.border-left-warning {
    border-left: 4px solid #f6c23e;
}
</style>

<?php include 'includes/admin_footer.php'; ?>
