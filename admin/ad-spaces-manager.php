<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();

if (!$auth->isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$success_message = '';
$error_message = '';

function sanitizeText(?string $value): string
{
    return trim((string) $value);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'create_space':
                $spaceId = sanitizeText($_POST['space_id'] ?? '');
                $spaceName = sanitizeText($_POST['space_name'] ?? '');
                $pageLocation = sanitizeText($_POST['page_location'] ?? '');
                $position = sanitizeText($_POST['position'] ?? '');
                $adType = $_POST['ad_type'] ?? 'both';
                $widthInput = sanitizeText($_POST['width'] ?? '');
                $heightInput = sanitizeText($_POST['height'] ?? '');
                $multiplier = max(0.1, (float) ($_POST['base_price_multiplier'] ?? 1));
                $isPremiumOnly = isset($_POST['is_premium_only']) ? 1 : 0;
                $displayOrder = (int) ($_POST['display_order'] ?? 0);
                $rotation = max(1, (int) ($_POST['max_ads_rotation'] ?? 5));

                if ($spaceId === '' || $spaceName === '' || $pageLocation === '' || $position === '') {
                    throw new InvalidArgumentException('Please complete all required fields.');
                }

                if (!in_array($adType, ['banner', 'text', 'both'], true)) {
                    $adType = 'both';
                }

                $checkStmt = $db->prepare('SELECT COUNT(*) FROM ad_spaces WHERE space_id = :space_id');
                $checkStmt->bindParam(':space_id', $spaceId);
                $checkStmt->execute();
                if ($checkStmt->fetchColumn() > 0) {
                    throw new RuntimeException('Space ID already exists. Choose a unique identifier.');
                }

                $width = $widthInput !== '' ? max(0, (int) $widthInput) : null;
                $height = $heightInput !== '' ? max(0, (int) $heightInput) : null;

                $insertQuery = 'INSERT INTO ad_spaces
                    (space_id, space_name, page_location, position, ad_type, width, height, base_price_multiplier, is_enabled, is_premium_only, display_order, max_ads_rotation)
                    VALUES
                    (:space_id, :space_name, :page_location, :position, :ad_type, :width, :height, :multiplier, 1, :premium_only, :display_order, :rotation)';

                $insertStmt = $db->prepare($insertQuery);
                $insertStmt->bindParam(':space_id', $spaceId);
                $insertStmt->bindParam(':space_name', $spaceName);
                $insertStmt->bindParam(':page_location', $pageLocation);
                $insertStmt->bindParam(':position', $position);
                $insertStmt->bindParam(':ad_type', $adType);

                if ($width !== null) {
                    $insertStmt->bindValue(':width', $width, PDO::PARAM_INT);
                } else {
                    $insertStmt->bindValue(':width', null, PDO::PARAM_NULL);
                }

                if ($height !== null) {
                    $insertStmt->bindValue(':height', $height, PDO::PARAM_INT);
                } else {
                    $insertStmt->bindValue(':height', null, PDO::PARAM_NULL);
                }

                $insertStmt->bindValue(':multiplier', $multiplier);
                $insertStmt->bindValue(':premium_only', $isPremiumOnly, PDO::PARAM_INT);
                $insertStmt->bindValue(':display_order', $displayOrder, PDO::PARAM_INT);
                $insertStmt->bindValue(':rotation', $rotation, PDO::PARAM_INT);
                $insertStmt->execute();

                $success_message = 'Ad space created successfully.';
                break;

            case 'toggle_space':
                $spaceId = (int) ($_POST['space_id'] ?? 0);
                $isEnabled = isset($_POST['is_enabled']) && (int) $_POST['is_enabled'] === 1 ? 1 : 0;

                $toggleStmt = $db->prepare('UPDATE ad_spaces SET is_enabled = :enabled WHERE id = :id');
                $toggleStmt->bindParam(':enabled', $isEnabled, PDO::PARAM_INT);
                $toggleStmt->bindParam(':id', $spaceId, PDO::PARAM_INT);
                $toggleStmt->execute();

                $success_message = $isEnabled ? 'Ad space activated.' : 'Ad space disabled.';
                break;

            case 'update_space':
                $spaceId = (int) ($_POST['space_id'] ?? 0);
                $spaceName = sanitizeText($_POST['space_name'] ?? '');
                $pageLocation = sanitizeText($_POST['page_location'] ?? '');
                $position = sanitizeText($_POST['position'] ?? '');
                $adType = $_POST['ad_type'] ?? 'both';
                $widthInput = sanitizeText($_POST['width'] ?? '');
                $heightInput = sanitizeText($_POST['height'] ?? '');
                $multiplier = max(0.1, (float) ($_POST['base_price_multiplier'] ?? 1));
                $isPremiumOnly = isset($_POST['is_premium_only']) ? 1 : 0;
                $displayOrder = (int) ($_POST['display_order'] ?? 0);
                $rotation = max(1, (int) ($_POST['max_ads_rotation'] ?? 5));

                if (!in_array($adType, ['banner', 'text', 'both'], true)) {
                    $adType = 'both';
                }

                $width = $widthInput !== '' ? max(0, (int) $widthInput) : null;
                $height = $heightInput !== '' ? max(0, (int) $heightInput) : null;

                $updateQuery = 'UPDATE ad_spaces
                    SET space_name = :space_name,
                        page_location = :page_location,
                        position = :position,
                        ad_type = :ad_type,
                        width = :width,
                        height = :height,
                        base_price_multiplier = :multiplier,
                        is_premium_only = :premium_only,
                        display_order = :display_order,
                        max_ads_rotation = :rotation
                    WHERE id = :id';

                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->bindParam(':space_name', $spaceName);
                $updateStmt->bindParam(':page_location', $pageLocation);
                $updateStmt->bindParam(':position', $position);
                $updateStmt->bindParam(':ad_type', $adType);

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
                $updateStmt->bindValue(':premium_only', $isPremiumOnly, PDO::PARAM_INT);
                $updateStmt->bindValue(':display_order', $displayOrder, PDO::PARAM_INT);
                $updateStmt->bindValue(':rotation', $rotation, PDO::PARAM_INT);
                $updateStmt->bindValue(':id', $spaceId, PDO::PARAM_INT);
                $updateStmt->execute();

                $success_message = 'Ad space updated successfully.';
                break;
        }
    } catch (Exception $exception) {
        $error_message = 'Action failed: ' . $exception->getMessage();
    }
}

