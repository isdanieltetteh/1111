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

// Get pricing for promotions and features
$promotion_pricing_query = "SELECT * FROM promotion_pricing WHERE is_active = 1 ORDER BY promotion_type, duration_days";
$promotion_pricing_stmt = $db->prepare($promotion_pricing_query);
$promotion_pricing_stmt->execute();
$promotion_pricing = $promotion_pricing_stmt->fetchAll(PDO::FETCH_ASSOC);

$feature_pricing_query = "SELECT * FROM feature_pricing WHERE is_active = 1";
$feature_pricing_stmt = $db->prepare($feature_pricing_query);
$feature_pricing_stmt->execute();
$feature_pricing = $feature_pricing_stmt->fetchAll(PDO::FETCH_ASSOC);

// Convert to associative arrays for JavaScript
$promotion_prices = [];
foreach ($promotion_pricing as $price) {
    $promotion_prices[$price['promotion_type']][$price['duration_days']] = $price['price'];
}

$feature_prices = [];
foreach ($feature_pricing as $price) {
    $feature_prices[$price['feature_type']] = $price['price'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify captcha
    $captcha_valid = false;
    
    if (isset($_POST['h-captcha-response']) && !empty($_POST['h-captcha-response'])) {
        $captcha_response = $_POST['h-captcha-response'];
        $secret_key = HCAPTCHA_SECRET_KEY;
        
        $verify_url = 'https://hcaptcha.com/siteverify';
        $data = [
            'secret' => $secret_key,
            'response' => $captcha_response,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ];
        
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents($verify_url, false, $context);
        $response = json_decode($result, true);
        
        $captcha_valid = $response['success'] ?? false;
    }
    
    if (!$captcha_valid) {
        $error_message = 'Please complete the captcha verification';
    } else {
        $name = trim($_POST['name']);
        $url = trim($_POST['url']);
        $category = $_POST['category'];
        $description = trim($_POST['description']);
        $supported_coins = trim($_POST['supported_coins']);
        $backlink_url = trim($_POST['backlink_url']);
        $referral_link = trim($_POST['referral_link'] ?? '');
        $promotion_type = $_POST['promotion_type'] ?? 'none';
        $promotion_duration = intval($_POST['promotion_duration'] ?? 0);
        $features = $_POST['features'] ?? [];
        
        // Validation
        if (empty($name) || empty($url) || empty($category) || empty($description)) {
            $error_message = 'Please fill in all required fields';
        } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
            $error_message = 'Please enter a valid URL';
        } elseif (!in_array($category, ['faucet', 'url_shortener'])) {
            $error_message = 'Please select a valid category';
        } else {
            $use_referral = in_array('referral_link', $features);
            $skip_backlink = in_array('skip_backlink', $features);
            
            if ($use_referral && !empty($referral_link)) {
                if (!filter_var($referral_link, FILTER_VALIDATE_URL)) {
                    $error_message = 'Please enter a valid referral link';
                } else {
                    // Extract base domain from both URLs to ensure they're from the same site
                    $main_domain = parse_url($url, PHP_URL_HOST);
                    $ref_domain = parse_url($referral_link, PHP_URL_HOST);
                    
                    if ($main_domain !== $ref_domain) {
                        $error_message = 'Referral link must be from the same domain as the main site URL';
                    }
                }
            } elseif ($use_referral && empty($referral_link)) {
                $error_message = 'Referral link is required when using the referral link feature';
            }
            
            if (!$skip_backlink) {
                if (empty($backlink_url)) {
                    $error_message = 'Backlink URL is required (or purchase Skip Backlink feature for $' . number_format($feature_prices['skip_backlink'] ?? 0, 2) . ')';
                } elseif (!filter_var($backlink_url, FILTER_VALIDATE_URL)) {
                    $error_message = 'Please enter a valid backlink URL';
                } else {
                    // Prevent using the same URL as backlink
                    $main_host = parse_url($url, PHP_URL_HOST);
                    $backlink_host = parse_url($backlink_url, PHP_URL_HOST);
                    
                    if ($main_host === $backlink_host) {
                        $error_message = 'Backlink URL cannot be from the same domain as your site. You need to place our link on a different website.';
                    }
                }
            }
            
            if (empty($error_message)) {
                // Check for duplicate URL (main URL check, but allow if using referral feature)
                $duplicate_query = "SELECT id, name FROM sites WHERE url = :url";
                $duplicate_stmt = $db->prepare($duplicate_query);
                $duplicate_stmt->bindParam(':url', $url);
                $duplicate_stmt->execute();
                $existing_site = $duplicate_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_site && !$use_referral) {
                    $error_message = 'This URL already exists: ' . htmlspecialchars($existing_site['name']) . '. Purchase "Use Referral Link" feature ($' . number_format($feature_prices['referral_link'] ?? 0, 2) . ') to submit with your referral link.';
                } else {
                    // Calculate total cost
                    $total_cost = 0;
                    
                    // Add promotion cost
                    if ($promotion_type !== 'none' && $promotion_duration > 0) {
                        $total_cost += $promotion_prices[$promotion_type][$promotion_duration] ?? 0;
                    }
                    
                    // Add feature costs
                    foreach ($features as $feature) {
                        $total_cost += $feature_prices[$feature] ?? 0;
                    }
                    
                    // Check if user has enough credits
                    if ($total_cost > 0 && $user['credits'] < $total_cost) {
                        $error_message = "Insufficient credits. You need $" . number_format($total_cost, 2) . " but have $" . number_format($user['credits'], 2);
                    } else {
                        try {
                            $db->beginTransaction();
                            
                            // Handle logo upload
                            $logo_path = '';
                            
                            if (isset($_POST['auto_fetched_logo']) && !empty($_POST['auto_fetched_logo'])) {
                                // Auto-fetched logo from temp directory
                                $temp_logo_path = trim($_POST['auto_fetched_logo']);
                                $temp_full_path = __DIR__ . '/' . $temp_logo_path;
                                
                                if (file_exists($temp_full_path)) {
                                    // Move from temp to permanent location
                                    $upload_dir = 'assets/images/logos/';
                                    if (!is_dir($upload_dir)) {
                                        mkdir($upload_dir, 0755, true);
                                    }
                                    
                                    $file_extension = pathinfo($temp_logo_path, PATHINFO_EXTENSION);
                                    $logo_filename = 'logo_' . time() . '_' . uniqid() . '.' . $file_extension;
                                    $logo_path = $upload_dir . $logo_filename;
                                    
                                    if (rename($temp_full_path, $logo_path)) {
                                        // Successfully moved auto-fetched logo
                                    } else {
                                        // Fallback: copy if rename fails
                                        if (copy($temp_full_path, $logo_path)) {
                                            unlink($temp_full_path); // Clean up temp file
                                        }
                                    }
                                }
                            }
                            // Handle manual file upload if no auto-fetched logo
                            elseif (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] == 0) {
                                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                                $max_size = 2 * 1024 * 1024; // 2MB
                                
                                if (in_array($_FILES['site_logo']['type'], $allowed_types) && $_FILES['site_logo']['size'] <= $max_size) {
                                    $upload_dir = 'assets/images/logos/';
                                    if (!is_dir($upload_dir)) {
                                        mkdir($upload_dir, 0755, true);
                                    }
                                    
                                    $file_extension = pathinfo($_FILES['site_logo']['name'], PATHINFO_EXTENSION);
                                    $logo_filename = 'logo_' . time() . '_' . uniqid() . '.' . $file_extension;
                                    $logo_path = $upload_dir . $logo_filename;
                                    
                                    move_uploaded_file($_FILES['site_logo']['tmp_name'], $logo_path);
                                }
                            }
                            
                            $final_url = ($use_referral && !empty($referral_link)) ? $referral_link : $url;
                            
                            // Insert site
                            $insert_query = "INSERT INTO sites (name, url, category, description, supported_coins, logo, backlink_url, referral_link, submitted_by, is_approved) 
                                           VALUES (:name, :url, :category, :description, :supported_coins, :logo, :backlink_url, :referral_link, :user_id, :is_approved)";
                            $insert_stmt = $db->prepare($insert_query);
                            $insert_stmt->bindParam(':name', $name);
                            $insert_stmt->bindParam(':url', $final_url);
                            $insert_stmt->bindParam(':category', $category);
                            $insert_stmt->bindParam(':description', $description);
                            $insert_stmt->bindParam(':supported_coins', $supported_coins);
                            $insert_stmt->bindParam(':logo', $logo_path);
                            $insert_stmt->bindParam(':backlink_url', $backlink_url);
                            $insert_stmt->bindParam(':referral_link', $referral_link);
                            $insert_stmt->bindParam(':user_id', $user_id);
                            $is_approved = in_array('priority_review', $features) ? 1 : 0;
                            $insert_stmt->bindParam(':is_approved', $is_approved);
                            $insert_stmt->execute();
                            
                            $site_id = $db->lastInsertId();
                            
                            // Process features
                            foreach ($features as $feature) {
                                $feature_query = "INSERT INTO site_features (site_id, feature_type, is_active) VALUES (:site_id, :feature_type, 1)";
                                $feature_stmt = $db->prepare($feature_query);
                                $feature_stmt->bindParam(':site_id', $site_id);
                                $feature_stmt->bindParam(':feature_type', $feature);
                                $feature_stmt->execute();
                            }
                            
                            if (!$skip_backlink && !empty($backlink_url)) {
                                $backlink_query = "INSERT INTO backlink_tracking (site_id, backlink_url, status, last_checked, created_at) 
                                                 VALUES (:site_id, :backlink_url, 'pending', NOW(), NOW())";
                                $backlink_stmt = $db->prepare($backlink_query);
                                $backlink_stmt->bindParam(':site_id', $site_id);
                                $backlink_stmt->bindParam(':backlink_url', $backlink_url);
                                $backlink_stmt->execute();
                            }
                            
                            // Process promotion if selected
                            if ($promotion_type !== 'none' && $promotion_duration > 0) {
                                $expires_at = date('Y-m-d H:i:s', strtotime("+{$promotion_duration} days"));
                                
                                $promotion_query = "INSERT INTO site_promotions (site_id, user_id, promotion_type, amount_paid, duration_days, expires_at, payment_status) 
                                                  VALUES (:site_id, :user_id, :promotion_type, :amount, :duration, :expires_at, 'completed')";
                                $promotion_stmt = $db->prepare($promotion_query);
                                $promotion_stmt->bindParam(':site_id', $site_id);
                                $promotion_stmt->bindParam(':user_id', $user_id);
                                $promotion_stmt->bindParam(':promotion_type', $promotion_type);
                                $promotion_cost = $promotion_prices[$promotion_type][$promotion_duration] ?? 0;
                                $promotion_stmt->bindValue(':amount', $promotion_cost, PDO::PARAM_STR);
                                $promotion_stmt->bindParam(':duration', $promotion_duration);
                                $promotion_stmt->bindParam(':expires_at', $expires_at);
                                $promotion_stmt->execute();
                                
                                // Update site promotion status
                                $update_site = "UPDATE sites SET is_{$promotion_type} = 1, {$promotion_type}_until = :expires_at WHERE id = :site_id";
                                $update_stmt = $db->prepare($update_site);
                                $update_stmt->bindParam(':expires_at', $expires_at);
                                $update_stmt->bindParam(':site_id', $site_id);
                                $update_stmt->execute();
                            }
                            
                            // Deduct credits if any cost
                            if ($total_cost > 0) {
                                $deduct_query = "UPDATE users SET credits = credits - :cost WHERE id = :user_id";
                                $deduct_stmt = $db->prepare($deduct_query);
                                $deduct_stmt->bindParam(':cost', $total_cost);
                                $deduct_stmt->bindParam(':user_id', $user_id);
                                $deduct_stmt->execute();
                            }
                            
                            $db->commit();
                            
                            if ($is_approved) {
                                $success_message = 'Site submitted and approved! It\'s now live in our directory.';
                            } else {
                                $success_message = 'Site submitted successfully! It will be reviewed within 24-48 hours.';
                            }
                            
                            // Clear form data
                            $_POST = [];
                            
                        } catch (Exception $e) {
                            $db->rollback();
                            $error_message = 'Error submitting site. Please try again.';
                        }
                    }
                }
            }
        }
    }
}

