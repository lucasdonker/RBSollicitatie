    </main>
  </div><!-- /bh-hoofd -->
</div><!-- /bh-kader -->

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

/* De stand staat in aria-checked, en de CSS tekent hem (.bh-schakelaar). Zo weet een
   schermlezer het ook — die leest geen inline transform. */
function updateThemeSwitch(isDark) {
  const sw = document.getElementById('themeSwitch');
  if (sw) sw.setAttribute('aria-checked', isDark ? 'true' : 'false');
}

// Initialiseer switch state bij laden
document.addEventListener('DOMContentLoaded', () => {
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  updateThemeSwitch(isDark);
});

/* Sidebar JS staat in layout_top.php */

/* ── Detail paneel ──────────────────────────────────────────────
   Het dossier is sinds de herbouw een eigen pagina (admin/sollicitant.php): deelbaar,
   bookmarkbaar, de terugknop werkt. openPaneel() stuurt daarom door in plaats van een
   paneel te openen. Het blijft bestaan zolang schermen die nog niet om zijn het
   aanroepen; weg zodra `grep -rn "openPaneel" admin/` leeg is. */
let huidigePaneelId = null;

function openPaneel(id) {
  window.location.href = APP_URL + '/admin/sollicitant.php?id=' + encodeURIComponent(id);
}

/* laadPaneel() en sluitPaneel() zijn weg met api_paneel.php: het dossier is een pagina,
   dus er valt niets meer in of uit te schuiven. */

/* ── Fase wijzigen via AJAX ─────────────────────────────────────
   De bevestiging komt uit bhBevestig(): die haalt de tekst bij de server, zodat wat
   er staat uit dezelfde bron komt als wat er gebeurt. Het oude confirm() somde zelf
   op welke fases mail sturen — een tweede lijstje naast fase_meldingstypen(). */
function fasWijzigen(solId, naar, label) {
  bhBevestig({
    titel: 'Fase wijzigen naar "' + label + '"?',
    tekst: 'Hoort bij deze overgang een bericht, dan gaat dat automatisch de deur uit.',
    knop:  'Ja, wijzigen',
    // Heb je al gebeld, of corrigeer je een fase die verkeerd stond? Dan hoeft er
    // geen standaardbericht uit. Tot nu toe kon dat alleen door het hele kanaal
    // tijdelijk uit te zetten bij Instellingen — en dat raakte iedereen.
    vink: { label: 'Zonder bericht versturen',
            uitleg: 'De fase verandert wel; er gaat alleen geen automatisch bericht naar deze persoon.' }
  }).then(function (ja) {
    if (!ja) return;
    var stil = (typeof ja === 'object' && ja.vink) ? '1' : '';
    // Meteen zichtbaar dat er iets loopt: anders gebeurt er tot het herladen niets.
    var klaar = window.P13Actie ? window.P13Actie.bezig(null, null) : function () {};
    fetch(APP_URL + '/admin/api_fase.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ sol_id: solId, naar: naar, stil: stil, _csrf: CSRF })
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (!data.ok) { klaar(); bhToast(data.fout || 'Opslaan mislukt.', 'fout'); return; }
      /* De pagina toont de fase op vier plekken; opnieuw laden is eerlijker dan die met
         de hand bijwerken (zo stond de rij-rendering eerder twee keer in de code).
         De melding over het bericht zet api_fase.php als flash, dus die staat er ná
         het herladen nog — vandaar dat hier geen pauze meer inzit. */
      location.reload();
    })
    .catch(function () { klaar(); bhToast('Verbindingsfout — probeer opnieuw.', 'fout'); });
  });
}

/* toastMelding, updateTabelRij en bouwActieKnoppen zijn weg. De eerste zit nu als
   bhToast in beheer.js; de andere twee bouwden een tabelrij na in JavaScript, naast
   dezelfde rij in PHP. Een herlaadactie houdt die twee per definitie gelijk. */

