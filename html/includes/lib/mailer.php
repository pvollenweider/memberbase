<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Minimal pure-PHP SMTP client.
 * Supports plain, STARTTLS, SSL/TLS, AUTH LOGIN, AUTH PLAIN, no-auth.
 *
 * @copyright 2026 Philippe Vollenweider
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
 * @param bool   $bcc         When true, also envelope-RCPT to $cfg['smtp_reply_to'] (silent copy — no Bcc header).
 * @return array{ok:bool,error:string,debug:string}
 */
function mbSmtpSend(array $cfg, string $to, string $subject, string $bodyText, string $bodyHtml = '', array $attachments = [], bool $bcc = false): array
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

        if ($bcc && $replyTo !== '') {
            $resp = $cmd('RCPT TO:<' . $replyTo . '>');
            if ($code($resp) !== 250) throw new RuntimeException("RCPT TO (bcc): $resp");
        }

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
 * AES-256-GCM (authenticated): a tampered ciphertext decrypts to '' instead
 * of silently yielding garbage. Format: "gcm:" . base64(iv . tag . ciphertext).
 */
function mbSmtpEncryptPassword(string $password, string $encKey): string
{
    if ($password === '' || $encKey === '') return '';
    $iv  = random_bytes(12);
    $key = hash('sha256', $encKey, true);
    $tag = '';
    $encrypted = openssl_encrypt($password, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return 'gcm:' . base64_encode($iv . $tag . $encrypted);
}

/**
 * Decrypt SMTP password from app_settings.
 * Reads the current "gcm:" format; falls back to the legacy unauthenticated
 * AES-256-CBC format for values stored before the GCM switch (they are
 * re-encrypted the next time the SMTP settings are saved).
 */
function mbSmtpDecryptPassword(string $encrypted, string $encKey): string
{
    if ($encrypted === '' || $encKey === '') return '';
    $key = hash('sha256', $encKey, true);

    if (str_starts_with($encrypted, 'gcm:')) {
        $data = base64_decode(substr($encrypted, 4));
        if ($data === false || strlen($data) < 29) return ''; // 12 iv + 16 tag + >=1
        $iv  = substr($data, 0, 12);
        $tag = substr($data, 12, 16);
        $enc = substr($data, 28);
        $result = openssl_decrypt($enc, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $result === false ? '' : $result;
    }

    // Legacy AES-256-CBC value
    $data = base64_decode($encrypted);
    if ($data === false || strlen($data) < 17) return '';
    $iv  = substr($data, 0, 16);
    $enc = substr($data, 16);
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
        // {{formal_greeting}}      = gender-aware salutation ("Chère Madame X," / "Cher Monsieur X,"),
        //                            falls back to "Madame, Monsieur," when gender/name is unknown
        // {{year}}                 = attestation year
        // {{cotisation_note}}      = extra sentence about cotisations not being deductible,
        // {{cotisation_note_html}}   shown only if the member paid a cotisation that year (empty otherwise)
        'tpl_attestation_don' => [
            'subject'   => 'Attestation de don {{year}} — {{org_name}}',
            'body_text' => "{{formal_greeting_text}}\n\nNous tenons à vous remercier sincèrement pour le soutien que vous avez apporté à {{org_name}} tout au long de l'année {{year}}. Votre engagement fidèle représente un appui essentiel pour les personnes que nous accompagnons.\n\nGrâce à votre générosité, nos partenaires sur le terrain peuvent protéger et soutenir celles et ceux confrontés à des situations de grande vulnérabilité. Votre contribution leur offre un accompagnement de qualité et de réelles perspectives de réinsertion.\n\nVous trouverez ci-joint votre attestation fiscale, qui vous permettra de déduire vos dons de vos impôts.{{cotisation_note}}\n\nEn vous remerciant une nouvelle fois pour votre précieux engagement, nous vous adressons nos salutations les meilleures.\n\n{{org_name}}\n\nPour toute question : {{contact_email}}",
            'body_html' => $htmlWrap(
                '<p>{{formal_greeting}}</p>
<p>Nous tenons à vous remercier sincèrement pour le soutien que vous avez apporté à <strong>{{org_name}}</strong> tout au long de l\'année <strong>{{year}}</strong>. Votre engagement fidèle représente un appui essentiel pour les personnes que nous accompagnons.</p>
<p>Grâce à votre générosité, nos partenaires sur le terrain peuvent protéger et soutenir celles et ceux confrontés à des situations de grande vulnérabilité. Votre contribution leur offre un accompagnement de qualité et de réelles perspectives de réinsertion.</p>
<p>Vous trouverez ci-joint votre attestation fiscale, qui vous permettra de déduire vos dons de vos impôts.{{cotisation_note_html}}</p>
<p>En vous remerciant une nouvelle fois pour votre précieux engagement, nous vous adressons nos salutations les meilleures.</p>
<p style="margin-top:24px"><strong>{{org_name}}</strong></p>
<p>Pour toute question : <a href="mailto:{{contact_email}}" style="color:#1a5276">{{contact_email}}</a></p>', '{{org_name}}'),
        ],
        // {{entries}}      = plain-text block, one line per entry (date · type · description · amount)
        // {{entries_html}} = HTML <table> of entries (built by the action)
        // {{total}}        = formatted sum of included entries
        // {{send_date}}        = date of the batch send (DD mois YYYY)
        // {{since_line}}       = "en YYYY" or "depuis votre dernier récapitulatif du DD.MM.YYYY"
        // {{total_lines}}      = total line(s) for plain text (includes donation sub-total when mixed)
        // {{attest_note}}      = fiscal attestation sentence (empty when no attestable amounts)
        // {{attest_note_html}} = same, HTML version with styling
        'tpl_compta_recap' => [
            'subject'   => 'Confirmation de versements {{since_line}} — {{org_name}}',
            'body_text' => "{{greeting_text}}\n\nNous vous confirmons l'enregistrement des versements suivants{{display_name_line}} {{since_line}} :\n\n{{entries}}\n\n{{total_lines}}\n\n{{attest_note}}\nPour toute question ou pour nous signaler une erreur, écrivez-nous à : {{contact_email}}\n\nNous vous remercions chaleureusement pour votre soutien.\n\nCordialement,\n{{org_name}}",
            'body_html' => $htmlWrap(
                '<p>{{greeting}}</p>
<p>Nous vous confirmons l\'enregistrement des versements suivants{{display_name_line}} <em>{{since_line}}</em> :</p>
{{entries_html}}
{{attest_note_html}}
<p>Pour toute question ou pour nous signaler une erreur : <a href="mailto:{{contact_email}}" style="color:#1a5276">{{contact_email}}</a></p>
<p style="margin-top:24px">Nous vous remercions chaleureusement pour votre soutien.<br><br>Cordialement,<br><strong>{{org_name}}</strong></p>', '{{org_name}}'),
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
 * @param bool  $bcc         When true, also sends a silent copy to $appSettings['smtp_reply_to']
 */
function mbSendMailWithError(
    PDO $pdo,
    string $to,
    string $subject,
    string $bodyHtml,
    string $bodyText = '',
    ?int $userId = null,
    string $tplKey = '',
    array $attachments = [],
    bool $bcc = false
): bool|string {
    global $appSettings;
    try {
        $cfg  = $appSettings;
        $cfg['smtp_enc_key'] = mbSmtpGetOrCreateEncKey($pdo);
        $text   = $bodyText !== '' ? $bodyText : strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml));
        $result = mbSmtpSend($cfg, $to, $subject, $text, $bodyHtml, $attachments, $bcc);
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
 * @param bool  $bcc         When true, also sends a silent copy to $appSettings['smtp_reply_to']
 */
function mbSendTemplateWithAttachment(
    PDO $pdo,
    string $to,
    string $tplKey,
    array $vars,
    ?int $userId = null,
    array $attachments = [],
    bool $bcc = false
): bool|string {
    $tpl      = mbGetTemplate($pdo, $tplKey);
    $subject  = mbRenderTemplate($tpl->subject,   $vars);
    $bodyText = mbRenderTemplate($tpl->body_text, $vars);
    $bodyHtml = isset($tpl->body_html) ? mbRenderTemplate($tpl->body_html, $vars) : '';
    return mbSendMailWithError($pdo, $to, $subject, $bodyHtml !== '' ? $bodyHtml : $bodyText, $bodyText, $userId, $tplKey, $attachments, $bcc);
}
