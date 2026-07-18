// Sidebar toggle — event delegation so the listener survives OOB topbar swaps.
// State is persisted to the PHP session (desktop preference) via a lightweight
// fetch. On mobile (< 992 px) the CSS semantics are inverted: the
// ca-sidebar-collapsed class SHOWS the sidebar (it slides in as an overlay).
// We therefore reset the class on mobile on initial load so the sidebar always
// starts closed on small screens, regardless of the stored desktop preference.
(function () {
  var ACTION_URL = (document.querySelector('base') ? document.querySelector('base').href : '') + 'index.php';

  function isMobile() { return window.innerWidth < 992; }

  // Backdrop overlay — injected once, reused.
  var _overlay = null;
  function getOverlay() {
    if (!_overlay) {
      _overlay = document.createElement('div');
      _overlay.id = 'ca-sidebar-overlay';
      _overlay.className = 'ca-sidebar-overlay';
      document.body.appendChild(_overlay);
      _overlay.addEventListener('click', function () {
        // On mobile the overlay only appears when the sidebar is open
        // (ca-sidebar-collapsed class = sidebar visible); clicking it closes.
        setCollapsed(false);
      });
    }
    return _overlay;
  }

  function updateOverlay() {
    var overlay = getOverlay();
    // Overlay is active on mobile when the sidebar is open.
    var sidebarVisible = isMobile()
      ? document.body.classList.contains('ca-sidebar-collapsed')
      : !document.body.classList.contains('ca-sidebar-collapsed');
    overlay.classList.toggle('ca-sidebar-overlay--active', isMobile() && sidebarVisible);
  }

  function persistToSession(collapsed) {
    var csrf = window.casaCsrfToken ? window.casaCsrfToken() : '';
    var fd = new FormData();
    fd.append('action', 'sidebarState');
    fd.append('collapsed', collapsed ? '1' : '0');
    fd.append('csrf', csrf);
    fetch(ACTION_URL, {
      method: 'POST',
      headers: { 'X-CSRF-Token': csrf },
      body: fd,
    }).catch(function () { /* best-effort */ });
  }

  function setCollapsed(collapsed) {
    document.body.classList.toggle('ca-sidebar-collapsed', collapsed);
    updateOverlay();
    // Only persist desktop preference to session; mobile state is transient.
    if (!isMobile()) {
      persistToSession(collapsed);
    }
  }

  // Event delegation — survives OOB topbar swaps (the button DOM is replaced
  // on every boosted navigation, so a direct addEventListener would be lost).
  document.addEventListener('click', function (e) {
    if (e.target.closest('#sidebarToggle')) {
      e.preventDefault();
      setCollapsed(!document.body.classList.contains('ca-sidebar-collapsed'));
    }
  });

  // On initial load: if the PHP session applied ca-sidebar-collapsed but we're
  // on mobile, remove the class (mobile always starts with sidebar closed).
  if (isMobile() && document.body.classList.contains('ca-sidebar-collapsed')) {
    document.body.classList.remove('ca-sidebar-collapsed');
  }

  // On resize from mobile to desktop: hide the overlay (sidebar state stays).
  window.addEventListener('resize', function () {
    if (!isMobile()) {
      getOverlay().classList.remove('ca-sidebar-overlay--active');
      // If the sidebar was opened on mobile (class present), it now means
      // "collapsed" in desktop terms — flip to uncollapsed so desktop shows it.
      // This edge case only fires if someone resizes mid-session.
      if (document.body.classList.contains('ca-sidebar-collapsed')) {
        document.body.classList.remove('ca-sidebar-collapsed');
      }
    }
  });
})();
