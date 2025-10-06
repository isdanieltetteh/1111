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
    $user_id = intval($_POST['user_id'] ?? 0);
    
    switch ($action) {
        case 'ban':
            $ban_reason = trim($_POST['ban_reason']);
            $update_query = "UPDATE users SET is_banned = 1, ban_reason = :ban_reason WHERE id = :user_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':ban_reason', $ban_reason);
            $update_stmt->bindParam(':user_id', $user_id);
            if ($update_stmt->execute()) {
                // Log security event
                require_once '../includes/security.php';
                $security = new SecurityManager($db);
                $security->logSecurityEvent($_SERVER['REMOTE_ADDR'], 'user_banned_by_admin', [
                    'banned_user_id' => $user_id,
                    'ban_reason' => $ban_reason,
                    'admin_id' => $_SESSION['user_id']
                ], 'medium', $user_id);
                
                $success_message = 'User banned successfully!';
            } else {
                $error_message = 'Error banning user.';
            }
            break;
            
        case 'unban':
            $update_query = "UPDATE users SET is_banned = 0, ban_reason = NULL WHERE id = :user_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':user_id', $user_id);
            if ($update_stmt->execute()) {
                // Log security event
                require_once '../includes/security.php';
                $security = new SecurityManager($db);
                $security->logSecurityEvent($_SERVER['REMOTE_ADDR'], 'user_unbanned_by_admin', [
                    'unbanned_user_id' => $user_id,
                    'admin_id' => $_SESSION['user_id']
                ], 'low', $user_id);
                
                $success_message = 'User unbanned successfully!';
            } else {
                $error_message = 'Error unbanning user.';
            }
            break;
            
        case 'make_moderator':
            $update_query = "UPDATE users SET is_moderator = 1 WHERE id = :user_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':user_id', $user_id);
            if ($update_stmt->execute()) {
                $success_message = 'User promoted to moderator!';
            } else {
                $error_message = 'Error promoting user.';
            }
            break;
            
        case 'remove_moderator':
            $update_query = "UPDATE users SET is_moderator = 0 WHERE id = :user_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':user_id', $user_id);
            if ($update_stmt->execute()) {
                $success_message = 'Moderator status removed!';
            } else {
                $error_message = 'Error removing moderator status.';
            }
            break;
            
        case 'edit_user':
            $edit_user_id = intval($_POST['user_id']);
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $reputation_points = intval($_POST['reputation_points']);
            $credits = floatval($_POST['credits']);
            $new_password = trim($_POST['new_password']);
            $is_admin = isset($_POST['is_admin']) ? 1 : 0;
            $is_moderator = isset($_POST['is_moderator']) ? 1 : 0;
            $is_banned = isset($_POST['is_banned']) ? 1 : 0;
            $ban_reason = trim($_POST['ban_reason']);
            
            // Validation
            if (empty($username) || empty($email)) {
                $error_message = 'Username and email are required';
                break;
            }
            
            // Check if username/email is taken by another user
            $check_query = "SELECT id FROM users WHERE (username = :username OR email = :email) AND id != :user_id";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':username', $username);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->bindParam(':user_id', $edit_user_id);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                $error_message = 'Username or email already exists';
                break;
            }
            
            try {
                $db->beginTransaction();
                
                // Handle avatar upload
                $avatar_path = null;
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    $max_size = 2 * 1024 * 1024; // 2MB
                    
                    if (in_array($_FILES['avatar']['type'], $allowed_types) && $_FILES['avatar']['size'] <= $max_size) {
                        $upload_dir = '../assets/images/avatars/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        // Resize avatar to standard size
                        $image_info = getimagesize($_FILES['avatar']['tmp_name']);
                        if ($image_info !== false) {
                            $source = null;
                            switch ($_FILES['avatar']['type']) {
                                case 'image/jpeg':
                                    $source = imagecreatefromjpeg($_FILES['avatar']['tmp_name']);
                                    break;
                                case 'image/png':
                                    $source = imagecreatefrompng($_FILES['avatar']['tmp_name']);
                                    break;
                                case 'image/gif':
                                    $source = imagecreatefromgif($_FILES['avatar']['tmp_name']);
                                    break;
                            }
                            
                            if ($source) {
                                $target_size = 200;
                                $resized = imagecreatetruecolor($target_size, $target_size);
                                
                                // Preserve transparency
                                if ($_FILES['avatar']['type'] === 'image/png' || $_FILES['avatar']['type'] === 'image/gif') {
                                    imagealphablending($resized, false);
                                    imagesavealpha($resized, true);
                                    $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
                                    imagefill($resized, 0, 0, $transparent);
                                }
                                
                                imagecopyresampled($resized, $source, 0, 0, 0, 0, $target_size, $target_size, $image_info[0], $image_info[1]);
                                
                                $file_extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                                $avatar_filename = 'avatar_' . $edit_user_id . '_' . time() . '.' . $file_extension;
                                $avatar_path = 'assets/images/avatars/' . $avatar_filename;
                                
                                $save_success = false;
                                switch ($_FILES['avatar']['type']) {
                                    case 'image/jpeg':
                                        $save_success = imagejpeg($resized, $upload_dir . $avatar_filename, 90);
                                        break;
                                    case 'image/png':
                                        $save_success = imagepng($resized, $upload_dir . $avatar_filename, 9);
                                        break;
                                    case 'image/gif':
                                        $save_success = imagegif($resized, $upload_dir . $avatar_filename);
                                        break;
                                }
                                
                                imagedestroy($source);
                                imagedestroy($resized);
                            }
                        }
                    }
                }
                
                // Build update query
                $update_fields = [
                    'username = :username',
                    'email = :email',
                    'reputation_points = :reputation_points',
                    'credits = :credits',
                    'is_admin = :is_admin',
                    'is_moderator = :is_moderator',
                    'is_banned = :is_banned'
                ];
                
                $params = [
                    ':username' => $username,
                    ':email' => $email,
                    ':reputation_points' => $reputation_points,
                    ':credits' => $credits,
                    ':is_admin' => $is_admin,
                    ':is_moderator' => $is_moderator,
                    ':is_banned' => $is_banned,
                    ':user_id' => $edit_user_id
                ];
                
                if ($is_banned) {
                    $update_fields[] = 'ban_reason = :ban_reason';
                    $params[':ban_reason'] = $ban_reason;
                } else {
                    $update_fields[] = 'ban_reason = NULL';
                }
                
                if (!empty($new_password)) {
                    $update_fields[] = 'password = :password';
                    $params[':password'] = password_hash($new_password, PASSWORD_DEFAULT);
                }
                
                if ($avatar_path) {
                    $update_fields[] = 'avatar = :avatar';
                    $params[':avatar'] = $avatar_path;
                }
                
                $update_query = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = :user_id";
                $update_stmt = $db->prepare($update_query);
                
                if ($update_stmt->execute($params)) {
                    $success_message = 'User updated successfully!';
                } else {
                    $error_message = 'Error updating user';
                }
                
                $db->commit();
                
            } catch (Exception $e) {
                $db->rollback();
                $error_message = 'Error updating user: ' . $e->getMessage();
            }
            break;
            
        case 'adjust_points':
            $points = intval($_POST['points']);
            $reason = trim($_POST['reason']);
            
            if ($points != 0) {
                // Update user reputation
                $update_query = "UPDATE users SET reputation_points = reputation_points + :points WHERE id = :user_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':points', $points);
                $update_stmt->bindParam(':user_id', $user_id);
                $update_stmt->execute();
                
                // Add to wallet if wallet system exists
                require_once '../includes/wallet.php';
                $wallet_manager = new WalletManager($db);
                $wallet_manager->addPoints($user_id, $points, 'earned', $reason ?: 'Admin adjustment');
                
                $success_message = 'Points adjusted successfully!';
            }
            break;
    }
}

