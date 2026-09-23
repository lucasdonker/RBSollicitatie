<?php
/**
 * Paal 13 — Eén ingang voor alle periodieke taken.
 *
 * Aangeroepen door cron/run.php (elke 5 min) én door lazy_cron_tick() (bij admin-bezoek,
 * max 1x per uur). Een lock in de instellingen (cron.lock_tot, 4 minuten) voorkomt dat beide
 * tegelijk draaien en herinneringen dubbel versturen.
 */

if (!function_exists('cron_taken_uitvoeren')) {

/**
 * @param string $bron     'cron' | 'lazy'
 * @param array  $limieten ['queue' => 8, 'ai' => 3]
 */
function cron_taken_uitvoeren(string $bron, array $limieten = []): array {
    $uit = ['ok' => true, 'bron' => $bron, 'taken' => []];
    if (!function_exists('instelling') || !function_exists('instelling_opslaan')) { $uit['ok'] = false; return $uit; }

    // Lock
    $lock = instelling('cron.lock_tot', '');
    if ($lock !== '' && strtotime($lock) > time()) {
        $uit['ok'] = false; $uit['overgeslagen'] = 'andere run bezig tot ' . $lock;
        return $uit;
    }
    instelling_opslaan('cron.lock_tot', date('Y-m-d H:i:s', time() + 240));

    $stap = function (string $naam, callable $fn) use (&$uit) {
        $t0 = microtime(true);
        try { $uit['taken'][$naam] = $fn(); }
        catch (\Throwable $e) { $uit['taken'][$naam] = ['fout' => $e->getMessage()]; error_log("cron $naam: " . $e->getMessage()); }
        if (is_array($uit['taken'][$naam])) $uit['taken'][$naam]['ms'] = (int)round((microtime(true) - $t0) * 1000);
    };

    try {
        if (function_exists('melding_queue_verwerk')) $stap('berichten', fn() => melding_queue_verwerk((int)($limieten['queue'] ?? 8)));
        if (function_exists('ai_queue_verwerk'))      $stap('ai_queue', fn() => ai_queue_verwerk((int)($limieten['ai'] ?? 3)));
        if (function_exists('herinnering_run'))       $stap('herinneringen', fn() => array_map(fn($r) => ['kandidaten' => $r['kandidaten'], 'verstuurd' => $r['verstuurd'], 'overgeslagen' => $r['overgeslagen']], herinnering_run()));
        if (function_exists('auto_invite_run'))       $stap('auto_invite', fn() => auto_invite_run());
        // Zwaardere taken alleen vanuit de echte cron
        if ($bron === 'cron') {
            if (function_exists('competenties_cron_sync')) $stap('authentik_sync', fn() => competenties_cron_sync());
            // Verlopen "blijf ingelogd"-tokens weggooien. Ze werken na de vervaldatum
            // toch niet meer, maar ze hoeven er ook niet te blijven staan.
            if (function_exists('portaal_onthoud_opschonen')) {
                $stap('apparaten', fn() => ['verwijderd' => portaal_onthoud_opschonen()]);
            }
            // Fotobestanden van dossiers die al weg zijn. Geen bewaartermijn maar opruimen van
            // wat al verwijderd hoorde te zijn, dus los van bewaar.actief.
            if (function_exists('foto_wezen_opruimen')) {
                $stap('fotos', fn() => ['verwijderd' => foto_wezen_opruimen()]);
            }
            if (function_exists('opschoon_run')) {
                $laatste = instelling('bewaar.laatste_run', '');
                if (substr($laatste, 0, 10) !== date('Y-m-d')) $stap('opschonen', fn() => opschoon_run(false, 'cron'));
                else $uit['taken']['opschonen'] = ['overgeslagen' => 'al gedraaid vandaag'];
            }
        }
        instelling_opslaan('cron.laatste_run', date('Y-m-d H:i:s'));
        instelling_opslaan('cron.laatste_bron', $bron);
    } finally {
        instelling_opslaan('cron.lock_tot', '');
    }
    return $uit;
}

}
