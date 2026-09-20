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
$_rollen = ['begeleider' => 'begeleider', 'bestuur' => 'bestuur',
            'penningmeester' => 'penningmeester', 'admin' => 'beheerder'];
$_rol    = $_rollen[$geen_rol] ?? $geen_rol;
?>
<div class="ds-kaart" style="max-width:520px;margin:48px auto;padding:8px">
  <div class="bh-leeg ds-fase-grijs" style="border:none">
    <span class="bh-leeg-icoon"><?= icoon('slot', 19) ?></span>
    <span class="bh-leeg-titel">
      <?= $_titel !== '' ? h($_titel) . ' is niet voor jouw rol' : 'Dit onderdeel is niet voor jouw rol' ?>
    </span>
    <span class="bh-leeg-tekst">
      <?= $geen_uitleg
            ? h($geen_uitleg)
            : ($_rol !== ''
                ? 'Jouw rol (' . h($_rol) . ') heeft hier geen inzage. Dat is een bewuste keuze, geen storing.'
                : 'Je hebt hier geen inzage. Dat is een bewuste keuze, geen storing.') ?>
    </span>
    <a class="ds-btn ds-btn-secundair ds-btn-sm" href="<?= APP_URL ?>/admin/">Terug naar Vandaag</a>
    <span class="bh-meta" style="margin-top:4px">Denk je dat dit niet klopt? Vraag een beheerder om toegang.</span>
  </div>
</div>
<?php
$geen_module = ''; $geen_rol = ''; $geen_uitleg = null;
