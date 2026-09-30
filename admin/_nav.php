<?php
/**
 * Partial: de zijbalk.
 *
 * Vier groepen met "Vandaag" los bovenaan. Onderin altijd wie je bent, hoe je bent
 * ingelogd en uitloggen (ontwerpfilosofie §11). Op de testomgeving een badge, zodat
 * niemand denkt in productie te werken.
 *
 * Deze zijbalk moet er net zo goed uitzien met twee items als met elf — de
 * penningmeester ziet er drie. Groepen zonder zichtbaar item verdwijnen vanzelf.
 *
 * Verwacht:
 *   $nav          array   uitkomst van bh_nav_groepen()
 *   $nav_actief   string  genormaliseerde sleutel (bh_nav_actief())
 *   $nav_bhd      array   huidige_beheerder()
 *   $nav_portaal  bool    toon de wissel naar het eigen sollicitatiedossier
 */
$nav         = $nav ?? ['los' => [], 'groepen' => []];
$nav_actief  = $nav_actief ?? '';
$nav_bhd     = $nav_bhd ?? ['naam' => '', 'rol' => '', 'via' => ''];
$nav_portaal = $nav_portaal ?? false;

/** Eén nav-item. */
$_item = function (array $i) use ($nav_actief) {
    $actief = $nav_actief === $i['id'];
    echo '<a href="' . h($i['url']) . '" class="bh-nav-link' . ($actief ? ' actief' : '') . '"'
       . ($actief ? ' aria-current="page"' : '') . ' data-sluit-nav>'
       . icoon($i['icoon'], 17)
       . '<span class="bh-nav-label">' . h($i['label']) . '</span>';
    if (!empty($i['teller'])) {
        echo '<span class="bh-nav-teller">' . (int)$i['teller'] . '</span>';
    }
    echo '</a>';
};
?>
<nav class="bh-zijbalk" id="zijbalk" aria-label="Hoofdmenu">

  <div class="bh-zijbalk-kop">
    <a class="bh-merk" href="<?= APP_URL ?>/admin/">
      <?php $_logo = function_exists('site_logo_url') ? site_logo_url() : null; ?>
      <?php if ($_logo): ?>
        <img src="<?= h($_logo) ?>" alt="Paal 13"
             data-terugval="<?= APP_URL ?>/assets/img/logo.svg">
      <?php else: ?>
        <span class="bh-merk-tegel"><?= icoon('golf', 15) ?></span>
        <span class="bh-merk-naam">Paal 13 Beheer</span>
      <?php endif; ?>
    </a>
    <?php if (function_exists('is_test_omgeving') && is_test_omgeving()): ?>
      <span class="bh-testbadge"><?= icoon('waarschuwing', 12) ?>Testomgeving</span>
    <?php endif; ?>
  </div>

  <div class="bh-zijbalk-lijst">
    <?php foreach ($nav['los'] as $_i) $_item($_i); ?>

    <?php foreach ($nav['groepen'] as $_groep => $_items): ?>
      <div class="bh-nav-groep">
        <div class="bh-nav-groepkop"><?= h($_groep) ?></div>
        <?php foreach ($_items as $_i) $_item($_i); ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="bh-zijbalk-voet">
    <a class="bh-gebruiker" href="<?= APP_URL ?>/admin/profiel.php">
      <?= bh_avatar(['voornaam' => $nav_bhd['naam'], 'achternaam' => ''], 30) ?>
      <span class="bh-gebruiker-tekst">
        <span class="bh-gebruiker-naam"><?= h($nav_bhd['naam']) ?></span>
        <span class="bh-gebruiker-rol">
          <?= h(implode(' + ', array_map('rol_naam', huidige_rollen()))) ?>
          · <?= $nav_bhd['via'] === 'authentik' ? 'SSO' : 'wachtwoord' ?>
        </span>
      </span>
    </a>
    <div class="bh-zijbalk-acties">
      <?php /* Vast, voor iedere rol: wie nieuw is weet nog niet dat het vraagteken bestaat. */ ?>
      <a href="<?= APP_URL ?>/admin/handleiding.php"><?= icoon('rondleiding', 14) ?>Hulp en handleidingen</a>
      <?php if ($nav_portaal): ?>
        <a href="<?= APP_URL ?>/wissel.php?naar=portaal"><?= icoon('wissel', 14) ?>Mijn sollicitatie</a>
      <?php endif; ?>
      <a href="<?= APP_URL ?>/admin/uitloggen.php"><?= icoon('uitloggen', 14) ?>Uitloggen</a>
    </div>
  </div>
</nav>
<?php
$nav = null; $nav_actief = ''; $nav_bhd = null; $nav_portaal = false;
