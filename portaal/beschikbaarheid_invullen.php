<?php
/**
 * Losse beschikbaarheid-invulpagina (het ontwerp maakt beschikbaarheid een eigen taak).
 * Hergebruikt het gedeelde velden-include en de gedeelde opslagfunctie, zodat er geen
 * duplicatie is met het BLK-formulier.
 */
require_once __DIR__ . '/../includes/functions.php';

// De sollicitatie waarmee iemand is ingelogd, niet "de nieuwste op dit e-mailadres": wie vaker
// solliciteerde, zou anders de beschikbaarheid van een andere sollicitatie overschrijven.
$sol = portaal_sollicitant_laden(portaal_vereis_login());
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
$mnd_nl = maanden_kort_nl();
while ($dag <= $eind) {
    $wk = (int)$dag->format('W');
    $alle_weken[] = [
        'week'    => $wk,
        'maand'   => (int)$dag->format('n'),
        'periode' => (int)$dag->format('j') . ' ' . $mnd_nl[(int)$dag->format('n')] . '–' . (int)(clone $dag)->modify('+6 days')->format('j') . ' ' . $mnd_nl[(int)(clone $dag)->modify('+6 days')->format('n')],
        'start'   => $dag->format('Y-m-d'),
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
        if (empty($waarschuwingen)) {
            // Klaar: terug naar Start of Taken (waar de sollicitant vandaan kwam) met de melding
            // daar — inclusief wat er hierna gebeurt, zodat de taak niet in stilte eindigt.
            $hierna = '';
            if (function_exists('portaal_hierna')) {
                $_sol_na  = portaal_sollicitant_laden($sol_id);
                $_open_na = ($_sol_na && function_exists('portaal_taken'))
                    ? count(portaal_taken_open(portaal_taken($_sol_na, blk_van($sol_id)))) : 0;
                if ($_sol_na) $hierna = ' ' . portaal_hierna($_sol_na, $_open_na);
            }
            flash('succes', 'Je beschikbaarheid is opgeslagen. Bedankt!' . $hierna);
            redirect(portaal_terug_url());
        }
        // Opgeslagen, met iets om naar te kijken: één melding (flash() bewaart er één).
        flash('waarschuwing', 'Je beschikbaarheid is opgeslagen. Let op: ' . implode(' ', $waarschuwingen));
    } catch (\Exception $e) {
        error_log('beschikbaarheid losse pagina: ' . $e->getMessage());
        flash('fout', 'Er ging iets mis bij het opslaan. Probeer het later opnieuw.');
    }
    // Waarschuwing of fout: terug naar het formulier (herkomst bewaren voor de volgende poging)
    redirect(portaal_url_met_terug(APP_URL . '/portaal/beschikbaarheid_invullen.php', portaal_terug_param()));
}

$portaal_titel = 'Beschikbaarheid doorgeven';
$portaal_terug = portaal_terug_url();
require __DIR__ . '/_kop.php';
?>



<?php
  $ai_aan = function_exists('ai_besch_actief') && ai_besch_actief();
  // Opgeslagen beschikbaarheid (de partial hergebruikt $b2). Startpunt: wizard alleen bij een lege
  // start én AI aan; wie al beschikbaarheid heeft, komt direct in het ingevulde formulier.
  $b2 = function_exists('beschikbaarheid2_van') ? beschikbaarheid2_van($sol_id) : ['profiel'=>null,'blokken'=>[],'afwezig'=>[],'verzoeken'=>[]];
  // Tussentijds bewaard concept (autosave): daar gaat de sollicitant verder waar hij was.
  $concept = function_exists('beschikbaarheid2_concept_van') ? beschikbaarheid2_concept_van($sol_id) : null;
  if ($concept) {
      $besch_start = ($ai_aan && ($concept['stap'] ?? '') === 'wizard' && empty($concept['periodes'])) ? 'wizard' : 'formulier';
  } else {
      $besch_start = ($ai_aan && empty($b2['blokken'])) ? 'wizard' : 'formulier';
  }
  $concept_tekst = 'Je invoer wordt automatisch bewaard';
  $concept_ok    = false;
  if ($concept && !empty($concept['op'])) {
      $ct = strtotime($concept['op']);
      $concept_tekst = 'Concept opgeslagen ' . (date('Y-m-d', $ct) === date('Y-m-d') ? 'om ' . date('H:i', $ct) : 'op ' . datum_nl(date('Y-m-d', $ct)));
      $concept_ok = true;
  }
