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
    $geslacht  = $_POST['geslacht'] ?? 'onbekend';
    $seizoen_id = (int)($_POST['seizoen_id'] ?? 0);
    $fase      = $_POST['fase'] ?? 'aangemeld';
    $stuur_mail_optie = isset($_POST['stuur_mail']);
    $notities  = trim($_POST['notities'] ?? '') ?: null; // wordt als bestuurs-notitie opgeslagen

    if (!$voornaam || !$achternaam || !$email) {
        $fout = 'Vul naam en e-mailadres in.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fout = 'Ongeldig e-mailadres.';
    } elseif (!$seizoen_id) {
        $fout = 'Kies een seizoen.';
    } else {
        try {
            // Persoon aanmaken of ophalen
            $bestaand = $db->prepare("SELECT id FROM personen WHERE email = ?");
            $bestaand->execute([$email]);
            $persoon = $bestaand->fetch();

            if ($persoon) {
                $pid = $persoon['id'];
                $db->prepare("UPDATE personen SET voornaam=?,tussenvoegsel=?,achternaam=?,telefoon=?,geboortedatum=?,geslacht=? WHERE id=?")
                   ->execute([$voornaam, $tv, $achternaam, $tel, $dob, $geslacht, $pid]);
            } else {
                $db->prepare("INSERT INTO personen (voornaam,tussenvoegsel,achternaam,email,telefoon,geboortedatum,geslacht) VALUES(?,?,?,?,?,?,?)")
                   ->execute([$voornaam, $tv, $achternaam, $email, $tel, $dob, $geslacht]);
                $pid = (int)$db->lastInsertId();
            }

            // Check of al in dit seizoen
            $al = $db->prepare("SELECT id FROM sollicitaties WHERE persoon_id=? AND seizoen_id=?");
            $al->execute([$pid, $seizoen_id]);
            if ($al->fetch()) {
                $fout = 'Deze persoon heeft al een sollicitatie in het geselecteerde seizoen.';
            } else {
                $eerder = $db->prepare("SELECT COUNT(*) FROM sollicitaties WHERE persoon_id=?");
                $eerder->execute([$pid]);
                $terugkerend = $eerder->fetchColumn() > 0 ? 1 : 0;
                $token = genereer_token();

                $db->prepare("INSERT INTO sollicitaties (persoon_id,seizoen_id,fase,terugkerend,notities,portaal_token) VALUES(?,?,?,?,?,?)")
                   ->execute([$pid, $seizoen_id, $fase, $terugkerend, $notities, $token]);
                $sol_id = (int)$db->lastInsertId();
        // Notitie opslaan in het notities-systeem (zichtbaar in paneel + timeline)
        if ($notities) {
            try {
                $bhd_n = huidige_beheerder();
                $db->prepare("INSERT INTO sollicitant_notities (sollicitatie_id, beheerder_id, tekst) VALUES (?,?,?)")
                   ->execute([$sol_id, $bhd_n['id'] ?? 0, $notities]);
            } catch (\Exception $e) {}
        }

                // Bevestiging versturen indien gewenst — via de centrale meldingen-kern (sjabloon 'aanmelding_bevestiging')
                if ($stuur_mail_optie && function_exists('melding_verstuur')) {
                    try { melding_verstuur('aanmelding_bevestiging', (int)$sol_id, [], ['prioriteit' => 'na_antwoord']); }
                    catch (\Throwable $e) { error_log('sollicitant_nieuw melding: ' . $e->getMessage()); }
                }

                flash('succes', "$voornaam $achternaam toegevoegd" . ($terugkerend ? ' (terugkerend)' : '') . '.');
                redirect(APP_URL . '/admin/sollicitanten.php?open=' . $sol_id);
            }
        } catch (PDOException $e) {
            $fout = 'Database fout: ' . $e->getMessage();
        }
    }
}

$seizoenen = $db->query("SELECT * FROM seizoenen ORDER BY id DESC")->fetchAll();
require __DIR__ . '/layout_top.php';
?>

