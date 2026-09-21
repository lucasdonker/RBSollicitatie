<?php
/**
 * Beheer — Trainingsweekend: het weekend zelf, en daarna het besluit.
 *
 * Twee momenten, twee standen van hetzelfde scherm:
 *   ?stap=weekend  — tijdens het weekend, op een telefoon: wie is er, en wat valt op.
 *   ?stap=besluit  — na afloop, aan tafel: per kandidaat aannemen of afwijzen, en
 *                    daarna in één keer doorvoeren.
 *
 * Doorvoeren is bewust een aparte stap, los van het besluit vastleggen. Op het moment
 * dat je doorvoert verandert de fase én gaat er bericht uit; dat wil je niet per
 * ongeluk doen terwijl je nog aan het overleggen bent. Tot dat moment staat een
 * besluit gewoon opgeslagen en kun je het nog wijzigen.
 *
 * Model: het selectiedagscherm. Aanwezigheid en observaties werken hetzelfde, met
 * dezelfde bouwstenen, zodat wie het ene kent het andere ook kent.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';

$pagina_titel = 'Trainingsweekend';
$actieve_nav  = 'trainingsweekend';
vereis_recht('selectiedag', 'lezen');

$seizoen = actief_seizoen();
$sid     = $seizoen ? (int)$seizoen['id'] : 0;
$ik      = (string)($_SESSION['bhd_naam'] ?? 'onbekend');

// Drie niveaus, net als op de selectiedag:
//   lezen     — meekijken (iedereen die hier binnenkomt)
//   meedraaien — afvinken en observeren: dat doen begeleiders, die hebben alleen 'lezen'.
//                Zie rechten.php: "mag inchecken/meedraaien, niet bewerken". Het is hun
//                werk in het weekend; zonder dit kan een begeleider niets vastleggen.
//   beslissen  — aannemen of afwijzen: bestuurswerk.
$mag_meedraaien = mag('selectiedag', 'lezen');
$mag_beslissen  = mag('selectiedag', 'bewerken');

// ── POST: JSON voor het weekend-scherm, gewone formulieren voor het besluit ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sid) {
    $actie = (string)($_POST['actie'] ?? '');
    $json  = in_array($actie, ['aanwezig', 'observatie', 'observatie_wis'], true);
    if ($json) header('Content-Type: application/json');
    csrf_check();

    if (!$mag_meedraaien) {
        if ($json) { echo json_encode(['ok' => false, 'fout' => 'geen_rechten']); exit; }
        http_response_code(403); exit('Geen rechten.');
    }

    if ($actie === 'aanwezig') {
        tw_aanwezig_zetten($sid, (int)$_POST['sol_id'], !empty($_POST['aan']), (string)($_POST['reden'] ?? ''));
        echo json_encode(['ok' => true]); exit;
    }
    if ($actie === 'observatie') {
        $ok = tw_observatie_toevoegen($sid, (int)$_POST['sol_id'], (string)($_POST['tekst'] ?? ''),
                                      (string)($_POST['soort'] ?? 'neutraal'), $ik);
        echo json_encode(['ok' => $ok, 'door' => $ik]); exit;
    }
    if ($actie === 'observatie_wis') {
        echo json_encode(['ok' => tw_observatie_wissen((int)$_POST['id'], $ik)]); exit;
    }

    if (!$mag_beslissen) { http_response_code(403); exit('Beslissen is bestuurswerk.'); }

    if ($actie === 'besluit') {
        $b = (string)($_POST['besluit'] ?? '');
        tw_besluit_zetten($sid, (int)$_POST['sol_id'], $b !== '' ? $b : null,
                          (string)($_POST['notitie'] ?? ''), $ik);
        flash('succes', 'Besluit vastgelegd. Er is nog geen bericht verstuurd — dat gebeurt bij het doorvoeren.');
        redirect(APP_URL . '/admin/trainingsweekend.php?stap=besluit');
    }

    if ($actie === 'doorvoeren') {
        $gedaan = 0; $over = 0;
        foreach (tw_kandidaten($sid) as $k) {
            if (empty($k['besluit']) || !empty($k['doorgevoerd_op'])) continue;
            $naar = tw_besluit_fase((string)$k['besluit']);
            // Twijfel leidt nergens heen: daar moet iemand over bellen, niet over mailen.
            if ($naar === null) { $over++; continue; }
            fase_zetten((int)$k['sol_id'], $naar, ['bron' => 'trainingsweekend', 'door' => $ik]);
            tw_doorgevoerd_markeren($sid, (int)$k['sol_id']);
            $gedaan++;
        }
        if (function_exists('audit_log')) {
            audit_log('tw_besluiten_doorgevoerd', 'seizoen', $sid, ['aantal' => $gedaan, 'twijfel' => $over]);
        }
        flash('succes', $gedaan . ' ' . ($gedaan === 1 ? 'besluit' : 'besluiten') . ' doorgevoerd.'
            . ($over > 0 ? ' ' . $over . ' op twijfel: die blijven staan tot er gebeld is.' : ''));
        redirect(APP_URL . '/admin/trainingsweekend.php?stap=besluit');
    }

    http_response_code(400); exit('Onbekende actie.');
}

$stap = (string)($_GET['stap'] ?? 'weekend');
if (!in_array($stap, ['weekend', 'besluit'], true)) $stap = 'weekend';
if ($stap === 'besluit' && !$mag_beslissen) $stap = 'weekend';

$kandidaten = $sid ? tw_kandidaten($sid) : [];
$stand      = tw_stand($kandidaten);
$obs_alle   = $sid ? tw_observaties_alle($sid) : [];

// Welke kandidaat staat open op het weekend-scherm?
$huidig_id = (int)($_GET['k'] ?? 0);
$ids       = array_map(fn($k) => (int)$k['sol_id'], $kandidaten);
if (!$huidig_id || !in_array($huidig_id, $ids, true)) {
    $huidig_id = $ids[0] ?? 0;
}
$pos    = array_search($huidig_id, $ids, true);
$huidig = $pos !== false ? $kandidaten[$pos] : null;

/** URL naar dit scherm, met een andere stap of kandidaat. */
function tw_url(?string $stap = null, ?int $k = null): string {
    $p = [];
    $s = $stap ?? (string)($_GET['stap'] ?? 'weekend');
    if ($s !== 'weekend') $p['stap'] = $s;
    if ($k)               $p['k'] = $k;
    return APP_URL . '/admin/trainingsweekend.php' . ($p ? '?' . http_build_query($p) : '');
}

