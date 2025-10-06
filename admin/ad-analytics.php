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

// Get date range filter
$date_range = $_GET['range'] ?? '30';
$date_condition = "DATE_SUB(NOW(), INTERVAL {$date_range} DAY)";

// Revenue Statistics
$revenue_query = "SELECT 
    COUNT(DISTINCT ad_id) as total_purchases,
    SUM(amount) as total_revenue,
    AVG(amount) as avg_purchase,
    SUM(CASE WHEN transaction_type = 'refund' THEN amount ELSE 0 END) as total_refunds,
    COUNT(DISTINCT user_id) as unique_advertisers
    FROM ad_transactions
    WHERE created_at >= {$date_condition}";
$revenue_stmt = $db->prepare($revenue_query);
$revenue_stmt->execute();
$revenue_stats = $revenue_stmt->fetch(PDO::FETCH_ASSOC);

// Performance Statistics
$performance_query = "SELECT 
    SUM(impression_count) as total_impressions,
    SUM(click_count) as total_clicks,
    COUNT(*) as total_ads,
    SUM(CASE WHEN visibility_level = 'premium' THEN 1 ELSE 0 END) as premium_ads,
    SUM(CASE WHEN ad_type = 'banner' THEN 1 ELSE 0 END) as banner_ads,
    SUM(CASE WHEN ad_type = 'text' THEN 1 ELSE 0 END) as text_ads
    FROM user_advertisements
    WHERE created_at >= {$date_condition}";
$performance_stmt = $db->prepare($performance_query);
$performance_stmt->execute();
$performance_stats = $performance_stmt->fetch(PDO::FETCH_ASSOC);

// Calculate CTR
$ctr = $performance_stats['total_impressions'] > 0 
    ? ($performance_stats['total_clicks'] / $performance_stats['total_impressions']) * 100 
    : 0;

// Top Advertisers
$top_advertisers_query = "SELECT 
    u.id, u.username, u.email,
    COUNT(DISTINCT ua.id) as ad_count,
    SUM(ua.cost_paid + ua.premium_cost) as total_spent,
    SUM(ua.impression_count) as total_impressions,
    SUM(ua.click_count) as total_clicks
    FROM users u
    INNER JOIN user_advertisements ua ON u.id = ua.user_id
    WHERE ua.created_at >= {$date_condition}
    GROUP BY u.id
    ORDER BY total_spent DESC
    LIMIT 10";
$top_advertisers_stmt = $db->prepare($top_advertisers_query);
$top_advertisers_stmt->execute();
$top_advertisers = $top_advertisers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Best Performing Ads
$best_ads_query = "SELECT 
    ua.id, ua.title, ua.ad_type, ua.visibility_level,
    u.username,
    ua.impression_count,
    ua.click_count,
    CASE 
        WHEN ua.impression_count > 0 
        THEN (ua.click_count / ua.impression_count) * 100 
        ELSE 0 
    END as ctr
    FROM user_advertisements ua
    LEFT JOIN users u ON ua.user_id = u.id
    WHERE ua.created_at >= {$date_condition}
    AND ua.status = 'active'
    ORDER BY ctr DESC, ua.click_count DESC
    LIMIT 10";
$best_ads_stmt = $db->prepare($best_ads_query);
$best_ads_stmt->execute();
$best_ads = $best_ads_stmt->fetchAll(PDO::FETCH_ASSOC);

// Revenue by Ad Type
$revenue_by_type_query = "SELECT 
    ua.ad_type,
    COUNT(*) as ad_count,
    SUM(at.amount) as revenue,
    AVG(at.amount) as avg_revenue
    FROM ad_transactions at
    INNER JOIN user_advertisements ua ON at.ad_id = ua.id
    WHERE at.created_at >= {$date_condition}
    AND at.transaction_type = 'purchase'
    GROUP BY ua.ad_type";
$revenue_by_type_stmt = $db->prepare($revenue_by_type_query);
$revenue_by_type_stmt->execute();
$revenue_by_type = $revenue_by_type_stmt->fetchAll(PDO::FETCH_ASSOC);

// Revenue by Duration
$revenue_by_duration_query = "SELECT 
    ua.duration_days,
    COUNT(*) as ad_count,
    SUM(at.amount) as revenue
    FROM ad_transactions at
    INNER JOIN user_advertisements ua ON at.ad_id = ua.id
    WHERE at.created_at >= {$date_condition}
    AND at.transaction_type = 'purchase'
    GROUP BY ua.duration_days
    ORDER BY ua.duration_days";
$revenue_by_duration_stmt = $db->prepare($revenue_by_duration_query);
$revenue_by_duration_stmt->execute();
$revenue_by_duration = $revenue_by_duration_stmt->fetchAll(PDO::FETCH_ASSOC);

