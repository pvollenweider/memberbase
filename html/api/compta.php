<?php
/**
 * /api/compta — REST endpoint for accounting entries.
 *
 * GET    /api/compta?memberId={id}[&year={y}]   list entries for a member
 * GET    /api/compta/{id}                        single entry
 * POST   /api/compta                             create entry
 * PUT    /api/compta/{id}                        update entry
 * DELETE /api/compta/{id}                        delete entry (admin only)
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../classes/compta_class.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

match (true) {
    $method === 'GET'    && $id === null => handleList(),
    $method === 'GET'                    => handleGet($id),
    $method === 'POST'   && $id === null => handleCreate(),
    $method === 'PUT'    && $id !== null => handleUpdate($id),
    $method === 'DELETE' && $id !== null => handleDelete($id),
    default                              => apiError(405, 'Method Not Allowed'),
};

// ── helpers ──────────────────────────────────────────────────────────────────

function entryToArray(Compta $c): array
{
    return [
        'id'               => (int)$c->getId(),
        'memberId'         => (int)$c->getUserId(),
        'typeId'           => (int)$c->getTypeId(),
        'date'             => $c->getDate() ? date('Y-m-d', (int)$c->getDate()) : null,
        'label'            => $c->getLibele() ?: null,
        'amount'           => $c->getSum() !== null ? (float)$c->getSum() : null,
        'receipt'          => $c->getQuittance() ?: null,
        'wantsAttestation' => (bool)$c->getWantsAttestation(),
    ];
}

function requestBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '') return [];
    $data = json_decode($raw, true);
    if (!is_array($data)) apiError(400, 'Invalid JSON body');
    return $data;
}

function applyFields(Compta $c, array $body): void
{
    if (array_key_exists('typeId',  $body)) $c->setTypeId((int)$body['typeId']);
    if (array_key_exists('label',   $body)) $c->setlibele(unquote((string)$body['label']));
    if (array_key_exists('receipt', $body)) $c->setQuittance((string)$body['receipt']);
    if (array_key_exists('wantsAttestation', $body)) {
        $c->setWantsAttestation((bool)$body['wantsAttestation']);
    }
    if (array_key_exists('amount', $body)) {
        $c->setSum((float)str_replace(',', '.', (string)$body['amount']));
    }
    if (array_key_exists('date', $body)) {
        $c->setDate($body['date'] ? (int)strtotime((string)$body['date']) : 0);
    }
}

function validateTypeId(int $typeId): void
{
    global $comptaTypes;
    if (!isset($comptaTypes[$typeId])) apiError(422, "Unknown typeId: $typeId");
}

// ── handlers ─────────────────────────────────────────────────────────────────

function handleList(): void
{
    global $pdo;

    $memberId = isset($_GET['memberId']) ? (int)$_GET['memberId'] : null;
    if (!$memberId) apiError(400, 'memberId query parameter is required');

    $year   = isset($_GET['year']) ? (int)$_GET['year'] : null;
    $params = [$memberId];
    $where  = 'WHERE c.user_id = ?';

    if ($year) {
        $from    = mktime(0, 0, 0, 1, 1, $year);
        $to      = mktime(0, 0, 0, 1, 1, $year + 1);
        $where  .= ' AND c.date >= ? AND c.date < ?';
        $params[] = $from;
        $params[] = $to;
    }

    $stmt = $pdo->prepare(
        "SELECT c.id, c.user_id, c.type_id, c.date, c.libele, c.sum, c.quittance, c.wants_attestation
         FROM compta c
         $where
         ORDER BY c.date DESC, c.id DESC"
    );
    $stmt->execute($params);

    $data = array_map(fn($r) => [
        'id'               => (int)$r->id,
        'memberId'         => (int)$r->user_id,
        'typeId'           => (int)$r->type_id,
        'date'             => $r->date ? date('Y-m-d', (int)$r->date) : null,
        'label'            => $r->libele    ?: null,
        'amount'           => $r->sum !== null ? (float)$r->sum : null,
        'receipt'          => $r->quittance ?: null,
        'wantsAttestation' => (bool)$r->wants_attestation,
    ], $stmt->fetchAll());

    echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function handleGet(int $id): void
{
    $c = new Compta();
    $c->lookupCompta($id);
    if (!$c->getId()) apiError(404, 'Entry not found');

    echo json_encode(['data' => entryToArray($c)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function handleCreate(): void
{
    global $pdo, $comptaTypes;
    if (!canWrite()) apiError(403, 'Forbidden');
    $body = requestBody();

    $memberId = isset($body['memberId']) ? (int)$body['memberId'] : 0;
    $typeId   = isset($body['typeId'])   ? (int)$body['typeId']   : 0;

    if (!$memberId)  apiError(422, 'memberId is required');
    if (!$typeId)    apiError(422, 'typeId is required');
    if (empty($body['date']))   apiError(422, 'date is required');
    if (!isset($body['amount'])) apiError(422, 'amount is required');

    validateTypeId($typeId);

    $stmt = $pdo->prepare("SELECT id FROM users WHERE id=? AND status=1 LIMIT 1");
    $stmt->execute([$memberId]);
    if (!$stmt->fetchColumn()) apiError(422, "Member #$memberId not found");

    $c = new Compta();
    $c->setUserId($memberId);
    $c->setlibele('');
    $c->setQuittance('');
    $c->setSum(0);
    $c->setDate(0);
    $c->setWantsAttestation(false);
    applyFields($c, $body);
    $c->save();

    $newId = (int)$pdo->lastInsertId();
    $c->lookupCompta($newId);

    $_auUser = $pdo->prepare("SELECT CONCAT(firstName,' ',lastName) FROM users WHERE id=?");
    $_auUser->execute([$memberId]);
    $typLabel = $comptaTypes[$typeId]->label ?? "type=$typeId";
    auditLog($pdo, 'addCompta',
        "membre: " . ($_auUser->fetchColumn() ?: "id=$memberId") . " | $typLabel | {$c->getSum()} CHF",
        $memberId);

    http_response_code(201);
    echo json_encode(['data' => entryToArray($c)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function handleUpdate(int $id): void
{
    global $pdo, $comptaTypes;
    if (!canWrite()) apiError(403, 'Forbidden');
    $body = requestBody();

    $c = new Compta();
    $c->lookupCompta($id);
    if (!$c->getId()) apiError(404, 'Entry not found');

    if (isset($body['typeId'])) validateTypeId((int)$body['typeId']);

    applyFields($c, $body);
    $c->save();

    $c->lookupCompta($id);
    $typLabel = $comptaTypes[(int)$c->getTypeId()]->label ?? "type={$c->getTypeId()}";
    auditLog($pdo, 'updateCompta', "compta#=$id | $typLabel | {$c->getSum()} CHF", (int)$c->getUserId());

    echo json_encode(['data' => entryToArray($c)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function handleDelete(int $id): void
{
    global $pdo;

    if (!canWrite()) apiError(403, 'Forbidden');

    $c = new Compta();
    $c->lookupCompta($id);
    if (!$c->getId()) apiError(404, 'Entry not found');

    auditLog($pdo, 'deleteCompta', "compta#=$id | {$c->getSum()} CHF", (int)$c->getUserId());
    $c->remove();

    http_response_code(204);
}
