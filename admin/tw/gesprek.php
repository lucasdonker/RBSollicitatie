<?php
/**
 * Het gesprek met het bestuur (TW-stap Gesprekken, ?k=<sol_id>). Alles over één persoon bij
 * elkaar: gegevens, de inzet (opgave + loonplanning-concept, bewerkbaar), wat er op het
 * weekend en de selectiedag is gezien, open taken — en onderaan het besluit.
 *
 * Openen = claimen ("bezig door jou", 30 min). Aannemen/afwijzen gaat direct door via
 * tw_gesprek_afronden(); het bericht aan de sollicitant gaat als {{gesprek_bericht}} mee.
 * Heeft een ander het open, dan kan wie mag beslissen het overnemen (admin/tw/_overnemen.php).
 * Wie niet kwam (tw_niet_gekomen()) krijgt hier ook een besluit, zonder gesprek en met een
 * eigen standaardtekst bij afwijzen.
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
$_open     = array_filter($_taken['rijen'], fn($t) => !$t['done']);
$_sol      = $gd_dossier['sol'];
$_b        = (string)($_k['besluit'] ?? '');
$_niet     = tw_niet_gekomen($_k);   // was er niet: geen gesprek, wel een besluit
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
    <section class="ds-kaart ds-kaart-p">
      <header class="bh-beoordeel-kop">
        <?= bh_avatar($_k, 72, ['vergroot' => true]) ?>
        <span class="bh-beoordeel-wie">
          <span class="bh-beoordeel-naam"><?= h((string)$_k['naam']) ?></span>
          <?= bh_meta([$gd_dossier['leeftijd'] !== null ? $gd_dossier['leeftijd'] . ' jaar' : '',
                       $gd_dossier['leeftijd_seizoen'] !== null && $gd_dossier['leeftijd_seizoen'] !== $gd_dossier['leeftijd'] ? $gd_dossier['leeftijd_seizoen'] . ' bij de start' : '',
                       fase_label((string)$_k['fase']),
                       // Strandwacht = Authentik-koppeling (is_lifeguard), zoals overal; "terugkerend" is alleen eerder gesolliciteerd.
                       !empty($_k['is_lifeguard']) ? 'terugkerende strandwacht' : (!empty($_sol['terugkerend']) ? 'eerder gesolliciteerd' : 'nieuw')]) ?>
          <?php if ($mag_dossier): ?><?= bh_meta([(string)($_sol['email'] ?? ''), (string)($_sol['telefoon'] ?? '')]) ?><?php endif; ?>
        </span>
        <?php if ($mag_dossier): ?><a class="ds-btn ds-btn-secundair ds-btn-sm" href="<?= APP_URL ?>/admin/sollicitant.php?id=<?= $_id ?>" target="_blank" rel="noopener">Dossier</a><?php endif; ?>
      </header>
      <?php if ($gd_dossier['minderjarig']): ?>
        <p class="bh-meta">Minderjarig: een ouder of verzorger tekent de documenten mee.</p>
      <?php endif; ?>
      <?php if (!empty($gd_dossier['leeftijd_melding'])): ?>
        <div class="ds-melding ds-melding-warn"><?= icoon('waarschuwing', 16) ?><span><?= h((string)$gd_dossier['leeftijd_melding']) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($_k['eerder_weg'])): ?>
        <p class="bh-twgesprek-weg"><?= icoon('klok', 14) ?> Moet weg: <?= h(tw_eerder_weg_tekst((string)$_k['eerder_weg'])) ?></p>
      <?php endif; ?>
      <?php if (!empty($_k['bevestiging_reden'])): ?>
        <p class="bh-dossier-tekst"><strong>Opmerking bij het aanmelden:</strong> <?= h((string)$_k['bevestiging_reden']) ?></p>
      <?php endif; ?>
      <?php if ($mag_blk && !empty($_k['bijzonderheden'])): ?>
        <div class="ds-melding ds-melding-warn"><?= icoon('waarschuwing', 16) ?><span><strong>Bijzonderheid:</strong> <?= nl2br(h((string)$_k['bijzonderheden'])) ?></span></div>
      <?php endif; ?>
    </section>

    <section class="ds-kaart ds-kaart-p">
      <?php
        $af_sol_id = $_id; $af_seizoen = $seizoen; $af_alleen_lezen = !$_claim['ok'];
        if (module_aan('loonplanning') && mag('loonplanning', 'lezen')) {
            require __DIR__ . '/../_afspraak_editor.php';
        } else {
            // Zonder loonplanning: alleen de opgave, per week.
            $_eff = beschikbaarheid2_effectief($_id, $seizoen);
            echo '<h3>Opgegeven beschikbaarheid</h3>';
            if (!$_eff) echo '<p class="bh-meta">Nog niets opgegeven.</p>';
            else {
                echo '<ul class="bh-twopgave">';
                foreach ($_eff as $_wk => $_w) echo '<li><span>W' . (int)$_wk . '</span> <strong>' . (int)$_w['dagen_max'] . ' d</strong></li>';
                echo '</ul>';
            }
        }
      ?>
    </section>

    <section class="ds-kaart ds-kaart-p">
      <h3>Dit weekend opgevallen</h3>
      <?php
        $obs_lijst = tw_observaties_van($sid, $_id);
        if ($obs_lijst) { $obs_eigen = null; require __DIR__ . '/../_observaties.php'; }
        else echo '<p class="bh-meta">' . ($_niet ? 'Niet op het weekend geweest, dus niets gezien.' : 'Niemand heeft iets opgeschreven.') . '</p>';
      ?>
    </section>

    <?php // Feedback van lifeguards: hier vastleggen (hoort bij het beslismoment); toont ook die van de selectiedag.
      $lgf_sol_id = $_id; $lgf_seizoen_id = $sid; $lgf_moment = 'tw'; $lgf_mag_bewerken = $mag_beslissen;
      $lgf_terug = tw_url('gesprekken', $_id); $lgf_kop = 'h3';
      require __DIR__ . '/../_lg_feedback.php'; ?>

    <?php if (module_aan('selectiedag') && ($gd_dossier['scores'] || $gd_dossier['observaties'] || $gd_dossier['besluit'])): ?>
    <section class="ds-kaart ds-kaart-p bh-twgesprek-sd">
      <h3>Op de selectiedag</h3>
      <?php $sds_dossier = $gd_dossier; $sds_open = true; $sds_cijfers_open = true; require __DIR__ . '/../_sd_samenvatting.php'; ?>
    </section>
    <?php endif; ?>

    <?php if ($_open): ?>
    <section class="ds-kaart ds-kaart-p">
      <h3>Nog open in het portaal <span class="bh-meta"><?= count($_open) ?></span></h3>
      <ul class="bh-twtaken">
        <?php foreach ($_open as $_t): ?><li><?= h($_t['titel']) ?><?php if ($_t['sub'] !== ''): ?> <span class="bh-meta"><?= h($_t['sub']) ?></span><?php endif; ?></li><?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>
  </div>

  <aside class="bh-twgesprek-besluit">
    <section class="ds-kaart ds-kaart-p">
      <h3>Besluit</h3>
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
        $_voor  = (string)($_k['voornaam'] ?? '') ?: (string)$_k['naam'];
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
      <?php // data-concept: feedback vastleggen hierboven herlaadt de pagina; het getypte bericht blijft (beheer.js). ?>
      <form method="post" class="bh-twgesprek-form"<?= $_mag_form ? '' : ' inert' ?>
            data-bevestig="Besluit vastleggen?" data-concept="twgesprek-<?= (int)$sid ?>-<?= $_id ?>">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="gesprek">
        <input type="hidden" name="sol_id" value="<?= $_id ?>">
        <fieldset class="bh-twkeuze">
          <legend class="bh-vw">Besluit over <?= h((string)$_k['naam']) ?></legend>
          <?php foreach (['aannemen' => 'Aannemen', 'twijfel' => 'Twijfel', 'afwijzen' => 'Afwijzen'] as $_w => $_l): ?>
            <label class="bh-twoptie <?= h($_w) ?>">
              <input type="radio" name="besluit" value="<?= h($_w) ?>" required<?= $_b === $_w ? ' checked' : '' ?>
                     data-bevestig-titel="<?= h($_bev[$_w][0]) ?>" data-bevestig="<?= h($_bev[$_w][1]) ?>"
                     data-bevestig-knop="<?= h($_bev[$_w][2]) ?>" data-bevestig-gevaar="<?= $_bev[$_w][3] ?>">
              <span><?= h($_l) ?></span>
            </label>
          <?php endforeach; ?>
        </fieldset>

        <div class="ds-veld">
          <label for="gb-<?= $_id ?>">Bericht aan de sollicitant</label>
          <textarea class="ds-textarea" id="gb-<?= $_id ?>" name="bericht" rows="5" maxlength="2000"
                    aria-describedby="gbh-<?= $_id ?>"><?= h((string)($_k['gesprek_bericht'] ?? '')) ?></textarea>
          <p class="ds-veld-hint" id="gbh-<?= $_id ?>">
            <?= $_niet ? 'Wat je de sollicitant wilt laten weten' : 'Wat jullie besproken hebben' ?>, in je eigen woorden — dit komt in de mail. Leeg laten = de standaardtekst:
            <br><em>Aangenomen:</em> <?= h(tw_standaard_bericht('aannemen')) ?>
            <br><em>Afgewezen:</em> <?= h(tw_standaard_bericht('afwijzen', $_niet)) ?>
          </p>
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
