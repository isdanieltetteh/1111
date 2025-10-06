<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ad-manager.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$ad_manager = new AdManager($db);

// Get parameters
$ad_type = isset($_GET['type']) ? $_GET['type'] : 'banner';
$format = isset($_GET['format']) ? $_GET['format'] : 'html';

// Validate ad type
if (!in_array($ad_type, ['banner', 'text'])) {
    echo json_encode(['error' => 'Invalid ad type']);
    exit();
}

// Get random ad
$ad = $ad_manager->getRandomAd($ad_type);

if ($ad) {
    if ($format === 'json') {
        // Return JSON data
        echo json_encode([
            'success' => true,
            'ad' => $ad
        ]);
    } else {
        // Return HTML
        if ($ad_type === 'banner') {
            $html = $ad_manager->renderBannerAd($ad, true);
        } else {
            $html = $ad_manager->renderTextAd($ad, true);
        }
        
        echo json_encode([
            'success' => true,
            'html' => $html
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No ads available'
    ]);
}
?>
