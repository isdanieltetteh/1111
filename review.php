<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/ad-widget.php';
 $auth = new Auth();
 $database = new Database();
 $db = $database->getConnection();

// Get site ID from URL parameter or query string
 $site_id = 0;
if (isset($_GET['id'])) {
    $site_id = intval($_GET['id']);
}

if (!$site_id) {
    header('Location: sites.php');
    exit();
}

// Get comprehensive site details
 $site_query = "SELECT s.*, u.username as submitted_by_username,
               COALESCE(AVG(r.rating), 0) as average_rating,
               COUNT(r.id) as review_count,
               COALESCE(SUM(CASE WHEN r.rating = 5 THEN 1 ELSE 0 END), 0) as five_star_count,
               COALESCE(SUM(CASE WHEN r.rating = 4 THEN 1 ELSE 0 END), 0) as four_star_count,
               COALESCE(SUM(CASE WHEN r.rating = 3 THEN 1 ELSE 0 END), 0) as three_star_count,
               COALESCE(SUM(CASE WHEN r.rating = 2 THEN 1 ELSE 0 END), 0) as two_star_count,
               COALESCE(SUM(CASE WHEN r.rating = 1 THEN 1 ELSE 0 END), 0) as one_star_count,
               CASE 
                   WHEN s.is_sponsored = 1 AND s.sponsored_until > NOW() THEN 'sponsored'
                   WHEN s.is_boosted = 1 AND s.boosted_until > NOW() THEN 'boosted'
                   ELSE 'normal'
               END as promotion_status
               FROM sites s 
               LEFT JOIN users u ON s.submitted_by = u.id
               LEFT JOIN reviews r ON s.id = r.site_id AND r.is_deleted = 0
               WHERE s.id = :site_id AND s.is_approved = 1
               GROUP BY s.id";
 $site_stmt = $db->prepare($site_query);
 $site_stmt->bindParam(':site_id', $site_id);
 $site_stmt->execute();
 $site = $site_stmt->fetch(PDO::FETCH_ASSOC);

if (!$site) {
    header('Location: sites');
    exit();
}

// Update view count
 $update_views = "UPDATE sites SET views = views + 1 WHERE id = :site_id";
 $update_stmt = $db->prepare($update_views);
 $update_stmt->bindParam(':site_id', $site_id);
 $update_stmt->execute();

// Get reviews for this site
$reviews_query = "SELECT r.*, u.username, u.avatar, u.reputation_points, l.name as level_name, l.badge_icon, l.badge_color
                  FROM reviews r 
                  JOIN users u ON r.user_id = u.id 
                  LEFT JOIN levels l ON u.active_badge_id = l.id
                  WHERE r.site_id = :site_id AND r.is_deleted = 0 
                  ORDER BY r.is_highlighted DESC, r.created_at DESC";
$reviews_stmt = $db->prepare($reviews_query);
$reviews_stmt->bindParam(':site_id', $site_id);
$reviews_stmt->execute();
$reviews = $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);

$review_replies = [];
if (!empty($reviews)) {
    $review_ids = array_column($reviews, 'id');
    $placeholders = implode(',', array_fill(0, count($review_ids), '?'));
    $replies_query = "SELECT rr.*, u.username, u.avatar, l.name as level_name, l.badge_icon, l.badge_color
                      FROM review_replies rr
                      JOIN users u ON rr.user_id = u.id
                      LEFT JOIN levels l ON u.active_badge_id = l.id
                      WHERE rr.review_id IN ($placeholders) AND rr.is_deleted = 0
                      ORDER BY rr.created_at ASC";
    $replies_stmt = $db->prepare($replies_query);
    foreach ($review_ids as $i => $id) {
        $replies_stmt->bindValue($i+1, $id, PDO::PARAM_INT);
    }
    $replies_stmt->execute();
    $all_replies = $replies_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group replies by review_id
    foreach ($all_replies as $reply) {
        $review_replies[$reply['review_id']][] = $reply;
    }
}

// Get user votes for reviews
 $user_review_votes = [];
