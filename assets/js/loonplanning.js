/**
 * Paal 13 — Loonplanning (admin/loonplanning.php).
 *
 * Klik op een week → zijpaneel met de dagen van die week (uit de opgave), plannen, dagen,
 * betaald. Klik op een naam → de persoon: functie, afspraak, slot. Elke wijziging slaat meteen
 * op; het antwoord brengt de cellen van die persoon, de totalen en de weektellingen terug,
 * zodat het scherm nooit iets anders zegt dan de server.
 */
(function () {
  'use strict';
  var raster = document.querySelector('[data-lp-raster]');
  var paneel = document.querySelector('[data-lp-paneel]');
  if (!raster || !paneel) return;
  var mag = raster.dataset.mag === '1', url = raster.dataset.endpoint, sc = raster.dataset.scenario;
  var DAG = ['', 'ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'];
  var huidig = null;           // {sol, week, cel} of {sol, persoon: knop}
  var terugFocus = null;

  function $(sel) { return paneel.querySelector(sel); }
  function fout(t) { if (window.bhToast) window.bhToast(t, 'fout'); }
  function geld(n) { return '€ ' + Number(n).toLocaleString('nl-NL', { maximumFractionDigits: 0 }); }
  function initialen(naam) { return naam.split(' ').filter(Boolean).map(function (d) { return d[0]; }).slice(0, 2).join('').toUpperCase(); }
  function persoonKnop(sol) { return raster.querySelector('[data-lp-persoon="' + sol + '"]'); }
  function isVast(sol) { var k = persoonKnop(sol); return !!(k && k.dataset.vast); }

  // ── Paneel openen / sluiten ──
  function open(stand, sol, sub) {
    var k = persoonKnop(sol);
    var naam = k ? k.dataset.naam : '';
    $('[data-lp-naam]').textContent = naam;
    $('[data-lp-initialen]').textContent = initialen(naam);
    $('[data-lp-sub]').textContent = sub;
    paneel.querySelectorAll('[data-lp-stand]').forEach(function (s) { s.hidden = s.dataset.lpStand !== stand; });
    $('[data-lp-vastmelding]').hidden = !isVast(sol);
    terugFocus = document.activeElement;
    paneel.hidden = false;
    document.body.classList.add('bh-paneel-open');
    $('[data-lp-sluit]').focus();
  }
  function sluit() {
    paneel.hidden = true; huidig = null;
    document.body.classList.remove('bh-paneel-open');
    if (terugFocus) terugFocus.focus();
  }
  paneel.addEventListener('click', function (e) { if (e.target === paneel || e.target.closest('[data-lp-sluit]')) sluit(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !paneel.hidden) sluit(); });

  // ── Week ──
  function weekOpen(cel) {
    var sol = cel.dataset.sol, wk = cel.dataset.week;
    huidig = { sol: sol, week: wk, cel: cel };
    open('week', sol, 'week ' + wk);
    $('[data-lp-voornaam]').textContent = (persoonKnop(sol).dataset.naam || '').split(' ')[0];
    $('[data-lp-dagen]').innerHTML = '<li class="bh-meta">Laden…</li>';
    $('[data-lp-redenen]').innerHTML = '';
    weekVelden();
    fetch(url + '?dagen=1&scenario=' + sc + '&sol_id=' + sol + '&week=' + wk, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d.ok && huidig && huidig.cel === cel) weekDagen(d); })
      .catch(function () { $('[data-lp-dagen]').innerHTML = '<li class="bh-meta">Ophalen lukte niet.</li>'; });
  }

  /** De invoer volgt de cel (data-attributen): die is na elke opslag bijgewerkt. */
  function weekVelden() {
    var c = huidig.cel.dataset, kan = mag && !isVast(huidig.sol);
    var op = $('[data-lp-veld="opgen"]'), bt = $('[data-lp-veld="betaald"]');
    op.checked = c.opgen === '1'; bt.checked = c.betaald !== '0';
    $('[data-lp-dagenwaarde]').textContent = c.opgen === '1' ? c.dagen : 0;
    op.disabled = !kan; bt.disabled = !kan || !op.checked;
    $('[data-lp-min]').disabled = $('[data-lp-plus]').disabled = !kan || !op.checked;
    paneel.querySelectorAll('[data-lp-alleenbewerken]').forEach(function (b) { b.hidden = !kan; });
    var max = parseInt(c.max, 10) || 0, dg = parseInt(c.dagen, 10) || 0;
    $('[data-lp-opgave]').textContent = max ? 'Opgegeven: hooguit ' + max + (max === 1 ? ' dag' : ' dagen') + ' deze week.' : 'Deze week is niet opgegeven.';
    var s = [];
    if (c.opgen === '1' && dg > 0 && (max === 0 || dg > max)) s.push(max === 0 ? 'Gepland in een week die niet is opgegeven: bespreek dit met de sollicitant.' : 'Meer dagen dan opgegeven (' + max + '): bespreek dit met de sollicitant.');
    $('[data-lp-signalen]').textContent = s.join(' ');
  }

  function weekDagen(d) {
    var lijst = $('[data-lp-dagen]'); lijst.innerHTML = '';
    var alle = [];
    d.beschikbaar.forEach(function (x) { alle.push([x, 'ja', 'kan']); });
    d.liever_niet.forEach(function (x) { alle.push([x, 'liever', 'liever niet']); });
    d.niet.forEach(function (x) { alle.push([x.datum, 'nee', 'kan niet']); });
    alle.sort(function (a, b) { return a[0] < b[0] ? -1 : 1; });
    alle.forEach(function (a) {
      var dt = new Date(a[0] + 'T12:00:00');
      var li = document.createElement('li');
      li.className = 'bh-lp-dag ' + a[1];
      li.innerHTML = '<strong></strong><span></span><em></em>';
      li.children[0].textContent = DAG[dt.getDay() === 0 ? 7 : dt.getDay()];
      li.children[1].textContent = dt.getDate();
      li.children[2].textContent = a[2];
      lijst.appendChild(li);
    });
    var red = $('[data-lp-redenen]'); red.innerHTML = '';
    var gezien = {};
    d.niet.forEach(function (n) {
      if (!n.reden || gezien[n.reden]) return;
      gezien[n.reden] = 1;
      var li = document.createElement('li'); li.textContent = n.reden; red.appendChild(li);
    });
    if (d.signalen_tekst && d.signalen_tekst.length) $('[data-lp-signalen]').textContent = d.signalen_tekst.join('. ') + '.';
  }

  function celOpslaan(extra) {
    var c = huidig.cel.dataset;
    var op = $('[data-lp-veld="opgen"]').checked, bt = $('[data-lp-veld="betaald"]').checked;
    var dg = parseInt($('[data-lp-dagenwaarde]').textContent, 10) || 0;
    if (op && dg === 0) dg = Math.min(5, parseInt(c.max, 10) || 5);
    if (!op) dg = 0;
    bewaar(Object.assign({ sol_id: c.sol, week_nr: c.week, dagen: dg, opgenomen: op ? '1' : '0', betaald: bt ? '1' : '0' }, extra || {}));
  }

  paneel.addEventListener('change', function (e) {
    if (!huidig) return;
    if (e.target.matches('[data-lp-veld]')) {
      if (e.target.dataset.lpVeld === 'opgen' && e.target.checked && $('[data-lp-dagenwaarde]').textContent === '0') {
        $('[data-lp-dagenwaarde]').textContent = Math.min(5, parseInt(huidig.cel.dataset.max, 10) || 5);
      }
      celOpslaan();
    }
    if (e.target.matches('[data-lp-functie]')) bewaar({ wat: 'functie', sol_id: huidig.sol, functie: e.target.value });
  });
  paneel.addEventListener('click', function (e) {
    if (!huidig || !huidig.cel) return;
    var w = $('[data-lp-dagenwaarde]'), n = parseInt(w.textContent, 10) || 0;
    if (e.target.closest('[data-lp-min]') && n > 1) { w.textContent = n - 1; celOpslaan(); }
    if (e.target.closest('[data-lp-plus]') && n < 7) { w.textContent = n + 1; celOpslaan(); }
    if (e.target.closest('[data-lp-kopieer]')) {
      var dg = parseInt(w.textContent, 10) || 0;
      bewaar({ wat: 'kopieer', sol_id: huidig.sol, week_nr: huidig.week, dagen: dg, betaald: $('[data-lp-veld="betaald"]').checked ? '1' : '0' },
             function () { if (window.bhToast) window.bhToast('Gekopieerd naar de volgende opgegeven weken.'); });
    }
  });

  // ── Persoon ──
  function persoonOpen(knop) {
    var sol = knop.dataset.lpPersoon;
    huidig = { sol: sol, persoon: knop };
    open('persoon', sol, knop.dataset.fase);
    var fn = $('[data-lp-functie]'); fn.value = knop.dataset.functie; fn.disabled = !mag || isVast(sol);
    var tot = raster.querySelector('[data-lp-tot="' + sol + '"]');
    $('[data-lp-totaal]').textContent = tot ? tot.textContent.replace(/\s+/g, ' ').trim() + ' in dit scenario' : '';
    $('[data-lp-dossier]').href = raster.dataset.dossier + sol;
    paneel.querySelectorAll('input[name="sol_id"]').forEach(function (i) { i.value = sol; });
    var ab = $('[data-lp-afspraakblok]');
    if (ab) {
      var defi = raster.dataset.definitief === '1', af = knop.dataset.afspraak;
      ab.hidden = !defi;
      $('[data-lp-afspraaktekst]').textContent = af === 'ja' ? 'Vastgesteld; komt overeen met dit scenario.'
        : (af === 'wijkt_af' ? 'Vastgesteld, maar dit scenario wijkt ervan af.' : 'Nog geen afspraak vastgesteld.');
      $('[data-lp-afspraakknop]').textContent = af ? 'Afspraak bijwerken uit dit scenario' : 'Afspraak vastleggen uit dit scenario';
      $('[data-lp-afspraakform]').hidden = af === 'ja';
    }
    var vast = knop.dataset.vast;
    var st = $('[data-lp-slottekst]');
    if (st) {
      st.textContent = vast ? 'Vastgezet op ' + vast + ', in alle scenario\'s.' : 'Nog niet vast. Na aannemen in het TW-gesprek gebeurt dit vanzelf.';
      $('[data-lp-vastzetform]').hidden = !!vast;
      $('[data-lp-ontgrendelform]').hidden = !vast;
    }
  }

  // ── Opslaan en bijwerken ──
  function bewaar(velden, klaar) {
    var body = new URLSearchParams(Object.assign({ ajax_loon: '1', scenario: sc, _csrf: raster.dataset.csrf }, velden));
    fetch(url + '?scenario=' + sc, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) throw new Error(d.fout || 'Opslaan lukte niet.');
        werkBij(d);
        if (klaar) klaar();
      })
      .catch(function (e) { fout(e.message); if (huidig && huidig.cel) weekVelden(); });
  }

  function werkBij(d) {
    var p = d.persoon;
    if (p) {
      Object.keys(p.cellen).forEach(function (wk) {
        var c = p.cellen[wk], el = raster.querySelector('[data-lp-cel="' + p.sol_id + '-' + wk + '"]');
        if (!el) return;
        el.className = 'bh-weekcel bh-weekcel-' + c.soort + ' bh-weekcel-klik';
        el.title = c.tip; el.setAttribute('aria-label', c.tip);
        el.textContent = c.tekst;
        if (c.sub) { var s = document.createElement('span'); s.className = 'bh-weekcel-sub'; s.textContent = c.sub; el.appendChild(s); }
        el.dataset.opgen = c.opgenomen; el.dataset.dagen = c.dagen; el.dataset.betaald = c.betaald; el.dataset.max = c.max;
      });
      var tot = raster.querySelector('[data-lp-tot="' + p.sol_id + '"]');
      if (tot) { tot.children[0].textContent = p.dagen + ' d'; tot.children[1].textContent = geld(p.kosten); }
      var k = persoonKnop(p.sol_id);
      if (k) { k.dataset.functie = p.functie; if (p.wijkt_af && k.dataset.afspraak) k.dataset.afspraak = 'wijkt_af'; }
    }
    Object.keys(d.tot_per_week || {}).forEach(function (wk) {
      var td = raster.querySelector('[data-lp-weektot="' + wk + '"]'), t = d.tot_per_week[wk];
      if (td) { td.firstChild.nodeValue = t.aantal; td.querySelector('.bh-raster-totwk').textContent = ' / ' + t.weekend; }
      var kt = raster.querySelector('[data-lp-weekkosten="' + wk + '"]'), kos = d.per_week[wk] ? d.per_week[wk].kosten : 0;
      if (kt) kt.textContent = kos > 0 ? '€ ' + (kos / 1000).toLocaleString('nl-NL', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + 'k' : '–';
    });
    var kop = document.querySelector('[data-lp-kop]');
    if (kop && d.totaal) {
      kop.querySelector('[data-lp="kosten"]').textContent = geld(d.totaal.kosten);
      kop.querySelector('[data-lp="dagen"]').textContent = d.totaal.dagen;
      kop.querySelector('[data-lp="onbetaald"]').textContent = d.totaal.dagen_onbetaald ? 'waarvan ' + d.totaal.dagen_onbetaald + ' onbetaald' : '';
      var budget = parseFloat(kop.dataset.budget) || 0, vul = kop.querySelector('[data-lp="budgetvul"]');
      if (budget > 0 && vul) {
        var pct = Math.min(100, Math.round(d.totaal.kosten / budget * 100));
        var kleur = d.totaal.over_budget ? 'rood' : (pct > 85 ? 'oranje' : 'groen');
        vul.style.setProperty('--w', pct + '%');
        vul.className = 'bh-lp-budgetvul ds-fase-' + kleur;
        var zin = kop.querySelector('[data-lp="budgetzin"] .bh-stoplicht');
        if (zin) {
          zin.className = 'bh-stoplicht ds-fase-' + kleur;
          zin.lastChild.nodeValue = d.totaal.over_budget ? 'Over budget: ' + geld(Math.abs(d.totaal.restant)) : 'Nog ' + geld(d.totaal.restant) + ' over';
        }
      }
    }
    if (huidig && huidig.cel) weekVelden();
  }

  raster.addEventListener('click', function (e) {
    var cel = e.target.closest('[data-lp-cel]');
    if (cel) { weekOpen(cel); return; }
    var p = e.target.closest('[data-lp-persoon]');
    if (p) persoonOpen(p);
  });

  // Vanuit het dossier (?persoon=<sol_id>): meteen het paneel van die persoon open.
  var vanuit = new URLSearchParams(location.search).get('persoon');
  if (vanuit && /^\d+$/.test(vanuit) && persoonKnop(vanuit)) persoonOpen(persoonKnop(vanuit));
})();
