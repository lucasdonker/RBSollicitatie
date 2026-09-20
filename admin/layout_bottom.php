</div><!-- /page-body -->
  </div><!-- /main-content -->
</div><!-- /admin-layout -->

<!-- Detail paneel (globaal) -->
<div class="paneel-overlay" id="paneelOverlay" onclick="sluitPaneel()"></div>
<div class="detail-paneel" id="detailPaneel"><div id="paneelInhoud"></div></div>

<script>
const APP_URL = '<?= APP_URL ?>';
const CSRF    = '<?= csrf_token() ?>';

/* ── Dark / light mode ──────────────────────────────────────── */
function toggleTheme() {
  const huidig = document.documentElement.getAttribute('data-theme');
  const isDark = huidig !== 'dark';
  document.documentElement.setAttribute('data-theme', isDark ? 'dark' : '');
  localStorage.setItem('paal13-theme', isDark ? 'dark' : 'light');
  updateThemeSwitch(isDark);
}

function updateThemeSwitch(isDark) {
  const dot = document.getElementById('themeDot');
  const sw  = document.getElementById('themeSwitch');
  if (!dot || !sw) return;
  // Profiel-toggle is 48x26 met dot 18px: schuift 22px naar rechts
  dot.style.transform = isDark ? 'translateX(22px)' : 'translateX(0)';
  sw.style.background = isDark ? 'var(--oranje)' : 'var(--grijs-bg)';
}

// Initialiseer switch state bij laden
document.addEventListener('DOMContentLoaded', () => {
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  updateThemeSwitch(isDark);
});

/* Sidebar JS staat in layout_top.php */

/* ── Detail paneel ──────────────────────────────────────────── */
let huidigePaneelId = null;

function openPaneel(id) {
  huidigePaneelId = id;
  document.getElementById('detailPaneel').classList.add('open');
  document.getElementById('paneelOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
  laadPaneel(id);
}

function laadPaneel(id) {
  document.getElementById('paneelInhoud').innerHTML =
    '<div class="paneel-header"><div style="color:rgba(255,255,255,.4);padding:20px;font-size:13px">Laden…</div></div>';
  fetch(APP_URL + '/admin/api_paneel.php?id=' + id)
    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
    .then(html => {
      document.getElementById('paneelInhoud').innerHTML = html;
      // Inline <script> tags via innerHTML worden NIET uitgevoerd — expliciet triggeren:
      if (typeof laadNotities === 'function') laadNotities(id);
      if (typeof laadTimeline === 'function') laadTimeline(id);
    })
    .catch(e => {
      document.getElementById('paneelInhoud').innerHTML =
        '<div class="paneel-header"><div style="color:#fc8181;padding:20px;font-size:13px">Fout: ' + e.message + '</div></div>';
    });
}

function sluitPaneel() {
  document.getElementById('detailPaneel').classList.remove('open');
  document.getElementById('paneelOverlay').classList.remove('open');
  document.body.style.overflow = '';
  huidigePaneelId = null;
  const url = new URL(window.location);
  url.searchParams.delete('panel');
  history.replaceState({}, '', url);
}

/* ── Fase wijzigen via AJAX ─────────────────────────────────── */
function fasWijzigen(solId, naar, label) {
  // Waarschuw expliciet als deze fase een uitnodigingsmail triggert
  let bevestiging = 'Fase wijzigen naar: ' + label + '?';
  if (naar === 'uitgenodigd_selectie' || naar === 'uitgenodigd_tw') {
    bevestiging += '\n\n⚠ Er wordt automatisch een uitnodigingsmail verstuurd.';
  }
  if (!confirm(bevestiging)) return;
  fetch(APP_URL + '/admin/api_fase.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ sol_id: solId, naar, _csrf: CSRF })
  })
  .then(r => r.json())
  .then(data => {
    if (!data.ok) { alert(data.fout || 'Fout bij opslaan.'); return; }
    laadPaneel(solId);
    updateTabelRij(solId, data.fase, data.label, data.kleur, data.snelle_actie);
    // Toon mail-resultaat indien aanwezig
    if (data.mail_melding) {
      toastMelding(data.mail_melding, data.mail_status === 'verstuurd' ? 'succes' : (data.mail_status === 'fout' || data.mail_status === 'geen_email' ? 'fout' : 'info'));
    }
  })
  .catch(() => alert('Verbindingsfout — probeer opnieuw.'));
}