if ($auth->isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    $review_ids = array_column($reviews, 'id');
    // Original code:
    // if (!empty($review_ids)) {
    //     $placeholders = implode(',', array_fill(0, count($review_ids), '?'));
    //     $review_votes_query = "SELECT review_id, vote_type FROM review_votes WHERE user_id = ? AND review_id IN ($placeholders)";
    //     $review_votes_stmt = $db->prepare($review_votes_query);
    //     $review_votes_stmt->bindValue(1, $user_id, PDO::PARAM_INT);
    //     foreach ($review_ids as $i => $id) {
    //         $review_votes_stmt->bindValue($i+2, $id, PDO::PARAM_INT);
    //     }
    //    $review_votes_stmt->execute();
    //     $user_review_votes = $review_votes_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    // }
    
    // Updated code:
    if ($user_id && !empty($review_ids)) {
        $placeholders = implode(',', array_fill(0, count($review_ids), '?'));
        $review_votes_query = "SELECT review_id, vote_type FROM votes WHERE user_id = ? AND review_id IN ($placeholders)";
        $review_votes_stmt = $db->prepare($review_votes_query);
        $review_votes_stmt->bindValue(1, $user_id, PDO::PARAM_INT);
        foreach ($review_ids as $i => $id) {
            $review_votes_stmt->bindValue($i+2, $id, PDO::PARAM_INT);
        }
       $review_votes_stmt->execute();
        $user_review_votes = $review_votes_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}

// Check user interactions
 $user_vote = null;
 $user_review = null;
if ($auth->isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    
    // Check user vote
    $vote_query = "SELECT vote_type FROM votes WHERE user_id = :user_id AND site_id = :site_id";
    $vote_stmt = $db->prepare($vote_query);
    $vote_stmt->bindParam(':user_id', $user_id);
    $vote_stmt->bindParam(':site_id', $site_id);
    $vote_stmt->execute();
    $user_vote_result = $vote_stmt->fetch(PDO::FETCH_ASSOC);
    $user_vote = $user_vote_result['vote_type'] ?? null;
    
    // Check user review
    $review_query = "SELECT * FROM reviews WHERE user_id = :user_id AND site_id = :site_id AND is_deleted = 0";
    $review_stmt = $db->prepare($review_query);
    $review_stmt->bindParam(':user_id', $user_id);
    $review_stmt->bindParam(':site_id', $site_id);
    $review_stmt->execute();
    $user_review = $review_stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle new review submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $auth->isLoggedIn() && isset($_POST['submit_review'])) {
    // Verify captcha
    $captcha_valid = false;
    
    if (isset($_POST['h-captcha-response']) && !empty($_POST['h-captcha-response'])) {
        $captcha_response = $_POST['h-captcha-response'];
        $secret_key = HCAPTCHA_SECRET_KEY;
        
        $verify_url = 'https://hcaptcha.com/siteverify';
        $data = [
            'secret' => $secret_key,
            'response' => $captcha_response,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ];
        
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents($verify_url, false, $context);
        $response = json_decode($result, true);
        
        $captcha_valid = $response['success'] ?? false;
    }
    
    if (!$captcha_valid) {
        $error_message = 'Please complete the captcha verification';
    } else {
        $rating = intval($_POST['rating']);
        $comment = trim($_POST['comment']);
        $proof_url = trim($_POST['proof_url'] ?? '');
        $is_scam_report = isset($_POST['is_scam_report']) ? 1 : 0;
        
        // Validate proof URL if provided
        $proof_url_valid = true;
        if (!empty($proof_url)) {
            $allowed_domains = ['imgur.com', 'postimg.cc', 'imgbb.com', 'ibb.co', 'gyazo.com', 'prnt.sc', 'i.imgur.com'];
            $parsed_url = parse_url($proof_url);
            $domain = $parsed_url['host'] ?? '';
            
            $proof_url_valid = false;
            foreach ($allowed_domains as $allowed_domain) {
                if (strpos($domain, $allowed_domain) !== false) {
                    $proof_url_valid = true;
                    break;
                }
            }
        }
        
        if (!$proof_url_valid) {
            $error_message = 'Proof URL must be from a legitimate image hosting service (imgur, postimg, imgbb, etc.)';
        } elseif ($rating >= 1 && $rating <= 5 && !$user_review) {
            $insert_review = "INSERT INTO reviews (site_id, user_id, rating, comment, proof_url, is_scam_report) 
                             VALUES (:site_id, :user_id, :rating, :comment, :proof_url, :is_scam_report)";
            $insert_stmt = $db->prepare($insert_review);
            $insert_stmt->bindParam(':site_id', $site_id);
            $insert_stmt->bindParam(':user_id', $_SESSION['user_id']);
            $insert_stmt->bindParam(':rating', $rating);
            $insert_stmt->bindParam(':comment', $comment);
            $insert_stmt->bindParam(':proof_url', $proof_url);
            $insert_stmt->bindParam(':is_scam_report', $is_scam_report);
            
            if ($insert_stmt->execute()) {
                // Award points for review
                if (file_exists(__DIR__ . '/includes/wallet.php')) {
                    require_once 'includes/wallet.php';
                    $wallet_manager = new WalletManager($db);
                    $wallet_manager->addPoints($_SESSION['user_id'], 5, 'earned', 'Review posted', $site_id, 'review');
                }
                
                // Update user reputation
                $update_reputation = "UPDATE users SET reputation_points = reputation_points + 5 WHERE id = :user_id";
                $rep_stmt = $db->prepare($update_reputation);
                $rep_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $rep_stmt->execute();
                
                // Check for automatic scam detection
                if ($is_scam_report) {
                    $scam_check_query = "SELECT 
                        COUNT(*) as total_reviews,
                        SUM(CASE WHEN is_scam_report = 1 THEN 1 ELSE 0 END) as scam_reports
                        FROM reviews WHERE site_id = :site_id AND is_deleted = 0";
                    $scam_check_stmt = $db->prepare($scam_check_query);
                    $scam_check_stmt->bindParam(':site_id', $site_id);
                    $scam_check_stmt->execute();
                    $scam_data = $scam_check_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($scam_data['total_reviews'] >= 10) {
                        $scam_percentage = ($scam_data['scam_reports'] / $scam_data['total_reviews']) * 100;
                        if ($scam_percentage >= 80) {
                            // Auto-flag as scam reported
                            $update_status = "UPDATE sites SET status = 'scam_reported', scam_reports_count = :scam_reports, total_reviews_for_scam = :total_reviews WHERE id = :site_id";
                            $update_stmt = $db->prepare($update_status);
                            $update_stmt->bindParam(':scam_reports', $scam_data['scam_reports']);
                            $update_stmt->bindParam(':total_reviews', $scam_data['total_reviews']);
                            $update_stmt->bindParam(':site_id', $site_id);
                            $update_stmt->execute();
                        }
                    }
                }
                
                header("Location: review?id={$site_id}");
                exit();
            }
        }
    }
}

// Get sponsored sites (top 5 with rotation)
$sponsored_query = "SELECT s.*, 
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
// Mark sponsored sites as shown
if ($sponsored_sites) {
    $ids = array_column($sponsored_sites, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("UPDATE sites SET sponsored_last_shown = NOW() WHERE id IN ($placeholders)");
    $stmt->execute($ids);
}
// Get boosted sites (top 3 with rotation)
$boosted_query = "SELECT s.*, 
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
LIMIT 3
";
$boosted_stmt = $db->prepare($boosted_query);
$boosted_stmt->execute();
$boosted_sites = $boosted_stmt->fetchAll(PDO::FETCH_ASSOC);
// Mark boosted sites as shown
if ($boosted_sites) {
    $ids = array_column($boosted_sites, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("UPDATE sites SET boosted_last_shown = NOW() WHERE id IN ($placeholders)");
    $stmt->execute($ids);
}

// Get related sites
 $related_sites_query = "SELECT s.*, 
                       COALESCE(AVG(r.rating), 0) as average_rating,
                       COUNT(r.id) as review_count
                       FROM sites s 
                       LEFT JOIN reviews r ON s.id = r.site_id 
                       WHERE s.is_approved = 1 AND s.category = :category AND s.id != :site_id
                       AND s.is_dead = FALSE AND s.admin_approved_dead = FALSE
                       GROUP BY s.id 
                       ORDER BY s.total_upvotes DESC, average_rating DESC
                       LIMIT 4";
 $related_sites_stmt = $db->prepare($related_sites_query);
 $related_sites_stmt->bindParam(':category', $site['category']);
 $related_sites_stmt->bindParam(':site_id', $site_id);
 $related_sites_stmt->execute();
 $related_sites = $related_sites_stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate masked referral link
 $referral_link = $site['referral_link'] ?: $site['url'];
 $masked_link = "visit?id=" . $site_id;

// Calculate trust score
 $trust_score = calculate_trust_score($site['average_rating'], $site['review_count'], $site['total_upvotes'], $site['total_downvotes']);

// SEO and Schema data
 $page_title = htmlspecialchars($site['name']) . ' review legit or scam | Trust Score ' . $trust_score . '% | ' . SITE_NAME;
 $page_description = 'Review of ' . htmlspecialchars($site['name']) . ' - ' . number_format($site['average_rating'], 1) . '/5 stars from ' . $site['review_count'] . ' reviews. Status: ' . ucfirst($site['status']) . '. Find out if it\'s legit or scam.';
 $page_keywords = htmlspecialchars($site['name']) . ', review, legit, scam, ' . $site['category'] . ', crypto faucet, earning site';

// Generate widget embed code
 $widget_embed_code = '<iframe src="' . SITE_URL . '/widget?site=' . $site_id . '&theme=dark" width="300" height="400" frameborder="0"></iframe>';

 $additional_head = '
    <script src="https://js.hcaptcha.com/1/api.js" async defer></script>

    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Review",
        "itemReviewed": {
            "@type": "WebSite",
            "name": "' . htmlspecialchars($site['name']) . '",
            "url": "' . htmlspecialchars($site['url']) . '",
            "description": "' . htmlspecialchars($site['description']) . '"
        },
        "aggregateRating": {
            "@type": "AggregateRating",
            "ratingValue": "' . number_format($site['average_rating'], 1) . '",
            "bestRating": "5",
            "worstRating": "1",
            "ratingCount": "' . $site['review_count'] . '"
        },
        "author": {
            "@type": "Organization",
            "name": "' . SITE_NAME . '"
        }
    }
    </script>
';

include 'includes/header.php';
?>

<?php
function truncateText($text, $length = 60) {
    if (strlen($text) <= $length) return $text;
    return substr($text, 0, $length) . '...';
}
?>

<main class="page-wrapper flex-grow-1 py-5">
    <section class="page-hero pb-0">
        <div class="container">
            <div class="site-overview-card glass-card p-4 p-lg-5 mb-5" data-site-id="<?php echo $site_id; ?>">
                <?php if ($site['promotion_status'] === 'sponsored'): ?>
                    <span class="promotion-tag"><i class="fas fa-crown"></i> Premium Sponsor</span>
                <?php elseif ($site['promotion_status'] === 'boosted'): ?>
                    <span class="promotion-tag boosted"><i class="fas fa-rocket"></i> Boosted Spotlight</span>
                <?php endif; ?>
                <div class="row g-4 align-items-center">
                    <div class="col-md-auto text-center text-md-start">
                        <img src="<?php echo htmlspecialchars($site['logo'] ?: 'assets/images/default-logo.png'); ?>" alt="<?php echo htmlspecialchars($site['name']); ?>" class="site-logo-xl mb-3">
                        <div class="d-flex flex-column gap-2 align-items-center align-items-md-start">
                            <?php echo get_status_badge($site['status']); ?>
                            <span class="trust-pill"><i class="fas fa-shield-halved me-1"></i><?php echo $trust_score; ?>% Trust Score</span>
                        </div>
                    </div>
                    <div class="col-md">
                        <div class="d-flex flex-column gap-3">
                            <div>
                                <h1 class="text-white fw-bold mb-3"><?php echo htmlspecialchars($site['name']); ?></h1>
                                <div class="d-flex flex-wrap align-items-center gap-3 text-muted">
                                    <?php echo render_stars(round($site['average_rating']), '1.5rem'); ?>
                                    <span class="fw-semibold"><?php echo number_format($site['average_rating'], 1); ?>/5 · <?php echo $site['review_count']; ?> reviews</span>
                                    <span class="badge bg-info-subtle text-uppercase"><?php echo ucfirst(str_replace('_', ' ', $site['category'])); ?></span>
                                </div>
                            </div>
                            <div class="meta-grid">
                                <div class="meta-item">
                                    <i class="fas fa-thumbs-up"></i>
                                    <div>
                                        <small class="text-muted text-uppercase">Upvotes</small>
                                        <div id="site-upvotes" class="fs-5 fw-bold text-white"><?php echo $site['total_upvotes']; ?></div>
                                    </div>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-thumbs-down"></i>
                                    <div>
                                        <small class="text-muted text-uppercase">Downvotes</small>
                                        <div class="fs-5 fw-bold text-white"><?php echo $site['total_downvotes']; ?></div>
                                    </div>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-eye"></i>
                                    <div>
                                        <small class="text-muted text-uppercase">Views</small>
                                        <div class="fs-5 fw-bold text-white"><?php echo number_format($site['views']); ?></div>
                                    </div>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-calendar-days"></i>
                                    <div>
                                        <small class="text-muted text-uppercase">Listed</small>
                                        <div class="fs-5 fw-bold text-white"><?php echo date('M j, Y', strtotime($site['created_at'])); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <p class="text-muted mb-0"><?php echo nl2br(htmlspecialchars(truncateText($site['description'], 0))); ?></p>
                            </div>
                            <div class="site-actions">
                                <a href="<?php echo $masked_link; ?>" class="btn btn-theme btn-gradient btn-lg" target="_blank" rel="nofollow" onclick="trackClick(<?php echo $site_id; ?>)">
                                    <i class="fas fa-arrow-up-right-from-square me-2"></i> Visit Site
                                </a>
                                <div class="vote-buttons">
                                    <button class="vote-btn upvote <?php echo $user_vote === 'upvote' ? 'active' : ''; ?>" onclick="vote(<?php echo $site_id; ?>, 'upvote', 'site')">
                                        <i class="fas fa-thumbs-up"></i>
                                        <span class="vote-count"><?php echo $site['total_upvotes']; ?></span>
                                    </button>
                                    <button class="vote-btn downvote <?php echo $user_vote === 'downvote' ? 'active' : ''; ?>" onclick="vote(<?php echo $site_id; ?>, 'downvote', 'site')">
                                        <i class="fas fa-thumbs-down"></i>
                                        <span class="vote-count"><?php echo $site['total_downvotes']; ?></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container my-5">
        <div class="dev-slot1 mb-3">Premium Banner 728x90</div>
        <div class="text-center">
            <?php echo displayAdSpace($db, 'review_top_banner'); ?>
        </div>
    </div>

    <section class="py-4 py-lg-5">
        <div class="container">
            <div class="row g-xl-5">
                <div class="col-lg-8">
                    <div class="glass-card p-4 p-lg-5 mb-4">
                        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4">
                            <h2 class="section-heading mb-0 text-white"><i class="fas fa-book-open text-info me-2"></i>About <?php echo htmlspecialchars($site['name']); ?></h2>
                            <span class="badge bg-secondary-subtle"><i class="fas fa-clock me-2"></i>Live trust insights</span>
                        </div>
                        <p class="text-muted mb-4"><?php echo nl2br(htmlspecialchars($site['description'])); ?></p>
                        <?php if ($site['supported_coins']): ?>
                            <div class="supported-coins-card">
                                <h5 class="text-white mb-3"><i class="fas fa-coins text-warning me-2"></i>Supported Cryptocurrencies</h5>
                                <div class="crypto-tags">
                                    <?php foreach (explode(',', $site['supported_coins']) as $coin): ?>
                                        <span class="crypto-tag"><?php echo trim($coin); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="dev-slot2 mb-5">Inline Ad 728x90</div>

                    <?php if (!empty($sponsored_sites)): ?>
                        <div class="glass-card p-4 p-lg-5 mb-5">
                            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4">
                                <div>
                                    <h2 class="section-heading mb-2 text-white"><i class="fas fa-crown text-warning me-2"></i>Premium Sponsored Sites</h2>
                                    <p class="text-muted mb-0">Legit partners investing in premium visibility for the community.</p>
                                </div>
                                <div class="dev-slot2 mt-4 mt-md-0 ms-md-4 flex-shrink-0" style="min-width:220px; min-height:80px;">Inline Ad 300x100</div>
                            </div>
                            <div class="row g-4">
                                <?php foreach ($sponsored_sites as $site): ?>
                                    <div class="col-md-6">
                                        <article class="listing-card featured-card featured-card--premium h-100">
                                            <div class="featured-card__badge-row">
                                                <span class="featured-badge featured-badge--premium"><i class="fas fa-crown me-2"></i>Premium Sponsor</span>
                                            </div>
                                            <div class="featured-card__header">
                                                <img src="<?php echo htmlspecialchars($site['logo'] ?: 'assets/images/default-logo.png'); ?>" alt="<?php echo htmlspecialchars($site['name']); ?>" class="site-logo">
                                                <div>
                                                    <h4 class="featured-card__title mb-1 text-white"><?php echo htmlspecialchars($site['name']); ?></h4>
                                                    <div class="featured-card__meta">
                                                        <span class="site-category"><i class="fas fa-layer-group"></i><?php echo ucfirst(str_replace('_', ' ', $site['category'])); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="featured-card__rating">
                                                <?php echo render_stars(round($site['average_rating']), '1rem'); ?>
                                                <span class="rating-value"><?php echo number_format($site['average_rating'], 1); ?>/5</span>
                                                <span class="text-muted">(<?php echo number_format($site['review_count']); ?> reviews)</span>
                                            </div>
                                            <p class="featured-card__description"><?php echo htmlspecialchars(truncateText($site['description'], 60)); ?></p>
                                            <div class="featured-card__status">
                                                <div class="featured-card__status-badges">
                                                    <?php echo get_status_badge($site['status'] ?? 'paying'); ?>
                                                </div>
                                                <div class="featured-card__trust featured-card__trust--premium"><i class="fas fa-gem me-2"></i>High Conversion Partner</div>
                                            </div>
                                            <div class="featured-card__actions">
                                                <a href="visit?id=<?php echo $site['id']; ?>" class="btn btn-theme btn-gradient" target="_blank" rel="nofollow"><i class="fas fa-arrow-up-right-from-square me-2"></i>Visit Site</a>
                                                <a href="review?id=<?php echo $site['id']; ?>" class="btn btn-theme btn-outline-glass"><i class="fas fa-info-circle me-2"></i>Details</a>
                                            </div>
                                        </article>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>



                    <div class="glass-card p-4 p-lg-5 mb-5">
                        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4">
                            <div>
                                <h2 class="section-heading mb-2 text-white"><i class="fas fa-comments text-info me-2"></i>Community Reviews</h2>
                                <p class="text-muted mb-0">Real experiences shared by earners like you.</p>
                            </div>
                            <?php if ($auth->isLoggedIn() && !$user_review): ?>
                                <button type="button" class="btn btn-theme btn-gradient" onclick="toggleReviewForm()">
                                    <i class="fas fa-pen-to-square me-2"></i>Write a Review
                                </button>
                            <?php elseif (!$auth->isLoggedIn()): ?>
                                <a href="login" class="btn btn-theme btn-outline-glass">
                                    <i class="fas fa-right-to-bracket me-2"></i>Login to Review
                                </a>
                            <?php endif; ?>
                        </div>

                        <?php if ($auth->isLoggedIn() && $user_review): ?>
                            <div class="alert alert-info bg-opacity-25 border-0 text-info mb-4">
                                <i class="fas fa-circle-check me-2"></i>You have already shared a review for this site.
                            </div>
                        <?php endif; ?>

                        <?php if ($auth->isLoggedIn() && !$user_review): ?>
                            <div class="inline-review-form mb-4" id="reviewFormContainer">
                                <h4 class="text-white mb-3"><i class="fas fa-edit me-2 text-info"></i>Share Your Experience</h4>
                                <form method="POST" id="reviewForm" class="row g-4">
                                    <div class="col-12">
                                        <label class="form-label text-white">Your Rating</label>
                                        <div class="interactive-rating d-inline-flex gap-2 fs-3" id="interactiveRating" data-rating="0">
                                            <span class="star" data-rating="1">★</span>
                                            <span class="star" data-rating="2">★</span>
                                            <span class="star" data-rating="3">★</span>
                                            <span class="star" data-rating="4">★</span>
                                            <span class="star" data-rating="5">★</span>
                                        </div>
                                        <input type="hidden" name="rating" id="ratingInput" required>
                                    </div>
                                    <div class="col-12">
                                        <label for="comment" class="form-label text-white">Your Review</label>
                                        <textarea name="comment" id="comment" class="form-control" rows="4" placeholder="Share your experience with this site..." required></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="proof_url" class="form-label text-white">Payment Proof URL (Optional)</label>
                                        <input type="url" name="proof_url" id="proof_url" class="form-control" placeholder="https://imgur.com/your-proof-image">
                                        <div class="form-text"><i class="fas fa-info-circle me-1"></i>Accepted hosts: imgur, postimg, imgbb, ibb, gyazo, prnt.sc</div>
                                    </div>
                                    <div class="col-md-6 d-flex align-items-end">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="is_scam_report" id="isScamReport">
                                            <label class="form-check-label text-warning" for="isScamReport"><i class="fas fa-triangle-exclamation me-1"></i>Report this site as a scam</label>
                                            <div class="form-text">Flag only if the platform failed to pay.</div>
                                        </div>
                                    </div>
                                    <div class="col-12 d-flex justify-content-center">
                                        <div class="h-captcha" data-sitekey="<?php echo HCAPTCHA_SITE_KEY; ?>"></div>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" name="submit_review" class="btn btn-theme btn-gradient btn-lg w-100">
                                            <i class="fas fa-paper-plane me-2"></i>Submit Review
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php elseif (!$auth->isLoggedIn()): ?>
                            <div class="inline-review-form active text-center mb-4" id="reviewFormContainer">
                                <h4 class="text-white mb-2"><i class="fas fa-star-half-stroke text-warning me-2"></i>Want to review this site?</h4>
                                <p class="text-muted mb-4">Create a free account to unlock voting, earn rewards, and help the community stay safe.</p>
                                <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                                    <a href="register" class="btn btn-theme btn-gradient">
                                        <i class="fas fa-user-plus me-2"></i>Sign Up Free
                                    </a>
                                    <a href="login" class="btn btn-theme btn-outline-glass">
                                        <i class="fas fa-right-to-bracket me-2"></i>Login
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($reviews)): ?>
                            <?php foreach ($reviews as $review): ?>
                                <?php $user_vote_on_review = $user_review_votes[$review['id']] ?? null; ?>
                                <div class="review-card <?php echo $review['is_highlighted'] ? 'highlighted' : ''; ?>" data-review-id="<?php echo $review['id']; ?>">
                                    <div class="review-header">
                                        <div class="review-author">
                                            <img src="<?php echo htmlspecialchars($review['avatar']); ?>" alt="<?php echo htmlspecialchars($review['username']); ?>" class="review-avatar">
                                            <div>
                                                <div class="d-flex flex-wrap align-items-center gap-2">
                                                    <span class="fw-semibold text-white"><?php echo htmlspecialchars($review['username']); ?></span>
                                                    <?php if ($review['level_name']): ?>
                                                        <span class="user-active-badge" style="background: <?php echo htmlspecialchars($review['badge_color'] ?? 'rgba(59, 130, 246, 0.4)'); ?>33; color: <?php echo htmlspecialchars($review['badge_color'] ?? '#60a5fa'); ?>; border: 1px solid <?php echo htmlspecialchars($review['badge_color'] ?? '#60a5fa'); ?>55;">
                                                            <?php echo $review['badge_icon']; ?> <?php echo htmlspecialchars($review['level_name']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($review['is_highlighted']): ?>
                                                        <span class="review-highlight-badge"><i class="fas fa-star me-1"></i>Highlighted</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="review-meta">
                                                    <span><i class="fas fa-clock me-1"></i><?php echo time_ago($review['created_at']); ?></span>
                                                    <span><?php echo str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']); ?></span>
                                                    <span><i class="fas fa-medal me-1"></i><?php echo number_format($review['reputation_points']); ?> rep</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="review-content">
                                        <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                                    </div>
                                    <div class="review-actions">
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <?php if ($review['proof_url']): ?>
                                                <a href="<?php echo htmlspecialchars($review['proof_url']); ?>" target="_blank" rel="nofollow" class="review-proof">
                                                    <i class="fas fa-arrow-up-right-from-square"></i> Payment Proof
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($review['is_scam_report']): ?>
                                                <span class="scam-report-badge"><i class="fas fa-triangle-exclamation me-1"></i>Scam Report</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($auth->isLoggedIn() && $_SESSION['user_id'] != $review['user_id']): ?>
                                            <div class="vote-buttons">
                                                <button class="vote-btn upvote <?php echo $user_vote_on_review === 'upvote' ? 'active' : ''; ?>" onclick="vote(<?php echo $review['id']; ?>, 'upvote', 'review')" data-review-id="<?php echo $review['id']; ?>" data-vote-type="upvote">
                                                    <i class="fas fa-thumbs-up"></i>
                                                    <span class="vote-count"><?php echo $review['upvotes']; ?></span>
                                                </button>
                                                <button class="vote-btn downvote <?php echo $user_vote_on_review === 'downvote' ? 'active' : ''; ?>" onclick="vote(<?php echo $review['id']; ?>, 'downvote', 'review')" data-review-id="<?php echo $review['id']; ?>" data-vote-type="downvote">
                                                    <i class="fas fa-thumbs-down"></i>
                                                    <span class="vote-count"><?php echo $review['downvotes']; ?></span>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($auth->isLoggedIn() && $_SESSION['user_id'] != $review['user_id']): ?>
                                        <div class="reply-section mt-3">
                                            <button class="btn btn-theme btn-outline-glass btn-sm" onclick="toggleReplyForm(<?php echo $review['id']; ?>)">
                                                <i class="fas fa-reply me-1"></i>Reply
                                            </button>
                                            <div class="reply-form" id="replyForm<?php echo $review['id']; ?>">
                                                <textarea class="form-control" placeholder="Write a helpful reply..." id="replyContent<?php echo $review['id']; ?>"></textarea>
                                                <div class="reply-actions">
                                                    <button class="btn btn-theme btn-gradient btn-sm" onclick="submitReply(<?php echo $review['id']; ?>, event)">Post Reply</button>
                                                    <button class="btn btn-theme btn-outline-glass btn-sm" onclick="toggleReplyForm(<?php echo $review['id']; ?>)">Cancel</button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (isset($review_replies[$review['id']]) && !empty($review_replies[$review['id']])): ?>
                                        <div class="replies-container mt-3">
                                            <?php foreach ($review_replies[$review['id']] as $reply): ?>
                                                <div class="related-card mb-3">
                                                    <img src="<?php echo htmlspecialchars($reply['avatar']); ?>" alt="<?php echo htmlspecialchars($reply['username']); ?>" class="related-logo">
                                                    <div>
                                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                                            <span class="fw-semibold text-white"><?php echo htmlspecialchars($reply['username']); ?></span>
                                                            <?php if ($reply['level_name']): ?>
                                                                <span class="user-active-badge" style="background: <?php echo htmlspecialchars($reply['badge_color'] ?? 'rgba(59, 130, 246, 0.4)'); ?>33; color: <?php echo htmlspecialchars($reply['badge_color'] ?? '#60a5fa'); ?>; border: 1px solid <?php echo htmlspecialchars($reply['badge_color'] ?? '#60a5fa'); ?>55;">
                                                                    <?php echo $reply['badge_icon']; ?> <?php echo htmlspecialchars($reply['level_name']); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                            <span class="text-muted"><i class="fas fa-clock me-1"></i><?php echo time_ago($reply['created_at']); ?></span>
                                                        </div>
                                                        <div class="text-muted"><?php echo nl2br(htmlspecialchars($reply['content'])); ?></div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                <h4 class="text-white">No reviews yet</h4>
                                <p class="text-muted">Be the first to review this site and help guide the community.</p>
                                <?php if (!$auth->isLoggedIn()): ?>
                                    <a href="register" class="btn btn-theme btn-gradient"><i class="fas fa-user-plus me-2"></i>Create Account</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($boosted_sites)): ?>
                        <div class="glass-card p-4 p-lg-5 mb-5">
                            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4">
                                <div>
                                    <h2 class="section-heading mb-2 text-white"><i class="fas fa-rocket text-primary me-2"></i>Boosted Community Favorites</h2>
                                    <p class="text-muted mb-0">Community-loved sites enjoying elevated visibility.</p>
                                </div>
                            </div>
                            <div class="row g-4">
                                <?php foreach ($boosted_sites as $site): ?>
                                    <div class="col-md-6">
                                        <article class="listing-card featured-card featured-card--boosted h-100">
                                            <div class="featured-card__badge-row">
                                                <span class="featured-badge featured-badge--boosted"><i class="fas fa-rocket me-2"></i>Boosted Spotlight</span>
                                            </div>
                                            <div class="featured-card__header">
                                                <img src="<?php echo htmlspecialchars($site['logo'] ?: 'assets/images/default-logo.png'); ?>" alt="<?php echo htmlspecialchars($site['name']); ?>" class="site-logo">
                                                <div>
                                                    <h4 class="featured-card__title mb-1 text-white"><?php echo htmlspecialchars($site['name']); ?></h4>
                                                    <div class="featured-card__meta">
                                                        <span class="site-category"><i class="fas fa-layer-group"></i><?php echo ucfirst(str_replace('_', ' ', $site['category'])); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="featured-card__rating">
                                                <?php echo render_stars(round($site['average_rating']), '1rem'); ?>
                                                <span class="rating-value"><?php echo number_format($site['average_rating'], 1); ?>/5</span>
                                                <span class="text-muted">(<?php echo number_format($site['review_count']); ?> reviews)</span>
                                            </div>
                                            <p class="featured-card__description"><?php echo htmlspecialchars(truncateText($site['description'], 60)); ?></p>
                                            
                                            <div class="featured-card__status">
                                                <div class="featured-card__status-badges">
                                                    <?php echo get_status_badge($site['status'] ?? 'paying'); ?>
                                                </div>
                                                <div class="featured-card__trust featured-card__trust--boosted"><i class="fas fa-bolt me-2"></i>Community Favorite</div>
                                            </div>
                                            <div class="featured-card__actions">
                                                <a href="visit?id=<?php echo $site['id']; ?>" class="btn btn-theme btn-gradient" target="_blank" rel="nofollow"><i class="fas fa-arrow-up-right-from-square me-2"></i>Visit Site</a>
                                                <a href="review?id=<?php echo $site['id']; ?>" class="btn btn-theme btn-outline-glass"><i class="fas fa-info-circle me-2"></i>Details</a>
                                            </div>
                                        </article>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>







                    <?php if (!empty($related_sites)): ?>
                        <div class="glass-card p-4 p-lg-5 mb-5">
                            <h3 class="section-heading text-white mb-4"><i class="fas fa-sitemap text-info me-2"></i>Related <?php echo ucfirst(str_replace('_', ' ', $site['category'])); ?> Sites</h3>
                            <div class="row g-4">
                                <?php foreach ($related_sites as $related_site): ?>
                                    <div class="col-sm-6">
                                        <div class="related-card h-100">
                                            <img src="<?php echo htmlspecialchars($related_site['logo'] ?: 'assets/images/default-logo.png'); ?>" alt="<?php echo htmlspecialchars($related_site['name']); ?>" class="related-logo">
                                            <div>
                                                <h5 class="text-white mb-1"><?php echo htmlspecialchars($related_site['name']); ?></h5>
                                                <div class="d-flex align-items-center gap-2 mb-2">
                                                    <?php echo render_stars(round($related_site['average_rating']), '0.9rem'); ?>
                                                    <span class="text-muted small"><?php echo $related_site['review_count']; ?> reviews</span>
                                                </div>
                                                <a href="review?id=<?php echo $related_site['id']; ?>" class="btn btn-theme btn-outline-glass btn-sm"><i class="fas fa-arrow-right me-1"></i>View</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-lg-4">
                    <div class="glass-card p-4 p-lg-5 mb-4">
                        <h3 class="text-white mb-4"><i class="fas fa-star-half-stroke text-warning me-2"></i>Rating Breakdown</h3>
                        <div class="text-center mb-4">
                            <div class="display-4 fw-bold text-warning"><?php echo number_format($site['average_rating'], 1); ?></div>
                            <?php echo render_stars(round($site['average_rating']), '1.3rem'); ?>
                            <p class="text-muted mb-0">Based on <?php echo $site['review_count']; ?> reviews</p>
                        </div>
                        <?php if ($site['review_count'] > 0): ?>
                            <div class="rating-breakdown">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <?php
                                        $count_key = ['', 'one', 'two', 'three', 'four', 'five'][$i] . '_star_count';
                                        $count = $site[$count_key];
                                        $width = $site['review_count'] > 0 ? ($count / $site['review_count']) * 100 : 0;
                                    ?>
                                    <div class="rating-bar">
                                        <span><?php echo $i; ?>★</span>
                                        <div class="rating-bar-fill">
                                            <div class="rating-bar-progress" style="width: <?php echo $width; ?>%"></div>
                                        </div>
                                        <span><?php echo $count; ?></span>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="glass-card p-4 p-lg-5 mb-4">
                        <h3 class="text-white mb-4"><i class="fas fa-info-circle text-info me-2"></i>Site Insights</h3>
                        <div class="d-flex flex-column gap-3">
                            <div class="meta-item">
                                <i class="fas fa-tag"></i>
                                <div>
                                    <small class="text-muted text-uppercase">Category</small>
                                    <div class="text-white fw-semibold"><?php echo ucfirst(str_replace('_', ' ', $site['category'])); ?></div>
                                </div>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-signal"></i>
                                <div>
                                    <small class="text-muted text-uppercase">Status</small>
                                    <div><?php echo get_status_badge($site['status']); ?></div>
                                </div>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-user"></i>
                                <div>
                                    <small class="text-muted text-uppercase">Submitted By</small>
                                    <div class="text-white fw-semibold"><?php echo htmlspecialchars($site['submitted_by_username'] ?: 'Community Member'); ?></div>
                                </div>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-shield"></i>
                                <div>
                                    <small class="text-muted text-uppercase">Trust Score</small>
                                    <div class="text-white fw-semibold"><?php echo $trust_score; ?>%</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="glass-card p-4 p-lg-5 mb-4 widget-embed">
                        <h3 class="text-white mb-3"><i class="fas fa-code text-info me-2"></i>Embed Live Widget</h3>
                        <p class="text-muted">Show real-time trust stats on your website.</p>
                        <div class="mb-3">
                            <label class="form-label">Widget Type</label>
                            <select class="form-select" id="widgetType" onchange="updateWidgetCode()">
                                <option value="card">Classic Card</option>
                                <option value="modern">Modern Card</option>
                                <option value="banner">Banner Style</option>
                                <option value="compact">Compact</option>
                                <option value="badge">Status Badge</option>
                                <option value="trust">Trust Score</option>
                                <option value="minimal">Minimal</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Size</label>
                            <select class="form-select" id="widgetSize" onchange="updateWidgetCode()">
                                <option value="small">Small</option>
                                <option value="medium">Medium</option>
                                <option value="large">Large</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <button type="button" class="btn btn-theme btn-outline-glass btn-sm d-inline-flex align-items-center" id="advancedSettingsToggle">
                                <i class="fas fa-sliders me-2" id="advancedToggleIcon"></i>Advanced Settings
                            </button>
                        </div>
                        <div id="advancedSettings" style="display: none;" class="mb-3">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Color Scheme</label>
                                    <select class="form-select" id="widgetColor" onchange="updateWidgetCode()">
                                        <option value="blue">Blue</option>
                                        <option value="green">Green</option>
                                        <option value="purple">Purple</option>
                                        <option value="orange">Orange</option>
                                        <option value="red">Red</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Theme</label>
                                    <select class="form-select" id="widgetTheme" onchange="updateWidgetCode()">
                                        <option value="dark">Dark</option>
                                        <option value="light">Light</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Animation</label>
                                    <select class="form-select" id="widgetAnimation" onchange="updateWidgetCode()">
                                        <option value="hover">Hover Effect</option>
                                        <option value="pulse">Pulse</option>
                                        <option value="glow">Glow</option>
                                        <option value="none">None</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Border Radius</label>
                                    <select class="form-select" id="widgetBorderRadius" onchange="updateWidgetCode()">
                                        <option value="none">None</option>
                                        <option value="small">Small</option>
                                        <option value="medium">Medium</option>
                                        <option value="large">Large</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Button Text</label>
                                    <input type="text" class="form-control" id="widgetButtonText" value="Claim Bonus" onkeyup="updateWidgetCode()">
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="showLogo" checked onchange="updateWidgetCode()">
                                        <label class="form-check-label" for="showLogo">Show Logo</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="showRating" checked onchange="updateWidgetCode()">
                                        <label class="form-check-label" for="showRating">Show Rating</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="showTrust" checked onchange="updateWidgetCode()">
                                        <label class="form-check-label" for="showTrust">Show Trust Score</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="showReviews" checked onchange="updateWidgetCode()">
                                        <label class="form-check-label" for="showReviews">Show Review Count</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <iframe id="widgetPreview" class="w-100 rounded" height="260" src=""></iframe>
                        <pre class="mt-3 bg-dark text-white p-3 rounded" style="white-space: pre-wrap;" id="embedCode"></pre>
                        <button class="copy-btn mt-2" type="button" onclick="copyEmbedCode()"><i class="fas fa-copy me-2"></i>Copy Embed Code</button>
                    </div>

                    <div class="dev-slot1 mb-4">Sidebar Ad 300x600</div>
                    <div class="text-center mb-5">
                        <?php echo displayAdSpace($db, 'review_sidebar_1'); ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>


<div id="toast" class="toast">
    <i class="fas fa-check-circle"></i>
    <span id="toast-message">Vote recorded successfully!</span>
</div>

<script>
// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    initializeInteractiveRating();
});