function kanaalVoorkeurZet(pid, keuze, el) {
  /* De keuze is al zichtbaar gezet door de browser; we bevestigen alleen of het
     ook echt is opgeslagen. Tijdens het verzoek staat het bedieningselement in
     laadstand, zodat je niet drie keer achter elkaar wisselt. */
  const klaar = window.P13Actie ? window.P13Actie.bezig(null, el) : function () {};
  const fd = new FormData(); fd.append('_csrf', CSRF); fd.append('persoon_id', pid); fd.append('kanaal_voorkeur', keuze);
  fetch(APP_URL + '/admin/api_kanaal.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(j => { klaar(); bhToast(j.ok ? 'Kanaalvoorkeur opgeslagen.' : ('Mislukt: ' + (j.fout || '')), j.ok ? 'succes' : 'fout'); })
    .catch(() => { klaar(); bhToast('Opslaan mislukt.', 'fout'); });
}

/* uitnodigVanUitPaneel() is weg: die vinkte een checkbox aan in de lijst ónder het
   paneel en submitte dat formulier. Het dossier heeft nu een eigen knop met een
   gewone POST naar zichzelf. */

/* ── Verwijderen ────────────────────────────────────────────────
   Wat er precies meegaat staat in het rode blok op de dossierpagina; de dialoog
   herhaalt de kern en vraagt om een bewuste tweede klik. */
function verwijderActie(solId, actie, bevestiging) {
  var knop = event && event.target ? event.target : null;
  var oud = knop ? knop.textContent : '';

  function stuur(ookLoondocs) {
    if (knop) { knop.disabled = true; knop.textContent = 'Bezig…'; }
    var body = { sol_id: solId, actie: actie, _csrf: CSRF };
    if (ookLoondocs) body.ook_loondocs = '1';
    fetch(APP_URL + '/admin/api_verwijder.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams(body)
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (knop) { knop.disabled = false; knop.textContent = oud; }
      if (!data.ok) {
        /* Verwerkt loondossier: de server vraagt om een expliciete tweede keuze, omdat
           de loonheffing en de ID-kopie onder de bewaarplicht van vijf jaar vallen.
           Eerder toonde het scherm alleen de foutmelding en kwam je niet verder — de
           parameter die de server verwacht werd nergens meegestuurd. */
        if (data.loondossier) { vraagLoondossier(data.fout); return; }
        bhToast(data.fout || 'Verwijderen mislukt.', 'fout');
        return;
      }
      if (data.verwijderd) window.location.href = APP_URL + '/admin/sollicitanten.php';
      else location.reload();
    })
    .catch(function () {
      bhToast('Verbindingsfout — probeer opnieuw.', 'fout');
      if (knop) { knop.disabled = false; knop.textContent = oud; }
    });
  }

  function vraagLoondossier(reden) {
    bhBevestig({
      titel: 'Er ligt een verwerkt loondossier',
      tekst: (reden || '') + ' Het loonheffingsformulier en de kopie van het identiteitsbewijs '
           + 'moeten vijf jaar bewaard blijven na afloop van het dienstjaar. Is het dienstverband '
           + 'nooit doorgegaan, dan vervalt die plicht en mag alles weg.',
      vink: { label: 'Het dienstverband is niet doorgegaan',
              uitleg: 'Vink dit alleen aan als deze persoon nooit heeft gewerkt. De stukken worden dan ook vernietigd.' },
      knop: 'Verwijderen', gevaar: true
    }).then(function (ja) {
      if (!ja) return;
      if (!ja.vink) { bhToast('Niets verwijderd — zonder die bevestiging blijft het loondossier staan.', 'info'); return; }
      stuur(true);
    });
  }

  bhBevestig({
    titel: 'Dit dossier verwijderen?',
    tekst: bevestiging || 'Het formulier, de beschikbaarheid, de notities en de ondertekende '
         + 'documenten gaan mee. Dit kun je niet terugdraaien.',
    knop: 'Ja, verwijderen', gevaar: true
  }).then(function (ja) { if (ja) stuur(false); });
}

