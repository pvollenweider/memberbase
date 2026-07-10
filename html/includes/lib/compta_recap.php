<?php
/**
 * Compta recap business logic -- DB queries and template-variable builders
 * extracted from the action layer.
 *
 * mbRecapBuildVars() is intentionally kept here (not pure.php) because it
 * contains HTML generation that depends on the entry data shape, not a
 * standalone pure transformation worth unit-testing in isolation.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

/**
 * Load compta entries grouped by member for recap emails.
 *
 * @param PDO      $db           Database connection
 * @param int|null $filterUserId Restrict to one user (null = all)
 * @param int      $year         Filter by payment year (0 = no filter)
 * @param bool     $force        Include already-notified entries
 * @return array<int, array>     user_id => array of compta row arrays
 */
function mbRecapLoadEntries(PDO $db, ?int $filterUserId = null, int $year = 0, bool $force = false): array
{
    $conditions = ['c.sum <> 0'];
    $params     = [];
    if (!$force) {
        $conditions[] = 'c.notified_at IS NULL';
    }
    if ($filterUserId !== null) {
        $conditions[] = 'c.user_id = ?';
        $params[]     = $filterUserId;
    }
    if ($year > 0) {
        $conditions[] = 'YEAR(FROM_UNIXTIME(c.date)) = ?';
        $params[]     = $year;
    }
    $where = implode(' AND ', $conditions);
    $stmt  = $db->prepare(
        "SELECT c.id, c.user_id, c.date, c.libele, c.sum, c.cotisation_year,
                COALESCE(ct.is_cotisation, 0)             AS ct_coti,
                COALESCE(ct.is_excluded_from_donation, 0) AS ct_excluded,
                u.firstname, u.lastname, u.society, u.email,
                COALESCE(ct.label, '') AS type_label
         FROM compta c
         JOIN contact u ON u.id = c.user_id AND u.status = 1
         LEFT JOIN compta_type ct ON ct.id = c.type_id
         WHERE $where
         ORDER BY c.user_id, c.date ASC"
    );
    $stmt->execute($params);
    $rows     = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $byMember = [];
    foreach ($rows as $r) {
        $byMember[$r['user_id']][] = $r;
    }
    return $byMember;
}

/**
 * Build the since_line string for recap templates.
 *
 * When a specific past year is requested (or force mode), uses "en YYYY"
 * instead of "depuis votre dernier recapitulatif du JJ.MM.AAAA".
 *
 * @param PDO  $db   Database connection
 * @param int  $year The recap year
 * @param bool $force Whether force mode is active
 * @return string French label for the since_line template variable
 */
function mbRecapSinceLine(PDO $db, int $year, bool $force): string
{
    // Email body is always French regardless of the admin UI locale.
    if ($force || $year !== (int)date('Y')) {
        return 'en ' . $year;
    }
    $lastBatchRaw = $db->query("SELECT MAX(notified_at) FROM compta WHERE notified_at IS NOT NULL")->fetchColumn();
    return $lastBatchRaw
        ? 'depuis votre dernier recapitulatif du ' . date('d.m.Y', strtotime($lastBatchRaw))
        : 'depuis votre adhesion';
}

/**
 * Build the template variable array and rendered entry blocks for one member.
 *
 * @param array $entries    Compta rows for one member (from mbRecapLoadEntries)
 * @param array $appSettings Global app settings
 * @return array{0: array, 1: int[], 2: string}  [vars, entry ids, total]
 */
