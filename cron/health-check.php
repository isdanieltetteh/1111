<?php
/**
 * Enhanced Health Check Cron Job
 * Run this script every hour via cron to check site health
 * 
 * Cron command: 0 * * * * /usr/bin/php /path/to/your/site/cron/health-check.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/site-health-checker.php';

// Prevent direct web access
if (isset($_SERVER['HTTP_HOST'])) {
    die('This script can only be run from command line');
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $health_checker = new SiteHealthChecker($db);
    
    echo "Starting enhanced health check at " . date('Y-m-d H:i:s') . "\n";
    
    // Check up to 100 sites per run (increased from 50)
    $results = $health_checker->checkAllSites(100);
    
    $total_checked = count($results);
    $dead_sites = array_filter($results, function($r) { return !$r['result']['accessible']; });
    $restored_sites = array_filter($results, function($r) { 
        return $r['result']['accessible'] && $r['previous_failures'] > 0; 
    });
    
    $dead_count = count($dead_sites);
    $restored_count = count($restored_sites);
    
    echo "Checked {$total_checked} sites\n";
    echo "Found {$dead_count} dead sites\n";
    echo "Restored {$restored_count} previously failed sites\n";
    
    if ($dead_count > 0) {
        echo "\nDead sites detected:\n";
        foreach ($dead_sites as $dead_site) {
            echo "- {$dead_site['site_name']}: {$dead_site['result']['error_message']}\n";
        }
        
        // Send email notification to admins if many sites are dead
        if ($dead_count > 5) {
            sendDeadLinkAlert($dead_count, $dead_sites);
        }
    }
    
    if ($restored_count > 0) {
        echo "\nRestored sites:\n";
        foreach ($restored_sites as $restored_site) {
            echo "- {$restored_site['site_name']}: Back online\n";
        }
    }
    
    // Clean up old health check records (keep last 90 days)
    $cleanup_query = "DELETE FROM site_health_checks WHERE last_checked < DATE_SUB(NOW(), INTERVAL 90 DAY)";
    $cleanup_stmt = $db->prepare($cleanup_query);
    $cleanup_stmt->execute();
    $cleaned = $cleanup_stmt->rowCount();
    
    if ($cleaned > 0) {
        echo "Cleaned up {$cleaned} old health check records\n";
    }
    
    // Update global health statistics
    updateGlobalHealthStats($db, $total_checked, $dead_count, $restored_count);
    
    echo "Health check completed at " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "Error during health check: " . $e->getMessage() . "\n";
    exit(1);
}

function sendDeadLinkAlert($dead_count, $dead_sites) {
    // Simple email alert (implement proper email sending in production)
    $subject = "Dead Link Alert: {$dead_count} sites detected as dead";
    $message = "The following sites have been detected as dead:\n\n";
    
    foreach (array_slice($dead_sites, 0, 10) as $site) {
        $message .= "- {$site['site_name']}: {$site['result']['error_message']}\n";
    }
    
    if (count($dead_sites) > 10) {
        $message .= "\n... and " . (count($dead_sites) - 10) . " more sites.\n";
    }
    
    $message .= "\nPlease review the dead links management panel for full details.";
    
    // Log the alert
    error_log("Dead link alert: {$dead_count} sites detected as dead");
}

function updateGlobalHealthStats($db, $checked, $dead, $restored) {
    try {
        $stats_query = "INSERT INTO system_health_stats (sites_checked, dead_detected, sites_restored, check_date) 
                       VALUES (:checked, :dead, :restored, CURDATE())
                       ON DUPLICATE KEY UPDATE
                       sites_checked = sites_checked + :checked,
                       dead_detected = dead_detected + :dead,
                       sites_restored = sites_restored + :restored";
        $stats_stmt = $db->prepare($stats_query);
        $stats_stmt->bindParam(':checked', $checked);
        $stats_stmt->bindParam(':dead', $dead);
        $stats_stmt->bindParam(':restored', $restored);
        $stats_stmt->execute();
    } catch (Exception $e) {
        error_log("Failed to update global health stats: " . $e->getMessage());
    }
}
?>
