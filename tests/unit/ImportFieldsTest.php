<?php
/**
 * Unit tests for the CSV import field helpers (html/includes/lib/import_fields.php).
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ImportFieldsTest extends TestCase
{
    #[DataProvider('sexeCases')]
    public function testNormalizeSexe(string $raw, string $expected): void
    {
        $this->assertSame($expected, importNormalizeSexe($raw));
    }

    public static function sexeCases(): array
    {
        return [
            'empty → na'                 => ['', 'na'],
            'blank → na'                 => ['   ', 'na'],
            'Monsieur → m'               => ['Monsieur', 'm'],
            'Madame → f'                 => ['Madame', 'f'],
            'Mme lowercase → f'          => ['mme', 'f'],
            'Mademoiselle → f'           => ['Mademoiselle', 'f'],
            'couple → hf'                => ['Madame et Monsieur', 'hf'],
            'couple reversed → hf'       => ['Monsieur et Madame', 'hf'],
            'unknown → na'               => ['Docteur', 'na'],
            'homme → m'                  => ['Homme', 'm'],
            'femme → f'                  => ['Femme', 'f'],
        ];
    }

    public function testImportFieldValueMapsSexe(): void
    {
        $this->assertSame('m', importFieldValue('sexe', 'Monsieur'));
        $this->assertSame('hf', importFieldValue('sexe', 'Madame et Monsieur'));
    }

    public function testImportFieldValuePassesThroughOtherFields(): void
    {
        // Non-sexe fields go through unquote() (typographic apostrophe normalized).
        $this->assertSame("l'asso", importFieldValue('society', "l\u{2019}asso"));
        $this->assertSame('Dupont', importFieldValue('lastName', 'Dupont'));
    }

    public function testAllowedFieldsMatchLabels(): void
    {
        $labels  = importFieldLabels();
        $allowed = importAllowedFields();
        $this->assertSame(array_keys($labels), $allowed);
        // A few keys the wizard relies on must exist.
        foreach (['lastName', 'firstName', 'email', 'sexe', 'birthDay'] as $key) {
            $this->assertArrayHasKey($key, $labels);
        }
    }
}
