/*
 * Paal 13 — gedeelde front-end helpers (admin én portaal).
 * Universele foto-vergroter (lightbox) die overal automatisch werkt, plus kleine
 * mobiele verbeteringen. Ingeladen in beide layouts.
 */
(function () {
  'use strict';

  function initLightbox() {
    var overlay = document.getElementById('p13-lightbox');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id = 'p13-lightbox';
      overlay.setAttribute('role', 'dialog');
      overlay.setAttribute('aria-label', 'Vergrote afbeelding');
      overlay.innerHTML =
        '<button type="button" class="p13-lightbox-sluit" aria-label="Sluiten">&times;</button>' +
        '<img alt="">';
      overlay.style.display = 'none';
      document.body.appendChild(overlay);

      var sluit = function () { overlay.style.display = 'none'; document.body.style.overflow = ''; };
      overlay.addEventListener('click', function (e) {
        if (e.target === overlay || e.target.classList.contains('p13-lightbox-sluit')) sluit();
      });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape') sluit(); });
    }

    document.querySelectorAll('img').forEach(function (img) {
      if (img.dataset.p13Lb) return;
      if (img.closest('#p13-lightbox')) return;
      var src = img.getAttribute('src') || '';
      if (!src) return;
      if (/logo|icon|icoon|avatar-default|\.svg($|\?)/i.test(src)) return;
      if (img.classList.contains('geen-vergroot')) return;
      if (img.width && img.width < 40) return;

      img.dataset.p13Lb = '1';
      img.style.cursor = 'zoom-in';
      img.addEventListener('click', function (e) {
        e.preventDefault();
        var lb = document.getElementById('p13-lightbox');
        lb.querySelector('img').src = img.currentSrc || img.src;
        lb.style.display = 'flex';
        document.body.style.overflow = 'hidden';
      });
    });
  }

  /* ── Portaal: formulier-feedback ──
     Het werk zit in assets/js/actie.js, dat op élke submit reageert — in het portaal
     én in het beheer. Deze functie blijft bestaan omdat schermen hem aanroepen via
     onsubmit="return portaalSubmit(this)".

     Wat hier stónd zette btn.disabled = true. Dat is stil gevaarlijk: een
     uitgeschakelde submitknop stuurt zijn name/value niet mee, dus een formulier dat
     op <button name="actie" value="..."> rekent verliest die waarde. */
  function portaalSubmit(form) {
    if (window.P13Actie) {
      window.P13Actie.bezig(form, form.querySelector('button[type="submit"]'));
    }
    return true;
  }

  /* ── Portaal: oudergegevens-formulier in een taakrij (velden zonder 'required' zodat een
        verborgen formulier de submit niet blokkeert; validatie hier) ── */
  function ouderValideer(form) {
    var v = function (n) { var el = form.querySelector('[name="' + n + '"]'); return el ? el.value.trim() : ''; };
    var naam = v('ouder_naam'), email = v('ouder_email'), tel = v('ouder_telefoon');
    var foutBox = form.querySelector('.ouder-fout');
    var cijfers = (tel.match(/\d/g) || []).length;
    var fout = '';
    if (!naam) fout = 'Vul de naam van je ouder/verzorger in.';
    else if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) fout = 'Vul een geldig e-mailadres in.';
    else if (cijfers < 8) fout = 'Vul een geldig telefoonnummer in (minstens 8 cijfers).';
    if (fout) {
      if (foutBox) { foutBox.textContent = fout; foutBox.hidden = false; foutBox.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
      else { alert(fout); }
      return false;
    }
    portaalSubmit(form);
    return true;
  }
  window.portaalSubmit = portaalSubmit;
  window.ouderValideer = ouderValideer;

  function initPortaalFormulieren() {
    /* Hier hing eerder aan élk formulier op de pagina een submit-handler — ook aan de
       formulieren van het beheer, want app.js draait daar ook. Dat is nu weg: actie.js
       doet dit één keer, voor beide kanten, zonder per formulier iets op te hangen. */

    // "Wijzigen" bij een afgeronde taakrij met inline formulier: formulier tonen/verbergen
    document.querySelectorAll('[data-toggle-inline]').forEach(function (knop) {
      if (knop.dataset.p13Toggle) return;
      knop.dataset.p13Toggle = '1';
      knop.addEventListener('click', function () {
        var rij = knop.closest('.pt-taakrij');
        var form = rij ? rij.querySelector('.pt-taakrij-inline') : null;
        if (!form) return;
        form.hidden = !form.hidden;
        if (!form.hidden) { var eerste = form.querySelector('input:not([type=hidden])'); if (eerste) eerste.focus(); }
      });
    });
  }

  /* ── Info-bolletjes (includes/ui.php: info_bol()) ── */
  function infoSluitAlles(behalve) {
    document.querySelectorAll('.ui-info-paneel').forEach(function (p) {
      if (p === behalve) return;
      p.hidden = true;
      var k = document.querySelector('.ui-info-knop[aria-controls="' + p.id + '"]');
      if (k) k.setAttribute('aria-expanded', 'false');
    });
  }

  function initInfoBollen() {
    document.querySelectorAll('.ui-info-knop').forEach(function (knop) {
      if (knop.dataset.p13Info) return;
      knop.dataset.p13Info = '1';
      knop.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var paneel = document.getElementById(knop.getAttribute('aria-controls'));
        if (!paneel) return;
        var wasOpen = !paneel.hidden;
        infoSluitAlles(paneel);
        paneel.hidden = wasOpen;
        knop.setAttribute('aria-expanded', wasOpen ? 'false' : 'true');
      });
    });
    document.querySelectorAll('.ui-info-sluit').forEach(function (sluit) {
      if (sluit.dataset.p13Info) return;
      sluit.dataset.p13Info = '1';
      sluit.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); infoSluitAlles(null); });
    });
    if (!document.body.dataset.p13InfoGlobal) {
      document.body.dataset.p13InfoGlobal = '1';
      // Buiten het paneel klikken of Escape sluit de uitleg
      document.addEventListener('click', function (e) { if (!e.target.closest || !e.target.closest('.ui-info')) infoSluitAlles(null); });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape') infoSluitAlles(null); });
    }
  }

  function boot() {
    initLightbox();
    initInfoBollen();
    initPortaalFormulieren();
    if (window.MutationObserver) {
      var obs = new MutationObserver(function () { initLightbox(); initInfoBollen(); });
      obs.observe(document.body, { childList: true, subtree: true });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
