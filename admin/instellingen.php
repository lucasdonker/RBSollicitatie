<?php
require_once __DIR__ . '/../includes/functions.php';
if (file_exists(__DIR__ . '/../includes/ai_analyse.php')) {
    require_once __DIR__ . '/../includes/ai_analyse.php';
}

$pagina_titel = 'Instellingen';
$actieve_nav  = 'instellingen';
vereis_recht('instellingen', 'lezen');
// De beheerlaag (bh_seizoen_fases, bh_badge) is nodig vóór layout_top.php, al bij het opslaan.
require_once __DIR__ . '/../includes/beheer/laden.php';

// Geheimen die je moet kunnen kopiëren (API-sleutel, cron-token) en de links naar
// Systeem & updates zijn er alleen voor wie Systeem mag bewerken.
$mag_systeem = mag('systeem', 'bewerken');
$mag_sjablonen = mag('mailing', 'bewerken');   // Sjablonen (mail_templates.php) eist bewerkrecht

$db = db();
// Triggers auto-setup van alle tabellen
instellingen_laden();

$tab = (string)($_GET['tab'] ?? 'algemeen');
// Oude tabbladnamen blijven werken: er liggen links naar in mails, in de handleiding
// en in bladwijzers. 'sso' is opgegaan in 'authentik'.
$tab = ['sso' => 'authentik'][$tab] ?? $tab;
// Technische tabbladen (bh_inst_tab_technisch) alleen met Systeem › bewerken: tonen én opslaan.
if (bh_inst_tab_technisch($tab) && !$mag_systeem) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') { http_response_code(403); exit('Dit is een technische instelling: alleen voor wie het systeem beheert.'); }
    $tab = 'algemeen';
}

// ── Opslaan ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    // Opslaan is bewerken. Met alleen leesrecht kon eerder alles gewijzigd worden.
    vereis_recht('instellingen', 'bewerken');

    $actie = $_POST['actie'] ?? '';
    // Technische acties vragen Systeem › bewerken, los van het tabblad in de URL: de check
    // hierboven kijkt alleen naar ?tab=, en een POST kan elke actie meesturen.
    $technische_acties = ['authentik_test', 'authentik_bulk_sync', 'cron_token_vernieuw', 'modules_opslaan',
                          'cron_nu', 'api_sleutel_genereer', 'api_opslaan', 'smtp_opslaan', 'smtp_test'];
    if (in_array($actie, $technische_acties, true)) vereis_recht('systeem', 'bewerken');

    if ($actie === 'instellingen') {
        // Alleen sleutels die als veld op dít tabblad staan (whitelist in includes/instellingen.php).
        // Geheimen volgen daarna apart: leeg = ongewijzigd.
        // flash() bewaart één melding: verzamel waarschuwingen, anders overschrijft "opgeslagen" ze.
        $inst_waarschuwing   = [];
        $toegestaan_sleutels = instellingen_formulier_sleutels($tab);
        $geheim_sleutels     = instellingen_geheime_sleutels();
        $wis_geheim          = array_map('strval', (array)($_POST['geheim_wis'] ?? []));
        $velden   = [];
        $geheimen = [];
        $geweigerd = [];
        foreach ((array)($_POST['inst'] ?? []) as $sleutel => $waarde) {
            $sleutel = (string)$sleutel;
            if (!in_array($sleutel, $toegestaan_sleutels, true) || !is_string($waarde)) { $geweigerd[] = mb_substr($sleutel, 0, 60); continue; }
            if (in_array($sleutel, $geheim_sleutels, true)) { $geheimen[$sleutel] = $waarde; continue; }
            $velden[$sleutel] = $waarde;
        }
        if ($geweigerd && function_exists('audit_log')) {
            audit_log('geweigerd', 'instelling', null, ['tab' => $tab, 'reden' => 'sleutel niet op dit tabblad', 'sleutels' => array_slice($geweigerd, 0, 20)]);
        }
        // Handmatige seizoensfase: leeg (automatisch) of een van de bekende fases.
        if (isset($velden['seizoen.fase']) && $velden['seizoen.fase'] !== '' && !isset(bh_seizoen_fases()[$velden['seizoen.fase']])) {
            $velden['seizoen.fase'] = '';
        }
        // Verificatiecode bij ondertekenen: vinkjes → komma-lijst. Het loonheffingsformulier houdt hem altijd
        // (wettelijk document met BSN; besluit 2.91.0) — ook als iemand het vinkje uit de POST haalt.
        if ($tab === 'modules' && isset($_POST['tfa_formulier'])) {
            $tfa = array_values(array_intersect(array_keys(instellingen_2fa_keuzes()), array_map('strval', (array)($_POST['tfa_typen'] ?? []))));
            $velden['ondertekenen.2fa_typen'] = implode(',', array_unique(array_merge(['loonheffing'], $tfa)));
        }
        // Privacyverklaring: leeg, een pad op dit domein ("/…") of een https-adres. Niets anders
        // (geen javascript:, geen "//ander-domein"): de link staat op het aanmeldformulier.
        if (isset($velden['privacy.url'])) {
            $pu = trim((string)$velden['privacy.url']);
            $pu_ok = $pu === '' || (str_starts_with($pu, '/') && !str_starts_with($pu, '//'))
                  || (str_starts_with(strtolower($pu), 'https://') && filter_var($pu, FILTER_VALIDATE_URL));
            if (!$pu_ok) { unset($velden['privacy.url']); $inst_waarschuwing[] = 'Het adres van de privacyverklaring is niet opgeslagen: gebruik een https-adres of een pad dat met / begint.'; }
        }
        // Loondocumenten: nooit korter dan de wettelijke vijf jaar (documenten_loon_bewaarjaren doet hetzelfde).
        if (isset($velden['bewaar.loondocumenten_jaar'])) {
            $velden['bewaar.loondocumenten_jaar'] = (string)max(5, min(10, (int)$velden['bewaar.loondocumenten_jaar']));
        }

        // Logo en favicon uploaden (overschrijft de URL als er een bestand is).
        // Geen SVG: een SVG kan script bevatten en wordt van dit domein geserveerd (opgeslagen XSS).
        $upload_types = [
            'logo'    => ['png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'webp' => ['image/webp']],
            'favicon' => ['png' => ['image/png'], 'ico' => ['image/vnd.microsoft.icon', 'image/x-icon']],
        ];
        foreach (['logo'=>'site.logo_url', 'favicon'=>'site.favicon_url'] as $veld => $instelling_key) {
            $bron = $_FILES[$veld.'_upload'] ?? null;
            if (!$bron || empty($bron['tmp_name']) || (int)$bron['error'] !== 0) continue;
            $naam_veld = $veld === 'logo' ? 'Logo' : 'Favicon';
            $ext = strtolower(pathinfo((string)$bron['name'], PATHINFO_EXTENSION));
            $mime = function_exists('finfo_open') ? (string)finfo_file(finfo_open(FILEINFO_MIME_TYPE), $bron['tmp_name']) : '';
            if ($bron['size'] >= 2*1024*1024) {
                $inst_waarschuwing[] = $naam_veld . ' niet opgeslagen: het bestand is groter dan 2 MB.';
                continue;
            }
            if (!isset($upload_types[$veld][$ext]) || ($mime !== '' && !in_array($mime, $upload_types[$veld][$ext], true))) {
                $inst_waarschuwing[] = $naam_veld . ' niet opgeslagen: alleen ' . ($veld === 'logo' ? 'PNG, JPG of WebP' : 'PNG of ICO') . ' is toegestaan. SVG kan script bevatten en weigeren we daarom.';
                continue;
            }
            $dir = __DIR__ . '/../uploads/branding/';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $fn = $veld . '_' . time() . '.' . $ext;
            if (move_uploaded_file($bron['tmp_name'], $dir . $fn)) {
                $velden[$instelling_key] = '/uploads/branding/' . $fn;
            }
        }

        // Huisregels-PDF: in de bijlagenbibliotheek zetten en als taak-document koppelen (of losmaken)
        if (!empty($_FILES['huisregels_upload']['tmp_name']) && function_exists('bijlage_opslaan')) {
            $hu = bijlage_opslaan($_FILES['huisregels_upload'], (int)($_bhd['id'] ?? 0), 'Huisregels (taak aangenomen)');
            if ($hu['ok']) {
                $velden['taken.huisregels_bijlage_id'] = (string)$hu['id'];
                if (function_exists('audit_log')) audit_log('gewijzigd', 'instelling', null, ['veld' => 'taken.huisregels_bijlage_id', 'bijlage' => $hu['id']]);
            } else {
                $inst_waarschuwing[] = 'Huisregels niet opgeslagen: ' . $hu['fout'];
            }
        }
        if ($tab === 'algemeen' && !empty($_POST['huisregels_los'])) $velden['taken.huisregels_bijlage_id'] = '0';

        $gewijzigd = [];
        foreach ($velden as $sleutel => $waarde) {
            $waarde = trim((string)$waarde);
            if (instelling($sleutel, "\0") === $waarde) continue;
            if (instelling_opslaan($sleutel, $waarde)) $gewijzigd[] = $sleutel;
        }
        foreach ($geheim_sleutels as $sleutel) {
            if (!in_array($sleutel, $toegestaan_sleutels, true)) continue;
            if (instelling_geheim_bijwerken($sleutel, (string)($geheimen[$sleutel] ?? ''), in_array($sleutel, $wis_geheim, true))) $gewijzigd[] = $sleutel;
        }
        // Alleen wélke sleutels veranderden, nooit de waarden (daar zitten geheimen tussen).
        if ($gewijzigd && function_exists('audit_log')) audit_log('gewijzigd', 'instelling', null, ['tab' => $tab, 'velden' => $gewijzigd]);
        if ($inst_waarschuwing) flash('waarschuwing', 'De rest is opgeslagen. ' . implode(' ', $inst_waarschuwing));
        else flash('succes', 'Instellingen opgeslagen.');
        redirect(APP_URL . '/admin/instellingen.php?tab=' . $tab);
    }

    if ($actie === 'authentik_test') {
        $res = authentik_test();
        flash($res['ok'] ? 'succes' : 'fout', $res['melding']);
        redirect(APP_URL . '/admin/instellingen.php?tab=authentik');
    }

    if ($actie === 'cron_token_vernieuw') {
        // Technisch (zie $technische_acties): wie het token niet mag zien, kan het ook niet vervangen.
        instelling_opslaan('cron.token', bin2hex(random_bytes(24)));
        if (function_exists('audit_log')) audit_log('gewijzigd', 'instelling', null, ['veld' => 'cron.token', 'actie' => 'nieuw token gemaakt']);
        flash('succes', 'Nieuw cron-token gemaakt. Zet de nieuwe regel in de geplande taak in Plesk; de oude werkt niet meer.');
        redirect(APP_URL . '/admin/instellingen.php?tab=bewaren');
    }

    if ($actie === 'modules_opslaan') {
        // Een module uitzetten haalt voor iedereen schermen weg: dat is Systeem (zie $technische_acties).
        $gewijzigd = [];
        foreach (modules_alle() as $sleutel => $m) {
            $aan = !empty($_POST['module'][$sleutel]) ? '1' : '0';
            if (instelling('module.' . $sleutel . '.aan', '1') === $aan) continue;
            instelling_opslaan('module.' . $sleutel . '.aan', $aan);
            $gewijzigd[$sleutel] = $aan === '1' ? 'aan' : 'uit';
        }
        if ($gewijzigd && function_exists('audit_log')) audit_log('gewijzigd', 'instelling', null, ['modules' => $gewijzigd]);
        flash('succes', $gewijzigd ? 'Modules bijgewerkt.' : 'Er is niets veranderd.');
        redirect(APP_URL . '/admin/instellingen.php?tab=modules');
    }

    if ($actie === 'cron_nu') {
        @set_time_limit(300);
        $res = cron_taken_uitvoeren('alles');
        $fouten = [];
        foreach ($res['groepen'] ?? [] as $g => $taken) {
            if (isset($taken['overgeslagen'])) { $fouten[] = "$g: " . $taken['overgeslagen']; continue; }
            foreach ($taken as $naam => $t) if (is_array($t) && isset($t['fout'])) $fouten[] = "$g/$naam: " . $t['fout'];
        }
        if (function_exists('audit_log')) audit_log('cron_handmatig', 'systeem', null, ['fouten' => count($fouten)]);
        flash($fouten ? 'fout' : 'succes', $fouten ? 'Uitgevoerd, met problemen: ' . implode('; ', $fouten) : 'Alle automatische taken zijn uitgevoerd.');
        redirect(APP_URL . '/admin/instellingen.php?tab=bewaren');
    }

    if ($actie === 'api_sleutel_genereer') {
        // Technisch (zie $technische_acties): een sleutel die je niet kunt kopiëren, kun je ook niet in de plugin zetten.
        // Nieuwe geheime sleutel genereren (32 bytes, hex).
        $nieuw = bin2hex(random_bytes(32));
        instelling_opslaan('api.sleutel', $nieuw);
        if (function_exists('audit_log')) audit_log('gewijzigd', 'instelling', null, ['veld' => 'api.sleutel', 'actie' => 'nieuwe sleutel gegenereerd']);
        flash('succes', 'Nieuwe API-sleutel gegenereerd. Vergeet niet deze in de WordPress-plugin te zetten.');
        redirect(APP_URL . '/admin/instellingen.php?tab=api');
    }
    if ($actie === 'api_opslaan') {
        instelling_opslaan('api.origins', trim($_POST['api_origins'] ?? ''));
        flash('succes', 'API-instellingen opgeslagen.');
        redirect(APP_URL . '/admin/instellingen.php?tab=api');
    }

    if ($actie === 'berichten_opslaan') {
        // De koppelingen (SMS-gateway, WhatsReach, sleutels) zijn technisch; de keuze per melding niet.
        // Zonder Systeem › bewerken staan die velden niet op het scherm en worden ze hier ook niet gelezen.
        if ($mag_systeem) {
        // SMS
        instelling_opslaan('sms.actief', !empty($_POST['sms_actief']) ? '1' : '0');
        instelling_opslaan('sms.gateway', $_POST['sms_gateway'] ?? '');
        instelling_opslaan('sms.afzender', trim($_POST['sms_afzender'] ?? 'Paal13'));
        instelling_opslaan('sms.http_url', trim($_POST['sms_http_url'] ?? ''));
        instelling_opslaan('sms.http_method', $_POST['sms_http_method'] ?? 'POST');
        // Geheimen: leeg = ongewijzigd, "verwijderen" aangevinkt = leegmaken.
        $wis_geheim = array_map('strval', (array)($_POST['geheim_wis'] ?? []));
        foreach (['sms_http_token' => 'sms.http_token', 'sms_easysendsms_key' => 'sms.easysendsms_key',
                  'sms_messagebird_key' => 'sms.messagebird_key', 'wa_api_sleutel' => 'wa.api_sleutel'] as $post_naam => $sleutel) {
            instelling_geheim_bijwerken($sleutel, (string)($_POST[$post_naam] ?? ''), in_array($sleutel, $wis_geheim, true));
        }
        // WhatsApp (WhatsReach)
        instelling_opslaan('wa.actief', !empty($_POST['wa_actief']) ? '1' : '0');
        instelling_opslaan('wa.base_url', trim($_POST['wa_base_url'] ?? '') ?: 'https://api.whatsreach.co/api/v1');
        instelling_opslaan('wa.afzender_nummer', trim($_POST['wa_afzender_nummer'] ?? ''));
        }
        // Per-melding kanaalmodus (email | kort | beide | uit)
        if (function_exists('meldingen_register')) {
            $modi_ok = array_keys(meldingen_kanaalmodi());
            foreach (meldingen_register() as $type => $mdef) {
                $gekozen = $_POST['notif'][$type] ?? '';
                if (!is_string($gekozen) || !in_array($gekozen, $modi_ok, true)) continue;
                if (!$mdef['kort_toegestaan'] && $gekozen !== 'uit') $gekozen = 'email';
                instelling_opslaan('notif.' . $type, $gekozen);
            }
        }
        flash('succes', 'Bericht-instellingen opgeslagen.');
        redirect(APP_URL . '/admin/instellingen.php?tab=berichten');
    }

    if ($actie === 'bericht_test') {
        // Testbericht door de hele meldingen-keten (kanaalplan, terugval, log) sturen.
        $naar_tel  = trim($_POST['test_nummer'] ?? '');
        $naar_mail = trim($_POST['test_email'] ?? ($_SESSION['bhd_email'] ?? ''));
        $via       = $_POST['test_kanaal'] ?? 'sms';
        $voorkeur  = $via === 'wa' ? 'whatsapp' : ($via === 'sms' ? 'sms' : 'email');
        $modus     = $via === 'email' ? 'email' : 'kort';
        $res = ['status' => 'mislukt', 'reden' => 'meldingen-kern ontbreekt', 'kanaal' => null];
        if (function_exists('melding_verstuur')) {
            $res = melding_verstuur('handmatig', 0, [
                'onderwerp'  => 'Testbericht Paal 13',
                'body_html'  => '<p>Dit is een testbericht van het Paal 13 sollicitatiesysteem. Als je dit ontvangt, werkt het gekozen kanaal.</p>',
                'tekst_kort' => 'Testbericht van het Paal 13 sollicitatiesysteem. Als je dit leest, werkt het kanaal.',
            ], [
                'ontvanger'   => ['email' => $naar_mail, 'naam' => 'Test', 'telefoon' => $naar_tel, 'rol' => 'direct', 'kanaal_voorkeur' => $voorkeur],
                'kanaalmodus' => $modus, 'prioriteit' => 'direct', 'negeer_plafond' => true,
            ]);
        }
        $ok = ($res['status'] === 'verstuurd');
        $melding = $ok
            ? 'Testbericht verstuurd via ' . ($res['kanaal'] ?? $via) . ($res['kanaal'] && $res['kanaal'] !== $voorkeur ? ' (terugval; het gekozen kanaal mislukte, zie het berichtenlog)' : '') . '.'
            : 'Testbericht mislukt: ' . ($res['reden'] ?? 'onbekend') . ' — controleer de instellingen en het berichtenlog.';
        flash($ok ? 'succes' : 'fout', $melding);
        redirect(APP_URL . '/admin/instellingen.php?tab=berichten');
    }

    if ($actie === 'smtp_opslaan') {
        $velden = [
            'smtp.host'     => trim($_POST['smtp_host'] ?? ''),
            'smtp.port'     => trim($_POST['smtp_port'] ?? '587'),
            'smtp.user'     => trim($_POST['smtp_user'] ?? ''),
            'smtp.encrypt'  => trim($_POST['smtp_encrypt'] ?? 'tls'),
            'smtp.from'     => trim($_POST['smtp_from'] ?? ''),
            'smtp.from_naam'=> trim($_POST['smtp_from_naam'] ?? ''),
        ];
        foreach ($velden as $sleutel => $waarde) {
            instelling_opslaan($sleutel, $waarde);
        }
        // Wachtwoord alleen bijwerken als er een nieuwe is ingevuld (anders behouden).
        // Niet trimmen: een wachtwoord mag met een spatie beginnen of eindigen.
        $nieuw_ww = (string)($_POST['smtp_pass'] ?? '');
        if ($nieuw_ww !== '') {
            instelling_opslaan('smtp.pass', $nieuw_ww);
        } elseif (in_array('smtp.pass', array_map('strval', (array)($_POST['geheim_wis'] ?? [])), true)) {
            instelling_geheim_bijwerken('smtp.pass', '', true);
        }
        flash('succes', 'SMTP-instellingen opgeslagen.');
        redirect(APP_URL . '/admin/instellingen.php?tab=smtp');
    }

    if ($actie === 'smtp_test') {
        $naar = trim($_POST['smtp_test_naar'] ?? '');
        if (!filter_var($naar, FILTER_VALIDATE_EMAIL)) {
            flash('fout', 'Vul een geldig e-mailadres in voor de test.');
        } else {
            $res = smtp_test($naar);
            flash($res['ok'] ? 'succes' : 'fout', $res['melding']);
        }
        redirect(APP_URL . '/admin/instellingen.php?tab=smtp');
    }

    if ($actie === 'authentik_bulk_sync') {
        instelling_opslaan('authentik.laatste_bulk_sync', ''); // reset gate → forceer
        $res = competenties_cron_sync();
        flash('succes', 'Sync uitgevoerd: ' . ($res['aantal'] ?? 0) . ' sollicitanten ververst.');
        redirect(APP_URL . '/admin/instellingen.php?tab=authentik');
    }

    if ($actie === 'functie_opslaan') {
        $fid    = (int)($_POST['functie_id'] ?? 0);
        $naam   = trim($_POST['naam'] ?? '');
        $factor = (float)str_replace(',', '.', $_POST['factor'] ?? '1');
        $min_l  = (int)($_POST['min_leeftijd'] ?? 0) ?: null;
        $max_l  = (int)($_POST['max_leeftijd'] ?? 99);
        $volgorde = (int)($_POST['volgorde'] ?? 0);
        if ($fid && $naam) {
            $db->prepare("UPDATE functies SET naam=?,factor=?,min_leeftijd=?,max_leeftijd=?,volgorde=? WHERE id=?")
               ->execute([$naam, $factor, $min_l, $max_l, $volgorde, $fid]);
            flash('succes', "Functie '$naam' bijgewerkt.");
        }
        redirect(APP_URL . '/admin/instellingen.php?tab=functies');
    }

    // ── Lonen & budget: alles per kalenderjaar (includes/loon_tarieven.php) ──
    if (in_array($actie, ['loon_tarief', 'loon_jaar', 'loon_budget', 'loon_jaar_nieuw'], true)) {
        $lj = (int)($_POST['jaar'] ?? 0);
        if ($lj < 2000 || $lj > 2100) { flash('fout', 'Onbekend jaar.'); redirect(APP_URL . '/admin/instellingen.php?tab=loon'); }
        $terug_loon = APP_URL . '/admin/instellingen.php?tab=loon&jaar=' . $lj;
        if ($actie === 'loon_tarief') {
            $periode = (string)($_POST['periode'] ?? '') === 'jun' ? 'jun' : 'jul_sep';
            $n = loon_tarieven_opslaan($lj, $periode, (array)($_POST['loon'] ?? []));
            if (function_exists('audit_log')) audit_log('gewijzigd', 'instelling', null, ['loon_tarieven' => $lj, 'periode' => $periode, 'leeftijden' => $n]);
            flash($n ? 'succes' : 'fout', $n ? 'Minimumlonen ' . $lj . ' (' . loon_periodes()[$periode] . ') opgeslagen.' : 'Niets opgeslagen: vul bedragen tussen 0 en 100 in.');
        } elseif ($actie === 'loon_jaar' || $actie === 'loon_budget') {
            $velden = $actie === 'loon_budget' ? ['budget'] : ['vt_percentage', 'uren_per_dag', 'pc_vast_juni', 'pc_vast_jul_sep'];
            $post   = (array)($_POST['lj'] ?? []);
            $in     = [];
            foreach ($velden as $v) if (isset($post[$v]) && is_string($post[$v])) $in[$v] = $post[$v];
            $opgeslagen = loon_jaar_opslaan($lj, $in);
            if ($opgeslagen && function_exists('audit_log')) audit_log('gewijzigd', 'instelling', null, ['loon_jaar' => $lj, 'velden' => $opgeslagen]);
            $overgeslagen = array_diff(array_keys($in), $opgeslagen);
            flash($overgeslagen ? 'waarschuwing' : 'succes', $overgeslagen
                ? 'Niet alles opgeslagen: een waarde viel buiten de grenzen (' . implode(', ', $overgeslagen) . ').'
                : ($actie === 'loon_budget' ? 'Budget ' . $lj . ' opgeslagen.' : 'Vakantietoeslag, werkuren en postcommandant ' . $lj . ' opgeslagen.'));
        } else {
            $van = (int)($_POST['van'] ?? 0);
            $ok  = loon_jaar_kopieer($van, $lj);
            if ($ok && function_exists('audit_log')) audit_log('gewijzigd', 'instelling', null, ['loon_jaar_nieuw' => $lj, 'van' => $van]);
            flash($ok ? 'succes' : 'fout', $ok
                ? 'Tarieven ' . $lj . ' gemaakt op basis van ' . $van . '. Pas ze aan naar de nieuwe minimumlonen.'
                : 'Niet gelukt: ' . $lj . ' heeft al tarieven, of ' . $van . ' heeft er geen.');
        }
        redirect($terug_loon);
    }

    if ($actie === 'bezetting_opslaan') {
        // Sjablonen komen binnen als JSON (opgebouwd in de UI)
        $json = $_POST['sjablonen_json'] ?? '';
        $d = json_decode($json, true);
        if (is_array($d) && $d) {
            // Normaliseren + valideren
            $schoon = [];
            foreach ($d as $sleutel => $post) {
                $rollen = [];
                foreach ($post['rollen'] ?? [] as $r) {
                    $label = trim($r['label'] ?? '');
                    $cat   = trim($r['categorie'] ?? '');
                    if (!$label || !$cat) continue;
                    $rollen[] = [
                        'sleutel'   => preg_replace('/[^a-z0-9_]/', '', strtolower($r['sleutel'] ?? $label)) ?: uniqid('rol'),
                        'label'     => mb_substr($label, 0, 60),
                        'categorie' => $cat,
                        'min_rang'  => max(1, min(9, (int)($r['min_rang'] ?? 1))),
                        'aantal'    => max(1, min(20, (int)($r['aantal'] ?? 1))),
                    ];
                }
                if (!$rollen) continue;
                $schoon[preg_replace('/[^a-z0-9_]/', '', strtolower($sleutel))] = [
                    'naam'   => mb_substr(trim($post['naam'] ?? $sleutel), 0, 60),
                    'icoon'  => bh_icoonnaam((string)($post['icoon'] ?? ''), 'locatie'),
                    'rollen' => $rollen,
                    'regel_geen_twee_aspiranten' => !empty($post['regel_geen_twee_aspiranten']),
                ];
            }
            if ($schoon && function_exists('bezetting_sjablonen_opslaan')) {
                bezetting_sjablonen_opslaan($schoon);
                flash('succes', 'Bezettingssjablonen opgeslagen.');
            } else {
                flash('fout', 'Geen geldige posten gevonden.');
            }
        } else {
            flash('fout', 'Kon de sjablonen niet lezen.');
        }
        redirect(APP_URL . '/admin/instellingen.php?tab=bezetting');
    }

    if ($actie === 'bezetting_herstel') {
        if (function_exists('bezetting_default_sjablonen') && function_exists('bezetting_sjablonen_opslaan')) {
            bezetting_sjablonen_opslaan(bezetting_default_sjablonen());
            flash('succes', 'Standaard bezetting hersteld.');
        }
        redirect(APP_URL . '/admin/instellingen.php?tab=bezetting');
    }

    if ($actie === 'selectie_criteria_opslaan') {
        // Per test een tekstveld met één criterium per regel → JSON-structuur
        $criteria = [];
        foreach (['sport' => 'Sporttest', 'zwem' => 'Zwemtest'] as $tk => $default_naam) {
            $naam  = trim($_POST['naam_' . $tk] ?? '') ?: $default_naam;
            $icoon = trim($_POST['icoon_' . $tk] ?? '');
            $regels = preg_split('/\r?\n/', $_POST['criteria_' . $tk] ?? '');
            $lijst = [];
            foreach ($regels as $r) {
                $label = trim($r);
                if ($label === '') continue;
                $lijst[] = [
                    'sleutel' => preg_replace('/[^a-z0-9]/', '', strtolower($label)) ?: uniqid('c'),
                    'label'   => mb_substr($label, 0, 40),
                ];
            }
            if ($lijst) $criteria[$tk] = ['naam' => mb_substr($naam, 0, 40), 'icoon' => bh_icoonnaam($icoon, $tk === 'zwem' ? 'golf' : 'bliksem'), 'criteria' => $lijst];
        }
        if ($criteria && function_exists('selectie_criteria_opslaan')) {
            selectie_criteria_opslaan($criteria);
            flash('succes', 'Beoordelingscriteria opgeslagen.');
        } else {
            flash('fout', 'Geen geldige criteria gevonden.');
        }
        redirect(APP_URL . '/admin/instellingen.php?tab=selectie');
    }

    if ($actie === 'selectie_criteria_herstel') {
        if (function_exists('selectie_default_criteria') && function_exists('selectie_criteria_opslaan')) {
            selectie_criteria_opslaan(selectie_default_criteria());
            flash('succes', 'Standaardcriteria uit het draaiboek hersteld.');
        }
        redirect(APP_URL . '/admin/instellingen.php?tab=selectie');
    }
}


