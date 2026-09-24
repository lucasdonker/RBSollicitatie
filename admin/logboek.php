<?php
/**
 * Beheer › Logboek — alle sporen van het systeem in één tijdlijn.
 *
 * Zes bronnen (audit, fouten, berichten, ondertekenen, AVG-opschoning, mislukte logins) naast
 * elkaar, doorzoekbaar op wie/wat/wanneer. Leest alleen; de bronnen zelf houden hun eigen scherm
 * voor de acties die daarbij horen (Foutenlog legen, bericht opnieuw sturen).
 *
 * Recht: module 'logboek' (standaard alleen admin) — het bevat wie-wat-wanneer over personen,
 * inclusief wie een kopie identiteitsbewijs heeft gedownload.
 */
require_once __DIR__ . '/../includes/functions.php';

$pagina_titel = 'Logboek';
$actieve_nav  = 'logboek';
vereis_recht('logboek', 'lezen');

$f          = log_filters_uit_get($_GET);
$pagina     = max(1, (int)($_GET['p'] ?? 1));
$per_pagina = 50;

$bronnen     = log_bronnen_beschikbaar();
$per_bron    = log_tel_per_bron($f);
$totaal      = $f['bron'] !== '' ? ($per_bron[$f['bron']] ?? 0) : array_sum($per_bron);
$max_pagina  = max(1, (int)ceil($totaal / $per_pagina));
$pagina      = min($pagina, $max_pagina);
$regels      = log_zoek($f, $pagina, $per_pagina);
$acties      = log_acties_aanwezig($f['bron']);
$heeft_filter = log_filters_actief($f);

/**
 * Link met de huidige filters, waarvan er een paar overschreven mogen worden.
 * Een lege waarde laat het filter weg, zodat de URL kort blijft.
 * @param string $naar  het doelbestand (het exportscherm leest dezelfde parameters)
 */
$url = function (array $anders = [], string $naar = 'logboek.php') use ($f, $pagina): string {
    $q = array_merge([
        'bron' => $f['bron'], 'wie' => $f['wie'], 'actie' => $f['actie'], 'object' => $f['objecttype'],
        'object_id' => $f['object_id'] ?: '', 'van' => $f['van'], 'tot' => $f['tot'],
        'ernst' => $f['ernst'], 'zoek' => $f['zoek'], 'p' => $pagina,
    ], $anders);
    $q = array_filter($q, function ($v) { return $v !== '' && $v !== 0 && $v !== null; });
    return APP_URL . '/admin/' . $naar . ($q ? '?' . http_build_query($q) : '');
};

require __DIR__ . '/layout_top.php';
?>

<div class="notitie-box bh-lb-notitie">
  <?= icoon('slot', 14) ?>
  Dit overzicht laat zien wie wat wanneer deed, inclusief inzage in persoonsgegevens. Gebruik het om
  een melding te herleiden of verantwoording af te leggen — niet om mensen te volgen. Regels worden
  na <?= h((string)(int)instelling('bewaar.audit_maanden', '12')) ?> maanden automatisch opgeruimd.
</div>

<!-- Bronnen -->
<div class="fase-tabs">
  <a href="<?= h($url(['bron' => '', 'p' => 1])) ?>" class="fase-tab <?= $f['bron'] === '' ? 'actief' : '' ?>">
    Alles <span class="count"><?= number_format(array_sum($per_bron), 0, ',', '.') ?></span>
  </a>
  <?php foreach ($bronnen as $sleutel => $bron): ?>
  <a href="<?= h($url(['bron' => $sleutel, 'p' => 1])) ?>"
     class="fase-tab <?= $f['bron'] === $sleutel ? 'actief' : '' ?>"
     title="<?= h($bron['uitleg']) ?>">
    <?= icoon($bron['icoon'], 14) ?><?= h($bron['label']) ?>
    <span class="count"><?= number_format($per_bron[$sleutel] ?? 0, 0, ',', '.') ?></span>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($f['bron'] !== '' && isset($bronnen[$f['bron']])): ?>
<p class="bh-lb-bronuitleg"><?= h($bronnen[$f['bron']]['uitleg']) ?></p>
<?php endif; ?>

