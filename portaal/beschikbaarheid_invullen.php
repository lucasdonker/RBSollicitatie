<?php
/**
 * Losse beschikbaarheid-invulpagina (het ontwerp maakt beschikbaarheid een eigen taak).
 * Hergebruikt het gedeelde velden-include en de gedeelde opslagfunctie, zodat er geen
 * duplicatie is met het BLK-formulier.
 */
require_once __DIR__ . '/../includes/functions.php';

// Sollicitant moet ingelogd zijn
if (!function_exists('portaal_ingelogd') || !portaal_ingelogd()) {
    redirect(APP_URL . '/portaal/');
}

$email = $_SESSION['portaal_email'] ?? '';
// Haal seizoen op met graceful fallback als de datum_werk-kolommen ontbreken (net als het BLK).
try {
    $stmt = db()->prepare("
        SELECT s.*, p.voornaam, p.achternaam, p.email, sz.naam AS seizoen_naam,
               sz.datum_werk_start, sz.datum_werk_eind, sz.id AS seizoen_id
        FROM sollicitaties s
        JOIN personen p ON p.id = s.persoon_id
        LEFT JOIN seizoenen sz ON sz.id = s.seizoen_id
        WHERE p.email = ?
        ORDER BY s.id DESC LIMIT 1
    ");
    $stmt->execute([$email]);
    $sol = $stmt->fetch();
} catch (\PDOException $e) {
    // Oude database zonder datum_werk-kolommen → zonder die kolommen ophalen
    $stmt = db()->prepare("
        SELECT s.*, p.voornaam, p.achternaam, p.email, sz.naam AS seizoen_naam,
               NULL AS datum_werk_start, NULL AS datum_werk_eind, sz.id AS seizoen_id
        FROM sollicitaties s
        JOIN personen p ON p.id = s.persoon_id
        LEFT JOIN seizoenen sz ON sz.id = s.seizoen_id
        WHERE p.email = ?
        ORDER BY s.id DESC LIMIT 1
    ");
    $stmt->execute([$email]);
    $sol = $stmt->fetch();
}
if (!$sol) { redirect(APP_URL . '/portaal/'); }

$sol_id = (int)$sol['id'];

// Weken van het seizoen bepalen uit de werk-datums — exact dezelfde logica als het BLK-formulier.
$alle_weken = [];
$jaar = (int)date('Y');
$week_start = $sol['datum_werk_start'] ?? null;
$week_eind  = $sol['datum_werk_eind']  ?? null;
if (!$week_start || !$week_eind) {
    // Fallback: week 22 t/m 35 (eind mei – begin sept)
    $week_start = (new DateTime())->setISODate($jaar, 22, 1)->format('Y-m-d');
    $week_eind  = (new DateTime())->setISODate($jaar, 35, 7)->format('Y-m-d');
}
$dag  = new DateTime($week_start);
$eind = new DateTime($week_eind);
while ($dag->format('N') != 1) $dag->modify('+1 day'); // naar maandag
$mnd_nl = [1=>'jan',2=>'feb',3=>'mrt',4=>'apr',5=>'mei',6=>'jun',7=>'jul',8=>'aug',9=>'sep',10=>'okt',11=>'nov',12=>'dec'];
while ($dag <= $eind) {
    $wk = (int)$dag->format('W');
    $alle_weken[] = [
        'week'    => $wk,
        'maand'   => (int)$dag->format('n'),
        'periode' => (int)$dag->format('j') . ' ' . $mnd_nl[(int)$dag->format('n')] . '–' . (int)(clone $dag)->modify('+6 days')->format('j') . ' ' . $mnd_nl[(int)(clone $dag)->modify('+6 days')->format('n')],
    ];
    $dag->modify('+7 days');
}
if (empty($alle_weken)) { // veiligheidsnet
    for ($w = 22; $w <= 35; $w++) $alle_weken[] = ['week' => $w, 'maand' => 0, 'periode' => 'W' . $w];
}

$ew = $alle_weken[0]['week'];
$lw = end($alle_weken)['week'];
reset($alle_weken);

// ── POST-verwerking: beschikbaarheid opslaan via de gedeelde functie ──
$flash_ok = '';
// ── AI-beschikbaarheid: interpreteer ingesproken/getypte tekst → voorstel ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ai_besch_submit'])) {
    header('Content-Type: application/json');
    csrf_check();
    if (!function_exists('ai_besch_actief') || !ai_besch_actief()) {
        echo json_encode(['status' => 'uit']); exit;
    }
    // Rate-limit: max 8 verzoeken per 10 minuten per sessie
    $nu = time();
    $_SESSION['ai_besch_hits'] = array_filter($_SESSION['ai_besch_hits'] ?? [], fn($t) => $t > $nu - 600);
    if (count($_SESSION['ai_besch_hits']) >= 8) {
        echo json_encode(['status' => 'fout', 'bericht' => 'Even rustig aan — probeer het over een paar minuten weer.']); exit;
    }
    $_SESSION['ai_besch_hits'][] = $nu;

    $seizoen_ai = function_exists('actief_seizoen') ? actief_seizoen() : null;
    $weken_geldig = array_keys(function_exists('seizoen_weken_datums') ? seizoen_weken_datums($seizoen_ai) : []);
    $res = ai_besch_verwerk($_POST['tekst'] ?? '', $weken_geldig, $seizoen_ai);
    if (function_exists('ai_besch_log')) {
        ai_besch_log($sol_id, $_POST['tekst'] ?? '', $res);
    }
    echo json_encode($res);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['besch_submit'])) {
    csrf_check();
    try {
        $waarschuwingen = beschikbaarheid_opslaan_uit_post($sol_id, $_POST);
        if ($waarschuwingen) {
            $_SESSION['b2_vz_waarschuwing'] = implode(' ', $waarschuwingen);
        }
        // Losse dagen (concrete datums) — zelfde als in het BLK
        if (function_exists('beschikbaarheid_dagen_opslaan')) {
            $dd = $_POST['b2_dag_datum'] ?? []; $ds = $_POST['b2_dag_soort'] ?? []; $dr = $_POST['b2_dag_reden'] ?? [];
            $losse = [];
            foreach ($dd as $i => $datum) {
                if (trim($datum) === '') continue;
                $losse[] = ['datum' => trim($datum), 'soort' => $ds[$i] ?? 'niet_beschikbaar', 'reden' => trim($dr[$i] ?? '')];
            }
            beschikbaarheid_dagen_opslaan($sol_id, $losse);
        }
        if (function_exists('audit_log')) audit_log('beschikbaarheid_ingevuld', 'sollicitatie', $sol_id, ['via' => 'losse taak']);
        $_SESSION['flash_besch_ok'] = 'Je beschikbaarheid is opgeslagen. Bedankt!';
    } catch (\Exception $e) {
        error_log('beschikbaarheid losse pagina: ' . $e->getMessage());
        $_SESSION['flash_besch_fout'] = 'Er ging iets mis bij het opslaan. Probeer het later opnieuw.';
    }
    redirect(APP_URL . '/portaal/beschikbaarheid_invullen.php');
}

