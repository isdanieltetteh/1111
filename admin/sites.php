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
if ($_SERVER['REQUEST_METHOD'] == 'POST' || isset($_GET['action'])) {
    $action = $_POST['action'] ?? $_GET['action'];
    $site_id = intval($_POST['site_id'] ?? $_GET['id'] ?? 0);
    
    switch ($action) {
        case 'approve':
            $update_query = "UPDATE sites SET is_approved = 1, approved_by = :admin_id WHERE id = :site_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':admin_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $update_stmt->bindParam(':site_id', $site_id, PDO::PARAM_INT);
            if ($update_stmt->execute()) {
                if ($update_stmt->rowCount() > 0) {
                    // Award points to submitter
                    $site_query = "SELECT submitted_by FROM sites WHERE id = :site_id";
                    $site_stmt = $db->prepare($site_query);
                    $site_stmt->bindParam(':site_id', $site_id, PDO::PARAM_INT);
                    $site_stmt->execute();
                    $site = $site_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($site) {
                        require_once '../includes/wallet.php';
                        $wallet_manager = new WalletManager($db);
                        $wallet_manager->addPoints($site['submitted_by'], 25, 'earned', 'Site approved by admin', $site_id, 'submission');
                    }
                    
                    $success_message = 'Site approved successfully!';
                } else {
                    $error_message = 'No changes made or site not found.';
                }
            } else {
                $error_message = 'Error approving site: ' . $update_stmt->errorInfo()[2];
            }
            break;
            
        case 'reject':
            $update_query = "UPDATE sites SET is_approved = 0 WHERE id = :site_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':site_id', $site_id, PDO::PARAM_INT);
            if ($update_stmt->execute()) {
                if ($update_stmt->rowCount() > 0) {
                    $success_message = 'Site rejected.';
                } else {
                    $error_message = 'No changes made or site not found.';
                }
            } else {
                $error_message = 'Error rejecting site: ' . $update_stmt->errorInfo()[2];
            }
            break;
            
        case 'mark_scam':
            $update_query = "UPDATE sites SET status = 'scam', admin_scam_decision = 1 WHERE id = :site_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':site_id', $site_id, PDO::PARAM_INT);
            if ($update_stmt->execute()) {
                if ($update_stmt->rowCount() > 0) {
                    $success_message = 'Site marked as scam.';
                } else {
                    $error_message = 'No changes made or site not found.';
                }
            } else {
                $error_message = 'Error marking site as scam: ' . $update_stmt->errorInfo()[2];
            }
            break;
            
        case 'feature':
            $featured = intval($_POST['featured'] ?? 0);
            $update_query = "UPDATE sites SET is_featured = :featured WHERE id = :site_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':featured', $featured, PDO::PARAM_INT);
            $update_stmt->bindParam(':site_id', $site_id, PDO::PARAM_INT);
            if ($update_stmt->execute()) {
                if ($update_stmt->rowCount() > 0) {
                    $success_message = $featured ? 'Site featured!' : 'Site unfeatured.';
                    // Log the action
                    $log_query = "INSERT INTO admin_action_logs (admin_id, action, target_type, target_id, details, ip_address) 
                                  VALUES (:admin_id, :action, 'site', :target_id, :details, :ip)";
                    $log_stmt = $db->prepare($log_query);
                    $log_action = $featured ? 'featured_site' : 'unfeatured_site';
                    $details = json_encode(['featured' => $featured]);
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $log_stmt->bindParam(':admin_id', $_SESSION['user_id'], PDO::PARAM_INT);
                    $log_stmt->bindParam(':action', $log_action);
                    $log_stmt->bindParam(':target_id', $site_id, PDO::PARAM_INT);
                    $log_stmt->bindParam(':details', $details);
                    $log_stmt->bindParam(':ip', $ip);
                    $log_stmt->execute();
                } else {
                    $error_message = 'No changes made to featured status or site not found.';
                }
            } else {
                $error_message = 'Error updating featured status: ' . $update_stmt->errorInfo()[2];
            }
            break;
            
        case 'sponsored':
            $sponsored = intval($_POST['sponsored'] ?? 0);
            $sponsored_until = $sponsored ? date('Y-m-d H:i:s', strtotime('+30 days')) : null;
            $update_query = "UPDATE sites SET is_sponsored = :sponsored, sponsored_until = :sponsored_until WHERE id = :site_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':sponsored', $sponsored, PDO::PARAM_INT);
            $update_stmt->bindParam(':sponsored_until', $sponsored_until);
            $update_stmt->bindParam(':site_id', $site_id, PDO::PARAM_INT);
            if ($update_stmt->execute()) {
                if ($update_stmt->rowCount() > 0) {
                    $success_message = $sponsored ? 'Site sponsored!' : 'Site unsponsored.';
                    // Log similar to feature
                    $log_query = "INSERT INTO admin_action_logs (admin_id, action, target_type, target_id, details, ip_address) 
                                  VALUES (:admin_id, :action, 'site', :target_id, :details, :ip)";
                    $log_stmt = $db->prepare($log_query);
                    $log_action = $sponsored ? 'sponsored_site' : 'unsponsored_site';
                    $details = json_encode(['sponsored' => $sponsored, 'until' => $sponsored_until]);
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $log_stmt->bindParam(':admin_id', $_SESSION['user_id'], PDO::PARAM_INT);
                    $log_stmt->bindParam(':action', $log_action);
                    $log_stmt->bindParam(':target_id', $site_id, PDO::PARAM_INT);
                    $log_stmt->bindParam(':details', $details);
                    $log_stmt->bindParam(':ip', $ip);
                    $log_stmt->execute();
                } else {
                    $error_message = 'No changes made to sponsored status or site not found.';
                }
            } else {
                $error_message = 'Error updating sponsored status: ' . $update_stmt->errorInfo()[2];
            }
            break;
            
        case 'boosted':
            $boosted = intval($_POST['boosted'] ?? 0);
            $boosted_until = $boosted ? date('Y-m-d H:i:s', strtotime('+30 days')) : null;
            $update_query = "UPDATE sites SET is_boosted = :boosted, boosted_until = :boosted_until WHERE id = :site_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':boosted', $boosted, PDO::PARAM_INT);
            $update_stmt->bindParam(':boosted_until', $boosted_until);
            $update_stmt->bindParam(':site_id', $site_id, PDO::PARAM_INT);
            if ($update_stmt->execute()) {
                if ($update_stmt->rowCount() > 0) {
                    $success_message = $boosted ? 'Site boosted!' : 'Site unboosted.';
                    // Log similar
                    $log_query = "INSERT INTO admin_action_logs (admin_id, action, target_type, target_id, details, ip_address) 
                                  VALUES (:admin_id, :action, 'site', :target_id, :details, :ip)";
                    $log_stmt = $db->prepare($log_query);
                    $log_action = $boosted ? 'boosted_site' : 'unboosted_site';
                    $details = json_encode(['boosted' => $boosted, 'until' => $boosted_until]);
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $log_stmt->bindParam(':admin_id', $_SESSION['user_id'], PDO::PARAM_INT);
                    $log_stmt->bindParam(':action', $log_action);
                    $log_stmt->bindParam(':target_id', $site_id, PDO::PARAM_INT);
                    $log_stmt->bindParam(':details', $details);
                    $log_stmt->bindParam(':ip', $ip);
                    $log_stmt->execute();
                } else {
                    $error_message = 'No changes made to boosted status or site not found.';
                }
            } else {
                $error_message = 'Error updating boosted status: ' . $update_stmt->errorInfo()[2];
            }
            break;
            
        case 'delete':
            $delete_query = "DELETE FROM sites WHERE id = :site_id";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->bindParam(':site_id', $site_id, PDO::PARAM_INT);
            if ($delete_stmt->execute()) {
                if ($delete_stmt->rowCount() > 0) {
                    $success_message = 'Site deleted successfully.';
                } else {
                    $error_message = 'Site not found.';
                }
            } else {
                $error_message = 'Error deleting site: ' . $delete_stmt->errorInfo()[2];
            }
            break;
            
        case 'update_site':
            $name = trim($_POST['name']);
            $url = trim($_POST['url']);
            $category = $_POST['category'];
            $description = trim($_POST['description']);
            $supported_coins = trim($_POST['supported_coins']);
            $backlink_url = trim($_POST['backlink_url']);
            $referral_link = trim($_POST['referral_link']);
            $status = $_POST['status'];
            
            // Handle logo upload for editing
            $logo_update = '';
            $logo_path = '';
            
            // Check for auto-fetched logo first
            if (isset($_POST['auto_fetched_logo']) && !empty($_POST['auto_fetched_logo'])) {
                // Auto-fetched logo from temp directory
                $temp_logo_path = trim($_POST['auto_fetched_logo']);
                $temp_full_path = __DIR__ . '/../' . $temp_logo_path;
                
                if (file_exists($temp_full_path)) {
                    // Move from temp to permanent location
                    $upload_dir = '../assets/images/logos/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_extension = pathinfo($temp_logo_path, PATHINFO_EXTENSION);
                    $logo_filename = 'logo_' . time() . '_' . uniqid() . '.' . $file_extension;
                    $logo_path = 'assets/images/logos/' . $logo_filename;
                    
                    if (rename($temp_full_path, $upload_dir . $logo_filename)) {
                        // Successfully moved auto-fetched logo
                        $logo_update = ', logo = :logo';
                    } else {
                        // Fallback: copy if rename fails
                        if (copy($temp_full_path, $upload_dir . $logo_filename)) {
                            unlink($temp_full_path); // Clean up temp file
                            $logo_path = 'assets/images/logos/' . $logo_filename;
                            $logo_update = ', logo = :logo';
                        }
                    }
                }
            }
            // Handle manual file upload if no auto-fetched logo
            elseif (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 2 * 1024 * 1024; // 2MB
                
                if (in_array($_FILES['logo']['type'], $allowed_types) && $_FILES['logo']['size'] <= $max_size) {
                    // Get current logo to delete later
                    $current_logo_query = "SELECT logo FROM sites WHERE id = :site_id";
                    $current_logo_stmt = $db->prepare($current_logo_query);
                    $current_logo_stmt->bindParam(':site_id', $site_id);
                    $current_logo_stmt->execute();
                    $current_site = $current_logo_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $upload_dir = '../assets/images/logos/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    // Resize logo
                    $image_info = getimagesize($_FILES['logo']['tmp_name']);
                    if ($image_info !== false) {
                        list($width, $height) = $image_info;
                        $target_size = 200;
                        
                        $source = null;
                        switch ($_FILES['logo']['type']) {
                            case 'image/jpeg':
                                $source = imagecreatefromjpeg($_FILES['logo']['tmp_name']);
                                break;
                            case 'image/png':
                                $source = imagecreatefrompng($_FILES['logo']['tmp_name']);
                                break;
                            case 'image/gif':
                                $source = imagecreatefromgif($_FILES['logo']['tmp_name']);
                                break;
                        }
                        
                        if ($source) {
                            $resized = imagecreatetruecolor($target_size, $target_size);
                            
                            // Preserve transparency
                            if ($_FILES['logo']['type'] === 'image/png' || $_FILES['logo']['type'] === 'image/gif') {
                                imagealphablending($resized, false);
                                imagesavealpha($resized, true);
                                $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
                                imagefill($resized, 0, 0, $transparent);
                            }
                            
                            imagecopyresampled($resized, $source, 0, 0, 0, 0, $target_size, $target_size, $width, $height);
                            
                            $file_extension = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                            $logo_filename = 'logo_' . time() . '_' . uniqid() . '.' . $file_extension;
                            $logo_path = 'assets/images/logos/' . $logo_filename;
                            
                            $save_success = false;
                            switch ($_FILES['logo']['type']) {
                                case 'image/jpeg':
                                    $save_success = imagejpeg($resized, $upload_dir . $logo_filename, 90);
                                    break;
                                case 'image/png':
                                    $save_success = imagepng($resized, $upload_dir . $logo_filename, 9);
                                    break;
                                case 'image/gif':
                                    $save_success = imagegif($resized, $upload_dir . $logo_filename);
                                    break;
                            }
                            
                            if ($save_success) {
                                $logo_update = ', logo = :logo';
                                
                                // Delete old logo if it exists and is not default
                                if ($current_site && $current_site['logo'] && 
                                    $current_site['logo'] !== 'assets/images/default-logo.png' && 
                                    file_exists('../' . $current_site['logo'])) {
                                    unlink('../' . $current_site['logo']);
                                }
                            }
                            
                            imagedestroy($source);
                            imagedestroy($resized);
                        }
                    }
                }
            }
            
            $update_query = "UPDATE sites SET 
                            name = :name, url = :url, category = :category, 
                            description = :description, supported_coins = :supported_coins,
                            backlink_url = :backlink_url, referral_link = :referral_link,
                            status = :status, updated_at = NOW() {$logo_update}
                            WHERE id = :site_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':name', $name);
            $update_stmt->bindParam(':url', $url);
            $update_stmt->bindParam(':category', $category);
            $update_stmt->bindParam(':description', $description);
            $update_stmt->bindParam(':supported_coins', $supported_coins);
            $update_stmt->bindParam(':backlink_url', $backlink_url);
            $update_stmt->bindParam(':referral_link', $referral_link);
            $update_stmt->bindParam(':status', $status);
            $update_stmt->bindParam(':site_id', $site_id);
            
            if ($logo_update) {
                $update_stmt->bindParam(':logo', $logo_path);
            }
            
            if ($update_stmt->execute()) {
                if ($update_stmt->rowCount() > 0) {
                    $success_message = 'Site updated successfully!';
                } else {
                    $error_message = 'No changes made or site not found.';
                }
            } else {
                $error_message = 'Error updating site: ' . $update_stmt->errorInfo()[2];
            }
            break;
            
        case 'add_site':
            $name = trim($_POST['name']);
            $url = trim($_POST['url']);
            $category = $_POST['category'];
            $description = trim($_POST['description']);
            $supported_coins = trim($_POST['supported_coins']);
            $backlink_url = trim($_POST['backlink_url']);
            $referral_link = trim($_POST['referral_link']);
            $status = $_POST['status'];
            
            // Handle logo upload
            $logo_path = '';
            
            // Check for auto-fetched logo first
            if (isset($_POST['auto_fetched_logo']) && !empty($_POST['auto_fetched_logo'])) {
                // Auto-fetched logo from temp directory
                $temp_logo_path = trim($_POST['auto_fetched_logo']);
                $temp_full_path = __DIR__ . '/../' . $temp_logo_path;
                
                if (file_exists($temp_full_path)) {
                    // Move from temp to permanent location
                    $upload_dir = '../assets/images/logos/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_extension = pathinfo($temp_logo_path, PATHINFO_EXTENSION);
                    $logo_filename = 'logo_' . time() . '_' . uniqid() . '.' . $file_extension;
                    $logo_path = 'assets/images/logos/' . $logo_filename;
                    
                    if (rename($temp_full_path, $upload_dir . $logo_filename)) {
                        // Successfully moved auto-fetched logo
                    } else {
                        // Fallback: copy if rename fails
                        if (copy($temp_full_path, $upload_dir . $logo_filename)) {
                            unlink($temp_full_path); // Clean up temp file
                        }
                    }
                }
            }
            // Handle manual file upload if no auto-fetched logo
            elseif (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 2 * 1024 * 1024; // 2MB
                
                if (in_array($_FILES['logo']['type'], $allowed_types) && $_FILES['logo']['size'] <= $max_size) {
                    $upload_dir = '../assets/images/logos/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    // Resize logo to standard size
                    $image_info = getimagesize($_FILES['logo']['tmp_name']);
                    if ($image_info !== false) {
                        list($width, $height) = $image_info;
                        $target_size = 200;
                        
                        $source = null;
                        switch ($_FILES['logo']['type']) {
                            case 'image/jpeg':
                                $source = imagecreatefromjpeg($_FILES['logo']['tmp_name']);
                                break;
                            case 'image/png':
                                $source = imagecreatefrompng($_FILES['logo']['tmp_name']);
                                break;
                            case 'image/gif':
                                $source = imagecreatefromgif($_FILES['logo']['tmp_name']);
                                break;
                        }
                        
                        if ($source) {
                            $resized = imagecreatetruecolor($target_size, $target_size);
                            
                            // Preserve transparency
                            if ($_FILES['logo']['type'] === 'image/png' || $_FILES['logo']['type'] === 'image/gif') {
                                imagealphablending($resized, false);
                                imagesavealpha($resized, true);
                                $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
                                imagefill($resized, 0, 0, $transparent);
                            }
                            
                            imagecopyresampled($resized, $source, 0, 0, 0, 0, $target_size, $target_size, $width, $height);
                            
                            $file_extension = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                            $logo_filename = 'logo_' . time() . '_' . uniqid() . '.' . $file_extension;
                            $logo_path = 'assets/images/logos/' . $logo_filename;
                            
                            $save_success = false;
                            switch ($_FILES['logo']['type']) {
                                case 'image/jpeg':
                                    $save_success = imagejpeg($resized, $upload_dir . $logo_filename, 90);
                                    break;
                                case 'image/png':
                                    $save_success = imagepng($resized, $upload_dir . $logo_filename, 9);
                                    break;
                                case 'image/gif':
                                    $save_success = imagegif($resized, $upload_dir . $logo_filename);
                                    break;
                            }
                            
                            imagedestroy($source);
                            imagedestroy($resized);
                        }
                    }
                }
            }
            
            $insert_query = "INSERT INTO sites (name, url, category, description, supported_coins, logo, backlink_url, referral_link, status, submitted_by, is_approved) 
                            VALUES (:name, :url, :category, :description, :supported_coins, :logo, :backlink_url, :referral_link, :status, :admin_id, 1)";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':name', $name);
            $insert_stmt->bindParam(':url', $url);
            $insert_stmt->bindParam(':category', $category);
            $insert_stmt->bindParam(':description', $description);
            $insert_stmt->bindParam(':supported_coins', $supported_coins);
            $insert_stmt->bindParam(':logo', $logo_path);
            $insert_stmt->bindParam(':backlink_url', $backlink_url);
            $insert_stmt->bindParam(':referral_link', $referral_link);
            $insert_stmt->bindParam(':status', $status);
            $insert_stmt->bindParam(':admin_id', $_SESSION['user_id']);
            
            if ($insert_stmt->execute()) {
                $success_message = 'Site added successfully!';
            } else {
                $error_message = 'Error adding site: ' . $insert_stmt->errorInfo()[2];
            }
            break;
    }
}

