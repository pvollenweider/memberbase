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
require_once __DIR__ . '/../classes/member_filter_class.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$sub    = $_GET['sub'] ?? null;

match (true) {
    $method === 'GET'    && $id === null                      => handleList(),
    $method === 'GET'    && $id !== null && $sub === 'groups' => handleGetGroups($id),
    $method === 'GET'    && $id !== null && $sub === 'compta' => handleGetCompta($id),
    $method === 'GET'    && $id !== null                      => handleGet($id),
    $method === 'POST'   && $id === null                      => handleCreate(),
    ($method === 'PUT' || $method === 'PATCH') && $id !== null => handleUpdate($id),
    $method === 'DELETE' && $id !== null                      => handleDelete($id),
    default                                                   => apiError(405, 'Method Not Allowed'),
};

// ── helpers ──────────────────────────────────────────────────────────────────

function memberFieldsForDiff(User $u): array
{
    return [
        'firstName' => (string)$u->getFirstName(),
        'lastName'  => (string)$u->getLastName(),
        'society'   => (string)$u->getSociety(),
        'gender'    => (string)$u->getSexe(),
        'title'     => (string)$u->getTitle(),
        'address'   => (string)$u->getAddress(),
        'npa'       => (string)$u->getNpa(),
        'email'     => (string)$u->getEmail(),
        'emailAlt'  => (string)$u->getEmailAlt(),
        'tel'       => (string)$u->getTel(),
        'telProf'   => (string)$u->getTelProf(),
        'portable'  => (string)$u->getPortable(),
        'fax'       => (string)$u->getFax(),
        'web'       => (string)$u->getWeb(),
        'birthDate' => $u->getBirthDay() ? date('Y-m-d', (int)$u->getBirthDay()) : '',
        'comment'   => (string)$u->getComment(),
    ];
}

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
        'emailAlt'         => $u->getEmailAlt() ?: null,
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
                'email','emailAlt','tel','telProf','portable','fax','web','comment'];
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

