<?php
/**
 * Tabblad: Dossier — het complete beeld dat je nodig hebt om te beslissen.
 *
 * Twee kolommen. Links wat je leest om een oordeel te vormen (gegevens, advies,
 * selectiedag, formulier, AI-analyse). Rechts wat je doet en wat er loopt (fase,
 * documenten, notities en tijdlijn, eerdere seizoenen).
 *
 * Verwacht: $tab_dossier  array  uitkomst van bh_dossier()
 */
$tab_dossier = $tab_dossier ?? null;
if (!$tab_dossier) return;

$_s   = $tab_dossier['sol'];
$_blk = $tab_dossier['blk'];
$_adv = bh_dossier_advies($tab_dossier);
$_doc = bh_dossier_documenten($tab_dossier);
$_taken = bh_dossier_taken($tab_dossier);
$_mag_bewerken = mag('sollicitanten', 'bewerken');
$_mag_blk = mag('blk', 'lezen');
?>
<div class="bh-dossier-kolommen">

  <div class="bh-dossier-hoofd">

    <section class="ds-kaart ds-kaart-p bh-gegevensraster">
      <div>
        <span class="bh-veldlabel">Geboortedatum</span>
        <span class="bh-veldwaarde"><?= h(datum_nl($_s['geboortedatum'] ?? null)) ?></span>
      </div>
      <div>
        <span class="bh-veldlabel">Aangemeld</span>
        <span class="bh-veldwaarde"><?= h(datum_nl($_s['aangemeld_op'] ?? null)) ?></span>
      </div>
      <div>
        <span class="bh-veldlabel">Berichten via</span>
        <?php if ($_mag_bewerken): ?>
          <select class="ds-select bh-veld-smal"
                  onchange="kanaalVoorkeurZet(<?= (int)$_s['pid'] ?>, this.value, this)">
            <?php foreach (['whatsapp' => 'WhatsApp', 'sms' => 'Sms', 'email' => 'Alleen e-mail'] as $_k => $_l): ?>
              <option value="<?= h($_k) ?>"<?= ($_s['kanaal_voorkeur'] ?? '') === $_k ? ' selected' : '' ?>><?= h($_l) ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <span class="bh-veldwaarde"><?= h(ucfirst((string)($_s['kanaal_voorkeur'] ?? '—'))) ?></span>
        <?php endif; ?>
      </div>
      <div>
        <?php // AVG (dataminimalisatie): het volledige adres alleen met het recht op het
              // kennismakingsformulier, net als blk_detail.php en de lijstkolommen; anders de woonplaats. ?>
        <span class="bh-veldlabel"><?= $_mag_blk ? 'Adres' : 'Woonplaats' ?></span>
        <span class="bh-veldwaarde">
          <?php
            if ($_mag_blk) {
                // straat/huisnummer/woonplaats zijn later toegevoegde kolommen; het oudere
                // vrije veld `adres` is de terugval voor dossiers van vóór die splitsing.
                $_str = trim(($_blk['straat'] ?? '') . ' ' . ($_blk['huisnummer'] ?? ''));
                $_pl  = trim((string)($_blk['woonplaats'] ?? ''));
                $_adres = trim($_str . ($_str !== '' && $_pl !== '' ? ', ' : '') . $_pl);
                if ($_adres === '') $_adres = trim((string)($_blk['adres'] ?? ''));
            } else {
                $_adres = $_blk ? blk_woonplaats($_blk) : '';
            }
            echo $_adres !== '' ? h($_adres) : '—';
          ?>
        </span>
      </div>
      <div>
        <span class="bh-veldlabel">Toestemming privacy</span>
        <span class="bh-veldwaarde<?= !empty($_s['privacy_akkoord_op']) ? ' goed' : '' ?>">
          <?= !empty($_s['privacy_akkoord_op'])
              ? 'Gegeven · ' . h(datum_nl(substr((string)$_s['privacy_akkoord_op'], 0, 10)))
              : 'Nog niet gegeven' ?>
        </span>
      </div>
      <div>
        <span class="bh-veldlabel">Ouderlijke toestemming</span>
        <span class="bh-veldwaarde<?= $tab_dossier['minderjarig'] ? '' : ' zacht' ?>">
          <?php // 18 is de ondertekeningsgrens — een andere grens dan de 16 hierboven. ?>
          <?= $tab_dossier['minderjarig'] ? 'Nodig — tekent via een ouder' : 'Niet nodig — 18 of ouder' ?>
        </span>
      </div>
    </section>

    <?php if ($_mag_bewerken) { $geg_dossier = $tab_dossier; require __DIR__ . '/_gegevens.php'; } ?>

    <?php if ($_adv): ?>
    <section class="ds-kaart ds-kaart-p">
      <div class="bh-kaartkop">
        <h2>Advies</h2>
        <?= bh_badge($_adv['label'], $_adv['kleur']) ?>
        <span class="bh-meta">advies, geen besluit</span>
      </div>
      <p class="bh-dossier-tekst"><?= h($_adv['reden']) ?></p>
      <?php $_per_test = bh_dossier_score_per_test($tab_dossier); ?>
      <?php if ($_per_test): ?>
        <div class="bh-scorebalken">
          <?php foreach ($_per_test as $_test => $_sc): ?>
            <div class="bh-scorebalk">
              <span class="bh-scorebalk-naam"><?= h(ucfirst((string)$_test)) ?></span>
              <span class="bh-scorebalk-spoor">
                <span class="bh-scorebalk-vul" style="--w: <?= (int)round($_sc / 5 * 100) ?>%"></span>
              </span>
              <span class="bh-scorebalk-getal bh-num"><?= h(number_format($_sc, 1, ',', '')) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($tab_dossier['besluit']['besluit'])): ?>
        <p class="bh-meta bh-dossier-besluitregel">
          Besluit van de nabespreking:
          <strong><?= h(ucfirst((string)$tab_dossier['besluit']['besluit'])) ?></strong>
        </p>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php
      // Competenties komen uit Authentik (diploma's en bevoegdheden van de vereniging).
      $_comp_aan = function_exists('competentie_sync_klaar') && competentie_sync_klaar();
      $_comps = $_comp_aan && function_exists('competenties_van') ? competenties_van((int)$_s['id']) : [];
      $_sync  = $_comp_aan && function_exists('competentie_sync_info') ? competentie_sync_info((int)$_s['id']) : [];
    ?>
    <?php if ($_comp_aan): ?>
    <section class="ds-kaart ds-kaart-p">
      <div class="bh-kaartkop">
        <h2>Competenties</h2>
        <?php if ($_mag_bewerken): ?>
          <button type="button" class="bh-kaartkop-link bh-kaartkop-linkknop"
                  onclick="competentieVerversen(<?= (int)$_s['id'] ?>)">Synchroniseren</button>
        <?php endif; ?>
      </div>
      <div id="compChips-<?= (int)$_s['id'] ?>">
        <?php if ($_comps): ?>
          <div class="bh-chiprij">
            <?php foreach ($_comps as $_c): ?><?= bh_chip((string)$_c['groep'], 'blauw') ?><?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="bh-dossier-tekst zacht">Geen competenties gevonden in Authentik.</p>
        <?php endif; ?>
      </div>
      <span class="bh-meta bh-dossier-voetregel" id="compStatus-<?= (int)$_s['id'] ?>">
        <?php if (!empty($_sync['laatst_gesynct'])): ?>
          Laatst opgehaald <?= h(datum_nl(substr((string)$_sync['laatst_gesynct'], 0, 10))) ?>
          <?php if (($_sync['sync_status'] ?? '') === 'niet_gevonden'): ?> · geen match gevonden<?php endif; ?>
        <?php else: ?>
          Nog niet opgehaald.
        <?php endif; ?>
      </span>
      <?php if ($_mag_bewerken && (($_sync['sync_status'] ?? '') === 'niet_gevonden' || !$_comps)): ?>
        <div class="bh-comp-koppel">
          <label class="bh-veldlabel" for="compUsername-<?= (int)$_s['id'] ?>">
            Handmatig koppelen op Authentik-gebruikersnaam
          </label>
          <div class="bh-notitie-nieuw bh-comp-koppelrij">
            <input type="text" class="ds-input" id="compUsername-<?= (int)$_s['id'] ?>" placeholder="gebruikersnaam">
            <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm"
                    onclick="competentieKoppelen(<?= (int)$_s['id'] ?>)">Koppelen</button>
          </div>
        </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($_blk && $_mag_blk): ?>
    <section class="ds-kaart ds-kaart-p">
      <div class="bh-kaartkop">
        <h2>Uit het kennismakingsformulier</h2>
        <?php if (empty($tab_dossier['blk_ingediend_op'])): ?><?= bh_badge('Concept', 'oranje') ?><?php endif; ?>
        <a class="bh-kaartkop-link" href="<?= h(dossier_url((int)$_s['id'], 'formulier')) ?>">Volledig formulier →</a>
      </div>
      <div class="bh-gegevensraster twee">
        <?php
          // Veldnamen exact zoals in blk_formulieren; werkervaring en motivatie_*
          // heten anders dan je zou gokken.
          $_velden = ['opleiding' => 'Opleiding', 'werkervaring' => 'Werkervaring',
                      'diplomas' => 'Diploma\'s', 'bijzonderheden' => 'Bijzonderheden'];
          foreach ($_velden as $_v => $_l):
            $_w = trim((string)($_blk[$_v] ?? ''));
            $_bijz = $_v === 'bijzonderheden' && $_w !== '';
        ?>
          <div>
            <span class="bh-veldlabel"><?= h($_l) ?></span>
            <?php // "Niet ingevuld" is iets anders dan leeg: dat onderscheid moet zichtbaar blijven. ?>
            <span class="bh-veldwaarde<?= $_w === '' ? ' zacht' : ($_bijz ? ' let-op' : '') ?>">
              <?= $_w === '' ? 'Niet ingevuld' : h($_w) ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
      <?php
        $_moties = ['motivatie_lifeguard' => 'Waarom strandwacht?',
                    'motivatie_strand'    => 'Waarom juist dit strand?'];
        foreach ($_moties as $_mv => $_ml):
          $_mot = trim((string)($_blk[$_mv] ?? ''));
          if ($_mot === '') continue;
      ?>
        <div class="bh-dossier-motivatie">
          <span class="bh-veldlabel"><?= h($_ml) ?></span>
          <p class="bh-dossier-tekst"><?= nl2br(h($_mot)) ?></p>
        </div>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <?php if ($_blk && !empty($_blk['ai_analyse']) && $_mag_blk): ?>
    <section class="ds-kaart ds-kaart-p">
      <div class="bh-kaartkop">
        <h2>AI-analyse</h2>
        <?= bh_badge('Geanonimiseerd · AVG-conform', 'grijs') ?>
        <?php if (!empty($_blk['ai_analyse_op'])): ?>
          <span class="bh-meta"><?= h(datum_nl(substr((string)$_blk['ai_analyse_op'], 0, 10))) ?></span>
        <?php endif; ?>
      </div>
      <p class="bh-dossier-tekst"><?= nl2br(h((string)$_blk['ai_analyse'])) ?></p>
    </section>
    <?php endif; ?>

    <?php if (mag('sollicitanten', 'verwijderen')): ?>
    <?php // Onomkeerbaar én persoonsgegevens: een gewone POST naar het tussenscherm in
          // admin/sollicitant.php (werkt zonder JavaScript), niet een dialoog. ?>
    <section class="bh-gevaarblok">
      <h2>Sollicitant verwijderen</h2>
      <p>
        Hiermee verdwijnen het kennismakingsformulier, de beschikbaarheid, alle notities en
        observaties, de ondertekende documenten en het loondossier. Berichten blijven in het
        logboek staan zonder inhoud en zonder adres. Dit kun je niet terugdraaien.
      </p>
      <p>
        <strong>Alleen deze sollicitatie</strong> laat naam, e-mail en telefoon staan voor andere
        seizoenen. <strong>Alles van deze persoon</strong> is voor een verwijderverzoek (AVG): alle
        sollicitaties worden leeggemaakt en de persoon wordt geanonimiseerd.
      </p>
      <form method="post" class="bh-gevaarblok-knoppen">
        <?= csrf_input() ?>
        <input type="hidden" name="id" value="<?= (int)$_s['id'] ?>">
        <button type="submit" name="verwijder" value="sollicitatie" class="ds-btn ds-btn-gevaar">Alleen deze sollicitatie…</button>
        <button type="submit" name="verwijder" value="persoon" class="ds-btn ds-btn-secundair">Alles van deze persoon…</button>
      </form>
    </section>
    <?php endif; ?>
  </div>

  <aside class="bh-dossier-zij">

    <section class="ds-kaart ds-kaart-p">
      <div class="bh-kaartkop">
        <h2>Fase</h2>
        <span class="bh-meta">bij afwijken kun je een reden opgeven</span>
      </div>
      <div class="bh-fasekeuze">
        <?php foreach (fasen() as $_f => $_lbl):
          $_nu = $_f === $_s['fase'];
        ?>
          <?php if ($_nu): ?>
            <span class="bh-fasechip nu ds-fase-<?= h(fase_kleur($_f)) ?>" aria-current="true">
              <?= icoon('vink', 11) ?><?= h(fase_label_kort($_f, 'mini')) ?>
            </span>
          <?php elseif ($_mag_bewerken): ?>
            <?php // Wijkt de stap af van de gewone volgorde, dan vraagt fasWijzigen() om een
                  // (optionele) reden; die komt als notitie in de tijdlijn. ?>
            <button type="button" class="bh-fasechip ds-fase-<?= h(fase_kleur($_f)) ?>"
                    onclick="fasWijzigen(<?= (int)$_s['id'] ?>, '<?= h($_f) ?>', '<?= h(fase_label($_f)) ?>', <?= bh_fase_afwijkend((string)$_s['fase'], $_f) ? 'true' : 'false' ?>)">
              <?= h(fase_label_kort($_f, 'mini')) ?>
            </button>
          <?php else: ?>
            <span class="bh-fasechip ds-fase-grijs"><?= h(fase_label_kort($_f, 'mini')) ?></span>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </section>

    <?php if ($_taken['totaal'] > 0): ?>
    <section class="ds-kaart ds-kaart-p">
      <div class="bh-kaartkop">
        <h2>Taken</h2>
        <span class="bh-meta">zoals de sollicitant ze in het portaal ziet</span>
      </div>
      <?= bh_voortgang($_taken['gedaan'], $_taken['totaal'], ['label' => $_taken['gedaan'] . ' / ' . $_taken['totaal'] . ' afgerond']) ?>
      <ul class="bh-doclijst">
        <?php foreach ($_taken['rijen'] as $_r):
          $_stand = $_r['done'] ? 'klaar' : ($_r['wacht'] ? 'wacht' : 'open');
        ?>
          <li class="<?= $_stand ?>">
            <span class="bh-doc-vink">
              <?= $_r['done'] ? icoon('vink', 14) : '<span class="bh-doc-stip"></span>' ?>
            </span>
            <span class="bh-doc-naam">
              <?= h($_r['titel']) ?>
              <?php if ($_r['sub'] !== ''): ?><span class="bh-doc-sub"><?= h($_r['sub']) ?></span><?php endif; ?>
            </span>
            <?php // Status altijd ook in woorden, niet alleen met kleur. ?>
            <span class="bh-doc-datum"><?= $_r['done'] ? 'Afgerond' : ($_r['wacht'] ? 'Wacht op ouder' : 'Open') ?></span>
            <?php if (!$_r['done'] && $_r['token'] && $_mag_bewerken): ?>
              <button type="button" class="bh-doc-actie"
                      onclick="kopieerOndertekenlink('<?= h($_r['token']) ?>', this)">Link kopiëren</button>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($_doc['totaal'] > 0): ?>
        <a class="bh-kaartkop-link bh-taken-voet" href="<?= h(dossier_url((int)$_s['id'], 'ondertekeningen')) ?>">
          Ondertekeningen · <?= $_doc['gedaan'] ?> van <?= $_doc['totaal'] ?> →
        </a>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="ds-kaart ds-kaart-p">
      <div class="bh-kaartkop">
        <h2>Notities</h2>
        <span class="bh-meta">alleen zichtbaar in het beheer</span>
      </div>
      <?php // De id's zijn die van laadNotities()/laadTimeline() in layout_bottom.php;
            // die JS wordt in een volgende stap naar beheer.js verhuisd. ?>
      <div id="notities-lijst-<?= (int)$_s['id'] ?>">
        <?php $laden_rijen = 2; $laden_tegel = false; require __DIR__ . '/../_laden.php'; ?>
      </div>
      <?php if ($_mag_bewerken): ?>
      <div class="bh-notitie-nieuw">
        <textarea id="nieuwe-notitie-<?= (int)$_s['id'] ?>" class="ds-textarea" rows="2"
                  placeholder="Notitie plaatsen…"></textarea>
        <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm"
                onclick="notitieToevoegen(<?= (int)$_s['id'] ?>)">Plaatsen</button>
      </div>
      <?php endif; ?>
    </section>

    <section class="ds-kaart ds-kaart-p">
      <div class="bh-kaartkop"><h2>Tijdlijn</h2></div>
      <div id="timeline-<?= (int)$_s['id'] ?>">
        <?php $laden_rijen = 3; $laden_tegel = false; require __DIR__ . '/../_laden.php'; ?>
      </div>
    </section>

    <script>
      // Deze twee halen hun inhoud op na het laden; ze stonden eerder alleen in het
      // paneel, dat ze zelf aanriep nadat innerHTML de scripts had ingeslikt.
      document.addEventListener('DOMContentLoaded', function () {
        if (window.laadNotities) laadNotities(<?= (int)$_s['id'] ?>);
        if (window.laadTimeline) laadTimeline(<?= (int)$_s['id'] ?>);
      });
    </script>

    <?php if ($tab_dossier['eerder']): ?>
    <section class="ds-kaart ds-kaart-p">
      <div class="bh-kaartkop"><h2>Eerdere seizoenen</h2></div>
      <ul class="bh-eerder">
        <?php foreach ($tab_dossier['eerder'] as $_e): ?>
          <li>
            <strong><?= h($_e['seizoen_naam']) ?></strong>
            <?= bh_fase_badge((string)$_e['fase'], ['lengte' => 'kort']) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>
  </aside>
</div>
<?php
$tab_dossier = null;
