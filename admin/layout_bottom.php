    </main>
  </div><!-- /bh-hoofd -->
</div><!-- /bh-kader -->

<script nonce="<?= h(csp_nonce()) ?>">
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
/* afwijkend = de stap is niet de gewone volgende (bh_fase_afwijkend() in PHP bepaalt dat).
   Dan volgt na de bevestiging één vraag om een reden. Die is optioneel — een lege reden
   blokkeert niets — en komt als notitie in de tijdlijn. Annuleren bij die vraag breekt
   de hele wijziging af. */
/* tekst = bh_fase_bevestig_tekst() uit PHP (wie, of er bericht uitgaat); gevaar bij afwijzen. */
function fasWijzigen(solId, naar, label, afwijkend, tekst, gevaar) {
  bhBevestig({
    titel: 'Fase wijzigen naar "' + label + '"?',
    tekst: (tekst || 'Hoort bij deze overgang een bericht, dan gaat dat automatisch de deur uit.')
         + (afwijkend ? ' Deze stap wijkt af van de gewone volgorde; hierna kun je een reden opgeven.' : ''),
    knop:  afwijkend ? 'Verder' : 'Ja, wijzigen',
    gevaar: !!gevaar,
    // Heb je al gebeld, of corrigeer je een fase die verkeerd stond? Dan hoeft er
    // geen standaardbericht uit. Tot nu toe kon dat alleen door het hele kanaal
    // tijdelijk uit te zetten bij Instellingen — en dat raakte iedereen.
    vink: { label: 'Zonder bericht versturen',
            uitleg: 'De fase verandert wel; er gaat alleen geen automatisch bericht naar deze persoon.' }
  }).then(function (ja) {
    if (!ja) return;
    var stil = (typeof ja === 'object' && ja.vink) ? '1' : '';
    if (!afwijkend) { fasVersturen(solId, naar, stil, ''); return; }
    bhVraag({
      titel: 'Waarom wijk je af? (optioneel)',
      tekst: 'Komt als notitie in de tijdlijn, zodat later terug te lezen is waarom. Leeg laten mag.',
      lang: true, plaatshouder: 'Bijvoorbeeld: verkeerd gezet, gebeld en besproken', knop: 'Ja, wijzigen'
    }).then(function (reden) {
      if (reden === null) return;          // Annuleren: niets wijzigen
      fasVersturen(solId, naar, stil, reden);
    });
  });
}

