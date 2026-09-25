<?php
/**
 * Het gesprek met het bestuur (TW-stap Gesprekken, ?k=<sol_id>). Alles over één persoon bij
 * elkaar: gegevens, de inzet (opgave + loonplanning-concept, bewerkbaar), wat er op het
 * weekend en de selectiedag is gezien, open taken — en onderaan het besluit.
 *
 * Openen = claimen ("bezig door jou", 30 min). Aannemen/afwijzen gaat direct door via
 * tw_gesprek_afronden(); het bericht aan de sollicitant gaat als {{gesprek_bericht}} mee.
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
    $_claim = evenement_claim('tw_deelname', 'gesprek', $sid, $_id, $ik_id);
}
$_mag_form = !$_klaar && $_claim['ok'];
$_taken    = bh_dossier_taken($gd_dossier);
$_open     = array_filter($_taken['rijen'], fn($t) => !$t['done']);
$_sol      = $gd_dossier['sol'];
$_b        = (string)($_k['besluit'] ?? '');
?>
<p><a class="bh-terug" href="<?= h(tw_url('gesprekken')) ?>"><?= icoon('terug', 16) ?> Alle gesprekken</a></p>

<?php if (!$_claim['ok']): ?>
  <div class="ds-melding ds-melding-warn" role="status">
    <?= icoon('waarschuwing', 16) ?>
    <span><?= h(evenement_claim_naam($_claim['door'])) ?> voert dit gesprek nu. Je kunt meelezen; het besluit
      vastleggen kan pas als dat gesprek klaar is (of na 30 minuten).</span>
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
                       !empty($_sol['terugkerend']) ? 'terugkerend' : 'nieuw']) ?>
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
      <?php if (!empty($_k['bevestiging_reden'])): ?>
        <p class="bh-dossier-tekst"><strong>Opmerking bij het aanmelden:</strong> <?= h((string)$_k['bevestiging_reden']) ?></p>
      <?php endif; ?>
      <?php if ($mag_blk && !empty($_k['bijzonderheden'])): ?>
        <div class="ds-melding ds-melding-warn"><?= icoon('waarschuwing', 16) ?><span><strong>Bijzonderheid:</strong> <?= nl2br(h((string)$_k['bijzonderheden'])) ?></span></div>
      <?php endif; ?>
    </section>

    <section class="ds-kaart ds-kaart-p">
      <?php
        $af_sol_id = $_id; $af_seizoen = $seizoen;
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
        else echo '<p class="bh-meta">Niemand heeft iets opgeschreven.</p>';
      ?>
    </section>

    <?php if (module_aan('selectiedag') && ($gd_dossier['scores'] || $gd_dossier['observaties'] || $gd_dossier['besluit'])): ?>
    <section class="ds-kaart ds-kaart-p bh-twgesprek-sd">
      <h3>Op de selectiedag</h3>
      <?php
        $scs_dossier = $gd_dossier; require __DIR__ . '/../_sd_scores.php';
        $sds_dossier = $gd_dossier; $sds_open = true; require __DIR__ . '/../_sd_samenvatting.php';
      ?>
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
        <p><?= bh_badge(['aannemen' => 'Aangenomen', 'afwijzen' => 'Afgewezen', 'twijfel' => 'Twijfel'][$_b] ?? 'Doorgevoerd', ['aannemen' => 'groen', 'afwijzen' => 'rood'][$_b] ?? 'oranje') ?>
          <span class="bh-meta">doorgevoerd op <?= h(datum_nl(substr((string)$_k['doorgevoerd_op'], 0, 10))) ?><?= !empty($_k['besluit_door']) ? ' door ' . h((string)$_k['besluit_door']) : '' ?></span></p>
        <?php if (!empty($_k['besluit_notitie'])): ?><p><strong>Intern:</strong> <?= h((string)$_k['besluit_notitie']) ?></p><?php endif; ?>
        <p><strong>Aan de sollicitant:</strong> <?= nl2br(h((string)($_k['gesprek_bericht'] ?: tw_standaard_bericht($_b)))) ?></p>
      <?php else: ?>
      <form method="post" class="bh-twgesprek-form"<?= $_mag_form ? '' : ' inert' ?>
            data-bevestig="Besluit vastleggen? Bij aannemen of afwijzen gaat het meteen door: de fase verandert en de sollicitant krijgt bericht, met jullie tekst. Twijfel blijft staan.">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="gesprek">
        <input type="hidden" name="sol_id" value="<?= $_id ?>">
        <fieldset class="bh-twkeuze">
          <legend class="bh-vw">Besluit over <?= h((string)$_k['naam']) ?></legend>
          <?php foreach (['aannemen' => 'Aannemen', 'twijfel' => 'Twijfel', 'afwijzen' => 'Afwijzen'] as $_w => $_l): ?>
            <label class="bh-twoptie <?= h($_w) ?>">
              <input type="radio" name="besluit" value="<?= h($_w) ?>" required<?= $_b === $_w ? ' checked' : '' ?>>
              <span><?= h($_l) ?></span>
            </label>
          <?php endforeach; ?>
        </fieldset>

        <div class="ds-veld">
          <label for="gb-<?= $_id ?>">Bericht aan de sollicitant</label>
          <textarea class="ds-textarea" id="gb-<?= $_id ?>" name="bericht" rows="5" maxlength="2000"
                    aria-describedby="gbh-<?= $_id ?>"><?= h((string)($_k['gesprek_bericht'] ?? '')) ?></textarea>
          <p class="ds-veld-hint" id="gbh-<?= $_id ?>">
            Wat jullie besproken hebben, in je eigen woorden — dit komt in de mail. Leeg laten = de standaardtekst:
            <br><em>Aangenomen:</em> <?= h(tw_standaard_bericht('aannemen')) ?>
            <br><em>Afgewezen:</em> <?= h(tw_standaard_bericht('afwijzen')) ?>
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
