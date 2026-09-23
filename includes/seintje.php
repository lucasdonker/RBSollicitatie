<?php
/**
 * Paal 13 — "Stuur me een seintje": toestemming om bij een volgende werving benaderd te worden.
 *
 * Waarom dit bestaat: een afgewezen sollicitant mogen we niet zomaar een wervingsbericht
 * sturen. De gegevens zijn verzameld voor die ene sollicitatie (AVG art. 5 lid 1b), en de
 * Telecommunicatiewet (art. 11.7) eist vooraf toestemming voor ongevraagde berichten, ook voor
 * ideële doelen. Die toestemming moet vrij en los gegeven worden — een zin in de voorwaarden
 * telt niet (AVG art. 7). Daarom vragen we het op het moment dat het logisch is: bij de
 * afwijzing, met één knop in de mail en in het portaal.
 *
 * Oud-strandwachten (ooit aangenomen) hebben dit niet nodig: daar is het gerechtvaardigd
 * belang, met de gewone afmeldlink. Zie seintje_doelgroep() in includes/beheer/berichten.php.
 *
 * Twee regels:
 *   1. Toestemming geven gebeurt nooit op een GET. Mailscanners openen links vooraf; een
 *      link die meteen "ja" zegt, geeft toestemming namens een scanner. Er zit één knop tussen.
 *   2. Intrekken is net zo makkelijk als geven (AVG art. 7 lid 3): de afmeldlink trekt het
 *      seintje ook in, en in het portaal staat de knop "toch niet".
 *
 * Opslag: personen.seintje_op (moment van toestemming, NULL = geen) en seintje_bron (waar).
 * Het token is het bestaande afmeld-token (optout_token) — één geheim per persoon.
 */

if (!function_exists('seintje_auto_setup')) {

function seintje_auto_setup(): void {
    static $done = false; if ($done) return; $done = true;
    if (!function_exists('db_kolom_verzeker')) return;
    try {
        $db = db();
        db_kolom_verzeker($db, 'personen', 'seintje_op',   'DATETIME DEFAULT NULL');
        db_kolom_verzeker($db, 'personen', 'seintje_bron', 'VARCHAR(20) DEFAULT NULL');
    } catch (\Throwable $e) { error_log('seintje_auto_setup: ' . $e->getMessage()); }
}

/**
 * Hoe lang een seintje geldt, in maanden. Zolang mogen we de gegevens bewaren en de persoon
 * benaderen; daarna vervalt de toestemming (en ruimt opschonen de gegevens op).
 */
function seintje_termijn_mnd(): int {
    $m = function_exists('instelling_int') ? instelling_int('bewaar.seintje_mnd', 12) : 12;
    return $m > 0 ? $m : 12;
}

/** Heeft deze persoon om een seintje gevraagd? */
function seintje_heeft(int $persoon_id): bool {
    seintje_auto_setup();
    try {
        $q = db()->prepare("SELECT seintje_op FROM personen WHERE id=?");
        $q->execute([$persoon_id]);
        return (string)$q->fetchColumn() !== '';
    } catch (\Throwable $e) { return false; }
}

/**
 * Toestemming vastleggen. Wie om een seintje vraagt, wil berichten: een eerdere afmelding
 * vervalt daarmee.
 *
 * @param string $bron  'mail' | 'portaal'
 */
function seintje_geven(int $persoon_id, string $bron): bool {
    seintje_auto_setup();
    if (function_exists('optout_auto_setup')) optout_auto_setup();
    try {
        db()->prepare("UPDATE personen SET seintje_op=NOW(), seintje_bron=?, mailing_afgemeld=0, mailing_afgemeld_op=NULL WHERE id=?")
            ->execute([substr($bron, 0, 20), $persoon_id]);
        if (function_exists('audit_log')) audit_log('seintje_gevraagd', 'persoon', $persoon_id, ['bron' => $bron]);
        return true;
    } catch (\Throwable $e) { error_log('seintje_geven: ' . $e->getMessage()); return false; }
}

/** Toestemming intrekken. Doet niets (en logt niets) als er geen toestemming was. */
function seintje_intrekken(int $persoon_id, string $bron): bool {
    seintje_auto_setup();
    try {
        $q = db()->prepare("UPDATE personen SET seintje_op=NULL, seintje_bron=NULL WHERE id=? AND seintje_op IS NOT NULL");
        $q->execute([$persoon_id]);
        if ($q->rowCount() > 0 && function_exists('audit_log')) {
            audit_log('seintje_ingetrokken', 'persoon', $persoon_id, ['bron' => $bron]);
        }
        return true;
    } catch (\Throwable $e) { error_log('seintje_intrekken: ' . $e->getMessage()); return false; }
}

/** De persoon achter een afmeld-token, of null. */
function seintje_persoon_via_token(string $token): ?array {
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) return null;
    seintje_auto_setup();
    if (function_exists('optout_auto_setup')) optout_auto_setup();
    try {
        $q = db()->prepare("SELECT id, voornaam, seintje_op FROM personen WHERE afmeld_token=?");
        $q->execute([$token]);
        return $q->fetch() ?: null;
    } catch (\Throwable $e) { return null; }
}

/** De link naar de seintje-pagina, of '' als er geen token te maken is. */
function seintje_link(int $persoon_id): string {
    if (!function_exists('optout_token')) return '';
    $token = optout_token($persoon_id);
    return $token !== '' ? APP_URL . '/portaal/seintje.php?t=' . $token : '';
}

/**
 * Het blok dat onder een afwijzingsmail komt. Staat los van het sjabloon, zodat een
 * bewerkte afwijzingstekst de vraag niet per ongeluk kwijtraakt.
 */
function seintje_mailblok(string $url): string {
    if ($url === '') return '';
    return "\n[info]<strong>Volgend jaar weer?</strong><br>Wil je een seintje als de werving weer opent? "
         . "Dan bewaren we je naam en contactgegevens daarvoor maximaal een jaar. Zonder seintje "
         . "verwijderen we je gegevens binnen vier weken.[/info]\n"
         . '[knop href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"]Stuur me een seintje[/knop]';
}

}
