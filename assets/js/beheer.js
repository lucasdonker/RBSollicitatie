/**
 * Paal 13 — gedeelde gedragslaag voor de beheeromgeving.
 *
 * Alles hier is een verbetering bovenop iets dat zonder JavaScript al werkt:
 * de filterbalk is een GET-formulier met een verzendknop, de rij-acties zijn
 * gewone submits. Valt dit bestand weg, dan is een scherm trager, niet stuk.
 *
 * Draait op elk beheerscherm (admin/layout_bottom.php).
 */
(function () {
  'use strict';

  /* ── Tabel: selectie en bulkbalk ─────────────────────────────── */

  function bulkVerversen(tabel) {
    var balk = document.getElementById('bulkbalk');
    if (!balk) return;
    var gekozen = tabel.querySelectorAll('.bh-kies:checked');
    var n = gekozen.length;

    balk.hidden = n === 0;
    var teller = balk.querySelector('[data-bulk-aantal]');
    if (teller) teller.textContent = String(n);

    // Welke fases zitten er in de selectie? Een knop die maar voor een deel van de
    // selectie geldt, verbergen we — anders belooft hij iets dat niet gebeurt.
    var fases = {};
    Array.prototype.forEach.call(gekozen, function (vak) {
      var rij = vak.closest('tr');
      if (rij && rij.dataset.fase) fases[rij.dataset.fase] = true;
    });
    Array.prototype.forEach.call(balk.querySelectorAll('[data-fases]'), function (knop) {
      var toegestaan = knop.dataset.fases.split(',');
      var alles = Object.keys(fases);
      knop.hidden = alles.length > 0 && !alles.every(function (f) { return toegestaan.indexOf(f) !== -1; });
    });

    // "Alles selecteren" weerspiegelt de stand van de rijen.
    var alles = tabel.querySelector('.bh-kies-alles');
    if (alles) {
      var totaal = tabel.querySelectorAll('.bh-kies').length;
      alles.checked = n > 0 && n === totaal;
      alles.indeterminate = n > 0 && n < totaal;
    }
  }

  function tabelKoppelen(tabel) {
    tabel.addEventListener('change', function (e) {
      var d = e.target;
      if (d.classList.contains('bh-kies-alles')) {
        Array.prototype.forEach.call(tabel.querySelectorAll('.bh-kies'), function (v) { v.checked = d.checked; });
        bulkVerversen(tabel);
      } else if (d.classList.contains('bh-kies')) {
        bulkVerversen(tabel);
      }
    });
    bulkVerversen(tabel);
  }

  /* ── Kolommenkiezer ──────────────────────────────────────────────
     De keuze gaat in een cookie en niet in localStorage: de server moet hem al
     kennen bij de eerste render, anders staan de kolommen even verkeerd en springt
     de tabel. Hier herschrijven we alleen --bh-tracks, zodat de kolombreedtes
     kloppen zonder de pagina te herladen. */

  function tracksHerberekenen(tabel) {
    var koppen = tabel.querySelectorAll('thead th');
    var delen = [];
    Array.prototype.forEach.call(koppen, function (th) {
      if (th.hidden) return;
      delen.push(th.classList.contains('bh-kol-kies') ? '34px' : (th.dataset.track || '1fr'));
    });
    tabel.style.setProperty('--bh-tracks', delen.join(' '));
  }

  function kolomZetten(tabel, sleutel, aan) {
    var i = -1;
    var koppen = tabel.querySelectorAll('thead th');
    Array.prototype.forEach.call(koppen, function (th, idx) {
      if (th.dataset.kolom === sleutel) i = idx;
    });
    if (i < 0) return;
    koppen[i].hidden = !aan;
    Array.prototype.forEach.call(tabel.querySelectorAll('tbody tr'), function (rij) {
      var cellen = rij.children;
      if (cellen[i]) cellen[i].hidden = !aan;
    });
    tracksHerberekenen(tabel);
  }

  function kolomkeuzeBewaren(tabel) {
    var aan = [];
    Array.prototype.forEach.call(tabel.querySelectorAll('thead th[data-kolom]'), function (th) {
      if (!th.hidden) aan.push(th.dataset.kolom);
    });
    var naam = tabel.dataset.kolomcookie;
    if (!naam) return;
    // Vervalt na dit seizoen; de servernaam bevat het seizoen-id.
    document.cookie = naam + '=' + aan.join(',') + ';path=/;max-age=15552000;samesite=lax';
  }

  function kiezerKoppelen(kiezer) {
    var tabel = document.getElementById(kiezer.dataset.voor);
    if (!tabel) return;
    kiezer.addEventListener('change', function (e) {
      if (!e.target.matches('input[type=checkbox][data-kolom]')) return;
      kolomZetten(tabel, e.target.dataset.kolom, e.target.checked);
      kolomkeuzeBewaren(tabel);
    });
  }

  /* ── Filterbalk: direct filteren zodra JS er is ──────────────── */

  function filterKoppelen(form) {
    form.classList.add('js');                     // verbergt de verzendknop via CSS
    var wacht = null;
    form.addEventListener('input', function (e) {
      if (!e.target.matches('input[type=search]')) return;
      clearTimeout(wacht);
      wacht = setTimeout(function () { form.submit(); }, 450);
    });
    form.addEventListener('change', function (e) {
      if (e.target.matches('select')) form.submit();
    });
  }

  /* ── Start ───────────────────────────────────────────────────── */

  function start(wortel) {
    (wortel || document).querySelectorAll('.bh-tabel').forEach(tabelKoppelen);
    (wortel || document).querySelectorAll('[data-kolomkiezer]').forEach(kiezerKoppelen);
    (wortel || document).querySelectorAll('.bh-filterbalk').forEach(filterKoppelen);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { start(document); });
  } else {
    start(document);
  }

  window.bhStart = start;   // voor blokken die later in de DOM komen
})();
