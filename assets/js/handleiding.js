/**
 * Paal 13 — een handleiding lezen in het beheer (admin/handleiding.php?doc=…).
 *
 * Drie dingen, allemaal een aanvulling: zonder JavaScript is de handleiding gewoon een
 * lange pagina met een inhoudsopgave die werkt.
 *   1. Zoeken: toont alleen de hoofdstukken waar het woord in staat, en markeert het.
 *   2. De inhoudsopgave licht op bij het hoofdstuk dat je leest.
 *   3. Afvinklijsten (`- [ ]` in de Markdown) zijn aan te vinken en worden per apparaat
 *      onthouden — een gemak, geen administratie: wie van apparaat wisselt begint opnieuw.
 */
(function () {
  'use strict';

  var tekst = document.getElementById('hb-tekst');
  if (!tekst) return;
  var hoofdstukken = Array.prototype.slice.call(tekst.querySelectorAll('[data-hb-hoofdstuk]'));
  var toc = {};
  Array.prototype.forEach.call(document.querySelectorAll('[data-hb-toc]'), function (a) { toc[a.getAttribute('data-hb-toc')] = a; });

  /* ── 1. Zoeken ───────────────────────────────────────────────── */
  var veld = document.getElementById('hb-zoek');
  var uitkomst = document.getElementById('hb-uitkomst');
  var niets = document.getElementById('hb-niets');
  var intro = tekst.querySelector('.bh-hb-intro');
  var bron = hoofdstukken.map(function (h) { return h.textContent.toLowerCase(); });

  function wisMarkering() {
    Array.prototype.forEach.call(tekst.querySelectorAll('mark.bh-hb-treffer'), function (m) {
      m.parentNode.replaceChild(document.createTextNode(m.textContent), m);
    });
    tekst.normalize();
  }

  function markeer(root, woord) {
    var w = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
      acceptNode: function (n) {
        return n.nodeValue.toLowerCase().indexOf(woord) !== -1 && !n.parentNode.closest('script, a.ds-btn')
          ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
      }
    });
    var lijst = [];
    while (w.nextNode()) lijst.push(w.currentNode);
    lijst.forEach(function (n) {
      var i = n.nodeValue.toLowerCase().indexOf(woord);
      if (i === -1) return;
      var na = n.splitText(i);
      na.splitText(woord.length);
      var m = document.createElement('mark');
      m.className = 'bh-hb-treffer';
      m.textContent = na.nodeValue;
      na.parentNode.replaceChild(m, na);
    });
  }

  var wacht;
  function zoek() {
    var q = (veld.value || '').trim().toLowerCase();
    wisMarkering();
    var gevonden = 0;
    hoofdstukken.forEach(function (h, i) {
      var raak = q === '' || bron[i].indexOf(q) !== -1;
      h.hidden = !raak;
      var a = toc[h.id];
      if (a) a.parentNode.hidden = !raak;
      if (raak) { gevonden++; if (q.length >= 2) markeer(h, q); }
    });
    if (intro) intro.hidden = q !== '';
    niets.hidden = gevonden > 0;
    uitkomst.textContent = q === '' ? '' : (gevonden === 1 ? '1 hoofdstuk gevonden' : gevonden + ' hoofdstukken gevonden');
  }
  if (veld) {
    veld.addEventListener('input', function () { clearTimeout(wacht); wacht = setTimeout(zoek, 150); });
    veld.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        var eerste = tekst.querySelector('mark.bh-hb-treffer');
        if (eerste) eerste.scrollIntoView({ block: 'center' });
      }
    });
  }

  // Een link naar een verborgen hoofdstuk (na zoeken) moet het hoofdstuk wél tonen.
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[href^="#"]');
    if (!a || !veld || veld.value === '') return;
    var doel = document.getElementById(a.getAttribute('href').slice(1));
    if (doel && doel.closest('[hidden]')) { veld.value = ''; zoek(); }
  });

  // Op een telefoon staat de inhoudsopgave bóven de tekst; open duwt hij het eerste
  // hoofdstuk een scherm naar beneden. Dicht beginnen, behalve als je hem nodig hebt.
  var inhoud = document.querySelector('.bh-hb-inhoud');
  if (inhoud && window.innerWidth < 900) {
    inhoud.open = false;
    inhoud.addEventListener('click', function (e) { if (e.target.closest('a')) inhoud.open = false; });
  }

  /* ── 2. Waar ben je ──────────────────────────────────────────── */
  if ('IntersectionObserver' in window) {
    var actief = null;
    var io = new IntersectionObserver(function (items) {
      items.forEach(function (it) {
        if (!it.isIntersecting) return;
        var a = toc[it.target.id];
        if (!a || a === actief) return;
        if (actief) { actief.classList.remove('actief'); actief.removeAttribute('aria-current'); }
        a.classList.add('actief'); a.setAttribute('aria-current', 'location');
        actief = a;
      });
    }, { rootMargin: '-15% 0px -75% 0px' });
    hoofdstukken.forEach(function (h) { io.observe(h); });
  }

  /* ── 3. Afvinklijsten ────────────────────────────────────────── */
  var sleutel = 'p13-hb-' + (new URLSearchParams(location.search).get('doc') || '');
  var bewaard = {};
  try { bewaard = JSON.parse(localStorage.getItem(sleutel) || '{}') || {}; } catch (e) { bewaard = {}; }

  hoofdstukken.forEach(function (h) {
    Array.prototype.forEach.call(h.querySelectorAll('li input[type="checkbox"]'), function (vak, i) {
      var id = h.id + ':' + i;
      vak.disabled = false;
      vak.checked = !!bewaard[id];
      // Pandoc zet het vinkje meestal in een <label>; zo niet, dan is de tekst van het
      // lijstitem het label — een vinkje zonder naam zegt een schermlezer niets.
      if (!vak.closest('label')) {
        var li = vak.closest('li');
        if (!li.id) li.id = 'hb-vak-' + h.id + '-' + i;
        vak.setAttribute('aria-labelledby', li.id);
      }
      vak.addEventListener('change', function () {
        if (vak.checked) bewaard[id] = 1; else delete bewaard[id];
        try { localStorage.setItem(sleutel, JSON.stringify(bewaard)); } catch (e) {}
      });
    });
  });
})();
