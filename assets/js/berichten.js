/**
 * Paal 13 — Berichten › Nieuw bericht (admin/berichten.php). Stond eerst in de pagina zelf.
 *
 *  - Aan: de vinkjes in de lijst zijn de stand; het zoekveld met chips is daar een weergave van.
 *  - Hoe: WhatsApp/sms, e-mail of beide. Alleen de velden die bij het kanaal horen zijn zichtbaar
 *    ([data-kanaal="kort beide"]); zonder JavaScript staan ze er allemaal.
 *  - Bericht: opmaakknoppen voor de korte tekst (WhatsApp-notatie), teller met sms-delen, een live
 *    voorbeeld van de WhatsApp-bubbel, en hoeveel ontvangers het per e-mail krijgen.
 * Functies die de pagina via data-klik/data-wijzig aanroept, staan op window.
 */

function berAantal() { return document.querySelectorAll('.ontv-cb:checked').length; }

function updateTeller() {
  var n = berAantal();
  var el = document.getElementById('verzendTeller');
  if (el) el.textContent = n === 0 ? 'niemand' : (n + ' ' + (n === 1 ? 'persoon' : 'personen'));
  aanTeken();
  terugvalTeller();
}
function selGroep(f, aan) {
  document.querySelectorAll('.fase-' + f).forEach(function (c) { c.checked = aan; });
  updateTeller();
}
function selAlles(aan) {
  document.querySelectorAll('.ontv-cb,.groep-cb').forEach(function (c) { c.checked = aan; });
  updateTeller();
}

/* ── Het "Aan"-veld ─────────────────────────────────────────────── */
var aanMarkeer = -1;                        // welke suggestie met de pijltjes is aangewezen

function aanAan() {
  var veld = document.getElementById('aanVeld');
  if (!veld || !document.querySelector('.ontv-cb')) return;
  veld.hidden = false;
  document.getElementById('aanAlles').open = false;
  aanTeken();
}

function aanTeken() {
  var vak = document.getElementById('aanVak');
  if (!vak || document.getElementById('aanVeld').hidden) return;
  vak.querySelectorAll('.bh-aanchip').forEach(function (c) { c.remove(); });
  var zoek = document.getElementById('aanZoek');
  document.querySelectorAll('.ontv-cb:checked').forEach(function (cb) {
    var chip = document.createElement('span');
    chip.className = 'bh-aanchip';
    chip.textContent = cb.dataset.naam || '';
    var weg = document.createElement('button');
    weg.type = 'button';
    weg.className = 'bh-aanchip-weg';
    weg.setAttribute('aria-label', 'Haal ' + (cb.dataset.naam || 'deze persoon') + ' weg');
    weg.textContent = '\u00d7';
    weg.addEventListener('click', function (e) { e.stopPropagation(); cb.checked = false; updateTeller(); });
    chip.appendChild(weg);
    vak.insertBefore(chip, zoek);
  });
  zoek.placeholder = vak.querySelector('.bh-aanchip') ? '' : 'naam typen\u2026';
}

function aanFocus(e) {
  if (e.target.closest('.bh-aanchip')) return;
  document.getElementById('aanZoek').focus();
}

function aanZoeken() {
  var zoek = document.getElementById('aanZoek');
  var lijst = document.getElementById('aanLijst');
  var leeg = document.getElementById('aanLeeg');
  var term = zoek.value.trim().toLowerCase();
  lijst.innerHTML = '';
  aanMarkeer = -1;
  if (!term) { aanSluit(); return; }

  var treffers = [];
  document.querySelectorAll('.ontv-cb').forEach(function (cb) {
    if (cb.checked || treffers.length >= 8) return;
    if ((cb.dataset.zoek || '').indexOf(term) !== -1) treffers.push(cb);
  });
  treffers.forEach(function (cb, i) {
    var li = document.createElement('li');
    li.className = 'bh-aansug';
    li.id = 'aansug-' + i;
    li.setAttribute('role', 'option');
    li.setAttribute('aria-selected', 'false');
    li.tabIndex = -1;
    var naam = document.createElement('span');
    naam.className = 'bh-aansug-naam';
    naam.textContent = cb.dataset.naam || '';
    var fase = document.createElement('span');
    fase.className = 'bh-aansug-fase';
    fase.textContent = cb.dataset.fase || '';
    li.appendChild(naam); li.appendChild(fase);
    li.addEventListener('click', function () { aanKies(cb); });
    lijst.appendChild(li);
  });
  lijst.hidden = treffers.length === 0;
  leeg.hidden = treffers.length !== 0;
  zoek.setAttribute('aria-expanded', treffers.length ? 'true' : 'false');
}

function aanKies(cb) {
  cb.checked = true;
  var zoek = document.getElementById('aanZoek');
  zoek.value = '';
  aanSluit();
  updateTeller();
  zoek.focus();
}

