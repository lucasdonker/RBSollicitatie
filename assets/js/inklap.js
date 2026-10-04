/**
 * Paal 13 — inklapblokken (.bh-klap, zie beheer.css) onthouden hun stand binnen de sessie.
 *
 *   <details class="bh-klap" data-klap-id="sd-test-sport"> … </details>
 *
 * Zonder data-klap-id is het een gewone <details>. De standaard (open of dicht) staat in de
 * HTML; pas als iemand zelf klapt, wint die keuze — tot het tabblad dichtgaat. sessionStorage
 * kan ontbreken of gooien (privévenster, geblokkeerde opslag): dan geldt gewoon de HTML.
 * Nieuwe blokken die later in de pagina komen: window.P13Klap.koppel(element).
 *
 * "Alles uitklappen": <button type="button" data-klap-alles="details.bh-nb-meer" aria-pressed="false">
 * opent of sluit alle blokken die bij die selector horen, en wisselt zijn eigen tekst.
 */
(function () {
  'use strict';
  var SLEUTEL = 'p13-klap';

  function lees() {
    try { return JSON.parse(sessionStorage.getItem(SLEUTEL) || '{}') || {}; } catch (e) { return {}; }
  }
  function bewaar(id, open) {
    var s = lees();
    s[id] = open;
    try { sessionStorage.setItem(SLEUTEL, JSON.stringify(s)); } catch (e) { /* dan niet */ }
  }

  function koppel(wortel) {
    var stand = lees();
    (wortel || document).querySelectorAll('details[data-klap-id]').forEach(function (d) {
      if (d.dataset.klapGekoppeld) return;
      d.dataset.klapGekoppeld = '1';
      var id = d.dataset.klapId;
      if (Object.prototype.hasOwnProperty.call(stand, id)) d.open = !!stand[id];
      d.addEventListener('toggle', function () { bewaar(id, d.open); });
    });
  }

  document.addEventListener('click', function (e) {
    var knop = e.target.closest('[data-klap-alles]');
    if (!knop) return;
    var open = knop.getAttribute('aria-pressed') !== 'true';
    document.querySelectorAll(knop.dataset.klapAlles).forEach(function (d) { d.open = open; });
    knop.setAttribute('aria-pressed', open ? 'true' : 'false');
    knop.textContent = open ? 'Alles inklappen' : 'Alles uitklappen';
  });

  window.P13Klap = { koppel: koppel };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { koppel(); });
  else koppel();
})();
