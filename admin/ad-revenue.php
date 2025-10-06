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
                             end_date = DATE_ADD(NOW(), INTERVAL duration_days DAY)
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
            $activate_query = "UPDATE user_advertisements SET status = 'active' WHERE id = :ad_id";
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
    SUM(CASE WHEN ad_type = 'banner' THEN 1 ELSE 0 END) as banner_ads,
    SUM(CASE WHEN ad_type = 'text' THEN 1 ELSE 0 END) as text_ads,
    SUM(CASE WHEN visibility_level = 'premium' THEN 1 ELSE 0 END) as premium_ads
    FROM user_advertisements";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

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
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Ads</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_ads']); ?></div>
                                    <small class="text-muted">
                                        <?php echo $stats['banner_ads']; ?> Banner | <?php echo $stats['text_ads']; ?> Text
                                    </small>
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
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Approval</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['pending_ads']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Ads</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['active_ads']); ?></div>
                                    <small class="text-muted">
                                        <?php echo $stats['premium_ads']; ?> Premium
                                    </small>
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
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Other Status</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($stats['paused_ads'] + $stats['expired_ads']); ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo $stats['paused_ads']; ?> Paused | <?php echo $stats['expired_ads']; ?> Expired
                                    </small>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-pause-circle fa-2x text-gray-300"></i>
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
                        <div class="col-md-4">
                            <label class="form-label">Status Filter</label>
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="paused" <?php echo $status_filter === 'paused' ? 'selected' : ''; ?>>Paused</option>
                                <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Type Filter</label>
                            <select name="type" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                                <option value="banner" <?php echo $type_filter === 'banner' ? 'selected' : ''; ?>>Banner Ads</option>
                                <option value="text" <?php echo $type_filter === 'text' ? 'selected' : ''; ?>>Text Ads</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
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
                                        <th>Ad Details</th>
                                        <th>Advertiser</th>
                                        <th>Type</th>
                                        <th>Duration</th>
                                        <th>Cost</th>
                                        <th>Performance</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ads as $ad): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($ad['title']); ?></strong>
                                            <?php if ($ad['ad_type'] === 'banner' && $ad['banner_image']): ?>
                                                <br><img src="../<?php echo htmlspecialchars($ad['banner_image']); ?>" 
                                                         alt="Banner" style="max-width: 150px; max-height: 50px;" class="mt-1">
                                            <?php elseif ($ad['ad_type'] === 'text'): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($ad['text_description']); ?></small>
                                            <?php endif; ?>
                                            <?php if ($ad['visibility_level'] === 'premium'): ?>
                                                <br><span class="badge bg-warning text-dark">Premium</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($ad['username']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($ad['email']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $ad['ad_type'] === 'banner' ? 'primary' : 'secondary'; ?>">
                                                <?php echo ucfirst($ad['ad_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $ad['duration_days']; ?> days
                                            <?php if ($ad['status'] === 'active' && $ad['days_remaining'] !== null): ?>
                                                <br><small class="text-muted"><?php echo max(0, $ad['days_remaining']); ?> left</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            $<?php echo number_format($ad['cost_paid'], 2); ?>
                                            <?php if ($ad['premium_cost'] > 0): ?>
                                                <br><small class="text-warning">+$<?php echo number_format($ad['premium_cost'], 2); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small>
                                                <i class="fas fa-eye"></i> <?php echo number_format($ad['impression_count']); ?>
                                                <br>
                                                <i class="fas fa-mouse-pointer"></i> <?php echo number_format($ad['click_count']); ?>
                                                <?php if ($ad['impression_count'] > 0): ?>
                                                    <br>CTR: <?php echo number_format(($ad['click_count'] / $ad['impression_count']) * 100, 2); ?>%
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'pending' => 'warning',
                                                'active' => 'success',
                                                'paused' => 'secondary',
                                                'expired' => 'danger',
                                                'rejected' => 'dark'
                                            ];
                                            $color = $status_colors[$ad['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo ucfirst($ad['status']); ?>
                                            </span>
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
