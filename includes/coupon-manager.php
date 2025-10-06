<?php
class CouponManager {
    private $db;
    private $security;
    
    public function __construct($database) {
        $this->db = $database;
        require_once __DIR__ . '/security.php';
        $this->security = new SecurityManager($database);
    }
    
    /**
     * Generate secure coupon code
     */
    public function generateCouponCode($prefix = '', $length = 8) {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = $prefix;
        
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        // Ensure uniqueness
        $check_query = "SELECT id FROM coupons WHERE code = :code";
        $check_stmt = $this->db->prepare($check_query);
        $check_stmt->bindParam(':code', $code);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            return $this->generateCouponCode($prefix, $length); // Recursive retry
        }
        
        return $code;
    }
    
    /**
     * Create new coupon with security validation
     */
 public function createCoupon($data, $admin_id) {
    try {
        // Generate unique coupon code
        $code = strtoupper(bin2hex(random_bytes(4)));

        // Assign values to variables (so they can be bound by reference)
        $title            = $data['title'];
        $description      = $data['description'] ?? '';
        $coupon_type      = $data['coupon_type'];
        $value            = $data['value'];
        $minimum_deposit  = $data['minimum_deposit'] ?? 0;
        $usage_limit      = $data['usage_limit'] ?? null;
        $user_limit       = $data['user_limit_per_account'] ?? 1;
        $expires_at       = $data['expires_at'] ?? null;

        // Insert query
        $insert_query = "INSERT INTO coupons 
            (code, title, description, coupon_type, value, minimum_deposit, usage_limit, user_limit_per_account, expires_at, created_by) 
            VALUES 
            (:code, :title, :description, :coupon_type, :value, :minimum_deposit, :usage_limit, :user_limit_per_account, :expires_at, :created_by)";

        $insert_stmt = $this->db->prepare($insert_query);

        // Bind params (variables only, not expressions)
        $insert_stmt->bindParam(':code', $code, PDO::PARAM_STR);
        $insert_stmt->bindParam(':title', $title, PDO::PARAM_STR);
        $insert_stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $insert_stmt->bindParam(':coupon_type', $coupon_type, PDO::PARAM_STR);
        $insert_stmt->bindParam(':value', $value, PDO::PARAM_STR);
        $insert_stmt->bindParam(':minimum_deposit', $minimum_deposit, PDO::PARAM_INT);
        $insert_stmt->bindParam(':usage_limit', $usage_limit, PDO::PARAM_INT);
        $insert_stmt->bindParam(':user_limit_per_account', $user_limit, PDO::PARAM_INT);
        $insert_stmt->bindParam(':expires_at', $expires_at);
        $insert_stmt->bindParam(':created_by', $admin_id, PDO::PARAM_INT);

        $insert_stmt->execute();

        return [
            'success' => true,
            'message' => 'Coupon created successfully',
            'coupon_code' => $code
        ];
    } catch (Exception $e) {
        error_log("Coupon creation failed: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Coupon creation failed: ' . $e->getMessage()
        ];
    }
}

    /**
     * Validate coupon with comprehensive security checks
     */
    public function validateCoupon($code, $user_id, $deposit_amount = 0) {
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        
        // Log validation attempt
        $this->logCouponAction(null, $user_id, $ip_address, 'validation_attempt', [
            'code' => $code,
            'deposit_amount' => $deposit_amount
        ], 'low');
        
        // Get coupon details
        $coupon_query = "SELECT * FROM coupons WHERE code = :code AND is_active = 1";
        $coupon_stmt = $this->db->prepare($coupon_query);
        $coupon_stmt->bindParam(':code', $code);
        $coupon_stmt->execute();
        $coupon = $coupon_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$coupon) {
            $this->logCouponAction(null, $user_id, $ip_address, 'invalid_code', ['code' => $code], 'medium', true);
            return ['valid' => false, 'message' => 'Invalid coupon code'];
        }
        
        // Check expiration
        if ($coupon['expires_at'] && strtotime($coupon['expires_at']) < time()) {
            $this->logCouponAction($coupon['id'], $user_id, $ip_address, 'expired_coupon', [], 'low');
            return ['valid' => false, 'message' => 'Coupon has expired'];
        }
        
        // Check usage limit
        if ($coupon['usage_limit'] && $coupon['usage_count'] >= $coupon['usage_limit']) {
            $this->logCouponAction($coupon['id'], $user_id, $ip_address, 'usage_limit_exceeded', [], 'medium');
            return ['valid' => false, 'message' => 'Coupon usage limit reached'];
        }
        
        // Check user redemption limit
        $user_redemptions_query = "SELECT COUNT(*) as count FROM coupon_redemptions WHERE coupon_id = :coupon_id AND user_id = :user_id";
        $user_redemptions_stmt = $this->db->prepare($user_redemptions_query);
        $user_redemptions_stmt->bindParam(':coupon_id', $coupon['id']);
        $user_redemptions_stmt->bindParam(':user_id', $user_id);
        $user_redemptions_stmt->execute();
        $user_redemptions = $user_redemptions_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_redemptions['count'] >= $coupon['user_limit_per_account']) {
            $this->logCouponAction($coupon['id'], $user_id, $ip_address, 'user_limit_exceeded', [], 'medium');
            return ['valid' => false, 'message' => 'You have already used this coupon'];
        }
        
        // Check minimum deposit requirement
        if ($coupon['minimum_deposit'] > 0 && $deposit_amount < $coupon['minimum_deposit']) {
            return ['valid' => false, 'message' => 'Minimum deposit of $' . number_format($coupon['minimum_deposit'], 2) . ' required'];
        }
        
        // Anti-fraud checks
        $fraud_check = $this->performFraudChecks($coupon['id'], $user_id, $ip_address);
        if (!$fraud_check['safe']) {
            $this->logCouponAction($coupon['id'], $user_id, $ip_address, 'fraud_detected', $fraud_check['details'], 'critical', true);
            return ['valid' => false, 'message' => 'Security validation failed'];
        }
        
        return ['valid' => true, 'coupon' => $coupon];
    }
    
    /**
     * Redeem coupon with security verification
     */
    public function redeemCoupon($code, $user_id, $deposit_amount = 0) {
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        
        // Validate coupon first
        $validation = $this->validateCoupon($code, $user_id, $deposit_amount);
        if (!$validation['valid']) {
            return $validation;
        }
        
        $coupon = $validation['coupon'];
        
        try {
            $this->db->beginTransaction();
            
            // Calculate redemption value
            $redemption_value = $this->calculateRedemptionValue($coupon, $deposit_amount);
            
            // Generate security hash for verification
            $security_data = [
                'coupon_id' => $coupon['id'],
                'user_id' => $user_id,
                'value' => $redemption_value,
                'timestamp' => time(),
                'ip' => $ip_address
            ];
            $security_hash = hash('sha256', json_encode($security_data) . SITE_SECRET_KEY);
            
            // Generate verification token
            $verification_token = bin2hex(random_bytes(32));
            
            // Record redemption
            $redemption_query = "INSERT INTO coupon_redemptions 
                                (coupon_id, user_id, ip_address, user_agent, redemption_value, 
                                 security_hash, verification_token) 
                                VALUES (:coupon_id, :user_id, :ip_address, :user_agent, :redemption_value,
                                        :security_hash, :verification_token)";
            
            $redemption_stmt = $this->db->prepare($redemption_query);
            $redemption_stmt->bindParam(':coupon_id', $coupon['id']);
            $redemption_stmt->bindParam(':user_id', $user_id);
            $redemption_stmt->bindParam(':ip_address', $ip_address);
            $redemption_stmt->bindParam(':user_agent', $user_agent);
            $redemption_stmt->bindParam(':redemption_value', $redemption_value);
            $redemption_stmt->bindParam(':security_hash', $security_hash);
            $redemption_stmt->bindParam(':verification_token', $verification_token);
            $redemption_stmt->execute();
            
            $redemption_id = $this->db->lastInsertId();
            
            // Update coupon usage count
            $update_usage = "UPDATE coupons SET usage_count = usage_count + 1 WHERE id = :coupon_id";
            $update_stmt = $this->db->prepare($update_usage);
            $update_stmt->bindParam(':coupon_id', $coupon['id']);
            $update_stmt->execute();
            
            // Add to user wallet (with verification pending)
            $this->addCouponValueToWallet($user_id, $redemption_value, $coupon, $redemption_id);
            
            // Log successful redemption
            $this->logCouponAction($coupon['id'], $user_id, $ip_address, 'coupon_redeemed', [
                'redemption_id' => $redemption_id,
                'value' => $redemption_value,
                'verification_token' => $verification_token
            ], 'low');
            
            $this->db->commit();
            
            return [
                'success' => true, 
                'message' => 'Coupon redeemed successfully!',
                'value' => $redemption_value,
                'verification_required' => true,
                'verification_token' => $verification_token
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            $this->logCouponAction($coupon['id'], $user_id, $ip_address, 'redemption_error', [
                'error' => $e->getMessage()
            ], 'high', true);
            return ['success' => false, 'message' => 'Error processing coupon redemption'];
        }
    }
    
    /**
     * Calculate redemption value based on coupon type
     */
    private function calculateRedemptionValue($coupon, $deposit_amount) {
        switch ($coupon['coupon_type']) {
            case 'deposit_bonus':
                return $coupon['value'];
            case 'percentage_bonus':
                return $deposit_amount * ($coupon['value'] / 100);
            case 'points_bonus':
                return $coupon['value']; // Points converted to USD
            case 'credits_bonus':
                return $coupon['value'];
            default:
                return $coupon['value'];
        }
    }
    
    /**
     * Add coupon value to user wallet with security verification
     */
 /**
 * Add coupon value directly to user's credits balance
 */
private function addCouponValueToWallet($user_id, $value, $coupon, $redemption_id) {
    $value = (float)$value; // ensure numeric

    // Update user credits
    $update_query = "UPDATE users 
                     SET credits = credits + :value 
                     WHERE id = :user_id";
    $update_stmt = $this->db->prepare($update_query);
    $update_stmt->bindParam(':value', $value, PDO::PARAM_STR);
    $update_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $update_stmt->execute();

    // Log deposit transaction
    $transaction_query = "INSERT INTO deposit_transactions 
                         (user_id, amount, currency, payment_method, status, coupon_redemption_id, description) 
                         VALUES (:user_id, :amount, 'USD', 'coupon', 'completed', :redemption_id, :description)";
    $transaction_stmt = $this->db->prepare($transaction_query);
    $transaction_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $transaction_stmt->bindParam(':amount', $value, PDO::PARAM_STR);
    $transaction_stmt->bindParam(':redemption_id', $redemption_id, PDO::PARAM_INT);
    $description = "Coupon redemption: " . $coupon['title'];
    $transaction_stmt->bindParam(':description', $description, PDO::PARAM_STR);
    $transaction_stmt->execute();
}

    /**
     * Perform comprehensive fraud checks
     */
    private function performFraudChecks($coupon_id, $user_id, $ip_address) {
        $risk_factors = [];
        $risk_score = 0;
        
        // Check IP redemption frequency
        $ip_redemptions_query = "SELECT COUNT(*) as count FROM coupon_redemptions 
                                WHERE ip_address = :ip_address AND redeemed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $ip_stmt = $this->db->prepare($ip_redemptions_query);
        $ip_stmt->bindParam(':ip_address', $ip_address);
        $ip_stmt->execute();
        $ip_redemptions = $ip_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($ip_redemptions['count'] >= 5) {
            $risk_factors[] = 'High IP redemption frequency';
            $risk_score += 30;
        }
        
        // Check user account age
        $user_query = "SELECT created_at, reputation_points FROM users WHERE id = :user_id";
        $user_stmt = $this->db->prepare($user_query);
        $user_stmt->bindParam(':user_id', $user_id);
        $user_stmt->execute();
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $account_age_hours = (time() - strtotime($user['created_at'])) / 3600;
        } else {
            $account_age_hours = 0;
        }
        
        if ($account_age_hours < 1) {
            $risk_factors[] = 'Very new account';
            $risk_score += 25;
        }
        
        // Check user reputation
        if ($user && $user['reputation_points'] < 10) {
            $risk_factors[] = 'Low reputation account';
            $risk_score += 15;
        }
        
        // Check for multiple accounts from same IP
        $ip_accounts_query = "SELECT COUNT(DISTINCT user_id) as count FROM ip_registrations WHERE ip_address = :ip_address";
        $ip_accounts_stmt = $this->db->prepare($ip_accounts_query);
        $ip_accounts_stmt->bindParam(':ip_address', $ip_address);
        $ip_accounts_stmt->execute();
        $ip_accounts = $ip_accounts_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($ip_accounts['count'] > 1) {
            $risk_factors[] = 'Multiple accounts from IP';
            $risk_score += 40;
        }
        
        // Determine if safe
        $is_safe = $risk_score < 50;
        
        return [
            'safe' => $is_safe,
            'risk_score' => $risk_score,
            'details' => [
                'risk_factors' => $risk_factors,
                'ip_redemptions_24h' => $ip_redemptions['count'],
                'account_age_hours' => round($account_age_hours, 2),
                'reputation_points' => $user ? $user['reputation_points'] : 0,
                'ip_account_count' => $ip_accounts['count']
            ]
        ];
    }
    
    /**
     * Log coupon-related security events
     */
