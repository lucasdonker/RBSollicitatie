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

/**
 * De werkbak: alles wat op een mens wacht, uit alle modules, gefilterd op wat jouw rol mag.
 * Elke regel is een aantal + een zin + een knop naar de plek waar je het afhandelt. Wat al de
 * hoofdtaak is (zelfde url) staat er niet nog eens. Afmeldingen en intrekverzoeken hebben een
 * eigen blok met knoppen (admin/_afmeldingen.php, admin/_intrekverzoeken.php) en staan hier niet.
 *
 * @return array<int,array{aantal:int,tekst:string,url:string,knop:string,toon:string,icoon:string}>
 */
function bh_vandaag_werkbak(?array $seizoen, array $hoofdtaak): array {
    $sid = $seizoen ? (int)$seizoen['id'] : 0;
    if (!$sid) return [];
    $mag = fn(string $m, string $a = 'lezen') => !function_exists('mag') || mag($m, $a);
    $tel = function (string $sql, array $p = []) {
        try { $q = db()->prepare($sql); $q->execute($p); return (int)$q->fetchColumn(); } catch (\Throwable $e) { return 0; }
    };
    $a = APP_URL . '/admin/';
    $uit = [];
    $zet = function (int $n, string $een, string $meer, string $url, string $knop, string $toon, string $icoon) use (&$uit, $hoofdtaak) {
        if ($n <= 0 || ($hoofdtaak['url'] ?? null) === $url) return;
        $uit[] = ['aantal' => $n, 'tekst' => $n === 1 ? $een : $meer, 'url' => $url, 'knop' => $knop, 'toon' => $toon, 'icoon' => $icoon];
    };
    $vandaag = date('Y-m-d');

    if ($mag('sollicitanten', 'bewerken')) {
        $n = $tel("SELECT COUNT(*) FROM sollicitaties WHERE seizoen_id=? AND fase='aangemeld'", [$sid]);
        $zet($n, 'aanmelding wacht op een uitnodiging', 'aanmeldingen wachten op een uitnodiging', $a . 'sollicitanten.php?fase=aangemeld', 'Uitnodigen', 'oranje', 'mensen');
    }
    if ($mag('beschikbaarheid', 'bewerken')) {
        $n = $tel("SELECT COUNT(*) FROM beschikbaarheid_verzoek v JOIN sollicitaties s ON s.id=v.sollicitatie_id WHERE s.seizoen_id=? AND v.status='open'", [$sid]);
        $zet($n, 'verzoek om minder dagen te beoordelen', 'verzoeken om minder dagen te beoordelen', $a . 'beschikbaarheid.php?toon=verzoek', 'Beoordelen', 'oranje', 'klok');
    }
    if ($mag('selectiedag', 'bewerken') && (!function_exists('module_aan') || module_aan('selectiedag'))) {
        $n = $tel("SELECT COUNT(*) FROM selectie_besluit b JOIN sollicitaties s ON s.id=b.sollicitatie_id
                   WHERE b.seizoen_id=? AND b.gebeld_op IS NOT NULL AND b.doorgevoerd_op IS NULL AND s.fase IN ('uitgenodigd_selectie','aangemeld_selectie','selectiedag_geweest')", [$sid]);
        $zet($n, 'gebeld, maar nog niet doorgevoerd', 'gebeld, maar nog niet doorgevoerd', $a . 'selectie_nabespreking.php?tab=bellijst', 'Doorvoeren', 'oranje', 'telefoon');
        if (!empty($seizoen['datum_selectiedag']) && $vandaag > $seizoen['datum_selectiedag']) {
            $n = $tel("SELECT COUNT(*) FROM sollicitaties s LEFT JOIN selectie_besluit b ON b.sollicitatie_id=s.id
                       WHERE s.seizoen_id=? AND s.fase='selectiedag_geweest' AND (b.besluit IS NULL OR b.besluit='')", [$sid]);
            $zet($n, 'kandidaat van de selectiedag zonder besluit', 'kandidaten van de selectiedag zonder besluit', $a . 'selectie_nabespreking.php?filter=open', 'Nabespreken', 'oranje', 'vink');
        }
    }
    if ($mag('trainingsweekend', 'bewerken') && (!function_exists('module_aan') || module_aan('trainingsweekend'))) {
        $n = $tel("SELECT COUNT(*) FROM tw_deelname d JOIN sollicitaties s ON s.id=d.sollicitatie_id
                   WHERE d.seizoen_id=? AND d.besluit='twijfel' AND d.doorgevoerd_op IS NULL AND s.fase IN ('uitgenodigd_tw','tw_geweest')", [$sid]);
        $zet($n, 'twijfelgeval na het trainingsweekend: bellen en beslissen', 'twijfelgevallen na het trainingsweekend: bellen en beslissen', $a . 'trainingsweekend.php?stap=gesprekken&besluit=twijfel', 'Naar de gesprekken', 'oranje', 'telefoon');
    }
    if ($mag('ondertekeningen')) {
        $n = bh_ondertekeningen_te_lang_open($sid);
        $zet($n, 'ondertekening staat langer dan 3 dagen open', 'ondertekeningen staan langer dan 3 dagen open', $a . 'ondertekeningen.php', 'Bekijken', 'grijs', 'potlood');
    }
    if ($mag('beschikbaarheid')) {
        $n = $tel("SELECT COUNT(*) FROM beschikbaarheid_profiel bp JOIN sollicitaties s ON s.id = bp.sollicitatie_id
                   WHERE bp.voorlopig = 1 AND s.seizoen_id = ? AND s.fase NOT IN ('afgewezen','afgehaakt')
                     AND EXISTS (SELECT 1 FROM beschikbaarheid_blok bb WHERE bb.sollicitatie_id = bp.sollicitatie_id)", [$sid]);
        $zet($n, 'beschikbaarheid is nog voorlopig', 'beschikbaarheden zijn nog voorlopig', $a . 'beschikbaarheid.php?toon=voorlopig', 'Bekijken', 'grijs', 'kalender');
    }
    if ($mag('documenten', 'bewerken') && function_exists('documenten_overzicht') && ($hoofdtaak['url'] ?? '') !== $a . 'documenten.php') {
        $n = count(array_filter(documenten_overzicht($sid), fn($r) => $r['dossier']['compleet'] && !$r['dossier']['verwerkt']));
        $zet($n, 'loondossier te verwerken', 'loondossiers te verwerken', $a . 'documenten.php', 'Verwerken', 'oranje', 'slot');
    }
    if ($mag('mailing')) {
        $n = $tel("SELECT COUNT(*) FROM mail_log WHERE status='mislukt' AND verstuurd_op > DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $zet($n, 'bericht kwam deze week niet aan', 'berichten kwamen deze week niet aan', $a . 'berichten.php?tab=log', 'Bekijken', 'rood', 'mail');
    }
    return $uit;
}

/**
 * Wat er nieuw is sinds je vorige bezoek (beheerder_vandaag_sinds()). Alleen wat je mag zien;
 * rijen met 0 vallen weg.
 *
 * @return array<int,array{aantal:int,tekst:string,url:?string}>
 */
function bh_vandaag_sinds(?array $seizoen, string $sinds): array {
    $sid = $seizoen ? (int)$seizoen['id'] : 0;
    if (!$sid) return [];
    $mag = fn(string $m) => !function_exists('mag') || mag($m, 'lezen');
    $tel = function (string $sql) use ($sid, $sinds) {
        try { $q = db()->prepare($sql); $q->execute([$sid, $sinds]); return (int)$q->fetchColumn(); } catch (\Throwable $e) { return 0; }
    };
    $a = APP_URL . '/admin/';
    $regels = [];
    $zet = function (bool $mag_zien, int $n, string $een, string $meer, ?string $url) use (&$regels) {
        if ($mag_zien && $n > 0) $regels[] = ['aantal' => $n, 'tekst' => $n === 1 ? $een : $meer, 'url' => $url];
    };
    $s = "JOIN sollicitaties s ON s.id = x.sollicitatie_id WHERE s.seizoen_id = ? AND";
    $zet($mag('sollicitanten'), $tel("SELECT COUNT(*) FROM sollicitaties s WHERE s.seizoen_id = ? AND s.aangemeld_op > ?"),
         'nieuwe aanmelding', 'nieuwe aanmeldingen', $a . 'sollicitanten.php?sort=s.aangemeld_op');
    $zet($mag('blk'), $tel("SELECT COUNT(*) FROM blk_formulieren x $s x.definitief_op > ?"),
         'kennismakingsformulier ingeleverd', 'kennismakingsformulieren ingeleverd', $a . 'sollicitanten.php?blk=ja');
    $zet($mag('beschikbaarheid'), $tel("SELECT COUNT(*) FROM beschikbaarheid_profiel x $s x.definitief_op > ?"),
         'beschikbaarheid definitief gemaakt', 'beschikbaarheden definitief gemaakt', $a . 'beschikbaarheid.php');
    $zet($mag('beschikbaarheid'), $tel("SELECT COUNT(*) FROM beschikbaarheid_verzoek x $s x.aangemaakt_op > ?"),
         'verzoek om minder dagen', 'verzoeken om minder dagen', $a . 'beschikbaarheid.php?toon=verzoek');
    $zet($mag('ondertekeningen'), $tel("SELECT COUNT(*) FROM ondertekeningen x $s x.ondertekend_op > ?"),
         'ondertekening binnen', 'ondertekeningen binnen', $a . 'ondertekeningen.php');
    $zet($mag('sollicitanten'), $tel("SELECT COUNT(*) FROM mail_log x $s x.meldingstype = 'sollicitant_vraag' AND x.verstuurd_op > ?"),
         'vraag uit het portaal (staat in de mailbox)', 'vragen uit het portaal (staan in de mailbox)', null);
    $zet($mag('sollicitanten'), $tel("SELECT COUNT(*) FROM selectie_indeling x $s x.bevestiging = 'komt_niet' AND x.bevestigd_op > ?")
                             + $tel("SELECT COUNT(*) FROM tw_deelname x $s x.bevestiging = 'komt_niet' AND x.bevestigd_op > ?"),
         'afmelding: kan niet komen', 'afmeldingen: kunnen niet komen', null);
    $zet($mag('sollicitanten'), $tel("SELECT COUNT(*) FROM intrek_verzoek x $s x.aangevraagd_op > ?"),
         'verzoek om in te trekken', 'verzoeken om in te trekken', $a . 'sollicitanten.php?intrek=ja');
    return $regels;
}

/**
 * De agenda: datums van het seizoen en geplande berichten die aan een datum hangen, vanaf
 * vandaag tot $dagen dagen vooruit, oplopend.
 *
 * @return array<int,array{datum:string,tot:?string,tekst:string,soort:string,over:int}>
 */
function bh_vandaag_agenda(?array $seizoen, int $dagen = 60): array {
    if (!$seizoen) return [];
    $van = date('Y-m-d'); $tot = date('Y-m-d', strtotime('+' . $dagen . ' days'));
    $uit = [];
    $zet = function (?string $datum, string $tekst, string $soort, ?string $eind = null) use (&$uit, $van, $tot) {
        if (!$datum) return;
        $d = substr($datum, 0, 10);
        $e = $eind ? substr($eind, 0, 10) : null;
        if (($e ?? $d) < $van || $d > $tot) return;   // lopend (bijv. het weekend) telt mee
        $uit[] = ['datum' => $d, 'tot' => ($e && $e !== $d) ? $e : null, 'tekst' => $tekst, 'soort' => $soort,
                  'over' => (int)round((strtotime($d) - strtotime($van)) / 86400)];
    };
    $mod = fn(string $m) => !function_exists('module_aan') || module_aan($m);
    if ($mod('selectiedag'))      $zet($seizoen['datum_selectiedag'] ?? null, 'Selectiedag', 'evenement');
    if ($mod('trainingsweekend')) $zet($seizoen['datum_tw_start'] ?? null, 'Trainingsweekend', 'evenement', $seizoen['datum_tw_eind'] ?? null);
    $zet($seizoen['datum_werk_start'] ?? null, 'Start werkperiode', 'seizoen');
    $zet($seizoen['datum_werk_eind'] ?? null, 'Einde werkperiode', 'seizoen');
    // Geplande berichten die aan de selectiedag of het weekend hangen (vaste datum + offset).
    if (function_exists('gepland_regels') && (!function_exists('mag') || mag('mailing', 'lezen'))) {
        foreach (gepland_regels(true) as $r) {
            $basis = ['selectiedag' => $seizoen['datum_selectiedag'] ?? null, 'trainingsweekend' => $seizoen['datum_tw_start'] ?? null][$r['gebeurtenis']] ?? null;
            if (!$basis) continue;
            $zet(date('Y-m-d', strtotime(substr($basis, 0, 10) . ' ' . sprintf('%+d', (int)$r['offset_dagen']) . ' days')),
                 'Bericht: ' . $r['naam'], 'bericht');
        }
    }
    usort($uit, fn($x, $y) => strcmp($x['datum'], $y['datum']) ?: strcmp($x['soort'], $y['soort']));
    return $uit;
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
