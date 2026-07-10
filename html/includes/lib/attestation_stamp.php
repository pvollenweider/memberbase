<?php
/**
 * Stamp/signature overlay for donation attestations (attestation.pdf template).
 *
 * The organisation's stamp and signature are static PNG images placed in the
 * blank "Tampon / Signature" area of the A4 template (bottom-left, below the
 * "Lieu / Date" line). Optional: attestations still generate fine without them.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

/**
 * Generate a one-page A4 overlay PDF with the stamp and signature images,
 * positioned to match html/assets/attestation.pdf's "Tampon / Signature" area.
 *
 * Returns null when either image file is missing or unreadable — callers
 * should skip stamping (not fail attestation generation) in that case.
 *
 * @param string $stampPath     Absolute path to the stamp PNG
 * @param string $signaturePath Absolute path to the signature PNG
 * @return string|null          Raw PDF bytes, or null
 */
function mbGenerateAttestationOverlayPdf(string $stampPath, string $signaturePath): ?string
{
    if (!is_readable($stampPath) || !is_readable($signaturePath)) {
        return null;
    }

    $autoload = __DIR__ . '/../../vendor/autoload.php';
    if (!file_exists($autoload)) {
        return null;
    }
    require_once $autoload;

    try {
        // Points, matching attestation.pdf's page size exactly (A4: 595.22 x 842 pt).
        $fpdf = new \Fpdf\Fpdf('P', 'pt', [595.22, 842]);
        $fpdf->AddPage();

        // Signature: under the "Tampon / Signature" label (~y 723), shifted right of the margin.
        $fpdf->Image($signaturePath, 145, 730, 140, 0);

        // Stamp: right of the signature, enlarged.
        $fpdf->Image($stampPath, 300, 725, 210, 0);

        return $fpdf->Output('S', '');
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * Merge an overlay PDF onto a flattened attestation PDF via pdftk stamp.
 * Returns the original path unchanged if overlay generation fails.
 *
 * @param string $pdfPath Path to the flattened attestation PDF (modified in place)
 */
function mbStampAttestation(string $pdfPath): void
{
    // conf/ lives outside html/ (not web-accessible), same convention as conf/db.php —
    // these images are org-specific and deliberately not committed to the repo.
    $stampPath     = __DIR__ . '/../../../conf/attestation_stamp.png';
    $signaturePath = __DIR__ . '/../../../conf/attestation_signature.png';

    $overlay = mbGenerateAttestationOverlayPdf($stampPath, $signaturePath);
    if ($overlay === null) {
        return;
    }

    $tmpOverlay = tempnam(sys_get_temp_dir(), 'ovl_') . '.pdf';
    $tmpStamped = tempnam(sys_get_temp_dir(), 'stp_') . '.pdf';
    file_put_contents($tmpOverlay, $overlay);

    $cmd = sprintf(
        'pdftk %s stamp %s output %s 2>&1',
        escapeshellarg($pdfPath),
        escapeshellarg($tmpOverlay),
        escapeshellarg($tmpStamped)
    );
    exec($cmd, $cmdOutput, $returnCode);
    unlink($tmpOverlay);

    if ($returnCode === 0 && file_exists($tmpStamped) && filesize($tmpStamped) > 0) {
        rename($tmpStamped, $pdfPath);
    } else {
        @unlink($tmpStamped);
    }
}

/**
 * Bytes-in/bytes-out convenience wrapper around mbStampAttestation() for
 * callers that hold PDF content in memory (e.g. before emailing it).
 */
function mbStampAttestationBytes(string $pdfBytes): string
{
    $tmpPdf = tempnam(sys_get_temp_dir(), 'att_stamp_') . '.pdf';
    file_put_contents($tmpPdf, $pdfBytes);
    mbStampAttestation($tmpPdf);
    $stamped = file_get_contents($tmpPdf);
    unlink($tmpPdf);
    return $stamped;
}
