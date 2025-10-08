<?php
// Site Configuration
define('SITE_NAME', 'Faucet Guard');
define('SITE_TAGLINE', 'Review Crypto Faucets & URL Shorteners');
define('SITE_DESCRIPTION', 'Discover the best crypto faucets and URL shorteners. Read reviews, check ratings, and find the highest paying crypto earning sites.');
define('SITE_KEYWORDS', 'crypto faucets, bitcoin faucets, url shorteners, cryptocurrency earning, passive income');
define('SITE_URL', 'https://' . $_SERVER['HTTP_HOST']);
define('SITE_EMAIL', 'support@' . $_SERVER['HTTP_HOST']);

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'mwqnrvfg_faucetguardpop');
define('DB_USER', 'mwqnrvfg_faucetguardpop');
define('DB_PASS', 'mwqnrvfg@faucetguardpop');

// File Upload Settings
define('MAX_LOGO_SIZE', 2 * 1024 * 1024); // 2MB
define('MAX_AVATAR_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

// Pagination Settings
define('SITES_PER_PAGE', 12);
define('REVIEWS_PER_PAGE', 10);
define('USERS_PER_PAGE', 20);

// Reputation System
define('POINTS_REGISTER', 10);
define('POINTS_SUBMIT_SITE', 10);
define('POINTS_WRITE_REVIEW', 5);
define('POINTS_RECEIVE_UPVOTE', 2);
define('POINTS_SITE_APPROVED', 25);

// FaucetPay Integration (Optional)
define('FAUCETPAY_API_KEY', '1052b4763b5830b1a082942e39f673f4c0e64c9b12e3c155f5ea46e7fad0baa9'); // Add your FaucetPay API key
define('FAUCETPAY_MERCHANT_ID', 'godwin853'); // Add your FaucetPay Merchant ID
define('FAUCETPAY_MERCHANT_USERNAME', 'godwin853');

$settings = [
    'merchant_username' => 'godwin853',
    'min_deposit' => 1.00 // example
];

// Email Settings (Optional)
define('SMTP_HOST', 'mail.faucetguard.live');
define('SMTP_PORT', 465);
define('SMTP_USERNAME', 'support@faucetguard.live');
define('SMTP_PASSWORD', 'Admin@SupportMail1');
define('SMTP_ENCRYPTION', 'ssl');
define('SMTP_TIMEOUT', 30);
define('SMTP_ALLOW_SELF_SIGNED', true);
define('SMTP_FROM_EMAIL', SITE_EMAIL);
define('SMTP_FROM_NAME', SITE_NAME);

// Social Media Links (Optional)
define('SOCIAL_TWITTER', '');
define('SOCIAL_TELEGRAM', '');
define('SOCIAL_DISCORD', '');

// Admin Settings
define('ADMIN_EMAIL', 'admin@faucetguard.live');
define('REQUIRE_EMAIL_VERIFICATION', false);
define('AUTO_APPROVE_SITES', false);
define('ENABLE_CREDITS_SYSTEM', true);

// Security Settings
define('SESSION_TIMEOUT', 86400); // 1 day
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
define('MAX_ACCOUNTS_PER_IP', 1);
define('IP_REGISTRATION_COOLDOWN_HOURS', 24);

// Coupon System Security
define('SITE_SECRET_KEY', 'hjkGTUkFDZiHgfhKJKrtWCVbkLIKUgmL'); // Change this to a random string
define('MAX_COUPON_VALUE', 1000.00);
define('MAX_COUPON_USAGE_LIMIT', 10000);
define('MAX_COUPONS_PER_IP_DAILY', 1);

// Captcha Settings
define('HCAPTCHA_SITE_KEY', 'c44aaadc-46da-477a-b076-9c5a5f5287c8'); // Add your hCaptcha site key
define('HCAPTCHA_SECRET_KEY', 'ES_c4fc2281b21b449f98e65763fff85255'); // Add your hCaptcha secret key
define('RECAPTCHA_SITE_KEY', ''); // Add your reCAPTCHA site key  
define('RECAPTCHA_SECRET_KEY', ''); // Add your reCAPTCHA secret key

// Cache Settings
define('ENABLE_CACHE', false);
define('CACHE_DURATION', 3600); // 1 hour

// Referral System Settings
define('REFERRAL_BONUS_PERCENTAGE', 10); // 10% of referral's earned points
define('REFERRAL_SIGNUP_BONUS', 25); // Points for successful referral
?>
