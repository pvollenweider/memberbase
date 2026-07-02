<?php
/**
 * Pure helper functions — no side effects, no I/O, no globals.
 *
 * Extracted from bootstrap.php so they can be unit-tested in isolation
 * (PHPUnit, see tests/unit/) without opening a database connection.
 * Intentionally NOT guarded by APP_ENTRY: it defines functions only and
 * emits nothing, so it is safe to include from anywhere (app or tests).
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

/** Parses a d/m/Y date string (as used in form inputs) to a Unix timestamp. */
function formatedDateToTimeStamp(?string $formatedDate): int
{
    if ($formatedDate) {
        $d = DateTime::createFromFormat('d/m/Y', $formatedDate);
        // Reject out-of-range dates (e.g. 32/13/2025) that createFromFormat silently rolls over
        $errors = DateTime::getLastErrors();
        if (!$d || ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return 0;
        }
        return $d->getTimestamp();
    }
    return 0;
}

/** Formats a Unix timestamp to a d/m/Y display string for form inputs and tables. */
function timeStampToformatedDate(?int $timestamp): string
{
    return $timestamp ? date("d/m/Y", $timestamp) : "";
}

/** Replaces typographic apostrophes (’) with straight apostrophes (') from user input. */
function unquote(string $s): string
{
    return str_replace("\u{2019}", "'", $s);
}
