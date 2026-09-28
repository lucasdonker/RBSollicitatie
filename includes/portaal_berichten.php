<?php
/**
 * Paal 13 — Portaal: berichten achter het belletje (ontwerp 08 "Berichten").
 *
 * Bron: mail_log (alle kanalen: e-mail, WhatsApp, SMS), alleen berichten aan de sollicitant
 * zelf (ontvanger_rol = sollicitant) die echt verstuurd zijn. Gelezen-status: sollicitaties.
 * berichten_gezien_op (failsafe-kolom); alles wat daarna verstuurd is telt als ongelezen.
 * Het volledige bericht is leesbaar in het portaal (portaal/bericht.php) zolang de inhoud
 * bewaard wordt (bewaar.maillog_body_mnd).
 */

if (!function_exists('portaal_berichten')) {

/** Zorgt dat de kolom voor de gelezen-status bestaat. */
function portaal_berichten_kolom_verzeker(): void {
    static $klaar = false;
    if ($klaar || !function_exists('db_kolom_verzeker') || schema_actueel()) return;
    $klaar = true;
    try { db_kolom_verzeker(db(), 'sollicitaties', 'berichten_gezien_op', 'DATETIME DEFAULT NULL'); }
    catch (\Throwable $e) { error_log('berichten_gezien_op: ' . $e->getMessage()); }
}

/** Wanneer de sollicitant het berichtenoverzicht voor het laatst opende (null = nooit). */
function portaal_berichten_gezien_op(int $sol_id): ?string {
    portaal_berichten_kolom_verzeker();
    try {
        $q = db()->prepare("SELECT berichten_gezien_op FROM sollicitaties WHERE id=?");
        $q->execute([$sol_id]);
        $v = $q->fetchColumn();
        return $v ?: null;
    } catch (\Throwable $e) { return null; }
}

/** Kanaal → leesbaar ("per e-mail") en icoonnaam. */
function portaal_bericht_kanaal(string $kanaal): array {
    return match ($kanaal) {
        'whatsapp' => ['label' => 'per WhatsApp', 'icoon' => 'whatsapp'],
        'sms'      => ['label' => 'per sms',      'icoon' => 'sms'],
        default    => ['label' => 'per e-mail',   'icoon' => 'mail'],
    };
}

/** De WHERE-voorwaarde voor "berichten aan deze sollicitant". */
function portaal_berichten_waar(): string {
    return "sollicitatie_id=? AND status='verstuurd' AND COALESCE(ontvanger_rol,'sollicitant')='sollicitant'";
}

/**
 * Berichten (nieuwste eerst, max 50). Per bericht: id, titel, samenvatting, kanaal, icoon,
 * meta ("Vandaag om 09:14 · per e-mail"), verstuurd_op, ongelezen (bool), heeft_inhoud.
 */
function portaal_berichten(int $sol_id, ?string $gezien_op = null): array {
    $gezien_op = $gezien_op ?? portaal_berichten_gezien_op($sol_id);
    $uit = [];
    try {
        $q = db()->prepare("SELECT id, onderwerp, verstuurd_op, kanaal, samenvatting, meldingstype, (body IS NOT NULL AND body<>'') AS heeft_inhoud
                            FROM mail_log WHERE " . portaal_berichten_waar() . "
                            ORDER BY verstuurd_op DESC, id DESC LIMIT 80");
        $q->execute([$sol_id]);
        // Eén bericht dat per e-mail én WhatsApp ging, staat als één regel (portaal_berichten_bundel).
        foreach (portaal_berichten_bundel($q->fetchAll()) as $groep) {
            $m = $groep['hoofd'];
            $kanalen = array_values(array_intersect(['email', 'whatsapp', 'sms'], array_map(fn($r) => (string)($r['kanaal'] ?? 'email'), $groep['rijen'])));
            $samen = '';
            foreach ($groep['rijen'] as $r) if (trim((string)($r['samenvatting'] ?? '')) !== '') { $samen = (string)$r['samenvatting']; break; }
            $ongelezen = false;
            foreach ($groep['rijen'] as $r) $ongelezen = $ongelezen || $gezien_op === null || strcmp((string)$r['verstuurd_op'], $gezien_op) > 0;
            $uit[] = [
                'id'           => (int)$m['id'],
                'titel'        => $m['onderwerp'] ?: 'Bericht van Paal 13',
                'samenvatting' => $samen,
                'kanaal'       => (string)($m['kanaal'] ?? 'email'),
                'kanalen'      => $kanalen,
                'icoon'        => portaal_bericht_kanaal((string)($m['kanaal'] ?? 'email'))['icoon'],
                'verstuurd_op' => $m['verstuurd_op'],
                'meta'         => portaal_bericht_tijd($m['verstuurd_op']) . ' · ' . portaal_bericht_kanalen_tekst($kanalen),
                'ongelezen'    => $ongelezen,
                'heeft_inhoud' => !empty($m['heeft_inhoud']),
            ];
            if (count($uit) >= 50) break;
        }
    } catch (\Throwable $e) {}
    return $uit;
}

/**
 * Rijen uit mail_log (nieuwste eerst) samenvoegen tot berichten: dezelfde melding die binnen
 * 15 minuten via meer kanalen ging (e-mail + WhatsApp/sms) is voor de sollicitant één bericht.
 * Hoofdversie = de e-mail (volledigste inhoud), anders de eerste. Handmatige berichten met een
 * verschillend onderwerp blijven los.
 *
 * @return array [ ['hoofd' => rij, 'rijen' => [rij, …]] ]  in dezelfde volgorde
 */
function portaal_berichten_bundel(array $rijen): array {
    $groepen = [];
    foreach ($rijen as $r) {
        $type = (string)($r['meldingstype'] ?? '');
        $t    = strtotime((string)$r['verstuurd_op']) ?: 0;
        $bij  = null;
        foreach ($groepen as $i => $g) {
            $g0 = $g['rijen'][0];
            if ($type === '' || (string)($g0['meldingstype'] ?? '') !== $type) continue;
            if (abs((strtotime((string)$g0['verstuurd_op']) ?: 0) - $t) > 900) continue;
            if (in_array((string)($r['kanaal'] ?? 'email'), array_map(fn($x) => (string)($x['kanaal'] ?? 'email'), $g['rijen']), true)) continue;
            $o1 = trim((string)($g0['onderwerp'] ?? '')); $o2 = trim((string)($r['onderwerp'] ?? ''));
            if ($o1 !== '' && $o2 !== '' && $o1 !== $o2) continue;
            $bij = $i; break;
        }
        if ($bij === null) $groepen[] = ['rijen' => [$r]];
        else $groepen[$bij]['rijen'][] = $r;
    }
    foreach ($groepen as &$g) {
        $g['hoofd'] = $g['rijen'][0];
        foreach ($g['rijen'] as $r) if (($r['kanaal'] ?? 'email') === 'email') { $g['hoofd'] = $r; break; }
        // Onderwerp van de e-mail als titel, ook als de WhatsApp-versie er geen had.
        if (trim((string)($g['hoofd']['onderwerp'] ?? '')) === '') {
            foreach ($g['rijen'] as $r) if (trim((string)($r['onderwerp'] ?? '')) !== '') { $g['hoofd']['onderwerp'] = $r['onderwerp']; break; }
        }
    }
    unset($g);
    return $groepen;
}

/**
 * Het icoonvlak van een bericht: één icoon per kanaal waarlangs het ging, zodat je ziet dat
 * een gebundeld bericht naar e-mail én WhatsApp ging. Decoratief; de meta-tekst zegt het ook.
 */
function portaal_kanaal_iconen(array $kanalen): string {
    $kanalen = array_values(array_intersect(['email', 'whatsapp', 'sms'], $kanalen)) ?: ['email'];
    $maat = count($kanalen) > 1 ? 16 : 20;
    $uit = '';
    foreach ($kanalen as $k) $uit .= icoon(portaal_bericht_kanaal($k)['icoon'], $maat);
    return '<span class="pt-bericht-icoon' . (count($kanalen) > 1 ? ' meer' : '') . '" aria-hidden="true">' . $uit . '</span>';
}

/** ['email','whatsapp'] → "per e-mail en WhatsApp". */
function portaal_bericht_kanalen_tekst(array $kanalen): string {
    $naam = ['email' => 'e-mail', 'whatsapp' => 'WhatsApp', 'sms' => 'sms'];
    $l = array_map(fn($k) => $naam[$k] ?? $k, $kanalen);
    if (count($l) <= 1) return 'per ' . ($l[0] ?? 'e-mail');
    $laatste = array_pop($l);
    return 'per ' . implode(', ', $l) . ' en ' . $laatste;
}

/** De andere kanalen van hetzelfde bericht, voor de detailpagina: [['kanaal','verstuurd_op'], …]. */
function portaal_bericht_ook(int $sol_id, array $b): array {
    if (empty($b['meldingstype'])) return [];
    try {
        $q = db()->prepare("SELECT id, onderwerp, verstuurd_op, kanaal, meldingstype FROM mail_log WHERE " . portaal_berichten_waar() . "
                            AND meldingstype=? ORDER BY verstuurd_op DESC, id DESC LIMIT 30");
        $q->execute([$sol_id, $b['meldingstype']]);
        foreach (portaal_berichten_bundel($q->fetchAll()) as $g) {
            if (!in_array((int)$b['id'], array_map(fn($r) => (int)$r['id'], $g['rijen']), true)) continue;
            return array_values(array_map(fn($r) => ['kanaal' => (string)$r['kanaal'], 'verstuurd_op' => $r['verstuurd_op']],
                                array_filter($g['rijen'], fn($r) => (int)$r['id'] !== (int)$b['id'])));
        }
    } catch (\Throwable $e) {}
    return [];
}

/** Eén bericht met inhoud, alleen als het van deze sollicitant is. Null als niet gevonden. */
function portaal_bericht_laden(int $sol_id, int $id): ?array {
    try {
        $q = db()->prepare("SELECT id, onderwerp, verstuurd_op, kanaal, samenvatting, body, meldingstype FROM mail_log WHERE id=? AND " . portaal_berichten_waar());
        $q->execute([$id, $sol_id]);
        $m = $q->fetch();
        if (!$m) return null;
        $k = portaal_bericht_kanaal((string)($m['kanaal'] ?? 'email'));
        return [
            'id' => (int)$m['id'], 'titel' => $m['onderwerp'] ?: 'Bericht van Paal 13', 'kanaal' => (string)($m['kanaal'] ?? 'email'),
            'icoon' => $k['icoon'], 'meta' => portaal_bericht_tijd($m['verstuurd_op']) . ' · ' . $k['label'],
            'samenvatting' => (string)($m['samenvatting'] ?? ''), 'body' => $m['body'] !== null ? (string)$m['body'] : null,
            'meldingstype' => (string)($m['meldingstype'] ?? ''),
        ];
    } catch (\Throwable $e) { return null; }
}

/**
 * Een vraag vanuit het portaal afhandelen. Eén plek, zodat elke pagina die het
 * vraagblok toont hem op dezelfde manier verwerkt.
 *
 * Begrenzing: drie vragen per uur per sollicitant. Genoeg voor wie iets wil weten,
 * te weinig om er een verzendkanaal van te maken.
 *
 * Gelukt = doorsturen naar dezelfde pagina (komt niet terug); de bevestiging staat dan in 'flash'.
 *
 * @return array{gedaan:bool,flash:?string,fout:?string}
 */
function portaal_vraag_verwerk(array $sol): array {
    $uit = ['gedaan' => false, 'flash' => null, 'fout' => null];
    // Na een geslaagde vraag is er doorgestuurd (hieronder); de bevestiging staat dan in de sessie.
    if (!empty($_SESSION['portaal_vraag_flash'])) { $uit['flash'] = (string)$_SESSION['portaal_vraag_flash']; unset($_SESSION['portaal_vraag_flash']); }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['vraag_versturen'])) return $uit;

    csrf_check();
    $sol_id = (int)($sol['id'] ?? 0);
    $vraag  = trim((string)($_POST['vraag'] ?? ''));
    $uit['gedaan'] = true;

    if (mb_strlen($vraag) < 5) { $uit['fout'] = 'Schrijf even op wat je wilt weten.'; return $uit; }
    if (mb_strlen($vraag) > 1500) $vraag = mb_substr($vraag, 0, 1500);

    if (function_exists('rate_limit') && !rate_limit('portaal_vraag', (string)$sol_id, 3, 3600)) {
        $uit['fout'] = 'Je hebt net al een paar vragen gestuurd. Wacht even, of mail ons rechtstreeks op ' . (function_exists('portaal_contact_email') ? portaal_contact_email() : 'sollicitatie@paal13.com') . '.';
        return $uit;
    }

    if (!function_exists('melding_verstuur')) { $uit['fout'] = 'Versturen lukt nu niet. Probeer het later opnieuw.'; return $uit; }

    try {
        $res = melding_verstuur('sollicitant_vraag', $sol_id, [
            'vraag'      => $vraag,
            'fase_label' => function_exists('fase_label') ? fase_label((string)($sol['fase'] ?? '')) : (string)($sol['fase'] ?? ''),
        ], ['prioriteit' => 'na_antwoord', 'dedup_min' => 0,
            // Naar het contactadres voor sollicitanten (Instellingen › Mail), niet het algemene bestuursadres.
            'ontvanger' => ['email' => portaal_contact_email(), 'naam' => 'Paal 13', 'rol' => 'bestuur']]);
        if (($res['status'] ?? '') === 'mislukt') {
            $uit['fout'] = 'Versturen lukte niet. Probeer het later opnieuw, of mail ons rechtstreeks op ' . (function_exists('portaal_contact_email') ? portaal_contact_email() : 'sollicitatie@paal13.com') . '.';
            return $uit;
        }
        if (function_exists('audit_log')) audit_log('vraag_gesteld', 'sollicitatie', $sol_id, ['lengte' => mb_strlen($vraag)]);
        $_SESSION['portaal_vraag_flash'] = 'Je vraag is verstuurd. Je krijgt antwoord per e-mail — meestal binnen een paar dagen.';
    } catch (\Throwable $e) {
        error_log('portaal_vraag: ' . $e->getMessage());
        $uit['fout'] = 'Versturen lukte niet. Probeer het later opnieuw.';
        return $uit;
    }
    // Doorsturen (Post/Redirect/Get): verversen mag de vraag niet nog eens versturen.
    redirect(APP_URL . '/portaal/' . basename((string)($_SERVER['SCRIPT_NAME'] ?? 'contact.php')));
    return $uit;
}

/**
 * Maak de opgeslagen e-mailinhoud veilig om in het portaal te tonen: alleen opmaak-tags,
 * geen scripts, geen event-attributen, geen externe formulieren.
 */
function portaal_bericht_html_schoon(string $html): string {
    $html = preg_replace('#<(script|style|iframe|object|embed|form|input|button)[^>]*>.*?</\1>#is', '', $html) ?? '';
    $html = strip_tags($html, '<p><br><strong><em><b><i><u><a><ul><ol><li><h2><h3><h4><table><tbody><tr><td><th><span><div><img><hr><blockquote>');
    $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
    $html = preg_replace('/\s(href|src)\s*=\s*("|\')?\s*javascript:[^"\'>\s]*("|\')?/i', ' $1="#"', $html) ?? '';
    return $html;
}

/** Aantal ongelezen berichten (voor de stip op het belletje). Faalt stil met 0. */
function portaal_berichten_ongelezen(int $sol_id): int {
    static $cache = [];
    if (isset($cache[$sol_id])) return $cache[$sol_id];
    try {
        $gezien = portaal_berichten_gezien_op($sol_id);
        if ($gezien === null) {
            $q = db()->prepare("SELECT COUNT(*) FROM mail_log WHERE " . portaal_berichten_waar());
            $q->execute([$sol_id]);
        } else {
            $q = db()->prepare("SELECT COUNT(*) FROM mail_log WHERE " . portaal_berichten_waar() . " AND verstuurd_op > ?");
            $q->execute([$sol_id, $gezien]);
        }
        return $cache[$sol_id] = (int)$q->fetchColumn();
    } catch (\Throwable $e) { return $cache[$sol_id] = 0; }
}

/** Markeer alles als gelezen (bij openen van het overzicht). */
function portaal_berichten_gezien(int $sol_id): void {
    portaal_berichten_kolom_verzeker();
    try { db()->prepare("UPDATE sollicitaties SET berichten_gezien_op=NOW() WHERE id=?")->execute([$sol_id]); }
    catch (\Throwable $e) {}
}

/** "2026-03-06 08:00:00" → "Vandaag om 08:00" / "Gisteren om 08:00" / "6 maart" / "6 maart 2025". */
function portaal_bericht_tijd(?string $dt): string {
    if (!$dt) return '';
    try { $d = new DateTime($dt); } catch (\Exception $e) { return $dt; }
    $vandaag  = new DateTime('today');
    $gisteren = new DateTime('yesterday');
    if ($d->format('Y-m-d') === $vandaag->format('Y-m-d'))  return 'Vandaag om ' . $d->format('H:i');
    if ($d->format('Y-m-d') === $gisteren->format('Y-m-d')) return 'Gisteren om ' . $d->format('H:i');
    $kort = function_exists('portaal_datum_kort') ? portaal_datum_kort($d->format('Y-m-d')) : $d->format('j-n');
    return $d->format('Y') === $vandaag->format('Y') ? $kort : $kort . ' ' . $d->format('Y');
}

} // function_exists
