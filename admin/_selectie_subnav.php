<?php
/**
 * Partial: de stappen van de selectiedag.
 *
 * Het ontwerp toont de selectiedag als één scherm met tabbladen (Groepen · Inchecken ·
 * Beoordelen · Nabespreking · Bellijst). Achter de schermen zijn het aparte bestanden —
 * ze doen genoeg verschillends om dat te rechtvaardigen — maar voor wie ermee werkt is
 * het één scherm. Deze balk stond eerder in elk van die bestanden apart, met een eigen
 * stukje CSS erbij.
 *
 * Verwacht:
 *   $subnav_actief  string  groepen|inchecken|beoordelen|nabespreking|bellijst
 *   $subnav_datum   ?string optioneel: de datum van de selectiedag, in de kop
 */
$subnav_actief = $subnav_actief ?? '';
$subnav_datum  = $subnav_datum ?? null;

$_stappen = [
    'groepen'      => ['Groepen',      'selectie_indeling.php',      'selectiedag', 'lezen'],
    'inchecken'    => ['Inchecken',    'selectie_inchecken.php',     'selectiedag', 'lezen'],
    'beoordelen'   => ['Beoordelen',   'selectiedag.php',            'selectiedag', 'lezen'],
    // Beslissen en bellen is bestuurswerk: een begeleider ziet deze twee niet.
    'nabespreking' => ['Nabespreking', 'selectie_nabespreking.php',  'selectiedag', 'bewerken'],
    'bellijst'     => ['Bellijst',     'selectie_nabespreking.php?tab=bellijst', 'selectiedag', 'bewerken'],
];
?>
<div class="bh-selectiekop">
  <div class="bh-selectiekop-titel">
    <h2>Selectiedag</h2>
    <?php if ($subnav_datum): ?>
      <span class="bh-meta"><?= h($subnav_datum) ?></span>
    <?php endif; ?>
  </div>
  <nav class="bh-substappen" aria-label="Stappen van de selectiedag">
    <?php foreach ($_stappen as $_k => $_s): ?>
      <?php if (!mag($_s[2], $_s[3])) continue; ?>
      <a class="bh-substap<?= $subnav_actief === $_k ? ' actief' : '' ?>"
         href="<?= APP_URL ?>/admin/<?= $_s[1] ?>"
         <?= $subnav_actief === $_k ? 'aria-current="page"' : '' ?>><?= h($_s[0]) ?></a>
    <?php endforeach; ?>
  </nav>
</div>
<?php
$subnav_actief = ''; $subnav_datum = null;
