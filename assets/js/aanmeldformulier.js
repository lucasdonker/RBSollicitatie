/**
 * Paal 13 — Aanmeldformulier.
 *
 * Verzorgt vier dingen bovenop de HTML die er al staat:
 *  1. de drie stappen (zonder dit script staan ze gewoon onder elkaar — de stappen zijn
 *     een laag erbovenop, geen voorwaarde; daarom maakt dit script ook de Verder-knoppen)
 *  2. controle per stap, met een melding in gewone taal bij het veld zelf
 *  3. het toestemmingsblok voor ouders bij een leeftijd onder de 16 (AVG-grens)
 *  4. anonieme tracking: bezocht (alleen op de website), gestart, welke stap bereikt, en
 *     wanneer iemand halverwege weggaat — per bron en campagne (?bron=, utm_campaign)
 *
 * Tracking gaat naar /track.php en bevat nooit iets wat iemand heeft ingevuld: alleen het
 * soort gebeurtenis, de bron en het stapnummer. Geen cookies. Zie includes/paginas/statistiek.php.
 *
 * Wat iemand invult wordt bewaard in sessionStorage, zodat een vergissing of een terugknop
 * niet betekent dat alles opnieuw moet. Dat blijft op het apparaat zelf, verdwijnt zodra het
 * tabblad dichtgaat, en wordt gewist zodra de aanmelding binnen is (de succesmelding).
 *
 * Geen afhankelijkheden, werkt op meerdere formulieren op dezelfde pagina.
 */
