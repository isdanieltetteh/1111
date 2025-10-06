<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

header('Content-Type: application/json');

$key = $_SERVER['REMOTE_ADDR'] . '_fetch_meta';
$limiter = new RateLimiter($key, 3, 60);

if (!$limiter->allow()) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many meta fetch requests']);
    exit;
}

$url = filter_input(INPUT_GET, 'url', FILTER_VALIDATE_URL);
if (!$url) {
    echo json_encode(['success' => false, 'error' => 'Invalid URL']);
    exit;
}

// Fetch HTML
$html = @file_get_contents($url);
if (!$html) {
    echo json_encode([
        'success' => false,
        'error' => 'Could not fetch site (may block bots). Please upload logo manually.'
    ]);
    exit;
}


// Parse meta
preg_match("/<title>(.*?)<\/title>/si", $html, $title);
preg_match('/<meta name="description" content="([^"]*)"/i', $html, $desc);
preg_match('/<meta property="og:image" content="([^"]*)"/i', $html, $ogImage);
preg_match('/<meta name="twitter:image" content="([^"]*)"/i', $html, $twitterImage);
preg_match('/<link rel="(?:shortcut )?icon"[^>]*href="([^"]*)"/i', $html, $favicon);

// Pick logo (priority order: og:image → twitter:image → favicon)
$logoUrl = $ogImage[1] ?? $twitterImage[1] ?? $favicon[1] ?? '';

// Convert relative logo path to absolute URL
if ($logoUrl && !preg_match('#^https?://#i', $logoUrl)) {
    $parsedUrl = parse_url($url);
    $base = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
    if (strpos($logoUrl, '/') === 0) {
        $logoUrl = $base . $logoUrl;
    } else {
        $logoUrl = rtrim($base, '/') . '/' . ltrim($logoUrl, '/');
    }
}

// Download logo locally
$localLogoPath = '';
if ($logoUrl) {
    $imgData = @file_get_contents($logoUrl);
    if ($imgData !== false) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($imgData);

        $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/x-icon' => 'ico'];
        if (isset($allowedTypes[$mimeType])) {
            $uploadDir = __DIR__ . '/../assets/images/logos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $logoFilename = 'auto_' . time() . '_' . uniqid() . '.' . $allowedTypes[$mimeType];
            $fullPath = $uploadDir . $logoFilename;

            if (file_put_contents($fullPath, $imgData)) {
                // Store relative path for DB
                $localLogoPath = 'assets/images/logos/' . $logoFilename;
            }
        }
    }
}

echo json_encode([
    'success' => true,
    'title' => $title[1] ?? '',
    'description' => $desc[1] ?? '',
    'logo' => $localLogoPath // return local logo path
]);