// Data
$functies = functies_ophalen();
// Lonen per kalenderjaar: standaard het jaar van het actieve seizoen (includes/loon_tarieven.php).
$loon_jaar_nu = loon_seizoen_jaar(actief_seizoen());
$loon_nu      = loon_tarieven_voor_jaar($loon_jaar_nu);   // Functies-tab: voorbeeldberekening
if ($tab === 'loon') {
    $loon_keuzes = loon_jaar_keuzes();
    $loon_jaar   = (int)($_GET['jaar'] ?? $loon_jaar_nu);
    if ($loon_jaar < 2000 || $loon_jaar > 2100) $loon_jaar = $loon_jaar_nu;
    if (!in_array($loon_jaar, $loon_keuzes, true)) { $loon_keuzes[] = $loon_jaar; rsort($loon_keuzes); }
    $loon_t       = loon_tarieven_voor_jaar($loon_jaar);
    $loon_eigen   = $loon_t['bron_jaar'] === $loon_jaar;
    $loon_jaren   = loon_tarief_jaren();
    $loon_nieuwst = $loon_jaren ? max($loon_jaren) : null;
    $loon_sz      = loon_seizoenen_per_jaar()[$loon_jaar] ?? [];
}

require __DIR__ . '/layout_top.php';
?>
<div class="bh-instkolommen">

<!-- Navigatie links. De iconen komen uit includes/iconen.php; eerder stond hier per
     item een losse SVG-string, waardoor dit scherm een eigen icoonset had. -->
<div class="bh-instzij">
  <nav class="ds-kaart bh-instnav" aria-label="Instellingen">
    <?php
    /* De tabbladen staan in bh_inst_tabs() (includes/beheer/zoek.php) en niet meer
       hier: de snelzoeker in de topbalk moet dezelfde lijst kennen, inclusief de
       zoekwoorden per tabblad. Twee kopieën betekent dat de ene "vakantietoeslag"
       vindt en de andere niet.

       De indeling zelf gaat over wát je instelt, niet over wélke techniek het
       gebruikt — dat was het probleem van de vorige: drie tabbladen over Authentik,
       AI op twee plekken, "Mail" als vergaarbak met de cronjob erin. */
    $nav_groepen = bh_inst_tabs();
    ?>
    <div class="bh-instzoek">
      <?= icoon('zoek', 15) ?>
      <input type="text" id="instZoek" class="bh-instzoek-veld" autocomplete="off"
             placeholder="Filter instellingen&hellip;" aria-label="Filter de instellingen"
             aria-describedby="instZoekUitslag">
    </div>
    <p class="bh-instzoek-leeg" id="instZoekUitslag" role="status" hidden>
      Geen tabblad met die term. Probeer een ander woord, of zoek met Ctrl&nbsp;K door het hele beheer.
    </p>

    <?php foreach ($nav_groepen as $groep => $items): ?>
      <div class="bh-instnav-groep" data-instgroep><?= h($groep) ?></div>
      <?php foreach ($items as $item): ?>
        <a class="bh-instnav-link<?= $tab === $item[0] ? ' actief' : '' ?>" href="?tab=<?= h($item[0]) ?>"
           data-instzoek="<?= h(mb_strtolower($item[1] . ' ' . $groep . ' ' . ($item[3] ?? ''))) ?>"
           <?= $tab === $item[0] ? 'aria-current="page"' : '' ?>>
          <?= icoon($item[2], 16) ?><?= h($item[1]) ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <?php if ($mag_sjablonen): ?>
    <div class="bh-instnav-extra" data-instgroep>
      <a class="bh-instnav-link" href="<?= APP_URL ?>/admin/mail_templates.php"
         data-instzoek="sjabloon template mail tekst bericht opmaak">
        <?= icoon('document', 16) ?>Sjablonen voor mails
      </a>
    </div>
    <?php endif; ?>
  </nav>

  <script>
  /* Filtert de tabbladenlijst terwijl je typt. Een groepskop verdwijnt zodra er
     geen zichtbaar tabblad meer onder staat — anders blijven er koppen zweven
     boven niets. Zonder JavaScript staat de volledige lijst er gewoon. */
  (function () {
    var veld = document.getElementById('instZoek');
    if (!veld) return;
    var melding = document.getElementById('instZoekUitslag');
    veld.addEventListener('input', function () {
      var t = veld.value.trim().toLowerCase();
      var raak = 0;
      document.querySelectorAll('.bh-instnav-link[data-instzoek]').forEach(function (a) {
        var aan = !t || a.dataset.instzoek.indexOf(t) !== -1;
        a.hidden = !aan;
        if (aan) raak++;
      });
      document.querySelectorAll('[data-instgroep]').forEach(function (g) {
        var zichtbaar = false, n = g.nextElementSibling;
        if (g.classList.contains('bh-instnav-extra')) {
          zichtbaar = !!g.querySelector('.bh-instnav-link:not([hidden])');
        } else {
          while (n && !n.hasAttribute('data-instgroep')) {
            if (n.classList.contains('bh-instnav-link') && !n.hidden) { zichtbaar = true; break; }
            n = n.nextElementSibling;
          }
        }
        g.hidden = !zichtbaar;
      });
      melding.hidden = raak !== 0;
    });
  })();
  </script>

  <section class="ds-kaart ds-kaart-p bh-instversie">
    <span class="bh-veldlabel">Versie</span>
    <span class="bh-veldwaarde"><?= h(defined('APP_VERSIE') ? APP_VERSIE : instelling('app.versie', '1.0.0')) ?></span>
    <?php if ($mag_systeem): ?><a href="<?= APP_URL ?>/admin/systeem.php">Systeem &amp; updates</a><?php endif; ?>
  </section>
