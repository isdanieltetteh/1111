<footer class="site-footer mt-auto pt-5 pb-4">
    <div class="container">
        <div class="row gy-4">
            <div class="col-md-6 col-lg-3">
                <h4 class="mb-3 text-white"><?php echo SITE_NAME; ?></h4>
                <p class="text-muted mb-4"><?php echo SITE_TAGLINE; ?></p>
                <?php if (SOCIAL_TWITTER || SOCIAL_TELEGRAM || SOCIAL_DISCORD): ?>
                    <div class="d-flex gap-3">
                        <?php if (SOCIAL_TWITTER): ?>
                            <a class="btn btn-sm btn-outline-glass px-3" href="<?php echo SOCIAL_TWITTER; ?>" target="_blank" rel="noopener">
                                <i class="fab fa-twitter"></i>
                            </a>
                        <?php endif; ?>
                        <?php if (SOCIAL_TELEGRAM): ?>
                            <a class="btn btn-sm btn-outline-glass px-3" href="<?php echo SOCIAL_TELEGRAM; ?>" target="_blank" rel="noopener">
                                <i class="fab fa-telegram"></i>
                            </a>
                        <?php endif; ?>
                        <?php if (SOCIAL_DISCORD): ?>
                            <a class="btn btn-sm btn-outline-glass px-3" href="<?php echo SOCIAL_DISCORD; ?>" target="_blank" rel="noopener">
                                <i class="fab fa-discord"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-6 col-lg-3">
                <h5 class="text-white mb-3">Quick Links</h5>
                <ul class="list-unstyled text-muted d-grid gap-2">
                    <li><a href="sites">Browse Sites</a></li>
                    <li><a href="rankings">Rankings</a></li>
                    <li><a href="submit-site">Submit Site</a></li>
                    <li><a href="about">About Us</a></li>
                </ul>
            </div>
            <div class="col-md-6 col-lg-3">
                <h5 class="text-white mb-3">Categories</h5>
                <ul class="list-unstyled text-muted d-grid gap-2">
                    <li><a href="sites?category=faucet">Crypto Faucets</a></li>
                    <li><a href="sites?category=url_shortener">URL Shorteners</a></li>
                    <li><a href="sites?status=paying">Paying Sites</a></li>
                    <li><a href="sites?sort=newest">New Sites</a></li>
                </ul>
            </div>
            <div class="col-md-6 col-lg-3">
                <h5 class="text-white mb-3">Support</h5>
                <ul class="list-unstyled text-muted d-grid gap-2">
                    <li><a href="help">Help Center</a></li>
                    <li><a href="faq">FAQ</a></li>
                    <li><a href="trust-safety">Trust &amp; Safety</a></li>
                    <li><a href="contact">Contact Us</a></li>
                    <li><a href="terms">Terms of Service</a></li>
                    <li><a href="privacy">Privacy Policy</a></li>
                    <li><a href="disclaimer">Disclaimer</a></li>
                    <li><a href="newsletter">Newsletter</a></li>
                    <li><a href="redeem-coupon">Redeem Coupon</a></li>
                </ul>
            </div>
        </div>

        <div class="dev-slot2 mt-5">
            Footer Premium Ad Slot 970x250
        </div>

        <div class="glass-card mt-5 p-4 p-lg-5 text-center animate-fade-in">
            <div class="mb-3">
                <span class="stat-ribbon">Stay Updated</span>
                <h4 class="mb-3 text-white"><i class="fas fa-envelope-open-text me-2"></i>Never Miss a Legit Opportunity</h4>
                <p class="text-muted mb-4">Get the latest crypto earning opportunities and scam alerts delivered to your inbox.</p>
            </div>
            <form id="footerNewsletterForm" class="row g-3 justify-content-center">
                <div class="col-md-6">
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="fas fa-envelope"></i></span>
                        <input type="email" id="footerNewsletterEmail" class="form-control border-start-0" placeholder="Enter your email address" required>
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-theme btn-gradient btn-lg"><i class="fas fa-bell me-2"></i>Subscribe</button>
                </div>
            </form>
            <small class="d-block mt-3 text-muted"><i class="fas fa-shield-halved me-2"></i>We respect your privacy. Unsubscribe anytime.</small>
        </div>

        <div class="footer-bottom text-center pt-4 mt-5">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
<script>
    AOS.init({
        once: true,
        duration: 700,
        offset: 120
    });

    const revealElements = document.querySelectorAll('.animate-fade-in');
    if (revealElements.length) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                }
            });
        }, { threshold: 0.2 });

        revealElements.forEach(el => observer.observe(el));
    }
</script>
<script src="assets/js/main.js"></script>
<script src="assets/js/notifications.js"></script>

<script>
    document.getElementById('footerNewsletterForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const email = document.getElementById('footerNewsletterEmail').value;
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subscribing...';

        fetch('ajax/newsletter-subscribe.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({email: email})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                submitBtn.innerHTML = '<i class="fas fa-check"></i> Subscribed!';
                submitBtn.classList.remove('btn-gradient');
                submitBtn.classList.add('btn-success');
                document.getElementById('footerNewsletterEmail').value = '';

                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.classList.remove('btn-success');
                    submitBtn.classList.add('btn-gradient');
                    submitBtn.disabled = false;
                }, 3000);
            } else {
                alert(data.message || 'Subscription failed');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Newsletter subscription error:', error);
            alert('Subscription failed. Please try again.');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });
</script>

<?php if (isset($additional_scripts)): ?>
    <?php echo $additional_scripts; ?>
<?php endif; ?>
</body>
</html>
