<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ad-settings.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();

if (!$auth->isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$min_cpc_rate = max(0.0, (float) get_ad_setting($db, 'min_cpc_rate', 0.05));
$min_cpm_rate = max(0.0, (float) get_ad_setting($db, 'min_cpm_rate', 1.00));

$success_message = '';
$error_message = '';

$form_defaults = [
    'advertiser_lookup' => '',
    'status' => 'pending',
    'charge_credits' => '',
    'ad_type' => 'banner',
    'visibility_level' => 'standard',
    'campaign_type' => 'standard',
    'pricing_id' => '',
    'budget_total' => '',
    'cpc_rate' => '',
    'cpm_rate' => '',
    'placement_type' => 'targeted',
    'ad_space_id' => '',
    'general_dimension' => '',
    'title' => '',
    'target_url' => '',
    'banner_alt_text' => '',
    'text_title' => '',
    'text_description' => ''
];

$form_values = $form_defaults;

$spaces_query = "SELECT * FROM ad_spaces WHERE is_enabled = 1 ORDER BY page_location, display_order";
$spaces_stmt = $db->prepare($spaces_query);
$spaces_stmt->execute();
$ad_spaces = $spaces_stmt->fetchAll(PDO::FETCH_ASSOC);

$general_dimension_map = [];
foreach ($ad_spaces as $space) {
    $types = $space['ad_type'] === 'both' ? ['banner', 'text'] : [$space['ad_type']];
    $width = isset($space['width']) ? (int) $space['width'] : 0;
    $height = isset($space['height']) ? (int) $space['height'] : 0;
    $multiplier = isset($space['base_price_multiplier']) ? (float) $space['base_price_multiplier'] : 1.0;

    foreach ($types as $type) {
        $key = $type . '_' . $width . 'x' . $height;
        if (!isset($general_dimension_map[$key])) {
            $label = ($width && $height) ? $width . 'x' . $height . 'px' : 'Responsive Flex';
            $general_dimension_map[$key] = [
                'ad_type' => $type,
                'width' => $width,
                'height' => $height,
                'multiplier' => $multiplier,
                'label' => $label
            ];
        } else {
            $general_dimension_map[$key]['multiplier'] = max($general_dimension_map[$key]['multiplier'], $multiplier);
        }
    }
}

$general_dimensions = array_values($general_dimension_map);
usort($general_dimensions, function ($a, $b) {
    if ($a['ad_type'] === $b['ad_type']) {
        if ($a['width'] === $b['width']) {
            return $b['height'] <=> $a['height'];
        }
        return $b['width'] <=> $a['width'];
    }
    return strcmp($a['ad_type'], $b['ad_type']);
});

$pricing_stmt = $db->prepare("SELECT * FROM ad_pricing WHERE is_active = 1 ORDER BY ad_type, duration_days");
$pricing_stmt->execute();
$pricing_options = $pricing_stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_campaign') {
    foreach ($form_values as $key => $default) {
        if ($key === 'charge_credits') {
            $form_values[$key] = isset($_POST['charge_credits']) ? '1' : '';
            continue;
        }
        if (isset($_POST[$key])) {
            $value = $_POST[$key];
            if (is_string($value)) {
                $value = trim($value);
            }
            $form_values[$key] = $value;
        }
    }

    $errors = [];
    $user = null;

    $lookup = $form_values['advertiser_lookup'];
    if ($lookup === '') {
        $errors[] = 'Please provide an advertiser user ID, email, or username.';
    } else {
        if (ctype_digit($lookup)) {
            $user_stmt = $db->prepare('SELECT id, username, email, credits FROM users WHERE id = :identifier LIMIT 1');
            $user_stmt->bindParam(':identifier', $lookup, PDO::PARAM_INT);
        } else {
            $user_stmt = $db->prepare('SELECT id, username, email, credits FROM users WHERE email = :identifier OR username = :identifier LIMIT 1');
            $user_stmt->bindParam(':identifier', $lookup);
        }
        $user_stmt->execute();
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            $errors[] = 'Advertiser account not found.';
        }
    }

    $ad_type = in_array($form_values['ad_type'], ['banner', 'text'], true) ? $form_values['ad_type'] : 'banner';
    $visibility_level = in_array($form_values['visibility_level'], ['standard', 'premium'], true) ? $form_values['visibility_level'] : 'standard';
    $campaign_type = in_array($form_values['campaign_type'], ['standard', 'cpc', 'cpm'], true) ? $form_values['campaign_type'] : 'standard';
    $placement_type = in_array($form_values['placement_type'], ['targeted', 'general'], true) ? $form_values['placement_type'] : 'targeted';

    $status = in_array($form_values['status'], ['pending', 'active', 'paused'], true) ? $form_values['status'] : 'pending';

    $title = trim((string) $form_values['title']);
    if ($title === '') {
        $errors[] = 'Campaign title is required.';
    }

    $target_url = trim((string) $form_values['target_url']);
    if (!filter_var($target_url, FILTER_VALIDATE_URL)) {
        $errors[] = 'Please provide a valid target URL.';
    }

    $is_cross_pool = $placement_type === 'general' ? 1 : 0;
    $space_multiplier = 1.0;
    $target_space_id = null;
    $target_width = null;
    $target_height = null;

    if ($placement_type === 'targeted') {
        $selected_space_id = (int) ($form_values['ad_space_id'] ?? 0);
        $selected_space = null;
        foreach ($ad_spaces as $space) {
            if ((int) $space['id'] === $selected_space_id) {
                $selected_space = $space;
                break;
            }
        }

        if (!$selected_space) {
            $errors[] = 'Please select a valid ad space.';
        } else {
            $allowed_types = $selected_space['ad_type'] === 'both' ? ['banner', 'text'] : [$selected_space['ad_type']];
            if (!in_array($ad_type, $allowed_types, true)) {
                $errors[] = 'Selected ad space does not support the chosen ad type.';
            } else {
                $space_multiplier = isset($selected_space['base_price_multiplier']) ? (float) $selected_space['base_price_multiplier'] : 1.0;
                $target_space_id = $selected_space['space_id'];
                if (!empty($selected_space['width'])) {
                    $target_width = (int) $selected_space['width'];
                }
                if (!empty($selected_space['height'])) {
                    $target_height = (int) $selected_space['height'];
                }
            }
        }
    } else {
        $general_choice = $form_values['general_dimension'];
        if ($general_choice === '') {
            $errors[] = 'Select a dimension for general rotation.';
        } else {
            $parts = explode('|', $general_choice);
            if (count($parts) !== 2) {
                $errors[] = 'Invalid general rotation selection.';
            } else {
                [$dimension_type, $dimension_size] = $parts;
                if ($dimension_type !== $ad_type) {
                    $errors[] = 'General rotation option does not match the selected ad type.';
                } else {
                    [$widthValue, $heightValue] = array_pad(explode('x', $dimension_size), 2, '0');
                    $widthValue = (int) $widthValue;
                    $heightValue = (int) $heightValue;
                    $selected_dimension = null;
                    foreach ($general_dimensions as $dimension) {
                        if ($dimension['ad_type'] === $ad_type && (int) $dimension['width'] === $widthValue && (int) $dimension['height'] === $heightValue) {
                            $selected_dimension = $dimension;
                            break;
                        }
                    }
                    if (!$selected_dimension) {
                        $errors[] = 'Selected general rotation size is not available.';
                    } else {
                        $space_multiplier = (float) $selected_dimension['multiplier'];
                        $target_width = $widthValue > 0 ? $widthValue : null;
                        $target_height = $heightValue > 0 ? $heightValue : null;
                    }
                }
            }
        }
    }

    $duration_days = 0;
    $budget_total = (float) $form_values['budget_total'];
    $cpc_rate = (float) $form_values['cpc_rate'];
    $cpm_rate = (float) $form_values['cpm_rate'];

    $base_cost = 0.0;
    $premium_cost = 0.0;
    $total_cost = 0.0;
    $budget_remaining = 0.0;

    if ($campaign_type === 'standard') {
        $pricing_id = (int) $form_values['pricing_id'];
        $pricing = null;
        foreach ($pricing_options as $option) {
            if ((int) $option['id'] === $pricing_id) {
                $pricing = $option;
                break;
            }
        }
        if (!$pricing) {
            $errors[] = 'Please choose a valid duration package.';
        } elseif ($pricing['ad_type'] !== $ad_type) {
            $errors[] = 'Selected pricing package does not match the ad type.';
        } else {
            $duration_days = (int) $pricing['duration_days'];
            $base_cost = (float) $pricing['base_price'] * $space_multiplier;
            if ($visibility_level === 'premium') {
                $premium_multiplier = isset($pricing['premium_multiplier']) ? (float) $pricing['premium_multiplier'] : 1.0;
                $premium_cost = $base_cost * max(0, $premium_multiplier - 1);
            }
            $total_cost = $base_cost + $premium_cost;
        }
    } elseif ($campaign_type === 'cpc') {
        if ($budget_total <= 0) {
            $errors[] = 'Enter a valid CPC budget.';
        }
        if ($cpc_rate < max(0.0, $min_cpc_rate)) {
            $errors[] = 'CPC rate must be at least $' . format_ad_rate($min_cpc_rate) . '.';
        }
        if ($budget_total > 0 && $cpc_rate > $budget_total) {
            $errors[] = 'Budget must be greater than the CPC rate.';
        }
        if (empty($errors) || $campaign_type !== 'cpc') {
            $base_cost = $budget_total;
            $total_cost = $budget_total;
            $budget_remaining = $budget_total;
        }
    } else {
        if ($budget_total <= 0) {
            $errors[] = 'Enter a valid CPM budget.';
        }
        if ($cpm_rate < max(0.0, $min_cpm_rate)) {
            $errors[] = 'CPM rate must be at least $' . format_ad_rate($min_cpm_rate) . '.';
        }
        if (empty($errors) || $campaign_type !== 'cpm') {
            $base_cost = $budget_total;
            $total_cost = $budget_total;
            $budget_remaining = $budget_total;
        }
    }

    $banner_image = null;
    $banner_alt_text = trim((string) $form_values['banner_alt_text']);
    $text_title = trim((string) $form_values['text_title']);
    $text_description = trim((string) $form_values['text_description']);

    if ($ad_type === 'banner') {
        if (!isset($_FILES['banner_image']) || $_FILES['banner_image']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload a banner creative.';
        } else {
            $upload_dir = __DIR__ . '/../uploads/ads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_extension = strtolower(pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($file_extension, $allowed_extensions, true)) {
                $errors[] = 'Invalid image format. Please use JPG, PNG, or GIF.';
            } else {
                $file_name = uniqid('admin_ad_') . '.' . $file_extension;
                $file_path = $upload_dir . $file_name;
                $image_size = @getimagesize($_FILES['banner_image']['tmp_name']);
                if ($image_size) {
                    $actual_width = $image_size[0];
                    $actual_height = $image_size[1];
                    if ($target_width && $actual_width > $target_width) {
                        $errors[] = 'Banner width exceeds the selected placement width.';
                    }
                    if ($target_height && $actual_height > $target_height) {
                        $errors[] = 'Banner height exceeds the selected placement height.';
                    }
                }
                if (empty($errors) && move_uploaded_file($_FILES['banner_image']['tmp_name'], $file_path)) {
                    $banner_image = 'uploads/ads/' . $file_name;
                } elseif (empty($errors)) {
                    $errors[] = 'Unable to save the uploaded banner image.';
                }
            }
        }
        if ($banner_alt_text === '') {
            $errors[] = 'Provide alt text for the banner.';
        }
    } else {
        if ($text_title === '' || $text_description === '') {
            $errors[] = 'Provide both a text ad headline and description.';
        }
    }

    $charge_credits = $form_values['charge_credits'] === '1';
    if ($charge_credits && $user && $total_cost > 0 && $user['credits'] < $total_cost) {
        $errors[] = 'The advertiser does not have enough credits to cover this campaign.';
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            if ($charge_credits && $total_cost > 0) {
                $deduct_query = 'UPDATE users SET credits = credits - :cost WHERE id = :user_id';
                $deduct_stmt = $db->prepare($deduct_query);
                $deduct_stmt->bindParam(':cost', $total_cost);
                $deduct_stmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
                $deduct_stmt->execute();
            }

            $insert_query = "INSERT INTO user_advertisements
                                (user_id, title, ad_type, visibility_level, duration_days, campaign_type, placement_type,
                                 banner_image, banner_alt_text, text_title, text_description,
                                 target_url, cost_paid, premium_cost, target_space_id, target_width, target_height,
                                 budget_total, budget_remaining, cpc_rate, cpm_rate, is_cross_pool, status)
                              VALUES
                                (:user_id, :title, :ad_type, :visibility_level, :duration_days, :campaign_type, :placement_type,
                                 :banner_image, :banner_alt_text, :text_title, :text_description,
                                 :target_url, :cost_paid, :premium_cost, :target_space_id, :target_width, :target_height,
                                 :budget_total, :budget_remaining, :cpc_rate, :cpm_rate, :is_cross_pool, :status)";

            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
            $insert_stmt->bindParam(':title', $title);
            $insert_stmt->bindParam(':ad_type', $ad_type);
            $insert_stmt->bindParam(':visibility_level', $visibility_level);
            $insert_stmt->bindParam(':duration_days', $duration_days, PDO::PARAM_INT);
            $insert_stmt->bindParam(':campaign_type', $campaign_type);
            $insert_stmt->bindParam(':placement_type', $placement_type);
            $insert_stmt->bindParam(':banner_image', $banner_image);
            $insert_stmt->bindParam(':banner_alt_text', $banner_alt_text);
            $insert_stmt->bindParam(':text_title', $text_title);
            $insert_stmt->bindParam(':text_description', $text_description);
            $insert_stmt->bindParam(':target_url', $target_url);
            $insert_stmt->bindParam(':cost_paid', $base_cost);
            $insert_stmt->bindParam(':premium_cost', $premium_cost);
            if ($target_space_id) {
                $insert_stmt->bindParam(':target_space_id', $target_space_id);
            } else {
                $insert_stmt->bindValue(':target_space_id', null, PDO::PARAM_NULL);
            }
            if ($target_width !== null) {
                $insert_stmt->bindParam(':target_width', $target_width, PDO::PARAM_INT);
            } else {
                $insert_stmt->bindValue(':target_width', null, PDO::PARAM_NULL);
            }
            if ($target_height !== null) {
                $insert_stmt->bindParam(':target_height', $target_height, PDO::PARAM_INT);
            } else {
                $insert_stmt->bindValue(':target_height', null, PDO::PARAM_NULL);
            }
            $insert_stmt->bindParam(':budget_total', $budget_total);
            $insert_stmt->bindParam(':budget_remaining', $budget_remaining);
            $insert_stmt->bindParam(':cpc_rate', $cpc_rate);
            $insert_stmt->bindParam(':cpm_rate', $cpm_rate);
            $insert_stmt->bindParam(':is_cross_pool', $is_cross_pool, PDO::PARAM_INT);
            $insert_stmt->bindParam(':status', $status);
            $insert_stmt->execute();

            $ad_id = (int) $db->lastInsertId();

            if ($charge_credits && $total_cost > 0) {
                $transaction_description = '';
                if ($campaign_type === 'standard') {
                    $transaction_description = "Admin-created {$ad_type} ad for {$duration_days} days";
                } elseif ($campaign_type === 'cpc') {
                    $transaction_description = 'Admin-created CPC campaign allocation';
                } else {
                    $transaction_description = 'Admin-created CPM campaign allocation';
                }

                $ad_tx_query = "INSERT INTO ad_transactions (ad_id, user_id, amount, transaction_type, description)
                                VALUES (:ad_id, :user_id, :amount, 'purchase', :description)";
                $ad_tx_stmt = $db->prepare($ad_tx_query);
                $ad_tx_stmt->bindParam(':ad_id', $ad_id, PDO::PARAM_INT);
                $ad_tx_stmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
                $ad_tx_stmt->bindParam(':amount', $total_cost);
                $ad_tx_stmt->bindParam(':description', $transaction_description);
                $ad_tx_stmt->execute();

                $credit_tx_query = "INSERT INTO credit_transactions (user_id, amount, type, description, status)
                                    VALUES (:user_id, :amount, 'spent', :description, 'completed')";
                $credit_tx_stmt = $db->prepare($credit_tx_query);
                $negative_amount = -$total_cost;
                $credit_tx_stmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
                $credit_tx_stmt->bindParam(':amount', $negative_amount);
                $credit_tx_stmt->bindParam(':description', $transaction_description);
                $credit_tx_stmt->execute();
            }

            if ($status === 'active') {
                $start_date = date('Y-m-d H:i:s');
                $end_date = null;
                if ($campaign_type === 'standard' && $duration_days > 0) {
                    $end_date = date('Y-m-d H:i:s', strtotime("+{$duration_days} days"));
                }
                $update_dates = $db->prepare('UPDATE user_advertisements SET start_date = :start_date, end_date = :end_date WHERE id = :ad_id');
                $update_dates->bindParam(':start_date', $start_date);
                if ($end_date !== null) {
                    $update_dates->bindParam(':end_date', $end_date);
                } else {
                    $update_dates->bindValue(':end_date', null, PDO::PARAM_NULL);
                }
                $update_dates->bindParam(':ad_id', $ad_id, PDO::PARAM_INT);
                $update_dates->execute();
            }

            $db->commit();

            $success_message = 'Campaign created successfully.';
            $form_values = $form_defaults;
        } catch (Exception $exception) {
            $db->rollBack();
            $error_message = 'Failed to create campaign: ' . $exception->getMessage();
        }
    } else {
        if (!empty($errors)) {
            $error_message = implode(' ', $errors);
        }
    }
}

