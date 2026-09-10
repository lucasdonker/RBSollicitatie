<?php
/**
 * Gedeelde portaal-voet. Sluit de pagina met de navigatiebalk (één keer, hier gedefinieerd)
 * en het gedeelde script. De actieve tab komt uit $portaal_nav.
 *
 * De navigatie verschijnt alleen als de sollicitant is ingelogd én we niet op een
 * "terug"-subpagina zitten met een eigen kop (dan zou een tabbalk verwarren). Dat regelt
 * $portaal_geen_nav (zet die op true om de balk te verbergen, bijv. op het inlogscherm).
 */
$portaal_geen_nav = $portaal_geen_nav ?? false;
$_nav = $portaal_nav ?? '';
$_toon_nav = !$portaal_geen_nav && function_exists('portaal_ingelogd') && portaal_ingelogd();
?>
  </main>

<?php if ($_toon_nav): ?>
  <nav class="pt-nav" aria-label="Hoofdnavigatie">
    <a href="<?= APP_URL ?>/portaal/" class="pt-nav-item <?= $_nav==='start'?'actief':'' ?>">
      <?= icoon('huis', 22) ?><span>Start</span>
    </a>
    <a href="<?= APP_URL ?>/portaal/?tab=taken" class="pt-nav-item <?= $_nav==='taken'?'actief':'' ?>">
      <?= icoon('lijst', 22) ?><span>Taken</span>
    </a>
    <a href="<?= APP_URL ?>/portaal/gegevens.php" class="pt-nav-item <?= $_nav==='gegevens'?'actief':'' ?>">
      <?= icoon('persoon', 22) ?><span>Gegevens</span>
    </a>
  </nav>
<?php endif; ?>

<script src="<?= APP_URL ?>/assets/js/app.js?v=<?= @filemtime(__DIR__.'/../assets/js/app.js') ?>"></script>
</body>
</html>
