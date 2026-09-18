<?php
// Mailings zijn opgegaan in Berichten (alle kanalen, sjablonen, log).
require_once __DIR__ . '/../includes/functions.php';
vereist_login();
$tab = ($_GET['tab'] ?? '') === 'log' ? 'log' : 'nieuw';
header('Location: ' . APP_URL . '/admin/berichten.php?tab=' . $tab, true, 301);
exit;
