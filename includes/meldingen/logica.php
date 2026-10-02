<?php
/**
 * Paal 13 — Meldingen: logica in sjablonen (als / anders / kies) en losse voorwaarden.
 *
 * Bewust een eigen, kleine taal: geen eval, geen PHP-templating. Het bestuur schrijft hem in een
 * editor, dus hij moet klein, voorspelbaar en veilig zijn. Puur: geen database, geen HTML-uitvoer.
 * Waarden komen uit een callback fn(string $naam): mixed (includes/meldingen/feiten.php).
 *
 * Sjabloon:
 *   {{als leeftijd < 18}} … {{anders als fase = "aangenomen"}} … {{anders}} … {{/als}}
 *   {{kies fase}} {{geval "aangenomen"}} … {{geval "afgewezen", "afgehaakt"}} … {{anders}} … {{/kies}}
 *   {{einde}} sluit het binnenste blok. Engels werkt ook: if, elseif, else, switch, case, default, end.
 * Voorwaarde:
 *   en / of / niet (ook and, or, not, &&, ||, !), haakjes, = != < > <= >=, in (…), niet in (…),
 *   is [niet] leeg, bevat. Tekst tussen aanhalingstekens, getallen kaal, ja/nee.
 *
 * Een gewone {{variabele}} laat deze laag ongemoeid; die vult melding_render() daarna in. Daardoor
 * wordt ingevulde tekst (een naam met "{{" erin) nooit als logica gelezen.
 * Een blok-tag die alleen in een alinea (e-mail) of op een eigen regel (korte tekst) staat, neemt
 * die alinea of regel mee: zo laat een weggevallen blok geen lege regel achter.
 */

if (!class_exists('SjabloonFout')) {
    /** Een fout in de schrijfwijze van een sjabloon of voorwaarde; de tekst is voor het bestuur. */
    class SjabloonFout extends \RuntimeException {}
}

