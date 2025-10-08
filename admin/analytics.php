<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/visitor_analytics.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();

if (!$auth->isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$allowedRanges = ['7', '14', '30', '90', '180', '365', 'custom'];
$range = $_GET['range'] ?? '30';
if (!in_array($range, $allowedRanges, true)) {
    $range = '30';
}

$endDate = new DateTimeImmutable('today 23:59:59');
if ($range === 'custom') {
    $startInput = $_GET['start_date'] ?? '';
    $endInput = $_GET['end_date'] ?? '';
    $startDate = DateTimeImmutable::createFromFormat('Y-m-d', $startInput) ?: $endDate->modify('-29 days')->setTime(0, 0);
    $endDate = DateTimeImmutable::createFromFormat('Y-m-d', $endInput) ? DateTimeImmutable::createFromFormat('Y-m-d', $endInput)->setTime(23, 59, 59) : $endDate;
} else {
    $days = (int) $range;
    $startDate = $endDate->modify('-' . ($days - 1) . ' days')->setTime(0, 0);
}

$summary = VisitorAnalytics::fetchSummary($db, $startDate, $endDate);
$timeSeries = VisitorAnalytics::fetchTimeSeries($db, $startDate, $endDate);
$topPages = VisitorAnalytics::fetchTopPages($db, $startDate, $endDate, 10);
$topCountries = VisitorAnalytics::fetchBreakdown($db, 'country', $startDate, $endDate, 8);
$topReferrers = VisitorAnalytics::fetchBreakdown($db, 'referrer', $startDate, $endDate, 6);
$filterOptions = VisitorAnalytics::fetchLogFilters($db);

$pagesPerVisitStmt = $db->prepare("SELECT AVG(visit_count) AS avg_pages
    FROM (
        SELECT COUNT(*) AS visit_count
        FROM visitor_logs
        WHERE visit_time BETWEEN :start AND :end
        GROUP BY ip_address, DATE(visit_time)
    ) AS visits");
$pagesPerVisitStmt->execute([
    ':start' => $startDate->format('Y-m-d H:i:s'),
    ':end' => $endDate->format('Y-m-d H:i:s'),
]);
$pagesPerVisit = (float) ($pagesPerVisitStmt->fetchColumn() ?: 0);

$peakHourStmt = $db->prepare("SELECT HOUR(visit_time) AS hour_bucket, COUNT(*) AS views
    FROM visitor_logs
    WHERE visit_time BETWEEN :start AND :end
    GROUP BY hour_bucket
    ORDER BY views DESC
    LIMIT 1");
$peakHourStmt->execute([
    ':start' => $startDate->format('Y-m-d H:i:s'),
    ':end' => $endDate->format('Y-m-d H:i:s'),
]);
$peakHour = $peakHourStmt->fetch(PDO::FETCH_ASSOC) ?: null;

$peakDayStmt = $db->prepare("SELECT DATE_FORMAT(visit_time, '%W') AS weekday, COUNT(*) AS views
    FROM visitor_logs
    WHERE visit_time BETWEEN :start AND :end
    GROUP BY weekday
    ORDER BY views DESC
    LIMIT 1");
$peakDayStmt->execute([
    ':start' => $startDate->format('Y-m-d H:i:s'),
    ':end' => $endDate->format('Y-m-d H:i:s'),
]);
$peakDay = $peakDayStmt->fetch(PDO::FETCH_ASSOC) ?: null;

$logFilterStart = $_GET['filter_start'] ?? $startDate->format('Y-m-d');
$logFilterEnd = $_GET['filter_end'] ?? $endDate->format('Y-m-d');
$logFilterCountry = $_GET['filter_country'] ?? '';
$logFilterReferrer = $_GET['filter_referrer'] ?? '';

$logs = VisitorAnalytics::fetchLogs($db, [
    'start' => $logFilterStart ? $logFilterStart . ' 00:00:00' : null,
    'end' => $logFilterEnd ? $logFilterEnd . ' 23:59:59' : null,
    'country' => $logFilterCountry,
    'referrer' => $logFilterReferrer,
], 100);

$page_title = 'Visitor Analytics - Admin Panel';
include 'includes/admin_header.php';

$seriesLabels = array_keys($timeSeries);
$seriesViews = array_map(static fn ($row) => $row['views'], $timeSeries);
$seriesVisitors = array_map(static fn ($row) => $row['visitors'], $timeSeries);

$countryLabels = array_map(static fn ($row) => $row['label'], $topCountries);
$countryViews = array_map(static fn ($row) => (int) $row['views'], $topCountries);
$referrerLabels = array_map(static fn ($row) => $row['label'], $topReferrers);
$referrerViews = array_map(static fn ($row) => (int) $row['views'], $topReferrers);

$newVisitors = max(0, $summary['unique_visitors'] - $summary['returning_visitors']);
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex flex-wrap flex-md-nowrap align-items-center justify-content-between pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-0">Visitor Analytics</h1>
                    <p class="text-muted mb-0">Understand how users discover and engage with <?php echo htmlspecialchars(SITE_NAME); ?>.</p>
                </div>
                <form class="d-flex align-items-center gap-2" method="get">
                    <select name="range" class="form-select form-select-sm" onchange="this.form.submit()">
                        <?php foreach (['7' => '7 Days', '14' => '14 Days', '30' => '30 Days', '90' => '90 Days', '180' => '6 Months', '365' => '12 Months', 'custom' => 'Custom'] as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo $range === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($range === 'custom'): ?>
                        <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate->format('Y-m-d')); ?>" class="form-control form-control-sm">
                        <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate->format('Y-m-d')); ?>" class="form-control form-control-sm">
                        <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                    <?php endif; ?>
                </form>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <span class="text-muted text-uppercase small">Total Visits</span>
                            <h3 class="mt-2 mb-0"><?php echo number_format($summary['total_visits']); ?></h3>
                            <p class="text-muted mb-0">Unique sessions in range</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <span class="text-muted text-uppercase small">Unique Visitors</span>
                            <h3 class="mt-2 mb-0"><?php echo number_format($summary['unique_visitors']); ?></h3>
                            <p class="text-muted mb-0">Individual IPs detected</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <span class="text-muted text-uppercase small">Page Views</span>
                            <h3 class="mt-2 mb-0"><?php echo number_format($summary['page_views']); ?></h3>
                            <p class="text-muted mb-0">All recorded hits</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <span class="text-muted text-uppercase small">Returning Visitors</span>
                            <h3 class="mt-2 mb-0"><?php echo number_format($summary['returning_visitors']); ?></h3>
                            <p class="text-muted mb-0"><?php echo number_format($newVisitors); ?> new visitors</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Traffic Timeline</h6>
                            <span class="text-muted small">Daily visits & unique visitors</span>
                        </div>
                        <div class="card-body">
                            <canvas id="trafficTimeline" height="240"></canvas>
                            <?php if (empty($seriesLabels)): ?>
                                <p class="text-muted text-center mt-3 mb-0">No visitor data recorded for this period.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-0">
                            <h6 class="mb-0">Engagement Highlights</h6>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                                <li class="mb-3">
                                    <strong><?php echo number_format($pagesPerVisit, 2); ?></strong>
                                    <span class="text-muted">average pages per visit</span>
                                </li>
                                <li class="mb-3">
                                    <strong><?php echo $peakHour ? sprintf('%02d:00', (int) $peakHour['hour_bucket']) : '—'; ?></strong>
                                    <span class="text-muted">peak traffic hour</span>
                                </li>
                                <li class="mb-3">
                                    <strong><?php echo $peakDay['weekday'] ?? '—'; ?></strong>
                                    <span class="text-muted">busiest day of the week</span>
                                </li>
                                <li class="mb-0 text-muted small">
                                    Top country: <?php echo $topCountries ? htmlspecialchars($topCountries[0]['label']) : 'Not enough data'; ?>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-0">
                            <h6 class="mb-0">Top Countries</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="countryBreakdown" height="220"></canvas>
                            <?php if (empty($countryLabels)): ?>
                                <p class="text-muted text-center mt-3 mb-0">Country-level data will appear as visits are recorded.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-0">
                            <h6 class="mb-0">Referrer Sources</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="referrerBreakdown" height="220"></canvas>
                            <?php if (empty($referrerLabels)): ?>
                                <p class="text-muted text-center mt-3 mb-0">Referrer data will appear when traffic sources are detected.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Top Pages</h6>
                    <span class="text-muted small">Most visited URLs in selected range</span>
                </div>
                <div class="card-body p-0">
                    <?php if ($topPages): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Page URL</th>
                                        <th class="text-end">Views</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topPages as $page): ?>
                                        <tr>
                                            <td class="text-break"><a href="<?php echo htmlspecialchars(SITE_URL . $page['page_url']); ?>" target="_blank"><?php echo htmlspecialchars($page['page_url']); ?></a></td>
                                            <td class="text-end"><?php echo number_format((int) $page['views']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">
                            <p class="mb-0">No page view data captured for the selected period.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-5">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Visitor Logs</h6>
                    <form class="row g-2 align-items-end" method="get">
                        <input type="hidden" name="range" value="<?php echo htmlspecialchars($range); ?>">
                        <?php if ($range === 'custom'): ?>
                            <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($startDate->format('Y-m-d')); ?>">
                            <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($endDate->format('Y-m-d')); ?>">
                        <?php endif; ?>
                        <div class="col-auto">
                            <label class="form-label small text-muted">From</label>
                            <input type="date" name="filter_start" value="<?php echo htmlspecialchars($logFilterStart); ?>" class="form-control form-control-sm">
                        </div>
                        <div class="col-auto">
                            <label class="form-label small text-muted">To</label>
                            <input type="date" name="filter_end" value="<?php echo htmlspecialchars($logFilterEnd); ?>" class="form-control form-control-sm">
                        </div>
                        <div class="col-auto">
                            <label class="form-label small text-muted">Country</label>
                            <select name="filter_country" class="form-select form-select-sm">
                                <option value="">All</option>
                                <?php foreach ($filterOptions['countries'] as $country): ?>
                                    <option value="<?php echo htmlspecialchars($country); ?>" <?php echo $logFilterCountry === $country ? 'selected' : ''; ?>><?php echo htmlspecialchars($country); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-auto">
                            <label class="form-label small text-muted">Referrer</label>
                            <select name="filter_referrer" class="form-select form-select-sm">
                                <option value="">All</option>
                                <?php foreach ($filterOptions['referrers'] as $referrer): ?>
                                    <option value="<?php echo htmlspecialchars($referrer); ?>" <?php echo $logFilterReferrer === $referrer ? 'selected' : ''; ?>><?php echo htmlspecialchars($referrer); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                        </div>
                    </form>
                </div>
                <div class="card-body p-0">
                    <?php if ($logs): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Visited</th>
                                        <th>IP Address</th>
                                        <th>Country</th>
                                        <th>Page</th>
                                        <th>Referrer</th>
                                        <th>User Agent</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td><?php echo date('M j, Y g:i A', strtotime($log['visit_time'])); ?></td>
                                            <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                            <td><?php echo htmlspecialchars($log['country']); ?></td>
                                            <td class="text-break"><a href="<?php echo htmlspecialchars(SITE_URL . $log['page_url']); ?>" target="_blank"><?php echo htmlspecialchars($log['page_url']); ?></a></td>
                                            <td class="text-break"><?php echo htmlspecialchars($log['referrer']); ?></td>
                                            <td class="text-break small"><?php echo htmlspecialchars($log['user_agent']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">
                            <p class="mb-0">No visitor entries match the selected filters.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const trafficCtx = document.getElementById('trafficTimeline');
        if (trafficCtx && <?php echo count($seriesLabels); ?>) {
            new Chart(trafficCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($seriesLabels, JSON_UNESCAPED_SLASHES); ?>,
                    datasets: [
                        {
                            label: 'Page Views',
                            data: <?php echo json_encode($seriesViews, JSON_UNESCAPED_SLASHES); ?>,
                            borderColor: '#4e73df',
                            backgroundColor: 'rgba(78, 115, 223, 0.12)',
                            fill: true,
                            tension: 0.35
                        },
                        {
                            label: 'Unique Visitors',
                            data: <?php echo json_encode($seriesVisitors, JSON_UNESCAPED_SLASHES); ?>,
                            borderColor: '#1cc88a',
                            backgroundColor: 'rgba(28, 200, 138, 0.12)',
                            fill: true,
                            tension: 0.35
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 }
                        }
                    },
                    plugins: {
                        legend: { display: true }
                    }
                }
            });
        }

        const countryCtx = document.getElementById('countryBreakdown');
        if (countryCtx && <?php echo count($countryLabels); ?>) {
            new Chart(countryCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($countryLabels, JSON_UNESCAPED_SLASHES); ?>,
                    datasets: [
                        {
                            label: 'Visits',
                            data: <?php echo json_encode($countryViews, JSON_UNESCAPED_SLASHES); ?>,
                            backgroundColor: '#36b9cc'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 }
                        }
                    }
                }
            });
        }

        const referrerCtx = document.getElementById('referrerBreakdown');
        if (referrerCtx && <?php echo count($referrerLabels); ?>) {
            new Chart(referrerCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($referrerLabels, JSON_UNESCAPED_SLASHES); ?>,
                    datasets: [
                        {
                            data: <?php echo json_encode($referrerViews, JSON_UNESCAPED_SLASHES); ?>,
                            backgroundColor: ['#4e73df', '#1cc88a', '#f6c23e', '#e74a3b', '#858796', '#36b9cc']
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }
    });
</script>

<?php include 'includes/admin_footer.php'; ?>
