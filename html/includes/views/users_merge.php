<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * UI and logic for merging two duplicate member records.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$_muIdA = (int)($_GET['a'] ?? 0);
$_muIdB = (int)($_GET['b'] ?? 0);

if ($_muIdA <= 0 || $_muIdB <= 0 || $_muIdA === $_muIdB) { ?>
<div class="alert alert-danger mt-4" role="alert">
  <i class="fas fa-triangle-exclamation me-2" aria-hidden="true"></i><?= $GLOBAL['invalidMergeParams'] ?>
</div>
<?php return; }

$_muUserA = new User(); $_muUserA->lookupUser($_muIdA);
$_muUserB = new User(); $_muUserB->lookupUser($_muIdB);

if (!$_muUserA->getId() || !$_muUserB->getId()) { ?>
<div class="alert alert-danger mt-4" role="alert">
  <i class="fas fa-triangle-exclamation me-2" aria-hidden="true"></i><?= $GLOBAL['memberNotFound'] ?>
</div>
<?php return; }

// Stats
$_muSt = $pdo->prepare("SELECT COUNT(*) FROM compta WHERE user_id=?");
$_muSt->execute([$_muIdA]); $cComptaA = (int)$_muSt->fetchColumn();
$_muSt->execute([$_muIdB]); $cComptaB = (int)$_muSt->fetchColumn();
$_muSt = $pdo->prepare("SELECT COUNT(*) FROM user_properties WHERE user_id=? AND parameter='suivi'");
$_muSt->execute([$_muIdA]); $cSuiviA = (int)$_muSt->fetchColumn();
$_muSt->execute([$_muIdB]); $cSuiviB = (int)$_muSt->fetchColumn();
$_muSt = $pdo->prepare("SELECT GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ') FROM user_team ut JOIN team t ON t.id = ut.team_id WHERE ut.user_id=?");
$_muSt->execute([$_muIdA]); $groupsA = $_muSt->fetchColumn() ?: '—';
$_muSt->execute([$_muIdB]); $groupsB = $_muSt->fetchColumn() ?: '—';

// Field map: key => [label, valueA, valueB]
$_muFields = [
    'firstName' => [$GLOBAL['firstName'],   (string)$_muUserA->firstName,                               (string)$_muUserB->firstName],
    'lastName'  => [$GLOBAL['lastName'],    (string)$_muUserA->lastName,                                (string)$_muUserB->lastName],
    'society'   => [$GLOBAL['society'],     (string)$_muUserA->society,                                 (string)$_muUserB->society],
    'sexe'      => [$GLOBAL['sexLabel'],    (string)$_muUserA->sexe,                                    (string)$_muUserB->sexe],
    'title'     => [$GLOBAL['title'],       (string)$_muUserA->title,                                   (string)$_muUserB->title],
    'address'   => [$GLOBAL['address'],     (string)$_muUserA->address,                                 (string)$_muUserB->address],
    'npa'       => [$GLOBAL['npaCity'],     (string)$_muUserA->npa,                                     (string)$_muUserB->npa],
    'tel'       => [$GLOBAL['telShort'],    (string)$_muUserA->tel,                                     (string)$_muUserB->tel],
    'telProf'   => [$GLOBAL['telProfShort'],(string)$_muUserA->telProf,                                 (string)$_muUserB->telProf],
    'portable'  => [$GLOBAL['portable'],    (string)$_muUserA->portable,                                (string)$_muUserB->portable],
    'fax'       => [$GLOBAL['fax'],         (string)$_muUserA->fax,                                     (string)$_muUserB->fax],
    'email'     => [$GLOBAL['email'],       (string)$_muUserA->email,                                   (string)$_muUserB->email],
    'web'       => [$GLOBAL['web'],         (string)$_muUserA->web,                                     (string)$_muUserB->web],
    'birthDay'  => [$GLOBAL['birthShort'],  timeStampToformatedDate((int)$_muUserA->birthDay),           timeStampToformatedDate((int)$_muUserB->birthDay)],
    'comment'   => [$GLOBAL['noteLabel'],   (string)$_muUserA->comment,                                 (string)$_muUserB->comment],
];

