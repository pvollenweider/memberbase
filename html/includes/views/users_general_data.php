<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * General data tab — inline view/edit with Alpine.js + PUT /api/contacts/{id}.
 *
 * View mode : data displayed as text, hover reveals edit trigger.
 * Edit mode : same fields as form inputs, saved via JSON API.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

$_mid = (int)$user->getId();

$_iData = json_encode([
    'lastName'  => (string)$user->getLastName(),
    'firstName' => (string)$user->getFirstName(),
    'society'   => (string)$user->getSociety(),
    'gender'    => (string)$user->getSexe() ?: 'na',
    'title'     => (string)$user->getTitle(),
    'address'   => (string)$user->getAddress(),
    'npa'       => (string)$user->getNpa(),
    'email'     => (string)$user->getEmail(),
    'emailAlt'  => (string)$user->getEmailAlt(),
    'web'       => (string)$user->getWeb(),
    'telProf'   => (string)$user->getTelProf(),
    'tel'       => (string)$user->getTel(),
    'portable'  => (string)$user->getPortable(),
    'fax'       => (string)$user->getFax(),
    'birthDate' => $user->getBirthDay() ? date('Y-m-d', (int)$user->getBirthDay()) : '',
    'comment'   => (string)$user->getComment(),
], JSON_HEX_QUOT | JSON_HEX_TAG);

$_gLabels = json_encode([
    'na' => $GLOBAL['na'],
    'hf' => $GLOBAL['hf'],
    'f'  => $GLOBAL['f'],
    'm'  => $GLOBAL['m'],
], JSON_HEX_QUOT | JSON_HEX_TAG);

$_noCotiTeam  = (int)($appSettings['member_no_coti_team'] ?? 0);
$_showCotiWarn = (int)$_stats->ever_coti > 0
    && (int)$_stats->coti_this_year === 0
    && ($_noCotiTeam === 0 || !$user->isMemberOfSegment($_noCotiTeam));

