/**
 * Paal 13 — de afspraak-editor in het TW-gesprek (admin/_afspraak_editor.php).
 * Elke wijziging slaat meteen op in het actieve scenario (concept). Aanvinken = 5 dagen.
 */
(function () {
  'use strict';
  document.querySelectorAll('[data-afspraak]').forEach(function (blok) {
    var sol = blok.dataset.sol, url = blok.dataset.endpoint;

    function bewaar(velden) {
      var body = new URLSearchParams(Object.assign({ sol_id: sol, _csrf: blok.dataset.csrf }, velden));
      return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d.ok) throw new Error(d.fout || 'nee');
          var t = blok.querySelector('[data-af-totaal]');
          if (t) t.textContent = d.dagen + ' dagen' + (d.onbetaald ? ', waarvan ' + d.onbetaald + ' onbetaald' : '')
            + ' · € ' + Number(d.kosten).toLocaleString('nl-NL', { maximumFractionDigits: 0 }) + ' bruto';
        })
        .catch(function (e) { if (window.bhToast) window.bhToast('Opslaan lukte niet: ' + e.message, 'fout'); });
    }

    blok.addEventListener('change', function (e) {
      if (e.target.matches('[data-af-functie]')) { bewaar({ wat: 'functie', functie: e.target.value }); return; }
      var rij = e.target.closest('tr[data-week]');
      if (!rij) return;
      var op = rij.querySelector('[data-af="opgenomen"]'), dg = rij.querySelector('[data-af="dagen"]'), bt = rij.querySelector('[data-af="betaald"]');
      if (e.target === op && op.checked && (!dg.value || dg.value === '0')) dg.value = 5;
      dg.disabled = bt.disabled = !op.checked;
      rij.classList.toggle('ingepland', op.checked);
      bewaar({ wat: 'cel', week_nr: rij.dataset.week, dagen: dg.value || 0, opgenomen: op.checked ? '1' : '0', betaald: bt.checked ? '1' : '0' });
    });
  });
})();
