/**
 * Paal 13 — direct opslaan voor formulieren met data-direct-opslaan (beheer).
 *
 * Een keuze (radio, select, vinkje) slaat meteen op; een tekstveld zodra je het verlaat.
 * Geen herlaadactie en geen contextwissel (WCAG 3.2.2): het formulier gaat met fetch weg
 * (ajax=1, de server antwoordt JSON {ok, melding}) en de status verschijnt in
 * [data-direct-status] (aria-live). De Opslaan-knop ([data-direct-knop]) verdwijnt alleen
 * als JavaScript werkt; zonder JavaScript is het een gewoon formulier.
 * Gebruikt door admin/tw/voorbereiden.php en admin/tw/evaluatie.php.
 */
(function () {
  'use strict';
  document.querySelectorAll('form[data-direct-opslaan]').forEach(function (form) {
    var status = form.querySelector('[data-direct-status]');
    var knop = form.querySelector('[data-direct-knop]');
    if (knop) knop.hidden = true;
    var bezig = null;

    function zeg(tekst, fout) {
      if (!status) return;
      status.textContent = tekst;
      status.classList.toggle('fout', !!fout);
    }

    function opslaan() {
      var data = new FormData(form);
      data.set('ajax', '1');
      zeg('opslaan…');
      if (bezig) bezig.abort();
      bezig = new AbortController();
      fetch(form.getAttribute('action') || location.href, { method: 'POST', body: data, signal: bezig.signal,
                                                            headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          zeg(d.ok ? 'opgeslagen' : (d.melding || 'niet opgeslagen'), !d.ok);
          if (!d.ok && window.bhToast) window.bhToast(d.melding || 'Niet opgeslagen.', 'fout');
        })
        .catch(function (e) { if (e.name !== 'AbortError') zeg('niet opgeslagen — geen verbinding', true); });
    }

    form.addEventListener('change', function (e) {
      if (e.target.matches('input[type=text], textarea')) return;   // tekst: pas bij verlaten
      opslaan();
    });
    form.addEventListener('focusout', function (e) {
      if (e.target.matches('input[type=text], textarea') && e.target.value !== e.target.defaultValue) {
        e.target.defaultValue = e.target.value;
        opslaan();
      }
    });
    // Enter in een tekstveld: opslaan zonder de pagina te verlaten.
    form.addEventListener('submit', function (e) { e.preventDefault(); opslaan(); });
  });
})();
