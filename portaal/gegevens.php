<?php
require_once __DIR__ . '/../includes/functions.php';
 if (function_exists('extra_tabellen_auto_setup')) extra_tabellen_auto_setup();
$sol_id = portaal_vereis_login();
$db = db();

// Ophalen gegevens
if (function_exists('personen_kanaal_auto_setup')) personen_kanaal_auto_setup();
$q = $db->prepare("
    SELECT s.id, s.fase, s.aangemeld_op, sz.naam AS seizoen_naam, p.id AS persoon_id,
           p.voornaam, p.tussenvoegsel, p.achternaam, p.email, p.telefoon, p.geboortedatum,
           COALESCE(p.kanaal_voorkeur, 'whatsapp') AS kanaal_voorkeur
    FROM sollicitaties s
    JOIN personen p ON p.id = s.persoon_id
    JOIN seizoenen sz ON sz.id = s.seizoen_id
    WHERE s.id = ?
");
$q->execute([$sol_id]);
$mij = $q->fetch();
if (!$mij) redirect(APP_URL . '/portaal/');

// ── Kanaalvoorkeur voor korte berichten (WhatsApp / sms / alleen e-mail) ──
$kanaal_opties = ['whatsapp' => 'WhatsApp', 'sms' => 'Sms', 'email' => 'Alleen e-mail'];
if (!function_exists('wa_actief') || !wa_actief()) unset($kanaal_opties['whatsapp']);
if (!function_exists('sms_actief') || !sms_actief()) unset($kanaal_opties['sms']);
$kanaal_flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kanaal_opslaan'])) {
    csrf_check();
    $keuze = (string)($_POST['kanaal_voorkeur'] ?? 'email');
    if (!in_array($keuze, ['email', 'whatsapp', 'sms'], true)) $keuze = 'email';
    try {
        $db->prepare("UPDATE personen SET kanaal_voorkeur=?, kanaal_gewijzigd_op=NOW(), kanaal_bron='portaal' WHERE id=?")->execute([$keuze, (int)$mij['persoon_id']]);
        if (function_exists('audit_log')) audit_log('kanaal_voorkeur', 'persoon', (int)$mij['persoon_id'], ['naar' => $keuze, 'via' => 'portaal']);
        $mij['kanaal_voorkeur'] = $keuze;
        $kanaal_flash = 'Je voorkeur is opgeslagen.';
    } catch (Exception $e) { $kanaal_flash = null; }
}

