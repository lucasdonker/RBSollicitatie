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
 *
 * Opgebouwd uit de gedeelde bouwstenen: _tabs (bronnen), _filterbalk, bh-tabel, _leeg, _paginering.
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
$filters_leeg = ['wie' => '', 'actie' => '', 'object' => '', 'object_id' => '', 'van' => '', 'tot' => '', 'ernst' => '', 'zoek' => '', 'p' => 1];

require __DIR__ . '/layout_top.php';
?>

<div class="ds-melding ds-melding-info bh-mb" role="note">
  <?= icoon('slot', 16) ?>
  <span>Dit overzicht laat zien wie wat wanneer deed, inclusief inzage in persoonsgegevens. Gebruik het om
  een melding te herleiden of verantwoording af te leggen — niet om mensen te volgen. Regels worden
  na <?= h((string)(int)instelling('bewaar.audit_maanden', '12')) ?> maanden automatisch opgeruimd.</span>
</div>

<?php
  // Bronnen als tabs
  $tabs_data = ['tabs' => [], 'vreemd' => [], 'vreemd_aantal' => 0];
  foreach ($bronnen as $_sl => $_br) {
      $tabs_data['tabs'][] = ['sleutel' => $_sl, 'label' => $_br['label'], 'aantal' => $per_bron[$_sl] ?? 0,
                              'kleur' => 'grijs', 'actueel' => true];
  }
  $tabs_actief = $f['bron'] !== '' ? $f['bron'] : 'alle';
  $tabs_url    = fn($s) => $url(['bron' => $s === 'alle' ? '' : $s, 'p' => 1]);
  $tabs_totaal = array_sum($per_bron);
  $tabs_voet   = $f['bron'] !== '' && isset($bronnen[$f['bron']]) ? $bronnen[$f['bron']]['uitleg'] : null;
  $tabs_label  = 'Filter op bron';
  require __DIR__ . '/_tabs.php';

  // Filters
  $_actie_opties = ['' => 'Alle acties'];
  foreach ($acties as $_a) $_actie_opties[$_a] = log_actie_label($_a);
  $filter_velden = [
      ['soort' => 'zoek',  'naam' => 'zoek',  'label' => 'Zoek in details', 'waarde' => $f['zoek'], 'breed' => true],
      ['soort' => 'zoek',  'naam' => 'wie',   'label' => 'Naam, e-mail of id', 'waarde' => $f['wie']],
      ['soort' => 'keuze', 'naam' => 'actie', 'label' => 'Actie', 'waarde' => $f['actie'], 'opties' => $_actie_opties],
      ['soort' => 'keuze', 'naam' => 'ernst', 'label' => 'Ernst', 'waarde' => $f['ernst'], 'opties' => ['' => 'Elke ernst'] + log_ernsten()],
      ['soort' => 'datum', 'naam' => 'van',   'label' => 'Van', 'waarde' => $f['van']],
      ['soort' => 'datum', 'naam' => 'tot',   'label' => 'tot', 'waarde' => $f['tot']],
  ];
  $filter_verborgen = array_filter(['bron' => $f['bron'], 'object' => $f['objecttype'], 'object_id' => $f['object_id'] ?: ''],
                                   fn($v) => $v !== '');
  $filter_wissen = $heeft_filter ? $url($filters_leeg) : null;
  $filter_acties = [['label' => 'Export (CSV)', 'url' => $url(['p' => ''], 'logboek_export.php'), 'icoon' => 'download']];
  require __DIR__ . '/_filterbalk.php';
?>

<?php if ($f['objecttype'] !== '' || $f['object_id'] > 0): ?>
  <p class="bh-tabs-voet">
    <?= icoon('info', 14) ?>
    Alleen: <?= h(log_object_tekst(['objecttype' => $f['objecttype'], 'object_id' => $f['object_id']])) ?>
    <a href="<?= h($url(['object' => '', 'object_id' => '', 'p' => 1])) ?>">alles tonen</a>
  </p>
<?php endif; ?>

<?php if (!$regels): ?>
  <div class="ds-kaart ds-kaart-p">
    <?php
      $leeg_titel = $heeft_filter ? 'Geen gebeurtenissen met deze filters' : 'Er is nog niets vastgelegd';
      $leeg_icoon = 'auditlog';
      $leeg_knop  = $heeft_filter ? ['tekst' => 'Wis filters', 'url' => $url($filters_leeg)] : null;
      require __DIR__ . '/_leeg.php';
    ?>
  </div>
<?php else: ?>
  <div class="bh-tabel-wrap">
    <table class="bh-tabel" style="--bh-tracks:minmax(120px,.8fr) minmax(140px,1fr) minmax(150px,1.1fr) minmax(110px,.9fr) minmax(0,1.6fr)">
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
          $bron    = $bronnen[$r['bron']] ?? null;
          $ernst   = (string)($r['ernst'] ?? 'info');
          $object  = log_object_tekst($r);
          $details = log_details_regels($r['details'] ?? null);
          $wie_type = (string)($r['wie_type'] ?? '');
      ?>
        <tr>
          <td data-label="Wanneer">
            <span class="bh-lb-cel">
              <span class="bh-num"><?= h(log_moment_tekst($r['moment'] ?? null)) ?></span>
              <?php if ($f['bron'] === '' && $bron): ?><span class="bh-meta"><?= h($bron['label']) ?></span><?php endif; ?>
            </span>
          </td>
          <td data-label="Wie">
            <span class="bh-lb-cel">
              <span><?= h(log_wie_naam($r)) ?></span>
              <?php if ($wie_type !== 'systeem'): ?>
                <?= bh_badge(log_wie_soorten()[$wie_type][0] ?? $wie_type, log_wie_kleur($wie_type)) ?>
              <?php endif; ?>
              <?php if (!empty($r['ip'])): ?><span class="bh-meta bh-lb-ip"><?= h($r['ip']) ?></span><?php endif; ?>
            </span>
          </td>
          <td data-label="Wat">
            <span class="bh-lb-cel">
              <span class="bh-lb-actie-regel">
                <?= icoon(log_actie_icoon($r['actie'] ?? null), 15) ?>
                <?= h(log_actie_label($r['actie'] ?? null)) ?>
              </span>
              <?php if ($ernst !== 'info'): ?><?= bh_badge(log_ernsten()[$ernst] ?? $ernst, log_ernst_kleur($ernst)) ?><?php endif; ?>
            </span>
          </td>
          <td data-label="Waar">
            <?php if ($object !== ''): ?>
              <a href="<?= h($url(['object' => (string)$r['objecttype'], 'object_id' => (int)($r['object_id'] ?? 0), 'p' => 1])) ?>"
                 title="Alles van dit object tonen"><?= h($object) ?></a>
            <?php else: ?>
              <span class="bh-meta">—</span>
            <?php endif; ?>
          </td>
          <td data-label="Details">
            <span class="bh-lb-details">
              <?= $details ? implode('<br>', array_map('h', $details)) : '—' ?>
            </span>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php
    $pag_totaal = $totaal; $pag_pagina = $pagina; $pag_per = $per_pagina;
    $pag_url    = fn(int $p) => $url(['p' => $p]);
    require __DIR__ . '/_paginering.php';
  ?>
<?php endif; ?>

<?php require __DIR__ . '/layout_bottom.php'; ?>
