<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/coupon-manager.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();
$coupon_manager = new CouponManager($db);

// Redirect if not admin
if (!$auth->isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$success_message = '';
$error_message = '';

// Handle coupon actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_coupon':
            $coupon_data = [
                'code' => trim($_POST['code']),
                'title' => trim($_POST['title']),
                'description' => trim($_POST['description']),
                'coupon_type' => $_POST['coupon_type'],
                'value' => floatval($_POST['value']),
                'minimum_deposit' => floatval($_POST['minimum_deposit'] ?? 0),
                'usage_limit' => !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : null,
                'user_limit_per_account' => intval($_POST['user_limit_per_account'] ?? 1),
                'expires_at' => !empty($_POST['expires_at']) ? $_POST['expires_at'] : null
            ];
            
            $result = $coupon_manager->createCoupon($coupon_data, $_SESSION['user_id']);
            
            if ($result['success']) {
                $success_message = $result['message'] . ' Code: ' . $result['code'];
            } else {
                $error_message = $result['message'];
            }
            break;
            
        case 'toggle_coupon':
            $coupon_id = intval($_POST['coupon_id']);
            $toggle_query = "UPDATE coupons SET is_active = NOT is_active WHERE id = :coupon_id";
            $toggle_stmt = $db->prepare($toggle_query);
            $toggle_stmt->bindParam(':coupon_id', $coupon_id);
            
            if ($toggle_stmt->execute()) {
                $success_message = 'Coupon status updated!';
            } else {
                $error_message = 'Error updating coupon status';
            }
            break;
            
        case 'delete_coupon':
            $coupon_id = intval($_POST['coupon_id']);
            
            // Check if coupon has been used
            $usage_check = "SELECT COUNT(*) as count FROM coupon_redemptions WHERE coupon_id = :coupon_id";
            $usage_stmt = $db->prepare($usage_check);
            $usage_stmt->bindParam(':coupon_id', $coupon_id);
            $usage_stmt->execute();
            $usage = $usage_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($usage['count'] > 0) {
                $error_message = 'Cannot delete coupon that has been redeemed';
            } else {
                $delete_query = "DELETE FROM coupons WHERE id = :coupon_id";
                $delete_stmt = $db->prepare($delete_query);
                $delete_stmt->bindParam(':coupon_id', $coupon_id);
                
                if ($delete_stmt->execute()) {
                    $success_message = 'Coupon deleted successfully!';
                } else {
                    $error_message = 'Error deleting coupon';
                }
            }
            break;
    }
}

// Get all coupons
$coupons = $coupon_manager->getActiveCoupons();

