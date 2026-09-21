<?php
/**
 * Beheer — Foutenlog: bestaat niet meer als eigen scherm.
 *
 * Het logboek (includes/logboek/) leest error_log al als een van zijn zes bronnen, mét
 * het referentienummer, het bestand, de regel en de URL — precies wat hier stond. Twee
 * schermen op dezelfde tabel betekent twee plekken waar je moet kijken en twee plekken
 * die uit de pas kunnen lopen.
 *
 * Dit bestand blijft staan als doorverwijzing: er liggen links naar in oude mails, in
 * het changelog en in de handleiding, en die horen gewoon ergens uit te komen.
 *
 * Wat bewust NIET meeverhuist is de knop "log legen". Een foutenlog met de hand wissen
 * is bewijs weggooien; hoe lang fouten bewaard blijven stel je in bij
 * Instellingen › Bewaartermijnen, en het opschonen gebeurt daar automatisch.
 */
require_once __DIR__ . '/../includes/functions.php';
// Hetzelfde recht als het scherm waar we heen sturen: anders krijgt iemand die het
// logboek wél mag lezen hier een weigering op een link die daarna gewoon werkt.
vereis_recht('logboek', 'lezen');

redirect(APP_URL . '/admin/logboek.php?bron=fout');
