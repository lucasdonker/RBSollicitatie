/*
 * Paal 13 — Volgorde door te slepen (lijsten met <ul data-volgorde data-url data-csrf>, items <li data-id>).
 * Gebruikt door de gesprekkenlijst van het trainingsweekend (admin/tw/gesprekken.php).
 *
 * Slepen is een extraatje: de pijltjes in elke rij doen hetzelfde, zonder JavaScript en met het
 * toetsenbord. Na loslaten gaat de hele volgorde naar de server (actie gesprek_volgorde_lijst) en
 * wordt de pagina herladen, zodat nummers en knoppen kloppen met wat er is opgeslagen.
 */
(function () {
  'use strict';
  function koppelAlles() { document.querySelectorAll('ul[data-volgorde]').forEach(koppel); }
  // Live bijwerken (assets/js/live.js) zet een nieuwe lijst neer: die ook koppelen.
  document.addEventListener('p13:live', koppelAlles);
  koppelAlles();

  function koppel(lijst) {
    if (lijst.dataset.volgordeGekoppeld) return;
    lijst.dataset.volgordeGekoppeld = '1';
    var sleept = null;

    lijst.addEventListener('dragstart', function (e) {
      var li = e.target.closest && e.target.closest('li[data-id]');
      if (!li || li.parentNode !== lijst) return;
      sleept = li;
      li.classList.add('sleept');
      if (e.dataTransfer) { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', li.dataset.id); }
    });

    lijst.addEventListener('dragover', function (e) {
      if (!sleept) return;
      var li = e.target.closest && e.target.closest('li[data-id]');
      if (!li || li === sleept || li.parentNode !== lijst) return;
      e.preventDefault();
      var r = li.getBoundingClientRect();
      var erna = (e.clientY - r.top) > r.height / 2;
      lijst.insertBefore(sleept, erna ? li.nextSibling : li);
    });

    lijst.addEventListener('drop', function (e) { if (sleept) e.preventDefault(); });

    lijst.addEventListener('dragend', function () {
      if (!sleept) return;
      sleept.classList.remove('sleept');
      var id = sleept.dataset.id;
      sleept = null;
      var volgorde = Array.prototype.map.call(lijst.querySelectorAll(':scope > li[data-id]'), function (li) { return li.dataset.id; });
      var body = new URLSearchParams({ actie: 'gesprek_volgorde_lijst', volgorde: volgorde.join(','), sol_id: id, ajax: '1', _csrf: lijst.dataset.csrf || '' });
      fetch(lijst.dataset.url || location.href, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d.ok) throw new Error(d.melding || 'Volgorde niet opgeslagen.');
          location.reload();
        })
        .catch(function (err) {
          if (window.bhToast) window.bhToast(err.message || 'Geen verbinding — probeer opnieuw.', 'fout');
        });
    });
  }
})();
