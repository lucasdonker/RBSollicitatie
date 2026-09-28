<?php
/**
 * Beheer — Seizoen: één seizoen per jaar opzetten, de aanmelding openen en sluiten.
 *
 * Dit is een jaarlijkse taak, dus het scherm gaat ervan uit dat je de volgorde niet
 * meer weet: het stappenplan rechts laat zien wat al af is en wat nu aan de beurt is.
 *
 * Open/gesloten gaat alleen over de AANMELDING (het aanmeldformulier). Waar het seizoen
 * staat, volgt uit de data (bh_seizoen_fase_bepalen). Er staat maar één aanmelding
 * tegelijk open: openen sluit de andere.
 *
 * Alle drie de acties (openen, sluiten, verwijderen) gaan via een tussenscherm dat
 * zegt wat er gebeurt. Verwijderen raakt persoonsgegevens van sollicitanten en kan
 * niet ongedaan worden gemaakt — daar hoort geen confirm() bij die je wegklikt. Het
 * loopt via de AVG-wislaag (bh_seizoen_verwijderen) en weigert bij verwerkte loondossiers.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';
$pagina_titel = 'Seizoen';
$actieve_nav  = 'seizoen';
vereis_recht('seizoen', 'bewerken');

$db  = db();
$bhd = huidige_beheerder();

// Auto-migratie: alle seizoen-kolommen (één lijst, gedeeld met het portaal: includes/portaal_dossier.php)
seizoen_kolommen_verzeker();

// Verwijderen en heropenen blijven bij de beheerder; bestuur mag instellen, het nieuwste seizoen openen en sluiten.
$mag_verwijderen = function_exists('mag') ? mag('seizoen', 'verwijderen') : ($bhd['rol'] ?? '') === 'admin';
if (!$mag_verwijderen && ($bhd['rol'] ?? '') === 'admin') $mag_verwijderen = true;
// Heropenen = de aanmelding openen van een seizoen dat niet het nieuwste is. Dat sluit het lopende
// seizoen en zet het hele beheer terug; daarom alleen de beheerder (server-side, niet alleen in de UI).
$nieuwste_id = (int)$db->query("SELECT MAX(id) FROM seizoenen")->fetchColumn();
$mag_openen  = fn(int $id): bool => $mag_verwijderen || $id === $nieuwste_id;

/** Eén seizoensrij mét het aantal sollicitaties, of null. ($db bewust zonder type: het
    renderharnas geeft hier een FakePDO door, en dat is precies de bedoeling.) */
