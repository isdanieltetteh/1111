<?php
class WalletManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    // Get user wallet
    public function getUserWallet($user_id) {
        $query = "SELECT * FROM user_wallets WHERE user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Add points to user wallet
    public function addPoints($user_id, $points, $type, $description, $reference_id = null, $reference_type = null) {
        $in_existing_transaction = $this->db->inTransaction();

        try {
            if (!$in_existing_transaction) {
                $this->db->beginTransaction();
            }

            // Update wallet balance
            $update_wallet = "UPDATE user_wallets SET
                             points_balance = points_balance + :points,
                             total_earned_points = total_earned_points + :points
                             WHERE user_id = :user_id";
            $stmt = $this->db->prepare($update_wallet);
            $stmt->bindParam(':points', $points);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();

            if ($type === 'earned') {
                $sync_reputation = "UPDATE users SET reputation_points = reputation_points + :points WHERE id = :user_id";
                $rep_stmt = $this->db->prepare($sync_reputation);
                $rep_stmt->bindParam(':points', $points);
                $rep_stmt->bindParam(':user_id', $user_id);
                $rep_stmt->execute();
            }

            // Log transaction
            $log_transaction = "INSERT INTO points_transactions
                               (user_id, points, type, description, reference_id, reference_type)
                               VALUES (:user_id, :points, :type, :description, :reference_id, :reference_type)";
            $stmt = $this->db->prepare($log_transaction);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':points', $points);
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':reference_id', $reference_id);
            $stmt->bindParam(':reference_type', $reference_type);
            $stmt->execute();

            // Handle referral bonus
            if ($type === 'earned') {
                $this->processReferralBonus($user_id, $points);
            }

            if (!$in_existing_transaction) {
                $this->db->commit();
            }
            return true;
        } catch (Exception $e) {
            if (!$in_existing_transaction && $this->db->inTransaction()) {
                $this->db->rollback();
            }
            return false;
        }
    }
    
    // Process referral bonus
    private function processReferralBonus($user_id, $points) {
        // Get referrer
        $query = "SELECT referred_by FROM users WHERE id = :user_id AND referred_by IS NOT NULL";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $referrer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($referrer) {
            // Get referral percentage
            $settings = $this->getWalletSettings();
            $referral_percentage = $settings['referral_percentage'] ?? REFERRAL_BONUS_PERCENTAGE;
            $bonus_points = floor($points * ($referral_percentage / 100));
            
            if ($bonus_points > 0) {
                // Add bonus to referrer
                $update_referrer = "UPDATE user_wallets SET 
                                   points_balance = points_balance + :bonus_points,
                                   total_earned_points = total_earned_points + :bonus_points
                                   WHERE user_id = :referrer_id";
                $stmt = $this->db->prepare($update_referrer);
                $stmt->bindParam(':bonus_points', $bonus_points);
                $stmt->bindParam(':referrer_id', $referrer['referred_by']);
                $stmt->execute();
                
                $update_referrer_rep = "UPDATE users SET reputation_points = reputation_points + :bonus_points WHERE id = :referrer_id";
                $rep_stmt = $this->db->prepare($update_referrer_rep);
                $rep_stmt->bindParam(':bonus_points', $bonus_points);
                $rep_stmt->bindParam(':referrer_id', $referrer['referred_by']);
                $rep_stmt->execute();
                
                // Log referral transaction
                $log_referral = "INSERT INTO points_transactions 
                                (user_id, points, type, description, reference_id) 
                                VALUES (:referrer_id, :bonus_points, 'referral_bonus', :description, :user_id)";
                $stmt = $this->db->prepare($log_referral);
                $stmt->bindParam(':referrer_id', $referrer['referred_by']);
                $stmt->bindParam(':bonus_points', $bonus_points);
                $description = "Referral bonus from user activity";
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
                
                // Update referral stats
                $update_referral_stats = "UPDATE user_referrals SET 
                                         points_earned = points_earned + :bonus_points,
                                         activities = activities + 1
                                         WHERE referrer_id = :referrer_id AND referred_id = :user_id";
                $stmt = $this->db->prepare($update_referral_stats);
                $stmt->bindParam(':bonus_points', $bonus_points);
                $stmt->bindParam(':referrer_id', $referrer['referred_by']);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
            }
        }
    }
    
    // Get wallet settings
    public function getWalletSettings() {
        $query = "SELECT * FROM wallet_settings WHERE id = 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get user referrals
    public function getUserReferrals($user_id) {
        $query = "SELECT ur.*, u.username, u.created_at as joined_date, ur.activities
                  FROM user_referrals ur
                  JOIN users u ON ur.referred_id = u.id
                  WHERE ur.referrer_id = :user_id
                  ORDER BY ur.created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Create referral
    public function createReferral($referrer_id, $referred_id, $referral_code) {
        $query = "INSERT INTO user_referrals (referrer_id, referred_id, referral_code, points_earned, activities) 
                  VALUES (:referrer_id, :referred_id, :referral_code, 0, 0)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':referrer_id', $referrer_id);
        $stmt->bindParam(':referred_id', $referred_id);
        $stmt->bindParam(':referral_code', $referral_code);
        return $stmt->execute();
    }
    
    // Process deposit
    public function processDeposit($user_id, $amount, $currency, $faucetpay_id = null, $bitpay_invoice_id = null) {
        try {
            $this->db->beginTransaction();
            
            // Check if transaction already exists to prevent double processing
            if ($faucetpay_id) {
                $existing_query = "SELECT id FROM credit_transactions WHERE transaction_id = :transaction_id";
                $existing_stmt = $this->db->prepare($existing_query);
                $existing_stmt->bindParam(':transaction_id', $faucetpay_id);
                $existing_stmt->execute();
                
                if ($existing_stmt->rowCount() > 0) {
                    $this->db->rollback();
                    return false; // Already processed
                }
            }
            
            // Update credit balance (unified with deposit balance)
            $update_wallet = "UPDATE users SET 
                             credits = credits + :amount
                             WHERE id = :user_id";
            $stmt = $this->db->prepare($update_wallet);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            // Log deposit transaction
            $payment_method = $faucetpay_id ? 'faucetpay' : 'bitpay';
            $log_deposit = "INSERT INTO credit_transactions 
                           (user_id, amount, type, description, transaction_id, status) 
                           VALUES (:user_id, :amount, 'deposit', :description, :transaction_id, 'completed')";
            $stmt = $this->db->prepare($log_deposit);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':amount', $amount);
            $description = "Deposit via " . ucfirst($payment_method) . " (" . $currency . ")";
            $stmt->bindParam(':description', $description);
            $transaction_id = $faucetpay_id ?: $bitpay_invoice_id;
            $stmt->bindParam(':transaction_id', $transaction_id);
            $stmt->execute();
            
            // Also update user_wallets table if it exists
            $wallet_update = "INSERT INTO user_wallets (user_id, deposit_balance, total_deposited) 
                             VALUES (:user_id, :amount, :amount)
                             ON DUPLICATE KEY UPDATE 
                             deposit_balance = deposit_balance + :amount,
                             total_deposited = total_deposited + :amount";
            $wallet_stmt = $this->db->prepare($wallet_update);
            $wallet_stmt->bindParam(':user_id', $user_id);
            $wallet_stmt->bindParam(':amount', $amount);
            $wallet_stmt->execute();
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            error_log("Deposit processing error: " . $e->getMessage());
            $this->db->rollback();
            return false;
        }
    }
    
    // Create withdrawal request
    public function createWithdrawalRequest($user_id, $points, $method, $wallet_address, $currency, $faucetpay_email = null) {
        $settings = $this->getWalletSettings();
        $amount = $points * $settings['points_to_usd_rate'];
        
        // Check minimum withdrawal based on method
        $min_points = $method === 'faucetpay' ? ($settings['min_faucetpay_points_withdrawal'] ?? $settings['min_points_withdrawal']) : $settings['min_points_withdrawal'];
        if ($points < $min_points) {
            return ['success' => false, 'message' => 'Minimum withdrawal is ' . number_format($min_points) . ' points for ' . ucfirst($method)];
        }
        
        // Validate currency for method
        $currency_query = "SELECT * FROM withdrawal_currencies WHERE currency_code = :currency AND is_active = 1 AND (withdrawal_method = :method OR withdrawal_method = 'both')";
        $currency_stmt = $this->db->prepare($currency_query);
        $currency_stmt->bindParam(':currency', $currency);
        $currency_stmt->bindParam(':method', $method);
        $currency_stmt->execute();
        $currency_data = $currency_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$currency_data) {
            return ['success' => false, 'message' => 'Selected currency is not available for this withdrawal method'];
        }
        
        // Validate required fields based on method
        if ($method === 'faucetpay') {
            if (empty($faucetpay_email)) {
                return ['success' => false, 'message' => 'FaucetPay email is required'];
            }
            if (!filter_var($faucetpay_email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Please enter a valid FaucetPay email address'];
            }
        } else {
            if (empty($wallet_address)) {
                return ['success' => false, 'message' => 'Wallet address is required'];
            }
            if (strlen($wallet_address) < 10) {
                return ['success' => false, 'message' => 'Please enter a valid wallet address'];
            }
        }
        
        // Check user balance
        $wallet = $this->getUserWallet($user_id);
        if (!$wallet || $wallet['points_balance'] < $points) {
            return ['success' => false, 'message' => 'Insufficient points balance'];
        }
        
        $fee_percent = $currency_data['fee_percentage'] > 0 ? 
                      $currency_data['fee_percentage'] : 
                      ($method === 'faucetpay' ? 
                       ($settings['faucetpay_fee_percentage'] ?? $settings['withdrawal_fee_percentage']) : 
                       $settings['withdrawal_fee_percentage']);
        
        $withdrawal_fee = $amount * ($fee_percent / 100);
        $net_amount = $amount - $withdrawal_fee;
        
        if ($net_amount < $currency_data['min_amount']) {
            $required_points = ceil(($currency_data['min_amount'] + $withdrawal_fee) / $settings['points_to_usd_rate']);
            return ['success' => false, 'message' => "Minimum withdrawal for {$currency} is \${$currency_data['min_amount']}. You need at least {$required_points} points."];
        }
        
        try {
            $this->db->beginTransaction();
            
            // Deduct points from wallet
            $update_wallet = "UPDATE user_wallets SET 
                             points_balance = points_balance - :points,
                             total_redeemed_points = total_redeemed_points + :points
                             WHERE user_id = :user_id";
            $stmt = $this->db->prepare($update_wallet);
            $stmt->bindParam(':points', $points);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            // Create withdrawal request
            $create_request = "INSERT INTO withdrawal_requests 
                              (user_id, amount, points_redeemed, withdrawal_method, wallet_address, currency, faucetpay_email, withdrawal_fee, net_amount, status) 
                              VALUES (:user_id, :amount, :points, :method, :wallet_address, :currency, :faucetpay_email, :withdrawal_fee, :net_amount, 'pending')";
            $stmt = $this->db->prepare($create_request);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':points', $points);
            $stmt->bindParam(':method', $method);
            $stmt->bindParam(':wallet_address', $wallet_address);
            $stmt->bindParam(':currency', $currency);
            $stmt->bindParam(':faucetpay_email', $faucetpay_email);
            $stmt->bindParam(':withdrawal_fee', $withdrawal_fee);
            $stmt->bindParam(':net_amount', $net_amount);
            $stmt->execute();
            
            $withdrawal_id = $this->db->lastInsertId();
            
            // Log points transaction
            $log_transaction = "INSERT INTO points_transactions 
                               (user_id, points, type, description, reference_id, reference_type) 
                               VALUES (:user_id, :points, 'redeemed', :description, :withdrawal_id, 'withdrawal')";
            $stmt = $this->db->prepare($log_transaction);
            $negative_points = -$points;
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':points', $negative_points);
            $description = "Withdrawal request: {$points} points → \${$net_amount} {$currency} via " . ucfirst($method);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':withdrawal_id', $withdrawal_id);
            $stmt->execute();
            
            $this->db->commit();
            return ['success' => true, 'message' => "Withdrawal request created successfully! You'll receive \${$net_amount} {$currency} after processing."];
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Withdrawal error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error creating withdrawal request. Please try again.'];
        }
    }
    
    // Get user transactions
    public function getUserTransactions($user_id, $type = 'all', $limit = 20) {
        $where_clause = "WHERE user_id = :user_id";
        if ($type !== 'all') {
            $where_clause .= " AND type = :type";
        }
        
        $query = "SELECT * FROM points_transactions 
                  {$where_clause}
                  ORDER BY created_at DESC 
                  LIMIT :limit";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        if ($type !== 'all') {
            $stmt->bindParam(':type', $type);
        }
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
