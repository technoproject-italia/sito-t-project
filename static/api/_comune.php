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

/**
 * Carica le librerie PHP. Il deploy carica vendor.zip (un solo file invece di migliaia via FTP):
 * alla prima richiesta dopo ogni aggiornamento l'archivio viene estratto in vendor/.
 */
function tp_vendor(): void
{
    $zip = __DIR__ . '/vendor.zip';
    $dir = __DIR__ . '/vendor';
    if (is_file($zip)) {
        $versione = (string) hash_file('sha256', $zip);
        if (@file_get_contents($dir . '/.versione') !== $versione) {
            $lock = fopen(__DIR__ . '/vendor.lock', 'c');
            flock($lock, LOCK_EX);
            // un'altra richiesta può averlo estratto mentre aspettavamo il lock
            if (@file_get_contents($dir . '/.versione') !== $versione) {
                $tmp = $dir . '-nuovo';
                tp_rimuovi_cartella($tmp);
                $archivio = new ZipArchive();
                if ($archivio->open($zip) !== true || !$archivio->extractTo($tmp)) {
                    throw new RuntimeException('Estrazione di vendor.zip non riuscita');
                }
                $archivio->close();
                file_put_contents($tmp . '/vendor/.versione', $versione);
                tp_rimuovi_cartella($dir);
                rename($tmp . '/vendor', $dir);
                tp_rimuovi_cartella($tmp);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
    require $dir . '/autoload.php';
}

function tp_rimuovi_cartella(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $voci = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($voci as $voce) {
        $voce->isDir() && !$voce->isLink() ? rmdir($voce->getPathname()) : unlink($voce->getPathname());
    }
    rmdir($dir);
}

/** Invia una email in SMTP autenticato su SSL (porta 465), dalla casella email_mittente. */
function tp_smtp_invia(array $cfg, string $a, string $oggetto, string $corpo, array $headers): void
{
    $sock = @stream_socket_client('ssl://' . $cfg['smtp_host'] . ':' . (int) $cfg['smtp_porta'], $errno, $errstr, 15);
    if (!$sock) {
        throw new RuntimeException("connessione non riuscita: {$errstr}");
    }
    stream_set_timeout($sock, 15);
    $comando = static function (?string $riga, int $atteso) use ($sock): void {
        if ($riga !== null) {
            fwrite($sock, $riga . "\r\n");
        }
        $risposta = '';
        while (($l = fgets($sock, 1024)) !== false) {
            $risposta .= $l;
            if (strlen($l) < 4 || $l[3] === ' ') {
                break;
            }
        }
        if ((int) substr($risposta, 0, 3) !== $atteso) {
            throw new RuntimeException('risposta inattesa: ' . trim($risposta));
        }
    };
    $mittente = $cfg['email_mittente'];
    $comando(null, 220);
    $comando('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), 250);
    $comando('AUTH LOGIN', 334);
    $comando(base64_encode($mittente), 334);
    $comando(base64_encode($cfg['smtp_password']), 235);
    $comando("MAIL FROM:<{$mittente}>", 250);
    $comando("RCPT TO:<{$a}>", 250);
    $comando('DATA', 354);
    $intestazioni = ['To' => $a, 'Subject' => $oggetto, 'Date' => date('r'), 'MIME-Version' => '1.0'] + $headers;
    $messaggio = '';
    foreach ($intestazioni as $k => $v) {
        $messaggio .= "{$k}: {$v}\r\n";
    }
    // righe che iniziano con un punto vanno raddoppiate (RFC 5321)
    $messaggio .= "\r\n" . preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], ["\n", "\r\n"], $corpo));
    $comando($messaggio . "\r\n.", 250);
    $comando('QUIT', 221);
    fclose($sock);
}