function toggleReviewForm() {
    const formContainer = document.getElementById('reviewFormContainer');
    formContainer.classList.toggle('active');
    if (formContainer.classList.contains('active')) {
        formContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

// Interactive rating system
function initializeInteractiveRating() {
    const ratingContainer = document.getElementById('interactiveRating');
    const ratingInput = document.getElementById('ratingInput');
    if (ratingContainer && ratingInput) {
        const stars = ratingContainer.querySelectorAll('.star');
        stars.forEach((star, index) => {
            star.addEventListener('click', function() {
                const rating = index + 1;
                ratingInput.value = rating;
                updateStars(rating);
            });
            star.addEventListener('mouseenter', function() {
                const rating = index + 1;
                updateStars(rating, true);
            });
        });
        ratingContainer.addEventListener('mouseleave', function() {
            const currentRating = parseInt(ratingInput.value) || 0;
            updateStars(currentRating);
        });
        function updateStars(rating, isHover = false) {
            stars.forEach((star, index) => {
                star.classList.remove('filled', 'hover');
                if (index < rating) {
                    star.classList.add(isHover ? 'hover' : 'filled');
                }
            });
        }
    }
}

// Voting system - live update
function vote(targetId, voteType, targetType) {
    if (!<?php echo $auth->isLoggedIn() ? 'true' : 'false'; ?>) {
        showToast('Please login to vote', 'warning');
        return;
    }

    const containerSelector = (targetType === 'site')
        ? `[data-site-id="${targetId}"] .vote-buttons`
        : `.review-card[data-review-id="${targetId}"] .vote-buttons`;

    const container = document.querySelector(containerSelector);
    if (container) {
        container.querySelectorAll('button').forEach(btn => btn.disabled = true);
    }

    const voteData = {
        target_id: targetId,
        vote_type: voteType,
        target_type: targetType
    };

    fetch('ajax/vote.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(voteData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateVoteDisplay(targetId, data.data, targetType);
            showToast('Your vote has been counted!', 'success');
        } else {
            showToast(data.message || 'Vote could not be processed', 'error');
        }
    })
    .catch(error => {
        console.error('Voting error:', error);
        showToast('Error processing vote. Please try again.', 'error');
    })
    .finally(() => {
        if (container) {
            setTimeout(() => {
                container.querySelectorAll('button').forEach(btn => btn.disabled = false);
            }, 1000);
        }
    });
}

