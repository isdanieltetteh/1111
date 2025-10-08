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

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'update_min_rates':
                $minCpcInput = isset($_POST['min_cpc_rate']) ? (float) $_POST['min_cpc_rate'] : 0;
                $minCpmInput = isset($_POST['min_cpm_rate']) ? (float) $_POST['min_cpm_rate'] : 0;

                $minCpc = max(0, round($minCpcInput, 4));
                $minCpm = max(0, round($minCpmInput, 4));

                $savedCpc = number_format($minCpc, 4, '.', '');
                $savedCpm = number_format($minCpm, 4, '.', '');

                $updatedCpc = set_ad_setting($db, 'min_cpc_rate', $savedCpc);
                $updatedCpm = set_ad_setting($db, 'min_cpm_rate', $savedCpm);

                if ($updatedCpc && $updatedCpm) {
                    $success_message = 'Performance campaign minimum bids updated.';
                } else {
                    $error_message = 'Unable to update minimum bid settings. Please try again.';
                }
                break;

            case 'toggle_space':
                $spaceId = (int) ($_POST['space_id'] ?? 0);
                $isEnabled = isset($_POST['is_enabled']) && (int) $_POST['is_enabled'] === 1 ? 1 : 0;

                $toggleStmt = $db->prepare('UPDATE ad_spaces SET is_enabled = :enabled WHERE id = :id');
                $toggleStmt->bindParam(':enabled', $isEnabled, PDO::PARAM_INT);
                $toggleStmt->bindParam(':id', $spaceId, PDO::PARAM_INT);
                $toggleStmt->execute();

                $success_message = $isEnabled ? 'Ad space enabled and ready to serve campaigns.' : 'Ad space disabled successfully.';
                break;

            case 'update_space':
                $spaceId = (int) ($_POST['space_id'] ?? 0);
                $widthInput = trim($_POST['width'] ?? '');
                $heightInput = trim($_POST['height'] ?? '');
                $multiplier = max(0.1, (float) ($_POST['base_price_multiplier'] ?? 1));
                $rotation = max(1, (int) ($_POST['max_ads_rotation'] ?? 1));
                $isPremiumOnly = isset($_POST['is_premium_only']) ? 1 : 0;

                $width = $widthInput !== '' ? max(0, (int) $widthInput) : null;
                $height = $heightInput !== '' ? max(0, (int) $heightInput) : null;

                $updateQuery = 'UPDATE ad_spaces
                                 SET width = :width,
                                     height = :height,
                                     base_price_multiplier = :multiplier,
                                     max_ads_rotation = :rotation,
                                     is_premium_only = :premium_only
                                 WHERE id = :id';
                $updateStmt = $db->prepare($updateQuery);

                if ($width !== null) {
                    $updateStmt->bindValue(':width', $width, PDO::PARAM_INT);
                } else {
                    $updateStmt->bindValue(':width', null, PDO::PARAM_NULL);
                }

                if ($height !== null) {
                    $updateStmt->bindValue(':height', $height, PDO::PARAM_INT);
                } else {
                    $updateStmt->bindValue(':height', null, PDO::PARAM_NULL);
                }

                $updateStmt->bindValue(':multiplier', $multiplier);
                $updateStmt->bindValue(':rotation', $rotation, PDO::PARAM_INT);
                $updateStmt->bindValue(':premium_only', $isPremiumOnly, PDO::PARAM_INT);
                $updateStmt->bindValue(':id', $spaceId, PDO::PARAM_INT);
                $updateStmt->execute();

                $success_message = 'Ad space settings updated.';
                break;
        }
    } catch (Exception $exception) {
        $error_message = 'Unable to process your request: ' . $exception->getMessage();
    }
}

$min_cpc_rate = max(0.0, (float) get_ad_setting($db, 'min_cpc_rate', 0.05));
$min_cpm_rate = max(0.0, (float) get_ad_setting($db, 'min_cpm_rate', 1.00));

$financialExpression = "CASE WHEN ua.campaign_type IN ('cpc','cpm') THEN ua.total_spent ELSE (ua.cost_paid + ua.premium_cost) END";

$spacesQuery = "SELECT
        ads.*,
        COALESCE(SUM(CASE WHEN ua.status = 'active' THEN 1 ELSE 0 END), 0) AS targeted_active,
        COALESCE(SUM(ua.impression_count), 0) AS targeted_impressions,
        COALESCE(SUM(ua.click_count), 0) AS targeted_clicks,
        COALESCE(SUM($financialExpression), 0) AS targeted_spend
    FROM ad_spaces ads
    LEFT JOIN user_advertisements ua
        ON ua.target_space_id = ads.space_id
       AND ua.placement_type = 'targeted'
    GROUP BY ads.id
    ORDER BY ads.page_location, ads.display_order";
