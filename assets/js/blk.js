/*
 * Paal 13 — Beter Leren Kennen (losse pagina, Portaal.dc sectie 03).
 * Stappen, validatie, adres-opzoeken (PDOK), foto-preview, diploma-chips,
 * tekenteller en autosave van het concept.
 * Configuratie uit window.BLK_CFG:
 *   { apiAdres, apiConcept, stap: 1, isEdit: false, totaal: 4 }
 */
(function () {
  'use strict';

  const CFG = Object.assign({ apiAdres: '', apiConcept: '', stap: 1, isEdit: false, totaal: 4 }, window.BLK_CFG || {});
  const NAMEN = { 1: 'Gegevens', 2: 'Achtergrond', 3: 'Motivatie', 4: 'Overig' };
  const form = document.getElementById('blkForm');
  if (!form) return;

  function el(id) { return document.getElementById(id); }
  function alle(sel, root) { return Array.from((root || document).querySelectorAll(sel)); }

  let huidig = CFG.stap || 1;

  /* ── Stappen ─────────────────────────────────────────────────── */
  function toonStap(nr, scroll) {
    huidig = nr;
    alle('.blk-stap').forEach(function (s) { s.classList.toggle('zichtbaar', s.id === 'fs' + nr); });
    alle('.blk-tab').forEach(function (t) {
      const n = parseInt(t.dataset.stap, 10);
      t.classList.toggle('actief', n === nr);
      t.classList.toggle('klaar', n < nr);
      // Stapnavigatie, geen tabpaneel: de huidige stap krijgt aria-current (WCAG 4.1.2).
      if (n === nr) t.setAttribute('aria-current', 'step'); else t.removeAttribute('aria-current');
    });
    alle('.blk-ov-rij').forEach(function (r) {
      const n = parseInt(r.dataset.stap, 10);
      r.classList.toggle('actief', n === nr);
      r.classList.toggle('klaar', n < nr);
    });
    const lbl = el('blkStapNr'); if (lbl) lbl.textContent = 'Stap ' + nr + ' van ' + CFG.totaal;
    const naam = el('blkStapNaam'); if (naam) naam.textContent = ' — ' + NAMEN[nr];
    const terug = el('blkTerug'); if (terug) terug.hidden = nr === 1;
    const volg = el('blkVolgende');
    if (volg) {
      const tekst = volg.querySelector('.tekst');
      if (tekst) tekst.textContent = nr === CFG.totaal ? (CFG.isEdit ? 'Formulier bijwerken' : 'Formulier inleveren') : 'Volgende';
    }
    if (scroll === false) return;
    const kop = document.querySelector('.blk-kop');
    if (kop) window.scrollTo({ top: Math.max(0, kop.getBoundingClientRect().top + window.scrollY - 8), behavior: 'smooth' });
  }

  function toonFout(stapNr, tekst, veld) {
    const stap = el('fs' + stapNr);
    if (!stap) return;
    let melding = stap.querySelector('.blk-fout');
    if (!melding) {
      melding = document.createElement('div');
      melding.className = 'blk-fout';
      melding.setAttribute('role', 'alert');
      stap.insertBefore(melding, stap.firstChild);
    }
    melding.innerHTML = '<svg class="ic" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><span></span>';
    melding.querySelector('span').textContent = tekst;
    if (veld) veldFout(veld, tekst);
  }

  /* Melding óók bij het veld zelf (de foutplek #blk-<naam>-fout uit blk_invullen.php, waar
     het veld met aria-describedby naar wijst). Het veld blijft ongeldig tot er iets in
     getypt wordt — niet na vier seconden, dan is de melding weg voordat je hem gelezen hebt. */
  function veldFout(veld, tekst) {
    const plek = veld.name ? el('blk-' + veld.name + '-fout') : null;
    veld.classList.add('fout');
    if (plek && window.P13Melding) P13Melding.fout(plek, tekst, veld);
    else { veld.setAttribute('aria-invalid', 'true'); veld.focus(); }
    veld.addEventListener('input', function () { veldFoutWeg(veld); }, { once: true });
  }
  function veldFoutWeg(veld) {
    const plek = veld.name ? el('blk-' + veld.name + '-fout') : null;
    veld.classList.remove('fout');
    veld.removeAttribute('aria-invalid');
    if (plek) { plek.textContent = ''; plek.hidden = true; }
  }
  // Na een serverfout: het veld met de fout krijgt dezelfde "weg bij typen" en de focus.
  alle('[aria-invalid="true"]', form).forEach(function (veld) {
    veld.addEventListener('input', function () { veldFoutWeg(veld); }, { once: true });
  });

  function wisFout(stapNr) {
    const stap = el('fs' + stapNr);
    const m = stap && stap.querySelector('.blk-fout');
    if (m) m.remove();
  }
  function labelVan(veld) {
    const lbl = veld.closest('.blk-veld');
    const span = lbl && lbl.querySelector('.blk-lbl');
    return span ? span.textContent.replace('*', '').trim() : (veld.getAttribute('placeholder') || 'een verplicht veld');
  }

  /* Controleer de verplichte velden van één stap; geeft het eerste lege veld terug. */
  function leegVerplicht(stapNr) {
    const stap = el('fs' + stapNr);
    if (!stap) return null;
    return alle('[data-verplicht]', stap).find(function (f) { return !f.value.trim(); }) || null;
  }

  function gaNaar(nr) {
    if (nr > huidig) {
      for (let s = huidig; s < nr; s++) {
        const leeg = leegVerplicht(s);
        if (leeg) {
          if (s !== huidig) toonStap(s);
          toonFout(s, 'Vul eerst "' + labelVan(leeg) + '" in voordat je verdergaat.', leeg);
          return;
        }
        wisFout(s);
      }
    }
    toonStap(nr);
  }

  alle('.blk-tab').forEach(function (t) { t.addEventListener('click', function () { gaNaar(parseInt(t.dataset.stap, 10)); }); });
  alle('.blk-ov-wijzig').forEach(function (b) { b.addEventListener('click', function () { gaNaar(parseInt(b.closest('.blk-ov-rij').dataset.stap, 10)); }); });
  const terugKnop = el('blkTerug');
  if (terugKnop) terugKnop.addEventListener('click', function () { if (huidig > 1) toonStap(huidig - 1); });
  const volgKnop = el('blkVolgende');
  /* De knop is een echte submit (werkt zonder JavaScript). Hier: tussenstappen en een mislukte
     controle tegenhouden; bij de laatste stap gaat het gewone versturen door, zodat actie.js de
     laadstand geeft. Ook Enter in een veld komt hier langs (de browser klikt dan deze knop). */
  if (volgKnop) volgKnop.addEventListener('click', function (e) {
    if (huidig < CFG.totaal) { e.preventDefault(); gaNaar(huidig + 1); return; }
    if (!valideer()) { e.preventDefault(); return; }
    if (concept) concept.stop(); // het echte opslaan neemt het over; geen concept meer sturen
  });

  /* ── Validatie vóór inleveren ─────────────────────────────────── */
  function velddata() {
    const d = new URLSearchParams();
    alle('input, textarea, select', form).forEach(function (f) {
      if (!f.name || f.type === 'file' || f.type === 'hidden' || f.name === 'reden_wijziging') return;
      d.append(f.name, f.value);
    });
    return d.toString();
  }
  const beginstand = velddata();

  function valideer() {
    for (let s = 1; s <= CFG.totaal; s++) {
      const leeg = leegVerplicht(s);
      if (leeg) { toonStap(s); toonFout(s, 'Vul eerst "' + labelVan(leeg) + '" in.', leeg); return false; }
      wisFout(s);
    }
    const reden = form.querySelector('[name="reden_wijziging"]');
    if (reden && CFG.isEdit && velddata() !== beginstand && !reden.value.trim()) {
      toonStap(CFG.totaal);
      toonFout(CFG.totaal, 'Je hebt iets aangepast. Vul kort in waarom je het formulier aanpast.', reden);
      return false;
    }
    return true;
  }

  /* ── Tekenteller (motivatie) ─────────────────────────────────── */
  alle('[data-teller]', form).forEach(function (f) {
    const t = el(f.dataset.teller);
    if (!t) return;
    const max = f.getAttribute('maxlength') || '1000';
    const upd = function () { t.textContent = f.value.length + ' / ' + max + ' tekens'; };
    f.addEventListener('input', upd); upd();
  });

  /* ── Diploma-chips ───────────────────────────────────────────── */
  alle('.blk-chip[data-voeg]', form).forEach(function (c) {
    c.addEventListener('click', function () {
      const doel = form.querySelector('[name="' + c.dataset.doel + '"]');
      if (!doel) return;
      const huidige = doel.value.trim();
      if (huidige.toLowerCase().indexOf(c.dataset.voeg.toLowerCase()) !== -1) return;
      doel.value = huidige ? huidige.replace(/[\s·,]+$/, '') + ' · ' + c.dataset.voeg : c.dataset.voeg;
      doel.dispatchEvent(new Event('input', { bubbles: true }));
    });
  });

  /* ── Adres opzoeken (PDOK) ───────────────────────────────────── */
  (function () {
    const pc = el('adr-pc'), hn = el('adr-hn'), st = el('adr-st'), wp = el('adr-wp');
    const box = el('adrBox'), regel = el('adrRegel'), sub = el('adrSub'), handmatig = el('adrHandmatig');
    if (!pc || !hn || !st || !wp || !box) return;
    let timer;

    function toon(staat, r, s) {
      box.hidden = false;
      box.classList.remove('zoeken', 'mis');
      if (staat) box.classList.add(staat);
      regel.textContent = r;
      sub.innerHTML = '';
      sub.appendChild(document.createTextNode(s));
      if (staat === '') {
        sub.appendChild(document.createTextNode(' '));
        const a = document.createElement('a'); a.href = '#'; a.textContent = 'Klopt het niet? Pas aan.';
        a.addEventListener('click', function (e) { e.preventDefault(); handmatig.hidden = false; st.focus(); });
        sub.appendChild(a);
      }
    }

    function zoek() {
      const pcVal = pc.value.replace(/\s+/g, '').toUpperCase();
      if (!/^[0-9]{4}[A-Z]{2}$/.test(pcVal) || !hn.value.trim()) return;
      toon('zoeken', 'Adres opzoeken…', 'Even geduld.');
      fetch(CFG.apiAdres + '?postcode=' + encodeURIComponent(pcVal) + '&huisnummer=' + encodeURIComponent(hn.value.trim()))
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d.ok) {
            if (!st.dataset.touched) st.value = d.straat;
            if (!wp.dataset.touched) wp.value = d.woonplaats;
            pc.value = d.postcode;
            handmatig.hidden = true;
            toon('', st.value + ' ' + hn.value.trim() + ', ' + wp.value, 'Straat en woonplaats zijn automatisch gevonden.');
          } else {
            handmatig.hidden = false;
            toon('mis', d.fout || 'Adres niet gevonden', 'Vul straat en woonplaats hieronder zelf in.');
          }
        })
        .catch(function () {
          handmatig.hidden = false;
          toon('mis', 'Kon het adres niet automatisch ophalen', 'Vul straat en woonplaats hieronder zelf in.');
        });
    }
    function later() { clearTimeout(timer); timer = setTimeout(zoek, 400); }
    pc.addEventListener('input', later);
    hn.addEventListener('input', later);
    st.addEventListener('input', function () { st.dataset.touched = '1'; });
    wp.addEventListener('input', function () { wp.dataset.touched = '1'; });
  })();

  /* ── Foto ────────────────────────────────────────────────────── */
  (function () {
    const inp = el('fotoInput'), avatar = el('fotoAvatar'), rij = el('fotoRij');
    const naam = el('fotoNaam'), status = el('fotoStatus'), thumb = el('fotoThumb'), verwijderVlag = el('fotoVerwijderen');
    if (!inp || !avatar || !rij) return;
    // Pas nu de knoppen tonen en het bestandsveld verbergen; zonder JS blijft het gewone veld staan.
    inp.closest('.blk-foto-zone')?.classList.add('blk-foto-js');
    inp.closest('.blk-veld-foto')?.classList.add('blk-foto-js');
    const avatarLeeg = avatar.innerHTML, thumbLeeg = thumb ? thumb.innerHTML : '';

    function kies(capture) {
      if (capture) inp.setAttribute('capture', 'user'); else inp.removeAttribute('capture');
      inp.click();
    }
    const maken = el('fotoMaken'), kiezen = el('fotoKiezen');
    if (maken) maken.addEventListener('click', function () { kies(true); });
    if (kiezen) kiezen.addEventListener('click', function () { kies(false); });
    const wijzig = el('fotoWijzig'), verwijder = el('fotoVerwijder');
    if (wijzig) wijzig.addEventListener('click', function () { kies(false); });
    if (verwijder) verwijder.addEventListener('click', function () {
      inp.value = '';
      if (verwijderVlag) verwijderVlag.value = '1';
      avatar.innerHTML = avatarLeeg;
      if (thumb) thumb.innerHTML = thumbLeeg;
      rij.hidden = true;
    });

    inp.addEventListener('change', function () {
      const f = inp.files && inp.files[0];
      if (!f) return;
      const fotoFout = el('blk-foto-fout');
      if (f.size > 5 * 1024 * 1024) {
        // Bij de foto zelf melden (P13Melding uit app.js), nooit alert().
        const tekst = 'De foto is groter dan 5 MB. Kies een kleinere foto.';
        if (fotoFout && window.P13Melding) P13Melding.fout(fotoFout, tekst);
        else if (fotoFout) { fotoFout.textContent = tekst; fotoFout.hidden = false; }
        inp.value = ''; return;
      }
      if (fotoFout) { fotoFout.textContent = ''; fotoFout.hidden = true; }
      const r = new FileReader();
      r.onload = function (e) {
        const img = '<img src="' + e.target.result + '" alt="">';
        avatar.innerHTML = img;
        if (thumb) thumb.innerHTML = img;
        if (naam) naam.textContent = f.name;
        if (status) status.textContent = 'Gekozen · wordt meegestuurd bij het inleveren';
        if (verwijderVlag) verwijderVlag.value = '0';
        rij.hidden = false;
      };
      r.readAsDataURL(f);
    });
  })();

  /* ── Autosave concept (gedeelde helper assets/js/portaal_concept.js) ── */
  const tekstVelden = ['postcode', 'huisnummer', 'straat', 'woonplaats', 'opleiding', 'werkervaring', 'motivatie_lifeguard', 'motivatie_strand', 'diplomas', 'bijzonderheden'];
  const socialVelden = alle('[name^="social_"]', form);

  /* Social media: de losse platform-velden gaan als één tekst mee in het concept
     (zelfde vorm als de server opslaat: "Instagram @naam · TikTok @naam"). */
  function socialTekst() {
    return socialVelden.map(function (f) {
      const v = f.value.trim();
      const naam = f.closest('.blk-social-rij').querySelector('.blk-social-lbl').textContent.trim();
      return v ? naam + ' ' + v : '';
    }).filter(Boolean).join(' · ');
  }

  /* Huidige formulierstaat als {veld: waarde}; null zolang er niets is ingevuld. */
  function conceptData() {
    const d = {};
    let iets = false;
    tekstVelden.forEach(function (n) {
      const f = form.querySelector('[name="' + n + '"]');
      if (f && f.value.trim()) { d[n] = f.value; iets = true; }
    });
    const soc = socialTekst();
    if (soc) { d.social_media = soc; iets = true; }
    return iets ? d : null;
  }

  let concept = null;
  if (window.PortaalConcept && CFG.apiConcept) {
    concept = PortaalConcept.maak({
      url: CFG.apiConcept,
      csrf: (form.querySelector('[name="_csrf"]') || {}).value || '',
      verzamel: conceptData,
      statusEl: el('blkAutosave'),
      tekstEl: el('blkAutosaveTekst')
    });
    concept.prime(); // wat al geladen is, hoeft niet opnieuw gestuurd
    form.addEventListener('input', function (e) {
      if (tekstVelden.indexOf(e.target.name) === -1 && (e.target.name || '').indexOf('social_') !== 0) return;
      concept.plan();
    });
    alle('.blk-tab, #blkTerug, #blkVolgende').forEach(function (k) { k.addEventListener('click', function () { setTimeout(function () { concept.bewaarNu(); }, 50); }); });
  }

  /* ── Start ───────────────────────────────────────────────────── */
  form.classList.add('blk-js');   // pas nu één stap tegelijk (zie stijl.css)
  toonStap(huidig, huidig !== 1);
  // Terug van de server met een fout: meteen naar het veld dat het betreft.
  const serverFout = form.querySelector('[aria-invalid="true"]');
  if (serverFout) serverFout.focus();
  // Straat/woonplaats pas tonen als het opzoeken mislukt (ontwerp: gevonden adres als kaartje)
  const _st = el('adr-st'), _wp = el('adr-wp'), _hm = el('adrHandmatig');
  if (_st && _wp && _hm && !_st.value && !_wp.value) _hm.hidden = true;
})();
