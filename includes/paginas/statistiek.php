<?php
/**
 * Pagina's — anonieme statistiek + werving-attributie.
 *
 * Twee lagen:
 *  1. Bezoekersstatistiek per pagina (bezoeken, unieke bezoekers, formulier gestart/afgerond).
 *     Geen cookies, geen IP-opslag, geen user-agent. Alleen een dag-gebonden hash om
 *     "uniek" te kunnen tellen; die wordt na 30 dagen op NULL gezet (zie pag_events_opschonen).
 *  2. Bron-attributie: de bron-code reist mee naar de sollicitatie, zodat later zichtbaar is
 *     waar aangenomen mensen vandaan kwamen — ook als ze via de WordPress-plugin binnenkwamen.
 */

if (!function_exists('pag_track')) {

/**
 * De gebeurtenissen die we tellen, in trechtervolgorde.
 *
 *  bezoek              de pagina is geopend
 *  formulier_start     iemand vult het eerste veld aan (komt van het script, niet van de server:
 *                      alleen zo weet je dat iemand écht begon en niet alleen langsliep)
 *  formulier_stap      een volgende stap bereikt; 'detail' is het stapnummer
 *  formulier_verlaten  het tabblad ging naar de achtergrond terwijl het formulier openstond;
 *                      'detail' is de verste stap. Een aanwijzing, geen bewijs — op mobiel is
 *                      dit het enige signaal dat iemand vertrekt, en soms komt hij gewoon terug.
 *  formulier_klaar     de aanmelding is opgeslagen (server)
 *  formulier_geweigerd de inzending is tegengehouden (server: bot, te snel, te vaak)
 */
function pag_event_typen(): array {
    return ['bezoek', 'formulier_start', 'formulier_stap', 'formulier_verlaten', 'formulier_klaar', 'formulier_geweigerd'];
}

/**
 * De kolom 'type' is een ENUM. MariaDB weigert een waarde die er niet in staat niet met een
 * fout maar zet er stilletjes een lege string neer — dus de ENUM moet mee met pag_event_typen().
 * Draait alleen als er echt iets ontbreekt.
 */
function pag_events_enum_bij(\PDO $db): void {
    try {
        $q = $db->prepare("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
                           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pagina_events' AND COLUMN_NAME = 'type'");
        $q->execute();
        $huidig = (string)$q->fetchColumn();
        if ($huidig === '') return;

        $ontbreekt = false;
        foreach (pag_event_typen() as $t) {
            if (!str_contains($huidig, "'" . $t . "'")) { $ontbreekt = true; break; }
        }
        if (!$ontbreekt) return;

        $lijst = "'" . implode("','", pag_event_typen()) . "'";
        $db->exec("ALTER TABLE `pagina_events` MODIFY COLUMN `type` ENUM($lijst) NOT NULL");
        error_log('pag_events_enum_bij: type-ENUM uitgebreid');
    } catch (\Throwable $e) { error_log('pag_events_enum_bij: ' . $e->getMessage()); }
}

/**
 * Het geheime zout voor het bezoekerspseudoniem: 32 willekeurige bytes (hex) in de instelling
 * paginas.pseudoniem_zout, eenmalig aangemaakt. INSERT IGNORE + opnieuw lezen: twee verzoeken
 * tegelijk krijgen zo toch hetzelfde zout. Lukt opslaan niet, dan geldt een zout voor dit
 * verzoek alleen (hooguit een dubbele telling, nooit een herleidbare hash).
 */
function pag_pseudoniem_zout(): string {
    static $zout = null;
    if ($zout !== null) return $zout;
    $geldig = fn($z) => is_string($z) && preg_match('/^[0-9a-f]{64}$/', $z);
    $z = function_exists('instelling') ? instelling('paginas.pseudoniem_zout', '') : '';
    if ($geldig($z)) return $zout = $z;
    $nieuw = bin2hex(random_bytes(32));
    try {
        db()->prepare("INSERT IGNORE INTO instellingen (sleutel, waarde) VALUES ('paginas.pseudoniem_zout', ?)")->execute([$nieuw]);
        $q = db()->prepare("SELECT waarde FROM instellingen WHERE sleutel = 'paginas.pseudoniem_zout'");
        $q->execute();
        $z = $q->fetchColumn();
        if ($geldig($z)) return $zout = $z;
    } catch (\Throwable $e) { error_log('pag_pseudoniem_zout: ' . $e->getMessage()); }
    return $zout = $nieuw;
}

/**
 * Dag-gebonden pseudoniem van de bezoeker. De dagsleutel is afgeleid van het geheime zout en
 * de datum (HMAC), de hash van IP + user-agent met die dagsleutel. Zonder het zout niet terug
 * te rekenen, en morgen is het een andere code: een bezoeker is niet over dagen te volgen.
 */
function pag_bezoeker_hash(): string {
    $dagsleutel = hash_hmac('sha256', date('Y-m-d'), pag_pseudoniem_zout());
    return hash_hmac('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? ''), $dagsleutel);
}

/** Grove bot-herkenning, zodat crawlers de statistiek niet opblazen. */
function pag_is_bot(): bool {
    $ua = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($ua === '') return true;
    return (bool)preg_match('/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|headless|monitor|uptime|curl|wget|python-requests|okhttp|ahrefs|semrush/i', $ua);
}

/**
 * Hoe lang na een bezoek hetzelfde bezoek nog "hetzelfde bezoek" is (minuten).
 * Binnen dit venster telt een nieuwe paginaweergave van dezelfde bezoeker niet opnieuw mee.
 */
function pag_bezoek_venster_min(): int {
    $m = function_exists('instelling_int') ? instelling_int('paginas.bezoek_venster_min', 30) : 30;
    return max(0, min(1440, $m));
}

/**
 * Is deze gebeurtenis net al geteld voor deze bezoeker op deze pagina?
 *
 * Een browser vraagt een pagina om allerlei redenen opnieuw op zonder dat iemand hem echt
 * opnieuw opent: terug-knop, vernieuwen, of een tabblad dat door Edge/Chrome in slaapstand
 * is gezet en bij het terugwisselen opnieuw wordt geladen. Zonder deze controle telt dat
 * allemaal mee. Hetzelfde geldt voor de stap-gebeurtenissen: heen en weer bladeren door het
 * formulier mag stap 2 niet vijf keer laten meetellen. Vandaar ook $detail in de vergelijking.
 */
function pag_event_recent(string $type, ?int $pagina_id, string $hash, ?string $detail = null): bool {
    $min = pag_bezoek_venster_min();
    if ($min <= 0) return false;
    try {
        $q = db()->prepare("SELECT 1 FROM pagina_events
                            WHERE type = ? AND bezoeker_hash = ? AND pagina_id <=> ? AND detail <=> ?
                              AND moment > DATE_SUB(NOW(), INTERVAL ? MINUTE) LIMIT 1");
        $q->execute([$type, $hash, $pagina_id, $detail, $min]);
        return (bool)$q->fetchColumn();
    } catch (\Throwable $e) { error_log('pag_event_recent: ' . $e->getMessage()); return false; }
}

/** Blijft bestaan voor oude aanroepen; pag_event_recent() is de bredere versie. */
function pag_bezoek_recent(?int $pagina_id, string $hash): bool {
    return pag_event_recent('bezoek', $pagina_id, $hash);
}

/**
 * Leg een gebeurtenis vast. Alleen het type, de pagina/bron, een stapnummer en het
 * dag-pseudoniem — nooit iets wat iemand heeft ingevuld.
 */
function pag_track(string $type, ?int $pagina_id, ?string $bron_code = null, ?string $detail = null): void {
    if (!in_array($type, pag_event_typen(), true)) return;
    if (function_exists('instelling_bool') && !instelling_bool('paginas.tracking', true)) return;
    // Alleen de serverkant-gebeurtenissen (een echte aanmelding, een weigering) tellen bij bots
    // sowieso niet mee; al het andere komt van een browser en moet de statistiek niet opblazen.
    if ($type !== 'formulier_klaar' && $type !== 'formulier_geweigerd' && pag_is_bot()) return;

    $detail = $detail === null ? null : substr(trim($detail), 0, 40);
    if ($detail === '') $detail = null;

    try {
        pag_auto_setup();
        if ($type !== 'formulier_klaar' && pag_event_recent($type, $pagina_id, pag_bezoeker_hash(), $detail)) return;
        db()->prepare("INSERT INTO pagina_events (pagina_id, bron_code, type, detail, bezoeker_hash) VALUES (?,?,?,?,?)")
            ->execute([$pagina_id, $bron_code, $type, $detail, pag_bezoeker_hash()]);
    } catch (\Throwable $e) { error_log('pag_track: ' . $e->getMessage()); }
}

/**
 * Statistiek voor meerdere pagina's in één query (de oude module deed dit per rij).
 * Retour: [pagina_id => ['bezoeken','uniek','starts','klaar','geweigerd','start_pct','klaar_pct']]
 */
function pag_stats_bulk(array $pagina_ids): array {
    $uit = [];
    $ids = array_values(array_unique(array_filter(array_map('intval', $pagina_ids))));
    foreach ($ids as $id) {
        $uit[$id] = ['bezoeken'=>0,'uniek'=>0,'starts'=>0,'klaar'=>0,'geweigerd'=>0,'verlaten'=>0,
                     'start_pct'=>0,'klaar_pct'=>0,'bezoek_klaar_pct'=>0];
    }
    if (!$ids) return $uit;
    try {
        pag_auto_setup();
        $gaten = implode(',', array_fill(0, count($ids), '?'));
        $q = db()->prepare("SELECT pagina_id, type, COUNT(*) AS aantal, COUNT(DISTINCT bezoeker_hash) AS uniek
                            FROM pagina_events WHERE pagina_id IN ($gaten) GROUP BY pagina_id, type");
        $q->execute($ids);
        foreach ($q->fetchAll() as $r) {
            $id = (int)$r['pagina_id'];
            if (!isset($uit[$id])) continue;
            switch ($r['type']) {
                case 'bezoek':              $uit[$id]['bezoeken']  = (int)$r['aantal'];
                                            $uit[$id]['uniek']     = (int)$r['uniek']; break;
                case 'formulier_start':     $uit[$id]['starts']    = (int)$r['aantal']; break;
                case 'formulier_klaar':     $uit[$id]['klaar']     = (int)$r['aantal']; break;
                case 'formulier_geweigerd': $uit[$id]['geweigerd'] = (int)$r['aantal']; break;
                case 'formulier_verlaten':  $uit[$id]['verlaten']  = (int)$r['aantal']; break;
            }
        }
    } catch (\Throwable $e) { error_log('pag_stats_bulk: ' . $e->getMessage()); }

    foreach ($uit as $id => $s) {
        $uit[$id]['start_pct']        = $s['uniek']  > 0 ? (int)round($s['starts'] / $s['uniek']  * 100) : 0;
        $uit[$id]['klaar_pct']        = $s['starts'] > 0 ? (int)round($s['klaar']  / $s['starts'] * 100) : 0;
        $uit[$id]['bezoek_klaar_pct'] = $s['uniek']  > 0 ? (int)round($s['klaar']  / $s['uniek']  * 100) : 0;
    }
    return $uit;
}

/** Statistiek van één pagina. */
function pag_stats(int $pagina_id): array {
    return pag_stats_bulk([$pagina_id])[$pagina_id];
}

/**
 * De trechter van één pagina: van bezoek tot aanmelding, met per stap hoeveel mensen er
 * kwamen en hoeveel er daarna afhaakten.
 *
 * Retour: ['treden' => [['sleutel','label','aantal','pct_van_start','verloop'], …], 'verlaten_per_stap' => [nr => aantal]]
 * 'verloop' is het aantal mensen dat deze trede haalde maar de volgende niet.
 *
 * Let op bij het lezen: stap 1 heeft geen eigen gebeurtenis — wie begint te typen zit per
 * definitie in stap 1. 'formulier_start' ís dus stap 1.
 */
function pag_trechter(int $pagina_id, int $stappen = 3): array {
    $tellingen = ['bezoek' => 0, 'formulier_start' => 0, 'formulier_klaar' => 0];
    $per_stap = [];
    $verlaten = [];

    try {
        pag_auto_setup();
        $q = db()->prepare("SELECT type, detail, COUNT(*) AS aantal
                            FROM pagina_events WHERE pagina_id = ? GROUP BY type, detail");
        $q->execute([$pagina_id]);
        foreach ($q->fetchAll() as $r) {
            $type = (string)$r['type'];
            $aantal = (int)$r['aantal'];
            if ($type === 'formulier_stap') {
                $per_stap[(int)$r['detail']] = ($per_stap[(int)$r['detail']] ?? 0) + $aantal;
            } elseif ($type === 'formulier_verlaten') {
                $verlaten[(int)$r['detail']] = ($verlaten[(int)$r['detail']] ?? 0) + $aantal;
            } elseif (isset($tellingen[$type])) {
                $tellingen[$type] += $aantal;
            }
        }
    } catch (\Throwable $e) { error_log('pag_trechter: ' . $e->getMessage()); }

    $labels = aanmeld_stappen_labels($stappen);

    $treden = [['sleutel' => 'bezoek', 'label' => 'Pagina bekeken', 'aantal' => $tellingen['bezoek']]];
    $treden[] = ['sleutel' => 'stap1', 'label' => 'Stap 1 · ' . $labels[1], 'aantal' => $tellingen['formulier_start']];
    for ($n = 2; $n <= $stappen; $n++) {
        $treden[] = ['sleutel' => 'stap' . $n, 'label' => 'Stap ' . $n . ' · ' . ($labels[$n] ?? ''), 'aantal' => $per_stap[$n] ?? 0];
    }
    $treden[] = ['sleutel' => 'klaar', 'label' => 'Aanmelding verstuurd', 'aantal' => $tellingen['formulier_klaar']];

    $basis = $tellingen['formulier_start'];
    foreach ($treden as $i => $t) {
        $treden[$i]['pct_van_start'] = $basis > 0 ? (int)round($t['aantal'] / $basis * 100) : 0;
        $volgende = $treden[$i + 1]['aantal'] ?? null;
        $treden[$i]['verloop'] = ($volgende !== null && $t['aantal'] > $volgende) ? $t['aantal'] - $volgende : 0;
    }

    return ['treden' => $treden, 'verlaten_per_stap' => $verlaten];
}

/** De korte stapnamen, zodat de statistiek dezelfde woorden gebruikt als het formulier zelf. */
function aanmeld_stappen_labels(int $aantal = 3): array {
    if (function_exists('aanmeld_stappen')) {
        $uit = [];
        foreach (aanmeld_stappen() as $nr => $s) $uit[$nr] = (string)$s['kort'];
        return $uit;
    }
    $terugval = [1 => 'Naam', 2 => 'Jij', 3 => 'Contact'];
    return array_slice($terugval, 0, $aantal, true);
}

/**
 * Wervingsoverzicht: per bron hoeveel aanmeldingen en hoeveel daarvan aangenomen.
 * Leest de sollicitaties zelf, niet de events — een aanmelding telt pas als hij er echt is.
 */
function pag_werving_overzicht(?int $seizoen_id = null): array {
    try {
        pag_auto_setup();
        $where = "WHERE s.bron_code IS NOT NULL AND s.bron_code <> ''";
        $args = [];
        if ($seizoen_id) { $where .= " AND s.seizoen_id = ?"; $args[] = $seizoen_id; }
        $q = db()->prepare("
            SELECT COALESCE(s.bron_label, s.bron_code) AS bron,
                   s.bron_code,
                   COUNT(*) AS aanmeldingen,
                   SUM(CASE WHEN s.fase = 'aangenomen' THEN 1 ELSE 0 END) AS aangenomen
            FROM sollicitaties s
            $where
            GROUP BY s.bron_code, bron
            ORDER BY aanmeldingen DESC
        ");
        $q->execute($args);
        return $q->fetchAll();
    } catch (\Throwable $e) { return []; }
}

/**
 * AVG-bewaarbeperking. Twee stappen:
 *  - na $hash_dagen het pseudoniem wissen (tellingen blijven, herleidbaarheid verdwijnt)
 *  - na $maanden de gebeurtenis helemaal verwijderen
 * Retour: ['gewist'=>int, 'verwijderd'=>int]
 */
function pag_events_opschonen(int $maanden = 0, int $hash_dagen = 0, bool $dry_run = false): array {
    if ($maanden <= 0)    $maanden    = function_exists('instelling_int') ? instelling_int('bewaar.pagina_events_mnd', 13) : 13;
    if ($hash_dagen <= 0) $hash_dagen = function_exists('instelling_int') ? instelling_int('bewaar.pagina_hash_dagen', 30) : 30;
    $uit = ['gewist' => 0, 'verwijderd' => 0];
    try {
        pag_auto_setup();
        $db = db();
        if ($hash_dagen > 0) {
            $q = $db->prepare("SELECT COUNT(*) FROM pagina_events WHERE bezoeker_hash IS NOT NULL AND moment < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $q->execute([$hash_dagen]);
            $uit['gewist'] = (int)$q->fetchColumn();
            if (!$dry_run && $uit['gewist'] > 0) {
                $db->prepare("UPDATE pagina_events SET bezoeker_hash = NULL WHERE bezoeker_hash IS NOT NULL AND moment < DATE_SUB(NOW(), INTERVAL ? DAY)")
                   ->execute([$hash_dagen]);
            }
        }
        if ($maanden > 0) {
            $q = $db->prepare("SELECT COUNT(*) FROM pagina_events WHERE moment < DATE_SUB(NOW(), INTERVAL ? MONTH)");
            $q->execute([$maanden]);
            $uit['verwijderd'] = (int)$q->fetchColumn();
            if (!$dry_run && $uit['verwijderd'] > 0) {
                $db->prepare("DELETE FROM pagina_events WHERE moment < DATE_SUB(NOW(), INTERVAL ? MONTH)")->execute([$maanden]);
            }
        }
    } catch (\Throwable $e) { error_log('pag_events_opschonen: ' . $e->getMessage()); }
    return $uit;
}

}
