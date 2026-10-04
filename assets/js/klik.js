/**
 * Paal 13 — gebeurtenissen zonder inline JavaScript (portaal én beheer).
 *
 * Waarom: de Content-Security-Policy staat geen onclick="…" meer toe ('unsafe-inline' is weg,
 * NIS2-hardening). Een geïnjecteerd stukje HTML kan dan geen script meer uitvoeren.
 *
 * Gebruik in HTML — dezelfde aanroep als vroeger, alleen een ander attribuut:
 *   data-klik="selGroep('nieuw', this.checked)"     (click)
 *   data-wijzig="besluitZet(12, null)"              (change)
 *   data-invoer="kortTeller()"                      (input)
 *   data-verzend="return normOpslaan(5, this)"      (submit)
 *   data-toets="aanToets(event)"                    (keydown)
 *   data-focus="aanZoeken()"                        (focus)
 *   data-klik-voorkom                               altijd preventDefault (was "…; return false")
 *   <img data-terugval="/assets/img/logo.svg">      andere afbeelding als de eerste niet laadt
 *
 * De tekst wordt NIET als JavaScript uitgevoerd (geen eval): hij wordt gelezen als één
 * functieaanroep met letterlijke argumenten — getallen, 'tekst', true/false/null, this, event,
 * this.<eigenschap> (bijv. this.checked, this.value, this.dataset.x) en @id.<eigenschap>
 * (bijv. @meteen-5.checked = document.getElementById('meteen-5').checked).
 * Geeft de functie false terug, dan volgt preventDefault — net als "return false" vroeger.
 *
 * Aan te roepen zijn alleen eigen functies op window (of één niveau diep, zoals P13Editor.insert).
 * Browser-ingangen als eval, fetch, location of document zijn geweigerd: zelfs wie een
 * data-klik weet in te voegen, kan daarmee niets anders dan wat onze eigen knoppen al doen.
 */
