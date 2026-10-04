<?php
/**
 * Het gesprek met het bestuur (TW-stap Gesprekken, ?k=<sol_id>) — opgebouwd zoals het gesprek loopt
 * (ontwerp goedgekeurd 2 okt 2026, 2.156.0):
 *   1. de kop: wie, en wat je meteen moet weten (eerder weg, opmerking bij "ik kom", bijzonderheid);
 *   2. Gesprekspunten (bh_tw_gesprekspunten()): wat je moet aankaarten, met een sprong naar het tabblad;
 *   3. tabbladen: Opgave en inzet (de opgave naast de afspraak-editor), Hoe ging het (weekend,
 *      strandwachten, selectiedag), Open in het portaal;
 *   4. rechts het besluit, dat blijft staan; op een telefoon onderaan.
 *
 * Openen = claimen ("bezig door jou", 30 min). Aannemen/afwijzen gaat direct door via
 * tw_gesprek_afronden(); het bericht aan de sollicitant gaat als {{gesprek_bericht}} mee.
 * Heeft een ander het open, dan kan wie mag beslissen het overnemen (admin/tw/_overnemen.php).
 * Wie niet kwam (tw_niet_gekomen()) krijgt hier ook een besluit, zonder gesprek en met een
 * eigen standaardtekst bij afwijzen.
 * Gedrag: assets/js/tabbladen.js (tabs), assets/js/gesprek.js (besluit), assets/js/afspraak.js (inzet).
 * Onderdeel van admin/trainingsweekend.php.
 */
$_id = (int)($_GET['k'] ?? 0);
$_k  = null;
foreach ($kandidaten as $_r) if ((int)$_r['sol_id'] === $_id) { $_k = $_r; break; }
$gd_dossier  = $_k ? bh_dossier($_id) : null;

if (!$_k || !$gd_dossier): ?>
  <div class="ds-kaart ds-kaart-p">
    <?php $leeg_titel = 'Niet gevonden'; $leeg_tekst = 'Deze persoon staat niet (meer) op de lijst van het trainingsweekend.'; $leeg_icoon = 'mensen'; require __DIR__ . '/../_leeg.php'; ?>
    <a class="ds-btn ds-btn-secundair" href="<?= h(tw_url('gesprekken')) ?>">Terug naar de gesprekken</a>
  </div>
<?php return; endif;

$_klaar = !empty($_k['doorgevoerd_op']);
$_claim = ['ok' => true, 'door' => null];
if (!$_klaar) {
    tw_rij_verzeker($sid, $_id);
    // Eén gesprek tegelijk: wie je hiervoor open had, komt vrij voor je collega's.
    evenement_claim_los_overige('tw_deelname', 'gesprek', $sid, $ik_id, $_id);
    $_claim = evenement_claim('tw_deelname', 'gesprek', $sid, $_id, $ik_id);
}
$_mag_form = !$_klaar && $_claim['ok'];
$_taken    = bh_dossier_taken($gd_dossier);
$_open     = array_values(array_filter($_taken['rijen'], fn($t) => !$t['done']));
$_sol      = $gd_dossier['sol'];
$_b        = (string)($_k['besluit'] ?? '');
$_niet     = tw_niet_gekomen($_k);   // was er niet: geen gesprek, wel een besluit
$_voor     = (string)($_k['voornaam'] ?? '') ?: (string)$_k['naam'];

// Wat er besproken moet worden (includes/beheer/gesprek.php, includes/beheer/beschikbaarheid.php).
$_opg    = bh_opgave_overzicht($_id, $seizoen, $gd_dossier['blk'] ?? null);
$_twobs  = tw_observaties_van($sid, $_id);
$_lgf    = lg_feedback_van($_id);
$_sd_aan = module_aan('selectiedag') && ($gd_dossier['scores'] || $gd_dossier['observaties'] || $gd_dossier['besluit']);
$_sd_nb  = $_sd_aan && $gd_dossier['scores']
    ? bh_nb_analyse(['aanwezig' => 1], $gd_dossier['scores'], $gd_dossier['observaties'] ?? [], [], selectie_criteria(), bh_advies_drempel()) : null;
