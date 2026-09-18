/*
 * Paal 13 — Ondertekenscherm (portaal/ondertekenen.php).
 * Handtekening-canvas (vinger, pen of muis via pointer-events), document uit-/inklappen en
 * validatie vóór verzenden. De velden en de opslag staan in PHP; hier alleen de interactie.
 */
(function () {
  'use strict';

  var form = document.getElementById('ondForm');
  if (!form) return;

  /* ── Document: "Alles lezen" / "Inklappen" ── */
  var doc = document.getElementById('ondDoc');
  var docToggle = document.getElementById('ondDocToggle');
  if (doc && docToggle) {
    docToggle.addEventListener('click', function () {
      var dicht = doc.classList.toggle('dicht');
      docToggle.textContent = dicht ? docToggle.dataset.dicht : docToggle.dataset.open;
      if (!dicht) doc.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  /* ── Handtekening-canvas ── */
  var canvas = document.getElementById('hdtCanvas');
  var wrap   = document.getElementById('hdtWrap');
  var hint   = document.getElementById('hdtHint');
  var data   = document.getElementById('hdtData');
  var wis    = document.getElementById('hdtWis');
  var ctx    = canvas ? canvas.getContext('2d') : null;
  var tekent = false, leeg = true, vx = 0, vy = 0;

  function tekenAf(src, w, h) {
    var img = new Image();
    img.onload = function () { ctx.drawImage(img, 0, 0, w, h); };
    img.src = src;
  }

  // Canvas op de juiste resolutie (scherp op retina); bestaande tekening blijft staan.
  function pasGrootteAan() {
    if (!canvas) return;
    var rect = canvas.getBoundingClientRect();
    if (!rect.width || !rect.height) return;
    var ratio = window.devicePixelRatio || 1;
    var oud = leeg ? null : canvas.toDataURL();
    canvas.width  = Math.round(rect.width * ratio);
    canvas.height = Math.round(rect.height * ratio);
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    ctx.lineWidth = 2.2; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#1a2230';
    if (oud) tekenAf(oud, rect.width, rect.height);
  }

  function pos(e) {
    var rect = canvas.getBoundingClientRect();
    return { x: e.clientX - rect.left, y: e.clientY - rect.top };
  }
  function start(e) {
    e.preventDefault();
    tekent = true;
    if (canvas.setPointerCapture) { try { canvas.setPointerCapture(e.pointerId); } catch (err) {} }
    var p = pos(e); vx = p.x; vy = p.y;
    if (leeg) { leeg = false; if (hint) hint.hidden = true; }
    if (wrap) wrap.classList.remove('fout');
    // Ook een enkele tik laat een punt achter
    ctx.beginPath(); ctx.moveTo(vx, vy); ctx.lineTo(vx + 0.1, vy + 0.1); ctx.stroke();
  }
  function beweeg(e) {
    if (!tekent) return;
    e.preventDefault();
    var p = pos(e);
    ctx.beginPath(); ctx.moveTo(vx, vy); ctx.lineTo(p.x, p.y); ctx.stroke();
    vx = p.x; vy = p.y;
  }
  function stop() {
    if (!tekent) return;
    tekent = false;
    if (!leeg) data.value = canvas.toDataURL('image/png');
  }

  if (canvas) {
    canvas.addEventListener('pointerdown', start);
    canvas.addEventListener('pointermove', beweeg);
    canvas.addEventListener('pointerup', stop);
    canvas.addEventListener('pointercancel', stop);
    canvas.addEventListener('pointerleave', stop);
    window.addEventListener('resize', pasGrootteAan);
    pasGrootteAan();
    // Handtekening terugzetten na een serverfout (het verborgen veld is dan nog gevuld)
    if (data && data.value) {
      leeg = false; if (hint) hint.hidden = true;
      var r = canvas.getBoundingClientRect();
      tekenAf(data.value, r.width, r.height);
    }
    if (wis) {
      wis.addEventListener('click', function () {
        ctx.save(); ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.restore();
        leeg = true; data.value = '';
        if (hint) hint.hidden = false;
      });
    }
  }

  /* ── Verificatiecode (2FA): code aanvragen via de API, dan het codeveld tonen ── */
  var tfa = document.getElementById('ond2fa');
  if (tfa) {
    var stap1 = document.getElementById('ond2faStap1'), stap2 = document.getElementById('ond2faStap2');
    var status = document.getElementById('ond2faStatus'), fout2 = document.getElementById('ond2faFout');
    var codeVeld = document.getElementById('ond2faCode');
    var csrf = form.querySelector('input[name="_csrf"]');
    var bezig = false;
    function kanaalTekst(k, naar) {
      if (k === 'sms') return 'Code verstuurd per sms naar ' + naar + '. Vul hem hieronder in (15 minuten geldig).';
      if (k === 'whatsapp') return 'Code verstuurd via WhatsApp naar ' + naar + '. Vul hem hieronder in (15 minuten geldig).';
      return 'Code verstuurd per e-mail naar ' + naar + '. Vul hem hieronder in (15 minuten geldig).';
    }
    function codeSturen(knop) {
      if (bezig) return; bezig = true;
      if (fout2) { fout2.hidden = true; fout2.textContent = ''; }
      var oud = knop.textContent; knop.disabled = true; knop.textContent = 'Bezig…';
      var body = new URLSearchParams(); body.set('token', tfa.dataset.token); if (csrf) body.set('_csrf', csrf.value);
      fetch(tfa.dataset.api, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (!j.ok) throw new Error(j.fout || 'Versturen mislukt.');
          stap1.hidden = true; stap2.hidden = false;
          status.textContent = kanaalTekst(j.kanaal, j.naar);
          if (codeVeld) { codeVeld.value = ''; codeVeld.focus(); }
        })
        .catch(function (e) { if (fout2) { fout2.textContent = e.message; fout2.hidden = false; } })
        .then(function () { bezig = false; knop.disabled = false; knop.textContent = oud; });
    }
    var stuur = document.getElementById('ond2faStuur'), opnieuw = document.getElementById('ond2faOpnieuw');
    if (stuur) stuur.addEventListener('click', function () { codeSturen(stuur); });
    if (opnieuw) opnieuw.addEventListener('click', function () { codeSturen(opnieuw); });
    // Na een serverfout staat de code al in het veld: stap 2 direct tonen
    if (codeVeld && codeVeld.value) { stap1.hidden = true; stap2.hidden = false; status.textContent = 'Vul de eerder ontvangen code in of vraag een nieuwe aan.'; }
  }

  /* ── Validatie vóór verzenden ── */
  function veldWrap(el) {
    return el.closest('.ond-veld') || el.closest('.ond-check') || el.closest('.ond-radio') || el;
  }
  function ondValideer(f) {
    f.querySelectorAll('.fout').forEach(function (el) { el.classList.remove('fout'); });
    var oud = document.getElementById('ondFoutmelding');
    if (oud) oud.remove();

    var fouten = [];
    var gezien = {};
    f.querySelectorAll('[required]').forEach(function (veld) {
      var leegVeld;
      if (veld.type === 'radio') {
        if (gezien[veld.name]) return;
        gezien[veld.name] = true;
        leegVeld = !f.querySelector('input[name="' + veld.name + '"]:checked');
        if (leegVeld) {
          f.querySelectorAll('input[name="' + veld.name + '"]').forEach(function (r) { veldWrap(r).classList.add('fout'); });
          fouten.push(veldWrap(veld));
        }
        return;
      }
      leegVeld = veld.type === 'checkbox' ? !veld.checked : !String(veld.value || '').trim();
      if (leegVeld) { var w = veldWrap(veld); w.classList.add('fout'); fouten.push(w); }
    });
    // "Ja, namelijk:" zonder toelichting
    var ja = f.querySelector('input[name="gezondheid_keuze"][value="ja"]');
    var toel = f.querySelector('[name="gezondheid_toelichting"]');
    if (ja && ja.checked && toel && !toel.value.trim()) { var tw = veldWrap(toel); tw.classList.add('fout'); fouten.push(tw); }
    // Handtekening verplicht
    if (data && !data.value) { if (wrap) { wrap.classList.add('fout'); fouten.push(wrap); } }
    // Verificatiecode verplicht (6 cijfers) als de stap er is
    var tfaCode = document.getElementById('ond2faCode');
    if (tfaCode) {
      var stap2zicht = document.getElementById('ond2faStap2') && !document.getElementById('ond2faStap2').hidden;
      if (!stap2zicht || !/^\d{6}$/.test(tfaCode.value.trim())) {
        var tw2 = document.getElementById('ond2fa'); if (tw2) { tw2.classList.add('fout'); fouten.push(tw2); }
      }
    }

    if (fouten.length) {
      var melding = document.createElement('div');
      melding.id = 'ondFoutmelding';
      melding.className = 'ds-melding ds-melding-error';
      melding.setAttribute('role', 'alert');
      melding.textContent = 'Er ontbreekt nog iets: de rood gemarkeerde velden zijn verplicht.';
      var links = f.querySelector('.ond-links');
      var doel = links ? (links.querySelector('.ond-doc') || links.firstElementChild) : null;
      if (doel && doel.parentNode) doel.parentNode.insertBefore(melding, doel); else f.prepend(melding);
      fouten[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
      return false;
    }
    if (window.portaalSubmit) window.portaalSubmit(f);
    return true;
  }
  window.ondValideer = ondValideer;
})();
