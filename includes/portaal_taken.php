<?php
/**
 * Paal 13 — Portaal: de takenlijst van een sollicitant (één bron voor start, Taken-tab en
 * het telbolletje in de navigatie) + de POST-acties die bij taken horen.
 *
 * Taak-contract (elke rij van portaal_taken()):
 *   id          string   stabiele sleutel: blk | besch | ouder | ond_<id> | oud_<id> | db_<id>
 *   titel       string
 *   sub         string   toelichting (open) of "Ingeleverd 8 mrt 2026" (afgerond)
 *   done        bool
 *   done_op     ?string  'Y-m-d H:i:s' van afronding (voor sorteren/tonen)
 *   url         ?string  null = niet klikbaar
 *   icoon       string   naam voor icoon()
 *   kleur       string   accent | ok | navy | error  (icoonachtergrond)
 *   actie_label ?string  'Bekijken' / 'Wijzigen' bij afgeronde rij; null + url = chevron
 *   urgent      bool
 *   volgorde    int      sorteersleutel binnen open/afgerond
 *   inline_form ?string  'ouder' → de rij toont het ouderformulier
 *   inline_data array    gegevens voor het inline formulier
 *
 * Geen HTML hier; de partials portaal/_taakrij.php en _takenlijst.php renderen.
 */

