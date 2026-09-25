<?php
/**
 * Groepsindeling van de selectiedag — bestuur, op een laptop.
 *
 * Het scherm en de handelingen zijn gedeeld met het trainingsweekend:
 * admin/_groepsindeling.php (weergave) en includes/beheer/groepsindeling.php (POST).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';

vereis_recht('selectiedag', 'lezen');

$seizoen = actief_seizoen();
$sid = $seizoen ? (int)$seizoen['id'] : 0;
$mag_bewerken = mag('selectiedag', 'bewerken');
$kandidaten = $sid ? selectie_kandidaten($sid) : [];
$_url = APP_URL . '/admin/selectie_indeling.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sid) {
    csrf_check();
    if (!$mag_bewerken) { http_response_code(403); exit; }
    if (!bh_groepsindeling_post('selectiedag', $sid, $kandidaten, $_url)) { http_response_code(400); exit('Onbekende actie.'); }
}

$pagina_titel = 'Groepsindeling';
$actieve_nav  = 'selectiedag';
require __DIR__ . '/layout_top.php';
?>

<?php
  $subnav_actief = 'groepen';
  $subnav_datum  = $seizoen && !empty($seizoen['datum_selectiedag']) ? datum_nl($seizoen['datum_selectiedag']) : null;
  require __DIR__ . '/_selectie_subnav.php';
?>

<?php if (!$sid || !$kandidaten): ?>
  <div class="ds-kaart ds-kaart-p">
    <?php
      $leeg_titel = $sid ? 'Nog niemand uitgenodigd' : 'Geen actief seizoen';
      $leeg_tekst = $sid ? 'Zodra er kandidaten voor de selectiedag zijn uitgenodigd, verdeel je ze hier in groepen.' : '';
      $leeg_icoon = 'mensen';
      $leeg_knop  = $sid && mag('sollicitanten', 'lezen') ? ['tekst' => 'Naar sollicitanten', 'url' => APP_URL . '/admin/sollicitanten.php'] : null;
      require __DIR__ . '/_leeg.php';
    ?>
  </div>
<?php else: ?>
  <?php
    $gi_ev = 'selectiedag'; $gi_kandidaten = $kandidaten; $gi_groepen = selectie_groepen($sid);
    $gi_relaties = evenement_relaties($sid); $gi_mag_bewerken = $mag_bewerken; $gi_endpoint = $_url;
    require __DIR__ . '/_groepsindeling.php';
  ?>
<?php endif; ?>
<?php require __DIR__ . '/layout_bottom.php'; ?>
