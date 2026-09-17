<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Action handler: per compta_type yearly budget figures (Finances > Budgets).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

$action = $_REQUEST['action'];

if ($action === 'updateComptaBudget') {
    // Auto-save: fired on blur/change of one budget input on the Budgets page.
    if (!isManager()) { http_response_code(403); exit; }
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');

    $comptaTypeId = (int)($_REQUEST['compta_type_id'] ?? 0);
    $year         = (int)($_REQUEST['year'] ?? 0);
    $amount       = isset($_REQUEST['amount']) ? (float)$_REQUEST['amount'] : 0.0;

    // Past years are display-only on the Budgets page (no input rendered) —
    // reject direct edits server-side too, not just by omitting the field.
    if ($comptaTypeId <= 0 || $year < (int)date('Y') || $year > 2100 || $amount < 0) {
        echo json_encode(['ok' => false, 'error' => 'invalid_params']);
        exit;
    }

    db()->prepare(
        "INSERT INTO compta_budget (compta_type_id, year, amount) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE amount = VALUES(amount)"
    )->execute([$comptaTypeId, $year, $amount]);
    auditLog(db(), 'updateComptaBudget', "compta_type_id=$comptaTypeId | year=$year | amount=$amount");

    echo json_encode(['ok' => true]);
    exit;
}