$page_title = 'Submit Site - ' . SITE_NAME;
$page_description = 'Submit your crypto faucet or URL shortener to our directory for review and approval.';
$current_page = 'submit';

$additional_head = '
    <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
    <script>
        window.promotionPrices = ' . json_encode($promotion_prices) . ';
        window.featurePrices = ' . json_encode($feature_prices) . ';
        window.siteUrl = "' . SITE_URL . '";
    </script>
';

include 'includes/header.php';
?>

<section class="page-hero pt-5 pb-0">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-lg-7 text-center text-lg-start">
                <span class="hero-badge mb-3">Premium Listing Submission</span>
                <h1 class="hero-title">Showcase Your <span class="gradient-text">Crypto Site</span></h1>
                <p class="lead text-secondary mt-3 mb-4">
                    Add your faucet or shortener to the <?php echo SITE_NAME; ?> directory and unlock premium visibility with trusted crypto earners.
                </p>
                <div class="d-flex flex-wrap gap-3 justify-content-center justify-content-lg-start">
                    <span class="option-chip"><i class="fas fa-magic"></i> Auto metadata import</span>
                    <span class="option-chip"><i class="fas fa-shield-halved"></i> Manual trust review</span>
                    <span class="option-chip"><i class="fas fa-bolt"></i> Same-day moderation</span>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="dev-slot mb-4">Hero Banner 970x250</div>
                <div class="glass-card p-4 h-100">
                    <div class="d-flex flex-wrap justify-content-between gap-3">
                        <div>
                            <div class="text-secondary text-uppercase small">Average approval</div>
                            <div class="display-6 fw-bold text-info">24h</div>
                        </div>
                        <div>
                            <div class="text-secondary text-uppercase small">Listings live</div>
                            <div class="display-6 fw-bold text-warning">3k+</div>
                        </div>
                        <div>
                            <div class="text-secondary text-uppercase small">Ad ready audience</div>
                            <div class="display-6 fw-bold text-success">92%</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<main class="py-5 py-lg-6">
    <div class="container">
        <?php if ($success_message): ?>
            <div class="alert-glass alert-success mb-4">
                <span class="icon"><i class="fas fa-check-circle"></i></span>
                <div><?php echo htmlspecialchars($success_message); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert-glass alert-danger mb-4">
                <span class="icon"><i class="fas fa-exclamation-triangle"></i></span>
                <div><?php echo htmlspecialchars($error_message); ?></div>
            </div>
        <?php endif; ?>

        <div class="row g-5 align-items-start">
            <div class="col-lg-8">
                <div class="glass-card p-4 p-lg-5 h-100">
                    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
                        <div>
                            <h2 class="section-heading mb-1">Submit Your Site</h2>
                            <p class="text-secondary mb-0">Share accurate details to fast-track manual verification and go live sooner.</p>
                        </div>
                        <span class="option-chip"><i class="fas fa-wallet"></i> Balance: $<?php echo number_format($user['credits'], 2); ?></span>
                    </div>

                    <form method="POST" enctype="multipart/form-data" id="submitForm" class="form-shell">
                        <div class="form-section">
                            <div class="form-section-header">
                                <div>
                                    <h3 class="form-section-title">1. Listing Essentials</h3>
                                    <p class="form-section-subtitle">Introduce your platform with the fundamentals reviewers and users need.</p>
                                </div>
                                <span class="form-note"><i class="fas fa-magic"></i> Auto-fill powered by metadata</span>
                            </div>
                            <div class="row g-4">
                                <div class="col-12">
                                    <label for="url" class="form-label">Main Site URL *</label>
                                    <div class="position-relative">
                                        <input type="url"
                                               id="url"
                                               name="url"
                                               class="form-control form-control-lg"
                                               placeholder="https://example.com"
                                               value="<?php echo htmlspecialchars($_POST['url'] ?? ''); ?>"
                                               required>
                                        <button type="button" id="autoFetchBtn" class="btn btn-theme btn-outline-glass btn-sm position-absolute top-50 end-0 translate-middle-y me-2" style="display: none;">
                                            <i class="fas fa-wand-magic-sparkles me-1"></i> Auto Fill
                                        </button>
                                    </div>
                                    <div id="urlValidation" class="validation-status mt-2"></div>
                                    <div class="form-text">Enter the main homepage URL (not a referral link).</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="name" class="form-label">Site Name *</label>
                                    <input type="text"
                                           id="name"
                                           name="name"
                                           class="form-control"
                                           placeholder="Enter site name"
                                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                           required>
                                    <div class="form-text">Use a clear, descriptive brand name.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="category" class="form-label">Category *</label>
                                    <select id="category" name="category" class="form-select" required>
                                        <option value="">Select category</option>
                                        <option value="faucet" <?php echo ($_POST['category'] ?? '') === 'faucet' ? 'selected' : ''; ?>>Crypto Faucet</option>
                                        <option value="url_shortener" <?php echo ($_POST['category'] ?? '') === 'url_shortener' ? 'selected' : ''; ?>>URL Shortener</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label for="description" class="form-label">Description *</label>
                                    <textarea id="description"
                                              name="description"
                                              class="form-control"
                                              rows="4"
                                              placeholder="Describe your site: payment rates, features, supported coins, minimum payout, etc."
                                              required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                    <div class="form-text">Be detailed and honest – this helps users understand your offer.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="supported_coins" class="form-label">Supported Cryptocurrencies</label>
                                    <input type="text"
                                           id="supported_coins"
                                           name="supported_coins"
                                           class="form-control"
                                           placeholder="BTC, ETH, LTC, DOGE, TRX, etc."
                                           value="<?php echo htmlspecialchars($_POST['supported_coins'] ?? ''); ?>">
                                    <div class="form-text">List every token or coin users can earn.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="site_logo" class="form-label">Site Logo *</label>
                                    <input type="file"
                                           id="site_logo"
                                           name="site_logo"
                                           class="form-control"
                                           accept="image/*"
                                           required>
                                    <div class="form-text">Recommended 200x200px, max 2MB (JPG, PNG, GIF).</div>
                                    <div class="mt-3">
                                        <img id="logoPreview" alt="Logo preview" style="display:none;">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="form-section-header">
                                <div>
                                    <h3 class="form-section-title">2. Compliance & Backlink</h3>
                                    <p class="form-section-subtitle">Verify backlink placement and unlock referral tracking if enabled.</p>
                                </div>
                            </div>
                            <div class="row g-4">
                                <div class="col-12">
                                    <label for="backlink_url" class="form-label" id="backLinkLabel">Backlink URL *</label>
                                    <div class="d-flex flex-column flex-md-row align-items-md-center gap-3">
                                        <input type="url"
                                               id="backlink_url"
                                               name="backlink_url"
                                               class="form-control"
                                               placeholder="https://yoursite.com/page-with-backlink"
                                               value="<?php echo htmlspecialchars($_POST['backlink_url'] ?? ''); ?>"
                                               required>
                                        <button type="button" id="checkBacklinkBtn" class="btn btn-theme btn-outline-glass btn-sm" style="display: none;">
                                            <i class="fas fa-link"></i> Verify
                                        </button>
                                    </div>
                                    <div id="backlinkValidation" class="validation-status mt-2"></div>
                                    <div class="form-text">Backlink must live on a different domain than your submission.</div>
                                </div>
                                <div class="col-12" id="referralLinkGroup" style="display: none;">
                                    <label for="referral_link" class="form-label">Your Referral Link *</label>
                                    <input type="url"
                                           id="referral_link"
                                           name="referral_link"
                                           class="form-control"
                                           placeholder="https://yoursite.com/ref/yourcode"
                                           value="<?php echo htmlspecialchars($_POST['referral_link'] ?? ''); ?>"
                                           disabled>
                                    <div class="form-text">Must match the same domain as your main site URL.</div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="form-section-header">
                                <div>
                                    <h3 class="form-section-title">3. Promotions & Features</h3>
                                    <p class="form-section-subtitle">Boost visibility with premium placements or save time with add-ons.</p>
                                </div>
                            </div>
                            <div class="option-grid two-col mb-4">
                                <label class="option-tile">
                                    <input type="radio" name="promotion_type" value="none" checked>
                                    <div class="option-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="option-title">No Promotion</div>
                                                <div class="option-meta">Standard listing placement</div>
                                            </div>
                                            <div class="option-price">$0.00</div>
                                        </div>
                                    </div>
                                </label>
                                <label class="option-tile">
                                    <input type="radio" name="promotion_type" value="sponsored">
                                    <div class="option-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="option-title">Sponsored</div>
                                                <div class="option-meta">Top carousel placement</div>
                                            </div>
                                            <span class="option-chip"><i class="fas fa-star"></i> Premium reach</span>
                                        </div>
                                    </div>
                                </label>
                                <label class="option-tile">
                                    <input type="radio" name="promotion_type" value="boosted">
                                    <div class="option-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="option-title">Boosted</div>
                                                <div class="option-meta">Priority after sponsored</div>
                                            </div>
                                            <span class="option-chip"><i class="fas fa-rocket"></i> High exposure</span>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <div class="option-grid two-col mb-4" id="durationSelection" style="display: none;">
                                <label class="option-tile">
                                    <input type="radio" name="promotion_duration" value="7">
                                    <div class="option-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="option-title mb-0">7 Days</div>
                                            <span class="option-price duration-price" data-sponsored="<?php echo $promotion_prices['sponsored'][7] ?? 0; ?>" data-boosted="<?php echo $promotion_prices['boosted'][7] ?? 0; ?>">$0.00</span>
                                        </div>
                                    </div>
                                </label>
                                <label class="option-tile">
                                    <input type="radio" name="promotion_duration" value="30">
                                    <div class="option-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="option-title mb-0">30 Days</div>
                                                <div class="option-meta">Most popular</div>
                                            </div>
                                            <span class="option-price duration-price" data-sponsored="<?php echo $promotion_prices['sponsored'][30] ?? 0; ?>" data-boosted="<?php echo $promotion_prices['boosted'][30] ?? 0; ?>">$0.00</span>
                                        </div>
                                    </div>
                                </label>
                                <label class="option-tile">
                                    <input type="radio" name="promotion_duration" value="90">
                                    <div class="option-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="option-title mb-0">90 Days</div>
                                                <div class="option-meta">Best value</div>
                                            </div>
                                            <span class="option-price duration-price" data-sponsored="<?php echo $promotion_prices['sponsored'][90] ?? 0; ?>" data-boosted="<?php echo $promotion_prices['boosted'][90] ?? 0; ?>">$0.00</span>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <div class="option-grid two-col">
                                <label class="option-tile">
                                    <input type="checkbox" name="features[]" value="referral_link" id="featureReferral" <?php echo in_array('referral_link', ($_POST['features'] ?? []), true) ? 'checked' : ''; ?>>
                                    <div class="option-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="option-title">Use Referral Link</div>
                                                <div class="option-meta">Earn with your referral URL (allows duplicates)</div>
                                            </div>
                                            <span class="option-price">$<?php echo number_format($feature_prices['referral_link'] ?? 0, 2); ?></span>
                                        </div>
                                    </div>
                                </label>
                                <label class="option-tile">
                                    <input type="checkbox" name="features[]" value="skip_backlink" id="featureSkipBacklink" <?php echo in_array('skip_backlink', ($_POST['features'] ?? []), true) ? 'checked' : ''; ?>>
                                    <div class="option-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="option-title">Skip Backlink</div>
                                                <div class="option-meta">Submit without placing a backlink</div>
                                            </div>
                                            <span class="option-price">$<?php echo number_format($feature_prices['skip_backlink'] ?? 0, 2); ?></span>
                                        </div>
                                    </div>
                                </label>
                                <label class="option-tile">
                                    <input type="checkbox" name="features[]" value="priority_review" <?php echo in_array('priority_review', ($_POST['features'] ?? []), true) ? 'checked' : ''; ?>>
                                    <div class="option-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="option-title">Priority Review</div>
                                                <div class="option-meta">Jump the queue with 24h approval</div>
                                            </div>
                                            <span class="option-price">$<?php echo number_format($feature_prices['priority_review'] ?? 0, 2); ?></span>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <div class="total-cost-card mt-4">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="fw-semibold text-uppercase text-secondary">Total Due</div>
                                    <div id="totalCost" class="fs-4 fw-bold text-info">$0.00</div>
                                </div>
                                <div class="balance mt-2">Credits available: $<?php echo number_format($user['credits'], 2); ?></div>
                            </div>
                        </div>

                        <div class="dev-slot2 text-center">Inline Ad 728x90</div>

                        <div class="form-section">
                            <div class="form-section-header">
                                <div>
                                    <h3 class="form-section-title">4. Final Checks</h3>
                                    <p class="form-section-subtitle">Agree to the terms and confirm you're ready to submit.</p>
                                </div>
                            </div>
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" name="terms_accepted" id="terms_accepted" required <?php echo isset($_POST['terms_accepted']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="terms_accepted">
                                    I confirm I have read and agree to the <a href="terms" target="_blank">submission guidelines</a> and provided accurate information.
                                </label>
                            </div>
                            <div class="d-flex justify-content-center mb-4">
                                <div class="h-captcha" data-sitekey="<?php echo HCAPTCHA_SITE_KEY; ?>"></div>
                            </div>
                            <button type="submit" class="btn btn-theme btn-gradient w-100" id="submitBtn">
                                <i class="fas fa-paper-plane me-2"></i> Submit Site for Review
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="dev-slot1 mb-4">Sidebar Ad 300x600</div>
                <div class="auth-aside-card mb-4">
                    <h4 class="section-heading mb-3">Submission Checklist</h4>
                    <ul class="list-unstyled d-grid gap-3 mb-0 text-secondary">
                        <li class="auth-bullet"><i class="fas fa-check"></i> Site must be functional and paying users.</li>
                        <li class="auth-bullet"><i class="fas fa-check"></i> Place a backlink to <?php echo SITE_NAME; ?> (unless you buy Skip Backlink).</li>
                        <li class="auth-bullet"><i class="fas fa-check"></i> Backlink must live on a different domain than your submission.</li>
                        <li class="auth-bullet"><i class="fas fa-check"></i> Provide a complete, honest description.</li>
                        <li class="auth-bullet"><i class="fas fa-check"></i> Upload a high quality logo (200x200px recommended).</li>
                        <li class="auth-bullet"><i class="fas fa-times"></i> No scams, adult, or illegal content accepted.</li>
                    </ul>
                </div>

                <div class="glass-card p-4 mb-4">
                    <h4 class="section-heading mb-3">Backlink Requirements</h4>
                    <p class="text-secondary mb-4">Add one of the following snippets to a different website before submitting your listing.</p>
                    <div class="mb-4">
                        <strong>Text Link</strong>
                        <div class="embed-code mt-2">
                            <button class="copy-btn" onclick="copyBacklinkCode('textLinkCode')"><i class="fas fa-copy"></i> Copy</button>
                            <pre id="textLinkCode">&lt;a href="<?php echo SITE_URL; ?>" target="_blank" rel="noopener"&gt;<?php echo SITE_NAME; ?>&lt;/a&gt;</pre>
                        </div>
                        <div class="form-text mt-2">Preview: <a href="<?php echo SITE_URL; ?>" target="_blank" rel="noopener"><?php echo SITE_NAME; ?></a></div>
                    </div>
                    <div>
                        <strong>Banner Link</strong>
                        <div class="embed-code mt-2">
                            <button class="copy-btn" onclick="copyBacklinkCode('bannerLinkCode')"><i class="fas fa-copy"></i> Copy</button>
                            <pre id="bannerLinkCode">&lt;a href="<?php echo SITE_URL; ?>" target="_blank" rel="noopener"&gt;
    &lt;img src="<?php echo SITE_URL; ?>/banner.png" alt="<?php echo SITE_NAME; ?>"&gt;
