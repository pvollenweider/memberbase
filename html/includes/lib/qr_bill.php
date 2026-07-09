<?php
/**
 * Swiss QR-Rechnung (QR bill) generation for cotisation reminders.
 *
 * Generates a PDF attachment containing the Swiss payment slip (bulletin de versement QR)
 * with an open amount, no reference number (TYPE_NON), and the cotisation year as message.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

use Sprain\SwissQrBill as QrBill;

/**
 * Generate a Swiss QR bill PDF as a raw string.
 *
 * Returns null when:
 * - org_iban is empty (QR bill not configured)
 * - vendor/autoload.php is not installed
 * - the IBAN or address data fails the QR bill validator
 *
 * @param array $settings  $appSettings values (org_iban, org_name, org_address, org_npa, org_city required)
 * @param int   $year      Cotisation year used as unstructured message ("Cotisation YYYY")
 * @return string|null     Raw PDF bytes, or null on failure
 */
function mbGenerateQrBillPdf(array $settings, int $year): ?string
{
    $autoload = __DIR__ . '/../../vendor/autoload.php';
    if (!file_exists($autoload)) {
        return null;
    }
    require_once $autoload;

    $iban = trim($settings['org_iban'] ?? '');
    if ($iban === '') {
        return null;
    }

    // Normalize IBAN: remove spaces (SIX standard payload must have no spaces)
    $ibanClean = strtoupper(str_replace([' ', '-'], '', $iban));

    $name    = trim($settings['org_name']    ?? '');
    $street  = trim($settings['org_address'] ?? '');
    $npa     = trim($settings['org_npa']     ?? '');
    $city    = trim($settings['org_city']    ?? '');
    $npaCity = trim("$npa $city");

    try {
        $qrBill = QrBill\QrBill::create();

        $qrBill->setCreditorInformation(
            QrBill\DataGroup\Element\CreditorInformation::create($ibanClean)
        );

        $qrBill->setCreditor(
            QrBill\DataGroup\Element\CombinedAddress::create(
                $name ?: 'Association',
                $street ?: '-',
                $npaCity ?: '-',
                'CH'
            )
        );

        // Open amount: currency only, no amount value
        $qrBill->setPaymentAmountInformation(
            QrBill\DataGroup\Element\PaymentAmountInformation::create('CHF')
        );

        // No reference number (NON type + standard IBAN, not QR-IBAN)
        $qrBill->setPaymentReference(
            QrBill\DataGroup\Element\PaymentReference::create(
                QrBill\DataGroup\Element\PaymentReference::TYPE_NON
            )
        );

        // Unstructured message visible on the payment slip
        $qrBill->setAdditionalInformation(
            QrBill\DataGroup\Element\AdditionalInformation::create(
                'Cotisation ' . $year
            )
        );

        // Validate before rendering
        $violations = $qrBill->getViolations();
        if (count($violations) > 0) {
            return null;
        }

        // Create A4 portrait page — the payment part occupies the bottom 105 mm
        $fpdf = new \Fpdf\Fpdf('P', 'mm', 'A4');
        $fpdf->AddPage();

        $output = new QrBill\PaymentPart\Output\FpdfOutput\FpdfOutput($qrBill, 'fr', $fpdf);
        $output->setPrintable(false)->getPaymentPart();

        // 'S' = return PDF as string
        return $fpdf->Output('S', '');

    } catch (\Throwable $e) {
        return null;
    }
}
