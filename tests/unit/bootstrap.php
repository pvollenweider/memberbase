<?php
/**
 * PHPUnit bootstrap for the pure-logic unit suite.
 *
 * Loads only side-effect-free files (no DB connection). APP_ENTRY is defined
 * so files guarded by `defined('APP_ENTRY') or die(...)` can be included.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

define('APP_ENTRY', true);

$lib = __DIR__ . '/../../html/includes/lib';
// PHPUnit may include this bootstrap inside a function scope: force $GLOBAL
// into the true global scope so `global $GLOBAL;` works in library functions.
global $GLOBAL;
require_once __DIR__ . '/../../html/locales/resources_fr.php'; // $GLOBAL labels (used by importFieldLabels)
require_once $lib . '/pure.php';           // formatedDateToTimeStamp, timeStampToformatedDate, unquote
require_once $lib . '/import_fields.php';  // importNormalizeSexe, importFieldValue, importFieldLabels
require_once __DIR__ . '/../../html/classes/compta_class.php'; // Compta entity (pure methods only)
