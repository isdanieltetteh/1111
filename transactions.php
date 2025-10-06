<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/wallet.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user = $auth->getCurrentUser();
$user_id = $_SESSION['user_id'];

$wallet_manager = new WalletManager($db);
$wallet = $wallet_manager->getUserWallet($user_id);

$allowed_filters = ['all', 'credits', 'deposits', 'earnings', 'withdrawals'];
$filter = isset($_GET['filter']) ? strtolower(trim($_GET['filter'])) : 'all';
if (!in_array($filter, $allowed_filters, true)) {
    $filter = 'all';
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10;

function fetchAllRows(PDO $db, string $query, array $params = []): array {
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $param = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $param);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$credit_rows = fetchAllRows($db, "SELECT id, amount, type, description, status, transaction_id, created_at FROM credit_transactions WHERE user_id = :user_id ORDER BY created_at DESC", [':user_id' => $user_id]);
$deposit_rows = fetchAllRows($db, "SELECT id, amount, currency, payment_method, description, status, faucetpay_id, bitpay_invoice_id, coupon_redemption_id, credits_amount, created_at FROM deposit_transactions WHERE user_id = :user_id ORDER BY created_at DESC", [':user_id' => $user_id]);
$points_rows = fetchAllRows($db, "SELECT id, points, type, description, reference_id, reference_type, created_at FROM points_transactions WHERE user_id = :user_id ORDER BY created_at DESC", [':user_id' => $user_id]);
$withdrawal_rows = fetchAllRows($db, "SELECT id, amount, net_amount, points_redeemed, withdrawal_method, wallet_address, currency, faucetpay_email, withdrawal_fee, status, created_at FROM withdrawal_requests WHERE user_id = :user_id ORDER BY created_at DESC", [':user_id' => $user_id]);

$credits_added = 0.0;
$credits_spent = 0.0;
$credits_withdrawn = 0.0;
foreach ($credit_rows as $row) {
    $amount = (float)$row['amount'];
    switch ($row['type']) {
        case 'deposit':
        case 'refund':
            $credits_added += $amount;
            break;
        case 'spent':
            $credits_spent += $amount;
            break;
        case 'withdrawal':
            $credits_withdrawn += $amount;
            break;
    }
}

$points_earned = 0;
$points_spent = 0;
foreach ($points_rows as $row) {
    $points = (int)$row['points'];
    if ($points >= 0) {
        $points_earned += $points;
    } else {
        $points_spent += abs($points);
    }
}

$withdraw_requested = 0.0;
$withdraw_released = 0.0;
foreach ($withdrawal_rows as $row) {
    $withdraw_requested += (float)$row['amount'];
    if ($row['status'] === 'completed') {
        $withdraw_released += (float)$row['net_amount'];
    }
}

$credit_entries = array_map(function ($row) {
    return [
        'id' => $row['id'],
        'category' => 'Credits',
        'sub_type' => $row['type'],
        'description' => $row['description'] ?: ucfirst($row['type']) . ' transaction',
        'amount' => (float)$row['amount'],
        'status' => $row['status'],
        'created_at' => $row['created_at'],
        'reference' => $row['transaction_id'],
        'tags' => array_filter([
            strtoupper(str_replace('_', ' ', $row['type'])),
            $row['transaction_id'] ? 'Ref ' . $row['transaction_id'] : null
        ])
    ];
}, $credit_rows);

$deposit_entries = array_map(function ($row) {
    $reference = $row['bitpay_invoice_id'] ?: $row['faucetpay_id'];
    if (!$reference && !empty($row['coupon_redemption_id'])) {
        $reference = 'Coupon #' . $row['coupon_redemption_id'];
    }
    $fallback = strtoupper($row['payment_method']) . ' deposit (' . strtoupper($row['currency']) . ')';
    return [
        'id' => $row['id'],
        'category' => 'Deposits',
        'sub_type' => $row['payment_method'],
        'description' => $row['description'] ?: $fallback,
        'amount' => (float)$row['amount'],
        'status' => $row['status'],
        'created_at' => $row['created_at'],
        'reference' => $reference,
        'tags' => array_filter([
            strtoupper($row['currency']),
            $row['credits_amount'] ? number_format((float)$row['credits_amount'], 2) . ' credits' : null
        ])
    ];
}, $deposit_rows);

$points_entries = array_map(function ($row) {
    $reference = '';
    if (!empty($row['reference_type'])) {
        $reference = strtoupper($row['reference_type']) . ($row['reference_id'] ? ' #' . $row['reference_id'] : '');
    }
    return [
        'id' => $row['id'],
        'category' => 'Earnings',
        'sub_type' => $row['type'],
        'description' => $row['description'] ?: 'Points activity',
        'amount' => (int)$row['points'],
        'status' => $row['type'] === 'spent' ? 'spent' : 'posted',
        'created_at' => $row['created_at'],
        'reference' => $reference,
        'tags' => array_filter([
            $reference ?: null
        ])
    ];
}, $points_rows);

$withdrawal_entries = array_map(function ($row) {
    $contact = $row['wallet_address'] ?: $row['faucetpay_email'];
    return [
        'id' => $row['id'],
        'category' => 'Withdrawals',
        'sub_type' => $row['withdrawal_method'],
        'description' => strtoupper($row['currency']) . ' withdrawal',
        'amount' => (float)$row['net_amount'],
        'status' => $row['status'],
        'created_at' => $row['created_at'],
        'reference' => $contact,
        'tags' => array_filter([
            strtoupper($row['currency']),
            number_format((int)$row['points_redeemed']) . ' pts',
            $row['withdrawal_fee'] > 0 ? 'Fee $' . number_format((float)$row['withdrawal_fee'], 2) : null
        ]),
        'requested' => (float)$row['amount']
    ];
}, $withdrawal_rows);

$records_by_filter = [
    'credits' => $credit_entries,
    'deposits' => $deposit_entries,
    'earnings' => $points_entries,
    'withdrawals' => $withdrawal_entries
];

$category_counts = [
    'credits' => count($records_by_filter['credits']),
    'deposits' => count($records_by_filter['deposits']),
    'earnings' => count($records_by_filter['earnings']),
    'withdrawals' => count($records_by_filter['withdrawals'])
];
$category_counts['all'] = array_sum($category_counts);

if ($filter === 'all') {
    $records = array_merge(...array_values($records_by_filter));
    usort($records, function ($a, $b) {
        return strtotime($b['created_at']) <=> strtotime($a['created_at']);
    });
} else {
    $records = $records_by_filter[$filter];
}

$total_rows = $filter === 'all' ? $category_counts['all'] : $category_counts[$filter];
$total_pages = max(1, (int)ceil($total_rows / $per_page));
if ($total_rows > 0 && $page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;
$transactions = array_slice($records, $offset, $per_page);

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time / 60) . 'm ago';
    if ($time < 86400) return floor($time / 3600) . 'h ago';
    return floor($time / 86400) . 'd ago';
}