$_punten = bh_tw_gesprekspunten($_opg, [
    'tw_obs' => $_twobs, 'lgf' => $_lgf, 'sd_nb' => $_sd_nb, 'sd_besluit' => (string)($gd_dossier['besluit']['besluit'] ?? ''),
    'evaluatie' => (string)($_k['evaluatie'] ?? ''), 'evaluatie_bericht' => (string)($_k['evaluatie_bericht'] ?? ''),
    'open' => array_column($_open, 'titel'), 'niet_gekomen' => $_niet,
]);
$_tw_tel = array_count_values(array_map(fn($o) => (string)($o['soort'] ?? 'neutraal'), $_twobs));
?>
<p><a class="bh-terug" href="<?= h(tw_url('gesprekken')) ?>"><?= icoon('terug', 16) ?> Alle gesprekken</a></p>

<?php if (!$_claim['ok']): ?>
  <div class="ds-melding ds-melding-warn bh-twbezet" role="status">
    <?= icoon('waarschuwing', 16) ?>
    <span><?= h(evenement_claim_naam($_claim['door'])) ?> heeft dit gesprek open<?= !empty($_claim['sinds']) ? ' sinds ' . h(date('H:i', strtotime((string)$_claim['sinds']))) : '' ?>.
      Je kunt meelezen; het besluit vastleggen kan pas als dat gesprek klaar is, na 30 minuten, of als je het overneemt.</span>
    <?php if ($mag_beslissen) { $ov_sol_id = $_id; $ov_naam = (string)$_k['naam']; $ov_door = (int)$_claim['door'];
                               $ov_sinds = $_claim['sinds'] ?? null; require __DIR__ . '/_overnemen.php'; } ?>
  </div>
<?php endif; ?>

<?php if ($_niet): ?>
  <div class="ds-melding ds-melding-info" role="note">
    <?= icoon('info', 16) ?>
    <span><strong>Niet gekomen</strong><?= ($_k['afgemeld_reden'] ?? '') !== '' ? ': ' . h((string)$_k['afgemeld_reden']) : '' ?>.
      Er is dit weekend geen gesprek geweest. Leg hier toch het besluit vast; bij afwijzen gaat er een tekst mee
      die past bij iemand die er niet was.</span>
  </div>
<?php endif; ?>