function aanSluit() {
  var lijst = document.getElementById('aanLijst');
  if (!lijst) return;
  lijst.hidden = true; lijst.innerHTML = '';
  document.getElementById('aanLeeg').hidden = true;
  document.getElementById('aanZoek').setAttribute('aria-expanded', 'false');
  aanMarkeer = -1;
}

function aanToets(e) {
  var opties = document.querySelectorAll('#aanLijst .bh-aansug');
  var zoek = e.target;
  /* Backspace in een leeg zoekveld haalt de laatste chip weg — hetzelfde gebaar als in elke mailapp. */
  if (e.key === 'Backspace' && !zoek.value) {
    var gekozen = document.querySelectorAll('.ontv-cb:checked');
    if (gekozen.length) { gekozen[gekozen.length - 1].checked = false; updateTeller(); }
    return;
  }
  if (e.key === 'Escape') { zoek.value = ''; aanSluit(); return; }
  if (!opties.length) return;
  if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
    e.preventDefault();
    aanMarkeer += (e.key === 'ArrowDown' ? 1 : -1);
    if (aanMarkeer < 0) aanMarkeer = opties.length - 1;
    if (aanMarkeer >= opties.length) aanMarkeer = 0;
    opties.forEach(function (o, i) {
      var aan = i === aanMarkeer;
      o.classList.toggle('aangewezen', aan);
      o.setAttribute('aria-selected', aan ? 'true' : 'false');
    });
    zoek.setAttribute('aria-activedescendant', opties[aanMarkeer].id);
    return;
  }
  if (e.key === 'Enter') {
    e.preventDefault();                     // anders verstuurt Enter het formulier
    opties[aanMarkeer < 0 ? 0 : aanMarkeer].click();
  }
}

document.addEventListener('click', function (e) {
  if (!e.target.closest('.bh-aanveld')) aanSluit();
});

/* ── Kanaal: alleen zien wat nodig is ───────────────────────────── */
function kanaalGekozen() {
  var k = document.querySelector('input[name=kanaalmodus]:checked');
  return k ? k.value : 'kort';
}

function kanaalWissel() {
  var k = kanaalGekozen();
  var sjabloon = document.getElementById('sjabloonSel');
  var eigen = !sjabloon || !sjabloon.value;
  document.querySelectorAll('[data-kanaal]').forEach(function (el) {
    el.hidden = (el.dataset.kanaal || '').split(' ').indexOf(k) === -1;
  });
  var form = document.getElementById('berichtForm');
  if (form) form.dataset.kanaalForm = k;
  if (eigen) terugvalTeller();
}

/** Hoeveel gekozen ontvangers krijgen het per e-mail (geen telefoon of liever e-mail)? */
function terugvalTeller() {
  var el = document.getElementById('terugvalTelling');
  if (!el) return;
  var gekozen = document.querySelectorAll('.ontv-cb:checked');
  var mail = [].filter.call(gekozen, function (cb) { return cb.dataset.kort !== '1'; }).length;
  var k = kanaalGekozen();
  if (k === 'email' || !gekozen.length || !mail) { el.textContent = ''; return; }
  el.textContent = mail + ' van ' + gekozen.length + ' ' + (mail === 1 ? 'krijgt' : 'krijgen')
    + ' het per e-mail: geen telefoonnummer, of liever alleen e-mail.';
}

function sjabloonWissel() {
  var s = document.getElementById('sjabloonSel');
  var eigen = !s || !s.value;
  document.getElementById('eigenInhoud').hidden = !eigen;
  document.getElementById('sjabloonInfo').hidden = eigen;
}

/* ── Korte tekst: opmaak, teller en voorbeeld ───────────────────── */
function kortTekst() { return document.getElementById('tekstKort'); }

/** Opmaakknop: zet *…*, _…_ of ~…~ om de selectie, of maak van de regels een opsomming. */
function kortOpmaak(soort) {
  var t = kortTekst();
  if (!t) return;
  var a = t.selectionStart, b = t.selectionEnd, v = t.value, sel = v.slice(a, b);
  if (soort === 'lijst') {
    var begin = v.lastIndexOf('\n', a - 1) + 1;
    var blok = v.slice(begin, b) || '';
    var regels = (blok || 'eerste punt').split('\n').map(function (r) { return /^\s*[-\u2022]\s/.test(r) ? r : '- ' + r; }).join('\n');
    t.setRangeText(regels, begin, b, 'end');
  } else if (soort === 'link') {
    t.setRangeText('{{portaal_url}}', a, b, 'end');
  } else {
    // Spaties aan de rand blijven buiten de tekens: "*belangrijk *" toont WhatsApp niet vet.
    var voor = (sel.match(/^\s*/) || [''])[0], na = (sel.match(/\s*$/) || [''])[0];
    var kern = sel.trim() || 'tekst';
    t.setRangeText(voor + soort + kern + soort + na, a, b, 'end');
    t.selectionStart = a + voor.length + 1; t.selectionEnd = t.selectionStart + kern.length;
  }
  t.focus();
  kortBijwerken();
}