</div>

<!-- Inhoud rechts: één onderwerp tegelijk -->
<div class="bh-instinhoud">

<?php if ($tab === 'algemeen'): ?>
<div class="ds-kaart">
  <div class="bh-kaartkop"><h2>Algemene instellingen</h2></div>
  <div class="bh-kaartbody">
    <form method="POST" enctype="multipart/form-data">
      <?= csrf_input() ?><input type="hidden" name="actie" value="instellingen">
      <div class="bh-instsectie">
        <h3>Organisatie</h3>
        <div class="ds-veld">
          <label>Naam van de organisatie</label>
          <input type="text" name="inst[site.naam]" value="<?= h(instelling('site.naam','Reddingsbrigade Paal 13')) ?>">
        </div>
        <div class="bh-veldrij">
          <div class="ds-veld">
            <label>Logo <?= bh_tip('Upload een bestand of vul een URL in. Wordt links bovenin getoond in admin én portaal. PNG, JPG of WebP; geen SVG, want daar kan script in zitten.') ?></label>
            <input type="file" name="logo_upload" accept="image/png,image/jpeg,image/webp">
            <div class="ds-veld-hint">Of vul een pad/URL in:</div>
            <input type="text" name="inst[site.logo_url]" value="<?= h(instelling('site.logo_url')) ?>" placeholder="/uploads/branding/logo.png" class="bh-inst-urlveld">
          </div>
          <div class="ds-veld">
            <label>Favicon <?= bh_tip('Pictogram in de browsertab. PNG of ICO.') ?></label>
            <input type="file" name="favicon_upload" accept="image/png,image/x-icon,image/vnd.microsoft.icon">
            <div class="ds-veld-hint">Of vul een pad/URL in:</div>
            <input type="text" name="inst[site.favicon_url]" value="<?= h(instelling('site.favicon_url')) ?>" placeholder="/uploads/branding/favicon.png" class="bh-inst-urlveld">
          </div>
        </div>
        <?php $logo = instelling('site.logo_url'); if ($logo): ?>
        <div class="bh-inst-logo">
          <img src="<?= h($logo) ?>" alt="Logo preview" class="bh-inst-logo-img">
          <span class="ds-veld-hint">Huidige logo</span>
        </div>
        <?php endif; ?>
      </div>

      <div class="bh-instsectie">
        <h3>Portaal</h3>
        <div class="ds-veld">
          <label>Welkomsttekst op portaal login <?= bh_tip('Tekst die sollicitanten zien op het inlogscherm van het portaal, voordat ze inloggen.') ?></label>
          <textarea name="inst[portaal.welkomst_tekst]" rows="3"><?= h(instelling('portaal.welkomst_tekst','Welkom op het sollicitantenportaal van Paal 13.')) ?></textarea>
        </div>
      </div>

      <div class="bh-instsectie">
        <h3>Taken na aannemen</h3>
        <?php
          $_hr_id  = instelling_int('taken.huisregels_bijlage_id', 0);
          $_hr_rij = ($_hr_id > 0 && function_exists('bijlage_rij')) ? bijlage_rij($_hr_id) : null;
        ?>
        <div class="ds-veld">
          <label>Huisregels (pdf) <?= bh_tip('Aangenomen strandwachten krijgen de taak \'Huisregels lezen\' met een link naar dit document. Zonder document blijft de taak staan als \'volgt binnenkort\'.') ?></label>
          <?php if ($_hr_rij): ?>
            <div class="bh-inst-huisregels">
              <span class="ds-badge-fase ds-fase-groen">In gebruik</span>
              <a href="<?= h(bijlage_download_url((int)$_hr_rij['id'], 7)) ?>" target="_blank" rel="noopener" class="bh-inst-huisregels-link"><?= h($_hr_rij['bestandsnaam']) ?></a>
              <span class="ds-veld-hint"><?= round((int)$_hr_rij['grootte'] / 1024) ?> kB · geplaatst <?= h(datum_nl(substr((string)$_hr_rij['aangemaakt_op'], 0, 10))) ?></span>
              <label class="bh-inst-losmaken"><input type="checkbox" name="huisregels_los" value="1"> Losmaken van de taak</label>
            </div>
          <?php elseif ($_hr_id > 0): ?>
            <div class="ds-melding ds-melding-error bh-inst-huisregels-stand">Het gekoppelde document (#<?= $_hr_id ?>) bestaat niet meer. Upload een nieuwe pdf.</div>
          <?php else: ?>
            <div class="ds-veld-hint bh-inst-huisregels-stand">Nog geen document. De taak "Huisregels lezen" staat bij aangenomen sollicitanten al klaar en krijgt automatisch een link zodra je hier een pdf plaatst.</div>
          <?php endif; ?>
          <input type="file" name="huisregels_upload" accept="application/pdf">
          <div class="ds-veld-hint">Een nieuwe pdf vervangt de link in de taak. Het bestand komt in de bijlagenbibliotheek; de sollicitant opent het via een tijdelijke, persoonlijke downloadlink.</div>
        </div>
      </div>

      <div class="bh-instsectie">
        <h3>Aanmeldformulier</h3>
        <p class="ds-veld-hint">De minimumleeftijd is 15 jaar (vast: onder de 16 is toestemming van een ouder nodig).</p>
      </div>

      <button type="submit" class="ds-btn ds-btn-primair">Opslaan</button>
    </form>
  </div>
</div>

<?php elseif ($tab === 'modules'): ?>
<div class="ds-kaart">
  <div class="bh-kaartkop"><h2>Modules</h2></div>
  <div class="bh-kaartbody">
    <p class="bh-inst-intro">
      De kern (sollicitanten, portaal, berichten, beschikbaarheid, ondertekenen, loondossiers) staat altijd aan.
      Een module die uitstaat verdwijnt uit het menu en uit de rechten, ook voor beheerders. Er wordt niets gewist:
      zet je hem weer aan, dan staat alles er nog.
    </p>
    <form method="POST">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="modules_opslaan">
      <?php foreach (modules_alle() as $sleutel => $m): ?>
      <div class="ds-veld">
        <label>
          <input type="checkbox" name="module[<?= h($sleutel) ?>]" value="1" <?= module_aan($sleutel) ? 'checked' : '' ?> <?= $mag_systeem ? '' : 'disabled' ?>>
          <?= h($m['label']) ?>
        </label>
        <div class="ds-veld-hint"><?= h($m['omschrijving']) ?></div>
      </div>
      <?php endforeach; ?>
      <?php if ($mag_systeem): ?>
        <button type="submit" class="ds-btn ds-btn-primair">Opslaan</button>
      <?php else: ?>
        <p class="ds-veld-hint">Modules aan- of uitzetten kan alleen wie Systeem mag bewerken.</p>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php /* Technisch (dit tabblad is alleen voor Systeem › bewerken): welke ondertekeningen een
         verificatiecode per sms/WhatsApp/e-mail vragen. Gelezen door ondertekening_2fa_typen(). */
      $_tfa_aan = function_exists('ondertekening_2fa_typen') ? ondertekening_2fa_typen() : ['loonheffing']; ?>
<div class="ds-kaart">
  <div class="bh-kaartkop"><h2>Ondertekenen: verificatiecode</h2></div>
  <div class="bh-kaartbody">
    <p class="bh-inst-intro">
      Bij de aangevinkte documenten krijgt wie tekent eerst een code (sms, dan WhatsApp, dan e-mail) en moet die
      invullen. De code en het kanaal komen in het bewijs bij de verzegelde PDF. Dat maakt de handtekening sterker,
      maar is ook een extra drempel: gebruik het voor documenten die wettelijk vastgelegd moeten worden. Het
      loonheffingsformulier (met BSN) houdt de code altijd.
    </p>
    <form method="POST">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="instellingen">
      <input type="hidden" name="tfa_formulier" value="1">
      <fieldset class="ds-veld">
        <legend>Verificatiecode verplicht bij</legend>
        <?php foreach (instellingen_2fa_keuzes() as $_t => $_l): $_vast = $_t === 'loonheffing'; ?>
          <label class="checkbox-label">
            <input type="checkbox" name="tfa_typen[]" value="<?= h($_t) ?>" <?= $_vast || in_array($_t, $_tfa_aan, true) ? 'checked' : '' ?> <?= $_vast ? 'disabled' : '' ?>>
            <?= h($_l) ?><?= $_vast ? ' (altijd, wettelijk document)' : '' ?>
          </label>
        <?php endforeach; ?>
        <div class="ds-veld-hint">De inschrijving als lid vraagt nooit een code: het is een verenigingsformulier, geen wettelijk document.</div>
      </fieldset>
      <button type="submit" class="ds-btn ds-btn-primair">Opslaan</button>
    </form>
  </div>
</div>

<?php elseif ($tab === 'functies'): ?>
<div class="ds-kaart">
  <div class="bh-kaartkop"><h2>Functies & factoren</h2></div>
  <div class="bh-kaartbody">
    <p class="bh-inst-intro">
      Het dagloon per persoon = minimumloon/uur × <?= h(loon_getal($loon_nu['uren_per_dag'])) ?> uur × factor × <?= h(loon_getal(1 + $loon_nu['vt_percentage'] / 100)) ?> (VT),
      met de tarieven van <?= (int)($loon_nu['bron_jaar'] ?? $loon_jaar_nu) ?> (<a href="?tab=loon">Lonen &amp; budget</a>). De factor geldt voor elk jaar.
    </p>
    <?php foreach ($functies as $f): ?>
    <form method="POST" class="bh-functie">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="functie_opslaan">
      <input type="hidden" name="functie_id" value="<?= $f['id'] ?>">
      <div class="bh-functie-rij">
        <div class="ds-veld">
          <label>Naam</label>
          <input type="text" name="naam" value="<?= h($f['naam']) ?>">
        </div>
        <div class="ds-veld">
          <label>Factor</label>
          <input type="number" name="factor" value="<?= $f['factor'] ?>" step="0.01" min="0" max="5">
        </div>
        <div class="ds-veld">
          <label>Min leeftijd</label>
          <input type="number" name="min_leeftijd" value="<?= $f['min_leeftijd'] ?>" min="14" max="99">
        </div>
        <div class="ds-veld">
          <label>Max leeftijd</label>
          <input type="number" name="max_leeftijd" value="<?= $f['max_leeftijd'] ?>" min="14" max="99">
        </div>
        <div class="ds-veld">
          <label>Volgorde</label>
          <input type="number" name="volgorde" value="<?= $f['volgorde'] ?>" min="0" max="99">
        </div>
        <div class="bh-functie-knop">
          <button type="submit" class="ds-btn ds-btn-primair ds-btn-sm">Sla op</button>
        </div>
      </div>
      <div class="bh-functie-voorbeeld">
        Voorbeeldberekening 20 jaar, juni <?= $loon_jaar_nu ?>:
        <strong><?= h(euro(bereken_dagloon(20, (string)$f['sleutel'], 'jun', null, $loon_jaar_nu), 2)) ?>/dag</strong>
        <?= $f['sleutel'] === 'postcommandant' ? '(vast weekbedrag ÷ 5)'
            : h('(uurloon × ' . loon_getal($loon_nu['uren_per_dag']) . ' × ' . loon_getal((float)$f['factor']) . ' × ' . loon_getal(1 + $loon_nu['vt_percentage'] / 100) . ')') ?>
      </div>
    </form>
    <?php endforeach; ?>

  </div>
</div>

<?php elseif ($tab === 'loon'): ?>
<?php /* Alles op dit tabblad geldt per kalenderjaar (includes/loon_tarieven.php). Een seizoen rekent met
         het jaar van de start van de werkperiode; ontbreken die tarieven, dan met het jaar ervoor. */ ?>
<div class="ds-kaart">
  <div class="bh-kaartkop">
    <h2>Lonen &amp; budget <?= $loon_jaar ?></h2>
    <div class="ds-veld-hint">Per kalenderjaar. Een seizoen rekent met het jaar waarin de werkperiode begint.</div>
  </div>
  <div class="bh-kaartbody">
    <?php
      $_opties = [];
      foreach ($loon_keuzes as $_j) $_opties[$_j] = $_j . (in_array($_j, $loon_jaren, true) ? '' : ' (nog geen tarieven)');
      $filter_velden    = [['soort' => 'keuze', 'naam' => 'jaar', 'label' => 'Jaar', 'waarde' => $loon_jaar, 'opties' => $_opties]];
      $filter_verborgen = ['tab' => 'loon'];
      require __DIR__ . '/_filterbalk.php';
    ?>
    <p class="ds-veld-hint bh-mb">
      <?= $loon_sz ? 'Geldt voor seizoen ' . h(implode(', ', $loon_sz)) . '.' : 'Er is nog geen seizoen in ' . $loon_jaar . '.' ?>
      Een wijziging hier geldt voor alle loonberekeningen van dit jaar (ook vastgestelde afspraken en de export) en verandert andere jaren niet.
    </p>
    <?php if (!$loon_eigen): ?>
      <div class="ds-melding ds-melding-warn bh-mb" role="status">
        <?= icoon('waarschuwing', 16) ?> <?= h(loon_tarieven_melding($loon_t)) ?>
        <?php if ($loon_t['bron_jaar'] !== null): ?>
          Maak de tarieven voor <?= $loon_jaar ?> aan: dat kopieert <?= (int)$loon_t['bron_jaar'] ?> (zonder het budget), daarna vul je de nieuwe minimumlonen in.
        <?php endif; ?>
      </div>
      <?php if ($loon_t['bron_jaar'] !== null): ?>
        <form method="POST">
          <?= csrf_input() ?>
          <input type="hidden" name="actie" value="loon_jaar_nieuw">
          <input type="hidden" name="jaar" value="<?= $loon_jaar ?>">
          <input type="hidden" name="van" value="<?= (int)$loon_t['bron_jaar'] ?>">
          <button type="submit" class="ds-btn ds-btn-primair"><?= icoon('plus', 15) ?> Tarieven <?= $loon_jaar ?> maken op basis van <?= (int)$loon_t['bron_jaar'] ?></button>
        </form>
      <?php endif; ?>
    <?php elseif ($loon_nieuwst !== null && !in_array($loon_nieuwst + 1, $loon_jaren, true)): ?>
      <form method="POST" data-bevestig="Nieuw jaar <?= $loon_nieuwst + 1 ?> maken? De tarieven, vakantietoeslag, werkuren en postcommandant van <?= $loon_nieuwst ?> worden gekopieerd; het budget niet. Daarna pas je de bedragen aan.">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="loon_jaar_nieuw">
        <input type="hidden" name="jaar" value="<?= $loon_nieuwst + 1 ?>">
        <input type="hidden" name="van" value="<?= $loon_nieuwst ?>">
        <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm"><?= icoon('plus', 14) ?> Nieuw jaar <?= $loon_nieuwst + 1 ?> op basis van <?= $loon_nieuwst ?></button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="ds-kaart">
  <div class="bh-kaartkop">
    <h2>Budget <?= $loon_jaar ?></h2>
    <div class="ds-veld-hint">Voor de voortgangsbalk op de loonplanning</div>
  </div>
  <div class="bh-kaartbody">
    <form method="POST" class="bh-inst-regelform">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="loon_budget">
      <input type="hidden" name="jaar" value="<?= $loon_jaar ?>">
      <div class="ds-veld bh-inst-kaalveld">
        <label for="lj-budget">Totaalbudget loonkosten <?= $loon_jaar ?> (€)</label>
        <input type="number" step="1" min="0" max="99999999" id="lj-budget" name="lj[budget]"
               value="<?= $loon_t['budget'] > 0 ? h((string)$loon_t['budget']) : '' ?>" placeholder="bijv. 25000" class="bh-inst-budgetveld"
               aria-describedby="lj-budget-hint">
      </div>
      <button type="submit" class="ds-btn ds-btn-primair">Opslaan</button>
      <div class="bh-inst-regelform-hint" id="lj-budget-hint">
        Laat leeg om zonder budget te werken — de loonplanning toont dan alleen de kosten. Het budget hoort bij dit
        jaar alleen: een volgend jaar begint zonder budget.
      </div>
    </form>
  </div>
</div>

<?php if ($loon_eigen): ?>

<!-- Vakantietoeslag stond bij Functies; alles over geld hoort bij elkaar. Per jaar, want ook dit kan wijzigen. -->
<div class="ds-kaart">
  <div class="bh-kaartkop"><h2>Vakantietoeslag &amp; werkuren <?= $loon_jaar ?></h2></div>
  <div class="bh-kaartbody">
      <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="loon_jaar">
        <input type="hidden" name="jaar" value="<?= $loon_jaar ?>">
        <div class="bh-veldrij">
          <div class="ds-veld">
            <label for="lj-vt">Vakantietoeslag % <?= bh_tip('Wordt automatisch opgeteld bij de uurlonen in de loonplanning. Standaard 8%.') ?></label>
            <input type="number" id="lj-vt" name="lj[vt_percentage]" value="<?= h((string)$loon_t['vt_percentage']) ?>" min="0" max="50" step="0.01">
            <div class="ds-veld-hint">Standaard 8% (wettelijk minimum)</div>
          </div>
          <div class="ds-veld">
            <label for="lj-uren">Werkuren per dag <?= bh_tip('Gebruikt voor dagloon-berekening: uurloon × dit getal × functie-factor × VT.') ?></label>
            <input type="number" id="lj-uren" name="lj[uren_per_dag]" value="<?= h((string)$loon_t['uren_per_dag']) ?>" min="1" max="12" step="0.25">
            <div class="ds-veld-hint">Standaard 8 uur (40u/week ÷ 5)</div>
          </div>
        </div>
        <div class="bh-veldrij">
          <div class="ds-veld">
            <label for="lj-pc-jun">Vast weekbedrag postcommandant (juni) <?= bh_tip('Postcommandant heeft vast bedrag per week, niet gerelateerd aan leeftijd.') ?></label>
            <div class="bh-inst-euro"><span aria-hidden="true">€</span>
            <input type="number" id="lj-pc-jun" name="lj[pc_vast_juni]" value="<?= h((string)$loon_t['pc_vast_juni']) ?>" min="0" max="99999" step="0.01"></div>
          </div>
          <div class="ds-veld">
            <label for="lj-pc-jul">Vast weekbedrag postcommandant (jul-sep)</label>
            <div class="bh-inst-euro"><span aria-hidden="true">€</span>
            <input type="number" id="lj-pc-jul" name="lj[pc_vast_jul_sep]" value="<?= h((string)$loon_t['pc_vast_jul_sep']) ?>" min="0" max="99999" step="0.01"></div>
          </div>
        </div>
        <button type="submit" class="ds-btn ds-btn-primair">Opslaan</button>
      </form>
  </div>
</div>

<?php
  // Factor van een lifeguard voor de voorbeeldkolom "Dag (LG)"; de JS rekent live met dezelfde getallen.
  $_lg_factor = 1.20;
  foreach ($functies as $_f) if ($_f['sleutel'] === 'lifeguard') $_lg_factor = (float)$_f['factor'];
?>
<div class="ds-kaart">
  <div class="bh-kaartkop">
    <h2>Minimumlonen per leeftijd <?= $loon_jaar ?></h2>
    <div class="ds-veld-hint">Bedragen per uur, excl. vakantietoeslag</div>
  </div>
  <div class="bh-kaartbody">
    <div class="bh-veldrij bh-inst-loonrij">
      <?php foreach (['jun'=>'Juni','jul_sep'=>'Juli / Aug / Sep'] as $periode=>$label): ?>
      <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="loon_tarief">
        <input type="hidden" name="jaar" value="<?= $loon_jaar ?>">
        <input type="hidden" name="periode" value="<?= $periode ?>">
        <h3 class="bh-inst-subkop"><?= $label ?></h3>
        <table class="bh-loontabel" data-loonvoorbeeld data-uren="<?= h((string)$loon_t['uren_per_dag']) ?>"
               data-factor="<?= h((string)$_lg_factor) ?>" data-vt="<?= h((string)$loon_t['vt_percentage']) ?>">
          <thead>
            <tr>
              <th>Leeftijd</th>
              <th>€/uur</th>
              <th>Dag (LG)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (loon_leeftijden() as $l):
              $loon = $loon_t['tarieven'][$periode][$l] ?? '';
              $dagl = $loon !== '' ? bereken_dagloon($l, 'lifeguard', $periode, null, $loon_jaar) : null;
            ?>
            <tr>
              <th scope="row"><?= $l ?> jr</th>
              <td>
                <span class="bh-euroveld">
                  <span aria-hidden="true">€</span>
                  <label class="sr-only" for="loon-<?= h($periode) ?>-<?= $l ?>">Uurloon <?= $l ?> jaar, <?= h($label) ?> <?= $loon_jaar ?></label>
                  <input type="number" id="loon-<?= h($periode) ?>-<?= $l ?>" name="loon[<?= $l ?>]"
                         value="<?= $loon === '' ? '' : h((string)$loon) ?>" step="0.01" min="0" max="99"
                         data-voorbeeld="prev-<?= h($periode) ?>-<?= $l ?>">
                </span>
              </td>
              <td class="bh-meta" id="prev-<?= $periode ?>-<?= $l ?>">
                <?= $dagl ? h(euro((float)$dagl, 2)) : '—' ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <button type="submit" class="ds-btn ds-btn-primair bh-inst-breedknop">Opslaan <?= $label ?> <?= $loon_jaar ?></button>
      </form>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php elseif ($tab === 'uitnodiging'): ?>
<div class="ds-kaart">
  <div class="bh-kaartkop"><h2>Uitnodigen &amp; automatisch</h2></div>
  <div class="bh-kaartbody">
    <form method="POST">
      <?= csrf_input() ?><input type="hidden" name="actie" value="instellingen">
      <div class="bh-instsectie">
        <h3>Seizoen en selectie</h3>
        <div class="bh-veldrij">
          <div class="ds-veld">
            <label for="inst-seizoen-fase">Seizoensfase</label>
            <?php $_sf = instelling('seizoen.fase', ''); ?>
            <select id="inst-seizoen-fase" name="inst[seizoen.fase]" aria-describedby="inst-seizoen-fase-hint">
              <option value="" <?= $_sf === '' ? 'selected' : '' ?>>Automatisch (uit de datums van het seizoen)</option>
              <?php foreach (bh_seizoen_fases() as $_fk => $_fl): ?>
              <option value="<?= h($_fk) ?>" <?= $_sf === $_fk ? 'selected' : '' ?>><?= h($_fl) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="ds-veld-hint" id="inst-seizoen-fase-hint">Bepaalt de seizoensbalk en de hoofdtaak op Vandaag, en wat de sollicitantenlijst als afwijkend aanmerkt. Kies alleen een vaste fase als de datums niet kloppen, bijvoorbeeld als de selectiedag verschoven is of de belronde uitloopt. Zet hem daarna terug op automatisch.</div>
          </div>
          <div class="ds-veld">
            <label for="inst-selectie-drempel">Advies-drempel selectiedag</label>
            <input type="number" id="inst-selectie-drempel" name="inst[selectie.drempel]" value="<?= h(instelling('selectie.drempel', '3.5')) ?>" min="1" max="5" step="0.1" aria-describedby="inst-selectie-drempel-hint">
            <div class="ds-veld-hint" id="inst-selectie-drempel-hint">Gemiddelde score (1 tot 5) vanaf waar het dossier "Door" adviseert. Tot een halve punt eronder is het "Twijfel", daaronder "Niet". Het is een advies; het besluit neem je zelf.</div>
          </div>
        </div>
      </div>

      <?php if (module_aan('trainingsweekend')): ?>
      <div class="bh-instsectie">
        <h3>Na het gesprek op het trainingsweekend</h3>
        <p class="ds-veld-hint bh-mb">
          In het gesprek schrijft het bestuur op wat er besproken is; dat gaat mee in de mail als <code>{{gesprek_bericht}}</code>.
          Blijft dat veld leeg, dan komt deze standaardtekst erin. Leeg laten = de ingebouwde tekst.
        </p>
        <?php foreach ([['tw.bericht_aangenomen', 'Standaardtekst bij aangenomen', 'aannemen', false],
                        ['tw.bericht_afgewezen', 'Standaardtekst bij afgewezen', 'afwijzen', false],
                        ['tw.bericht_afgewezen_niet_gekomen', 'Standaardbericht bij afwijzen: niet op het trainingsweekend geweest', 'afwijzen', true]] as $_v): ?>
        <div class="ds-veld">
          <label for="inst-<?= h(str_replace('.', '-', $_v[0])) ?>"><?= h($_v[1]) ?></label>
          <textarea id="inst-<?= h(str_replace('.', '-', $_v[0])) ?>" name="inst[<?= h($_v[0]) ?>]" rows="3" maxlength="2000"
                    placeholder="<?= h(instelling($_v[0], '') === '' ? tw_standaard_bericht($_v[2], $_v[3]) : '') ?>"><?= h((string)instelling($_v[0], '')) ?></textarea>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="bh-instsectie">
        <h3>Automatisch uitnodigen voor de selectiedag</h3>
        <p class="ds-veld-hint bh-mb">
          Sollicitanten binnen de leeftijdsgrenzen worden automatisch uitgenodigd, of je krijgt ze als voorstel te zien.
        </p>
        <div class="ds-veld">
          <label>Modus</label>
          <select name="inst[invite.modus]">
            <?php foreach (['uit'=>'Uit — geen automatisering','suggestie'=>'Suggestie — markeer in de lijst, admin handelt','auto'=>'Automatisch — nodig uit na ingestelde vertraging'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= instelling('invite.modus') === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="bh-veldrij">
          <div class="ds-veld">
            <label>Minimumleeftijd</label>
            <input type="number" name="inst[invite.min_leeftijd]" value="<?= instelling_int('invite.min_leeftijd',16) ?>" min="14" max="40">
          </div>
          <div class="ds-veld">
            <label>Maximumleeftijd</label>
            <input type="number" name="inst[invite.max_leeftijd]" value="<?= instelling_int('invite.max_leeftijd',30) ?>" min="14" max="65">
          </div>
        </div>
        <div class="ds-veld" id="delay_veld"<?= instelling('invite.modus') !== 'auto' ? ' hidden' : '' ?>>
          <label>Vertraging in uren (alleen bij automatisch)</label>
          <input type="number" name="inst[invite.delay_uur]" value="<?= instelling_int('invite.delay_uur',24) ?>" min="0" max="168">
          <div class="ds-veld-hint">0 = direct uitnodigen. 24 = na 24 uur automatisch uitnodigen.</div>
        </div>
      </div>

      <div class="bh-instsectie">
        <h3>Automatisering bij fase-wijziging</h3>
        <p class="ds-veld-hint bh-mb">
          Wat gebeurt er automatisch als een sollicitant van fase wisselt.
        </p>
        <div class="bh-veldrij">
          <div class="ds-veld">
            <label>
              <input type="hidden" name="inst[auto.ondertekening_sd]" value="0">
              <input type="checkbox" name="inst[auto.ondertekening_sd]" value="1" <?= instelling('auto.ondertekening_sd', '1') === '1' ? 'checked' : '' ?>>
              Ondertekeningen bij uitnodiging selectiedag
            </label>
            <div class="ds-veld-hint">18+: gezondheid + intake als portaal-taak. Minderjarig: ouder krijgt automatisch mail zodra sollicitant ouder-gegevens invult.</div>
          </div>
          <div class="ds-veld">
            <label>
              <input type="hidden" name="inst[auto.kennismakingspakket]" value="0">
              <input type="checkbox" name="inst[auto.kennismakingspakket]" value="1" <?= instelling('auto.kennismakingspakket', '1') === '1' ? 'checked' : '' ?>>
              Kennismakingspakket bij aangenomen
            </label>
            <div class="ds-veld-hint">Welkomstmail + portaal-taken (lidmaatschap, loonheffing, huisregels).</div>
          </div>
        </div>
      </div>

      <div class="bh-instsectie">
        <h3>Herinneringen</h3>
        <p class="ds-veld-hint bh-mb">
          Automatische herinneringen, gecontroleerd op wat er echt verstuurd is (niet dubbel). Inhoud en kanaal per type:
          <?php if ($mag_sjablonen): ?><a href="<?= APP_URL ?>/admin/mail_templates.php" class="bh-let-op">Sjablonen</a> en<?php endif; ?>
          <a href="<?= APP_URL ?>/admin/instellingen.php?tab=berichten" class="bh-let-op">Kanalen &amp; meldingen</a>.
          <?php if ($mag_systeem): ?>Proefdraaien zonder te versturen: <a href="<?= APP_URL ?>/admin/systeem.php#herinneringen" class="bh-let-op">Systeem › Herinneringen</a>.<?php endif; ?>
        </p>

        <?php if (function_exists('herinnering_regels')): foreach (herinnering_regels() as $hslug => $hregel):
          $h_aan = herinnering_instelling($hslug, 'aan') === '1'; ?>
        <div class="bh-veldrij bh-inst-herinnering">
          <div class="ds-veld bh-inst-herinnering-wat">
            <label>
              <input type="hidden" name="inst[herinnering.<?= $hslug ?>.aan]" value="0">
              <input type="checkbox" name="inst[herinnering.<?= $hslug ?>.aan]" value="1" <?= $h_aan ? 'checked' : '' ?>>
              <?= h($hregel['naam']) ?>
            </label>
            <div class="ds-veld-hint"><?= h($hregel['uitleg']) ?></div>
            <?php if ($hslug === 'selectiedag_morgen'): ?>
            <input type="text" name="inst[herinnering.selectiedag_morgen.info]" value="<?= h(instelling('herinnering.selectiedag_morgen.info', '') ?: instelling('reminder.sd_morgen_info', '')) ?>" placeholder="Laatste info, bv: het wordt warm, neem water mee" class="bh-inst-herinnering-info">
            <?php endif; ?>
          </div>
          <?php if (empty($hregel['vast_na'])): ?>
          <div class="ds-veld bh-inst-getalveld">
            <label><?= in_array($hslug, ['selectiedag_nadert', 'trainingsweekend_nadert'], true) ? 'Dagen vooraf' : 'Na (dagen)' ?></label>
            <input type="number" name="inst[herinnering.<?= $hslug ?>.na]" value="<?= (int)herinnering_instelling($hslug, 'na') ?>" min="0" max="60">
          </div>
          <?php endif; ?>
          <?php if ((int)$hregel['herhaal'] > 0): ?>
          <div class="ds-veld bh-inst-getalveld">
            <label>Herhaal elke</label>
            <input type="number" name="inst[herinnering.<?= $hslug ?>.herhaal]" value="<?= (int)herinnering_instelling($hslug, 'herhaal') ?>" min="1" max="60">
          </div>
          <div class="ds-veld bh-inst-getalveld-smal">
            <label>Max</label>
            <input type="number" name="inst[herinnering.<?= $hslug ?>.max]" value="<?= (int)herinnering_instelling($hslug, 'max') ?>" min="1" max="20">
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; endif; ?>

        <div class="bh-veldrij">
          <div class="ds-veld">
            <label>Rustperiode (dagen)</label>
            <input type="number" name="inst[herinnering.rust_dagen]" value="<?= instelling_int('herinnering.rust_dagen', 3) ?>" min="0" max="30">
            <div class="ds-veld-hint">Maximaal één "zachte" herinnering per sollicitant per zoveel dagen.</div>
          </div>
          <div class="ds-veld">
            <label>Hoe vaak hetzelfde bericht per dag</label>
            <input type="number" name="inst[melding.plafond_dag]" value="<?= instelling_int('melding.plafond_dag', 8) ?>" min="1" max="50">
            <div class="ds-veld-hint">Vaker hetzelfde automatische bericht naar dezelfde ontvanger in 24 uur wordt overgeslagen (zie het logboek).
              Andere berichtsoorten, bestuursmeldingen en wat je zelf in gang zet (fase omzetten, bulk, handmatig) tellen niet mee.</div>
          </div>
          <div class="ds-veld">
            <label>Bijlage als e-mailbijlage tot (MB)</label>
            <input type="number" name="inst[bijlage.mail_max_mb]" value="<?= instelling_int('bijlage.mail_max_mb', 15) ?>" min="1" max="25">
            <div class="ds-veld-hint">Grotere bestanden gaan als downloadknop in de mail (link 14 dagen geldig). Weigert de mailserver een bijlage toch, dan gaat de mail automatisch opnieuw met een downloadknop.</div>
          </div>
          <div class="ds-veld">
            <label>WhatsApp per uur (max)</label>
            <input type="number" name="inst[wa.max_per_uur]" value="<?= instelling_int('wa.max_per_uur', 30) ?>" min="1" max="500">
            <div class="ds-veld-hint">Boven dit aantal valt de kern terug op SMS/e-mail.</div>
          </div>
        </div>
      </div>
      <button type="submit" class="ds-btn ds-btn-primair">Opslaan</button>
    </form>
  </div>
</div>

<?php elseif ($tab === 'mail'): ?>
<div class="ds-kaart">
  <div class="bh-kaartkop"><h2>Afzender &amp; adressen</h2></div>
  <div class="bh-kaartbody">
    <form method="POST">
      <?= csrf_input() ?><input type="hidden" name="actie" value="instellingen">
      <div class="bh-instsectie">
        <h3>E-mailadressen</h3>
        <div class="bh-veldrij">
          <div class="ds-veld">
            <label>Foutmeldingen sturen naar</label>
            <input type="email" name="inst[mail.fout_email]" value="<?= h(instelling('mail.fout_email','website@paal13.com')) ?>">
            <div class="ds-veld-hint">Ontvangt automatisch een mail bij technische fouten (foutcode + details)</div>
          </div>
          <div class="ds-veld">
            <label>Bestuur e-mail</label>
            <input type="email" name="inst[mail.bestuur_email]" value="<?= h(instelling('mail.bestuur_email','bestuur@paal13.com')) ?>">
            <div class="ds-veld-hint">Ontvangt notificaties bij nieuwe aanmeldingen</div>
          </div>
        </div>
        <div class="bh-veldrij">
          <div class="ds-veld">
            <label>Contact voor sollicitanten</label>
            <input type="email" name="inst[mail.contact_email]" value="<?= h(instelling('mail.contact_email','sollicitatie@paal13.com')) ?>">
            <div class="ds-veld-hint">Staat in het portaal onder Contact en onderaan elke pagina; vragen uit het portaal komen hier binnen. Leeg = bestuursadres</div>
          </div>
        </div>
        <div class="bh-veldrij">
          <div class="ds-veld">
            <label>Penningmeester e-mail</label>
            <input type="email" name="inst[mail.penningmeester_email]" value="<?= h(instelling('mail.penningmeester_email','')) ?>">
            <div class="ds-veld-hint">Ontvangt de melding "loondossier compleet" (loonheffing, kopie ID, bankpas). Leeg = bestuursadres</div>
          </div>
          <div class="ds-veld">
            <label>Ledenservice e-mail</label>
            <input type="email" name="inst[mail.ledenservice_email]" value="<?= h(instelling('mail.ledenservice_email','')) ?>">
            <div class="ds-veld-hint">Ontvangt de ondertekende inschrijving als lid/donateur (met PDF). Leeg = bestuursadres</div>
          </div>
        </div>
        <div class="ds-veld">
          <label class="checkbox-label"><input type="hidden" name="inst[documenten.penningmeester_bijlagen]" value="0"><input type="checkbox" name="inst[documenten.penningmeester_bijlagen]" value="1" <?= instelling('documenten.penningmeester_bijlagen','0') === '1' ? 'checked' : '' ?>> Loondossier-stukken als bijlage meesturen in de mail aan de penningmeester</label>
          <div class="ds-veld-hint">Standaard uit: de stukken (BSN, kopie identiteitsbewijs, bankpas) gaan dan alleen via de beveiligde inzage in het beheer. Aanzetten stuurt ze onversleuteld per e-mail; doe dat alleen als het mailpad vertrouwd is.</div>
        </div>
      </div>
      <button type="submit" class="ds-btn ds-btn-primair">Opslaan</button>
    </form>
  </div>
</div>

<?php elseif ($tab === 'ai'): ?>

  <form method="POST" action="">
    <?= csrf_input() ?>
    <input type="hidden" name="actie" value="instellingen">
    <input type="hidden" name="tab" value="ai">

    <div class="ds-kaart">
      <div class="bh-kaartkop"><h2>AI Analyse — Gemini</h2></div>
      <div class="bh-kaartbody">

        <div class="bh-instsectie">
          <h3>API Configuratie</h3>
          <div class="bh-veldrij">
            <?= bh_geheim_veld('inst[ai.api_key]', 'ai.api_key', 'Gemini API-sleutel', instelling('ai.api_key', '') !== '',
                  '<a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener" class="bh-let-op">Gratis sleutel aanvragen bij Google AI Studio</a>') ?>
            <div class="ds-veld">
              <label>Model</label>
              <select name="inst[ai.model]">
                <?php // Zelfde standaard als ai_model() in includes/ai_analyse.php; uitgezette modellen vallen daar terug.
                      $m = function_exists('ai_model') ? ai_model() : instelling('ai.model', 'gemini-2.5-flash'); ?>
                <option value="gemini-2.5-flash"      <?= $m==='gemini-2.5-flash'?'selected':'' ?>>Gemini 2.5 Flash (standaard)</option>
                <option value="gemini-2.5-flash-lite" <?= $m==='gemini-2.5-flash-lite'?'selected':'' ?>>Gemini 2.5 Flash-Lite (sneller, goedkoper)</option>
                <option value="gemini-2.5-pro"        <?= $m==='gemini-2.5-pro'?'selected':'' ?>>Gemini 2.5 Pro (grondiger, trager)</option>
              </select>
            </div>
          </div>
        </div>

        <div class="bh-instsectie">
          <h3>Experimentele functies</h3>
          <?php $besch_aan = instelling('ai.beschikbaarheid_hulp', '0') === '1'; ?>
          <label class="bh-inst-experiment">
            <input type="hidden" name="inst[ai.beschikbaarheid_hulp]" value="0">
            <input type="checkbox" name="inst[ai.beschikbaarheid_hulp]" value="1" <?= $besch_aan ? 'checked' : '' ?>>
            <span>
              <strong>AI-hulp bij beschikbaarheid</strong>
              <span class="bh-inst-experiment-label">EXPERIMENTEEL</span>
              <span class="bh-inst-experiment-uitleg">
                Sollicitanten kunnen hun beschikbaarheid in gewone taal typen; de AI vult het formulier dan alvast in.
                De sollicitant controleert het resultaat daarna zelf. Vereist een geldige Gemini API-key hierboven.
              </span>
            </span>
          </label>
        </div>

        <div class="bh-instsectie">
          <h3>Wat er naar Google gaat</h3>
          <p class="ds-veld-hint bh-mb">
            De instructie voor de analyse staat vast in de code (<code>includes/ai_analyse.php</code>), zodat
            niemand er per ongeluk persoonsgegevens aan toevoegt. Per sollicitant gaat alleen dit mee:
            leeftijdscategorie, opleiding, diploma's en certificaten, werkervaring, de twee motivaties en de
            beschikbaarheid (aantal weken). <strong>Niet</strong>: naam, e-mail, telefoon, adres, foto en de
            bijzonderheden uit het formulier (die kunnen medisch zijn). Heeft een sollicitant bezwaar gemaakt,
            dan gaat er niets meer heen.
          </p>
        </div>

        <div class="bh-instsectie">
          <h3>Activering</h3>
          <label class="bh-inst-vinkregel">
            <input type="hidden" name="inst[ai.auto_analyse]" value="0">
            <input type="checkbox" name="inst[ai.auto_analyse]" value="1" <?= instelling('ai.auto_analyse', '1') === '1' ? 'checked' : '' ?>>
            <span>Automatisch analyseren na ontvangst kennismakingsformulier</span>
          </label>
          <div class="ds-veld-hint">Uit = alleen handmatig, met de knop "Analyseer nu" bij het volledige formulier.</div>
        </div>

      </div>
      <div class="bh-inst-kaartvoet">
        <button type="submit" class="ds-btn ds-btn-primair">Opslaan</button>
      </div>
    </div>
  </form>

<?php elseif ($tab === 'authentik'): ?>

  <!-- Inloggen: stond eerder als apart tabblad "Authentik SSO-login". Twee tabbladen
       over dezelfde koppeling is één te veel. -->
<div class="ds-kaart">
  <div class="bh-kaartkop"><h2>Authentik SSO</h2></div>
  <div class="bh-kaartbody">
    <p class="bh-inst-intro bh-inst-intro-14">
      De koppeling met Authentik staat in <code>includes/config.php</code> (OIDC_ISSUER, OIDC_CLIENT_ID).
      Met een extra API-token kunnen <strong>terugkerende sollicitanten</strong> met hun Paal 13-account
      inloggen, in plaats van met een inloglink per e-mail.
    </p>

    <div class="info-blok bh-inst-status">
      <div class="bh-inst-status-kop">Status</div>
      <div>Koppeling in config.php: <?= authentik_actief() ? '<span class="bh-tekst-ok">aanwezig</span>' : '<span class="bh-tekst-fout">ontbreekt</span>' ?></div>
      <div>API-token: <?= instelling('authentik.api_token') ? '<span class="bh-tekst-ok">ingesteld</span>' : '<span class="bh-tekst-grijs">— geen (alleen inloglink per e-mail)</span>' ?></div>
      <div>Issuer: <code><?= defined('OIDC_ISSUER') ? h(OIDC_ISSUER) : '—' ?></code></div>
    </div>

    <form method="POST">
      <?= csrf_input() ?>
      <input type="hidden" name="inst_tab" value="sso">

      <?= bh_geheim_veld('inst[authentik.api_token]', 'authentik.api_token', 'Authentik API-token', instelling('authentik.api_token', '') !== '',
            'Maak in Authentik onder Directory › Tokens een long-lived token aan, met scope <code>core/users</code> (view). Verwijder het token om SSO voor het portaal uit te zetten; inloggen met een code blijft werken.') ?>

      <div class="ds-veld">
        <label>Groepen met toegang tot het beheer <?= bh_tip('Namen van groepen in Authentik die in het beheer mogen. Andere gebruikers kunnen alleen in het portaal inloggen.') ?></label>
        <input type="text" name="inst[authentik.admin_groepen]" value="<?= h(instelling('authentik.admin_groepen', AUTHENTIK_STANDAARD_GROEPEN)) ?>"
               placeholder="<?= h(AUTHENTIK_STANDAARD_GROEPEN) ?>">
        <div class="ds-veld-hint">
          Scheid meerdere groepen met een komma. Wie in een van deze groepen zit, komt in het beheer.
        </div>
      </div>

      <button type="submit" name="actie" value="instellingen" class="ds-btn ds-btn-primair">Opslaan</button>
    </form>

    <div class="bh-inst-werking">
      <div class="bh-inst-werking-kop">Werking</div>
      <ul class="bh-inst-werking-lijst">
        <li>De sollicitant vult het e-mailadres in op het portaal.</li>
        <li>Staat dat adres in Authentik? Dan gaat het inloggen met het Paal 13-account.</li>
        <li>Staat het er niet in? Dan volgt een inloglink per e-mail (dat blijft altijd werken).</li>
        <li>Inloggen in het beheer verandert hier niet door.</li>
      </ul>
    </div>
  </div>
</div>



  <?php
    competenties_auto_setup();
    $a_url   = instelling('authentik.url', '');
    $a_token = instelling('authentik.token', '');
    // Bestaande koppeling (OIDC_ISSUER + authentik.api_token) tonen als hint
    $a_url_bestaand   = defined('OIDC_ISSUER') ? OIDC_ISSUER : '';
    $a_token_bestaand = instelling('authentik.api_token', '');
    $mapping = competentie_mapping();
    $geconf  = competentie_sync_klaar();
    $laatste_bulk = instelling('authentik.laatste_bulk_sync', '');
  ?>

  <div class="bh-instsectie bh-inst-los">
    <h3>Authentik-koppeling</h3>
    <div class="ds-veld-hint bh-inst-uitlegblok">
      <strong>Wat is dit?</strong> Het systeem haalt de competenties (vaardigheden) van terugkerende sollicitanten op uit Authentik.
      Authentik blijft de leidende bron — wijzigingen doe je dáár, niet hier.<br><br>
      <strong>Wat heb je nodig?</strong><br>
      1. De <strong>basis-URL</strong> van jullie Authentik (bijv. <code>https://auth.paal13.com</code>).<br>
      2. Een <strong>API-token</strong> van een Authentik service-account met leesrechten op gebruikers en groepen.
      Maak deze aan in Authentik onder <em>Directory → Tokens</em> (of een service-account met token).<br><br>
      Vul beide in, sla op, en klik op <strong>Test verbinding</strong> om te controleren of het werkt.
    </div>

    <form method="POST">
      <?= csrf_input() ?><input type="hidden" name="actie" value="instellingen">
      <div class="bh-veldrij">
        <div class="ds-veld">
          <label>Authentik basis-URL</label>
          <input type="url" name="inst[authentik.url]" value="<?= h($a_url) ?>" placeholder="<?= $a_url_bestaand ? h($a_url_bestaand) . ' (uit SSO-config)' : 'https://auth.paal13.com' ?>">
          <?php if (!$a_url && $a_url_bestaand): ?><div class="bh-inst-klein bh-tekst-ok">Gebruikt automatisch de SSO-URL: <?= h($a_url_bestaand) ?></div><?php endif; ?>
        </div>
        <?= bh_geheim_veld('inst[authentik.token]', 'authentik.token', 'API-token (service-account)', $a_token !== '',
              (!$a_token && $a_token_bestaand) ? 'Leeg: het systeem gebruikt het Authentik API-token hierboven.' : '') ?>
      </div>
      <div class="bh-inst-knoppen">
        <button type="submit" class="ds-btn ds-btn-primair">Opslaan</button>
        <?php if ($geconf): ?>
        <button type="submit" name="actie" value="authentik_test" class="ds-btn ds-btn-secundair">Test verbinding</button>
        <span class="bh-inst-stand bh-tekst-ok">Geconfigureerd</span>
        <?php else: ?>
        <span class="bh-inst-stand bh-tekst-grijs">Nog niet geconfigureerd</span>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <?php if ($geconf): ?>
  <div class="bh-instsectie bh-inst-los">
    <h3>Synchronisatie</h3>
    <p class="ds-veld-hint bh-mb">
      Competenties worden automatisch ververst: bij elke nieuwe aanmelding, dagelijks via de cron, en handmatig per sollicitant (ververs-knop in het dossier).
      <?php if ($laatste_bulk): ?><br>Laatste volledige sync: <strong><?= h(datum_nl(substr($laatste_bulk,0,10))) ?> <?= h(substr($laatste_bulk,11,5)) ?></strong>.<?php endif; ?>
    </p>
    <form method="POST" data-bevestig="Alle actieve sollicitanten opnieuw synchroniseren met Authentik? Hun competenties worden overschreven met wat er in Authentik staat.">
      <?= csrf_input() ?>
      <button type="submit" name="actie" value="authentik_bulk_sync" class="ds-btn ds-btn-secundair">Nu alle sollicitanten verversen</button>
    </form>
  </div>
  <?php endif; ?>

  <div class="bh-instsectie">
    <h3>Groep-indeling (competenties)</h3>
    <p class="ds-veld-hint bh-mb">
      Welke Authentik-groepen tellen als competentie, en hun rangorde. Per categorie tonen we alleen de <strong>hoogste</strong> rang die iemand heeft
      (bijv. wie Chauffeur is, hoeft niet ook als Opstapper Auto te verschijnen). Groepen die hier niet staan, worden genegeerd.
    </p>
    <div class="bh-tabel-wrap">
      <table class="bh-inst-comptabel">
        <thead>
          <tr>
            <th class="bh-cel">Categorie</th>
            <th class="bh-cel">Rangorde (laag → hoog)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($mapping as $cat => $def):
            $groepen = $def['groepen'] ?? [];
            asort($groepen);
          ?>
          <tr>
            <td class="bh-compcel"><?= icoon(bh_icoonnaam((string)($def['icoon'] ?? '')), 15) ?><?= h($def['label'] ?? $cat) ?>
              <div class="bh-inst-compsub"><?= ($def['ranking'] ?? true) ? 'met rangorde' : 'los (geen rangorde)' ?></div>
            </td>
            <td class="bh-cel">
              <?php foreach ($groepen as $groep => $rang): ?>
                <span class="bh-inst-groepchip">
                  <?php if ($def['ranking'] ?? true): ?><span class="bh-tekst-grijs"><?= (int)$rang ?>.</span> <?php endif; ?><?= h($groep) ?>
                </span>
              <?php endforeach; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="bh-inst-voetnoot">
      De indeling is voorgeconfigureerd op basis van jullie Authentik-groepen. Aanpassen kan later via een uitgebreider scherm; neem contact op als een groep verkeerd staat.
    </div>
  </div>

<?php elseif ($tab === 'bezetting'): ?>
  <?php
    $sjab = function_exists('bezetting_sjablonen') ? bezetting_sjablonen() : [];
    $comp_map = function_exists('competentie_mapping') ? competentie_mapping() : [];
  ?>
  <div class="ds-kaart">
    <div class="bh-kaartkop"><h2>Posten &amp; bezetting</h2></div>
    <div class="bh-kaartbody">
      <p class="bh-inst-intro">
        Welke rollen moeten er per post bemand zijn? De <a href="<?= APP_URL ?>/admin/dekking.php" class="bh-link-navy">Dekking-pagina</a>
        rekent hiermee uit of elke week te bemannen is. Rollen matchen op competentie + minimum-rang uit Authentik.
        <br><strong>De postcommandant valt hier buiten</strong> — die regelt het bestuur apart.
      </p>

      <div id="postenLijst"></div>
      <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="postToevoegen()">+ Post toevoegen</button>

      <form method="POST" class="bh-post-opslaan" onsubmit="return bezettingSerialiseer()">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="bezetting_opslaan">
        <input type="hidden" name="sjablonen_json" id="sjablonenJson">
        <button type="submit" class="ds-btn ds-btn-primair">Opslaan</button>
      </form>
      <form method="POST" class="bh-herstelform" data-bevestig="De standaardbezetting terugzetten? Je eigen posten en rollen verdwijnen daarmee.">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="bezetting_herstel">
        <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm"> Standaard herstellen</button>
      </form>
    </div>
  </div>

  <script>
  const COMP_CATS = <?= json_encode(array_map(fn($c) => ['label'=>$c['label'], 'groepen'=>$c['groepen'] ?? []], $comp_map)) ?>;
  let SJABLONEN = <?= json_encode($sjab) ?>;

  // Eigen escape: render() draait meteen, vóórdat beheer.js (bhTekst) onderaan de pagina laadt.
  const esc = s => String(s ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
  function catOpties(sel) {
    let h = '';
    for (const c in COMP_CATS) h += '<option value="'+esc(c)+'"'+(c===sel?' selected':'')+'>'+esc(COMP_CATS[c].label)+'</option>';
    return h;
  }
  function rangOpties(cat, sel) {
    const groepen = COMP_CATS[cat]?.groepen || {};
    let h = '';
    for (const g in groepen) h += '<option value="'+esc(groepen[g])+'"'+(parseInt(groepen[g])===parseInt(sel)?' selected':'')+'>'+esc(groepen[g])+' — '+esc(g)+' of hoger</option>';
    return h || '<option value="1">1</option>';
  }

  function render() {
    const box = document.getElementById('postenLijst');
    box.innerHTML = '';
    for (const ps in SJABLONEN) {
      const post = SJABLONEN[ps];
      const div = document.createElement('div');
      div.className = 'bh-post';
      let rollenHtml = '';
      (post.rollen || []).forEach((r, i) => {
        rollenHtml +=
          '<div class="bh-post-rol">' +
            '<input type="text" value="'+(r.label||'').replace(/"/g,'&quot;')+'" placeholder="Rol" oninput="SJABLONEN[\''+ps+'\'].rollen['+i+'].label=this.value" class="bh-post-rolnaam">' +
            '<select onchange="SJABLONEN[\''+ps+'\'].rollen['+i+'].categorie=this.value;SJABLONEN[\''+ps+'\'].rollen['+i+'].min_rang=1;render()" class="bh-post-categorie">'+catOpties(r.categorie)+'</select>' +
            '<select onchange="SJABLONEN[\''+ps+'\'].rollen['+i+'].min_rang=parseInt(this.value)" class="bh-post-rang">'+rangOpties(r.categorie, r.min_rang)+'</select>' +
            '<input type="number" min="1" max="20" value="'+(r.aantal||1)+'" oninput="SJABLONEN[\''+ps+'\'].rollen['+i+'].aantal=parseInt(this.value)||1" title="Aantal personen" class="bh-post-aantal">' +
            '<button type="button" class="ds-btn ds-btn-secundair ds-btn-sm bh-post-weg" onclick="rolWeg(\''+ps+'\','+i+')">Verwijderen</button>' +
          '</div>';
      });
      div.innerHTML =
        '<div class="bh-post-kop">' +
          '<input type="text" value="'+(post.icoon||'').replace(/"/g,'&quot;')+'" oninput="SJABLONEN[\''+ps+'\'].icoon=this.value" class="bh-post-icoon">' +
          '<input type="text" value="'+(post.naam||'').replace(/"/g,'&quot;')+'" oninput="SJABLONEN[\''+ps+'\'].naam=this.value" placeholder="Postnaam" class="bh-post-naam">' +
          '<label class="bh-post-regel"><input type="checkbox" '+(post.regel_geen_twee_aspiranten?'checked':'')+' onchange="SJABLONEN[\''+ps+'\'].regel_geen_twee_aspiranten=this.checked"> nooit 2 aspiranten samen</label>' +
          '<button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="postWeg(\''+ps+'\')">Post verwijderen</button>' +
        '</div>' +
        rollenHtml +
        '<button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="rolToevoegen(\''+ps+'\')">+ Rol</button>';
      box.appendChild(div);
    }
  }
  function rolToevoegen(ps) {
    const eersteCat = Object.keys(COMP_CATS)[0] || 'lifeguard';
    SJABLONEN[ps].rollen = SJABLONEN[ps].rollen || [];
    SJABLONEN[ps].rollen.push({sleutel:'rol'+Date.now(), label:'', categorie:eersteCat, min_rang:1, aantal:1});
    render();
  }
  function rolWeg(ps, i) { SJABLONEN[ps].rollen.splice(i,1); render(); }
  function postWeg(ps) {
  bhBevestig({ titel: 'Post verwijderen?', tekst: 'De rollen van deze post verdwijnen mee. Opslaan doe je daarna met de knop onderaan.', knop: 'Ja, verwijderen', gevaar: true })
    .then(function (ja) { if (ja) { delete SJABLONEN[ps]; render(); } });
}
  function postToevoegen() {
    const sleutel = 'post' + Date.now();
    SJABLONEN[sleutel] = {naam:'Nieuwe post', icoon:'locatie', rollen:[], regel_geen_twee_aspiranten:false};
    render();
  }
  function bezettingSerialiseer() {
    document.getElementById('sjablonenJson').value = JSON.stringify(SJABLONEN);
    return true;
  }
  render();
  </script>

<?php elseif ($tab === 'selectie'): ?>
<?php $crit = selectie_criteria(); ?>
<div class="ds-kaart">
  <div class="bh-kaartkop">
    <h2>Beoordelingscriteria selectiedag</h2>
    <div class="ds-veld-hint">De onderdelen waarop beoordelaars scoren, per test. Eén criterium per regel.</div>
  </div>
  <div class="bh-kaartbody">
    <form method="POST">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="selectie_criteria_opslaan">
      <?php
      // Een korte, vaste lijst iconen. Vrij typen gaf een emoji-veld; die kwam daarna
      // in het beoordeelscherm terecht en botste met de rest van de interface.
      $test_iconen = ['bliksem' => 'Bliksem (sport)', 'golf' => 'Golf (zwemmen)',
                      'reddingsboei' => 'Reddingsboei', 'strand' => 'Strand',
                      'ster' => 'Ster', 'schild' => 'Schild', 'klok' => 'Klok', 'punt' => 'Stip'];
      ?>
      <div class="bh-veldrij bh-inst-loonrij bh-inst-testrij">
        <?php foreach (['sport'=>'Sporttest', 'zwem'=>'Zwemtest'] as $tk => $default):
          $_ic = bh_icoonnaam((string)($crit[$tk]['icoon'] ?? ''), $tk === 'zwem' ? 'golf' : 'bliksem');
        ?>
        <fieldset class="bh-testveld">
          <legend><?= h($default) ?></legend>
          <div class="bh-veldrij">
            <div class="ds-veld bh-testicoon">
              <label for="icoon_<?= $tk ?>">Icoon <span class="bh-testicoon-nu"><?= icoon($_ic, 16) ?></span></label>
              <select id="icoon_<?= $tk ?>" name="icoon_<?= $tk ?>">
                <?php foreach ($test_iconen as $_n => $_l): ?>
                  <option value="<?= h($_n) ?>" <?= $_ic === $_n ? 'selected' : '' ?>><?= h($_l) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="ds-veld">
              <label for="naam_<?= $tk ?>">Naam van de test</label>
              <input type="text" id="naam_<?= $tk ?>" name="naam_<?= $tk ?>"
                     value="<?= h($crit[$tk]['naam'] ?? $default) ?>" placeholder="<?= h($default) ?>">
            </div>
          </div>
          <div class="ds-veld">
            <label for="criteria_<?= $tk ?>">Criteria</label>
            <textarea id="criteria_<?= $tk ?>" name="criteria_<?= $tk ?>" rows="8"
                      placeholder="Eén criterium per regel"><?php
              foreach ($crit[$tk]['criteria'] ?? [] as $c) echo h($c['label']) . "\n";
            ?></textarea>
            <p class="ds-veld-hint">Eén criterium per regel. Beoordelaars scoren elk criterium van 1 tot 5.</p>
          </div>
        </fieldset>
        <?php endforeach; ?>
      </div>
      <div class="bh-knoprij">
        <button type="submit" class="ds-btn ds-btn-primair">Opslaan</button>
      </div>
    </form>
    <form method="POST" class="bh-herstelform" data-bevestig="Terug naar de standaardcriteria uit het draaiboek? Je eigen criteria verdwijnen.">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="selectie_criteria_herstel">
      <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">
        <?= icoon('terug', 14) ?> Standaard uit het draaiboek herstellen
      </button>
    </form>
    <p class="ds-veld-hint bh-inst-scorenoot">
      Scores zijn indicatief en helpen bij de nabespreking — ze zijn geen harde afrekening. De observaties die beoordelaars
      op de dag noteren zijn het belangrijkst.
    </p>
  </div>
</div>

<?php elseif ($tab === 'bewaren'): ?>
<?php
  // Opschoon-logboek ophalen (laatste 20)
  $opschoon_log = [];
  try {
      if (function_exists('opschoon_auto_setup')) opschoon_auto_setup();
      $opschoon_log = db()->query("SELECT * FROM opschoon_log ORDER BY uitgevoerd_op DESC LIMIT 20")->fetchAll();
  } catch (\Exception $e) {}
  $laatste_opschoon = instelling('bewaar.laatste_run', '');
?>
<?php if (!instelling_bool('bewaar.actief')): ?>
<div class="ds-melding ds-melding-error bh-mb" role="status">
  <strong>Automatisch opschonen staat uit: er wordt niets opgeruimd.</strong>
  Zolang dit uitstaat, kloppen de privacyverklaring en de afwijzingsmail niet: die beloven dat gegevens na de
  termijnen hieronder verdwijnen. Zet het aan met het vinkje in de kaart hieronder. Let op: aanzetten wist bij
  de eerstvolgende dagelijkse run alles wat al over de termijn is, en dat is niet terug te draaien.
</div>
<?php endif; ?>
<div class="ds-kaart">
  <div class="bh-kaartkop"><h2>Bewaartermijnen</h2></div>
  <div class="bh-kaartbody">
    <div class="ds-melding ds-melding-info bh-inst-bewaaruitleg">
      <?= icoon('info', 16) ?>
      <span><strong>Automatisch opschonen.</strong> Volgens de AVG mag je persoonsgegevens niet langer bewaren dan nodig.
      Hieronder stel je in na hoeveel tijd welke gegevens worden opgeschoond. Dat geldt ook voor wie is aangenomen:
      het dossier van een oud-strandwacht wordt leeggemaakt na de termijn na het laatste seizoen. Loondocumenten zijn
      de uitzondering; die vernietigt de penningmeester met de hand. Een termijn op <strong>0</strong> betekent: niet
      automatisch opschonen. Behalve bij <strong>Gezondheidsgegevens</strong>: daar betekent 0 juist direct na het seizoen.</span>
    </div>

    <form method="POST">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="instellingen">

      <div class="ds-veld bh-inst-hoofdschakel">
        <label>
          <input type="hidden" name="inst[bewaar.actief]" value="0">
          <input type="checkbox" name="inst[bewaar.actief]" value="1" <?= instelling_bool('bewaar.actief') ? 'checked' : '' ?>>
          Automatisch opschonen inschakelen
        </label>
        <div class="ds-veld-hint">Zolang dit uitstaat, wordt er niets automatisch verwijderd of geanonimiseerd.</div>
      </div>

      <h3>Termijnen (in maanden, tenzij anders vermeld)</h3>
      <div class="bh-veldrij">
        <div class="ds-veld">
          <label>Afgewezen sollicitanten, in weken <?= bh_tip('Gerekend vanaf de afwijzing. Daarna worden persoonsgegevens, formulier, beschikbaarheid, notities en documenten gewist; de geanonimiseerde statistiek blijft. De Autoriteit Persoonsgegevens gaat uit van 4 weken.') ?></label>
          <input type="number" name="inst[bewaar.afgewezen_weken]" value="<?= instelling_int('bewaar.afgewezen_weken',4) ?>" min="0" max="52">
          <div class="ds-veld-hint">Daarna anonimiseren. Wie om een seintje vroeg, blijft tot dat verloopt.</div>
        </div>
        <div class="ds-veld">
          <label>Seintje bij de volgende werving <?= bh_tip('Zo lang geldt de toestemming die een afgewezen sollicitant gaf via "Stuur me een seintje". Daarna vervalt die en worden de gegevens geanonimiseerd. De tekst in de mail en het portaal zegt "maximaal een jaar": ga daar niet boven.') ?></label>
          <input type="number" name="inst[bewaar.seintje_mnd]" value="<?= instelling_int('bewaar.seintje_mnd',12) ?>" min="1" max="12">
          <div class="ds-veld-hint">Maximaal 12, want dat beloven we</div>
        </div>
      </div>
      <div class="bh-veldrij">
        <div class="ds-veld">
          <label>Oud-strandwachten, in jaren <?= bh_tip('Gerekend vanaf het einde van het laatste seizoen waarin iemand werkte. Daarna worden formulier, beschikbaarheid, notities, foto en documenten gewist. Loonheffing en kopie ID blijven staan tot de penningmeester ze op Loondossiers vernietigt; pas daarna wordt ook de naam geanonimiseerd. De AP gaat voor een personeelsdossier uit van 2 jaar.') ?></label>
          <input type="number" name="inst[bewaar.aangenomen_jaar]" value="<?= instelling_int('bewaar.aangenomen_jaar',2) ?>" min="0" max="10">
          <div class="ds-veld-hint">Na het laatste seizoen. De privacyverklaring noemt dit getal.</div>
        </div>
        <div class="ds-veld">
          <label>Gezondheidsgegevens <?= bh_tip('0 = na afloop van het seizoen, en meteen bij afwijzen of afhaken. Anders: zoveel maanden na ondertekening. Geldt voor de gezondheidsverklaring en de gezondheidsvragen in de intake, ook in de verzegelde PDF.') ?></label>
          <input type="number" name="inst[bewaar.gezondheid_mnd]" value="<?= instelling_int('bewaar.gezondheid_mnd',0) ?>" min="0" max="60">
          <div class="ds-veld-hint">0 = na het seizoen, of meteen bij afwijzing</div>
        </div>
      </div>
      <div class="bh-veldrij">
        <div class="ds-veld">
          <label>AI-invoertekst <?= bh_tip('De vrije beschikbaarheidstekst die naar de AI-dienst is gestuurd.') ?></label>
          <input type="number" name="inst[bewaar.ai_invoer_mnd]" value="<?= instelling_int('bewaar.ai_invoer_mnd',3) ?>" min="0" max="60">
          <div class="ds-veld-hint">Daarna wissen</div>
        </div>
        <div class="ds-veld">
          <label>Niet-voltooide aanmeldingen <?= bh_tip('Aanmeldingen die in de fase \'aangemeld\' bleven staan en nooit een formulier of beschikbaarheid kregen.') ?></label>
          <input type="number" name="inst[bewaar.onvoltooid_mnd]" value="<?= instelling_int('bewaar.onvoltooid_mnd',3) ?>" min="0" max="60">
          <div class="ds-veld-hint">Daarna verwijderen</div>
        </div>
      </div>
      <div class="bh-veldrij">
        <div class="ds-veld">
          <label>Berichtenlog (alle kanalen)</label>
          <input type="number" name="inst[bewaar.maillog_mnd]" value="<?= instelling_int('bewaar.maillog_mnd',12) ?>" min="0" max="60">
          <div class="ds-veld-hint">Daarna de logregels verwijderen</div>
        </div>
        <div class="ds-veld">
          <label>Inhoud van berichten <?= bh_tip('De volledige tekst van verstuurde berichten (leesbaar in het portaal en het berichtenlog). Daarna blijft alleen onderwerp + samenvatting over.') ?></label>
          <input type="number" name="inst[bewaar.maillog_body_mnd]" value="<?= instelling_int('bewaar.maillog_body_mnd',3) ?>" min="0" max="24">
          <div class="ds-veld-hint">Daarna de inhoud leegmaken</div>
        </div>
        <div class="ds-veld">
          <label>Losse bijlagen <?= bh_tip('Geüploade bestanden die aan geen enkel sjabloon meer hangen.') ?></label>
          <input type="number" name="inst[bewaar.bijlagen_mnd]" value="<?= instelling_int('bewaar.bijlagen_mnd',12) ?>" min="0" max="60">
          <div class="ds-veld-hint">Daarna verwijderen</div>
        </div>
      </div>
      <div class="bh-veldrij">
        <div class="ds-veld">
          <label>Bezoekersstatistiek landingspagina's <?= bh_tip('Aantallen bezoeken en aanmeldingen per landingspagina. Bevat geen persoonsgegevens.') ?></label>
          <input type="number" name="inst[bewaar.pagina_events_mnd]" value="<?= instelling_int('bewaar.pagina_events_mnd',13) ?>" min="0" max="60">
          <div class="ds-veld-hint">Daarna verwijderen (0 = bewaren)</div>
        </div>
        <div class="ds-veld">
          <label>Herkenning unieke bezoekers <?= bh_tip('Een dag-gebonden pseudoniem om unieke bezoekers te tellen. Wordt hierna gewist; de aantallen blijven staan.') ?></label>
          <input type="number" name="inst[bewaar.pagina_hash_dagen]" value="<?= instelling_int('bewaar.pagina_hash_dagen',30) ?>" min="0" max="365">
          <div class="ds-veld-hint">Dagen, daarna wissen</div>
        </div>
      </div>
      <div class="bh-veldrij">
        <div class="ds-veld">
          <label for="inst-audit-maanden">Auditlog (wie deed wat) <?= bh_tip('Het logboek van handelingen in het beheer, inclusief wie welk dossier inzag. Nodig om een melding of datalek te herleiden.') ?></label>
          <input type="number" id="inst-audit-maanden" name="inst[bewaar.audit_maanden]" value="<?= instelling_int('bewaar.audit_maanden',12) ?>" min="0" max="60">
          <div class="ds-veld-hint">Maanden, daarna verwijderen</div>
        </div>
        <div class="ds-veld">
          <label for="inst-foutenlog-dagen">Foutenlog <?= bh_tip('Technische foutmeldingen. Kunnen een e-mailadres of een adres van een pagina bevatten.') ?></label>
          <input type="number" id="inst-foutenlog-dagen" name="inst[bewaar.foutenlog_dagen]" value="<?= instelling_int('bewaar.foutenlog_dagen',90) ?>" min="0" max="730">
          <div class="ds-veld-hint">Dagen, daarna verwijderen</div>
        </div>
        <div class="ds-veld">
          <label for="inst-bankpas-dagen">Kopie bankpas <?= bh_tip('Gerekend vanaf het moment dat de penningmeester het loondossier op Verwerkt zet.') ?></label>
          <input type="number" id="inst-bankpas-dagen" name="inst[bewaar.bankpas_na_verwerking_dagen]" value="<?= instelling_int('bewaar.bankpas_na_verwerking_dagen',30) ?>" min="0" max="365">
          <div class="ds-veld-hint">Dagen na verwerking, daarna vernietigen</div>
        </div>
      </div>
      <div class="bh-veldrij">
        <div class="ds-veld">
          <label for="inst-loondoc-jaar">Loondocumenten, in jaren <?= bh_tip('Loonheffingsformulier en kopie identiteitsbewijs. De fiscale bewaarplicht loopt tot vijf jaar na het einde van het kalenderjaar waarin het dienstverband eindigde. Minder dan 5 kan niet.') ?></label>
          <input type="number" id="inst-loondoc-jaar" name="inst[bewaar.loondocumenten_jaar]" value="<?= max(5, instelling_int('bewaar.loondocumenten_jaar', 5)) ?>" min="5" max="10" aria-describedby="inst-loondoc-jaar-hint">
          <div class="ds-veld-hint" id="inst-loondoc-jaar-hint">Na het laatste seizoen. Daarna komt het dossier op de lijst "Te beoordelen voor vernietiging" op Loondossiers; het wordt nooit automatisch gewist.</div>
        </div>
      </div>
      <p class="ds-veld-hint bh-mb">
        <strong>Loondocumenten</strong> worden niet automatisch gewist: het systeem weet wel tot welk seizoen iemand
        hier werkte, maar niet wanneer het dienstverband eindigde. De penningmeester controleert dat in de
        loonadministratie en vernietigt ze met de hand, op het scherm Loondossiers.
      </p>

      <h3>Privacyverklaring</h3>
      <div class="ds-veld">
        <label for="inst-privacy-url">Adres van de privacyverklaring</label>
        <input type="text" id="inst-privacy-url" name="inst[privacy.url]" value="<?= h(instelling('privacy.url', '')) ?>"
               placeholder="<?= h(APP_URL . '/privacy.php') ?>" class="bh-inst-urlveld" inputmode="url" aria-describedby="inst-privacy-url-hint">
        <div class="ds-veld-hint" id="inst-privacy-url-hint">Leeg = de verklaring in dit systeem, die de termijnen hierboven automatisch noemt. Vul alleen een ander adres in (https, of een pad dat met / begint) als de verklaring op de website staat; die moet je dan zelf bijhouden. Het aanmeldformulier, de landingspagina's en de WordPress-plugin linken hiernaar.</div>
      </div>

      <h3>Meldingen en statistiek</h3>
      <div class="ds-veld">
        <label class="checkbox-label">
          <input type="hidden" name="inst[documenten.download_melding]" value="0">
          <input type="checkbox" name="inst[documenten.download_melding]" value="1" <?= instelling('documenten.download_melding', '1') === '1' ? 'checked' : '' ?> aria-describedby="inst-downloadmelding-hint">
          Mail naar het bestuur bij elke download van een loondocument
        </label>
        <div class="ds-veld-hint" id="inst-downloadmelding-hint">Standaard aan. Gaat naar het bestuursadres bij het downloaden van een kopie ID, bankpas of loonheffingsformulier, zodat onverwachte inzage opvalt. De mail bevat geen document.</div>
      </div>
      <div class="ds-veld">
        <label class="checkbox-label">
          <input type="hidden" name="inst[paginas.tracking]" value="0">
          <input type="checkbox" name="inst[paginas.tracking]" value="1" <?= instelling_bool('paginas.tracking', true) ? 'checked' : '' ?> aria-describedby="inst-tracking-hint">
          Bezoekersstatistiek op de landingspagina's en het aanmeldformulier
        </label>
        <div class="ds-veld-hint" id="inst-tracking-hint">Telt bezoeken en bij welke stap mensen afhaken, zonder cookies en zonder persoonsgegevens. Uit = er wordt niets meer geteld; wat al geteld is blijft volgens de termijn hierboven.</div>
      </div>

      <button type="submit" class="ds-btn ds-btn-primair">Opslaan</button>
    </form>
  </div>
</div>

<div class="ds-kaart bh-inst-logkaart">
  <div class="bh-kaartkop"><h2>Opschoon-logboek</h2></div>
  <div class="bh-kaartbody">
    <p class="ds-veld-hint bh-mb">
      De opschoning draait automatisch één keer per dag via de cron.
      <?php if ($laatste_opschoon): ?>
        Laatst uitgevoerd: <strong><?= h($laatste_opschoon) ?></strong>.
      <?php else: ?>
        Nog niet eerder uitgevoerd.
      <?php endif; ?>
    </p>
    <?php if ($opschoon_log): ?>
    <table class="bh-opschoontabel">
      <thead>
        <tr>
          <th class="bh-opschoon-datum">Datum</th><th>Categorie</th><th class="bh-opschoon-aantal">Aantal</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($opschoon_log as $r): ?>
        <tr>
          <td class="bh-opschoon-datum"><?= h($r['uitgevoerd_op']) ?></td>
          <td><?= h(str_replace('_',' ', $r['categorie'])) ?></td>
          <td class="bh-opschoon-aantal"><?= (int)$r['aantal'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <p class="bh-leegregel">Nog geen opschoonacties geregistreerd.</p>
    <?php endif; ?>
  </div>
</div>


<!-- Automatische taken. Stond eerder onderaan het mail-tabblad; de cron doet
     ook de AVG-opschoning hierboven, dus die hoort hier. -->
    <!-- ── Cronjob ───────────────────────────────────────────── -->
    <div class="bh-cron">
      <h3 class="bh-cron-kop">Automatische taken</h3>
      <?php
      $cron_token = instelling('cron.token');
      if (empty($cron_token)) {
          $cron_token = bin2hex(random_bytes(24));
          instelling_opslaan('cron.token', $cron_token);
      }
      $cron_pad = realpath(__DIR__ . '/../cron/run.php') ?: 'cron/run.php';
      ?>
      <p class="ds-veld-hint bh-mb">
        Alle automatische taken lopen via deze geplande taak: berichten en AI elke minuut, herinneringen en
        uitnodigen elk kwartier, Authentik en de AVG-opschoning dagelijks. Er is geen vangnet bij een
        paginabezoek: staat de taak stil, dan gaat er niets de deur uit.
      </p>
      <div class="bh-chiprij bh-mb">
        <?php foreach (cron_stand() as $g): ?>
          <?= bh_badge($g['label'] . ': ' . ($g['laatste'] ? date('d-m H:i', strtotime($g['laatste'])) : 'nog nooit'),
                       $g['status'] === 'ok' ? 'groen' : ($g['status'] === 'laat' ? 'oranje' : 'rood')) ?>
        <?php endforeach; ?>
      </div>
      <?php if ($mag_systeem): ?>
      <p class="ds-veld-hint">Plesk › Geplande taken › <strong>PHP-script uitvoeren</strong>, elke minuut (<code>* * * * *</code>):</p>
      <div class="bh-cronregel"><?= h($cron_pad) ?></div>
      <p class="ds-veld-hint">Kan de server geen PHP-script plannen, gebruik dan een opdracht (ook elke minuut):</p>
      <div class="bh-cronregel">
        curl -s -H "X-Cron-Token: <?= h($cron_token) ?>" "<?= h(APP_URL . '/cron/run.php') ?>" &gt; /dev/null 2&gt;&amp;1
      </div>
      <div class="bh-knoprij bh-mb">
        <form method="POST" data-bevestig="Alle automatische taken nu uitvoeren? Berichten in de wachtrij en herinneringen die klaarstaan gaan dan echt de deur uit.">
          <?= csrf_input() ?>
          <input type="hidden" name="actie" value="cron_nu">
          <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm"><?= icoon('ververs', 14) ?> Nu uitvoeren</button>
        </form>
        <form method="POST" data-bevestig="Een nieuw cron-token maken? Een geplande taak met curl werkt daarna niet meer tot je de nieuwe regel in Plesk zet. De PHP-script-variant heeft geen token nodig.">
          <?= csrf_input() ?>
          <input type="hidden" name="actie" value="cron_token_vernieuw">
          <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm"><?= icoon('slot', 14) ?> Nieuw token maken</button>
        </form>
      </div>
      <p class="ds-veld-hint bh-mb">Het PHP-script heeft geen token nodig (het draait op de server zelf). Bij curl gaat het token als header mee, zodat het niet in de toegangslogs belandt. Denk je dat het token is uitgelekt, of neemt iemand met toegang tot Plesk afscheid? Maak dan een nieuw token.</p>
      <?php else: ?>
      <p class="ds-veld-hint bh-mb"><?= bh_badge('Ingesteld', 'groen') ?> Het cron-token en de regel zelf ziet alleen wie Systeem mag bewerken.</p>
      <?php endif; ?>

      <?php
      // Wachtrij status
      $q_stats = ['ai'=>[], 'mail'=>[]];
      try {
          if (function_exists('ai_queue_auto_setup'))   { ai_queue_auto_setup();   $q_stats['ai']   = db()->query("SELECT status,COUNT(*) n FROM ai_queue   GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR); }
          if (function_exists('mail_queue_auto_setup')) { mail_queue_auto_setup(); $q_stats['mail'] = db()->query("SELECT status,COUNT(*) n FROM mail_queue GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR); }
      } catch (\Exception $e) {}
      $q_standen = ['wacht' => ['Wacht', 'blauw'], 'bezig' => ['Bezig', 'oranje'], 'klaar' => ['Klaar', 'groen'], 'fout' => ['Mislukt', 'rood']];
      ?>
      <?php foreach (['ai'=>'AI-analyses', 'mail'=>'Mails in de wachtrij'] as $sleutel => $label): if (!empty($q_stats[$sleutel])): ?>
      <div class="bh-cron-wachtrijblok">
        <div class="bh-cron-wachtrij"><?= h($label) ?></div>
        <div class="bh-chiprij">
          <?php foreach ($q_stats[$sleutel] as $st => $n): $_qs = $q_standen[$st] ?? [ucfirst((string)$st), 'grijs']; ?>
          <?= bh_badge($_qs[0] . ': ' . (int)$n, $_qs[1]) ?>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; endforeach; ?>
    </div>

<?php elseif ($tab === 'smtp'): ?>
  <?php
    $s_host    = instelling('smtp.host', defined('SMTP_HOST') ? SMTP_HOST : '');
    $s_port    = instelling('smtp.port', defined('SMTP_PORT') ? (string)SMTP_PORT : '587');
    $s_user    = instelling('smtp.user', defined('SMTP_USER') ? SMTP_USER : '');
    $s_encrypt = instelling('smtp.encrypt', defined('SMTP_ENCRYPT') ? SMTP_ENCRYPT : 'tls');
    $s_from    = instelling('smtp.from', defined('MAIL_FROM') ? MAIL_FROM : '');
    $s_fromnaam= instelling('smtp.from_naam', defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Reddingsbrigade Paal 13');
    $s_heeft_ww= instelling('smtp.pass', '') !== '' || (defined('SMTP_PASS') && SMTP_PASS !== '');
  ?>
  <div class="ds-kaart">
    <div class="bh-kaartkop"><h2>E-mailinstellingen (SMTP)</h2></div>
    <div class="bh-kaartbody">
      <p class="bh-inst-intro bh-inst-intro-vast">
        Hiermee verstuurt het systeem e-mails. Vul de gegevens van je mailserver in. Weet je ze niet? Die vind je bij je hostingprovider (Cloud86) onder de e-mailinstellingen van dit adres.
      </p>
      <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="smtp_opslaan">
        <div class="bh-smtpraster">
          <div class="bh-smtpraster-breed">
            <label class="ds-label">SMTP-server (host)</label>
            <input type="text" name="smtp_host" value="<?= h($s_host) ?>" placeholder="mail.paal13.com" class="ds-input">
          </div>
          <div>
            <label class="ds-label">Poort</label>
            <input type="text" name="smtp_port" value="<?= h($s_port) ?>" placeholder="587" class="ds-input">
          </div>
          <div>
            <label class="ds-label">Beveiliging</label>
            <select name="smtp_encrypt" class="ds-input">
              <option value="tls" <?= $s_encrypt==='tls'?'selected':'' ?>>STARTTLS (poort 587) — meest gebruikt</option>
              <option value="ssl" <?= $s_encrypt==='ssl'?'selected':'' ?>>SSL/TLS (poort 465)</option>
              <option value="none" <?= $s_encrypt==='none'?'selected':'' ?>>Geen (afgeraden)</option>
            </select>
          </div>
          <div>
            <label class="ds-label">Gebruikersnaam</label>
            <input type="text" name="smtp_user" value="<?= h($s_user) ?>" placeholder="website@paal13.com" class="ds-input">
          </div>
          <div>
            <?php $_ww_db = instelling('smtp.pass', '') !== ''; ?>
            <?= bh_geheim_veld('smtp_pass', 'smtp.pass', 'Wachtwoord', $s_heeft_ww,
                  (!$_ww_db && $s_heeft_ww) ? 'Nu uit config.php.' : '', $_ww_db) ?>
          </div>
          <div>
            <label class="ds-label">Afzender-adres</label>
            <input type="text" name="smtp_from" value="<?= h($s_from) ?>" placeholder="no-reply@paal13.com" class="ds-input">
          </div>
          <div>
            <label class="ds-label">Afzender-naam</label>
            <input type="text" name="smtp_from_naam" value="<?= h($s_fromnaam) ?>" placeholder="Reddingsbrigade Paal 13" class="ds-input">
          </div>
        </div>
        <div class="bh-smtp-voet">
          <button type="submit" class="ds-btn ds-btn-primair">Opslaan</button>
        </div>
      </form>
    </div>
  </div>

  <div class="ds-kaart">
    <div class="bh-kaartkop"><h2>Testmail versturen</h2></div>
    <div class="bh-kaartbody">
      <p class="bh-inst-intro bh-inst-intro-vast bh-inst-intro-14">
        Stuur een testmail om te controleren of je instellingen werken. Sla je wijzigingen eerst op. Lukt het niet, dan zie je de reden hier én in <a href="<?= APP_URL ?>/admin/logboek.php?bron=fout">het logboek</a>.
      </p>
      <form method="POST" class="bh-inst-regelform">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="smtp_test">
        <div class="bh-inst-testnaar">
          <label class="ds-label">Stuur test naar</label>
          <input type="email" name="smtp_test_naar" value="<?= h(huidige_beheerder()['email'] ?? '') ?>" placeholder="jouw@email.nl" class="ds-input">
        </div>
        <button type="submit" class="ds-btn ds-btn-secundair">Verstuur testmail</button>
      </form>
    </div>
  </div>

<?php elseif ($tab === 'api'): ?>
  <?php
    $api_sleutel = instelling('api.sleutel', '');
    $api_origins = instelling('api.origins', '');
    $api_basis_url = APP_URL . '/api';
  ?>
  <div class="bh-instsectie">
    <h2 class="bh-inst-tabkop">Website &amp; API</h2>
    <p class="bh-inst-tabintro">
      Hiermee koppelt de WordPress-plugin op de website met dit systeem: de plugin haalt de
      seizoensinfo op en stuurt aanmeldingen hierheen. De verbinding is beveiligd met een geheime sleutel.
    </p>

    <div class="bh-subkaart">
      <div class="bh-inst-blokkop">API-sleutel</div>
      <?php if ($api_sleutel && !$mag_systeem): ?>
        <p class="ds-veld-hint bh-mb"><?= bh_badge('Ingesteld', 'groen') ?> De sleutel zelf, en een nieuwe maken, is er alleen voor wie Systeem mag bewerken.</p>
      <?php elseif ($api_sleutel): ?>
        <div class="bh-inst-sleutelrij">
          <input type="text" readonly value="<?= h($api_sleutel) ?>" id="apiSleutel"
                 class="bh-inst-sleutel">
          <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('apiSleutel').value);this.textContent='Gekopieerd!'">Kopieer</button>
        </div>
        <p class="bh-inst-uitleg bh-inst-uitleg-10">
          Zet deze sleutel in de WordPress-plugin. Houd 'm geheim — wie de sleutel heeft, kan aanmeldingen versturen.
        </p>
      <?php else: ?>
        <p class="bh-inst-geensleutel">Er is nog geen sleutel.<?= $mag_systeem ? ' Genereer er een om de koppeling te activeren.' : ' Vraag een beheerder er een te maken.' ?></p>
      <?php endif; ?>
      <?php if ($mag_systeem): ?>
      <form method="post" data-bevestig="<?= $api_sleutel
          ? 'Een nieuwe sleutel maakt de oude meteen ongeldig. Tot je de nieuwe in de WordPress-plugin zet, komen er geen aanmeldingen meer binnen.'
          : 'Een sleutel genereren zet de koppeling met de website aan.' ?>">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="api_sleutel_genereer">
        <button type="submit" class="ds-btn ds-btn-primair ds-btn-sm"><?= $api_sleutel ? 'Nieuwe sleutel genereren' : 'Sleutel genereren' ?></button>
      </form>
      <?php endif; ?>
    </div>

    <form method="post">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="api_opslaan">
      <label class="bh-inst-veldkop">Toegestane websites (CORS)</label>
      <p class="bh-inst-uitleg bh-inst-uitleg-6">
        Eén per regel. De hoofdsite (paal13.com) is altijd toegestaan. Voeg hier extra domeinen toe als de plugin op een ander adres draait.
      </p>
      <textarea name="api_origins" rows="3" class="bh-inst-origins" placeholder="https://paal13.com"><?= h($api_origins) ?></textarea>
      <div class="bh-api-voet"><button type="submit" class="ds-btn ds-btn-primair ds-btn-sm">Opslaan</button></div>
    </form>

    <div class="bh-inst-pluginblok">
      <div class="bh-inst-pluginblok-kop">Voor de plugin-instellingen</div>
      <table class="bh-inst-plugintabel">
        <tr><td class="bh-cel-zacht">API-basis-URL</td><td class="bh-code"><?= h($api_basis_url) ?></td></tr>
        <tr><td class="bh-cel-zacht">Seizoensinfo</td><td class="bh-code">GET <?= h($api_basis_url) ?>/seizoen.php</td></tr>
        <tr><td class="bh-cel-zacht">Aanmelden</td><td class="bh-code">POST <?= h($api_basis_url) ?>/aanmelden.php</td></tr>
      </table>
    </div>
  </div>

<?php elseif ($tab === 'berichten'): ?>
  <?php
    $sms_gw = instelling('sms.gateway', '');
  ?>
  <div class="bh-instsectie">
    <h2 class="bh-inst-tabkop">SMS, WhatsApp &amp; meldingen</h2>
    <p class="bh-inst-tabintro">
      Stuur meldingen naast e-mail ook via SMS of WhatsApp. Beide kanalen zijn los in- en uit te
      schakelen. Onderaan bepaal je per soort melding via welke kanalen die verstuurd wordt.
    </p>

    <form method="post">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="berichten_opslaan">

      <?php if (!$mag_systeem): ?>
      <p class="bh-inst-uitleg bh-inst-uitleg-12">
        SMS staat <strong><?= instelling('sms.actief', '0') === '1' ? 'aan' : 'uit' ?></strong>, WhatsApp staat
        <strong><?= instelling('wa.actief', '0') === '1' ? 'aan' : 'uit' ?></strong>. De koppeling met die diensten
        (sleutels en verzendmethode) stelt systeembeheer in.
      </p>
      <?php else: ?>
      <!-- SMS -->
      <div class="bh-subkaart">
        <label class="inst-toggle bh-inst-kanaalschakel">
          <input type="checkbox" name="sms_actief" value="1" <?= instelling('sms.actief','0')==='1'?'checked':'' ?>>
          SMS inschakelen
        </label>

        <label class="ds-label">Gateway (verzendmethode)</label>
        <select name="sms_gateway" id="smsGateway" onchange="smsGatewayToon()" class="bh-sms-gateway">
          <option value="">— kies —</option>
          <option value="eigen_telefoon" <?= $sms_gw==='eigen_telefoon'?'selected':'' ?>>Eigen telefoon / VoIP (goedkoopst)</option>
          <option value="http_gateway" <?= $sms_gw==='http_gateway'?'selected':'' ?>>Generieke HTTP-gateway</option>
          <option value="easysendsms" <?= $sms_gw==='easysendsms'?'selected':'' ?>>EasySendSMS (pay-as-you-go)</option>
          <option value="messagebird" <?= $sms_gw==='messagebird'?'selected':'' ?>>MessageBird / Bird (NL)</option>
        </select>
        <p class="bh-inst-uitleg bh-inst-uitleg-12">
          <strong>Goedkoopst:</strong> "Eigen telefoon / VoIP" — je gebruikt je eigen (VoIP- of Android-)nummer met
          een app of dienst die een simpel webadres aanbiedt om een SMS te sturen. Je betaalt dan alleen je eigen
          bundel, geen kosten per bericht. De andere opties rekenen per bericht (ca. 1 cent), maar zijn kant-en-klaar.
        </p>

        <div class="bh-sms-afzenderveld">
          <label class="ds-label">Afzendernaam</label>
          <input type="text" name="sms_afzender" value="<?= h(instelling('sms.afzender','Paal13')) ?>" class="bh-sms-afzender" maxlength="11">
          <span class="ds-veld-hint">max 11 tekens</span>
        </div>

        <!-- HTTP / eigen telefoon velden -->
        <div class="sms-veld" data-voor="eigen_telefoon http_gateway" hidden>
          <label class="ds-label bh-mt">Webadres (URL) van de gateway</label>
          <input type="text" name="sms_http_url" value="<?= h(instelling('sms.http_url','')) ?>" class="bh-inst-volbreed" placeholder="https://.../send?to={nummer}&text={tekst}">
          <p class="bh-sms-plekhouders">Je mag <code>{nummer}</code> en <code>{tekst}</code> als plek-houders gebruiken. Anders worden ze als POST-velden meegestuurd.</p>
          <div class="bh-sms-httprij">
            <div>
              <label class="ds-label">Methode</label>
              <select name="sms_http_method" class="bh-sms-methode">
                <option value="POST" <?= instelling('sms.http_method','POST')==='POST'?'selected':'' ?>>POST</option>
                <option value="GET" <?= instelling('sms.http_method','POST')==='GET'?'selected':'' ?>>GET</option>
              </select>
            </div>
            <div class="bh-sms-token">
              <?= bh_geheim_veld('sms_http_token', 'sms.http_token', 'Token (optioneel)', instelling('sms.http_token', '') !== '') ?>
            </div>
          </div>
        </div>

        <!-- EasySendSMS -->
        <div class="sms-veld" data-voor="easysendsms" hidden>
          <?= bh_geheim_veld('sms_easysendsms_key', 'sms.easysendsms_key', 'EasySendSMS API-sleutel', instelling('sms.easysendsms_key', '') !== '') ?>
        </div>

        <!-- MessageBird -->
        <div class="sms-veld" data-voor="messagebird" hidden>
          <?= bh_geheim_veld('sms_messagebird_key', 'sms.messagebird_key', 'MessageBird API-sleutel', instelling('sms.messagebird_key', '') !== '') ?>
        </div>
      </div>

      <!-- WhatsApp -->
      <div class="bh-subkaart">
        <label class="inst-toggle bh-inst-kanaalschakel bh-inst-kanaalschakel-10">
          <input type="checkbox" name="wa_actief" value="1" <?= instelling('wa.actief','0')==='1'?'checked':'' ?>>
          WhatsApp inschakelen (via WhatsReach)
        </label>
        <div class="bh-wa-risico">
          <strong>Let op:</strong> WhatsReach werkt buiten de officiële WhatsApp Business API om. Dit brengt een
          reëel risico met zich mee dat het gebruikte WhatsApp-nummer door WhatsApp geblokkeerd wordt. Gebruik bij
          voorkeur een apart nummer, en stuur alleen naar mensen die contact via WhatsApp verwachten. Dit kanaal
          staat daarom standaard uit.
        </div>
        <?= bh_geheim_veld('wa_api_sleutel', 'wa.api_sleutel', 'WhatsReach API-sleutel (X-API-Key)', instelling('wa.api_sleutel', '') !== '') ?>
        <label class="ds-label">Base URL</label>
        <input type="text" name="wa_base_url" value="<?= h(instelling('wa.base_url','https://api.whatsreach.co/api/v1')) ?>" class="bh-inst-volbreed bh-wa-baseurl">
        <label class="ds-label">Afzendernummer <span class="bh-label-optioneel">(optioneel — bij meerdere gekoppelde apparaten)</span></label>
        <input type="text" name="wa_afzender_nummer" value="<?= h(instelling('wa.afzender_nummer','')) ?>" class="bh-inst-volbreed" placeholder="+31612345678">
        <p class="bh-wa-nummeruitleg">Het WhatsApp-nummer waarvandaan berichten verstuurd worden (het "from"-apparaat uit WhatsReach). Laat leeg als je maar één apparaat hebt gekoppeld.</p>
      </div>
      <?php endif; ?>

      <!-- Kanaal per melding -->
      <div class="bh-subkaart">
        <div class="bh-meldkanaal-kop">Welke melding via welk kanaal?</div>
        <p class="bh-inst-uitleg bh-inst-uitleg-12">
          <strong>E-mail</strong> = alleen e-mail. <strong>WhatsApp/SMS</strong> = kort bericht via het kanaal dat de sollicitant koos (standaard WhatsApp; SMS als WhatsApp niet kan);
          lukt dat niet, dan alsnog per e-mail. <strong>Beide</strong> = e-mail én kort bericht. <strong>Uit</strong> = niet versturen.
          Ouders en het bestuur krijgen altijd e-mail.
          <?php if ($mag_sjablonen): ?>Teksten bewerken: <a href="<?= APP_URL ?>/admin/mail_templates.php" class="bh-let-op">Sjablonen</a>.<?php endif; ?>
        </p>
        <?php if (function_exists('meldingen_per_categorie')): $kmodi = meldingen_kanaalmodi(); foreach (meldingen_per_categorie() as $ck => $typen): if ($ck === 'handmatig') continue; ?>
        <div class="bh-meldkanaal-groep"><?= h(meldingen_categorieen()[$ck] ?? $ck) ?></div>
        <table class="bh-meldkanaaltabel">
          <?php foreach ($typen as $type => $mdef): $mm = melding_kanaalmodus($type); ?>
          <tr>
            <td><?php if ($mag_sjablonen): ?><a href="<?= APP_URL ?>/admin/mail_templates.php?slug=<?= h($type) ?>" class="bh-meldkanaal-naam"><?= h($mdef['naam']) ?></a><?php else: ?><span class="bh-meldkanaal-naam"><?= h($mdef['naam']) ?></span><?php endif; ?>
              <div class="ds-veld-hint"><?= h($mdef['beschrijving'] ?? '') ?></div></td>
            <td class="bh-meldkanaal-keuze">
              <select name="notif[<?= h($type) ?>]">
                <?php foreach ($kmodi as $mk => $ml): if (!$mdef['kort_toegestaan'] && $mk !== 'email' && $mk !== 'uit') continue; ?>
                <option value="<?= $mk ?>" <?= $mm === $mk ? 'selected' : '' ?>><?= h($ml) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <?php endforeach; ?>
        </table>
        <?php endforeach; endif; ?>
      </div>

      <button type="submit" class="ds-btn ds-btn-primair">Opslaan</button>
    </form>

    <!-- Test -->
    <div class="bh-testbericht">
      <div class="bh-inst-blokkop">Testbericht sturen</div>
      <form method="post" class="bh-testbericht-form">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="bericht_test">
        <div>
          <label class="ds-label">Telefoonnummer</label>
          <input type="text" name="test_nummer" placeholder="06..." class="bh-testbericht-nummer">
        </div>
        <div>
          <label class="ds-label">E-mail (terugval)</label>
          <input type="email" name="test_email" value="<?= h($_SESSION['bhd_email'] ?? '') ?>" class="bh-testbericht-email">
        </div>
        <div>
          <label class="ds-label">Via</label>
          <select name="test_kanaal" class="bh-testbericht-kanaal">
            <option value="wa">WhatsApp</option>
            <option value="sms">SMS</option>
            <option value="email">E-mail</option>
          </select>
        </div>
        <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Test versturen</button>
      </form>
    </div>
  </div>

  <script>
    function smsGatewayToon() {
      var gw = document.getElementById('smsGateway').value;
      document.querySelectorAll('.sms-veld').forEach(function(el){
        el.hidden = !el.dataset.voor.split(' ').includes(gw);
      });
    }
    smsGatewayToon();
  </script>

<?php endif; ?>

</div>
</div>

<script>
// Loon-voorbeeld live bijwerken: uurloon × uren × factor lifeguard × (1 + VT), met de getallen van
// het gekozen jaar (data-* op de tabel), dus hetzelfde als loon_dagloon() op de server.
document.addEventListener('input', function (e) {
  const input = e.target.closest ? e.target.closest('input[data-voorbeeld]') : null;
  if (!input) return;
  const tab = input.closest('[data-loonvoorbeeld]');
  const el  = document.getElementById(input.dataset.voorbeeld);
  if (!tab || !el) return;
  const v = parseFloat(String(input.value).replace(',', '.'));
  if (!v || isNaN(v)) { el.textContent = '—'; return; }
  const dl = Math.round(v * parseFloat(tab.dataset.uren) * parseFloat(tab.dataset.factor) * (1 + parseFloat(tab.dataset.vt) / 100) * 100) / 100;
  el.textContent = '€' + dl.toFixed(2).replace('.', ',');
});

// Delay veld tonen/verbergen
document.querySelector('select[name="inst[invite.modus]"]')?.addEventListener('change', function() {
  const df = document.getElementById('delay_veld');
  if (df) df.hidden = this.value !== 'auto';
});
</script>

<?php require __DIR__ . '/layout_bottom.php'; ?>