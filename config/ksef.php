<?php

return [
    // Adresy API KSeF 2.0. Wybór środowiska w aplikacji: Ustawienia → KSeF.
    'environments' => [
        'test' => 'https://api-test.ksef.mf.gov.pl/v2',
        'demo' => 'https://api-demo.ksef.mf.gov.pl/v2',
        'prod' => 'https://api.ksef.mf.gov.pl/v2',
    ],

    'timeout' => (int) env('KSEF_TIMEOUT', 30),

    // Ścieżka do pakietu certyfikatów CA, gdy PHP nie ma ustawionego curl.cainfo (np. lokalnie na Windows).
    'ca_bundle' => env('KSEF_CA_BUNDLE'),

    // Liczba prób sprawdzenia statusu uwierzytelnienia (co sekundę).
    'auth_poll_attempts' => (int) env('KSEF_AUTH_POLL_ATTEMPTS', 15),

    'page_size' => 100,

    // Limity API (HTTP 429): ile razy ponowić zapytanie i ile łącznie sekund czekać (wg nagłówka Retry-After).
    // Przy dłuższej blokadzie (np. limit godzinowy) pobieranie jest przerywane – resztę pobierze kolejne uruchomienie.
    'rate_limit_retries' => (int) env('KSEF_RATE_LIMIT_RETRIES', 5),
    'rate_limit_max_wait' => (int) env('KSEF_RATE_LIMIT_MAX_WAIT', 180),
];
