<?php
/*
 * Assistente virtuale: riceve la conversazione dal browser e chiede la risposta a Claude,
 * usando come contesto la base di conoscenza in ../chatbot/conoscenza.json.
 * Il testo delle conversazioni non viene salvato sul server.
 */
declare(strict_types=1);
require __DIR__ . '/_comune.php';
require __DIR__ . '/vendor/autoload.php';

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\RateLimitException;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    tp_json(['errore' => 'Metodo non consentito.'], 405);
}

$cfg = tp_config();
tp_controlla_origine($cfg);

if (empty($cfg['anthropic_api_key'])) {
    // il widget passa automaticamente alla modalità locale
    tp_json(['errore' => 'AI non configurata.'], 503);
}
if (!tp_limite('chat', (int) ($cfg['max_messaggi_ora'] ?? 40), 3600)) {
    tp_json(['reply' => "Hai fatto molte domande in poco tempo. Per continuare scrivici dalla pagina Contatti: ti risponde una persona del nostro team."], 429);
}

// --- Conversazione dal browser: solo ruoli validi, testi brevi, ultimi 12 messaggi ---
$input = json_decode((string) file_get_contents('php://input'), true);
$messaggi = [];
foreach (array_slice((array) ($input['messages'] ?? []), -12) as $m) {
    $ruolo = $m['role'] ?? '';
    $testo = trim((string) ($m['content'] ?? ''));
    if (!in_array($ruolo, ['user', 'assistant'], true) || $testo === '') {
        continue;
    }
    $messaggi[] = ['role' => $ruolo, 'content' => mb_substr($testo, 0, 1000)];
}
// la conversazione deve iniziare dall'utente e finire con l'utente
while ($messaggi && $messaggi[0]['role'] !== 'user') {
    array_shift($messaggi);
}
if (!$messaggi || end($messaggi)['role'] !== 'user') {
    tp_json(['errore' => 'Messaggio mancante.'], 400);
}

// --- Prompt di sistema dalla base di conoscenza (stabile: viene messo in cache) ---
$kb = json_decode((string) file_get_contents(__DIR__ . '/../chatbot/conoscenza.json'), true);
$system = "Sei l'assistente virtuale del sito di {$kb['azienda']['ragione_sociale']}. "
    . "Aiuti i visitatori a capire cosa l'azienda può realizzare per loro e li accompagni verso una richiesta di preventivo.\n\n"
    . "REGOLE\n- " . implode("\n- ", $kb['regole']) . "\n\n"
    . "DATI AZIENDALI\n" . json_encode($kb['azienda'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n"
    . "BASE DI CONOSCENZA\n";
foreach ($kb['argomenti'] as $a) {
    $system .= "## {$a['titolo']}\n{$a['risposta']}\n\n";
}

try {
    $client = new Client(apiKey: $cfg['anthropic_api_key']);
    $risposta = $client->beta->messages->create(
        model: $cfg['modello'] ?? 'claude-opus-5-5',
        maxTokens: 2000,
        system: [['type' => 'text', 'text' => $system, 'cacheControl' => ['type' => 'ephemeral']]],
        messages: $messaggi,
        outputConfig: ['effort' => $cfg['effort'] ?? 'low'],
        // se il modello declina una richiesta, il server riprova su un modello alternativo
        fallbacks: 'default',
        betas: ['server-side-fallback-2026-07-01'],
    );

    if ($risposta->stopReason === 'refusal') {
        tp_json(['reply' => "Su questo argomento non posso aiutarti. Posso però raccontarti cosa sviluppiamo: gestionali, siti, app e soluzioni di intelligenza artificiale."]);
    }

    $testo = '';
    foreach ($risposta->content as $blocco) {
        if ($blocco->type === 'text') {
            $testo .= $blocco->text;
        }
    }
    $testo = trim($testo);
    if ($testo === '') {
        throw new RuntimeException('Risposta vuota');
    }
    tp_json(['reply' => $testo]);
} catch (RateLimitException $e) {
    tp_json(['errore' => 'Servizio momentaneamente occupato.'], 503);
} catch (APIStatusException $e) {
    error_log('[chat] errore API ' . $e->getMessage());
    tp_json(['errore' => 'Servizio non disponibile.'], 502);
} catch (APIConnectionException $e) {
    error_log('[chat] connessione non riuscita ' . $e->getMessage());
    tp_json(['errore' => 'Servizio non raggiungibile.'], 502);
} catch (Throwable $e) {
    error_log('[chat] ' . $e->getMessage());
    tp_json(['errore' => 'Errore interno.'], 500);
}
