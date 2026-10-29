<?php
/*
 * Riceve il modulo di contatto e invia una email al titolare.
 * Risponde in JSON alle richieste fatte da JavaScript; altrimenti reindirizza
 * a contatti.html?inviato=1 (funziona anche con JavaScript disattivato).
 */
declare(strict_types=1);
require __DIR__ . '/_comune.php';

$ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

function esito(bool $ok, string $errore = '', int $status = 200): never
{
    global $ajax;
    if ($ajax) {
        tp_json($ok ? ['ok' => true] : ['ok' => false, 'errore' => $errore], $status);
    }
    header('Location: ../contatti.html?inviato=' . ($ok ? '1' : '0') . '#modulo', true, 303);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    esito(false, 'Metodo non consentito.', 405);
}

$cfg = tp_config();
tp_controlla_origine($cfg);

// Honeypot: i bot compilano anche il campo nascosto
if (!empty($_POST['sito_web'])) {
    esito(true);
}
if (!tp_limite('contatto', 5, 3600)) {
    esito(false, 'Hai inviato troppe richieste. Riprova tra un\'ora o scrivici via email.', 429);
}

$campo = static function (string $nome, int $max) {
    $v = trim((string) ($_POST[$nome] ?? ''));
    $v = str_replace(["\r", "\0"], '', $v);
    return mb_substr($v, 0, $max);
};

$nome      = $campo('nome', 100);
$azienda   = $campo('azienda', 120);
$email     = $campo('email', 160);
$telefono  = $campo('telefono', 30);
$tipo      = $campo('tipo', 80);
$budget    = $campo('budget', 40);
$messaggio = $campo('messaggio', 5000);
$privacy   = ($_POST['privacy'] ?? '') === '1';

if (mb_strlen($nome) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($messaggio) < 10 || !$privacy) {
    esito(false, 'Controlla i campi obbligatori e il consenso privacy.', 422);
}

// Nessun ritorno a capo nelle intestazioni (protezione da header injection)
$nomeHeader = preg_replace('/[\r\n]+/', ' ', $nome);

$corpo = "Nuova richiesta dal sito\n"
    . "--------------------------------\n"
    . "Nome: {$nome}\n"
    . "Azienda: " . ($azienda ?: '-') . "\n"
    . "Email: {$email}\n"
    . "Telefono: " . ($telefono ?: '-') . "\n"
    . "Tipo di progetto: {$tipo}\n"
    . "Budget: " . ($budget ?: 'non indicato') . "\n"
    . "Consenso privacy: sì, " . date('d/m/Y H:i') . "\n"
    . "--------------------------------\n\n"
    . $messaggio . "\n";

$headers = [
    'From' => 'Sito Technoproject <' . $cfg['email_mittente'] . '>',
    'Reply-To' => mb_encode_mimeheader($nomeHeader) . ' <' . $email . '>',
    'Content-Type' => 'text/plain; charset=UTF-8',
    'X-Mailer' => 'technoproject-sito',
];

$oggetto = mb_encode_mimeheader($cfg['oggetto'] . ' — ' . $tipo);
$inviata = mail($cfg['email_destinatario'], $oggetto, $corpo, $headers, '-f' . $cfg['email_mittente']);

$inviata ? esito(true) : esito(false, 'Non siamo riusciti a inviare il messaggio. Riprova o scrivici via email.', 500);
