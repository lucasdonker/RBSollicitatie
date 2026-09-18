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
  function weekOptiesNr(gekozen) {
    let html = '';
    for (const wk in CFG.weken) {
      const sel = (String(wk) === String(gekozen)) ? ' selected' : '';
      html += '<option value="' + wk + '"' + sel + '>' + wk + '</option>';
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
    const vanW = data.van_week || CFG.eerste;
    const totW = data.tot_week || CFG.laatste;
    div.innerHTML =
      '<div class="bp-kop">' +
        '<span class="bp-titel">Week ' + esc(vanW) + ' t/m ' + esc(totW) + '</span>' +
        '<button type="button" class="bp-wis" onclick="this.closest(\'.besch-rij\').remove(); beschHerbereken()" aria-label="Verwijderen">' +
          '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>' +
        '</button>' +
      '</div>' +
      '<div class="bp-velden">' +
        '<label class="bp-veld"><span class="bp-veld-lbl">Van week</span>' +
          '<select name="b2_blok_van[]" class="bp-select" onchange="beschPeriodeTitel(this); beschHerbereken()">' + weekOpties(vanW) + '</select>' +
        '</label>' +
        '<label class="bp-veld"><span class="bp-veld-lbl">Tot en met</span>' +
          '<select name="b2_blok_tot[]" class="bp-select" onchange="beschPeriodeTitel(this); beschHerbereken()">' + weekOpties(totW) + '</select>' +
        '</label>' +
      '</div>' +
      '<div class="bp-segment">' +
        '<button type="button" class="bp-seg' + (regime === 'vijf' ? ' actief' : '') + '" onclick="beschRegimeKies(this,\'vijf\')">Hele week</button>' +
        '<button type="button" class="bp-seg' + (regime === 'weekend' ? ' actief' : '') + '" onclick="beschRegimeKies(this,\'weekend\')">Alleen weekend</button>' +
        '<input type="hidden" name="b2_blok_regime[]" value="' + regime + '">' +
      '</div>' +
      '<div class="besch-weekenddagen" style="' + (regime === 'weekend' ? '' : 'display:none') + '">' +
        '<span class="besch-dagpil-lbl">Welke dagen in het weekend?</span>' +
        '<div class="besch-dagpil-rij">' +
        [6, 7].map(function (d) {
          const aan = (data.weekend_dagen || [6, 7]).map(String).includes(String(d));
          return '<label class="besch-dagpil' + (aan ? ' actief' : '') + '"><input type="checkbox" class="besch-wkdag" value="' + d + '"' + (aan ? ' checked' : '') + ' onchange="beschDagpilToggle(this); beschWkdagSync(this); beschHerbereken()"> ' + DAGEN_KORT[d] + '</label>';
        }).join('') +
        '</div>' +
        '<input type="hidden" name="b2_blok_wkdagen[]" value="' + esc(JSON.stringify(data.weekend_dagen || [6, 7])) + '">' +
      '</div>';
    return div;
  }
  window.beschPeriodeToevoegen = function (data) { el('beschPeriodes').appendChild(periodeRij(data)); beschHerbereken(); };
  window.beschPeriodeTitel = function (sel) {
    const rij = sel.closest('.besch-rij');
    const van = rij.querySelector('[name="b2_blok_van[]"]').value;
    const tot = rij.querySelector('[name="b2_blok_tot[]"]').value;
    const t = rij.querySelector('.bp-titel');
    if (t) t.textContent = 'Week ' + van + ' t/m ' + tot;
  };
  window.beschRegimeKies = function (btn, regime) {
    const rij = btn.closest('.besch-rij');
    rij.querySelectorAll('.bp-seg').forEach(function (b) { b.classList.remove('actief'); });
    btn.classList.add('actief');
    rij.querySelector('[name="b2_blok_regime[]"]').value = regime;
    rij.querySelector('.besch-weekenddagen').style.display = regime === 'weekend' ? '' : 'none';
    beschHerbereken();
  };
  window.beschDagpilToggle = function (cb) {
    const lbl = cb.closest('.besch-dagpil');
    if (lbl) lbl.classList.toggle('actief', cb.checked);
  };
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
  function wisBtn() {
    return '<button type="button" class="bs-item-wis" onclick="this.closest(\'.besch-rij\').remove(); beschHerbereken()" aria-label="Verwijderen">' +
      '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>';
  }

  function afwezigRij(data) {
    data = data || {};
    const div = document.createElement('div');
    div.className = 'besch-rij besch-afwezig bs-item-rij';
    div.innerHTML =
      '<div class="bs-item-tekst">' +
        '<div class="bs-item-datums">' +
          '<input type="date" name="b2_afw_van[]" value="' + esc(data.van_datum || data.van || '') + '" onchange="beschHerbereken()" aria-label="Van">' +
          '<span>t/m</span>' +
          '<input type="date" name="b2_afw_tot[]" value="' + esc(data.tot_datum || data.tot || '') + '" onchange="beschHerbereken()" aria-label="Tot">' +
        '</div>' +
        '<input type="text" name="b2_afw_label[]" value="' + esc(data.label || '') + '" placeholder="reden (bijv. vakantie)" class="bs-invoer" style="margin-top:8px" onchange="beschHerbereken()">' +
      '</div>' + wisBtn();
    return div;
  }
  window.beschAfwezigToevoegen = function (data) { el('beschAfwezig').appendChild(afwezigRij(data)); beschHerbereken(); };

  /* Sectie 3: Losse dagen */
  function losseDagRij(data) {
    data = data || {};
    const soort = data.soort === 'voorkeur_vrij' ? 'voorkeur_vrij' : 'niet_beschikbaar';
    const div = document.createElement('div');
    div.className = 'besch-rij besch-losdag bs-item-rij';
    div.innerHTML =
      '<div class="bs-item-tekst">' +
        '<div class="bs-item-datums">' +
          '<input type="date" name="b2_dag_datum[]" value="' + esc(data.datum || '') + '" onchange="beschHerbereken()" aria-label="Datum">' +
          '<select name="b2_dag_soort[]" class="bs-mini-select" onchange="beschHerbereken()">' +
            '<option value="niet_beschikbaar"' + (soort === 'niet_beschikbaar' ? ' selected' : '') + '>kan niet</option>' +
            '<option value="voorkeur_vrij"' + (soort === 'voorkeur_vrij' ? ' selected' : '') + '>liever vrij</option>' +
          '</select>' +
        '</div>' +
        '<input type="text" name="b2_dag_reden[]" value="' + esc(data.reden || '') + '" placeholder="reden (optioneel)" class="bs-invoer" style="margin-top:8px" onchange="beschHerbereken()">' +
      '</div>' + wisBtn();
    return div;
  }
  window.beschLosseDagToevoegen = function (data) { el('beschLosseDagen').appendChild(losseDagRij(data)); beschHerbereken(); };

  /* Sectie 4: Minder dagen (verzoek) */
  function verzoekRij(data) {
    data = data || {};
    const div = document.createElement('div');
    div.className = 'besch-rij besch-verzoek bs-item-rij';
    div.innerHTML =
      '<div class="bs-item-tekst">' +
        '<div class="bs-item-datums">' +
          '<span class="bs-lbl-mini">week</span>' +
          '<select name="b2_vz_van[]" class="bs-mini-select" onchange="beschHerbereken()">' + weekOptiesNr(data.van_week || CFG.eerste) + '</select>' +
          '<span>t/m</span>' +
          '<select name="b2_vz_tot[]" class="bs-mini-select" onchange="beschHerbereken()">' + weekOptiesNr(data.tot_week || CFG.laatste) + '</select>' +
          '<span>max</span>' +
          '<input type="number" name="b2_vz_dagen[]" min="1" max="6" value="' + esc(data.dagen_aantal || data.dagen || '') + '" class="bs-mini-getal" onchange="beschHerbereken()" aria-label="dagen">' +
          '<span>dgn</span>' +
        '</div>' +
        '<input type="text" name="b2_vz_toelichting[]" value="' + esc(data.toelichting || '') + '" placeholder="reden (bijv. school)" class="bs-invoer" style="margin-top:8px" onchange="beschHerbereken()">' +
        '<input type="hidden" name="b2_vz_pool[]" value="' + esc(JSON.stringify((data.dagen_pool || data.pool || []))) + '">' +
      '</div>' + wisBtn();
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
      doel.textContent = regels.join('. ') + '.';
    } else {
      doel.textContent = 'Vul hieronder je beschikbaarheid in.';
    }
  };

  /* Vaste vrije dag aan/uit (pil-toggle) */
  window.beschVasteToggle = function (cb) {
    const lbl = cb.closest('.bs-dagpil');
    if (lbl) lbl.classList.toggle('actief', cb.checked);
    // Hint bijwerken
    const hint = el('beschVasteHint');
    if (hint) {
      const namen = { 1:'ma', 2:'di', 3:'wo', 4:'do', 5:'vr' };
      const gekozen = Array.from(document.querySelectorAll('.besch-vastedag:checked')).map(function (c) { return namen[c.value]; });
      hint.textContent = gekozen.length ? 'Elke ' + gekozen.join(', ') : 'Geen';
    }
    beschHerbereken();
  };

  /* AI-hulp */
  /* Voorbeeld-chip vult het AI-tekstveld */
  window.beschAiChip = function (btn) {
    const veld = el('beschAiTekst');
    if (veld) { veld.value = btn.textContent.trim(); veld.focus(); }
  };

  /* ── Wizard-navigatie ─────────────────────────────────────────── */
  function wizToon(stapId) {
    ['wizStap1', 'wizStap2', 'wizStap3', 'beschForm'].forEach(function (id) {
      const e = el(id); if (e) e.style.display = (id === stapId) ? '' : 'none';
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
  window.wizNaarFormulier = function () { wizToon('beschForm'); };
  window.wizOvernemen = function () {
    // De secties zijn al gevuld door beschAiVulIn; toon het formulier ter controle.
    wizToon('beschForm');
  };

  // Bewaart de laatst getypte AI-tekst zodat "aanvullen" de context meestuurt.
  let wizLaatsteTekst = '';

  function wizVerstuurTekst(tekst, knop, statusEl) {
    if (knop) { knop.disabled = true; knop.dataset.oud = knop.textContent; knop.textContent = 'Bezig…'; }
    if (statusEl) statusEl.textContent = '';
    wizLaatsteTekst = tekst;
    // Naar stap 2 (laadscherm) als de wizard aanwezig is
    if (el('wizStap2')) { wizToon('wizStap2'); wizChecklistAnimatie(); }
    const body = new URLSearchParams();
    body.set('ai_besch_submit', '1');
    body.set('tekst', tekst);
    const csrf = document.querySelector('[name="_csrf"]');
    if (csrf) body.set('_csrf', csrf.value);
    return fetch(location.pathname, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (knop) { knop.disabled = false; knop.textContent = knop.dataset.oud || 'Maak er een planning van'; }
        if (res.status === 'uit') { wizFout('De AI-hulp staat momenteel uit. Vul je beschikbaarheid handmatig in.', statusEl); return; }
        if (res.status === 'fout' || res.status === 'geweigerd') { wizFout(res.bericht || 'Het lukte niet. Vul het handmatig in.', statusEl); return; }
        // Vul de secties vast in (ook bij een vervolgvraag: wat we WEL weten staat er dan al)
        beschAiVulIn(res);
        if (el('wizStap3')) {
          // Toon het voorstel-scherm
          if (el('wizSamenvatting')) el('wizSamenvatting').textContent = res.samenvatting || 'We hebben je beschikbaarheid ingevuld — controleer het hieronder.';
          wizBouwOnderdelen(res);
          wizToonNavraag(res.vraag || '');
          wizToon('wizStap3');
        } else {
          wizNaarFormulier();
        }
      })
      .catch(function () {
        if (knop) { knop.disabled = false; knop.textContent = knop.dataset.oud || 'Maak er een planning van'; }
        wizFout('Er ging iets mis met de verbinding. Vul je beschikbaarheid handmatig in.', statusEl);
      });
  }

  function wizFout(bericht, statusEl) {
    // Terug naar stap 1 met de foutmelding
    if (el('wizStap1')) wizToon('wizStap1');
    const s = statusEl || el('beschAiStatus');
    if (s) s.textContent = bericht;
  }

  function wizChecklistAnimatie() {
    ['wizChk1', 'wizChk2', 'wizChk3'].forEach(function (id, i) {
      const e = el(id); if (!e) return;
      e.classList.remove('wiz-chk-klaar');
      setTimeout(function () { e.classList.add('wiz-chk-klaar'); }, 500 + i * 600);
    });
  }

  function wizBouwOnderdelen(d) {
    const doel = el('wizOnderdelen');
    if (!doel) return;
    const items = [];
    const dagNaam = { 1:'maandag', 2:'dinsdag', 3:'woensdag', 4:'donderdag', 5:'vrijdag', 6:'zaterdag', 7:'zondag' };
    (d.blokken || []).forEach(function (b) {
      const regime = b.regime === 'weekend' ? 'alleen weekend' : 'hele weken';
      items.push({ titel: 'Werkperiode: week ' + b.van_week + ' t/m ' + b.tot_week, sub: regime });
    });
    (d.vaste_vrije_dagen || []).forEach(function (n) {
      items.push({ titel: 'Vaste vrije dag: ' + (dagNaam[n] || n), sub: d.vaste_reden || '' });
    });
    (d.afwezig || []).forEach(function (a) {
      items.push({ titel: 'Afwezig: ' + (a.van || a.van_datum) + ' t/m ' + (a.tot || a.tot_datum), sub: a.label || '' });
    });
    (d.losse_dagen || []).forEach(function (l) {
      items.push({ titel: (l.soort === 'voorkeur_vrij' ? 'Liever vrij: ' : 'Niet beschikbaar: ') + l.datum, sub: l.reden || '' });
    });
    (d.verzoeken || []).forEach(function (v) {
      items.push({ titel: 'Week ' + v.van_week + '-' + v.tot_week + ': max ' + (v.dagen_aantal || v.dagen) + ' dagen', sub: 'Als verzoek opgenomen — het bestuur beoordeelt dit', waarschuwing: true });
    });
    if (!items.length) { doel.innerHTML = ''; return; }
    let html = '<div class="wiz3-kaart">';
    items.forEach(function (it, i) {
      if (i > 0) html += '<div class="wiz3-lijn"></div>';
      html += '<div class="wiz3-rij' + (it.waarschuwing ? ' waarschuwing' : '') + '">' +
        '<div class="wiz3-rij-tekst"><span class="wiz3-rij-titel">' + esc(it.titel) + '</span>' +
        (it.sub ? '<span class="wiz3-rij-sub">' + esc(it.sub) + '</span>' : '') + '</div>' +
        '<button type="button" class="wiz3-wijzig" onclick="wizNaarFormulier()">Wijzig</button>' +
      '</div>';
    });
    html += '</div>';
    doel.innerHTML = html;
  }

  function wizToonNavraag(vraag) {
    const doel = el('wizNavraag');
    if (!doel) return;
    if (!vraag) { doel.innerHTML = ''; return; }
    // De AI kan een vrije vervolgvraag stellen; toon 'm als een net blok met een tekstantwoord.
    doel.innerHTML =
      '<div class="wiz-navraag">' +
        '<div class="wiz-navraag-kop">' + esc('Nog even checken') + '</div>' +
        '<p class="wiz-navraag-vraag">' + esc(vraag) + '</p>' +
        '<div class="wiz-aanvullen-rij">' +
          '<input type="text" id="beschAiNavraag" placeholder="Je antwoord…">' +
          '<button type="button" class="wiz-aanvul-knop" onclick="beschAiNavraagVerstuur()" aria-label="Verstuur">&rarr;</button>' +
        '</div>' +
      '</div>';
  }

  window.beschAiNavraagVerstuur = function () {
    const inp = el('beschAiNavraag');
    if (!inp || !inp.value.trim()) return;
    // Stuur de oorspronkelijke tekst + het antwoord op de vraag samen opnieuw.
    wizVerstuurTekst(wizLaatsteTekst + '\n\nAanvulling: ' + inp.value.trim(), null, el('beschAiStatus'));
  };

  window.beschAiAanvullen = function () {
    const inp = el('beschAiAanvul');
    if (!inp || !inp.value.trim()) return;
    wizVerstuurTekst(wizLaatsteTekst + '\n\nAanvulling: ' + inp.value.trim(), null, el('beschAiStatus'));
    inp.value = '';
  };

  window.beschAiVerwerk = function () {
    const veld = el('beschAiTekst'), knop = el('beschAiKnop'), status = el('beschAiStatus');
    if (!veld || !veld.value.trim()) { if (status) status.textContent = 'Typ eerst iets over wanneer je kunt werken.'; return; }
    wizVerstuurTekst(veld.value.trim(), knop, status);
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
    // Vaste vrije dagen: de checkboxes aanvinken (AI geeft weekdagnummers 1-7) én de pil + hint bijwerken
    const vasteDagen = (d.vaste_vrije_dagen || []).map(String);
    document.querySelectorAll('.besch-vastedag').forEach(function (cb) { cb.checked = vasteDagen.includes(cb.value); beschVasteToggle(cb); });
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
