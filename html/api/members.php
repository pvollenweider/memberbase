<?php
/**
 * /api/members — REST endpoint for member records.
 *
 * GET    /api/members          list (paginated, optional ?search=)
 * GET    /api/members/{id}     single member detail
 * POST   /api/members          create a member
 * PUT    /api/members/{id}     update a member
 * DELETE /api/members/{id}     deactivate (default) or delete (?dispose=delete, admin only)
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../classes/user_class.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$sub    = $_GET['sub'] ?? null;

match (true) {
    $method === 'GET'    && $id === null                      => handleList(),
    $method === 'GET'    && $id !== null && $sub === 'groups' => handleGetGroups($id),
    $method === 'GET'    && $id !== null                      => handleGet($id),
    $method === 'POST'   && $id === null                      => handleCreate(),
    ($method === 'PUT' || $method === 'PATCH') && $id !== null => handleUpdate($id),
    $method === 'DELETE' && $id !== null                      => handleDelete($id),
    default                                                   => apiError(405, 'Method Not Allowed'),
};

// ── helpers ──────────────────────────────────────────────────────────────────

function memberToArray(User $u): array
{
    return [
        'id'               => (int)$u->getId(),
        'lastName'         => (string)$u->getLastName(),
        'firstName'        => (string)$u->getFirstName(),
        'society'          => $u->getSociety()  ?: null,
        'gender'           => $u->getSexe()     ?: null,
        'title'            => $u->getTitle()    ?: null,
        'address'          => $u->getAddress()  ?: null,
        'npa'              => $u->getNpa()      ?: null,
        'email'            => $u->getEmail()    ?: null,
        'tel'              => $u->getTel()      ?: null,
        'telProf'          => $u->getTelProf()  ?: null,
        'portable'         => $u->getPortable() ?: null,
        'fax'              => $u->getFax()      ?: null,
        'web'              => $u->getWeb()      ?: null,
        'birthDate'        => $u->getBirthDay() ? date('Y-m-d', (int)$u->getBirthDay()) : null,
        'comment'          => $u->getComment()  ?: null,
        'createdAt'        => $u->getCreationDate()     ? date('c', (int)$u->getCreationDate())     : null,
        'updatedAt'        => $u->getModificationDate() ? date('c', (int)$u->getModificationDate()) : null,
    ];
}

/** Reads and validates a JSON request body. Returns the decoded array. */
function requestBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '') return [];
    $data = json_decode($raw, true);
    if (!is_array($data)) apiError(400, 'Invalid JSON body');
    return $data;
}

/** Applies allowed string fields from $body onto $user. */
function applyFields(User $user, array $body): void
{
    $allowed = ['firstName','lastName','society','gender','title','address','npa',
                'email','tel','telProf','portable','fax','web','comment'];
    foreach ($allowed as $field) {
        if (!array_key_exists($field, $body)) continue;
        $val = (string)$body[$field];
        match ($field) {
            'gender'  => $user->setSexe(in_array($val, ['m','f','hf','na']) ? $val : 'na'),
            default   => $user->{'set' . ucfirst($field)}(unquote($val)),
        };
    }
    if (array_key_exists('birthDate', $body)) {
        $ts = $body['birthDate'] ? strtotime((string)$body['birthDate']) : 0;
        $user->birthDay = $ts ?: 0;
    }
}

// ── handlers ─────────────────────────────────────────────────────────────────