/* ── Mini toast melding ─────────────────────────────────────── */
function toastMelding(tekst, type) {
  type = type || 'info';
  const kleur = type === 'succes' ? '#10b981' : (type === 'fout' ? '#ef4444' : '#3b82f6');
  const d = document.createElement('div');
  d.style.cssText = 'position:fixed;top:20px;right:20px;background:' + kleur + ';color:#fff;padding:12px 18px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.18);font-size:14px;font-weight:500;z-index:99999;max-width:380px';
  d.textContent = tekst;
  document.body.appendChild(d);
  setTimeout(() => { d.style.transition = 'opacity .4s'; d.style.opacity = '0'; }, 4000);
  setTimeout(() => d.remove(), 4500);
}

function updateTabelRij(solId, fase, label, kleur, snelleActie) {
  const rij = document.querySelector('.soll-rij[data-id="' + solId + '"]');
  if (!rij) return;
  rij.dataset.fase = fase;
  const badge = rij.querySelector('.badge');
  if (badge) { badge.className = 'badge badge-' + kleur; badge.textContent = label; }
  const actieKolom = rij.querySelector('.actie-kolom');
  if (actieKolom) actieKolom.innerHTML = bouwActieKnoppen(solId, fase);
}

function bouwActieKnoppen(solId, fase) {
  let html = '';
  if (fase === 'aangemeld')
    html += `<button type="button" class="btn btn-groen btn-sm" onclick="enkeleActie(${solId},'uitnodigen_selectie')">Uitnodigen SD</button> `;
  else if (fase === 'selectiedag_geweest')
    html += `<button type="button" class="btn btn-primary btn-sm" onclick="enkeleActie(${solId},'uitnodigen_tw')">Uitnodigen TW</button> `;
  html += `<button type="button" class="btn btn-outline btn-sm" onclick="openPaneel(${solId})" title="Open dossier">···</button>`;
  return html;
}

/* ── Uitnodigen vanuit paneel ───────────────────────────────── */
function kanaalVoorkeurZet(pid, keuze, el) {
  const fd = new FormData(); fd.append('_csrf', CSRF); fd.append('persoon_id', pid); fd.append('kanaal_voorkeur', keuze);
  fetch(APP_URL + '/admin/api_kanaal.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(j => toastMelding(j.ok ? 'Kanaalvoorkeur opgeslagen.' : ('Mislukt: ' + (j.fout || '')), j.ok ? 'succes' : 'fout'))
    .catch(() => toastMelding('Opslaan mislukt.', 'fout'));
}

function uitnodigVanUitPaneel(solId, actie) {
  const labels = { uitnodigen_selectie: 'uitnodigen voor de selectiedag', uitnodigen_tw: 'uitnodigen voor het trainingsweekend' };
  if (!confirm('Deze persoon ' + (labels[actie] || actie) + '?')) return;
  const cb = document.querySelector('.soll-rij[data-id="' + solId + '"] .soll-cb');
  if (cb) { document.querySelectorAll('.soll-cb').forEach(c => c.checked = false); cb.checked = true; }
  document.getElementById('bulkActieVeld').value = actie;
  document.getElementById('bulkForm').submit();
}

/* ── Verwijder operaties ────────────────────────────────────── */
function verwijderActie(solId, actie, bevestiging) {
  if (!confirm(bevestiging)) return;
  const knop = event.target;
  knop.disabled = true; knop.textContent = 'Bezig…';
  fetch(APP_URL + '/admin/api_verwijder.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ sol_id: solId, actie, _csrf: CSRF })
  })
  .then(r => r.json())
  .then(data => {
    if (!data.ok) { alert(data.fout || 'Fout.'); knop.disabled = false; knop.textContent = '🗑️'; return; }
    if (data.verwijderd) {
      sluitPaneel();
      const rij = document.querySelector('.soll-rij[data-id="' + solId + '"]');
      if (rij) { rij.style.transition = 'opacity .3s'; rij.style.opacity = '0'; setTimeout(() => rij.remove(), 300); }
    } else {
      laadPaneel(solId);
    }
  })
  .catch(() => { alert('Verbindingsfout.'); knop.disabled = false; });
}

