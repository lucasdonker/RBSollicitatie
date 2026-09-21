/**
 * Paal 13 — "er gebeurt iets". Gedeeld door het portaal en het beheer.
 *
 * Het probleem dat dit oplost: je drukt op Verzenden, er gebeurt een halve seconde
 * niets zichtbaars, en je weet niet of je knop wel aankwam. Dus druk je nog eens, of
 * verander je nog iets terwijl het verzoek al onderweg is. Dat laatste is niet alleen
 * verwarrend maar ook fout: wat je daarna typt gaat niet meer mee.
 *
 * Drie dingen, alle drie zonder dat een scherm er iets voor hoeft te doen:
 *   1. een voortgangsbalk bovenaan zodra je iets start, ook bij een gewone link;
 *   2. de knop waarop je drukte gaat in laadstand en is niet nog eens in te drukken;
 *   3. het formulier wordt onaanraakbaar (inert) tot het antwoord er is.
 *
 * Waarom inert en niet disabled: een uitgeschakeld veld stuurt zijn waarde niet mee,
 * en een uitgeschakelde submitknop stuurt zijn name/value niet mee — dat zou stil
 * formulieren breken die op die waarde rekenen (actie=verzend). inert maakt alleen
 * onaanraakbaar; de browser verstuurt gewoon alles.
 *
 * Valt dit bestand weg, dan werkt alles nog — het voelt alleen doder.
 */
(function () {
  'use strict';

  /* ── Voortgangsbalk ──────────────────────────────────────────────
     Kruipt naar 90% en blijft daar hangen: de rest komt pas als de pagina er
     echt is. Beter een balk die wacht dan een balk die klaar zegt te zijn. */
  var balk = null, kruip = null;

  function balkStart() {
    if (balk) return;
    balk = document.createElement('div');
    balk.className = 'p13-bezigbalk';
    balk.setAttribute('role', 'progressbar');
    balk.setAttribute('aria-label', 'Bezig');
    document.body.appendChild(balk);
    var breed = 0;
    kruip = setInterval(function () {
      breed += (90 - breed) * 0.12;                 // steeds trager richting 90%
      balk.style.width = breed.toFixed(1) + '%';
    }, 120);
  }

  function balkKlaar() {
    if (!balk) return;
    clearInterval(kruip);
    balk.style.width = '100%';
    var weg = balk; balk = null;
    setTimeout(function () { weg.classList.add('klaar'); }, 60);
    setTimeout(function () { weg.remove(); }, 400);
  }

  /* ── Knop in laadstand ──────────────────────────────────────────
     De tekst blijft staan en er komt een ring naast. Tekst vervangen door
     "Bezig…" laat de knop verspringen en kost je net het houvast waar je naar
     keek. */
  function knopBezig(knop) {
    if (!knop || knop.dataset.p13Bezig === '1') return;
    knop.dataset.p13Bezig = '1';
    knop.setAttribute('aria-busy', 'true');

    // Portaalknoppen dragen soms hun eigen spinner in de markup
    // (<span class="btn-tekst"> + <span class="btn-spinner">). Die gebruiken we
    // dan, in plaats van er een tweede ring naast te zetten.
    var t = knop.querySelector ? knop.querySelector('.btn-tekst') : null;
    var s = knop.querySelector ? knop.querySelector('.btn-spinner') : null;
    if (t && s) { t.hidden = true; s.hidden = false; knop.dataset.p13Eigen = '1'; return; }
    knop.classList.add('p13-bezig');
  }

  function knopVrij(knop) {
    if (!knop) return;
    knop.dataset.p13Bezig = '';
    knop.classList.remove('p13-bezig');
    knop.removeAttribute('aria-busy');
    if (knop.dataset.p13Eigen === '1') {
      knop.dataset.p13Eigen = '';
      var t = knop.querySelector('.btn-tekst'), s = knop.querySelector('.btn-spinner');
      if (t && s) { t.hidden = false; s.hidden = true; }
    }
  }

  /**
   * Zet een formulier in de wachtstand. Openbaar als window.P13Actie.bezig(),
   * zodat een scherm dat zijn eigen fetch doet hetzelfde gedrag krijgt.
   * Geeft de functie terug die alles weer vrijgeeft.
   */
  function bezig(form, knop) {
    balkStart();
    knopBezig(knop);
    if (form) {
      form.dataset.p13Loopt = '1';
      // inert bestaat sinds 2023 in alle browsers; zonder valt alleen het
      // vergrendelen weg, niet de rest.
      if ('inert' in HTMLElement.prototype) form.inert = true;
      form.setAttribute('aria-busy', 'true');
    }
    return function vrij() {
      balkKlaar();
      knopVrij(knop);
      if (form) {
        form.dataset.p13Loopt = '';
        if ('inert' in HTMLElement.prototype) form.inert = false;
        form.removeAttribute('aria-busy');
      }
    };
  }

  /* ── Formulieren ────────────────────────────────────────────────── */

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.hasAttribute('data-geen-bezig')) return;
    if (e.defaultPrevented) return;                 // iemand anders handelt het af

    // Een tweede submit terwijl de eerste loopt: tegenhouden. Dubbel verzenden is
    // op dit systeem niet onschuldig — het kan een tweede mail betekenen.
    if (form.dataset.p13Loopt === '1') { e.preventDefault(); return; }

    // GET-formulieren (filterbalk, zoeken) zijn goedkoop; alleen de balk, geen
    // vergrendeling, anders flikkert het bij elke filterwijziging.
    var post = (form.method || 'get').toLowerCase() === 'post';
    var knop = e.submitter || form.querySelector('[type=submit]');
    if (!post) { balkStart(); return; }

    bezig(form, knop);
  }, true);

  /* ── Links ──────────────────────────────────────────────────────
     Een interne link laadt een nieuwe pagina; daar zit dezelfde stilte. */
  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('a[href]') : null;
    if (!a || e.defaultPrevented || e.button !== 0) return;
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;   // nieuw tabblad
    if (a.target && a.target !== '_self') return;
    if (a.hasAttribute('download') || a.hasAttribute('data-geen-bezig')) return;
    var href = a.getAttribute('href') || '';
    if (!href || href[0] === '#' || /^(mailto:|tel:|javascript:)/i.test(href)) return;
    if (a.origin && a.origin !== window.location.origin) return;    // ander domein
    balkStart();
  }, true);

  /* Terug-knop: de pagina komt uit de cache en is dan gewoon weer bruikbaar.
     Zonder dit blijft een vergrendeld formulier vergrendeld staan. */
  window.addEventListener('pageshow', function (e) {
    if (!e.persisted) return;
    balkKlaar();
    document.querySelectorAll('form[data-p13-loopt="1"]').forEach(function (f) {
      f.dataset.p13Loopt = '';
      if ('inert' in HTMLElement.prototype) f.inert = false;
      f.removeAttribute('aria-busy');
    });
    document.querySelectorAll('.p13-bezig').forEach(knopVrij);
  });

  window.addEventListener('pagehide', balkKlaar);

  window.P13Actie = { bezig: bezig, balkStart: balkStart, balkKlaar: balkKlaar };
})();
