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

$page_title = 'Buy Ads - ' . SITE_NAME;
$page_description = 'Launch banner or text ad campaigns to reach engaged faucet users.';
$current_page = 'dashboard';
$additional_head = <<<HTML
<style>
    .ad-option-grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }

    .ad-option-stack {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .pricing-option {
        position: relative;
        display: block;
        border-radius: var(--radius-md);
        border: 1px solid var(--color-border);
        background: linear-gradient(135deg, rgba(15, 27, 58, 0.8), rgba(26, 42, 84, 0.85));
        padding: 1.25rem 1.5rem;
        cursor: pointer;
        transition: var(--transition-base);
        overflow: hidden;
    }

    .pricing-option:hover {
        border-color: var(--color-primary);
        box-shadow: var(--shadow-soft);
        transform: translateY(-3px);
    }

    .pricing-option.selected {
        border-color: var(--color-primary);
        box-shadow: var(--shadow-soft);
        background: linear-gradient(135deg, rgba(56, 189, 248, 0.18), rgba(168, 85, 247, 0.12));
    }

    .pricing-option input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .option-duration {
        font-weight: 600;
        letter-spacing: 0.01em;
    }

    .option-price {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--color-primary);
    }

    .option-premium {
        font-size: 0.8rem;
        color: var(--color-accent);
    }

    .visibility-grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    }

    .visibility-option {
        position: relative;
        display: block;
        border-radius: var(--radius-md);
        border: 1px solid var(--color-border);
        background: rgba(10, 20, 46, 0.85);
        padding: 1.5rem;
        transition: var(--transition-base);
        cursor: pointer;
    }

    .visibility-option:hover,
    .visibility-option.selected {
        border-color: var(--color-primary);
        background: linear-gradient(135deg, rgba(56, 189, 248, 0.15), rgba(52, 211, 153, 0.12));
        box-shadow: var(--shadow-soft);
    }

    .visibility-option input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .visibility-title {
        font-weight: 600;
        margin-bottom: 0.35rem;
    }

    .visibility-description {
        color: var(--color-text-muted);
        font-size: 0.9rem;
    }

    .step-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.35rem 0.9rem;
        border-radius: 999px;
        background: rgba(56, 189, 248, 0.15);
        color: var(--color-primary);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-size: 0.75rem;
    }

    .credit-balance-card {
        border-radius: var(--radius-md);
        background: linear-gradient(135deg, rgba(56, 189, 248, 0.18), rgba(34, 197, 94, 0.2));
        border: 1px solid rgba(56, 189, 248, 0.25);
        box-shadow: var(--shadow-soft);
    }

    .credit-balance-card .balance-amount {
        font-size: 2rem;
        font-weight: 700;
    }

    .form-help {
        display: block;
        margin-top: 0.5rem;
        font-size: 0.85rem;
        color: var(--color-text-muted);
    }

    .live-preview {
        border-radius: var(--radius-md);
        border: 1px dashed rgba(56, 189, 248, 0.45);
        background: rgba(13, 25, 56, 0.75);
        padding: 1.25rem;
    }

    .live-preview h4 {
        font-size: 1rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }

    .preview-content {
        border-radius: var(--radius-sm);
        border: 1px solid rgba(148, 163, 184, 0.15);
        background: rgba(4, 12, 31, 0.65);
        padding: 1.25rem;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 120px;
        text-align: center;
    }

    .preview-banner-img {
        max-width: 100%;
        height: auto;
        border-radius: var(--radius-sm);
    }

    .preview-placeholder {
        color: var(--color-text-muted);
        font-style: italic;
    }

    .preview-text-title {
        font-weight: 700;
        font-size: 1.1rem;
        color: var(--color-primary);
        margin-bottom: 0.35rem;
        word-break: break-word;
    }

    .preview-text-description {
        font-size: 0.95rem;
        color: var(--color-text-muted);
        word-break: break-word;
    }

    .cost-summary {
        border-radius: var(--radius-md);
        border: 1px solid var(--color-border);
        background: rgba(8, 18, 44, 0.85);
    }

    .cost-summary .cost-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 0;
        font-weight: 500;
    }

    .cost-summary .cost-row + .cost-row {
        border-top: 1px solid rgba(148, 163, 184, 0.08);
    }

    .cost-summary .cost-total {
        font-size: 1.35rem;
        font-weight: 700;
        color: var(--color-primary);
    }

    .ad-sidebar-card ul {
        list-style: none;
        padding: 0;
        margin: 0;
        display: grid;
        gap: 1rem;
    }

    .ad-sidebar-card li {
        display: flex;
        gap: 0.75rem;
        align-items: flex-start;
    }

    .ad-sidebar-card li span {
        font-size: 1.25rem;
    }

    @media (max-width: 767.98px) {
        .credit-balance-card {
            text-align: center;
        }

        .credit-balance-card .btn {
            width: 100%;
        }
    }
