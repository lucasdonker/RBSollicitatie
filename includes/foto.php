<?php
/**
 * Paal 13 — De foto van een sollicitant (uit het kennismakingsformulier).
 *
 * Eén plek voor opslaan, tonen en weggooien. Tot 2.99.3 stonden foto's in uploads/fotos/,
 * rechtstreeks op te vragen met een naam als sol_12_1726000000.jpg: wie het id en ongeveer
 * het tijdstip kende, kon hem raden. En bij het wissen van een dossier bleef het bestand
 * gewoon staan. Nu:
 *  - opslag in de afgeschermde documentenmap (DOCUMENTEN_MAP, buiten de webroot), met een
 *    willekeurige naam;
 *  - tonen alleen via /foto.php, dat eerst kijkt of je hem mag zien: de sollicitant zelf,
 *    of een beheerder met een recht waarbij foto's horen;
 *  - weg bij vervangen, verwijderen en AVG-wissen, en een opruimronde in de cron voor
 *    bestanden waarvan de rij langs een andere weg verdween (CASCADE, seizoen wissen).
 *
 * In blk_formulieren.foto_url staat "foto:<naam>". Oude waarden ("/uploads/fotos/<naam>")
 * worden eenmalig verhuisd door foto_auto_setup() (draait via db_failsafe()).
 */

if (!function_exists('foto_opslaan')) {

/** De afgeschermde map, of null als hij niet te maken of te beschrijven is. */
function foto_map(): ?string {
    $basis = function_exists('documenten_map') ? documenten_map() : null;
    if ($basis === null) return null;
    $dir = $basis . '/fotos';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    return (is_dir($dir) && is_writable($dir)) ? $dir : null;
}

/** De oude, publieke map. Alleen nog om uit te verhuizen en om te lezen tot dat gelukt is. */
function foto_oude_map(): string {
    return dirname(__DIR__) . '/uploads/fotos';
}

/** De bestandsnaam uit een opgeslagen waarde, of null als het geen sollicitantfoto is. */
function foto_naam(?string $opgeslagen): ?string {
    $w = (string)$opgeslagen;
    if (str_starts_with($w, 'foto:')) $naam = substr($w, 5);
    // Oud: "/uploads/fotos/<naam>", en nog ouder een volledige URL daarnaartoe.
    elseif (preg_match('#/uploads/fotos/([^/?\#]+)$#', $w, $m)) $naam = $m[1];
    else return null;
    return preg_match('/^sol_\d+_[A-Za-z0-9]+\.(jpg|png|webp)$/', $naam) ? $naam : null;
}

/** Van welke sollicitatie is deze foto? Het id staat in de naam. */
function foto_sol_id(string $naam): int {
    $delen = explode('_', $naam);
    return (int)($delen[1] ?? 0);
}

/** Het bestand op schijf: eerst de nieuwe plek, dan de oude. */
function foto_pad(string $naam): ?string {
    foreach ([foto_map(), foto_oude_map()] as $dir) {
        if ($dir && is_file($dir . '/' . $naam)) return $dir . '/' . $naam;
    }
    return null;
}

/** De URL om hem te tonen. */
function foto_beveiligde_url(string $naam): string {
    return rtrim(APP_URL, '/') . '/foto.php?f=' . rawurlencode($naam);
}

/**
 * Een kleine, ingesloten versie (data-URL, JPEG) voor de incheck-tablet. Die heeft geen
 * inlog, dus foto.php laat hem terecht niets zien; de kiosk krijgt na een geldige code
 * alleen de miniaturen van de hooguit drie treffers mee. Null zonder foto of zonder GD.
 */
function foto_miniatuur_data_url(?string $opgeslagen, int $px = 128): ?string {
    $naam = foto_naam($opgeslagen);
    $pad  = $naam !== null ? foto_pad($naam) : null;
    if ($pad === null || !function_exists('imagecreatefromstring')) return null;
    $bron = @imagecreatefromstring((string)file_get_contents($pad));
    if (!$bron) return null;
    $b = imagesx($bron); $h = imagesy($bron); $zijde = min($b, $h);
    $doel = imagecreatetruecolor($px, $px);
    // Vierkant uit het midden, zodat een ronde avatar geen vervormd gezicht toont.
    imagecopyresampled($doel, $bron, 0, 0, (int)(($b - $zijde) / 2), (int)(($h - $zijde) / 2), $px, $px, $zijde, $zijde);
    ob_start(); imagejpeg($doel, null, 80); $jpg = (string)ob_get_clean();
    imagedestroy($bron); imagedestroy($doel);
    return 'data:image/jpeg;base64,' . base64_encode($jpg);
}

/**
 * Een geüploade foto controleren en opslaan. Geeft de waarde voor blk_formulieren.foto_url
 * terug, of null als het bestand geen bruikbare foto is.
 */
function foto_opslaan(int $sol_id, array $bestand): ?string {
    if (empty($bestand['tmp_name']) || ($bestand['error'] ?? 1) !== 0) return null;
    if (($bestand['size'] ?? 0) >= 5 * 1024 * 1024) return null;
    // De inhoud telt, niet de extensie die iemand eraan gaf.
    $info = @getimagesize($bestand['tmp_name']);
    $typen = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    $ext = $typen[$info[2] ?? 0] ?? null;
    if ($info === false || $ext === null) return null;

    $dir = foto_map();
    if ($dir === null) { error_log('foto_opslaan: geen schrijfbare fotomap'); return null; }
    $naam = 'sol_' . $sol_id . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($bestand['tmp_name'], $dir . '/' . $naam)) return null;
    @chmod($dir . '/' . $naam, 0600);
    return 'foto:' . $naam;
}

