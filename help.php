<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

$auth = new Auth();

$page_title = 'Help Center - ' . SITE_NAME;
$page_description = 'Get help using ' . SITE_NAME . '. Find guides, tutorials, and support resources for crypto earning sites.';
$page_keywords = 'help, support, guides, tutorials, crypto faucets, how to use';

$help_topics = [
    'getting-started' => [
        'title' => 'Getting Started',
        'icon' => 'fa-play-circle',
        'description' => 'Learn the basics of creating an account, browsing sites, and earning your first crypto.'
    ],
    'submissions' => [
        'title' => 'Site Submissions',
        'icon' => 'fa-plus-circle',
        'description' => 'Step-by-step guide to submitting sites, including backlink requirements and approval process.'
    ],
    'reviews' => [
        'title' => 'Reviews & Ratings',
        'icon' => 'fa-star',
        'description' => 'How to write effective reviews, report scams, and help the community make informed decisions.'
    ],
    'wallet' => [
        'title' => 'Wallet & Earnings',
        'icon' => 'fa-wallet',
        'description' => 'Understand the points system, withdrawal methods, and how to maximize your earnings.'
    ],
    'safety' => [
        'title' => 'Trust & Safety',
        'icon' => 'fa-shield-halved',
        'description' => 'Learn about our scam detection system and how to protect yourself from fraudulent sites.'
    ],
    'promotions' => [
        'title' => 'Promotions',
        'icon' => 'fa-rocket',
        'description' => 'Discover how to promote your sites with sponsored listings and premium features.'
    ],
];

$additional_head = '';

include 'includes/header.php';
?>

