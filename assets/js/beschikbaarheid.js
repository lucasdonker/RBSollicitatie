/* Paal 13 — gedeelde beschikbaarheid-formulier logica (b2 + AI-tekst).
   Gebruikt door het losse beschikbaarheidsformulier. Verwacht dat B2_EERSTE en B2_LAATSTE
   als globale constanten zijn gezet vóór dit bestand geladen wordt. */
function b2WeekOpties(sel) {
  let h = '';
  for (const wn in B2_WEEKLABEL) h += '<option value="' + wn + '"' + (parseInt(wn)===sel?' selected':'') + '>' + B2_WEEKLABEL[wn] + '</option>';
  return h;
}

function b2BlokToevoegen() {
  const div = document.createElement('div');
  div.className = 'b2-blok-rij';
  div.style.cssText = 'border:1px solid var(--rand-licht);border-radius:8px;padding:10px;margin-bottom:8px';
  div.innerHTML =
    '<div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">' +
      '<span style="font-size:12px;color:var(--grijs)">van</span>' +
      '<select name="b2_blok_van[]" onchange="b2Herbereken()" aria-label="Van week" style="flex:1;min-width:110px">' + b2WeekOpties(B2_EERSTE) + '</select>' +
      '<span style="font-size:12px;color:var(--grijs)">t/m</span>' +
      '<select name="b2_blok_tot[]" onchange="b2Herbereken()" aria-label="Tot en met week" style="flex:1;min-width:110px">' + b2WeekOpties(B2_LAATSTE) + '</select>' +
      '<select name="b2_blok_regime[]" onchange="b2RegimeCheck(this);b2Herbereken()" aria-label="Regime" style="flex:1;min-width:130px"><option value="vijf">hele weken (5 dagen)</option><option value="weekend">alleen weekenden</option></select>' +
      '<button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="b2BlokVerwijder(this)" aria-label="Periode verwijderen" style="min-width:44px;min-height:38px"><svg class="ic" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>' +
    '</div>' +
    '<div class="b2-weekend-dagen" style="display:none;margin-top:8px;padding-top:8px;border-top:1px dashed var(--rand-licht)">' +
      '<span style="font-size:12px;color:var(--tekst-zacht);margin-right:6px">Welke dagen?</span>' +
      [[5,'vr'],[6,'za'],[7,'zo']].map(d =>
        '<label style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border:1px solid var(--rand);border-radius:6px;cursor:pointer;font-size:12.5px;margin-right:4px"><input type="checkbox" class="b2-wkdag" value="'+d[0]+'" checked onchange="b2Herbereken()"> '+d[1]+'</label>'
      ).join('') +
      '<input type="hidden" name="b2_blok_wkdagen[]" value="[5,6,7]">' +
    '</div>';
  document.getElementById('b2BlokLijst').appendChild(div);
  b2Herbereken();
}

function b2BlokVerwijder(btn) {
  const rijen = document.querySelectorAll('#b2BlokLijst .b2-blok-rij');
  if (rijen.length <= 1) { b2Fout('Je moet minimaal één periode opgeven.'); return; }
  btn.closest('.b2-blok-rij').remove();
  b2Herbereken();
}

