<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();

// Get FAQ categories and questions
$faqs = [
    'Getting Started' => [
        [
            'question' => 'How do I create an account?',
            'answer' => 'Click the "Register" button in the top navigation, fill out the registration form with your username, email, and password. Your account will be created instantly and you can start using the platform immediately.'
        ],
        [
            'question' => 'Is it free to use ' . SITE_NAME . '?',
            'answer' => 'Yes! Creating an account, browsing sites, writing reviews, and most features are completely free. We only charge for premium promotional features like sponsored listings.'
        ],
        [
            'question' => 'How do I find legitimate crypto faucets?',
            'answer' => 'Use our "Browse Sites" page with filters for "Paying" status. Look for sites with high ratings, positive reviews, and our verification badges. Check the Trust & Safety page for more tips.'
        ]
    ],
    'Site Submissions' => [
        [
            'question' => 'How do I submit a new site?',
            'answer' => 'Go to "Submit Site" page, fill out the form with site details, and include a backlink to our site. Your submission will be reviewed by our team and the community before approval.'
        ],
        [
            'question' => 'Why was my site submission rejected?',
            'answer' => 'Common reasons include: missing backlink, site not accessible, duplicate submission, or site doesn\'t meet our quality standards. Check your email for specific feedback from our team.'
        ],
        [
            'question' => 'Can I submit the same URL multiple times?',
            'answer' => 'Generally no, but you can submit the same URL with a different title if you purchase the "Use Referral Link" feature for $15. This allows you to promote your referral version of the site.'
        ]
    ],
    'Reviews & Ratings' => [
        [
            'question' => 'How do I write a review?',
            'answer' => 'Visit any site\'s detail page and click "Write Review". Rate the site 1-5 stars, write your experience, and optionally include payment proof. Reviews help other users make informed decisions.'
        ],
        [
            'question' => 'What makes a good review?',
            'answer' => 'Good reviews are honest, detailed, and based on personal experience. Include information about payment speed, minimum payouts, user interface, and any issues you encountered.'
        ],
        [
            'question' => 'How do I report a scam site?',
            'answer' => 'When writing a review, check the "Report as Scam" checkbox and provide details. If 80% of reviews (minimum 10) report a site as scam, it\'s automatically flagged for investigation.'
        ]
    ],
    'Wallet & Earnings' => [
        [
            'question' => 'How does the points system work?',
            'answer' => 'Earn points by writing reviews (5 pts), submitting approved sites (25 pts), and receiving upvotes (2 pts). Convert points to cryptocurrency through our withdrawal system.'
        ],
        [
            'question' => 'What are the withdrawal minimums?',
            'answer' => 'FaucetPay withdrawals: 1000 points minimum. Direct wallet withdrawals: 2000 points minimum. Different minimums may apply for different cryptocurrencies.'
        ],
        [
            'question' => 'How long do withdrawals take?',
            'answer' => 'Withdrawals are typically processed within 24-48 hours. FaucetPay withdrawals are usually faster than direct wallet transfers.'
        ]
    ],
    'Trust & Safety' => [
        [
            'question' => 'How do you detect scam sites?',
            'answer' => 'We use automated detection based on community reports. When ≥80% of reviews (min. 10) report a site as scam, it\'s automatically flagged. Our admin team then investigates and makes final decisions.'
        ],
        [
            'question' => 'What does "Verified Paying" mean?',
            'answer' => 'Sites marked as "Paying" have positive community feedback, recent payment proofs, and no scam reports. We maintain a high legitimacy rate through continuous monitoring.'
        ],
        [
            'question' => 'How can I protect myself from scams?',
            'answer' => 'Only use sites with "Paying" status, read recent reviews, check for payment proofs, and never invest more than you can afford to lose. Report suspicious sites immediately.'
        ]
    ],
    'Promotions & Features' => [
        [
            'question' => 'What are sponsored sites?',
            'answer' => 'Sponsored sites pay for premium placement in our top positions. They rotate fairly and are clearly marked with sponsored badges. Sponsorship doesn\'t affect our verification standards.'
        ],
        [
            'question' => 'How do I promote my site?',
            'answer' => 'Visit the "Promote Sites" page to purchase sponsored or boosted listings. You can also buy premium features like referral link usage and backlink skipping.'
        ],
        [
            'question' => 'What payment methods do you accept?',
            'answer' => 'We accept cryptocurrency payments through FaucetPay and BitPay. This includes Bitcoin, Ethereum, Litecoin, USDT, and many other popular cryptocurrencies.'
        ]
    ]
];

$category_icons = [
    'Getting Started' => ['icon' => 'fa-play-circle', 'badge' => 'success'],
    'Site Submissions' => ['icon' => 'fa-plus-circle', 'badge' => 'primary'],
    'Reviews & Ratings' => ['icon' => 'fa-star', 'badge' => 'warning'],
    'Wallet & Earnings' => ['icon' => 'fa-wallet', 'badge' => 'purple'],
    'Trust & Safety' => ['icon' => 'fa-shield-halved', 'badge' => 'danger'],
    'Promotions & Features' => ['icon' => 'fa-rocket', 'badge' => 'teal'],
];

