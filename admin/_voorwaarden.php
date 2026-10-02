<?php
/**
 * Partial: logica en voorwaarden (voor gevorderden). Uitleg van de schrijfwijze plus alle velden uit
 * voorwaarde_feiten() (één bron), gegroepeerd en doorzoekbaar. Een klik zet een werkend begin in het
 * veld: in een sjabloon een heel {{als …}}-blok, in een voorwaardeveld alleen de voorwaarde.
 * Invoegen en zoeken doet assets/js/variabelen.js (data-var, data-var-zoek), dus laad dat script.
 *
 * Verwacht:
 *   $vw_soort  'sjabloon' (blokken in een berichttekst) of 'voorwaarde' (een los voorwaardeveld)
 *   $vw_doel   ?string  id van het veld waar een klik in moet (anders: waar je het laatst typte)
 */
$vw_soort = ($vw_soort ?? 'sjabloon') === 'voorwaarde' ? 'voorwaarde' : 'sjabloon';
$vw_doel  = $vw_doel ?? null;
$_vw_groepen = [];
foreach (voorwaarde_feiten() as $_n => $_f) $_vw_groepen[$_f['groep']][$_n] = $_f;

/** Een werkend voorbeeld voor één veld. */
$_vw_voorbeeld = function (string $naam, array $f) use ($vw_soort): string {
    if ($f['soort'] === 'getal')      $vw = $naam . (str_starts_with($naam, 'dagen_tot') ? ' <= 3' : (str_contains($naam, 'dagen') ? ' > 7' : ($naam === 'leeftijd' ? ' < 18' : ' > 0')));
    elseif ($f['soort'] === 'janee')  $vw = $naam;
    else {
        $w = array_keys($f['waarden']);
        $vw = $naam . ' = "' . ($w[1] ?? $w[0]) . '"';
    }
    return $vw_soort === 'sjabloon' ? '{{als ' . $vw . '}} … {{/als}}' : $vw;
};
$_vw_snel = $vw_soort === 'sjabloon'
    ? ['Als … dan'          => '{{als leeftijd < 18}} … {{/als}}',
       'Als … anders'       => '{{als fase = "aangenomen"}} … {{anders}} … {{/als}}',
       'Als … anders als'   => '{{als leeftijd < 16}} … {{anders als leeftijd < 18}} … {{anders}} … {{/als}}',
       'Kies per fase'      => '{{kies fase}}{{geval "aangenomen"}} … {{geval "afgewezen", "afgehaakt"}} … {{anders}} … {{/kies}}']
    : ['Formulier na een week nog open' => 'kennismakingsformulier = "open" en kennismakingsformulier.dagen_open > 7',
       'Minderjarig zonder ouderadres'  => 'leeftijd < 18 en niet ouder_bekend',
       'In een van deze fases'          => 'fase in ("uitgenodigd_selectie", "aangemeld_selectie")'];
$_vw_attr = $vw_doel ? ' data-var-doel="' . h($vw_doel) . '"' : '';
?>
<details class="bh-klap bh-varkiezer" data-varkiezer data-klap-id="voorwaarden-<?= h($vw_soort) ?>">
  <summary>
    <span class="bh-klap-titel"><?= $vw_soort === 'sjabloon' ? 'Logica: als, anders, kies' : 'Velden voor de voorwaarde' ?></span>
    <span class="bh-klap-sam">voor gevorderden</span>
  </summary>
  <div class="bh-klap-inhoud">
    <?php if ($vw_soort === 'sjabloon'): ?>
      <p class="ds-veld-hint">
        Laat een stuk tekst alleen zien als iets klopt. <code>{{als …}}</code> … <code>{{anders als …}}</code> …
        <code>{{anders}}</code> … <code>{{/als}}</code>, of per waarde met <code>{{kies fase}}</code>
        <code>{{geval "aangenomen"}}</code> … <code>{{anders}}</code> … <code>{{/kies}}</code>.
        Een blok op een eigen regel of alinea laat geen lege regel achter.
      </p>
    <?php endif; ?>
    <p class="ds-veld-hint">
      Vergelijk met <code>=</code> <code>!=</code> <code>&lt;</code> <code>&gt;</code> <code>&lt;=</code> <code>&gt;=</code>,
      <code>in ("a", "b")</code>, <code>is leeg</code> of <code>bevat</code>; combineer met <code>en</code>,
      <code>of</code>, <code>niet</code> en haakjes. Tekst staat tussen aanhalingstekens, getallen niet.
      Een variabele werkt ook, bijvoorbeeld <code>ouder_naam is niet leeg</code>.
      Bij het opslaan controleren we de schrijfwijze; een tikfout wordt niet opgeslagen.
    </p>
    <div class="bh-vargroep" data-vargroep>
      <span class="bh-veldlabel">Voorbeelden</span>
      <div class="bh-varchips">
        <?php foreach ($_vw_snel as $_l => $_s): ?>
          <button type="button" class="bh-varchip" data-var="<?= h($_s) ?>"<?= $_vw_attr ?>
                  data-var-tekst="<?= h(mb_strtolower($_l . ' ' . $_s)) ?>"><?= h($_l) ?></button>
        <?php endforeach; ?>
      </div>
    </div>
    <label class="bh-zoek">
      <span class="bh-zoek-icoon"><?= icoon('zoek', 15) ?></span>
      <span class="bh-vw">Zoek een veld</span>
      <input type="search" data-var-zoek placeholder="Zoek, bijvoorbeeld formulier of leeftijd">
    </label>
    <?php foreach ($_vw_groepen as $_g => $_velden): ?>
      <div class="bh-vargroep" data-vargroep>
        <span class="bh-veldlabel"><?= h($_g) ?></span>
        <div class="bh-varchips">
          <?php foreach ($_velden as $_n => $_f):
            $_w = !empty($_f['waarden']) ? implode(' · ', array_keys($_f['waarden'])) : ($_f['soort'] === 'janee' ? 'ja · nee' : 'getal');
          ?>
            <button type="button" class="bh-varchip bh-vwchip" data-var="<?= h($_vw_voorbeeld($_n, $_f)) ?>"<?= $_vw_attr ?>
                    data-var-tekst="<?= h(mb_strtolower($_n . ' ' . $_f['label'] . ' ' . $_w)) ?>">
              <span><?= h($_f['label']) ?> <code><?= h($_n) ?></code></span>
              <span class="bh-vwchip-waarden"><?= h($_w) ?></span>
            </button>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <p class="ds-veld-hint">
      "Dagen open" telt vanaf het moment dat een document of taak klaarstond; bij een formulier vanaf de
      laatste fasewissel. <code>nvt</code> = hoort niet bij deze sollicitant of deze fase.
    </p>
  </div>
</details>
<?php
$vw_soort = null; $vw_doel = null;
