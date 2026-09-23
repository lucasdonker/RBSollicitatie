<?php
/**
 * Paal 13 — Werk ná het antwoord.
 *
 * Eén plek voor "de bezoeker heeft zijn pagina, doe nu de rest": mails en WhatsApp
 * versturen, het auditlog wegschrijven, de lazy-cron, de koppeling met Authentik.
 *
 * Waarom dit een eigen bestand is: die plekken sloten elk zelf de verbinding met
 * fastcgi_finish_request(), maar lieten de PHP-sessie open. De sessie blijft dan
 * vergrendeld tot het script écht klaar is. Na een formulier volgt een redirect, en die
 * volgende pagina bleef in session_start() hangen tot de mails de deur uit waren — het
 * antwoord was er dus snel, maar de pagina die je daarna zag niet. Dat was het "trage
 * formulier". antwoord_afsluiten() geeft daarom eerst de sessie vrij.
 *
 * Na het afsluiten kun je $_SESSION nog lezen, maar wat je erin schrijft wordt niet meer
 * bewaard. Werk ná het antwoord mag dus nooit iets in de sessie willen zetten.
 */

if (!function_exists('antwoord_afsluiten')) {

/** Sessie vrijgeven en het antwoord naar de bezoeker sturen. Veilig om vaker aan te roepen. */
function antwoord_afsluiten(): void {
    static $gedaan = false;
    if ($gedaan) return;
    $gedaan = true;
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    if (function_exists('fastcgi_finish_request'))      @fastcgi_finish_request();
    elseif (function_exists('litespeed_finish_request')) @litespeed_finish_request();
}

/** Kan dit verzoek echt antwoorden vóór het werk klaar is? Niet onder CLI (harnas, cron). */
function na_antwoord_mogelijk(): bool {
    return PHP_SAPI !== 'cli' && function_exists('fastcgi_finish_request');
}

/**
 * Voer $werk uit nadat de bezoeker zijn antwoord heeft. Een fout erin breekt niets meer
 * af — de pagina is al weg — en komt in het errorlog.
 */
function na_antwoord(callable $werk, string $naam = 'na_antwoord'): void {
    register_shutdown_function(function () use ($werk, $naam) {
        antwoord_afsluiten();
        try { $werk(); }
        catch (\Throwable $e) { error_log($naam . ' (na antwoord): ' . $e->getMessage()); }
    });
}

}
