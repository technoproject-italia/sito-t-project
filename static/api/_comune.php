<?php
declare(strict_types=1);

function tp_config(): array
{
    $file = __DIR__ . '/config.php';
    if (!is_file($file)) {
        tp_json(['ok' => false, 'errore' => 'Servizio non configurato.'], 503);
    }
    return require $file;
}

function tp_json(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Blocca le chiamate provenienti da altri siti. */
function tp_controlla_origine(array $cfg): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && !in_array($origin, $cfg['origini_consentite'] ?? [], true)) {
        tp_json(['ok' => false, 'errore' => 'Origine non autorizzata.'], 403);
    }
}

/** Limite di richieste per IP in una finestra di tempo, salvato su file (nessun dato personale in chiaro). */
function tp_limite(string $chiave, int $max, int $secondi): bool
{
    $dir = __DIR__ . '/dati-temp';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0';
    $file = $dir . '/' . $chiave . '-' . hash('sha256', $ip . date('Y-m-d')) . '.json';
    $ora = time();
    $voci = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
    $voci = array_values(array_filter($voci, fn ($t) => $t > $ora - $secondi));
    if (count($voci) >= $max) {
        return false;
    }
    $voci[] = $ora;
    file_put_contents($file, json_encode($voci), LOCK_EX);

    // pulizia dei file più vecchi di un giorno
    if (random_int(1, 50) === 1) {
        foreach (glob($dir . '/*.json') ?: [] as $f) {
            if (filemtime($f) < $ora - 86400) {
                @unlink($f);
            }
        }
    }
    return true;
}
