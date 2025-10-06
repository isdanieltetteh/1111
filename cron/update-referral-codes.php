<?php
/**
 * One-time script to update existing referral codes to use usernames
 * Run this once to migrate from ID-based to username-based referral codes
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Prevent web access
if (isset($_SERVER['HTTP_HOST'])) {
    die('This script can only be run from command line');
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "Starting referral code migration...\n";
    
    // Update all users to use username as referral code
    $update_query = "UPDATE users SET referral_code = username WHERE referral_code != username OR referral_code IS NULL";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->execute();
    $updated_count = $update_stmt->rowCount();
    
    echo "Updated {$updated_count} user referral codes to use usernames\n";
    
    // Update existing referral relationships to use new codes
    $fix_referrals_query = "UPDATE user_referrals ur 
                           JOIN users u ON ur.referrer_id = u.id 
                           SET ur.referral_code = u.username 
                           WHERE ur.referral_code != u.username";
    $fix_referrals_stmt = $db->prepare($fix_referrals_query);
    $fix_referrals_stmt->execute();
    $fixed_referrals = $fix_referrals_stmt->rowCount();
    
    echo "Fixed {$fixed_referrals} existing referral relationships\n";
    
    echo "Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
    exit(1);
}
?>
