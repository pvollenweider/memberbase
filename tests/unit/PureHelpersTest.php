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

    public function testDateTimeBoundFormatsAsMysqlDatetime(): void
    {
        $ts = mktime(0, 0, 0, 6, 15, 2020);
        $this->assertSame('2020-06-15 00:00:00', mbDateTimeBound($ts));
    }

    public function testUnquoteReplacesTypographicApostrophe(): void
    {
        $this->assertSame("l'association", unquote("l\u{2019}association"));
        // Straight apostrophes and other text pass through unchanged.
        $this->assertSame("déjà l'a", unquote("déjà l'a"));
        $this->assertSame('', unquote(''));
    }

    // ── mbBuildSalutation ─────────────────────────────────────────────────────

    public function testSalutationWithFullName(): void
    {
        $r = mbBuildSalutation('Jean', 'Dupont', '');
        $this->assertSame('Jean Dupont', $r['display_name']);
        $this->assertSame('', $r['society']);
        $this->assertStringContainsString('Jean Dupont', $r['greeting']);
        $this->assertSame('Bonjour Jean Dupont,', $r['greeting_text']);
    }

    public function testSalutationSocietyOnlyFallback(): void
    {
        $r = mbBuildSalutation('', '', 'ACME SA');
        $this->assertSame('ACME SA', $r['display_name']);
        $this->assertSame('ACME SA', $r['society']);
        // No person name → generic greeting (no society name in greeting).
        $this->assertSame('Bonjour,', $r['greeting_text']);
        $this->assertStringNotContainsString('ACME', $r['greeting_text']);
    }

    public function testSalutationPersonNameTakesPrecedence(): void
    {
        $r = mbBuildSalutation('Marie', 'Curie', 'Lab SA');
        $this->assertSame('Marie Curie', $r['display_name']);
        $this->assertStringContainsString('Marie Curie', $r['greeting']);
    }

    public function testSalutationAllEmpty(): void
    {
        $r = mbBuildSalutation('', '', '');
        $this->assertSame('', $r['display_name']);
        $this->assertSame('Bonjour,', $r['greeting_text']);
    }

    public function testSalutationGreetingHtmlEscapes(): void
    {
        $r = mbBuildSalutation('Jean', 'D<oe>', '');
        $this->assertStringContainsString('D&lt;oe&gt;', $r['greeting']);
    }

    // ── mbRenderTemplate ─────────────────────────────────────────────────────

    public function testRenderTemplateReplacesTokens(): void
    {
        $out = mbRenderTemplate('Hello {{name}}, year {{year}}.', ['name' => 'Alice', 'year' => '2025']);
        $this->assertSame('Hello Alice, year 2025.', $out);
    }

    public function testRenderTemplateUnknownTokensLeftIntact(): void
    {
        $out = mbRenderTemplate('Hello {{name}}!', ['other' => 'x']);
        $this->assertSame('Hello {{name}}!', $out);
    }

    public function testRenderTemplateEmptyVars(): void
    {
        $out = mbRenderTemplate('No tokens here.', []);
        $this->assertSame('No tokens here.', $out);
    }

    // ── mbBuildCotiReminderVars ───────────────────────────────────────────────

    private function fakeMember(string $first = 'Jean', string $last = 'Dupont', string $society = '', string $email = 'j@example.com'): object
    {
        return (object)['firstname' => $first, 'lastname' => $last, 'society' => $society, 'email' => $email];
    }

    public function testCotiReminderVarsContainsExpectedKeys(): void
    {
        $settings = ['org_name' => 'MonOrg', 'org_city' => 'Genève', 'smtp_reply_to' => 'reply@org.ch'];
        $vars = mbBuildCotiReminderVars($this->fakeMember(), 2025, $settings);

        foreach (['display_name', 'greeting', 'greeting_text', 'firstname', 'lastname', 'email',
                  'year', 'org_name', 'org_city', 'contact_email', 'membership_url', 'membership_url_block'] as $key) {
            $this->assertArrayHasKey($key, $vars, "Missing key: $key");
        }
        $this->assertSame('2025', $vars['year']);
        $this->assertSame('MonOrg', $vars['org_name']);
        $this->assertSame('reply@org.ch', $vars['contact_email']);
    }

    public function testCotiReminderVarsMembershipUrlBlock(): void
    {
        $settings = ['membership_url' => 'https://example.com/join'];
        $vars = mbBuildCotiReminderVars($this->fakeMember(), 2025, $settings);

        $this->assertStringContainsString('https://example.com/join', $vars['membership_url_block']);
        $this->assertStringContainsString('<a ', $vars['membership_url_block']);
    }

    public function testCotiReminderVarsMembershipUrlEmptyBlock(): void
    {
        $vars = mbBuildCotiReminderVars($this->fakeMember(), 2025, []);
        $this->assertSame('', $vars['membership_url_block']);
        $this->assertSame('', $vars['membership_url']);
    }

    public function testCotiReminderVarsContactEmailFallsBackToFromEmail(): void
    {
        $settings = ['smtp_from_email' => 'from@org.ch'];
        $vars = mbBuildCotiReminderVars($this->fakeMember(), 2025, $settings);
        $this->assertSame('from@org.ch', $vars['contact_email']);
    }

    public function testNormalizeCommentWhitespaceStripsInsideTags(): void
    {
        // Strips whitespace right after an opening tag and right before a
        // closing tag; whitespace *between* two tags is left untouched.
        $this->assertSame(
            '<p>Hello</p>  <p>World</p>',
            mbNormalizeCommentWhitespace("<p>\n  Hello\n</p>  <p>World  </p>")
        );
    }

    public function testNormalizeCommentWhitespaceLeavesPlainTextAlone(): void
    {
        $this->assertSame('no tags here', mbNormalizeCommentWhitespace('no tags here'));
    }

    public function testFormatSwissIdeValidNumber(): void
    {
        $this->assertSame('CHE-123.456.789', mbFormatSwissIde('CHE-123.456.789'));
        $this->assertSame('CHE-123.456.789', mbFormatSwissIde('123 456 789'));
        // More than 9 digits: keeps the last 9.
        $this->assertSame('CHE-123.456.789', mbFormatSwissIde('CHE-000.123.456.789'));
    }

    public function testFormatSwissIdeTooShortReturnsNull(): void
    {
        $this->assertNull(mbFormatSwissIde('12345'));
        $this->assertNull(mbFormatSwissIde(''));
    }

    public function testValidComptaTypeColorAcceptsKnownColor(): void
    {
        $this->assertSame('ca-orange-subtle', mbValidComptaTypeColor('ca-orange-subtle'));
    }

    public function testValidComptaTypeColorFallsBackOnUnknown(): void
    {
        $this->assertSame('bg-light', mbValidComptaTypeColor('not-a-color'));
        $this->assertSame('bg-light', mbValidComptaTypeColor(null));
    }

    public function testComptaTypeReturnUrlDefaults(): void
    {
        $this->assertSame('?view=settings&tab=compta', mbComptaTypeReturnUrl(null, null));
    }

    public function testComptaTypeReturnUrlHonorsAllowedViewAndSanitizesTab(): void
    {
        $this->assertSame(
            '?view=manageComptaTypes&tab=dontions',
            mbComptaTypeReturnUrl('manageComptaTypes', 'don4tions!')
        );
    }

    public function testComptaTypeReturnUrlRejectsUnknownView(): void
    {
        $this->assertSame('?view=settings&tab=compta', mbComptaTypeReturnUrl('someOtherView', 'compta'));
    }
}
