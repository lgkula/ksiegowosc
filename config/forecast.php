<?php

return [
    // Klient, dla którego wystawiana jest faktura z wynagrodzeniem podstawowym i dodatkami.
    'kruk' => [
        // NIP kontrahenta (opcjonalnie); gdy pusty, faktura rozpoznawana jest po nazwie.
        'nip' => env('FORECAST_KRUK_NIP'),
        // Wyrażenie regularne na nazwę kontrahenta.
        'name_pattern' => '/kruk/iu',
        // Pozycje faktury pasujące do wzorca traktowane są jako dodatki, pozostałe jako wynagrodzenie podstawowe.
        'additions_pattern' => '/(dodat|premi|bonus|nadgodzin|dyżur)/iu',
        'vat_rate' => 23,
        // Faktura za miesiąc liczy się jako wystawiona, gdy jej netto przekracza próg; inaczej podstawa
        // wynagrodzenia podpowiada się z poprzedniego miesiąca.
        'invoiced_threshold' => 5000,
    ],

    // Podwykonawca rozliczany godzinowo (Joanna Kokot Usługi Informatyczne).
    'subcontractor' => [
        'nip' => '9161389290',
        'name' => 'Joanna Kokot Usługi Informatyczne',
        'vat_rate' => 23,
        // Domyślna stawka PIT wystawcy (% od netto) do wyliczenia obciążeń faktury; zmienia się ją na stronie prognozy.
        'pit_rate' => 12,
    ],
];
