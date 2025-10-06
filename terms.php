<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

$auth = new Auth();

$page_title = 'Terms of Service - ' . SITE_NAME;
$page_description = 'Terms of Service and User Agreement for ' . SITE_NAME . '. Please read these terms carefully before using our platform.';
$page_keywords = 'terms of service, user agreement, legal, conditions, crypto directory';

$additional_head = '';

include 'includes/header.php';
?>

<main class="page-wrapper flex-grow-1">
    <section class="page-hero text-white text-center">
        <div class="container">
            <div class="hero-content mx-auto" data-aos="fade-up">
                <div class="hero-badge mb-4">
                    <i class="fas fa-file-contract"></i>
                    <span>Legal Agreement</span>
                </div>
                <h1 class="hero-title mb-3">Terms of Service</h1>
                <p class="hero-lead">These terms govern your access to <?php echo SITE_NAME; ?> and outline the responsibilities of all community members.</p>
                <div class="legal-meta mt-4">
                    <span class="pill"><i class="fas fa-calendar-days"></i>Last updated: <?php echo date('F j, Y'); ?></span>
                    <span class="pill"><i class="fas fa-globe"></i>Worldwide Coverage</span>
                </div>
            </div>
        </div>
    </section>

    <div class="container my-5">
        <div class="dev-slot">Legal Banner Ad 970x250</div>
    </div>

    <section class="py-5">
        <div class="container legal-wrapper">
            <div class="legal-toc" data-aos="fade-up">
                <h2 class="h5 text-white mb-3"><i class="fas fa-list me-2 text-info"></i>Table of Contents</h2>
                <ul>
                    <li><a href="#acceptance"><i class="fas fa-check"></i> 1. Acceptance of Terms</a></li>
                    <li><a href="#description"><i class="fas fa-info"></i> 2. Service Description</a></li>
                    <li><a href="#accounts"><i class="fas fa-user"></i> 3. User Accounts</a></li>
                    <li><a href="#conduct"><i class="fas fa-gavel"></i> 4. User Conduct</a></li>
                    <li><a href="#content"><i class="fas fa-edit"></i> 5. Content and Reviews</a></li>
                    <li><a href="#payments"><i class="fas fa-credit-card"></i> 6. Payments and Credits</a></li>
                    <li><a href="#privacy"><i class="fas fa-lock"></i> 7. Privacy and Data</a></li>
                    <li><a href="#liability"><i class="fas fa-shield-halved"></i> 8. Liability and Disclaimers</a></li>
                    <li><a href="#termination"><i class="fas fa-times-circle"></i> 9. Termination</a></li>
                    <li><a href="#changes"><i class="fas fa-sync"></i> 10. Changes to Terms</a></li>
                </ul>
            </div>

            <div class="legal-section" id="acceptance" data-aos="fade-up">
                <h3><i class="fas fa-check"></i> 1. Acceptance of Terms</h3>
                <p>By accessing and using <?php echo SITE_NAME; ?> ("the Platform"), you accept and agree to be bound by the terms and provisions of this agreement.</p>
                <div class="legal-callout success mt-3">
                    <strong>Important:</strong> If you do not agree to these terms, please discontinue using the platform immediately.
                </div>
            </div>

            <div class="legal-section" id="description" data-aos="fade-up">
                <h3><i class="fas fa-info"></i> 2. Service Description</h3>
                <p><?php echo SITE_NAME; ?> provides the following services:</p>
                <ul>
                    <li>A directory of crypto faucets and URL shorteners</li>
                    <li>User reviews and ratings system</li>
                    <li>Community-driven verification and scam detection</li>
                    <li>Promotional services for site owners</li>
                    <li>Wallet and points system for user rewards</li>
                </ul>
            </div>

            <div class="legal-section" id="accounts" data-aos="fade-up">
                <h3><i class="fas fa-user"></i> 3. User Accounts</h3>
                <h4>Account Creation</h4>
                <ul>
                    <li>You must provide accurate and complete information</li>
                    <li>You are responsible for maintaining account security</li>
                    <li>One account per person is allowed</li>
                    <li>Usernames must be unique and appropriate</li>
                </ul>
                <h4>Account Responsibilities</h4>
                <ul>
                    <li>Keep your login credentials secure</li>
                    <li>Notify us immediately of any unauthorized access</li>
                    <li>You are responsible for all activities under your account</li>
                </ul>
            </div>

            <div class="legal-section" id="conduct" data-aos="fade-up">
                <h3><i class="fas fa-gavel"></i> 4. User Conduct</h3>
                <div class="legal-callout warning">
                    <strong>Prohibited Activities:</strong> Violation of these guidelines can result in suspension or termination.
                </div>
                <ul>
                    <li>Submitting false or misleading information</li>
                    <li>Creating fake reviews or manipulating ratings</li>
                    <li>Attempting to circumvent our verification systems</li>
                    <li>Harassing other users or staff</li>
                    <li>Promoting illegal activities or scam sites</li>
                    <li>Using automated tools to manipulate the platform</li>
                </ul>
            </div>

            <div class="legal-section" id="content" data-aos="fade-up">
                <h3><i class="fas fa-edit"></i> 5. Content and Reviews</h3>
                <h4>User-Generated Content</h4>
                <ul>
                    <li>By submitting content, you grant us the right to display, modify, and distribute your content</li>
                    <li>We may use content to improve the platform</li>
                    <li>We reserve the right to remove content that violates our guidelines</li>
                </ul>
                <h4>Review Guidelines</h4>
                <ul>
                    <li>Reviews must be based on personal experience</li>
                    <li>Provide honest and accurate information</li>
                    <li>Include proof of payment when possible</li>
                    <li>Respect other users and site owners</li>
                </ul>
            </div>

            <div class="legal-section" id="payments" data-aos="fade-up">
                <h3><i class="fas fa-credit-card"></i> 6. Payments and Credits</h3>
                <h4>Credit System</h4>
                <ul>
                    <li>Credits are virtual currency for platform features</li>
                    <li>Credits have no cash value outside the platform</li>
                    <li>Credit purchases are non-refundable</li>
                    <li>We reserve the right to adjust credit values</li>
                </ul>
                <h4>Withdrawals</h4>
                <ul>
                    <li>Minimum withdrawal amounts apply</li>
                    <li>Processing fees may be deducted</li>
                    <li>Withdrawals are processed within 48 hours</li>
                    <li>We reserve the right to verify large withdrawals</li>
                </ul>
            </div>

            <div class="legal-section" id="privacy" data-aos="fade-up">
                <h3><i class="fas fa-lock"></i> 7. Privacy and Data</h3>
                <p>Your privacy is important to us. Please review our Privacy Policy to understand how we collect, use, and protect your information.</p>
                <ul>
                    <li>We collect minimal necessary information</li>
                    <li>Data is used to improve platform security</li>
                    <li>We never sell personal information</li>
                    <li>You can request data deletion at any time</li>
                </ul>
                <div class="legal-callout info mt-3">
                    <strong>See also:</strong> <a href="privacy" class="legal-link">Privacy Policy</a>
                </div>
            </div>

            <div class="legal-section" id="liability" data-aos="fade-up">
                <h3><i class="fas fa-shield-halved"></i> 8. Liability and Disclaimers</h3>
                <div class="legal-callout danger">
                    <strong>Important Disclaimer:</strong> <?php echo SITE_NAME; ?> is a directory service. We do not operate the listed sites and are not responsible for their actions.
                </div>
                <h4>Platform Limitations</h4>
                <ul>
                    <li>We provide information "as is" without warranties</li>
                    <li>Site statuses may change without notice</li>
                    <li>We are not liable for losses from listed sites</li>
                    <li>Users should conduct their own due diligence</li>
                </ul>
            </div>

            <div class="legal-section" id="termination" data-aos="fade-up">
                <h3><i class="fas fa-times-circle"></i> 9. Account Termination</h3>
                <h4>Termination by User</h4>
                <p>You may terminate your account at any time by contacting support.</p>
                <h4>Termination by Platform</h4>
                <p>We may terminate accounts for:</p>
                <ul>
                    <li>Violation of these terms</li>
                    <li>Fraudulent or malicious activity</li>
                    <li>Abuse of platform features</li>
                    <li>Legal requirements</li>
                </ul>
            </div>

            <div class="legal-section" id="changes" data-aos="fade-up">
                <h3><i class="fas fa-sync"></i> 10. Changes to Terms</h3>
                <p>We reserve the right to modify these terms at any time. Users will be notified of significant changes via email or platform notifications.</p>
                <div class="legal-callout warning mt-3">
                    <strong>Continued Use:</strong> Continued use of the platform after changes constitutes acceptance of the new terms.
                </div>
            </div>

            <div class="legal-footer-card mt-5" data-aos="fade-up">
                <h3 class="h5 text-white mb-3">Questions About These Terms?</h3>
                <p>If you have any questions about these Terms of Service, please contact us. We're happy to clarify anything before you continue using the platform.</p>
                <div class="legal-actions">
                    <a href="contact" class="btn btn-theme btn-gradient"><i class="fas fa-envelope me-2"></i>Contact Support</a>
                    <a href="faq" class="btn btn-theme btn-outline-glass"><i class="fas fa-question-circle me-2"></i>Read the FAQ</a>
                </div>
            </div>

            <div class="dev-slot2 mt-5">Footer Legal Ad 728x90</div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