<!-- Filters -->
<div class="ds-kaart">
  <div class="bh-kaartbody bh-kaartbody-compact">
    <form method="get" class="filter-balk" role="search" aria-label="Logboek doorzoeken">
      <?php if ($f['bron'] !== ''): ?><input type="hidden" name="bron" value="<?= h($f['bron']) ?>"><?php endif; ?>

      <div class="zoek-input">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.5" y2="16.5"/>
        </svg>
        <label for="lbZoek" class="sr-only">Vrij zoeken</label>
        <input type="text" id="lbZoek" name="zoek" value="<?= h($f['zoek']) ?>" placeholder="Zoek in details…">
      </div>

      <label for="lbWie" class="sr-only">Wie</label>
      <input type="text" id="lbWie" name="wie" value="<?= h($f['wie']) ?>" placeholder="Wie (naam, e-mail of id)"
             class="bh-lb-filter bh-lb-wie">

      <label for="lbActie" class="sr-only">Actie</label>
      <select id="lbActie" name="actie" class="bh-lb-filter bh-lb-keuze bh-lb-actie">
        <option value="">Alle acties</option>
        <?php foreach ($acties as $a): ?>
        <option value="<?= h($a) ?>" <?= $f['actie'] === $a ? 'selected' : '' ?>><?= h(log_actie_label($a)) ?></option>
        <?php endforeach; ?>
      </select>

      <label for="lbErnst" class="sr-only">Ernst</label>
      <select id="lbErnst" name="ernst" class="bh-lb-filter bh-lb-keuze">
        <option value="">Alles</option>
        <?php foreach (log_ernsten() as $sleutel => $label): ?>
        <option value="<?= h($sleutel) ?>" <?= $f['ernst'] === $sleutel ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>

      <span class="bh-lb-periode">
        <label for="lbVan">Van</label>
        <input type="date" id="lbVan" name="van" value="<?= h($f['van']) ?>" class="bh-lb-datum">
        <label for="lbTot">tot</label>
        <input type="date" id="lbTot" name="tot" value="<?= h($f['tot']) ?>" class="bh-lb-datum">
      </span>

      <?php if ($f['objecttype'] !== '' || $f['object_id'] > 0): ?>
        <input type="hidden" name="object" value="<?= h($f['objecttype']) ?>">
        <input type="hidden" name="object_id" value="<?= (int)$f['object_id'] ?>">
        <span class="ds-badge-fase ds-fase-blauw"><?= h(log_object_tekst(['objecttype' => $f['objecttype'], 'object_id' => $f['object_id']])) ?></span>
      <?php endif; ?>

      <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Filter</button>
      <?php if ($heeft_filter): ?>
        <a href="<?= h($url(['wie' => '', 'actie' => '', 'object' => '', 'object_id' => '', 'van' => '', 'tot' => '', 'ernst' => '', 'zoek' => '', 'p' => 1])) ?>"
           class="bh-lb-wis">Wis filters</a>
      <?php endif; ?>

      <span class="bh-vulling"></span>
      <a class="ds-btn ds-btn-secundair ds-btn-sm" href="<?= h($url(['p' => ''], 'logboek_export.php')) ?>">
        <?= icoon('download', 14) ?> Export (CSV)
      </a>
    </form>
  </div>
</div>

<!-- Tijdlijn -->
<div class="ds-kaart">
  <?php if (!$regels): ?>
    <div class="bh-kaartbody">
      <div class="leeg-scherm">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
          <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
          <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
        </svg>
        <p><?= $heeft_filter ? 'Geen gebeurtenissen met deze filters.' : 'Er is nog niets vastgelegd.' ?></p>
      </div>
    </div>
  <?php else: ?>
    <div class="tabel-wrap">
      <table class="data-tabel">
        <thead>
          <tr>
            <th scope="col">Wanneer</th>
            <th scope="col">Wie</th>
            <th scope="col">Wat</th>
            <th scope="col">Waar</th>
            <th scope="col">Details</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($regels as $r):
            $bron      = $bronnen[$r['bron']] ?? null;
            $ernst     = (string)($r['ernst'] ?? 'info');
            $object    = log_object_tekst($r);
            $details   = log_details_regels($r['details'] ?? null);
        ?>
          <tr>
            <td data-label="Wanneer" class="bh-lb-wanneer">
              <?= h(log_moment_tekst($r['moment'] ?? null)) ?>
              <?php if ($f['bron'] === '' && $bron): ?>
                <br><span class="bh-lb-klein"><?= h($bron['label']) ?></span>
              <?php endif; ?>
            </td>

            <td data-label="Wie">
              <?= h(log_wie_naam($r)) ?>
              <?php if (($r['wie_type'] ?? '') !== 'systeem'):
                  $soort = log_wie_soorten()[(string)($r['wie_type'] ?? '')] ?? null; ?>
                <br><span class="badge <?= h(log_wie_klasse($r['wie_type'] ?? null)) ?>">
                  <?= h($soort ? $soort[0] : (string)($r['wie_type'] ?? '')) ?>
                </span>
              <?php endif; ?>
              <?php if (!empty($r['ip'])): ?>
                <br><span class="bh-lb-klein bh-lb-ip"><?= h($r['ip']) ?></span>
              <?php endif; ?>
            </td>

            <td data-label="Wat">
              <span class="bh-lb-actie-regel">
                <?= icoon(log_actie_icoon($r['actie'] ?? null), 15) ?>
                <?= h(log_actie_label($r['actie'] ?? null)) ?>
              </span>
              <?php if ($ernst !== 'info'): ?>
                <br><span class="badge <?= h(log_ernst_klasse($ernst)) ?>"><?= h(log_ernsten()[$ernst] ?? $ernst) ?></span>
              <?php endif; ?>
            </td>

            <td data-label="Waar">
              <?php if ($object !== ''): ?>
                <a href="<?= h($url(['object' => (string)$r['objecttype'], 'object_id' => (int)($r['object_id'] ?? 0), 'p' => 1])) ?>"
                   class="bh-link-navy" title="Alles van dit object tonen"><?= h($object) ?></a>
              <?php else: ?>
                <span class="bh-tekst-grijs">—</span>
              <?php endif; ?>
            </td>

            <td data-label="Details" class="bh-lb-details">
              <?php if (!$details): ?>
                <span class="bh-tekst-grijs">—</span>
              <?php else: ?>
                <?= implode('<br>', array_map('h', $details)) ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Paginering -->
<?php if ($max_pagina > 1): ?>
<div class="bh-lb-paginering">
  <?php if ($pagina > 1): ?>
    <a href="<?= h($url(['p' => $pagina - 1])) ?>" class="ds-btn ds-btn-secundair ds-btn-sm">&larr; Vorige</a>
  <?php endif; ?>
  <span class="bh-lb-paginatekst">
    Pagina <?= (int)$pagina ?> van <?= (int)$max_pagina ?> &middot; <?= number_format($totaal, 0, ',', '.') ?> gebeurtenissen
  </span>
  <?php if ($pagina < $max_pagina): ?>
    <a href="<?= h($url(['p' => $pagina + 1])) ?>" class="ds-btn ds-btn-secundair ds-btn-sm">Volgende &rarr;</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/layout_bottom.php'; ?>
