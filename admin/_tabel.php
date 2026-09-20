<?php
/**
 * Partial: brede datatabel die op telefoon rijen wordt.
 *
 * Een echte <table> met het grid van het ontwerp eroverheen: zo blijven <th scope>
 * en de schermlezer-semantiek intact, terwijl de kolombreedtes uit één custom
 * property komen (--bh-tracks). De kolommenkiezer herschrijft alleen die waarde.
 *
 * Verwacht:
 *   $tabel        array     uitkomst van bh_kolommen()
 *   $tabel_rijen  iterable  de rijen
 *   $tabel_rij    string    pad naar de partial die de cellen van één rij tekent.
 *                           Die krijgt $rij en $tabel_kolommen (zichtbare sleutels).
 *   $tabel_rij_id callable  optioneel: fn(array $rij): int|string — vult data-id
 *   $tabel_rij_cls callable optioneel: fn(array $rij): string — extra rijklasse
 *                           ('bh-rij-gemarkeerd', 'bh-rij-gedempt')
 *   $tabel_selectie bool    optioneel: selectievakje per rij (name="ids[]")
 *   $tabel_leeg   array     optioneel: ['titel','tekst','toon','icoon'] voor de lege staat
 *
 * Ruimt zijn vlaggen op, zodat twee tabellen op één pagina elkaar niet besmetten.
 */
$tabel          = $tabel ?? null;
$tabel_rijen    = $tabel_rijen ?? [];
$tabel_rij      = $tabel_rij ?? '';
$tabel_rij_id   = $tabel_rij_id ?? null;
$tabel_rij_cls  = $tabel_rij_cls ?? null;
$tabel_selectie = $tabel_selectie ?? false;
$tabel_leeg     = $tabel_leeg ?? [];

if ($tabel && $tabel_rij !== ''):
    $_zicht  = $tabel['zichtbaar'];
    $_tracks = ($tabel_selectie ? '34px ' : '') . $tabel['tracks'];
    $_rijen  = is_array($tabel_rijen) ? $tabel_rijen : iterator_to_array($tabel_rijen);
?>
<?php if (!$_rijen): ?>
  <div class="ds-kaart" style="padding:16px">
    <?php
      $leeg_titel = $tabel_leeg['titel'] ?? 'Niets gevonden';
      $leeg_tekst = $tabel_leeg['tekst'] ?? '';
      $leeg_toon  = $tabel_leeg['toon'] ?? 'grijs';
      $leeg_icoon = $tabel_leeg['icoon'] ?? 'lijst';
      require __DIR__ . '/_leeg.php';
    ?>
  </div>
<?php else: ?>
<table class="bh-tabel" id="tabel-<?= h($tabel['id']) ?>" data-tabel="<?= h($tabel['id']) ?>"
       style="--bh-tracks:<?= h($_tracks) ?>">
  <thead>
    <tr>
      <?php if ($tabel_selectie): ?>
        <th scope="col" class="bh-kol-kies">
          <input type="checkbox" class="bh-kies-alles" aria-label="Alles selecteren">
        </th>
      <?php endif; ?>
      <?php foreach ($_zicht as $_s):
        $_k     = $tabel['kolommen'][$_s];
        $_url   = bh_sorteer_url($tabel, $_s);
        $_stand = bh_sorteer_stand($tabel, $_s);
        $_cls   = ($_k['uitlijn'] === 'rechts' ? 'bh-rechts ' : '')
                . ($_k['mobiel'] === 'verberg' ? 'bh-geen-mobiel ' : '');
      ?>
        <th scope="col" class="<?= h(trim($_cls)) ?>" data-track="<?= h($_k['track']) ?>"
            data-kolom="<?= h($_s) ?>"<?= $_stand ? ' aria-sort="' . $_stand . '"' : '' ?>>
          <?php if ($_url): ?>
            <a href="<?= h($_url) ?>"><?= h($_k['kop']) ?><?php
              if ($_stand) echo icoon($_stand === 'ascending' ? 'chevron' : 'chevron', 12);
            ?></a>
          <?php else: ?>
            <?= h($_k['kop']) ?>
          <?php endif; ?>
        </th>
      <?php endforeach; ?>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($_rijen as $rij):
      $_id  = $tabel_rij_id ? $tabel_rij_id($rij) : ($rij['id'] ?? '');
      $_rcl = $tabel_rij_cls ? trim((string)$tabel_rij_cls($rij)) : '';
      $tabel_kolommen = $_zicht;
    ?>
    <tr<?= $_id !== '' ? ' data-id="' . h((string)$_id) . '"' : '' ?><?= $_rcl ? ' class="' . h($_rcl) . '"' : '' ?>>
      <?php if ($tabel_selectie): ?>
        <td class="bh-kol-kies">
          <input type="checkbox" name="ids[]" value="<?= h((string)$_id) ?>" class="bh-kies"
                 aria-label="Selecteer deze regel">
        </td>
      <?php endif; ?>
      <?php require $tabel_rij; ?>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php endif; ?>
<?php
$tabel = null; $tabel_rijen = []; $tabel_rij = ''; $tabel_rij_id = null;
$tabel_rij_cls = null; $tabel_selectie = false; $tabel_leeg = [];
