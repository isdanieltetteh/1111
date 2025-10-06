<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

// Handle support ticket submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $priority = $_POST['priority'] ?? 'medium';
    
    // Validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error_message = 'Please fill in all fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address';
    } else {
        // Insert support ticket
        $user_id = $auth->isLoggedIn() ? $_SESSION['user_id'] : null;
        
        $insert_query = "INSERT INTO support_tickets (user_id, name, email, subject, message, priority) 
                        VALUES (:user_id, :name, :email, :subject, :message, :priority)";
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bindParam(':user_id', $user_id);
        $insert_stmt->bindParam(':name', $name);
        $insert_stmt->bindParam(':email', $email);
        $insert_stmt->bindParam(':subject', $subject);
        $insert_stmt->bindParam(':message', $message);
        $insert_stmt->bindParam(':priority', $priority);
        
        if ($insert_stmt->execute()) {
            $ticket_id = $db->lastInsertId();
            $success_message = "Thank you for contacting us! Your support ticket #{$ticket_id} has been created. We'll respond within 24 hours.";
            
            // Clear form
            $_POST = [];
        } else {
            $error_message = 'Error submitting your message. Please try again.';
        }
    }
}

$page_title = 'Support Tickets - ' . SITE_NAME;
$page_description = 'Submit a support ticket for help with your account or technical issues.';
$current_page = 'dashboard';
include 'includes/header.php';
?>

