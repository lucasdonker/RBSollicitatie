<?php
/**
 * Beheer — Rollen & rechten: wat mag welke rol?
 *
 * De matrix is bewust een echte tabel met rij- en kolomkoppen: het is tabelgegevens,
 * en elk vinkje krijgt een eigen aria-label ("Bestuur mag Sollicitanten bewerken").
 * Zonder dat label hoort een schermlezer alleen "aankruisvakje" en is dit scherm
 * onbruikbaar — bij een rechtenmatrix is dat geen detail.
 *
 * Admin staat vast op alles en is niet uit te vinken: anders kun je jezelf buitensluiten.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';
$pagina_titel = 'Accounts & rechten';
$actieve_nav  = 'rollen';
// Via het modulerecht en niet via de rolrang, net als beheerders.php. In de
// standaardmatrix heeft alleen de beheerder 'beheerders', dus wie er nu in kan
// verandert niet — maar het is voortaan instelbaar in plaats van hardgecodeerd.
vereis_recht('beheerders', 'bewerken');

rechten_auto_setup();
$db   = db();
$fout = '';

/** Hoeveel beheerders hebben deze rol (als hoofdrol of als extra rol)? */
function rol_gebruikers($db, string $sleutel): int {
    $q = $db->prepare("SELECT COUNT(*) FROM beheerders WHERE rol=? OR FIND_IN_SET(?, extra_rollen)");
    $q->execute([$sleutel, $sleutel]);
    return (int)$q->fetchColumn();
}

/** Eén rol uit rollen_alle(), of null. */
function rol_zoek(string $sleutel): ?array {
    foreach (rollen_alle() as $r) if ($r['sleutel'] === $sleutel) return $r;
    return null;
}

