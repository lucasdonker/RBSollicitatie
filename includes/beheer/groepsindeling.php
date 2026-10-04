<?php
/**
 * Beheer — de handelingen van het indelingsscherm (admin/_groepsindeling.php), voor de
 * selectiedag én het trainingsweekend. GEEN HTML: flash + redirect, of JSON voor slepen
 * en "niet samen met".
 *
 * Het scherm roept bh_groepsindeling_post() aan in zijn POST-tak, ná csrf_check() en de
 * rechtencheck. Onbekende acties laten de functie stil terugkeren (false), zodat het scherm
 * zijn eigen acties kan blijven afhandelen.
 */

if (!function_exists('bh_groepsindeling_acties')) {

/** De acties die dit scherm kent. */
function bh_groepsindeling_acties(): array {
    return ['aantal_groepen', 'voorstel', 'verplaats', 'relatie'];
}

/**
 * Handel één POST af en stop (redirect of JSON). Retourneert false als de actie niet van het
 * indelingsscherm is.
 *
 * @param string   $ev          'selectiedag' | 'tw'
 * @param array    $kandidaten  selectie_kandidaten() / tw_kandidaten() — voor het voorstel en de controle
 * @param string   $terug       URL van het scherm (zonder #anker)
 */
function bh_groepsindeling_post(string $ev, int $seizoen_id, array $kandidaten, string $terug): bool {
    $actie = (string)($_POST['actie'] ?? '');
    if (!in_array($actie, bh_groepsindeling_acties(), true)) return false;
    $ids = array_map(fn($k) => (int)$k['sol_id'], $kandidaten);

    if ($actie === 'aantal_groepen') {
        evenement_groepen_zetten($ev, $seizoen_id, (int)($_POST['aantal'] ?? 1));
        flash('succes', 'Aantal groepen bijgewerkt.');
        redirect($terug);
    }
    if ($actie === 'voorstel') {
        // Het aantal uit het formulier geldt: eerst de groepen daarop zetten, dan verdelen.
        evenement_groepen_zetten($ev, $seizoen_id, (int)($_POST['aantal'] ?? 3));
        evenement_voorstel_maken($ev, $seizoen_id, $kandidaten, (int)($_POST['aantal'] ?? 3));
        flash('succes', 'Voorstel gemaakt. Je kunt het hieronder nog aanpassen.');
        redirect($terug);
    }
    if ($actie === 'verplaats') {
        // Eén endpoint voor slepen (fetch, JSON) en de keuzelijst "Naar groep" op het kaartje
        // (gewoon formulier met terug=1, redirect terug naar dat kaartje).
        $formulier = !empty($_POST['terug']);
        $sol_id    = (int)($_POST['sol_id'] ?? 0);
        $groep_raw = (string)($_POST['groep_id'] ?? '');
        $groep_id  = $groep_raw !== '' ? (int)$groep_raw : null;
        $geldig    = in_array($sol_id, $ids, true) && ($groep_id === null || evenement_groep_bestaat($ev, $seizoen_id, $groep_id));
        if ($geldig) evenement_indeel($ev, $seizoen_id, $sol_id, $groep_id);
        if ($formulier) {
            flash($geldig ? 'succes' : 'fout', $geldig ? 'Verplaatst.' : 'Verplaatsen niet gelukt: onbekende persoon of groep.');
            redirect($terug . '#kaart-' . $sol_id);
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => $geldig]);
        exit;
    }
    // relatie: "niet samen met" — één set per seizoen, geldt voor beide evenementen.
    header('Content-Type: application/json');
    $a = (int)($_POST['a'] ?? 0);
    $b = (int)($_POST['b'] ?? 0);
    $ok = $a !== $b && in_array($a, $ids, true) && in_array($b, $ids, true);
    if ($ok) selectie_relatie_zet($seizoen_id, $a, $b, ($_POST['toevoegen'] ?? '') === '1');
    echo json_encode(['ok' => $ok]);
    exit;
}

} // function_exists