/* ── AI Analyse (via wachtrij) ─────────────────────────────── */
/* ── Competenties (Authentik) ───────────────────────────────── */
function competentieVerversen(solId) {
  const status = document.getElementById('compStatus-' + solId);
  if (status) status.textContent = 'Ophalen uit Authentik…';
  fetch(APP_URL + '/admin/api_competentie.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ actie: 'ververs', sol_id: solId, _csrf: CSRF })
  })
  .then(r => r.json())
  .then(data => {
    if (!data.ok) {
      if (status) status.textContent = data.fout || data.melding || 'Ophalen mislukt.';
      bhToast(data.fout || data.melding || 'Ophalen mislukt.', 'fout');
      return;
    }
    location.reload();
  })
  .catch(() => { if (status) status.textContent = 'Verbindingsfout'; });
}

function competentieKoppelen(solId) {
  const username = document.getElementById('compUsername-' + solId).value.trim();
  if (!username) { bhToast('Geef eerst een gebruikersnaam op.', 'fout'); return; }
  const status = document.getElementById('compStatus-' + solId);
  if (status) status.textContent = 'Koppelen en synchroniseren…';
  fetch(APP_URL + '/admin/api_competentie.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ actie: 'koppel', sol_id: solId, username, _csrf: CSRF })
  })
  .then(r => r.json())
  .then(data => {
    if (!data.ok) {
      if (status) status.textContent = data.fout || data.melding || 'Koppelen mislukt.';
      bhToast(data.fout || data.melding || 'Koppelen mislukt.', 'fout');
      return;
    }
    location.reload();
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
    if (!data.ok) { bhToast(data.fout || 'Opslaan mislukt.', 'fout'); return; }
    const lbl = document.getElementById('normLabel-' + solId);
    if (lbl) lbl.textContent = data.norm_dagen ? data.norm_dagen + ' dagen/week (afwijkend)' : '5 dagen/week (standaard)';
    document.getElementById('normEdit-' + solId).style.display = 'none';
  })
  .catch(() => bhToast('Verbindingsfout — probeer opnieuw.', 'fout'));
}

/* ── Verzoek afwijkend aantal dagen (bestuur beslist) ───────── */
function verzoekBeslis(verzoekId, besluit, solId) {
  var doen = function () {
    fetch(APP_URL + '/admin/api_verzoek.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ verzoek_id: verzoekId, besluit: besluit, _csrf: CSRF })
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (!data.ok) { bhToast(data.fout || 'Opslaan mislukt.', 'fout'); return; }
      location.reload();
    })
    .catch(function () { bhToast('Verbindingsfout — probeer opnieuw.', 'fout'); });
  };
  if (besluit !== 'afwijzen') { doen(); return; }
  bhBevestig({
    titel: 'Dit verzoek afwijzen?',
    tekst: 'De sollicitant houdt dan de beschikbaarheid die er nu staat. Hij krijgt hierover bericht.',
    knop: 'Ja, afwijzen', gevaar: true
  }).then(function (ja) { if (ja) doen(); });
}

/* ── Bestuurs-correctie per week ─────────────────────────────────
   Eén paneel, twee klanten: een cel in het beschikbaarheidsraster en een weektegel
   in het dossier. Daarom staat het hier en niet in een scherm.

   De oude versie vroeg eerst "kan niet" of "alleen bepaalde dagen" en pas daarna de
   dagen. Dat is één vraag te veel: de dagen zélf zeggen het al. Geen enkele dag
   aangevinkt = die week niet. Dat is wat de server als soort 'niet' kent, dus de
   opslag verandert niet mee — alleen de vraag die je gesteld wordt. */
var CORR_DAGEN = [[1,'ma'],[2,'di'],[3,'wo'],[4,'do'],[5,'vr'],[6,'za'],[7,'zo']];