<div class="page-wrapper flex-grow-1">
    <section class="page-hero pb-0">
        <div class="container">
            <div class="glass-card p-4 p-lg-5 animate-fade-in" data-aos="fade-up">
                <div class="d-flex flex-column flex-lg-row align-items-lg-start justify-content-between gap-4">
                    <div class="flex-grow-1">
                        <div class="dashboard-breadcrumb mb-3">
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">Support</li>
                                </ol>
                            </nav>
                        </div>
                        <h1 class="text-white fw-bold mb-2">Support Center</h1>
                        <p class="text-muted mb-0">Open a ticket and our response team will guide you through any issue.</p>
                    </div>
                    <div class="text-lg-end">
                        <div class="option-chip justify-content-center ms-lg-auto">
                            <i class="fas fa-life-ring"></i>
                            <span>Live assistance 24/7</span>
                        </div>
                        <a href="faq.php" class="btn btn-theme btn-outline-glass mt-3">
                            <i class="fas fa-book-open me-2"></i>Browse FAQs
                        </a>
                    </div>
                </div>
            </div>
            <div class="dev-slot mt-4">Hero Banner 970x250</div>
        </div>
    </section>

    <section class="py-4">
        <div class="container">
            <?php
            $dashboard_nav_links = [
                [
                    'href' => 'dashboard.php',
                    'icon' => 'fa-gauge-high',
                    'label' => 'Overview',
                    'description' => 'Insights & rewards summary'
                ],
                [
                    'href' => 'my-submissions.php',
                    'icon' => 'fa-globe',
                    'label' => 'My Submissions',
                    'description' => 'Manage and update your listings'
                ],
                [
                    'href' => 'my-ads.php',
                    'icon' => 'fa-rectangle-ad',
                    'label' => 'My Campaigns',
                    'description' => 'Track ad performance & status'
                ],
                [
                    'href' => 'notifications.php',
                    'icon' => 'fa-bell',
                    'label' => 'Notifications',
                    'description' => 'Review alerts & platform updates'
                ],
                [
                    'href' => 'wallet.php',
                    'icon' => 'fa-wallet',
                    'label' => 'Wallet',
                    'description' => 'Monitor credits & transactions'
                ],
                [
                    'href' => 'support-tickets.php',
                    'icon' => 'fa-life-ring',
                    'label' => 'Support',
                    'description' => 'Submit & follow support tickets'
                ],
                [
                    'href' => 'promote-sites.php',
                    'icon' => 'fa-rocket',
                    'label' => 'Promotions',
                    'description' => 'Boost visibility with premium slots'
                ],
                [
                    'href' => 'buy-credits.php',
                    'icon' => 'fa-credit-card',
                    'label' => 'Buy Credits',
                    'description' => 'Top up instantly for upgrades'
                ],
                [
                    'href' => 'redeem-coupon.php',
                    'icon' => 'fa-ticket',
                    'label' => 'Redeem Coupons',
                    'description' => 'Apply promo codes for bonuses'
                ],
                [
                    'href' => 'profile.php',
                    'icon' => 'fa-user-gear',
                    'label' => 'Account Settings',
                    'description' => 'Update profile & security details'
                ]
            ];
            $dashboard_nav_current = basename($_SERVER['PHP_SELF'] ?? '');
            ?>
            <div class="glass-card p-4 p-lg-5 mb-4" data-aos="fade-up">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                    <div>
                        <h2 class="h5 text-white mb-1">Navigate Your Toolkit</h2>
                        <p class="text-muted mb-0">Quick links to every dashboard feature.</p>
                    </div>
                    <a href="promote-sites.php" class="btn btn-theme btn-outline-glass btn-sm">
                        <i class="fas fa-bullhorn me-2"></i>Promote Listings
                    </a>
                </div>
                <div class="row g-3 row-cols-2 row-cols-sm-3 row-cols-lg-4 row-cols-xl-5 dashboard-nav-grid">
                    <?php foreach ($dashboard_nav_links as $link): ?>
                        <div class="col">
                            <a class="dashboard-nav-tile <?php echo $dashboard_nav_current === basename($link['href']) ? 'active' : ''; ?>"
                               href="<?php echo htmlspecialchars($link['href']); ?>">
                                <span class="tile-icon"><i class="fas <?php echo htmlspecialchars($link['icon']); ?>"></i></span>
                                <span class="tile-label"><?php echo htmlspecialchars($link['label']); ?></span>
                                <span class="tile-desc text-muted"><?php echo htmlspecialchars($link['description']); ?></span>
                                <span class="tile-arrow"><i class="fas fa-arrow-right"></i></span>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="pb-5">
        <div class="container">
            <div class="dev-slot2 mb-4">Inline Ad 728x90</div>

            <?php if ($error_message): ?>
                <div class="alert alert-glass alert-danger mb-4" role="alert">
                    <span class="icon text-danger"><i class="fas fa-exclamation-circle"></i></span>
                    <div><?php echo htmlspecialchars($error_message); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-glass alert-success mb-4" role="alert">
                    <span class="icon text-success"><i class="fas fa-check-circle"></i></span>
                    <div><?php echo htmlspecialchars($success_message); ?></div>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-xl-8">
                    <div class="glass-card p-4 p-lg-5 h-100 animate-fade-in" data-aos="fade-up">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
                            <div>
                                <h2 class="h4 text-white mb-1">Submit a Support Ticket</h2>
                                <p class="text-muted mb-0">Tell us what happened and we’ll reach out within the listed response window.</p>
                            </div>
                            <div class="option-chip">
                                <i class="fas fa-shield-halved"></i>
                                <span>Secure & confidential</span>
                            </div>
                        </div>

                        <form method="POST" class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label text-uppercase small text-muted">Your Name</label>
                                <input type="text"
                                       id="name"
                                       name="name"
                                       class="form-control form-control-lg"
                                       placeholder="Enter your full name"
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ($auth->isLoggedIn() ? $auth->getCurrentUser()['username'] : '')); ?>"
                                       required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label text-uppercase small text-muted">Email Address</label>
                                <input type="email"
                                       id="email"
                                       name="email"
                                       class="form-control form-control-lg"
                                       placeholder="Enter your email address"
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ($auth->isLoggedIn() ? $auth->getCurrentUser()['email'] : '')); ?>"
                                       required>
                            </div>
                            <div class="col-12">
                                <label for="subject" class="form-label text-uppercase small text-muted">Subject</label>
                                <input type="text"
                                       id="subject"
                                       name="subject"
                                       class="form-control form-control-lg"
                                       placeholder="Brief description of your issue"
                                       value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>"
                                       required>
                            </div>
                            <div class="col-12 col-lg-6">
                                <label for="priority" class="form-label text-uppercase small text-muted">Priority</label>
                                <div class="input-glass">
                                    <select id="priority" name="priority" class="form-select form-select-lg">
                                        <option value="low" <?php echo ($_POST['priority'] ?? '') === 'low' ? 'selected' : ''; ?>>Low - General inquiry</option>
                                        <option value="medium" <?php echo ($_POST['priority'] ?? 'medium') === 'medium' ? 'selected' : ''; ?>>Medium - Account issue</option>
                                        <option value="high" <?php echo ($_POST['priority'] ?? '') === 'high' ? 'selected' : ''; ?>>High - Urgent problem</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-12">
                                <label for="message" class="form-label text-uppercase small text-muted">Message</label>
                                <textarea id="message"
                                          name="message"
                                          class="form-control form-control-lg"
                                          rows="6"
                                          placeholder="Please describe your issue in detail..."
                                          required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-theme btn-gradient w-100">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Ticket
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="col-xl-4">
                    <div class="d-flex flex-column gap-4">
                        <div class="glass-card p-4 animate-fade-in" data-aos="fade-up" data-aos-delay="100">
                            <h3 class="h5 text-white mb-3">Common Topics</h3>
                            <div class="d-flex flex-column gap-3 text-muted small">
                                <div class="d-flex gap-3">
                                    <span class="badge rounded-pill bg-info-subtle text-info-emphasis"><i class="fas fa-user-shield"></i></span>
                                    <div>
                                        <strong class="d-block text-white-50">Account Problems</strong>
                                        <span>Login issues, password resets, account verification</span>
                                    </div>
                                </div>
                                <div class="d-flex gap-3">
                                    <span class="badge rounded-pill bg-success-subtle text-success"><i class="fas fa-globe"></i></span>
                                    <div>
                                        <strong class="d-block text-white-50">Site Submissions</strong>
                                        <span>Approval status, backlink verification, duplicate listings</span>
                                    </div>
                                </div>
                                <div class="d-flex gap-3">
                                    <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis"><i class="fas fa-wallet"></i></span>
                                    <div>
                                        <strong class="d-block text-white-50">Wallet & Withdrawals</strong>
                                        <span>Points balance, withdrawal requests, payment timing</span>
                                    </div>
                                </div>
                                <div class="d-flex gap-3">
                                    <span class="badge rounded-pill bg-danger-subtle text-danger"><i class="fas fa-exclamation-triangle"></i></span>
                                    <div>
                                        <strong class="d-block text-white-50">Scam Reports</strong>
                                        <span>Flag fraudulent sites and appeal trust decisions</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="glass-card p-4 animate-fade-in" data-aos="fade-up" data-aos-delay="200">
                            <h3 class="h5 text-white mb-3">Response Expectations</h3>
                            <ul class="list-unstyled mb-0 text-muted small">
                                <li class="d-flex align-items-center justify-content-between py-2 border-bottom border-opacity-25">
                                    <span><i class="fas fa-hourglass-half me-2 text-success"></i>Low Priority</span>
                                    <span class="text-success">48-72 hrs</span>
                                </li>
                                <li class="d-flex align-items-center justify-content-between py-2 border-bottom border-opacity-25">
                                    <span><i class="fas fa-clock me-2 text-warning"></i>Medium Priority</span>
                                    <span class="text-warning">24-48 hrs</span>
                                </li>
                                <li class="d-flex align-items-center justify-content-between pt-2">
                                    <span><i class="fas fa-bolt me-2 text-danger"></i>High Priority</span>
                                    <span class="text-danger">2-24 hrs</span>
                                </li>
                            </ul>
                        </div>
                        <div class="glass-card p-4 animate-fade-in" data-aos="fade-up" data-aos-delay="300">
                            <h3 class="h5 text-white mb-3">Before You Submit</h3>
                            <ul class="list-unstyled text-muted small mb-0">
                                <li class="d-flex gap-2 mb-2">
                                    <i class="fas fa-search text-info"></i>
                                    <span>Review the <a href="faq.php" class="link-light">FAQ</a> to see if your question is covered.</span>
                                </li>
                                <li class="d-flex gap-2 mb-2">
                                    <i class="fas fa-copy text-info"></i>
                                    <span>Include URLs, screenshots, and transaction IDs for faster help.</span>
                                </li>
                                <li class="d-flex gap-2 mb-2">
                                    <i class="fas fa-shield-halved text-info"></i>
                                    <span>Tickets are encrypted and only visible to our trust team.</span>
                                </li>
                                <li class="d-flex gap-2">
                                    <i class="fas fa-envelope-open text-info"></i>
                                    <span>Watch for a reply from <strong>support@<?php echo strtolower(parse_url(SITE_URL, PHP_URL_HOST)); ?></strong>.</span>
                                </li>
                            </ul>
                        </div>
                        <div class="dev-slot1">Sidebar Ad 300x600</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>