// ── Verwerken ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $actie   = (string)($_POST['actie'] ?? '');
    $sleutel = (string)($_POST['sleutel'] ?? '');

    if ($actie === 'rol_nieuw') {
        $naam  = trim((string)($_POST['naam'] ?? ''));
        $groep = trim((string)($_POST['authentik_groep'] ?? ''));
        // Nieuwe rollen krijgen nooit admin-rang: die rol maak je niet per ongeluk.
        $rang  = max(1, min(2, (int)($_POST['rang'] ?? 1)));
        $nieuw = strtolower(preg_replace('/[^a-z0-9_]/i', '_', $naam));
        if ($naam === '' || $nieuw === '') {
            $fout = 'Geef de rol een naam.';
        } else {
            try {
                $db->prepare("INSERT INTO rollen (sleutel, naam, authentik_groep, rang, systeemrol) VALUES (?,?,?,?,0)")
                   ->execute([$nieuw, $naam, $groep ?: null, $rang]);
                // Een nieuwe rol begint met alles uit. Andersom zou een typefout in de
                // naam meteen iemand toegang tot persoonsgegevens geven.
                foreach (rechten_modules() as $mod => $_m) {
                    $db->prepare("INSERT INTO rol_rechten (rol_sleutel, module, lezen, bewerken, verwijderen) VALUES (?,?,0,0,0)")
                       ->execute([$nieuw, $mod]);
                }
                audit_log('rol_aangemaakt', 'rol', null,
                          ['rol' => $nieuw, 'naam' => $naam, 'authentik_groep' => $groep ?: 'geen', 'rang' => $rang]);
                flash('succes', 'Rol "' . $naam . '" toegevoegd. Alle rechten staan nog uit — zet ze hieronder aan.');
            } catch (Exception $e) {
                $fout = 'Er bestaat al een rol met (bijna) dezelfde naam.';
            }
        }
        if (!$fout) redirect(APP_URL . '/admin/rollen.php');
    }

    if ($actie === 'rol_bewerken') {
        $naam  = trim((string)($_POST['naam'] ?? ''));
        $groep = trim((string)($_POST['authentik_groep'] ?? ''));
        if ($sleutel !== '' && $naam !== '') {
            $vq = $db->prepare("SELECT naam, authentik_groep, systeemrol FROM rollen WHERE sleutel=?");
            $vq->execute([$sleutel]);
            $voor = $vq->fetch() ?: ['naam' => '', 'authentik_groep' => '', 'systeemrol' => 0];

            // De Authentik-groep bepaalt wie deze rol krijgt: een wijziging hier verandert
            // wie er binnenkomt, dus die hoort in het logboek.
            if (!empty($voor['systeemrol'])) {
                $db->prepare("UPDATE rollen SET authentik_groep=? WHERE sleutel=?")->execute([$groep ?: null, $sleutel]);
                $na = ['authentik_groep' => $groep];
            } else {
                $db->prepare("UPDATE rollen SET naam=?, authentik_groep=? WHERE sleutel=?")->execute([$naam, $groep ?: null, $sleutel]);
                $na = ['naam' => $naam, 'authentik_groep' => $groep];
            }
            $verschil = [];
            foreach ($na as $veld => $nieuwe) {
                if ((string)($voor[$veld] ?? '') !== (string)$nieuwe) {
                    $verschil[$veld] = ['oud' => (string)($voor[$veld] ?? ''), 'nieuw' => (string)$nieuwe];
                }
            }
            if ($verschil) audit_log('rol_gewijzigd', 'rol', null, array_merge(['rol' => $sleutel], $verschil));
            flash('succes', 'Rol bijgewerkt.');
        }
        redirect(APP_URL . '/admin/rollen.php');
    }

    if ($actie === 'rol_verwijderen') {
        $rol    = rol_zoek($sleutel);
        $aantal = $sleutel !== '' ? rol_gebruikers($db, $sleutel) : 0;
        if (!$rol) {
            flash('fout', 'Die rol bestaat niet (meer).');
        } elseif (!empty($rol['systeemrol'])) {
            flash('fout', 'Systeemrollen kunnen niet verwijderd worden — het systeem rekent erop dat ze bestaan.');
        } elseif ($aantal > 0) {
            flash('fout', 'Deze rol hangt nog aan ' . $aantal . ' ' . ($aantal === 1 ? 'beheerder' : 'beheerders')
                        . '. Geef die eerst een andere rol.');
        } else {
            $db->prepare("DELETE FROM rol_rechten WHERE rol_sleutel=?")->execute([$sleutel]);
            $db->prepare("DELETE FROM rol_taak WHERE rol_sleutel=?")->execute([$sleutel]);
            $db->prepare("DELETE FROM rollen WHERE sleutel=?")->execute([$sleutel]);
            audit_log('rol_verwijderd', 'rol', null, ['rol' => $sleutel]);
            flash('succes', 'Rol verwijderd.');
        }
        redirect(APP_URL . '/admin/rollen.php');
    }

    if ($actie === 'rechten_opslaan') {
        // taak[rol_sleutel][module][taak] = 1. Per rol de hele matrix; de oude rechten (lezen/bewerken/
        // verwijderen) leidt rol_taken_opslaan() eruit af (includes/rechten_taken.php).
        $matrix = (array)($_POST['taak'] ?? []);
        foreach (rollen_alle() as $rol) {
            if ($rol['sleutel'] === 'admin') continue;   // admin blijft altijd alles
            $verschil = rol_taken_opslaan($rol['sleutel'], (array)($matrix[$rol['sleutel']] ?? []));
            // Wie een rol meer of minder taken geeft, moet herleidbaar zijn.
            if ($verschil) {
                $log = [];
                foreach ($verschil as $mod => $v) $log[$mod] = ['oud' => implode(', ', $v['oud']), 'nieuw' => implode(', ', $v['nieuw'])];
                audit_log('rol_gewijzigd', 'rol', null, array_merge(['rol' => $rol['sleutel']], $log));
            }
        }
        flash('succes', 'Rechten opgeslagen.');
        redirect(APP_URL . '/admin/rollen.php');
    }
}

// ── Bevestiging als tussenscherm ─────────────────────────────
$bev_rol = null;
if (($_GET['bevestig'] ?? '') === 'verwijder') {
    $bev_rol = rol_zoek((string)($_GET['rol'] ?? ''));
    if ($bev_rol && !empty($bev_rol['systeemrol'])) $bev_rol = null;
}

$rollen  = rollen_alle();
$modules = rechten_modules();