function handleList(): void
{
    global $pdo;

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
                users.id, users.firstname, users.lastname, users.society,
                users.email, users.npa, users.address, users.sexe
            FROM users $where
            ORDER BY users.lastname ASC, users.firstname ASC
            LIMIT ? OFFSET ?";

    $stmt = $pdo->prepare($sql);
    $i = 1;
    foreach ($params as $val) { $stmt->bindValue($i++, $val); }
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
        'meta' => ['page' => $page, 'limit' => $limit, 'total' => $total],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function handleGet(int $id): void
{
    $user = new User();
    $user->lookupUser($id);
    if (!$user->getId()) apiError(404, 'Member not found');

    echo json_encode(['data' => memberToArray($user)],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function handleCreate(): void
{
    global $pdo;
    if (!canWrite()) apiError(403, 'Forbidden');
    $body = requestBody();

    if (empty(trim((string)($body['lastName'] ?? '')))) {
        apiError(422, 'lastName is required');
    }

    $user = new User();
    applyFields($user, $body);

    $newId = $user->save();
    auditLog($pdo, 'addUser', "id=$newId | {$user->firstName} {$user->lastName} | email: {$user->email}", $newId);

    $user->lookupUser($newId);
    http_response_code(201);
    echo json_encode(['data' => memberToArray($user)],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function handleUpdate(int $id): void
{
    global $pdo;
    if (!canWrite()) apiError(403, 'Forbidden');
    $body = requestBody();

    $user = new User();
    $user->lookupUser($id);
    if (!$user->getId()) apiError(404, 'Member not found');

    $before = memberToArray($user);
    applyFields($user, $body);
    $user->save();
    $after  = memberToArray($user);

    $diffs = [];
    foreach (array_keys($body) as $k) {
        $bval = (string)($before[$k] ?? '');
        $aval = (string)($after[$k]  ?? '');
        if ($bval !== $aval) {
            $diffs[] = "$k: «$bval» → «$aval»";
        }
    }
    $detail = "id=$id | {$user->firstName} {$user->lastName}";
    $detail .= $diffs ? ' | ' . implode(' ; ', $diffs) : ' | (aucune modification)';
    auditLog($pdo, 'updateUser', $detail, $id);

    $user->lookupUser($id);
    echo json_encode(['data' => memberToArray($user)],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function handleDelete(int $id): void
{
    global $pdo;

    $user = new User();
    $user->lookupUser($id);
    if (!$user->getId()) apiError(404, 'Member not found');

    $dispose = trim($_GET['dispose'] ?? 'deactivate');

    if ($dispose === 'delete') {
        if (!isAdmin()) apiError(403, 'Admin role required to permanently delete a member');
        auditLog($pdo, 'deleteUser', "id=$id | {$user->firstName} {$user->lastName}", $id);
        $user->remove();
    } else {
        $pdo->prepare("UPDATE users SET status=0 WHERE id=?")->execute([$id]);
        auditLog($pdo, 'deactivateUser', "id=$id | {$user->firstName} {$user->lastName}", $id);
    }

    http_response_code(204);
}

function handleGetGroups(int $id): void
{
    global $pdo;

    $chk = $pdo->prepare("SELECT id FROM users WHERE id=? AND status=1 LIMIT 1");
    $chk->execute([$id]);
    if (!$chk->fetchColumn()) apiError(404, 'Member not found');

    $stmt = $pdo->prepare(
        "SELECT t.id, t.name, t.hidden,
                cat.id AS cat_id, cat.name AS cat_name
         FROM team t
         JOIN user_properties up ON up.parameter = CONCAT('team_', t.id) AND up.user_id = ?
         LEFT JOIN (
             SELECT j.teamid, c.id, c.name, c.sort_order
             FROM metagroup j
             JOIN metagroup c ON c.id = j.id AND c.name IS NOT NULL AND c.is_filter = 0
             WHERE j.teamid IS NOT NULL
             GROUP BY j.teamid
         ) cat ON cat.teamid = t.id
         ORDER BY COALESCE(cat.sort_order, 99999) ASC,
                  COALESCE(cat.name, 'ZZZZ') ASC,
                  t.name ASC"
    );
    $stmt->execute([$id]);

    $data = array_map(fn($r) => [
        'id'           => (int)$r->id,
        'name'         => $r->name,
        'hidden'       => (bool)$r->hidden,
        'categoryId'   => ($r->cat_id)   ? (int)$r->cat_id   : null,
        'categoryName' => ($r->cat_name) ? $r->cat_name       : null,
    ], $stmt->fetchAll());

    echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
