<?php
/**
 * Partial: filterbalk (zoeken, keuzelijsten, sorteren, acties) + de actieve filters als chips.
 *
 * Werkt zonder JavaScript: het is een gewoon GET-formulier met een verzendknop.
 * JavaScript maakt het daarna direct — maar de balk mag nooit alléén met JS werken,
 * anders is een scherm stuk zodra er iets misgaat.
 *
 * Verwacht:
 *   $filter_actie   string  form-action (standaard: de huidige pagina)
 *   $filter_velden  array   elk: ['soort'=>'zoek'|'keuze'|'sorteer'|'datum', 'naam','label',
 *                                 'waarde', 'opties'=>[waarde=>label], 'breed'=>bool,
 *                                 'meer'=>bool, 'standaard'=>?string]
 *                           sorteer = keuzelijst voor de volgorde: telt niet als filter en krijgt
 *                                     geen chip (ook op een telefoon bruikbaar, waar je niet op een
 *                                     kolomkop tikt).
 *                           meer    = staat in het inklapbare "Meer filters", zodat de balk rustig
 *                                     blijft; een actief filter is daar alsnog als chip te zien.
 *                           standaard = de waarde die "geen filter" betekent (anders: de eerste optie).
 *   $filter_verborgen array optioneel: [naam => waarde] die in de URL moet blijven
 *                           (bijv. de actieve fase-tab)
 *   $filter_acties  array   optioneel: knoppen rechts, elk ['label','url'|'submit','toon','icoon','titel']
 *                           ('titel' = tooltip op een link-knop), of een menu:
 *                           ['label','icoon','menu' => [['label','url','icoon','titel'], …]] — voor wat je
 *                           zelden doet (importeren, exporteren), zodat de balk rustig blijft
 *   $filter_wissen  ?string optioneel: URL van "wis alle filters"
 *   $filter_telling ?string optioneel: resultaat in woorden, bijv. "12 van 30"
 */
$filter_actie     = $filter_actie ?? '';
$filter_velden    = $filter_velden ?? [];
$filter_verborgen = $filter_verborgen ?? [];
$filter_acties    = $filter_acties ?? [];
$filter_wissen    = $filter_wissen ?? null;
$filter_telling   = $filter_telling ?? null;

