<?php
/**
 * Tabblad: het volledige kennismakingsformulier (BLK) zoals de sollicitant het invulde.
 *
 * Een leeg veld en een niet-ingevuld veld zijn twee verschillende dingen; dat
 * onderscheid moet zichtbaar blijven. Bijzonderheden krijgen een opvallende opmaak,
 * want dat is precies waar het bestuur naar zoekt.
 *
 * Verwacht: $tab_dossier  array  uitkomst van bh_dossier()
 */
$tab_dossier = $tab_dossier ?? null;
if (!$tab_dossier) return;
$_s   = $tab_dossier['sol'];
$_blk = $tab_dossier['blk'];

if (!$_blk) {
    echo '<div class="ds-kaart" style="padding:16px">';
    $leeg_titel = 'Het formulier is nog niet ingevuld';
    $leeg_tekst = 'Zodra ' . h($_s['voornaam']) . ' het kennismakingsformulier indient, staat het hier.';
    $leeg_icoon = 'document';
    require __DIR__ . '/../_leeg.php';
    echo '</div>';
    return;
}

/** Eén veld, met "Niet ingevuld" als het leeg is. */
$_veld = function (string $label, ?string $waarde, string $toon = '') {
    $w = trim((string)$waarde);
    echo '<div><span class="bh-veldlabel">' . h($label) . '</span>';
    echo '<span class="bh-veldwaarde' . ($w === '' ? ' zacht' : ($toon !== '' ? ' ' . $toon : '')) . '">'
       . ($w === '' ? 'Niet ingevuld' : nl2br(h($w))) . '</span></div>';
};
?>
<div class="bh-formulier-kaarten">

  <section class="ds-kaart ds-kaart-p">
    <div class="bh-kaartkop">
      <h2>Persoonsgegevens</h2>
      <span class="bh-meta">
        ingevuld <?= h(datum_nl(substr((string)($_blk['ingevuld_op'] ?? ''), 0, 10))) ?>
      </span>
    </div>
    <div class="bh-gegevensraster">
      <?php
        $_veld('Naam', volledige_naam($_s));
        $_veld('Geboortedatum', datum_nl($_s['geboortedatum'] ?? null));
        $_veld('E-mail', $_s['email'] ?? null);
        $_veld('Telefoon', $_s['telefoon'] ?? null);
        $_str = trim(($_blk['straat'] ?? '') . ' ' . ($_blk['huisnummer'] ?? ''));
        $_veld('Adres', $_str !== '' ? $_str : (string)($_blk['adres'] ?? ''));
        $_veld('Postcode en plaats', trim(($_blk['postcode'] ?? '') . '  ' . ($_blk['woonplaats'] ?? '')));
      ?>
    </div>
  </section>

  <section class="ds-kaart ds-kaart-p">
    <div class="bh-kaartkop"><h2>Achtergrond</h2></div>
    <div class="bh-gegevensraster twee">
      <?php
        $_veld('Opleiding', $_blk['opleiding'] ?? null);
        $_veld('Werkervaring', $_blk['werkervaring'] ?? null);
      ?>
    </div>
    <div class="bh-dossier-motivatie">
      <span class="bh-veldlabel">Diploma's en certificaten</span>
      <?php $_dip = trim((string)($_blk['diplomas'] ?? '')); ?>
      <?php if ($_dip === ''): ?>
        <span class="bh-veldwaarde zacht">Niet ingevuld</span>
      <?php else: ?>
        <div class="bh-chiprij">
          <?php foreach (preg_split('/[,\n;]+/', $_dip) as $_d): $_d = trim($_d); if ($_d === '') continue; ?>
            <?= bh_chip($_d, 'blauw') ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="ds-kaart ds-kaart-p">
    <div class="bh-kaartkop"><h2>Motivatie</h2></div>
    <div class="bh-dossier-motivatie" style="margin-top:0;padding-top:0;border:none">
      <span class="bh-veldlabel">Waarom strandwacht?</span>
      <p class="bh-dossier-tekst"><?= nl2br(h(trim((string)($_blk['motivatie_lifeguard'] ?? '')) ?: 'Niet ingevuld')) ?></p>
    </div>
    <div class="bh-dossier-motivatie">
      <span class="bh-veldlabel">Waarom juist dit strand?</span>
      <p class="bh-dossier-tekst"><?= nl2br(h(trim((string)($_blk['motivatie_strand'] ?? '')) ?: 'Niet ingevuld')) ?></p>
    </div>
  </section>

  <?php $_bijz = trim((string)($_blk['bijzonderheden'] ?? '')); ?>
  <section class="ds-kaart ds-kaart-p<?= $_bijz !== '' ? ' bh-kaart-let-op' : '' ?>">
    <div class="bh-kaartkop">
      <h2>Bijzonderheden</h2>
      <?php if ($_bijz !== ''): ?><?= bh_badge('Let op', 'rood') ?><?php endif; ?>
    </div>
    <p class="bh-dossier-tekst<?= $_bijz === '' ? ' zacht' : '' ?>">
      <?= $_bijz === '' ? 'Niets opgegeven' : nl2br(h($_bijz)) ?>
    </p>
    <?php $_sm = trim((string)($_blk['social_media'] ?? '')); ?>
    <?php if ($_sm !== ''): ?>
      <div class="bh-dossier-motivatie">
        <span class="bh-veldlabel">Social media</span>
        <span class="bh-veldwaarde"><?= h($_sm) ?></span>
      </div>
    <?php endif; ?>
  </section>

  <div class="bh-formulier-voet">
    <a class="ds-btn ds-btn-secundair ds-btn-sm"
       href="<?= APP_URL ?>/admin/blk_detail.php?id=<?= (int)$_s['id'] ?>">
      <?= icoon('printer', 15) ?> Afdrukweergave en wijzigingsgeschiedenis
    </a>
  </div>
</div>
<?php
$tab_dossier = null;
