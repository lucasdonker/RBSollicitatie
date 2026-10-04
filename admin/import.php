<?php
/**
 * Data Import Tool
 * Importeert de bestaande sollicitanten uit het CSV bestand
 * Eenmalig te gebruiken bij eerste installatie
 * Daarna veilig — draait nooit dubbel door email-check
 */

$config_path = __DIR__ . '/../includes/config.php';
if (!file_exists($config_path)) {
    die('Niet geïnstalleerd.');
}
require_once __DIR__ . '/../includes/functions.php';
// Beveiliging: alleen een ingelogde admin mag data importeren.
vereist_rol('admin');

$seizoen = actief_seizoen();
$stap = $_GET['stap'] ?? 'overzicht';
$resultaten = [];
$fouten = [];

// Verwerk import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_import'])) {
    csrf_check();
    if (empty($_FILES['csv_bestand']['tmp_name'])) {
        $fout_upload = 'Geen bestand geüpload.';
    } else {
        $seizoen_id = (int)$_POST['seizoen_id'];
        $fase = $_POST['fase'] ?? 'aangemeld';
        $tmp = $_FILES['csv_bestand']['tmp_name'];

        // Parse CSV
        $handle = fopen($tmp, 'r');
        $headers = fgetcsv($handle); // eerste rij = headers
        // Strip BOM
        $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");

        $db = db();
        $aangemaakt = 0;
        $bijgewerkt = 0;
        $overgeslagen = 0;
        $rij_nr = 1;

        while (($rij = fgetcsv($handle)) !== false) {
            $rij_nr++;
            if (count($rij) < count($headers)) continue;
            $data = array_combine($headers, $rij);

            $naam = trim($data['Naam'] ?? '');
            $email = strtolower(trim($data['Email'] ?? ''));
            if (!$naam || !$email) { $overgeslagen++; continue; }

            // Naam splitsen
            $delen = explode(' ', $naam);
            $voornaam = array_shift($delen);
            $achternaam = array_pop($delen) ?? $voornaam;
            $tussenvoegsel = !empty($delen) ? implode(' ', $delen) : null;
            if (!$achternaam) { $achternaam = $voornaam; $voornaam = ''; }

            // Telefoon normaliseren
            $tel = preg_replace('/[^0-9+]/', '', $data['Telefoon nummer'] ?? '');
            if (strlen($tel) > 9 && !str_starts_with($tel, '+') && !str_starts_with($tel, '0')) {
                $tel = '+' . $tel; // 31612345678 -> +31612345678
            }

            // Geboortedatum
            $dob = $data['Geboorte datum'] ?? null;
            if ($dob && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) $dob = null;

            // Geslacht
            $geslacht_map = ['Man' => 'man', 'Vrouw' => 'vrouw', '' => 'onbekend'];
            $geslacht = $geslacht_map[$data['Geslacht'] ?? ''] ?? 'onbekend';

            try {
                // Persoon aanmaken of updaten
                $bestaand = $db->prepare("SELECT id FROM personen WHERE email=?");
                $bestaand->execute([$email]);
                $persoon = $bestaand->fetch();

                if ($persoon) {
                    $persoon_id = $persoon['id'];
                    $db->prepare("UPDATE personen SET voornaam=?,tussenvoegsel=?,achternaam=?,telefoon=?,geboortedatum=?,geslacht=? WHERE id=?")
                       ->execute([$voornaam,$tussenvoegsel,$achternaam,$tel,$dob,$geslacht,$persoon_id]);
                    $bijgewerkt++;
                } else {
                    $db->prepare("INSERT INTO personen (voornaam,tussenvoegsel,achternaam,email,telefoon,geboortedatum,geslacht) VALUES(?,?,?,?,?,?,?)")
                       ->execute([$voornaam,$tussenvoegsel,$achternaam,$email,$tel,$dob,$geslacht]);
                    $persoon_id = $db->lastInsertId();
                    $aangemaakt++;
                }

                // Check of al in dit seizoen
                $al_sol = $db->prepare("SELECT id FROM sollicitaties WHERE persoon_id=? AND seizoen_id=?");
                $al_sol->execute([$persoon_id, $seizoen_id]);
                if ($al_sol->fetch()) { $overgeslagen++; continue; }

                // Check terugkerend
                $eerder = $db->prepare("SELECT COUNT(*) FROM sollicitaties WHERE persoon_id=?");
                $eerder->execute([$persoon_id]);
                $terugkerend = $eerder->fetchColumn() > 0 ? 1 : 0;

                // Token
                $token = genereer_token();

                // Sollicitatie aanmaken
                $db->prepare("INSERT INTO sollicitaties (persoon_id,seizoen_id,fase,terugkerend,portaal_token) VALUES(?,?,?,?,?)")
                   ->execute([$persoon_id,$seizoen_id,$fase,$terugkerend,$token]);
                $sol_id = $db->lastInsertId();

                // Beschikbaarheid importeren
                $weken = [];
                for ($w = 23; $w <= 35; $w++) {
                    if (($data["Week $w"] ?? '') === '✓') $weken[] = $w;
                }
                $uitzonderingen = trim($data['Beschikbaarheid uitzonderingen'] ?? '') ?: null;
                $opmerkingen = trim($data['Opmerkingen'] ?? '') ?: null;
                $alle_uitz = array_filter([$uitzonderingen, $opmerkingen]);

                if (!empty($weken) || !empty($alle_uitz)) {
                    $db->prepare("INSERT INTO beschikbaarheid (sollicitatie_id,weken_json,uitzonderingen,ingevuld_op) VALUES(?,?,?,NOW())")
                       ->execute([$sol_id, json_encode($weken), implode("\n", $alle_uitz) ?: null]);
                }

                // BLK data importeren (uit CSV velden)
                $motivatie_cols = [
                    'Motivatie',
                    'Waarom vind je het strand leuk?',
                ];
                $motivatie = implode("\n\n", array_filter(array_map(fn($k) => trim($data[$k] ?? ''), $motivatie_cols)));
                $ervaring = trim($data['Werk ervaring'] ?? '') ?: null;
                $diplomas = trim($data["Diploma's"] ?? '') ?: null;
                $opleiding = trim($data['Opleiding'] ?? '') ?: null;
                $social = trim($data['Sociale Media'] ?? '') ?: null;

                if ($motivatie || $ervaring || $diplomas) {
                    $db->prepare("INSERT INTO blk_formulieren (sollicitatie_id,motivatie,ervaring,over_jezelf,ingevuld_op) VALUES(?,?,?,?,NOW())")
                       ->execute([$sol_id, $motivatie ?: null, $ervaring ? "$diplomas\n$ervaring" : $diplomas, $opleiding, ]);
                }

                $resultaten[] = ['naam' => $naam, 'email' => $email, 'weken' => count($weken), 'ok' => true];

            } catch (Exception $e) {
                $fouten[] = ['naam' => $naam, 'rij' => $rij_nr, 'fout' => $e->getMessage()];
            }
        }
        fclose($handle);

        $stap = 'resultaat';
        $import_stats = compact('aangemaakt','bijgewerkt','overgeslagen');
    }
}

