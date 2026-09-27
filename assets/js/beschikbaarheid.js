/*
 * Paal 13 — Beschikbaarheidsformulier (Portaal.dc sectie 4a/4b).
 * Alle rijen (werkperiodes, vakanties, losse dagen, verzoeken) worden hier opgebouwd; PHP levert
 * alleen de configuratie en de opgeslagen data via window.BESCH_CFG:
 *   { weken: {27:'W27 · 29 jun', ...}, datums: {27:{van:'29 juni', tot:'5 juli'}}, eerste: 27, laatste: 35,
 *     data: {periodes, vaste, vaste_toelichting, vakanties, losse, verzoeken, voorlopig} | null,
 *     concept: {stap, ai_tekst, ...zelfde velden als data} | null, start: 'wizard'|'formulier',
 *     apiConcept: url }
 * Eén dataformaat ("data") wordt gedeeld door opgeslagen data, het AI-voorstel en het concept.
 */
(function () {
  'use strict';

  const CFG = window.BESCH_CFG || { weken: {}, datums: {}, eerste: 22, laatste: 35 };
  const DAGEN = { 1: 'maandag', 2: 'dinsdag', 3: 'woensdag', 4: 'donderdag', 5: 'vrijdag', 6: 'zaterdag', 7: 'zondag' };
  const DAGEN_KORT = { 1: 'ma', 2: 'di', 3: 'wo', 4: 'do', 5: 'vr', 6: 'za', 7: 'zo' };
  const MND = ['', 'jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
  const SVG_CHEV = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>';
  const SVG_WIS  = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>';

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
  function datumNL(iso) {
    if (!iso) return '';
    const p = iso.split('-'); if (p.length !== 3) return iso;
    return parseInt(p[2]) + ' ' + MND[parseInt(p[1])];
  }
  function wisBtn() {
    return '<button type="button" class="bs-item-wis" onclick="this.closest(\'.besch-rij\').remove(); beschHerbereken()" aria-label="Verwijderen">' + SVG_WIS + '</button>';
  }
  function veld(lbl, inner) { return '<label class="bs-item-veld"><span class="bs-item-lbl">' + lbl + '</span>' + inner + '</label>'; }

  /* ── Sectie 1: werkperiodes (uitklapbare kaart) ─────────────── */
  function periodeSub(van, tot, regime, dicht, wkdagen) {
    van = parseInt(van); tot = parseInt(tot);
    const d1 = (CFG.datums || {})[van], d2 = (CFG.datums || {})[tot];
    const delen = [];
    if (d1 && d2) delen.push(d1.van + ' t/m ' + d2.tot);
    const n = tot - van + 1;
    if (n > 0) delen.push(n + (n === 1 ? ' week' : ' weken'));
    if (dicht) delen.push(regime === 'weekend' ? 'alleen weekend' + (wkdagen && wkdagen.length ? ' (' + wkdagen.join('/') + ')' : '') : 'hele week');
    return delen.join(' · ');
  }
  function periodeRij(data, open) {
    data = data || {};
    const div = document.createElement('div');
    div.className = 'besch-rij besch-periode' + (open ? '' : ' dicht');
    const regime = data.regime === 'weekend' ? 'weekend' : 'vijf';
    const vanW = klem(data.van, CFG.eerste);
    const totW = klem(data.tot, CFG.laatste);
    const wkd = (data.wkdagen && data.wkdagen.length ? data.wkdagen : [6, 7]).map(String);
    div.innerHTML =
      '<div class="bp-kop">' +
        '<button type="button" class="bp-toggle" aria-expanded="' + (open ? 'true' : 'false') + '" onclick="beschPeriodeToggle(this)">' +
          '<span class="bp-titel">Week ' + vanW + ' t/m ' + totW + '</span>' +
          '<span class="bp-sub"></span>' +
        '</button>' +
        '<span class="bp-chev" aria-hidden="true">' + SVG_CHEV + '</span>' +
        '<button type="button" class="bp-wis" onclick="this.closest(\'.besch-rij\').remove(); beschHerbereken()" aria-label="Periode verwijderen">' + SVG_WIS + '</button>' +
      '</div>' +
      '<div class="bp-body">' +
        '<div class="bp-velden">' +
          '<label class="bp-veld"><span class="bp-veld-lbl">Van week</span>' +
            '<select name="b2_blok_van[]" class="bp-select" onchange="beschHerbereken()">' + weekOpties(vanW) + '</select>' +
          '</label>' +
          '<label class="bp-veld"><span class="bp-veld-lbl">Tot en met</span>' +
            '<select name="b2_blok_tot[]" class="bp-select" onchange="beschHerbereken()">' + weekOpties(totW) + '</select>' +
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
            const aan = wkd.includes(String(d));
            return '<label class="besch-dagpil' + (aan ? ' actief' : '') + '"><input type="checkbox" class="besch-wkdag" value="' + d + '"' + (aan ? ' checked' : '') + ' onchange="beschDagpilToggle(this); beschHerbereken()"> ' + DAGEN_KORT[d] + '</label>';
          }).join('') +
          '</div>' +
          '<input type="hidden" name="b2_blok_wkdagen[]" value="' + esc(JSON.stringify(wkd.map(Number))) + '">' +
        '</div>' +
      '</div>';
    return div;
  }
  function periodeKopBijwerken(rij) {
    const van = rij.querySelector('[name="b2_blok_van[]"]').value;
    const tot = rij.querySelector('[name="b2_blok_tot[]"]').value;
    const regime = rij.querySelector('[name="b2_blok_regime[]"]').value;
    const wkd = Array.from(rij.querySelectorAll('.besch-wkdag:checked')).map(function (c) { return DAGEN_KORT[c.value]; });
    rij.querySelector('.bp-titel').textContent = 'Week ' + van + ' t/m ' + tot;
    rij.querySelector('.bp-sub').textContent = periodeSub(van, tot, regime, rij.classList.contains('dicht'), wkd);
    // Weekenddagen naar het hidden veld
    const g = Array.from(rij.querySelectorAll('.besch-wkdag:checked')).map(function (c) { return parseInt(c.value); });
    rij.querySelector('[name="b2_blok_wkdagen[]"]').value = JSON.stringify(g);
  }
  window.beschPeriodeToevoegen = function (data, open) {
    el('beschPeriodes').appendChild(periodeRij(data, open !== false));
    beschHerbereken();
  };
  window.beschPeriodeToggle = function (btn) {
    const rij = btn.closest('.besch-periode');
    const dicht = rij.classList.toggle('dicht');
    btn.setAttribute('aria-expanded', dicht ? 'false' : 'true');
    periodeKopBijwerken(rij);
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

  /* ── Secties "alleen als het van toepassing is" (ingeklapt met samenvatting) ── */
  function sectie(naam) { return document.querySelector('.bs-uitz-sec[data-sectie="' + naam + '"]'); }
  function sectieOpen(naam, open) {
    const sec = sectie(naam); if (!sec) return;
    const body = sec.querySelector('.bs-uitz-body'), kop = sec.querySelector('.bs-uitz-kop');
    body.hidden = !open;
    sec.classList.toggle('open', open);
    if (kop) kop.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  window.beschSectieToggle = function (btn) {
    const sec = btn.closest('.bs-uitz-sec');
    const naam = sec.dataset.sectie;
    const wasOpen = !sec.querySelector('.bs-uitz-body').hidden;
    sectieOpen(naam, !wasOpen);
    // Lege sectie openen = meteen een eerste rij klaarzetten
    if (!wasOpen && sec.dataset.leeg === '1') {
      if (naam === 'vakanties') beschVakantieToevoegen();
      else if (naam === 'verzoeken') beschVerzoekToevoegen();
      else if (naam === 'losse') beschLosseDagToevoegen();
    }
  };
  window.beschSectieToon = function (naam) {
    sectieOpen(naam, true);
    const sec = sectie(naam);
    if (sec && sec.scrollIntoView) sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  /* Sectie 2: vakanties (afwezig) */
  function vakantieRij(data) {
    data = data || {};
    const div = document.createElement('div');
    div.className = 'besch-rij besch-afwezig bs-item-rij';
    div.innerHTML =
      '<div class="bs-item-tekst">' +
        '<div class="bs-item-grid">' +
          veld('Van', '<input type="date" name="b2_afw_van[]" value="' + esc(data.van || '') + '" onchange="beschHerbereken()">') +
          veld('Tot en met', '<input type="date" name="b2_afw_tot[]" value="' + esc(data.tot || '') + '" onchange="beschHerbereken()">') +
        '</div>' +
        '<input type="text" name="b2_afw_label[]" value="' + esc(data.label || '') + '" placeholder="Reden (bijv. vakantie Frankrijk)" class="bs-invoer" maxlength="100" onchange="beschHerbereken()">' +
      '</div>' + wisBtn();
    return div;
  }
  window.beschVakantieToevoegen = function (data) { sectieOpen('vakanties', true); el('beschAfwezig').appendChild(vakantieRij(data)); beschHerbereken(); };

  /* Sectie 3: losse dagen */
  function losseDagRij(data) {
    data = data || {};
    const soort = data.soort === 'voorkeur_vrij' ? 'voorkeur_vrij' : 'niet_beschikbaar';
    const div = document.createElement('div');
    div.className = 'besch-rij besch-losdag bs-item-rij';
    div.innerHTML =
      '<div class="bs-item-tekst">' +
        '<div class="bs-item-grid">' +
          veld('Datum', '<input type="date" name="b2_dag_datum[]" value="' + esc(data.datum || '') + '" onchange="beschHerbereken()">') +
          veld('Wat geldt', '<select name="b2_dag_soort[]" class="bs-select" onchange="beschHerbereken()">' +
            '<option value="niet_beschikbaar"' + (soort === 'niet_beschikbaar' ? ' selected' : '') + '>Kan niet</option>' +
            '<option value="voorkeur_vrij"' + (soort === 'voorkeur_vrij' ? ' selected' : '') + '>Liever vrij</option>' +
          '</select>') +
        '</div>' +
        '<input type="text" name="b2_dag_reden[]" value="' + esc(data.reden || '') + '" placeholder="Reden (optioneel)" class="bs-invoer" maxlength="100" onchange="beschHerbereken()">' +
      '</div>' + wisBtn();
    return div;
  }
  window.beschLosseDagToevoegen = function (data) { sectieOpen('losse', true); el('beschLosseDagen').appendChild(losseDagRij(data)); beschHerbereken(); };

  /* Sectie 4: minder dagen (verzoek aan het bestuur) */
  function statusBadge(status) {
    if (status === 'goedgekeurd') return '<span class="bs-badge ok">Goedgekeurd</span>';
    if (status === 'afgewezen')   return '<span class="bs-badge error">Afgewezen</span>';
    return '<span class="bs-badge verzoek">Verzoek</span>';
  }
  function verzoekRij(data) {
    data = data || {};
    const status = data.status || 'open';
    const div = document.createElement('div');
    div.className = 'besch-rij besch-verzoek bs-item-rij';
    div.innerHTML =
      '<div class="bs-item-tekst">' +
        (status !== 'open' ? '<div>' + statusBadge(status) + '</div>' : '') +
        '<div class="bs-item-grid">' +
          veld('Van week', '<select name="b2_vz_van[]" class="bs-select" onchange="beschHerbereken()">' + weekOpties(klem(data.van, CFG.eerste)) + '</select>') +
          veld('Tot en met', '<select name="b2_vz_tot[]" class="bs-select" onchange="beschHerbereken()">' + weekOpties(klem(data.tot, CFG.laatste)) + '</select>') +
        '</div>' +
        '<div class="bs-item-grid">' +
          veld('Max. dagen per week', '<input type="number" name="b2_vz_dagen[]" min="1" max="4" inputmode="numeric" value="' + esc(data.dagen || '') + '" onchange="beschHerbereken()">') +
        '</div>' +
        verzoekDagen(data.pool || []) +
        '<input type="text" name="b2_vz_toelichting[]" value="' + esc(data.toelichting || '') + '" placeholder="Reden (bijv. tentamenweek)" class="bs-invoer" maxlength="300" onchange="beschHerbereken()">' +
        '<input type="hidden" name="b2_vz_id[]" value="' + esc(data.id || '') + '">' +
        '<input type="hidden" name="b2_vz_status[]" value="' + esc(status) + '">' +
        '<input type="hidden" name="b2_vz_pool[]" value="' + esc(JSON.stringify(data.pool || [])) + '">' +
        (status !== 'open' ? '<span class="bs-item-hint">Wijzig je dit verzoek, dan beoordeelt het bestuur het opnieuw.</span>' : '<span class="bs-item-hint">Het bestuur beoordeelt dit verzoek; je krijgt bericht.</span>') +
      '</div>' + wisBtn();
    return div;
  }
  /* Welke dagen kan iemand die weken wél? Leeg = maakt niet uit (het bestuur kiest).
     De keuze gaat via het verborgen veld b2_vz_pool[] mee, zoals de AI-wizard hem al vulde. */
  function verzoekDagen(pool) {
    const aan = (pool || []).map(String);
    return '<fieldset class="bs-vz-dagen">' +
      '<legend class="bs-item-lbl">Welke dagen kun je die weken wél? Laat leeg als het niet uitmaakt.</legend>' +
      '<div class="bs-dagpillen">' +
      [1, 2, 3, 4, 5, 6, 7].map(function (d) {
        const is = aan.includes(String(d));
        return '<label class="bs-dagpil' + (is ? ' actief' : '') + '"><input type="checkbox" class="besch-vzdag" value="' + d + '"' +
          (is ? ' checked' : '') + ' aria-label="' + DAGEN[d] + '" onchange="beschVzDagToggle(this)">' + DAGEN_KORT[d] + '</label>';
      }).join('') +
      '</div>' +
      '<span class="bs-item-hint besch-vz-poolhint" hidden></span>' +
    '</fieldset>';
  }
  window.beschVzDagToggle = function (cb) {
    const lbl = cb.closest('.bs-dagpil');
    if (lbl) lbl.classList.toggle('actief', cb.checked);
    const rij = cb.closest('.besch-verzoek');
    const pool = Array.from(rij.querySelectorAll('.besch-vzdag:checked')).map(function (c) { return parseInt(c.value); });
    rij.querySelector('[name="b2_vz_pool[]"]').value = JSON.stringify(pool);
    beschHerbereken();
  };
  // Minder dagen gekozen dan het aantal dat je wilt werken: dat kan het rooster niet waarmaken.
  function verzoekPoolHint(rij) {
    const hint = rij.querySelector('.besch-vz-poolhint'); if (!hint) return;
    const pool = rij.querySelectorAll('.besch-vzdag:checked').length;
    const dagen = parseInt(rij.querySelector('[name="b2_vz_dagen[]"]').value) || 0;
    const fout = pool > 0 && dagen > pool;
    hint.hidden = !fout;
    hint.textContent = fout ? 'Je kiest ' + pool + (pool === 1 ? ' dag' : ' dagen') + ', maar wilt er ' + dagen + ' werken. Kies meer dagen of verlaag het aantal.' : '';
  }
  window.beschVerzoekToevoegen = function (data) { sectieOpen('verzoeken', true); el('beschVerzoeken').appendChild(verzoekRij(data)); beschHerbereken(); };

  /* ── Vaste vrije dag (pillen) ────────────────────────────────── */
  window.beschVasteToggle = function (cb) {
    const lbl = cb.closest('.bs-dagpil');
    if (lbl) lbl.classList.toggle('actief', cb.checked);
    beschHerbereken();
  };

  /* ── Formulier → één data-object (samenvatting, concept, validatie) ── */
  window.beschVerzamel = function () {
    const d = { periodes: [], vaste: [], vaste_toelichting: '', vakanties: [], losse: [], verzoeken: [], voorlopig: false };
    document.querySelectorAll('#beschPeriodes .besch-periode').forEach(function (rij) {
      d.periodes.push({
        van: parseInt(rij.querySelector('[name="b2_blok_van[]"]').value),
        tot: parseInt(rij.querySelector('[name="b2_blok_tot[]"]').value),
        regime: rij.querySelector('[name="b2_blok_regime[]"]').value,
        wkdagen: Array.from(rij.querySelectorAll('.besch-wkdag:checked')).map(function (c) { return parseInt(c.value); })
      });
    });
    d.vaste = Array.from(document.querySelectorAll('.besch-vastedag:checked')).map(function (c) { return parseInt(c.value); });
    d.vaste_toelichting = el('beschVasteToelichting') ? el('beschVasteToelichting').value.trim() : '';
    document.querySelectorAll('#beschAfwezig .besch-afwezig').forEach(function (rij) {
      d.vakanties.push({ van: rij.querySelector('[name="b2_afw_van[]"]').value, tot: rij.querySelector('[name="b2_afw_tot[]"]').value, label: rij.querySelector('[name="b2_afw_label[]"]').value.trim() });
    });
    document.querySelectorAll('#beschLosseDagen .besch-losdag').forEach(function (rij) {
      d.losse.push({ datum: rij.querySelector('[name="b2_dag_datum[]"]').value, soort: rij.querySelector('[name="b2_dag_soort[]"]').value, reden: rij.querySelector('[name="b2_dag_reden[]"]').value.trim() });
    });
    document.querySelectorAll('#beschVerzoeken .besch-verzoek').forEach(function (rij) {
      let pool = []; try { pool = JSON.parse(rij.querySelector('[name="b2_vz_pool[]"]').value || '[]'); } catch (e) {}
      d.verzoeken.push({
        id: rij.querySelector('[name="b2_vz_id[]"]').value || null,
        status: rij.querySelector('[name="b2_vz_status[]"]').value || 'open',
        van: parseInt(rij.querySelector('[name="b2_vz_van[]"]').value),
        tot: parseInt(rij.querySelector('[name="b2_vz_tot[]"]').value),
        dagen: parseInt(rij.querySelector('[name="b2_vz_dagen[]"]').value) || null,
        pool: pool,
        toelichting: rij.querySelector('[name="b2_vz_toelichting[]"]').value.trim()
      });
    });
    const vl = document.querySelector('[name="b2_voorlopig"]');
    d.voorlopig = !!(vl && vl.checked);
    return d;
  };

  /* Data-object → formulier (opgeslagen data, AI-voorstel, concept). Vervangt alles. */
  window.beschVulIn = function (d, opties) {
    d = d || {}; opties = opties || {};
    el('beschPeriodes').innerHTML = '';
    el('beschAfwezig').innerHTML = '';
    el('beschLosseDagen').innerHTML = '';
    el('beschVerzoeken').innerHTML = '';
    (d.periodes || []).forEach(function (p) { el('beschPeriodes').appendChild(periodeRij(p, !!opties.periodesOpen)); });
    if (!(d.periodes || []).length) el('beschPeriodes').appendChild(periodeRij({ van: CFG.eerste, tot: CFG.laatste, regime: 'vijf' }, true));
    (d.vakanties || []).forEach(function (v) { el('beschAfwezig').appendChild(vakantieRij(v)); });
    (d.losse || []).forEach(function (l) { el('beschLosseDagen').appendChild(losseDagRij(l)); });
    (d.verzoeken || []).forEach(function (v) { el('beschVerzoeken').appendChild(verzoekRij(v)); });
    const vaste = (d.vaste || []).map(String);
    document.querySelectorAll('.besch-vastedag').forEach(function (cb) {
      cb.checked = vaste.includes(cb.value);
      const lbl = cb.closest('.bs-dagpil'); if (lbl) lbl.classList.toggle('actief', cb.checked);
    });
    if (el('beschVasteToelichting')) el('beschVasteToelichting').value = d.vaste_toelichting || '';
    const vl = document.querySelector('[name="b2_voorlopig"]');
    if (vl) vl.checked = !!d.voorlopig;
    ['vaste', 'vakanties', 'verzoeken', 'losse'].forEach(function (n) { sectieOpen(n, false); });
    beschHerbereken();
  };

  /* AI-antwoord (blokken/afwezig/losse_dagen/…) → data-object */
  function aiNaarData(r) {
    return {
      periodes:  (r.blokken || []).map(function (b) { return { van: b.van_week, tot: b.tot_week, regime: b.regime, wkdagen: b.weekend_dagen || [6, 7] }; }),
      vaste:     r.vaste_vrije_dagen || [],
      vaste_toelichting: r.vaste_reden || '',
      vakanties: (r.afwezig || []).map(function (a) { return { van: a.van_datum || a.van, tot: a.tot_datum || a.tot, label: a.label }; }),
      losse:     (r.losse_dagen || []).map(function (l) { return { datum: l.datum, soort: l.soort, reden: l.reden }; }),
      verzoeken: (r.verzoeken || []).map(function (v) { return { van: v.van_week, tot: v.tot_week, dagen: v.dagen_aantal || v.dagen, pool: v.pool || [], toelichting: v.toelichting }; }),
      voorlopig: false
    };
  }

  /* ── Samenvatting (navy) + samenvattingsregels per sectie ───── */
  window.beschHerbereken = function () {
    const d = beschVerzamel();
    const regels = [];
    document.querySelectorAll('#beschPeriodes .besch-periode').forEach(periodeKopBijwerken);
    document.querySelectorAll('#beschVerzoeken .besch-verzoek').forEach(verzoekPoolHint);
    d.periodes.forEach(function (p) {
      if (!p.van || !p.tot) return;
      if (p.regime === 'weekend') {
        const dagen = p.wkdagen.map(function (n) { return DAGEN_KORT[n]; });
        regels.push('Werkt week ' + p.van + ' t/m ' + p.tot + ' — alleen weekend' + (dagen.length ? ' (' + dagen.join('/') + ')' : ''));
      } else {
        regels.push('Werkt week ' + p.van + ' t/m ' + p.tot + ' — hele weken (5 dagen)');
      }
    });
    if (d.vaste.length) regels.push('Vast vrij op ' + d.vaste.map(function (n) { return DAGEN[n]; }).join(', ') + (d.vaste_toelichting ? ' (' + d.vaste_toelichting + ')' : ''));
    d.vakanties.forEach(function (v) { if (v.van && v.tot) regels.push('Afwezig ' + datumNL(v.van) + ' t/m ' + datumNL(v.tot) + (v.label ? ' (' + v.label + ')' : '')); });
    d.losse.forEach(function (l) { if (l.datum) regels.push((l.soort === 'voorkeur_vrij' ? 'Liever vrij op ' : 'Niet beschikbaar op ') + datumNL(l.datum) + (l.reden ? ' (' + l.reden + ')' : '')); });
    d.verzoeken.forEach(function (v) {
      if (!v.van || !v.tot || !v.dagen) return;
      const wk = v.van === v.tot ? 'week ' + v.van : 'week ' + v.van + ' t/m ' + v.tot;
      const op = (v.pool || []).length ? ', op ' + v.pool.map(function (n) { return DAGEN_KORT[n]; }).join('/') : '';
      regels.push('Minder dagen gevraagd in ' + wk + ' (max ' + v.dagen + op + ')' + (v.status === 'goedgekeurd' ? ' — goedgekeurd' : v.status === 'afgewezen' ? ' — afgewezen' : ''));
    });
    const doel = el('beschPreview');
    if (doel) doel.textContent = regels.length ? regels.join('. ') + '.' : 'Vul hieronder je beschikbaarheid in.';

    // Samenvattingsregels in de ingeklapte secties
    const gevuldeVak = d.vakanties.filter(function (v) { return v.van && v.tot; });
    const gevuldeLos = d.losse.filter(function (l) { return l.datum; });
    const gevuldeVz  = d.verzoeken.filter(function (v) { return v.dagen; });
    sectieSub('vaste', d.vaste.length ? 'Elke ' + d.vaste.map(function (n) { return DAGEN[n]; }).join(', ') : 'Geen', d.vaste.length > 0, false);
    sectieSub('vakanties',
      gevuldeVak.length === 0 ? 'Niets ingevuld' : gevuldeVak.length === 1 ? datumNL(gevuldeVak[0].van) + ' – ' + datumNL(gevuldeVak[0].tot) + (gevuldeVak[0].label ? ' · ' + gevuldeVak[0].label : '') : gevuldeVak.length + ' periodes',
      d.vakanties.length > 0, d.vakanties.length === 0);
    sectieSub('verzoeken',
      gevuldeVz.length === 0 ? 'Niets ingevuld' : gevuldeVz.length === 1 ? '1 verzoek — ' + (gevuldeVz[0].van === gevuldeVz[0].tot ? 'week ' + gevuldeVz[0].van : 'week ' + gevuldeVz[0].van + '–' + gevuldeVz[0].tot) + ', max. ' + gevuldeVz[0].dagen + ' dagen' : gevuldeVz.length + ' verzoeken',
      d.verzoeken.length > 0, d.verzoeken.length === 0);
    sectieSub('losse',
      gevuldeLos.length === 0 ? 'Niets ingevuld' : gevuldeLos.length === 1 ? datumNL(gevuldeLos[0].datum) + (gevuldeLos[0].reden ? ' · ' + gevuldeLos[0].reden : '') : gevuldeLos.length + ' dagen',
      d.losse.length > 0, d.losse.length === 0);
    document.dispatchEvent(new CustomEvent('besch:gewijzigd'));
  };
  function sectieSub(naam, tekst, gevuld, leeg) {
    const sec = sectie(naam); if (!sec) return;
    const sub = sec.querySelector('.bs-uitz-sub'); if (sub) sub.textContent = tekst;
    sec.classList.toggle('gevuld', !!gevuld);
    sec.dataset.leeg = leeg ? '1' : '0';
  }

  /* ── Validatie bij opslaan ───────────────────────────────────── */
  window.beschValideer = function () {
    const periodes = el('beschPeriodes');
    let foutPlek = el('beschPeriodesFout');
    if (!document.querySelectorAll('#beschPeriodes .besch-periode').length) {
      // Melding bij de werkperiodes zelf (P13Melding uit app.js), nooit alert().
      if (!foutPlek && periodes) {
        foutPlek = document.createElement('div');
        foutPlek.id = 'beschPeriodesFout';
        periodes.parentNode.insertBefore(foutPlek, periodes);
      }
      const tekst = 'Geef minstens één werkperiode op (wanneer je kunt werken).';
      if (window.P13Melding && foutPlek) P13Melding.fout(foutPlek, tekst);
      else if (foutPlek) { foutPlek.setAttribute('role', 'alert'); foutPlek.textContent = tekst; }
      return false;
    }
    if (foutPlek && window.P13Melding) P13Melding.wis(foutPlek);
    // Vaste vrije dagen: verborgen b2_vaste_uit[]-velden opnieuw opbouwen uit de checkboxes
    const form = el('beschForm') || document.querySelector('form');
    document.querySelectorAll('.besch-vaste-hidden').forEach(function (h) { h.remove(); });
    document.querySelectorAll('.besch-vastedag:checked').forEach(function (cb) {
      const h = document.createElement('input');
      h.type = 'hidden'; h.name = 'b2_vaste_uit[]'; h.value = cb.value; h.className = 'besch-vaste-hidden';
      form.appendChild(h);
    });
    if (window.BESCH_CONCEPT) window.BESCH_CONCEPT.stop();
    return true;
  };

  /* ── AI-wizard ───────────────────────────────────────────────── */
  window.beschAiChip = function (btn) {
    const veld = el('beschAiTekst');
    if (veld) { veld.value = btn.textContent.trim(); veld.focus(); }
  };
  function wizToon(stapId) {
    ['wizStap1', 'wizStap2', 'wizStap3', 'beschForm'].forEach(function (id) {
      const e = el(id); if (e) e.style.display = (id === stapId) ? '' : 'none';
    });
    const link = el('beschOpnieuwSlim'); if (link) link.style.display = (stapId === 'beschForm') ? '' : 'none';
    window.scrollTo({ top: 0, behavior: 'smooth' });
    document.dispatchEvent(new CustomEvent('besch:gewijzigd'));
  }
  window.beschHuidigeStap = function () {
    const f = el('beschForm');
    return (f && f.style.display !== 'none') ? 'formulier' : 'wizard';
  };
  window.wizNaarFormulier = function (sectieNaam) {
    wizToon('beschForm');
    if (sectieNaam) setTimeout(function () { beschSectieToon(sectieNaam); }, 50);
  };
  window.wizNaarStart = function () { if (el('wizStap1')) wizToon('wizStap1'); };
  window.wizOvernemen = function () { wizToon('beschForm'); };

  let wizLaatsteTekst = '';

  function wizVerstuurTekst(tekst, knop, statusEl) {
    if (knop) { knop.disabled = true; knop.dataset.oud = knop.textContent; knop.textContent = 'Bezig…'; }
    if (statusEl) statusEl.textContent = '';
    wizLaatsteTekst = tekst;
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
        // Secties vast invullen (ook bij een vervolgvraag: wat we WEL weten staat er dan al)
        beschVulIn(aiNaarData(res));
        if (el('wizStap3')) {
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
    (d.blokken || []).forEach(function (b) {
      items.push({ titel: 'Werkperiode: week ' + b.van_week + ' t/m ' + b.tot_week, sub: b.regime === 'weekend' ? 'alleen weekend' : 'hele weken', sectie: '' });
    });
    (d.vaste_vrije_dagen || []).forEach(function (n) {
      items.push({ titel: 'Vaste vrije dag: ' + (DAGEN[n] || n), sub: d.vaste_reden || '', sectie: 'vaste' });
    });
    (d.afwezig || []).forEach(function (a) {
      items.push({ titel: 'Vakantie: ' + datumNL(a.van_datum || a.van) + ' t/m ' + datumNL(a.tot_datum || a.tot), sub: a.label || '', sectie: 'vakanties' });
    });
    (d.losse_dagen || []).forEach(function (l) {
      items.push({ titel: (l.soort === 'voorkeur_vrij' ? 'Liever vrij: ' : 'Niet beschikbaar: ') + datumNL(l.datum), sub: l.reden || '', sectie: 'losse' });
    });
    (d.verzoeken || []).forEach(function (v) {
      const wk = v.van_week === v.tot_week ? 'week ' + v.van_week : 'week ' + v.van_week + ' t/m ' + v.tot_week;
      const pool = v.dagen_pool || v.pool || [];
      items.push({ titel: 'Minder dagen: ' + wk + ' — max ' + (v.dagen_aantal || v.dagen) + ' dagen' + (pool.length ? ' (' + pool.map(function (n) { return DAGEN_KORT[n]; }).join('/') + ')' : ''), sub: 'Binnen je werkperiode · het bestuur beoordeelt dit', waarschuwing: true, sectie: 'verzoeken' });
    });
    if (!items.length) { doel.innerHTML = ''; return; }
    let html = '<div class="wiz3-kaart">';
    items.forEach(function (it, i) {
      if (i > 0) html += '<div class="wiz3-lijn"></div>';
      html += '<div class="wiz3-rij' + (it.waarschuwing ? ' waarschuwing' : '') + '">' +
        '<div class="wiz3-rij-tekst"><span class="wiz3-rij-titel">' + esc(it.titel) + '</span>' +
        (it.sub ? '<span class="wiz3-rij-sub">' + esc(it.sub) + '</span>' : '') + '</div>' +
        '<button type="button" class="wiz3-wijzig" onclick="wizNaarFormulier(\'' + it.sectie + '\')">Wijzig</button>' +
      '</div>';
    });
    html += '</div>';
    doel.innerHTML = html;
  }
  function wizToonNavraag(vraag) {
    const doel = el('wizNavraag');
    if (!doel) return;
    if (!vraag) { doel.innerHTML = ''; return; }
    doel.innerHTML =
      '<div class="wiz-navraag">' +
        '<div class="wiz-navraag-kop">Nog even checken</div>' +
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

  /* ── Start ───────────────────────────────────────────────────── */
  function init() {
    const vt = el('beschVasteToelichting');
    if (vt) vt.addEventListener('input', beschHerbereken);
    const form = el('beschForm');
    if (form) form.addEventListener('input', function (e) { if (e.target && e.target.type === 'text') beschHerbereken(); });

    // Startdata: concept (afgebroken sessie) gaat vóór opgeslagen data; anders leeg.
    const concept = CFG.concept || null;
    const bron = (concept && concept.periodes) ? concept : (CFG.data || {});
    beschVulIn(bron, { periodesOpen: !(bron.periodes && bron.periodes.length) });
    if (concept && concept.ai_tekst && el('beschAiTekst') && !el('beschAiTekst').value) el('beschAiTekst').value = concept.ai_tekst;

    // Startpunt staat al in de HTML (PHP): wizard bij lege start, anders het formulier.
    // Alleen corrigeren als de wizard ontbreekt terwijl het formulier verborgen is.
    if (!el('wizStap1') && el('beschForm') && el('beschForm').style.display === 'none') wizToon('beschForm');

    // Autosave (concept): alles wat de sollicitant invult, ook de wizard-tekst en de huidige stap.
    if (window.PortaalConcept && CFG.apiConcept) {
      const csrf = document.querySelector('[name="_csrf"]');
      window.BESCH_CONCEPT = PortaalConcept.maak({
        url: CFG.apiConcept,
        csrf: csrf ? csrf.value : '',
        statusEl: el('beschAutosave'),
        tekstEl: el('beschAutosaveTekst'),
        verzamel: function () {
          const d = beschVerzamel();
          d.stap = beschHuidigeStap();
          d.ai_tekst = el('beschAiTekst') ? el('beschAiTekst').value : '';
          return d;
        }
      });
      window.BESCH_CONCEPT.prime();
      document.addEventListener('besch:gewijzigd', function () { window.BESCH_CONCEPT.plan(); });
      if (form) form.addEventListener('change', function () { window.BESCH_CONCEPT.plan(); });
      const ai = el('beschAiTekst');
      if (ai) ai.addEventListener('input', function () { window.BESCH_CONCEPT.plan(); });
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