(function () {
  'use strict';
  if (window.P13Klik) return;

  var GEWEIGERD = ['eval', 'Function', 'setTimeout', 'setInterval', 'fetch', 'open', 'location', 'document',
    'navigator', 'localStorage', 'sessionStorage', 'XMLHttpRequest', 'WebSocket', 'Worker', 'postMessage',
    'alert', 'confirm', 'prompt', 'history', 'top', 'parent', 'self', 'globalThis', 'frames', 'opener',
    'importScripts', 'constructor', '__proto__', 'prototype'];

  var EVENTS = { click: 'klik', change: 'wijzig', input: 'invoer', submit: 'verzend', keydown: 'toets', focusin: 'focus' };

  // ── Argumenten lezen ────────────────────────────────────────────
  function splitsArgs(s) {
    var uit = [], huidig = '', quote = null, diepte = 0;
    for (var i = 0; i < s.length; i++) {
      var c = s.charAt(i);
      if (quote) {
        huidig += c;
        if (c === '\\' && i + 1 < s.length) { huidig += s.charAt(++i); continue; }
        if (c === quote) quote = null;
        continue;
      }
      if (c === '"' || c === "'") { quote = c; huidig += c; continue; }
      if (c === '(' || c === '[') diepte++;
      if (c === ')' || c === ']') diepte--;
      if (c === ',' && diepte === 0) { uit.push(huidig.trim()); huidig = ''; continue; }
      huidig += c;
    }
    if (huidig.trim() !== '') uit.push(huidig.trim());
    return uit;
  }

  function pad(obj, delen) {
    for (var i = 0; i < delen.length; i++) {
      if (obj == null || GEWEIGERD.indexOf(delen[i]) !== -1) return undefined;
      obj = obj[delen[i]];
    }
    return obj;
  }

  function waarde(tok, el, ev) {
    if (/^-?\d+(\.\d+)?$/.test(tok)) return parseFloat(tok);
    if (tok === 'true') return true;
    if (tok === 'false') return false;
    if (tok === 'null') return null;
    if (tok === 'undefined') return undefined;
    var q = tok.charAt(0);
    if ((q === "'" || q === '"') && tok.charAt(tok.length - 1) === q) {
      // \uXXXX komt uit json_encode (JSON_HEX_QUOT/JSON_HEX_APOS), \n en \' uit de hand.
      return tok.slice(1, -1).replace(/\\u([0-9a-fA-F]{4})|\\(.)/g, function (m, hex, t) {
        if (hex) return String.fromCharCode(parseInt(hex, 16));
        return t === 'n' ? '\n' : t;
      });
    }
    if (tok === 'this') return el;
    if (tok === 'event') return ev;
    if (tok.indexOf('this.') === 0) return pad(el, tok.slice(5).split('.'));
    if (tok.charAt(0) === '@') {
      var punt = tok.indexOf('.');
      var doel = document.getElementById(punt === -1 ? tok.slice(1) : tok.slice(1, punt));
      return punt === -1 ? doel : pad(doel, tok.slice(punt + 1).split('.'));
    }
    throw new Error('P13Klik: onbekend argument ' + tok);
  }

  function functie(naam) {
    if (!/^[A-Za-z_$][\w$]*(\.[A-Za-z_$][\w$]*)?$/.test(naam)) return null;
    var delen = naam.split('.');
    if (delen[0] === 'window') return naam === 'window.print' ? { fn: window.print, ctx: window } : null;
    if (GEWEIGERD.indexOf(delen[0]) !== -1 || GEWEIGERD.indexOf(delen[1] || '') !== -1) return null;
    var ctx = delen.length === 2 ? window[delen[0]] : window;
    var fn = ctx ? ctx[delen[delen.length - 1]] : null;
    return typeof fn === 'function' ? { fn: fn, ctx: ctx } : null;
  }

  function uitvoeren(tekst, el, ev) {
    var m = /^\s*(return\s+)?([\w$.]+)\s*\(([\s\S]*)\)\s*;?\s*$/.exec(tekst);
    if (!m) { console.error('P13Klik: geen geldige aanroep: ' + tekst); return; }
    var f = functie(m[2]);
    if (!f) { console.error('P13Klik: functie niet toegestaan of niet gevonden: ' + m[2]); return; }
    var args = splitsArgs(m[3]).map(function (t) { return waarde(t, el, ev); });
    return f.fn.apply(f.ctx, args);
  }

  // ── Eén luisteraar per soort, in de capture-fase ─────────────────
  // Capture: zo draait de aanroep vóór andere luisteraars op document (bijv. de bevestiging en de
  // voortgangsbalk bij verzenden) — in dezelfde volgorde als het inline attribuut vroeger deed.
  Object.keys(EVENTS).forEach(function (type) {
    var attr = 'data-' + EVENTS[type];
    document.addEventListener(type, function (ev) {
      var el = ev.target && ev.target.closest ? ev.target.closest('[' + attr + ']') : null;
      if (!el) return;
      // Een formulier-submit hoort bij het formulier zelf, niet bij een knop erin.
      if (type === 'submit' && el !== ev.target) return;
      if (type === 'click' && el.hasAttribute('data-klik-voorkom')) ev.preventDefault();
      var uit;
      try { uit = uitvoeren(el.getAttribute(attr), el, ev); }
      catch (e) { console.error(e); }
      if (uit === false) ev.preventDefault();
    }, true);
  });

  // Afbeelding die niet laadt → terugval (error-events borrelen niet op, dus capture).
  function terugval(img) {
    var alt = img.getAttribute('data-terugval');
    img.removeAttribute('data-terugval');   // één keer, geen lus
    img.src = alt;
  }
  document.addEventListener('error', function (ev) {
    var img = ev.target;
    if (img && img.tagName === 'IMG' && img.hasAttribute('data-terugval')) terugval(img);
  }, true);
  // Mislukte het laden al vóórdat dit script er was, dan alsnog.
  function naloop() {
    document.querySelectorAll('img[data-terugval]').forEach(function (img) {
      if (img.complete && img.naturalWidth === 0) terugval(img);
    });
  }
  document.addEventListener('DOMContentLoaded', naloop);
  window.addEventListener('load', naloop);

  // ── Kleine vaste handelingen die eerder als losse regel JavaScript in de HTML stonden ──
  /** Voor tests en de ontwikkelaar: is dit een geldige, bereikbare aanroep? (voert niets uit) */
  function controleer(tekst) {
    var m = /^\s*(return\s+)?([\w$.]+)\s*\(([\s\S]*)\)\s*;?\s*$/.exec(tekst || '');
    if (!m) return 'geen aanroep';
    if (!functie(m[2])) return 'functie ontbreekt of geweigerd: ' + m[2];
    return '';
  }

  window.P13Klik = {
    uitvoeren: uitvoeren,
    controleer: controleer,
    /** Waarde van een veld zetten, bijv. welke knop een formulier verstuurde. */
    zet: function (selector, w) { var el = document.querySelector(selector); if (el) el.value = w; },
    /** Het omliggende element weghalen (een rij in een lijst). */
    ouderWeg: function (el) { if (el && el.parentElement) el.parentElement.remove(); },
    /** De waarde naar het volgende veld spiegelen (kleurkiezer ↔ tekstveld). */
    spiegel: function (el) { if (el && el.nextElementSibling) el.nextElementSibling.value = el.value; },
    /** De inhoud van een veld naar het klembord; de knop zegt dat het gelukt is. */
    kopieer: function (selector, knop) {
      var el = document.querySelector(selector);
      if (!el || !navigator.clipboard) return;
      navigator.clipboard.writeText(el.value || el.textContent || '').then(function () {
        if (knop) { var oud = knop.textContent; knop.textContent = 'Gekopieerd'; setTimeout(function () { knop.textContent = oud; }, 2000); }
      });
    },
    /** Naar de waarde van een keuzelijst gaan (paginering "per pagina"). */
    naar: function (el) { if (el && el.value) window.location.href = el.value; },
    /** Het formulier van dit element versturen (keuzelijst die meteen opslaat). */
    verstuur: function (el) {
      var f = el && el.form;
      if (!f) return;
      if (f.requestSubmit) f.requestSubmit(); else f.submit();
    }
  };
})();
