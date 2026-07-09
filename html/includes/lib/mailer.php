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
 * Send an email via SMTP, with optional HTML body (multipart/alternative).
 *
 * @param array  $cfg      Keys: smtp_host, smtp_port, smtp_encryption, smtp_auth,
 *                         smtp_user, smtp_password, smtp_enc_key, smtp_from_email,
 *                         smtp_from_name, smtp_reply_to.
 * @param string $to
 * @param string $subject
 * @param string $bodyText    Plain-text body.
 * @param string $bodyHtml    Optional HTML body; when provided, sends multipart/alternative.
 * @param array  $attachments Optional list of attachments. Each entry: ['name' => string, 'mime' => string, 'data' => string (raw bytes)].
 * @return array{ok:bool,error:string,debug:string}
 */
function mbSmtpSend(array $cfg, string $to, string $subject, string $bodyText, string $bodyHtml = '', array $attachments = []): array
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

    $log = [];
    $log[] = "Config: host=$host port=$port encryption=$encryption auth=" . ($auth ? 'yes' : 'no');
    if ($auth) $log[] = "Auth user: $user";

    if ($host === '') return ['ok' => false, 'error' => 'smtp_not_configured', 'debug' => implode("\n", $log)];

    $timeout = 15;

    if ($encryption === 'ssl') {
        $address = 'ssl://' . $host . ':' . $port;
    } else {
        $address = 'tcp://' . $host . ':' . $port;
    }

    $log[] = "Connecting to $address ...";

    $errno  = 0;
    $errstr = '';
    $ctx = stream_context_create(['ssl' => [
        'verify_peer'       => false,
        'verify_peer_name'  => false,
        'allow_self_signed' => true,
    ]]);
    error_clear_last();
    $sock = @stream_socket_client($address, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
    if ($sock === false) {
        // $errstr is often empty on SSL errors — fall back to error_get_last()
        if ($errstr === '' || $errstr === '0') {
            $last = error_get_last();
            $errstr = $last['message'] ?? 'unknown error';
        }
        $msg = "Connection failed: $errstr" . ($errno ? " ($errno)" : '');
        $log[] = $msg;
        return ['ok' => false, 'error' => $msg, 'debug' => implode("\n", $log)];
    }
    $log[] = "Connected.";
    stream_set_timeout($sock, $timeout);

    $read = function() use ($sock, &$log): string {
        $buf = '';
        while ($line = fgets($sock, 512)) {
            $buf .= $line;
            $log[] = '< ' . rtrim($line);
            if (strlen($line) >= 4 && $line[3] !== '-') break;
        }
        return $buf;
    };

    $cmd = function(string $c, string $display = '') use ($sock, $read, &$log): string {
        // Log the command but mask base64 credentials
        $log[] = '> ' . ($display !== '' ? $display : $c);
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
        $ehloHost = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $resp = $cmd('EHLO ' . $ehloHost);
        if ($code($resp) !== 250) throw new RuntimeException("EHLO: $resp");
        $ehlo = $resp;

        // STARTTLS upgrade
        if ($encryption === 'starttls') {
            if (strpos($ehlo, 'STARTTLS') === false) {
                throw new RuntimeException('Server does not support STARTTLS');
            }
            $resp = $cmd('STARTTLS');
            if ($code($resp) !== 220) throw new RuntimeException("STARTTLS: $resp");
            $log[] = '(TLS handshake...)';
            if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('TLS handshake failed');
            }
            $log[] = 'TLS established.';
            // Re-EHLO after TLS
            $resp = $cmd('EHLO ' . $ehloHost);
            if ($code($resp) !== 250) throw new RuntimeException("EHLO post-TLS: $resp");
            $ehlo = $resp;
        }

        // AUTH
        if ($auth && $user !== '') {
            if (strpos($ehlo, 'AUTH') !== false && strpos($ehlo, 'LOGIN') !== false) {
                $resp = $cmd('AUTH LOGIN');
                if ($code($resp) !== 334) throw new RuntimeException("AUTH LOGIN: $resp");
                $resp = $cmd(base64_encode($user), 'AUTH username: [base64]');
                if ($code($resp) !== 334) throw new RuntimeException("AUTH username: $resp");
                $resp = $cmd(base64_encode($pass), 'AUTH password: [base64]');
                if ($code($resp) !== 235) throw new RuntimeException("AUTH password: $resp");
            } elseif (strpos($ehlo, 'PLAIN') !== false) {
                $plain = base64_encode("\0" . $user . "\0" . $pass);
                $resp = $cmd('AUTH PLAIN ' . $plain, 'AUTH PLAIN [base64]');
                if ($code($resp) !== 235) throw new RuntimeException("AUTH PLAIN: $resp");
            }
        }

        // Envelope
        $resp = $cmd('MAIL FROM:<' . $fromEmail . '>');
        if ($code($resp) !== 250) throw new RuntimeException("MAIL FROM: $resp");

        $resp = $cmd('RCPT TO:<' . $to . '>');
        if ($code($resp) !== 250) throw new RuntimeException("RCPT TO: $resp");

        // Data
        $resp = $cmd('DATA');
        if ($code($resp) !== 354) throw new RuntimeException("DATA: $resp");

        $date           = date('r');
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

        if (!empty($attachments)) {
            // multipart/mixed wraps the body + attachments
            $outerBoundary = '----=_Mixed_' . md5(uniqid('', true));
            $headers .= "Content-Type: multipart/mixed; boundary=\"$outerBoundary\"\r\n";
            $bodyPart = "--$outerBoundary\r\n";

            if ($bodyHtml !== '') {
                // Inner multipart/alternative for text + HTML
                $altBoundary  = '----=_Alt_' . md5(uniqid('', true));
                $bodyPart .= "Content-Type: multipart/alternative; boundary=\"$altBoundary\"\r\n\r\n";
                $bodyPart .= "--$altBoundary\r\n";
                $bodyPart .= "Content-Type: text/plain; charset=UTF-8\r\n";
                $bodyPart .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $bodyPart .= chunk_split(base64_encode($bodyText));
                $bodyPart .= "--$altBoundary\r\n";
                $bodyPart .= "Content-Type: text/html; charset=UTF-8\r\n";
                $bodyPart .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $bodyPart .= chunk_split(base64_encode($bodyHtml));
                $bodyPart .= "--$altBoundary--\r\n";
            } else {
                $bodyPart .= "Content-Type: text/plain; charset=UTF-8\r\n";
                $bodyPart .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $bodyPart .= chunk_split(base64_encode($bodyText));
            }

            foreach ($attachments as $att) {
                $safeName = preg_replace('/[^\w.\-]/', '_', $att['name'] ?? 'attachment');
                $bodyPart .= "--$outerBoundary\r\n";
                $bodyPart .= "Content-Type: " . ($att['mime'] ?? 'application/octet-stream') . "; name=\"$safeName\"\r\n";
                $bodyPart .= "Content-Disposition: attachment; filename=\"$safeName\"\r\n";
                $bodyPart .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $bodyPart .= chunk_split(base64_encode($att['data']));
            }
            $bodyPart .= "--$outerBoundary--\r\n";
            $message = $headers . "\r\n" . $bodyPart;

        } elseif ($bodyHtml !== '') {
            $boundary = '----=_Part_' . md5(uniqid('', true));
            $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
            $bodyPart  = "--$boundary\r\n";
            $bodyPart .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $bodyPart .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $bodyPart .= chunk_split(base64_encode($bodyText));
            $bodyPart .= "--$boundary\r\n";
            $bodyPart .= "Content-Type: text/html; charset=UTF-8\r\n";
            $bodyPart .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $bodyPart .= chunk_split(base64_encode($bodyHtml));
            $bodyPart .= "--$boundary--\r\n";
            $message = $headers . "\r\n" . $bodyPart;
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $headers .= "Content-Transfer-Encoding: base64\r\n";
            $message = $headers . "\r\n" . chunk_split(base64_encode($bodyText));
        }

        // Dot-stuff: lines starting with '.' must be doubled
        $message = preg_replace('/^\./', '..', $message);

        $log[] = '> [message headers + body]';
        fwrite($sock, $message . "\r\n.\r\n");
        $resp = $read();
        if ($code($resp) !== 250) throw new RuntimeException("Message accepted: $resp");

        $cmd('QUIT');
        fclose($sock);

        return ['ok' => true, 'error' => '', 'debug' => implode("\n", $log)];

    } catch (RuntimeException $e) {
        @fclose($sock);
        return ['ok' => false, 'error' => $e->getMessage(), 'debug' => implode("\n", $log)];
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
function mbSendMail(
    PDO $pdo,
    string $to,
    string $subject,
    string $bodyHtml,
    string $bodyText = '',
    ?int $userId = null
): bool {
    global $appSettings;
    try {
        $cfg  = $appSettings;
        $cfg['smtp_enc_key'] = mbSmtpGetOrCreateEncKey($pdo);
        $text   = $bodyText !== '' ? $bodyText : strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml));
        $result = mbSmtpSend($cfg, $to, $subject, $text, $bodyHtml);
        $status = $result['ok'] ? 'sent' : 'error';
        $errMsg = $result['ok'] ? null : ($result['error'] ?? 'unknown error');
        _mbLogEmail($pdo, $to, $subject, $status, $errMsg, $userId, $text, $bodyHtml);
        return $result['ok'];
    } catch (\Throwable $e) {
        _mbLogEmail($pdo, $to, $subject, 'error', $e->getMessage(), $userId);
        return false;
    }
}

