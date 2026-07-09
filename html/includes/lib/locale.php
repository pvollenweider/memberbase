<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * UI locale loading — PHP resource bundles, one file per language.
 *
 * French (resources_fr.php) is the complete default bundle. Other languages
 * (resources_en.php, resources_de.php, resources_es.php) are loaded on top
 * of it, so any key missing from a translation transparently falls back to
 * French instead of breaking the page.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

const APP_DEFAULT_LOCALE = 'fr';

/** Available UI locales — code => native language name (never translated). */
function mbAvailableLocales(): array
{
    return [
        'fr' => 'Français',
        'en' => 'English',
        'de' => 'Deutsch',
        'es' => 'Español',
    ];
}

/** Normalize any user-supplied value to a supported locale code. */
function mbNormalizeLocale(?string $lang): string
{
    $lang = strtolower(trim((string)$lang));
    return array_key_exists($lang, mbAvailableLocales()) ? $lang : APP_DEFAULT_LOCALE;
}

/**
 * Populate $GLOBAL with the French base bundle, then apply the requested
 * language's overrides. Safe to call from any entry point.
 */
function mbLoadLocale(?string $lang = null): void
{
    global $GLOBAL;
    $lang = mbNormalizeLocale($lang);
    require __DIR__ . '/../../locales/resources_fr.php';
    if ($lang !== APP_DEFAULT_LOCALE) {
        require __DIR__ . '/../../locales/resources_' . $lang . '.php';
    }
    $GLOBAL['currentLocale'] = $lang;
}
