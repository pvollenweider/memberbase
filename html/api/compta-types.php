<?php
/**
 * /api/compta-types — read-only list of accounting types.
 *
 * GET /api/compta-types   returns all types ordered by sort_order, label
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError(405, 'Method Not Allowed');
}
if (!canRead()) apiError(403, 'Forbidden');

global $comptaTypes;

$data = array_values(array_map(fn($ct) => [
    'id'                   => (int)$ct->id,
    'label'                => $ct->label,
    'color'                => $ct->color ?: null,
    'sortOrder'            => (int)$ct->sort_order,
    'isCotisation'         => (bool)$ct->is_cotisation,
    'isExcludedFromDonation' => (bool)$ct->is_excluded_from_donation,
], $comptaTypes));

echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
