<?php
/**
 * Partial: de cellen van één sollicitant-rij (binnen admin/_tabel.php).
 *
 * De kop, het grid, de selectie en de mobiele omslag zitten in _tabel.php; hier staat
 * alleen wát er in de cellen komt. De celvolgorde volgt $tabel_kolommen, zodat de
 * kolommenkiezer klopt zonder dat deze partial dat hoeft te weten.
 *
 * Verwacht (gezet door _tabel.php):
 *   $rij             array   de sollicitatie + persoon
 *   $tabel_kolommen  array   zichtbare kolomsleutels, in volgorde
 *
 * Een naam is overal een link naar het dossier (ontwerpfilosofie §5) — nergens een
 * apart "Bekijk"-knopje.
 */
$_url  = APP_URL . '/admin/sollicitant.php?id=' . (int)($rij['id'] ?? 0);
$_dim  = ($rij['fase'] ?? '') === 'afgewezen';
$_att  = bh_rij_attentie($rij);
$_act  = bh_fase_actie((string)($rij['fase'] ?? ''));

foreach ($tabel_kolommen as $_kol):
    switch ($_kol):

    case 'naam': ?>
      <td data-label="Naam">
        <a class="bh-naamcel" href="<?= h($_url) ?>">
          <?= bh_avatar($rij, 30, ['dim' => $_dim]) ?>
          <span class="bh-naamcel-tekst">
            <span class="bh-naamcel-naam"><?= h(volledige_naam($rij)) ?></span>
            <span class="bh-naamcel-sub"><?= h((string)($rij['email'] ?? '')) ?></span>
          </span>
        </a>
      </td>
    <?php break;

    case 'leeftijd': ?>
      <td data-label="Leeftijd" class="bh-num"><?= h(leeftijd($rij['geboortedatum'] ?? null)) ?></td>
    <?php break;

    case 'woonplaats': ?>
      <td data-label="Woonplaats"><?= h((string)($rij['woonplaats'] ?? '—')) ?></td>
    <?php break;

    case 'telefoon': ?>
      <td data-label="Telefoon" class="bh-num"><?= h((string)($rij['telefoon'] ?: '—')) ?></td>
    <?php break;

    case 'status': ?>
      <td data-label="Status">
        <span class="bh-statuscel">
          <?= bh_fase_badge((string)($rij['fase'] ?? ''), ['lengte' => 'kort']) ?>
          <?php if (!empty($rij['terugkerend'])): ?><?= bh_chip('terug', 'paars') ?><?php endif; ?>
          <?php if ($_att): ?>
            <?php // Kort in de cel, de volle reden als tooltip — anders duwt deze regel de
                  // datumkolom weg. Hij staat voluit in het dossier en in het berichtenlog. ?>
            <span class="bh-attentie" title="<?= h($_att['uitleg']) ?>">
              <?= icoon('waarschuwing', 13) ?><?= h($_att['kort']) ?>
            </span>
          <?php endif; ?>
        </span>
      </td>
    <?php break;

    case 'aangemeld': ?>
      <td data-label="Aangemeld" class="bh-num"><?= h(datum_nl($rij['aangemeld_op'] ?? null)) ?></td>
    <?php break;

    case 'actie': ?>
      <?php // Geen "Bekijk"-knop: de naam ís de link naar het dossier (ontwerpfilosofie §5,
            // "nergens een apart Bekijk-knopje dat op het ene scherm wel en het andere niet
            // bestaat"). Hier staat alleen wat je met deze kandidaat kunt dóen. ?>
      <td data-label="Actie" class="bh-rechts">
        <span class="bh-acties">
          <?php if ($_att): ?>
            <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm"
                    title="<?= h($_att['uitleg']) ?>"><?= h($_att['label']) ?></button>
          <?php endif; ?>
          <?php if ($_act): ?>
            <button type="submit" name="rij_actie" value="<?= h($_act['actie'] . ':' . (int)$rij['id']) ?>"
                    class="ds-btn ds-btn-primair ds-btn-sm"><?= h(bh_fase_actie_kort($_act)) ?></button>
          <?php endif; ?>
        </span>
      </td>
    <?php break;

    default: ?>
      <td data-label="<?= h($_kol) ?>"><?= h((string)($rij[$_kol] ?? '')) ?></td>
    <?php break;

    endswitch;
endforeach;
