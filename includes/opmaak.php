<?php
/**
 * Paal 13 — hoe we datums, bedragen en cijfers schrijven. Eén bron voor beheer, portaal, mail en
 * landingspagina's: DateTime::format('M'/'F'/'l') is Engels, en losse lijstjes per scherm liepen
 * uit elkaar ("11 apr" hier, "11 april" daar, "€12,34" naast "€ 1.234").
 *
 * Geen database, geen HTML. Geladen door includes/functions.php (en door de harnas-stub).
 *
 *   datum_nl('2026-04-11')        11 apr 2026        lijsten, logboek, tabellen
 *   datum_kort_nl('2026-04-11')   11 apr             rasterkoppen, weekranges
 *   datum_dag_nl('2026-04-11')    zaterdag 11 apr    koppen per dag
 *   datum_lang_nl('2026-04-11')   11 april 2026      lopende tekst (ook met dag en/of zonder jaar)
 *   euro(1234.5)                  € 1.235            bedragen in het beheer (met centen: euro($v, 2))
 *   cijfer_nl(7.25)               7,3                scores en gemiddelden (null → —)
 */

if (!function_exists('maanden_kort_nl')) {

/** De Nederlandse maandafkortingen, 1-gebaseerd. */
function maanden_kort_nl(): array {
    return [1=>'jan',2=>'feb',3=>'mrt',4=>'apr',5=>'mei',6=>'jun',7=>'jul',8=>'aug',9=>'sep',10=>'okt',11=>'nov',12=>'dec'];
}

/** De volledige Nederlandse maandnamen, 1-gebaseerd. */
function maanden_nl(): array {
    return [1=>'januari',2=>'februari',3=>'maart',4=>'april',5=>'mei',6=>'juni',
            7=>'juli',8=>'augustus',9=>'september',10=>'oktober',11=>'november',12=>'december'];
}

/** De weekdagen, 1 = maandag … 7 = zondag (zoals DateTime::format('N')). */
function dagen_nl(): array {
    return [1=>'maandag',2=>'dinsdag',3=>'woensdag',4=>'donderdag',5=>'vrijdag',6=>'zaterdag',7=>'zondag'];
}

/** Korte weekdagen, 1 = ma … 7 = zo (dagknoppen, rasters). */
function dagen_kort_nl(): array {
    return [1=>'ma',2=>'di',3=>'wo',4=>'do',5=>'vr',6=>'za',7=>'zo'];
}

/** Een DateTime uit een string of DateTime; null als het geen datum is. */
function opmaak_dt($d): ?DateTimeInterface {
    if (!$d) return null;
    if ($d instanceof DateTimeInterface) return $d;
    try { return new DateTime((string)$d); } catch (\Exception $e) { return null; }
}

function datum_nl(?string $d): string {
    if (!$d) return '—';
    $dt = opmaak_dt($d);
    return $dt ? $dt->format('j') . ' ' . maanden_kort_nl()[(int)$dt->format('n')] . ' ' . $dt->format('Y') : $d;
}

/** "22 jun" — dag en maand zonder jaartal, voor weekranges en rasterkoppen. */
function datum_kort_nl($d): string {
    if (!$d) return '';
    $dt = opmaak_dt($d);
    return $dt ? $dt->format('j') . ' ' . maanden_kort_nl()[(int)$dt->format('n')] : (string)$d;
}

/** "zaterdag 3 mei" — weekdag, dag en maand, voor koppen per dag. */
function datum_dag_nl($d): string {
    if (!$d) return '';
    $dt = opmaak_dt($d);
    return $dt ? dagen_nl()[(int)$dt->format('N')] . ' ' . datum_kort_nl($dt) : (string)$d;
}

/** "11 april 2026", "zaterdag 11 april" enz.: voluit, voor lopende tekst. */
function datum_lang_nl($d, bool $met_dag = false, bool $met_jaar = true): string {
    if (!$d) return '';
    $dt = opmaak_dt($d);
    if (!$dt) return (string)$d;
    return ($met_dag ? dagen_nl()[(int)$dt->format('N')] . ' ' : '')
         . $dt->format('j') . ' ' . maanden_nl()[(int)$dt->format('n')]
         . ($met_jaar ? ' ' . $dt->format('Y') : '');
}

/** "€ 1.235" of met centen "€ 12,34" — euroteken, vaste spatie, Nederlandse scheidingstekens. */
function euro(float $v, int $decimalen = 0): string {
    return "€\u{00A0}" . number_format($v, $decimalen, ',', '.');
}

/** Een score of gemiddelde met één decimaal en een komma ("7,3"); null wordt "—". */
function cijfer_nl(?float $v, int $decimalen = 1): string {
    return $v === null ? '—' : number_format($v, $decimalen, ',', '');
}

}
