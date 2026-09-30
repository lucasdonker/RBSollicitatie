<?php
/**
 * Paal 13 — Taakrechten (2.141.0). GEEN HTML.
 *
 * Elke module heeft benoemde taken ("Beoordelen", "Nabespreken", "Versturen"), met een label, een
 * uitleg en een basis: het oude recht (lezen, bewerken of verwijderen) waar de taak uit voortkomt.
 * Een module zonder eigen taken krijgt Bekijken / Wijzigen / Verwijderen (rechten_taken()).
 *
 * Opslag, bewust zonder omzetting:
 *   - rol_rechten (lezen/bewerken/verwijderen) blijft staan en blijft gelden voor alle bestaande
 *     checks: mag('sollicitanten', 'bewerken') werkt zoals altijd.
 *   - rol_taak (rol, module, taak, toegestaan) wordt pas gevuld als iemand de matrix opslaat.
 *     Staat er voor een taak niets, dan geldt de basis: een taak met basis 'lezen' mag wie lezen
 *     heeft. Dus zonder opslaan heeft iedereen exact wat hij had.
 *   - Bij opslaan worden de oude bits uit de taken afgeleid (rechten_bits_uit_taken()):
 *     lezen = minstens één taak aan (elke taak houdt bekijken in);
 *     bewerken / verwijderen = ALLE taken met die basis aan. Streng met opzet: een scherm dat nog
 *     op 'bewerken' controleert, gaat dicht zodra één bewerk-taak uit staat, nooit open omdat er
 *     één aan staat. Wie een taak los wil geven, zet dat scherm om naar mag($module, '<taak>').
 *
 * mag($module, $actie) kent beide: lezen/bewerken/verwijderen = het oude recht, elke andere
 * naam = een taak van die module (onbekende taak = nee, met een regel in de foutlog).
 */

