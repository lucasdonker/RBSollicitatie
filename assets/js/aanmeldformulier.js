/**
 * Paal 13 — Aanmeldformulier: toestemmingsblok voor ouders.
 *
 * Gedeeld door /aanmelden.php en het formulier op landingspagina's. Werkt op basis van
 * data-attributen, zodat beide formulieren hun eigen opmaak kunnen houden:
 *   data-geboortedatum   op het datumveld
 *   data-ouder-blok      op het blok dat bij een leeftijd onder de 16 moet verschijnen
 *
 * Onder de 16 jaar is toestemming van een ouder nodig om de gegevens te mogen verwerken.
 * Dat is de AVG-grens; de 18-jaargrens voor ondertekenen staat daar los van.
 */
(function () {
  'use strict';

  var datum = document.querySelector('[data-geboortedatum]');
  var blok = document.querySelector('[data-ouder-blok]');
  if (!datum || !blok) return;

  function leeftijdOp(waarde) {
    if (!waarde) return null;
    var g = new Date(waarde);
    if (isNaN(g.getTime())) return null;
    var nu = new Date();
    var jaren = nu.getFullYear() - g.getFullYear();
    var maanden = nu.getMonth() - g.getMonth();
    if (maanden < 0 || (maanden === 0 && nu.getDate() < g.getDate())) jaren--;
    return jaren;
  }

  function bijwerken() {
    var leeftijd = leeftijdOp(datum.value);
    var nodig = leeftijd !== null && leeftijd < 16;
    var wasVerborgen = blok.hidden;

    blok.hidden = !nodig;
    blok.style.display = nodig ? '' : 'none';

    // Verborgen velden mogen niet verplicht zijn: de browser kan er dan niet heen springen
    // en blokkeert het versturen zonder zichtbare melding.
    var naam = blok.querySelector('[name="ouder_toestemming_naam"]');
    var akkoord = blok.querySelector('[name="ouder_akkoord"]');
    if (naam) { naam.required = nodig; naam.setAttribute('aria-required', nodig ? 'true' : 'false'); }
    if (akkoord) { akkoord.required = nodig; akkoord.setAttribute('aria-required', nodig ? 'true' : 'false'); }

    // Verschijnt het blok door een wijziging van de bezoeker, meld dat dan hoorbaar.
    if (nodig && wasVerborgen) blok.setAttribute('aria-live', 'polite');
  }

  datum.addEventListener('change', bijwerken);
  datum.addEventListener('input', bijwerken);
  bijwerken(); // ook direct bij laden, bijvoorbeeld na een foutmelding met ingevulde datum

  // Na een mislukte verzending de aandacht naar de melding brengen.
  var melding = document.querySelector('[data-formulier-fout]');
  if (melding && typeof melding.focus === 'function') melding.focus();

  var gelukt = document.querySelector('[data-formulier-succes]');
  if (gelukt && typeof gelukt.focus === 'function') gelukt.focus();
})();
