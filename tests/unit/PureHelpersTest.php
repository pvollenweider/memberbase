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

    public function testClassifyContactTypeDefaultsToPrivate(): void
    {
        $this->assertSame(CONTACT_TYPE_PRIVATE, mbClassifyContactTypeRow(false, false, false, false));
    }

    public function testClassifyContactTypeInstitutionalTakesPriority(): void
    {
        $this->assertSame(CONTACT_TYPE_INSTITUTION, mbClassifyContactTypeRow(true, true, true, true));
    }

    public function testClassifyContactTypeFinancialBeatsCompanyAndSociety(): void
    {
        $this->assertSame(CONTACT_TYPE_FINANCIAL, mbClassifyContactTypeRow(false, true, true, true));
    }

    public function testClassifyContactTypeCompanyFromPaymentTypeAlone(): void
    {
        $this->assertSame(CONTACT_TYPE_COMPANY, mbClassifyContactTypeRow(false, false, true, false));
    }

    public function testClassifyContactTypeCompanyFromSocietyAlone(): void
    {
        // Society field non-empty is enough on its own — no need for a
        // matching "company" compta entry too (clarified mid-discussion).
        $this->assertSame(CONTACT_TYPE_COMPANY, mbClassifyContactTypeRow(false, false, false, true));
    }

    public function testValidTaskPriorityAcceptsKnownLevels(): void
    {
        $this->assertSame(1, mbValidTaskPriority(1));
        $this->assertSame(3, mbValidTaskPriority(3));
    }

    public function testValidTaskPriorityFallsBackToNormal(): void
    {
        $this->assertSame(2, mbValidTaskPriority(0));
        $this->assertSame(2, mbValidTaskPriority(99));
    }

    public function testTaskIsOverdueWhenPastAndOpen(): void
    {
        $yesterday = strtotime('-1 day');
        $this->assertTrue(mbTaskIsOverdue($yesterday, null));
    }

    public function testTaskIsNotOverdueWhenDone(): void
    {
        $yesterday = strtotime('-1 day');
        $this->assertFalse(mbTaskIsOverdue($yesterday, time()));
    }

    public function testTaskIsNotOverdueWhenNoDueDate(): void
    {
        $this->assertFalse(mbTaskIsOverdue(null, null));
    }

    public function testTaskIsNotOverdueWhenDueToday(): void
    {
        $this->assertFalse(mbTaskIsOverdue(time(), null));
    }

    public function testTaskIsNotOverdueWhenDueInFuture(): void
    {
        $this->assertFalse(mbTaskIsOverdue(strtotime('+1 day'), null));
    }

    public function testDefaultViewIsDashboardWhenBare(): void
    {
        $this->assertSame('dashboard', mbDefaultView([]));
    }

    public function testDefaultViewIsExplicitViewWhenSet(): void
    {
        $this->assertSame('peopleFinance', mbDefaultView(['view' => 'peopleFinance']));
    }

    public function testDefaultViewIsListWhenSegmentPresent(): void
    {
        $this->assertSame('list', mbDefaultView(['segment' => '5']));
    }

    public function testDefaultViewIsListWhenCombinedSegmentPresent(): void
    {
        $this->assertSame('list', mbDefaultView(['combinedSegment' => '2']));
    }

    public function testDefaultViewIsListWhenSearchStringPresent(): void
    {
        $this->assertSame('list', mbDefaultView(['searchString' => 'Dupont']));
    }

    public function testDefaultViewIsListWhenContactTypeIdPresent(): void
    {
        $this->assertSame('list', mbDefaultView(['contactTypeId' => '2']));
    }

    public function testAllowedComptaTypeIdsUnrestrictedWhenNoMatrixRow(): void
    {
        $this->assertSame([1, 2, 3], mbAllowedComptaTypeIds([1, 2, 3], []));
    }

    public function testAllowedComptaTypeIdsRestrictedToMatrixRow(): void
    {
        $this->assertSame([1, 3], mbAllowedComptaTypeIds([1, 2, 3], [1, 3]));
    }

    public function testAllowedComptaTypeIdsRestrictionExcludesArchivedCandidate(): void
    {
        // $comptaTypeIds already excludes archived types upstream — a
        // restriction listing an id not in the candidate set is simply ignored.
        $this->assertSame([1], mbAllowedComptaTypeIds([1], [1, 99]));
    }
}
