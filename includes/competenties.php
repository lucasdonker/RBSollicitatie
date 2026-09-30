<?php
/**
 * Paal 13 — Competenties (Authentik-koppeling)
 *
 * Authentik is leidend. Wij houden een lokale cache (competenties-tabel) die we
 * vullen vanuit Authentik en bij elke sync overschrijven. Nooit lokaal bewerken.
 *
 * Mapping: elke Authentik-group hoort bij een categorie + rang, of wordt genegeerd.
 * In de weergave tonen we per categorie alleen de HOOGSTE rang die iemand heeft.
 *
 * Match: op e-mail, met handmatige koppeling (authentik_username) als fallback.
 */

// ── Defaults op basis van het groepenmodel (docs/handleiding/authentik_groepen.md) ──
// categorie => [ 'label'=>..., 'icoon'=> naam uit includes/iconen.php, 'groepen' => [ 'groep' => rang, ... ] ]
// 'verborgen' => true: wordt gesynct maar niet als competentie getoond (alleen voor "terugkerend").
function competentie_default_mapping(): array {
    return [
        'lifeguard' => [
            'label'   => 'Lifeguard',
            'icoon'   => 'reddingsboei',
            'ranking' => true,
            'groepen' => [
                'kw-lifeguard-aspirant' => 1,
                'kw-lifeguard-junior'   => 2,
                'kw-lifeguard'          => 3,
                'kw-lifeguard-senior'   => 4,
            ],
        ],
        'varen' => [
            'label'   => 'Varen',
            'icoon'   => 'golf',
            'ranking' => true,
            'groepen' => [
                'kw-boot-opstapper' => 1,
                'kw-boot-schipper'  => 2,
            ],
        ],
        'rijden' => [
            'label'   => 'Rijden',
            'icoon'   => 'wissel',
            'ranking' => true,
            'groepen' => [
                'kw-auto-opstapper' => 1,
                'kw-auto-chauffeur' => 2,
            ],
        ],
        'overig' => [
            'label'   => 'Overig',
            'icoon'   => 'schild',
            'ranking' => false,
            'groepen' => [
                'kw-postcommandant' => 1,
                'kw-bevelvoerder'   => 1,
                'lid-alarmploeg'    => 1,
            ],
        ],
        // Terugkerende strandwacht (authentik_lifeguard_sql). Bewust niet kw-lifeguard-*: er zijn mensen
        // met een diploma die nooit of lang geleden werkten. Besluit Lucas, 30 sep 2026.
        'strandploeg' => [
            'label'     => 'Strandploeg',
            'icoon'     => 'strand',
            'ranking'   => false,
            'verborgen' => true,
            'groepen'   => [
                'lid-strandploeg'     => 2,
                'lid-oud-strandploeg' => 1,
            ],
        ],
    ];
}

/**
 * De groepsnamen van vóór 2.132.2, en hun opvolger. Alleen voor een eerder opgeslagen mapping
 * (instelling authentik.mapping): de standaard hierboven heeft de nieuwe namen al.
 */
function competentie_oude_groepnamen(): array {
    return ['Aspirant Lifeguard' => 'kw-lifeguard-aspirant', 'Junior Lifeguard' => 'kw-lifeguard-junior',
            'Lifeguard' => 'kw-lifeguard', 'Senior Lifeguard' => 'kw-lifeguard-senior',
            'Opstapper Boot' => 'kw-boot-opstapper', 'Schipper' => 'kw-boot-schipper',
            'Opstapper Auto' => 'kw-auto-opstapper', 'Chauffeur' => 'kw-auto-chauffeur',
            'Alarmploeg' => 'lid-alarmploeg', 'Strandploeg' => 'lid-strandploeg'];
}

/**
 * Leesbare naam van een groep voor op het scherm: "kw-lifeguard-senior" → "Senior Lifeguard".
 * De oude Authentik-namen wáren de leesbare namen; wat daar niet in staat: voorvoegsel eraf.
 */
