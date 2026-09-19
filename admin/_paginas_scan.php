<?php
/**
 * Partial — het rapport van een ingelezen ontwerp.
 * Verwacht: $rapport_versie (rij uit pagina_versies), $pagina, $id, $mag_bewerken.
 *
 * Het ontwerp is niet opgeschoond. Dit rapport laat zien wat erin zit, zodat iemand die
 * beslissing bewust neemt. Externe bronnen zijn geblokkeerd tot ze hier worden toegestaan.
 */
$scan = pag_scan_van_versie($rapport_versie);
$bevindingen = (array)($scan['bevindingen'] ?? []);
$hosts = (array)($scan['hosts'] ?? []);
$telling = (array)($scan['telling'] ?? []);
$al_toegestaan = preg_split('/\s+/', (string)($pagina['csp_extra'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
$is_bevestigd = $rapport_versie['scan_bevestigd_op'] !== null;
?>
<section class="ds-kaart ds-kaart-p pg-rapport" aria-labelledby="pg-rapport-kop">
  <h2 class="pg-h2" id="pg-rapport-kop">Rapport van versie <?= (int)$rapport_versie['versie'] ?></h2>
  <p class="pg-sub">
    <?= h((string)($rapport_versie['bestandsnaam_origineel'] ?? 'ontwerp.html')) ?> ·
    <?= (int)round($rapport_versie['grootte'] / 1024) ?> KB ·
    ingelezen op <?= h(datum_nl((string)$rapport_versie['aangemaakt_op'])) ?>
  </p>

  <?php if (!$bevindingen): ?>
    <div class="ds-melding ds-melding-ok"><div>Er is niets bijzonders gevonden in dit ontwerp.</div></div>
  <?php else: ?>
    <p class="pg-telling">
      <?php foreach (['hoog' => 'aandachtspunt', 'midden' => 'punt om te weten', 'laag' => 'klein punt', 'info' => 'tip'] as $niv => $woord): ?>
        <?php if (!empty($telling[$niv])): ?>
        <span class="pg-tel pg-tel-<?= $niv ?>"><?= (int)$telling[$niv] ?> <?= h($woord) ?><?= $telling[$niv] > 1 ? 'en' : '' ?></span>
        <?php endif; ?>
      <?php endforeach; ?>
    </p>

    <ul class="pg-bevindingen">
      <?php foreach ($bevindingen as $b): ?>
      <li class="pg-bevinding pg-bevinding-<?= h((string)$b['niveau']) ?>">
        <span class="pg-niveau"><?= h(pag_scan_niveau_label((string)$b['niveau'])) ?></span>
        <div>
          <strong><?= h((string)$b['titel']) ?></strong>
          <p><?= h((string)$b['detail']) ?></p>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if ($is_bevestigd): ?>
    <p class="pg-rij-meta">Dit rapport is al bevestigd op <?= h(datum_nl((string)$rapport_versie['scan_bevestigd_op'])) ?>.</p>
  <?php elseif ($mag_bewerken): ?>
    <form method="post" class="pg-bevestig">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="bevestig">
      <input type="hidden" name="versie_id" value="<?= (int)$rapport_versie['id'] ?>">

      <?php $open = array_values(array_diff($hosts, $al_toegestaan)); ?>
      <?php if ($open): ?>
      <fieldset class="pg-hosts">
        <legend>Externe bronnen toestaan</legend>
        <p class="ds-veld-hint">Deze pagina verwijst naar de hosts hieronder. Ze worden geblokkeerd zolang je ze niet aanvinkt — het ontwerp kan er dan anders uitzien dan bedoeld. Vink alleen aan wat je vertrouwt.</p>
        <?php foreach ($open as $i => $host): ?>
        <div class="pg-vink">
          <input type="checkbox" id="pg-host-<?= $i ?>" name="host[]" value="<?= h($host) ?>">
          <label for="pg-host-<?= $i ?>"><code><?= h($host) ?></code></label>
        </div>
        <?php endforeach; ?>
      </fieldset>
      <?php endif; ?>

      <div class="pg-vink">
        <input type="checkbox" id="pg-gelezen" name="gelezen" value="1" required aria-required="true">
        <label for="pg-gelezen">Ik heb het rapport gelezen en wil dit ontwerp live zetten.</label>
      </div>

      <div class="pg-knoprij">
        <button type="submit" class="ds-btn ds-btn-primair">Live zetten</button>
        <a class="ds-btn ds-btn-secundair" href="<?= APP_URL ?>/p/<?= h((string)$pagina['slug']) ?>?preview=<?= (int)$rapport_versie['id'] ?>" target="_blank" rel="noopener">
          Eerst bekijken
        </a>
      </div>
    </form>
  <?php endif; ?>
</section>
