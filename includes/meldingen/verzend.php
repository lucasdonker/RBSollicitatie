<?php
/**
 * Paal 13 — Meldingen: de verzendkern.
 *
 *   melding_verstuur($type, $sol_id, $extra, $opties)
 *
 * Eén ingang voor élke melding (e-mail, WhatsApp, SMS). Bepaalt ontvanger en kanalen, rendert
 * het sjabloon, zet het bericht in de wachtrij (mail_queue) mét een logregel (mail_log) en
 * verstuurt: direct (synchroon), ná het antwoord aan de gebruiker (shutdown, PHP-FPM) of via
 * de cron. Bij een kort kanaal (WhatsApp/SMS) is e-mail altijd de terugval.
 *
 * Logregel (mail_log): status wacht → bezig → verstuurd | mislukt | overgeslagen. Bij 'overgeslagen'
 * staat de reden in `foutmelding` (dubbel, afgemeld, geen_adres, plafond, uit). `kanaal` is het
 * kanaal waarmee het uiteindelijk gelukt is; `fallback_van` het kanaal dat eerst mislukte.
 *
 * Kanaalplan per item: 'email' | 'kort' (whatsapp → sms → email) | 'beide' (twee items: e-mail
 * én whatsapp → sms zonder e-mail-terugval, want die mail gaat al).
 */

