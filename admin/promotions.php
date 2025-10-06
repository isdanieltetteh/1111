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
        case 'update_pricing':
            $pricing_updates = $_POST['pricing'] ?? [];
            
            foreach ($pricing_updates as $id => $data) {
                $update_query = "UPDATE promotion_pricing SET price = :price WHERE id = :id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':price', $data['price']);
                $update_stmt->bindParam(':id', $id);
                $update_stmt->execute();
            }
            
            $success_message = 'Pricing updated successfully!';
            break;
            
        case 'update_features':
            $feature_updates = $_POST['features'] ?? [];
            
            foreach ($feature_updates as $id => $data) {
                $update_query = "UPDATE feature_pricing SET price = :price WHERE id = :id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':price', $data['price']);
                $update_stmt->bindParam(':id', $id);
                $update_stmt->execute();
            }
            
            $success_message = 'Feature pricing updated successfully!';
            break;
            
        case 'extend_promotion':
            $promotion_id = intval($_POST['promotion_id']);
            $additional_days = intval($_POST['additional_days']);
            
            $extend_query = "UPDATE site_promotions SET 
                            expires_at = DATE_ADD(expires_at, INTERVAL :days DAY),
                            duration_days = duration_days + :days
                            WHERE id = :promotion_id";
            $extend_stmt = $db->prepare($extend_query);
            $extend_stmt->bindParam(':days', $additional_days);
            $extend_stmt->bindParam(':promotion_id', $promotion_id);
            $extend_stmt->execute();
            
            $success_message = 'Promotion extended successfully!';
            break;
    }
}

// Get promotion statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM site_promotions WHERE is_active = 1 AND expires_at > NOW()) as active_promotions,
    (SELECT COUNT(*) FROM site_promotions WHERE promotion_type = 'sponsored' AND is_active = 1 AND expires_at > NOW()) as active_sponsored,
    (SELECT COUNT(*) FROM site_promotions WHERE promotion_type = 'boosted' AND is_active = 1 AND expires_at > NOW()) as active_boosted,
    (SELECT COALESCE(SUM(amount_paid), 0) FROM site_promotions WHERE payment_status = 'completed') as total_revenue,
    (SELECT COALESCE(SUM(amount_paid), 0) FROM site_promotions WHERE payment_status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as monthly_revenue";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get active promotions
$promotions_query = "SELECT sp.*, s.name as site_name, u.username 
                     FROM site_promotions sp
                     JOIN sites s ON sp.site_id = s.id
                     JOIN users u ON sp.user_id = u.id
                     WHERE sp.is_active = 1 AND sp.expires_at > NOW()
                     ORDER BY sp.promotion_type, sp.expires_at ASC";
$promotions_stmt = $db->prepare($promotions_query);
$promotions_stmt->execute();
$active_promotions = $promotions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pricing data
$promotion_pricing_query = "SELECT * FROM promotion_pricing ORDER BY promotion_type, duration_days";
$promotion_pricing_stmt = $db->prepare($promotion_pricing_query);
$promotion_pricing_stmt->execute();
$promotion_pricing = $promotion_pricing_stmt->fetchAll(PDO::FETCH_ASSOC);

$feature_pricing_query = "SELECT * FROM feature_pricing ORDER BY feature_type";
$feature_pricing_stmt = $db->prepare($feature_pricing_query);
$feature_pricing_stmt->execute();
$feature_pricing = $feature_pricing_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Promotions Management - Admin Panel';
include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Promotions Management</h1>
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
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Active Promotions</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['active_promotions']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-rocket fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Sponsored Sites</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['active_sponsored']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-star fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Boosted Sites</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['active_boosted']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-fire fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Revenue</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($stats['total_revenue'], 2); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pricing Management -->
            <div class="row mb-4">
                <div class="col-lg-6">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Promotion Pricing</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_pricing">
                                <?php foreach ($promotion_pricing as $pricing): ?>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong><?php echo ucfirst($pricing['promotion_type']); ?> - <?php echo $pricing['duration_days']; ?> Days</strong>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" name="pricing[<?php echo $pricing['id']; ?>][price]" 
                                                   class="form-control" value="<?php echo $pricing['price']; ?>" 
                                                   step="0.01" min="0" required>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <button type="submit" class="btn btn-primary">Update Pricing</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Feature Pricing</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_features">
                                <?php foreach ($feature_pricing as $feature): ?>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong><?php echo ucfirst(str_replace('_', ' ', $feature['feature_type'])); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($feature['description']); ?></small>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" name="features[<?php echo $feature['id']; ?>][price]" 
                                                   class="form-control" value="<?php echo $feature['price']; ?>" 
                                                   step="0.01" min="0" required>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <button type="submit" class="btn btn-primary">Update Feature Pricing</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Promotions -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Active Promotions</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($active_promotions)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Site</th>
                                        <th>Type</th>
                                        <th>User</th>
                                        <th>Amount Paid</th>
                                        <th>Duration</th>
                                        <th>Expires</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($active_promotions as $promotion): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($promotion['site_name']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $promotion['promotion_type'] === 'sponsored' ? 'warning' : 'info'; ?>">
                                                <?php echo $promotion['promotion_type'] === 'sponsored' ? '⭐ Sponsored' : '🔥 Boosted'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($promotion['username']); ?></td>
                                        <td>$<?php echo number_format($promotion['amount_paid'], 2); ?></td>
                                        <td><?php echo $promotion['duration_days']; ?> days</td>
                                        <td>
                                            <?php 
                                            $time_left = strtotime($promotion['expires_at']) - time();
                                            if ($time_left > 0) {
                                                $days = floor($time_left / 86400);
                                                $hours = floor(($time_left % 86400) / 3600);
                                                echo $days . 'd ' . $hours . 'h';
                                            } else {
                                                echo 'Expired';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" 
                                                    onclick="extendPromotion(<?php echo $promotion['id']; ?>, '<?php echo htmlspecialchars($promotion['site_name']); ?>')">
                                                Extend
                                            </button>
                                            <a href="../review.php?id=<?php echo $promotion['site_id']; ?>" 
                                               class="btn btn-sm btn-info" target="_blank">View</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-rocket fa-3x text-muted mb-3"></i>
                            <h5>No active promotions</h5>
                            <p class="text-muted">No sites are currently being promoted.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Extend Promotion Modal -->
<div class="modal fade" id="extendModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Extend Promotion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="extendForm">
                <input type="hidden" name="action" value="extend_promotion">
                <input type="hidden" name="promotion_id" id="extendPromotionId">
                <div class="modal-body">
                    <p>Extending promotion for: <strong id="extendSiteName"></strong></p>
                    
                    <div class="mb-3">
                        <label class="form-label">Additional Days</label>
                        <input type="number" name="additional_days" class="form-control" 
                               min="1" max="365" value="7" required>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>Note:</strong> This will extend the current promotion period without additional payment.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Extend Promotion</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function extendPromotion(promotionId, siteName) {
    document.getElementById('extendPromotionId').value = promotionId;
    document.getElementById('extendSiteName').textContent = siteName;
    
    const modal = new bootstrap.Modal(document.getElementById('extendModal'));
    modal.show();
}
</script>

<?php include 'includes/admin_footer.php'; ?>
