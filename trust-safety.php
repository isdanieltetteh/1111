<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();

// Get trust & safety statistics
$stats_query = "SELECT
                (SELECT COUNT(*) FROM sites WHERE is_approved = 1 AND status = 'paying') as legitimate_sites,
                (SELECT COUNT(*) FROM sites WHERE is_approved = 1) as total_sites,
                (SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as protected_users,
                (SELECT COUNT(*) FROM sites WHERE status = 'scam_reported') as reported_scam_sites,
                (SELECT COUNT(*) FROM sites WHERE status = 'scam') as confirmed_scam_sites";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Calculate legitimacy percentage
$legitimacy_rate = $stats['total_sites'] > 0 ? round(($stats['legitimate_sites'] / $stats['total_sites']) * 100, 1) : 0;

// Get recent scam detection examples (anonymized)
$recent_detections_query = "SELECT s.name, s.status, s.scam_reports_count, s.total_reviews_for_scam, s.updated_at
                           FROM sites s
                           WHERE s.status IN ('scam_reported', 'scam')
                           ORDER BY s.updated_at DESC
                           LIMIT 5";
$recent_detections_stmt = $db->prepare($recent_detections_query);
$recent_detections_stmt->execute();
$recent_detections = $recent_detections_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Trust & Safety - ' . SITE_NAME;
$page_description = 'Learn how ' . SITE_NAME . ' protects our community through advanced detection systems, transparent processes, and collaborative moderation.';
$page_keywords = 'trust, safety, scam protection, crypto security, site verification, community protection';

$additional_head = '';

include 'includes/header.php';
?>

<main class="page-wrapper flex-grow-1">
    <section class="page-hero text-white">
        <div class="container">
            <div class="hero-content" data-aos="fade-up">
                <div class="hero-badge mb-4">
                    <i class="fas fa-shield-halved"></i>
                    <span>Security &amp; Moderation</span>
                </div>
                <h1 class="hero-title mb-4">Trust &amp; Safety Center</h1>
                <p class="hero-lead mb-5">We combine automated detection with community intelligence to keep fraudulent platforms out of the ecosystem.</p>
                <div class="row g-3 justify-content-center">
                    <div class="col-6 col-md-3">
                        <div class="trust-stat-card">
                            <div class="label">Legitimacy Rate</div>
                            <div class="value"><?php echo $legitimacy_rate; ?>%</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="trust-stat-card">
                            <div class="label">Verified Paying</div>
                            <div class="value"><?php echo number_format($stats['legitimate_sites']); ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="trust-stat-card">
                            <div class="label">Reports This Month</div>
                            <div class="value"><?php echo number_format($stats['reported_scam_sites']); ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="trust-stat-card">
                            <div class="label">Users Protected</div>
                            <div class="value"><?php echo number_format($stats['protected_users']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container my-5">
        <div class="dev-slot">Leader Board Ad Slot 970x250</div>
    </div>

    <section class="py-5">
        <div class="container trust-detail-grid">
            <div class="d-flex flex-column gap-4" data-aos="fade-right">
                <div class="glass-card p-4 p-lg-5">
                    <h2 class="section-heading h4 text-white mb-3">How We Classify Sites</h2>
                    <p class="text-muted mb-4">Every platform passes through layered verification—live uptime checks, backlink audits, community sentiment, and moderator review.</p>
                    <div class="status-grid">
                        <div class="status-card">
                            <div class="status-icon"><i class="fas fa-check-circle"></i></div>
                            <h4 class="h6 text-white mb-2">Verified Paying</h4>
                            <p>Consistent payouts, strong reviews, and zero pending scam flags.</p>
                        </div>
                        <div class="status-card">
                            <div class="status-icon"><i class="fas fa-exclamation-triangle"></i></div>
                            <h4 class="h6 text-white mb-2">Under Investigation</h4>
                            <p>Triggered when ≥80% of reviews mark a site as scam or when automated monitors detect risk.</p>
                        </div>
                        <div class="status-card">
                            <div class="status-icon"><i class="fas fa-times-circle"></i></div>
                            <h4 class="h6 text-white mb-2">Confirmed Scam</h4>
                            <p>Community evidence validated by moderators. Sites are delisted and publicly flagged.</p>
                        </div>
                    </div>
                    <div class="trust-highlight mt-4">
                        <strong>Transparency First:</strong> Status changes trigger notifications for followers and appear instantly in site timelines.
                    </div>
                </div>

                <div class="timeline-card">
                    <h3 class="h5 text-white mb-3"><i class="fas fa-wave-square me-2 text-info"></i>Investigation Workflow</h3>
                    <div class="timeline-step">
                        <div class="index">1</div>
                        <div>
                            <h4 class="h6 text-white mb-1">Community Signal</h4>
                            <p>Reports, scam-marked reviews, and automated uptime monitors flag suspicious behaviour.</p>
                        </div>
                    </div>
                    <div class="timeline-step">
                        <div class="index">2</div>
                        <div>
                            <h4 class="h6 text-white mb-1">Automated Scoring</h4>
                            <p>Risk scoring engine cross-checks payment proofs, velocity of reports, and backlink compliance.</p>
                        </div>
                    </div>
                    <div class="timeline-step">
                        <div class="index">3</div>
                        <div>
                            <h4 class="h6 text-white mb-1">Moderator Review</h4>
                            <p>Trust team validates evidence, contacts site owners when needed, and issues a final decision.</p>
                        </div>
                    </div>
                    <div class="trust-process-note">Average investigation time: under 12 hours for escalated cases.</div>
                </div>

                <div class="glass-card p-4 p-lg-5">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
                        <h3 class="h5 text-white mb-0"><i class="fas fa-radar me-2 text-warning"></i>Recent Scam Reports</h3>
                        <span class="badge bg-warning text-dark">Live feed</span>
                    </div>
                    <?php if (!empty($recent_detections)): ?>
                        <?php foreach ($recent_detections as $detection): ?>
                            <div class="detection-card">
                                <h4 class="h6 text-white mb-2"><?php echo htmlspecialchars($detection['name']); ?></h4>
                                <?php
                                    $status_labels = [
                                        'scam_reported' => 'Under Investigation',
                                        'scam' => 'Confirmed Scam'
                                    ];
                                    $status = $detection['status'];
                                ?>
                                <span class="badge bg-danger me-2"><i class="fas fa-virus me-1"></i><?php echo $status_labels[$status] ?? ucfirst($status); ?></span>
                                <div class="meta">
                                    <span><i class="fas fa-user-secret me-1"></i><?php echo number_format($detection['scam_reports_count']); ?> community reports</span>
                                    <span><i class="fas fa-comments me-1"></i><?php echo number_format($detection['total_reviews_for_scam']); ?> total reviews</span>
                                    <span><i class="fas fa-clock me-1"></i><?php echo date('M j, Y', strtotime($detection['updated_at'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="detection-card text-muted">No recent escalations. Keep reporting suspicious behaviour to protect the community.</div>
                    <?php endif; ?>
                </div>

                <div class="safety-callout" data-aos="fade-up">
                    <h3 class="h5 text-white mb-3"><i class="fas fa-users-shield me-2"></i>Community Safety Pledge</h3>
                    <p class="mb-4">Every review, report, and upvote strengthens our collective defence. Stay vigilant and let us know when a platform changes behaviour.</p>
                    <div class="legal-actions">
                        <a href="review" class="btn btn-theme btn-outline-glass"><i class="fas fa-comment-exclamation me-2"></i>Report a Scam</a>
                        <a href="submit-site" class="btn btn-theme btn-gradient"><i class="fas fa-paper-plane me-2"></i>Submit a Site</a>
                    </div>
                </div>
            </div>

            <div class="trust-aside-grid" data-aos="fade-left">
                <div class="trust-side-card">
                    <h5><i class="fas fa-lock me-2 text-info"></i>Safety Checklist</h5>
                    <ul>
                        <li>Verify “Paying” status and recent payout proofs before engaging.</li>
                        <li>Enable email notifications for status changes on your favourite sites.</li>
                        <li>Never share private keys or seed phrases with any third party.</li>
                        <li>Set unique passwords and enable 2FA wherever possible.</li>
                    </ul>
                </div>
                <div class="trust-side-card">
                    <h5><i class="fas fa-bell me-2 text-warning"></i>Alert System</h5>
                    <div class="trust-alert mb-3">
                        <i class="fas fa-radiation"></i>
                        <span>Sites with unresolved scam flags are automatically hidden from promotions and payouts.</span>
                    </div>
                    <p class="text-muted mb-0">Follow sites to receive instant email alerts the moment their status changes.</p>
                </div>
                <div class="trust-side-card">
                    <h5><i class="fas fa-question-circle me-2 text-success"></i>Need Help?</h5>
                    <p class="text-muted">Our moderators respond to escalations within 12 hours and provide detailed outcomes in your notifications feed.</p>
                    <a href="contact" class="btn btn-theme btn-outline-glass w-100 mt-3"><i class="fas fa-headset me-2"></i>Contact Safety Team</a>
                    <div class="dev-slot2 mt-4">Inline Safety Ad 300x250</div>
                </div>
                <div class="dev-slot1">Sidebar Guardian Ad 300x600</div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
