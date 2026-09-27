<?php
/**
 * Logboek — labels, iconen en leesbare details.
 *
 * De code schrijft slugs (fase_gewijzigd, document_gedownload, …); hier worden dat Nederlandse
 * zinnen met een lijn-icoon uit includes/iconen.php. Geen emoji — dat is de huisregel, en het
 * oude auditscherm overtrad hem.
 *
 * Een slug die hier niet in staat verdwijnt NIET: die krijgt een nette afleiding. Zo blijft een
 * nieuwe actie in de code altijd zichtbaar, ook als niemand eraan denkt dit register bij te werken.
 */

if (!function_exists('log_actie_label')) {

/**
 * Het register: slug => [label, icoonnaam].
 * Gegroepeerd zoals de code ze schrijft; zie de aanroepen van audit_log() door de codebase heen.
 */
function log_actie_register(): array {
    return [
        // Algemeen
        'aangemaakt'      => ['Aangemaakt', 'plus'],
        'gewijzigd'       => ['Gewijzigd', 'potlood'],
        'verwijderd'      => ['Verwijderd', 'prullenbak'],
        'geexporteerd'    => ['Geëxporteerd', 'download'],
        'bekeken'         => ['Bekeken', 'oog'],
        'gemigreerd'      => ['Gemigreerd', 'wissel'],

        // Toegang
        'ingelogd'        => ['Ingelogd', 'sleutel'],
        'uitgelogd'       => ['Uitgelogd', 'uitloggen'],
        'login_mislukt'   => ['Inloggen mislukt', 'waarschuwing'],
        'sso_geweigerd'   => ['Inloggen via Authentik geweigerd (geen toegestane groep)', 'slot'],
        'geweigerd'       => ['Geweigerd', 'waarschuwing'],
        'gewisseld'       => ['Gewisseld van omgeving', 'wissel'],
        'rol_gewijzigd'   => ['Rechten van een rol gewijzigd', 'slot'],
        'rol_aangemaakt'  => ['Rol aangemaakt', 'slot'],
        'rol_verwijderd'  => ['Rol verwijderd', 'slot'],
        'beheerder_gewijzigd'   => ['Beheerder gewijzigd', 'persoon'],
        'beheerder_aangemaakt'  => ['Beheerder toegevoegd', 'persoon'],
        'beheerder_verwijderd'  => ['Beheerder verwijderd', 'persoon'],
        'tweestaps_aangezet'    => ['Tweestapsverificatie aangezet', 'schild'],
        'tweestaps_uitgezet'    => ['Tweestapsverificatie uitgezet', 'slot'],
        'tweestaps_gereset'     => ['Tweestapsverificatie gereset door een beheerder', 'slot'],
        'tweestaps_mislukt'     => ['Tweestapscode fout', 'waarschuwing'],
        'tweestaps_herstelcode' => ['Herstelcode gebruikt om in te loggen', 'sleutel'],
        'tweestaps_mailcode'    => ['Inlogcode beheer per e-mail verstuurd', 'mail'],

        // Sollicitatie
        'fase_gewijzigd'          => ['Fase gewijzigd', 'pijl-rechts'],
        'sollicitatie_verwijderd' => ['Sollicitatie verwijderd', 'prullenbak'],
        'blk_ingediend'           => ['BLK ingeleverd', 'document'],
        'blk_gewijzigd'           => ['BLK gewijzigd', 'document'],

        // Beschikbaarheid
        'beschikbaarheid_ingevuld'   => ['Beschikbaarheid ingevuld', 'kalender'],
        'beschikbaarheid_definitief' => ['Beschikbaarheid definitief gemaakt', 'kalender'],

        // Ondertekenen (audit + bewijsspoor)
        'ondertekening_afgerond'    => ['Ondertekening afgerond', 'document'],
        'lidmaatschap_overgeslagen' => ['Lidmaatschap overgeslagen', 'info'],
        'zegel_certificaat_aangemaakt' => ['Zegelcertificaat aangemaakt', 'slot'],
        'aangemaakt_ondertekening'  => ['Klaargezet', 'document'],
        'geopend'                   => ['Geopend', 'oog'],
        'code_verzonden'            => ['Verificatiecode verstuurd', 'telefoon'],
        'code_fout'                 => ['Verificatiecode fout', 'waarschuwing'],
        'code_ok'                   => ['Verificatiecode goed', 'vink'],
        'ondertekend'               => ['Ondertekend', 'vink-cirkel'],
        'pdf_verzegeld'             => ['PDF verzegeld', 'slot'],
        'pdf_gedownload'            => ['PDF gedownload', 'download'],
        'verwerkt'                  => ['Verwerkt', 'vink'],
        'penningmeester_gemeld'     => ['Penningmeester gemeld', 'mail'],
        'verstuurd_ledenservice'    => ['Naar ledenservice gestuurd', 'mail'],

        // Documenten en kluis
        'document_geupload'   => ['Document geüpload', 'document'],
        'document_gedownload' => ['Document gedownload', 'download'],
        'document_verwijderd' => ['Document verwijderd', 'prullenbak'],
        'document_vernietigd' => ['Document vernietigd', 'prullenbak'],
        'loondossier_verwerkt'=> ['Loondossier verwerkt', 'vink'],
        'gegevens_gecorrigeerd'  => ['Gegevens gecorrigeerd', 'potlood'],
        'persoon_gewist_avg'     => ['Alle gegevens gewist (AVG-verzoek)', 'prullenbak'],
        'loondossier_vernietigd' =>['Loondossier vernietigd (na de bewaarplicht)', 'prullenbak'],
        'bijlage_geupload'    => ['Bijlage geüpload', 'document'],
        'bijlage_gedownload'  => ['Bijlage gedownload', 'download'],

        // Berichten
        'melding'           => ['Melding verstuurd', 'mail'],
        'bericht'           => ['Bericht verstuurd', 'mail'],
        'bericht_verstuurd' => ['Bericht verstuurd', 'mail'],
        'kanaal_voorkeur'   => ['Kanaalvoorkeur gewijzigd', 'telefoon'],
        'afgemeld'          => ['Afgemeld voor berichten', 'kruis-cirkel'],
        'seintje_gevraagd'    => ['Seintje gevraagd voor de volgende werving', 'bel'],
        'claim_overgenomen'   => ['Gesprek overgenomen van een andere beheerder', 'wissel'],
        'seintje_ingetrokken' => ['Seintje ingetrokken', 'kruis-cirkel'],

        // Sollicitatie intrekken (includes/intrekken.php)
        'intrekverzoek'              => ['Vroeg de sollicitatie in te trekken', 'waarschuwing'],
        'intrekverzoek_geannuleerd'  => ['Verzoek om in te trekken geannuleerd', 'kruis-cirkel'],
        'intrekverzoek_doorgevoerd'  => ['Intrekken doorgevoerd (afgehaakt)', 'vink'],
        'intrekverzoek_afgewezen'    => ['Intrekken niet doorgevoerd, loopt door', 'info'],

        // Selectiedag en trainingsweekend (2.117 e.v.)
        'tw_evaluatie'               => ['Eerste indruk trainingsweekend vastgelegd', 'vink'],
        'lg_feedback_vastgelegd'     => ['Feedback van een lifeguard vastgelegd', 'chat'],
        'lg_feedback_gewist'         => ['Feedback van een lifeguard verwijderd', 'prullenbak'],
        'loonplan_opgave_overgenomen'=> ['Opgave overgenomen in de loonplanning', 'kalender'],
        'herinneringen_gedraaid' => ['Herinneringen gedraaid', 'klok'],

        // Landingspagina's
        'versie_geupload'     => ['Ontwerp geüpload', 'document'],
        'versie_geactiveerd'  => ['Ontwerp geactiveerd', 'vink'],
        'asset_toegevoegd'    => ['Bestand toegevoegd', 'plus'],
        'asset_verwijderd'    => ['Bestand verwijderd', 'prullenbak'],
        'scan_bevestigd'      => ['Scanrapport bevestigd', 'schild'],
        'csp_host_toegestaan' => ['Externe host toegestaan', 'schild'],

        // AVG-opschoning (categorieën uit opschoon_log)
        'afgewezen_geanonimiseerd'  => ['Afgewezen sollicitaties geanonimiseerd', 'slot'],
        'aangenomen_geanonimiseerd' => ['Oud-lifeguards geanonimiseerd', 'slot'],
        'wachtrij_opgeschoond'      => ['Berichtenwachtrij opgeschoond', 'slot'],
        'foutenlog_verwijderd'      => ['Foutenlog opgeschoond', 'slot'],
        'gezondheid_verwijderd'     => ['Gezondheidsgegevens verwijderd', 'slot'],
        'ai_invoer_verwijderd'      => ['AI-invoer verwijderd', 'slot'],
        'onvoltooid_verwijderd'     => ['Onvoltooide aanmeldingen verwijderd', 'slot'],
        'berichtinhoud_geleegd'     => ['Berichtinhoud geleegd', 'slot'],
        'bijlagen_verwijderd'       => ['Bijlagen verwijderd', 'slot'],
        'maillog_verwijderd'        => ['Berichtenlog opgeschoond', 'slot'],
        'auditlog_verwijderd'       => ['Auditlog opgeschoond', 'slot'],
        'pagina_hash_gewist'        => ['Bezoekerspseudoniemen gewist', 'slot'],
        'pagina_events_verwijderd'  => ['Paginastatistiek verwijderd', 'slot'],
        'loondocumenten_vernietigd' => ['Loondocumenten vernietigd', 'slot'],   // oude regels; sinds 2.100 handmatig
        'bankpas_vernietigd'        => ['Kopie bankpas vernietigd', 'slot'],
    ];
}

/** Leesbaar label voor een actie-slug. Onbekend → nette afleiding, nooit leeg. */
function log_actie_label(?string $slug): string {
    $slug = (string)$slug;
    if ($slug === '') return '—';
    $r = log_actie_register();
    if (isset($r[$slug])) return $r[$slug][0];
    // Onbekend (bijv. een foutcode als E_WARNING, of een nieuwe actie): maak er tekst van.
    return ucfirst(str_replace('_', ' ', $slug));
}

/** Icoonnaam voor een actie-slug; valt terug op een neutraal bolletje. */
function log_actie_icoon(?string $slug): string {
    $r = log_actie_register();
    return $r[(string)$slug][1] ?? 'punt';
}

/** De actor, met een badge-kleur per soort. */
function log_wie_soorten(): array {
    return [
        'beheerder'   => ['Beheerder', 'badge-blauw'],
        'sollicitant' => ['Sollicitant', 'badge-teal'],
        'systeem'     => ['Systeem', 'badge-grijs'],
        'onbekend'    => ['Onbekend', 'badge-oranje'],
    ];
}

/** Naam van de actor, of een nette terugval op het type. */
function log_wie_naam(array $r): string {
    $naam = trim((string)($r['wie_naam'] ?? ''));
    if ($naam !== '') return $naam;
    if (!empty($r['wie_id'])) return '#' . (int)$r['wie_id'];
    $s = log_wie_soorten();
    return $s[$r['wie_type'] ?? 'systeem'][0] ?? 'Systeem';
}

/** Badge-klasse voor het actortype. */
function log_wie_klasse(?string $type): string {
    $s = log_wie_soorten();
    return $s[(string)$type][1] ?? 'badge-grijs';
}

/** Badge-klasse per ernst. */
function log_ernst_klasse(?string $ernst): string {
    if ($ernst === 'fout') return 'badge-rood';
    if ($ernst === 'waarschuwing') return 'badge-oranje';
    return 'badge-grijs';
}

/**
 * Details leesbaar maken. De auditlog schrijft JSON ('veld' => ['oud'=>.., 'nieuw'=>..]),
 * de andere bronnen leveren platte tekst. Beide worden hier één lijst met regels.
 *
 * @return array regels als platte tekst — de aanroeper zorgt zelf voor h()
 */
function log_details_regels(?string $details): array {
    $details = trim((string)$details);
    if ($details === '') return [];

    $d = json_decode($details, true);
    if (!is_array($d)) return [$details];  // platte tekst (fouten, berichten, opschoning)

    $regels = [];
    foreach ($d as $sleutel => $waarde) {
        $naam = ucfirst(str_replace('_', ' ', (string)$sleutel));
        if (is_array($waarde) && array_key_exists('oud', $waarde) && array_key_exists('nieuw', $waarde)) {
            $regels[] = $naam . ': ' . log_waarde_tekst($waarde['oud']) . ' → ' . log_waarde_tekst($waarde['nieuw']);
        } else {
            $regels[] = $naam . ': ' . log_waarde_tekst($waarde);
        }
    }
    return $regels;
}

/** Eén waarde uit de details als tekst. */
function log_waarde_tekst($v): string {
    if ($v === null)  return 'leeg';
    if ($v === true)  return 'ja';
    if ($v === false) return 'nee';
    if (is_scalar($v)) {
        $t = (string)$v;
        return $t === '' ? 'leeg' : $t;
    }
    return (string)json_encode($v, JSON_UNESCAPED_UNICODE);
}

/** Het object waar de regel over gaat, als korte tekst. */
function log_object_tekst(array $r): string {
    $type = trim((string)($r['objecttype'] ?? ''));
    if ($type === '') return '';
    $label = ucfirst(str_replace('_', ' ', $type));
    return !empty($r['object_id']) ? $label . ' #' . (int)$r['object_id'] : $label;
}

/** Datum/tijd zoals het elders in het beheer wordt getoond. */
function log_moment_tekst(?string $moment): string {
    if (!$moment) return '';
    $t = strtotime($moment);
    return $t ? date('d-m-Y H:i', $t) : (string)$moment;
}

}
