<?php
/**
 * Beheer — Dekking: passen de beschikbare mensen op de rollen van de posten?
 *
 * Dit is een ándere vraag dan het beschikbaarheidsscherm. Daar gaat het over aantallen,
 * hier over competenties: tien mensen die geen van allen mogen varen leveren nog steeds
 * geen schipper. Het scherm begint daarom met het antwoord ("Tekort in W28, W30") en
 * pas daarna met de weken.
 *
 * Eén week tegelijk open: het detail is de hoofdtaak, het weekoverzicht is de keuze.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';
vereis_recht('dekking', 'lezen');

$pagina_titel = 'Dekking';
$actieve_nav  = 'dekking';
// Weken naast elkaar: geen tekstbreedte, maar de ruimte die er is.
$pagina_breed = true;

$seizoen = function_exists('actief_seizoen') ? actief_seizoen() : null;
$sid     = $seizoen ? (int)$seizoen['id'] : 0;

$weken_datums = function_exists('seizoen_weken_datums') ? seizoen_weken_datums($seizoen) : [];
$dekking      = ($sid && function_exists('bezetting_dekking')) ? bezetting_dekking($sid, $seizoen) : [];

$detail_wk  = isset($_GET['week']) ? (int)$_GET['week'] : 0;
$comp_klaar = function_exists('competentie_sync_klaar') ? competentie_sync_klaar() : false;

// De stand van de hele zomer in één zin — de eerste regel van het scherm.
$stand = bh_dek_zin($dekking);

/** "22 jun – 28 jun" voor een week, of een lege string als de datums onbekend zijn. */
$wk_periode = function (int $wk) use ($weken_datums): string {
    if (!isset($weken_datums[$wk])) return '';
    return datum_kort_nl($weken_datums[$wk]['start']) . ' – ' . datum_kort_nl($weken_datums[$wk]['eind']);
};

require __DIR__ . '/layout_top.php';
?>

<?php if (!$comp_klaar): ?>
<div class="ds-melding ds-melding-warn bh-blokkade">
  <?= icoon('waarschuwing', 18) ?>
  <span>
    <strong>Authentik is nog niet gekoppeld.</strong>
    Zonder competenties weet het systeem niet wie mag varen of rijden, en kan het de dekking
    niet berekenen. Koppel het bij
    <a href="<?= APP_URL ?>/admin/instellingen.php?tab=authentik">Instellingen › Authentik &amp; competenties</a>.
  </span>
</div>
<?php endif; ?>

<?php if (empty($dekking)): ?>
  <?php
  $leeg_titel = 'Er valt nog niets te dekken';
  $leeg_tekst = 'Zodra sollicitanten hun beschikbaarheid hebben ingevuld, rekent dit scherm uit of de posten bemand kunnen worden.';
  $leeg_icoon = 'schild';
  require __DIR__ . '/_leeg.php';
  ?>
<?php else: ?>

<section class="ds-kaart bh-dekkop">
  <div class="bh-dekkop-zin">
    <?= bh_stoplicht($stand[0], $stand[1]) ?>
  </div>
  <p class="bh-dekkop-uitleg">
    Eén pool: wie op de hoofdpost staat, staat niet tegelijk op GK. De postcommandant valt
    hierbuiten — die regelt het bestuur apart. Klik een week voor de rollen en het bellijstje.
  </p>
  <span class="bh-dekkop-legenda">
    <?= bh_stoplicht('goed', 'Gedekt met reserve') ?>
    <?= bh_stoplicht('krap', 'Precies genoeg') ?>
    <?= bh_stoplicht('slecht', 'Rol onvervulbaar') ?>
  </span>
</section>

<div class="bh-dektegels">
  <?php foreach ($dekking as $wk => $d):
    $_stand = bh_dek_stand((string)$d['status']);
    $_open  = $detail_wk === (int)$wk;
  ?>
  <a class="bh-dektegel ds-fase-<?= $_stand === 'goed' ? 'groen' : ($_stand === 'krap' ? 'oranje' : 'rood') ?><?= $_open ? ' open' : '' ?>"
     href="?week=<?= (int)$wk ?>"<?= $_open ? ' aria-current="true"' : '' ?>>
    <span class="bh-dektegel-kop">
      Week <?= (int)$wk ?>
      <span class="bh-dektegel-stand"><?= h(bh_dek_woord((string)$d['status'])) ?></span>
    </span>
    <span class="bh-dektegel-datum">
      <?php $_per = $wk_periode((int)$wk); ?>
      <?= $_per !== '' ? h($_per) . ' · ' : '' ?><?= (int)$d['pool_grootte'] ?> beschikbaar
    </span>
    <span class="bh-dektegel-posten">
      <?php foreach ($d['posten'] as $p): ?>
      <span class="bh-dektegel-post ds-fase-<?= bh_dek_stand((string)$p['status']) === 'goed' ? 'groen' : (bh_dek_stand((string)$p['status']) === 'krap' ? 'oranje' : 'rood') ?>">
        <?= icoon(bh_post_icoon((string)($p['icoon'] ?? '')), 14) ?>
        <?= h((string)$p['naam']) ?>
        <span class="sr-only">— <?= h(bh_dek_woord((string)$p['status'])) ?></span>
      </span>
      <?php endforeach; ?>
    </span>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($detail_wk && isset($dekking[$detail_wk])): $d = $dekking[$detail_wk]; ?>
