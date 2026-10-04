/**
 * Paal 13 — variabele invoegen (admin/_variabelen.php). Onthoudt het laatste veld waarin je typte
 * (een veld met data-var-veld, of de e-maileditor .p13-editor) en zet daar {{naam}} op de cursor.
 * Zoeken filtert de knoppen. Geen veld gekozen? Dan het eerste zichtbare veld met data-var-veld.
 * Een knop met data-var-doel="id" zet zijn tekst altijd in dat veld (admin/_voorwaarden.php).
 */
(function () {
  'use strict';
  var laatst = null;   // {el: HTMLElement} of {editor: id}

  document.addEventListener('focusin', function (e) {
    var veld = e.target.closest('[data-var-veld]');
    if (veld) { laatst = { el: veld }; return; }
    var ed = e.target.closest('.p13-editor');
    if (ed && ed.id) laatst = { editor: ed.id };
  });

  function zichtbaar(el) { return !!(el && el.offsetParent !== null); }

  function invoegen(tekst, vastDoel) {
    var doel = vastDoel ? { el: vastDoel } : laatst;
    if (doel && doel.el && !zichtbaar(doel.el)) doel = null;
    if (doel && doel.editor && !zichtbaar(document.getElementById(doel.editor))) doel = null;
    if (!doel) {
      var eerste = [].filter.call(document.querySelectorAll('[data-var-veld]'), zichtbaar)[0];
      if (eerste) doel = { el: eerste };
    }
    if (!doel) return;
    if (doel.editor && window.P13Editor) { window.P13Editor.insert(doel.editor, tekst); return; }
    var el = doel.el;
    var start = el.selectionStart != null ? el.selectionStart : el.value.length;
    var eind = el.selectionEnd != null ? el.selectionEnd : el.value.length;
    el.setRangeText(tekst, start, eind, 'end');
    el.focus();
    el.dispatchEvent(new Event('input', { bubbles: true }));
  }

  document.addEventListener('click', function (e) {
    var knop = e.target.closest('[data-var]');
    // data-var-doel="id": altijd in dat veld (een voorwaardeveld), niet waar je het laatst typte.
    if (knop) invoegen(knop.dataset.var, knop.dataset.varDoel ? document.getElementById(knop.dataset.varDoel) : null);
  });

  document.addEventListener('input', function (e) {
    if (!e.target.matches('[data-var-zoek]')) return;
    var term = e.target.value.trim().toLowerCase();
    var kiezer = e.target.closest('[data-varkiezer]');
    kiezer.querySelectorAll('[data-vargroep]').forEach(function (g) {
      var n = 0;
      g.querySelectorAll('[data-var]').forEach(function (b) {
        var ja = !term || (b.dataset.varTekst || '').indexOf(term) !== -1;
        b.hidden = !ja; if (ja) n++;
      });
      g.hidden = n === 0;
    });
  });

  window.P13Var = { invoegen: invoegen };
})();
