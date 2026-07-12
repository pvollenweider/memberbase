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

/**
 * Formats a Unix timestamp as a MySQL DATETIME literal ('Y-m-d H:i:s') for
 * binding against DATETIME columns (e.g. compta.date range bounds built via
 * mktime()). Always converts in PHP, never via MySQL's FROM_UNIXTIME()/
 * UNIX_TIMESTAMP() — those use the DB session timezone, which differs from
 * PHP's hardcoded "Europe/Zurich" (see includes/lib/bootstrap.php) and would
 * silently shift the bound by an hour or two (see #143).
 */
function mbDateTimeBound(int $ts): string
{
    return date('Y-m-d H:i:s', $ts);
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
 * Build a formal, gender-aware salutation ("Chère Madame X," / "Cher Monsieur X,")
 * from the contact.sexe enum (na / f / m / hf), for formal correspondence
 * (donation attestations) where "Bonjour X," would be too casual.
 *
 * Falls back to "Madame, Monsieur," when sexe is unknown/couple or no name is set.
 *
 * Returns: formal_greeting (HTML), formal_greeting_text (plain).
 */
function mbBuildFormalSalutation(string $sexe, string $firstname, string $lastname, string $society): array
{
    $ln  = trim($lastname);
    $soc = trim($society);

    if ($sexe === 'f' && $ln !== '') {
        $text = "Chère Madame $ln,";
    } elseif ($sexe === 'm' && $ln !== '') {
        $text = "Cher Monsieur $ln,";
    } elseif ($sexe === 'hf' && $ln !== '') {
        $text = "Chère Madame, Cher Monsieur $ln,";
    } elseif ($ln === '' && $soc !== '') {
        $text = "Madame, Monsieur,";
    } else {
        $text = "Madame, Monsieur,";
    }

    return [
        'formal_greeting'      => htmlspecialchars($text, ENT_QUOTES, 'UTF-8'),
        'formal_greeting_text' => $text,
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
 * Build email template variables for a donation attestation.
 * Pure -- no DB, no I/O.
 */
function mbBuildAttestationVars(object $m, array $appSettings, int $year): array
{
    $contactEmail = $appSettings['smtp_reply_to'] ?? ($appSettings['smtp_from_email'] ?? '');
    $salutation   = mbBuildSalutation($m->firstname ?? '', $m->lastname ?? '', $m->society ?? '');
    $formal       = mbBuildFormalSalutation($m->sexe ?? 'na', $m->firstname ?? '', $m->lastname ?? '', $m->society ?? '');
    return array_merge($salutation, $formal, [
        'org_name'      => $appSettings['org_name'] ?? '',
        'contact_email' => $contactEmail,
        'year'          => (string)$year,
    ]);
}

/**
 * Strip whitespace immediately inside HTML tags (after an opening tag, before
 * a closing tag) to match TipTap's editor output, which never emits that
 * whitespace. Used when normalizing a rich-text comment field before saving
 * or diffing against the stored value.
 */
function mbNormalizeCommentWhitespace(string $html): string
{
    $html = preg_replace('/(<(?!\/)[^>]+>)\s+/', '$1', $html);
    return preg_replace('/\s+(<\/[^>]+>)/', '$1', $html);
}

/**
 * Normalize a Swiss company identification number (IDE/UID) to the canonical
 * CHE-XXX.XXX.XXX form. Strips everything but digits, keeps the last 9
 * (the CHE prefix is non-numeric, the UID body is always 9 digits). Returns
 * null when fewer than 9 digits remain -- not enough to form a valid UID.
 */
function mbFormatSwissIde(string $raw): ?string
{
    $digits = preg_replace('/[^0-9]/', '', $raw);
    if (strlen($digits) < 9) {
        return null;
    }
    $uid9 = substr($digits, -9);
    return 'CHE-' . substr($uid9, 0, 3) . '.' . substr($uid9, 3, 3) . '.' . substr($uid9, 6, 3);
}

/** Compta type badge colors allowed in the UI -- Bootstrap subtles + a few custom hues. */
const COMPTA_TYPE_COLORS = [
    'bg-primary-subtle', 'bg-secondary-subtle', 'bg-success-subtle', 'bg-danger-subtle',
    'bg-warning-subtle', 'bg-info-subtle', 'bg-light', 'bg-dark-subtle',
    'ca-orange-subtle', 'ca-teal-subtle', 'ca-pink-subtle', 'ca-purple-subtle',
    'ca-indigo-subtle', 'ca-lime-subtle',
];

/** Validates a compta type color against the allowed palette, falling back to 'bg-light'. */
function mbValidComptaTypeColor(?string $color): string
{
    return in_array($color, COMPTA_TYPE_COLORS, true) ? $color : 'bg-light';
}

/**
 * Build the `?view=...&tab=...` redirect suffix used after compta-type
 * actions (add/update/delete/reorder), which can be triggered from either
 * the Réglages page or the standalone "manage compta types" view.
 */
function mbComptaTypeReturnUrl(?string $returnView, ?string $returnTab): string
{
    $allowedViews = ['settings', 'manageComptaTypes'];
    $view = in_array($returnView, $allowedViews, true) ? $returnView : 'settings';
    $tab  = preg_replace('/[^a-zA-Z]/', '', $returnTab ?? 'compta');
    return '?view=' . $view . '&tab=' . $tab;
}

const CONTACT_TYPE_PRIVATE     = 'private';
const CONTACT_TYPE_INSTITUTION = 'institution';
const CONTACT_TYPE_FINANCIAL   = 'financial';
const CONTACT_TYPE_COMPANY     = 'company';

/**
 * Contact type classification rule (issue #165), priority order, first
 * match wins: institutional payment > financial institution payment >
 * company payment or non-empty society > private (default).
 */
function mbClassifyContactTypeRow(bool $hasInstitutional, bool $hasFinancial, bool $hasCompany, bool $hasSociety): string
{
    if ($hasInstitutional) {
        return CONTACT_TYPE_INSTITUTION;
    }
    if ($hasFinancial) {
        return CONTACT_TYPE_FINANCIAL;
    }
    if ($hasCompany || $hasSociety) {
        return CONTACT_TYPE_COMPANY;
    }
    return CONTACT_TYPE_PRIVATE;
}

/** Task priority levels (1=haute, 2=normale, 3=basse) — see SuiviTask. */
const TASK_PRIORITIES = [1, 2, 3];

/** Validates a task priority, falling back to 2 (normale) for anything unknown. */
function mbValidTaskPriority(int $priority): int
{
    return in_array($priority, TASK_PRIORITIES, true) ? $priority : 2;
}

/** A task is overdue when open and its due date is strictly before today. */
function mbTaskIsOverdue(?int $dueDate, ?int $doneAt): bool
{
    if ($doneAt !== null || !$dueDate) {
        return false;
    }
    return date('Y-m-d', $dueDate) < date('Y-m-d');
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