/* ── AI Analyse (via wachtrij) ─────────────────────────────── */
/* ── Competenties (Authentik) ───────────────────────────────── */
function competentieVerversen(solId) {
  const status = document.getElementById('compStatus-' + solId);
  if (status) status.textContent = '🔄 Ophalen uit Authentik…';
  fetch(APP_URL + '/admin/api_competentie.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ actie: 'ververs', sol_id: solId, _csrf: CSRF })
  })
  .then(r => r.json())
  .then(data => {
    if (!data.ok) { if (status) status.innerHTML = '<span style="color:var(--rood)">' + (data.fout || data.melding || 'Fout') + '</span>'; return; }
    // Herlaad het paneel om de nieuwe chips te tonen
    if (typeof openPaneel === 'function') openPaneel(solId);
  })
  .catch(() => { if (status) status.textContent = 'Verbindingsfout'; });
}

function competentieKoppelen(solId) {
  const username = document.getElementById('compUsername-' + solId).value.trim();
  if (!username) { alert('Geef een gebruikersnaam op.'); return; }
  const status = document.getElementById('compStatus-' + solId);
  if (status) status.textContent = '🔄 Koppelen en synchroniseren…';
  fetch(APP_URL + '/admin/api_competentie.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ actie: 'koppel', sol_id: solId, username, _csrf: CSRF })
  })
  .then(r => r.json())
  .then(data => {
    if (!data.ok) { if (status) status.innerHTML = '<span style="color:var(--rood)">' + (data.fout || data.melding || 'Fout') + '</span>'; return; }
    if (typeof openPaneel === 'function') openPaneel(solId);
  })
  .catch(() => { if (status) status.textContent = 'Verbindingsfout'; });
}

/* ── Afwijkende norm (bestuursbesluit) ──────────────────────── */
function normOpslaan(solId) {
  const dagen = document.getElementById('normDagen-' + solId).value;
  const notitie = document.getElementById('normNotitie-' + solId).value;
  fetch(APP_URL + '/admin/api_norm.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ sol_id: solId, norm_dagen: dagen, norm_notitie: notitie, _csrf: CSRF })
  })
  .then(r => r.json())
  .then(data => {
    if (!data.ok) { alert(data.fout || 'Fout'); return; }
    const lbl = document.getElementById('normLabel-' + solId);
    if (lbl) lbl.textContent = data.norm_dagen ? data.norm_dagen + ' dagen/week (afwijkend)' : '5 dagen/week (standaard)';
    document.getElementById('normEdit-' + solId).style.display = 'none';
  })
  .catch(() => alert('Verbindingsfout'));
}

/* ── Verzoek afwijkend aantal dagen (bestuur beslist) ───────── */
function verzoekBeslis(verzoekId, besluit, solId) {
  if (besluit === 'afwijzen' && !confirm('Verzoek afwijzen?')) return;
  fetch(APP_URL + '/admin/api_verzoek.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ verzoek_id: verzoekId, besluit: besluit, _csrf: CSRF })
  })
  .then(r => r.json())
  .then(data => {
    if (!data.ok) { alert(data.fout || 'Fout'); return; }
    // Ververs het paneel zodat de nieuwe status + herberekende norm zichtbaar wordt
    if (typeof paneelHerlaad === 'function') paneelHerlaad(solId);
    else location.reload();
  })
  .catch(() => alert('Verbindingsfout'));
}

