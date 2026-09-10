<?php
/**
 * Paal 13 — Auditlogboek.
 *
 * Legt vast wie wat met welke gegevens doet (en, optioneel, bekijkt), zonder de gebruiker
 * te laten wachten. De truc: gebeurtenissen worden tijdens de request in het geheugen
 * verzameld en pas ná de response naar de database geschreven (via fastcgi_finish_request
 * op PHP-FPM, met register_shutdown_function als terugval).
 *
 * Gebruik:
 *   audit_log('gewijzigd', 'sollicitant', 42, ['oud' => ..., 'nieuw' => ...]);
 *   audit_log('bekeken', 'beschikbaarheid', 42);
 *
 * Faalt NOOIT hard: een probleem met de logging mag een pagina niet breken.
 */

if (!function_exists('audit_log')) {

/** In-memory buffer van gebeurtenissen voor deze request. */
$GLOBALS['_audit_buffer'] = [];
$GLOBALS['_audit_shutdown_geregistreerd'] = false;

function audit_dag_auto_setup(): void {
    static $done = false; if ($done) return; $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS `audit_log` (
            `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `moment` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `wie_type` ENUM('beheerder','sollicitant','systeem') NOT NULL DEFAULT 'systeem',
            `wie_id` INT DEFAULT NULL,
            `wie_naam` VARCHAR(150) DEFAULT NULL,
            `actie` VARCHAR(40) NOT NULL,
            `objecttype` VARCHAR(60) DEFAULT NULL,
            `object_id` INT DEFAULT NULL,
            `details` TEXT DEFAULT NULL,
            `ip` VARCHAR(45) DEFAULT NULL,
            `browser` VARCHAR(255) DEFAULT NULL,
            KEY `idx_moment` (`moment`),
            KEY `idx_wie` (`wie_type`, `wie_id`),
            KEY `idx_object` (`objecttype`, `object_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Exception $e) { error_log('audit_dag_auto_setup: ' . $e->getMessage()); }
}

/**
 * Bepaal wie de actie doet, op basis van de sessie.
 * Beheerder heeft voorrang; anders sollicitant (portaal); anders systeem.
 */
function audit_wie(): array {
    // Beheerder ingelogd?
    if (!empty($_SESSION['bhd_id'])) {
        return ['type' => 'beheerder', 'id' => (int)$_SESSION['bhd_id'], 'naam' => $_SESSION['bhd_naam'] ?? ''];
    }
    // Sollicitant via portaal?
    if (!empty($_SESSION['portaal_sol_id'])) {
        return ['type' => 'sollicitant', 'id' => (int)$_SESSION['portaal_sol_id'], 'naam' => $_SESSION['portaal_email'] ?? ''];
    }
    return ['type' => 'systeem', 'id' => null, 'naam' => null];
}

/**
 * Leg een gebeurtenis vast. Schrijft NIET meteen weg — verzamelt in het geheugen.
 *
 * @param string      $actie      bekeken|aangemaakt|gewijzigd|verwijderd|geexporteerd|ingelogd|uitgelogd|mail_verstuurd|...
 * @param string|null $objecttype sollicitant|beschikbaarheid|mailtemplate|rol|seizoen|...
 * @param int|null    $object_id  id van het object
 * @param array|null  $details    vrije context (bijv. ['oud'=>.., 'nieuw'=>..]); wordt JSON
 */
function audit_log(string $actie, ?string $objecttype = null, ?int $object_id = null, ?array $details = null): void {
    try {
        $wie = audit_wie();
        $GLOBALS['_audit_buffer'][] = [
            'wie_type'   => $wie['type'],
            'wie_id'     => $wie['id'],
            'wie_naam'   => $wie['naam'] ? mb_substr($wie['naam'], 0, 150) : null,
            'actie'      => mb_substr($actie, 0, 40),
            'objecttype' => $objecttype ? mb_substr($objecttype, 0, 60) : null,
            'object_id'  => $object_id,
            'details'    => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
            'browser'    => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
        ];
        audit_registreer_wegschrijven();
    } catch (\Throwable $e) {
        error_log('audit_log: ' . $e->getMessage());  // nooit hard falen
    }
}

/**
 * Zorg dat de buffer ná de response wordt weggeschreven. Één keer registreren per request.
 */
function audit_registreer_wegschrijven(): void {
    if ($GLOBALS['_audit_shutdown_geregistreerd']) return;
    $GLOBALS['_audit_shutdown_geregistreerd'] = true;
    register_shutdown_function('audit_flush');
}

/**
 * Schrijf de verzamelde gebeurtenissen weg. Wordt aangeroepen bij shutdown.
 * Als PHP-FPM beschikbaar is, is de response op dat moment al verstuurd, dus de
 * gebruiker merkt hier niets van.
 */
function audit_flush(): void {
    $buffer = $GLOBALS['_audit_buffer'] ?? [];
    if (!$buffer) return;
    $GLOBALS['_audit_buffer'] = [];  // voorkom dubbel wegschrijven

    // Response afsluiten zodat de gebruiker niet wacht (PHP-FPM).
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    }

    try {
        audit_dag_auto_setup();
        $db = db();
        $sql = "INSERT INTO audit_log (wie_type, wie_id, wie_naam, actie, objecttype, object_id, details, ip, browser)
                VALUES (?,?,?,?,?,?,?,?,?)";
        $stmt = $db->prepare($sql);
        foreach ($buffer as $r) {
            $stmt->execute([
                $r['wie_type'], $r['wie_id'], $r['wie_naam'], $r['actie'],
                $r['objecttype'], $r['object_id'], $r['details'], $r['ip'], $r['browser'],
            ]);
        }
    } catch (\Throwable $e) {
        error_log('audit_flush: ' . $e->getMessage());
    }
}

/**
 * Log-helper voor een wijziging met oude → nieuwe waarden. Slaat alleen de velden op
 * die daadwerkelijk veranderd zijn (compacte details).
 */
function audit_wijziging(string $objecttype, int $object_id, array $oud, array $nieuw): void {
    $verschil = [];
    foreach ($nieuw as $veld => $nieuwe_waarde) {
        $oude_waarde = $oud[$veld] ?? null;
        if ($oude_waarde != $nieuwe_waarde) {
            $verschil[$veld] = ['oud' => $oude_waarde, 'nieuw' => $nieuwe_waarde];
        }
    }
    if ($verschil) audit_log('gewijzigd', $objecttype, $object_id, $verschil);
}

}
