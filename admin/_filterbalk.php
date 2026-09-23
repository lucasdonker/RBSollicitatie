<?php
/**
 * Partial: filterbalk (zoeken, keuzelijsten, acties).
 *
 * Werkt zonder JavaScript: het is een gewoon GET-formulier met een verzendknop.
 * JavaScript maakt het daarna direct — maar de balk mag nooit alléén met JS werken,
 * anders is een scherm stuk zodra er iets misgaat.
 *
 * Verwacht:
 *   $filter_actie   string  form-action (standaard: de huidige pagina)
 *   $filter_velden  array   elk: ['soort'=>'zoek'|'keuze', 'naam','label',
 *                                 'waarde', 'opties'=>[waarde=>label], 'breed'=>bool]
 *   $filter_verborgen array optioneel: [naam => waarde] die in de URL moet blijven
 *                           (bijv. de actieve fase-tab)
 *   $filter_acties  array   optioneel: knoppen rechts, elk ['label','url'|'submit','toon','icoon','titel']
 *                           ('titel' = tooltip op een link-knop)
 *   $filter_wissen  ?string optioneel: URL van "wis alle filters"
 */
$filter_actie     = $filter_actie ?? '';
$filter_velden    = $filter_velden ?? [];
$filter_verborgen = $filter_verborgen ?? [];
$filter_acties    = $filter_acties ?? [];
$filter_wissen    = $filter_wissen ?? null;
?>
<form class="bh-filterbalk" method="get" action="<?= h($filter_actie) ?>" role="search">
  <?php foreach ($filter_verborgen as $_n => $_v): ?>
    <input type="hidden" name="<?= h((string)$_n) ?>" value="<?= h((string)$_v) ?>">
  <?php endforeach; ?>

  <?php foreach ($filter_velden as $_f): ?>
    <?php if (($_f['soort'] ?? 'zoek') === 'zoek'): ?>
      <label class="bh-zoek<?= !empty($_f['breed']) ? ' breed' : '' ?>">
        <span class="bh-zoek-icoon"><?= icoon('zoek', 15) ?></span>
        <span class="bh-vw"><?= h($_f['label'] ?? 'Zoeken') ?></span>
        <input type="search" name="<?= h($_f['naam']) ?>" value="<?= h((string)($_f['waarde'] ?? '')) ?>"
               placeholder="<?= h($_f['label'] ?? 'Zoeken') ?>">
      </label>
    <?php else: ?>
      <label class="bh-filter-keuze">
        <span class="bh-vw"><?= h($_f['label'] ?? '') ?></span>
        <select name="<?= h($_f['naam']) ?>" class="ds-select">
          <?php foreach (($_f['opties'] ?? []) as $_w => $_l): ?>
            <option value="<?= h((string)$_w) ?>"<?= (string)($_f['waarde'] ?? '') === (string)$_w ? ' selected' : '' ?>>
              <?= h((string)$_l) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
    <?php endif; ?>
  <?php endforeach; ?>

  <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm bh-filter-verzend">Filteren</button>

  <?php if ($filter_wissen): ?>
    <a class="bh-filter-wis" href="<?= h($filter_wissen) ?>">Wis filters</a>
  <?php endif; ?>

  <?php if ($filter_acties): ?>
    <span class="bh-filter-acties">
      <?php foreach ($filter_acties as $_a):
        $_cls = 'ds-btn ds-btn-' . ($_a['toon'] ?? 'secundair') . ' ds-btn-sm';
      ?>
        <?php if (!empty($_a['url'])): ?>
          <a class="<?= $_cls ?>" href="<?= h($_a['url']) ?>"<?= !empty($_a['titel']) ? ' title="' . h($_a['titel']) . '"' : '' ?>>
            <?php if (!empty($_a['icoon'])) echo icoon($_a['icoon'], 15); ?><?= h($_a['label']) ?>
          </a>
        <?php else: ?>
          <button type="submit" class="<?= $_cls ?>" name="<?= h($_a['submit'] ?? 'actie') ?>"
                  value="<?= h($_a['waarde'] ?? '1') ?>">
            <?php if (!empty($_a['icoon'])) echo icoon($_a['icoon'], 15); ?><?= h($_a['label']) ?>
          </button>
        <?php endif; ?>
      <?php endforeach; ?>
    </span>
  <?php endif; ?>
</form>
<?php
$filter_actie = ''; $filter_velden = []; $filter_verborgen = []; $filter_acties = []; $filter_wissen = null;
