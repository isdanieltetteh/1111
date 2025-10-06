<?php
require_once 'config/config.php';
require_once 'includes/auth.php';
require_once 'config/database.php';

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
$error_message = '';
$success_message = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $email_notifications = isset($_POST['email_notifications']);
    
    // Validation
    if (empty($email)) {
        $error_message = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address';
    } else {
        // Check if email is already taken by another user
        $email_check = "SELECT id FROM users WHERE email = :email AND id != :user_id";
        $email_stmt = $db->prepare($email_check);
        $email_stmt->bindParam(':email', $email);
        $email_stmt->bindParam(':user_id', $user_id);
        $email_stmt->execute();
        
        if ($email_stmt->rowCount() > 0) {
            $error_message = 'This email is already in use by another account';
        } else {
            $update_fields = ['email = :email', 'email_notifications = :email_notifications'];
            $params = [':email' => $email, ':email_notifications' => $email_notifications ? 1 : 0, ':user_id' => $user_id];
            
            // Handle password change
            if (!empty($new_password)) {
                if (empty($current_password)) {
                    $error_message = 'Current password is required to change password';
                } elseif ($new_password !== $confirm_password) {
                    $error_message = 'New passwords do not match';
                } elseif (strlen($new_password) < 6) {
                    $error_message = 'New password must be at least 6 characters long';
                } else {
                    // Verify current password
                    if (password_verify($current_password, $user['password'])) {
                        $update_fields[] = 'password = :password';
                        $params[':password'] = password_hash($new_password, PASSWORD_DEFAULT);
                    } else {
                        $error_message = 'Current password is incorrect';
                    }
                }
            }
            
            // Handle avatar upload
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 2 * 1024 * 1024; // 2MB
                
                if (in_array($_FILES['avatar']['type'], $allowed_types) && $_FILES['avatar']['size'] <= $max_size) {
                    // Get image dimensions
                    $image_info = getimagesize($_FILES['avatar']['tmp_name']);
                    if ($image_info === false) {
                        $error_message = 'Invalid image file';
                    } else {
                        $width = $image_info[0];
                        $height = $image_info[1];
                        
                        // Define fixed avatar dimensions
                        $avatar_width = 200;
                        $avatar_height = 200;
                        
                        // Create new image with fixed dimensions
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
                            // Create square canvas for avatar
                            $resized = imagecreatetruecolor($avatar_width, $avatar_height);
                            
                            // Preserve transparency for PNG and GIF
                            if ($_FILES['avatar']['type'] === 'image/png' || $_FILES['avatar']['type'] === 'image/gif') {
                                imagealphablending($resized, false);
                                imagesavealpha($resized, true);
                                $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
                                imagefill($resized, 0, 0, $transparent);
                            }
                            
                            // Calculate crop dimensions to maintain aspect ratio
                            $src_x = 0;
                            $src_y = 0;
                            $src_w = $width;
                            $src_h = $height;
                            
                            if ($width > $height) {
                                // Landscape
                                $src_w = $height;
                                $src_x = ($width - $height) / 2;
                            } elseif ($height > $width) {
                                // Portrait
                                $src_h = $width;
                                $src_y = ($height - $width) / 2;
                            }
                            
                            // Resize and crop to square
                            imagecopyresampled($resized, $source, 0, 0, $src_x, $src_y, $avatar_width, $avatar_height, $src_w, $src_h);
                            
                            $upload_dir = 'assets/images/avatars/';
                            if (!is_dir($upload_dir)) {
                                mkdir($upload_dir, 0755, true);
                            }
                            
                            $file_extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                            $avatar_filename = 'avatar_' . $user_id . '_' . time() . '.' . $file_extension;
                            $avatar_path = $upload_dir . $avatar_filename;
                            
                            $save_success = false;
                            switch ($_FILES['avatar']['type']) {
                                case 'image/jpeg':
                                    $save_success = imagejpeg($resized, $avatar_path, 90);
                                    break;
                                case 'image/png':
                                    $save_success = imagepng($resized, $avatar_path, 9);
                                    break;
                                case 'image/gif':
                                    $save_success = imagegif($resized, $avatar_path);
                                    break;
                            }
                            
                            if ($save_success) {
                                // Delete old avatar if it's not the default
                                if ($user['avatar'] !== 'assets/images/default-avatar.png' && file_exists($user['avatar'])) {
                                    unlink($user['avatar']);
                                }
                                
                                $update_fields[] = 'avatar = :avatar';
                                $params[':avatar'] = $avatar_path;
                            }
                            
                            imagedestroy($source);
                            imagedestroy($resized);
                        }
                    }
                } else {
                    $error_message = 'Invalid file type or file too large. Max 2MB, JPG/PNG/GIF only.';
                }
            }
            
            if (empty($error_message)) {
                // Update user profile
                $update_query = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = :user_id";
                $update_stmt = $db->prepare($update_query);
                
                if ($update_stmt->execute($params)) {
                    $success_message = 'Profile updated successfully!';
                    
                    // Update session email if changed
                    if ($email !== $_SESSION['email']) {
                        $_SESSION['email'] = $email;
                    }
                    
                    // Refresh user data
                    $user = $auth->getCurrentUser();
                } else {
                    $error_message = 'Error updating profile. Please try again.';
                }
            }
        }
    }
    
    // Handle account deletion
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_account'])) {
        $confirm_username = trim($_POST['confirm_username']);
        $confirm_password = $_POST['confirm_password'];
        
        if ($confirm_username !== $user['username']) {
            $error_message = 'Username confirmation does not match';
        } elseif (!password_verify($confirm_password, $user['password'])) {
            $error_message = 'Password is incorrect';
        } else {
            try {
                $db->beginTransaction();
                
                // Soft delete user account
                $delete_query = "UPDATE users SET 
                                is_banned = 1, 
                                ban_reason = 'Account deleted by user',
                                email = CONCAT('deleted_', id, '@deleted.local'),
                                username = CONCAT('deleted_user_', id)
                                WHERE id = :user_id";
                $delete_stmt = $db->prepare($delete_query);
                $delete_stmt->bindParam(':user_id', $user_id);
                $delete_stmt->execute();
                
                // Anonymize reviews
                $anonymize_reviews = "UPDATE reviews SET comment = '[User deleted their account]' WHERE user_id = :user_id";
                $anonymize_stmt = $db->prepare($anonymize_reviews);
                $anonymize_stmt->bindParam(':user_id', $user_id);
                $anonymize_stmt->execute();
                
                $db->commit();
                
                // Logout user
                $auth->logout();
                
                header('Location: index.php?deleted=1');
                exit();
                
            } catch (Exception $e) {
                $db->rollback();
                $error_message = 'Error deleting account. Please contact support.';
            }
        }
    }
}
?>
<?php
$page_title = 'Profile Settings - ' . SITE_NAME;
$page_description = 'Manage your ' . SITE_NAME . ' profile settings, avatar, and preferences.';
$current_page = 'dashboard';
include 'includes/header.php';
?>