// "3 – 5 mei 2026" als het in dezelfde maand valt, anders "29 apr – 2 mei 2026".
$tw_periode = '';
if ($seizoen && !empty($seizoen['datum_tw_start'])) {
    $_van = (string)$seizoen['datum_tw_start'];
    $_tot = (string)($seizoen['datum_tw_eind'] ?? '');
    if ($_tot === '' || $_tot === $_van) {
        $tw_periode = datum_nl($_van);
    } elseif (substr($_van, 0, 7) === substr($_tot, 0, 7)) {
        $tw_periode = (int)substr($_van, 8, 2) . ' – ' . datum_nl($_tot);
    } else {
        $tw_periode = datum_kort_nl($_van) . ' – ' . datum_nl($_tot);
    }
}

require __DIR__ . '/layout_top.php';
?>

<div class="bh-selectiekop">
  <div class="bh-selectiekop-titel">
    <h2>Trainingsweekend</h2>
    <?php if ($tw_periode !== ''): ?><span class="bh-meta"><?= h($tw_periode) ?></span><?php endif; ?>
  </div>
  <nav class="bh-substappen" aria-label="Stappen van het trainingsweekend">
    <a class="bh-substap<?= $stap === 'weekend' ? ' actief' : '' ?>" href="<?= h(tw_url('weekend')) ?>"
       <?= $stap === 'weekend' ? 'aria-current="page"' : '' ?>>Het weekend</a>
    <?php if ($mag_beslissen): ?>
      <a class="bh-substap<?= $stap === 'besluit' ? ' actief' : '' ?>" href="<?= h(tw_url('besluit')) ?>"
         <?= $stap === 'besluit' ? 'aria-current="page"' : '' ?>>
        Besluit<?= $stand['te_beslissen'] > 0 ? ' (' . $stand['te_beslissen'] . ')' : '' ?>
      </a>
    <?php endif; ?>
  </nav>