function mbRecapBuildVars(array $entries, array $appSettings): array
{
    $sendDate    = date('d.m.Y');
    $nextYear    = (int)date('Y') + 1;
    $lines       = [];
    $htmlRows    = '';
    $ids         = [];
    $odd         = true;
    $sumTotal    = 0.0;
    $sumDonation = 0.0; // only entries not excluded from donation

    foreach ($entries as $e) {
        $ids[]      = (int)$e['id'];
        $amount_raw = (float)$e['sum'];
        $excluded   = !empty($e['ct_excluded']);
        $sumTotal  += $amount_raw;
        if (!$excluded) {
            $sumDonation += $amount_raw;
        }

        $d         = $e['date'] ? date('d.m.Y', (int)$e['date']) : '--';
        $typeLabel = $e['type_label'] !== '' ? $e['type_label'] : '--';
        $desc      = $e['libele'] !== '' ? $e['libele'] : $typeLabel;
        // Append cotisation year to description when it differs from payment year.
        if (!empty($e['ct_coti']) && !empty($e['cotisation_year'])) {
            $payYear = $e['date'] ? (int)date('Y', (int)$e['date']) : 0;
            if ((int)$e['cotisation_year'] !== $payYear) {
                $desc .= ' (' . (int)$e['cotisation_year'] . ')';
            }
        }
        $descHtml = htmlspecialchars($desc, ENT_QUOTES, 'UTF-8');
        $amount   = number_format($amount_raw, 2, '.', "'");
        $lines[]  = $d . '  ' . $desc . '  CHF ' . $amount . ($excluded ? '  *' : '');

        $bg        = $odd ? '#f7fafd' : '#ffffff';
        $htmlRows .= '<tr style="background:' . $bg . '">'
                   . '<td style="border:1px solid #dde3ea;padding:8px;font-size:14px">' . $d . '</td>'
                   . '<td style="border:1px solid #dde3ea;padding:8px;font-size:14px">' . $descHtml . '</td>'
                   . '<td style="border:1px solid #dde3ea;padding:8px;font-size:14px;text-align:right">CHF ' . $amount . '</td>'
                   . '</tr>';
        $odd = !$odd;
    }

    $totalFmt    = number_format($sumTotal, 2, '.', "'");
    $donationFmt = number_format($sumDonation, 2, '.', "'");
    $hasMixed    = $sumDonation > 0.0 && abs($sumDonation - $sumTotal) > 0.001;

    // Build total row(s) for HTML.
    $totalHtmlRows = '<tr style="background:#1a5276;color:#ffffff">'
                   . '<td colspan="2" style="border:1px solid #154360;padding:8px;text-align:right"><strong>Total des versements</strong></td>'
                   . '<td style="border:1px solid #154360;padding:8px;text-align:right"><strong>CHF ' . $totalFmt . '</strong></td>'
                   . '</tr>';
    if ($hasMixed) {
        $totalHtmlRows .= '<tr style="background:#eaf4fb;color:#1a5276">'
                        . '<td colspan="2" style="border:1px solid #dde3ea;padding:8px;text-align:right;font-size:13px">Dont dons pouvant figurer sur l\'attestation fiscale</td>'
                        . '<td style="border:1px solid #dde3ea;padding:8px;text-align:right;font-size:13px">CHF ' . $donationFmt . '</td>'
                        . '</tr>';
    }

    $entriesHtml = '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:16px 0;font-size:14px">'
                 . '<tr style="background:#1a5276;color:#ffffff">'
                 . '<th style="border:1px solid #154360;padding:8px;font-weight:600;text-align:left">Date</th>'
                 . '<th style="border:1px solid #154360;padding:8px;font-weight:600;text-align:left">Description</th>'
                 . '<th style="border:1px solid #154360;padding:8px;font-weight:600;text-align:right">Montant</th>'
                 . '</tr>' . $htmlRows . $totalHtmlRows . '</table>';

    // Build total lines for plain-text version.
    $totalLines = 'Total des versements : CHF ' . $totalFmt;
    if ($hasMixed) {
        $totalLines .= "\nDont dons pouvant figurer sur l'attestation fiscale : CHF " . $donationFmt;
    }

    // Fiscal note: only mention attestation when there are attestable amounts.
    $attestNextYear     = 'au début de l\'année ' . $nextYear;
    $attestNote         = $sumDonation > 0.0
        ? "Une attestation fiscale récapitulant vos dons attestables vous sera envoyée $attestNextYear."
        : '';
    $attestNoteHtml     = $sumDonation > 0.0
        ? '<p style="margin-top:20px;padding:14px 16px;background:#eaf4fb;border-left:4px solid #1a5276;font-size:14px;color:#1a5276">'
          . '<strong>Attestation fiscale :</strong> Un document récapitulant vos dons attestables vous sera envoyé ' . $attestNextYear . '.'
          . '</p>'
        : '';

    $contactEmail = $appSettings['smtp_reply_to'] ?? ($appSettings['smtp_from_email'] ?? '');
    $first        = $entries[0];
    $salutation   = mbBuildSalutation(
        $first['firstname'] ?? '',
        $first['lastname']  ?? '',
        $first['society']   ?? ''
    );
    $personName      = trim(($first['firstname'] ?? '') . ' ' . ($first['lastname'] ?? ''));
    $displayNameLine = ($personName === '' && $salutation['society'] !== '')
        ? ' au nom de ' . $salutation['society']
        : '';

    $vars = array_merge($salutation, [
        'firstname'          => $first['firstname'],
        'lastname'           => $first['lastname'],
        'email'              => $first['email'],
        'display_name_line'  => $displayNameLine,
        'entries'            => implode("\n", $lines),
        'entries_html'       => $entriesHtml,
        'total'              => $totalFmt,
        'total_donation'     => $donationFmt,
        'has_mixed'          => $hasMixed ? '1' : '',
        'total_lines'        => $totalLines,
        'attest_note'        => $attestNote,
        'attest_note_html'   => $attestNoteHtml,
        'send_date'          => $sendDate,
        'since_line'         => '', // filled by caller
        'org_name'           => $appSettings['org_name']    ?? '',
        'org_address'        => $appSettings['org_address'] ?? '',
        'org_city'           => $appSettings['org_city']    ?? '',
        'org_web'            => $appSettings['org_web']     ?? '',
        'contact_email'      => $contactEmail,
    ]);
    return [$vars, $ids, $totalFmt];
}
