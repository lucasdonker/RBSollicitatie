/**
 * Paal 13 — rondleidingen door het beheer.
 *
 * Leest <script type="application/json" id="rl-data"> (admin/_rondleiding.php) en wijst
 * stap voor stap een element op het échte scherm aan. De inhoud en de rechten komen
 * van de server (includes/beheer/rondleiding.php); hier zit alleen het tonen.
 *
 * Starten:
 *   - een route loopt via het adres: ?rondleiding=<id>&rl=<scherm>[e]   (e = laatste stap,
 *     voor "Vorige" terug naar het vorige scherm). Geen opslag nodig, dus het werkt ook in
 *     een privévenster en de terugknop van de browser doet wat je verwacht;
 *   - [data-rl-start="scherm"] start de rondleiding van het huidige scherm.
 *
 * Ontbreekt het element van een stap, of valt het buiten beeld (lege lijst, ingeklapt
 * menu op een telefoon), dan staat de stap als venster in het midden. Zo breekt een
 * rondleiding nooit op de toestand van de data.
 */
(function () {
  'use strict';

  var bron = document.getElementById('rl-data');
  if (!bron) return;
  var data;
  try { data = JSON.parse(bron.textContent); } catch (e) { return; }

  var MARGE = 12;
  var rustig = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var bestand = location.pathname.split('/').pop() || 'index.php';

  var staat = null;          // { route, s, i }
  var laag, spot, kaart, vorigeFocus, bezig = false;

  /* ── Opbouw ──────────────────────────────────────────────────── */

  function maak(tag, klasse, tekst) {
    var n = document.createElement(tag);
    if (klasse) n.className = klasse;
    if (tekst != null) n.textContent = tekst;
    return n;
  }

  function bouw() {
    if (kaart) return;
    laag = maak('div', 'bh-rl-laag');
    spot = maak('div', 'bh-rl-spot');
    spot.setAttribute('aria-hidden', 'true');
    kaart = maak('div', 'bh-rl-kaart');
    kaart.setAttribute('role', 'dialog');
    kaart.setAttribute('aria-modal', 'true');
    kaart.setAttribute('aria-labelledby', 'rl-titel');
    kaart.setAttribute('aria-describedby', 'rl-tekst');
    // Klik op de achtergrond stopt níet: je klikt makkelijk mis, en dan ben je je plek kwijt.
    laag.addEventListener('click', function () { var t = kaart.querySelector('#rl-titel'); if (t) t.focus(); });
    document.body.appendChild(laag);
    document.body.appendChild(spot);
    document.body.appendChild(kaart);
    document.addEventListener('keydown', toets, true);
    window.addEventListener('resize', herplaatsLater);
    window.addEventListener('scroll', herplaatsLater, true);
  }

  function breekAf() {
    document.removeEventListener('keydown', toets, true);
    window.removeEventListener('resize', herplaatsLater);
    window.removeEventListener('scroll', herplaatsLater, true);
    [laag, spot, kaart].forEach(function (n) { if (n && n.parentNode) n.parentNode.removeChild(n); });
    laag = spot = kaart = null;
    document.documentElement.classList.remove('bh-rl-actief');
  }

  /* ── Staat ───────────────────────────────────────────────────── */

  function scherm() { return staat.route.schermen[staat.s]; }
  function stap() { return scherm().stappen[staat.i]; }

  function start(route, s, i) {
    if (!route || !route.schermen || !route.schermen.length) return;
    vorigeFocus = document.activeElement;
    staat = { route: route, s: s, i: i };
    welkomWeg();
    bouw();
    document.documentElement.classList.add('bh-rl-actief');
    toon();
  }

  function stop() {
    if (!staat) return;
    staat = null;
    breekAf();
    // De route uit het adres halen: anders start hij opnieuw bij herladen.
    try {
      var p = new URLSearchParams(location.search);
      if (p.has('rondleiding') || p.has('rl')) {
        p.delete('rondleiding'); p.delete('rl');
        var q = p.toString();
        history.replaceState(null, '', location.pathname + (q ? '?' + q : '') + location.hash);
      }
    } catch (e) {}
    if (vorigeFocus && vorigeFocus.focus && document.contains(vorigeFocus)) vorigeFocus.focus();
  }

  /** Naar scherm s (eind = laatste stap). Zelfde bestand = ter plekke verder. */
  function naarScherm(s, eind) {
    var doel = staat.route.schermen[s];
    if (doel.bestand === bestand) {
      staat.s = s;
      staat.i = eind ? doel.stappen.length - 1 : 0;
      toon();
      return;
    }
    if (bezig) return;
    bezig = true;
    var url = doel.url + (doel.url.indexOf('?') === -1 ? '?' : '&')
            + 'rondleiding=' + encodeURIComponent(staat.route.id) + '&rl=' + s + (eind ? 'e' : '');
    if (window.P13Actie && window.P13Actie.bezig) window.P13Actie.bezig(null, null);
    location.href = url;
  }

  function volgende() {
    if (staat.i < scherm().stappen.length - 1) { staat.i++; toon(); return; }
    if (staat.s < staat.route.schermen.length - 1) { naarScherm(staat.s + 1, false); return; }
    klaar();
  }

  function vorige() {
    if (staat.i > 0) { staat.i--; toon(); return; }
    if (staat.s > 0) naarScherm(staat.s - 1, true);
  }

  function klaar() {
    var route = staat.route;
    if (route.enkel) { stop(); return; }
    // Eén afsluitend venster: wat nu, en waar je het terugvindt.
    staat.klaar = true;
    toon();
  }

  /* ── Tonen ───────────────────────────────────────────────────── */

  function knop(tekst, soort, fn) {
    var b = maak('button', 'ds-btn ds-btn-' + soort + ' ds-btn-sm', tekst);
    b.type = 'button';
    b.addEventListener('click', fn);
    return b;
  }

  function toon() {
    kaart.innerHTML = '';
    var route = staat.route;

    if (staat.klaar) {
      kaart.appendChild(maak('div', 'bh-rl-label', 'Rondleiding afgerond'));
      var kt = maak('h2', 'bh-rl-titel', route.titel + ': klaar');
      kt.id = 'rl-titel'; kt.tabIndex = -1;
      kaart.appendChild(kt);
      var kp = maak('p', 'bh-rl-tekst', route.afsluiting ||
        'Je kent nu de weg. Op elk scherm staat rechtsboven het vraagteken met uitleg en een rondleiding van dat scherm.');
      kp.id = 'rl-tekst';
      kaart.appendChild(kp);
      var kv = maak('div', 'bh-rl-voet');
      var terug = maak('a', 'ds-btn ds-btn-secundair ds-btn-sm', 'Alle rondleidingen');
      terug.href = data.overzicht;
      kv.appendChild(terug);
      kv.appendChild(maak('span', 'bh-rl-ruimte'));
      kv.appendChild(knop('Sluiten', 'primair', stop));
      kaart.appendChild(kv);
      plaats(null);
      kt.focus();
      return;
    }

    var sc = scherm(), st = stap();
    var aantalSchermen = route.schermen.length;
    var label = (route.enkel ? 'Rondleiding' : route.titel)
              + ' · ' + sc.titel
              + (aantalSchermen > 1 ? ' (' + (staat.s + 1) + '/' + aantalSchermen + ')' : '');
    kaart.appendChild(maak('div', 'bh-rl-label', label));

    var t = maak('h2', 'bh-rl-titel', st.titel);
    t.id = 'rl-titel'; t.tabIndex = -1;
    kaart.appendChild(t);
    var p = maak('p', 'bh-rl-tekst', st.tekst);
    p.id = 'rl-tekst';
    kaart.appendChild(p);

    if (staat.i === 0 && sc.lees) {
      var a = maak('a', 'bh-rl-lees', 'Lees meer in de handleiding');
      a.href = sc.lees; a.target = '_blank'; a.rel = 'noopener';
      kaart.appendChild(a);
    }

    // Stippen: hoe ver je in dít scherm bent. Het getal staat er ook, voor wie de stippen niet ziet.
    var voortgang = maak('div', 'bh-rl-stippen');
    voortgang.setAttribute('aria-label', 'Stap ' + (staat.i + 1) + ' van ' + sc.stappen.length);
    for (var n = 0; n < sc.stappen.length; n++) {
      var d = maak('span', n === staat.i ? 'nu' : (n < staat.i ? 'gedaan' : ''));
      d.setAttribute('aria-hidden', 'true');
      voortgang.appendChild(d);
    }
    voortgang.appendChild(maak('span', 'bh-rl-teller', (staat.i + 1) + '/' + sc.stappen.length));
    kaart.appendChild(voortgang);

    var v = maak('div', 'bh-rl-voet');
    v.appendChild(knop('Stoppen', 'secundair', stop));
    v.appendChild(maak('span', 'bh-rl-ruimte'));
    if (staat.i > 0 || staat.s > 0) v.appendChild(knop('Vorige', 'secundair', vorige));
    var laatsteStap = staat.i === sc.stappen.length - 1;
    var laatsteScherm = staat.s === aantalSchermen - 1;
    var verder = !laatsteStap ? 'Volgende'
               : !laatsteScherm ? 'Verder: ' + route.schermen[staat.s + 1].titel
               : (route.enkel ? 'Klaar' : 'Afronden');
    v.appendChild(knop(verder, 'primair', volgende));
    kaart.appendChild(v);

    plaats(zoek(st.doel));
    t.focus({ preventScroll: true });
  }

  /** Het element van een stap, maar alleen als het echt te zien is. */
  function zoek(doel) {
    if (!doel) return null;
    var el = null;
    try { el = document.querySelector(doel); } catch (e) { return null; }
    if (!el || el.closest('[hidden]')) return null;
    var r = el.getBoundingClientRect();
    if (r.width === 0 && r.height === 0) return null;
    return el;
  }

  var huidigDoel = null;

  function plaats(el) {
    huidigDoel = el;
    var smal = window.innerWidth < 640;
    kaart.classList.toggle('onder', smal);
    if (el) {
      el.scrollIntoView({ block: smal ? 'start' : 'center', inline: 'nearest', behavior: 'auto' });
      if (smal) window.scrollBy(0, -80);
    }
    herplaats();
  }

  function herplaats() {
    if (!kaart) return;
    var el = huidigDoel;
    var r = el ? el.getBoundingClientRect() : null;
    var vw = window.innerWidth, vh = window.innerHeight;
    // Na het scrollen nog steeds buiten beeld (bijv. het menu op een telefoon)? Dan midden.
    if (r && (r.bottom <= 0 || r.top >= vh || r.right <= 0 || r.left >= vw)) r = null;

    laag.classList.toggle('dim', !r);
    if (r) {
      spot.hidden = false;
      var top = Math.max(r.top - 6, 2), left = Math.max(r.left - 6, 2);
      spot.style.top = top + 'px';
      spot.style.left = left + 'px';
      spot.style.width = Math.min(r.width + 12, vw - left - 2) + 'px';
      spot.style.height = Math.min(r.height + 12, vh - top - 2) + 'px';
    } else {
      spot.hidden = true;
    }

    if (kaart.classList.contains('onder')) { kaart.style.top = kaart.style.left = ''; kaart.classList.remove('midden'); return; }

    var kw = kaart.offsetWidth, kh = kaart.offsetHeight;
    if (!r) {
      kaart.classList.add('midden');
      kaart.style.left = Math.max(16, (vw - kw) / 2) + 'px';
      kaart.style.top = Math.max(16, (vh - kh) / 2) + 'px';
      return;
    }
    kaart.classList.remove('midden');
    var y;
    if (r.bottom + MARGE + kh <= vh - 8) y = r.bottom + MARGE;          // eronder
    else if (r.top - MARGE - kh >= 8) y = r.top - MARGE - kh;           // erboven
    else y = Math.max(8, vh - kh - 16);                                  // groot element: onderin
    var x = Math.min(Math.max(16, r.left), vw - kw - 16);
    kaart.style.left = Math.max(16, x) + 'px';
    kaart.style.top = y + 'px';
  }

  var gepland = false;
  function herplaatsLater() {
    if (gepland) return;
    gepland = true;
    requestAnimationFrame(function () { gepland = false; herplaats(); });
  }

  function toets(e) {
    if (!staat) return;
    if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); stop(); return; }
    var inVeld = e.target && /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName);
    if (!inVeld && !staat.klaar && e.key === 'ArrowRight') { e.preventDefault(); volgende(); return; }
    if (!inVeld && !staat.klaar && e.key === 'ArrowLeft')  { e.preventDefault(); vorige(); return; }
    if (e.key === 'Tab') {
      // Focus blijft in de kaart: de rest van de pagina ligt achter de laag.
      var f = kaart.querySelectorAll('button, a[href]');
      if (!f.length) return;
      var eerste = f[0], laatste = f[f.length - 1];
      if (!kaart.contains(document.activeElement)) { e.preventDefault(); eerste.focus(); }
      else if (e.shiftKey && (document.activeElement === eerste || document.activeElement.id === 'rl-titel')) { e.preventDefault(); laatste.focus(); }
      else if (!e.shiftKey && document.activeElement === laatste) { e.preventDefault(); eerste.focus(); }
    }
  }

  /* ── Welkomstblok op Vandaag ─────────────────────────────────── */
  // Onthouden of iemand het weg heeft geklikt is een gemak per apparaat, geen gegeven:
  // lukt opslag niet (privévenster), dan tonen we het blok gewoon niet opnieuw.
  var WELKOM = 'p13-rl-welkom';
  function welkomWeg() {
    try { localStorage.setItem(WELKOM, 'weg'); } catch (e) {}
    var w = document.querySelector('[data-rl-welkom]');
    if (w) w.hidden = true;
  }
  (function welkom() {
    var w = document.querySelector('[data-rl-welkom]');
    if (!w) return;
    var weg = true;
    try { weg = localStorage.getItem(WELKOM) === 'weg'; } catch (e) { weg = true; }
    if (!weg) w.hidden = false;
    var x = w.querySelector('[data-rl-welkom-weg]');
    if (x) x.addEventListener('click', welkomWeg);
  })();

  /* ── Starters ────────────────────────────────────────────────── */

  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-rl-start="scherm"]');
    if (!b || !data.scherm) return;
    e.preventDefault();
    var hp = document.getElementById('hulppaneel'), hk = document.getElementById('hulpknop');
    if (hp) hp.hidden = true;
    if (hk) hk.setAttribute('aria-expanded', 'false');
    var sc = Object.assign({}, data.scherm, { bestand: bestand, url: location.href });
    start({ id: 'scherm', titel: sc.titel, enkel: true, schermen: [sc] }, 0, 0);
  });

  // Route uit het adres: ?rondleiding=bestuur&rl=3 (of 3e voor de laatste stap).
  if (data.route) {
    var p = new URLSearchParams(location.search);
    var m = /^(\d+)(e?)$/.exec(p.get('rl') || '0');
    var s = m ? parseInt(m[1], 10) : 0;
    var sc = data.route.schermen[s];
    // Klopt het scherm niet met waar we zijn (adres met de hand aangepast)? Zoek het op bestand.
    if (!sc || sc.bestand !== bestand) {
      s = -1;
      for (var k = 0; k < data.route.schermen.length; k++) {
        if (data.route.schermen[k].bestand === bestand) { s = k; break; }
      }
      m = null;
    }
    if (s >= 0) {
      var i = (m && m[2] === 'e') ? data.route.schermen[s].stappen.length - 1 : 0;
      start(data.route, s, i);
    }
  }

  // Van buitenaf te bedienen (en te testen): start(route, scherm, stap) en stop().
  window.P13Rondleiding = {
    start: function (route, s, i) { start(route, s || 0, i || 0); },
    stop: function () { stop(); },
    data: data
  };
})();
