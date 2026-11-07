<?php

return [
    // API kursów walut NBP (https://api.nbp.pl). Pakiet CA bierze się z KSEF_CA_BUNDLE.
    'base_url' => env('NBP_BASE_URL', 'https://api.nbp.pl/api'),

    'timeout' => (int) env('NBP_TIMEOUT', 10),
];