/* ── AI-beschikbaarheid (experimenteel) ──────────────────── */
function aiBeschVerwerk() {
  const veld = document.getElementById('aiBeschTekst');
  const tekst = (veld?.value || '').trim();
  const status = document.getElementById('aiBeschStatus');
  const vraagBox = document.getElementById('aiBeschVraag');
  const knop = document.getElementById('aiBeschKnop');
  if (!tekst) { status.textContent = 'Typ eerst iets in.'; return; }

  // Blokkeer input + knop tijdens verwerken
  const t = knop.querySelector('.ai-tekst'), s = knop.querySelector('.ai-spinner');
  const zetBezig = (bezig) => {
    knop.disabled = bezig;
    veld.readOnly = bezig;
    veld.style.opacity = bezig ? '0.6' : '';
    veld.style.cursor = bezig ? 'not-allowed' : '';
    if (t&&s){ t.style.display = bezig ? 'none' : ''; s.style.display = bezig ? '' : 'none'; }
  };
  zetBezig(true);
  status.textContent = 'De AI leest je tekst…'; vraagBox.style.display = 'none';

  const csrf = document.querySelector('[name="_csrf"]')?.value || '';
  fetch(location.pathname, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ ai_besch_submit:'1', tekst, _csrf:csrf }) })
    .then(r => r.json())
    .then(d => {
      zetBezig(false);
      if (d.status === 'uit')      { status.textContent = 'De AI-hulp staat uit.'; return; }
      if (d.status === 'fout')     { status.textContent = d.bericht || 'Er ging iets mis.'; return; }
      if (d.status === 'geweigerd'){ status.textContent = ''; vraagBox.style.display=''; vraagBox.style.background='var(--rood-bg)'; vraagBox.style.borderColor='var(--rood)'; vraagBox.textContent = '⚠️ ' + (d.bericht || 'Ik kon geen beschikbaarheid herkennen.'); return; }
      if (d.status === 'vraag')    { status.textContent = ''; vraagBox.style.display=''; vraagBox.style.background='var(--geel-bg)'; vraagBox.style.borderColor='var(--geel)'; vraagBox.textContent = '❓ ' + d.vraag; return; }
      if (d.status === 'ok') {
        aiBeschVulIn(d);
        status.textContent = '✓ Ingevuld — controleer hieronder of het klopt.';
        if (d.vraag) { vraagBox.style.display=''; vraagBox.style.background='var(--geel-bg)'; vraagBox.style.borderColor='var(--geel)'; vraagBox.textContent = '❓ ' + d.vraag; }
      }
    })
    .catch(() => {
      zetBezig(false);
      status.textContent = 'Kon de AI niet bereiken. Vul het formulier handmatig in.';
    });
}

