<?php
/**
 * Data integrity checks -- DB queries extracted from the integrity view.
 *
 * mbRunIntegrityChecks() returns a named array of result sets so the
 * view only needs to render the data, not fetch it.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

/**
 * Run all integrity checks and return a map of result sets.
 *
 * Keys match the variable names previously used in the view to keep
 * the template changes minimal.
 *
 * @param PDO $db Database connection
 * @return array<string, object[]> Named result sets; each value is a (possibly empty) array of rows
 */
function mbRunIntegrityChecks(PDO $db): array
{
    return [
        'dupNames' => $db->query("
            SELECT firstName, lastName, COUNT(*) AS cnt,
                   GROUP_CONCAT(id ORDER BY id SEPARATOR ',') AS ids
            FROM users
            WHERE status=1 AND (TRIM(firstName) != '' OR TRIM(lastName) != '')
            GROUP BY TRIM(LOWER(firstName)), TRIM(LOWER(lastName))
            HAVING COUNT(*) > 1
            ORDER BY lastName, firstName
        ")->fetchAll(PDO::FETCH_OBJ),

        'dupEmails' => $db->query("
            SELECT email, COUNT(*) AS cnt,
                   GROUP_CONCAT(id ORDER BY id SEPARATOR ',') AS ids
            FROM users
            WHERE status=1 AND TRIM(email) != ''
            GROUP BY TRIM(LOWER(email))
            HAVING COUNT(*) > 1
            ORDER BY email
        ")->fetchAll(PDO::FETCH_OBJ),

        'hiddenInCats' => $db->query("
            SELECT DISTINCT t.id AS team_id, t.name AS team_name,
                   m.id AS mg_id, m.name AS mg_name, m.sort_order AS mg_sort
            FROM team t
            JOIN metagroup j ON j.teamid = t.id
            JOIN metagroup m ON m.id = j.id AND m.name IS NOT NULL AND m.is_filter = 0
            WHERE t.hidden = 1
            ORDER BY m.sort_order, m.name, t.name
        ")->fetchAll(PDO::FETCH_OBJ),

        'hiddenInMeta' => $db->query("
            SELECT DISTINCT t.id AS team_id, t.name AS team_name,
                   m.id AS mg_id, m.name AS mg_name, m.sort_order AS mg_sort
            FROM team t
            JOIN metagroup j ON j.teamid = t.id
            JOIN metagroup m ON m.id = j.id AND m.name IS NOT NULL AND m.is_filter = 1
            WHERE t.hidden = 1
            ORDER BY m.sort_order, m.name, t.name
        ")->fetchAll(PDO::FETCH_OBJ),

        'hiddenWithMembers' => $db->query("
            SELECT t.id AS team_id, t.name AS team_name,
                   COUNT(up.user_id) AS member_count
            FROM team t
            JOIN user_properties up ON up.parameter = CONCAT('team_', t.id)
            JOIN users u ON u.id = up.user_id AND u.status = 1
            WHERE t.hidden = 1
            GROUP BY t.id, t.name
            ORDER BY t.name
        ")->fetchAll(PDO::FETCH_OBJ),

        'dateInvalid' => $db->query("
            SELECT c.id, c.date, c.user_id, u.firstname, u.lastname, c.libele
            FROM compta c
            LEFT JOIN users u ON u.id = c.user_id
            WHERE c.date = 0 OR c.date > UNIX_TIMESTAMP()
            ORDER BY c.id DESC
            LIMIT 100
        ")->fetchAll(PDO::FETCH_OBJ),

        'typeNull' => $db->query("
            SELECT c.id, c.user_id, u.firstname, u.lastname, c.libele, c.sum
            FROM compta c
            LEFT JOIN users u ON u.id = c.user_id
            WHERE c.type_id IS NULL
            ORDER BY c.id DESC
            LIMIT 100
        ")->fetchAll(PDO::FETCH_OBJ),

        'emailInvalid' => $db->query("
            SELECT id, firstname, lastname, email
            FROM users
            WHERE status=1 AND TRIM(email) != '' AND email NOT LIKE '%@%'
            ORDER BY lastname, firstname
        ")->fetchAll(PDO::FETCH_OBJ),

        'sexeInvalid' => $db->query("
            SELECT id, firstname, lastname, sexe
            FROM users
            WHERE status=1 AND sexe NOT IN ('na','hf','f','m')
            ORDER BY lastname, firstname
        ")->fetchAll(PDO::FETCH_OBJ),

        'noName' => $db->query("
            SELECT id, firstname, lastname, society
            FROM users
            WHERE status=1 AND TRIM(lastname) = '' AND TRIM(society) = ''
            ORDER BY id
        ")->fetchAll(PDO::FETCH_OBJ),

        'emailAltInvalid' => $db->query("
            SELECT id, firstname, lastname, email_alt
            FROM users
            WHERE status=1 AND TRIM(email_alt) != '' AND email_alt NOT LIKE '%@%'
            ORDER BY lastname, firstname
        ")->fetchAll(PDO::FETCH_OBJ),

        'birthdayFuture' => $db->query("
            SELECT id, firstname, lastname, birthday
            FROM users
            WHERE status=1 AND birthday > 0 AND birthday > UNIX_TIMESTAMP()
            ORDER BY lastname, firstname
        ")->fetchAll(PDO::FETCH_OBJ),
    ];
}
