<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Application settings form covering groups, filters, and accounting types.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
// Guard: segment table may not exist yet if migrations are pending.
try {
    $allSegments = db()->query("SELECT id, name, hidden FROM segment ORDER BY hidden ASC, name ASC")->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    $allSegments = [];
}
$saved = isset($_GET['saved']);
$_activeTab = $_REQUEST['tab'] ?? null;
$_settingsDrillDown = in_array($_REQUEST['view'] ?? '', ['updateSegment', 'updateCombinedSegment']);

// Guard: the compta/contactTypes panes below assume columns/tables from
// migrations 0035-0037 (is_financial_institution, is_company, is_archived,
// contact_type…) that a not-yet-migrated instance won't have — since every
// tab pane renders unconditionally on every ?view=settings load (Bootstrap
// tabs, not server-side routing), a missing column there would fatal the
// WHOLE Réglages page, including the Santé tab needed to apply the pending
// migration in the first place. Detect and show a notice instead.
$_ctSchemaPending = (bool)array_intersect(
    pendingMigrations($pdo),
    ['0035_contact_type', '0036_compta_type_matrix_archive', '0037_contact_type_icon', '0038_contact_type_default_compta_type']
);

$_paneClass = function(string $tab) use ($_activeTab): string {
    $active = $_activeTab ?? '';
    if ($active === 'teams' || $active === 'segments') $active = 'groups';
    return $active === $tab ? ' show active' : '';
};

