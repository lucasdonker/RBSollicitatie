<?php
/**
 * Partial: "geen rechten".
 *
 * Geen rechten is geen storing (ontwerpfilosofie §8): het is een keuze van de
 * beheerder. Dus geen rood alarm en geen foutcode, maar een uitleg en een weg vooruit.
 * Wat je niet hoort te zien staat sowieso niet in het menu — deze pagina zie je
 * alleen via een gedeelde link of een bladwijzer.
 *
 * Verwacht:
 *   $geen_module  string  de modulenaam, voor de titel
 *   $geen_rol     string  de rol van de gebruiker
 *   $geen_uitleg  ?string optioneel: eigen tekst in plaats van de standaardzin
 */
$geen_module = $geen_module ?? '';
$geen_rol    = $geen_rol ?? '';
$geen_uitleg = $geen_uitleg ?? null;

$_labels = function_exists('rechten_modules') ? rechten_modules() : [];
$_titel  = $_labels[$geen_module]['label'] ?? $geen_module;
$_rol    = $geen_rol !== '' && function_exists('rol_naam') ? mb_strtolower(rol_naam($geen_rol)) : $geen_rol;
// Staat de hele module uit, dan ligt het niet aan de rol: zeg dat, en waar hij aan kan.
$_module_uit = function_exists('module_recht_actief') && $geen_module !== '' && !module_recht_actief($geen_module);
if ($_module_uit) {
    $_def   = modules_alle()[module_van_recht($geen_module)] ?? [];
    $_titel = (string)($_def['label'] ?? $geen_module);
    $geen_uitleg = $geen_uitleg ?? 'Deze module staat uit voor dit systeem. Een beheerder kan hem aanzetten bij Instellingen › Modules; de gegevens zijn bewaard gebleven.';
}
?>
<div class="ds-kaart bh-losse-kaart">
  <div class="bh-leeg ds-fase-grijs bh-leeg-kaal">
    <span class="bh-leeg-icoon"><?= icoon('slot', 19) ?></span>
    <span class="bh-leeg-titel">
      <?= $_module_uit ? h($_titel) . ' staat uit'
            : ($_titel !== '' ? h($_titel) . ' is niet voor jouw rol' : 'Dit onderdeel is niet voor jouw rol') ?>
    </span>
    <span class="bh-leeg-tekst">
      <?= $geen_uitleg
            ? h($geen_uitleg)
            : ($_rol !== ''
                ? 'Jouw rol (' . h($_rol) . ') heeft hier geen inzage. Dat is een bewuste keuze, geen storing.'
                : 'Je hebt hier geen inzage. Dat is een bewuste keuze, geen storing.') ?>
    </span>
    <a class="ds-btn ds-btn-secundair ds-btn-sm" href="<?= APP_URL ?>/admin/">Terug naar Vandaag</a>
    <span class="bh-meta bh-leeg-voetnoot">Denk je dat dit niet klopt? Vraag een beheerder om toegang.</span>
  </div>
</div>
<?php
$geen_module = ''; $geen_rol = ''; $geen_uitleg = null;
