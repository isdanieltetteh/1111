<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/wallet.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();
$wallet_manager = new WalletManager($db);

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
        case 'approve_withdrawal':
            $request_id = intval($_POST['request_id']);
            
            // Get withdrawal details
            $withdrawal_query = "SELECT * FROM withdrawal_requests WHERE id = :request_id AND status = 'pending'";
            $withdrawal_stmt = $db->prepare($withdrawal_query);
            $withdrawal_stmt->bindParam(':request_id', $request_id, PDO::PARAM_INT);
            if ($withdrawal_stmt->execute()) {
                $withdrawal = $withdrawal_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$withdrawal) {
                    $error_message = 'Withdrawal request not found or already processed';
                    break;
                }
                
                $update_query = "UPDATE withdrawal_requests SET status = 'completed', processed_at = NOW(), processed_by = :admin_id WHERE id = :request_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':request_id', $request_id, PDO::PARAM_INT);
                $update_stmt->bindParam(':admin_id', $_SESSION['user_id'], PDO::PARAM_INT);
                if ($update_stmt->execute()) {
                    if ($update_stmt->rowCount() > 0) {
                        // Log the approval
                        $log_query = "INSERT INTO points_transactions (user_id, points, type, description, reference_id, reference_type) 
                                     VALUES (:user_id, 0, 'withdrawal_approved', 'Withdrawal approved by admin', :request_id, 'withdrawal')";
                        $log_stmt = $db->prepare($log_query);
                        $log_stmt->bindParam(':user_id', $withdrawal['user_id'], PDO::PARAM_INT);
                        $log_stmt->bindParam(':request_id', $request_id, PDO::PARAM_INT);
                        $log_stmt->execute();
                        
                        $success_message = 'Withdrawal approved successfully!';
                    } else {
                        $error_message = 'No changes made to withdrawal request.';
                    }
                } else {
                    $error_message = 'Error approving withdrawal: ' . $update_stmt->errorInfo()[2];
                }
            } else {
                $error_message = 'Error fetching withdrawal: ' . $withdrawal_stmt->errorInfo()[2];
            }
            break;
            
        case 'reject_withdrawal':
            $request_id = intval($_POST['request_id']);
            $reason = trim($_POST['reason']);
            
            // Get withdrawal details to refund points
            $withdrawal_query = "SELECT * FROM withdrawal_requests WHERE id = :request_id";
            $withdrawal_stmt = $db->prepare($withdrawal_query);
            $withdrawal_stmt->bindParam(':request_id', $request_id, PDO::PARAM_INT);
            if ($withdrawal_stmt->execute()) {
                $withdrawal = $withdrawal_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($withdrawal) {
                    $db->beginTransaction();
                    try {
                        // Refund points to user
                        $refund_query = "UPDATE user_wallets SET 
                                        points_balance = points_balance + :points,
                                        total_redeemed_points = total_redeemed_points - :points
                                        WHERE user_id = :user_id";
                        $refund_stmt = $db->prepare($refund_query);
                        $refund_stmt->bindParam(':points', $withdrawal['points_redeemed'], PDO::PARAM_INT);
                        $refund_stmt->bindParam(':user_id', $withdrawal['user_id'], PDO::PARAM_INT);
                        $refund_stmt->execute();
                        
                        // Log the refund
                        $log_refund = "INSERT INTO points_transactions (user_id, points, type, description, reference_id, reference_type) 
                                      VALUES (:user_id, :points, 'refund', 'Withdrawal rejected - points refunded', :request_id, 'withdrawal')";
                        $log_stmt = $db->prepare($log_refund);
                        $log_stmt->bindParam(':user_id', $withdrawal['user_id'], PDO::PARAM_INT);
                        $log_stmt->bindParam(':points', $withdrawal['points_redeemed'], PDO::PARAM_INT);
                        $log_stmt->bindParam(':request_id', $request_id, PDO::PARAM_INT);
                        $log_stmt->execute();
                        
                        // Update withdrawal status
                        $update_query = "UPDATE withdrawal_requests SET status = 'rejected', admin_notes = :reason, processed_at = NOW(), processed_by = :admin_id WHERE id = :request_id";
                        $update_stmt = $db->prepare($update_query);
                        $update_stmt->bindParam(':request_id', $request_id, PDO::PARAM_INT);
                        $update_stmt->bindParam(':reason', $reason);
                        $update_stmt->bindParam(':admin_id', $_SESSION['user_id'], PDO::PARAM_INT);
                        $update_stmt->execute();
                        
                        $db->commit();
                        $success_message = 'Withdrawal rejected and points refunded.';
                    } catch (Exception $e) {
                        $db->rollBack();
                        $error_message = 'Error processing rejection: ' . $e->getMessage();
                    }
                } else {
                    $error_message = 'Withdrawal request not found';
                }
            } else {
                $error_message = 'Error fetching withdrawal: ' . $withdrawal_stmt->errorInfo()[2];
            }
            break;
    }
}