$_noOuterContainer = true;
$_phIcon = 'fa-gear';
$_phTitle = $GLOBAL['administration'];
include __DIR__ . '/../partials/page_header.php';
?>
<div class="container-xl px-4 ca-hero-overlap">

        <div class="tab-content">

          <!-- Réglages -->
          <div class="tab-pane fade<?= $_paneClass('settings') ?>" id="tab-settings" role="tabpanel" aria-labelledby="tab-settings-btn">
            <?php if (!isAdmin()): ?>
            <div class="alert alert-danger" role="alert"><i class="fas fa-lock me-2" aria-hidden="true"></i><?= $GLOBAL['adminOnly'] ?></div>
            <?php else: ?>
            <div class="card mb-4">
            <div class="card-header"><?= $GLOBAL['settings'] ?></div>
            <div class="card-body">
            <div id="settings-save-msg"></div>
            <form action="<?= appUrl() ?>" method="post"
                  hx-post="<?= appUrl() ?>"
                  hx-target="#settings-save-msg"
                  hx-swap="innerHTML">
              <input type="hidden" name="action" value="saveSettings"/>
              <input type="hidden" name="view" value="settings"/>
              <p class="form-section-title"><i class="fas fa-building me-1" aria-hidden="true"></i><?= $GLOBAL['organization'] ?></p>
              <div class="mb-3">
                <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_org_name"><?= $GLOBAL['orgName'] ?></label>
                <input type="text" name="org_name" id="s_org_name" class="form-control form-control-sm" style="max-width:320px"
                       value="<?= htmlspecialchars($appSettings['org_name'] ?? '', ENT_QUOTES, $charset) ?>">
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_org_address"><?= $GLOBAL['address'] ?></label>
                <input type="text" name="org_address" id="s_org_address" class="form-control form-control-sm" style="max-width:320px"
                       value="<?= htmlspecialchars($appSettings['org_address'] ?? '', ENT_QUOTES, $charset) ?>">
              </div>
              <div class="row g-2 mb-3" style="max-width:320px">
                <div class="col-4">
                  <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_org_npa"><?= $GLOBAL['npaShort'] ?></label>
                  <input type="text" name="org_npa" id="s_org_npa" class="form-control form-control-sm"
                         value="<?= htmlspecialchars($appSettings['org_npa'] ?? '', ENT_QUOTES, $charset) ?>">
                </div>
                <div class="col-8">
                  <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_org_city"><?= $GLOBAL['city'] ?></label>
                  <input type="text" name="org_city" id="s_org_city" class="form-control form-control-sm"
                         value="<?= htmlspecialchars($appSettings['org_city'] ?? '', ENT_QUOTES, $charset) ?>">
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_org_country"><?= $GLOBAL['country'] ?></label>
                <input type="text" name="org_country" id="s_org_country" class="form-control form-control-sm" style="max-width:320px"
                       value="<?= htmlspecialchars($appSettings['org_country'] ?? '', ENT_QUOTES, $charset) ?>">
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_org_ide"><?= $GLOBAL['orgIde'] ?></label>
                <p class="text-muted mb-2" style="font-size:0.78rem"><?= $GLOBAL['orgIdeHelp'] ?></p>
                <div class="d-flex gap-2 align-items-center flex-wrap" style="max-width:440px">
                  <input type="text" name="org_ide" id="s_org_ide" class="form-control form-control-sm" style="max-width:200px"
                         placeholder="CHE-123.456.789"
                         value="<?= htmlspecialchars($appSettings['org_ide'] ?? '', ENT_QUOTES, $charset) ?>">
                  <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-zefix-lookup"
                          data-action="<?= htmlspecialchars(appUrl(), ENT_QUOTES, $charset) ?>"
                          data-label-checking="<?= htmlspecialchars($GLOBAL['zefixChecking'], ENT_QUOTES, $charset) ?>"
                          data-label-verify="<?= htmlspecialchars($GLOBAL['zefixVerify'], ENT_QUOTES, $charset) ?>">
                    <i class="fas fa-magnifying-glass me-1" aria-hidden="true"></i><span><?= $GLOBAL['zefixVerify'] ?></span>
                  </button>
                </div>
                <div id="zefix-result" class="mt-2" style="font-size:0.82rem"></div>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_org_purpose"><?= $GLOBAL['orgPurpose'] ?></label>
                <p class="text-muted mb-2" style="font-size:0.78rem"><?= $GLOBAL['orgPurposeHelp'] ?></p>
                <textarea name="org_purpose" id="s_org_purpose" class="form-control form-control-sm" rows="3" style="max-width:420px"><?= htmlspecialchars($appSettings['org_purpose'] ?? '', ENT_QUOTES, $charset) ?></textarea>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_org_tax_status"><?= $GLOBAL['orgTaxStatus'] ?></label>
                <p class="text-muted mb-2" style="font-size:0.78rem"><?= $GLOBAL['orgTaxStatusHelp'] ?></p>
                <input type="text" name="org_tax_status" id="s_org_tax_status" class="form-control form-control-sm" style="max-width:280px"
                       placeholder="<?= htmlspecialchars($GLOBAL['orgTaxStatusPlaceholder'], ENT_QUOTES, $charset) ?>"
                       value="<?= htmlspecialchars($appSettings['org_tax_status'] ?? '', ENT_QUOTES, $charset) ?>">
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_org_iban"><?= $GLOBAL['orgIban'] ?></label>
                <p class="text-muted mb-2" style="font-size:0.78rem"><?= $GLOBAL['orgIbanHelp'] ?></p>
                <input type="text" name="org_iban" id="s_org_iban" class="form-control form-control-sm" style="max-width:320px"
                       placeholder="CH56 0483 5012 3456 7800 9"
                       value="<?= htmlspecialchars($appSettings['org_iban'] ?? '', ENT_QUOTES, $charset) ?>">
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_org_coti_amount_desc"><?= $GLOBAL['orgCotiAmountDesc'] ?></label>
                <p class="text-muted mb-2" style="font-size:0.78rem"><?= $GLOBAL['orgCotiAmountDescHelp'] ?></p>
                <input type="text" name="org_coti_amount_desc" id="s_org_coti_amount_desc" class="form-control form-control-sm" style="max-width:560px"
                       placeholder="<?= htmlspecialchars($GLOBAL['orgCotiAmountDescPlaceholder'], ENT_QUOTES, $charset) ?>"
                       value="<?= htmlspecialchars($appSettings['org_coti_amount_desc'] ?? '', ENT_QUOTES, $charset) ?>">
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_membership_url"><?= $GLOBAL['membershipUrlLabel'] ?></label>
                <p class="text-muted mb-2" style="font-size:0.78rem"><?= $GLOBAL['membershipUrlHelp'] ?></p>
                <input type="url" name="membership_url" id="s_membership_url" class="form-control form-control-sm" style="max-width:420px"
                       placeholder="https://www.example.org/devenir-membre"
                       value="<?= htmlspecialchars($appSettings['membership_url'] ?? '', ENT_QUOTES, $charset) ?>">
              </div>

              <div class="mb-4">
                <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_membre_segment_prefix"><?= $GLOBAL['memberSegmentPrefixLabel'] ?></label>
                <p class="text-muted mb-2" style="font-size:0.78rem"><?= $GLOBAL['memberSegmentPrefixHelp'] ?></p>
                <input type="text" name="membre_segment_prefix" id="s_membre_segment_prefix" class="form-control form-control-sm" style="max-width:200px"
                       value="<?= htmlspecialchars($appSettings['membre_segment_prefix'] ?? 'Membre', ENT_QUOTES, $charset) ?>">
              </div>
              <p class="form-section-title"><i class="fas fa-sliders me-1" aria-hidden="true"></i><?= $GLOBAL['groups'] ?></p>
              <div class="mb-4">
                <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_default_segment"><?= $GLOBAL['defaultSegmentLabel'] ?></label>
                <p class="text-muted mb-2" style="font-size:0.78rem"><?= $GLOBAL['defaultSegmentHelp'] ?></p>
                <select name="default_segment" id="s_default_segment" class="form-select form-select-sm" style="max-width:320px">
                  <?php foreach ($allSegments as $t): ?>
                  <option value="<?= (int)$t->id ?>" <?= (int)$appSettings['default_segment'] === (int)$t->id ? 'selected' : '' ?>>
                    <?= htmlentities($t->name, ENT_COMPAT, $charset) ?><?= $t->hidden ? ' ' . $GLOBAL['maskedSuffix'] : '' ?>
                  </option>
                  <?php endforeach ?>
                </select>
              </div>
              <div class="mb-4">
                <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_membre_segment"><?= $GLOBAL['membreSegmentLabel'] ?></label>
                <p class="text-muted mb-2" style="font-size:0.78rem"><?= $GLOBAL['membreSegmentHelp'] ?></p>
                <select name="membre_segment" id="s_membre_segment" class="form-select form-select-sm" style="max-width:320px">
                  <?php foreach ($allSegments as $t): ?>
                  <option value="<?= (int)$t->id ?>" <?= (int)$appSettings['membre_segment'] === (int)$t->id ? 'selected' : '' ?>>
                    <?= htmlentities($t->name, ENT_COMPAT, $charset) ?><?= $t->hidden ? ' ' . $GLOBAL['maskedSuffix'] : '' ?>
                  </option>
                  <?php endforeach ?>
                </select>
              </div>
              <div class="mb-4">
                <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_member_no_coti_segment"><?= $GLOBAL['noCotiSegmentLabel'] ?></label>
                <p class="text-muted mb-2" style="font-size:0.78rem"><?= $GLOBAL['noCotiSegmentHelp'] ?></p>
                <select name="member_no_coti_segment" id="s_member_no_coti_segment" class="form-select form-select-sm" style="max-width:320px">
                  <option value="0" <?= empty($appSettings['member_no_coti_segment']) ? 'selected' : '' ?>><?= $GLOBAL['noneOption'] ?></option>
                  <?php foreach ($allSegments as $t): ?>
                  <option value="<?= (int)$t->id ?>" <?= (int)($appSettings['member_no_coti_segment'] ?? 0) === (int)$t->id ? 'selected' : '' ?>>
                    <?= htmlentities($t->name, ENT_COMPAT, $charset) ?><?= $t->hidden ? ' ' . $GLOBAL['maskedSuffix'] : '' ?>
                  </option>
                  <?php endforeach ?>
                </select>
              </div>
              <button type="submit" class="btn btn-primary btn-sm"><?= $GLOBAL['save'] ?></button>
            </form>
            </div><!-- .card-body -->
            </div><!-- .card -->
            <?php endif ?>
            <?php if (isAdmin()): ?>
            <script>
            (function () {
              function doLookup(btnId, resultId, actionName, ideFieldId, fillFn, labels) {
                var btn    = document.getElementById(btnId);
                var result = document.getElementById(resultId);
                if (!btn) return;
                btn.addEventListener('click', function () {
                  var ide = document.getElementById(ideFieldId).value.trim();
                  if (!ide) { result.innerHTML = '<span class="text-warning">' + (labels.missingIde || '') + '</span>'; return; }
                  btn.disabled = true;
                  btn.querySelector('span').textContent = btn.dataset.labelChecking;
                  result.innerHTML = '';
                  var fd = new FormData();
                  fd.append('action', actionName);
                  fd.append('ide', ide);
                  fd.append('csrf', window.casaCsrfToken ? window.casaCsrfToken() : '');
                  // HX-Request: true makes index.php take its no-full-page-render
                  // branch (see index.php $isHtmx) so the response body is pure
                  // JSON instead of the full page shell with JSON appended.
                  fetch(btn.dataset.action, { method: 'POST', body: fd,
                    headers: { 'X-CSRF-Token': window.casaCsrfToken ? window.casaCsrfToken() : '',
                               'HX-Request': 'true' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) { fillFn(data, result); })
                    .catch(function () { result.innerHTML = '<span class="text-danger">' + (labels.networkError || '') + '</span>'; })
                    .finally(function () {
                      btn.disabled = false;
                      btn.querySelector('span').textContent = btn.dataset.labelVerify;
                    });
                });
              }

              var zLabels = {
                missingIde:   <?= json_encode($GLOBAL['zefixMissingIde']) ?>,
                networkError: <?= json_encode($GLOBAL['zefixNetworkError']) ?>
              };
              doLookup('btn-zefix-lookup', 'zefix-result', 'zefixLookup', 's_org_ide', function (data, el) {
                if (data.error) {
                  var msgs = { invalid_ide: <?= json_encode($GLOBAL['zefixInvalidIde']) ?>,
                               not_found:   <?= json_encode($GLOBAL['zefixNotFound']) ?>,
                               unreachable: <?= json_encode($GLOBAL['zefixUnreachable']) ?> };
                  el.innerHTML = '<span class="text-danger">' + (msgs[data.error] || data.error) + '</span>';
                  return;
                }
                if (data.ide)     document.getElementById('s_org_ide').value = data.ide;
                if (data.name)    document.getElementById('s_org_name').value = data.name;
                if (data.street)  document.getElementById('s_org_address').value = data.street;
                if (data.npa)     document.getElementById('s_org_npa').value  = data.npa;
                if (data.city)    document.getElementById('s_org_city').value = data.city;
                if (data.country) document.getElementById('s_org_country').value = data.country;
                if (data.purpose) document.getElementById('s_org_purpose').value = data.purpose;
                var info = [];
                if (data.name)     info.push('<strong>' + data.name + '</strong>');
                if (data.street)   info.push(data.street + (data.npa ? ', ' + data.npa : '') + (data.city ? ' ' + data.city : ''));
                el.innerHTML = '<span class="text-success"><i class="fas fa-check me-1"></i>' + info.join(' — ') + '</span>';
              }, zLabels);
            }());
            </script>
            <?php endif ?>
          </div><!-- #tab-settings -->

          <!-- Types compta (manager+) -->
          <div class="tab-pane fade<?= $_paneClass('compta') ?>" id="tab-compta" role="tabpanel" aria-labelledby="tab-compta-btn">
            <?php if (!isManager()): ?>
            <div class="alert alert-danger mt-3" role="alert"><i class="fas fa-lock me-2" aria-hidden="true"></i><?= $GLOBAL['adminOnly'] ?></div>
            <?php elseif ($_ctSchemaPending): ?>
            <?php include __DIR__ . '/settings_schema_pending_notice.php'; ?>
            <?php else: $ctEmbedded = true; $ctReturnView = 'settings'; $ctReturnTab = 'compta'; include __DIR__ . '/settings_compta_types.php'; endif ?>
          </div><!-- #tab-compta -->

          <!-- Type de contact (admin only) -->
          <div class="tab-pane fade<?= $_paneClass('contactTypes') ?>" id="tab-contactTypes" role="tabpanel" aria-labelledby="tab-contactTypes-btn">
            <?php if (!isAdmin()): ?>
            <div class="alert alert-danger mt-3" role="alert"><i class="fas fa-lock me-2" aria-hidden="true"></i><?= $GLOBAL['adminOnly'] ?></div>
            <?php elseif ($_ctSchemaPending): ?>
            <?php include __DIR__ . '/settings_schema_pending_notice.php'; ?>
            <?php else: $_ctEmbedded = true; include __DIR__ . '/settings_contact_types.php'; endif ?>
          </div><!-- #tab-contactTypes -->

          <!-- Groupes -->
          <div class="tab-pane fade<?= $_paneClass('groups') ?>" id="tab-groups" role="tabpanel" aria-labelledby="tab-groups-btn">
            <div class="mt-1">
            <?php if (($_REQUEST['view'] ?? '') === 'updateSegment'): include __DIR__ . '/settings_group_edit.php'; else: include __DIR__ . '/settings_groups.php'; endif; ?>
            </div>
          </div><!-- #tab-groups -->

          <!-- Catégories -->
          <div class="tab-pane fade<?= $_paneClass('categories') ?>" id="tab-categories" role="tabpanel" aria-labelledby="tab-categories-btn">
            <div class="card mb-4">
            <div class="card-header"><i class="fas fa-tag me-1" aria-hidden="true"></i><?= $GLOBAL['categories'] ?></div>
            <div class="card-body">
            <?php include __DIR__ . '/settings_categories.php'; ?>
            </div><!-- .card-body -->
            </div><!-- .card -->
          </div><!-- #tab-categories -->

          <!-- Segments combinés -->
          <div class="tab-pane fade<?= $_paneClass('filters') ?>" id="tab-filters" role="tabpanel" aria-labelledby="tab-filters-btn">
            <?php if (($_REQUEST['view'] ?? '') === 'updateCombinedSegment'): include __DIR__ . '/settings_filter_edit.php'; else: ?>
            <div class="card mb-4">
            <div class="card-header"><i class="fas fa-layer-group me-1" aria-hidden="true"></i><?= $GLOBAL['combinedSegments'] ?></div>
            <div class="card-body">
            <?php include __DIR__ . '/settings_filters.php'; ?>
            </div><!-- .card-body -->
            </div><!-- .card -->
            <?php endif ?>
          </div><!-- #tab-filters -->

          <?php if (isAdmin()): ?>
          <!-- Email / SMTP -->
          <div class="tab-pane fade<?= $_paneClass('email') ?>" id="tab-email" role="tabpanel" aria-labelledby="tab-email-btn">
            <?php include __DIR__ . '/settings_email.php'; ?>
          </div><!-- #tab-email -->

          <!-- Utilisateurs app -->
          <div class="tab-pane fade<?= $_paneClass('users') ?>" id="tab-users" role="tabpanel" aria-labelledby="tab-users-btn">
            <?php $_stEmbedded = true; include __DIR__ . '/settings_app_users.php'; ?>
          </div><!-- #tab-users -->

          <!-- Journal d'activité -->
          <div class="tab-pane fade<?= $_paneClass('audit') ?>" id="tab-audit" role="tabpanel" aria-labelledby="tab-audit-btn">
            <?php $_stEmbedded = true; include __DIR__ . '/settings_audit_log.php'; ?>
          </div><!-- #tab-audit -->

          <!-- Intégrité -->
          <div class="tab-pane fade<?= $_paneClass('integrity') ?>" id="tab-integrity" role="tabpanel" aria-labelledby="tab-integrity-btn">
            <div class="mt-1 col-md-10">
            <?php include __DIR__ . '/settings_integrity.php'; ?>
            </div>
          </div><!-- #tab-integrity -->

          <!-- Santé / observabilité -->
          <div class="tab-pane fade<?= $_paneClass('health') ?>" id="tab-health" role="tabpanel" aria-labelledby="tab-health-btn">
            <div class="mt-1 col-md-10">
            <?php include __DIR__ . '/settings_health.php'; ?>
            </div>
          </div><!-- #tab-health -->
          <?php endif ?>

        </div><!-- .tab-content -->

</div>