/** Het bestand weggooien (op beide plekken). Doet niets bij een lege of vreemde waarde. */
function foto_verwijderen(?string $opgeslagen): void {
    $naam = foto_naam($opgeslagen);
    if ($naam === null) return;
    foreach ([foto_map(), foto_oude_map()] as $dir) {
        if ($dir && is_file($dir . '/' . $naam)) @unlink($dir . '/' . $naam);
    }
}

/** De foto van een sollicitatie weggooien, vóórdat de BLK-rij verdwijnt. */
function foto_van_sollicitatie_verwijderen(int $sol_id): void {
    try {
        $q = db()->prepare("SELECT foto_url FROM blk_formulieren WHERE sollicitatie_id = ?");
        $q->execute([$sol_id]);
        foto_verwijderen($q->fetchColumn() ?: null);
    } catch (\PDOException $e) { /* geen BLK-tabel: dan ook geen foto */ }
}

/**
 * Mag de huidige bezoeker deze foto zien? De sollicitant zelf, of een beheerder met een
 * recht waarbij hij de sollicitant in beeld krijgt (lijst, BLK, selectiedag, trainingsweekend).
 */
function foto_mag_zien(string $naam): bool {
    if (!empty($_SESSION['bhd_id']) && function_exists('mag')) {
        foreach (['sollicitanten', 'blk', 'selectiedag', 'trainingsweekend'] as $module) {
            if (mag($module, 'lezen')) return true;
        }
    }
    return !empty($_SESSION['portaal_sol_id']) && (int)$_SESSION['portaal_sol_id'] === foto_sol_id($naam);
}

/**
 * Eenmalig (via db_failsafe): foto's uit de publieke map naar de afgeschermde map, met een
 * nieuwe, willekeurige naam. Lukt het verhuizen niet, dan blijft de oude waarde staan en
 * toont foto.php hem nog vanaf de oude plek.
 */
function foto_auto_setup(): void {
    static $gedaan = false; if ($gedaan) return; $gedaan = true;
    $oud = foto_oude_map();
    if (!is_dir($oud)) return;
    // Wat er om welke reden ook blijft liggen, is niet meer rechtstreeks op te vragen.
    if (!is_file($oud . '/.htaccess')) {
        @file_put_contents($oud . '/.htaccess', "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
    }
    $dir = foto_map();
    if ($dir === null) return;
    try {
        $db = db();
        $rijen = $db->query("SELECT sollicitatie_id, foto_url FROM blk_formulieren WHERE foto_url LIKE '%/uploads/fotos/%'")->fetchAll();
        $upd = $db->prepare("UPDATE blk_formulieren SET foto_url = ? WHERE sollicitatie_id = ? AND foto_url = ?");
        foreach ($rijen as $r) {
            $naam = foto_naam($r['foto_url']);
            if ($naam === null || !is_file($oud . '/' . $naam)) continue;
            $ext = pathinfo($naam, PATHINFO_EXTENSION);
            $nieuw = 'sol_' . (int)$r['sollicitatie_id'] . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
            if (!@copy($oud . '/' . $naam, $dir . '/' . $nieuw)) continue;
            @chmod($dir . '/' . $nieuw, 0600);
            $upd->execute(['foto:' . $nieuw, $r['sollicitatie_id'], $r['foto_url']]);
            @unlink($oud . '/' . $naam);
        }
    } catch (\Throwable $e) { error_log('foto_auto_setup: ' . $e->getMessage()); }
}

/**
 * Cron: fotobestanden waar geen BLK-rij meer naar wijst. Zo verdwijnt ook de foto als een
 * sollicitatie langs een andere weg is verwijderd (seizoen wissen, CASCADE). Alleen
 * bestanden ouder dan een uur: een net geüploade foto heeft zijn rij misschien nog niet.
 */
function foto_wezen_opruimen(): int {
    $in_gebruik = [];
    try {
        foreach (db()->query("SELECT foto_url FROM blk_formulieren WHERE foto_url IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $w) {
            // Ook de kale bestandsnaam: een waarde met een afwijkende naam die foto_naam()
            // niet herkent, is nog steeds in gebruik en mag niet weg.
            $in_gebruik[basename((string)$w)] = true;
            $n = foto_naam($w);
            if ($n !== null) $in_gebruik[$n] = true;
        }
    } catch (\Throwable $e) { error_log('foto_wezen_opruimen: ' . $e->getMessage()); return 0; }

    $weg = 0;
    foreach ([foto_map(), foto_oude_map()] as $dir) {
        if (!$dir || !is_dir($dir)) continue;
        foreach (glob($dir . '/sol_*') ?: [] as $pad) {
            $naam = basename($pad);
            if (isset($in_gebruik[$naam]) || filemtime($pad) > time() - 3600) continue;
            if (@unlink($pad)) $weg++;
        }
    }
    return $weg;
}

}
