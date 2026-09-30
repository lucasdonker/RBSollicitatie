<?php
/**
 * Paal 13 — seizoenbeheer: wat staat er klaar, en wat gebeurt er als je iets doet?
 *
 * Geen HTML. Drie dingen:
 *   1. bh_seizoen_stappen()  — het stappenplan als stand, niet als uitleg. Een statische
 *      lijst "1. aanmaken 2. datums 3. openen" lees je één keer; een lijst die zegt wat
 *      al af is, blijft nuttig. Dit is een jaarlijkse taak: niemand onthoudt de volgorde.
 *   2. bh_seizoen_gevolgen() — de tekst van de bevestiging. Die hoort hier en niet in
 *      het scherm, want de knop op de kaart en het tussenscherm zonder JavaScript
 *      moeten hetzelfde beloven.
 *   3. bh_seizoen_verwijderen() — een seizoen weg, via de AVG-wislaag (includes/avg_wissen.php).
 *
 * Open/gesloten gaat ALLEEN over de aanmelding (het aanmeldformulier). Waar het seizoen staat
 * (selectiedag, weekend, aanname) volgt uit de data: bh_seizoen_fase_bepalen() in fase_ui.php.
 */

if (!function_exists('bh_seizoen_stappen')) {

/**
 * De stappen van een seizoen, met per stap of hij af is. Stappen van een module die uit
 * staat (selectiedag, trainingsweekend, loonplanning) vallen weg.
 *
 * @return array [ ['sleutel','titel','tekst','af'=>bool,'nu'=>bool], … ]
 *               'nu' staat op de eerste stap die nog niet af is: dat is wat je moet doen.
 */
function bh_seizoen_stappen(?array $sz): array {
    $sz = $sz ?: [];
    $heeft = fn(string $k) => trim((string)($sz[$k] ?? '')) !== '';
    $sd_aan = bh_seizoen_module_aan('selectiedag');
    $tw_aan = bh_seizoen_module_aan('trainingsweekend');
    $sid    = (int)($sz['id'] ?? 0);
    $n      = (int)($sz['n'] ?? 0);
    $open   = ($sz['status'] ?? '') === 'open';
    // Waar het seizoen staat, uit de data (niet uit de status en niet uit de handmatige fase:
    // die geldt voor het actieve seizoen, niet voor een ander dat je bewerkt).
    $fase   = $sid ? bh_seizoen_fase_bepalen($sz) : 'werving';
    $na     = fn(string ...$fases) => in_array($fase, $fases, true);

    $stappen = [
        ['sleutel' => 'aanmaken', 'titel' => 'Seizoen aangemaakt',
         'tekst' => 'Een naam, bijvoorbeeld "Zomer 2027".',
         'af' => $heeft('naam')],
        ['sleutel' => 'datums', 'titel' => 'Datums ingevuld',
         'tekst' => ($sd_aan ? 'Selectiedag, ' : '') . ($tw_aan ? 'trainingsweekend en ' : '')
                  . 'de werkperiode. De werkperiode bepaalt welke weken de sollicitant in het portaal ziet.',
         'af' => (!$sd_aan || $heeft('datum_selectiedag')) && (!$tw_aan || $heeft('datum_tw_start')) && $heeft('datum_werk_start')],
        // Af zodra de aanmelding open staat of heeft gestaan (er zijn aanmeldingen).
        ['sleutel' => 'openen', 'titel' => 'Aanmelding geopend',
         'tekst' => 'Het aanmeldformulier staat aan; wie zich aanmeldt, komt in dit seizoen terecht.',
         'af' => $open || $n > 0],
    ];
    if ($sd_aan) {
        $stappen[] = ['sleutel' => 'selectiedag', 'titel' => 'Selectiedag en bellijst',
            'tekst' => 'Uitnodigen, groepen indelen, inchecken, nabespreken en bellen doe je bij Selectiedag.',
            'af' => $na('trainingsweekend', 'aanname', 'afronden')];
    }
    if ($tw_aan) {
        $stappen[] = ['sleutel' => 'trainingsweekend', 'titel' => 'Trainingsweekend en gesprekken',
            'tekst' => 'Voorbereiden, inchecken, beoordelen en de gesprekken (aannemen of afwijzen) doe je bij Trainingsweekend.',
            'af' => $na('afronden')];
    }
    if (bh_seizoen_module_aan('loonplanning')) {
        $aangenomen = function_exists('bh_fase_tellingen') && $sid ? (int)(bh_fase_tellingen($sid)['aangenomen'] ?? 0) : 0;
        $stappen[] = ['sleutel' => 'loonplanning', 'titel' => 'Afspraken vastgesteld',
            'tekst' => 'In de loonplanning maak je de inzet van wie is aangenomen definitief.',
            'af' => $na('afronden') && $aangenomen > 0 && bh_aangenomen_zonder_afspraak($sid) === 0];
    }
    // De aanmelding sluiten kan op elk moment; wie al in de procedure zit, loopt gewoon door.
    $stappen[] = ['sleutel' => 'sluiten', 'titel' => 'Aanmelding gesloten',
        'tekst' => 'Zet het aanmeldformulier uit als je genoeg aanmeldingen hebt, uiterlijk bij het afronden. Alle gegevens blijven bewaard.',
        'af' => !$open && $n > 0];

    $gezet = false;
    foreach ($stappen as $i => $s) {
        $stappen[$i]['nu'] = (!$s['af'] && !$gezet);
        if ($stappen[$i]['nu']) $gezet = true;
    }
    return $stappen;
}

/**
 * Wat gebeurt er als je deze seizoensactie uitvoert?
 * Zelfde vorm als bh_gevolgen(), zodat admin/_bevestig.php hem zonder aanpassing rendert.
 *
 * @param array      $sz          de seizoensrij, inclusief 'n' (aantal sollicitaties) en bij
 *                                verwijderen 'loon_bewaard' (bh_seizoen_loon_bewaard())
 * @param array|null $ander_open  een ánder seizoen waarvan de aanmelding nu open staat (of null)
 * @param array|null $actief      het seizoen waar het beheer nu mee werkt (actief_seizoen())
 */
function bh_seizoen_gevolgen(string $actie, array $sz, ?array $ander_open = null, ?array $actief = null): array {
    $naam = (string)($sz['naam'] ?? 'dit seizoen');
    $n    = (int)($sz['n'] ?? 0);

    if ($actie === 'open') {
        $regels = [
            'Het aanmeldformulier op de website wordt meteen actief.',
            'Wie zich aanmeldt, komt in ' . $naam . ' terecht.',
        ];
        // Er staat maar één aanmelding tegelijk open: openen sluit de andere. Dat moet je weten
        // vóór je klikt, want daarmee verschuift ook het seizoen waar het beheer mee werkt.
        if ($ander_open) {
            $regels[] = 'De aanmelding van ' . ($ander_open['naam'] ?? 'het andere seizoen') . ' gaat tegelijk dicht: er kan maar één seizoen tegelijk open staan. '
                      . 'Wie daar al in de procedure zit, loopt gewoon door.';
        }
        if ($actief && (int)($actief['id'] ?? 0) !== (int)($sz['id'] ?? 0)) {
            $regels[] = 'Het beheer (Vandaag, Sollicitanten, Selectiedag, Trainingsweekend) werkt daarna met ' . $naam
                      . ' in plaats van ' . ($actief['naam'] ?? 'het huidige seizoen') . '.';
        }
        $wacht = function_exists('seintje_wachtend_aantal') ? seintje_wachtend_aantal() : 0;
        if ($wacht > 0) {
            $regels[] = ($wacht === 1 ? 'Eén persoon liet' : $wacht . ' mensen lieten') . ' via de website een e-mailadres achter voor een seintje. '
                      . 'Stuur ze na het openen een bericht via Berichten › Seintje gevraagd; Vandaag herinnert je eraan.';
        }
        return [
            'titel'  => 'Aanmelding voor ' . $naam . ' openen?',
            'regels' => $regels,
            'bericht'      => 'Controleer eerst of de datums van de selectiedag en het trainingsweekend kloppen — die staan in de bevestigingsmail die elke aanmelder krijgt.',
            'onomkeerbaar' => false,
            'knop'         => 'Ja, aanmelding openen',
            'toon'         => 'primair',
        ];
    }

    if ($actie === 'sluit') {
        return [
            'titel'  => 'Aanmelding voor ' . $naam . ' sluiten?',
            'regels' => [
                'Het aanmeldformulier gaat uit; nieuwe aanmeldingen zijn niet meer mogelijk.',
                'Wie al in de procedure zit, loopt gewoon door: selectiedag, trainingsweekend en aanname werken zoals altijd.',
                'Alle gegevens blijven bewaard en je kunt de aanmelding later weer openen.',
            ],
            'bericht'      => null,
            'onomkeerbaar' => false,
            'knop'         => 'Ja, aanmelding sluiten',
            'toon'         => 'primair',
        ];
    }

    // Verwijderen. Dit is het enige echt onomkeerbare op dit scherm, en het raakt
    // persoonsgegevens: benoem precies wát er weggaat, niet alleen "weet je het zeker".
    $regels = $n > 0
        ? [
            $n . ' ' . ($n === 1 ? 'sollicitatie verdwijnt' : 'sollicitaties verdwijnen') . ' uit dit seizoen.',
            'Daarmee verdwijnen ook de formulieren, de beoordelingen, de beschikbaarheid, de afspraken en de ondertekeningen van die mensen, '
            . 'en de documenten en foto\'s op schijf. In het berichtenlog blijft alleen een geanonimiseerde regel.',
            'Loondossiers die nog niet verwerkt zijn, gaan mee. Een seizoen met verwerkte loondossiers (wettelijke bewaarplicht) kun je niet verwijderen.',
            'Wie alleen in dit seizoen voorkwam, wordt ook als persoon geanonimiseerd (ook een gevraagd seintje voor een volgend seizoen vervalt). '
            . 'Wie ook in een ander seizoen staat, blijft daar staan.',
            'Dit is niet terug te draaien en er is geen prullenbak.',
          ]
        : ['Dit seizoen heeft geen sollicitaties; er gaan geen persoonsgegevens verloren.'];

    return [
        'titel'        => 'Seizoen ' . $naam . ' definitief verwijderen?',
        'regels'       => $regels,
        'bericht'      => $n > 0 ? 'Wil je de gegevens bewaren maar geen aanmeldingen meer? Sluit dan de aanmelding in plaats van te verwijderen. Oude gegevens ruimt het automatisch opschonen op na de bewaartermijn.' : null,
        'onomkeerbaar' => true,
        'knop'         => $n > 0 ? 'Ja, verwijder alles' : 'Ja, verwijderen',
        'toon'         => 'gevaar',
    ];
}

/**
 * Hoeveel sollicitaties in dit seizoen hebben een verwerkt loondossier waar nog iets van
 * over is (bewaarplicht)? Verwerkt = de loonheffing heeft verwerkt_op. Is het loondossier
 * al vernietigd (Loondossiers, na de termijn), dan telt het niet meer: dan is er niets te bewaren.
 * Lukt de telling niet, dan de strenge variant van het dossier (bh_loon_verwerkt): liever
 * weigeren dan iets wissen wat bewaard moest blijven.
 */
function bh_seizoen_loon_bewaard(int $sid): int {
    if ($sid <= 0) return 0;
    $soorten = function_exists('documenten_loonsoorten') ? documenten_loonsoorten() : [];
    $in = $soorten ? implode(',', array_fill(0, count($soorten), '?')) : "''";
    try {
        $q = db()->prepare("SELECT COUNT(DISTINCT s.id) FROM sollicitaties s
                            JOIN ondertekeningen o ON o.sollicitatie_id = s.id AND o.type = 'loonheffing' AND o.verwerkt_op IS NOT NULL
                            WHERE s.seizoen_id = ?
                              AND (o.vertrouwelijk IS NOT NULL OR o.pdf_document_id IS NOT NULL
                                   OR EXISTS (SELECT 1 FROM sollicitant_documenten d WHERE d.sollicitatie_id = s.id
                                              AND d.status IN ('ingediend','verwerkt') AND d.soort IN ($in)))");
        $q->execute(array_merge([$sid], $soorten));
        return (int)$q->fetchColumn();
    } catch (\Throwable $e) {
        if (($e instanceof \PDOException) && (string)$e->getCode() === '42S02') return 0;   // geen ondertekeningen-tabel: niets verwerkt
        $n = 0;
        foreach (bh_seizoen_sollicitatie_ids($sid) as $id) if (function_exists('bh_loon_verwerkt') && bh_loon_verwerkt([$id])) $n++;
        return $n;
    }
}

/** De sollicitatie-id's van een seizoen. */
function bh_seizoen_sollicitatie_ids(int $sid): array {
    try {
        $q = db()->prepare("SELECT id FROM sollicitaties WHERE seizoen_id = ? ORDER BY id");
        $q->execute([$sid]);
        return array_map('intval', array_column($q->fetchAll(), 'id'));
    } catch (\Throwable $e) { return []; }
}

/**
 * Een seizoen verwijderen, met alles wat erbij hoort — via dezelfde AVG-wislaag als het
 * verwijderen van één dossier (bh_dossier_verwijderen): per sollicitatie leegmaken (ook
 * documenten en foto's op schijf) en het berichtenlog anonimiseren, daarna de rijen weg.
 * Personen die daarna nergens meer voorkomen worden geanonimiseerd (avg_personen_zonder_sollicitatie()).
 *
 * Verwerkte loondossiers vallen onder de fiscale bewaarplicht (AVG art. 17 lid 3 sub b): dan
 * weigert dit. Eén dossier kan met de verklaring "dienstverband niet doorgegaan"; voor een heel
 * seizoen, waarin mensen echt gewerkt hebben, bestaat die uitweg bewust niet.
 *
 * @return array{ok:bool,fout:?string,tekst:string,sollicitaties:int}
 */
function bh_seizoen_verwijderen(int $sid): array {
    $uit = ['ok' => false, 'fout' => null, 'tekst' => '', 'sollicitaties' => 0];
    $db = db();
    try {
        $q = $db->prepare("SELECT naam FROM seizoenen WHERE id = ?");
        $q->execute([$sid]);
        $naam = $q->fetchColumn();
    } catch (\Throwable $e) { $naam = false; }
    if ($naam === false) { $uit['fout'] = 'Dit seizoen bestaat niet (meer).'; return $uit; }

    $bewaard = bh_seizoen_loon_bewaard($sid);
    if ($bewaard > 0) {
        $uit['fout'] = 'Niets verwijderd: ' . $naam . ' heeft ' . $bewaard . ' verwerkte '
                     . ($bewaard === 1 ? 'loondossier' : 'loondossiers') . ' met een wettelijke bewaarplicht. '
                     . 'Vernietig die eerst bij Loondossiers als de bewaartermijn voorbij is.';
        return $uit;
    }

    $ids = bh_seizoen_sollicitatie_ids($sid);
    $personen = [];
    try {
        $q = $db->prepare("SELECT DISTINCT persoon_id FROM sollicitaties WHERE seizoen_id = ?");
        $q->execute([$sid]);
        $personen = array_map('intval', array_column($q->fetchAll(), 'persoon_id'));
    } catch (\Throwable $e) {}

    try {
        $db->beginTransaction();
        foreach ($ids as $id) {
            avg_sollicitatie_leegmaken($db, $id, true);   // niet verwerkt: geen bewaarplicht (zie hierboven)
            avg_maillog_anonimiseren($db, $id, 'verwijderd_' . $id . '@anoniem.local');
        }
        // Alles wat aan het seizoen hangt, zonder FK naar seizoenen (anders blijven er wezen staan).
        // Een tabel die (nog) niet bestaat, slaat avg_stap stil over. De afspraakweken gaan mee (FK).
        // Volgorde: eerst wat naar een ander verwijst (planning → scenario, indeling → groep).
        if ($ids) avg_stap($db, "DELETE FROM loon_planning WHERE sollicitatie_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")", $ids);
        // Extra personen in de loonplanning (postcommandant e.d.): hun planning, dan zijzelf.
        avg_stap($db, "DELETE FROM loon_extra_planning WHERE extra_id IN (SELECT id FROM loon_extra_persoon WHERE seizoen_id = ?)", [$sid]);
        foreach (['inzet_afspraak', 'loon_vergrendeling', 'loon_scenario', 'loon_extra_persoon', 'lifeguard_feedback',
                  'selectie_besluit', 'selectie_score', 'selectie_observatie', 'selectie_indeling', 'selectie_relatie', 'selectie_groep',
                  'tw_observatie', 'tw_deelname', 'tw_groep'] as $_t) {
            avg_stap($db, "DELETE FROM `$_t` WHERE seizoen_id = ?", [$sid]);
        }
        $db->prepare("DELETE FROM sollicitaties WHERE seizoen_id = ?")->execute([$sid]);
        $db->prepare("DELETE FROM seizoenen WHERE id = ?")->execute([$sid]);
        $geanonimiseerd = function_exists('avg_personen_zonder_sollicitatie') ? avg_personen_zonder_sollicitatie($db, $personen) : 0;
        $db->commit();
        if (function_exists('avg_na_commit')) avg_na_commit();
    } catch (\Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        if (function_exists('avg_na_rollback')) avg_na_rollback();
        error_log('bh_seizoen_verwijderen ' . $sid . ': ' . $e->getMessage());
        $uit['fout'] = 'Verwijderen is niet gelukt; er is niets uit de database gewist. De fout staat in het logboek.';
        return $uit;
    }

    // Buiten de transactie, faalt stil (zelfde als bh_dossier_verwijderen).
    foreach ($ids as $id) {
        if (function_exists('portaal_onthoud_intrekken_alle')) portaal_onthoud_intrekken_alle($id);
        if (function_exists('portaal_sollicitant_cache_wissen')) portaal_sollicitant_cache_wissen($id);
    }
    if (function_exists('audit_log')) {
        audit_log('seizoen_verwijderd', 'seizoen', $sid,
                  ['naam' => $naam, 'sollicitaties' => count($ids), 'personen_geanonimiseerd' => $geanonimiseerd]);
    }
    $uit['ok'] = true;
    $uit['sollicitaties'] = count($ids);
    $uit['tekst'] = 'Seizoen "' . $naam . '" en alle bijbehorende gegevens zijn verwijderd'
                  . (count($ids) ? ' (' . count($ids) . ' ' . (count($ids) === 1 ? 'sollicitatie' : 'sollicitaties') . ').' : '.');
    return $uit;
}

} // function_exists
