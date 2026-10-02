<?php
/**
 * Paal 13 — Alle periodieke taken, in drie groepen.
 *
 * Alleen de echte cron draait dit (cron/run.php). Er is bewust géén "lazy cron" meer die op
 * een paginabezoek meelift: het systeem mag niet afhangen van wie toevallig het beheer opent.
 *
 * Elke groep heeft een eigen interval, lock en laatste-run. Zo kan één geplande taak per minuut
 * volstaan (run.php zonder groep draait wat aan de beurt is), maar je mag ook per groep een
 * eigen taak maken. Een langzame dagtaak (Authentik, opschonen) houdt dan de berichten niet op.
 */

if (!function_exists('cron_taken_uitvoeren')) {

/**
 * De groepen: interval in seconden (dag = één keer per kalenderdag) en de taken, in volgorde.
 * Een taak is een naam => callable die een array (resultaat) teruggeeft.
 */
function cron_groepen(): array {
    // Taken die uit zichzelf berichten maken, wachten 's ochtends tot het ingestelde uur (melding_automatisch_nu()).
    $overdag = fn(callable $taak) => function_exists('melding_automatisch_nu') && !melding_automatisch_nu()
        ? ['wacht_tot' => sprintf('%02d:00', melding_automatisch_vanaf())]
        : $taak();
    return [
        'snel' => [
            'label'    => 'Berichten en AI (elke minuut)',
            'interval' => 60,
            'taken'    => [
                'berichten' => fn() => function_exists('melding_queue_verwerk') ? melding_queue_verwerk(20) : null,
                'ai_queue'  => fn() => function_exists('ai_queue_verwerk') ? ai_queue_verwerk(3) : null,
            ],
        ],
        'kwartier' => [
            'label'    => 'Herinneringen, uitnodigen en opruimen (elk kwartier)',
            'interval' => 900,
            'taken'    => [
                'herinneringen' => fn() => $overdag(fn() => function_exists('herinnering_run')
                    ? array_map(fn($r) => ['kandidaten' => $r['kandidaten'], 'verstuurd' => $r['verstuurd'], 'overgeslagen' => $r['overgeslagen']], herinnering_run())
                    : null),
                'auto_invite'   => fn() => $overdag(fn() => function_exists('auto_invite_run') ? auto_invite_run() : null),
                // Gebruikte/verlopen inlogcodes en tellers van de snelheidsbegrenzing; los van bewaar.actief.
                'vervallen'     => fn() => function_exists('opschoon_vervallen') ? opschoon_vervallen() : null,
                // Verlopen "blijf ingelogd"-tokens: werken toch niet meer, hoeven er niet te staan.
                'apparaten'     => fn() => function_exists('portaal_onthoud_opschonen') ? ['verwijderd' => portaal_onthoud_opschonen()] : null,
                // Tellers van live bijwerken die een week stillagen (bevatten de naam van wie iets wijzigde).
                'live'          => fn() => function_exists('live_opruimen') ? ['verwijderd' => live_opruimen()] : null,
            ],
        ],
        'dag' => [
            'label'    => 'Authentik en AVG-opschoning (dagelijks)',
            'interval' => 'dag',
            'taken'    => [
                'authentik_sync' => fn() => function_exists('competenties_cron_sync') ? competenties_cron_sync() : null,
                // Fotobestanden van dossiers die al weg zijn: opruimen, geen bewaartermijn.
                'fotos'          => fn() => function_exists('foto_wezen_opruimen') ? ['verwijderd' => foto_wezen_opruimen()] : null,
                'opschonen'      => fn() => function_exists('opschoon_run') ? opschoon_run(false, 'cron') : null,
            ],
        ],
    ];
}

/** Is deze groep aan de beurt (sinds de laatste geslaagde run)? */
function cron_groep_aan_de_beurt(string $groep): bool {
    $def = cron_groepen()[$groep] ?? null;
    if (!$def) return false;
    $laatste = instelling('cron.laatste_' . $groep, '');
    if ($laatste === '') return true;
    if ($def['interval'] === 'dag') return substr($laatste, 0, 10) !== date('Y-m-d');
    // 10 seconden speling: een minuutjob die net iets eerder start, slaat anders elke tweede keer over.
    return strtotime($laatste) <= time() - (int)$def['interval'] + 10;
}

/**
 * Voer een groep uit (of 'auto': alle groepen die aan de beurt zijn; 'alles': alle groepen).
 * Retourneert per groep de uitkomst van elke taak.
 */
function cron_taken_uitvoeren(string $groep = 'auto'): array {
    $uit = ['ok' => true, 'groepen' => []];
    if (!function_exists('instelling') || !function_exists('instelling_opslaan')) { $uit['ok'] = false; return $uit; }

    $alle = cron_groepen();
    if ($groep === 'auto')      $te_doen = array_values(array_filter(array_keys($alle), 'cron_groep_aan_de_beurt'));
    elseif ($groep === 'alles') $te_doen = array_keys($alle);
    elseif (isset($alle[$groep])) $te_doen = [$groep];
    else { $uit['ok'] = false; $uit['fout'] = 'Onbekende groep: ' . $groep; return $uit; }

    foreach ($te_doen as $g) $uit['groepen'][$g] = cron_groep_draaien($g, $alle[$g]);
    return $uit;
}

/** Eén groep: lock, taken, laatste run. Een fout in één taak stopt de andere niet. */
function cron_groep_draaien(string $groep, array $def): array {
    // Lock per groep: ruim langer dan een run duurt, zodat een vastgelopen run na een tijd vrijkomt.
    $lock_sleutel = 'cron.lock_' . $groep;
    $lock = instelling($lock_sleutel, '');
    if ($lock !== '' && strtotime($lock) > time()) return ['overgeslagen' => 'andere run bezig tot ' . $lock];
    instelling_opslaan($lock_sleutel, date('Y-m-d H:i:s', time() + ($groep === 'dag' ? 1800 : 300)));

    $taken = [];
    try {
        foreach ($def['taken'] as $naam => $fn) {
            $t0 = microtime(true);
            try { $taken[$naam] = $fn(); }
            catch (\Throwable $e) { $taken[$naam] = ['fout' => $e->getMessage()]; error_log("cron $groep/$naam: " . $e->getMessage()); }
            if (is_array($taken[$naam])) $taken[$naam]['ms'] = (int)round((microtime(true) - $t0) * 1000);
        }
        $nu = date('Y-m-d H:i:s');
        instelling_opslaan('cron.laatste_' . $groep, $nu);
        instelling_opslaan('cron.laatste_run', $nu);
    } finally {
        instelling_opslaan($lock_sleutel, '');
    }
    return $taken;
}

/**
 * Stand per groep voor Systeem, Vandaag en de health-check:
 * groep => ['label', 'laatste', 'status' (ok|laat|stil|nooit)].
 * Laat = langer dan 2x het interval geleden; stil = langer dan 6x (dag: 2 dagen).
 */
function cron_stand(): array {
    $uit = [];
    foreach (cron_groepen() as $g => $def) {
        $laatste = function_exists('instelling') ? instelling('cron.laatste_' . $g, '') : '';
        if ($laatste === '') { $uit[$g] = ['label' => $def['label'], 'laatste' => null, 'status' => 'nooit']; continue; }
        $sec = $def['interval'] === 'dag' ? 86400 : (int)$def['interval'];
        $leeftijd = time() - strtotime($laatste);
        // Minimaal een kwartier speling: een minuutgroep die één keer overslaat is geen alarm.
        $status = $leeftijd > max(6 * $sec, 1800) ? 'stil' : ($leeftijd > max(2 * $sec, 900) ? 'laat' : 'ok');
        $uit[$g] = ['label' => $def['label'], 'laatste' => $laatste, 'status' => $status];
    }
    return $uit;
}

/**
 * Hartslag voor een externe uptime-monitor (api/status.php): liep de taakronde de laatste
 * $max_sec seconden? De minuutgroep zet cron.laatste_run elke minuut, dus 10 minuten is ruim.
 * @return array{laatst:?string, seconden:?int, ok:bool}
 */
function cron_hartslag(int $max_sec = 600): array {
    $laatst = function_exists('instelling') ? instelling('cron.laatste_run', '') : '';
    if ($laatst === '' || strtotime($laatst) === false) return ['laatst' => null, 'seconden' => null, 'ok' => false];
    $sec = max(0, time() - strtotime($laatst));
    return ['laatst' => date('c', strtotime($laatst)), 'seconden' => $sec, 'ok' => $sec <= $max_sec];
}

}
