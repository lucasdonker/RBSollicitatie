<?php
/**
 * Paal 13 — Wanneer keek deze beheerder voor het laatst op Vandaag? GEEN HTML.
 *
 * Voor "Sinds je vorige bezoek" (admin/index.php). laatste_login kan dat niet: die wordt bij het
 * inloggen al overschreven, dus je ziet dan nooit wat er tussen twee bezoeken gebeurde.
 * Twee kolommen op beheerders: vandaag_gezien_op (nu) en vandaag_vorige_op (het ijkpunt).
 * Wie binnen een uur opnieuw kijkt, houdt hetzelfde ijkpunt — anders is na één keer
 * verversen alles "oud".
 */

if (!function_exists('beheerder_bezoek_auto_setup')) {

function beheerder_bezoek_auto_setup(): void {
    static $done = false;
    if ($done || schema_actueel()) return;
    $done = true;
    if (!function_exists('db_kolom_verzeker')) return;
    try {
        db_kolom_verzeker(db(), 'beheerders', 'vandaag_gezien_op', 'DATETIME DEFAULT NULL');
        db_kolom_verzeker(db(), 'beheerders', 'vandaag_vorige_op', 'DATETIME DEFAULT NULL');
    } catch (\Throwable $e) { error_log('beheerder_bezoek_auto_setup: ' . $e->getMessage()); }
}

/**
 * Registreert dit bezoek en geeft het ijkpunt terug ('Y-m-d H:i:s'): het vorige bezoek,
 * of 7 dagen geleden als er nog geen is.
 */
function beheerder_vandaag_sinds(int $beheerder_id): string {
    $week = date('Y-m-d H:i:s', strtotime('-7 days'));
    if ($beheerder_id <= 0) return $week;
    beheerder_bezoek_auto_setup();
    try {
        $q = db()->prepare("SELECT vandaag_gezien_op, vandaag_vorige_op FROM beheerders WHERE id=?");
        $q->execute([$beheerder_id]);
        $r = $q->fetch() ?: [];
        $gezien = (string)($r['vandaag_gezien_op'] ?? '');
        $vorige = (string)($r['vandaag_vorige_op'] ?? '');
        $nu = date('Y-m-d H:i:s');
        if ($gezien === '' || strtotime($gezien) < time() - 3600) {
            // Nieuw bezoek: het vorige "gezien" wordt het ijkpunt.
            $vorige = $gezien !== '' ? $gezien : $week;
            db()->prepare("UPDATE beheerders SET vandaag_vorige_op=?, vandaag_gezien_op=? WHERE id=?")->execute([$vorige, $nu, $beheerder_id]);
        } else {
            db()->prepare("UPDATE beheerders SET vandaag_gezien_op=? WHERE id=?")->execute([$nu, $beheerder_id]);
        }
        return $vorige !== '' ? $vorige : $week;
    } catch (\Throwable $e) { return $week; }
}

} // function_exists