<main class="page-wrapper flex-grow-1">
    <section class="page-hero text-white text-center">
        <div class="container">
            <div class="hero-content mx-auto" data-aos="fade-up">
                <div class="hero-badge mb-4">
                    <i class="fas fa-life-ring"></i>
                    <span>Support Hub</span>
                </div>
                <h1 class="hero-title mb-4">Need Help with <span class="gradient-text"><?php echo SITE_NAME; ?></span>?</h1>
                <p class="hero-lead mb-5">
                    Explore tutorials, platform walkthroughs, and trusted safety guidelines crafted for crypto earners.
                </p>
                <div class="row g-3 justify-content-center">
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="hero-stat-card">
                            <div class="hero-stat-value">24/7</div>
                            <div class="hero-stat-label">Guides &amp; Docs</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="hero-stat-card">
                            <div class="hero-stat-value"><?php echo count($help_topics); ?></div>
                            <div class="hero-stat-label">Support Topics</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="hero-stat-card">
                            <div class="hero-stat-value">Community</div>
                            <div class="hero-stat-label">Powered Answers</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container my-5">
        <div class="dev-slot">Premium Banner Ad Slot 970x250</div>
    </div>

    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="section-heading mb-3">Browse Help Topics</h2>
                <p class="section-subtitle">Everything you need to make the most of <?php echo SITE_NAME; ?>.</p>
            </div>
            <div class="row g-4">
                <?php foreach ($help_topics as $anchor => $topic): ?>
                    <div class="col-sm-6 col-lg-4" data-aos="fade-up">
                        <a href="#<?php echo $anchor; ?>" class="text-decoration-none text-reset">
                            <div class="glass-card h-100 p-4 d-flex flex-column gap-3 text-center">
                                <div class="feature-icon mx-auto">
                                    <i class="fas <?php echo htmlspecialchars($topic['icon']); ?>"></i>
                                </div>
                                <h3 class="h5 text-white mb-2"><?php echo htmlspecialchars($topic['title']); ?></h3>
                                <p class="text-muted mb-0"><?php echo htmlspecialchars($topic['description']); ?></p>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="py-5" id="getting-started">
        <div class="container">
            <div class="row g-4 g-xl-5 align-items-start">
                <div class="col-lg-8">
                    <div class="glass-card p-4 p-lg-5 h-100" data-aos="fade-right">
                        <h2 class="section-heading h3 text-white mb-2"><i class="fas fa-play-circle me-2 text-success"></i>Getting Started Guide</h2>
                        <p class="text-muted mb-4">Follow these quick steps to set up your account and start earning safely.</p>

                        <div class="support-step">
                            <div class="support-step-index">1</div>
                            <div>
                                <h4 class="text-white mb-2">Create Your Account</h4>
                                <p>Sign up with a unique username and email. Your username doubles as your referral code, so choose something memorable.</p>
                            </div>
                        </div>

                        <div class="support-step">
                            <div class="support-step-index">2</div>
                            <div>
                                <h4 class="text-white mb-2">Browse Verified Sites</h4>
                                <p>Use the “Browse Sites” page to find legitimate crypto earning opportunities. Filter by the “Paying” status for the safest picks.</p>
                            </div>
                        </div>

                        <div class="support-step">
                            <div class="support-step-index">3</div>
                            <div>
                                <h4 class="text-white mb-2">Write Your First Review</h4>
                                <p>Share your experience with platforms you've used. Earn 5 points per review and help the community make informed decisions.</p>
                            </div>
                        </div>

                        <div class="support-step">
                            <div class="support-step-index">4</div>
                            <div>
                                <h4 class="text-white mb-2">Earn and Withdraw</h4>
                                <p>Accumulate points through reviews and activities, then withdraw via FaucetPay or direct wallet transfers when you’re ready.</p>
                            </div>
                        </div>

                        <div class="help-contact-card mt-4">
                            <h4 class="h5 text-white mb-2">Need a walkthrough?</h4>
                            <p>Our quick-start checklist walks you through profile completion, notification preferences, and bookmarking your favorite sites.</p>
                            <a href="sites" class="btn btn-theme btn-outline-glass"><i class="fas fa-search me-2"></i>Explore Verified Sites</a>
                        </div>

                        <div class="help-inline-ad">
                            <div class="dev-slot2">Inline Ad Slot 468x60</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="help-sidebar" data-aos="fade-left">
                        <div class="help-contact-card">
                            <h4 class="h5 text-white mb-3">Talk with Support</h4>
                            <p>Reach our team at <strong><?php echo SITE_EMAIL; ?></strong> or submit a ticket through the contact form.</p>
                            <a href="contact" class="btn btn-theme btn-gradient"><i class="fas fa-headset me-2"></i>Contact Support</a>
                        </div>
                        <div class="glass-card p-4">
                            <h5 class="text-white mb-3">Helpful Links</h5>
                            <ul class="list-unstyled text-muted mb-0">
                                <li class="mb-2"><i class="fas fa-chevron-right me-2 text-info"></i><a href="faq" class="text-decoration-none text-reset">Frequently Asked Questions</a></li>
                                <li class="mb-2"><i class="fas fa-chevron-right me-2 text-info"></i><a href="trust-safety" class="text-decoration-none text-reset">Trust &amp; Safety Center</a></li>
                                <li><i class="fas fa-chevron-right me-2 text-info"></i><a href="submit-site" class="text-decoration-none text-reset">Submit a New Site</a></li>
                            </ul>
                        </div>
                        <div class="dev-slot1">Sidebar Tower Ad 300x600</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="row g-4" data-aos="fade-up">
                <?php foreach ($help_topics as $anchor => $topic): ?>
                    <?php if ($anchor === 'getting-started') { continue; } ?>
                    <div class="col-md-6 col-lg-4">
                        <div id="<?php echo $anchor; ?>" class="anchor-offset"></div>
                        <div class="glass-card h-100 p-4">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="feature-icon"><i class="fas <?php echo htmlspecialchars($topic['icon']); ?>"></i></div>
                                <div>
                                    <h4 class="h6 text-white mb-1"><?php echo htmlspecialchars($topic['title']); ?></h4>
                                    <p class="text-muted mb-0"><?php echo htmlspecialchars($topic['description']); ?></p>
                                </div>
                            </div>
                            <?php if ($anchor === 'submissions'): ?>
                                <ul class="text-muted mb-0 ps-3">
                                    <li>Confirm your site is live, unique, and includes our backlink before submitting.</li>
                                    <li>Track approval status from your dashboard once the community review begins.</li>
                                </ul>
                                <div class="mt-3">
                                    <a href="submit-site" class="btn btn-theme btn-outline-glass w-100"><i class="fas fa-paper-plane me-2"></i>Submit Your Site</a>
                                </div>
                            <?php elseif ($anchor === 'reviews'): ?>
                                <ul class="text-muted mb-0 ps-3">
                                    <li>Share honest experiences, payout timelines, and attach proof when possible.</li>
                                    <li>Use the scam report toggle to alert moderators if a site stops paying.</li>
                                </ul>
                                <div class="mt-3">
                                    <a href="sites" class="btn btn-theme btn-outline-glass w-100"><i class="fas fa-comment-dots me-2"></i>Find a Site to Review</a>
                                </div>
                            <?php elseif ($anchor === 'wallet'): ?>
                                <ul class="text-muted mb-0 ps-3">
                                    <li>Earn points from reviews, submissions, referrals, and community upvotes.</li>
                                    <li>Withdraw via FaucetPay (1000 pts) or direct wallets (2000 pts) within 48 hours.</li>
                                </ul>
                                <div class="mt-3">
                                    <a href="wallet" class="btn btn-theme btn-outline-glass w-100"><i class="fas fa-wallet me-2"></i>Open Wallet</a>
                                </div>
                            <?php elseif ($anchor === 'safety'): ?>
                                <ul class="text-muted mb-0 ps-3">
                                    <li>Sites flagged by 80% scam reviews trigger automated investigations.</li>
                                    <li>Look for “Verified Paying” badges and recent proof before investing time.</li>
                                </ul>
                                <div class="mt-3">
                                    <a href="trust-safety" class="btn btn-theme btn-outline-glass w-100"><i class="fas fa-shield-halved me-2"></i>Trust &amp; Safety</a>
                                </div>
                            <?php elseif ($anchor === 'promotions'): ?>
                                <ul class="text-muted mb-0 ps-3">
                                    <li>Boost visibility with sponsored listings or referral link upgrades.</li>
                                    <li>Use promotion analytics in your dashboard to track clicks and conversions.</li>
                                </ul>
                                <div class="mt-3">
                                    <a href="promote-sites" class="btn btn-theme btn-outline-glass w-100"><i class="fas fa-bullhorn me-2"></i>Promotion Options</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="dev-slot2 mt-5">After Content Ad Slot 728x90</div>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="glass-card p-4 p-lg-5 text-center" data-aos="fade-up">
                <h3 class="h4 text-white mb-3">Still Need Help?</h3>
                <p class="text-muted mb-4">Our support team and community moderators are ready to assist with anything you can’t find in the docs.</p>
                <div class="support-quick-links">
                    <a href="faq" class="btn btn-theme btn-gradient"><i class="fas fa-question-circle me-2"></i>Visit FAQ</a>
                    <a href="contact" class="btn btn-theme btn-outline-glass"><i class="fas fa-envelope-open-text me-2"></i>Submit a Ticket</a>
                    <a href="trust-safety" class="btn btn-theme btn-outline-glass"><i class="fas fa-user-shield me-2"></i>Trust &amp; Safety Center</a>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