/* ── Verhinderingen (beschikbaarheid dagen) ─────────────────── */
/* ── Bestuurs-correctie per week (herontworpen verhindering) ── */
function correctieOpen(solId, week) {
  // Bouw een kleine inline modal
  const bestaand = document.getElementById('corrModal');
  if (bestaand) bestaand.remove();
  const overlay = document.createElement('div');
  overlay.id = 'corrModal';
  overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;display:flex;align-items:center;justify-content:center;padding:16px';
  overlay.innerHTML =
    '<div style="background:var(--bg-card);border-radius:12px;padding:20px;max-width:420px;width:100%;box-shadow:0 10px 40px rgba(0,0,0,.3)">' +
      '<h3 style="margin:0 0 4px;font-size:16px">Correctie voor week ' + week + '</h3>' +
      '<p style="font-size:12.5px;color:var(--tekst-zacht);margin:0 0 14px">Overschrijft wat de sollicitant zelf doorgaf. Gebruik dit bijvoorbeeld na een telefoontje.</p>' +
      '<label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">Wat is er aan de hand?</label>' +
      '<div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">' +
        '<label style="flex:1;min-width:150px;display:flex;align-items:center;gap:6px;padding:8px 12px;border:1px solid var(--rand);border-radius:6px;cursor:pointer;font-size:13px"><input type="radio" name="corrSoort" value="niet" checked onchange="corrSoortWissel()"> Kan deze week niet</label>' +
        '<label style="flex:1;min-width:150px;display:flex;align-items:center;gap:6px;padding:8px 12px;border:1px solid var(--rand);border-radius:6px;cursor:pointer;font-size:13px"><input type="radio" name="corrSoort" value="minder" onchange="corrSoortWissel()"> Alleen bepaalde dagen</label>' +
      '</div>' +
      '<div id="corrDagen" style="display:none;margin-bottom:12px">' +
        '<label style="display:block;font-size:12.5px;color:var(--tekst-zacht);margin-bottom:6px">Welke dagen kan hij/zij dan wél?</label>' +
        '<div style="display:flex;gap:4px;flex-wrap:wrap">' +
          [[1,'ma'],[2,'di'],[3,'wo'],[4,'do'],[5,'vr'],[6,'za'],[7,'zo']].map(d =>
            '<label style="display:inline-flex;align-items:center;gap:3px;padding:5px 10px;border:1px solid var(--rand);border-radius:5px;cursor:pointer;font-size:12.5px"><input type="checkbox" class="corrDag" value="'+d[0]+'"> '+d[1]+'</label>'
          ).join('') +
        '</div>' +
      '</div>' +
      '<label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">Reden <span style="color:var(--rood)">*</span></label>' +
      '<input type="text" id="corrReden" placeholder="bijv. belde: die week op vakantie" style="width:100%;padding:9px;border:1px solid var(--rand);border-radius:6px;margin-bottom:16px;font-size:13px">' +
      '<div style="display:flex;gap:8px;justify-content:flex-end">' +
        '<button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById(\'corrModal\').remove()">Annuleren</button>' +
        '<button type="button" class="btn btn-primary btn-sm" onclick="correctieOpslaan(' + solId + ',' + week + ')">Opslaan</button>' +
      '</div>' +
    '</div>';
  document.body.appendChild(overlay);
  overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });
}

function corrSoortWissel() {
  const minder = document.querySelector('input[name="corrSoort"]:checked')?.value === 'minder';
  document.getElementById('corrDagen').style.display = minder ? '' : 'none';
}

function correctieOpslaan(solId, week) {
  const soort = document.querySelector('input[name="corrSoort"]:checked')?.value || 'niet';
  const reden = document.getElementById('corrReden').value.trim();
  if (!reden) { alert('Geef een reden op.'); return; }
  const dagen = Array.from(document.querySelectorAll('.corrDag:checked')).map(c => c.value);
  if (soort === 'minder' && !dagen.length) { alert('Kies minstens één dag.'); return; }
  const body = new URLSearchParams({ sol_id: solId, week, soort, reden, _csrf: CSRF });
  dagen.forEach(d => body.append('dagen[]', d));
  fetch(APP_URL + '/admin/api_correctie.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body })
    .then(r => r.json())
    .then(data => { if (!data.ok) { alert(data.fout || 'Fout'); return; } location.reload(); })
    .catch(() => alert('Verbindingsfout'));
}

function correctieWis(solId, week) {
  if (!confirm('Correctie voor week ' + week + ' herstellen?')) return;
  fetch(APP_URL + '/admin/api_correctie.php', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ sol_id: solId, week, soort: '', _csrf: CSRF })
  })
  .then(r => r.json())
  .then(data => { if (!data.ok) { alert(data.fout || 'Fout'); return; } location.reload(); })
  .catch(() => alert('Verbindingsfout'));
}

/* ── Ondertekening ──────────────────────────────────────────── */
function kopieerOndertekenlink(url) {
  navigator.clipboard.writeText(url).then(() => toastMelding('Link gekopieerd — plak in mail', 'succes'));
}

