<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get and validate secure token
$token = $_GET['token'] ?? '';
$redirect_url = $_GET['url'] ?? '';

if (empty($token) || empty($redirect_url)) {
    header('Location: sites.php');
    exit();
}

// Validate token
$token_query = "SELECT svt.*, s.name, s.id as site_id
               FROM secure_visit_tokens svt
               JOIN sites s ON svt.site_id = s.id
               WHERE svt.token = :token AND svt.expires_at > NOW() AND svt.used_at IS NULL";
$stmt = $db->prepare($token_query);
$stmt->bindParam(':token', $token);
$stmt->execute();
$token_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$token_data) {
    header('Location: sites.php');
    exit();
}

$site_id = $token_data['site_id'];
$site_name = $token_data['name'];

// Mark token as used
$mark_used_query = "UPDATE secure_visit_tokens SET used_at = NOW() WHERE token = :token";
$mark_used_stmt = $db->prepare($mark_used_query);
$mark_used_stmt->bindParam(':token', $token);
$mark_used_stmt->execute();

// Get redirect ads from admin settings
$ads_query = "SELECT * FROM redirect_ads WHERE is_active = 1 ORDER BY sort_order ASC";
$ads_stmt = $db->prepare($ads_query);
$ads_stmt->execute();
$ads = $ads_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get redirect settings
$settings_query = "SELECT * FROM redirect_settings WHERE id = 1";
$settings_stmt = $db->prepare($settings_query);
$settings_stmt->execute();
$settings = $settings_stmt->fetch(PDO::FETCH_ASSOC);

$countdown_time = $settings['countdown_seconds'] ?? 10;
$redirect_message = $settings['redirect_message'] ?? 'You will be redirected to the site in {seconds} seconds...';

// Update click count and log
$update_query = "UPDATE sites SET clicks = clicks + 1 WHERE id = :id";
$update_stmt = $db->prepare($update_query);
$update_stmt->bindParam(':id', $site_id);
$update_stmt->execute();

// Log click for analytics
$log_query = "INSERT INTO site_clicks (site_id, ip_address, user_agent, clicked_at) VALUES (:site_id, :ip, :user_agent, NOW())";
$log_stmt = $db->prepare($log_query);
$log_stmt->bindParam(':site_id', $site_id);
$log_stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR']);
$log_stmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT']);
$log_stmt->execute();

// Validate redirect URL for security
if (!filter_var($redirect_url, FILTER_VALIDATE_URL)) {
    header('Location: sites.php');
    exit();
}

$page_title = 'Redirecting to ' . htmlspecialchars($site_name) . ' - ' . SITE_NAME;
$page_description = 'You are being redirected to ' . htmlspecialchars($site_name) . '. Please wait...';
$page_keywords = '';
$current_page = '';
$additional_head = '
    <meta name="robots" content="noindex, nofollow">
';

$redirect_host = parse_url($redirect_url, PHP_URL_HOST) ?? $redirect_url;

include 'includes/header.php';
?>

