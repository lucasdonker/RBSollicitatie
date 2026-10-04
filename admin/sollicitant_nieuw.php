<?php
require_once __DIR__ . '/../includes/functions.php';
 if (function_exists('extra_tabellen_auto_setup')) extra_tabellen_auto_setup();
$pagina_titel = 'Sollicitant toevoegen';
$actieve_nav  = 'sollicitanten';
vereis_recht('sollicitanten', 'bewerken');
$db = db();

$fout = ''; $seizoen = actief_seizoen();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $voornaam  = trim($_POST['voornaam'] ?? '');
    $tv        = trim($_POST['tussenvoegsel'] ?? '') ?: null;
    $achternaam = trim($_POST['achternaam'] ?? '');
    $email     = strtolower(trim($_POST['email'] ?? ''));
    $tel       = trim($_POST['telefoon'] ?? '') ?: null;
    $dob       = $_POST['geboortedatum'] ?? null ?: null;
    $geslacht  = (string)($_POST['geslacht'] ?? 'onbekend');
    if (!in_array($geslacht, ['man', 'vrouw', 'anders', 'onbekend'], true)) $geslacht = 'onbekend';
    $seizoen_id = (int)($_POST['seizoen_id'] ?? 0);
    $fase      = (string)($_POST['fase'] ?? 'aangemeld');
    $stuur_mail_optie = isset($_POST['stuur_mail']);
    $stil_fase = isset($_POST['stil']);
    $notities  = trim($_POST['notities'] ?? '') ?: null; // wordt als bestuurs-notitie opgeslagen

    if (!$voornaam || !$achternaam || !$email) {
        $fout = 'Vul naam en e-mailadres in.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fout = 'Ongeldig e-mailadres.';
    } elseif (!$seizoen_id) {
        $fout = 'Kies een seizoen.';
    } elseif (!isset(fasen()[$fase]) || $fase === 'afgewezen') {
        $fout = 'Kies een geldige beginfase.';
    } else {
        try {
            // Eerst alleen kijken, niets schrijven: bij een bestaand adres mag er pas iets
            // aan de persoon veranderen als zeker is dat de sollicitatie er ook komt.
            $bestaand = $db->prepare("SELECT id FROM personen WHERE LOWER(email) = ?");
            $bestaand->execute([$email]);
            $pid = (int)($bestaand->fetchColumn() ?: 0);

            $al_in_seizoen = false;
            if ($pid) {
                $al = $db->prepare("SELECT id FROM sollicitaties WHERE persoon_id=? AND seizoen_id=?");
                $al->execute([$pid, $seizoen_id]);
                $al_in_seizoen = (bool)$al->fetch();
            }

            if ($al_in_seizoen) {
                $fout = 'Deze persoon heeft al een sollicitatie in het geselecteerde seizoen. Er is niets gewijzigd.';
            } else {
                $db->beginTransaction();
                if ($pid) {
                    // Naam bijwerken; telefoon, geboortedatum en geslacht alleen als ze zijn ingevuld,
                    // zodat een leeg veld hier geen bekend gegeven wist.
                    $db->prepare("UPDATE personen SET voornaam=?, tussenvoegsel=?, achternaam=?,
                                         telefoon=COALESCE(?, telefoon), geboortedatum=COALESCE(?, geboortedatum),
                                         geslacht=IF(?='onbekend', geslacht, ?) WHERE id=?")
                       ->execute([$voornaam, $tv, $achternaam, $tel, $dob, $geslacht, $geslacht, $pid]);
                } else {
                    $db->prepare("INSERT INTO personen (voornaam,tussenvoegsel,achternaam,email,telefoon,geboortedatum,geslacht) VALUES(?,?,?,?,?,?,?)")
                       ->execute([$voornaam, $tv, $achternaam, $email, $tel, $dob, $geslacht]);
                    $pid = (int)$db->lastInsertId();
                }

                $eerder = $db->prepare("SELECT COUNT(*) FROM sollicitaties WHERE persoon_id=?");
                $eerder->execute([$pid]);
                $terugkerend = $eerder->fetchColumn() > 0 ? 1 : 0;

                // Altijd beginnen als aangemeld; de gekozen fase gaat daarna via fase_zetten(),
                // zodat ondertekeningen, kennismakingspakket en berichten hetzelfde gaan als
                // bij elke andere fasewissel.
                $db->prepare("INSERT INTO sollicitaties (persoon_id,seizoen_id,fase,terugkerend,notities,portaal_token) VALUES(?,?,'aangemeld',?,?,?)")
                   ->execute([$pid, $seizoen_id, $terugkerend, $notities, genereer_token()]);
                $sol_id = (int)$db->lastInsertId();

                // Notitie in het notities-systeem (zichtbaar in het dossier en de tijdlijn)
                if ($notities) {
                    $bhd_n = huidige_beheerder();
                    $db->prepare("INSERT INTO sollicitant_notities (sollicitatie_id, beheerder_id, tekst) VALUES (?,?,?)")
                       ->execute([$sol_id, $bhd_n['id'] ?? 0, $notities]);
                }
                $db->commit();

                // Bevestiging versturen indien gewenst — via de centrale meldingen-kern (sjabloon 'aanmelding_bevestiging')
                if ($stuur_mail_optie && function_exists('melding_verstuur')) {
                    try { melding_verstuur('aanmelding_bevestiging', (int)$sol_id, [], ['prioriteit' => 'na_antwoord']); }
                    catch (\Throwable $e) { error_log('sollicitant_nieuw melding: ' . $e->getMessage()); }
                }

                $extra = '';
                if ($fase !== 'aangemeld' && function_exists('fase_zetten')) {
                    $fr = fase_zetten($sol_id, $fase, 'handmatig', ['seizoen_id' => $seizoen_id, 'stil' => $stil_fase]);
                    if (!$fr['ok']) {
                        $extra = ' De beginfase kon niet worden gezet (' . ($fr['fout'] ?? 'onbekend') . '); de sollicitatie staat op Aangemeld.';
                    } else {
                        $mt = $stil_fase ? 'Er is geen bericht voor de beginfase verstuurd.'
                                         : (function_exists('fase_melding_tekst') ? fase_melding_tekst($fr) : '');
                        $extra = ' Fase: ' . fase_label($fase) . '.' . ($mt ? ' ' . $mt : '');
                    }
                }

                flash($extra !== '' && str_contains($extra, 'niet worden gezet') ? 'waarschuwing' : 'succes',
                      "$voornaam $achternaam toegevoegd" . ($terugkerend ? ' (terugkerend)' : '') . '.' . $extra);
                redirect(APP_URL . '/admin/sollicitant.php?id=' . $sol_id);
            }
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('sollicitant_nieuw: ' . $e->getMessage());
            $fout = 'Opslaan is niet gelukt; er is niets toegevoegd. De fout staat in het logboek.';
        }
    }
}

