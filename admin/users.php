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

$user_stats_stmt = $db->query(
    "SELECT 
        COUNT(*) AS total_users,
        SUM(CASE WHEN is_banned = 1 THEN 1 ELSE 0 END) AS banned_users,
        SUM(CASE WHEN is_moderator = 1 THEN 1 ELSE 0 END) AS moderator_users,
        SUM(CASE WHEN is_admin = 1 THEN 1 ELSE 0 END) AS admin_users,
        SUM(CASE WHEN is_banned = 0 AND last_active >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS active_week
     FROM users"
);
$user_stats = $user_stats_stmt ? $user_stats_stmt->fetch(PDO::FETCH_ASSOC) : [];
$user_stats = $user_stats ?: [
    'total_users' => 0,
    'banned_users' => 0,
    'moderator_users' => 0,
    'admin_users' => 0,
    'active_week' => 0,
];

$recent_signups_stmt = $db->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$recent_signups = $recent_signups_stmt ? (int) $recent_signups_stmt->fetchColumn() : 0;

$top_contributors_stmt = $db->query(
    "SELECT id, username, avatar, reputation_points, total_reviews, total_submissions
     FROM users
     WHERE is_banned = 0
     ORDER BY reputation_points DESC
     LIMIT 4"
);
$top_contributors = $top_contributors_stmt ? $top_contributors_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

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
        <div class="alert alert-success shadow-sm border-0"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger shadow-sm border-0"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="admin-metric-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="metric-label">Total Members</p>
                        <p class="metric-value mb-1"><?php echo number_format($user_stats['total_users']); ?></p>
                        <span class="metric-trend up"><i class="fas fa-user-shield"></i><?php echo number_format($user_stats['admin_users']); ?> admins</span>
                    </div>
                    <span class="metric-icon info"><i class="fas fa-users"></i></span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="admin-metric-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="metric-label">New This Week</p>
                        <p class="metric-value mb-1"><?php echo number_format($recent_signups); ?></p>
                        <span class="metric-trend up"><i class="fas fa-user-plus"></i>Fresh arrivals</span>
                    </div>
                    <span class="metric-icon success"><i class="fas fa-wand-magic-sparkles"></i></span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="admin-metric-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="metric-label">Active (7 days)</p>
                        <p class="metric-value mb-1"><?php echo number_format($user_stats['active_week']); ?></p>
                        <span class="metric-trend up"><i class="fas fa-signal"></i>Engaged right now</span>
                    </div>
                    <span class="metric-icon success"><i class="fas fa-chart-line"></i></span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="admin-metric-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="metric-label">Guarded Accounts</p>
                        <p class="metric-value mb-1"><?php echo number_format($user_stats['banned_users']); ?></p>
                        <span class="metric-trend down"><i class="fas fa-user-lock"></i>On compliance hold</span>
                    </div>
                    <span class="metric-icon danger"><i class="fas fa-user-lock"></i></span>
                </div>
            </div>
        </div>
    </div>

    <div class="admin-content-wrapper mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <h2 class="admin-section-title">Member Filters</h2>
                <p class="admin-section-subtitle mb-0">Slice the community by role, health, and engagement.</p>
            </div>
        </div>
        <form method="GET" class="admin-toolbar">
            <div>
                <label class="form-label">Search Users</label>
                <input type="text" name="search" class="form-control" placeholder="Username or email..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Users</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active Users</option>
                    <option value="banned" <?php echo $status_filter === 'banned' ? 'selected' : ''; ?>>Banned Users</option>
                    <option value="moderators" <?php echo $status_filter === 'moderators' ? 'selected' : ''; ?>>Moderators</option>
                </select>
            </div>
            <div class="ms-auto">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <a href="users.php" class="btn btn-outline-secondary">Reset</a>
                    <button type="submit" class="btn btn-primary shadow-hover">Apply</button>
                </div>
            </div>
        </form>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            <div class="admin-content-wrapper h-100">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="admin-section-title">Community Leaders</h2>
                        <p class="admin-section-subtitle mb-0">Highest reputation members driving engagement.</p>
                    </div>
                </div>
                <?php if (!empty($top_contributors)): ?>
                    <div class="admin-leaderboard">
                        <?php foreach ($top_contributors as $index => $leader): ?>
                            <?php $leaderAvatar = !empty($leader['avatar']) ? $leader['avatar'] : 'assets/images/default-avatar.png'; ?>
                            <div class="admin-leader-item">
                                <span class="admin-leader-rank"><?php echo $index + 1; ?></span>
                                <span class="avatar-ring sm">
                                    <img src="../<?php echo htmlspecialchars($leaderAvatar); ?>" alt="<?php echo htmlspecialchars($leader['username']); ?>">
                                </span>
                                <div class="flex-grow-1">
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <strong><?php echo htmlspecialchars($leader['username']); ?></strong>
                                        <span class="status-chip success"><i class="fas fa-bolt"></i><?php echo number_format($leader['reputation_points']); ?> pts</span>
                                    </div>
                                    <div class="admin-leader-meta">
                                        <span><?php echo number_format($leader['total_reviews']); ?> reviews</span>
                                        <span>•</span>
                                        <span><?php echo number_format($leader['total_submissions']); ?> sites</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="admin-subtle-card text-center">
                        <h6 class="mb-1">No leaders yet</h6>
                        <p class="mb-0">As users engage the leaderboard will populate automatically.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="admin-subtle-card h-100">
                <h6>Moderation Radar</h6>
                <ul class="admin-quick-list mb-0">
                    <li><span>Moderators on duty</span><strong><?php echo number_format($user_stats['moderator_users']); ?></strong></li>
                    <li><span>Admins</span><strong><?php echo number_format($user_stats['admin_users']); ?></strong></li>
                    <li><span>Accounts on watch</span><strong><?php echo number_format($user_stats['banned_users']); ?></strong></li>
                    <li><span>New entrants (7d)</span><strong><?php echo number_format($recent_signups); ?></strong></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="admin-content-wrapper">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <h2 class="admin-section-title">User Directory</h2>
                <p class="admin-section-subtitle mb-0">Viewing <?php echo number_format($total_users); ?> accounts (page <?php echo $page; ?> of <?php echo max($total_pages, 1); ?>).</p>
            </div>
        </div>

        <?php if (!empty($users)): ?>
            <div class="table-responsive">
                <table class="table table-hover admin-table align-middle">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Level</th>
                            <th>Reputation</th>
                            <th>Activity</th>
                            <th>Wallet</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <?php $avatarPath = !empty($user['avatar']) ? $user['avatar'] : 'assets/images/default-avatar.png'; ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="avatar-ring sm">
                                            <img src="../<?php echo htmlspecialchars($avatarPath); ?>" alt="<?php echo htmlspecialchars($user['username']); ?>">
                                        </span>
                                        <div>
                                            <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                            <div class="table-meta"><?php echo htmlspecialchars($user['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($user['level_name']): ?>
                                        <span class="status-chip info"><i class="fas fa-medal"></i><?php echo htmlspecialchars($user['level_name']); ?></span>
                                    <?php else: ?>
                                        <span class="table-meta">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo number_format($user['reputation_points']); ?></strong>
                                    <div class="table-meta">Reviews: <?php echo number_format($user['total_reviews_written']); ?></div>
                                </td>
                                <td>
                                    <div class="table-meta">Sites: <?php echo number_format($user['total_submissions']); ?></div>
                                    <div class="table-meta">Last active: <?php echo $user['last_active'] ? date('M j, Y', strtotime($user['last_active'])) : 'No activity'; ?></div>
                                </td>
                                <td>
                                    <strong><?php echo number_format($user['credit_balance'], 2); ?></strong>
                                    <div class="table-meta">Wallet credits</div>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php if ($user['is_banned']): ?>
                                            <span class="status-chip danger"><i class="fas fa-user-slash"></i>Banned</span>
                                        <?php else: ?>
                                            <span class="status-chip success"><i class="fas fa-user-check"></i>Active</span>
                                        <?php endif; ?>
                                        <?php if ($user['is_admin']): ?>
                                            <span class="status-chip warning"><i class="fas fa-crown"></i>Admin</span>
                                        <?php endif; ?>
                                        <?php if ($user['is_moderator']): ?>
                                            <span class="status-chip info"><i class="fas fa-shield-halved"></i>Moderator</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($user['is_banned'] && $user['ban_reason']): ?>
                                        <div class="table-meta text-danger fw-semibold mt-1"><?php echo htmlspecialchars($user['ban_reason']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="table-action-group justify-content-end">
                                        <button type="button" class="btn btn-outline-primary btn-sm shadow-hover" onclick="editUser(<?php echo htmlspecialchars(json_encode($user), ENT_QUOTES, 'UTF-8'); ?>)">
                                            <i class="fas fa-user-cog"></i> Edit
                                        </button>

                                        <?php if (!$user['is_admin']): ?>
                                            <?php if ($user['is_banned']): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="unban">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="btn btn-success btn-sm shadow-hover">Unban</button>
                                                </form>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-danger btn-sm shadow-hover" onclick="banUserWithReason(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>')">
                                                    Ban
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($user['is_moderator']): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="remove_moderator">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="btn btn-warning btn-sm shadow-hover">Remove Mod</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="make_moderator">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="btn btn-info btn-sm shadow-hover">Make Mod</button>
                                                </form>
                                            <?php endif; ?>

                                            <button type="button" class="btn btn-secondary btn-sm shadow-hover" onclick="adjustPoints(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>')">
                                                Adjust Points
                                            </button>

                                            <button type="button" class="btn btn-outline-info btn-sm shadow-hover" onclick="viewUserActivity(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>')">
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
        <?php else: ?>
            <div class="admin-subtle-card text-center">
                <i class="fas fa-users-slash fa-2x text-muted mb-2"></i>
                <h6>No users match your filters</h6>
                <p class="mb-0">Try adjusting your search criteria to surface more accounts.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Ban User Modal -->
<div class="modal fade" id="banUserModal" tabindex="-1">
    <div class="modal-dialog admin-modal">
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
                    
                    <div class="alert alert-warning shadow-sm border-0">
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
    <div class="modal-dialog admin-modal">
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
    <div class="modal-dialog modal-xl admin-modal">
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
    <div class="modal-dialog modal-lg admin-modal">
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
