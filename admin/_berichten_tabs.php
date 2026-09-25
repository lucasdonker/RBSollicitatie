<?php
/**
 * Partial: de tabbladen van Berichten.
 *
 * Nieuw bericht en Verzonden zijn tabs van berichten.php; Gepland is een eigen bestand
 * (geplande_berichten.php), want het doet genoeg anders. Voor wie ermee werkt is het één
 * scherm onder één nav-item, dus de wissel hoort hier en niet in de zijbalk.
 *
 * Gepland verschijnt alleen met 'mailing' bewerken: dat is wat het scherm zelf eist, en
 * een tab die op een 403 uitkomt is erger dan geen tab.
 *
 * Verwacht: $bt_actief  'nieuw' | 'log' | 'gepland' | 'sjablonen'
 */
$bt_actief = $bt_actief ?? 'nieuw';

$_bt = [
    'nieuw'   => ['Nieuw bericht', APP_URL . '/admin/berichten.php?tab=nieuw', true],
    'log'     => ['Verzonden',     APP_URL . '/admin/berichten.php?tab=log',   true],
    'gepland' => ['Gepland',       APP_URL . '/admin/geplande_berichten.php',  !function_exists('mag') || mag('mailing', 'bewerken')],
    // Sjablonen (mail_templates.php) hoort bij Berichten, niet bij Instellingen: het zijn de teksten van de berichten.
    'sjablonen' => ['Sjablonen',   APP_URL . '/admin/mail_templates.php',      !function_exists('mag') || mag('mailing', 'bewerken')],
];
?>
<div class="bh-tabs" role="tablist" aria-label="Berichten">
  <?php foreach ($_bt as $_k => $_t): ?>
    <?php if (!$_t[2]) continue; ?>
    <a class="bh-tab<?= $bt_actief === $_k ? ' actief' : '' ?>" href="<?= h($_t[1]) ?>" role="tab"
       aria-selected="<?= $bt_actief === $_k ? 'true' : 'false' ?>"><?= h($_t[0]) ?></a>
  <?php endforeach; ?>
</div>
<?php
$bt_actief = 'nieuw';
