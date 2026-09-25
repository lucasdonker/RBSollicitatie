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
 * Ondertekeningen die langer dan drie dagen openstaan, in dit seizoen.
 *
 * Eén telling voor het dashboard, de systeemstatus en het menubolletje. Die telden
 * eerder elk apart over álle seizoenen en inclusief afgewezen kandidaten, terwijl
 * admin/ondertekeningen.php (waar ze naartoe linken) alleen dit seizoen toont — het
 * getal klopte dan nooit met de lijst.
 */
function bh_ondertekeningen_te_lang_open(int $seizoen_id): int {
    static $cache = [];
    if (isset($cache[$seizoen_id])) return $cache[$seizoen_id];
    if ($seizoen_id <= 0) return 0;
    try {
        $q = db()->prepare("SELECT COUNT(*) FROM ondertekeningen o
                            JOIN sollicitaties s ON s.id = o.sollicitatie_id
                            WHERE s.seizoen_id = ? AND s.fase NOT IN ('afgewezen','afgehaakt')
                              AND o.ondertekend_op IS NULL
                              AND o.aangemaakt_op < DATE_SUB(NOW(), INTERVAL 3 DAY)");
        $q->execute([$seizoen_id]);
        return $cache[$seizoen_id] = (int)$q->fetchColumn();
    } catch (\Throwable $e) { return $cache[$seizoen_id] = 0; }
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
    // Het menubolletje (bh_nav_tellers) vraagt dit op elke pagina; Vandaag nog een keer.
    static $cache = [];
    $sid = $seizoen ? (int)$seizoen['id'] : 0;
    $sleutel = $sid . '|' . $seizoen_fase;
    if (isset($cache[$sleutel])) return $cache[$sleutel];
    $tk = bh_vandaag_hoofdtaak_bepalen($sid, $seizoen_fase);
    // Is de taak van het seizoen niet voor jouw rol (de knop viel weg op rechten), dan een taak
    // die wél bij je past — de penningmeester had hier "12 aanmeldingen uitnodigen" staan.
    if (!$tk['klaar'] && $tk['url'] === null) $tk = bh_vandaag_roltaak($sid, $tk);
    return $cache[$sleutel] = $tk;
}

/**
 * De hoofdtaak voor wie de taak van het seizoen niet kan doen.
 * Penningmeester: loondossiers die compleet zijn maar nog niet verwerkt. Anders: niets voor jou.
 */
function bh_vandaag_roltaak(int $sid, array $algemeen): array {
    $niets = ['titel' => 'Er ligt nu niets voor jou', 'knop' => null, 'url' => null, 'gedaan' => 0, 'totaal' => 0, 'klaar' => true,
              'tekst' => 'Het bestuur is bezig met: ' . mb_strtolower($algemeen['titel']) . '.'];
    if ($sid && function_exists('mag') && mag('documenten', 'bewerken') && function_exists('documenten_overzicht')) {
        $rijen = documenten_overzicht($sid);
        $te_doen = count(array_filter($rijen, fn($r) => $r['dossier']['compleet'] && !$r['dossier']['verwerkt']));
        $klaar   = count(array_filter($rijen, fn($r) => $r['dossier']['verwerkt']));
        if ($te_doen > 0) {
            return ['titel' => $te_doen === 1 ? 'Eén loondossier te verwerken' : $te_doen . ' loondossiers te verwerken',
                    'tekst' => 'Compleet aangeleverd, nog niet in de loonadministratie. Markeer ze als verwerkt zodra dat klaar is.',
                    'knop'  => 'Naar de loondossiers', 'url' => APP_URL . '/admin/documenten.php',
                    'gedaan' => $klaar, 'totaal' => $klaar + $te_doen, 'klaar' => false];
        }
        return ['titel' => 'Geen loondossiers te verwerken', 'knop' => null, 'url' => null, 'gedaan' => $klaar, 'totaal' => count($rijen), 'klaar' => true,
                'tekst' => count($rijen) ? $klaar . ' van ' . count($rijen) . ' verwerkt; de rest is nog niet compleet.' : 'Er is nog niemand aangenomen.'];
    }
    return $niets;
}

/**
 * De tekst over automatische herinneringen, zoals ze echt lopen
 * (includes/meldingen/herinneringen.php, regel herinnering_selectie_aanmelden).
 * Leeg als dat niet te bepalen is: liever niets zeggen dan iets wat niet klopt.
 */
function bh_vandaag_herinnering_tekst(): string {
    $slug = 'herinnering_selectie_aanmelden';
    if (!function_exists('herinnering_aan') || !function_exists('herinnering_int')) return '';
    if (!herinnering_aan($slug)) return 'De automatische herinnering staat uit (Instellingen › Uitnodigen & automatisch).';
    $na      = herinnering_int($slug, 'na');
    $herhaal = herinnering_int($slug, 'herhaal');
    $max     = herinnering_int($slug, 'max');
    $zin = 'Vanaf 14 dagen vóór de selectiedag krijgen ze automatisch een herinnering, '
         . 'de eerste ' . $na . ' ' . ($na === 1 ? 'dag' : 'dagen') . ' na de uitnodiging';
    if ($herhaal > 0 && $max > 1) $zin .= ', daarna elke ' . $herhaal . ' dagen, hooguit ' . $max . ' keer';
    return $zin . '.';
}

/** @see bh_vandaag_hoofdtaak() — zonder cache. */
function bh_vandaag_hoofdtaak_bepalen(int $sid, string $seizoen_fase): array {
    $t   = $sid ? bh_fase_tellingen($sid) : [];
    $s   = APP_URL . '/admin/sollicitanten.php?fase=';

    // LET OP: gebruik deze helper en niet `$leeg + [...]`. De +-operator op arrays laat
    // de LINKERkant winnen bij dubbele sleutels, dus de titel bleef leeg.
    $niets = fn(string $titel, string $tekst): array => [
        'titel' => $titel, 'tekst' => $tekst, 'knop' => null, 'url' => null,
        'gedaan' => 0, 'totaal' => 0, 'klaar' => true,
    ];
    // Een knop naar een scherm dat je rol niet mag openen is een belofte die op "Geen
    // toegang" eindigt. Dan de taak wel tonen, maar zonder knop.
    $taak = function (array $tk, string $module, string $actie = 'lezen'): array {
        if (function_exists('mag') && !mag($module, $actie)) { $tk['knop'] = null; $tk['url'] = null; }
        return $tk;
    };

    switch ($seizoen_fase) {
        case 'werving':
            $n = (int)($t['aangemeld'] ?? 0);
            if ($n === 0) {
                return $niets('Nog geen nieuwe aanmeldingen',
                              'Zodra iemand zich via een wervingspagina aanmeldt, staat die hier.');
            }
            return $taak(['titel' => $n === 1 ? 'Eén aanmelding uitnodigen' : $n . ' aanmeldingen uitnodigen',
                    'tekst'  => 'Deze mensen hebben zich aangemeld en wachten op een uitnodiging voor de selectiedag.',
                    'knop'   => 'Naar de aanmeldingen', 'url' => $s . 'aangemeld',
                    'gedaan' => 0, 'totaal' => $n, 'klaar' => false], 'sollicitanten');

        case 'formulieren':
            $n = (int)($t['uitgenodigd_selectie'] ?? 0);
            $klaar = (int)($t['aangemeld_selectie'] ?? 0);
            if ($n === 0) {
                return $niets('Alle formulieren zijn binnen',
                              'Iedereen die is uitgenodigd heeft zich aangemeld voor de selectiedag.');
            }
            return $taak(['titel' => $n . ' wachten nog op hun formulier',
                    'tekst'  => trim('Zij zijn uitgenodigd maar hebben zich nog niet aangemeld voor de selectiedag. '
                              . bh_vandaag_herinnering_tekst()),
                    'knop'   => 'Bekijk wie', 'url' => $s . 'uitgenodigd_selectie',
                    'gedaan' => $klaar, 'totaal' => $klaar + $n, 'klaar' => false], 'sollicitanten');

        case 'selectiedag':
            $n = (int)($t['aangemeld_selectie'] ?? 0);
            return $taak(['titel' => 'Vandaag is de selectiedag',
                    'tekst'  => $n > 0 ? $n . ' kandidaten worden verwacht. Inchecken gaat op de tablet bij de ingang.'
                                       : 'Inchecken gaat op de tablet bij de ingang.',
                    'knop'   => 'Naar inchecken', 'url' => APP_URL . '/admin/selectie_inchecken.php',
                    'gedaan' => (int)($t['selectiedag_geweest'] ?? 0), 'totaal' => $n + (int)($t['selectiedag_geweest'] ?? 0),
                    'klaar'  => false], 'selectiedag');

        case 'bellijst':
            // De stand van de bellijst zelf (selectie_besluit), niet alleen de fase: wie
            // "SD geweest" is kan nog zonder besluit zijn, gebeld maar niet doorgevoerd, of
            // op twijfel staan. Dat zijn drie verschillende taken.
            if ($sid && function_exists('selectie_besluiten') && function_exists('selectie_kandidaten')) {
                $besluiten = selectie_besluiten($sid);
                $zonder = $ongebeld = $gebeld = $twijfel = 0;
                foreach (selectie_kandidaten($sid) as $k) {
                    $b = $besluiten[(int)$k['sol_id']] ?? null;
                    $w = (string)($b['besluit'] ?? '');
                    if ($w === '')             $zonder++;
                    elseif ($w === 'twijfel')  $twijfel++;
                    elseif (empty($b['doorgevoerd_op'])) { if (empty($b['gebeld_op'])) $ongebeld++; else $gebeld++; }
                }
                $doorgevoerd = 0;
                foreach ($besluiten as $b) if (!empty($b['doorgevoerd_op'])) $doorgevoerd++;
                $open = $ongebeld + $gebeld;

                if ($zonder > 0 && $open === 0) {
                    return $taak(['titel' => $zonder === 1 ? 'Eén kandidaat zonder besluit' : $zonder . ' kandidaten zonder besluit',
                            'tekst'  => 'Leg eerst in de nabespreking per kandidaat een besluit vast; daarna bel je.',
                            'knop'   => 'Naar de nabespreking', 'url' => APP_URL . '/admin/selectie_nabespreking.php?filter=open',
                            'gedaan' => $doorgevoerd, 'totaal' => $doorgevoerd + $zonder + $twijfel, 'klaar' => false],
                        'selectiedag', 'bewerken');
                }
                if ($open === 0) {
                    return $niets($twijfel > 0 ? 'Alleen twijfelgevallen over' : 'De bellijst is leeg',
                        ($twijfel > 0 ? $twijfel . ' op twijfel: daar moet iemand over bellen. ' : '')
                        . (int)($t['uitgenodigd_tw'] ?? 0) . ' kandidaten zijn uitgenodigd voor het trainingsweekend.');
                }
                $tekst = $ongebeld . ' nog niet gebeld';
                if ($gebeld > 0)  $tekst .= ', ' . $gebeld . ' gebeld maar nog niet doorgevoerd';
                if ($zonder > 0)  $tekst .= ', ' . $zonder . ' nog zonder besluit';
                if ($twijfel > 0) $tekst .= ', ' . $twijfel . ' op twijfel';
                return $taak(['titel' => 'De bellijst afwerken', 'tekst' => $tekst . '.',
                        'knop'   => 'Bellijst openen', 'url' => APP_URL . '/admin/selectie_nabespreking.php?tab=bellijst',
                        'gedaan' => $doorgevoerd, 'totaal' => $doorgevoerd + $open, 'klaar' => false],
                    'selectiedag', 'bewerken');
            }
            // Terugval zonder selectiedag-module: de oude telling op fase.
            $n = (int)($t['selectiedag_geweest'] ?? 0);
            $door = (int)($t['uitgenodigd_tw'] ?? 0);
            if ($n === 0) {
                return $niets('Iedereen heeft een besluit',
                              'De bellijst is leeg. ' . $door . ' kandidaten zijn uitgenodigd voor het trainingsweekend.');
            }
            return $taak(['titel' => 'De bellijst afwerken',
                    'tekst'  => $n . ' kandidaten van de selectiedag zijn nog niet doorgevoerd.',
                    'knop'   => 'Bellijst openen', 'url' => APP_URL . '/admin/selectie_nabespreking.php?tab=bellijst',
                    'gedaan' => $door, 'totaal' => $n + $door, 'klaar' => false], 'selectiedag', 'bewerken');

        case 'trainingsweekend':
            $n = (int)($t['uitgenodigd_tw'] ?? 0);
            // Inchecken zet tw_geweest, "niet gekomen" niet; de voortgang komt dus uit tw_deelname.
            $st = ($sid && function_exists('tw_kandidaten') && function_exists('tw_stand')) ? tw_stand(tw_kandidaten($sid)) : null;
            return $taak(['titel' => 'Het trainingsweekend',
                    'tekst'  => $n . ' kandidaten zijn uitgenodigd.'
                              . ($st && $st['onbekend'] > 0 ? ' ' . $st['onbekend'] . ' nog niet afgevinkt.' : ''),
                    'knop'   => 'Naar het trainingsweekend', 'url' => APP_URL . '/admin/trainingsweekend.php',
                    'gedaan' => $st ? (int)$st['aanwezig'] + (int)$st['afwezig'] : (int)($t['tw_geweest'] ?? 0),
                    'totaal' => $st ? (int)$st['totaal'] : $n + (int)($t['tw_geweest'] ?? 0),
                    'klaar'  => false], 'trainingsweekend');

        case 'aanname':
        default:
            // De besluiten staan in tw_deelname (het gesprek met het bestuur). Telden we alleen
            // de fase, dan stond hier "De lichting is rond" terwijl er nog gesprekken openstonden.
            $aan = (int)($t['aangenomen'] ?? 0);
            if ($sid && function_exists('tw_kandidaten') && function_exists('tw_stand')) {
                $st = tw_stand(tw_kandidaten($sid));
                $besluit_url = APP_URL . '/admin/trainingsweekend.php?stap=gesprekken';
                $voortgang = ['gedaan' => (int)$st['doorgevoerd'], 'totaal' => (int)$st['totaal'], 'klaar' => false];
                if ($st['te_beslissen'] > 0) {
                    return $taak(['titel' => $st['te_beslissen'] . ' wachten op een gesprek',
                            'tekst' => 'Zij waren uitgenodigd voor het trainingsweekend en moeten nog worden aangenomen of afgewezen.',
                            'knop' => 'Naar de gesprekken', 'url' => $besluit_url] + $voortgang, 'trainingsweekend', 'bewerken');
                }
                if ($st['klaar_om_door_te_voeren'] > 0) {
                    // Gebeurt alleen als doorvoeren in het gesprek mislukte (fase_zetten gaf een fout).
                    $k = (int)$st['klaar_om_door_te_voeren'];
                    return $taak(['titel' => $k . ' ' . ($k === 1 ? 'besluit' : 'besluiten') . ' niet doorgevoerd',
                            'tekst' => 'Het besluit staat vast, maar doorvoeren lukte niet. Open het gesprek en leg het opnieuw vast.',
                            'knop' => 'Naar de gesprekken', 'url' => $besluit_url] + $voortgang, 'trainingsweekend', 'bewerken');
                }
                if ($st['twijfel_open'] > 0) {
                    return $taak(['titel' => $st['twijfel_open'] . ' op twijfel',
                            'tekst' => 'Bel ze, open daarna het gesprek en leg het besluit vast. Twijfel gaat nooit vanzelf de deur uit.',
                            'knop' => 'Naar de gesprekken', 'url' => $besluit_url] + $voortgang, 'trainingsweekend', 'bewerken');
                }
            }
            $n = (int)($t['tw_geweest'] ?? 0);
            if ($n === 0) {
                return $niets('De lichting is rond',
                                $aan . ' strandwachten zijn aangenomen. De loondossiers lopen verder.');
            }
            return $taak(['titel' => $n . ' wachten op een besluit',
                    'tekst'  => 'Zij waren op het trainingsweekend en moeten nog worden aangenomen of afgewezen.',
                    'knop'   => 'Bekijk wie', 'url' => $s . 'tw_geweest',
                    'gedaan' => $aan, 'totaal' => $n + $aan,
                    'klaar'  => false], 'sollicitanten');
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

    $n = bh_ondertekeningen_te_lang_open($sid);
    if ($n > 0) {
        $uit[] = ['tekst' => $n === 1 ? 'Eén ondertekening staat langer dan 3 dagen open'
                                      : $n . ' ondertekeningen staan langer dan 3 dagen open',
                  'toon' => 'oranje', 'extra' => null,
                  'url' => APP_URL . '/admin/ondertekeningen.php'];
    }

    // Zelfde selectie als de lijst "nog voorlopig" (beschikbaarheid2_voorlopigen) en de
    // herinneringen: dit seizoen, en alleen wie echt weken heeft ingevuld.
    try {
        $q = db()->prepare("SELECT COUNT(*) FROM beschikbaarheid_profiel bp
                            JOIN sollicitaties s ON s.id = bp.sollicitatie_id
                            WHERE bp.voorlopig = 1 AND s.seizoen_id = ? AND s.fase NOT IN ('afgewezen','afgehaakt')
                              AND EXISTS (SELECT 1 FROM beschikbaarheid_blok bb WHERE bb.sollicitatie_id = bp.sollicitatie_id)");
        $q->execute([$sid]);
        $n = (int)$q->fetchColumn();
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
    $sz       = function_exists('actief_seizoen') ? actief_seizoen() : null;
    $open     = bh_ondertekeningen_te_lang_open((int)($sz['id'] ?? 0));
    $ronde    = function_exists('instelling') ? instelling('cron.laatste_run', '') : '';
    // Elke groep telt: draaien de berichten wel maar de herinneringen niet, dan klopt het niet.
    $ronde_ok = $ronde !== '';
    if (function_exists('cron_stand')) {
        foreach (cron_stand() as $g) if ($g['status'] !== 'ok') $ronde_ok = false;
    }

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
    $n = bh_ondertekeningen_te_lang_open($sid);
    if ($n > 0) $uit['ondertekeningen'] = $n;

    return $uit;
}

} // function_exists