// Get filters
$status_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build WHERE clause
$where_conditions = ['1=1'];
$params = [];

if ($status_filter !== 'all') {
    switch ($status_filter) {
        case 'banned':
            $where_conditions[] = "u.is_banned = 1";
            break;
        case 'moderators':
            $where_conditions[] = "u.is_moderator = 1";
            break;
        case 'active':
            $where_conditions[] = "u.is_banned = 0 AND u.last_active >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
    }
}

if (!empty($search)) {
    $where_conditions[] = "(u.username LIKE :search OR u.email LIKE :search)";
    $params[':search'] = "%{$search}%";
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$count_query = "SELECT COUNT(*) as total FROM users u WHERE {$where_clause}";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_users = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_users / $per_page);

// Get users
$users_query = "SELECT u.*, l.name as level_name, l.badge_icon,
                (SELECT COUNT(*) FROM sites WHERE submitted_by = u.id) as total_submissions,
                (SELECT COUNT(*) FROM reviews WHERE user_id = u.id) as total_reviews_written,
                u.credits as credit_balance
                FROM users u
                LEFT JOIN levels l ON u.level_id = l.id
                WHERE {$where_clause}
                ORDER BY u.created_at DESC
                LIMIT {$per_page} OFFSET {$offset}";

$users_stmt = $db->prepare($users_query);
$users_stmt->execute($params);
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Users Management - Admin Panel';
include 'includes/admin_header.php';
?>