$financialExpression = "CASE WHEN ua.campaign_type IN ('cpc','cpm') THEN ua.total_spent ELSE (ua.cost_paid + ua.premium_cost) END";

$spacesQuery = "SELECT
        ads.*,
        COALESCE(SUM(CASE WHEN ua.status IN ('active','paused') THEN 1 ELSE 0 END), 0) AS campaign_count,
        COALESCE(SUM(ua.impression_count), 0) AS impressions,
        COALESCE(SUM(ua.click_count), 0) AS clicks,
        COALESCE(SUM($financialExpression), 0) AS spend
    FROM ad_spaces ads
    LEFT JOIN user_advertisements ua
        ON ua.target_space_id = ads.space_id
       AND ua.placement_type = 'targeted'
    GROUP BY ads.id
    ORDER BY ads.page_location, ads.display_order";
$spacesStmt = $db->prepare($spacesQuery);
$spacesStmt->execute();
$ad_spaces = $spacesStmt->fetchAll(PDO::FETCH_ASSOC);

$totalSpaces = count($ad_spaces);
$enabledSpaces = count(array_filter($ad_spaces, static fn($space) => (int) $space['is_enabled'] === 1));
$premiumOnlySpaces = count(array_filter($ad_spaces, static fn($space) => (int) $space['is_premium_only'] === 1));
$totalRotation = array_sum(array_map(static fn($space) => (int) $space['max_ads_rotation'], $ad_spaces));
$averageMultiplier = $totalSpaces > 0 ? array_sum(array_map(static fn($space) => (float) $space['base_price_multiplier'], $ad_spaces)) / $totalSpaces : 0;

