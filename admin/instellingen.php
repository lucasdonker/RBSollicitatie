<?php
require_once __DIR__ . '/../includes/functions.php';
if (file_exists(__DIR__ . '/../includes/ai_analyse.php')) {
    require_once __DIR__ . '/../includes/ai_analyse.php';
}

// Fallback: garandeer dat ai_prompt_default() altijd bestaat (zelfs als ai_analyse.php ontbreekt)
if (!function_exists('ai_prompt_default')) {
    function ai_prompt_default(): string {
        return "Je bent een kritisch selectiecommissielid voor Reddingsbrigade Paal 13, een professionele reddingsbrigade. Je taak is een eerlijk, realistisch assessment te schrijven — niet bemoedigend maar objectief. Wees bereid negatieve conclusies te trekken als de gegevens daartoe aanleiding geven.

SEIZOEN: {{seizoen_naam}} ({{seizoen_weken_totaal}} weken totaal)

ANONIEME SOLLICITANTGEGEVENS:
- Leeftijdscategorie: {{leeftijdscategorie}}
- Opleiding: {{opleiding}}
- Diploma's en certificaten: {{diplomas}}
- Werkervaring: {{werkervaring}}
- Motivatie (lifeguard): {{motivatie_lifeguard}}
- Motivatie (strand): {{motivatie_strand}}
- Beschikbaarheid: {{beschikbaarheid}} ({{aantal_weken_beschikbaar}} van {{seizoen_weken_totaal}} weken)
- Bijzonderheden: {{bijzonderheden}}

Schrijf een beknopt assessment in dit exacte format (geen inleiding, direct beginnen):

**Geschiktheid:** [★ t/m ★★★★★] — [één eerlijke zin: waarom wel of niet geschikt, zonder omhaal]

**Bijdrage aan de groep:** [2-3 zinnen: wat voegt deze persoon concreet toe aan het team? Denk aan: leeftijd t.o.v. groep, werkstijl die je verwacht op basis van opleiding/motivatie, ervaring die anderen missen of juist al hebben. Wees realistisch — ook 'weinig toegevoegde waarde' is een geldige conclusie.]

**Toekomstperspectief:** [1-2 zinnen: hoeveel seizoenen verwacht je dat deze persoon inzetbaar is? Onderbouw met opleiding, leeftijd, motivatie.]

**Aandachtspunten:** [1-3 concrete zorgen of vraagtekens op basis van de gegevens. Als er geen zijn: vermeld dat expliciet. Verzin geen zorgen, maar sla ook niets over.]

**Advies:** [Uitnodigen ✓ / Twijfel ⚠️ / Niet uitnodigen ✗] — [één zin waarom, wees direct]";
    }
}

// Lokale fallback default prompt — gebruikt als ai_analyse.php niet beschikbaar is
if (!function_exists('ai_prompt_default')) {
    function ai_prompt_default(): string {
        return "Je bent een kritisch selectiecommissielid voor Reddingsbrigade Paal 13, een professionele reddingsbrigade. Je taak is een eerlijk, realistisch assessment te schrijven — niet bemoedigend maar objectief. Wees bereid negatieve conclusies te trekken als de gegevens daartoe aanleiding geven.

SEIZOEN: {{seizoen_naam}} ({{seizoen_weken_totaal}} weken totaal)

ANONIEME SOLLICITANTGEGEVENS:
- Leeftijdscategorie: {{leeftijdscategorie}}
- Opleiding: {{opleiding}}
- Diploma's en certificaten: {{diplomas}}
- Werkervaring: {{werkervaring}}
- Motivatie (lifeguard): {{motivatie_lifeguard}}
- Motivatie (strand): {{motivatie_strand}}
- Beschikbaarheid: {{beschikbaarheid}} ({{aantal_weken_beschikbaar}} van {{seizoen_weken_totaal}} weken)
- Bijzonderheden: {{bijzonderheden}}

Schrijf een beknopt assessment in dit exacte format (geen inleiding, direct beginnen):

**Geschiktheid:** [★ t/m ★★★★★] — [één eerlijke zin: waarom wel of niet geschikt, zonder omhaal]

**Bijdrage aan de groep:** [2-3 zinnen: wat voegt deze persoon concreet toe aan het team? Denk aan: leeftijd t.o.v. groep, werkstijl die je verwacht op basis van opleiding/motivatie, ervaring die anderen missen of juist al hebben. Wees realistisch — ook 'weinig toegevoegde waarde' is een geldige conclusie.]

**Toekomstperspectief:** [1-2 zinnen: hoeveel seizoenen verwacht je dat deze persoon inzetbaar is? Onderbouw met opleiding, leeftijd, motivatie.]

**Aandachtspunten:** [1-3 concrete zorgen of vraagtekens op basis van de gegevens. Als er geen zijn: vermeld dat expliciet. Verzin geen zorgen, maar sla ook niets over.]

**Advies:** [Uitnodigen ✓ / Twijfel ⚠️ / Niet uitnodigen ✗] — [één zin waarom, wees direct]";
    }
}
$pagina_titel = 'Instellingen';
$actieve_nav  = 'instellingen';
vereis_recht('instellingen', 'lezen');

$db = db();
// Triggers auto-setup van alle tabellen
instellingen_laden();

$tab = $_GET['tab'] ?? 'algemeen';

