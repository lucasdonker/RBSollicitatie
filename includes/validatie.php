<?php
/**
 * Paal 13 — Validatiehelpers voor Nederlandse gegevens (één plek, geen HTML).
 */

if (!function_exists('bsn_geldig')) {

/** BSN: precies 9 cijfers en de 11-proef (gewichten 9..2, laatste cijfer -1). */
function bsn_geldig(string $bsn): bool {
    $b = preg_replace('/\D+/', '', $bsn);
    if (strlen($b) !== 9 || $b === '000000000') return false;
    $som = 0;
    for ($i = 0; $i < 8; $i++) $som += (int)$b[$i] * (9 - $i);
    $som -= (int)$b[8];
    return $som % 11 === 0;
}

/** BSN opgeschoond (alleen cijfers). */
function bsn_schoon(string $bsn): string { return preg_replace('/\D+/', '', $bsn); }

/** IBAN: lengte per land (NL 18), mod-97 = 1. */
function iban_geldig(string $iban): bool {
    $i = strtoupper(preg_replace('/\s+/', '', $iban));
    if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $i)) return false;
    if (str_starts_with($i, 'NL') && strlen($i) !== 18) return false;
    $herschikt = substr($i, 4) . substr($i, 0, 4);
    $num = '';
    foreach (str_split($herschikt) as $c) $num .= ctype_alpha($c) ? (string)(ord($c) - 55) : $c;
    // mod 97 in stukken (getal is te groot voor int)
    $rest = 0;
    foreach (str_split($num, 7) as $deel) $rest = (int)(($rest . $deel) % 97);
    return $rest === 1;
}

/** IBAN netjes in groepen van 4: "NL91 ABNA 0417 1643 00". */
function iban_opmaak(string $iban): string {
    return trim(chunk_split(strtoupper(preg_replace('/\s+/', '', $iban)), 4, ' '));
}

/** Voorletters uit de voornamen: "Lieke Anne-Marie" → "L.A.M.". */
function naam_voorletters(string $voornamen): string {
    $uit = '';
    foreach (preg_split('/[\s\-]+/u', trim($voornamen)) ?: [] as $deel) {
        if ($deel === '') continue;
        $l = function_exists('mb_substr') ? mb_strtoupper(mb_substr($deel, 0, 1, 'UTF-8'), 'UTF-8') : strtoupper(substr($deel, 0, 1));
        $uit .= $l . '.';
    }
    return $uit;
}

/** Nederlandse postcode "1234 AB" (of false). */
function postcode_opmaak(string $pc): string|false {
    $p = strtoupper(preg_replace('/\s+/', '', $pc));
    return preg_match('/^([1-9][0-9]{3})([A-Z]{2})$/', $p, $m) ? $m[1] . ' ' . $m[2] : false;
}

} // function_exists
