<?php
/**
 * Paal 13 — Portaal: berichten achter het belletje (ontwerp 08 "Berichten").
 *
 * Bron: mail_log (alleen e-mail; sms/WhatsApp worden nog niet per sollicitant gelogd — later,
 * met een kanaal-kolom). Gelezen-status: sollicitaties.berichten_gezien_op (failsafe-kolom);
 * alles wat daarna verstuurd is telt als ongelezen.
 */

if (!function_exists('portaal_berichten')) {

/** Zorgt dat de kolom voor de gelezen-status bestaat. */
function portaal_berichten_kolom_verzeker(): void {
    static $klaar = false;
    if ($klaar || !function_exists('db_kolom_verzeker')) return;
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

/**
 * Berichten (nieuwste eerst, max 50). Per bericht: titel, meta ("Vandaag om 09:14 · per e-mail"),
 * kanaal, verstuurd_op, ongelezen (bool).
 */
function portaal_berichten(int $sol_id, ?string $gezien_op = null): array {
    $gezien_op = $gezien_op ?? portaal_berichten_gezien_op($sol_id);
    $uit = [];
    try {
        $q = db()->prepare("SELECT onderwerp, verstuurd_op, type FROM mail_log
                            WHERE sollicitatie_id=? AND status='verstuurd' AND type='mail'
                            ORDER BY verstuurd_op DESC, id DESC LIMIT 50");
        $q->execute([$sol_id]);
        foreach ($q->fetchAll() as $m) {
            $uit[] = [
                'titel'        => $m['onderwerp'] ?: 'Bericht van Paal 13',
                'kanaal'       => 'e-mail',
                'verstuurd_op' => $m['verstuurd_op'],
                'meta'         => portaal_bericht_tijd($m['verstuurd_op']) . ' · per e-mail',
                'ongelezen'    => $gezien_op === null || strcmp((string)$m['verstuurd_op'], $gezien_op) > 0,
            ];
        }
    } catch (\Throwable $e) {}
    return $uit;
}

/** Aantal ongelezen berichten (voor de stip op het belletje). Faalt stil met 0. */
function portaal_berichten_ongelezen(int $sol_id): int {
    static $cache = [];
    if (isset($cache[$sol_id])) return $cache[$sol_id];
    try {
        $gezien = portaal_berichten_gezien_op($sol_id);
        if ($gezien === null) {
            $q = db()->prepare("SELECT COUNT(*) FROM mail_log WHERE sollicitatie_id=? AND status='verstuurd' AND type='mail'");
            $q->execute([$sol_id]);
        } else {
            $q = db()->prepare("SELECT COUNT(*) FROM mail_log WHERE sollicitatie_id=? AND status='verstuurd' AND type='mail' AND verstuurd_op > ?");
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
