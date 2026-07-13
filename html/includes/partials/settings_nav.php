<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Shared Réglages sidebar (desktop nav + mobile select) — single source of
 * truth so standalone routes that used to be settings tabs (e.g.
 * ?view=inactiveUsers) can't drift out of sync with settings_general.php's
 * own copy the way users_inactive.php's hardcoded duplicate once did.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

function mbSettingsNavItem(string $tab, string $icon, string $label, string $activeTab, bool $drillDown, string $self): void
{
    if ($activeTab === 'teams' || $activeTab === 'segments') $activeTab = 'groups';
    $isActive    = $activeTab === $tab;
    $activeClass = $isActive ? ' active' : '';
    if ($drillDown) {
        $url = htmlspecialchars($self . '?view=settings&tab=' . $tab, ENT_QUOTES);
        echo '<li role="presentation">'
           . '<a class="ca-settings-nav-btn' . $activeClass . '" href="' . $url . '" style="text-decoration:none">'
           . '<i class="' . $icon . ' fa-fw" aria-hidden="true"></i>' . htmlspecialchars($label)
           . '</a></li>';
    } else {
        $ariaSelected = $isActive ? 'true' : 'false';
        echo '<li role="presentation">'
           . '<button class="ca-settings-nav-btn' . $activeClass . '" id="tab-' . $tab . '-btn"'
           . ' data-bs-toggle="tab" data-bs-target="#tab-' . $tab . '"'
           . ' type="button" role="tab" aria-controls="tab-' . $tab . '" aria-selected="' . $ariaSelected . '">'
           . '<i class="' . $icon . ' fa-fw" aria-hidden="true"></i>' . htmlspecialchars($label)
           . '</button></li>';
    }
}

/**
 * Mobile <select> that replaces the sidebar on small screens. Rendered as a
 * sibling BEFORE .ca-settings-layout (grid with 2 fixed columns — the select
 * must not become a 3rd grid item).
 *
 * @param string $activeTab currently active tab key (e.g. 'groups', 'compta'…)
 * @param bool   $drillDown true for standalone pages (navigates directly to
 *                          ?view=settings&tab=X instead of switching an
 *                          in-page Bootstrap tab pane — see the caller's own
 *                          tab-sync script for the non-drillDown case)
 */
function mbRenderSettingsMobileSelect(string $activeTab, bool $drillDown): void
{
    global $GLOBAL;
    $self = appUrl();
    if ($activeTab === 'teams' || $activeTab === 'segments') $activeTab = 'groups';
    ?>
    <div class="ca-settings-select-wrap">
      <select class="form-select form-select-sm" id="settings-select" aria-label="<?= $GLOBAL['settingsSectionAria'] ?>"
              data-no-dirty
              <?php if ($drillDown): ?>onchange="window.__dirtyOverride=true;window.location=this.value"<?php endif ?>>
        <?php foreach (['groups' => $GLOBAL['groups'], 'categories' => $GLOBAL['categories'], 'filters' => $GLOBAL['combinedSegments']] as $_tab => $_label): ?>
        <option value="<?= $drillDown ? htmlspecialchars($self . '?view=settings&tab=' . $_tab, ENT_QUOTES) : '#tab-' . $_tab ?>" <?= $activeTab === $_tab ? 'selected' : '' ?>><?= $_label ?></option>
        <?php endforeach ?>
        <?php if (isManager()): ?>
        <option value="<?= $drillDown ? htmlspecialchars($self . '?view=settings&tab=compta', ENT_QUOTES) : '#tab-compta' ?>" <?= $activeTab === 'compta' ? 'selected' : '' ?>><?= $GLOBAL['comptaTypes'] ?></option>
        <?php endif ?>
        <?php if (isAdmin()): ?>
        <?php foreach ([
            'contactTypes' => $GLOBAL['contactTypesTitle'],
            'settings'     => $GLOBAL['settings'],
            'email'        => $GLOBAL['smtpSettings'],
            'users'        => $GLOBAL['users'],
            'audit'        => $GLOBAL['journal'],
            'integrity'    => $GLOBAL['integrity'],
            'health'       => $GLOBAL['health'],
        ] as $_tab => $_label): ?>
        <option value="<?= $drillDown ? htmlspecialchars($self . '?view=settings&tab=' . $_tab, ENT_QUOTES) : '#tab-' . $_tab ?>" <?= $activeTab === $_tab ? 'selected' : '' ?>><?= $_label ?></option>
        <?php endforeach ?>
        <option value="<?= htmlspecialchars($self . '?view=inactiveUsers', ENT_QUOTES) ?>" <?= $activeTab === 'inactiveUsers' ? 'selected' : '' ?>><?= $GLOBAL['archived'] ?></option>
        <?php endif ?>
      </select>
    </div>
    <?php
}

