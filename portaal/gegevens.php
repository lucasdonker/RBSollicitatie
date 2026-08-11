<?php
require_once __DIR__ . '/../includes/functions.php';
sessie_start();

// Alleen ingelogde sollicitanten
if (empty($_SESSION['portaal_sol_id'])) {
    redirect(APP_URL . '/portaal/');
}

$db = db();
$sol_id = (int)$_SESSION['portaal_sol_id'];

// Ophalen gegevens
$q = $db->prepare("
    SELECT s.id, s.fase, s.aangemeld_op, sz.naam AS seizoen_naam,
           p.voornaam, p.tussenvoegsel, p.achternaam, p.email, p.telefoon, p.geboortedatum
    FROM sollicitaties s
    JOIN personen p ON p.id = s.persoon_id
    JOIN seizoenen sz ON sz.id = s.seizoen_id
    WHERE s.id = ?
");
$q->execute([$sol_id]);
$mij = $q->fetch();
if (!$mij) redirect(APP_URL . '/portaal/');

// ── Download data (JSON) ────────────────────────────────────
if (isset($_GET['download'])) {
    $data = ['persoon' => $mij];
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
        $b = $db->prepare("SELECT onderwerp, verstuurd_op, status FROM mail_log WHERE sollicitatie_id = ? ORDER BY verstuurd_op DESC");
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
            try { $db->prepare("UPDATE mail_log SET ontvanger=?, ontvanger_naam='Verwijderd' WHERE sollicitatie_id = ?")->execute([$anon_email, $sol_id]); } catch (\Exception $e) {}

            $db->commit();
            session_destroy();
            $verwijderd = true;
        } catch (Exception $e) {
            $db->rollBack();
            $fout = 'Verwijderen mislukt: ' . $e->getMessage();
        }
    }
}

$titel = 'Mijn gegevens';
$logo  = function_exists('site_logo_url') ? site_logo_url() : '';
?>
<!DOCTYPE html>
<html lang="nl" data-theme="">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($titel) ?> — Paal 13</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/stijl.css">
<script>(function(){var t=localStorage.getItem('paal13-theme')||'light';document.documentElement.setAttribute('data-theme',t==='dark'?'dark':'');})();</script>
<style>
body{background:var(--bg);min-height:100vh}
.wrap{max-width:640px;margin:0 auto;padding:24px 16px 48px}
.avg-card{background:var(--bg-card);border:1px solid var(--rand);border-radius:12px;padding:24px;margin-bottom:16px;box-shadow:var(--shadow)}
.avg-card h2{margin-top:0;font-size:17px;font-weight:600}
.avg-rij{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--rand-licht);font-size:13.5px;gap:12px;flex-wrap:wrap}
.avg-rij:last-child{border-bottom:none}
.avg-label{color:var(--tekst-zacht);flex-shrink:0}
.avg-waarde{color:var(--tekst);font-weight:500;text-align:right;word-break:break-word}
.gevaar{border-color:var(--rood-bg);background:var(--rood-bg);padding:20px;border-radius:8px;margin-top:14px}
.gevaar h3{color:var(--rood);margin-top:0;font-size:15px}
.gevaar-input{width:100%;margin-top:8px;padding:10px;border:1px solid var(--rood);border-radius:6px;font-family:monospace;background:var(--bg-card);color:var(--tekst)}
</style>
</head>
<body>
<div class="wrap">

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;gap:12px;flex-wrap:wrap">
  <a href="<?= APP_URL ?>/portaal/" class="btn btn-outline btn-sm">← Terug naar portaal</a>
  <?php if ($logo): ?><img src="<?= h($logo) ?>" alt="Paal 13" style="height:32px"><?php endif; ?>
</div>

<h1 style="font-size:22px;margin-bottom:6px">Mijn gegevens</h1>
<p style="color:var(--tekst-zacht);font-size:13px;margin-bottom:24px">
  Hier zie je welke gegevens Paal 13 over je bewaart, en heb je controle over inzage en verwijdering conform de AVG.
</p>

<?php if ($verwijderd): ?>
  <div class="flash flash-succes" style="text-align:center;padding:24px">
    <h2 style="margin-top:0">✓ Account verwijderd</h2>
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
    <h2>👤 Wat we van je weten</h2>
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

  <!-- Downloaden -->
  <div class="avg-card">
    <h2>📥 Al mijn gegevens downloaden</h2>
    <p style="font-size:13px;color:var(--tekst-zacht)">
      Volgens de AVG (artikel 15) heb je recht op inzage. Je krijgt hieronder een JSON-bestand met alles wat we van je hebben: persoon, BLK-formulier, beschikbaarheid en mail-geschiedenis.
    </p>
    <a href="?download=1" class="btn btn-primary">📄 Download als JSON</a>
  </div>

  <!-- Verwijderen -->
  <div class="avg-card">
    <h2>🗑️ Mijn account verwijderen</h2>
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
      <h3>⚠️ Definitief verwijderen</h3>
      <p style="font-size:12.5px;color:var(--rood);margin-bottom:10px">
        Typ <strong>VERWIJDER</strong> in het veld en klik op de knop.
      </p>
      <form method="POST" onsubmit="return confirm('Weet je het echt zeker? Dit is definitief.');">
        <?= csrf_input() ?>
        <input type="hidden" name="verwijder" value="1">
        <input type="text" name="bevestig" placeholder="VERWIJDER" autocomplete="off" class="gevaar-input" required>
        <button type="submit" class="btn btn-danger" style="margin-top:10px;width:100%">Definitief verwijderen</button>
      </form>
    </div>
  </div>

<?php endif; ?>

</div>
</body>
</html>
