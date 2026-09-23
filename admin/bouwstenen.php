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
<p class="bh-meta">
  Met <code>['vergroot' =&gt; true]</code> wordt een échte foto een knop die hem groot opent —
  gebruik dat waar de avatar niet al in een link staat (in een lijstrij wint de link).
  Bij initialen doet de optie niets: er valt dan niets te vergroten.
</p>

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

<div class="ds-sectie-titel">Tabel — echte partial, op telefoon wordt elke rij een kaartje</div>
<?php
// Bewust via _tabel.php + _rij_sollicitant.php: zo toetst deze pagina het échte
// contract en niet een nagebouwde tabel die toevallig goed staat.
$_vb = [
    ['id' => 1, 'voornaam' => 'Zoë', 'achternaam' => 'Meijer', 'tussenvoegsel' => '',
     'email' => 'zoe.meijer@voorbeeld.nl', 'telefoon' => '0624881103', 'geboortedatum' => '2008-03-04',
     'woonplaats' => "'s-Gravenzande", 'fase' => 'selectiedag_geweest', 'aangemeld_op' => '2026-08-19', 'terugkerend' => 0],
    ['id' => 2, 'voornaam' => 'Sem', 'achternaam' => 'Bakker', 'tussenvoegsel' => '',
     'email' => 'sem.bakker@voorbeeld.nl', 'telefoon' => '0612345678', 'geboortedatum' => '2007-03-04',
     'woonplaats' => 'Den Haag', 'fase' => 'selectiedag_geweest', 'aangemeld_op' => '2026-08-19', 'terugkerend' => 1],
    ['id' => 3, 'voornaam' => 'Noah', 'achternaam' => 'Groot', 'tussenvoegsel' => 'de',
     'email' => 'noah.degroot@voorbeeld.nl', 'telefoon' => '0633021987', 'geboortedatum' => '2009-06-01',
     'woonplaats' => 'Poeldijk', 'fase' => 'selectiedag_geweest', 'aangemeld_op' => '2026-09-12', 'terugkerend' => 0,
     'mail_mislukt' => 1, 'mail_mislukt_reden' => 'Adres bestaat niet'],
    ['id' => 4, 'voornaam' => 'Mila', 'achternaam' => 'Bos', 'tussenvoegsel' => '',
     'email' => 'mila.bos@voorbeeld.nl', 'telefoon' => '', 'geboortedatum' => '2010-01-20',
     'woonplaats' => 'Callantsoog', 'fase' => 'afgewezen', 'aangemeld_op' => '2026-08-19', 'terugkerend' => 0],
];
$tabel = bh_kolommen('proef', [
    'naam'       => ['kop' => 'Naam',       'track' => 'minmax(180px,2.2fr)', 'sorteer' => 'p.achternaam'],
    'leeftijd'   => ['kop' => 'Lft',        'track' => '56px',  'uitlijn' => 'rechts', 'mobiel' => 'verberg'],
    'woonplaats' => ['kop' => 'Woonplaats', 'track' => 'minmax(80px,1fr)'],
    'status'     => ['kop' => 'Status',     'track' => 'minmax(205px,1.8fr)'],
    'aangemeld'  => ['kop' => 'Aangemeld',  'track' => '104px', 'sorteer' => 's.aangemeld_op'],
    'actie'      => ['kop' => 'Actie',      'track' => '290px', 'uitlijn' => 'rechts'],
], ['sort' => $_GET['sort'] ?? null, 'richting' => $_GET['richting'] ?? null]);
$tabel_rijen    = $_vb;
$tabel_rij      = __DIR__ . '/_rij_sollicitant.php';
$tabel_selectie = true;
$tabel_rij_cls  = fn(array $r) => ($r['fase'] === 'afgewezen' ? 'bh-rij-gedempt' : '');
require __DIR__ . '/_tabel.php';
?>

<div class="ds-sectie-titel">Zelfde tabel, geen rijen</div>
<?php
$tabel = bh_kolommen('proef_leeg', ['naam' => ['kop' => 'Naam'], 'status' => ['kop' => 'Status']]);
$tabel_rijen = [];
$tabel_rij   = __DIR__ . '/_rij_sollicitant.php';
$tabel_leeg  = ['titel' => 'Geen kandidaten in deze fase',
                'tekst' => 'Zodra er iemand doorschuift, staat hij hier.'];
require __DIR__ . '/_tabel.php';
?>

<div class="ds-sectie-titel">Lege staat — leeg is vaak goed nieuws</div>
<div class="ds-kaart ds-kaart-p">
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

<div class="ds-sectie-titel">Weekcel — vijf standen, klikbaar en niet-klikbaar</div>
<div class="ds-kaart ds-kaart-p">
  <div class="bh-rastertabel" style="--bh-tracks:repeat(5,minmax(44px,84px))">
    <div style="display:grid;grid-template-columns:var(--bh-tracks)">
      <?= bh_weekcel('5',  'vol',       ['titel' => 'hele week']) ?>
      <?= bh_weekcel('wk', 'weekend',   ['titel' => 'alleen het weekend']) ?>
      <?= bh_weekcel('3',  'deels',     ['titel' => '3 van de 5 dagen']) ?>
      <?= bh_weekcel('2',  'correctie', ['titel' => 'gecorrigeerd door het bestuur']) ?>
      <?= bh_weekcel('—',  'leeg',      ['titel' => 'niet beschikbaar']) ?>
    </div>
  </div>
  <p class="bh-meta" style="margin-top:10px">
    <?= bh_weekcel('', 'vol') ?>Lege tekst geeft bewust niets: een gekleurd vlak zonder inhoud is
    voor een kleurenblinde gebruiker en voor een schermlezer geen mededeling.
  </p>
  <p style="margin-top:12px">
    <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm"
            onclick="correctieOpen(1, 28, 'Jacob Donker')">Bewerkpaneel openen</button>
  </p>
</div>

<div class="ds-sectie-titel">Dekkingsgrafiek — met en zonder norm</div>
<?php
$_vbweken = [];
foreach ([26, 27, 28, 29, 30, 31] as $_i => $_w) {
    $_vbweken[$_w] = ['wn' => $_w, 'kort' => (22 + $_i * 7) . ' jun', 'eind' => '', 'weektype' => $_i < 2 ? 'voor' : 'hoofd'];
}
$_vbtot = [26 => ['aantal' => 10, 'weekend' => 9], 27 => ['aantal' => 9, 'weekend' => 4],
           28 => ['aantal' => 8, 'weekend' => 7],  29 => ['aantal' => 7, 'weekend' => 7],
           30 => ['aantal' => 5, 'weekend' => 3],  31 => ['aantal' => 11, 'weekend' => 10]];
$_dek   = bh_dekking_weken($_vbweken, $_vbtot, 7);
$_weken = $_vbweken;
require __DIR__ . '/_dekkinggrafiek.php';

$_dek   = bh_dekking_weken($_vbweken, $_vbtot, 0);
$_weken = $_vbweken;
$_titel = 'Zelfde cijfers, geen norm ingesteld';
require __DIR__ . '/_dekkinggrafiek.php';
?>

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