/**
 * Log coupon-related security events
 */
private function logCouponAction($coupon_id, $user_id, $ip_address, $action, $details, $risk_level, $is_suspicious = false) {
    try {
        $log_query = "INSERT INTO coupon_security_logs 
                      (coupon_id, user_id, ip_address, action, details, risk_level, is_suspicious) 
                      VALUES (:coupon_id, :user_id, :ip_address, :action, :details, :risk_level, :is_suspicious)";
        $log_stmt = $this->db->prepare($log_query);

        $details_json = json_encode($details);

        $log_stmt->bindParam(':coupon_id', $coupon_id, PDO::PARAM_INT);
        $log_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $log_stmt->bindParam(':ip_address', $ip_address, PDO::PARAM_STR);
        $log_stmt->bindParam(':action', $action, PDO::PARAM_STR);
        $log_stmt->bindParam(':details', $details_json, PDO::PARAM_STR); // fixed
        $log_stmt->bindParam(':risk_level', $risk_level, PDO::PARAM_STR);
        $log_stmt->bindValue(':is_suspicious', $is_suspicious ? 1 : 0, PDO::PARAM_INT);

        $log_stmt->execute();
    } catch (Exception $e) {
        error_log("Coupon security logging failed: " . $e->getMessage());
    }
}

    
    /**
    /**
 * Get user's coupon redemption history
 */
