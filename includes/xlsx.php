<?php
/**
 * Paal 13 — Kleine, eigen Excel-schrijver (.xlsx). GEEN HTML, geen externe bibliotheek.
 *
 * Genoeg voor nette exports: meerdere tabbladen, een opgemaakte kopregel die blijft staan,
 * een filter op de kop, kolombreedtes en getal-, geld- en datumopmaak. Geen formules: dat
 * houdt het bestand voorspelbaar en sluit "formula injection" uit (tekst is altijd tekst).
 *
 * Het zip-omhulsel schrijven we zelf (zie xlsx_zip()), zodat de export niet afhangt van de
 * PHP-extensie zip die niet overal aanstaat.
 *
 * Gebruik:
 *   $boek = [
 *     ['naam' => 'Per persoon', 'kop' => ['Naam', 'Bruto'], 'breedte' => [28, 12],
 *      'rijen' => [['Sanne', ['v' => 812.5, 's' => 'geld']]], 'boven' => 'CONCEPT'],
 *   ];
 *   xlsx_download($boek, 'loonplanning.xlsx');
 *
 * Een cel is een waarde (int/float = getal, string = tekst, null = leeg) of ['v' => waarde,
 * 's' => stijl]. Stijlen: 'geld' (€ 0,00), 'getal', 'datum' (Y-m-d in, dd-mm-jjjj uit),
 * 'vet', 'let' (oranje vlak, voor iets dat opvalt), 'zacht' (grijs, schuin).
 */

