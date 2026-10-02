<?php
/**
 * Paal 13 — Meldingen: herinneringen.
 *
 * Regels in code (herinnering_regels()), instellingen per regel in het beheer
 * (`herinnering.<slug>.aan|na|herhaal|max`), en dubbelcheck op DATA in mail_log
 * (meldingstype + sollicitatie + referentie), niet meer op de onderwerp-tekst.
 *
 * Per regel: kandidaten($seizoen) levert rijen ['sol_id', 'extra' => [...], 'referentie' => ?,
 * 'ontvanger' => ?]. herinnering_run() past dan toe: aan/uit, max, herhaal-interval en de
 * rustperiode (max één "zachte" herinnering per sollicitant per N dagen), en verstuurt via
 * melding_verstuur() met prioriteit 'queue'.
 */

if (!function_exists('herinnering_regels')) {

/** Alle herinneringsregels. Sleutel = meldingstype (= sjabloon). */
function herinnering_regels(): array {
    return [
        'herinnering_selectie_aanmelden' => [
            'naam' => 'Nog niet aangemeld voor de selectiedag', 'uitleg' => 'Uitgenodigd, maar nog niet laten weten of hij of zij komt, terwijl de selectiedag binnen 14 dagen is.',
            'aan' => '1', 'na' => 3, 'herhaal' => 4, 'max' => 3, 'rust' => true,
            'oud' => [], 'kandidaten' => 'herinnering_kand_selectie_aanmelden',
        ],
        'herinnering_ouder_gegevens' => [
            'naam' => 'Gegevens ouder/verzorger ontbreken', 'uitleg' => 'Minderjarig, uitgenodigd of aangemeld voor de selectiedag, nog geen e-mailadres van de ouder/verzorger.',
            'aan' => '1', 'na' => 2, 'herhaal' => 4, 'max' => 3, 'rust' => true,
            'oud' => [], 'kandidaten' => 'herinnering_kand_ouder_gegevens',
        ],
        'ondertekening_herinnering' => [
            'naam' => 'Document nog niet ondertekend', 'uitleg' => 'Per document naar de ondertekenaar (ouder of sollicitant) als het na N dagen nog openstaat.',
            'aan' => '1', 'na' => 3, 'herhaal' => 5, 'max' => 2, 'rust' => true,
            'oud' => ['aan' => 'reminder.ondertekening_aan', 'na' => 'reminder.ondertekening_dagen'], 'kandidaten' => 'herinnering_kand_ondertekening',
        ],
        'beschikbaarheid_voorlopig' => [
            'naam' => 'Beschikbaarheid nog voorlopig', 'uitleg' => 'Vanaf 14 dagen vóór de deadline (2 weken vóór het trainingsweekend); na de deadline elke 2 dagen.',
            'aan' => '1', 'na' => 0, 'herhaal' => 4, 'max' => 5, 'rust' => true,
            'oud' => ['aan' => 'reminder.besch_aan'], 'kandidaten' => 'herinnering_kand_beschikbaarheid',
        ],
        'herinnering_taken_open' => [
            'naam' => 'Openstaande taken', 'uitleg' => 'Er staan nog taken open in het portaal (formulier, beschikbaarheid, documenten), gemeten vanaf de laatste fase-wijziging.',
            'aan' => '1', 'na' => 5, 'herhaal' => 7, 'max' => 3, 'rust' => true,
            'oud' => ['aan' => 'reminder.blk_aan', 'na' => 'reminder.blk_dagen'], 'kandidaten' => 'herinnering_kand_taken_open',
        ],
        'selectiedag_nadert' => [
            'naam' => 'Selectiedag nadert', 'uitleg' => 'N dagen vóór de selectiedag naar iedereen die is uitgenodigd of aangemeld.',
            'aan' => '1', 'na' => 3, 'herhaal' => 0, 'max' => 1, 'rust' => false,
            'oud' => ['aan' => 'reminder.sd_aan', 'na' => 'reminder.sd_dagen'], 'kandidaten' => 'herinnering_kand_selectiedag_nadert',
        ],
        'selectiedag_morgen' => [
            'naam' => 'Selectiedag morgen', 'uitleg' => 'De dag vóór de selectiedag: laatste praktische info (tekst hieronder).',
            'aan' => '1', 'na' => 1, 'herhaal' => 0, 'max' => 1, 'rust' => false, 'vast_na' => true,
            'oud' => ['aan' => 'reminder.sd_morgen_aan'], 'kandidaten' => 'herinnering_kand_selectiedag_morgen',
        ],
        'trainingsweekend_nadert' => [
            'naam' => 'Trainingsweekend nadert', 'uitleg' => 'N dagen vóór het trainingsweekend naar iedereen die is uitgenodigd.',
            'aan' => '1', 'na' => 3, 'herhaal' => 0, 'max' => 1, 'rust' => false,
            'oud' => [], 'kandidaten' => 'herinnering_kand_tw_nadert',
        ],
    ];
}

/**
 * Vanaf welk uur automatische rondes berichten mogen maken (instelling melding.auto_vanaf_uur,
 * standaard 7). Geldt voor de cron: herinneringen, geplande berichten en automatisch uitnodigen.
 * Een ronde die op een dag "aan de beurt" is (selectiedag morgen, 10 dagen na …) gaat dan om
 * 07:00 uit in plaats van net na middernacht. Wat een mens in gang zet (fase omzetten, handmatig
 * bericht, "Nu draaien") en reacties op wat de sollicitant zelf doet (inlogcode, bevestiging)
 * gaan altijd direct. Bewust geen eindtijd (besluit Lucas, 2 okt 2026): geen stille uren.
 */
function melding_automatisch_vanaf(): int {
    return max(0, min(23, function_exists('instelling_int') ? instelling_int('melding.auto_vanaf_uur', 7) : 7));
}

/** Mag een automatische ronde nu berichten maken? $uur alleen voor tests. */
function melding_automatisch_nu(?int $uur = null): bool {
    return ($uur ?? (int)date('G')) >= melding_automatisch_vanaf();
}

/** Instelling van een regel, met terugval op de oude reminder.*-sleutels en de standaard uit de regel. */
function herinnering_instelling(string $slug, string $veld): string {
    $regel = herinnering_regels()[$slug] ?? null;
    if (!$regel) return '';
    $std = (string)($regel[$veld] ?? '');
    if (!function_exists('instelling')) return $std;
    $v = instelling('herinnering.' . $slug . '.' . $veld, '');
    if ($v !== '') return $v;
    if (!empty($regel['oud'][$veld])) { $v = instelling($regel['oud'][$veld], ''); if ($v !== '') return $v; }
    return $std;
}

function herinnering_aan(string $slug): bool { return herinnering_instelling($slug, 'aan') === '1'; }
function herinnering_int(string $slug, string $veld): int { return max(0, (int)herinnering_instelling($slug, $veld)); }

/** Aantal en laatste verzending van een type voor een sollicitatie (+ referentie). */
function herinnering_stand(string $slug, int $sol_id, ?string $referentie = null): array {
    try {
        $sql = "SELECT COUNT(*) AS n, MAX(verstuurd_op) AS laatste FROM mail_log WHERE meldingstype=? AND sollicitatie_id=? AND status IN ('wacht','bezig','verstuurd')";
        $p = [$slug, $sol_id];
        if ($referentie) { $sql .= " AND referentie=?"; $p[] = $referentie; }
        $q = db()->prepare($sql); $q->execute($p);
        $r = $q->fetch() ?: ['n' => 0, 'laatste' => null];
        return ['aantal' => (int)($r['n'] ?? 0), 'laatste' => $r['laatste'] ?? null];
    } catch (\Throwable $e) { return ['aantal' => 0, 'laatste' => null]; }
}

/** Kreeg deze sollicitant de afgelopen N dagen al een "zachte" herinnering? */
function herinnering_in_rust(int $sol_id, int $rust_dagen): bool {
    if ($rust_dagen <= 0) return false;
    $zacht = array_keys(array_filter(herinnering_regels(), fn($r) => !empty($r['rust'])));
    if (!$zacht) return false;
    try {
        $in = implode(',', array_fill(0, count($zacht), '?'));
        $q = db()->prepare("SELECT 1 FROM mail_log WHERE sollicitatie_id=? AND meldingstype IN ($in) AND status IN ('wacht','bezig','verstuurd') AND verstuurd_op > DATE_SUB(NOW(), INTERVAL ? DAY) LIMIT 1");
        $q->execute(array_merge([$sol_id], $zacht, [$rust_dagen]));
        return (bool)$q->fetchColumn();
    } catch (\Throwable $e) { return false; }
}

/**
 * Draai alle (of één) regel(s). $dry_run: alleen tellen en kandidaten teruggeven, niets versturen.
 * @return array per slug: ['naam','aan','kandidaten','verstuurd','overgeslagen','lijst'=>[...]]
 */
function herinnering_run(?string $alleen_slug = null, bool $dry_run = false): array {
    $uit = [];
    $seizoen = function_exists('actief_seizoen') ? actief_seizoen() : null;
    if (!$seizoen) return $uit;
    $rust_dagen = function_exists('instelling_int') ? instelling_int('herinnering.rust_dagen', 3) : 3;
    $rust_deze_run = []; // sol_id's die in deze run al een zachte herinnering kregen

    foreach (herinnering_regels() as $slug => $regel) {
        if ($alleen_slug !== null && $alleen_slug !== $slug) continue;
        $res = ['naam' => $regel['naam'], 'aan' => herinnering_aan($slug), 'kandidaten' => 0, 'verstuurd' => 0, 'overgeslagen' => 0, 'lijst' => []];
        if (!$res['aan'] || !function_exists($regel['kandidaten'])) { $uit[$slug] = $res; continue; }
        $na = !empty($regel['vast_na']) ? (int)$regel['na'] : herinnering_int($slug, 'na');
        $herhaal = herinnering_int($slug, 'herhaal');
        $max     = max(1, herinnering_int($slug, 'max'));
        try { $kandidaten = ($regel['kandidaten'])($seizoen, $na); } catch (\Throwable $e) { error_log("herinnering $slug: " . $e->getMessage()); $kandidaten = []; }

        foreach ($kandidaten as $k) {
            $sol_id = (int)$k['sol_id'];
            $res['kandidaten']++;
            $ref   = $k['referentie'] ?? null;
            $stand = herinnering_stand($slug, $sol_id, $ref);
            $reden = null;
            if ($stand['aantal'] >= $max) $reden = 'max';
            elseif ($stand['laatste']) {
                $h = isset($k['herhaal']) ? (int)$k['herhaal'] : $herhaal;
                if ($h <= 0 || strtotime($stand['laatste']) > time() - $h * 86400) $reden = 'herhaal';
            }
            if ($reden === null && !empty($regel['rust']) && (isset($rust_deze_run[$sol_id]) || herinnering_in_rust($sol_id, $rust_dagen))) $reden = 'rust';
            $res['lijst'][] = ['sol_id' => $sol_id, 'naam' => $k['naam'] ?? '', 'referentie' => $ref, 'actie' => $reden ?? 'versturen'];
            if ($reden !== null) { $res['overgeslagen']++; continue; }
            if ($dry_run) { $res['verstuurd']++; if (!empty($regel['rust'])) $rust_deze_run[$sol_id] = true; continue; }
            $opties = ['prioriteit' => 'queue', 'dedup_min' => 0];
            if ($ref) $opties['referentie'] = $ref;
            if (!empty($k['ontvanger'])) $opties['ontvanger'] = $k['ontvanger'];
            $r = melding_verstuur($slug, $sol_id, $k['extra'] ?? [], $opties);
            if (in_array($r['status'], ['wacht', 'verstuurd'], true)) { $res['verstuurd']++; if (!empty($regel['rust'])) $rust_deze_run[$sol_id] = true; }
            else $res['overgeslagen']++;
        }
        $uit[$slug] = $res;
    }
    // Geplande berichten (regels uit het beheer) draaien mee
    if (function_exists('gepland_run') && ($alleen_slug === null || str_starts_with($alleen_slug, 'gepland_'))) {
        $gid = $alleen_slug !== null ? (int)substr($alleen_slug, 8) : null;
        try { foreach (gepland_run($gid, $dry_run) as $k => $r) $uit[$k] = $r; }
        catch (\Throwable $e) { error_log('gepland_run: ' . $e->getMessage()); }
    }
    return $uit;
}

// ─────────────────────────────────────────────────────────────
//  Kandidaten per regel
// ─────────────────────────────────────────────────────────────

/** Basisquery: sollicitaties in het seizoen met persoon; $waar extra voorwaarden met s./p.-aliassen. */
function herinnering_sollicitaties(int $seizoen_id, string $waar, array $p = []): array {
    try {
        $q = db()->prepare("SELECT s.id AS sol_id, s.fase, s.gewijzigd_op, s.aangemeld_op, p.voornaam, p.achternaam, p.geboortedatum, p.ouder_email
                            FROM sollicitaties s JOIN personen p ON p.id=s.persoon_id
                            WHERE s.seizoen_id=? AND s.fase NOT IN ('afgewezen','afgehaakt','verwijderd_door_sollicitant') $waar");
        $q->execute(array_merge([$seizoen_id], $p));
        return $q->fetchAll();
    } catch (\Throwable $e) { error_log('herinnering_sollicitaties: ' . $e->getMessage()); return []; }
}

function herinnering_dagen_tot(?string $datum): ?int {
    if (!$datum) return null;
    $ts = strtotime($datum);
    if (!$ts) return null;
    return (int)floor(($ts - strtotime('today')) / 86400);
}

function herinnering_kand_selectie_aanmelden(array $seizoen, int $na): array {
    $d = herinnering_dagen_tot($seizoen['datum_selectiedag'] ?? null);
    if ($d === null || $d < 0 || $d > 14) return [];
    $uit = [];
    // Uitgenodigd = nog niet "ik kom" gezegd (2.146.0). Wie "kan niet" doorgaf, wacht op het bestuur: geen herinnering.
    foreach (herinnering_sollicitaties((int)$seizoen['id'], "AND s.fase='uitgenodigd_selectie' AND COALESCE(s.gewijzigd_op, s.aangemeld_op) <= DATE_SUB(NOW(), INTERVAL ? DAY)
                 AND NOT EXISTS (SELECT 1 FROM selectie_indeling si WHERE si.sollicitatie_id = s.id AND si.bevestiging = 'komt_niet')", [$na]) as $r) {
        $uit[] = ['sol_id' => (int)$r['sol_id'], 'naam' => trim($r['voornaam'] . ' ' . $r['achternaam'])];
    }
    return $uit;
}

function herinnering_kand_ouder_gegevens(array $seizoen, int $na): array {
    $uit = [];
    $rijen = herinnering_sollicitaties((int)$seizoen['id'], "AND s.fase IN ('uitgenodigd_selectie','aangemeld_selectie') AND (p.ouder_email IS NULL OR p.ouder_email='') AND COALESCE(s.gewijzigd_op, s.aangemeld_op) <= DATE_SUB(NOW(), INTERVAL ? DAY)", [$na]);
    foreach ($rijen as $r) {
        $lft = function_exists('leeftijd_uit_datum') ? leeftijd_uit_datum($r['geboortedatum'] ?? null) : null;
        if ($lft === null || $lft >= 18) continue;
        $uit[] = ['sol_id' => (int)$r['sol_id'], 'naam' => trim($r['voornaam'] . ' ' . $r['achternaam'])];
    }
    return $uit;
}

/** Open ondertekeningen met een adres, in een lopende sollicitatie. $waar/$p: extra voorwaarden (o./s.). */
function herinnering_ondertekeningen_open(string $waar, array $p): array {
    try {
        $q = db()->prepare("SELECT o.id, o.type, o.token, o.ondertekenaar_rol, o.ondertekenaar_naam, o.ondertekenaar_email, o.sollicitatie_id, p.voornaam, p.achternaam
                            FROM ondertekeningen o JOIN sollicitaties s ON s.id=o.sollicitatie_id JOIN personen p ON p.id=s.persoon_id
                            WHERE o.ondertekend_op IS NULL AND o.ondertekenaar_email IS NOT NULL AND o.ondertekenaar_email<>''
                              AND s.fase NOT IN ('afgewezen','afgehaakt','verwijderd_door_sollicitant') $waar");
        $q->execute($p);
        return $q->fetchAll();
    } catch (\Throwable $e) { error_log('herinnering ondertekening: ' . $e->getMessage()); return []; }
}

/**
 * Eén herinnering voor één open ondertekening, in de vorm van herinnering_run(): wie, welk document,
 * de link en de ontvanger (ouder of sollicitant). Eén bron voor de cron én de knop in het beheer
 * (ondertekening_herinneren()), zodat die hetzelfde bericht sturen.
 */
function herinnering_ondertekening_kandidaat(array $o): array {
    $is_ouder = $o['ondertekenaar_rol'] === 'ouder';
    $label = function_exists('ondertekening_label') ? ondertekening_label($o['type']) : 'Formulier';
    $naam  = $o['ondertekenaar_naam'] ?: ($is_ouder ? 'ouder/verzorger' : $o['voornaam']);
    return [
        'sol_id' => (int)$o['sollicitatie_id'], 'naam' => trim($o['voornaam'] . ' ' . $o['achternaam']) . ' (' . $label . ')',
        'referentie' => 'ondertekening:' . (int)$o['id'],
        'extra' => ['ontvanger_naam' => $naam, 'document_naam' => $label, 'onderteken_url' => function_exists('ondertekening_url') ? ondertekening_url($o['token']) : '', 'is_ouder' => $is_ouder ? '1' : '0'],
        'ontvanger' => ['email' => $o['ondertekenaar_email'], 'naam' => $naam, 'telefoon' => '', 'rol' => $is_ouder ? 'ouder' : 'sollicitant'],
    ];
}

function herinnering_kand_ondertekening(array $seizoen, int $na): array {
    return array_map('herinnering_ondertekening_kandidaat', herinnering_ondertekeningen_open(
        'AND s.seizoen_id=? AND o.aangemaakt_op < DATE_SUB(NOW(), INTERVAL ? DAY)', [(int)$seizoen['id'], $na]));
}

/**
 * Handmatig een herinnering sturen voor deze ondertekeningen (knop op Ondertekeningen). Zelfde bericht
 * als de automatische herinnering, maar zonder maximum of rustperiode: iemand kiest er bewust voor.
 * Wel: niet vaker dan eens per uur per document (dubbel klikken, twee bestuursleden tegelijk).
 * Getekend, zonder adres of niet meer lopend wordt stil overgeslagen.
 *
 * @param int[] $ond_ids
 * @return array{verstuurd:int,dubbel:int,niet:int}
 */
function ondertekening_herinneren(array $ond_ids): array {
    $uit = ['verstuurd' => 0, 'dubbel' => 0, 'niet' => 0];
    $ids = array_values(array_unique(array_filter(array_map('intval', $ond_ids), fn($i) => $i > 0)));
    if (!$ids || !function_exists('melding_verstuur')) return $uit;
    $rijen = herinnering_ondertekeningen_open('AND o.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids);
    $uit['niet'] = count($ids) - count($rijen);
    $per_sol = [];   // voor het logboek: wie, welke documenten
    foreach ($rijen as $o) {
        $k = herinnering_ondertekening_kandidaat($o);
        $r = melding_verstuur('ondertekening_herinnering', $k['sol_id'], $k['extra'],
                              ['prioriteit' => 'na_antwoord', 'dedup_min' => 60, 'referentie' => $k['referentie'], 'ontvanger' => $k['ontvanger']]);
        if (in_array($r['status'] ?? '', ['wacht', 'verstuurd'], true)) { $uit['verstuurd']++; $per_sol[$k['sol_id']][] = $k['extra']['document_naam']; }
        elseif (($r['reden'] ?? '') === 'dubbel') $uit['dubbel']++;
        else $uit['niet']++;
    }
    if (function_exists('audit_log')) {
        foreach ($per_sol as $sol => $docs) audit_log('ondertekening_herinnerd', 'sollicitatie', (int)$sol, ['documenten' => $docs]);
    }
    return $uit;
}

function herinnering_kand_beschikbaarheid(array $seizoen, int $na): array {
    if (!function_exists('beschikbaarheid2_deadline')) return [];
    $deadline = beschikbaarheid2_deadline($seizoen);
    if (!$deadline) return [];
    $d = (int)floor(($deadline->getTimestamp() - strtotime('today')) / 86400);
    if ($d > 14) return [];
    $uit = [];
    try {
        $q = db()->prepare("SELECT s.id AS sol_id, p.voornaam, p.achternaam FROM beschikbaarheid_profiel bp
                            JOIN sollicitaties s ON s.id=bp.sollicitatie_id JOIN personen p ON p.id=s.persoon_id
                            WHERE bp.voorlopig=1 AND s.seizoen_id=? AND s.fase NOT IN ('afgewezen','afgehaakt','verwijderd_door_sollicitant')");
        $q->execute([(int)$seizoen['id']]);
        foreach ($q->fetchAll() as $r) {
            $uit[] = [
                'sol_id' => (int)$r['sol_id'], 'naam' => trim($r['voornaam'] . ' ' . $r['achternaam']),
                'herhaal' => $d < 0 ? 2 : null,
                'extra' => ['deadline_datum' => function_exists('datum_nl') ? datum_nl($deadline->format('Y-m-d')) : $deadline->format('d-m-Y'),
                            'dagen_resterend' => (string)max(0, $d), 'deadline_voorbij' => $d < 0 ? '1' : '0'],
            ];
        }
    } catch (\Throwable $e) { error_log('herinnering beschikbaarheid: ' . $e->getMessage()); }
    foreach ($uit as &$k) { if ($k['herhaal'] === null) unset($k['herhaal']); }
    return $uit;
}

/**
 * De variabelen voor de herinnering "Openstaande taken" van één sollicitant, of null als er niets
 * openstaat. Dezelfde takenbron als het portaal (portaal_taken). Eén bron voor cron en beheer.
 */
function herinnering_taken_extra(int $sol_id): ?array {
    if (!function_exists('portaal_taken') || !function_exists('portaal_sollicitant_laden')) return null;
    try {
        $sol = portaal_sollicitant_laden($sol_id);
        if (!$sol) return null;
        $blk  = function_exists('blk_van') ? blk_van($sol_id) : null;
        $open = portaal_taken_open(portaal_taken($sol, $blk));
    } catch (\Throwable $e) { return null; }
    if (!$open) return null;
    $blok = '<ul>' . implode('', array_map(fn($t) => '<li>' . htmlspecialchars((string)$t['titel'], ENT_QUOTES, 'UTF-8') . '</li>', $open)) . '</ul>';
    return ['taken_aantal' => (string)count($open), 'taken_blok' => $blok];
}

function herinnering_kand_taken_open(array $seizoen, int $na): array {
    $uit = [];
    $fases = "'aangemeld','uitgenodigd_selectie','aangemeld_selectie','selectiedag_geweest','uitgenodigd_tw','tw_geweest','aangenomen'";
    foreach (herinnering_sollicitaties((int)$seizoen['id'], "AND s.fase IN ($fases) AND COALESCE(s.gewijzigd_op, s.aangemeld_op) <= DATE_SUB(NOW(), INTERVAL ? DAY)", [$na]) as $r) {
        $extra = herinnering_taken_extra((int)$r['sol_id']);
        if (!$extra) continue;
        $uit[] = ['sol_id' => (int)$r['sol_id'], 'naam' => trim($r['voornaam'] . ' ' . $r['achternaam']) . ' (' . $extra['taken_aantal'] . ')',
                  'extra' => $extra];
    }
    return $uit;
}

/**
 * Handmatige herinnering vanaf de sollicitantenlijst (bulk). Per sollicitant:
 *  - staan er taken open in het portaal, dan de herinnering "Openstaande taken" aan de sollicitant;
 *  - staat er een document open dat een ouder moet tekenen, dan de ondertekenherinnering aan de ouder
 *    (de sollicitant ziet dat als "wacht op ouder" en kan er zelf niets aan doen).
 * Niet vaker dan eens per uur per bericht; bij meer dan tien mensen via de wachtrij.
 *
 * @return array{sollicitanten:int,ouders:int,dubbel:int,niets:int}
 */
function sollicitant_herinneren(array $sol_ids): array {
    $uit = ['sollicitanten' => 0, 'ouders' => 0, 'dubbel' => 0, 'niets' => 0];
    $ids = array_values(array_unique(array_filter(array_map('intval', $sol_ids), fn($i) => $i > 0)));
    if (!$ids || !function_exists('melding_verstuur')) return $uit;
    $prio = count($ids) > 10 ? 'queue' : 'na_antwoord';
    $ouder_docs = [];
    foreach (herinnering_ondertekeningen_open("AND o.ondertekenaar_rol='ouder' AND o.sollicitatie_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")", $ids) as $o) {
        $ouder_docs[(int)$o['sollicitatie_id']][] = (int)$o['id'];
    }
    foreach ($ids as $sol) {
        $iets = false;
        $extra = herinnering_taken_extra($sol);
        if ($extra) {
            $r = melding_verstuur('herinnering_taken_open', $sol, $extra, ['prioriteit' => $prio, 'dedup_min' => 60]);
            if (in_array($r['status'] ?? '', ['wacht', 'verstuurd'], true)) {
                $uit['sollicitanten']++; $iets = true;
                if (function_exists('audit_log')) audit_log('taken_herinnerd', 'sollicitatie', $sol, ['taken' => (int)$extra['taken_aantal']]);
            }
            elseif (($r['reden'] ?? '') === 'dubbel') { $uit['dubbel']++; $iets = true; }
        }
        if (!empty($ouder_docs[$sol])) {
            $r = ondertekening_herinneren($ouder_docs[$sol]);
            if ($r['verstuurd']) $uit['ouders']++;
            if ($r['dubbel']) $uit['dubbel']++;
            $iets = $iets || $r['verstuurd'] || $r['dubbel'];
        }
        if (!$iets) $uit['niets']++;
    }
    return $uit;
}

function herinnering_kand_selectiedag_nadert(array $seizoen, int $na): array {
    $d = herinnering_dagen_tot($seizoen['datum_selectiedag'] ?? null);
    if ($d === null || $d < 2 || $d > max(2, $na)) return [];
    return array_map(fn($r) => ['sol_id' => (int)$r['sol_id'], 'naam' => trim($r['voornaam'] . ' ' . $r['achternaam'])],
        herinnering_sollicitaties((int)$seizoen['id'], "AND s.fase IN ('uitgenodigd_selectie','aangemeld_selectie')"));
}

function herinnering_kand_selectiedag_morgen(array $seizoen, int $na): array {
    $d = herinnering_dagen_tot($seizoen['datum_selectiedag'] ?? null);
    if ($d !== 1) return [];
    $info = function_exists('instelling') ? (instelling('herinnering.selectiedag_morgen.info', '') ?: instelling('reminder.sd_morgen_info', '')) : '';
    return array_map(fn($r) => ['sol_id' => (int)$r['sol_id'], 'naam' => trim($r['voornaam'] . ' ' . $r['achternaam']), 'extra' => ['laatste_info' => $info]],
        herinnering_sollicitaties((int)$seizoen['id'], "AND s.fase IN ('uitgenodigd_selectie','aangemeld_selectie')"));
}

function herinnering_kand_tw_nadert(array $seizoen, int $na): array {
    $d = herinnering_dagen_tot($seizoen['datum_tw_start'] ?? null);
    if ($d === null || $d < 1 || $d > max(1, $na)) return [];
    return array_map(fn($r) => ['sol_id' => (int)$r['sol_id'], 'naam' => trim($r['voornaam'] . ' ' . $r['achternaam'])],
        herinnering_sollicitaties((int)$seizoen['id'], "AND s.fase='uitgenodigd_tw'"));
}

}
