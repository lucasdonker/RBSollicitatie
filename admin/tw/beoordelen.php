<?php
/**
 * TW-stap Beoordelen: vrijwilligers op hun telefoon. Links (of bovenaan op een telefoon) de
 * lijst met foto, rechts de persoon: observaties toevoegen, en ingeklapt wat er op de
 * selectiedag over hem of haar is gezegd. Onderdeel van admin/trainingsweekend.php.
 * Gedrag: assets/js/tw_beoordelen.js (tikken is opslaan).
 */
$obs_alle  = tw_observaties_alle($sid);
// Groep kiezen (zoals op de selectiedag); de keuze reist mee in elke link.
$bo_groepen = evenement_groepen('tw', $sid);
$bo_gnaam   = [];
foreach ($bo_groepen as $_g) $bo_gnaam[(int)$_g['id']] = evenement_groep_label($_g);
$bo_groep   = (string)($_GET['groep'] ?? '');
if ($bo_groep !== '' && !isset($bo_gnaam[(int)$bo_groep])) $bo_groep = '';
$bo_url     = fn(?int $k = null, ?string $g = null) => tw_url('beoordelen', $k, ['groep' => $g ?? $bo_groep]);
$bo_kand    = $bo_groep === '' ? $kandidaten : array_values(array_filter($kandidaten, fn($k) => (string)($k['groep_id'] ?? '') === $bo_groep));
// Twijfel van het bestuur (stap Evaluatie) bovenaan, terugkerende lifeguards onderaan;
// vorige/volgende volgt dezelfde volgorde. Aannemen/afwijzen blijft onzichtbaar voor beoordelaars.
$bo_twijfel = tw_twijfel_ids($bo_kand);
$bo_secties = evenement_beoordeel_secties($bo_kand, $bo_twijfel, 'Extra aandacht gevraagd',
                                          'Het bestuur hoort over hen graag extra wat je ziet.');
