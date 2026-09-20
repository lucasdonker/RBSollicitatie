<?php
/**
 * Partial: de bellijst.
 *
 * Links wie er gebeld moet worden, rechts het gesprek met de kandidaat die aan de beurt
 * is. Het bestuur belt afwijzingen altijd persoonlijk; daarom staat de afwijsmail
 * standaard uit en voer je per persoon door nádat je hebt gebeld.
 *
 * LET OP — wat hier (nog) niet staat: het ontwerp toont onderaan "N berichten
 * klaargezet, nog niet verstuurd" met een knop "Ronde afsluiten en versturen". Dat
 * vraagt een berichtenbuffer die er nog niet is; tot die er is verstuurt doorvoeren
 * direct. Een knop die bundeling belooft terwijl er direct wordt verstuurd, is erger
 * dan geen knop.
 *
 * Verwacht:
 *   $bel_sid         int    seizoen-id
 *   $bel_lijst       array  kandidaten met een definitief besluit, nog niet doorgevoerd
 *   $bel_besluiten   array  [sol_id => besluit-rij]
 *   $bel_gebeld      int
 *   $bel_ongebeld    int
 */
$bel_sid        = (int)($bel_sid ?? 0);
$bel_lijst      = $bel_lijst ?? [];
$bel_besluiten  = $bel_besluiten ?? [];
$bel_gebeld     = (int)($bel_gebeld ?? 0);
$bel_ongebeld   = (int)($bel_ongebeld ?? 0);

if (!$bel_lijst) {
    echo '<div class="ds-kaart" style="padding:16px">';
    $leeg_titel = 'De bellijst is leeg';
    $leeg_tekst = 'Iedereen met een definitief besluit is doorgevoerd. Staat er nog een twijfelgeval open, '
                . 'dan vind je die op het tabblad Nabespreking.';
    $leeg_toon  = 'groen';
    $leeg_icoon = 'vink';
    require __DIR__ . '/_leeg.php';
    echo '</div>';
    return;
}