$pageLocations = array_unique(array_map(static fn($space) => $space['page_location'], $ad_spaces));
$positions = array_unique(array_map(static fn($space) => $space['position'], $ad_spaces));
$adTypeCounts = [
    'banner' => count(array_filter($ad_spaces, static fn($space) => $space['ad_type'] === 'banner')),
    'text' => count(array_filter($ad_spaces, static fn($space) => $space['ad_type'] === 'text')),
    'both' => count(array_filter($ad_spaces, static fn($space) => $space['ad_type'] === 'both')),
];

$pageSummary = [];
foreach ($ad_spaces as $space) {
    $pageSummary[$space['page_location']]['count'] = ($pageSummary[$space['page_location']]['count'] ?? 0) + 1;
    $pageSummary[$space['page_location']]['campaigns'] = ($pageSummary[$space['page_location']]['campaigns'] ?? 0) + (int) $space['campaign_count'];
}
ksort($pageSummary);

function dimensionLabel($width, $height): string
{
    $width = (int) $width;
    $height = (int) $height;

    if ($width === 0 || $height === 0) {
        return 'Responsive Flex';
    }

    return $width . 'x' . $height . 'px';
}

$page_title = 'Ad Spaces Manager - Admin Panel';
include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex flex-wrap flex-md-nowrap align-items-center justify-content-between pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-0">Ad Spaces Manager</h1>
                    <p class="text-muted mb-0">Define where ads appear, their dimensions, and how they are prioritised across the site.</p>
                </div>
                <div class="btn-group" role="group">
                    <a href="ad-revenue.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-clipboard-check me-1"></i>Campaigns</a>
                    <a href="ad-control.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-sliders-h me-1"></i>Placement Control</a>
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

            <div class="row mb-4 g-3">
                <div class="col-xl-3 col-md-6">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs fw-bold text-primary text-uppercase mb-1">Total Spaces</div>
                            <div class="h5 mb-0 fw-bold text-gray-800"><?php echo number_format($totalSpaces); ?></div>
                            <small class="text-muted">Enabled: <?php echo number_format($enabledSpaces); ?> · Premium: <?php echo number_format($premiumOnlySpaces); ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs fw-bold text-success text-uppercase mb-1">Average Multiplier</div>
                            <div class="h5 mb-0 fw-bold text-gray-800"><?php echo number_format($averageMultiplier, 2); ?>x</div>
                            <small class="text-muted">Rotation capacity: <?php echo number_format($totalRotation); ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs fw-bold text-info text-uppercase mb-1">Placement Types</div>
                            <div class="h5 mb-0 fw-bold text-gray-800">Banner <?php echo $adTypeCounts['banner']; ?> · Text <?php echo $adTypeCounts['text']; ?></div>
                            <small class="text-muted">Hybrid placements: <?php echo $adTypeCounts['both']; ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs fw-bold text-warning text-uppercase mb-1">Page Coverage</div>
                            <div class="h5 mb-0 fw-bold text-gray-800"><?php echo number_format(count($pageLocations)); ?> pages</div>
                            <small class="text-muted">Campaign links: <?php echo array_sum(array_column($pageSummary, 'campaigns')); ?></small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">Create New Ad Space</h5>
                    <form method="post" class="row g-3">
                        <input type="hidden" name="action" value="create_space">
                        <div class="col-md-4">
                            <label class="form-label">Space ID *</label>
                            <input type="text" class="form-control" name="space_id" placeholder="homepage_top_banner" required>
                            <div class="form-text">Used in templates and widgets.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Space Name *</label>
                            <input type="text" class="form-control" name="space_name" placeholder="Homepage Top Banner" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ad Type *</label>
                            <select name="ad_type" class="form-select">
                                <option value="both">Banner &amp; Text</option>
                                <option value="banner">Banner Only</option>
                                <option value="text">Text Only</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Page Location *</label>
                            <input list="pageLocationsList" class="form-control" name="page_location" placeholder="dashboard" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Position *</label>
                            <input list="positionList" class="form-control" name="position" placeholder="top" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Base Price Multiplier</label>
                            <input type="number" step="0.1" min="0.1" class="form-control" name="base_price_multiplier" value="1.0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Width (px)</label>
                            <input type="number" min="0" class="form-control" name="width" placeholder="728">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Height (px)</label>
                            <input type="number" min="0" class="form-control" name="height" placeholder="90">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Display Order</label>
                            <input type="number" class="form-control" name="display_order" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Max Ads Rotation</label>
                            <input type="number" min="1" class="form-control" name="max_ads_rotation" value="5">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_premium_only" id="createPremiumOnly">
                                <label class="form-check-label" for="createPremiumOnly">Restrict to premium visibility campaigns</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Create Space</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-lg-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Page Coverage Overview</h5>
                            <div class="list-group list-group-flush">
                                <?php foreach ($pageSummary as $location => $summary): ?>
                                    <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-semibold text-secondary text-uppercase small mb-1"><?php echo htmlspecialchars($location); ?></div>
                                            <div class="text-muted small">Spaces: <?php echo number_format($summary['count']); ?> · Campaign links: <?php echo number_format($summary['campaigns']); ?></div>
                                        </div>
                                        <span class="badge bg-primary-subtle text-primary"><?php echo number_format($summary['count']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Helpful Tips</h5>
                            <ul class="list-unstyled text-muted small mb-0">
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Use responsive dimensions (leave width/height blank) to host flexible widgets.</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Premium-only slots pair well with CPC/CPM campaigns using higher bids.</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Balance rotation caps to keep exposure fair across targeted and general campaigns.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-5">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">Existing Ad Spaces</h5>
                        <span class="text-muted small">Click edit to adjust placement settings.</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                            <tr>
                                <th scope="col">Space</th>
                                <th scope="col">Type</th>
                                <th scope="col">Dimensions</th>
                                <th scope="col">Multiplier</th>
                                <th scope="col">Campaigns</th>
                                <th scope="col">Performance</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="text-end">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($ad_spaces as $space): ?>
                                <?php
                                $typeBadge = $space['ad_type'] === 'both'
                                    ? '<span class="badge bg-primary-subtle text-primary me-1">Banner</span><span class="badge bg-info-subtle text-info">Text</span>'
                                    : '<span class="badge bg-primary-subtle text-primary">' . ucfirst($space['ad_type']) . '</span>';
                                $statusBadges = [];
                                $statusBadges[] = $space['is_enabled'] ? '<span class="badge bg-success-subtle text-success">Enabled</span>' : '<span class="badge bg-danger-subtle text-danger">Disabled</span>';
                                if ((int) $space['is_premium_only'] === 1) {
                                    $statusBadges[] = '<span class="badge bg-warning-subtle text-warning">Premium</span>';
                                }
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark"><?php echo htmlspecialchars($space['space_name']); ?></div>
                                        <div class="text-muted small">ID: <?php echo htmlspecialchars($space['space_id']); ?> · <?php echo htmlspecialchars($space['page_location']); ?> · <?php echo htmlspecialchars($space['position']); ?></div>
                                    </td>
                                    <td><?php echo $typeBadge; ?></td>
                                    <td><?php echo dimensionLabel($space['width'], $space['height']); ?></td>
                                    <td><?php echo number_format($space['base_price_multiplier'], 2); ?>x<br><small class="text-muted">Rotation: <?php echo (int) $space['max_ads_rotation']; ?> · Order: <?php echo (int) $space['display_order']; ?></small></td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?php echo number_format($space['campaign_count']); ?></div>
                                        <div class="text-muted small">Spend: $<?php echo number_format($space['spend'], 2); ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?php echo number_format($space['impressions']); ?> views</div>
                                        <div class="text-muted small">Clicks: <?php echo number_format($space['clicks']); ?></div>
                                    </td>
                                    <td><?php echo implode(' ', $statusBadges); ?></td>
                                    <td class="text-end">
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="toggle_space">
                                            <input type="hidden" name="space_id" value="<?php echo (int) $space['id']; ?>">
                                            <input type="hidden" name="is_enabled" value="<?php echo $space['is_enabled'] ? 0 : 1; ?>">
                                            <button type="submit" class="btn btn-sm <?php echo $space['is_enabled'] ? 'btn-outline-danger' : 'btn-outline-success'; ?> me-2"><i class="fas <?php echo $space['is_enabled'] ? 'fa-power-off' : 'fa-play'; ?>"></i></button>
                                        </form>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#spaceEditModal<?php echo (int) $space['id']; ?>"><i class="fas fa-pen"></i></button>
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

<datalist id="pageLocationsList">
    <?php foreach ($pageLocations as $location): ?>
        <option value="<?php echo htmlspecialchars($location); ?>">
    <?php endforeach; ?>
</datalist>
<datalist id="positionList">
    <?php foreach ($positions as $position): ?>
        <option value="<?php echo htmlspecialchars($position); ?>">
    <?php endforeach; ?>
</datalist>

<?php foreach ($ad_spaces as $space): ?>
<div class="modal fade" id="spaceEditModal<?php echo (int) $space['id']; ?>" tabindex="-1" aria-labelledby="spaceEditLabel<?php echo (int) $space['id']; ?>" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="update_space">
                <input type="hidden" name="space_id" value="<?php echo (int) $space['id']; ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="spaceEditLabel<?php echo (int) $space['id']; ?>">Edit <?php echo htmlspecialchars($space['space_name']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Space Name</label>
                            <input type="text" class="form-control" name="space_name" value="<?php echo htmlspecialchars($space['space_name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ad Type</label>
                            <select name="ad_type" class="form-select">
                                <option value="both" <?php echo $space['ad_type'] === 'both' ? 'selected' : ''; ?>>Banner &amp; Text</option>
                                <option value="banner" <?php echo $space['ad_type'] === 'banner' ? 'selected' : ''; ?>>Banner Only</option>
                                <option value="text" <?php echo $space['ad_type'] === 'text' ? 'selected' : ''; ?>>Text Only</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Page Location</label>
                            <input type="text" class="form-control" name="page_location" value="<?php echo htmlspecialchars($space['page_location']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Position</label>
                            <input type="text" class="form-control" name="position" value="<?php echo htmlspecialchars($space['position']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Width (px)</label>
                            <input type="number" min="0" class="form-control" name="width" value="<?php echo htmlspecialchars($space['width']); ?>" placeholder="Leave blank for responsive">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Height (px)</label>
                            <input type="number" min="0" class="form-control" name="height" value="<?php echo htmlspecialchars($space['height']); ?>" placeholder="Leave blank for responsive">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Base Price Multiplier</label>
                            <input type="number" step="0.1" min="0.1" class="form-control" name="base_price_multiplier" value="<?php echo htmlspecialchars($space['base_price_multiplier']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Display Order</label>
                            <input type="number" class="form-control" name="display_order" value="<?php echo htmlspecialchars($space['display_order']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Max Ads Rotation</label>
                            <input type="number" min="1" class="form-control" name="max_ads_rotation" value="<?php echo htmlspecialchars($space['max_ads_rotation']); ?>">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="modalPremiumOnly<?php echo (int) $space['id']; ?>" name="is_premium_only" <?php echo (int) $space['is_premium_only'] === 1 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="modalPremiumOnly<?php echo (int) $space['id']; ?>">Restrict to premium campaigns</label>
                            </div>
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

<?php include 'includes/admin_footer.php'; ?>
