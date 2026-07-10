<?php
/**
 * Donation attestation PDF generation (fills html/assets/attestation.pdf via pdftk).
 *
 * Shared by attestation_don.php (single download), attestation_bulk.php (bulk
 * download) and the email-sending actions (attestation_email.php).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

/** Field names: convert UTF-8 → Latin-1 to match the PDF's internal AcroForm encoding. */
function mbAttestationFdfEscapeName(string $s): string
{
    $latin1 = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $latin1);
}

/** Field values: encode as UTF-16BE hex string to support accented characters. */
function mbAttestationFdfEncodeValue(string $s): string
{
    $utf16be = mb_convert_encoding($s, 'UTF-16BE', 'UTF-8');
    return '<' . strtoupper(bin2hex("\xfe\xff" . $utf16be)) . '>';
}

function mbAttestationFdfGenerate(array $fields): string
{
    $fdf  = "%FDF-1.2\n%\xe2\xe3\xcf\xd3\n";
    $fdf .= "1 0 obj\n<< /FDF << /Fields [\n";
    foreach ($fields as $name => $value) {
        $fdf .= "<< /T (" . mbAttestationFdfEscapeName($name) . ") /V " . mbAttestationFdfEncodeValue($value) . " >>\n";
    }
    $fdf .= "] >> >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
    return $fdf;
}

/**
 * Build the AcroForm field values for one donor's attestation.
 *
 * @param array  $appSettings Global app_settings (org_name, org_address, org_npa, org_city, org_ide)
 * @param string $lastname
 * @param string $firstname
 * @param string $npaFull     Combined "NPA Localité" field as stored on the contact
 * @param string $address
 * @param float  $total       Total donation amount for the year
 * @param int    $year
 * @param int    $asOf        Unix timestamp used for the "Lieu / Date" signature line
 *                             (defaults to now — pass the original send date to regenerate
 *                             a past attestation with its original date, e.g. from email_log)
 * @return array
 */
function mbBuildAttestationFields(
    array $appSettings,
    string $lastname,
    string $firstname,
    string $npaFull,
    string $address,
    float $total,
    int $year,
    ?int $asOf = null
): array {
    global $GLOBAL;
    $npaParts = preg_split('/\s+/', trim($npaFull), 2);
    $asOf     = $asOf ?? time();

    $orgIde   = trim($appSettings['org_ide']  ?? '');
    $orgName  = trim($appSettings['org_name'] ?? '');
    $instName = $orgIde ? "$orgName — IDE $orgIde" : $orgName;

    return [
        'Nom de institution' => $instName,
        'Adresse'            => $appSettings['org_address'] ?? '',
        'NPA'                => $appSettings['org_npa']     ?? '',
        'Localite'           => $appSettings['org_city']    ?? '',
        'Nom'                => $lastname,
        'Prenom'             => $firstname,
        'Adresse 2'          => $address,
        'NPA 2'              => $npaParts[0] ?? '',
        'Localite 2'         => $npaParts[1] ?? '',
        'annee1'             => (string)$year,
        'annee2'             => (string)$year,
        'Case à cocher2'     => $GLOBAL['yes'] ?? 'Oui', // Dons en espèces
        'Somme'              => number_format($total, 2, '.', "'"),
        'Lieu'               => $appSettings['org_city'] ?? '',
        'mois'               => date('m', $asOf),
        'date'               => date('Y', $asOf),
    ];
}

/**
 * Fill the attestation.pdf template with the given fields via pdftk (fill_form + flatten).
 *
 * @return string|null Raw PDF bytes, or null on pdftk failure
 */
function mbGenerateAttestationPdf(array $fields): ?string
{
    $tmpFdf = tempnam(sys_get_temp_dir(), 'att_') . '.fdf';
    $tmpPdf = tempnam(sys_get_temp_dir(), 'att_') . '.pdf';
    file_put_contents($tmpFdf, mbAttestationFdfGenerate($fields));

    $template = __DIR__ . '/../../assets/attestation.pdf';
    $cmd = sprintf(
        'pdftk %s fill_form %s output %s flatten 2>&1',
        escapeshellarg($template),
        escapeshellarg($tmpFdf),
        escapeshellarg($tmpPdf)
    );
    exec($cmd, $cmdOutput, $returnCode);
    unlink($tmpFdf);

    if ($returnCode !== 0 || !file_exists($tmpPdf) || filesize($tmpPdf) === 0) {
        if (file_exists($tmpPdf)) { unlink($tmpPdf); }
        return null;
    }

    $bytes = file_get_contents($tmpPdf);
    unlink($tmpPdf);
    return $bytes;
}

