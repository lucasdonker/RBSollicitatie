<?php
/**
 * Selectiedag — inchecken op de tablet (zelfservice).
 *
 * Draait op een gedeelde iPad zonder ingelogde gebruiker. Beveiligd met een
 * toegangscode (instelling 'selectie.incheck_code') die in de URL zit:
 *   /portaal/selectie_incheck.php?code=XXXX
 *
 * De sollicitant typt zijn naam, ziet zijn groep, en is aangemeld. Klaar.
 */
require_once __DIR__ . '/../includes/functions.php';

$seizoen = actief_seizoen();
$sid = $seizoen ? (int)$seizoen['id'] : 0;

$verwachte_code = function_exists('instelling') ? instelling('selectie.incheck_code', '') : '';
$code = $_GET['code'] ?? ($_POST['code'] ?? '');
$toegang = $verwachte_code !== '' && hash_equals($verwachte_code, (string)$code);

// AJAX-endpoints (alleen met geldige code)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $toegang && $sid) {
    header('Content-Type: application/json');
    $actie = $_POST['actie'] ?? '';

    if ($actie === 'zoek') {
        echo json_encode(['ok' => true, 'resultaten' => selectie_zoek_kandidaat($sid, $_POST['term'] ?? '')]);
        exit;
    }
    if ($actie === 'inchecken') {
        $res = selectie_aanwezig_zetten($sid, (int)$_POST['sol_id'], true);
        echo json_encode(['ok' => true] + $res);
        exit;
    }
    echo json_encode(['ok' => false]);
    exit;
}