<div class="page-wrapper flex-grow-1">
    <section class="page-hero pb-0">
        <div class="container">
            <div class="glass-card p-4 p-lg-5 animate-fade-in" data-aos="fade-up">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4">
                    <div class="d-flex align-items-start gap-3">
                        <div class="account-avatar-wrapper">
                            <img src="<?php echo htmlspecialchars($user['avatar']); ?>"
                                 alt="<?php echo htmlspecialchars($user['username']); ?> avatar"
                                 class="account-avatar">
                            <span class="status-indicator <?php echo $user['is_banned'] ? 'status-danger' : 'status-success'; ?>"></span>
                        </div>
                        <div>
                            <div class="dashboard-breadcrumb mb-2">
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb mb-0">
                                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                        <li class="breadcrumb-item active" aria-current="page">Account Settings</li>
                                    </ol>
                                </nav>
                            </div>
                            <h1 class="text-white fw-bold mb-1"><?php echo htmlspecialchars($user['username']); ?></h1>
                            <p class="text-muted mb-0">Member since <?php echo date('M Y', strtotime($user['created_at'])); ?> · <?php echo htmlspecialchars($user['active_badge_name']); ?></p>
                        </div>
                    </div>
                    <div class="text-lg-end">
                        <div class="option-chip justify-content-center ms-lg-auto">
                            <i class="fas fa-shield-halved"></i>
                            <span>Security optimized</span>
                        </div>
                        <a href="wallet.php" class="btn btn-theme btn-outline-glass mt-3">
                            <i class="fas fa-wallet me-2"></i>Wallet Overview
                        </a>
                    </div>
                </div>
            </div>
            <div class="dev-slot mt-4">Hero Banner 970x250</div>
        </div>
    </section>

    <section class="py-4">
        <div class="container">
            <?php
            $dashboard_nav_links = [
                [
                    'href' => 'dashboard.php',
                    'icon' => 'fa-gauge-high',
                    'label' => 'Overview',
                    'description' => 'Insights & rewards summary'
                ],
                [
                    'href' => 'my-submissions.php',
                    'icon' => 'fa-globe',
                    'label' => 'My Submissions',
                    'description' => 'Manage and update your listings'
                ],
                [
                    'href' => 'my-ads.php',
                    'icon' => 'fa-rectangle-ad',
                    'label' => 'My Campaigns',
                    'description' => 'Track ad performance & status'
                ],
                [
                    'href' => 'notifications.php',
                    'icon' => 'fa-bell',
                    'label' => 'Notifications',
                    'description' => 'Review alerts & platform updates'
                ],
                [
                    'href' => 'wallet.php',
                    'icon' => 'fa-wallet',
                    'label' => 'Wallet',
                    'description' => 'Monitor credits & transactions'
                ],
                [
                    'href' => 'support-tickets.php',
                    'icon' => 'fa-life-ring',
                    'label' => 'Support',
                    'description' => 'Submit & follow support tickets'
                ],
                [
                    'href' => 'promote-sites.php',
                    'icon' => 'fa-rocket',
                    'label' => 'Promotions',
                    'description' => 'Boost visibility with premium slots'
                ],
                [
                    'href' => 'buy-credits.php',
                    'icon' => 'fa-credit-card',
                    'label' => 'Buy Credits',
                    'description' => 'Top up instantly for upgrades'
                ],
                [
                    'href' => 'redeem-coupon.php',
                    'icon' => 'fa-ticket',
                    'label' => 'Redeem Coupons',
                    'description' => 'Apply promo codes for bonuses'
                ],
                [
                    'href' => 'profile.php',
                    'icon' => 'fa-user-gear',
                    'label' => 'Account Settings',
                    'description' => 'Update profile & security details'
                ]
            ];
            $dashboard_nav_current = basename($_SERVER['PHP_SELF'] ?? '');
            ?>
            <div class="glass-card p-4 p-lg-5 mb-4" data-aos="fade-up">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                    <div>
                        <h2 class="h5 text-white mb-1">Navigate Your Toolkit</h2>
                        <p class="text-muted mb-0">Quick links to every dashboard feature.</p>
                    </div>
                    <a href="dashboard.php" class="btn btn-theme btn-outline-glass btn-sm">
                        <i class="fas fa-gauge-high me-2"></i>Return to Overview
                    </a>
                </div>
                <div class="row g-3 row-cols-2 row-cols-sm-3 row-cols-lg-4 row-cols-xl-5 dashboard-nav-grid">
                    <?php foreach ($dashboard_nav_links as $link): ?>
                        <div class="col">
                            <a class="dashboard-nav-tile <?php echo $dashboard_nav_current === basename($link['href']) ? 'active' : ''; ?>"
                               href="<?php echo htmlspecialchars($link['href']); ?>">
                                <span class="tile-icon"><i class="fas <?php echo htmlspecialchars($link['icon']); ?>"></i></span>
                                <span class="tile-label"><?php echo htmlspecialchars($link['label']); ?></span>
                                <span class="tile-desc text-muted"><?php echo htmlspecialchars($link['description']); ?></span>
                                <span class="tile-arrow"><i class="fas fa-arrow-right"></i></span>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="pb-5">
        <div class="container">
            <div class="dev-slot2 mb-4">Inline Ad 728x90</div>

            <?php if ($error_message): ?>
                <div class="alert alert-glass alert-danger mb-4" role="alert">
                    <span class="icon text-danger"><i class="fas fa-exclamation-triangle"></i></span>
                    <div><?php echo htmlspecialchars($error_message); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-glass alert-success mb-4" role="alert">
                    <span class="icon text-success"><i class="fas fa-check-circle"></i></span>
                    <div><?php echo htmlspecialchars($success_message); ?></div>
                </div>
            <?php endif; ?>

            <div class="row g-4 align-items-start">
                <div class="col-xl-8">
                    <div class="glass-card p-4 p-lg-5 animate-fade-in" data-aos="fade-up">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
                            <div>
                                <h2 class="h4 text-white mb-1">Profile Preferences</h2>
                                <p class="text-muted mb-0">Update your email, avatar, and security settings to keep your account current.</p>
                            </div>
                            <div class="option-chip">
                                <i class="fas fa-lock"></i>
                                <span>2FA ready</span>
                            </div>
                        </div>

                        <form method="POST" enctype="multipart/form-data" class="row g-3">
                            <div class="col-12">
                                <label class="form-label text-uppercase small text-muted">Current Avatar</label>
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Current avatar" class="account-avatar">
                                    <p class="text-muted small mb-0">Upload a new image to refresh your profile identity. Max 2MB JPG, PNG, or GIF.</p>
                                </div>
                            </div>
                            <div class="col-12">
                                <label for="avatar" class="form-label text-uppercase small text-muted">Upload New Avatar</label>
                                <input type="file" id="avatar" name="avatar" class="form-control" accept="image/*">
                                <small class="text-muted">Images are automatically cropped to 200x200px.</small>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="username" class="form-label text-uppercase small text-muted">Username</label>
                                <input type="text" id="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                <small class="text-muted">Usernames are permanent.</small>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="email" class="form-label text-uppercase small text-muted">Email Address</label>
                                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="current_password" class="form-label text-uppercase small text-muted">Current Password</label>
                                <input type="password" id="current_password" name="current_password" class="form-control" placeholder="Required to set a new password">
                                <small class="text-muted">Leave blank to keep your existing password.</small>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="new_password" class="form-label text-uppercase small text-muted">New Password</label>
                                <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Minimum 6 characters">
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="new_password_confirm" class="form-label text-uppercase small text-muted">Confirm New Password</label>
                                <input type="password" id="new_password_confirm" name="confirm_password" class="form-control" placeholder="Confirm new password">
                            </div>
                            <div class="col-12 col-md-6 d-flex align-items-center">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="email_notifications" name="email_notifications" <?php echo $user['email_notifications'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="email_notifications">Receive email notifications</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-theme btn-gradient w-100">
                                    <i class="fas fa-save me-2"></i>Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="col-xl-4">
                    <div class="d-flex flex-column gap-4">
                        <div class="glass-card p-4 animate-fade-in" data-aos="fade-up" data-aos-delay="100">
                            <h3 class="h5 text-white mb-3">Account Statistics</h3>
                            <div class="d-grid gap-3">
                                <div class="glass-stat-tile">
                                    <span class="glass-stat-label">Reputation Points</span>
                                    <span class="glass-stat-value text-info"><?php echo number_format($user['reputation_points']); ?></span>
                                </div>
                                <div class="glass-stat-tile">
                                    <span class="glass-stat-label">Current Level</span>
                                    <span class="glass-stat-value text-warning"><?php echo $user['active_badge_icon']; ?> <?php echo htmlspecialchars($user['active_badge_name']); ?></span>
                                </div>
                                <div class="glass-stat-tile">
                                    <span class="glass-stat-label">Total Reviews</span>
                                    <span class="glass-stat-value"><?php echo number_format($user['total_reviews']); ?></span>
                                </div>
                                <div class="glass-stat-tile">
                                    <span class="glass-stat-label">Total Upvotes</span>
                                    <span class="glass-stat-value text-success"><?php echo number_format($user['total_upvotes']); ?></span>
                                </div>
                                <div class="glass-stat-tile">
                                    <span class="glass-stat-label">Sites Submitted</span>
                                    <span class="glass-stat-value"><?php echo number_format($user['total_submissions']); ?></span>
                                </div>
                                <div class="glass-stat-tile">
                                    <span class="glass-stat-label">Credits Balance</span>
                                    <span class="glass-stat-value text-success">$<?php echo number_format($user['credits'], 4); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="glass-card p-4 animate-fade-in" data-aos="fade-up" data-aos-delay="150">
                            <h3 class="h5 text-white mb-3">Quick Actions</h3>
                            <div class="d-grid gap-2">
                                <a href="dashboard.php" class="btn btn-theme btn-outline-glass"><i class="fas fa-gauge-high me-2"></i>Dashboard Overview</a>
                                <a href="my-submissions.php" class="btn btn-theme btn-outline-glass"><i class="fas fa-globe me-2"></i>My Submissions</a>
                                <a href="my-ads.php" class="btn btn-theme btn-outline-glass"><i class="fas fa-rectangle-ad me-2"></i>My Campaigns</a>
                                <a href="notifications.php" class="btn btn-theme btn-outline-glass"><i class="fas fa-bell me-2"></i>Notifications</a>
                                <a href="buy-credits.php" class="btn btn-theme btn-gradient"><i class="fas fa-credit-card me-2"></i>Buy Credits</a>
                                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                    <i class="fas fa-trash-can me-2"></i>Delete Account
                                </button>
                            </div>
                        </div>

                        <div class="glass-card p-4 animate-fade-in" data-aos="fade-up" data-aos-delay="200">
                            <h3 class="h5 text-white mb-3">Account Status</h3>
                            <ul class="list-unstyled text-muted small mb-0">
                                <li class="d-flex justify-content-between mb-2"><span>Member Since</span><span><?php echo date('M j, Y', strtotime($user['created_at'])); ?></span></li>
                                <li class="d-flex justify-content-between mb-2"><span>Last Active</span><span><?php echo date('M j, Y g:i A', strtotime($user['last_active'])); ?></span></li>
                                <li class="d-flex justify-content-between">
                                    <span>Account Role</span>
                                    <span>
                                        <?php if ($user['is_banned']): ?>
                                            <span class="text-danger">Banned</span>
                                        <?php elseif ($user['is_admin']): ?>
                                            <span class="text-warning">Administrator</span>
                                        <?php elseif ($user['is_moderator']): ?>
                                            <span class="text-info">Moderator</span>
                                        <?php else: ?>
                                            <span class="text-success">Active Member</span>
                                        <?php endif; ?>
                                    </span>
                                </li>
                            </ul>
                        </div>

                        <div class="dev-slot1">Sidebar Ad 300x600</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Account Deletion Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">
            <div class="modal-header border-0">
                <h5 class="modal-title text-danger" id="deleteAccountModalLabel"><i class="fas fa-exclamation-triangle me-2"></i>Delete Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="alert alert-glass alert-danger mb-4" role="alert">
                        <span class="icon text-danger"><i class="fas fa-bomb"></i></span>
                        <div><strong>This action is permanent.</strong> Your account, reviews, referrals, and site data will be anonymized and removed.</div>
                    </div>
                    <div class="mb-3">
                        <label for="delete_confirm_username" class="form-label">Confirm Username</label>
                        <input type="text" id="delete_confirm_username" name="confirm_username" class="form-control" placeholder="Enter your username" required>
                    </div>
                    <div class="mb-3">
                        <label for="delete_confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" id="delete_confirm_password" name="confirm_password" class="form-control" placeholder="Enter your password" required>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-theme btn-outline-glass" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_account" class="btn btn-danger"><i class="fas fa-trash-can me-2"></i>Delete My Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<style>
.account-avatar-wrapper {
    position: relative;
    display: inline-block;
}

.account-avatar {
    width: 84px;
    height: 84px;
    border-radius: 24px;
    object-fit: cover;
    border: 2px solid rgba(148, 163, 184, 0.3);
}

.status-indicator {
    position: absolute;
    bottom: 6px;
    right: 6px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 2px solid #0b1120;
}

.status-success { background: linear-gradient(135deg, #34d399, #22c55e); }
.status-danger { background: linear-gradient(135deg, #f97316, #ef4444); }

.glass-modal {
    background: rgba(15, 23, 42, 0.92);
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 1.25rem;
    backdrop-filter: blur(12px);
}

.glass-modal .modal-body {
    padding: 1.5rem 1.5rem 0;
}

.glass-modal .modal-footer {
    padding: 0 1.5rem 1.5rem;
}

.btn-close-white {
    filter: invert(1);
}

.text-purple { color: #a855f7 !important; }
.bg-purple { background-color: rgba(168, 85, 247, 1) !important; }
.bg-purple.bg-opacity-10 { background-color: rgba(168, 85, 247, 0.1) !important; }
</style>

<script>
const confirmPasswordInput = document.getElementById('new_password_confirm');
if (confirmPasswordInput) {
    confirmPasswordInput.addEventListener('input', function () {
        const newPassword = document.getElementById('new_password').value;
        if (newPassword && this.value && newPassword !== this.value) {
            this.setCustomValidity('Passwords do not match');
        } else {
            this.setCustomValidity('');
        }
    });
}
</script>