</style>
HTML;

include 'includes/header.php';
?>

<div class="page-wrapper flex-grow-1">
    <section class="page-hero pb-0">
        <div class="container">
            <div class="glass-card p-4 p-lg-5 animate-fade-in" data-aos="fade-up">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-8">
                        <div class="dashboard-breadcrumb mb-3">
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">Buy Ads</li>
                                </ol>
                            </nav>
                        </div>
                        <h1 class="text-white fw-bold mb-2">Launch High-Impact Campaigns</h1>
                        <p class="text-muted mb-4">Secure premium placements across the directory, boost visibility, and convert faucet traffic into loyal users.</p>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="my-ads.php" class="btn btn-theme btn-outline-glass"><i class="fas fa-rectangle-ad me-2"></i>Manage My Ads</a>
                            <a href="dashboard.php" class="btn btn-theme btn-outline-glass"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="credit-balance-card p-4 h-100 d-flex flex-column justify-content-between" data-aos="fade-left">
                            <div class="text-uppercase small fw-semibold text-muted mb-2">Available Credits</div>
                            <div class="balance-amount text-white mb-2">$<?php echo number_format($user['credits'], 2); ?></div>
                            <p class="text-muted small mb-3">Top up your balance to keep campaigns running without interruptions.</p>
                            <a href="buy-credits.php" class="btn btn-theme btn-gradient"><i class="fas fa-plus me-2"></i>Add Credits</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="ad-slot dev-slot mt-4">Campaign Banner 970x250</div>
        </div>
    </section>

    <section class="pb-5">
        <div class="container">
            <?php if ($success_message): ?>
                <div class="alert alert-glass alert-success mb-4" role="alert">
                    <span class="icon text-success"><i class="fas fa-check-circle"></i></span>
                    <div><?php echo htmlspecialchars($success_message); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-glass alert-danger mb-4" role="alert">
                    <span class="icon text-danger"><i class="fas fa-exclamation-triangle"></i></span>
                    <div><?php echo htmlspecialchars($error_message); ?></div>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-8">
                    <form method="POST" enctype="multipart/form-data" id="adPurchaseForm" class="d-grid gap-4">
                        <input type="hidden" name="action" value="purchase">
                        <input type="hidden" name="ad_type" id="selectedAdType">
                        <input type="hidden" name="duration_days" id="selectedDuration">
                        <input type="hidden" name="ad_space_id" id="selectedAdSpaceId">

                        <div class="glass-card p-4 p-lg-5" data-aos="fade-up">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                                <span class="step-badge"><i class="fas fa-layer-group"></i> Step 1</span>
                                <h2 class="h5 text-white mb-0">Choose Your Ad Format</h2>
                            </div>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="border border-light border-opacity-10 rounded-4 p-3 h-100">
                                        <h3 class="h6 text-white mb-1">Banner Ads</h3>
                                        <p class="text-muted small mb-3">Graphic placements for maximum visual impact.</p>
                                        <div class="ad-option-stack">
                                            <?php foreach ($banner_pricing as $price): ?>
                                                <label class="pricing-option" data-type="banner" data-duration="<?php echo $price['duration_days']; ?>" data-price="<?php echo $price['base_price']; ?>" data-multiplier="<?php echo $price['premium_multiplier']; ?>">
                                                    <input type="radio" name="ad_type_duration" value="banner_<?php echo $price['duration_days']; ?>">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <span class="option-duration"><?php echo $price['duration_days']; ?> Days</span>
                                                        <span class="option-price">$<?php echo number_format($price['base_price'], 2); ?></span>
                                                    </div>
                                                    <div class="option-premium">Premium: $<?php echo number_format($price['base_price'] * $price['premium_multiplier'], 2); ?></div>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border border-light border-opacity-10 rounded-4 p-3 h-100">
                                        <h3 class="h6 text-white mb-1">Text Ads</h3>
                                        <p class="text-muted small mb-3">Concise call-to-action copy that blends with content.</p>
                                        <div class="ad-option-stack">
                                            <?php foreach ($text_pricing as $price): ?>
                                                <label class="pricing-option" data-type="text" data-duration="<?php echo $price['duration_days']; ?>" data-price="<?php echo $price['base_price']; ?>" data-multiplier="<?php echo $price['premium_multiplier']; ?>">
                                                    <input type="radio" name="ad_type_duration" value="text_<?php echo $price['duration_days']; ?>">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
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
                        </div>

                        <div class="ad-slot dev-slot2" data-aos="fade-up">Inline Marketplace 728x90</div>

                        <div class="glass-card p-4 p-lg-5" id="visibilitySection" style="display: none;" data-aos="fade-up">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                                <span class="step-badge"><i class="fas fa-star"></i> Step 2</span>
                                <h2 class="h5 text-white mb-0">Set Your Visibility Level</h2>
                            </div>
                            <div class="visibility-grid">
                                <label class="visibility-option selected" data-visibility="standard">
                                    <input type="radio" name="visibility_level" value="standard" checked>
                                    <div class="visibility-title"><i class="fas fa-signal me-2 text-primary"></i>Standard Rotation</div>
                                    <div class="visibility-description">Balanced exposure within regular ad slots.</div>
                                </label>
                                <label class="visibility-option" data-visibility="premium">
                                    <input type="radio" name="visibility_level" value="premium">
                                    <div class="visibility-title"><i class="fas fa-crown me-2 text-warning"></i>Premium Boost</div>
                                    <div class="visibility-description">Top-of-page priority with accelerated impressions.</div>
                                </label>
                            </div>
                        </div>

                        <div class="glass-card p-4 p-lg-5" id="adDetailsSection" style="display: none;" data-aos="fade-up">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                                <span class="step-badge"><i class="fas fa-clipboard-list"></i> Step 3</span>
                                <h2 class="h5 text-white mb-0">Provide Campaign Details</h2>
                            </div>
                            <div class="row g-4">
                                <div class="col-12">
                                    <label class="form-label">Ad Space Location *</label>
                                    <select name="ad_space_id" class="form-select" required id="adSpaceSelect">
                                        <option value="">Select ad space...</option>
                                        <?php foreach ($ad_spaces as $space): ?>
                                            <option value="<?php echo $space['id']; ?>"
                                                    data-multiplier="<?php echo $space['base_price_multiplier']; ?>"
                                                    data-width="<?php echo $space['width']; ?>"
                                                    data-height="<?php echo $space['height']; ?>">
                                                <?php echo htmlspecialchars($space['space_name']); ?> (<?php echo htmlspecialchars($space['page_location']); ?>)
                                                <?php if ($space['width'] && $space['height']): ?>- <?php echo $space['width']; ?>x<?php echo $space['height']; ?><?php endif; ?>
                                                <?php if ($space['base_price_multiplier'] != 1.0): ?>- <?php echo $space['base_price_multiplier']; ?>x price<?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="form-help">Pick the exact slot where your campaign will appear.</span>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Ad Title *</label>
                                    <input type="text" name="title" class="form-control" required maxlength="255">
                                    <span class="form-help">Internal reference name for your records.</span>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Target URL *</label>
                                    <input type="url" name="target_url" class="form-control" required placeholder="https://example.com">
                                    <span class="form-help">Visitors will be redirected to this destination.</span>
                                </div>
                                <div class="col-12" id="bannerFields" style="display: none;">
                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <label class="form-label">Banner Image *</label>
                                            <input type="file" name="banner_image" class="form-control" accept="image/*" id="bannerImageInput">
                                            <span class="form-help" id="bannerSizeHelp">Recommended size: 728x90px or 468x60px. Max 2MB.</span>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Alt Text *</label>
                                            <input type="text" name="banner_alt_text" class="form-control" maxlength="255">
                                            <span class="form-help">Describe the banner for accessibility and SEO.</span>
                                        </div>
                                        <div class="col-12">
                                            <div class="live-preview" id="bannerAdPreview" style="display: none;">
                                                <h4><i class="fas fa-eye"></i> Live Preview</h4>
                                                <div class="preview-content">
                                                    <img id="bannerPreviewImage" class="preview-banner-img" src="/placeholder.svg" alt="Banner preview" style="display: none;">
                                                    <div class="preview-placeholder" id="bannerPreviewPlaceholder">Upload an image to see the banner preview.</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12" id="textFields" style="display: none;">
                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <label class="form-label">Ad Headline *</label>
                                            <input type="text" name="text_title" class="form-control" maxlength="100" id="textTitleInput">
                                            <span class="form-help">Max 100 characters. Keep it compelling.</span>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Ad Description *</label>
                                            <textarea name="text_description" class="form-control" maxlength="255" id="textDescriptionInput" rows="3"></textarea>
                                            <span class="form-help">Support your headline with key benefits.</span>
                                        </div>
                                        <div class="col-12">
                                            <div class="live-preview" id="textAdPreview" style="display: none;">
                                                <h4><i class="fas fa-eye"></i> Live Preview</h4>
                                                <div class="preview-content text-start">
                                                    <div class="preview-text-ad w-100">
                                                        <div class="preview-text-title" id="previewTitle">Your Headline Here</div>
                                                        <div class="preview-text-description" id="previewDescription">Your description will appear here...</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="glass-card p-4 p-lg-5 cost-summary" id="costSummary" style="display: none;" data-aos="fade-up">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                                <span class="step-badge"><i class="fas fa-cash-register"></i> Summary</span>
                                <h2 class="h5 text-white mb-0">Estimated Investment</h2>
                            </div>
                            <div class="cost-row">
                                <span>Base Price</span>
                                <span id="basePrice">$0.00</span>
                            </div>
                            <div class="cost-row" id="premiumCostRow" style="display: none;">
                                <span>Premium Upgrade</span>
                                <span id="premiumCost">$0.00</span>
                            </div>
                            <div class="cost-row">
                                <span>Ad Space Multiplier</span>
                                <span id="spaceMultiplierDisplay">1x</span>
                            </div>
                            <div class="cost-row cost-total">
                                <span>Total Due</span>
                                <span id="totalCost">$0.00</span>
                            </div>
                            <div class="d-grid mt-4">
                                <button type="submit" id="submitBtn" class="btn btn-theme btn-gradient btn-lg" disabled>
                                    <i class="fas fa-cart-plus me-2"></i>Confirm &amp; Purchase Ad
                                </button>
                            </div>
                            <p class="text-muted small mb-0 mt-3">Credits will be deducted immediately and your campaign will enter the approval queue.</p>
                        </div>
                    </form>
                </div>
                <div class="col-lg-4">
                    <div class="glass-card p-4 p-lg-5 ad-sidebar-card h-100" data-aos="fade-up" data-aos-delay="100">
                        <h2 class="h5 text-white mb-3"><i class="fas fa-rocket text-primary me-2"></i>Reach Engaged Users</h2>
                        <ul>
                            <li>
                                <span>🚀</span>
                                <div>
                                    <strong class="text-white d-block">Realtime Impressions</strong>
                                    <small class="text-muted">Ads rotate across high-traffic faucet pages with live tracking.</small>
                                </div>
                            </li>
                            <li>
                                <span>🎯</span>
                                <div>
                                    <strong class="text-white d-block">Targeted Audience</strong>
                                    <small class="text-muted">Connect with crypto-savvy users actively seeking new platforms.</small>
                                </div>
                            </li>
                            <li>
                                <span>💡</span>
                                <div>
                                    <strong class="text-white d-block">Flexible Formats</strong>
                                    <small class="text-muted">Switch between banner or text creatives anytime you renew.</small>
                                </div>
                            </li>
                            <li>
                                <span>📊</span>
                                <div>
                                    <strong class="text-white d-block">Transparent Spend</strong>
                                    <small class="text-muted">Every credit is logged in your transactions for easy reporting.</small>
                                </div>
                            </li>
                        </ul>
                    </div>
                    <div class="ad-slot dev-slot mt-4">Sidebar Campaign 300x600</div>
                    <div class="glass-card p-4 p-lg-5 mt-4" data-aos="fade-up" data-aos-delay="150">
                        <h2 class="h6 text-white mb-2"><i class="fas fa-lightbulb text-warning me-2"></i>Optimization Tips</h2>
                        <ul class="list-unstyled text-muted small mb-0 d-grid gap-2">
                            <li><i class="fas fa-check-circle text-success me-2"></i>Refresh creatives every 7 days to fight banner blindness.</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i>Pair premium visibility with trending slots for launch weeks.</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i>Track conversions via your dashboard transactions feed.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="ad-slot dev-slot2 mt-5">Footer Marketplace 970x90</div>
        </div>
    </section>
