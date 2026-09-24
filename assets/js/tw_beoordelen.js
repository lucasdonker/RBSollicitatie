/**
 * Paal 13 — trainingsweekend, stap Beoordelen (admin/tw/beoordelen.php).
 * Soort kiezen, observatie opslaan, eigen observatie wissen. Geen bewaarknop voor de soort:
 * je staat buiten met een telefoon in je hand en loopt zo weer weg.
 */
(function () {
  'use strict';
  var blok = document.querySelector('[data-twbeoordeel]');
  if (!blok) return;
  var kaart = blok.querySelector('[data-sol]');

  function post(velden) {
    var body = new URLSearchParams(Object.assign({ _csrf: blok.dataset.csrf }, velden));
    return fetch(blok.dataset.endpoint, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
      .then(function (r) { return r.json(); });
  }
  function fout(t) { if (window.bhToast) window.bhToast(t, 'fout'); }

  blok.addEventListener('click', function (e) {
    var soort = e.target.closest('[data-soort]');
    if (soort) {
      soort.parentNode.querySelectorAll('[data-soort]').forEach(function (b) {
        var aan = b === soort;
        b.classList.toggle('gekozen', aan);
        b.setAttribute('aria-pressed', aan ? 'true' : 'false');
      });
      return;
    }

    if (e.target.closest('[data-obs-opslaan]') && kaart) {
      var veld = kaart.querySelector('[data-obs-tekst]');
      var tekst = (veld.value || '').trim();
      if (!tekst) { fout('Schrijf eerst iets op.'); veld.focus(); return; }
      var gekozen = kaart.querySelector('[data-soort].gekozen');
      var knop = e.target.closest('[data-obs-opslaan]');
      var klaar = window.P13Actie ? window.P13Actie.bezig(knop.closest('.bh-observatieblok'), knop) : function () {};
      post({ actie: 'observatie', sol_id: kaart.dataset.sol, tekst: tekst, soort: gekozen ? gekozen.dataset.soort : 'neutraal' })
        .then(function (d) {
          if (!d.ok) throw new Error();
          location.reload();
        })
        .catch(function () { klaar(); fout('Opslaan lukte niet — probeer het opnieuw.'); });
      return;
    }

    var wis = e.target.closest('[data-obs-wis]');
    if (wis) {
      bhBevestig({ titel: 'Observatie verwijderen?', tekst: 'Alleen je eigen observatie; die van anderen blijven staan.', knop: 'Ja, verwijderen', gevaar: true })
        .then(function (ja) {
          if (!ja) return;
          post({ actie: 'observatie_wis', id: wis.dataset.obsWis }).then(function (d) {
            if (!d.ok) throw new Error();
            var li = wis.closest('li'); if (li) li.remove();
          }).catch(function () { fout('Verwijderen lukte niet.'); });
        });
    }
  });
})();
