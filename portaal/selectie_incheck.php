<?php
/**
 * Oud adres van de incheck-tablet. Sinds 2.109.0 is dat portaal/incheck.php (selectiedag én
 * trainingsweekend, één scherm). Bestaande tablet-links blijven zo werken.
 */
require_once __DIR__ . '/../includes/functions.php';
$code = is_string($_GET['code'] ?? null) ? $_GET['code'] : '';
header('Location: ' . APP_URL . '/portaal/incheck.php?ev=selectiedag&code=' . rawurlencode($code), true, 301);
exit;