// Get coupon statistics
$stats_query = "SELECT 
    COUNT(*) as total_coupons,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_coupons,
    SUM(usage_count) as total_redemptions,
    (SELECT COALESCE(SUM(redemption_value), 0) FROM coupon_redemptions) as total_value_redeemed,
    (SELECT COUNT(*) FROM coupon_redemptions WHERE redeemed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as monthly_redemptions
    FROM coupons";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$page_title = 'Coupon Management - Admin Panel';
include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Coupon Management</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCouponModal">
                    <i class="fas fa-plus"></i> Create Coupon
                </button>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- Coupon Statistics -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Coupons</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_coupons']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-ticket-simple fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Coupons</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['active_coupons']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Redemptions</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_redemptions']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-gift fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Value Redeemed</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($stats['total_value_redeemed'], 2); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Coupons List -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">All Coupons</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($coupons)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Title</th>
                                        <th>Type</th>
                                        <th>Value</th>
                                        <th>Usage</th>
                                        <th>Expires</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($coupons as $coupon): ?>
                                    <tr>
                                        <td>
                                            <strong style="font-family: monospace; background: rgba(59, 130, 246, 0.1); padding: 0.25rem 0.5rem; border-radius: 0.25rem;">
                                                <?php echo htmlspecialchars($coupon['code']); ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($coupon['title']); ?></strong>
                                            <?php if ($coupon['description']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($coupon['description']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $coupon['coupon_type'] === 'deposit_bonus' ? 'success' : ($coupon['coupon_type'] === 'percentage_bonus' ? 'primary' : ($coupon['coupon_type'] === 'points_bonus' ? 'warning' : 'info')); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $coupon['coupon_type'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($coupon['coupon_type'] === 'percentage_bonus'): ?>
                                                <?php echo $coupon['value']; ?>%
                                            <?php else: ?>
                                                $<?php echo number_format($coupon['value'], 4); ?>
                                            <?php endif; ?>
                                            
                                            <?php if ($coupon['minimum_deposit'] > 0): ?>
                                                <br><small class="text-muted">Min: $<?php echo number_format($coupon['minimum_deposit'], 2); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $coupon['usage_count']; ?>
                                            <?php if ($coupon['usage_limit']): ?>
                                                / <?php echo $coupon['usage_limit']; ?>
                                            <?php else: ?>
                                                / ∞
                                            <?php endif; ?>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo $coupon['user_limit_per_account']; ?> per user
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($coupon['expires_at']): ?>
                                                <?php 
                                                $expires = strtotime($coupon['expires_at']);
                                                $now = time();
                                                if ($expires < $now): ?>
                                                    <span class="badge bg-danger">Expired</span>
                                                    <br><small><?php echo date('M j, Y', $expires); ?></small>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Active</span>
                                                    <br><small><?php echo date('M j, Y', $expires); ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-info">No Expiry</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $coupon['is_active'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $coupon['is_active'] ? 'Active' : 'Disabled'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group-vertical btn-group-sm">
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="toggle_coupon">
                                                    <input type="hidden" name="coupon_id" value="<?php echo $coupon['id']; ?>">
                                                    <button type="submit" class="btn btn-outline-warning btn-sm">
                                                        <?php echo $coupon['is_active'] ? 'Disable' : 'Enable'; ?>
                                                    </button>
                                                </form>
                                                
                                                <button class="btn btn-outline-info btn-sm" 
                                                        onclick="viewCouponDetails(<?php echo htmlspecialchars(json_encode($coupon)); ?>)">
                                                    Details
                                                </button>
                                                
                                                <?php if ($coupon['total_redemptions'] == 0): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="delete_coupon">
                                                        <input type="hidden" name="coupon_id" value="<?php echo $coupon['id']; ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm" 
                                                                onclick="return confirm('Delete this coupon?')">
                                                            Delete
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-ticket-simple fa-3x text-muted mb-3"></i>
                            <h5>No coupons created yet</h5>
                            <p class="text-muted">Create your first coupon to reward users.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCouponModal">
                                Create First Coupon
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Create Coupon Modal -->
<div class="modal fade" id="createCouponModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Coupon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_coupon">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Coupon Code</label>
                                <input type="text" name="code" class="form-control" 
                                       placeholder="Leave empty to auto-generate" 
                                       pattern="[A-Z0-9]{4,20}" 
                                       style="text-transform: uppercase;">
                                <small class="form-text text-muted">4-20 characters, letters and numbers only</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Coupon Title</label>
                                <input type="text" name="title" class="form-control" 
                                       placeholder="Welcome Bonus" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2" 
                                  placeholder="Bonus for new users..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Coupon Type</label>
                                <select name="coupon_type" class="form-select" required>
                                    <option value="deposit_bonus">Deposit Bonus (Fixed Amount)</option>
                                    <option value="percentage_bonus">Percentage Bonus</option>
                                    <option value="points_bonus">Points Bonus</option>
                                    <option value="credits_bonus">Credits Bonus</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Value</label>
                                <input type="number" name="value" class="form-control" 
                                       step="0.0001" min="0.0001" max="1000" required>
                                <small class="form-text text-muted">Amount in USD or percentage</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Minimum Deposit (USD)</label>
                                <input type="number" name="minimum_deposit" class="form-control" 
                                       step="0.01" min="0" value="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Usage Limit</label>
                                <input type="number" name="usage_limit" class="form-control" 
                                       min="1" max="10000" placeholder="Unlimited">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Uses Per Account</label>
                                <input type="number" name="user_limit_per_account" class="form-control" 
                                       min="1" max="10" value="1" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Expiry Date</label>
                                <input type="datetime-local" name="expires_at" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-shield-halved"></i> Security Features</h6>
                        <ul class="mb-0">
                            <li>All redemptions are logged with IP tracking</li>
                            <li>Fraud detection prevents abuse</li>
                            <li>Security hashes prevent tampering</li>
                            <li>Usage limits prevent overuse</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Coupon</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Coupon Details Modal -->
<div class="modal fade" id="couponDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Coupon Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="couponDetailsContent">
                <!-- Content loaded by JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewCouponDetails(coupon) {
    const content = document.getElementById('couponDetailsContent');
    
    const typeColors = {
        'deposit_bonus': 'success',
        'percentage_bonus': 'primary', 
        'points_bonus': 'warning',
        'credits_bonus': 'info'
    };
    
    const typeColor = typeColors[coupon.coupon_type] || 'secondary';
    
    content.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <h6>Basic Information</h6>
                <table class="table table-sm">
                    <tr><td><strong>Code:</strong></td><td><code>${coupon.code}</code></td></tr>
                    <tr><td><strong>Title:</strong></td><td>${coupon.title}</td></tr>
                    <tr><td><strong>Type:</strong></td><td><span class="badge bg-${typeColor}">${coupon.coupon_type.replace('_', ' ')}</span></td></tr>
                    <tr><td><strong>Value:</strong></td><td>${coupon.coupon_type === 'percentage_bonus' ? coupon.value + '%' : '$' + parseFloat(coupon.value).toFixed(4)}</td></tr>
                    <tr><td><strong>Created By:</strong></td><td>${coupon.created_by_username}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Usage & Limits</h6>
                <table class="table table-sm">
                    <tr><td><strong>Usage Count:</strong></td><td>${coupon.usage_count}</td></tr>
                    <tr><td><strong>Usage Limit:</strong></td><td>${coupon.usage_limit || 'Unlimited'}</td></tr>
                    <tr><td><strong>Per Account:</strong></td><td>${coupon.user_limit_per_account}</td></tr>
                    <tr><td><strong>Min Deposit:</strong></td><td>$${parseFloat(coupon.minimum_deposit).toFixed(2)}</td></tr>
                    <tr><td><strong>Expires:</strong></td><td>${coupon.expires_at ? new Date(coupon.expires_at).toLocaleDateString() : 'Never'}</td></tr>
                </table>
            </div>
        </div>
        
        ${coupon.description ? `<div class="mt-3"><h6>Description</h6><p class="text-muted">${coupon.description}</p></div>` : ''}
        
        <div class="mt-3">
            <h6>Redemption Statistics</h6>
            <div class="row">
                <div class="col-md-4 text-center">
                    <div class="border rounded p-3">
                        <h4 class="text-primary">${coupon.total_redemptions}</h4>
                        <small class="text-muted">Total Redemptions</small>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <div class="border rounded p-3">
                        <h4 class="text-success">$${(coupon.total_redemptions * coupon.value).toFixed(2)}</h4>
                        <small class="text-muted">Total Value</small>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <div class="border rounded p-3">
                        <h4 class="text-info">${coupon.usage_limit ? Math.round((coupon.usage_count / coupon.usage_limit) * 100) : 0}%</h4>
                        <small class="text-muted">Usage Rate</small>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    const modal = new bootstrap.Modal(document.getElementById('couponDetailsModal'));
    modal.show();
}
</script>

<?php include 'includes/admin_footer.php'; ?>
