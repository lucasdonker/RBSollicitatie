<?php
/**
 * Partial: Gegevens corrigeren (AVG art. 16) — naam, e-mail, telefoon, geboortedatum.
 *
 * Een uitklapper binnen de kaart Gegevens (admin/dossier/_tab_dossier.php), dicht tenzij je hier via
 * een link komt (?corrigeer=1#gegevens: de knop "Adres corrigeren" in de lijst en "Gegevens
 * corrigeren" bij een mislukt bericht; dan staat ook de kaart eromheen open). Het
 * formulier is een gewone POST naar admin/api_gegevens.php, dat terugstuurt naar het
 * dossier met een melding; de validatie en het logboek staan in bh_gegevens_corrigeren().
 *
 * Alleen met sollicitanten-bewerken; de aanroeper controleert dat, api_gegevens.php ook.
 *
 * Verwacht: $geg_dossier  array  uitkomst van bh_dossier()
 */
$geg_dossier = $geg_dossier ?? null;
if (!$geg_dossier) return;

$_gs = $geg_dossier['sol'];
$_gid = (int)$_gs['id'];
$_open = !empty($_GET['corrigeer']);
?>
<details class="bh-klap bh-gegevenskaart"<?= $_open ? ' open' : '' ?>>
  <summary>
    <span class="bh-klap-titel">Gegevens corrigeren</span>
    <span class="bh-klap-sam">naam, e-mail, telefoon, geboortedatum</span>
  </summary>

  <form method="post" action="<?= APP_URL ?>/admin/api_gegevens.php" class="bh-veldform">
    <?= csrf_input() ?>
    <input type="hidden" name="sol_id" value="<?= $_gid ?>">
    <?php foreach (bh_soll_filter_params($_GET) as $_fk => $_fv): ?>
      <input type="hidden" name="f[<?= h((string)$_fk) ?>]" value="<?= h((string)$_fv) ?>">
    <?php endforeach; ?>

    <div class="bh-veldrij">
      <div class="ds-veld">
        <label for="geg-voornaam-<?= $_gid ?>">Voornaam</label>
        <input type="text" id="geg-voornaam-<?= $_gid ?>" name="voornaam" maxlength="100" required
               autocomplete="off" value="<?= h((string)($_gs['voornaam'] ?? '')) ?>">
      </div>
      <div class="ds-veld">
        <label for="geg-tv-<?= $_gid ?>">Tussenvoegsel</label>
        <input type="text" id="geg-tv-<?= $_gid ?>" name="tussenvoegsel" maxlength="30"
               autocomplete="off" value="<?= h((string)($_gs['tussenvoegsel'] ?? '')) ?>">
      </div>
    </div>
    <div class="ds-veld">
      <label for="geg-achternaam-<?= $_gid ?>">Achternaam</label>
      <input type="text" id="geg-achternaam-<?= $_gid ?>" name="achternaam" maxlength="100" required
             autocomplete="off" value="<?= h((string)($_gs['achternaam'] ?? '')) ?>">
    </div>
    <div class="bh-veldrij">
      <div class="ds-veld">
        <label for="geg-email-<?= $_gid ?>">E-mailadres</label>
        <input type="email" id="geg-email-<?= $_gid ?>" name="email" maxlength="200" required
               autocomplete="off" value="<?= h((string)($_gs['email'] ?? '')) ?>">
        <div class="ds-veld-hint">Hoort bij één persoon; het portaal logt hiermee in.</div>
      </div>
      <div class="ds-veld">
        <label for="geg-tel-<?= $_gid ?>">Telefoon</label>
        <input type="tel" id="geg-tel-<?= $_gid ?>" name="telefoon" maxlength="30"
               autocomplete="off" value="<?= h((string)($_gs['telefoon'] ?? '')) ?>">
      </div>
    </div>
    <div class="ds-veld">
      <label for="geg-geb-<?= $_gid ?>">Geboortedatum</label>
      <input type="date" id="geg-geb-<?= $_gid ?>" name="geboortedatum" max="<?= date('Y-m-d') ?>"
             value="<?= h((string)($_gs['geboortedatum'] ?? '')) ?>">
      <?php // Twee aparte grenzen: 16 (aanmeldleeftijd, AVG) en 18 (ondertekenen via een ouder). ?>
      <div class="ds-veld-hint">
        Gaat iemand hierdoor over de grens van 16 of 18 jaar, dan zie je na het opslaan een
        waarschuwing. Klaargezette documenten veranderen niet vanzelf.
      </div>
    </div>

    <div class="bh-veldform-voet">
      <button type="submit" class="ds-btn ds-btn-primair ds-btn-sm">Gegevens opslaan</button>
      <span class="bh-meta">In het logboek komt welke velden zijn gewijzigd, niet de nieuwe e-mail of het nummer.</span>
    </div>
  </form>
</details>
<?php
$geg_dossier = null;