<main class="page-wrapper flex-grow-1">
    <section class="page-hero page-hero-compact text-white text-center">
        <div class="container" data-aos="fade-up">
            <div class="hero-badge mb-3">
                <i class="fas fa-shield-halved"></i>
                <span>Secure handoff by <?php echo SITE_NAME; ?></span>
            </div>
            <h1 class="hero-title h2 mb-3">Redirecting you to <?php echo htmlspecialchars($site_name); ?></h1>
            <p class="hero-lead text-muted mx-auto" style="max-width: 640px;">
                We run safety checks on every outbound visit. Review the countdown or jump ahead when you're ready.
            </p>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="row g-4 align-items-start">
                <div class="col-lg-7">
                    <div class="redirect-surface glass-card p-4 p-lg-5 text-center" data-aos="fade-up">
                        <div class="mb-4">
                            <div class="d-inline-flex align-items-center gap-2 px-3 py-2 rounded-pill redirect-chip">
                                <i class="fas fa-route text-info"></i>
                                <span class="text-white fw-semibold">Verified Redirect</span>
                            </div>
                            <h2 class="text-white fw-bold mt-3 mb-2">Preparing <?php echo htmlspecialchars($site_name); ?></h2>
                            <p class="text-muted mb-0">Support our monitoring efforts by keeping this window open until the transfer completes.</p>
                        </div>

                        <div class="redirect-countdown mx-auto mb-4" id="countdownCircle">
                            <span id="countdownNumber"><?php echo $countdown_time; ?></span>
                        </div>

                        <div class="redirect-progress mb-4">
                            <div class="progress-fill" id="progressFill" style="width: 0%"></div>
                        </div>

                        <p class="redirect-meta mb-4" id="redirectMessage">
                            <?php echo str_replace('{seconds}', '<span id="secondsText">' . $countdown_time . '</span>', $redirect_message); ?>
                        </p>

                        <div class="redirect-actions mb-4">
                            <a href="<?php echo htmlspecialchars($redirect_url); ?>" class="btn btn-theme btn-gradient" id="visitNowBtn" target="_blank" rel="nofollow">
                                <i class="fas fa-arrow-up-right-from-square me-2"></i>Visit Now
                            </a>
                            <a href="review.php?id=<?php echo $site_id; ?>" class="btn btn-theme btn-outline-glass">
                                <i class="fas fa-arrow-left me-2"></i>Back to Review
                            </a>
                        </div>

                        <?php if (!empty($ads)): ?>
                            <div class="row g-3">
                                <?php foreach ($ads as $ad): ?>
                                    <div class="col-12">
                                        <div class="redirect-ad-slot">
                                            <div class="ad-content">
                                                <?php if ($ad['type'] === 'image' && $ad['image_url']): ?>
                                                    <?php if ($ad['link_url']): ?>
                                                        <a href="<?php echo htmlspecialchars($ad['link_url']); ?>" target="_blank" rel="nofollow">
                                                            <img src="<?php echo htmlspecialchars($ad['image_url']); ?>" alt="<?php echo htmlspecialchars($ad['title']); ?>">
                                                        </a>
                                                    <?php else: ?>
                                                        <img src="<?php echo htmlspecialchars($ad['image_url']); ?>" alt="<?php echo htmlspecialchars($ad['title']); ?>">
                                                    <?php endif; ?>
                                                <?php elseif ($ad['type'] === 'html' && $ad['html_content']): ?>
                                                    <?php echo $ad['html_content']; ?>
                                                <?php else: ?>
                                                    <div class="text-muted">
                                                        <i class="fas fa-ad fa-2x mb-2"></i>
                                                        <div>Advertisement Space</div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="redirect-ad-slot">
                                <div class="text-muted">
                                    <i class="fas fa-ad fa-2x mb-2"></i>
                                    <div><strong>Promote Your Site Here</strong></div>
                                    <small>Contact admin to place your ad on the redirect screen.</small>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="redirect-meta mt-4">
                            <i class="fas fa-shield-halved me-2"></i>This secure jump helps keep <?php echo SITE_NAME; ?> free for the community.
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="glass-card p-4 h-100 d-flex flex-column gap-4 redirect-intel" data-aos="fade-up" data-aos-delay="50">
                        <div class="d-flex align-items-center gap-3">
                            <div class="redirect-avatar">
                                <i class="fas fa-satellite-dish"></i>
                            </div>
                            <div class="text-start">
                                <span class="text-uppercase small text-muted">Destination</span>
                                <h3 class="h5 text-white mb-1"><?php echo htmlspecialchars($redirect_host); ?></h3>
                                <a href="<?php echo htmlspecialchars($redirect_url); ?>" class="text-info small" target="_blank" rel="nofollow noopener">
                                    <?php echo htmlspecialchars($redirect_url); ?>
                                </a>
                            </div>
                        </div>
                        <div class="redirect-metrics">
                            <div class="metric-card">
                                <span class="metric-label">Countdown</span>
                                <span class="metric-value"><span id="metricSeconds"><?php echo $countdown_time; ?></span>s</span>
                            </div>
                            <div class="metric-card">
                                <span class="metric-label">Token</span>
                                <span class="metric-value">Active</span>
                            </div>
                            <div class="metric-card">
                                <span class="metric-label">Stay Safe</span>
                                <span class="metric-value">Verified</span>
                            </div>
                        </div>
                        <div class="dev-slot1 text-center">Sidebar Ad 300x250</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container pb-5">
        <div class="dev-slot">Footer Banner Ad 970x90</div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>

<script>
    let countdown = <?php echo $countdown_time; ?>;
    const totalTime = <?php echo $countdown_time; ?>;
    const redirectUrl = <?php echo json_encode($redirect_url); ?>;

    const countdownNumber = document.getElementById('countdownNumber');
    const secondsText = document.getElementById('secondsText');
    const metricSeconds = document.getElementById('metricSeconds');
    const progressFill = document.getElementById('progressFill');
    const countdownCircle = document.getElementById('countdownCircle');

    function updateCountdown() {
        countdownNumber.textContent = countdown;
        if (secondsText) secondsText.textContent = countdown;
        if (metricSeconds) metricSeconds.textContent = countdown;

        const progress = ((totalTime - countdown) / totalTime) * 100;
        progressFill.style.width = progress + '%';

        const degrees = (progress / 100) * 360;
        countdownCircle.style.background = `conic-gradient(var(--color-primary) ${degrees}deg, rgba(12, 25, 52, 0.6) ${degrees}deg)`;

        if (countdown <= 0) {
            window.location.href = redirectUrl;
            return;
        }

        countdown--;
        setTimeout(updateCountdown, 1000);
    }

    setTimeout(updateCountdown, 1000);

    document.getElementById('visitNowBtn').addEventListener('click', function() {
        console.log('Manual click tracked');
    });
</script>
