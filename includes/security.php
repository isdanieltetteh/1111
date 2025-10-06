<?php
class SecurityManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Log security events
     */
    public function logSecurityEvent($ip_address, $action, $details = [], $risk_level = 'low', $user_id = null) {
        try {
            $query = "INSERT INTO security_logs (ip_address, user_id, action, details, risk_level) 
                     VALUES (:ip_address, :user_id, :action, :details, :risk_level)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':ip_address', $ip_address);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':action', $action);
            $details_json = json_encode($details);
            $stmt->bindParam(':details', $details_json);
            $stmt->bindParam(':risk_level', $risk_level);
            $stmt->execute();
        } catch (Exception $e) {
            // Log to error log if database logging fails
            error_log("Security logging failed: " . $e->getMessage());
        }
    }
    
    /**
     * Check if IP is blocked
     */
    public function isIPBlocked($ip_address) {
        try {
            $query = "SELECT id FROM blocked_ips 
                     WHERE ip_address = :ip_address 
                     AND (expires_at IS NULL OR expires_at > NOW() OR is_permanent = 1)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':ip_address', $ip_address);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Check for multiple accounts from same IP
     */
    public function checkMultipleAccounts($ip_address, $exclude_user_id = null) {
        try {
            $query = "SELECT COUNT(DISTINCT user_id) as count FROM ip_registrations WHERE ip_address = :ip_address";
            if ($exclude_user_id) {
                $query .= " AND user_id != :exclude_user_id";
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':ip_address', $ip_address);
            if ($exclude_user_id) {
                $stmt->bindParam(':exclude_user_id', $exclude_user_id);
            }
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'] ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Check if email domain is temporary/disposable
     */
    public function isTempEmailDomain($email) {
        try {
            $domain = strtolower(substr(strrchr($email, "@"), 1));
            
            $query = "SELECT id FROM temp_email_domains WHERE domain = :domain AND is_blocked = 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':domain', $domain);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Record IP registration
     */
    public function recordIPRegistration($ip_address, $user_id) {
        try {
            $query = "INSERT INTO ip_registrations (ip_address, user_id) VALUES (?, ?)";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$ip_address, $user_id]);
        } catch (Exception $e) {
            error_log("IP registration logging failed: " . $e->getMessage());
        }
    }
    
    /**
     * Validate registration security
     */
    public function validateRegistration($ip_address, $email, $username) {
        $errors = [];
        
        // Check if IP is blocked
        if ($this->isIPBlocked($ip_address)) {
            $errors[] = 'Your IP address has been blocked';
        }
        
        // Check for too many accounts from same IP
        $account_count = $this->checkMultipleAccounts($ip_address);
        if ($account_count >= MAX_ACCOUNTS_PER_IP) {
            $errors[] = 'Maximum number of accounts reached for this IP address';
        }
        
        // Check for temporary email
        if ($this->isTempEmailDomain($email)) {
            $errors[] = 'Temporary or disposable email addresses are not allowed';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Check if registration is allowed from this IP
     */
    public function canRegisterFromIP($ip_address) {
        // Check if IP is blocked
        if ($this->isIPBlocked($ip_address)) {
            return [
                'allowed' => false,
                'reason' => 'Your IP address has been blocked'
            ];
        }
        
        // Check for too many accounts from same IP
        $account_count = $this->checkMultipleAccounts($ip_address);
        if ($account_count >= MAX_ACCOUNTS_PER_IP) {
            return [
                'allowed' => false,
                'reason' => 'Maximum number of accounts reached for this IP address'
            ];
        }
        
        return [
            'allowed' => true,
            'reason' => ''
        ];
    }
}
?>
