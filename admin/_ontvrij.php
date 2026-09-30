<?php
/**
 * Partial: één ontvanger in de "Aan"-lijst van Berichten, met vinkje.
 *
 * Het vinkje is de bron van waarheid: de chips en de teller in berichten.php lezen alleen
 * de .ontv-cb-vinkjes. De groepklasse (fase-<groep>) laat "hele groep aan" werken.
 *
 * Verwacht:
 *   $ontv        array   rij met voornaam/tussenvoegsel/achternaam, email, telefoon,
 *                        kanaal_voorkeur, optioneel afgemeld
 *   $ontv_id     int     wat er verstuurd wordt (sollicitatie-id)
 *   $ontv_groep  string  groepsleutel voor de klasse (een fase, 'oud' of 'seintje')
 *   $ontv_label  string  groepnaam voor de chip ("Aangemeld", "Oud-strandwachten")
 *   $ontv_sub    string  optioneel: extra tekst achter het adres (bijv. "laatst 2025")
 *   $ontv_aan    bool    vooraf aangevinkt
 */
$_naam = volledige_naam($ontv);
$_kv   = (string)($ontv['kanaal_voorkeur'] ?? 'whatsapp');
?>
<label class="bh-ontvrij">
  <input type="checkbox" name="ontvangers[]" value="<?= (int)$ontv_id ?>"
         class="ontv-cb fase-<?= h($ontv_groep) ?>" data-wijzig="updateTeller()"
         data-naam="<?= h($_naam) ?>"
         data-zoek="<?= h(mb_strtolower($_naam . ' ' . (string)($ontv['email'] ?? ''))) ?>"
         data-fase="<?= h($ontv_label) ?>"
         <?= !empty($ontv_aan) ? 'checked' : '' ?>>
  <span class="bh-ontvrij-tekst">
    <span class="bh-ontvrij-naam">
      <?= h($_naam) ?>
      <?php if (!empty($ontv['afgemeld'])): ?><?= bh_chip('afgemeld', 'rood') ?><?php endif; ?>
    </span>
    <span class="bh-ontvrij-sub">
      <?= h((string)($ontv['email'] ?? '')) ?>
      <?php if (!empty($ontv['telefoon'])): ?>
        · <?= h($_kv === 'sms' ? 'sms' : ($_kv === 'email' ? 'alleen e-mail' : 'WhatsApp')) ?>
      <?php else: ?>
        · geen telefoon
      <?php endif; ?>
      <?= !empty($ontv_sub) ? ' · ' . h($ontv_sub) : '' ?>
    </span>
  </span>
</label>
<?php
$ontv = []; $ontv_id = 0; $ontv_groep = ''; $ontv_label = ''; $ontv_sub = ''; $ontv_aan = false;
