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
    if ($leeftijd === null) return ['ok'=>false,'fout'=>'Vul een geldige geboortedatum in.','code'=>'dob_ongeldig'];
    if ($leeftijd < 15) return ['ok'=>false,'fout'=>'Je moet minimaal 15 jaar zijn om je aan te melden.','code'=>'te_jong'];
    if (!$akkoord) return ['ok'=>false,'fout'=>'Ga akkoord met de privacyverklaring.','code'=>'geen_akkoord'];
    if ($leeftijd < 16 && (!$ouder_akkoord || $ouder_naam === '')) {
        return ['ok'=>false,'fout'=>'Omdat je nog geen 16 bent, hebben we de toestemming en naam van je ouder/verzorger nodig.','code'=>'ouder_nodig'];
    }

    // Persoon opzoeken of aanmaken
    $bestaand = $db->prepare("SELECT id FROM personen WHERE email=?");
    $bestaand->execute([$email]);
    $p = $bestaand->fetch();
    if ($p) {
        $pid = $p['id'];
        $db->prepare("UPDATE personen SET voornaam=?,tussenvoegsel=?,achternaam=?,telefoon=?,geboortedatum=?,geslacht=? WHERE id=?")
           ->execute([$voornaam, $tv ?: null, $achternaam, $tel, $dob, $geslacht, $pid]);
    } else {
        $db->prepare("INSERT INTO personen (voornaam,tussenvoegsel,achternaam,email,telefoon,geboortedatum,geslacht) VALUES(?,?,?,?,?,?,?)")
           ->execute([$voornaam, $tv ?: null, $achternaam, $email, $tel, $dob, $geslacht]);
        $pid = $db->lastInsertId();
    }

    // Al aangemeld voor dit seizoen?
    $al = $db->prepare("SELECT id FROM sollicitaties WHERE persoon_id=? AND seizoen_id=?");
    $al->execute([$pid, $seizoen['id']]);
    if ($al->fetch()) return ['ok'=>false,'fout'=>'Je hebt je al aangemeld voor dit seizoen. Check je e-mail.','code'=>'al_aangemeld'];

    // Terugkerend?
    $eerder = $db->prepare("SELECT COUNT(*) FROM sollicitaties WHERE persoon_id=?");
    $eerder->execute([$pid]);
    $ter = $eerder->fetchColumn() > 0 ? 1 : 0;

    $token = genereer_token();
    if (function_exists('consent_auto_setup')) consent_auto_setup();
    $ip = substr($context['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $ouder_op = ($leeftijd < 16) ? date('Y-m-d H:i:s') : null;
    $ouder_naam_val = ($leeftijd < 16) ? ($ouder_naam ?: null) : null;

    // Bron-attributie: waar kwam deze aanmelding vandaan? (landingspagina/advertentie)
    $bron_code  = isset($in['bron_code'])  ? substr(trim((string)$in['bron_code']), 0, 80)   : null;
    $bron_label = isset($in['bron_label']) ? substr(trim((string)$in['bron_label']), 0, 150) : null;

    // Zorg dat de bron-kolommen bestaan (failsafe-stijl), dan pas invoegen.
    if (function_exists('db_kolom_verzeker')) {
        db_kolom_verzeker($db, 'sollicitaties', 'bron_code', 'VARCHAR(80) DEFAULT NULL');
        db_kolom_verzeker($db, 'sollicitaties', 'bron_label', 'VARCHAR(150) DEFAULT NULL');
    }

    $db->prepare("INSERT INTO sollicitaties (persoon_id,seizoen_id,terugkerend,portaal_token,privacy_akkoord_op,privacy_versie,privacy_ip,ouder_toestemming_op,ouder_toestemming_naam,bron_code,bron_label)
                  VALUES(?,?,?,?,?,?,?,?,?,?,?)")
       ->execute([$pid, $seizoen['id'], $ter, $token, date('Y-m-d H:i:s'), PRIVACY_VERSIE, $ip, $ouder_op, $ouder_naam_val, $bron_code, $bron_label]);
    $sid = (int)$db->lastInsertId();

    // Tel de afronding mee voor de landingspagina-statistiek (anoniem).
    if (function_exists('pag_track') && $bron_code) {
        $lp = function_exists('pag_via_bron') ? pag_via_bron($bron_code) : null;
        pag_track('formulier_klaar', isset($lp['id']) ? (int)$lp['id'] : null, $bron_code);
    }

    // Bevestiging aan de sollicitant + melding aan het bestuur, via de centrale meldingen-kern
    // (kanaalkeuze, terugval en logboek zitten daar). Verzending ná het antwoord: snel formulier.
    $purl = APP_URL . '/portaal/?token=' . $token;
    if (function_exists('melding_verstuur')) {
        try { melding_verstuur('aanmelding_bevestiging', $sid, ['portaal_url' => $purl], ['prioriteit' => 'na_antwoord']); }
        catch (\Throwable $e) { error_log('melding aanmelding_bevestiging: ' . $e->getMessage()); }
        try {
            melding_verstuur('bestuur_nieuwe_aanmelding', $sid,
                ['terugkerend_html' => $ter ? '<p><em>Terugkerende sollicitant: heeft eerder gesolliciteerd.</em></p>' : ''],
                ['prioriteit' => 'na_antwoord']);
        } catch (\Throwable $e) { error_log('melding bestuur_nieuwe_aanmelding: ' . $e->getMessage()); }
    }

    // Competenties uit Authentik (best-effort)
    if (function_exists('competenties_sync') && function_exists('competentie_sync_klaar') && competentie_sync_klaar()) {
        try { competenties_sync($sid, $email); } catch (\Exception $e) { error_log('sync bij aanmelding: ' . $e->getMessage()); }
    }

    // Audit
    if (function_exists('audit_log')) {
        audit_log('aangemaakt', 'sollicitatie', $sid, ['via' => $context['via'] ?? 'formulier', 'terugkerend' => (bool)$ter]);
    }

    return ['ok'=>true, 'sollicitatie_id'=>$sid, 'portaal_token'=>$token, 'terugkerend'=>(bool)$ter, 'portaal_url'=>$purl];
}

}
