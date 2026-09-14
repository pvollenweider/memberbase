<?php
/**
 * Unit tests for mbBuildAttestationFields() (html/includes/lib/attestation.php).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

use PHPUnit\Framework\TestCase;

final class AttestationFieldsTest extends TestCase
{
    public function testDateFieldHoldsTheDayNotTheYear(): void
    {
        // The bottom "Date" line on the PDF is 3 separate form fields laid
        // out jour/mois/année, misleadingly named 'date'/'mois'/'annee2' --
        // 'date' is positionally the DAY box. Regression for the bug where
        // it held date('Y') (the year, duplicating 'annee2') instead of the day.
        $asOf = mktime(0, 0, 0, 9, 14, 2026); // 14 September 2026
        $fields = mbBuildAttestationFields(
            ['org_name' => 'Casa Alianza', 'org_address' => 'Rue X', 'org_npa' => '1200', 'org_city' => 'Genève'],
            'Dupont', 'Alice', '1200 Genève', 'Rue Y', 100.0, 2025, $asOf
        );

        $this->assertSame('14', $fields['date']);
        $this->assertSame('09', $fields['mois']);
        $this->assertSame('2026', $fields['annee2']);
    }

    public function testBottomYearIsTheSigningYearNotTheDonationYear(): void
    {
        // Regression: an attestation for 2025 donations signed in 2026 (the
        // common case -- attestations for a year are sent early the next
        // year) must show 2026 at the bottom, not 2025. 'annee1' ("durant
        // l'année civile") is the donation year and must stay 2025.
        $asOf = mktime(0, 0, 0, 1, 15, 2026); // 15 January 2026
        $fields = mbBuildAttestationFields(
            ['org_name' => 'Casa Alianza', 'org_address' => 'Rue X', 'org_npa' => '1200', 'org_city' => 'Genève'],
            'Dupont', 'Alice', '1200 Genève', 'Rue Y', 100.0, 2025, $asOf
        );

        $this->assertSame('2025', $fields['annee1']);
        $this->assertSame('2026', $fields['annee2']);
    }

    public function testDefaultsToNowWhenAsOfOmitted(): void
    {
        $fields = mbBuildAttestationFields(
            ['org_name' => 'Casa Alianza', 'org_address' => 'Rue X', 'org_npa' => '1200', 'org_city' => 'Genève'],
            'Dupont', 'Alice', '1200 Genève', 'Rue Y', 100.0, 2025
        );

        $this->assertSame(date('d'), $fields['date']);
        $this->assertSame(date('m'), $fields['mois']);
    }
}
