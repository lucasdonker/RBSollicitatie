/**
 * Paal 13 — Selectiedag › Nabespreking (admin/selectie_nabespreking.php).
 *
 * Besluit en aantekening gaan samen de deur uit: de aantekening hoort bij het besluit, en los
 * opslaan zou een aantekening zonder besluit kunnen achterlaten. Nogmaals op het actieve besluit
 * klikken wist het (besluit '' — de server verwijdert dan de rij, aantekening inbegrepen).
 * "Alles uitklappen" opent of sluit de details van alle kaarten tegelijk.
 */
(function () {
  'use strict';
  var lijst = document.querySelector('[data-nb]');
  if (!lijst) return;
  var KLEUR = { door: 'groen', twijfel: 'oranje', niet: 'rood' };

  function huidig(kaart) {
    var aan = kaart.querySelector('[data-besluit].aan');
    return aan ? aan.dataset.besluit : '';
  }

  function zet(kaart, besluit) {
    var stand = kaart.querySelector('[data-stand]');
    var notitie = kaart.querySelector('[data-notitie]');
    if (stand) stand.textContent = 'opslaan…';
    var body = new URLSearchParams({ actie: 'besluit', sol_id: kaart.dataset.sol, besluit: besluit,
                                     notitie: notitie ? notitie.value : '', _csrf: lijst.dataset.csrf });
    fetch(lijst.dataset.endpoint, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) throw new Error(d.fout || '');
        kaart.querySelectorAll('[data-besluit]').forEach(function (b) {
          var aan = besluit !== '' && b.dataset.besluit === besluit;
          b.classList.toggle('aan', aan);
          b.setAttribute('aria-pressed', aan ? 'true' : 'false');
        });
        kaart.className = kaart.className.replace(/ds-fase-\w+/, 'ds-fase-' + (KLEUR[besluit] || 'grijs'));
        kaart.classList.toggle('besloten', besluit !== '');
        if (besluit === '' && notitie) notitie.value = '';
        if (stand) stand.textContent = besluit === '' ? 'besluit gewist' : 'opgeslagen';
      })
      .catch(function (e) {
        if (stand) stand.textContent = 'niet opgeslagen';
        if (window.bhToast) window.bhToast((e && e.message) || 'Opslaan mislukt.', 'fout');
      });
  }

  lijst.addEventListener('click', function (e) {
    var knop = e.target.closest('[data-besluit]');
    if (!knop) return;
    var kaart = knop.closest('[data-sol]');
    zet(kaart, knop.dataset.besluit === huidig(kaart) ? '' : knop.dataset.besluit);
  });

  lijst.addEventListener('change', function (e) {
    if (!e.target.matches('[data-notitie]')) return;
    var kaart = e.target.closest('[data-sol]');
    var b = huidig(kaart);
    if (!b) {   // nog geen besluit: de aantekening gaat mee zodra je er een kiest
      var st = kaart.querySelector('[data-stand]');
      if (st) st.textContent = 'nog niet opgeslagen: kies een besluit, dan gaat de aantekening mee';
      return;
    }
    zet(kaart, b);
  });

  var alles = document.querySelector('[data-nb-alles]');
  if (alles) {
    alles.addEventListener('click', function () {
      var open = alles.getAttribute('aria-pressed') !== 'true';
      lijst.querySelectorAll('details.bh-nb-meer').forEach(function (d) { d.open = open; });
      alles.setAttribute('aria-pressed', open ? 'true' : 'false');
      alles.textContent = open ? 'Alles inklappen' : 'Alles uitklappen';
    });
  }
})();
