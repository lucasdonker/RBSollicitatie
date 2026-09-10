<?php
/**
 * Paal 13 — Toegangskeuze na login.
 *
 * Bepaalt welke delen van het systeem iemand mag gebruiken (sollicitantenportaal en/of
 * beheer), onthoudt de persoonlijke voorkeur, en helpt bij het schakelen ertussen.
 *
 * Achtergrond (fase 3): na Authentik-login kan iemand zowel beheerder/begeleider als
 * sollicitant zijn. Die persoon kiest waar hij heen wil; de keuze wordt onthouden en is
 * makkelijk te wisselen.
 */

if (!function_exists('toegang_bepaal')) {

/**
 * Bepaal op basis van e-mail welke ingangen iemand heeft.
 * Retourneert ['beheer' => bool, 'portaal' => bool, 'beheerder' => array|null, 'rol' => string|null].
 */
function toegang_bepaal(string $email): array {
    $email = strtolower(trim($email));
    $uit = ['beheer' => false, 'portaal' => false, 'beheerder' => null, 'rol' => null];
    if ($email === '') return $uit;

    try {
        $db = db();
        // Beheerder?
        $b = $db->prepare("SELECT * FROM beheerders WHERE email = ? LIMIT 1");
        $b->execute([$email]);
        $beheerder = $b->fetch();
        if ($beheerder) {
            $uit['beheer'] = true;
            $uit['beheerder'] = $beheerder;
            $uit['rol'] = $beheerder['rol'] ?? 'begeleider';
        }
        // Sollicitant?
        $s = $db->prepare("SELECT s.id FROM sollicitaties s JOIN personen p ON p.id = s.persoon_id WHERE p.email = ? LIMIT 1");
        $s->execute([$email]);
        if ($s->fetch()) $uit['portaal'] = true;
    } catch (\Exception $e) {
        error_log('toegang_bepaal: ' . $e->getMessage());
    }
    return $uit;
}

/**
 * Haal de voorkeur van een beheerder op ('beheer' of 'portaal').
 * Standaard 'beheer' als er niets is ingesteld (want beheerders werken meestal in het beheer).
 */
function toegang_voorkeur(int $beheerder_id): string {
    if ($beheerder_id <= 0) return 'portaal';
    try {
        $q = db()->prepare("SELECT standaard_ingang FROM beheerders WHERE id = ?");
        $q->execute([$beheerder_id]);
        $v = $q->fetchColumn();
        if ($v === 'portaal' || $v === 'beheer') return $v;
    } catch (\Exception $e) {}
    return 'beheer';
}

/** Sla de voorkeur van een beheerder op. */
function toegang_voorkeur_opslaan(int $beheerder_id, string $ingang): bool {
    if ($beheerder_id <= 0 || !in_array($ingang, ['beheer', 'portaal'], true)) return false;
    try {
        // Zorg dat de kolom bestaat (failsafe-stijl)
        if (function_exists('db_kolom_verzeker')) {
            db_kolom_verzeker(db(), 'beheerders', 'standaard_ingang', "VARCHAR(10) DEFAULT NULL");
        }
        db()->prepare("UPDATE beheerders SET standaard_ingang = ? WHERE id = ?")->execute([$ingang, $beheerder_id]);
        return true;
    } catch (\Exception $e) {
        error_log('toegang_voorkeur_opslaan: ' . $e->getMessage());
        return false;
    }
}

/**
 * Log iemand in als beheerder op basis van een e-mail (voor de keuze-flow).
 * Gebruikt de bestaande login_beheerder(). Retourneert true bij succes.
 */
function toegang_login_als_beheerder(string $email, string $via = 'sso'): bool {
    try {
        $q = db()->prepare("SELECT * FROM beheerders WHERE email = ? LIMIT 1");
        $q->execute([strtolower(trim($email))]);
        $b = $q->fetch();
        if (!$b) return false;
        login_beheerder($b, $via);
        return true;
    } catch (\Exception $e) {
        error_log('toegang_login_als_beheerder: ' . $e->getMessage());
        return false;
    }
}

/**
 * Log iemand in als sollicitant op basis van een e-mail (voor de keuze-flow).
 * Retourneert true bij succes.
 */
function toegang_login_als_sollicitant(string $email): bool {
    try {
        $q = db()->prepare("SELECT s.id FROM sollicitaties s JOIN personen p ON p.id = s.persoon_id WHERE p.email = ? ORDER BY s.aangemeld_op DESC LIMIT 1");
        $q->execute([strtolower(trim($email))]);
        $sol = $q->fetch();
        if (!$sol) return false;
        login_portaal((int)$sol['id'], strtolower(trim($email)));
        return true;
    } catch (\Exception $e) {
        error_log('toegang_login_als_sollicitant: ' . $e->getMessage());
        return false;
    }
}

}
