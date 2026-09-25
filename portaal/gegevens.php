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

/* "Blijf ingelogd" moet je ook kunnen terugdraaien — bijvoorbeeld als je je telefoon
   kwijt bent. Dit trekt álle apparaten in, ook het huidige; daarna moet overal opnieuw
   worden ingelogd. Dat is de bedoeling. */
$apparaten_flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apparaten_vergeten'])) {
    csrf_check();
    if (function_exists('portaal_onthoud_intrekken_alle')) {
        portaal_onthoud_intrekken_alle($sol_id);
        portaal_onthoud_cookie('');
        if (function_exists('audit_log')) audit_log('apparaten_vergeten', 'sollicitatie', $sol_id, ['via' => 'portaal']);
        $apparaten_flash = 'Klaar — op elk apparaat moet nu opnieuw worden ingelogd.';
    }
}
$apparaten_aantal = function_exists('portaal_onthoud_aantal') ? portaal_onthoud_aantal($sol_id) : 0;

// ── Verzoek om in te trekken (portaal/_intrekken.php, includes/intrekken.php) ──
$intrek_flash = null; $intrek_fout = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['intrek_actie']) && function_exists('intrek_aanvragen')) {
    csrf_check();
    if ($_POST['intrek_actie'] === 'annuleren') {
        $intrek_flash = intrek_annuleren($sol_id) ? 'Je verzoek is geannuleerd. Je sollicitatie loopt gewoon door.' : null;
    } else {
        $intrek_fout = intrek_aanvragen($sol_id, (string)($_POST['reden'] ?? ''), !empty($_POST['seintje'])) ?: null;
        if (!$intrek_fout) $intrek_flash = 'Je verzoek is verstuurd. We nemen contact met je op.';
    }
}