$_muDivergent = [];
$_muDefaults = [];
foreach ($_muFields as $k => [,$vA, $vB]) {
    if (trim($vA) !== trim($vB)) {
        $_muDivergent[] = $k;
        $emptyA = trim($vA) === '';
        $emptyB = trim($vB) === '';
        if ($emptyA && !$emptyB) $_muDefaults[$k] = 'b';
        elseif ($emptyB && !$emptyA) $_muDefaults[$k] = 'a';
        else $_muDefaults[$k] = 'a';
    }
}
$_muDivergentJson = json_encode($_muDivergent);
$_muDefaultsJson  = json_encode($_muDefaults);
$_muNameA = htmlspecialchars(trim($_muUserA->firstName . ' ' . $_muUserA->lastName), ENT_QUOTES, $charset);
$_muNameB = htmlspecialchars(trim($_muUserB->firstName . ' ' . $_muUserB->lastName), ENT_QUOTES, $charset);
?>

<div class="ca-merge-wrap" x-data="mergeApp()" x-cloak>

  <div class="d-flex align-items-center gap-2 mb-4" style="font-size:0.8rem">
    <a href="<?= $_SERVER['PHP_SELF'] ?>?view=settings&tab=integrity" class="text-muted text-decoration-none">
      <i class="fas fa-stethoscope me-1" aria-hidden="true"></i><?= $GLOBAL['integrity'] ?>
    </a>
    <i class="fas fa-chevron-right text-muted" style="font-size:0.65rem" aria-hidden="true"></i>
    <span class="text-muted"><?= $GLOBAL['memberMerge'] ?></span>
  </div>

  <div class="d-flex align-items-baseline gap-3 mb-1">
    <h1 class="ca-merge-title"><?= $GLOBAL['mergeTwoMembers'] ?></h1>
  </div>
  <p class="text-muted mb-4" style="font-size:0.85rem">
    <?= $GLOBAL['mergeInstruction'] ?>
    <?php if (empty($_muDivergent)): ?>
    <span class="badge text-bg-success ms-1"><?= $GLOBAL['allDataIdentical'] ?></span>
    <?php else: ?>
    <span class="badge text-bg-warning ms-1"><?= sprintf($GLOBAL['divergentFieldsCount'], count($_muDivergent), count($_muDivergent) > 1 ? 's' : '', count($_muDivergent) > 1 ? 's' : '') ?></span>
    <?php endif ?>
  </p>

  <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" id="merge-form">
    <input type="hidden" name="action" value="mergeUsers">
    <input type="hidden" name="view"   value="mergeUsers">
    <input type="hidden" name="idA"    value="<?= $_muIdA ?>">
    <input type="hidden" name="idB"    value="<?= $_muIdB ?>">

    <!-- Field comparison table -->
    <div class="table-responsive mb-4">
      <table class="ca-merge-table table table-sm align-middle mb-0" aria-label="<?= $GLOBAL['mergeTableAria'] ?>">
        <thead>
          <tr>
            <th class="ca-merge-th-field" scope="col"><?= $GLOBAL['fieldLabel'] ?></th>
            <th class="ca-merge-th-profile" scope="col">
              <div class="ca-merge-profile-head">
                <span class="ca-merge-profile-badge">A</span>
                <span><?= $_muNameA ?></span>
                <span class="text-muted" style="font-size:0.75rem">#<?= $_muIdA ?></span>
              </div>
            </th>
            <th class="ca-merge-th-profile" scope="col">
              <div class="ca-merge-profile-head">
                <span class="ca-merge-profile-badge ca-merge-profile-badge--b">B</span>
                <span><?= $_muNameB ?></span>
                <span class="text-muted" style="font-size:0.75rem">#<?= $_muIdB ?></span>
              </div>
            </th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($_muFields as $key => [$label, $vA, $vB]):
            $isDivergent = in_array($key, $_muDivergent);
            $isHtml = ($key === 'comment');
            $vAHtml = $isHtml ? ($vA ?: '') : nl2br(htmlspecialchars($vA, ENT_QUOTES, $charset));
            $vBHtml = $isHtml ? ($vB ?: '') : nl2br(htmlspecialchars($vB, ENT_QUOTES, $charset));
          ?>
          <tr class="ca-merge-row <?= $isDivergent ? 'ca-merge-row--divergent' : 'ca-merge-row--same' ?>">
            <td class="ca-merge-label"><?= htmlspecialchars($label, ENT_QUOTES, $charset) ?></td>

            <?php if ($isDivergent): ?>
            <!-- Hidden input — default A, overridden by Alpine -->
            <input type="hidden" name="fields[<?= $key ?>]" value="a" x-bind:value="selections['<?= $key ?>']">

            <td class="ca-merge-cell ca-merge-cell--a"
                :class="{'selected': selections['<?= $key ?>'] === 'a' || selections['<?= $key ?>'] === 'both'}"
                @click="<?= $isHtml ? "if(selections['{$key}'] !== 'both') select('{$key}', 'a')" : "select('{$key}', 'a')" ?>"
                role="button"
                tabindex="0"
                @keydown.enter.prevent="<?= $isHtml ? "if(selections['{$key}'] !== 'both') select('{$key}', 'a')" : "select('{$key}', 'a')" ?>"
                @keydown.space.prevent="<?= $isHtml ? "if(selections['{$key}'] !== 'both') select('{$key}', 'a')" : "select('{$key}', 'a')" ?>"
                :aria-pressed="selections['<?= $key ?>'] === 'a'"
                aria-label="<?= sprintf($GLOBAL['chooseValueA'], htmlspecialchars($label, ENT_QUOTES, $charset)) ?>">
              <span class="ca-merge-cell-check" aria-hidden="true"><i class="fas fa-check"></i></span>
              <span class="ca-merge-cell-value"><?= $vAHtml ?: '<span class="text-muted fst-italic">' . $GLOBAL['emptyValue'] . '</span>' ?></span>
            </td>
            <td class="ca-merge-cell ca-merge-cell--b"
                :class="{'selected': selections['<?= $key ?>'] === 'b' || selections['<?= $key ?>'] === 'both'}"
                @click="<?= $isHtml ? "if(selections['{$key}'] !== 'both') select('{$key}', 'b')" : "select('{$key}', 'b')" ?>"
                role="button"
                tabindex="0"
                @keydown.enter.prevent="<?= $isHtml ? "if(selections['{$key}'] !== 'both') select('{$key}', 'b')" : "select('{$key}', 'b')" ?>"
                @keydown.space.prevent="<?= $isHtml ? "if(selections['{$key}'] !== 'both') select('{$key}', 'b')" : "select('{$key}', 'b')" ?>"
                :aria-pressed="selections['<?= $key ?>'] === 'b'"
                aria-label="<?= sprintf($GLOBAL['chooseValueB'], htmlspecialchars($label, ENT_QUOTES, $charset)) ?>">
              <span class="ca-merge-cell-check" aria-hidden="true"><i class="fas fa-check"></i></span>
              <span class="ca-merge-cell-value"><?= $vBHtml ?: '<span class="text-muted fst-italic">' . $GLOBAL['emptyValue'] . '</span>' ?></span>
            </td>

            <?php if ($isHtml): ?>
            </tr>
            <tr class="ca-merge-row ca-merge-row--divergent">
              <td></td>
              <td colspan="2" class="pt-1 pb-2">
                <div class="form-check form-check-inline mb-0">
                  <input class="form-check-input" type="checkbox" id="merge_both_<?= $key ?>"
                         :checked="selections['<?= $key ?>'] === 'both'"
                         @change="select('<?= $key ?>', $event.target.checked ? 'both' : 'a')">
                  <label class="form-check-label text-muted" for="merge_both_<?= $key ?>" style="font-size:0.8rem">
                    <?= $GLOBAL['keepBothNotes'] ?>
                  </label>
                </div>
              </td>
            <?php endif ?>

            <?php else: ?>
            <td class="ca-merge-cell ca-merge-cell--same">
              <span><?= $vAHtml ?: '<span class="text-muted fst-italic">' . $GLOBAL['emptyValue'] . '</span>' ?></span>
            </td>
            <td class="ca-merge-cell ca-merge-cell--same">
              <span><?= $vBHtml ?: '<span class="text-muted fst-italic">' . $GLOBAL['emptyValue'] . '</span>' ?></span>
            </td>
            <?php endif ?>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>

    <!-- Stats (read-only) -->
    <div class="ca-merge-stats mb-4">
      <p class="fw-semibold mb-2" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:.04em;color:var(--ca-ink-muted)"><?= $GLOBAL['linkedDataAuto'] ?></p>
      <table class="table table-sm mb-0" style="font-size:0.82rem;max-width:560px">
        <thead>
          <tr>
            <th></th>
            <th class="text-center"><?= $GLOBAL['profileA'] ?> <span class="text-muted">#<?= $_muIdA ?></span></th>
            <th class="text-center"><?= $GLOBAL['profileB'] ?> <span class="text-muted">#<?= $_muIdB ?></span></th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><?= $GLOBAL['comptaEntries'] ?></td>
            <td class="text-center"><?= $cComptaA ?></td>
            <td class="text-center"><?= $cComptaB ?></td>
          </tr>
          <tr>
            <td><?= $GLOBAL['suiviEntries'] ?></td>
            <td class="text-center"><?= $cSuiviA ?></td>
            <td class="text-center"><?= $cSuiviB ?></td>
          </tr>
          <tr>
            <td><?= $GLOBAL['groups'] ?></td>
            <td class="text-muted" style="font-size:0.78rem"><?= htmlspecialchars($groupsA, ENT_QUOTES, $charset) ?></td>
            <td class="text-muted" style="font-size:0.78rem"><?= htmlspecialchars($groupsB, ENT_QUOTES, $charset) ?></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Survivor + disposal -->
    <div class="ca-merge-options mb-4">
      <div class="row g-4">
        <div class="col-md-6">
          <p class="fw-semibold mb-2" style="font-size:0.85rem"><?= $GLOBAL['survivorProfile'] ?></p>
          <div class="d-flex gap-3">
            <label class="ca-merge-radio" x-bind:class="{'active': survivor === 'a'}">
              <input type="radio" name="survivor" value="a" x-model="survivor" class="visually-hidden">
              <span class="ca-merge-profile-badge">A</span>
              <span><?= $_muNameA ?> <span class="text-muted" style="font-size:0.78rem">#<?= $_muIdA ?></span></span>
            </label>
            <label class="ca-merge-radio" x-bind:class="{'active': survivor === 'b'}">
              <input type="radio" name="survivor" value="b" x-model="survivor" class="visually-hidden">
              <span class="ca-merge-profile-badge ca-merge-profile-badge--b">B</span>
              <span><?= $_muNameB ?> <span class="text-muted" style="font-size:0.78rem">#<?= $_muIdB ?></span></span>
            </label>
          </div>
        </div>
        <div class="col-md-6">
          <p class="fw-semibold mb-2" style="font-size:0.85rem"><?= $GLOBAL['sourceProfileAfterMerge'] ?></p>
          <div class="d-flex gap-3">
            <label class="ca-merge-radio" x-bind:class="{'active': disposal === 'hide'}">
              <input type="radio" name="disposal" value="hide" x-model="disposal" class="visually-hidden">
              <i class="fas fa-eye-slash me-1 text-muted" aria-hidden="true"></i>
              <span><?= $GLOBAL['archive'] ?></span>
            </label>
            <label class="ca-merge-radio ca-merge-radio--danger" x-bind:class="{'active': disposal === 'delete'}">
              <input type="radio" name="disposal" value="delete" x-model="disposal" class="visually-hidden">
              <i class="fas fa-trash-can me-1" aria-hidden="true"></i>
              <span><?= $GLOBAL['delete'] ?></span>
            </label>
          </div>
          <p class="text-muted mt-1 mb-0" style="font-size:0.78rem" x-show="disposal === 'delete'">
            <i class="fas fa-triangle-exclamation text-danger me-1" aria-hidden="true"></i>
            <?= $GLOBAL['mergeDeleteWarning'] ?>
          </p>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="d-flex gap-2 align-items-center">
      <button type="button"
              class="btn btn-danger"
              @click="openConfirm()"
              :aria-disabled="!allResolved"
              :style="!allResolved ? 'opacity:.5;pointer-events:none' : ''">
        <i class="fas fa-code-merge me-1" aria-hidden="true"></i><?= $GLOBAL['merge'] ?>
      </button>
      <a href="<?= $_SERVER['PHP_SELF'] ?>?view=settings&tab=integrity" class="btn btn-outline-secondary"><?= $GLOBAL['cancel'] ?></a>
      <span class="text-muted ms-2" style="font-size:0.82rem" x-show="!allResolved" x-cloak>
        <i class="fas fa-circle-info me-1" aria-hidden="true"></i>
        <?= $GLOBAL['resolveAllFields'] ?>
      </span>
    </div>

  </form><!-- #merge-form -->

  <!-- Confirmation dialog -->
  <dialog id="merge-dialog" class="ca-merge-dialog" aria-labelledby="merge-dialog-title">
    <div class="ca-merge-dialog-body">
      <h2 class="ca-merge-dialog-title" id="merge-dialog-title">
        <i class="fas fa-triangle-exclamation text-danger me-2" aria-hidden="true"></i>
        <?= $GLOBAL['confirmMerge'] ?>
      </h2>
      <p style="font-size:0.9rem"><?= $GLOBAL['mergeConfirmIntro'] ?></p>
      <ul class="ca-merge-dialog-summary" style="font-size:0.85rem">
        <li><?= $GLOBAL['survivorLabel'] ?> <strong x-text="survivor === 'a' ? '<?= addslashes($_muNameA) ?> #<?= $_muIdA ?>' : '<?= addslashes($_muNameB) ?> #<?= $_muIdB ?>'"></strong></li>
        <li><?= $GLOBAL['sourceDeletedLabel'] ?> <span x-text="disposal === 'delete' ? '<?= $GLOBAL['yesIrreversible'] ?>' : '<?= $GLOBAL['noArchivedOnly'] ?>'"></span></li>
        <li x-show="<?= count($_muDivergent) ?> > 0"><?= sprintf($GLOBAL['fieldsModifiedSummary'], count($_muDivergent)) ?></li>
        <li><?= $GLOBAL['mergeReattachInfo'] ?></li>
        <li><?= $GLOBAL['mergeSegmentsInfo'] ?></li>
      </ul>
      <div class="d-flex gap-2 justify-content-end mt-4">
        <button type="button" class="btn btn-outline-secondary" @click="closeConfirm()"><?= $GLOBAL['cancel'] ?></button>
        <button type="submit" form="merge-form" class="btn btn-danger">
          <i class="fas fa-code-merge me-1" aria-hidden="true"></i><?= $GLOBAL['confirmMerge'] ?>
        </button>
      </div>
    </div>
  </dialog>

</div><!-- .ca-merge-wrap -->

<script>
function mergeApp() {
    const divergent = <?= $_muDivergentJson ?>;
    const defaults = <?= $_muDefaultsJson ?>;
    const sel = {};
    divergent.forEach(function(k) { sel[k] = defaults[k] || 'a'; });
    return {
        selections: sel,
        survivor: 'a',
        disposal: 'hide',
        divergent: divergent,
        get allResolved() {
            return this.divergent.every(function(k) { return !!this.selections[k]; }, this);
        },
        select: function(field, side) {
            this.selections[field] = side;
        },
        openConfirm: function() {
            document.getElementById('merge-dialog').showModal();
        },
        closeConfirm: function() {
            document.getElementById('merge-dialog').close();
        },
    };
}
</script>
