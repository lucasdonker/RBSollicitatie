<?php
/**
 * Beheer — Beheerders: wie kan er in het beheer, en met welke rol?
 *
 * Twee manieren om binnen te komen, en dat moet zichtbaar zijn: via Authentik (SSO,
 * de normale weg) of met een lokaal wachtwoord (de terugval als Authentik plat ligt).
 * Een account met alleen SSO heeft geen wachtwoord — dat is goed nieuws en geen gat.
 *
 * Elke wijziging hier verandert wie bij persoonsgegevens kan. Daarom schrijft elke
 * actie een auditregel, en gaat deactiveren via een bevestiging die zegt wat het doet.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';
require_once __DIR__ . '/../includes/tweestaps.php';
$pagina_titel = 'Beheerders & rechten';
$actieve_nav  = 'beheerders';
vereis_recht('beheerders', 'bewerken');

$db   = db();
$fout = '';
$mij  = (int)(huidige_beheerder()['id'] ?? 0);
// Tweestaps resetten (telefoon kwijt) is voorbehouden aan de rol admin, niet aan elk
// account met het recht "beheerders": het haalt een beveiliging van iemand anders weg.
$ben_admin = (huidige_beheerder()['rol'] ?? '') === 'admin';
tweestaps_setup();

/** Eén beheerder, of null. */
function beheerder_rij($db, int $id): ?array {
    $q = $db->prepare("SELECT * FROM beheerders WHERE id=?");
    $q->execute([$id]);
    return $q->fetch() ?: null;
}

$rolnamen = [];
foreach (rollen_alle() as $_r) $rolnamen[$_r['sleutel']] = $_r['naam'];

// Het recht "beheerders" mag geen opstap naar admin zijn: een admin-account aanpassen
// (rol, wachtwoord, uitzetten) en de rol admin toekennen kan alleen een admin.
$mag_aanpassen = fn(?array $b): bool => $b !== null && ($ben_admin || ($b['rol'] ?? '') !== 'admin');
$mag_toekennen = fn(string $rol): bool => isset($rolnamen[$rol]) && ($ben_admin || $rol !== 'admin');

