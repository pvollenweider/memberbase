// Sidebar toggle behavior for the fixed left nav. Active-state on nav-links
// is set server-side (topbar.php / sidebar_nav.php), matching this app's
// own ?view= routing rather than a client-side path matcher.
(function () {
  var toggleBtn = document.getElementById('sidebarToggle');
  var content = document.querySelector('.ca-app-content');
  var STORAGE_KEY = 'ca_sidebar_collapsed';

  function setCollapsed(collapsed) {
    document.body.classList.toggle('ca-sidebar-collapsed', collapsed);
    localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
  }

  if (toggleBtn) {
    toggleBtn.addEventListener('click', function (e) {
      e.preventDefault();
      setCollapsed(!document.body.classList.contains('ca-sidebar-collapsed'));
    });
  }

  if (localStorage.getItem(STORAGE_KEY) === '1') {
    setCollapsed(true);
  }

  // Clicking into the content area on mobile closes an open sidebar.
  if (content) {
    content.addEventListener('click', function () {
      if (window.innerWidth < 992 && document.body.classList.contains('ca-sidebar-collapsed')) {
        setCollapsed(false);
      }
    });
  }
})();
