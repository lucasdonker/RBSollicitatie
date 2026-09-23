<?php
/**
 * Partial: de norm van deze persoon — hoeveel dagen per week die werkt (bestuursbesluit).
 *
 * Standaard 5; een afwijkende norm (1 t/m 6) is een besluit van het bestuur, met een
 * reden. Opslaan via normOpslaan() in layout_bottom.php → admin/api_norm.php.
 *
 * Bewerken vraagt twee rechten: beschikbaarheid-bewerken (dit scherm) én
 * selectiedag-bewerken, want dat eist api_norm.php. Zonder beide staat de norm er
 * alleen als tekst — geen formulier dat de server daarna weigert.
 *
 * Verwacht:
 *   $norm_sol      int         sollicitatie-id
 *   $norm_profiel  ?array      beschikbaarheid2_van()['profiel'] (norm_dagen, norm_notitie)
 */
$norm_sol     = (int)($norm_sol ?? 0);
$norm_profiel = $norm_profiel ?? null;
if ($norm_sol <= 0) return;

$_nd  = !empty($norm_profiel['norm_dagen']) ? (int)$norm_profiel['norm_dagen'] : null;
$_nn  = trim((string)($norm_profiel['norm_notitie'] ?? ''));
$_nmag = mag('beschikbaarheid', 'bewerken');   // gelijk aan admin/api_norm.php
$_nlabel = $_nd ? $_nd . ' dagen per week (afwijkend)' : '5 dagen per week (standaard)';
?>
<section class="ds-kaart ds-kaart-p">
  <div class="bh-kaartkop"><h2>Norm</h2></div>
  <p class="bh-dossier-tekst" id="normLabel-<?= $norm_sol ?>"><?= h($_nlabel) ?></p>
  <?php if (!$_nmag && $_nn !== ''): ?>
    <p class="bh-meta"><?= h($_nn) ?></p>
  <?php endif; ?>

  <?php if ($_nmag): ?>
  <form class="bh-normform" onsubmit="return normOpslaan(<?= $norm_sol ?>, this)">
    <div class="ds-veld">
      <label for="normDagen-<?= $norm_sol ?>">Norm (dagen per week)</label>
      <select id="normDagen-<?= $norm_sol ?>" class="ds-select">
        <option value=""<?= $_nd === null ? ' selected' : '' ?>>Standaard (5)</option>
        <?php for ($_i = 1; $_i <= 6; $_i++): if ($_i === 5) continue; ?>
          <option value="<?= $_i ?>"<?= $_nd === $_i ? ' selected' : '' ?>><?= $_i ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="ds-veld">
      <label for="normNotitie-<?= $norm_sol ?>">Reden</label>
      <input type="text" id="normNotitie-<?= $norm_sol ?>" class="ds-input" maxlength="300"
             value="<?= h($_nn) ?>" placeholder="Waarom wijkt de norm af?">
    </div>
    <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Norm opslaan</button>
  </form>
  <?php endif; ?>
</section>
<?php
$norm_sol = 0; $norm_profiel = null;
