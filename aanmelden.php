<?php
require_once __DIR__.'/includes/functions.php';
require_once __DIR__.'/includes/aanmelden_kern.php';
$db=db(); $seizoen=actief_seizoen();
$open=$seizoen&&$seizoen['status']==='open';
$fout=''; $succes=false;

// Bron-attributie: waar komt deze bezoeker vandaan? (landingspagina/advertentie)
// Komt binnen via ?bron=... (of in de POST, zodat hij door de hele flow meereist).
$bron_code = substr(trim((string)($_POST['bron_code'] ?? $_GET['bron'] ?? '')), 0, 80);
$ingebed = !empty($_GET['ingebed']);

if($_SERVER['REQUEST_METHOD']==='POST'&&$open){
    csrf_check();
    // Bron meegeven aan de gedeelde bron van waarheid.
    $invoer = $_POST;
    if ($bron_code !== '') {
        $invoer['bron_code'] = $bron_code;
        if (function_exists('landing_via_bron')) {
            $lp = landing_via_bron($bron_code);
            if ($lp) $invoer['bron_label'] = $lp['titel'] ?? $bron_code;
        }
    }
    $resultaat = sollicitatie_aanmaken($invoer, ['ip'=>$_SERVER['REMOTE_ADDR']??'', 'via'=>'website_formulier']);
    if ($resultaat['ok']) {
        $succes = true;
    } else {
        $fout = $resultaat['fout'];
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $open && $bron_code !== '') {
    // Formulier geopend vanaf een landingspagina → tel als "formulier gestart" (anoniem).
    if (function_exists('landing_track')) {
        $landing_id = null;
        if (function_exists('landing_via_bron')) { $lp = landing_via_bron($bron_code); $landing_id = $lp['id'] ?? null; }
        landing_track('formulier_start', $landing_id, $bron_code);
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Aanmelden als lifeguard — Paal 13</title>
<link rel="stylesheet" href="<?=APP_URL?>/assets/css/stijl.css">
<style>
body{background:#001530;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.wrap{background:#fff;border-radius:16px;width:100%;max-width:500px;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,.3)}
.head{background:var(--navy);padding:26px 30px;text-align:center}
.head img{height:50px}
.head h1{color:var(--oranje);font-size:19px;margin:12px 0 3px}
.head p{color:rgba(255,255,255,.6);font-size:13px}
.body{padding:26px 30px}
.succes-blok{text-align:center;padding:16px 0}
.succes-blok .icoon{font-size:52px;margin-bottom:14px}
.succes-blok h2{color:var(--navy);margin-bottom:8px}
.succes-blok p{color:var(--tekst-zacht);font-size:14px;line-height:1.7}
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <img src="<?=APP_URL?>/assets/img/logo.png" alt="Paal 13">
    <h1>Word lifeguard!</h1>
    <p>Reddingsbrigade Callantsoog &amp; Groote Keeten</p>
  </div>
  <div class="body">
    <?php if(!$open): ?>
    <div class="flash flash-waarschuwing"><strong>De aanmelding is momenteel gesloten.</strong><br>Houd onze website in de gaten voor het nieuwe seizoen.</div>
    <?php elseif($succes): ?>
    <div class="succes-blok">
      <div class="icoon">🌊</div>
      <h2>Aanmelding ontvangen!</h2>
      <p>We hebben een bevestiging gestuurd naar jouw e-mailadres. Je ontvangt bericht als je wordt uitgenodigd voor de selectiedag.</p>
    </div>
    <?php else: ?>
    <?php if($fout): ?><div class="flash flash-fout"><?=h($fout)?></div><?php endif; ?>
    <p style="font-size:13px;color:var(--tekst-zacht);margin-bottom:18px">Meld je aan voor de selectieprocedure <?=h($seizoen['naam']??'')?></p>
    <form method="POST">
      <?= csrf_input() ?>
      <?php if ($bron_code !== ''): ?><input type="hidden" name="bron_code" value="<?= h($bron_code) ?>"><?php endif; ?>
      <div class="form-rij">
        <div class="form-groep"><label>Voornaam <span class="vereist">*</span></label><input type="text" name="voornaam" value="<?=h($_POST['voornaam']??'')?>" required autocomplete="given-name"></div>
        <div class="form-groep"><label>Tussenvoegsel</label><input type="text" name="tussenvoegsel" value="<?=h($_POST['tussenvoegsel']??'')?>"></div>
      </div>
      <div class="form-groep"><label>Achternaam <span class="vereist">*</span></label><input type="text" name="achternaam" value="<?=h($_POST['achternaam']??'')?>" required autocomplete="family-name"></div>
      <div class="form-rij">
        <div class="form-groep"><label>Geboortedatum <span class="vereist">*</span></label><input type="date" name="geboortedatum" id="geboortedatum" value="<?=h($_POST['geboortedatum']??'')?>" required></div>
        <div class="form-groep"><label>Geslacht</label><select name="geslacht"><option value="onbekend">Zeg ik liever niet</option><option value="man" <?=($_POST['geslacht']??'')==='man'?'selected':''?>>Man</option><option value="vrouw" <?=($_POST['geslacht']??'')==='vrouw'?'selected':''?>>Vrouw</option><option value="anders" <?=($_POST['geslacht']??'')==='anders'?'selected':''?>>Anders</option></select></div>
      </div>
      <div class="form-groep"><label>E-mailadres <span class="vereist">*</span></label><input type="email" name="email" value="<?=h($_POST['email']??'')?>" required autocomplete="email"><div class="form-hulp">Hierop ontvang je de bevestiging en uitnodigingen.</div></div>
      <div class="form-groep"><label>Telefoonnummer <span class="vereist">*</span></label><input type="tel" name="telefoon" value="<?=h($_POST['telefoon']??'')?>" required autocomplete="tel" placeholder="06-12345678"></div>
      <div class="form-groep" style="margin-top:16px">
        <label style="display:flex;align-items:flex-start;gap:9px;font-weight:400;cursor:pointer">
          <input type="checkbox" name="akkoord" style="width:auto;margin-top:3px;flex-shrink:0" required>
          <span style="font-size:13px">Ik ga akkoord met de <a href="https://paal13.com/privacy-beleid/" target="_blank" style="color:var(--oranje)">privacyverklaring</a>.</span>
        </label>
      </div>
      <?php // Ouder-toestemming: alleen nodig voor 15-jarigen (jeugdledenuitzondering).
            // Standaard verborgen; verschijnt via JS zodra de geboortedatum onder de 16 jaar valt. ?>
      <div id="ouderBlok" style="display:none;margin-top:14px;padding:14px 16px;background:var(--oranje-bg);border:1px solid rgba(212,61,0,.25);border-radius:8px">
        <div style="font-size:13px;font-weight:600;color:var(--oranje);margin-bottom:8px">👪 Toestemming ouder/verzorger</div>
        <p style="font-size:12.5px;color:var(--tekst-zacht);margin:0 0 12px">Omdat je nog geen 16 jaar bent, hebben we toestemming van een ouder of verzorger nodig voor het verwerken van je gegevens.</p>
        <div class="form-groep" style="margin-bottom:10px">
          <label>Naam ouder/verzorger</label>
          <input type="text" name="ouder_toestemming_naam" value="<?=h($_POST['ouder_toestemming_naam']??'')?>" placeholder="Voor- en achternaam">
        </div>
        <label style="display:flex;align-items:flex-start;gap:9px;font-weight:400;cursor:pointer">
          <input type="checkbox" name="ouder_akkoord" style="width:auto;margin-top:3px;flex-shrink:0">
          <span style="font-size:13px">Als ouder/verzorger geef ik toestemming voor het verwerken van de gegevens van mijn kind conform de <a href="https://paal13.com/privacy-beleid/" target="_blank" style="color:var(--oranje)">privacyverklaring</a>.</span>
        </label>
      </div>
      <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;margin-top:8px">Aanmelden →</button>
    </form>
    <?php endif; ?>
  </div>
</div>
<script>
// Ouder-toestemmingsblok tonen zodra de gekozen geboortedatum een leeftijd onder 16 aangeeft.
(function(){
  var dob = document.getElementById('geboortedatum');
  var blok = document.getElementById('ouderBlok');
  if (!dob || !blok) return;
  function leeftijdOp(d){
    if (!d) return null;
    var g = new Date(d); if (isNaN(g)) return null;
    var n = new Date(), l = n.getFullYear() - g.getFullYear();
    var m = n.getMonth() - g.getMonth();
    if (m < 0 || (m === 0 && n.getDate() < g.getDate())) l--;
    return l;
  }
  function update(){
    var l = leeftijdOp(dob.value);
    var toon = (l !== null && l < 16);
    blok.style.display = toon ? 'block' : 'none';
    // Velden alleen verplicht maken als het blok zichtbaar is (anders blokkeert een verborgen veld de submit)
    var naam = blok.querySelector('[name="ouder_toestemming_naam"]');
    var akk  = blok.querySelector('[name="ouder_akkoord"]');
    if (naam) naam.required = toon;
    if (akk)  akk.required = toon;
  }
  dob.addEventListener('change', update);
  dob.addEventListener('input', update);
  update(); // ook direct bij laden (bv. na een validatiefout met ingevulde datum)
})();
</script>
</body>
</html>
