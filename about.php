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
                (SELECT COUNT(*) FROM sites WHERE is_approved = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as new_sites_month";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$legitimacy_rate = $stats['total_sites'] > 0 ? round(($stats['paying_sites'] / $stats['total_sites']) * 100, 1) : 0;

$page_title = 'About Us - ' . SITE_NAME;
$page_description = 'Learn about ' . SITE_NAME . ', our mission to provide the most trusted crypto earning site directory, and how we protect our community.';
$page_keywords = 'about, crypto directory, mission, team, trust, safety, community';
$current_page = 'about';

$additional_head = '';

include 'includes/header.php';
?>

<main class="page-wrapper flex-grow-1">
    <section class="page-hero text-white text-center">
        <div class="container">
            <div class="hero-content mx-auto" data-aos="fade-up">
                <div class="hero-badge mb-4">
                    <i class="fas fa-compass"></i>
                    <span>Our Story</span>
                </div>
                <h1 class="hero-title mb-3">About <?php echo SITE_NAME; ?></h1>
                <p class="hero-lead">We're building the most trusted and comprehensive directory for crypto earning opportunities, protecting our community through transparency, verification, and collaborative moderation.</p>
                <div class="row g-3 justify-content-center mt-4">
                    <div class="col-6 col-md-3">
                        <div class="hero-stat-card">
                            <div class="hero-stat-value"><?php echo $legitimacy_rate; ?>%</div>
                            <div class="hero-stat-label">Verified Paying</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="hero-stat-card">
                            <div class="hero-stat-value"><?php echo number_format($stats['total_users']); ?>+</div>
                            <div class="hero-stat-label">Trusted Users</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="hero-stat-card">
                            <div class="hero-stat-value"><?php echo number_format($stats['total_reviews']); ?>+</div>
                            <div class="hero-stat-label">Community Reviews</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container my-5">
        <div class="dev-slot">About Page Banner 970x250</div>
    </div>

    <section class="py-5">
        <div class="container" data-aos="fade-up">
            <div class="mission-card">
                <h2 class="h3 text-white mb-3">Our Mission</h2>
                <p class="text-muted mb-0">To create the world's most trusted platform for discovering legitimate crypto earning opportunities. We believe everyone deserves access to verified, safe, and profitable crypto faucets and URL shorteners without the fear of scams or fraudulent sites.</p>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container" data-aos="fade-up">
            <h2 class="section-heading text-center mb-4">Our Core Values</h2>
            <div class="values-grid">
                <div class="value-card">
                    <div class="value-icon value-icon-trust"><i class="fas fa-shield-halved"></i></div>
                    <h3 class="h5 text-white mb-2">Trust &amp; Verification</h3>
                    <p>Every site undergoes rigorous verification. We maintain a <?php echo $legitimacy_rate; ?>% legitimacy rate through community-driven validation and automated scam detection.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon value-icon-visibility"><i class="fas fa-eye"></i></div>
                    <h3 class="h5 text-white mb-2">Transparency</h3>
                    <p>All our processes are open and transparent. From scam detection algorithms to ranking systems, we believe in complete visibility for our community.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon value-icon-community"><i class="fas fa-users"></i></div>
                    <h3 class="h5 text-white mb-2">Community First</h3>
                    <p>Our <?php echo number_format($stats['total_users']); ?>+ members drive our platform. Every review, vote, and report helps protect fellow users and improve the ecosystem.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon value-icon-innovation"><i class="fas fa-rocket"></i></div>
                    <h3 class="h5 text-white mb-2">Innovation</h3>
                    <p>We continuously innovate with advanced detection systems, automated monitoring, and premium features to stay ahead of fraudulent activities.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container" data-aos="fade-up">
            <h2 class="section-heading text-center mb-4">Our Journey</h2>
            <div class="story-timeline">
                <div class="story-item">
                    <div class="story-date">2024</div>
                    <h4 class="text-white mb-2">Platform Launch</h4>
                    <p>Started with a simple idea: create a trusted directory where crypto enthusiasts could find legitimate earning opportunities without fear of scams.</p>
                </div>
                <div class="story-item">
                    <div class="story-date">Early 2024</div>
                    <h4 class="text-white mb-2">Community Growth</h4>
                    <p>Reached our first 1,000 users and implemented our automated scam detection system based on community feedback.</p>
                </div>
                <div class="story-item">
                    <div class="story-date">Mid 2024</div>
                    <h4 class="text-white mb-2">Trust System</h4>
                    <p>Launched our comprehensive trust and safety framework, achieving a <?php echo $legitimacy_rate; ?>% legitimacy rate through rigorous verification.</p>
                </div>
                <div class="story-item">
                    <div class="story-date">Late 2024</div>
                    <h4 class="text-white mb-2">Advanced Features</h4>
                    <p>Introduced wallet functionality, referral programs, and premium promotion options to enhance user experience and platform sustainability.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container" data-aos="fade-up">
            <h2 class="section-heading text-center mb-4">Our Team</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="team-card h-100">
                        <div class="team-avatar"><i class="fas fa-user-tie"></i></div>
                        <h4 class="text-white mb-1">Development Team</h4>
                        <p class="text-info mb-3">Platform Architecture</p>
                        <p>Experienced developers focused on building secure, scalable, and user-friendly crypto platforms with advanced security measures.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="team-card h-100">
                        <div class="team-avatar"><i class="fas fa-shield-halved"></i></div>
                        <h4 class="text-white mb-1">Security Team</h4>
                        <p class="text-success mb-3">Trust &amp; Safety</p>
                        <p>Dedicated security experts who monitor the platform 24/7, investigate reports, and maintain our high standards of site verification.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="team-card h-100">
                        <div class="team-avatar"><i class="fas fa-users"></i></div>
                        <h4 class="text-white mb-1">Community Team</h4>
                        <p class="text-warning mb-3">User Experience</p>
                        <p>Community managers who engage with users, handle support requests, and ensure our platform serves the crypto earning community effectively.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container" data-aos="fade-up">
            <h2 class="section-heading text-center mb-4">Platform Impact</h2>
            <div class="impact-grid">
                <div class="impact-card">
                    <div class="icon shield"><i class="fas fa-shield-halved"></i></div>
                    <div class="value"><?php echo number_format($stats['paying_sites']); ?></div>
                    <p>Verified Paying Sites</p>
                </div>
                <div class="impact-card">
                    <div class="icon users"><i class="fas fa-users"></i></div>
                    <div class="value"><?php echo number_format($stats['total_users']); ?>+</div>
                    <p>Protected Users</p>
                </div>
                <div class="impact-card">
                    <div class="icon reviews"><i class="fas fa-comments"></i></div>
                    <div class="value"><?php echo number_format($stats['total_reviews']); ?>+</div>
                    <p>Community Reviews</p>
                </div>
                <div class="impact-card">
                    <div class="icon rate"><i class="fas fa-chart-line"></i></div>
                    <div class="value"><?php echo $legitimacy_rate; ?>%</div>
                    <p>Legitimacy Rate</p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container" data-aos="fade-up">
            <div class="cta-card">
                <h2 class="h4 text-white mb-3">Join Our Mission</h2>
                <p>Help us build the most trusted crypto earning directory. Every review, vote, and site submission makes our community stronger and safer.</p>
                <div class="legal-actions">
                    <a href="register" class="btn btn-theme btn-gradient"><i class="fas fa-user-plus me-2"></i>Join Community</a>
                    <a href="sites" class="btn btn-theme btn-outline-glass"><i class="fas fa-search me-2"></i>Browse Sites</a>
                </div>
            </div>
            <div class="dev-slot2 mt-5">About Inline Ad 728x90</div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