<div style="max-width:600px">
<?php if ($fout): ?><div class="ds-melding ds-melding-error"><?= h($fout) ?></div><?php endif; ?>

<div class="ds-kaart">
  <div class="bh-kaartkop"><h2>Nieuwe sollicitant toevoegen</h2></div>
  <div class="bh-kaartbody">
    <form method="POST">
      <?= csrf_input() ?>

      <div class="bh-veldrij">
        <div class="ds-veld"><label>Voornaam <span class="vereist">*</span></label><input type="text" name="voornaam" value="<?= h($_POST['voornaam'] ?? '') ?>" required autofocus></div>
        <div class="ds-veld"><label>Tussenvoegsel</label><input type="text" name="tussenvoegsel" value="<?= h($_POST['tussenvoegsel'] ?? '') ?>"></div>
      </div>
      <div class="ds-veld"><label>Achternaam <span class="vereist">*</span></label><input type="text" name="achternaam" value="<?= h($_POST['achternaam'] ?? '') ?>" required></div>

      <div class="bh-veldrij">
        <div class="ds-veld">
          <label>E-mailadres <span class="vereist">*</span></label>
          <input type="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required>
          <div class="ds-veld-hint">Als dit adres al bestaat, wordt het profiel bijgewerkt</div>
        </div>
        <div class="ds-veld"><label>Telefoonnummer</label><input type="tel" name="telefoon" value="<?= h($_POST['telefoon'] ?? '') ?>" placeholder="06-12345678"></div>
      </div>

      <div class="bh-veldrij">
        <div class="ds-veld"><label>Geboortedatum</label><input type="date" name="geboortedatum" value="<?= h($_POST['geboortedatum'] ?? '') ?>"></div>
        <div class="ds-veld"><label>Geslacht</label>
          <select name="geslacht">
            <option value="onbekend">Zeg ik liever niet</option>
            <option value="man">Man</option>
            <option value="vrouw">Vrouw</option>
            <option value="anders">Anders</option>
          </select>
        </div>
      </div>

      <hr style="border:none;border-top:1px solid var(--rand);margin:16px 0">

      <div class="bh-veldrij">
        <div class="ds-veld"><label>Seizoen <span class="vereist">*</span></label>
          <select name="seizoen_id" required>
            <option value="">— kies seizoen —</option>
            <?php foreach ($seizoenen as $s): ?>
            <option value="<?= $s['id'] ?>" <?= ($s === reset($seizoenen)) ? 'selected' : '' ?>><?= h($s['naam']) ?> (<?= $s['status'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="ds-veld"><label>Fase</label>
          <select name="fase">
            <option value="aangemeld">Aangemeld</option>
            <option value="uitgenodigd_selectie">Uitgenodigd selectiedag</option>
            <option value="aangemeld_selectie">Aangemeld selectiedag</option>
            <option value="selectiedag_geweest">Selectiedag geweest</option>
            <option value="uitgenodigd_tw">Uitgenodigd trainingsweekend</option>
            <option value="aangenomen">Aangenomen</option>
          </select>
        </div>
      </div>

      <div class="ds-veld"><label>Interne notities</label>
        <textarea name="notities" rows="2" placeholder="Bijv: aangemeld via telefonisch contact"><?= h($_POST['notities'] ?? '') ?></textarea>
      </div>

      <div class="ds-veld">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:400">
          <input type="checkbox" name="stuur_mail" value="1" checked style="width:auto">
          Stuur welkomstmail naar sollicitant (met portaal link)
        </label>
      </div>

      <div style="display:flex;gap:10px;margin-top:8px">
        <button type="submit" class="ds-btn ds-btn-primair">Toevoegen</button>
        <a href="<?= APP_URL ?>/admin/sollicitanten.php" class="ds-btn ds-btn-secundair">Annuleer</a>
      </div>
    </form>
  </div>
</div>
</div>

<?php require __DIR__ . '/layout_bottom.php'; ?>