// ── Download data (JSON) ────────────────────────────────────
if (isset($_GET['download'])) {
    $data = ['persoon' => array_diff_key($mij, ['persoon_id' => 1])];
    try {
        // BLK zonder AI analyse (intern bestuurs-oordeel, geen persoonsdata van sollicitant)
        $b = $db->prepare("SELECT * FROM blk_formulieren WHERE sollicitatie_id = ?");
        $b->execute([$sol_id]);
        $blk = $b->fetch();
        if ($blk) {
            unset($blk['ai_analyse'], $blk['ai_analyse_op']);
            $data['blk_formulier'] = $blk;
        } else $data['blk_formulier'] = null;
    } catch (Exception $e) { $data['blk_formulier'] = null; }
    try {
        $b = $db->prepare("SELECT * FROM beschikbaarheid WHERE sollicitatie_id = ?");
        $b->execute([$sol_id]);
        $data['beschikbaarheid'] = $b->fetch() ?: null;
    } catch (Exception $e) { $data['beschikbaarheid'] = null; }
    try {
        $b = $db->prepare("SELECT onderwerp, verstuurd_op, status, kanaal FROM mail_log WHERE sollicitatie_id = ? AND COALESCE(ontvanger_rol,'sollicitant')='sollicitant' ORDER BY verstuurd_op DESC");
        $b->execute([$sol_id]);
        $data['mail_geschiedenis'] = $b->fetchAll();
    } catch (Exception $e) { $data['mail_geschiedenis'] = []; }

    $data['gegenereerd_op'] = date('c');
    $data['toelichting'] = 'Dit is een export van al jouw persoonlijke gegevens bij Reddingsbrigade Paal 13, conform artikel 15 AVG (recht op inzage). Interne beoordelingen van het bestuur zijn niet opgenomen — die vallen onder gerechtvaardigd belang van de organisatie.';

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="paal13-mijn-gegevens-' . date('Y-m-d') . '.json"');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Verwijder account ───────────────────────────────────────
$verwijderd = false;
$fout = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verwijder'])) {
    csrf_check();
    if (($_POST['bevestig'] ?? '') !== 'VERWIJDER') {
        $fout = 'Typ "VERWIJDER" (hoofdletters) om te bevestigen.';
    } else {
        try {
            $db->beginTransaction();
            $db->prepare("DELETE FROM blk_formulieren WHERE sollicitatie_id = ?")->execute([$sol_id]);
            try { $db->prepare("DELETE FROM beschikbaarheid WHERE sollicitatie_id = ?")->execute([$sol_id]); } catch (\Exception $e) {}
            try { $db->prepare("DELETE FROM sollicitant_notities WHERE sollicitatie_id = ?")->execute([$sol_id]); } catch (\Exception $e) {}
            try { $db->prepare("DELETE FROM beoordelingen WHERE sollicitatie_id = ?")->execute([$sol_id]); } catch (\Exception $e) {}

            // Bestuur informeren vóór de anonimisering (de naam is daarna weg); verzending ná het antwoord.
            if (function_exists('melding_verstuur')) {
                try { melding_verstuur('bestuur_zelf_verwijderd', $sol_id, [], ['prioriteit' => 'na_antwoord']); } catch (\Throwable $e) {}
            }

            // Anonimiseer sollicitatie (statistiek blijft)
            $db->prepare("UPDATE sollicitaties SET fase='verwijderd_door_sollicitant' WHERE id = ?")->execute([$sol_id]);

            // Dynamische kolom-anonimisering: check welke bestaan in personen
            $dbn = $db->query("SELECT DATABASE()")->fetchColumn();
            $cols_stmt = $db->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='personen'");
            $cols_stmt->execute([$dbn]);
            $bestaande = array_column($cols_stmt->fetchAll(), 'COLUMN_NAME');
            $anon_email = 'verwijderd_' . $sol_id . '@anoniem.local';

            $anon_map = [
                'voornaam'      => 'Verwijderd',
                'tussenvoegsel' => null,
                'achternaam'    => '(verzoek AVG)',
                'email'         => $anon_email,
                'telefoon'      => null,
                'geboortedatum' => null,
                'adres'         => null,
                'straat'        => null,
                'huisnummer'    => null,
                'postcode'      => null,
                'woonplaats'    => null,
                'geslacht'      => null,
            ];
            $set_delen = [];
            $params = [];
            foreach ($anon_map as $kol => $waarde) {
                if (in_array($kol, $bestaande, true)) {
                    $set_delen[] = "`$kol`=?";
                    $params[] = $waarde;
                }
            }
            if ($set_delen) {
                $sql = "UPDATE personen SET " . implode(', ', $set_delen)
                     . " WHERE id=(SELECT persoon_id FROM sollicitaties WHERE id=?)";
                $params[] = $sol_id;
                $db->prepare($sql)->execute($params);
            }

            // Mail-log ontvanger anonimiseren
            try { $db->prepare("UPDATE mail_log SET ontvanger=?, body=NULL, samenvatting=NULL WHERE sollicitatie_id = ?")->execute([$anon_email, $sol_id]); }
            catch (\Exception $e) { try { $db->prepare("UPDATE mail_log SET ontvanger=? WHERE sollicitatie_id = ?")->execute([$anon_email, $sol_id]); } catch (\Exception $e2) {} }

            $db->commit();
            session_destroy();
            $verwijderd = true;
        } catch (Exception $e) {
            $db->rollBack();
            $fout = 'Verwijderen mislukt: ' . $e->getMessage();
        }
    }
}