</div>

<?php if (!$sid || !$kandidaten): ?>
  <div class="ds-kaart" style="padding:16px">
    <?php
    $leeg_titel = !$sid ? 'Geen actief seizoen' : 'Nog niemand uitgenodigd';
    $leeg_tekst = !$sid
        ? 'Maak eerst een seizoen aan bij Seizoenen.'
        : 'Zodra je na de selectiedag mensen uitnodigt voor het trainingsweekend, staan ze hier.';
    $leeg_icoon = 'reddingsboei';
    require __DIR__ . '/_leeg.php';
    ?>
  </div>

<?php elseif ($stap === 'weekend'): ?>

<div class="bh-beoordeel">
  <aside class="bh-beoordeel-lijst">
    <div class="bh-beoordeel-stand">
      <span><?= (int)$stand['totaal'] ?> uitgenodigd</span>
      <span class="bh-beoordeel-klaar"><?= (int)$stand['aanwezig'] ?> aanwezig</span>
    </div>
    <?= bh_voortgang((int)$stand['aanwezig'] + (int)$stand['afwezig'], max(1, (int)$stand['totaal']), ['label' => '']) ?>
    <?php if ($stand['onbekend'] > 0): ?>
      <p class="bh-meta"><?= (int)$stand['onbekend'] ?> nog niet afgevinkt</p>
    <?php endif; ?>

    <ul class="bh-kandidaatlijst">
      <?php foreach ($kandidaten as $_k):
        $_id  = (int)$_k['sol_id'];
        $_af  = empty($_k['aanwezig']) && ($_k['ingecheckt_op'] !== null || $_k['afgemeld_reden'] !== null);
        $_nu  = $_id === $huidig_id;
        $_nob = count($obs_alle[$_id] ?? []);
      ?>
      <li>
        <a class="bh-kandidaat<?= $_nu ? ' nu' : '' ?><?= $_af ? ' afwezig' : '' ?>"
           href="<?= h(tw_url('weekend', $_id)) ?>"<?= $_nu ? ' aria-current="true"' : '' ?>>
          <?= bh_avatar(['voornaam' => $_k['voornaam'], 'achternaam' => $_k['achternaam']], 38, ['dim' => $_af]) ?>
          <span class="bh-kandidaat-tekst">
            <span class="bh-kandidaat-naam">
              <?= h((string)$_k['naam']) ?>
              <?php if (!empty($_k['bijzonderheden'])): ?>
                <span class="bh-kandidaat-letop" title="Bijzonderheid gemeld"><?= icoon('waarschuwing', 14) ?></span>
              <?php endif; ?>
            </span>
            <span class="bh-kandidaat-sub">
              <?php if (!empty($_k['aanwezig'])): ?>aanwezig
              <?php elseif ($_af): ?>niet gekomen<?= !empty($_k['afgemeld_reden']) ? ' · ' . h((string)$_k['afgemeld_reden']) : '' ?>
              <?php else: ?>nog niet afgevinkt<?php endif; ?>
              <?php if ($_nob): ?> · <?= $_nob ?> <?= $_nob === 1 ? 'observatie' : 'observaties' ?><?php endif; ?>
            </span>
          </span>
          <?php if ($_nu): ?><span class="bh-kandidaat-nu">nu</span>
          <?php elseif (!empty($_k['aanwezig'])): ?><span class="bh-kandidaat-vink"><?= icoon('vink', 16) ?></span>
          <?php endif; ?>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
  </aside>

  <?php if ($huidig): $hid = (int)$huidig['sol_id']; $obs = $obs_alle[$hid] ?? []; ?>
  <section class="bh-beoordeel-kaart" id="kandidaat">
    <header class="bh-beoordeel-kop">
      <?= bh_avatar(['voornaam' => $huidig['voornaam'], 'achternaam' => $huidig['achternaam']], 44) ?>
      <span class="bh-beoordeel-wie">
        <span class="bh-beoordeel-naam"><?= h((string)$huidig['naam']) ?></span>
        <span class="bh-meta">
          <?= bh_meta([$huidig['leeftijd'] ? $huidig['leeftijd'] . ' jaar' : '', (string)($huidig['telefoon'] ?? '')]) ?>
        </span>
      </span>
      <a class="ds-btn ds-btn-secundair ds-btn-sm" href="<?= APP_URL ?>/admin/sollicitant.php?id=<?= $hid ?>">Dossier</a>
    </header>

    <?php if (!empty($huidig['bijzonderheden'])): ?>
      <div class="ds-melding ds-melding-warn bh-beoordeel-bijz">
        <?= icoon('waarschuwing', 16) ?>
        <span><strong>Bijzonderheid gemeld:</strong> <?= nl2br(h((string)$huidig['bijzonderheden'])) ?></span>
      </div>
    <?php endif; ?>

    <div class="bh-testblok">
      <h3>Is <?= h((string)$huidig['voornaam']) ?> er?</h3>
      <div class="bh-aanwezigkeuze" id="aanw-<?= $hid ?>">
        <button type="button" class="bh-aanwezig ja<?= !empty($huidig['aanwezig']) ? ' gekozen' : '' ?>"
                <?= $mag_meedraaien ? '' : 'disabled' ?>
                aria-pressed="<?= !empty($huidig['aanwezig']) ? 'true' : 'false' ?>"
                onclick="twAanwezig(<?= $hid ?>, true)">
          <?= icoon('vink', 18) ?> Aanwezig
        </button>
        <button type="button" class="bh-aanwezig nee<?= (empty($huidig['aanwezig']) && ($huidig['ingecheckt_op'] !== null || $huidig['afgemeld_reden'] !== null)) ? ' gekozen' : '' ?>"
                <?= $mag_meedraaien ? '' : 'disabled' ?>
                aria-pressed="<?= (empty($huidig['aanwezig']) && $huidig['afgemeld_reden'] !== null) ? 'true' : 'false' ?>"
                onclick="twAfwezig(<?= $hid ?>)">
          <?= icoon('kruis', 18) ?> Niet gekomen
        </button>
      </div>
      <p class="bh-zijpaneel-hint" id="aanwstand-<?= $hid ?>">
        <?php if (!empty($huidig['aanwezig'])): ?>
          Afgevinkt<?= !empty($huidig['ingecheckt_op']) ? ' om ' . h(substr((string)$huidig['ingecheckt_op'], 11, 5)) : '' ?>.
        <?php elseif (!empty($huidig['afgemeld_reden'])): ?>
          Niet gekomen — <?= h((string)$huidig['afgemeld_reden']) ?>
        <?php else: ?>
          Nog niet afgevinkt. Tikken is opslaan; er gaat geen bericht uit.
        <?php endif; ?>
      </p>
    </div>

    <div class="bh-observatieblok">
      <h3>Wat valt op?</h3>
      <?php if ($mag_meedraaien): ?>
        <div class="bh-soortkeuze" id="soort-<?= $hid ?>">
          <button type="button" class="bh-soort positief" data-soort="positief"
                  onclick="twSoort(<?= $hid ?>, 'positief', this)">Positief</button>
          <button type="button" class="bh-soort neutraal gekozen" data-soort="neutraal"
                  onclick="twSoort(<?= $hid ?>, 'neutraal', this)">Neutraal</button>
          <button type="button" class="bh-soort aandacht" data-soort="aandacht"
                  onclick="twSoort(<?= $hid ?>, 'aandacht', this)">Aandacht</button>
        </div>
        <label class="sr-only" for="obs-<?= $hid ?>">Observatie over <?= h((string)$huidig['naam']) ?></label>
        <textarea class="ds-textarea" id="obs-<?= $hid ?>" rows="2" placeholder="Bijvoorbeeld: pakt aan zonder vragen, gaat goed om met kritiek…"></textarea>
        <button type="button" class="ds-btn ds-btn-primair ds-btn-vol" onclick="twObs(<?= $hid ?>)">
          Observatie opslaan
        </button>
        <p class="ds-veld-hint">
          Observaties zijn voor de nabespreking. De sollicitant ziet ze niet.
        </p>
      <?php endif; ?>

      <ul class="bh-observaties" id="obslijst-<?= $hid ?>">
        <?php foreach ($obs as $_o):
          $_kleur = ['positief' => 'groen', 'aandacht' => 'rood'][$_o['soort'] ?? ''] ?? 'grijs';
          $_eigen = ($_o['beoordelaar'] ?? '') === $ik;
        ?>
        <li class="ds-fase-<?= h($_kleur) ?>" id="obs-<?= (int)$_o['id'] ?>">
          <span class="bh-obs-stip"></span>
          <span class="bh-obs-tekst">
            <?= nl2br(h((string)$_o['tekst'])) ?>
            <span class="bh-obs-wie"><?= h((string)($_o['beoordelaar'] ?? '')) ?></span>
          </span>
          <?php if ($_eigen): ?>
            <button type="button" class="bh-obs-wis" aria-label="Observatie verwijderen"
                    onclick="twObsWis(<?= (int)$_o['id'] ?>)"><?= icoon('kruis', 14) ?></button>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <footer class="bh-beoordeel-voet">
      <?php $_v = $pos > 0 ? $kandidaten[$pos - 1] : null; $_n = $kandidaten[$pos + 1] ?? null; ?>
      <?php if ($_v): ?>
        <a class="ds-btn ds-btn-secundair" href="<?= h(tw_url('weekend', (int)$_v['sol_id'])) ?>" rel="prev">Vorige</a>
      <?php else: ?>
        <span class="ds-btn ds-btn-zacht" aria-disabled="true">Vorige</span>
      <?php endif; ?>
      <?php if ($_n): ?>
        <a class="ds-btn ds-btn-primair bh-beoordeel-volgende" href="<?= h(tw_url('weekend', (int)$_n['sol_id'])) ?>" rel="next">
          Volgende: <?= h((string)$_n['voornaam']) ?>
        </a>
      <?php elseif ($mag_beslissen): ?>
        <a class="ds-btn ds-btn-primair bh-beoordeel-volgende" href="<?= h(tw_url('besluit')) ?>">Naar het besluit</a>
      <?php endif; ?>
    </footer>
  </section>
  <?php endif; ?>
