<?php
/**
 * Zelf inchecken op de tablet bij de ingang — selectiedag én trainingsweekend.
 *   /portaal/incheck.php?ev=selectiedag|tw&code=XXXXXX
 *
 * Geen inlog: een gedeelde tablet, beveiligd met een toegangscode per evenement (Beheer ›
 * Selectiedag › Inchecken, of Trainingsweekend). Beeldtaal van ontwerp 3c (Beheer.dc.html).
 *
 * Privacy: de tablet staat voor iedereen zichtbaar. Er is dus nooit een lijst van alle namen;
 * pas na drie letters hooguit drie treffers, met een kleine foto om jezelf te herkennen.
 *
 * Iemand van de organisatie kan hier met de pincode het inchecken afsluiten (wie dan niet
 * is ingecheckt: niet verschenen). Laatkomers kunnen daarna gewoon nog inchecken.
 */
require_once __DIR__ . '/../includes/functions.php';

$ev  = (string)($_GET['ev'] ?? ($_POST['ev'] ?? 'selectiedag'));
$def = evenement_def($ev);
if (!$def) { http_response_code(404); exit('Deze pagina bestaat niet.'); }
module_vereist($def['module']);

$seizoen = actief_seizoen();
$sid = $seizoen ? (int)$seizoen['id'] : 0;