public function getUserRedemptions($user_id) {
    $query = "SELECT cr.*, c.title, c.code, c.coupon_type 
              FROM coupon_redemptions cr
              JOIN coupons c ON cr.coupon_id = c.id
              WHERE cr.user_id = :user_id
              ORDER BY cr.redeemed_at DESC";

    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    
    /**
     * Get active coupons for admin
     */
    public function getActiveCoupons() {
        $query = "SELECT c.*, u.username as created_by_username,
                 (SELECT COUNT(*) FROM coupon_redemptions WHERE coupon_id = c.id) as total_redemptions
                 FROM coupons c
                 JOIN users u ON c.created_by = u.id
                 WHERE c.is_active = 1
                 ORDER BY c.created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Verify redemption integrity (anti-tampering)
     */
    public function verifyRedemptionIntegrity($redemption_id, $verification_token) {
        $query = "SELECT cr.*, c.* FROM coupon_redemptions cr
                 JOIN coupons c ON cr.coupon_id = c.id
                 WHERE cr.id = :redemption_id AND cr.verification_token = :token";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':redemption_id', $redemption_id);
        $stmt->bindParam(':token', $verification_token);
        $stmt->execute();
        $redemption = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$redemption) {
            return false;
        }
        
        // Verify security hash
        $security_data = [
            'coupon_id' => $redemption['coupon_id'],
            'user_id' => $redemption['user_id'],
            'value' => $redemption['redemption_value'],
            'timestamp' => strtotime($redemption['redeemed_at']),
            'ip' => $redemption['ip_address']
        ];
        $expected_hash = hash('sha256', json_encode($security_data) . SITE_SECRET_KEY);
        
        return hash_equals($redemption['security_hash'], $expected_hash);
    }
}
?>