$spacesStmt = $db->prepare($spacesQuery);
$spacesStmt->execute();
$ad_spaces = $spacesStmt->fetchAll(PDO::FETCH_ASSOC);

$generalMetricsStmt = $db->query("SELECT
        ad_type,
        COALESCE(target_width, 0) AS width_key,
        COALESCE(target_height, 0) AS height_key,
        COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END), 0) AS active_campaigns,
        COALESCE(SUM(impression_count), 0) AS impressions,
        COALESCE(SUM(click_count), 0) AS clicks,
        COALESCE(SUM($financialExpression), 0) AS spend
    FROM user_advertisements
    WHERE placement_type = 'general'
    GROUP BY ad_type, COALESCE(target_width, 0), COALESCE(target_height, 0)");
$generalMetrics = [];
foreach ($generalMetricsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $key = $row['ad_type'] . ':' . $row['width_key'] . 'x' . $row['height_key'];
    $generalMetrics[$key] = [
        'active_campaigns' => (int) $row['active_campaigns'],
        'impressions' => (int) $row['impressions'],
        'clicks' => (int) $row['clicks'],
        'spend' => (float) $row['spend'],
        'width' => (int) $row['width_key'],
        'height' => (int) $row['height_key'],
        'ad_type' => $row['ad_type'],
    ];
}

$generalCoverageCount = 0;
$inventoryCapacity = 0;
$inventoryUsed = 0;

foreach ($ad_spaces as &$space) {
    $supportedTypes = $space['ad_type'] === 'both' ? ['banner', 'text'] : [$space['ad_type']];
    $widthKey = (int) ($space['width'] ?? 0);
    $heightKey = (int) ($space['height'] ?? 0);

    $generalActive = 0;
    $generalImpressions = 0;
    $generalClicks = 0;
    $generalSpend = 0.0;

    foreach ($supportedTypes as $type) {
        $generalKey = $type . ':' . $widthKey . 'x' . $heightKey;
        if (isset($generalMetrics[$generalKey])) {
            $generalActive += $generalMetrics[$generalKey]['active_campaigns'];
            $generalImpressions += $generalMetrics[$generalKey]['impressions'];
            $generalClicks += $generalMetrics[$generalKey]['clicks'];
            $generalSpend += $generalMetrics[$generalKey]['spend'];
        }
    }

    if ($generalActive > 0) {
        $generalCoverageCount++;
    }

    $space['general_active'] = $generalActive;
    $space['general_impressions'] = $generalImpressions;
    $space['general_clicks'] = $generalClicks;
    $space['general_spend'] = $generalSpend;
    $space['total_impressions'] = (int) $space['targeted_impressions'] + $generalImpressions;
    $space['total_clicks'] = (int) $space['targeted_clicks'] + $generalClicks;
    $space['total_spend'] = (float) $space['targeted_spend'] + $generalSpend;
    $space['ctr'] = $space['total_impressions'] > 0 ? ($space['total_clicks'] / $space['total_impressions']) * 100 : 0;

    $occupied = (int) $space['targeted_active'] + $generalActive;
    $maxRotation = max(0, (int) $space['max_ads_rotation']);
    $space['fill_rate'] = $maxRotation > 0 ? min(100, ($occupied / $maxRotation) * 100) : 0;

    $inventoryCapacity += $maxRotation;
    $inventoryUsed += min($maxRotation, $occupied);
}
unset($space);