/** Is dit een logica-tag ({{als \u2026}}, {{/kies}} \u2026) en geen variabele? */
var LOGICA_TAG = /^\{\{\s*\/?\s*(als|if|anders|else|elseif|elif|standaard|default|kies|switch|geval|case|einde|end|endif|endswitch)(?![\w.])/i;

/** Tekens en sms-delen (160 tekens, of 153 per deel als het er meer zijn). Met logica telt de
    langste variant; die rekent de server uit bij Voorbeeld en Versturen. */
function kortBijwerken() {
  var t = kortTekst(), teller = document.getElementById('kortTeller');
  if (!t) return;
  var n = t.value.length, max = t.dataset.kortMax || t.maxLength;
  var logica = (t.value.match(/\{\{[^{}\n]+\}\}/g) || []).some(function (s) { return LOGICA_TAG.test(s); });
  if (logica) {
    if (teller) teller.textContent = n + ' tekens met logica \u00b7 max ' + max + ' per variant, gecontroleerd bij Voorbeeld en Versturen';
  } else {
    var delen = n === 0 ? 0 : (n <= 160 ? 1 : Math.ceil(n / 153));
    if (teller) teller.textContent = n + ' van ' + max + ' tekens \u00b7 ' + delen + ' ' + (delen === 1 ? 'sms' : 'sms-delen');
  }
  kortVoorbeeld();
}

/** WhatsApp-bubbel: dezelfde notatie als op de telefoon, opgebouwd met tekstknopen (geen innerHTML). */
function kortVoorbeeld() {
  var t = kortTekst(), bubbel = document.getElementById('kortBubbel');
  if (!t || !bubbel) return;
  bubbel.textContent = '';
  var tekst = t.value.trim() || 'Hier zie je hoe je bericht eruitziet.';
  // Variabelen en logica eerst, zodat een _ in {{als formulier.dagen_open > 7}} geen cursief wordt.
  var patroon = /(\{\{[^{}\n]+\}\}|\*[^*\n]+\*|_[^_\n]+_|~[^~\n]+~)/gi;
  tekst.split('\n').forEach(function (regel, i) {
    if (i) bubbel.appendChild(document.createElement('br'));
    var laatst = 0, m;
    patroon.lastIndex = 0;
    while ((m = patroon.exec(regel))) {
      if (m.index > laatst) bubbel.appendChild(document.createTextNode(regel.slice(laatst, m.index)));
      var s = m[0], el;
      if (s[0] === '{') {
        el = document.createElement('mark');
        // Logica toont alle takken; wat één ontvanger krijgt zie je met de knop Voorbeeld.
        el.className = LOGICA_TAG.test(s) ? 'bh-logicamerk' : 'bh-varmerk';
        el.textContent = s;
      }
      else { el = document.createElement({ '*': 'strong', '_': 'em', '~': 's' }[s[0]]); el.textContent = s.slice(1, -1); }
      bubbel.appendChild(el);
      laatst = m.index + s.length;
    }
    if (laatst < regel.length) bubbel.appendChild(document.createTextNode(regel.slice(laatst)));
  });
}

document.addEventListener('click', function (e) {
  var k = e.target.closest('[data-opmaak]');
  if (k) kortOpmaak(k.dataset.opmaak);
});
document.addEventListener('input', function (e) {
  if (e.target.id === 'tekstKort') kortBijwerken();
});

/* Versturen is onomkeerbaar, dus er komt een bevestiging die zegt wát er gebeurt:
   hoeveel mensen en via welk kanaal. bhBevestig() is asynchroon, dus we houden de
   submit tegen en verzenden hem zelf zodra er ja is gezegd. */
function verzendBevestig(ev) {
  document.getElementById('actieVeld').value = 'verzend';
  var n = berAantal();
  if (!n) { bhToast('Kies eerst minstens \u00e9\u00e9n ontvanger.', 'fout'); return false; }
  if (ev && ev.target && ev.target.dataset.bevestigd === 'ja') return true;

  var keuze = document.querySelector('input[name=kanaalmodus]:checked');
  var label = keuze ? keuze.parentNode.querySelector('strong').textContent : 'WhatsApp/SMS';
  var knop  = ev && ev.target ? ev.target.closest('button') : document.getElementById('verzendKnop');
  var extra = document.getElementById('terugvalTelling');

  bhBevestig({
    titel: 'Versturen naar ' + n + ' ' + (n === 1 ? 'persoon' : 'personen') + '?',
    tekst: 'Het bericht gaat via ' + label + ' de deur uit en is daarna niet meer terug te halen. '
         + (extra && extra.textContent ? extra.textContent + ' ' : '')
         + 'Wie zich heeft afgemeld wordt overgeslagen.',
    knop: 'Ja, versturen'
  }).then(function (ja) {
    if (!ja) return;
    knop.dataset.bevestigd = 'ja';
    knop.click();
  });
  return false;
}

aanAan(); updateTeller(); sjabloonWissel(); kanaalWissel(); kortBijwerken();
