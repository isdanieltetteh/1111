<?php
require_once __DIR__ . '/../config/config.php';

/**
 * Provide the standard context values shared across email templates.
 */
function email_build_context(array $overrides = []): array
{
    $defaults = [
        'site_name' => SITE_NAME,
        'site_url' => SITE_URL,
        'support_email' => SITE_EMAIL,
        'preheader' => '',
        'subject' => '',
        'name' => 'there',
        'username' => 'there',
        'unsubscribe_url' => '',
    ];

    return array_merge($defaults, $overrides);
}

/**
 * Return the default HTML fragment used inside the email layout.
 */
function email_default_content_html(): string
{
    return <<<HTML
<h2 style="margin-top:0;margin-bottom:16px;font-size:22px;line-height:1.3;font-weight:600;color:#111827;">Latest update from {{site_name}}</h2>
<p style="margin:0 0 16px;color:#374151;font-size:15px;line-height:1.6;">Hi {{name}},</p>
<p style="margin:0 0 16px;color:#374151;font-size:15px;line-height:1.6;">We wanted to share some fresh news with you. Replace this text with your announcement and feel free to use <strong>bold</strong>, <em>emphasis</em>, links and other formatting tools.</p>
<ul style="margin:0 0 16px 20px;padding:0;color:#374151;font-size:15px;line-height:1.6;">
    <li>Use {{name}} or {{username}} to personalise copy.</li>
    <li>Insert call-to-action buttons and rich formatting.</li>
    <li>Remember to include clear benefits and next steps.</li>
</ul>
<p style="margin:0 0 16px;color:#374151;font-size:15px;line-height:1.6;">Cheers,<br>{{site_name}} Team</p>
HTML;
}

/**
 * Return the default plain text template.
 */
function email_default_content_text(): string
{
    return <<<TEXT
Hi {{name}},

Your update goes here. Replace this text with the plain text copy of your announcement. Keep sentences short and scannable. Use {{site_name}} to mention the brand and {{unsubscribe_url}} to reference the unsubscribe link if needed.

Cheers,
{{site_name}} Team
TEXT;
}

/**
 * Build the final HTML email body using the provided content fragment.
 *
 * @param string $contentHtml HTML fragment created by the editor.
 * @param string $preheader   Preheader text to embed as hidden preview copy.
 * @param array  $context     Merge tags available to templates.
 */
function email_wrap_html(string $contentHtml, string $preheader, array $context): string
{
    $content = email_merge_tags($contentHtml, $context, true);
    $preheaderText = htmlspecialchars($preheader !== '' ? $preheader : ($context['preheader'] ?? ''), ENT_QUOTES, 'UTF-8');
    $siteName = htmlspecialchars($context['site_name'] ?? SITE_NAME, ENT_QUOTES, 'UTF-8');
    $unsubscribeUrl = isset($context['unsubscribe_url']) && $context['unsubscribe_url'] !== ''
        ? htmlspecialchars($context['unsubscribe_url'], ENT_QUOTES, 'UTF-8')
        : '';

    $unsubscribeBlock = $unsubscribeUrl !== ''
        ? '<p style="margin:0;font-size:12px;color:#6b7280;">No longer wish to hear from us? <a href="' . $unsubscribeUrl . '" style="color:#6b7280;text-decoration:underline;">Unsubscribe instantly</a>.</p>'
        : '';

    $year = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{$siteName}</title>
    <style>
        @media (prefers-color-scheme: dark) {
            body, table, td { background-color: #0f172a !important; color: #e2e8f0 !important; }
            a { color: #38bdf8 !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#f5f7fa;font-family:Segoe UI,Helvetica,Arial,sans-serif;">
    <span style="display:none !important;visibility:hidden;mso-hide:all;font-size:1px;color:#f5f7fa;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">{$preheaderText}</span>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f5f7fa;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px;background-color:#ffffff;border-radius:18px;padding:40px 36px;box-shadow:0 25px 60px rgba(15,23,42,0.12);">
                    <tr>
                        <td style="padding-bottom:24px;text-align:left;">
                            <div style="font-size:18px;font-weight:700;color:#0f172a;">{$siteName}</div>
                            <div style="font-size:13px;color:#6b7280;">{$context['subject']}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size:15px;line-height:1.7;color:#111827;">
                            {$content}
                        </td>
                    </tr>
                </table>
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px;margin-top:16px;">
                    <tr>
                        <td style="text-align:center;color:#9ca3af;font-size:12px;line-height:1.6;">
                            <p style="margin:0 0 6px;">© {$year} {$siteName}. All rights reserved.</p>
                            {$unsubscribeBlock}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}

/**
 * Merge template tags for both HTML and plain text versions.
 *
 * @param string $template  Template body.
 * @param array  $context   Context key/value pairs.
 * @param bool   $allowHtml Whether HTML entities should be preserved.
 */
function email_merge_tags(string $template, array $context, bool $allowHtml = false): string
{
    $replacements = [];
    foreach ($context as $key => $value) {
        $placeholder = '{{' . $key . '}}';
        $replacements[$placeholder] = $allowHtml ? (string) $value : html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');
    }

    return strtr($template, $replacements);
}

/**
 * Render the final HTML and plain text bodies for a message.
 *
 * @param string $contentHtml HTML fragment provided by admin.
 * @param string $contentText Plain text template provided by admin.
 * @param array  $context     Context values available to templates.
 * @param string $preheader   Hidden preview text.
 *
 * @return array{0:string,1:string}
 */
function email_render_bodies(string $contentHtml, string $contentText, array $context, string $preheader = ''): array
{
    $html = email_wrap_html($contentHtml, $preheader, $context);

    $textTemplate = $contentText !== '' ? $contentText : strip_tags($contentHtml);
    $textTemplate = preg_replace('/<br\s*\/?>(\r?\n)?/i', "\n", $textTemplate);
    $textTemplate = html_entity_decode(strip_tags($textTemplate), ENT_QUOTES, 'UTF-8');

    $text = email_merge_tags($textTemplate, $context);

    $text = preg_replace("/\n{3,}/", "\n\n", trim($text));

    return [$html, $text];
}
