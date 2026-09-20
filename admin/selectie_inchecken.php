<?php
/**
 * Inchecken — draait op een tablet bij de ingang.
 *
 * Dit scherm wordt staand bediend, met één hand, soms met natte handen
 * (ontwerpfilosofie §6). Daarom: één kolom, grote raakvlakken (48px), hoog contrast,
 * geen horizontaal scrollen. Een tik ís opslaan; er is geen bewaarknop.
 *
 * Inchecken zet meteen de fase op "selectiedag geweest": niemand werkt achteraf een
 * lijst bij.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';

vereis_recht('selectiedag', 'lezen');   // begeleiders mogen ook afvinken

$seizoen = actief_seizoen();
$sid = $seizoen ? (int)$seizoen['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sid) {
    csrf_check();
    $actie = (string)($_POST['actie'] ?? '');

    if ($actie === 'aanwezig') {
        header('Content-Type: application/json');
        $res = selectie_aanwezig_zetten($sid, (int)$_POST['sol_id'], ($_POST['aanwezig'] ?? '') === '1');
        echo json_encode(['ok' => true] + $res);
        exit;
    }
    if ($actie === 'code_genereren') {
        vereis_recht('selectiedag', 'bewerken');
        // Leesbare code: geen tekens die je kunt verwarren (0/O, 1/I/L).
        $alfabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 6; $i++) $code .= $alfabet[random_int(0, strlen($alfabet) - 1)];
        instelling_opslaan('selectie.incheck_code', $code);
        flash('succes', 'Nieuwe toegangscode aangemaakt. De oude tablet-links werken niet meer.');
        redirect(APP_URL . '/admin/selectie_inchecken.php');
    }
}

$code        = function_exists('instelling') ? instelling('selectie.incheck_code', '') : '';
$incheck_url = APP_URL . '/portaal/selectie_incheck.php?code=' . urlencode($code);
$mag_bewerken = mag('selectiedag', 'bewerken');

$kandidaten = $sid ? selectie_kandidaten($sid) : [];
$groepen    = $sid ? selectie_groepen($sid) : [];
$groep_naam = [];
foreach ($groepen as $g) $groep_naam[$g['id']] = $g['naam'] ?: 'Groep ' . $g['nummer'];

$n_aanwezig = count(array_filter($kandidaten, fn($k) => $k['aanwezig']));
$n_totaal   = count($kandidaten);

$per_groep = [];
foreach ($kandidaten as $k) {
    $key = $k['groep_id'] ? ($groep_naam[$k['groep_id']] ?? 'Onbekend') : 'Nog geen groep';
    $per_groep[$key][] = $k;
}
ksort($per_groep);

$pagina_titel = 'Inchecken';
$actieve_nav  = 'selectiedag';
require __DIR__ . '/layout_top.php';
?>

<?php
  $subnav_actief = 'inchecken';
  $subnav_datum  = $seizoen && !empty($seizoen['datum_selectiedag']) ? datum_nl($seizoen['datum_selectiedag']) : null;
  require __DIR__ . '/_selectie_subnav.php';
?>

<?php if (!$sid): ?>
  <div class="ds-kaart" style="padding:16px">
    <?php
      $leeg_titel = 'Geen actief seizoen';
      $leeg_tekst = 'Maak eerst een seizoen aan en zet de datum van de selectiedag.';
      $leeg_icoon = 'kalender';
      require __DIR__ . '/_leeg.php';
    ?>
  </div>
<?php else: ?>

<div class="bh-incheck">

  <section class="ds-kaart ds-kaart-p bh-incheck-tablet">
    <div class="bh-kaartkop">
      <h2>Tablet bij de ingang</h2>
      <span class="bh-meta">sollicitanten melden zichzelf aan</span>
    </div>
    <?php if ($code): ?>
      <div class="bh-incheck-coderij">
        <div class="bh-incheck-codeblok">
          <span class="bh-veldlabel">Toegangscode</span>
          <span class="bh-incheck-code"><?= h($code) ?></span>
        </div>
        <div class="bh-incheck-link">
          <span class="bh-veldlabel">Tablet-link</span>
          <div class="bh-incheck-linkrij">
            <input type="text" class="ds-input" readonly value="<?= h($incheck_url) ?>" id="incheckUrl">
            <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm"
                    onclick="kopieerIncheckLink()">Kopiëren</button>
            <a class="ds-btn ds-btn-secundair ds-btn-sm" href="<?= h($incheck_url) ?>" target="_blank" rel="noopener">Openen</a>
          </div>
        </div>
      </div>
      <?php if ($mag_bewerken): ?>
        <form method="post" class="bh-incheck-nieuw" id="codeForm">
          <?= csrf_input() ?>
          <input type="hidden" name="actie" value="code_genereren">
          <button type="button" class="bh-doc-actie" onclick="nieuweCode()">Nieuwe code aanmaken</button>
        </form>
      <?php endif; ?>
    <?php elseif ($mag_bewerken): ?>
      <p class="bh-dossier-tekst">Er is nog geen code. Zonder code kunnen sollicitanten zich niet zelf aanmelden.</p>
      <form method="post">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="code_genereren">
        <button type="submit" class="ds-btn ds-btn-primair">Toegangscode aanmaken</button>
      </form>
    <?php else: ?>
      <p class="bh-dossier-tekst zacht">Nog geen code ingesteld. Vraag een bestuurslid er een aan te maken.</p>
    <?php endif; ?>
  </section>

  <section class="ds-kaart">
    <div class="bh-incheck-kop">
      <div class="bh-incheck-teller">
        <span class="bh-incheck-getal bh-num" id="teller"><?= $n_aanwezig ?>/<?= $n_totaal ?></span>
        <span class="bh-meta">aanwezig</span>
      </div>
      <label class="bh-zoek">
        <span class="bh-zoek-icoon"><?= icoon('zoek', 15) ?></span>
        <span class="bh-vw">Zoek op naam</span>
        <input type="search" id="zoek" placeholder="Zoek op naam" autocomplete="off">
      </label>
    </div>

    <?php if (!$kandidaten): ?>
      <div style="padding:16px">
        <?php
          $leeg_titel = 'Nog niemand uitgenodigd';
          $leeg_tekst = 'Zodra er kandidaten zijn uitgenodigd voor de selectiedag, staan ze hier.';
          $leeg_icoon = 'mensen';
          require __DIR__ . '/_leeg.php';
        ?>
      </div>
    <?php else: ?>
      <div id="incheckLijst">
        <?php foreach ($per_groep as $_groepnaam => $_leden): ?>
          <div class="bh-incheck-groep" data-groep>
            <span><?= h($_groepnaam) ?></span>
            <span class="bh-meta"><?= count($_leden) ?></span>
          </div>
          <?php foreach ($_leden as $_k): ?>
            <div class="bh-incheck-rij<?= $_k['aanwezig'] ? ' aanwezig' : '' ?>"
                 data-naam="<?= h(strtolower((string)$_k['naam'])) ?>" id="rij-<?= (int)$_k['sol_id'] ?>">
              <?php
                $_delen = explode(' ', trim((string)$_k['naam']));
                echo bh_avatar(['voornaam' => $_delen[0] ?? '', 'achternaam' => end($_delen) ?: ''], 40);
              ?>
              <span class="bh-incheck-naam">
                <?= h((string)$_k['naam']) ?>
                <span class="bh-incheck-sub" id="sub-<?= (int)$_k['sol_id'] ?>">
                  <?= $_k['aanwezig'] ? 'ingecheckt' : h($_groepnaam) ?>
                </span>
              </span>
              <?php if ($mag_bewerken): ?>
                <button type="button" class="bh-aanwezig<?= $_k['aanwezig'] ? ' ja' : '' ?>"
                        id="knop-<?= (int)$_k['sol_id'] ?>"
                        onclick="aanwezigWissel(<?= (int)$_k['sol_id'] ?>)">
                  <?= $_k['aanwezig'] ? 'Ongedaan' : 'Aanwezig' ?>
                </button>
              <?php else: ?>
                <?= $_k['aanwezig'] ? bh_badge('Aanwezig', 'groen') : bh_badge('Nog niet', 'grijs') ?>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </div>
      <div class="bh-incheck-voet">
        <span class="bh-meta">Wie na afloop niet is ingecheckt, telt als niet verschenen.</span>
        <a class="ds-btn ds-btn-primair" href="<?= APP_URL ?>/admin/selectiedag.php">Naar beoordelen</a>
      </div>
    <?php endif; ?>
  </section>
</div>

<script>
const CSRF = '<?= csrf_token() ?>';
let nAanwezig = <?= (int)$n_aanwezig ?>;
const nTotaal = <?= (int)$n_totaal ?>;

/* Een tik ís opslaan: de knop wisselt meteen en herstelt zich als de server nee zegt.
   Wachten op een rondje server voelt op een tablet als "hij deed het niet". */