if (!function_exists('melding_verstuur')) {

// ─────────────────────────────────────────────────────────────
//  Schema
// ─────────────────────────────────────────────────────────────

/** Kolommen op mail_log en mail_queue voor de centrale meldingen. */
function meldingen_log_auto_setup(): void {
    static $done = false; if ($done) return; $done = true;
    if (!function_exists('db_kolom_verzeker')) return;
    try {
        $db = db();
        if (function_exists('mail_queue_auto_setup')) mail_queue_auto_setup();
        if (function_exists('db_tabel_bestaat') && !db_tabel_bestaat($db, 'mail_log')) {
            $db->exec("CREATE TABLE IF NOT EXISTS `mail_log` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `sollicitatie_id` INT DEFAULT NULL,
                `type` VARCHAR(50) NOT NULL DEFAULT 'mail',
                `ontvanger` VARCHAR(200) NOT NULL,
                `onderwerp` VARCHAR(300) NOT NULL,
                `verstuurd_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `status` ENUM('wacht','bezig','verstuurd','mislukt','overgeslagen') NOT NULL DEFAULT 'verstuurd',
                `foutmelding` TEXT DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        foreach ([
            'kanaal'        => "VARCHAR(10) NOT NULL DEFAULT 'email'",
            'meldingstype'  => 'VARCHAR(50) DEFAULT NULL',
            'categorie'     => 'VARCHAR(20) DEFAULT NULL',
            'ontvanger_rol' => "VARCHAR(15) NOT NULL DEFAULT 'sollicitant'",
            'persoon_id'    => 'INT DEFAULT NULL',
            'samenvatting'  => 'VARCHAR(300) DEFAULT NULL',
            'body'          => 'MEDIUMTEXT DEFAULT NULL',
            'fallback_van'  => 'VARCHAR(10) DEFAULT NULL',
            'referentie'    => 'VARCHAR(60) DEFAULT NULL',
            'queue_id'      => 'INT DEFAULT NULL',
            'pogingen'      => 'TINYINT NOT NULL DEFAULT 0',
            'bijgewerkt_op' => 'DATETIME DEFAULT NULL',
        ] as $k => $def) db_kolom_verzeker($db, 'mail_log', $k, $def);

        // Status-enum uitbreiden (wacht/bezig/overgeslagen) als dat nog niet is gebeurd.
        try {
            $col = $db->query("SHOW COLUMNS FROM mail_log LIKE 'status'")->fetch();
            if ($col && stripos($col['Type'] ?? '', "'wacht'") === false) {
                $db->exec("ALTER TABLE mail_log MODIFY COLUMN status ENUM('wacht','bezig','verstuurd','mislukt','overgeslagen') NOT NULL DEFAULT 'verstuurd'");
            }
        } catch (\Throwable $e) { error_log('mail_log status-enum: ' . $e->getMessage()); }
        foreach (['idx_ml_sol_type' => '(sollicitatie_id, meldingstype, verstuurd_op)', 'idx_ml_kanaal' => '(kanaal, verstuurd_op)', 'idx_ml_ontv' => '(ontvanger, verstuurd_op)'] as $naam => $kol) {
            try {
                $q = $db->prepare("SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mail_log' AND INDEX_NAME=?");
                $q->execute([$naam]);
                if (!$q->fetchColumn()) $db->exec("ALTER TABLE mail_log ADD INDEX `$naam` $kol");
            } catch (\Throwable $e) {}
        }

        foreach ([
            'log_id'        => 'INT DEFAULT NULL',
            'kanaal_plan'   => "VARCHAR(40) NOT NULL DEFAULT 'email'",
            'telefoon'      => 'VARCHAR(30) DEFAULT NULL',
            'tekst_kort'    => 'TEXT DEFAULT NULL',
            'meldingstype'  => 'VARCHAR(50) DEFAULT NULL',
            'prioriteit'    => "VARCHAR(12) NOT NULL DEFAULT 'queue'",
            'bijlagen_json' => 'TEXT DEFAULT NULL',
            'ontvanger_rol' => "VARCHAR(15) NOT NULL DEFAULT 'sollicitant'",
        ] as $k => $def) db_kolom_verzeker($db, 'mail_queue', $k, $def);
    } catch (\Throwable $e) { error_log('meldingen_log_auto_setup: ' . $e->getMessage()); }
}

/** Kanaalvoorkeur per persoon. Standaard WhatsApp (auto opt-in); afmelden kan in het portaal of beheer. */
function personen_kanaal_auto_setup(): void {
    static $done = false; if ($done) return; $done = true;
    if (!function_exists('db_kolom_verzeker')) return;
    try {
        $db = db();
        db_kolom_verzeker($db, 'personen', 'kanaal_voorkeur',     "ENUM('email','whatsapp','sms') NOT NULL DEFAULT 'whatsapp'");
        db_kolom_verzeker($db, 'personen', 'kanaal_gewijzigd_op', 'DATETIME DEFAULT NULL');
        db_kolom_verzeker($db, 'personen', 'kanaal_bron',         'VARCHAR(20) DEFAULT NULL');
    } catch (\Throwable $e) { error_log('personen_kanaal_auto_setup: ' . $e->getMessage()); }
}

/** Het bestuursadres: instelling, anders de constante uit config.php. */
function bestuur_email(): string {
    $v = function_exists('instelling') ? trim(instelling('mail.bestuur_email', '')) : '';
    if ($v !== '') return $v;
    return defined('MAIL_BESTUUR') ? (string)MAIL_BESTUUR : '';
}

/** Het adres van de penningmeester (loonadministratie): instelling, anders het bestuursadres. */
function penningmeester_email(): string {
    $v = function_exists('instelling') ? trim(instelling('mail.penningmeester_email', '')) : '';
    return $v !== '' ? $v : bestuur_email();
}

// ─────────────────────────────────────────────────────────────
//  Dispatch
// ─────────────────────────────────────────────────────────────

/**
 * Verstuur een melding.
 *
 * @param string $type    sleutel uit meldingen_register()
 * @param int    $sol_id  sollicitatie (0 = geen; dan moet $opties['ontvanger'] gevuld zijn)
 * @param array  $extra   extra sjabloonvariabelen (bij 'handmatig': onderwerp, body_html, tekst_kort)
 * @param array  $opties  ontvanger (['email','naam','telefoon','rol']), kanaalmodus, prioriteit
 *                        ('direct'|'na_antwoord'|'queue'), bijlagen, dedup_min, referentie,
 *                        negeer_plafond, voetnoot_html
 * @return array ['status'=>'wacht|verstuurd|mislukt|overgeslagen','reden'=>?string,'log_id'=>?int,'kanaal'=>?string,'items'=>[]]
 */
function melding_verstuur(string $type, int $sol_id, array $extra = [], array $opties = []): array {
    $uit = ['status' => 'overgeslagen', 'reden' => null, 'log_id' => null, 'kanaal' => null, 'items' => []];
    try {
        meldingen_log_auto_setup();
        personen_kanaal_auto_setup();
        $def = function_exists('melding_definitie') ? melding_definitie($type) : null;
        if (!$def) { $uit['status'] = 'mislukt'; $uit['reden'] = 'onbekend_type'; error_log("melding_verstuur: onbekend type $type"); return $uit; }

        $modus = $opties['kanaalmodus'] ?? melding_kanaalmodus($type);
        if (!$def['kort_toegestaan'] && $modus !== 'uit') $modus = 'email';
        if ($modus === 'uit') { $uit['reden'] = 'uit'; melding_audit($type, $sol_id, null, 'overgeslagen', 'uit'); return $uit; }

        $ontv = melding_ontvanger($def, $sol_id, $opties);
        $vars = melding_vars($sol_id, $extra);
        if ($ontv) {
            $vars['ontvanger_naam'] = $vars['ontvanger_naam'] ?? ($ontv['naam'] ?? '');
            if ($def['ontvanger'] === 'ouder' && empty($vars['ouder_naam'])) $vars['ouder_naam'] = $ontv['naam'] ?? '';
        }
        $override = $opties['override'] ?? [];
        if (in_array($def['categorie'], ['handmatig', 'gepland'], true)) {
            // Niet-transactioneel: afmeldvoet verplicht (AVG/Telecommunicatiewet)
            $voet = $opties['voetnoot_html'] ?? '';
            if ($voet === '' && !empty($vars['afmeld_url'])) {
                $voet = '<p style="font-size:11px;color:#999;margin-top:24px;border-top:1px solid #eee;padding-top:12px">Je ontvangt dit bericht omdat je je hebt aangemeld bij Reddingsbrigade Paal 13. Geen berichten meer? <a href="' . htmlspecialchars((string)$vars['afmeld_url'], ENT_QUOTES, 'UTF-8') . '" style="color:#999">Afmelden</a>.</p>';
            }
            if ($voet !== '') $override['voetnoot_html'] = $voet;
        }
        // Bijlagen: uit het sjabloon (bijlagen_json) + meegegeven. Te groot voor e-mail → downloadknop in de mail.
        $sj_bijl = [];
        $sj_json = $override['bijlagen_json'] ?? (function_exists('sjabloon_laden') ? (sjabloon_laden($type)['bijlagen_json'] ?? null) : null);
        if ($sj_json) { $j = json_decode((string)$sj_json, true); if (is_array($j)) $sj_bijl = $j; }
        $bijl = melding_bijlagen_verdeel(melding_bijlagen_resolve(melding_bijlagen_normaliseer(array_merge($sj_bijl, $opties['bijlagen'] ?? []))));
        if ($bijl['links']) $override['bijlagen_blok_html'] = melding_bijlagen_blok_html($bijl['links']);

        $r = melding_render($type, $vars, $override);
        if (!$r) { $uit['status'] = 'mislukt'; $uit['reden'] = 'geen_sjabloon'; return $uit; }
        // Eén keer, bij het allereerste WhatsApp/SMS-bericht aan deze persoon: uitleg + hoe je afmeldt.
        if ($r['tekst_kort'] !== '' && !empty($ontv['persoon_id']) && $modus !== 'email' && !melding_eerder_kort((int)$ontv['persoon_id'])) {
            $r['tekst_kort'] = rtrim($r['tekst_kort']) . "\n\nZo houden we je via WhatsApp of sms op de hoogte van je sollicitatie bij Paal 13. Liever alleen e-mail? Pas dat aan onder Mijn gegevens in je portaal: " . ($vars['portaal_url'] ?? '') . 'gegevens.php';
        }

        $basis = [
            'sollicitatie_id' => $sol_id ?: null, 'persoon_id' => $ontv['persoon_id'] ?? null,
            'meldingstype' => $type, 'categorie' => $def['categorie'], 'ontvanger_rol' => $ontv['rol'] ?? $def['ontvanger'],
            'ontvanger' => $ontv['email'] ?? '', 'naam' => $ontv['naam'] ?? '', 'telefoon' => $ontv['telefoon'] ?? '',
            'onderwerp' => $r['onderwerp'], 'samenvatting' => $def['body_loggen'] ? $r['samenvatting'] : '',
            'referentie' => $opties['referentie'] ?? null,
        ];

        // Geen adres → overgeslagen (met logregel, zodat het zichtbaar is)
        if (!$ontv || (empty($ontv['email']) && empty($ontv['telefoon']))) {
            $uit['reden'] = 'geen_adres';
            $uit['log_id'] = melding_log_overgeslagen($basis, 'geen_adres');
            return $uit;
        }
        // Dubbelcheck op data (type + sollicitatie + referentie binnen venster)
        $dedup = (int)($opties['dedup_min'] ?? $def['dedup_min']);
        if ($dedup > 0 && $sol_id > 0 && melding_recent($type, $sol_id, $dedup, $basis['referentie'])) {
            $uit['reden'] = 'dubbel'; melding_audit($type, $sol_id, null, 'overgeslagen', 'dubbel'); return $uit;
        }
        // Opt-out (alleen niet-transactionele berichten)
        if (in_array($def['categorie'], ['handmatig', 'gepland'], true) && !empty($ontv['afgemeld']) && empty($opties['negeer_afmelding'])) {
            $uit['reden'] = 'afgemeld'; $uit['log_id'] = melding_log_overgeslagen($basis, 'afgemeld'); return $uit;
        }
        // Dagplafond per ontvanger (anti-loop); systeem, handmatig en interne adressen uitgezonderd
        if (!in_array($def['categorie'], ['systeem', 'handmatig'], true) && empty($opties['negeer_plafond'])
            && !in_array($basis['ontvanger_rol'], ['bestuur', 'penningmeester'], true)
            && melding_plafond_bereikt($basis['ontvanger'] ?: $basis['telefoon'], $type)) {
            $uit['reden'] = 'plafond'; $uit['log_id'] = melding_log_overgeslagen($basis, 'plafond');
            if (function_exists('p13_fout')) p13_fout('P13-M004', "type $type, log #{$uit['log_id']}");
            return $uit;
        }

        // Een bestand kan alleen per e-mail mee: bij 'kort' dan óók e-mail (kort krijgt een downloadlink).
        $bijlagen = ($bijl['mee'] || $bijl['links']) ? ['mee' => $bijl['mee'], 'kort' => $bijl['kort']] : [];
        // Reserve: weigert de mailserver de bijlage (te groot), dan opnieuw met downloadknoppen i.p.v. bijlagen.
        if ($bijlagen && $bijl['kort']) $bijlagen['reserve_html'] = melding_bijlagen_blok_html(array_map(fn($k) => $k + ['grootte' => 0], $bijl['kort']));
        if ($bijlagen && $modus === 'kort') $modus = 'beide';
        $plannen = melding_kanaalplan($modus, $ontv, $def, $r['tekst_kort'] !== '');
        $prio = $opties['prioriteit'] ?? 'direct';
        if ($prio === 'na_antwoord' && (PHP_SAPI === 'cli' || !function_exists('fastcgi_finish_request'))) $prio = 'direct';
        $ids = [];
        foreach ($plannen as $plan) {
            $item = array_merge($basis, [
                'kanaal_plan' => implode(',', $plan), 'html' => $r['html'], 'body_log' => $def['body_loggen'] ? ($plan[0] === 'email' ? $r['body_html'] : $r['tekst_kort']) : null,
                'tekst_kort' => $r['tekst_kort'], 'bijlagen' => $bijlagen, 'prioriteit' => $prio,
            ]);
            $log_id = melding_queue_toevoegen($item, $prio);
            if ($log_id) $ids[] = $log_id;
        }
        if (!$ids) { $uit['status'] = 'mislukt'; $uit['reden'] = 'queue'; return $uit; }
        $uit['log_id'] = $ids[0];
        $uit['status'] = 'wacht';

        if ($prio === 'direct') {
            $res = melding_queue_verwerk(count($ids), $ids);
            $uit['items'] = $res['items'] ?? [];
            $eerste = $uit['items'][0] ?? null;
            if ($eerste) { $uit['status'] = $eerste['status']; $uit['kanaal'] = $eerste['kanaal'] ?? null; $uit['reden'] = $eerste['fout'] ?? null; }
        } elseif ($prio === 'na_antwoord') {
            foreach ($ids as $id) melding_flush_registreer($id);
        }
        return $uit;
    } catch (\Throwable $e) {
        error_log('melding_verstuur ' . $type . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
        if (function_exists('p13_fout')) p13_fout('P13-M002', "melding_verstuur($type): " . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
        $uit['status'] = 'mislukt'; $uit['reden'] = 'exceptie: ' . $e->getMessage();
        return $uit;
    }
}

/**
 * Wie krijgt de melding? Op basis van de ontvanger-rol uit de definitie.
 * Retourneert ['email','naam','telefoon','persoon_id','rol','kanaal_voorkeur','afgemeld'] of null.
 */
function melding_ontvanger(array $def, int $sol_id, array $opties): ?array {
    $rol = $def['ontvanger'];
    $basis = ['email' => '', 'naam' => '', 'telefoon' => '', 'persoon_id' => null, 'rol' => $rol, 'kanaal_voorkeur' => 'email', 'afgemeld' => false];

    // Expliciete ontvanger (direct, ondertekenaar, test)
    if (!empty($opties['ontvanger']) && is_array($opties['ontvanger'])) {
        $o = array_merge($basis, $opties['ontvanger']);
        $o['rol'] = $opties['ontvanger']['rol'] ?? ($rol === 'direct' ? 'direct' : $rol);
        if ($o['persoon_id']) $o = array_merge($o, melding_persoon_kanaal((int)$o['persoon_id']));
        elseif (!empty($opties['ontvanger']['kanaal_voorkeur'])) $o['kanaal_voorkeur'] = $opties['ontvanger']['kanaal_voorkeur'];
        return $o;
    }
    if ($rol === 'bestuur') {
        $e = bestuur_email();
        return $e === '' ? null : array_merge($basis, ['email' => $e, 'naam' => 'Bestuur', 'rol' => 'bestuur']);
    }
    if ($rol === 'penningmeester') {
        $e = penningmeester_email();
        return $e === '' ? null : array_merge($basis, ['email' => $e, 'naam' => 'Penningmeester', 'rol' => 'penningmeester']);
    }
    if ($rol === 'direct') return null;

    if ($sol_id <= 0) return null;
    $rij = function_exists('portaal_sollicitant_laden') ? portaal_sollicitant_laden($sol_id) : null;
    if (!$rij) return null;
    $pid = (int)($rij['persoon_id'] ?? 0);
    $pk  = $pid ? melding_persoon_kanaal($pid) : [];

    if ($rol === 'ouder') {
        $oe = trim((string)($pk['ouder_email'] ?? ''));
        if ($oe === '') return null;
        return array_merge($basis, ['email' => $oe, 'naam' => (string)($pk['ouder_naam'] ?: 'ouder/verzorger'), 'telefoon' => '', 'persoon_id' => $pid, 'rol' => 'ouder', 'afgemeld' => false]);
    }
    // sollicitant (ook standaard voor 'ondertekenaar' zonder expliciete ontvanger)
    $naam = function_exists('volledige_naam') ? volledige_naam($rij) : trim(($rij['voornaam'] ?? '') . ' ' . ($rij['achternaam'] ?? ''));
    return array_merge($basis, [
        'email' => (string)($rij['email'] ?? ''), 'naam' => $naam, 'telefoon' => (string)($rij['telefoon'] ?? ''),
        'persoon_id' => $pid, 'rol' => 'sollicitant',
    ], $pk);
}

/** Kanaalvoorkeur, afmelding en oudergegevens van een persoon (één query). */
function melding_persoon_kanaal(int $persoon_id): array {
    try {
        $q = db()->prepare("SELECT kanaal_voorkeur, mailing_afgemeld, ouder_naam, ouder_email FROM personen WHERE id=?");
        $q->execute([$persoon_id]);
        $p = $q->fetch();
        if (!$p) return [];
        return [
            'kanaal_voorkeur' => (string)($p['kanaal_voorkeur'] ?: 'whatsapp'),
            'afgemeld'        => (int)($p['mailing_afgemeld'] ?? 0) === 1,
            'ouder_naam'      => $p['ouder_naam'] ?? '', 'ouder_email' => $p['ouder_email'] ?? '',
        ];
    } catch (\Throwable $e) { return []; }
}

/**
 * Kanaalplannen voor een ontvanger. Elk plan is een lijst kanalen in volgorde van proberen.
 *   email → [[email]]
 *   kort  → [[whatsapp, sms, email]] (naar voorkeur; zonder telefoon/kort kanaal: [[email]])
 *   beide → [[email], [whatsapp, sms]]
 */
function melding_kanaalplan(string $modus, array $ontv, array $def, bool $heeft_kort = true): array {
    $email_ok = !empty($ontv['email']);
    $tel      = trim((string)($ontv['telefoon'] ?? ''));
    $voorkeur = (string)($ontv['kanaal_voorkeur'] ?? 'email');
    $wa_ok    = function_exists('wa_actief') && wa_actief();
    $sms_ok   = function_exists('sms_actief') && sms_actief();

    $kort = [];
    if ($heeft_kort && $tel !== '' && !empty($def['kort_toegestaan']) && $voorkeur !== 'email') {
        if ($voorkeur === 'whatsapp' && $wa_ok) $kort[] = 'whatsapp';
        if ($sms_ok) $kort[] = 'sms';
        if ($voorkeur === 'sms' && $wa_ok && !$sms_ok) $kort = []; // sms gekozen maar niet beschikbaar → alleen e-mail
    }
    if ($modus === 'email' || !$kort) return $email_ok ? [['email']] : ($kort ? [$kort] : []);
    if ($modus === 'kort') { $plan = $kort; if ($email_ok) $plan[] = 'email'; return [$plan]; }
    // beide
    $uit = [];
    if ($email_ok) $uit[] = ['email'];
    $uit[] = $kort;
    return $uit;
}

/** Is dit type recent al (in wachtrij of verstuurd) voor deze sollicitatie (+ referentie)? */
function melding_recent(string $type, int $sol_id, int $venster_min, ?string $referentie = null): bool {
    try {
        $sql = "SELECT 1 FROM mail_log WHERE sollicitatie_id=? AND meldingstype=? AND status IN ('wacht','bezig','verstuurd')
                AND verstuurd_op > DATE_SUB(NOW(), INTERVAL ? MINUTE)";
        $p = [$sol_id, $type, $venster_min];
        if ($referentie !== null && $referentie !== '') { $sql .= " AND referentie=?"; $p[] = $referentie; }
        $q = db()->prepare($sql . " LIMIT 1");
        $q->execute($p);
        return (bool)$q->fetchColumn();
    } catch (\Throwable $e) { return false; }
}

/** Kreeg deze persoon al eerder een WhatsApp/SMS-bericht (in wachtrij of verstuurd)? */
function melding_eerder_kort(int $persoon_id): bool {
    try {
        $q = db()->prepare("SELECT 1 FROM mail_log WHERE persoon_id=? AND kanaal IN ('whatsapp','sms') AND status IN ('wacht','bezig','verstuurd') LIMIT 1");
        $q->execute([$persoon_id]);
        return (bool)$q->fetchColumn();
    } catch (\Throwable $e) { return true; }
}

/**
 * Anti-loop: meer dan N keer dezelfde automatische melding naar hetzelfde adres in 24 uur.
 * Bewust per meldingstype — een loop herhaalt altijd hetzelfde bericht, terwijl verschillende
 * berichten op één dag (fase, taak, herinnering) gewoon legitiem zijn. Zonder type valt het
 * terug op de oude telling over alle automatische categorieën.
 */
function melding_plafond_bereikt(string $adres, string $type = ''): bool {
    if ($adres === '') return false;
    $max = function_exists('instelling_int') ? max(1, instelling_int('melding.plafond_dag', 8)) : 8;
    try {
        $sql = "SELECT COUNT(*) FROM mail_log WHERE ontvanger=? AND categorie IN ('fase','taak','herinnering','bestuur','gepland')
                AND status IN ('wacht','bezig','verstuurd') AND verstuurd_op > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $p = [$adres];
        if ($type !== '') { $sql .= " AND meldingstype=?"; $p[] = $type; }
        $q = db()->prepare($sql);
        $q->execute($p);
        return (int)$q->fetchColumn() >= $max;
    } catch (\Throwable $e) { return false; }
}

/** Logregel 'overgeslagen' met reden. Retourneert log-id (of null). */
function melding_log_overgeslagen(array $b, string $reden): ?int {
    try {
        $db = db();
        $db->prepare("INSERT INTO mail_log (sollicitatie_id, persoon_id, type, kanaal, meldingstype, categorie, ontvanger_rol, ontvanger, onderwerp, samenvatting, referentie, status, foutmelding, bijgewerkt_op)
                      VALUES (?,?,?,?,?,?,?,?,?,?,?,'overgeslagen',?,NOW())")
           ->execute([$b['sollicitatie_id'], $b['persoon_id'], 'mail', 'email', $b['meldingstype'], $b['categorie'], $b['ontvanger_rol'],
                      $b['ontvanger'] ?: ($b['telefoon'] ?? ''), $b['onderwerp'], $b['samenvatting'], $b['referentie'], $reden]);
        $id = (int)$db->lastInsertId();
        melding_audit($b['meldingstype'], (int)($b['sollicitatie_id'] ?? 0), $id, 'overgeslagen', $reden);
        return $id;
    } catch (\Throwable $e) { error_log('melding_log_overgeslagen: ' . $e->getMessage()); return null; }
}

/** Audit zonder PII: type, status, kanaal, log-id. */
function melding_audit(string $type, int $sol_id, ?int $log_id, string $status, ?string $detail = null, ?string $kanaal = null): void {
    if (!function_exists('audit_log')) return;
    try { audit_log('melding', 'sollicitatie', $sol_id ?: null, array_filter(['type' => $type, 'status' => $status, 'kanaal' => $kanaal, 'log_id' => $log_id, 'detail' => $detail])); }
    catch (\Throwable $e) {}
}

// ─────────────────────────────────────────────────────────────
//  Wachtrij
// ─────────────────────────────────────────────────────────────

/** Zet één item (logregel + wachtrijregel) klaar. Retourneert het log-id, of 0 bij een fout. */
function melding_queue_toevoegen(array $item, string $prioriteit): int {
    try {
        $db = db();
        $plan = explode(',', $item['kanaal_plan']);
        $db->prepare("INSERT INTO mail_log (sollicitatie_id, persoon_id, type, kanaal, meldingstype, categorie, ontvanger_rol, ontvanger, onderwerp, samenvatting, body, referentie, status, pogingen, bijgewerkt_op)
                      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'wacht',0,NOW())")
           ->execute([$item['sollicitatie_id'], $item['persoon_id'], $plan[0] === 'email' ? 'mail' : $plan[0], $plan[0], $item['meldingstype'], $item['categorie'],
                      $item['ontvanger_rol'], $plan[0] === 'email' ? $item['ontvanger'] : ($item['telefoon'] ?: $item['ontvanger']), $item['onderwerp'],
                      $item['samenvatting'], $item['body_log'], $item['referentie']]);
        $log_id = (int)$db->lastInsertId();
        $db->prepare("INSERT INTO mail_queue (naar_email, naar_naam, onderwerp, body, sol_id, status, log_id, kanaal_plan, telefoon, tekst_kort, meldingstype, prioriteit, bijlagen_json, ontvanger_rol)
                      VALUES (?,?,?,?,?,'wacht',?,?,?,?,?,?,?,?)")
           ->execute([$item['ontvanger'], $item['naam'], $item['onderwerp'], $item['html'], $item['sollicitatie_id'], $log_id, $item['kanaal_plan'],
                      $item['telefoon'] ?: null, $item['tekst_kort'], $item['meldingstype'], $prioriteit,
                      $item['bijlagen'] ? json_encode($item['bijlagen'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null, $item['ontvanger_rol']]);
        $qid = (int)$db->lastInsertId();
        $db->prepare("UPDATE mail_log SET queue_id=? WHERE id=?")->execute([$qid, $log_id]);
        return $log_id;
    } catch (\Throwable $e) {
        error_log('melding_queue_toevoegen: ' . $e->getMessage());
        if (function_exists('p13_fout')) p13_fout('P13-M002', 'wachtrij (' . ($item['meldingstype'] ?? '?') . '): ' . $e->getMessage());
        return 0;
    }
}

/**
 * Verwerk wachtende items. $alleen_log_ids beperkt tot specifieke items (directe verzending).
 * Retourneert tellers + per item de uitkomst.
 */
function melding_queue_verwerk(int $max = 8, ?array $alleen_log_ids = null): array {
    meldingen_log_auto_setup();
    $res = ['verwerkt' => 0, 'fouten' => 0, 'items' => []];
    try {
        if ($alleen_log_ids) {
            $in = implode(',', array_map('intval', $alleen_log_ids));
            $rows = db()->query("SELECT * FROM mail_queue WHERE status='wacht' AND log_id IN ($in) ORDER BY id ASC")->fetchAll();
        } else {
            $q = db()->prepare("SELECT * FROM mail_queue WHERE status='wacht' AND pogingen < 3 ORDER BY (prioriteit='queue') ASC, id ASC LIMIT ?");
            $q->bindValue(1, $max, PDO::PARAM_INT);
            $q->execute();
            $rows = $q->fetchAll();
        }
    } catch (\Throwable $e) { error_log('melding_queue_verwerk: ' . $e->getMessage()); return $res; }

    $kort_teller = 0;
    foreach ($rows as $m) {
        $plan = array_filter(explode(',', (string)($m['kanaal_plan'] ?? 'email')));
        $heeft_kort = (bool)array_intersect($plan, ['whatsapp', 'sms']);
        if ($heeft_kort && $alleen_log_ids === null && $kort_teller >= 20) continue; // max 20 korte berichten per cron-run
        try { db()->prepare("UPDATE mail_queue SET status='bezig', pogingen=pogingen+1 WHERE id=?")->execute([$m['id']]); } catch (\Throwable $e) {}
        $m['pogingen'] = (int)$m['pogingen'] + 1;
        $r = melding_verzend_item($m);
        if ($heeft_kort) $kort_teller++;
        $res['items'][] = ['log_id' => (int)$m['log_id'], 'status' => $r['status'], 'kanaal' => $r['kanaal'], 'fout' => $r['fout']];
        if ($r['status'] === 'verstuurd') $res['verwerkt']++; else $res['fouten']++;
        if (count($rows) > 1) usleep(200000); // SMTP/API niet overstelpen
    }
    return $res;
}

/**
 * Voer het kanaalplan van één wachtrij-item uit. Werkt log en wachtrij bij.
 * Korte kanalen alleen bij de eerste poging; herhalingen gaan alleen nog via e-mail.
 */
function melding_verzend_item(array $m): array {
    $plan = array_values(array_filter(explode(',', (string)($m['kanaal_plan'] ?? 'email'))));
    $poging = (int)($m['pogingen'] ?? 1);
    $fouten = []; $gelukt = null; $fallback_van = null;
    $log_id = (int)($m['log_id'] ?? 0);
    $bijlagen = []; $kort_links = []; $reserve_html = '';
    if (!empty($m['bijlagen_json'])) {
        $j = json_decode((string)$m['bijlagen_json'], true);
        if (is_array($j) && isset($j['mee'])) { $bijlagen = melding_bijlagen_resolve($j['mee'], true); $kort_links = $j['kort'] ?? []; $reserve_html = (string)($j['reserve_html'] ?? ''); }
        elseif (is_array($j)) { $bijlagen = melding_bijlagen_resolve($j, true); foreach ($bijlagen as $b) if (!empty($b['url'])) $kort_links[] = ['naam' => $b['naam_mooi'] ?? $b['naam'], 'url' => $b['url']]; }
    }
    // Tijdelijk ontsleutelde kluis-documenten na afloop altijd wissen (ook bij een fout)
    $tmp_wissen = function () use (&$bijlagen) { foreach ($bijlagen as $b) if (!empty($b['tmp'])) @unlink($b['tmp']); };
    // Korte kanalen: nette downloadlinks achter de tekst
    $tekst_kort = rtrim((string)$m['tekst_kort']) . melding_bijlagen_kort_tekst($kort_links);

    foreach ($plan as $kanaal) {
        if ($kanaal !== 'email' && $poging > 1) { $fouten[] = "$kanaal: overgeslagen bij herhaling"; continue; }
        $r = ['ok' => false, 'fout' => 'onbekend kanaal'];
        try {
            if ($kanaal === 'whatsapp') {
                $r = melding_kanaal_whatsapp((string)$m['telefoon'], $tekst_kort);
            } elseif ($kanaal === 'sms') {
                $r = (function_exists('sms_actief') && sms_actief() && $m['telefoon'])
                    ? melding_resultaat(function_exists('sms_verstuur_res') ? sms_verstuur_res((string)$m['telefoon'], $tekst_kort) : sms_verstuur((string)$m['telefoon'], $tekst_kort))
                    : ['ok' => false, 'fout' => 'sms niet beschikbaar'];
            } elseif ($kanaal === 'email') {
                if ($m['naar_email'] === '' || $m['naar_email'] === null) { $r = ['ok' => false, 'fout' => 'geen e-mailadres']; }
                elseif (class_exists('Mailer') && method_exists('Mailer', 'verzend')) {
                    $r = Mailer::verzend((string)$m['naar_email'], (string)($m['naar_naam'] ?? ''), (string)$m['onderwerp'], (string)$m['body'], $bijlagen);
                    // Mailserver weigert op grootte → nog één keer zonder bijlagen, met downloadknoppen
                    if (empty($r['ok']) && $bijlagen && $reserve_html !== '' && preg_match('/size|too (large|big)|exceed|552|limit/i', (string)($r['fout'] ?? ''))) {
                        $body2 = (string)$m['body'];
                        $blok  = function_exists('mail_shortcodes') ? mail_shortcodes($reserve_html) : $reserve_html;
                        $body2 = strpos($body2, '<!-- FOOTER -->') !== false ? str_replace('<!-- FOOTER -->', $blok . '<!-- FOOTER -->', $body2) : $body2 . $blok;
                        $r2 = Mailer::verzend((string)$m['naar_email'], (string)($m['naar_naam'] ?? ''), (string)$m['onderwerp'], $body2, []);
                        if (!empty($r2['ok'])) { $fouten[] = 'email: bijlage geweigerd door mailserver, verstuurd met downloadlink'; $r = $r2; }
                    }
                }
                else $r = ['ok' => false, 'fout' => 'mailer ontbreekt'];
            }
        } catch (\Throwable $e) { $r = ['ok' => false, 'fout' => $e->getMessage()]; }
        if (!empty($r['ok'])) { $gelukt = $kanaal; break; }
        $fouten[] = $kanaal . ': ' . substr((string)($r['fout'] ?? 'mislukt'), 0, 160);
        if ($fallback_van === null) $fallback_van = $kanaal;
        if ($kanaal === 'whatsapp') melding_wa_fout_registreer();
    }
    $tmp_wissen();

    $fouttekst = $fouten ? implode(' | ', $fouten) : null;
    try {
        $db = db();
        if ($gelukt) {
            $db->prepare("UPDATE mail_queue SET status='klaar', verwerkt_op=NOW(), fout=? WHERE id=?")->execute([$fouttekst, $m['id']]);
            if ($log_id) $db->prepare("UPDATE mail_log SET status='verstuurd', kanaal=?, type=?, ontvanger=?, fallback_van=?, foutmelding=?, pogingen=?, verstuurd_op=NOW(), bijgewerkt_op=NOW() WHERE id=?")
                ->execute([$gelukt, $gelukt === 'email' ? 'mail' : $gelukt, $gelukt === 'email' ? $m['naar_email'] : ($m['telefoon'] ?: $m['naar_email']), $gelukt !== $plan[0] ? $fallback_van : null, $fouttekst, $poging, $log_id]);
            melding_audit((string)($m['meldingstype'] ?? ''), (int)($m['sol_id'] ?? 0), $log_id, 'verstuurd', null, $gelukt);
            return ['status' => 'verstuurd', 'kanaal' => $gelukt, 'fout' => null];
        }
        // Niets gelukt: herhalen als e-mail in het plan zit en er pogingen over zijn
        $kan_herhalen = in_array('email', $plan, true) && $poging < 3;
        $db->prepare("UPDATE mail_queue SET status=?, fout=? WHERE id=?")->execute([$kan_herhalen ? 'wacht' : 'fout', $fouttekst, $m['id']]);
        if ($log_id) $db->prepare("UPDATE mail_log SET status=?, foutmelding=?, pogingen=?, bijgewerkt_op=NOW() WHERE id=?")
            ->execute([$kan_herhalen ? 'wacht' : 'mislukt', $fouttekst, $poging, $log_id]);
        if (!$kan_herhalen) {
            melding_audit((string)($m['meldingstype'] ?? ''), (int)($m['sol_id'] ?? 0), $log_id, 'mislukt', null, $plan[0]);
            if (function_exists('p13_fout')) p13_fout('P13-M002', "log #$log_id (" . ($m['meldingstype'] ?? '?') . ", " . implode('/', $plan) . ")");
        }
        return ['status' => $kan_herhalen ? 'wacht' : 'mislukt', 'kanaal' => null, 'fout' => $fouttekst];
    } catch (\Throwable $e) {
        error_log('melding_verzend_item: ' . $e->getMessage());
        return ['status' => 'mislukt', 'kanaal' => null, 'fout' => $e->getMessage()];
    }
}

/**
 * Bijlagen normaliseren: int of ['bijlage_id'=>n] (uit de bijlagenbibliotheek) of ['pad','naam','mime']
 * (tijdelijk bestand, bijv. een PDF van een ondertekening).
 */
function melding_bijlagen_normaliseer(array $lijst): array {
    $uit = [];
    foreach ($lijst as $b) {
        if (is_int($b) || (is_string($b) && ctype_digit($b))) { if ((int)$b > 0) $uit[] = ['bijlage_id' => (int)$b]; }
        elseif (is_array($b) && !empty($b['bijlage_id'])) $uit[] = ['bijlage_id' => (int)$b['bijlage_id']];
        elseif (is_array($b) && !empty($b['document_id'])) $uit[] = ['document_id' => (int)$b['document_id']];
        elseif (is_array($b) && !empty($b['pad'])) $uit[] = ['pad' => $b['pad'], 'naam' => $b['naam'] ?? basename($b['pad']), 'mime' => $b['mime'] ?? 'application/octet-stream'];
    }
    return $uit;
}

/**
 * Bijlagen klaarmaken voor verzending: [['id'=>?,'pad','naam','naam_mooi','mime','grootte','url'=>?]]. Ontbrekende bestanden vallen weg.
 * Documenten uit de kluis ('document_id') worden alleen op het verzendmoment ($materialiseer=true) ontsleuteld naar een
 * tijdelijk bestand ('tmp' => pad; de verzender wist het na afloop); in de wachtrij blijft alleen het id staan.
 */
function melding_bijlagen_resolve(array $lijst, bool $materialiseer = false): array {
    $uit = [];
    foreach ($lijst as $b) {
        if (!empty($b['bijlage_id'])) {
            if (function_exists('bijlagen_van_ids')) foreach (bijlagen_van_ids([(int)$b['bijlage_id']]) as $r) $uit[] = $r;
        } elseif (!empty($b['document_id'])) {
            if (!function_exists('document_rij')) continue;
            $d = document_rij((int)$b['document_id']);
            if (!$d || !in_array($d['status'], ['ingediend', 'verwerkt'], true)) continue;
            $rij = ['id' => null, 'document_id' => (int)$d['id'], 'pad' => null, 'naam' => $d['bestandsnaam'], 'naam_mooi' => function_exists('bijlage_naam_mooi') ? bijlage_naam_mooi((string)$d['bestandsnaam']) : $d['bestandsnaam'],
                    'mime' => $d['mime'], 'grootte' => (int)$d['grootte'], 'url' => null];
            if ($materialiseer) {
                $inhoud = document_inhoud($d);
                if ($inhoud === null) continue;
                $tmp = sys_get_temp_dir() . '/doc_' . (int)$d['id'] . '_' . bin2hex(random_bytes(4)) . '.bin';
                if (@file_put_contents($tmp, $inhoud) === false) continue;
                if (function_exists('kluis_wis')) kluis_wis($inhoud);
                $rij['pad'] = $tmp; $rij['tmp'] = $tmp;
            }
            $uit[] = $rij;
        } elseif (!empty($b['pad']) && is_file($b['pad'])) {
            $naam = $b['naam'] ?? basename($b['pad']);
            $uit[] = ['id' => null, 'pad' => $b['pad'], 'naam' => $naam, 'naam_mooi' => function_exists('bijlage_naam_mooi') ? bijlage_naam_mooi($naam) : $naam,
                      'mime' => $b['mime'] ?? 'application/octet-stream', 'grootte' => (int)(@filesize($b['pad']) ?: 0), 'url' => null];
        }
    }
    return $uit;
}

/**
 * Verdeel opgeloste bijlagen: 'mee' (als e-mailbijlage), 'links' (te groot → downloadknop in de mail),
 * 'kort' (naam + link voor WhatsApp/SMS; alle bibliotheek-bestanden).
 */
function melding_bijlagen_verdeel(array $res): array {
    $max = function_exists('bijlagen_mail_max_bytes') ? bijlagen_mail_max_bytes() : 8 * 1048576;
    $uit = ['mee' => [], 'links' => [], 'kort' => []];
    foreach ($res as $b) {
        $te_groot = (int)($b['grootte'] ?? 0) > $max && !empty($b['url']);
        if ($te_groot) $uit['links'][] = ['naam' => $b['naam_mooi'] ?? $b['naam'], 'url' => $b['url'], 'grootte' => (int)$b['grootte']];
        elseif (!empty($b['document_id'])) $uit['mee'][] = ['document_id' => (int)$b['document_id']]; // kluis: pas bij verzending ontsleutelen, nooit een downloadlink
        else $uit['mee'][] = !empty($b['id']) ? ['bijlage_id' => (int)$b['id']] : ['pad' => $b['pad'], 'naam' => $b['naam'], 'mime' => $b['mime']];
        if (!empty($b['url'])) $uit['kort'][] = ['naam' => $b['naam_mooi'] ?? $b['naam'], 'url' => $b['url']];
    }
    return $uit;
}

/** HTML-blok met downloadknoppen voor bestanden die te groot zijn als bijlage. */
function melding_bijlagen_blok_html(array $links): string {
    $h = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $uit = '<p style="margin:20px 0 6px"><strong>Download</strong></p>';
    foreach ($links as $l) {
        $mb = $l['grootte'] > 0 ? ' (' . round($l['grootte'] / 1048576, 1) . ' MB)' : '';
        $uit .= '[knop href="' . $h($l['url']) . '"]' . $h($l['naam']) . $mb . '[/knop]';
    }
    return $uit . '<p style="font-size:12px;color:#8a94a3">De downloadlink is 14 dagen geldig.</p>';
}

/** Tekst voor WhatsApp/SMS: leesbare naam + korte link, elk op een eigen regel. */
function melding_bijlagen_kort_tekst(array $kort): string {
    if (!$kort) return '';
    $uit = "\n";
    foreach ($kort as $l) $uit .= "\n" . (count($kort) > 1 ? 'Bijlage: ' : 'Bijlage: ') . $l['naam'] . "\n" . $l['url'];
    return $uit;
}

/** Normaliseer een kanaal-resultaat (bool of ['ok','fout']) naar ['ok','fout']. */
function melding_resultaat($r): array {
    if (is_array($r)) return ['ok' => !empty($r['ok']), 'fout' => $r['fout'] ?? null];
    return ['ok' => (bool)$r, 'fout' => $r ? null : 'mislukt'];
}

/** WhatsApp met uurlimiet en pauze na herhaalde fouten (WhatsReach-blokkeringsrisico). */
function melding_kanaal_whatsapp(string $telefoon, string $tekst): array {
    if (!function_exists('wa_actief') || !wa_actief()) return ['ok' => false, 'fout' => 'whatsapp niet actief'];
    if ($telefoon === '' || $tekst === '') return ['ok' => false, 'fout' => 'geen nummer of tekst'];
    $pauze = function_exists('instelling') ? instelling('wa.pauze_tot', '') : '';
    if ($pauze !== '' && strtotime($pauze) > time()) return ['ok' => false, 'fout' => 'whatsapp gepauzeerd tot ' . $pauze];
    $max_uur = function_exists('instelling_int') ? max(1, instelling_int('wa.max_per_uur', 30)) : 30;
    try {
        $q = db()->query("SELECT COUNT(*) FROM mail_log WHERE kanaal='whatsapp' AND status='verstuurd' AND verstuurd_op > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        if ((int)$q->fetchColumn() >= $max_uur) return ['ok' => false, 'fout' => 'whatsapp uurlimiet bereikt'];
    } catch (\Throwable $e) {}
    static $laatste = 0.0;
    $wacht = 1.5 - (microtime(true) - $laatste);
    if ($laatste > 0 && $wacht > 0) usleep((int)($wacht * 1000000));
    $laatste = microtime(true);
    return melding_resultaat(function_exists('wa_verstuur_res') ? wa_verstuur_res($telefoon, $tekst) : wa_verstuur($telefoon, $tekst));
}

/** Drie WhatsApp-fouten binnen tien minuten → 30 minuten pauze. */
function melding_wa_fout_registreer(): void {
    if (!function_exists('instelling_opslaan')) return;
    try {
        $lijst = json_decode(instelling('wa.fouten_recent', '[]'), true);
        if (!is_array($lijst)) $lijst = [];
        $lijst = array_values(array_filter($lijst, fn($t) => (int)$t > time() - 600));
        $lijst[] = time();
        instelling_opslaan('wa.fouten_recent', json_encode($lijst));
        if (count($lijst) >= 3) {
            instelling_opslaan('wa.pauze_tot', date('Y-m-d H:i:s', time() + 1800));
            instelling_opslaan('wa.fouten_recent', '[]');
            if (function_exists('p13_fout')) p13_fout('P13-M005', 'WhatsApp 30 minuten gepauzeerd na herhaalde fouten');
        }
    } catch (\Throwable $e) {}
}

/**
 * Portaal-inlogcode versturen (type 'portaal_code'): kanaalmodus + persoonsvoorkeur, e-mail als
 * terugval, inhoud wordt nooit gelogd, verzending ná het antwoord zodat het inlogscherm snel is.
 */
function portaal_code_versturen(string $email, string $voornaam, string $code, ?string $telefoon = null): void {
    $pid = null; $voorkeur = 'email';
    try {
        $q = db()->prepare("SELECT id, kanaal_voorkeur FROM personen WHERE email=? LIMIT 1");
        $q->execute([$email]);
        if ($p = $q->fetch()) { $pid = (int)$p['id']; $voorkeur = (string)($p['kanaal_voorkeur'] ?: 'whatsapp'); }
    } catch (\Throwable $e) {}
    melding_verstuur('portaal_code', 0, ['otp_code' => $code, 'voornaam' => $voornaam], [
        'ontvanger'  => ['email' => $email, 'naam' => $voornaam, 'telefoon' => (string)$telefoon, 'persoon_id' => $pid, 'kanaal_voorkeur' => $voorkeur, 'rol' => 'direct'],
        'prioriteit' => 'na_antwoord', 'dedup_min' => 0,
    ]);
}

// ─────────────────────────────────────────────────────────────
//  Verzenden ná het antwoord (PHP-FPM)
// ─────────────────────────────────────────────────────────────

function melding_flush_registreer(int $log_id): void {
    $GLOBALS['_melding_flush_ids'][] = $log_id;
    if (!empty($GLOBALS['_melding_flush_geregistreerd'])) return;
    $GLOBALS['_melding_flush_geregistreerd'] = true;
    register_shutdown_function('melding_flush');
}

function melding_flush(): void {
    $ids = $GLOBALS['_melding_flush_ids'] ?? [];
    if (!$ids) return;
    $GLOBALS['_melding_flush_ids'] = [];
    if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
    try { melding_queue_verwerk(count($ids), $ids); } catch (\Throwable $e) { error_log('melding_flush: ' . $e->getMessage()); }
}

}
