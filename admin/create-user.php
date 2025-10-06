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

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;
    $is_moderator = isset($_POST['is_moderator']) ? 1 : 0;
    $reputation_points = intval($_POST['reputation_points'] ?? 0);
    $credits = floatval($_POST['credits'] ?? 0);
    
    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        $error_message = 'Username, email, and password are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters long';
    } else {
        // Check if username or email exists
        $check_query = "SELECT id FROM users WHERE username = :username OR email = :email";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':username', $username);
        $check_stmt->bindParam(':email', $email);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            $error_message = 'Username or email already exists';
        } else {
            try {
                $db->beginTransaction();
                
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Generate referral code
                $referral_code = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $username));
                
                // Insert user
                $insert_query = "INSERT INTO users (username, email, password, is_admin, is_moderator, reputation_points, credits, referral_code, created_at) 
                                VALUES (:username, :email, :password, :is_admin, :is_moderator, :reputation_points, :credits, :referral_code, NOW())";
                $insert_stmt = $db->prepare($insert_query);
                $insert_stmt->bindParam(':username', $username);
                $insert_stmt->bindParam(':email', $email);
                $insert_stmt->bindParam(':password', $hashed_password);
                $insert_stmt->bindParam(':is_admin', $is_admin);
                $insert_stmt->bindParam(':is_moderator', $is_moderator);
                $insert_stmt->bindParam(':reputation_points', $reputation_points);
                $insert_stmt->bindParam(':credits', $credits);
                $insert_stmt->bindParam(':referral_code', $referral_code);
                $insert_stmt->execute();
                
                $new_user_id = $db->lastInsertId();
                
                // Create user wallet
                $wallet_query = "INSERT INTO user_wallets (user_id) VALUES (:user_id)";
                $wallet_stmt = $db->prepare($wallet_query);
                $wallet_stmt->bindParam(':user_id', $new_user_id);
                $wallet_stmt->execute();
                
                // Handle avatar upload
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
                                $avatar_filename = 'avatar_' . $new_user_id . '_' . time() . '.' . $file_extension;
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
                                    // Update user avatar
                                    $avatar_update = "UPDATE users SET avatar = :avatar WHERE id = :user_id";
                                    $avatar_stmt = $db->prepare($avatar_update);
                                    $relative_path = 'assets/images/avatars/' . $avatar_filename;
                                    $avatar_stmt->bindParam(':avatar', $relative_path);
                                    $avatar_stmt->bindParam(':user_id', $new_user_id);
                                    $avatar_stmt->execute();
                                }
                                
                                imagedestroy($source);
                                imagedestroy($resized);
                            }
                        }
                    }
                }
                
                $db->commit();
                $success_message = "User '{$username}' created successfully!";
                
                // Clear form
                $_POST = [];
                
            } catch (Exception $e) {
                $db->rollback();
                $error_message = 'Error creating user: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'Create User - Admin Panel';
include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Create New User</h1>
                <a href="users.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Users
                </a>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Username</label>
                                            <input type="text" name="username" class="form-control" 
                                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                                   pattern="[a-zA-Z0-9_]{3,20}" required>
                                            <small class="form-text text-muted">3-20 characters, letters, numbers, underscore only</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Email Address</label>
                                            <input type="email" name="email" class="form-control" 
                                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" name="password" class="form-control" 
                                           placeholder="Enter password (min 6 characters)" required>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Reputation Points</label>
                                            <input type="number" name="reputation_points" class="form-control" 
                                                   value="<?php echo htmlspecialchars($_POST['reputation_points'] ?? '0'); ?>" min="0">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Credits Balance</label>
                                            <input type="number" name="credits" class="form-control" 
                                                   value="<?php echo htmlspecialchars($_POST['credits'] ?? '0'); ?>" 
                                                   step="0.0001" min="0">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Avatar</label>
                                    <input type="file" name="avatar" class="form-control" accept="image/*">
                                    <small class="form-text text-muted">Max 2MB, JPG/PNG/GIF only</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Avatar</label>
                                    <input type="file" name="avatar" class="form-control" accept="image/*">
                                    <small class="form-text text-muted">Max 2MB, JPG/PNG/GIF only</small>
                                </div>
                                
                                <div class="mb-3">
                                    <h6>Permissions</h6>
                                    <div class="form-check">
                                        <input type="checkbox" name="is_admin" class="form-check-input" 
                                               <?php echo isset($_POST['is_admin']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label">Administrator</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" name="is_moderator" class="form-check-input" 
                                               <?php echo isset($_POST['is_moderator']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label">Moderator</label>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Create User</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-body">
                            <h6>User Creation Guidelines</h6>
                            <ul class="text-muted">
                                <li>Username must be unique and 3-20 characters</li>
                                <li>Email must be valid and unique</li>
                                <li>Password minimum 6 characters</li>
                                <li>Admin users have full access</li>
                                <li>Moderators can manage content</li>
                                <li>Reputation points affect user level</li>
                                <li>Credits can be used for promotions</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/admin_footer.php'; ?>