$campaignStatsStmt = $db->query("SELECT
        COUNT(*) AS total_campaigns,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_campaigns,
        SUM(CASE WHEN placement_type = 'general' THEN 1 ELSE 0 END) AS general_campaigns,
        SUM(CASE WHEN campaign_type IN ('cpc','cpm') THEN 1 ELSE 0 END) AS performance_campaigns,
        COALESCE(SUM(impression_count), 0) AS impressions,
        COALESCE(SUM(click_count), 0) AS clicks,
        COALESCE(SUM($financialExpression), 0) AS spend,
        COALESCE(SUM(CASE WHEN campaign_type IN ('cpc','cpm') THEN budget_remaining ELSE 0 END), 0) AS remaining_budget
    FROM user_advertisements");
$campaignStats = $campaignStatsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$totalCampaigns = (int) ($campaignStats['total_campaigns'] ?? 0);
$activeCampaigns = (int) ($campaignStats['active_campaigns'] ?? 0);
$generalCampaigns = (int) ($campaignStats['general_campaigns'] ?? 0);
$performanceCampaigns = (int) ($campaignStats['performance_campaigns'] ?? 0);
$totalImpressions = (int) ($campaignStats['impressions'] ?? 0);
$totalClicks = (int) ($campaignStats['clicks'] ?? 0);
$totalSpend = (float) ($campaignStats['spend'] ?? 0);
$remainingBudget = (float) ($campaignStats['remaining_budget'] ?? 0);
$averageCtr = $totalImpressions > 0 ? ($totalClicks / $totalImpressions) * 100 : 0;

$totalSpaces = count($ad_spaces);
$generalCoveragePercent = $totalSpaces > 0 ? ($generalCoverageCount / $totalSpaces) * 100 : 0;
$inventoryUtilisation = $inventoryCapacity > 0 ? ($inventoryUsed / $inventoryCapacity) * 100 : 0;

$topSpaces = $ad_spaces;
usort($topSpaces, static function ($a, $b) {
    return $b['total_clicks'] <=> $a['total_clicks'];
});
$topSpaces = array_slice($topSpaces, 0, 6);
$topSpaceLabels = array_map(static function ($space) {
    return $space['space_name'];
}, $topSpaces);
$topSpaceClicks = array_map(static function ($space) {
    return (int) $space['total_clicks'];
}, $topSpaces);
$topSpaceImpressions = array_map(static function ($space) {
    return (int) $space['total_impressions'];
}, $topSpaces);

$generalBreakdown = array_values($generalMetrics);
usort($generalBreakdown, static function ($a, $b) {
    return $b['impressions'] <=> $a['impressions'];
});
$generalBreakdown = array_slice($generalBreakdown, 0, 5);

function formatDimensionLabel($width, $height) {
    $width = (int) $width;
    $height = (int) $height;

    if ($width === 0 || $height === 0) {
        return 'Responsive Flex';
    }

    return $width . 'x' . $height . 'px';
}

$page_title = 'Ad Placement Control - Admin Panel';
include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex flex-wrap flex-md-nowrap align-items-center justify-content-between pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-0">Ad Placement Control</h1>
                    <p class="text-muted mb-0">Balance inventory, optimise pricing multipliers, and monitor how each placement performs.</p>
                </div>
                <div class="btn-group" role="group">
                    <a href="ad-revenue.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-clipboard-check me-1"></i>Campaigns</a>
                    <a href="ad-spaces-manager.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-border-all me-1"></i>Spaces</a>
                    <a href="ad-analytics.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-chart-pie me-1"></i>Analytics</a>
                </div>
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

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <h5 class="card-title mb-1">Performance Campaign Rate Floors</h5>
                            <p class="text-muted small mb-0">Set the minimum bids allowed for CPC and CPM campaigns to prevent unfair pricing.</p>
                        </div>
                        <span class="badge bg-primary-subtle text-primary"><i class="fas fa-gavel me-1"></i>Bid Controls</span>
                    </div>
                    <form method="post" class="row g-3 align-items-end">
                        <input type="hidden" name="action" value="update_min_rates">
                        <div class="col-md-6">
                            <label class="form-label">Minimum CPC Rate ($)</label>
                            <input type="number" step="0.0001" min="0" name="min_cpc_rate" class="form-control" value="<?php echo htmlspecialchars(number_format($min_cpc_rate, 4, '.', '')); ?>">
                            <small class="text-muted">Applied to every pay-per-click campaign.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Minimum CPM Rate ($)</label>
                            <input type="number" step="0.0001" min="0" name="min_cpm_rate" class="form-control" value="<?php echo htmlspecialchars(number_format($min_cpm_rate, 4, '.', '')); ?>">
                            <small class="text-muted">Charged per 1,000 impressions.</small>
                        </div>
                        <div class="col-12 d-flex justify-content-between align-items-center">
                            <div class="text-muted small"><i class="fas fa-info-circle me-1"></i>Current floors: CPC $<?php echo format_ad_rate($min_cpc_rate); ?> · CPM $<?php echo format_ad_rate($min_cpm_rate); ?>. Higher bids automatically receive more rotation priority.</div>
                            <button type="submit" class="btn btn-primary">Save Minimum Rates</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row mb-4 g-3">
                <div class="col-xl-3 col-md-6">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs fw-bold text-primary text-uppercase mb-1">Active Campaigns</div>
                            <div class="h5 mb-0 fw-bold text-gray-800"><?php echo number_format($activeCampaigns); ?> / <?php echo number_format($totalCampaigns); ?></div>
                            <small class="text-muted">General: <?php echo number_format($generalCampaigns); ?> · Performance: <?php echo number_format($performanceCampaigns); ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs fw-bold text-success text-uppercase mb-1">Delivered Impressions</div>
                            <div class="h5 mb-0 fw-bold text-gray-800"><?php echo number_format($totalImpressions); ?></div>
                            <small class="text-muted">Clicks: <?php echo number_format($totalClicks); ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs fw-bold text-info text-uppercase mb-1">Inventory Health</div>
                            <div class="h5 mb-0 fw-bold text-gray-800"><?php echo number_format($inventoryUtilisation, 1); ?>%</div>
                            <small class="text-muted">General coverage: <?php echo number_format($generalCoveragePercent, 1); ?>%</small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs fw-bold text-warning text-uppercase mb-1">Ad Spend</div>
                            <div class="h5 mb-0 fw-bold text-gray-800">$<?php echo number_format($totalSpend, 2); ?></div>
                            <small class="text-muted">Budget remaining: $<?php echo number_format($remainingBudget, 2); ?> · CTR: <?php echo number_format($averageCtr, 2); ?>%</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-xl-8">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title mb-0">Top Performing Placements</h5>
                                <span class="badge bg-primary bg-opacity-10 text-primary">By clicks</span>
                            </div>
                            <canvas id="topSpacesChart" height="220"></canvas>
                            <?php if (empty($topSpaces)): ?>
                                <p class="text-muted small mb-0 mt-3">No campaign data yet. Approve campaigns to populate performance metrics.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title mb-3">General Rotation Coverage</h5>
                            <p class="text-muted small">Cross-pool campaigns automatically fill matching slots. Track where coverage is strongest.</p>
                            <?php if (!empty($generalBreakdown)): ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($generalBreakdown as $breakdown): ?>
                                        <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="fw-semibold text-secondary text-uppercase small mb-1"><?php echo strtoupper($breakdown['ad_type']); ?> · <?php echo formatDimensionLabel($breakdown['width'], $breakdown['height']); ?></div>
                                                <div class="text-muted small">Active: <?php echo number_format($breakdown['active_campaigns']); ?> · Views: <?php echo number_format($breakdown['impressions']); ?> · Clicks: <?php echo number_format($breakdown['clicks']); ?></div>
                                            </div>
                                            <span class="badge bg-success-subtle text-success">$<?php echo number_format($breakdown['spend'], 2); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted small mb-0">No general rotation campaigns are active right now.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-5">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">Ad Space Inventory</h5>
                        <span class="text-muted small">Manage multipliers, rotation caps, and availability per placement.</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                            <tr>
                                <th scope="col">Placement</th>
                                <th scope="col">Type</th>
                                <th scope="col">Dimensions</th>
                                <th scope="col">Status</th>
                                <th scope="col">Active Campaigns</th>
                                <th scope="col">Performance</th>
                                <th scope="col">Fill</th>
                                <th scope="col">Revenue</th>
                                <th scope="col" class="text-end">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($ad_spaces as $space): ?>
                                <?php
                                $dimensionLabel = formatDimensionLabel($space['width'], $space['height']);
                                $typeBadges = $space['ad_type'] === 'both'
                                    ? '<span class="badge bg-primary-subtle text-primary me-1">Banner</span><span class="badge bg-info-subtle text-info">Text</span>'
                                    : '<span class="badge bg-primary-subtle text-primary">' . ucfirst($space['ad_type']) . '</span>';
                                $statusBadges = [];
                                $statusBadges[] = $space['is_enabled'] ? '<span class="badge bg-success-subtle text-success">Enabled</span>' : '<span class="badge bg-danger-subtle text-danger">Disabled</span>';
                                if (!empty($space['is_premium_only'])) {
                                    $statusBadges[] = '<span class="badge bg-warning-subtle text-warning">Premium Only</span>';
                                }
                                $activeTotal = (int) $space['targeted_active'] + (int) $space['general_active'];
                                $performanceSummary = number_format($space['total_impressions']) . ' views · ' . number_format($space['total_clicks']) . ' clicks';
                                $fillRate = number_format($space['fill_rate'], 1);
                                $ctrValue = number_format($space['ctr'], 2);
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark"><?php echo htmlspecialchars($space['space_name']); ?></div>
                                        <div class="text-muted small">ID: <?php echo htmlspecialchars($space['space_id']); ?> · <?php echo htmlspecialchars($space['page_location']); ?> · <?php echo htmlspecialchars($space['position']); ?></div>
                                    </td>
                                    <td><?php echo $typeBadges; ?></td>
                                    <td>
                                        <div><?php echo $dimensionLabel; ?></div>
                                        <div class="text-muted small">Multiplier: <?php echo number_format($space['base_price_multiplier'], 2); ?>x · Rotation cap: <?php echo (int) $space['max_ads_rotation']; ?></div>
                                    </td>
                                    <td><?php echo implode(' ', $statusBadges); ?></td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?php echo number_format($activeTotal); ?></div>
                                        <div class="text-muted small">Targeted: <?php echo number_format($space['targeted_active']); ?> · General: <?php echo number_format($space['general_active']); ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?php echo $performanceSummary; ?></div>
                                        <div class="text-muted small">CTR: <?php echo $ctrValue; ?>%</div>
                                    </td>
                                    <td style="min-width: 140px;">
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $fillRate; ?>%;" aria-valuenow="<?php echo $fillRate; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <div class="text-muted small mt-1"><?php echo $fillRate; ?>% utilised</div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark">$<?php echo number_format($space['total_spend'], 2); ?></div>
                                        <div class="text-muted small">General: $<?php echo number_format($space['general_spend'], 2); ?></div>
                                    </td>
                                    <td class="text-end">
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="toggle_space">
                                            <input type="hidden" name="space_id" value="<?php echo (int) $space['id']; ?>">
                                            <input type="hidden" name="is_enabled" value="<?php echo $space['is_enabled'] ? 0 : 1; ?>">
                                            <button type="submit" class="btn btn-sm <?php echo $space['is_enabled'] ? 'btn-outline-danger' : 'btn-outline-success'; ?> me-2">
                                                <i class="fas <?php echo $space['is_enabled'] ? 'fa-power-off' : 'fa-play'; ?>"></i>
                                            </button>
                                        </form>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editSpaceModal<?php echo (int) $space['id']; ?>">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php foreach ($ad_spaces as $space): ?>
<div class="modal fade" id="editSpaceModal<?php echo (int) $space['id']; ?>" tabindex="-1" aria-labelledby="editSpaceModalLabel<?php echo (int) $space['id']; ?>" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="update_space">
                <input type="hidden" name="space_id" value="<?php echo (int) $space['id']; ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="editSpaceModalLabel<?php echo (int) $space['id']; ?>">Edit <?php echo htmlspecialchars($space['space_name']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Width (px)</label>
                            <input type="number" min="0" class="form-control" name="width" value="<?php echo htmlspecialchars($space['width']); ?>" placeholder="Leave blank for responsive">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Height (px)</label>
                            <input type="number" min="0" class="form-control" name="height" value="<?php echo htmlspecialchars($space['height']); ?>" placeholder="Leave blank for responsive">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Base Price Multiplier</label>
                            <input type="number" step="0.1" min="0.1" class="form-control" name="base_price_multiplier" value="<?php echo htmlspecialchars($space['base_price_multiplier']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Max Ads in Rotation</label>
                            <input type="number" min="1" class="form-control" name="max_ads_rotation" value="<?php echo htmlspecialchars($space['max_ads_rotation']); ?>">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="premiumOnlyCheck<?php echo (int) $space['id']; ?>" name="is_premium_only" <?php echo !empty($space['is_premium_only']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="premiumOnlyCheck<?php echo (int) $space['id']; ?>">
                                    Restrict placement to premium visibility campaigns only
                                </label>
                            </div>
                            <small class="text-muted">Premium-only slots will ignore standard campaigns but accept CPC/CPM with premium visibility.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const chartElement = document.getElementById('topSpacesChart');
        if (!chartElement) {
            return;
        }

        const labels = <?php echo json_encode($topSpaceLabels); ?>;
        if (!labels.length) {
            chartElement.style.display = 'none';
            return;
        }

        const clicks = <?php echo json_encode($topSpaceClicks); ?>;
        const impressions = <?php echo json_encode($topSpaceImpressions); ?>;

        new Chart(chartElement.getContext('2d'), {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Clicks',
                        data: clicks,
                        backgroundColor: 'rgba(59, 130, 246, 0.7)',
                        borderRadius: 6
                    },
                    {
                        label: 'Impressions',
                        data: impressions,
                        backgroundColor: 'rgba(16, 185, 129, 0.35)',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback(value) {
                                return value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label(context) {
                                return `${context.dataset.label}: ${context.parsed.y.toLocaleString()}`;
                            }
                        }
                    }
                }
            }
        });
    });
</script>

<?php include 'includes/admin_footer.php'; ?>