function correctieOpen(solId, week, naam) {
  var oud = document.getElementById('corrPaneel');
  if (oud) oud.remove();

  var dagknoppen = CORR_DAGEN.map(function (d) {
    return '<button type="button" class="bh-dagknop" data-dag="' + d[0] + '" aria-pressed="false"' +
           ' onclick="corrDagWissel(this)">' + d[1] + '</button>';
  }).join('');

  var overlay = document.createElement('div');
  overlay.id = 'corrPaneel';
  overlay.className = 'bh-zijpaneel-overlay';
  overlay.innerHTML =
    '<div class="bh-zijpaneel" role="dialog" aria-modal="true" aria-labelledby="corrTitel">' +
      '<header class="bh-zijpaneel-kop">' +
        '<div class="bh-zijpaneel-wie">' +
          '<strong id="corrTitel">' + (naam ? bhTekst(naam) : 'Beschikbaarheid') + '</strong>' +
          '<span>week ' + week + ' · correctie door het bestuur</span>' +
        '</div>' +
        '<button type="button" class="bh-zijpaneel-sluit" aria-label="Sluiten" onclick="corrSluit()">&times;</button>' +
      '</header>' +
      '<div class="bh-zijpaneel-body">' +
        '<section class="bh-zijpaneel-blok">' +
          '<h3>Welke dagen kan deze week wél?</h3>' +
          '<div class="bh-dagknoppen">' + dagknoppen + '</div>' +
          '<div class="bh-snelkeuzes">' +
            '<button type="button" class="bh-snelkeuze" onclick="corrSnel(\'week\')">Hele week</button>' +
            '<button type="button" class="bh-snelkeuze" onclick="corrSnel(\'weekend\')">Alleen weekend</button>' +
            '<button type="button" class="bh-snelkeuze" onclick="corrSnel(\'geen\')">Deze week niet</button>' +
          '</div>' +
          '<p class="bh-zijpaneel-hint" id="corrSamenvatting">Niets geselecteerd — dat betekent: deze week helemaal niet beschikbaar.</p>' +
        '</section>' +
        '<section class="bh-zijpaneel-blok">' +
          '<h3><label for="corrReden">Reden</label></h3>' +
          '<input type="text" id="corrReden" class="ds-veld" maxlength="200"' +
                ' placeholder="bijv. belde: die week op vakantie">' +
          '<p class="bh-zijpaneel-hint">Verplicht. Deze regel komt in de tijdlijn van de sollicitant te staan,' +
          ' zodat later terug te vinden is waarom dit is aangepast.</p>' +
        '</section>' +
      '</div>' +
      '<footer class="bh-zijpaneel-voet">' +
        '<button type="button" class="ds-btn ds-btn-zacht ds-btn-sm" onclick="corrSluit()">Annuleren</button>' +
        '<button type="button" class="ds-btn ds-btn-primair ds-btn-sm" onclick="correctieOpslaan(' + solId + ',' + week + ')">Opslaan</button>' +
      '</footer>' +
    '</div>';

  document.body.appendChild(overlay);
  overlay.addEventListener('click', function (e) { if (e.target === overlay) corrSluit(); });
  document.addEventListener('keydown', corrEscape);
  var reden = document.getElementById('corrReden');
  if (reden) reden.focus();
}

function corrEscape(e) { if (e.key === 'Escape') corrSluit(); }

function corrSluit() {
  var el = document.getElementById('corrPaneel');
  if (el) el.remove();
  document.removeEventListener('keydown', corrEscape);
}

function corrGekozen() {
  return Array.from(document.querySelectorAll('#corrPaneel .bh-dagknop[aria-pressed="true"]'))
              .map(function (b) { return b.dataset.dag; });
}

function corrSamenvatting() {
  var n = corrGekozen().length;
  var el = document.getElementById('corrSamenvatting');
  if (!el) return;
  el.textContent = n === 0
    ? 'Niets geselecteerd — dat betekent: deze week helemaal niet beschikbaar.'
    : n + (n === 1 ? ' dag' : ' dagen') + ' beschikbaar deze week.';
}

