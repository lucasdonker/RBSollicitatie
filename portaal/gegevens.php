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

// ── Weergave: wat we bewaren (AVG art. 15) ──────────────────
// Adres staat in het Beter Leren Kennen-formulier, de ouder/verzorger op de persoon.
$adres = '';
if (!$verwijderd) {
    require_once __DIR__ . '/../includes/blk.php';
    $_blk = function_exists('blk_van') ? blk_van($sol_id) : null;
    if ($_blk) {
        $_a = (!empty($_blk['straat']) || !empty($_blk['postcode'])) ? $_blk : array_merge($_blk, array_filter(blk_adres_splitsen($_blk['adres'] ?? '')));
        $_regel1 = trim(($_a['straat'] ?? '') . ' ' . ($_a['huisnummer'] ?? ''));
        $_regel2 = trim(($_a['postcode'] ?? '') . ' ' . ($_a['woonplaats'] ?? ''));
        $adres = trim($_regel1 . ($_regel1 !== '' && $_regel2 !== '' ? ', ' : '') . $_regel2);
    }
}
$ouder = (!$verwijderd && function_exists('portaal_ouder_gegevens')) ? portaal_ouder_gegevens($sol_id) : [];
$ouder_tekst = implode(' · ', array_filter([$ouder['ouder_naam'] ?? '', $ouder['ouder_email'] ?? '', $ouder['ouder_telefoon'] ?? '']));

$naam = trim($mij['voornaam'] . ' ' . ($mij['tussenvoegsel'] ? $mij['tussenvoegsel'] . ' ' : '') . $mij['achternaam']);
$jaar = '';
if (!empty($mij['geboortedatum'])) {
    try { $jaar = (new DateTime($mij['geboortedatum']))->diff(new DateTime())->y . ' jaar'; } catch (\Exception $e) { $jaar = ''; }
}
$profiel_sub = implode(' · ', array_filter([$jaar, 'aangemeld ' . datum_nl(substr((string)$mij['aangemeld_op'], 0, 10))]));
// Statuschip: de status zoals de sollicitant hem ziet (includes/portaal_dossier.php), niet de beheernaam.
$fase_tekst = portaal_fase_label((string)$mij['fase']);
$fase_kleur = match ((string)$mij['fase']) { 'aangenomen' => 'ok', 'afgewezen' => 'error', 'aangemeld', 'afgehaakt' => 'navy', default => 'accent' };
$wijzig_url = APP_URL . '/portaal/contact.php#vraagTekst';

$portaal_titel = 'Mijn gegevens';
$portaal_nav = 'gegevens';
require __DIR__ . '/_kop.php';
?>

<?php if ($verwijderd): ?>
  <div class="pt-leeg groot" role="status">
    <span class="pt-leeg-icoon"><?= icoon('vink-cirkel', 26) ?></span>
    <h1 class="pt-leeg-titel">Account verwijderd</h1>
    <p class="pt-leeg-tekst">Je persoonlijke gegevens zijn geanonimiseerd. Bedankt voor je interesse in Paal 13.</p>
    <?php if ($loon_bewaard): ?>
      <p class="pt-leeg-tekst">Alleen je loondossier bewaren we nog, omdat de wet dat eist. Daarna vernietigt de penningmeester het.</p>
    <?php endif; ?>
    <a href="https://paal13.com" class="ds-btn ds-btn-secundair">Terug naar paal13.com</a>
  </div>