// Vul de bestaande formuliervelden met de (gevalideerde) AI-data
function aiBeschVulIn(d) {
  // Periode-blokken: bestaande rijen wissen, dan opnieuw opbouwen
  const lijst = document.getElementById('b2BlokLijst');
  lijst.innerHTML = '';
  (d.blokken || []).forEach(blok => {
    b2BlokToevoegen();
    const rij = lijst.lastElementChild;
    // Klem de weeknummers binnen het geldige seizoensbereik, anders bestaat de <option> niet
    // en springt de dropdown terug naar leeg (AI kan soms een week net buiten het seizoen geven).
    const klem = (w) => Math.min(B2_LAATSTE, Math.max(B2_EERSTE, parseInt(w) || B2_EERSTE));
    const vanW = klem(blok.van_week), totW = klem(blok.tot_week);
    rij.querySelector('[name="b2_blok_van[]"]').value = vanW;
    rij.querySelector('[name="b2_blok_tot[]"]').value = Math.max(vanW, totW);
    const regimeSel = rij.querySelector('[name="b2_blok_regime[]"]');
    regimeSel.value = blok.regime === 'weekend' ? 'weekend' : 'vijf';
    b2RegimeCheck(regimeSel);
    if (blok.regime === 'weekend' && Array.isArray(blok.weekend_dagen)) {
      rij.querySelectorAll('.b2-wkdag').forEach(cb => {
        cb.checked = blok.weekend_dagen.includes(parseInt(cb.value));
      });
      const hidden = rij.querySelector('[name="b2_blok_wkdagen[]"]');
      if (hidden) hidden.value = JSON.stringify(blok.weekend_dagen);
    }
  });
  if (!(d.blokken || []).length) b2BlokToevoegen(); // altijd minstens één rij

  // Afwezigheid
  if ((d.afwezig || []).length) {
    const afwLijst = document.getElementById('b2AfwezigLijst');
    if (afwLijst) {
      afwLijst.innerHTML = '';
      d.afwezig.forEach(a => {
        b2AfwezigToevoegen();
        const rij = afwLijst.lastElementChild;
        // Afwezigheid komt nu als exacte datums uit de AI (halve weken blijven behouden).
        // Terugval: als er toch nog een weeknummer komt, zetten we die om naar maandag/zondag.
        const van = a.van_datum || (a.van_week ? aiWeekNaarDatum(a.van_week, 'start') : '');
        const tot = a.tot_datum || (a.tot_week ? aiWeekNaarDatum(a.tot_week, 'eind') : '');
        if (van) rij.querySelector('[name="b2_afw_van[]"]').value = van;
        if (tot) rij.querySelector('[name="b2_afw_tot[]"]').value = tot;
        const lbl = rij.querySelector('[name="b2_afw_label[]"]');
        if (lbl && a.label) lbl.value = a.label;
      });
    }
  }

  // Losse dagen (concrete datums die iemand niet kan / liever vrij wil)
  if ((d.losse_dagen || []).length) {
    const losLijst = document.getElementById('b2LosseDagLijst');
    if (losLijst) {
      losLijst.innerHTML = '';
      d.losse_dagen.forEach(ld => {
        b2LosseDagToevoegen();
        const rij = losLijst.lastElementChild;
        if (ld.datum) rij.querySelector('[name="b2_dag_datum[]"]').value = ld.datum;
        const soortSel = rij.querySelector('[name="b2_dag_soort[]"]');
        if (soortSel && ld.soort) soortSel.value = ld.soort;
        const redenInp = rij.querySelector('[name="b2_dag_reden[]"]');
        if (redenInp && ld.reden) redenInp.value = ld.reden;
      });
    }
  }

  // Vaste vrije dag(en) — weekdagnummers die elke week vrij zijn
  if ((d.vaste_vrije_dagen || []).length) {
    // Vink de juiste weekdagen aan
    document.querySelectorAll('[name="b2_vaste_uit[]"]').forEach(cb => {
      cb.checked = d.vaste_vrije_dagen.map(String).includes(cb.value);
    });
    const toel = document.getElementById('b2VasteToelichting');
    if (toel && d.vaste_reden) toel.value = d.vaste_reden;
    const cbAan = document.getElementById('b2VasteAan');
    if (cbAan) cbAan.checked = true;
    if (typeof b2VasteToggle === 'function') b2VasteToggle();
  }

  // Verzoeken — minder dagen in bepaalde weken
  if ((d.verzoeken || []).length) {
    const vzLijst = document.getElementById('b2VerzoekLijst');
    if (vzLijst) {
      vzLijst.innerHTML = '';
      d.verzoeken.forEach(vz => {
        b2VerzoekToevoegen();
        const rij = vzLijst.lastElementChild;

        if (vz.dagen_aantal) { const n = rij.querySelector('[name="b2_vz_dagen[]"]'); if (n) n.value = vz.dagen_aantal; }
        const toel = rij.querySelector('[name="b2_vz_toelichting[]"]');
        if (toel && vz.toelichting) toel.value = vz.toelichting;
        // Pool-dagen aanvinken
        if ((vz.pool || []).length) {
          rij.querySelectorAll('.b2-vz-pooldag').forEach(cb => {
            cb.checked = vz.pool.map(String).includes(cb.value);
          });
        }
      });
    }
  }

  // Uitzonderingen die de AI invulde meteen uitklappen, zodat de gebruiker ze ziet
  b2OpenDetailsMet('b2AfwezigLijst',   (d.afwezig || []).length);
  b2OpenDetailsMet('b2LosseDagLijst',  (d.losse_dagen || []).length);
  b2OpenDetailsMet('b2VasteBlok',      (d.vaste_vrije_dagen || []).length);
  b2OpenDetailsMet('b2VerzoekLijst',   (d.verzoeken || []).length);

  b2Herbereken();
  // Scroll naar het formulier zodat de gebruiker het resultaat ziet
  document.getElementById('b2BlokLijst')?.scrollIntoView({behavior:'smooth', block:'center'});
}

// Klap het <details>-blok open dat een bepaald element bevat (bij AI-invulling)
function b2OpenDetailsMet(elementId, conditie) {
  if (!conditie) return;
  const el = document.getElementById(elementId);
  if (!el) return;
  const details = el.closest('details.b2-uitz');
  if (details) details.open = true;
}

