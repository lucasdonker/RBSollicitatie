<?php
/**
 * Paal 13 — AI Analyse via Gemini
 *
 * PRINCIPE: Alleen cron_ai.php roept de Gemini API aan.
 * Alles anders voegt toe aan de wachtrij en wacht.
 */

// ── Setup ─────────────────────────────────────────────────────
function ai_queue_auto_setup(): void {
    static $done = false;
    if ($done) return; $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS `ai_queue` (
            `id`              INT AUTO_INCREMENT PRIMARY KEY,
            `sollicitatie_id` INT NOT NULL,
            `type`            VARCHAR(50) NOT NULL DEFAULT 'blk_analyse',
            `status`          ENUM('wacht','bezig','klaar','fout') NOT NULL DEFAULT 'wacht',
            `pogingen`        TINYINT NOT NULL DEFAULT 0,
            `fout`            TEXT DEFAULT NULL,
            `aangemaakt_op`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `verwerkt_op`     DATETIME DEFAULT NULL,
            `volgende_poging` DATETIME DEFAULT NULL,
            UNIQUE KEY `sol_type` (`sollicitatie_id`,`type`),
            INDEX `idx_wacht` (`status`,`volgende_poging`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Exception $e) {}
}

// ── Wachtrij ─────────────────────────────────────────────────
/**
 * Voegt toe aan wachtrij.
 * Doet GEEN API call. Skipt als analyse al klaar is of al in wachtrij.
 * Returns: 'al_klaar' | 'al_in_wachtrij' | 'toegevoegd' | 'fout'
 */
function ai_queue_toevoegen(int $sol_id, string $type = 'blk_analyse'): string {
    ai_queue_auto_setup();
    try {
        // Al geanalyseerd?
        $check_analyse = db()->prepare("SELECT ai_analyse FROM blk_formulieren WHERE sollicitatie_id=? AND ai_analyse IS NOT NULL AND ai_analyse != ''");
        $check_analyse->execute([$sol_id]);
        if ($check_analyse->fetchColumn()) return 'al_klaar';

        // Al in wachtrij (wacht of bezig)?
        $check_q = db()->prepare("SELECT status FROM ai_queue WHERE sollicitatie_id=? AND type=?");
        $check_q->execute([$sol_id, $type]);
        $bestaand = $check_q->fetch();
        if ($bestaand) {
            if (in_array($bestaand['status'], ['wacht','bezig'])) return 'al_in_wachtrij';
            // Was fout of klaar maar geen analyse → reset
            db()->prepare("UPDATE ai_queue SET status='wacht', pogingen=0, fout=NULL, volgende_poging=NOW(), aangemaakt_op=NOW() WHERE sollicitatie_id=? AND type=?")
               ->execute([$sol_id, $type]);
            return 'toegevoegd';
        }

        db()->prepare("INSERT INTO ai_queue (sollicitatie_id,type,status,volgende_poging) VALUES (?,?,'wacht',NOW())")
           ->execute([$sol_id, $type]);
        return 'toegevoegd';
    } catch (\Exception $e) {
        error_log("AI queue fout: " . $e->getMessage());
        return 'fout';
    }
}

function ai_queue_status(int $sol_id): ?array {
    ai_queue_auto_setup();
    try {
        $s = db()->prepare("SELECT * FROM ai_queue WHERE sollicitatie_id=? ORDER BY aangemaakt_op DESC LIMIT 1");
        $s->execute([$sol_id]);
        return $s->fetch() ?: null;
    } catch (\Exception $e) { return null; }
}

/**
 * Verwerk N items uit de wachtrij. ALLEEN hier wordt de API aangeroepen.
 * Aangeroepen door cron_ai.php.
 */
function ai_queue_verwerk(int $max = 5): array {
    ai_queue_auto_setup();
    $res = ['verwerkt'=>0,'overgeslagen'=>0,'fouten'=>0];
    if (!instelling('ai.api_key')) return $res;

    try {
        $stmt = db()->prepare("
            SELECT q.id, q.sollicitatie_id, q.pogingen
            FROM ai_queue q
            WHERE q.status = 'wacht'
            AND (q.volgende_poging IS NULL OR q.volgende_poging <= NOW())
            AND q.pogingen < 3
            ORDER BY q.aangemaakt_op ASC
            LIMIT ?
        ");
        $stmt->execute([$max]);
        $items = $stmt->fetchAll();
    } catch (\Exception $e) { return $res; }

    foreach ($items as $item) {
        $sol_id = (int)$item['sollicitatie_id'];

        // Markeer als bezig
        db()->prepare("UPDATE ai_queue SET status='bezig', pogingen=pogingen+1 WHERE id=?")
           ->execute([$item['id']]);

        // Haal data op
        try {
            $bq = db()->prepare("SELECT * FROM blk_formulieren WHERE sollicitatie_id=?");
            $bq->execute([$sol_id]); $blk = $bq->fetch();
            if (!$blk) {
                db()->prepare("UPDATE ai_queue SET status='fout', fout='Geen BLK gevonden' WHERE id=?")->execute([$item['id']]);
                $res['fouten']++; continue;
            }

            $seizoen = actief_seizoen();
            $peil    = $seizoen['datum_werk_start'] ?? date('Y').'-06-01';
            $pq = db()->prepare("SELECT p.geboortedatum FROM sollicitaties s JOIN personen p ON p.id=s.persoon_id WHERE s.id=?");
            $pq->execute([$sol_id]); $prs = $pq->fetch();
            $lft = $prs ? leeftijd_op_datum($prs['geboortedatum'], $peil) : 20;

            // ← ENIGE PLEK WAAR DE API WORDT AANGEROEPEN
            $analyse = ai_doe_api_call($blk, $lft);
        } catch (\Exception $e) {
            $analyse = '__FOUT__: ' . $e->getMessage();
        }

        // Verwerk resultaat
        if ($analyse && !str_starts_with($analyse, '__')) {
            ai_sla_analyse_op($sol_id, $analyse);
            db()->prepare("UPDATE ai_queue SET status='klaar', verwerkt_op=NOW(), fout=NULL WHERE id=?")
               ->execute([$item['id']]);
            $res['verwerkt']++;
        } else {
            $is_rate_limit = str_starts_with($analyse ?? '', '__RATE_LIMIT__');
            $pogingen = (int)$item['pogingen'];

            if ($is_rate_limit || $pogingen < 2) {
                // Backoff: 5 min → 15 min → 45 min
                $delay = [5, 15, 45][$pogingen] ?? 45;
                db()->prepare("UPDATE ai_queue SET status='wacht', fout=?, volgende_poging=DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id=?")
                   ->execute([$analyse, $delay, $item['id']]);
            } else {
                db()->prepare("UPDATE ai_queue SET status='fout', fout=? WHERE id=?")->execute([$analyse, $item['id']]);
            }
            $res['fouten']++;

            // Stop bij rate limit — wacht op volgende cron run
            if ($is_rate_limit) break;
        }

        // 1 seconde pauze tussen calls (Gemini: max 15 RPM = ~4 sec per call veilig)
        sleep(1);
    }

    return $res;
}

// ── Gemini API call (privé, alleen via ai_queue_verwerk) ─────
function ai_doe_api_call(array $blk, int $leeftijd): ?string {
    $api_key = instelling('ai.api_key');
    if (!$api_key) return null;
    if (!function_exists('curl_init')) return '__CURL_NIET_BESCHIKBAAR__';

    $lft_cat = match(true) {
        $leeftijd < 16 => 'Minderjarige (15 jaar)',
        $leeftijd < 18 => 'Minderjarige (16-17 jaar)',
        $leeftijd < 21 => 'Jong volwassene (18-20 jaar)',
        $leeftijd < 25 => 'Volwassene (21-24 jaar)',
        $leeftijd < 30 => 'Volwassene (25-29 jaar)',
        default        => 'Volwassene (30+ jaar)',
    };

    $weken_arr = json_decode($blk['beschikbaarheid_intentie'] ?? '', true);
    $besch = is_array($weken_arr) ? count($weken_arr) . ' weken' : ($blk['beschikbaarheid_intentie'] ?: 'onbekend');

    $prompt = "Je bent een kritisch selectiecommissielid voor Reddingsbrigade Paal 13, een professionele reddingsbrigade. Je taak is een eerlijk, realistisch assessment te schrijven — niet bemoedigend maar objectief. Wees bereid negatieve conclusies te trekken als de gegevens daartoe aanleiding geven.

ANONIEME SOLLICITANTGEGEVENS:
- Leeftijdscategorie: $lft_cat
- Opleiding: " . ($blk['opleiding'] ?: 'Niet opgegeven') . "
- Diploma's en certificaten: " . ($blk['diplomas'] ?: 'Geen') . "
- Werkervaring: " . ($blk['werkervaring'] ?: 'Geen') . "
- Motivatie (lifeguard): " . ($blk['motivatie_lifeguard'] ?: 'Niet ingevuld') . "
- Motivatie (strand): " . ($blk['motivatie_strand'] ?: 'Niet ingevuld') . "
- Beschikbaarheid: $besch
- Bijzonderheden: " . ($blk['bijzonderheden'] ?: 'Geen') . "

Schrijf een beknopt assessment in dit exacte format (geen inleiding, direct beginnen):

**Geschiktheid:** [★ t/m ★★★★★] — [één eerlijke zin: waarom wel of niet geschikt, zonder omhaal]

**Bijdrage aan de groep:** [2-3 zinnen: wat voegt deze persoon concreet toe aan het team? Denk aan: leeftijd t.o.v. groep, werkstijl die je verwacht op basis van opleiding/motivatie, ervaring die anderen missen of juist al hebben. Wees realistisch — ook 'weinig toegevoegde waarde' is een geldige conclusie.]

**Toekomstperspectief:** [1-2 zinnen: hoeveel seizoenen verwacht je dat deze persoon inzetbaar is? Onderbouw met opleiding, leeftijd, motivatie.]

**Aandachtspunten:** [1-3 concrete zorgen of vraagtekens op basis van de gegevens. Als er geen zijn: vermeld dat expliciet. Verzin geen zorgen, maar sla ook niets over.]

**Advies:** [Uitnodigen ✓ / Twijfel ⚠️ / Niet uitnodigen ✗] — [één zin waarom, wees direct]";

    $payload = json_encode([
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => 0.2]
    ]);

    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=$api_key");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err)        return '__CURL_FOUT__: ' . $err;
    if ($http === 429) return '__RATE_LIMIT__';
    if ($http !== 200) {
        $msg = json_decode($resp, true)['error']['message'] ?? "HTTP $http";
        return '__API_FOUT__: ' . $msg;
    }

    return json_decode($resp, true)['candidates'][0]['content']['parts'][0]['text'] ?? null;
}

// ── Helpers ───────────────────────────────────────────────────
function ai_sla_analyse_op(int $sol_id, string $analyse): void {
    try {
        db()->exec("ALTER TABLE blk_formulieren ADD COLUMN IF NOT EXISTS ai_analyse TEXT DEFAULT NULL");
        db()->exec("ALTER TABLE blk_formulieren ADD COLUMN IF NOT EXISTS ai_analyse_op DATETIME DEFAULT NULL");
        db()->prepare("UPDATE blk_formulieren SET ai_analyse=?, ai_analyse_op=NOW() WHERE sollicitatie_id=?")
           ->execute([$analyse, $sol_id]);
    } catch (\Exception $e) { error_log("AI opslaan: " . $e->getMessage()); }
}

function ai_render(string $tekst): string {
    $h = htmlspecialchars($tekst, ENT_QUOTES, 'UTF-8');
    $h = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $h);
    $h = preg_replace('/^(\d+\.\s)/m', '<br><strong>$1</strong>', $h);
    $h = preg_replace('/^[-•]\s(.+)/m', '<br>• $1', $h);
    return trim(ltrim(nl2br($h), '<br>'));
}

function invite_aanbeveling(int $leeftijd): array {
    $min = instelling_int('invite.min_leeftijd', 16);
    $max = instelling_int('invite.max_leeftijd', 30);
    $modus = instelling('invite.modus', 'suggestie');
    $ok = ($leeftijd >= $min && $leeftijd <= $max);
    return ['voldoet'=>$ok,'min'=>$min,'max'=>$max,'modus'=>$modus,
            'label'=>$ok ? "✓ Leeftijd {$leeftijd}jr voldoet ({$min}–{$max}jr)" : "✗ Leeftijd {$leeftijd}jr buiten criteria"];
}