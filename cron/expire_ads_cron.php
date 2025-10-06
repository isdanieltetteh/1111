<?php
/**
 * Cron job to expire old advertisements
 * Run this script daily via cron:
 * 0 0 * * * php /path/to/scripts/expire_ads_cron.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ad-manager.php';

$database = new Database();
$db = $database->getConnection();
$ad_manager = new AdManager($db);

echo "Starting ad expiration check...\n";

if ($ad_manager->expireOldAds()) {
    echo "Successfully expired old advertisements.\n";
} else {
    echo "Error expiring advertisements.\n";
}

echo "Done.\n";
?>