?>

<h1 style="font:var(--text-title);margin-bottom:6px">Beschikbaarheid doorgeven</h1>
<p style="color:var(--ink-soft);font-size:14px;margin-bottom:10px">Welke weken kun je werken dit seizoen? Geef het hieronder aan — je kunt het later altijd aanpassen.</p>
<span class="pt-autosave <?= $concept_ok ? 'ok' : '' ?>" id="beschAutosave" aria-live="polite"><?= icoon('vink', 13) ?><span id="beschAutosaveTekst"><?= h($concept_tekst) ?></span></span>

<?php if ($ai_aan): ?>
<!-- ══ AI-WIZARD (stap 1 t/m 4) — alleen als AI-hulp aan staat ══ -->
<div id="wizStap1" class="wiz-stap" <?= $besch_start === 'wizard' ? '' : 'style="display:none"' ?>>
  <div class="wiz1-navy">
    <div class="wiz1-badge"><?= icoon('bliksem', 14) ?> <span>Slim invullen</span></div>
    <span class="wiz1-titel">Typ gewoon wanneer je kunt — wij maken er een planning van.</span>
    <span class="wiz1-sub">Je ziet daarna precies wat we ervan begrepen hebben en kunt alles nog aanpassen.</span>
  </div>
  <div class="wiz1-veldkaart">
    <label class="wiz1-veld-lbl">Wanneer kun je werken deze zomer?</label>
    <textarea id="beschAiTekst" rows="6" placeholder="Bijvoorbeeld: Ik kan vanaf eind juni tot eind augustus hele weken, behalve dat ik op woensdag naar school moet. Van 23 augustus tot 1 september ben ik op vakantie in Frankrijk en 18 juli heb ik een bruiloft. In week 31 heb ik tentamens, dan liever max 3 dagen."></textarea>
    <div class="wiz1-voorbeelden">
      <span class="wiz1-vb-lbl">Voorbeelden om te beginnen</span>
      <div class="wiz1-chips">
        <button type="button" class="wiz1-chip" onclick="beschAiChip(this)">Alleen weekenden</button>
        <button type="button" class="wiz1-chip" onclick="beschAiChip(this)">Hele juli en augustus</button>
        <button type="button" class="wiz1-chip" onclick="beschAiChip(this)">Niet in de bouwvak</button>
      </div>
    </div>
    <button type="button" class="wiz1-zelf" onclick="wizNaarFormulier()">Liever zelf invullen met vinkjes</button>
  </div>
  <div class="wiz1-voet">
    <button type="button" id="beschAiKnop" class="wiz1-knop" onclick="beschAiVerwerk()">Maak er een planning van <?= icoon('pijl-rechts', 18) ?></button>
  </div>
  <div id="beschAiStatus" class="besch-hint" style="margin-top:8px;text-align:center"></div>
</div>
<div id="wizStap2" class="wiz-stap" style="display:none">
  <div class="wiz-kaart">
    <div class="wiz-laad-kop"><span class="wiz-spinner"></span> We lezen je antwoord…</div>
    <ul class="wiz-checklist">
      <li id="wizChk1"><span class="wiz-chk-bol"></span> Werkperiodes gevonden</li>
      <li id="wizChk2"><span class="wiz-chk-bol"></span> Vakantie en losse dagen</li>
      <li id="wizChk3"><span class="wiz-chk-bol"></span> Vaste vrije dag</li>
    </ul>
    <p class="besch-hint" style="margin:12px 0 0">Dit duurt een paar seconden. Je hoeft niets te doen.</p>
  </div>
