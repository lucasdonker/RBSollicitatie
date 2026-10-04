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
 * Twijfel staat er ook op: na het gesprek wordt het door of niet. Bij "door" leg je meteen
 * vast of iemand op het trainingsweekend komt. Wie een persoon opent, claimt hem 30 minuten
 * ("bezig met bellen door X"), zodat niet twee mensen dezelfde kandidaat bellen.
 *
 * Verwacht:
 *   $bel_sid         int    seizoen-id
 *   $bel_lijst       array  kandidaten met een besluit (door/twijfel/niet), nog niet doorgevoerd
 *   $bel_besluiten   array  [sol_id => besluit-rij]
 *   $bel_scores      array  selectie_scores_alle()
 *   $bel_obs         array  selectie_observaties_alle()
 *   $bel_lgf         array  lg_feedback_alle() — feedback van lifeguards, alleen lezen
 *   $bel_nb          array  [sol_id => bh_nb_analyse()] — advies en signalen uit de nabespreking
 *   $bel_groepen     array  [groep_id => naam]
 *   $bel_gebeld      int
 *   $bel_ongebeld    int
 *
 * De kaart rechts is het spiekbriefje voor het gesprek: alles wat je nodig hebt staat erop
 * (besluit en reden, advies en signalen, cijfers, alle observaties, feedback, bijzonderheid),
 * met het nummer groot, want je belt meestal vanaf je telefoon met de pc ernaast.
 */
$bel_sid        = (int)($bel_sid ?? 0);
$bel_lijst      = $bel_lijst ?? [];
$bel_besluiten  = $bel_besluiten ?? [];
$bel_scores     = $bel_scores ?? [];
$bel_obs        = $bel_obs ?? [];
$bel_nb         = $bel_nb ?? [];
$bel_groepen    = $bel_groepen ?? [];
$bel_gebeld     = (int)($bel_gebeld ?? 0);
$bel_ongebeld   = (int)($bel_ongebeld ?? 0);
$_ik            = (int)($_SESSION['bhd_id'] ?? 0);
$_besluit_kleur = ['door' => 'groen', 'twijfel' => 'oranje', 'niet' => 'rood'];
// Wat doorvoeren doet, in woorden die kloppen met de meldingsinstellingen (de afwijsmail staat standaard uit).
$_doorvoer_tekst = static function (bool $door): string {
    $mail = bh_melding_gaat_uit(fase_meldingstypen($door ? 'uitgenodigd_tw' : 'afgewezen'));
    return ($door ? 'De fase wordt Uitgenodigd trainingsweekend' : 'De fase wordt Afgewezen')
         . ($mail ? ' en het bericht gaat meteen uit. Een verstuurd bericht neem je niet terug.'
                  : '. Er gaat geen bericht uit (staat uit bij Instellingen).');
};

if (!$bel_lijst) {
    echo '<div class="ds-kaart ds-kaart-p">';
    $leeg_titel = 'De bellijst is leeg';
    $leeg_tekst = 'Iedereen met een definitief besluit is doorgevoerd. Staat er nog een twijfelgeval open, '
                . 'dan vind je die op het tabblad Nabespreking.';
    $leeg_toon  = 'groen';
    $leeg_icoon = 'vink';
    require __DIR__ . '/_leeg.php';
    echo '</div>';
    return;
}

