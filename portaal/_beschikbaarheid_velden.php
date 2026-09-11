<?php
/**
 * Gedeelde beschikbaarheid-velden. Verwacht dat $sol en $alle_weken gezet zijn.
 * Gebruikt door de losse beschikbaarheid-pagina (en tijdens de overgang eventueel elders).
 * Zet zelf de b2-variabelen klaar op basis van $sol.
 */
if (!isset($sol) || !isset($alle_weken)) { return; }
?>
          <div class="sectie-titel">Beschikbaarheid</div>
          <?php
            $b2 = function_exists('beschikbaarheid2_van') ? beschikbaarheid2_van($sol['id']) : ['profiel'=>null,'blokken'=>[],'afwezig'=>[],'verzoeken'=>[]];
            $b2p = $b2['profiel'];
            // Week-opties met datum voor de dropdowns
            $week_opties = [];
            foreach ($alle_weken as $w) $week_opties[$w['week']] = 'W' . $w['week'] . ' · ' . preg_replace('/–.*/', '', $w['periode']);
            $eerste_wk = $alle_weken[0]['week'] ?? 23;
            $laatste_wk = end($alle_weken)['week'] ?? 35;
            // Bestaande blokken, of één standaardblok (hele seizoen, hele weken)
            $bestaande_blokken = $b2['blokken'] ?: [['van_week'=>$eerste_wk,'tot_week'=>$laatste_wk,'regime'=>'vijf','weekend_dagen'=>null]];
          ?>
          <p style="font-size:13px;color:var(--tekst-zacht);margin-bottom:12px">
            Geef aan wanneer je kunt werken. De meeste mensen hebben genoeg aan één periode. Standaard is <strong>hele weken (5 dagen)</strong>.
          </p>

          <!-- Gidsje (punt 13) -->
          <details style="margin-bottom:14px;border:1px solid var(--blauw);border-radius:10px;background:var(--blauw-bg)">
            <summary style="padding:11px 16px;cursor:pointer;font-size:13px;font-weight:600;color:var(--blauw)">Hoe vul ik dit in? (voorbeeld)</summary>
            <div style="padding:4px 16px 14px;font-size:12.5px;line-height:1.7;color:var(--tekst)">
              <p style="margin:0 0 8px"><strong>Stel:</strong> je werkt de hele zomervakantie, behalve twee weken dat je zelf op vakantie bent in augustus.</p>
              <p style="margin:0 0 4px">Dan vul je in:</p>
              <ul style="margin:0 0 8px;padding-left:18px">
                <li><strong>Periode:</strong> laat de standaard "hele seizoen · hele weken" staan.</li>
                <li><strong>Afwezig:</strong> voeg je vakantie toe (bijv. 3 t/m 16 augustus).</li>
              </ul>
              <p style="margin:0;color:var(--tekst-zacht)">Klaar! Weet je iets nog niet zeker? Vink onderaan "voorlopig" aan en werk het later bij.</p>
            </div>
          </details>

          <!-- Foutmelding-blok (WCAG: tekst, niet alleen kleur) -->
          <?php
            // Detecteer per uitzondering of er al data is → dan het blok automatisch openklappen
            // (nieuw formulier = alles dicht; terugkerend = toon wat al ingevuld is).
            $losse_dagen   = function_exists('beschikbaarheid_dagen') ? beschikbaarheid_dagen($sol['id']) : [];
            $vaste_sel     = $b2p['vaste_dagen_uit'] ?? [];
            $heeft_afwezig = !empty($b2['afwezig']);
            $heeft_losse   = !empty($losse_dagen);
            $heeft_vaste   = !empty($vaste_sel);
            $heeft_verzoek = false;
            foreach ($b2['verzoeken'] as $vz) { if (($vz['status'] ?? '') === 'open' || ($vz['status'] ?? '') === 'goedgekeurd') { $heeft_verzoek = true; break; } }
          ?>

          <?php if (function_exists('ai_besch_actief') && ai_besch_actief()): ?>
          <!-- AI-hulp: de snelle route (experimenteel, uitschakelbaar via instellingen) -->
          <div style="background:linear-gradient(135deg,rgba(5,77,138,.06),rgba(212,61,0,.05));border:1px solid var(--rand);border-radius:12px;padding:16px;margin-bottom:14px">
            <div style="font-weight:600;font-size:14px;margin-bottom:4px;color:var(--navy)">✨ Liever gewoon typen? <span style="font-size:11px;font-weight:500;color:var(--grijs);background:var(--bg);padding:2px 8px;border-radius:10px;margin-left:4px">experimenteel</span></div>
            <p style="font-size:12.5px;color:var(--tekst-zacht);margin:0 0 10px">
              Schrijf in je eigen woorden wanneer je kunt, dan vult de AI de velden hieronder alvast in. <strong>Jij controleert het daarna.</strong> Bijvoorbeeld: "de hele zomer, in juni alleen weekenden, 20 juli–3 augustus op vakantie".
            </p>
            <p style="font-size:11.5px;color:var(--grijs);margin:0 0 10px;display:flex;align-items:flex-start;gap:5px">
              <span aria-hidden="true">ℹ️</span>
              <span>Je tekst wordt verwerkt door een externe AI-dienst (Google Gemini). Vul hier geen gevoelige of medische informatie in — dat kan gewoon in het formulier zelf.</span>
            </p>
            <textarea id="aiBeschTekst" rows="2" placeholder="Typ hier je beschikbaarheid…"
                      style="width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid var(--rand);border-radius:8px;font-size:14px;font-family:inherit;resize:vertical"></textarea>
            <div style="display:flex;gap:8px;align-items:center;margin-top:8px;flex-wrap:wrap">
              <button type="button" class="ds-btn ds-btn-primair ds-btn-sm" id="aiBeschKnop" onclick="aiBeschVerwerk()">
                <span class="ai-tekst">✨ Vul het formulier voor mij in</span>
                <span class="ai-spinner" style="display:none">Bezig…</span>
              </button>
              <span id="aiBeschStatus" style="font-size:12.5px;color:var(--tekst-zacht)"></span>
            </div>
            <div id="aiBeschVraag" style="display:none;margin-top:10px;padding:10px 12px;background:var(--geel-bg);border-left:3px solid var(--geel);border-radius:6px;font-size:13px"></div>
          </div>
          <div style="text-align:center;font-size:12px;color:var(--grijs);margin:2px 0 14px;text-transform:uppercase;letter-spacing:.05em">— of vul het zelf in —</div>
          <?php endif; ?>

          <!-- Foutmelding-blok (WCAG: tekst, niet alleen kleur) -->
          <div id="b2FoutMelding" role="alert" aria-live="assertive" style="display:none;margin-bottom:12px;padding:11px 14px;background:var(--rood-bg);border-left:3px solid var(--rood);border-radius:6px;font-size:13px;color:var(--rood)"></div>

          <?php
            // Melding als het bestuur dagen heeft aangepast (herleidbaar, transparant naar de sollicitant)
            $bestuur_aanpassingen = [];
            if (function_exists('beschikbaarheid_dagen')) {
                foreach (beschikbaarheid_dagen($sol['id']) as $bd) {
                    if (($bd['bron'] ?? '') === 'bestuur') $bestuur_aanpassingen[] = $bd;
                }
            }
            if ($bestuur_aanpassingen):
          ?>
          <div style="margin-bottom:14px;padding:12px 15px;background:var(--blauw-bg,#e8f0f8);border-left:3px solid var(--navy);border-radius:8px;font-size:13px">
            <strong>ℹ️ Een aantal dagen is door het bestuur aangepast.</strong>
            <div style="margin-top:6px;color:var(--tekst-zacht)">
              <?php foreach ($bestuur_aanpassingen as $ba):
                $lbl = date('d-m-Y', strtotime($ba['datum']));
                $status = ($ba['soort'] ?? '') === 'beschikbaar' ? 'beschikbaar gemaakt' : 'op niet-beschikbaar gezet';
              ?>
              <div>• <?= h($lbl) ?> — <?= h($status) ?><?= !empty($ba['reden']) ? ' (' . h($ba['reden']) . ')' : '' ?></div>
              <?php endforeach; ?>
            </div>
            <div style="margin-top:6px;font-size:12px;color:var(--grijs)">Deze aanpassingen gelden boven je eigen invoer. Klopt er iets niet? Neem contact op met het bestuur.</div>
          </div>
          <?php endif; ?>

          <!-- STAP 1: Wanneer kun je werken? (verplicht, standaard hele seizoen) -->
          <div style="background:var(--bg-card);border:1px solid var(--rand);border-radius:12px;padding:16px;margin-bottom:14px">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
              <span style="flex-shrink:0;width:24px;height:24px;border-radius:50%;background:var(--navy);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:600">1</span>
              <span style="font-weight:600;font-size:14.5px">Wanneer kun je werken?</span>
              <span style="margin-left:auto;font-size:11px;font-weight:600;padding:3px 10px;border-radius:11px;background:var(--oranje-bg);color:var(--oranje)">nodig</span>
            </div>
            <div id="b2BlokLijst">
              <?php foreach ($bestaande_blokken as $blok):
                $is_weekend = ($blok['regime'] ?? '') === 'weekend';
                $wd_sel = $is_weekend ? ($blok['weekend_dagen'] ?: [5,6,7]) : [5,6,7];
              ?>
              <div class="b2-blok-rij" style="border:1px solid var(--rand-licht);border-radius:8px;padding:10px;margin-bottom:8px">
                <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                  <span style="font-size:12px;color:var(--grijs)">van</span>
                  <select name="b2_blok_van[]" onchange="b2Herbereken()" aria-label="Van week" style="flex:1;min-width:110px">
                    <?php foreach ($week_opties as $wn => $lbl): ?><option value="<?= $wn ?>" <?= (int)$blok['van_week']===$wn?'selected':'' ?>><?= h($lbl) ?></option><?php endforeach; ?>
                  </select>
                  <span style="font-size:12px;color:var(--grijs)">t/m</span>
                  <select name="b2_blok_tot[]" onchange="b2Herbereken()" aria-label="Tot en met week" style="flex:1;min-width:110px">
                    <?php foreach ($week_opties as $wn => $lbl): ?><option value="<?= $wn ?>" <?= (int)$blok['tot_week']===$wn?'selected':'' ?>><?= h($lbl) ?></option><?php endforeach; ?>
                  </select>
                  <select name="b2_blok_regime[]" onchange="b2RegimeCheck(this);b2Herbereken()" aria-label="Regime" style="flex:1;min-width:130px">
                    <option value="vijf" <?= !$is_weekend?'selected':'' ?>>hele weken (5 dagen)</option>
                    <option value="weekend" <?= $is_weekend?'selected':'' ?>>alleen weekenden</option>
                  </select>
                  <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="b2BlokVerwijder(this)" title="Periode verwijderen" aria-label="Periode verwijderen" style="min-width:44px;min-height:38px"><?= icoon("kruis", 15) ?></button>
                </div>
                <div class="b2-weekend-dagen" style="<?= $is_weekend?'':'display:none;' ?>margin-top:8px;padding-top:8px;border-top:1px dashed var(--rand-licht)">
                  <span style="font-size:12px;color:var(--tekst-zacht);margin-right:6px">Welke dagen?</span>
                  <?php foreach ([5=>'vr',6=>'za',7=>'zo'] as $dn=>$dl): ?>
                  <label style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border:1px solid var(--rand);border-radius:6px;cursor:pointer;font-size:12.5px;margin-right:4px">
                    <input type="checkbox" class="b2-wkdag" value="<?= $dn ?>" <?= in_array($dn,$wd_sel)?'checked':'' ?> onchange="b2Herbereken()"> <?= $dl ?>
                  </label>
                  <?php endforeach; ?>
                  <input type="hidden" name="b2_blok_wkdagen[]" value="<?= h(json_encode($wd_sel)) ?>">
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="b2BlokToevoegen()">+ Nog een periode</button>
            <p style="font-size:11.5px;color:var(--grijs);margin-top:8px">Dit staat al ingevuld op het hele seizoen. Klopt dat? Dan hoef je hier niets te doen. Extra weekenden werken mag ook — kies dan "alleen weekenden".</p>
          </div>

          <!-- STAP 2: Uitzonderingen (optioneel, inklapbaar) -->
          <div style="background:var(--bg-card);border:1px solid var(--rand);border-radius:12px;padding:16px;margin-bottom:14px">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
              <span style="flex-shrink:0;width:24px;height:24px;border-radius:50%;background:var(--navy);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:600">2</span>
              <span style="font-weight:600;font-size:14.5px">Zijn er uitzonderingen?</span>
              <span style="margin-left:auto;font-size:11px;font-weight:600;padding:3px 10px;border-radius:11px;background:var(--grijs-bg);color:var(--tekst-zacht)">optioneel</span>
            </div>
            <p style="font-size:12.5px;color:var(--tekst-zacht);margin:0 0 12px">Open alleen wat op jou van toepassing is. Geen uitzonderingen? Dan ben je klaar — sla onderaan op.</p>

            <!-- Uitzondering: vakantie/afwezig -->
            <details class="b2-uitz" <?= $heeft_afwezig?'open':'' ?> style="border:1px solid var(--rand);border-radius:10px;margin-bottom:8px;background:var(--bg)">
              <summary style="padding:12px 14px;cursor:pointer;font-size:14px;list-style:none;display:flex;align-items:center;gap:10px">
                <span aria-hidden="true">✈️</span> Ik ben een hele periode weg (vakantie)
                <span class="b2-uitz-chev" style="margin-left:auto;color:var(--grijs)">›</span>
              </summary>
              <div style="padding:0 14px 14px">
                <p style="font-size:12px;color:var(--tekst-zacht);margin:0 0 10px">Bijvoorbeeld vakantie of kamp. Voor één losse dag gebruik je het blok hieronder.</p>
                <div id="b2AfwezigLijst">
                  <?php foreach ($b2['afwezig'] as $a): ?>
                  <div class="b2-afwezig-rij" style="display:flex;gap:6px;align-items:center;margin-bottom:6px;flex-wrap:wrap">
                    <input type="date" name="b2_afw_van[]" value="<?= h($a['van_datum']) ?>" onchange="b2Herbereken()" aria-label="Afwezig van" style="flex:1;min-width:130px">
                    <span style="color:var(--grijs)">t/m</span>
                    <input type="date" name="b2_afw_tot[]" value="<?= h($a['tot_datum']) ?>" onchange="b2Herbereken()" aria-label="Afwezig tot en met" style="flex:1;min-width:130px">
                    <input type="text" name="b2_afw_label[]" value="<?= h($a['label'] ?? '') ?>" placeholder="bijv. vakantie" aria-label="Omschrijving" style="flex:1;min-width:100px">
                    <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="this.parentElement.remove();b2Herbereken()" aria-label="Verwijderen" style="min-width:44px;min-height:38px"><?= icoon("kruis", 15) ?></button>
                  </div>
                  <?php endforeach; ?>
                </div>
                <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="b2AfwezigToevoegen()">+ Periode toevoegen</button>
              </div>
            </details>

            <!-- Uitzondering: losse dagen -->
            <details class="b2-uitz" <?= $heeft_losse?'open':'' ?> style="border:1px solid var(--rand);border-radius:10px;margin-bottom:8px;background:var(--bg)">
              <summary style="padding:12px 14px;cursor:pointer;font-size:14px;list-style:none;display:flex;align-items:center;gap:10px">
                Losse dagen dat ik niet kan
                <span class="b2-uitz-chev" style="margin-left:auto;color:var(--grijs)">›</span>
              </summary>
              <div style="padding:0 14px 14px">
                <p style="font-size:12px;color:var(--tekst-zacht);margin:0 0 10px">Kun je een week wél werken, maar één losse dag niet (bijv. 12 augustus, of die ene woensdag)? Geef die dag hier op.</p>
                <div id="b2LosseDagLijst">
                  <?php foreach ($losse_dagen as $ld): ?>
                  <div class="b2-losdag-rij" style="display:flex;gap:6px;align-items:center;margin-bottom:6px;flex-wrap:wrap">
                    <input type="date" name="b2_dag_datum[]" value="<?= h($ld['datum']) ?>" aria-label="Datum" style="flex:1;min-width:140px">
                    <select name="b2_dag_soort[]" aria-label="Soort" style="flex:1;min-width:130px">
                      <option value="niet_beschikbaar" <?= ($ld['soort']??'')==='niet_beschikbaar'?'selected':'' ?>>Kan ik niet</option>
                      <option value="voorkeur_vrij" <?= ($ld['soort']??'')==='voorkeur_vrij'?'selected':'' ?>>Liever vrij</option>
                    </select>
                    <input type="text" name="b2_dag_reden[]" value="<?= h($ld['reden'] ?? '') ?>" placeholder="reden (optioneel)" aria-label="Reden" style="flex:1;min-width:100px">
                    <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="this.parentElement.remove()" aria-label="Verwijderen" style="min-width:44px;min-height:38px"><?= icoon("kruis", 15) ?></button>
                  </div>
                  <?php endforeach; ?>
                </div>
                <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="b2LosseDagToevoegen()">+ Dag toevoegen</button>
              </div>
            </details>

            <!-- Uitzondering: vaste vrije dag -->
            <details class="b2-uitz" <?= $heeft_vaste?'open':'' ?> style="border:1px solid var(--rand);border-radius:10px;margin-bottom:8px;background:var(--bg)">
              <summary style="padding:12px 14px;cursor:pointer;font-size:14px;list-style:none;display:flex;align-items:center;gap:10px">
                <span aria-hidden="true">🔁</span> Een vaste dag die ik elke week vrij wil
                <span class="b2-uitz-chev" style="margin-left:auto;color:var(--grijs)">›</span>
              </summary>
              <div style="padding:0 14px 14px">
                <p style="font-size:12px;color:var(--tekst-zacht);margin:0 0 10px">
                  Heb je een terugkerende reden waardoor een vaste dag lastig is (school, sport, ander werk)? Geef het door als <strong>voorkeur</strong>.
                </p>
                <input type="checkbox" id="b2VasteAan" <?= $heeft_vaste?'checked':'' ?> onchange="b2VasteToggle()" style="display:none">
                <div id="b2VasteBlok">
                  <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px">
                    <?php foreach ([1=>'ma',2=>'di',3=>'wo',4=>'do',5=>'vr',6=>'za',7=>'zo'] as $dn => $dl): ?>
                    <label style="display:flex;align-items:center;gap:5px;padding:8px 14px;border:1px solid var(--rand);border-radius:6px;cursor:pointer;font-size:13px;min-height:40px">
                      <input type="checkbox" name="b2_vaste_uit[]" value="<?= $dn ?>" <?= in_array($dn, $vaste_sel) ? 'checked' : '' ?>
                             onchange="b2VasteDagCheck();b2Herbereken()"> <?= $dl ?>
                    </label>
                    <?php endforeach; ?>
                  </div>
                  <div style="padding:10px 14px;background:var(--blauw-bg);border-left:3px solid var(--blauw);border-radius:6px;font-size:12.5px;color:var(--tekst);margin-bottom:8px">
                    ℹ️ <strong>Dit is een voorkeur, geen vaste afspraak.</strong> We nemen het mee, maar het betekent niet dat je die dag automatisch vrij krijgt — de planning maken we later samen. Kun je een <strong>weekenddag</strong> (vr/za/zo) écht niet? Dat zijn onze drukste dagen, dus licht dat goed toe.
                  </div>
                  <textarea name="b2_vaste_toelichting" id="b2VasteToelichting" rows="2" placeholder="Waarom zou je deze dag(en) willen vrijhouden?" aria-label="Toelichting vaste vrije dag" style="width:100%"><?= h($b2p['vaste_toelichting'] ?? '') ?></textarea>
                </div>
              </div>
            </details>

            <!-- Uitzondering: minder dagen (verzoek) -->
            <details class="b2-uitz" <?= $heeft_verzoek?'open':'' ?> style="border:1px solid var(--rand);border-radius:10px;background:var(--bg)">
              <summary style="padding:12px 14px;cursor:pointer;font-size:14px;list-style:none;display:flex;align-items:center;gap:10px">
                <span aria-hidden="true">➖</span> In bepaalde weken minder dagen
                <span class="b2-uitz-chev" style="margin-left:auto;color:var(--grijs)">›</span>
              </summary>
              <div style="padding:0 14px 14px">
                <p style="font-size:12px;color:var(--tekst-zacht);margin:0 0 10px">
                  Standaard gaan we uit van 5 dagen per week. Wil je in bepaalde weken minder? Geef het door als <strong>verzoek</strong> — het bestuur beoordeelt dit. Je kunt optioneel aangeven welke dagen je die week <strong>wél</strong> kunt.
                </p>
                <div id="b2VerzoekLijst">
                  <?php foreach ($b2['verzoeken'] as $vz): if ($vz['status']!=='open' && $vz['status']!=='goedgekeurd') continue;
                    $pool_sel = $vz['dagen_pool'] ?: [];
                    // Lege pool betekent "alle dagen" — toon dan alles aangevinkt (i.p.v. impliciet niks).
                    if (!$pool_sel) $pool_sel = [1,2,3,4,5,6,7]; ?>
                  <div class="b2-verzoek-rij" style="border:1px solid var(--rand-licht);border-radius:8px;padding:10px;margin-bottom:8px">
                    <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                      <span style="font-size:12px;color:var(--grijs)">van</span>
                      <select name="b2_vz_van[]" aria-label="Verzoek van week" style="flex:1;min-width:105px">
                        <?php foreach ($week_opties as $wn => $lbl): ?><option value="<?= $wn ?>" <?= (int)$vz['van_week']===$wn?'selected':'' ?>><?= h($lbl) ?></option><?php endforeach; ?>
                      </select>
                      <span style="font-size:12px;color:var(--grijs)">t/m</span>
                      <select name="b2_vz_tot[]" aria-label="Verzoek tot week" style="flex:1;min-width:105px">
                        <?php foreach ($week_opties as $wn => $lbl): ?><option value="<?= $wn ?>" <?= (int)$vz['tot_week']===$wn?'selected':'' ?>><?= h($lbl) ?></option><?php endforeach; ?>
                      </select>
                      <input type="number" name="b2_vz_dagen[]" value="<?= (int)$vz['dagen_aantal'] ?>" min="1" max="6" aria-label="Aantal dagen" style="width:60px" placeholder="dagen">
                      <span style="font-size:12px;color:var(--grijs)">dagen</span>
                      <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="this.closest('.b2-verzoek-rij').remove()" aria-label="Verwijderen" style="min-width:44px;min-height:38px"><?= icoon("kruis", 15) ?></button>
                      <?php if ($vz['status']==='goedgekeurd'): ?><span style="font-size:11px;color:var(--groen)">✓ goedgekeurd</span><?php endif; ?>
                    </div>
                    <input type="text" name="b2_vz_toelichting[]" value="<?= h($vz['toelichting'] ?? '') ?>" placeholder="reden (bijv. school)" aria-label="Reden" style="width:100%;margin-top:6px">
                    <div style="margin-top:8px">
                      <span style="font-size:12px;color:var(--tekst-zacht);margin-right:6px">Welke dagen kun je die week wél? Vink de dagen uit die niet kunnen.</span>
                      <?php foreach ([1=>'ma',2=>'di',3=>'wo',4=>'do',5=>'vr',6=>'za',7=>'zo'] as $dn=>$dl): ?>
                      <label style="display:inline-flex;align-items:center;gap:3px;padding:3px 8px;border:1px solid var(--rand);border-radius:5px;cursor:pointer;font-size:12px;margin:2px 2px 0 0">
                        <input type="checkbox" class="b2-vz-pooldag" value="<?= $dn ?>" <?= in_array($dn,$pool_sel)?'checked':'' ?>> <?= $dl ?>
                      </label>
                      <?php endforeach; ?>
                      <input type="hidden" name="b2_vz_pool[]" value="<?= h(json_encode($pool_sel)) ?>">
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
                <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="b2VerzoekToevoegen()">+ Verzoek toevoegen</button>
              </div>
            </details>
          </div>

          <!-- Overzicht: zo begrijpen wij het -->
          <div style="background:var(--groen-bg,var(--grijs-bg));border:1px solid var(--rand);border-radius:12px;padding:16px;margin-bottom:14px">
            <div style="font-weight:600;font-size:13.5px;margin-bottom:8px">Zo begrijpen wij het <span id="b2WekenTeller" style="font-weight:400;color:var(--tekst-zacht)"></span></div>
            <div id="b2Preview" aria-live="polite" style="font-size:13px;line-height:1.7;color:var(--tekst)">
              <span style="color:var(--grijs);font-style:italic">Vul je periode in…</span>
            </div>
            <div id="b2OverlapWaarschuwing" style="display:none;margin-top:10px;padding:9px 12px;background:var(--amber-bg);border-left:3px solid var(--amber);border-radius:6px;font-size:12.5px;color:var(--amber)"></div>
          </div>

          <!-- 6. Voorlopig / definitief -->
          <div style="background:var(--oranje-bg);border:1px solid var(--oranje);border-radius:10px;padding:14px 16px;margin-bottom:12px">
            <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer">
              <input type="checkbox" name="b2_voorlopig" value="1" <?= !empty($b2p['voorlopig']) ? 'checked' : '' ?> style="margin-top:3px;width:18px;height:18px">
              <span style="font-size:13px;line-height:1.6">
                <strong>Mijn beschikbaarheid is nog voorlopig.</strong><br>
                <span style="color:var(--tekst-zacht)">Weet je het nog niet zeker? Geen probleem — vul in wat je nu weet en vink dit aan. Je kunt het later altijd bijwerken via je portaal. <strong>Let op:</strong> werk je het niet bij, dan gebruiken we je huidige invoer automatisch vanaf <strong>2 weken vóór het trainingsweekend</strong>.</span>
              </span>
            </label>
          </div>