<section class="ds-kaart bh-dekdetail" id="week-detail">
  <header class="bh-dekdetail-kop">
    <h2>Week <?= $detail_wk ?></h2>
    <span class="bh-dekdetail-datum"><?= h($wk_periode($detail_wk)) ?></span>
    <span class="bh-dekdetail-telling"><?= (int)$d['ingezet'] ?> ingezet van <?= (int)$d['pool_grootte'] ?> beschikbaar</span>
    <a class="ds-btn ds-btn-zacht ds-btn-sm" href="?">Sluiten</a>
  </header>

  <?php foreach ($d['posten'] as $p): ?>
  <section class="bh-dekpost">
    <header class="bh-dekpost-kop">
      <?= icoon(bh_post_icoon((string)($p['icoon'] ?? '')), 18) ?>
      <h3><?= h((string)$p['naam']) ?></h3>
      <?= bh_stoplicht(bh_dek_stand((string)$p['status']), bh_dek_woord((string)$p['status'])) ?>
    </header>

    <ul class="bh-rollijst">
      <?php foreach ($p['rollen'] as $r): $_ok = !empty($r['vervuld']); ?>
      <li class="bh-rolrij ds-fase-<?= $_ok ? 'groen' : 'rood' ?>">
        <span class="bh-rolrij-vink"><?= icoon($_ok ? 'vink' : 'kruis', 15) ?></span>
        <span class="bh-rolrij-label"><?= h((string)$r['label']) ?></span>
        <span class="bh-rolrij-namen">
          <?= $r['namen'] ? h(implode(', ', $r['namen'])) : 'niemand beschikbaar' ?>
        </span>
        <span class="bh-rolrij-reserve">
          <?php if ($_ok && (int)$r['reserve'] > 0): ?>
            +<?= (int)$r['reserve'] ?> reserve
          <?php elseif ($_ok): ?>
            geen reserve
          <?php endif; ?>
        </span>
      </li>
      <?php endforeach; ?>
    </ul>

    <?php foreach (($p['tekort'] ?? []) as $t): ?>
    <div class="bh-tekort">
      <p class="bh-tekort-kop">
        Waarom tekort: <?= h((string)$t['rol']) ?><?= !empty($t['melding']) ? ' — ' . h((string)$t['melding']) : '' ?>
      </p>
      <p class="bh-tekort-cijfers">
        Nodig <?= (int)$t['nodig'] ?> · gevonden <?= (int)$t['gevonden'] ?> ·
        vereist <?= h((string)$t['categorie']) ?> vanaf rang <?= (int)$t['min_rang'] ?>
      </p>
      <?php
        $bel = function_exists('bezetting_bellijst')
             ? bezetting_bellijst($sid, $detail_wk, (string)$t['categorie'], (int)$t['min_rang'], $seizoen)
             : [];
      ?>
      <?php if ($bel): ?>
        <p class="bh-tekort-kop">Wel gekwalificeerd, deze week niet beschikbaar:</p>
        <ul class="bh-persoonchips">
          <?php foreach ($bel as $b): ?>
          <li class="bh-persoonchip">
            <?= h((string)$b['naam']) ?>
            <?php if (!empty($b['telefoon'])): ?>
              <a href="tel:<?= h((string)$b['telefoon']) ?>" title="Bel <?= h((string)$b['naam']) ?>"
                 aria-label="Bel <?= h((string)$b['naam']) ?>"><?= icoon('telefoon', 14) ?></a>
            <?php endif; ?>
            <?php if (!empty($b['email'])): ?>
              <a href="mailto:<?= h((string)$b['email']) ?>" title="Mail <?= h((string)$b['naam']) ?>"
                 aria-label="Mail <?= h((string)$b['naam']) ?>"><?= icoon('mail', 14) ?></a>
            <?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="bh-tekort-cijfers">
          Niemand met deze competentie gevonden — ook niet buiten de beschikbare groep.
          Dit lost zich niet op met bellen.
        </p>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </section>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php /* Dekking is een afgeleide van de beschikbaarheid; de versheid van dít scherm is
         dus de versheid van die gegevens. */ ?>
<?= bh_versheid(bh_laatst(
      "SELECT MAX(GREATEST(COALESCE(b.definitief_op,'1000-01-01'), COALESCE(b.ingevuld_op,'1000-01-01')))
       FROM beschikbaarheid b JOIN sollicitaties s ON s.id = b.sollicitatie_id
       WHERE s.seizoen_id = ?", [$sid]), 'Beschikbaarheid') ?>

<?php endif; ?>

<?php require __DIR__ . '/layout_bottom.php'; ?>
