<?php
/**
 * Paal 13 — berichten: van een logregel naar een mededeling.
 *
 * Geen HTML. Dit bestand beantwoordt één vraag per regel: wat is ermee gebeurd, en
 * waarom? Het ontwerp is daar expliciet over — "reden altijd in woorden". Een kolom
 * met het woord "mislukt" en verder niets laat het bestuur zelf raden, en dan wordt
 * er niet gebeld terwijl dat wel moest.
 *
 * De ruwe foutmelding gaat nooit verloren: staat er iets dat we niet herkennen, dan
 * tonen we die tekst letterlijk. Liever een technische zin dan een lege cel.
 */

if (!function_exists('bh_kanaal_naam')) {

/** Kanaalnaam zoals een mens hem zou zeggen. */
function bh_kanaal_naam(?string $k): string {
    return ['email' => 'E-mail', 'whatsapp' => 'WhatsApp', 'sms' => 'sms'][(string)$k] ?? (string)$k;
}

/**
 * Het kanaal van één regel, inclusief de terugval: "WhatsApp → e-mail".
 * Dat pijltje is informatie: het bericht is wél aangekomen, alleen anders.
 */
function bh_bericht_kanaal(array $rij): string {
    $nu = bh_kanaal_naam($rij['kanaal'] ?? null);
    $van = (string)($rij['fallback_van'] ?? '');
    return $van !== '' ? bh_kanaal_naam($van) . ' → ' . strtolower($nu) : $nu;
}

/**
 * Vertaal de foutmelding van de mailer of de WhatsApp-API naar één zin.
 * Herkent de gevallen die in de praktijk voorkomen; de rest gaat letterlijk door.
 */
function bh_bericht_reden_tekst(?string $ruw): string {
    $r = trim((string)$ruw);
    if ($r === '') return '';
    $lc = strtolower($r);
    $regels = [
        'Adres bestaat niet'                  => ['no such user', 'user unknown', 'mailbox unavailable', 'address rejected', '550 5.1.1', 'recipient not found'],
        'Postbus vol'                         => ['mailbox full', 'over quota', 'insufficient storage', '552'],
        'Geweigerd als spam'                  => ['spam', 'blocked', 'blacklist', 'reputation'],
        'Geen WhatsApp op dit nummer'         => ['not a whatsapp', 'no whatsapp', 'invalid wa', 'not registered'],
        'Telefoonnummer klopt niet'           => ['invalid number', 'invalid phone', 'bad number'],
        'Geen e-mailadres bekend'             => ['geen e-mail', 'geen email', 'no recipient', 'lege ontvanger'],
        'Sollicitant heeft zich afgemeld'     => ['afgemeld', 'opt-out', 'unsubscribed'],
        'Dagplafond bereikt'                  => ['plafond', 'daglimiet', 'rate limit', 'too many'],
        'Verbinding met de mailserver mislukt' => ['connection', 'timed out', 'timeout', 'could not connect', 'smtp'],
    ];
    foreach ($regels as $zin => $sleutels) {
        foreach ($sleutels as $s) if (str_contains($lc, $s)) return $zin;
    }
    return $r;
}

/**
 * De stand van één logregel: welk woord staat er, in welke kleur, en waarom.
 *
 * @return array woord:string · stand:'goed'|'krap'|'slecht'|'neutraal' · kleur (fasekleur) ·
 *               reden:string (mag leeg zijn bij een gewone verzending) · icoon:string
 */
function bh_bericht_stand(array $rij): array {
    $status = (string)($rij['status'] ?? '');
    $reden  = bh_bericht_reden_tekst($rij['foutmelding'] ?? null);
    $terug  = trim((string)($rij['fallback_van'] ?? '')) !== '';

    if ($status === 'verstuurd' && $terug) {
        return ['woord' => 'Teruggevallen', 'stand' => 'krap', 'kleur' => 'oranje', 'icoon' => 'wissel',
                'reden' => $reden !== '' ? $reden : 'Het eerste kanaal lukte niet; per ' . strtolower(bh_kanaal_naam($rij['kanaal'] ?? null)) . ' wél'];
    }
    if ($status === 'verstuurd') {
        return ['woord' => 'Verstuurd', 'stand' => 'goed', 'kleur' => 'groen', 'icoon' => 'vink', 'reden' => $reden];
    }
    if ($status === 'mislukt') {
        return ['woord' => 'Mislukt', 'stand' => 'slecht', 'kleur' => 'rood', 'icoon' => 'kruis',
                'reden' => $reden !== '' ? $reden : 'Reden onbekend — zie het logboek'];
    }
    if ($status === 'overgeslagen') {
        return ['woord' => 'Overgeslagen', 'stand' => 'neutraal', 'kleur' => 'grijs', 'icoon' => 'punt',
                'reden' => $reden !== '' ? $reden : 'Bewust niet verstuurd'];
    }
    if ($status === 'wacht' || $status === 'bezig') {
        return ['woord' => 'In de wachtrij', 'stand' => 'neutraal', 'kleur' => 'blauw', 'icoon' => 'klok',
                'reden' => $reden !== '' ? $reden : 'Wordt de komende minuten verstuurd'];
    }
    return ['woord' => $status !== '' ? $status : 'Onbekend', 'stand' => 'neutraal', 'kleur' => 'grijs',
            'icoon' => 'punt', 'reden' => $reden];
}

/**
 * Is er bij deze regel iets te dóen? Zo ja: wat, en waar.
 * Een mislukt adres corrigeer je in het dossier — daar staat het veld.
 *
 * @return ?array ['tekst' => 'Corrigeer', 'url' => '…']
 */
function bh_bericht_actie(array $rij): ?array {
    if (($rij['status'] ?? '') !== 'mislukt') return null;
    $sol = (int)($rij['sollicitatie_id'] ?? 0);
    if (!$sol) return null;
    return ['tekst' => 'Gegevens corrigeren',
            'url'   => bh_gegevens_url($sol)];   // de kaart Gegevens corrigeren, opengeklapt
}

/**
 * De uitslag van een verzendronde in één zin.
 *
 * Bewust niet "12 verstuurd" als er ook drie mislukt zijn: dan leest iemand alleen het
 * eerste getal en gaat verder. Wat misging staat vooraan.
 */
function bh_verzend_zin(array $r): array {
    $mislukt = (int)($r['mislukt'] ?? 0);
    $over    = (int)($r['overgeslagen'] ?? 0);
    $ok      = (int)($r['ok'] ?? 0);
    $wacht   = (int)($r['wacht'] ?? 0);
    $goed    = $ok + $wacht;

    $bericht = !empty($r['via_queue'])
        ? $goed . ' ' . ($goed === 1 ? 'bericht staat' : 'berichten staan') . ' in de wachtrij'
        : $ok . ' ' . ($ok === 1 ? 'bericht' : 'berichten') . ' verstuurd';

    if ($mislukt > 0) {
        return ['slecht', $mislukt . ' mislukt, ' . $bericht . ($over ? ', ' . $over . ' overgeslagen' : '')];
    }
    if ($over > 0) {
        return ['krap', $over . ' overgeslagen, ' . $bericht];
    }
    return ['goed', $bericht];
}

/**
 * Mensen uit vorige seizoenen die we een wervingsbericht mógen sturen.
 *
 * Twee groepen, en alleen die twee:
 *   'oud'     — ooit aangenomen: gerechtvaardigd belang (werk-/lidmaatschapsrelatie).
 *   'seintje' — afgewezen of afgehaakt, maar zelf om een seintje gevraagd, en die
 *               toestemming is nog geen seintje_termijn_mnd() oud (includes/seintje.php).
 *               Plus wie buiten het seizoen via de website om een seintje vroeg en dat
 *               bevestigde (includes/seintje_werving.php): die rijen hebben sol_id = -id,
 *               persoon_id 0 en 'werving_seintje_id'; verzenden via seintje_werving_verstuur().
 * Wie zich heeft afgemeld, is geanonimiseerd, zijn gegevens liet verwijderen of zich dit
 * seizoen al heeft aangemeld, valt er vanzelf af. Afgewezen zónder seintje komt er nooit
 * in: daar is geen grondslag voor (AVG art. 6, Telecommunicatiewet art. 11.7).
 *
 * Verzonden wordt op de laatste sollicitatie van de persoon (sol_id): die draagt het
 * afmeld-token en de gegevens. Het seizoen van die sollicitatie is dus een oud seizoen;
 * berichten.php zet {{seizoen_naam}} daarom zelf op het huidige.
 *
 * @return array rijen: sol_id, persoon_id, voornaam, tussenvoegsel, achternaam, email,
 *               telefoon, kanaal_voorkeur, groep ('oud'|'seintje'), laatste_seizoen
 */
function bh_vorige_seizoenen(int $huidig_seizoen_id): array {
    if (function_exists('seintje_auto_setup')) seintje_auto_setup();
    if (function_exists('optout_auto_setup')) optout_auto_setup();
    if (function_exists('personen_kanaal_auto_setup')) personen_kanaal_auto_setup();
    $mnd = function_exists('seintje_termijn_mnd') ? seintje_termijn_mnd() : 12;
    $rijen = [];
    try {
        $q = db()->prepare("
            SELECT a.sol_id, p.id AS persoon_id, p.voornaam, p.tussenvoegsel, p.achternaam, p.email, p.telefoon,
                   COALESCE(p.kanaal_voorkeur,'whatsapp') AS kanaal_voorkeur,
                   IF(a.ooit_aangenomen = 1, 'oud', 'seintje') AS groep,
                   (SELECT sz.naam FROM sollicitaties s3 JOIN seizoenen sz ON sz.id = s3.seizoen_id
                     WHERE s3.id = a.sol_id) AS laatste_seizoen
            FROM personen p
            JOIN (SELECT persoon_id, MAX(id) AS sol_id, MAX(fase = 'aangenomen') AS ooit_aangenomen
                    FROM sollicitaties
                   WHERE seizoen_id <> ? AND fase <> 'verwijderd_door_sollicitant'
                   GROUP BY persoon_id) a ON a.persoon_id = p.id
            WHERE COALESCE(p.mailing_afgemeld, 0) = 0
              AND p.email NOT LIKE 'geanonimiseerd+%' AND p.email NOT LIKE '%@anoniem.local'
              AND (a.ooit_aangenomen = 1
                   OR (p.seintje_op IS NOT NULL AND p.seintje_op >= DATE_SUB(NOW(), INTERVAL ? MONTH)))
              AND NOT EXISTS (SELECT 1 FROM sollicitaties s2 WHERE s2.persoon_id = p.id AND s2.seizoen_id = ?)
            ORDER BY p.achternaam, p.voornaam");
        $q->execute([$huidig_seizoen_id, $mnd, $huidig_seizoen_id]);
        $rijen = $q->fetchAll();
    } catch (\Throwable $e) {
        error_log('bh_vorige_seizoenen: ' . $e->getMessage());
    }
    // Plus wie buiten het seizoen via de website om een seintje vroeg en dat bevestigde
    // (includes/seintje_werving.php). Ontdubbeld op e-mailadres: wie hierboven al staat,
    // krijgt het bericht via die rij. Die rijen hebben een negatief sol_id.
    if (function_exists('seintje_werving_doelgroep')) {
        $rijen = array_merge($rijen, seintje_werving_doelgroep($huidig_seizoen_id, array_column($rijen, 'email')));
    }
    return $rijen;
}

} // function_exists