function statusBadgeClass($status) {
    $status = strtolower($status);
    return match ($status) {
        'completed', 'posted', 'refund' => 'bg-success-subtle text-success fw-semibold',
        'pending' => 'bg-warning-subtle text-warning fw-semibold',
        'failed', 'rejected', 'spent' => 'bg-danger-subtle text-danger fw-semibold',
        default => 'bg-info-subtle text-info fw-semibold'
    };
}

function formatAmountDisplay(array $transaction) {
    $category = $transaction['category'];
    $subType = strtolower($transaction['sub_type']);
    $amount = $transaction['amount'];
    $is_positive = true;

    if ($category === 'Credits') {
        $is_positive = in_array($subType, ['deposit', 'refund'], true);
    } elseif ($category === 'Earnings') {
        $is_positive = $amount >= 0;
    } elseif ($category === 'Withdrawals') {
        $is_positive = false;
    } elseif ($category === 'Deposits') {
        $is_positive = true;
    }

    $display_amount = $category === 'Earnings'
        ? number_format(abs((int)$amount)) . ' pts'
        : '$' . number_format(abs((float)$amount), 2);

    $sign = '';
    if ($category === 'Earnings') {
        $sign = $amount > 0 ? '+' : ($amount < 0 ? '-' : '');
    } elseif ($category === 'Credits') {
        $sign = $is_positive ? '+' : '-';
    } elseif ($category === 'Withdrawals') {
        $sign = '-';
    } elseif ($category === 'Deposits') {
        $sign = '+';
    }

    $class = $is_positive || ($category === 'Earnings' && $amount > 0) ? 'text-success' : 'text-danger';

    return [$sign . $display_amount, $class];
}

function buildPageLink($filter, $page) {
    $query = http_build_query(['filter' => $filter, 'page' => $page]);
    return 'transactions.php?' . $query;
}

$filter_labels = [
    'all' => ['label' => 'All Activity', 'icon' => 'fa-wave-square'],
    'credits' => ['label' => 'Credits', 'icon' => 'fa-credit-card'],
    'deposits' => ['label' => 'Deposits', 'icon' => 'fa-wallet'],
    'earnings' => ['label' => 'Earnings', 'icon' => 'fa-coins'],
    'withdrawals' => ['label' => 'Withdrawals', 'icon' => 'fa-paper-plane']
];