&lt;/a&gt;</pre>
                        </div>
                    </div>
                </div>

                <div class="dev-slot mb-4">After Content Ad 728x90</div>

                <div class="glass-card p-4 mb-4">
                    <h4 class="section-heading mb-3">Review Timeline</h4>
                    <ol class="text-secondary ps-3 mb-0">
                        <li class="mb-2">Submission – Your listing enters moderation.</li>
                        <li class="mb-2">Verification – We confirm backlink and functionality.</li>
                        <li class="mb-2">Approval – Listings go live within 24–48 hours.</li>
                        <li>Notification – Receive status updates via email.</li>
                    </ol>
                </div>

                <div class="glass-card p-4">
                    <h4 class="section-heading mb-3">Pro Tips for Fast Approval</h4>
                    <ul class="text-secondary ps-3 mb-0">
                        <li class="mb-2">Write a transparent, detailed description.</li>
                        <li class="mb-2">List every supported cryptocurrency.</li>
                        <li class="mb-2">Ensure your site is fully mobile responsive.</li>
                        <li class="mb-2">Have clear payment proofs on standby.</li>
                        <li>Respond quickly to follow-up questions.</li>
                    </ul>
                </div>

                <div class="dev-slot2 mt-4">Footer Ad 728x90</div>
            </div>
        </div>
    </div>
