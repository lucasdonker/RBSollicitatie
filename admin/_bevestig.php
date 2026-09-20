<?php
/**
 * Partial: bevestiging van iets onomkeerbaars.
 *
 * Werkt als tussenscherm zonder JavaScript: de knop post naar ?bevestig=<actie> en
 * deze pagina verschijnt. Dat is niet alleen een terugval — het is ook de vorm die in
 * het renderharnas te screenshotten is. Een confirm() kun je niet vastleggen.
 *
 * Verwacht:
 *   $bev         array   uitkomst van bh_gevolgen()
 *   $bev_form    array   ['actie' => url, 'velden' => [naam => waarde]]  (csrf komt erbij)
 *   $bev_terug   string  url van de annuleerknop
 *   $bev_namen   array   optioneel: namen van de betrokken mensen
 */
$bev       = $bev ?? null;
$bev_form  = $bev_form ?? ['actie' => '', 'velden' => []];
$bev_terug = $bev_terug ?? '';
$bev_namen = $bev_namen ?? [];
if (!$bev) return;
?>
<div class="bh-bevestig">
  <form method="post" action="<?= h($bev_form['actie']) ?>" class="bh-bevestig-kaart">
    <?= csrf_input() ?>
    <?php foreach (($bev_form['velden'] ?? []) as $_n => $_v): ?>
      <?php if (is_array($_v)): foreach ($_v as $_vv): ?>
        <input type="hidden" name="<?= h((string)$_n) ?>[]" value="<?= h((string)$_vv) ?>">
      <?php endforeach; else: ?>
        <input type="hidden" name="<?= h((string)$_n) ?>" value="<?= h((string)$_v) ?>">
      <?php endif; ?>
    <?php endforeach; ?>

    <h2><?= h($bev['titel']) ?></h2>

    <?php if (!empty($bev['bericht'])): ?>
      <div class="ds-melding <?= $bev['onomkeerbaar'] ? 'ds-melding-warn' : 'ds-melding-info' ?>">
        <?= icoon($bev['onomkeerbaar'] ? 'waarschuwing' : 'info', 16) ?>
        <span><?= h($bev['bericht']) ?></span>
      </div>
    <?php endif; ?>

    <?php if (!empty($bev['regels'])): ?>
      <ul class="bh-bevestig-regels">
        <?php foreach ($bev['regels'] as $_r): ?><li><?= h($_r) ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($bev_namen): ?>
      <?php
        // Begrensd op het aantal dat de actie betreft: zou de namenquery ooit meer
        // teruggeven, dan noemt de bevestiging liever niemand dan de verkeerden.
        $_verwacht = count($bev_form['velden']['ids'] ?? []);
        $_namen = ($_verwacht > 0 && count($bev_namen) > $_verwacht) ? [] : $bev_namen;
      ?>
      <?php if ($_namen): ?>
      <p class="bh-bevestig-namen">
        <?= h(implode(', ', array_slice($_namen, 0, 8))) ?><?php
          if (count($_namen) > 8) echo ' en nog ' . (count($_namen) - 8);
        ?>
      </p>
      <?php endif; ?>
    <?php endif; ?>

    <div class="bh-bevestig-knoppen">
      <a class="ds-btn ds-btn-zacht" href="<?= h($bev_terug) ?>">Annuleren</a>
      <button type="submit" class="ds-btn ds-btn-<?= $bev['toon'] === 'gevaar' ? 'gevaar' : 'primair' ?>">
        <?= h($bev['knop']) ?>
      </button>
    </div>
  </form>
</div>
<?php
$bev = null; $bev_form = null; $bev_terug = ''; $bev_namen = [];