// Get wallet statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM user_wallets) as total_wallets,
    (SELECT COALESCE(SUM(credits), 0) FROM users) as total_credits,
    (SELECT COALESCE(SUM(points_balance), 0) FROM user_wallets) as total_points,
    (SELECT COUNT(*) FROM withdrawal_requests WHERE status = 'pending') as pending_withdrawals,
    (SELECT COALESCE(SUM(amount), 0) FROM withdrawal_requests WHERE status = 'pending') as pending_amount,
    (SELECT COUNT(*) FROM credit_transactions WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as monthly_credits";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get filters for pending withdrawals
$withdrawal_status_filter = $_GET['withdrawal_status'] ?? 'pending';
$withdrawal_page = max(1, intval($_GET['withdrawal_page'] ?? 1));
$withdrawal_per_page = 20;
$withdrawal_offset = ($withdrawal_page - 1) * $withdrawal_per_page;

// Build WHERE clause for withdrawals
$withdrawal_where = ['1=1'];
$withdrawal_params = [];
if ($withdrawal_status_filter !== 'all') {
    $withdrawal_where[] = "wr.status = :status";
    $withdrawal_params[':status'] = $withdrawal_status_filter;
}

// Get total withdrawal count
$withdrawal_count_query = "SELECT COUNT(*) as total FROM withdrawal_requests wr WHERE " . implode(' AND ', $withdrawal_where);
$withdrawal_count_stmt = $db->prepare($withdrawal_count_query);
$withdrawal_count_stmt->execute($withdrawal_params);
$total_withdrawals = $withdrawal_count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$withdrawal_total_pages = ceil($total_withdrawals / $withdrawal_per_page);

// Get withdrawals with pagination
$withdrawals_query = "SELECT wr.*, u.username, u.email 
                     FROM withdrawal_requests wr
                     JOIN users u ON wr.user_id = u.id
                     WHERE " . implode(' AND ', $withdrawal_where) . "
                     ORDER BY wr.created_at ASC
                     LIMIT :limit OFFSET :offset";
$withdrawals_stmt = $db->prepare($withdrawals_query);
$withdrawals_stmt->bindParam(':limit', $withdrawal_per_page, PDO::PARAM_INT);
$withdrawals_stmt->bindParam(':offset', $withdrawal_offset, PDO::PARAM_INT);
foreach ($withdrawal_params as $key => $value) {
    $withdrawals_stmt->bindParam($key, $value);
}
$withdrawals_stmt->execute();
$pending_withdrawals = $withdrawals_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filters for transactions
$transaction_type_filter = $_GET['transaction_type'] ?? 'all';
$transaction_page = max(1, intval($_GET['transaction_page'] ?? 1));
$transaction_per_page = 20;
$transaction_offset = ($transaction_page - 1) * $transaction_per_page;

// Build separate WHERE clauses for points and credits
$pt_where = ['1=1'];
$ct_where = ['1=1'];
$transaction_params = [];
if ($transaction_type_filter !== 'all') {
    $pt_where[] = "pt.type = :type";
    $ct_where[] = "ct.type = :type";
    $transaction_params[':type'] = $transaction_type_filter;
}

// Get total transaction count
$transaction_count_query = "SELECT COUNT(*) as total 
                           FROM (
                               SELECT id FROM points_transactions pt WHERE " . implode(' AND ', $pt_where) . "
                               UNION ALL
                               SELECT id FROM credit_transactions ct WHERE " . implode(' AND ', $ct_where) . "
                           ) as combined";
$transaction_count_stmt = $db->prepare($transaction_count_query);
$transaction_count_stmt->execute($transaction_params);
$total_transactions = $transaction_count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$transaction_total_pages = ceil($total_transactions / $transaction_per_page);

// Get recent transactions with pagination
$transactions_query = "SELECT * FROM (
    SELECT pt.id, pt.user_id, u.username, pt.points as amount, pt.type, pt.description, pt.created_at, 'points' as source
    FROM points_transactions pt
    JOIN users u ON pt.user_id = u.id
    WHERE " . implode(' AND ', $pt_where) . "
    UNION ALL
    SELECT ct.id, ct.user_id, u.username, ct.amount, ct.type, ct.description, ct.created_at, 'credits' as source
    FROM credit_transactions ct
    JOIN users u ON ct.user_id = u.id
    WHERE " . implode(' AND ', $ct_where) . "
) as combined
ORDER BY created_at DESC
LIMIT :limit OFFSET :offset";
$transactions_stmt = $db->prepare($transactions_query);
$transactions_stmt->bindParam(':limit', $transaction_per_page, PDO::PARAM_INT);
$transactions_stmt->bindParam(':offset', $transaction_offset, PDO::PARAM_INT);
foreach ($transaction_params as $key => $value) {
    $transactions_stmt->bindParam($key, $value);
}
$transactions_stmt->execute();
$recent_transactions = $transactions_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Wallet Management - Admin Panel';
include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Wallet Management</h1>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- Wallet Statistics -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Credits</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($stats['total_credits'], 4); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Points</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_points']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-coins fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Withdrawals</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['pending_withdrawals']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-arrow-up fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Monthly Credits</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['monthly_credits']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Withdrawals -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Withdrawal Requests</h6>
                </div>
                <div class="card-body">
                    <form method="GET" class="mb-3">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select name="withdrawal_status" class="form-select" onchange="this.form.submit()">
                                    <option value="all" <?php echo $withdrawal_status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="pending" <?php echo $withdrawal_status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="completed" <?php echo $withdrawal_status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="rejected" <?php echo $withdrawal_status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                        </div>
                    </form>

                    <?php if (!empty($pending_withdrawals)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Amount</th>
                                        <th>Points</th>
                                        <th>Method</th>
                                        <th>Wallet Address</th>
                                        <th>Requested</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_withdrawals as $withdrawal): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($withdrawal['username']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($withdrawal['email']); ?></small>
                                        </td>
                                        <td>
                                            <strong>$<?php echo number_format($withdrawal['net_amount'], 4); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                Gross: $<?php echo number_format($withdrawal['amount'], 4); ?>
                                                <?php if ($withdrawal['withdrawal_fee'] > 0): ?>
                                                    <br>Fee: $<?php echo number_format($withdrawal['withdrawal_fee'], 4); ?>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td><?php echo number_format($withdrawal['points_redeemed']); ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo ucfirst($withdrawal['withdrawal_method']); ?>
                                            </span>
                                            <br>
                                            <small><?php echo htmlspecialchars($withdrawal['currency']); ?></small>
                                        </td>
                                        <td style="max-width: 200px; word-break: break-all;">
                                            <?php if ($withdrawal['faucetpay_email']): ?>
                                                <i class="fas fa-envelope text-primary"></i>
                                                <small><?php echo htmlspecialchars($withdrawal['faucetpay_email']); ?></small>
                                            <?php else: ?>
                                                <i class="fas fa-wallet text-info"></i>
                                                <small><?php echo htmlspecialchars($withdrawal['wallet_address']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($withdrawal['created_at'])); ?></td>
                                        <td>
                                            <?php if ($withdrawal['status'] === 'pending'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="approve_withdrawal">
                                                    <input type="hidden" name="request_id" value="<?php echo $withdrawal['id']; ?>">
                                                    <button type="submit" class="btn btn-success btn-sm" 
                                                            onclick="return confirm('Approve this withdrawal? This action cannot be undone.')">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                </form>
                                                
                                                <button class="btn btn-danger btn-sm" 
                                                        onclick="rejectWithdrawal(<?php echo $withdrawal['id']; ?>)">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            <?php else: ?>
                                                <span class="badge bg-<?php echo $withdrawal['status'] === 'completed' ? 'success' : 'danger'; ?>">
                                                    <?php echo ucfirst($withdrawal['status']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Withdrawal Pagination -->
                        <?php if ($withdrawal_total_pages > 1): ?>
                            <nav>
                                <ul class="pagination justify-content-center">
                                    <?php if ($withdrawal_page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['withdrawal_page' => $withdrawal_page - 1])); ?>">Previous</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $withdrawal_page - 2); $i <= min($withdrawal_total_pages, $withdrawal_page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $withdrawal_page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['withdrawal_page' => $i])); ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($withdrawal_page < $withdrawal_total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['withdrawal_page' => $withdrawal_page + 1])); ?>">Next</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h5>No Withdrawal Requests</h5>
                            <p class="text-muted">No withdrawal requests found for the selected status.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Credit & Points Transactions</h6>
                </div>
                <div class="card-body">
                    <form method="GET" class="mb-3">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Transaction Type</label>
                                <select name="transaction_type" class="form-select" onchange="this.form.submit()">
                                    <option value="all" <?php echo $transaction_type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                                    <option value="earned" <?php echo $transaction_type_filter === 'earned' ? 'selected' : ''; ?>>Earned</option>
                                    <option value="spent" <?php echo $transaction_type_filter === 'spent' ? 'selected' : ''; ?>>Spent</option>
                                    <option value="refund" <?php echo $transaction_type_filter === 'refund' ? 'selected' : ''; ?>>Refund</option>
                                    <option value="withdrawal_approved" <?php echo $transaction_type_filter === 'withdrawal_approved' ? 'selected' : ''; ?>>Withdrawal Approved</option>
                                    <option value="deposit" <?php echo $transaction_type_filter === 'deposit' ? 'selected' : ''; ?>>Deposit</option>
                                    <option value="adjustment" <?php echo $transaction_type_filter === 'adjustment' ? 'selected' : ''; ?>>Adjustment</option>
                                    <option value="withdrawal" <?php echo $transaction_type_filter === 'withdrawal' ? 'selected' : ''; ?>>Withdrawal</option>
                                </select>
                            </div>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Amount</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_transactions as $transaction): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($transaction['username']); ?></td>
                                    <td>
                                        <span class="<?php echo $transaction['amount'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php 
                                            $prefix = $transaction['source'] === 'credits' ? '$' : '';
                                            echo ($transaction['amount'] > 0 ? '+' : '') . $prefix . number_format($transaction['amount'], $transaction['source'] === 'credits' ? 4 : 0); 
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $transaction['source'] === 'credits' ? 'primary' : 'secondary'; ?>">
                                            <?php echo ucfirst($transaction['source']) . ' - ' . ucfirst(str_replace('_', ' ', $transaction['type'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                    <td><?php echo date('M j, g:i A', strtotime($transaction['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Transaction Pagination -->
                    <?php if ($transaction_total_pages > 1): ?>
                        <nav>
                            <ul class="pagination justify-content-center">
                                <?php if ($transaction_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['transaction_page' => $transaction_page - 1])); ?>">Previous</a>
                                        </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $transaction_page - 2); $i <= min($transaction_total_pages, $transaction_page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i == $transaction_page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['transaction_page' => $i])); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($transaction_page < $transaction_total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['transaction_page' => $transaction_page + 1])); ?>">Next</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Reject Withdrawal Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Withdrawal Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="rejectForm">
                <input type="hidden" name="action" value="reject_withdrawal">
                <input type="hidden" name="request_id" id="rejectRequestId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Reason for Rejection</label>
                        <textarea name="reason" class="form-control" rows="3" 
                                  placeholder="Explain why this withdrawal is being rejected..." required></textarea>
                    </div>
                    <div class="alert alert-warning">
                        <strong>Note:</strong> Points will be automatically refunded to the user's account.
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
function rejectWithdrawal(requestId) {
    document.getElementById('rejectRequestId').value = requestId;
    const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
    modal.show();
}
</script>

<?php include 'includes/admin_footer.php'; ?>