// Wie is aan de beurt: de eerste die nog niet gebeld is en die geen collega al aan het bellen is.
$_open = (int)($_GET['bel'] ?? 0);
if (!$_open) {
    foreach ($bel_lijst as $_k) {
        $_kb = $bel_besluiten[(int)$_k['sol_id']] ?? [];
        if (empty($_kb['gebeld_op']) && !evenement_claim_actief($_kb['bel_door'] ?? null, $_kb['bel_sinds'] ?? null, $_ik)) {
            $_open = (int)$_k['sol_id']; break;
        }
    }
    if (!$_open) $_open = (int)$bel_lijst[0]['sol_id'];
}
$_huidig = null;
foreach ($bel_lijst as $_k) if ((int)$_k['sol_id'] === $_open) $_huidig = $_k;
?>
<div class="bh-bellijst">

  <?php // data-live-blok: gebeld of bezig door een collega verschijnt vanzelf (assets/js/live.js). ?>
  <div class="bh-bel-lijst ds-kaart" data-live-blok id="bel-lijst">
    <div class="bh-bel-kop">
      <span><strong class="bh-num"><?= $bel_gebeld ?></strong> van <strong class="bh-num"><?= $bel_gebeld + $bel_ongebeld ?></strong> gebeld</span>
      <?php if ($bel_gebeld > 0): ?>
        <form method="post" class="bh-bel-alledoor" data-bevestig-titel="<?= $bel_gebeld ?> gebelde doorvoeren?"
              data-bevestig="Iedereen die als gebeld staat, gaat naar de nieuwe fase: door naar het trainingsweekend, niet door naar Afgewezen. Berichten gaan uit volgens de instellingen; een verstuurd bericht neem je niet terug."
              data-bevestig-knop="Ja, doorvoeren">
          <?= csrf_input() ?>
          <input type="hidden" name="actie" value="gebelde_doorvoeren">
          <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Alle gebelde doorvoeren (<?= $bel_gebeld ?>)</button>
        </form>
      <?php endif; ?>
    </div>

    <table class="bh-tabel bh-bel-tabel" style="--bh-tracks:minmax(150px,2fr) 66px minmax(96px,1fr) 104px">
      <thead>
        <tr>
          <th scope="col">Sollicitant</th>
          <th scope="col" class="bh-rechts">Score</th>
          <th scope="col">Besluit</th>
          <th scope="col" class="bh-rechts">Gebeld</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($bel_lijst as $_k):
          $_id = (int)$_k['sol_id'];
          $_b   = $bel_besluiten[$_id] ?? [];
          $_gem = selectie_gemiddelde($bel_scores[$_id] ?? []);
          $_isnu = $_id === $_open;
          $_gebeld = !empty($_b['gebeld_op']);
          $_bezig = !$_gebeld && evenement_claim_actief($_b['bel_door'] ?? null, $_b['bel_sinds'] ?? null, $_ik);
        ?>
          <tr class="<?= $_isnu ? 'bh-rij-gemarkeerd' : '' ?><?= $_gebeld ? ' bh-rij-gedempt' : '' ?>">
            <td data-label="Sollicitant">
              <a class="bh-naamcel" href="?tab=bellijst&bel=<?= $_id ?>">
                <span class="bh-naamcel-tekst">
                  <span class="bh-naamcel-naam"><?= h((string)$_k['naam']) ?></span>
                  <span class="bh-naamcel-sub"><?= h($_k['telefoon'] ? telefoon_opmaak((string)$_k['telefoon']) : 'geen nummer') ?></span>
                </span>
              </a>
            </td>
            <td data-label="Score" class="bh-rechts bh-num">
              <?= h(cijfer_nl($_gem)) ?>
            </td>
            <td data-label="Besluit">
              <?= bh_badge(ucfirst((string)($_b['besluit'] ?? '')), $_besluit_kleur[$_b['besluit'] ?? ''] ?? 'grijs') ?>
            </td>
            <td data-label="Gebeld" class="bh-rechts">
              <?php if ($_gebeld): ?>
                <span class="bh-meta"><?= h(datum_nl(substr((string)$_b['gebeld_op'], 0, 10))) ?></span>
              <?php elseif ($_bezig): ?>
                <span class="bh-meta">bezig: <?= h(evenement_claim_naam((int)$_b['bel_door'])) ?></span>
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
    $_obs = $bel_obs[$_id] ?? [];
    $_besl = (string)($_b['besluit'] ?? '');
    $_door = $_besl === 'door';
    $_gebeld = !empty($_b['gebeld_op']);
    // Openen = claimen (30 minuten). Heeft een ander hem, dan kijk je mee maar bel je niet.
    // Je belt één persoon tegelijk: wie je hiervoor open had, komt vrij voor je collega's.
    evenement_claim_los_overige('selectie_besluit', 'bel', $bel_sid, $_ik, $_id);
    $_claim = $_gebeld ? ['ok' => true] : evenement_claim('selectie_besluit', 'bel', $bel_sid, $_id, $_ik);
    $_tw = evenement_bevestiging_van('tw', $bel_sid, $_id);
    $_nb = $bel_nb[$_id] ?? null;
    $_tel = trim((string)($_huidig['telefoon'] ?? ''));
    $_obs_tel = array_count_values(array_map(fn($o) => (string)($o['soort'] ?? 'neutraal'), $_obs));
    $_obs_sam = implode(' · ', array_filter([
        !empty($_obs_tel['positief']) ? $_obs_tel['positief'] . ' positief' : '',
        !empty($_obs_tel['neutraal']) ? $_obs_tel['neutraal'] . ' neutraal' : '',
        !empty($_obs_tel['aandacht']) ? $_obs_tel['aandacht'] . ' aandacht' : '',
    ]));
  ?>
  <?php // Ook de kaart ververst (id per persoon), maar niet zolang je een aantekening typt. ?>
  <section class="bh-bel-gesprek ds-kaart" aria-labelledby="bel-naam-<?= $_id ?>" data-live-blok id="bel-kaart-<?= $_id ?>">
    <header class="bh-bel-gesprek-kop">
      <?= bh_avatar($_huidig, 44, ['vergroot' => true]) ?>
      <span class="bh-beoordeel-wie">
        <a class="bh-beoordeel-naam" id="bel-naam-<?= $_id ?>" href="<?= APP_URL ?>/admin/sollicitant.php?id=<?= $_id ?>&amp;tab=selectiedag"><?= h((string)$_huidig['naam']) ?></a>
        <?= bh_meta([
            $_huidig['leeftijd'] ? $_huidig['leeftijd'] . ' jaar' : '',
            !empty($_huidig['groep_id']) ? ($bel_groepen[(int)$_huidig['groep_id']] ?? '') : '',
            empty($_huidig['aanwezig']) ? 'niet aanwezig geweest' : '',
        ]) ?>
      </span>
      <span class="bh-bel-besluit">
        <?= bh_badge('Besluit: ' . mb_strtolower(selectie_besluit_labels()[$_besl][0] ?? 'nog geen'), $_besluit_kleur[$_besl] ?? 'grijs') ?>
        <?php if (!empty($_nb['advies'])): ?>
          <?= bh_badge('Advies: ' . mb_strtolower($_nb['advies']['label']), $_nb['advies']['kleur'], ['titel' => $_nb['advies']['reden']]) ?>
        <?php endif; ?>
      </span>
    </header>

    <div class="bh-bel-telefoon">
      <?= icoon('telefoon', 20) ?>
      <?php if ($_tel !== ''): ?>
        <span class="bh-bel-nummer" id="tel-<?= $_id ?>"><?= h(telefoon_opmaak($_tel)) ?></span>
        <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" data-klik="belKopieer(<?= $_id ?>, this)">Kopieer</button>
        <a class="ds-btn ds-btn-primair ds-btn-sm" href="tel:<?= h(preg_replace('/[^\d+]/', '', $_tel)) ?>">Bellen</a>
      <?php else: ?>
        <span class="bh-meta">Geen telefoonnummer bekend. Kijk in het dossier of er een ander adres is.</span>
      <?php endif; ?>
    </div>

    <div class="bh-bel-inhoud"<?= $_claim['ok'] ? '' : ' inert' ?>>
      <div class="bh-bel-blok">
        <span class="bh-veldlabel">Waarom dit besluit</span>
        <p class="bh-dossier-tekst"><?= !empty($_b['notitie']) ? nl2br(h((string)$_b['notitie'])) : '<span class="zacht">Geen reden genoteerd in de nabespreking.</span>' ?></p>
        <?php if (!empty($_nb['signalen'])): ?>
          <div class="bh-nb-signalen" aria-label="Signalen">
            <?php foreach ($_nb['signalen'] as $_s) echo bh_chip($_s['tekst'], bh_nb_signaal_kleur($_s['soort'])); ?>
          </div>
        <?php endif; ?>
        <?php if ($_tw['stand'] !== 'onbekend'): ?>
          <p class="bh-meta">Trainingsweekend: <?= h(mb_strtolower(evenement_bevestig_standen()[$_tw['stand']])) ?><?= !empty($_tw['reden']) ? ' — ' . h((string)$_tw['reden']) : '' ?></p>
        <?php endif; ?>
      </div>

      <?php if (!empty($_huidig['bijzonderheden'])): ?>
        <div class="ds-melding ds-melding-warn">
          <?= icoon('waarschuwing', 16) ?>
          <?php if (mag('blk', 'lezen')): ?>
            <span><strong>Bijzonderheid:</strong> <?= nl2br(h((string)$_huidig['bijzonderheden'])) ?></span>
          <?php else: ?>
            <span><strong>Let op:</strong> er is een bijzonderheid bekend; die staat in het formulier.</span>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="bh-bel-blok">
        <span class="bh-veldlabel">Cijfers op de selectiedag</span>
        <?php if (!empty($bel_scores[$_id])): $sck_scores = $bel_scores[$_id]; require __DIR__ . '/_sd_cijfers_kort.php'; ?>
        <?php else: ?><p class="bh-dossier-tekst zacht">Niet beoordeeld.</p><?php endif; ?>
      </div>

      <?php if ($_obs): ?>
      <details class="bh-klap bh-bel-blok" open data-klap-id="bel-obs">
        <summary>
          <span class="bh-klap-titel">Observaties</span>
          <span class="bh-klap-sam"><?= h($_obs_sam) ?></span>
        </summary>
        <div class="bh-klap-inhoud">
          <?php $obs_lijst = $_obs; $obs_plat = true; $obs_eigen = null; require __DIR__ . '/_observaties.php'; ?>
        </div>
      </details>
      <?php else: ?>
      <div class="bh-bel-blok">
        <span class="bh-veldlabel">Observaties</span>
        <p class="bh-dossier-tekst zacht">Niemand heeft iets genoteerd.</p>
      </div>
      <?php endif; ?>

      <?php if (!empty($bel_lgf[$_id])): ?>
        <div class="bh-bel-blok">
          <span class="bh-veldlabel">Feedback van strandwachten</span>
          <?php $obs_lijst = lg_feedback_als_observaties($bel_lgf[$_id]); $obs_plat = true; $obs_eigen = null; require __DIR__ . '/_observaties.php'; ?>
        </div>
      <?php endif; ?>

      <div>
        <span class="bh-veldlabel" id="lbl-gespreksnotitie">Wat heb je verteld?</span>
        <textarea class="ds-textarea" id="gesprek-<?= $_id ?>" rows="3"
                  aria-labelledby="lbl-gespreksnotitie"
                  placeholder="Korte aantekening van het gesprek…"><?= h((string)($_b['gebeld_notitie'] ?? '')) ?></textarea>
      </div>

      <?php if (!$_gebeld && $_besl !== 'niet'): ?>
      <fieldset class="bh-bel-tw" id="tw-<?= $_id ?>">
        <legend class="bh-veldlabel">Komt op het trainingsweekend?<?= $_besl === 'twijfel' ? ' (alleen als het door wordt)' : '' ?></legend>
        <div class="bh-knoprij">
          <?php foreach (['onbekend' => 'Weet nog niet', 'komt' => 'Ja', 'komt_niet' => 'Nee'] as $_tk => $_tl): ?>
            <label class="bh-bel-twkeuze"><input type="radio" name="tw_<?= $_id ?>" value="<?= $_tk ?>" <?= $_tw['stand'] === $_tk ? 'checked' : '' ?>> <?= h($_tl) ?></label>
          <?php endforeach; ?>
        </div>
        <input type="text" class="ds-input" id="twreden-<?= $_id ?>" maxlength="300" placeholder="Opmerking (verplicht bij nee)"
               value="<?= h((string)($_tw['reden'] ?? '')) ?>" aria-label="Opmerking over het trainingsweekend, verplicht als iemand niet kan">
      </fieldset>
      <?php endif; ?>
    </div>

    <footer class="bh-bel-acties">
      <?php if (!$_claim['ok']): ?>
        <div class="ds-melding ds-melding-warn">
          <?= icoon('telefoon', 16) ?>
          <span><?= h(evenement_claim_naam($_claim['door'])) ?> is deze sollicitant aan het bellen (sinds <?= h(date('H:i', strtotime((string)$_claim['sinds']))) ?>).
            Na 30 minuten komt hij weer vrij.</span>
        </div>
      <?php elseif ($_gebeld): ?>
        <div class="ds-melding ds-melding-ok bh-bel-gebeld">
          <?= icoon('vink-cirkel', 16) ?>
          <span>Gebeld op <?= h(datum_nl(substr((string)$_b['gebeld_op'], 0, 10))) ?>. Nog niet doorgevoerd.</span>
        </div>
        <button type="button" class="ds-btn ds-btn-primair" data-doorvoer-tekst="<?= h($_doorvoer_tekst($_door)) ?>"
                data-klik="doorvoerenEen(<?= $_id ?>, <?= $_door ? 'true' : 'false' ?>, this.dataset.doorvoerTekst)">
          <?= $_door ? 'Doorzetten naar trainingsweekend' : 'Afwijzen doorvoeren' ?>
        </button>
        <button type="button" class="ds-btn ds-btn-zacht" data-klik="gebeldWissen(<?= $_id ?>)">
          Toch niet gebeld
        </button>
      <?php elseif ($_besl === 'twijfel'): ?>
        <button type="button" class="ds-btn ds-btn-primair" data-klik="gebeldZet(<?= $_id ?>, false, 'door')">Gebeld — toch door</button>
        <button type="button" class="ds-btn ds-btn-secundair" data-klik="gebeldZet(<?= $_id ?>, false, 'niet')">Gebeld — niet door</button>
        <button type="button" class="ds-btn ds-btn-zacht" data-klik="volgendeBellen(<?= $_id ?>)">Overslaan</button>
      <?php else: ?>
        <?php // Eén knop; het vinkje bepaalt of het meteen doorgaat. Standaard uit (besluit Lucas, 2.128.3):
              // doorvoeren is een bewuste tweede stap, per persoon of "alle gebelde doorvoeren". ?>
        <label class="bh-zijpaneel-vink bh-bel-meteen">
          <input type="checkbox" id="meteen-<?= $_id ?>">
          <span>Meteen doorvoeren. <?= h($_doorvoer_tekst($_door)) ?></span>
        </label>
        <button type="button" class="ds-btn ds-btn-primair" data-doorvoer-tekst="<?= h($_doorvoer_tekst($_door)) ?>"
                data-klik="gebeldZet(<?= $_id ?>, @meteen-<?= $_id ?>.checked, '', this.dataset.doorvoerTekst)">Gebeld</button>
        <button type="button" class="ds-btn ds-btn-zacht" data-klik="volgendeBellen(<?= $_id ?>)">Overslaan</button>
      <?php endif; ?>
    </footer>
  </section>
  <?php endif; ?>