// Seizoenen ophalen
$seizoenen = db()->query("SELECT * FROM seizoenen ORDER BY id DESC")->fetchAll();

$pagina_titel = 'Sollicitanten importeren';
$actieve_nav  = 'import';   // eigen hulptekst; bh_nav_actief() laat Sollicitanten oplichten
require __DIR__ . '/layout_top.php';
?>
<style>
.imp-result-rij{display:flex;align-items:center;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--rand);font-size:13px}
.imp-result-rij:last-child{border-bottom:none}
.imp-fout-rij{background:var(--rood-bg);border-radius:5px;padding:8px 10px;font-size:12px;color:var(--error);margin-bottom:6px}
.imp-stat-rij{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px}
.imp-stat-box{background:var(--grijs-bg);border-radius:8px;padding:12px;text-align:center}
.imp-stat-box .n{font-size:24px;font-weight:700;color:var(--navy)}
.imp-stat-box .l{font-size:11px;color:var(--tekst-zacht);margin-top:2px}
</style>
<div class="page-body">

  <?php if ($stap === 'overzicht'): ?>

  <div class="ds-kaart">
    <div class="bh-kaartkop"><h2>Vóór je importeert</h2></div>
    <div class="bh-kaartbody">
      <div class="ds-melding ds-melding-warn">
        <strong>Eenmalige actie.</strong> Het systeem controleert op e-mailadres — dubbelen worden automatisch overgeslagen. Veilig om meerdere keren te draaien.
      </div>
      <ul class="bh-imp-uitleg">
        <li>Personen worden aangemaakt of bijgewerkt op e-mailadres</li>
        <li>Beschikbaarheid (week 23–35) wordt geïmporteerd</li>
        <li>Motivatie, ervaring en diploma's worden geïmporteerd als kennismakingsformulier</li>
        <li>CSV formaat: zoals geëxporteerd uit het huidige Google Sheets systeem</li>
      </ul>
    </div>
  </div>

  <div class="ds-kaart">
    <div class="bh-kaartkop"><h2>CSV uploaden</h2></div>
    <div class="bh-kaartbody">
      <?php if (isset($fout_upload)): ?>
      <div class="ds-melding ds-melding-error"><?= h($fout_upload) ?></div>
      <?php endif; ?>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="start_import" value="1">
        <?= csrf_input() ?>
        <div class="ds-veld">
          <label for="veld-seizoen-id">Seizoen</label>
          <select id="veld-seizoen-id" name="seizoen_id" required>
            <?php foreach ($seizoenen as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $s === reset($seizoenen) ? 'selected' : '' ?>>
              <?= h($s['naam']) ?> (<?= $s['status'] ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="ds-veld">
          <label for="veld-fase">Beginfase</label>
          <select id="veld-fase" name="fase">
            <option value="aangemeld">Aangemeld (nieuw)</option>
            <option value="uitgenodigd_selectie">Uitgenodigd selectiedag</option>
            <option value="aangemeld_selectie">Aangemeld selectiedag</option>
            <option value="selectiedag_geweest">Selectiedag geweest</option>
            <option value="uitgenodigd_tw">Uitgenodigd trainingsweekend</option>
            <option value="aangenomen">Aangenomen</option>
          </select>
          <div class="ds-veld-hint">Kies de juiste fase voor de te importeren sollicitanten. Als ze allemaal al een selectiedag hebben gehad, kies dan "Selectiedag geweest".</div>
        </div>
        <div class="ds-veld">
          <label for="veld-csv-bestand">CSV bestand</label>
          <input id="veld-csv-bestand" type="file" name="csv_bestand" accept=".csv" required>
          <div class="ds-veld-hint">Exporteer uit Google Sheets als CSV (kommagescheiden, UTF-8)</div>
        </div>
        <button type="submit" class="ds-btn ds-btn-primair">Import starten</button>
        <a href="<?= APP_URL ?>/admin/" class="ds-btn ds-btn-secundair bh-imp-annuleer">Annuleer</a>
      </form>
    </div>
  </div>

  <?php elseif ($stap === 'resultaat'): ?>

  <div class="ds-kaart">
    <div class="bh-kaartkop"><h2><?= icoon('vink', 18) ?> Import voltooid</h2></div>
    <div class="bh-kaartbody">
      <div class="imp-stat-rij">
        <div class="imp-stat-box"><div class="n"><?= $import_stats['aangemaakt'] ?></div><div class="l">Aangemaakt</div></div>
        <div class="imp-stat-box"><div class="n"><?= $import_stats['bijgewerkt'] ?></div><div class="l">Bijgewerkt</div></div>
        <div class="imp-stat-box"><div class="n"><?= $import_stats['overgeslagen'] ?></div><div class="l">Overgeslagen</div></div>
      </div>

      <?php if (!empty($fouten)): ?>
      <div class="bh-imp-fouten">
        <div class="bh-imp-foutenkop">Fouten (<?= count($fouten) ?>):</div>
        <?php foreach ($fouten as $f): ?>
        <div class="imp-fout-rij">Rij <?= $f['rij'] ?>: <?= h($f['naam']) ?> — <?= h($f['fout']) ?></div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($resultaten)): ?>
      <div class="bh-imp-resultaten">
        <?php foreach ($resultaten as $r): ?>
        <div class="imp-result-rij">
          <span><?= h($r['naam']) ?></span>
          <span class="bh-imp-detail"><?= $r['weken'] ?> weken | <?= h($r['email']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="bh-imp-voet">
        <a href="<?= APP_URL ?>/admin/sollicitanten.php" class="ds-btn ds-btn-primair">Naar sollicitanten <?= icoon('chevron', 14) ?></a>
        <a href="<?= APP_URL ?>/admin/import.php" class="ds-btn ds-btn-secundair">Nog een import</a>
      </div>
    </div>
  </div>

  <?php endif; ?>

</div>
<?php require __DIR__ . '/layout_bottom.php'; ?>
