<?php
/**
 * Partial: de topbalk — paginatitel, seizoenbadge, hulpknop, hamburger op mobiel.
 *
 * Verwacht:
 *   $top_titel    string  paginatitel
 *   $top_seizoen  ?array  actief_seizoen()
 *   $top_hulp     ?array  hulp_tekst() → ['titel','tekst','url']
 */
$top_titel   = $top_titel ?? 'Beheer';
$top_seizoen = $top_seizoen ?? null;
$top_hulp    = $top_hulp ?? null;
?>
<header class="bh-topbar">
  <button type="button" class="bh-hamburger" id="hamburger" aria-label="Menu openen"
          aria-expanded="false" aria-controls="zijbalk"><?= icoon('menu', 20) ?></button>

  <h1 class="bh-topbar-titel"><?= h($top_titel) ?></h1>

  <?php /* Het venster hoort bij de knop en staat daarom hier; een geopende <dialog>
           gaat naar de top layer en trekt zich niets van deze flexbalk aan. */ ?>
  <?php require __DIR__ . '/_zoeker.php'; ?>

  <?php if ($top_seizoen): ?>
    <?php $_open = ($top_seizoen['status'] ?? '') === 'open'; ?>
    <span class="bh-seizoen ds-fase-<?= $_open ? 'groen' : 'grijs' ?>">
      <?= icoon($_open ? 'vink-cirkel' : 'slot', 13) ?>
      <?= h($top_seizoen['naam']) ?> · <?= $_open ? 'Open' : 'Gesloten' ?>
    </span>
  <?php endif; ?>

  <?php if ($top_hulp): ?>
    <button type="button" class="bh-hulpknop" id="hulpknop" aria-expanded="false"
            aria-controls="hulppaneel" aria-label="Uitleg over dit scherm"
            title="Uitleg over dit scherm">?</button>
  <?php endif; ?>
</header>

<?php if ($top_hulp): ?>
<div id="hulppaneel" class="bh-hulppaneel" role="region" aria-label="Uitleg over dit scherm" hidden>
  <div class="bh-hulppaneel-kop">
    <strong><?= icoon('info', 16) ?> <?= h($top_hulp['titel']) ?></strong>
    <button type="button" class="bh-hulppaneel-sluit" aria-label="Sluiten"><?= icoon('kruis', 16) ?></button>
  </div>
  <p><?= h($top_hulp['tekst']) ?></p>
  <?php if (!empty($top_hulp['url'])): ?>
    <a href="<?= h($top_hulp['url']) ?>" target="_blank" rel="noopener">
      <?= icoon('document', 14) ?> Lees meer in de handleiding
    </a>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php
$top_titel = 'Beheer'; $top_seizoen = null; $top_hulp = null;