function emitMemberList(array $rows, int $page, int $limit, int $total, bool $includeTypes, array $groupsByUser = []): void
{
    $typesByUser = [];
    if ($includeTypes && !empty($rows)) {
        $ids = array_unique(array_map(fn($r) => (int)$r->id, $rows));
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $stT = db()->prepare("
            SELECT c.user_id, ct.id AS type_id, ct.label, ct.color
            FROM compta c
            JOIN compta_type ct ON ct.id = c.type_id
            WHERE c.user_id IN ($ph)
            GROUP BY c.user_id, ct.id, ct.label, ct.color
            ORDER BY ct.sort_order ASC, ct.label ASC
        ");
        $stT->execute($ids);
        foreach ($stT->fetchAll() as $tr) {
            $uid = (int)$tr->user_id;
            if (!isset($typesByUser[$uid])) $typesByUser[$uid] = [];
            $typesByUser[$uid][] = ['id' => (int)$tr->type_id, 'label' => $tr->label, 'color' => $tr->color ?: ''];
        }
    }
    $includeGroups = !empty($groupsByUser);
    $data = array_map(function ($r) use ($includeTypes, $typesByUser, $includeGroups, $groupsByUser) {
        $row = [
            'id'        => (int)$r->id,
            'lastName'  => $r->lastname,
            'firstName' => $r->firstname,
            'society'   => $r->society      ?: null,
            'email'     => $r->email        ?: null,
            'npa'       => $r->npa          ?: null,
            'address'   => $r->address      ?: null,
            'gender'    => $r->sexe         ?: null,
            'createdAt' => $r->creationDate ? date('c', (int)$r->creationDate) : null,
        ];
        if ($includeTypes) {
            $row['types'] = $typesByUser[(int)$r->id] ?? [];
        }
        if ($includeGroups) {
            $row['groups'] = $groupsByUser[(int)$r->id] ?? [];
        }
        return $row;
    }, $rows);
    echo json_encode(['data' => $data, 'meta' => ['page' => $page, 'limit' => $limit, 'total' => $total]],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function handleVirtualFilter(int $filterId, int $page, int $limit, int $offset, bool $includeTypes): void
{
    global $appSettings;

    $year = (int)date('Y');

    $baseSelect = "SELECT users.id, users.firstname, users.lastname, users.society,
                          users.email, users.npa, users.address, users.sexe, users.creationDate
                   FROM users";
    $orderBy    = "ORDER BY users.lastname ASC, users.firstname ASC";

    // All active members — no ID restriction needed
    if ($filterId === FILTER_ALL_EXCEPT_ARCHIVES) {
        $total = (int)db()->query("SELECT COUNT(*) FROM users WHERE status = 1")->fetchColumn();
        $stmt = db()->prepare("$baseSelect WHERE users.status = 1 $orderBy LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $limit,  PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        emitMemberList($stmt->fetchAll(), $page, $limit, $total, $includeTypes);
        return;
    }

    if (!in_array($filterId, MemberFilter::RESOLVABLE, true)) {
        apiError(400, 'Unknown virtual filter');
    }

    // Shared filter logic — same source of truth as the members list view
    $ids = array_keys(MemberFilter::resolveIds($filterId, db(), $year, $appSettings));
    $total = count($ids);

    if ($total === 0) {
        emitMemberList([], $page, $limit, 0, $includeTypes);
        return;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("$baseSelect WHERE users.id IN ($placeholders) $orderBy LIMIT ? OFFSET ?");
    $i = 1;
    foreach ($ids as $uid) { $stmt->bindValue($i++, $uid, PDO::PARAM_INT); }
    $stmt->bindValue($i++, $limit,  PDO::PARAM_INT);
    $stmt->bindValue($i,   $offset, PDO::PARAM_INT);
    $stmt->execute();

    emitMemberList($stmt->fetchAll(), $page, $limit, $total, $includeTypes);
}

function handleList(): void
{
    global $appSettings;
    if (!canRead()) apiError(403, 'Forbidden');

    $search       = trim($_GET['search'] ?? '');
    $segmentId    = isset($_GET['team']) ? (int)$_GET['team'] : null;
    $metagroupId  = isset($_GET['metagroup']) ? (int)$_GET['metagroup'] : null;
    $page         = max(1, (int)($_GET['page']  ?? 1));
    $limit        = min(2000, max(1, (int)($_GET['limit'] ?? 25)));
    $offset       = ($page - 1) * $limit;
    $includeTypes = !empty($_GET['types']);

    // Virtual filters: negative segmentId handled as SQL subqueries
    if ($segmentId !== null && $segmentId < 0) {
        handleVirtualFilter($segmentId, $page, $limit, $offset, $includeTypes);
        return;
    }

    $joins  = '';
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

    if ($segmentId !== null && $segmentId > 0) {
        $joins  .= ' JOIN user_segment up_t ON up_t.user_id = users.id AND up_t.segment_id = ?';
        // rebuild flat params list
        $params = array_merge(
            $search !== '' ? array_fill(0, 8, '%' . $search . '%') : [],
            [$segmentId]
        );
    }

    $mgSegmentIds = [];
    $mgParams     = [];

    if ($metagroupId !== null && $metagroupId > 0) {
        $stmtMg = db()->prepare("SELECT segmentid FROM metagroup WHERE id=? AND segmentid IS NOT NULL");
        $stmtMg->execute([$metagroupId]);
        $mgSegmentIds = $stmtMg->fetchAll(PDO::FETCH_COLUMN);
        if (empty($mgSegmentIds)) {
            echo json_encode(['data' => [], 'meta' => ['page' => 1, 'limit' => $limit, 'total' => 0]],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }
        $mgParams  = $mgSegmentIds;
        $mgPh      = implode(',', array_fill(0, count($mgParams), '?'));
        $joins    .= ' JOIN user_segment up_mg ON up_mg.user_id = users.id AND up_mg.segment_id IN (' . $mgPh . ')';
        $params    = array_merge($search !== '' ? array_fill(0, 8, '%' . $search . '%') : [], $mgParams);
    }

    $stmtCount = db()->prepare("SELECT COUNT(DISTINCT users.id) FROM users $joins $where");
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();

    $sql = "SELECT DISTINCT
                users.id, users.firstname, users.lastname, users.society,
                users.email, users.npa, users.address, users.sexe,
                users.creationDate
            FROM users $joins $where
            ORDER BY users.lastname ASC, users.firstname ASC
            LIMIT ? OFFSET ?";

    $stmt = db()->prepare($sql);
    $i = 1;
    foreach ($params as $val) { $stmt->bindValue($i++, $val); }
    $stmt->bindValue($i++, $limit,  PDO::PARAM_INT);
    $stmt->bindValue($i,   $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    // Pre-fetch metagroup segment membership for each result user
    $groupsByUser = [];
    if (!empty($mgSegmentIds) && !empty($rows)) {
        $resultIds    = array_map(fn($r) => (int)$r->id, $rows);
        // Fetch segment names
        $segIdPh      = implode(',', array_fill(0, count($mgSegmentIds), '?'));
        $stNames      = db()->prepare("SELECT id, name FROM segment WHERE id IN ($segIdPh)");
        $stNames->execute($mgSegmentIds);
        $segmentNames = array_column($stNames->fetchAll(PDO::FETCH_ASSOC), 'name', 'id');
        // Fetch which users are in which segments
        $userPh    = implode(',', array_fill(0, count($resultIds), '?'));
        $segPh     = implode(',', array_fill(0, count($mgParams), '?'));
        $stGrp     = db()->prepare("
            SELECT user_id, segment_id FROM user_segment
            WHERE user_id IN ($userPh) AND segment_id IN ($segPh)
            ORDER BY segment_id ASC
        ");
        $stGrp->execute(array_merge($resultIds, $mgParams));
        foreach ($stGrp->fetchAll() as $gr) {
            $uid = (int)$gr->user_id;
            $sid = (int)$gr->segment_id;
            if (!isset($groupsByUser[$uid])) $groupsByUser[$uid] = [];
            $groupsByUser[$uid][] = ['id' => $sid, 'name' => $segmentNames[$sid] ?? ''];
        }
    }

    emitMemberList($rows, $page, $limit, $total, $includeTypes, $groupsByUser);
}

function handleGet(int $id): void
{
    if (!canRead()) apiError(403, 'Forbidden');
    $user = new User();
    $user->lookupUser($id);
    if (!$user->getId()) apiError(404, 'Member not found');

    echo json_encode(['data' => memberToArray($user)],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function handleCreate(): void
{
    if (!canWrite()) apiError(403, 'Forbidden');
    $body = requestBody();

    if (empty(trim((string)($body['lastName'] ?? '')))) {
        apiError(422, 'lastName is required');
    }

    $user = new User();
    // Initialize all string fields to '' to avoid NOT NULL constraint on INSERT
    foreach (['firstName','lastName','society','title','address','npa','tel','telProf',
              'portable','fax','email','emailAlt','web','comment'] as $_f) {
        $user->{'set' . ucfirst($_f)}('');
    }
    $user->setSexe('na');
    $user->birthDay = 0;
    applyFields($user, $body);

    $newId = $user->save();
    auditLog(db(), 'addUser', "id=$newId | {$user->firstName} {$user->lastName} | email: {$user->email}", $newId);

    $user->lookupUser($newId);
    http_response_code(201);
    echo json_encode(['data' => memberToArray($user)],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function handleUpdate(int $id): void
{
    if (!canWrite()) apiError(403, 'Forbidden');
    $body = requestBody();

    $user = new User();
    $user->lookupUser($id);
    if (!$user->getId()) apiError(404, 'Member not found');

    $before = memberFieldsForDiff($user);
    applyFields($user, $body);
    $user->save();

    // Reload from DB for accurate after state
    $freshUser = new User();
    $freshUser->lookupUser($id);
    $after = memberFieldsForDiff($freshUser);

    $diffs = [];
    foreach (array_keys($body) as $k) {
        if (!array_key_exists($k, $before)) continue;
        $bval = $before[$k];
        $aval = $after[$k];
        if ($bval === $aval) continue;
        $diffs[] = "$k: «" . ($bval !== '' ? $bval : '∅') . "» → «" . ($aval !== '' ? $aval : '∅') . "»";
    }
    $detail = "id=$id | {$freshUser->getFirstName()} {$freshUser->getLastName()}";
    $detail .= $diffs ? ' | ' . implode(' ; ', $diffs) : ' | (aucune modification)';
    auditLog(db(), 'updateUser', $detail, $id);

    echo json_encode(['data' => memberToArray($freshUser)],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function handleDelete(int $id): void
{

    $user = new User();
    $user->lookupUser($id);
    if (!$user->getId()) apiError(404, 'Member not found');

    $dispose = trim($_GET['dispose'] ?? 'deactivate');

    if ($dispose === 'delete') {
        if (!isAdmin()) apiError(403, 'Admin role required to permanently delete a member');
        auditLog(db(), 'deleteUser', "id=$id | {$user->firstName} {$user->lastName}", $id);
        $user->remove();
    } else {
        db()->prepare("UPDATE users SET status=0 WHERE id=?")->execute([$id]);
        auditLog(db(), 'deactivateUser', "id=$id | {$user->firstName} {$user->lastName}", $id);
    }

    http_response_code(204);
}

function handleGetGroups(int $id): void
{
    if (!canRead()) apiError(403, 'Forbidden');

    $chk = db()->prepare("SELECT id FROM users WHERE id=? AND status=1 LIMIT 1");
    $chk->execute([$id]);
    if (!$chk->fetchColumn()) apiError(404, 'Member not found');

    $stmt = db()->prepare(
        "SELECT t.id, t.name, t.hidden,
                cat.id AS cat_id, cat.name AS cat_name
         FROM segment t
         JOIN user_segment us ON us.segment_id = t.id AND us.user_id = ?
         LEFT JOIN (
             SELECT j.segmentid, c.id, c.name, c.sort_order
             FROM metagroup j
             JOIN metagroup c ON c.id = j.id AND c.name IS NOT NULL AND c.is_filter = 0
             WHERE j.segmentid IS NOT NULL
             GROUP BY j.segmentid
         ) cat ON cat.segmentid = t.id
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

function handleGetCompta(int $id): void
{
    if (!canRead()) apiError(403, 'Forbidden');

    $chk = db()->prepare("SELECT id FROM users WHERE id=? AND status=1 LIMIT 1");
    $chk->execute([$id]);
    if (!$chk->fetchColumn()) apiError(404, 'Member not found');

    $stmt = db()->prepare(
        "SELECT c.id, c.date, c.libele, c.sum, c.quittance,
                c.wants_attestation, c.notified_at, c.cotisation_year,
                ct.id AS type_id, ct.label AS type_label, ct.color AS type_color,
                COALESCE(ct.is_cotisation, 0) AS is_cotisation
         FROM compta c
         LEFT JOIN compta_type ct ON ct.id = c.type_id
         WHERE c.user_id = ?
         ORDER BY c.date DESC, c.id DESC"
    );
    $stmt->execute([$id]);

    $data = array_map(fn($r) => [
        'id'              => (int)$r->id,
        'date'            => $r->date ? date('Y-m-d', (int)$r->date) : null,
        'label'           => $r->libele ?: null,
        'amount'          => $r->sum !== null ? (float)$r->sum : null,
        'quittance'       => $r->quittance ?: null,
        'wantsAttestation'=> (bool)$r->wants_attestation,
        'notifiedAt'      => $r->notified_at ?: null,
        'cotisationYear'  => $r->cotisation_year !== null ? (int)$r->cotisation_year : null,
        'type'            => $r->type_id ? [
            'id'            => (int)$r->type_id,
            'label'         => $r->type_label,
            'color'         => $r->type_color ?: null,
            'isCotisation'  => (bool)$r->is_cotisation,
        ] : null,
    ], $stmt->fetchAll());

    echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
