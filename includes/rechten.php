<?php
/**
 * Paal 13 — Rollen- en rechtensysteem (RBAC).
 *
 * Eén bron van waarheid voor "wie mag wat". Rollen staan in de database (toe te
 * voegen/hernoemen), elke rol koppelt aan een Authentik-groep, en per rol/module
 * zijn er drie rechten: lezen, bewerken, verwijderen.
 *
 * Gebruik in pagina's:   vereis_recht('sollicitanten', 'lezen');
 * Gebruik in het menu:    if (mag('sollicitanten', 'lezen')) { ... toon item ... }
 */

if (!function_exists('rechten_modules')) {

/**
 * De modules waarop rechten gelden. Sleutel = interne naam, waarde = weergave +
 * of de drie acties (bewerken/verwijderen) zinvol zijn voor die module.
 * 'acties' bepaalt welke kolommen in de matrix-UI verschijnen.
 */
function rechten_modules(): array {
    return [
        'sollicitanten'   => ['label' => 'Sollicitanten',          'groep' => 'Werving',        'acties' => ['lezen','bewerken','verwijderen']],
        'paginas'         => ['label' => "Wervingspagina's", 'groep' => 'Werving',        'acties' => ['lezen','bewerken','verwijderen']],
        'blk'             => ['label' => 'BLK-formulieren & details','groep' => 'Werving',       'acties' => ['lezen','bewerken']],
        'selectiedag'     => ['label' => 'Selectiedag',            'groep' => 'Selectie',       'acties' => ['lezen','bewerken']],
        'ondertekeningen' => ['label' => 'Ondertekeningen',        'groep' => 'Selectie',       'acties' => ['lezen','bewerken']],
        'documenten'      => ['label' => 'Loondossiers (ID, bankpas, loonheffing)', 'groep' => 'Beheer', 'acties' => ['lezen','bewerken','verwijderen']],
        'beschikbaarheid' => ['label' => 'Beschikbaarheid',        'groep' => 'Planning',       'acties' => ['lezen','bewerken']],
        'dekking'         => ['label' => 'Dekking',                'groep' => 'Planning',       'acties' => ['lezen']],
        'loonplanning'    => ['label' => 'Loonplanning',           'groep' => 'Planning',       'acties' => ['lezen','bewerken']],
        'mailing'         => ['label' => 'Berichten & sjablonen',  'groep' => 'Communicatie',   'acties' => ['lezen','bewerken']],
        // 'verwijderen' bestaat hier apart omdat een seizoen wissen ook alle sollicitaties
        // van dat jaar wist. Standaard heeft alleen de beheerder het; bestuur krijgt 'rw'.
        'seizoen'         => ['label' => 'Seizoenbeheer',          'groep' => 'Beheer',         'acties' => ['lezen','bewerken','verwijderen']],
        'instellingen'    => ['label' => 'Instellingen',           'groep' => 'Beheer',         'acties' => ['lezen','bewerken']],
        'beheerders'      => ['label' => 'Beheerders & rollen',    'groep' => 'Beheer',         'acties' => ['lezen','bewerken']],
        'systeem'         => ['label' => 'Systeem, updates & logs','groep' => 'Beheer',         'acties' => ['lezen','bewerken']],
        // Apart van 'systeem': het logboek toont wie-wat-wanneer over personen (inclusief wie een
        // kopie identiteitsbewijs downloadde). Inzage daarin hoort niet vanzelf bij het recht om
        // migraties te draaien of te deployen.
        'logboek'         => ['label' => 'Logboek (alle logs)',    'groep' => 'Beheer',         'acties' => ['lezen']],
    ];
}

/** De drie mogelijke acties, in vaste volgorde. */
function rechten_acties(): array {
    return ['lezen' => 'Lezen', 'bewerken' => 'Bewerken', 'verwijderen' => 'Verwijderen'];
}

/**
 * Rechten van één module als korte notatie: 'rwd', 'rw', 'r' of 'geen'.
 * Dezelfde letters als in rechten_standaard_rollen(); handig om in het logboek te tonen
 * wat er precies veranderde zonder drie booleans uit te schrijven.
 */
function rechten_bits(array $r): string {
    $bits = (!empty($r['lezen']) ? 'r' : '') . (!empty($r['bewerken']) ? 'w' : '') . (!empty($r['verwijderen']) ? 'd' : '');
    return $bits === '' ? 'geen' : $bits;
}

/**
 * Standaardrollen + hun rechten. Veilige defaults: begeleider mag weinig.
 * Rang bepaalt de hiërarchie (hoger = meer). De 'admin'-rol krijgt altijd alles,
 * ongeacht de matrix (zie mag()).
 *
 * Per rol: [module => [lezen,bewerken,verwijderen]] als bitjes.
 */
function rechten_standaard_rollen(): array {
    // Helper-notatie: 'r'=lezen, 'w'=bewerken, 'd'=verwijderen
    return [
        'begeleider' => [
            'naam' => 'Begeleider',
            'authentik_groep' => 'Sol-Begeleider',
            'rang' => 1,
            'rechten' => [
                // Begeleider: alleen wat nodig is op de selectiedag. GEEN persoonsgegevens.
                //
                // Wat 'lezen' op selectiedag betekent (het scherm bepaalt wat meedraaien is):
                //   - Groepsindeling bekijken (niet wijzigen).
                //   - Inchecken: Aanwezig/Ongedaan tikken (niet de tablet-code aanmaken).
                //   - Beoordelen: eigen cijfers geven/wissen, observaties plaatsen, en de
                //     eígen observaties verwijderen (niet die van anderen).
                //   - Trainingsweekend: afvinken en observeren (niet besluiten of doorvoeren).
                // Nabespreking en bellijst zijn 'bewerken' (bestuur). BLK-bijzonderheden
                // (medisch) ziet een begeleider niet, alleen een neutrale "let op"-vlag; het
                // dossier en telefoonnummers ook niet (daar horen 'blk' en 'sollicitanten' bij).
                'selectiedag'     => 'r',
                'dekking'         => 'r',
            ],
        ],
        'bestuur' => [
            'naam' => 'Bestuur',
            'authentik_groep' => 'Bestuur',
            'rang' => 2,
            'rechten' => [
                'sollicitanten'   => 'rwd',
                'paginas'         => 'rwd',
                'blk'             => 'rw',
                'selectiedag'     => 'rw',
                'ondertekeningen' => 'rw',
                'beschikbaarheid' => 'rw',
                'dekking'         => 'r',
                'loonplanning'    => 'rw',
                'mailing'         => 'rw',
                'seizoen'         => 'rw',
                'instellingen'    => 'rw',
                // GEEN beheerders/systeem — dat blijft admin. GEEN documenten (BSN/ID): penningmeester + admin.
            ],
        ],
        'penningmeester' => [
            'naam' => 'Penningmeester',
            'authentik_groep' => 'Sol-Penningmeester',
            'rang' => 2,
            'rechten' => [
                // De loonadministratie: loondossiers inzien, downloaden, verwerkt markeren, bankpas vernietigen,
                // en de loonplanning (kosten en scenario's) — dat is juist het werkgereedschap van de penningmeester.
                'documenten'      => 'rwd',
                'loonplanning'    => 'rw',
                'sollicitanten'   => 'r',
            ],
        ],
        'admin' => [
            'naam' => 'Admin',
            'authentik_groep' => 'Sol-Admin',
            'rang' => 3,
            'rechten' => 'ALLES', // admin krijgt altijd alles
        ],
    ];
}

/** Auto-setup: maak de tabellen en seed de standaardrollen als ze nog niet bestaan. */
function rechten_auto_setup(): void {
    static $done = false; if ($done) return; $done = true;
    try {
        $db = db();
        $db->exec("CREATE TABLE IF NOT EXISTS `rollen` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `sleutel` VARCHAR(40) NOT NULL UNIQUE,
            `naam` VARCHAR(80) NOT NULL,
            `authentik_groep` VARCHAR(120) DEFAULT NULL,
            `rang` INT NOT NULL DEFAULT 1,
            `systeemrol` TINYINT(1) NOT NULL DEFAULT 0,
            `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS `rol_rechten` (
            `rol_sleutel` VARCHAR(40) NOT NULL,
            `module` VARCHAR(40) NOT NULL,
            `lezen` TINYINT(1) NOT NULL DEFAULT 0,
            `bewerken` TINYINT(1) NOT NULL DEFAULT 0,
            `verwijderen` TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`rol_sleutel`, `module`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Standaardrollen die nog ontbreken (verse database: allemaal; bestaande database: nieuwe rollen zoals
        // 'penningmeester') aanmaken met hun standaardrechten. Bestaande rollen worden nooit overschreven.
        $bestaand = $db->query("SELECT sleutel FROM rollen")->fetchAll(PDO::FETCH_COLUMN);
        foreach (rechten_standaard_rollen() as $sleutel => $rol) {
            if (in_array($sleutel, $bestaand, true)) continue;
            $db->prepare("INSERT INTO rollen (sleutel, naam, authentik_groep, rang, systeemrol) VALUES (?,?,?,?,1)")
               ->execute([$sleutel, $rol['naam'], $rol['authentik_groep'], $rol['rang']]);
            if ($rol['rechten'] === 'ALLES') {
                foreach (rechten_modules() as $mod => $_m) {
                    $db->prepare("INSERT IGNORE INTO rol_rechten (rol_sleutel, module, lezen, bewerken, verwijderen) VALUES (?,?,1,1,1)")
                       ->execute([$sleutel, $mod]);
                }
            } else {
                foreach ($rol['rechten'] as $mod => $bits) {
                    $l = str_contains($bits, 'r') ? 1 : 0;
                    $w = str_contains($bits, 'w') ? 1 : 0;
                    $d = str_contains($bits, 'd') ? 1 : 0;
                    $db->prepare("INSERT IGNORE INTO rol_rechten (rol_sleutel, module, lezen, bewerken, verwijderen) VALUES (?,?,?,?,?)")
                       ->execute([$sleutel, $mod, $l, $w, $d]);
                }
            }
            error_log("rechten_auto_setup: rol '$sleutel' aangemaakt");
        }
        rechten_nieuwe_standaarden();
    } catch (\Exception $e) { error_log('rechten_auto_setup: ' . $e->getMessage()); }
}

/**
 * Standaardrechten die ná de eerste installatie bij een bestaande rol zijn gekomen.
 *
 * Een bestaande rol wordt nooit uit rechten_standaard_rollen() overschreven — een admin kan
 * de matrix hebben aangepast. Maar dan krijgt een bestaande installatie een nieuw standaardrecht
 * ook nooit. Deze lijst lost dat op: elk item wordt precies één keer toegepast (vlag in de
 * instellingen), en alleen als ophoging — een recht wordt hier nooit ingetrokken. Haalt een
 * admin het daarna weer weg, dan blijft het weg.
 *
 * Nieuw item = één regel hieronder: [rol, module, bits, reden].
 */
function rechten_nieuwe_standaarden(): void {
    $db = db();
    $lijst = [
        ['penningmeester', 'loonplanning', 'rw', '2.101.1: loonplanning is het werkgereedschap van de penningmeester'],
    ];
    if (!function_exists('instelling') || !function_exists('instelling_opslaan')) return;
    foreach ($lijst as $item) {
        $vlag = 'rechten.toegevoegd.' . $item[0] . '.' . $item[1];
        if (instelling($vlag, '') !== '') continue;
        $rol = $db->prepare("SELECT COUNT(*) FROM rollen WHERE sleutel = ?");
        $rol->execute([$item[0]]);
        if ((int)$rol->fetchColumn() === 0) continue;       // rol bestaat (nog) niet: dan seedt de setup hem al goed
        $l = str_contains($item[2], 'r') ? 1 : 0;
        $w = str_contains($item[2], 'w') ? 1 : 0;
        $d = str_contains($item[2], 'd') ? 1 : 0;
        // Alleen ophogen: GREATEST houdt een recht dat er al was.
        $db->prepare("INSERT INTO rol_rechten (rol_sleutel, module, lezen, bewerken, verwijderen) VALUES (?,?,?,?,?)
                      ON DUPLICATE KEY UPDATE lezen = GREATEST(lezen, VALUES(lezen)),
                                              bewerken = GREATEST(bewerken, VALUES(bewerken)),
                                              verwijderen = GREATEST(verwijderen, VALUES(verwijderen))")
           ->execute([$item[0], $item[1], $l, $w, $d]);
        instelling_opslaan($vlag, date('Y-m-d'));
        if (function_exists('audit_log')) {
            audit_log('rol_gewijzigd', 'rol', null, ['rol' => $item[0], 'module' => $item[1], 'rechten' => $item[2],
                                                    'reden' => $item[3], 'door' => 'systeem']);
        }
        error_log("rechten_nieuwe_standaarden: {$item[0]} kreeg {$item[1]} ({$item[2]})");
    }
}

/** Alle rollen ophalen (voor de matrix-UI en Authentik-mapping). */
function rollen_alle(): array {
    rechten_auto_setup();
    try {
        return db()->query("SELECT * FROM rollen ORDER BY rang DESC, naam")->fetchAll();
    } catch (\Exception $e) { return []; }
}

/** De rechten van één rol als [module => ['lezen'=>bool,...]]. */
function rol_rechten(string $rol_sleutel): array {
    rechten_auto_setup();
    $uit = [];
    try {
        $q = db()->prepare("SELECT * FROM rol_rechten WHERE rol_sleutel=?");
        $q->execute([$rol_sleutel]);
        foreach ($q->fetchAll() as $r) {
            $uit[$r['module']] = [
                'lezen'       => (bool)$r['lezen'],
                'bewerken'    => (bool)$r['bewerken'],
                'verwijderen' => (bool)$r['verwijderen'],
            ];
        }
    } catch (\Exception $e) {}
    return $uit;
}

/**
 * De kernfunctie: mag de ingelogde beheerder deze actie op deze module?
 * admin-rol mag altijd alles (kan zichzelf nooit buitensluiten).
 */
function mag(string $module, string $actie = 'lezen'): bool {
    rechten_auto_setup();
    $rol = $_SESSION['bhd_rol'] ?? '';
    if ($rol === '') return false;
    if ($rol === 'admin') return true; // admin: altijd volledige toegang

    // Cache per request
    static $cache = [];
    if (!isset($cache[$rol])) $cache[$rol] = rol_rechten($rol);
    $r = $cache[$rol][$module] ?? null;
    if (!$r) return false;
    return !empty($r[$actie]);
}

/**
 * Breekt af met 403 als de beheerder het recht niet heeft. Hoort bovenaan een pagina,
 * vóór de layout: deze functie stopt met die(), en dat kan niet meer nadat er al HTML
 * is uitgestuurd.
 *
 * Een mens krijgt de nette pagina binnen het gewone kader — geen rechten is een keuze,
 * geen storing (ontwerpfilosofie §8). Een API-aanroep krijgt JSON, want daar zou HTML
 * de aanroeper alleen maar in de war brengen.
 */
function vereis_recht(string $module, string $actie = 'lezen'): void {
    if (function_exists('vereist_login')) vereist_login();
    if (mag($module, $actie)) return;

    http_response_code(403);

    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $is_api = str_starts_with($script, 'api_')
           || str_contains(strtolower(implode(' ', headers_list())), 'application/json');

    if ($is_api) {
        header('Content-Type: application/json; charset=utf-8');
        die(json_encode(['ok' => false, 'fout' => 'geen_rechten', 'module' => $module, 'actie' => $actie]));
    }

    $layout = __DIR__ . '/../admin/layout_top.php';
    $partial = __DIR__ . '/../admin/_geen_rechten.php';
    if (is_file($layout) && is_file($partial)) {
        $pagina_titel = 'Geen toegang';
        $actieve_nav  = '';
        require $layout;
        $geen_module = $module;
        $geen_rol    = (string)($_SESSION['bhd_rol'] ?? '');
        require $partial;
        require __DIR__ . '/../admin/layout_bottom.php';
        exit;
    }

    // Terugval als de layout er niet is (losse scripts, tests).
    $terug = defined('APP_URL') ? APP_URL . '/admin/' : '/admin/';
    die('<div style="font-family:sans-serif;max-width:520px;margin:80px auto;text-align:center">'
      . '<h2>Geen toegang</h2><p>Je rol heeft hier geen inzage. Dat is een bewuste keuze, geen storing.</p>'
      . '<p><a href="' . htmlspecialchars($terug) . '">Terug naar Vandaag</a></p></div>');
}

/** Zet Authentik-groepen om naar een rol-sleutel op basis van de rollen-tabel. */
function rol_uit_authentik_groepen(array $groups): ?string {
    rechten_auto_setup();
    try {
        // Hoogste rang wint als iemand in meerdere groepen zit
        $rollen = db()->query("SELECT sleutel, authentik_groep, rang FROM rollen WHERE authentik_groep IS NOT NULL AND authentik_groep<>'' ORDER BY rang DESC")->fetchAll();
        foreach ($rollen as $rol) {
            foreach ($groups as $g) {
                if (strcasecmp(trim($rol['authentik_groep']), trim($g)) === 0) {
                    return $rol['sleutel'];
                }
            }
        }
    } catch (\Exception $e) {}
    return null;
}

}
