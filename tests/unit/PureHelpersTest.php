<?php
/**
 * Unit tests for pure date/string helpers (html/includes/lib/pure.php).
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

use PHPUnit\Framework\TestCase;

final class PureHelpersTest extends TestCase
{
    public function testValidDateRoundTrips(): void
    {
        // Parse then format should return the original d/m/Y string, TZ-independent.
        $ts = formatedDateToTimeStamp('15/06/2023');
        $this->assertGreaterThan(0, $ts);
        $this->assertSame('15/06/2023', timeStampToformatedDate($ts));
    }

    public function testEmptyAndNullDateReturnZero(): void
    {
        $this->assertSame(0, formatedDateToTimeStamp(''));
        $this->assertSame(0, formatedDateToTimeStamp(null));
    }

    public function testOutOfRangeDateIsRejected(): void
    {
        // createFromFormat silently rolls 32/13 over; the helper must reject it.
        $this->assertSame(0, formatedDateToTimeStamp('32/13/2025'));
        $this->assertSame(0, formatedDateToTimeStamp('31/02/2025'));
    }

    public function testMalformedDateIsRejected(): void
    {
        $this->assertSame(0, formatedDateToTimeStamp('not-a-date'));
        $this->assertSame(0, formatedDateToTimeStamp('2025-01-01'));
    }

    public function testTimeStampZeroFormatsEmpty(): void
    {
        $this->assertSame('', timeStampToformatedDate(0));
        $this->assertSame('', timeStampToformatedDate(null));
    }

    public function testUnquoteReplacesTypographicApostrophe(): void
    {
        $this->assertSame("l'association", unquote("l\u{2019}association"));
        // Straight apostrophes and other text pass through unchanged.
        $this->assertSame("déjà l'a", unquote("déjà l'a"));
        $this->assertSame('', unquote(''));
    }
}
