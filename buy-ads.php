<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

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

$success_message = '';
$error_message = '';

// Handle ad purchase
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'purchase') {
    $ad_type = $_POST['ad_type'];
    $duration_days = intval($_POST['duration_days']);
    $visibility_level = $_POST['visibility_level'];
    $title = trim($_POST['title']);
    $target_url = trim($_POST['target_url']);
    $ad_space_id = isset($_POST['ad_space_id']) && !empty($_POST['ad_space_id']) ? $_POST['ad_space_id'] : null; // Get the space_id string instead of the integer id
    
    // Type-specific fields
    $banner_image = null;
    $banner_alt_text = null;
    $text_title = null;
    $text_description = null;
    
    if ($ad_type === 'banner') {
        $banner_alt_text = trim($_POST['banner_alt_text']);
        
        // Handle image upload
        if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/ads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $file_name = uniqid('ad_') . '.' . $file_extension;
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $file_path)) {
                    $banner_image = $file_path;
                } else {
                    $error_message = 'Failed to upload banner image.';
                }
            } else {
                $error_message = 'Invalid image format. Please use JPG, PNG, or GIF.';
            }
        } else {
            $error_message = 'Please upload a banner image.';
        }
    } else {
        $text_title = trim($_POST['text_title']);
        $text_description = trim($_POST['text_description']);
    }
    
    if (empty($error_message)) {
        $pricing_query = "SELECT * FROM ad_pricing WHERE ad_type = :ad_type AND duration_days = :duration_days";
        $pricing_stmt = $db->prepare($pricing_query);
        $pricing_stmt->bindParam(':ad_type', $ad_type);
        $pricing_stmt->bindParam(':duration_days', $duration_days);
        $pricing_stmt->execute();
        $pricing = $pricing_stmt->fetch(PDO::FETCH_ASSOC);
        
        $space_multiplier = 1.0;
        $target_space_id = null; // Initialize target_space_id string
        if ($ad_space_id) {
            $space_query = "SELECT space_id, base_price_multiplier FROM ad_spaces WHERE id = :space_id AND is_enabled = 1";
            $space_stmt = $db->prepare($space_query);
            $space_stmt->bindParam(':space_id', $ad_space_id);
            $space_stmt->execute();
            $space = $space_stmt->fetch(PDO::FETCH_ASSOC);
            if ($space) {
                $space_multiplier = $space['base_price_multiplier'];
                $target_space_id = $space['space_id']; // Store the space_id string, not the integer id
            }
        }
        
        if ($pricing) {
            $base_cost = $pricing['base_price'] * $space_multiplier;
            $premium_cost = 0;
            
            if ($visibility_level === 'premium') {
                $premium_cost = $base_cost * ($pricing['premium_multiplier'] - 1);
            }
            
            $total_cost = $base_cost + $premium_cost;
            
            // Check user balance
            if ($user['credits'] >= $total_cost) {
                try {
                    $db->beginTransaction();
                    
                    // Deduct credits
                    $deduct_query = "UPDATE users SET credits = credits - :cost WHERE id = :user_id";
                    $deduct_stmt = $db->prepare($deduct_query);
                    $deduct_stmt->bindParam(':cost', $total_cost);
                    $deduct_stmt->bindParam(':user_id', $user_id);
                    $deduct_stmt->execute();
                    
                    // Create advertisement
                    $insert_ad = "INSERT INTO user_advertisements
                                 (user_id, title, ad_type, visibility_level, duration_days,
                                  banner_image, banner_alt_text, text_title, text_description,
                                  target_url, cost_paid, premium_cost, target_space_id, status)
                                 VALUES
                                 (:user_id, :title, :ad_type, :visibility_level, :duration_days,
                                  :banner_image, :banner_alt_text, :text_title, :text_description,
                                  :target_url, :cost_paid, :premium_cost, :target_space_id, 'pending')";
                    $insert_stmt = $db->prepare($insert_ad);
                    $insert_stmt->bindParam(':user_id', $user_id);
                    $insert_stmt->bindParam(':title', $title);
                    $insert_stmt->bindParam(':ad_type', $ad_type);
                    $insert_stmt->bindParam(':visibility_level', $visibility_level);
                    $insert_stmt->bindParam(':duration_days', $duration_days);
                    $insert_stmt->bindParam(':banner_image', $banner_image);
                    $insert_stmt->bindParam(':banner_alt_text', $banner_alt_text);
                    $insert_stmt->bindParam(':text_title', $text_title);
                    $insert_stmt->bindParam(':text_description', $text_description);
                    $insert_stmt->bindParam(':target_url', $target_url);
                    $insert_stmt->bindParam(':cost_paid', $base_cost);
                    $insert_stmt->bindParam(':premium_cost', $premium_cost);
                    $insert_stmt->bindParam(':target_space_id', $target_space_id);
                    $insert_stmt->execute();
                    
                    $ad_id = $db->lastInsertId();
                    
                    // Log transaction
                    $log_transaction = "INSERT INTO ad_transactions (ad_id, user_id, amount, transaction_type, description)
                                       VALUES (:ad_id, :user_id, :amount, 'purchase', :description)";
                    $log_stmt = $db->prepare($log_transaction);
                    $log_stmt->bindParam(':ad_id', $ad_id);
                    $log_stmt->bindParam(':user_id', $user_id);
                    $log_stmt->bindParam(':amount', $total_cost);
                    $description = "Ad purchase: {$ad_type} ad for {$duration_days} days" . ($visibility_level === 'premium' ? ' (Premium)' : '');
                    $log_stmt->bindParam(':description', $description);
                    $log_stmt->execute();
                    
                    // Log credit transaction
                    $credit_log = "INSERT INTO credit_transactions (user_id, amount, type, description, status)
                                  VALUES (:user_id, :amount, 'spent', :description, 'completed')";
                    $credit_stmt = $db->prepare($credit_log);
                    $negative_amount = -$total_cost;
                    $credit_stmt->bindParam(':user_id', $user_id);
                    $credit_stmt->bindParam(':amount', $negative_amount);
                    $credit_stmt->bindParam(':description', $description);
                    $credit_stmt->execute();
                    
                    $db->commit();
                    
                    // Update user credits in session
                    $user['credits'] -= $total_cost;
                    
                    $success_message = "Advertisement purchased successfully! Your ad is pending admin approval.";
                    
                    // Redirect to my ads page
                    header('Location: my-ads.php?success=1');
                    exit();
                } catch (Exception $e) {
                    $db->rollback();
                    $error_message = 'Error processing purchase: ' . $e->getMessage();
                }
            } else {
                $error_message = "Insufficient credits. You need $" . number_format($total_cost, 2) . " but have $" . number_format($user['credits'], 2);
            }
        } else {
            $error_message = 'Invalid pricing configuration.';
        }
    }
}

