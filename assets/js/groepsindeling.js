/**
 * Paal 13 — groepsindeling (admin/_groepsindeling.php), selectiedag én trainingsweekend.
 *
 * Slepen: het kaartje verhuist meteen, de server volgt; daarna één herlaadactie, omdat de
 * botsmarkering van de hele kolom afhangt en niet van dit ene kaartje. Toetsenbord en touch
 * gebruiken de keuzelijst "Naar groep" op het kaartje (gewoon formulier, geen JavaScript nodig).
 * "Niet samen met": de naamknop opent een venster met vinkjes; elk vinkje slaat meteen op.
 */
(function () {
  'use strict';
  var raster = document.querySelector('[data-groepsindeling]');
  if (!raster) return;
  var mag = raster.dataset.mag === '1';
  var data = {};
  try { data = JSON.parse((document.getElementById('gi-data') || {}).textContent || '{}'); } catch (e) { data = {}; }
  var kandidaten = data.kandidaten || [];
  var relaties = data.relaties || {};

  function post(velden) {
    var body = new URLSearchParams(Object.assign({ _csrf: raster.dataset.csrf }, velden));
    return fetch(raster.dataset.endpoint, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
      .then(function (r) { return r.json(); });
  }
  function toast(t) { if (window.bhToast) window.bhToast(t, 'fout'); }

  function hertel() {
    raster.querySelectorAll('.bh-groepkolom-body').forEach(function (body) {
      var teller = raster.querySelector('[data-telling="' + (body.dataset.groep || '0') + '"]');
      if (teller) teller.textContent = body.querySelectorAll('.bh-kandkaart').length;
    });
  }

  if (!mag) return;

  // ── Slepen ──
  var sleep = null;
  raster.querySelectorAll('.bh-kandkaart').forEach(function (kaart) {
    kaart.addEventListener('dragstart', function () { sleep = kaart; kaart.classList.add('sleept'); });
    kaart.addEventListener('dragend', function () { kaart.classList.remove('sleept'); sleep = null; });
  });
  raster.querySelectorAll('.droppable').forEach(function (zone) {
    zone.addEventListener('dragover', function (e) { e.preventDefault(); zone.classList.add('doel'); });
    zone.addEventListener('dragleave', function () { zone.classList.remove('doel'); });
    zone.addEventListener('drop', function (e) {
      e.preventDefault();
      zone.classList.remove('doel');
      if (!sleep) return;
      var solId = sleep.dataset.sol;
      zone.appendChild(sleep);
      hertel();
      post({ actie: 'verplaats', sol_id: solId, groep_id: zone.dataset.groep })
        .then(function (d) {
          if (!d.ok) toast('Verplaatsen niet opgeslagen.');
          // Bij een fout wat langer wachten, zodat de melding te lezen is vóór het kaartje terugspringt.
          setTimeout(function () { location.reload(); }, d.ok ? 120 : 1800);
        })
        .catch(function () { toast('Verplaatsen niet opgeslagen.'); });
    });
  });

  // ── Niet samen met ──
  var venster = document.getElementById('relModal');
  if (!venster) return;
  var lijst = venster.querySelector('[data-rel-lijst]');
  var gewijzigd = false;

  function zet(a, b, aan) {
    relaties[a] = relaties[a] || [];
    relaties[b] = relaties[b] || [];
    if (aan) {
      if (relaties[a].indexOf(b) === -1) relaties[a].push(b);
      if (relaties[b].indexOf(a) === -1) relaties[b].push(a);
    } else {
      relaties[a] = relaties[a].filter(function (x) { return x !== b; });
      relaties[b] = relaties[b].filter(function (x) { return x !== a; });
    }
    gewijzigd = true;
    post({ actie: 'relatie', a: a, b: b, toevoegen: aan ? '1' : '0' })
      .then(function (d) { if (!d.ok) toast('Niet opgeslagen.'); })
      .catch(function () { toast('Niet opgeslagen.'); });
  }

  function open(solId, naam) {
    venster.querySelector('[data-rel-naam]').textContent = naam;
    lijst.innerHTML = '';
    var mijn = relaties[solId] || [];
    kandidaten.filter(function (k) { return k.id !== solId; }).forEach(function (k) {
      var rij = document.createElement('label');
      rij.className = 'bh-rel-rij';
      var vak = document.createElement('input');
      vak.type = 'checkbox';
      vak.checked = mijn.indexOf(k.id) !== -1;
      vak.addEventListener('change', function (e) { zet(solId, k.id, e.target.checked); });
      rij.appendChild(vak);
      rij.appendChild(document.createTextNode(' ' + k.naam));
      lijst.appendChild(rij);
    });
    venster.showModal();
  }

  raster.addEventListener('click', function (e) {
    var knop = e.target.closest('[data-rel-kies]');
    if (knop) open(parseInt(knop.dataset.relKies, 10), knop.dataset.naam || '');
  });
  venster.querySelector('[data-rel-sluit]').addEventListener('click', function () { venster.close(); });
  // Botsingen hangen van de hele kolom af: na een wijziging één keer herladen.
  venster.addEventListener('close', function () { if (gewijzigd) location.reload(); });
})();
