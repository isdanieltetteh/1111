<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message   = '';

// Handle success from redirect (PRG)
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $ticket_id = $_GET['ticket_id'] ?? '';
    $success_message = "Thank you for contacting us! Your support ticket #{$ticket_id} has been created. We'll respond within 24 hours.";
}

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify captcha
    $captcha_valid = false;

    if (!empty($_POST['h-captcha-response'])) {
        $captcha_response = $_POST['h-captcha-response'];
        $secret_key = HCAPTCHA_SECRET_KEY;

        $verify_url = 'https://hcaptcha.com/siteverify';
        $data = [
            'secret'   => $secret_key,
            'response' => $captcha_response,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ];

        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data)
            ]
        ];

        $context  = stream_context_create($options);
        $result   = file_get_contents($verify_url, false, $context);
        $response = json_decode($result, true);

        $captcha_valid = $response['success'] ?? false;
    }

    if (!$captcha_valid) {
        $error_message = 'Please complete the captcha verification';
    } else {
        $name     = trim($_POST['name']);
        $email    = trim($_POST['email']);
        $subject  = trim($_POST['subject']);
        $message  = trim($_POST['message']);
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

                // Redirect to prevent resubmission
                header("Location: contact?success=1&ticket_id=" . urlencode($ticket_id));
                exit;
            } else {
                $error_message = 'Error submitting your message. Please try again.';
            }
        }
    }
}

$page_title = 'Contact Us - ' . SITE_NAME;
$page_description = 'Get in touch with the ' . SITE_NAME . ' team. We\'re here to help with any questions or concerns.';

$additional_head = '
    <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
';

include 'includes/header.php';
?>