function corrDagWissel(knop) {
  knop.setAttribute('aria-pressed', knop.getAttribute('aria-pressed') === 'true' ? 'false' : 'true');
  corrSamenvatting();
}

function corrSnel(welke) {
  var aan = welke === 'week' ? [1,2,3,4,5,6,7] : (welke === 'weekend' ? [5,6,7] : []);
  document.querySelectorAll('#corrPaneel .bh-dagknop').forEach(function (b) {
    b.setAttribute('aria-pressed', aan.indexOf(parseInt(b.dataset.dag, 10)) !== -1 ? 'true' : 'false');
  });
  corrSamenvatting();
}

function correctieOpslaan(solId, week) {
  var reden = (document.getElementById('corrReden').value || '').trim();
  if (!reden) { bhToast('Vul een reden in — die komt in de tijdlijn te staan.', 'fout'); return; }
  var dagen = corrGekozen();
  var soort = dagen.length ? 'minder' : 'niet';
  var body  = new URLSearchParams({ sol_id: solId, week: week, soort: soort, reden: reden, _csrf: CSRF });
  dagen.forEach(function (d) { body.append('dagen[]', d); });
  fetch(APP_URL + '/admin/api_correctie.php', {
    method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body
  })
  .then(function (r) { return r.json(); })
  .then(function (data) {
    if (!data.ok) { bhToast(data.fout || 'Opslaan mislukt.', 'fout'); return; }
    location.reload();
  })
  .catch(function () { bhToast('Verbindingsfout — probeer opnieuw.', 'fout'); });
}

function correctieWis(solId, week) {
  bhBevestig({
    titel: 'Correctie voor week ' + week + ' ongedaan maken?',
    tekst: 'De beschikbaarheid valt dan terug op wat de sollicitant zelf opgaf.',
    knop: 'Ja, herstellen'
  }).then(function (ja) {
    if (!ja) return;
    fetch(APP_URL + '/admin/api_correctie.php', {
      method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ sol_id: solId, week: week, soort: '', _csrf: CSRF })
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (!data.ok) { bhToast(data.fout || 'Herstellen mislukt.', 'fout'); return; }
      location.reload();
    })
    .catch(function () { bhToast('Verbindingsfout — probeer opnieuw.', 'fout'); });
  });
}

/* ── Ondertekening ──────────────────────────────────────────────
   De dossierpagina geeft een token mee; hier maken we daar de volledige link van,
   zodat er maar één plek is die weet hoe die eruitziet. */
function kopieerOndertekenlink(tokenOfUrl) {
  var url = /^https?:/i.test(tokenOfUrl)
    ? tokenOfUrl
    : APP_URL + '/portaal/ondertekenen.php?token=' + encodeURIComponent(tokenOfUrl);
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(url)
      .then(function () { bhToast('Link gekopieerd — plak hem in een bericht.', 'succes'); })
      .catch(function () { bhToast('Kopiëren lukte niet: ' + url, 'fout'); });
  } else {
    bhToast('Kopiëren lukte niet: ' + url, 'fout');
  }
}