// Update vote counts and active state after a vote
function updateVoteDisplay(targetId, voteData, targetType) {
    let element;
    if (targetType === 'site') {
        element = document.querySelector(`[data-site-id="${targetId}"] .vote-buttons`);
        // Also update the site header upvotes count
        const siteUpvotes = document.getElementById('site-upvotes');
        if (siteUpvotes) {
            siteUpvotes.textContent = voteData.upvotes;
        }
    } else {
        element = document.querySelector(`.review-card[data-review-id="${targetId}"] .vote-buttons`);
    }
    if (!element) return;

    // Update counts
    const upvoteBtn = element.querySelector('.vote-btn.upvote .vote-count');
    const downvoteBtn = element.querySelector('.vote-btn.downvote .vote-count');
    if (upvoteBtn) upvoteBtn.textContent = voteData.upvotes;
    if (downvoteBtn) downvoteBtn.textContent = voteData.downvotes;

    // Reset and set active state
    const upvoteBtnEl = element.querySelector('.vote-btn.upvote');
    const downvoteBtnEl = element.querySelector('.vote-btn.downvote');
    if (upvoteBtnEl && downvoteBtnEl) {
        upvoteBtnEl.classList.remove('active');
        downvoteBtnEl.classList.remove('active');
        if (voteData.user_vote === 'upvote') {
            upvoteBtnEl.classList.add('active');
        } else if (voteData.user_vote === 'downvote') {
            downvoteBtnEl.classList.add('active');
        }
    }
}

