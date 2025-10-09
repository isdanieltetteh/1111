<?php
/**
 * Lightweight SMTP mailer used across the application.
 * Provides a consistent interface for sending transactional
 * emails without depending on external libraries.
 */

class MailService
{
    private static ?MailService $instance = null;

    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $encryption;
    private int $timeout;
    private string $fromEmail;
    private string $fromName;
    private string $returnPath;
    private bool $allowSelfSigned;

    private function __construct()
    {
        $this->host = defined('SMTP_HOST') ? (string) SMTP_HOST : '';
        $this->port = defined('SMTP_PORT') ? (int) SMTP_PORT : 587;
        $this->username = defined('SMTP_USERNAME') ? (string) SMTP_USERNAME : '';
        $this->password = defined('SMTP_PASSWORD') ? (string) SMTP_PASSWORD : '';
        $this->encryption = defined('SMTP_ENCRYPTION') ? strtolower((string) SMTP_ENCRYPTION) : 'tls';
        $this->timeout = defined('SMTP_TIMEOUT') ? (int) SMTP_TIMEOUT : 30;
        $this->fromEmail = defined('SMTP_FROM_EMAIL') ? (string) SMTP_FROM_EMAIL : (defined('SITE_EMAIL') ? SITE_EMAIL : '');
        $this->fromName = defined('SMTP_FROM_NAME') ? (string) SMTP_FROM_NAME : (defined('SITE_NAME') ? SITE_NAME : '');
        $this->allowSelfSigned = defined('SMTP_ALLOW_SELF_SIGNED') ? (bool) SMTP_ALLOW_SELF_SIGNED : false;
        $this->returnPath = defined('SMTP_RETURN_PATH') && filter_var(SMTP_RETURN_PATH, FILTER_VALIDATE_EMAIL)
            ? (string) SMTP_RETURN_PATH
            : $this->fromEmail;
    }

    public static function getInstance(): MailService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Send an email message.
     *
     * @param string|array $recipients A single email address or an array of addresses
     * @param string $subject          Email subject line
     * @param string $htmlBody         HTML body of the message
     * @param array $options           Optional configuration:
     *                                - text: plain text version
     *                                - reply_to: ['email' => '', 'name' => '']
     *                                - cc: array of cc recipients
     *                                - bcc: array of bcc recipients
     * @return array{success:bool,message:string}
     */
    public function send($recipients, string $subject, string $htmlBody, array $options = []): array
    {
        $recipientList = $this->normaliseRecipients($recipients);
        if (empty($recipientList)) {
            return ['success' => false, 'message' => 'No valid recipients were provided'];
        }

        $subject = trim(preg_replace("/[\r\n]+/", ' ', $subject));
        $htmlBody = $this->normaliseLineEndings($htmlBody ?: '');

        $textBody = $options['text'] ?? $this->generateTextVersion($htmlBody);
        $textBody = $this->normaliseLineEndings($textBody);

        $ccRecipients = !empty($options['cc']) && is_array($options['cc'])
            ? $this->normaliseRecipients($options['cc'])
            : [];

        $bccRecipients = !empty($options['bcc']) && is_array($options['bcc'])
            ? $this->normaliseRecipients($options['bcc'])
            : [];

        $headers = $this->buildHeaders($recipientList, $ccRecipients, $bccRecipients, $subject, $options);
        $message = $this->buildMessageBody($textBody, $htmlBody, $headers['_boundary']);

        // Attempt SMTP delivery first
        if ($this->host) {
            try {
                $this->sendViaSmtp(array_merge($recipientList, $ccRecipients, $bccRecipients), $headers, $subject, $message);
                return ['success' => true, 'message' => 'Message sent via SMTP'];
            } catch (Exception $e) {
                $this->log('SMTP delivery failed: ' . $e->getMessage());
            }
        }

        // Fallback to PHP mail() if SMTP is unavailable
        if (function_exists('mail')) {
            $toHeader = implode(', ', array_column($recipientList, 'address'));
            $mailHeaders = $this->flattenHeaders($headers, "\r\n", false);

            $additionalParameters = '';
            if ($this->returnPath) {
                $additionalParameters = '-f' . $this->returnPath;
            }

            if (@mail($toHeader, $subject, $message, $mailHeaders, $additionalParameters)) {
                return ['success' => true, 'message' => 'Message sent via PHP mail()'];
            }

            $this->log('mail() fallback failed when attempting to deliver message.');
        }

        return ['success' => false, 'message' => 'Unable to send email'];
    }

