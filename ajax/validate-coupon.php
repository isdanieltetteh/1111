<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/coupon-manager.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login to validate coupons']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$coupon_code = strtoupper(trim($input['coupon_code'] ?? ''));
$deposit_amount = floatval($input['deposit_amount'] ?? 0);
$user_id = $_SESSION['user_id'];

if (empty($coupon_code)) {
    echo json_encode(['valid' => false, 'message' => 'Coupon code is required']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $coupon_manager = new CouponManager($db);
    
    $validation = $coupon_manager->validateCoupon($coupon_code, $user_id, $deposit_amount);
    
    if ($validation['valid']) {
        $coupon = $validation['coupon'];
        
        // Calculate preview value
        $preview_value = 0;
        switch ($coupon['coupon_type']) {
            case 'deposit_bonus':
                $preview_value = $coupon['value'];
                break;
            case 'percentage_bonus':
                $preview_value = $deposit_amount * ($coupon['value'] / 100);
                break;
            case 'points_bonus':
            case 'credits_bonus':
                $preview_value = $coupon['value'];
                break;
        }
        
        echo json_encode([
            'valid' => true,
            'coupon' => [
                'title' => $coupon['title'],
                'description' => $coupon['description'],
                'type' => $coupon['coupon_type'],
                'value' => $coupon['value'],
                'preview_value' => $preview_value,
                'minimum_deposit' => $coupon['minimum_deposit'],
                'expires_at' => $coupon['expires_at']
            ]
        ]);
    } else {
        echo json_encode($validation);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['valid' => false, 'message' => 'Error validating coupon']);
}
?>
