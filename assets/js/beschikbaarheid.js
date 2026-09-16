/*
 * Paal 13 — Beschikbaarheidsformulier (schone, modulaire opzet).
 * Alle configuratie komt uit window.BESCH_CFG:
 *   { weken: {22:'W22 · 25 mei', ...}, eerste: 22, laatste: 35 }
 */
(function () {
  'use strict';

  const CFG = window.BESCH_CFG || { weken: {}, eerste: 22, laatste: 35 };
  const DAGEN = { 1: 'maandag', 2: 'dinsdag', 3: 'woensdag', 4: 'donderdag', 5: 'vrijdag', 6: 'zaterdag', 7: 'zondag' };
  const DAGEN_KORT = { 1: 'ma', 2: 'di', 3: 'wo', 4: 'do', 5: 'vr', 6: 'za', 7: 'zo' };

  function el(id) { return document.getElementById(id); }
  function esc(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function klem(w, fb) { const n = parseInt(w); if (isNaN(n)) return fb; return Math.min(CFG.laatste, Math.max(CFG.eerste, n)); }
  function weekOpties(gekozen) {
    let html = '';
    for (const wk in CFG.weken) {
      const sel = (String(wk) === String(gekozen)) ? ' selected' : '';
      html += '<option value="' + wk + '"' + sel + '>' + esc(CFG.weken[wk]) + '</option>';
    }
    return html;
  }
  function xBtn() {
    return '<button type="button" class="besch-x" onclick="this.closest(\'.besch-rij\').remove(); beschHerbereken()" aria-label="Verwijderen">' +
      '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>';
  }
  function datumNL(iso) {
    if (!iso) return '';
    const p = iso.split('-'); if (p.length !== 3) return iso;
    const mnd = ['', 'jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
    return parseInt(p[2]) + ' ' + mnd[parseInt(p[1])];
  }

  /* Sectie 1: Werkperiodes */
  function periodeRij(data) {
    data = data || {};
    const div = document.createElement('div');
    div.className = 'besch-rij besch-periode';
    const regime = data.regime === 'weekend' ? 'weekend' : 'vijf';
    div.innerHTML =
      '<div class="besch-rij-hoofd">' +
        '<span class="besch-lbl">van</span>' +
        '<select name="b2_blok_van[]" onchange="beschHerbereken()">' + weekOpties(data.van_week || CFG.eerste) + '</select>' +
        '<span class="besch-lbl">t/m</span>' +
        '<select name="b2_blok_tot[]" onchange="beschHerbereken()">' + weekOpties(data.tot_week || CFG.laatste) + '</select>' +
        '<select name="b2_blok_regime[]" class="besch-regime" onchange="beschRegimeToggle(this); beschHerbereken()">' +
          '<option value="vijf"' + (regime === 'vijf' ? ' selected' : '') + '>hele weken</option>' +
          '<option value="weekend"' + (regime === 'weekend' ? ' selected' : '') + '>alleen weekend</option>' +
        '</select>' +
        xBtn() +
      '</div>' +
      '<div class="besch-weekenddagen" style="' + (regime === 'weekend' ? '' : 'display:none') + '">' +
        '<span class="besch-lbl">welke dagen?</span>' +
        [1, 2, 3, 4, 5, 6, 7].map(function (d) {
          const aan = (data.weekend_dagen || [6, 7]).map(String).includes(String(d));
          return '<label class="besch-dagpil"><input type="checkbox" class="besch-wkdag" value="' + d + '"' + (aan ? ' checked' : '') + ' onchange="beschWkdagSync(this); beschHerbereken()"> ' + DAGEN_KORT[d] + '</label>';
        }).join('') +
        '<input type="hidden" name="b2_blok_wkdagen[]" value="' + esc(JSON.stringify(data.weekend_dagen || [6, 7])) + '">' +
      '</div>';
    return div;
  }
  window.beschPeriodeToevoegen = function (data) { el('beschPeriodes').appendChild(periodeRij(data)); beschHerbereken(); };
  window.beschRegimeToggle = function (sel) {
    const rij = sel.closest('.besch-rij');
    rij.querySelector('.besch-weekenddagen').style.display = sel.value === 'weekend' ? '' : 'none';
  };
  window.beschWkdagSync = function (cb) {
    const rij = cb.closest('.besch-rij');
    const g = Array.from(rij.querySelectorAll('.besch-wkdag:checked')).map(function (c) { return parseInt(c.value); });
    rij.querySelector('[name="b2_blok_wkdagen[]"]').value = JSON.stringify(g);
  };

  /* Sectie 2: Afwezig */
  function afwezigRij(data) {
    data = data || {};
    const div = document.createElement('div');
    div.className = 'besch-rij besch-afwezig';
    div.innerHTML =
      '<div class="besch-rij-hoofd">' +
        '<input type="date" name="b2_afw_van[]" value="' + esc(data.van_datum || data.van || '') + '" onchange="beschHerbereken()" aria-label="Van datum">' +
        '<span class="besch-lbl">t/m</span>' +
        '<input type="date" name="b2_afw_tot[]" value="' + esc(data.tot_datum || data.tot || '') + '" onchange="beschHerbereken()" aria-label="Tot datum">' +
        xBtn() +
      '</div>' +
      '<input type="text" name="b2_afw_label[]" value="' + esc(data.label || '') + '" placeholder="reden (bijv. vakantie)" class="besch-breed" onchange="beschHerbereken()">';
    return div;
  }
  window.beschAfwezigToevoegen = function (data) { el('beschAfwezig').appendChild(afwezigRij(data)); beschHerbereken(); };

  /* Sectie 3: Losse dagen */
  function losseDagRij(data) {
    data = data || {};
    const soort = data.soort === 'voorkeur_vrij' ? 'voorkeur_vrij' : 'niet_beschikbaar';
    const div = document.createElement('div');
    div.className = 'besch-rij besch-losdag';
    div.innerHTML =
      '<div class="besch-rij-hoofd">' +
        '<input type="date" name="b2_dag_datum[]" value="' + esc(data.datum || '') + '" onchange="beschHerbereken()" aria-label="Datum">' +
        '<select name="b2_dag_soort[]" onchange="beschHerbereken()">' +
          '<option value="niet_beschikbaar"' + (soort === 'niet_beschikbaar' ? ' selected' : '') + '>kan ik niet</option>' +
          '<option value="voorkeur_vrij"' + (soort === 'voorkeur_vrij' ? ' selected' : '') + '>liever vrij</option>' +
        '</select>' +
        xBtn() +
      '</div>' +
      '<input type="text" name="b2_dag_reden[]" value="' + esc(data.reden || '') + '" placeholder="reden (optioneel)" class="besch-breed" onchange="beschHerbereken()">';
    return div;
  }
  window.beschLosseDagToevoegen = function (data) { el('beschLosseDagen').appendChild(losseDagRij(data)); beschHerbereken(); };

  /* Sectie 4: Minder dagen (verzoek) */
  function verzoekRij(data) {
    data = data || {};
    const div = document.createElement('div');
    div.className = 'besch-rij besch-verzoek';
    const pool = (data.dagen_pool || data.pool || []).map(String);
    div.innerHTML =
      '<div class="besch-rij-hoofd">' +
        '<span class="besch-lbl">van</span>' +
        '<select name="b2_vz_van[]" onchange="beschHerbereken()">' + weekOpties(data.van_week || CFG.eerste) + '</select>' +
        '<span class="besch-lbl">t/m</span>' +
        '<select name="b2_vz_tot[]" onchange="beschHerbereken()">' + weekOpties(data.tot_week || CFG.laatste) + '</select>' +
        '<input type="number" name="b2_vz_dagen[]" min="1" max="6" value="' + esc(data.dagen_aantal || data.dagen || '') + '" placeholder="dagen" class="besch-getal" onchange="beschHerbereken()" aria-label="Aantal dagen">' +
        '<span class="besch-lbl">dagen</span>' +
        xBtn() +
      '</div>' +
      '<div class="besch-pooldagen">' +
        '<span class="besch-lbl">welke dagen kun je wél? (optioneel)</span>' +
        [1, 2, 3, 4, 5, 6, 7].map(function (d) {
          const aan = pool.includes(String(d));
          return '<label class="besch-dagpil"><input type="checkbox" class="besch-pooldag" value="' + d + '"' + (aan ? ' checked' : '') + ' onchange="beschPoolSync(this); beschHerbereken()"> ' + DAGEN_KORT[d] + '</label>';
        }).join('') +
        '<input type="hidden" name="b2_vz_pool[]" value="' + esc(JSON.stringify((data.dagen_pool || data.pool || []))) + '">' +
      '</div>' +
      '<input type="text" name="b2_vz_toelichting[]" value="' + esc(data.toelichting || '') + '" placeholder="reden (bijv. school)" class="besch-breed" onchange="beschHerbereken()">';
    return div;
  }
  window.beschVerzoekToevoegen = function (data) { el('beschVerzoeken').appendChild(verzoekRij(data)); beschHerbereken(); };
  window.beschPoolSync = function (cb) {
    const rij = cb.closest('.besch-rij');
    const g = Array.from(rij.querySelectorAll('.besch-pooldag:checked')).map(function (c) { return parseInt(c.value); });
    rij.querySelector('[name="b2_vz_pool[]"]').value = JSON.stringify(g);
  };

  /* Centrale preview */
  window.beschHerbereken = function () {
    const regels = [];
    document.querySelectorAll('#beschPeriodes .besch-periode').forEach(function (rij) {
      const van = rij.querySelector('[name="b2_blok_van[]"]').value;
      const tot = rij.querySelector('[name="b2_blok_tot[]"]').value;
      const regime = rij.querySelector('[name="b2_blok_regime[]"]').value;
      if (!van || !tot) return;
      if (regime === 'weekend') {
        const dagen = Array.from(rij.querySelectorAll('.besch-wkdag:checked')).map(function (c) { return DAGEN_KORT[c.value]; });
        regels.push('Werkt week ' + van + ' t/m ' + tot + ' — alleen weekend' + (dagen.length ? ' (' + dagen.join('/') + ')' : ''));
      } else {
        regels.push('Werkt week ' + van + ' t/m ' + tot + ' — hele weken (5 dagen)');
      }
    });
    const vaste = Array.from(document.querySelectorAll('.besch-vastedag:checked')).map(function (c) { return DAGEN[c.value]; });
    if (vaste.length) {
      const toel = el('beschVasteToelichting') ? el('beschVasteToelichting').value.trim() : '';
      regels.push('Vast vrij op ' + vaste.join(', ') + (toel ? ' (' + toel + ')' : ''));
    }
    document.querySelectorAll('#beschAfwezig .besch-afwezig').forEach(function (rij) {
      const van = rij.querySelector('[name="b2_afw_van[]"]').value;
      const tot = rij.querySelector('[name="b2_afw_tot[]"]').value;
      const lbl = rij.querySelector('[name="b2_afw_label[]"]').value.trim();
      if (van && tot) regels.push('Afwezig ' + datumNL(van) + ' t/m ' + datumNL(tot) + (lbl ? ' (' + lbl + ')' : ''));
    });
    document.querySelectorAll('#beschLosseDagen .besch-losdag').forEach(function (rij) {
      const datum = rij.querySelector('[name="b2_dag_datum[]"]').value;
      const soort = rij.querySelector('[name="b2_dag_soort[]"]').value;
      const reden = rij.querySelector('[name="b2_dag_reden[]"]').value.trim();
      if (datum) regels.push((soort === 'voorkeur_vrij' ? 'Liever vrij op ' : 'Niet beschikbaar op ') + datumNL(datum) + (reden ? ' (' + reden + ')' : ''));
    });
    document.querySelectorAll('#beschVerzoeken .besch-verzoek').forEach(function (rij) {
      const van = rij.querySelector('[name="b2_vz_van[]"]').value;
      const tot = rij.querySelector('[name="b2_vz_tot[]"]').value;
      const dagen = rij.querySelector('[name="b2_vz_dagen[]"]').value;
      if (van && tot && dagen) {
        const pool = Array.from(rij.querySelectorAll('.besch-pooldag:checked')).map(function (c) { return DAGEN_KORT[c.value]; });
        regels.push('Verzoek: week ' + van + '-' + tot + ' maximaal ' + dagen + ' dagen' + (pool.length ? ' (' + pool.join('/') + ')' : ''));
      }
    });
    const doel = el('beschPreview');
    if (!doel) return;
    if (regels.length) {
      doel.innerHTML = '<ul class="besch-preview-lijst">' + regels.map(function (r) { return '<li>' + esc(r) + '</li>'; }).join('') + '</ul>';
    } else {
      doel.innerHTML = '<p class="besch-preview-leeg">Vul hierboven je beschikbaarheid in — hier verschijnt dan een samenvatting.</p>';
    }
  };

  /* AI-hulp */
  /* Voorbeeld-chip vult het AI-tekstveld */
  window.beschAiChip = function (btn) {
    const veld = el('beschAiTekst');
    if (veld) { veld.value = btn.textContent.trim(); veld.focus(); }
  };

  window.beschAiVerwerk = function () {
    const veld = el('beschAiTekst'), knop = el('beschAiKnop'), status = el('beschAiStatus');
    if (!veld || !veld.value.trim()) { if (status) status.textContent = 'Typ eerst iets over wanneer je kunt werken.'; return; }
    if (knop) { knop.disabled = true; knop.dataset.oud = knop.textContent; knop.textContent = 'Bezig…'; }
    if (status) status.textContent = '';
    const body = new URLSearchParams();
    body.set('ai_besch_submit', '1');
    body.set('tekst', veld.value.trim());
    const csrf = document.querySelector('[name="_csrf"]');
    if (csrf) body.set('_csrf', csrf.value);
    fetch(location.pathname, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (knop) { knop.disabled = false; knop.textContent = knop.dataset.oud || 'Voorstel maken'; }
        if (res.status === 'uit') { if (status) status.textContent = 'De AI-hulp staat momenteel uit. Vul je beschikbaarheid handmatig in.'; return; }
        if (res.status === 'fout' || res.status === 'geweigerd') { if (status) status.textContent = res.bericht || 'Het lukte niet. Probeer het handmatig.'; return; }
        if (res.status === 'vraag') { if (status) status.textContent = res.vraag || 'Kun je het iets specifieker maken?'; return; }
        beschAiVulIn(res);
        if (status) status.textContent = 'Voorstel ingevuld — controleer het hieronder en pas aan waar nodig.';
      })
      .catch(function () {
        if (knop) { knop.disabled = false; knop.textContent = knop.dataset.oud || 'Voorstel maken'; }
        if (status) status.textContent = 'Er ging iets mis met de verbinding. Vul je beschikbaarheid handmatig in.';
      });
  };
  function beschAiVulIn(d) {
    // Een AI-voorstel VERVANGT de huidige invoer — alle secties eerst leegmaken.
    el('beschPeriodes').innerHTML = '';
    el('beschAfwezig').innerHTML = '';
    el('beschLosseDagen').innerHTML = '';
    el('beschVerzoeken').innerHTML = '';
    if (d.blokken && d.blokken.length) {
      d.blokken.forEach(function (b) {
        beschPeriodeToevoegen({ van_week: klem(b.van_week, CFG.eerste), tot_week: klem(b.tot_week, CFG.laatste), regime: b.regime === 'weekend' ? 'weekend' : 'vijf', weekend_dagen: b.weekend_dagen || [6, 7] });
      });
    } else {
      // Geen periode in het voorstel? Toch één neerzetten zodat het formulier geldig blijft.
      beschPeriodeToevoegen({ van_week: CFG.eerste, tot_week: CFG.laatste, regime: 'vijf' });
    }
    (d.afwezig || []).forEach(function (a) { beschAfwezigToevoegen({ van: a.van || a.van_datum, tot: a.tot || a.tot_datum, label: a.label }); });
    (d.losse_dagen || []).forEach(function (l) { beschLosseDagToevoegen({ datum: l.datum, soort: l.soort, reden: l.reden }); });
    (d.verzoeken || []).forEach(function (v) { beschVerzoekToevoegen({ van_week: klem(v.van_week, CFG.eerste), tot_week: klem(v.tot_week, CFG.laatste), dagen_aantal: v.dagen_aantal || v.dagen, dagen_pool: v.dagen_pool || v.pool || [], toelichting: v.toelichting }); });
    // Vaste vrije dagen: de checkboxes aanvinken (AI geeft weekdagnummers 1-7)
    const vasteDagen = (d.vaste_vrije_dagen || []).map(String);
    document.querySelectorAll('.besch-vastedag').forEach(function (cb) { cb.checked = vasteDagen.includes(cb.value); });
    if (d.vaste_reden && el('beschVasteToelichting')) el('beschVasteToelichting').value = d.vaste_reden;
    beschHerbereken();
  }

  window.beschValideer = function () {
    if (!document.querySelectorAll('#beschPeriodes .besch-periode').length) {
      alert('Geef minstens één werkperiode op (wanneer je kunt werken).');
      return false;
    }
    // Vaste vrije dagen: verborgen b2_vaste_uit[]-velden opnieuw opbouwen uit de checkboxes
    document.querySelectorAll('.besch-vaste-hidden').forEach(function (h) { h.remove(); });
    const form = document.querySelector('form');
    document.querySelectorAll('.besch-vastedag:checked').forEach(function (cb) {
      const h = document.createElement('input');
      h.type = 'hidden'; h.name = 'b2_vaste_uit[]'; h.value = cb.value; h.className = 'besch-vaste-hidden';
      form.appendChild(h);
    });
    return true;
  };

  function init() {
    document.querySelectorAll('.besch-vastedag').forEach(function (cb) { cb.addEventListener('change', beschHerbereken); });
    const vt = el('beschVasteToelichting');
    if (vt) vt.addEventListener('input', beschHerbereken);
    if (!document.querySelectorAll('#beschPeriodes .besch-periode').length) {
      beschPeriodeToevoegen({ van_week: CFG.eerste, tot_week: CFG.laatste, regime: 'vijf' });
    }
    beschHerbereken();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