$portaal_titel = 'Mijn gegevens';
$portaal_nav = 'gegevens';
$logo  = function_exists('site_logo_url') ? site_logo_url() : '';
require __DIR__ . '/_kop.php';
?>
<style>
.avg-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px;margin-bottom:16px;box-shadow:var(--shadow-card)}
.avg-card h2{margin-top:0;font:var(--text-subhead)}
.avg-rij{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--rand-2);font-size:14px;gap:12px;flex-wrap:wrap}
.avg-rij:last-child{border-bottom:none}
.avg-label{color:var(--ink-soft);flex-shrink:0}
.avg-waarde{color:var(--ink);font-weight:500;text-align:right;word-break:break-word}
.gevaar{border:1px solid #f0c9be;background:var(--error-soft);padding:18px;border-radius:var(--radius-md);margin-top:14px}
.gevaar h3{color:var(--error);margin-top:0;font-size:15px}
.gevaar-input{width:100%;margin-top:8px;padding:10px;border:1px solid var(--error);border-radius:var(--radius-sm);font-family:monospace;background:var(--surface);color:var(--ink)}
</style>

<h1 style="font-size:22px;margin-bottom:6px">Mijn gegevens</h1>
<p style="color:var(--tekst-zacht);font-size:13px;margin-bottom:24px">
  Hier zie je welke gegevens Paal 13 over je bewaart, en heb je controle over inzage en verwijdering conform de AVG.
</p>

<?php if ($verwijderd): ?>
  <div class="flash flash-succes" style="text-align:center;padding:24px">
    <h2 style="margin-top:0"><?= icoon("vink-cirkel", 20) ?> Account verwijderd</h2>
    <p style="margin-bottom:0">Je persoonlijke gegevens zijn geanonimiseerd. Bedankt voor je interesse in Paal 13.</p>
    <p style="font-size:12px;color:var(--tekst-zacht);margin-top:14px">
      <a href="https://paal13.com">Terug naar paal13.com</a>
    </p>
  </div>
<?php else: ?>

  <?php if ($fout): ?>
    <div class="flash flash-fout"><?= h($fout) ?></div>
  <?php endif; ?>

  <!-- Wat is er van jou bekend -->
  <div class="avg-card">
    <h2><?= icoon("persoon", 18) ?> Wat we van je weten</h2>
    <div class="avg-rij">
      <span class="avg-label">Naam</span>
      <span class="avg-waarde"><?= h(trim($mij['voornaam'] . ' ' . ($mij['tussenvoegsel'] ? $mij['tussenvoegsel'] . ' ' : '') . $mij['achternaam'])) ?></span>
    </div>
    <div class="avg-rij">
      <span class="avg-label">E-mail</span>
      <span class="avg-waarde"><?= h($mij['email']) ?></span>
    </div>
    <?php if ($mij['telefoon']): ?>
      <div class="avg-rij"><span class="avg-label">Telefoon</span><span class="avg-waarde"><?= h($mij['telefoon']) ?></span></div>
    <?php endif; ?>
    <?php if ($mij['geboortedatum']): ?>
      <div class="avg-rij"><span class="avg-label">Geboortedatum</span><span class="avg-waarde"><?= h(datum_nl($mij['geboortedatum'])) ?></span></div>
    <?php endif; ?>
    <div class="avg-rij">
      <span class="avg-label">Aangemeld</span>
      <span class="avg-waarde"><?= h(datum_nl(substr($mij['aangemeld_op'],0,10))) ?> — seizoen <?= h($mij['seizoen_naam']) ?></span>
    </div>
    <div class="avg-rij">
      <span class="avg-label">Huidige status</span>
      <span class="avg-waarde"><?= h(fase_label($mij['fase'])) ?></span>
    </div>
  </div>

  <!-- Berichten: kanaalvoorkeur -->
  <div class="avg-card">
    <h2><?= icoon("bel", 18) ?> Hoe wil je berichten ontvangen?</h2>
    <p style="font-size:13px;color:var(--tekst-zacht)">
      Belangrijke berichten (uitnodiging, herinnering, bevestiging) sturen we per e-mail. Korte berichten sturen we
      <?php $kort_kanalen = array_values(array_diff_key($kanaal_opties, ['email' => 1])); ?>
      daarnaast via <?= h($kort_kanalen ? implode(' of ', $kort_kanalen) : 'WhatsApp of sms') ?>
      naar <?= $mij['telefoon'] ? h($mij['telefoon']) : 'je telefoonnummer (nog niet bekend)' ?>. Lukt dat niet, dan krijg je het bericht per e-mail.
      Kies hier wat jij wilt; je kunt dit altijd aanpassen.
    </p>
    <?php if ($kanaal_flash): ?><div class="flash flash-succes" style="margin-bottom:10px"><?= h($kanaal_flash) ?></div><?php endif; ?>
    <form method="POST">
      <?= csrf_input() ?>
      <input type="hidden" name="kanaal_opslaan" value="1">
      <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:12px">
        <?php $huidig = isset($kanaal_opties[$mij['kanaal_voorkeur'] ?? '']) ? $mij['kanaal_voorkeur'] : 'email'; foreach ($kanaal_opties as $kk => $kl): ?>
        <label style="display:flex;align-items:center;gap:10px;font-size:14px;padding:10px 12px;border:1px solid var(--rand-2);border-radius:var(--radius-md);cursor:pointer">
          <input type="radio" name="kanaal_voorkeur" value="<?= $kk ?>" <?= $huidig === $kk ? 'checked' : '' ?>>
          <?= h($kl) ?><?= $kk === 'email' ? ' <span style="color:var(--tekst-zacht);font-size:12px">(geen WhatsApp/sms)</span>' : '' ?>
        </label>
        <?php endforeach; ?>
      </div>
      <button type="submit" class="ds-btn ds-btn-primair">Voorkeur opslaan</button>
    </form>
  </div>

  <!-- Downloaden -->
  <div class="avg-card">
    <h2><?= icoon("download", 18) ?> Al mijn gegevens downloaden</h2>
    <p style="font-size:13px;color:var(--tekst-zacht)">
      Volgens de AVG (artikel 15) heb je recht op inzage. Je krijgt hieronder een JSON-bestand met alles wat we van je hebben: persoon, BLK-formulier, beschikbaarheid en mail-geschiedenis.
    </p>
    <a href="?download=1" class="ds-btn ds-btn-primair"><?= icoon("download", 18) ?> Download als JSON</a>
  </div>

  <!-- Verwijderen -->
  <div class="avg-card">
    <h2><?= icoon("prullenbak", 18) ?> Mijn account verwijderen</h2>
    <p style="font-size:13px;color:var(--tekst-zacht)">
      Volgens de AVG (artikel 17) heb je recht op vergetelheid. Bij verwijdering:
    </p>
    <ul style="font-size:13px;line-height:1.7;color:var(--tekst-zacht);margin:8px 0 12px 22px">
      <li>Je naam, e-mail, telefoon en adres worden geanonimiseerd.</li>
      <li>Je BLK-formulier en beschikbaarheid worden verwijderd.</li>
      <li>Je sollicitatie-record blijft anoniem bestaan voor onze statistieken.</li>
      <li>Deze actie is <strong>onomkeerbaar</strong>.</li>
    </ul>

    <div class="gevaar">
      <h3><?= icoon('waarschuwing', 16) ?> Definitief verwijderen</h3>
      <p style="font-size:12.5px;color:var(--rood);margin-bottom:10px">
        Typ <strong>VERWIJDER</strong> in het veld en klik op de knop.
      </p>
      <form method="POST" onsubmit="return confirm('Weet je het echt zeker? Dit is definitief.');">
        <?= csrf_input() ?>
        <input type="hidden" name="verwijder" value="1">
        <input type="text" name="bevestig" placeholder="VERWIJDER" autocomplete="off" class="gevaar-input" required>
        <button type="submit" class="ds-btn ds-btn-gevaar ds-btn-vol" style="margin-top:10px">Definitief verwijderen</button>
      </form>
    </div>
  </div>

  <?php
    // Uitloggen / naar beheer (alleen als deze sollicitant ook beheertoegang heeft).
    $_kan_beheer = false;
    $_p_email = $_SESSION['portaal_email'] ?? '';
    if (function_exists('toegang_bepaal') && $_p_email) {
        $_t = toegang_bepaal($_p_email);
        $_kan_beheer = !empty($_t['beheer']);
    }
  ?>
  <div class="pt-voetlinks">
    <?php if ($_kan_beheer): ?>
      <a href="<?= APP_URL ?>/wissel.php?naar=beheer"><?= icoon('wissel', 15) ?> Naar beheer</a>
    <?php endif; ?>
    <a href="<?= APP_URL ?>/portaal/?uitloggen=1"><?= icoon('uitloggen', 15) ?> Uitloggen</a>
    <a href="https://paal13.com">paal13.com</a>
  </div>

<?php endif; ?>

<?php require __DIR__ . '/_voet.php'; ?>
