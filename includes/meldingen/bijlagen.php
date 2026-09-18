<?php
/**
 * Paal 13 — Meldingen: bijlagen.
 *
 * Bestanden (magazine, informatieblad, ...) die met een sjabloon of een handmatig bericht
 * meegaan. Opslag buiten het webbereik (uploads/bijlagen met .htaccess deny), download alleen
 * via download.php met een getekende, verlopende link. E-mail krijgt het bestand als bijlage;
 * WhatsApp/SMS krijgt de downloadlink.
 */

if (!function_exists('bijlagen_auto_setup')) {

function bijlagen_auto_setup(): void {
    static $done = false; if ($done) return; $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS `bijlagen` (
            `id`            INT AUTO_INCREMENT PRIMARY KEY,
            `bestandsnaam`  VARCHAR(200) NOT NULL,
            `opslagnaam`    VARCHAR(80)  NOT NULL,
            `mime`          VARCHAR(100) NOT NULL,
            `grootte`       INT NOT NULL DEFAULT 0,
            `omschrijving`  VARCHAR(200) DEFAULT NULL,
            `geupload_door` INT DEFAULT NULL,
            `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Throwable $e) { error_log('bijlagen_auto_setup: ' . $e->getMessage()); }
}

/** Toegestane bestandstypen: extensie => mime. */
function bijlagen_typen(): array {
    return [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];
}

/**
 * Maximale bestandsgrootte: 25 MB, of minder als de server (upload_max_filesize/post_max_size) minder toestaat.
 * Mailservers weigeren vaak bijlagen boven ~20 MB; grote bestanden gaan beter via de downloadlink.
 */
function bijlagen_max_bytes(): int {
    $naar_bytes = function (string $v): int {
        $v = trim($v); if ($v === '' || $v === '-1') return PHP_INT_MAX;
        $n = (int)$v; $e = strtolower(substr($v, -1));
        return $e === 'g' ? $n * 1073741824 : ($e === 'm' ? $n * 1048576 : ($e === 'k' ? $n * 1024 : $n));
    };
    return (int)min(25 * 1048576, $naar_bytes((string)ini_get('upload_max_filesize')), $naar_bytes((string)ini_get('post_max_size')));
}

/** De opslagmap (aangemaakt met .htaccess die directe toegang blokkeert). */
function bijlagen_map(): string {
    $dir = dirname(__DIR__, 2) . '/uploads/bijlagen';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) @file_put_contents($ht, "# Geen directe toegang: downloads lopen via download.php met een getekende link\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
    if (!is_file($dir . '/index.html')) @file_put_contents($dir . '/index.html', '');
    return $dir;
}

/** Geheim voor de downloadlinks (eenmalig gegenereerd, in de instellingen). */
function bijlage_geheim(): string {
    $g = function_exists('instelling') ? instelling('bijlage.geheim', '') : '';
    if ($g === '' && function_exists('instelling_opslaan')) { $g = bin2hex(random_bytes(32)); instelling_opslaan('bijlage.geheim', $g); }
    return $g;
}

/**
 * Sla een geüpload bestand op ($_FILES-element). Retourneert ['ok'=>bool, 'id'=>?int, 'fout'=>?string].
 */
function bijlage_opslaan(array $upload, ?int $door = null, string $omschrijving = ''): array {
    bijlagen_auto_setup();
    if (empty($upload['tmp_name']) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return ['ok' => false, 'id' => null, 'fout' => 'geen bestand'];
    $max = bijlagen_max_bytes();
    if ((int)$upload['size'] > $max) return ['ok' => false, 'id' => null, 'fout' => 'bestand groter dan ' . round($max / 1048576) . ' MB'];
    $naam = (string)($upload['name'] ?? 'bestand');
    $ext  = strtolower(pathinfo($naam, PATHINFO_EXTENSION));
    $typen = bijlagen_typen();
    if (!isset($typen[$ext])) return ['ok' => false, 'id' => null, 'fout' => 'bestandstype niet toegestaan (pdf, jpg, png, webp, docx, xlsx)'];
    // Inhoud controleren (magic bytes) i.p.v. alleen de extensie
    $mime = function_exists('mime_content_type') ? (@mime_content_type($upload['tmp_name']) ?: '') : '';
    if ($mime !== '' && $mime !== $typen[$ext] && !($ext === 'jpg' && $mime === 'image/jpeg')) {
        if (!in_array($mime, ['application/zip', 'application/octet-stream'], true) || !in_array($ext, ['docx', 'xlsx'], true)) return ['ok' => false, 'id' => null, 'fout' => 'bestandsinhoud past niet bij het type'];
    }
    $veilige_naam = preg_replace('/[^A-Za-z0-9._-]+/', '_', $naam);
    $veilige_naam = substr(trim($veilige_naam, '_') ?: 'bestand.' . $ext, 0, 200);
    $opslag = bin2hex(random_bytes(16)) . '.' . $ext;
    $doel = bijlagen_map() . '/' . $opslag;
    if (!@move_uploaded_file($upload['tmp_name'], $doel)) return ['ok' => false, 'id' => null, 'fout' => 'opslaan mislukt (map niet schrijfbaar?)'];
    try {
        db()->prepare("INSERT INTO bijlagen (bestandsnaam, opslagnaam, mime, grootte, omschrijving, geupload_door) VALUES (?,?,?,?,?,?)")
           ->execute([$veilige_naam, $opslag, $typen[$ext], (int)$upload['size'], $omschrijving ?: null, $door]);
        $id = (int)db()->lastInsertId();
        if (function_exists('audit_log')) audit_log('bijlage_geupload', 'bijlage', $id, ['grootte' => (int)$upload['size']]);
        return ['ok' => true, 'id' => $id, 'fout' => null];
    } catch (\Throwable $e) { @unlink($doel); return ['ok' => false, 'id' => null, 'fout' => 'database: ' . $e->getMessage()]; }
}

function bijlage_rij(int $id): ?array {
    bijlagen_auto_setup();
    try { $q = db()->prepare("SELECT * FROM bijlagen WHERE id=?"); $q->execute([$id]); return $q->fetch() ?: null; }
    catch (\Throwable $e) { return null; }
}

/** Volledig pad op schijf, of null als het bestand ontbreekt. */
function bijlage_pad(array $rij): ?string {
    $pad = bijlagen_map() . '/' . basename((string)$rij['opslagnaam']);
    return is_file($pad) ? $pad : null;
}

function bijlage_verwijderen(int $id): bool {
    $r = bijlage_rij($id);
    if (!$r) return false;
    $pad = bijlage_pad($r);
    if ($pad) @unlink($pad);
    try { db()->prepare("DELETE FROM bijlagen WHERE id=?")->execute([$id]); return true; } catch (\Throwable $e) { return false; }
}

/** Alle bijlagen (nieuwste eerst). */
function bijlagen_lijst(): array {
    bijlagen_auto_setup();
    try { return db()->query("SELECT * FROM bijlagen ORDER BY id DESC")->fetchAll(); } catch (\Throwable $e) { return []; }
}

/** Handtekening voor een downloadlink. */
function bijlage_token(int $id, int $exp): string {
    return substr(hash_hmac('sha256', $id . '|' . $exp, bijlage_geheim()), 0, 40);
}

/** Getekende, verlopende downloadlink (voor WhatsApp/SMS en als knop in e-mail). */
function bijlage_download_url(int $id, int $geldig_dagen = 14): string {
    $exp = time() + max(1, $geldig_dagen) * 86400;
    return rtrim(APP_URL, '/') . '/download.php?b=' . $id . '&e=' . $exp . '&t=' . bijlage_token($id, $exp);
}

/** Klaar-voor-verzending-lijst uit ids: [['id','pad','naam','mime','url']]. Ontbrekende bestanden worden overgeslagen. */
function bijlagen_van_ids(array $ids): array {
    $uit = [];
    foreach (array_unique(array_map('intval', $ids)) as $id) {
        if ($id <= 0) continue;
        $r = bijlage_rij($id);
        if (!$r) continue;
        $pad = bijlage_pad($r);
        if (!$pad) continue;
        $uit[] = ['id' => $id, 'pad' => $pad, 'naam' => $r['bestandsnaam'], 'mime' => $r['mime'], 'url' => bijlage_download_url($id)];
    }
    return $uit;
}

/** Ids die door sjablonen gebruikt worden (voor de opschoning). */
function bijlagen_in_gebruik(): array {
    $uit = [];
    try {
        foreach (db()->query("SELECT bijlagen_json FROM mail_templates WHERE bijlagen_json IS NOT NULL AND bijlagen_json<>''")->fetchAll(PDO::FETCH_COLUMN) as $j) {
            $a = json_decode((string)$j, true);
            if (is_array($a)) foreach ($a as $v) $uit[] = (int)(is_array($v) ? ($v['bijlage_id'] ?? 0) : $v);
        }
    } catch (\Throwable $e) {}
    return array_values(array_unique(array_filter($uit)));
}

}
