/**
 * Paal 13 — Accounts & rechten › Rollen (admin/rollen.php).
 *
 * - rolBewerk(sleutel): het bewerkformulier van een rol open/dicht (data-klik op de knop).
 * - De taakmatrix: per onderdeel een vinkje "Alles" per rol (aan, uit of half), en de kop van het
 *   blok telt mee ("Bestuur 5/5 · Begeleider 3/5"). Zonder JavaScript werkt de matrix gewoon;
 *   alleen "Alles" doet dan niets.
 */
(function () {
  'use strict';

  /* Bewerken staat per rol ingeklapt: de kaart moet leesbaar blijven, en een rol bewerken is een uitzondering. */
  window.rolBewerk = function (sleutel) {
    var f = document.getElementById('rolform-' + sleutel);
    if (!f) return;
    f.hidden = !f.hidden;
    if (!f.hidden) { var v = f.querySelector('input[type=text]:not([readonly])'); if (v) v.focus(); }
  };

  var form = document.querySelector('[data-taakmatrix]');
  if (!form) return;

  // Rolnamen uit de kolomkoppen (zelfde volgorde als de cellen).
  function rolNamen(blok) {
    var uit = {}, koppen = blok.querySelectorAll('thead th'), alles = blok.querySelectorAll('[data-taak-alles]');
    alles.forEach(function (cb) {
      var td = cb.closest('td'), i = Array.prototype.indexOf.call(td.parentNode.children, td);
      uit[cb.dataset.taakAlles] = koppen[i] ? koppen[i].textContent.trim() : cb.dataset.taakAlles;
    });
    return uit;
  }

  function werkBij(blok) {
    var namen = rolNamen(blok), sam = [];
    blok.querySelectorAll('[data-taak-alles]').forEach(function (alles) {
      var rol = alles.dataset.taakAlles;
      var taken = blok.querySelectorAll('input[data-taak-rol="' + rol + '"]');
      var aan = Array.prototype.filter.call(taken, function (t) { return t.checked; }).length;
      alles.checked = aan === taken.length && aan > 0;
      alles.indeterminate = aan > 0 && aan < taken.length;
      if (aan > 0) sam.push(namen[rol] + ' ' + aan + '/' + taken.length);
    });
    var s = blok.querySelector('[data-taak-sam]');
    if (s) s.textContent = sam.length ? sam.join(' · ') : 'alleen Admin';
  }

  form.querySelectorAll('.bh-taakblok').forEach(werkBij);

  form.addEventListener('change', function (e) {
    var blok = e.target.closest('.bh-taakblok');
    if (!blok) return;
    if (e.target.matches('[data-taak-alles]')) {
      var aan = e.target.checked;
      blok.querySelectorAll('input[data-taak-rol="' + e.target.dataset.taakAlles + '"]').forEach(function (t) { t.checked = aan; });
    }
    werkBij(blok);
  });
})();