<div class="bh-twgesprek">
  <div class="bh-twgesprek-info">

    <section class="ds-kaart bh-twkop" aria-label="Wie">
      <?= bh_avatar($_k, 56, ['vergroot' => true]) ?>
      <div class="bh-twkop-wie">
        <h2 class="bh-twkop-naam"><?= h((string)$_k['naam']) ?></h2>
        <?= bh_meta([$gd_dossier['leeftijd'] !== null ? $gd_dossier['leeftijd'] . ' jaar' : '',
                     $gd_dossier['leeftijd_seizoen'] !== null && $gd_dossier['leeftijd_seizoen'] !== $gd_dossier['leeftijd'] ? $gd_dossier['leeftijd_seizoen'] . ' bij de start' : '',
                     // Strandwacht = Authentik-koppeling (is_lifeguard), zoals overal; "terugkerend" is alleen eerder gesolliciteerd.
                     !empty($_k['is_lifeguard']) ? 'terugkerende strandwacht' : (!empty($_sol['terugkerend']) ? 'eerder gesolliciteerd' : 'nieuw'),
                     $mag_dossier && !empty($_sol['telefoon']) ? telefoon_opmaak((string)$_sol['telefoon']) : '']) ?>
      </div>
      <?php if ($mag_dossier): ?><a class="ds-btn ds-btn-secundair ds-btn-sm" href="<?= APP_URL ?>/admin/sollicitant.php?id=<?= $_id ?>" target="_blank" rel="noopener">Dossier</a><?php endif; ?>
      <?php
        $_chips = [];
        if (!empty($_k['eerder_weg']))        $_chips[] = ['Moet weg: ' . tw_eerder_weg_tekst((string)$_k['eerder_weg']), 'oranje'];
        if (!empty($_k['bevestiging_reden'])) $_chips[] = ['Bij "ik kom": ' . bh_gesprek_fragment((string)$_k['bevestiging_reden'], 80), 'grijs'];
        if (!empty($_k['bijzonderheden']))    $_chips[] = [$mag_blk ? 'Bijzonderheid: ' . bh_gesprek_fragment((string)$_k['bijzonderheden'], 80) : 'Bijzonderheid bekend (zie formulier)', 'oranje'];
        if ($gd_dossier['minderjarig'])       $_chips[] = ['Minderjarig: een ouder tekent mee', 'grijs'];
        if (!empty($gd_dossier['leeftijd_melding'])) $_chips[] = [(string)$gd_dossier['leeftijd_melding'], 'oranje'];
      ?>
      <?php if ($_chips): ?>
        <div class="bh-twkop-chips"><?php foreach ($_chips as $_c) echo bh_chip($_c[0], $_c[1]); ?></div>
      <?php endif; ?>
      <?php if ($mag_blk && !empty($_k['bijzonderheden']) && mb_strlen((string)$_k['bijzonderheden']) > 80): ?>
        <p class="bh-twkop-lang"><strong>Bijzonderheid:</strong> <?= nl2br(h((string)$_k['bijzonderheden'])) ?></p>
      <?php endif; ?>
    </section>

    <?php if ($_punten): ?>
    <section class="ds-kaart ds-kaart-p bh-twpunten" aria-labelledby="punten-kop">
      <div class="bh-kaartkop"><h2 id="punten-kop">Gesprekspunten</h2><span class="bh-meta">uit de opgave, de beoordelingen en het portaal</span></div>
      <ul>
        <?php foreach ($_punten as $_p): ?>
          <li class="bh-twpunt <?= h($_p['soort']) ?>">
            <span class="bh-twpunt-stip" aria-hidden="true"></span>
            <span class="bh-twpunt-tekst"><strong><?= h($_p['titel']) ?></strong> <?= h($_p['tekst']) ?>
              <button type="button" class="bh-twpunt-naar" data-naar-tab="tab-<?= h($_p['tab']) ?>"><?= h(['opgave' => 'Opgave', 'hoe' => 'Hoe ging het', 'open' => 'Open'][$_p['tab']] ?? '') ?></button></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>

    <div class="bh-twtabs" data-tabbladen id="twgesprek-tabs">
      <div class="bh-tablijst" role="tablist" aria-label="Onderdelen van het gesprek">
        <button type="button" role="tab" id="tab-opgave" aria-controls="p-opgave" aria-selected="true">Opgave en inzet</button>
        <button type="button" role="tab" id="tab-hoe" aria-controls="p-hoe" aria-selected="false">Hoe ging het</button>
        <button type="button" role="tab" id="tab-open" aria-controls="p-open" aria-selected="false">Open in het portaal<?php if ($_open): ?> <span class="bh-tablijst-tel"><?= count($_open) ?></span><?php endif; ?></button>
      </div>

      <div role="tabpanel" id="p-opgave" aria-labelledby="tab-opgave" class="bh-tabpaneel bh-twopgave-inzet">
        <?php $opo = $_opg; $opo_naam = $_voor; require __DIR__ . '/../_opgave_overzicht.php'; ?>
        <section class="ds-kaart ds-kaart-p">
          <?php
            $af_sol_id = $_id; $af_seizoen = $seizoen; $af_alleen_lezen = !$_claim['ok']; $af_week_notities = $_opg['week'];
            if (module_aan('loonplanning') && mag('loonplanning', 'lezen')) {
                require __DIR__ . '/../_afspraak_editor.php';
            } else {
                // Zonder loonplanning: alleen de opgave, per week.
                $_eff = beschikbaarheid2_effectief($_id, $seizoen);
                echo '<h3>Per week</h3>';
                if (!$_eff) echo '<p class="bh-meta">Nog niets opgegeven.</p>';
                else {
                    echo '<ul class="bh-twopgave">';
                    foreach ($_eff as $_wk => $_w) echo '<li><span>W' . (int)$_wk . '</span> <strong>' . (int)$_w['dagen_max'] . ' d</strong></li>';
                    echo '</ul>';
                }
            }
          ?>
        </section>
      </div>

      <div role="tabpanel" id="p-hoe" aria-labelledby="tab-hoe" class="bh-tabpaneel bh-twhoe">
        <div class="bh-twhoe-kolom">
          <section class="ds-kaart ds-kaart-p">
            <div class="bh-kaartkop"><h2>Dit weekend</h2>
              <span class="bh-meta"><?= h(implode(' · ', array_filter([count($_twobs) . ' ' . (count($_twobs) === 1 ? 'observatie' : 'observaties'),
                  !empty($_tw_tel['positief']) ? $_tw_tel['positief'] . ' positief' : '', !empty($_tw_tel['aandacht']) ? $_tw_tel['aandacht'] . ' aandacht' : '']))) ?></span></div>
            <?php
              if ($_twobs) { $obs_lijst = $_twobs; $obs_eigen = null; require __DIR__ . '/../_observaties.php'; }
              else echo '<p class="bh-meta">' . ($_niet ? 'Niet op het weekend geweest, dus niets gezien.' : 'Niemand heeft iets opgeschreven.') . '</p>';
            ?>
          </section>
          <?php // Feedback van strandwachten: lezen en (met de taak Gesprekken) toevoegen; ook de stap Feedback en het dossier kunnen dat.
            $lgf_sol_id = $_id; $lgf_seizoen_id = $sid; $lgf_moment = 'tw'; $lgf_mag_bewerken = $mag_beslissen;
            $lgf_terug = tw_url('gesprekken', $_id); $lgf_kop = 'h2'; $lgf_rijen = $_lgf;
            require __DIR__ . '/../_lg_feedback.php'; ?>
        </div>
        <?php if ($_sd_aan): ?>
        <section class="ds-kaart ds-kaart-p bh-twgesprek-sd" aria-label="Selectiedag">
          <?php $sds_dossier = $gd_dossier; $sds_open = true; require __DIR__ . '/../_sd_samenvatting.php'; ?>
        </section>
        <?php endif; ?>
      </div>

      <div role="tabpanel" id="p-open" aria-labelledby="tab-open" class="bh-tabpaneel">
        <section class="ds-kaart ds-kaart-p">
          <div class="bh-kaartkop"><h2>Nog open in het portaal</h2><span class="bh-meta"><?= count($_open) ?></span></div>
          <?php if ($_open): ?>
            <ul class="bh-twtaken">
              <?php foreach ($_open as $_t): ?><li><?= h($_t['titel']) ?><?php if ($_t['sub'] !== ''): ?> <span class="bh-meta"><?= h($_t['sub']) ?></span><?php endif; ?></li><?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="bh-meta"><?= h($_voor) ?> heeft alles gedaan wat er nu in het portaal staat.</p>
          <?php endif; ?>
        </section>
      </div>
    </div>
  </div>

  <aside class="bh-twgesprek-besluit">
    <section class="ds-kaart ds-kaart-p">
      <h2 class="bh-twbesluit-kop">Besluit</h2>
      <?php if ($_klaar): ?>
        <?php $_bd = tw_besluit_labels()[$_b] ?? ['Doorgevoerd', 'grijs']; ?>
        <p><?= bh_badge($_bd[0], $_bd[1]) ?>
          <span class="bh-meta">doorgevoerd op <?= h(datum_nl(substr((string)$_k['doorgevoerd_op'], 0, 10))) ?><?= !empty($_k['besluit_door']) ? ' door ' . h((string)$_k['besluit_door']) : '' ?></span></p>
        <?php if (!empty($_k['besluit_notitie'])): ?><p><strong>Intern:</strong> <?= h((string)$_k['besluit_notitie']) ?></p><?php endif; ?>
        <p><strong>Aan de sollicitant:</strong> <?= nl2br(h((string)($_k['gesprek_bericht'] ?: tw_standaard_bericht($_b, $_niet)))) ?></p>
      <?php else: ?>
      <?php if (!empty($_k['evaluatie'])): ?>
        <p class="bh-dossier-tekst">
          Eerste indruk: <?= bh_badge(tw_evaluatie_standen()[$_k['evaluatie']] ?? (string)$_k['evaluatie'], ['aannemen' => 'groen', 'twijfel' => 'oranje', 'afwijzen' => 'rood'][$_k['evaluatie']] ?? 'grijs') ?>
          <?php if (!empty($_k['evaluatie_bericht'])): ?><span class="bh-meta"><?= h((string)$_k['evaluatie_bericht']) ?></span><?php endif; ?>
        </p>
      <?php endif; ?>
      <?php
        // Per besluit zeggen wat er echt gebeurt: met naam, of er mail uitgaat (instellingen) en of het vastligt.
        $_mail  = fn(array $t) => bh_melding_gaat_uit($t)
            ? $_voor . ' krijgt nu een mail met jullie bericht.' : 'Er gaat geen mail uit (staat uit bij Instellingen).';
        $_afw_t = [$_niet && function_exists('melding_definitie') && melding_definitie('tw_niet_gekomen') ? 'tw_niet_gekomen' : 'tw_afgewezen'];
        $_bev = [
            'aannemen' => [$_k['naam'] . ' aannemen?', 'De fase wordt Aangenomen en de formulieren staan meteen in het portaal. '
                          . $_mail(['aangenomen']) . ' De afgesproken inzet staat daarna vast in alle scenario\'s. '
                          . 'Terugzetten kan in het dossier, maar een verstuurde mail neem je niet terug.', 'Ja, aannemen', ''],
            'twijfel'  => ['Op twijfel zetten?', 'Er verandert niets: geen nieuwe fase, geen mail, de inzet staat nog niet vast. '
                          . 'Bel ' . $_voor . ' na het weekend en open dit gesprek dan opnieuw.', 'Op twijfel zetten', ''],
            'afwijzen' => [$_k['naam'] . ' afwijzen?', 'De fase wordt Afgewezen. ' . $_mail($_afw_t)
                          . ' Terugzetten kan in het dossier, maar een verstuurde mail neem je niet terug.', 'Ja, afwijzen', '1'],
        ];
      ?>
      <?php // data-concept: feedback vastleggen herlaadt de pagina; het getypte bericht blijft (beheer.js). ?>
      <form method="post" class="bh-twgesprek-form" data-twgesprek<?= $_mag_form ? '' : ' inert' ?>
            data-bevestig="Besluit vastleggen?" data-concept="twgesprek-<?= (int)$sid ?>-<?= $_id ?>">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="gesprek">
        <input type="hidden" name="sol_id" value="<?= $_id ?>">
        <fieldset class="bh-twkeuze">
          <legend class="bh-veldlabel">Wat besluiten jullie?</legend>
          <?php foreach (['aannemen' => 'Aannemen', 'twijfel' => 'Twijfel', 'afwijzen' => 'Afwijzen'] as $_w => $_l): ?>
            <label class="bh-twoptie <?= h($_w) ?>">
              <input type="radio" name="besluit" value="<?= h($_w) ?>" required<?= $_b === $_w ? ' checked' : '' ?>
                     data-bevestig-titel="<?= h($_bev[$_w][0]) ?>" data-bevestig="<?= h($_bev[$_w][1]) ?>"
                     data-bevestig-knop="<?= h($_bev[$_w][2]) ?>" data-bevestig-gevaar="<?= $_bev[$_w][3] ?>">
              <span><?= h($_l) ?></span>
            </label>
          <?php endforeach; ?>
        </fieldset>
        <p class="bh-twgevolg" data-gevolg aria-live="polite">Kies een besluit; hier staat dan wat er gebeurt.</p>

        <div class="ds-veld">
          <label for="gb-<?= $_id ?>">Bericht aan <?= h($_voor) ?></label>
          <textarea class="ds-textarea" id="gb-<?= $_id ?>" name="bericht" rows="5" maxlength="2000"
                    aria-describedby="gbh-<?= $_id ?>"
                    placeholder="<?= $_niet ? 'Wat je wilt laten weten' : 'Wat jullie besproken hebben' ?>, in je eigen woorden. Leeg = de standaardtekst."><?= h((string)($_k['gesprek_bericht'] ?? '')) ?></textarea>
          <button type="button" class="bh-doc-actie" data-standaard hidden
                  data-standaard-aannemen="<?= h(tw_standaard_bericht('aannemen')) ?>"
                  data-standaard-afwijzen="<?= h(tw_standaard_bericht('afwijzen', $_niet)) ?>">Standaardtekst invoegen om aan te passen</button>
          <p class="ds-veld-hint" id="gbh-<?= $_id ?>">Dit komt in de mail. Laat je het leeg, dan gaat de standaardtekst mee.</p>
        </div>

        <div class="ds-veld">
          <label for="gn-<?= $_id ?>">Interne notitie <span class="bh-meta">alleen voor het bestuur, komt in het dossier</span></label>
          <input type="text" class="ds-input" id="gn-<?= $_id ?>" name="notitie" maxlength="300" value="<?= h((string)($_k['besluit_notitie'] ?? '')) ?>">
        </div>

        <button type="submit" class="ds-btn ds-btn-primair ds-btn-vol">Besluit vastleggen</button>
      </form>
      <?php if ($_mag_form): ?>
      <form method="post" class="bh-twgesprek-los">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="gesprek_los">
        <input type="hidden" name="sol_id" value="<?= $_id ?>">
        <button type="submit" class="bh-doc-actie">Terug zonder besluit</button>
      </form>
      <?php endif; ?>
      <?php endif; ?>
    </section>
  </aside>
</div>
<script src="<?= APP_URL ?>/assets/js/tabbladen.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/tabbladen.js') ?>" defer></script>
<script src="<?= APP_URL ?>/assets/js/gesprek.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/gesprek.js') ?>" defer></script>
