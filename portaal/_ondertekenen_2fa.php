<?php
/**
 * Partial: verificatiecode (2FA) in de tekenkaart van ondertekenen.php.
 * Verwacht $rec, $token, $u (u-vorm), $tel_masker (gemaskeerd nummer of ''), $v (waarden na fout).
 * De code wordt via portaal/api_ondertekenen_code.php gestuurd (JS); het veld 'code' gaat mee in de POST.
 */
require_once __DIR__ . '/../includes/functions.php';
portaal_alleen_include(__FILE__);
$_kanaal = function_exists('sms_actief') && sms_actief() ? 'sms' : ((function_exists('wa_actief') && wa_actief()) ? 'whatsapp' : 'email');
$_waar   = $tel_masker !== '' && $_kanaal !== 'email'
    ? (($_kanaal === 'sms' ? 'per sms' : 'via WhatsApp') . ' naar ' . $tel_masker)
    : 'per e-mail';
?>
<div class="ond-2fa" id="ond2fa" data-token="<?= h($token) ?>" data-api="<?= APP_URL ?>/portaal/api_ondertekenen_code.php">
  <div class="ond-2fa-kop">
    <span class="ond-2fa-titel"><?= icoon('schild', 18) ?>Verificatiecode</span>
    <span class="ond-2fa-sub">Ter bevestiging dat <?= $u ? 'u' : 'jij' ?> het bent: <?= $u ? 'u krijgt' : 'je krijgt' ?> een code <?= h($_waar) ?>.</span>
  </div>
  <div class="ond-2fa-stap1" id="ond2faStap1">
    <button type="button" class="ond-2fa-knop" id="ond2faStuur"><?= icoon('sms', 16) ?>Code sturen</button>
    <span class="ond-2fa-fout" id="ond2faFout" role="alert" hidden></span>
  </div>
  <div class="ond-2fa-stap2" id="ond2faStap2" hidden>
    <span class="ond-2fa-status" id="ond2faStatus"></span>
    <label class="ds-veld ond-veld ond-2fa-veld">
      <span class="ond-lbl">Code <span class="ster">*</span></span>
      <input type="text" name="code" id="ond2faCode" class="ds-input ond-2fa-input" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" placeholder="000000" value="<?= $v('code') ?>">
    </label>
    <button type="button" class="ond-2fa-opnieuw" id="ond2faOpnieuw">Geen code ontvangen? Opnieuw sturen</button>
  </div>
</div>