function aanwezigWissel(solId) {
  const knop = document.getElementById('knop-' + solId);
  const rij  = document.getElementById('rij-' + solId);
  const sub  = document.getElementById('sub-' + solId);
  const wordt = !knop.classList.contains('ja');
  const subOud = sub ? sub.textContent : '';

  zetStand(knop, rij, sub, wordt, subOud);
  knop.disabled = true;

  fetch(location.pathname, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ actie: 'aanwezig', sol_id: solId, aanwezig: wordt ? '1' : '0', _csrf: CSRF })
  })
  .then(r => r.json())
  .then(d => {
    knop.disabled = false;
    if (!d.ok) { zetStand(knop, rij, sub, !wordt, subOud); bhToast('Opslaan mislukt.', 'fout'); }
  })
  .catch(() => {
    knop.disabled = false;
    zetStand(knop, rij, sub, !wordt, subOud);
    bhToast('Geen verbinding — probeer opnieuw.', 'fout');
  });
}

function zetStand(knop, rij, sub, aanwezig, subOud) {
  knop.classList.toggle('ja', aanwezig);
  knop.textContent = aanwezig ? 'Ongedaan' : 'Aanwezig';
  rij.classList.toggle('aanwezig', aanwezig);
  if (sub) sub.textContent = aanwezig ? 'ingecheckt' : subOud;
  nAanwezig += aanwezig ? 1 : -1;
  document.getElementById('teller').textContent = nAanwezig + '/' + nTotaal;
}

