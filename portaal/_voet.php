<?php
/**
 * Gedeelde portaal-voet. Sluit de pagina met de navigatiebalk (één keer, hier gedefinieerd)
 * en het gedeelde script. De actieve tab komt uit $portaal_nav.
 *
 * De navigatie verschijnt alleen als de sollicitant is ingelogd én $portaal_geen_nav niet
 * gezet is (bijv. op het inlogscherm). Het telbolletje op "Taken" toont $portaal_open_taken
 * (aantal open taken) als de pagina dat heeft gezet en het groter is dan nul.
 */
$portaal_geen_nav = $portaal_geen_nav ?? false;
$_nav = $portaal_nav ?? '';
$_toon_nav = !$portaal_geen_nav && function_exists('portaal_ingelogd') && portaal_ingelogd();
$_open = isset($portaal_open_taken) ? (int)$portaal_open_taken
       : (($_toon_nav && function_exists('portaal_taken_open_aantal')) ? portaal_taken_open_aantal((int)$_SESSION['portaal_sol_id']) : 0);
?>
  </main>

<?php if ($_toon_nav): ?>
  <nav class="pt-nav" aria-label="Hoofdnavigatie">
    <div class="pt-nav-merk">
      <span class="pt-merk-logo"><?= icoon('golf', 18) ?></span>
      <div class="pt-merk-tekst">
        <span class="pt-merk-naam">Paal 13</span>
        <span class="pt-merk-sub"><?php $sz = function_exists('actief_seizoen') ? actief_seizoen() : null; echo $sz ? h($sz['naam']) : 'Sollicitatie 2026'; ?></span>
      </div>
    </div>
    <div class="pt-nav-items">
      <a href="<?= APP_URL ?>/portaal/" class="pt-nav-item <?= $_nav==='start'?'actief':'' ?>">
        <?= icoon('huis', 22) ?><span>Start</span>
      </a>
      <a href="<?= APP_URL ?>/portaal/taken.php" class="pt-nav-item <?= $_nav==='taken'?'actief':'' ?>">
        <span class="pt-nav-icoon-wrap"><?= icoon('lijst', 22) ?><?php if ($_open > 0): ?><span class="pt-nav-badge"><?= $_open ?></span><?php endif; ?></span>
        <span>Taken</span>
      </a>
      <a href="<?= APP_URL ?>/portaal/gegevens.php" class="pt-nav-item <?= $_nav==='gegevens'?'actief':'' ?>">
        <?= icoon('persoon', 22) ?><span>Gegevens</span>
      </a>
    </div>
  </nav>
<?php endif; ?>

<script src="<?= APP_URL ?>/assets/js/app.js?v=<?= @filemtime(__DIR__.'/../assets/js/app.js') ?>"></script>
</body>
</html>