function startOndertekening(solId, type) {
  const labels = {
    'gezondheid_ouder':  'Gezondheidsverklaring t/m 17 (ouder ondertekent)',
    'gezondheid_18plus': 'Gezondheidsverklaring 18+ (sollicitant zelf)',
    'intake':            'Intakeformulier selectiedag',
    'intake_ouder':      'Intakeformulier selectiedag (ouder ondertekent)',
  };
  const isOuder = (type === 'gezondheid_ouder' || type === 'intake_ouder');
  let email = '', naam = '';
  if (isOuder) {
    email = prompt('E-mailadres van ouder/verzorger:');
    if (!email) return;
    naam = prompt('Naam van ouder/verzorger (optioneel):') || '';
  } else {
    if (!confirm(labels[type] + ' aanmaken en per mail versturen naar de sollicitant?')) return;
  }
  fetch(APP_URL + '/admin/api_ondertekening.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      sol_id: solId, type, naar_email: email, naar_naam: naam, _csrf: CSRF
    })
  })
  .then(r => r.json())
  .then(data => {
    if (!data.ok) { alert(data.fout || 'Fout'); return; }
    toastMelding('Uitnodiging verstuurd', 'succes');
    laadPaneel(solId);
  })
  .catch(() => alert('Verbindingsfout'));
}

/* ── Notities beheer ────────────────────────────────────────── */
function laadNotities(solId) {
  fetch(APP_URL + '/admin/api_notitie.php?actie=lijst&sol_id=' + solId)
    .then(r => r.json())
    .then(data => {
      const box = document.getElementById('notities-lijst-' + solId);
      if (!box) return;
      if (!data.ok) { box.innerHTML = '<div style="color:var(--rood);font-size:12px">Fout: ' + data.fout + '</div>'; return; }
      const mijnId = data.huidige_id;
      if (!data.notities.length) {
        box.innerHTML = '<div style="font-size:12px;color:var(--grijs);padding:6px 0;font-style:italic">Nog geen notities.</div>';
        return;
      }
      box.innerHTML = data.notities.map(n => {
        const bewerkbaar = (n.beheerder_id == mijnId);
        const acties = bewerkbaar
          ? `<div style="display:flex;gap:4px;margin-top:4px">
               <button class="btn btn-outline btn-sm" style="font-size:10px;padding:2px 8px" onclick="notitieBewerken(${n.id}, ${solId})">✎</button>
               <button class="btn btn-outline btn-sm" style="font-size:10px;padding:2px 8px;color:var(--rood)" onclick="notitieVerwijderen(${n.id}, ${solId})">🗑</button>
             </div>`
          : '';
        const tekstEscaped = String(n.tekst).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
        return `
          <div class="notitie-item" data-id="${n.id}" style="border-left:3px solid var(--oranje);background:var(--grijs-bg);padding:8px 10px;border-radius:4px;margin-bottom:6px;font-size:12.5px">
            <div class="notitie-tekst">${tekstEscaped}</div>
            <div style="font-size:10.5px;color:var(--grijs);margin-top:4px">
              ${n.beheerder_naam || 'Onbekend'} · ${formatDatum(n.aangemaakt_op)}
              ${n.gewijzigd_op !== n.aangemaakt_op ? ' <em>(bewerkt)</em>' : ''}
            </div>
            ${acties}
          </div>`;
      }).join('');
    })
    .catch(e => console.error(e));
}

function notitieToevoegen(solId) {
  const veld = document.getElementById('nieuwe-notitie-' + solId);
  const tekst = veld.value.trim();
  if (!tekst) return;
  fetch(APP_URL + '/admin/api_notitie.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ actie: 'toevoegen', sol_id: solId, tekst, _csrf: CSRF })
  })
  .then(r => r.json())
  .then(data => {
    if (!data.ok) { alert(data.fout || 'Fout'); return; }
    veld.value = '';
    laadNotities(solId);
    laadTimeline(solId);
  })
  .catch(() => alert('Verbindingsfout'));
}

function notitieBewerken(id, solId) {
  const item = document.querySelector('.notitie-item[data-id="' + id + '"]');
  if (!item) return;
  const huidig = item.querySelector('.notitie-tekst').innerText;
  const nieuwe = prompt('Notitie bewerken:', huidig);
  if (nieuwe === null || nieuwe.trim() === '') return;
  fetch(APP_URL + '/admin/api_notitie.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ actie: 'bewerken', id, tekst: nieuwe.trim(), _csrf: CSRF })
  })
  .then(r => r.json())
  .then(data => { if (!data.ok) alert(data.fout); laadNotities(solId); });
}