    private function normaliseRecipients($recipients): array
    {
        $list = [];

        $recipients = is_array($recipients) ? $recipients : [$recipients];

        foreach ($recipients as $key => $value) {
            if (is_array($value) && isset($value['email'])) {
                $email = trim((string) $value['email']);
                $name = trim((string) ($value['name'] ?? ''));
            } elseif (is_string($key)) {
                $email = trim((string) $key);
                $name = trim((string) $value);
            } else {
                $email = trim((string) $value);
                $name = '';
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->log("Invalid email skipped: {$email}");
                continue;
            }

            $list[] = [
                'address' => $email,
                'name' => $name
            ];
        }

        return $list;
    }

    private function buildHeaders(array $recipients, array $ccRecipients, array $bccRecipients, string $subject, array $options): array
    {
        $headers = [];

        $headers['Date'] = date('r');
        $headers['Subject'] = $subject;
        $headers['From'] = $this->formatAddress($this->fromEmail, $this->fromName);
        $headers['To'] = implode(', ', array_map(fn ($recipient) => $this->formatAddress($recipient['address'], $recipient['name']), $recipients));
        $headers['MIME-Version'] = '1.0';

        $headers['Message-ID'] = $this->generateMessageId();
        $headers['X-Mailer'] = SITE_NAME . ' Mailer';
        if (!empty($_SERVER['SERVER_ADDR'])) {
            $headers['X-Originating-IP'] = '[' . $_SERVER['SERVER_ADDR'] . ']';
        }

        if (!empty($options['reply_to']) && is_array($options['reply_to'])) {
            $reply = $options['reply_to'];
            if (!empty($reply['email']) && filter_var($reply['email'], FILTER_VALIDATE_EMAIL)) {
                $headers['Reply-To'] = $this->formatAddress($reply['email'], $reply['name'] ?? '');
            }
        } elseif (!isset($headers['Reply-To'])) {
            $headers['Reply-To'] = $this->formatAddress($this->fromEmail, $this->fromName);
        }

        if ($ccRecipients) {
            $headers['Cc'] = implode(', ', array_map(fn ($recipient) => $this->formatAddress($recipient['address'], $recipient['name']), $ccRecipients));
        }

        if ($bccRecipients) {
            $headers['Bcc'] = implode(', ', array_map(fn ($recipient) => $this->formatAddress($recipient['address'], $recipient['name']), $bccRecipients));
        }

        $boundary = '=_Boundary_' . md5(uniqid((string) mt_rand(), true));
        $headers['Content-Type'] = 'multipart/alternative; boundary="' . $boundary . '"';
        $headers['_boundary'] = $boundary;

        if (!empty($options['list_unsubscribe'])) {
            $list = $options['list_unsubscribe'];
            $list = is_array($list) ? $list : [$list];
            $formatted = [];
            foreach ($list as $entry) {
                $entry = trim((string) $entry);
                if ($entry === '') {
                    continue;
                }

                $entry = preg_replace("/[\r\n]+/", ' ', $entry);
                if ($entry[0] !== '<') {
                    $entry = '<' . $entry . '>';
                }
                $formatted[] = $entry;
            }

            if ($formatted) {
                $headers['List-Unsubscribe'] = implode(', ', $formatted);
            }
        }

        if (!empty($options['list_unsubscribe_post'])) {
            $headers['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
        }

        if (!empty($options['custom_headers']) && is_array($options['custom_headers'])) {
            foreach ($options['custom_headers'] as $name => $value) {
                $name = trim((string) $name);
                if ($name === '') {
                    continue;
                }

                $headers[$name] = preg_replace("/[\r\n]+/", ' ', (string) $value);
            }
        }

        return $headers;
    }

    private function buildMessageBody(string $textBody, string $htmlBody, string $boundary): string
    {
        $parts = [];
        $parts[] = '--' . $boundary;
        $parts[] = 'Content-Type: text/plain; charset=UTF-8';
        $parts[] = 'Content-Transfer-Encoding: 8bit';
        $parts[] = '';
        $parts[] = $textBody;
        $parts[] = '';
        $parts[] = '--' . $boundary;
        $parts[] = 'Content-Type: text/html; charset=UTF-8';
        $parts[] = 'Content-Transfer-Encoding: 8bit';
        $parts[] = '';
        $parts[] = $htmlBody;
        $parts[] = '';
        $parts[] = '--' . $boundary . '--';
        $parts[] = '';

        return implode("\r\n", $parts);
    }

    private function sendViaSmtp(array $recipients, array $headers, string $subject, string $message): void
    {
        $contextOptions = [];
        if ($this->allowSelfSigned) {
            $contextOptions['ssl'] = [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ];
        }

        $transport = $this->encryption === 'ssl' ? 'ssl://' : '';
        $socket = @stream_socket_client(
            $transport . $this->host . ':' . $this->port,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $contextOptions ? stream_context_create($contextOptions) : null
        );

        if (!$socket) {
            throw new RuntimeException("Unable to connect to SMTP server: {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, $this->timeout);

        $this->expectSmtpResponse($socket, 220);
        $this->smtpCommand($socket, 'EHLO ' . $this->getServerName(), 250);

        if ($this->encryption === 'tls') {
            $this->smtpCommand($socket, 'STARTTLS', 220);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Unable to establish TLS encryption with SMTP server');
            }
            $this->smtpCommand($socket, 'EHLO ' . $this->getServerName(), 250);
        }

        if ($this->username && $this->password) {
            $this->smtpCommand($socket, 'AUTH LOGIN', 334);
            $this->smtpCommand($socket, base64_encode($this->username), 334);
            $this->smtpCommand($socket, base64_encode($this->password), 235);
        }

        $fromAddress = $this->returnPath ?: $this->extractEmailAddress($headers['From']);
        $this->smtpCommand($socket, 'MAIL FROM: <' . $fromAddress . '>', 250);

        foreach ($recipients as $recipient) {
            $this->smtpCommand($socket, 'RCPT TO: <' . $recipient['address'] . '>', [250, 251]);
        }

        $this->smtpCommand($socket, 'DATA', 354);

        $dataHeaders = $headers;
        $boundary = $dataHeaders['_boundary'] ?? null;
        unset($dataHeaders['_boundary']);

        $dataHeaders['Subject'] = $subject;

        $headerLines = $this->flattenHeaders($dataHeaders, "\r\n", false);
        $payload = $headerLines . "\r\n\r\n";

        $payload .= $message;

        $payload = preg_replace("/^\./m", '..', $payload ?? '');
        $payload .= "\r\n.\r\n";

        $this->writeToSocket($socket, $payload);
        $this->expectSmtpResponse($socket, 250);
        $this->smtpCommand($socket, 'QUIT', 221);
        fclose($socket);
    }

    private function flattenHeaders(array $headers, string $separator = "\r\n", bool $includeSubject = true): string
    {
        $lines = [];

        foreach ($headers as $name => $value) {
            if ($name === '_boundary') {
                continue;
            }

            if (!$includeSubject && strtolower($name) === 'subject') {
                continue;
            }

            $lines[] = $name . ': ' . $value;
        }

        return implode($separator, $lines);
    }

    private function generateTextVersion(string $html): string
    {
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $stripped = strip_tags($decoded);
        $stripped = preg_replace("/[ \t]+/", ' ', $stripped);
        $stripped = preg_replace("/[\r\n]{3,}/", "\n\n", $stripped);

        return trim($stripped);
    }

    private function formatAddress(string $email, string $name = ''): string
    {
        if ($name === '') {
            return $email;
        }

        $sanitisedName = trim(preg_replace("/[\r\n]+/", ' ', $name));
        $sanitisedName = addcslashes($sanitisedName, '"');

        return sprintf('"%s" <%s>', $sanitisedName, $email);
    }

    private function normaliseLineEndings(string $text): string
    {
        return preg_replace("/(\r\n|\r|\n)/", "\r\n", $text);
    }

    private function smtpCommand($socket, string $command, $expectedCodes): void
    {
        $this->writeToSocket($socket, $command . "\r\n");
        $this->expectSmtpResponse($socket, $expectedCodes);
    }

    private function expectSmtpResponse($socket, $expectedCodes): void
    {
        $expected = (array) $expectedCodes;
        $response = $this->readFromSocket($socket);

        if (!$response) {
            throw new RuntimeException('Empty response from SMTP server');
        }

        $code = (int) substr($response, 0, 3);

        if (!in_array($code, $expected, true)) {
            throw new RuntimeException('Unexpected SMTP response: ' . trim($response));
        }
    }

    private function readFromSocket($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        return $response;
    }

    private function writeToSocket($socket, string $data): void
    {
        $result = fwrite($socket, $data);
        if ($result === false) {
            throw new RuntimeException('Failed to write to SMTP socket');
        }
    }

    private function extractEmailAddress(string $formatted): string
    {
        if (preg_match('/<(.*)>/', $formatted, $matches)) {
            return trim($matches[1]);
        }

        return trim($formatted);
    }

    private function getServerName(): string
    {
        if (!empty($_SERVER['SERVER_NAME'])) {
            return $_SERVER['SERVER_NAME'];
        }

        return 'localhost';
    }

    private function getMailDomain(): string
    {
        if ($this->fromEmail && strpos($this->fromEmail, '@') !== false) {
            return substr($this->fromEmail, strrpos($this->fromEmail, '@') + 1);
        }

        return $this->getServerName();
    }

    private function generateMessageId(): string
    {
        return sprintf('<%s@%s>', bin2hex(random_bytes(16)), $this->getMailDomain());
    }

    private function log(string $message): void
    {
        error_log('[MailService] ' . $message);
    }
}