</div>

<script>
const TW_SOORT = {};
function twPost(body) {
  body.append('_csrf', CSRF);
  return fetch(APP_URL + '/admin/trainingsweekend.php', {
    method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body
  }).then(function (r) { return r.json(); });
}

/* Tikken is opslaan, net als op de selectiedag. Geen bewaarknop: je staat buiten
   met een telefoon in je hand en loopt zo weer weg. */
function twAanwezig(id, aan) {
  var body = new URLSearchParams({ actie: 'aanwezig', sol_id: id, aan: aan ? '1' : '' });
  twPost(body).then(function (d) {
    if (!d.ok) { bhToast('Opslaan mislukt.', 'fout'); return; }
    location.reload();
  }).catch(function () { bhToast('Verbindingsfout — probeer opnieuw.', 'fout'); });
}

/* Bij "niet gekomen" vragen we waarom. Ziek is iets anders dan niet komen opdagen,
   en dat verschil telt bij het besluit. */
function twAfwezig(id) {
  bhVraag({
    titel: 'Niet gekomen — waarom?',
    tekst: 'Eén regel is genoeg. Dit staat straks bij het besluit, zodat je het verschil ziet tussen afgemeld en niet komen opdagen.',
    knop: 'Opslaan'
  }).then(function (reden) {
    if (reden === null) return;
    var body = new URLSearchParams({ actie: 'aanwezig', sol_id: id, aan: '', reden: reden });
    twPost(body).then(function (d) {
      if (!d.ok) { bhToast('Opslaan mislukt.', 'fout'); return; }
      location.reload();
    }).catch(function () { bhToast('Verbindingsfout — probeer opnieuw.', 'fout'); });
  });
}

