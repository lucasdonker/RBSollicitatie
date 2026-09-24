<?php
/**
 * Partial: de kaart "Tweestapsverificatie" in Mijn profiel (admin/profiel.php).
 * Alleen tonen bij een account met een wachtwoord (tweestaps_van_toepassing()).
 *
 * Vier standen, in deze volgorde:
 *   1. net aangezet   → de herstelcodes, één keer (komen alleen uit dit antwoord, nergens bewaard)
 *   2. bezig          → geheim als tekst + otpauth-link, code ter bevestiging
 *   3. aan            → status, uitzetten / opnieuw instellen (wachtwoord + code)
 *   4. uit            → uitleg + "Instellen"
 *
 * Verwacht:
 *   $rec           array   de beheerder-rij (tweestaps_rij())
 *   $ts_fout       array   ['bevestig' => string, 'wijzig' => string]
 *   $herstel_toon  ?array  de zojuist gemaakte herstelcodes, of null
 */
$_ts_id    = (int)($rec['id'] ?? 0);
$_ts_stat  = tweestaps_status($rec) ?? ['tekst' => 'Tweestaps uit', 'kleur' => 'oranje'];
$_ts_nieuw = $herstel_toon ? null : tweestaps_voorlopig($_ts_id);
$ts_fout   = $ts_fout ?? ['bevestig' => '', 'wijzig' => ''];
?>
<section class="ds-kaart ds-kaart-p bh-profiel-breed" id="tweestaps">
  <div class="bh-kaartkop">
    <h2>Tweestapsverificatie</h2>
    <?= bh_badge($herstel_toon ? 'Tweestaps aan' : $_ts_stat['tekst'], $herstel_toon ? 'groen' : $_ts_stat['kleur']) ?>
  </div>

  <?php if ($herstel_toon): ?>
    <div class="ds-melding ds-melding-ok" role="status">
      <?= icoon('vink', 16) ?><span>Tweestaps staat aan. Vanaf nu vraagt inloggen met je wachtwoord ook een code uit je app.</span>
    </div>
    <h3>Je herstelcodes</h3>
    <p class="bh-dossier-tekst">
      Ben je je telefoon kwijt, dan kom je met een van deze codes toch binnen. Elke code werkt één keer.
      <strong>Dit is de enige keer dat je ze ziet</strong> — wij bewaren alleen een onleesbare vingerafdruk.
      Schrijf ze op of print ze en bewaar ze offline, niet in je mail of op dezelfde telefoon.
    </p>
    <ol>
      <?php foreach ($herstel_toon as $_c): ?><li><code class="bh-code"><?= h($_c) ?></code></li><?php endforeach; ?>
    </ol>
    <div class="bh-knoprij">
      <a class="ds-btn ds-btn-primair ds-btn-sm" href="<?= APP_URL ?>/admin/profiel.php">Ik heb ze bewaard</a>
    </div>

  <?php elseif ($_ts_nieuw !== null): ?>
    <ol class="bh-dossier-tekst">
      <li>Open een authenticator-app op je telefoon (bijvoorbeeld Microsoft Authenticator, Google Authenticator,
          2FAS of de wachtwoordmanager die je al gebruikt) en kies "account toevoegen".</li>
      <li>Kies "sleutel invoeren" en neem deze sleutel over (type: tijdgebaseerd):<br>
          <code class="bh-code"><?= h(tweestaps_geheim_groepen($_ts_nieuw)) ?></code><br>
          Op je telefoon zelf kan het ook met één tik:
          <a href="<?= h(tweestaps_otpauth_uri((string)($rec['email'] ?? ''), $_ts_nieuw)) ?>">open in je authenticator-app</a>.</li>
      <li>Vul hieronder de code van 6 cijfers in die de app nu toont.</li>
    </ol>
    <p class="ds-veld-hint">Deel deze sleutel met niemand: wie hem heeft, kan de codes maken.</p>
    <form method="POST" action="<?= APP_URL ?>/admin/profiel.php#tweestaps">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="tweestaps_bevestig">
      <?php $tsv = ['id' => 'ts_bevestig', 'label' => 'Code uit je app', 'autofocus' => true, 'fout' => $ts_fout['bevestig']];
            require __DIR__ . '/_tweestaps_codeveld.php'; ?>
      <div class="bh-knoprij">
        <button type="submit" class="ds-btn ds-btn-primair ds-btn-sm">Bevestigen en aanzetten</button>
      </div>
    </form>
    <form method="POST" action="<?= APP_URL ?>/admin/profiel.php#tweestaps">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="tweestaps_annuleer">
      <div class="bh-knoprij">
        <button type="submit" class="ds-btn ds-btn-zacht ds-btn-sm">Annuleren</button>
      </div>
    </form>

  <?php elseif (tweestaps_aan($rec)): ?>
    <?php $_over = tweestaps_herstel_over($rec); ?>
    <p class="bh-dossier-tekst">
      Aan sinds <?= h(datum_nl(substr((string)$rec['tweestaps_aan_op'], 0, 10))) ?>. Inloggen met je wachtwoord vraagt
      ook een code uit je authenticator-app. Je hebt nog <?= $_over ?> ongebruikte
      herstelcode<?= $_over === 1 ? '' : 's' ?>.
    </p>
    <?php if ($_over <= 2): ?>
      <div class="ds-melding ds-melding-warn"><?= icoon('waarschuwing', 16) ?><span>Je hebt bijna geen herstelcodes meer. Kies "Opnieuw instellen" voor een nieuwe set.</span></div>
    <?php endif; ?>
    <form method="POST" action="<?= APP_URL ?>/admin/profiel.php#tweestaps">
      <?= csrf_input() ?>
      <p class="ds-veld-hint">Nieuwe telefoon, of uitzetten? Bevestig dat jij het bent met je wachtwoord en een code.</p>
      <div class="ds-veld">
        <label class="ds-label" for="ts_ww">Huidig wachtwoord</label>
        <input class="ds-input" type="password" id="ts_ww" name="huidig_wachtwoord" required autocomplete="current-password">
      </div>
      <?php $tsv = ['id' => 'ts_code', 'label' => 'Code uit je app of een herstelcode', 'herstel' => true, 'fout' => $ts_fout['wijzig']];
            require __DIR__ . '/_tweestaps_codeveld.php'; ?>
      <div class="bh-knoprij">
        <button type="submit" name="actie" value="tweestaps_opnieuw" class="ds-btn ds-btn-secundair ds-btn-sm">Opnieuw instellen</button>
        <button type="submit" name="actie" value="tweestaps_uit" class="ds-btn ds-btn-gevaar ds-btn-sm">Uitzetten</button>
      </div>
    </form>

  <?php else: ?>
    <p class="bh-dossier-tekst">
      Met een wachtwoord inloggen vraagt na je wachtwoord nog een tweede stap. Zonder app is dat een code
      per e-mail; met een authenticator-app op je telefoon gaat het sneller en werkt het ook als je mail
      niet bereikbaar is. Dat is ook veiliger: wie je wachtwoord én je mailbox heeft, komt dan nog niet binnen.
    </p>
    <?php if (!tweestaps_kan_instellen()): ?>
      <div class="ds-melding ds-melding-warn"><?= icoon('waarschuwing', 16) ?><span>Instellen kan nu niet: de kluis voor geheimen werkt niet. Meld dit bij de systeembeheerder.</span></div>
    <?php else: ?>
      <form method="POST" action="<?= APP_URL ?>/admin/profiel.php#tweestaps">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="tweestaps_start">
        <div class="bh-knoprij">
          <button type="submit" class="ds-btn ds-btn-primair ds-btn-sm">Instellen</button>
        </div>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</section>