/**
 * Insert one row into email_log. Silently ignores failures (e.g. table not yet migrated).
 *
 * @param ?int    $userId   Optional member id (links the log entry to the member's suivi)
 * @param string  $bodyText Rendered plain-text body
 * @param string  $bodyHtml Rendered HTML body (may be empty)
 */
function _mbLogEmail(
    PDO $pdo,
    string $to,
    string $subject,
    string $status,
    ?string $errorMsg,
    ?int $userId = null,
    string $bodyText = '',
    string $bodyHtml = '',
    string $tplKey = ''
): void {
    try {
        $pdo->prepare(
            "INSERT INTO email_log (user_id, tpl_key, to_email, subject, status, error_msg, body_text, body_html)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([$userId, $tplKey, $to, $subject, $status, $errorMsg, $bodyText, $bodyHtml]);
    } catch (\Throwable $e) {
        // Columns may not exist yet (migration pending) — retry without new columns
        try {
            $pdo->prepare(
                "INSERT INTO email_log (to_email, subject, status, error_msg) VALUES (?, ?, ?, ?)"
            )->execute([$to, $subject, $status, $errorMsg]);
        } catch (\Throwable $e2) {}
    }
}

/**
 * Built-in fallback templates used when the DB table is empty or not yet migrated.
 * Keys match email_templates.key values.
 */
function mbDefaultTemplates(): array
{
    // Shared inline CSS for HTML emails (table-based, safe for Outlook/Gmail/Apple Mail)
    $htmlWrap = static function(string $bodyContent, string $orgName): string {
        return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . htmlspecialchars($orgName, ENT_QUOTES, 'UTF-8') . '</title></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Helvetica,Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:24px 0">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:6px;overflow:hidden;max-width:600px;width:100%">
  <!-- Header -->
  <tr><td style="background:#1a5276;padding:24px 32px">
    <p style="margin:0;color:#ffffff;font-size:20px;font-weight:bold">' . htmlspecialchars($orgName, ENT_QUOTES, 'UTF-8') . '</p>
  </td></tr>
  <!-- Body -->
  <tr><td style="padding:32px 32px 24px;color:#222222;font-size:15px;line-height:1.6">
' . $bodyContent . '
  </td></tr>
  <!-- Footer -->
  <tr><td style="background:#f0f0f0;padding:16px 32px;color:#888888;font-size:12px;border-top:1px solid #e0e0e0">
    ' . htmlspecialchars($orgName, ENT_QUOTES, 'UTF-8') . ' · Ce message vous a été envoyé automatiquement, merci de ne pas y répondre directement.
  </td></tr>
</table>
</td></tr>
</table>
</body></html>';
    };

    return [
        // {{type}}       = label of the compta_type (e.g. "Don libre")
        // {{amount}}     = formatted amount (e.g. "80.00")
        // {{entry_date}} = date of the entry (DD.MM.YYYY)
        // {{libele}}     = free-text note on the entry (may be empty)
        'tpl_payment_receipt' => [
            'subject'   => 'Confirmation de réception — {{org_name}}',
            'body_text' => "{{greeting_text}}\n\nNous avons bien reçu votre versement{{society_line}} et vous en remercions.\n\n  Type    : {{type}}\n  Montant : CHF {{amount}}\n  Date    : {{entry_date}}\n{{libele_line}}\nPour toute question : {{contact_email}}\n\nCordialement,\n{{org_name}}",
            'body_html' => $htmlWrap(
                '<p>{{greeting}}</p>
<p>Nous avons bien reçu votre versement{{society_line}} et vous en remercions chaleureusement.</p>
<table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;margin:16px 0;font-size:14px">
  <tr style="background:#f0f4f8"><td style="border:1px solid #dde3ea;width:40%"><strong>Type</strong></td><td style="border:1px solid #dde3ea">{{type}}</td></tr>
  <tr><td style="border:1px solid #dde3ea"><strong>Montant</strong></td><td style="border:1px solid #dde3ea">CHF {{amount}}</td></tr>
  <tr style="background:#f0f4f8"><td style="border:1px solid #dde3ea"><strong>Date</strong></td><td style="border:1px solid #dde3ea">{{entry_date}}</td></tr>
  {{libele_row}}
</table>
<p>Pour toute question : <a href="mailto:{{contact_email}}" style="color:#1a5276">{{contact_email}}</a></p>
<p style="margin-top:24px">Cordialement,<br><strong>{{org_name}}</strong></p>', '{{org_name}}'),
        ],
        // {{year}}           = cotisation year (e.g. "2026")
        // {{membership_url}} = URL of the membership page (from app settings)
        'tpl_cotisation_reminder' => [
            'subject'   => 'Rappel de cotisation',
            'body_text' => "{{greeting_text}}\n\nSauf erreur de notre part, votre cotisation à {{org_name}} pour l'année {{year}} n'a pas encore été réglée.\n\nEn tant que membre, votre cotisation annuelle est essentielle : elle permet à notre association de mener à bien ses activités en faveur des enfants en situation de vulnérabilité en Amérique latine — recherche de fonds, suivi de projets sur le terrain, sensibilisation du public et représentation auprès des organisations internationales.\n\nSi vous avez déjà renouvelé votre adhésion, merci — vous pouvez ignorer ce message. Dans le cas contraire, nous comptons sur votre soutien et vous invitons à renouveler votre adhésion quand vous le pouvez.\n\nPour plus d'informations sur l'adhésion : {{membership_url}}\n{{payment_info_text}}\nPour toute question : {{contact_email}}\n\nCordialement,\n{{org_name}}",
            'body_html' => $htmlWrap(
                '<p>{{greeting}}</p>
<p>Sauf erreur de notre part, votre cotisation à <strong>{{org_name}}</strong> pour l\'année <strong>{{year}}</strong> n\'a pas encore été réglée.</p>
<p>En tant que membre, votre cotisation annuelle est essentielle : elle permet à notre association de mener à bien ses activités en faveur des enfants en situation de vulnérabilité en Amérique latine :</p>
<ul style="margin:8px 0 12px 0;padding-left:20px;line-height:1.7">
  <li>Recherche de fonds pour soutenir les projets sur le terrain</li>
  <li>Suivi des projets et reddition de comptes à nos bailleurs</li>
  <li>Missions d\'évaluation sur le terrain</li>
  <li>Sensibilisation du public et des étudiant·e·s</li>
  <li>Représentation auprès des organisations internationales</li>
  <li>Recrutement de volontaires</li>
</ul>
<p>Si vous avez déjà renouvelé votre adhésion, merci — vous pouvez ignorer ce message. Dans le cas contraire, nous comptons sur votre soutien et vous invitons à renouveler votre adhésion quand vous le pouvez.</p>
{{membership_url_block}}
{{payment_info_block}}
<p>Pour toute question : <a href="mailto:{{contact_email}}" style="color:#1a5276">{{contact_email}}</a></p>
<p style="margin-top:24px">Cordialement,<br><strong>{{org_name}}</strong></p>', '{{org_name}}'),
        ],
        'tpl_attestation_don' => [
            'subject'   => 'Attestation de don',
            'body_text' => "{{greeting_text}}\n\nVeuillez trouver ci-joint votre attestation de don.\n\nCordialement,\n{{org_name}}",
            'body_html' => $htmlWrap(
                '<p>{{greeting}}</p>
<p>Veuillez trouver ci-joint votre attestation de don pour l\'année fiscale écoulée.</p>
<p>Pour toute question : <a href="mailto:{{contact_email}}" style="color:#1a5276">{{contact_email}}</a></p>
<p style="margin-top:24px">Cordialement,<br><strong>{{org_name}}</strong></p>', '{{org_name}}'),
        ],
        // {{entries}}      = plain-text block, one line per entry (date · type · description · amount)
        // {{entries_html}} = HTML <table> of entries (built by the action)
        // {{total}}        = formatted sum of included entries
        // {{send_date}}    = date of the batch send (DD mois YYYY)
        // {{since_line}}   = "depuis votre dernier récapitulatif du DD.MM.YYYY" or "depuis votre adhésion"
        'tpl_compta_recap' => [
            'subject'   => 'Récapitulatif de vos versements — {{org_name}}',
            'body_text' => "{{greeting_text}}\n\nVoici le récapitulatif de vos versements enregistrés{{display_name_line}} {{since_line}} :\n\n{{entries}}\nTotal : CHF {{total}}\n\nUne attestation de don vous sera envoyée en début d'année prochaine pour votre déclaration fiscale.\n\nPour toute question, n'hésitez pas à nous contacter : {{contact_email}}\n\nCordialement,\n{{org_name}}",
            'body_html' => $htmlWrap(
                '<p>{{greeting}}</p>
<p>Voici le récapitulatif de vos versements enregistrés{{display_name_line}} <em>{{since_line}}</em> :</p>
{{entries_html}}
<table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;margin-top:0;font-size:14px">
  <tr style="background:#1a5276;color:#ffffff">
    <td colspan="3" style="border:1px solid #154360;text-align:right"><strong>Total : CHF {{total}}</strong></td>
  </tr>
</table>
<p style="margin-top:20px;padding:14px 16px;background:#eaf4fb;border-left:4px solid #1a5276;font-size:14px;color:#1a5276">
  <strong>Attestation de don :</strong> Un document officiel vous sera envoyé en début d\'année prochaine pour votre déclaration fiscale cantonale et fédérale.
</p>
<p>Pour toute question : <a href="mailto:{{contact_email}}" style="color:#1a5276">{{contact_email}}</a></p>
<p style="margin-top:24px">Cordialement,<br><strong>{{org_name}}</strong></p>', '{{org_name}}'),
        ],
    ];
}

/**
 * Load an email template from the DB, falling back to the built-in default.
 *
 * @param PDO    $pdo
 * @param string $key  Template key (e.g. 'tpl_welcome')
 * @return object      Object with ->subject and ->body_text properties
 */
function mbGetTemplate(PDO $pdo, string $key): object
{
    try {
        $row = $pdo->prepare("SELECT subject, body_text, body_html FROM email_templates WHERE `key`=? LIMIT 1");
        $row->execute([$key]);
        $tpl = $row->fetchObject();
        if ($tpl && $tpl->body_text !== '') {
            // If body_html column doesn't exist yet, set empty string
            if (!isset($tpl->body_html)) $tpl->body_html = '';
            return $tpl;
        }
    } catch (\Throwable $e) {
        // Table may not exist yet or body_html column not migrated
    }
    $defaults = mbDefaultTemplates();
    if (isset($defaults[$key])) {
        return (object)$defaults[$key];
    }
    return (object)['subject' => '', 'body_text' => '', 'body_html' => ''];
}

// mbBuildSalutation() and mbRenderTemplate() live in pure.php (no side effects, unit-testable).
// pure.php is loaded by bootstrap.php before mailer.php is ever required.

/**
 * Load a template, render it with $vars, and send via mbSendMail.
 *
 * @param PDO    $pdo
 * @param string $to      Recipient email address
 * @param string $tplKey  Template key (e.g. 'tpl_welcome')
 * @param array  $vars    Placeholder values
 * @return bool
 */
function mbSendTemplate(PDO $pdo, string $to, string $tplKey, array $vars, ?int $userId = null): bool|string
{
    $tpl      = mbGetTemplate($pdo, $tplKey);
    $subject  = mbRenderTemplate($tpl->subject,   $vars);
    $bodyText = mbRenderTemplate($tpl->body_text, $vars);
    $bodyHtml = isset($tpl->body_html) ? mbRenderTemplate($tpl->body_html, $vars) : '';
    return mbSendMailWithError($pdo, $to, $subject, $bodyHtml !== '' ? $bodyHtml : $bodyText, $bodyText, $userId, $tplKey);
}

/**
 * Like mbSendMail but returns true on success or an error string on failure.
 *
 * @param array $attachments Optional — same format as mbSmtpSend $attachments
 */
function mbSendMailWithError(
    PDO $pdo,
    string $to,
    string $subject,
    string $bodyHtml,
    string $bodyText = '',
    ?int $userId = null,
    string $tplKey = '',
    array $attachments = []
): bool|string {
    global $appSettings;
    try {
        $cfg  = $appSettings;
        $cfg['smtp_enc_key'] = mbSmtpGetOrCreateEncKey($pdo);
        $text   = $bodyText !== '' ? $bodyText : strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml));
        $result = mbSmtpSend($cfg, $to, $subject, $text, $bodyHtml, $attachments);
        $status = $result['ok'] ? 'sent' : 'error';
        $errMsg = $result['ok'] ? null : ($result['error'] ?? 'unknown error');
        _mbLogEmail($pdo, $to, $subject, $status, $errMsg, $userId, $text, $bodyHtml, $tplKey);
        return $result['ok'] ? true : ($errMsg ?? 'send_failed');
    } catch (\Throwable $e) {
        _mbLogEmail($pdo, $to, $subject, 'error', $e->getMessage(), $userId, '', '', $tplKey);
        return $e->getMessage();
    }
}

/**
 * Like mbSendTemplate but attaches an optional list of files.
 *
 * @param array $attachments Same format as mbSmtpSend $attachments
 */
function mbSendTemplateWithAttachment(
    PDO $pdo,
    string $to,
    string $tplKey,
    array $vars,
    ?int $userId = null,
    array $attachments = []
): bool|string {
    $tpl      = mbGetTemplate($pdo, $tplKey);
    $subject  = mbRenderTemplate($tpl->subject,   $vars);
    $bodyText = mbRenderTemplate($tpl->body_text, $vars);
    $bodyHtml = isset($tpl->body_html) ? mbRenderTemplate($tpl->body_html, $vars) : '';
    return mbSendMailWithError($pdo, $to, $subject, $bodyHtml !== '' ? $bodyHtml : $bodyText, $bodyText, $userId, $tplKey, $attachments);
}