</div>

<script>
    const optionLabels = document.querySelectorAll('.pricing-option');
    const visibilityOptions = document.querySelectorAll('.visibility-option');
    let selectedType = null;
    let selectedDuration = null;
    let selectedPrice = 0;
    let selectedMultiplier = 1;
    let selectedVisibility = 'standard';
    let selectedAdSpaceId = null;
    let spaceMultiplier = 1.0;

    optionLabels.forEach(label => {
        label.addEventListener('click', function() {
            optionLabels.forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');

            selectedType = this.dataset.type;
            selectedDuration = parseInt(this.dataset.duration, 10);
            selectedPrice = parseFloat(this.dataset.price);
            selectedMultiplier = parseFloat(this.dataset.multiplier);

            document.getElementById('selectedAdType').value = selectedType;
            document.getElementById('selectedDuration').value = selectedDuration;

            document.getElementById('visibilitySection').style.display = 'block';
            document.getElementById('adDetailsSection').style.display = 'block';

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

    visibilityOptions.forEach(option => {
        option.addEventListener('click', function() {
            visibilityOptions.forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');
            selectedVisibility = this.dataset.visibility;
            updateCostSummary();
        });
    });

    const adSpaceSelect = document.getElementById('adSpaceSelect');
    adSpaceSelect.addEventListener('change', function() {
        selectedAdSpaceId = this.value;
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption && selectedOption.dataset && selectedOption.dataset.multiplier) {
            spaceMultiplier = parseFloat(selectedOption.dataset.multiplier) || 1.0;
        } else {
            spaceMultiplier = 1.0;
        }
        document.getElementById('selectedAdSpaceId').value = selectedAdSpaceId;

        if (selectedOption && selectedOption.dataset) {
            const width = selectedOption.dataset.width;
            const height = selectedOption.dataset.height;
            if (width && height && selectedType === 'banner') {
                document.getElementById('bannerSizeHelp').textContent = `Recommended size: ${width}x${height}px. Max 2MB.`;
            } else {
                document.getElementById('bannerSizeHelp').textContent = 'Recommended size: 728x90px or 468x60px. Max 2MB.';
            }
        }

        updateCostSummary();
    });

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
        if (!selectedType || !selectedDuration) {
            return;
        }

        const basePrice = selectedPrice * spaceMultiplier;
        let premiumCost = 0;

        if (selectedVisibility === 'premium') {
            premiumCost = basePrice * (selectedMultiplier - 1);
        }

        const totalCost = basePrice + premiumCost;

        document.getElementById('basePrice').textContent = '$' + basePrice.toFixed(2);
        document.getElementById('premiumCost').textContent = '$' + premiumCost.toFixed(2);
        document.getElementById('totalCost').textContent = '$' + totalCost.toFixed(2);
        document.getElementById('spaceMultiplierDisplay').textContent = spaceMultiplier.toFixed(2) + 'x';

        if (selectedVisibility === 'premium') {
            document.getElementById('premiumCostRow').style.display = 'flex';
        } else {
            document.getElementById('premiumCostRow').style.display = 'none';
        }

        document.getElementById('costSummary').style.display = 'block';
        document.getElementById('submitBtn').disabled = false;
    }
</script>

<?php include 'includes/footer.php'; ?>
