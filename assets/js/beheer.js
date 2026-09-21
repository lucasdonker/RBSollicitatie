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

  /* ── Tekst veilig in een HTML-string ────────────────────────────
     Wie met innerHTML een blok opbouwt, moet elke waarde die van de server komt
     hierdoorheen halen. Eén plek, zodat er niet per scherm een eigen (vergeten)
     variant ontstaat. Liefst bouw je met textContent — maar niet alles kan dat. */
  function bhTekst(waarde) {
    var d = document.createElement('div');
    d.textContent = waarde == null ? '' : String(waarde);
    return d.innerHTML;
  }

  /* ── Toast: bevestiging van wat je zelf net deed ────────────────
     Voor iets dat je moet wéten staat een vaste melding boven de pagina; die
     verdwijnt niet vanzelf (ontwerpfilosofie §8). Deze verdwijnt wel. */

  function toastHost() {
    var h = document.getElementById('bh-toasts');
    if (!h) {
      h = document.createElement('div');
      h.id = 'bh-toasts';
      h.setAttribute('aria-live', 'polite');
      document.body.appendChild(h);
    }
    return h;
  }

  function bhToast(tekst, soort) {
    var d = document.createElement('div');
    d.className = 'bh-toast' + (soort ? ' ' + soort : '');
    d.textContent = tekst;
    toastHost().appendChild(d);
    setTimeout(function () { d.classList.add('weg'); }, 4200);
    setTimeout(function () { d.remove(); }, 4700);
    return d;
  }

  /* ── Bevestiging ────────────────────────────────────────────────
     Een <dialog>: focus-trap, Escape en de achtergrond-afdekking krijg je gratis van
     de browser. confirm() kon dat niet, was niet te stylen en niet te screenshotten.
     Geeft een Promise, zodat de aanroeper leest als een gesprek. */

  function bhBevestig(opt) {
    opt = opt || {};
    return new Promise(function (klaar) {
      var dlg = document.createElement('dialog');
      dlg.className = 'bh-dialoog' + (opt.gevaar ? ' gevaar' : '');
      var titel = document.createElement('h2');
      titel.textContent = opt.titel || 'Weet je het zeker?';
      dlg.appendChild(titel);

      if (opt.tekst) {
        var p = document.createElement('p');
        p.textContent = opt.tekst;
        dlg.appendChild(p);
      }

      /* Eén optioneel vinkje in de dialoog, bijvoorbeeld "zonder bericht versturen".
         De keuze hoort bij het moment van besluiten, niet in een instelling die
         iedereen raakt. Bij ja krijgt de aanroeper true of {vink:true}. */
      var vink = null;
      if (opt.vink) {
        var lab = document.createElement('label');
        lab.className = 'bh-dialoog-vink';
        vink = document.createElement('input');
        vink.type = 'checkbox';
        if (opt.vink.aan) vink.checked = true;
        var sp = document.createElement('span');
        sp.textContent = opt.vink.label;
        lab.appendChild(vink); lab.appendChild(sp);
        dlg.appendChild(lab);
        if (opt.vink.uitleg) {
          var hu = document.createElement('p');
          hu.className = 'bh-dialoog-vinkhulp';
          hu.textContent = opt.vink.uitleg;
          dlg.appendChild(hu);
        }
      }

      var rij = document.createElement('div');
      rij.className = 'bh-dialoog-knoppen';
      var nee = document.createElement('button');
      nee.type = 'button';
      nee.className = 'ds-btn ds-btn-zacht';
      nee.textContent = opt.annuleer || 'Annuleren';
      var ja = document.createElement('button');
      ja.type = 'button';
      ja.className = 'ds-btn ' + (opt.gevaar ? 'ds-btn-gevaar' : 'ds-btn-primair');
      ja.textContent = opt.knop || 'Doorgaan';
      rij.appendChild(nee); rij.appendChild(ja);
      dlg.appendChild(rij);
      document.body.appendChild(dlg);

      function sluit(antwoord) {
        if (dlg.open) dlg.close();
        dlg.remove();
        klaar(antwoord);
      }
      nee.addEventListener('click', function () { sluit(false); });
      ja.addEventListener('click', function () {
        sluit(vink ? { vink: vink.checked } : true);
      });
      dlg.addEventListener('cancel', function (e) { e.preventDefault(); sluit(false); });

      if (typeof dlg.showModal === 'function') { dlg.showModal(); ja.focus(); }
      else { sluit(window.confirm(titel.textContent)); }   // heel oude browser
    });
  }

  /* Vraagt één waarde. Vervangt prompt(): die is niet te stylen, negeert de donkere
     modus en geeft op mobiel een dialoog die niet bij de pagina hoort. */
  function bhVraag(opt) {
    opt = opt || {};
    return new Promise(function (klaar) {
      var dlg = document.createElement('dialog');
      dlg.className = 'bh-dialoog';
      var titel = document.createElement('h2');
      titel.textContent = opt.titel || 'Invullen';
      dlg.appendChild(titel);
      if (opt.tekst) {
        var p = document.createElement('p');
        p.textContent = opt.tekst;
        dlg.appendChild(p);
      }
      var veld = document.createElement(opt.lang ? 'textarea' : 'input');
      veld.className = opt.lang ? 'ds-textarea' : 'ds-input';
      if (!opt.lang) veld.type = opt.type || 'text';
      veld.value = opt.waarde || '';
      if (opt.plaatshouder) veld.placeholder = opt.plaatshouder;
      veld.style.marginTop = '12px';
      dlg.appendChild(veld);

      var rij = document.createElement('div');
      rij.className = 'bh-dialoog-knoppen';
      var nee = document.createElement('button');
      nee.type = 'button'; nee.className = 'ds-btn ds-btn-zacht'; nee.textContent = 'Annuleren';
      var ja = document.createElement('button');
      ja.type = 'button'; ja.className = 'ds-btn ds-btn-primair'; ja.textContent = opt.knop || 'Opslaan';
      rij.appendChild(nee); rij.appendChild(ja);
      dlg.appendChild(rij);
      document.body.appendChild(dlg);

      function sluit(waarde) { if (dlg.open) dlg.close(); dlg.remove(); klaar(waarde); }
      nee.addEventListener('click', function () { sluit(null); });
      ja.addEventListener('click', function () { sluit(veld.value.trim()); });
      veld.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !opt.lang) { e.preventDefault(); ja.click(); }
      });
      dlg.addEventListener('cancel', function (e) { e.preventDefault(); sluit(null); });

      if (typeof dlg.showModal === 'function') { dlg.showModal(); veld.focus(); veld.select(); }
      else { sluit(window.prompt(titel.textContent, veld.value)); }
    });
  }

  window.bhTekst = bhTekst;
  window.bhToast = bhToast;
  window.bhBevestig = bhBevestig;
  window.bhVraag = bhVraag;
  // Zolang oude schermen de oude naam nog aanroepen. Weg als grep 0 geeft.
  window.toastMelding = function (t, s) { return bhToast(t, s === 'fout' ? 'fout' : (s === 'succes' ? 'succes' : '')); };

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

  /* ── Zijbalk op mobiel ────────────────────────────────────────
     Eén implementatie. Eerder stond deze twee keer — in layout_top én in
     layout_bottom, met verschillende klassenamen en verschillende breekpunten
     (768 tegen 900), waarbij de tweede de eerste stil overschreef. */

  function zijbalkKoppelen() {
    var balk = document.getElementById('zijbalk');
    var knop = document.getElementById('hamburger');
    var laag = document.getElementById('navoverlay');
    if (!balk || !knop) return;

    function zet(open) {
      balk.classList.toggle('open', open);
      knop.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (laag) {
        laag.hidden = !open;
        requestAnimationFrame(function () { laag.classList.toggle('zichtbaar', open); });
      }
      document.body.style.overflow = open ? 'hidden' : '';
      if (open) balk.querySelector('a').focus();
    }

    knop.addEventListener('click', function () { zet(!balk.classList.contains('open')); });
    if (laag) laag.addEventListener('click', function () { zet(false); });
    balk.addEventListener('click', function (e) {
      if (e.target.closest('[data-sluit-nav]')) zet(false);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && balk.classList.contains('open')) { zet(false); knop.focus(); }
    });
    // Zelfde breekpunt als de media-query: anders blijft de balk op een tablet hangen.
    window.addEventListener('resize', function () {
      if (window.innerWidth > 900 && balk.classList.contains('open')) zet(false);
    });
  }

  /* ── Hulppaneel in de topbalk ─────────────────────────────────── */

  function hulpKoppelen() {
    var knop = document.getElementById('hulpknop');
    var paneel = document.getElementById('hulppaneel');
    if (!knop || !paneel) return;
    function zet(open) {
      paneel.hidden = !open;
      knop.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    knop.addEventListener('click', function () { zet(paneel.hidden); });
    paneel.addEventListener('click', function (e) {
      if (e.target.closest('.bh-hulppaneel-sluit')) { zet(false); knop.focus(); }
    });
  }

  /* ── Start ───────────────────────────────────────────────────── */

  /* ── Bevestiging op een gewoon formulier ──────────────────────
     <form data-bevestig="Wat er gebeurt als je doorgaat."> vraagt het eerst.
     Eén haak voor alle schermen, zodat er geen confirm() terugsluipt. Zonder
     JavaScript verzendt het formulier gewoon — net als voorheen. Is de actie
     onomkeerbaar én raakt hij persoonsgegevens, gebruik dan een tussenscherm
     (_bevestig.php): dat is te screenshotten en werkt ook zonder JS. */
  function bevestigFormKoppelen(form) {
    if (form.dataset.bhBevestigGekoppeld) return;
    form.dataset.bhBevestigGekoppeld = '1';
    form.addEventListener('submit', function (e) {
      if (form.dataset.bhAkkoord === '1') { form.dataset.bhAkkoord = ''; return; }
      e.preventDefault();
      bhBevestig({
        titel: form.dataset.bevestigTitel || 'Weet je het zeker?',
        tekst: form.dataset.bevestig,
        knop:  form.dataset.bevestigKnop || 'Doorgaan',
        gevaar: form.dataset.bevestigGevaar === '1'
      }).then(function (ja) {
        if (!ja) return;
        form.dataset.bhAkkoord = '1';
        if (typeof form.requestSubmit === 'function') form.requestSubmit(); else form.submit();
      });
    });
  }

  function start(wortel) {
    (wortel || document).querySelectorAll('.bh-tabel').forEach(tabelKoppelen);
    (wortel || document).querySelectorAll('[data-kolomkiezer]').forEach(kiezerKoppelen);
    (wortel || document).querySelectorAll('.bh-filterbalk').forEach(filterKoppelen);
    (wortel || document).querySelectorAll('form[data-bevestig]').forEach(bevestigFormKoppelen);
    if (!wortel || wortel === document) { zijbalkKoppelen(); hulpKoppelen(); }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { start(document); });
  } else {
    start(document);
  }

  window.bhStart = start;   // voor blokken die later in de DOM komen
})();
