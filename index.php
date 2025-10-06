<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();

// Get platform statistics
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM sites WHERE is_approved = 1) as total_sites,
                (SELECT COUNT(*) FROM sites WHERE is_approved = 1 AND status = 'paying') as paying_sites,
                (SELECT COUNT(*) FROM users) as total_users,
                (SELECT COUNT(*) FROM reviews) as total_reviews,
                (SELECT COALESCE(SUM(total_upvotes), 0) FROM sites WHERE is_approved = 1) as total_upvotes,
                (SELECT COUNT(*) FROM sites WHERE is_approved = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as new_sites_month";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Calculate trust metrics
$trust_rate = $stats['total_sites'] > 0 ? round(($stats['paying_sites'] / $stats['total_sites']) * 100, 1) : 0;

// Get top 6 sponsored sites
$sponsored_query =  "SELECT s.*, 
                   COALESCE(AVG(r.rating), 0) as average_rating,
                   COUNT(r.id) as review_count,
                   u.username as submitted_by_username
                   FROM sites s 
                   LEFT JOIN reviews r ON s.id = r.site_id 
                   LEFT JOIN users u ON s.submitted_by = u.id
                   WHERE s.is_approved = 1 AND s.is_sponsored = 1 AND s.sponsored_until > NOW()
                   AND s.is_dead = FALSE AND s.admin_approved_dead = FALSE
                   GROUP BY s.id 
                   ORDER BY COALESCE(s.sponsored_last_shown, '1970-01-01') ASC, RAND()
LIMIT 5
";
$sponsored_stmt = $db->prepare($sponsored_query);
$sponsored_stmt->execute();
$sponsored_sites = $sponsored_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get top 3 rated sites
$top_sites_query = "SELECT s.*, 
                    COALESCE(AVG(r.rating), 0) as average_rating,
                    COUNT(r.id) as review_count,
                    u.username as submitted_by_username
                    FROM sites s 
                    LEFT JOIN reviews r ON s.id = r.site_id 
                    LEFT JOIN users u ON s.submitted_by = u.id
                    WHERE s.is_approved = 1 AND s.is_boosted = 1 AND s.boosted_until > NOW()
                    AND s.is_dead = FALSE AND s.admin_approved_dead = FALSE
                    GROUP BY s.id 
                    ORDER BY COALESCE(s.boosted_last_shown, '1970-01-01') ASC, RAND()
LIMIT 3";
$top_sites_stmt = $db->prepare($top_sites_query);
$top_sites_stmt->execute();
$top_sites = $top_sites_stmt->fetchAll(PDO::FETCH_ASSOC);

function renderStars($rating, $class = '') {
    $html = '<div class="star-rating">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<span class="' . ($i <= $rating ? 'filled' : '') . '">★</span>';
    }
    $html .= '</div>';
    return $html;
}

function getStatusBadge($status) {
    $badges = [
        'paying' => '<span class="status-badge status-paying"><i class="fas fa-check-circle me-2"></i>Verified Paying</span>',
        'scam_reported' => '<span class="status-badge status-scam-reported"><i class="fas fa-exclamation-triangle me-2"></i>Under Review</span>',
        'scam' => '<span class="status-badge status-scam"><i class="fas fa-times-circle me-2"></i>Confirmed Scam</span>'
    ];
    return $badges[$status] ?? '';
}

function truncateText($text, $length = 60) {
    if (strlen($text) <= $length) return $text;
    return substr($text, 0, $length) . '...';
}

$page_title = SITE_NAME . ' - ' . SITE_TAGLINE;
$page_description = 'Discover the most trusted crypto faucets and URL shorteners. Read verified reviews, check real ratings, and find legitimate earning opportunities with our community-driven platform.';
$page_keywords = 'crypto faucets, bitcoin faucets, url shorteners, cryptocurrency earning, passive income, crypto reviews, legitimate faucets';
$current_page = 'home';

$additional_head = '';

include 'includes/header.php';
?>

