<form action="<?= $_SERVER['PHP_SELF'] ?>" method="post" name="updateUser" role="form">
    <input type="hidden" name="id" value="<?= $user->getId() ?>"/>
    <input type="hidden" name="action" value="updateUser"/>
    <input type="hidden" name="view" value="generalData"/>

    <p class="form-section-title">Coordonnées</p>

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
            <a target="_blank" href="http://tel.local.ch/fr/q/<?= urlencode($user->getLastName() . ' ' . $user->getFirstName()) ?>.html">
                <i class="fas fa-home" aria-hidden="true"></i> <?= $GLOBAL['address'] ?>
            </a>
            <br>
            <a href="https://www.google.ch/maps/place/<?= urlencode($user->getAddress() . ',' . $user->getNpa()) ?>" target="_blank">
                <i class="fas fa-map-marker-alt" aria-hidden="true"></i> map
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

    <p class="form-section-title">Infos complémentaires</p>

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
            <i class="fas fa-mobile-alt" aria-hidden="true"></i> <?= $GLOBAL['portable'] ?>
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
        <label for="comment" class="col-md-3 col-form-label"><?= $GLOBAL['compet'] ?></label>
        <div class="col-md-9">
            <textarea class="form-control form-control-sm ck" rows="3" name="comment"
                      id="comment"><?= htmlentities($user->getComment(), ENT_COMPAT, $charset) ?></textarea>
        </div>
    </div>

    <div class="mt-3 d-flex align-items-center gap-3 flex-wrap">
        <button type="submit" class="btn btn-primary btn-sm"><?= $GLOBAL['update'] ?></button>
        <?php if ($user->isCotisationPayed(date("Y")) == -1 && ($user->isMemberOfTeam(5) || $user->isMemberOfTeam(6))): ?>
            <span class="badge bg-danger">Cotisation <?= date("Y") ?> non payée</span>
        <?php endif; ?>
        <span class="text-muted small ms-auto">
            <?php if ($user->getCreationDate()): ?>
                Créé: <?= timeStampToformatedDate($user->getCreationDate()) ?>
            <?php endif; ?>
            <?php if ($user->getModificationDate()): ?>
                &nbsp;· Modifié: <?= timeStampToformatedDate($user->getModificationDate()) ?>
            <?php endif; ?>
        </span>
    </div>
</form>
