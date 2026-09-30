<?php
/**
 * Loonplanning — de kop: welk scenario, wat kost het tegen het budget, waar zit het geld (_geld.php),
 * concept of definitief, en de acties (zelden gebruikte in een menu: Scenario, Extra persoon, Exporteren).
 * Onderdeel van admin/loonplanning.php (zet $scenarios, $scenario_id, $analyse, $geld, $functies, $definitief_op, …).
 * De getallen hebben data-lp-* zodat assets/js/loonplanning.js ze na elke wijziging bijwerkt.
 */
$_tot    = $analyse['totaal'];
$_budget = (float)$_tot['budget'];
$_pct    = $_budget > 0 ? min(100, round($_tot['kosten'] / $_budget * 100)) : 0;
$_stand  = $_budget <= 0 ? null : (!empty($_tot['over_budget']) ? 'slecht' : ($_pct > 85 ? 'krap' : 'goed'));
$_dit_actief = false;
foreach ($scenarios as $_s) if ((int)$_s['id'] === $scenario_id && !empty($_s['actief'])) $_dit_actief = true;
$_n_aangenomen = count(array_filter($analyse['mensen'], fn($m) => $m['fase'] === 'aangenomen'));
$_n_vast = count($sloten);
// Welk tariefjaar er gerekend wordt; een zin als dat een eerder jaar is (includes/loon_tarieven.php).
$_tarief = $analyse['tarieven'] ?? loonplan_tarief_info($seizoen);
?>
<section class="ds-kaart ds-kaart-p bh-lp-kop" data-lp-kop data-budget="<?= $_budget ?>">
  <div class="bh-lp-scenarios">
    <span class="bh-veldlabel">Scenario</span>
    <nav class="bh-lp-sclijst" aria-label="Scenario's">
      <?php foreach ($scenarios as $_s): $_nu = (int)$_s['id'] === $scenario_id; ?>
        <a class="bh-lp-sc<?= $_nu ? ' nu' : '' ?>" href="?scenario=<?= (int)$_s['id'] ?>"<?= $_nu ? ' aria-current="page"' : '' ?>>
          <?php if (!empty($_s['actief'])): ?><?= icoon('ster', 13) ?><?php endif; ?><?= h((string)$_s['naam']) ?>
          <?php if (!empty($_s['actief'])): ?><span class="bh-vw">(actief)</span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <?php if ($mag_bewerken): ?>
      <details class="bh-lp-nieuw">
        <summary class="bh-doc-actie">Kopie maken</summary>
        <form method="post" class="bh-lp-nieuwform">
          <?= csrf_input() ?><input type="hidden" name="sc_actie" value="dupliceer">
          <label class="bh-vw" for="lpNieuwNaam">Naam van de kopie</label>
          <input type="text" id="lpNieuwNaam" name="naam" class="ds-input" required maxlength="80" placeholder="Bijv. Krap budget">
          <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Maak kopie</button>
        </form>
      </details>
    <?php endif; ?>
  </div>

  <div class="bh-lp-stand">
    <div class="bh-lp-getal">
      <span class="bh-veldlabel">Loonkosten</span>
      <strong data-lp="kosten"><?= h(euro((float)$_tot['kosten'])) ?></strong>
    </div>
    <div class="bh-lp-getal">
      <span class="bh-veldlabel">Werkdagen</span>
      <strong data-lp="dagen"><?= (int)$_tot['dagen'] ?></strong>
      <span class="bh-meta" data-lp="onbetaald"><?= !empty($_tot['dagen_onbetaald']) ? 'waarvan ' . (int)$_tot['dagen_onbetaald'] . ' onbetaald' : '' ?></span>
    </div>
    <div class="bh-lp-budget">
      <?php if ($_budget > 0): ?>
        <span class="bh-veldlabel">Budget <?= h(euro((float)$_budget)) ?></span>
        <span class="bh-lp-budgetbalk"><span class="bh-lp-budgetvul ds-fase-<?= ['goed' => 'groen', 'krap' => 'oranje', 'slecht' => 'rood'][$_stand] ?>" data-lp="budgetvul" style="--w: <?= $_pct ?>%"></span></span>
        <span data-lp="budgetzin"><?= bh_stoplicht($_stand, !empty($_tot['over_budget'])
            ? 'Over budget: ' . euro(abs((float)$_tot['restant']))
            : 'Nog ' . euro((float)$_tot['restant']) . ' over') ?></span>
      <?php else: ?>
        <span class="bh-meta">Geen budget ingesteld voor <?= $_tarief['jaar'] ?><?= mag('instellingen', 'bewerken') ? ' — <a href="' . APP_URL . '/admin/instellingen.php?tab=loon&amp;jaar=' . $_tarief['jaar'] . '">instellen</a>' : '' ?>.</span>
      <?php endif; ?>
      <?php if ($_tarief['melding'] !== ''): ?>
        <span class="bh-meta"><?= icoon('waarschuwing', 13) ?> <?= h($_tarief['melding']) ?></span>
      <?php endif; ?>
    </div>
  </div>

  <?php require __DIR__ . '/_geld.php'; ?>

  <div class="bh-lp-status">
    <?php if ($definitief_op): ?>
      <?= bh_badge('Definitief sinds ' . date('d-m-Y', strtotime($definitief_op)), 'groen') ?>
      <span class="bh-meta">De afspraken zijn vastgesteld: de sollicitant ziet ze, het dossier en de exports gebruiken ze.</span>
    <?php else: ?>
      <?= bh_badge('Concept', 'grijs') ?>
      <span class="bh-meta">Na het trainingsweekend maak je de planning definitief: dan wordt het actieve scenario de afspraak
        van iedereen die is aangenomen (<?= $_n_aangenomen ?>).<?= $_n_vast ? ' ' . $_n_vast . ' ' . ($_n_vast === 1 ? 'persoon staat' : 'personen staan') . ' al vast na het gesprek.' : '' ?></span>
    <?php endif; ?>
    <span class="bh-lp-knoppen">
      <?php if ($mag_definitief): ?>
        <form method="post" data-bevestig="Loonplanning definitief maken? Dit scenario wordt de actieve planning en de afspraak van iedereen die is aangenomen. Die afspraak zien de sollicitant, het dossier en de loonadministratie. Een eerdere afspraak van dezelfde persoon wordt vervangen.">
          <?= csrf_input() ?><input type="hidden" name="sc_actie" value="definitief">
          <button type="submit" class="ds-btn ds-btn-primair ds-btn-sm"><?= icoon('vink', 14) ?> <?= $definitief_op ? 'Opnieuw definitief maken' : 'Definitief maken' ?></button></form>
      <?php endif; ?>
      <?php if ($mag_bewerken): ?>

        <?php /* Wat je zelden doet, in een menu: de kop blijft rustig en het budget staat voorop. */ ?>
        <details class="bh-filter-menu" data-actiemenu>
          <summary class="ds-btn ds-btn-secundair ds-btn-sm">Scenario</summary>
          <div class="bh-filter-menu-lijst">
            <form method="post" data-bevestig="Voor iedereen die in dit scenario nog niets gepland heeft de eigen opgave overnemen? Wie al gepland is of vaststaat, blijft zoals het is.">
              <?= csrf_input() ?><input type="hidden" name="sc_actie" value="opgave_alle">
              <button type="submit"><?= icoon('lijst', 15) ?> Opgave overnemen (wie nog leeg is)</button></form>
            <?php if (!$_dit_actief): ?>
              <form method="post"><?= csrf_input() ?><input type="hidden" name="sc_actie" value="activeer">
                <button type="submit"><?= icoon('ster', 15) ?> Maak dit scenario actief</button></form>
              <form method="post" data-bevestig="Scenario verwijderen? De ingeplande dagen in dit scenario verdwijnen mee; de beschikbaarheid van de sollicitanten blijft staan.">
                <?= csrf_input() ?><input type="hidden" name="sc_actie" value="verwijder">
                <button type="submit"><?= icoon('prullenbak', 15) ?> Scenario verwijderen</button></form>
            <?php endif; ?>
          </div>
        </details>

        <details class="bh-filter-menu" data-actiemenu>
          <summary class="ds-btn ds-btn-secundair ds-btn-sm"><?= icoon('plus', 14) ?> Extra persoon</summary>
          <form method="post" class="bh-filter-menu-lijst bh-lp-extraform">
            <?= csrf_input() ?><input type="hidden" name="sc_actie" value="extra_opslaan">
            <p class="bh-meta">Iemand die geen sollicitant is maar wel meetelt in kosten, budget, dekking en rooster, zoals de postcommandant.</p>
            <label class="bh-veldlabel" for="lpExtraNaam">Naam</label>
            <input type="text" id="lpExtraNaam" name="naam" class="ds-input" required maxlength="100">
            <label class="bh-veldlabel" for="lpExtraFunctie">Functie</label>
            <select id="lpExtraFunctie" name="functie" class="ds-select">
              <?php foreach ($functies as $_f): ?><option value="<?= h($_f['sleutel']) ?>"<?= $_f['sleutel'] === 'postcommandant' ? ' selected' : '' ?>><?= h($_f['naam']) ?></option><?php endforeach; ?>
            </select>
            <label class="bh-veldlabel" for="lpExtraGeb">Geboortedatum <span class="bh-meta">(optioneel)</span></label>
            <input type="date" id="lpExtraGeb" name="geboortedatum" class="ds-input">
            <span class="bh-meta">Alleen nodig voor een dagloon naar leeftijd; leeg = gerekend als 18 jaar.</span>
            <label class="bh-veldlabel" for="lpExtraNotitie">Notitie <span class="bh-meta">(optioneel)</span></label>
            <input type="text" id="lpExtraNotitie" name="notitie" class="ds-input" maxlength="255">
            <button type="submit" class="ds-btn ds-btn-primair ds-btn-sm">Toevoegen</button>
          </form>
        </details>
      <?php endif; ?>
      <details class="bh-lp-export">
        <summary class="ds-btn ds-btn-secundair ds-btn-sm"><?= icoon('download', 14) ?> Exporteren</summary>
        <ul class="bh-lp-exportlijst">
          <?php if ($definitief_op): ?><li><a href="<?= APP_URL ?>/admin/export_loonplanning.php?bron=afspraak" data-geen-bezig>Loonadministratie (vastgesteld, Excel)</a></li><?php endif; ?>
          <li><a href="<?= APP_URL ?>/admin/export_loonplanning.php?bron=scenario&amp;scenario=<?= $scenario_id ?>" data-geen-bezig>Dit scenario (Excel, CONCEPT)</a></li>
          <li><a href="<?= APP_URL ?>/admin/export_rooster.php" data-geen-bezig>Voor het rooster (Excel)</a></li>
          <li><a href="<?= APP_URL ?>/admin/export_rooster.php?formaat=json" data-geen-bezig>Voor het rooster (JSON)</a></li>
        </ul>
      </details>
    </span>
  </div>

  <?php
    $begrippen_titel = 'Hoe werkt de loonplanning?';
    $begrippen = [
        'Opgave'          => 'Wat de sollicitant zelf aan beschikbaarheid opgaf. Met "Opgave overnemen" zet je die als startpunt in het scenario.',
        'Scenario'        => 'Een proefplanning: wie werkt wanneer, betaald of onbetaald. Maak een kopie om varianten naast elkaar te zetten.',
        'Actief scenario' => 'Het scenario met de ster. Dat telt: hierin schrijven de gesprekken van het trainingsweekend, en dit wordt de afspraak bij "Definitief maken".',
        'Concept'         => 'Zolang de planning niet definitief is, kun je alles nog wijzigen en ziet de sollicitant niets.',
        'Vast na het gesprek' => 'Wie in het gesprek van het trainingsweekend is aangenomen, staat in alle scenario\'s gelijk en op slot. Twijfel zet niets vast.',
        'Definitief maken' => 'Het actieve scenario wordt de afspraak van iedereen die is aangenomen. Dat kan later opnieuw; de oude afspraak wordt dan vervangen.',
        'Afspraak'        => 'Wat is vastgesteld. Die ziet de sollicitant in het portaal, en het dossier en de export voor de loonadministratie gebruiken hem.',
    ];
    require __DIR__ . '/../_begrippen.php';
  ?>
</section>
