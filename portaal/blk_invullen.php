<?php
/**
 * Beter Leren Kennen — losse invulpagina (Portaal.dc sectie 03).
 *
 * Vier stappen (gegevens, achtergrond, motivatie, overig) met aanklikbare tabs, een automatisch
 * bewaard concept en — na inleveren — een verplichte reden bij wijzigingen. Losgekoppeld van de
 * startpagina, precies zoals beschikbaarheid_invullen.php. De veldnamen en de opslaglogica zijn
 * ongewijzigd overgenomen uit het oude inline formulier; alleen de presentatie is nieuw.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/blk.php';

$sol_id = portaal_vereis_login();

$stmt = db()->prepare("
    SELECT s.*, p.id AS persoon_id, p.voornaam, p.tussenvoegsel, p.achternaam, p.email, p.telefoon
    FROM sollicitaties s JOIN personen p ON p.id = s.persoon_id
    WHERE s.id = ?
");
$stmt->execute([$sol_id]);
$sol = $stmt->fetch();
if (!$sol) { unset($_SESSION['portaal_sol_id']); redirect(APP_URL . '/portaal/'); }

// Het formulier hoort bij de selectiedag-fase; daarbuiten terug naar de startpagina.
if (!in_array($sol['fase'] ?? '', blk_fases_zichtbaar(), true)) {
    flash('info', 'Het Beter Leren Kennen-formulier is op dit moment niet in te vullen.');
    redirect(APP_URL . '/portaal/');
}

$blk          = blk_van($sol_id);
$is_ingediend = blk_is_ingediend($sol, $blk);
$fout         = '';
$fout_stap    = 1;

// ── Opslaan ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['blk_submit'])) {
    csrf_check();
    $reden   = trim($_POST['reden_wijziging'] ?? '');
    // Een "edit" is alleen een WIJZIGING ná definitieve indiening — niet de eerste indiening
    // vanuit een autosave-concept (die maakt al een blk-rij aan).
    $is_edit = $is_ingediend;
    $labels  = blk_veld_labels();

    // Telefoonnummer (staat bij de persoon; het ontwerp vraagt het in stap 1)
    $tel_in  = trim($_POST['telefoon'] ?? '');
    $tel_oud = trim($sol['telefoon'] ?? '');
    $tel_ok  = preg_match_all('/\d/', $tel_in) >= 8;

    // Adresvelden: postcode normaliseren (1234 AB)
    $pc_raw = strtoupper(preg_replace('/\s+/', '', $_POST['postcode'] ?? ''));
    $pc     = preg_match('/^([0-9]{4})([A-Z]{2})$/', $pc_raw, $m) ? "{$m[1]} {$m[2]}" : $pc_raw;
    $straat_in = trim($_POST['straat'] ?? '');
    $hnr_in    = trim($_POST['huisnummer'] ?? '');
    $wp_in     = trim($_POST['woonplaats'] ?? '');
    // Samengesteld adres voor backwards compat
    $adres_samen = ($straat_in && $hnr_in) ? "$straat_in $hnr_in, $pc $wp_in" : '';

    // Beschikbaarheid zit niet meer in dit formulier (eigen taak); die kolommen laten we ongemoeid.
    $velden = [
        'postcode'            => $pc,
        'huisnummer'          => $hnr_in,
        'straat'              => $straat_in,
        'woonplaats'          => $wp_in,
        'adres'               => $adres_samen,
        'opleiding'           => trim($_POST['opleiding'] ?? ''),
        'werkervaring'        => trim($_POST['werkervaring'] ?? ''),
        'diplomas'            => trim($_POST['diplomas'] ?? ''),
        'motivatie_lifeguard' => trim($_POST['motivatie_lifeguard'] ?? ''),
        'motivatie_strand'    => trim($_POST['motivatie_strand'] ?? ''),
        'social_media'        => blk_social_samenstellen(blk_social_uit_post($_POST)),
        'bijzonderheden'      => trim($_POST['bijzonderheden'] ?? ''),
    ];

    // Filter velden zodat we niet falen als een kolom (nog) ontbreekt
    try {
        $kolommen = db()->query("SHOW COLUMNS FROM blk_formulieren")->fetchAll(PDO::FETCH_COLUMN);
    } catch (\Exception $e) { $kolommen = []; }
    $kolommen_set = array_flip($kolommen);
    $velden = array_intersect_key($velden, $kolommen_set);
    if (!isset($velden['postcode']) && isset($kolommen_set['adres']) && !isset($velden['adres'])) {
        $velden['adres'] = $adres_samen;
    }

    // Wat is er inhoudelijk gewijzigd (alleen relevant bij een edit)?
    $blk_changes = [];
    if ($is_edit) {
        foreach ($velden as $veld => $nieuw) {
            $oud = $blk[$veld] ?? '';
            if (trim($oud ?? '') !== trim($nieuw ?? '')) {
                $blk_changes[] = ['veld' => $veld, 'label' => $labels[$veld] ?? $veld, 'oud' => $oud, 'nieuw' => $nieuw];
            }
        }
        if ($tel_in !== '' && $tel_in !== $tel_oud) {
            $blk_changes[] = ['veld' => 'telefoon', 'label' => $labels['telefoon'], 'oud' => $tel_oud, 'nieuw' => $tel_in];
        }
    }

    if (!$tel_ok) {
        $fout = 'Vul een geldig telefoonnummer in (minstens 8 cijfers).'; $fout_stap = 1;
    } elseif (!$pc || !$hnr_in) {
        $fout = 'Vul je postcode en huisnummer in.'; $fout_stap = 1;
    } elseif (empty($velden['opleiding'])) {
        $fout = 'Vul je opleiding in.'; $fout_stap = 2;
    } elseif (empty($velden['motivatie_lifeguard'])) {
        $fout = 'Vul je motivatie in.'; $fout_stap = 3;
    } elseif ($is_edit && $blk_changes && !$reden) {
        $fout = 'Je hebt iets aangepast. Vul kort in waarom je het formulier aanpast.'; $fout_stap = 4;
    } else {
        // Foto: verwijderen, vervangen of behouden (includes/foto.php)
        $foto_oud = $blk['foto_url'] ?? null;
        $foto_url = $foto_oud;
        if (!empty($_POST['foto_verwijderen']) && $_POST['foto_verwijderen'] === '1') {
            $foto_url = null;
        }
        if (!empty($_FILES['foto']['tmp_name'])) {
            $foto_url = foto_opslaan((int)$sol_id, $_FILES['foto']) ?? $foto_url;
        }

        $nulls = array_map(fn($v) => $v ?: null, $velden);

        if ($is_edit && !empty($blk_changes)) {
            try {
                db()->prepare("INSERT INTO blk_wijzigingen (sollicitatie_id, reden, wijzigingen_json) VALUES(?,?,?)")
                   ->execute([$sol_id, $reden, json_encode($blk_changes)]);
            } catch (\Exception $e) { /* tabel bestaat nog niet — skip */ }
        }

        // INSERT ... ON DUPLICATE KEY UPDATE: werkt of er nu al een (concept)rij bestaat of niet
        $cols = implode(',', array_map(fn($k) => "`$k`", array_keys($velden)));
        $ph   = implode(',', array_fill(0, count($velden), '?'));
        $upd  = implode(',', array_map(fn($k) => "`$k`=VALUES(`$k`)", array_keys($velden)));
        db()->prepare("INSERT INTO blk_formulieren (sollicitatie_id,$cols,foto_url) VALUES(?,$ph,?)
                       ON DUPLICATE KEY UPDATE $upd, foto_url=VALUES(foto_url), bijgewerkt_op=NOW()")
           ->execute([$sol_id, ...array_values($nulls), $foto_url]);
        // Pas nu de nieuwe waarde vaststaat: de vervangen of verwijderde foto weg.
        if ($foto_oud && $foto_oud !== $foto_url) foto_verwijderen($foto_oud);

        // Telefoonnummer bij de persoon bijwerken
        if ($tel_in !== '' && $tel_in !== $tel_oud) {
            try {
                db()->prepare("UPDATE personen SET telefoon=? WHERE id=?")->execute([$tel_in, (int)$sol['persoon_id']]);
            } catch (\Exception $e) { error_log('blk telefoon bijwerken: ' . $e->getMessage()); }
        }

        if (!$is_edit) {
            // Inleveren = aanmelden voor de selectiedag: fase-wissel (met bevestiging + bestuursmelding)
            // via de centrale fase-functie; was de fase al verder, dan alleen een "taak afgerond"-bericht.
            $fase_res = ['ok' => false, 'ongewijzigd' => true];
            if ($sol['fase'] === 'uitgenodigd_selectie' && function_exists('fase_zetten')) {
                try { $fase_res = fase_zetten($sol_id, 'aangemeld_selectie', 'blk', ['alleen_van' => ['uitgenodigd_selectie'], 'prioriteit' => 'na_antwoord']); } catch (\Throwable $e) {}
            }
            if (!empty($fase_res['ongewijzigd']) && function_exists('melding_verstuur')) {
                try { melding_verstuur('taak_afgerond', $sol_id, ['taak_naam' => 'Beter leren kennen-formulier'], ['prioriteit' => 'na_antwoord']); } catch (\Throwable $e) {}
            }
        }
        // Definitief ingediend markeren (de betrouwbare "taak afgerond"-indicator, los van de fase)
        try { db()->prepare("UPDATE blk_formulieren SET definitief_op=NOW() WHERE sollicitatie_id=? AND definitief_op IS NULL")->execute([$sol_id]); } catch (\Exception $e) {}

        if (function_exists('audit_log')) {
            try { audit_log($is_edit ? 'blk_gewijzigd' : 'blk_ingediend', 'sollicitatie', $sol_id, ['via' => 'losse pagina']); } catch (\Exception $e) {}
        }
        // AI-analyse: toevoegen aan wachtrij (cron verwerkt dit), alleen als automatisch analyseren aan staat
        if (function_exists('ai_queue_toevoegen') && instelling('ai.api_key') && instelling('ai.auto_analyse', '1') === '1') {
            try { ai_queue_toevoegen($sol_id, 'blk_analyse'); } catch (\Exception $e) {}
        }

        /* Niet alleen "gelukt", maar ook wat er nu gebeurt. Zonder die zin eindigt de
           taak in stilte en weet je niet of er nog iets van je wordt verwacht. */
        $hierna = '';
        if (function_exists('portaal_hierna')) {
            $_sol_na  = portaal_sollicitant_laden($sol_id) ?: $sol;
            $_open_na = function_exists('portaal_taken')
                ? count(portaal_taken_open(portaal_taken($_sol_na, blk_van($sol_id))))
                : 0;
            $hierna = ' ' . portaal_hierna($_sol_na, $_open_na);
        }
        flash('succes', ($is_edit
            ? 'Je wijzigingen aan het Beter Leren Kennen-formulier zijn opgeslagen.'
            : 'Je Beter Leren Kennen-formulier is ingeleverd. Bedankt!') . $hierna);
        redirect(portaal_terug_url());
    }
}