/**
 * Compute a member's total donation amount for a year (excludes types flagged
 * is_excluded_from_donation), same rule used across attestations/résumé dons.
 */
function mbGetDonationTotal(PDO $pdo, int $userId, int $year): float
{
    $from = mktime(0, 0, 0, 1, 0, $year);
    $to   = mktime(0, 0, 0, 1, 1, $year + 1);
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(c.sum), 0) AS total
        FROM compta c
        WHERE c.user_id = ?
          AND c.date > ? AND c.date < ?
          AND c.type_id NOT IN (SELECT id FROM compta_type WHERE is_excluded_from_donation = 1)
    ");
    $stmt->execute([$userId, $from, $to]);
    return (float)$stmt->fetchObject()->total;
}

/**
 * Whether a member has at least one cotisation-type entry (compta_type.is_cotisation=1)
 * for the given year — used to conditionally show the "deductions don't apply to
 * cotisations" note in the attestation email.
 */
function mbHasCotisationEntries(PDO $pdo, int $userId, int $year): bool
{
    $from = mktime(0, 0, 0, 1, 0, $year);
    $to   = mktime(0, 0, 0, 1, 1, $year + 1);
    $stmt = $pdo->prepare("
        SELECT 1 FROM compta c
        WHERE c.user_id = ?
          AND c.date > ? AND c.date < ?
          AND c.type_id IN (SELECT id FROM compta_type WHERE is_cotisation = 1)
        LIMIT 1
    ");
    $stmt->execute([$userId, $from, $to]);
    return (bool)$stmt->fetchColumn();
}

/**
 * mbBuildAttestationVars() plus the cotisation_note/cotisation_note_html vars,
 * which require a DB lookup (mbBuildAttestationVars itself stays pure/no-DB).
 */
function mbBuildAttestationVarsForUser(PDO $pdo, object $m, array $appSettings, int $year): array
{
    $vars = mbBuildAttestationVars($m, $appSettings, $year);
    if (mbHasCotisationEntries($pdo, (int)$m->id, $year)) {
        $vars['cotisation_note']      = " Nous vous rappelons toutefois que ces déductions ne s'appliquent pas aux éventuelles cotisations.";
        $vars['cotisation_note_html'] = ' Nous vous rappelons toutefois que ces déductions ne s\'appliquent pas aux éventuelles cotisations.';
    } else {
        $vars['cotisation_note']      = '';
        $vars['cotisation_note_html'] = '';
    }
    return $vars;
}

/**
 * Generate one member's attestation PDF for a year, optionally stamped
 * (stamp/signature overlay, see attestation_stamp.php).
 *
 * @param ?int $asOf Unix timestamp for the "Lieu / Date" line (defaults to now —
 *                    pass the original send date to regenerate a past attestation)
 * @return string|null Raw PDF bytes, or null if the member/data lookup or pdftk generation fails
 */
function mbGenerateAttestationForUser(PDO $pdo, array $appSettings, Contact $user, int $year, bool $stamp, ?int $asOf = null): ?string
{
    $total  = mbGetDonationTotal($pdo, (int)$user->getId(), $year);
    $fields = mbBuildAttestationFields(
        $appSettings,
        $user->getLastName()  ?? '',
        $user->getFirstName() ?? '',
        $user->getNpa()       ?? '',
        $user->getAddress()   ?? '',
        $total,
        $year,
        $asOf
    );
    $pdf = mbGenerateAttestationPdf($fields);
    if ($pdf === null) {
        return null;
    }
    if ($stamp) {
        require_once __DIR__ . '/attestation_stamp.php';
        $pdf = mbStampAttestationBytes($pdf);
    }
    return $pdf;
}