function startOndertekening(solId, type) {
  const labels = {
    'gezondheid_ouder':  'Gezondheidsverklaring t/m 17 (ouder ondertekent)',
    'gezondheid_18plus': 'Gezondheidsverklaring 18+ (sollicitant zelf)',
    'intake':            'Intakeformulier selectiedag',
    'intake_ouder':      'Intakeformulier selectiedag (ouder ondertekent)',
  };
  const isOuder = (type === 'gezondheid_ouder' || type === 'intake_ouder');

  function versturen(email, naam) {
    fetch(APP_URL + '/admin/api_ondertekening.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        sol_id: solId, type: type, naar_email: email || '', naar_naam: naam || '', _csrf: CSRF
      })
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (!data.ok) { bhToast(data.fout || 'Versturen mislukt.', 'fout'); return; }
      bhToast('Uitnodiging verstuurd.', 'succes');
      setTimeout(function () { location.reload(); }, 900);
    })
    .catch(function () { bhToast('Verbindingsfout — probeer opnieuw.', 'fout'); });
  }

  if (!isOuder) {
    bhBevestig({
      titel: labels[type] + ' klaarzetten?',
      tekst: 'De sollicitant krijgt een bericht met een persoonlijke ondertekenlink.',
      knop: 'Ja, versturen'
    }).then(function (ja) { if (ja) versturen('', ''); });
    return;
  }
  // Onder de 18 tekent een ouder mee; daarvoor is een adres nodig.
  bhVraag({
    titel: 'Naar welk adres van de ouder of verzorger?',
    tekst: labels[type] + ' gaat naar dit adres, niet naar de sollicitant zelf.',
    type: 'email', plaatshouder: 'naam@voorbeeld.nl', knop: 'Volgende'
  }).then(function (email) {
    if (!email) return;
    bhVraag({
      titel: 'Naam van de ouder of verzorger',
      tekst: 'Mag leeg blijven; de naam komt in de aanhef van het bericht.',
      plaatshouder: 'optioneel', knop: 'Versturen'
    }).then(function (naam) { versturen(email, naam || ''); });
  });
}

