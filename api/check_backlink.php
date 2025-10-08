<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

header('Content-Type: application/json');

$key = $_SERVER['REMOTE_ADDR'] . '_check_backlink';
$limiter = new RateLimiter($key, 10, 60);

if (!$limiter->allow()) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many backlink checks']);
    exit;
}

$url = filter_input(INPUT_GET, 'url', FILTER_VALIDATE_URL);
if (!$url) {
    echo json_encode(['success' => false, 'error' => 'Invalid backlink URL']);
    exit;
}

// Prevent SSRF
$host = parse_url($url, PHP_URL_HOST);
if (!$host || preg_match('/^(localhost|127\.|10\.|192\.168\.)/', $host)) {
    echo json_encode(['success' => false, 'error' => 'Unsafe host']);
    exit;
}

$site_domain = parse_url(SITE_URL, PHP_URL_HOST);
$normalizeHost = static function (?string $value) {
    if ($value === null) {
        return '';
    }

    $lower = strtolower($value);
    return preg_replace('/^www\./', '', $lower);
};

if ($normalizeHost($host) === $normalizeHost($site_domain)) {
    echo json_encode(['success' => false, 'error' => 'Backlink must be hosted on your domain, not ours.']);
    exit;
}

try {
    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'user_agent' => 'Mozilla/5.0 (compatible; BacklinkChecker/1.0)',
            'follow_location' => true,
            'max_redirects' => 3
        ]
    ]);
    
    $html = @file_get_contents($url, false, $context);
    if (!$html) {
        echo json_encode(['success' => false, 'error' => 'Could not fetch backlink page']);
        exit;
    }
    
    // Check for various forms of our site URL
    $site_domain = parse_url(SITE_URL, PHP_URL_HOST);
    $site_url_variations = [
        SITE_URL,
        rtrim(SITE_URL, '/'),
        'http://' . $site_domain,
        'https://' . $site_domain,
        'www.' . $site_domain,
        $site_domain
    ];
    
    $found = false;
    $found_variation = '';
    foreach ($site_url_variations as $variation) {
        if (stripos($html, $variation) !== false) {
            $found = true;
            $found_variation = $variation;
            break;
        }
    }
    
    if (!$found) {
        echo json_encode([
            'success' => false, 
            'error' => 'Backlink not found on page. Please ensure you have added a link to ' . SITE_URL . ' on a different domain.'
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Backlink verified successfully!',
        'found_url' => $found_variation
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error checking backlink']);
}
?>