// Reply functionality
function toggleReplyForm(reviewId) {
    const form = document.getElementById('replyForm' + reviewId);
    form.classList.toggle('active');
    if (form.classList.contains('active')) {
        document.getElementById('replyContent' + reviewId).focus();
    }
}

function submitReply(reviewId, event) { // Added 'event' parameter
    const content = document.getElementById('replyContent' + reviewId).value.trim();
    if (!content) {
        showToast('Please enter a reply', 'warning');
        return;
    }
    
    const submitBtn = event.target;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Posting...';
    
    fetch('ajax/submit-reply.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `review_id=${reviewId}&reply_content=${encodeURIComponent(content)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.reload) {
                location.reload();
            } else {
                showToast(data.message, 'success');
                toggleReplyForm(reviewId);
                document.getElementById('replyContent' + reviewId).value = '';
            }
        } else {
            showToast(data.message || 'Error submitting reply', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Post Reply';
        }
    })
    .catch(error => {
        console.error('Reply error:', error);
        showToast('Error submitting reply', 'error');
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Post Reply';
    });
}

// Copy embed code
function copyEmbedCode() {
    const embedCode = document.getElementById('embedCode').textContent;
    navigator.clipboard.writeText(embedCode).then(() => {
        const copyBtn = document.querySelector('.copy-btn');
        const originalText = copyBtn.innerHTML;
        copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        copyBtn.style.background = '#10b981';
        setTimeout(() => {
            copyBtn.innerHTML = originalText;
            copyBtn.style.background = '';
        }, 2000);
    });
}

