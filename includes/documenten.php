<?php
/**
 * Paal 13 — Documenten van sollicitanten (aangenomen-takenpakket): kopie ID (voor/achter), kopie bankpas,
 * pasfoto en de verzegelde PDF's van ondertekeningen (loonheffing, lidmaatschap).
 *
 * Alles staat versleuteld in de kluis (includes/kluis.php), in een map buiten het webbereik
 * (DOCUMENTEN_MAP in config.php; anders uploads/documenten met een deny-.htaccess). De rij in
 * `sollicitant_documenten` bewaart alleen metadata + sha256 van de inhoud. Ontsleutelen gebeurt
 * uitsluitend in het geheugen, op de plekken die daar het recht voor hebben (beheer-download,
 * PDF-bijlage op verzendmoment).
 *
 * Statussen: ingediend (door de sollicitant geüpload), verwerkt (penningmeester heeft het
 * overgenomen), vervangen (door een nieuwere upload; bestand is weg), vernietigd (bestand is weg,
 * rij blijft als bewijs). Vervangen/vernietigde rijen tellen mee voor de uploadlimiet.
 */

if (!function_exists('documenten_auto_setup')) {

function documenten_auto_setup(): void {
    static $done = false; if ($done) return; $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS `sollicitant_documenten` (
            `id`              INT AUTO_INCREMENT PRIMARY KEY,
            `sollicitatie_id` INT NOT NULL,
            `soort`           VARCHAR(40) NOT NULL,
            `bestandsnaam`    VARCHAR(200) NOT NULL,
            `opslagnaam`      VARCHAR(80) NOT NULL,
            `mime`            VARCHAR(100) NOT NULL,
            `grootte`         INT NOT NULL DEFAULT 0,
            `sha256`          CHAR(64) DEFAULT NULL,
            `kluis_versie`    TINYINT NOT NULL DEFAULT 1,
            `extra`           VARCHAR(100) DEFAULT NULL,
            `status`          VARCHAR(20) NOT NULL DEFAULT 'ingediend',
            `geupload_op`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `geupload_ip`     VARCHAR(45) DEFAULT NULL,
            `verwerkt_op`     DATETIME DEFAULT NULL,
            `verwerkt_door`   INT DEFAULT NULL,
            `vernietigd_op`   DATETIME DEFAULT NULL,
            `laatst_gedownload_op`   DATETIME DEFAULT NULL,
            `laatst_gedownload_door` INT DEFAULT NULL,
            INDEX (`sollicitatie_id`), INDEX (`status`), INDEX (`soort`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // De langste soort is 'ondertekening_gezondheid_18plus' (31 tekens); 2.90.0 maakte de kolom 30 breed,
        // waardoor die waarde werd afgekapt en de verzegelde PDF niet meer terug te vinden was.
        try {
            $col = db()->query("SHOW COLUMNS FROM sollicitant_documenten LIKE 'soort'")->fetch();
            if ($col && preg_match('/varchar\((\d+)\)/i', (string)($col['Type'] ?? ''), $m) && (int)$m[1] < 40) {
                db()->exec("ALTER TABLE sollicitant_documenten MODIFY COLUMN `soort` VARCHAR(40) NOT NULL");
                error_log('db_failsafe: sollicitant_documenten.soort verbreed naar VARCHAR(40)');
            }
        } catch (\Throwable $e) { error_log('documenten_auto_setup soort-breedte: ' . $e->getMessage()); }
    } catch (\Throwable $e) { error_log('documenten_auto_setup: ' . $e->getMessage()); }
}

/** Opslagmap buiten het webbereik. Maakt hem aan (0700) en zet een deny-.htaccess voor het geval hij tóch in de webroot staat. */
function documenten_map(): ?string {
    $dir = (defined('DOCUMENTEN_MAP') && DOCUMENTEN_MAP !== '' && !str_contains((string)DOCUMENTEN_MAP, '{{'))
        ? rtrim((string)DOCUMENTEN_MAP, '/\\')
        : dirname(__DIR__) . '/uploads/documenten';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    if (!is_dir($dir) || !is_writable($dir)) return null;
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) @file_put_contents($ht, "# Geen directe toegang: alleen via het beheer, na ontsleuteling\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
    if (!is_file($dir . '/index.html')) @file_put_contents($dir . '/index.html', '');
    return $dir;
}

/** Staat de opslagmap binnen de webroot? (Health-check waarschuwt dan.) */
function documenten_map_in_webroot(): bool {
    $dir = documenten_map();
    if ($dir === null) return false;
    $root = realpath(dirname(__DIR__));
    $abs  = realpath($dir);
    return $root !== false && $abs !== false && str_starts_with($abs, $root);
}

/** Toegestane typen voor uploads door de sollicitant. */
function documenten_typen(): array {
    return ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'];
}

function documenten_max_bytes(): int { return 10 * 1048576; }

/**
 * Alle documentsoorten: wat het is, bij welke taak het hoort, wie het mag inzien en hoe lang het blijft.
 *   recht     : rechtenmodule voor inzage/download in het beheer
 *   bewaren   : loon (wettelijk, bewaar.loondocumenten_jaar) | verwerkt_dan_weg (bankpas) | dossier (volgt het dossier)
 *   compleet  : telt mee voor "dossier compleet" richting de penningmeester
 *   upload    : mag de sollicitant dit zelf uploaden (anders maakt het systeem het aan)
 */
function document_soorten(): array {
    static $s = null;
    if ($s !== null) return $s;
    $s = [
        'id_voor'   => ['titel' => 'Identiteitsbewijs — voorkant', 'taak' => 'id_kopie', 'recht' => 'documenten', 'bewaren' => 'loon', 'compleet' => true,  'upload' => true],
        'id_achter' => ['titel' => 'Identiteitsbewijs — achterkant', 'taak' => 'id_kopie', 'recht' => 'documenten', 'bewaren' => 'loon', 'compleet' => false, 'upload' => true],
        'bankpas'   => ['titel' => 'Bankpas — voorkant', 'taak' => 'bankpas_kopie', 'recht' => 'documenten', 'bewaren' => 'verwerkt_dan_weg', 'compleet' => true, 'upload' => true],
        // Pasfoto is er bewust NIET (besluit Lucas, 19 sep 2026), ook al staat die in het ontwerp.
    ];
    // Verzegelde PDF's van ondertekeningen: soort ondertekening_<type> (aangemaakt door het systeem bij het tekenen)
    foreach (['gezondheid_18plus', 'gezondheid_ouder', 'intake', 'intake_ouder', 'loonheffing', 'lidmaatschap'] as $type) {
        $gevoelig = in_array($type, ['loonheffing', 'lidmaatschap'], true); // BSN resp. IBAN
        $s['ondertekening_' . $type] = [
            'titel'    => (function_exists('ondertekening_label') ? ondertekening_label($type) : $type) . ' (verzegeld)',
            'taak'     => null,
            'recht'    => $gevoelig ? 'documenten' : 'ondertekeningen',
            'bewaren'  => $type === 'loonheffing' ? 'loon' : 'dossier',
            'compleet' => false,
            'upload'   => false,
        ];
    }
    return $s;
}

/** Soort van de verzegelde PDF bij een ondertekeningstype. */
function document_soort_ondertekening(string $type): string { return 'ondertekening_' . $type; }

/**
 * De upload-taken in het portaal: taak_slug => titel, sub (ontwerp), uitleg, soorten (vakken op de pagina), icoon.
 * Eén bron voor kennismakingspakket_taken_definities(), portaal_taken() en document_upload.php.
 */
function document_taken(): array {
    return [
        'id_kopie' => [
            'titel' => 'Kopie ID uploaden', 'sub' => 'Voor de loonadministratie', 'icoon' => 'id-kaart', 'volgorde' => 20,
            'uitleg' => 'Paal 13 is als werkgever wettelijk verplicht een volledige, leesbare kopie van je identiteitsbewijs te bewaren (paspoort of identiteitskaart; een rijbewijs geldt niet). Dek dus niets af. De kopie wordt versleuteld bewaard en is alleen zichtbaar voor de penningmeester.',
            'soorten' => ['id_voor' => 'Voorkant', 'id_achter' => 'Achterkant (alleen bij een identiteitskaart)'],
        ],
        'bankpas_kopie' => [
            'titel' => 'Kopie bankpas uploaden', 'sub' => 'Voor je salaris', 'icoon' => 'bankpas', 'volgorde' => 21,
            'uitleg' => 'Een foto van de voorkant van je bankpas, zodat we je naam en rekeningnummer goed overnemen. De achterkant is niet nodig. Na verwerking door de penningmeester wordt de foto vernietigd.',
            'soorten' => ['bankpas' => 'Voorkant'],
        ],
    ];
}

/** AAD waarmee een document aan zijn dossier en soort vastzit. */
function document_aad(int $sol_id, string $soort, string $opslagnaam): string {
    return 'document|' . $sol_id . '|' . $soort . '|' . $opslagnaam;
}

/** Actieve documenten van een dossier: soort => rij (ingediend/verwerkt; vervangen en vernietigd niet). */
function documenten_van(int $sol_id): array {
    documenten_auto_setup();
    $uit = [];
    try {
        $q = db()->prepare("SELECT * FROM sollicitant_documenten WHERE sollicitatie_id=? AND status IN ('ingediend','verwerkt') ORDER BY id");
        $q->execute([$sol_id]);
        foreach ($q->fetchAll() as $r) $uit[$r['soort']] = $r;
    } catch (\Throwable $e) {}
    return $uit;
}

function document_rij(int $id): ?array {
    documenten_auto_setup();
    try { $q = db()->prepare("SELECT * FROM sollicitant_documenten WHERE id=?"); $q->execute([$id]); return $q->fetch() ?: null; }
    catch (\Throwable $e) { return null; }
}

function document_pad(array $rij): ?string {
    $dir = documenten_map();
    if ($dir === null) return null;
    $pad = $dir . '/' . basename((string)$rij['opslagnaam']);
    return is_file($pad) ? $pad : null;
}

/** Uploadlimiet: maximaal 20 uploads per uur per dossier (alle statussen tellen mee). */
function documenten_upload_toegestaan(int $sol_id): bool {
    try {
        $q = db()->prepare("SELECT COUNT(*) FROM sollicitant_documenten WHERE sollicitatie_id=? AND geupload_op > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $q->execute([$sol_id]);
        return (int)$q->fetchColumn() < 20;
    } catch (\Throwable $e) { return true; }
}

/**
 * Sla inhoud (al in het geheugen) versleuteld op als document van een dossier. Een bestaand actief
 * document van dezelfde soort wordt 'vervangen' (bestand weg, rij blijft als bewijs).
 * Retour: ['ok'=>bool, 'id'=>?int, 'fout'=>?string].
 */
function document_opslaan_inhoud(int $sol_id, string $soort, string $inhoud, string $bestandsnaam, string $mime, ?string $extra = null, ?string $ip = null): array {
    documenten_auto_setup();
    $soorten = document_soorten();
    if (!isset($soorten[$soort])) return ['ok' => false, 'id' => null, 'fout' => 'onbekende documentsoort'];
    if (!function_exists('kluis_beschikbaar') || !kluis_beschikbaar()) return ['ok' => false, 'id' => null, 'fout' => 'de beveiligde opslag (kluis) is niet beschikbaar; neem contact op met het bestuur'];
    $dir = documenten_map();
    if ($dir === null) return ['ok' => false, 'id' => null, 'fout' => 'de opslagmap is niet schrijfbaar'];

    $opslag = bin2hex(random_bytes(20)) . '.enc';
    if (!kluis_bestand_schrijven($inhoud, $dir . '/' . $opslag, document_aad($sol_id, $soort, $opslag))) {
        return ['ok' => false, 'id' => null, 'fout' => 'versleuteld opslaan mislukt'];
    }
    $sha = hash('sha256', $inhoud);
    try {
        $db = db();
        $db->beginTransaction();
        // Oud exemplaar van dezelfde soort → vervangen (bestand wissen)
        $q = $db->prepare("SELECT id, opslagnaam FROM sollicitant_documenten WHERE sollicitatie_id=? AND soort=? AND status IN ('ingediend','verwerkt')");
        $q->execute([$sol_id, $soort]);
        foreach ($q->fetchAll() as $oud) {
            @unlink($dir . '/' . basename((string)$oud['opslagnaam']));
            $db->prepare("UPDATE sollicitant_documenten SET status='vervangen', vernietigd_op=NOW() WHERE id=?")->execute([(int)$oud['id']]);
        }
        $db->prepare("INSERT INTO sollicitant_documenten (sollicitatie_id, soort, bestandsnaam, opslagnaam, mime, grootte, sha256, kluis_versie, extra, status, geupload_ip)
                      VALUES (?,?,?,?,?,?,?,?,?,'ingediend',?)")
           ->execute([$sol_id, $soort, substr($bestandsnaam, 0, 200), $opslag, $mime, strlen($inhoud), $sha, function_exists('kluis_kek_versie') ? kluis_kek_versie() : 1, $extra, $ip]);
        $id = (int)$db->lastInsertId();
        $db->commit();
    } catch (\Throwable $e) {
        try { db()->rollBack(); } catch (\Throwable $e2) {}
        @unlink($dir . '/' . $opslag);
        error_log('document_opslaan_inhoud: ' . $e->getMessage());
        return ['ok' => false, 'id' => null, 'fout' => 'opslaan in de database mislukt'];
    }
    if (function_exists('audit_log')) audit_log('document_geupload', 'document', $id, ['soort' => $soort, 'sollicitatie_id' => $sol_id, 'grootte' => strlen($inhoud)]);
    if (function_exists('taak_status_zetten') && !empty($soorten[$soort]['taak'])) documenten_taak_bijwerken($sol_id, $soorten[$soort]['taak']);
    return ['ok' => true, 'id' => $id, 'fout' => null];
}

/**
 * Upload door de sollicitant ($_FILES-element): valideren, in het geheugen lezen, tijdelijk bestand
 * meteen wissen, versleuteld opslaan. Geen plaintext op schijf.
 */
function document_opslaan(int $sol_id, string $soort, array $upload, ?string $extra = null, ?string $ip = null): array {
    $s = document_soorten()[$soort] ?? null;
    if (!$s || empty($s['upload'])) return ['ok' => false, 'id' => null, 'fout' => 'dit document kun je niet zelf uploaden'];
    if (!documenten_upload_toegestaan($sol_id)) return ['ok' => false, 'id' => null, 'fout' => 'te veel uploads in korte tijd; probeer het over een uur opnieuw'];
    $v = upload_valideer($upload, documenten_typen(), documenten_max_bytes());
    if (!$v['ok']) return ['ok' => false, 'id' => null, 'fout' => $v['fout']];
    $inhoud = @file_get_contents($upload['tmp_name']);
    @unlink($upload['tmp_name']);
    if ($inhoud === false || $inhoud === '') return ['ok' => false, 'id' => null, 'fout' => 'het bestand kon niet gelezen worden'];
    $res = document_opslaan_inhoud($sol_id, $soort, $inhoud, $v['naam'], $v['mime'], $extra, $ip);
    kluis_wis($inhoud);
    return $res;
}

/** Ontsleutelde inhoud (alleen aanroepen na een rechtencontrole; de aanroeper logt de download). */
function document_inhoud(array $rij): ?string {
    if (!in_array($rij['status'] ?? '', ['ingediend', 'verwerkt'], true)) return null;
    $pad = document_pad($rij);
    if ($pad === null || !function_exists('kluis_bestand_lezen')) return null;
    return kluis_bestand_lezen($pad, document_aad((int)$rij['sollicitatie_id'], (string)$rij['soort'], (string)$rij['opslagnaam']));
}

/** Door de sollicitant verwijderd (alleen zolang nog niet verwerkt): bestand weg, rij als 'vervangen'. */
function document_verwijderen_door_sollicitant(int $id, int $sol_id): bool {
    $r = document_rij($id);
    if (!$r || (int)$r['sollicitatie_id'] !== $sol_id || $r['status'] !== 'ingediend') return false;
    $pad = document_pad($r);
    if ($pad) @unlink($pad);
    try { db()->prepare("UPDATE sollicitant_documenten SET status='vervangen', vernietigd_op=NOW() WHERE id=?")->execute([$id]); } catch (\Throwable $e) { return false; }
    if (function_exists('audit_log')) audit_log('document_verwijderd', 'document', $id, ['soort' => $r['soort'], 'door' => 'sollicitant']);
    $taak = document_soorten()[$r['soort']]['taak'] ?? null;
    if ($taak) documenten_taak_bijwerken($sol_id, $taak);
    return true;
}

/** Vernietigen (beheer/opschoning): bestand weg, rij blijft als bewijs met status 'vernietigd'. */
function document_vernietigen(int $id, string $reden = ''): bool {
    $r = document_rij($id);
    if (!$r) return false;
    $pad = document_pad($r);
    if ($pad) @unlink($pad);
    try { db()->prepare("UPDATE sollicitant_documenten SET status='vernietigd', vernietigd_op=NOW() WHERE id=?")->execute([$id]); } catch (\Throwable $e) { return false; }
    if (function_exists('audit_log')) audit_log('document_vernietigd', 'document', $id, ['soort' => $r['soort'], 'reden' => $reden]);
    return true;
}

/** Alle documenten van een dossier vernietigen (dossier verwijderd). $ook_loondocs=false laat de wettelijk te bewaren stukken staan. */
function documenten_bij_dossier_verwijderen(int $sol_id, bool $ook_loondocs): int {
    $n = 0; $soorten = document_soorten();
    try {
        $q = db()->prepare("SELECT id, soort FROM sollicitant_documenten WHERE sollicitatie_id=? AND status IN ('ingediend','verwerkt')");
        $q->execute([$sol_id]);
        foreach ($q->fetchAll() as $r) {
            if (!$ook_loondocs && ($soorten[$r['soort']]['bewaren'] ?? '') === 'loon') continue;
            if (document_vernietigen((int)$r['id'], 'dossier verwijderd')) $n++;
        }
    } catch (\Throwable $e) {}
    return $n;
}

/**
 * Is de upload-taak klaar? id_kopie: voorkant + (bij identiteitskaart) achterkant; overige: het ene vak.
 * Zet de taakstatus in sollicitant_taken gelijk.
 */
function documenten_taak_klaar(int $sol_id, string $taak_slug, ?array $docs = null): bool {
    $docs = $docs ?? documenten_van($sol_id);
    $def  = document_taken()[$taak_slug] ?? null;
    if (!$def) return false;
    if ($taak_slug === 'id_kopie') {
        if (empty($docs['id_voor'])) return false;
        $type = (string)($docs['id_voor']['extra'] ?? '');
        return $type !== 'id_kaart' || !empty($docs['id_achter']);
    }
    foreach (array_keys($def['soorten']) as $soort) { if (empty($docs[$soort])) return false; }
    return true;
}

function documenten_taak_bijwerken(int $sol_id, string $taak_slug): void {
    if (function_exists('taak_status_zetten')) taak_status_zetten($sol_id, $taak_slug, documenten_taak_klaar($sol_id, $taak_slug));
}

// ── Loondossier (penningmeester) ─────────────────────────────

/** De ondertekening van een type voor een dossier (of null). */
function documenten_ondertekening(int $sol_id, string $type): ?array {
    try {
        $q = db()->prepare("SELECT * FROM ondertekeningen WHERE sollicitatie_id=? AND type=? ORDER BY id DESC LIMIT 1");
        $q->execute([$sol_id, $type]);
        return $q->fetch() ?: null;
    } catch (\Throwable $e) { return null; }
}

/**
 * Status van het loondossier: loonheffing ondertekend + ID (voor, en achter bij een identiteitskaart) + bankpas.
 * Retour: ['compleet'=>bool, 'ontbreekt'=>[labels], 'docs'=>soort=>rij, 'loonheffing'=>?rij, 'lidmaatschap'=>?rij, 'verwerkt'=>bool].
 */
function documenten_loondossier(int $sol_id): array {
    $docs = documenten_van($sol_id);
    $lh   = documenten_ondertekening($sol_id, 'loonheffing');
    $lid  = documenten_ondertekening($sol_id, 'lidmaatschap');
    $ontbreekt = [];
    if (!$lh || empty($lh['ondertekend_op'])) $ontbreekt[] = 'Loonheffingsformulier';
    if (empty($docs['id_voor'])) $ontbreekt[] = 'Kopie identiteitsbewijs';
    elseif (($docs['id_voor']['extra'] ?? '') === 'id_kaart' && empty($docs['id_achter'])) $ontbreekt[] = 'Achterkant identiteitskaart';
    if (empty($docs['bankpas'])) $ontbreekt[] = 'Kopie bankpas';
    $verwerkt = $lh && !empty($lh['verwerkt_op']);
    return ['compleet' => !$ontbreekt, 'ontbreekt' => $ontbreekt, 'docs' => $docs, 'loonheffing' => $lh, 'lidmaatschap' => $lid, 'verwerkt' => $verwerkt];
}

/** Bijlagen (document_id's) voor de mail aan de penningmeester: ID voor/achter, bankpas, verzegelde loonheffing-PDF. */
function documenten_penningmeester_bijlagen(array $dossier): array {
    $uit = [];
    foreach (['id_voor', 'id_achter', 'bankpas'] as $s) if (!empty($dossier['docs'][$s]['id'])) $uit[] = ['document_id' => (int)$dossier['docs'][$s]['id']];
    if (!empty($dossier['loonheffing']['pdf_document_id'])) $uit[] = ['document_id' => (int)$dossier['loonheffing']['pdf_document_id']];
    return $uit;
}

/**
 * Is het loondossier compleet? Dan één keer de penningmeester mailen (melding penningmeester_dossier_compleet).
 * Met $opnieuw=true wordt hij nogmaals gestuurd (knop in het beheer). Faalt stil.
 */
function documenten_dossier_compleet_check(int $sol_id, bool $opnieuw = false): bool {
    $d = documenten_loondossier($sol_id);
    if (!$d['compleet'] || !function_exists('melding_verstuur')) return false;
    $lh_id = (int)($d['loonheffing']['id'] ?? 0);
    if (!$opnieuw && $lh_id) {
        try {
            $q = db()->prepare("SELECT 1 FROM ondertekening_gebeurtenissen WHERE ondertekening_id=? AND gebeurtenis='penningmeester_gemeld' LIMIT 1");
            $q->execute([$lh_id]);
            if ($q->fetchColumn()) return false;
        } catch (\Throwable $e) {}
    }
    $met_bijlagen = function_exists('instelling') && instelling('documenten.penningmeester_bijlagen', '0') === '1';
    $lijst = '<ul>';
    $lijst .= '<li>Loonheffingsformulier, ondertekend ' . h(datum_nl(substr((string)$d['loonheffing']['ondertekend_op'], 0, 10))) . ' (digitaal verzegelde PDF)</li>';
    foreach (['id_voor' => 'Kopie identiteitsbewijs (voorkant)', 'id_achter' => 'Kopie identiteitsbewijs (achterkant)', 'bankpas' => 'Kopie bankpas'] as $s => $l) {
        if (!empty($d['docs'][$s])) $lijst .= '<li>' . h($l) . ', ontvangen ' . h(datum_nl(substr((string)$d['docs'][$s]['geupload_op'], 0, 10))) . '</li>';
    }
    $lijst .= '</ul>';
    $r = melding_verstuur('penningmeester_dossier_compleet', $sol_id, [
        'documenten_lijst' => $lijst,
        'beheer_url'       => rtrim(APP_URL, '/') . '/admin/documenten.php?sol=' . $sol_id,
        'bijlagen_tekst'   => $met_bijlagen ? 'De stukken zitten als bijlage bij deze mail.' : 'De stukken staan klaar in het beheer (Loondossiers); download ze daar.',
    ], ['referentie' => 'loondossier:' . $sol_id, 'prioriteit' => 'direct', 'dedup_min' => 0,
        'bijlagen' => $met_bijlagen ? documenten_penningmeester_bijlagen($d) : []]);
    $ok = in_array($r['status'] ?? '', ['verstuurd', 'wacht'], true);
    if ($ok && $lh_id && function_exists('ondertekening_gebeurtenis')) ondertekening_gebeurtenis($lh_id, 'penningmeester_gemeld', ['bijlagen' => $met_bijlagen ? 'ja' : 'nee', 'opnieuw' => $opnieuw ? 'ja' : 'nee']);
    return $ok;
}

/** Loondossier verwerkt: documenten + loonheffing op verwerkt, gebeurtenis + audit. */
function documenten_verwerkt_markeren(int $sol_id, int $door): void {
    try {
        db()->prepare("UPDATE sollicitant_documenten SET status='verwerkt', verwerkt_op=NOW(), verwerkt_door=? WHERE sollicitatie_id=? AND status='ingediend' AND soort IN ('id_voor','id_achter','bankpas','ondertekening_loonheffing')")->execute([$door, $sol_id]);
        db()->prepare("UPDATE ondertekeningen SET verwerkt_op=NOW(), verwerkt_door=? WHERE sollicitatie_id=? AND type='loonheffing' AND ondertekend_op IS NOT NULL AND verwerkt_op IS NULL")->execute([$door, $sol_id]);
    } catch (\Throwable $e) { error_log('documenten_verwerkt_markeren: ' . $e->getMessage()); }
    $lh = documenten_ondertekening($sol_id, 'loonheffing');
    if ($lh && function_exists('ondertekening_gebeurtenis')) ondertekening_gebeurtenis((int)$lh['id'], 'verwerkt', ['door' => $door]);
    if (function_exists('audit_log')) audit_log('loondossier_verwerkt', 'sollicitatie', $sol_id);
}

/** Overzicht voor het beheer: alle aangenomen sollicitaties van een seizoen met hun loondossier. */
function documenten_overzicht(int $seizoen_id): array {
    $uit = [];
    try {
        $q = db()->prepare("SELECT s.id, p.voornaam, p.tussenvoegsel, p.achternaam, p.geboortedatum FROM sollicitaties s JOIN personen p ON p.id=s.persoon_id WHERE s.seizoen_id=? AND s.fase='aangenomen' ORDER BY p.achternaam, p.voornaam");
        $q->execute([$seizoen_id]);
        foreach ($q->fetchAll() as $s) {
            $uit[] = ['sol' => $s, 'naam' => trim($s['voornaam'] . ' ' . trim(($s['tussenvoegsel'] ?? '') . ' ' . $s['achternaam'])), 'dossier' => documenten_loondossier((int)$s['id'])];
        }
    } catch (\Throwable $e) {}
    return $uit;
}

/** Rechtenmodule die nodig is om een document te downloaden. */
function document_recht_module(string $soort): string {
    return document_soorten()[$soort]['recht'] ?? 'documenten';
}

} // function_exists