// Wie is aan de beurt: de eerste die nog niet gebeld is.
$_open = (int)($_GET['bel'] ?? 0);
if (!$_open) {
    foreach ($bel_lijst as $_k) {
        if (empty($bel_besluiten[(int)$_k['sol_id']]['gebeld_op'])) { $_open = (int)$_k['sol_id']; break; }
    }
    if (!$_open) $_open = (int)$bel_lijst[0]['sol_id'];
}
$_huidig = null;
foreach ($bel_lijst as $_k) if ((int)$_k['sol_id'] === $_open) $_huidig = $_k;
?>
<div class="bh-bellijst">

  <div class="bh-bel-lijst ds-kaart">
    <div class="bh-bel-kop">
      <span><strong class="bh-num"><?= $bel_gebeld ?></strong> van <strong class="bh-num"><?= $bel_gebeld + $bel_ongebeld ?></strong> gebeld</span>
      <?php if ($bel_gebeld > 0): ?>
        <form method="post" id="alleDoorForm" style="margin-left:auto">
          <?= csrf_input() ?>
          <input type="hidden" name="actie" value="gebelde_doorvoeren">
          <button type="button" class="bh-doc-actie" onclick="alleGebeldeDoorvoeren(<?= $bel_gebeld ?>)">
            Alle gebelde doorvoeren
          </button>
        </form>
      <?php endif; ?>
    </div>

    <table class="bh-tabel bh-bel-tabel" style="--bh-tracks:minmax(150px,2fr) 66px minmax(96px,1fr) 104px">
      <thead>
        <tr>
          <th scope="col">Kandidaat</th>
          <th scope="col" class="bh-rechts">Score</th>
          <th scope="col">Besluit</th>
          <th scope="col" class="bh-rechts">Gebeld</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($bel_lijst as $_k):
          $_id = (int)$_k['sol_id'];
          $_b   = $bel_besluiten[$_id] ?? [];
          $_gem = nb_gemiddelde(selectie_scores_van($bel_sid, $_id));
          $_isnu = $_id === $_open;
          $_gebeld = !empty($_b['gebeld_op']);
        ?>
          <tr class="<?= $_isnu ? 'bh-rij-gemarkeerd' : '' ?><?= $_gebeld ? ' bh-rij-gedempt' : '' ?>">
            <td data-label="Kandidaat">
              <a class="bh-naamcel" href="?tab=bellijst&bel=<?= $_id ?>">
                <span class="bh-naamcel-tekst">
                  <span class="bh-naamcel-naam"><?= h((string)$_k['naam']) ?></span>
                  <span class="bh-naamcel-sub"><?= h((string)($_k['telefoon'] ?: 'geen nummer')) ?></span>
                </span>
              </a>
            </td>
            <td data-label="Score" class="bh-rechts bh-num">
              <?= $_gem !== null ? h(number_format($_gem, 1, ',', '')) : '—' ?>
            </td>
            <td data-label="Besluit">
              <?= bh_badge(ucfirst((string)($_b['besluit'] ?? '')),
                           ($_b['besluit'] ?? '') === 'door' ? 'groen' : 'rood') ?>
            </td>
            <td data-label="Gebeld" class="bh-rechts">
              <?php if ($_gebeld): ?>
                <span class="bh-meta"><?= h(datum_nl(substr((string)$_b['gebeld_op'], 0, 10))) ?></span>
              <?php elseif ($_isnu): ?>
                <span class="bh-bel-nu">aan de beurt</span>
              <?php else: ?>
                <span class="bh-meta">nee</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($_huidig):
    $_id = (int)$_huidig['sol_id'];
    $_b  = $bel_besluiten[$_id] ?? [];
    $_obs = selectie_observaties_van($bel_sid, $_id);
    $_door = ($_b['besluit'] ?? '') === 'door';
    $_gebeld = !empty($_b['gebeld_op']);
  ?>
  <section class="bh-bel-gesprek ds-kaart">
    <header class="bh-bel-gesprek-kop">
      <?= bh_avatar(['voornaam' => $_huidig['voornaam'], 'achternaam' => $_huidig['achternaam']], 44) ?>
      <span class="bh-beoordeel-wie">
        <span class="bh-beoordeel-naam"><?= h((string)$_huidig['naam']) ?></span>
        <span class="bh-meta">
          <?= $_huidig['leeftijd'] ? $_huidig['leeftijd'] . ' jaar · ' : '' ?>
          besluit: <?= h((string)($_b['besluit'] ?? '')) ?>
        </span>
      </span>
      <?php if (!empty($_huidig['telefoon'])): ?>
        <a class="ds-btn ds-btn-primair" href="tel:<?= h(preg_replace('/\s+/', '', (string)$_huidig['telefoon'])) ?>">
          Bellen
        </a>
      <?php else: ?>
        <span class="bh-meta">geen nummer bekend</span>
      <?php endif; ?>
    </header>

    <div class="bh-bel-inhoud">
      <?php if (!empty($_b['notitie'])): ?>
        <div>
          <span class="bh-veldlabel">Reden bij het besluit</span>
          <p class="bh-dossier-tekst"><?= nl2br(h((string)$_b['notitie'])) ?></p>
        </div>
      <?php endif; ?>

      <?php if ($_obs): ?>
        <div>
          <span class="bh-veldlabel">Observaties van die dag</span>
          <ul class="bh-observaties" style="margin-top:8px">
            <?php foreach (array_slice($_obs, 0, 4) as $_o):
              $_ok = ['positief' => 'groen', 'aandacht' => 'rood'][$_o['soort'] ?? ''] ?? 'grijs'; ?>
              <li class="ds-fase-<?= h($_ok) ?>">
                <span class="bh-obs-stip"></span>
                <span class="bh-obs-tekst">
                  <?= nl2br(h((string)$_o['tekst'])) ?>
                  <span class="bh-obs-wie"><?= h((string)($_o['beoordelaar'] ?? '')) ?></span>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <div>
        <span class="bh-veldlabel" id="lbl-gespreksnotitie">Wat heb je verteld?</span>
        <textarea class="ds-textarea" id="gesprek-<?= $_id ?>" rows="3"
                  aria-labelledby="lbl-gespreksnotitie"
                  placeholder="Korte aantekening van het gesprek…"><?= h((string)($_b['gebeld_notitie'] ?? '')) ?></textarea>
      </div>
    </div>

    <footer class="bh-bel-acties">
      <?php if ($_gebeld): ?>
        <div class="ds-melding ds-melding-ok" style="width:100%">
          <?= icoon('vink-cirkel', 16) ?>
          <span>Gebeld op <?= h(datum_nl(substr((string)$_b['gebeld_op'], 0, 10))) ?>. Nog niet doorgevoerd.</span>
        </div>
        <button type="button" class="ds-btn ds-btn-primair"
                onclick="doorvoerenEen(<?= $_id ?>, <?= $_door ? 'true' : 'false' ?>)">
          <?= $_door ? 'Doorzetten naar trainingsweekend' : 'Afwijzen doorvoeren' ?>
        </button>
        <button type="button" class="ds-btn ds-btn-zacht" onclick="gebeldWissen(<?= $_id ?>)">
          Toch niet gebeld
        </button>
      <?php else: ?>
        <button type="button" class="ds-btn <?= $_door ? 'ds-btn-primair' : 'ds-btn-gevaar' ?>"
                onclick="gebeldZet(<?= $_id ?>, true)">
          Gebeld — <?= $_door ? 'doorzetten' : 'afwijzen' ?>
        </button>
        <button type="button" class="ds-btn ds-btn-secundair" onclick="gebeldZet(<?= $_id ?>, false)">
          Gebeld, later doorvoeren
        </button>
        <button type="button" class="ds-btn ds-btn-zacht" onclick="volgendeBellen()">Overslaan</button>
      <?php endif; ?>
    </footer>
  </section>
  <?php endif; ?>