// ── Weergave ──────────────────────────────────────────────────
// Bij een fout tonen we wat er is ingevuld; anders de opgeslagen (concept)waarden.
$na_fout = $fout !== '';
$w       = $na_fout ? $_POST : ($blk ?? []);
// Oudere rijen hebben soms alleen het samengestelde 'adres' (de losse kolommen ontbraken toen);
// leid postcode/huisnummer/straat/woonplaats daar dan uit af, anders blijft stap 1 leeg.
if (empty($w['postcode']) && empty($w['huisnummer']) && !empty($w['adres'])) {
    $w = array_merge($w, array_filter(blk_adres_splitsen($w['adres'])));
}
$social_w = $na_fout ? blk_social_uit_post($_POST) : blk_social_splitsen($blk['social_media'] ?? '');
$v        = fn(string $k): string => h((string)($w[$k] ?? ''));
$tel_w   = $na_fout ? ($_POST['telefoon'] ?? '') : ($sol['telefoon'] ?? '');
$adres_bekend = !empty($w['straat']) && !empty($w['woonplaats']);

$foto_bestaand = $blk['foto_url'] ?? null;
$foto_naam     = $foto_bestaand ? basename($foto_bestaand) : '';

// Concept-status in de kop
$concept_tekst = 'Je invoer wordt automatisch bewaard';
$concept_ok    = false;
if ($blk && !$is_ingediend && !empty($blk['bijgewerkt_op'])) {
    $bt = strtotime($blk['bijgewerkt_op']);
    $concept_tekst = 'Concept opgeslagen ' . (date('Y-m-d', $bt) === date('Y-m-d') ? 'om ' . date('H:i', $bt) : 'op ' . datum_nl(date('Y-m-d', $bt)));
    $concept_ok = true;
}
$ingeleverd_op = $blk['definitief_op'] ?? ($blk['ingevuld_op'] ?? null);

