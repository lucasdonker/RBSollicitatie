/**
 * Paal 13 — zelf inchecken op de tablet (portaal/incheck.php).
 *
 * Typen → hooguit drie treffers met foto → "Dit ben ik" → welkom (met groep op de selectiedag)
 * → na tien seconden vanzelf terug voor de volgende. Geen lijst van iedereen: de tablet staat
 * bij de ingang, voor iedereen zichtbaar.
 */
(function () {
  'use strict';
  var root = document.querySelector('[data-kiosk]');
  if (!root) return;
  var ev = root.dataset.ev, code = root.dataset.code;
  var zoek = document.getElementById('kioskZoek');
  var lijst = root.querySelector('[data-resultaten]');
  var timer = null, terug = null;
  var laatste = null;   // {sol_id, token} van de laatste check-in, voor "Dit ben ik niet"
  var nietIk = root.querySelector('[data-niet-ik]');
  var melding = root.querySelector('[data-kiosk-melding]');

  function post(velden) {
    var body = new URLSearchParams(Object.assign({ ev: ev, code: code }, velden));
    return fetch(location.pathname, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
      .then(function (r) { return r.json(); });
  }

  function el(tag, cls, tekst) { var e = document.createElement(tag); if (cls) e.className = cls; if (tekst != null) e.textContent = tekst; return e; }

  function toon(res) {
    lijst.innerHTML = '';
    if (!res.length) {
      lijst.appendChild(el('li', 'pt-kiosk-geen', 'Geen naam gevonden. Vraag iemand van de organisatie om hulp.'));
      return;
    }
    res.forEach(function (k) {
      var li = el('li', 'pt-kiosk-rij' + (k.aanwezig ? ' al' : ''));
      var av = el('span', 'pt-kiosk-avatar');
      if (k.foto) { var img = document.createElement('img'); img.src = k.foto; img.alt = ''; av.appendChild(img); }
      else av.textContent = k.initialen || '?';
      var tekst = el('span', 'pt-kiosk-naam', k.naam);
      var sub = el('span', 'pt-kiosk-sub', k.aanwezig ? 'al ingecheckt' : (k.groep_nummer ? (k.groep_naam || 'groep ' + k.groep_nummer) : ''));
      tekst.appendChild(sub);
      var knop = el('button', 'pt-kiosk-knop', k.aanwezig ? 'Dat ben ik' : 'Dit ben ik');
      knop.type = 'button';
      knop.setAttribute('aria-label', 'Inchecken als ' + k.naam);
      knop.addEventListener('click', function () { inchecken(k, knop); });
      li.appendChild(av); li.appendChild(tekst); li.appendChild(knop);
      lijst.appendChild(li);
    });
    // De server geeft er hooguit drie: misschien staat je naam er dan nog niet bij.
    if (res.length >= 3) lijst.appendChild(el('li', 'pt-kiosk-geen', 'Staat je naam er niet bij? Typ verder.'));
  }

  function inchecken(k, knop) {
    knop.disabled = true;
    post({ actie: 'inchecken', sol_id: k.sol_id }).then(function (d) {
      if (!d.ok) throw new Error('nee');
      root.querySelector('[data-klaar-naam]').textContent = 'Welkom, ' + k.naam.split(' ')[0] + '!';
      var groep = root.querySelector('[data-klaar-groep]');
      if (d.groep_nummer) {
        root.querySelector('[data-klaar-tekst]').textContent = 'Je bent ingecheckt. Je zit in:';
        groep.textContent = d.groep_naam || ('Groep ' + d.groep_nummer);
        groep.hidden = false;
      } else {
        root.querySelector('[data-klaar-tekst]').textContent = ev === 'tw'
          ? 'Je bent ingecheckt. Meld je bij de organisatie.'
          : 'Je bent ingecheckt. Meld je bij de organisatie voor je groep.';
        groep.hidden = true;
      }
      // Nog iets te tekenen: groot in beeld, en langer laten staan zodat het gelezen wordt.
      var letop = root.querySelector('[data-klaar-letop]');
      var open = d.formulieren || [];
      letop.hidden = !open.length;
      if (open.length) {
        letop.querySelector('[data-letop-wat]').textContent = open.join(' en ');
        letop.querySelector('[data-letop-tekst]').textContent = d.formulieren_tekst || '';
      }
      // Verkeerde naam getikt? Binnen een minuut terug te draaien (alleen een nieuwe check-in).
      laatste = d.ongedaan ? { sol_id: k.sol_id, token: d.ongedaan } : null;
      nietIk.hidden = !laatste;
      if (melding) melding.textContent = '';
      root.querySelector('[data-stap="zoek"]').hidden = true;
      root.querySelector('[data-stap="klaar"]').hidden = false;
      terug = setTimeout(opnieuw, open.length ? 25000 : 10000);
    }).catch(function () {
      knop.disabled = false;
      knop.textContent = 'Opnieuw proberen';
    });
  }

  function opnieuw() {
    clearTimeout(terug);
    laatste = null; nietIk.hidden = true;
    zoek.value = ''; lijst.innerHTML = '';
    root.querySelector('[data-stap="klaar"]').hidden = true;
    root.querySelector('[data-stap="zoek"]').hidden = false;
    zoek.focus();
  }

  zoek.addEventListener('input', function () {
    clearTimeout(timer);
    var term = zoek.value.trim();
    if (term.length < 3) { lijst.innerHTML = ''; return; }
    timer = setTimeout(function () {
      post({ actie: 'zoek', term: term }).then(function (d) { if (d.ok) toon(d.resultaten); }).catch(function () {});
    }, 250);
  });
  root.querySelector('[data-volgende]').addEventListener('click', opnieuw);
  nietIk.addEventListener('click', function () {
    if (!laatste) return;
    var l = laatste;
    post({ actie: 'ongedaan', sol_id: l.sol_id, token: l.token }).then(function (d) {
      opnieuw();
      if (melding) melding.textContent = d.ok ? 'Teruggedraaid. Zoek nu je eigen naam.' : (d.fout || 'Dat lukte niet.');
    }).catch(function () { if (melding) melding.textContent = 'Geen verbinding. Vraag iemand van de organisatie.'; });
  });

  root.querySelector('[data-afsluiten]').addEventListener('submit', function (e) {
    e.preventDefault();
    var pin = document.getElementById('kioskPin');
    var melding = root.querySelector('[data-org-melding]');
    post({ actie: 'afsluiten', pin: pin.value }).then(function (d) {
      pin.value = '';
      melding.textContent = d.ok
        ? 'Afgesloten: ' + d.aantal + ' op niet verschenen. Laatkomers kunnen nog inchecken.'
        : (d.fout || 'Dat lukte niet.');
    }).catch(function () { melding.textContent = 'Geen verbinding.'; });
  });

  zoek.focus();
})();
