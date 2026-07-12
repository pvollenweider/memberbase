<?php
/**
 * Contact type classification (issue #165) — private donor vs institution vs
 * financial institution vs company. Backed by the `contact_type` lookup
 * table (id, code, label): `code` is the stable key the classification rule
 * (mbClassifyContactTypeRow(), pure.php) depends on, `label` is
 * admin-editable and never used for logic.
 *
 * Classification is suggested, never applied automatically:
 * mbSuggestContactTypes() returns one row per active contact (current +
 * suggested contact_type_id) for a review UI; the caller decides which
 * suggestions to write via mbApplyContactTypes().
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

/** Validates a contact_type.id, falling back to 1 ('private') if unknown. */
function mbValidContactTypeId(PDO $db, int $id): int
{
    $validIds = array_map('intval', array_column($db->query("SELECT id FROM contact_type")->fetchAll(PDO::FETCH_OBJ), 'id'));
    return in_array($id, $validIds, true) ? $id : 1;
}

/** @return array<string,int> code => contact_type.id */
function mbContactTypeIdsByCode(PDO $db): array
{
    $rows = $db->query("SELECT id, code FROM contact_type")->fetchAll(PDO::FETCH_OBJ);
    $map = [];
    foreach ($rows as $row) {
        $map[$row->code] = (int)$row->id;
    }
    return $map;
}

/**
 * @param PDO $db
 * @return object[] One row per active contact: id, firstname, lastname,
 *                   society, current_type_id, current_label, suggested_code,
 *                   suggested_type_id, suggested_label.
 */
function mbSuggestContactTypes(PDO $db): array
{
    $typeIdsByCode = mbContactTypeIdsByCode($db);
    $labelsByCode  = [];
    foreach ($db->query("SELECT code, label FROM contact_type")->fetchAll(PDO::FETCH_OBJ) as $row) {
        $labelsByCode[$row->code] = $row->label;
    }

    $rows = $db->query("
        SELECT u.id, u.firstname, u.lastname, u.society,
               u.contact_type_id AS current_type_id, uct.code AS current_code, uct.label AS current_label,
               MAX(ct.is_institutional)         AS has_institutional,
               MAX(ct.is_financial_institution) AS has_financial,
               MAX(ct.is_company)               AS has_company
        FROM contact u
        JOIN contact_type uct ON uct.id = u.contact_type_id
        LEFT JOIN compta c ON c.user_id = u.id
        LEFT JOIN compta_type ct ON ct.id = c.type_id
        WHERE u.status = 1
        GROUP BY u.id, u.firstname, u.lastname, u.society, u.contact_type_id, uct.code, uct.label
        ORDER BY u.lastname, u.firstname, u.society
    ")->fetchAll(PDO::FETCH_OBJ);

    foreach ($rows as $row) {
        $suggestedCode = mbClassifyContactTypeRow(
            (bool)$row->has_institutional,
            (bool)$row->has_financial,
            (bool)$row->has_company,
            trim((string)$row->society) !== ''
        );
        $row->suggested_code    = $suggestedCode;
        $row->suggested_type_id = $typeIdsByCode[$suggestedCode] ?? $typeIdsByCode[CONTACT_TYPE_PRIVATE];
        $row->suggested_label   = $labelsByCode[$suggestedCode] ?? $suggestedCode;
    }

    return $rows;
}

/**
 * Applies suggested contact_type_id for the given contact IDs (only — no
 * bulk "apply to all" without an explicit list, so the review UI stays the
 * single source of truth for what gets written).
 *
 * @param PDO $db
 * @param array<int,int> $typeIdByUserId user_id => contact_type_id
 */
function mbApplyContactTypes(PDO $db, array $typeIdByUserId): int
{
    $validIds = array_map('intval', array_column($db->query("SELECT id FROM contact_type")->fetchAll(PDO::FETCH_OBJ), 'id'));
    $stmt = $db->prepare("UPDATE contact SET contact_type_id = ? WHERE id = ?");
    $applied = 0;
    foreach ($typeIdByUserId as $userId => $typeId) {
        if (!in_array((int)$typeId, $validIds, true)) {
            continue;
        }
        $stmt->execute([(int)$typeId, (int)$userId]);
        $applied++;
    }
    return $applied;
}
