/*
 * Paal 13 — Live bijwerken in het beheer (includes/live.php, admin/api_live.php).
 *
 * <main data-live="sd:12 sol:3" data-live-v='{"sd:12":4}' data-live-ik="7" data-live-url="…">
 * Elke 10 seconden (alleen als het tabblad zichtbaar is) vragen we de tellers op. Veranderde er
 * iets door een ánder (je eigen wijzigingen zie je al), dan:
 *   - blokken met data-live-blok en een id worden stil vervangen door die uit een vers opgehaalde
 *     pagina — behalve een blok waarin je aan het typen bent (dan volgt een melding);
 *   - zonder zulke blokken verschijnt onderin "X heeft iets gewijzigd" met Vernieuwen.
 * Er wordt nooit iets overschreven waar je mee bezig bent.
 *
 * Twee events over document:
 *   'p13:live-wijziging'  zodra een ander iets veranderde (vóór vervangen of melden): een onderdeel
 *                         dat zichzelf kan bijwerken doet dat dan (assets/js/afspraak.js);
 *   'p13:live'            na het vervangen van blokken, zodat een script zijn tellers of filters
 *                         opnieuw kan toepassen (assets/js/incheck.js, volgorde.js).
 */
(function () {
  'use strict';
  var main = document.querySelector('[data-live]');
  if (!main || !window.fetch || !window.DOMParser) return;

  var scopes = (main.dataset.live || '').split(' ').filter(Boolean);
  var stand = {};
  try { stand = JSON.parse(main.dataset.liveV || '{}'); } catch (e) {}
  var ik = parseInt(main.dataset.liveIk || '0', 10);
  var url = main.dataset.liveUrl;
  var bezig = false, klok = null, gestopt = false;
  var INTERVAL = 10000;
  if (!scopes.length || !url) return;

  function stop() { gestopt = true; clearInterval(klok); }

  function bezigIn(blok) {
    var a = document.activeElement;
    if (a && blok.contains(a) && /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName) && a.type !== 'button' && a.type !== 'submit') return true;
    // Getypt maar nog niet opgeslagen (bijv. een notitie op de bellijst)
    var vuil = false;
    blok.querySelectorAll('textarea, input[type="text"], input:not([type])').forEach(function (v) { if (v.value !== v.defaultValue) vuil = true; });
    return vuil || blok.hasAttribute('data-live-bezig');
  }

  function namenTekst(namen) {
    var uniek = namen.filter(function (n, i) { return namen.indexOf(n) === i; });
    if (uniek.length === 1) return uniek[0];
    return uniek.slice(0, -1).join(', ') + ' en ' + uniek[uniek.length - 1];
  }

  function melding(namen) {
    var m = document.getElementById('bh-live-melding');
    if (!m) {
      m = document.createElement('div');
      m.id = 'bh-live-melding';
      m.className = 'bh-live-melding';
      m.setAttribute('role', 'status');
      var t = document.createElement('span'); t.className = 'bh-live-tekst'; m.appendChild(t);
      var v = document.createElement('button'); v.type = 'button'; v.className = 'ds-btn ds-btn-primair ds-btn-sm'; v.textContent = 'Vernieuwen';
      v.addEventListener('click', function () { location.reload(); });
      m.appendChild(v);
      var s = document.createElement('button'); s.type = 'button'; s.className = 'bh-live-sluit'; s.setAttribute('aria-label', 'Melding sluiten'); s.textContent = '×';
      s.addEventListener('click', function () { m.remove(); });
      m.appendChild(s);
      document.body.appendChild(m);
    }
    m.querySelector('.bh-live-tekst').textContent = namenTekst(namen) + ' heeft hier iets gewijzigd. Vernieuw om het te zien.';
  }

  function vervang(namen) {
    var blokken = Array.prototype.slice.call(document.querySelectorAll('[data-live-blok][id]'));
    fetch(location.href, { credentials: 'same-origin', headers: { 'X-P13-Live': '1' }, cache: 'no-store' })
      .then(function (r) { if (!r.ok || r.redirected) throw new Error('niet'); return r.text(); })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var overgeslagen = false, vervangen = [];
        blokken.forEach(function (blok) {
          var nieuw = doc.getElementById(blok.id);
          if (!nieuw) return;
          if (bezigIn(blok)) { overgeslagen = true; return; }
          var kopie = document.importNode(nieuw, true);
          blok.replaceWith(kopie);
          vervangen.push(kopie);
          if (window.P13Klap && window.P13Klap.koppel) window.P13Klap.koppel(kopie);
        });
        if (vervangen.length) document.dispatchEvent(new CustomEvent('p13:live', { detail: { blokken: vervangen } }));
        if (overgeslagen) melding(namen);
      })
      .catch(function () { melding(namen); });
  }

  function vraag() {
    if (gestopt || bezig || document.hidden) return;
    bezig = true;
    fetch(url + '?s=' + encodeURIComponent(scopes.join(',')), { credentials: 'same-origin', headers: { 'X-P13-Live': '1' }, cache: 'no-store' })
      .then(function (r) { if (r.status === 401) { stop(); return null; } return r.json(); })
      .then(function (d) {
        if (!d || !d.ok) return;
        var anderen = [];
        Object.keys(d.v || {}).forEach(function (s) {
          var n = d.v[s];
          // Een teller van 0 is opgeruimd (live_opruimen), geen wijziging.
          if (stand[s] !== undefined && n.v !== 0 && n.v !== stand[s] && !(n.door_id && n.door_id === ik)) anderen.push(n.door || 'Iemand');
          stand[s] = n.v;
        });
        if (!anderen.length) return;
        // Eerst laten weten dát er iets veranderde, zodat een onderdeel dat zichzelf kan bijwerken
        // (bijv. de afspraak-editor, assets/js/afspraak.js) dat meteen doet.
        document.dispatchEvent(new CustomEvent('p13:live-wijziging', { detail: { namen: anderen } }));
        if (document.querySelector('[data-live-blok][id]')) vervang(anderen); else melding(anderen);
      })
      .catch(function () { /* geen verbinding: de volgende ronde opnieuw */ })
      .then(function () { bezig = false; });
  }

  klok = setInterval(vraag, INTERVAL);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) vraag(); });
})();