function notitieVerwijderen(id, solId) {
  if (!confirm('Notitie verwijderen?')) return;
  fetch(APP_URL + '/admin/api_notitie.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ actie: 'verwijderen', id, _csrf: CSRF })
  })
  .then(r => r.json())
  .then(data => { if (!data.ok) alert(data.fout); laadNotities(solId); laadTimeline(solId); });
}

/* ── Timeline ───────────────────────────────────────────────── */
function laadTimeline(solId) {
  fetch(APP_URL + '/admin/api_timeline.php?sol_id=' + solId)
    .then(r => r.json())
    .then(data => {
      const box = document.getElementById('timeline-' + solId);
      if (!box) return;
      if (!data.ok || !data.events.length) {
        box.innerHTML = '<div style="font-size:12px;color:var(--grijs);padding:6px 0;font-style:italic">Nog geen activiteit.</div>';
        return;
      }
      box.innerHTML = '<div class="timeline-lijn" style="position:relative;padding-left:14px;border-left:2px solid var(--rand)">'
        + data.events.map(e => {
            const inhoud = String(e.inhoud || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            return `
              <div class="timeline-event" style="position:relative;margin-bottom:12px;padding-left:10px">
                <span style="position:absolute;left:-22px;top:0;font-size:13px;background:var(--bg-card);padding:0 2px">${e.icon}</span>
                <div style="font-size:12.5px;font-weight:500">${e.titel}</div>
                <div style="font-size:11.5px;color:var(--tekst-zacht);margin-top:1px">${inhoud}</div>
                <div style="font-size:10.5px;color:var(--grijs);margin-top:2px">${formatDatum(e.tijd)} · ${e.door}</div>
              </div>`;
          }).join('') + '</div>';
    })
    .catch(e => console.error(e));
}

/* Format DATETIME → NL leesbaar */
function formatDatum(iso) {
  if (!iso) return '';
  const d = new Date(iso.replace(' ', 'T'));
  if (isNaN(d)) return iso;
  const nu = new Date();
  const diffMs = nu - d;
  const diffMin = Math.floor(diffMs / 60000);
  if (diffMin < 1) return 'zojuist';
  if (diffMin < 60) return diffMin + ' min geleden';
  const diffH = Math.floor(diffMin / 60);
  if (diffH < 24) return diffH + ' uur geleden';
  const diffD = Math.floor(diffH / 24);
  if (diffD < 7) return diffD + ' dag' + (diffD>1?'en':'') + ' geleden';
  return d.toLocaleDateString('nl-NL', {day:'2-digit',month:'short',year:'numeric'}) + ' ' + d.toLocaleTimeString('nl-NL',{hour:'2-digit',minute:'2-digit'});
}

function genereerAnalyse(solId) {
  const loadEl = document.getElementById('ai-loading-' + solId);
  const knop   = document.querySelector('[onclick="genereerAnalyse(' + solId + ')"]');
  if (loadEl) { loadEl.style.display=''; loadEl.textContent='⏳ Toevoegen aan wachtrij...'; }
  if (knop)   knop.disabled = true;

  fetch(APP_URL + '/admin/api_ai_analyse.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ sol_id: solId, _csrf: CSRF })
  })
  .then(r => r.json())
  .then(data => {
    if (!data.ok) {
      if (loadEl) loadEl.style.display = 'none';
      if (knop)   knop.disabled = false;
      alert(data.fout || 'Toevoegen mislukt.');
      return;
    }
    const msgs = {
      'al_klaar':       '✓ Analyse al beschikbaar — ververs de pagina.',
      'al_in_wachtrij': '📋 Al in wachtrij. Verwerking binnen 5 min.',
      'toegevoegd':     '📋 In wachtrij geplaatst. Verwerking binnen 5 min via cron.',
    };
    if (loadEl) { loadEl.style.display=''; loadEl.textContent = msgs[data.status] || data.bericht; }
    if (knop)   knop.disabled = false;
    if (data.status === 'al_klaar' && typeof laadPaneel === 'function') laadPaneel(solId);
  })
  .catch(() => {
    if (loadEl) loadEl.style.display = 'none';
    if (knop)   knop.disabled = false;
    alert('Verbindingsfout.');
  });
}