document.getElementById('zoek').addEventListener('input', function () {
  const q = this.value.trim().toLowerCase();
  document.querySelectorAll('.bh-incheck-rij').forEach(function (r) {
    r.hidden = q !== '' && r.dataset.naam.indexOf(q) === -1;
  });
  // Een groepskop zonder zichtbare namen eronder is ruis.
  document.querySelectorAll('[data-groep]').forEach(function (kop) {
    let el = kop.nextElementSibling, zichtbaar = false;
    while (el && !el.hasAttribute('data-groep')) {
      if (!el.hidden) { zichtbaar = true; break; }
      el = el.nextElementSibling;
    }
    kop.hidden = !zichtbaar;
  });
});

function kopieerIncheckLink() {
  const v = document.getElementById('incheckUrl').value;
  if (navigator.clipboard) {
    navigator.clipboard.writeText(v).then(function () { bhToast('Link gekopieerd.', 'succes'); });
  }
}

function nieuweCode() {
  bhBevestig({
    titel: 'Nieuwe toegangscode aanmaken?',
    tekst: 'De tablets die nu open staan werken daarna niet meer; die moet je opnieuw openen met de nieuwe link.',
    knop: 'Ja, nieuwe code'
  }).then(function (ja) { if (ja) document.getElementById('codeForm').submit(); });
}
</script>

<?php endif; ?>
<?php require __DIR__ . '/layout_bottom.php'; ?>