// ── Download data (JSON) ────────────────────────────────────
// Alles wat over deze sollicitatie is vastgelegd (AVG art. 15); de lijst staat in avg_inzage().
if (isset($_GET['download'])) {
    $data = ['toelichting' => 'Dit zijn alle gegevens die Reddingsbrigade Paal 13 over jou heeft vastgelegd voor je sollicitatie (AVG artikel 15, recht op inzage). '
                            . 'Je BSN, IBAN en handtekening staan hier niet in; die staan in de ondertekende documenten onder je taken. '
                            . 'Waar "beoordelaar" staat, laten we de naam van die persoon weg.',
             'gegenereerd_op' => date('c'),
             'persoon' => array_diff_key($mij, ['persoon_id' => 1])]
          + avg_inzage($db, $sol_id);

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="paal13-mijn-gegevens-' . date('Y-m-d') . '.json"');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Verwijder account ───────────────────────────────────────
$verwijderd = false;
$fout = '';
/* AVG art. 17 lid 3 sub b: is het loondossier al verwerkt, dan bewaren we alleen wat de wet
   eist (loonheffing met BSN, kopie ID, naam en geboortedatum). De rest gaat gewoon weg. */
$loon_bewaard = function_exists('documenten_loondossier') && !empty(documenten_loondossier($sol_id)['verwerkt']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verwijder'])) {
    csrf_check();
    if (($_POST['bevestig'] ?? '') !== 'VERWIJDER') {
        $fout = 'Typ "VERWIJDER" (hoofdletters) om te bevestigen.';
    } else {
        try {
            $db->beginTransaction();
            // Kluis, taken, ondertekengegevens, BLK, beschikbaarheid, beoordelingen, selectiedag,
            // trainingsweekend, apparaten (includes/avg_wissen.php). Een echte fout rolt alles
            // terug; de sollicitant krijgt dan een eerlijke melding.
            avg_sollicitatie_leegmaken($db, $sol_id, !$loon_bewaard);

            // Bestuur informeren vóór de anonimisering (de naam is daarna weg); verzending ná het antwoord.
            if (function_exists('melding_verstuur')) {
                try { melding_verstuur('bestuur_zelf_verwijderd', $sol_id, [], ['prioriteit' => 'na_antwoord']); } catch (\Throwable $e) {}
            }

            // Anonimiseer sollicitatie (statistiek blijft)
            $db->prepare("UPDATE sollicitaties SET fase='verwijderd_door_sollicitant' WHERE id = ?")->execute([$sol_id]);

            $anon_email = 'verwijderd_' . $sol_id . '@anoniem.local';
            $pq = $db->prepare("SELECT persoon_id FROM sollicitaties WHERE id=?");
            $pq->execute([$sol_id]);
            avg_persoon_anonimiseren($db, (int)$pq->fetchColumn(), 'Verwijderd', '(verzoek AVG)', $anon_email,
                                     $loon_bewaard ? avg_behoud_loondossier() : []);
            avg_maillog_anonimiseren($db, $sol_id, $anon_email);

            $db->commit();
            if (function_exists('portaal_onthoud_cookie')) portaal_onthoud_cookie('');
            session_destroy();
            $verwijderd = true;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            p13_fout('P13-P004', 'AVG-verwijdering sollicitatie ' . $sol_id . ' mislukt', $e);
            $fout = 'Het verwijderen is niet gelukt; er is niets gewist. Het beheer is automatisch op de hoogte gebracht. '
                  . 'Probeer het later opnieuw of mail ' . portaal_contact_email() . '.';
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
.avg-card .pt-kaart-tekst{font-size:13px;color:var(--tekst-zacht)}
.avg-rij{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--rand-2);font-size:14px;gap:12px;flex-wrap:wrap}
.avg-rij:last-child{border-bottom:none}
.avg-label{color:var(--ink-soft);flex-shrink:0}
.avg-waarde{color:var(--ink);font-weight:500;text-align:right;word-break:break-word}
.gevaar{border:1px solid var(--fase-rood-rand);background:var(--error-soft);padding:18px;border-radius:var(--radius-md);margin-top:14px}
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
    <?php if ($loon_bewaard): ?>
      <p style="margin-bottom:0">Alleen je loondossier bewaren we nog, omdat de wet dat eist. Daarna vernietigt de penningmeester het.</p>
    <?php endif; ?>
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
    <?php /* AVG art. 16 (rectificatie): zelf wijzigen kan niet, het bestuur past het aan. */ ?>
    <p style="font-size:13px;color:var(--tekst-zacht);margin:12px 0 0">
      Klopt je naam, e-mailadres of telefoonnummer niet?
      <a href="<?= APP_URL ?>/portaal/berichten.php#vraagTekst">Stel een vraag via Berichten</a>, dan past het bestuur het aan.
    </p>
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

  <?php
    $ik_sol_id = $sol_id; $ik_persoon = (int)$mij['persoon_id']; $ik_fase = (string)$mij['fase'];
    $ik_flash = $intrek_flash; $ik_fout = $intrek_fout;
    require __DIR__ . '/_intrekken.php';
  ?>

  <!-- Downloaden -->
  <div class="avg-card" id="download">
    <h2><?= icoon("download", 18) ?> Al mijn gegevens downloaden</h2>
    <p style="font-size:13px;color:var(--tekst-zacht)">
      Volgens de AVG (artikel 15) heb je recht op inzage. Je krijgt hieronder een JSON-bestand met alles wat we over je hebben vastgelegd:
      je gegevens, je formulieren, je beschikbaarheid, de beoordelingen en besluiten van de selectiedag en het trainingsweekend,
      de AI-analyse, de notities van het bestuur, je documenten en ondertekeningen (zonder de bestanden zelf) en de berichten die we je stuurden.
    </p>
    <a href="?download=1" class="ds-btn ds-btn-primair"><?= icoon("download", 18) ?> Download als JSON</a>
  </div>

  <!-- Verwijderen -->
  <div class="avg-card" id="verwijderen">
    <h2><?= icoon("prullenbak", 18) ?> Mijn account verwijderen</h2>
    <p style="font-size:13px;color:var(--tekst-zacht)">
      Volgens de AVG (artikel 17) heb je recht op vergetelheid. Bij verwijdering:
    </p>
    <ul style="font-size:13px;line-height:1.7;color:var(--tekst-zacht);margin:8px 0 12px 22px">
      <li>Je <?= $loon_bewaard ? 'e-mail, telefoon' : 'naam, e-mail, telefoon' ?> en adres worden geanonimiseerd.</li>
      <li>Je formulieren, foto, beschikbaarheid en beoordelingen worden verwijderd.</li>
      <li>Je sollicitatie-record blijft anoniem bestaan voor onze statistieken.</li>
      <li>Deze actie is <strong>onomkeerbaar</strong>.</li>
    </ul>
    <?php if ($loon_bewaard): ?>
      <?php /* AVG art. 17 lid 3 sub b: alleen wat de wet eist blijft; B1-taal. */ ?>
      <div class="ds-melding" style="margin-bottom:12px"><?= icoon('info', 18) ?> <div>
        <strong>Wat blijft er bewaard?</strong>
        <p style="margin:6px 0 0">Je loongegevens zijn al verwerkt. De wet (voor de belasting) verplicht ons die te bewaren.
          Daarom blijft dit staan: je naam, je geboortedatum, je ondertekende opgaaf voor de loonheffing (met je BSN) en de kopie van je identiteitsbewijs.
          Alleen de penningmeester kan daarbij.</p>
        <p style="margin:6px 0 0">We bewaren het tot 5 jaar na het einde van het jaar waarin je voor het laatst bij ons werkte.
          Daarna vernietigt de penningmeester het. Al het andere verwijderen we meteen.</p>
      </div></div>
    <?php endif; ?>

    <?php /* Zelfde patroon als "Vergeet alle apparaten": eerst uitklappen, dan pas de knop.
             Werkt zonder JavaScript; na een fout staat hij meteen open. */ ?>
    <details class="pt-bevestig gevaar"<?= ($fout && isset($_POST['verwijder'])) ? ' open' : '' ?>>
      <summary><?= icoon('waarschuwing', 16) ?> Definitief verwijderen</summary>
      <p><?= $loon_bewaard ? 'Je e-mail, telefoon en adres worden onherkenbaar gemaakt; alleen je loondossier blijft.' : 'Je naam, e-mail, telefoon en adres worden onherkenbaar gemaakt.' ?> Je formulieren en beschikbaarheid verdwijnen.
        Je kunt daarna niet meer inloggen. Dit kun je niet terugdraaien.</p>
      <p>Weet je het zeker? Typ <strong>VERWIJDER</strong> in het veld en klik op de knop.</p>
      <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="verwijder" value="1">
        <label for="bevestigVerwijder" class="sr-only">Typ VERWIJDER om te bevestigen</label>
        <input type="text" id="bevestigVerwijder" name="bevestig" placeholder="VERWIJDER" autocomplete="off" class="gevaar-input" required>
        <button type="submit" class="ds-btn ds-btn-gevaar ds-btn-vol" style="margin-top:10px">Ja, verwijder mijn account</button>
      </form>
    </details>
  </div>

  <?php if ($apparaten_aantal > 0 || $apparaten_flash): ?>
  <div class="pt-kaart">
    <h2 class="pt-kaart-titel"><?= icoon('slot', 18) ?> Ingelogd blijven</h2>
    <?php if ($apparaten_flash): ?>
      <div class="ds-melding ds-melding-ok"><?= icoon('vink-cirkel', 18) ?> <div><?= h($apparaten_flash) ?></div></div>
    <?php else: ?>
      <p class="pt-kaart-tekst">
        <?= $apparaten_aantal === 1
            ? 'Eén apparaat onthoudt je, zodat je daar niet steeds opnieuw hoeft in te loggen.'
            : h((string)$apparaten_aantal) . ' apparaten onthouden je, zodat je daar niet steeds opnieuw hoeft in te loggen.' ?>
        Telefoon kwijt of op een vreemde computer ingelogd? Vergeet ze dan allemaal.
      </p>
      <?php /* Uitklappen en dan pas de knop: twee bewuste handelingen, en het werkt
               zonder JavaScript. De data-bevestig-haak uit het beheer draait hier niet. */ ?>
      <details class="pt-bevestig">
        <summary>Vergeet alle apparaten</summary>
        <p>Op elk apparaat — ook dit — moet daarna opnieuw worden ingelogd met een link uit je mail.</p>
        <form method="POST">
          <?= csrf_input() ?>
          <input type="hidden" name="apparaten_vergeten" value="1">
          <button type="submit" class="ds-btn ds-btn-secundair">Ja, vergeet ze allemaal</button>
        </form>
      </details>
    <?php endif; ?>
  </div>
  <?php endif; ?>

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
