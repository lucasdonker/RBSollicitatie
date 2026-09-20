/**
 * Paal 13 — gedeelde tekst-editor voor e-mails (beheer).
 * Eén contenteditable-editor met HTML-tab, gebruikt door Mail templates en Berichten.
 *
 *   P13Editor.init(id)          koppelt de editor met dat id (zie admin/_editor.php)
 *   P13Editor.sync(id)          kopieert de visuele inhoud naar het verborgen textarea (vóór submit)
 *   P13Editor.insert(id, tekst) voegt tekst/HTML in op de cursor (variabelen, shortcodes)
 *   P13Editor.html(id)          huidige HTML
 */
(function () {
  'use strict';
  var editors = {};

  function el(id, deel) { return document.getElementById(id + '_' + deel); }

  function init(id) {
    var wrap = document.getElementById(id);
    if (!wrap || editors[id]) return;
    var e = {
      id: id, wrap: wrap, vis: el(id, 'vis'), ta: el(id, 'html'), toolbar: el(id, 'toolbar'),
      tabVis: el(id, 'tab_vis'), tabHtml: el(id, 'tab_html'), modus: 'vis'
    };
    editors[id] = e;
    e.vis.innerHTML = e.ta.value;

    // Toolbar-knoppen: data-cmd (execCommand), data-block (formatBlock), data-html (rauwe HTML), data-actie
    wrap.querySelectorAll('[data-cmd]').forEach(function (b) {
      b.addEventListener('click', function () { focus(e); document.execCommand(b.getAttribute('data-cmd'), false, null); });
    });
    var blok = el(id, 'blok');
    if (blok) blok.addEventListener('change', function () {
      if (!blok.value) return; focus(e); document.execCommand('formatBlock', false, '<' + blok.value + '>'); blok.value = '';
    });
    wrap.querySelectorAll('[data-html]').forEach(function (b) {
      b.addEventListener('click', function () { insert(id, b.getAttribute('data-html')); });
    });
    wrap.querySelectorAll('[data-actie]').forEach(function (b) {
      b.addEventListener('click', function () { actie(e, b.getAttribute('data-actie')); });
    });
    e.tabVis.addEventListener('click', function () { wissel(e, 'vis'); });
    e.tabHtml.addEventListener('click', function () { wissel(e, 'html'); });

    // Plakken als platte tekst (geen rommelige Word-HTML)
    e.vis.addEventListener('paste', function (ev) {
      ev.preventDefault();
      var t = (ev.clipboardData || window.clipboardData).getData('text/plain');
      document.execCommand('insertText', false, t);
    });
    // Formulier-submit: altijd synchroniseren
    var form = wrap.closest('form');
    if (form) form.addEventListener('submit', function () { sync(id); });
  }

  function focus(e) { if (e.modus === 'vis') e.vis.focus(); }

  function wissel(e, modus) {
    if (modus === e.modus) return;
    if (modus === 'html') { e.ta.value = e.vis.innerHTML; }
    else { e.vis.innerHTML = e.ta.value; }
    e.modus = modus;
    e.vis.style.display = modus === 'vis' ? '' : 'none';
    e.toolbar.style.display = modus === 'vis' ? '' : 'none';
    e.ta.style.display = modus === 'html' ? '' : 'none';
    e.tabVis.classList.toggle('actief', modus === 'vis');
    e.tabHtml.classList.toggle('actief', modus === 'html');
  }

  function actie(e, naam) {
    var url, tekst;
    if (naam === 'link') {
      url = prompt('Webadres (URL):'); if (!url) return;
      tekst = prompt('Linktekst:', 'Klik hier'); if (!tekst) return;
      insert(e.id, '<a href="' + esc(url) + '">' + esc(tekst) + '</a>');
    } else if (naam === 'knop') {
      url = prompt('Webadres van de knop (URL):', '{{portaal_url}}'); if (!url) return;
      tekst = prompt('Knoptekst:', 'Naar mijn portaal'); if (!tekst) return;
      insert(e.id, '<p>[knop href="' + esc(url) + '"]' + esc(tekst) + '[/knop]</p>');
    } else if (naam === 'info') {
      insert(e.id, '<p>[info]<strong>Goed om te weten</strong><br>Tekst...[/info]</p>');
    } else if (naam === 'letop') {
      insert(e.id, '<p>[let op]Tekst...[/let op]</p>');
    } else if (naam === 'afbeelding') {
      url = prompt('Webadres van de afbeelding (URL, jpg/png):'); if (!url) return;
      insert(e.id, '<p><img src="' + esc(url) + '" alt="" style="max-width:100%;height:auto;border-radius:8px"></p>');
    } else if (naam === 'lijn') {
      insert(e.id, '<hr>');
    }
  }

  function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

  function insert(id, html) {
    var e = editors[id]; if (!e) return;
    if (e.modus === 'vis') {
      e.vis.focus();
      document.execCommand('insertHTML', false, html);
    } else {
      var p = e.ta.selectionStart || 0;
      e.ta.value = e.ta.value.slice(0, p) + html + e.ta.value.slice(e.ta.selectionEnd || p);
      e.ta.selectionStart = e.ta.selectionEnd = p + html.length; e.ta.focus();
    }
  }

  function sync(id) {
    var e = editors[id]; if (!e) return;
    if (e.modus === 'vis') e.ta.value = e.vis.innerHTML;
  }

  function html(id) { sync(id); return editors[id] ? editors[id].ta.value : ''; }

  window.P13Editor = { init: init, sync: sync, insert: insert, html: html };
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.p13-editor[data-auto]').forEach(function (w) { init(w.id); });
  });
})();
