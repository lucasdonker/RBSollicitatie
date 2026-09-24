<?php
/**
 * Beheer — Kluissleutel wisselen (sleutelrotatie).
 *
 * De kluis (includes/kluis.php) versleutelt elk gevoelig gegeven met een eigen datasleutel (DEK), en die
 * DEK met de hoofdsleutel uit config.php. Wisselen = de DEK's opnieuw inpakken met de nieuwe
 * hoofdsleutel; de versleutelde inhoud zelf blijft staan. Dat gebeurt hier in batches, zonder
 * JavaScript: elke klik op "Volgende batch" post de stand mee en gaat verder waar de vorige ophield.
 *
 * Dit scherm toont nooit een sleutel en schrijft er nooit een in het logboek; alleen aantallen.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';
vereis_recht('systeem', 'bewerken');

$pagina_titel = 'Kluissleutel wisselen';
$actieve_nav  = 'systeem';
$hier         = APP_URL . '/admin/kluis.php';

$kluis_ok   = function_exists('kluis_beschikbaar') && kluis_beschikbaar();
$configfout = function_exists('kluis_rotatie_configfout') ? kluis_rotatie_configfout() : '';
$stand      = null;
$fout       = '';

// ── Verwerken: één batch ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['actie'] ?? '') === 'herversleutel') {
    csrf_check();
    if (!$kluis_ok) {
        $fout = 'De kluis werkt niet; los dat eerst op (zie Systeem › Systeemgezondheid).';
    } elseif ($configfout !== '') {
        $fout = $configfout;
    } else {
        $vorige = json_decode((string)($_POST['stand'] ?? ''), true);
        $stand  = kluis_herversleutel_batch(is_array($vorige) ? $vorige : []);
    }
}

$status = $kluis_ok ? kluis_rotatie_status() : null;

// ── Bevestiging als tussenscherm ─────────────────────────────
$bevestig = ($_GET['bevestig'] ?? '') === 'herversleutel' && $status && $status['oud'] > 0 && $configfout === '';

// Stappenplan: wat is af, wat is nu aan de beurt?
$oude_sleutel = $status && $status['oude_versie'] !== null;
$nog          = $status ? (int)$status['oud'] : 0;
$nu_stap      = !$oude_sleutel ? 1 : ($nog > 0 ? 3 : 4);
$stappen = [
    ['titel' => 'Nieuwe sleutel maken',
     'tekst' => 'Op de server of je eigen computer: php -r "echo base64_encode(random_bytes(32));" — bewaar hem meteen in de wachtwoordkluis van het bestuur.'],
    ['titel' => 'config.php aanpassen',
     'tekst' => 'Zet de huidige sleutel en versie over naar KLUIS_SLEUTEL_OUD en KLUIS_SLEUTEL_OUD_VERSIE. Zet de nieuwe sleutel in KLUIS_SLEUTEL en verhoog KLUIS_SLEUTEL_VERSIE met 1.'],
    ['titel' => 'Hier herversleutelen tot er 0 over zijn',
     'tekst' => 'Batch voor batch; je kunt stoppen en later verdergaan. Wat al klaar is wordt overgeslagen.'],
    ['titel' => 'Back-up controleren',
     'tekst' => 'Maak een verse back-up van database en documentenmap en controleer dat een document uit het beheer nog opent.'],
    ['titel' => 'Oude sleutel weghalen',
     'tekst' => 'Haal KLUIS_SLEUTEL_OUD en KLUIS_SLEUTEL_OUD_VERSIE uit config.php. Pas dan is de oude sleutel waardeloos.'],
];

require __DIR__ . '/layout_top.php';
?>

<?php if ($bevestig): ?>
  <?php
  $bev = [
      'titel'  => 'Kluis herversleutelen naar sleutelversie ' . (int)$status['versie'] . '?',
      'regels' => [
          $nog . ' ' . ($nog === 1 ? 'versleuteld gegeven wordt' : 'versleutelde gegevens worden') . ' opnieuw ingepakt met de nieuwe sleutel. De inhoud zelf blijft zoals hij is.',
          'Dit gaat in batches van 200. Na elke batch zie je de stand en klik je op "Volgende batch".',
          'Maak vooraf een back-up van de database en de documentenmap.',
          'Laat KLUIS_SLEUTEL_OUD in config.php staan tot hier 0 over staat; anders worden de overgebleven gegevens onleesbaar.',
      ],
      'bericht'      => null,
      'onomkeerbaar' => false,
      'knop'         => 'Ja, herversleutelen',
      'toon'         => 'primair',
  ];
  $bev_form  = ['actie' => $hier, 'velden' => ['actie' => 'herversleutel', 'stand' => '']];
  $bev_terug = $hier;
  require __DIR__ . '/_bevestig.php';
  ?>
  <?php require __DIR__ . '/layout_bottom.php'; ?>
  <?php return; ?>
<?php endif; ?>

<?php if ($fout !== ''): ?>
  <div class="ds-melding ds-melding-error bh-blokkade"><?= icoon('waarschuwing', 18) ?><span><?= h($fout) ?></span></div>
<?php endif; ?>

<?php if (!$kluis_ok): ?>
  <div class="ds-kaart ds-kaart-p">
    <?php
    $leeg_titel = 'De kluis werkt niet';
    $leeg_tekst = 'Zonder werkende kluis valt er niets te wisselen. Bekijk de melding bij Systeem › Systeemgezondheid.';
    $leeg_icoon = 'waarschuwing';
    $leeg_toon  = 'rood';
    $leeg_knop  = ['tekst' => 'Naar Systeem', 'url' => APP_URL . '/admin/systeem.php'];
    require __DIR__ . '/_leeg.php';
    ?>
  </div>
  <?php require __DIR__ . '/layout_bottom.php'; ?>
  <?php return; ?>
<?php endif; ?>

<?php if ($stand !== null): ?>
<section class="ds-kaart">
  <div class="bh-kaartkop"><h2><?= $stand['klaar'] ? 'Herversleutelen klaar' : 'Bezig met herversleutelen' ?></h2></div>
  <div class="bh-kaartbody">
    <p><?= bh_voortgang((int)$stand['gedaan'], (int)$stand['gedaan'] + $nog, ['label' => (int)$stand['gedaan'] . ' gedaan, ' . $nog . ' te gaan']) ?></p>
    <p class="bh-meta">
      Deze run: <?= (int)$stand['gedaan'] ?> opnieuw ingepakt · <?= (int)$stand['al_goed'] ?> waren al goed · <?= (int)$stand['mislukt'] ?> mislukt
    </p>
    <?php if ($stand['fouten']): ?>
      <div class="ds-melding ds-melding-warn">
        <?= icoon('waarschuwing', 16) ?>
        <span>Niet gelukt (de rest ging gewoon door):</span>
      </div>
      <ul class="bh-bevestig-regels">
        <?php foreach ($stand['fouten'] as $_f): ?><li><?= h($_f) ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <?php if (!$stand['klaar']): ?>
      <form method="post" action="<?= h($hier) ?>" class="bh-veldform-voet">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="herversleutel">
        <input type="hidden" name="stand" value="<?= h(json_encode($stand)) ?>">
        <button type="submit" class="ds-btn ds-btn-primair">Volgende batch</button>
        <a class="ds-btn ds-btn-zacht" href="<?= h($hier) ?>">Stoppen, later verder</a>
      </form>
    <?php elseif ($nog === 0): ?>
      <div class="ds-melding ds-melding-ok">
        <?= icoon('vink', 16) ?>
        <span>Alles staat op sleutelversie <?= (int)$status['versie'] ?>. Controleer de back-up en haal daarna KLUIS_SLEUTEL_OUD uit config.php.</span>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<section class="ds-kaart">
  <div class="bh-kaartkop"><h2>Stand van de kluis</h2></div>
  <div class="bh-kaartbody">
    <p>
      Huidige sleutel: <?= bh_badge('versie ' . (int)$status['versie'], 'blauw') ?>
      · Oude sleutel in config.php:
      <?= $oude_sleutel ? bh_badge('ja, versie ' . (int)$status['oude_versie'], 'oranje') : bh_badge('nee', 'grijs') ?>
    </p>

    <?php if ($configfout !== ''): ?>
      <div class="ds-melding ds-melding-error"><?= icoon('waarschuwing', 16) ?><span><?= h($configfout) ?></span></div>
    <?php endif; ?>
    <?php if ($status['zonder_sleutel'] > 0): ?>
      <div class="ds-melding ds-melding-error">
        <?= icoon('waarschuwing', 16) ?>
        <span>Onleesbaar: <?= (int)$status['zonder_sleutel'] ?> versleutelde gegevens staan op een sleutelversie die niet (meer) in config.php staat. Zet die sleutel terug als KLUIS_SLEUTEL_OUD met de juiste versie.</span>
      </div>
    <?php endif; ?>
    <?php if ($status['ongeldig'] > 0): ?>
      <div class="ds-melding ds-melding-warn">
        <?= icoon('waarschuwing', 16) ?>
        <span>Niet in kluisformaat (beschadigd?): <?= (int)$status['ongeldig'] ?>. Herversleutelen slaat die over en meldt ze.</span>
      </div>
    <?php endif; ?>

    <div class="bh-tabel-wrap">
      <table class="bh-tabel" style="--bh-tracks:minmax(0,1fr) 90px 150px 150px">
        <thead>
          <tr>
            <th scope="col">Soort</th>
            <th scope="col" class="bh-rechts">Totaal</th>
            <th scope="col" class="bh-rechts">Huidige versie</th>
            <th scope="col">Nog te doen</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($status['bronnen'] as $_b): $_te_doen = $_b['oud'] + $_b['zonder_sleutel']; ?>
          <tr>
            <td><?= h($_b['titel']) ?></td>
            <td class="bh-num bh-rechts"><?= (int)$_b['totaal'] ?></td>
            <td class="bh-num bh-rechts"><?= (int)$_b['huidig'] ?></td>
            <td><?= $_te_doen > 0 ? bh_stoplicht($_b['zonder_sleutel'] > 0 ? 'slecht' : 'krap', $_te_doen . ' over') : bh_stoplicht('goed', 'niets') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($nog > 0 && $configfout === '' && ($stand === null || $stand['klaar'])): ?>
      <div class="bh-veldform-voet">
        <a class="ds-btn ds-btn-primair" href="<?= h($hier) ?>?bevestig=herversleutel">Herversleutelen</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="ds-kaart">
  <div class="bh-kaartkop"><h2>Zo wissel je de sleutel</h2></div>
  <div class="bh-kaartbody">
    <p class="bh-dossier-tekst">
      Wissel de sleutel bij een vermoeden van een lek, als iemand vertrekt die bij config.php kon, en verder
      eens per jaar. De sleutel staat nooit op dit scherm en nooit in het logboek.
    </p>
    <ol class="bh-stappen">
      <?php foreach ($stappen as $_i => $_s): $_af = $_i + 1 < $nu_stap; $_nu = $_i + 1 === $nu_stap; ?>
      <li class="bh-stap<?= $_af ? ' af' : '' ?><?= $_nu ? ' nu' : '' ?>">
        <span class="bh-stap-bol"><?= $_af ? icoon('vink', 13) : (string)($_i + 1) ?></span>
        <span class="bh-stap-tekst">
          <strong><?= h($_s['titel']) ?></strong>
          <span><?= h($_s['tekst']) ?></span>
        </span>
      </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>

<?php require __DIR__ . '/layout_bottom.php'; ?>