<?php include 'includes/admin_sidebar.php'; ?>

<main class="admin-main">
    <div class="admin-page-header">
        <div>
            <div class="admin-breadcrumb">
                <i class="fas fa-users text-primary"></i>
                <span>Community</span>
                <span class="text-muted">Users</span>
            </div>
            <h1>User Intelligence</h1>
            <p class="text-muted mb-0">Ban, promote, and analyze every account in the ecosystem.</p>
        </div>
    </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

    <!-- Filters -->
    <div class="admin-content-wrapper mb-4">
        <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Search Users</label>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Username or email..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Users</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active Users</option>
                                <option value="banned" <?php echo $status_filter === 'banned' ? 'selected' : ''; ?>>Banned Users</option>
                                <option value="moderators" <?php echo $status_filter === 'moderators' ? 'selected' : ''; ?>>Moderators</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary d-block">Filter</button>
                        </div>
        </form>
    </div>

    <!-- Users Table -->
    <div class="admin-content-wrapper">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Level</th>
                                    <th>Reputation</th>
                                    <th>Activity</th>
                                    <th>Wallet</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="../<?php echo htmlspecialchars($user['avatar']); ?>" 
                                                 class="rounded-circle me-2" width="32" height="32">
                                            <div>
                                                <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($user['level_name']): ?>
                                            <span class="badge bg-primary">
                                                <?php echo $user['badge_icon']; ?> <?php echo htmlspecialchars($user['level_name']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo number_format($user['reputation_points']); ?></strong> pts
                                    </td>
                                    <td>
                                        <small>
                                            <?php echo $user['total_submissions']; ?> sites<br>
                                            <?php echo $user['total_reviews_written']; ?> reviews
                                        </small>
                                    </td>
                                    <td>
                                        $<?php echo number_format($user['credit_balance'], 4); ?>
                                    </td>
                                    <td>
                                        <?php if ($user['is_banned']): ?>
                                            <span class="badge bg-danger">Banned</span>
                                            <?php if ($user['ban_reason']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($user['ban_reason']); ?></small>
                                            <?php endif; ?>
                                        <?php elseif ($user['is_admin']): ?>
                                            <span class="badge bg-warning">Admin</span>
                                        <?php elseif ($user['is_moderator']): ?>
                                            <span class="badge bg-info">Moderator</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group-vertical btn-group-sm">
                                            <button class="btn btn-outline-primary btn-sm" 
                                                    onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                                <i class="fas fa-edit"></i> Edit User
                                            </button>
                                            
                                            <?php if (!$user['is_admin']): ?>
                                                <?php if ($user['is_banned']): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="unban">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="btn btn-success btn-sm">Unban</button>
                                                    </form>
                                                <?php else: ?>
                                                    <button class="btn btn-danger btn-sm" 
                                                            onclick="banUserWithReason(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                        Ban
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($user['is_moderator']): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="remove_moderator">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="btn btn-warning btn-sm">Remove Mod</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="make_moderator">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="btn btn-info btn-sm">Make Mod</button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <button class="btn btn-secondary btn-sm" 
                                                        onclick="adjustPoints(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                    Adjust Points
                                                </button>
                                                
                                                <button class="btn btn-outline-info btn-sm" 
                                                        onclick="viewUserActivity(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                    <i class="fas fa-history"></i> Activity
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav>
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
</main>

<!-- Ban User Modal -->
<div class="modal fade" id="banUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ban User Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="banUserForm">
                <input type="hidden" name="action" value="ban">
                <input type="hidden" name="user_id" id="banUserIdModal">
                <div class="modal-body">
                    <p>Banning user: <strong id="banUsernameModal"></strong></p>
                    
                    <div class="mb-3">
                        <label class="form-label">Ban Reason</label>
                        <select name="ban_reason" class="form-select" required>
                            <option value="">Select reason...</option>
                            <option value="Multiple accounts from same IP address">Multiple accounts from same IP address</option>
                            <option value="Duplicate account violation">Duplicate account violation</option>
                            <option value="Fraudulent activity detected">Fraudulent activity detected</option>
                            <option value="Spam or abuse">Spam or abuse</option>
                            <option value="Terms of service violation">Terms of service violation</option>
                            <option value="Fake reviews or manipulation">Fake reviews or manipulation</option>
                            <option value="Temporary email usage">Temporary email usage</option>
                            <option value="Suspicious behavior pattern">Suspicious behavior pattern</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-warning">
                        <strong>Warning:</strong> This action will permanently ban the user account. 
                        The user will see this reason when attempting to log in.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Ban Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Adjust Points Modal -->
<div class="modal fade" id="adjustPointsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adjust User Points</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="adjustPointsForm">
                <input type="hidden" name="action" value="adjust_points">
                <input type="hidden" name="user_id" id="adjustUserId">
                <div class="modal-body">
                    <p>Adjusting points for: <strong id="adjustUsername"></strong></p>
                    
                    <div class="mb-3">
                        <label class="form-label">Points Adjustment</label>
                        <input type="number" name="points" class="form-control" 
                               placeholder="Enter positive or negative number" required>
                        <small class="form-text text-muted">Use negative numbers to deduct points</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason (Optional)</label>
                        <textarea name="reason" class="form-control" rows="3" 
                                  placeholder="Reason for adjustment..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Adjust Points</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="editUserForm">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center mb-3">
                                <img id="editUserAvatar" src="" class="rounded-circle mb-2" width="100" height="100">
                                <h6 id="editUserDisplayName"></h6>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">New Avatar</label>
                                <input type="file" name="avatar" class="form-control" accept="image/*">
                                <small class="form-text text-muted">Max 2MB, JPG/PNG/GIF only</small>
                            </div>
                        </div>
                        
                        <div class="col-md-8">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Username</label>
                                        <input type="text" name="username" id="editUsername" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" id="editEmail" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Reputation Points</label>
                                        <input type="number" name="reputation_points" id="editReputationPoints" class="form-control" min="0">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Credits Balance</label>
                                        <input type="number" name="credits" id="editCredits" class="form-control" step="0.0001" min="0">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">New Password (leave blank to keep current)</label>
                                <input type="password" name="new_password" id="editNewPassword" class="form-control">
                            </div>
                            
                            <div class="mb-3">
                                <h6>Permissions & Status</h6>
                                <div class="form-check">
                                    <input type="checkbox" name="is_admin" id="editIsAdmin" class="form-check-input">
                                    <label class="form-check-label">Administrator</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="is_moderator" id="editIsModerator" class="form-check-input">
                                    <label class="form-check-label">Moderator</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="is_banned" id="editIsBanned" class="form-check-input" onchange="toggleBanReason()">
                                    <label class="form-check-label">Banned</label>
                                </div>
                            </div>
                            
                            <div class="mb-3" id="banReasonGroup" style="display: none;">
                                <label class="form-label">Ban Reason</label>
                                <textarea name="ban_reason" id="editBanReason" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- User Activity Modal -->
<div class="modal fade" id="userActivityModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">User Activity History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="userActivityContent">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function banUserWithReason(userId, username) {
    document.getElementById('banUserIdModal').value = userId;
    document.getElementById('banUsernameModal').textContent = username;
    
    const modal = new bootstrap.Modal(document.getElementById('banUserModal'));
    modal.show();
}

function adjustPoints(userId, username) {
    document.getElementById('adjustUserId').value = userId;
    document.getElementById('adjustUsername').textContent = username;
    
    const modal = new bootstrap.Modal(document.getElementById('adjustPointsModal'));
    modal.show();
}

function editUser(user) {
    document.getElementById('editUserId').value = user.id;
    document.getElementById('editUserDisplayName').textContent = user.username;
    document.getElementById('editUserAvatar').src = '../' + user.avatar;
    document.getElementById('editUsername').value = user.username;
    document.getElementById('editEmail').value = user.email;
    document.getElementById('editReputationPoints').value = user.reputation_points;
    document.getElementById('editCredits').value = user.credit_balance;
    document.getElementById('editIsAdmin').checked = user.is_admin == 1;
    document.getElementById('editIsModerator').checked = user.is_moderator == 1;
    document.getElementById('editIsBanned').checked = user.is_banned == 1;
    document.getElementById('editBanReason').value = user.ban_reason || '';
    
    toggleBanReason();
    
    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
}

function toggleBanReason() {
    const isBanned = document.getElementById('editIsBanned').checked;
    const banReasonGroup = document.getElementById('banReasonGroup');
    banReasonGroup.style.display = isBanned ? 'block' : 'none';
}

function viewUserActivity(userId, username) {
    fetch(`ajax/get-user-activity.php?user_id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('userActivityContent').innerHTML = data.html;
                const modal = new bootstrap.Modal(document.getElementById('userActivityModal'));
                modal.show();
            } else {
                alert('Error loading user activity: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error loading user activity:', error);
            alert('Error loading user activity');
        });
}
</script>

<?php include 'includes/admin_footer.php'; ?>
