<?php
/**
 * Unit tests for Compta::setCotisationYear() validation.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

use PHPUnit\Framework\TestCase;

final class ComptaYearTest extends TestCase
{
    private function make(): Compta
    {
        return new Compta();
    }

    public function testNullAndEmptyStringClearYear(): void
    {
        $c = $this->make();
        $c->setCotisationYear(null);
        $this->assertNull($c->getCotisationYear());

        $c->setCotisationYear('');
        $this->assertNull($c->getCotisationYear());
    }

    public function testCurrentYearIsAccepted(): void
    {
        $c = $this->make();
        $now = (int)date('Y');
        $c->setCotisationYear($now);
        $this->assertSame($now, $c->getCotisationYear());
    }

    public function testNextYearIsAccepted(): void
    {
        $c = $this->make();
        $next = (int)date('Y') + 1;
        $c->setCotisationYear($next);
        $this->assertSame($next, $c->getCotisationYear());
    }

    public function testYearTooFarInFutureIsRejected(): void
    {
        $c = $this->make();
        $c->setCotisationYear((int)date('Y') + 2);
        $this->assertNull($c->getCotisationYear());
    }

    public function testYearTooFarInPastIsRejected(): void
    {
        $c = $this->make();
        $c->setCotisationYear((int)date('Y') - 51);
        $this->assertNull($c->getCotisationYear());
    }

    public function testOldButValidYearIsAccepted(): void
    {
        $c = $this->make();
        $old = (int)date('Y') - 10;
        $c->setCotisationYear($old);
        $this->assertSame($old, $c->getCotisationYear());
    }

    public function testStringYearIsCoerced(): void
    {
        $c = $this->make();
        $now = (int)date('Y');
        $c->setCotisationYear((string)$now);
        $this->assertSame($now, $c->getCotisationYear());
    }

    /**
     * Verify that the COALESCE fallback logic (cotisation_year ?? year-from-date)
     * produces the expected effective year for both new and legacy entries.
     */
    public function testCoalesceEffectiveYear(): void
    {
        $ts = mktime(0, 0, 0, 12, 15, 2024); // 15 Dec 2024

        // Legacy entry: no cotisation_year stored — effective year = payment year
        $c1 = $this->make();
        $c1->date = $ts;
        $c1->setCotisationYear(null);
        $effective1 = $c1->getCotisationYear() ?? (int)date('Y', (int)$c1->date);
        $this->assertSame(2024, $effective1);

        // New entry: year explicitly set to next year (December payment for next cotisation)
        $c2 = $this->make();
        $c2->date = $ts;
        $c2->setCotisationYear(2025);
        $effective2 = $c2->getCotisationYear() ?? (int)date('Y', (int)$c2->date);
        $this->assertSame(2025, $effective2);
    }
}