// Wat staat er nu in de URL, en welke keuzes wijken af van "geen filter"? Daar komen de chips van.
$_fb_params = $filter_verborgen;
foreach ($filter_velden as $_f) {
    $_w = (string)($_f['waarde'] ?? '');
    if ($_w !== '') $_fb_params[$_f['naam']] = $_w;
}
$_fb_basis = $filter_actie !== '' ? $filter_actie : strtok((string)($_SERVER['REQUEST_URI'] ?? ''), '?');
$_fb_chips = [];
$_fb_meer_actief = 0;
foreach ($filter_velden as $_f) {
    $_s = $_f['soort'] ?? 'zoek';
    if ($_s === 'zoek' || $_s === 'sorteer') continue;
    $_w   = (string)($_f['waarde'] ?? '');
    $_opt = $_f['opties'] ?? [];
    $_std = array_key_exists('standaard', $_f) ? (string)$_f['standaard'] : ($_s === 'keuze' ? (string)array_key_first($_opt) : '');
    if ($_w === '' || $_w === $_std) continue;
    $_zonder = $_fb_params;
    unset($_zonder[$_f['naam']]);
    $_tekst = $_s === 'datum' ? trim(($_f['label'] ?? '') . ' ' . datum_kort_nl($_w)) : (string)($_opt[$_w] ?? $_w);
    $_fb_chips[] = ['tekst' => $_tekst, 'url' => $_fb_basis . ($_zonder ? '?' . http_build_query($_zonder) : '')];
    if (!empty($_f['meer'])) $_fb_meer_actief++;
}
$_fb_veld = function (array $_f): void {
    $_s = $_f['soort'] ?? 'zoek';
    if ($_s === 'zoek'): ?>
      <label class="bh-zoek<?= !empty($_f['breed']) ? ' breed' : '' ?>">
        <span class="bh-zoek-icoon"><?= icoon('zoek', 15) ?></span>
        <span class="bh-vw"><?= h($_f['label'] ?? 'Zoeken') ?></span>
        <input type="search" name="<?= h($_f['naam']) ?>" value="<?= h((string)($_f['waarde'] ?? '')) ?>"
               placeholder="<?= h($_f['label'] ?? 'Zoeken') ?>">
      </label>
    <?php elseif ($_s === 'datum'): ?>
      <label class="bh-filter-keuze bh-filter-datum">
        <span><?= h($_f['label'] ?? '') ?></span>
        <input type="date" name="<?= h($_f['naam']) ?>" value="<?= h((string)($_f['waarde'] ?? '')) ?>" class="ds-input">
      </label>
    <?php else: ?>
      <label class="bh-filter-keuze<?= $_s === 'sorteer' ? ' bh-filter-sorteer' : '' ?>">
        <?php if ($_s === 'sorteer'): ?><span class="bh-filter-sorteer-icoon" aria-hidden="true"><?= icoon('lijst', 15) ?></span><?php endif; ?>
        <span class="bh-vw"><?= h($_f['label'] ?? '') ?></span>
        <select name="<?= h($_f['naam']) ?>" class="ds-select">
          <?php foreach (($_f['opties'] ?? []) as $_w => $_l): ?>
            <option value="<?= h((string)$_w) ?>"<?= (string)($_f['waarde'] ?? '') === (string)$_w ? ' selected' : '' ?>>
              <?= h((string)$_l) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
    <?php endif;
};
$_fb_gewoon = array_filter($filter_velden, fn($f) => empty($f['meer']));
$_fb_meer   = array_filter($filter_velden, fn($f) => !empty($f['meer']));
?>
<form class="bh-filterbalk" method="get" action="<?= h($filter_actie) ?>" role="search">
  <?php foreach ($filter_verborgen as $_n => $_v): ?>
    <input type="hidden" name="<?= h((string)$_n) ?>" value="<?= h((string)$_v) ?>">
  <?php endforeach; ?>

  <?php foreach ($_fb_gewoon as $_f) $_fb_veld($_f); ?>

  <?php if ($_fb_meer): ?>
    <details class="bh-filter-meer">
      <summary class="ds-btn ds-btn-secundair ds-btn-sm">
        <?= icoon('instellingen', 15) ?> Meer filters<?php if ($_fb_meer_actief): ?> <span class="bh-num">(<?= $_fb_meer_actief ?>)</span><?php endif; ?>
      </summary>
      <div class="bh-filter-meer-velden">
        <?php foreach ($_fb_meer as $_f) $_fb_veld($_f); ?>
      </div>
    </details>
  <?php endif; ?>

  <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm bh-filter-verzend">Filteren</button>

  <?php if ($filter_wissen && !$_fb_chips): ?>
    <a class="bh-filter-wis" href="<?= h($filter_wissen) ?>">Wis filters</a>
  <?php endif; ?>

  <?php if ($filter_acties): ?>
    <span class="bh-filter-acties">
      <?php foreach ($filter_acties as $_a):
        $_cls = 'ds-btn ds-btn-' . ($_a['toon'] ?? 'secundair') . ' ds-btn-sm';
      ?>
        <?php if (!empty($_a['menu'])): ?>
          <?php $am_label = $_a['label']; $am_icoon = $_a['icoon'] ?? null; $am_items = $_a['menu']; require __DIR__ . '/_actiemenu.php'; ?>
        <?php elseif (!empty($_a['url'])): ?>
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
<?php if ($_fb_chips || $filter_telling !== null): ?>
<div class="bh-filterchips" aria-label="Actieve filters">
  <?php if ($filter_telling !== null): ?><span class="bh-filterchips-telling"><?= h($filter_telling) ?></span><?php endif; ?>
  <?php foreach ($_fb_chips as $_c): ?>
    <a class="bh-filterchip" href="<?= h($_c['url']) ?>" aria-label="Filter <?= h($_c['tekst']) ?> weghalen">
      <?= h($_c['tekst']) ?> <?= icoon('kruis', 12) ?>
    </a>
  <?php endforeach; ?>
  <?php if ($_fb_chips && $filter_wissen): ?>
    <a class="bh-filter-wis" href="<?= h($filter_wissen) ?>">Wis alle filters</a>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php
$filter_actie = ''; $filter_velden = []; $filter_verborgen = []; $filter_acties = []; $filter_wissen = null; $filter_telling = null;
$_fb_veld = null;
