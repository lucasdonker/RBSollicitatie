<?php
/**
 * Partial: paginering met "x – y van z" en een keuze per pagina.
 *
 * Verwacht:
 *   $pag_totaal     int       aantal regels in totaal
 *   $pag_pagina     int       huidige pagina (1-gebaseerd)
 *   $pag_per        int       regels per pagina
 *   $pag_url        callable  fn(int $pagina): string
 *   $pag_per_url    callable  optioneel: fn(int $per): string — vult de keuzelijst
 *   $pag_per_opties array     optioneel (standaard 15/25/50/100)
 *
 * Bij één pagina toont hij alleen het aantal: knoppen die nergens heen gaan zijn ruis.
 */
$pag_totaal     = max(0, (int)($pag_totaal ?? 0));
$pag_per        = max(1, (int)($pag_per ?? 25));
$pag_pagina     = max(1, (int)($pag_pagina ?? 1));
$pag_url        = $pag_url ?? fn(int $p) => '?pagina=' . $p;
$pag_per_url    = $pag_per_url ?? null;
$pag_per_opties = $pag_per_opties ?? [15, 25, 50, 100];

$_paginas = max(1, (int)ceil($pag_totaal / $pag_per));
$pag_pagina = min($pag_pagina, $_paginas);
$_van = $pag_totaal === 0 ? 0 : ($pag_pagina - 1) * $pag_per + 1;
$_tot = min($pag_pagina * $pag_per, $pag_totaal);
?>
<div class="bh-paginering">
  <span class="bh-pag-telling">
    <?php if ($pag_totaal === 0): ?>
      Geen regels
    <?php else: ?>
      <span class="bh-num"><?= $_van ?> – <?= $_tot ?></span> van <span class="bh-num"><?= $pag_totaal ?></span>
    <?php endif; ?>
  </span>

  <?php if ($pag_per_url && $pag_totaal > min($pag_per_opties)): ?>
    <label class="bh-pag-per">
      <span class="bh-vw">Regels per pagina</span>
      <select class="ds-select" onchange="location.href=this.value">
        <?php foreach ($pag_per_opties as $_o): ?>
          <option value="<?= h($pag_per_url($_o)) ?>"<?= $_o === $pag_per ? ' selected' : '' ?>>
            <?= (int)$_o ?> per pagina
          </option>
        <?php endforeach; ?>
      </select>
    </label>
  <?php endif; ?>

  <?php if ($_paginas > 1): ?>
    <span class="bh-pag-knoppen">
      <?php if ($pag_pagina > 1): ?>
        <a class="ds-btn ds-btn-secundair ds-btn-sm" href="<?= h($pag_url($pag_pagina - 1)) ?>"
           rel="prev" aria-label="Vorige pagina">‹ Vorige</a>
      <?php else: ?>
        <span class="ds-btn ds-btn-zacht ds-btn-sm" aria-disabled="true">‹ Vorige</span>
      <?php endif; ?>
      <span class="bh-pag-van">pagina <span class="bh-num"><?= $pag_pagina ?></span> van <span class="bh-num"><?= $_paginas ?></span></span>
      <?php if ($pag_pagina < $_paginas): ?>
        <a class="ds-btn ds-btn-secundair ds-btn-sm" href="<?= h($pag_url($pag_pagina + 1)) ?>"
           rel="next" aria-label="Volgende pagina">Volgende ›</a>
      <?php else: ?>
        <span class="ds-btn ds-btn-zacht ds-btn-sm" aria-disabled="true">Volgende ›</span>
      <?php endif; ?>
    </span>
  <?php endif; ?>
</div>
<?php
$pag_totaal = 0; $pag_pagina = 1; $pag_per = 25; $pag_url = null; $pag_per_url = null; $pag_per_opties = null;