$bo_lijst   = evenement_secties_plat($bo_secties);
$ids       = array_map(fn($k) => (int)$k['sol_id'], $bo_lijst);
$huidig_id = (int)($_GET['k'] ?? 0);
if (!in_array($huidig_id, $ids, true)) $huidig_id = $ids[0] ?? 0;
$pos    = array_search($huidig_id, $ids, true);
$huidig = $pos !== false ? $bo_lijst[$pos] : null;
$_aanw  = fn($k) => !empty($k['aanwezig']) ? 'aanwezig' : (($k['afgemeld_reden'] ?? null) !== null ? 'niet gekomen' : 'nog niet ingecheckt');
?>
<div class="bh-beoordeel" data-twbeoordeel data-endpoint="<?= APP_URL ?>/admin/trainingsweekend.php" data-csrf="<?= h(csrf_token()) ?>">
  <aside class="bh-beoordeel-lijst">
    <?php
      $gk_groepen = $bo_groepen; $gk_kandidaten = $kandidaten; $gk_gekozen = $bo_groep;
      $gk_url = fn(string $g) => $bo_url(null, $g);
      require __DIR__ . '/../_groepkeuze.php';
    ?>
    <?php foreach ($bo_secties as $_sec): ?>
    <?php if ($_sec['titel']): ?>
      <h3 class="bh-kandidaatlijst-kop"><?= h($_sec['titel']) ?> <span class="bh-num"><?= count($_sec['rijen']) ?></span></h3>
      <?php if ($_sec['uitleg'] !== ''): ?><p class="bh-meta bh-kandidaatlijst-uitleg"><?= h($_sec['uitleg']) ?></p><?php endif; ?>
    <?php endif; ?>
    <ul class="bh-kandidaatlijst">
      <?php foreach ($_sec['rijen'] as $_k):
        $_id  = (int)$_k['sol_id'];
        $_nu  = $_id === $huidig_id;
        $_af  = empty($_k['aanwezig']) && ($_k['afgemeld_reden'] ?? null) !== null;
        $_nob = count($obs_alle[$_id] ?? []);
      ?>
      <li>
        <a class="bh-kandidaat<?= $_nu ? ' nu' : '' ?><?= $_af ? ' afwezig' : '' ?>" href="<?= h($bo_url($_id)) ?>#kandidaat"<?= $_nu ? ' aria-current="true"' : '' ?>>
          <?= bh_avatar($_k, 38, ['dim' => $_af]) ?>
          <span class="bh-kandidaat-tekst">
            <span class="bh-kandidaat-naam">
              <?= h((string)$_k['naam']) ?>
              <?php if (!empty($_k['bijzonderheden'])): ?>
                <span class="bh-kandidaat-letop" title="Bijzonderheid gemeld"><?= icoon('waarschuwing', 14) ?><span class="bh-vw">bijzonderheid gemeld</span></span>
              <?php endif; ?>
            </span>
            <span class="bh-kandidaat-sub"><?= h($_aanw($_k)) ?><?= $_nob ? ' · ' . $_nob . ' ' . ($_nob === 1 ? 'observatie' : 'observaties') : '' ?></span>
          </span>
          <?php if ($_nu): ?><span class="bh-kandidaat-nu">nu</span><?php endif; ?>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endforeach; ?>
  </aside>

  <?php if ($huidig): $hid = (int)$huidig['sol_id']; ?>
  <section class="bh-beoordeel-kaart" id="kandidaat" data-sol="<?= $hid ?>">
    <header class="bh-beoordeel-kop">
      <?= bh_avatar($huidig, 56, ['vergroot' => true]) ?>
      <span class="bh-beoordeel-wie">
        <span class="bh-beoordeel-naam"><?= h((string)$huidig['naam']) ?></span>
        <?php /* Geen telefoonnummer: een beoordelaar belt niet (dataminimalisatie). */ ?>
        <?= bh_meta([$huidig['leeftijd'] ? $huidig['leeftijd'] . ' jaar' : '', $bo_gnaam[(int)($huidig['groep_id'] ?? 0)] ?? '',
                     $_aanw($huidig), !empty($huidig['is_lifeguard']) ? 'terugkerende lifeguard' : '']) ?>
      </span>
      <?php if ($mag_dossier): ?>
        <a class="ds-btn ds-btn-secundair ds-btn-sm" href="<?= APP_URL ?>/admin/sollicitant.php?id=<?= $hid ?>">Dossier</a>
      <?php endif; ?>
    </header>

    <?php if (in_array($hid, $bo_twijfel, true)): ?>
      <div class="ds-melding ds-melding-info bh-beoordeel-vraag">
        <?= icoon('info', 16) ?>
        <span><strong>Extra aandacht gevraagd door het bestuur.</strong>
          <?= !empty($huidig['evaluatie_bericht']) ? h((string)$huidig['evaluatie_bericht']) : 'Schrijf op wat je ziet, ook als het klein is.' ?></span>
      </div>
    <?php endif; ?>

    <?php if (!empty($huidig['bijzonderheden'])): ?>
      <div class="ds-melding ds-melding-warn bh-beoordeel-bijz">
        <?= icoon('waarschuwing', 16) ?>
        <?php if ($mag_blk): ?>
          <span><strong>Bijzonderheid gemeld:</strong> <?= nl2br(h((string)$huidig['bijzonderheden'])) ?></span>
        <?php else: ?>
          <span><strong>Let op:</strong> er is een bijzonderheid bekend bij het bestuur. Vraag bij een
            bestuurslid na waar je dit weekend op moet letten.</span>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="bh-observatieblok">
      <h3>Wat valt op?</h3>
      <?php if ($mag_meedraaien && !empty($huidig['doorgevoerd_op'])): ?>
        <p class="bh-meta">Over <?= h((string)$huidig['voornaam']) ?> is al besloten; nieuwe observaties horen nu in het dossier.</p>
      <?php elseif ($mag_meedraaien): ?>
        <div class="bh-soortkeuze" role="group" aria-label="Soort observatie">
          <button type="button" class="bh-soort positief" data-soort="positief" aria-pressed="false">Positief</button>
          <button type="button" class="bh-soort neutraal gekozen" data-soort="neutraal" aria-pressed="true">Neutraal</button>
          <button type="button" class="bh-soort aandacht" data-soort="aandacht" aria-pressed="false">Aandacht</button>
        </div>
        <label class="bh-vw" for="obs-<?= $hid ?>">Observatie over <?= h((string)$huidig['naam']) ?></label>
        <textarea class="ds-textarea" id="obs-<?= $hid ?>" data-obs-tekst rows="3" maxlength="2000" placeholder="Bijvoorbeeld: pakt aan zonder vragen, gaat goed om met kritiek…"></textarea>
        <button type="button" class="ds-btn ds-btn-primair ds-btn-vol" data-obs-opslaan>Observatie opslaan</button>
        <p class="ds-veld-hint">Voor het gesprek met het bestuur. De sollicitant ziet dit niet.</p>
      <?php endif; ?>

      <?php
        $obs_lijst = $obs_alle[$hid] ?? [];
        $obs_eigen = fn($o) => tw_observatie_is_eigen($o, $ik, $ik_id);
        if ($obs_lijst) require __DIR__ . '/../_observaties.php';
        else echo '<p class="bh-meta">Nog niets opgeschreven.</p>';
      ?>
    </div>

    <?php $d = bh_dossier($hid); if ($d) { $sds_dossier = $d; require __DIR__ . '/../_sd_samenvatting.php'; } ?>

    <footer class="bh-beoordeel-voet">
      <?php $_v = $pos > 0 ? $bo_lijst[$pos - 1] : null; $_n = $bo_lijst[$pos + 1] ?? null; ?>
      <?php if ($_v): ?>
        <a class="ds-btn ds-btn-secundair" href="<?= h($bo_url((int)$_v['sol_id'])) ?>#kandidaat" rel="prev">Vorige</a>
      <?php else: ?>
        <span class="ds-btn ds-btn-zacht" aria-disabled="true">Vorige</span>
      <?php endif; ?>
      <?php if ($_n): ?>
        <a class="ds-btn ds-btn-primair bh-beoordeel-volgende" href="<?= h($bo_url((int)$_n['sol_id'])) ?>#kandidaat" rel="next">Volgende: <?= h((string)$_n['voornaam']) ?></a>
      <?php endif; ?>
    </footer>
  </section>
  <?php endif; ?>
</div>
<script src="<?= APP_URL ?>/assets/js/tw_beoordelen.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/tw_beoordelen.js') ?>" defer></script>
