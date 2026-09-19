<?php
/**
 * Auditlogboek — opgegaan in het Logboek (2.93.0), dat alle zes logbronnen naast elkaar toont.
 * Dit bestand blijft als doorverwijzing staan voor oude links en bladwijzers.
 */
require_once __DIR__ . '/../includes/functions.php';

$q = $_GET;
$q['bron'] = 'audit';                 // dit scherm liet alleen audit_log zien
unset($q['p']);                       // paginanummer heeft in het nieuwe filter geen betekenis meer
redirect(APP_URL . '/admin/logboek.php' . ($q ? '?' . http_build_query($q) : '?bron=audit'));