function updateWidgetCode() {
    const type = document.getElementById('widgetType').value;
    const size = document.getElementById('widgetSize').value;
    const color = document.getElementById('widgetColor').value;
    const theme = document.getElementById('widgetTheme').value;
    const animation = document.getElementById('widgetAnimation').value;
    const borderRadius = document.getElementById('widgetBorderRadius').value;
    const buttonText = document.getElementById('widgetButtonText').value;
    const showLogo = document.getElementById('showLogo').checked ? '1' : '0';
    const showRating = document.getElementById('showRating').checked ? '1' : '0';
    const showTrust = document.getElementById('showTrust').checked ? '1' : '0';
    const showReviews = document.getElementById('showReviews').checked ? '1' : '0';
    
    const preview = document.getElementById('widgetPreview');
    const siteUrl = '<?php echo SITE_URL; ?>';
    
    const params = new URLSearchParams({
        site: '<?php echo $site_id; ?>',
        type: type,
        size: size,
        color: color,
        theme: theme,
        animation: animation,
        border_radius: borderRadius,
        button_text: buttonText,
        show_logo: showLogo,
        show_rating: showRating,
        show_trust: showTrust,
        show_reviews: showReviews
    });
    
    preview.src = `${siteUrl}/widget?${params.toString()}`;
    
    const embedCode = document.getElementById('embedCode');
    const widgetUrl = `${siteUrl}/widget?${params.toString()}`;
    
    // Determine appropriate dimensions based on widget type and size
    let width = '300', height = '400';
    if (type === 'banner') {
        width = size === 'small' ? '400' : size === 'large' ? '600' : '500';
        height = '120';
    } else if (type === 'compact') {
        width = size === 'small' ? '200' : size === 'large' ? '300' : '250';
        height = size === 'small' ? '200' : size === 'large' ? '300' : '250';
    } else if (type === 'minimal') {
        width = size === 'small' ? '140' : size === 'large' ? '200' : '160';
        height = size === 'small' ? '160' : size === 'large' ? '220' : '180';
    } else if (type === 'badge') {
        width = size === 'small' ? '160' : size === 'large' ? '220' : '180';
        height = size === 'small' ? '200' : size === 'large' ? '280' : '240';
    } else if (type === 'trust') {
        width = size === 'small' ? '180' : size === 'large' ? '240' : '200';
        height = size === 'small' ? '220' : size === 'large' ? '300' : '260';
    } else {
        // card, modern
        width = size === 'small' ? '250' : size === 'large' ? '400' : '300';
        height = size === 'small' ? '320' : size === 'large' ? '480' : '400';
    }
    
    embedCode.textContent = `<iframe src="${widgetUrl}" width="${width}" height="${height}" frameborder="0"></iframe>`;
}