function competentie_groep_label(string $groep): string {
    static $label = null;
    if ($label === null) $label = array_flip(competentie_oude_groepnamen());
    if (isset($label[$groep])) return $label[$groep];
    $kaal = preg_replace('/^(kw|lid|fn)-/', '', $groep);
    return ucfirst(str_replace('-', ' ', (string)$kaal));
}

/** Actieve mapping: uit instelling (JSON) of default. */
function competentie_mapping(): array {
    $json = instelling('authentik.mapping', '');
    if ($json) {
        $m = json_decode($json, true);
        if (is_array($m) && $m) {
            // Eerder opgeslagen met de oude groepsnamen: omzetten, anders matcht niets meer. De
            // categorie strandploeg ontbreekt daar; die komt uit de standaard (nodig voor "terugkerend").
            $oud = competentie_oude_groepnamen();
            foreach ($m as $cat => $def) {
                $nieuw = [];
                foreach ((array)($def['groepen'] ?? []) as $g => $rang) $nieuw[$oud[$g] ?? $g] = $rang;
                $m[$cat]['groepen'] = $nieuw;
            }
            return $m + ['strandploeg' => competentie_default_mapping()['strandploeg']];
        }
    }
    return competentie_default_mapping();
}

function competentie_mapping_opslaan(array $mapping): void {
    instelling_opslaan('authentik.mapping', json_encode($mapping, JSON_UNESCAPED_UNICODE));
}

/** Platte lijst: group-naam => ['categorie'=>..., 'rang'=>...] voor snelle lookup. */
function competentie_group_index(): array {
    $idx = [];
    foreach (competentie_mapping() as $cat => $def) {
        foreach ($def['groepen'] ?? [] as $groep => $rang) {
            $idx[$groep] = ['categorie' => $cat, 'rang' => (int)$rang];
        }
    }
    // Overgang: zolang Authentik nog de oude groepsnamen heeft, tellen die als hun opvolger.
    // Zo breekt de dekking niet als deze versie eerder live staat dan de hernoeming in Authentik.
    foreach (competentie_oude_groepnamen() as $oud => $nieuw) {
        if (isset($idx[$nieuw]) && !isset($idx[$oud])) $idx[$oud] = $idx[$nieuw];
    }
    return $idx;
}

