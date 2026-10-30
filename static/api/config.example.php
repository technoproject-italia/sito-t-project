<?php
/*
 * CONFIGURAZIONE — copia questo file come "config.php" nella stessa cartella
 * e inserisci i valori reali. config.php non è raggiungibile dal web (.htaccess).
 */
return [
    // --- Modulo di contatto ---
    'email_destinatario' => 'info@__DOMINIO__',      // dove arrivano le richieste
    'email_mittente'     => 'info@__DOMINIO__',      // casella esistente su Hostinger (stesso dominio)
    'oggetto'            => 'Nuova richiesta dal sito __DOMINIO__',
    // Invio autenticato dalla casella email_mittente; senza password si usa mail() del server
    'smtp_host'          => 'smtp.hostinger.com',
    'smtp_porta'         => 465,
    'smtp_password'      => '',

    // --- Chatbot (Claude) ---
    // false: l'endpoint rifiuta le richieste anche con la chiave inserita (vedi CHATBOT_ATTIVO in build.py)
    'chatbot_attivo'     => false,
    // Chiave da https://console.anthropic.com  (lascia vuoto per usare solo la modalità locale)
    'anthropic_api_key'  => '',
    'modello'            => 'claude-opus-5-5',
    'effort'             => 'low',        // low = risposte rapide ed economiche; medium/high = più ragionate
    'max_messaggi_ora'   => 40,           // limite per indirizzo IP, protegge dai costi

    // Domini autorizzati a chiamare le API (protezione da uso esterno)
    'origini_consentite' => ['https://__DOMINIO__', 'https://www.__DOMINIO__'],
];