function seizoen_rij($db, int $id): ?array {
    $q = $db->prepare("SELECT s.*, (SELECT COUNT(*) FROM sollicitaties WHERE seizoen_id=s.id) n
                       FROM seizoenen s WHERE s.id=?");
    $q->execute([$id]);
    return $q->fetch() ?: null;
}

// ── POST — vóór layout_top.php, want elke tak eindigt in een redirect ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $actie = (string)($_POST['actie'] ?? '');
    $sid   = (int)($_POST['seizoen_id'] ?? 0);

    switch ($actie) {
        case 'nieuw':
            $naam = trim((string)($_POST['naam'] ?? ''));
            if ($naam !== '') {
                // Vóór het invoegen: het seizoen waar het beheer mee werkt. Dat blijft zo als de
                // aanmelding ervan open staat of het sollicitaties heeft (actief_seizoen()).
                $nu_actief = actief_seizoen();
                if ($nu_actief && ($nu_actief['status'] ?? '') !== 'open'
                    && (int)(seizoen_rij($db, (int)$nu_actief['id'])['n'] ?? 0) === 0) $nu_actief = null;
                $db->prepare("INSERT INTO seizoenen (naam, status) VALUES (?, 'gesloten')")->execute([$naam]);
                $nieuw_id = (int)$db->lastInsertId();
                flash('succes', 'Seizoen "' . $naam . '" aangemaakt, met de aanmelding dicht. Vul nu de datums in.'
                    . ($nu_actief
                        ? ' Het beheer blijft werken met "' . $nu_actief['naam'] . '" tot je de aanmelding van dit seizoen opent.'
                        : ''));
                // Meteen het nieuwe seizoen bewerken: daar ga je de datums invullen.
                if ($nieuw_id > 0) redirect(APP_URL . '/admin/seizoen.php?id=' . $nieuw_id);
            } else {
                flash('fout', 'Geef het seizoen een naam.');
            }
            break;

        case 'update':
            try {
                $db->prepare("UPDATE seizoenen SET naam=?, datum_selectiedag=?, locatie_selectiedag=?,
                    selectiedag_verzameltijd=?, selectiedag_starttijd=?, selectiedag_eindtijd=?,
                    adres_selectiedag=?, selectiedag_meenemen=?,
                    datum_tw_start=?, datum_tw_eind=?, locatie_tw=?, tw_verzameltijd=?, tw_begintijd=?, tw_eindtijd=?,
                    adres_tw=?, tw_meenemen=?,
                    datum_werk_start=?, datum_werk_eind=?,
                    hoofdseizoen_start_week=?, hoofdseizoen_eind_week=?, aanmelden_tot=?
                    WHERE id=?")
                   ->execute([
                       $_POST['naam'],
                       $_POST['datum_selectiedag'] ?: null,
                       $_POST['locatie_selectiedag'] ?: null,
                       $_POST['selectiedag_verzameltijd'] ?: null,
                       $_POST['selectiedag_starttijd'] ?: null,
                       $_POST['selectiedag_eindtijd'] ?: null,
                       trim($_POST['adres_selectiedag'] ?? '') ?: null,
                       trim($_POST['selectiedag_meenemen'] ?? '') ?: null,
                       $_POST['datum_tw_start'] ?: null,
                       $_POST['datum_tw_eind'] ?: null,
                       $_POST['locatie_tw'] ?: null,
                       $_POST['tw_verzameltijd'] ?: null,
                       $_POST['tw_begintijd'] ?: null,
                       $_POST['tw_eindtijd'] ?: null,
                       trim($_POST['adres_tw'] ?? '') ?: null,
                       trim($_POST['tw_meenemen'] ?? '') ?: null,
                       $_POST['datum_werk_start'] ?: null,
                       $_POST['datum_werk_eind'] ?: null,
                       (int)($_POST['hoofdseizoen_start_week'] ?? 0) ?: null,
                       (int)($_POST['hoofdseizoen_eind_week'] ?? 0) ?: null,
                       preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['aanmelden_tot'] ?? '')) ? $_POST['aanmelden_tot'] : null,
                       $sid,
                   ]);
                flash('succes', 'Seizoen bijgewerkt.');
            } catch (Exception $e) {
                // Terugval voor een database waar de extra kolommen nog ontbreken.
                $db->prepare("UPDATE seizoenen SET naam=?, datum_selectiedag=?, locatie_selectiedag=?,
                    datum_tw_start=?, datum_tw_eind=?, locatie_tw=? WHERE id=?")
                   ->execute([$_POST['naam'], $_POST['datum_selectiedag'] ?: null, $_POST['locatie_selectiedag'] ?: null,
                              $_POST['datum_tw_start'] ?: null, $_POST['datum_tw_eind'] ?: null, $_POST['locatie_tw'] ?: null, $sid]);
                flash('waarschuwing', 'Seizoen deels bijgewerkt: naam, selectiedag en trainingsweekend zijn opgeslagen, de andere velden niet. Draai de databasecontrole bij Systeem & updates en sla daarna opnieuw op.');
            }
            redirect(APP_URL . '/admin/seizoen.php?id=' . $sid);

        case 'open':
            // Er staat maar één aanmelding tegelijk open. Twee open seizoenen lieten
            // actief_seizoen() stil het nieuwste kiezen; nu sluit openen de andere.
            $sz = seizoen_rij($db, $sid);
            if (!$sz) break;
            if (!$mag_openen($sid)) {
                flash('fout', 'De aanmelding van een eerder seizoen heropenen kan alleen een beheerder.');
                redirect(APP_URL . '/admin/seizoen.php?id=' . $sid);
            }
            $q = $db->prepare("SELECT naam FROM seizoenen WHERE status='open' AND id<>?");
            $q->execute([$sid]);
            $gesloten = array_column($q->fetchAll(), 'naam');
            $db->beginTransaction();
            try {
                $db->prepare("UPDATE seizoenen SET status='gesloten' WHERE status='open' AND id<>?")->execute([$sid]);
                $db->prepare("UPDATE seizoenen SET status='open' WHERE id=?")->execute([$sid]);
                $db->commit();
            } catch (\Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                error_log('seizoen openen ' . $sid . ': ' . $e->getMessage());
                flash('fout', 'Openen is niet gelukt; er is niets veranderd.');
                redirect(APP_URL . '/admin/seizoen.php?id=' . $sid);
            }
            flash('succes', 'De aanmelding voor ' . $sz['naam'] . ' staat open — het aanmeldformulier is nu actief.'
                . ($gesloten ? ' De aanmelding van ' . implode(', ', $gesloten) . ' is daarmee gesloten.' : ''));
            redirect(APP_URL . '/admin/seizoen.php?id=' . $sid);

        case 'sluit':
            $db->prepare("UPDATE seizoenen SET status='gesloten' WHERE id=?")->execute([$sid]);
            flash('succes', 'De aanmelding is gesloten — er kan niet meer worden aangemeld. Wie al in de procedure zit, loopt gewoon door.');
            redirect(APP_URL . '/admin/seizoen.php?id=' . $sid);

        case 'verwijder':
            if (!$mag_verwijderen) { http_response_code(403); exit('Alleen een beheerder kan een seizoen verwijderen.'); }
            // Via de AVG-wislaag; weigert bij verwerkte loondossiers (bewaarplicht).
            $r = bh_seizoen_verwijderen($sid);
            if (!$r['ok']) {
                flash('fout', (string)$r['fout']);
                redirect(APP_URL . '/admin/seizoen.php?id=' . $sid);
            }
            flash('succes', $r['tekst']);
            break;
    }

    redirect(APP_URL . '/admin/seizoen.php');
}