if (!function_exists('xlsx_maak')) {

/** De stijlnummers in styles.xml (cellXfs), op volgorde. */
function xlsx_stijlen(): array {
    return ['standaard' => 0, 'kop' => 1, 'geld' => 2, 'getal' => 3, 'datum' => 4, 'vet' => 5, 'let' => 6, 'zacht' => 7];
}

/** Het hele werkboek als binaire string. */
function xlsx_maak(array $bladen): string {
    $bestanden = [];
    $bestanden['[Content_Types].xml'] = xlsx_content_types(count($bladen));
    $bestanden['_rels/.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';
    $sheets = ''; $rels = '';
    foreach (array_values($bladen) as $i => $blad) {
        $n = $i + 1;
        $naam = xlsx_bladnaam((string)($blad['naam'] ?? "Blad $n"));
        $sheets .= '<sheet name="' . xlsx_esc($naam) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
        $rels   .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
        $bestanden['xl/worksheets/sheet' . $n . '.xml'] = xlsx_blad($blad);
    }
    $rels .= '<Relationship Id="rId' . (count($bladen) + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
    $bestanden['xl/workbook.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets>' . $sheets . '</sheets></workbook>';
    $bestanden['xl/_rels/workbook.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>';
    $bestanden['xl/styles.xml'] = xlsx_styles();
    return xlsx_zip($bestanden);
}

/** Stuur het werkboek als download en stop. */
function xlsx_download(array $bladen, string $bestandsnaam): void {
    $data = xlsx_maak($bladen);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]+/', '_', $bestandsnaam) . '"');
    header('Content-Length: ' . strlen($data));
    header('Cache-Control: no-store');   // persoonsgegevens: niet in een tussencache
    echo $data;
    exit;
}

/** Excel staat maximaal 31 tekens toe en een paar tekens niet. */
function xlsx_bladnaam(string $naam): string {
    $naam = str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $naam);
    return mb_substr(trim($naam) ?: 'Blad', 0, 31);
}

function xlsx_esc(string $s): string {
    // Stuurtekens (behalve tab/regeleinde) zijn ongeldig in XML en maken het bestand onleesbaar.
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s) ?? '';
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/** Kolomletter: 0 → A, 26 → AA. */
function xlsx_kolom(int $i): string {
    $s = '';
    for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s;
    return $s;
}

/** Eén cel als XML. */
function xlsx_cel(string $ref, $cel): string {
    $stijl = 0;
    if (is_array($cel)) { $stijl = xlsx_stijlen()[$cel['s'] ?? 'standaard'] ?? 0; $cel = $cel['v'] ?? null; }
    $s = $stijl ? ' s="' . $stijl . '"' : '';
    if ($cel === null || $cel === '') return $stijl ? '<c r="' . $ref . '"' . $s . '/>' : '';
    if ($stijl === xlsx_stijlen()['datum'] && is_string($cel)) {
        $ts = strtotime($cel . ' 00:00:00 UTC');
        if ($ts !== false) return '<c r="' . $ref . '"' . $s . '><v>' . (intdiv($ts, 86400) + 25569) . '</v></c>';
    }
    if (is_int($cel) || is_float($cel)) return '<c r="' . $ref . '"' . $s . '><v>' . (is_float($cel) ? round($cel, 4) : $cel) . '</v></c>';
    if (is_bool($cel)) $cel = $cel ? 'ja' : 'nee';
    return '<c r="' . $ref . '"' . $s . ' t="inlineStr"><is><t xml:space="preserve">' . xlsx_esc((string)$cel) . '</t></is></c>';
}

/** Eén werkblad: optionele regel erboven, kopregel (vast + filter), rijen. */
function xlsx_blad(array $blad): string {
    $kop   = $blad['kop'] ?? [];
    $rijen = $blad['rijen'] ?? [];
    $boven = $blad['boven'] ?? null;          // bijv. "CONCEPT — nog niet definitief"
    $xml = ''; $r = 0;
    if ($boven !== null) {
        $r++;
        $xml .= '<row r="' . $r . '">' . xlsx_cel('A' . $r, ['v' => $boven, 's' => 'let']) . '</row>';
    }
    $kop_rij = 0;
    if ($kop) {
        $r++; $kop_rij = $r;
        $xml .= '<row r="' . $r . '">';
        foreach (array_values($kop) as $k => $t) $xml .= xlsx_cel(xlsx_kolom($k) . $r, ['v' => $t, 's' => 'kop']);
        $xml .= '</row>';
    }
    foreach ($rijen as $rij) {
        $r++;
        $xml .= '<row r="' . $r . '">';
        foreach (array_values($rij) as $k => $cel) $xml .= xlsx_cel(xlsx_kolom($k) . $r, $cel);
        $xml .= '</row>';
    }
    $cols = '';
    foreach (array_values($blad['breedte'] ?? []) as $k => $b) {
        $cols .= '<col min="' . ($k + 1) . '" max="' . ($k + 1) . '" width="' . (float)$b . '" customWidth="1"/>';
    }
    $views = '';
    if ($kop_rij) {
        // Kopregel (en wat erboven staat) blijft staan tijdens scrollen; eerste kolom ook, bij brede bladen.
        $x = !empty($blad['vaste_kolom']) ? ' xSplit="1"' : '';
        $cel = (!empty($blad['vaste_kolom']) ? 'B' : 'A') . ($kop_rij + 1);
        $views = '<sheetViews><sheetView workbookViewId="0"><pane' . $x . ' ySplit="' . $kop_rij . '" topLeftCell="' . $cel . '" activePane="bottomRight" state="frozen"/></sheetView></sheetViews>';
    }
    $filter = ($kop_rij && $rijen) ? '<autoFilter ref="A' . $kop_rij . ':' . xlsx_kolom(max(0, count($kop) - 1)) . $r . '"/>' : '';
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . $views . ($cols ? '<cols>' . $cols . '</cols>' : '') . '<sheetData>' . $xml . '</sheetData>' . $filter . '</worksheet>';
}

function xlsx_content_types(int $aantal): string {
    $bladen = '';
    for ($i = 1; $i <= $aantal; $i++) {
        $bladen .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    }
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . $bladen . '</Types>';
}

/** Huisstijl in de kop (navy met wit), geld in euro's, datum dd-mm-jjjj. Volgorde = xlsx_stijlen(). */
function xlsx_styles(): string {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="2"><numFmt numFmtId="164" formatCode="&quot;€&quot;\ #,##0.00"/><numFmt numFmtId="165" formatCode="dd\-mm\-yyyy"/></numFmts>'
        . '<fonts count="4">'
        .   '<font><sz val="11"/><name val="Calibri"/></font>'
        .   '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
        .   '<font><b/><sz val="11"/><name val="Calibri"/></font>'
        .   '<font><i/><sz val="11"/><color rgb="FF6B7280"/><name val="Calibri"/></font>'
        . '</fonts>'
        . '<fills count="4">'
        .   '<fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
        .   '<fill><patternFill patternType="solid"><fgColor rgb="FF054D8A"/></patternFill></fill>'
        .   '<fill><patternFill patternType="solid"><fgColor rgb="FFFCE7DC"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="8">'
        .   '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'                                  // standaard
        .   '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'       // kop
        .   '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'           // geld
        .   '<xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'             // getal
        .   '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'           // datum
        .   '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                     // vet
        .   '<xf numFmtId="0" fontId="2" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'       // let
        .   '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                     // zacht
        . '</cellXfs></styleSheet>';
}

/**
 * Een zip-bestand (PKZIP 2.0) van [pad => inhoud]. Deflate als zlib er is, anders opgeslagen.
 * Klein en zonder ZipArchive: dat is een aparte PHP-extensie die niet op elke server aanstaat.
 */
function xlsx_zip(array $bestanden): string {
    $data = ''; $centraal = ''; $n = 0;
    $tijd = 0; $datum = (2026 - 1980) << 9 | 1 << 5 | 1;        // vaste datum: exports zijn reproduceerbaar
    foreach ($bestanden as $pad => $inhoud) {
        $crc = crc32($inhoud);
        $los = strlen($inhoud);
        $ingepakt = function_exists('gzdeflate') ? gzdeflate($inhoud, 6) : false;
        $methode = $ingepakt !== false ? 8 : 0;
        if ($ingepakt === false) $ingepakt = $inhoud;
        $kop = pack('vvvvvVVVvv', 20, 0x0800, $methode, $tijd, $datum, $crc, strlen($ingepakt), $los, strlen($pad), 0);
        $offset = strlen($data);
        $data .= "PK\x03\x04" . $kop . $pad . $ingepakt;
        $centraal .= "PK\x01\x02" . pack('vvvvvvVVVvvvvvVV', 20, 20, 0x0800, $methode, $tijd, $datum, $crc, strlen($ingepakt), $los,
                                            strlen($pad), 0, 0, 0, 0, 0, $offset) . $pad;
        $n++;
    }
    return $data . $centraal . "PK\x05\x06" . pack('vvvvVVv', 0, 0, $n, $n, strlen($centraal), strlen($data), 0);
}

}
