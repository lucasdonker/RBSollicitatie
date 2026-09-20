/*
 * Paal 13 — Documenten uploaden (portaal/document_upload.php).
 * Voorbeeld tonen na kiezen, uploadknop pas actief met een bestand, grootte/type vooraf checken,
 * paspoort/ID-kaart-keuze doorgeven en het achterkant-vak tonen, verwijderen bevestigen.
 */
(function () {
  'use strict';
  var MAX = 10 * 1048576;
  var TYPEN = ['image/jpeg', 'image/png', 'application/pdf'];

  function melding(form, tekst) {
    var m = form.querySelector('.du-fout');
    if (!m) { m = document.createElement('div'); m.className = 'ds-melding ds-melding-error du-fout'; m.setAttribute('role', 'alert'); form.insertBefore(m, form.querySelector('.du-acties')); }
    m.textContent = tekst; m.hidden = !tekst;
  }

  document.querySelectorAll('.du-form').forEach(function (form) {
    var input = form.querySelector('.du-input');
    var knop = form.querySelector('.du-upload');
    var prev = form.querySelector('.du-preview');
    var img = form.querySelector('.du-preview-img');
    var naam = form.querySelector('.du-preview-naam');
    if (!input || !knop) return;

    input.addEventListener('change', function () {
      var f = input.files && input.files[0];
      melding(form, '');
      knop.disabled = true; prev.hidden = true; img.hidden = true; img.removeAttribute('src');
      if (!f) return;
      if (f.size > MAX) { melding(form, 'Dit bestand is groter dan 10 MB. Maak een nieuwe foto of kies een kleiner bestand.'); input.value = ''; return; }
      if (TYPEN.indexOf(f.type) < 0) { melding(form, 'Alleen een foto (jpg, png) of een pdf. HEIC-foto\'s van een iPhone: kies in de camera-instellingen "Meest compatibel" of maak een schermafbeelding.'); input.value = ''; return; }
      naam.textContent = f.name + ' · ' + (f.size >= 1048576 ? (f.size / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(f.size / 1024)) + ' kB');
      prev.hidden = false;
      if (f.type !== 'application/pdf' && window.URL && URL.createObjectURL) {
        img.src = URL.createObjectURL(f); img.hidden = false;
        img.onload = function () { URL.revokeObjectURL(img.src); };
      }
      knop.disabled = false;
    });

    form.addEventListener('submit', function (e) {
      var sub = e.submitter;
      if (sub && sub.dataset.bevestig) { if (!confirm(sub.dataset.bevestig)) { e.preventDefault(); return; } return; }
      var idtype = form.querySelector('.du-idtype');
      if (idtype && !idtype.value) { e.preventDefault(); melding(form, 'Kies eerst hierboven of het een paspoort of een identiteitskaart is.'); return; }
      if (window.portaalSubmit) window.portaalSubmit(form);
    });
  });

  // Paspoort / identiteitskaart: waarde in het voorkant-formulier zetten, achterkant-vak tonen/verbergen
  var keuzes = document.querySelectorAll('input[name="id_type_keuze"]');
  function idTypeToepassen() {
    var v = ''; keuzes.forEach(function (r) { if (r.checked) v = r.value; });
    document.querySelectorAll('.du-idtype').forEach(function (h) { h.value = v; });
    document.querySelectorAll('.du-alleen-idkaart').forEach(function (vak) { vak.hidden = (v !== 'id_kaart'); });
  }
  keuzes.forEach(function (r) { r.addEventListener('change', idTypeToepassen); });
  if (keuzes.length) idTypeToepassen();
})();
