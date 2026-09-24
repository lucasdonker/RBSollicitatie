<?php
/**
 * Tabblad: de documenten die ondertekend moeten worden.
 *
 * De leeftijdsregel is juridisch en moet zichtbaar zijn: vanaf 18 tekent de sollicitant
 * zelf, daaronder tekent een ouder mee. Dat is een andere grens dan de 16 bij de
 * aanmelding — die twee nooit samenvoegen.
 *
 * Gezondheidsverklaringen bevatten medische gegevens: alleen bestuur en hoger mogen de
 * verzegelde PDF openen. Wie dat niet mag, ziet dat het bestaat maar niet wat erin staat.
 *
 * Verwacht: $tab_dossier  array  uitkomst van bh_dossier()
 */
$tab_dossier = $tab_dossier ?? null;
if (!$tab_dossier) return;

$_s   = $tab_dossier['sol'];
$_doc = bh_dossier_documenten($tab_dossier);
$_mag_bewerken = mag('ondertekeningen', 'bewerken');
?>
<div class="bh-dossier-kolommen">
  <div class="bh-dossier-hoofd">

    <?php if ($tab_dossier['minderjarig']): ?>
      <?php
        $_ouder_bekend = !empty($_s['ouder_email']) || !empty($_s['ouder_naam']);
      ?>
      <div class="ds-melding <?= $_ouder_bekend ? 'ds-melding-info' : 'ds-melding-warn' ?>">
        <?= icoon($_ouder_bekend ? 'ouders' : 'waarschuwing', 16) ?>
        <span>
          <?php if ($_ouder_bekend): ?>
            <strong><?= h($_s['voornaam']) ?> is nog geen 18.</strong>
            Een ouder of verzorger tekent mee:
            <?= h(trim((string)($_s['ouder_naam'] ?? ''))) ?>
            <?php if (!empty($_s['ouder_email'])): ?>· <?= h((string)$_s['ouder_email']) ?><?php endif; ?>
            <?php if (!empty($_s['ouder_telefoon'])): ?>· <?= h((string)$_s['ouder_telefoon']) ?><?php endif; ?>
          <?php else: ?>
            <strong><?= h($_s['voornaam']) ?> is nog geen 18 en er is nog geen ouder bekend.</strong>
            De ouderformulieren kunnen pas uitgaan zodra er een naam en e-mailadres van een
            ouder of verzorger is ingevuld.
          <?php endif; ?>
        </span>
      </div>
    <?php endif; ?>

    <section class="ds-kaart ds-kaart-p">
      <div class="bh-kaartkop">
        <h2>Documenten</h2>
        <span class="bh-meta bh-num"><?= $_doc['gedaan'] ?> van <?= $_doc['totaal'] ?> ondertekend</span>
      </div>
      <?= bh_voortgang($_doc['gedaan'], $_doc['totaal']) ?>

      <ul class="bh-ondlijst">
        <?php foreach ($_doc['rijen'] as $_r): ?>
          <li class="<?= $_r['klaar'] ? 'klaar' : 'open' ?>">
            <span class="bh-ond-status">
              <?= $_r['klaar'] ? bh_badge('Ondertekend', 'groen')
                               : bh_badge(($_r['rol'] ?? '') === 'ouder' ? 'Wacht op ouder' : 'Wacht', 'oranje') ?>
            </span>
            <span class="bh-ond-naam">
              <?= h($_r['label']) ?>
              <?php if ($_r['klaar']): ?>
                <span class="bh-ond-sub">
                  <?= h(datum_nl(substr((string)$_r['op'], 0, 10))) ?>
                  <?php if (!empty($_r['door'])): ?> · <?= h((string)$_r['door']) ?><?php endif; ?>
                </span>
              <?php endif; ?>
            </span>
            <span class="bh-ond-acties">
              <?php if ($_r['klaar'] && $_r['id']): ?>
                <?php $_toegang = bh_mag_document((string)$_r['type']); ?>
                <?php if (!$_toegang[0]): ?>
                  <span class="bh-ond-slot" title="Dit document bevat gegevens die niet voor iedereen zijn">
                    <?= icoon('slot', 14) ?> <?= h((string)$_toegang[1]) ?>
                  </span>
                <?php else: ?>
                  <a class="ds-btn ds-btn-secundair ds-btn-sm"
                     href="<?= APP_URL ?>/admin/document_download.php?ondertekening=<?= (int)$_r['id'] ?>">
                    <?= icoon('download', 14) ?> PDF
                  </a>
                <?php endif; ?>
              <?php elseif (!$_r['klaar'] && $_r['token'] && $_mag_bewerken): ?>
                <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm"
                        onclick="kopieerOndertekenlink('<?= h((string)$_r['token']) ?>', this)">
                  Link kopiëren
                </button>
              <?php elseif (!$_r['klaar']): ?>
                <span class="bh-meta">nog niet klaargezet</span>
              <?php endif; ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  </div>

  <aside class="bh-dossier-zij">
    <section class="ds-kaart ds-kaart-p">
      <div class="bh-kaartkop"><h2>Hoe dit werkt</h2></div>
      <p class="bh-dossier-tekst">
        Ondertekenen gebeurt in het portaal, met een persoonlijke link. Die link gaat
        automatisch mee met de uitnodiging; lukt dat niet, dan kun je hem hier kopiëren
        en zelf sturen.
      </p>
      <p class="bh-dossier-tekst bh-dossier-besluittekst">
        Elk ondertekend document wordt verzegeld opgeslagen en is daarna niet meer te
        wijzigen. Wie wat wanneer opende staat in het logboek.
      </p>
    </section>
  </aside>
</div>
<?php
$tab_dossier = null;