function twSoort(id, soort, knop) {
  TW_SOORT[id] = soort;
  var blok = document.getElementById('soort-' + id);
  if (blok) blok.querySelectorAll('.bh-soort').forEach(function (b) { b.classList.toggle('gekozen', b === knop); });
}

function twObs(id) {
  var veld = document.getElementById('obs-' + id);
  var tekst = (veld.value || '').trim();
  if (!tekst) { bhToast('Schrijf eerst iets op.', 'fout'); return; }
  var body = new URLSearchParams({ actie: 'observatie', sol_id: id, tekst: tekst, soort: TW_SOORT[id] || 'neutraal' });
  twPost(body).then(function (d) {
    if (!d.ok) { bhToast('Opslaan mislukt.', 'fout'); return; }
    veld.value = '';
    bhToast('Observatie opgeslagen.');
    location.reload();
  }).catch(function () { bhToast('Verbindingsfout — probeer opnieuw.', 'fout'); });
}

function twObsWis(obsId) {
  bhBevestig({ titel: 'Observatie verwijderen?', tekst: 'Alleen je eigen observatie; die van anderen blijven staan.',
               knop: 'Ja, verwijderen', gevaar: true })
    .then(function (ja) {
      if (!ja) return;
      twPost(new URLSearchParams({ actie: 'observatie_wis', id: obsId })).then(function (d) {
        if (!d.ok) { bhToast('Verwijderen mislukt.', 'fout'); return; }
        var el = document.getElementById('obs-' + obsId);
        if (el) el.remove();
      });
    });
}
</script>

