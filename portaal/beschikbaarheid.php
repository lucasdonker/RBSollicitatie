<?php
/**
 * Portaal — beschikbaarheid-overzicht voor de sollicitant.
 * Toont in mensentaal wat wij begrepen hebben, met verzoeken + status,
 * en knoppen om aan te passen of definitief te maken.
 */
require_once __DIR__ . '/../includes/functions.php';
$sol_id = portaal_vereis_login();
$db = db();

// Sollicitant + seizoen
$q = $db->prepare("
    SELECT s.id, s.fase, sz.naam AS seizoen_naam, sz.datum_werk_start, sz.datum_werk_eind, sz.datum_tw_start,
           sz.id AS seizoen_id, p.voornaam
    FROM sollicitaties s
    JOIN personen p ON p.id = s.persoon_id
    JOIN seizoenen sz ON sz.id = s.seizoen_id
    WHERE s.id = ?
");
$q->execute([$sol_id]);
$mij = $q->fetch();
if (!$mij) redirect(APP_URL . '/portaal/');

$seizoen = ['datum_werk_start'=>$mij['datum_werk_start'], 'datum_werk_eind'=>$mij['datum_werk_eind'], 'datum_tw_start'=>$mij['datum_tw_start'], 'id'=>$mij['seizoen_id']];

// Actie: definitief maken → terug naar Start/Taken (waar de sollicitant vandaan kwam) met melding
$flash_fout = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['actie'] ?? '') === 'definitief') {
    csrf_check();
    if (function_exists('beschikbaarheid2_definitief_maken') && beschikbaarheid2_definitief_maken($sol_id)) {
        if (function_exists('audit_log')) { try { audit_log('beschikbaarheid_definitief', 'sollicitatie', $sol_id, ['via' => 'portaal']); } catch (\Throwable $e) {} }
        flash('succes', 'Je beschikbaarheid staat nu definitief. Bedankt!');
        redirect(portaal_terug_url());
    }
    $flash_fout = 'Er staat nog geen beschikbaarheid om definitief te maken. Vul die eerst in.';
}
$terug_param = portaal_terug_param();

$b2 = function_exists('beschikbaarheid2_van') ? beschikbaarheid2_van($sol_id) : ['profiel'=>null,'blokken'=>[],'afwezig'=>[],'verzoeken'=>[],'correcties'=>[]];
$b2p = $b2['profiel'];
$eff = function_exists('beschikbaarheid2_effectief') ? beschikbaarheid2_effectief($sol_id, $seizoen) : [];
$wkd = function_exists('seizoen_weken_datums') ? seizoen_weken_datums($seizoen) : [];
$wk_label = function($wn) use ($wkd) {
    $wn = (int)$wn;
    return isset($wkd[$wn]) ? 'week ' . $wn . ' (' . datum_kort_nl($wkd[$wn]['start']) . ')' : 'week ' . $wn;
};
$heeft_besch = $b2p && $b2['blokken'];
// De vastgestelde afspraak (loonplanning definitief gemaakt). Vóór dat moment bestaat hij niet
// en ziet de sollicitant niets van de planning.
$afspraak = (function_exists('module_aan') && module_aan('loonplanning') && function_exists('inzet_afspraak_van'))
    ? inzet_afspraak_van($sol_id) : null;
$functie_namen = function_exists('functies_ophalen') ? array_column(functies_ophalen(), 'naam', 'sleutel') : [];
$voorlopig = $heeft_besch && !empty($b2p['voorlopig']);

// Deadline
$deadline = function_exists('beschikbaarheid2_deadline') ? beschikbaarheid2_deadline($seizoen) : null;
$dgn_tot = $deadline ? (int)floor(($deadline->getTimestamp() - strtotime('today')) / 86400) : null;

