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

  // Een fout blijft staan tot je hem sluit; een bevestiging gaat na 8 seconden weg, maar
  // wacht zolang de muis of de focus erop staat (WCAG 2.2.1: genoeg tijd om te lezen).
  function bhToast(tekst, soort) {
    var d = document.createElement('div');
    d.className = 'bh-toast' + (soort ? ' ' + soort : '');
    if (soort === 'fout') d.setAttribute('role', 'alert');
    var t = document.createElement('span');
    t.textContent = tekst;
    d.appendChild(t);
    var sluit = document.createElement('button');
    sluit.type = 'button';
    sluit.className = 'bh-toast-sluit';
    sluit.setAttribute('aria-label', 'Melding sluiten');
    sluit.textContent = '×';
    d.appendChild(sluit);
    toastHost().appendChild(d);

    var klok = null;
    function weg() { d.classList.add('weg'); setTimeout(function () { d.remove(); }, 400); }
    function start() { if (soort !== 'fout') klok = setTimeout(weg, 8000); }
    function pauze() { clearTimeout(klok); }
    sluit.addEventListener('click', weg);
    d.addEventListener('mouseenter', pauze);
    d.addEventListener('mouseleave', start);
    d.addEventListener('focusin', pauze);
    d.addEventListener('focusout', start);
    start();
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

      // Zonder showModal() (heel oude browser) gewoon open zetten: nooit terugvallen op
      // confirm(), die afspraak geldt ook hier (CLAUDE.md).
      if (typeof dlg.showModal === 'function') dlg.showModal(); else dlg.setAttribute('open', '');
      // Bij iets gevaarlijks (verwijderen, afwijzen) staat de focus op Annuleren: een Enter
      // uit gewoonte mag dan niets onherroepelijks doen.
      (opt.gevaar ? nee : ja).focus();
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
      veld.className = (opt.lang ? 'ds-textarea' : 'ds-input') + ' bh-dialoog-veld';
      if (!opt.lang) veld.type = opt.type || 'text';
      veld.value = opt.waarde || '';
      if (opt.plaatshouder) veld.placeholder = opt.plaatshouder;
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

      if (typeof dlg.showModal === 'function') dlg.showModal(); else dlg.setAttribute('open', '');
      veld.focus(); veld.select();
    });
  }

  window.bhTekst = bhTekst;
  window.bhToast = bhToast;
  window.bhBevestig = bhBevestig;
  window.bhVraag = bhVraag;
  // Zolang oude schermen de oude naam nog aanroepen. Weg als grep 0 geeft.
  /* toastMelding was de naam van vóór de herbouw. De laatste aanroep is weg, dus de
     alias ook — een tweede naam voor hetzelfde is precies hoe er weer twee varianten
     ontstaan. */

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

    /* En hoe smal de tabel mag worden voordat hij gaat schuiven. Dezelfde optelling
       als bh_kolommen() in PHP: zonder dit zou de tabel na het aanzetten van een paar
       kolommen weer kolommen afsnijden tot je de pagina herlaadt. */
    var min = 0;
    delen.forEach(function (track) {
      var m = /minmax\(\s*(\d+)px/i.exec(track) || /^\s*(\d+)px\s*$/.exec(track);
      min += m ? parseInt(m[1], 10) : 90;
    });
    min += Math.max(0, delen.length - 1) * 14 + 32;
    tabel.style.setProperty('--bh-min', min + 'px');
  }

  /**
   * Zet een kolom aan of uit. Geeft false terug als de kolom niet in de HTML staat —
   * dan moet de server hem eerst leveren.
   */
  function kolomZetten(tabel, sleutel, aan) {
    var i = -1;
    var koppen = tabel.querySelectorAll('thead th');
    Array.prototype.forEach.call(koppen, function (th, idx) {
      if (th.dataset.kolom === sleutel) i = idx;
    });
    if (i < 0) return false;
    koppen[i].hidden = !aan;
    Array.prototype.forEach.call(tabel.querySelectorAll('tbody tr'), function (rij) {
      var cellen = rij.children;
      if (cellen[i]) cellen[i].hidden = !aan;
    });
    tracksHerberekenen(tabel);
    return true;
  }

  /* De keuze gaat in een cookie met het seizoen-id erin, zodat de server hem al bij de
     eerste render kent en de kolommen niet verspringen. Een kolom die uitstaat wordt
     server-side niet gerenderd: de gegevens staan dan ook niet in de HTML. Dat is
     bewust — een uitgezette kolom "Bijzonderheden" hoort niet onzichtbaar in de pagina
     mee te reizen. */
  function kolomkeuzeBewaren(tabel, ookAan) {
    var aan = ookAan ? ookAan.slice() : [];
    Array.prototype.forEach.call(tabel.querySelectorAll('thead th[data-kolom]'), function (th) {
      if (!th.hidden && aan.indexOf(th.dataset.kolom) === -1) aan.push(th.dataset.kolom);
    });
    var naam = tabel.dataset.kolomcookie;
    if (!naam) return;
    document.cookie = naam + '=' + aan.join(',') + ';path=/;max-age=15552000;samesite=lax';
  }

  function kiezerKoppelen(kiezer) {
    var tabel = document.getElementById(kiezer.dataset.voor);
    if (!tabel) return;

    /* Kolommen die de server nog moet leveren. Ze worden verzameld en pas opgehaald
       als je de kiezer dichtklapt: vink je er vier aan, dan is dat één keer wachten
       in plaats van vier keer. */
    var wachtend = [];

    kiezer.addEventListener('change', function (e) {
      if (!e.target.matches('input[type=checkbox][data-kolom]')) return;
      var sleutel = e.target.dataset.kolom;

      if (kolomZetten(tabel, sleutel, e.target.checked)) {
        kolomkeuzeBewaren(tabel, wachtend);
        tellerBijwerken(kiezer);
        return;
      }
      // Staat niet in de HTML: hij stond uit, dus de server stuurde hem niet mee.
      var i = wachtend.indexOf(sleutel);
      if (e.target.checked) { if (i === -1) wachtend.push(sleutel); }
      else if (i !== -1) { wachtend.splice(i, 1); }
      kolomkeuzeBewaren(tabel, wachtend);
      toonWachtend(kiezer, wachtend.length);
      tellerBijwerken(kiezer);
    });

    kiezer.addEventListener('toggle', function () {
      if (kiezer.open || !wachtend.length) return;
      if (window.P13Actie) window.P13Actie.balkStart();
      location.reload();
    });
  }

  /** "Kolommen 6/16" op de knop, zodat het getal klopt met wat je net aanvinkte. */
  function tellerBijwerken(kiezer) {
    var getal = kiezer.querySelector('.bh-kolomkiezer-getal');
    if (!getal) return;
    var vinkjes = kiezer.querySelectorAll('input[type=checkbox][data-kolom]');
    var aan = 0;
    Array.prototype.forEach.call(vinkjes, function (v) { if (v.checked) aan++; });
    getal.textContent = aan + '/' + vinkjes.length;
  }

  /** Zegt in de kiezer dat er nog iets opgehaald moet worden. */
  function toonWachtend(kiezer, aantal) {
    var regel = kiezer.querySelector('[data-kolomwacht]');
    if (!regel) return;
    regel.hidden = aantal === 0;
    regel.textContent = aantal === 0 ? ''
      : (aantal === 1 ? 'Eén kolom wordt opgehaald' : aantal + ' kolommen worden opgehaald')
        + ' zodra je dit lijstje sluit.';
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
      // Ook een datum: met JS is de verzendknop verborgen, anders kon je een datumfilter niet toepassen.
      if (e.target.matches('select, input[type=date]')) form.submit();
    });
  }

  // "Meer filters" en actiemenu's (admin/_actiemenu.php) zijn panelen: klik ernaast of Escape sluit ze.
  document.addEventListener('click', function (e) {
    document.querySelectorAll('details.bh-filter-meer[open], details.bh-filter-menu[open]').forEach(function (p) {
      if (!p.contains(e.target)) p.open = false;
    });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var p = e.target.closest && e.target.closest('details.bh-filter-meer[open], details.bh-filter-menu[open]');
    if (p) { p.open = false; p.querySelector('summary').focus(); }
  });

  /* ── Zijbalk op mobiel ────────────────────────────────────────
     Eén implementatie. Eerder stond deze twee keer — in layout_top én in
     layout_bottom, met verschillende klassenamen en verschillende breekpunten
     (768 tegen 900), waarbij de tweede de eerste stil overschreef. */

  function zijbalkKoppelen() {
    var balk = document.getElementById('zijbalk');
    var knop = document.getElementById('hamburger');
    var laag = document.getElementById('navoverlay');
    if (!balk || !knop) return;
    var smal = window.matchMedia('(max-width: 900px)');

    // Op een smal scherm is een dichte zijbalk buiten beeld geschoven; zonder inert kon je er
    // met Tab nog in belanden zonder iets te zien (WCAG 2.4.3 / 2.4.7). Breed: altijd bereikbaar.
    function inertZetten() {
      balk.inert = smal.matches && !balk.classList.contains('open');
    }

    function zet(open) {
      balk.classList.toggle('open', open);
      inertZetten();
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
    if (smal.addEventListener) smal.addEventListener('change', inertZetten);
    inertZetten();
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
     (_bevestig.php): dat is te screenshotten en werkt ook zonder JS.
     Per keuze een eigen tekst: zet data-bevestig(-titel/-knop/-gevaar) op de radioknoppen;
     die van de aangevinkte keuze gaat voor (bijv. aannemen / twijfel / afwijzen). */
  function bevestigFormKoppelen(form) {
    if (form.dataset.bhBevestigGekoppeld) return;
    form.dataset.bhBevestigGekoppeld = '1';
    form.addEventListener('submit', function (e) {
      if (form.dataset.bhAkkoord === '1') { form.dataset.bhAkkoord = ''; return; }
      // Een knop met data-zonder-bevestig in hetzelfde formulier hoeft niet te vragen
      // (bijv. "Alleen aantal aanpassen" naast "Voorstel maken").
      var knop = e.submitter || null;
      if (knop && knop.hasAttribute('data-zonder-bevestig')) return;
      e.preventDefault();
      var bron = form.querySelector('input:checked[data-bevestig]') || form;
      bhBevestig({
        titel: bron.dataset.bevestigTitel || form.dataset.bevestigTitel || 'Weet je het zeker?',
        tekst: bron.dataset.bevestig,
        knop:  bron.dataset.bevestigKnop || form.dataset.bevestigKnop || 'Doorgaan',
        gevaar: bron.dataset.bevestigGevaar === '1'
      }).then(function (ja) {
        if (!ja) return;
        form.dataset.bhAkkoord = '1';
        // De aangeklikte knop meegeven: anders gaan zijn name/value verloren.
        if (typeof form.requestSubmit === 'function') form.requestSubmit(knop && knop.form === form ? knop : undefined);
        else form.submit();
      });
    });
  }

  /* ── Concept bewaren ──────────────────────────────────────────
     <form data-concept="sleutel">: wat je in de tekstvelden typt blijft op dit apparaat
     (sessionStorage, weg als het tabblad dichtgaat) tot het formulier is verstuurd. Voor
     schermen waar een ánder formulier de pagina herlaadt (bijv. feedback vastleggen in het
     TW-gesprek): anders was het half getypte bericht weg. Teruggezet wordt alleen in een
     veld dat nog de waarde van de server heeft. */
  function conceptKoppelen(form) {
    if (form.dataset.bhConceptGekoppeld || !window.sessionStorage) return;
    form.dataset.bhConceptGekoppeld = '1';
    var sleutel = 'bhconcept:' + form.dataset.concept;
    var velden = form.querySelectorAll('textarea[name], input[type=text][name]');
    try {
      var bewaard = JSON.parse(sessionStorage.getItem(sleutel) || '{}');
      velden.forEach(function (v) {
        if (bewaard[v.name] != null && v.value === v.defaultValue && bewaard[v.name] !== v.value) v.value = bewaard[v.name];
      });
    } catch (e) {}
    form.addEventListener('input', function () {
      var uit = {};
      velden.forEach(function (v) { if (v.value !== v.defaultValue) uit[v.name] = v.value; });
      try { sessionStorage.setItem(sleutel, JSON.stringify(uit)); } catch (e) {}
    });
    // Alleen wissen als het echt verstuurd wordt (niet bij de eerste submit die op bevestiging wacht).
    form.addEventListener('submit', function (e) {
      if (!e.defaultPrevented) { try { sessionStorage.removeItem(sleutel); } catch (err) {} }
    });
  }

  /* ── Snelzoeker (Ctrl+K) ──────────────────────────────────────
     Eén veld voor "waar staat die persoon" en "waar stond die instelling". De
     server beslist wát je mag vinden (admin/api_zoek.php → bh_zoek()); hier staat
     alleen hoe het zich gedraagt.

     Tijdens het typen loopt er een verzoek; dat mag de invoer nooit ophouden. Elke
     toetsaanslag breekt het vorige verzoek af (AbortController) en de uitslag van
     een verlopen verzoek wordt weggegooid — anders knippert de lijst terug naar een
     ouder antwoord dat toevallig later binnenkomt. */
  var zoekTeller = 0, zoekAf = null, zoekWacht = null, zoekAangewezen = -1;

  function zoekKoppelen() {
    var knop = document.getElementById('zoekKnop');
    var venster = document.getElementById('zoekVenster');
    var veld = document.getElementById('zoekVeld');
    if (!knop || !venster || !veld) return;

    function open() {
      if (!venster.open) venster.showModal();
      veld.value = '';
      zoekLeegmaken();
      veld.focus();
    }
    knop.addEventListener('click', open);
    venster.querySelectorAll('[data-zoeksluit]').forEach(function (b) {
      b.addEventListener('click', function () { venster.close(); });
    });
    // Klikken naast het venster sluit het: bij een <dialog> vangt het element zelf
    // de klik op de achtergrond op, dus we kijken of de klik buiten de inhoud viel.
    venster.addEventListener('click', function (e) {
      if (e.target === venster) venster.close();
    });

    document.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
        e.preventDefault();
        venster.open ? venster.close() : open();
      }
    });

    veld.addEventListener('input', function () {
      clearTimeout(zoekWacht);
      zoekWacht = setTimeout(zoekUitvoeren, 130);   // wacht tot je even stopt met typen
    });
    veld.addEventListener('keydown', zoekToets);
  }

  function zoekLeegmaken(bericht) {
    var uit = document.getElementById('zoekUitslag');
    uit.innerHTML = '<p class="bh-zoekhint">' + bhTekst(bericht ||
      'Typ minstens twee letters.') + '</p>';
    zoekAangewezen = -1;
    document.getElementById('zoekVeld').setAttribute('aria-expanded', 'false');
  }

  function zoekUitvoeren() {
    var veld = document.getElementById('zoekVeld');
    var term = veld.value.trim();
    if (term.length < 2) { zoekLeegmaken(); return; }

    var mijn = ++zoekTeller;
    if (zoekAf) zoekAf.abort();
    zoekAf = new AbortController();

    fetch('api_zoek.php?q=' + encodeURIComponent(term), {
      signal: zoekAf.signal, credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (mijn !== zoekTeller) return;            // een nieuwere zoekopdracht won
        if (!d.ok) { zoekLeegmaken(d.fout || 'Zoeken lukte niet.'); return; }
        zoekTonen(d, term);
      })
      .catch(function (e) {
        if (e.name === 'AbortError' || mijn !== zoekTeller) return;
        zoekLeegmaken('Zoeken lukte niet — is de verbinding weg?');
      });
  }

  function zoekTonen(d, term) {
    var uit = document.getElementById('zoekUitslag');
    var html = '';

    if (d.mensen && d.mensen.length) {
      html += '<div class="bh-zoekgroep">Mensen</div>';
      d.mensen.forEach(function (m) {
        html += '<a class="bh-zoekrij" role="option" aria-selected="false" href="sollicitant.php?id=' + (+m.id) + '">'
              + '<span class="bh-zoekrij-tekst">'
              + '<span class="bh-zoekrij-titel">' + bhTekst(m.naam) + '</span>'
              + '<span class="bh-zoekrij-sub">' + bhTekst(m.email) + '</span>'
              + '</span>'
              + '<span class="bh-zoekrij-zij">' + bhTekst(m.fase)
              + (m.huidig ? '' : ' · ' + bhTekst(m.seizoen)) + '</span></a>';
      });
    }
    if (d.plekken && d.plekken.length) {
      html += '<div class="bh-zoekgroep">Schermen</div>';
      d.plekken.forEach(function (p) {
        html += '<a class="bh-zoekrij" role="option" aria-selected="false" href="' + bhTekst(p.url) + '">'
              + '<span class="bh-zoekrij-tekst">'
              + '<span class="bh-zoekrij-titel">' + bhTekst(p.titel) + '</span>'
              + '<span class="bh-zoekrij-sub">' + bhTekst(p.waar) + '</span>'
              + '</span></a>';
      });
    }
    if (!html) {
      zoekLeegmaken('Niets gevonden voor “' + term + '”.');
      return;
    }
    uit.innerHTML = html;
    zoekAangewezen = -1;
    zoekWijzen(0);                                  // de eerste staat meteen klaar
    document.getElementById('zoekVeld').setAttribute('aria-expanded', 'true');
  }

  function zoekWijzen(i) {
    var rijen = document.querySelectorAll('#zoekUitslag .bh-zoekrij');
    if (!rijen.length) return;
    if (i < 0) i = rijen.length - 1;
    if (i >= rijen.length) i = 0;
    rijen.forEach(function (r, n) {
      var aan = n === i;
      r.classList.toggle('aangewezen', aan);
      r.setAttribute('aria-selected', aan ? 'true' : 'false');
      if (aan) r.scrollIntoView({ block: 'nearest' });
    });
    zoekAangewezen = i;
  }

  function zoekToets(e) {
    if (e.key === 'ArrowDown') { e.preventDefault(); zoekWijzen(zoekAangewezen + 1); return; }
    if (e.key === 'ArrowUp')   { e.preventDefault(); zoekWijzen(zoekAangewezen - 1); return; }
    if (e.key === 'Enter') {
      var rij = document.querySelector('#zoekUitslag .bh-zoekrij.aangewezen');
      if (rij) { e.preventDefault(); rij.click(); }
    }
  }

  /* <button data-kopieer="#veld"> kopieert de waarde (of tekst) van dat element. Eén haak,
     één keer aan document: ook voor blokken die later in de DOM komen. */
  document.addEventListener('click', function (e) {
    var knop = e.target.closest && e.target.closest('[data-kopieer]');
    if (!knop) return;
    var bron = document.querySelector(knop.getAttribute('data-kopieer'));
    if (!bron || !navigator.clipboard) return;
    navigator.clipboard.writeText(bron.value !== undefined ? bron.value : bron.textContent)
      .then(function () { bhToast('Gekopieerd.', 'succes'); });
  });

  function start(wortel) {
    (wortel || document).querySelectorAll('.bh-tabel').forEach(tabelKoppelen);
    (wortel || document).querySelectorAll('[data-kolomkiezer]').forEach(kiezerKoppelen);
    (wortel || document).querySelectorAll('.bh-filterbalk').forEach(filterKoppelen);
    (wortel || document).querySelectorAll('form[data-bevestig]').forEach(bevestigFormKoppelen);
    (wortel || document).querySelectorAll('form[data-concept]').forEach(conceptKoppelen);
    if (!wortel || wortel === document) { zijbalkKoppelen(); hulpKoppelen(); zoekKoppelen(); }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { start(document); });
  } else {
    start(document);
  }

  window.bhStart = start;   // voor blokken die later in de DOM komen
})();
