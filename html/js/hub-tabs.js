/**
 * Shared helpers for the tab-hub views ("Membres & finances" #164,
 * "Journaux") that embed independently-written standalone views as tabs.
 *
 * Each embedded view still constructs its own filter links (year picker,
 * type dropdown, etc.) pointing at its OWN standalone route (e.g.
 * ?view=comptaRecap) — fine when reached directly, but a real problem once
 * embedded: clicking any filter kicks the user out of the hub back to the
 * standalone page. caHubRewriteEmbeddedLinks() rewrites those hrefs, in
 * place, to point at the hub + tab instead, preserving every other query
 * param. caHubEnableTabDeepLink() keeps the URL's ?tab= in sync when
 * switching tabs client-side, so the current tab is bookmarkable/shareable.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
(function (global) {
    'use strict';

    function caHubRewriteEmbeddedLinks(paneSelector, legacyView, hubView, tab) {
        document.querySelectorAll(paneSelector + ' a[href*="view=' + legacyView + '"]').forEach(function (a) {
            var url = new URL(a.href, window.location.origin);
            if (url.searchParams.get('view') !== legacyView) return; // exact match only, not a substring of another view name
            url.searchParams.set('view', hubView);
            url.searchParams.set('tab', tab);
            a.href = url.pathname + '?' + url.searchParams.toString();
        });
    }

    function caHubEnableTabDeepLink(tabButtonSelector, tabIdPattern, paramName) {
        paramName = paramName || 'tab';
        document.querySelectorAll(tabButtonSelector).forEach(function (btn) {
            btn.addEventListener('shown.bs.tab', function () {
                var m = btn.id.match(tabIdPattern);
                if (!m) return;
                var url = new URL(window.location.href);
                url.searchParams.set(paramName, m[1]);
                history.pushState({}, '', url);
            });
        });
    }

    global.caHubRewriteEmbeddedLinks = caHubRewriteEmbeddedLinks;
    global.caHubEnableTabDeepLink = caHubEnableTabDeepLink;
})(window);
