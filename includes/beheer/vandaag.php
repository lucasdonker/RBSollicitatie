<?php
/**
 * Paal 13 — wat staat er vandaag te doen? GEEN HTML.
 *
 * Het startscherm opent op een vraag, niet op data (ontwerpfilosofie §1): niet "hier
 * zijn 17 rijen" maar "dit moet je nu doen". Er is één hoofdtaak tegelijk; de rest
 * staat eronder als "loopt ondertussen" (§2).
 *
 * Alles hier faalt stil. Een dashboard dat omvalt omdat één telling niet lukt is
 * erger dan een dashboard met een streepje.
 */

if (!function_exists('bh_vandaag_hoofdtaak')) {

/** Tellingen per fase voor dit seizoen. */
function bh_fase_tellingen(int $seizoen_id): array {
    static $cache = [];
    if (isset($cache[$seizoen_id])) return $cache[$seizoen_id];
    $uit = [];
    try {
        $q = db()->prepare("SELECT fase, COUNT(*) n FROM sollicitaties WHERE seizoen_id=? GROUP BY fase");
        $q->execute([$seizoen_id]);
        foreach ($q->fetchAll() as $r) $uit[$r['fase']] = (int)$r['n'];
    } catch (\Throwable $e) {}
    return $cache[$seizoen_id] = $uit;
}

/**
 * De ene taak die nu telt.
 *
 * De seizoensfase bepaalt wélke vraag er speelt; de tellingen bepalen of er nog iets
 * te doen is. Is er niets, dan zegt het scherm dat ook — leeg is vaak goed nieuws.
 *
 * @return array{titel:string,tekst:string,knop:?string,url:?string,gedaan:int,totaal:int,klaar:bool}
 */
function bh_vandaag_hoofdtaak(?array $seizoen, string $seizoen_fase): array {
    $sid = $seizoen ? (int)$seizoen['id'] : 0;
    $t   = $sid ? bh_fase_tellingen($sid) : [];
    $s   = APP_URL . '/admin/sollicitanten.php?fase=';

    // LET OP: gebruik deze helper en niet `$leeg + [...]`. De +-operator op arrays laat
    // de LINKERkant winnen bij dubbele sleutels, dus de titel bleef leeg.
    $niets = fn(string $titel, string $tekst): array => [
        'titel' => $titel, 'tekst' => $tekst, 'knop' => null, 'url' => null,
        'gedaan' => 0, 'totaal' => 0, 'klaar' => true,
    ];

    switch ($seizoen_fase) {
        case 'werving':
            $n = (int)($t['aangemeld'] ?? 0);
            if ($n === 0) {
                return $niets('Nog geen nieuwe aanmeldingen',
                              'Zodra iemand zich via een wervingspagina aanmeldt, staat hij hier.');
            }
            return ['titel' => $n === 1 ? 'Eén aanmelding uitnodigen' : $n . ' aanmeldingen uitnodigen',
                    'tekst'  => 'Deze mensen hebben zich aangemeld en wachten op een uitnodiging voor de selectiedag.',
                    'knop'   => 'Naar de aanmeldingen', 'url' => $s . 'aangemeld',
                    'gedaan' => 0, 'totaal' => $n, 'klaar' => false];

        case 'formulieren':
            $n = (int)($t['uitgenodigd_selectie'] ?? 0);
            $klaar = (int)($t['aangemeld_selectie'] ?? 0);
            if ($n === 0) {
                return $niets('Alle formulieren zijn binnen',
                              'Iedereen die is uitgenodigd heeft zich aangemeld voor de selectiedag.');
            }
            return ['titel' => $n . ' wachten nog op hun formulier',
                    'tekst'  => 'Zij zijn uitgenodigd maar hebben zich nog niet aangemeld voor de selectiedag. '
                              . 'Herinneringen gaan automatisch na 3 en 7 dagen.',
                    'knop'   => 'Bekijk wie', 'url' => $s . 'uitgenodigd_selectie',
                    'gedaan' => $klaar, 'totaal' => $klaar + $n, 'klaar' => false];

        case 'selectiedag':
            $n = (int)($t['aangemeld_selectie'] ?? 0);
            return ['titel' => 'Vandaag is de selectiedag',
                    'tekst'  => $n > 0 ? $n . ' kandidaten worden verwacht. Inchecken gaat op de tablet bij de ingang.'
                                       : 'Inchecken gaat op de tablet bij de ingang.',
                    'knop'   => 'Naar inchecken', 'url' => APP_URL . '/admin/selectie_inchecken.php',
                    'gedaan' => (int)($t['selectiedag_geweest'] ?? 0), 'totaal' => $n + (int)($t['selectiedag_geweest'] ?? 0),
                    'klaar'  => false];

        case 'bellijst':
            $n = (int)($t['selectiedag_geweest'] ?? 0);
            $door = (int)($t['uitgenodigd_tw'] ?? 0);
            if ($n === 0) {
                return $niets('Iedereen heeft een besluit',
                              'De bellijst is leeg. ' . $door . ' kandidaten zijn uitgenodigd voor het trainingsweekend.');
            }
            return ['titel' => 'De bellijst afwerken',
                    'tekst'  => $n . ' van de ' . ($n + $door) . ' nog niet gebeld.',
                    'knop'   => 'Bellijst openen', 'url' => APP_URL . '/admin/selectie_nabespreking.php?tab=bellijst',
                    'gedaan' => $door, 'totaal' => $n + $door, 'klaar' => false];

        case 'trainingsweekend':
            $n = (int)($t['uitgenodigd_tw'] ?? 0);
            return ['titel' => 'Het trainingsweekend',
                    'tekst'  => $n . ' kandidaten zijn uitgenodigd.',
                    'knop'   => 'Naar het trainingsweekend', 'url' => APP_URL . '/admin/trainingsweekend.php',
                    'gedaan' => (int)($t['tw_geweest'] ?? 0), 'totaal' => $n + (int)($t['tw_geweest'] ?? 0),
                    'klaar'  => false];

        case 'aanname':
        default:
            $n = (int)($t['tw_geweest'] ?? 0);
            if ($n === 0) {
                $aan = (int)($t['aangenomen'] ?? 0);
                return $niets('De lichting is rond',
                                $aan . ' strandwachten zijn aangenomen. De loondossiers lopen verder.');
            }
            return ['titel' => $n . ' wachten op een besluit',
                    'tekst'  => 'Zij waren op het trainingsweekend en moeten nog worden aangenomen of afgewezen.',
                    'knop'   => 'Bekijk wie', 'url' => $s . 'tw_geweest',
                    'gedaan' => (int)($t['aangenomen'] ?? 0), 'totaal' => $n + (int)($t['aangenomen'] ?? 0),
                    'klaar'  => false];
    }
}

/**
 * De dingen die ondertussen lopen: wel weten, niet nu handelen.
 * @return array<int,array{tekst:string,toon:string,extra:?string,url:?string}>
 */
function bh_vandaag_loopt(?array $seizoen): array {
    $uit = [];
    $sid = $seizoen ? (int)$seizoen['id'] : 0;
    if (!$sid) return $uit;

    try {
        $n = (int)db()->query("SELECT COUNT(*) FROM ondertekeningen
                               WHERE ondertekend_op IS NULL
                                 AND aangemaakt_op < DATE_SUB(NOW(), INTERVAL 3 DAY)")->fetchColumn();
        if ($n > 0) {
            $uit[] = ['tekst' => $n === 1 ? 'Eén ondertekening staat langer dan 3 dagen open'
                                          : $n . ' ondertekeningen staan langer dan 3 dagen open',
                      'toon' => 'oranje', 'extra' => null,
                      'url' => APP_URL . '/admin/ondertekeningen.php'];
        }
    } catch (\Throwable $e) {}

    try {
        $n = (int)db()->query("SELECT COUNT(*) FROM beschikbaarheid_profiel WHERE voorlopig=1")->fetchColumn();
        if ($n > 0) {
            $uit[] = ['tekst' => $n . ' beschikbaarheden zijn nog voorlopig', 'toon' => 'grijs',
                      'extra' => null, 'url' => APP_URL . '/admin/beschikbaarheid.php'];
        }
    } catch (\Throwable $e) {}

    return $uit;
}

/**
 * De systeemstatusbalk — twee standen.
 *
 * Automatisering die je niet ziet wordt eng zodra ze hapert (§3), dus de taakronde
 * staat altijd in beeld: wanneer hij liep en of er iets misging. Nooit alleen kleur:
 * de alarmstand heeft ook een woord en een knop.
 *
 * @return array{ok:bool,wachtrij:int,fouten:int,open:int,ronde:?string,ronde_ok:bool}
 */
function bh_systeemstatus(): array {
    $tel = function (string $sql): int {
        try { return (int)db()->query($sql)->fetchColumn(); } catch (\Throwable $e) { return 0; }
    };
    $wachtrij = $tel("SELECT COUNT(*) FROM mail_queue WHERE status='wacht'");
    $fouten   = $tel("SELECT COUNT(*) FROM mail_queue WHERE status='fout'
                      AND aangemaakt_op > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $open     = $tel("SELECT COUNT(*) FROM ondertekeningen WHERE ondertekend_op IS NULL
                      AND aangemaakt_op < DATE_SUB(NOW(), INTERVAL 3 DAY)");
    $ronde    = function_exists('instelling') ? instelling('cron.laatste_run', '') : '';
    $ronde_ok = $ronde !== '' && strtotime($ronde) > time() - 7200;

    return [
        'ok'       => $fouten === 0 && $ronde_ok && $wachtrij <= 20,
        'wachtrij' => $wachtrij, 'fouten' => $fouten, 'open' => $open,
        'ronde'    => $ronde !== '' ? $ronde : null, 'ronde_ok' => $ronde_ok,
    ];
}

/** Badges in het menu: alleen wat om aandacht vraagt. */
function bh_nav_tellers(?array $seizoen): array {
    $uit = [];
    $sid = $seizoen ? (int)$seizoen['id'] : 0;
    if (!$sid) return $uit;

    $taak = bh_vandaag_hoofdtaak($seizoen, bh_seizoen_fase($seizoen));
    if (!$taak['klaar'] && $taak['totaal'] > 0) {
        $uit['dashboard'] = max(0, $taak['totaal'] - $taak['gedaan']);
    }
    try {
        $n = (int)db()->query("SELECT COUNT(*) FROM ondertekeningen WHERE ondertekend_op IS NULL
                               AND aangemaakt_op < DATE_SUB(NOW(), INTERVAL 3 DAY)")->fetchColumn();
        if ($n > 0) $uit['ondertekeningen'] = $n;
    } catch (\Throwable $e) {}

    return $uit;
}

} // function_exists
