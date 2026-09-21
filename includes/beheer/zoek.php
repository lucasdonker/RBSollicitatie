<?php
/**
 * Paal 13 — zoeken door het beheer. GEEN HTML.
 *
 * Eén bron voor twee zoekvelden die er anders uitzien maar hetzelfde weten:
 *   - de snelzoeker in de topbalk (Ctrl+K) — mensen én schermen
 *   - het filterveld op instellingen.php — alleen de tabbladen
 *
 * Zonder deze laag zou de tweede een kopie van de eerste worden, en dan vindt de ene
 * "vakantietoeslag" wel en de andere niet. Daarom staat de tabbladenlijst van
 * instellingen.php hier: bh_inst_tabs() is de enige plek waar hij bestaat.
 *
 * Rechten: bh_zoek() filtert op mag(). Iets wat je niet mag zien hoort ook niet in de
 * zoekresultaten te staan — anders is de zoeker een manier om te zien wát er bestaat
 * (ontwerpfilosofie §10: geen recht is wég, niet uitgegrijsd).
 */

if (!function_exists('bh_inst_tabs')) {

/**
 * De tabbladen van instellingen.php: groep => [ [id, label, icoonnaam, zoektermen] ].
 *
 * De zoektermen zijn de woorden waarmee iemand zoekt maar die niet in het tablabel
 * staan — "vakantietoeslag" hoort bij "Lonen & budget", "sso" bij "Authentik". Dat is
 * de hele reden dat dit een lijst is en geen tekstvergelijking op het label.
 */
function bh_inst_tabs(): array {
    return [
      'Organisatie' => [
        ['algemeen',    'Algemeen',                  'instellingen', 'brigade naam logo contact adres portaal url tijdzone leeftijd minimumleeftijd'],
      ],
      'Selectie & seizoen' => [
        ['uitnodiging', 'Uitnodigen & automatisch',  'bliksem',      'automatisch uitnodigen herinnering deadline aanmeldstop drempel'],
        ['selectie',    'Beoordelingscriteria',      'vink',         'criteria competenties beoordelen cijfer weging selectiedag'],
        ['bezetting',   'Posten & bezetting',        'schild',       'post strandpost bezetting diensten minimale dekking'],
      ],
      'Inzet & loon' => [
        ['functies',    'Functies',                  'mensen',       'functie rol strandwacht niveau diploma'],
        ['loon',        'Lonen & budget',            'grafiek',      'uurloon salaris budget vakantietoeslag werkuren leeftijdsschaal loonheffing'],
      ],
      'Berichten' => [
        ['berichten',   'Kanalen & meldingen',       'chat',         'whatsapp sms kanaal melding opt-in plafond whatsreach'],
        ['mail',        'Afzender & adressen',       'mail',         'afzender antwoordadres reply-to bestuur bcc e-mailadres'],
        ['smtp',        'E-mailserver',              'systeem',      'smtp mailserver poort wachtwoord tls verbinding'],
      ],
      'Koppelingen' => [
        ['authentik',   'Authentik',                 'schild',       'sso oidc single sign-on inloggen client secret'],
        ['api',         'Website & API',             'koppeling',    'api sleutel key wordpress webhook aanmeldformulier plugin'],
        ['ai',          'AI-analyse',                'ster',         'ai analyse model api key beschikbaarheid wizard'],
      ],
      'Privacy & onderhoud' => [
        ['bewaren',     'Bewaren & opschonen',       'klok',         'bewaartermijn avg opschonen verwijderen cron privacy gdpr'],
      ],
    ];
}

/**
 * Alle plekken waar je heen kunt, als platte lijst voor de zoeker.
 *
 * @param bool $alles  true = niet op rechten filteren
 * @return array<array{titel:string,waar:string,url:string,icoon:string,termen:string}>
 */
function bh_zoek_plekken(bool $alles = false): array {
    $basis = APP_URL . '/admin/';
    $uit   = [];

    $mag_zien = function (?string $module) use ($alles): bool {
        if ($alles || $module === null) return true;
        return !function_exists('mag') || mag($module, 'lezen');
    };

    // 1. De schermen uit de navigatie — bh_nav_groepen() filtert zelf al op rechten.
    $nav = bh_nav_groepen();
    foreach ($nav['los'] as $i) {
        $uit[] = ['titel' => $i['label'], 'waar' => 'Scherm', 'url' => $i['url'],
                  'icoon' => $i['icoon'], 'termen' => 'dashboard start vandaag overzicht'];
    }
    foreach ($nav['groepen'] as $groep => $items) {
        foreach ($items as $i) {
            $uit[] = ['titel' => $i['label'], 'waar' => $groep, 'url' => $i['url'],
                      'icoon' => $i['icoon'], 'termen' => ''];
        }
    }

    // 2. Schermen die geen eigen nav-item hebben maar wel gezocht worden.
    $extra = [
        ['Sjablonen voor mails', 'mail_templates.php',     'document', 'mailing', 'sjabloon template mail tekst bericht opmaak'],
        ['Geplande berichten',   'geplande_berichten.php', 'klok',     'mailing', 'gepland wachtrij verzending uitgesteld'],
        ['Nieuwe sollicitant',   'sollicitant_nieuw.php',  'plus',     'sollicitanten', 'toevoegen handmatig aanmelden nieuw'],
        ['Sollicitanten importeren', 'import.php',         'upload',   'sollicitanten', 'import csv excel bulk inlezen'],
        ['Rollen & rechten',     'rollen.php',             'sleutel',  'beheerders', 'rol recht module machtiging toegang'],
        ['Systeem & updates',    'systeem.php',            'systeem',  'instellingen', 'update versie server status onderhoud'],
        ['Handleiding',          'handleiding.php',        'document', null, 'handleiding uitleg help hulp documentatie'],
        ['Mijn profiel',         'profiel.php',            'persoon',  null, 'profiel wachtwoord eigen account thema donker'],
    ];
    foreach ($extra as $e) {
        if (!$mag_zien($e[3])) continue;
        $uit[] = ['titel' => $e[0], 'waar' => 'Scherm', 'url' => $basis . $e[1],
                  'icoon' => $e[2], 'termen' => $e[4]];
    }

    // 3. De instellingen-tabbladen.
    if ($mag_zien('instellingen')) {
        foreach (bh_inst_tabs() as $groep => $items) {
            foreach ($items as $t) {
                $uit[] = ['titel' => $t[1], 'waar' => 'Instellingen · ' . $groep,
                          'url' => $basis . 'instellingen.php?tab=' . $t[0],
                          'icoon' => $t[2], 'termen' => $t[3]];
            }
        }
    }

    return $uit;
}

/**
 * Mensen zoeken op naam of e-mailadres, over alle seizoenen.
 *
 * Bewust niet beperkt tot het actieve seizoen: "dat ene formulier van vorig jaar" is
 * precies waarvoor je zoekt. Het seizoen staat er wél bij, en het actieve komt eerst,
 * zodat je niet per ongeluk een oud dossier opent.
 *
 * @return array<array{id:int,naam:string,email:string,fase:string,seizoen:string,huidig:bool}>
 */
function bh_zoek_mensen(string $term, int $max = 6): array {
    $term = trim($term);
    if ($term === '' || mb_strlen($term) < 2) return [];
    if (function_exists('mag') && !mag('sollicitanten', 'lezen')) return [];

    $sz  = function_exists('actief_seizoen') ? actief_seizoen() : null;
    $sid = (int)($sz['id'] ?? 0);
    $p   = '%' . $term . '%';

    try {
        $st = db()->prepare(
            "SELECT s.id, s.fase, p.voornaam, p.achternaam, p.email,
                    COALESCE(sz.naam,'') AS seizoen, s.seizoen_id
             FROM sollicitaties s
             JOIN personen p  ON p.id = s.persoon_id
             LEFT JOIN seizoenen sz ON sz.id = s.seizoen_id
             WHERE p.voornaam LIKE ? OR p.achternaam LIKE ? OR p.email LIKE ?
                OR CONCAT(p.voornaam,' ',p.achternaam) LIKE ?
             ORDER BY (s.seizoen_id = ?) DESC, s.id DESC
             LIMIT " . (int)$max
        );
        $st->execute([$p, $p, $p, $p, $sid]);
        $rijen = $st->fetchAll() ?: [];
    } catch (\Throwable $e) {
        return [];
    }

    $uit = [];
    foreach ($rijen as $r) {
        $uit[] = [
            'id'      => (int)$r['id'],
            'naam'    => trim((string)$r['voornaam'] . ' ' . (string)$r['achternaam']),
            'email'   => (string)$r['email'],
            'fase'    => function_exists('fase_label_kort') ? fase_label_kort((string)$r['fase']) : (string)$r['fase'],
            'seizoen' => (string)$r['seizoen'],
            'huidig'  => (int)$r['seizoen_id'] === $sid,
        ];
    }
    return $uit;
}

/**
 * Plekken zoeken. Vergelijkt tegen titel + groep + zoektermen, allemaal kleingeletterd.
 *
 * @return array<array{titel:string,waar:string,url:string,icoon:string}>
 */
function bh_zoek_plaatsen(string $term, int $max = 6): array {
    $term = mb_strtolower(trim($term));
    if ($term === '') return [];

    $uit = [];
    foreach (bh_zoek_plekken() as $p) {
        $hooi = mb_strtolower($p['titel'] . ' ' . $p['waar'] . ' ' . $p['termen']);
        $pos  = mb_strpos($hooi, $term);
        if ($pos === false) continue;
        // Een treffer in de titel weegt zwaarder dan een treffer in de zoekwoorden:
        // wie "loon" typt wil "Lonen & budget" bovenaan, niet "Loonheffing" ergens.
        $p['rang'] = mb_strpos(mb_strtolower($p['titel']), $term) !== false ? 0 : 1;
        unset($p['termen']);
        $uit[] = $p;
    }
    usort($uit, fn($a, $b) => $a['rang'] <=> $b['rang']);
    return array_slice(array_map(function ($p) { unset($p['rang']); return $p; }, $uit), 0, $max);
}

/** Beide soorten in één antwoord, voor de snelzoeker. */
function bh_zoek(string $term): array {
    return [
        'mensen'  => bh_zoek_mensen($term),
        'plekken' => bh_zoek_plaatsen($term),
    ];
}

} // function_exists