$taken_per_rol = [];
$gebruikers_per_rol = [];
foreach ($rollen as $rol) {
    $taken_per_rol[$rol['sleutel']]      = rol_taken_effectief($rol['sleutel']);
    $gebruikers_per_rol[$rol['sleutel']] = rol_gebruikers($db, $rol['sleutel']);
}

$modules_per_groep = [];
foreach ($modules as $mod => $mdef) $modules_per_groep[$mdef['groep']][$mod] = $mdef;

require __DIR__ . '/layout_top.php';
?>

<?php if ($bev_rol): $_n = $gebruikers_per_rol[$bev_rol['sleutel']] ?? 0; ?>
  <?php
  $bev = [
      'titel'  => 'Rol ' . $bev_rol['naam'] . ' verwijderen?',
      'regels' => $_n > 0
          ? ['Deze rol hangt nog aan ' . $_n . ' ' . ($_n === 1 ? 'beheerder' : 'beheerders') . '; het verwijderen lukt pas als die een andere rol hebben.']
          : ['De rechten van deze rol verdwijnen mee.',
             'Staat er een Authentik-groep aan gekoppeld, dan krijgt iemand uit die groep voortaan de standaardrol.'],
      'bericht'      => null,
      'onomkeerbaar' => true,
      'knop'         => 'Ja, verwijderen',
      'toon'         => 'gevaar',
  ];
  $bev_form  = ['actie' => APP_URL . '/admin/rollen.php',
                'velden' => ['actie' => 'rol_verwijderen', 'sleutel' => (string)$bev_rol['sleutel']]];
  $bev_terug = APP_URL . '/admin/rollen.php';
  require __DIR__ . '/_bevestig.php';
  ?>
  <?php require __DIR__ . '/layout_bottom.php'; ?>
  <?php return; ?>
<?php endif; ?>

<?php $bt_actief = 'rollen'; require __DIR__ . '/_beheer_tabs.php'; ?>

<?php if ($fout): ?>
  <div class="ds-melding ds-melding-error bh-blokkade"><?= icoon('waarschuwing', 18) ?><span><?= h($fout) ?></span></div>
<?php endif; ?>