// Daily Revenue Chart Data (last 30 days)
$daily_revenue_query = "SELECT 
    DATE(created_at) as date,
    SUM(CASE WHEN transaction_type = 'purchase' THEN amount ELSE 0 END) as revenue,
    COUNT(DISTINCT ad_id) as ad_count
    FROM ad_transactions
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC";
$daily_revenue_stmt = $db->prepare($daily_revenue_query);
$daily_revenue_stmt->execute();
$daily_revenue = $daily_revenue_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Advertisement Analytics - Admin Panel';
include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Advertisement Analytics & Revenue</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="?range=7" class="btn btn-sm btn-outline-secondary <?php echo $date_range == '7' ? 'active' : ''; ?>">7 Days</a>
                        <a href="?range=30" class="btn btn-sm btn-outline-secondary <?php echo $date_range == '30' ? 'active' : ''; ?>">30 Days</a>
                        <a href="?range=90" class="btn btn-sm btn-outline-secondary <?php echo $date_range == '90' ? 'active' : ''; ?>">90 Days</a>
                        <a href="?range=365" class="btn btn-sm btn-outline-secondary <?php echo $date_range == '365' ? 'active' : ''; ?>">1 Year</a>
                    </div>
                    <a href="ad-revenue.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Management
                    </a>
                </div>
            </div>

             Revenue Statistics 
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Revenue</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        $<?php echo number_format($revenue_stats['total_revenue'], 2); ?>
                                    </div>
                                    <?php if ($revenue_stats['total_refunds'] > 0): ?>
                                        <small class="text-danger">
                                            -$<?php echo number_format($revenue_stats['total_refunds'], 2); ?> refunded
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Purchases</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($revenue_stats['total_purchases']); ?>
                                    </div>
                                    <small class="text-muted">
                                        Avg: $<?php echo number_format($revenue_stats['avg_purchase'], 2); ?>
                                    </small>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Unique Advertisers</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($revenue_stats['unique_advertisers']); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Average CTR</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($ctr, 2); ?>%
                                    </div>
                                    <small class="text-muted">
                                        <?php echo number_format($performance_stats['total_clicks']); ?> / 
                                        <?php echo number_format($performance_stats['total_impressions']); ?>
                                    </small>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-mouse-pointer fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

             Daily Revenue Chart 
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="mb-0">Daily Revenue (Last 30 Days)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="revenueChart" height="80"></canvas>
                        </div>
                    </div>
                </div>
            </div>

             Revenue Breakdown 
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="mb-0">Revenue by Ad Type</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($revenue_by_type)): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Type</th>
                                                <th>Ads</th>
                                                <th>Revenue</th>
                                                <th>Avg</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($revenue_by_type as $type): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-<?php echo $type['ad_type'] === 'banner' ? 'primary' : 'secondary'; ?>">
                                                        <?php echo ucfirst($type['ad_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo number_format($type['ad_count']); ?></td>
                                                <td><strong>$<?php echo number_format($type['revenue'], 2); ?></strong></td>
                                                <td>$<?php echo number_format($type['avg_revenue'], 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No data available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="mb-0">Revenue by Duration</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($revenue_by_duration)): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Duration</th>
                                                <th>Ads Sold</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($revenue_by_duration as $duration): ?>
                                            <tr>
                                                <td><strong><?php echo $duration['duration_days']; ?> days</strong></td>
                                                <td><?php echo number_format($duration['ad_count']); ?></td>
                                                <td><strong>$<?php echo number_format($duration['revenue'], 2); ?></strong></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No data available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

             Top Advertisers 
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="mb-0">Top Advertisers</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($top_advertisers)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Rank</th>
                                                <th>Advertiser</th>
                                                <th>Total Ads</th>
                                                <th>Total Spent</th>
                                                <th>Impressions</th>
                                                <th>Clicks</th>
                                                <th>CTR</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $rank = 1; foreach ($top_advertisers as $advertiser): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($rank <= 3): ?>
                                                        <span class="badge bg-warning text-dark">#<?php echo $rank; ?></span>
                                                    <?php else: ?>
                                                        #<?php echo $rank; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($advertiser['username']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($advertiser['email']); ?></small>
                                                </td>
                                                <td><?php echo number_format($advertiser['ad_count']); ?></td>
                                                <td><strong>$<?php echo number_format($advertiser['total_spent'], 2); ?></strong></td>
                                                <td><?php echo number_format($advertiser['total_impressions']); ?></td>
                                                <td><?php echo number_format($advertiser['total_clicks']); ?></td>
                                                <td>
                                                    <?php 
                                                    $advertiser_ctr = $advertiser['total_impressions'] > 0 
                                                        ? ($advertiser['total_clicks'] / $advertiser['total_impressions']) * 100 
                                                        : 0;
                                                    echo number_format($advertiser_ctr, 2); 
                                                    ?>%
                                                </td>
                                            </tr>
                                            <?php $rank++; endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center py-4">No advertiser data available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

             Best Performing Ads 
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="mb-0">Best Performing Ads</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($best_ads)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Ad Title</th>
                                                <th>Advertiser</th>
                                                <th>Type</th>
                                                <th>Impressions</th>
                                                <th>Clicks</th>
                                                <th>CTR</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($best_ads as $ad): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($ad['title']); ?></strong>
                                                    <?php if ($ad['visibility_level'] === 'premium'): ?>
                                                        <span class="badge bg-warning text-dark ms-1">Premium</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($ad['username']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $ad['ad_type'] === 'banner' ? 'primary' : 'secondary'; ?>">
                                                        <?php echo ucfirst($ad['ad_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo number_format($ad['impression_count']); ?></td>
                                                <td><?php echo number_format($ad['click_count']); ?></td>
                                                <td>
                                                    <strong class="text-success"><?php echo number_format($ad['ctr'], 2); ?>%</strong>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center py-4">No performance data available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Daily Revenue Chart
const ctx = document.getElementById('revenueChart').getContext('2d');
const revenueChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($daily_revenue, 'date')); ?>,
        datasets: [{
            label: 'Revenue ($)',
            data: <?php echo json_encode(array_column($daily_revenue, 'revenue')); ?>,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.1)',
            tension: 0.1,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Revenue: $' + context.parsed.y.toFixed(2);
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value;
                    }
                }
            }
        }
    }
});
</script>

<style>
.border-left-primary {
    border-left: 4px solid #4e73df;
}
.border-left-success {
    border-left: 4px solid #1cc88a;
}
.border-left-info {
    border-left: 4px solid #36b9cc;
}
.border-left-warning {
    border-left: 4px solid #f6c23e;
}
</style>

<?php include 'includes/admin_footer.php'; ?>
