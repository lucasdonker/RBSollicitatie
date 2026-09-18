<?php
/**
 * Verificatiecode (2FA) aanvragen vóór het ondertekenen. POST token=<ondertekening-token> (+ CSRF).
 * Stuurt een 6-cijferige code naar het eigen telefoonnummer (SMS → WhatsApp → e-mail) en antwoordt JSON:
 *   {ok:true, kanaal:'sms'|'whatsapp'|'email', naar:'06******78'} of {ok:false, fout:'...'}.
 * Zelfde toegang als het ondertekenscherm: de token is de sleutel; ingelogd zijn hoeft niet (ouder-route).
 */
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$uit = function (array $a, int $code = 200): never { http_response_code($code); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; };
if ($_SERVER['REQUEST_METHOD'] !== 'POST') $uit(['ok' => false, 'fout' => 'Alleen POST.'], 405);
try { csrf_check(); } catch (\Throwable $e) { $uit(['ok' => false, 'fout' => 'Sessie verlopen. Laad de pagina opnieuw.'], 403); }

$token = trim((string)($_POST['token'] ?? ''));
if ($token === '' || !preg_match('/^[A-Za-z0-9]{3,64}$/', $token)) $uit(['ok' => false, 'fout' => 'Ongeldige aanvraag.'], 400);

$q = db()->prepare("SELECT o.*, p.email AS sol_email, p.telefoon AS sol_telefoon, p.ouder_telefoon, p.voornaam
                    FROM ondertekeningen o JOIN sollicitaties s ON s.id=o.sollicitatie_id JOIN personen p ON p.id=s.persoon_id WHERE o.token=?");
$q->execute([$token]);
$rec = $q->fetch();
if (!$rec) $uit(['ok' => false, 'fout' => 'Document niet gevonden.'], 404);
if (!empty($rec['ondertekend_op'])) $uit(['ok' => false, 'fout' => 'Dit document is al ondertekend.'], 409);
if (!ondertekening_2fa_nodig((string)$rec['type'])) $uit(['ok' => false, 'fout' => 'Voor dit document is geen code nodig.'], 400);

$is_ouder = ($rec['ondertekenaar_rol'] === 'ouder');
$ontvanger = $is_ouder
    ? ['email' => (string)$rec['ondertekenaar_email'], 'naam' => (string)($rec['ondertekenaar_naam'] ?: 'ouder/verzorger'), 'telefoon' => (string)($rec['ouder_telefoon'] ?? '')]
    : ['email' => (string)$rec['sol_email'], 'naam' => (string)$rec['voornaam'], 'telefoon' => (string)($rec['sol_telefoon'] ?? '')];

$r = ondertekening_code_versturen((int)$rec['id'], $ontvanger);
if (!$r['ok']) $uit(['ok' => false, 'fout' => $r['fout']], 429);
$uit(['ok' => true, 'kanaal' => $r['kanaal'], 'naar' => $r['kanaal'] === 'email' ? $ontvanger['email'] : ondertekening_tel_masker($ontvanger['telefoon'])]);
