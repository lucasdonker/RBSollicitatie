/**
 * Paal 13 — de afspraak-editor in het TW-gesprek (admin/_afspraak_editor.php).
 *
 * Elke wijziging slaat meteen op in het actieve scenario (concept); aanvinken = 5 dagen.
 * Elke 15 seconden haalt hij de stand opnieuw op, zodat een wijziging in de loonplanning hier
 * zichtbaar is — behalve terwijl je zelf iets invult of er een opslag onderweg is.
 * Op slot (na het gesprek): alles alleen-lezen; "Toch aanpassen" vraagt een reden.
 */
(function () {
  'use strict';
  document.querySelectorAll('[data-afspraak]').forEach(function (blok) {
    var sol = blok.dataset.sol, url = blok.dataset.endpoint, mag = blok.dataset.mag === '1';
    var bezig = 0, slot = blok.classList.contains('vast');

    function fout(t) { if (window.bhToast) window.bhToast(t, 'fout'); }

    function toon(d) {
      slot = !!d.slot;
      blok.classList.toggle('vast', slot);
      var kan = mag && !slot;
      var tekst = blok.querySelector('[data-af-slottekst]');
      tekst.textContent = slot
        ? 'Vastgezet na het gesprek (' + d.slot.op + '), in alle scenario\'s.'
        : 'Wijzigingen gaan meteen in het actieve scenario. Na het besluit staat dit vast in alle scenario\'s.';
      var o = blok.querySelector('[data-af-ontgrendel]'), v = blok.querySelector('[data-af-vastzetten]');
      if (o) o.hidden = !slot;
      if (v) v.hidden = slot;
      var og = blok.querySelector('[data-af-opgave]');
      if (og) og.hidden = slot;
      var fn = blok.querySelector('[data-af-functie]');
      if (d.functie && document.activeElement !== fn) fn.value = d.functie;
      fn.disabled = !kan;
      blok.querySelectorAll('tr[data-week]').forEach(function (rij) {
        var w = (d.weken || {})[rij.dataset.week] || { opgenomen: 0, dagen: 0, betaald: 1 };
        var op = rij.querySelector('[data-af="opgenomen"]'), dg = rij.querySelector('[data-af="dagen"]'), bt = rij.querySelector('[data-af="betaald"]');
        if (rij.contains(document.activeElement)) return;   // niet onder je vingers veranderen
        op.checked = !!w.opgenomen; dg.value = w.dagen; bt.checked = !!w.betaald;
        op.disabled = !kan; dg.disabled = bt.disabled = !kan || !w.opgenomen;
        rij.classList.toggle('ingepland', !!w.opgenomen);
      });
      blok.querySelector('[data-af-totaal]').textContent = d.dagen + ' dagen' + (d.onbetaald ? ', waarvan ' + d.onbetaald + ' onbetaald' : '')
        + ' · € ' + Number(d.kosten).toLocaleString('nl-NL', { maximumFractionDigits: 0 }) + ' bruto';
    }

    function post(velden) {
      bezig++;
      var body = new URLSearchParams(Object.assign({ sol_id: sol, _csrf: blok.dataset.csrf }, velden));
      return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (!d.ok) throw new Error(d.fout || 'Opslaan lukte niet.'); toon(d); })
        .catch(function (e) { fout(e.message); verversen(true); })
        .then(function () { bezig--; });
    }

    function verversen(altijd) {
      if (!altijd && (bezig || document.hidden || blok.contains(document.activeElement))) return;
      fetch(url + '?sol_id=' + encodeURIComponent(sol), { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (d.ok && !bezig) toon(d); })
        .catch(function () {});
    }
    setInterval(verversen, 15000);

    blok.addEventListener('change', function (e) {
      if (slot) return;
      if (e.target.matches('[data-af-functie]')) { post({ wat: 'functie', functie: e.target.value }); return; }
      var rij = e.target.closest('tr[data-week]');
      if (!rij) return;
      var op = rij.querySelector('[data-af="opgenomen"]'), dg = rij.querySelector('[data-af="dagen"]'), bt = rij.querySelector('[data-af="betaald"]');
      if (e.target === op && op.checked && (!dg.value || dg.value === '0')) dg.value = 5;
      dg.disabled = bt.disabled = !op.checked;
      rij.classList.toggle('ingepland', op.checked);
      post({ wat: 'cel', week_nr: rij.dataset.week, dagen: dg.value || 0, opgenomen: op.checked ? '1' : '0', betaald: bt.checked ? '1' : '0' });
    });

    blok.addEventListener('click', function (e) {
      if (e.target.closest('[data-af-vastzetten]')) { post({ wat: 'vastzetten' }); return; }
      if (e.target.closest('[data-af-opgave]') && !slot) {
        var doe = function () { post({ wat: 'opgave' }); };
        if (window.bhBevestig) {
          bhBevestig({ titel: 'Opgave overnemen?', knop: 'Ja, overnemen',
                       tekst: 'Elke week wordt ingepland met zoveel dagen als de sollicitant opgaf. Wat hier nu staat, wordt vervangen.' })
            .then(function (ja) { if (ja) doe(); });
        } else doe();
        return;
      }
      if (e.target.closest('[data-af-ontgrendel]') && window.bhVraag) {
        bhVraag({ titel: 'Toch aanpassen?', knop: 'Ontgrendelen',
                  tekst: 'Dit is met de sollicitant besproken. Waarom pas je het aan? De reden komt in het logboek. Zet het daarna opnieuw vast.' })
          .then(function (reden) {
            if (reden === null) return;
            if (!reden) { fout('Een reden is verplicht.'); return; }
            post({ wat: 'ontgrendelen', reden: reden });
          });
      }
    });
  });
})();