</div>

<script nonce="<?= h(csp_nonce()) ?>">
// window.CSRF, geen const: layout_bottom.php declareert CSRF ook, en een tweede const
// brak dat hele script af (SyntaxError) — thema, meldingen en fase wijzigen deden niets.
window.CSRF = window.CSRF || '<?= csrf_token() ?>';

function belPost(body) {
  return fetch(location.pathname, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams(Object.assign({ _csrf: CSRF }, body))
  }).then(function (r) { return r.json(); });
}

function gebeldZet(solId, meteen, nieuwBesluit, doorvoerTekst) {
  const notitie = document.getElementById('gesprek-' + solId).value;
  const velden = { actie: 'gebeld', sol_id: solId, gebeld_notitie: notitie, meteen_doorvoeren: meteen ? '1' : '', nieuw_besluit: nieuwBesluit || '' };
  // "Komt op het TW?" alleen meesturen als het door wordt (door, of twijfel die door wordt).
  const tw = document.querySelector('input[name="tw_' + solId + '"]:checked');
  const wordtDoor = nieuwBesluit === 'door' || (!nieuwBesluit && document.getElementById('tw-' + solId));
  if (tw && wordtDoor && tw.value !== 'onbekend') {
    velden.tw_bevestiging = tw.value;
    velden.tw_reden = (document.getElementById('twreden-' + solId) || {}).value || '';
  }
  const doen = function () {
    belPost(velden)
      .then(function (d) {
        if (!d.ok) { bhToast(d.fout || 'Opslaan mislukt.', 'fout'); return; }
        location.reload();
      })
      .catch(function () { bhToast('Geen verbinding — probeer opnieuw.', 'fout'); });
  };
  if (!meteen) { doen(); return; }
  bhBevestig({
    titel: 'Gebeld en meteen doorvoeren?',
    tekst: doorvoerTekst || 'De fase gaat om.',
    knop: 'Ja, doorvoeren'
  }).then(function (ja) { if (ja) doen(); });
}