// Get filters
 $status_filter = $_GET['status'] ?? 'all';
 $category_filter = $_GET['category'] ?? 'all';
 $approval_filter = $_GET['approval'] ?? 'all';
 $page = max(1, intval($_GET['page'] ?? 1));
 $per_page = 20;
 $offset = ($page - 1) * $per_page;

// Build WHERE clause
 $where_conditions = ['1=1'];
 $params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "s.status = :status";
    $params[':status'] = $status_filter;
}

if ($category_filter !== 'all') {
    $where_conditions[] = "s.category = :category";
    $params[':category'] = $category_filter;
}

if ($approval_filter !== 'all') {
    $where_conditions[] = "s.is_approved = :approval";
    $params[':approval'] = $approval_filter === 'approved' ? 1 : 0;
}

 $where_clause = implode(' AND ', $where_conditions);

// Get total count
 $count_query = "SELECT COUNT(*) as total FROM sites s WHERE {$where_clause}";
 $count_stmt = $db->prepare($count_query);
 $count_stmt->execute($params);
 $total_sites = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
 $total_pages = ceil($total_sites / $per_page);

// Get sites
 $sites_query = "SELECT s.*, u.username as submitted_by_username, a.username as approved_by_username,
                COALESCE(AVG(r.rating), 0) as average_rating, COUNT(r.id) as review_count
                FROM sites s 
                LEFT JOIN users u ON s.submitted_by = u.id
                LEFT JOIN users a ON s.approved_by = a.id
                LEFT JOIN reviews r ON s.id = r.site_id
                WHERE {$where_clause}
                GROUP BY s.id
                ORDER BY s.created_at DESC
                LIMIT {$per_page} OFFSET {$offset}";

 $sites_stmt = $db->prepare($sites_query);
 $sites_stmt->execute($params);
 $sites = $sites_stmt->fetchAll(PDO::FETCH_ASSOC);

 $page_title = 'Sites Management - Admin Panel';
