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
 * bh_td() maakt de openingstag: die weet uit de kolomdefinitie of een cel rechts
 * uitlijnt en of hij op telefoon verdwijnt. Zelf een <td> schrijven werkt ook, maar
 * dan vergeet je die klassen — en dan verdwijnt de kolomkop wel en de cel niet.
 *
 * Een naam is overal een link naar het dossier (ontwerpfilosofie §5) — nergens een
 * apart "Bekijk"-knopje.
 */
// De filters gaan mee naar het dossier: daar loop je met ← → door dezelfde selectie
// en staat eronder waar je vandaan kwam (ontwerpfilosofie §5).
$_f_par = function_exists('bh_soll_filter_params') ? bh_soll_filter_params($_GET) : [];
$_url   = APP_URL . '/admin/sollicitant.php?id=' . (int)($rij['id'] ?? 0)
        . ($_f_par ? '&' . http_build_query($_f_par) : '');
$_dim  = ($rij['fase'] ?? '') === 'afgewezen';
$_att  = bh_rij_attentie($rij);
$_act  = bh_fase_actie((string)($rij['fase'] ?? ''));

foreach ($tabel_kolommen as $_kol):
    switch ($_kol):

    case 'naam': ?>
      <?= bh_td('naam') ?>
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
      <?= bh_td('leeftijd', 'bh-num') ?><?= h(leeftijd($rij['geboortedatum'] ?? null)) ?></td>
    <?php break;

    case 'woonplaats': ?>
      <?php
        /* Niet rechtstreeks $rij['woonplaats']: dat is een later toegevoegde kolom en
           formulieren van vóór die splitsing hebben alles in één `adres`-veld staan.
           Die stonden hier op een streepje terwijl er wél een adres was. */
        $_plaats = function_exists('blk_woonplaats') ? blk_woonplaats($rij) : (string)($rij['woonplaats'] ?? '');
      ?>
      <?php // title erbij: een lange plaatsnaam wordt afgekapt en moet dan wel te lezen zijn ?>
      <?= bh_td('woonplaats', '', 'title="' . h($_plaats) . '"') ?>
        <?= h($_plaats !== '' ? $_plaats : '—') ?>
      </td>
    <?php break;

    case 'telefoon': ?>
      <?= bh_td('telefoon', 'bh-num') ?><?= h((string)($rij['telefoon'] ?: '—')) ?></td>
    <?php break;

    case 'status': ?>
      <?= bh_td('status') ?>
        <span class="bh-statuscel">
          <?= bh_fase_badge((string)($rij['fase'] ?? ''), ['lengte' => 'kort']) ?>
          <?php if (!empty($rij['terugkerend'])): ?><?= bh_chip('terug', 'paars') ?><?php endif; ?>
          <?php // Uitnodig-modus "Suggestie": een voorstel, de knop blijft een mensenklik. ?>
          <?php if (bh_uitnodig_suggestie($rij)): ?>
            <?= bh_badge('Uitnodigen?', 'groen', ['titel' => 'Valt binnen de leeftijdsgrenzen voor uitnodigen (Instellingen › Uitnodigen & automatisch)']) ?>
          <?php endif; ?>
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
      <?= bh_td('aangemeld', 'bh-num') ?><?= h(datum_nl($rij['aangemeld_op'] ?? null)) ?></td>
    <?php break;

    case 'actie': ?>
      <?php // Geen "Bekijk"-knop: de naam ís de link naar het dossier (ontwerpfilosofie §5,
            // "nergens een apart Bekijk-knopje dat op het ene scherm wel en het andere niet
            // bestaat"). Hier staat alleen wat je met deze kandidaat kunt dóen. ?>
      <?= bh_td('actie') ?>
        <span class="bh-acties">
          <?php if ($_att && !empty($_att['url'])): ?>
            <?php // Naar de kaart Gegevens corrigeren in het dossier: daar staat het veld. ?>
            <a class="ds-btn ds-btn-secundair ds-btn-sm" href="<?= h($_att['url']) ?>"
               title="<?= h($_att['uitleg']) ?>"><?= h($_att['label']) ?></a>
          <?php endif; ?>
          <?php if ($_act): ?>
            <button type="submit" name="rij_actie" value="<?= h($_act['actie'] . ':' . (int)$rij['id']) ?>"
                    class="ds-btn ds-btn-primair ds-btn-sm"><?= h(bh_fase_actie_kort($_act)) ?></button>
          <?php endif; ?>
        </span>
      </td>
    <?php break;

    // ── Velden uit het kennismakingsformulier ──────────────────
    // Die zijn er twaalf en ze gedragen zich hetzelfde, dus ze krijgen geen twaalf
    // losse takken. Wat per veld verschilt (kop, breedte, recht, of de tekst lang is)
    // staat in blk_kolom_velden().
    case 'blk_ingevuld': ?>
      <?= bh_td('blk_ingevuld', 'bh-num') ?><?= h(datum_nl($rij['blk_ingevuld'] ?? null) ?: '—') ?></td>
    <?php break;

    default:
      $_blkveld = function_exists('blk_kolom_velden') ? (blk_kolom_velden()[$_kol] ?? null) : null;
      $_waarde  = trim((string)($rij[$_kol] ?? ''));
      if ($_blkveld && !empty($_blkveld['lang'])): ?>
        <?php
          /* Een motivatie van tien regels hoort niet in een tabelcel. Afkappen op één
             regel met de volle tekst als tooltip: zo kun je de kolom scannen en toch
             lezen wat er staat. De regelafbrekingen worden spaties, anders staat er
             midden in de cel een lege regel. */
          $_kort = preg_replace('/\s+/u', ' ', $_waarde);
          $_kort = mb_strlen($_kort) > 90 ? mb_substr($_kort, 0, 88) . '…' : $_kort;
        ?>
        <?= bh_td($_kol, 'bh-langcel', $_waarde !== '' ? 'title="' . h($_waarde) . '"' : '') ?>
          <?= h($_kort !== '' ? $_kort : '—') ?>
        </td>
      <?php else: ?>
        <?= bh_td($_kol, '', $_waarde !== '' ? 'title="' . h($_waarde) . '"' : '') ?>
          <?= h($_waarde !== '' ? $_waarde : '—') ?>
        </td>
      <?php endif;
    break;

    endswitch;
endforeach;