// ── Verwerken ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $actie = (string)($_POST['actie'] ?? '');
    $bid   = (int)($_POST['bid'] ?? 0);

    if (in_array($actie, ['wachtwoord', 'rol', 'deactiveer', 'activeer'], true) && !$mag_aanpassen(beheerder_rij($db, $bid))) {
        $fout = 'Een account met de rol ' . ($rolnamen['admin'] ?? 'admin') . ' kan alleen door een ' . ($rolnamen['admin'] ?? 'admin') . ' worden aangepast.';
        $actie = '';
    }

    if ($actie === 'nieuw') {
        $naam  = trim((string)($_POST['naam'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $ww    = (string)($_POST['wachtwoord'] ?? '');
        $rol   = (string)($_POST['rol'] ?? 'bestuur');
        if ($naam === '' || $email === '' || $ww === '') {
            $fout = 'Vul een naam, een e-mailadres en een tijdelijk wachtwoord in.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $fout = 'Dat e-mailadres klopt niet.';
        } elseif (strlen($ww) < 8) {
            $fout = 'Het wachtwoord moet minstens 8 tekens hebben.';
        } elseif (!$mag_toekennen($rol)) {
            $fout = 'Die rol kun je niet toekennen.';
        } else {
            try {
                $db->prepare("INSERT INTO beheerders (naam,email,wachtwoord,rol) VALUES(?,?,?,?)")
                   ->execute([$naam, $email, password_hash($ww, PASSWORD_DEFAULT), $rol]);
                // Wie toegang krijgt tot het beheer hoort herleidbaar te zijn. Nooit het wachtwoord.
                audit_log('beheerder_aangemaakt', 'beheerder', (int)$db->lastInsertId(),
                          ['naam' => $naam, 'email' => $email, 'rol' => $rol]);
                flash('succes', $naam . ' kan nu inloggen.');
            } catch (Exception $e) {
                $fout = 'Er bestaat al een account met dat e-mailadres.';
            }
        }
    }

    if ($actie === 'wachtwoord') {
        $ww = (string)($_POST['nieuw_wachtwoord'] ?? '');
        if (strlen($ww) < 8) {
            $fout = 'Het wachtwoord moet minstens 8 tekens hebben.';
        } else {
            $db->prepare("UPDATE beheerders SET wachtwoord=? WHERE id=?")
               ->execute([password_hash($ww, PASSWORD_DEFAULT), $bid]);
            // Alleen dát het gebeurde, nooit het wachtwoord zelf of de hash.
            audit_log('beheerder_gewijzigd', 'beheerder', $bid, ['wachtwoord' => 'opnieuw ingesteld']);
            flash('succes', 'Het wachtwoord is opnieuw ingesteld. Geef het zelf door — het staat nergens anders.');
        }
    }

    if ($actie === 'rol') {
        $nieuw = (string)($_POST['rol'] ?? '');
        $oud   = (string)(beheerder_rij($db, $bid)['rol'] ?? '');
        if ($bid === $mij) {
            $fout = 'Je kunt je eigen rol niet wijzigen. Vraag een andere beheerder.';
        } elseif (!$mag_toekennen($nieuw)) {
            $fout = 'Die rol kun je niet toekennen.';
        } elseif ($nieuw !== $oud) {
            $db->prepare("UPDATE beheerders SET rol=? WHERE id=?")->execute([$nieuw, $bid]);
            audit_log('beheerder_gewijzigd', 'beheerder', $bid, ['rol' => ['oud' => $oud, 'nieuw' => $nieuw]]);
            flash('succes', 'Rol bijgewerkt.');
        }
    }

    if ($actie === 'deactiveer' || $actie === 'activeer') {
        $aan = $actie === 'activeer';
        if (!$aan && $bid === $mij) {
            $fout = 'Je kunt je eigen account niet uitzetten — dan kom je er zelf niet meer in.';
        } else {
            $db->prepare("UPDATE beheerders SET actief=? WHERE id=?")->execute([$aan ? 1 : 0, $bid]);
            audit_log('beheerder_gewijzigd', 'beheerder', $bid,
                      ['actief' => ['oud' => $aan ? 'nee' : 'ja', 'nieuw' => $aan ? 'ja' : 'nee']]);
            flash('succes', $aan ? 'Account weer actief.' : 'Account uitgezet — deze persoon kan niet meer inloggen.');
        }
    }

    if ($actie === 'tweestaps_reset') {
        $doel = beheerder_rij($db, $bid);
        if (!$ben_admin) {
            $fout = 'Alleen een beheerder met de rol Beheerder kan tweestaps resetten.';
        } elseif ($doel && tweestaps_aan($doel)) {
            tweestaps_uitschakelen($bid);
            audit_log('tweestaps_gereset', 'beheerder', $bid, ['naam' => (string)$doel['naam']]);
            flash('succes', 'Tweestaps van ' . $doel['naam'] . ' is gereset. Bij de volgende wachtwoordlogin krijgt '
                . $doel['naam'] . ' een code per e-mail en kan de app opnieuw worden ingesteld in Mijn profiel.');
        }
    }

    if (!$fout) redirect(APP_URL . '/admin/beheerders.php');
}

// ── Bevestiging als tussenscherm ─────────────────────────────
$bev_actie = (string)($_GET['bevestig'] ?? '');
$bev_b     = null;
if (in_array($bev_actie, ['deactiveer', 'activeer', 'tweestaps_reset'], true)) {
    $bev_b = beheerder_rij($db, (int)($_GET['id'] ?? 0));
    if (!$mag_aanpassen($bev_b) || ($bev_actie === 'tweestaps_reset' && (!$ben_admin || !tweestaps_aan($bev_b)))) { $bev_b = null; $bev_actie = ''; }
}

$beheerders = $db->query("SELECT * FROM beheerders ORDER BY actief DESC, rol DESC, naam")->fetchAll();

require __DIR__ . '/layout_top.php';
?>

<?php if ($bev_b): $_uit = $bev_actie === 'deactiveer'; ?>
  <?php
  if ($bev_actie === 'tweestaps_reset') {
    $bev = [
      'titel'  => 'Tweestaps van ' . $bev_b['naam'] . ' resetten?',
      'regels' => ['Doe dit alleen als ' . $bev_b['naam'] . ' de telefoon met de authenticator-app kwijt is én geen herstelcodes meer heeft. Check eerst dat het verzoek echt van deze persoon komt (bel of spreek elkaar).',
                   'De app-koppeling en alle herstelcodes vervallen. Inloggen met het wachtwoord vraagt daarna een code per e-mail.',
                   $bev_b['naam'] . ' kan de app daarna zelf opnieuw instellen in Mijn profiel.'],
      'bericht'      => 'Dit wordt vastgelegd in het logboek.',
      'onomkeerbaar' => true,
      'knop'         => 'Ja, tweestaps resetten',
      'toon'         => 'gevaar',
    ];
  } else {
  $bev = [
      'titel'  => ($_uit ? 'Account van ' : 'Account van ') . $bev_b['naam'] . ($_uit ? ' uitzetten?' : ' weer aanzetten?'),
      'regels' => $_uit
          ? [$bev_b['naam'] . ' kan daarna niet meer inloggen, ook niet via Authentik.',
             'Het account blijft bestaan; wat deze persoon eerder deed blijft in het logboek staan.',
             'Je kunt het later weer aanzetten.']
          : [$bev_b['naam'] . ' kan daarna weer inloggen met de rol ' . ($rolnamen[$bev_b['rol']] ?? $bev_b['rol']) . '.'],
      'bericht'      => null,
      'onomkeerbaar' => false,
      'knop'         => $_uit ? 'Ja, uitzetten' : 'Ja, aanzetten',
      'toon'         => $_uit ? 'gevaar' : 'primair',
  ];
  }
  $bev_form  = ['actie' => APP_URL . '/admin/beheerders.php',
                'velden' => ['actie' => $bev_actie, 'bid' => (int)$bev_b['id']]];
  $bev_terug = APP_URL . '/admin/beheerders.php';
  require __DIR__ . '/_bevestig.php';
  ?>
  <?php require __DIR__ . '/layout_bottom.php'; ?>
  <?php return; ?>
<?php endif; ?>

<?php $bt_actief = 'beheerders'; require __DIR__ . '/_beheer_tabs.php'; ?>

<?php if ($fout): ?>
  <div class="ds-melding ds-melding-error bh-blokkade"><?= icoon('waarschuwing', 18) ?><span><?= h($fout) ?></span></div>
<?php endif; ?>

<div class="bh-beheerders-kolommen">
  <div class="bh-beheerders-hoofd">
    <section class="ds-kaart bh-beheerderslijst">
      <div class="bh-loglijst-kop">
        <h2>Beheerders</h2>
        <span class="bh-meta"><?= count($beheerders) ?> <?= count($beheerders) === 1 ? 'account' : 'accounts' ?></span>
      </div>

      <ul class="bh-bhlijst">
        <?php foreach ($beheerders as $b):
          $_actief = !empty($b['actief']);
          $_ik     = (int)$b['id'] === $mij;
        ?>
        <li class="bh-bhrij<?= $_actief ? '' : ' uit' ?>">
          <span class="bh-bhrij-wie">
            <?= bh_avatar(['voornaam' => (string)$b['naam'], 'achternaam' => ''], 34, ['dim' => !$_actief]) ?>
            <span class="bh-bhrij-tekst">
              <span class="bh-bhrij-naam">
                <?= h((string)$b['naam']) ?>
                <?php if ($_ik): ?><?= bh_chip('jij', 'blauw') ?><?php endif; ?>
                <?php if (!$_actief): ?><?= bh_badge('Uitgezet', 'grijs') ?><?php endif; ?>
              </span>
              <span class="bh-bhrij-sub"><?= h((string)$b['email']) ?></span>
            </span>
          </span>

          <span class="bh-bhrij-login">
            <?php if (!empty($b['authentik_uid'])): ?>
              <span class="bh-bhrij-via" title="Logt in via Authentik"><?= icoon('schild', 14) ?> Authentik</span>
            <?php endif; ?>
            <?php if (!empty($b['wachtwoord'])): ?>
              <span class="bh-bhrij-via" title="Kan ook inloggen met een wachtwoord in dit systeem"><?= icoon('sleutel', 14) ?> Wachtwoord</span>
              <?php $_ts = tweestaps_status($b); ?>
              <?= bh_badge($_ts['tekst'], $_ts['kleur'], ['titel' => tweestaps_aan($b)
                  ? 'Wachtwoordlogin vraagt een code uit een authenticator-app'
                  : 'Wachtwoordlogin vraagt een code per e-mail; een app is nog niet ingesteld']) ?>
            <?php endif; ?>
            <?php if (empty($b['authentik_uid']) && empty($b['wachtwoord'])): ?>
              <span class="bh-bhrij-via let-op" title="Dit account kan op geen enkele manier inloggen">
                <?= icoon('waarschuwing', 14) ?> Geen inlogmethode
              </span>
            <?php endif; ?>
            <?php $_extra = array_filter(explode(',', (string)($b['extra_rollen'] ?? ''))); if ($_extra): ?>
              <span class="bh-bhrij-via" title="Extra rollen uit Authentik; de rechten tellen op">
                ook <?= h(implode(', ', array_map(fn($r) => $rolnamen[$r] ?? $r, $_extra))) ?>
              </span>
            <?php endif; ?>
            <span class="bh-bhrij-laatst">
              <?= !empty($b['laatste_login']) ? 'laatst ' . h(datum_kort_nl(substr((string)$b['laatste_login'], 0, 10))) : 'nog nooit ingelogd' ?>
            </span>
          </span>

          <?php $_aanpasbaar = $mag_aanpassen($b); ?>
          <?php if ($_aanpasbaar && !$_ik): ?>
          <form class="bh-bhrij-rol" method="POST">
            <?= csrf_input() ?>
            <input type="hidden" name="actie" value="rol">
            <input type="hidden" name="bid" value="<?= (int)$b['id'] ?>">
            <label class="sr-only" for="rol-<?= (int)$b['id'] ?>">Rol van <?= h((string)$b['naam']) ?></label>
            <select class="ds-select bh-veld-smal" id="rol-<?= (int)$b['id'] ?>" name="rol">
              <?php foreach ($rolnamen as $_sl => $_nm): if (!$mag_toekennen($_sl)) continue; ?>
                <option value="<?= h($_sl) ?>" <?= $b['rol'] === $_sl ? 'selected' : '' ?>><?= h($_nm) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="ds-btn ds-btn-zacht ds-btn-sm">Rol opslaan</button>
          </form>
          <?php else: ?>
          <span class="bh-bhrij-rol"><?= h($rolnamen[$b['rol']] ?? (string)$b['rol']) ?></span>
          <?php endif; ?>

          <span class="bh-bhrij-acties">
            <?php if ($_aanpasbaar): ?>
            <a class="ds-btn ds-btn-zacht ds-btn-sm" href="#ww-<?= (int)$b['id'] ?>"
               data-klik-voorkom data-klik="bhWachtwoord(<?= (int)$b['id'] ?>)">Wachtwoord</a>
            <?php endif; ?>
            <?php if ($ben_admin && tweestaps_aan($b)): ?>
              <a class="ds-btn ds-btn-zacht ds-btn-sm" href="?bevestig=tweestaps_reset&amp;id=<?= (int)$b['id'] ?>">Tweestaps resetten</a>
            <?php endif; ?>
            <?php if ($_ik): ?>
              <span class="bh-meta" title="Je kunt je eigen account niet uitzetten">jouw account</span>
            <?php elseif ($_aanpasbaar): ?>
              <a class="ds-btn <?= $_actief ? 'ds-btn-gevaar' : 'ds-btn-secundair' ?> ds-btn-sm"
                 href="?bevestig=<?= $_actief ? 'deactiveer' : 'activeer' ?>&amp;id=<?= (int)$b['id'] ?>">
                <?= $_actief ? 'Uitzetten' : 'Aanzetten' ?>
              </a>
            <?php endif; ?>
          </span>

          <form class="bh-bhrij-ww" id="ww-<?= (int)$b['id'] ?>" method="POST" hidden>
            <?= csrf_input() ?>
            <input type="hidden" name="actie" value="wachtwoord">
            <input type="hidden" name="bid" value="<?= (int)$b['id'] ?>">
            <label class="ds-label" for="wwv-<?= (int)$b['id'] ?>">Nieuw wachtwoord voor <?= h((string)$b['naam']) ?></label>
            <div class="bh-bhrij-wwrij">
              <input class="ds-input" type="password" id="wwv-<?= (int)$b['id'] ?>" name="nieuw_wachtwoord"
                     minlength="8" autocomplete="new-password" placeholder="Minimaal 8 tekens" required>
              <button type="submit" class="ds-btn ds-btn-primair ds-btn-sm">Opslaan</button>
              <button type="button" class="ds-btn ds-btn-zacht ds-btn-sm" data-klik="bhWachtwoord(<?= (int)$b['id'] ?>)">Annuleren</button>
            </div>
            <p class="ds-veld-hint">
              Het systeem verstuurt dit niet. Geef het zelf door en laat de persoon het daarna wijzigen.
            </p>
          </form>
        </li>
        <?php endforeach; ?>
      </ul>
    </section>
  </div>

  <aside class="bh-beheerders-zij">
    <form method="POST" class="ds-kaart ds-kaart-p">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="nieuw">
      <div class="bh-kaartkop"><h2>Account aanmaken</h2></div>
      <p class="ds-veld-hint">
        Alleen nodig voor iemand die niet via Authentik binnenkomt. Wie wél in Authentik staat,
        krijgt bij de eerste keer inloggen vanzelf een account.
      </p>
      <div class="ds-veld">
        <label class="ds-label" for="nb_naam">Naam</label>
        <input class="ds-input" type="text" id="nb_naam" name="naam" required placeholder="Jan de Vries">
      </div>
      <div class="ds-veld">
        <label class="ds-label" for="nb_email">E-mailadres</label>
        <input class="ds-input" type="email" id="nb_email" name="email" required placeholder="jan@paal13.com">
      </div>
      <div class="ds-veld">
        <label class="ds-label" for="nb_ww">Tijdelijk wachtwoord</label>
        <input class="ds-input" type="password" id="nb_ww" name="wachtwoord" required minlength="8"
               autocomplete="new-password" placeholder="Minimaal 8 tekens">
        <p class="ds-veld-hint">Geef dit zelf door; het systeem mailt het niet.</p>
      </div>
      <div class="ds-veld">
        <label class="ds-label" for="nb_rol">Rol</label>
        <select class="ds-select" id="nb_rol" name="rol">
          <?php foreach ($rolnamen as $_sl => $_nm): if (!$mag_toekennen($_sl)) continue; ?>
            <option value="<?= h($_sl) ?>" <?= $_sl === 'bestuur' ? 'selected' : '' ?>><?= h($_nm) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="ds-veld-hint">Wat elke rol precies mag, stel je in bij <a href="<?= APP_URL ?>/admin/rollen.php">Rollen</a>.</p>
      </div>
      <div class="bh-veldform-voet">
        <button type="submit" class="ds-btn ds-btn-primair">Account aanmaken</button>
      </div>
    </form>

    <?php if (authentik_geconfigureerd()): ?>
    <section class="ds-kaart ds-kaart-p bh-ssokaart aan">
      <div class="bh-kaartkop"><h2>Authentik is gekoppeld</h2></div>
      <p class="bh-dossier-tekst">
        Inloggen via Authentik kan alleen wie daar in een groep zit die bij
        <a href="<?= APP_URL ?>/admin/rollen.php">Rollen</a> aan een rol gekoppeld is. Met meerdere van
        die groepen tellen de rechten op. De rollen volgen bij elke login Authentik; een rol die je hier
        met de hand kiest, wordt bij de volgende SSO-login weer overschreven.
        Haal je iemand in Authentik uit de groep, dan kan die persoon niet meer via SSO inloggen.
        Heeft het account ook een wachtwoord, zet het account dan hier uit.
      </p>
    </section>
    <?php else: ?>
    <section class="ds-kaart ds-kaart-p bh-ssokaart">
      <div class="bh-kaartkop"><h2>Authentik is niet gekoppeld</h2></div>
      <p class="bh-dossier-tekst">
        Zonder koppeling kan er alleen met een wachtwoord uit dit systeem worden ingelogd. Zet de
        OIDC-gegevens in <code>includes/config.php</code> om SSO aan te zetten; het runbook voor
        systeembeheer beschrijft de stappen.
      </p>
    </section>
    <?php endif; ?>
  </aside>
</div>

<script nonce="<?= h(csp_nonce()) ?>">
/* Het wachtwoordformulier staat standaard dicht: het is een uitzonderingsactie, en
   een open wachtwoordveld per rij maakt de lijst onleesbaar. */
function bhWachtwoord(id) {
  var f = document.getElementById('ww-' + id);
  if (!f) return;
  f.hidden = !f.hidden;
  if (!f.hidden) { var v = f.querySelector('input[type=password]'); if (v) v.focus(); }
}
</script>

<?php require __DIR__ . '/layout_bottom.php'; ?>