<section class="ds-kaart ds-kaart-p">
  <div class="bh-kaartkop"><h2>Rollen</h2></div>
  <p class="ds-veld-hint">
    Een rol koppel je aan een Authentik-groep; wie via SSO binnenkomt krijgt dan vanzelf de
    juiste rol. <strong>Admin</strong> houdt altijd volledige toegang en is niet in te perken —
    zo sluit je jezelf nooit buiten.
  </p>

  <ul class="bh-rollijstje">
    <?php foreach ($rollen as $rol):
      $_sys = !empty($rol['systeemrol']);
      $_n   = $gebruikers_per_rol[$rol['sleutel']] ?? 0;
    ?>
    <li class="bh-rolkaart">
      <div class="bh-rolkaart-kop">
        <span class="bh-rolkaart-naam">
          <?= h((string)$rol['naam']) ?>
          <?php if ($_sys): ?><?= bh_badge('systeemrol', 'blauw') ?><?php endif; ?>
          <?= bh_chip('rang ' . (int)$rol['rang']) ?>
          <?= bh_chip($_n . ' ' . ($_n === 1 ? 'gebruiker' : 'gebruikers')) ?>
        </span>
        <span class="bh-rolkaart-acties">
          <button type="button" class="ds-btn ds-btn-zacht ds-btn-sm"
                  data-klik="rolBewerk('<?= h($rol['sleutel']) ?>')">Bewerken</button>
          <?php if (!$_sys): ?>
            <a class="ds-btn ds-btn-gevaar ds-btn-sm" href="?bevestig=verwijder&amp;rol=<?= h(urlencode($rol['sleutel'])) ?>">Verwijderen</a>
          <?php endif; ?>
        </span>
      </div>
      <p class="bh-rolkaart-groep">
        Authentik-groep:
        <?php if (!empty($rol['authentik_groep'])): ?>
          <strong><?= h((string)$rol['authentik_groep']) ?></strong>
        <?php else: ?>
          <span class="bh-meta">niet gekoppeld — deze rol geef je handmatig</span>
        <?php endif; ?>
      </p>

      <form class="bh-rolkaart-form" id="rolform-<?= h($rol['sleutel']) ?>" method="POST" hidden>
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="rol_bewerken">
        <input type="hidden" name="sleutel" value="<?= h($rol['sleutel']) ?>">
        <div class="bh-veldrij">
          <div class="ds-veld">
            <label class="ds-label" for="rn-<?= h($rol['sleutel']) ?>">Naam</label>
            <input class="ds-input" type="text" id="rn-<?= h($rol['sleutel']) ?>" name="naam"
                   value="<?= h((string)$rol['naam']) ?>" <?= $_sys ? 'readonly' : '' ?>>
            <?php if ($_sys): ?>
              <p class="ds-veld-hint">De naam van een systeemrol ligt vast; de Authentik-groep mag je wel wijzigen.</p>
            <?php endif; ?>
          </div>
          <div class="ds-veld">
            <label class="ds-label" for="rg-<?= h($rol['sleutel']) ?>">Authentik-groep</label>
            <input class="ds-input" type="text" id="rg-<?= h($rol['sleutel']) ?>" name="authentik_groep"
                   value="<?= h((string)($rol['authentik_groep'] ?? '')) ?>" placeholder="bijv. app-sol-begeleider">
          </div>
        </div>
        <div class="bh-rolkaart-formvoet">
          <button type="submit" class="ds-btn ds-btn-primair ds-btn-sm">Opslaan</button>
          <button type="button" class="ds-btn ds-btn-zacht ds-btn-sm" data-klik="rolBewerk('<?= h($rol['sleutel']) ?>')">Annuleren</button>
        </div>
      </form>
    </li>
    <?php endforeach; ?>
  </ul>

  <details class="bh-nieuwerol">
    <summary>Nieuwe rol toevoegen</summary>
    <form method="POST">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="rol_nieuw">
      <div class="bh-veldrij">
        <div class="ds-veld">
          <label class="ds-label" for="nr_naam">Naam</label>
          <input class="ds-input" type="text" id="nr_naam" name="naam" required placeholder="bijv. Coördinator">
        </div>
        <div class="ds-veld">
          <label class="ds-label" for="nr_groep">Authentik-groep</label>
          <input class="ds-input" type="text" id="nr_groep" name="authentik_groep" placeholder="bijv. app-sol-planner">
        </div>
        <div class="ds-veld">
          <label class="ds-label" for="nr_rang">Rang</label>
          <select class="ds-select" id="nr_rang" name="rang">
            <option value="1">1 — basis</option>
            <option value="2">2 — uitgebreid</option>
          </select>
        </div>
      </div>
      <p class="ds-veld-hint">
        De rol begint met alle rechten uit. Rang bepaalt alleen de volgorde bij mensen die in
        meerdere Authentik-groepen zitten; wat een rol mág staat in de matrix hieronder.
      </p>
      <div class="bh-rolkaart-formvoet">
        <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Rol toevoegen</button>
      </div>
    </form>
  </details>
</section>

<?php
  // De matrix per onderdeel: taken als rijen, rollen als kolommen (includes/rechten_taken.php).
  // Ingeklapt per onderdeel, met in de kop per rol hoeveel taken aanstaan.
  $_rol_namen = [];
  foreach ($rollen as $rol) $_rol_namen[$rol['sleutel']] = (string)$rol['naam'];
