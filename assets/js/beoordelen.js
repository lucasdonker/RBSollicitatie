/**
 * Paal 13 — Beoordelen op de selectiedag (admin/selectiedag.php) en het trainingsweekend
 * (admin/tw/beoordelen.php). Eén script, omdat een begeleider op beide dagen hetzelfde doet.
 *
 * Verwacht een element [data-beoordeel] met data-endpoint en data-csrf. Daarbinnen:
 *   .bh-cijfers[data-test][data-crit] > button[data-cijfer]   tikken ís opslaan (alleen selectiedag)
 *   [data-obs-blok][data-sol]                                 observatie-invoer (admin/_observatie_invoer.php)
 *   [data-lijstknop]                                          kandidatenlijst in-/uitklappen op een telefoon
 *
 * Geen bewaarknop voor een cijfer en geen herladen na een observatie: je staat buiten met een
 * telefoon in je hand. Tik je met tekst in het veld naar iemand anders, dan wordt die tekst eerst
 * opgeslagen — anders ben je hem kwijt.
 */
(function () {
  'use strict';
  var wortel = document.querySelector('[data-beoordeel]');
  if (!wortel) return;

  function post(velden) {
    var body = new URLSearchParams(Object.assign({ _csrf: wortel.dataset.csrf }, velden));
    return fetch(wortel.dataset.endpoint, {
      method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body
    }).then(function (r) { return r.json(); });
  }
  function toast(t, soort) { if (window.bhToast) window.bhToast(t, soort || 'fout'); }
  function tijd() { var d = new Date(); return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2); }

  /* ── Cijfers ─────────────────────────────────────────────────── */
  function testStand(blok) {
    var tel = blok && blok.querySelector('[data-test-stand]');
    if (!tel) return;
    var klaar = blok.querySelectorAll('.bh-criterium.gedaan').length;
    var tot = blok.querySelectorAll('.bh-criterium').length;
    tel.textContent = klaar + ' van ' + tot + ' gescoord';
    tel.classList.toggle('nog', klaar < tot);
  }

  function zetScore(knop) {
    var rij = knop.closest('.bh-cijfers');
    var crit = knop.closest('.bh-criterium');
    var kaart = knop.closest('[data-sol]');
    if (!rij || !crit || !kaart) return;
    var vorige = rij.querySelector('.gekozen');
    var stand = crit.querySelector('.bh-criterium-stand');
    var gem = crit.querySelector('.bh-criterium-gem');
    var wissen = knop === vorige;   // nogmaals op het gekozen cijfer tikken wist je score

    function toon(actief) {
      rij.querySelectorAll('[data-cijfer]').forEach(function (b) {
        b.classList.toggle('gekozen', b === actief);
        b.setAttribute('aria-pressed', b === actief ? 'true' : 'false');
      });
      crit.classList.toggle('gedaan', !!actief);
      testStand(rij.closest('.bh-testblok'));
    }

    toon(wissen ? null : knop);
    if (stand) stand.textContent = wissen ? 'wissen…' : 'opslaan…';

    post({ actie: 'score', sol_id: kaart.dataset.sol, test: rij.dataset.test, criterium: rij.dataset.crit,
           score: wissen ? 0 : knop.dataset.cijfer })
      .then(function (d) {
        if (!d.ok) throw new Error(d.fout || 'mislukt');
        if (stand) stand.textContent = wissen ? 'gewist' : 'opgeslagen';
        if (gem) gem.textContent = d.aantal > 1 ? 'gemiddelde van ' + d.aantal + ' beoordelaars: ' + d.gem : '';
      })
      .catch(function () {
        toon(vorige);
        if (stand) stand.textContent = wissen ? 'niet gewist' : 'niet opgeslagen';
        toast('Cijfer niet opgeslagen — probeer het opnieuw.');
      });
  }

  /* ── Observaties ─────────────────────────────────────────────── */
  var obs = wortel.querySelector('[data-obs-blok]');
  var veld = obs && obs.querySelector('[data-obs-tekst]');
  var conceptSleutel = obs ? 'p13-obs-concept-' + wortel.dataset.endpoint + '-' + obs.dataset.sol : '';

  function concept(tekst) {
    try {
      if (tekst) sessionStorage.setItem(conceptSleutel, tekst); else sessionStorage.removeItem(conceptSleutel);
    } catch (e) { /* geen opslag: dan geen concept */ }
  }
  // Telefoon = chatweergave (CSS ≤560px): het veld groeit mee met de tekst, en na versturen staat
  // de nieuwste observatie in beeld, net boven de invoerbalk.
  var chat = window.matchMedia ? window.matchMedia('(max-width: 560px)') : { matches: false };
  function groei() {
    if (!veld || !chat.matches) { if (veld) veld.style.height = ''; return; }
    veld.style.height = 'auto';
    veld.style.height = Math.min(veld.scrollHeight + 2, 160) + 'px';
  }
  function naarNieuwste() {
    if (!chat.matches || !obs) return;
    var lijst = obs.querySelector('[data-obs-lijst]');
    if (lijst) lijst.scrollIntoView({ block: 'end', behavior: 'smooth' });
  }
  if (veld) {
    try { var c = sessionStorage.getItem(conceptSleutel); if (c && !veld.value) veld.value = c; } catch (e) {}
    if (chat.matches) veld.rows = 1;
    groei();
    veld.addEventListener('input', function () { concept(veld.value.trim() ? veld.value : ''); groei(); });
    veld.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); obsOpslaan(); }
    });
  }

  function kiesSoort(knop) {
    var invoer = knop.closest('.bh-obsinvoer');
    invoer.querySelectorAll('[data-soort]').forEach(function (b) {
      var aan = b === knop;
      b.classList.toggle('gekozen', aan);
      b.setAttribute('aria-pressed', aan ? 'true' : 'false');
    });
    invoer.dataset.soort = knop.dataset.soort;
  }

  /** Slaat de tekst op; geeft een Promise<bool>. De tekst blijft staan als het misgaat. */
  function obsOpslaan() {
    if (!veld) return Promise.resolve(true);
    var tekst = veld.value.trim();
    if (!tekst) { toast('Schrijf eerst iets op.'); veld.focus(); return Promise.resolve(false); }
    var invoer = obs.querySelector('.bh-obsinvoer');
    var knop = obs.querySelector('[data-obs-opslaan]');
    var stand = obs.querySelector('[data-obs-stand]');
    var klaar = window.P13Actie ? window.P13Actie.bezig(invoer, knop) : function () {};
    return post({ actie: 'observatie', sol_id: obs.dataset.sol, tekst: tekst, soort: invoer.dataset.soort || 'neutraal' })
      .then(function (d) {
        klaar();
        if (!d.ok) throw new Error();
        veld.value = '';
        concept('');
        groei();
        if (typeof d.html === 'string') {
          var lijst = obs.querySelector('[data-obs-lijst]');
          lijst.innerHTML = d.html;
          if (window.P13Klap) window.P13Klap.koppel(lijst);
        }
        naarNieuwste();
        var neutraal = invoer.querySelector('[data-soort="neutraal"]');
        if (neutraal) kiesSoort(neutraal);
        if (stand) { stand.textContent = 'Opgeslagen om ' + tijd() + '.'; stand.classList.add('ok'); }
        return true;
      })
      .catch(function () {
        klaar();
        toast('Opslaan lukte niet — je tekst staat er nog. Probeer het opnieuw.');
        return false;
      });
  }

  function obsWis(knop) {
    window.bhBevestig({ titel: 'Observatie verwijderen?', tekst: 'Alleen deze observatie; die van anderen blijven staan.',
                        knop: 'Ja, verwijderen', gevaar: true })
      .then(function (ja) {
        if (!ja) return;
        post({ actie: 'observatie_wis', id: knop.dataset.obsWis }).then(function (d) {
          if (!d.ok) throw new Error();
          var li = knop.closest('li');
          var dag = li && li.closest('.bh-obsdag');
          if (li) li.remove();
          if (dag && !dag.querySelector('li')) dag.remove();
          var lijst = obs && obs.querySelector('[data-obs-lijst]');
          if (lijst && !lijst.querySelector('li')) lijst.innerHTML = '<p class="bh-meta">Nog niets opgeschreven.</p>';
        }).catch(function () { toast('Verwijderen lukte niet.'); });
      });
  }

  wortel.addEventListener('click', function (e) {
    var cijfer = e.target.closest('[data-cijfer]');
    if (cijfer && !cijfer.disabled) { zetScore(cijfer); return; }
    var soort = e.target.closest('.bh-obsinvoer [data-soort]');
    if (soort) { kiesSoort(soort); return; }
    if (e.target.closest('[data-obs-opslaan]')) { obsOpslaan(); return; }
    var wis = e.target.closest('[data-obs-wis]');
    if (wis) { obsWis(wis); return; }
    var lijstknop = e.target.closest('[data-lijstknop]');
    if (lijstknop) {
      var lijst = lijstknop.closest('.bh-beoordeel-lijst');
      var dicht = lijst.classList.toggle('dicht');
      lijstknop.setAttribute('aria-expanded', dicht ? 'false' : 'true');
    }
  });

  // Doorklikken (volgende, een naam, een stap) met tekst in het veld: eerst opslaan, dan pas weg.
  // In de capture-fase, zodat dit vóór de laadbalk van actie.js komt.
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[href]');
    if (!a || !veld || !veld.value.trim() || e.defaultPrevented || e.button !== 0) return;
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || (a.target && a.target !== '_self')) return;
    var href = a.getAttribute('href') || '';
    if (!href || href[0] === '#') return;
    e.preventDefault();
    obsOpslaan().then(function (ok) { if (ok) window.location.href = a.href; });
  }, true);

  // Op een telefoon staat de kandidatenlijst dicht: de persoon die je beoordeelt is waar het om gaat.
  var knop = wortel.querySelector('[data-lijstknop]');
  if (knop && window.matchMedia && window.matchMedia('(max-width: 900px)').matches) {
    knop.closest('.bh-beoordeel-lijst').classList.add('dicht');
    knop.setAttribute('aria-expanded', 'false');
  }
})();
