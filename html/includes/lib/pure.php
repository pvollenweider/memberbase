<?php
/**
 * Pure helper functions -- no side effects, no I/O, no globals.
 *
 * Extracted from bootstrap.php so they can be unit-tested in isolation
 * (PHPUnit, see tests/unit/) without opening a database connection.
 * Intentionally NOT guarded by APP_ENTRY: it defines functions only and
 * emits nothing, so it is safe to include from anywhere (app or tests).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

/** Parses a d/m/Y date string (as used in form inputs) to a Unix timestamp. */
function formatedDateToTimeStamp(?string $formatedDate): int
{
    if ($formatedDate) {
        $d = DateTime::createFromFormat('d/m/Y', $formatedDate);
        // Reject out-of-range dates (e.g. 32/13/2025) that createFromFormat silently rolls over
        $errors = DateTime::getLastErrors();
        if (!$d || ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return 0;
        }
        return $d->getTimestamp();
    }
    return 0;
}

/** Formats a Unix timestamp to a d/m/Y display string for form inputs and tables. */
function timeStampToformatedDate(?int $timestamp): string
{
    return $timestamp ? date("d/m/Y", $timestamp) : "";
}

/** Replaces typographic apostrophes (’) with straight apostrophes from user input. */
function unquote(string $s): string
{
    return str_replace("\u{2019}", "'", $s);
}

/**
 * Build greeting/display-name template variables for a contact.
 *
 * Returns: display_name, society, greeting (HTML), greeting_text (plain).
 */
function mbBuildSalutation(string $firstname, string $lastname, string $society): array
{
    $fn          = trim($firstname);
    $ln          = trim($lastname);
    $soc         = trim($society);
    $personName  = trim("$fn $ln");
    $displayName = $personName !== '' ? $personName : $soc;

    // Greeting uses person name only -- no society fallback (avoids "Bonjour Entreprise SA,")
    $greeting     = $personName !== ''
        ? 'Bonjour <strong>' . htmlspecialchars($personName, ENT_QUOTES, 'UTF-8') . '</strong>,'
        : 'Bonjour,';
    $greetingText = $personName !== '' ? "Bonjour $personName," : 'Bonjour,';

    return [
        'display_name'  => $displayName,
        'society'       => $soc,
        'greeting'      => $greeting,
        'greeting_text' => $greetingText,
    ];
}

/**
 * Replace {{placeholder}} tokens in a template string.
 */
function mbRenderTemplate(string $tpl, array $vars): string
{
    foreach ($vars as $k => $v) {
        $tpl = str_replace('{{' . $k . '}}', (string)$v, $tpl);
    }
    return $tpl;
}

/**
 * Build email template variables for a cotisation reminder.
 * Pure -- no DB, no I/O.
 */
function mbBuildCotiReminderVars(object $m, int $year, array $appSettings): array
{
    $contactEmail    = $appSettings['smtp_reply_to'] ?? ($appSettings['smtp_from_email'] ?? '');
    $membershipUrl   = $appSettings['membership_url'] ?? '';
    $membershipBlock = $membershipUrl !== ''
        ? '<p style="margin:16px 0"><a href="' . htmlspecialchars($membershipUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#1a5276">'
          . htmlspecialchars($membershipUrl, ENT_QUOTES, 'UTF-8') . '</a></p>'
        : '';

    $iban    = trim($appSettings['org_iban'] ?? '');
    $orgName = $appSettings['org_name'] ?? '';

    // Payment info block injected into the reminder when an IBAN is configured
    if ($iban !== '') {
        // Format IBAN with spaces for human readability (groups of 4)
        $ibanDisplay = implode(' ', str_split(strtoupper(str_replace(' ', '', $iban)), 4));

        $amountDesc = trim($appSettings['org_coti_amount_desc'] ?? '')
            ?: 'min. CHF 50.- / pers. · CHF 80.- / famille · CHF 20.- étudiant·e·s, AVS, chômeur·euse·s';

        $paymentInfoText = "\n---\nVersement bancaire :\n"
            . "  Bénéficiaire : $orgName\n"
            . "  IBAN         : $ibanDisplay\n"
            . "  Montant      : $amountDesc\n"
            . "  Communication: Cotisation $year\n"
            . "\nLe bulletin de versement QR est joint à ce message.\n---";

        $paymentInfoBlock = '<hr style="border:none;border-top:1px solid #e0e0e0;margin:24px 0">'
            . '<table cellpadding="0" cellspacing="0" width="100%" style="font-size:14px;line-height:1.7">'
            . '<tr><td colspan="2" style="padding-bottom:8px;font-weight:bold;color:#1a5276">Bulletin de versement QR ci-joint</td></tr>'
            . '<tr><td style="width:160px;color:#555">Bénéficiaire</td><td>' . htmlspecialchars($orgName, ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '<tr><td style="color:#555">IBAN</td><td><code>' . htmlspecialchars($ibanDisplay, ENT_QUOTES, 'UTF-8') . '</code></td></tr>'
            . '<tr><td style="color:#555">Montant</td><td>' . htmlspecialchars($amountDesc, ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '<tr><td style="color:#555">Communication</td><td>Cotisation ' . $year . '</td></tr>'
            . '</table>'
            . '<hr style="border:none;border-top:1px solid #e0e0e0;margin:24px 0">';
    } else {
        $paymentInfoText  = '';
        $paymentInfoBlock = '';
    }

    $salutation = mbBuildSalutation($m->firstname ?? '', $m->lastname ?? '', $m->society ?? '');
    return array_merge($salutation, [
        'firstname'            => $m->firstname ?? '',
        'lastname'             => $m->lastname  ?? '',
        'email'                => $m->email     ?? '',
        'year'                 => (string)$year,
        'membership_url'       => $membershipUrl,
        'membership_url_block' => $membershipBlock,
        'org_name'             => $orgName,
        'org_address'          => $appSettings['org_address'] ?? '',
        'org_city'             => $appSettings['org_city']    ?? '',
        'org_web'              => $appSettings['org_web']     ?? '',
        'contact_email'        => $contactEmail,
        'payment_info_text'    => $paymentInfoText,
        'payment_info_block'   => $paymentInfoBlock,
    ]);
}