/* ── Notities beheer ────────────────────────────────────────── */
function laadNotities(solId) {
  fetch(APP_URL + '/admin/api_notitie.php?actie=lijst&sol_id=' + solId)
    .then(r => r.json())
    .then(data => {
      const box = document.getElementById('notities-lijst-' + solId);
      if (!box) return;
      if (!data.ok) {
        box.innerHTML = '<p class="bh-dossier-tekst zacht">Notities konden niet geladen worden.</p>';
        return;
      }
      const mijnId = data.huidige_id;
      if (!data.notities.length) {
        box.innerHTML = '<p class="bh-dossier-tekst zacht">Nog geen notities.</p>';
        return;
      }
      box.innerHTML = data.notities.map(n => {
        const bewerkbaar = (n.beheerder_id == mijnId);
        const acties = bewerkbaar
          ? `<span class="bh-notitie-acties">
               <button type="button" onclick="notitieBewerken(${n.id}, ${solId})">Bewerken</button>
               <button type="button" class="gevaar" onclick="notitieVerwijderen(${n.id}, ${solId})">Verwijderen</button>
             </span>`
          : '';
        const tekstEscaped = String(n.tekst).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
        return `
          <div class="bh-notitie notitie-item" data-id="${n.id}">
            <div class="notitie-tekst">${tekstEscaped}</div>
            <div class="bh-notitie-wie">
              ${n.beheerder_naam || 'Onbekend'} · ${formatDatum(n.aangemaakt_op)}
              ${n.gewijzigd_op !== n.aangemaakt_op ? ' (bewerkt)' : ''}
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
    if (!data.ok) { bhToast(data.fout || 'Opslaan mislukt.', 'fout'); return; }
    veld.value = '';
    laadNotities(solId);
    laadTimeline(solId);
  })
  .catch(() => bhToast('Verbindingsfout — probeer opnieuw.', 'fout'));
}

function notitieBewerken(id, solId) {
  const item = document.querySelector('.notitie-item[data-id="' + id + '"]');
  if (!item) return;
  const huidig = item.querySelector('.notitie-tekst').innerText;
  bhVraag({ titel: 'Notitie bewerken', waarde: huidig, lang: true, knop: 'Opslaan' })
    .then(function (nieuwe) {
      if (nieuwe === null || nieuwe === '') return;
      fetch(APP_URL + '/admin/api_notitie.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ actie: 'bewerken', id: id, tekst: nieuwe, _csrf: CSRF })
      })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok) bhToast(data.fout || 'Opslaan mislukt.', 'fout');
        laadNotities(solId);
      });
    });
}

function notitieVerwijderen(id, solId) {
  bhBevestig({
    titel: 'Deze notitie verwijderen?',
    tekst: 'De notitie verdwijnt uit het dossier. Dat de notitie er was, blijft in het logboek staan.',
    knop: 'Ja, verwijderen', gevaar: true
  }).then(function (ja) {
    if (!ja) return;
    fetch(APP_URL + '/admin/api_notitie.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ actie: 'verwijderen', id: id, _csrf: CSRF })
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (!data.ok) bhToast(data.fout || 'Verwijderen mislukt.', 'fout');
      laadNotities(solId); laadTimeline(solId);
    });
  });
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
  if (loadEl) { loadEl.style.display = ''; loadEl.textContent = 'Toevoegen aan de wachtrij…'; }
  if (knop)   knop.disabled = true;

  fetch(APP_URL + '/admin/api_ai_analyse.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ sol_id: solId, _csrf: CSRF })
  })
  .then(function (r) { return r.json(); })
  .then(function (data) {
    if (knop) knop.disabled = false;
    if (!data.ok) {
      if (loadEl) loadEl.style.display = 'none';
      bhToast(data.fout || 'Toevoegen mislukt.', 'fout');
      return;
    }
    const msgs = {
      'al_klaar':       'De analyse staat er al — ververs de pagina.',
      'al_in_wachtrij': 'Staat al in de wachtrij; binnen vijf minuten verwerkt.',
      'toegevoegd':     'In de wachtrij gezet; binnen vijf minuten verwerkt.'
    };
    const tekst = msgs[data.status] || data.bericht || 'Aangevraagd.';
    if (loadEl) { loadEl.style.display = ''; loadEl.textContent = tekst; }
    bhToast(tekst, 'succes');
    if (data.status === 'al_klaar') setTimeout(function () { location.reload(); }, 900);
  })
  .catch(function () {
    if (loadEl) loadEl.style.display = 'none';
    if (knop)   knop.disabled = false;
    bhToast('Verbindingsfout — probeer opnieuw.', 'fout');
  });
}

/* De zijbalk zit nu in assets/js/beheer.js (zijbalkKoppelen). Hij stond hier én in
   layout_top.php, met verschillende klassenamen en breekpunten; de tweede overschreef
   de eerste stil. Deze twee no-ops blijven staan zolang oude schermen ze nog in een
   onclick hebben staan — weg zodra `grep -rn "toggleSidebar" admin/` leeg is. */
window.toggleSidebar = function() {
  document.getElementById('hamburger')?.click();
};
window.sluitSidebar = function() {
  const b = document.getElementById('zijbalk');
  if (b && b.classList.contains('open')) document.getElementById('hamburger')?.click();
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
  tooltipEl.style.cssText = 'position:absolute;background:var(--navy);color:var(--op-vol);padding:8px 12px;border-radius:6px;font-size:12px;line-height:1.5;max-width:260px;z-index:10000;pointer-events:none;opacity:0;transition:opacity .15s;box-shadow:0 4px 12px rgba(0,0,0,.3)';
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
  // Oude links met ?panel=<id> (bladwijzers, mails) gaan naar het dossier.
  const panelId = new URLSearchParams(window.location.search).get('panel');
  if (panelId && /^\d+$/.test(panelId)) openPaneel(panelId);
});
</script>
<script src="<?= APP_URL ?>/assets/js/actie.js?v=<?= @filemtime(__DIR__.'/../assets/js/actie.js') ?>"></script>
<script src="<?= APP_URL ?>/assets/js/app.js?v=<?= @filemtime(__DIR__.'/../assets/js/app.js') ?>"></script>
<script src="<?= APP_URL ?>/assets/js/editor.js?v=<?= @filemtime(__DIR__.'/../assets/js/editor.js') ?>"></script>
<script src="<?= APP_URL ?>/assets/js/beheer.js?v=<?= @filemtime(__DIR__.'/../assets/js/beheer.js') ?>"></script>
</body>
</html>