<?php else: /* ── stap: besluit ───────────────────────────────── */ ?>

<?php
$_klaar   = (int)$stand['klaar_om_door_te_voeren'];
$_twijfel = (int)$stand['twijfel_open'];
if ($stand['te_beslissen'] > 0) {
    $_toon = 'slecht';
    $_stand_woord = $stand['te_beslissen'] . ' ' . ($stand['te_beslissen'] === 1 ? 'kandidaat wacht' : 'kandidaten wachten') . ' op een besluit';
} elseif ($_klaar > 0) {
    $_toon = 'krap';
    $_stand_woord = $_klaar . ' ' . ($_klaar === 1 ? 'besluit staat' : 'besluiten staan') . ' klaar om door te voeren';
} elseif ($_twijfel > 0) {
    $_toon = 'krap';
    $_stand_woord = $_twijfel . ' op twijfel — daar moet iemand over bellen';
} else {
    $_toon = 'goed';
    $_stand_woord = 'Alles is besloten en doorgevoerd';
}
?>
<section class="ds-kaart bh-dekkop">
  <div class="bh-dekkop-zin">
    <?= bh_stoplicht($_toon, $_stand_woord) ?>
  </div>
  <p class="bh-dekkop-uitleg">
    Een besluit vastleggen doet nog niets: er verandert geen fase en er gaat geen bericht uit.
    Dat gebeurt pas als je hieronder doorvoert. Zo kun je eerst de hele groep langslopen.
    <?php if ($_twijfel > 0): ?>
      <strong>Twijfel gaat nooit vanzelf de deur uit</strong> — dat is een telefoontje, geen mail.
      <?= $_twijfel === 1 ? 'Eén kandidaat staat' : $_twijfel . ' kandidaten staan' ?> daarop te wachten.
    <?php endif; ?>
  </p>
  <?php if ($_klaar > 0): ?>
  <form class="bh-dekkop-legenda" method="POST"
        data-bevestig-titel="<?= $_klaar ?> <?= $_klaar === 1 ? 'besluit' : 'besluiten' ?> doorvoeren?"
        data-bevestig="Iedereen die je hebt aangenomen of afgewezen krijgt hierover bericht, en de fase verandert mee. Kandidaten op twijfel blijven staan — die bel je zelf."
        data-bevestig-knop="Ja, doorvoeren">
    <?= csrf_input() ?>
    <input type="hidden" name="actie" value="doorvoeren">
    <button type="submit" class="ds-btn ds-btn-primair">
      <?= $_klaar ?> <?= $_klaar === 1 ? 'besluit' : 'besluiten' ?> doorvoeren
    </button>
  </form>
  <?php endif; ?>
</section>