$_createdAt  = $user->getCreationDate()     ? timeStampToformatedDate($user->getCreationDate())     : '';
$_modifiedAt = $user->getModificationDate() ? timeStampToformatedDate($user->getModificationDate()) : '';
?>
<style>
.ca-view-zone {
    position: relative;
    border-radius: 6px;
    padding: 6px 8px;
    margin: -6px -8px;
    transition: background 0.12s, outline-color 0.12s;
    outline: 1px solid transparent;
}
.ca-view-zone:hover {
    background: rgba(0,0,0,.025);
    outline-color: #ced4da;
    cursor: pointer;
}
.ca-edit-hint {
    position: absolute;
    top: 6px;
    right: 6px;
    opacity: 0;
    transition: opacity 0.12s;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 1px 8px;
    font-size: 0.72rem;
    color: #6c757d;
    pointer-events: none;
    line-height: 1.8;
}
.ca-view-zone:hover .ca-edit-hint {
    opacity: 1;
}
.ca-field-label {
    font-size: 0.78rem;
    color: var(--ca-ink-muted, #6c757d);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 1px;
}
.ca-field-value {
    color: inherit;
    min-height: 1.4em;
}
.ca-field-value.empty {
    color: #adb5bd;
    font-style: italic;
}
[x-cloak] { display: none !important; }
</style>

<div x-data="memberGeneralForm()"
     data-member-id="<?= $_mid ?>"
     data-initial="<?= htmlspecialchars($_iData, ENT_QUOTES, 'UTF-8') ?>"
     data-gender-labels="<?= htmlspecialchars($_gLabels, ENT_QUOTES, 'UTF-8') ?>"
     data-no-dirty>

    <template x-if="saved"><div id="casa-save-ok"></div></template>

    <?php if ($_showCotiWarn): ?>
    <div class="alert alert-danger py-1 px-2 mb-2 small">
        <i class="fas fa-circle-exclamation me-1" aria-hidden="true"></i>
        <?= $GLOBAL['cotiUnpayed'] ?>
    </div>
    <?php endif ?>

    <!-- ── VIEW MODE ────────────────────────────────────────────────── -->
    <div x-show="!editing"
         class="ca-view-zone<?= canWrite() ? '' : ' pe-none' ?>"
         <?= canWrite() ? '@click="startEdit()" title="' . $GLOBAL['clickToEdit'] . '"' : '' ?>>

        <?php if (canWrite()): ?>
        <span class="ca-edit-hint" aria-hidden="true">
            <i class="fas fa-pen me-1"></i><?= $GLOBAL['edit'] ?>
        </span>
        <?php endif ?>

        <p class="form-section-title mt-0"><?= $GLOBAL['contactInfo'] ?></p>

        <div class="row row-cols-1 row-cols-md-2 g-2 mb-3">

            <div x-show="data.society">
                <div class="ca-field-label"><?= $GLOBAL['society'] ?></div>
                <div class="ca-field-value" x-text="data.society"></div>
            </div>

            <div>
                <div class="ca-field-label"><?= $GLOBAL['lastName'] ?> / <?= $GLOBAL['firstName'] ?></div>
                <div class="ca-field-value fw-semibold">
                    <span x-text="data.lastName"></span>
                    <span x-text="data.firstName ? ' ' + data.firstName : ''"></span>
                </div>
            </div>

            <div x-show="data.gender && data.gender !== 'na'">
                <div class="ca-field-label"><?= $GLOBAL['sexe'] ?></div>
                <div class="ca-field-value" x-text="genderLabels[data.gender] ?? data.gender"></div>
            </div>

            <div x-show="data.title">
                <div class="ca-field-label"><?= $GLOBAL['title'] ?></div>
                <div class="ca-field-value" x-text="data.title"></div>
            </div>

            <div x-show="data.address || data.npa">
                <div class="ca-field-label"><?= $GLOBAL['address'] ?></div>
                <div class="ca-field-value">
                    <span x-text="data.address"></span>
                    <span x-show="data.address && data.npa">, </span>
                    <span x-text="data.npa"></span>
                </div>
                <div class="mt-1 d-flex gap-2" x-show="data.address">
                    <a :href="'https://www.google.ch/maps/place/' + encodeURIComponent((data.address||'') + ',' + (data.npa||''))"
                       target="_blank" class="text-muted small" @click.stop>
                        <i class="fas fa-location-dot me-1" aria-hidden="true"></i><?= $GLOBAL['googleMaps'] ?>
                    </a>
                </div>
            </div>

            <div x-show="data.email">
                <div class="ca-field-label"><i class="fas fa-envelope me-1" aria-hidden="true"></i><?= $GLOBAL['email'] ?></div>
                <div class="ca-field-value d-flex align-items-center gap-2 flex-wrap">
                    <a :href="'mailto:' + data.email" x-text="data.email" @click.stop></a>
                </div>
            </div>

            <div x-show="data.emailAlt">
                <div class="ca-field-label"><i class="fas fa-envelope me-1" aria-hidden="true"></i><?= $GLOBAL['emailAltLong'] ?></div>
                <div class="ca-field-value" x-text="data.emailAlt"></div>
            </div>

            <div x-show="data.web">
                <div class="ca-field-label"><i class="fas fa-globe me-1" aria-hidden="true"></i><?= $GLOBAL['web'] ?></div>
                <div class="ca-field-value">
                    <a :href="data.web" x-text="data.web" target="_blank" @click.stop></a>
                </div>
            </div>

        </div>

        <p class="form-section-title"><?= $GLOBAL['additionalInfo'] ?></p>

        <div class="row row-cols-1 row-cols-md-2 g-2 mb-3">

            <div x-show="data.telProf">
                <div class="ca-field-label"><i class="fas fa-phone me-1" aria-hidden="true"></i><?= $GLOBAL['telProf'] ?></div>
                <div class="ca-field-value">
                    <a :href="'tel:' + data.telProf" x-text="data.telProf" @click.stop></a>
                </div>
            </div>

            <div x-show="data.tel">
                <div class="ca-field-label"><i class="fas fa-phone me-1" aria-hidden="true"></i><?= $GLOBAL['tel'] ?></div>
                <div class="ca-field-value">
                    <a :href="'tel:' + data.tel" x-text="data.tel" @click.stop></a>
                </div>
            </div>

            <div x-show="data.portable">
                <div class="ca-field-label"><i class="fas fa-mobile-screen-button me-1" aria-hidden="true"></i><?= $GLOBAL['portable'] ?></div>
                <div class="ca-field-value">
                    <a :href="'tel:' + data.portable" x-text="data.portable" @click.stop></a>
                </div>
            </div>

            <div x-show="data.fax">
                <div class="ca-field-label"><i class="fas fa-print me-1" aria-hidden="true"></i><?= $GLOBAL['fax'] ?></div>
                <div class="ca-field-value" x-text="data.fax"></div>
            </div>

            <div x-show="data.birthDate">
                <div class="ca-field-label"><?= $GLOBAL['birthDay'] ?></div>
                <div class="ca-field-value" x-text="formatDate(data.birthDate)"></div>
            </div>

        </div>

        <div x-show="data.comment">
            <div class="ca-field-label mb-1"><?= $GLOBAL['compet'] ?></div>
            <div class="ca-field-value" x-html="data.comment"></div>
        </div>

        <?php if ($_createdAt || $_modifiedAt): ?>
        <div class="mt-3 text-muted small text-end">
            <?php if ($_createdAt):  ?><?= sprintf($GLOBAL['createdAtLabel'], $_createdAt) ?><?php endif ?>
            <?php if ($_modifiedAt): ?>&nbsp;· <?= sprintf($GLOBAL['modifiedAtLabel'], $_modifiedAt) ?><?php endif ?>
        </div>
        <?php endif ?>
    </div>

    <!-- ── EDIT MODE ────────────────────────────────────────────────── -->
    <?php if (canWrite()): ?>
    <div x-show="editing" x-cloak>

        <p class="form-section-title mt-0"><?= $GLOBAL['contactInfo'] ?></p>

        <div class="row mb-2">
            <label for="gd-society" class="col-md-3 col-form-label"><?= $GLOBAL['society'] ?></label>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-sm" id="gd-society" x-model="draft.society"/>
            </div>
        </div>
        <div class="row mb-2">
            <label for="gd-lastName" class="col-md-3 col-form-label"><?= $GLOBAL['lastName'] ?></label>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-sm" id="gd-lastName" x-model="draft.lastName"/>
            </div>
        </div>
        <div class="row mb-2">
            <label for="gd-firstName" class="col-md-3 col-form-label"><?= $GLOBAL['firstName'] ?></label>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-sm" id="gd-firstName" x-model="draft.firstName"/>
            </div>
        </div>
        <div class="row mb-2">
            <label for="gd-gender" class="col-md-3 col-form-label"><?= $GLOBAL['sexe'] ?></label>
            <div class="col-md-9">
                <select class="form-select form-select-sm" id="gd-gender" x-model="draft.gender">
                    <option value="na"><?= $GLOBAL['na'] ?></option>
                    <option value="hf"><?= $GLOBAL['hf'] ?></option>
                    <option value="f"><?= $GLOBAL['f'] ?></option>
                    <option value="m"><?= $GLOBAL['m'] ?></option>
                </select>
            </div>
        </div>
        <div class="row mb-2">
            <label for="gd-title" class="col-md-3 col-form-label"><?= $GLOBAL['title'] ?></label>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-sm" id="gd-title" x-model="draft.title"/>
            </div>
        </div>
        <div class="row mb-2">
            <label for="gd-address" class="col-md-3 col-form-label"><?= $GLOBAL['address'] ?></label>
            <div class="col-md-9">
                <textarea class="form-control form-control-sm" rows="2" id="gd-address" x-model="draft.address"></textarea>
            </div>
        </div>
        <div class="row mb-2">
            <label for="gd-npa" class="col-md-3 col-form-label"><?= $GLOBAL['npa'] ?></label>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-sm" id="gd-npa" x-model="draft.npa"/>
            </div>
        </div>
        <div class="row mb-2">
            <label for="gd-email" class="col-md-3 col-form-label">
                <i class="fas fa-envelope me-1" aria-hidden="true"></i><?= $GLOBAL['email'] ?>
            </label>
            <div class="col-md-9">
                <input type="email" class="form-control form-control-sm" id="gd-email" x-model="draft.email"/>
            </div>
        </div>
        <div class="row mb-2">
            <label for="gd-emailAlt" class="col-md-3 col-form-label">
                <i class="fas fa-envelope me-1" aria-hidden="true"></i><?= $GLOBAL['emailAltLong'] ?>
            </label>
            <div class="col-md-9">
                <input type="email" class="form-control form-control-sm" id="gd-emailAlt" x-model="draft.emailAlt"/>
                <div class="form-text"><?= $GLOBAL['emailAltHint'] ?></div>
            </div>
        </div>
        <div class="row mb-2">
            <label for="gd-web" class="col-md-3 col-form-label">
                <i class="fas fa-globe me-1" aria-hidden="true"></i><?= $GLOBAL['web'] ?>
            </label>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-sm" id="gd-web" x-model="draft.web"/>
            </div>
        </div>

        <p class="form-section-title"><?= $GLOBAL['additionalInfo'] ?></p>

        <div class="row mb-2">
            <label for="gd-telProf" class="col-md-3 col-form-label">
                <i class="fas fa-phone me-1" aria-hidden="true"></i><?= $GLOBAL['telProf'] ?>
            </label>
            <div class="col-md-9">
                <input type="tel" class="form-control form-control-sm" id="gd-telProf" x-model="draft.telProf"/>
            </div>
        </div>
        <div class="row mb-2">
            <label for="gd-tel" class="col-md-3 col-form-label">
                <i class="fas fa-phone me-1" aria-hidden="true"></i><?= $GLOBAL['tel'] ?>
            </label>
            <div class="col-md-9">
                <input type="tel" class="form-control form-control-sm" id="gd-tel" x-model="draft.tel"/>
            </div>
        </div>
        <div class="row mb-2">
            <label for="gd-portable" class="col-md-3 col-form-label">
                <i class="fas fa-mobile-screen-button me-1" aria-hidden="true"></i><?= $GLOBAL['portable'] ?>
            </label>
            <div class="col-md-9">
                <input type="tel" class="form-control form-control-sm" id="gd-portable" x-model="draft.portable"/>
            </div>
        </div>
        <div class="row mb-2">
            <label for="gd-fax" class="col-md-3 col-form-label">
                <i class="fas fa-print me-1" aria-hidden="true"></i><?= $GLOBAL['fax'] ?>
            </label>
            <div class="col-md-9">
                <input type="text" class="form-control form-control-sm" id="gd-fax" x-model="draft.fax"/>
            </div>
        </div>
        <div class="row mb-2">
            <label for="gd-birthDate" class="col-md-3 col-form-label"><?= $GLOBAL['birthDay'] ?></label>
            <div class="col-md-9">
                <input type="date" class="form-control form-control-sm" id="gd-birthDate" x-model="draft.birthDate"/>
            </div>
        </div>
        <div class="row mb-2">
            <label for="tiptap-comment" class="col-md-3 col-form-label"><?= $GLOBAL['compet'] ?></label>
            <div class="col-md-9">
                <div class="tiptap-wrap border rounded" id="tiptap-wrap-comment">
                    <div class="tiptap-toolbar d-flex gap-1 px-2 py-1 border-bottom bg-light rounded-top flex-wrap" role="toolbar" aria-label="<?= $GLOBAL['ttFormatting'] ?>">
                        <button type="button" class="tt-btn" data-tt="bold"        title="<?= $GLOBAL['ttBold'] ?>"           aria-label="<?= $GLOBAL['ttBoldShort'] ?>"><i class="fas fa-bold"></i></button>
                        <button type="button" class="tt-btn" data-tt="italic"      title="<?= $GLOBAL['ttItalic'] ?>"       aria-label="<?= $GLOBAL['ttItalicShort'] ?>"><i class="fas fa-italic"></i></button>
                        <span class="tt-sep"></span>
                        <button type="button" class="tt-btn" data-tt="bulletList"  title="<?= $GLOBAL['ttBulletList'] ?>"           aria-label="<?= $GLOBAL['ttBulletList'] ?>"><i class="fas fa-list-ul"></i></button>
                        <button type="button" class="tt-btn" data-tt="orderedList" title="<?= $GLOBAL['ttOrderedList'] ?>"         aria-label="<?= $GLOBAL['ttOrderedList'] ?>"><i class="fas fa-list-ol"></i></button>
                        <span class="tt-sep"></span>
                        <button type="button" class="tt-btn" data-tt="undo"        title="<?= $GLOBAL['ttUndo'] ?>"        aria-label="<?= $GLOBAL['cancel'] ?>"><i class="fas fa-rotate-left"></i></button>
                        <button type="button" class="tt-btn" data-tt="redo"        title="<?= $GLOBAL['ttRedo'] ?>" aria-label="<?= $GLOBAL['ttRedoShort'] ?>"><i class="fas fa-rotate-right"></i></button>
                    </div>
                    <div id="tiptap-comment" class="tiptap-body px-3 py-2"
                         style="min-height:80px;cursor:text"
                         aria-label="<?= htmlspecialchars($GLOBAL['compet'], ENT_QUOTES, 'UTF-8') ?>"
                         aria-multiline="true"></div>
                </div>
                <textarea name="comment" id="comment" style="display:none"><?= htmlentities($user->getComment(), ENT_COMPAT, $charset) ?></textarea>
            </div>
        </div>

        <div class="alert alert-danger py-2 px-3 mt-3 small" x-show="error" x-text="error" x-cloak></div>

        <div class="mt-3 d-flex align-items-center gap-2 flex-wrap">
            <button type="button" class="btn btn-primary btn-sm" @click="save()" :disabled="saving">
                <span x-show="saving" x-cloak>
                    <span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                </span>
                <?= $GLOBAL['saveBtn'] ?>
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" @click="cancel()" :disabled="saving">
                <?= $GLOBAL['cancel'] ?>
            </button>
            <?php if ($_createdAt || $_modifiedAt): ?>
            <span class="text-muted small ms-auto">
                <?php if ($_createdAt):  ?><?= sprintf($GLOBAL['createdAtLabel'], $_createdAt) ?><?php endif ?>
                <?php if ($_modifiedAt): ?>&nbsp;· <?= sprintf($GLOBAL['modifiedAtLabel'], $_modifiedAt) ?><?php endif ?>
            </span>
            <?php endif ?>
        </div>
    </div>
    <?php endif ?>

</div>