</div>

<script>
const CSRF = '<?= csrf_token() ?>';

function belPost(body) {
  return fetch(location.pathname, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams(Object.assign({ _csrf: CSRF }, body))
  }).then(function (r) { return r.json(); });
}

function gebeldZet(solId, meteen) {
  const notitie = document.getElementById('gesprek-' + solId).value;
  const doen = function () {
    belPost({ actie: 'gebeld', sol_id: solId, gebeld_notitie: notitie, meteen_doorvoeren: meteen ? '1' : '' })
      .then(function (d) {
        if (!d.ok) { bhToast('Opslaan mislukt.', 'fout'); return; }
        location.reload();
      })
      .catch(function () { bhToast('Geen verbinding — probeer opnieuw.', 'fout'); });
  };
  if (!meteen) { doen(); return; }
  bhBevestig({
    titel: 'Gebeld en meteen doorvoeren?',
    tekst: 'De fase gaat om en het bijbehorende bericht gaat uit. Dat kun je niet terugnemen.',
    knop: 'Ja, doorvoeren'
  }).then(function (ja) { if (ja) doen(); });
}

function gebeldWissen(solId) {
  belPost({ actie: 'gebeld_wissen', sol_id: solId })
    .then(function () { location.reload(); })
    .catch(function () { bhToast('Geen verbinding — probeer opnieuw.', 'fout'); });
}

function doorvoerenEen(solId, isDoor) {
  bhBevestig({
    titel: isDoor ? 'Doorzetten naar het trainingsweekend?' : 'Deze kandidaat afwijzen?',
    tekst: 'De fase gaat om en het bijbehorende bericht gaat uit. Dat kun je niet terugnemen.',
    knop: isDoor ? 'Ja, doorzetten' : 'Ja, afwijzen', gevaar: !isDoor
  }).then(function (ja) {
    if (!ja) return;
    belPost({ actie: 'doorvoeren_een', sol_id: solId })
      .then(function (d) {
        if (!d.ok) { bhToast('Doorvoeren mislukt.', 'fout'); return; }
        location.reload();
      })
      .catch(function () { bhToast('Geen verbinding — probeer opnieuw.', 'fout'); });
  });
}

function alleGebeldeDoorvoeren(n) {
  bhBevestig({
    titel: n + ' gebelde kandidaten doorvoeren?',
    tekst: 'Iedereen die je als gebeld hebt gemarkeerd gaat naar zijn nieuwe fase, en de bijbehorende '
         + 'berichten gaan uit. Dat kun je niet terugnemen.',
    knop: 'Ja, doorvoeren'
  }).then(function (ja) { if (ja) document.getElementById('alleDoorForm').submit(); });
}

function volgendeBellen() {
  const rijen = document.querySelectorAll('.bh-bel-tabel tbody tr');
  let na = false;
  for (const r of rijen) {
    const link = r.querySelector('a');
    if (na && link) { location.href = link.getAttribute('href'); return; }
    if (r.classList.contains('bh-rij-gemarkeerd')) na = true;
  }
  bhToast('Dit was de laatste in de lijst.', '');
}
</script>
<?php
$bel_sid = 0; $bel_lijst = []; $bel_besluiten = []; $bel_gebeld = 0; $bel_ongebeld = 0;
