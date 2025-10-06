<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

$auth = new Auth();

$page_title = 'Privacy Policy - ' . SITE_NAME;
$page_description = 'Privacy Policy for ' . SITE_NAME . '. Learn how we collect, use, and protect your personal information.';
$page_keywords = 'privacy policy, data protection, personal information, GDPR, crypto directory';

$additional_head = '';

include 'includes/header.php';
?>

<main class="page-wrapper flex-grow-1">
    <section class="page-hero text-white text-center">
        <div class="container">
            <div class="hero-content mx-auto" data-aos="fade-up">
                <div class="hero-badge mb-4">
                    <i class="fas fa-user-shield"></i>
                    <span>Data Protection</span>
                </div>
                <h1 class="hero-title mb-3">Privacy Policy</h1>
                <p class="hero-lead">Learn how we handle your personal information, protect your data, and empower your privacy rights.</p>
                <div class="privacy-meta mt-4">
                    <span class="pill"><i class="fas fa-calendar-check"></i>Last updated: <?php echo date('F j, Y'); ?></span>
                    <span class="pill"><i class="fas fa-shield-halved"></i>GDPR Ready</span>
                </div>
            </div>
        </div>
    </section>

    <div class="container my-5">
        <div class="dev-slot">Privacy Hero Ad 970x250</div>
    </div>

    <section class="py-5">
        <div class="container legal-wrapper">
            <div class="legal-section" data-aos="fade-up">
                <h3><i class="fas fa-info-circle"></i> Information We Collect</h3>
                <div class="privacy-table mt-3">
                    <table>
                        <thead>
                            <tr>
                                <th>Data Type</th>
                                <th>Purpose</th>
                                <th>Retention</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Email Address</td>
                                <td>Account management, notifications</td>
                                <td>Until account deletion</td>
                            </tr>
                            <tr>
                                <td>Username</td>
                                <td>Platform identification, referrals</td>
                                <td>Until account deletion</td>
                            </tr>
                            <tr>
                                <td>IP Address</td>
                                <td>Security, fraud prevention</td>
                                <td>30 days</td>
                            </tr>
                            <tr>
                                <td>Usage Data</td>
                                <td>Platform improvement, analytics</td>
                                <td>12 months</td>
                            </tr>
                            <tr>
                                <td>Wallet Addresses</td>
                                <td>Withdrawal processing</td>
                                <td>Until withdrawal completion</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="legal-section" data-aos="fade-up">
                <h3><i class="fas fa-shield-halved"></i> How We Use Your Information</h3>
                <ul>
                    <li><strong>Platform Operation:</strong> Manage accounts, process transactions, provide services</li>
                    <li><strong>Security:</strong> Detect fraud, prevent abuse, maintain platform integrity</li>
                    <li><strong>Communication:</strong> Send important updates, notifications, and support responses</li>
                    <li><strong>Improvement:</strong> Analyze usage patterns to enhance user experience</li>
                    <li><strong>Legal Compliance:</strong> Meet regulatory requirements and legal obligations</li>
                </ul>
            </div>

            <div class="legal-section" data-aos="fade-up">
                <h3><i class="fas fa-share-nodes"></i> Information Sharing</h3>
                <p>We do not sell, trade, or rent your personal information. We may share data only in these circumstances:</p>
                <ul>
                    <li><strong>With Your Consent:</strong> When you explicitly authorize sharing</li>
                    <li><strong>Service Providers:</strong> Trusted partners who help operate our platform</li>
                    <li><strong>Legal Requirements:</strong> When required by law or legal process</li>
                    <li><strong>Safety:</strong> To protect rights, property, or safety of users</li>
                </ul>
            </div>

            <div class="legal-section" data-aos="fade-up">
                <h3><i class="fas fa-lock"></i> Data Security</h3>
                <ul>
                    <li>Encrypted data transmission (SSL/TLS)</li>
                    <li>Secure password hashing</li>
                    <li>Regular security audits</li>
                    <li>Access controls and monitoring</li>
                    <li>Secure server infrastructure</li>
                </ul>
                <div class="legal-callout info mt-3">
                    <strong>Need more detail?</strong> Contact our security team via <a href="contact" class="legal-link">support</a> for additional documentation.
                </div>
            </div>

            <div class="legal-section" data-aos="fade-up">
                <h3><i class="fas fa-user-shield"></i> Your Rights</h3>
                <p>You have the following rights regarding your personal data:</p>
                <div class="privacy-rights-grid mt-3">
                    <div class="privacy-rights-card">
                        <div class="icon"><i class="fas fa-eye"></i></div>
                        <h5 class="text-white mb-2">Right to Access</h5>
                        <p>Request a copy of your personal data we hold.</p>
                    </div>
                    <div class="privacy-rights-card">
                        <div class="icon"><i class="fas fa-edit"></i></div>
                        <h5 class="text-white mb-2">Right to Rectify</h5>
                        <p>Correct inaccurate or incomplete data.</p>
                    </div>
                    <div class="privacy-rights-card">
                        <div class="icon"><i class="fas fa-trash"></i></div>
                        <h5 class="text-white mb-2">Right to Erasure</h5>
                        <p>Request deletion of your personal data.</p>
                    </div>
                    <div class="privacy-rights-card">
                        <div class="icon"><i class="fas fa-download"></i></div>
                        <h5 class="text-white mb-2">Data Portability</h5>
                        <p>Export your data in a portable format.</p>
                    </div>
                </div>
            </div>

            <div class="legal-section" data-aos="fade-up">
                <h3><i class="fas fa-cookie-bite"></i> Cookies and Tracking</h3>
                <p>We use cookies and similar technologies to:</p>
                <ul>
                    <li>Maintain your login session</li>
                    <li>Remember your preferences</li>
                    <li>Analyze platform usage</li>
                    <li>Improve user experience</li>
                </ul>
                <p>You can control cookie settings through your browser preferences.</p>
            </div>

            <div class="legal-section" data-aos="fade-up">
                <h3><i class="fas fa-globe"></i> International Users</h3>
                <p>Our platform is accessible globally. By using our service, you consent to the transfer and processing of your data in accordance with this privacy policy.</p>
            </div>

            <div class="legal-section" data-aos="fade-up">
                <h3><i class="fas fa-envelope"></i> Contact Us</h3>
                <p>If you have questions about this Privacy Policy or want to exercise your rights, please contact us:</p>
                <ul class="privacy-contact-list">
                    <li><strong>Email:</strong> <?php echo SITE_EMAIL; ?></li>
                    <li><strong>Contact Form:</strong> <a href="contact">Submit a ticket</a></li>
                    <li><strong>Response Time:</strong> Within 48 hours</li>
                </ul>
            </div>

            <div class="legal-footer-card mt-5" data-aos="fade-up">
                <h3 class="h5 text-white mb-3">Questions About Your Privacy?</h3>
                <p>We're committed to protecting your privacy and being transparent about our practices.</p>
                <div class="legal-actions">
                    <a href="contact" class="btn btn-theme btn-gradient"><i class="fas fa-envelope me-2"></i>Contact Us</a>
                    <a href="trust-safety" class="btn btn-theme btn-outline-glass"><i class="fas fa-user-shield me-2"></i>Trust &amp; Safety</a>
                </div>
            </div>

            <div class="dev-slot2 mt-5">Privacy Inline Ad 728x90</div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