// ── Bevestiging als tussenscherm ──────────────────────────────
$bevestig_actie = (string)($_GET['bevestig'] ?? '');
$bevestig_sz    = null;
if (in_array($bevestig_actie, ['open', 'sluit', 'verwijder'], true)) {
    $bevestig_sz = seizoen_rij($db, (int)($_GET['id'] ?? 0));
    if (!$bevestig_sz || ($bevestig_actie === 'verwijder' && !$mag_verwijderen)
        || ($bevestig_actie === 'open' && !$mag_openen((int)$bevestig_sz['id']))) {
        $bevestig_actie = ''; $bevestig_sz = null;
    }
    // Verwerkte loondossiers (bewaarplicht): dan geen knop die toch zou weigeren, maar meteen de reden.
    if ($bevestig_sz && $bevestig_actie === 'verwijder' && ($_bewaard = bh_seizoen_loon_bewaard((int)$bevestig_sz['id'])) > 0) {
        flash('fout', $bevestig_sz['naam'] . ' kan niet worden verwijderd: er ' . ($_bewaard === 1 ? 'is één verwerkt loondossier' : 'zijn ' . $_bewaard . ' verwerkte loondossiers')
            . ' met een wettelijke bewaarplicht. Vernietig die eerst bij Loondossiers als de bewaartermijn voorbij is.');
        redirect(APP_URL . '/admin/seizoen.php?id=' . (int)$bevestig_sz['id']);
    }
}