function gebeldWissen(solId) {
  belPost({ actie: 'gebeld_wissen', sol_id: solId })
    .then(function (d) {
      if (!d.ok) { bhToast(d.fout || 'Opslaan mislukt.', 'fout'); return; }
      location.reload();
    })
    .catch(function () { bhToast('Geen verbinding — probeer opnieuw.', 'fout'); });
}

function doorvoerenEen(solId, isDoor, doorvoerTekst) {
  bhBevestig({
    titel: isDoor ? 'Doorzetten naar het trainingsweekend?' : 'Deze sollicitant afwijzen?',
    tekst: doorvoerTekst || 'De fase gaat om.',
    knop: isDoor ? 'Ja, doorzetten' : 'Ja, afwijzen', gevaar: !isDoor
  }).then(function (ja) {
    if (!ja) return;
    belPost({ actie: 'doorvoeren_een', sol_id: solId })
      .then(function (d) {
        if (!d.ok) { bhToast(d.fout || 'Doorvoeren mislukt.', 'fout'); return; }
        location.reload();
      })
      .catch(function () { bhToast('Geen verbinding — probeer opnieuw.', 'fout'); });
  });
}

// Bellen gaat meestal vanaf je eigen telefoon met de pc ernaast: het nummer staat groot, en
// kopiëren is één klik (bijv. naar WhatsApp Web). Zonder klembord: het nummer selecteren.
function belKopieer(solId, knop) {
  const el = document.getElementById('tel-' + solId);
  if (!el) return;
  const nummer = el.textContent.trim();
  const klaar = function () { knop.textContent = 'Gekopieerd'; setTimeout(function () { knop.textContent = 'Kopieer'; }, 2000); };
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(nummer).then(klaar, function () { belSelecteer(el); });
  } else {
    belSelecteer(el);
  }
}
function belSelecteer(el) {
  const r = document.createRange(); r.selectNodeContents(el);
  const s = window.getSelection(); s.removeAllRanges(); s.addRange(r);
  bhToast('Het nummer is geselecteerd; kopieer het met Ctrl+C.', '');
}

function volgendeBellen(solId) {
  // Overslaan = de vergrendeling loslaten, zodat een ander hem wél kan bellen.
  if (solId) belPost({ actie: 'bel_los', sol_id: solId }).catch(function () {});
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