(function () {
  'use strict';

  var MELDING = {
    leeg:        'Vul dit veld in.',
    email:       'Dit lijkt geen geldig e-mailadres. Controleer of er een @ en een punt in staan.',
    telefoon:    'Vul een telefoonnummer in waarop we je kunnen bereiken.',
    datum:       'Vul een geldige geboortedatum in.',
    toekomst:    'Deze datum ligt in de toekomst.',
    teJong:      'Je moet minimaal 15 jaar zijn om je aan te melden.',
    teOud:       'Controleer je geboortedatum even.',
    akkoord:     'Zet een vinkje om te laten weten dat je de privacyverklaring hebt gelezen.',
    ouderNaam:   'Vul de naam van je ouder of verzorger in.',
    ouderVink:   'Je ouder of verzorger moet dit aanvinken.'
  };

  // ── Kleine hulpjes ───────────────────────────────────────────────

  function maakEl(tag, klasse, tekst) {
    var el = document.createElement(tag);
    if (klasse) el.className = klasse;
    if (tekst) el.textContent = tekst;
    return el;
  }

  function leeftijdOp(waarde) {
    if (!waarde) return null;
    var d = new Date(waarde);
    if (isNaN(d.getTime())) return null;
    var nu = new Date();
    var jaren = nu.getFullYear() - d.getFullYear();
    var maanden = nu.getMonth() - d.getMonth();
    if (maanden < 0 || (maanden === 0 && nu.getDate() < d.getDate())) jaren--;
    return jaren;
  }

  function datumGeldig(waarde) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(waarde)) return false;
    var d = new Date(waarde);
    return !isNaN(d.getTime()) && d.getFullYear() > 1900;
  }

  // Bewust ruim: een adres met een rare TLD of een plusje is gewoon geldig. De server
  // heeft het laatste woord (filter_var), dit voorkomt alleen de duidelijke typefouten.
  function emailGeldig(waarde) {
    return /^[^\s@]+@[^\s@.]+(\.[^\s@.]+)+$/.test(waarde);
  }

  function telefoonGeldig(waarde) {
    return (waarde.replace(/\D/g, '').length >= 8);
  }

  // ── Eén formulier ────────────────────────────────────────────────

  function Formulier(kaart) {
    var form = kaart.querySelector('[data-af-form]');
    if (!form) return;

    var stappen = Array.prototype.slice.call(form.querySelectorAll('[data-af-stap]'));
    var acties = form.querySelector('[data-af-acties]');
    var verzendKnop = form.querySelector('[data-af-verzend]');
    var verzendTekst = verzendKnop ? verzendKnop.textContent : '';
    var voortgang = kaart.querySelector('[data-af-voortgang]');
    var balkVul = kaart.querySelector('[data-af-balkvul]');
    var teller = kaart.querySelector('[data-af-teller]');
    var bollen = Array.prototype.slice.call(kaart.querySelectorAll('[data-af-bol]'));
    var stapVeld = form.querySelector('[data-af-stapveld]');
    var datumVeld = form.querySelector('[data-geboortedatum]');
    var ouderBlok = form.querySelector('[data-ouder-blok]');

    var bron = kaart.getAttribute('data-af-bron') || '';
    var trackUrl = kaart.getAttribute('data-af-track') || '';
    var sleutel = 'p13af:' + (bron || location.pathname);
    // Campagne uit het adres (?bron=, utm_campaign, utm_source), zelfde regel als
    // pag_campagne_uit() op de server. De statistiek telt dan "<bron>/<campagne>", en het
    // verborgen veld p13_campagne laat de campagne de verzending overleven (ook via WordPress).
    var campagne = campagneUitAdres(bron);
    var trackBron = campagne ? (bron + '/' + campagne).slice(0, 80) : bron;

    var nu = parseInt(kaart.getAttribute('data-af-startstap'), 10) || 1;
    var hoogste = nu;
    var gestart = false;
    var verzonden = false;
    var verlatenGemeld = false;

    // ── Tracking ───────────────────────────────────────────────────

    function track(type, detail) {
      if (!trackUrl) return;
      var body = 't=' + encodeURIComponent(type) + '&b=' + encodeURIComponent(trackBron);
      if (detail !== undefined && detail !== null) body += '&d=' + encodeURIComponent(detail);
      try {
        if (navigator.sendBeacon) {
          navigator.sendBeacon(trackUrl, new Blob([body], { type: 'application/x-www-form-urlencoded' }));
        } else if (window.fetch) {
          fetch(trackUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body,
            credentials: 'omit',
            keepalive: true,
            mode: 'cors'
          }).catch(function () {});
        }
      } catch (e) { /* tracking mag een aanmelding nooit in de weg zitten */ }
    }

    function meldStart() {
      if (gestart) return;
      gestart = true;
      track('formulier_start');
    }

    if (campagne && !form.querySelector('input[name="p13_campagne"]')) {
      var cv = document.createElement('input');
      cv.type = 'hidden'; cv.name = 'p13_campagne'; cv.value = campagne;
      form.appendChild(cv);
    }
    // Het formulier op de website (WordPress) meet ook het bezoek: daar ziet onze server de
    // pagina niet. Een landingspagina telt het bezoek zelf (p.php), dus daar staat dit uit.
    if (kaart.getAttribute('data-af-bezoek') === '1') track('bezoek');

    // ── Opslag op het apparaat zelf ────────────────────────────────

    // Toestemmingsvinkjes bewaren we bewust niet. Toestemming moet een handeling zijn die
    // iemand op dat moment zelf verricht, niet iets wat een script voor hem terugzet.
    var NIET_BEWAREN = ['akkoord', 'ouder_akkoord', 'p13_w'];
    var bewaarWacht = null;

    function bewaar() {
      if (!window.sessionStorage) return;
      clearTimeout(bewaarWacht);
      bewaarWacht = setTimeout(function () {
        var uit = {};
        Array.prototype.forEach.call(form.elements, function (el) {
          if (!el.name || el.type === 'hidden' || el.name.charAt(0) === '_') return;
          if (NIET_BEWAREN.indexOf(el.name) !== -1) return;
          uit[el.name] = el.value;
        });
        try { sessionStorage.setItem(sleutel, JSON.stringify(uit)); } catch (e) {}
      }, 400);
    }

    // Alleen lege velden worden aangevuld. Zo wint een waarde die de server al heeft
    // teruggezet (na een foutmelding) altijd van wat hier nog lag, en vult dit tegelijk
    // de gaten op waar de server níéts kon terugzetten — zoals bij de WordPress-plugin,
    // die het formulier uit een cache haalt en de invoer dus niet kent.
    function herstel() {
      if (!window.sessionStorage) return;
      var ruw;
      try { ruw = sessionStorage.getItem(sleutel); } catch (e) { return; }
      if (!ruw) return;
      var data;
      try { data = JSON.parse(ruw); } catch (e) { return; }
      Object.keys(data).forEach(function (naam) {
        if (NIET_BEWAREN.indexOf(naam) !== -1) return;
        var el = form.querySelector('[name="' + naam + '"]');
        if (!el || el.type === 'checkbox' || typeof data[naam] !== 'string') return;
        // Een keuzelijst heeft altijd een waarde; daar telt de eerste optie als "leeg".
        var leeg = el.tagName === 'SELECT'
          ? (el.selectedIndex <= 0)
          : (el.value === '');
        if (leeg) el.value = data[naam];
      });
    }

    // ── Veldfouten ─────────────────────────────────────────────────

    function foutPlek(el) {
      var groep = el.closest('[data-af-veld]');
      return groep ? groep.querySelector('[data-af-veldfout]') : null;
    }

    function zetFout(el, tekst) {
      var plek = foutPlek(el);
      if (plek) plek.textContent = tekst || '';
      if (tekst) el.setAttribute('aria-invalid', 'true');
      else el.removeAttribute('aria-invalid');
    }

    /** Controleer één veld. Retour: foutmelding of '' als het goed is. */
    function controleer(el) {
      var waarde = (el.type === 'checkbox') ? el.checked : (el.value || '').trim();

      if (el.type === 'checkbox') {
        if (!el.required || waarde) return '';
        return el.name === 'ouder_akkoord' ? MELDING.ouderVink : MELDING.akkoord;
      }
      if (!waarde) {
        if (!el.required) return '';
        return el.name === 'ouder_toestemming_naam' ? MELDING.ouderNaam : MELDING.leeg;
      }
      if (el.type === 'email' && !emailGeldig(waarde)) return MELDING.email;
      if (el.type === 'tel' && !telefoonGeldig(waarde)) return MELDING.telefoon;
      if (el.hasAttribute('data-af-minleeftijd')) {
        if (!datumGeldig(waarde)) return MELDING.datum;
        var leeftijd = leeftijdOp(waarde);
        if (leeftijd === null) return MELDING.datum;
        if (leeftijd < 0) return MELDING.toekomst;
        if (leeftijd < parseInt(el.getAttribute('data-af-minleeftijd'), 10)) return MELDING.teJong;
        if (leeftijd > 100) return MELDING.teOud;
      }
      return '';
    }

    /** Alle velden van een stap controleren. Retour: het eerste foute veld, of null. */
    function controleerStap(index) {
      var stap = stappen[index - 1];
      if (!stap) return null;
      var eerste = null;
      var velden = stap.querySelectorAll('input, select');
      Array.prototype.forEach.call(velden, function (el) {
        if (el.type === 'hidden' || el.name === 'p13_w') return;
        // Velden in een verborgen blok (ouderblok) tellen niet mee.
        if (el.closest('[hidden]') && el.closest('[hidden]') !== stap) return;
        var fout = controleer(el);
        zetFout(el, fout);
        if (fout && !eerste) eerste = el;
      });
      return eerste;
    }

    // ── Ouderblok ──────────────────────────────────────────────────

    function ouderBijwerken() {
      if (!datumVeld || !ouderBlok) return;
      var leeftijd = leeftijdOp(datumVeld.value);
      var grens = parseInt(datumVeld.getAttribute('data-af-ouderleeftijd'), 10) || 16;
      var min = parseInt(datumVeld.getAttribute('data-af-minleeftijd'), 10) || 15;
      // Alleen voor wie zich écht kan aanmelden en nog geen 16 is. Bij een duidelijke
      // typefout (een kind van acht) hoort er één melding te staan over de leeftijd,
      // niet ook nog een blok dat om toestemming vraagt.
      var nodig = leeftijd !== null && leeftijd >= min && leeftijd < grens;
      var wasVerborgen = ouderBlok.hidden;

      ouderBlok.hidden = !nodig;

      // Een verborgen veld mag niet verplicht zijn: de browser kan er niet heen springen
      // en blokkeert het versturen dan zonder zichtbare melding.
      var naam = ouderBlok.querySelector('[name="ouder_toestemming_naam"]');
      var vink = ouderBlok.querySelector('[name="ouder_akkoord"]');
      [naam, vink].forEach(function (el) {
        if (!el) return;
        el.required = nodig;
        el.setAttribute('aria-required', nodig ? 'true' : 'false');
        if (!nodig) zetFout(el, '');
      });

      if (nodig && wasVerborgen) {
        ouderBlok.setAttribute('aria-live', 'polite');
        ouderBlok.setAttribute('tabindex', '-1');
      }
    }

    // ── Stappen ────────────────────────────────────────────────────

    function toon(index, verplaatsFocus) {
      nu = Math.min(Math.max(index, 1), stappen.length);
      stappen.forEach(function (stap, i) { stap.hidden = (i + 1) !== nu; });

      if (nu > hoogste) {
        hoogste = nu;
        track('formulier_stap', nu);
      }
      if (stapVeld) stapVeld.value = String(hoogste);

      // Eerlijke balk: halverwege stap 2 van 3 sta je op 50%, niet op 100% terwijl je
      // nog moet verzenden.
      var pct = Math.round(((nu - 0.5) / stappen.length) * 100);
      if (balkVul) balkVul.style.width = pct + '%';
      // Op een klein scherm staan de bollen uit; dan is deze regel het enige dat vertelt
      // waar je bent, dus gaat de naam van de stap erin mee. De CSS toont hem alleen daar.
      if (teller) {
        teller.textContent = 'Stap ' + nu + ' van ' + stappen.length;
        var bolNu = kaart.querySelector('[data-af-bol="' + nu + '"]');
        var kort = bolNu ? bolNu.querySelector('.af-bol-tekst') : null;
        var naam = kort ? (kort.textContent || '').trim() : '';
        if (naam) teller.appendChild(maakEl('span', 'af-stapnaam', ' · ' + naam));
      }
      bollen.forEach(function (bol) {
        var n = parseInt(bol.getAttribute('data-af-bol'), 10);
        bol.classList.toggle('is-nu', n === nu);
        bol.classList.toggle('is-klaar', n < nu);
        if (n === nu) bol.setAttribute('aria-current', 'step');
        else bol.removeAttribute('aria-current');
      });

      knoppenBijwerken();

      if (verplaatsFocus) {
        var legend = stappen[nu - 1].querySelector('.af-legend');
        var doel = legend || stappen[nu - 1];
        doel.setAttribute('tabindex', '-1');
        doel.focus({ preventScroll: true });
        // Alleen scrollen als de kaart buiten beeld is geraakt.
        var vak = kaart.getBoundingClientRect();
        if (vak.top < 0 || vak.top > window.innerHeight * 0.6) {
          kaart.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      }
    }

    function verder() {
      var fout = controleerStap(nu);
      if (fout) {
        fout.focus();
        var plek = foutPlek(fout);
        if (plek) plek.setAttribute('role', 'alert');
        return;
      }
      toon(nu + 1, true);
    }

    function terug() { toon(nu - 1, true); }

    var knopTerug, knopVerder;

    function knoppenBijwerken() {
      if (!knopTerug || !knopVerder) return;
      knopTerug.hidden = (nu === 1);
      var laatste = (nu === stappen.length);
      knopVerder.hidden = laatste;
      verzendKnop.hidden = !laatste;
      knopVerder.textContent = 'Verder';
    }

    function stappenAanzetten() {
      if (stappen.length < 2 || !acties || !verzendKnop) return false;

      knopTerug = maakEl('button', 'af-terug', 'Terug');
      knopTerug.type = 'button';
      knopTerug.addEventListener('click', terug);

      knopVerder = maakEl('button', 'af-knop', 'Verder');
      knopVerder.type = 'button';
      knopVerder.addEventListener('click', verder);

      acties.insertBefore(knopTerug, acties.firstChild);
      acties.insertBefore(knopVerder, verzendKnop);

      form.classList.add('is-stapsgewijs');
      if (voortgang) voortgang.hidden = false;

      // Enter in een tekstveld betekent "verder", niet "verzenden" — behalve op de laatste stap.
      form.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        var el = e.target;
        if (!el.tagName || el.tagName !== 'INPUT' || el.type === 'checkbox') return;
        if (nu < stappen.length) { e.preventDefault(); verder(); }
      });

      return true;
    }

    // ── Opstarten ──────────────────────────────────────────────────

    herstel();
    ouderBijwerken();

    var stapsgewijs = stappenAanzetten();
    if (stapsgewijs) {
      toon(nu, false);
      // Kwam het formulier terug met een serverfout, spring dan naar die stap toe.
      if (nu > 1) hoogste = nu;
    }

    if (datumVeld) {
      datumVeld.addEventListener('change', ouderBijwerken);
      datumVeld.addEventListener('input', ouderBijwerken);
    }

    // Eerste echte interactie = "formulier gestart".
    ['focusin', 'input'].forEach(function (soort) {
      form.addEventListener(soort, function (e) {
        if (e.target && e.target.name && e.target.name !== 'p13_w') meldStart();
      }, { once: false });
    });

    // Fout weghalen zodra iemand het veld verbetert; pas opnieuw controleren bij verlaten.
    form.addEventListener('input', function (e) {
      var el = e.target;
      if (!el.name || el.name === 'p13_w') return;
      if (el.getAttribute('aria-invalid') === 'true' && !controleer(el)) zetFout(el, '');
      bewaar();
    });
    form.addEventListener('change', bewaar);
    form.addEventListener('focusout', function (e) {
      var el = e.target;
      if (!el.name || el.name === 'p13_w' || !el.tagName) return;
      if (el.tagName !== 'INPUT' && el.tagName !== 'SELECT') return;
      // Een leeg veld dat nog niet is aangeraakt hoeft niet meteen rood te worden.
      if (el.type !== 'checkbox' && !(el.value || '').trim() && el.getAttribute('aria-invalid') !== 'true') return;
      zetFout(el, controleer(el));
    });

    // ── Verzenden ──────────────────────────────────────────────────

    form.addEventListener('submit', function (e) {
      // Nog eens drukken terwijl het eerste verzoek loopt = een tweede aanmelding. Tegenhouden.
      if (verzonden) { e.preventDefault(); return; }
      // Bij de stapsgewijze weergave controleren we alle stappen; anders alleen wat er staat.
      var eersteFout = null;
      var eersteStap = 0;
      for (var i = 1; i <= stappen.length; i++) {
        var fout = controleerStap(i);
        if (fout && !eersteFout) { eersteFout = fout; eersteStap = i; }
      }
      if (eersteFout) {
        e.preventDefault();
        if (stapsgewijs && eersteStap !== nu) toon(eersteStap, false);
        eersteFout.focus();
        return;
      }

      // De opslag blijft staan tot de aanmelding echt binnen is (zie start()): gaat er op de
      // server iets mis (sessie verlopen, al aangemeld), dan staat de invoer er nog.
      verzonden = true;
      if (stapVeld) stapVeld.value = String(hoogste);
      if (verzendKnop) {
        verzendKnop.setAttribute('aria-busy', 'true');
        verzendKnop.textContent = 'Bezig met versturen…';
      }
      // Niet disablen: een uitgeschakelde knop stuurt zijn name/value niet mee. inert maakt
      // het formulier alleen onaanraakbaar; de browser verstuurt gewoon alles.
      if ('inert' in HTMLElement.prototype) form.inert = true;
      form.setAttribute('aria-busy', 'true');
    });

    // Terug-knop: de pagina komt uit de cache en moet dan weer bruikbaar zijn.
    window.addEventListener('pageshow', function (e) {
      if (!e.persisted || !verzonden) return;
      verzonden = false;
      if ('inert' in HTMLElement.prototype) form.inert = false;
      form.removeAttribute('aria-busy');
      if (verzendKnop) { verzendKnop.removeAttribute('aria-busy'); verzendKnop.textContent = verzendTekst; }
    });

    // ── Halverwege weg ─────────────────────────────────────────────
    // Een tabblad dat naar de achtergrond gaat is op mobiel het enige betrouwbare signaal
    // dat iemand vertrekt. Het is dus een aanwijzing, geen bewijs: in de statistiek staat
    // dit als "mogelijk afgehaakt" en nooit als harde uitval.

    function meldVerlaten() {
      if (verzonden || verlatenGemeld || !gestart) return;
      verlatenGemeld = true;
      track('formulier_verlaten', hoogste);
    }

    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'hidden') meldVerlaten();
    });
    window.addEventListener('pagehide', meldVerlaten);
  }

  /** Campagnewaarde: kleine letters, a-z 0-9 en koppeltekens, max. 40 (= pag_campagne_schoon()). */
  function campagneSchoon(ruw) {
    return String(ruw || '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40).replace(/-+$/, '');
  }

  function campagneUitAdres(bron) {
    var q;
    try { q = new URLSearchParams(window.location.search); } catch (e) { return ''; }
    var sleutels = ['bron', 'utm_campaign', 'utm_source'];
    for (var i = 0; i < sleutels.length; i++) {
      var c = campagneSchoon(q.get(sleutels[i]));
      if (c && c !== bron) return c;
    }
    return '';
  }

  // ── Starten ──────────────────────────────────────────────────────

  function start() {
    // Aanmelding binnen: de bewaarde invoer op dit apparaat mag weg.
    if (document.querySelector('[data-formulier-succes]') && window.sessionStorage) {
      try {
        for (var i = sessionStorage.length - 1; i >= 0; i--) {
          var k = sessionStorage.key(i);
          if (k && k.indexOf('p13af:') === 0) sessionStorage.removeItem(k);
        }
      } catch (e) {}
    }
    var kaarten = document.querySelectorAll('[data-af-kaart]');
    Array.prototype.forEach.call(kaarten, function (kaart) { Formulier(kaart); });

    // Na een mislukte of geslaagde verzending de aandacht naar de melding brengen.
    var melding = document.querySelector('[data-formulier-fout]') || document.querySelector('[data-formulier-succes]');
    if (melding && typeof melding.focus === 'function') melding.focus();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
