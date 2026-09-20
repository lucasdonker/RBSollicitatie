<?php
/**
 * Levende bouwstenenpagina: elke beheer-bouwsteen in elke stand, naast elkaar.
 *
 * Waarvoor: één plek om te zien of een wijziging aan een bouwsteen overal klopt —
 * in licht én donker, op telefoon én desktop. Bouw je een nieuwe bouwsteen, zet hem
 * hier ook neer; anders ontdek je een kapotte stand pas op een echt scherm.
 *
 * Alleen voor de beheerder: het is gereedschap, geen beheerscherm. Toont geen echte
 * gegevens, alleen voorbeelden.
 */
require_once __DIR__ . '/../includes/functions.php';
vereist_rol('admin');
$pagina_titel = 'Bouwstenen';
$actieve_nav  = 'systeem';
require __DIR__ . '/layout_top.php';

/** Voorbeeldpersonen voor de avatars (geen echte gegevens). */
$_p = [
    ['voornaam' => 'Zoë', 'achternaam' => 'Meijer'],
    ['voornaam' => 'Sem', 'achternaam' => 'Bakker'],
    ['voornaam' => '',    'achternaam' => ''],
];
?>

<div class="ds-melding ds-melding-info" style="margin-bottom:20px">
  <?= icoon('info', 16) ?>
  <span>Dit is gereedschap, geen beheerscherm. Wijzig je een bouwsteen, controleer hem hier
        in beide modi en op telefoonbreedte.</span>
</div>

<div class="ds-sectie-titel">Statusbadge — acht fases</div>
<div class="ds-kaart ds-kaart-p" style="display:flex;gap:8px;flex-wrap:wrap">
  <?php foreach (fasen() as $_f => $_lbl): ?>
    <?= bh_fase_badge($_f) ?>
  <?php endforeach; ?>
</div>

<div class="ds-sectie-titel">Fasebadge — drie lengtes (vol · kort voor tabel · mini voor de knoppenrij)</div>
<div class="ds-kaart ds-kaart-p" style="display:flex;flex-direction:column;gap:10px">
  <?php foreach (['vol', 'kort', 'mini'] as $_lg): ?>
  <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
    <span class="bh-meta" style="width:42px"><?= h($_lg) ?></span>
    <?php foreach (array_keys(fasen()) as $_f): ?><?= bh_fase_badge($_f, ['lengte' => $_lg]) ?><?php endforeach; ?>
  </div>
  <?php endforeach; ?>
</div>

<div class="ds-sectie-titel">Statusbadge met stip · losse badge</div>
<div class="ds-kaart ds-kaart-p" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
  <?= bh_fase_badge('selectiedag_geweest', ['stip' => true]) ?>
  <?= bh_badge('Eerder gesolliciteerd', 'paars') ?>
  <?= bh_badge('mail mislukt', 'rood', ['titel' => 'Adres bestaat niet']) ?>
  <?= bh_badge('', 'groen') ?><span class="bh-meta">← lege tekst geeft bewust niets</span>
</div>

<div class="ds-sectie-titel">Stoplicht — kleur mét woord</div>
<div class="ds-kaart ds-kaart-p" style="display:flex;gap:20px;flex-wrap:wrap">
  <?= bh_stoplicht('goed', 'Gedekt met reserve') ?>
  <?= bh_stoplicht('krap', 'Precies genoeg') ?>
  <?= bh_stoplicht('slecht', 'Rol onvervulbaar') ?>
</div>

<div class="ds-sectie-titel">Avatar — foto, initialen, terugval</div>
<div class="ds-kaart ds-kaart-p" style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">
  <?= bh_avatar($_p[0], 30) ?>
  <?= bh_avatar($_p[1], 38) ?>
  <?= bh_avatar($_p[0], 58) ?>
  <?= bh_avatar($_p[2], 38) ?>
  <?= bh_avatar($_p[1], 38, ['dim' => true]) ?>
  <span class="bh-meta">30 · 38 · 58 px, zonder naam, gedempt</span>
</div>

<div class="ds-sectie-titel">Chip · meta-regel · voortgang</div>
<div class="ds-kaart ds-kaart-p" style="display:flex;flex-direction:column;gap:14px">
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <?= bh_chip('Lifesaver 3') ?><?= bh_chip('EHBO') ?><?= bh_chip('Vaarbewijs 1') ?>
    <?= bh_chip('terugkerend', 'paars') ?>
  </div>
  <?= bh_meta(['19 jaar', 'Den Haag', 'sem.bakker@voorbeeld.nl', '06 12 34 56 78']) ?>
  <?= bh_meta(['17 jaar', '', 'Poeldijk', '']) ?>
  <div style="max-width:320px"><?= bh_voortgang(5, 6) ?></div>
  <div style="max-width:320px"><?= bh_voortgang(6, 6) ?></div>
  <div style="max-width:320px"><?= bh_voortgang(0, 6) ?></div>
</div>

<div class="ds-sectie-titel">Knoppen</div>
<div class="ds-kaart ds-kaart-p" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
  <button class="ds-btn ds-btn-primair">Uitnodigen trainingsweekend</button>
  <button class="ds-btn ds-btn-secundair">Bericht</button>
  <button class="ds-btn ds-btn-zacht">Annuleren</button>
  <button class="ds-btn ds-btn-gevaar">Afwijzen</button>
  <button class="ds-btn ds-btn-secundair ds-btn-sm">Klein</button>
  <button class="ds-btn ds-btn-primair" disabled>Uitgeschakeld</button>