<main class="page-wrapper flex-grow-1">
    <section class="page-hero text-white text-center">
        <div class="container">
            <div class="hero-content mx-auto" data-aos="fade-up">
                <div class="hero-badge mb-4">
                    <i class="fas fa-headset"></i>
                    <span>Support Desk</span>
                </div>
                <h1 class="hero-title mb-4">Contact Us</h1>
                <p class="hero-lead">We're here to help with submissions, reviews, wallet withdrawals, and advertising support.</p>
            </div>
        </div>
    </section>

    <div class="container my-5">
        <div class="dev-slot">Premium Support Ad Slot 970x250</div>
    </div>

    <section class="py-5">
        <div class="container">
            <div class="row g-4 g-lg-5 align-items-start">
                <div class="col-lg-7">
                    <div class="glass-card p-4 p-lg-5 contact-form" data-aos="fade-right">
                        <h2 class="section-heading h4 text-white mb-3">Send Us a Message</h2>
                        <p class="text-muted mb-4">Complete the form below and our team will respond within one business day.</p>

                        <?php if ($error_message): ?>
                            <div class="alert-glass alert-danger mb-4">
                                <i class="fas fa-circle-exclamation icon"></i>
                                <div><?php echo htmlspecialchars($error_message); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if ($success_message): ?>
                            <div class="alert-glass alert-success mb-4">
                                <i class="fas fa-check-circle icon"></i>
                                <div><?php echo htmlspecialchars($success_message); ?></div>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="form-group">
                                <label for="name">Your Name</label>
                                <input type="text"
                                       id="name"
                                       name="name"
                                       class="form-control"
                                       placeholder="Enter your full name"
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ($auth->isLoggedIn() ? $auth->getCurrentUser()['username'] : '')); ?>"
                                       required>
                            </div>

                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email"
                                       id="email"
                                       name="email"
                                       class="form-control"
                                       placeholder="Enter your email address"
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ($auth->isLoggedIn() ? $auth->getCurrentUser()['email'] : '')); ?>"
                                       required>
                            </div>

                            <div class="form-group">
                                <label for="subject">Subject</label>
                                <input type="text"
                                       id="subject"
                                       name="subject"
                                       class="form-control"
                                       placeholder="Brief description of your inquiry"
                                       value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>"
                                       required>
                            </div>

                            <div class="form-group">
                                <label for="priority">Priority</label>
                                <select id="priority" name="priority" class="form-select">
                                    <option value="low" <?php echo ($_POST['priority'] ?? '') === 'low' ? 'selected' : ''; ?>>Low - General inquiry</option>
                                    <option value="medium" <?php echo ($_POST['priority'] ?? 'medium') === 'medium' ? 'selected' : ''; ?>>Medium - Account issue</option>
                                    <option value="high" <?php echo ($_POST['priority'] ?? '') === 'high' ? 'selected' : ''; ?>>High - Urgent problem</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="message">Message</label>
                                <textarea id="message"
                                          name="message"
                                          class="form-control"
                                          rows="6"
                                          placeholder="Please describe your question or issue in detail..."
                                          required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-group">
                                <div class="h-captcha" data-sitekey="<?php echo HCAPTCHA_SITE_KEY; ?>"></div>
                            </div>

                            <button type="submit" class="btn btn-theme btn-gradient mt-4"><i class="fas fa-paper-plane me-2"></i>Send Message</button>
                        </form>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="d-flex flex-column gap-4" data-aos="fade-left">
                        <div class="glass-card p-4">
                            <h3 class="h5 text-white mb-3">Support Overview</h3>
                            <p class="text-muted mb-4">We typically respond within 24 hours, prioritising urgent wallet or safety concerns first.</p>
                            <div class="contact-meta-item">
                                <div class="icon mail"><i class="fas fa-envelope"></i></div>
                                <div>
                                    <strong>Email Support</strong>
                                    <p class="mb-0"><?php echo SITE_EMAIL; ?></p>
                                </div>
                            </div>
                            <div class="contact-meta-item">
                                <div class="icon clock"><i class="fas fa-clock"></i></div>
                                <div>
                                    <strong>Response Time</strong>
                                    <p class="mb-0">Within 24 hours (business days)</p>
                                </div>
                            </div>
                            <div class="contact-meta-item">
                                <div class="icon support"><i class="fas fa-headset"></i></div>
                                <div>
                                    <strong>Support Hours</strong>
                                    <p class="mb-0">Monday - Friday, 9 AM - 6 PM UTC</p>
                                </div>
                            </div>
                        </div>

                        <div class="glass-card p-4">
                            <h3 class="h5 text-white mb-3">Quick Answers</h3>
                            <ul class="contact-bullet-list mb-4">
                                <li><i class="fas fa-question-circle"></i><a href="faq" class="text-decoration-none text-reset">Visit the FAQ for common issues</a></li>
                                <li><i class="fas fa-shield-halved"></i><a href="trust-safety" class="text-decoration-none text-reset">Review our Trust &amp; Safety policies</a></li>
                                <li><i class="fas fa-bullhorn"></i><a href="promote-sites" class="text-decoration-none text-reset">Explore promotional options</a></li>
                            </ul>
                            <div class="contact-support-links">
                                <?php if (SOCIAL_TELEGRAM): ?>
                                    <a href="<?php echo SOCIAL_TELEGRAM; ?>" target="_blank" class="btn btn-theme btn-outline-glass"><i class="fab fa-telegram"></i> Telegram</a>
                                <?php endif; ?>
                                <?php if (SOCIAL_DISCORD): ?>
                                    <a href="<?php echo SOCIAL_DISCORD; ?>" target="_blank" class="btn btn-theme btn-outline-glass"><i class="fab fa-discord"></i> Discord</a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="contact-cta">
                            <h4 class="h5 text-white mb-3">Advertiser or Partner?</h4>
                            <p>Reach out to discuss sponsored placements, featured reviews, and custom promotional packages.</p>
                            <a href="buy-ads" class="btn btn-theme btn-gradient"><i class="fas fa-bullseye me-2"></i>View Ad Packages</a>
                        </div>

                        <div class="dev-slot1">Sidebar Support Ad 300x600</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