// Weeknummer → datum (op basis van de week-opties die al in de select staan)
const AI_WEEK_DATUMS = window.AI_WEEK_DATUMS || [];
function aiWeekNaarDatum(wk, welke) {
  const m = AI_WEEK_DATUMS.find(x => x.wk === parseInt(wk));
  return m ? (welke === 'start' ? m.start : m.eind) : null;
}

function b2RegimeCheck(sel) {
  const rij = sel.closest('.b2-blok-rij');
  const wk = rij.querySelector('.b2-weekend-dagen');
  if (wk) wk.style.display = sel.value === 'weekend' ? '' : 'none';
}

function b2AfwezigToevoegen() {
  const div = document.createElement('div');
  div.className = 'b2-afwezig-rij';
  div.style.cssText = 'display:flex;gap:6px;align-items:center;margin-bottom:6px;flex-wrap:wrap';
  div.innerHTML = '<input type="date" name="b2_afw_van[]" onchange="b2Herbereken()" aria-label="Afwezig van" style="flex:1;min-width:130px">' +
    '<span style="color:var(--grijs)">t/m</span>' +
    '<input type="date" name="b2_afw_tot[]" onchange="b2Herbereken()" aria-label="Afwezig tot en met" style="flex:1;min-width:130px">' +
    '<input type="text" name="b2_afw_label[]" placeholder="bijv. vakantie" aria-label="Omschrijving" style="flex:1;min-width:100px">' +
    '<button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="this.parentElement.remove();b2Herbereken()" aria-label="Verwijderen" style="min-width:44px;min-height:38px"><svg class="ic" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>';
  document.getElementById('b2AfwezigLijst').appendChild(div);
}

function b2LosseDagToevoegen() {
  const div = document.createElement('div');
  div.className = 'b2-losdag-rij';
  div.style.cssText = 'display:flex;gap:6px;align-items:center;margin-bottom:6px;flex-wrap:wrap';
  div.innerHTML = '<input type="date" name="b2_dag_datum[]" aria-label="Datum" style="flex:1;min-width:140px">' +
    '<select name="b2_dag_soort[]" aria-label="Soort" style="flex:1;min-width:130px"><option value="niet_beschikbaar">Kan ik niet</option><option value="voorkeur_vrij">Liever vrij</option></select>' +
    '<input type="text" name="b2_dag_reden[]" placeholder="reden (optioneel)" aria-label="Reden" style="flex:1;min-width:100px">' +
    '<button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="this.parentElement.remove()" aria-label="Verwijderen" style="min-width:44px;min-height:38px"><svg class="ic" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>';
  document.getElementById('b2LosseDagLijst').appendChild(div);
}

function b2VerzoekToevoegen() {
  const div = document.createElement('div');
  div.className = 'b2-verzoek-rij';
  div.style.cssText = 'border:1px solid var(--rand-licht);border-radius:8px;padding:10px;margin-bottom:8px';
  div.innerHTML =
    '<div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">' +
      '<span style="font-size:12px;color:var(--grijs)">van</span>' +
      '<select name="b2_vz_van[]" aria-label="Verzoek van week" style="flex:1;min-width:105px">' + b2WeekOpties(B2_EERSTE) + '</select>' +
      '<span style="font-size:12px;color:var(--grijs)">t/m</span>' +
      '<select name="b2_vz_tot[]" aria-label="Verzoek tot week" style="flex:1;min-width:105px">' + b2WeekOpties(B2_LAATSTE) + '</select>' +
      '<input type="number" name="b2_vz_dagen[]" min="1" max="6" aria-label="Aantal dagen" style="width:60px" placeholder="dagen">' +
      '<span style="font-size:12px;color:var(--grijs)">dagen</span>' +
      '<button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="this.closest(\'.b2-verzoek-rij\').remove()" aria-label="Verwijderen" style="min-width:44px;min-height:38px"><svg class="ic" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>' +
    '</div>' +
    '<input type="text" name="b2_vz_toelichting[]" placeholder="reden (bijv. school)" aria-label="Reden" style="width:100%;margin-top:6px">' +
    '<div style="margin-top:8px">' +
      '<span style="font-size:12px;color:var(--tekst-zacht);margin-right:6px">Welke dagen kun je die week wél? Vink de dagen uit die niet kunnen.</span>' +
      [[1,'ma'],[2,'di'],[3,'wo'],[4,'do'],[5,'vr'],[6,'za'],[7,'zo']].map(d =>
        '<label style="display:inline-flex;align-items:center;gap:3px;padding:3px 8px;border:1px solid var(--rand);border-radius:5px;cursor:pointer;font-size:12px;margin:2px 2px 0 0"><input type="checkbox" class="b2-vz-pooldag" value="'+d[0]+'" checked> '+d[1]+'</label>'
      ).join('') +
      '<input type="hidden" name="b2_vz_pool[]" value="[1,2,3,4,5,6,7]">' +
    '</div>';
  document.getElementById('b2VerzoekLijst').appendChild(div);
}