/**
 * Desktop sidebar <nav> — first child of .ca-settings-layout.
 *
 * @param string $activeTab see mbRenderSettingsMobileSelect()
 * @param bool   $drillDown see mbRenderSettingsMobileSelect()
 */
function mbRenderSettingsNav(string $activeTab, bool $drillDown): void
{
    global $GLOBAL;
    $self = appUrl();
    if ($activeTab === 'teams' || $activeTab === 'segments') $activeTab = 'groups';
    ?>
    <nav class="ca-settings-nav" aria-label="<?= $GLOBAL['settingsSectionsAria'] ?>">
      <ul role="tablist" aria-orientation="vertical" id="settings-tabs">
        <?php
        mbSettingsNavItem('groups',     'fas fa-users',       $GLOBAL['groups'],           $activeTab, $drillDown, $self);
        mbSettingsNavItem('categories', 'fas fa-tag',         $GLOBAL['categories'],       $activeTab, $drillDown, $self);
        mbSettingsNavItem('filters',    'fas fa-layer-group', $GLOBAL['combinedSegments'], $activeTab, $drillDown, $self);
        if (isManager()):
        ?>
        <li role="presentation" class="ca-settings-nav-divider" aria-hidden="true"><?= $GLOBAL['management'] ?></li>
        <?php
        mbSettingsNavItem('compta', 'fas fa-tags', $GLOBAL['comptaTypes'], $activeTab, $drillDown, $self);
        if (isAdmin()):
        mbSettingsNavItem('contactTypes', 'fas fa-id-card', $GLOBAL['contactTypesTitle'], $activeTab, $drillDown, $self);
        endif;
        endif;
        if (isAdmin()):
        ?>
        <li role="presentation" class="ca-settings-nav-divider" aria-hidden="true"><?= $GLOBAL['administration'] ?></li>
        <?php
        mbSettingsNavItem('settings',  'fas fa-sliders',           $GLOBAL['settings'],  $activeTab, $drillDown, $self);
        mbSettingsNavItem('email',     'fas fa-envelope',          $GLOBAL['smtpSettings'], $activeTab, $drillDown, $self);
        mbSettingsNavItem('users',     'fas fa-user-shield',       $GLOBAL['users'],     $activeTab, $drillDown, $self);
        mbSettingsNavItem('audit',     'fas fa-clock-rotate-left', $GLOBAL['journal'],   $activeTab, $drillDown, $self);
        mbSettingsNavItem('integrity', 'fas fa-stethoscope',       $GLOBAL['integrity'], $activeTab, $drillDown, $self);
        mbSettingsNavItem('health',    'fas fa-heart-pulse',       $GLOBAL['health'],    $activeTab, $drillDown, $self);
        ?>
        <li role="presentation">
          <a class="ca-settings-nav-btn<?= $activeTab === 'inactiveUsers' ? ' active' : '' ?>" href="<?= appUrl() ?>?view=inactiveUsers"
             style="text-decoration:none">
            <i class="fas fa-archive fa-fw" aria-hidden="true"></i><?= $GLOBAL['archived'] ?>
          </a>
        </li>
        <?php endif ?>
      </ul>
    </nav>
    <?php
}
