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

    // veld: het tekstveld dat de opslag start; pas bij succes geldt zijn waarde als bewaard,
    // zodat een mislukte poging bij de volgende keer verlaten opnieuw wordt geprobeerd.
    function opslaan(veld) {
      var data = new FormData(form);
      data.set('ajax', '1');
      zeg('opslaan…');
      if (bezig) bezig.abort();
      bezig = new AbortController();
      var waarde = veld ? veld.value : null;
      fetch(form.getAttribute('action') || location.href, { method: 'POST', body: data, signal: bezig.signal,
                                                            headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          zeg(d.ok ? 'opgeslagen' : (d.melding || 'niet opgeslagen'), !d.ok);
          if (d.ok && veld) veld.defaultValue = waarde;
          if (!d.ok && window.bhToast) window.bhToast(d.melding || 'Niet opgeslagen.', 'fout');
        })
        .catch(function (e) { if (e.name !== 'AbortError') zeg('niet opgeslagen — geen verbinding', true); });
    }

    /* Een keuze die eerst een tekst nodig heeft (bijv. "Kan niet" vraagt een reden): de gekozen
       optie of radio heeft data-vraagt="#veld". Is dat veld leeg, dan nog niet opslaan maar
       ernaartoe; bij het verlaten van dat veld gaat alles samen weg. */
    function vraagtEerst(el) {
      var bron = el.tagName === 'SELECT' ? el.options[el.selectedIndex] : el;
      var sel = bron && bron.getAttribute('data-vraagt');
      var doel = sel ? form.querySelector(sel) : null;
      return doel && !doel.value.trim() ? doel : null;
    }

    form.addEventListener('change', function (e) {
      if (e.target.matches('input[type=text], textarea')) return;   // tekst: pas bij verlaten
      var doel = vraagtEerst(e.target);
      if (doel) { zeg('vul eerst de opmerking in, dan slaan we op', true); doel.focus(); return; }
      opslaan(null);
    });
    form.addEventListener('focusout', function (e) {
      if (e.target.matches('input[type=text], textarea') && e.target.value !== e.target.defaultValue) {
        opslaan(e.target);
      }
    });
    // Enter in een tekstveld: opslaan zonder de pagina te verlaten.
    form.addEventListener('submit', function (e) { e.preventDefault(); opslaan(null); });
  });
})();
