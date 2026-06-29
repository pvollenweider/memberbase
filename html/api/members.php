<?php
/**
 * GET /api/members — paginated member list with optional search.
 *
 * Query params:
 *   search  string  filter by name / email / npa / address / society
 *   page    int     1-based page number (default 1)
 *   limit   int     results per page, max 100 (default 25)
 *
 * Response:
 *   { "data": [...], "meta": { "page", "limit", "total" } }
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError(405, 'Method Not Allowed');
}

$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page']  ?? 1));
$limit  = min(100, max(1, (int)($_GET['limit'] ?? 25)));
$offset = ($page - 1) * $limit;

$where  = 'WHERE users.status = 1';
$params = [];

if ($search !== '') {
    $like    = '%' . $search . '%';
    $where  .= ' AND (
        users.firstname LIKE ? OR users.lastname LIKE ?
        OR CONCAT(users.firstname, " ", users.lastname) LIKE ?
        OR CONCAT(users.lastname,  " ", users.firstname) LIKE ?
        OR users.society LIKE ? OR users.npa LIKE ?
        OR users.email   LIKE ? OR users.address LIKE ?
    )';
    $params = array_fill(0, 8, $like);
}

$stmtCount = $pdo->prepare("SELECT COUNT(DISTINCT users.id) FROM users $where");
$stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();

$sql = "SELECT DISTINCT
            users.id,
            users.firstname,
            users.lastname,
            users.society,
            users.email,
            users.npa,
            users.address,
            users.sexe
        FROM users
        $where
        ORDER BY users.lastname ASC, users.firstname ASC
        LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($sql);
$i = 1;
foreach ($params as $val) {
    $stmt->bindValue($i++, $val);
}
$stmt->bindValue($i++, $limit,  PDO::PARAM_INT);
$stmt->bindValue($i,   $offset, PDO::PARAM_INT);
$stmt->execute();

$data = array_map(fn($r) => [
    'id'        => (int)$r->id,
    'lastName'  => $r->lastname,
    'firstName' => $r->firstname,
    'society'   => $r->society ?: null,
    'email'     => $r->email   ?: null,
    'npa'       => $r->npa     ?: null,
    'address'   => $r->address ?: null,
], $stmt->fetchAll());

echo json_encode([
    'data' => $data,
    'meta' => [
        'page'  => $page,
        'limit' => $limit,
        'total' => $total,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