$logo = function_exists('site_logo_url') ? (site_logo_url() ?: '') : '';
$dagnamen = ['',' maandag','dinsdag','woensdag','donderdag','vrijdag','zaterdag','zondag'];
$portaal_titel = 'Mijn beschikbaarheid';
$portaal_nav = 'start';
$portaal_terug = portaal_terug_url();
require __DIR__ . '/_kop.php';
?>
<div class="pt-stapel">
  <div class="pt-begroet">
    <h1>Mijn beschikbaarheid</h1>
    <p>Dit is wat wij van jou begrepen hebben voor seizoen <?= h($mij['seizoen_naam']) ?>. Zo gebruiken we het voor de planning.</p>
  </div>

  <?php if ($flash_fout): ?>
    <div class="ds-melding ds-melding-error" role="alert"><?= icoon('let-op', 18) ?> <div><?= h($flash_fout) ?></div></div>
  <?php endif; ?>

  <?php if (!$heeft_besch): ?>
    <div class="pt-leeg">
      <span class="pt-leeg-icoon navy"><?= icoon('lijst', 26) ?></span>
      <span class="pt-leeg-tekst">Je hebt nog geen beschikbaarheid doorgegeven.</span>
      <a href="<?= h(portaal_url_met_terug(APP_URL . '/portaal/beschikbaarheid_invullen.php', $terug_param)) ?>" class="ds-btn ds-btn-primair">Beschikbaarheid invullen</a>
    </div>
  <?php else: ?>

    <!-- Status -->
    <div class="pt-kaart">
      <?php if ($voorlopig): ?>
        <span class="ds-badge ds-badge-warn">Voorlopig</span>
        <p class="bk-status-tekst">
          Je beschikbaarheid staat nog op voorlopig. Klopt alles? Maak het dan definitief.
          <?php if ($deadline): ?>
            <br><span class="bk-zacht<?= ($dgn_tot !== null && $dgn_tot <= 7) ? ' bk-dringend' : '' ?>">
              Deadline: <strong><?= h(datum_nl($deadline->format('Y-m-d'))) ?></strong><?php if ($dgn_tot !== null): ?> — <?= $dgn_tot < 0 ? 'verstreken' : 'nog ' . $dgn_tot . ' ' . ($dgn_tot===1?'dag':'dagen') ?><?php endif; ?>.
            </span>
          <?php endif; ?>
          <br><span class="bk-klein">Werk je het niet bij, dan gebruiken we je huidige invoer automatisch vanaf 2 weken vóór het trainingsweekend.</span>
        </p>
      <?php else: ?>
        <span class="ds-badge ds-badge-ok"><?= icoon("vink", 13) ?> Definitief</span>
        <p class="bk-status-tekst bk-zacht">Je beschikbaarheid is definitief<?= !empty($b2p['definitief_op']) ? ' sinds ' . h(datum_nl($b2p['definitief_op'])) : '' ?>. Je kunt hem nog steeds aanpassen als er iets wijzigt.</p>
      <?php endif; ?>
    </div>

    <?php if ($afspraak): ?>
    <!-- Wat er is afgesproken (na het trainingsweekend vastgesteld) -->
    <div class="pt-kaart">
      <h2 class="pt-kaart-titel"><?= icoon("vink-cirkel", 18) ?> Wat we hebben afgesproken</h2>
      <div class="bk-rij">Je werkt als <strong><?= h($functie_namen[$afspraak['functie_sleutel']] ?? $afspraak['functie_sleutel']) ?></strong>.
        <span class="bk-klein">Vastgesteld op <?= h(datum_nl(substr((string)$afspraak['vastgesteld_op'], 0, 10))) ?>.</span></div>
      <?php foreach ($afspraak['weken'] as $wn => $aw): ?>
        <div class="bk-rij">
          <strong><?= h($wk_label($wn)) ?></strong>: <?= (int)$aw['dagen'] ?> <?= (int)$aw['dagen'] === 1 ? 'dag' : 'dagen' ?>
          <?php if ($aw['weekdagen']): ?><span class="bk-zacht"> (<?= h(implode(', ', array_map(fn($d) => trim($dagnamen[$d]), $aw['weekdagen']))) ?>)</span><?php endif; ?>
          <?php if (!$aw['betaald']): ?><span class="bk-zacht"> · onbetaald</span><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <div class="bk-rij bk-klein">Klopt dit niet met wat je hebt besproken? <a href="<?= APP_URL ?>/portaal/contact.php#vraagTekst">Stel een vraag via Contact</a>.
        Hieronder staat wat je zelf hebt opgegeven; dat blijft bewaard.</div>
    </div>
    <?php endif; ?>

    <!-- Samenvatting in mensentaal -->
    <div class="pt-kaart">
      <h2 class="pt-kaart-titel"><?= icoon("kalender", 18) ?> Wanneer je kunt werken</h2>
      <?php foreach ($b2['blokken'] as $blok): ?>
        <div class="bk-rij">
          <strong><?= h($wk_label($blok['van_week'])) ?> t/m <?= h($wk_label($blok['tot_week'])) ?></strong> —
          <?php if ($blok['regime'] === 'weekend'):
            $wd = $blok['weekend_dagen'] ?: [5,6,7];
            echo 'alleen ' . implode('/', array_map(fn($d) => trim($dagnamen[$d]), $wd));
          else: ?>
            hele weken (5 dagen)
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <div class="bk-rij bk-zacht"><?= count($eff) ?> <?= count($eff)===1?'week':'weken' ?> beschikbaar in totaal.</div>
    </div>

    <!-- Afwezigheid + voorkeuren -->
    <?php if ($b2['afwezig'] || !empty($b2p['vaste_dagen_uit'])): ?>
    <div class="pt-kaart">
      <h2 class="pt-kaart-titel"><?= icoon('info', 18) ?> Verder doorgegeven</h2>
      <?php if ($b2['afwezig']): ?>
        <div class="bk-rij">
          <strong>Afwezig:</strong>
          <?= implode(', ', array_map(fn($a) => h(datum_nl($a['van_datum'])) . ' t/m ' . h(datum_nl($a['tot_datum'])) . ($a['label'] ? ' (' . h($a['label']) . ')' : ''), $b2['afwezig'])) ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($b2p['vaste_dagen_uit'])): ?>
        <div class="bk-rij">
          <strong>Voorkeur vrij:</strong>
          <?= implode(', ', array_map(fn($d) => trim($dagnamen[$d]), $b2p['vaste_dagen_uit'])) ?>
          <span class="bk-zacht">(voorkeur — de planner beslist)</span>
          <?php if (!empty($b2p['vaste_toelichting'])): ?><br><span class="bk-klein"><?= h($b2p['vaste_toelichting']) ?></span><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Verzoeken minder dagen + status (status in woorden én kleur, nooit alleen kleur) -->
    <?php if ($b2['verzoeken']): ?>
    <div class="pt-kaart">
      <h2 class="pt-kaart-titel"><?= icoon('lijst', 18) ?> Verzoeken (minder dagen)</h2>
      <?php foreach ($b2['verzoeken'] as $vz):
        $st = $vz['status'];
        $st_klasse = $st === 'goedgekeurd' ? 'ok' : ($st === 'afgewezen' ? 'error' : 'open');
        $st_tekst = $st === 'goedgekeurd' ? 'goedgekeurd' : ($st === 'afgewezen' ? 'afgewezen' : 'in behandeling');
      ?>
        <div class="bk-rij">
          <strong><?= h($wk_label($vz['van_week'])) ?> t/m <?= h($wk_label($vz['tot_week'])) ?></strong>: <?= (int)$vz['dagen_aantal'] ?> dagen/week
          <span class="bk-status <?= $st_klasse ?>"> · <?= $st_tekst ?></span>
          <?php if (!empty($vz['dagen_pool'])): ?>
            <br><span class="bk-klein">Beschikbare dagen die week: <?= implode(', ', array_map(fn($d) => trim($dagnamen[$d]), $vz['dagen_pool'])) ?></span>
          <?php endif; ?>
          <?php if (!empty($vz['toelichting'])): ?>
            <br><span class="bk-klein"><?= h($vz['toelichting']) ?></span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Bestuurs-correcties (als die er zijn, transparant tonen) -->
    <?php if (!empty($b2['correcties'])): ?>
    <div class="pt-kaart">
      <h2 class="pt-kaart-titel"><?= icoon("potlood", 18) ?> Aangepast in overleg</h2>
      <?php foreach ($b2['correcties'] as $c): ?>
        <div class="bk-rij">
          <strong><?= h($wk_label($c['week'])) ?></strong>:
          <?= $c['soort'] === 'niet' ? 'deze week niet ingedeeld' : 'aangepaste dagen' ?>
          <?php if (!empty($c['reden'])): ?><span class="bk-zacht"> — <?= h($c['reden']) ?></span><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Acties -->
    <div class="bk-acties">
      <a href="<?= h(portaal_url_met_terug(APP_URL . '/portaal/beschikbaarheid_invullen.php', $terug_param)) ?>" class="ds-btn ds-btn-secundair"><?= icoon("potlood", 16) ?> Aanpassen</a>
      <?php if ($voorlopig): ?>
      <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="definitief">
        <input type="hidden" name="terug" value="<?= h($terug_param) ?>">
        <button type="submit" class="ds-btn ds-btn-primair ds-btn-vol"><?= icoon('vink', 16) ?> Definitief maken</button>
      </form>
      <?php endif; ?>
    </div>

  <?php endif; ?>
</div>

<?php require __DIR__ . '/_voet.php'; ?>