</div>

<div class="ds-sectie-titel">Tabel — op telefoon wordt elke rij een kaartje</div>
<?php
$_rijen = [
    ['p' => ['voornaam' => 'Zoë', 'achternaam' => 'Meijer'], 'mail' => 'zoe.meijer@voorbeeld.nl',
     'lft' => 18, 'plaats' => "'s-Gravenzande", 'fase' => 'selectiedag_geweest', 'datum' => '19 aug', 'mark' => true],
    ['p' => ['voornaam' => 'Daan', 'achternaam' => 'Vermeer'], 'mail' => 'daan.vermeer@voorbeeld.nl',
     'lft' => 19, 'plaats' => 'Julianadorp', 'fase' => 'uitgenodigd_tw', 'datum' => '18 aug', 'mark' => false],
    ['p' => ['voornaam' => 'Mila', 'achternaam' => 'Bos'], 'mail' => 'mila.bos@voorbeeld.nl',
     'lft' => 16, 'plaats' => 'Callantsoog', 'fase' => 'afgewezen', 'datum' => '19 aug', 'mark' => false, 'dim' => true],
];
?>
<table class="bh-tabel" style="--bh-tracks:2.2fr .55fr 1fr 1.5fr .85fr 1.4fr">
  <thead>
    <tr>
      <th scope="col">Naam</th><th scope="col">Lft</th><th scope="col">Woonplaats</th>
      <th scope="col">Status</th><th scope="col">Aangemeld</th>
      <th scope="col" class="bh-rechts">Actie</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($_rijen as $_r): ?>
    <tr class="<?= $_r['mark'] ? 'bh-rij-gemarkeerd' : '' ?><?= !empty($_r['dim']) ? ' bh-rij-gedempt' : '' ?>">
      <td data-label="Naam">
        <span class="bh-naamcel">
          <?= bh_avatar($_r['p'], 30, ['dim' => !empty($_r['dim'])]) ?>
          <span class="bh-naamcel-tekst">
            <span class="bh-naamcel-naam"><?= h(volledige_naam($_r['p'])) ?></span>
            <span class="bh-naamcel-sub"><?= h($_r['mail']) ?></span>
          </span>
        </span>
      </td>
      <td data-label="Leeftijd" class="bh-num"><?= (int)$_r['lft'] ?></td>
      <td data-label="Woonplaats"><?= h($_r['plaats']) ?></td>
      <td data-label="Status"><?= bh_fase_badge($_r['fase'], ['lengte' => 'kort']) ?></td>
      <td data-label="Aangemeld" class="bh-num"><?= h($_r['datum']) ?></td>
      <td data-label="Actie" class="bh-rechts">
        <span class="bh-acties">
          <?php if ($_r['fase'] === 'selectiedag_geweest'): ?>
            <button class="ds-btn ds-btn-primair ds-btn-sm">Uitnodigen TW</button>
          <?php endif; ?>
          <button class="ds-btn ds-btn-zacht ds-btn-sm" aria-label="Meer">···</button>
        </span>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<div class="ds-sectie-titel">Lege staat — leeg is vaak goed nieuws</div>
<div class="ds-kaart" style="padding:16px">
  <?php
    $leeg_titel = 'Bellijst is leeg';
    $leeg_tekst = 'Alle 12 kandidaten hebben een besluit. De berichten zijn verstuurd om 20:14.';
    $leeg_toon  = 'groen';
    $leeg_icoon = 'vink';
    require __DIR__ . '/_leeg.php';
  ?>
</div>
<div class="ds-kaart" style="padding:16px;margin-top:12px">
  <?php
    $leeg_titel = 'Nog geen aanmeldingen';
    $leeg_tekst = 'Zodra iemand zich via een wervingspagina aanmeldt, verschijnt hij hier.';
    require __DIR__ . '/_leeg.php';
  ?>
</div>

<div class="ds-sectie-titel">Laadstaat — de vorm van wat komt</div>
<div class="ds-kaart" style="padding:8px 0">
  <?php $laden_rijen = 3; require __DIR__ . '/_laden.php'; ?>
</div>

<div class="ds-sectie-titel">Meldingen</div>
<div class="ds-kaart ds-kaart-p" style="display:flex;flex-direction:column;gap:10px">
  <div class="ds-melding ds-melding-ok"><?= icoon('vink-cirkel', 16) ?><span>12 uitnodigingen verstuurd.</span></div>
  <div class="ds-melding ds-melding-warn"><?= icoon('waarschuwing', 16) ?><span>Twee berichten konden niet: adres bestaat niet.</span></div>
  <div class="ds-melding ds-melding-error"><?= icoon('kruis-cirkel', 16) ?><span>Beveiligde opslag niet bereikbaar — ref. STG-4471.</span></div>
  <div class="ds-melding ds-melding-info"><?= icoon('info', 16) ?><span>De afwijsmail staat uit; het bestuur belt zelf.</span></div>
</div>

<div style="height:40px"></div>

<?php require __DIR__ . '/layout_bottom.php'; ?>