$stappen = [1 => 'Gegevens', 2 => 'Achtergrond', 3 => 'Motivatie', 4 => 'Overig'];
$start_stap = $na_fout ? $fout_stap : 1;

$portaal_titel = 'Beter Leren Kennen';
$portaal_terug = portaal_terug_url();
require __DIR__ . '/_kop.php';
?>

<form method="POST" enctype="multipart/form-data" id="blkForm" class="blk" novalidate>
  <?= csrf_input() ?>
  <input type="hidden" name="blk_submit" value="1">
  <input type="hidden" name="terug" value="<?= h(portaal_terug_param()) ?>">
  <input type="hidden" name="foto_verwijderen" id="fotoVerwijderen" value="0">

  <!-- Kop: titel + stap, tabs, concept-status -->
  <header class="blk-kop">
    <div class="blk-kop-rij">
      <div class="blk-kop-titel">
        <span class="blk-kop-naam">Beter Leren Kennen</span>
        <span class="blk-kop-stap"><span id="blkStapNr">Stap 1 van 4</span><span class="blk-kop-stapnaam" id="blkStapNaam"></span></span>
      </div>
    </div>
    <div class="blk-kop-rechts">
      <div class="blk-tabs" role="tablist">
        <?php foreach ($stappen as $nr => $naam): ?>
        <button type="button" class="blk-tab" data-stap="<?= $nr ?>" role="tab"><span class="blk-tab-nr"><?= $nr ?></span><span class="blk-tab-lbl"><?= h($naam) ?></span></button>
        <?php endforeach; ?>
      </div>
      <span class="blk-autosave <?= $concept_ok ? 'ok' : '' ?>" id="blkAutosave" aria-live="polite"><?= icoon('vink', 13) ?><span id="blkAutosaveTekst"><?= h($concept_tekst) ?></span></span>
    </div>
  </header>

  <div class="blk-body">
    <div class="blk-links">

      <?php if ($na_fout): ?>
      <div class="ds-melding ds-melding-error"><?= icoon('let-op', 18) ?> <div><?= h($fout) ?></div></div>
      <?php elseif ($is_ingediend): ?>
      <div class="blk-ingeleverd" style="margin-top:0">
        <?= icoon('vink-cirkel', 20) ?>
        <div>
          <div class="blk-ingeleverd-titel">Ingeleverd<?= $ingeleverd_op ? ' op ' . h(datum_nl(substr($ingeleverd_op, 0, 10))) : '' ?></div>
          <div class="blk-ingeleverd-sub">Je kunt het formulier nog bijwerken. We vragen dan om een korte reden.</div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Stap 1: Gegevens -->
      <section class="blk-stap" id="fs1">
        <div class="blk-sectie"><span class="blk-sectie-titel">Jouw gegevens</span><div class="blk-sectie-streep"></div></div>
        <div class="blk-kaart">
          <label class="blk-veld"><span class="blk-lbl">Naam</span>
            <input class="blk-input" value="<?= h(volledige_naam($sol)) ?>" disabled></label>
          <label class="blk-veld"><span class="blk-lbl">E-mailadres</span>
            <input class="blk-input" value="<?= h($sol['email']) ?>" disabled>
            <span class="blk-hint">Klopt je e-mailadres niet? Laat het ons weten via <a href="<?= APP_URL ?>/portaal/contact.php#vraagTekst">Contact</a>, dan past het bestuur het aan.</span></label>
          <label class="blk-veld"><span class="blk-lbl">Telefoonnummer <span class="ster">*</span></span>
            <input class="blk-input" type="tel" name="telefoon" value="<?= h($tel_w) ?>" autocomplete="tel" data-verplicht="1"></label>
        </div>
        <div class="blk-kaart">
          <div class="blk-rij">
            <label class="blk-veld"><span class="blk-lbl">Postcode <span class="ster">*</span></span>
              <input class="blk-input" type="text" name="postcode" id="adr-pc" value="<?= $v('postcode') ?>" placeholder="1234 AB" maxlength="7" autocomplete="postal-code" data-verplicht="1"></label>
            <label class="blk-veld"><span class="blk-lbl">Huisnr. <span class="ster">*</span></span>
              <input class="blk-input" type="text" name="huisnummer" id="adr-hn" value="<?= $v('huisnummer') ?>" placeholder="12a" data-verplicht="1"></label>
          </div>
          <div class="blk-adres" id="adrBox" <?= $adres_bekend ? '' : 'hidden' ?>>
            <?= icoon('locatie', 18) ?>
            <div class="blk-adres-tekst">
              <span class="blk-adres-regel" id="adrRegel"><?= $adres_bekend ? $v('straat') . ' ' . $v('huisnummer') . ', ' . $v('woonplaats') : '' ?></span>
              <span class="blk-adres-sub" id="adrSub"><?= $adres_bekend ? 'Straat en woonplaats zijn automatisch gevonden.' : '' ?></span>
            </div>
          </div>
          <div class="blk-rij" id="adrHandmatig" <?= $adres_bekend ? 'hidden' : '' ?>>
            <label class="blk-veld"><span class="blk-lbl">Straat</span>
              <input class="blk-input" type="text" name="straat" id="adr-st" value="<?= $v('straat') ?>" autocomplete="address-line1"></label>
            <label class="blk-veld"><span class="blk-lbl">Woonplaats</span>
              <input class="blk-input" type="text" name="woonplaats" id="adr-wp" value="<?= $v('woonplaats') ?>" autocomplete="address-level2"></label>
          </div>
        </div>
      </section>

      <!-- Stap 2: Achtergrond -->
      <section class="blk-stap" id="fs2">
        <div class="blk-sectie"><span class="blk-sectie-titel">Achtergrond</span><div class="blk-sectie-streep"></div></div>
        <div class="blk-kaart">
          <label class="blk-veld"><span class="blk-lbl">Opleiding <span class="ster">*</span></span>
            <textarea class="blk-textarea" name="opleiding" rows="2" placeholder="Bijv: HAVO 4, HBO Verpleegkunde jaar 3..." data-verplicht="2"><?= $v('opleiding') ?></textarea></label>
          <label class="blk-veld"><span class="blk-lbl">Diploma's en certificaten</span>
            <textarea class="blk-textarea" name="diplomas" rows="2" placeholder="Bijv: Zwemdiploma A+B, EHBO, Junior Lifeguard..."><?= $v('diplomas') ?></textarea></label>
          <div class="blk-chips">
            <button type="button" class="blk-chip" data-doel="diplomas" data-voeg="Zwemmend redden">+ Zwemmend redden</button>
            <button type="button" class="blk-chip" data-doel="diplomas" data-voeg="EHBO">+ EHBO</button>
            <button type="button" class="blk-chip" data-doel="diplomas" data-voeg="Vaarbewijs">+ Vaarbewijs</button>
          </div>
        </div>
        <div class="blk-kaart">
          <label class="blk-veld"><span class="blk-lbl">Werkervaring</span>
            <textarea class="blk-textarea" name="werkervaring" rows="3" placeholder="Bijv: bijbaan, vrijwilligerswerk..."><?= $v('werkervaring') ?></textarea>
            <span class="blk-hint">Ook bijbanen en vrijwilligerswerk zijn interessant.</span></label>
        </div>
      </section>

      <!-- Stap 3: Motivatie -->
      <section class="blk-stap" id="fs3">
        <div class="blk-sectie"><span class="blk-sectie-titel">Motivatie</span><div class="blk-sectie-streep"></div></div>
        <div class="blk-kaart">
          <label class="blk-veld"><span class="blk-lbl">Waarom wil je lifeguard worden? <span class="ster">*</span></span>
            <span class="blk-hint">Een paar zinnen is genoeg — we zijn vooral nieuwsgierig naar jouw reden.</span>
            <textarea class="blk-textarea" name="motivatie_lifeguard" rows="5" maxlength="1000" placeholder="Vertel ons wat je motiveert..." data-verplicht="3" data-teller="motTeller"><?= $v('motivatie_lifeguard') ?></textarea></label>
          <span class="blk-teller" id="motTeller"></span>
        </div>
        <div class="blk-kaart">
          <label class="blk-veld"><span class="blk-lbl">Wat doe je het liefst op het strand?</span>
            <span class="blk-hint">Eén of twee zinnen is genoeg.</span>
            <textarea class="blk-textarea" name="motivatie_strand" rows="3" placeholder="Bijv: zwemmen, volleybal, mensen helpen..."><?= $v('motivatie_strand') ?></textarea></label>
        </div>
      </section>

      <!-- Stap 4: Overig -->
      <section class="blk-stap" id="fs4">
        <div class="blk-sectie"><span class="blk-sectie-titel">Tot slot</span><div class="blk-sectie-streep"></div></div>
        <div class="blk-kaart">
          <div class="blk-veld"><span class="blk-lbl">Social media <span class="optioneel">(optioneel)</span></span>
            <span class="blk-hint">Vul alleen in wat je gebruikt; de rest mag leeg blijven.</span>
            <div class="blk-social">
              <?php foreach (blk_social_platforms() as $sleutel => $pf): ?>
              <label class="blk-social-rij"><span class="blk-social-lbl"><?= h($pf['naam']) ?></span>
                <input class="blk-input" type="text" name="social_<?= $sleutel ?>" value="<?= h($social_w[$sleutel] ?? '') ?>" placeholder="<?= h($pf['placeholder']) ?>" autocomplete="off"></label>
              <?php endforeach; ?>
            </div>
            <span class="blk-hint">Je gebruikersnaam is genoeg; een link plakken mag ook.</span></div>
          <div class="blk-veld" style="gap:9px">
            <span class="blk-lbl">Foto van jezelf</span>
            <div class="blk-foto-zone">
              <div class="blk-foto-avatar" id="fotoAvatar"><?php if ($foto_bestaand): ?><img src="<?= h(foto_url($foto_bestaand)) ?>" alt="Jouw foto"><?php else: ?><?= icoon('persoon', 44) ?><?php endif; ?></div>
              <div class="blk-foto-knoppen">
                <button type="button" class="blk-foto-maken" id="fotoMaken"><?= icoon('camera', 16) ?>Foto maken</button>
                <button type="button" class="blk-foto-kiezen" id="fotoKiezen">Kiezen</button>
              </div>
              <span class="blk-foto-hint">Recente foto waarop je goed te zien bent. Max 5 MB.</span>
              <input type="file" id="fotoInput" name="foto" accept="image/jpeg,image/png,image/webp">
            </div>
            <div class="blk-foto-rij" id="fotoRij" <?= $foto_bestaand ? '' : 'hidden' ?>>
              <div class="blk-foto-thumb" id="fotoThumb"><?php if ($foto_bestaand): ?><img src="<?= h(foto_url($foto_bestaand)) ?>" alt=""><?php else: ?><?= icoon('persoon', 22) ?><?php endif; ?></div>
              <div class="blk-foto-info">
                <span class="blk-foto-naam" id="fotoNaam"><?= h($foto_naam) ?></span>
                <span class="blk-foto-status" id="fotoStatus"><?= $foto_bestaand ? 'Geüpload · tik om te vergroten' : '' ?></span>
              </div>
              <div class="blk-foto-acties">
                <button type="button" class="blk-foto-wijzig" id="fotoWijzig">Wijzigen</button>
                <button type="button" class="blk-foto-verwijder" id="fotoVerwijder">Verwijderen</button>
              </div>
            </div>
          </div>
        </div>
        <div class="blk-kaart">
          <label class="blk-veld"><span class="blk-lbl">Bijzonderheden, medicatie of allergieën</span>
            <textarea class="blk-textarea" name="bijzonderheden" rows="3" placeholder="Iets wat we moeten weten?"><?= $v('bijzonderheden') ?></textarea>
            <span class="blk-hint">Laat dit veld leeg als er niets bijzonders is. Vul het alleen in als wij ergens rekening mee moeten houden (bijv. een allergie of medicatie). Alleen de mensen voor wie dit relevant is, zien dit.</span></label>
        </div>
        <?php if ($is_ingediend): ?>
        <div class="blk-reden">
          <span class="blk-reden-titel">Waarom pas je het formulier aan? <span class="ster">*</span></span>
          <textarea class="blk-textarea" name="reden_wijziging" rows="3" placeholder="Bijv. mijn telefoonnummer is gewijzigd"><?= h($na_fout ? ($_POST['reden_wijziging'] ?? '') : '') ?></textarea>
          <span class="blk-reden-hint">Alleen nodig bij het bijwerken van een al ingeleverd formulier. We houden bij wat je aanpast zodat er geen verwarring ontstaat.</span>
        </div>
        <?php endif; ?>
      </section>

    </div>

    <!-- Desktop: overzicht van de stappen + uitleg -->
    <aside class="blk-rechts" aria-hidden="true">
      <div class="blk-overzicht">
        <span class="blk-overzicht-lbl">Overzicht</span>
        <?php foreach ($stappen as $nr => $naam): ?>
        <div class="blk-ov-rij" data-stap="<?= $nr ?>">
          <span class="blk-ov-bol"></span><?= icoon('vink', 18) ?>
          <span class="blk-ov-naam"><?= $nr === 4 ? 'Overig &amp; foto' : h($naam) ?></span>
          <button type="button" class="blk-ov-wijzig" tabindex="-1">Wijzig</button>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="blk-info">
        <?= icoon('info', 20) ?>
        <span>Je concept wordt automatisch bewaard. Na inleveren kun je het formulier nog bijwerken — we vragen dan wel om een reden.</span>
      </div>
    </aside>
  </div>

  <!-- Voet -->
  <footer class="blk-voet">
    <button type="button" class="blk-knop-terug" id="blkTerug" hidden>Terug</button>
    <div class="blk-voet-rechts">
      <a class="blk-knop-later" href="<?= APP_URL ?>/portaal/">Later afmaken</a>
      <button type="button" class="blk-knop-volgende" id="blkVolgende"><span class="tekst">Volgende</span><?= icoon('pijl-rechts', 18) ?></button>
    </div>
  </footer>
</form>

<script>
  window.BLK_CFG = {
    apiAdres:   <?= json_encode(APP_URL . '/portaal/api_adres.php') ?>,
    apiConcept: <?= json_encode(APP_URL . '/portaal/api_blk_concept.php') ?>,
    stap:       <?= (int)$start_stap ?>,
    isEdit:     <?= $is_ingediend ? 'true' : 'false' ?>,
    totaal:     <?= count($stappen) ?>
  };
</script>
<script src="<?= APP_URL ?>/assets/js/portaal_concept.js?v=<?= @filemtime(__DIR__ . '/../assets/js/portaal_concept.js') ?>"></script>
<script src="<?= APP_URL ?>/assets/js/blk.js?v=<?= @filemtime(__DIR__ . '/../assets/js/blk.js') ?>"></script>

<?php require __DIR__ . '/_voet.php'; ?>
