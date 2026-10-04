/*
 * Paal 13 — Documenten uploaden (portaal/document_upload.php).
 * Alleen verrijking: het scherm werkt ook zonder dit script. Hier komt bij: voorbeeld tonen na
 * kiezen, grootte/type vooraf checken, het achterkant-vak verbergen bij een paspoort.
 * Verwijderen bevestigen gaat via het gedeelde blok portaal/_bevestig.php (geen confirm()).
 */
(function () {
  'use strict';
  var MAX = 10 * 1048576;
  var TYPEN = ['image/jpeg', 'image/png', 'application/pdf'];

  function melding(form, tekst) {
    var plek = form.querySelector('.du-fout');
    if (!plek) {
      plek = document.createElement('div');
      plek.className = 'du-fout';
      form.insertBefore(plek, form.querySelector('.du-acties'));
    }
    if (window.P13Melding) { if (tekst) P13Melding.fout(plek, tekst); else P13Melding.wis(plek); }
    else { plek.textContent = tekst; plek.setAttribute('role', 'alert'); }
  }

  document.querySelectorAll('.du-form').forEach(function (form) {
    var input = form.querySelector('.du-input');
    var prev = form.querySelector('.du-preview');
    var img = form.querySelector('.du-preview-img');
    var naam = form.querySelector('.du-preview-naam');
    if (!input) return;

    input.addEventListener('change', function () {
      var f = input.files && input.files[0];
      melding(form, '');
      prev.hidden = true; img.hidden = true; img.removeAttribute('src');
      if (!f) return;
      if (f.size > MAX) { melding(form, 'Dit bestand is groter dan 10 MB. Maak een nieuwe foto of kies een kleiner bestand.'); input.value = ''; return; }
      if (TYPEN.indexOf(f.type) < 0) { melding(form, 'Alleen een foto (jpg, png) of een pdf. HEIC-foto\'s van een iPhone: kies in de camera-instellingen "Meest compatibel" of maak een schermafbeelding.'); input.value = ''; return; }
      naam.textContent = f.name + ' · ' + (f.size >= 1048576 ? (f.size / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(f.size / 1024)) + ' kB');
      prev.hidden = false;
      if (f.type !== 'application/pdf' && window.URL && URL.createObjectURL) {
        img.src = URL.createObjectURL(f); img.hidden = false;
        img.onload = function () { URL.revokeObjectURL(img.src); };
      }
    });

    form.addEventListener('submit', function (e) {
      // Voorkant: eerst paspoort of identiteitskaart kiezen (de server controleert dit ook).
      var keuzes = document.querySelectorAll('input[name="id_type"][form="' + form.id + '"]');
      if (keuzes.length && !Array.prototype.some.call(keuzes, function (r) { return r.checked; })) {
        e.preventDefault();
        melding(form, 'Kies eerst hierboven of het een paspoort of een identiteitskaart is.');
        keuzes[0].focus();
      }
      // Laadstand en vergrendeling: assets/js/actie.js, zodra vaststaat dat er verzonden wordt.
    });
  });

  // Paspoort / identiteitskaart: achterkant-vak alleen tonen bij een identiteitskaart
  var keuzes = document.querySelectorAll('input[name="id_type"]');
  function idTypeToepassen() {
    var v = ''; keuzes.forEach(function (r) { if (r.checked) v = r.value; });
    document.querySelectorAll('.du-alleen-idkaart').forEach(function (vak) { vak.hidden = (v !== 'id_kaart'); });
  }
  keuzes.forEach(function (r) { r.addEventListener('change', idTypeToepassen); });
  if (keuzes.length) idTypeToepassen();
})();