<div class="bh-twbesluiten">
  <?php foreach ($kandidaten as $_k):
    $_id   = (int)$_k['sol_id'];
    $_obs  = $obs_alle[$_id] ?? [];
    $_af   = empty($_k['aanwezig']) && ($_k['ingecheckt_op'] !== null || $_k['afgemeld_reden'] !== null);
    $_door = !empty($_k['doorgevoerd_op']);
    $_b    = (string)($_k['besluit'] ?? '');
    $_kleur = ['aannemen' => 'groen', 'twijfel' => 'oranje', 'afwijzen' => 'rood'][$_b] ?? 'grijs';
  ?>
  <section class="ds-kaart bh-twkaart ds-fase-<?= h($_kleur) ?><?= $_door ? ' doorgevoerd' : '' ?>">
    <header class="bh-twkaart-kop">
      <?= bh_avatar(['voornaam' => $_k['voornaam'], 'achternaam' => $_k['achternaam']], 38, ['dim' => $_door]) ?>
      <span class="bh-twkaart-wie">
        <a class="bh-twkaart-naam" href="<?= APP_URL ?>/admin/sollicitant.php?id=<?= $_id ?>"><?= h((string)$_k['naam']) ?></a>
        <span class="bh-meta">
          <?php if ($_af): ?>
            niet gekomen<?= !empty($_k['afgemeld_reden']) ? ' — ' . h((string)$_k['afgemeld_reden']) : '' ?>
          <?php elseif (!empty($_k['aanwezig'])): ?>
            het hele weekend erbij
          <?php else: ?>
            aanwezigheid niet afgevinkt
          <?php endif; ?>
          · <?= count($_obs) ?> <?= count($_obs) === 1 ? 'observatie' : 'observaties' ?>
        </span>
      </span>
      <?php if ($_door): ?>
        <?= bh_badge('Doorgevoerd · ' . fase_label((string)$_k['fase']), 'grijs') ?>
      <?php elseif ($_b !== ''): ?>
        <?= bh_badge(['aannemen' => 'Aannemen', 'twijfel' => 'Twijfel', 'afwijzen' => 'Afwijzen'][$_b], $_kleur) ?>
      <?php endif; ?>
    </header>

    <?php if ($_obs): ?>
    <ul class="bh-observaties bh-twkaart-obs">
      <?php foreach ($_obs as $_o):
        $_ok = ['positief' => 'groen', 'aandacht' => 'rood'][$_o['soort'] ?? ''] ?? 'grijs';
      ?>
      <li class="ds-fase-<?= h($_ok) ?>">
        <span class="bh-obs-stip"></span>
        <span class="bh-obs-tekst">
          <?= nl2br(h((string)$_o['tekst'])) ?>
          <span class="bh-obs-wie"><?= h((string)($_o['beoordelaar'] ?? '')) ?></span>
        </span>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?>
      <p class="bh-meta bh-twkaart-geenobs">
        Niemand heeft iets opgeschreven. Dat hoeft niets te betekenen — maar het is wel
        weinig om een besluit op te baseren.
      </p>
    <?php endif; ?>

    <?php if ($_door): ?>
      <p class="bh-meta bh-twkaart-geenobs">
        Doorgevoerd op <?= h(datum_nl(substr((string)$_k['doorgevoerd_op'], 0, 10))) ?><?php
          if (!empty($_k['besluit_door'])): ?> · besluit van <?= h((string)$_k['besluit_door']) ?><?php endif; ?>.
      </p>
    <?php else: ?>
    <form class="bh-twkaart-form" method="POST">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="besluit">
      <input type="hidden" name="sol_id" value="<?= $_id ?>">
      <div class="bh-twkeuze" role="radiogroup" aria-label="Besluit over <?= h((string)$_k['naam']) ?>">
        <?php foreach (['aannemen' => 'Aannemen', 'twijfel' => 'Twijfel', 'afwijzen' => 'Afwijzen'] as $_w => $_l): ?>
          <label class="bh-twoptie <?= h($_w) ?>">
            <input type="radio" name="besluit" value="<?= h($_w) ?>" <?= $_b === $_w ? 'checked' : '' ?>>
            <span><?= h($_l) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <div class="ds-veld">
        <label class="sr-only" for="not-<?= $_id ?>">Toelichting bij het besluit over <?= h((string)$_k['naam']) ?></label>
        <input type="text" id="not-<?= $_id ?>" name="notitie" maxlength="300"
               value="<?= h((string)($_k['besluit_notitie'] ?? '')) ?>"
               placeholder="Waarom? Eén regel — dit komt in het dossier.">
      </div>
      <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Besluit vastleggen</button>
    </form>
    <?php endif; ?>
  </section>
  <?php endforeach; ?>
</div>

<?php endif; ?>

<?php require __DIR__ . '/layout_bottom.php'; ?>
