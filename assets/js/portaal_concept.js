/*
 * Paal 13 — gedeelde autosave-helper voor portaalformulieren (concept).
 *
 *   const concept = PortaalConcept.maak({
 *     url:      '/portaal/api_besch_concept.php',   // POST-endpoint (antwoordt {ok, tijd})
 *     csrf:     'token',
 *     verzamel: function () { return {...} | null; }, // huidige formulierstaat; null = niets te bewaren
 *     statusEl: document.getElementById('..'),      // optioneel: element met .ok-klasse + tekst
 *     tekstEl:  document.getElementById('..'),      // optioneel: element waar de statustekst in komt
 *     wachten:  2500                                // debounce in ms
 *   });
 *   concept.plan();     // na een wijziging (debounced)
 *   concept.bewaarNu(); // direct (bijv. bij stap-wissel)
 *   concept.stop();     // vóór het echte opslaan: geen concept meer sturen
 *
 * Het endpoint krijgt: _csrf + concept=<JSON>. Bij het verlaten van de pagina gaat het laatste
 * concept via sendBeacon mee. Dedupe op inhoud: ongewijzigd = geen request.
 */
(function () {
  'use strict';
  window.PortaalConcept = {
    maak: function (o) {
      let timer = null, laatst = '', gestopt = false;
      const wachten = o.wachten || 2500;

      function status(tekst, ok) {
        if (o.tekstEl) o.tekstEl.textContent = tekst;
        if (o.statusEl) o.statusEl.classList.toggle('ok', !!ok);
      }
      function body(json) {
        const d = new URLSearchParams();
        d.append('_csrf', o.csrf || '');
        d.append('concept', json);
        return d;
      }
      function bewaar(sync) {
        if (gestopt || !o.url) return;
        let data = null;
        try { data = o.verzamel(); } catch (e) { return; }
        if (!data) return;
        const json = JSON.stringify(data);
        if (json === laatst) return;
        laatst = json;
        if (sync) { if (navigator.sendBeacon) navigator.sendBeacon(o.url, body(json)); return; }
        status('Concept opslaan…', false);
        fetch(o.url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body(json).toString() })
          .then(function (r) { return r.json(); })
          .then(function (r) { status(r.ok ? 'Concept opgeslagen' + (r.tijd ? ' om ' + r.tijd : '') : 'Concept kon niet worden opgeslagen', !!r.ok); })
          .catch(function () { status('Concept kon niet worden opgeslagen', false); });
      }
      window.addEventListener('pagehide', function () { clearTimeout(timer); bewaar(true); });
      return {
        /* Huidige staat als "al bewaard" markeren (bij het openen), zodat alleen echte wijzigingen worden gestuurd. */
        prime: function () { try { const d = o.verzamel(); laatst = d ? JSON.stringify(d) : ''; } catch (e) {} },
        plan: function () { clearTimeout(timer); timer = setTimeout(function () { bewaar(false); }, wachten); },
        bewaarNu: function () { clearTimeout(timer); bewaar(false); },
        stop: function () { gestopt = true; clearTimeout(timer); }
      };
    }
  };
})();
