<?php
/**
 * /api/suivi — REST endpoint for follow-up (suivi) notes.
 *
 * GET    /api/suivi?memberId={id}   list entries for a member (newest first)
 * GET    /api/suivi/{id}            single entry
 * POST   /api/suivi                 create entry
 * PUT    /api/suivi/{id}            update entry
 * DELETE /api/suivi/{id}            delete entry
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../classes/property_class.php';

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

function entryToArray(UserProperty $p): array
{
    return [
        'id'       => (int)$p->getId(),
        'memberId' => (int)$p->getUserId(),
        'date'     => $p->getDate() ? date('Y-m-d', (int)$p->getDate()) : null,
        'note'     => $p->getValue() ?: null,
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

function loadEntry(int $id): UserProperty
{
    $chk = db()->prepare("SELECT id FROM contact_properties WHERE id=? AND parameter='suivi' LIMIT 1");
    $chk->execute([$id]);
    if (!$chk->fetchColumn()) apiError(404, 'Entry not found');
    $p = new UserProperty();
    $p->lookupUserProperty($id);
    return $p;
}

// ── handlers ─────────────────────────────────────────────────────────────────

function handleList(): void
{
    if (!canRead()) apiError(403, 'Forbidden');

    $memberId = isset($_GET['memberId']) ? (int)$_GET['memberId'] : null;
    if (!$memberId) apiError(400, 'memberId query parameter is required');

    $stmt = db()->prepare(
        "SELECT id, user_id, date, value
         FROM contact_properties
         WHERE user_id = ? AND parameter = 'suivi'
         ORDER BY date DESC, id DESC"
    );
    $stmt->execute([$memberId]);

    $data = array_map(fn($r) => [
        'id'       => (int)$r->id,
        'memberId' => (int)$r->user_id,
        'date'     => $r->date ? date('Y-m-d', strtotime($r->date)) : null,
        'note'     => $r->value ?: null,
    ], $stmt->fetchAll());

    echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function handleGet(int $id): void
{
    if (!canRead()) apiError(403, 'Forbidden');
    $p = loadEntry($id);
    echo json_encode(['data' => entryToArray($p)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function handleCreate(): void
{
    if (!canWrite()) apiError(403, 'Forbidden');

    $body     = requestBody();
    $memberId = isset($body['memberId']) ? (int)$body['memberId'] : 0;
    if (!$memberId)         apiError(422, 'memberId is required');
    if (empty($body['date'])) apiError(422, 'date is required');
    if (!isset($body['note']) || trim((string)$body['note']) === '') apiError(422, 'note is required');

    $stmt = db()->prepare("SELECT id FROM contact WHERE id=? AND status=1 LIMIT 1");
    $stmt->execute([$memberId]);
    if (!$stmt->fetchColumn()) apiError(422, "Member #$memberId not found");

    $p = new UserProperty();
    $p->setUserId($memberId);
    $p->setParameter('suivi');
    $p->setDate((int)strtotime((string)$body['date']));
    $p->setValue(unquote(trim((string)$body['note'])));
    $p->save();

    // UserProperty.save() uses a custom sequence; retrieve the id just inserted
    $stmt2 = db()->prepare(
        "SELECT id FROM contact_properties WHERE user_id=? AND parameter='suivi' ORDER BY id DESC LIMIT 1"
    );
    $stmt2->execute([$memberId]);
    $newId = (int)$stmt2->fetchColumn();

    $_auU = db()->prepare("SELECT CONCAT(firstName,' ',lastName) FROM contact WHERE id=?");
    $_auU->execute([$memberId]);
    auditLog(db(), 'addSuivi', "membre: " . ($_auU->fetchColumn() ?: "id=$memberId") . " | " . substr((string)$body['note'], 0, 80), $memberId);

    $p->lookupUserProperty($newId);
    http_response_code(201);
    echo json_encode(['data' => entryToArray($p)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function handleUpdate(int $id): void
{
    if (!canWrite()) apiError(403, 'Forbidden');

    $p    = loadEntry($id);
    $body = requestBody();

    if (array_key_exists('date', $body)) {
        $p->setDate((int)strtotime((string)$body['date']));
    }
    if (array_key_exists('note', $body)) {
        $p->setValue(unquote(trim((string)$body['note'])));
    }
    $p->save();

    $_auU = db()->prepare("SELECT CONCAT(firstName,' ',lastName) FROM contact WHERE id=?");
    $_auU->execute([(int)$p->getUserId()]);
    auditLog(db(), 'updateSuivi', "suivi#=$id | membre: " . ($_auU->fetchColumn() ?: "id={$p->getUserId()}"), (int)$p->getUserId());

    $p->lookupUserProperty($id);
    echo json_encode(['data' => entryToArray($p)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function handleDelete(int $id): void
{
    if (!canWrite()) apiError(403, 'Forbidden');

    $p = loadEntry($id);

    $_auU = db()->prepare("SELECT CONCAT(firstName,' ',lastName) FROM contact WHERE id=?");
    $_auU->execute([(int)$p->getUserId()]);
    auditLog(db(), 'deleteSuivi', "suivi#=$id | membre: " . ($_auU->fetchColumn() ?: "id={$p->getUserId()}"), (int)$p->getUserId());

    $p->remove();
    http_response_code(204);
}