</div>
<div id="wizStap3" class="wiz-stap" style="display:none">
  <div class="wiz3-navy">
    <span class="wiz3-lbl">Zo hebben we het begrepen</span>
    <span id="wizSamenvatting" class="wiz3-tekst"></span>
  </div>
  <div id="wizOnderdelen" class="wiz3-onderdelen"></div>
  <div id="wizNavraag"></div>
  <div class="wiz3-aanvullen">
    <label class="wiz3-aanvullen-lbl">Nog iets aanvullen?</label>
    <div class="wiz3-aanvullen-rij">
      <input type="text" id="beschAiAanvul" placeholder='Bijv. "week 33 kan ik ook niet"'>
      <button type="button" class="wiz3-aanvul-knop" onclick="beschAiAanvullen()" aria-label="Verstuur"><?= icoon('pijl-rechts', 18) ?></button>
    </div>
  </div>
  <button type="button" class="wiz1-knop" style="margin-top:14px" onclick="wizOvernemen()">Klopt, dit overnemen</button>
  <button type="button" class="wiz3-zelf-knop" style="margin-top:10px" onclick="wizNaarFormulier()">Zelf aanpassen in het formulier</button>
</div>
<?php endif; ?>

<form method="POST" id="beschForm" class="wiz-stap" <?= $besch_start === 'formulier' ? '' : 'style="display:none"' ?>>
  <?= csrf_input() ?>
  <input type="hidden" name="besch_submit" value="1">
  <input type="hidden" name="terug" value="<?= h(portaal_terug_param()) ?>">
  <?php require __DIR__ . '/_beschikbaarheid_velden.php'; ?>
  <div style="margin-top:20px">
    <button type="submit" class="ds-btn ds-btn-primair ds-btn-vol" onclick="return beschValideer()"><?= icoon('vink', 16) ?> Beschikbaarheid opslaan</button>
  </div>
</form>
<?php if ($ai_aan): ?>
  <button type="button" id="beschOpnieuwSlim" class="wiz1-zelf" style="<?= $besch_start === 'formulier' ? '' : 'display:none;' ?>width:100%;margin-top:12px" onclick="wizNaarStart()"><?= icoon('bliksem', 14) ?> Opnieuw slim invullen met tekst</button>
<?php endif; ?>

<?php
  // Weeklabels voor de dropdowns ("W27 · 29 jun") en de datumrange per week voor de subregel
  // van de periode-kaart ("29 juni t/m 9 augustus · 6 weken"), direct uit $alle_weken.
  $week_labels = []; $week_datums = [];
  $mnd_lang = maanden_nl();
  foreach ($alle_weken as $w) {
      $delen = explode('–', (string)($w['periode'] ?? ''));
      $week_labels[$w['week']] = 'W' . $w['week'] . ' · ' . trim($delen[0]);
      $van_d = !empty($w['start']) ? new DateTime($w['start']) : (new DateTime())->setISODate($jaar, $w['week'], 1);
      $tot_d = (clone $van_d)->modify('+6 days');
      $week_datums[$w['week']] = [
          'van' => (int)$van_d->format('j') . ' ' . $mnd_lang[(int)$van_d->format('n')],
          'tot' => (int)$tot_d->format('j') . ' ' . $mnd_lang[(int)$tot_d->format('n')],
      ];
  }
?>
<script>
  window.BESCH_CFG = {
    weken:   <?= json_encode($week_labels, JSON_UNESCAPED_UNICODE) ?>,
    datums:  <?= json_encode($week_datums, JSON_UNESCAPED_UNICODE) ?>,
    eerste:  <?= (int)$ew ?>,
    laatste: <?= (int)$lw ?>,
    data:    <?= json_encode($besch_data ?? null, JSON_UNESCAPED_UNICODE) ?>,
    concept: <?= json_encode($concept, JSON_UNESCAPED_UNICODE) ?>,
    start:   <?= json_encode($besch_start) ?>,
    apiConcept: <?= json_encode(APP_URL . '/portaal/api_besch_concept.php') ?>
  };
</script>
<script src="<?= APP_URL ?>/assets/js/portaal_concept.js?v=<?= @filemtime(__DIR__.'/../assets/js/portaal_concept.js') ?>"></script>
<script src="<?= APP_URL ?>/assets/js/beschikbaarheid.js?v=<?= @filemtime(__DIR__.'/../assets/js/beschikbaarheid.js') ?>"></script>

<?php require __DIR__ . '/_voet.php'; ?>