// Toast notification system
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toast-message');
    
    // Set message
    toastMessage.textContent = message;
    
    // Set icon based on type
    const icon = toast.querySelector('i');
    icon.className = type === 'success' ? 'fas fa-check-circle' : 
                   type === 'warning' ? 'fas fa-exclamation-circle' : 
                   'fas fa-times-circle';
    
    // Set background color based on type
    toast.style.background = type === 'success' ? 'rgba(16, 185, 129, 0.9)' : 
                            type === 'warning' ? 'rgba(245, 158, 11, 0.9)' : 
                            'rgba(239, 68, 68, 0.9)';
    
    // Show toast
    toast.classList.add('show');
    
    // Hide after 3 seconds
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// Widget options init
document.addEventListener('DOMContentLoaded', function() {
    initializeInteractiveRating(); // Ensure this is called on DOMContentLoaded
    const urlParams = new URLSearchParams(window.location.search);
    const type = urlParams.get('widget_type') || 'card';
    const theme = urlParams.get('widget_theme') || 'dark';
    if (document.getElementById('widgetType')) {
        document.getElementById('widgetType').value = type;
    }
    if (document.getElementById('widgetTheme')) {
        document.getElementById('widgetTheme').value = theme;
    }
    updateWidgetCode();

    // Advanced Settings Toggle
    const advancedSettingsToggle = document.getElementById('advancedSettingsToggle');
    const advancedSettings = document.getElementById('advancedSettings');
    const toggleIcon = document.getElementById('advancedToggleIcon');
    
    if (advancedSettingsToggle && advancedSettings && toggleIcon) {
        advancedSettingsToggle.addEventListener('click', function() {
            const isHidden = advancedSettings.style.display === 'none';
            
            if (isHidden) {
                advancedSettings.style.display = 'block';
                toggleIcon.style.transform = 'rotate(90deg)';
                toggleIcon.classList.remove('fa-chevron-right');
                toggleIcon.classList.add('fa-chevron-down');
            } else {
                advancedSettings.style.display = 'none';
                toggleIcon.style.transform = 'rotate(0deg)';
                toggleIcon.classList.remove('fa-chevron-down');
                toggleIcon.classList.add('fa-chevron-right');
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>