function b2VasteToggle() {
  // Het vaste-dag-blok zit nu in een uitklapbare <details>; die regelt zichtbaarheid.
  // Deze functie houdt alleen de checkbox-status in sync (voor bestaande aanroepen).
  const cb = document.getElementById('b2VasteAan');
  const blok = document.getElementById('b2VasteBlok');
  if (blok) blok.style.display = '';
  // Als iemand alle dagen uitvinkt, hoeft er niets gewist te worden — leeg = geen voorkeur.
}

function b2VasteDagCheck() { /* voorkeur — geen harde eis meer, preview verzorgt de rest */ }

function b2Fout(tekst) {
  const box = document.getElementById('b2FoutMelding');
  if (!box) { alert(tekst); return; }
  box.textContent = '⚠️ ' + tekst;
  box.style.display = '';
  box.scrollIntoView({behavior:'smooth', block:'center'});
}
function b2FoutWis() { const box = document.getElementById('b2FoutMelding'); if (box) box.style.display = 'none'; }

function b2Herbereken() {
  // Serialiseer weekend-dagen + verzoek-pools naar hidden inputs
  document.querySelectorAll('#b2BlokLijst .b2-blok-rij').forEach(rij => {
    const hid = rij.querySelector('[name="b2_blok_wkdagen[]"]');
    if (hid) {
      const dagen = Array.from(rij.querySelectorAll('.b2-wkdag:checked')).map(c => parseInt(c.value));
      hid.value = JSON.stringify(dagen.length ? dagen : [5,6,7]);
    }
  });
  document.querySelectorAll('#b2VerzoekLijst .b2-verzoek-rij').forEach(rij => {
    const hid = rij.querySelector('[name="b2_vz_pool[]"]');
    const aangevinkt = Array.from(rij.querySelectorAll('.b2-vz-pooldag:checked')).map(c => parseInt(c.value));
    if (hid) hid.value = JSON.stringify(aangevinkt);

    // Validatie: aantal aangevinkte dagen moet minstens gelijk zijn aan het gevraagde aantal
    const aantalInput = rij.querySelector('[name="b2_vz_dagen[]"]');
    const gevraagd = aantalInput ? parseInt(aantalInput.value) || 0 : 0;
    let waarschuwing = rij.querySelector('.b2-vz-waarschuwing');
    if (!waarschuwing) {
      waarschuwing = document.createElement('div');
      waarschuwing.className = 'b2-vz-waarschuwing';
      waarschuwing.style.cssText = 'font-size:12px;color:var(--oranje);margin-top:6px;display:none';
      rij.appendChild(waarschuwing);
    }
    if (gevraagd > 0 && aangevinkt.length < gevraagd) {
      waarschuwing.textContent = '⚠️ Je vraagt ' + gevraagd + ' dagen aan, maar hebt er ' + aangevinkt.length + ' aangevinkt. Vink minstens ' + gevraagd + ' dagen aan die je wél kunt.';
      waarschuwing.style.display = '';
    } else {
      waarschuwing.style.display = 'none';
    }
  });

  const box = document.getElementById('b2Preview');
  const teller = document.getElementById('b2WekenTeller');
  if (!box) return;

  const rijen = Array.from(document.querySelectorAll('#b2BlokLijst .b2-blok-rij'));
  const afwV = Array.from(document.querySelectorAll('[name="b2_afw_van[]"]')).map(i => i.value);
  const afwT = Array.from(document.querySelectorAll('[name="b2_afw_tot[]"]')).map(i => i.value);
  const vasteUit = Array.from(document.querySelectorAll('[name="b2_vaste_uit[]"]:checked')).map(c => parseInt(c.value));

  if (!rijen.length) { box.innerHTML = '<span style="color:var(--grijs);font-style:italic">Vul je periode in…</span>'; if (teller) teller.textContent=''; return; }

  let zinnen = [];
  let weekTeller = {}; // week => aantal blokken (voor overlap-detectie)
  let totaalWeken = new Set();
  for (const rij of rijen) {
    let van = parseInt(rij.querySelector('[name="b2_blok_van[]"]').value);
    let tot = parseInt(rij.querySelector('[name="b2_blok_tot[]"]').value);
    const regime = rij.querySelector('[name="b2_blok_regime[]"]').value;
    if (!van || !tot) continue;
    if (tot < van) { const t = van; van = tot; tot = t; }
    for (let w = van; w <= tot; w++) { totaalWeken.add(w); weekTeller[w] = (weekTeller[w]||0) + 1; }
    let regimeTekst;
    if (regime === 'weekend') {
      const dagen = Array.from(rij.querySelectorAll('.b2-wkdag:checked')).map(c => B2_DAGNL[parseInt(c.value)]);
      regimeTekst = dagen.length ? 'alleen ' + dagen.join('/') : 'alleen weekend';
    } else {
      regimeTekst = 'hele weken (5 dagen)';
    }
    zinnen.push('Van <strong>' + (B2_WEEKLABEL[van]||('W'+van)) + '</strong> tot <strong>' + (B2_WEEKLABEL[tot]||('W'+tot)) + '</strong>: ' + regimeTekst + '.');
  }

  let html = zinnen.map(z => '<div>• ' + z + '</div>').join('');
  if (afwV.some((v,i) => v && afwT[i])) {
    const stukken = [];
    afwV.forEach((v,i) => { if (v && afwT[i]) stukken.push(b2DatumNL(v) + ' t/m ' + b2DatumNL(afwT[i])); });
    html += '<div style="margin-top:6px">• Afwezig: <strong>' + stukken.join(', ') + '</strong>.</div>';
  }
  if (vasteUit.length) {
    html += '<div style="margin-top:6px">• Voorkeur vrij: <strong>' + vasteUit.map(d => B2_DAGNL[d]).join(', ') + '</strong> <span style="color:var(--grijs)">(planner beslist)</span>.</div>';
  }

  // Losse dagen dat iemand niet kan / liever vrij wil
  const losRijen = Array.from(document.querySelectorAll('#b2LosseDagLijst .b2-losdag-rij'));
  const losStukken = [];
  losRijen.forEach(rij => {
    const datum = rij.querySelector('[name="b2_dag_datum[]"]')?.value;
    if (!datum) return;
    const soort = rij.querySelector('[name="b2_dag_soort[]"]')?.value;
    const reden = rij.querySelector('[name="b2_dag_reden[]"]')?.value;
    let stuk = b2DatumNL(datum);
    if (soort === 'voorkeur_vrij') stuk += ' (liever vrij)';
    if (reden) stuk += ' — ' + reden;
    losStukken.push(stuk);
  });
  if (losStukken.length) {
    html += '<div style="margin-top:6px">• Losse dagen niet beschikbaar: <strong>' + losStukken.join(', ') + '</strong>.</div>';
  }

  // Verzoeken om minder dagen in bepaalde weken
  const vzRijen = Array.from(document.querySelectorAll('#b2VerzoekLijst .b2-verzoek-rij'));
  const vzStukken = [];
  vzRijen.forEach(rij => {
    const van = rij.querySelector('[name="b2_vz_van[]"]')?.value;
    const tot = rij.querySelector('[name="b2_vz_tot[]"]')?.value;
    const dagen = rij.querySelector('[name="b2_vz_dagen[]"]')?.value;
    if (!van || !tot || !dagen) return;
    let stuk = (B2_WEEKLABEL[van]||('W'+van)) + ' t/m ' + (B2_WEEKLABEL[tot]||('W'+tot)) + ': max ' + dagen + ' ' + (dagen==1?'dag':'dagen');
    vzStukken.push(stuk);
  });
  if (vzStukken.length) {
    html += '<div style="margin-top:6px">• Verzoek minder dagen: <strong>' + vzStukken.join('; ') + '</strong> <span style="color:var(--grijs)">(bestuur beoordeelt)</span>.</div>';
  }

  const n = totaalWeken.size;
  if (teller) teller.textContent = '— ' + n + (n === 1 ? ' week' : ' weken');
  box.innerHTML = html || '<span style="color:var(--grijs)">Geen periode ingevuld.</span>';

  // Overlap-waarschuwing (punt 1)
  const overlap = Object.keys(weekTeller).filter(w => weekTeller[w] > 1).map(w => B2_WEEKLABEL[w] || ('W'+w));
  const owBox = document.getElementById('b2OverlapWaarschuwing');
  if (owBox) {
    if (overlap.length) {
      owBox.innerHTML = '⚠️ Deze week(en) zitten in meerdere periodes: <strong>' + overlap.join(', ') + '</strong>. We gebruiken dan de <strong>eerste</strong> periode. Klopt dat?';
      owBox.style.display = '';
    } else {
      owBox.style.display = 'none';
    }
  }
}

