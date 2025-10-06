<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();

// Get popular sites for suggestions
$popular_sites_query = "SELECT id, name, category, total_upvotes 
                       FROM sites 
                       WHERE is_approved = 1 AND is_dead = FALSE AND admin_approved_dead = FALSE
                       ORDER BY total_upvotes DESC, views DESC 
                       LIMIT 6";
$popular_sites_stmt = $db->prepare($popular_sites_query);
$popular_sites_stmt->execute();
$popular_sites = $popular_sites_stmt->fetchAll(PDO::FETCH_ASSOC);

http_response_code(404);

$page_title = 'Page Not Found - ' . SITE_NAME;
$page_description = 'The page you are looking for could not be found.';

include 'includes/header.php';
?>


<div class="page-wrapper flex-grow-1">
    <section class="page-hero pb-0">
        <div class="container">
            <div class="glass-card p-4 p-lg-5 text-center animate-fade-in" data-aos="fade-up">
                <div class="mx-auto mb-4 rounded-circle bg-danger bg-opacity-25 text-danger d-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                    <span class="display-6 fw-bold">404</span>
                </div>
                <h1 class="text-white fw-bold mb-2">Page not found</h1>
                <p class="text-muted mb-4">The URL may be outdated or the page might have moved. Let’s get you back on track.</p>
                <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center">
                    <a href="index.php" class="btn btn-theme btn-gradient"><i class="fas fa-home me-2"></i>Return home</a>
                    <a href="sites.php" class="btn btn-outline-light"><i class="fas fa-compass me-2"></i>Browse directory</a>
                </div>
            </div>
            <div class="dev-slot mt-4">Hero Banner 970x250</div>
        </div>
    </section>

    <section class="py-5">
        <div class="container" data-aos="fade-up" data-aos-delay="100">
            <div class="row g-4 align-items-start">
                <div class="col-12 col-lg-8">
                    <div class="glass-card p-4 p-lg-5 h-100">
                        <h2 class="h4 text-white mb-3">Popular destinations to explore</h2>
                        <p class="text-muted small mb-4">Jump into verified earning opportunities curated by the community.</p>
                        <?php if (!empty($popular_sites)): ?>
                        <div class="row g-3 row-cols-1 row-cols-sm-2">
                            <?php foreach ($popular_sites as $site): ?>
                                <div class="col">
                                    <a href="review/<?php echo urlencode(strtolower(str_replace([' ', '_', '-'], '-', $site['name']))); ?>?id=<?php echo $site['id']; ?>"
                                       class="glass-card link-unstyled h-100 p-4">
                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                            <h3 class="h6 text-white mb-0"><?php echo htmlspecialchars($site['name']); ?></h3>
                                            <span class="badge bg-success bg-opacity-25 text-success"><i class="fas fa-arrow-trend-up me-1"></i><?php echo $site['total_upvotes']; ?> upvotes</span>
                                        </div>
                                        <p class="text-muted small mb-0"><?php echo ucfirst(str_replace('_', ' ', $site['category'])); ?> listings loved by our reviewers.</p>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-glass alert-info mb-0">
                            <span class="icon text-info"><i class="fas fa-info-circle"></i></span>
                            <div>No featured sites available right now. Visit the directory to discover new listings.</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-12 col-lg-4">
                    <div class="sticky-lg-top" style="top: 100px;">
                        <div class="glass-card p-4 mb-4">
                            <h3 class="h6 text-white mb-3">Need assistance?</h3>
                            <p class="text-muted small mb-3">Search our Help Center or reach out to support if you believe this link should exist.</p>
                            <div class="d-grid gap-2">
                                <a href="help.php" class="btn btn-outline-light btn-sm"><i class="fas fa-book-open me-2"></i>Help Center</a>
                                <a href="contact.php" class="btn btn-theme btn-sm"><i class="fas fa-headset me-2"></i>Contact support</a>
                            </div>
                        </div>
                        <div class="dev-slot1 mb-4">Sidebar Ad 300x600</div>
                        <div class="glass-card p-4">
                            <h4 class="h6 text-white mb-2">Discover more</h4>
                            <p class="text-muted small mb-3">Browse rankings, reviews, and premium placements curated by our moderators.</p>
                            <a href="rankings.php" class="btn btn-outline-light btn-sm w-100"><i class="fas fa-trophy me-2"></i>View rankings</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dev-slot2 mt-4">Inline Ad 728x90</div>
        </div>
    </section>
</div>


<?php include 'includes/footer.php'; ?>
