<?php
/**
 * Paal 13 — Aanmelding verwerken (bron van waarheid).
 *
 * Eén functie die een nieuwe sollicitatie aanmaakt, gebruikt door zowel het interne
 * formulier (aanmelden.php) als de publieke API (api/aanmelden.php). Zo is er precies
 * één plek waar de aanmeldlogica leeft — het platform blijft de baas.
 *
 * Retourneert een array met 'ok' (bool) en bij fouten 'fout' (tekst), of bij succes
 * 'sollicitatie_id', 'portaal_token', 'terugkerend'.
 */

if (!function_exists('sollicitatie_aanmaken')) {

function sollicitatie_aanmaken(array $in, array $context = []): array {
    $db = db();
    $seizoen = actief_seizoen();
    if (!$seizoen || ($seizoen['status'] ?? '') !== 'open') {
        return ['ok' => false, 'fout' => 'Er is op dit moment geen open seizoen om je voor aan te melden.', 'code' => 'geen_open_seizoen'];
    }

    // Velden normaliseren
    $voornaam   = trim($in['voornaam'] ?? '');
    $tv         = trim($in['tussenvoegsel'] ?? '');
    $achternaam = trim($in['achternaam'] ?? '');
    $email      = strtolower(trim($in['email'] ?? ''));
    $tel        = trim($in['telefoon'] ?? '');
    $dob        = trim($in['geboortedatum'] ?? '');
    $geslacht   = $in['geslacht'] ?? 'onbekend';
    $akkoord    = !empty($in['akkoord']);
    $ouder_akkoord = !empty($in['ouder_akkoord']);
    $ouder_naam    = trim($in['ouder_toestemming_naam'] ?? '');

    $leeftijd = function_exists('leeftijd_uit_datum') ? leeftijd_uit_datum($dob) : null;

    // Validatie — exact zoals het interne formulier
    if (!$voornaam || !$achternaam || !$email || !$tel || !$dob) return ['ok'=>false,'fout'=>'Vul alle verplichte velden in.','code'=>'velden_incompleet'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok'=>false,'fout'=>'Ongeldig e-mailadres.','code'=>'email_ongeldig'];
    // Server-side, want het formulier kan ook zonder de browsercontrole binnenkomen (plugin, API).
    if (function_exists('telefoon_geldig') && !telefoon_geldig($tel)) {
        return ['ok'=>false,'fout'=>'Vul een geldig telefoonnummer in, bijvoorbeeld 06 12 34 56 78.','code'=>'telefoon_ongeldig'];
    }
    // Streng: alleen een echte kalenderdatum (JJJJ-MM-DD, zoals een datumveld stuurt), niet in de
    // toekomst en niet ouder dan 100. DateTime alleen accepteert ook "morgen" of 2099-01-01, en
    // diff()->y geeft dan een positieve leeftijd.
    $dob_dt = DateTime::createFromFormat('!Y-m-d', $dob);
    if (!$dob_dt || $dob_dt->format('Y-m-d') !== $dob || $dob_dt > new DateTime('today') || ($leeftijd ?? 0) > 100) $leeftijd = null;
    if ($leeftijd === null) return ['ok'=>false,'fout'=>'Vul een geldige geboortedatum in.','code'=>'dob_ongeldig'];
    if ($leeftijd < 15) return ['ok'=>false,'fout'=>'Je moet minimaal 15 jaar zijn om je aan te melden.','code'=>'te_jong'];
    if (!$akkoord) return ['ok'=>false,'fout'=>'Zet een vinkje om te laten weten dat je de privacyverklaring hebt gelezen.','code'=>'geen_akkoord'];
    if ($leeftijd < 16 && (!$ouder_akkoord || $ouder_naam === '')) {
        return ['ok'=>false,'fout'=>'Omdat je nog geen 16 bent, hebben we het akkoord en de naam van je ouder of verzorger nodig.','code'=>'ouder_nodig'];
    }

    // Persoon opzoeken of aanmaken. Een bestaand persoon wordt NIET overschreven: dit formulier is
    // anoniem, dus wie andermans e-mailadres intypt zou anders naam en telefoon (het kanaal van de
    // inlogcode!) kunnen vervangen. Lege velden vullen we aan; een afwijking ziet het bestuur.
    $bestaand = $db->prepare("SELECT id, voornaam, tussenvoegsel, achternaam, telefoon, geboortedatum, geslacht FROM personen WHERE email=?");
    $bestaand->execute([$email]);
    $p = $bestaand->fetch();
    $afwijkend = [];
    if ($p) {
        $pid = $p['id'];
        $al = $db->prepare("SELECT id FROM sollicitaties WHERE persoon_id=? AND seizoen_id=?");
        $al->execute([$pid, $seizoen['id']]);
        // Al aangemeld: zelfde antwoord als een nieuwe aanmelding, anders verraadt dit formulier welke
        // e-mailadressen bij ons bekend zijn. De echte eigenaar krijgt de bevestiging (met portaallink)
        // nog eens; melding_verstuur stuurt hem hooguit één keer per uur (dedup), dus geen spamkanaal.
        $al_rij = $al->fetch();
        if ($al_rij) {
            if (function_exists('melding_verstuur')) {
                try { melding_verstuur('aanmelding_bevestiging', (int)$al_rij['id'], ['portaal_url' => APP_URL . '/portaal/'], ['prioriteit' => 'na_antwoord']); }
                catch (\Throwable $e) { error_log('melding aanmelding_bevestiging (dubbel): ' . $e->getMessage()); }
            }
            if (function_exists('audit_log')) audit_log('aanmelding_dubbel', 'sollicitatie', (int)$al_rij['id'], ['via' => $context['via'] ?? 'formulier']);
            return ['ok'=>true, 'dubbel'=>true, 'sollicitatie_id'=>(int)$al_rij['id'], 'portaal_token'=>'', 'terugkerend'=>true, 'portaal_url'=>APP_URL . '/portaal/'];
        }

        $nieuw = ['voornaam' => $voornaam, 'tussenvoegsel' => $tv, 'achternaam' => $achternaam,
                  'telefoon' => $tel, 'geboortedatum' => $dob, 'geslacht' => $geslacht];
        $aanvullen = [];
        foreach ($nieuw as $k => $v) {
            $oud = trim((string)($p[$k] ?? ''));
            if ($v === '' || ($k === 'geslacht' && $v === 'onbekend')) continue;
            // Telefoon nooit vanuit dit formulier invullen, ook niet als hij leeg is: daar gaat de
            // inlogcode heen. Het bestuur ziet het nummer als afwijking en zet het zelf in het dossier.
            if ($k === 'telefoon' && $oud === '') { $afwijkend[] = $k; continue; }
            if ($oud === '' || ($k === 'geslacht' && $oud === 'onbekend')) $aanvullen[$k] = $v;
            elseif (mb_strtolower($oud) !== mb_strtolower($v)) $afwijkend[] = $k;
        }
        if ($aanvullen) {
            $sets = implode(',', array_map(fn($k) => "$k=?", array_keys($aanvullen)));
            $db->prepare("UPDATE personen SET $sets WHERE id=?")->execute([...array_values($aanvullen), $pid]);
        }
    } else {
        $db->prepare("INSERT INTO personen (voornaam,tussenvoegsel,achternaam,email,telefoon,geboortedatum,geslacht) VALUES(?,?,?,?,?,?,?)")
           ->execute([$voornaam, $tv ?: null, $achternaam, $email, $tel, $dob, $geslacht]);
        $pid = $db->lastInsertId();
    }

    // Terugkerend?
    $eerder = $db->prepare("SELECT COUNT(*) FROM sollicitaties WHERE persoon_id=?");
    $eerder->execute([$pid]);
    $ter = $eerder->fetchColumn() > 0 ? 1 : 0;

    $token = genereer_token();
    $ip = substr($context['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $ouder_op = ($leeftijd < 16) ? date('Y-m-d H:i:s') : null;
    $ouder_naam_val = ($leeftijd < 16) ? ($ouder_naam ?: null) : null;

    // Bron-attributie: waar kwam deze aanmelding vandaan? (landingspagina/advertentie)
    // "<bron>" of "<bron>/<campagne>" (includes/paginas/werving.php); alleen a-z, 0-9, - en één /.
    $bron_code  = isset($in['bron_code'])  ? substr(preg_replace('#[^a-z0-9\-/]#', '', strtolower(trim((string)$in['bron_code']))), 0, 80) : null;
    if ($bron_code !== null && substr_count($bron_code, '/') > 1) $bron_code = implode('/', array_slice(explode('/', $bron_code), 0, 2));
    if ($bron_code === '') $bron_code = null;
    $bron_label = isset($in['bron_label']) ? substr(trim((string)$in['bron_label']), 0, 150) : null;
    // UTM los (utm_source, utm_medium, utm_campaign, utm_content), geschoond; leeg = NULL.
    $utm = function_exists('pag_utm_uit') ? array_map(fn($v) => $v !== '' ? $v : null, pag_utm_uit($in)) : [];

    // De privacy- en bronkolommen legt db_failsafe() aan (consent_auto_setup en
    // db_verwachte_kolommen). Hier niet nog eens per aanmelding: dat waren drie
    // INFORMATION_SCHEMA-vragen bij elke verzending.
    // De UTM-kolommen alleen als er UTM is: kolomnamen uit pag_utm_velden() (vaste lijst, geen invoer).
    $utm = array_filter($utm);
    $kol = 'persoon_id,seizoen_id,terugkerend,portaal_token,privacy_akkoord_op,privacy_versie,privacy_ip,ouder_toestemming_op,ouder_toestemming_naam,bron_code,bron_label'
         . ($utm ? ',' . implode(',', array_keys($utm)) : '');
    $db->prepare("INSERT INTO sollicitaties ($kol) VALUES(" . implode(',', array_fill(0, 11 + count($utm), '?')) . ")")
       ->execute(array_merge([$pid, $seizoen['id'], $ter, $token, date('Y-m-d H:i:s'), PRIVACY_VERSIE, $ip, $ouder_op, $ouder_naam_val, $bron_code, $bron_label], array_values($utm)));
    $sid = (int)$db->lastInsertId();

    // Tel de afronding mee voor de landingspagina-statistiek (anoniem).
    if (function_exists('pag_track') && $bron_code) {
        $lp = function_exists('pag_via_bron') ? pag_via_bron($bron_code) : null;
        pag_track('formulier_klaar', isset($lp['id']) ? (int)$lp['id'] : null, $bron_code, null, $utm);
    }

    // Bevestiging aan de sollicitant + melding aan het bestuur, via de centrale meldingen-kern
    // (kanaalkeuze, terugval en logboek zitten daar). Verzending ná het antwoord: snel formulier.
    // Het portaal logt in met een inloglink of code (portaal_login.php); een token in deze URL
    // deed niets en is daarom weg.
    $purl = APP_URL . '/portaal/';
    $afwijk_labels = ['voornaam' => 'voornaam', 'tussenvoegsel' => 'tussenvoegsel', 'achternaam' => 'achternaam',
                      'telefoon' => 'telefoonnummer', 'geboortedatum' => 'geboortedatum', 'geslacht' => 'geslacht'];
    $afwijk_html = $afwijkend
        ? '<p><em>Let op: bij deze aanmelding wijkt dit af van het bestaande dossier: ' . h(implode(', ', array_map(fn($k) => $afwijk_labels[$k], $afwijkend)))
          . (in_array('telefoon', $afwijkend, true) ? ' (opgegeven telefoonnummer: ' . h($tel) . ')' : '')
          . '. We hebben het dossier niet aangepast; controleer het, bijvoorbeeld door te bellen, en corrigeer het zo nodig in het dossier.</em></p>'
        : '';
    if (function_exists('melding_verstuur')) {
        try { melding_verstuur('aanmelding_bevestiging', $sid, ['portaal_url' => $purl], ['prioriteit' => 'na_antwoord']); }
        catch (\Throwable $e) { error_log('melding aanmelding_bevestiging: ' . $e->getMessage()); }
        try {
            melding_verstuur('bestuur_nieuwe_aanmelding', $sid,
                ['terugkerend_html' => ($ter ? '<p><em>Terugkerende sollicitant: heeft eerder gesolliciteerd.</em></p>' : '') . $afwijk_html],
                ['prioriteit' => 'na_antwoord']);
        } catch (\Throwable $e) { error_log('melding bestuur_nieuwe_aanmelding: ' . $e->getMessage()); }
    }

    // Competenties uit Authentik (best-effort), ná het antwoord: het is een verzoek naar een
    // andere server (tot 12 seconden) en niets op het scherm hangt ervan af. Kan de server niet
    // eerst antwoorden (geen FPM), dan niet hier: de bezoeker zou erop wachten. De dagelijkse
    // cron (competenties_cron_sync) haalt het dan in.
    if (na_antwoord_mogelijk() && function_exists('competenties_sync') && function_exists('competentie_sync_klaar') && competentie_sync_klaar()) {
        na_antwoord(fn() => competenties_sync($sid, $email), 'sync bij aanmelding');
    }

    // Audit
    if (function_exists('audit_log')) {
        audit_log('aangemaakt', 'sollicitatie', $sid, ['via' => $context['via'] ?? 'formulier', 'terugkerend' => (bool)$ter]
                  + ($afwijkend ? ['afwijkend' => $afwijkend] : []));
    }

    return ['ok'=>true, 'sollicitatie_id'=>$sid, 'portaal_token'=>$token, 'terugkerend'=>(bool)$ter, 'portaal_url'=>$purl];
}

}
