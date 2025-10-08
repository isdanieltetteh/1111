<?php
require_once __DIR__ . '/../config/config.php';

/**
 * Generate a verification token for newsletter subscriptions.
 */
function newsletter_generate_verification_token(): string
{
    return bin2hex(random_bytes(32));
}

/**
 * Build a verification URL for the subscriber.
 */
function newsletter_verification_url(string $email, string $token): string
{
    return SITE_URL . '/newsletter-manage.php?action=verify&email=' . rawurlencode($email) . '&token=' . rawurlencode($token);
}

/**
 * Generate a signed unsubscribe token for the provided email.
 */
function newsletter_generate_unsubscribe_token(string $email): string
{
    $normalized = strtolower(trim($email));
    return hash_hmac('sha256', $normalized . '|unsubscribe', SITE_SECRET_KEY);
}

/**
 * Build the unsubscribe management URL.
 */
function newsletter_unsubscribe_url(string $email): string
{
    $token = newsletter_generate_unsubscribe_token($email);

    return SITE_URL . '/newsletter-manage.php?action=unsubscribe&email=' . rawurlencode($email) . '&token=' . $token;
}

/**
 * Dispatch a confirmation email using the centralised template system.
 */
function newsletter_send_confirmation_email(MailService $mailer, string $email, string $verificationToken): void
{
    require_once __DIR__ . '/email_template.php';

    $verificationUrl = newsletter_verification_url($email, $verificationToken);
    $unsubscribeUrl = newsletter_unsubscribe_url($email);

    $context = email_build_context([
        'subject' => 'Confirm your subscription',
        'preheader' => 'Click to confirm your subscription to ' . SITE_NAME,
        'unsubscribe_url' => $unsubscribeUrl,
    ]);

    $context['verification_url'] = htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8');
    $context['verification_url_plain'] = $verificationUrl;

    $htmlTemplate = <<<HTML
<p>Hi {{name}},</p>
<p>Thanks for joining the {{site_name}} insider list.</p>
<p style="margin:24px 0;"><a href="{{verification_url}}" style="background-color:#2563eb;color:#ffffff;padding:14px 28px;border-radius:999px;text-decoration:none;font-weight:600;">Confirm my email</a></p>
<p>If the button above does not work, copy and paste this link into your browser:</p>
<p style="word-break:break-all;">{{verification_url}}</p>
<p>This extra step ensures nobody can sign you up without permission.</p>
HTML;

    $textTemplate = <<<TEXT
Hi {{name}},

Thanks for joining the {{site_name}} insider list. Confirm your address by visiting: {{verification_url_plain}}

If you didn't request this, you can ignore the email.
TEXT;

    [$htmlBody, $textBody] = email_render_bodies($htmlTemplate, $textTemplate, $context, $context['preheader']);

    $mailer->send(
        [['email' => $email, 'name' => '']],
        '[' . SITE_NAME . '] Confirm your subscription',
        $htmlBody,
        [
            'text' => $textBody,
            'reply_to' => ['email' => SITE_EMAIL, 'name' => SITE_NAME],
            'list_unsubscribe' => [$unsubscribeUrl, 'mailto:' . SITE_EMAIL],
            'list_unsubscribe_post' => true,
            'custom_headers' => ['X-Subscription-Flow' => 'newsletter-confirm'],
        ]
    );
}

