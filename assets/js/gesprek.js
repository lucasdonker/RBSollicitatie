/*
 * Paal 13 — het besluit in het TW-gesprek (admin/tw/gesprek.php).
 *   - Kies je aannemen/twijfel/afwijzen, dan staat eronder wat er gebeurt (de tekst die ook in de
 *     bevestiging komt, data-bevestig op de keuze).
 *   - "Standaardtekst invoegen" zet de standaardtekst van de gekozen keuze in het bericht, om aan te
 *     passen (data-standaard-aannemen / data-standaard-afwijzen op de knop). Een getypt bericht
 *     wordt niet overschreven zonder te vragen.
 */
(function () {
  'use strict';
  var form = document.querySelector('[data-twgesprek]');
  if (!form) return;
  var gevolg = form.querySelector('[data-gevolg]');
  var bericht = form.querySelector('textarea[name="bericht"]');
  var knop = form.querySelector('[data-standaard]');

  function gekozen() { return form.querySelector('input[name="besluit"]:checked'); }
  function toonGevolg() {
    var k = gekozen();
    if (gevolg && k) gevolg.textContent = k.dataset.bevestig || '';
    if (knop) knop.hidden = !k || k.value === 'twijfel';
  }
  form.addEventListener('change', function (e) { if (e.target.name === 'besluit') toonGevolg(); });
  toonGevolg();

  if (knop && bericht) knop.addEventListener('click', function () {
    var k = gekozen();
    if (!k) return;
    var tekst = knop.dataset['standaard' + (k.value === 'afwijzen' ? 'Afwijzen' : 'Aannemen')] || '';
    var zet = function () { bericht.value = tekst; bericht.dispatchEvent(new Event('input', { bubbles: true })); bericht.focus(); };
    if (!bericht.value.trim() || !window.bhBevestig) { zet(); return; }
    window.bhBevestig({ titel: 'Bericht vervangen?', tekst: 'Wat je al typte, maakt plaats voor de standaardtekst.', knop: 'Vervangen' })
      .then(function (ja) { if (ja) zet(); });
  });
})();
