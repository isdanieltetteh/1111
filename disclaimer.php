<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

$auth = new Auth();

$page_title = 'Disclaimer - ' . SITE_NAME;
$page_description = 'Important disclaimer and risk warnings for using ' . SITE_NAME . ' crypto earning directory.';
$page_keywords = 'disclaimer, risk warning, liability, crypto risks, investment warning';

$additional_head = '';

include 'includes/header.php';
?>

<main class="page-wrapper flex-grow-1">
    <section class="page-hero text-white text-center">
        <div class="container">
            <div class="hero-content mx-auto" data-aos="fade-up">
                <div class="hero-badge mb-4">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Risk Awareness</span>
                </div>
                <h1 class="hero-title mb-3">Important Disclaimer</h1>
                <p class="hero-lead">Understand the risks associated with cryptocurrency earning platforms before participating.</p>
            </div>
        </div>
    </section>

    <div class="container my-5">
        <div class="dev-slot">Risk Banner Ad 970x250</div>
    </div>

    <section class="py-5">
        <div class="container legal-wrapper" data-aos="fade-up">
            <div class="disclaimer-alert">
                <h2 class="h4 mb-2">Risk Warning</h2>
                <p class="mb-0">Cryptocurrency activities involve significant financial risk. Never invest more than you can afford to lose.</p>
            </div>

            <div class="legal-section mt-4">
                <h3><i class="fas fa-info-circle"></i> Platform Disclaimer</h3>
                <p><?php echo SITE_NAME; ?> is a directory and review platform. We do not operate, own, or control any of the crypto faucets, URL shorteners, or other sites listed in our directory.</p>
                <ul>
                    <li>We are not responsible for the actions of listed sites</li>
                    <li>Site statuses can change without notice</li>
                    <li>We do not guarantee payments from any listed site</li>
                    <li>Users should conduct their own research before using any site</li>
                </ul>
            </div>

            <div class="legal-section mt-4">
                <h3><i class="fas fa-exclamation-triangle"></i> Risk Factors</h3>
                <div class="risk-grid mt-3">
                    <div class="risk-card">
                        <h5><i class="fas fa-chart-line me-2"></i>Market Volatility</h5>
                        <p>Cryptocurrency values can fluctuate dramatically. Earnings may lose value quickly.</p>
                    </div>
                    <div class="risk-card">
                        <h5><i class="fas fa-user-times me-2"></i>Site Reliability</h5>
                        <p>Crypto sites may stop paying, change terms, or shut down without warning.</p>
                    </div>
                    <div class="risk-card">
                        <h5><i class="fas fa-shield-halved me-2"></i>Security Risks</h5>
                        <p>Malicious sites may attempt to steal personal information or cryptocurrency.</p>
                    </div>
                    <div class="risk-card">
                        <h5><i class="fas fa-gavel me-2"></i>Regulatory Risk</h5>
                        <p>Cryptocurrency regulations vary by jurisdiction and may change.</p>
                    </div>
                </div>
            </div>

            <div class="legal-section mt-4">
                <h3><i class="fas fa-ban"></i> Not Financial Advice</h3>
                <p>Nothing on this platform constitutes financial, investment, or legal advice. All information is provided for educational and informational purposes only.</p>
                <ul>
                    <li>Consult qualified professionals for financial advice</li>
                    <li>Understand local laws regarding cryptocurrency</li>
                    <li>Research thoroughly before making any investments</li>
                    <li>Consider your risk tolerance and financial situation</li>
                </ul>
            </div>

            <div class="legal-section mt-4">
                <h3><i class="fas fa-arrow-up-right-from-square"></i> Third-Party Sites</h3>
                <p>Our platform contains links to external websites. We are not responsible for:</p>
                <ul>
                    <li>Content or practices of external sites</li>
                    <li>Privacy policies of third-party sites</li>
                    <li>Security of external platforms</li>
                    <li>Accuracy of information on linked sites</li>
                </ul>
            </div>

            <div class="legal-section mt-4">
                <h3><i class="fas fa-shield-halved"></i> Limitation of Liability</h3>
                <p>To the maximum extent permitted by law, <?php echo SITE_NAME; ?> shall not be liable for:</p>
                <ul>
                    <li>Any direct, indirect, or consequential damages</li>
                    <li>Loss of profits, data, or business opportunities</li>
                    <li>Actions or omissions of third-party sites</li>
                    <li>Technical failures or service interruptions</li>
                    <li>Cryptocurrency market fluctuations</li>
                </ul>
            </div>

            <div class="legal-section mt-4">
                <h3><i class="fas fa-user-check"></i> Your Responsibility</h3>
                <p>As a user, you are responsible for:</p>
                <ul>
                    <li>Conducting your own research and due diligence</li>
                    <li>Understanding the risks involved in cryptocurrency activities</li>
                    <li>Complying with local laws and regulations</li>
                    <li>Protecting your account credentials and wallet information</li>
                    <li>Reporting suspicious or fraudulent activities</li>
                </ul>
            </div>

            <div class="legal-footer-card mt-5" data-aos="fade-up">
                <h3 class="h5 text-white mb-3">Questions About This Disclaimer?</h3>
                <p>If you need clarification about this disclaimer or want to report a concern, please get in touch.</p>
                <div class="legal-actions">
                    <a href="contact" class="btn btn-theme btn-gradient"><i class="fas fa-envelope me-2"></i>Contact Support</a>
                    <a href="terms" class="btn btn-theme btn-outline-glass"><i class="fas fa-file-contract me-2"></i>Terms of Service</a>
                </div>
            </div>

            <div class="dev-slot2 mt-5">Disclaimer Inline Ad 728x90</div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