$page_title = 'Create Ad Campaign - Admin Panel';
include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex flex-wrap flex-md-nowrap align-items-center justify-content-between pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-0">Create Ad Campaign</h1>
                    <p class="text-muted mb-0">Launch campaigns directly for advertisers. Minimum CPC: $<?php echo format_ad_rate($min_cpc_rate); ?> · Minimum CPM: $<?php echo format_ad_rate($min_cpm_rate); ?>.</p>
                </div>
                <a href="ad-revenue.php" class="btn btn-outline-primary"><i class="fas fa-clipboard-list me-1"></i>Manage Campaigns</a>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="alert alert-info border-start border-3 border-info mb-4">
                <i class="fas fa-info-circle me-2"></i>Performance campaigns rotate based on bid size. Higher bids receive more impressions across available inventory.
            </div>

            <div class="card shadow-sm mb-5">
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data" id="adminCampaignForm" class="row g-4">
                        <input type="hidden" name="action" value="create_campaign">

                        <div class="col-12">
                            <h5 class="text-primary mb-3">Advertiser &amp; Campaign Basics</h5>
                            <div class="row g-3">
                                <div class="col-lg-6">
                                    <label class="form-label">Advertiser Account *</label>
                                    <input type="text" name="advertiser_lookup" class="form-control" value="<?php echo htmlspecialchars($form_values['advertiser_lookup']); ?>" required>
                                    <small class="text-muted">Accepts user ID, username, or email address.</small>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Initial Status *</label>
                                    <select name="status" class="form-select">
                                        <?php foreach (['pending' => 'Pending Approval', 'active' => 'Active Immediately', 'paused' => 'Paused'] as $status_key => $status_label): ?>
                                            <option value="<?php echo $status_key; ?>" <?php echo $form_values['status'] === $status_key ? 'selected' : ''; ?>><?php echo $status_label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="chargeCredits" name="charge_credits" value="1" <?php echo $form_values['charge_credits'] === '1' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="chargeCredits">Charge advertiser credits</label>
                                        <small class="text-muted d-block">Deducts the campaign cost immediately.</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <h5 class="text-primary mb-3">Campaign Configuration</h5>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Ad Type *</label>
                                    <select name="ad_type" id="adTypeSelect" class="form-select">
                                        <option value="banner" <?php echo $form_values['ad_type'] === 'banner' ? 'selected' : ''; ?>>Banner</option>
                                        <option value="text" <?php echo $form_values['ad_type'] === 'text' ? 'selected' : ''; ?>>Text</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Visibility *</label>
                                    <select name="visibility_level" id="visibilitySelect" class="form-select">
                                        <option value="standard" <?php echo $form_values['visibility_level'] === 'standard' ? 'selected' : ''; ?>>Standard</option>
                                        <option value="premium" <?php echo $form_values['visibility_level'] === 'premium' ? 'selected' : ''; ?>>Premium</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Campaign Type *</label>
                                    <select name="campaign_type" id="campaignTypeSelect" class="form-select">
                                        <option value="standard" <?php echo $form_values['campaign_type'] === 'standard' ? 'selected' : ''; ?>>Standard (Duration)</option>
                                        <option value="cpc" <?php echo $form_values['campaign_type'] === 'cpc' ? 'selected' : ''; ?>>CPC - Pay Per Click</option>
                                        <option value="cpm" <?php echo $form_values['campaign_type'] === 'cpm' ? 'selected' : ''; ?>>CPM - Per 1,000 Impressions</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Placement *</label>
                                    <select name="placement_type" id="placementTypeSelect" class="form-select">
                                        <option value="targeted" <?php echo $form_values['placement_type'] === 'targeted' ? 'selected' : ''; ?>>Target Specific Space</option>
                                        <option value="general" <?php echo $form_values['placement_type'] === 'general' ? 'selected' : ''; ?>>General Rotation</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-12" id="standardPricingSection">
                            <label class="form-label">Duration Package *</label>
                            <select name="pricing_id" id="pricingSelect" class="form-select">
                                <option value="">Select duration...</option>
                                <?php foreach ($pricing_options as $pricing): ?>
                                    <option value="<?php echo $pricing['id']; ?>"
                                            data-ad-type="<?php echo $pricing['ad_type']; ?>"
                                            data-price="<?php echo $pricing['base_price']; ?>"
                                            data-premium="<?php echo $pricing['premium_multiplier']; ?>"
                                        <?php echo (string) $form_values['pricing_id'] === (string) $pricing['id'] ? 'selected' : ''; ?>>
                                        <?php echo strtoupper($pricing['ad_type']); ?> · <?php echo $pricing['duration_days']; ?> days — $<?php echo number_format($pricing['base_price'], 2); ?> base
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 d-none" id="budgetField">
                            <label class="form-label">Campaign Budget *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" min="0" class="form-control" name="budget_total" id="budgetInput" value="<?php echo htmlspecialchars($form_values['budget_total']); ?>">
                            </div>
                            <small class="text-muted">Total credits reserved for this campaign.</small>
                        </div>
                        <div class="col-md-3 d-none" id="cpcRateField">
                            <label class="form-label">CPC Rate *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.0001" min="<?php echo number_format($min_cpc_rate, 4, '.', ''); ?>" data-min="<?php echo number_format($min_cpc_rate, 4, '.', ''); ?>" class="form-control" name="cpc_rate" id="cpcRateInput" value="<?php echo htmlspecialchars($form_values['cpc_rate']); ?>">
                            </div>
                            <small class="text-muted">Minimum $<?php echo format_ad_rate($min_cpc_rate); ?> per click. Higher bids increase exposure.</small>
                        </div>
                        <div class="col-md-3 d-none" id="cpmRateField">
                            <label class="form-label">CPM Rate *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.0001" min="<?php echo number_format($min_cpm_rate, 4, '.', ''); ?>" data-min="<?php echo number_format($min_cpm_rate, 4, '.', ''); ?>" class="form-control" name="cpm_rate" id="cpmRateInput" value="<?php echo htmlspecialchars($form_values['cpm_rate']); ?>">
                            </div>
                            <small class="text-muted">Minimum $<?php echo format_ad_rate($min_cpm_rate); ?> per 1,000 impressions. Higher bids gain more rotation weight.</small>
                        </div>

                        <div class="col-md-6" id="targetedFields">
                            <label class="form-label">Ad Space *</label>
                            <select name="ad_space_id" id="adSpaceSelect" class="form-select">
                                <option value="">Select space...</option>
                                <?php foreach ($ad_spaces as $space): ?>
                                    <option value="<?php echo $space['id']; ?>"
                                            data-ad-type="<?php echo $space['ad_type']; ?>"
                                            data-multiplier="<?php echo $space['base_price_multiplier']; ?>"
                                            data-width="<?php echo $space['width']; ?>"
                                            data-height="<?php echo $space['height']; ?>"
                                        <?php echo (string) $form_values['ad_space_id'] === (string) $space['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($space['space_name']); ?> (<?php echo htmlspecialchars($space['page_location']); ?>)
                                        <?php if ($space['width'] && $space['height']): ?> - <?php echo $space['width']; ?>x<?php echo $space['height']; ?><?php endif; ?>
                                        <?php if ($space['base_price_multiplier'] != 1.0): ?> · <?php echo $space['base_price_multiplier']; ?>x multiplier<?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Select the exact placement for targeted campaigns.</small>
                        </div>

                        <div class="col-md-6 d-none" id="generalFields">
                            <label class="form-label">General Rotation Size *</label>
                            <select name="general_dimension" id="generalDimensionSelect" class="form-select">
                                <option value="">Select dimensions...</option>
                                <?php foreach ($general_dimensions as $dimension): ?>
                                    <?php $value = $dimension['ad_type'] . '|' . $dimension['width'] . 'x' . $dimension['height']; ?>
                                    <option value="<?php echo $value; ?>"
                                            data-ad-type="<?php echo $dimension['ad_type']; ?>"
                                            data-multiplier="<?php echo $dimension['multiplier']; ?>"
                                        <?php echo $form_values['general_dimension'] === $value ? 'selected' : ''; ?>>
                                        <?php echo strtoupper($dimension['ad_type']); ?> — <?php echo $dimension['label']; ?> (<?php echo $dimension['multiplier']; ?>x)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Ad will rotate through every matching placement.</small>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Campaign Title *</label>
                            <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($form_values['title']); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Target URL *</label>
                            <input type="url" name="target_url" class="form-control" value="<?php echo htmlspecialchars($form_values['target_url']); ?>" required>
                        </div>

                        <div class="col-12" id="bannerFields">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Banner Image *</label>
                                    <input type="file" name="banner_image" class="form-control" accept="image/*">
                                    <small class="text-muted" id="bannerSizeHelp">Upload JPG, PNG, or GIF (max 2MB).</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Alt Text *</label>
                                    <input type="text" name="banner_alt_text" class="form-control" value="<?php echo htmlspecialchars($form_values['banner_alt_text']); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="col-12 d-none" id="textFields">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Headline *</label>
                                    <input type="text" name="text_title" class="form-control" value="<?php echo htmlspecialchars($form_values['text_title']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Description *</label>
                                    <textarea name="text_description" class="form-control" rows="3"><?php echo htmlspecialchars($form_values['text_description']); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="card bg-light border-0">
                                <div class="card-body">
                                    <h6 class="fw-semibold mb-2">Cost Preview</h6>
                                    <p class="mb-1">Base Cost: <span id="summaryBaseCost">$0.00</span></p>
                                    <p class="mb-1">Premium Uplift: <span id="summaryPremiumCost">$0.00</span></p>
                                    <p class="mb-0">Total Allocation: <strong id="summaryTotalCost">$0.00</strong></p>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 d-flex justify-content-between align-items-center">
                            <span class="text-muted"><i class="fas fa-shield-check me-2"></i>Campaigns remain pending until approved unless activated immediately.</span>
                            <button type="submit" class="btn btn-primary">Create Campaign</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const adTypeSelect = document.getElementById('adTypeSelect');
        const visibilitySelect = document.getElementById('visibilitySelect');
        const campaignTypeSelect = document.getElementById('campaignTypeSelect');
        const placementTypeSelect = document.getElementById('placementTypeSelect');
        const pricingSelect = document.getElementById('pricingSelect');
        const budgetField = document.getElementById('budgetField');
        const cpcRateField = document.getElementById('cpcRateField');
        const cpmRateField = document.getElementById('cpmRateField');
        const budgetInput = document.getElementById('budgetInput');
        const cpcRateInput = document.getElementById('cpcRateInput');
        const cpmRateInput = document.getElementById('cpmRateInput');
        const adSpaceSelect = document.getElementById('adSpaceSelect');
        const generalDimensionSelect = document.getElementById('generalDimensionSelect');
        const targetedFields = document.getElementById('targetedFields');
        const generalFields = document.getElementById('generalFields');
        const bannerFields = document.getElementById('bannerFields');
        const textFields = document.getElementById('textFields');
        const bannerSizeHelp = document.getElementById('bannerSizeHelp');
        const summaryBaseCost = document.getElementById('summaryBaseCost');
        const summaryPremiumCost = document.getElementById('summaryPremiumCost');
        const summaryTotalCost = document.getElementById('summaryTotalCost');

        const formatRateDisplay = (value) => {
            const numeric = Number(value || 0);
            const fixed = numeric.toFixed(4);
            return fixed.replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '');
        };

        function getSelectedMultiplier() {
            let option = null;
            if (placementTypeSelect.value === 'general') {
                option = generalDimensionSelect ? generalDimensionSelect.selectedOptions[0] : null;
            } else {
                option = adSpaceSelect ? adSpaceSelect.selectedOptions[0] : null;
            }
            return option ? parseFloat(option.dataset.multiplier || '1') : 1;
        }

        function updateBannerHelper(option) {
            if (!bannerSizeHelp) {
                return;
            }
            if (placementTypeSelect.value === 'targeted' && option) {
                const width = option.dataset.width;
                const height = option.dataset.height;
                if (width && height) {
                    bannerSizeHelp.textContent = `Recommended size: ${width}x${height}px. Max 2MB.`;
                    return;
                }
            }
            bannerSizeHelp.textContent = 'Upload JPG, PNG, or GIF (max 2MB).';
        }

        function filterPlacementOptions() {
            const selectedType = adTypeSelect.value;
            Array.from(adSpaceSelect.options).forEach((option, index) => {
                if (index === 0) {
                    return;
                }
                const optionType = option.dataset.adType;
                const isAllowed = optionType === 'both' || optionType === selectedType;
                option.hidden = !isAllowed;
                if (!isAllowed && option.selected) {
                    option.selected = false;
                }
            });

            Array.from(generalDimensionSelect.options).forEach((option, index) => {
                if (index === 0) {
                    return;
                }
                const optionType = option.dataset.adType;
                const isAllowed = optionType === selectedType;
                option.hidden = !isAllowed;
                if (!isAllowed && option.selected) {
                    option.selected = false;
                }
            });
        }

        function toggleCreativeFields() {
            const isBanner = adTypeSelect.value === 'banner';
            bannerFields.classList.toggle('d-none', !isBanner);
            textFields.classList.toggle('d-none', isBanner);
        }

        function togglePlacementFields() {
            const isGeneral = placementTypeSelect.value === 'general';
            targetedFields.classList.toggle('d-none', isGeneral);
            generalFields.classList.toggle('d-none', !isGeneral);

            const option = isGeneral ? (generalDimensionSelect ? generalDimensionSelect.selectedOptions[0] : null)
                                     : (adSpaceSelect ? adSpaceSelect.selectedOptions[0] : null);
            updateBannerHelper(option);
        }

        function toggleCampaignTypeFields() {
            const type = campaignTypeSelect.value;
            const isStandard = type === 'standard';

            document.getElementById('standardPricingSection').classList.toggle('d-none', !isStandard);
            budgetField.classList.toggle('d-none', isStandard);
            cpcRateField.classList.toggle('d-none', type !== 'cpc');
            cpmRateField.classList.toggle('d-none', type !== 'cpm');
        }

        function updateSummary() {
            const multiplier = getSelectedMultiplier();
            let baseCost = 0;
            let premiumCost = 0;
            let totalCost = 0;

            if (campaignTypeSelect.value === 'standard') {
                const selectedOption = pricingSelect.selectedOptions[0];
                const basePrice = selectedOption ? parseFloat(selectedOption.dataset.price || '0') : 0;
                const premiumMultiplier = selectedOption ? parseFloat(selectedOption.dataset.premium || '1') : 1;
                baseCost = basePrice * multiplier;
                if (visibilitySelect.value === 'premium') {
                    premiumCost = baseCost * Math.max(0, premiumMultiplier - 1);
                }
                totalCost = baseCost + premiumCost;
            } else {
                const budget = parseFloat(budgetInput.value || '0');
                baseCost = budget;
                totalCost = budget;
            }

            summaryBaseCost.textContent = '$' + baseCost.toFixed(2);
            summaryPremiumCost.textContent = '$' + premiumCost.toFixed(2);
            summaryTotalCost.textContent = '$' + totalCost.toFixed(2);
        }

        function ensureRateMinimums(event) {
            const input = event.target;
            const minimum = parseFloat(input.dataset.min || '0');
            if (!Number.isNaN(minimum) && parseFloat(input.value || '0') < minimum) {
                input.value = formatRateDisplay(minimum);
            }
        }

        adTypeSelect.addEventListener('change', () => {
            filterPlacementOptions();
            toggleCreativeFields();
            togglePlacementFields();
            updateSummary();
        });

        visibilitySelect.addEventListener('change', updateSummary);
        campaignTypeSelect.addEventListener('change', () => {
            toggleCampaignTypeFields();
            updateSummary();
        });
        placementTypeSelect.addEventListener('change', () => {
            togglePlacementFields();
            updateSummary();
        });

        if (pricingSelect) {
            pricingSelect.addEventListener('change', updateSummary);
        }
        if (budgetInput) {
            budgetInput.addEventListener('input', updateSummary);
        }
        if (cpcRateInput) {
            cpcRateInput.addEventListener('change', ensureRateMinimums);
            cpcRateInput.addEventListener('input', updateSummary);
        }
        if (cpmRateInput) {
            cpmRateInput.addEventListener('change', ensureRateMinimums);
            cpmRateInput.addEventListener('input', updateSummary);
        }
        if (adSpaceSelect) {
            adSpaceSelect.addEventListener('change', () => {
                const option = adSpaceSelect.selectedOptions[0];
                updateBannerHelper(option);
                updateSummary();
            });
        }
        if (generalDimensionSelect) {
            generalDimensionSelect.addEventListener('change', () => {
                const option = generalDimensionSelect.selectedOptions[0];
                if (placementTypeSelect.value === 'general') {
                    updateBannerHelper(option);
                }
                updateSummary();
            });
        }

        filterPlacementOptions();
        toggleCreativeFields();
        toggleCampaignTypeFields();
        togglePlacementFields();
        updateSummary();
    });
</script>

<?php include 'includes/admin_footer.php'; ?>
