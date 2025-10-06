<?php
/**
 * Global utility functions for the crypto directory application
 */

/**
 * Generate secure token for various purposes
 */
function generate_secure_token($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Sanitize user input
 */
function sanitize_input($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Format currency amount
 */
function format_currency($amount, $decimals = 4) {
    return '$' . number_format($amount, $decimals);
}

/**
 * Time ago helper
 */
function time_ago($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    if ($time < 2592000) return floor($time/86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}

/**
 * Render star rating HTML
 */
function render_stars($rating, $size = '1rem') {
    $html = '<div class="star-rating" style="font-size: ' . $size . '">';
    for ($i = 1; $i <= 5; $i++) {
        $class = $i <= $rating ? 'star filled' : 'star';
        $html .= '<span class="' . $class . '">★</span>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Get status badge HTML
 */
function get_status_badge($status) {
    $badges = [
        'paying' => '<span class="status-badge status-paying">✅ Paying</span>',
        'scam_reported' => '<span class="status-badge status-scam-reported">⚠ Scam Reported</span>',
        'scam' => '<span class="status-badge status-scam">❌ Scam</span>'
    ];
    return $badges[$status] ?? '';
}

/**
 * Validate email address
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate URL
 */
function is_valid_url($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Generate referral code from username
 */
function generate_referral_code($username) {
    return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $username));
}

/**
 * Calculate trust score
 */
function calculate_trust_score($rating, $reviews, $upvotes, $downvotes) {
    if ($reviews == 0) return 0;
    
    $rating_score = ($rating / 5) * 40; // 40% weight
    $review_score = min(($reviews / 10) * 30, 30); // 30% weight, max at 10 reviews
    $vote_ratio = ($upvotes + $downvotes) > 0 ? ($upvotes / ($upvotes + $downvotes)) * 30 : 0; // 30% weight
    
    return round($rating_score + $review_score + $vote_ratio);
}
?>
