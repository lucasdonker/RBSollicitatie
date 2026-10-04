<?php
/**
 * Pagina's — QR-code voor een deellink (poster, flyer). Geen externe dienst, geen bibliotheek.
 *
 * TCPDF's 2D-barcodeklasse (tcpdf_barcodes_2d.php) zit niet in onze uitgeklede kopie van
 * includes/lib/tcpdf, dus dit is een eigen, kleine encoder volgens ISO/IEC 18004:
 * bytemodus, foutcorrectie M (15%), versie 1 t/m 10 (tot 213 tekens — ruim genoeg voor
 * /p/<slug>?bron=<campagne>). De opbouw volgt de bekende referentie-implementatie van
 * Project Nayuki; de harnas-test controleert de Reed-Solomon-codes en de formaatbits.
 *
 * Uitvoer: pag_qr_matrix() (bool[y][x]), pag_qr_svg() (drukwerk, schaalt oneindig) en
 * pag_qr_png() (alleen met GD). Laadt alleen waar nodig (beheer), niet op de publieke pagina.
 */

if (!function_exists('pag_qr_matrix')) {

/** Foutcorrectie M, versie 1–10: [ecc-codewoorden per blok, aantal blokken]. */
function pag_qr_tabel(int $v): array {
    $ecc     = [1 => 10, 16, 26, 18, 24, 16, 18, 22, 22, 26];
    $blokken = [1 => 1, 1, 1, 2, 2, 4, 4, 4, 5, 5];
    return [$ecc[$v], $blokken[$v]];
}

/** Aantal modules voor data + foutcorrectie (zonder vaste patronen). */
function pag_qr_ruwe_modules(int $v): int {
    $r = (16 * $v + 128) * $v + 64;
    if ($v >= 2) {
        $n = intdiv($v, 7) + 2;
        $r -= (25 * $n - 10) * $n - 55;
        if ($v >= 7) $r -= 36;
    }
    return $r;
}

/** Aantal datacodewoorden bij foutcorrectie M. */
function pag_qr_data_codewoorden(int $v): int {
    $t = pag_qr_tabel($v);
    return intdiv(pag_qr_ruwe_modules($v), 8) - $t[0] * $t[1];
}

/** Vermenigvuldigen in GF(2^8) met het QR-polynoom 0x11D. */
function pag_qr_gf_mul(int $x, int $y): int {
    $z = 0;
    for ($i = 7; $i >= 0; $i--) {
        $z = ($z << 1) ^ (($z >> 7) * 0x11D);
        $z ^= (($y >> $i) & 1) * $x;
    }
    return $z;
}

/** Reed-Solomon-generator van de gegeven graad (coëfficiënten, hoogste macht weggelaten). */
function pag_qr_rs_deler(int $graad): array {
    $r = array_fill(0, $graad, 0);
    $r[$graad - 1] = 1;
    $wortel = 1;
    for ($i = 0; $i < $graad; $i++) {
        for ($j = 0; $j < $graad; $j++) {
            $r[$j] = pag_qr_gf_mul($r[$j], $wortel);
            if ($j + 1 < $graad) $r[$j] ^= $r[$j + 1];
        }
        $wortel = pag_qr_gf_mul($wortel, 0x02);
    }
    return $r;
}

/** De foutcorrectiecodewoorden bij een blok data. */
function pag_qr_rs_rest(array $data, array $deler): array {
    $r = array_fill(0, count($deler), 0);
    foreach ($data as $b) {
        $f = $b ^ array_shift($r);
        $r[] = 0;
        foreach ($deler as $i => $d) $r[$i] ^= pag_qr_gf_mul($d, $f);
    }
    return $r;
}

/** Datacodewoorden (bytemodus) voor $tekst in versie $v. */
function pag_qr_data(string $tekst, int $v): array {
    $cap = pag_qr_data_codewoorden($v) * 8;
    $bits = [];
    $zet = function (int $waarde, int $n) use (&$bits): void {
        for ($i = $n - 1; $i >= 0; $i--) $bits[] = ($waarde >> $i) & 1;
    };
    $zet(0b0100, 4);                                 // bytemodus
    $zet(strlen($tekst), $v < 10 ? 8 : 16);          // lengte
    foreach (str_split($tekst) as $c) $zet(ord($c), 8);
    $zet(0, min(4, $cap - count($bits)));            // afsluiter
    $zet(0, (8 - count($bits) % 8) % 8);             // aanvullen tot hele byte
    $data = [];
    foreach (array_chunk($bits, 8) as $byte) $data[] = (int)bindec(implode('', $byte));
    for ($p = 0xEC; count($data) < $cap / 8; $p ^= 0xEC ^ 0x11) $data[] = $p;   // opvulbytes EC 11 EC …
    return $data;
}

/** Data in blokken verdelen, foutcorrectie erbij en verweven (volgorde van de standaard). */
function pag_qr_codewoorden(array $data, int $v): array {
    $t = pag_qr_tabel($v);
    $ecl = $t[0];
    $nb = $t[1];
    $ruw = intdiv(pag_qr_ruwe_modules($v), 8);
    $kort = $nb - $ruw % $nb;
    $kort_len = intdiv($ruw, $nb);
    $deler = pag_qr_rs_deler($ecl);
    $blokken = [];
    $k = 0;
    for ($i = 0; $i < $nb; $i++) {
        $len = $kort_len - $ecl + ($i < $kort ? 0 : 1);
        $dat = array_slice($data, $k, $len);
        $k += $len;
        $ecc = pag_qr_rs_rest($dat, $deler);
        if ($i < $kort) $dat[] = 0;     // plaatshouder, wordt bij het verweven overgeslagen
        $blokken[] = array_merge($dat, $ecc);
    }
    $uit = [];
    $blen = count($blokken[0]);
    for ($i = 0; $i < $blen; $i++) {
        foreach ($blokken as $j => $b) {
            if ($i !== $kort_len - $ecl || $j >= $kort) $uit[] = $b[$i];
        }
    }
    return $uit;
}

/** Posities van de uitlijnpatronen. */
function pag_qr_uitlijn(int $v): array {
    if ($v === 1) return [];
    $n = intdiv($v, 7) + 2;
    $stap = intdiv($v * 8 + $n * 3 + 5, $n * 4 - 4) * 2;
    $uit = [];
    for ($i = 0, $pos = $v * 4 + 17 - 7; $i < $n - 1; $i++, $pos -= $stap) array_unshift($uit, $pos);
    array_unshift($uit, 6);
    return $uit;
}

/** Omkeren volgens masker $m op (x, y)? */
function pag_qr_masker(int $m, int $x, int $y): bool {
    switch ($m) {
        case 0:  return ($x + $y) % 2 === 0;
        case 1:  return $y % 2 === 0;
        case 2:  return $x % 3 === 0;
        case 3:  return ($x + $y) % 3 === 0;
        case 4:  return (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0;
        case 5:  return ($x * $y % 2 + $x * $y % 3) === 0;
        case 6:  return (($x * $y % 2 + $x * $y % 3) % 2) === 0;
        default: return ((($x + $y) % 2 + $x * $y % 3) % 2) === 0;
    }
}

/** De 15 formaatbits voor foutcorrectie M en masker $m (BCH + XOR-masker). */
function pag_qr_formaatbits(int $m): int {
    $data = (0 << 3) | $m;          // M = 00
    $rem = $data;
    for ($i = 0; $i < 10; $i++) $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
    return (($data << 10) | $rem) ^ 0x5412;
}

/** Strafpunten van een matrix (de vier regels van de standaard), om het beste masker te kiezen. */
function pag_qr_straf(array $m): int {
    $n = count($m);
    $straf = 0;
    $lijnen = [];
    for ($i = 0; $i < $n; $i++) {
        $lijnen[] = $m[$i];
        $lijnen[] = array_column($m, $i);
    }
    foreach ($lijnen as $l) {
        // Regel 1: vijf of meer gelijke modules op een rij
        $run = 1;
        for ($i = 1; $i <= $n; $i++) {
            if ($i < $n && $l[$i] === $l[$i - 1]) { $run++; continue; }
            if ($run >= 5) $straf += 3 + $run - 5;
            $run = 1;
        }
        // Regel 3: een vinderachtig patroon 1011101 met vier lichte modules ernaast
        $s = implode('', array_map('intval', $l));
        $straf += 40 * (substr_count($s, '10111010000') + substr_count($s, '00001011101'));
    }
    // Regel 2: blokjes van 2x2 dezelfde kleur
    for ($y = 0; $y < $n - 1; $y++) {
        for ($x = 0; $x < $n - 1; $x++) {
            $c = $m[$y][$x];
            if ($c === $m[$y][$x + 1] && $c === $m[$y + 1][$x] && $c === $m[$y + 1][$x + 1]) $straf += 3;
        }
    }
    // Regel 4: verhouding donker/licht
    $donker = 0;
    foreach ($m as $rij) foreach ($rij as $c) if ($c) $donker++;
    $totaal = $n * $n;
    $straf += 10 * max(0, (int)ceil(abs($donker * 20 - $totaal * 10) / $totaal) - 1);
    return $straf;
}

/**
 * De QR-matrix voor $tekst: bool[y][x] (true = donker), zonder stille zone.
 * Null als de tekst te lang is voor versie 10 (213 bytes).
 */
function pag_qr_matrix(string $tekst): ?array {
    $v = 0;
    for ($i = 1; $i <= 10; $i++) {
        if (4 + ($i < 10 ? 8 : 16) + 8 * strlen($tekst) <= pag_qr_data_codewoorden($i) * 8) { $v = $i; break; }
    }
    if ($v === 0) return null;

    $n = $v * 4 + 17;
    $m = array_fill(0, $n, array_fill(0, $n, false));
    $f = $m;   // vaste patronen (niet maskeren, niet overschrijven)
    $zet = function (int $x, int $y, bool $donker) use (&$m, &$f): void { $m[$y][$x] = $donker; $f[$y][$x] = true; };

    // Tijdlijnen
    for ($i = 0; $i < $n; $i++) { $zet(6, $i, $i % 2 === 0); $zet($i, 6, $i % 2 === 0); }
    // Vinderpatronen (met witte rand)
    foreach ([[3, 3], [$n - 4, 3], [3, $n - 4]] as $c) {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $x = $c[0] + $dx; $y = $c[1] + $dy;
                if ($x < 0 || $y < 0 || $x >= $n || $y >= $n) continue;
                $d = max(abs($dx), abs($dy));
                $zet($x, $y, $d !== 2 && $d !== 4);
            }
        }
    }
    // Uitlijnpatronen (niet op de hoeken met een vinder)
    $pos = pag_qr_uitlijn($v);
    $laatste = count($pos) - 1;
    foreach ($pos as $i => $px) {
        foreach ($pos as $j => $py) {
            if (($i === 0 && $j === 0) || ($i === 0 && $j === $laatste) || ($i === $laatste && $j === 0)) continue;
            for ($dy = -2; $dy <= 2; $dy++) for ($dx = -2; $dx <= 2; $dx++) $zet($px + $dx, $py + $dy, max(abs($dx), abs($dy)) !== 1);
        }
    }
    // Formaatbits (eerst gereserveerd, echte waarde na de maskerkeuze)
    $formaat = function (int $bits) use ($zet, $n): void {
        $bit = fn(int $i): bool => (($bits >> $i) & 1) === 1;
        for ($i = 0; $i <= 5; $i++) $zet(8, $i, $bit($i));
        $zet(8, 7, $bit(6));
        $zet(8, 8, $bit(7));
        $zet(7, 8, $bit(8));
        for ($i = 9; $i < 15; $i++) $zet(14 - $i, 8, $bit($i));
        for ($i = 0; $i < 8; $i++) $zet($n - 1 - $i, 8, $bit($i));
        for ($i = 8; $i < 15; $i++) $zet(8, $n - 15 + $i, $bit($i));
        $zet(8, $n - 8, true);   // altijd donker
    };
    $formaat(0);
    // Versie-informatie (versie 7 en hoger)
    if ($v >= 7) {
        $rem = $v;
        for ($i = 0; $i < 12; $i++) $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        $bits = ($v << 12) | $rem;
        for ($i = 0; $i < 18; $i++) {
            $b = (($bits >> $i) & 1) === 1;
            $a = $n - 11 + $i % 3;
            $c = intdiv($i, 3);
            $zet($a, $c, $b);
            $zet($c, $a, $b);
        }
    }

    // Codewoorden in zigzag plaatsen, van rechtsonder naar boven, twee kolommen tegelijk
    $cw = pag_qr_codewoorden(pag_qr_data($tekst, $v), $v);
    $totaal_bits = count($cw) * 8;
    $i = 0;
    for ($rechts = $n - 1; $rechts >= 1; $rechts -= 2) {
        if ($rechts === 6) $rechts = 5;
        for ($vert = 0; $vert < $n; $vert++) {
            for ($j = 0; $j < 2; $j++) {
                $x = $rechts - $j;
                $omhoog = (($rechts + 1) & 2) === 0;
                $y = $omhoog ? $n - 1 - $vert : $vert;
                if (!$f[$y][$x] && $i < $totaal_bits) {
                    $m[$y][$x] = (($cw[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                    $i++;
                }
            }
        }
    }

    // Masker kiezen: het laagste aantal strafpunten
    $maskeer = function (array $mat, int $mk) use ($f, $n): array {
        for ($y = 0; $y < $n; $y++) for ($x = 0; $x < $n; $x++) {
            if (!$f[$y][$x] && pag_qr_masker($mk, $x, $y)) $mat[$y][$x] = !$mat[$y][$x];
        }
        return $mat;
    };
    $beste = null; $beste_straf = PHP_INT_MAX; $beste_mk = 0;
    for ($mk = 0; $mk < 8; $mk++) {
        $m_kopie = $m;
        $m = $maskeer($m_kopie, $mk);
        $formaat(pag_qr_formaatbits($mk));
        $s = pag_qr_straf($m);
        if ($s < $beste_straf) { $beste_straf = $s; $beste = $m; $beste_mk = $mk; }
        $m = $m_kopie;
    }
    return $beste;
}

/**
 * SVG voor drukwerk: één pad, vier modules stille zone, geen vaste pixelmaat (viewBox).
 * $titel wordt de toegankelijke naam (role="img" + <title>).
 */
function pag_qr_svg(string $tekst, string $titel = ''): string {
    $m = pag_qr_matrix($tekst);
    if ($m === null) return '';
    $n = count($m);
    $q = 4;
    $w = $n + 2 * $q;
    $pad = '';
    foreach ($m as $y => $rij) foreach ($rij as $x => $donker) {
        if ($donker) $pad .= 'M' . ($x + $q) . ' ' . ($y + $q) . 'h1v1h-1z';
    }
    $t = $titel !== '' ? $titel : 'QR-code';
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $w . '" width="' . ($w * 8) . '" height="' . ($w * 8) . '"'
         . ' role="img" shape-rendering="crispEdges"><title>' . htmlspecialchars($t, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</title>'
         . '<rect width="100%" height="100%" fill="#ffffff"/><path fill="#000000" d="' . $pad . '"/></svg>';
}

/** PNG (zwart op wit), $schaal pixels per module. Null zonder GD of bij een te lange tekst. */
function pag_qr_png(string $tekst, int $schaal = 16): ?string {
    if (!function_exists('imagecreate') || !function_exists('imagepng')) return null;
    $m = pag_qr_matrix($tekst);
    if ($m === null) return null;
    $n = count($m);
    $q = 4;
    $px = ($n + 2 * $q) * $schaal;
    $img = imagecreate($px, $px);
    imagecolorallocate($img, 255, 255, 255);     // eerste kleur = achtergrond
    $zwart = imagecolorallocate($img, 0, 0, 0);
    foreach ($m as $y => $rij) foreach ($rij as $x => $donker) {
        if ($donker) imagefilledrectangle($img, ($x + $q) * $schaal, ($y + $q) * $schaal, ($x + $q + 1) * $schaal - 1, ($y + $q + 1) * $schaal - 1, $zwart);
    }
    ob_start();
    imagepng($img);
    $uit = (string)ob_get_clean();
    imagedestroy($img);
    return $uit;
}

}