if (!function_exists('portaal_taken')) {

/** Fases waarin de beschikbaarheid als taak zichtbaar is. */
function portaal_taken_fases_beschikbaarheid(): array {
    return ['uitgenodigd_selectie', 'aangemeld_selectie', 'selectiedag_geweest', 'uitgenodigd_tw', 'tw_geweest', 'aangenomen'];
}

/** Fases waarin de ouder/verzorger-gegevens (minderjarigen) gevraagd worden. */
function portaal_taken_fases_ouder(): array {
    return ['uitgenodigd_selectie', 'aangemeld_selectie'];
}

/**
 * Alle taken van deze sollicitant, open eerst (urgent bovenaan), daarna afgerond (nieuwste eerst).
 */
function portaal_taken(array $sol, ?array $blk = null): array {
    $sol_id = (int)$sol['id'];
    $fase   = $sol['fase'] ?? '';
    $taken  = [];
    $std    = ['sub' => '', 'done' => false, 'done_op' => null, 'url' => null, 'icoon' => 'document',
               'kleur' => 'accent', 'actie_label' => null, 'urgent' => false, 'volgorde' => 50,
               'inline_form' => null, 'inline_data' => []];

    // 1) Ouder/verzorger-gegevens (minderjarig in de selectiedag-fases, of documenten die op
    //    een ouder wachten zonder e-mailadres).
    $ouder_docs = portaal_ouder_docs($sol_id);
    $ouder_nodig = false;
    if (in_array($fase, portaal_taken_fases_ouder(), true)) {
        $lft = function_exists('leeftijd_uit_datum') ? leeftijd_uit_datum($sol['geboortedatum'] ?? null) : null;
        if ($lft !== null && $lft < 18) $ouder_nodig = true;
    }
    foreach ($ouder_docs as $d) { if (empty($d['email']) && !$d['klaar']) $ouder_nodig = true; }
    if ($ouder_nodig) {
        $og = portaal_ouder_gegevens($sol_id);
        $bekend = !empty($og['ouder_email']);
        $taken[] = array_merge($std, [
            'id'          => 'ouder',
            'titel'       => $bekend ? 'Gegevens ouder/verzorger' : 'Gegevens ouder/verzorger invullen',
            'sub'         => $bekend
                ? trim(($og['ouder_naam'] ?? '') . ' · ' . ($og['ouder_email'] ?? ''), ' ·')
                : 'Je ouder of verzorger tekent mee omdat je nog geen 18 bent',
            'done'        => $bekend,
            'icoon'       => 'ouders',
            'kleur'       => $bekend ? 'ok' : 'accent',
            'actie_label' => $bekend ? 'Wijzigen' : null,
            'urgent'      => !$bekend,
            'volgorde'    => 10,
            'inline_form' => 'ouder',
            'inline_data' => $og,
        ]);
    }

    // 2) Eigen ondertekeningen (gezondheidsverklaring 18+, intakeformulier).
    try {
        $q = db()->prepare("SELECT id, type, token, ondertekend_op FROM ondertekeningen WHERE sollicitatie_id=? AND ondertekenaar_rol='sollicitant' ORDER BY aangemaakt_op");
        $q->execute([$sol_id]);
        foreach ($q->fetchAll() as $o) {
            $klaar = !empty($o['ondertekend_op']);
            $titel = match ($o['type']) {
                'gezondheid_18plus' => 'Gezondheidsverklaring ondertekenen',
                'intake'            => 'Intakeformulier invullen',
                default             => 'Document ondertekenen',
            };
            $taken[] = array_merge($std, [
                'id'          => 'ond_' . (int)$o['id'],
                'titel'       => $klaar ? preg_replace('/ (ondertekenen|invullen)$/', '', $titel) : $titel,
                'sub'         => $klaar ? 'Ondertekend ' . datum_nl(substr($o['ondertekend_op'], 0, 10)) : 'Nodig voor de selectiedag',
                'done'        => $klaar,
                'done_op'     => $o['ondertekend_op'] ?: null,
                'url'         => APP_URL . '/portaal/ondertekenen.php?token=' . $o['token'],
                'icoon'       => 'ondertekenen',
                'kleur'       => $klaar ? 'ok' : 'accent',
                'actie_label' => $klaar ? 'Bekijken' : null,
                'urgent'      => !$klaar,
                'volgorde'    => 20,
            ]);
        }
    } catch (\Throwable $e) {}

    // 3) Documenten die de ouder/verzorger tekent (status zichtbaar voor de sollicitant).
    foreach ($ouder_docs as $d) {
        if (empty($d['email']) && !$d['klaar']) continue; // zit in de ouder-gegevens-taak
        $taken[] = array_merge($std, [
            'id'       => 'oud_' . $d['id'],
            'titel'    => $d['label'],
            'sub'      => $d['klaar']
                ? 'Ondertekend ' . datum_nl(substr((string)$d['klaar_op'], 0, 10)) . ($d['naam'] ? ' door ' . $d['naam'] : '')
                : 'Wacht op ondertekening door ' . ($d['email'] ?: 'je ouder/verzorger'),
            'done'     => $d['klaar'],
            'done_op'  => $d['klaar_op'],
            'icoon'    => $d['klaar'] ? 'ondertekenen' : 'mail',
            'kleur'    => $d['klaar'] ? 'ok' : 'navy',
            'urgent'   => false,
            'volgorde' => 25,
        ]);
    }

    // 4) Beter Leren Kennen (alleen in de selectiedag-fases in te vullen; daarna alleen te bekijken).
    if (in_array($fase, blk_fases_zichtbaar(), true) || blk_is_ingediend($sol, $blk)) {
        $klaar = blk_is_ingediend($sol, $blk);
        $op    = $blk['definitief_op'] ?? ($blk['ingevuld_op'] ?? null);
        $taken[] = array_merge($std, [
            'id'          => 'blk',
            'titel'       => $klaar ? 'Beter Leren Kennen' : 'Beter Leren Kennen invullen',
            'sub'         => $klaar
                ? 'Ingeleverd' . ($op ? ' ' . datum_nl(substr($op, 0, 10)) : '')
                : '± 8 minuten' . (!empty($sol['datum_selectiedag']) ? ' · vóór ' . portaal_datum_kort($sol['datum_selectiedag']) : ''),
            'done'        => $klaar,
            'done_op'     => $op,
            'url'         => APP_URL . '/portaal/blk_invullen.php',
            'icoon'       => 'document',
            'kleur'       => $klaar ? 'ok' : 'accent',
            'actie_label' => $klaar ? 'Bekijken' : null,
            'urgent'      => !$klaar,
            'volgorde'    => 30,
        ]);
    }

    // 5) Beschikbaarheid: doorgeven → (voorlopig) definitief maken → bijwerken.
    if (in_array($fase, portaal_taken_fases_beschikbaarheid(), true) && function_exists('beschikbaarheid2_van')) {
        $bv = [];
        try { $bv = beschikbaarheid2_van($sol_id); } catch (\Throwable $e) {}
        $ingevuld  = !empty($bv['blokken']);
        $voorlopig = $ingevuld && !empty($bv['profiel']['voorlopig']);
        $bijgewerkt = $bv['profiel']['bijgewerkt_op'] ?? ($bv['profiel']['aangemaakt_op'] ?? null);
        $invul_url = APP_URL . '/portaal/beschikbaarheid_invullen.php';
        $kijk_url  = APP_URL . '/portaal/beschikbaarheid.php';
        if (!$ingevuld) {
            $taken[] = array_merge($std, [
                'id' => 'besch', 'titel' => 'Beschikbaarheid doorgeven', 'sub' => 'Welke weken kun je werken?',
                'url' => $invul_url, 'icoon' => 'kalender', 'kleur' => 'accent', 'urgent' => true, 'volgorde' => 40,
            ]);
        } elseif ($voorlopig) {
            $deadline = function_exists('beschikbaarheid2_deadline') ? beschikbaarheid2_deadline() : null;
            $sub = 'Staat nog op voorlopig';
            if ($deadline) {
                $dagen = (int)floor(($deadline->getTimestamp() - strtotime('today')) / 86400);
                if ($dagen < 0)       $sub = 'Deadline verstreken — maak hem nu definitief';
                elseif ($dagen === 0) $sub = 'Vandaag is de deadline';
                elseif ($dagen === 1) $sub = 'Nog 1 dag · deadline ' . portaal_datum_kort($deadline->format('Y-m-d'));
                else                  $sub = 'Nog ' . $dagen . ' dagen · deadline ' . portaal_datum_kort($deadline->format('Y-m-d'));
            }
            $taken[] = array_merge($std, [
                'id' => 'besch', 'titel' => 'Beschikbaarheid definitief maken', 'sub' => $sub,
                'url' => $kijk_url, 'icoon' => 'kalender', 'kleur' => 'accent', 'urgent' => true, 'volgorde' => 40,
            ]);
        } else {
            $taken[] = array_merge($std, [
                'id' => 'besch', 'titel' => 'Beschikbaarheid',
                'sub' => 'Bijgewerkt' . ($bijgewerkt ? ' ' . datum_nl(substr($bijgewerkt, 0, 10)) : ''),
                'done' => true, 'done_op' => $bijgewerkt,
                'url' => in_array($fase, blk_fases_zichtbaar(), true) ? $invul_url : $kijk_url,
                'icoon' => 'kalender', 'kleur' => 'ok', 'actie_label' => 'Wijzigen', 'volgorde' => 40,
            ]);
        }
    }

    // 6) Taken uit de database (kennismakingspakket bij aangenomen). Alleen-lezen: het echte
    //    takenpakket (afvinken, bijlagen) is een aparte, latere bundel.
    try {
        $q = db()->prepare("SELECT id, taak_slug, titel, omschrijving, link_url, status, verplicht, voltooid_op FROM sollicitant_taken WHERE sollicitatie_id=? ORDER BY verplicht DESC, id");
        $q->execute([$sol_id]);
        foreach ($q->fetchAll() as $t) {
            $klaar = ($t['status'] ?? '') === 'voltooid';
            $taken[] = array_merge($std, [
                'id'       => 'db_' . (int)$t['id'],
                'titel'    => $t['titel'],
                'sub'      => $klaar
                    ? 'Afgerond' . (!empty($t['voltooid_op']) ? ' ' . datum_nl(substr($t['voltooid_op'], 0, 10)) : '')
                    : trim((string)$t['omschrijving']) . (empty($t['verplicht']) ? ' (optioneel)' : ''),
                'done'     => $klaar,
                'done_op'  => $t['voltooid_op'] ?: null,
                'url'      => $t['link_url'] ?: null,
                'icoon'    => 'lijst',
                'kleur'    => $klaar ? 'ok' : 'accent',
                'urgent'   => !$klaar && !empty($t['verplicht']),
                'volgorde' => 50,
            ]);
        }
    } catch (\Throwable $e) {}

    usort($taken, function ($a, $b) {
        if ($a['done'] !== $b['done']) return $a['done'] ? 1 : -1;
        if (!$a['done']) {
            if ($a['urgent'] !== $b['urgent']) return $a['urgent'] ? -1 : 1;
            return $a['volgorde'] <=> $b['volgorde'];
        }
        return strcmp((string)$b['done_op'], (string)$a['done_op']) ?: ($a['volgorde'] <=> $b['volgorde']);
    });
    return $taken;
}

/** Alleen de open taken. */
function portaal_taken_open(array $taken): array {
    return array_values(array_filter($taken, fn($t) => empty($t['done'])));
}

/** Alleen de afgeronde taken. */
function portaal_taken_afgerond(array $taken): array {
    return array_values(array_filter($taken, fn($t) => !empty($t['done'])));
}

/**
 * Aantal open taken voor het telbolletje in de navigatie. Laadt zelf wat nodig is,
 * gecachet per request, en faalt stil (0) zodat de voet nooit breekt.
 */
function portaal_taken_open_aantal(int $sol_id): int {
    static $cache = [];
    if (isset($cache[$sol_id])) return $cache[$sol_id];
    try {
        $sol = portaal_sollicitant_laden($sol_id);
        if (!$sol) return $cache[$sol_id] = 0;
        $blk = function_exists('blk_van') ? blk_van($sol_id) : null;
        return $cache[$sol_id] = count(portaal_taken_open(portaal_taken($sol, $blk)));
    } catch (\Throwable $e) { return $cache[$sol_id] = 0; }
}

/** Documenten die de ouder/verzorger tekent: label, e-mail, status. */
function portaal_ouder_docs(int $sol_id): array {
    $uit = [];
    try {
        $q = db()->prepare("SELECT id, type, ondertekenaar_naam, ondertekenaar_email, ondertekend_op, aangemaakt_op FROM ondertekeningen WHERE sollicitatie_id=? AND ondertekenaar_rol='ouder' ORDER BY aangemaakt_op");
        $q->execute([$sol_id]);
        foreach ($q->fetchAll() as $o) {
            $uit[] = [
                'id'       => (int)$o['id'],
                'label'    => match ($o['type']) {
                    'gezondheid_ouder' => 'Gezondheidsverklaring (ouder/verzorger)',
                    'intake_ouder'     => 'Intakeformulier (ouder/verzorger)',
                    default            => 'Document (ouder/verzorger)',
                },
                'naam'     => $o['ondertekenaar_naam'] ?? '',
                'email'    => $o['ondertekenaar_email'] ?? '',
                'klaar'    => !empty($o['ondertekend_op']),
                'klaar_op' => $o['ondertekend_op'] ?: null,
            ];
        }
    } catch (\Throwable $e) {}
    return $uit;
}

/** Ouder/verzorger-gegevens op de persoon (naam, e-mail, telefoon). */
function portaal_ouder_gegevens(int $sol_id): array {
    $leeg = ['ouder_naam' => '', 'ouder_email' => '', 'ouder_telefoon' => ''];
    try {
        if (function_exists('personen_ouder_auto_setup')) personen_ouder_auto_setup();
        $q = db()->prepare("SELECT p.ouder_naam, p.ouder_email, p.ouder_telefoon FROM personen p JOIN sollicitaties s ON s.persoon_id=p.id WHERE s.id=?");
        $q->execute([$sol_id]);
        $r = $q->fetch();
        return $r ? array_merge($leeg, array_filter($r, fn($v) => $v !== null)) : $leeg;
    } catch (\Throwable $e) { return $leeg; }
}

/**
 * POST-acties bij taken (alleen aanroepen als de sollicitant is ingelogd, vóór alle HTML):
 *  - ouder_submit zonder ond_id: oudergegevens opslaan + ondertekeningen klaarzetten
 *  - taak_toggle: DB-taak open/voltooid (blijft beschikbaar voor het latere takenpakket)
 *  - ouder_submit met ond_id: ouder-gegevens op één ondertekening bijwerken + mail
 * Na afloop redirect naar Start of Taken (whitelist via veld 'terug').
 */
function portaal_taken_post_afhandelen(int $sol_id): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $terug = (($_POST['terug'] ?? '') === 'taken') ? APP_URL . '/portaal/taken.php' : APP_URL . '/portaal/';

    if (isset($_POST['ouder_submit']) && !isset($_POST['ond_id'])) {
        csrf_check();
        $o_naam  = trim($_POST['ouder_naam'] ?? '');
        $o_email = trim($_POST['ouder_email'] ?? '');
        $o_tel   = trim($_POST['ouder_telefoon'] ?? '');
        // Telefoon: cijfers, spaties, +, -, () toegestaan; minstens 8 cijfers
        $tel_ok = preg_match_all('/\d/', $o_tel) >= 8;
        if ($o_naam && filter_var($o_email, FILTER_VALIDATE_EMAIL) && $tel_ok) {
            $opgeslagen = false;
            // Stap 1: gegevens opslaan (dit is de kern — moet lukken)
            try {
                if (function_exists('personen_ouder_auto_setup')) personen_ouder_auto_setup();
                // Zeker stellen dat de ouder-kolommen bestaan
                try {
                    $db = db();
                    if (function_exists('db_kolom_verzeker')) {
                        db_kolom_verzeker($db, 'personen', 'ouder_naam',     'VARCHAR(120) DEFAULT NULL');
                        db_kolom_verzeker($db, 'personen', 'ouder_email',    'VARCHAR(255) DEFAULT NULL');
                        db_kolom_verzeker($db, 'personen', 'ouder_telefoon', 'VARCHAR(30) DEFAULT NULL');
                    }
                } catch (\Throwable $e) { error_log('ouder-kolommen aanmaken: ' . $e->getMessage()); }

                $pid_q = db()->prepare("SELECT persoon_id FROM sollicitaties WHERE id=?");
                $pid_q->execute([$sol_id]);
                $persoon_id = (int)$pid_q->fetchColumn();
                if ($persoon_id > 0) {
                    db()->prepare("UPDATE personen SET ouder_naam=?, ouder_email=?, ouder_telefoon=? WHERE id=?")
                        ->execute([$o_naam, $o_email, $o_tel, $persoon_id]);
                    $opgeslagen = true;
                } else {
                    error_log('ouder_submit: geen persoon_id voor sollicitatie ' . $sol_id);
                    $_SESSION['flash_portaal_fout'] = 'Er ging iets mis bij het opslaan (kon je gegevens niet koppelen). Neem contact op.';
                }
            } catch (\Throwable $e) {
                error_log('ouder_submit opslaan: ' . $e->getMessage());
                $_SESSION['flash_portaal_fout'] = 'Er ging iets mis bij het opslaan van de gegevens. Probeer het later opnieuw of neem contact op via ' . portaal_contact_email() . '.';
            }
            // Stap 2: vervolgstappen — mogen falen zonder de bevestiging te blokkeren
            if ($opgeslagen) {
                $docs_ok = true;
                try { if (function_exists('ondertekeningen_klaarzetten_sd')) ondertekeningen_klaarzetten_sd($sol_id); }
                catch (\Throwable $e) { $docs_ok = false; error_log('ondertekeningen_klaarzetten_sd: ' . $e->getMessage()); }
                try { if (function_exists('mail_taken_klaar')) mail_taken_klaar($sol_id); }
                catch (\Throwable $e) { error_log('mail_taken_klaar: ' . $e->getMessage()); }
                $_SESSION['flash_portaal'] = $docs_ok
                    ? 'Bedankt! Je ouder/verzorger ontvangt nu een mail om de formulieren te ondertekenen.'
                    : 'De gegevens zijn opgeslagen. Het klaarzetten van de formulieren lukte nog niet — we kijken ernaar; probeer het zo nodig later opnieuw.';
            }
        } else {
            $_SESSION['flash_portaal_fout'] = 'Vul een geldige naam, e-mailadres én telefoonnummer in.';
        }
        redirect($terug);
    }

    if (isset($_POST['taak_toggle'])) {
        csrf_check();
        $taak_id = (int)($_POST['taak_id'] ?? 0);
        try {
            $q = db()->prepare("SELECT status FROM sollicitant_taken WHERE id=? AND sollicitatie_id=?");
            $q->execute([$taak_id, $sol_id]);
            $t = $q->fetch();
            if ($t) {
                $nieuw    = $t['status'] === 'voltooid' ? 'open' : 'voltooid';
                $voltooid = $nieuw === 'voltooid' ? date('Y-m-d H:i:s') : null;
                db()->prepare("UPDATE sollicitant_taken SET status=?, voltooid_op=? WHERE id=?")
                    ->execute([$nieuw, $voltooid, $taak_id]);
            }
        } catch (\Throwable $e) {}
        redirect($terug);
    }

    if (isset($_POST['ouder_submit']) && isset($_POST['ond_id'])) {
        csrf_check();
        $ond_id      = (int)($_POST['ond_id'] ?? 0);
        $ouder_naam  = trim($_POST['ouder_naam'] ?? '');
        $ouder_email = trim($_POST['ouder_email'] ?? '');
        if ($ond_id && $ouder_naam && filter_var($ouder_email, FILTER_VALIDATE_EMAIL)) {
            try {
                // Alleen eigen records zonder ondertekening bijwerken
                $upd = db()->prepare("UPDATE ondertekeningen SET ondertekenaar_naam=?, ondertekenaar_email=? WHERE id=? AND sollicitatie_id=? AND ondertekend_op IS NULL");
                $upd->execute([$ouder_naam, $ouder_email, $ond_id, $sol_id]);
                if ($upd->rowCount() > 0 && function_exists('ouder_mail_versturen')) ouder_mail_versturen($ond_id);
            } catch (\Throwable $e) {}
        }
        redirect($terug);
    }
}

} // function_exists
