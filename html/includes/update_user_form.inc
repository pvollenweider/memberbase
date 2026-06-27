<?php
if ($userid == -1) {
    if (isset($_REQUEST['userid'])) {
        $userid = $_REQUEST['userid'];
    } else {
        $userid = $_REQUEST['id'];
    }
}
$user = new User();
$user->lookupUser($userid);

// Stats for badges + mini-dashboard
$_year = (int)date('Y');
$_tsThisYear = mktime(0,0,0,1,1,$_year);
$_tsNextYear = mktime(0,0,0,1,1,$_year+1);
$_tsLastYear = mktime(0,0,0,1,1,$_year-1);
$_stStats = $pdo->prepare("
    SELECT
        COUNT(*) AS compta_count,
        -- dons (is_excluded_from_donation = 0)
        SUM(CASE WHEN COALESCE(ct.is_excluded_from_donation,0)=0 THEN 1 ELSE 0 END) AS don_count,
        COALESCE(SUM(CASE WHEN COALESCE(ct.is_excluded_from_donation,0)=0 THEN CAST(c.`sum` AS DECIMAL(10,2)) ELSE 0 END), 0) AS total_amount,
        COALESCE(SUM(CASE WHEN COALESCE(ct.is_excluded_from_donation,0)=0 AND c.date >= ? AND c.date < ? THEN CAST(c.`sum` AS DECIMAL(10,2)) ELSE 0 END), 0) AS this_year_amount,
        COALESCE(SUM(CASE WHEN COALESCE(ct.is_excluded_from_donation,0)=0 AND c.date >= ? AND c.date < ? THEN CAST(c.`sum` AS DECIMAL(10,2)) ELSE 0 END), 0) AS last_year_amount,
        SUM(CASE WHEN COALESCE(ct.is_excluded_from_donation,0)=0 AND c.date >= ? AND c.date < ? THEN 1 ELSE 0 END) AS this_year_count,
        SUM(CASE WHEN COALESCE(ct.is_excluded_from_donation,0)=0 AND c.date >= ? AND c.date < ? THEN 1 ELSE 0 END) AS last_year_count,
        -- autres versements (is_excluded_from_donation = 1)
        SUM(CASE WHEN COALESCE(ct.is_excluded_from_donation,0)=1 THEN 1 ELSE 0 END) AS other_count,
        COALESCE(SUM(CASE WHEN COALESCE(ct.is_excluded_from_donation,0)=1 THEN CAST(c.`sum` AS DECIMAL(10,2)) ELSE 0 END), 0) AS other_amount,
        COALESCE(SUM(CASE WHEN COALESCE(ct.is_excluded_from_donation,0)=1 AND c.date >= ? AND c.date < ? THEN CAST(c.`sum` AS DECIMAL(10,2)) ELSE 0 END), 0) AS other_this_year_amount,
        SUM(CASE WHEN COALESCE(ct.is_excluded_from_donation,0)=1 AND c.date >= ? AND c.date < ? THEN 1 ELSE 0 END) AS other_this_year_count,
        COALESCE(SUM(CASE WHEN COALESCE(ct.is_excluded_from_donation,0)=1 AND c.date >= ? AND c.date < ? THEN CAST(c.`sum` AS DECIMAL(10,2)) ELSE 0 END), 0) AS other_last_year_amount,
        SUM(CASE WHEN COALESCE(ct.is_excluded_from_donation,0)=1 AND c.date >= ? AND c.date < ? THEN 1 ELSE 0 END) AS other_last_year_count,
        -- cotisation
        SUM(CASE WHEN COALESCE(ct.is_cotisation,0)=1 THEN 1 ELSE 0 END) AS ever_coti,
        SUM(CASE WHEN COALESCE(ct.is_cotisation,0)=1 AND c.date >= ? AND c.date < ? THEN 1 ELSE 0 END) AS coti_this_year,
        -- années du premier versement par catégorie
        MIN(CASE WHEN COALESCE(ct.is_excluded_from_donation,0)=0 THEN c.date ELSE NULL END) AS don_first_ts,
        MIN(CASE WHEN COALESCE(ct.is_excluded_from_donation,0)=1 THEN c.date ELSE NULL END) AS other_first_ts,
        MIN(c.date) AS all_first_ts,
        -- total tous versements
        COALESCE(SUM(CAST(c.`sum` AS DECIMAL(10,2))), 0) AS all_time_amount
    FROM compta c
    LEFT JOIN compta_type ct ON ct.id = c.type_id
    WHERE c.user_id=?
");
$_stStats->execute([
    $_tsThisYear, $_tsNextYear,  // this_year_amount (don)
    $_tsLastYear, $_tsThisYear,  // last_year_amount (don)
    $_tsThisYear, $_tsNextYear,  // this_year_count (don)
    $_tsLastYear, $_tsThisYear,  // last_year_count (don)
    $_tsThisYear, $_tsNextYear,  // other_this_year_amount
    $_tsThisYear, $_tsNextYear,  // other_this_year_count
    $_tsLastYear, $_tsThisYear,  // other_last_year_amount
    $_tsLastYear, $_tsThisYear,  // other_last_year_count
    $_tsThisYear, $_tsNextYear,  // coti_this_year
    $user->getId()
]);
$_stats = $_stStats->fetchObject();
// Per-type breakdown of "autres versements" — this year / last year / total
$_stOtherTypes = $pdo->prepare("
    SELECT ct.label,
        COUNT(*) AS cnt,
        COALESCE(SUM(CAST(c.`sum` AS DECIMAL(10,2))),0) AS amount,
        COALESCE(SUM(CASE WHEN c.date >= ? AND c.date < ? THEN CAST(c.`sum` AS DECIMAL(10,2)) ELSE 0 END),0) AS this_year_amount,
        SUM(CASE WHEN c.date >= ? AND c.date < ? THEN 1 ELSE 0 END) AS this_year_count,
        COALESCE(SUM(CASE WHEN c.date >= ? AND c.date < ? THEN CAST(c.`sum` AS DECIMAL(10,2)) ELSE 0 END),0) AS last_year_amount,
        SUM(CASE WHEN c.date >= ? AND c.date < ? THEN 1 ELSE 0 END) AS last_year_count
    FROM compta c
    LEFT JOIN compta_type ct ON ct.id = c.type_id
    WHERE c.user_id=? AND COALESCE(ct.is_excluded_from_donation,0)=1
    GROUP BY ct.id, ct.label
    ORDER BY ct.sort_order ASC, ct.label ASC
");
$_stOtherTypes->execute([
    $_tsThisYear, $_tsNextYear,
    $_tsThisYear, $_tsNextYear,
    $_tsLastYear, $_tsThisYear,
    $_tsLastYear, $_tsThisYear,
    $user->getId()
]);
$_otherTypes = $_stOtherTypes->fetchAll(PDO::FETCH_OBJ);
$_suiviStmt = $pdo->prepare("SELECT COUNT(*) FROM user_properties WHERE user_id=? AND parameter='suivi'");
$_suiviStmt->execute([$user->getId()]);
$_suiviCount = (int)$_suiviStmt->fetchColumn();
?>

<nav class="navbar navbar-expand-lg navbar-light bg-light mb-3">
    <a class="navbar-brand" href="<?= $_SERVER['PHP_SELF'] ?>?view=generalData&amp;userid=<?= $user->getId() ?>"><?= htmlentities($user->getSociety(), ENT_COMPAT, $charset) ?> <?= htmlentities($user->getFirstName(), ENT_COMPAT, $charset) ?> <?= htmlentities($user->getLastName(), ENT_COMPAT, $charset) ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#userNavContent" aria-controls="userNavContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="userNavContent">
        <ul class="navbar-nav me-auto">
            <li class="nav-item<?php if ($view == "generalData") { ?> active<?php } ?>">
                <a class="nav-link" href="<?= $_SERVER['PHP_SELF'] ?>?view=generalData&amp;userid=<?= $user->getId() ?>"><i class="far fa-id-card"></i> <?= $GLOBAL['generalData'] ?></a>
            </li>
            <li class="nav-item<?php if ($view == "compta") { ?> active<?php } ?>">
                <a class="nav-link" href="<?= $_SERVER['PHP_SELF'] ?>?view=compta&amp;userid=<?= $user->getId() ?>">
                    <i class="fas fa-file-contract"></i> <?= $GLOBAL['compta'] ?>
                    <?php if ((int)$_stats->compta_count > 0): ?>
                    <span class="ms-1 text-muted" style="font-size:0.7rem;opacity:0.6"><?= (int)$_stats->compta_count ?></span>
                    <?php endif ?>
                </a>
            </li>
            <li class="nav-item<?php if ($view == "suivi") { ?> active<?php } ?>">
                <a class="nav-link" href="<?= $_SERVER['PHP_SELF'] ?>?view=suivi&amp;userid=<?= $user->getId() ?>">
                    <i class="far fa-list-alt"></i> <?= $GLOBAL['suivi'] ?>
                    <?php if ($_suiviCount > 0): ?>
                    <span class="ms-1 text-muted" style="font-size:0.7rem;opacity:0.6"><?= $_suiviCount ?></span>
                    <?php endif ?>
                </a>
            </li>
            <?php if (isAdmin()): ?>
            <li class="nav-item<?php if ($view == "userHistory") { ?> active<?php } ?>">
                <a class="nav-link" href="<?= $_SERVER['PHP_SELF'] ?>?view=userHistory&amp;userid=<?= $user->getId() ?>"><i class="fas fa-history"></i> Historique</a>
            </li>
            <?php endif ?>
        </ul>
        <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-flex align-items-center ms-auto" data-no-dirty id="status-toggle-form">
          <input type="hidden" name="action"   value="<?= $user->status ? 'deactivateUser' : 'reactivateUser' ?>">
          <input type="hidden" name="id"       value="<?= (int)$user->getId() ?>">
          <input type="hidden" name="redirect" value="updateUser">
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" role="switch" id="status-toggle"
                   <?= $user->status ? 'checked' : '' ?>
                   onchange="<?= $user->status
                     ? 'this.checked=true;var m=new bootstrap.Modal(document.getElementById(\'deactivate-modal\'));m.show()'
                     : 'document.getElementById(\'status-toggle-form\').submit()' ?>">
            <label class="form-check-label" for="status-toggle"><?= $user->status ? 'Actif' : 'Archivé' ?></label>
          </div>
        </form>
    </div>
</nav>

<?php if ($user->status): ?>
<div class="modal fade" id="deactivate-modal" tabindex="-1" aria-labelledby="deactivate-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:380px">
    <div class="modal-content">
      <div class="modal-body p-4 text-center">
        <div class="mb-3" style="font-size:2rem;color:var(--ca-ink-muted)">
          <i class="fas fa-eye-slash" aria-hidden="true"></i>
        </div>
        <h6 class="mb-1" id="deactivate-modal-label">Archiver ce membre&nbsp;?</h6>
        <p class="text-muted mb-4" style="font-size:0.83rem">
          Le profil sera retiré de toutes les listes.<br>Désarchivable à tout moment.
        </p>
        <div class="d-flex gap-2 justify-content-center">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="button" class="btn btn-danger"
                  onclick="bootstrap.Modal.getInstance(document.getElementById('deactivate-modal')).hide();document.getElementById('status-toggle-form').submit()">
            <i class="fas fa-archive me-1" aria-hidden="true"></i>Archiver
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif ?>

<?php
if ($view == "compta") {

    //include "avocat.inc";
    //include "cd.inc";
    //include "coti.inc";
    //include "dons.inc";
    //include "reintegration.inc";
    include "compta_generic.inc";
} else if ($view == "suivi") {
    ?><?php include "suivi.inc"; ?><?php
} else if ($view == "userHistory") {
    ?><?php include "user_history.inc"; ?><?php
} else {
    ?>
    <?php if (!$user->status): ?>
    <div class="ca-inactive-banner alert alert-warning d-flex align-items-center gap-2 mb-3 py-2" role="alert">
      <i class="fas fa-eye-slash" aria-hidden="true"></i>
      <span>Ce profil est <strong>archivé</strong> — il n'apparaît dans aucune liste.</span>
    </div>
    <?php endif ?>
    <div class="position-relative <?= !$user->status ? 'ca-inactive-wrap' : '' ?>" x-data="{ showAll: false }">
      <?php if (!$user->status): ?>
      <div class="ca-inactive-overlay" aria-hidden="true"></div>
      <?php endif ?>

      <!-- Carte lecture mobile (masquée sur md+) -->
      <div class="d-md-none mb-3 p-3 rounded border" style="background:var(--ca-ground)">
        <?php $_muSociety = trim((string)$user->getSociety()); $_muName = trim($user->getFirstName().' '.$user->getLastName()); ?>
        <?php if ($_muSociety): ?>
        <div class="fw-semibold" style="font-size:1rem"><?= htmlspecialchars($_muSociety, ENT_QUOTES, $charset) ?></div>
        <?php endif ?>
        <?php if ($_muName): ?>
        <div class="<?= $_muSociety ? 'text-muted' : 'fw-semibold' ?>" style="font-size:<?= $_muSociety ? '0.9rem' : '1rem' ?>"><?= htmlspecialchars($_muName, ENT_QUOTES, $charset) ?></div>
        <?php endif ?>
        <?php if ($user->getEmail()): ?>
        <div class="mt-2"><a href="mailto:<?= htmlspecialchars($user->getEmail(), ENT_QUOTES, $charset) ?>" class="text-decoration-none">
          <i class="fas fa-envelope fa-fw me-1 text-muted" aria-hidden="true"></i><?= htmlspecialchars($user->getEmail(), ENT_QUOTES, $charset) ?>
        </a></div>
        <?php endif ?>
        <?php if ($user->getPortable()): ?>
        <div><a href="tel:<?= htmlspecialchars(preg_replace('/\s+/','',$user->getPortable()), ENT_QUOTES, $charset) ?>" class="text-decoration-none">
          <i class="fas fa-mobile-alt fa-fw me-1 text-muted" aria-hidden="true"></i><?= htmlspecialchars($user->getPortable(), ENT_QUOTES, $charset) ?>
        </a></div>
        <?php endif ?>
        <?php if ($user->getTel()): ?>
        <div><a href="tel:<?= htmlspecialchars(preg_replace('/\s+/','',$user->getTel()), ENT_QUOTES, $charset) ?>" class="text-decoration-none">
          <i class="fas fa-phone fa-fw me-1 text-muted" aria-hidden="true"></i><?= htmlspecialchars($user->getTel(), ENT_QUOTES, $charset) ?>
        </a></div>
        <?php endif ?>
        <button type="button" class="btn btn-sm btn-outline-secondary mt-3 w-100"
                @click="showAll = !showAll"
                x-text="showAll ? 'Masquer les champs' : 'Voir tous les champs'">
        </button>
      </div>

      <div class="row">
          <div class="col-md-8 ca-mobile-expandable" x-show="showAll">
              <?php include "generalData.inc"; ?>
          </div>
          <div class="col-md-4 small">
              <?php include "memberOf.inc"; ?>
              <?php if ((int)$_stats->don_count > 0): ?>
              <div class="ca-stats-mini mt-3 p-3 rounded border" style="background:var(--bs-light)">
                <div class="fw-semibold mb-2 text-muted" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:.05em">
                  <i class="fas fa-hand-holding-heart me-1" aria-hidden="true"></i>Dons
                </div>
                <div class="d-flex flex-column gap-1">
                  <div class="d-flex justify-content-between align-items-baseline">
                    <span style="font-size:0.8rem"><?= $_year ?></span>
                    <span class="fw-bold" style="font-size:0.95rem">
                      <?php if ((int)$_stats->this_year_count > 0): ?>
                        <?= number_format((float)$_stats->this_year_amount, 2, '.', "'") ?> <small class="text-muted fw-normal" style="font-size:0.7rem">CHF</small>
                      <?php else: ?><span class="text-muted" style="font-size:0.8rem">—</span><?php endif ?>
                    </span>
                  </div>
                  <div class="d-flex justify-content-between align-items-baseline text-muted">
                    <span style="font-size:0.8rem"><?= $_year - 1 ?></span>
                    <span style="font-size:0.85rem">
                      <?php if ((int)$_stats->last_year_count > 0): ?>
                        <?= number_format((float)$_stats->last_year_amount, 2, '.', "'") ?> <small style="font-size:0.7rem">CHF</small>
                      <?php else: ?>—<?php endif ?>
                    </span>
                  </div>
                  <div class="d-flex justify-content-between align-items-baseline border-top pt-1 mt-1">
                    <span style="font-size:0.75rem;color:var(--ca-ink-muted)">Total depuis <?= $_stats->don_first_ts ? date('Y', (int)$_stats->don_first_ts) : '—' ?></span>
                    <span class="fw-semibold" style="font-size:0.85rem">
                      <?= number_format((float)$_stats->total_amount, 2, '.', "'") ?> <small class="fw-normal text-muted" style="font-size:0.7rem">CHF</small>
                    </span>
                  </div>
                </div>
              </div>
              <?php endif ?>
              <?php if ((int)$_stats->other_count > 0): ?>
              <?php $_otherTypeLabels = implode(', ', array_map(fn($t) => htmlentities((string)$t->label, ENT_COMPAT, $charset), array_filter($_otherTypes, fn($t) => (int)$t->this_year_count > 0 || (int)$t->last_year_count > 0))); ?>
              <div class="ca-stats-mini mt-2 p-3 rounded border" style="background:var(--bs-light)">
                <div class="fw-semibold mb-2 text-muted" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:.05em">
                  <i class="fas fa-receipt me-1" aria-hidden="true"></i>Autres versements<?php if ($_otherTypeLabels): ?> <span class="fw-normal text-lowercase" style="letter-spacing:0">(<?= $_otherTypeLabels ?>)</span><?php endif ?>
                </div>
                <div class="d-flex flex-column gap-1">
                  <div class="d-flex justify-content-between align-items-baseline">
                    <span style="font-size:0.8rem"><?= $_year ?></span>
                    <span class="fw-bold" style="font-size:0.95rem">
                      <?php if ((int)$_stats->other_this_year_count > 0): ?>
                        <?= number_format((float)$_stats->other_this_year_amount, 2, '.', "'") ?> <small class="text-muted fw-normal" style="font-size:0.7rem">CHF</small>
                      <?php else: ?><span class="text-muted" style="font-size:0.8rem">—</span><?php endif ?>
                    </span>
                  </div>
                  <div class="d-flex justify-content-between align-items-baseline text-muted">
                    <span style="font-size:0.8rem"><?= $_year - 1 ?></span>
                    <span style="font-size:0.85rem">
                      <?php if ((int)$_stats->other_last_year_count > 0): ?>
                        <?= number_format((float)$_stats->other_last_year_amount, 2, '.', "'") ?> <small style="font-size:0.7rem">CHF</small>
                      <?php else: ?>—<?php endif ?>
                    </span>
                  </div>
                  <div class="d-flex justify-content-between align-items-baseline border-top pt-1 mt-1">
                    <span style="font-size:0.75rem;color:var(--ca-ink-muted)">Total depuis <?= $_stats->other_first_ts ? date('Y', (int)$_stats->other_first_ts) : '—' ?></span>
                    <span class="fw-semibold" style="font-size:0.85rem">
                      <?= number_format((float)$_stats->other_amount, 2, '.', "'") ?> <small class="fw-normal text-muted" style="font-size:0.7rem">CHF</small>
                    </span>
                  </div>
                </div>
              </div>
              <?php endif ?>
              <?php if ((int)$_stats->compta_count > 0): ?>
              <div class="mt-2 px-3 py-2 rounded border d-flex justify-content-between align-items-baseline" style="background:var(--bs-light)">
                <span class="text-muted" style="font-size:0.75rem">Total depuis <?= $_stats->all_first_ts ? date('Y', (int)$_stats->all_first_ts) : '—' ?></span>
                <span class="fw-semibold" style="font-size:0.85rem">
                  <?= number_format((float)$_stats->all_time_amount, 2, '.', "'") ?> <small class="fw-normal text-muted" style="font-size:0.7rem">CHF</small>
                </span>
              </div>
              <?php endif ?>
          </div>
      </div>
    </div>
    <?php $_hasCompta = (int)$_stats->compta_count > 0; ?>
    <div class="row mt-3">
      <div class="col-md-12 d-flex align-items-center justify-content-end gap-3">

        <?php if (!$user->status): ?>
          <?php if ($_hasCompta): ?>
          <!-- Has compta → offer anonymize, not delete -->
          <a href="<?= $_SERVER['PHP_SELF'] ?>?view=anonymizeUser&amp;id=<?= (int)$user->getId() ?>"
             class="btn btn-outline-danger btn-sm"
             title="Ce profil a des données comptables — la suppression est impossible. L'anonymisation efface les données personnelles tout en conservant l'historique comptable.">
            <i class="fas fa-user-secret me-1" aria-hidden="true"></i>Anonymiser
          </a>
          <?php else: ?>
          <!-- No compta → allow delete -->
          <a href="<?= $_SERVER['PHP_SELF'] ?>?view=deleteUser&amp;id=<?= (int)$user->getId() ?>"
             class="btn btn-danger btn-sm">
            <i class="fas fa-user-times me-1" aria-hidden="true"></i>Supprimer
          </a>
          <?php endif ?>
        <?php endif ?>

      </div>
    </div>
    <?php
}


?>
