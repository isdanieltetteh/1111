<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/wallet.php';

$database = new Database();
$db = $database->getConnection();
$wallet_manager = new WalletManager($db);

// FaucetPay sends POST with token
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'])) {
    $token = $_POST['token'];

    // Verify payment with FaucetPay API
    $payment_info = file_get_contents("https://faucetpay.io/merchant/get-payment/" . $token);
    $payment_info = json_decode($payment_info, true);

    if (!$payment_info || !$payment_info['valid']) {
        http_response_code(400);
        exit("Invalid payment verification");
    }

    $merchant_username = $payment_info['merchant_username'];
    if ($merchant_username !== FAUCETPAY_MERCHANT_USERNAME) {
        http_response_code(400);
        exit("Invalid merchant");
    }

    $amount_usd = floatval($payment_info['amount1']);
    $currency   = $payment_info['currency2'];
    $txn_id     = $payment_info['transaction_id'];
    $custom     = $payment_info['custom'];

    // Extract from custom: credits_userid_amount
    $parts = explode('_', $custom);
    if (count($parts) < 3 || $parts[0] !== 'credits') {
        http_response_code(400);
        exit("Invalid custom data");
    }

    $user_id = intval($parts[1]);
    $credits = floatval($parts[2]);

    // Prevent duplicates
    $stmt = $db->prepare("SELECT id FROM credit_transactions WHERE transaction_id = ?");
    $stmt->execute([$txn_id]);
    if ($stmt->fetch()) {
        echo "OK"; // already processed
        exit();
    }

    // Add credits to user balance
    if ($wallet_manager->processDeposit($user_id, $credits, $currency, $txn_id)) {
        echo "OK"; // FaucetPay expects this
        exit();
    } else {
        http_response_code(500);
        exit("Deposit failed");
    }
}

http_response_code(400);
exit("Invalid request");

?>