$verwachte_code = function_exists('instelling') ? (string)instelling($def['code'], '') : '';
$code = (string)($_GET['code'] ?? ($_POST['code'] ?? ''));
// Code raden: na 20 foute pogingen per uur wordt dit IP-adres niet meer vergeleken, ook niet
// met de goede code. De tablet zelf gebruikt steeds de juiste code en telt dus nooit mee.
$geblokt = $code !== '' && rate_limit_telling('kiosk_code', rate_identiteit(), 3600) >= 20;
$toegang = !$geblokt && $verwachte_code !== '' && hash_equals($verwachte_code, $code);
if ($code !== '' && !$toegang && !$geblokt) rate_limit('kiosk_code', rate_identiteit(), 20, 3600);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $toegang && $sid) {
    header('Content-Type: application/json; charset=utf-8');
    // Ruim genoeg voor een hele dag vanaf één tablet (elke toetsaanslag zoekt).
    if (!rate_limit('kiosk_actie', rate_identiteit(), 1500, 3600)) { echo json_encode(['ok' => false]); exit; }
    $actie = (string)($_POST['actie'] ?? '');
    if ($actie === 'zoek') {
        echo json_encode(['ok' => true, 'resultaten' => evenement_zoek($ev, $sid, (string)($_POST['term'] ?? ''))]);
        exit;
    }
    if ($actie === 'inchecken') {
        $sol_id = (int)($_POST['sol_id'] ?? 0);
        if (!evenement_is_kandidaat($ev, $sid, $sol_id)) { echo json_encode(['ok' => false]); exit; }
        $was = evenement_is_aanwezig($ev, $sid, $sol_id);
        $r = evenement_inchecken($ev, $sid, $sol_id, true, 'tablet');
        // Nog iets te tekenen? Dan de link sturen en het op de tablet zeggen (alleen de naam van het formulier).
        $f = evenement_formulieren_melden($sol_id);
        // Alleen een nieuwe check-in mag via "Dit ben ik niet" terug (was iemand er al, dan niet).
        $ongedaan = $was ? null : evenement_ongedaan_token($ev, $sid, $sol_id, time());
        echo json_encode(['ok' => true] + $r + ['formulieren' => $f['open'], 'formulieren_tekst' => $f['tekst'], 'ongedaan' => $ongedaan]);
        exit;
    }
    if ($actie === 'ongedaan') {
        $sol_id = (int)($_POST['sol_id'] ?? 0);
        if (!evenement_ongedaan_token_ok($ev, $sid, $sol_id, (string)($_POST['token'] ?? ''))) {
            echo json_encode(['ok' => false, 'fout' => 'Dat kan nu niet meer. Vraag iemand van de organisatie.']);
            exit;
        }
        evenement_inchecken($ev, $sid, $sol_id, false, 'tablet');
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($actie === 'afsluiten') {
        // Pincode raden: 5 pogingen per kwartier, los van de zoeklimiet.
        if (!rate_limit('kiosk_pin', rate_identiteit(), 5, 900)) {
            echo json_encode(['ok' => false, 'fout' => 'Te vaak geprobeerd. Probeer het over een kwartier opnieuw.']);
            exit;
        }
        if (!evenement_pin_ok($ev, (string)($_POST['pin'] ?? ''))) {
            echo json_encode(['ok' => false, 'fout' => 'Onjuiste pincode.']);
            exit;
        }
        $n = evenement_afsluiten($ev, $sid, 0);
        echo json_encode(['ok' => true, 'aantal' => $n]);
        exit;
    }
    echo json_encode(['ok' => false]);
    exit;
}

$datum = $ev === 'tw' ? ($seizoen['datum_tw_start'] ?? null) : ($seizoen['datum_selectiedag'] ?? null);
$titel = $ev === 'tw' ? 'Welkom op het trainingsweekend' : 'Welkom op de selectiedag';
$logo  = function_exists('site_logo_url') ? (site_logo_url() ?: '') : '';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><?php /* inzoomen mag altijd (WCAG 1.4.4) */ ?>
<meta name="robots" content="noindex">
<title>Inchecken — Paal 13</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/stijl.css?v=<?= @filemtime(__DIR__ . '/../assets/css/stijl.css') ?>">
</head>
<body class="pt-kiosk-body">

<?php if (!$toegang): ?>
  <main class="pt-kiosk-dicht">
    <h1><?= icoon('slot', 22) ?> Geen geldige toegangscode</h1>
    <p>Deze pagina is alleen bereikbaar via de link met code. Vraag de organisatie om de tablet klaar te zetten.</p>
  </main>
<?php elseif (!$sid): ?>
  <main class="pt-kiosk-dicht"><h1>Geen actief seizoen</h1></main>
<?php else: ?>

<main class="pt-kiosk" data-kiosk data-ev="<?= h($ev) ?>" data-code="<?= h($code) ?>">
  <header class="pt-kiosk-band">
    <?php if ($logo): ?><img src="<?= h($logo) ?>" alt="" class="pt-kiosk-logo"><?php endif; ?>
    <div class="pt-kiosk-titel">
      <h1><?= h($titel) ?></h1>
      <?php if ($datum): ?><span><?= h(datum_nl($datum)) ?></span><?php endif; ?>
    </div>
  </header>

  <section class="pt-kiosk-stap" data-stap="zoek">
    <label for="kioskZoek" class="pt-kiosk-vraag">Typ je naam om in te checken</label>
    <input type="text" id="kioskZoek" class="pt-kiosk-zoek" autocomplete="off" autocapitalize="words" spellcheck="false"
           placeholder="Je voor- of achternaam" aria-describedby="kioskHint">
    <p id="kioskHint" class="pt-kiosk-hint">Na drie letters verschijnt je naam.</p>
    <p class="pt-kiosk-hint" data-kiosk-melding role="status"></p>
    <ul class="pt-kiosk-lijst" data-resultaten aria-live="polite"></ul>
  </section>

  <section class="pt-kiosk-stap pt-kiosk-klaar" data-stap="klaar" hidden aria-live="assertive">
    <span class="pt-kiosk-vinkrond"><?= icoon('vink', 44) ?></span>
    <h2 data-klaar-naam></h2>
    <p data-klaar-tekst></p>
    <div class="pt-kiosk-groep" data-klaar-groep hidden></div>
    <div class="pt-kiosk-letop" data-klaar-letop role="alert" hidden>
      <?= icoon('waarschuwing', 22) ?>
      <div>
        <strong>Let op, nog niet getekend: <span data-letop-wat></span></strong>
        <p data-letop-tekst></p>
      </div>
    </div>
    <button type="button" class="ds-btn ds-btn-primair pt-kiosk-volgende" data-volgende>Volgende persoon</button>
    <button type="button" class="ds-btn ds-btn-zacht pt-kiosk-volgende" data-niet-ik hidden>Dit ben ik niet</button>
  </section>

  <footer class="pt-kiosk-voet">
    <details class="pt-kiosk-org">
      <summary>Organisatie</summary>
      <form data-afsluiten class="pt-kiosk-orgform">
        <label for="kioskPin">Pincode om het inchecken af te sluiten</label>
        <p class="pt-kiosk-hint">Wie dan nog niet is ingecheckt, komt op niet verschenen. Laatkomers kunnen daarna nog inchecken.</p>
        <div class="pt-kiosk-orgrij">
          <input type="password" id="kioskPin" inputmode="numeric" autocomplete="off" class="ds-input">
          <button type="submit" class="ds-btn ds-btn-secundair">Afsluiten</button>
        </div>
        <p class="pt-kiosk-orgmelding" data-org-melding role="status"></p>
      </form>
    </details>
  </footer>
</main>
<script src="<?= APP_URL ?>/assets/js/kiosk.js?v=<?= @filemtime(__DIR__ . '/../assets/js/kiosk.js') ?>" defer></script>
<?php endif; ?>
</body>
</html>
