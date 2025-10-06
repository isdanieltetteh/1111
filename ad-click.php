<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/ad-manager.php';

$database = new Database();
$db = $database->getConnection();
$ad_manager = new AdManager($db);

// Get ad ID
$ad_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($ad_id > 0) {
    // Get ad details
    $ad_query = "SELECT * FROM user_advertisements WHERE id = :ad_id AND status = 'active'";
    $ad_stmt = $db->prepare($ad_query);
    $ad_stmt->bindParam(':ad_id', $ad_id);
    $ad_stmt->execute();
    $ad = $ad_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($ad) {
        // Track click
        $user_id = $_SESSION['user_id'] ?? null;
        $referrer_url = $_SERVER['HTTP_REFERER'] ?? null;
        $ad_manager->trackClick($ad_id, $user_id, $referrer_url);
        
        // Redirect to target URL
        header('Location: ' . $ad['target_url']);
        exit();
    }
}

// If ad not found or invalid, redirect to homepage
header('Location: /');
exit();
?>
