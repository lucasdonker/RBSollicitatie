/*
 * Paal 13 — gedeelde front-end helpers (admin én portaal).
 * Universele foto-vergroter (lightbox) die overal automatisch werkt, plus kleine
 * mobiele verbeteringen. Ingeladen in beide layouts.
 */
(function () {
  'use strict';

  /* ── Foto vergroten ────────────────────────────────────────────
     Eén vergroter voor het hele systeem. Er stond er ook een tweede in
     admin/layout_bottom.php (window.fotoVergroten met een eigen #fotoModal);
     die deed hetzelfde met een ander uiterlijk en een eigen Escape-handler.
     Die is weg — deze neemt de naam over, zodat bestaande aanroepen blijven werken.

     Twee manieren om hem te openen:
       1. data-foto="<url>" data-naam="<bijschrift>" op een knop  (de nette weg:
          bh_avatar(..., ['vergroot' => true]) maakt die)
       2. automatisch op elke gewone <img> die groot genoeg is
     De eerste wint: staat er een data-foto op of eromheen, dan slaat de scan
     de afbeelding erbinnen over. */
  var lbOpener = null;

  function lbOverlay() {
    var overlay = document.getElementById('p13-lightbox');
    if (overlay) return overlay;

    overlay = document.createElement('div');
    overlay.id = 'p13-lightbox';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', 'Vergrote afbeelding');
    overlay.innerHTML =
      '<button type="button" class="p13-lightbox-sluit" aria-label="Sluiten">&times;</button>' +
      '<figure class="p13-lightbox-fig"><img alt=""><figcaption></figcaption></figure>';
    overlay.style.display = 'none';
    document.body.appendChild(overlay);

    overlay.addEventListener('click', function (e) {
      if (e.target === overlay || e.target.closest('.p13-lightbox-sluit')) lbSluit();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && overlay.style.display === 'flex') lbSluit();
    });
    return overlay;
  }

  function lbSluit() {
    var overlay = document.getElementById('p13-lightbox');
    if (!overlay) return;
    overlay.style.display = 'none';
    document.body.style.overflow = '';
    /* Terug naar waar je vandaan kwam, anders staat de focus na het sluiten
       bovenaan de pagina en ben je met het toetsenbord je plek kwijt. */
    if (lbOpener && document.contains(lbOpener)) lbOpener.focus();
    lbOpener = null;
  }

  function lbOpen(url, naam, opener) {
    if (!url) return;
    var overlay = lbOverlay();
    overlay.querySelector('img').src = url;
    var bij = overlay.querySelector('figcaption');
    bij.textContent = naam || '';
    bij.hidden = !naam;
    lbOpener = opener || null;
    overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    overlay.querySelector('.p13-lightbox-sluit').focus();
  }

  /* Publieke ingang; blijft bestaan voor schermen met een onclick erin. */
  window.fotoVergroten = function (url, naam) { lbOpen(url, naam, null); };

  /* Gedelegeerd: werkt ook voor knoppen die later in de pagina komen. */
  document.addEventListener('click', function (e) {
    var knop = e.target.closest ? e.target.closest('[data-foto]') : null;
    if (!knop) return;
    e.preventDefault();
    lbOpen(knop.getAttribute('data-foto'), knop.getAttribute('data-naam'), knop);
  });

  function initLightbox() {
    document.querySelectorAll('img').forEach(function (img) {
      if (img.dataset.p13Lb) return;
      if (img.closest('#p13-lightbox')) return;
      /* Zit er al een expliciete vergroot-knop omheen, of is de afbeelding zelf
         een link, dan niet ook nog eens een eigen klik erop hangen. */
      if (img.closest('[data-foto]')) return;
      if (img.closest('a')) return;
      var src = img.getAttribute('src') || '';
      if (!src) return;
      if (/logo|icon|icoon|avatar-default|\.svg($|\?)/i.test(src)) return;
      if (img.classList.contains('geen-vergroot')) return;
      if (img.width && img.width < 40) return;

      img.dataset.p13Lb = '1';
      img.style.cursor = 'zoom-in';
      img.addEventListener('click', function (e) {
        e.preventDefault();
        lbOpen(img.currentSrc || img.src, img.getAttribute('alt') || '', null);
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
