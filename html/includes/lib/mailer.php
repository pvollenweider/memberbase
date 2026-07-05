<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Minimal pure-PHP SMTP client.
 * Supports plain, STARTTLS, SSL/TLS, AUTH LOGIN, AUTH PLAIN, no-auth.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

/**
 * Send an email via SMTP.
 *
 * @param array  $cfg  Keys: host, port, encryption (none|starttls|ssl), auth (0|1),
 *                     username, password, from_email, from_name, reply_to.
 * @param string $to
 * @param string $subject
 * @param string $body   Plain-text body.
 * @return array{ok:bool,error:string}
 */
function mbSmtpSend(array $cfg, string $to, string $subject, string $body): array
{
    $host       = $cfg['smtp_host']       ?? '';
    $port       = (int)($cfg['smtp_port'] ?? 587);
    $encryption = $cfg['smtp_encryption'] ?? 'starttls'; // none | starttls | ssl
    $auth       = (bool)($cfg['smtp_auth'] ?? false);
    $user       = $cfg['smtp_user']       ?? '';
    $pass       = mbSmtpDecryptPassword($cfg['smtp_password'] ?? '', $cfg['smtp_enc_key'] ?? '');
    $fromEmail  = $cfg['smtp_from_email'] ?? '';
    $fromName   = $cfg['smtp_from_name']  ?? '';
    $replyTo    = $cfg['smtp_reply_to']   ?? '';

    if ($host === '') return ['ok' => false, 'error' => 'smtp_not_configured'];

    $timeout = 15;

    // Build socket address
    if ($encryption === 'ssl') {
        $address = 'ssl://' . $host . ':' . $port;
    } else {
        $address = 'tcp://' . $host . ':' . $port;
    }

    $errno  = 0;
    $errstr = '';
    $ctx = stream_context_create(['ssl' => [
        'verify_peer'       => false,
        'verify_peer_name'  => false,
    ]]);
    $sock = @stream_socket_client($address, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
    if ($sock === false) {
        return ['ok' => false, 'error' => "Connection failed: $errstr ($errno)"];
    }
    stream_set_timeout($sock, $timeout);

    $read = function() use ($sock): string {
        $buf = '';
        while ($line = fgets($sock, 512)) {
            $buf .= $line;
            // A line not starting with 3-digit code followed by '-' is the last line of a response
            if (strlen($line) >= 4 && $line[3] !== '-') break;
        }
        return $buf;
    };

    $cmd = function(string $c) use ($sock, $read): string {
        fwrite($sock, $c . "\r\n");
        return $read();
    };

    $code = function(string $resp): int {
        return (int)substr($resp, 0, 3);
    };

    try {
        // Server greeting
        $resp = $read();
        if ($code($resp) !== 220) throw new RuntimeException("Greeting: $resp");

        // EHLO
        $resp = $cmd('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        if ($code($resp) !== 250) throw new RuntimeException("EHLO: $resp");
        $ehlo = $resp;

        // STARTTLS upgrade
        if ($encryption === 'starttls') {
            if (strpos($ehlo, 'STARTTLS') === false) {
                throw new RuntimeException('Server does not support STARTTLS');
            }
            $resp = $cmd('STARTTLS');
            if ($code($resp) !== 220) throw new RuntimeException("STARTTLS: $resp");
            if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('TLS handshake failed');
            }
            // Re-EHLO after TLS
            $resp = $cmd('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            if ($code($resp) !== 250) throw new RuntimeException("EHLO post-TLS: $resp");
            $ehlo = $resp;
        }

        // AUTH
        if ($auth && $user !== '') {
            if (strpos($ehlo, 'AUTH') !== false && strpos($ehlo, 'LOGIN') !== false) {
                $resp = $cmd('AUTH LOGIN');
                if ($code($resp) !== 334) throw new RuntimeException("AUTH LOGIN: $resp");
                $resp = $cmd(base64_encode($user));
                if ($code($resp) !== 334) throw new RuntimeException("AUTH username: $resp");
                $resp = $cmd(base64_encode($pass));
                if ($code($resp) !== 235) throw new RuntimeException("AUTH password: $resp");
            } elseif (strpos($ehlo, 'PLAIN') !== false) {
                $plain = base64_encode("\0" . $user . "\0" . $pass);
                $resp = $cmd('AUTH PLAIN ' . $plain);
                if ($code($resp) !== 235) throw new RuntimeException("AUTH PLAIN: $resp");
            }
        }

        // Envelope
        $fromFull = $fromName !== '' ? '"' . addslashes($fromName) . '" <' . $fromEmail . '>' : $fromEmail;
        $resp = $cmd('MAIL FROM:<' . $fromEmail . '>');
        if ($code($resp) !== 250) throw new RuntimeException("MAIL FROM: $resp");

        $resp = $cmd('RCPT TO:<' . $to . '>');
        if ($code($resp) !== 250) throw new RuntimeException("RCPT TO: $resp");

        // Data
        $resp = $cmd('DATA');
        if ($code($resp) !== 354) throw new RuntimeException("DATA: $resp");

        $date    = date('r');
        $subjectEncoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $fromEncoded    = $fromName !== ''
            ? '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromEmail . '>'
            : $fromEmail;

        $headers  = "Date: $date\r\n";
        $headers .= "From: $fromEncoded\r\n";
        $headers .= "To: $to\r\n";
        if ($replyTo !== '') $headers .= "Reply-To: $replyTo\r\n";
        $headers .= "Subject: $subjectEncoded\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: base64\r\n";

        $bodyEncoded = chunk_split(base64_encode($body));

        // Dot-stuff: lines starting with '.' must be doubled
        $message = $headers . "\r\n" . $bodyEncoded;
        $message = preg_replace('/^\./', '..', $message);

        fwrite($sock, $message . "\r\n.\r\n");
        $resp = $read();
        if ($code($resp) !== 250) throw new RuntimeException("Message accepted: $resp");

        $cmd('QUIT');
        fclose($sock);

        return ['ok' => true, 'error' => ''];

    } catch (RuntimeException $e) {
        @fclose($sock);
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Encrypt SMTP password for storage in app_settings.
 */
function mbSmtpEncryptPassword(string $password, string $encKey): string
{
    if ($password === '' || $encKey === '') return '';
    $iv = random_bytes(16);
    $key = hash('sha256', $encKey, true);
    $encrypted = openssl_encrypt($password, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt SMTP password from app_settings.
 */
function mbSmtpDecryptPassword(string $encrypted, string $encKey): string
{
    if ($encrypted === '' || $encKey === '') return '';
    $data = base64_decode($encrypted);
    if (strlen($data) < 17) return '';
    $iv  = substr($data, 0, 16);
    $enc = substr($data, 16);
    $key = hash('sha256', $encKey, true);
    $result = openssl_decrypt($enc, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return $result === false ? '' : $result;
}

/**
 * Return or generate the per-installation SMTP encryption key stored in app_settings.
 */
function mbSmtpGetOrCreateEncKey(PDO $pdo): string
{
    $row = $pdo->query("SELECT value FROM app_settings WHERE `key`='smtp_enc_key' LIMIT 1")->fetchColumn();
    if ($row && strlen($row) >= 32) return $row;
    $key = bin2hex(random_bytes(32));
    $pdo->prepare("INSERT INTO app_settings (`key`,`value`) VALUES ('smtp_enc_key',?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)")->execute([$key]);
    return $key;
}

/**
 * High-level send helper: reads SMTP config from $appSettings, sends, logs result.
 *
 * Uses the global $appSettings array populated by bootstrap.php.
 * Falls back silently — never throws, never breaks the calling page.
 *
 * @param PDO    $pdo
 * @param string $to        Recipient email address.
 * @param string $subject
 * @param string $bodyHtml  HTML body (also used as plain-text fallback if $bodyText is empty).
 * @param string $bodyText  Optional plain-text body.
 * @return bool             True if the email was sent successfully.
 */
function mbSendMail(PDO $pdo, string $to, string $subject, string $bodyHtml, string $bodyText = ''): bool
{
    global $appSettings;
    try {
        $cfg = $appSettings;
        $cfg['smtp_enc_key'] = mbSmtpGetOrCreateEncKey($pdo);
        // Use plain text body for now (HTML support requires MIME multipart — future enhancement)
        $body   = $bodyText !== '' ? $bodyText : strip_tags($bodyHtml);
        $result = mbSmtpSend($cfg, $to, $subject, $body);
        $status = $result['ok'] ? 'sent' : 'error';
        $errMsg = $result['ok'] ? null : ($result['error'] ?? 'unknown error');
        _mbLogEmail($pdo, $to, $subject, $status, $errMsg);
        return $result['ok'];
    } catch (\Throwable $e) {
        _mbLogEmail($pdo, $to, $subject, 'error', $e->getMessage());
        return false;
    }
}

/**
 * Insert one row into email_log. Silently ignores failures (e.g. table not yet migrated).
 */
function _mbLogEmail(PDO $pdo, string $to, string $subject, string $status, ?string $errorMsg): void
{
    try {
        $pdo->prepare(
            "INSERT INTO email_log (to_email, subject, status, error_msg) VALUES (?, ?, ?, ?)"
        )->execute([$to, $subject, $status, $errorMsg]);
    } catch (\Throwable $e) {
        // Table may not exist yet (migration pending) — silently ignore
    }
}