$page_title = 'Frequently Asked Questions - ' . SITE_NAME;
$page_description = 'Find answers to common questions about ' . SITE_NAME . ', including how to use our platform, submit sites, write reviews, and earn cryptocurrency.';
$page_keywords = 'FAQ, help, questions, crypto faucets, support, how to use';

$additional_head = '';

include 'includes/header.php';
?>

<main class="page-wrapper flex-grow-1">
    <section class="page-hero text-white text-center">
        <div class="container">
            <div class="hero-content mx-auto" data-aos="fade-up">
                <div class="hero-badge mb-4">
                    <i class="fas fa-circle-question"></i>
                    <span>Knowledge Base</span>
                </div>
                <h1 class="hero-title mb-4">Frequently Asked Questions</h1>
                <p class="hero-lead">Find quick answers on account setup, submissions, safety, and monetization tools.</p>
            </div>
        </div>
    </section>

    <div class="container my-5">
        <div class="dev-slot">Billboard Ad Slot 970x250</div>
    </div>

    <section class="py-5">
        <div class="container">
            <div class="glass-card p-4 p-lg-5 mb-5" data-aos="fade-up">
                <div class="d-flex flex-column flex-md-row align-items-md-center gap-3">
                    <i class="fas fa-search text-info fs-4"></i>
                    <input type="text" id="faqSearch" class="form-control" placeholder="Search FAQs, guides, or troubleshooting tips...">
                </div>
                <p class="text-muted mb-0 mt-3">Tip: search for keywords like “withdrawal”, “sponsored”, or “backlink” to jump to specific answers.</p>
            </div>

            <?php foreach ($faqs as $category => $questions): ?>
                <div class="glass-card p-4 p-lg-5 mb-4 faq-category" data-aos="fade-up">
                    <div class="faq-category-title">
                        <?php $badge = $category_icons[$category]['badge'] ?? 'primary'; ?>
                        <span class="badge <?php echo htmlspecialchars($badge); ?>"><i class="fas <?php echo htmlspecialchars($category_icons[$category]['icon'] ?? 'fa-circle'); ?>"></i></span>
                        <h2 class="h5 mb-0 text-white"><?php echo htmlspecialchars($category); ?></h2>
                    </div>

                    <?php foreach ($questions as $faq): ?>
                        <div class="faq-item">
                            <button class="faq-question" type="button" onclick="toggleFaq(this)">
                                <span><?php echo htmlspecialchars($faq['question']); ?></span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="faq-answer">
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($faq['answer'])); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <div class="faq-empty" style="display: none;">
                <i class="fas fa-search-minus"></i>
                <p>No questions matched your search. Try different keywords or explore the Help Center.</p>
            </div>

            <div class="dev-slot2 mt-5">Inline Ad Slot 728x90</div>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="glass-card p-4 p-lg-5 text-center" data-aos="fade-up">
                <h3 class="h4 text-white mb-3">Need More Assistance?</h3>
                <p class="text-muted mb-4">Contact our support team or browse in-depth guides tailored for crypto earners.</p>
                <div class="support-quick-links">
                    <a href="help" class="btn btn-theme btn-outline-glass"><i class="fas fa-graduation-cap me-2"></i>Help Center</a>
                    <a href="contact" class="btn btn-theme btn-gradient"><i class="fas fa-headset me-2"></i>Contact Support</a>
                    <a href="trust-safety" class="btn btn-theme btn-outline-glass"><i class="fas fa-shield-halved me-2"></i>Trust &amp; Safety</a>
                </div>
            </div>
            <div class="dev-slot1 mt-4">Sidebar Tower Ad 300x600</div>
        </div>
    </section>
</main>

<script>
function toggleFaq(button) {
    const item = button.closest('.faq-item');
    item.classList.toggle('active');
}

const faqSearch = document.getElementById('faqSearch');
if (faqSearch) {
    faqSearch.addEventListener('input', function () {
        const searchTerm = this.value.toLowerCase();
        const categories = document.querySelectorAll('.faq-category');
        let anyVisible = false;

        categories.forEach(category => {
            let hasVisibleItems = false;
            category.querySelectorAll('.faq-item').forEach(item => {
                const question = item.querySelector('.faq-question span').textContent.toLowerCase();
                const answer = item.querySelector('.faq-answer').textContent.toLowerCase();
                if (!searchTerm || question.includes(searchTerm) || answer.includes(searchTerm)) {
                    item.style.display = '';
                    hasVisibleItems = true;
                    anyVisible = true;
                } else {
                    item.style.display = 'none';
                    item.classList.remove('active');
                }
            });
            category.style.display = hasVisibleItems ? '' : 'none';
        });

        const emptyState = document.querySelector('.faq-empty');
        if (emptyState) {
            emptyState.style.display = anyVisible ? 'none' : '';
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>