$category_icons = [
    'Credits' => ['icon' => 'fa-credit-card', 'class' => 'bg-info bg-opacity-10 text-info'],
    'Deposits' => ['icon' => 'fa-arrow-trend-up', 'class' => 'bg-success bg-opacity-10 text-success'],
    'Earnings' => ['icon' => 'fa-bolt', 'class' => 'bg-warning bg-opacity-10 text-warning'],
    'Withdrawals' => ['icon' => 'fa-arrow-trend-down', 'class' => 'bg-danger bg-opacity-10 text-danger']
];

$page_title = 'Transactions - ' . SITE_NAME;
$page_description = 'Track your credit spending, deposits, earnings, and withdrawals in one timeline.';
$current_page = 'dashboard';

include 'includes/header.php';
?>

<div class="page-wrapper flex-grow-1">
    <section class="page-hero pb-0">
        <div class="container">
            <div class="glass-card p-4 p-lg-5 animate-fade-in" data-aos="fade-up">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4">
                    <div class="flex-grow-1">
                        <div class="dashboard-breadcrumb mb-3">
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item"><a href="wallet.php">Wallet</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">Transactions</li>
                                </ol>
                            </nav>
                        </div>
                        <h1 class="text-white fw-bold mb-2">Transaction Timeline</h1>
                        <p class="text-muted mb-0">A unified ledger of credits, points, deposits, and withdrawals to keep your strategy transparent.</p>
                    </div>
                    <div class="text-lg-end">
                        <div class="option-chip justify-content-center ms-lg-auto">
                            <i class="fas fa-chart-line"></i>
                            <span><?php echo number_format($wallet['points_balance'] ?? 0); ?> pts balance</span>
                        </div>
                        <div class="d-flex flex-wrap gap-2 justify-content-lg-end mt-3">
                            <a href="wallet.php" class="btn btn-theme btn-outline-glass">
                                <i class="fas fa-wallet me-2"></i>Back to Wallet
                            </a>
                            <a href="buy-credits.php" class="btn btn-theme btn-gradient">
                                <i class="fas fa-credit-card me-2"></i>Buy Credits
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="ad-slot dev-slot mt-4">Transactions Banner 970x250</div>
        </div>
    </section>

    <section class="py-4">
        <div class="container">
            <div class="row g-4" data-aos="fade-up" data-aos-delay="50">
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="glass-card p-4 h-100">
                        <span class="stat-ribbon">Credits Added</span>
                        <div class="d-flex align-items-center gap-3 mt-3">
                            <div class="rounded-circle bg-success bg-opacity-10 text-success d-inline-flex align-items-center justify-content-center" style="width:56px;height:56px;">
                                <i class="fas fa-arrow-trend-up"></i>
                            </div>
                            <div>
                                <h3 class="h4 mb-1 text-white">$<?php echo number_format($credits_added, 2); ?></h3>
                                <p class="text-muted mb-0 small">Deposits &amp; refunds credited</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="glass-card p-4 h-100">
                        <span class="stat-ribbon">Credits Used</span>
                        <div class="d-flex align-items-center gap-3 mt-3">
                            <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-inline-flex align-items-center justify-content-center" style="width:56px;height:56px;">
                                <i class="fas fa-bolt-lightning"></i>
                            </div>
                            <div>
                                <h3 class="h4 mb-1 text-white">$<?php echo number_format($credits_spent + $credits_withdrawn, 2); ?></h3>
                                <p class="text-muted mb-0 small">Campaign spend &amp; withdrawals</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="glass-card p-4 h-100">
                        <span class="stat-ribbon">Points Earned</span>
                        <div class="d-flex align-items-center gap-3 mt-3">
                            <div class="rounded-circle bg-warning bg-opacity-10 text-warning d-inline-flex align-items-center justify-content-center" style="width:56px;height:56px;">
                                <i class="fas fa-coins"></i>
                            </div>
                            <div>
                                <h3 class="h4 mb-1 text-white"><?php echo number_format($points_earned); ?> pts</h3>
                                <p class="text-muted mb-0 small">Reviews, bonuses &amp; referrals</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="glass-card p-4 h-100">
                        <span class="stat-ribbon">Withdrawals Paid</span>
                        <div class="d-flex align-items-center gap-3 mt-3">
                            <div class="rounded-circle bg-info bg-opacity-10 text-info d-inline-flex align-items-center justify-content-center" style="width:56px;height:56px;">
                                <i class="fas fa-paper-plane"></i>
                            </div>
                            <div>
                                <h3 class="h4 mb-1 text-white">$<?php echo number_format($withdraw_released, 2); ?></h3>
                                <p class="text-muted mb-0 small">Sent to your payout wallets</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="pb-5">
        <div class="container">
            <div class="glass-card p-4 p-lg-5 mb-4 animate-fade-in" data-aos="fade-up" data-aos-delay="100">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
                    <h2 class="h4 text-white mb-0">Filter Activity</h2>
                    <div class="text-muted small">Showing <?php echo min($per_page, count($transactions)); ?> of <?php echo $total_rows; ?> records</div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($filter_labels as $key => $meta): ?>
                        <a class="btn btn-sm <?php echo $filter === $key ? 'btn-theme btn-gradient' : 'btn-theme btn-outline-glass'; ?> d-flex align-items-center gap-2"
                           href="<?php echo htmlspecialchars(buildPageLink($key, 1)); ?>">
                            <i class="fas <?php echo htmlspecialchars($meta['icon']); ?>"></i>
                            <span><?php echo htmlspecialchars($meta['label']); ?></span>
                            <span class="badge bg-dark bg-opacity-50"><?php echo $category_counts[$key]; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="ad-slot dev-slot2 mb-4">Inline Transactions Ad 728x90</div>

            <?php if (!empty($transactions)): ?>
                <div class="d-grid gap-4">
                    <?php foreach ($transactions as $transaction): ?>
                        <?php [$amount_display, $amount_class] = formatAmountDisplay($transaction); ?>
                        <?php $icon_meta = $category_icons[$transaction['category']] ?? ['icon' => 'fa-receipt', 'class' => 'bg-primary bg-opacity-10 text-primary']; ?>
                        <div class="glass-card p-4 p-lg-5" data-aos="fade-up">
                            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="rounded-4 <?php echo $icon_meta['class']; ?> d-inline-flex align-items-center justify-content-center" style="width:56px;height:56px;">
                                        <i class="fas <?php echo htmlspecialchars($icon_meta['icon']); ?>"></i>
                                    </div>
                                    <div>
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <span class="badge rounded-pill bg-light bg-opacity-10 text-uppercase fw-semibold"><?php echo htmlspecialchars($transaction['category']); ?></span>
                                            <span class="badge rounded-pill bg-dark bg-opacity-50 text-uppercase fw-semibold"><?php echo htmlspecialchars(str_replace('_', ' ', $transaction['sub_type'])); ?></span>
                                            <span class="badge rounded-pill <?php echo statusBadgeClass($transaction['status']); ?>"><?php echo ucfirst(htmlspecialchars($transaction['status'])); ?></span>
                                        </div>
                                        <h3 class="h5 text-white mt-3 mb-1"><?php echo htmlspecialchars($transaction['description']); ?></h3>
                                        <?php if (!empty($transaction['reference'])): ?>
                                            <div class="text-muted small mb-1">Reference: <?php echo htmlspecialchars($transaction['reference']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($transaction['tags'])): ?>
                                            <div class="d-flex flex-wrap gap-2 mt-2">
                                                <?php foreach ($transaction['tags'] as $tag): ?>
                                                    <span class="badge bg-secondary bg-opacity-25 text-muted fw-semibold"><?php echo htmlspecialchars($tag); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="text-muted small mt-3">
                                            <i class="fas fa-clock me-1"></i><?php echo timeAgo($transaction['created_at']); ?> · <?php echo date('M j, Y • H:i', strtotime($transaction['created_at'])); ?>
                                        </div>
                                        <?php if ($transaction['category'] === 'Withdrawals' && isset($transaction['requested'])): ?>
                                            <div class="text-muted small mt-1">Requested: $<?php echo number_format($transaction['requested'], 2); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="text-lg-end">
                                    <div class="fs-4 fw-semibold <?php echo $amount_class; ?>"><?php echo $amount_display; ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="glass-card p-4 p-lg-5 text-center" data-aos="fade-up">
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3 class="text-white mb-2">No transactions yet</h3>
                        <p class="text-muted mb-0">Start earning points, make deposits, or redeem rewards to see your activity timeline populate.</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($total_pages > 1): ?>
                <nav class="mt-5" aria-label="Transactions pagination">
                    <ul class="pagination justify-content-center flex-wrap gap-2">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo htmlspecialchars(buildPageLink($filter, max(1, $page - 1))); ?>" tabindex="-1" aria-disabled="<?php echo $page <= 1 ? 'true' : 'false'; ?>">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo htmlspecialchars(buildPageLink($filter, $i)); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo htmlspecialchars(buildPageLink($filter, min($total_pages, $page + 1))); ?>">
                                <i class="fas fa-angle-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>

            <div class="ad-slot dev-slot2 mt-5">Footer Ledger Ad 970x90</div>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>
