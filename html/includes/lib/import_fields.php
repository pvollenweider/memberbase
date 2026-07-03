<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Shared field definitions for the CSV import wizard.
 *
 * Single source of truth for the member fields an import can target:
 * keys are User property names, values are the French UI labels.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

function importFieldLabels(): array
{
    global $GLOBAL;
    return [
        'lastName'  => $GLOBAL['lastNameFull'],
        'firstName' => $GLOBAL['firstName'],
        'society'   => $GLOBAL['society'],
        'sexe'      => $GLOBAL['genderCivility'],
        'title'     => $GLOBAL['title'],
        'email'     => $GLOBAL['email'],
        'emailAlt'  => $GLOBAL['emailAlt'],
        'tel'       => $GLOBAL['landlinePhone'],
        'telProf'   => $GLOBAL['workPhone'],
        'portable'  => $GLOBAL['mobilePhone'],
        'fax'       => $GLOBAL['fax'],
        'address'   => $GLOBAL['address'],
        'npa'       => $GLOBAL['npaCity'],
        'web'       => $GLOBAL['website'],
        'birthDay'  => $GLOBAL['birthDateWithFormat'],
        'comment'   => $GLOBAL['remarks'],
    ];
}

function importAllowedFields(): array
{
    return array_keys(importFieldLabels());
}

/**
 * Normalizes a free-text civility ("Monsieur", "Madame", "Madame et Monsieur"…)
 * to the users.sexe enum: na / f (Femme) / m (Homme) / hf (couple).
 */
function importNormalizeSexe(string $raw): string
{
    $v = mb_strtolower(trim($raw));
    if ($v === '') return 'na';
    $hasF = (bool)preg_match('/\b(madame|mme|mademoiselle|mlle|femme)\b/u', $v);
    $hasM = (bool)preg_match('/\b(monsieur|mr|m\.?|homme)\b/u', $v);
    if ($hasF && $hasM) return 'hf';
    if ($hasF)          return 'f';
    if ($hasM)          return 'm';
    return 'na';
}

/**
 * Applies per-field normalization to a raw CSV value before assigning it to a User.
 * sexe is mapped to its enum; every other field is passed through unquote().
 */
function importFieldValue(string $field, string $raw): string
{
    if ($field === 'sexe') return importNormalizeSexe($raw);
    return unquote($raw);
}