function fasVersturen(solId, naar, stil, reden) {
  // Meteen zichtbaar dat er iets loopt: anders gebeurt er tot het herladen niets.
  var klaar = window.P13Actie ? window.P13Actie.bezig(null, null) : function () {};
  fetch(APP_URL + '/admin/api_fase.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ sol_id: solId, naar: naar, stil: stil, reden: reden || '', _csrf: CSRF })
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

/* verwijderActie() is weg. Verwijderen is onomkeerbaar én raakt persoonsgegevens, dus
   het gaat via het tussenscherm in admin/sollicitant.php (_bevestig.php): werkt zonder
   JavaScript, met de keuze "alleen deze sollicitatie" of "alles van deze persoon" en —
   bij een verwerkt loondossier — het vinkje voor het dienstverband. */

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

/* ── Afwijkende norm (bestuursbesluit) ──────────────────────────
   Het formulier staat in het tabblad Beschikbaarheid van het dossier
   (admin/dossier/_norm.php) en roept dit aan via onsubmit. De knop gaat in laadstand
   tijdens het verzoek; daarna staat de nieuwe norm in het label. */
function normOpslaan(solId, form) {
  var dagen   = document.getElementById('normDagen-' + solId).value;
  var notitie = document.getElementById('normNotitie-' + solId).value;
  var knop = form ? form.querySelector('button[type=submit]') : null;
  var klaar = window.P13Actie ? window.P13Actie.bezig(form || null, knop) : function () {};
  fetch(APP_URL + '/admin/api_norm.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ sol_id: solId, norm_dagen: dagen, norm_notitie: notitie, _csrf: CSRF })
  })
  .then(function (r) { return r.json(); })
  .then(function (data) {
    klaar();
    if (!data.ok) { bhToast(data.fout || 'Opslaan mislukt.', 'fout'); return; }
    var lbl = document.getElementById('normLabel-' + solId);
    if (lbl) lbl.textContent = data.norm_dagen ? data.norm_dagen + ' dagen per week (afwijkend)' : '5 dagen per week (standaard)';
    bhToast('Norm opgeslagen.', 'succes');
  })
  .catch(function () { klaar(); bhToast('Verbindingsfout — probeer opnieuw.', 'fout'); });
  return false;
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
    tekst: 'De sollicitant houdt dan de beschikbaarheid die er nu staat en krijgt hierover bericht.',
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
var corrWeek = 0, corrSol = 0, corrLaatsteWeek = 0;

/* Openen doet twee dingen: meteen een leeg paneel neerzetten (anders gebeurt er na je
   klik een halve seconde niets) en de gegevens erbij halen. Wat er al staat — dagen,
   reden, historie — komt van de server, zodat het paneel niet hoeft te raden wat het
   raster toevallig toont. */
function correctieOpen(solId, week, naam) {
  corrSol = solId; corrWeek = week;
  corrPaneelNeerzetten(naam, week);

  fetch(APP_URL + '/admin/api_correctie.php?sol_id=' + solId + '&week=' + week, { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (!d.ok) { corrSluit(); bhToast(d.fout || 'Ophalen mislukt.', 'fout'); return; }
      corrVullen(d);
    })
    .catch(function () { corrSluit(); bhToast('Verbindingsfout — probeer opnieuw.', 'fout'); });
}

function corrPaneelNeerzetten(naam, week) {
  var oud = document.getElementById('corrPaneel');
  if (oud) oud.remove();

  var dagknoppen = CORR_DAGEN.map(function (d) {
    return '<button type="button" class="bh-dagknop" data-dag="' + d[0] + '" aria-pressed="false"' +
           ' data-klik="corrDagWissel(this)">' + d[1] + '</button>';
  }).join('');

  /* Op een breed scherm schuift het paneel naast het raster in plaats van eroverheen —
     zo blijven de weken zichtbaar waar je juist naar kijkt terwijl je er een aanpast
     (Beheer ontwerp.dc.html r.854: raster en paneel staan náást elkaar). Alleen als het
     paneel de pagina écht afdekt is het modaal; anders zou aria-modal tegen een
     schermlezer liegen over wat er nog bereikbaar is. */
  var naast = window.matchMedia('(min-width: 1280px)').matches;

  var overlay = document.createElement('div');
  overlay.id = 'corrPaneel';
  overlay.className = 'bh-zijpaneel-overlay' + (naast ? ' bh-zijpaneel-naast' : '');
  overlay.innerHTML =
    '<div class="bh-zijpaneel" role="dialog"' + (naast ? '' : ' aria-modal="true"') +
    ' aria-labelledby="corrTitel">' +
      '<header class="bh-zijpaneel-kop">' +
        '<span class="bh-zijpaneel-avatar" id="corrInitialen" aria-hidden="true"></span>' +
        '<div class="bh-zijpaneel-wie">' +
          '<strong id="corrTitel">' + (naam ? bhTekst(naam) : 'Beschikbaarheid') + '</strong>' +
          '<span id="corrPeriode">week ' + week + '</span>' +
        '</div>' +
        '<button type="button" class="bh-zijpaneel-sluit" aria-label="Sluiten" data-klik="corrSluit()">&times;</button>' +
      '</header>' +
      '<div class="bh-zijpaneel-body">' +

        '<section class="bh-zijpaneel-blok">' +
          '<h3>Dagen deze week</h3>' +
          '<div class="bh-dagknoppen">' + dagknoppen + '</div>' +
          '<div class="bh-snelkeuzes">' +
            '<button type="button" class="bh-snelkeuze" data-klik="corrSnel(\'week\')">Hele week</button>' +
            '<button type="button" class="bh-snelkeuze" data-klik="corrSnel(\'weekend\')">Alleen weekend</button>' +
            '<button type="button" class="bh-snelkeuze" data-klik="corrSnel(\'geen\')">Deze week niet</button>' +
          '</div>' +
          '<p class="bh-zijpaneel-hint" id="corrSamenvatting">Niets geselecteerd — dat betekent: deze week helemaal niet beschikbaar.</p>' +
          '<p class="bh-zijpaneel-hint" id="corrNorm" hidden></p>' +
          '<label class="bh-zijpaneel-vink" id="corrKopieerRegel" hidden>' +
            '<input type="checkbox" id="corrKopieer">' +
            '<span id="corrKopieerTekst">Ook de volgende weken</span>' +
          '</label>' +
        '</section>' +

        '<section class="bh-zijpaneel-blok">' +
          '<h3>Status</h3>' +
          '<p class="bh-zijpaneel-status" id="corrStatus"></p>' +
        '</section>' +

        '<section class="bh-zijpaneel-blok">' +
          '<h3><label for="corrRedenKeuze">Reden</label></h3>' +
          '<select id="corrRedenKeuze" class="ds-veld" data-wijzig="corrRedenWissel()"></select>' +
          '<input type="text" id="corrReden" class="ds-veld bh-zijpaneel-redenvrij" maxlength="200"' +
                ' placeholder="Toelichting (optioneel)">' +
          '<p class="bh-zijpaneel-hint">Deze regel komt in de tijdlijn van de sollicitant te staan,' +
          ' zodat later terug te vinden is waarom dit is aangepast.</p>' +
          '<label class="bh-zijpaneel-vink">' +
            '<input type="checkbox" id="corrBericht">' +
            '<span id="corrBerichtTekst">Hierover berichten</span>' +
          '</label>' +
        '</section>' +

        '<section class="bh-zijpaneel-blok" id="corrHistorieBlok" hidden>' +
          '<h3>Historie</h3>' +
          '<ul class="bh-zijpaneel-historie" id="corrHistorie"></ul>' +
        '</section>' +

      '</div>' +
      '<footer class="bh-zijpaneel-voet">' +
        // Alleen zichtbaar als er iets terug te draaien ís: dan valt de week terug op
        // wat de sollicitant zelf opgaf.
        '<button type="button" class="ds-btn ds-btn-zacht ds-btn-sm bh-zijpaneel-wis" id="corrWis"' +
              ' data-klik="correctieWis()" hidden>Correctie ongedaan maken</button>' +
        '<button type="button" class="ds-btn ds-btn-zacht ds-btn-sm" data-klik="corrSluit()">Annuleren</button>' +
        '<button type="button" class="ds-btn ds-btn-primair ds-btn-sm" id="corrOpslaan"' +
              ' data-klik="correctieOpslaan()">Opslaan</button>' +
      '</footer>' +
    '</div>';

  document.body.appendChild(overlay);
  document.body.classList.add('bh-paneel-open');
  overlay.addEventListener('click', function (e) { if (e.target === overlay) corrSluit(); });
  document.addEventListener('keydown', corrEscape);
}

/** De uitkomst van de GET in het paneel zetten. */
function corrVullen(d) {
  var zet = function (id, tekst) { var el = document.getElementById(id); if (el) el.textContent = tekst; };

  zet('corrInitialen', d.initialen || '');
  zet('corrTitel', d.naam || 'Beschikbaarheid');
  zet('corrPeriode', 'week ' + d.week + (d.periode ? ' · ' + d.periode : ''));
  zet('corrBerichtTekst', (d.naam ? d.naam.split(' ')[0] : 'De sollicitant') + ' hierover berichten');

  /* De dagen zoals ze nu gelden — niet alleen een eerdere correctie. Klik je op een
     cel waar "4" staat, dan zie je die vier dagen aangevinkt en weet je wat je
     verandert. Een dag die de sollicitant liever vrij heeft krijgt een merkteken: de
     planner beslist, maar moet het wel weten. */
  var aan = d.dagen || [];
  var vrij = d.voorkeur_vrij || [];
  document.querySelectorAll('#corrPaneel .bh-dagknop').forEach(function (b) {
    var nr = parseInt(b.dataset.dag, 10);
    b.setAttribute('aria-pressed', aan.indexOf(nr) !== -1 ? 'true' : 'false');
    if (vrij.indexOf(nr) !== -1) {
      b.classList.add('voorkeur-vrij');
      b.title = 'Heeft deze dag liever vrij (voorkeur, jij beslist)';
    }
  });
  corrSamenvatting();

  /* Het getal in de cel is begrensd door de norm; er kunnen dus méér dagen aanstaan
     dan de cel laat zien. Zonder deze regel lijkt het paneel iets anders te zeggen
     dan het raster. */
  var normregel = document.getElementById('corrNorm');
  if (!d.ingevuld) {
    normregel.hidden = false;
    normregel.textContent = 'Deze sollicitant heeft voor deze week nog niets ingevuld. '
                          + 'Wat je hier kiest geldt als correctie van het bestuur.';
  } else if (d.norm && aan.length > d.norm) {
    normregel.hidden = false;
    normregel.textContent = 'In het raster staat ' + d.norm + ': dat is de norm van deze persoon, '
                          + 'niet het aantal dagen dat die persoon kan.';
  } else if (d.regime === 'weekend') {
    normregel.hidden = false;
    normregel.textContent = 'Deze week telt als weekendweek — alleen de weekenddagen tellen mee.';
  }

  var wis = document.getElementById('corrWis');
  if (wis && d.heeft_correctie) wis.hidden = false;

  // Doorzetten naar de rest van het seizoen
  corrLaatsteWeek = d.laatste_week || 0;
  if (corrLaatsteWeek > d.week) {
    document.getElementById('corrKopieerRegel').hidden = false;
    zet('corrKopieerTekst', 'Ook W' + (d.week + 1) + ' t/m W' + corrLaatsteWeek);
  }

  /* De status hoort bij de hele beschikbaarheid van deze persoon, niet bij deze ene
     week — het model kent geen status per week. Dat staat er daarom bij, anders lijkt
     "voorlopig" over week 28 te gaan. */
  zet('corrStatus', d.voorlopig
    ? 'Voorlopig — deze sollicitant heeft de beschikbaarheid nog niet definitief gemaakt.'
    : 'Definitief — de sollicitant heeft de beschikbaarheid vastgezet.');

  // Redenen
  var sel = document.getElementById('corrRedenKeuze');
  sel.innerHTML = '<option value="">Kies een reden…</option>';
  Object.keys(d.redenen || {}).forEach(function (k) {
    var o = document.createElement('option');
    o.value = k; o.textContent = d.redenen[k];
    if (k === d.redensleutel) o.selected = true;
    sel.appendChild(o);
  });
  document.getElementById('corrReden').value = d.reden || '';
  corrRedenWissel();

  // Historie
  if ((d.historie || []).length) {
    var ul = document.getElementById('corrHistorie');
    ul.innerHTML = '';
    d.historie.forEach(function (h) {
      var li = document.createElement('li');
      li.innerHTML = '<span class="bh-zijpaneel-hwanneer">' + bhTekst(h.wanneer) + '</span>' +
                     '<span class="bh-zijpaneel-hwat">' + bhTekst(h.wat) + '</span>';
      ul.appendChild(li);
    });
    document.getElementById('corrHistorieBlok').hidden = false;
  }

  if (d.mag_bewerken === false) {
    document.getElementById('corrOpslaan').hidden = true;
    var kaart = document.querySelector('#corrPaneel .bh-zijpaneel');
    if (kaart) kaart.querySelectorAll('input,select,button.bh-dagknop,button.bh-snelkeuze')
                    .forEach(function (el) { el.disabled = true; });
  }
  sel.focus();
}

function corrRedenWissel() {
  // Het vrije veld is bij "Anders…" de reden zelf, en anders een toelichting erop.
  var k = document.getElementById('corrRedenKeuze').value;
  var vrij = document.getElementById('corrReden');
  vrij.placeholder = k === 'anders' ? 'Beschrijf de reden' : 'Toelichting (optioneel)';
}

function corrEscape(e) { if (e.key === 'Escape') corrSluit(); }

function corrSluit() {
  var el = document.getElementById('corrPaneel');
  if (el) el.remove();
  document.body.classList.remove('bh-paneel-open');
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

function correctieOpslaan() {
  var sleutel = document.getElementById('corrRedenKeuze').value;
  var vrij    = (document.getElementById('corrReden').value || '').trim();
  if (!sleutel && !vrij) { bhToast('Kies een reden — die komt in de tijdlijn te staan.', 'fout'); return; }
  if (sleutel === 'anders' && !vrij) { bhToast('Beschrijf de reden.', 'fout'); return; }

  var dagen = corrGekozen();
  var kopieer = document.getElementById('corrKopieer');
  var knop = document.getElementById('corrOpslaan');
  var klaar = window.P13Actie ? window.P13Actie.bezig(null, knop) : function () {};

  var body = new URLSearchParams({
    sol_id: corrSol, week: corrWeek,
    soort: dagen.length ? 'minder' : 'niet',
    redensleutel: sleutel, reden: vrij,
    tot_week: (kopieer && kopieer.checked) ? corrLaatsteWeek : 0,
    bericht: (document.getElementById('corrBericht').checked ? '1' : ''),
    _csrf: CSRF
  });
  dagen.forEach(function (d) { body.append('dagen[]', d); });

  fetch(APP_URL + '/admin/api_correctie.php', {
    method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body
  })
  .then(function (r) { return r.json(); })
  .then(function (data) {
    if (!data.ok) { klaar(); bhToast(data.fout || 'Opslaan mislukt.', 'fout'); return; }
    location.reload();   // de melding komt als flash terug na het herladen
  })
  .catch(function () { klaar(); bhToast('Verbindingsfout — probeer opnieuw.', 'fout'); });
}

/** Zonder argumenten: de week die op dat moment in het paneel staat. */
function correctieWis(solId, week) {
  solId = solId || corrSol;
  week  = week  || corrWeek;
  bhBevestig({
    titel: 'Correctie voor week ' + week + ' ongedaan maken?',
    tekst: 'De beschikbaarheid valt dan terug op wat de sollicitant zelf opgaf.',
    knop: 'Ja, herstellen'
  }).then(function (ja) {
    if (!ja) return;
    var klaar = window.P13Actie ? window.P13Actie.bezig(null, document.getElementById('corrWis')) : function () {};
    fetch(APP_URL + '/admin/api_correctie.php', {
      method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ sol_id: solId, week: week, soort: '', _csrf: CSRF })
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (!data.ok) { klaar(); bhToast(data.fout || 'Herstellen mislukt.', 'fout'); return; }
      location.reload();
    })
    .catch(function () { klaar(); bhToast('Verbindingsfout — probeer opnieuw.', 'fout'); });
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

/* startOndertekening() is weg: niets riep hem nog aan. De documenten zet fase_zetten()
   klaar bij de uitnodiging, en een verkeerd ouder-adres wordt bij "Opnieuw versturen"
   bijgewerkt (includes/ondertekeningen_auto.php). */

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
               <button type="button" data-klik="notitieBewerken(${n.id}, ${solId})">Bewerken</button>
               <button type="button" class="gevaar" data-klik="notitieVerwijderen(${n.id}, ${solId})">Verwijderen</button>
             </span>`
          : '';
        const tekstEscaped = String(n.tekst).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
        return `
          <div class="bh-notitie notitie-item" data-id="${n.id}">
            <div class="notitie-tekst">${tekstEscaped}</div>
            <div class="bh-notitie-wie">
              ${bhTekst(n.beheerder_naam || 'Onbekend')} · ${formatDatum(n.aangemaakt_op)}
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
        box.innerHTML = '<div class="bh-tijdlijn-leeg">Nog geen activiteit.</div>';
        return;
      }
      box.innerHTML = '<div class="timeline-lijn bh-tijdlijn">'
        + data.events.map(e => {
            // Tekst altijd escapen (namen en titels komen uit de database); het icoon
            // is een SVG die de server zelf maakt met icoon().
            const esc = s => String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            return `
              <div class="timeline-event bh-tijdlijn-item">
                <span class="bh-tijdlijn-icoon">${e.icon || ''}</span>
                <div class="bh-tijdlijn-titel">${esc(e.titel)}</div>
                <div class="bh-tijdlijn-inhoud">${esc(e.inhoud)}</div>
                <div class="bh-tijdlijn-wanneer">${formatDatum(e.tijd)} · ${esc(e.door)}</div>
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

/* Foto vergroten zat hier én in assets/js/app.js: twee overlays met twee
   uiterlijken en twee Escape-handlers op dezelfde afbeeldingen. Er is er nu
   nog één, in app.js (#p13-lightbox), die ook window.fotoVergroten levert —
   met bijschrift, een sluitknop en focus die terugkeert naar de knop. */

/* ── Tooltip systeem ──────────────────────────────────────────
   Gebruik: bh_tip('Uitleg hier') (een knop), of data-tip op een bestaand element.
   Ook bij toetsenbordfocus; Esc sluit. */
document.addEventListener('DOMContentLoaded', () => {
  const tooltipEl = document.createElement('div');
  tooltipEl.id = 'p13-tooltip';
  tooltipEl.className = 'bh-tooltip';   // uiterlijk in beheer.css; alleen positie en zichtbaarheid hier
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
  // Toetsenbord: tonen bij focus, weg bij verlaten of Esc (WCAG 1.4.13).
  document.body.addEventListener('focusin', e => {
    const t = e.target.closest('[data-tip]');
    if (t) { huidige = t; toon(t); }
  });
  document.body.addEventListener('focusout', e => {
    if (e.target.closest('[data-tip]')) { verberg(); huidige = null; }
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') { verberg(); huidige = null; } });
});

/* ── Flash berichten ────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  // Meldingen boven de pagina blijven staan: wie traag leest of een schermlezer gebruikt,
  // mag ze niet missen (WCAG 2.2.1). Ze verdwijnen bij de volgende pagina vanzelf.
  // Oude links met ?panel=<id> (bladwijzers, mails) gaan naar het dossier.
  const panelId = new URLSearchParams(window.location.search).get('panel');
  if (panelId && /^\d+$/.test(panelId)) openPaneel(panelId);
});
</script>
<script src="<?= APP_URL ?>/assets/js/actie.js?v=<?= @filemtime(__DIR__.'/../assets/js/actie.js') ?>"></script>
<script src="<?= APP_URL ?>/assets/js/app.js?v=<?= @filemtime(__DIR__.'/../assets/js/app.js') ?>"></script>
<script src="<?= APP_URL ?>/assets/js/editor.js?v=<?= @filemtime(__DIR__.'/../assets/js/editor.js') ?>"></script>
<script src="<?= APP_URL ?>/assets/js/beheer.js?v=<?= @filemtime(__DIR__.'/../assets/js/beheer.js') ?>"></script>
<script src="<?= APP_URL ?>/assets/js/inklap.js?v=<?= @filemtime(__DIR__.'/../assets/js/inklap.js') ?>"></script>
<?php require __DIR__ . '/_rondleiding.php'; ?>
</body>
</html>