$seizoenen = $db->query("SELECT * FROM seizoenen ORDER BY id DESC")->fetchAll();
$standaard_sid = (int)($_POST['seizoen_id'] ?? ($seizoen['id'] ?? 0));
require __DIR__ . '/layout_top.php';
?>

<div class="bh-smalform">
<?php if ($fout): ?><div class="ds-melding ds-melding-error"><?= h($fout) ?></div><?php endif; ?>

<div class="ds-kaart">
  <div class="bh-kaartkop"><h2>Nieuwe sollicitant toevoegen</h2></div>
  <div class="bh-kaartbody">
    <form method="POST" class="bh-veldform">
      <?= csrf_input() ?>

      <div class="bh-veldrij">
        <div class="ds-veld"><label for="n-voornaam">Voornaam <span class="vereist">*</span></label><input type="text" id="n-voornaam" name="voornaam" value="<?= h($_POST['voornaam'] ?? '') ?>" required autofocus></div>
        <div class="ds-veld"><label for="n-tv">Tussenvoegsel</label><input type="text" id="n-tv" name="tussenvoegsel" value="<?= h($_POST['tussenvoegsel'] ?? '') ?>"></div>
      </div>
      <div class="ds-veld"><label for="n-achternaam">Achternaam <span class="vereist">*</span></label><input type="text" id="n-achternaam" name="achternaam" value="<?= h($_POST['achternaam'] ?? '') ?>" required></div>

      <div class="bh-veldrij">
        <div class="ds-veld">
          <label for="n-email">E-mailadres <span class="vereist">*</span></label>
          <input type="email" id="n-email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required>
          <div class="ds-veld-hint">Bestaat dit adres al, dan komt de sollicitatie bij die persoon. Naam en ingevulde velden worden pas bijgewerkt als er in dit seizoen nog geen sollicitatie is.</div>
        </div>
        <div class="ds-veld"><label for="n-tel">Telefoonnummer</label><input type="tel" id="n-tel" name="telefoon" value="<?= h($_POST['telefoon'] ?? '') ?>" placeholder="06-12345678"></div>
      </div>

      <div class="bh-veldrij">
        <div class="ds-veld"><label for="n-geb">Geboortedatum</label><input type="date" id="n-geb" name="geboortedatum" value="<?= h($_POST['geboortedatum'] ?? '') ?>"></div>
        <div class="ds-veld"><label for="n-geslacht">Geslacht</label>
          <select id="n-geslacht" name="geslacht">
            <?php foreach (['onbekend' => 'Zeg ik liever niet', 'man' => 'Man', 'vrouw' => 'Vrouw', 'anders' => 'Anders'] as $_g => $_gl): ?>
              <option value="<?= $_g ?>"<?= ($_POST['geslacht'] ?? '') === $_g ? ' selected' : '' ?>><?= h($_gl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <fieldset class="bh-veldgroep">
        <legend>Sollicitatie</legend>
        <div class="bh-veldrij">
          <div class="ds-veld"><label for="n-seizoen">Seizoen <span class="vereist">*</span></label>
            <select id="n-seizoen" name="seizoen_id" required>
              <option value="">— kies seizoen —</option>
              <?php foreach ($seizoenen as $s): ?>
              <option value="<?= (int)$s['id'] ?>"<?= (int)$s['id'] === $standaard_sid ? ' selected' : '' ?>><?= h($s['naam']) ?> (<?= h((string)$s['status']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="ds-veld"><label for="n-fase">Beginfase</label>
            <select id="n-fase" name="fase">
              <?php foreach (fasen() as $_f => $_fl): if ($_f === 'afgewezen') continue; ?>
                <option value="<?= h($_f) ?>"<?= ($_POST['fase'] ?? 'aangemeld') === $_f ? ' selected' : '' ?>><?= h($_fl) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="ds-veld-hint">Een latere fase doet hetzelfde als een fasewijziging in het dossier: documenten klaarzetten en het bijbehorende bericht.</div>
          </div>
        </div>

        <div class="ds-veld"><label for="n-notities">Interne notities</label>
          <textarea id="n-notities" name="notities" rows="2" placeholder="Bijv: aangemeld via telefonisch contact"><?= h($_POST['notities'] ?? '') ?></textarea>
        </div>

        <label class="bh-dialoog-vink">
          <input type="checkbox" name="stuur_mail" value="1"<?= ($_SERVER['REQUEST_METHOD'] !== 'POST' || isset($_POST['stuur_mail'])) ? ' checked' : '' ?>>
          <span>Stuur welkomstmail naar de sollicitant (met portaallink)</span>
        </label>
        <label class="bh-dialoog-vink">
          <input type="checkbox" name="stil" value="1"<?= isset($_POST['stil']) ? ' checked' : '' ?>>
          <span>Geen bericht bij de beginfase</span>
        </label>
        <p class="bh-dialoog-vinkhulp">Alleen van belang als de beginfase niet Aangemeld is.</p>
      </fieldset>

      <div class="bh-veldform-voet">
        <button type="submit" class="ds-btn ds-btn-primair">Toevoegen</button>
        <a href="<?= APP_URL ?>/admin/sollicitanten.php" class="ds-btn ds-btn-secundair">Annuleer</a>
      </div>
    </form>
  </div>
</div>
</div>

<?php require __DIR__ . '/layout_bottom.php'; ?>
