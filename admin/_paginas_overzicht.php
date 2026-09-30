<?php
/**
 * Partial — Landingspagina's › Overzicht: waar komen aanmeldingen vandaan, over alle bronnen heen
 * (elke landingspagina, het formulier op de website en elke campagne).
 *
 * Verwacht: $overzicht (pag_bronnen_overzicht()), $periode (sleutel uit pag_periodes()).
 * Logica: includes/paginas/overzicht.php. De trechter: admin/_paginas_trechter.php.
 */
$_tot = $overzicht['totaal'];
$_pct = fn(?int $v): string => $v === null ? '—' : $v . '%';

$filter_velden = [
    ['soort' => 'keuze', 'naam' => 'periode', 'label' => 'Periode', 'waarde' => $periode, 'opties' => pag_periodes(), 'standaard' => 'seizoen'],
];
require __DIR__ . '/_filterbalk.php';
?>
<dl class="pg-cijfers pg-cijfers-groot pg-overzicht-cijfers">
  <div><dt>Bezoeken</dt><dd><?= (int)$_tot['bezoeken'] ?></dd></div>
  <div><dt>Begonnen aan het formulier</dt><dd><?= (int)$_tot['gestart'] ?></dd></div>
  <div><dt>Aanmeldingen</dt><dd><?= (int)$_tot['aanmeldingen'] ?></dd></div>
  <div><dt>Aangenomen</dt><dd><?= (int)$_tot['aangenomen'] ?></dd></div>
  <div><dt>Aanmeldingen per 100 bezoeken</dt><dd><?= $_tot['conversie'] === null ? '—' : (int)$_tot['conversie'] ?></dd></div>
</dl>

<?php
  $pt_trechter = $overzicht['trechter'];
  $pt_titel = 'Alle bronnen samen: waar haken mensen af?';
  $pt_sub = 'Van bekeken tot verstuurd, voor alle landingspagina\'s en het formulier op de website samen. De percentages zijn ten opzichte van iedereen die aan het formulier begon.';
  $pt_leeg = 'In deze periode is er nog niets gemeten.';
  require __DIR__ . '/_paginas_trechter.php';
?>

<section class="ds-kaart ds-kaart-p pg-bronnen">
  <h2 class="pg-h2">Per bron</h2>
  <p class="pg-sub">Elke landingspagina, het formulier op de website en wat er zonder bron binnenkwam. Kwam iemand via een
    campagnelink (<code>?bron=…</code> of <code>utm_campaign</code>), dan staat de campagne eronder.
    Aangenomen telt wie uit die aanmeldingen nu aangenomen is.</p>
  <?php if (!$overzicht['bronnen']): ?>
    <p class="ds-leeg">In deze periode is er nog niets gemeten en nog niemand aangemeld.</p>
  <?php else: ?>
  <div class="tabel-scroll">
    <table class="pg-tabel pg-brontabel">
      <caption class="pg-verborgen">Bezoeken, aanmeldingen en aangenomen per bron en campagne</caption>
      <thead>
        <tr>
          <th scope="col">Bron</th>
          <th scope="col" class="pg-rechts">Bezoeken</th>
          <th scope="col" class="pg-rechts">Begonnen</th>
          <th scope="col" class="pg-rechts">Aanmeldingen</th>
          <th scope="col" class="pg-rechts">Aangenomen</th>
          <th scope="col" class="pg-rechts">Per 100 bezoeken</th>
          <th scope="col">Grootste afhaak</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($overzicht['bronnen'] as $_b):
          $_soort = ['pagina' => 'landingspagina', 'zonder' => 'direct in het portaal, handmatig of van vóór de meting',
                     'extern' => $_b['bron'] === 'website' ? 'formulier op de website' : 'eigen bron (website of oude link)'][$_b['soort']];
        ?>
        <tr>
          <th scope="row" class="pg-bronrij">
            <?php if ($_b['pagina_id']): ?>
              <a href="<?= APP_URL ?>/admin/paginas_bewerk.php?id=<?= (int)$_b['pagina_id'] ?>"><?= h($_b['label']) ?></a>
            <?php else: ?><?= h($_b['label']) ?><?php endif; ?>
            <span class="pg-bronsoort"><?= h($_soort) ?></span>
          </th>
          <td class="pg-rechts"><?= $_b['soort'] === 'zonder' ? '—' : (int)$_b['bezoeken'] ?></td>
          <td class="pg-rechts"><?= $_b['soort'] === 'zonder' ? '—' : (int)$_b['gestart'] ?></td>
          <td class="pg-rechts"><?= (int)$_b['aanmeldingen'] ?></td>
          <td class="pg-rechts"><?= (int)$_b['aangenomen'] ?></td>
          <td class="pg-rechts"><?= $_b['soort'] === 'zonder' ? '—' : $_pct($_b['conversie']) ?></td>
          <td><?= $_b['soort'] === 'zonder' ? '' : h(pag_bron_afhaak($_b)) ?></td>
        </tr>
          <?php foreach ($_b['campagnes'] as $_c => $_x): ?>
          <tr class="pg-campagnerij">
            <td><span class="pg-verborgen"><?= h($_b['label']) ?>, campagne </span><?= h((string)$_c) ?></td>
            <td class="pg-rechts"><?= (int)$_x['bezoeken'] ?></td>
            <td class="pg-rechts"><?= (int)$_x['gestart'] ?></td>
            <td class="pg-rechts"><?= (int)$_x['aanmeldingen'] ?></td>
            <td class="pg-rechts"><?= (int)$_x['aangenomen'] ?></td>
            <td class="pg-rechts"><?= $_pct($_x['conversie']) ?></td>
            <td><?= h(pag_bron_afhaak($_x)) ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="bh-meta pg-bronnen-voet">Bezoeken tellen één keer per half uur per bezoeker, zonder cookie. Het bezoek aan het formulier op de
    website telt vanaf plugin 2.142. Na 13 maanden verdwijnen de metingen (Instellingen › Bewaren); de aanmeldingen zelf blijven.</p>
  <?php endif; ?>
</section>
