<?php
/**
 * Pagina's — opslag op schijf.
 *
 *   uploads/paginas/<slug>/
 *     versies/   0001.html, 0002.html …   (afgeschermd; alleen PHP leest hieruit)
 *     assets/    foto.jpg, logo.png …     (publiek leesbaar, gerefereerd met een relatief pad)
 *
 * uploads/ staat in .gitignore en in de beschermde paden van de deploy, dus deze bestanden
 * overleven een update. Elke upload wordt een nieuwe versie: nooit overschrijven, altijd
 * kunnen terugrollen.
 */

if (!function_exists('pag_basis_map')) {

function pag_basis_map(): string { return dirname(__DIR__, 2) . '/uploads/paginas'; }

/**
 * Toegestane bestandstypen voor bijgeüploade afbeeldingen.
 * Bewust géén SVG: die kan script bevatten en zou onder script-src 'self' draaien.
 * Bewust ook geen ico/woff2/avif: daarvan wijkt de mime per server af, waardoor de
 * magic-bytes-controle in upload_valideer() legitieme bestanden zou weigeren.
 */
function pag_asset_typen(): array {
    return ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp'];
}

/**
 * Maximale grootte van één geüpload ontwerp. Standaard 16 MB: een export uit een
 * ontwerptool heeft vaak afbeeldingen en lettertypen als data-URI in de HTML zitten,
 * en die lopen makkelijk over 10 MB heen. Staat de server lager (upload_max_filesize /
 * post_max_size in Plesk), dan telt die grens — daarom hier al het minimum van beide,
 * zodat het scherm nooit meer belooft dan er aankomt.
 *
 * Hoger dan dit is onverstandig, ook als de server het aankan: het nalezen van het
 * ontwerp kost ongeveer 3,5x de bestandsgrootte aan werkgeheugen (gemeten: 10,6 MB →
 * 40 MB, 25 MB → 88 MB), en de bezoeker moet dat hele bestand over 4G binnenhalen
 * vóór hij iets ziet. Ingesloten afbeeldingen horen los geüpload te worden.
 */
function pag_max_html_bytes(): int {
    $kb = function_exists('instelling_int') ? instelling_int('paginas.max_html_kb', 16384) : 16384;
    return (int)min(max(64, $kb) * 1024, upload_server_max_bytes());
}

/**
 * Past een bestand van deze grootte nog in het werkgeheugen van dit proces?
 * De scan houdt de HTML plus de zoekresultaten vast; reken op 4x de bestandsgrootte.
 * Zonder deze controle stopt PHP halverwege met een fatale fout — een witte pagina
 * zonder uitleg, precies op het moment dat de beheerder zijn ontwerp uploadt.
 */
function pag_geheugen_genoeg(int $bytes): bool {
    $limiet = upload_ini_bytes((string)ini_get('memory_limit'));
    if ($limiet <= 0 || $limiet === PHP_INT_MAX) return true;   // geen limiet ingesteld
    return ($bytes * 4) < ($limiet - memory_get_usage(true));
}

function pag_max_asset_bytes(): int {
    $kb = function_exists('instelling_int') ? instelling_int('paginas.max_asset_kb', 5120) : 5120;
    return (int)min(max(64, $kb) * 1024, upload_server_max_bytes());
}

/** Bestandsgrootte in gewone taal: onder 1 MB in KB, daarboven in MB met één decimaal. */
function pag_bytes_tekst(int $bytes): string {
    if ($bytes < 1048576) return max(1, (int)round($bytes / 1024)) . ' KB';
    $mb = $bytes / 1048576;
    return rtrim(rtrim(number_format($mb, 1, ',', ''), '0'), ',') . ' MB';
}

/** Map van één pagina; maakt hem aan met de juiste afscherming. Null als dat niet lukt. */
function pag_map(string $slug, bool $maak = true): ?string {
    if (!pag_slug_geldig($slug)) return null;
    $dir = pag_basis_map() . '/' . $slug;
    if (!is_dir($dir)) {
        if (!$maak) return null;
        @mkdir($dir, 0755, true);
    }
    if (!is_dir($dir)) return null;

    // versies/ afschermen — die HTML hoort alleen via de renderer naar buiten te gaan
    $versies = $dir . '/versies';
    if (!is_dir($versies)) @mkdir($versies, 0755, true);
    $ht = $versies . '/.htaccess';
    if (!is_file($ht)) @file_put_contents($ht, "# Geen directe toegang: deze HTML gaat alleen via de renderer naar buiten\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
    if (!is_file($versies . '/index.html')) @file_put_contents($versies . '/index.html', '');

    // assets/ mag publiek, maar alleen afbeeldingen en lettertypen
    $assets = $dir . '/assets';
    if (!is_dir($assets)) @mkdir($assets, 0755, true);
    $hta = $assets . '/.htaccess';
    if (!is_file($hta)) @file_put_contents($hta, "# Alleen afbeeldingen; nooit uitvoerbare bestanden\n<IfModule mod_authz_core.c>\n  Require all denied\n  <FilesMatch \"\\.(jpe?g|png|gif|webp)$\">\n    Require all granted\n  </FilesMatch>\n</IfModule>\nOptions -Indexes\n<IfModule mod_headers.c>\n  Header set X-Content-Type-Options nosniff\n</IfModule>\n");
    if (!is_file($assets . '/index.html')) @file_put_contents($assets . '/index.html', '');

    return $dir;
}

/** Map meeverhuizen als de slug wijzigt. True als er niets te doen viel of het lukte. */
function pag_map_hernoem(string $oud, string $nieuw): bool {
    if (!pag_slug_geldig($nieuw)) return false;
    $van = pag_basis_map() . '/' . $oud;
    if (!is_dir($van)) return true;                       // nog niets opgeslagen
    $naar = pag_basis_map() . '/' . $nieuw;
    if (is_dir($naar)) return false;                      // zou bestaande bestanden overschrijven
    return @rename($van, $naar);
}

function pag_map_verwijder(string $slug): void {
    $dir = pag_map($slug, false);
    if ($dir === null) return;
    foreach (['versies', 'assets'] as $sub) {
        foreach (glob($dir . '/' . $sub . '/*') ?: [] as $f) { if (is_file($f)) @unlink($f); }
        @rmdir($dir . '/' . $sub);
    }
    foreach (glob($dir . '/{,.}*', GLOB_BRACE) ?: [] as $f) { if (is_file($f)) @unlink($f); }
    @rmdir($dir);
}

// ── Versies ──────────────────────────────────────────────────────────────

/**
 * Nieuwe versie wegschrijven. De versie is nog NIET actief en nog niet bevestigd: dat
 * gebeurt pas nadat de beheerder het scanrapport heeft gezien (zie import.php).
 * Retour: ['ok'=>bool, 'versie_id'=>int, 'fout'=>string]
 */
function pag_versie_opslaan(array $pagina, string $html, array $meta = []): array {
    $dir = pag_map((string)$pagina['slug']);
    if ($dir === null) return ['ok' => false, 'versie_id' => 0, 'fout' => 'De opslagmap kon niet worden aangemaakt.'];
    try {
        $db = db();
        $q = $db->prepare("SELECT COALESCE(MAX(versie),0) FROM pagina_versies WHERE pagina_id = ?");
        $q->execute([(int)$pagina['id']]);
        $nr = (int)$q->fetchColumn() + 1;

        $naam = 'versies/' . str_pad((string)$nr, 4, '0', STR_PAD_LEFT) . '.html';
        if (@file_put_contents($dir . '/' . $naam, $html) === false) {
            return ['ok' => false, 'versie_id' => 0, 'fout' => 'Het bestand kon niet worden opgeslagen.'];
        }
        $bhd = function_exists('huidige_beheerder') ? (int)(huidige_beheerder()['id'] ?? 0) : 0;
        $db->prepare("INSERT INTO pagina_versies (pagina_id, versie, bestand, sha256, grootte, bron, bestandsnaam_origineel, scan_json, aangemaakt_door) VALUES (?,?,?,?,?,?,?,?,?)")
           ->execute([
               (int)$pagina['id'], $nr, $naam, hash('sha256', $html), strlen($html),
               (string)($meta['bron'] ?? 'upload'),
               substr((string)($meta['bestandsnaam'] ?? ''), 0, 200) ?: null,
               isset($meta['scan']) ? json_encode($meta['scan'], JSON_UNESCAPED_UNICODE) : null,
               $bhd ?: null,
           ]);
        $vid = (int)$db->lastInsertId();
        if (function_exists('audit_log')) {
            audit_log('versie_geupload', 'pagina', (int)$pagina['id'], [
                'versie' => $nr, 'sha256' => hash('sha256', $html), 'bestand' => $meta['bestandsnaam'] ?? '', 'bytes' => strlen($html),
            ]);
        }
        return ['ok' => true, 'versie_id' => $vid, 'fout' => ''];
    } catch (\Throwable $e) {
        error_log('pag_versie_opslaan: ' . $e->getMessage());
        return ['ok' => false, 'versie_id' => 0, 'fout' => 'Opslaan van de versie is niet gelukt.'];
    }
}

function pag_versie_rij(int $versie_id): ?array {
    try {
        $q = db()->prepare("SELECT * FROM pagina_versies WHERE id = ?");
        $q->execute([$versie_id]);
        return $q->fetch() ?: null;
    } catch (\Throwable $e) { return null; }
}

function pag_versie_lijst(int $pagina_id): array {
    try {
        $q = db()->prepare("SELECT * FROM pagina_versies WHERE pagina_id = ? ORDER BY versie DESC");
        $q->execute([$pagina_id]);
        return $q->fetchAll();
    } catch (\Throwable $e) { return []; }
}

/** De HTML van een versie van schijf lezen. Null als het bestand weg is. */
function pag_versie_inhoud(array $pagina, array $versie): ?string {
    $dir = pag_map((string)$pagina['slug'], false);
    if ($dir === null) return null;
    $pad = $dir . '/' . $versie['bestand'];
    // Padcontrole: 'bestand' komt uit de database, maar vertrouwen doen we niet.
    $echt = realpath($pad);
    if ($echt === false || !str_starts_with($echt, (string)realpath($dir))) return null;
    $inhoud = @file_get_contents($echt);
    return $inhoud === false ? null : $inhoud;
}

/** Een versie actief maken (publiceren of terugrollen). Alleen als de scan bevestigd is. */
function pag_versie_activeer(int $pagina_id, int $versie_id): bool {
    $v = pag_versie_rij($versie_id);
    if (!$v || (int)$v['pagina_id'] !== $pagina_id || $v['scan_bevestigd_op'] === null) return false;
    try {
        db()->prepare("UPDATE paginas SET actieve_versie_id=?, gewijzigd_op=NOW() WHERE id=?")->execute([$versie_id, $pagina_id]);
        if (function_exists('audit_log')) audit_log('versie_geactiveerd', 'pagina', $pagina_id, ['versie' => (int)$v['versie']]);
        pag_versies_opruimen($pagina_id);
        return true;
    } catch (\Throwable $e) { error_log('pag_versie_activeer: ' . $e->getMessage()); return false; }
}

/** Oude versies opruimen: de laatste N blijven staan, de actieve altijd. */
function pag_versies_opruimen(int $pagina_id, int $bewaar = 0): void {
    if ($bewaar <= 0) $bewaar = function_exists('instelling_int') ? instelling_int('paginas.bewaar_versies', 10) : 10;
    $pagina = pag_via_id($pagina_id);
    if (!$pagina) return;
    $dir = pag_map((string)$pagina['slug'], false);
    $lijst = pag_versie_lijst($pagina_id);
    if (count($lijst) <= $bewaar) return;
    $actief = (int)($pagina['actieve_versie_id'] ?? 0);
    foreach (array_slice($lijst, $bewaar) as $v) {
        if ((int)$v['id'] === $actief) continue;
        try {
            db()->prepare("DELETE FROM pagina_versies WHERE id = ?")->execute([(int)$v['id']]);
            if ($dir !== null && is_file($dir . '/' . $v['bestand'])) @unlink($dir . '/' . $v['bestand']);
        } catch (\Throwable $e) { error_log('pag_versies_opruimen: ' . $e->getMessage()); }
    }
}

// ── Assets (afbeeldingen bij een pagina) ─────────────────────────────────

/** De map is de waarheid: geen aparte tabel die uit de pas kan lopen. */
function pag_asset_lijst(string $slug): array {
    $dir = pag_map($slug, false);
    if ($dir === null) return [];
    $uit = [];
    foreach (glob($dir . '/assets/*') ?: [] as $pad) {
        $naam = basename($pad);
        if (!is_file($pad) || $naam === 'index.html' || $naam === '.htaccess') continue;
        $uit[] = ['naam' => $naam, 'grootte' => (int)filesize($pad)];
    }
    usort($uit, fn($a, $b) => strcmp($a['naam'], $b['naam']));
    return $uit;
}

/** Bijgeüploade afbeelding opslaan. Retour: ['ok'=>bool,'naam'=>string,'fout'=>string] */
function pag_asset_opslaan(string $slug, array $upload): array {
    $dir = pag_map($slug);
    if ($dir === null) return ['ok' => false, 'naam' => '', 'fout' => 'De opslagmap kon niet worden aangemaakt.'];
    if (!function_exists('upload_valideer')) return ['ok' => false, 'naam' => '', 'fout' => 'Uploaden is niet beschikbaar.'];

    $v = upload_valideer($upload, pag_asset_typen(), pag_max_asset_bytes());
    if (!$v['ok']) return ['ok' => false, 'naam' => '', 'fout' => $v['fout']];

    // Veilige, voorspelbare bestandsnaam zodat het ontwerp er met een relatief pad naar kan wijzen.
    $basis = pag_slug_maak(pathinfo((string)$v['naam'], PATHINFO_FILENAME)) ?: 'afbeelding';
    $naam  = $basis . '.' . $v['ext'];
    $n = 2;
    while (is_file($dir . '/assets/' . $naam)) { $naam = $basis . '-' . $n . '.' . $v['ext']; $n++; }

    if (!@move_uploaded_file($upload['tmp_name'], $dir . '/assets/' . $naam)) {
        return ['ok' => false, 'naam' => '', 'fout' => 'Het bestand kon niet worden opgeslagen.'];
    }
    @chmod($dir . '/assets/' . $naam, 0644);
    if (function_exists('audit_log')) audit_log('asset_toegevoegd', 'pagina', 0, ['slug' => $slug, 'bestand' => $naam]);
    return ['ok' => true, 'naam' => $naam, 'fout' => ''];
}

function pag_asset_verwijder(string $slug, string $naam): bool {
    $dir = pag_map($slug, false);
    if ($dir === null) return false;
    $naam = basename($naam);
    if ($naam === '' || $naam === 'index.html' || $naam === '.htaccess') return false;
    $pad = $dir . '/assets/' . $naam;
    if (!is_file($pad)) return false;
    @unlink($pad);
    if (function_exists('audit_log')) audit_log('asset_verwijderd', 'pagina', 0, ['slug' => $slug, 'bestand' => $naam]);
    return true;
}

}
