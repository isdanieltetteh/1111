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

// Get date range
$period = $_GET['period'] ?? '30';
$valid_periods = ['7', '30', '90', '365'];
if (!in_array($period, $valid_periods)) {
    $period = '30';
}

// Get analytics data
$analytics_query = "SELECT 
    DATE(created_at) as date,
    COUNT(*) as count,
    'sites' as type
    FROM sites 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$period} DAY)
    GROUP BY DATE(created_at)
    
    UNION ALL
    
    SELECT 
    DATE(created_at) as date,
    COUNT(*) as count,
    'users' as type
    FROM users 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$period} DAY)
    GROUP BY DATE(created_at)
    
    UNION ALL
    
    SELECT 
    DATE(created_at) as date,
    COUNT(*) as count,
    'reviews' as type
    FROM reviews 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$period} DAY)
    GROUP BY DATE(created_at)
    
    ORDER BY date DESC";

$analytics_stmt = $db->prepare($analytics_query);
$analytics_stmt->execute();
$analytics_data = $analytics_stmt->fetchAll(PDO::FETCH_ASSOC);

// Process data for charts
$chart_data = [];
$dates = [];
for ($i = $period - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $dates[] = $date;
    $chart_data[$date] = ['sites' => 0, 'users' => 0, 'reviews' => 0];
}

foreach ($analytics_data as $row) {
    if (isset($chart_data[$row['date']])) {
        $chart_data[$row['date']][$row['type']] = $row['count'];
    }
}

// Get top performing sites
$top_sites_query = "SELECT s.name, s.views, s.clicks, s.total_upvotes, s.total_reviews,
                    COALESCE(AVG(r.rating), 0) as average_rating,
                    (s.clicks / NULLIF(s.views, 0) * 100) as ctr
                    FROM sites s
                    LEFT JOIN reviews r ON s.id = r.site_id
                    WHERE s.is_approved = 1
                    GROUP BY s.id
                    ORDER BY s.views DESC
                    LIMIT 10";
$top_sites_stmt = $db->prepare($top_sites_query);
$top_sites_stmt->execute();
$top_sites = $top_sites_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user engagement stats
$engagement_query = "SELECT 
    (SELECT COUNT(*) FROM users WHERE last_active >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as weekly_active,
    (SELECT COUNT(*) FROM users WHERE last_active >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as monthly_active,
    (SELECT COUNT(*) FROM reviews WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as weekly_reviews,
    (SELECT COUNT(*) FROM votes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as weekly_votes";
$engagement_stmt = $db->prepare($engagement_query);
$engagement_stmt->execute();
$engagement = $engagement_stmt->fetch(PDO::FETCH_ASSOC);

$page_title = 'Analytics - Admin Panel';
include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Analytics Dashboard</h1>
                <div class="btn-group">
                    <a href="?period=7" class="btn btn-sm <?php echo $period == '7' ? 'btn-primary' : 'btn-outline-primary'; ?>">7 Days</a>
                    <a href="?period=30" class="btn btn-sm <?php echo $period == '30' ? 'btn-primary' : 'btn-outline-primary'; ?>">30 Days</a>
                    <a href="?period=90" class="btn btn-sm <?php echo $period == '90' ? 'btn-primary' : 'btn-outline-primary'; ?>">90 Days</a>
                    <a href="?period=365" class="btn btn-sm <?php echo $period == '365' ? 'btn-primary' : 'btn-outline-primary'; ?>">1 Year</a>
                </div>
            </div>

            <!-- Engagement Stats -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Weekly Active Users</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($engagement['weekly_active']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Monthly Active Users</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($engagement['monthly_active']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-check fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Weekly Reviews</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($engagement['weekly_reviews']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-comments fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Weekly Votes</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($engagement['weekly_votes']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-thumbs-up fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activity Chart -->
            <div class="row">
                <div class="col-xl-8 col-lg-7">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Activity Overview (Last <?php echo $period; ?> Days)</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="activityChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 col-lg-5">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Top Performing Sites</h6>
                        </div>
                        <div class="card-body">
                            <?php foreach ($top_sites as $index => $site): ?>
                            <div class="d-flex align-items-center mb-3">
                                <div class="me-3">
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">
                                        <?php echo $index + 1; ?>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($site['name']); ?></h6>
                                    <small class="text-muted">
                                        <?php echo number_format($site['views']); ?> views • 
                                        <?php echo number_format($site['clicks']); ?> clicks • 
                                        CTR: <?php echo number_format($site['ctr'], 1); ?>%
                                    </small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Deposit Transactions</h6>
                </div>
                <div class="card-body">
                    <?php
                    // Get recent deposit transactions
                    $deposits_query = "SELECT dt.*, u.username 
                                      FROM deposit_transactions dt
                                      JOIN users u ON dt.user_id = u.id
                                      WHERE dt.status = 'completed'
                                      ORDER BY dt.completed_at DESC
                                      LIMIT 10";
                    $deposits_stmt = $db->prepare($deposits_query);
                    $deposits_stmt->execute();
                    $recent_deposits = $deposits_stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    
                    <?php if (!empty($recent_deposits)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Currency</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_deposits as $deposit): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($deposit['username']); ?></td>
                                        <td class="text-success">$<?php echo number_format($deposit['amount'], 4); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $deposit['payment_method'] === 'bitpay' ? 'warning' : 'primary'; ?>">
                                                <?php echo $deposit['payment_method'] === 'bitpay' ? 'BitPay' : 'FaucetPay'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($deposit['currency']); ?></td>
                                        <td><?php echo date('M j, g:i A', strtotime($deposit['completed_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-coins fa-3x text-muted mb-3"></i>
                            <h5>No deposits yet</h5>
                            <p class="text-muted">Deposit transactions will appear here.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Activity Chart
const ctx = document.getElementById('activityChart').getContext('2d');
const chartData = <?php echo json_encode($chart_data); ?>;
const dates = <?php echo json_encode($dates); ?>;

const sitesData = dates.map(date => chartData[date].sites);
const usersData = dates.map(date => chartData[date].users);
const reviewsData = dates.map(date => chartData[date].reviews);

new Chart(ctx, {
    type: 'line',
    data: {
        labels: dates.map(date => new Date(date).toLocaleDateString()),
        datasets: [{
            label: 'Sites',
            data: sitesData,
            borderColor: '#4e73df',
            backgroundColor: 'rgba(78, 115, 223, 0.1)',
            tension: 0.3
        }, {
            label: 'Users',
            data: usersData,
            borderColor: '#1cc88a',
            backgroundColor: 'rgba(28, 200, 138, 0.1)',
            tension: 0.3
        }, {
            label: 'Reviews',
            data: reviewsData,
            borderColor: '#36b9cc',
            backgroundColor: 'rgba(54, 185, 204, 0.1)',
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>

<?php include 'includes/admin_footer.php'; ?>