/* ── Mobile sidebar toggle ─────────────────────────────────── */
window.toggleSidebar = function() {
  const sb = document.getElementById('sidebar');
  const ov = document.getElementById('sidebarOverlay');
  if (!sb) return;
  sb.classList.toggle('open');
  if (ov) ov.classList.toggle('zichtbaar');
};
window.sluitSidebar = function() {
  document.getElementById('sidebar')?.classList.remove('open');
  document.getElementById('sidebarOverlay')?.classList.remove('zichtbaar');
};

/* ── Foto vergroten modaal ─────────────────────────────────── */
window.fotoVergroten = function(url, naam) {
  let m = document.getElementById('fotoModal');
  if (!m) {
    m = document.createElement('div');
    m.id = 'fotoModal';
    m.onclick = () => m.style.display = 'none';
    m.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9999;align-items:center;justify-content:center;padding:40px;cursor:zoom-out';
    m.innerHTML = '<div style="max-width:90vw;max-height:90vh;text-align:center">' +
      '<img id="fotoModalImg" src="" alt="" style="max-width:100%;max-height:80vh;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,.5)">' +
      '<div id="fotoModalCaption" style="color:#fff;margin-top:12px;font-size:14px"></div>' +
      '<div style="color:rgba(255,255,255,.5);font-size:11px;margin-top:4px">Klik om te sluiten</div></div>';
    document.body.appendChild(m);
  }
  document.getElementById('fotoModalImg').src = url;
  document.getElementById('fotoModalCaption').textContent = naam || '';
  m.style.display = 'flex';
};

/* ESC sluit modaal */
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    const m = document.getElementById('fotoModal');
    if (m && m.style.display === 'flex') m.style.display = 'none';
  }
});

/* ── Tooltip systeem ──────────────────────────────────────────
   Gebruik: <span class="tip" data-tip="Uitleg hier">ℹ</span> */
document.addEventListener('DOMContentLoaded', () => {
  const tooltipEl = document.createElement('div');
  tooltipEl.id = 'p13-tooltip';
  tooltipEl.style.cssText = 'position:absolute;background:var(--navy);color:#fff;padding:8px 12px;border-radius:6px;font-size:12px;line-height:1.5;max-width:260px;z-index:10000;pointer-events:none;opacity:0;transition:opacity .15s;box-shadow:0 4px 12px rgba(0,0,0,.3)';
  document.body.appendChild(tooltipEl);
  let huidige = null;

  function toon(el) {
    const txt = el.getAttribute('data-tip');
    if (!txt) return;
    tooltipEl.textContent = txt;
    const r = el.getBoundingClientRect();
    let top  = r.bottom + window.scrollY + 6;
    let left = r.left + window.scrollX + (r.width/2) - 130;
    if (left < 8) left = 8;
    if (left + 260 > window.innerWidth) left = window.innerWidth - 268;
    tooltipEl.style.top  = top + 'px';
    tooltipEl.style.left = left + 'px';
    tooltipEl.style.opacity = '1';
  }
  function verberg() { tooltipEl.style.opacity = '0'; }

  document.body.addEventListener('mouseover', e => {
    const t = e.target.closest('.tip,[data-tip]');
    if (t && t !== huidige) { huidige = t; toon(t); }
  });
  document.body.addEventListener('mouseout', e => {
    if (e.target.closest('.tip,[data-tip]')) { verberg(); huidige = null; }
  });
  // Mobiel: tap toggelt
  document.body.addEventListener('click', e => {
    const t = e.target.closest('.tip,[data-tip]');
    if (t) {
      if (tooltipEl.style.opacity === '1' && huidige === t) verberg();
      else { huidige = t; toon(t); }
    }
  });
});

/* ── Flash berichten ────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.flash').forEach(el => {
    setTimeout(() => {
      el.style.transition = 'opacity .4s';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 400);
    }, 5000);
  });
  const panelId = new URLSearchParams(window.location.search).get('panel');
  if (panelId) openPaneel(parseInt(panelId));
});
</script>
<script src="<?= APP_URL ?>/assets/js/app.js?v=<?= @filemtime(__DIR__.'/../assets/js/app.js') ?>"></script>
<script src="<?= APP_URL ?>/assets/js/editor.js?v=<?= @filemtime(__DIR__.'/../assets/js/editor.js') ?>"></script>
<script src="<?= APP_URL ?>/assets/js/beheer.js?v=<?= @filemtime(__DIR__.'/../assets/js/beheer.js') ?>"></script>
</body>
</html>