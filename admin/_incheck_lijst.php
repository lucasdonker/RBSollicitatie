<?php
/**
 * Partial: inchecken bij de ingang — ontwerp Beheer.dc.html 3c. Voor de selectiedag én het
 * trainingsweekend (één lijst, één gedrag).
 *
 * Staand bediend, met één hand, soms natte handen: grote raakvlakken, een tik ís opslaan.
 * Drie standen per rij: verwacht (Aanwezig / Niet), aanwezig (ingecheckt hh:mm, Ongedaan)
 * en niet (niet verschenen of afgemeld, met Aanwezig voor een laatkomer).
 *
 * Verwacht:
 *   $ick_ev           'selectiedag' | 'tw'
 *   $ick_titel        "Inchecken selectiedag"
 *   $ick_sub          "zondag 12 april · post Paal 13" (mag leeg)
 *   $ick_lijst        uit evenement_lijst()
 *   $ick_mag          bool  mag aanwezig/niet zetten (lezen op de module)
 *   $ick_mag_sluiten  bool  mag afsluiten (bewerken op de module)
 *   $ick_verder       ['url' => …, 'label' => 'Naar beoordelen'] of null
 * Gedrag: assets/js/incheck.js. Opslaan: admin/api_incheck.php.
 */
$ick_lijst = $ick_lijst ?? [];
$_afgesloten = evenement_afgesloten_op($ick_ev, (int)(actief_seizoen()['id'] ?? 0));
$_n_aanw = count(array_filter($ick_lijst, fn($k) => $k['stand'] === 'aanwezig'));
$_n_verw = count(array_filter($ick_lijst, fn($k) => $k['stand'] === 'verwacht'));
// Niet getekende gezondheidsverklaring/intake: wie dat heeft, gaat niet het water in.
$_open_f = function_exists('evenement_open_formulieren_seizoen') ? evenement_open_formulieren_seizoen((int)(actief_seizoen()['id'] ?? 0)) : [];
?>
<section class="ds-kaart bh-ick" data-incheck data-ev="<?= h($ick_ev) ?>" data-endpoint="<?= APP_URL ?>/admin/api_incheck.php" data-csrf="<?= h(csrf_token()) ?>">
  <div class="bh-ick-band">
    <div class="bh-ick-titel">
      <span class="bh-ick-titeltekst"><?= h($ick_titel) ?></span>
      <?php if (!empty($ick_sub)): ?><span class="bh-ick-subtekst"><?= h($ick_sub) ?></span><?php endif; ?>
    </div>
    <div class="bh-ick-tellers">
      <div class="bh-ick-teller"><span class="bh-ick-getal bh-num" data-teller="aanwezig"><?= $_n_aanw ?></span><span>aanwezig</span></div>
      <div class="bh-ick-teller"><span class="bh-ick-getal bh-num verwacht" data-teller="verwacht"><?= $_n_verw ?></span><span>verwacht</span></div>
    </div>
  </div>

  <div class="bh-ick-zoekrij">
    <label class="bh-zoek bh-ick-zoek">
      <span class="bh-zoek-icoon"><?= icoon('zoek', 17) ?></span>
      <span class="bh-vw">Zoek op naam</span>
      <input type="search" data-ick-zoek placeholder="Zoek op naam" autocomplete="off">
    </label>
    <button type="button" class="bh-ick-filter" data-ick-filter aria-pressed="false">Alleen verwacht</button>
  </div>

  <?php if (!$ick_lijst): ?>
    <div class="bh-incheck-leeg">
      <?php
        $leeg_titel = 'Nog niemand uitgenodigd';
        $leeg_tekst = 'Zodra er kandidaten zijn uitgenodigd, staan ze hier.';
        $leeg_icoon = 'mensen';
        require __DIR__ . '/_leeg.php';
      ?>
    </div>
  <?php else: ?>
  <ul class="bh-ick-lijst">
    <?php foreach ($ick_lijst as $_k):
      $_sub = trim(($_k['leeftijd'] !== null ? $_k['leeftijd'] . ' jaar' : '') . ($_k['groep'] ? ' · ' . $_k['groep'] : ''), ' ·');
      $_tijd = $_k['ingecheckt_op'] ? date('H:i', strtotime($_k['ingecheckt_op'])) : '';
    ?>
    <li class="bh-ick-rij" data-sol="<?= (int)$_k['sol_id'] ?>" data-stand="<?= h($_k['stand']) ?>"
        data-naam="<?= h(mb_strtolower($_k['naam'])) ?>" data-sub="<?= h($_sub) ?>">
      <span class="bh-ick-avatar"><?= bh_avatar($_k, 44) ?><span class="bh-ick-vink" aria-hidden="true"><?= icoon('vink', 19) ?></span></span>
      <span class="bh-ick-naam">
        <?= h($_k['naam']) ?>
        <span class="bh-ick-regel" data-ick-regel><?php
          if ($_k['stand'] === 'aanwezig')  echo 'ingecheckt ' . h($_tijd);
          elseif ($_k['stand'] === 'niet')  echo h($_k['afgemeld_reden'] ?: 'niet verschenen');
          else                              echo h($_sub);
          if ($_k['stand'] === 'verwacht' && ($_k['bevestiging'] ?? '') === 'komt_niet') echo ' · gaf door niet te kunnen';
        ?></span>
        <?php if (!empty($_k['bevestiging_reden'])): ?>
          <span class="bh-ick-regel">Opmerking: <?= h((string)$_k['bevestiging_reden']) ?></span>
        <?php endif; ?>
        <?php if (!empty($_open_f[(int)$_k['sol_id']])): ?>
          <span class="bh-ick-letop"><?= bh_badge('Niet getekend: ' . implode(', ', $_open_f[(int)$_k['sol_id']]), 'rood') ?></span>
        <?php endif; ?>
      </span>
      <?php if ($ick_mag): ?>
        <span class="bh-ick-knoppen">
          <button type="button" class="bh-ick-aanwezig" data-ick="aanwezig">Aanwezig</button>
          <button type="button" class="bh-ick-niet" data-ick="niet">Niet</button>
          <button type="button" class="bh-ick-ongedaan" data-ick="ongedaan">Ongedaan</button>
        </span>
      <?php else: ?>
        <?= $_k['stand'] === 'aanwezig' ? bh_badge('Aanwezig', 'groen') : ($_k['stand'] === 'niet' ? bh_badge('Niet', 'rood') : bh_badge('Verwacht', 'grijs')) ?>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>

  <div class="bh-ick-voet">
    <span class="bh-meta">
      <?php if ($_afgesloten): ?>
        Afgesloten om <?= h(date('H:i', strtotime($_afgesloten))) ?>: wie toen niet was ingecheckt staat op niet verschenen. Een laatkomer check je gewoon nog in.
      <?php else: ?>
        Inchecken staat open. Sluit je af, dan krijgt wie nog niet is ingecheckt "niet verschenen"; laatkomers kun je daarna nog inchecken.
      <?php endif; ?>
    </span>
    <?php if ($ick_mag_sluiten && !$_afgesloten): ?>
      <form method="POST" action="<?= APP_URL ?>/admin/api_incheck.php"
            data-bevestig="Inchecken afsluiten? Wie nu nog niet is ingecheckt, komt op niet verschenen. Laatkomers kun je daarna nog inchecken.">
        <?= csrf_input() ?>
        <input type="hidden" name="ev" value="<?= h($ick_ev) ?>">
        <input type="hidden" name="actie" value="afsluiten">
        <button type="submit" class="ds-btn ds-btn-secundair">Inchecken afsluiten</button>
      </form>
    <?php endif; ?>
    <?php if (!empty($ick_verder)): ?>
      <a class="ds-btn ds-btn-primair" href="<?= h($ick_verder['url']) ?>"><?= h($ick_verder['label']) ?></a>
    <?php endif; ?>
  </div>
</section>
<script src="<?= APP_URL ?>/assets/js/incheck.js?v=<?= @filemtime(__DIR__ . '/../assets/js/incheck.js') ?>" defer></script>