if (!function_exists('sjabloon_heeft_logica')) {

/** Snelle check: staat er een blok-tag in? Zonder blok-tag verandert er niets aan het sjabloon. */
function sjabloon_heeft_logica(string $tekst): bool {
    return strpos($tekst, '{{') !== false && (bool)preg_match(
        '~\{\{(?:\s|<[^>]*>|&nbsp;)*(?:/\s*)?(?:als|if|anders|else|elseif|elif|standaard|default|kies|switch|geval|case|einde|end|endif|endswitch)(?![\w.])~i',
        $tekst);
}

/** De inhoud van een {{…}}-tag als platte tekst (de editor zet er soms opmaak of &quot; in). */
function sjabloon_tag_tekst(string $tag, bool $html): string {
    $s = substr($tag, 2, -2);
    if ($html) {
        // Alleen echte tags weghalen (geen strip_tags: "leeftijd <18" zou dan verdwijnen).
        $s = (string)preg_replace('~</?[a-z][^>]*>~i', '', $s);
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return trim(str_replace("\xC2\xA0", ' ', $s));
}

/**
 * Wat voor tag is dit? [soort, rest] of null (een gewone variabele).
 * Soorten: als, andersals, anders, kies, geval, einde_als, einde_kies, einde.
 */
function sjabloon_tag_soort(string $inhoud): ?array {
    $s = trim($inhoud);
    if (preg_match('~^/\s*(als|if)$|^endif$~i', $s))       return ['einde_als', ''];
    if (preg_match('~^/\s*(kies|switch)$|^endswitch$~i', $s)) return ['einde_kies', ''];
    if (preg_match('~^(einde|end)$~i', $s))                 return ['einde', ''];
    if (preg_match('~^(?:anders\s+als|else\s*if|elif)(?![\w.])(.*)$~is', $s, $m)) return ['andersals', trim($m[1])];
    if (preg_match('~^(?:anders|else|standaard|default)$~i', $s)) return ['anders', ''];
    if (preg_match('~^(?:als|if)(?![\w.])(.*)$~is', $s, $m))       return ['als', trim($m[1])];
    if (preg_match('~^(?:kies|switch)(?![\w.])(.*)$~is', $s, $m))  return ['kies', trim($m[1])];
    if (preg_match('~^(?:geval|case)(?![\w.])(.*)$~is', $s, $m))   return ['geval', trim($m[1])];
    return null;
}

/** Alleen de blok-tags uit een stuk, of null als er ook iets anders (een variabele) in staat. */
function sjabloon_alleen_logica(string $stuk, bool $html): ?string {
    if (!preg_match_all('~\{\{(?:(?!\}\}).)*\}\}~s', $stuk, $m)) return null;
    foreach ($m[0] as $tag) if (sjabloon_tag_soort(sjabloon_tag_tekst($tag, $html)) === null) return null;
    return implode('', $m[0]);
}

/** Een alinea (e-mail) of regel (korte tekst) met alleen blok-tags: alleen de tags houden. */
function sjabloon_logica_opruimen(string $tekst, bool $html): string {
    $tags = '((?:\{\{(?:(?!\}\}).)*\}\}(?:\s|&nbsp;|&#160;|\xC2\xA0|<br\s*/?>)*)+)';
    if ($html) {
        $tekst = (string)preg_replace_callback('~<(p|div)\b[^>]*>(?:\s|&nbsp;|&#160;|\xC2\xA0|<br\s*/?>)*' . $tags . '</\1>~is',
            fn($m) => sjabloon_alleen_logica($m[2], true) ?? $m[0], $tekst);
        // Regels binnen één alinea (korte tekst als e-mail: "Hoi<br>{{als …}}<br>…"): zoals bij platte tekst
        // vervalt de <br> ná de blokregel, of die ervóór als het de laatste regel van de alinea is.
        $tekst = (string)preg_replace_callback('~(<p\b[^>]*>|<br\s*/?>)\s*' . $tags . '<br\s*/?>~is',
            fn($m) => ($t = sjabloon_alleen_logica($m[2], true)) !== null ? $m[1] . $t : $m[0], $tekst);
        return (string)preg_replace_callback('~<br\s*/?>\s*((?:\{\{(?:(?!\}\}).)*\}\}\s*)+)(?=</p>)~is',
            fn($m) => sjabloon_alleen_logica($m[1], true) ?? $m[0], $tekst);
    }
    return (string)preg_replace_callback('~^[ \t]*((?:\{\{(?:(?!\}\}).)*\}\}[ \t]*)+)(?:\r?\n|\z)~m',
        fn($m) => sjabloon_alleen_logica($m[1], false) ?? $m[0], $tekst);
}

/** Sjabloon → boom. Gooit SjabloonFout bij een fout in de opbouw of in een voorwaarde. */
function sjabloon_logica_ontleed(string $tekst, bool $html): array {
    $delen = preg_split('~(\{\{(?:(?!\}\}).)*\}\})~s', sjabloon_logica_opruimen($tekst, $html), -1, PREG_SPLIT_DELIM_CAPTURE);
    $tokens = [];
    foreach ($delen as $nr => $deel) {
        if ($deel === '') continue;
        $soort = $nr % 2 === 1 ? sjabloon_tag_soort(sjabloon_tag_tekst($deel, $html)) : null;
        $tokens[] = $soort === null ? ['t', $deel] : ['tag', $soort[0], $soort[1], '{{' . sjabloon_tag_tekst($deel, $html) . '}}'];
    }
    $i = 0;
    $knopen = sjabloon_logica_blok($tokens, $i, []);
    if ($i < count($tokens)) throw new SjabloonFout(sjabloon_los_tag($tokens[$i]));
    return $knopen;
}

/** Uitleg bij een tag die ergens staat waar hij niet hoort. */
function sjabloon_los_tag(array $tok): string {
    $wat = [
        'andersals' => 'staat niet binnen een {{als …}}-blok, of na {{anders}}',
        'anders'    => 'staat niet binnen een {{als …}}- of {{kies …}}-blok, of staat er twee keer in',
        'geval'     => 'staat niet direct binnen een {{kies …}}-blok',
        'einde_als' => 'sluit niets af: er staat geen {{als …}} open',
        'einde_kies'=> 'sluit niets af: er staat geen {{kies …}} open',
        'einde'     => 'sluit niets af: er staat geen blok open',
    ][$tok[1]] ?? 'staat op een verkeerde plek';
    return $tok[3] . ' ' . $wat . '.';
}

/** Knopen tot een tag uit $stop (die blijft staan voor de aanroeper). */
function sjabloon_logica_blok(array $tokens, int &$i, array $stop): array {
    $knopen = [];
    $n = count($tokens);
    while ($i < $n) {
        $tok = $tokens[$i];
        if ($tok[0] === 't') { $knopen[] = ['t', $tok[1]]; $i++; continue; }
        if (in_array($tok[1], $stop, true)) return $knopen;
        if ($tok[1] === 'als')  { $knopen[] = sjabloon_logica_als($tokens, $i); continue; }
        if ($tok[1] === 'kies') { $knopen[] = sjabloon_logica_kies($tokens, $i); continue; }
        throw new SjabloonFout(sjabloon_los_tag($tok));
    }
    return $knopen;
}

function sjabloon_voorwaarde_van(array $tok): array {
    if ($tok[2] === '') throw new SjabloonFout($tok[3] . ' mist een voorwaarde, bijvoorbeeld {{als leeftijd < 18}}.');
    try { return voorwaarde_ontleed($tok[2]); }
    catch (SjabloonFout $e) { throw new SjabloonFout('In ' . $tok[3] . ': ' . $e->getMessage()); }
}

function sjabloon_logica_als(array $tokens, int &$i): array {
    $open  = $tokens[$i];
    $takken = [];
    $expr  = sjabloon_voorwaarde_van($open);
    $i++;
    $anders_gehad = false;
    while (true) {
        $knopen = sjabloon_logica_blok($tokens, $i, ['andersals', 'anders', 'einde_als', 'einde', 'einde_kies', 'geval']);
        $takken[] = [$expr, $knopen];
        if ($i >= count($tokens)) throw new SjabloonFout($open[3] . ' wordt niet afgesloten met {{/als}}.');
        $tok = $tokens[$i];
        if ($tok[1] === 'einde_als' || $tok[1] === 'einde') { $i++; return ['als', $takken]; }
        if ($anders_gehad || in_array($tok[1], ['einde_kies', 'geval'], true)) {
            throw new SjabloonFout($tok[1] === 'einde_kies' ? $tok[3] . ' sluit een {{als …}}-blok; gebruik {{/als}}.' : sjabloon_los_tag($tok));
        }
        $i++;
        if ($tok[1] === 'anders') { $anders_gehad = true; $expr = null; continue; }
        $expr = sjabloon_voorwaarde_van($tok);   // anders als
    }
}

function sjabloon_logica_kies(array $tokens, int &$i): array {
    $open = $tokens[$i];
    if ($open[2] === '') throw new SjabloonFout($open[3] . ' mist waar het om gaat, bijvoorbeeld {{kies fase}}.');
    try { $expr = voorwaarde_ontleed($open[2]); }
    catch (SjabloonFout $e) { throw new SjabloonFout('In ' . $open[3] . ': ' . $e->getMessage()); }
    $i++;
    // Tussen {{kies}} en het eerste {{geval}} mag alleen witruimte staan: dat wordt nooit getoond.
    $voor = sjabloon_logica_blok($tokens, $i, ['geval', 'anders', 'einde_kies', 'einde']);
    foreach ($voor as $k) {
        if ($k[0] !== 't' || trim(html_entity_decode(strip_tags($k[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t\n\r\0\x0B\xC2\xA0") !== '') {
            throw new SjabloonFout('Tekst tussen ' . $open[3] . ' en het eerste {{geval …}} wordt nooit getoond.');
        }
    }
    $gevallen = []; $standaard = null;
    while (true) {
        if ($i >= count($tokens)) throw new SjabloonFout($open[3] . ' wordt niet afgesloten met {{/kies}}.');
        $tok = $tokens[$i];
        if ($tok[1] === 'einde_kies' || $tok[1] === 'einde') { $i++; return ['kies', $expr, $gevallen, $standaard]; }
        if ($tok[1] === 'einde_als') throw new SjabloonFout($tok[3] . ' sluit een {{kies …}}-blok; gebruik {{/kies}}.');
        if ($tok[1] === 'geval' && $standaard === null) {
            if ($tok[2] === '') throw new SjabloonFout($tok[3] . ' mist een waarde, bijvoorbeeld {{geval "aangenomen"}}.');
            try { $waarden = voorwaarde_lijst_ontleed($tok[2]); }
            catch (SjabloonFout $e) { throw new SjabloonFout('In ' . $tok[3] . ': ' . $e->getMessage()); }
            $i++;
            $gevallen[] = [$waarden, sjabloon_logica_blok($tokens, $i, ['geval', 'anders', 'einde_kies', 'einde', 'einde_als', 'andersals'])];
            continue;
        }
        if ($tok[1] === 'anders' && $standaard === null) {
            $i++;
            $standaard = sjabloon_logica_blok($tokens, $i, ['geval', 'anders', 'einde_kies', 'einde', 'einde_als', 'andersals']);
            continue;
        }
        throw new SjabloonFout($tok[1] === 'geval' ? $tok[3] . ' staat na {{anders}}; zet {{anders}} als laatste.' : sjabloon_los_tag($tok));
    }
}

/** Boom → tekst. */
function sjabloon_logica_uit(array $knopen, callable $waarde): string {
    $uit = '';
    foreach ($knopen as $k) {
        if ($k[0] === 't') { $uit .= $k[1]; continue; }
        if ($k[0] === 'als') {
            foreach ($k[1] as $tak) {
                if ($tak[0] === null || voorwaarde_waar(voorwaarde_reken($tak[0], $waarde))) { $uit .= sjabloon_logica_uit($tak[1], $waarde); break; }
            }
            continue;
        }
        // kies
        $v = voorwaarde_reken($k[1], $waarde);
        $gekozen = $k[3];
        foreach ($k[2] as $geval) {
            foreach ($geval[0] as $w) {
                if (voorwaarde_gelijk($v, voorwaarde_reken($w, $waarde))) { $gekozen = $geval[1]; break 2; }
            }
        }
        if ($gekozen) $uit .= sjabloon_logica_uit($gekozen, $waarde);
    }
    return $uit;
}

/**
 * Pas de logica in een sjabloontekst toe. Zonder blok-tags komt de tekst ongewijzigd terug.
 * @throws SjabloonFout
 */
function sjabloon_logica(string $tekst, callable $waarde, bool $html = false): string {
    if (!sjabloon_heeft_logica($tekst)) return $tekst;
    return sjabloon_logica_uit(sjabloon_logica_ontleed($tekst, $html), $waarde);
}

/**
 * Fouten in een sjabloontekst, in woorden. Leeg = in orde.
 * $bekend: naam => lijst met toegestane waarden (keuzeveld) of null (vrij veld).
 */
function sjabloon_logica_fouten(string $tekst, bool $html, array $bekend): array {
    if (!sjabloon_heeft_logica($tekst)) return [];
    try { $knopen = sjabloon_logica_ontleed($tekst, $html); }
    catch (SjabloonFout $e) { return [$e->getMessage()]; }
    $fouten = [];
    $loop = function (array $knopen) use (&$loop, &$fouten, $bekend) {
        foreach ($knopen as $k) {
            if ($k[0] === 'als') {
                foreach ($k[1] as $tak) { if ($tak[0] !== null) $fouten = array_merge($fouten, voorwaarde_ast_fouten($tak[0], $bekend)); $loop($tak[1]); }
            } elseif ($k[0] === 'kies') {
                $fouten = array_merge($fouten, voorwaarde_ast_fouten($k[1], $bekend));
                foreach ($k[2] as $geval) {
                    foreach ($geval[0] as $w) $fouten = array_merge($fouten, voorwaarde_ast_fouten(['cmp', '=', $k[1], $w], $bekend));
                    $loop($geval[1]);
                }
                if ($k[3]) $loop($k[3]);
            }
        }
    };
    $loop($knopen);
    return array_values(array_unique($fouten));
}

// ─────────────────────────────────────────────────────────────
//  Voorwaarden
// ─────────────────────────────────────────────────────────────

/** Tekst → tokens ['s' => soort, 'w' => waarde, 'x' => zoals geschreven]. */
function voorwaarde_tokens(string $s): array {
    $woorden = ['en' => 'en', 'and' => 'en', 'of' => 'of', 'or' => 'of', 'niet' => 'niet', 'not' => 'niet',
                'in' => 'in', 'is' => 'is', 'bevat' => 'bevat', 'contains' => 'bevat'];
    $vast = ['ja' => true, 'waar' => true, 'true' => true, 'nee' => false, 'onwaar' => false, 'false' => false,
             'leeg' => null, 'null' => null, 'empty' => null];
    $uit = []; $i = 0; $n = strlen($s);
    while ($i < $n) {
        if (ctype_space($s[$i])) { $i++; continue; }
        if (preg_match('~\G(?:"([^"]*)"|\'([^\']*)\'|“([^”]*)”|„([^”“]*)[”“]|‘([^’]*)’)~u', $s, $m, PREG_UNMATCHED_AS_NULL, $i)) {
            $w = '';
            for ($g = 1; $g < count($m); $g++) if ($m[$g] !== null) { $w = $m[$g]; break; }
            $uit[] = ['s' => 'lit', 'w' => $w, 'x' => $m[0]]; $i += strlen($m[0]); continue;
        }
        if (preg_match('~\G\d+(?:[.,]\d+)?~', $s, $m, 0, $i)) {
            $uit[] = ['s' => 'lit', 'w' => (float)str_replace(',', '.', $m[0]) + 0, 'x' => $m[0]]; $i += strlen($m[0]); continue;
        }
        if (preg_match('~\G[a-z_][a-z0-9_]*(?:\.[a-z0-9_]+)*~i', $s, $m, 0, $i)) {
            $woord = strtolower($m[0]);
            if (isset($woorden[$woord]))              $uit[] = ['s' => $woorden[$woord], 'w' => null, 'x' => $m[0]];
            elseif (array_key_exists($woord, $vast))  $uit[] = ['s' => 'lit', 'w' => $vast[$woord], 'x' => $m[0]];
            else                                      $uit[] = ['s' => 'naam', 'w' => $woord, 'x' => $m[0]];
            $i += strlen($m[0]); continue;
        }
        if (preg_match('~\G(==|!=|<>|<=|>=|&&|\|\||=|<|>|\(|\)|\[|\]|,|!|-)~', $s, $m, 0, $i)) {
            $op = ['&&' => 'en', '||' => 'of', '!' => 'niet'][$m[0]] ?? null;
            $uit[] = $op ? ['s' => $op, 'w' => null, 'x' => $m[0]] : ['s' => 'op', 'w' => $m[0], 'x' => $m[0]];
            $i += strlen($m[0]); continue;
        }
        preg_match('~\G.~su', $s, $m, 0, $i);
        throw new SjabloonFout('Het teken "' . ($m[0] ?? $s[$i]) . '" begrijp ik niet. Zet tekst tussen aanhalingstekens.');
    }
    return $uit;
}

/** Voorwaarde → boom. @throws SjabloonFout */
function voorwaarde_ontleed(string $s): array {
    $t = voorwaarde_tokens($s);
    if (!$t) throw new SjabloonFout('De voorwaarde is leeg.');
    $i = 0;
    $ast = voorwaarde_p_of($t, $i);
    if ($i < count($t)) throw new SjabloonFout('Ik snap "' . $t[$i]['x'] . '" niet op die plek. Mist er "en" of "of"?');
    return $ast;
}

/** "a", "b", 3 → lijst met waarden (voor {{geval …}}). */
function voorwaarde_lijst_ontleed(string $s): array {
    $t = voorwaarde_tokens($s);
    $i = 0; $uit = [voorwaarde_p_waarde($t, $i)];
    while (($t[$i]['w'] ?? null) === ',') { $i++; $uit[] = voorwaarde_p_waarde($t, $i); }
    if ($i < count($t)) throw new SjabloonFout('Ik snap "' . $t[$i]['x'] . '" niet. Scheid waarden met een komma.');
    return $uit;
}

function voorwaarde_p_of(array $t, int &$i): array {
    $a = voorwaarde_p_en($t, $i);
    while (($t[$i]['s'] ?? '') === 'of') { $i++; $a = ['of', $a, voorwaarde_p_en($t, $i)]; }
    return $a;
}

function voorwaarde_p_en(array $t, int &$i): array {
    $a = voorwaarde_p_niet($t, $i);
    while (($t[$i]['s'] ?? '') === 'en') { $i++; $a = ['en', $a, voorwaarde_p_niet($t, $i)]; }
    return $a;
}

function voorwaarde_p_niet(array $t, int &$i): array {
    if (($t[$i]['s'] ?? '') === 'niet') { $i++; return ['niet', voorwaarde_p_niet($t, $i)]; }
    return voorwaarde_p_vergelijk($t, $i);
}

function voorwaarde_p_vergelijk(array $t, int &$i): array {
    $a = voorwaarde_p_waarde($t, $i);
    $tok = $t[$i] ?? null;
    if (!$tok) return $a;
    if ($tok['s'] === 'op' && in_array($tok['w'], ['=', '==', '!=', '<>', '<', '>', '<=', '>='], true)) {
        $i++;
        $op = ['==' => '=', '<>' => '!='][$tok['w']] ?? $tok['w'];
        return ['cmp', $op, $a, voorwaarde_p_waarde($t, $i)];
    }
    if ($tok['s'] === 'in') { $i++; return ['in', $a, voorwaarde_p_lijst($t, $i), false]; }
    if ($tok['s'] === 'niet' && ($t[$i + 1]['s'] ?? '') === 'in') { $i += 2; return ['in', $a, voorwaarde_p_lijst($t, $i), true]; }
    if ($tok['s'] === 'is') {
        $i++;
        $neg = false;
        if (($t[$i]['s'] ?? '') === 'niet') { $neg = true; $i++; }
        return ['cmp', $neg ? '!=' : '=', $a, voorwaarde_p_waarde($t, $i)];
    }
    if ($tok['s'] === 'bevat') { $i++; return ['bevat', $a, voorwaarde_p_waarde($t, $i)]; }
    return $a;
}

function voorwaarde_p_lijst(array $t, int &$i): array {
    $open = $t[$i]['w'] ?? null;
    if ($open !== '(' && $open !== '[') throw new SjabloonFout('Na "in" verwacht ik een lijst, bijvoorbeeld ("aangemeld", "aangenomen").');
    $sluit = $open === '(' ? ')' : ']';
    $i++;
    $uit = [voorwaarde_p_waarde($t, $i)];
    while (($t[$i]['w'] ?? null) === ',') { $i++; $uit[] = voorwaarde_p_waarde($t, $i); }
    if (($t[$i]['w'] ?? null) !== $sluit) throw new SjabloonFout('De lijst wordt niet afgesloten met "' . $sluit . '".');
    $i++;
    return $uit;
}

function voorwaarde_p_waarde(array $t, int &$i): array {
    $tok = $t[$i] ?? null;
    if (!$tok) throw new SjabloonFout('De voorwaarde houdt te vroeg op.');
    if ($tok['s'] === 'lit')  { $i++; return ['lit', $tok['w']]; }
    if ($tok['s'] === 'naam') { $i++; return ['naam', $tok['w']]; }
    if ($tok['w'] === '-' && ($t[$i + 1]['s'] ?? '') === 'lit' && is_numeric($t[$i + 1]['w'])) { $i += 2; return ['lit', -$t[$i - 1]['w']]; }
    if ($tok['w'] === '(') {
        $i++;
        $a = voorwaarde_p_of($t, $i);
        if (($t[$i]['w'] ?? null) !== ')') throw new SjabloonFout('Er mist een ")".');
        $i++;
        return $a;
    }
    throw new SjabloonFout('Op de plek van "' . $tok['x'] . '" verwacht ik een veld, een waarde of "(".');
}

/** Reken een boom uit. Onbekende velden zijn leeg (null); dat breekt nooit een bericht. */
function voorwaarde_reken(array $ast, callable $waarde) {
    switch ($ast[0]) {
        case 'lit':   return $ast[1];
        case 'naam':  return $waarde($ast[1]);
        case 'niet':  return !voorwaarde_waar(voorwaarde_reken($ast[1], $waarde));
        case 'en':    return voorwaarde_waar(voorwaarde_reken($ast[1], $waarde)) && voorwaarde_waar(voorwaarde_reken($ast[2], $waarde));
        case 'of':    return voorwaarde_waar(voorwaarde_reken($ast[1], $waarde)) || voorwaarde_waar(voorwaarde_reken($ast[2], $waarde));
        case 'cmp':   return voorwaarde_vergelijk($ast[1], voorwaarde_reken($ast[2], $waarde), voorwaarde_reken($ast[3], $waarde));
        case 'in':
            $v = voorwaarde_reken($ast[1], $waarde);
            $in = false;
            foreach ($ast[2] as $w) if (voorwaarde_gelijk($v, voorwaarde_reken($w, $waarde))) { $in = true; break; }
            return $ast[3] ? !$in : $in;
        case 'bevat':
            $a = voorwaarde_reken($ast[1], $waarde); $b = voorwaarde_reken($ast[2], $waarde);
            if ($a === null || $b === null || is_bool($a)) return false;
            return strpos(voorwaarde_klein((string)$a), voorwaarde_klein((string)$b)) !== false;
    }
    return null;
}

function voorwaarde_klein(string $s): string {
    return function_exists('mb_strtolower') ? mb_strtolower(trim($s), 'UTF-8') : strtolower(trim($s));
}

function voorwaarde_leeg($v): bool {
    return $v === null || (is_string($v) && trim($v) === '');
}

function voorwaarde_waar($v): bool {
    if ($v === null) return false;
    if (is_bool($v)) return $v;
    if (is_int($v) || is_float($v)) return $v != 0;
    $s = trim((string)$v);
    return $s !== '' && $s !== '0';
}

function voorwaarde_gelijk($a, $b): bool {
    if ($a === null || $b === null) return voorwaarde_leeg($a) && voorwaarde_leeg($b);
    if (is_bool($a) || is_bool($b)) return voorwaarde_waar($a) === voorwaarde_waar($b);
    if (is_numeric($a) && is_numeric($b)) return (float)$a == (float)$b;
    return voorwaarde_klein((string)$a) === voorwaarde_klein((string)$b);
}

function voorwaarde_vergelijk(string $op, $a, $b): bool {
    if ($op === '=')  return voorwaarde_gelijk($a, $b);
    if ($op === '!=') return !voorwaarde_gelijk($a, $b);
    if ($a === null || $b === null || is_bool($a) || is_bool($b)) return false;   // "onbekend < 5" is nooit waar
    $c = is_numeric($a) && is_numeric($b) ? ((float)$a <=> (float)$b) : strcmp(voorwaarde_klein((string)$a), voorwaarde_klein((string)$b));
    return match ($op) { '<' => $c < 0, '>' => $c > 0, '<=' => $c <= 0, '>=' => $c >= 0, default => false };
}

/** Losse voorwaarde: waar of niet. @throws SjabloonFout */
function voorwaarde_toets(string $expr, callable $waarde): bool {
    return voorwaarde_waar(voorwaarde_reken(voorwaarde_ontleed($expr), $waarde));
}

/** Fouten in een losse voorwaarde, in woorden. Leeg = in orde. */
function voorwaarde_fouten(string $expr, array $bekend): array {
    try { $ast = voorwaarde_ontleed($expr); }
    catch (SjabloonFout $e) { return [$e->getMessage()]; }
    return array_values(array_unique(voorwaarde_ast_fouten($ast, $bekend)));
}

/**
 * Onbekende velden en waarden die een keuzeveld niet kent. Een kale waarde die bij het veld hoort
 * (fase = aangenomen) krijgt de tip om aanhalingstekens te gebruiken.
 */
function voorwaarde_ast_fouten(array $ast, array $bekend): array {
    $fouten = [];
    $keuze = function (array $kant) use ($bekend): ?array {
        return $kant[0] === 'naam' && !empty($bekend[$kant[1]]) ? $bekend[$kant[1]] : null;
    };
    $tegen = function (array $veld, array $w) use ($keuze, &$fouten): bool {   // true = afgehandeld
        $waarden = $keuze($veld);
        if ($waarden === null) return false;
        if ($w[0] === 'lit' && is_string($w[1]) && trim($w[1]) !== '' && !in_array(voorwaarde_klein($w[1]), array_map('voorwaarde_klein', $waarden), true)) {
            $fouten[] = '"' . $w[1] . '" is geen waarde van ' . $veld[1] . '. Mogelijk: ' . implode(', ', $waarden) . '.';
        }
        if ($w[0] === 'naam' && in_array($w[1], $waarden, true)) {
            $fouten[] = 'Zet ' . $w[1] . ' tussen aanhalingstekens: ' . $veld[1] . ' = "' . $w[1] . '".';
            return true;
        }
        return false;
    };
    switch ($ast[0]) {
        case 'naam':
            if (!array_key_exists($ast[1], $bekend)) $fouten[] = voorwaarde_onbekend_tekst($ast[1], array_keys($bekend));
            break;
        case 'niet':
            $fouten = voorwaarde_ast_fouten($ast[1], $bekend);
            break;
        case 'en': case 'of': case 'bevat':
            $fouten = array_merge(voorwaarde_ast_fouten($ast[1], $bekend), voorwaarde_ast_fouten($ast[2], $bekend));
            break;
        case 'cmp':
            $links = $ast[2]; $rechts = $ast[3];
            if (in_array($ast[1], ['=', '!='], true)) {
                if ($tegen($links, $rechts))      { $fouten = array_merge($fouten, voorwaarde_ast_fouten($links, $bekend)); break; }
                if ($tegen($rechts, $links))      { $fouten = array_merge($fouten, voorwaarde_ast_fouten($rechts, $bekend)); break; }
            }
            $fouten = array_merge($fouten, voorwaarde_ast_fouten($links, $bekend), voorwaarde_ast_fouten($rechts, $bekend));
            break;
        case 'in':
            $fouten = voorwaarde_ast_fouten($ast[1], $bekend);
            foreach ($ast[2] as $w) if (!$tegen($ast[1], $w)) $fouten = array_merge($fouten, voorwaarde_ast_fouten($w, $bekend));
            break;
    }
    return $fouten;
}

function voorwaarde_onbekend_tekst(string $naam, array $namen): string {
    $best = null; $afstand = 4;
    foreach ($namen as $n) {
        $d = levenshtein($naam, (string)$n);
        if ($d < $afstand) { $afstand = $d; $best = $n; }
    }
    return 'Onbekend veld "' . $naam . '".' . ($best !== null ? ' Bedoel je ' . $best . '?' : ' Tekst zet je tussen aanhalingstekens.');
}

}
