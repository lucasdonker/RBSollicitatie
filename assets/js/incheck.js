/**
 * Paal 13 — inchecken in het beheer (admin/_incheck_lijst.php), selectiedag en trainingsweekend.
 *
 * Een tik ís opslaan: de rij wisselt meteen en zet zichzelf terug als de server nee zegt.
 * Wachten op een rondje server voelt op een tablet als "hij deed het niet".
 * De stand staat in data-stand (verwacht | aanwezig | niet); CSS toont de juiste knoppen.
 */
(function () {
  'use strict';

  function tijd() { var d = new Date(); return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2); }

  function tel(blok) {
    var rijen = blok.querySelectorAll('.bh-ick-rij');
    var a = 0, v = 0;
    rijen.forEach(function (r) { if (r.dataset.stand === 'aanwezig') a++; else if (r.dataset.stand === 'verwacht') v++; });
    blok.querySelector('[data-teller="aanwezig"]').textContent = a;
    blok.querySelector('[data-teller="verwacht"]').textContent = v;
  }

  function zet(rij, stand, regel) {
    rij.dataset.stand = stand;
    rij.querySelector('[data-ick-regel]').textContent = regel;
  }

  function klik(blok, rij, wat) {
    var oudStand = rij.dataset.stand, oudRegel = rij.querySelector('[data-ick-regel]').textContent;
    var nieuw = wat === 'aanwezig' ? 'aanwezig' : (wat === 'niet' ? 'niet' : 'verwacht');
    zet(rij, nieuw, nieuw === 'aanwezig' ? 'ingecheckt ' + tijd() : (nieuw === 'niet' ? 'Niet gekomen' : rij.dataset.sub));
    tel(blok);
    var body = new URLSearchParams({ ev: blok.dataset.ev, actie: 'zet', sol_id: rij.dataset.sol, stand: wat, _csrf: blok.dataset.csrf || window.CSRF || '' });
    fetch(blok.dataset.endpoint, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) throw new Error('nee');
        // Op de selectiedag de groep erbij: "ingecheckt 08:52 · groep 2".
        if (d.stand === 'aanwezig' && d.groep_nummer) {
          rij.querySelector('[data-ick-regel]').textContent = 'ingecheckt ' + (d.tijd || tijd()) + ' · ' + (d.groep_naam || 'groep ' + d.groep_nummer);
        }
      })
      .catch(function () {
        zet(rij, oudStand, oudRegel); tel(blok);
        if (window.bhToast) window.bhToast('Opslaan lukte niet — probeer het opnieuw.', 'fout');
      });
  }

  function filter(blok) {
    var q = (blok.querySelector('[data-ick-zoek]').value || '').trim().toLowerCase();
    var alleen = blok.querySelector('[data-ick-filter]').getAttribute('aria-pressed') === 'true';
    blok.querySelectorAll('.bh-ick-rij').forEach(function (r) {
      r.hidden = (q !== '' && r.dataset.naam.indexOf(q) === -1) || (alleen && r.dataset.stand !== 'verwacht');
    });
  }

  document.querySelectorAll('[data-incheck]').forEach(function (blok) {
    blok.addEventListener('click', function (e) {
      var knop = e.target.closest('[data-ick]');
      if (knop) { klik(blok, knop.closest('.bh-ick-rij'), knop.dataset.ick); return; }
      var f = e.target.closest('[data-ick-filter]');
      if (f) { f.setAttribute('aria-pressed', f.getAttribute('aria-pressed') === 'true' ? 'false' : 'true'); filter(blok); }
    });
    blok.querySelector('[data-ick-zoek]').addEventListener('input', function () { filter(blok); });
  });

  // Live bijwerken (assets/js/live.js) zette nieuwe rijen neer: tellers en je zoekfilter opnieuw toepassen.
  document.addEventListener('p13:live', function () {
    document.querySelectorAll('[data-incheck]').forEach(function (blok) { tel(blok); filter(blok); });
  });
})();