// Get pricing data
$pricing_query = "SELECT * FROM ad_pricing WHERE is_active = 1 ORDER BY ad_type, duration_days";
$pricing_stmt = $db->prepare($pricing_query);
$pricing_stmt->execute();
$all_pricing = $pricing_stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize pricing by type
$banner_pricing = array_filter($all_pricing, fn($p) => $p['ad_type'] === 'banner');
$text_pricing = array_filter($all_pricing, fn($p) => $p['ad_type'] === 'text');

$spaces_query = "SELECT * FROM ad_spaces WHERE is_enabled = 1 ORDER BY page_location, space_name";
$spaces_stmt = $db->prepare($spaces_query);
$spaces_stmt->execute();
$ad_spaces = $spaces_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buy Advertisement - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0;
            line-height: 1.6;
            min-height: 100vh;
            padding: 2rem;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #3b82f6, #10b981);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .header p {
            color: #94a3b8;
            font-size: 1.125rem;
        }

        .balance-card {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: 1rem;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 2rem;
        }

        .balance-label {
            font-size: 0.875rem;
            color: #94a3b8;
            margin-bottom: 0.5rem;
        }

        .balance-amount {
            font-size: 2rem;
            font-weight: 700;
            color: #10b981;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 2rem;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .pricing-card {
            background: rgba(51, 65, 85, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(148, 163, 184, 0.1);
            border-radius: 1.25rem;
            padding: 2rem;
            transition: all 0.3s ease;
        }

        .pricing-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            border-color: rgba(59, 130, 246, 0.3);
        }

        .pricing-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .pricing-type {
            font-size: 1.5rem;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 0.5rem;
        }

        .pricing-description {
            color: #94a3b8;
            font-size: 0.875rem;
        }

        .pricing-options {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .pricing-option {
            background: rgba(30, 41, 59, 0.5);
            border: 2px solid rgba(148, 163, 184, 0.1);
            border-radius: 0.75rem;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .pricing-option:hover {
            border-color: rgba(59, 130, 246, 0.5);
            background: rgba(59, 130, 246, 0.1);
        }

        .pricing-option.selected {
            border-color: #3b82f6;
            background: rgba(59, 130, 246, 0.2);
        }

        .option-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .option-duration {
            font-weight: 600;
            color: #f1f5f9;
        }

        .option-price {
            font-weight: 700;
            color: #3b82f6;
        }

        .option-premium {
            font-size: 0.75rem;
            color: #f59e0b;
        }

        .form-section {
            background: rgba(51, 65, 85, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(148, 163, 184, 0.1);
            border-radius: 1.25rem;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .form-section h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: #f1f5f9;
            margin-bottom: 0.5rem;
        }

        .form-input,
        .form-textarea,
        .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 0.5rem;
            color: #e2e8f0;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-input:focus,
        .form-textarea:focus,
        .form-select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-help {
            font-size: 0.875rem;
            color: #94a3b8;
            margin-top: 0.5rem;
        }

        /* Added live preview styles */
        .live-preview {
            background: rgba(30, 41, 59, 0.5);
            border: 2px solid rgba(59, 130, 246, 0.3);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        .live-preview h4 {
            font-size: 1rem;
            font-weight: 600;
            color: #f1f5f9;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .preview-content {
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 0.5rem;
            padding: 1.5rem;
            min-height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .preview-text-ad {
            width: 100%;
        }

        .preview-text-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: #3b82f6;
            margin-bottom: 0.5rem;
            word-break: break-word;
        }

        .preview-text-description {
            color: #94a3b8;
            font-size: 0.875rem;
            line-height: 1.5;
            word-break: break-word;
        }

        .preview-placeholder {
            color: #64748b;
            font-style: italic;
            text-align: center;
        }

        .preview-banner-img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 0.5rem;
        }

        .visibility-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .visibility-option {
            background: rgba(30, 41, 59, 0.5);
            border: 2px solid rgba(148, 163, 184, 0.1);
            border-radius: 0.75rem;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .visibility-option:hover {
            border-color: rgba(59, 130, 246, 0.5);
        }

        .visibility-option.selected {
            border-color: #3b82f6;
            background: rgba(59, 130, 246, 0.2);
        }

        .visibility-option input[type="radio"] {
            display: none;
        }

        .visibility-title {
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 0.5rem;
        }

        .visibility-description {
            font-size: 0.875rem;
            color: #94a3b8;
        }

        .cost-summary {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .cost-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            color: #94a3b8;
        }

        .cost-row.total {
            border-top: 1px solid rgba(148, 163, 184, 0.2);
            padding-top: 0.75rem;
            margin-top: 0.75rem;
            font-size: 1.25rem;
            font-weight: 700;
            color: #f1f5f9;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 1rem 2rem;
            border: none;
            border-radius: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            width: 100%;
        }

        .btn-primary:hover:not(:disabled) {
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.4);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: rgba(148, 163, 184, 0.1);
            color: #cbd5e1;
            border: 1px solid rgba(148, 163, 184, 0.2);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #94a3b8;
            text-decoration: none;
            margin-bottom: 2rem;
            transition: color 0.3s ease;
        }

        .back-link:hover {
            color: #3b82f6;
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .header h1 {
                font-size: 2rem;
            }

            .pricing-grid {
                grid-template-columns: 1fr;
            }

            .visibility-options {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Back to Dashboard
        </a>

        <div class="header">
            <h1>Buy Advertisement Space</h1>
            <p>Promote your content to thousands of visitors</p>
        </div>

        <div class="balance-card">
            <div class="balance-label">Your Credit Balance</div>
            <div class="balance-amount">$<?php echo number_format($user['credits'], 2); ?></div>
            <a href="buy-credits" class="btn btn-secondary" style="margin-top: 1rem; width: auto;">
                <i class="fas fa-plus"></i> Add Credits
            </a>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="adPurchaseForm">
            <input type="hidden" name="action" value="purchase">
            
            <!-- Step 1: Choose Ad Type -->
            <div class="form-section">
                <h3>Step 1: Choose Ad Type</h3>
                <div class="pricing-grid">
                    <div class="pricing-card">
                        <div class="pricing-header">
                            <div class="pricing-type">Banner Ads</div>
                            <div class="pricing-description">Eye-catching image advertisements</div>
                        </div>
                        <div class="pricing-options">
                            <?php foreach ($banner_pricing as $price): ?>
                            <label class="pricing-option" data-type="banner" data-duration="<?php echo $price['duration_days']; ?>" data-price="<?php echo $price['base_price']; ?>" data-multiplier="<?php echo $price['premium_multiplier']; ?>">
                                <input type="radio" name="ad_type_duration" value="banner_<?php echo $price['duration_days']; ?>">
                                <div class="option-header">
                                    <span class="option-duration"><?php echo $price['duration_days']; ?> Days</span>
                                    <span class="option-price">$<?php echo number_format($price['base_price'], 2); ?></span>
                                </div>
                                <div class="option-premium">Premium: $<?php echo number_format($price['base_price'] * $price['premium_multiplier'], 2); ?></div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="pricing-card">
                        <div class="pricing-header">
                            <div class="pricing-type">Text Ads</div>
                            <div class="pricing-description">Simple and effective text-based ads</div>
                        </div>
                        <div class="pricing-options">
                            <?php foreach ($text_pricing as $price): ?>
                            <label class="pricing-option" data-type="text" data-duration="<?php echo $price['duration_days']; ?>" data-price="<?php echo $price['base_price']; ?>" data-multiplier="<?php echo $price['premium_multiplier']; ?>">
                                <input type="radio" name="ad_type_duration" value="text_<?php echo $price['duration_days']; ?>">
                                <div class="option-header">
                                    <span class="option-duration"><?php echo $price['duration_days']; ?> Days</span>
                                    <span class="option-price">$<?php echo number_format($price['base_price'], 2); ?></span>
                                </div>
                                <div class="option-premium">Premium: $<?php echo number_format($price['base_price'] * $price['premium_multiplier'], 2); ?></div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hidden fields for selected values -->
            <input type="hidden" name="ad_type" id="selectedAdType">
            <input type="hidden" name="duration_days" id="selectedDuration">
            <input type="hidden" name="ad_space_id" id="selectedAdSpaceId">

            <!-- Step 2: Choose Visibility -->
            <div class="form-section" id="visibilitySection" style="display: none;">
                <h3>Step 2: Choose Visibility Level</h3>
                <div class="visibility-options">
                    <label class="visibility-option" data-visibility="standard">
                        <input type="radio" name="visibility_level" value="standard" checked>
                        <div class="visibility-title">Standard</div>
                        <div class="visibility-description">Regular ad rotation with fair exposure</div>
                    </label>
                    <label class="visibility-option" data-visibility="premium">
                        <input type="radio" name="visibility_level" value="premium">
                        <div class="visibility-title">Premium <i class="fas fa-star" style="color: #f59e0b;"></i></div>
                        <div class="visibility-description">Priority placement for maximum visibility</div>
                    </label>
                </div>
            </div>

            <!-- Step 3: Ad Details -->
            <div class="form-section" id="adDetailsSection" style="display: none;">
                <h3>Step 3: Ad Details</h3>
                
                <!-- Ad Space Location -->
                <div class="form-group">
                    <label class="form-label">Ad Space Location *</label>
                    <select name="ad_space_id" class="form-select" required id="adSpaceSelect">
                        <option value="">Select ad space...</option>
                        <?php foreach ($ad_spaces as $space): ?>
                            <option value="<?php echo $space['id']; ?>"
                                    data-multiplier="<?php echo $space['base_price_multiplier']; ?>"
                                    data-width="<?php echo $space['width']; ?>"
                                    data-height="<?php echo $space['height']; ?>">
                                <?php echo htmlspecialchars($space['space_name']); ?>
                                (<?php echo htmlspecialchars($space['page_location']); ?>)
                                <?php if ($space['width'] && $space['height']): ?>
                                    - <?php echo $space['width']; ?>x<?php echo $space['height']; ?>
                                <?php endif; ?>
                                <?php if ($space['base_price_multiplier'] != 1.0): ?>
                                    - <?php echo $space['base_price_multiplier']; ?>x price
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-help">Choose where your ad will be displayed</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Ad Title *</label>
                    <input type="text" name="title" class="form-input" required maxlength="255">
                    <div class="form-help">Internal name for your ad (not shown to visitors)</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Target URL *</label>
                    <input type="url" name="target_url" class="form-input" required placeholder="https://example.com">
                    <div class="form-help">Where visitors will be directed when they click your ad</div>
                </div>

                <!-- Banner Ad Fields -->
                <div id="bannerFields" style="display: none;">
                    <div class="form-group">
                        <label class="form-label">Banner Image *</label>
                        <input type="file" name="banner_image" class="form-input" accept="image/*" id="bannerImageInput">
                        <div class="form-help" id="bannerSizeHelp">Recommended size: 728x90px or 468x60px. Max 2MB.</div>
                    </div>

                    <!-- Banner preview -->
                    <div class="live-preview" id="bannerAdPreview" style="display: none;">
                        <h4>
                            <i class="fas fa-eye"></i>
                            Live Preview
                        </h4>
                        <div class="preview-content">
                            <img id="bannerPreviewImage" class="preview-banner-img" src="/placeholder.svg" alt="Banner preview" style="display: none;">
                            <div class="preview-placeholder" id="bannerPreviewPlaceholder">Upload an image to see preview</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Alt Text *</label>
                        <input type="text" name="banner_alt_text" class="form-input" maxlength="255">
                        <div class="form-help">Descriptive text for accessibility</div>
                    </div>
                </div>

                <!-- Text Ad Fields -->
                <div id="textFields" style="display: none;">
                    <div class="form-group">
                        <label class="form-label">Ad Headline *</label>
                        <input type="text" name="text_title" class="form-input" maxlength="100" id="textTitleInput">
                        <div class="form-help">Catchy headline (max 100 characters)</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Ad Description *</label>
                        <textarea name="text_description" class="form-textarea" maxlength="255" id="textDescriptionInput"></textarea>
                        <div class="form-help">Brief description (max 255 characters)</div>
                    </div>

                    <!-- Added live preview for text ads -->
                    <div class="live-preview" id="textAdPreview" style="display: none;">
                        <h4>
                            <i class="fas fa-eye"></i>
                            Live Preview
                        </h4>
                        <div class="preview-content">
                            <div class="preview-text-ad">
                                <div class="preview-text-title" id="previewTitle">Your Headline Here</div>
                                <div class="preview-text-description" id="previewDescription">Your description will appear here...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cost Summary -->
            <div class="cost-summary" id="costSummary" style="display: none;">
                <h3 style="margin-bottom: 1rem;">Cost Summary</h3>
                <div class="cost-row">
                    <span>Base Price:</span>
                    <span id="basePrice">$0.00</span>
                </div>
                <div class="cost-row" id="premiumCostRow" style="display: none;">
                    <span>Premium Upgrade:</span>
                    <span id="premiumCost">$0.00</span>
                </div>
                <div class="cost-row total">
                    <span>Total Cost:</span>
                    <span id="totalCost">$0.00</span>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                <i class="fas fa-shopping-cart"></i>
                Purchase Advertisement
            </button>
        </form>
    </div>

    <script>
        let selectedType = null;
        let selectedDuration = null;
        let selectedPrice = 0;
        let selectedMultiplier = 1;
        let selectedVisibility = 'standard';
        let selectedAdSpaceId = null;
        let spaceMultiplier = 1.0;

        // Handle ad type and duration selection
        document.querySelectorAll('.pricing-option').forEach(option => {
            option.addEventListener('click', function() {
                // Remove selected class from all options
                document.querySelectorAll('.pricing-option').forEach(opt => opt.classList.remove('selected'));
                
                // Add selected class to clicked option
                this.classList.add('selected');
                
                // Get selected values
                selectedType = this.dataset.type;
                selectedDuration = parseInt(this.dataset.duration);
                selectedPrice = parseFloat(this.dataset.price);
                selectedMultiplier = parseFloat(this.dataset.multiplier);
                
                // Update hidden fields
                document.getElementById('selectedAdType').value = selectedType;
                document.getElementById('selectedDuration').value = selectedDuration;
                
                // Show visibility section
                document.getElementById('visibilitySection').style.display = 'block';
                document.getElementById('adDetailsSection').style.display = 'block';
                
                // Show appropriate fields
                if (selectedType === 'banner') {
                    document.getElementById('bannerFields').style.display = 'block';
                    document.getElementById('textFields').style.display = 'none';
                    document.getElementById('textAdPreview').style.display = 'none';
                    document.getElementById('bannerAdPreview').style.display = 'none';
                } else {
                    document.getElementById('bannerFields').style.display = 'none';
                    document.getElementById('textFields').style.display = 'block';
                    document.getElementById('textAdPreview').style.display = 'block';
                    document.getElementById('bannerAdPreview').style.display = 'none';
                }
                
                updateCostSummary();
            });
        });

        // Handle visibility selection
        document.querySelectorAll('.visibility-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.visibility-option').forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                selectedVisibility = this.dataset.visibility;
                updateCostSummary();
            });
        });

        document.getElementById('adSpaceSelect').addEventListener('change', function() {
            selectedAdSpaceId = this.value;
            const selectedOption = this.options[this.selectedIndex];
            spaceMultiplier = parseFloat(selectedOption.dataset.multiplier) || 1.0;
            document.getElementById('selectedAdSpaceId').value = selectedAdSpaceId; // Update hidden field with space_id string
            
            // Update recommended size for banner ads
            const width = selectedOption.dataset.width;
            const height = selectedOption.dataset.height;
            if (width && height && selectedType === 'banner') {
                document.getElementById('bannerSizeHelp').textContent = `Recommended size: ${width}x${height}px. Max 2MB.`;
            }

            updateCostSummary();
        });

        // Handle banner image upload preview
        const bannerInput = document.getElementById('bannerImageInput');
        if (bannerInput) {
            bannerInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        document.getElementById('bannerPreviewImage').src = event.target.result;
                        document.getElementById('bannerPreviewImage').style.display = 'block';
                        document.getElementById('bannerPreviewPlaceholder').style.display = 'none';
                        document.getElementById('bannerAdPreview').style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                } else {
                    document.getElementById('bannerAdPreview').style.display = 'none';
                }
            });
        }

        const textTitleInput = document.getElementById('textTitleInput');
        const textDescriptionInput = document.getElementById('textDescriptionInput');
        const previewTitle = document.getElementById('previewTitle');
        const previewDescription = document.getElementById('previewDescription');

        if (textTitleInput) {
            textTitleInput.addEventListener('input', function() {
                previewTitle.textContent = this.value || 'Your Headline Here';
            });
        }

        if (textDescriptionInput) {
            textDescriptionInput.addEventListener('input', function() {
                previewDescription.textContent = this.value || 'Your description will appear here...';
            });
        }

        function updateCostSummary() {
            if (!selectedType || !selectedDuration) return;
            
            const basePrice = selectedPrice * spaceMultiplier;
            let premiumCost = 0;
            
            if (selectedVisibility === 'premium') {
                premiumCost = basePrice * (selectedMultiplier - 1);
            }
            
            const totalCost = basePrice + premiumCost;
            
            document.getElementById('basePrice').textContent = '$' + basePrice.toFixed(2);
            document.getElementById('premiumCost').textContent = '$' + premiumCost.toFixed(2);
            document.getElementById('totalCost').textContent = '$' + totalCost.toFixed(2);
            
            if (selectedVisibility === 'premium') {
                document.getElementById('premiumCostRow').style.display = 'flex';
            } else {
                document.getElementById('premiumCostRow').style.display = 'none';
            }
            
            document.getElementById('costSummary').style.display = 'block';
            document.getElementById('submitBtn').disabled = false;
        }
    </script>
</body>
</html>
