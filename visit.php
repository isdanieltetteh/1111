<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get site ID
$site_id = intval($_GET['id'] ?? 0);

if (!$site_id) {
    header('Location: sites.php');
    exit();
}

// Generate secure token
$token = bin2hex(random_bytes(32));
$expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));
$user_id = $_SESSION['user_id'] ?? null;
$ip_address = $_SERVER['REMOTE_ADDR'];

// Store secure token
$token_query = "INSERT INTO secure_visit_tokens (site_id, token, user_id, ip_address, expires_at) 
               VALUES (:site_id, :token, :user_id, :ip_address, :expires_at)";
$token_stmt = $db->prepare($token_query);
$token_stmt->bindParam(':site_id', $site_id);
$token_stmt->bindParam(':token', $token);
$token_stmt->bindParam(':user_id', $user_id);
$token_stmt->bindParam(':ip_address', $ip_address);
$token_stmt->bindParam(':expires_at', $expires_at);
$token_stmt->execute();

// Get site details with feature check
$query = "SELECT s.url, s.referral_link, sf.feature_type, s.name
          FROM sites s 
          LEFT JOIN site_features sf ON s.id = sf.site_id AND sf.feature_type = 'referral_link' AND sf.is_active = 1
          WHERE s.id = :id AND s.is_approved = 1 AND s.is_dead = FALSE AND s.admin_approved_dead = FALSE";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $site_id);
$stmt->execute();
$site = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$site) {
    header('Location: sites.php');
    exit();
}

// Determine final URL
$final_url = $site['url'];
if ($site['feature_type'] === 'referral_link' && !empty($site['referral_link'])) {
    $final_url = $site['referral_link'];
}

// Redirect to secure redirect page with token
header("Location: redirect.php?token=" . $token . "&url=" . urlencode($final_url));
exit();
?>