$flash_ok = $_SESSION['flash_besch_ok'] ?? '';
$flash_fout = $_SESSION['flash_besch_fout'] ?? '';
$vz_waarschuwing = $_SESSION['b2_vz_waarschuwing'] ?? '';
unset($_SESSION['flash_besch_ok'], $_SESSION['flash_besch_fout'], $_SESSION['b2_vz_waarschuwing']);

$portaal_titel = 'Beschikbaarheid doorgeven';
$portaal_terug = APP_URL . '/portaal/';
require __DIR__ . '/_kop.php';
?>

<h1 style="font:var(--text-title);margin-bottom:6px">Beschikbaarheid doorgeven</h1>
<p style="color:var(--ink-soft);font-size:14px;margin-bottom:18px">Welke weken kun je werken dit seizoen? Geef het hieronder aan — je kunt het later altijd aanpassen.</p>

<?php if ($flash_ok): ?>
  <div class="ds-melding ds-melding-ok" style="margin-bottom:16px"><?= icoon('vink', 18) ?> <?= h($flash_ok) ?></div>
<?php endif; ?>
<?php if ($flash_fout): ?>
  <div class="ds-melding ds-melding-error" style="margin-bottom:16px"><?= icoon('let-op', 18) ?> <?= h($flash_fout) ?></div>
<?php endif; ?>
<?php if ($vz_waarschuwing): ?>
  <div class="ds-melding ds-melding-warn" style="margin-bottom:16px"><?= icoon('waarschuwing', 18) ?> <?= h($vz_waarschuwing) ?></div>
<?php endif; ?>

<form method="POST" id="beschForm">
  <?= csrf_input() ?>
  <input type="hidden" name="besch_submit" value="1">

  <?php require __DIR__ . '/_beschikbaarheid_velden.php'; ?>

  <div style="margin-top:20px">
    <button type="submit" class="ds-btn ds-btn-primair ds-btn-vol" onclick="return b2Valideer()"><?= icoon('vink', 16) ?> Beschikbaarheid opslaan</button>
  </div>
</form>

<?php
  // Constanten die het gedeelde beschikbaarheid-script nodig heeft
  $seizoen_obj = ['id' => $sol['seizoen_id'] ?? null, 'eerste_week' => $ew, 'laatste_week' => $lw];
  $weken_datums_ai = function_exists('seizoen_weken_datums') ? seizoen_weken_datums($seizoen_obj) : [];
  $ai_datums = [];
  foreach (array_keys($weken_datums_ai) as $wk) {
      $ai_datums[] = [
          'wk'    => $wk,
          'start' => (($weken_datums_ai[$wk]['start'] ?? null) instanceof DateTimeInterface) ? $weken_datums_ai[$wk]['start']->format('Y-m-d') : '',
          'eind'  => (($weken_datums_ai[$wk]['eind']  ?? null) instanceof DateTimeInterface) ? $weken_datums_ai[$wk]['eind']->format('Y-m-d')  : '',
      ];
  }

?>
<script>
  const B2_WEEKLABEL = <?= json_encode($week_labels, JSON_UNESCAPED_UNICODE) ?>;
  const B2_EERSTE = <?= (int)$ew ?>;
  const B2_LAATSTE = <?= (int)$lw ?>;
  window.AI_WEEK_DATUMS = <?= json_encode($ai_datums) ?>;
</script>
<script src="<?= APP_URL ?>/assets/js/beschikbaarheid.js?v=<?= @filemtime(__DIR__.'/../assets/js/beschikbaarheid.js') ?>"></script>

<?php require __DIR__ . '/_voet.php'; ?>
