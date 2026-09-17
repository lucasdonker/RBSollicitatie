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

  /* ── Portaal: formulier-feedback (knop → "Bezig…", geen dubbelklik) ── */
  function portaalSubmit(form) {
    var btn = form.querySelector('button[type="submit"]');
    if (btn) {
      btn.disabled = true;
      var t = btn.querySelector('.btn-tekst'), s = btn.querySelector('.btn-spinner');
      if (t && s) { t.hidden = true; s.hidden = false; }
      else { btn.dataset.oud = btn.innerHTML; btn.innerHTML = 'Bezig…'; }
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
    document.querySelectorAll('form').forEach(function (f) {
      if (f.dataset.p13Submit || f.hasAttribute('onsubmit')) return;
      f.dataset.p13Submit = '1';
      f.addEventListener('submit', function () { portaalSubmit(f); });
    });
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

  function boot() {
    initLightbox();
    initPortaalFormulieren();
    if (window.MutationObserver) {
      var obs = new MutationObserver(function () { initLightbox(); });
      obs.observe(document.body, { childList: true, subtree: true });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
