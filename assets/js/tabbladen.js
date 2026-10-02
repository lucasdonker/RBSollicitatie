/*
 * Paal 13 — Tabbladen binnen één scherm (WAI-ARIA tabs), bijv. het TW-gesprek.
 *
 *   <div data-tabbladen id="uniek">
 *     <div class="bh-tablijst" role="tablist" aria-label="…">
 *       <button role="tab" id="tab-a" aria-controls="p-a">A</button> …
 *     </div>
 *     <div role="tabpanel" id="p-a" aria-labelledby="tab-a">…</div> …
 *   </div>
 *   Elders: <button data-naar-tab="tab-a"> springt naar dat tabblad.
 *
 * Zonder JavaScript staan alle panelen onder elkaar en is de tablijst verborgen (CSS op .klaar).
 * Pijltjes links/rechts, Home en End wisselen; het gekozen tabblad blijft per scherm onthouden
 * zolang het browsertabblad open is.
 */
(function () {
  'use strict';
  function lees(k) { try { return sessionStorage.getItem(k); } catch (e) { return null; } }
  function schrijf(k, v) { try { sessionStorage.setItem(k, v); } catch (e) {} }

  document.querySelectorAll('[data-tabbladen]').forEach(function (wortel) {
    var tabs = Array.prototype.slice.call(wortel.querySelectorAll('[role="tab"]'));
    if (!tabs.length) return;
    var sleutel = 'p13-tab-' + (wortel.id || location.pathname);

    function kies(tab, focus) {
      tabs.forEach(function (t) {
        var aan = t === tab;
        t.setAttribute('aria-selected', aan ? 'true' : 'false');
        t.tabIndex = aan ? 0 : -1;
        var p = document.getElementById(t.getAttribute('aria-controls'));
        if (p) p.hidden = !aan;
      });
      schrijf(sleutel, tab.id);
      if (focus) tab.focus();
    }

    tabs.forEach(function (t, i) {
      t.addEventListener('click', function () { kies(t); });
      t.addEventListener('keydown', function (e) {
        var n = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: tabs.length - 1 }[e.key];
        if (n === undefined) return;
        e.preventDefault();
        kies(tabs[(n + tabs.length) % tabs.length], true);
      });
    });

    document.addEventListener('click', function (e) {
      var knop = e.target.closest && e.target.closest('[data-naar-tab]');
      if (!knop) return;
      var t = document.getElementById(knop.dataset.naarTab);
      if (!t || tabs.indexOf(t) === -1) return;
      e.preventDefault();
      kies(t, true);
      wortel.scrollIntoView({ block: 'start', behavior: 'smooth' });
    });

    var bewaard = document.getElementById(lees(sleutel) || '');
    kies(tabs.indexOf(bewaard) !== -1 ? bewaard : (tabs.filter(function (t) { return t.getAttribute('aria-selected') === 'true'; })[0] || tabs[0]));
    wortel.classList.add('klaar');
  });
})();