$seizoenen = $db->query("
    SELECT s.*, (SELECT COUNT(*) FROM sollicitaties WHERE seizoen_id=s.id) n
    FROM seizoenen s ORDER BY s.id DESC
")->fetchAll();

// "Huidig" betekent hier hetzelfde als in de rest van het beheer: actief_seizoen() (het
// seizoen met de aanmelding open, anders het nieuwste met sollicitaties). Eerder was dat
// hier altijd het nieuwste, zodat een net aangemaakt, leeg seizoen "huidig" heette terwijl
// Vandaag, Sollicitanten en de selectiedag nog met het lopende seizoen werkten.
$actief    = actief_seizoen();
$actief_id = $actief ? (int)$actief['id'] : 0;

// Welk seizoen bewerk je? Standaard het actieve; ?id= kiest een ander (Alle seizoenen).
$bewerk_id = (int)($_GET['id'] ?? 0) ?: $actief_id;
$huidig    = null;
foreach ($seizoenen as $_sz) if ((int)$_sz['id'] === $bewerk_id) { $huidig = $_sz; break; }
if (!$huidig) {
    foreach ($seizoenen as $_sz) if ((int)$_sz['id'] === $actief_id) { $huidig = $_sz; break; }
}
if (!$huidig) $huidig = $seizoenen[0] ?? null;
$bewerkt_actief = $huidig && (int)$huidig['id'] === $actief_id;
$stappen = bh_seizoen_stappen($huidig);

/** Een ánder seizoen dat open staat dan $id (of null). */
$ander_open = function (int $id) use ($seizoenen): ?array {
    foreach ($seizoenen as $_sz) {
        if ((int)$_sz['id'] !== $id && ($_sz['status'] ?? '') === 'open') return $_sz;
    }
    return null;
};

/** Link naar het bevestigingsscherm van een actie. */
$bev_url = fn(string $a, int $id) => '?bevestig=' . $a . '&id=' . $id;

require __DIR__ . '/layout_top.php';
?>

<?php if ($bevestig_sz): ?>
  <?php
  $bev = bh_seizoen_gevolgen($bevestig_actie, $bevestig_sz, $ander_open((int)$bevestig_sz['id']), $actief);
  $bev_form = ['actie' => APP_URL . '/admin/seizoen.php',
               'velden' => ['actie' => $bevestig_actie, 'seizoen_id' => (int)$bevestig_sz['id']]];
  $bev_terug = APP_URL . '/admin/seizoen.php?id=' . (int)$bevestig_sz['id'];
  require __DIR__ . '/_bevestig.php';
  ?>
  <?php require __DIR__ . '/layout_bottom.php'; ?>
  <?php return; ?>
<?php endif; ?>

<?php if ($huidig && !$bewerkt_actief && $actief): ?>
  <div class="ds-melding ds-melding-info bh-seizoen-anders" role="status">
    <?= icoon('info', 16) ?>
    <span>
      Je bewerkt <strong><?= h((string)$huidig['naam']) ?></strong>. De rest van het beheer
      (Vandaag, Sollicitanten, de selectiedag) werkt met <strong><?= h((string)$actief['naam']) ?></strong>:
      dat is het seizoen <?= ($actief['status'] ?? '') === 'open' ? 'waarvan de aanmelding open staat' : 'dat nu loopt, omdat er geen aanmelding open staat' ?>.
      <a href="<?= APP_URL ?>/admin/seizoen.php">Naar <?= h((string)$actief['naam']) ?></a>
    </span>
  </div>
<?php endif; ?>

<?php if ($huidig): $_open = ($huidig['status'] ?? '') === 'open'; ?>
<section class="ds-kaart bh-seizoenkaart<?= $_open ? ' open' : '' ?>">
  <div class="bh-seizoenkaart-wie">
    <h2><?= h((string)$huidig['naam']) ?> <?php if ($bewerkt_actief): ?><?= bh_chip('huidig', 'blauw') ?><?php endif; ?></h2>
    <?= bh_stoplicht($_open ? 'goed' : 'krap',
          $_open ? 'Aanmelding open — sollicitanten kunnen zich aanmelden'
                 : 'Aanmelding gesloten — het aanmeldformulier staat uit') ?>
  </div>
  <div class="bh-seizoenkaart-acties">
    <?php if ($_open || $mag_openen((int)$huidig['id'])): ?>
    <a class="ds-btn <?= $_open ? 'ds-btn-secundair' : 'ds-btn-primair' ?>"
       href="<?= h($bev_url($_open ? 'sluit' : 'open', (int)$huidig['id'])) ?>">
      <?= $_open ? 'Aanmelding sluiten' : 'Aanmelding openen' ?>
    </a>
    <?php else: ?>
    <span class="ds-veld-hint">Dit is een eerder seizoen; de aanmelding heropenen kan alleen een beheerder.</span>
    <?php endif; ?>
    <a class="ds-btn ds-btn-zacht ds-btn-sm" href="<?= APP_URL ?>/aanmelden.php" target="_blank" rel="noopener">
      Aanmeldformulier bekijken
    </a>
  </div>
</section>
<?php endif; ?>

<div class="bh-seizoen-kolommen">
  <div class="bh-seizoen-hoofd">

    <?php if ($huidig): ?>
    <form method="POST" class="ds-kaart ds-kaart-p bh-veldform">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="update">
      <input type="hidden" name="seizoen_id" value="<?= (int)$huidig['id'] ?>">

      <div class="bh-kaartkop"><h2><?= h((string)$huidig['naam']) ?> instellen</h2></div>

      <div class="ds-veld">
        <label class="ds-label" for="sz_naam">Naam van het seizoen</label>
        <input class="ds-input" type="text" id="sz_naam" name="naam" value="<?= h((string)$huidig['naam']) ?>" required>
      </div>

      <fieldset class="bh-veldgroep">
        <legend>Aanmelding</legend>
        <div class="ds-veld">
          <label class="ds-label" for="sz_aanmelden_tot">Aanmelden kan tot</label>
          <input class="ds-input" type="date" id="sz_aanmelden_tot" name="aanmelden_tot" value="<?= h((string)($huidig['aanmelden_tot'] ?? '')) ?>" aria-describedby="sz_aanmelden_tot_hint">
          <p class="ds-veld-hint" id="sz_aanmelden_tot_hint">
            De sollicitatiedeadline, tot en met deze dag. Staat op de landingspagina's
            (<code>[[aanmelden_tot]]</code>) en in de vacature voor Google, die daarna vanzelf verloopt.
            Het formulier sluit hier niet vanzelf door: dat doe je met "Aanmelding sluiten".
          </p>
        </div>
      </fieldset>

      <fieldset class="bh-veldgroep">
        <legend>Werkperiode</legend>
        <p class="ds-veld-hint">
          Sollicitanten zien in het portaal elke week tussen deze twee datums als een aan te
          klikken tegel. Zonder werkperiode kunnen ze hun beschikbaarheid niet invullen.
        </p>
        <div class="bh-veldrij">
          <div class="ds-veld">
            <label class="ds-label" for="sz_werk_start">Eerste werkdag</label>
            <input class="ds-input" type="date" id="sz_werk_start" name="datum_werk_start" value="<?= h((string)($huidig['datum_werk_start'] ?? '')) ?>">
          </div>
          <div class="ds-veld">
            <label class="ds-label" for="sz_werk_eind">Laatste werkdag</label>
            <input class="ds-input" type="date" id="sz_werk_eind" name="datum_werk_eind" value="<?= h((string)($huidig['datum_werk_eind'] ?? '')) ?>">
          </div>
        </div>
        <div class="bh-veldrij">
          <div class="ds-veld">
            <label class="ds-label" for="sz_hs_start">Hoofdseizoen vanaf week</label>
            <input class="ds-input" type="number" id="sz_hs_start" name="hoofdseizoen_start_week" min="1" max="53"
                   value="<?= h((string)($huidig['hoofdseizoen_start_week'] ?? '')) ?>" placeholder="bijv. 27">
          </div>
          <div class="ds-veld">
            <label class="ds-label" for="sz_hs_eind">Hoofdseizoen tot en met week</label>
            <input class="ds-input" type="number" id="sz_hs_eind" name="hoofdseizoen_eind_week" min="1" max="53"
                   value="<?= h((string)($huidig['hoofdseizoen_eind_week'] ?? '')) ?>" placeholder="bijv. 34">
          </div>
        </div>
        <p class="ds-veld-hint">
          In het voor- en naseizoen zijn de posten alleen in het weekend bemand; de dekking
          rekent daar dan ook mee. Laat je dit leeg, dan telt elke week als hoofdseizoen.
        </p>
      </fieldset>

      <fieldset class="bh-veldgroep">
        <legend>Selectiedag</legend>
        <div class="bh-veldrij">
          <div class="ds-veld">
            <label class="ds-label" for="sz_sd_datum">Datum</label>
            <input class="ds-input" type="date" id="sz_sd_datum" name="datum_selectiedag" value="<?= h((string)($huidig['datum_selectiedag'] ?? '')) ?>">
          </div>
          <div class="ds-veld">
            <label class="ds-label" for="sz_sd_locatie">Locatie</label>
            <input class="ds-input" type="text" id="sz_sd_locatie" name="locatie_selectiedag"
                   value="<?= h((string)($huidig['locatie_selectiedag'] ?? '')) ?>" placeholder="bijv. Paal 13">
          </div>
        </div>
        <div class="bh-veldrij">
          <div class="ds-veld">
            <label class="ds-label" for="sz_sd_verzamel">Verzameltijd</label>
            <input class="ds-input" type="time" id="sz_sd_verzamel" name="selectiedag_verzameltijd" value="<?= h((string)($huidig['selectiedag_verzameltijd'] ?? '')) ?>">
            <p class="ds-veld-hint">Hoe laat sollicitanten er moeten zijn.</p>
          </div>
          <div class="ds-veld">
            <label class="ds-label" for="sz_sd_start">Start programma</label>
            <input class="ds-input" type="time" id="sz_sd_start" name="selectiedag_starttijd" value="<?= h((string)($huidig['selectiedag_starttijd'] ?? '')) ?>">
          </div>
        </div>
        <div class="bh-veldrij">
          <div class="ds-veld">
            <label class="ds-label" for="sz_sd_eind">Eindtijd (ongeveer)</label>
            <input class="ds-input" type="time" id="sz_sd_eind" name="selectiedag_eindtijd" value="<?= h((string)($huidig['selectiedag_eindtijd'] ?? '')) ?>">
            <p class="ds-veld-hint">Het portaal toont "tot ongeveer 16:00".</p>
          </div>
          <div class="ds-veld">
            <label class="ds-label" for="sz_sd_adres">Adres</label>
            <input class="ds-input" type="text" id="sz_sd_adres" name="adres_selectiedag"
                   value="<?= h((string)($huidig['adres_selectiedag'] ?? '')) ?>" placeholder="bijv. Strandweg 13, Callantsoog">
          </div>
        </div>
        <div class="ds-veld">
          <label class="ds-label" for="sz_sd_mee">Wat neem je mee</label>
          <textarea class="ds-textarea" id="sz_sd_mee" name="selectiedag_meenemen" rows="2"
                    placeholder="bijv. Zwemkleding, handdoek en iets te eten."><?= h((string)($huidig['selectiedag_meenemen'] ?? '')) ?></textarea>
          <p class="ds-veld-hint">Staat op de selectiedag-kaart in het portaal en is in mails te gebruiken als {{selectiedag.meenemen}}.</p>
        </div>
      </fieldset>

      <fieldset class="bh-veldgroep">
        <legend>Trainingsweekend</legend>
        <div class="bh-veldrij">
          <div class="ds-veld">
            <label class="ds-label" for="sz_tw_start">Start</label>
            <input class="ds-input" type="date" id="sz_tw_start" name="datum_tw_start" value="<?= h((string)($huidig['datum_tw_start'] ?? '')) ?>">
          </div>
          <div class="ds-veld">
            <label class="ds-label" for="sz_tw_eind">Eind</label>
            <input class="ds-input" type="date" id="sz_tw_eind" name="datum_tw_eind" value="<?= h((string)($huidig['datum_tw_eind'] ?? '')) ?>">
          </div>
        </div>
        <div class="ds-veld">
          <label class="ds-label" for="sz_tw_locatie">Locatie</label>
          <input class="ds-input" type="text" id="sz_tw_locatie" name="locatie_tw"
                 value="<?= h((string)($huidig['locatie_tw'] ?? '')) ?>" placeholder="bijv. Strandpost Callantsoog">
        </div>
        <div class="bh-veldrij">
          <div class="ds-veld">
            <label class="ds-label" for="sz_tw_verzamel">Verzameltijd</label>
            <input class="ds-input" type="time" id="sz_tw_verzamel" name="tw_verzameltijd" value="<?= h((string)($huidig['tw_verzameltijd'] ?? '')) ?>">
          </div>
          <div class="ds-veld">
            <label class="ds-label" for="sz_tw_begin">Start programma</label>
            <input class="ds-input" type="time" id="sz_tw_begin" name="tw_begintijd" value="<?= h((string)($huidig['tw_begintijd'] ?? '')) ?>">
          </div>
        </div>
        <div class="bh-veldrij">
          <div class="ds-veld">
            <label class="ds-label" for="sz_tw_eindtijd">Eindtijd (ongeveer)</label>
            <input class="ds-input" type="time" id="sz_tw_eindtijd" name="tw_eindtijd" value="<?= h((string)($huidig['tw_eindtijd'] ?? '')) ?>">
          </div>
          <div class="ds-veld">
            <label class="ds-label" for="sz_tw_adres">Adres</label>
            <input class="ds-input" type="text" id="sz_tw_adres" name="adres_tw"
                   value="<?= h((string)($huidig['adres_tw'] ?? '')) ?>" placeholder="bijv. Duinweg 1, Callantsoog">
          </div>
        </div>
        <div class="ds-veld">
          <label class="ds-label" for="sz_tw_mee">Wat neem je mee</label>
          <textarea class="ds-textarea" id="sz_tw_mee" name="tw_meenemen" rows="2"
                    placeholder="bijv. Slaapzak, matje, zwemkleding."><?= h((string)($huidig['tw_meenemen'] ?? '')) ?></textarea>
          <p class="ds-veld-hint">Staat op de trainingsweekend-kaart in het portaal en is in mails te gebruiken als {{tw.meenemen}}.</p>
        </div>
      </fieldset>

      <div class="bh-veldform-voet">
        <button type="submit" class="ds-btn ds-btn-primair">Opslaan</button>
      </div>
    </form>
    <?php endif; ?>

    <form method="POST" class="ds-kaart ds-kaart-p">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="nieuw">
      <div class="bh-kaartkop"><h2>Nieuw seizoen aanmaken</h2></div>
      <p class="ds-veld-hint">
        Het nieuwe seizoen begint met de aanmelding dicht. Bestaande gegevens blijven staan, en
        wie eerder heeft gesolliciteerd wordt vanzelf herkend als terugkerend.
      </p>
      <?php $_nu_open = $ander_open(0); if ($_nu_open): ?>
        <div class="ds-melding ds-melding-warn">
          <?= icoon('waarschuwing', 16) ?>
          <span>
            De aanmelding van <strong><?= h((string)$_nu_open['naam']) ?></strong> staat nog open. Het nieuwe
            seizoen maak je wel alvast aan, maar het beheer en het aanmeldformulier blijven met
            <?= h((string)$_nu_open['naam']) ?> werken tot je de aanmelding van het nieuwe seizoen opent;
            die van <?= h((string)$_nu_open['naam']) ?> gaat dan vanzelf dicht.
          </span>
        </div>
      <?php endif; ?>
      <div class="ds-veld">
        <label class="ds-label" for="sz_nieuw">Naam</label>
        <input class="ds-input" type="text" id="sz_nieuw" name="naam" placeholder="bijv. Zomer 2027" required>
      </div>
      <div class="bh-veldform-voet">
        <button type="submit" class="ds-btn ds-btn-secundair">Aanmaken</button>
      </div>
    </form>
  </div>

  <aside class="bh-seizoen-zij">
    <section class="ds-kaart ds-kaart-p">
      <div class="bh-kaartkop"><h2>Waar sta je?</h2></div>
      <ol class="bh-stappen">
        <?php foreach ($stappen as $_i => $_s): ?>
        <li class="bh-stap<?= $_s['af'] ? ' af' : '' ?><?= $_s['nu'] ? ' nu' : '' ?>">
          <span class="bh-stap-bol"><?= $_s['af'] ? icoon('vink', 13) : (string)($_i + 1) ?></span>
          <span class="bh-stap-tekst">
            <strong><?= h($_s['titel']) ?></strong>
            <span><?= h($_s['tekst']) ?></span>
          </span>
          <?php if ($_s['nu']): ?><span class="bh-stap-nu">nu</span><?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ol>
    </section>

    <section class="ds-kaart ds-kaart-p">
      <div class="bh-kaartkop"><h2>Alle seizoenen</h2></div>
      <?php if (!$seizoenen): ?>
        <p class="bh-meta">Er is nog geen seizoen aangemaakt.</p>
      <?php else: ?>
      <ul class="bh-seizoenlijst">
        <?php foreach ($seizoenen as $sz):
          $_is_open = ($sz['status'] ?? '') === 'open';
          $_huidig  = $huidig && (int)$sz['id'] === (int)$huidig['id'];   // het seizoen dat je nu bewerkt
          $_actief  = (int)$sz['id'] === $actief_id;                      // het seizoen van de rest van het beheer
        ?>
        <li class="bh-seizoenrij">
          <span class="bh-seizoenrij-naam">
            <?= h((string)$sz['naam']) ?>
            <?php if ($_actief): ?><?= bh_chip('huidig', 'blauw') ?><?php endif; ?>
            <?= bh_badge($_is_open ? 'Aanmelding open' : 'Aanmelding dicht', $_is_open ? 'groen' : 'grijs') ?>
          </span>
          <span class="bh-seizoenrij-sub">
            <?= (int)$sz['n'] ?> <?= (int)$sz['n'] === 1 ? 'sollicitatie' : 'sollicitaties' ?>
            <?php if (!empty($sz['datum_selectiedag'])): ?>
              · selectiedag <?= h(datum_kort_nl($sz['datum_selectiedag'])) ?>
            <?php endif; ?>
            <?php if (!empty($sz['datum_werk_start'])): ?>
              · <?= h(datum_kort_nl($sz['datum_werk_start'])) ?>–<?= h(datum_kort_nl($sz['datum_werk_eind'] ?? '')) ?>
            <?php endif; ?>
          </span>
          <span class="bh-seizoenrij-acties">
            <?php if ($_huidig): ?>
              <span class="bh-meta">je bewerkt dit seizoen</span>
            <?php else: ?>
              <a class="ds-btn ds-btn-secundair ds-btn-sm" href="<?= APP_URL ?>/admin/seizoen.php?id=<?= (int)$sz['id'] ?>">
                Bewerken<span class="bh-vw"> — <?= h((string)$sz['naam']) ?></span>
              </a>
            <?php endif; ?>
            <?php if ($mag_verwijderen): ?>
              <?php if (!$_huidig): ?>
                <a class="ds-btn ds-btn-zacht ds-btn-sm" href="<?= h($bev_url($_is_open ? 'sluit' : 'open', (int)$sz['id'])) ?>">
                  <?= $_is_open ? 'Aanmelding sluiten' : 'Aanmelding openen' ?><span class="bh-vw"> — <?= h((string)$sz['naam']) ?></span>
                </a>
              <?php endif; ?>
              <a class="ds-btn ds-btn-gevaar ds-btn-sm" href="<?= h($bev_url('verwijder', (int)$sz['id'])) ?>">Verwijderen</a>
            <?php endif; ?>
          </span>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <?php if (!$mag_verwijderen): ?>
        <p class="ds-veld-hint">Een seizoen verwijderen, of de aanmelding van een eerder seizoen heropenen, kan alleen een beheerder.</p>
      <?php endif; ?>
    </section>
  </aside>
</div>

<?php require __DIR__ . '/layout_bottom.php'; ?>