include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Sites Management</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSiteModal">
                    <i class="fas fa-plus"></i> Add New Site
                </button>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="paying" <?php echo $status_filter === 'paying' ? 'selected' : ''; ?>>Paying</option>
                                <option value="scam_reported" <?php echo $status_filter === 'scam_reported' ? 'selected' : ''; ?>>Scam Reported</option>
                                <option value="scam" <?php echo $status_filter === 'scam' ? 'selected' : ''; ?>>Scam</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select">
                                <option value="all" <?php echo $category_filter === 'all' ? 'selected' : ''; ?>>All Categories</option>
                                <option value="faucet" <?php echo $category_filter === 'faucet' ? 'selected' : ''; ?>>Faucets</option>
                                <option value="url_shortener" <?php echo $category_filter === 'url_shortener' ? 'selected' : ''; ?>>URL Shorteners</option>
                                <option value="mining" <?php echo $category_filter === 'mining' ? 'selected' : ''; ?>>Mining</option>
                                <option value="staking" <?php echo $category_filter === 'staking' ? 'selected' : ''; ?>>Staking</option>
                                <option value="trading" <?php echo $category_filter === 'trading' ? 'selected' : ''; ?>>Trading</option>
                                <option value="games" <?php echo $category_filter === 'games' ? 'selected' : ''; ?>>Games</option>
                                <option value="surveys" <?php echo $category_filter === 'surveys' ? 'selected' : ''; ?>>Surveys</option>
                                <option value="other" <?php echo $category_filter === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Approval</label>
                            <select name="approval" class="form-select">
                                <option value="all" <?php echo $approval_filter === 'all' ? 'selected' : ''; ?>>All</option>
                                <option value="approved" <?php echo $approval_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="pending" <?php echo $approval_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary d-block">Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sites Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Site</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Rating</th>
                                    <th>Reviews</th>
                                    <th>Submitted By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sites as $site): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="../<?php echo htmlspecialchars($site['logo'] ?: 'assets/images/default-logo.png'); ?>" 
                                                 class="rounded me-2" width="32" height="32">
                                            <div>
                                                <strong><?php echo htmlspecialchars($site['name']); ?></strong>
                                                <?php if ($site['is_featured']): ?>
                                                    <span class="badge bg-warning ms-1">Featured</span>
                                                <?php endif; ?>
                                                <?php if ($site['is_sponsored']): ?>
                                                    <span class="badge bg-primary ms-1">Sponsored</span>
                                                <?php endif; ?>
                                                <?php if ($site['is_boosted']): ?>
                                                    <span class="badge bg-info ms-1">Boosted</span>
                                                <?php endif; ?>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($site['url']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo ucfirst(str_replace('_', ' ', $site['category'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $status_badges = [
                                            'paying' => '<span class="badge bg-success">✅ Paying</span>',
                                            'scam_reported' => '<span class="badge bg-warning">⚠ Scam Reported</span>',
                                            'scam' => '<span class="badge bg-danger">❌ Scam</span>'
                                        ];
                                        echo $status_badges[$site['status']] ?? '';
                                        ?>
                                        <?php if (!$site['is_approved']): ?>
                                            <br><span class="badge bg-secondary mt-1">Pending Approval</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo number_format($site['average_rating'], 1); ?>/5</strong>
                                    </td>
                                    <td><?php echo $site['review_count']; ?></td>
                                    <td><?php echo htmlspecialchars($site['submitted_by_username'] ?: 'Unknown'); ?></td>
                                    <td>
                                        <div class="btn-group-vertical btn-group-sm">
                                            <button class="btn btn-info btn-sm" 
                                                    onclick="viewSiteDetails(<?php echo htmlspecialchars(json_encode($site)); ?>)">
                                                View Details
                                            </button>
                                            
                                            <button class="btn btn-warning btn-sm" 
                                                    onclick="editSiteDetails(<?php echo htmlspecialchars(json_encode($site)); ?>)">
                                                Edit Site
                                            </button>
                                            
                                            <?php if (!$site['is_approved']): ?>
                                                <a href="?action=approve&id=<?php echo $site['id']; ?>" 
                                                   class="btn btn-success btn-sm">Approve</a>
                                                <a href="?action=reject&id=<?php echo $site['id']; ?>" 
                                                   class="btn btn-secondary btn-sm">Reject</a>
                                            <?php endif; ?>
                                            
                                            <?php if ($site['status'] !== 'scam'): ?>
                                                <a href="?action=mark_scam&id=<?php echo $site['id']; ?>" 
                                                   class="btn btn-danger btn-sm">Mark Scam</a>
                                            <?php endif; ?>
                                            
                                          
                                            
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="sponsored">
                                                <input type="hidden" name="site_id" value="<?php echo $site['id']; ?>">
                                                <input type="hidden" name="sponsored" value="<?php echo $site['is_sponsored'] ? 0 : 1; ?>">
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    <?php echo $site['is_sponsored'] ? 'Unsponsor' : 'Sponsor'; ?>
                                                </button>
                                            </form>

                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="boosted">
                                                <input type="hidden" name="site_id" value="<?php echo $site['id']; ?>">
                                                <input type="hidden" name="boosted" value="<?php echo $site['is_boosted'] ? 0 : 1; ?>">
                                                <button type="submit" class="btn btn-info btn-sm">
                                                    <?php echo $site['is_boosted'] ? 'Unboost' : 'Boost'; ?>
                                                </button>
                                            </form>
                                            
                                            <a href="../review.php?id=<?php echo $site['id']; ?>" 
                                               class="btn btn-info btn-sm" target="_blank">View</a>
                                            
                                            <a href="?action=delete&id=<?php echo $site['id']; ?>" 
                                               class="btn btn-danger btn-sm">Delete</a>
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
    </div>
</div>

<!-- Site Details Modal -->
<div class="modal fade" id="siteDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Site Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="siteDetailsContent">
                <!-- Content loaded by JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="editSiteDetails()">Edit Site</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Site Modal -->
<div class="modal fade" id="addSiteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Site</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="addSiteForm">
                <input type="hidden" name="action" value="add_site">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3 position-relative">
                                <label class="form-label">Site URL</label>
                                <input type="url" name="url" id="addSiteUrl" class="form-control" required>
                                <button type="button" id="autoFetchAddBtn" class="btn btn-sm btn-success position-absolute end-0 top-0 mt-4 me-2" style="display: none;">
                                    <i class="fas fa-magic"></i> Auto Fill
                                </button>
                                <div id="addUrlValidation" class="validation-status mt-2"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Site Name</label>
                                <input type="text" name="name" id="addSiteName" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select" required>
                                    <option value="faucet">Crypto Faucet</option>
                                    <option value="url_shortener">URL Shortener</option>
                                    <option value="mining">Mining Pool</option>
                                    <option value="staking">Staking Platform</option>
                                    <option value="trading">Trading Platform</option>
                                    <option value="games">Crypto Games</option>
                                    <option value="surveys">Paid Surveys</option>
                                    <option value="defi">DeFi Platform</option>
                                    <option value="nft">NFT Platform</option>
                                    <option value="exchange">Crypto Exchange</option>
                                    <option value="wallet">Crypto Wallet</option>
                                    <option value="lending">Crypto Lending</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select" required>
                                    <option value="paying">Paying</option>
                                    <option value="scam_reported">Scam Reported</option>
                                    <option value="scam">Scam</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="addSiteDescription" class="form-control" rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Supported Coins</label>
                        <input type="text" name="supported_coins" class="form-control" placeholder="BTC, ETH, LTC, etc.">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Site Logo</label>
                        <input type="file" name="logo" class="form-control" accept="image/*">
                        <img id="addLogoPreview" class="img-thumbnail mt-2" style="max-width: 150px; display: none;">
                        <input type="hidden" name="auto_fetched_logo" id="addAutoFetchedLogo">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Backlink URL</label>
                        <input type="url" name="backlink_url" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Referral Link</label>
                        <input type="url" name="referral_link" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Site</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Site Modal -->
<div class="modal fade" id="editSiteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Site Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editSiteForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_site">
                <input type="hidden" name="site_id" id="editSiteId">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3 position-relative">
                                <label class="form-label">Site URL</label>
                                <input type="url" name="url" id="editSiteUrl" class="form-control" required>
                                <button type="button" id="autoFetchEditBtn" class="btn btn-sm btn-success position-absolute end-0 top-0 mt-4 me-2" style="display: none;">
                                    <i class="fas fa-magic"></i> Auto Fill
                                </button>
                                <div id="editUrlValidation" class="validation-status mt-2"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Site Name</label>
                                <input type="text" name="name" id="editSiteName" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select name="category" id="editSiteCategory" class="form-select" required>
                                    <option value="faucet">Crypto Faucet</option>
                                    <option value="url_shortener">URL Shortener</option>
                                    <option value="mining">Mining Pool</option>
                                    <option value="staking">Staking Platform</option>
                                    <option value="trading">Trading Platform</option>
                                    <option value="games">Crypto Games</option>
                                    <option value="surveys">Paid Surveys</option>
                                    <option value="defi">DeFi Platform</option>
                                    <option value="nft">NFT Platform</option>
                                    <option value="exchange">Crypto Exchange</option>
                                    <option value="wallet">Crypto Wallet</option>
                                    <option value="lending">Crypto Lending</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" id="editSiteStatus" class="form-select" required>
                                    <option value="paying">Paying</option>
                                    <option value="scam_reported">Scam Reported</option>
                                    <option value="scam">Scam</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="editSiteDescription" class="form-control" rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Supported Coins</label>
                        <input type="text" name="supported_coins" id="editSiteCoins" class="form-control" placeholder="BTC, ETH, LTC, etc.">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Site Logo</label>
                        <input type="file" name="logo" class="form-control" accept="image/*">
                        <img id="editLogoPreview" class="img-thumbnail mt-2" style="max-width: 150px; display: none;">
                        <input type="hidden" name="auto_fetched_logo" id="editAutoFetchedLogo">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Backlink URL</label>
                        <input type="url" name="backlink_url" id="editSiteBacklink" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Referral Link</label>
                        <input type="url" name="referral_link" id="editSiteReferral" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Site</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .validation-status { 
        padding: 8px 12px; 
        border-radius: 4px; 
        font-size: 0.875rem; 
        display: none; 
    }
    .validation-success { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
    .validation-error { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
    .validation-loading { background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.2); }
</style>

<script>
// Store current site data for editing
let currentSiteData = null;

function viewSiteDetails(site) {
    currentSiteData = site;
    const content = document.getElementById('siteDetailsContent');
    
    const statusBadges = {
        'paying': '<span class="badge bg-success">✅ Paying</span>',
        'scam_reported': '<span class="badge bg-warning">⚠ Scam Reported</span>',
        'scam': '<span class="badge bg-danger">❌ Scam</span>'
    };
    
    const categoryNames = {
        'faucet': 'Crypto Faucet',
        'url_shortener': 'URL Shortener',
        'mining': 'Mining Pool',
        'staking': 'Staking Platform',
        'trading': 'Trading Platform',
        'games': 'Crypto Games',
        'surveys': 'Paid Surveys',
        'other': 'Other'
    };
    
    content.innerHTML = `
        <div class="row">
            <div class="col-md-4 text-center">
                <img src="../${site.logo || 'assets/images/default-logo.png'}" 
                     class="img-fluid rounded mb-3" style="max-width: 150px;">
                <h4>${site.name}</h4>
                ${statusBadges[site.status] || ''}
                ${site.is_featured ? '<br><span class="badge bg-warning mt-1">Featured</span>' : ''}
            </div>
            <div class="col-md-8">
                <table class="table table-sm">
                    <tr><td><strong>URL:</strong></td><td><a href="${site.url}" target="_blank">${site.url}</a></td></tr>
                    <tr><td><strong>Category:</strong></td><td>${categoryNames[site.category] || site.category}</td></tr>
                    <tr><td><strong>Description:</strong></td><td>${site.description}</td></tr>
                    <tr><td><strong>Supported Coins:</strong></td><td>${site.supported_coins || 'Not specified'}</td></tr>
                    <tr><td><strong>Backlink URL:</strong></td><td>${site.backlink_url ? '<a href="' + site.backlink_url + '" target="_blank">' + site.backlink_url + '</a>' : 'Not provided'}</td></tr>
                    <tr><td><strong>Referral Link:</strong></td><td>${site.referral_link ? '<a href="' + site.referral_link + '" target="_blank">' + site.referral_link + '</a>' : 'Not provided'}</td></tr>
                    <tr><td><strong>Submitted By:</strong></td><td>${site.submitted_by_username || 'Unknown'}</td></tr>
                    <tr><td><strong>Submitted:</strong></td><td>${new Date(site.created_at).toLocaleDateString()}</td></tr>
                    <tr><td><strong>Rating:</strong></td><td>${parseFloat(site.average_rating).toFixed(1)}/5 (${site.review_count} reviews)</td></tr>
                    <tr><td><strong>Votes:</strong></td><td>👍 ${site.total_upvotes} | 👎 ${site.total_downvotes}</td></tr>
                    <tr><td><strong>Views:</strong></td><td>${parseInt(site.views).toLocaleString()}</td></tr>
                    <tr><td><strong>Clicks:</strong></td><td>${parseInt(site.clicks).toLocaleString()}</td></tr>
                </table>
            </div>
        </div>
    `;
    
    const modal = new bootstrap.Modal(document.getElementById('siteDetailsModal'));
    modal.show();
}

function editSiteDetails(site) {
    if (site) {
        currentSiteData = site;
    } else if (currentSiteData) {
        site = currentSiteData;
    } else {
        return;
    }
    
    document.getElementById('editSiteId').value = site.id;
    document.getElementById('editSiteName').value = site.name;
    document.getElementById('editSiteUrl').value = site.url;
    document.getElementById('editSiteCategory').value = site.category;
    document.getElementById('editSiteDescription').value = site.description;
    document.getElementById('editSiteCoins').value = site.supported_coins || '';
    document.getElementById('editSiteBacklink').value = site.backlink_url || '';
    document.getElementById('editSiteReferral').value = site.referral_link || '';
    document.getElementById('editSiteStatus').value = site.status;
    
    // Show auto-fetch button if URL is valid
    const editUrlInput = document.getElementById('editSiteUrl');
    const autoFetchEditBtn = document.getElementById('autoFetchEditBtn');
    if (editUrlInput.value && editUrlInput.value.startsWith('http')) {
        autoFetchEditBtn.style.display = 'inline-block';
    } else {
        autoFetchEditBtn.style.display = 'none';
    }
    
    // Show existing logo if available
    const editLogoPreview = document.getElementById('editLogoPreview');
    if (site.logo) {
        editLogoPreview.src = '../' + site.logo;
        editLogoPreview.style.display = 'block';
    } else {
        editLogoPreview.style.display = 'none';
    }
    
    const modal = new bootstrap.Modal(document.getElementById('editSiteModal'));
    modal.show();
}

// Auto-fetch functionality for Add Site Modal
document.addEventListener('DOMContentLoaded', function() {
    // Add Site Modal Auto-Fetch
    const addSiteUrl = document.getElementById('addSiteUrl');
    const autoFetchAddBtn = document.getElementById('autoFetchAddBtn');
    const addUrlValidation = document.getElementById('addUrlValidation');
    const addSiteName = document.getElementById('addSiteName');
    const addSiteDescription = document.getElementById('addSiteDescription');
    const addLogoPreview = document.getElementById('addLogoPreview');
    const addAutoFetchedLogo = document.getElementById('addAutoFetchedLogo');
    
    // Edit Site Modal Auto-Fetch
    const editSiteUrl = document.getElementById('editSiteUrl');
    const autoFetchEditBtn = document.getElementById('autoFetchEditBtn');
    const editUrlValidation = document.getElementById('editUrlValidation');
    const editSiteName = document.getElementById('editSiteName');
    const editSiteDescription = document.getElementById('editSiteDescription');
    const editLogoPreview = document.getElementById('editLogoPreview');
    const editAutoFetchedLogo = document.getElementById('editAutoFetchedLogo');
    
    // Show auto-fetch button when URL is entered
    addSiteUrl.addEventListener('input', function() {
        const url = this.value.trim();
        if (url && url.startsWith('http')) {
            autoFetchAddBtn.style.display = 'inline-block';
        } else {
            autoFetchAddBtn.style.display = 'none';
            addUrlValidation.style.display = 'none';
        }
    });
    
    editSiteUrl.addEventListener('input', function() {
        const url = this.value.trim();
        if (url && url.startsWith('http')) {
            autoFetchEditBtn.style.display = 'inline-block';
        } else {
            autoFetchEditBtn.style.display = 'none';
            editUrlValidation.style.display = 'none';
        }
    });
    
    // Auto-fetch for Add Site
    autoFetchAddBtn.addEventListener('click', async function() {
        const url = addSiteUrl.value.trim();
        if (!url) return;
        
        autoFetchAddBtn.disabled = true;
        autoFetchAddBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Fetching...';
        
        try {
            showValidation(addUrlValidation, 'loading', 'Fetching metadata...');
            
            const response = await fetch(`../api/fetch_metadata.php?url=${encodeURIComponent(url)}`);
            const data = await response.json();
            
            if (data.success) {
                if (data.title && !addSiteName.value.trim()) {
                    addSiteName.value = data.title;
                }
                if (data.description && !addSiteDescription.value.trim()) {
                    addSiteDescription.value = data.description;
                }
                if (data.logo_url) {
                    // Download and set logo
                    const logoResponse = await fetch(`../api/download_logo.php?url=${encodeURIComponent(data.logo_url)}&site_url=${encodeURIComponent(url)}`);
                    const logoData = await logoResponse.json();
                    if (logoData.success) {
                        addLogoPreview.src = logoData.local_path;
                        addLogoPreview.style.display = 'block';
                        addAutoFetchedLogo.value = logoData.local_path;
                    }
                }
                showValidation(addUrlValidation, 'success', 'Metadata fetched successfully!');
            } else {
                showValidation(addUrlValidation, 'error', data.error || 'Could not fetch metadata');
            }
        } catch (error) {
            showValidation(addUrlValidation, 'error', 'Error fetching metadata');
        } finally {
            autoFetchAddBtn.disabled = false;
            autoFetchAddBtn.innerHTML = '<i class="fas fa-magic"></i> Auto Fill';
        }
    });
    
    // Auto-fetch for Edit Site
    autoFetchEditBtn.addEventListener('click', async function() {
        const url = editSiteUrl.value.trim();
        if (!url) return;
        
        autoFetchEditBtn.disabled = true;
        autoFetchEditBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Fetching...';
        
        try {
            showValidation(editUrlValidation, 'loading', 'Fetching metadata...');
            
            const response = await fetch(`../api/fetch_metadata.php?url=${encodeURIComponent(url)}`);
            const data = await response.json();
            
            if (data.success) {
                if (data.title && !editSiteName.value.trim()) {
                    editSiteName.value = data.title;
                }
                if (data.description && !editSiteDescription.value.trim()) {
                    editSiteDescription.value = data.description;
                }
                if (data.logo_url) {
                    // Download and set logo
                    const logoResponse = await fetch(`../api/download_logo.php?url=${encodeURIComponent(data.logo_url)}&site_url=${encodeURIComponent(url)}`);
                    const logoData = await logoResponse.json();
                    if (logoData.success) {
                        editLogoPreview.src = logoData.local_path;
                        editLogoPreview.style.display = 'block';
                        editAutoFetchedLogo.value = logoData.local_path;
                    }
                }
                showValidation(editUrlValidation, 'success', 'Metadata fetched successfully!');
            } else {
                showValidation(editUrlValidation, 'error', data.error || 'Could not fetch metadata');
            }
        } catch (error) {
            showValidation(editUrlValidation, 'error', 'Error fetching metadata');
        } finally {
            autoFetchEditBtn.disabled = false;
            autoFetchEditBtn.innerHTML = '<i class="fas fa-magic"></i> Auto Fill';
        }
    });
    
    // Handle logo upload for Add Site
    document.querySelector('#addSiteForm input[name="logo"]').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                addLogoPreview.src = e.target.result;
                addLogoPreview.style.display = 'block';
            };
            reader.readAsDataURL(file);
            addAutoFetchedLogo.value = ''; // Clear auto-fetched logo
        }
    });
    
    // Handle logo upload for Edit Site
    document.querySelector('#editSiteForm input[name="logo"]').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                editLogoPreview.src = e.target.result;
                editLogoPreview.style.display = 'block';
            };
            reader.readAsDataURL(file);
            editAutoFetchedLogo.value = ''; // Clear auto-fetched logo
        }
    });
    
    function showValidation(element, type, message) {
        element.className = `validation-status validation-${type}`;
        element.textContent = message;
        element.style.display = 'block';
    }
});
</script>
<?php include 'includes/admin_footer.php'; ?>