<?php else: ?>
<div class="pt-stapel pt-gegevens">

  <div class="pt-paginatitel"><h1>Mijn gegevens</h1><span>Inzien, downloaden en laten verwijderen</span></div>

  <!-- Wie je bent (ontwerp 06: initialen, naam, leeftijd · aangemeld) -->
  <section class="pt-profiel" aria-label="Jouw sollicitatie">
    <span class="pt-profiel-initialen" aria-hidden="true"><?= h(portaal_initialen($mij)) ?></span>
    <div class="pt-profiel-tekst">
      <span class="pt-profiel-naam"><?= h($naam) ?></span>
      <span class="pt-profiel-sub"><?= h($profiel_sub) ?> · seizoen <?= h($mij['seizoen_naam']) ?></span>
    </div>
    <span class="pt-profiel-chip <?= $fase_kleur ?>"><span class="pt-profiel-stip" aria-hidden="true"></span><span class="sr-only">Status: </span><?= h($fase_tekst) ?></span>
  </section>

  <div class="pt-gegevens-grid">
    <!-- Persoonsgegevens (AVG art. 15: alles wat we van je weten, ook adres en ouder/verzorger) -->
    <section class="pt-gegevens-lijst" aria-labelledby="ggPersoon">
      <h2 class="pt-gegevens-kop" id="ggPersoon">Persoonsgegevens</h2>
      <?php
        /* Eén rij = label, waarde en (bij wat je kunt laten aanpassen) "Wijzig". De link staat
           in de <dd>, want een <dl> mag alleen dt/dd bevatten. */
        $rijen = [
            ['Naam', $naam, 'je naam'],
            ['E-mailadres', (string)$mij['email'], 'je e-mailadres'],
            ['Telefoonnummer', (string)($mij['telefoon'] ?: 'Nog niet bekend'), 'je telefoonnummer'],
            ['Geboortedatum', $mij['geboortedatum'] ? datum_nl($mij['geboortedatum']) : '', ''],
            ['Adres', $adres, 'je adres'],
            ['Ouder / verzorger', $ouder_tekst, ''],
            ['Status', $fase_tekst, ''],
        ];
      ?>
      <dl class="pt-gegevens-rijen">
        <?php foreach ($rijen as $r): if ($r[1] === '') continue; ?>
        <div class="pt-gegevens-rij">
          <dt><?= h($r[0]) ?></dt>
          <dd><span class="pt-gegevens-waarde"><?= h($r[1]) ?></span><?php if ($r[2] !== ''): ?>
            <a href="<?= h($wijzig_url) ?>" class="pt-gegevens-wijzig">Wijzig<span class="sr-only"> <?= h($r[2]) ?></span></a><?php endif; ?></dd>
        </div>
        <?php endforeach; ?>
      </dl>
      <?php /* AVG art. 16 (rectificatie): zelf wijzigen kan niet, het bestuur past het aan. */ ?>
      <p class="pt-gegevens-voet">Klopt er iets niet? Via "Wijzig" laat je het ons weten op Contact; het bestuur past het dan aan.</p>
    </section>

    <!-- Jouw gegevens (AVG): downloaden (art. 15) en verwijderen (art. 17) -->
    <section class="pt-stapel" id="download" aria-labelledby="ggAvg">
      <div class="pt-kaart">
        <h2 class="pt-kaart-titel" id="ggAvg">Jouw gegevens (AVG)</h2>
        <p class="pt-kaart-tekst">Download alles wat we over je hebben vastgelegd (AVG artikel 15), of laat je account verwijderen.
          In het bestand staan je gegevens, formulieren, beschikbaarheid, beoordelingen en besluiten, notities, documenten en
          ondertekeningen (zonder de bestanden zelf) en de berichten die we je stuurden.</p>
      </div>
      <div class="pt-takenlijst">
        <a href="?download=1" class="pt-taakrij" download>
          <span class="pt-taakrij-icoon navy"><?= icoon('download', 20) ?></span>
          <span class="pt-taakrij-tekst">
            <span class="pt-taakrij-titel">Mijn gegevens downloaden</span>
            <span class="pt-taakrij-sub">Alles wat we van je bewaren, als bestand (JSON)</span>
          </span>
          <span class="pt-taakrij-chevron"><?= icoon('chevron', 18) ?></span>
        </a>
        <div class="pt-taakrij-scheiding"></div>
        <a href="#verwijderen" class="pt-taakrij">
          <span class="pt-taakrij-icoon error"><?= icoon('prullenbak', 20) ?></span>
          <span class="pt-taakrij-tekst">
            <span class="pt-taakrij-titel">Mijn account verwijderen</span>
            <span class="pt-taakrij-sub">Je sollicitatie stopt dan en je portaal wordt gesloten</span>
          </span>
          <span class="pt-taakrij-chevron"><?= icoon('chevron', 18) ?></span>
        </a>
      </div>
    </section>
  </div>

  <!-- Berichten: kanaalvoorkeur -->
  <section class="pt-kaart" aria-labelledby="ggKanaal">
    <h2 class="pt-kaart-titel" id="ggKanaal"><?= icoon('bel', 18) ?> Hoe wil je berichten ontvangen?</h2>
    <p class="pt-kaart-tekst">
      Belangrijke berichten (uitnodiging, herinnering, bevestiging) sturen we per e-mail. Korte berichten sturen we
      <?php $kort_kanalen = array_values(array_diff_key($kanaal_opties, ['email' => 1])); ?>
      daarnaast via <?= h($kort_kanalen ? implode(' of ', $kort_kanalen) : 'WhatsApp of sms') ?>
      naar <?= $mij['telefoon'] ? h($mij['telefoon']) : 'je telefoonnummer (nog niet bekend)' ?>. Lukt dat niet, dan krijg je het bericht per e-mail.
    </p>
    <?php if ($kanaal_flash): ?><div class="ds-melding ds-melding-ok" role="status"><?= icoon('vink-cirkel', 18) ?> <div><?= h($kanaal_flash) ?></div></div><?php endif; ?>
    <form method="POST" class="pt-gegevens-form">
      <?= csrf_input() ?>
      <input type="hidden" name="kanaal_opslaan" value="1">
      <fieldset class="pt-deelname-keuze pt-kanaal-keuze">
        <legend class="pt-inline-legend">Kies wat jij wilt; je kunt dit altijd aanpassen.</legend>
        <?php $huidig = isset($kanaal_opties[$mij['kanaal_voorkeur'] ?? '']) ? $mij['kanaal_voorkeur'] : 'email'; foreach ($kanaal_opties as $kk => $kl): ?>
        <label class="pt-deelname-optie">
          <input type="radio" name="kanaal_voorkeur" value="<?= h($kk) ?>" <?= $huidig === $kk ? 'checked' : '' ?>>
          <span><?= h($kl) ?><?= $kk === 'email' ? ' <span class="pt-kanaal-uitleg">(geen WhatsApp of sms)</span>' : '' ?></span>
        </label>
        <?php endforeach; ?>
      </fieldset>
      <button type="submit" class="ds-btn ds-btn-primair">Voorkeur opslaan</button>
    </form>
  </section>

  <?php
    $ik_sol_id = $sol_id; $ik_persoon = (int)$mij['persoon_id']; $ik_fase = (string)$mij['fase'];
    $ik_flash = $intrek_flash; $ik_fout = $intrek_fout;
    require __DIR__ . '/_intrekken.php';
  ?>

  <?php if ($apparaten_aantal > 0 || $apparaten_flash): ?>
  <section class="pt-kaart" aria-labelledby="ggApparaten">
    <h2 class="pt-kaart-titel" id="ggApparaten"><?= icoon('slot', 18) ?> Ingelogd blijven</h2>
    <?php if ($apparaten_flash): ?>
      <div class="ds-melding ds-melding-ok" role="status"><?= icoon('vink-cirkel', 18) ?> <div><?= h($apparaten_flash) ?></div></div>
    <?php else: ?>
      <p class="pt-kaart-tekst">
        <?= $apparaten_aantal === 1
            ? 'Eén apparaat onthoudt je, zodat je daar niet steeds opnieuw hoeft in te loggen.'
            : h((string)$apparaten_aantal) . ' apparaten onthouden je, zodat je daar niet steeds opnieuw hoeft in te loggen.' ?>
        Telefoon kwijt of op een vreemde computer ingelogd? Vergeet ze dan allemaal.
      </p>
      <?php $bev = [
          'knop_open' => 'Vergeet alle apparaten',
          'tekst'     => ['Op elk apparaat — ook dit — moet daarna opnieuw worden ingelogd met een link uit je mail.'],
          'knop'      => 'Ja, vergeet ze allemaal',
          'velden'    => ['apparaten_vergeten' => '1'],
      ]; require __DIR__ . '/_bevestig.php'; ?>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <!-- Verwijderen (AVG art. 17) -->
  <section class="pt-kaart pt-gegevens-gevaar" id="verwijderen" aria-labelledby="ggVerwijder">
    <h2 class="pt-kaart-titel" id="ggVerwijder"><?= icoon('prullenbak', 18) ?> Mijn account verwijderen</h2>
    <p class="pt-kaart-tekst">Volgens de AVG (artikel 17) mag je ons vragen je gegevens te wissen. Bij verwijderen:</p>
    <ul class="pt-gegevens-gevolgen">
      <li>Je <?= $loon_bewaard ? 'e-mail, telefoon' : 'naam, e-mail, telefoon' ?> en adres worden geanonimiseerd.</li>
      <li>Je formulieren, foto, beschikbaarheid en beoordelingen worden verwijderd.</li>
      <li>Je sollicitatie blijft anoniem bestaan voor onze statistieken.</li>
      <li>Je sollicitatie stopt en je kunt niet meer inloggen. Dit is <strong>onomkeerbaar</strong>.</li>
    </ul>
    <?php if ($loon_bewaard): ?>
      <?php /* AVG art. 17 lid 3 sub b: alleen wat de wet eist blijft; B1-taal. */ ?>
      <div class="ds-melding"><?= icoon('info', 18) ?> <div>
        <strong>Wat blijft er bewaard?</strong>
        <p>Je loongegevens zijn al verwerkt. De wet (voor de belasting) verplicht ons die te bewaren.
          Daarom blijft dit staan: je naam, je geboortedatum, je ondertekende opgaaf voor de loonheffing (met je BSN) en de kopie van je identiteitsbewijs.
          Alleen de penningmeester kan daarbij.</p>
        <p>We bewaren het tot 5 jaar na het einde van het jaar waarin je voor het laatst bij ons werkte.
          Daarna vernietigt de penningmeester het. Al het andere verwijderen we meteen.</p>
      </div></div>
    <?php endif; ?>
    <?php $bev = [
        'knop_open' => 'Definitief verwijderen',
        'icoon'     => 'waarschuwing',
        'gevaar'    => true,
        'titel'     => 'Gegevens laten verwijderen?',
        'tekst'     => [($loon_bewaard ? 'Je e-mail, telefoon en adres worden onherkenbaar gemaakt; alleen je loondossier blijft.' : 'Je naam, e-mail, telefoon en adres worden onherkenbaar gemaakt.')
                        . ' Je formulieren en beschikbaarheid verdwijnen. Je kunt daarna niet meer inloggen. Dit kun je niet terugdraaien.'],
        'typ'       => 'VERWIJDER',
        'knop'      => 'Ja, verwijder mijn account',
        'velden'    => ['verwijder' => '1'],
        'fout'      => isset($_POST['verwijder']) ? $fout : '',
    ]; require __DIR__ . '/_bevestig.php'; ?>
  </section>

  <?php if (function_exists('privacy_url')): ?>
    <p class="pt-hulp">Hoe lang we je gegevens bewaren, lees je in de <a href="<?= h(privacy_url()) ?>">privacyverklaring</a>.</p>
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

</div>
<?php endif; ?>

<?php require __DIR__ . '/_voet.php'; ?>