function b2DatumNL(iso) {
  if (!iso) return '';
  const mnd = ['jan','feb','mrt','apr','mei','jun','jul','aug','sep','okt','nov','dec'];
  const p = iso.split('-');
  return parseInt(p[2]) + ' ' + mnd[parseInt(p[1])-1];
}

function b2Valideer() {
  b2FoutWis();
  // Minimaal één blok met geldige weken
  const rijen = Array.from(document.querySelectorAll('#b2BlokLijst .b2-blok-rij'));
  if (!rijen.length) { b2Fout('Geef minimaal één periode op waarin je kunt werken.'); return false; }
  // Weekend-blok moet minstens één dag hebben
  for (const rij of rijen) {
    if (rij.querySelector('[name="b2_blok_regime[]"]').value === 'weekend') {
      if (!rij.querySelectorAll('.b2-wkdag:checked').length) {
        b2Fout('Kies bij "alleen weekenden" minstens één dag (vrijdag, zaterdag of zondag).');
        return false;
      }
    }
  }
  // Afwezigheid: van mag niet na tot
  const afwV = Array.from(document.querySelectorAll('[name="b2_afw_van[]"]')).map(i => i.value);
  const afwT = Array.from(document.querySelectorAll('[name="b2_afw_tot[]"]')).map(i => i.value);
  for (let i = 0; i < afwV.length; i++) {
    if (afwV[i] && afwT[i] && afwV[i] > afwT[i]) { b2Fout('Bij een afwezigheid ligt de einddatum vóór de begindatum. Controleer je vakantieperiode.'); return false; }
    if ((afwV[i] && !afwT[i]) || (!afwV[i] && afwT[i])) { b2Fout('Vul bij een afwezigheid zowel een begin- als einddatum in.'); return false; }
  }
  // Toelichting verplicht als er een vaste voorkeur-dag is gekozen
  const heeftVaste = document.querySelectorAll('[name="b2_vaste_uit[]"]:checked').length > 0;
  if (heeftVaste) {
    const toel = (document.getElementById('b2VasteToelichting')?.value || '').trim();
    if (!toel) { b2Fout('Je hebt een vaste vrije dag als voorkeur aangegeven. Licht kort toe waarom, zodat we het goed kunnen meewegen.'); document.getElementById('b2VasteToelichting')?.focus(); return false; }
  }
  return true;
}