// ── Opslaan ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    vereis_recht('instellingen', 'lezen');

    $actie = $_POST['actie'] ?? '';

    if ($actie === 'instellingen') {
        $velden = $_POST['inst'] ?? [];

        // Logo upload (overschrijft URL als bestand geüpload is)
        foreach (['logo'=>'site.logo_url', 'favicon'=>'site.favicon_url'] as $veld => $instelling_key) {
            if (!empty($_FILES[$veld.'_upload']['tmp_name']) && $_FILES[$veld.'_upload']['error'] === 0) {
                $bron = $_FILES[$veld.'_upload'];
                if ($bron['size'] < 2*1024*1024) {  // 2MB max
                    $ext = strtolower(pathinfo($bron['name'], PATHINFO_EXTENSION));
                    $toegestaan = $veld === 'favicon' ? ['png','ico','svg'] : ['png','jpg','jpeg','webp','svg'];
                    if (in_array($ext, $toegestaan, true)) {
                        $dir = __DIR__ . '/../uploads/branding/';
                        if (!is_dir($dir)) @mkdir($dir, 0755, true);
                        $fn = $veld . '_' . time() . '.' . $ext;
                        if (move_uploaded_file($bron['tmp_name'], $dir . $fn)) {
                            $velden[$instelling_key] = '/uploads/branding/' . $fn;
                        }
                    }
                }
            }
        }

        foreach ($velden as $sleutel => $waarde) {
            // Saniteer sleutel (alleen letters, punten, underscores)
            if (!preg_match('/^[a-z0-9._]+$/', $sleutel)) continue;
            instelling_opslaan($sleutel, trim($waarde));
        }
        flash('succes', 'Instellingen opgeslagen.');
        redirect(APP_URL . '/admin/instellingen.php?tab=' . $tab);
    }

    if ($actie === 'authentik_test') {
        $res = authentik_test();
        flash($res['ok'] ? 'succes' : 'fout', $res['melding']);
        redirect(APP_URL . '/admin/instellingen.php?tab=authentik');
    }

    if ($actie === 'api_sleutel_genereer') {
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
        // SMS
        instelling_opslaan('sms.actief', !empty($_POST['sms_actief']) ? '1' : '0');
        instelling_opslaan('sms.gateway', $_POST['sms_gateway'] ?? '');
        instelling_opslaan('sms.afzender', trim($_POST['sms_afzender'] ?? 'Paal13'));
        instelling_opslaan('sms.http_url', trim($_POST['sms_http_url'] ?? ''));
        instelling_opslaan('sms.http_method', $_POST['sms_http_method'] ?? 'POST');
        instelling_opslaan('sms.http_token', trim($_POST['sms_http_token'] ?? ''));
        instelling_opslaan('sms.easysendsms_key', trim($_POST['sms_easysendsms_key'] ?? ''));
        instelling_opslaan('sms.messagebird_key', trim($_POST['sms_messagebird_key'] ?? ''));
        // WhatsApp (WhatsReach)
        instelling_opslaan('wa.actief', !empty($_POST['wa_actief']) ? '1' : '0');
        instelling_opslaan('wa.api_sleutel', trim($_POST['wa_api_sleutel'] ?? ''));
        instelling_opslaan('wa.base_url', trim($_POST['wa_base_url'] ?? '') ?: 'https://api.whatsreach.co/api/v1');
        instelling_opslaan('wa.afzender_nummer', trim($_POST['wa_afzender_nummer'] ?? ''));
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
        // Wachtwoord alleen bijwerken als er een nieuwe is ingevuld (anders behouden)
        $nieuw_ww = $_POST['smtp_pass'] ?? '';
        if ($nieuw_ww !== '' && $nieuw_ww !== '••••••••') {
            instelling_opslaan('smtp.pass', $nieuw_ww);
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

    if ($actie === 'loon_tarief') {
        $periode    = $_POST['periode'] === 'jun' ? 'jun' : 'jul_sep';
        $leeftijden = $_POST['loon'] ?? [];
        $stmt = $db->prepare("INSERT INTO loon_tarieven (leeftijd,periode,min_uurloon) VALUES (?,?,?) ON DUPLICATE KEY UPDATE min_uurloon=VALUES(min_uurloon)");
        foreach ($leeftijden as $lft => $loon) {
            $loon_val = (float)str_replace(',', '.', $loon);
            if ($loon_val > 0) $stmt->execute([(int)$lft, $periode, $loon_val]);
        }
        flash('succes', 'Loonuurtarieven opgeslagen.');
        redirect(APP_URL . '/admin/instellingen.php?tab=loon');
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
                    'icoon'  => mb_substr(trim($post['icoon'] ?? '•'), 0, 4),
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
            if ($lijst) $criteria[$tk] = ['naam' => mb_substr($naam, 0, 40), 'icoon' => mb_substr($icoon, 0, 4), 'criteria' => $lijst];
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
$tarieven_jun    = $db->query("SELECT leeftijd, min_uurloon FROM loon_tarieven WHERE periode='jun'    ORDER BY leeftijd")->fetchAll(\PDO::FETCH_KEY_PAIR);
$tarieven_jul    = $db->query("SELECT leeftijd, min_uurloon FROM loon_tarieven WHERE periode='jul_sep' ORDER BY leeftijd")->fetchAll(\PDO::FETCH_KEY_PAIR);

require __DIR__ . '/layout_top.php';
?>
<style>
.inst-grid { display:grid; grid-template-columns:220px 1fr; gap:18px; align-items:start; }
.inst-nav a { display:flex; align-items:center; gap:9px; padding:10px 14px; border-radius:8px; text-decoration:none; color:var(--tekst-zacht); font-size:13px; font-weight:500; transition:all .15s; margin-bottom:3px; }
.inst-nav a:hover { background:var(--grijs-bg); color:var(--tekst); }
.inst-nav a.actief { background:var(--navy); color:#fff; }
.inst-nav a svg { width:16px; height:16px; flex-shrink:0; }
.inst-sectie { border-bottom:1px solid var(--rand); padding-bottom:20px; margin-bottom:20px; }
.inst-sectie:last-child { border:none; margin:0; padding:0; }
.inst-sectie h3 { font-size:13px; font-weight:600; color:var(--navy); margin-bottom:12px; }
[data-theme="dark"] .inst-sectie h3 { color:var(--tekst); }
.loon-tabel-inst { width:100%; border-collapse:collapse; font-size:13px; }
.loon-tabel-inst th { background:var(--grijs-bg); padding:8px 12px; text-align:left; font-size:11px; font-weight:600; text-transform:uppercase; color:var(--tekst-zacht); border-bottom:1px solid var(--rand); }
.loon-tabel-inst td { padding:7px 12px; border-bottom:1px solid var(--rand); }
.loon-input { width:90px; text-align:right; font-family:monospace; }
.preview-loon { font-size:11px; color:var(--grijs); }
</style>

<div class="inst-grid">

<!-- Navigatie links -->
<div>
  <div class="card">
    <div class="card-body" style="padding:10px" class="inst-nav">
      <div class="inst-nav">
        <?php $nav_groepen = [
          'Organisatie' => [
            ['algemeen', 'Algemeen',            '<circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/>'],
            ['functies', 'Functies & factoren', '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
          ],
          'Selectie & seizoen' => [
            ['uitnodiging','Uitnodiging & automatisering', '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.15 12 19.79 19.79 0 0 1 1.08 3.4 2 2 0 0 1 3.06 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.09 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21 16"/>'],
            ['selectie', 'Beoordelingscriteria', '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>'],
            ['bezetting','Posten & bezetting',   '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>'],
          ],
          'Loon & planning' => [
            ['loon',     'Vakantietoeslag & lonen', '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>'],
          ],
          'Communicatie' => [
            ['mail',     'Mail & herinneringen',  '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>'],
            ['smtp',     'E-mailserver (SMTP)',   '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 5L2 7"/>'],
          ],
          'AI & koppelingen' => [
            ['ai',       'AI-analyse',            '<circle cx="12" cy="12" r="3"/><path d="M12 1v6m0 10v6m11-11h-6m-10 0H1"/>'],
            ['authentik', 'Authentik & competenties', '<path d="M12 2l7 4v6c0 5-3.5 8-7 10-3.5-2-7-5-7-10V6z"/>'],
            ['sso',      'Authentik SSO-login',   '<path d="M12 1l3 6 6 .75-4.12 4.62L18 19l-6-3.25L6 19l1.13-6.63L3 7.75 9 7z"/>'],
          ],
          'Koppelingen' => [
            ['api', 'Website & API', '<path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/>'],
            ['berichten', 'SMS, WhatsApp & meldingen', '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'],
          ],
          'Privacy' => [
            ['bewaren', 'Bewaartermijnen (AVG)', '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'],
          ],
        ]; ?>
        <?php foreach ($nav_groepen as $groep => $items): ?>
        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--grijs);padding:10px 14px 4px"><?= h($groep) ?></div>
        <?php foreach ($items as [$t, $l, $svg]): ?>
        <a href="?tab=<?= $t ?>" class="<?= $tab===$t?'actief':'' ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $svg ?></svg>
          <?= $l ?>
        </a>
        <?php endforeach; endforeach; ?>
        <div style="border-top:1px solid var(--rand);margin-top:8px;padding:10px 14px 2px">
          <a href="<?= APP_URL ?>/admin/mail_templates.php" style="display:flex;align-items:center;gap:9px;font-size:13px;color:var(--tekst-zacht);text-decoration:none;font-weight:500">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Mail templates ↗
          </a>
        </div>
      </div>
    </div>
  </div>
  <div class="card" style="margin-top:10px">
    <div class="card-body" style="padding:12px 14px;font-size:12px;color:var(--tekst-zacht);line-height:1.7">
      <strong style="display:block;margin-bottom:4px;color:var(--navy)">Versie</strong>
      <?= defined('APP_VERSIE') ? APP_VERSIE : instelling('app.versie', '1.0.0') ?><br>
      <a href="<?= APP_URL ?>/admin/deploy.php" style="color:var(--oranje)">Deploy / versiebeheer ↗</a>
    </div>
  </div>
</div>

<!-- Inhoud rechts -->
<div>

<?php if ($tab === 'algemeen'): ?>
<div class="card">
  <div class="card-header"><div class="card-title">⚙️ Algemene instellingen</div></div>
  <div class="card-body">
    <form method="POST" enctype="multipart/form-data">
      <?= csrf_input() ?><input type="hidden" name="actie" value="instellingen">
      <div class="inst-sectie">
        <h3>Organisatie</h3>
        <div class="form-groep">
          <label>Naam van de organisatie</label>
          <input type="text" name="inst[site.naam]" value="<?= h(instelling('site.naam','Reddingsbrigade Paal 13')) ?>">
        </div>
        <div class="form-rij">
          <div class="form-groep">
            <label>Logo <span class="tip" data-tip="Upload een bestand of vul een URL in. Wordt links bovenin getoond in admin én portaal."></span></label>
            <input type="file" name="logo_upload" accept="image/png,image/jpeg,image/webp,image/svg+xml" style="font-size:12px">
            <div class="form-hulp">Of vul een pad/URL in:</div>
            <input type="text" name="inst[site.logo_url]" value="<?= h(instelling('site.logo_url')) ?>" placeholder="/uploads/branding/logo.png" style="margin-top:4px">
          </div>
          <div class="form-groep">
            <label>Favicon <span class="tip" data-tip="Pictogram in browsertab. PNG, ICO of SVG."></span></label>
            <input type="file" name="favicon_upload" accept="image/png,image/x-icon,image/vnd.microsoft.icon,image/svg+xml" style="font-size:12px">
            <div class="form-hulp">Of vul een pad/URL in:</div>
            <input type="text" name="inst[site.favicon_url]" value="<?= h(instelling('site.favicon_url')) ?>" placeholder="/uploads/branding/favicon.svg" style="margin-top:4px">
          </div>
        </div>
        <?php $logo = instelling('site.logo_url'); if ($logo): ?>
        <div style="margin-top:8px;display:flex;align-items:center;gap:10px">
          <img src="<?= h($logo) ?>" alt="Logo preview" style="max-height:60px;border:1px solid var(--rand);border-radius:6px;padding:6px;background:var(--bg-card)">
          <span style="font-size:11px;color:var(--tekst-zacht)">Huidige logo</span>
        </div>
        <?php endif; ?>
      </div>

      <div class="inst-sectie">
        <h3>Portaal</h3>
        <div class="form-groep">
          <label>Welkomsttekst op portaal login <span class="tip" data-tip="Tekst die sollicitanten zien voordat ze inloggen via OTP-code."></span></label>
          <textarea name="inst[portaal.welkomst_tekst]" rows="3"><?= h(instelling('portaal.welkomst_tekst','Welkom op het sollicitantenportaal van Paal 13.')) ?></textarea>
        </div>
      </div>

      <div class="inst-sectie">
        <h3>Aanmeldformulier</h3>
        <div class="form-rij">
          <div class="form-groep">
            <label>Minimumleeftijd sollicitant <span class="tip" data-tip="Wordt gebruikt voor de uitnodiging-suggestie. Sollicitanten jonger dan dit krijgen een waarschuwing."></span></label>
            <input type="number" name="inst[aanmeld.min_leeftijd]" value="<?= instelling_int('aanmeld.min_leeftijd',15) ?>" min="10" max="30">
          </div>
          <div class="form-groep">
            <label>Maximumleeftijd sollicitant</label>
            <input type="number" name="inst[aanmeld.max_leeftijd]" value="<?= instelling_int('aanmeld.max_leeftijd',35) ?>" min="18" max="65">
          </div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary">Opslaan</button>
    </form>
  </div>
</div>

<?php elseif ($tab === 'functies'): ?>
<div class="card">
  <div class="card-header"><div class="card-title">👥 Functies & factoren</div></div>
  <div class="card-body">
    <p style="font-size:13px;color:var(--tekst-zacht);margin-bottom:16px">
      Het dagloon per persoon = minimumloon/uur × <?= instelling_int('loon.uren_per_dag',8) ?> uur × factor × <?= 1 + instelling_float('loon.vt_percentage',8)/100 ?> (VT).
    </p>
    <?php foreach ($functies as $f): ?>
    <form method="POST" style="border:1px solid var(--rand);border-radius:8px;padding:14px;margin-bottom:10px">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="functie_opslaan">
      <input type="hidden" name="functie_id" value="<?= $f['id'] ?>">
      <div style="display:grid;grid-template-columns:1fr 80px 80px 80px 60px auto;gap:10px;align-items:end">
        <div class="form-groep" style="margin:0">
          <label style="font-size:11px">Naam</label>
          <input type="text" name="naam" value="<?= h($f['naam']) ?>">
        </div>
        <div class="form-groep" style="margin:0">
          <label style="font-size:11px">Factor</label>
          <input type="number" name="factor" value="<?= $f['factor'] ?>" step="0.01" min="0" max="5">
        </div>
        <div class="form-groep" style="margin:0">
          <label style="font-size:11px">Min leeftijd</label>
          <input type="number" name="min_leeftijd" value="<?= $f['min_leeftijd'] ?>" min="14" max="99">
        </div>
        <div class="form-groep" style="margin:0">
          <label style="font-size:11px">Max leeftijd</label>
          <input type="number" name="max_leeftijd" value="<?= $f['max_leeftijd'] ?>" min="14" max="99">
        </div>
        <div class="form-groep" style="margin:0">
          <label style="font-size:11px">Volgorde</label>
          <input type="number" name="volgorde" value="<?= $f['volgorde'] ?>" min="0" max="99">
        </div>
        <div style="padding-bottom:1px">
          <button type="submit" class="btn btn-primary btn-sm">Sla op</button>
        </div>
      </div>
      <?php
      $dl_jun = $f['sleutel'] !== 'postcommandant'
        ? '€'.number_format(bereken_dagloon(20, $f['sleutel'], 'jun'), 2, ',', '.')
        : '€'.number_format(instelling_float('loon.pc_vast_juni',850)/5, 2, ',', '.');
      ?>
      <div style="font-size:11px;color:var(--grijs);margin-top:6px">
        Voorbeeldberekening 20jr, juni: <strong><?= $dl_jun ?>/dag</strong>
        <?= $f['factor'] > 0 ? "(uurloon × 8 × {$f['factor']} × 1.08)" : "(vast weekbedrag ÷ 5)" ?>
      </div>
    </form>
    <?php endforeach; ?>

    <div class="inst-sectie" style="margin-top:16px">
      <h3>Vakantietoeslag & werkuren</h3>
      <form method="POST">
        <?= csrf_input() ?><input type="hidden" name="actie" value="instellingen">
        <div class="form-rij">
          <div class="form-groep">
            <label>Vakantietoeslag % <span class="tip" data-tip="Wordt automatisch opgeteld bij de uurlonen in de loonplanning. Standaard 8%."></span></label>
            <input type="number" name="inst[loon.vt_percentage]" value="<?= instelling_int('loon.vt_percentage',8) ?>" min="0" max="20">
            <div class="form-hulp">Standaard 8% (wettelijk minimum)</div>
          </div>
          <div class="form-groep">
            <label>Werkuren per dag <span class="tip" data-tip="Gebruikt voor dagloon-berekening: uurloon × dit getal × functie-factor × VT."></span></label>
            <input type="number" name="inst[loon.uren_per_dag]" value="<?= instelling_int('loon.uren_per_dag',8) ?>" min="1" max="12">
            <div class="form-hulp">Standaard 8 uur (40u/week ÷ 5)</div>
          </div>
        </div>
        <div class="form-rij">
          <div class="form-groep">
            <label>Vast weekbedrag postcommandant (juni) <span class="tip" data-tip="Postcommandant heeft vast bedrag per week, niet gerelateerd aan leeftijd."></span></label>
            <div style="position:relative"><span style="position:absolute;left:10px;top:9px;color:var(--grijs)">€</span>
            <input type="number" name="inst[loon.pc_vast_juni]" value="<?= instelling_float('loon.pc_vast_juni',850) ?>" style="padding-left:24px" step="0.01"></div>
          </div>
          <div class="form-groep">
            <label>Vast weekbedrag postcommandant (jul-sep)</label>
            <div style="position:relative"><span style="position:absolute;left:10px;top:9px;color:var(--grijs)">€</span>
            <input type="number" name="inst[loon.pc_vast_jul_sep]" value="<?= instelling_float('loon.pc_vast_jul_sep',750) ?>" style="padding-left:24px" step="0.01"></div>
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Opslaan</button>
      </form>
    </div>
  </div>
</div>

<?php elseif ($tab === 'loon'): ?>
<div class="card" style="margin-bottom:14px">
  <div class="card-header">
    <div class="card-title">🎯 Seizoensbudget</div>
    <div style="font-size:12px;color:var(--tekst-zacht)">Voor de voortgangsbalk op de loonplanning</div>
  </div>
  <div class="card-body">
    <form method="POST" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="instellingen">
      <div class="form-groep" style="margin:0">
        <label for="budget_seizoen">Totaalbudget loonkosten dit seizoen (€)</label>
        <input type="number" step="100" min="0" id="budget_seizoen" name="inst[loon.budget_seizoen]"
               value="<?= h(instelling('loon.budget_seizoen', '')) ?>" placeholder="bijv. 25000" style="max-width:200px">
      </div>
      <button type="submit" class="btn btn-primary">Opslaan</button>
      <div style="font-size:12px;color:var(--tekst-zacht);flex:1;min-width:200px">
        Laat leeg om zonder budget te werken — de loonplanning toont dan alleen de kosten.
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">💶 Minimumlonen per leeftijd</div>
    <div style="font-size:12px;color:var(--tekst-zacht)">Bedragen per uur, excl. vakantietoeslag</div>
  </div>
  <div class="card-body">
    <div class="form-rij" style="gap:20px">
      <?php foreach (['jun'=>'Juni','jul_sep'=>'Juli / Aug / Sep'] as $periode=>$label): ?>
      <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="loon_tarief">
        <input type="hidden" name="periode" value="<?= $periode ?>">
        <h3 style="font-size:14px;font-weight:600;color:var(--navy);margin-bottom:10px"><?= $label ?></h3>
        <table class="loon-tabel-inst">
          <thead>
            <tr>
              <th>Leeftijd</th>
              <th>€/uur</th>
              <th>Dag (LG)</th>
            </tr>
          </thead>
          <tbody>
            <?php $tarieven = $periode === 'jun' ? $tarieven_jun : $tarieven_jul;
            for ($l = 15; $l <= 32; $l++):
              $loon = $tarieven[$l] ?? '';
              $dagl = $loon ? bereken_dagloon($l, 'lifeguard', $periode) : null;
            ?>
            <tr>
              <td style="font-weight:600"><?= $l ?> jr</td>
              <td>
                <div style="position:relative">
                  <span style="position:absolute;left:8px;top:7px;color:var(--grijs);font-size:12px">€</span>
                  <input type="number" class="loon-input" name="loon[<?= $l ?>]"
                         value="<?= $loon ?>" step="0.01" min="0" max="99"
                         style="padding-left:20px"
                         oninput="updatePreview(this, <?= $l ?>, '<?= $periode ?>')">
                </div>
              </td>
              <td class="preview-loon" id="prev-<?= $periode ?>-<?= $l ?>">
                <?= $dagl ? '€'.number_format($dagl,2,',','.') : '—' ?>
              </td>
            </tr>
            <?php endfor; ?>
          </tbody>
        </table>
        <button type="submit" class="btn btn-primary" style="margin-top:10px;width:100%;justify-content:center">Opslaan <?= $label ?></button>
      </form>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php elseif ($tab === 'uitnodiging'): ?>
<div class="card">
  <div class="card-header"><div class="card-title">📨 Uitnodigingsinstellingen</div></div>
  <div class="card-body">
    <form method="POST">
      <?= csrf_input() ?><input type="hidden" name="actie" value="instellingen">
      <div class="inst-sectie">
        <h3>Auto-invite op basis van leeftijd</h3>
        <p style="font-size:13px;color:var(--tekst-zacht);margin-bottom:12px">
          Sollicitanten binnen de leeftijdsgrenzen worden automatisch of als suggestie weergegeven voor uitnodiging.
        </p>
        <div class="form-groep">
          <label>Modus</label>
          <select name="inst[invite.modus]">
            <?php foreach (['uit'=>'Uit — geen automatisering','suggestie'=>'Suggestie — markeer in de lijst, admin handelt','auto'=>'Automatisch — nodig uit na ingestelde vertraging'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= instelling('invite.modus') === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-rij">
          <div class="form-groep">
            <label>Minimumleeftijd</label>
            <input type="number" name="inst[invite.min_leeftijd]" value="<?= instelling_int('invite.min_leeftijd',16) ?>" min="14" max="40">
          </div>
          <div class="form-groep">
            <label>Maximumleeftijd</label>
            <input type="number" name="inst[invite.max_leeftijd]" value="<?= instelling_int('invite.max_leeftijd',30) ?>" min="14" max="65">
          </div>
        </div>
        <div class="form-groep" id="delay_veld" style="<?= instelling('invite.modus') !== 'auto' ? 'display:none' : '' ?>">
          <label>Vertraging in uren (alleen bij automatisch)</label>
          <input type="number" name="inst[invite.delay_uur]" value="<?= instelling_int('invite.delay_uur',24) ?>" min="0" max="168">
          <div class="form-hulp">0 = direct uitnodigen. 24 = na 24 uur automatisch uitnodigen.</div>
        </div>
      </div>

      <div class="inst-sectie">
        <h3>⚙️ Automatisering bij fase-wijziging</h3>
        <p style="font-size:12px;color:var(--tekst-zacht);margin-bottom:10px">
          Wat gebeurt er automatisch als een sollicitant van fase wisselt.
        </p>
        <div class="form-rij">
          <div class="form-groep">
            <label>
              <input type="hidden" name="inst[auto.ondertekening_sd]" value="0">
              <input type="checkbox" name="inst[auto.ondertekening_sd]" value="1" <?= instelling('auto.ondertekening_sd', '1') === '1' ? 'checked' : '' ?>>
              Ondertekeningen bij SD-uitnodiging
            </label>
            <div class="form-hulp">18+: gezondheid + intake als portaal-taak. Minderjarig: ouder krijgt automatisch mail zodra sollicitant ouder-gegevens invult.</div>
          </div>
          <div class="form-groep">
            <label>
              <input type="hidden" name="inst[auto.kennismakingspakket]" value="0">
              <input type="checkbox" name="inst[auto.kennismakingspakket]" value="1" <?= instelling('auto.kennismakingspakket', '1') === '1' ? 'checked' : '' ?>>
              Kennismakingspakket bij aangenomen
            </label>
            <div class="form-hulp">Welkomstmail + portaal-taken (lidmaatschap, loonheffing, huisregels).</div>
          </div>
        </div>
      </div>

      <div class="inst-sectie">
        <h3>Herinneringen</h3>
        <p style="font-size:12px;color:var(--tekst-zacht);margin-bottom:10px">
          Automatische herinneringen, gecontroleerd op wat er echt verstuurd is (niet dubbel). Inhoud en kanaal per type:
          <a href="<?= APP_URL ?>/admin/mail_templates.php" style="color:var(--oranje)">Mail templates</a> en
          <a href="<?= APP_URL ?>/admin/instellingen.php?tab=berichten" style="color:var(--oranje)">Berichten</a>.
          Proefdraaien zonder te versturen: <a href="<?= APP_URL ?>/admin/systeem.php#herinneringen" style="color:var(--oranje)">Systeem › Herinneringen</a>.
        </p>

        <?php if (function_exists('herinnering_regels')): foreach (herinnering_regels() as $hslug => $hregel):
          $h_aan = herinnering_instelling($hslug, 'aan') === '1'; ?>
        <div class="form-rij" style="align-items:flex-start">
          <div class="form-groep" style="flex:2">
            <label>
              <input type="hidden" name="inst[herinnering.<?= $hslug ?>.aan]" value="0">
              <input type="checkbox" name="inst[herinnering.<?= $hslug ?>.aan]" value="1" <?= $h_aan ? 'checked' : '' ?>>
              <?= h($hregel['naam']) ?>
            </label>
            <div class="form-hulp"><?= h($hregel['uitleg']) ?></div>
            <?php if ($hslug === 'selectiedag_morgen'): ?>
            <input type="text" name="inst[herinnering.selectiedag_morgen.info]" value="<?= h(instelling('herinnering.selectiedag_morgen.info', '') ?: instelling('reminder.sd_morgen_info', '')) ?>" placeholder="Laatste info, bv: het wordt warm, neem water mee" style="margin-top:6px">
            <?php endif; ?>
          </div>
          <?php if (empty($hregel['vast_na'])): ?>
          <div class="form-groep" style="max-width:120px">
            <label><?= in_array($hslug, ['selectiedag_nadert', 'trainingsweekend_nadert'], true) ? 'Dagen vooraf' : 'Na (dagen)' ?></label>
            <input type="number" name="inst[herinnering.<?= $hslug ?>.na]" value="<?= (int)herinnering_instelling($hslug, 'na') ?>" min="0" max="60">
          </div>
          <?php endif; ?>
          <?php if ((int)$hregel['herhaal'] > 0): ?>
          <div class="form-groep" style="max-width:120px">
            <label>Herhaal elke</label>
            <input type="number" name="inst[herinnering.<?= $hslug ?>.herhaal]" value="<?= (int)herinnering_instelling($hslug, 'herhaal') ?>" min="1" max="60">
          </div>
          <div class="form-groep" style="max-width:100px">
            <label>Max</label>
            <input type="number" name="inst[herinnering.<?= $hslug ?>.max]" value="<?= (int)herinnering_instelling($hslug, 'max') ?>" min="1" max="20">
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; endif; ?>

        <div class="form-rij">
          <div class="form-groep">
            <label>Rustperiode (dagen)</label>
            <input type="number" name="inst[herinnering.rust_dagen]" value="<?= instelling_int('herinnering.rust_dagen', 3) ?>" min="0" max="30">
            <div class="form-hulp">Maximaal één "zachte" herinnering per sollicitant per zoveel dagen.</div>
          </div>
          <div class="form-groep">
            <label>Dagplafond per ontvanger</label>
            <input type="number" name="inst[melding.plafond_dag]" value="<?= instelling_int('melding.plafond_dag', 8) ?>" min="1" max="50">
            <div class="form-hulp">Meer automatische meldingen per dag worden overgeslagen (anti-loop; zie foutenlog).</div>
          </div>
          <div class="form-groep">
            <label>Bijlage als e-mailbijlage tot (MB)</label>
            <input type="number" name="inst[bijlage.mail_max_mb]" value="<?= instelling_int('bijlage.mail_max_mb', 15) ?>" min="1" max="25">
            <div class="form-hulp">Grotere bestanden gaan als downloadknop in de mail (link 14 dagen geldig). Weigert de mailserver een bijlage toch, dan gaat de mail automatisch opnieuw met een downloadknop.</div>
          </div>
          <div class="form-groep">
            <label>WhatsApp per uur (max)</label>
            <input type="number" name="inst[wa.max_per_uur]" value="<?= instelling_int('wa.max_per_uur', 30) ?>" min="1" max="500">
            <div class="form-hulp">Boven dit aantal valt de kern terug op SMS/e-mail.</div>
          </div>
        </div>
      </div>
      <button type="submit" class="btn btn-primary">Opslaan</button>
    </form>
  </div>
</div>

<?php elseif ($tab === 'mail'): ?>
<div class="card">
  <div class="card-header"><div class="card-title">📧 Mail & notificaties</div></div>
  <div class="card-body">
    <form method="POST">
      <?= csrf_input() ?><input type="hidden" name="actie" value="instellingen">
      <div class="inst-sectie">
        <h3>E-mailadressen</h3>
        <div class="form-rij">
          <div class="form-groep">
            <label>Foutmeldingen sturen naar</label>
            <input type="email" name="inst[mail.fout_email]" value="<?= h(instelling('mail.fout_email','website@paal13.com')) ?>">
            <div class="form-hulp">Ontvangt automatisch een mail bij technische fouten (foutcode + details)</div>
          </div>
          <div class="form-groep">
            <label>Bestuur e-mail</label>
            <input type="email" name="inst[mail.bestuur_email]" value="<?= h(instelling('mail.bestuur_email','bestuur@paal13.com')) ?>">
            <div class="form-hulp">Ontvangt notificaties bij nieuwe aanmeldingen</div>
          </div>
        </div>
        <div class="form-rij">
          <div class="form-groep">
            <label>Penningmeester e-mail</label>
            <input type="email" name="inst[mail.penningmeester_email]" value="<?= h(instelling('mail.penningmeester_email','')) ?>">
            <div class="form-hulp">Ontvangt de melding "loondossier compleet" (loonheffing, kopie ID, bankpas). Leeg = bestuursadres</div>
          </div>
          <div class="form-groep">
            <label>Ledenservice e-mail</label>
            <input type="email" name="inst[mail.ledenservice_email]" value="<?= h(instelling('mail.ledenservice_email','')) ?>">
            <div class="form-hulp">Ontvangt de ondertekende inschrijving als lid/donateur (met PDF). Leeg = bestuursadres</div>
          </div>
        </div>
        <div class="form-groep">
          <label class="checkbox-label"><input type="hidden" name="inst[documenten.penningmeester_bijlagen]" value="0"><input type="checkbox" name="inst[documenten.penningmeester_bijlagen]" value="1" <?= instelling('documenten.penningmeester_bijlagen','0') === '1' ? 'checked' : '' ?>> Loondossier-stukken als bijlage meesturen in de mail aan de penningmeester</label>
          <div class="form-hulp">Standaard uit: de stukken (BSN, kopie identiteitsbewijs, bankpas) gaan dan alleen via de beveiligde inzage in het beheer. Aanzetten stuurt ze onversleuteld per e-mail; doe dat alleen als het mailpad vertrouwd is.</div>
        </div>
      </div>
      <div class="inst-sectie">
        <h3>AI Analyse (BLK)</h3>
        <div class="form-hulp" style="padding:12px 16px;background:var(--blauw-bg);border-left:3px solid var(--blauw);border-radius:6px">
          🤖 AI-instellingen (Gemini API key, model, prompt template) zijn verplaatst naar het aparte tabblad <a href="?tab=ai" style="font-weight:500">AI Analyse</a>.
        </div>
      </div>
      <button type="submit" class="btn btn-primary">Opslaan</button>
    </form>

    <!-- ── Cronjob ───────────────────────────────────────────── -->
    <div style="border-top:1px solid var(--rand);padding-top:18px;margin-top:24px">
      <h3 style="font-size:14px;color:var(--navy);margin-bottom:10px">⏰ Cronjob (automatische taken)</h3>
      <?php
      $cron_token = instelling('cron.token');
      if (empty($cron_token)) {
          $cron_token = bin2hex(random_bytes(24));
          instelling_opslaan('cron.token', $cron_token);
      }
      $cron_url = APP_URL . '/cron/run.php?token=' . $cron_token;
      ?>
      <p style="font-size:13px;color:var(--tekst-zacht);margin-bottom:12px">
        Voer de cronjob in cPanel in om de wachtrijen (AI, mail, auto-invite) elke 5 minuten te verwerken.
      </p>
      <div style="background:var(--grijs-bg);border:1px solid var(--rand);border-radius:6px;padding:12px;font-family:monospace;font-size:12px;word-break:break-all;margin-bottom:10px">
        */5 * * * * curl -s "<?= h($cron_url) ?>" &gt; /dev/null 2&gt;&amp;1
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
        <a href="<?= h($cron_url) ?>" target="_blank" class="btn btn-outline btn-sm">▶ Handmatig uitvoeren</a>
      </div>

      <?php
      // Wachtrij status
      $q_stats = ['ai'=>[], 'mail'=>[]];
      try {
          if (function_exists('ai_queue_auto_setup'))   { ai_queue_auto_setup();   $q_stats['ai']   = db()->query("SELECT status,COUNT(*) n FROM ai_queue   GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR); }
          if (function_exists('mail_queue_auto_setup')) { mail_queue_auto_setup(); $q_stats['mail'] = db()->query("SELECT status,COUNT(*) n FROM mail_queue GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR); }
      } catch (\Exception $e) {}
      $kleuren = ['wacht'=>'badge-blauw','bezig'=>'badge-oranje','klaar'=>'badge-groen','fout'=>'badge-rood'];
      ?>
      <?php foreach (['ai'=>'AI analyses', 'mail'=>'Mail wachtrij'] as $sleutel => $label): if (!empty($q_stats[$sleutel])): ?>
      <div style="margin-bottom:8px">
        <div style="font-size:12px;font-weight:600;color:var(--tekst-zacht);margin-bottom:4px"><?= h($label) ?></div>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
          <?php foreach ($q_stats[$sleutel] as $st => $n): ?>
          <span class="badge <?= $kleuren[$st] ?? 'badge-grijs' ?>" style="font-size:11px"><?= h($st) ?>: <?= $n ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; endforeach; ?>
    </div>
  </div>
</div>

<?php elseif ($tab === 'sso'): ?>
<div class="card">
  <div class="card-header"><div class="card-title">🔐 Authentik SSO</div></div>
  <div class="card-body">
    <p style="font-size:13px;color:var(--tekst-zacht);margin-bottom:14px">
      Authentik is geconfigureerd via <code>includes/config.php</code> (OIDC_ISSUER, OIDC_CLIENT_ID).
      Met een extra API-token kunnen <strong>terugkerende sollicitanten</strong> inloggen via Authentik
      (in plaats van per-e-mail OTP code).
    </p>

    <div class="info-blok" style="background:var(--grijs-bg);padding:12px;border-radius:6px;margin-bottom:14px;font-size:12px">
      <div style="font-weight:600;color:var(--tekst);margin-bottom:6px">Status</div>
      <div>OIDC config: <?= authentik_actief() ? '<span style="color:var(--groen)">✓ aanwezig</span>' : '<span style="color:var(--rood)">✗ ontbreekt</span>' ?></div>
      <div>API-token: <?= instelling('authentik.api_token') ? '<span style="color:var(--groen)">✓ ingesteld</span>' : '<span style="color:var(--grijs)">— geen (alleen OTP)</span>' ?></div>
      <div>Issuer: <code><?= defined('OIDC_ISSUER') ? h(OIDC_ISSUER) : '—' ?></code></div>
    </div>

    <form method="POST">
      <?= csrf_input() ?>
      <input type="hidden" name="inst_tab" value="sso">

      <div class="form-groep">
        <label>Authentik API token <span class="tip" data-tip="Maak in Authentik → Directory → Tokens een long-lived token aan. Dit gebruiken we om te checken of een sollicitant een Authentik-account heeft."></span></label>
        <input type="text" name="inst[authentik.api_token]" value="<?= h(instelling('authentik.api_token', '')) ?>"
               placeholder="ak_xxx..." autocomplete="off">
        <div class="form-hulp">
          Vereist scope: <code>core/users</code> view. Laat leeg om SSO voor portaal uit te schakelen (OTP blijft werken).
        </div>
      </div>

      <div class="form-groep">
        <label>Toegestane admin-groepen <span class="tip" data-tip="Authentik group namen die admin-toegang krijgen. Andere gebruikers kunnen alleen via portaal inloggen."></span></label>
        <input type="text" name="inst[authentik.admin_groepen]" value="<?= h(instelling('authentik.admin_groepen', 'Bestuur,Sol-Admin,Sol-Begeleider')) ?>"
               placeholder="Bestuur,Sol-Admin,Sol-Begeleider">
        <div class="form-hulp">
          Comma-separated. Gebruikers met een van deze groepen krijgen toegang tot <code>/admin</code>.
        </div>
      </div>

      <button type="submit" name="actie" value="instellingen" class="btn btn-primary">Opslaan</button>
    </form>

    <div style="border-top:1px solid var(--rand);padding-top:14px;margin-top:18px">
      <div style="font-weight:600;font-size:13px;margin-bottom:8px">Werking</div>
      <ul style="font-size:12px;color:var(--tekst-zacht);line-height:1.7;padding-left:18px">
        <li>Sollicitant vult email in op <code>/portaal/</code></li>
        <li>Bestaat dit email in Authentik? → SSO-login (geen OTP nodig)</li>
        <li>Niet in Authentik? → Code per mail (fallback, blijft werken)</li>
        <li>Admin-login via <code>/admin/</code> werkt onveranderd</li>
      </ul>
    </div>
  </div>
</div>

<?php elseif ($tab === 'ai'): ?>

  <form method="POST" action="">
    <?= csrf_input() ?>
    <input type="hidden" name="actie" value="instellingen">
    <input type="hidden" name="tab" value="ai">

    <div class="card">
      <div class="card-header"><h2>🤖 AI Analyse — Gemini</h2></div>
      <div class="card-body">

        <div class="inst-sectie">
          <h3>API Configuratie</h3>
          <div class="form-rij">
            <div class="form-groep">
              <label>Gemini API Key <span class="tip" data-tip="Verkrijg een gratis key via Google AI Studio"></span></label>
              <input type="text" name="inst[ai.api_key]" value="<?= h(instelling('ai.api_key', '')) ?>" placeholder="AIza...">
              <div class="form-hulp"><a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener" style="color:var(--oranje)">Gratis key aanvragen →</a></div>
            </div>
            <div class="form-groep">
              <label>Model</label>
              <select name="inst[ai.model]">
                <?php $m = instelling('ai.model', 'gemini-1.5-flash'); ?>
                <option value="gemini-1.5-flash"     <?= $m==='gemini-1.5-flash'?'selected':'' ?>>Gemini 1.5 Flash (snel)</option>
                <option value="gemini-1.5-pro"       <?= $m==='gemini-1.5-pro'?'selected':'' ?>>Gemini 1.5 Pro (kwaliteit)</option>
                <option value="gemini-2.0-flash-exp" <?= $m==='gemini-2.0-flash-exp'?'selected':'' ?>>Gemini 2.0 Flash (experimenteel)</option>
              </select>
            </div>
          </div>
        </div>

        <div class="inst-sectie">
          <h3>Experimentele functies</h3>
          <?php $besch_aan = instelling('ai.beschikbaarheid_hulp', '0') === '1'; ?>
          <label style="display:flex;gap:10px;align-items:flex-start;cursor:pointer;padding:12px 14px;border:1px solid var(--rand);border-radius:8px;background:var(--grijs-bg)">
            <input type="hidden" name="inst[ai.beschikbaarheid_hulp]" value="0">
            <input type="checkbox" name="inst[ai.beschikbaarheid_hulp]" value="1" <?= $besch_aan ? 'checked' : '' ?> style="margin-top:2px">
            <span>
              <strong>AI-hulp bij beschikbaarheid</strong>
              <span style="display:inline-block;font-size:10px;font-weight:600;color:var(--oranje);border:1px solid var(--oranje);border-radius:4px;padding:1px 6px;margin-left:6px;vertical-align:middle">EXPERIMENTEEL</span>
              <span style="display:block;font-size:12px;color:var(--tekst-zacht);margin-top:4px;font-weight:400">
                Sollicitanten kunnen hun beschikbaarheid in gewone taal typen; de AI vult het formulier dan alvast in.
                De sollicitant controleert het resultaat daarna zelf. Vereist een geldige Gemini API-key hierboven.
              </span>
            </span>
          </label>
        </div>

        <div class="inst-sectie">
          <h3>Prompt template</h3>
          <p style="font-size:12px;color:var(--tekst-zacht);margin-bottom:10px">
            De instructie die naar Gemini wordt gestuurd voor elke BLK-analyse. Gebruik variabelen tussen <code style="background:var(--grijs-bg);padding:1px 5px;border-radius:3px">{{ }}</code>.
          </p>

          <div style="background:var(--grijs-bg);border:1px solid var(--rand);border-radius:6px;padding:12px 14px;margin-bottom:12px;font-size:12px">
            <strong>Beschikbare variabelen:</strong>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:4px 14px;margin-top:8px;font-family:'SF Mono',Menlo,Consolas,monospace;font-size:11.5px">
              <span><code>{{leeftijd}}</code></span>
              <span><code>{{leeftijdscategorie}}</code></span>
              <span><code>{{opleiding}}</code></span>
              <span><code>{{diplomas}}</code></span>
              <span><code>{{werkervaring}}</code></span>
              <span><code>{{motivatie_lifeguard}}</code></span>
              <span><code>{{motivatie_strand}}</code></span>
              <span><code>{{beschikbaarheid}}</code></span>
              <span><code>{{aantal_weken_beschikbaar}}</code></span>
              <span><code>{{bijzonderheden}}</code></span>
              <span><code>{{seizoen_naam}}</code></span>
              <span><code>{{seizoen_weken_totaal}}</code></span>
            </div>
          </div>

          <div class="form-groep">
            <textarea id="aiPromptTextarea" name="inst[ai.prompt_template]" rows="22"
                      style="font-family:'SF Mono',Menlo,Consolas,monospace;font-size:12.5px;line-height:1.6;white-space:pre-wrap"><?= h(instelling('ai.prompt_template', '') ?: (function_exists('ai_prompt_default') ? ai_prompt_default() : '')) ?></textarea>
            <div class="form-hulp">Laat leeg om de ingebouwde standaard te gebruiken.</div>
          </div>

          <!-- Verborgen template met default prompt -->
          <textarea id="aiPromptDefault" style="display:none"><?= h(function_exists('ai_prompt_default') ? ai_prompt_default() : '') ?></textarea>

          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
            <button type="button" class="btn btn-outline btn-sm"
                    onclick="if(confirm('Standaard prompt terugzetten?')){document.getElementById('aiPromptTextarea').value=document.getElementById('aiPromptDefault').value;}">
              ↺ Standaard terugzetten
            </button>
          </div>
        </div>

        <div class="inst-sectie">
          <h3>Activering</h3>
          <label style="display:flex;align-items:center;gap:8px">
            <input type="hidden" name="inst[ai.auto_analyse]" value="0">
            <input type="checkbox" name="inst[ai.auto_analyse]" value="1" <?= instelling('ai.auto_analyse', '1') === '1' ? 'checked' : '' ?>>
            <span>Automatisch analyseren na ontvangst BLK</span>
          </label>
          <div class="form-hulp">Uit = handmatig via 'Analyseer' knop in paneel.</div>
        </div>

      </div>
      <div style="padding:14px 18px;border-top:1px solid var(--rand);background:var(--grijs-bg);text-align:right">
        <button type="submit" class="btn btn-primary">💾 Opslaan</button>
      </div>
    </div>
  </form>

<?php elseif ($tab === 'authentik'): ?>

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

  <div class="inst-sectie" style="margin-bottom:16px">
    <h3>🔐 Authentik-koppeling</h3>
    <div class="form-hulp" style="padding:12px 16px;background:var(--blauw-bg);border-left:3px solid var(--blauw);border-radius:6px;margin-bottom:14px;font-size:13px;line-height:1.7">
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
      <div class="form-rij">
        <div class="form-groep">
          <label>Authentik basis-URL</label>
          <input type="url" name="inst[authentik.url]" value="<?= h($a_url) ?>" placeholder="<?= $a_url_bestaand ? h($a_url_bestaand) . ' (uit SSO-config)' : 'https://auth.paal13.com' ?>">
          <?php if (!$a_url && $a_url_bestaand): ?><div style="font-size:11px;color:var(--groen)">✓ Gebruikt automatisch de SSO-URL: <?= h($a_url_bestaand) ?></div><?php endif; ?>
        </div>
        <div class="form-groep">
          <label>API-token <span style="color:var(--grijs);font-weight:400">(service-account)</span></label>
          <input type="password" name="inst[authentik.token]" value="<?= h($a_token) ?>" placeholder="<?= ($a_token || $a_token_bestaand) ? '••••••••••••' : 'Plak hier de token' ?>" autocomplete="off">
          <?php if (!$a_token && $a_token_bestaand): ?><div style="font-size:11px;color:var(--groen)">✓ Gebruikt automatisch de bestaande API-token</div><?php endif; ?>
        </div>
      </div>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <button type="submit" class="btn btn-primary">💾 Opslaan</button>
        <?php if ($geconf): ?>
        <button type="submit" name="actie" value="authentik_test" class="btn btn-outline">🔌 Test verbinding</button>
        <span style="font-size:12px;color:var(--groen)">✓ Geconfigureerd</span>
        <?php else: ?>
        <span style="font-size:12px;color:var(--grijs)">Nog niet geconfigureerd</span>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <?php if ($geconf): ?>
  <div class="inst-sectie" style="margin-bottom:16px">
    <h3>🔄 Synchronisatie</h3>
    <p style="font-size:13px;color:var(--tekst-zacht);margin-bottom:12px">
      Competenties worden automatisch ververst: bij elke nieuwe aanmelding, dagelijks via de cron, en handmatig per sollicitant (ververs-knop in het dossier).
      <?php if ($laatste_bulk): ?><br>Laatste volledige sync: <strong><?= h(datum_nl(substr($laatste_bulk,0,10))) ?> <?= h(substr($laatste_bulk,11,5)) ?></strong>.<?php endif; ?>
    </p>
    <form method="POST" onsubmit="return confirm('Nu alle actieve sollicitanten opnieuw synchroniseren met Authentik?')">
      <?= csrf_input() ?>
      <button type="submit" name="actie" value="authentik_bulk_sync" class="btn btn-outline">🔄 Nu alle sollicitanten verversen</button>
    </form>
  </div>
  <?php endif; ?>

  <div class="inst-sectie">
    <h3>🏷️ Groep-indeling (competenties)</h3>
    <p style="font-size:13px;color:var(--tekst-zacht);margin-bottom:12px">
      Welke Authentik-groepen tellen als competentie, en hun rangorde. Per categorie tonen we alleen de <strong>hoogste</strong> rang die iemand heeft
      (bijv. wie Chauffeur is, hoeft niet ook als Opstapper Auto te verschijnen). Groepen die hier niet staan, worden genegeerd.
    </p>
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead>
          <tr style="border-bottom:2px solid var(--rand);text-align:left">
            <th style="padding:8px 10px">Categorie</th>
            <th style="padding:8px 10px">Rangorde (laag → hoog)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($mapping as $cat => $def):
            $groepen = $def['groepen'] ?? [];
            asort($groepen);
          ?>
          <tr style="border-bottom:1px solid var(--rand-licht)">
            <td style="padding:8px 10px;font-weight:600;white-space:nowrap"><?= h($def['icoon'] ?? '') ?> <?= h($def['label']) ?>
              <div style="font-size:11px;color:var(--grijs);font-weight:400"><?= ($def['ranking'] ?? true) ? 'met rangorde' : 'los (geen rangorde)' ?></div>
            </td>
            <td style="padding:8px 10px">
              <?php foreach ($groepen as $groep => $rang): ?>
                <span style="display:inline-block;background:var(--grijs-bg);border:1px solid var(--rand);border-radius:5px;padding:3px 9px;margin:2px;font-size:12px">
                  <?php if ($def['ranking'] ?? true): ?><span style="color:var(--grijs)"><?= (int)$rang ?>.</span> <?php endif; ?><?= h($groep) ?>
                </span>
              <?php endforeach; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="font-size:12px;color:var(--grijs);margin-top:10px">
      De indeling is voorgeconfigureerd op basis van jullie Authentik-groepen. Aanpassen kan later via een uitgebreider scherm; neem contact op als een groep verkeerd staat.
    </div>
  </div>

<?php elseif ($tab === 'bezetting'): ?>
  <?php
    $sjab = function_exists('bezetting_sjablonen') ? bezetting_sjablonen() : [];
    $comp_map = function_exists('competentie_mapping') ? competentie_mapping() : [];
  ?>
  <div class="card">
    <div class="card-header"><div class="card-title">Posten &amp; bezetting</div></div>
    <div class="card-body">
      <p style="font-size:13px;color:var(--tekst-zacht);margin-bottom:16px">
        Welke rollen moeten er per post bemand zijn? De <a href="<?= APP_URL ?>/admin/dekking.php" style="color:var(--navy)">Dekking-pagina</a>
        rekent hiermee uit of elke week te bemannen is. Rollen matchen op competentie + minimum-rang uit Authentik.
        <br><strong>De postcommandant valt hier buiten</strong> — die regelt het bestuur apart.
      </p>

      <div id="postenLijst"></div>
      <button type="button" class="btn btn-outline btn-sm" onclick="postToevoegen()">+ Post toevoegen</button>

      <form method="POST" style="margin-top:18px;display:flex;gap:8px;flex-wrap:wrap" onsubmit="return bezettingSerialiseer()">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="bezetting_opslaan">
        <input type="hidden" name="sjablonen_json" id="sjablonenJson">
        <button type="submit" class="btn btn-primary">Opslaan</button>
      </form>
      <form method="POST" style="margin-top:8px" onsubmit="return confirm('Standaardbezetting herstellen? Je huidige indeling gaat verloren.')">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="bezetting_herstel">
        <button type="submit" class="btn btn-outline btn-sm">↩ Standaard herstellen</button>
      </form>
    </div>
  </div>

  <script>
  const COMP_CATS = <?= json_encode(array_map(fn($c) => ['label'=>$c['label'], 'groepen'=>$c['groepen'] ?? []], $comp_map)) ?>;
  let SJABLONEN = <?= json_encode($sjab) ?>;

  function catOpties(sel) {
    let h = '';
    for (const c in COMP_CATS) h += '<option value="'+c+'"'+(c===sel?' selected':'')+'>'+COMP_CATS[c].label+'</option>';
    return h;
  }
  function rangOpties(cat, sel) {
    const groepen = COMP_CATS[cat]?.groepen || {};
    let h = '';
    for (const g in groepen) h += '<option value="'+groepen[g]+'"'+(parseInt(groepen[g])===parseInt(sel)?' selected':'')+'>'+groepen[g]+' — '+g+' of hoger</option>';
    return h || '<option value="1">1</option>';
  }

  function render() {
    const box = document.getElementById('postenLijst');
    box.innerHTML = '';
    for (const ps in SJABLONEN) {
      const post = SJABLONEN[ps];
      const div = document.createElement('div');
      div.style.cssText = 'border:1px solid var(--rand);border-radius:10px;padding:14px;margin-bottom:12px;background:var(--bg)';
      let rollenHtml = '';
      (post.rollen || []).forEach((r, i) => {
        rollenHtml +=
          '<div style="display:flex;gap:6px;align-items:center;margin-bottom:6px;flex-wrap:wrap">' +
            '<input type="text" value="'+(r.label||'').replace(/"/g,'&quot;')+'" placeholder="Rol" oninput="SJABLONEN[\''+ps+'\'].rollen['+i+'].label=this.value" style="flex:2;min-width:130px;padding:6px 8px;border:1px solid var(--rand);border-radius:5px;font-size:13px">' +
            '<select onchange="SJABLONEN[\''+ps+'\'].rollen['+i+'].categorie=this.value;SJABLONEN[\''+ps+'\'].rollen['+i+'].min_rang=1;render()" style="flex:1;min-width:110px;padding:6px;border:1px solid var(--rand);border-radius:5px;font-size:13px">'+catOpties(r.categorie)+'</select>' +
            '<select onchange="SJABLONEN[\''+ps+'\'].rollen['+i+'].min_rang=parseInt(this.value)" style="flex:2;min-width:150px;padding:6px;border:1px solid var(--rand);border-radius:5px;font-size:13px">'+rangOpties(r.categorie, r.min_rang)+'</select>' +
            '<input type="number" min="1" max="20" value="'+(r.aantal||1)+'" oninput="SJABLONEN[\''+ps+'\'].rollen['+i+'].aantal=parseInt(this.value)||1" title="Aantal personen" style="width:60px;padding:6px;border:1px solid var(--rand);border-radius:5px;font-size:13px">' +
            '<button type="button" class="btn btn-outline btn-sm" onclick="rolWeg(\''+ps+'\','+i+')" style="min-width:36px">✕</button>' +
          '</div>';
      });
      div.innerHTML =
        '<div style="display:flex;gap:8px;align-items:center;margin-bottom:10px;flex-wrap:wrap">' +
          '<input type="text" value="'+(post.icoon||'').replace(/"/g,'&quot;')+'" oninput="SJABLONEN[\''+ps+'\'].icoon=this.value" style="width:48px;text-align:center;padding:6px;border:1px solid var(--rand);border-radius:5px;font-size:15px">' +
          '<input type="text" value="'+(post.naam||'').replace(/"/g,'&quot;')+'" oninput="SJABLONEN[\''+ps+'\'].naam=this.value" placeholder="Postnaam" style="flex:1;min-width:150px;padding:7px 10px;border:1px solid var(--rand);border-radius:5px;font-weight:600;font-size:14px">' +
          '<label style="display:flex;align-items:center;gap:5px;font-size:12px;color:var(--tekst-zacht)"><input type="checkbox" '+(post.regel_geen_twee_aspiranten?'checked':'')+' onchange="SJABLONEN[\''+ps+'\'].regel_geen_twee_aspiranten=this.checked"> nooit 2 aspiranten samen</label>' +
          '<button type="button" class="btn btn-outline btn-sm" onclick="postWeg(\''+ps+'\')">Post verwijderen</button>' +
        '</div>' +
        rollenHtml +
        '<button type="button" class="btn btn-outline btn-sm" onclick="rolToevoegen(\''+ps+'\')">+ Rol</button>';
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
  function postWeg(ps) { if (confirm('Post verwijderen?')) { delete SJABLONEN[ps]; render(); } }
  function postToevoegen() {
    const sleutel = 'post' + Date.now();
    SJABLONEN[sleutel] = {naam:'Nieuwe post', icoon:'📍', rollen:[], regel_geen_twee_aspiranten:false};
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
<div class="card">
  <div class="card-header">
    <div class="card-title">✅ Beoordelingscriteria selectiedag</div>
    <div style="font-size:12px;color:var(--tekst-zacht)">De onderdelen waarop beoordelaars scoren, per test. Eén criterium per regel.</div>
  </div>
  <div class="card-body">
    <form method="POST">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="selectie_criteria_opslaan">
      <div class="form-rij" style="gap:20px;align-items:flex-start">
        <?php foreach (['sport'=>'Sporttest', 'zwem'=>'Zwemtest'] as $tk => $default): ?>
        <div style="flex:1;min-width:240px">
          <div style="display:flex;gap:8px;margin-bottom:8px">
            <input type="text" name="icoon_<?= $tk ?>" value="<?= h($crit[$tk]['icoon'] ?? '') ?>"
                   style="width:56px;text-align:center;font-size:16px" title="Icoon" maxlength="4">
            <input type="text" name="naam_<?= $tk ?>" value="<?= h($crit[$tk]['naam'] ?? $default) ?>"
                   style="flex:1;font-weight:600" placeholder="Naam van de test">
          </div>
          <textarea name="criteria_<?= $tk ?>" rows="8" style="width:100%;font-size:14px;font-family:inherit;padding:10px 12px;border:1px solid var(--rand);border-radius:8px;box-sizing:border-box"
                    placeholder="Eén criterium per regel"><?php
            foreach ($crit[$tk]['criteria'] ?? [] as $c) echo h($c['label']) . "\n";
          ?></textarea>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;gap:8px;margin-top:14px">
        <button type="submit" class="btn btn-primary">Opslaan</button>
      </div>
    </form>
    <form method="POST" style="margin-top:8px" onsubmit="return confirm('Terug naar de standaardcriteria uit het draaiboek?')">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="selectie_criteria_herstel">
      <button type="submit" class="btn btn-outline btn-sm">↩ Standaard uit draaiboek herstellen</button>
    </form>
    <p style="font-size:12px;color:var(--grijs);margin-top:12px">
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
<div class="card">
  <div class="card-header"><div class="card-title">🕐 Bewaartermijnen</div></div>
  <div class="card-body">
    <div class="flash flash-info" style="margin-bottom:16px">
      <strong>Automatisch opschonen.</strong> Volgens de AVG mag je persoonsgegevens niet langer bewaren dan nodig.
      Hieronder stel je in na hoeveel tijd welke gegevens worden opgeschoond. Aangenomen kandidaten worden nooit
      automatisch verwijderd. Zet een termijn op <code>0</code> om die categorie niet automatisch op te schonen.
    </div>

    <form method="POST">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="instellingen">

      <div class="form-groep" style="margin-bottom:18px">
        <label style="display:flex;align-items:center;gap:9px;font-weight:500;cursor:pointer">
          <input type="hidden" name="inst[bewaar.actief]" value="0">
          <input type="checkbox" name="inst[bewaar.actief]" value="1" style="width:auto" <?= instelling_bool('bewaar.actief') ? 'checked' : '' ?>>
          Automatisch opschonen inschakelen
        </label>
        <div class="form-hulp">Zolang dit uitstaat, wordt er niets automatisch verwijderd of geanonimiseerd.</div>
      </div>

      <h3>Termijnen (in maanden)</h3>
      <div class="form-rij">
        <div class="form-groep">
          <label>Afgewezen sollicitanten <span class="tip" data-tip="Na deze termijn worden de persoonsgegevens geanonimiseerd. Geanonimiseerde statistiek blijft behouden. AP-advies is 4 weken (~1 maand); langer mag mits gemotiveerd."></span></label>
          <input type="number" name="inst[bewaar.afgewezen_mnd]" value="<?= instelling_int('bewaar.afgewezen_mnd',6) ?>" min="0" max="60">
          <div class="form-hulp">Daarna anonimiseren</div>
        </div>
        <div class="form-groep">
          <label>Gezondheidsgegevens <span class="tip" data-tip="0 = verwijderen zodra de sollicitatie is afgewezen. Anders: zoveel maanden na ondertekening."></span></label>
          <input type="number" name="inst[bewaar.gezondheid_mnd]" value="<?= instelling_int('bewaar.gezondheid_mnd',0) ?>" min="0" max="60">
          <div class="form-hulp">0 = bij afwijzing verwijderen</div>
        </div>
      </div>
      <div class="form-rij">
        <div class="form-groep">
          <label>AI-invoertekst <span class="tip" data-tip="De vrije beschikbaarheidstekst die naar de AI-dienst is gestuurd."></span></label>
          <input type="number" name="inst[bewaar.ai_invoer_mnd]" value="<?= instelling_int('bewaar.ai_invoer_mnd',3) ?>" min="0" max="60">
          <div class="form-hulp">Daarna wissen</div>
        </div>
        <div class="form-groep">
          <label>Niet-voltooide aanmeldingen <span class="tip" data-tip="Aanmeldingen die in de fase 'aangemeld' bleven staan en nooit een formulier of beschikbaarheid kregen."></span></label>
          <input type="number" name="inst[bewaar.onvoltooid_mnd]" value="<?= instelling_int('bewaar.onvoltooid_mnd',3) ?>" min="0" max="60">
          <div class="form-hulp">Daarna verwijderen</div>
        </div>
      </div>
      <div class="form-rij">
        <div class="form-groep">
          <label>Berichtenlog (alle kanalen)</label>
          <input type="number" name="inst[bewaar.maillog_mnd]" value="<?= instelling_int('bewaar.maillog_mnd',12) ?>" min="0" max="60">
          <div class="form-hulp">Daarna de logregels verwijderen</div>
        </div>
        <div class="form-groep">
          <label>Inhoud van berichten <span class="tip" data-tip="De volledige tekst van verstuurde berichten (leesbaar in het portaal en het berichtenlog). Daarna blijft alleen onderwerp + samenvatting over."></span></label>
          <input type="number" name="inst[bewaar.maillog_body_mnd]" value="<?= instelling_int('bewaar.maillog_body_mnd',3) ?>" min="0" max="24">
          <div class="form-hulp">Daarna de inhoud leegmaken</div>
        </div>
        <div class="form-groep">
          <label>Losse bijlagen <span class="tip" data-tip="Geüploade bestanden die aan geen enkel sjabloon meer hangen."></span></label>
          <input type="number" name="inst[bewaar.bijlagen_mnd]" value="<?= instelling_int('bewaar.bijlagen_mnd',12) ?>" min="0" max="60">
          <div class="form-hulp">Daarna verwijderen</div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary">Opslaan</button>
    </form>
  </div>
</div>

<div class="card" style="margin-top:14px">
  <div class="card-header"><div class="card-title">📋 Opschoon-logboek</div></div>
  <div class="card-body">
    <p style="font-size:13px;color:var(--tekst-zacht);margin-bottom:12px">
      De opschoning draait automatisch één keer per dag via de cron.
      <?php if ($laatste_opschoon): ?>
        Laatst uitgevoerd: <strong><?= h($laatste_opschoon) ?></strong>.
      <?php else: ?>
        Nog niet eerder uitgevoerd.
      <?php endif; ?>
    </p>
    <?php if ($opschoon_log): ?>
    <table style="width:100%;font-size:13px;border-collapse:collapse">
      <thead>
        <tr style="text-align:left;border-bottom:1px solid var(--rand)">
          <th style="padding:6px 0">Datum</th><th>Categorie</th><th style="text-align:right">Aantal</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($opschoon_log as $r): ?>
        <tr style="border-bottom:1px solid var(--rand-licht)">
          <td style="padding:6px 0"><?= h($r['uitgevoerd_op']) ?></td>
          <td><?= h(str_replace('_',' ', $r['categorie'])) ?></td>
          <td style="text-align:right"><?= (int)$r['aantal'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <p style="font-size:13px;color:var(--grijs)">Nog geen opschoonacties geregistreerd.</p>
    <?php endif; ?>
  </div>
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
  <div class="card" style="margin-bottom:14px">
    <div class="card-header"><div class="card-title">E-mailinstellingen (SMTP)</div></div>
    <div class="card-body">
      <p style="font-size:13px;color:var(--tekst-zacht);margin:0 0 16px">
        Hiermee verstuurt het systeem e-mails. Vul de gegevens van je mailserver in. Weet je ze niet? Die vind je bij je hostingprovider (Cloud86) onder de e-mailinstellingen van dit adres.
      </p>
      <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="smtp_opslaan">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div style="grid-column:1/-1">
            <label style="display:block;font-size:12px;font-weight:600;color:var(--tekst-zacht);margin-bottom:4px">SMTP-server (host)</label>
            <input type="text" name="smtp_host" value="<?= h($s_host) ?>" placeholder="mail.paal13.com" style="width:100%;padding:9px 12px;border:1px solid var(--rand);border-radius:8px;font-size:14px">
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:600;color:var(--tekst-zacht);margin-bottom:4px">Poort</label>
            <input type="text" name="smtp_port" value="<?= h($s_port) ?>" placeholder="587" style="width:100%;padding:9px 12px;border:1px solid var(--rand);border-radius:8px;font-size:14px">
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:600;color:var(--tekst-zacht);margin-bottom:4px">Beveiliging</label>
            <select name="smtp_encrypt" style="width:100%;padding:9px 12px;border:1px solid var(--rand);border-radius:8px;font-size:14px">
              <option value="tls" <?= $s_encrypt==='tls'?'selected':'' ?>>STARTTLS (poort 587) — meest gebruikt</option>
              <option value="ssl" <?= $s_encrypt==='ssl'?'selected':'' ?>>SSL/TLS (poort 465)</option>
              <option value="none" <?= $s_encrypt==='none'?'selected':'' ?>>Geen (afgeraden)</option>
            </select>
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:600;color:var(--tekst-zacht);margin-bottom:4px">Gebruikersnaam</label>
            <input type="text" name="smtp_user" value="<?= h($s_user) ?>" placeholder="website@paal13.com" style="width:100%;padding:9px 12px;border:1px solid var(--rand);border-radius:8px;font-size:14px">
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:600;color:var(--tekst-zacht);margin-bottom:4px">Wachtwoord</label>
            <input type="password" name="smtp_pass" value="<?= $s_heeft_ww ? '••••••••' : '' ?>" autocomplete="new-password" style="width:100%;padding:9px 12px;border:1px solid var(--rand);border-radius:8px;font-size:14px">
            <span style="font-size:11px;color:var(--grijs)"><?= $s_heeft_ww ? 'Laat ongewijzigd om het huidige wachtwoord te behouden.' : 'Nog geen wachtwoord ingesteld.' ?></span>
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:600;color:var(--tekst-zacht);margin-bottom:4px">Afzender-adres</label>
            <input type="text" name="smtp_from" value="<?= h($s_from) ?>" placeholder="no-reply@paal13.com" style="width:100%;padding:9px 12px;border:1px solid var(--rand);border-radius:8px;font-size:14px">
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:600;color:var(--tekst-zacht);margin-bottom:4px">Afzender-naam</label>
            <input type="text" name="smtp_from_naam" value="<?= h($s_fromnaam) ?>" placeholder="Reddingsbrigade Paal 13" style="width:100%;padding:9px 12px;border:1px solid var(--rand);border-radius:8px;font-size:14px">
          </div>
        </div>
        <div style="margin-top:16px">
          <button type="submit" class="btn btn-primary">Opslaan</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><div class="card-title">Testmail versturen</div></div>
    <div class="card-body">
      <p style="font-size:13px;color:var(--tekst-zacht);margin:0 0 14px">
        Stuur een testmail om te controleren of je instellingen werken. Sla je wijzigingen eerst op. Lukt het niet, dan zie je de reden hier én in <a href="<?= APP_URL ?>/admin/foutenlog.php" style="color:var(--navy)">Foutenlog</a>.
      </p>
      <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="smtp_test">
        <div style="flex:1;min-width:220px">
          <label style="display:block;font-size:12px;font-weight:600;color:var(--tekst-zacht);margin-bottom:4px">Stuur test naar</label>
          <input type="email" name="smtp_test_naar" value="<?= h(huidige_beheerder()['email'] ?? '') ?>" placeholder="jouw@email.nl" style="width:100%;padding:9px 12px;border:1px solid var(--rand);border-radius:8px;font-size:14px">
        </div>
        <button type="submit" class="btn btn-outline">Verstuur testmail</button>
      </form>
    </div>
  </div>

<?php elseif ($tab === 'api'): ?>
  <?php
    $api_sleutel = instelling('api.sleutel', '');
    $api_origins = instelling('api.origins', '');
    $api_basis_url = APP_URL . '/api';
  ?>
  <div class="inst-sectie">
    <h2 style="font-size:16px;margin:0 0 4px">Website &amp; API</h2>
    <p style="color:var(--tekst-zacht);font-size:13.5px;margin:0 0 18px">
      Hiermee koppelt de WordPress-plugin op de website met dit systeem: de plugin haalt de
      seizoensinfo op en stuurt aanmeldingen hierheen. De verbinding is beveiligd met een geheime sleutel.
    </p>

    <div style="background:var(--bg-card,#fff);border:1px solid var(--rand);border-radius:10px;padding:16px;margin-bottom:16px">
      <div style="font-weight:600;font-size:14px;margin-bottom:8px">API-sleutel</div>
      <?php if ($api_sleutel): ?>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:8px">
          <input type="text" readonly value="<?= h($api_sleutel) ?>" id="apiSleutel"
                 style="flex:1;min-width:280px;font-family:monospace;font-size:12px;background:var(--bg)">
          <button type="button" class="btn btn-outline btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('apiSleutel').value);this.textContent='Gekopieerd!'">Kopieer</button>
        </div>
        <p style="font-size:12px;color:var(--tekst-zacht);margin:0 0 10px">
          Zet deze sleutel in de WordPress-plugin. Houd 'm geheim — wie de sleutel heeft, kan aanmeldingen versturen.
        </p>
      <?php else: ?>
        <p style="font-size:13px;color:var(--oranje);margin:0 0 10px">Er is nog geen sleutel. Genereer er een om de koppeling te activeren.</p>
      <?php endif; ?>
      <form method="post" onsubmit="return confirm('<?= $api_sleutel ? 'Een nieuwe sleutel maakt de oude ongeldig. De plugin moet dan bijgewerkt worden. Doorgaan?' : 'Nieuwe sleutel genereren?' ?>')">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="api_sleutel_genereer">
        <button type="submit" class="btn btn-primary btn-sm"><?= $api_sleutel ? 'Nieuwe sleutel genereren' : 'Sleutel genereren' ?></button>
      </form>
    </div>

    <form method="post">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="api_opslaan">
      <label style="display:block;font-weight:600;font-size:14px;margin-bottom:4px">Toegestane websites (CORS)</label>
      <p style="font-size:12px;color:var(--tekst-zacht);margin:0 0 6px">
        Eén per regel. De hoofdsite (paal13.com) is altijd toegestaan. Voeg hier extra domeinen toe als de plugin op een ander adres draait.
      </p>
      <textarea name="api_origins" rows="3" style="width:100%;font-family:monospace;font-size:12px" placeholder="https://paal13.com"><?= h($api_origins) ?></textarea>
      <div style="margin-top:10px"><button type="submit" class="btn btn-primary btn-sm">Opslaan</button></div>
    </form>

    <div style="margin-top:20px;padding:14px;background:var(--bg);border:1px solid var(--rand);border-radius:10px">
      <div style="font-weight:600;font-size:13px;margin-bottom:6px">Voor de plugin-instellingen</div>
      <table style="font-size:12.5px;width:100%">
        <tr><td style="padding:3px 8px;color:var(--tekst-zacht)">API-basis-URL</td><td style="padding:3px 8px;font-family:monospace"><?= h($api_basis_url) ?></td></tr>
        <tr><td style="padding:3px 8px;color:var(--tekst-zacht)">Seizoensinfo</td><td style="padding:3px 8px;font-family:monospace">GET <?= h($api_basis_url) ?>/seizoen.php</td></tr>
        <tr><td style="padding:3px 8px;color:var(--tekst-zacht)">Aanmelden</td><td style="padding:3px 8px;font-family:monospace">POST <?= h($api_basis_url) ?>/aanmelden.php</td></tr>
      </table>
    </div>
  </div>

<?php elseif ($tab === 'berichten'): ?>
  <?php
    $sms_gw = instelling('sms.gateway', '');
  ?>
  <div class="inst-sectie">
    <h2 style="font-size:16px;margin:0 0 4px">SMS, WhatsApp &amp; meldingen</h2>
    <p style="color:var(--tekst-zacht);font-size:13.5px;margin:0 0 18px">
      Stuur meldingen naast e-mail ook via SMS of WhatsApp. Beide kanalen zijn los in- en uit te
      schakelen. Onderaan bepaal je per soort melding via welke kanalen die verstuurd wordt.
    </p>

    <form method="post">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="berichten_opslaan">

      <!-- SMS -->
      <div style="background:var(--bg-card,#fff);border:1px solid var(--rand);border-radius:10px;padding:16px;margin-bottom:16px">
        <label class="inst-toggle" style="display:flex;align-items:center;gap:10px;font-weight:600;font-size:15px;margin-bottom:12px">
          <input type="checkbox" name="sms_actief" value="1" <?= instelling('sms.actief','0')==='1'?'checked':'' ?>>
          SMS inschakelen
        </label>

        <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px">Gateway (verzendmethode)</label>
        <select name="sms_gateway" id="smsGateway" onchange="smsGatewayToon()" style="width:100%;max-width:360px;margin-bottom:6px">
          <option value="">— kies —</option>
          <option value="eigen_telefoon" <?= $sms_gw==='eigen_telefoon'?'selected':'' ?>>Eigen telefoon / VoIP (goedkoopst)</option>
          <option value="http_gateway" <?= $sms_gw==='http_gateway'?'selected':'' ?>>Generieke HTTP-gateway</option>
          <option value="easysendsms" <?= $sms_gw==='easysendsms'?'selected':'' ?>>EasySendSMS (pay-as-you-go)</option>
          <option value="messagebird" <?= $sms_gw==='messagebird'?'selected':'' ?>>MessageBird / Bird (NL)</option>
        </select>
        <p style="font-size:12px;color:var(--tekst-zacht);margin:0 0 12px">
          <strong>Goedkoopst:</strong> "Eigen telefoon / VoIP" — je gebruikt je eigen (VoIP- of Android-)nummer met
          een app of dienst die een simpel webadres aanbiedt om een SMS te sturen. Je betaalt dan alleen je eigen
          bundel, geen kosten per bericht. De andere opties rekenen per bericht (ca. 1 cent), maar zijn kant-en-klaar.
        </p>

        <div style="margin-bottom:10px">
          <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px">Afzendernaam</label>
          <input type="text" name="sms_afzender" value="<?= h(instelling('sms.afzender','Paal13')) ?>" style="width:100%;max-width:200px" maxlength="11">
          <span style="font-size:11px;color:var(--tekst-zacht)">max 11 tekens</span>
        </div>

        <!-- HTTP / eigen telefoon velden -->
        <div class="sms-veld" data-voor="eigen_telefoon http_gateway" style="display:none">
          <label style="display:block;font-size:13px;font-weight:600;margin:8px 0 4px">Webadres (URL) van de gateway</label>
          <input type="text" name="sms_http_url" value="<?= h(instelling('sms.http_url','')) ?>" style="width:100%" placeholder="https://.../send?to={nummer}&text={tekst}">
          <p style="font-size:11px;color:var(--tekst-zacht);margin:2px 0 8px">Je mag <code>{nummer}</code> en <code>{tekst}</code> als plek-houders gebruiken. Anders worden ze als POST-velden meegestuurd.</p>
          <div style="display:flex;gap:10px;flex-wrap:wrap">
            <div>
              <label style="display:block;font-size:12px;margin-bottom:2px">Methode</label>
              <select name="sms_http_method" style="width:100px">
                <option value="POST" <?= instelling('sms.http_method','POST')==='POST'?'selected':'' ?>>POST</option>
                <option value="GET" <?= instelling('sms.http_method','POST')==='GET'?'selected':'' ?>>GET</option>
              </select>
            </div>
            <div style="flex:1;min-width:180px">
              <label style="display:block;font-size:12px;margin-bottom:2px">Token (optioneel)</label>
              <input type="text" name="sms_http_token" value="<?= h(instelling('sms.http_token','')) ?>" style="width:100%">
            </div>
          </div>
        </div>

        <!-- EasySendSMS -->
        <div class="sms-veld" data-voor="easysendsms" style="display:none">
          <label style="display:block;font-size:13px;font-weight:600;margin:8px 0 4px">EasySendSMS API-sleutel</label>
          <input type="text" name="sms_easysendsms_key" value="<?= h(instelling('sms.easysendsms_key','')) ?>" style="width:100%">
        </div>

        <!-- MessageBird -->
        <div class="sms-veld" data-voor="messagebird" style="display:none">
          <label style="display:block;font-size:13px;font-weight:600;margin:8px 0 4px">MessageBird API-sleutel</label>
          <input type="text" name="sms_messagebird_key" value="<?= h(instelling('sms.messagebird_key','')) ?>" style="width:100%">
        </div>
      </div>

      <!-- WhatsApp -->
      <div style="background:var(--bg-card,#fff);border:1px solid var(--rand);border-radius:10px;padding:16px;margin-bottom:16px">
        <label class="inst-toggle" style="display:flex;align-items:center;gap:10px;font-weight:600;font-size:15px;margin-bottom:10px">
          <input type="checkbox" name="wa_actief" value="1" <?= instelling('wa.actief','0')==='1'?'checked':'' ?>>
          WhatsApp inschakelen (via WhatsReach)
        </label>
        <div style="padding:10px 14px;background:var(--oranje-bg);border:1px solid var(--oranje);border-radius:8px;font-size:12.5px;color:var(--oranje);margin-bottom:12px">
          <strong>Let op:</strong> WhatsReach werkt buiten de officiële WhatsApp Business API om. Dit brengt een
          reëel risico met zich mee dat het gebruikte WhatsApp-nummer door WhatsApp geblokkeerd wordt. Gebruik bij
          voorkeur een apart nummer, en stuur alleen naar mensen die contact via WhatsApp verwachten. Dit kanaal
          staat daarom standaard uit.
        </div>
        <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px">WhatsReach API-sleutel <span style="font-weight:400;color:var(--ink-muted)">(X-API-Key)</span></label>
        <input type="text" name="wa_api_sleutel" value="<?= h(instelling('wa.api_sleutel','')) ?>" style="width:100%;margin-bottom:8px" placeholder="je API-sleutel">
        <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px">Base URL</label>
        <input type="text" name="wa_base_url" value="<?= h(instelling('wa.base_url','https://api.whatsreach.co/api/v1')) ?>" style="width:100%;margin-bottom:8px">
        <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px">Afzendernummer <span style="font-weight:400;color:var(--ink-muted)">(optioneel — bij meerdere gekoppelde apparaten)</span></label>
        <input type="text" name="wa_afzender_nummer" value="<?= h(instelling('wa.afzender_nummer','')) ?>" style="width:100%" placeholder="+31612345678">
        <p style="font-size:12px;color:var(--ink-muted);margin:6px 0 0">Het WhatsApp-nummer waarvandaan berichten verstuurd worden (het "from"-apparaat uit WhatsReach). Laat leeg als je maar één apparaat hebt gekoppeld.</p>
      </div>

      <!-- Kanaal per melding -->
      <div style="background:var(--bg-card,#fff);border:1px solid var(--rand);border-radius:10px;padding:16px;margin-bottom:16px">
        <div style="font-weight:600;font-size:15px;margin-bottom:6px">Welke melding via welk kanaal?</div>
        <p style="font-size:12px;color:var(--tekst-zacht);margin:0 0 12px">
          <strong>E-mail</strong> = alleen e-mail. <strong>WhatsApp/SMS</strong> = kort bericht via het kanaal dat de sollicitant koos (standaard WhatsApp; SMS als WhatsApp niet kan);
          lukt dat niet, dan alsnog per e-mail. <strong>Beide</strong> = e-mail én kort bericht. <strong>Uit</strong> = niet versturen.
          Ouders en het bestuur krijgen altijd e-mail. Teksten bewerken: <a href="<?= APP_URL ?>/admin/mail_templates.php" style="color:var(--oranje)">Sjablonen</a>.
        </p>
        <?php if (function_exists('meldingen_per_categorie')): $kmodi = meldingen_kanaalmodi(); foreach (meldingen_per_categorie() as $ck => $typen): if ($ck === 'handmatig') continue; ?>
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--grijs);margin:12px 0 4px"><?= h(meldingen_categorieen()[$ck] ?? $ck) ?></div>
        <table style="width:100%;border-collapse:collapse;font-size:13px">
          <?php foreach ($typen as $type => $mdef): $mm = melding_kanaalmodus($type); ?>
          <tr style="border-bottom:1px solid var(--rand)">
            <td style="padding:7px 8px"><a href="<?= APP_URL ?>/admin/mail_templates.php?slug=<?= h($type) ?>" style="color:inherit;text-decoration:none"><?= h($mdef['naam']) ?></a>
              <div style="font-size:11px;color:var(--tekst-zacht)"><?= h($mdef['beschrijving'] ?? '') ?></div></td>
            <td style="padding:7px 8px;width:220px;text-align:right">
              <select name="notif[<?= h($type) ?>]" style="font-size:12px;padding:5px 8px;width:200px">
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

      <button type="submit" class="btn btn-primary">Opslaan</button>
    </form>

    <!-- Test -->
    <div style="background:var(--bg);border:1px solid var(--rand);border-radius:10px;padding:16px;margin-top:16px">
      <div style="font-weight:600;font-size:14px;margin-bottom:8px">Testbericht sturen</div>
      <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="bericht_test">
        <div>
          <label style="display:block;font-size:12px;margin-bottom:2px">Telefoonnummer</label>
          <input type="text" name="test_nummer" placeholder="06..." style="width:160px">
        </div>
        <div>
          <label style="display:block;font-size:12px;margin-bottom:2px">E-mail (terugval)</label>
          <input type="email" name="test_email" value="<?= h($_SESSION['bhd_email'] ?? '') ?>" style="width:220px">
        </div>
        <div>
          <label style="display:block;font-size:12px;margin-bottom:2px">Via</label>
          <select name="test_kanaal" style="width:120px">
            <option value="wa">WhatsApp</option>
            <option value="sms">SMS</option>
            <option value="email">E-mail</option>
          </select>
        </div>
        <button type="submit" class="btn btn-outline btn-sm">Test versturen</button>
      </form>
    </div>
  </div>

  <script>
    function smsGatewayToon() {
      var gw = document.getElementById('smsGateway').value;
      document.querySelectorAll('.sms-veld').forEach(function(el){
        el.style.display = el.dataset.voor.split(' ').includes(gw) ? '' : 'none';
      });
    }
    smsGatewayToon();
  </script>

<?php endif; ?>

</div>
</div>

<script>
// Loon preview live bijwerken
function updatePreview(input, leeftijd, periode) {
  const v = parseFloat(input.value);
  const el = document.getElementById('prev-' + periode + '-' + leeftijd);
  if (!el) return;
  if (!v || isNaN(v)) { el.textContent = '—'; return; }
  // Factor lifeguard 1.2, VT 1.08, 8 uur
  const dl = Math.round(v * 8 * 1.2 * 1.08 * 100) / 100;
  el.textContent = '€' + dl.toFixed(2).replace('.', ',');
}

// Delay veld tonen/verbergen
document.querySelector('select[name="inst[invite.modus]"]')?.addEventListener('change', function() {
  const df = document.getElementById('delay_veld');
  if (df) df.style.display = this.value === 'auto' ? '' : 'none';
});
</script>

<?php require __DIR__ . '/layout_bottom.php'; ?>