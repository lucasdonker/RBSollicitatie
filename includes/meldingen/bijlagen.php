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
    return (int)min(25 * 1048576, upload_server_max_bytes());
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
    // Validatie (fout, grootte, extensie, magic bytes, veilige naam) via de gedeelde helper in includes/upload.php
    $v = upload_valideer($upload, bijlagen_typen(), bijlagen_max_bytes());
    if (!$v['ok']) return ['ok' => false, 'id' => null, 'fout' => $v['fout']];
    $ext = $v['ext']; $veilige_naam = $v['naam'];
    $opslag = bin2hex(random_bytes(16)) . '.' . $ext;
    $doel = bijlagen_map() . '/' . $opslag;
    if (!@move_uploaded_file($upload['tmp_name'], $doel)) return ['ok' => false, 'id' => null, 'fout' => 'opslaan mislukt (map niet schrijfbaar?)'];
    try {
        db()->prepare("INSERT INTO bijlagen (bestandsnaam, opslagnaam, mime, grootte, omschrijving, geupload_door) VALUES (?,?,?,?,?,?)")
           ->execute([$veilige_naam, $opslag, $v['mime'], $v['grootte'], $omschrijving ?: null, $door]);
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

/** Handtekening voor een downloadlink (24 hex; met HMAC-sleutel van 32 bytes ruim voldoende). */
function bijlage_token(int $id, int $exp): string {
    return substr(hash_hmac('sha256', $id . '|' . $exp, bijlage_geheim()), 0, 24);
}

/**
 * Getekende, verlopende downloadlink: kort en netjes (/d/<id>-<verloopt>-<token>, via .htaccess
 * naar download.php). Voor WhatsApp/SMS en als knop in e-mail.
 */
function bijlage_download_url(int $id, int $geldig_dagen = 14): string {
    $exp = time() + max(1, $geldig_dagen) * 86400;
    return rtrim(APP_URL, '/') . '/d/' . $id . '-' . $exp . '-' . bijlage_token($id, $exp);
}

/** Leesbare naam voor in berichten: "magazine_2026_v2.pdf" → "Magazine 2026 v2". */
function bijlage_naam_mooi(string $bestandsnaam): string {
    $n = pathinfo($bestandsnaam, PATHINFO_FILENAME);
    $n = trim((string)preg_replace('/[_\-]+/', ' ', $n));
    return $n === '' ? 'Bijlage' : ucfirst($n);
}

/**
 * Boven deze grootte gaat een bestand niet als e-mailbijlage mee maar als downloadknop
 * (instelling bijlage.mail_max_mb, standaard 15 MB). Weigert de mailserver een kleinere bijlage
 * alsnog, dan verstuurt de kern de mail automatisch opnieuw met een downloadknop.
 */
function bijlagen_mail_max_bytes(): int {
    $mb = function_exists('instelling_int') ? instelling_int('bijlage.mail_max_mb', 15) : 15;
    return max(1, $mb) * 1048576;
}

/** Klaar-voor-verzending-lijst uit ids: [['id','pad','naam','naam_mooi','mime','grootte','url']]. Ontbrekende bestanden worden overgeslagen. */
function bijlagen_van_ids(array $ids): array {
    $uit = [];
    foreach (array_unique(array_map('intval', $ids)) as $id) {
        if ($id <= 0) continue;
        $r = bijlage_rij($id);
        if (!$r) continue;
        $pad = bijlage_pad($r);
        if (!$pad) continue;
        $uit[] = ['id' => $id, 'pad' => $pad, 'naam' => $r['bestandsnaam'], 'naam_mooi' => bijlage_naam_mooi((string)$r['bestandsnaam']),
                  'mime' => $r['mime'], 'grootte' => (int)($r['grootte'] ?: (@filesize($pad) ?: 0)), 'url' => bijlage_download_url($id)];
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