function competenties_auto_setup(): void {
    static $done = false;
    if ($done || schema_actueel()) return;
    $done = true;
    try {
        $db = db();
        $db->exec("CREATE TABLE IF NOT EXISTS `competenties` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `sollicitatie_id` INT NOT NULL,
            `categorie` VARCHAR(40) NOT NULL,
            `groep` VARCHAR(80) NOT NULL,
            `rang` INT NOT NULL DEFAULT 0,
            `bron` VARCHAR(20) NOT NULL DEFAULT 'authentik',
            `gesynct_op` DATETIME DEFAULT NULL,
            UNIQUE KEY `uniek` (`sollicitatie_id`,`groep`),
            INDEX (`sollicitatie_id`),
            INDEX (`categorie`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Koppeling + sync-status op sollicitaties (of aparte tabel)
        $db->exec("CREATE TABLE IF NOT EXISTS `authentik_koppeling` (
            `sollicitatie_id` INT PRIMARY KEY,
            `authentik_username` VARCHAR(150) DEFAULT NULL,
            `authentik_pk` VARCHAR(80) DEFAULT NULL,
            `laatst_gesynct` DATETIME DEFAULT NULL,
            `sync_status` VARCHAR(20) DEFAULT NULL,
            `sync_melding` VARCHAR(255) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Exception $e) { error_log('competenties_auto_setup: ' . $e->getMessage()); }
}

// ── Authentik API ────────────────────────────────────────────
// Basis-URL: nieuwe instelling authentik.url, anders de bestaande OIDC_ISSUER uit config.
function authentik_basis_url(): string {
    $u = instelling('authentik.url', '');
    if ($u === '' && defined('OIDC_ISSUER')) $u = OIDC_ISSUER;
    return rtrim($u, '/');
}
// Token: nieuwe instelling authentik.token, anders de bestaande authentik.api_token.
function authentik_token(): string {
    return instelling('authentik.token', '') ?: instelling('authentik.api_token', '');
}
// Klaar voor competentie-sync? (eigen check, botst niet met SSO-check authentik_geconfigureerd)
function competentie_sync_klaar(): bool {
    return authentik_basis_url() !== '' && authentik_token() !== '' && function_exists('curl_init');
}

/**
 * Roep de Authentik API aan. Retourneert [http_code, data|foutmelding].
 */
function authentik_api(string $pad, array $params = []): array {
    $url = authentik_basis_url();
    $token = authentik_token();
    if (!$url || !$token) return [0, 'Authentik niet geconfigureerd'];

    $full = $url . '/api/v3/' . ltrim($pad, '/');
    if ($params) $full .= '?' . http_build_query($params);

    $ch = curl_init($full);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) return [0, 'Verbindingsfout: ' . $err];
    $data = json_decode($resp, true);
    if ($code >= 400) {
        $detail = is_array($data) ? ($data['detail'] ?? json_encode($data)) : $resp;
        return [$code, 'API-fout (' . $code . '): ' . mb_substr((string)$detail, 0, 200)];
    }
    return [$code, $data];
}

/** Test de verbinding: haalt 1 gebruiker op. */
function authentik_test(): array {
    [$code, $data] = authentik_api('core/users/', ['page_size' => 1]);
    if ($code === 200 && is_array($data)) {
        $aantal = $data['pagination']['count'] ?? '?';
        return ['ok' => true, 'melding' => "Verbinding werkt. Authentik meldt $aantal gebruikers."];
    }
    return ['ok' => false, 'melding' => is_string($data) ? $data : 'Onbekende fout'];
}

/**
 * Zoek een Authentik-gebruiker op e-mail of username. Retourneert user-array of null.
 */
function authentik_zoek_gebruiker(string $email, ?string $username = null): ?array {
    if ($username) {
        [$code, $data] = authentik_api('core/users/', ['username' => $username]);
        if ($code === 200 && !empty($data['results'])) return $data['results'][0];
    }
    if ($email) {
        [$code, $data] = authentik_api('core/users/', ['email' => $email]);
        if ($code === 200 && !empty($data['results'])) return $data['results'][0];
    }
    return null;
}

/**
 * Sync competenties voor één sollicitatie. Best-effort: gooit nooit.
 * Retourneert ['ok'=>bool, 'melding'=>..., 'aantal'=>n].
 */
function competenties_sync(int $sol_id, string $email, ?string $username = null): array {
    competenties_auto_setup();
    if (!competentie_sync_klaar()) {
        return ['ok' => false, 'melding' => 'Authentik niet geconfigureerd'];
    }
    $db = db();

    // Bestaande handmatige koppeling heeft voorrang
    $kop = $db->prepare("SELECT authentik_username FROM authentik_koppeling WHERE sollicitatie_id=?");
    $kop->execute([$sol_id]);
    $vast_username = $kop->fetchColumn() ?: $username;

    try {
        $user = authentik_zoek_gebruiker($email, $vast_username ?: null);
        if (!$user) {
            competenties_sync_status($sol_id, null, null, 'niet_gevonden', 'Geen Authentik-gebruiker gevonden voor ' . $email);
            return ['ok' => false, 'melding' => 'Geen match in Authentik', 'aantal' => 0];
        }

        $groepen = $user['groups_obj'] ?? [];
        if (!$groepen && !empty($user['pk'])) {
            // Fallback: haal groepen apart op
            [$c, $d] = authentik_api('core/groups/', ['members_by_pk' => $user['pk']]);
            if ($c === 200) $groepen = $d['results'] ?? [];
        }

        $index = competentie_group_index();
        $matches = [];
        foreach ($groepen as $g) {
            $naam = $g['name'] ?? '';
            if (isset($index[$naam])) {
                $matches[] = ['groep' => $naam, 'categorie' => $index[$naam]['categorie'], 'rang' => $index[$naam]['rang']];
            }
        }

        // Overschrijf lokale cache (Authentik is leidend)
        $db->prepare("DELETE FROM competenties WHERE sollicitatie_id=?")->execute([$sol_id]);
        if ($matches) {
            $ins = $db->prepare("INSERT INTO competenties (sollicitatie_id, categorie, groep, rang, bron, gesynct_op) VALUES (?,?,?,?,'authentik',NOW())");
            foreach ($matches as $m) $ins->execute([$sol_id, $m['categorie'], $m['groep'], $m['rang']]);
        }

        competenties_sync_status($sol_id, $user['username'] ?? null, (string)($user['pk'] ?? ''), 'ok', count($matches) . ' competenties');
        return ['ok' => true, 'melding' => count($matches) . ' competenties gesynct', 'aantal' => count($matches)];

    } catch (\Exception $e) {
        error_log('competenties_sync: ' . $e->getMessage());
        competenties_sync_status($sol_id, null, null, 'fout', mb_substr($e->getMessage(), 0, 200));
        return ['ok' => false, 'melding' => 'Fout: ' . $e->getMessage(), 'aantal' => 0];
    }
}

function competenties_sync_status(int $sol_id, ?string $username, ?string $pk, string $status, string $melding): void {
    try {
        db()->prepare("INSERT INTO authentik_koppeling (sollicitatie_id, authentik_username, authentik_pk, laatst_gesynct, sync_status, sync_melding)
                       VALUES (?,?,?,NOW(),?,?)
                       ON DUPLICATE KEY UPDATE
                         authentik_username = COALESCE(VALUES(authentik_username), authentik_username),
                         authentik_pk = COALESCE(VALUES(authentik_pk), authentik_pk),
                         laatst_gesynct = NOW(), sync_status = VALUES(sync_status), sync_melding = VALUES(sync_melding)")
           ->execute([$sol_id, $username, $pk, $status, $melding]);
    } catch (\Exception $e) {}
}

/** Handmatige koppeling vastleggen (op username). */
function competentie_koppel_handmatig(int $sol_id, string $username): void {
    competenties_auto_setup();
    db()->prepare("INSERT INTO authentik_koppeling (sollicitatie_id, authentik_username)
                   VALUES (?,?) ON DUPLICATE KEY UPDATE authentik_username=VALUES(authentik_username)")
       ->execute([$sol_id, $username]);
}

/**
 * Competenties van een sollicitatie, per categorie ALLEEN de hoogste rang.
 * Retourneert [ ['categorie'=>..,'label'=>..,'icoon'=>..,'groep'=>..,'rang'=>..], ... ]
 */
function competenties_van(int $sol_id): array {
    competenties_auto_setup();
    $mapping = competentie_mapping();
    $rijen = [];
    try {
        $q = db()->prepare("SELECT categorie, groep, rang FROM competenties WHERE sollicitatie_id=?");
        $q->execute([$sol_id]);
        $rijen = $q->fetchAll();
    } catch (\Exception $e) { return []; }

    // Per categorie hoogste rang
    $hoogste = [];
    foreach ($rijen as $r) {
        $cat = $r['categorie'];
        if (!isset($hoogste[$cat]) || $r['rang'] > $hoogste[$cat]['rang']) {
            $hoogste[$cat] = ['groep' => $r['groep'], 'rang' => (int)$r['rang']];
        }
    }

    $uit = [];
    foreach ($mapping as $cat => $def) {
        if (!empty($def['verborgen'])) continue;   // bv. strandploeg: alleen voor "terugkerend"
        $ranking = $def['ranking'] ?? true;
        if ($ranking) {
            if (isset($hoogste[$cat])) {
                $uit[] = ['categorie'=>$cat, 'label'=>$def['label'], 'icoon'=>$def['icoon'] ?? 'punt', 'groep'=>$hoogste[$cat]['groep'], 'rang'=>$hoogste[$cat]['rang']];
            }
        } else {
            // Losse competenties: toon elke die iemand heeft
            foreach ($rijen as $r) {
                if ($r['categorie'] === $cat) {
                    $uit[] = ['categorie'=>$cat, 'label'=>$def['label'], 'icoon'=>$def['icoon'] ?? 'punt', 'groep'=>$r['groep'], 'rang'=>(int)$r['rang']];
                }
            }
        }
    }
    return $uit;
}

/**
 * SQL-expressie (0/1): is dit een terugkerende strandwacht? Ja als de sync bij deze sollicitatie een
 * groep uit de categorie strandploeg vond: lid-strandploeg (dit seizoen) of lid-oud-strandploeg
 * (afgelopen 3 seizoenen, bijgehouden door het Verenigingsportaal). Een Authentik-account of een
 * lifeguard-diploma (kw-*) is bewust niet genoeg: dat hebben ook mensen die nooit of lang geleden
 * werkten (besluit Lucas, 30 sep 2026). Zolang de sync niet gedraaid heeft, telt iemand als nieuw.
 * Gebruik: "SELECT …, " . authentik_lifeguard_sql('s.id') . " AS is_lifeguard FROM sollicitaties s …"
 */
function authentik_lifeguard_sql(string $sol_kolom): string {
    if (!preg_match('/^[a-z_]+\.[a-z_]+$/', $sol_kolom)) $sol_kolom = 's.id';
    return "(CASE WHEN EXISTS (SELECT 1 FROM competenties cp WHERE cp.sollicitatie_id = $sol_kolom
                 AND cp.categorie = 'strandploeg') THEN 1 ELSE 0 END)";
}

/** Sync-status van een sollicitatie voor weergave. */
function competentie_sync_info(int $sol_id): array {
    competenties_auto_setup();
    try {
        $q = db()->prepare("SELECT authentik_username, laatst_gesynct, sync_status, sync_melding FROM authentik_koppeling WHERE sollicitatie_id=?");
        $q->execute([$sol_id]);
        return $q->fetch() ?: [];
    } catch (\Exception $e) { return []; }
}

/**
 * Dagelijkse hersync van alle actieve sollicitanten (via cron, met gate).
 */
function competenties_cron_sync(): array {
    if (!competentie_sync_klaar()) return ['ok' => false, 'reden' => 'niet geconfigureerd'];
    $laatste = instelling('authentik.laatste_bulk_sync', '');
    if ($laatste && strtotime($laatste) > time() - 82800) { // ~23u gate
        return ['ok' => true, 'overgeslagen' => true];
    }
    instelling_opslaan('authentik.laatste_bulk_sync', date('Y-m-d H:i:s'));

    $seizoen = actief_seizoen();
    if (!$seizoen) return ['ok' => true, 'aantal' => 0];

    $q = db()->prepare("
        SELECT s.id, p.email FROM sollicitaties s
        JOIN personen p ON p.id = s.persoon_id
        WHERE s.seizoen_id = ? AND s.fase NOT IN ('afgewezen') AND p.email IS NOT NULL
    ");
    $q->execute([(int)$seizoen['id']]);
    $n = 0;
    foreach ($q->fetchAll() as $r) {
        competenties_sync((int)$r['id'], $r['email']);
        $n++;
        usleep(150000); // 0.15s tussen calls, vriendelijk voor de API
    }
    return ['ok' => true, 'aantal' => $n];
}