</main>

<script>
(function(){
    // Server-provided price maps
    const PROMOTION_PRICES = <?php echo json_encode($promotion_prices, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>;
    const FEATURE_PRICES = <?php echo json_encode($feature_prices, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>;

    const $ = (sel) => document.querySelector(sel);
    const $$ = (sel) => Array.from(document.querySelectorAll(sel));

    const totalCostEl = $('#totalCost');
    const durationWrap = $('#durationSelection');
    const backlinkInput = $('#backlink_url');
    const backlinkLabel = $('#backLinkLabel');
    const featureSkipBack = $('#featureSkipBacklink');
    const featureReferral = $('#featureReferral');
    const referralInput = $('#referral_link');
    const referralGroup = $('#referralLinkGroup');
    const logoInput = $('#site_logo');
    const logoPreview = $('#logoPreview');
    const urlInput = $('#url');
    const autoFetchBtn = $('#autoFetchBtn');
    const checkBacklinkBtn = $('#checkBacklinkBtn');
    const nameInput = $('#name');
    const descInput = $('#description');

    function showValidation(elementId, type, message) {
        const el = $(elementId);
        if (!el) return;
        
        el.className = `validation-status validation-${type}`;
        el.textContent = message;
        el.style.display = 'block';
    }

    function hideValidation(elementId) {
        const el = $(elementId);
        if (el) el.style.display = 'none';
    }

    async function autoFetchMetadata() {
        const url = urlInput.value.trim();
        if (!url) return;

        autoFetchBtn.disabled = true;
        autoFetchBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Fetching...';
        
        try {
            const response = await fetch(`api/fetch_metadata.php?url=${encodeURIComponent(url)}`);
            const data = await response.json();
            
            if (data.success) {
                if (data.title && !nameInput.value.trim()) {
                    nameInput.value = data.title;
                }
                if (data.description && !descInput.value.trim()) {
                    descInput.value = data.description;
                }
                if (data.logo_url) {
                    // Download and set logo
                    const logoResponse = await fetch(`api/download_logo.php?url=${encodeURIComponent(data.logo_url)}&site_url=${encodeURIComponent(url)}`);
                    const logoData = await logoResponse.json();
                    if (logoData.success) {
                        logoPreview.src = logoData.local_path;
                        logoPreview.style.display = 'block';
                        let hiddenInput = document.getElementById('auto_fetched_logo');
                        if (!hiddenInput) {
                            hiddenInput = document.createElement('input');
                            hiddenInput.type = 'hidden';
                            hiddenInput.id = 'auto_fetched_logo';
                            hiddenInput.name = 'auto_fetched_logo';
                            logoInput.parentNode.appendChild(hiddenInput);
                        }
                        hiddenInput.value = logoData.local_path;
                        // Make logo input optional since we have auto-fetched logo
                        logoInput.removeAttribute('required');
                    }
                }
                showValidation('#urlValidation', 'success', 'Metadata fetched successfully!');
            } else {
                showValidation('#urlValidation', 'error', data.error || 'Could not fetch metadata');
            }
        } catch (error) {
            showValidation('#urlValidation', 'error', 'Error fetching metadata');
        } finally {
            autoFetchBtn.disabled = false;
            autoFetchBtn.innerHTML = '<i class="fas fa-wand-magic-sparkles"></i> Auto Fill';
        }
    }

    async function checkBacklink() {
        const backlinkUrl = backlinkInput.value.trim();
        const siteUrl = urlInput.value.trim();
        
        if (!backlinkUrl || !siteUrl) return;

        try {
            const siteHost = new URL(siteUrl).hostname;
            const backlinkHost = new URL(backlinkUrl).hostname;
            
            if (siteHost === backlinkHost) {
                showValidation('#backlinkValidation', 'error', 'Backlink must be on a different domain than your site');
                return;
            }
        } catch (e) {
            showValidation('#backlinkValidation', 'error', 'Invalid URL format');
            return;
        }

        showValidation('#backlinkValidation', 'loading', 'Verifying backlink...');
        
        try {
            const response = await fetch(`api/check_backlink.php?url=${encodeURIComponent(backlinkUrl)}`);
            const data = await response.json();
            
            if (data.success) {
                showValidation('#backlinkValidation', 'success', 'Backlink verified successfully!');
            } else {
                showValidation('#backlinkValidation', 'error', data.error || 'Backlink not found');
            }
        } catch (error) {
            showValidation('#backlinkValidation', 'error', 'Error verifying backlink');
        }
    }

    // Logo preview
    if (logoInput) {
        logoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    logoPreview.src = e.target.result;
                    logoPreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
                const hiddenInput = document.getElementById('auto_fetched_logo');
                if (hiddenInput) {
                    hiddenInput.value = '';
                }
                // Make logo input required again
                logoInput.setAttribute('required', 'required');
            } else {
                logoPreview.style.display = 'none';
            }
        });
    }

    // URL input handling
    if (urlInput) {
        urlInput.addEventListener('input', function() {
            const url = this.value.trim();
            if (url && url.startsWith('http')) {
                autoFetchBtn.style.display = 'inline-block';
            } else {
                autoFetchBtn.style.display = 'none';
                hideValidation('#urlValidation');
            }
        });
    }

    if (autoFetchBtn) {
        autoFetchBtn.addEventListener('click', autoFetchMetadata);
    }

    // Backlink validation
    if (backlinkInput) {
        backlinkInput.addEventListener('input', function() {
            const url = this.value.trim();
            if (url && url.startsWith('http') && !(featureSkipBack && featureSkipBack.checked)) {
                checkBacklinkBtn.style.display = 'inline-block';
            } else {
                checkBacklinkBtn.style.display = 'none';
                hideValidation('#backlinkValidation');
            }
        });
    }

    if (checkBacklinkBtn) {
        checkBacklinkBtn.addEventListener('click', checkBacklink);
    }

    function currentPromotionType(){
        const el = document.querySelector('input[name="promotion_type"]:checked');
        return el ? el.value : 'none';
    }

    function currentPromotionDuration(){
        const el = document.querySelector('input[name="promotion_duration"]:checked');
        return el ? parseInt(el.value, 10) : 0;
    }

    function updateDurationVisibility(){
        if (!durationWrap) return;
        const type = currentPromotionType();
        durationWrap.style.display = (type !== 'none') ? 'block' : 'none';
        // Update prices displayed in duration cards based on selected type
        $$('.duration-price').forEach(span => {
            const sponsored = parseFloat(span.getAttribute('data-sponsored') || '0');
            const boosted   = parseFloat(span.getAttribute('data-boosted') || '0');
            span.textContent = '$' + (type === 'sponsored' ? sponsored : (type === 'boosted' ? boosted : 0)).toFixed(2);
        });
    }

    function updateReferralState() {
        const enabled = featureReferral && featureReferral.checked;
        if (referralGroup) {
            referralGroup.style.display = enabled ? 'block' : 'none';
        }
        if (referralInput) {
            referralInput.disabled = !enabled;
            referralInput.required = enabled;
        }
    }

    function updateBacklinkState() {
        const skip = featureSkipBack && featureSkipBack.checked;
        if (backlinkInput) {
            backlinkInput.disabled = !!skip;
            backlinkInput.required = !skip;
        }
        if (backlinkLabel) {
            backlinkLabel.textContent = skip ? 'Backlink URL (skipped by paid option)' : 'Backlink URL *';
        }
        if (checkBacklinkBtn) {
            checkBacklinkBtn.style.display = skip ? 'none' : (backlinkInput.value.trim() ? 'inline-block' : 'none');
        }
        if (skip) {
            hideValidation('#backlinkValidation');
        }
    }

    function calcTotal() {
        let total = 0;
        const type = currentPromotionType();
        const dur  = currentPromotionDuration();
        if (type !== 'none' && dur > 0 && PROMOTION_PRICES[type] && PROMOTION_PRICES[type][dur]) {
            total += parseFloat(PROMOTION_PRICES[type][dur]);
        }
        $$('input[name="features[]"]:checked').forEach(cb => {
            const price = FEATURE_PRICES[cb.value] || 0;
            total += parseFloat(price);
        });
        if (totalCostEl) {
            totalCostEl.textContent = '$' + total.toFixed(2);
        }

        // Update submit button
        const submitBtn = $('#submitBtn');
        if (submitBtn) {
            const baseIcon = '<i class="fas fa-paper-plane me-2"></i>';
            if (total > 0) {
                submitBtn.innerHTML = `${baseIcon}Submit Site & Pay $${total.toFixed(2)}`;
            } else {
                submitBtn.innerHTML = `${baseIcon}Submit Site for Review`;
            }
        }
    }

    // Bind listeners
    $$('input[name="promotion_type"]').forEach(r => r.addEventListener('change', ()=>{updateDurationVisibility(); calcTotal();}));
    $$('input[name="promotion_duration"]').forEach(r => r.addEventListener('change', calcTotal));
    $$('input[name="features[]"]').forEach(cb => cb.addEventListener('change', () => {
        updateReferralState(); 
        updateBacklinkState(); 
        calcTotal();
    }));

    // Initial setup
    updateDurationVisibility();
    updateReferralState();
    updateBacklinkState();
    calcTotal();
})();

function copyBacklinkCode(id) {
    const codeEl = document.getElementById(id);
    if (!codeEl) return;

    const text = codeEl.textContent.trim();
    navigator.clipboard.writeText(text).then(() => {
        const btn = event.currentTarget;
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        btn.style.background = '#10b981';

        setTimeout(() => {
            btn.innerHTML = original;
            btn.style.background = '';
        }, 2000);
    });
}
</script>

<?php include 'includes/footer.php'; ?>
