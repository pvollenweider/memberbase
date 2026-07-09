<?php
/**
 * /api/segments — REST endpoint for segments (groups).
 *
 * GET    /api/segments                        list all segments with member count
 * GET    /api/segments/{id}                   single segment
 * GET    /api/segments/{id}/members           members of a segment
 * POST   /api/segments                        create segment (manager)
 * PUT    /api/segments/{id}                   rename / toggle hidden (manager)
 * DELETE /api/segments/{id}                   delete segment (manager, only if empty)
 * POST   /api/segments/{id}/members           add a member to a segment (manager)
 * DELETE /api/segments/{id}/members           remove a member from a segment (manager)
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../classes/segment_class.php';
require_once __DIR__ . '/../classes/user_class.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$sub    = $_GET['sub'] ?? null; // 'members' for /api/segments/{id}/members

// Subquery: resolves the non-filter category (is_filter=0) for each segment.
define('CAT_JOIN',
    "LEFT JOIN (
        SELECT j.segmentid, c.id, c.name, c.sort_order
        FROM metagroup j
        JOIN metagroup c ON c.id = j.id AND c.name IS NOT NULL AND c.is_filter = 0
        WHERE j.segmentid IS NOT NULL
        GROUP BY j.segmentid
    ) cat ON cat.segmentid = t.id"
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

function loadSegment(int $id): Segment
{
    $segment = new Segment();
    try {
        $segment->lookupSegment($id);
    } catch (\RuntimeException) {
        apiError(404, 'Segment not found');
    }
    return $segment;
}

function segmentToArray(object $row): array
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
    if (!canRead()) apiError(403, 'Forbidden');

    $stmt = db()->query(
        "SELECT t.id, t.name, t.hidden,
                COUNT(us.user_id) AS member_count,
                cat.id AS cat_id, cat.name AS cat_name
         FROM segment t
         LEFT JOIN contact_segment us ON us.segment_id = t.id
         LEFT JOIN contact u ON u.id = us.user_id AND u.status = 1
         " . CAT_JOIN . "
         GROUP BY t.id, t.name, t.hidden, cat.id, cat.name, cat.sort_order
         ORDER BY COALESCE(cat.sort_order, 99999) ASC,
                  COALESCE(cat.name, 'ZZZZ') ASC,
                  t.hidden ASC, t.name ASC"
    );

    echo json_encode(
        ['data' => array_map('segmentToArray', $stmt->fetchAll())],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}

function handleGet(int $id): void
{
    if (!canRead()) apiError(403, 'Forbidden');

    $stmt = db()->prepare(
        "SELECT t.id, t.name, t.hidden,
                COUNT(us.user_id) AS member_count,
                cat.id AS cat_id, cat.name AS cat_name
         FROM segment t
         LEFT JOIN contact_segment us ON us.segment_id = t.id
         LEFT JOIN contact u ON u.id = us.user_id AND u.status = 1
         " . CAT_JOIN . "
         WHERE t.id = ?
         GROUP BY t.id, t.name, t.hidden, cat.id, cat.name, cat.sort_order"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetchObject();
    if (!$row) apiError(404, 'Segment not found');

    echo json_encode(['data' => segmentToArray($row)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function handleListMembers(int $id): void
{
    if (!canRead()) apiError(403, 'Forbidden');

    $stmt = db()->prepare("SELECT id FROM segment WHERE id=? LIMIT 1");
    $stmt->execute([$id]);
    if (!$stmt->fetchColumn()) apiError(404, 'Segment not found');

    $stmt = db()->prepare(
        "SELECT u.id, u.firstname, u.lastname, u.email, u.npa
         FROM contact u
         JOIN contact_segment us ON us.user_id = u.id AND us.segment_id = ?
         WHERE u.status = 1
         ORDER BY u.lastname ASC, u.firstname ASC"
    );
    $stmt->execute([$id]);

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
    if (!isManager()) apiError(403, 'Manager role required');

    $body = requestBody();
    $name = trim((string)($body['name'] ?? ''));
    if ($name === '') apiError(422, 'name is required');

    $segment = new Segment();
    $segment->setName(unquote($name));
    $segment->setHidden((bool)($body['hidden'] ?? false));
    $segment->save();

    auditLog(db(), 'addSegment', "name: $name");

    $stmt = db()->prepare("SELECT t.id, t.name, t.hidden, 0 AS member_count, NULL AS cat_id, NULL AS cat_name FROM segment t WHERE t.id=?");
    $stmt->execute([$segment->getId()]);

    http_response_code(201);
    echo json_encode(['data' => segmentToArray($stmt->fetchObject())], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function handleUpdate(int $id): void
{
    if (!isManager()) apiError(403, 'Manager role required');

    $segment = loadSegment($id);
    $body = requestBody();

    if (array_key_exists('name',   $body)) $segment->setName(unquote(trim((string)$body['name'])));
    if (array_key_exists('hidden', $body)) $segment->setHidden((bool)$body['hidden']);
    $segment->save();

    auditLog(db(), 'renameSegment', "id=$id | name: {$segment->getName()}");

    $stmt = db()->prepare(
        "SELECT t.id, t.name, t.hidden,
                COUNT(us.user_id) AS member_count,
                cat.id AS cat_id, cat.name AS cat_name
         FROM segment t
         LEFT JOIN contact_segment us ON us.segment_id = t.id
         LEFT JOIN contact u ON u.id = us.user_id AND u.status = 1
         " . CAT_JOIN . "
         WHERE t.id = ?
         GROUP BY t.id, t.name, t.hidden, cat.id, cat.name, cat.sort_order"
    );
    $stmt->execute([$id]);

    echo json_encode(['data' => segmentToArray($stmt->fetchObject())], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function handleDelete(int $id): void
{
    if (!isManager()) apiError(403, 'Manager role required');

    $segment = loadSegment($id);

    if ($segment->isUsed()) {
        apiError(409, 'Segment still has members — remove them first or use force=true');
    }

    auditLog(db(), 'deleteSegment', "id=$id | name: {$segment->getName()}");
    db()->prepare("DELETE FROM segment WHERE id=?")->execute([$id]);

    http_response_code(204);
}

function handleAddMember(int $groupId): void
{
    if (!isManager()) apiError(403, 'Manager role required');

    $body     = requestBody();
    $memberId = isset($body['memberId']) ? (int)$body['memberId'] : 0;
    if (!$memberId) apiError(422, 'memberId is required');

    $chkGroup = db()->prepare("SELECT id FROM segment WHERE id=? LIMIT 1");
    $chkGroup->execute([$groupId]);
    if (!$chkGroup->fetchColumn()) apiError(404, 'Segment not found');

    $chkUser = db()->prepare("SELECT id FROM contact WHERE id=? AND status=1 LIMIT 1");
    $chkUser->execute([$memberId]);
    if (!$chkUser->fetchColumn()) apiError(422, "Member #$memberId not found");

    db()->prepare("INSERT IGNORE INTO contact_segment (user_id, segment_id) VALUES (?, ?)")
        ->execute([$memberId, $groupId]);

    $_auU = db()->prepare("SELECT CONCAT(firstname,' ',lastname) FROM contact WHERE id=?");
    $_auU->execute([$memberId]);
    $_auName = trim((string)$_auU->fetchColumn());
    auditLog(db(), 'assignSegment', "group_id=$groupId | membre #$memberId" . ($_auName ? ": $_auName" : ''), $memberId);

    http_response_code(204);
}

function handleRemoveMember(int $groupId): void
{
    if (!isManager()) apiError(403, 'Manager role required');

    $body     = requestBody();
    $memberId = isset($body['memberId']) ? (int)$body['memberId'] : 0;
    if (!$memberId) apiError(422, 'memberId is required');

    db()->prepare("DELETE FROM contact_segment WHERE user_id=? AND segment_id=?")
        ->execute([$memberId, $groupId]);

    $_auU = db()->prepare("SELECT CONCAT(firstname,' ',lastname) FROM contact WHERE id=?");
    $_auU->execute([$memberId]);
    $_auName = trim((string)$_auU->fetchColumn());
    auditLog(db(), 'unassignSegment', "group_id=$groupId | membre #$memberId" . ($_auName ? ": $_auName" : ''), $memberId);

    http_response_code(204);
}
