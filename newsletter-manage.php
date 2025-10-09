<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/newsletter_helpers.php';

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? '';
$email = trim((string) ($_GET['email'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));

$status = 'error';
$title = 'Newsletter preferences';
$message = 'Invalid request. Please double-check your link and try again.';
$extraContent = '';

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $message = 'The email address supplied is not valid.';
} else {
    switch ($action) {
        case 'verify':
            if ($email === '' || $token === '') {
                $message = 'Verification details are missing.';
                break;
            }

            $stmt = $db->prepare('SELECT id, verification_token, verified_at, is_active FROM newsletter_subscriptions WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$subscription) {
                $message = 'We could not find your subscription. Please subscribe again from the newsletter page.';
                break;
            }

            if (empty($subscription['verification_token']) || !hash_equals($subscription['verification_token'], $token)) {
                $message = 'This verification link has expired or is invalid. You can request a new one by subscribing again.';
                break;
            }

            $update = $db->prepare('UPDATE newsletter_subscriptions SET verified_at = NOW(), is_active = 1, verification_token = NULL, updated_at = NOW() WHERE id = :id');
            $update->execute([':id' => (int) $subscription['id']]);

            $status = 'success';
            $title = 'Subscription confirmed';
            $message = 'Thank you! Your email address has been verified and you will now receive our newsletter.';
            $extraContent = '<p class="mb-0">Need to unsubscribe later? You will find a quick link at the bottom of every email we send.</p>';
            break;

        case 'unsubscribe':
            if ($email === '' || $token === '') {
                $message = 'Missing unsubscribe details.';
                break;
            }

            $expected = newsletter_generate_unsubscribe_token($email);
            if (!hash_equals($expected, $token)) {
                $message = 'This unsubscribe link is no longer valid. Please use the most recent email we sent you.';
                break;
            }

            $stmt = $db->prepare('SELECT id, is_active FROM newsletter_subscriptions WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($subscription) {
                $update = $db->prepare('UPDATE newsletter_subscriptions SET is_active = 0, updated_at = NOW() WHERE id = :id');
                $update->execute([':id' => (int) $subscription['id']]);
            } else {
                $insert = $db->prepare('INSERT INTO newsletter_subscriptions (email, preferences, is_active, created_at, updated_at) VALUES (:email, :preferences, 0, NOW(), NOW())');
                $insert->execute([
                    ':email' => $email,
                    ':preferences' => json_encode([]),
                ]);
            }

            $status = 'success';
            $title = 'You have been unsubscribed';
            $message = 'You will no longer receive marketing emails from us. We are sorry to see you go.';
            $extraContent = '<p class="mb-0">Changed your mind? <a href="' . htmlspecialchars(SITE_URL . '/newsletter.php', ENT_QUOTES, 'UTF-8') . '">Subscribe again</a> at any time.</p>';
            break;

        default:
            $message = 'This management link has expired or is incorrect.';
    }
}

$page_title = $title . ' - ' . SITE_NAME;
include __DIR__ . '/includes/header.php';
?>

<div class="page-wrapper flex-grow-1 py-5">
    <div class="container" style="max-width:720px;">
        <div class="glass-card p-4 p-lg-5 text-center animate-fade-in">
            <div class="mb-4">
                <?php if ($status === 'success'): ?>
                    <div class="rounded-circle bg-success bg-opacity-10 text-success mx-auto d-flex align-items-center justify-content-center" style="width:72px;height:72px;">
                        <i class="fas fa-check fa-2x"></i>
                    </div>
                <?php else: ?>
                    <div class="rounded-circle bg-warning bg-opacity-10 text-warning mx-auto d-flex align-items-center justify-content-center" style="width:72px;height:72px;">
                        <i class="fas fa-exclamation fa-2x"></i>
                    </div>
                <?php endif; ?>
            </div>
            <h1 class="text-white fw-bold mb-3"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="text-muted mb-4"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php if ($extraContent !== ''): ?>
                <div class="text-muted">
                    <?php echo $extraContent; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
