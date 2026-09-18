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
            `soort`           VARCHAR(30) NOT NULL,
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
    return [
        'id_voor'   => ['titel' => 'Identiteitsbewijs — voorkant', 'taak' => 'id_kopie', 'recht' => 'documenten', 'bewaren' => 'loon', 'compleet' => true,  'upload' => true],
        'id_achter' => ['titel' => 'Identiteitsbewijs — achterkant', 'taak' => 'id_kopie', 'recht' => 'documenten', 'bewaren' => 'loon', 'compleet' => false, 'upload' => true],
        'bankpas'   => ['titel' => 'Bankpas — voorkant', 'taak' => 'bankpas_kopie', 'recht' => 'documenten', 'bewaren' => 'verwerkt_dan_weg', 'compleet' => true, 'upload' => true],
        'pasfoto'   => ['titel' => 'Pasfoto', 'taak' => 'pasfoto', 'recht' => 'sollicitanten', 'bewaren' => 'dossier', 'compleet' => false, 'upload' => true],
        'loonheffing_pdf'  => ['titel' => 'Loonheffingsformulier (verzegeld)', 'taak' => null, 'recht' => 'documenten', 'bewaren' => 'loon', 'compleet' => false, 'upload' => false],
        'lidmaatschap_pdf' => ['titel' => 'Inschrijving vereniging (verzegeld)', 'taak' => null, 'recht' => 'documenten', 'bewaren' => 'dossier', 'compleet' => false, 'upload' => false],
    ];
}

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
        'pasfoto' => [
            'titel' => 'Pasfoto uploaden', 'sub' => 'Voor je dienstpas', 'icoon' => 'camera', 'volgorde' => 22,
            'uitleg' => 'Een recente foto recht van voren, met een effen achtergrond, zonder zonnebril of pet.',
            'soorten' => ['pasfoto' => 'Pasfoto'],
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

} // function_exists
