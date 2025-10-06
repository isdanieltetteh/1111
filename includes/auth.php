<?php
// Include config first to ensure constants are defined
if (file_exists(__DIR__ . '/../config/config.php')) {
    require_once __DIR__ . '/../config/config.php';
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/security.php';

class Auth {
    private $db;
    private $securityManager;
    
    public function __construct() {
        try {
            // Ensure config is loaded
            if (!defined('DB_HOST')) {
                if (file_exists(__DIR__ . '/../config/config.php')) {
                    require_once __DIR__ . '/../config/config.php';
                } else {
                    throw new Exception('Configuration file not found');
                }
            }
            
            $database = new Database();
            $this->db = $database->getConnection();
            $this->securityManager = new SecurityManager($this->db);
        } catch (Exception $e) {
            error_log("Auth constructor error: " . $e->getMessage());
            // Fallback for logout scenarios
            $this->db = null;
            $this->securityManager = null;
        }
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Check if current user is admin
     */
    public function isAdmin() {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        if ($this->db) {
            $query = "SELECT is_admin FROM users WHERE id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && $user['is_admin'] == 1) {
                $_SESSION['is_admin'] = true; // Update session
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get current user data
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        if (!$this->db) {
            return null;
        }
        
        // Update last_active timestamp
        $update_active = "UPDATE users SET last_active = NOW() WHERE id = :user_id";
        $update_stmt = $this->db->prepare($update_active);
        $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $update_stmt->execute();
        
        $query = "SELECT u.*, 
                         l.name as active_badge_name, 
                         l.badge_icon as active_badge_icon, 
                         l.badge_color as active_badge_color,
                         l.difficulty as active_badge_difficulty,
                         l.description,
                         uw.deposit_balance,
                         uw.points_balance,
                         (SELECT COUNT(*) FROM reviews WHERE user_id = u.id) as total_reviews,
                         (SELECT COALESCE(SUM(upvotes), 0) FROM reviews WHERE user_id = u.id) as total_upvotes
                  FROM users u 
                  LEFT JOIN levels l ON u.active_badge_id = l.id
                  LEFT JOIN user_wallets uw ON u.id = uw.user_id
                  WHERE u.id = :user_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Ensure badge fields are set to avoid undefined key warnings
        if ($user) {
            $user['active_badge_name'] = $user['active_badge_name'] ?? 'Newcomer';
            $user['active_badge_icon'] = $user['active_badge_icon'] ?? '🆕';
            $user['active_badge_color'] = $user['active_badge_color'] ?? '#6b7280';
            $user['active_badge_difficulty'] = $user['active_badge_difficulty'] ?? 'newcomer';
            $user['description'] = $user['description'] ?? 'Welcome to the community!';
            $user['badge_icon'] = $user['active_badge_icon'];
            $user['level_name'] = $user['active_badge_name'];
        }
        
        return $user;
    }
    
    /**
     * Login user
     */
    public function login($username, $password, $remember_me = false, $ip_address = null) {
        if (!$ip_address) {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
        
        try {
            // Get user by username or email
            $query = "SELECT * FROM users WHERE (username = :username OR email = :username) AND is_banned = 0";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $this->securityManager->logSecurityEvent($ip_address, 'login_failed', [
                    'username' => $username,
                    'reason' => 'user_not_found'
                ], 'medium');
                return ['success' => false, 'message' => 'Invalid username or password'];
            }
            
            // Check if user is banned
            if ($user['is_banned']) {
                $this->securityManager->logSecurityEvent($ip_address, 'login_failed', [
                    'user_id' => $user['id'],
                    'username' => $username,
                    'reason' => 'user_banned'
                ], 'high', $user['id']);
                return ['success' => false, 'message' => 'Your account has been banned. Reason: ' . $user['ban_reason']];
            }
            
            // Verify password
            if (!password_verify($password, $user['password'])) {
                $this->securityManager->logSecurityEvent($ip_address, 'login_failed', [
                    'user_id' => $user['id'],
                    'username' => $username,
                    'reason' => 'invalid_password'
                ], 'medium', $user['id']);
                return ['success' => false, 'message' => 'Invalid username or password'];
            }
            
            // Update last login
            $update_query = "UPDATE users SET last_login = NOW(), last_active = NOW(), last_ip = :ip WHERE id = :user_id";
            $update_stmt = $this->db->prepare($update_query);
            $update_stmt->bindParam(':ip', $ip_address);
            $update_stmt->bindParam(':user_id', $user['id']);
            $update_stmt->execute();
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['is_admin'] = ($user['is_admin'] == 1);
            
            // Set remember me cookie if requested
            if ($remember_me) {
                $token = bin2hex(random_bytes(32));
                $expires = time() + (30 * 24 * 60 * 60); // 30 days
                
                // Store remember token in database
                $remember_query = "INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (:user_id, :token, :expires_at)
                                  ON DUPLICATE KEY UPDATE token = :token, expires_at = :expires_at";
                $remember_stmt = $this->db->prepare($remember_query);
                $remember_stmt->bindParam(':user_id', $user['id']);
                $remember_stmt->bindParam(':token', hash('sha256', $token));
                $remember_stmt->bindParam(':expires_at', date('Y-m-d H:i:s', $expires));
                $remember_stmt->execute();
                
                setcookie('remember_token', $token, $expires, '/', '', true, true);
            }
            
            // Log successful login
            $this->securityManager->logSecurityEvent($ip_address, 'login_success', [
                'user_id' => $user['id'],
                'username' => $user['username']
            ], 'low', $user['id']);
            
            return ['success' => true, 'message' => 'Login successful'];
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred during login'];
        }
    }
    
    /**
     * Register new user
     */
    public function register($username, $email, $password, $newsletter_preferences = [], $ip_address = null) {
        if (!$ip_address) {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
        
        // Get referrer ID from POST data if available
        $referrer_id = $_POST['referrer_id'] ?? null;
        
        try {
            // Check if username exists
            $check_query = "SELECT id FROM users WHERE username = :username OR email = :email";
            $check_stmt = $this->db->prepare($check_query);
            $check_stmt->bindParam(':username', $username);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->execute();
            
            if ($check_stmt->fetch()) {
                return ['success' => false, 'message' => 'Username or email already exists'];
            }
            
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Generate referral code from username
            $referral_code = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $username));
            
            $referrer_value = null;
            if ($referrer_id !== null && !is_array($referrer_id)) {
                $referrer_value = (int)$referrer_id;
            }
            
            // Record IP registration for security tracking
            $this->securityManager->recordIPRegistration($ip_address, null); // Will update with user_id after insert
            
            // Insert new user
            $insert_query = "INSERT INTO users (username, email, password, referred_by, referral_code, created_at, last_ip) 
                            VALUES (:username, :email, :password, :referred_by, :referral_code, NOW(), :ip)";
            $insert_stmt = $this->db->prepare($insert_query);
            $insert_stmt->bindParam(':username', $username);
            $insert_stmt->bindParam(':email', $email);
            $insert_stmt->bindParam(':password', $hashed_password);
            $insert_stmt->bindParam(':referred_by', $referrer_value, PDO::PARAM_INT);
            $insert_stmt->bindParam(':referral_code', $referral_code);
            $insert_stmt->bindParam(':ip', $ip_address);
            $insert_stmt->execute();
            
            $new_user_id = $this->db->lastInsertId();
            
            // Update IP registration with user ID
            $update_ip_reg = "UPDATE ip_registrations SET user_id = :user_id WHERE ip_address = :ip_address AND user_id IS NULL ORDER BY created_at DESC LIMIT 1";
            $update_ip_stmt = $this->db->prepare($update_ip_reg);
            $update_ip_stmt->bindParam(':user_id', $new_user_id);
            $update_ip_stmt->bindParam(':ip_address', $ip_address);
            $update_ip_stmt->execute();
            
            // Log successful registration
            $this->securityManager->logSecurityEvent($ip_address, 'user_registered', [
                'user_id' => $new_user_id,
                'username' => $username,
                'has_referrer' => $referrer_id !== null
            ], 'low', $new_user_id);
            
            // Create user wallet
            $wallet_query = "INSERT INTO user_wallets (user_id) VALUES (:user_id)";
            $wallet_stmt = $this->db->prepare($wallet_query);
            $wallet_stmt->bindParam(':user_id', $new_user_id);
            $wallet_stmt->execute();
            
            // Initialize wallet manager
            if ($new_user_id && file_exists(__DIR__ . '/wallet.php')) {
                require_once 'wallet.php';
                $wallet_manager = new WalletManager($this->db);
                
                if ($referrer_value) {
                    $wallet_manager->createReferral($referrer_value, $new_user_id, $referral_code);
                    
                    // Give referral bonus to referrer
                    $wallet_manager->addPoints($referrer_value, REFERRAL_SIGNUP_BONUS, 'referral_bonus', "New referral signup: {$username}", $new_user_id, 'referral_signup');
                }
                
                // Handle newsletter subscription
                if (!empty($newsletter_preferences) && is_array($newsletter_preferences)) {
                    $newsletter_query = "INSERT INTO newsletter_subscriptions (email, user_id, preferences, verified_at) 
                                       VALUES (:email, :user_id, :preferences, NOW())";
                    $newsletter_stmt = $this->db->prepare($newsletter_query);
                    $newsletter_stmt->bindParam(':email', $email);
                    $newsletter_stmt->bindParam(':user_id', $new_user_id);
                    $preferences_json = json_encode($newsletter_preferences);
                    $newsletter_stmt->bindParam(':preferences', $preferences_json);
                    $newsletter_stmt->execute();
                }
                
                // Give signup bonus to new user
                $wallet_manager->addPoints($new_user_id, POINTS_REGISTER, 'earned', 'Welcome bonus for joining');
            }
            
            return ['success' => true, 'message' => 'Registration successful'];
            
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred during registration'];
        }
    }
    
    /**
     * Logout user
     */
    public function logout() {
        if ($this->isLoggedIn() && $this->securityManager) {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            
            // Log logout
            $this->securityManager->logSecurityEvent($ip_address, 'user_logout', [
                'user_id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'] ?? 'unknown'
            ], 'low', $_SESSION['user_id']);
        }
        
        // Destroy session
        session_destroy();
        session_start();
    }
    
    /**
     * Get user's earned badges
     */
    public function getUserBadges($user_id) {
        $query = "SELECT l.*, ub.earned_at 
                  FROM user_badges ub 
                  JOIN levels l ON ub.badge_id = l.id 
                  WHERE ub.user_id = :user_id 
                  ORDER BY ub.earned_at DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Set user's active badge
     */
    public function setActiveBadge($user_id, $badge_id) {
        try {
            // Check if user has earned this badge
            $check_query = "SELECT id FROM user_badges WHERE user_id = :user_id AND badge_id = :badge_id";
            $check_stmt = $this->db->prepare($check_query);
            $check_stmt->bindParam(':user_id', $user_id);
            $check_stmt->bindParam(':badge_id', $badge_id);
            $check_stmt->execute();
            
            if (!$check_stmt->fetch()) {
                return false; // User hasn't earned this badge
            }
            
            // Update user's active badge
            $update_query = "UPDATE users SET active_badge_id = :badge_id WHERE id = :user_id";
            $update_stmt = $this->db->prepare($update_query);
            $update_stmt->bindParam(':badge_id', $badge_id);
            $update_stmt->bindParam(':user_id', $user_id);
            $update_stmt->execute();
            
            return true;
            
        } catch (Exception $e) {
            error_log("Set active badge error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update user badges based on achievements
     */
    public function updateUserBadges($user_id) {
        try {
            // Get user stats
            $user_stats_query = "SELECT 
                u.reputation_points,
                u.total_reviews,
                u.total_upvotes,
                u.total_submissions,
                (SELECT COUNT(*) FROM reports WHERE user_id = u.id AND status = 'confirmed') as confirmed_reports
                FROM users u 
                WHERE u.id = :user_id";
            $user_stats_stmt = $this->db->prepare($user_stats_query);
            $user_stats_stmt->bindParam(':user_id', $user_id);
            $user_stats_stmt->execute();
            $user_stats = $user_stats_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user_stats) return false;
            
            // Get all available badges
            $badges_query = "SELECT * FROM levels ORDER BY min_reputation ASC";
            $badges_stmt = $this->db->prepare($badges_query);
            $badges_stmt->execute();
            $badges = $badges_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($badges as $badge) {
                // Check if user has already earned this badge
                $earned_query = "SELECT id FROM user_badges WHERE user_id = :user_id AND badge_id = :badge_id";
                $earned_stmt = $this->db->prepare($earned_query);
                $earned_stmt->bindParam(':user_id', $user_id);
                $earned_stmt->bindParam(':badge_id', $badge['id']);
                $earned_stmt->execute();
                
                // Skip if already earned
                if ($earned_stmt->fetch()) {
                    continue;
                }
                
                $meets_requirements = $this->checkBadgeRequirements($badge, $user_stats);
                
                if ($meets_requirements) {
                    // Award badge
                    $award_query = "INSERT INTO user_badges (user_id, badge_id, earned_at) VALUES (:user_id, :badge_id, NOW())";
                    $award_stmt = $this->db->prepare($award_query);
                    $award_stmt->bindParam(':user_id', $user_id);
                    $award_stmt->bindParam(':badge_id', $badge['id']);
                    $award_stmt->execute();
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Update user badges error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if user meets all badge requirements
     */
    private function checkBadgeRequirements($badge, $user_stats) {
        // Always check minimum reputation first
        if ($user_stats['reputation_points'] < $badge['min_reputation']) {
            return false;
        }
        
        // Special badges (Moderator, Administrator) require manual assignment
        if ($badge['difficulty'] === 'special' && in_array($badge['id'], [13, 14])) {
            return false;
        }
        
        // Parse requirements from the requirements text field
        $requirements = strtolower($badge['requirements']);
        
        // Check for review requirements (e.g., "5+ reviews", "10+ reviews")
        if (preg_match('/(\d+)\+?\s*reviews?/', $requirements, $matches)) {
            $required_reviews = (int)$matches[1];
            if ($user_stats['total_reviews'] < $required_reviews) {
                return false;
            }
        }
        
        // Check for upvote requirements (e.g., "50+ upvotes", "100+ upvotes")
        if (preg_match('/(\d+)\+?\s*upvotes?/', $requirements, $matches)) {
            $required_upvotes = (int)$matches[1];
            if ($user_stats['total_upvotes'] < $required_upvotes) {
                return false;
            }
        }
        
        // Check for submission requirements (e.g., "5+ approved submissions")
        if (preg_match('/(\d+)\+?\s*(?:approved\s+)?submissions?/', $requirements, $matches)) {
            $required_submissions = (int)$matches[1];
            if ($user_stats['total_submissions'] < $required_submissions) {
                return false;
            }
        }
        
        // Check for report requirements (e.g., "5+ confirmed scam sites", "Report 5+ confirmed")
        if (preg_match('/(?:report\s+)?(\d+)\+?\s*(?:confirmed|scam)/', $requirements, $matches)) {
            $required_reports = (int)$matches[1];
            if ($user_stats['confirmed_reports'] < $required_reports) {
                return false;
            }
        }
        
        // All requirements met
        return true;
    }

    /**
     * Check if user has permission for specific action
     */
    public function hasPermission($permission) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        // Admin has all permissions
        if ($this->isAdmin()) {
            return true;
        }
        
        // Add specific permission checks here as needed
        return false;
    }
    
    /**
     * Get user by ID
     */
    public function getUserById($user_id) {
        $query = "SELECT * FROM users WHERE id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
