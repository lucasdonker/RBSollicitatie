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

/**
 * Telefoonnummer leesbaar maken: "0612345678" → "06 12 34 56 78". Alleen voor Nederlandse 10-cijferige
 * nummers die met 0 beginnen; andere notaties (bijv. +31…) komen ongewijzigd terug, alleen opgeschoond.
 */
function telefoon_opmaak(string $tel): string {
    $t = trim($tel);
    $cijfers = preg_replace('/\D+/', '', $t);
    if (strlen($cijfers) === 10 && str_starts_with($cijfers, '0') && !str_contains($t, '+')) {
        return implode(' ', str_split($cijfers, 2));
    }
    return $t;
}

/**
 * Is dit een bruikbaar telefoonnummer voor sms/WhatsApp? Nederlands: 10 cijfers met een 0 vooraan
 * (06…, 020…), of +31/0031 gevolgd door 9 cijfers. Buitenlands (+xx): 8 tot 15 cijfers.
 * Spaties, streepjes en haakjes mogen; letters niet.
 */
function telefoon_geldig(string $tel): bool {
    $t = trim($tel);
    if ($t === '' || preg_match('/[^0-9+\s\-().]/', $t)) return false;
    $c = preg_replace('/\D+/', '', $t);
    if (str_starts_with($t, '+31') || str_starts_with($c, '0031')) return strlen(preg_replace('/^(0031|31)/', '', $c)) === 9;
    if (str_starts_with($t, '+') || str_starts_with($c, '00'))    return strlen(ltrim($c, '0')) >= 8 && strlen(ltrim($c, '0')) <= 15;
    return strlen($c) === 10 && $c[0] === '0';
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
