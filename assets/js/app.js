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

  function boot() {
    initLightbox();
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