if (!function_exists('rechten_taken')) {

/** De drie oude rechten; ook de toegestane waarden van 'basis'. Een taak mag zo niet heten. */
function rechten_basis_acties(): array {
    return ['lezen', 'bewerken', 'verwijderen'];
}

/**
 * De taken van een module: [taak => ['label', 'uitleg', 'basis']], in weergavevolgorde.
 * Uit rechten_modules()[$module]['taken'], anders afgeleid van de acties van de module.
 */
function rechten_taken(string $module): array {
    static $cache = [];
    if (isset($cache[$module])) return $cache[$module];
    $def = rechten_modules()[$module] ?? null;
    if (!$def) return $cache[$module] = [];
    if (!empty($def['taken'])) return $cache[$module] = $def['taken'];
    $auto = [
        'lezen'       => ['bekijken',  'Bekijken',    'Het onderdeel openen en lezen.'],
        'bewerken'    => ['wijzigen',  'Wijzigen',    'Alles in dit onderdeel aanpassen.'],
        'verwijderen' => ['wissen',    'Verwijderen', 'Gegevens in dit onderdeel definitief verwijderen.'],
    ];
    $uit = [];
    foreach ($def['acties'] as $a) {
        if (isset($auto[$a])) $uit[$auto[$a][0]] = ['label' => $auto[$a][1], 'uitleg' => $auto[$a][2], 'basis' => $a];
    }
    return $cache[$module] = $uit;
}

/** De expliciet opgeslagen taken van één rol: [module][taak] => bool. Leeg zolang de matrix niet is opgeslagen. */
function rol_taken(string $rol): array {
    $uit = [];
    try {
        $q = db()->prepare("SELECT module, taak, toegestaan FROM rol_taak WHERE rol_sleutel = ?");
        $q->execute([$rol]);
        foreach ($q->fetchAll() as $r) $uit[$r['module']][$r['taak']] = (bool)$r['toegestaan'];
    } catch (\Exception $e) {}
    return $uit;
}

/**
 * Mag deze rol deze taak? Opgeslagen waarde, anders de basis uit de oude rechten.
 * @param array $bits  rol_rechten($rol)
 * @param array $taken rol_taken($rol)
 */
function rol_taak_mag(array $bits, array $taken, string $module, string $taak): bool {
    $def = rechten_taken($module)[$taak] ?? null;
    if (!$def) return false;
    if (isset($taken[$module][$taak])) return $taken[$module][$taak];
    return !empty($bits[$module][$def['basis']]);
}

/** Alle taken van een rol zoals ze nu gelden: [module][taak] => bool (voor de matrix en de test). */
function rol_taken_effectief(string $rol): array {
    $bits = rol_rechten($rol); $taken = rol_taken($rol);
    $uit = [];
    foreach (rechten_modules() as $mod => $_m) {
        foreach (rechten_taken($mod) as $taak => $_t) $uit[$mod][$taak] = rol_taak_mag($bits, $taken, $mod, $taak);
    }
    return $uit;
}

/** De oude rechten afgeleid uit de taken van één module (zie de kop van dit bestand). */
function rechten_bits_uit_taken(string $module, array $aan): array {
    $bits = ['lezen' => false, 'bewerken' => false, 'verwijderen' => false];
    $per_basis = [];
    foreach (rechten_taken($module) as $taak => $def) $per_basis[$def['basis']][] = !empty($aan[$taak]);
    foreach ($per_basis as $b) if (in_array(true, $b, true)) $bits['lezen'] = true;
    foreach (['bewerken', 'verwijderen'] as $a) {
        $bits[$a] = !empty($per_basis[$a]) && !in_array(false, $per_basis[$a], true);
    }
    return $bits;
}

/**
 * De taken van één rol opslaan (hele matrix van die rol) en de oude rechten eruit afleiden.
 * @param array $aan [module][taak] => truthy (wat niet genoemd is, staat uit)
 * @return array verschil per module ['oud' => [taak, …], 'nieuw' => [taak, …]] voor het logboek
 */
function rol_taken_opslaan(string $rol, array $aan): array {
    if ($rol === 'admin') return [];   // admin houdt altijd alles
    $voor = rol_taken_effectief($rol);
    $db = db();
    $q_taak = $db->prepare("INSERT INTO rol_taak (rol_sleutel, module, taak, toegestaan) VALUES (?,?,?,?)
                            ON DUPLICATE KEY UPDATE toegestaan = VALUES(toegestaan)");
    $q_bits = $db->prepare("INSERT INTO rol_rechten (rol_sleutel, module, lezen, bewerken, verwijderen) VALUES (?,?,?,?,?)
                            ON DUPLICATE KEY UPDATE lezen = VALUES(lezen), bewerken = VALUES(bewerken), verwijderen = VALUES(verwijderen)");
    $verschil = [];
    foreach (rechten_modules() as $mod => $_m) {
        $nu = [];
        foreach (rechten_taken($mod) as $taak => $_t) {
            $nu[$taak] = !empty($aan[$mod][$taak]);
            $q_taak->execute([$rol, $mod, $taak, $nu[$taak] ? 1 : 0]);
        }
        $b = rechten_bits_uit_taken($mod, $nu);
        $q_bits->execute([$rol, $mod, (int)$b['lezen'], (int)$b['bewerken'], (int)$b['verwijderen']]);
        $oud = array_keys(array_filter($voor[$mod] ?? []));
        $new = array_keys(array_filter($nu));
        if ($oud !== $new) $verschil[$mod] = ['oud' => $oud ?: ['geen'], 'nieuw' => $new ?: ['geen']];
    }
    return $verschil;
}

/**
 * Een oud recht is opgehoogd (rechten_nieuwe_standaarden): zet de taken met die basis ook aan,
 * als de rol al opgeslagen taken voor die module heeft. Anders kreeg het nieuwe standaardrecht
 * nooit effect op schermen die op een taak controleren.
 */
function rol_taken_ophogen(string $rol, string $module, array $acties): void {
    $taken = rol_taken($rol)[$module] ?? [];
    if (!$taken) return;
    $q = db()->prepare("UPDATE rol_taak SET toegestaan = 1 WHERE rol_sleutel = ? AND module = ? AND taak = ?");
    foreach (rechten_taken($module) as $taak => $def) {
        if (in_array($def['basis'], $acties, true)) $q->execute([$rol, $module, $taak]);
    }
}

} // function_exists
