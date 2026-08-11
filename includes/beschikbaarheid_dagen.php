<?php
/**
 * Seizoen-hulpfuncties voor beschikbaarheid.
 *
 * Let op: het oude dag-precieze "verhinderingen"-model is vervangen door de
 * bestuurs-correcties in beschikbaarheid2.php (zie beschikbaarheid2_correctie_zetten).
 * De tabel `beschikbaarheid_verhinderingen` blijft in de database staan zodat er
 * geen historische data verloren gaat, maar wordt niet meer gelezen of geschreven.
 *
 * Wat hier over is, zijn twee helpers die elders nog gebruikt worden.
 */

/** Weektype van een week: 'voor' | 'hoofd' | 'na' (t.o.v. het hoofdseizoen). */
function seizoen_weektype(array $seizoen, int $week): string {
    $start = (int)($seizoen['hoofdseizoen_start_week'] ?? 0);
    $eind  = (int)($seizoen['hoofdseizoen_eind_week'] ?? 0);
    if (!$start || !$eind) return 'hoofd';
    if ($week < $start) return 'voor';
    if ($week > $eind)  return 'na';
    return 'hoofd';
}

/** Korte dagnaam: 1=ma … 7=zo. */
function dag_kort(int $dag): string {
    $d = ['', 'ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'];
    return $d[$dag] ?? (string)$dag;
}
