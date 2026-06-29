<?php
/**
 * /api/groups — REST endpoint for groups (teams).
 *
 * GET    /api/groups                        list all groups with member count
 * GET    /api/groups/{id}                   single group
 * GET    /api/groups/{id}/members           members of a group
 * POST   /api/groups                        create group (manager)
 * PUT    /api/groups/{id}                   rename / toggle hidden (manager)
 * DELETE /api/groups/{id}                   delete group (manager, only if empty)
 * POST   /api/groups/{id}/members           add a member to a group (manager)
 * DELETE /api/groups/{id}/members           remove a member from a group (manager)
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../classes/team_class.php';
require_once __DIR__ . '/../classes/user_class.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$sub    = $_GET['sub'] ?? null; // 'members' for /api/groups/{id}/members

// Subquery: resolves the non-filter category (is_filter=0) for each team.
define('CAT_JOIN',
    "LEFT JOIN (
        SELECT j.teamid, c.id, c.name, c.sort_order
        FROM metagroup j
        JOIN metagroup c ON c.id = j.id AND c.name IS NOT NULL AND c.is_filter = 0
        WHERE j.teamid IS NOT NULL
        GROUP BY j.teamid
    ) cat ON cat.teamid = t.id"
);

match (true) {
    $method === 'GET'    && $id === null && $sub === null  => handleList(),
    $method === 'GET'    && $id !== null && $sub === null  => handleGet($id),
    $method === 'GET'    && $id !== null && $sub === 'members' => handleListMembers($id),
    $method === 'POST'   && $id === null && $sub === null  => handleCreate(),
    $method === 'PUT'    && $id !== null && $sub === null  => handleUpdate($id),
    $method === 'DELETE' && $id !== null && $sub === null  => handleDelete($id),
    $method === 'POST'   && $id !== null && $sub === 'members' => handleAddMember($id),
    $method === 'DELETE' && $id !== null && $sub === 'members' => handleRemoveMember($id),
    default                              => apiError(405, 'Method Not Allowed'),
};

// ── helpers ──────────────────────────────────────────────────────────────────

function requestBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '') return [];
    $data = json_decode($raw, true);
    if (!is_array($data)) apiError(400, 'Invalid JSON body');
    return $data;
}

function loadGroup(int $id): Team
{
    $team = new Team();
    try {
        $team->lookupTeam($id);
    } catch (\RuntimeException) {
        apiError(404, 'Group not found');
    }
    return $team;
}

function groupToArray(object $row): array
{
    return [
        'id'           => (int)$row->id,
        'name'         => $row->name,
        'hidden'       => (bool)$row->hidden,
        'memberCount'  => isset($row->member_count) ? (int)$row->member_count : null,
        'categoryId'   => (isset($row->cat_id)   && $row->cat_id)   ? (int)$row->cat_id   : null,
        'categoryName' => (isset($row->cat_name) && $row->cat_name) ? $row->cat_name       : null,
    ];
}


// ── handlers ─────────────────────────────────────────────────────────────────

function handleList(): void
{
    global $pdo;

    $stmt = $pdo->query(
        "SELECT t.id, t.name, t.hidden,
                COUNT(up.user_id) AS member_count,
                cat.id AS cat_id, cat.name AS cat_name
         FROM team t
         LEFT JOIN user_properties up ON up.parameter = CONCAT('team_', t.id)
         LEFT JOIN users u ON u.id = up.user_id AND u.status = 1
         " . CAT_JOIN . "
         GROUP BY t.id, t.name, t.hidden, cat.id, cat.name, cat.sort_order
         ORDER BY COALESCE(cat.sort_order, 99999) ASC,
                  COALESCE(cat.name, 'ZZZZ') ASC,
                  t.hidden ASC, t.name ASC"
    );

    echo json_encode(
        ['data' => array_map('groupToArray', $stmt->fetchAll())],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}

function handleGet(int $id): void
{
    global $pdo;

    $stmt = $pdo->prepare(
        "SELECT t.id, t.name, t.hidden,
                COUNT(up.user_id) AS member_count,
                cat.id AS cat_id, cat.name AS cat_name
         FROM team t
         LEFT JOIN user_properties up ON up.parameter = CONCAT('team_', t.id)
         LEFT JOIN users u ON u.id = up.user_id AND u.status = 1
         " . CAT_JOIN . "
         WHERE t.id = ?
         GROUP BY t.id, t.name, t.hidden, cat.id, cat.name, cat.sort_order"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetchObject();
    if (!$row) apiError(404, 'Group not found');

    echo json_encode(['data' => groupToArray($row)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function handleListMembers(int $id): void
{
    global $pdo;

    $stmt = $pdo->prepare("SELECT id FROM team WHERE id=? LIMIT 1");
    $stmt->execute([$id]);
    if (!$stmt->fetchColumn()) apiError(404, 'Group not found');

    $stmt = $pdo->prepare(
        "SELECT u.id, u.firstname, u.lastname, u.email, u.npa
         FROM users u
         JOIN user_properties up ON up.user_id = u.id AND up.parameter = ?
         WHERE u.status = 1
         ORDER BY u.lastname ASC, u.firstname ASC"
    );
    $stmt->execute(["team_$id"]);

    $data = array_map(fn($r) => [
        'id'        => (int)$r->id,
        'lastName'  => $r->lastname,
        'firstName' => $r->firstname,
        'email'     => $r->email  ?: null,
        'npa'       => $r->npa    ?: null,
    ], $stmt->fetchAll());

    echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function handleCreate(): void
{
    global $pdo;
    if (!isManager()) apiError(403, 'Manager role required');

    $body = requestBody();
    $name = trim((string)($body['name'] ?? ''));
    if ($name === '') apiError(422, 'name is required');

    $team = new Team();
    $team->setName(unquote($name));
    $team->setHidden((bool)($body['hidden'] ?? false));
    $team->save();

    auditLog($pdo, 'addTeam', "name: $name");

    $stmt = $pdo->prepare("SELECT t.id, t.name, t.hidden, 0 AS member_count, NULL AS cat_id, NULL AS cat_name FROM team t WHERE t.id=?");
    $stmt->execute([$team->getId()]);

    http_response_code(201);
    echo json_encode(['data' => groupToArray($stmt->fetchObject())], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function handleUpdate(int $id): void
{
    global $pdo;
    if (!isManager()) apiError(403, 'Manager role required');

    $team = loadGroup($id);
    $body = requestBody();

    if (array_key_exists('name',   $body)) $team->setName(unquote(trim((string)$body['name'])));
    if (array_key_exists('hidden', $body)) $team->setHidden((bool)$body['hidden']);
    $team->save();

    auditLog($pdo, 'renameTeam', "id=$id | name: {$team->getName()}");

    $stmt = $pdo->prepare(
        "SELECT t.id, t.name, t.hidden,
                COUNT(up.user_id) AS member_count,
                cat.id AS cat_id, cat.name AS cat_name
         FROM team t
         LEFT JOIN user_properties up ON up.parameter = CONCAT('team_', t.id)
         LEFT JOIN users u ON u.id = up.user_id AND u.status = 1
         " . CAT_JOIN . "
         WHERE t.id = ?
         GROUP BY t.id, t.name, t.hidden, cat.id, cat.name, cat.sort_order"
    );
    $stmt->execute([$id]);

    echo json_encode(['data' => groupToArray($stmt->fetchObject())], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function handleDelete(int $id): void
{
    global $pdo;
    if (!isManager()) apiError(403, 'Manager role required');

    $team = loadGroup($id);

    if ($team->isUsed()) {
        apiError(409, 'Group still has members — remove them first or use force=true');
    }

    auditLog($pdo, 'deleteTeam', "id=$id | name: {$team->getName()}");
    $pdo->prepare("DELETE FROM team WHERE id=?")->execute([$id]);

    http_response_code(204);
}

function handleAddMember(int $groupId): void
{
    global $pdo;
    if (!isManager()) apiError(403, 'Manager role required');

    $body     = requestBody();
    $memberId = isset($body['memberId']) ? (int)$body['memberId'] : 0;
    if (!$memberId) apiError(422, 'memberId is required');

    $chkGroup = $pdo->prepare("SELECT id FROM team WHERE id=? LIMIT 1");
    $chkGroup->execute([$groupId]);
    if (!$chkGroup->fetchColumn()) apiError(404, 'Group not found');

    $chkUser = $pdo->prepare("SELECT id FROM users WHERE id=? AND status=1 LIMIT 1");
    $chkUser->execute([$memberId]);
    if (!$chkUser->fetchColumn()) apiError(422, "Member #$memberId not found");

    $pdo->prepare("INSERT IGNORE INTO user_properties (user_id, parameter, value) VALUES (?, ?, 'true')")
        ->execute([$memberId, "team_$groupId"]);

    $_auU = $pdo->prepare("SELECT CONCAT(firstname,' ',lastname) FROM users WHERE id=?");
    $_auU->execute([$memberId]);
    $_auName = trim((string)$_auU->fetchColumn());
    auditLog($pdo, 'addMembership', "group_id=$groupId | membre #$memberId" . ($_auName ? ": $_auName" : ''), $memberId);

    http_response_code(204);
}

function handleRemoveMember(int $groupId): void
{
    global $pdo;
    if (!isManager()) apiError(403, 'Manager role required');

    $body     = requestBody();
    $memberId = isset($body['memberId']) ? (int)$body['memberId'] : 0;
    if (!$memberId) apiError(422, 'memberId is required');

    $pdo->prepare("DELETE FROM user_properties WHERE user_id=? AND parameter=?")
        ->execute([$memberId, "team_$groupId"]);

    $_auU = $pdo->prepare("SELECT CONCAT(firstname,' ',lastname) FROM users WHERE id=?");
    $_auU->execute([$memberId]);
    $_auName = trim((string)$_auU->fetchColumn());
    auditLog($pdo, 'removeMembership', "group_id=$groupId | membre #$memberId" . ($_auName ? ": $_auName" : ''), $memberId);

    http_response_code(204);
}