<main class="page-wrapper flex-grow-1">
    <section class="page-hero text-white text-center">
        <div class="container">
            <div class="hero-content mx-auto" data-aos="fade-up">
                <div class="hero-badge mb-4">
                    <i class="fas fa-shield-halved"></i>
                    <span><?php echo $trust_rate; ?>% Verified Paying Sites</span>
                </div>
                <h1 class="hero-title mb-4">
                    Discover <span class="gradient-text">Trusted Crypto</span> Earning Opportunities
                </h1>
                <p class="hero-lead mb-5">
                    Join thousands of earners navigating the crypto faucet and URL shortener landscape with confidence, verified data, and real community insights.
                </p>
                <div class="d-flex flex-column flex-md-row justify-content-center gap-3 mb-5">
                    <a href="sites" class="btn btn-theme btn-gradient btn-lg"><i class="fas fa-search me-2"></i>Browse Verified Sites</a>
                    <a href="register" class="btn btn-theme btn-outline-glass btn-lg"><i class="fas fa-user-plus me-2"></i>Join Free</a>
                </div>
                <div class="row g-3 justify-content-center">
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="hero-stat-card animate-fade-in">
                            <div class="hero-stat-value"><?php echo number_format($stats['total_sites']); ?>+</div>
                            <div class="hero-stat-label">Verified Sites</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="hero-stat-card animate-fade-in">
                            <div class="hero-stat-value"><?php echo number_format($stats['total_users']); ?>+</div>
                            <div class="hero-stat-label">Active Users</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="hero-stat-card animate-fade-in">
                            <div class="hero-stat-value"><?php echo number_format($stats['total_reviews']); ?>+</div>
                            <div class="hero-stat-label">Real Reviews</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container my-5">
        <div class="dev-slot">Responsive Banner Ad Slot 970x250 / 728x90</div>
    </div>

    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="section-heading mb-3">Why Choose <?php echo SITE_NAME; ?>?</h2>
                <p class="section-subtitle">Your premium gateway to trustworthy crypto faucets and URL shorteners.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-xl-3" data-aos="fade-up" data-aos-delay="50">
                    <div class="glass-card h-100 p-4 text-center">
                        <div class="feature-icon mx-auto"><i class="fas fa-shield-halved"></i></div>
                        <h4 class="text-white mb-3">Verified Trust System</h4>
                        <p class="text-muted mb-0">Advanced risk scoring, scam reporting, and human moderation keep you safe.</p>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3" data-aos="fade-up" data-aos-delay="100">
                    <div class="glass-card h-100 p-4 text-center">
                        <div class="feature-icon mx-auto"><i class="fas fa-users"></i></div>
                        <h4 class="text-white mb-3">Community Intelligence</h4>
                        <p class="text-muted mb-0"><?php echo number_format($stats['total_users']); ?>+ members contribute live reviews, votes, and reports.</p>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3" data-aos="fade-up" data-aos-delay="150">
                    <div class="glass-card h-100 p-4 text-center">
                        <div class="feature-icon mx-auto"><i class="fas fa-lock"></i></div>
                        <h4 class="text-white mb-3">Security First</h4>
                        <p class="text-muted mb-0">Real-time monitoring and layered security keep bad actors out.</p>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3" data-aos="fade-up" data-aos-delay="200">
                    <div class="glass-card h-100 p-4 text-center">
                        <div class="feature-icon mx-auto"><i class="fas fa-coins"></i></div>
                        <h4 class="text-white mb-3">Earn &amp; Grow</h4>
                        <p class="text-muted mb-0">Discover new campaigns, track paying platforms, and unlock rewards faster.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="row g-lg-5">
                <div class="col-lg-8">
                    <?php if (!empty($sponsored_sites)): ?>
                        <div class="glass-card p-4 p-md-5 mb-5 animate-fade-in" data-aos="fade-up">
                            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4">
                                <div>
                                    <h2 class="section-heading mb-2"><i class="fas fa-crown me-2 text-warning"></i>Premium Sponsored Sites</h2>
                                    <p class="text-muted mb-0">Leading platforms investing in premium placement to highlight their legitimacy.</p>
                                </div>
                                <div class="dev-slot2 mt-4 mt-md-0 ms-md-4 flex-shrink-0" style="min-width:220px; min-height:80px;">Inline Ad 300x100</div>
                            </div>
                            <div class="row g-4">
                                <?php foreach ($sponsored_sites as $site): ?>
                                    <div class="col-md-6">
                                        <article class="listing-card featured-card featured-card--premium h-100 animate-fade-in" data-site-id="<?php echo $site['id']; ?>">
                                            <div class="featured-card__badge-row">
                                                <span class="featured-badge featured-badge--premium"><i class="fas fa-crown me-2"></i>Premium Sponsor</span>
                                            </div>
                                            <div class="featured-card__header">
                                <img src="<?php echo htmlspecialchars($site['logo'] ?: 'assets/images/default-logo.png'); ?>" alt="<?php echo htmlspecialchars($site['name']); ?>" class="site-logo">
                                <div>
                                    <h5 class="featured-card__title mb-1 text-white"><?php echo htmlspecialchars($site['name']); ?></h5>
                                    <div class="featured-card__meta">
                                        <span class="site-category"><i class="fas fa-layer-group"></i><?php echo ucfirst(str_replace('_', ' ', $site['category'])); ?></span>
                                    </div>
                                </div>
                            </div>
                                            <div class="featured-card__rating">
                                                <?php echo renderStars(round($site['average_rating'])); ?>
                                                <span class="rating-value"><?php echo number_format($site['average_rating'], 1); ?>/5</span>
                                                <span class="text-muted">(<?php echo $site['review_count']; ?> reviews)</span>
                                            </div>
                                            <p class="featured-card__description"><?php echo htmlspecialchars(truncateText($site['description'], 60)); ?></p>
                                            
                                            <div class="featured-card__status">
                                                <div class="featured-card__status-badges">
                                                    <?php echo getStatusBadge($site['status'] ?? 'paying'); ?>
                                                </div>
                                                <div class="featured-card__trust featured-card__trust--premium"><i class="fas fa-gem me-2"></i>High Conversion Partner</div>
                                            </div>
                                            <div class="featured-card__actions">
                                                <a href="visit?id=<?php echo $site['id']; ?>" class="btn btn-theme btn-gradient" target="_blank" rel="nofollow"><i class="fas fa-arrow-up-right-from-square me-2"></i>Visit Site</a>
                                                <a href="review?id=<?php echo $site['id']; ?>" class="btn btn-theme btn-outline-glass"><i class="fas fa-info-circle me-2"></i>View Details</a>
                                            </div>
                                        </article>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-lg-4">
                    <div class="dev-slot1 mb-4">Sidebar Ad Slot 300x600</div>
                    <div class="glass-card p-4 mb-4 animate-fade-in" data-aos="fade-up">
                        <h4 class="text-white mb-3"><i class="fas fa-bullhorn me-2"></i>Promote Your Site</h4>
                        <p class="text-muted">Reach thousands of active crypto earners. Boost visibility with sponsored placements and premium features.</p>
                        <a href="promote-sites" class="btn btn-theme btn-gradient w-100"><i class="fas fa-rocket me-2"></i>View Promo Options</a>
                    </div>
                    <div class="glass-card p-4 animate-fade-in" data-aos="fade-up">
                        <h4 class="text-white mb-3"><i class="fas fa-lightbulb me-2"></i>Pro Tips</h4>
                        <ul class="text-muted list-unstyled mb-0 d-grid gap-2">
                            <li><i class="fas fa-check-circle me-2 text-success"></i>Monitor status changes before investing time.</li>
                            <li><i class="fas fa-check-circle me-2 text-success"></i>Stack community votes with your own due diligence.</li>
                            <li><i class="fas fa-check-circle me-2 text-success"></i>Use watchlists in your dashboard for daily earners.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="section-heading mb-3">Our Growing Community</h2>
                <p class="section-subtitle">Real-time signals from the strongest earning network.</p>
            </div>
            <div class="row g-4">
                <div class="col-6 col-md-4 col-xl-2" data-aos="fade-up">
                    <div class="glass-card text-center p-4">
                        <h3 class="text-white mb-1"><?php echo number_format($stats['total_sites']); ?></h3>
                        <p class="text-muted mb-0">Verified Sites</p>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2" data-aos="fade-up" data-aos-delay="50">
                    <div class="glass-card text-center p-4">
                        <h3 class="text-white mb-1"><?php echo number_format($stats['paying_sites']); ?></h3>
                        <p class="text-muted mb-0"><?php echo $trust_rate; ?>% Paying</p>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2" data-aos="fade-up" data-aos-delay="100">
                    <div class="glass-card text-center p-4">
                        <h3 class="text-white mb-1"><?php echo number_format($stats['total_users']); ?></h3>
                        <p class="text-muted mb-0">Community Members</p>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2" data-aos="fade-up" data-aos-delay="150">
                    <div class="glass-card text-center p-4">
                        <h3 class="text-white mb-1"><?php echo number_format($stats['total_reviews']); ?></h3>
                        <p class="text-muted mb-0">Reviews Shared</p>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2" data-aos="fade-up" data-aos-delay="200">
                    <div class="glass-card text-center p-4">
                        <h3 class="text-white mb-1"><?php echo number_format($stats['total_upvotes']); ?></h3>
                        <p class="text-muted mb-0">Community Votes</p>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2" data-aos="fade-up" data-aos-delay="250">
                    <div class="glass-card text-center p-4">
                        <h3 class="text-white mb-1"><?php echo number_format($stats['new_sites_month']); ?></h3>
                        <p class="text-muted mb-0">New This Month</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container my-5">
        <div class="dev-slot2">After Content Ad Slot 468x60</div>
    </div>

    <section class="py-5">
        <div class="container">
            <div class="glass-card p-4 p-lg-5 text-center position-relative overflow-hidden" data-aos="fade-up">
                <div class="position-absolute top-0 start-0 translate-middle rounded-circle" style="width:220px;height:220px;background:rgba(56,189,248,0.18);"></div>
                <div class="position-absolute bottom-0 end-0 translate-middle rounded-circle" style="width:260px;height:260px;background:rgba(34,197,94,0.18);"></div>
                <div class="position-relative">
                    <h2 class="section-heading mb-3">Start Earning Safely Today</h2>
                    <p class="section-subtitle mb-4">Create a free account, track paying faucets, and collaborate with a transparent community.</p>
                    <div class="d-flex flex-column flex-md-row justify-content-center gap-3 mb-4">
                        <a href="register" class="btn btn-theme btn-gradient btn-lg"><i class="fas fa-rocket me-2"></i>Get Started Free</a>
                        <a href="sites" class="btn btn-theme btn-outline-glass btn-lg"><i class="fas fa-compass me-2"></i>Explore Directory</a>
                    </div>
                    <p class="text-muted mb-0"><i class="fas fa-shield-halved me-2"></i>100% Free • Transparent Reviews • Admin Verified Listings</p>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>