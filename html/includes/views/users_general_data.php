<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * General data tab form for viewing and editing a member's core information.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$_sexeLabels = ['m' => $GLOBAL['m'], 'f' => $GLOBAL['f'], 'hf' => $GLOBAL['hf'], 'na' => $GLOBAL['na']];
$_sexeDisplay = $_sexeLabels[$user->sexe] ?? htmlentities((string)$user->sexe, ENT_COMPAT, $charset);
?>
<?php if (!empty($_savedOk)): ?><div id="casa-save-ok" hidden></div><?php endif ?>

<div x-data="memberGeneralForm()">

    <!-- VIEW MODE -->
    <div x-show="!editing" x-cloak
         @click="startEdit()"
         class="member-view-card"
         role="button" tabindex="0" aria-label="Cliquer pour modifier"
         @keydown.enter="startEdit()" @keydown.space.prevent="startEdit()">

        <div class="member-view-hint text-muted small mb-2">
            <i class="fas fa-pencil" aria-hidden="true"></i> Cliquer pour modifier
        </div>

        <p class="form-section-title"><?= $GLOBAL['contactInfo'] ?></p>

        <?php if ($user->getSociety()): ?>
        <div class="member-view-row">
            <span class="member-view-label"><?= $GLOBAL['society'] ?></span>
            <span class="member-view-value"><?= htmlentities($user->getSociety(), ENT_COMPAT, $charset) ?></span>
        </div>
        <?php endif ?>
        <div class="member-view-row">
            <span class="member-view-label"><?= $GLOBAL['lastName'] ?></span>
            <span class="member-view-value"><?= htmlentities($user->getLastName(), ENT_COMPAT, $charset) ?></span>
        </div>
        <div class="member-view-row">
            <span class="member-view-label"><?= $GLOBAL['firstName'] ?></span>
            <span class="member-view-value"><?= htmlentities($user->getFirstName(), ENT_COMPAT, $charset) ?></span>
        </div>
        <?php if ($user->sexe && $user->sexe !== 'na'): ?>
        <div class="member-view-row">
            <span class="member-view-label"><?= $GLOBAL['sexe'] ?></span>
            <span class="member-view-value"><?= htmlentities($_sexeDisplay, ENT_COMPAT, $charset) ?></span>
        </div>
        <?php endif ?>
        <?php if ($user->getTitle()): ?>
        <div class="member-view-row">
            <span class="member-view-label"><?= $GLOBAL['title'] ?></span>
            <span class="member-view-value"><?= htmlentities($user->getTitle(), ENT_COMPAT, $charset) ?></span>
        </div>
        <?php endif ?>
        <?php if ($user->getAddress() || $user->getNpa()): ?>
        <div class="member-view-row">
            <span class="member-view-label"><?= $GLOBAL['address'] ?></span>
            <span class="member-view-value">
                <?= htmlentities($user->getAddress(), ENT_COMPAT, $charset) ?>
                <?php if ($user->getNpa()): ?>
                    <br><?= htmlentities($user->getNpa(), ENT_COMPAT, $charset) ?>
                <?php endif ?>
            </span>
        </div>
        <?php endif ?>
        <?php if ($user->getEmail()): ?>
        <div class="member-view-row">
            <span class="member-view-label"><i class="fas fa-envelope" aria-hidden="true"></i> <?= $GLOBAL['email'] ?></span>
            <span class="member-view-value"><?= htmlentities($user->getEmail(), ENT_COMPAT, $charset) ?></span>
        </div>
        <?php endif ?>
        <?php if ($user->getWeb()): ?>
        <div class="member-view-row">
            <span class="member-view-label"><i class="fas fa-globe" aria-hidden="true"></i> <?= $GLOBAL['web'] ?></span>
            <span class="member-view-value"><?= htmlentities($user->getWeb(), ENT_COMPAT, $charset) ?></span>
        </div>
        <?php endif ?>

        <?php if ($user->getTelProf() || $user->getTel() || $user->getPortable() || $user->getFax() || $user->getBirthDay() || $user->getComment()): ?>
        <p class="form-section-title"><?= $GLOBAL['additionalInfo'] ?></p>
        <?php if ($user->getTelProf()): ?>
        <div class="member-view-row">
            <span class="member-view-label"><i class="fas fa-phone" aria-hidden="true"></i> <?= $GLOBAL['telProf'] ?></span>
            <span class="member-view-value"><?= htmlentities($user->getTelProf(), ENT_COMPAT, $charset) ?></span>
        </div>
        <?php endif ?>
        <?php if ($user->getTel()): ?>
        <div class="member-view-row">
            <span class="member-view-label"><i class="fas fa-phone" aria-hidden="true"></i> <?= $GLOBAL['tel'] ?></span>
            <span class="member-view-value"><?= htmlentities($user->getTel(), ENT_COMPAT, $charset) ?></span>
        </div>
        <?php endif ?>
        <?php if ($user->getPortable()): ?>
        <div class="member-view-row">
            <span class="member-view-label"><i class="fas fa-mobile-screen-button" aria-hidden="true"></i> <?= $GLOBAL['portable'] ?></span>
            <span class="member-view-value"><?= htmlentities($user->getPortable(), ENT_COMPAT, $charset) ?></span>
        </div>
        <?php endif ?>
        <?php if ($user->getFax()): ?>
        <div class="member-view-row">
            <span class="member-view-label"><i class="fas fa-print" aria-hidden="true"></i> <?= $GLOBAL['fax'] ?></span>
            <span class="member-view-value"><?= htmlentities($user->getFax(), ENT_COMPAT, $charset) ?></span>
        </div>
        <?php endif ?>
        <?php if ($user->getBirthDay()): ?>
        <div class="member-view-row">
            <span class="member-view-label"><?= $GLOBAL['birthDay'] ?></span>
            <span class="member-view-value"><?= timeStampToformatedDate($user->getBirthDay()) ?></span>
        </div>
        <?php endif ?>
        <?php if ($user->getComment()): ?>
        <div class="member-view-row">
            <span class="member-view-label"><?= $GLOBAL['compet'] ?></span>
            <span class="member-view-value"><?= $user->getComment() /* already stored as HTML */ ?></span>
        </div>
        <?php endif ?>
        <?php endif ?>

        <div class="mt-3 d-flex align-items-center gap-3 flex-wrap">
            <?php
            $_noCotiTeamGd = (int)($appSettings['member_no_coti_team'] ?? 0);
            $_showCotiWarn = (int)$_stats->ever_coti > 0
                && (int)$_stats->coti_this_year === 0
                && ($_noCotiTeamGd === 0 || !$user->isMemberOfTeam($_noCotiTeamGd));
            ?>
            <?php if ($_showCotiWarn): ?>
                <span class="badge bg-danger">Cotisation <?= date("Y") ?> non payée</span>
            <?php endif ?>
            <span class="text-muted small ms-auto">
                <?php if ($user->getCreationDate()): ?>
                    Créé: <?= timeStampToformatedDate($user->getCreationDate()) ?>
                <?php endif ?>
                <?php if ($user->getModificationDate()): ?>
                    &nbsp;· Modifié: <?= timeStampToformatedDate($user->getModificationDate()) ?>
                <?php endif ?>
            </span>
        </div>
    </div>

    <!-- EDIT MODE -->
    <form x-show="editing" x-cloak
          action="<?= $_SERVER['PHP_SELF'] ?>" method="post" name="updateUser" role="form"
          data-no-dirty>
        <input type="hidden" name="id" value="<?= $user->getId() ?>"/>
        <input type="hidden" name="action" value="updateUser"/>
        <input type="hidden" name="view" value="generalData"/>

        <p class="form-section-title"><?= $GLOBAL['contactInfo'] ?></p>

        <div class="row mb-2">
            <label for="society" class="col-md-3 col-form-label"><?= $GLOBAL['society'] ?></label>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-sm" name="society" id="society"
                       value="<?= htmlentities($user->getSociety(), ENT_COMPAT, $charset) ?>"/>
            </div>
        </div>
        <div class="row mb-2">
            <label for="lastName" class="col-md-3 col-form-label"><?= $GLOBAL['lastName'] ?></label>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-sm" name="lastName" id="lastName"
                       value="<?= htmlentities($user->getLastName(), ENT_COMPAT, $charset) ?>"/>
            </div>
        </div>
        <div class="row mb-2">
            <label for="firstName" class="col-md-3 col-form-label"><?= $GLOBAL['firstName'] ?></label>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-sm" name="firstName" id="firstName"
                       value="<?= htmlentities($user->getFirstName(), ENT_COMPAT, $charset) ?>"/>
            </div>
        </div>
        <div class="row mb-2">
            <label for="sexe" class="col-md-3 col-form-label"><?= $GLOBAL['sexe'] ?></label>
            <div class="col-md-9">
                <select name="sexe" id="sexe" class="form-select form-select-sm">
                    <option value="na"<?php if ($user->sexe == "na") { ?> selected<?php } ?>><?= $GLOBAL['na'] ?></option>
                    <option value="hf"<?php if ($user->sexe == "hf") { ?> selected<?php } ?>><?= $GLOBAL['hf'] ?></option>
                    <option value="f"<?php if ($user->sexe == "f") { ?> selected<?php } ?>><?= $GLOBAL['f'] ?></option>
                    <option value="m"<?php if ($user->sexe == "m") { ?> selected<?php } ?>><?= $GLOBAL['m'] ?></option>
                </select>
            </div>
        </div>
        <div class="row mb-2">
            <label for="title" class="col-md-3 col-form-label"><?= $GLOBAL['title'] ?></label>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-sm" name="title" id="title"
                       value="<?= htmlentities($user->getTitle(), ENT_COMPAT, $charset) ?>"/>
            </div>
        </div>
        <div class="row mb-2">
            <label for="address" class="col-md-3 col-form-label">
                <i class="fas fa-home" aria-hidden="true"></i> <?= $GLOBAL['address'] ?>
                <br>
                <a href="https://www.google.ch/maps/place/<?= urlencode($user->getAddress() . ',' . $user->getNpa()) ?>" target="_blank">
                    <i class="fas fa-location-dot" aria-hidden="true"></i> map
                </a>
            </label>
            <div class="col-md-9">
                <textarea class="form-control form-control-sm" rows="2" name="address" id="address"><?= htmlentities($user->getAddress(), ENT_COMPAT, $charset) ?></textarea>
            </div>
        </div>
        <div class="row mb-2">
            <label for="npa" class="col-md-3 col-form-label"><?= $GLOBAL['npa'] ?></label>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-sm" name="npa" id="npa"
                       value="<?= htmlentities($user->getNpa(), ENT_COMPAT, $charset) ?>"/>
            </div>
        </div>
        <div class="row mb-2">
            <label for="email" class="col-md-3 col-form-label">
                <i class="fas fa-envelope" aria-hidden="true"></i> <?= $GLOBAL['email'] ?>
            </label>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-sm" name="email" id="email"
                       value="<?= htmlentities($user->getEmail(), ENT_COMPAT, $charset) ?>"/>
            </div>
        </div>
        <div class="row mb-2">
            <label for="web" class="col-md-3 col-form-label">
                <i class="fas fa-globe" aria-hidden="true"></i> <?= $GLOBAL['web'] ?>
            </label>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-sm" name="web" id="web"
                       value="<?= htmlentities($user->getWeb(), ENT_COMPAT, $charset) ?>"/>
            </div>
        </div>

        <p class="form-section-title"><?= $GLOBAL['additionalInfo'] ?></p>

        <div class="row mb-2">
            <label for="telProf" class="col-md-3 col-form-label">
                <i class="fas fa-phone" aria-hidden="true"></i> <?= $GLOBAL['telProf'] ?>
            </label>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-sm" name="telProf" id="telProf"
                       value="<?= htmlentities($user->getTelProf(), ENT_COMPAT, $charset) ?>"/>
            </div>
        </div>
        <div class="row mb-2">
            <label for="tel" class="col-md-3 col-form-label">
                <i class="fas fa-phone" aria-hidden="true"></i> <?= $GLOBAL['tel'] ?>
            </label>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-sm" name="tel" id="tel"
                       value="<?= htmlentities($user->getTel(), ENT_COMPAT, $charset) ?>"/>
            </div>
        </div>
        <div class="row mb-2">
            <label for="portable" class="col-md-3 col-form-label">
                <i class="fas fa-mobile-screen-button" aria-hidden="true"></i> <?= $GLOBAL['portable'] ?>
            </label>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-sm" name="portable" id="portable"
                       value="<?= htmlentities($user->getPortable(), ENT_COMPAT, $charset) ?>"/>
            </div>
        </div>
        <div class="row mb-2">
            <label for="fax" class="col-md-3 col-form-label">
                <i class="fas fa-print" aria-hidden="true"></i> <?= $GLOBAL['fax'] ?>
            </label>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-sm" name="fax" id="fax"
                       value="<?= htmlentities($user->getFax(), ENT_COMPAT, $charset) ?>"/>
            </div>
        </div>
        <div class="row mb-2">
            <label for="birthDay" class="col-md-3 col-form-label"><?= $GLOBAL['birthDay'] ?></label>
            <div class="col-md-9">
                <div class="input-group input-group-sm" id="datetimepicker1">
                    <input type="text" id="birthDay" name="birthDay" class="form-control datepicker"
                           value="<?= timeStampToformatedDate($user->getBirthDay()) ?>"/>
                </div>
            </div>
        </div>
        <div class="row mb-2">
            <label for="tiptap-comment" class="col-md-3 col-form-label"><?= $GLOBAL['compet'] ?></label>
            <div class="col-md-9">
                <div class="tiptap-wrap border rounded" id="tiptap-wrap-comment">
                    <div class="tiptap-toolbar d-flex gap-1 px-2 py-1 border-bottom bg-light rounded-top flex-wrap" role="toolbar" aria-label="Formatage">
                        <button type="button" class="tt-btn" data-tt="bold" title="Gras (Ctrl+B)" aria-label="Gras"><i class="fas fa-bold"></i></button>
                        <button type="button" class="tt-btn" data-tt="italic" title="Italique (Ctrl+I)" aria-label="Italique"><i class="fas fa-italic"></i></button>
                        <span class="tt-sep"></span>
                        <button type="button" class="tt-btn" data-tt="bulletList" title="Liste à puces" aria-label="Liste à puces"><i class="fas fa-list-ul"></i></button>
                        <button type="button" class="tt-btn" data-tt="orderedList" title="Liste numérotée" aria-label="Liste numérotée"><i class="fas fa-list-ol"></i></button>
                        <span class="tt-sep"></span>
                        <button type="button" class="tt-btn" data-tt="undo" title="Annuler (Ctrl+Z)" aria-label="Annuler"><i class="fas fa-rotate-left"></i></button>
                        <button type="button" class="tt-btn" data-tt="redo" title="Rétablir (Ctrl+Shift+Z)" aria-label="Rétablir"><i class="fas fa-rotate-right"></i></button>
                    </div>
                    <div id="tiptap-comment" class="tiptap-body px-3 py-2" style="min-height:80px;cursor:text" aria-label="<?= htmlspecialchars($GLOBAL['compet'], ENT_QUOTES, 'UTF-8') ?>" aria-multiline="true"></div>
                </div>
                <textarea name="comment" id="comment" style="display:none"><?= htmlentities($user->getComment(), ENT_COMPAT, $charset) ?></textarea>
            </div>
        </div>

        <div class="mt-3 d-flex align-items-center gap-3 flex-wrap">
            <button type="submit" class="btn btn-primary btn-sm"><?= $GLOBAL['update'] ?></button>
            <button type="button" class="btn btn-outline-secondary btn-sm" @click="cancel()"><?= $GLOBAL['cancel'] ?></button>
            <?php if ($_showCotiWarn): ?>
                <span class="badge bg-danger">Cotisation <?= date("Y") ?> non payée</span>
            <?php endif ?>
            <span class="text-muted small ms-auto">
                <?php if ($user->getCreationDate()): ?>
                    Créé: <?= timeStampToformatedDate($user->getCreationDate()) ?>
                <?php endif ?>
                <?php if ($user->getModificationDate()): ?>
                    &nbsp;· Modifié: <?= timeStampToformatedDate($user->getModificationDate()) ?>
                <?php endif ?>
            </span>
        </div>
    </form>

</div>