?>
<form method="POST" class="ds-kaart bh-matrixkaart" data-taakmatrix>
  <?= csrf_input() ?>
  <input type="hidden" name="actie" value="rechten_opslaan">

  <div class="bh-lijstkop">
    <div>
      <h2>Wat mag welke rol?</h2>
      <p class="bh-meta">Per onderdeel de taken. Elke taak houdt in dat je het onderdeel mag bekijken.</p>
    </div>
    <span class="bh-lijstkop-acties">
      <button type="button" class="ds-btn ds-btn-zacht ds-btn-sm" data-klap-alles=".bh-taakblok">Alles uitklappen</button>
    </span>
  </div>

  <?php foreach ($modules_per_groep as $groep => $mods): ?>
    <h3 class="bh-taakgroep"><?= h((string)$groep) ?></h3>
    <?php foreach ($mods as $mod => $mdef):
      $_taken = rechten_taken($mod);
      $_sam = [];
      foreach ($rollen as $rol) {
          if ($rol['sleutel'] === 'admin') continue;
          $_n = count(array_filter($taken_per_rol[$rol['sleutel']][$mod] ?? []));
          if ($_n > 0) $_sam[] = $rol['naam'] . ' ' . $_n . '/' . count($_taken);
      }
    ?>
    <details class="bh-klap bh-taakblok" data-klap-id="rechten-<?= h($mod) ?>" data-mod="<?= h($mod) ?>">
      <summary>
        <span class="bh-klap-titel"><?= h((string)$mdef['label']) ?></span>
        <span class="bh-klap-sam" data-taak-sam><?= h($_sam ? implode(' · ', $_sam) : 'alleen Admin') ?></span>
      </summary>
      <div class="bh-klap-inhoud bh-matrixrol">
        <table class="bh-matrix bh-taakmatrix">
          <caption class="sr-only">Taken bij <?= h((string)$mdef['label']) ?>, per rol</caption>
          <thead>
            <tr>
              <th scope="col" class="bh-matrix-mod">Taak</th>
              <?php foreach ($rollen as $rol): ?><th scope="col" class="bh-matrix-rol"><?= h((string)$rol['naam']) ?></th><?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <tr class="bh-taak-alles">
              <th scope="row" class="bh-matrix-mod">Alles bij <?= h((string)$mdef['label']) ?></th>
              <?php foreach ($rollen as $rol): ?>
                <td>
                  <?php if ($rol['sleutel'] === 'admin'): ?>
                    <input type="checkbox" checked disabled aria-label="Admin: alle taken bij <?= h((string)$mdef['label']) ?> — vast">
                  <?php else: ?>
                    <input type="checkbox" data-taak-alles="<?= h($rol['sleutel']) ?>"
                           aria-label="<?= h($rol['naam']) ?>: alle taken bij <?= h((string)$mdef['label']) ?> aan of uit">
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
            <?php foreach ($_taken as $taak => $tdef): ?>
            <tr>
              <th scope="row" class="bh-matrix-mod">
                <span class="bh-taak-label"><?= h($tdef['label']) ?></span>
                <span class="bh-taak-uitleg"><?= h($tdef['uitleg']) ?></span>
              </th>
              <?php foreach ($rollen as $rol):
                $_label = $rol['naam'] . ' mag bij ' . $mdef['label'] . ': ' . $tdef['label'];
              ?>
              <td>
                <?php if ($rol['sleutel'] === 'admin'):
                  // Loondossiers: de admin ziet de status, maar opent de stukken niet (mag_kluis()).
                  $_vast = $mod === 'documenten' ? 'Admin ziet de loondossiers, maar alleen de penningmeester opent de stukken'
                                                 : 'Admin heeft altijd volledige toegang'; ?>
                  <input type="checkbox" checked disabled aria-label="<?= h($_label) ?> — vast. <?= h($_vast) ?>" title="<?= h($_vast) ?>">
                <?php else: ?>
                  <input type="checkbox" name="taak[<?= h($rol['sleutel']) ?>][<?= h($mod) ?>][<?= h($taak) ?>]" value="1"
                         <?= !empty($taken_per_rol[$rol['sleutel']][$mod][$taak]) ? 'checked' : '' ?>
                         aria-label="<?= h($_label) ?>" data-taak-rol="<?= h($rol['sleutel']) ?>">
                <?php endif; ?>
              </td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </details>
    <?php endforeach; ?>
  <?php endforeach; ?>

  <div class="bh-matrix-voet">
    <button type="submit" class="ds-btn ds-btn-primair">Rechten opslaan</button>
    <span class="bh-meta">De hele matrix wordt in één keer opgeslagen. Elke wijziging komt in het logboek.</span>
  </div>
</form>
<script src="<?= APP_URL ?>/assets/js/rollen.js?v=<?= @filemtime(__DIR__ . '/../assets/js/rollen.js') ?>" defer></script>

<?php require __DIR__ . '/layout_bottom.php'; ?>
