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
    return [
        'lastName'  => 'Nom de famille',
        'firstName' => 'Prénom',
        'society'   => 'Société',
        'email'     => 'Email',
        'emailAlt'  => 'Email alt.',
        'tel'       => 'Téléphone fixe',
        'telProf'   => 'Tél. professionnel',
        'portable'  => 'Mobile',
        'fax'       => 'Fax',
        'address'   => 'Adresse',
        'npa'       => 'NPA / Ville',
        'web'       => 'Site web',
        'birthDay'  => 'Date de naissance (JJ/MM/AAAA)',
        'comment'   => 'Remarques',
    ];
}

function importAllowedFields(): array
{
    return array_keys(importFieldLabels());
}