$logo = function_exists('site_logo_url') ? (site_logo_url() ?: '') : '';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>Aanmelden — Selectiedag Paal 13</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/stijl.css">
<style>
  body { background:linear-gradient(160deg,#054d8a,#032e52); min-height:100vh; margin:0; display:flex; align-items:center; justify-content:center; padding:20px; font-family:Inter,system-ui,sans-serif; }
  .kiosk { background:#fff; border-radius:20px; box-shadow:0 20px 60px rgba(0,0,0,.3); width:100%; max-width:520px; padding:32px; }
  .kiosk img { height:48px; display:block; margin:0 auto 20px; }
  .kiosk h1 { text-align:center; font-size:26px; color:#054d8a; margin:0 0 6px; }
  .kiosk .sub { text-align:center; color:#5a6478; font-size:15px; margin:0 0 24px; }
  .zoekveld { width:100%; font-size:22px; padding:18px 20px; border:2px solid #d0d5dd; border-radius:14px; box-sizing:border-box; text-align:center; }
  .zoekveld:focus { outline:none; border-color:#054d8a; }
  .res { margin-top:16px; display:flex; flex-direction:column; gap:10px; }
  .res-knop { display:flex; align-items:center; gap:12px; width:100%; padding:18px 20px; font-size:20px; text-align:left; border:2px solid #e5e8ec; border-radius:14px; background:#fff; cursor:pointer; transition:.15s; }
  .res-knop:hover, .res-knop:active { border-color:#054d8a; background:#f0f6fc; }
  .res-knop.klaar { border-color:#1d9e75; background:#eafaf3; color:#0d6b4a; }
  .res-naam { flex:1; font-weight:600; }
  .res-meta { font-size:14px; color:#5a6478; font-weight:400; }
  .bevestig { text-align:center; padding:40px 20px; }
  .bevestig .vink { font-size:72px; margin-bottom:12px; }
  .bevestig h2 { font-size:30px; color:#0d6b4a; margin:0 0 8px; }
  .bevestig .groep { font-size:48px; font-weight:800; color:#054d8a; margin:14px 0; }
  .bevestig p { font-size:18px; color:#5a6478; }
  .btn-groot { display:inline-block; margin-top:20px; padding:16px 40px; font-size:20px; background:#D43D00; color:#fff; border:none; border-radius:14px; cursor:pointer; }
  .geen-toegang { text-align:center; color:#fff; max-width:420px; }
  .geen-toegang h1 { font-size:24px; }
</style>
</head>
<body>

<?php if (!$toegang): ?>
  <div class="geen-toegang">
    <h1>🔒 Geen geldige toegangscode</h1>
    <p>Deze incheck-pagina is alleen bereikbaar via de juiste link met code. Vraag het organisatieteam om de tablet klaar te zetten.</p>
  </div>
<?php elseif (!$sid): ?>
  <div class="geen-toegang"><h1>Geen actief seizoen</h1></div>
<?php else: ?>

<div class="kiosk" id="kiosk">
  <?php if ($logo): ?><img src="<?= h($logo) ?>" alt="Paal 13"><?php endif; ?>

  <div id="stapZoek">
    <h1>Welkom!</h1>
    <p class="sub">Typ je naam om je aan te melden voor de selectiedag.</p>
    <input type="text" class="zoekveld" id="zoek" placeholder="Je naam…" autocomplete="off" autofocus>
    <div class="res" id="resultaten"></div>
  </div>

  <div id="stapBevestig" style="display:none">
    <div class="bevestig">
      <div class="vink">✅</div>
      <h2 id="bevNaam"></h2>
      <p>Je bent aangemeld! Je zit in:</p>
      <div class="groep" id="bevGroep"></div>
      <p id="bevInstructie">Ga naar de tafel van je groep.</p>
      <button class="btn-groot" onclick="reset()">Volgende persoon</button>
    </div>
  </div>
</div>

<script>
const CODE = <?= json_encode((string)$code) ?>;
let zoekTimer = null;

const zoekEl = document.getElementById('zoek');
zoekEl.addEventListener('input', () => {
  clearTimeout(zoekTimer);
  const term = zoekEl.value.trim();
  if (term.length < 2) { document.getElementById('resultaten').innerHTML = ''; return; }
  zoekTimer = setTimeout(() => zoek(term), 250);
});

function zoek(term) {
  fetch(location.pathname, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ actie:'zoek', code:CODE, term }) })
    .then(r => r.json())
    .then(d => {
      const box = document.getElementById('resultaten');
      box.innerHTML = '';
      if (!d.ok || !d.resultaten.length) {
        box.innerHTML = '<p style="text-align:center;color:#5a6478">Geen naam gevonden. Vraag het organisatieteam om hulp.</p>';
        return;
      }
      d.resultaten.forEach(k => {
        const knop = document.createElement('button');
        knop.className = 'res-knop' + (k.aanwezig == 1 ? ' klaar' : '');
        const groep = k.groep_nummer ? 'groep ' + k.groep_nummer : 'nog geen groep';
        knop.innerHTML = '<span class="res-naam">' + esc(k.naam) + '</span>' +
          '<span class="res-meta">' + (k.aanwezig == 1 ? '✓ al aangemeld' : groep) + '</span>';
        knop.onclick = () => inchecken(k.sol_id, k.naam);
        box.appendChild(knop);
      });
    });
}

function inchecken(solId, naam) {
  fetch(location.pathname, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ actie:'inchecken', code:CODE, sol_id:solId }) })
    .then(r => r.json())
    .then(d => {
      if (!d.ok) return;
      document.getElementById('bevNaam').textContent = naam;
      const g = d.groep_nummer ? (d.groep_naam || ('Groep ' + d.groep_nummer)) : 'Nog geen groep';
      document.getElementById('bevGroep').textContent = g;
      document.getElementById('bevInstructie').textContent = d.groep_nummer
        ? 'Ga naar de tafel van je groep.'
        : 'Meld je bij het organisatieteam voor je groep.';
      document.getElementById('stapZoek').style.display = 'none';
      document.getElementById('stapBevestig').style.display = 'block';
    });
}

function reset() {
  zoekEl.value = '';
  document.getElementById('resultaten').innerHTML = '';
  document.getElementById('stapBevestig').style.display = 'none';
  document.getElementById('stapZoek').style.display = 'block';
  zoekEl.focus();
}

function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
</script>

<?php endif; ?>
</body>
</html>
